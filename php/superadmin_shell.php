<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id'])) {
    header('Location: app.php?page=login');
    exit();
}

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/sidebar_pending_counts.php';
require_once __DIR__ . '/superadmin_session.php';
requireCompletedAccountSetup($pdo);

$currentRole = strtoupper($_SESSION['role'] ?? 'USER');
$currentPage = strtolower(trim($_GET['page'] ?? 'account_management'));
$dashboardRole = $currentRole === 'ADMIN' ? 'Administrator' : ($currentRole === 'USER' ? 'Member' : 'Super Administrator');
$currentUserId = (int)$_SESSION['user_id'];
$superAdminSessionState = $currentRole === 'SUPER_ADMIN'
    ? enforceSuperAdminSession($pdo, $_SERVER['REQUEST_METHOD'] === 'POST')
    : ['status' => 'not_applicable'];
$pagePermissions = [
    'account_management' => 'MANAGE_USERS',
    'blocked_accounts' => 'BLOCK_ACCOUNTS',
    'registration_approval' => 'APPROVE_REGISTRATIONS',
    'security_logs' => 'VIEW_SECURITY_LOGS',
    'deletion_requests' => 'SUBMIT_DELETION_REQUESTS',
    'administrators' => 'MANAGE_ADMINISTRATORS',
    'roles_permissions' => 'MANAGE_ROLES',
];
$superAdminPages = ['administrators', 'roles_permissions'];
$isAuthorizedPage = false;
if ($currentPage === 'notifications') {
    $isAuthorizedPage = in_array($currentRole, ['ADMIN', 'SUPER_ADMIN'], true);
} elseif ($currentPage === 'my_profile') {
    $isAuthorizedPage = in_array($currentRole, ['USER', 'ADMIN', 'SUPER_ADMIN'], true);
} elseif ($currentPage === 'profile_password') {
    $isAuthorizedPage = in_array($currentRole, ['ADMIN', 'SUPER_ADMIN'], true);
}
if (isset($pagePermissions[$currentPage])) {
    $isAuthorizedPage = !in_array($currentPage, $superAdminPages, true) || $currentRole === 'SUPER_ADMIN';
    if ($currentPage === 'deletion_requests') {
        $isAuthorizedPage = $isAuthorizedPage && (
            hasPermission($pdo, $currentUserId, 'SUBMIT_DELETION_REQUESTS')
            || hasPermission($pdo, $currentUserId, 'APPROVE_DELETIONS')
        );
    } elseif ($currentPage === 'security_logs' && $currentRole === 'USER') {
        $isAuthorizedPage = true;
    } else {
        $isAuthorizedPage = $isAuthorizedPage && hasPermission($pdo, $currentUserId, $pagePermissions[$currentPage]);
    }
}
if (!$isAuthorizedPage) {
    http_response_code(403);
    require __DIR__ . '/access_restricted.php';
    exit;
}

$pageMap = [
    'my_profile' => 'my_profile.php',
    'profile_password' => 'profile_password.php',
    'account_management' => 'account_management.php',
    'blocked_accounts' => 'blocked_accounts.php',
    'administrators' => 'admin_management.php',
    'security_logs' => 'security_logs.php',
    'notifications' => 'notifications.php',
    'registration_approval' => 'registration_approval.php',
    'roles_permissions' => 'roles_permissions.php',
    'deletion_requests' => 'deletion_requests.php',
];

if (!isset($pageMap[$currentPage])) {
    header('Location: app.php?page=account_management');
    exit();
}

$includeFile = __DIR__ . '/' . $pageMap[$currentPage];
$canView = static function (string $permission) use ($pdo, $currentUserId): bool {
    return hasPermission($pdo, $currentUserId, $permission);
};
ob_start();
if ($isAuthorizedPage) {
    require $includeFile;
} else {
    require __DIR__ . '/access_restricted.php';
}
$captured = ob_get_clean();

$bodyContent = preg_replace('/^.*?<body[^>]*>|<\/body>.*$/is', '', $captured);
$bodyContent = trim($bodyContent);

// Count after the current workflow has finished handling its request.
$sidebarPendingCounts = sidebarPendingCounts($pdo, $currentUserId, $currentRole);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROSTLINE Secure Rail System - <?= $dashboardRole ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <?php if ($currentPage === 'deletion_requests'): ?>
    <link rel="stylesheet" href="../css/deletion_requests.css?v=<?= filemtime(__DIR__ . '/../css/deletion_requests.css') ?>">
    <?php endif; ?>
    <?php if ($currentRole === 'SUPER_ADMIN'): ?>
    <link rel="stylesheet" href="../css/superadmin_session.css?v=<?= filemtime(__DIR__ . '/../css/superadmin_session.css') ?>">
    <script src="../js/superadmin_session.js?v=<?= filemtime(__DIR__ . '/../js/superadmin_session.js') ?>" defer></script>
    <?php endif; ?>
    <?php if ($currentPage === 'roles_permissions'): ?>
    <link rel="stylesheet" href="../css/roles_permissions.css?v=<?= filemtime(__DIR__ . '/../css/roles_permissions.css') ?>">
    <?php endif; ?>
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
    <script src="../js/superadmin_demotion.js?v=<?= filemtime(__DIR__ . '/../js/superadmin_demotion.js') ?>" defer></script>
    <script src="../js/security_logs.js?v=<?= filemtime(__DIR__ . '/../js/security_logs.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
    <script src="../js/sidebar_scroll.js?v=<?= filemtime(__DIR__ . '/../js/sidebar_scroll.js') ?>" defer></script>
    <script src="../js/admin_management.js?v=<?= filemtime(__DIR__ . '/../js/admin_management.js') ?>" defer></script>
    <script src="../js/my_profile.js?v=<?= filemtime(__DIR__ . '/../js/my_profile.js') ?>" defer></script>
    <script src="../js/account_management.js?v=<?= filemtime(__DIR__ . '/../js/account_management.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/notifications.css?v=<?= filemtime(__DIR__ . '/../css/notifications.css') ?>">
    <script src="../js/notifications.js?v=<?= filemtime(__DIR__ . '/../js/notifications.js') ?>" defer></script>
    <script src="../js/account_status_poll.js?v=<?= filemtime(__DIR__ . '/../js/account_status_poll.js') ?>" defer></script>
    <script src="../js/deletion_requests.js?v=<?= filemtime(__DIR__ . '/../js/deletion_requests.js') ?>" defer></script>
    <script src="../js/roles_permissions.js?v=<?= filemtime(__DIR__ . '/../js/roles_permissions.js') ?>" defer></script>
    <?php if (in_array($currentPage, ['my_profile', 'profile_password'], true)): ?>
    <link rel="stylesheet" href="../css/my_profile.css?v=<?= filemtime(__DIR__ . '/../css/my_profile.css') ?>">
    <?php endif; ?>
    <?php if ($currentPage === 'security_logs'): ?>
    <link rel="stylesheet" href="../css/security_logs.css?v=<?= filemtime(__DIR__ . '/../css/security_logs.css') ?>">
    <?php endif; ?>
</head>
<body class="superadmin-body">
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <i class="fas fa-snowflake logo-icon"></i>
                    <div class="logo-text">
                        <span class="logo-main">FROSTLINE</span>
                        <span class="logo-sub">SECURE RAIL SYSTEM</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <span class="nav-section-label">ACCOUNT SECURITY</span>
                    <?php if ($canView('MANAGE_USERS')): ?><a href="app.php?page=account_management" class="nav-item <?= $currentPage === 'account_management' ? 'active' : '' ?>">
                        <i class="fas fa-users-cog"></i>
                        <span>Account Management</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canView('APPROVE_REGISTRATIONS')): ?>
                    <a href="app.php?page=registration_approval" class="nav-item <?= $currentPage === 'registration_approval' ? 'active' : '' ?>">
                        <i class="fas fa-user-check"></i>
                        <span>Registration Approval</span>
                        <?php if (isset($sidebarPendingCounts['registration_approval'])): ?>
                            <?= sidebarPendingBadge($sidebarPendingCounts['registration_approval'], 'registrations awaiting approval') ?>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($canView('BLOCK_ACCOUNTS')): ?><a href="app.php?page=blocked_accounts" class="nav-item <?= $currentPage === 'blocked_accounts' ? 'active' : '' ?>">
                        <i class="fas fa-user-lock"></i>
                        <span>Blocked Accounts</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($currentRole === 'SUPER_ADMIN' && $canView('MANAGE_ROLES')): ?><a href="app.php?page=roles_permissions" class="nav-item <?= $currentPage === 'roles_permissions' ? 'active' : '' ?>">
                        <i class="fas fa-user-shield"></i>
                        <span>Roles & Permissions</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canView('VIEW_SECURITY_LOGS')): ?><a href="app.php?page=security_logs" class="nav-item <?= $currentPage === 'security_logs' ? 'active' : '' ?>">
                        <i class="fas fa-history"></i>
                        <span>Security Logs</span>
                    </a><?php endif; ?>
                </div>

                <div class="nav-section">
                    <span class="nav-section-label">ADMINISTRATION</span>
                    <?php if ($currentRole === 'SUPER_ADMIN' && $canView('MANAGE_ADMINISTRATORS')): ?><a href="app.php?page=administrators" class="nav-item <?= $currentPage === 'administrators' ? 'active' : '' ?>">
                        <i class="fas fa-user-tie"></i>
                        <span>Administrators</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canView('SUBMIT_DELETION_REQUESTS') || $canView('APPROVE_DELETIONS')): ?><a href="app.php?page=deletion_requests" class="nav-item <?= $currentPage === 'deletion_requests' ? 'active' : '' ?>">
                        <i class="fas fa-user-minus"></i>
                        <span>Deletion Requests</span>
                        <?php if (isset($sidebarPendingCounts['deletion_requests'])): ?>
                            <?= sidebarPendingBadge($sidebarPendingCounts['deletion_requests'], 'deletion requests awaiting review') ?>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>

                <div class="nav-section">
                    <span class="nav-section-label">ACCOUNT</span>
                    <a href="app.php?page=my_profile" class="nav-item <?= $currentPage === 'my_profile' ? 'active' : '' ?>">
                        <i class="fas fa-user"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="logout.php" class="nav-item logout">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>

        <div class="main-content">
            <header class="top-header">
                <button class="menu-toggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?= $dashboardRole ?> Dashboard</h1>
                <div class="header-actions">
                    <div class="notification-center">
                        <button class="notification-btn" type="button" aria-label="Notifications" aria-expanded="false" aria-controls="header-notifications" aria-haspopup="dialog">
                            <i class="fas fa-bell" aria-hidden="true"></i>
                            <span class="notification-badge" hidden></span>
                        </button>
                        <section id="header-notifications" class="notification-panel" role="dialog" aria-labelledby="header-notifications-title" hidden>
                            <header class="notification-panel-header"><h2 id="header-notifications-title">Notifications</h2><span data-notification-unread-label></span></header>
                            <div class="notification-panel-actions"><button type="button" data-notification-mark-all>Mark All as Read</button></div>
                            <p class="notification-feedback" role="status" data-notification-feedback></p>
                            <div class="notification-panel-list" data-notification-list><p class="notification-empty">Loading notifications…</p></div>
                            <footer><a href="app.php?page=notifications">View All Notifications</a></footer>
                        </section>
                    </div>
                    <a class="profile-section" href="app.php?page=my_profile" aria-label="My Profile">
                        <div class="profile-avatar">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="profile-info">
                            <span class="profile-name"><?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Account') ?></span>
                            <span class="profile-role"><?= htmlspecialchars(ucwords(strtolower(str_replace('_', ' ', $currentRole)))) ?></span>
                        </div>
                    </a>
                </div>
            </header>
            <?php if ($currentRole === 'SUPER_ADMIN'): ?>
            <div id="superadmin-session-warning" class="superadmin-session-warning" data-status="<?= htmlspecialchars($superAdminSessionState['status'] ?? 'active') ?>" data-remaining="<?= (int)($superAdminSessionState['remaining_seconds'] ?? 0) ?>" hidden role="alert">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <div><strong>Another Super Administrator has signed in.</strong><span>For security, this session will automatically sign out in <b id="superadmin-session-countdown">60</b> seconds.</span></div>
            </div>
            <?php endif; ?>

            <div class="dashboard-content">
                <?= $bodyContent ?>
            </div>
        </div>
    </div>
</body>
</html>
