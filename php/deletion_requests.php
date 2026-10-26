<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'authorization.php';
require_once __DIR__ . '/notifications_store.php';
require_once __DIR__ . '/superadmin_session.php';
require_once __DIR__ . '/profile_data.php';
require_once __DIR__ . '/deletion_history.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$role = $_SESSION['role'] ?? 'USER';
if (strtoupper((string)$role) === 'SUPER_ADMIN') enforceSuperAdminSession($pdo, $_SERVER['REQUEST_METHOD'] === 'POST');
if (!isset($_SESSION['user_id']) || !in_array($role, ['ADMIN', 'SUPER_ADMIN'], true)) {
    http_response_code(403);
    exit('Forbidden');
}

$isSuperAdminRole = $role === 'SUPER_ADMIN';
$isDeletionReviewer = $isSuperAdminRole && hasPermission($pdo, (int)$_SESSION['user_id'], 'APPROVE_DELETIONS');
$canSubmitDeletionRequest = !$isSuperAdminRole && hasPermission($pdo, (int)$_SESSION['user_id'], 'SUBMIT_DELETION_REQUESTS');
$isSuperAdmin = $isSuperAdminRole;
if (!$isSuperAdminRole && !$isDeletionReviewer && !$canSubmitDeletionRequest) {
    http_response_code(403);
    exit('Forbidden');
}
$csrfKey = 'deletion_requests_csrf';
if (empty($_SESSION[$csrfKey])) $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
$csrfToken = $_SESSION[$csrfKey];
$errors = [];
$message = $_GET['message'] ?? '';

function deletionName(array $account): string {
    return trim(implode(' ', array_filter([$account['first_name'] ?? '', $account['middle_name'] ?? '', $account['last_name'] ?? '', $account['extension_name'] ?? ''])));
}
function deletionAccount(PDO $pdo, int $id, bool $managementOnly = false, bool $lock = false): ?array {
    $stmt = $pdo->prepare("SELECT id, id_number, first_name, middle_name, last_name, extension_name, username, email, role, status, created_at FROM users WHERE id = ? AND (? = 0 OR role NOT IN ('ADMIN', 'SUPER_ADMIN') OR first_login_required = 0) LIMIT 1" . ($lock ? " FOR UPDATE" : ""));
    $stmt->execute([$id, (int)$managementOnly]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    return $account ?: null;
}
function deletionLog(PDO $pdo, string $action, array $target, string $reason = '', int $requestId = 0): void {
    $actorStmt = $pdo->prepare('SELECT id_number, username, first_name, middle_name, last_name, extension_name, role FROM users WHERE id = ? LIMIT 1');
    $actorStmt->execute([(int)$_SESSION['user_id']]);
    $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
    if (!$actor) throw new RuntimeException('The acting administrator could not be verified.');
    $actorName = deletionName($actor);
    $targetName = $target['full_name'] ?? deletionName($target);
    $details = ($requestId > 0 ? 'Deletion history #' . $requestId . '; Origin: Approved Request; ' : '') . 'Target: ' . $targetName . ' (' . ($target['employee_id'] ?? $target['id_number'] ?? '') . ')';
    if ($reason !== '') $details .= ' Reason: ' . $reason;
    $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, target_user_id, target_username, status, failure_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SUCCESS', ?)");
    $details = mb_strcut($details, 0, 255, 'UTF-8');
    $stmt->execute([(int)$_SESSION['user_id'], $actor['username'], $actorName, $actor['role'], $action, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $actor['id_number'], $target['id'] ?? $target['user_id'] ?? null, $target['username'] ?? $target['target_username'] ?? null, $details]);
}
function deletionRedirect(string $message): void {
    header('Location: app.php?page=deletion_requests&message=' . rawurlencode($message));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid request token');
    }
    $action = $_POST['action'] ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);

    if (in_array($action, ['approve', 'reject'], true) && !$isDeletionReviewer) {
        http_response_code(403);
        exit('Forbidden');
    }

    if ($action === 'submit' && $canSubmitDeletionRequest) {
        $target = deletionAccount($pdo, (int)($_POST['user_id'] ?? 0), true);
        $reason = is_string($_POST['reason'] ?? null) ? trim($_POST['reason']) : ''; 
        if (!$target || $target['role'] === 'SUPER_ADMIN' || (int)$target['id'] === (int)$_SESSION['user_id']) $errors[] = 'That account cannot be requested for deletion.';
        if ($reason === '' || strlen($reason) > 500) $errors[] = 'A deletion reason is required and must be 500 characters or fewer.';
        if (!$errors) {
            try {
                $pdo->beginTransaction();
                // Serialize submissions for the same target before checking pending requests.
                $target = deletionAccount($pdo, (int)$target['id'], true, true);
                if (!$target || $target['role'] === 'SUPER_ADMIN' || (int)$target['id'] === (int)$_SESSION['user_id']) {
                    throw new RuntimeException('That account cannot be requested for deletion.');
                }
                $existing = $pdo->prepare("SELECT id FROM deletion_requests WHERE user_id = ? AND status = 'PENDING' LIMIT 1 FOR UPDATE");
                $existing->execute([$target['id']]);
                if ($existing->fetchColumn()) throw new RuntimeException('This account already has a pending deletion request.');
                $stmt = $pdo->prepare("INSERT INTO deletion_requests (user_id, employee_id, full_name, username, email, account_role, requested_by, reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$target['id'], $target['id_number'], deletionName($target), $target['username'], $target['email'], $target['role'], (int)$_SESSION['user_id'], $reason]);
                $submittedRequestId = (int)$pdo->lastInsertId();
                deletionLog($pdo, 'DELETION_REQUEST_SUBMITTED', $target, $reason);
                notifyDeletionSubmitted($pdo, $submittedRequestId);
                $pdo->commit();
                deletionRedirect('Deletion request submitted for super administrator review.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = $e instanceof PDOException ? 'The request could not be submitted. Please try again.' : $e->getMessage();
            }
        }
    }

    if ($isDeletionReviewer && in_array($action, ['approve', 'reject'], true)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT * FROM deletion_requests WHERE id = ? AND status = \'PENDING\' FOR UPDATE');
            $stmt->execute([$requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            $target = $request && $request['user_id'] ? deletionAccount($pdo, (int)$request['user_id'], true, true) : null;
            if (!$request) throw new RuntimeException('This request has already been reviewed.');
            if ($action === 'approve') {
                $targetForLog = $target ?: ['id' => $request['user_id'], 'username' => $request['username'], 'employee_id' => $request['employee_id'], 'full_name' => $request['full_name']];
                if (!$target) throw new RuntimeException('The requested account no longer exists.');
                if ($target['role'] === 'SUPER_ADMIN' || (int)$target['id'] === (int)$_SESSION['user_id']) throw new RuntimeException('Super administrator accounts cannot be deleted.');
                deletionLog($pdo, 'ACCOUNT_DELETED', $target);
                $delete = $pdo->prepare('DELETE FROM users WHERE id = ? AND role <> \'SUPER_ADMIN\'');
                $delete->execute([$target['id']]);
                if ($delete->rowCount() !== 1) throw new RuntimeException('The requested account could not be deleted.');
                $newStatus = 'APPROVED';
                $success = 'Account deleted and request approved.';
            } else {
                $targetForLog = $target ?: ['id' => $request['user_id'], 'username' => $request['username'], 'employee_id' => $request['employee_id'], 'full_name' => $request['full_name']];
                deletionLog($pdo, 'DELETION_REQUEST_REJECTED', $targetForLog);
                $newStatus = 'REJECTED';
                $success = 'Deletion request rejected.';
            }
            $update = $pdo->prepare('UPDATE deletion_requests SET status = ?, reviewed_by = ?, reviewed_at = CURRENT_TIMESTAMP WHERE id = ?');
            $update->execute([$newStatus, (int)$_SESSION['user_id'], $requestId]);
            if ($update->rowCount() !== 1) throw new RuntimeException('The deletion request status could not be updated.');
            if ($action === 'approve') deletionLog($pdo, 'DELETION_REQUEST_APPROVED', $targetForLog, '', $requestId);
            notifyDeletionDecision($pdo, $requestId, (int)$request['requested_by'], $newStatus);
            $pdo->commit();
            deletionRedirect($success);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$viewId = (int)($_GET['view'] ?? 0);
$viewRequest = null;
if ($viewId > 0) {
    $viewSql = "SELECT dr.*, " . deletionOriginSql('dr') . " AS deletion_origin, r.username AS requester_username, CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.extension_name) AS requester_name,
        u.status AS live_status, u.created_at AS live_created_at, u.email AS live_email, u.role AS live_role,
        u.birthdate AS live_birthdate, u.age AS live_age, u.sex AS live_sex, u.purok AS live_purok,
        u.barangay AS live_barangay, u.city_municipality AS live_city, u.province AS live_province,
        u.country AS live_country, u.zip_code AS live_zip
        FROM deletion_requests dr
        LEFT JOIN users r ON r.id = dr.requested_by
        LEFT JOIN users u ON u.id = dr.user_id
        WHERE dr.id = ?";
    $viewStmt = $pdo->prepare($viewSql . ($isDeletionReviewer ? '' : ' AND dr.requested_by = ' . (int)$_SESSION['user_id']));
    $viewStmt->execute([$viewId]);
    $viewRequest = $viewStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$q = trim($_GET['q'] ?? '');
$statusFilter = strtoupper(trim($_GET['status'] ?? ''));
$pageNumber = max(1, (int)($_GET['p'] ?? 1));
$where = $isDeletionReviewer ? '1=1' : 'dr.requested_by = ' . (int)$_SESSION['user_id'];
$params = [];
if ($q !== '') {
    $where .= ' AND (dr.employee_id LIKE ? OR dr.full_name LIKE ? OR dr.username LIKE ? OR dr.account_role LIKE ?)';
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
}
if (in_array($statusFilter, ['PENDING', 'APPROVED', 'REJECTED'], true)) {
    $where .= ' AND dr.status = ?';
    $params[] = $statusFilter;
}
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM deletion_requests dr WHERE $where");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$perPage = 10;
$totalPages = max(1, (int)ceil($total / $perPage));
$pageNumber = min($pageNumber, $totalPages);
$listStmt = $pdo->prepare("SELECT dr.*, " . deletionOriginSql('dr') . " AS deletion_origin, CONCAT_WS(' ', r.first_name, r.middle_name, r.last_name, r.extension_name) AS requester_name FROM deletion_requests dr LEFT JOIN users r ON r.id = dr.requested_by WHERE $where ORDER BY dr.created_at DESC, dr.id DESC LIMIT $perPage OFFSET " . (($pageNumber - 1) * $perPage));
$listStmt->execute($params);
$requests = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$accounts = [];
if ($canSubmitDeletionRequest) {
    $accountStmt = $pdo->prepare("SELECT id, id_number, first_name, middle_name, last_name, extension_name, username, email, role, status FROM users WHERE id <> ? AND role <> 'SUPER_ADMIN' AND (role <> 'ADMIN' OR first_login_required = 0) ORDER BY last_name, first_name");
    $accountStmt->execute([(int)$_SESSION['user_id']]);
    $accounts = $accountStmt->fetchAll(PDO::FETCH_ASSOC);
}
function queryLink(array $changes = []): string { return 'app.php?' . http_build_query(array_merge($_GET, ['page' => 'deletion_requests'], $changes)); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletion Requests - FROSTLINE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/deletion_requests.css?v=<?= filemtime(__DIR__ . '/../css/deletion_requests.css') ?>">
    <script src="../js/deletion_requests.js?v=<?= filemtime(__DIR__ . '/../js/deletion_requests.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
</head>
<body class="superadmin-body">
<main class="management-page">
    <header class="management-header">
        <div><p class="eyebrow">ADMINISTRATION</p><h1>Deletion Requests</h1><p class="management-subtitle"><?= $isSuperAdmin ? 'Review and resolve account deletion requests.' : 'Submit account deletion requests and track their status.' ?></p></div>
    </header>
    <?php if ($message): ?><div class="management-alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php foreach ($errors as $error): ?><div class="management-alert error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>

    <?php if ($canSubmitDeletionRequest): ?>
    <form class="deletion-request-layout" method="post" id="deletion-request-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="submit">
        <section class="management-panel account-selection-panel">
            <div class="deletion-section-heading"><div><h2>1. Select an Account</h2><p>Search and choose the account you want to request for deletion.</p></div><label class="account-search"><span class="sr-only">Search eligible accounts</span><i class="fas fa-search" aria-hidden="true"></i><input type="search" id="account-search" placeholder="Search by ID No., name, or username"></label></div>
            <div class="table-container account-selection-table-wrap"><table class="management-table deletion-account-table" id="eligible-accounts-table"><thead><tr><th>Select</th><th>ID NO.</th><th>Full Name</th><th>Username</th><th>Role</th><th>Status</th></tr></thead><tbody><?php foreach ($accounts as $account): ?><?php $accountName = deletionName($account); ?><tr class="account-option" tabindex="0" data-account-search="<?= htmlspecialchars(strtolower($account['id_number'] . ' ' . $accountName . ' ' . $account['username'] . ' ' . $account['email'])) ?>" data-account-name="<?= htmlspecialchars($accountName) ?>" data-account-username="<?= htmlspecialchars($account['username']) ?>" data-account-employee-id="<?= htmlspecialchars($account['id_number']) ?>" data-account-role="<?= htmlspecialchars($account['role']) ?>" data-account-email="<?= htmlspecialchars($account['email']) ?>" data-account-status="<?= htmlspecialchars($account['status']) ?>"><td><input type="radio" name="user_id" value="<?= (int)$account['id'] ?>" aria-label="Select <?= htmlspecialchars($accountName) ?>"></td><td class="data-employee-id"><?= htmlspecialchars($account['id_number']) ?></td><td class="data-name"><?= htmlspecialchars($accountName) ?></td><td class="data-username"><?= htmlspecialchars($account['username']) ?></td><td><span class="sl-badge sl-badge-role"><?= htmlspecialchars($account['role']) ?></span></td><td><span class="sl-badge sl-badge-active"><span class="sl-dot sl-dot-active"></span><?= htmlspecialchars($account['status']) ?></span></td></tr><?php endforeach; ?></tbody></table><p class="account-empty-state" id="account-empty-state" hidden>No eligible accounts match your search.</p></div>
            <div class="account-table-footer"><span id="account-page-summary"></span><nav class="account-pagination" id="account-pagination" aria-label="Eligible account pages"></nav></div>
        </section>
        <section class="management-panel reason-panel"><div class="deletion-section-heading"><div><h2>2. Provide Reason</h2><p>Explain why this account should be submitted for review.</p></div></div><div class="selected-account-summary"><p class="summary-label">Selected Account</p><div class="summary-empty" id="summary-empty"><i class="fas fa-user-circle" aria-hidden="true"></i><strong>No account selected.</strong><span>Select an account from the list to continue.</span></div><div class="summary-details" id="summary-details" hidden><div class="summary-avatar"><i class="fas fa-user" aria-hidden="true"></i></div><div><strong id="summary-name"></strong><dl><div><dt>Username</dt><dd id="summary-username"></dd></div><div><dt>ID No.</dt><dd id="summary-employee-id"></dd></div><div><dt>Role</dt><dd id="summary-role"></dd></div><div><dt>Email</dt><dd id="summary-email"></dd></div><div><dt>Status</dt><dd id="summary-status"></dd></div></dl></div></div></div><label class="reason-field">Reason for deletion <span>*</span><textarea required name="reason" maxlength="500" placeholder="Explain why this account should be deleted..."></textarea></label><div class="deletion-info"><i class="fas fa-circle-info" aria-hidden="true"></i><span>This will submit a deletion request for administrative review.<br>The account will not be deleted immediately.</span></div><button class="btn btn-primary submit-request-button" type="submit"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit Request</button></section>
    </form>
    <?php endif; ?>

    <section class="management-panel"><div class="deletion-section-heading recent-requests-heading"><div><h2>Recent Deletion Requests</h2><p><?= $isSuperAdmin ? 'Review submitted requests and recorded direct deletions.' : 'Track the status of your submitted deletion requests.' ?></p></div></div><form class="filter-bar" method="get" action="app.php"><input type="hidden" name="page" value="deletion_requests"><label class="deletion-filter-search"><span>Search</span><i class="fas fa-search" aria-hidden="true"></i><input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by ID No., name, or username"></label><label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach (['PENDING', 'APPROVED', 'REJECTED'] as $option): ?><option value="<?= $option ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= $option ?></option><?php endforeach; ?></select></label><button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button><a class="btn btn-quiet" href="app.php?page=deletion_requests">Clear</a></form>
        <div class="table-container"><table class="management-table deletion-table"><thead><tr><th>Request ID</th><th>ID NO.</th><th>Full Name</th><th>Username</th><th>Role</th><th>Requested By</th><th>Request Date</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php if (!$requests): ?><tr><td colspan="10" class="empty-state">No deletion requests found.</td></tr><?php endif; ?><?php foreach ($requests as $request): ?><tr><td class="data-id">#<?= (int)$request['id'] ?></td><td class="data-employee-id"><?= htmlspecialchars($request['employee_id']) ?></td><td class="data-name"><?= htmlspecialchars($request['full_name']) ?></td><td class="data-username"><?= htmlspecialchars($request['username']) ?></td><td><span class="sl-badge sl-badge-role"><?= htmlspecialchars($request['account_role']) ?></span></td><td><?= htmlspecialchars($request['requester_name'] ?: 'Former account') ?></td><td class="data-date"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($request['created_at']))) ?></td><td class="reason-cell" title="<?= htmlspecialchars($request['reason']) ?>"><?= htmlspecialchars($request['reason']) ?></td><td><span class="sl-badge <?= strtolower($request['status']) === 'approved' ? 'sl-badge-success' : (strtolower($request['status']) === 'rejected' ? 'sl-badge-failed' : 'sl-badge-blocked') ?>"><span class="sl-dot <?= strtolower($request['status']) === 'approved' ? 'sl-dot-success' : (strtolower($request['status']) === 'rejected' ? 'sl-dot-failed' : 'sl-dot-blocked') ?>"></span><?= htmlspecialchars($request['status']) ?></span></td><td class="action-cell"><a data-record-view data-record-key="deletion_requests:<?= (int)$request['id'] ?>" aria-haspopup="dialog" href="<?= htmlspecialchars(queryLink(['view' => $request['id']])) ?>"><i class="fas fa-eye" aria-hidden="true"></i> View</a><?php if ($isSuperAdmin && $request['status'] === 'PENDING'): ?><form method="post" onsubmit="return confirm('Reject this deletion request?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>"><input type="hidden" name="action" value="reject"><button type="submit"><i class="fas fa-ban" aria-hidden="true"></i> Reject</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
        <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="Deletion request pages"><?php if ($pageNumber > 1): ?><a aria-label="Previous deletion request page" href="<?= htmlspecialchars(queryLink(['p' => $pageNumber - 1])) ?>">Previous</a><?php endif; ?><?php for ($page = 1; $page <= $totalPages; $page++): ?><a class="<?= $page === $pageNumber ? 'current' : '' ?>" href="<?= htmlspecialchars(queryLink(['p' => $page])) ?>"><?= $page ?></a><?php endfor; ?><?php if ($pageNumber < $totalPages): ?><a aria-label="Next deletion request page" href="<?= htmlspecialchars(queryLink(['p' => $pageNumber + 1])) ?>">Next</a><?php endif; ?></nav><?php endif; ?>
    </section>

    <?php if ($viewRequest): ?><template data-record-details data-title="Deletion Request Details" data-record-key="deletion_requests:<?= (int)$viewRequest['id'] ?>" data-record-id="<?= htmlspecialchars('REQUEST #' . $viewRequest['id']) ?>">
<p class="record-details-name"><?= htmlspecialchars($viewRequest['full_name']) ?></p>
<dl class="detail-grid"><div><dt>ID No.</dt><dd><?= htmlspecialchars($viewRequest['employee_id']) ?></dd></div><div><dt>Full Name</dt><dd><?= htmlspecialchars($viewRequest['full_name']) ?></dd></div><div><dt>Username</dt><dd><?= htmlspecialchars($viewRequest['username']) ?></dd></div><div><dt>Email</dt><dd><?= htmlspecialchars($viewRequest['live_email'] ?: $viewRequest['email']) ?></dd></div><div><dt>Role</dt><dd><span class="role-badge"><?= htmlspecialchars($viewRequest['live_role'] ?: $viewRequest['account_role']) ?></span></dd></div><div><dt>Account Status</dt><dd><?= htmlspecialchars($viewRequest['live_status'] ?: 'Unavailable (account no longer exists)') ?></dd></div><div><dt>Account Created</dt><dd><?= htmlspecialchars(deletionAccountCreated($viewRequest['live_created_at'])) ?></dd></div><div><dt>Record Type</dt><dd><?= htmlspecialchars(deletionHistoryLabel($viewRequest)) ?></dd></div><div><dt>Requested By</dt><dd><?= htmlspecialchars($viewRequest['requester_name'] ?: 'Former account') ?></dd></div><div><dt>Request Date</dt><dd><?= htmlspecialchars(date('Y-m-d H:i', strtotime($viewRequest['created_at']))) ?></dd></div><div class="detail-wide"><dt>Reason for Deletion</dt><dd><?= nl2br(htmlspecialchars($viewRequest['reason'])) ?></dd></div></dl><?php if ($isSuperAdmin && $viewRequest['status'] === 'PENDING'): ?><div class="review-actions"><form method="post" onsubmit="return confirm('Confirm Account Deletion\n\nYou are about to permanently delete <?= htmlspecialchars(addslashes($viewRequest['full_name'])) ?> (<?= htmlspecialchars(addslashes($viewRequest['employee_id'])) ?>). This action cannot be undone.')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="request_id" value="<?= (int)$viewRequest['id'] ?>"><input type="hidden" name="action" value="approve"><button class="btn btn-danger" type="submit"><i class="fas fa-trash"></i> Approve Deletion</button></form><form method="post" onsubmit="return confirm('Reject this deletion request?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>"><input type="hidden" name="request_id" value="<?= (int)$viewRequest['id'] ?>"><input type="hidden" name="action" value="reject"><button class="btn btn-secondary" type="submit"><i class="fas fa-times"></i> Reject Request</button></form></div><?php endif; ?>
<h2>Profile Information Available for Review</h2>
        <dl class="detail-grid">
            <div><dt>Birthdate</dt><dd><?= htmlspecialchars($viewRequest['live_birthdate'] ?: '—') ?></dd></div>
            <div><dt>Age</dt><dd><?= htmlspecialchars(profileAge($viewRequest['live_birthdate']) ?? 'Unavailable') ?></dd></div>
            <div><dt>Sex</dt><dd><?= htmlspecialchars($viewRequest['live_sex'] ?: '—') ?></dd></div>
            <div><dt>Purok / Address</dt><dd><?= htmlspecialchars($viewRequest['live_purok'] ?: '—') ?></dd></div>
            <div><dt>Barangay</dt><dd><?= htmlspecialchars($viewRequest['live_barangay'] ?: '—') ?></dd></div>
            <div><dt>City / Municipality</dt><dd><?= htmlspecialchars($viewRequest['live_city'] ?: '—') ?></dd></div>
            <div><dt>Province</dt><dd><?= htmlspecialchars($viewRequest['live_province'] ?: '—') ?></dd></div>
            <div><dt>Country</dt><dd><?= htmlspecialchars($viewRequest['live_country'] ?: '—') ?></dd></div>
            <div><dt>ZIP Code</dt><dd><?= htmlspecialchars($viewRequest['live_zip'] ?: '—') ?></dd></div>
        </dl>
    </template><?php endif; ?>

<?php require __DIR__ . '/record_details_modal.php'; ?>
</main>
</body>
</html>
