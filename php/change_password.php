<?php
require_once __DIR__ . '/security_log_helpers.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once 'profile_recovery.php';
requireProfileRecoveryAccount($pdo, (int)($_SESSION['reset_user_id'] ?? 0));

if (!isProfileRecovery() && (!isset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id']) || empty($_SESSION['security_verified']) || ($_SESSION['recovery_stage'] ?? '') !== 'security_verified')) {
    $_SESSION['forgot_error'] = 'Please complete email and security verification first.';
    header('Location: ' . recoveryLocation('start'));
    exit;
}
if (!isset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id'])) {
    header('Location: app.php?page=login');
    exit;
}

$challengeStmt = $pdo->prepare('SELECT id, user_id FROM password_recovery WHERE id = ? AND user_id = ? AND otp_verified = 1 AND used_at IS NULL LIMIT 1');
$challengeStmt->execute([(int)$_SESSION['reset_challenge_id'], (int)$_SESSION['reset_user_id']]);
if (!$challengeStmt->fetch(PDO::FETCH_ASSOC)) {
    unset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id']);
    header('Location: ' . (isProfileRecovery() ? 'app.php?page=my_profile' : recoveryLocation('start')));
    exit;
}
$account = recoveryAccount($pdo, (int)$_SESSION['reset_user_id']);
if (!isProfileRecovery() && ($eligibilityError = recoveryEligibilityMessage($account)) !== null) {
    $_SESSION['forgot_error'] = $eligibilityError;
    header('Location: ' . recoveryLocation('start'));
    exit;
}
$resetCsrf = $_SESSION['reset_csrf'] ??= bin2hex(random_bytes(32));

$error_message = '';
$success_message = $_SESSION['otp_verification_success'] ?? '';
unset($_SESSION['otp_verification_success']);

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (!hash_equals((string)($_SESSION['reset_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        header("Location: app.php?page=login");
        exit;
    }
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (!empty($new_password) && !empty($confirm_password)) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 8 && preg_match('/[A-Z]/', $new_password) && preg_match('/[a-z]/', $new_password) && preg_match('/[0-9]/', $new_password) && preg_match('/[^A-Za-z0-9]/', $new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                if (!isProfileRecovery()) {
                    $currentAccount = recoveryAccount($pdo, (int)$_SESSION['reset_user_id']);
                    $eligibilityError = recoveryEligibilityMessage($currentAccount);
                    if ($eligibilityError !== null) {
                        unset($_SESSION['reset_user_id'], $_SESSION['reset_challenge_id'], $_SESSION['reset_csrf'], $_SESSION['security_verified'], $_SESSION['recovery_stage']);
                        $_SESSION['forgot_error'] = $eligibilityError;
                        header('Location: ' . recoveryLocation('start'));
                        exit;
                    }
                    $account = $currentAccount;
                }
                
                $pdo->beginTransaction();
                $stmt = isProfileRecovery()
                    ? $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
                    : $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND status IN ('ACTIVE', 'INACTIVE') AND first_login_required = 0");
                $stmt->execute([$hashed_password, $_SESSION['reset_user_id']]);
                if ($stmt->rowCount() !== 1) {
                    $pdo->rollBack();
                    $_SESSION['forgot_error'] = 'This account is no longer eligible for password recovery.';
                    header('Location: ' . recoveryLocation('start'));
                    exit;
                }
                $consume = $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ? AND user_id = ? AND otp_verified = 1 AND used_at IS NULL');
                $consume->execute([(int)$_SESSION['reset_challenge_id'], (int)$_SESSION['reset_user_id']]);
                if ($consume->rowCount() !== 1) {
                    $pdo->rollBack();
                    $error_message = 'This recovery session is no longer valid.';
                } else {
                    if (!isProfileRecovery()) {
                        $identity = securityLogAccountIdentity($pdo, (int)$account['id']);
                        $log = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, status, failure_reason) VALUES (?, ?, ?, ?, 'PASSWORD_RESET', ?, ?, ?, 'SUCCESS', NULL)");
                        $fullName = securityLogFullName($identity) ?: $account['username'];
                        $log->execute([(int)$account['id'], $account['username'], $fullName, $account['role'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $identity['id_number'] ?? null]);
                    }
                    $pdo->commit();
                    unset($_SESSION['reset_user_id'], $_SESSION['reset_username'], $_SESSION['reset_challenge_id'], $_SESSION['reset_csrf'], $_SESSION['security_verified'], $_SESSION['recovery_stage'], $_SESSION['recovery_challenge_id']);
                    if (isProfileRecovery()) {
                        unset($_SESSION['profile_recovery_user_id'], $_SESSION['forgot_question_ids'], $_SESSION['forgot_email'], $_SESSION['forgot_role'], $_SESSION['otp_verification_success']);
                        $_SESSION['profile_message'] = 'Password changed successfully.';
                        header('Location: app.php?page=my_profile');
                        exit;
                    }
                    $_SESSION['password_change_success'] = "Password Reset Successful. Please log in with your new password.";
                    header("Location: app.php?page=login");
                    exit;
                }
            } else {
                $error_message = "Password must be at least 8 characters and contain uppercase, lowercase, numbers, and a special character.";
            }
        } else {
            $error_message = "Mismatch Password";
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROSTLINE - Change Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/forgot_password.css">

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
                <a href="app.php?page=login" id="loginLink">Login</a>
            </nav>
        </div>
    </header>
    
    <main class="forgot-form">
        <div class="form-box" style="max-width:480px">
            <h1>Change Password</h1>
            
            <?php if (!empty($success_message)): ?>
                <div class="success-message"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <form method="POST" id="changePasswordForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($resetCsrf) ?>">
                <div class="form-content">
                    <div class="input-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Enter new password" required>
                    </div>
                    
                    <div class="input-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="forgot-btn">Change Password</button>
                </div>
            </form>
        </div>
    </main>

<?php include 'footer.php'; ?>
</body>
</html>
