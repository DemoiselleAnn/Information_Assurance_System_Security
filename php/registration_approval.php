<?php
require_once __DIR__ . '/security_log_helpers.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/notifications_store.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$role = strtoupper($_SESSION['role'] ?? 'USER');
if (!isset($_SESSION['user_id']) || !in_array($role, ['ADMIN', 'SUPER_ADMIN'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
requirePermission($pdo, 'APPROVE_REGISTRATIONS');

function approvalRedirect(array $params = []): void {
    header('Location: app.php?' . http_build_query(array_merge(['page' => 'registration_approval'], $params)));
    exit;
}

function approvalCsrf(): string {
    if (empty($_SESSION['registration_approval_csrf'])) {
        $_SESSION['registration_approval_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['registration_approval_csrf'];
}

function approvalName(array $account): string {
    return trim(implode(' ', array_filter([$account['first_name'], $account['middle_name'], $account['last_name'], $account['extension_name']])));
}

function logApprovalEvent(PDO $pdo, string $action, array $target): void {
    try {
        $actorStmt = $pdo->prepare('SELECT id_number, username, first_name, middle_name, last_name, extension_name, role FROM users WHERE id = ? LIMIT 1');
        $actorStmt->execute([(int)$_SESSION['user_id']]);
        $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
        if (!$actor) return;
        $actorName = securityLogFullName($actor);
        $details = 'Registration: ' . approvalName($target) . ' (' . $target['id_number'] . ')';
        $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, target_user_id, target_username, status, failure_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SUCCESS', ?)");
        $stmt->execute([(int)$_SESSION['user_id'], $actor['username'], $actorName, $actor['role'], $action, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $actor['id_number'], $target['id'], $target['username'], $details]);
    } catch (Throwable $e) {
        error_log('Registration approval log error: ' . $e->getMessage());
    }
}

$csrfToken = approvalCsrf();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Invalid request token');
    }

    $accountId = (int)($_POST['account_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['approve', 'reject'], true)) approvalRedirect(['error' => 'Invalid approval action.']);

    $targetStmt = $pdo->prepare("SELECT id, id_number, first_name, middle_name, last_name, extension_name, username, email, role, status, created_at FROM users WHERE id = ? AND role = 'USER' AND status = 'PENDING' LIMIT 1");
    $targetStmt->execute([$accountId]);
    $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$target) approvalRedirect(['error' => 'This pending registration is no longer available.']);

    $newStatus = $action === 'approve' ? 'INACTIVE' : 'REJECTED';
    $pdo->beginTransaction();
    try {
        $update = $pdo->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role = 'USER' AND status = 'PENDING'");
        $update->execute([$newStatus, $accountId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('This pending registration was already processed.');
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        approvalRedirect(['error' => $e->getMessage()]);
    }

    logApprovalEvent($pdo, $action === 'approve' ? 'REGISTRATION_APPROVED' : 'REGISTRATION_REJECTED', $target);
    notifyRegistrationDecision($pdo, $accountId, $action);
    approvalRedirect(['message' => $action === 'approve' ? 'Registration approved successfully.' : 'Registration rejected successfully.']);
}

$viewRegistration = null;
$viewRegistrationId = filter_var($_GET['view'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($viewRegistrationId) {
    // Use the same registration scope and fields as the table; never load administrative accounts.
    $viewStmt = $pdo->prepare("SELECT id, id_number, first_name, middle_name, last_name, extension_name, username, email, role, status, created_at FROM users WHERE id = ? AND role = 'USER' LIMIT 1");
    $viewStmt->execute([$viewRegistrationId]);
    $viewRegistration = $viewStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$query = trim($_GET['q'] ?? '');
$status = strtoupper(trim($_GET['status'] ?? 'PENDING'));
if (!in_array($status, ['PENDING', 'ACTIVE', 'REJECTED'], true)) $status = 'PENDING';
$page = max(1, (int)($_GET['page_no'] ?? 1));
$perPage = 10;
$where = ["u.role = 'USER'", 'u.status = ?'];
$params = [$status];
if ($query !== '') {
    $where[] = '(u.id_number LIKE ? OR u.first_name LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $like = '%' . $query . '%';
    array_push($params, $like, $like, $like, $like, $like, $like);
}
$whereSql = implode(' AND ', $where);
$count = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$list = $pdo->prepare("SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name, u.extension_name, u.username, u.email, u.role, u.status, u.created_at FROM users u WHERE $whereSql ORDER BY u.created_at ASC, u.id ASC LIMIT $perPage OFFSET $offset");
$list->execute($params);
$registrations = $list->fetchAll(PDO::FETCH_ASSOC);
$message = $_GET['message'] ?? '';
$error = $_GET['error'] ?? '';
function approvalLink(array $changes = []): string {
    return 'app.php?' . http_build_query(array_merge($_GET, ['page' => 'registration_approval'], $changes));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registration Approval - FROSTLINE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
</head>
<body class="superadmin-body">
<main class="management-page">
    <header class="management-header"><div><p class="eyebrow">ACCOUNT SECURITY</p><h1>Registration Approval</h1><p class="management-subtitle">Review pending user registrations before they can log in.</p></div></header>
    <?php if ($message): ?><div class="management-alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="management-alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <section class="management-panel">
        <form class="filter-bar" method="get" action="app.php">
            <input type="hidden" name="page" value="registration_approval">
            <label>ID No. / Search<input type="search" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search by ID No., name, or username"></label>
            <label>Status<select name="status"><option value="PENDING" <?= $status === 'PENDING' ? 'selected' : '' ?>>Pending</option><option value="ACTIVE" <?= $status === 'ACTIVE' ? 'selected' : '' ?>>Approved</option><option value="REJECTED" <?= $status === 'REJECTED' ? 'selected' : '' ?>>Rejected</option></select></label>
            <button class="btn btn-secondary" type="submit"><i class="fas fa-filter"></i> Filter</button>
            <a class="btn btn-quiet" href="app.php?page=registration_approval">Clear</a>
        </form>
        <div class="table-container"><table class="management-table"><thead><tr><th>ID NO.</th><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Registration Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
        <?php if (!$registrations): ?><tr><td colspan="8" class="empty-state">No registrations found.</td></tr><?php endif; ?>
        <?php foreach ($registrations as $registration): ?><tr><td class="data-employee-id"><?= htmlspecialchars($registration['id_number']) ?></td><td class="data-name"><?= htmlspecialchars(approvalName($registration)) ?></td><td class="data-username"><?= htmlspecialchars($registration['username']) ?></td><td class="data-email"><?= htmlspecialchars($registration['email']) ?></td><td><span class="role-badge data-role-text">USER</span></td><td class="data-date"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($registration['created_at']))) ?></td><td><span class="status-badge <?= strtolower($registration['status']) ?> data-status-text"><?= htmlspecialchars($registration['status']) ?></span></td><td class="action-cell"><a data-record-view data-record-key="registration_approval:<?= (int)$registration['id'] ?>" aria-haspopup="dialog" class="btn btn-small btn-quiet" href="<?= htmlspecialchars(approvalLink(['view' => $registration['id']])) ?>"><i class="fas fa-eye"></i> View</a><?php if ($registration['status'] === 'PENDING'): ?><form method="post" onsubmit="return confirm('Approve this registration?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="account_id" value="<?= (int)$registration['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-small btn-approve" type="submit">Approve</button></form><form method="post" onsubmit="return confirm('Reject this registration?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="account_id" value="<?= (int)$registration['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-small btn-danger" type="submit">Reject</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Registration pages"><?php for ($index = 1; $index <= $totalPages; $index++): ?><a class="page-number <?= $index === $page ? 'active' : '' ?>" href="<?= htmlspecialchars(approvalLink(['page_no' => $index])) ?>"><?= $index ?></a><?php endfor; ?></nav><?php endif; ?>
    </section>
<?php if ($viewRegistration): ?>
<template data-record-details data-title="Registration Details" data-record-key="registration_approval:<?= (int)$viewRegistration['id'] ?>" data-record-id="<?= htmlspecialchars($viewRegistration['id_number']) ?>">
    <dl class="detail-grid">
        <div><dt>ID No.</dt><dd><?= htmlspecialchars($viewRegistration['id_number']) ?></dd></div>
        <div><dt>Full Name</dt><dd><?= htmlspecialchars(approvalName($viewRegistration)) ?></dd></div>
        <div><dt>Username</dt><dd><?= htmlspecialchars($viewRegistration['username']) ?></dd></div>
        <div><dt>Email</dt><dd><?= htmlspecialchars($viewRegistration['email']) ?></dd></div>
        <div><dt>Role</dt><dd><span class="role-badge"><?= htmlspecialchars($viewRegistration['role']) ?></span></dd></div>
        <div><dt>Registration Date</dt><dd><?= htmlspecialchars(date('Y-m-d H:i', strtotime($viewRegistration['created_at']))) ?></dd></div>
        <div><dt>Status</dt><dd><span class="status-badge <?= strtolower($viewRegistration['status']) ?>"><?= htmlspecialchars($viewRegistration['status']) ?></span></dd></div>
    </dl>
</template>
<?php endif; ?>
<?php require __DIR__ . '/record_details_modal.php'; ?>
</main>
</body>
</html>