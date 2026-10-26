<?php
require_once __DIR__ . '/security_log_helpers.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'profile_recovery.php';
requireProfileRecoveryAccount($pdo, (int)($_SESSION['reset_user_id'] ?? 0));

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isProfileRecovery() && (!isset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id']) || empty($_SESSION['security_verified']) || ($_SESSION['recovery_stage'] ?? '') !== 'security_verified')) {
        $_SESSION['forgot_error'] = 'Please complete email and security verification first.';
        header('Location: ' . recoveryLocation('start'));
        exit();
    }
    if (!isset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id'])) {
        header("Location: app.php?page=login");
        exit();
    }
    if (!hash_equals((string)($_SESSION['reset_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        header("Location: app.php?page=login");
        exit();
    }

    $challengeStmt = $pdo->prepare('SELECT id FROM password_recovery WHERE id = ? AND user_id = ? AND otp_verified = 1 AND used_at IS NULL LIMIT 1');
    $challengeStmt->execute([(int)$_SESSION['reset_challenge_id'], (int)$_SESSION['reset_user_id']]);
    if (!$challengeStmt->fetchColumn()) {
        header('Location: ' . (isProfileRecovery() ? 'app.php?page=my_profile' : recoveryLocation('start')));
        exit();
    }

    if (!isProfileRecovery()) {
        $account = recoveryAccount($pdo, (int)$_SESSION['reset_user_id']);
        $eligibilityError = recoveryEligibilityMessage($account);
        if ($eligibilityError !== null) {
            $_SESSION['forgot_error'] = $eligibilityError;
            header('Location: ' . recoveryLocation('start'));
            exit();
        }
    }

    $new_password = $_POST['new_password'];
    $reenter_password = $_POST['reenter_password'];
    $user_id = $_SESSION['reset_user_id'];

    $errors = [];

    // Validate input
    if (empty($new_password)) {
        $errors[] = "New password is required.";
    }

    if (empty($reenter_password)) {
        $errors[] = "Re-enter password is required.";
    }

    if ($new_password !== $reenter_password) {
        $errors[] = "Passwords do not match.";
    }

    // Validate password strength
    if (strlen($new_password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (!preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || 
        !preg_match('/[0-9]/', $new_password) || !preg_match('/[^A-Za-z0-9]/', $new_password)) {
        $errors[] = "Password must contain uppercase, lowercase, numbers, and special characters.";
    }

    if (!empty($errors)) {
        $_SESSION['change_error'] = implode(" ", $errors);
        header("Location: change_password.php");
        exit();
    }

    try {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        $pdo->beginTransaction();
        // Update password in database
        $stmt = isProfileRecovery()
            ? $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            : $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND status IN ('ACTIVE', 'INACTIVE') AND first_login_required = 0");
        $stmt->execute([$hashed_password, $user_id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Account is no longer eligible for password recovery.');
        }

        $consume = $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ? AND user_id = ? AND otp_verified = 1 AND used_at IS NULL');
        $consume->execute([(int)$_SESSION['reset_challenge_id'], $user_id]);
        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('Recovery session is no longer valid.');
        }
        if (!isProfileRecovery()) {
            $identity = securityLogAccountIdentity($pdo, (int)$account['id']);
            $log = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status, failure_reason) VALUES (?, ?, ?, ?, 'PASSWORD_RESET', ?, ?, ?, 'SUCCESS', NULL)");
            $log->execute([(int)$account['id'], $account['username'], securityLogFullName($identity) ?: $account['username'], $account['role'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $identity['id_number'] ?? null]);
        }
        $pdo->commit();

        // Clear reset session data
        unset($_SESSION['reset_user_id']);
        unset($_SESSION['reset_username']);
        unset($_SESSION['reset_challenge_id']);
        unset($_SESSION['reset_csrf']);
        if (isProfileRecovery()) {
            unset($_SESSION['profile_recovery_user_id'], $_SESSION['forgot_question_ids'], $_SESSION['forgot_email'], $_SESSION['forgot_role']);
            $_SESSION['profile_message'] = 'Password changed successfully.';
            header('Location: app.php?page=my_profile');
            exit;
        }

        $_SESSION['password_change_success'] = "Password Reset Successful. Please log in with your new password.";
        header("Location: app.php?page=login");
        exit();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION['change_error'] = "Password reset could not be completed.";
        header("Location: change_password.php");
        exit();
    }
} else {
    header("Location: change_password.php");
    exit();
}
?>
