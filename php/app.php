<?php
// Front controller router
define('APP_ENTRY', true);
require_once __DIR__ . '/session_bootstrap.php';

$routes = [
    'home' => 'index.php',
    'login' => 'login.php',
    'register' => 'register.php',
    'register_preview' => 'register_preview.php',
    'homepage' => 'index.php',
    'customer_dashboard' => 'customer_dashboard.php',
    'profile' => 'profile.php',
    'my_profile' => 'profile_entry.php',
    'profile_password' => 'superadmin_shell.php',
    'forgot_password' => 'forgot_password.php',
    'otp_verification' => 'otp_verification.php',
    'security_questions' => 'security_questions.php',
    'change_password' => 'change_password.php',
    'edit_profile' => 'edit_profile.php',
    'administrators' => 'superadmin_shell.php',
    'account_management' => 'superadmin_shell.php',
    'blocked_accounts' => 'superadmin_shell.php',
    'security_logs' => 'superadmin_shell.php',
    'notifications' => 'superadmin_shell.php',
    'registration_approval' => 'superadmin_shell.php',
    'deletion_requests' => 'superadmin_shell.php',
    'roles_permissions' => 'superadmin_shell.php',
    // First-login workflow
    'first_login_otp'   => 'first_login_otp.php',
    'first_login_setup' => 'first_login_setup.php',
    'superadmin_waiting' => 'superadmin_waiting.php',
    'superadmin_session_ended' => 'superadmin_session_ended.php',
];

$page = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : 'home';

// Legacy dashboard aliases now land on the current Frostline interface.
if (in_array($page, ['superadmin', 'admin'], true)) {
    header('Location: app.php?page=account_management');
    exit;
}

if (!array_key_exists($page, $routes)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Send this action through its existing handler before HTML navigation guards
// can turn an expired session or rejected request into a login-page response.
require_once __DIR__ . '/demotion_request.php';
if ($page === 'account_management' && isSuperAdminDemotionRequest()) {
    require __DIR__ . '/account_management.php';
    exit;
}

// ── Session cleanup on navigation ────────────────────────────────────────────
// Leaving recovery OR first-login flows clears their respective session state.
// Cleanup and authentication share the same session until the request ends.
$leavingRecovery   = !in_array($page, ['forgot_password', 'otp_verification', 'security_questions', 'change_password', 'profile_password'], true);
$leavingFirstLogin = !in_array($page, ['first_login_otp', 'first_login_setup', 'login'], true);

if ($leavingRecovery || $leavingFirstLogin) {

    if ($leavingRecovery) {
        unset(
            $_SESSION['profile_recovery_user_id'],
            $_SESSION['forgot_question_ids'],
            $_SESSION['forgot_user_id'], $_SESSION['forgot_username'], $_SESSION['forgot_email'],
            $_SESSION['forgot_role'], $_SESSION['forgot_questions'], $_SESSION['forgot_csrf'],
            $_SESSION['forgot_error'], $_SESSION['forgot_success'],
            $_SESSION['recovery_challenge_id'], $_SESSION['otp_csrf'], $_SESSION['otp_error'],
            $_SESSION['recovery_stage'], $_SESSION['security_questions_csrf'], $_SESSION['security_question_attempts'],
            $_SESSION['reset_user_id'], $_SESSION['reset_username'],
            $_SESSION['reset_challenge_id'], $_SESSION['reset_csrf'], $_SESSION['security_verified']
        );
    }

    if ($leavingFirstLogin) {
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
    }

}

// Refresh the authenticated role from the database so role changes cannot leave
// an old Admin session authoritative after the account becomes a User.
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/superadmin_session.php';
    require_once __DIR__ . '/session_presence.php';
    cleanupStaleUserSessions($pdo);
    $sessionUserStmt = $pdo->prepare('SELECT username, role, status FROM users WHERE id = ? LIMIT 1');
    $sessionUserStmt->execute([(int)$_SESSION['user_id']]);
    $sessionUser = $sessionUserStmt->fetch(PDO::FETCH_ASSOC);
    // Reject a revoked Super Admin session before refreshing its role or presence.
    if (isset($_SESSION['superadmin_session_token']) && (!$sessionUser || $sessionUser['role'] !== 'SUPER_ADMIN')) {
        superAdminEndSession($pdo, 'Your account role has changed. Please log in again.');
        header('Location: app.php?page=login');
        exit;
    }
    if (!$sessionUser || $sessionUser['status'] === 'BLOCKED') {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['full_name']);
    } else {
        $_SESSION['username'] = $sessionUser['username'];
        $_SESSION['role'] = $sessionUser['role'];
    }
}

if (!empty($_SESSION['user_id']) && strtoupper((string)($_SESSION['role'] ?? '')) === 'SUPER_ADMIN') {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/superadmin_session.php';
    enforceSuperAdminSession($pdo, $_SERVER['REQUEST_METHOD'] === 'POST');
}
if (!empty($_SESSION['user_id']) && !empty($_SESSION['security_session_key'])) {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/session_presence.php';
    if (!touchUserPresence($pdo, (int)$_SESSION['user_id'], (string)$_SESSION['security_session_key'])) {
        startUserPresence($pdo, (int)$_SESSION['user_id'], (string)$_SESSION['security_session_key']);
    }
}

// Serve the requested page by including the original file.
if ($page === 'security_logs' && strtoupper($_SESSION['role'] ?? '') === 'USER') {
    $_GET['dashboard_view'] = 'security_logs';
    require __DIR__ . '/customer_dashboard.php';
    exit;
}
require __DIR__ . '/' . $routes[$page];
?>


