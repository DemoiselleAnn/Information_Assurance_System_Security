<?php
// Setup is a verified pre-authentication session, separate from dashboard ownership.
function firstLoginSetupDiagnostic(string $reason): void {
    error_log('CakeDeco first-login setup failure: ' . json_encode([
        'reason' => $reason, 'account_id' => (int)($_SESSION['first_login_user_id'] ?? 0),
    ]));
}

function endFirstLoginSetupSession(string $message, string $reason): never {
    firstLoginSetupDiagnostic($reason);
    foreach (['user_id', 'username', 'full_name', 'email', 'role', 'challenge_id', 'stage', 'otp_csrf', 'setup_csrf', 'setup_errors', 'setup_old'] as $key) {
        unset($_SESSION['first_login_' . $key]);
    }
    $_SESSION['login_flash'] = ['error_message' => $message, 'remaining_time' => 0, 'attempt_count_display' => 0, 'locked_username' => ''];
    header('Location: app.php?page=login');
    exit;
}

// Read the original server deadline on every OTP/setup request. Never renew it.
function requireUnexpiredFirstLoginAdministrator(PDO $pdo, int $userId): void {
    $s=$pdo->prepare("SELECT role,first_login_required,(temporary_credential_expires_at IS NOT NULL AND temporary_credential_expires_at>CURRENT_TIMESTAMP) AS unexpired FROM users WHERE id=?");
    $s->execute([$userId]);$account=$s->fetch(PDO::FETCH_ASSOC);
    if (!$account || (in_array($account['role'],['ADMIN','SUPER_ADMIN'],true) && (int)$account['first_login_required']===1 && !(int)$account['unexpired'])) {
        endFirstLoginSetupSession('Your initial credentials have expired or the setup session is no longer available. Please contact an administrator.', 'initial_deadline_expired');
    }
}
