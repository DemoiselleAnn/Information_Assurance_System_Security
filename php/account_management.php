<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/demotion_request.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/account_status.php';
require_once __DIR__ . '/superadmin_session.php';

if (isSuperAdminDemotionRequest()) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_POST['action'] ?? null) !== 'demote_super_admin') {
        demotionRequestError('invalid_request', 'This action requires a valid POST request.', 405);
    }
    if (empty($_SESSION['user_id'])) {
        demotionRequestError('unauthenticated_session', 'Your session has expired. Please log in again.', 401);
    }
    if (($_SESSION['role'] ?? '') !== 'SUPER_ADMIN') {
        demotionRequestError('invalid_actor', 'Only an authenticated Super Admin can perform this action.', 403);
    }
    try {
        $actorCheck = $pdo->prepare('SELECT role, status, first_login_required FROM users WHERE id = ?');
        $actorCheck->execute([(int)$_SESSION['user_id']]);
        $verifiedActor = $actorCheck->fetch(PDO::FETCH_ASSOC);
        if (!$verifiedActor || $verifiedActor['role'] !== 'SUPER_ADMIN' || $verifiedActor['status'] === 'BLOCKED' || $verifiedActor['first_login_required']) {
            demotionRequestError('invalid_actor', 'The acting Super Admin could not be verified. Please log in again.', 403);
        }
        $demotionSessionState = superAdminSessionState($pdo);
        if ($demotionSessionState['status'] !== 'active') {
            if (in_array($demotionSessionState['status'], ['missing', 'expired'], true)) {
                superAdminEndSession($pdo, 'Your session has expired. Please log in again.');
            }
            demotionRequestError('unauthenticated_session', 'Your session has expired. Please log in again.', 401);
        }
    } catch (PDOException $e) {
        demotionRequestError('database_error', 'The demotion could not be completed. Please try again.', 500);
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id']) || !in_array(($_SESSION['role'] ?? 'USER'), ['ADMIN', 'SUPER_ADMIN'], true)) {
    http_response_code(403);
    exit('Forbidden');
}
require_once __DIR__ . '/recovery_mail.php';
require_once __DIR__ . '/temporary_credentials.php';
requirePermission($pdo, 'MANAGE_USERS');

function canDirectDeleteAccount(array $account, int $actorId, string $actorRole): bool {
    if ($actorRole !== 'SUPER_ADMIN') {
        return false;
    }
    if (!$account) {
        return false;
    }
    if ((int)$account['id'] === $actorId) {
        return false;
    }
    if ($account['role'] === 'SUPER_ADMIN') {
        return false;
    }
    return in_array($account['role'], ['USER', 'ADMIN'], true);
}

function redirectToAccountManagement(array $params = []): void {
    $query = http_build_query(array_merge(['page' => 'account_management'], $params));
    header('Location: app.php?' . $query);
    exit();
}

function accountStatusClass(string $status): string {
    return strtolower($status);
}

function formatDateTime(?string $value): string {
    if (empty($value) || $value === '0000-00-00 00:00:00') {
        return '—';
    }
    return date('Y-m-d H:i', strtotime($value));
}

$errors = [];
$message = '';
$viewAccount = null;
$editAccount = null;
$superAdminLimitMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isSuperAdminDemotionRequest() && (!is_string($_SESSION['account_management_csrf'] ?? null)
        || $_SESSION['account_management_csrf'] === '' || !is_string($_POST['csrf_token'] ?? null))) {
        demotionRequestError('invalid_csrf', 'Request verification failed. Refresh the page and try again.', 419);
    }
    if (!hash_equals($_SESSION['account_management_csrf'] ?? '', $_POST['csrf_token'] ?? '')) {
        if (isSuperAdminDemotionRequest()) demotionRequestError('invalid_csrf', 'Request verification failed. Refresh the page and try again.', 419);
        http_response_code(403);
        exit('Invalid request token');
    }

    $action = $_POST['action'] ?? '';
    if (!in_array($action, ['demote_super_admin', 'delete_account', 'block', 'update'], true)) {
        http_response_code(400);
        exit('Unsupported account action');
    }
    $targetId = is_scalar($_POST['account_id'] ?? null) ? (int)$_POST['account_id'] : 0;
    $target = $targetId > 0 && $action !== 'demote_super_admin' ? getAccount($pdo, $targetId, true) : null;

    if ($action === 'demote_super_admin') {
        if ($targetId <= 0) demotionRequestError('missing_target', 'The selected account could not be found.', 422);
        $demotionJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
        try {
            $pdo->exec('SET SESSION innodb_lock_wait_timeout = 10');
            demoteOtherSuperAdmin($pdo, $targetId, $_POST['current_password'] ?? '');
            if ($demotionJson) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => true, 'redirect' => 'app.php?page=account_management&message=' . rawurlencode('Super Admin demoted to Admin. Previous sessions revoked and Admin permissions applied.')]);
                exit;
            }
            redirectToAccountManagement(['message' => 'Super Admin demoted to Admin. Previous sessions revoked and Admin permissions applied.']);
        } catch (Throwable $e) {
            if ($e instanceof DemotionValidationException) demotionRequestError($e->reason, $e->getMessage(), $e->httpStatus);
            demotionRequestError('database_error', 'The demotion could not be completed. Please try again.', 500);
        }
    }

    if ($action === 'delete_account') {
        if (($_SESSION['role'] ?? 'USER') !== 'SUPER_ADMIN') {
            http_response_code(403);
            exit('Forbidden');
        }

        if (!$target) {
            redirectToAccountManagement(['error' => 'This account could not be found.']);
        }
        if (!canDirectDeleteAccount($target, (int)$_SESSION['user_id'], (string)($_SESSION['role'] ?? 'USER'))) {
            redirectToAccountManagement(['error' => 'This account cannot be deleted directly.']);
        }

        $reason = trim((string)($_POST['deletion_reason'] ?? ''));
        if ($reason === '') {
            redirectToAccountManagement(['error' => 'A deletion reason is required.']);
        }
        if (strlen($reason) > 500) {
            redirectToAccountManagement(['error' => 'Deletion reason must be 500 characters or fewer.']);
        }

        try {
            $pdo->beginTransaction();
            $lockedTarget = $pdo->prepare('SELECT id, id_number, first_name, middle_name, last_name, extension_name, username, email, role, status, created_at FROM users WHERE id = ? AND (role NOT IN (\'ADMIN\', \'SUPER_ADMIN\') OR first_login_required = 0) FOR UPDATE');
            $lockedTarget->execute([$targetId]);
            $deleteTarget = $lockedTarget->fetch(PDO::FETCH_ASSOC);

            if (!$deleteTarget || !canDirectDeleteAccount($deleteTarget, (int)$_SESSION['user_id'], (string)($_SESSION['role'] ?? 'USER'))) {
                throw new RuntimeException('This account cannot be deleted directly.');
            }

            $actorStmt = $pdo->prepare('SELECT id_number, username, first_name, middle_name, last_name, extension_name, role FROM users WHERE id = ? LIMIT 1');
            $actorStmt->execute([(int)$_SESSION['user_id']]);
            $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
            if (!$actor) {
                throw new RuntimeException('The acting administrator could not be verified.');
            }

            $actorName = securityLogFullName($actor);
            $targetName = securityLogFullName($deleteTarget);
            $actorIdNumber = $actor['id_number'] ?? '';
            $details = 'Target: ' . $targetName . ' (' . $deleteTarget['id_number'] . ') Reason: ' . $reason;

            $snapshotStmt = $pdo->prepare(
                'INSERT INTO deletion_requests (user_id, employee_id, full_name, username, email, account_role, requested_by, reason, status, reviewed_by, reviewed_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
            );
            $snapshotStmt->execute([
                (int)$deleteTarget['id'],
                $deleteTarget['id_number'],
                $targetName,
                $deleteTarget['username'],
                $deleteTarget['email'],
                $deleteTarget['role'],
                (int)$_SESSION['user_id'],
                $reason,
                'APPROVED',
                (int)$_SESSION['user_id'],
            ]);

            $historyId = (int)$pdo->lastInsertId();
            $details = mb_strcut('Deletion history #' . $historyId . '; Origin: Direct Deletion; ' . $details, 0, 255, 'UTF-8');

            $logStmt = $pdo->prepare(
                "INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, target_user_id, target_username, status, failure_reason)
                 VALUES (?, ?, ?, ?, 'ACCOUNT_DELETED', ?, ?, ?, ?, ?, 'SUCCESS', ?)"
            );
            $logStmt->execute([
                (int)$_SESSION['user_id'],
                $actor['username'],
                $actorName,
                $actor['role'],
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $actorIdNumber,
                (int)$deleteTarget['id'],
                $deleteTarget['username'],
                $details,
            ]);

            $deleteStmt = $pdo->prepare('DELETE FROM users WHERE id = ? AND role IN (\'USER\', \'ADMIN\') AND id <> ?');
            $deleteStmt->execute([(int)$deleteTarget['id'], (int)$_SESSION['user_id']]);
            if ($deleteStmt->rowCount() !== 1) {
                throw new RuntimeException('The account could not be deleted.');
            }

            $pdo->commit();

            $mailBody = "Your FROSTLINE account has been deleted.\n\nAdministrative reason: {$reason}\n\nIf you believe this was a mistake, please contact support.";
            if (!sendRecoveryEmail($deleteTarget['email'], 'FROSTLINE account deleted', $mailBody, (int)$deleteTarget['id'], (string)$deleteTarget['role'])) {
                error_log(sprintf('Direct account deletion email failed: account_id=%d role=%s', (int)$deleteTarget['id'], (string)$deleteTarget['role']));
            }

            redirectToAccountManagement(['message' => 'Account deleted successfully.']);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            redirectToAccountManagement(['error' => $e->getMessage()]);
        }
    }

    if ($action === 'block') {
        requirePermission($pdo, 'BLOCK_ACCOUNTS');
        [$updated, $statusMessage] = updateAccountStatus($pdo, $targetId, 'BLOCKED');
        if (!$updated) {
            redirectToAccountManagement(['error' => $statusMessage]);
        }
        redirectToAccountManagement(['message' => $statusMessage]);
    }

    if ($action === 'update') {
        if (!$target || $target['role'] === 'SUPER_ADMIN' || (int)$target['id'] === (int)$_SESSION['user_id']) {
            redirectToAccountManagement(['error' => 'This account cannot be edited from this page.']);
        }

        $idNumber = $target['id_number']; // IDs are immutable; ignore submitted values.
        $firstName = trim($_POST['first_name'] ?? '');
        $middleName = trim($_POST['middle_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = strtoupper(trim($_POST['role'] ?? 'USER'));

        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First name and last name are required.';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z -]{0,49}$/', $firstName) || ($middleName !== '' && !preg_match('/^[A-Za-z][A-Za-z -]{0,49}$/', $middleName)) || !preg_match('/^[A-Za-z][A-Za-z -]{0,49}$/', $lastName)) {
            $errors[] = 'Names may contain letters, spaces, and hyphens only.';
        }
        $validUsername = $role === 'USER'
            ? (bool)preg_match('/^[a-z]{5,}\.[a-z]{2,}$/', $username) && strlen($username) <= 40
            : (bool)preg_match('/^[A-Za-z0-9]{5,40}$/', $username);
        if (!$validUsername) {
            $errors[] = $role === 'USER'
                ? 'Usernames must use lowercase name.lastname format.'
                : 'Username must be 5-40 alphanumeric characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($role, ['USER', 'ADMIN', 'SUPER_ADMIN'], true)) {
            $errors[] = 'Only User, Administrator, and Super Administrator roles can be assigned here.';
        }
        if (($_SESSION['role'] ?? 'USER') !== 'SUPER_ADMIN' && $role !== $target['role']) {
            $errors[] = 'Only a Super Administrator can change account roles.';
        }

        $duplicateStmt = $pdo->prepare('SELECT id, id_number, username, email FROM users WHERE (username = ? OR email = ?) AND id <> ? LIMIT 1');
        $duplicateStmt->execute([$username, $email, $targetId]);
        $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);
        if ($duplicate) {
            if ($duplicate['username'] === $username) $errors[] = 'Username already exists.';
            if ($duplicate['email'] === $email) $errors[] = 'Email already exists.';
        }

        if (!$errors) {
            try {
                $pdo->beginTransaction();
                if ($role === 'SUPER_ADMIN' && $target['role'] !== 'SUPER_ADMIN') {
                    requireSuperAdminSlot($pdo);
                }
                // Preserve the current database status, including concurrent presence changes.
                // Promotion to Super Administrator retains its existing INACTIVE rule.
                $updateStmt = $pdo->prepare('UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, username = ?, email = ?, role = ?, status = CASE WHEN ? = \'SUPER_ADMIN\' THEN \'INACTIVE\' ELSE status END, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role IN (\'USER\', \'ADMIN\')');
                $updateStmt->execute([$firstName, $middleName ?: null, $lastName, $username, $email, $role, $role, $targetId]);
                if ($role === 'USER') {
                    $clearPermissions = $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?');
                    $clearPermissions->execute([$targetId]);
                }
                $updated = getAccount($pdo, $targetId, true);
                if (!$updated || $updated['role'] !== $role || $updated['username'] !== $username) {
                    throw new RuntimeException('Account could not be updated.');
                }
                $pdo->commit();
                logAccountEvent($pdo, 'ACCOUNT_UPDATED', $updated);
                redirectToAccountManagement(['message' => 'Account updated successfully.']);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                if ($e instanceof SuperAdminLimitReached) {
                    $superAdminLimitMessage = 'Two usable Super Administrator accounts already exist. Block one of the existing Super Administrators before promoting another account.';
                } else {
                    $errors[] = 'Account could not be updated. Please try again.';
                }
            }
        }

        $editAccount = array_merge($target, [
            'id' => $targetId,
            'id_number' => $idNumber,
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'username' => $username,
            'email' => $email,
            'role' => $role,
        ]);
    }
}

if (isset($_GET['view'])) {
    $viewAccount = getAccount($pdo, (int)$_GET['view'], true);
    if (!$viewAccount) {
        $errors[] = 'Account not found.';
    } else {
        logAccountEvent($pdo, 'ACCOUNT_VIEWED', $viewAccount);
    }
}

if (isset($_GET['edit'])) {
    $editAccount = getAccount($pdo, (int)$_GET['edit'], true);
    if (!$editAccount || $editAccount['role'] === 'SUPER_ADMIN' || (int)$editAccount['id'] === (int)$_SESSION['user_id']) {
        $errors[] = 'This account cannot be edited from this page.';
        $editAccount = null;
    }
}

// Setup completion banner (set by process_first_login_setup.php)
$setupBanner = $_SESSION['setup_completed_banner'] ?? '';
unset($_SESSION['setup_completed_banner']);

if (!empty($_GET['message'])) {
    $message = $_GET['message'];
}
if (!empty($_GET['error'])) {
    $errors[] = $_GET['error'];
}
$limitAction = $_GET['super_admin_limit'] ?? '';
if ($limitAction === 'promotion') {
    $superAdminLimitMessage = 'Two usable Super Administrator accounts already exist. Block one of the existing Super Administrators before promoting another account.';
}

$q = trim($_GET['q'] ?? '');
$roleFilter = strtoupper(trim($_GET['role'] ?? ''));
$statusFilter = strtoupper(trim($_GET['status'] ?? ''));

if (!in_array($roleFilter, ['', 'SUPER_ADMIN', 'ADMIN', 'USER'], true)) {
    $roleFilter = '';
}
if (!in_array($statusFilter, ['', 'ACTIVE', 'INACTIVE', 'PENDING', 'BLOCKED', 'REJECTED'], true)) {
    $statusFilter = '';
}

$sql = "SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name, u.username, u.email, u.role, u.status,
               u.first_login_required, u.temporary_credential_expires_at, u.temporary_credential_used_at, u.created_at,
               (SELECT MAX(sl.created_at)
                FROM security_logs sl
                WHERE sl.user_id = u.id AND sl.action = 'LOGIN' AND sl.status = 'SUCCESS') AS last_login
        FROM users u
                WHERE u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN')
                    AND (u.role = 'USER' OR u.first_login_required = 0)";
$params = [];

if ($q !== '') {
    $sql .= ' AND (u.id_number LIKE ? OR u.first_name LIKE ? OR u.middle_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)';
    $term = '%' . $q . '%';
    $params = [$term, $term, $term, $term, $term, $term];
}
if ($roleFilter !== '') {
    $sql .= ' AND u.role = ?';
    $params[] = $roleFilter;
}
if ($statusFilter !== '') {
    $sql .= ' AND u.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY u.created_at DESC, u.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalAccounts = count($accounts);
$perPage = 10;
$page = max(1, (int)($_GET['page_no'] ?? 1));
$totalPages = max(1, (int)ceil($totalAccounts / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;
$paginatedAccounts = array_slice($accounts, $offset, $perPage);

$csrfToken = accountCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Management - FROSTLINE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
    <script src="../js/superadmin_demotion.js?v=<?= filemtime(__DIR__ . '/../js/superadmin_demotion.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
</head>
<body class="superadmin-body">
    <main class="management-page">
        <header class="management-header">
            <div>
                <p class="eyebrow">ACCOUNT SECURITY</p>
                <h1>Account Management</h1>
                <p class="management-subtitle">Manage user and administrator accounts, access status, and account information.</p>
            </div>
        </header>

        <?php if ($setupBanner): ?><div class="management-alert success" style="background:#e6ffe6;border-color:#7dd67d;color:#1e7e34;font-weight:600"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($setupBanner) ?></div><?php endif; ?>
        <?php if ($message): ?><div class="management-alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php foreach ($errors as $error): ?><div class="management-alert error"><?= htmlspecialchars($error) ?></div><?php endforeach; ?>
        <?php if ($superAdminLimitMessage !== ''): ?>
            <dialog open class="management-dialog" aria-labelledby="super-admin-limit-title">
                <form method="dialog">
                    <button class="management-dialog-close" value="close" aria-label="Close dialog">&times;</button>
                    <h2 id="super-admin-limit-title">Super Administrator Limit Reached</h2>
                    <p><?= htmlspecialchars($superAdminLimitMessage) ?></p>
                    <button class="btn btn-primary" value="close">OK</button>
                </form>
            </dialog>
        <?php endif; ?>

        <section class="management-panel">
            <form class="filter-bar" method="get" action="app.php">
                <input type="hidden" name="page" value="account_management">
                <label>
                    Search
                    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search by ID No., name, or username">
                </label>
                <label>
                    Role
                    <select name="role">
                        <option value="">All Roles</option>
                        <option value="SUPER_ADMIN" <?= $roleFilter === 'SUPER_ADMIN' ? 'selected' : '' ?>>Super Administrator</option>
                        <option value="ADMIN" <?= $roleFilter === 'ADMIN' ? 'selected' : '' ?>>Administrator</option>
                        <option value="USER" <?= $roleFilter === 'USER' ? 'selected' : '' ?>>User</option>
                    </select>
                </label>
                <label>
                    Status
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="ACTIVE" <?= $statusFilter === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                        <option value="INACTIVE" <?= $statusFilter === 'INACTIVE' ? 'selected' : '' ?>>Inactive</option>
                        <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>Pending</option>
                        <option value="BLOCKED" <?= $statusFilter === 'BLOCKED' ? 'selected' : '' ?>>Blocked</option>
                    </select>
                </label>
                <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
                <a class="btn btn-quiet" href="app.php?page=account_management">Clear</a>
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
                            <th>Setup</th>
                            <th>Date Created</th>
                            <th>Last Login</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$paginatedAccounts): ?>
                            <tr>
                                <td colspan="10" class="empty-state">
                                    <div class="empty-title">No accounts found.</div>
                                    <div class="empty-subtitle">Try changing your search or filter criteria.</div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($paginatedAccounts as $account): ?>
                                <?php
                                $setupRequired = array_key_exists('first_login_required', $account)
                                    ? (int)$account['first_login_required']
                                    : null;
                                $setupExpired = $setupRequired === 1
                                    && !empty($account['temporary_credential_expires_at'])
                                    && temporaryCredentialIsExpired($account['temporary_credential_expires_at']);
                                $setupLabel = $setupRequired === null
                                    ? 'Setup Unavailable'
                                    : ($setupRequired === 1 ? ($setupExpired ? 'Credential Expired' : 'Incomplete') : 'Complete');
                                $setupClass = $setupRequired === null
                                    ? 'pending'
                                    : ($setupRequired === 1 ? ($setupExpired ? 'rejected' : 'pending') : 'active');
                                ?>
                                <tr data-account-id="<?= (int)$account['id'] ?>">
                                    <td class="data-id"><?= htmlspecialchars($account['id_number']) ?></td>
                                    <td class="data-name"><?= htmlspecialchars(trim(implode(' ', array_filter([$account['first_name'], $account['middle_name'], $account['last_name']]))) ?: '—') ?></td>
                                    <td class="data-username"><?= htmlspecialchars($account['username']) ?></td>
                                    <td class="data-email"><?= htmlspecialchars($account['email']) ?></td>
                                    <td><span class="role-badge data-role-text"><?= htmlspecialchars($account['role']) ?></span></td>
                                    <td><span class="status-badge <?= accountStatusClass((string)$account['status']) ?> data-status-text"><?= htmlspecialchars($account['status']) ?></span></td>
                                    <td><span class="status-badge <?= $setupClass ?>"><?= htmlspecialchars($setupLabel) ?></span></td>
                                    <td class="data-date"><?= htmlspecialchars(formatDateTime($account['created_at'])) ?></td>
                                    <td class="data-date"><?= htmlspecialchars(formatDateTime($account['last_login'])) ?></td>
                                    <td class="action-cell">
                                        <a data-record-view data-record-key="account_management:<?= (int)$account['id'] ?>" aria-haspopup="dialog" class="btn btn-small btn-quiet" href="app.php?page=account_management&view=<?= (int)$account['id'] ?>"><i class="fas fa-eye"></i> View</a>
                                        <?php if ((int)$account['id'] !== (int)$_SESSION['user_id'] && $account['role'] !== 'SUPER_ADMIN'): ?>
                                            <a class="btn btn-small btn-secondary" href="app.php?page=account_management&edit=<?= (int)$account['id'] ?>"><i class="fas fa-pen"></i> Edit</a>
                                            <?php if ($account['status'] !== 'BLOCKED'): ?>
                                                <form method="post" onsubmit="return confirm('Block this account?\n\nThis account will no longer be able to access the system until it is unblocked.');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                    <input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
                                                    <input type="hidden" name="action" value="block">
                                                    <button class="btn btn-small btn-danger" type="submit"><i class="fas fa-lock"></i> Block</button>
                                                </form>
                                            <?php endif; ?>
                                                <?php if (($_SESSION['role'] ?? 'USER') === 'SUPER_ADMIN' && in_array($account['role'], ['USER', 'ADMIN'], true)): ?>
                                                    <button type="button" class="btn btn-small btn-danger delete-account-trigger" data-account-id="<?= (int)$account['id'] ?>" data-id-number="<?= htmlspecialchars($account['id_number']) ?>" data-full-name="<?= htmlspecialchars(trim(implode(' ', array_filter([$account['first_name'], $account['middle_name'], $account['last_name']]))) ?: $account['username']) ?>" data-username="<?= htmlspecialchars($account['username']) ?>" data-role="<?= htmlspecialchars($account['role']) ?>" data-email="<?= htmlspecialchars($account['email']) ?>"><i class="fas fa-trash"></i> Delete</button>
                                                <?php endif; ?>
                                        <?php else: ?>
                                            <span class="locked-tag">Restricted</span>
                                        <?php endif; ?>
                                        <?php if (($_SESSION['role'] ?? '') === 'SUPER_ADMIN' && $account['role'] === 'SUPER_ADMIN' && (int)$account['id'] !== (int)$_SESSION['user_id']): ?>
                                            <?php $demotionAccount = $account; require __DIR__ . '/superadmin_demotion_modal.php'; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalAccounts > $perPage): ?>
                <div class="pagination" aria-label="Account pagination">
                    <?php
                    $baseQuery = http_build_query([
                        'page' => 'account_management',
                        'q' => $q,
                        'role' => $roleFilter,
                        'status' => $statusFilter,
                    ]);
                    $prevPage = max(1, $page - 1);
                    $nextPage = min($totalPages, $page + 1);
                    ?>
                    <a class="btn btn-quiet <?= $page <= 1 ? 'disabled' : '' ?>" href="app.php?<?= $baseQuery ?>&page_no=<?= $prevPage ?>" <?= $page <= 1 ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Previous</a>
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a class="page-number <?= $i === $page ? 'active' : '' ?>" href="app.php?<?= $baseQuery ?>&page_no=<?= $i ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <a class="btn btn-quiet <?= $page >= $totalPages ? 'disabled' : '' ?>" href="app.php?<?= $baseQuery ?>&page_no=<?= $nextPage ?>" <?= $page >= $totalPages ? 'aria-disabled="true" tabindex="-1"' : '' ?>>Next</a>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($viewAccount): ?>
            <template data-record-details data-title="Account Details" data-record-key="account_management:<?= (int)$viewAccount['id'] ?>" data-record-id="<?= htmlspecialchars($viewAccount['id_number']) ?>">
<p class="record-details-name"><?= htmlspecialchars(trim(implode(' ', array_filter([$viewAccount['first_name'], $viewAccount['middle_name'], $viewAccount['last_name']]))) ?: $viewAccount['username']) ?></p>
<dl class="detail-grid">
                    <div><dt>ID No.</dt><dd><?= htmlspecialchars($viewAccount['id_number']) ?></dd></div>
                    <div><dt>Full Name</dt><dd><?= htmlspecialchars(trim(implode(' ', array_filter([$viewAccount['first_name'], $viewAccount['middle_name'], $viewAccount['last_name']]))) ?: '—') ?></dd></div>
                    <div><dt>Username</dt><dd><?= htmlspecialchars($viewAccount['username']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= htmlspecialchars($viewAccount['email']) ?></dd></div>
                    <div><dt>Role</dt><dd><span class="role-badge"><?= htmlspecialchars($viewAccount['role']) ?></span></dd></div>
                    <div><dt>Status</dt><dd><span class="status-badge <?= accountStatusClass((string)$viewAccount['status']) ?>"><?= htmlspecialchars($viewAccount['status']) ?></span></dd></div>
                    <div><dt>Date Created</dt><dd><?= htmlspecialchars(formatDateTime($viewAccount['created_at'])) ?></dd></div>
                    <div><dt>Last Login</dt><dd><?= htmlspecialchars(formatDateTime($viewAccount['last_login'])) ?></dd></div>
                    <div class="detail-wide"><dt>Account Activity</dt><dd><?= !empty($viewAccount['last_login']) ? 'User has logged in successfully in the system.' : 'No successful login recorded.' ?></dd></div>
                </dl>
            
</template>
        <?php endif; ?>

        <?php if ($editAccount): ?>
            <section class="management-panel" id="account-form">
                <h2>Edit Account</h2>
                <p class="form-note">Update the account details for this selected user or administrator.</p>
                <form class="administrator-form" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="account_id" value="<?= (int)$editAccount['id'] ?>">

                    <label>ID No.<input readonly tabindex="-1" value="<?= htmlspecialchars($editAccount['id_number'] ?? '') ?>"></label>
                    <label>First Name<input required name="first_name" maxlength="50" value="<?= htmlspecialchars($editAccount['first_name'] ?? '') ?>"></label>
                    <label>Middle Name<input name="middle_name" maxlength="50" value="<?= htmlspecialchars($editAccount['middle_name'] ?? '') ?>"></label>
                    <label>Last Name<input required name="last_name" maxlength="50" value="<?= htmlspecialchars($editAccount['last_name'] ?? '') ?>"></label>
                    <label>Username<input required id="account-username" name="username" maxlength="40" pattern="<?= ($editAccount['role'] ?? 'USER') === 'USER' ? '[a-z]{5,}\\.[a-z]{2,}' : '[A-Za-z0-9]{5,40}' ?>" value="<?= htmlspecialchars($editAccount['username'] ?? '') ?>"></label>
                    <label>Email<input required type="email" name="email" maxlength="100" value="<?= htmlspecialchars($editAccount['email'] ?? '') ?>"></label>
                    <label>Role<select id="account-role-edit" name="role">
                        <option value="USER" <?= ($editAccount['role'] ?? 'USER') === 'USER' ? 'selected' : '' ?>>User</option>
                        <option value="ADMIN" <?= ($editAccount['role'] ?? 'ADMIN') === 'ADMIN' ? 'selected' : '' ?>>Administrator</option>
                        <?php if (($_SESSION['role'] ?? 'USER') === 'SUPER_ADMIN'): ?>
                            <option value="SUPER_ADMIN" <?= ($editAccount['role'] ?? 'USER') === 'SUPER_ADMIN' ? 'selected' : '' ?>>Super Administrator</option>
                        <?php endif; ?>
                    </select></label>

                    <div class="form-actions">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
                        <a class="btn btn-quiet" href="app.php?page=account_management">Cancel</a>
                    </div>
                </form>
            </section>
        <?php endif; ?>
        <?php require __DIR__ . '/record_details_modal.php'; ?>
    </main>
    <?php if (($_SESSION['role'] ?? 'USER') === 'SUPER_ADMIN'): ?>
    <div class="delete-modal-backdrop" id="deleteAccountModal" aria-hidden="true">
        <div class="delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteAccountModalTitle">
            <div class="delete-modal-header">
                <h3 id="deleteAccountModalTitle">Delete Account</h3>
                <button type="button" class="delete-modal-close" aria-label="Close delete confirmation">&times;</button>
            </div>
            <div class="delete-modal-body">
                <p class="delete-modal-intro">You are about to permanently delete this account.</p>
                <dl class="delete-account-summary">
                    <div><dt>ID No.</dt><dd id="deleteAccountIdNumber">—</dd></div>
                    <div><dt>Full Name</dt><dd id="deleteAccountFullName">—</dd></div>
                    <div><dt>Username</dt><dd id="deleteAccountUsername">—</dd></div>
                    <div><dt>Role</dt><dd id="deleteAccountRole">—</dd></div>
                    <div class="full-width"><dt>Email</dt><dd id="deleteAccountEmail">—</dd></div>
                </dl>
                <form method="post" class="delete-account-form" id="deleteAccountForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="action" value="delete_account">
                    <input type="hidden" name="account_id" id="deleteAccountId" value="">
                    <label for="deleteAccountReason">Reason for deletion <span class="required-marker">*</span>
                        <textarea id="deleteAccountReason" name="deletion_reason" maxlength="500" placeholder="Enter the reason for this permanent deletion." required></textarea>
                    </label>
                    <p class="delete-warning">This action cannot be undone.</p>
                    <div class="form-actions delete-actions">
                        <button type="button" class="btn btn-quiet delete-cancel" data-close-modal>Cancel</button>
                        <button type="button" class="btn btn-danger delete-final-trigger"><i class="fas fa-trash"></i> Continue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="delete-modal-backdrop" id="deleteConfirmModal" aria-hidden="true">
        <div class="delete-modal" role="dialog" aria-modal="true" aria-labelledby="deleteConfirmModalTitle">
            <div class="delete-modal-header">
                <h3 id="deleteConfirmModalTitle">Final Confirmation</h3>
                <button type="button" class="delete-modal-close" aria-label="Close final confirmation">&times;</button>
            </div>
            <div class="delete-modal-body">
                <p class="delete-modal-intro">This account will be permanently deleted.</p>
                <p class="delete-confirmation-copy">Please confirm that you want to continue with this irreversible action.</p>
                <div class="form-actions delete-actions">
                    <button type="button" class="btn btn-quiet delete-cancel" data-close-modal>Cancel</button>
                    <button type="submit" class="btn btn-danger" form="deleteAccountForm"><i class="fas fa-trash"></i> Delete Account</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($editAccount): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const role = document.getElementById('account-role-edit');
        const username = document.getElementById('account-username');
        if (!role || !username) return;
        function syncUsernameRule() {
            const isUser = role.value === 'USER';
            username.pattern = isUser ? '[a-z]{5,}\\.[a-z]{2,}' : '[A-Za-z0-9]{5,40}';
            username.title = isUser ? 'Use lowercase name.lastname format.' : 'Use 5-40 letters and numbers only.';
        }
        role.addEventListener('change', syncUsernameRule);
        syncUsernameRule();
    });
    </script>
    <?php endif; ?>
    <?php if (($_SESSION['role'] ?? 'USER') === 'SUPER_ADMIN'): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const modal = document.getElementById('deleteAccountModal');
        const confirmModal = document.getElementById('deleteConfirmModal');
        const closeButtons = document.querySelectorAll('.delete-modal-close, [data-close-modal]');
        const triggerButtons = document.querySelectorAll('.delete-account-trigger');
        const continueButton = document.querySelector('.delete-final-trigger');
        const hiddenId = document.getElementById('deleteAccountId');
        const idNumber = document.getElementById('deleteAccountIdNumber');
        const fullName = document.getElementById('deleteAccountFullName');
        const username = document.getElementById('deleteAccountUsername');
        const roleBadge = document.getElementById('deleteAccountRole');
        const email = document.getElementById('deleteAccountEmail');
        const reason = document.getElementById('deleteAccountReason');
        if (!modal || !hiddenId) return;

        function closeModals() {
            modal.classList.remove('open');
            modal.setAttribute('aria-hidden', 'true');
            if (confirmModal) {
                confirmModal.classList.remove('open');
                confirmModal.setAttribute('aria-hidden', 'true');
            }
            if (reason) reason.value = '';
        }

        function openConfirm() {
            if (!reason || !reason.value.trim()) {
                reason.focus();
                reason.setCustomValidity('A deletion reason is required.');
                reason.reportValidity();
                reason.setCustomValidity('');
                return;
            }
            if (confirmModal) {
                confirmModal.classList.add('open');
                confirmModal.setAttribute('aria-hidden', 'false');
            }
        }

        triggerButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                hiddenId.value = button.dataset.accountId || '';
                if (idNumber) idNumber.textContent = button.dataset.idNumber || '—';
                if (fullName) fullName.textContent = button.dataset.fullName || '—';
                if (username) username.textContent = button.dataset.username || '—';
                if (roleBadge) roleBadge.textContent = button.dataset.role || '—';
                if (email) email.textContent = button.dataset.email || '—';
                if (reason) reason.value = '';
                modal.classList.add('open');
                modal.setAttribute('aria-hidden', 'false');
                if (confirmModal) {
                    confirmModal.classList.remove('open');
                    confirmModal.setAttribute('aria-hidden', 'true');
                }
                if (reason) reason.focus();
            });
        });

        if (continueButton) {
            continueButton.addEventListener('click', openConfirm);
        }

        closeButtons.forEach(function (button) {
            button.addEventListener('click', closeModals);
        });

        [modal, confirmModal].forEach(function (currentModal) {
            if (!currentModal) return;
            currentModal.addEventListener('click', function (event) {
                if (event.target === currentModal) {
                    closeModals();
                }
            });
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && (modal.classList.contains('open') || (confirmModal && confirmModal.classList.contains('open')))) {
                closeModals();
            }
        });
    });
    </script>
    <?php endif; ?>
</body>
</html>
