<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/security_log_helpers.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: app.php?page=login');
    exit();
}

$currentRole = strtoupper($_SESSION['role'] ?? 'USER');
$userId = (int)($_SESSION['user_id'] ?? 0);
$securityLogsHome = $currentRole === 'USER' ? 'customer_dashboard' : 'account_management';

if (!in_array($currentRole, ['SUPER_ADMIN', 'ADMIN', 'USER'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
if ($currentRole !== 'USER') requirePermission($pdo, 'VIEW_SECURITY_LOGS');

function getLogDescription(string $action, ?string $reason): string {
    if (!empty($reason)) {
        return $reason;
    }

    $labels = [
        'LOGIN' => 'Successful login',
        'LOGOUT' => 'Successful logout',
        'FAILED_LOGIN' => 'Failed login attempt',
        'ACCOUNT_BLOCKED' => 'Account blocked',
        'ACCOUNT_UNBLOCKED' => 'Account unblocked',
        'ADMIN_UPDATED' => 'Account information updated',
        'ADMIN_CREATED' => 'Administrator account created',
        'SUPER_ADMIN_CREATED' => 'Super Administrator account created',
        'ADMIN_BLOCKED' => 'Administrator account blocked',
        'ADMIN_UNBLOCKED' => 'Administrator account unblocked',
        'DELETION_REQUEST_SUBMITTED' => 'Deletion request submitted',
        'DELETION_REQUEST_APPROVED' => 'Deletion request approved',
        'DELETION_REQUEST_REJECTED' => 'Deletion request rejected',
        'ACCOUNT_DELETED' => 'Account deleted',
        'PERMISSIONS_CHANGED' => 'Permissions changed',
        'ACCOUNT_VIEWED' => 'Account details viewed',
        'ACCOUNT_UPDATED' => 'Account information updated',
        'PASSWORD_RESET' => 'Password reset performed',
        'REGISTRATION_APPROVED' => 'Registration approved',
        'REGISTRATION_REJECTED' => 'Registration rejected',
    ];

    return $labels[$action] ?? ucfirst(strtolower(str_replace('_', ' ', $action)));
}

function formatLogDateTime(?string $value): string {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '—';
    }

    return date('M j, Y', strtotime($value));
}

function formatLogTime(?string $value): string {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '—';
    }

    return date('h:i A', strtotime($value));
}

function getRoleLabel(string $role): string {
    return match ($role) {
        'SUPER_ADMIN' => 'Super Administrator',
        'ADMIN' => 'Administrator',
        'USER' => 'User',
        default => ucfirst(strtolower($role)),
    };
}

function getStatusLabel(string $status): string {
    return match ($status) {
        'SUCCESS' => 'Successful',
        'FAILED' => 'Failed',
        'BLOCKED' => 'Blocked',
        default => ucfirst(strtolower($status)),
    };
}

function buildSecurityWhere(string $role, int $userId, string $alias = 'sl'): array {
    $conditions = ['1=1'];
    $params = [];

    if ($role === 'SUPER_ADMIN') {
        return [$conditions[0], $params];
    }

    if ($role === 'ADMIN') {
        $conditions[] = $alias . '.role IN (\'ADMIN\', \'USER\')';
        return [implode(' AND ', $conditions), $params];
    }

    $conditions[] = $alias . '.user_id = ?';
    $conditions[] = $alias . '.role = \'USER\'';
    $params[] = $userId;

    return [implode(' AND ', $conditions), $params];
}

$monthFilter = isset($_GET['month']) ? (int)$_GET['month'] : 0;
$dateFilter = is_string($_GET['date'] ?? null) ? trim($_GET['date']) : '';
if ($monthFilter < 1 || $monthFilter > 12) $monthFilter = 0;
if ($dateFilter !== '') {
    $dateParts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateFilter, $matches) ? $matches : [];
    if (!$dateParts || !checkdate((int)$dateParts[2], (int)$dateParts[3], (int)$dateParts[1])) {
        $dateFilter = '';
    } else {
        // A complete date takes precedence over a conflicting month in an incoming URL.
        $monthFilter = (int)$dateParts[2];
    }
}
$roleFilter = strtoupper(trim($_GET['role'] ?? ''));
$logTypeFilter = $_GET['log_type'] ?? 'all';
$statusFilter = strtoupper(trim($_GET['status'] ?? ''));
$searchTerm = trim($_GET['q'] ?? '');
$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;

$viewLog = null;
if ($viewId > 0) {
    $viewStmt = $pdo->prepare(
        "SELECT sl.id, sl.user_id, COALESCE(NULLIF(sl.actor_id_number, ''), u.id_number) AS employee_id, u.first_name, u.middle_name, u.last_name, sl.full_name AS snapshot_full_name, COALESCE(NULLIF(sl.username, ''), u.username, 'Deleted Account') AS username, sl.role, sl.action, sl.status, sl.failure_reason, sl.ip_address, sl.user_agent, sl.actor_id_number, sl.target_user_id, sl.target_username, sl.created_at
         FROM security_logs sl
         LEFT JOIN users u ON u.id = sl.user_id
         WHERE sl.id = ?"
    );
    $viewStmt->execute([$viewId]);
    $viewRow = $viewStmt->fetch(PDO::FETCH_ASSOC);
    if ($viewRow) {
        $baseAccessAllowed = false;
        if ($currentRole === 'SUPER_ADMIN') {
            $baseAccessAllowed = true;
        } elseif ($currentRole === 'ADMIN') {
            $baseAccessAllowed = in_array($viewRow['role'], ['ADMIN', 'USER'], true);
        } elseif ($currentRole === 'USER') {
            $baseAccessAllowed = ($viewRow['role'] === 'USER' && (int)$viewRow['user_id'] === $userId);
        }

        if ($baseAccessAllowed) {
            $viewLog = $viewRow;
        }
    }
}

// Return only authorized, escaped details for the modal request.
if (($_GET['details_only'] ?? '') === '1') {
    header('Content-Type: text/html; charset=UTF-8');
    if (!$viewLog) {
        http_response_code(404);
        echo '<p role="alert">This security log is unavailable or you do not have permission to view it.</p>';
    } else {
        require __DIR__ . '/security_log_details.php';
    }
    exit;
}

if (!in_array($roleFilter, ['', 'SUPER_ADMIN', 'ADMIN', 'USER'], true)) {
    $roleFilter = '';
}
if (!in_array($statusFilter, ['', 'SUCCESS', 'FAILED', 'BLOCKED'], true)) {
    $statusFilter = '';
}
$allowedLogTypes = ['all', 'LOGIN', 'LOGOUT', 'SECURITY_ACTION', 'FAILED_LOGIN'];
if (!in_array($logTypeFilter, $allowedLogTypes, true)) {
    $logTypeFilter = 'all';
}

$whereClauses = ['1=1'];
$params = [];

[$baseWhere, $baseParams] = buildSecurityWhere($currentRole, $userId);
$whereClauses[] = $baseWhere;
$params = array_merge($params, $baseParams);

if ($roleFilter !== '') {
    $whereClauses[] = 'sl.role = ?';
    $params[] = $roleFilter;
}

if ($monthFilter >= 1 && $monthFilter <= 12) {
    $whereClauses[] = 'MONTH(sl.created_at) = ?';
    $params[] = $monthFilter;
}

if ($dateFilter !== '') {
    $whereClauses[] = 'DATE(sl.created_at) = ?';
    $params[] = $dateFilter;
}

if ($logTypeFilter === 'LOGIN') {
    $whereClauses[] = 'sl.action = ?';
    $params[] = 'LOGIN';
} elseif ($logTypeFilter === 'LOGOUT') {
    $whereClauses[] = 'sl.action = ?';
    $params[] = 'LOGOUT';
} elseif ($logTypeFilter === 'SECURITY_ACTION') {
    $whereClauses[] = 'sl.action NOT IN (\'LOGIN\', \'LOGOUT\', \'FAILED_LOGIN\')';
} elseif ($logTypeFilter === 'FAILED_LOGIN') {
    $whereClauses[] = 'sl.action = ?';
    $params[] = 'FAILED_LOGIN';
}

if ($statusFilter !== '') {
    $whereClauses[] = 'sl.status = ?';
    $params[] = $statusFilter;
}

if ($searchTerm !== '') {
    $whereClauses[] = "(COALESCE(NULLIF(sl.actor_id_number, ''), u.id_number) LIKE ? OR COALESCE(NULLIF(sl.full_name, ''), u.first_name) LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ? OR COALESCE(NULLIF(sl.username, ''), u.username) LIKE ? OR sl.action LIKE ? OR sl.failure_reason LIKE ?)";
    $like = '%' . $searchTerm . '%';
    $params = array_merge($params, [$like, $like, $like, $like, $like, $like, $like]);
}

/* ---------- KPI COUNTS (same WHERE scope — authorized & filtered) ---------- */
function kpiCount(PDO $pdo, array $whereClauses, array $params, ?string $actionExtra = null): int {
    $wc = $whereClauses;
    $pp = $params;
    if ($actionExtra !== null) {
        $wc[] = $actionExtra;
    }
    $sql = "SELECT COUNT(*) FROM security_logs sl LEFT JOIN users u ON u.id = sl.user_id WHERE " . implode(' AND ', $wc);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($pp);
    return (int)$stmt->fetchColumn();
}
$kpiTotal          = kpiCount($pdo, $whereClauses, $params, null);
$kpiSuccessfulLogin = kpiCount($pdo, $whereClauses, $params, "sl.action = 'LOGIN' AND sl.status = 'SUCCESS'");
$kpiFailedLogin     = kpiCount($pdo, $whereClauses, $params, "sl.action = 'FAILED_LOGIN'");
$kpiSecurityActions = kpiCount($pdo, $whereClauses, $params, "sl.action NOT IN ('LOGIN','LOGOUT','FAILED_LOGIN')");
/* ------------------------------------------------------------------------- */

$perPage = 10;
$totalSql = "SELECT COUNT(*)
    FROM security_logs sl
    LEFT JOIN users u ON u.id = sl.user_id
    WHERE " . implode(' AND ', $whereClauses);
$totalStmt = $pdo->prepare($totalSql);
$totalStmt->execute($params);
$totalLogs = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));
$logsPage = max(1, (int)($_GET['page_no'] ?? 1));
if ($logsPage > $totalPages) $logsPage = $totalPages;
$offset = ($logsPage - 1) * $perPage;

$sql = "SELECT sl.id, COALESCE(NULLIF(sl.actor_id_number, ''), u.id_number) AS employee_id, u.first_name, u.middle_name, u.last_name, sl.full_name AS snapshot_full_name, COALESCE(NULLIF(sl.username, ''), u.username, 'Deleted Account') AS username, sl.role, sl.action, sl.status, sl.failure_reason, sl.created_at
        FROM security_logs sl
        LEFT JOIN users u ON u.id = sl.user_id
        WHERE " . implode(' AND ', $whereClauses) . "
    ORDER BY sl.created_at DESC, sl.id DESC
    LIMIT $perPage OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logs = [];
foreach ($rows as $row) {
    $fullName = trim((string)($row['snapshot_full_name'] ?? '')) ?: (trim(implode(' ', array_filter([$row['first_name'], $row['middle_name'], $row['last_name']]))) ?: 'Deleted Account');
    $logs[] = [
        'id' => (int)$row['id'],
        'employee_id' => $row['employee_id'] ?? '—',
        'full_name' => $fullName,
        'username' => $row['username'] ?? '—',
        'role' => $row['role'] ?? 'USER',
        'action' => $row['action'] ?? 'LOGIN',
        'status' => $row['status'] ?? 'SUCCESS',
        'description' => getLogDescription($row['action'] ?? 'LOGIN', $row['failure_reason'] ?? null),
        'created_at' => $row['created_at'],
    ];
}

$logoutMap = [];
foreach ($logs as $log) {
    if ($log['action'] === 'LOGIN') {
        foreach ($logs as $candidate) {
            if ($candidate['user_id'] ?? null) {
                // no-op placeholder to keep logic clear during render
            }
        }
    }
}

$paginatedLogs = $logs;

$sessionWhere = [
    "login.action = 'LOGIN'",
    "login.status = 'SUCCESS'",
    'login.session_key IS NOT NULL',
];
$sessionParams = [];
[$sessionBaseWhere, $sessionBaseParams] = buildSecurityWhere($currentRole, $userId, 'login');
$sessionWhere[] = $sessionBaseWhere;
$sessionParams = array_merge($sessionParams, $sessionBaseParams);

if ($roleFilter !== '') {
    $sessionWhere[] = 'login.role = ?';
    $sessionParams[] = $roleFilter;
}
if ($monthFilter >= 1 && $monthFilter <= 12) {
    $sessionWhere[] = 'MONTH(login.created_at) = ?';
    $sessionParams[] = $monthFilter;
}
if ($dateFilter !== '') {
    $sessionWhere[] = 'DATE(login.created_at) = ?';
    $sessionParams[] = $dateFilter;
}
if ($logTypeFilter === 'LOGOUT') {
    $sessionWhere[] = 'logout.id IS NOT NULL';
} elseif (in_array($logTypeFilter, ['SECURITY_ACTION', 'FAILED_LOGIN'], true)) {
    $sessionWhere[] = '1 = 0';
}
if ($statusFilter !== '') {
    $sessionWhere[] = 'login.status = ?';
    $sessionParams[] = $statusFilter;
}
if ($searchTerm !== '') {
    $sessionWhere[] = "(COALESCE(NULLIF(login.actor_id_number, ''), u.id_number) LIKE ? OR login.full_name LIKE ? OR login.username LIKE ?)";
    $like = '%' . $searchTerm . '%';
    $sessionParams = array_merge($sessionParams, [$like, $like, $like]);
}

$sessionFrom = "FROM security_logs login
    LEFT JOIN users u ON u.id = login.user_id
    LEFT JOIN user_sessions presence ON presence.session_hash = SHA2(login.session_key, 256)
        AND presence.user_id = login.user_id
    LEFT JOIN security_logs logout ON logout.id = (
        SELECT MIN(logout_candidate.id)
        FROM security_logs logout_candidate
        WHERE logout_candidate.session_key = login.session_key
          AND logout_candidate.user_id <=> login.user_id
          AND logout_candidate.id > login.id
          AND logout_candidate.created_at >= login.created_at
          AND logout_candidate.action = 'LOGOUT'
          AND logout_candidate.status = 'SUCCESS'
    )
    WHERE " . implode(' AND ', $sessionWhere);

/* Count and paginate the filtered sessions independently of security events. */
$sessionTotalStmt = $pdo->prepare("SELECT COUNT(*) $sessionFrom");
$sessionTotalStmt->execute($sessionParams);
$sessionsTotalCount = (int)$sessionTotalStmt->fetchColumn();

$sessionsPerPage = 10;
$sessionsTotalPages = max(1, (int)ceil($sessionsTotalCount / $sessionsPerPage));
$sessionsPage = filter_var($_GET['sessions_page'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$sessionsPage = min($sessionsTotalPages, $sessionsPage === false ? 1 : $sessionsPage);
$sessionsOffset = ($sessionsPage - 1) * $sessionsPerPage;
$sessionsShowFrom = $sessionsTotalCount > 0 ? $sessionsOffset + 1 : 0;
$sessionsShowTo = min($sessionsTotalCount, $sessionsOffset + $sessionsPerPage);

$sessionSql = "SELECT login.id, COALESCE(NULLIF(login.actor_id_number, ''), u.id_number) AS employee_id, login.full_name, login.username, login.role,
        login.created_at AS time_in, logout.created_at AS time_out,
        presence.ended_at AS presence_ended_at, presence.last_activity_at AS presence_last_activity,
        (presence.ended_at IS NULL AND presence.last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1800 SECOND)
            AND presence.last_activity_at <= CURRENT_TIMESTAMP AND u.status = 'ACTIVE') AS presence_active
    $sessionFrom
    ORDER BY login.created_at DESC, login.id DESC
    LIMIT $sessionsPerPage OFFSET $sessionsOffset";
$sessionStmt = $pdo->prepare($sessionSql);
$sessionStmt->execute($sessionParams);
$sessions = $sessionStmt->fetchAll(PDO::FETCH_ASSOC);


$csrfToken = bin2hex(random_bytes(32));
$_SESSION['security_logs_csrf'] = $csrfToken;

/* ------- pagination helpers ------- */
$baseQuery = http_build_query([
    'page'     => 'security_logs',
    'month'    => $monthFilter,
    'date'     => $dateFilter,
    'role'     => $roleFilter,
    'log_type' => $logTypeFilter,
    'status'   => $statusFilter,
    'q'        => $searchTerm,
    'sessions_page' => $sessionsPage,
]);
$sessionsBaseQuery = http_build_query([
    'page' => 'security_logs',
    'month' => $monthFilter,
    'date' => $dateFilter,
    'role' => $roleFilter,
    'log_type' => $logTypeFilter,
    'status' => $statusFilter,
    'q' => $searchTerm,
    'page_no' => $logsPage,
]);
$sessionsPageWindow = range(max(1, $sessionsPage - 2), min($sessionsTotalPages, $sessionsPage + 2));
$showFrom = $totalLogs > 0 ? $offset + 1 : 0;
$showTo   = min($totalLogs, $offset + $perPage);

/* For page-number rendering: window around current page */
$pageWindow = [];
$range = 2;
for ($p = max(1, $logsPage - $range); $p <= min($totalPages, $logsPage + $range); $p++) {
    $pageWindow[] = $p;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Logs - FROSTLINE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <link rel="stylesheet" href="../css/security_logs.css?v=<?= filemtime(__DIR__ . '/../css/security_logs.css') ?>">
    <script src="../js/security_logs.js?v=<?= filemtime(__DIR__ . '/../js/security_logs.js') ?>" defer></script>
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
</head>
<body class="superadmin-body">
    <main class="sl-page">
        <link rel="stylesheet" href="../css/security_logs.css?v=<?= filemtime(__DIR__ . '/../css/security_logs.css') ?>">
        <style>
        /* ---- Critical in-body fallback styles for Security Logs (always applied) ---- */
        .sl-page { width: 100%; min-width: 0; padding: 0; display: flex; flex-direction: column; gap: 18px; color: #173B63; font-family: 'Poppins', sans-serif; }
        .sl-header { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .sl-title { margin: .25rem 0; color: #0F2747; font-size: 1.9rem; font-weight: 700; letter-spacing: -.3px; line-height: 1.15; }
        .sl-subtitle { color: #52749A; margin: 0; font-size: .94rem; font-weight: 400; }
        .sl-breadcrumb { display: inline-flex; align-items: center; gap: .5rem; color: #7892AA; font-size: .82rem; font-weight: 500; }
        .sl-breadcrumb a { color: #52749A; text-decoration: none; }
        .sl-breadcrumb a:hover { color: #2F6FAE; }
        .sl-bc-sep { color: #7892AA; opacity: .7; }
        .sl-bc-current { color: #0F2747; font-weight: 600; }
        .sl-kpi-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; }
        .sl-kpi-card { background: #FFFFFF; border: 1px solid #E3EEF7; border-radius: 16px; box-shadow: 0 4px 18px rgba(23, 59, 99, 0.06); padding: 20px 24px; display: flex; align-items: center; gap: 1rem; transition: transform .2s ease, box-shadow .2s ease; }
        .sl-kpi-card:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(23, 59, 99, .10); }
        .sl-kpi-icon { width: 52px; height: 52px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; flex: 0 0 auto; }
        .sl-kpi-icon-blue { background: #DCEFFA; color: #2F6FAE; }
        .sl-kpi-icon-green { background: #DDF4EA; color: #276749; }
        .sl-kpi-icon-red { background: #F8E1E5; color: #9b2335; }
        .sl-kpi-icon-navy { background: #E2EAF6; color: #0F2747; }
        .sl-kpi-body { min-width: 0; flex: 1 1 auto; }
        .sl-kpi-label { margin: 0 0 .25rem; color: #7892AA; font-size: .78rem; font-weight: 600; }
        .sl-kpi-value { margin: 0 0 .2rem; color: #0F2747; font-size: 1.65rem; font-weight: 700; line-height: 1.1; }
        .sl-kpi-sub { margin: 0; color: #7892AA; font-size: .76rem; }
        .sl-card { background: #FFFFFF; border: 1px solid #E3EEF7; border-radius: 16px; box-shadow: 0 4px 18px rgba(23, 59, 99, 0.06); padding: 24px; width: 100%; box-sizing: border-box; }
        .sl-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; margin-bottom: 1.2rem; flex-wrap: wrap; }
        .sl-card-heading { display: flex; align-items: flex-start; gap: .85rem; min-width: 0; }
        .sl-icon-circle { width: 42px; height: 42px; border-radius: 12px; background: #DCEFFA; color: #2F6FAE; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex: 0 0 auto; }
        .sl-icon-blue  { background: #DCEFFA; color: #2F6FAE; }
        .sl-icon-green { background: #DDF4EA; color: #276749; }
        .sl-icon-navy  { background: #E2EAF6; color: #0F2747; }
        .sl-card-title { margin: 0; color: #0F2747; font-size: 1.1rem; font-weight: 600; }
        .sl-card-eyebrow { margin: 0 0 .2rem; color: #7892AA; font-size: .72rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; }
        .sl-card-subtitle { margin: .25rem 0 0; color: #7892AA; font-size: .82rem; line-height: 1.5; font-weight: 400; }
        .sl-muted-note { color: #7892AA; font-size: .78rem; font-weight: 500; align-self: center; }
        .sl-filter-bar { display: flex; flex-direction: column; gap: 1rem; width: 100%; }
        .sl-filter-row { display: grid; gap: 1rem; align-items: end; }
        .sl-filter-row-top { grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .sl-filter-row-bottom { grid-template-columns: 1fr auto; }
        .sl-filter-field { display: flex; flex-direction: column; gap: .35rem; min-width: 0; }
        .sl-filter-search { min-width: 0; }
        .sl-filter-label { color: #52749A; font-size: .78rem; font-weight: 600; padding-left: 2px; }
        .sl-filter-field select, .sl-filter-field input[type="date"], .sl-search-wrap input[type="search"] { box-sizing: border-box; width: 100%; min-height: 42px; padding: .55rem .8rem; border: 1px solid #E3EEF7; border-radius: 10px; background: #fff; color: #173B63; font: inherit; font-size: .86rem; transition: border-color .18s ease, box-shadow .18s ease; }
        .sl-filter-field select:focus, .sl-filter-field input[type="date"]:focus, .sl-search-wrap input[type="search"]:focus { outline: none; border-color: #75B9E6; box-shadow: 0 0 0 3px rgba(117,185,230,.18); }
        .sl-search-wrap { position: relative; display: block; width: 100%; }
        .sl-search-wrap input[type="search"] { padding-left: 2.25rem; padding-right: .8rem; }
        .sl-search-icon { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: #7892AA; font-size: .85rem; }
        .sl-filter-actions { display: flex; align-items: center; gap: .6rem; flex-wrap: wrap; }
        .sl-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; padding: .58rem 1.05rem; min-height: 42px; border-radius: 10px; border: 1px solid transparent; font: inherit; font-size: .84rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s ease; white-space: nowrap; box-sizing: border-box; color: inherit; }
        .sl-btn-primary { background: #2F6FAE; color: #fff; }
        .sl-btn-primary:hover { background: #0F2747; transform: translateY(-1px); box-shadow: 0 4px 10px rgba(47,111,174,.22); }
        .sl-btn-outline { background: #fff; color: #52749A; border-color: #E3EEF7; }
        .sl-btn-outline:hover { background: #EAF4FC; border-color: #75B9E6; color: #2F6FAE; }
        .sl-badge { display: inline-flex; align-items: center; gap: .35rem; padding: .28rem .7rem; border-radius: 999px; font-size: .72rem; font-weight: 700; letter-spacing: .15px; white-space: nowrap; line-height: 1; }
        .sl-badge-role { background: #DCEFFA; color: #2F6FAE; }
        .sl-badge-success { background: #DDF4EA; color: #276749; }
        .sl-badge-active { background: #DDF4EA; color: #276749; }
        .sl-badge-failed { background: #F8E1E5; color: #9b2335; }
        .sl-badge-blocked { background: #FFF0D2; color: #966b00; }
        .sl-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .sl-dot-success { background: #34A853; box-shadow: 0 0 0 3px rgba(52,168,83,.15); }
        .sl-dot-active  { background: #34A853; box-shadow: 0 0 0 3px rgba(52,168,83,.15); }
        .sl-dot-failed  { background: #E53E3E; box-shadow: 0 0 0 3px rgba(229,62,62,.15); }
        .sl-dot-blocked { background: #D69E2E; box-shadow: 0 0 0 3px rgba(214,158,46,.15); }
        .sl-action-pill { display: inline-flex; align-items: center; justify-content: center; padding: .3rem .6rem; border-radius: 8px; font-size: .72rem; font-weight: 700; letter-spacing: .25px; background: #EAF4FC; color: #2F6FAE; white-space: nowrap; }
        .sl-table-wrap { width: 100%; overflow-x: auto; border: 1px solid #E3EEF7; border-radius: 10px; background: #fff; -webkit-overflow-scrolling: touch; }
        .sl-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 720px; font-size: .84rem; }
        .sl-table-events { min-width: 1180px; }
        .sl-table thead th { background: #EDF6FE; color: #0F2747; font-size: .74rem; font-weight: 700; letter-spacing: .3px; text-transform: uppercase; padding: .85rem 1rem; text-align: left; border-bottom: 1px solid #E3EEF7; white-space: nowrap; position: sticky; top: 0; z-index: 1; }
        .sl-table thead th:first-child { border-top-left-radius: 10px; }
        .sl-table thead th:last-child { border-top-right-radius: 10px; }
        .sl-table tbody td { padding: .75rem 1rem; border-bottom: 1px solid #EEF5FB; color: #173B63; vertical-align: middle; text-align: left; }
        .sl-table tbody tr:last-child td { border-bottom: none; }
        .sl-table tbody tr:hover td { background: #F7FBFF; }
        .sl-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: .82rem; font-weight: 500; color: #52749A; letter-spacing: .1px; }
        .sl-desc-cell { max-width: 260px; }
        .sl-desc-text { display: block; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .sl-col-action { width: 90px; text-align: center; }
        .action-cell { text-align: center; }
        .row-action-menu { position: relative; display: inline-flex; }
        .row-action-trigger { all: unset; cursor: pointer; width: 32px; height: 32px; border-radius: 8px; background: #EAF4FC; color: #2F6FAE; display: inline-flex; align-items: center; justify-content: center; font-size: .9rem; transition: background .2s ease, color .2s ease, transform .2s ease; }
        .row-action-trigger:hover { background: #2F6FAE; color: #fff; transform: translateY(-1px); }
        .row-action-trigger[aria-expanded="true"] { background: #0F2747; color: #fff; }
        .row-action-popover { display: none; position: fixed; background: #fff; border: 1px solid #E3EEF7; border-radius: 10px; box-shadow: 0 8px 22px rgba(23,59,99,.18); padding: .3rem; z-index: 50; min-width: 140px; }
        .row-action-entry a { display: flex; align-items: center; gap: .5rem; padding: .5rem .65rem; border-radius: 7px; color: #173B63; font-size: .8rem; font-weight: 500; text-decoration: none; transition: background .15s ease, color .15s ease; }
        .row-action-entry a:hover { background: #EAF4FC; color: #2F6FAE; }
        .sl-empty { text-align: center; padding: 2.5rem 1rem; }
        .sl-empty-title { color: #0F2747; font-size: 1rem; font-weight: 600; margin: 0 0 .25rem; }
        .sl-empty-sub { color: #7892AA; font-size: .84rem; margin: 0; }
        .sl-pagination { margin-top: 1.2rem; padding-top: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; border-top: 1px solid #E3EEF7; }
        .sl-pag-info { color: #7892AA; font-size: .82rem; font-weight: 500; }
        .sl-pag-info strong { color: #0F2747; font-weight: 600; }
        .sl-pag-controls { display: inline-flex; align-items: center; gap: .3rem; flex-wrap: wrap; }
        .sl-pag-btn { width: 34px; height: 34px; border-radius: 8px; background: #fff; border: 1px solid #E3EEF7; color: #52749A; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: .78rem; transition: all .2s ease; cursor: pointer; }
        .sl-pag-btn:hover:not(.disabled) { border-color: #75B9E6; background: #EAF4FC; color: #2F6FAE; }
        .sl-pag-btn.disabled { opacity: .4; pointer-events: none; cursor: not-allowed; }
        .sl-page-link { min-width: 34px; height: 34px; padding: 0 .7rem; border-radius: 8px; background: #fff; border: 1px solid #E3EEF7; color: #52749A; display: inline-flex; align-items: center; justify-content: center; font-size: .82rem; font-weight: 600; text-decoration: none; transition: all .2s ease; }
        .sl-page-link:hover { border-color: #75B9E6; background: #EAF4FC; color: #2F6FAE; }
        .sl-page-link.active { background: #2F6FAE; border-color: #2F6FAE; color: #fff; box-shadow: 0 3px 8px rgba(47,111,174,.28); }
        .sl-page-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; color: #7892AA; font-size: .9rem; }
        .sl-detail-grid { list-style: none; padding: 0; margin: 0; display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: .75rem 1.25rem; }
        .sl-detail-wide { grid-column: 1 / -1; }
        .sl-detail-grid > div { padding: .7rem .9rem; border: 1px solid #E3EEF7; border-radius: 10px; background: #FCFDFE; }
        .sl-detail-grid dt { margin: 0 0 .25rem; color: #7892AA; font-size: .72rem; font-weight: 700; letter-spacing: .2px; }
        .sl-detail-grid dd { margin: 0; color: #173B63; font-size: .86rem; font-weight: 500; word-break: break-word; }
        .sr-only { position: absolute !important; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        @media (max-width: 1200px) { .sl-kpi-grid { grid-template-columns: repeat(2,1fr); } .sl-filter-row-top { grid-template-columns: repeat(3,1fr); } .sl-detail-grid { grid-template-columns: repeat(2,1fr); } }
        @media (max-width: 900px) { .sl-filter-row-top { grid-template-columns: repeat(2,1fr); } .sl-filter-row-bottom { grid-template-columns: 1fr; } .sl-filter-actions { justify-content: flex-start; } .sl-pagination { flex-direction: column; align-items: flex-start; } }
        @media (max-width: 640px) { .sl-title { font-size: 1.5rem; } .sl-kpi-grid { grid-template-columns: 1fr 1fr; } .sl-kpi-card { padding: 16px; gap: .75rem; } .sl-kpi-icon { width: 44px; height: 44px; font-size: 1.05rem; } .sl-kpi-value { font-size: 1.35rem; } .sl-filter-row-top { grid-template-columns: 1fr; } .sl-detail-grid { grid-template-columns: 1fr; } .sl-table thead th, .sl-table tbody td { padding: .65rem .75rem; } .sl-pag-controls { gap: .2rem; } .sl-page-link { min-width: 30px; height: 30px; padding: 0 .55rem; } .sl-pag-btn { width: 30px; height: 30px; } }
        @media (max-width: 420px) { .sl-kpi-grid { grid-template-columns: 1fr; } .sl-filter-actions { flex-direction: column; align-items: stretch; } .sl-filter-actions > * { width: 100%; justify-content: center; } }
        </style>

        <!-- ===== PAGE HEADER ===== -->
        <header class="sl-header">
            <div class="sl-header-left">
                <h1 class="sl-title">Security Logs</h1>
                <p class="sl-subtitle">Review account activity, login history, and selected security actions.</p>
            </div>
            <nav class="sl-breadcrumb" aria-label="Breadcrumb">
                <a href="app.php?page=<?= htmlspecialchars($securityLogsHome) ?>">Home</a>
                <span class="sl-bc-sep">›</span>
                <span class="sl-bc-current">Security Logs</span>
            </nav>
        </header>

        <!-- ===== KPI CARDS ===== -->
        <section class="sl-kpi-grid" aria-label="Security log summary">
            <article class="sl-kpi-card">
                <div class="sl-kpi-icon sl-kpi-icon-blue">
                    <i class="fas fa-users" aria-hidden="true"></i>
                </div>
                <div class="sl-kpi-body">
                    <p class="sl-kpi-label">Total Logs</p>
                    <p class="sl-kpi-value"><?= number_format($kpiTotal) ?></p>
                    <p class="sl-kpi-sub">All recorded activities</p>
                </div>
            </article>
            <article class="sl-kpi-card">
                <div class="sl-kpi-icon sl-kpi-icon-green">
                    <i class="fas fa-right-to-bracket" aria-hidden="true"></i>
                </div>
                <div class="sl-kpi-body">
                    <p class="sl-kpi-label">Successful Logins</p>
                    <p class="sl-kpi-value"><?= number_format($kpiSuccessfulLogin) ?></p>
                    <p class="sl-kpi-sub">Completed sign-ins</p>
                </div>
            </article>
            <article class="sl-kpi-card">
                <div class="sl-kpi-icon sl-kpi-icon-red">
                    <i class="fas fa-xmark" aria-hidden="true"></i>
                </div>
                <div class="sl-kpi-body">
                    <p class="sl-kpi-label">Failed Logins</p>
                    <p class="sl-kpi-value"><?= number_format($kpiFailedLogin) ?></p>
                    <p class="sl-kpi-sub">Unsuccessful attempts</p>
                </div>
            </article>
            <article class="sl-kpi-card">
                <div class="sl-kpi-icon sl-kpi-icon-navy">
                    <i class="fas fa-shield-halved" aria-hidden="true"></i>
                </div>
                <div class="sl-kpi-body">
                    <p class="sl-kpi-label">Security Actions</p>
                    <p class="sl-kpi-value"><?= number_format($kpiSecurityActions) ?></p>
                    <p class="sl-kpi-sub">Password changes, etc.</p>
                </div>
            </article>
        </section>

        <!-- ===== FILTER LOGS CARD ===== -->
        <section class="sl-card sl-filter-card" id="security-log-filters">
            <header class="sl-card-header">
                <div class="sl-card-heading">
                    <div class="sl-icon-circle">
                        <i class="fas fa-filter" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="sl-card-title">Filter Logs</h2>
                    </div>
                </div>
            </header>

            <form class="sl-filter-bar" method="get" action="app.php">
                <input type="hidden" name="page" value="security_logs">

                <div class="sl-filter-row sl-filter-row-top">
                    <label class="sl-filter-field">
                        <span class="sl-filter-label">Month</span>
                        <select name="month" id="sl-month-filter">
                            <option value="0">All Months</option>
                            <?php foreach (range(1, 12) as $m): ?>
                                <option value="<?= $m ?>" <?= $monthFilter === $m ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <div class="sl-filter-field sl-date-field">
                        <label class="sl-filter-label" for="sl-date-filter">Date</label>
                        <div class="sl-date-picker" data-security-date-picker>
                            <input id="sl-date-filter" type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>">
                        </div>
                    </div>

                    <label class="sl-filter-field">
                        <span class="sl-filter-label">Role</span>
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="SUPER_ADMIN" <?= $roleFilter === 'SUPER_ADMIN' ? 'selected' : '' ?>>Super Administrator</option>
                            <option value="ADMIN"       <?= $roleFilter === 'ADMIN'       ? 'selected' : '' ?>>Administrator</option>
                            <option value="USER"        <?= $roleFilter === 'USER'        ? 'selected' : '' ?>>User</option>
                        </select>
                    </label>

                    <label class="sl-filter-field">
                        <span class="sl-filter-label">Log Type</span>
                        <select name="log_type">
                            <option value="all"             <?= $logTypeFilter === 'all'             ? 'selected' : '' ?>>All Activity</option>
                            <option value="LOGIN"           <?= $logTypeFilter === 'LOGIN'           ? 'selected' : '' ?>>Login</option>
                            <option value="LOGOUT"          <?= $logTypeFilter === 'LOGOUT'          ? 'selected' : '' ?>>Logout</option>
                            <option value="SECURITY_ACTION" <?= $logTypeFilter === 'SECURITY_ACTION' ? 'selected' : '' ?>>Security Actions</option>
                            <option value="FAILED_LOGIN"    <?= $logTypeFilter === 'FAILED_LOGIN'    ? 'selected' : '' ?>>Failed Login</option>
                        </select>
                    </label>

                    <label class="sl-filter-field">
                        <span class="sl-filter-label">Status</span>
                        <select name="status">
                            <option value=""        <?= $statusFilter === ''        ? 'selected' : '' ?>>All Statuses</option>
                            <option value="SUCCESS" <?= $statusFilter === 'SUCCESS' ? 'selected' : '' ?>>Successful</option>
                            <option value="FAILED"  <?= $statusFilter === 'FAILED'  ? 'selected' : '' ?>>Failed</option>
                            <option value="BLOCKED" <?= $statusFilter === 'BLOCKED' ? 'selected' : '' ?>>Blocked</option>
                        </select>
                    </label>
                </div>

                <div class="sl-filter-row sl-filter-row-bottom">
                    <label class="sl-filter-field sl-filter-search">
                        <span class="sl-filter-label sr-only">Search</span>
                        <span class="sl-search-wrap">
                            <i class="fas fa-search sl-search-icon" aria-hidden="true"></i>
                            <input
                                type="search"
                                name="q"
                                value="<?= htmlspecialchars($searchTerm) ?>"
                                placeholder="Search by ID No., name, or username"
                            >
                        </span>
                    </label>

                    <div class="sl-filter-actions">
                        <button class="sl-btn sl-btn-primary" type="submit">
                            <i class="fas fa-filter" aria-hidden="true"></i>
                            Apply Filter
                        </button>
                        <a class="sl-btn sl-btn-outline" href="app.php?page=security_logs">
                            <i class="fas fa-rotate-left" aria-hidden="true"></i>
                            Clear Filter
                        </a>
                    </div>
                </div>
            </form>
        </section>

        <!-- ===== LOGIN SESSIONS CARD ===== -->
        <section class="sl-card" id="login-sessions">
            <header class="sl-card-header">
                <div class="sl-card-heading">
                    <div class="sl-icon-circle sl-icon-blue">
                        <i class="fas fa-display" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="sl-card-title">Login Sessions</h2>
                        <p class="sl-card-subtitle">Login and logout sessions, newest first. Active requires recent matching session presence. Ended or Expired has presence evidence but no recorded logout; Unclosed has no matching presence record. Missing logout times are left blank.</p>
                    </div>
                </div>
            </header>

            <div class="sl-table-wrap">
                <table class="sl-table sl-table-sessions">
                    <thead>
                        <tr>
                            <th>ID NO.</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$sessions): ?>
                            <tr>
                                <td colspan="8" class="sl-empty">
                                    <div class="sl-empty-title">No login sessions found.</div>
                                    <div class="sl-empty-sub">Try adjusting your search or filter criteria.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $session): ?>
                                <?php $sessionLabel = securityLogSessionLabel($session); $isActive = $sessionLabel === 'Active'; $sessionClass = $isActive ? 'sl-badge-active' : ($sessionLabel === 'Completed' ? 'sl-badge-success' : 'sl-badge-role'); ?>
                                <tr>
                                    <td class="sl-mono"><?= htmlspecialchars($session['employee_id'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($session['full_name'] ?? 'Unknown') ?></td>
                                    <td class="sl-mono"><?= htmlspecialchars($session['username'] ?? '—') ?></td>
                                    <td>
                                        <span class="sl-badge sl-badge-role">
                                            <?= htmlspecialchars(getRoleLabel($session['role'] ?? 'USER')) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(formatLogTime($session['time_in'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars(formatLogTime($session['time_out'] ?? null)) ?></td>
                                    <td><?= htmlspecialchars(formatLogDateTime($session['time_in'] ?? null)) ?></td>
                                    <td>
                                        <span class="sl-badge <?= $sessionClass ?>">
                                            <?php if ($isActive || $sessionLabel === 'Completed'): ?><span class="sl-dot <?= $isActive ? 'sl-dot-active' : 'sl-dot-success' ?>" aria-hidden="true"></span><?php endif; ?>
                                            <?= htmlspecialchars($sessionLabel) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <footer class="sl-pagination" aria-label="Login Sessions pagination">
                <div class="sl-pag-info">
                    Showing <strong><?= number_format($sessionsShowFrom) ?></strong>–<strong><?= number_format($sessionsShowTo) ?></strong> of <strong><?= number_format($sessionsTotalCount) ?></strong> sessions.
                </div>
                <?php if ($sessionsTotalCount > 0): ?>
                    <nav class="sl-pag-controls" aria-label="Login Sessions pages">
                        <?php if ($sessionsPage <= 1): ?>
                            <span class="sl-pag-btn disabled" aria-disabled="true" aria-label="Previous"><i class="fas fa-chevron-left" aria-hidden="true"></i></span>
                        <?php else: ?>
                            <a class="sl-pag-btn" aria-label="Previous" title="Previous" href="app.php?<?= htmlspecialchars($sessionsBaseQuery) ?>&sessions_page=<?= $sessionsPage - 1 ?>"><i class="fas fa-chevron-left" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php if ($sessionsPageWindow[0] > 1): ?>
                            <a class="sl-page-link" href="app.php?<?= htmlspecialchars($sessionsBaseQuery) ?>&sessions_page=1">1</a>
                            <?php if ($sessionsPageWindow[0] > 2): ?><span class="sl-page-ellipsis">…</span><?php endif; ?>
                        <?php endif; ?>
                        <?php foreach ($sessionsPageWindow as $sessionPageNumber): ?>
                            <a class="sl-page-link <?= $sessionPageNumber === $sessionsPage ? 'active' : '' ?>"
                               <?= $sessionPageNumber === $sessionsPage ? 'aria-current="page"' : '' ?>
                               href="app.php?<?= htmlspecialchars($sessionsBaseQuery) ?>&sessions_page=<?= $sessionPageNumber ?>"><?= $sessionPageNumber ?></a>
                        <?php endforeach; ?>
                        <?php if (end($sessionsPageWindow) < $sessionsTotalPages): ?>
                            <?php if (end($sessionsPageWindow) < $sessionsTotalPages - 1): ?><span class="sl-page-ellipsis">…</span><?php endif; ?>
                            <a class="sl-page-link" href="app.php?<?= htmlspecialchars($sessionsBaseQuery) ?>&sessions_page=<?= $sessionsTotalPages ?>"><?= $sessionsTotalPages ?></a>
                        <?php endif; ?>
                        <?php if ($sessionsPage >= $sessionsTotalPages): ?>
                            <span class="sl-pag-btn disabled" aria-disabled="true" aria-label="Next"><i class="fas fa-chevron-right" aria-hidden="true"></i></span>
                        <?php else: ?>
                            <a class="sl-pag-btn" aria-label="Next" title="Next" href="app.php?<?= htmlspecialchars($sessionsBaseQuery) ?>&sessions_page=<?= $sessionsPage + 1 ?>"><i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </footer>
        </section>

        <!-- ===== DETAILED SECURITY EVENTS CARD ===== -->
        <section class="sl-card" id="security-events">
            <header class="sl-card-header">
                <div class="sl-card-heading">
                    <div class="sl-icon-circle sl-icon-navy">
                        <i class="fas fa-list" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h2 class="sl-card-title">Detailed Security Events</h2>
                        <p class="sl-card-subtitle">Complete list of security-related activities.</p>
                    </div>
                </div>
            </header>

            <div class="sl-table-wrap">
                <table class="sl-table sl-table-events">
                    <thead>
                        <tr>
                            <th>Log ID</th>
                            <th>ID NO.</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th class="sl-col-action">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$paginatedLogs): ?>
                            <tr>
                                <td colspan="11" class="sl-empty">
                                    <div class="sl-empty-title">No security logs found.</div>
                                    <div class="sl-empty-sub">Try adjusting your search or filter criteria.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paginatedLogs as $log): ?>
                                <?php
                                $statusClass = 'sl-badge-failed';
                                $statusDot   = 'sl-dot-failed';
                                $s = strtoupper($log['status'] ?? 'SUCCESS');
                                if ($s === 'SUCCESS') { $statusClass = 'sl-badge-success'; $statusDot = 'sl-dot-success'; }
                                elseif ($s === 'BLOCKED') { $statusClass = 'sl-badge-blocked'; $statusDot = 'sl-dot-blocked'; }
                                ?>
                                <tr>
                                    <td class="sl-mono"><?= htmlspecialchars('LOG-' . str_pad((string)$log['id'], 4, '0', STR_PAD_LEFT)) ?></td>
                                    <td class="sl-mono"><?= htmlspecialchars($log['employee_id']) ?></td>
                                    <td><?= htmlspecialchars($log['full_name']) ?></td>
                                    <td class="sl-mono"><?= htmlspecialchars($log['username']) ?></td>
                                    <td>
                                        <span class="sl-badge sl-badge-role">
                                            <?= htmlspecialchars(getRoleLabel($log['role'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="sl-action-pill sl-action-<?= htmlspecialchars(strtolower($log['action'])) ?>">
                                            <?= htmlspecialchars($log['action']) ?>
                                        </span>
                                    </td>
                                    <td class="sl-desc-cell">
                                        <span class="sl-desc-text" title="<?= htmlspecialchars($log['description']) ?>">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars(formatLogDateTime($log['created_at'])) ?></td>
                                    <td><?= htmlspecialchars(formatLogTime($log['created_at'])) ?></td>
                                    <td>
                                        <span class="sl-badge <?= $statusClass ?>">
                                            <span class="sl-dot <?= $statusDot ?>" aria-hidden="true"></span>
                                            <?= htmlspecialchars(getStatusLabel($log['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="action-cell sl-col-action">
                                        <button
                                            type="button"
                                            class="btn btn-small btn-quiet"
                                            data-record-view
                                            data-record-key="security_logs:<?= (int)$log['id'] ?>"
                                            data-details-url="app.php?page=security_logs&view=<?= (int)$log['id'] ?>&details_only=1"
                                            data-log-id="<?= (int)$log['id'] ?>"
                                            aria-haspopup="dialog"
                                        >
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- ===== PAGINATION ===== -->
            <footer class="sl-pagination" aria-label="Security log pagination">
                <div class="sl-pag-info">
                    <?php if ($totalLogs > 0): ?>
                        Showing <strong><?= number_format($showFrom) ?></strong>–<strong><?= number_format($showTo) ?></strong> of <strong><?= number_format($totalLogs) ?></strong> logs
                    <?php else: ?>
                        No logs to display
                    <?php endif; ?>
                </div>
                <div class="sl-pag-controls">
                    <?php
                    $prevPage = max(1, $logsPage - 1);
                    $nextPage = min($totalPages, $logsPage + 1);
                    ?>
                    <?php if ($totalLogs > 0): ?>
                        <a aria-label="Previous" class="sl-pag-btn <?= $logsPage <= 1 ? 'disabled' : '' ?>"
                           href="app.php?<?= $baseQuery ?>&page_no=<?= $prevPage ?>"
                           <?= $logsPage <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            <i class="fas fa-chevron-left" aria-hidden="true"></i>
                        </a>

                        <?php if ($pageWindow && $pageWindow[0] > 1): ?>
                            <a class="sl-page-link" href="app.php?<?= $baseQuery ?>&page_no=1">1</a>
                            <?php if ($pageWindow[0] > 2): ?>
                                <span class="sl-page-ellipsis">…</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php foreach ($pageWindow as $p): ?>
                            <a class="sl-page-link <?= $p === $logsPage ? 'active' : '' ?>"
                               href="app.php?<?= $baseQuery ?>&page_no=<?= $p ?>"><?= $p ?></a>
                        <?php endforeach; ?>

                        <?php if ($pageWindow && end($pageWindow) < $totalPages): ?>
                            <?php if (end($pageWindow) < $totalPages - 1): ?>
                                <span class="sl-page-ellipsis">…</span>
                            <?php endif; ?>
                            <a class="sl-page-link" href="app.php?<?= $baseQuery ?>&page_no=<?= $totalPages ?>"><?= $totalPages ?></a>
                        <?php endif; ?>

                        <a aria-label="Next" class="sl-pag-btn <?= $logsPage >= $totalPages ? 'disabled' : '' ?>"
                           href="app.php?<?= $baseQuery ?>&page_no=<?= $nextPage ?>"
                           <?= $logsPage >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>
                            <i class="fas fa-chevron-right" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </footer>
        </section>

        <?php if ($viewLog): ?>
        <template data-record-details data-title="Security Log Details" data-record-key="security_logs:<?= (int)$viewLog['id'] ?>" data-record-id="<?= htmlspecialchars('LOG-' . str_pad((string)$viewLog['id'], 4, '0', STR_PAD_LEFT)) ?>">
            <?php require __DIR__ . '/security_log_details.php'; ?>
        </template>
        <?php endif; ?>
        <?php require __DIR__ . '/record_details_modal.php'; ?>

    </main>
</body>
</html>
