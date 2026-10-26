<?php
require_once __DIR__ . '/security_log_helpers.php';
// ---------------------------------------------------------------------------
// first_login_otp.php — OTP verification screen for first-login flow
// Reuses the password_recovery table and forgot_password.css styling.
// ---------------------------------------------------------------------------
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'recovery_mail.php';
require_once __DIR__ . '/first_login_state.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ── Session guard ────────────────────────────────────────────────────────────
// Must have a valid first-login session at exactly the otp_pending stage.
$userId      = (int)($_SESSION['first_login_user_id']      ?? 0);
$challengeId = (int)($_SESSION['first_login_challenge_id'] ?? 0);
$stage       = $_SESSION['first_login_stage'] ?? '';

if (!$userId || !$challengeId || $stage !== 'otp_pending') {
    // Clean up any partial first-login state and send back to login
    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_username'],
        $_SESSION['first_login_full_name'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_role'],
        $_SESSION['first_login_challenge_id'],
        $_SESSION['first_login_stage'],
        $_SESSION['first_login_otp_csrf']
    );
    header('Location: app.php?page=login');
    exit;
}

requireUnexpiredFirstLoginAdministrator($pdo, $userId);

// ── Verify the challenge still exists and is valid ───────────────────────────
$stmt = $pdo->prepare(
    "SELECT r.expires_at, r.attempts, r.resend_count, r.last_sent_at, u.email, u.status, u.role
     FROM password_recovery r
     JOIN users u ON u.id = r.user_id
     WHERE r.id = ?
       AND r.user_id = ?
       AND r.used_at IS NULL
       AND r.otp_verified = 0
    AND u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN')
    AND u.first_login_required = 1
    AND u.status <> 'BLOCKED'
     LIMIT 1"
);
$stmt->execute([$challengeId, $userId]);
$challenge = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$challenge) {
    // OTP no longer valid — send back to login
    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_username'],
        $_SESSION['first_login_full_name'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_role'],
        $_SESSION['first_login_challenge_id'],
        $_SESSION['first_login_stage'],
        $_SESSION['first_login_otp_csrf']
    );
    $_SESSION['login_flash'] = [
        'error_message'       => 'Your verification session has expired. Please log in again.',
        'remaining_time'      => 0,
        'attempt_count_display' => 0,
        'locked_username'     => '',
    ];
    header('Location: app.php?page=login');
    exit;
}

// Account blocked mid-flow
if ($challenge['status'] === 'BLOCKED') {
    unset(
        $_SESSION['first_login_user_id'],
        $_SESSION['first_login_username'],
        $_SESSION['first_login_full_name'],
        $_SESSION['first_login_email'],
        $_SESSION['first_login_role'],
        $_SESSION['first_login_challenge_id'],
        $_SESSION['first_login_stage'],
        $_SESSION['first_login_otp_csrf']
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

// ── Handle POST (verify or resend) ───────────────────────────────────────────
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!hash_equals($_SESSION['first_login_otp_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Your session has expired. Please log in again.';
    } else {
        $action = $_POST['action'] ?? 'verify';

        // ── Resend ────────────────────────────────────────────────────────
        if ($action === 'resend') {
            if ((int)$challenge['resend_count'] >= 3) {
                // Invalidate challenge and kick back to login
                $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ?')
                    ->execute([$challengeId]);
                unset(
                    $_SESSION['first_login_user_id'],
                    $_SESSION['first_login_username'],
                    $_SESSION['first_login_full_name'],
                    $_SESSION['first_login_email'],
                    $_SESSION['first_login_role'],
                    $_SESSION['first_login_challenge_id'],
                    $_SESSION['first_login_stage'],
                    $_SESSION['first_login_otp_csrf']
                );
                $_SESSION['login_flash'] = [
                    'error_message'       => 'Too many code requests. Please log in again.',
                    'remaining_time'      => 0,
                    'attempt_count_display' => 0,
                    'locked_username'     => '',
                ];
                header('Location: app.php?page=login');
                exit;
            }

            if (strtotime($challenge['last_sent_at']) > time() - 60) {
                $error = 'Please wait before requesting another code.';
            } else {
                $newOtp    = (string)random_int(100000, 999999);
                $now       = date('Y-m-d H:i:s');
                $expiresAt = date('Y-m-d H:i:s', time() + 600);

                $pdo->prepare(
                    'UPDATE password_recovery
                     SET otp_hash = ?, expires_at = ?, attempts = 0,
                         resend_count = resend_count + 1,
                         otp_verified = 0, last_sent_at = ?
                     WHERE id = ?'
                )->execute([password_hash($newOtp, PASSWORD_DEFAULT), $expiresAt, $now, $challengeId]);

                $email   = $challenge['email'];
                $name    = $_SESSION['first_login_full_name'];
                $subject = 'FROSTLINE – First Login Verification Code';
                $body    = "Hello {$name},\n\n"
                    . "Your new FROSTLINE first-login verification code is: {$newOtp}\n\n"
                    . "This code expires in 10 minutes and can only be used once.\n\n"
                    . "-- FROSTLINE Secure Rail System --";

                $sent = sendRecoveryEmail($email, $subject, $body, $userId, $challenge['role']);

                // Log resend (never log the OTP value)
                $uname = $_SESSION['first_login_username'];
                $fname = $_SESSION['first_login_full_name'];
                try {
                    $identity = securityLogAccountIdentity($pdo, $userId);
                    $fname = securityLogFullName($identity) ?: $fname;
                    $pdo->prepare(
                    "INSERT INTO security_logs
                         (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status, failure_reason)
                         VALUES (?, ?, ?, ?, 'FIRST_LOGIN_OTP_RESENT', ?, ?, ?, ?, ?)"
                    )->execute([
                        $userId, $uname, $fname, $challenge['role'],
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null,
                            $identity['id_number'] ?? null,
                        $sent ? 'SUCCESS' : 'FAILED',
                        $sent ? null : 'Resend email delivery failed'
                    ]);
                } catch (Throwable $e) {
                    error_log('First login OTP resend log error: ' . $e->getMessage());
                }

                $error = $sent
                    ? 'A new verification code has been sent to your email.'
                    : 'The verification email could not be sent. Please try again.';
            }
        } else {
            // ── Verify OTP ────────────────────────────────────────────────
            $submittedOtp = trim($_POST['otp'] ?? '');

            if (!preg_match('/^\d{6}$/', $submittedOtp)) {
                $error = 'Enter the six-digit verification code.';
            } elseif (strtotime($challenge['expires_at']) <= time()) {
                $error = 'This verification code has expired. Please request a new one.';
            } else {
                $attempts = (int)$challenge['attempts'] + 1;

                if ($attempts > 5) {
                    $pdo->prepare('UPDATE password_recovery SET used_at = NOW(), attempts = ? WHERE id = ?')
                        ->execute([$attempts, $challengeId]);
                    $uname = $_SESSION['first_login_username'];
                    $fname = $_SESSION['first_login_full_name'];
                    try {
                        $identity = securityLogAccountIdentity($pdo, $userId);
                        $fname = securityLogFullName($identity) ?: $fname;
                        $pdo->prepare(
                        "INSERT INTO security_logs
                             (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status, failure_reason)
                             VALUES (?, ?, ?, ?, 'FIRST_LOGIN_OTP_FAILED', ?, ?, ?, 'FAILED', ?)"
                        )->execute([
                            $userId, $uname, $fname, $challenge['role'],
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                            $identity['id_number'] ?? null,
                            'Too many incorrect OTP attempts'
                        ]);
                    } catch (Throwable $e) {}

                    unset(
                        $_SESSION['first_login_user_id'],
                        $_SESSION['first_login_username'],
                        $_SESSION['first_login_full_name'],
                        $_SESSION['first_login_email'],
                        $_SESSION['first_login_role'],
                        $_SESSION['first_login_challenge_id'],
                        $_SESSION['first_login_stage'],
                        $_SESSION['first_login_otp_csrf']
                    );
                    $_SESSION['login_flash'] = [
                        'error_message'       => 'Too many incorrect attempts. Please log in again.',
                        'remaining_time'      => 0,
                        'attempt_count_display' => 0,
                        'locked_username'     => '',
                    ];
                    header('Location: app.php?page=login');
                    exit;
                }

                // Re-fetch otp_hash (fresh read to be safe)
                $hashStmt = $pdo->prepare('SELECT otp_hash FROM password_recovery WHERE id = ? LIMIT 1');
                $hashStmt->execute([$challengeId]);
                $currentHash = (string)$hashStmt->fetchColumn();

                if (!password_verify($submittedOtp, $currentHash)) {
                    $pdo->prepare('UPDATE password_recovery SET attempts = ? WHERE id = ?')
                        ->execute([$attempts, $challengeId]);

                    $uname = $_SESSION['first_login_username'];
                    $fname = $_SESSION['first_login_full_name'];
                    try {
                        $identity = securityLogAccountIdentity($pdo, $userId);
                        $fname = securityLogFullName($identity) ?: $fname;
                        $pdo->prepare(
                        "INSERT INTO security_logs
                             (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status, failure_reason)
                             VALUES (?, ?, ?, ?, 'FIRST_LOGIN_OTP_FAILED', ?, ?, ?, 'FAILED', ?)"
                        )->execute([
                            $userId, $uname, $fname, $challenge['role'],
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                            $identity['id_number'] ?? null,
                            'Incorrect OTP entered'
                        ]);
                    } catch (Throwable $e) {}

                    if ($attempts >= 5) {
                        $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ?')
                            ->execute([$challengeId]);
                        unset(
                            $_SESSION['first_login_user_id'],
                            $_SESSION['first_login_username'],
                            $_SESSION['first_login_full_name'],
                            $_SESSION['first_login_email'],
                            $_SESSION['first_login_role'],
                            $_SESSION['first_login_challenge_id'],
                            $_SESSION['first_login_stage'],
                            $_SESSION['first_login_otp_csrf']
                        );
                        $_SESSION['login_flash'] = [
                            'error_message'       => 'Too many incorrect attempts. Please log in again.',
                            'remaining_time'      => 0,
                            'attempt_count_display' => 0,
                            'locked_username'     => '',
                        ];
                        header('Location: app.php?page=login');
                        exit;
                    }
                    $error = 'Incorrect verification code. ' . (5 - $attempts) . ' attempt(s) remaining.';
                } else {
                    // ── OTP correct ───────────────────────────────────────
                    $verifyOtp = $pdo->prepare("UPDATE password_recovery r JOIN users u ON u.id=r.user_id
                        SET r.otp_verified=1,r.attempts=? WHERE r.id=? AND r.user_id=?
                        AND r.used_at IS NULL AND r.otp_verified=0 AND r.expires_at>CURRENT_TIMESTAMP
                        AND u.first_login_required=1 AND u.status<>'BLOCKED'
                        AND (u.role='USER' OR (u.role IN ('ADMIN','SUPER_ADMIN')
                            AND u.temporary_credential_expires_at>CURRENT_TIMESTAMP))");
                    $verifyOtp->execute([$attempts,$challengeId,$userId]);
                    if ($verifyOtp->rowCount()!==1) endFirstLoginSetupSession('Your verification session or initial credentials have expired. Please log in again.', 'otp_deadline_expired');

                    $uname = $_SESSION['first_login_username'];
                    $fname = $_SESSION['first_login_full_name'];
                    try {
                        $identity = securityLogAccountIdentity($pdo, $userId);
                        $fname = securityLogFullName($identity) ?: $fname;
                        $pdo->prepare(
                        "INSERT INTO security_logs
                             (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status)
                             VALUES (?, ?, ?, ?, 'FIRST_LOGIN_OTP_VERIFIED', ?, ?, ?, 'SUCCESS')"
                        )->execute([
                            $userId, $uname, $fname, $challenge['role'],
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null,
                            $identity['id_number'] ?? null,
                        ]);
                    } catch (Throwable $e) {}

                    // Advance stage — do not grant dashboard access yet
                    $_SESSION['first_login_stage'] = 'otp_verified';
                    header('Location: app.php?page=first_login_setup');
                    exit;
                }
            }
        }
    }
}

// Flash messages from resend
$flashError = $_SESSION['first_login_otp_flash'] ?? '';
unset($_SESSION['first_login_otp_flash']);
if ($flashError) $error = $flashError;

$maskedEmail = maskRecoveryEmail($_SESSION['first_login_email'] ?? '');
$otpCsrf     = $_SESSION['first_login_otp_csrf'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROSTLINE – Verify Your Email</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/forgot_password.css?v=<?= filemtime(__DIR__ . '/../css/forgot_password.css') ?>">
</head>
<body class="main-log">
<header class="main-header">
    <div class="header-content">
        <div class="logo-container">
            <span class="logo-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
            <div class="logo-copy">
                <h1 class="logo">FROSTLINE</h1>
                <span>SECURE RAIL SYSTEM</span>
            </div>
        </div>
        <nav class="nav-links">
            <a href="app.php?page=login">Login</a>
        </nav>
    </div>
</header>

<main class="forgot-form">
    <div class="form-box" style="max-width:480px">
        <h1>Verify Your Email</h1>
        <p style="text-align:center;margin-bottom:1rem;color:#52738b;font-size:.9rem">
            We sent a 6-digit verification code to<br>
            <strong><?= htmlspecialchars($maskedEmail) ?></strong>
        </p>
        <p style="text-align:center;margin-bottom:1.2rem;color:#52738b;font-size:.82rem">
            This is step 1 of your first-time account setup.
        </p>

        <?php if ($error): ?>
            <?php $isSuccess = str_contains($error, 'new verification code has been sent'); ?>
            <div class="<?= $isSuccess ? 'success-message' : 'error-message' ?>">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="post" action="first_login_otp.php" id="otpForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($otpCsrf) ?>">
            <input type="hidden" name="otp" id="otp_combined">
            <input type="hidden" name="action" id="otp_action" value="verify">

            <div class="form-content">
                <div class="input-group">
                    <label for="otp_input_1">Verification code</label>
                    <div class="otp-container">
                        <div class="otp-input-wrapper">
                            <input type="password" class="otp-input" id="otp_input_1"
                                   maxlength="1" inputmode="numeric" pattern="[0-9]"
                                   autocomplete="one-time-code" required>
                            <input type="password" class="otp-input"
                                   maxlength="1" inputmode="numeric" pattern="[0-9]"
                                   autocomplete="one-time-code" required>
                            <input type="password" class="otp-input"
                                   maxlength="1" inputmode="numeric" pattern="[0-9]"
                                   autocomplete="one-time-code" required>
                            <input type="password" class="otp-input"
                                   maxlength="1" inputmode="numeric" pattern="[0-9]"
                                   autocomplete="one-time-code" required>
                            <input type="password" class="otp-input"
                                   maxlength="1" inputmode="numeric" pattern="[0-9]"
                                   autocomplete="one-time-code" required>
                            <input type="password" class="otp-input"
                                   maxlength="1" inputmode="numeric" pattern="[0-9]"
                                   autocomplete="one-time-code" required>
                        </div>
                    </div>
                    <div class="otp-error" id="otpError"></div>
                </div>
            </div>

            <div class="form-actions">
                <button class="forgot-btn" type="submit"
                        onclick="document.getElementById('otp_action').value='verify'">
                    Verify Code
                </button>
                <button class="forgot-btn" type="submit" formnovalidate
                        onclick="document.getElementById('otp_action').value='resend'"
                        style="background:#52738b">
                    Send New Code
                </button>
            </div>
        </form>
    </div>
</main>

<footer class="frostline-footer">
    <span>FROSTLINE SECURE RAIL SYSTEM</span>
    <span>&copy; <?= date('Y') ?> Frostline</span>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const otpInputs  = document.querySelectorAll('.otp-input');
    const otpCombined = document.getElementById('otp_combined');
    const otpError   = document.getElementById('otpError');
    const otpForm    = document.getElementById('otpForm');

    function updateCombined() {
        otpCombined.value = Array.from(otpInputs).map(i => i.value).join('');
    }

    otpInputs.forEach(function(input, index) {
        input.addEventListener('input', function(e) {
            if (!/^\d$/.test(e.target.value)) { e.target.value = ''; return; }
            if (e.target.value && index < otpInputs.length - 1) otpInputs[index + 1].focus();
            updateCombined();
        });
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) otpInputs[index - 1].focus();
        });
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const data = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            if (!data) return;
            otpInputs.forEach(b => { b.value = ''; });
            data.split('').forEach(function(ch, i) { if (otpInputs[i]) otpInputs[i].value = ch; });
            const next = Array.from(otpInputs).findIndex((inp, i) => i >= data.length && !inp.value);
            if (next !== -1) otpInputs[next].focus();
            else otpInputs[Math.min(data.length - 1, 5)].focus();
            updateCombined();
        });
    });

    otpForm.addEventListener('submit', function(e) {
        const action = document.getElementById('otp_action').value;
        if (action === 'resend') return; // no validation needed
        updateCombined();
        const val = Array.from(otpInputs).map(i => i.value).join('');
        if (val.length !== 6) {
            e.preventDefault();
            otpError.textContent = 'Please enter the complete 6-digit verification code.';
        }
    });
});
</script>
</body>
</html>
