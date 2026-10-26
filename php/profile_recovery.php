<?php
// Shared routing and account binding for authenticated password changes.
function isProfileRecovery(): bool {
    return isset($_SESSION['profile_recovery_user_id']);
}

function requireProfileRecoveryAccount(PDO $pdo, ?int $targetId = null): void {
    if (!isProfileRecovery()) return;
    if (($_SESSION['role'] ?? '') === 'SUPER_ADMIN') {
        require_once __DIR__ . '/superadmin_session.php';
        enforceSuperAdminSession($pdo, ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST');
    }
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    $boundId = (int)$_SESSION['profile_recovery_user_id'];
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ? AND role IN ('ADMIN', 'SUPER_ADMIN') AND status <> 'BLOCKED'");
    $stmt->execute([$actorId]);
    $accountRole = $stmt->fetchColumn();
    if (!$actorId || $actorId !== $boundId || ($targetId !== null && $targetId !== $actorId) || !$accountRole) {
        http_response_code(403);
        exit('This password-change session is no longer valid.');
    }
    if ($accountRole === 'SUPER_ADMIN' && ($_SESSION['role'] ?? '') !== 'SUPER_ADMIN') {
        http_response_code(403);
        exit('This password-change session is no longer valid.');
    }
}

function recoveryLocation(string $stage): string {
    if (isProfileRecovery()) return 'app.php?page=profile_password&stage=' . $stage;
    return 'app.php?page=' . ['start'=>'forgot_password', 'questions'=>'security_questions', 'otp'=>'otp_verification', 'password'=>'change_password'][$stage];
}

function recoveryAccount(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare('SELECT id, id_number, username, email, role, status, first_login_required, temporary_credential_expires_at FROM users WHERE id = ? AND role IN (\'USER\', \'ADMIN\', \'SUPER_ADMIN\') LIMIT 1');
    $stmt->execute([$userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    return $account ?: null;
}

function recoveryEligibilityMessage(?array $account): ?string {
    if (!$account) return 'We could not verify an account using the information provided.';
    if ($account['status'] === 'BLOCKED') {
        return 'Account Blocked: This account is currently blocked. Please contact an administrator for assistance.';
    }
    if (!empty($account['first_login_required'])) {
        return 'Account Setup Incomplete: This account has not completed its initial setup. Please contact a Super Administrator for assistance.';
    }
    if ($account['status'] === 'PENDING' && $account['role'] === 'USER') {
        return 'Account Pending Approval: Your account is still awaiting approval.';
    }
    if ($account['status'] === 'PENDING' || $account['status'] === 'REJECTED') {
        return 'This account is not currently eligible for password recovery.';
    }
    if (!in_array($account['status'], ['ACTIVE', 'INACTIVE'], true)) {
        return 'This account is not currently eligible for password recovery.';
    }
    if (!filter_var($account['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Your account does not have a valid recovery email. Please contact an administrator.';
    }
    return null;
}

function recoveryQuestionBank(): array {
    return [
        'Who is your best friend in Elementary?',
        'What is the name of your favorite pet?',
        'Who is your favorite teacher in high school?',
        'What was the name of your first school?',
        "What is your mother's maiden name?",
        'What city were you born in?',
        'What was your childhood nickname?',
        'What is the name of your first car?',
        'What was your favorite subject in school?',
    ];
}
