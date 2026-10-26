<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'recovery_mail.php';
require_once 'profile_recovery.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request.']);
    exit;
}

$identifier = $_POST['identifier'] ?? '';
if (!is_string($identifier)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid ID No. or Username.']);
    exit;
}
$identifier = trim($identifier);
if ($identifier === '') {
    echo json_encode(['success' => false, 'error' => 'Please enter your ID No. or Username.']);
    exit;
}
// Reject an ID joined to another identifier; never split it into account lookups.
if (preg_match('/(?:^|[\s\/,;])\d{4}-\d{4}[\s\/,;]+\S|\S[\s\/,;]+\d{4}-\d{4}(?:$|[\s\/,;])/', $identifier)) {
    echo json_encode(['success' => false, 'error' => 'Enter only one ID No. or Username.']);
    exit;
}
// Match the registration ID format and the existing user/admin username rules.
$isId = preg_match('/^\d{4}-\d{4}$/D', $identifier) === 1;
$isUserUsername = strlen($identifier) <= 40 && preg_match('/^[a-z]{5,}\.[a-z]{2,}$/D', $identifier) === 1;
$isAdminUsername = preg_match('/^[A-Za-z0-9]{5,40}$/D', $identifier) === 1;
if (!$isId && !$isUserUsername && !$isAdminUsername) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid ID No. or Username.']);
    exit;
}

$sql = $isId
    ? "SELECT id FROM users WHERE id_number = ? AND role IN ('USER', 'ADMIN', 'SUPER_ADMIN') LIMIT 1"
    : "SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND role IN ('USER', 'ADMIN', 'SUPER_ADMIN') LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$identifier]);
$userId = (int)$stmt->fetchColumn();
$user = recoveryAccount($pdo, $userId);
$eligibilityError = recoveryEligibilityMessage($user);
if ($eligibilityError !== null) {
    echo json_encode(['success' => false, 'error' => $eligibilityError]);
    exit;
}

$questionCount = $pdo->prepare('SELECT COUNT(*) FROM security_answers WHERE user_id = ?');
$questionCount->execute([$userId]);
if ((int)$questionCount->fetchColumn() < 3) {
    echo json_encode(['success' => false, 'error' => 'We could not verify an account using the information provided.']);
    exit;
}

session_regenerate_id(true);
unset($_SESSION['profile_recovery_user_id'], $_SESSION['forgot_question_ids'], $_SESSION['forgot_questions']);
unset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id'], $_SESSION['reset_csrf'], $_SESSION['recovery_challenge_id'], $_SESSION['otp_csrf'], $_SESSION['forgot_question_attempts'], $_SESSION['security_question_attempts'], $_SESSION['security_verified']);

$otp = (string)random_int(100000, 999999);
$now = date('Y-m-d H:i:s');
$expiresAt = date('Y-m-d H:i:s', time() + 600);
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE password_recovery SET used_at = ? WHERE user_id = ? AND used_at IS NULL')->execute([$now, $userId]);
    $insert = $pdo->prepare('INSERT INTO password_recovery (user_id, otp_hash, expires_at, last_sent_at) VALUES (?, ?, ?, ?)');
    $insert->execute([$userId, password_hash($otp, PASSWORD_DEFAULT), $expiresAt, $now]);
    $challengeId = (int)$pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Recovery is temporarily unavailable. Please try again.']);
    exit;
}

$message = "Your FROSTLINE account recovery code is: {$otp}\n\nThis code expires in 10 minutes and can be used once.";
if (!sendRecoveryEmail($user['email'], 'FROSTLINE account recovery code', $message, $userId, (string)$user['role'])) {
    $pdo->prepare('UPDATE password_recovery SET used_at = ? WHERE id = ?')->execute([$now, $challengeId]);
    echo json_encode(['success' => false, 'error' => 'The verification email could not be sent. Please contact the administrator.']);
    exit;
}

$_SESSION['forgot_user_id'] = $userId;
$_SESSION['forgot_username'] = $user['username'];
$_SESSION['forgot_role'] = $user['role'];
$_SESSION['recovery_challenge_id'] = $challengeId;
$_SESSION['recovery_stage'] = 'otp_pending';
$_SESSION['otp_csrf'] = bin2hex(random_bytes(32));
echo json_encode(['success' => true, 'redirect' => 'app.php?page=otp_verification']);
