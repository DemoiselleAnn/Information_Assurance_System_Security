<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/account_status.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id']) || !in_array(strtoupper($_SESSION['role'] ?? 'USER'), ['ADMIN', 'SUPER_ADMIN'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
requirePermission($pdo, 'MANAGE_USERS');
requirePermission($pdo, 'BLOCK_ACCOUNTS');

function formatBlockedAccountDateTime(?string $value): string {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '—';
    }

    return date('Y-m-d H:i', strtotime($value));
}

function redirectToBlockedAccounts(array $params = []): void {
    $query = http_build_query(array_merge(['page' => 'blocked_accounts'], $params));
    header('Location: app.php?' . $query);
    exit();
}

$csrfToken = accountCsrfToken();
$message = trim($_GET['message'] ?? '');
$error = trim($_GET['error'] ?? '');

$searchTerm = trim($_GET['q'] ?? '');
$roleFilter = strtoupper(trim($_GET['role'] ?? ''));
$superAdminLimitMessage = ($_GET['super_admin_limit'] ?? '') === 'unblock'
    ? 'Two usable Super Administrator accounts already exist. Block one of the current Super Administrators before unblocking this account.'
    : '';
if (!in_array($roleFilter, ['', 'ADMIN', 'SUPER_ADMIN', 'USER'], true)) {
    $roleFilter = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['account_management_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token');
    }

    if (($_POST['action'] ?? '') !== 'unblock') {
        redirectToBlockedAccounts(['error' => 'Invalid account action.']);
    }

    $targetId = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($targetId === false || $targetId === null) {
        redirectToBlockedAccounts(['error' => 'Invalid account.']);
    }

    [$updated, $statusMessage] = updateAccountStatus($pdo, (int)$targetId, 'INACTIVE');
    $redirectParams = [
        $updated ? 'message' : ($statusMessage === 'SUPER_ADMIN_LIMIT_UNBLOCK' ? 'super_admin_limit' : 'error') => $statusMessage === 'SUPER_ADMIN_LIMIT_UNBLOCK' ? 'unblock' : $statusMessage,
        'q' => $searchTerm,
        'role' => $roleFilter,
    ];
    redirectToBlockedAccounts($redirectParams);
}

$where = ["u.status = 'BLOCKED'", "u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN')", "(u.role = 'USER' OR u.first_login_required = 0)"];
$params = [];
if ($roleFilter !== '') {
    $where[] = 'u.role = ?';
    $params[] = $roleFilter;
}
if ($searchTerm !== '') {
    $where[] = '(u.id_number LIKE ? OR CONCAT_WS(\' \', u.first_name, u.middle_name, u.last_name) LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = '%' . $searchTerm . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);
$perPage = 10;
$page = max(1, (int)($_GET['page_no'] ?? 1));
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$countStmt->execute($params);
$totalAccounts = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalAccounts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name, u.username, u.email, u.role, u.status, u.created_at,
        (SELECT MAX(sl.created_at) FROM security_logs sl WHERE sl.user_id = u.id AND sl.action = 'LOGIN' AND sl.status = 'SUCCESS') AS last_login
    FROM users u
    WHERE $whereSql
    ORDER BY u.updated_at DESC, u.id DESC
    LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseQuery = http_build_query(['page' => 'blocked_accounts', 'q' => $searchTerm, 'role' => $roleFilter]);
$prevPage = max(1, $page - 1);
$nextPage = min($totalPages, $page + 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blocked Accounts - FROSTLINE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
</head>
<body class="superadmin-body">
    <main class="management-page">
        <header class="management-header">
            <div>
                <p class="eyebrow">ACCOUNT SECURITY</p>
                <h1>Blocked Accounts</h1>
                <p class="management-subtitle">Review accounts that are currently blocked and restore eligible access.</p>
            </div>
        </header>

        <?php if ($message): ?><div class="management-alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="management-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($superAdminLimitMessage !== ''): ?>
            <dialog open class="management-dialog" aria-labelledby="super-admin-limit-title">
                <form method="dialog">
                    <h2 id="super-admin-limit-title">Super Administrator Limit Reached</h2>
                    <p><?= htmlspecialchars($superAdminLimitMessage) ?></p>
                    <button class="btn btn-primary" value="close">OK</button>
                </form>
            </dialog>
        <?php endif; ?>

        <section class="management-panel">
            <form class="filter-bar" method="get" action="app.php">
                <input type="hidden" name="page" value="blocked_accounts">
                <label>
                    Search
                    <input type="search" name="q" value="<?= htmlspecialchars($searchTerm) ?>" placeholder="Search by ID No., name, or username">
                </label>
                <label>
                    Role
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="ADMIN" <?= $roleFilter === 'ADMIN' ? 'selected' : '' ?>>Administrator</option>
                        <option value="USER" <?= $roleFilter === 'USER' ? 'selected' : '' ?>>User</option>
                    </select>
                </label>
                <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Apply Filter</button>
                <a class="btn btn-quiet" href="app.php?page=blocked_accounts">Clear</a>
            </form>

            <div class="table-container">
                <table class="management-table">
                    <thead>
                        <tr>
                            <th>ID NO.</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$accounts): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="empty-title">No blocked accounts found.</div>
                                    <div class="empty-subtitle">Blocked accounts matching the current filters will appear here.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($accounts as $account): ?>
                                <tr data-account-id="<?= (int)$account['id'] ?>">
                                    <td class="data-employee-id"><?= htmlspecialchars($account['id_number']) ?></td>
                                    <td class="data-name"><?= htmlspecialchars(trim(implode(' ', array_filter([$account['first_name'], $account['middle_name'], $account['last_name']]))) ?: '—') ?></td>
                                    <td class="data-username"><?= htmlspecialchars($account['username']) ?></td>
                                    <td class="data-email"><?= htmlspecialchars($account['email']) ?></td>
                                    <td><span class="role-badge data-role-text"><?= htmlspecialchars($account['role']) ?></span></td>
                                    <td><span class="status-badge blocked data-status-text"><?= htmlspecialchars($account['status']) ?></span></td>
                                    <td class="data-date"><?= htmlspecialchars(formatBlockedAccountDateTime($account['last_login'])) ?></td>
                                    <td class="action-cell">
                                        <form method="post" onsubmit="return confirm('Are you sure you want to unblock this account?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                            <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                            <input type="hidden" name="action" value="unblock">
                                            <button class="btn btn-small btn-approve" type="submit"><i class="fas fa-unlock"></i> Unblock</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalAccounts > $perPage): ?>
                <div class="pagination" aria-label="Blocked account pagination">
                    <a class="btn btn-quiet <?= $page <= 1 ? 'disabled' : '' ?>" href="app.php?<?= $baseQuery ?>&page_no=<?= $prevPage ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="page-number <?= $i === $page ? 'active' : '' ?>" href="app.php?<?= $baseQuery ?>&page_no=<?= $i ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a class="btn btn-quiet <?= $page >= $totalPages ? 'disabled' : '' ?>" href="app.php?<?= $baseQuery ?>&page_no=<?= $nextPage ?>" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
