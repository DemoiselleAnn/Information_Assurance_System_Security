<?php
require_once __DIR__ . '/security_log_helpers.php';
// ---------------------------------------------------------------------------
// process_first_login_setup.php — Backend processor for first-login account
// setup.  This performs an UPDATE on the existing Administrator account.
// It NEVER inserts a new users row.
//
// Security guarantees:
//  – Role is read from the database, never from POST data.
//  – first_login_required is cleared only after ALL steps succeed.
//  – The recovery challenge is consumed atomically with the password update.
//  – Security answers are hashed before storage; plain-text is never logged.
//  – Duplicate submission is handled by re-checking first_login_required.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once __DIR__ . '/superadmin_session.php';
require_once __DIR__ . '/first_login_state.php';
require_once __DIR__ . '/first_login_activation.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ── Helper: abort back to setup with errors ───────────────────────────────────
function setupError(array $errors, array $oldValues = [], string $reason = 'invalid_setup_input'): void {
    firstLoginSetupDiagnostic($reason);
    $_SESSION['first_login_setup_errors'] = $errors;
    $_SESSION['first_login_setup_old']    = $oldValues;
    $_SESSION['first_login_stage']        = 'setup_error';
    header('Location: app.php?page=first_login_setup');
    exit;
}

// ── Helper: log security event ────────────────────────────────────────────────
function logFirstLoginEvent(PDO $pdo, int $userId, string $username, string $fullName, string $role, string $action, string $status, ?string $reason = null): void {
    try {
        $identity = securityLogAccountIdentity($pdo, $userId);
        $fullName = securityLogFullName($identity) ?: $fullName;
        $pdo->prepare(
            "INSERT INTO security_logs
             (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $userId, $username, $fullName, $role, $action,
            $_SERVER['REMOTE_ADDR']    ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $identity['id_number'] ?? null,
            $status,
            $reason
        ]);
    } catch (Throwable $e) {
        error_log('First login setup log error: ' . $e->getMessage());
    }
}

// ── Helper: normalize security answer (mirrors process_forgot_password.php) ──
function normalizeAnswer(string $answer): string {
    return strtolower(trim(preg_replace('/\s+/', ' ', $answer)));
}

// ── Reject non-POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: app.php?page=first_login_setup');
    exit;
}

// ── Session guard ─────────────────────────────────────────────────────────────
$userId      = (int)($_SESSION['first_login_user_id']      ?? 0);
$challengeId = (int)($_SESSION['first_login_challenge_id'] ?? 0);
$stage       = $_SESSION['first_login_stage'] ?? '';

if (!$userId || !$challengeId || !in_array($stage, ['otp_verified', 'setup_error'], true)) {
    endFirstLoginSetupSession($userId && $challengeId
        ? 'Your verification session is no longer valid. Please verify your account again.'
        : 'Your setup session has expired. Please log in again.', 'invalid_setup_session');
}

requireUnexpiredFirstLoginAdministrator($pdo, $userId);

// ── CSRF check ────────────────────────────────────────────────────────────────
if (!is_string($_SESSION['first_login_setup_csrf'] ?? null) || $_SESSION['first_login_setup_csrf'] === ''
    || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['first_login_setup_csrf'], $_POST['csrf_token'])) {
    setupError(['Request verification failed. Refresh the setup page and try again.'], [], 'invalid_csrf');
}

// ── Re-fetch account from DB (never trust POST for identity/role) ─────────────
$stmt = $pdo->prepare(
    "SELECT u.id, u.id_number, u.username, u.email,
            u.first_name, u.middle_name, u.last_name, u.extension_name,
            u.role, u.status, u.first_login_required, u.password AS current_password_hash
     FROM users u
     JOIN password_recovery r ON r.user_id = u.id
     WHERE u.id = ?
       AND r.id = ?
       AND r.otp_verified = 1
       AND r.used_at IS NULL
    AND u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN')
       AND u.first_login_required = 1
     LIMIT 1"
);
$stmt->execute([$userId, $challengeId]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$account) {
    endFirstLoginSetupSession('Your verification session is no longer valid. Please verify your account again.', 'invalid_verified_challenge');
}

if ($account['status'] === 'BLOCKED') {
    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_username'],
        $_SESSION['first_login_full_name'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_role'],
        $_SESSION['first_login_challenge_id'],
        $_SESSION['first_login_stage'],
        $_SESSION['first_login_otp_csrf'],
        $_SESSION['first_login_setup_csrf']
    );
    $_SESSION['login_flash'] = [
        'error_message'       => 'Your account has been blocked. Please contact a Super Administrator.',
        'remaining_time'      => 0,
        'attempt_count_display' => 0,
        'locked_username'     => '',
    ];
    header('Location: app.php?page=login');
    exit;
}

$dbUserId  = (int)$account['id'];
$username  = $account['username'];
$fullName  = securityLogFullName($account);

// ── Collect and validate POST values ─────────────────────────────────────────
$errors    = [];
$oldValues = [];

// ── Password ──────────────────────────────────────────────────────────────────
$newPassword     = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
$confirmPassword = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';

if ($newPassword === '') {
    $errors[] = 'New password is required.';
} elseif (strlen($newPassword) < 8 || strlen($newPassword) > 20) {
    $errors[] = 'Password must be 8–20 characters.';
} elseif (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword)
       || !preg_match('/\d/',   $newPassword) || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
    $errors[] = 'Password must contain uppercase, lowercase, a number, and a special character.';
} elseif ($newPassword !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
} elseif (password_verify($newPassword, $account['current_password_hash'])) {
    // Prevent reuse of the temporary password
    $errors[] = 'Your new password must be different from the temporary password.';
}

// ── Security questions ────────────────────────────────────────────────────────
$questionPools = [
    1 => [
        'Who is your best friend in Elementary?',
        'What is the name of your favorite pet?',
        'Who is your favorite teacher in high school?',
    ],
    2 => [
        'What was the name of your first school?',
        "What is your mother's maiden name?",
        'What city were you born in?',
    ],
    3 => [
        'What was your childhood nickname?',
        'What is the name of your first car?',
        'What was your favorite subject in school?',
    ],
];

$questions = [];
$answers   = [];

for ($i = 1; $i <= 3; $i++) {
    $q = is_string($_POST["question{$i}"] ?? null) ? trim($_POST["question{$i}"]) : '';
    $a = is_string($_POST["answer{$i}"] ?? null) ? trim($_POST["answer{$i}"]) : '';

    // Validate question belongs to the correct pool
    if (!in_array($q, $questionPools[$i], true)) {
        $errors[] = "Security question {$i} is invalid.";
    }
    if ($a === '') {
        $errors[] = "Answer for security question {$i} is required.";
    }

    $oldValues["question{$i}"] = $q;
    $questions[$i] = $q;
    $answers[$i]   = $a;
}

// Duplicate question check
$uniqueQuestions = array_unique(array_filter($questions));
if (count($uniqueQuestions) < 3) {
    $errors[] = 'Please select three different security questions.';
}

if ($errors) {
    setupError($errors, $oldValues);
}

// ── All validation passed — commit atomically ─────────────────────────────────
$pdo->beginTransaction();
try {
    // Lock the user before touching dependent records; cleanup must lock in the same order.
    lockFirstLoginAccountForSetup($pdo, $dbUserId, $account['role']);
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // 2. Delete any existing security answers for this account (idempotent)
    $pdo->prepare('DELETE FROM security_answers WHERE user_id = ?')
        ->execute([$dbUserId]);

    // 3. Insert the three new security answers (hashed, never plain-text)
    $insertAnswer = $pdo->prepare(
        'INSERT INTO security_answers (user_id, question, answer) VALUES (?, ?, ?)'
    );
    for ($i = 1; $i <= 3; $i++) {
        $hashedAnswer = password_hash(normalizeAnswer($answers[$i]), PASSWORD_DEFAULT);
        $insertAnswer->execute([$dbUserId, $questions[$i], $hashedAnswer]);
    }

    // 4. Consume the OTP challenge
    $consume = $pdo->prepare(
        'UPDATE password_recovery
         SET used_at = NOW()
         WHERE id = ? AND user_id = ? AND otp_verified = 1 AND used_at IS NULL'
    );
    $consume->execute([$challengeId, $dbUserId]);
    if ($consume->rowCount() !== 1) {
        throw new RuntimeException('Recovery challenge consumption failed.');
    }

    // Clear the setup flag only after all setup writes, before committing atomically.
    completeFirstLoginAccount($pdo, $dbUserId, $account['role'], $hashedPassword);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($e instanceof FirstLoginActivationUnavailable) {
        endFirstLoginSetupSession('Your initial credentials have expired or account setup is no longer available. Please contact an administrator.', 'expired_or_unavailable_setup');
    }
    error_log('First-login setup commit error: type=' . get_class($e) . ' code=' . $e->getCode());
    setupError(["We couldn't complete your account setup. Please try again."], $oldValues, 'database_error');
}

// Erase plain-text password/answers from memory
$newPassword     = str_repeat('0', strlen($newPassword));
$confirmPassword = str_repeat('0', strlen($confirmPassword));
for ($i = 1; $i <= 3; $i++) {
    if (isset($answers[$i])) {
        $answers[$i] = str_repeat('0', strlen($answers[$i]));
    }
}
unset($newPassword, $confirmPassword, $answers);

// ── Security logging (no password/answer values logged) ──────────────────────
logFirstLoginEvent($pdo, $dbUserId, $username, $fullName, $account['role'], 'FIRST_LOGIN_PASSWORD_CHANGED', 'SUCCESS');
logFirstLoginEvent($pdo, $dbUserId, $username, $fullName, $account['role'], 'FIRST_LOGIN_SECURITY_QUESTIONS_SET', 'SUCCESS');
logFirstLoginEvent($pdo, $dbUserId, $username, $fullName, $account['role'], 'FIRST_LOGIN_SETUP_COMPLETED', 'SUCCESS');

// ── Establish full authenticated session ──────────────────────────────────────
// Clear first-login session state
unset(
    $_SESSION['first_login_user_id'],
    $_SESSION['first_login_username'],
    $_SESSION['first_login_full_name'],
    $_SESSION['first_login_email'],
    $_SESSION['first_login_role'],
    $_SESSION['first_login_challenge_id'],
    $_SESSION['first_login_stage'],
    $_SESSION['first_login_otp_csrf'],
    $_SESSION['first_login_setup_csrf'],
    $_SESSION['first_login_setup_errors'],
    $_SESSION['first_login_setup_old']
);

// Re-fetch account for session data (role confirmed from DB)
$fresh = $pdo->prepare(
    "SELECT id, username, first_name, middle_name, last_name, extension_name, role
     FROM users WHERE id = ? LIMIT 1"
);
$fresh->execute([$dbUserId]);
$freshAccount = $fresh->fetch(PDO::FETCH_ASSOC);

if ($freshAccount) {
    session_regenerate_id(true);
    unset($_SESSION['superadmin_session_token'], $_SESSION['superadmin_superseded_logged']);
    $_SESSION['user_id']              = (int)$freshAccount['id'];
    $_SESSION['username']             = $freshAccount['username'];
    $_SESSION['role']                 = $freshAccount['role'];   // always from DB
    $_SESSION['full_name']            = securityLogFullName($freshAccount);
    $_SESSION['security_session_key'] = bin2hex(random_bytes(32));
    try {
        if (($freshAccount['role'] ?? '') === 'SUPER_ADMIN') {
            $_SESSION['superadmin_session_token'] = activateSuperAdminSession($pdo, (int)$freshAccount['id']);
            if (superAdminSessionState($pdo)['status'] === 'active') superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_STARTED', 'A new Super Administrator session became authoritative after account setup.');
        }
        startUserPresence($pdo, (int)$freshAccount['id'], $_SESSION['security_session_key']);
    } catch (Throwable $e) {
        // Setup has committed. A failed login acquisition must not undo it or
        // leave a partially authenticated Super Admin session behind.
        error_log('First-login post-setup authentication error: type=' . get_class($e) . ' code=' . $e->getCode());
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['full_name'], $_SESSION['security_session_key'], $_SESSION['superadmin_session_token']);
        $_SESSION['login_flash'] = ['error_message' => 'Account setup is complete. Please log in with your new password.', 'remaining_time' => 0, 'attempt_count_display' => 0, 'locked_username' => ''];
        header('Location: app.php?page=login');
        exit;
    }
    $_SESSION['setup_completed_banner'] = 'Account setup completed successfully. Welcome to FROSTLINE.';
}

header('Location: app.php?page=' . (($freshAccount['role'] ?? '') === 'USER' ? 'customer_dashboard' : 'account_management'));
exit;
