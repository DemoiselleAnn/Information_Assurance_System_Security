<?php
require_once __DIR__ . '/security_log_helpers.php';
$changePasswordError = '';
$changePasswordMessage = $_SESSION['member_password_message'] ?? '';
unset($_SESSION['member_password_message']);
$changePasswordCsrf = $_SESSION['member_password_csrf'] ??= bin2hex(random_bytes(32));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'authenticated_change_password') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($changePasswordCsrf, $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid request token');
    }

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $passwordStmt = $pdo->prepare('SELECT password, username, role, status FROM users WHERE id = ? LIMIT 1');
    $passwordStmt->execute([$sessionUserId]);
    $account = $passwordStmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || $account['role'] !== 'USER' || $account['status'] !== 'ACTIVE') {
        http_response_code(403);
        exit('Forbidden');
    }
    if (!is_string($currentPassword) || !password_verify($currentPassword, $account['password'])) {
        $changePasswordError = 'The current password is incorrect.';
    } elseif (!is_string($newPassword) || strlen($newPassword) < 8
        || !preg_match('/[A-Z]/', $newPassword)
        || !preg_match('/[a-z]/', $newPassword)
        || !preg_match('/[0-9]/', $newPassword)
        || !preg_match('/[^A-Za-z0-9]/', $newPassword)) {
        $changePasswordError = 'Password must be at least 8 characters and contain uppercase, lowercase, numbers, and a special character.';
    } elseif ($newPassword !== $confirmPassword) {
        $changePasswordError = 'Passwords do not match.';
    } elseif (password_verify($newPassword, $account['password'])) {
        $changePasswordError = 'The new password must be different from the current password.';
    } else {
        try {
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ? AND role = \'USER\' AND status = \'ACTIVE\'');
            $update->execute([$newHash, $sessionUserId]);
            if ($update->rowCount() !== 1) {
                throw new RuntimeException('Password was not updated.');
            }

            $identity = securityLogAccountIdentity($pdo, (int)$sessionUserId);

            $log = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, session_key, actor_id_number, status, failure_reason) VALUES (?, ?, ?, ?, 'PASSWORD_RESET', ?, ?, ?, ?, 'SUCCESS', NULL)");
            $log->execute([
                $sessionUserId,
                $account['username'],
                securityLogFullName($identity) ?: ($_SESSION['full_name'] ?? $account['username']),
                $account['role'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $_SESSION['security_session_key'] ?? null,
                $identity['id_number'] ?? null,
            ]);
            $pdo->commit();

            $_SESSION['member_password_message'] = 'Password changed successfully.';
            header('Location: app.php?page=customer_dashboard&view=change_password');
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $changePasswordError = 'Password could not be changed. Please try again.';
        }
    }
}
?>
<section class="profile-content password-change-content" aria-labelledby="change-password-title">
    <header class="profile-content-header">
        <div>
            <p class="eyebrow">ACCOUNT SECURITY</p>
            <h1 class="profile-title" id="change-password-title">Change Password</h1>
            <p class="profile-subtitle">Update the password for your authenticated Frostline account.</p>
        </div>
    </header>

    <section class="profile-card password-change-card" aria-label="Change password form">
        <header class="profile-card-header">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <div>
                <h3>Secure your account</h3>
                <p>Enter your current password before choosing a new one.</p>
            </div>
        </header>
        <?php if ($changePasswordError): ?>
            <div class="password-change-alert error"><?= htmlspecialchars($changePasswordError, ENT_QUOTES, 'UTF-8') ?></div>
        <?php elseif ($changePasswordMessage): ?>
            <div class="password-change-alert success"><?= htmlspecialchars($changePasswordMessage, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form class="password-change-form" method="post" action="app.php?page=customer_dashboard&amp;view=change_password">
            <input type="hidden" name="action" value="authenticated_change_password">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($changePasswordCsrf, ENT_QUOTES, 'UTF-8') ?>">
            <label>Current Password<input type="password" name="current_password" autocomplete="current-password" required></label>
            <label>New Password<input type="password" name="new_password" autocomplete="new-password" required></label>
            <label>Confirm New Password<input type="password" name="confirm_password" autocomplete="new-password" required></label>
            <p class="password-change-hint">Use at least 8 characters with uppercase and lowercase letters, a number, and a special character.</p>
            <div class="password-change-actions">
                <a class="profile-action password-change-cancel" href="app.php?page=customer_dashboard&amp;view=profile">Cancel</a>
                <button class="profile-action" type="submit"><i class="fas fa-key" aria-hidden="true"></i> Change Password</button>
            </div>
        </form>
    </section>
</section>
