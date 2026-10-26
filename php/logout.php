<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin_session.php';
require_once __DIR__ . '/session_presence.php';
require_once __DIR__ . '/security_log_helpers.php';

// Log logout event if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'];
    $full_name = $_SESSION['full_name'] ?? 'Unknown';
    $role = $_SESSION['role'] ?? 'USER';
    $sessionKey = $_SESSION['security_session_key'] ?? null;
    if ($role === 'SUPER_ADMIN') releaseSuperAdminSession($pdo);
    endUserPresence($pdo, (int)$user_id, $sessionKey);

    // Log security event
    try {
        $identity = securityLogAccountIdentity($pdo, (int)$user_id);
        $full_name = securityLogFullName($identity) ?: $full_name;
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, session_key, actor_id_number, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $full_name, $role, 'LOGOUT', $ip_address, $user_agent, $sessionKey, $identity['id_number'] ?? null, 'SUCCESS']);
    } catch (PDOException $e) {
        // Log error but don't break logout flow
        error_log("Security log error: " . $e->getMessage());
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Start new session to store logout message
session_id('');
ensureCakeDecoSession();
$_SESSION['logout_success'] = true;

// Redirect to home via router after logout
header("Location: app.php?page=home");
exit;
?>
