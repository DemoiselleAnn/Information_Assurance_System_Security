<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'recovery_mail.php';
require_once 'profile_recovery.php';

function otpError(string $message): void {
    $_SESSION['otp_error'] = $message;
    header('Location: ' . recoveryLocation('otp'));
    exit;
}

$challengeId = (int)($_SESSION['recovery_challenge_id'] ?? 0);
if (!$challengeId) {
    header('Location: app.php?page=login');
    exit;
}

$stmt = $pdo->prepare("SELECT r.*, u.id AS account_id, u.email, u.username, u.role FROM password_recovery r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.used_at IS NULL AND u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN') LIMIT 1");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$challenge) otpError('This verification code is no longer valid. Please start again.');
requireProfileRecoveryAccount($pdo, (int)$challenge['account_id']);
if (!isProfileRecovery()) {
    $eligibilityError = recoveryEligibilityMessage(recoveryAccount($pdo, (int)$challenge['account_id']));
    if ($eligibilityError !== null) {
        $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ?')->execute([$challengeId]);
        $_SESSION['forgot_error'] = $eligibilityError;
        header('Location: ' . recoveryLocation('start'));
        exit;
    }
}
if (!hash_equals((string)($_SESSION['otp_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    otpError('This recovery session is no longer valid. Please start again.');
}

if (isset($_POST['action']) && $_POST['action'] === 'resend') {
    if ((int)$challenge['resend_count'] >= 3) {
        $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ?')->execute([$challengeId]);
        otpError('Too many new code requests. Please start recovery again later.');
    }
    if (strtotime($challenge['last_sent_at']) > time() - 60) otpError('Please wait before requesting another code.');
    $otp = (string)random_int(100000, 999999);
    $now = date('Y-m-d H:i:s');
    $expiresAt = date('Y-m-d H:i:s', time() + 600);
    $newHash = password_hash($otp, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE password_recovery SET otp_hash = ?, expires_at = ?, attempts = 0, resend_count = resend_count + 1, otp_verified = 0, last_sent_at = ? WHERE id = ?')->execute([$newHash, $expiresAt, $now, $challengeId]);
    if (!sendRecoveryEmail($challenge['email'], 'FROSTLINE account recovery code', "Your FROSTLINE account recovery code is: {$otp}\n\nThis code expires in 10 minutes and can be used once.", (int)$challenge['account_id'], (string)$challenge['role'])) {
        $pdo->prepare('UPDATE password_recovery SET used_at = ? WHERE id = ?')->execute([$now, $challengeId]);
        otpError('The verification email could not be sent. Please try again later.');
    }
    $_SESSION['otp_error'] = 'A new verification code was sent.';
    header('Location: ' . recoveryLocation('otp'));
    exit;
}

$otp = trim((string)($_POST['otp'] ?? ''));
if (!preg_match('/^\d{6}$/', $otp)) otpError('Enter the six-digit verification code.');
if (strtotime($challenge['expires_at']) <= time()) otpError('This verification code has expired. Please request a new one.');

$attempts = (int)$challenge['attempts'] + 1;
if ($attempts > 5) {
    $pdo->prepare('UPDATE password_recovery SET used_at = ?, attempts = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $attempts, $challengeId]);
    otpError('Too many incorrect attempts. Please start recovery again.');
}
if (!password_verify($otp, $challenge['otp_hash'])) {
    if ($attempts >= 5) {
        $pdo->prepare('UPDATE password_recovery SET used_at = ?, attempts = ? WHERE id = ?')->execute([date('Y-m-d H:i:s'), $attempts, $challengeId]);
        otpError('Too many incorrect attempts. Please start recovery again.');
    }
    $pdo->prepare('UPDATE password_recovery SET attempts = ? WHERE id = ?')->execute([$attempts, $challengeId]);
    otpError('Incorrect verification code.');
}

$pdo->prepare('UPDATE password_recovery SET otp_verified = 1, attempts = ? WHERE id = ?')->execute([$attempts, $challengeId]);
$_SESSION['reset_user_id'] = (int)$challenge['account_id'];
$_SESSION['reset_challenge_id'] = $challengeId;
$_SESSION['recovery_stage'] = 'otp_verified';
$_SESSION['otp_verification_success'] = 'Verification successful. Please answer the security questions.';
unset($_SESSION['otp_csrf']);
header('Location: ' . recoveryLocation('questions'));
exit;
