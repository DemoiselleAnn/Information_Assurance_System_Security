<?php
require_once __DIR__ . '/security_log_helpers.php';
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once __DIR__ . '/profile_data.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/notifications_store.php';
requireCompletedAccountSetup($pdo);
require_once __DIR__ . '/account_status.php';
require_once __DIR__ . '/administrator_status.php';
require_once 'recovery_mail.php';
require_once __DIR__ . '/temporary_credentials.php';
require_once __DIR__ . '/session_presence.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'USER') !== 'SUPER_ADMIN') {
    if (($_SESSION['role'] ?? 'USER') === 'ADMIN') {
        header('Location: app.php?page=administrators');
        exit();
    }
    http_response_code(403);
    exit('Forbidden');
}

function redirectToManagement(array $params = []): void {
    $query = http_build_query(array_merge(['page' => 'administrators'], $params));
    header('Location: app.php?' . $query);
    exit();
}

function managementCsrfToken(): string {
    if (empty($_SESSION['admin_management_csrf'])) {
        $_SESSION['admin_management_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_management_csrf'];
}

function logManagementEvent(PDO $pdo, string $action, array $target, string $failureReason = null, bool $strict = false): void {
    try {
        $actorId = (int)$_SESSION['user_id'];
        $actor = $pdo->prepare('SELECT id_number, username, first_name, middle_name, last_name, extension_name, role FROM users WHERE id = ? LIMIT 1');
        $actor->execute([$actorId]);
        $actorData = $actor->fetch(PDO::FETCH_ASSOC);
        if (!$actorData) {
            if ($strict) throw new RuntimeException('Audit actor could not be verified.');
            return;
        }

        $actorName = securityLogFullName($actorData);
        $targetName = trim(implode(' ', array_filter([
            $target['first_name'] ?? '',
            $target['middle_name'] ?? '',
            $target['last_name'] ?? ''
        ])));
        $details = 'Target: ' . $targetName . ' (' . ($target['id_number'] ?? '') . ')';
        $status  = $failureReason ? 'FAILED' : 'SUCCESS';

        $stmt = $pdo->prepare(
            "INSERT INTO security_logs
             (user_id, username, full_name, role, action, ip_address, user_agent,
              actor_id_number, target_user_id, target_username, status, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $actorId,
            $actorData['username'],
            $actorName,
            $actorData['role'],
            $action,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $actorData['id_number'],
            $target['id'] ?? null,
            $target['username'] ?? null,
            $status,
            $failureReason
        ]);
        if ($status === 'SUCCESS') notifyAdministratorEvent($pdo, (int)$target['id'], (int)$pdo->lastInsertId(), $action);
    } catch (Throwable $e) {
        if ($strict) throw $e;
        error_log('Administrator management log error: ' . $e->getMessage());
    }
}

function getAdmin(PDO $pdo, int $id, bool $completedOnly = false): ?array {
    $stmt = $pdo->prepare(
        "SELECT id, id_number, first_name, middle_name, last_name, username, email,
                role, status, first_login_required, temporary_credential_expires_at, temporary_credential_used_at, created_at
         FROM users
         WHERE id = ? AND role IN ('ADMIN', 'SUPER_ADMIN')
           AND (? = 0 OR first_login_required = 0)
         LIMIT 1"
    );
    $stmt->execute([$id, (int)$completedOnly]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
}

function getEditableAdmin(PDO $pdo, int $id, bool $completedOnly = false): ?array {
    $stmt = $pdo->prepare(
        "SELECT id, id_number, first_name, middle_name, last_name, username, email,
                role, status, first_login_required, temporary_credential_expires_at, temporary_credential_used_at, created_at
         FROM users
         WHERE id = ? AND role = 'ADMIN'
           AND (? = 0 OR first_login_required = 0)
         LIMIT 1"
    );
    $stmt->execute([$id, (int)$completedOnly]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    return $admin ?: null;
}

function sendAdminCreationEmail(
    PDO $pdo,
    string $recipientEmail,
    string $recipientName,
    string $idNumber,
    string $username,
    string $tempPassword,
    int    $newUserId,
    string $role
): bool {
     return sendTemporaryCredentialEmail($recipientEmail, $recipientName, $idNumber, $username, $tempPassword, $newUserId, $role);
}

// ---------------------------------------------------------------------------
// POST handling
// ---------------------------------------------------------------------------
$errors = [];
$message = '';
$editAdmin = null;
$viewAdmin = null;
$selectedRole = 'ADMIN';
$superAdminLimitMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_string($_SESSION['admin_management_csrf'] ?? null) || $_SESSION['admin_management_csrf'] === ''
        || !is_string($_POST['csrf_token'] ?? null) || !hash_equals($_SESSION['admin_management_csrf'], $_POST['csrf_token'])) {
        http_response_code(403);
        exit('Invalid request token');
    }

    $action   = $_POST['action'] ?? '';
    if (!in_array($action, ['create', 'update', 'block', 'unblock'], true)) {
        http_response_code(400);
        exit('Unsupported account action');
    }
    $targetId = (int)($_POST['admin_id'] ?? 0);
    $target   = $targetId > 0
        ? (in_array($action, ['block', 'unblock'], true) ? getAdmin($pdo, $targetId, true) : getEditableAdmin($pdo, $targetId, true))
        : null;

    // ── Block / Unblock ──────────────────────────────────────────────────
    if (in_array($action, ['block', 'unblock'], true)) {
        [$updated, $statusMessage] = transitionAdministratorStatus($pdo, $targetId, $action);
        if (!$updated && $statusMessage === 'SUPER_ADMIN_LIMIT_UNBLOCK') {
            redirectToManagement(['super_admin_limit' => 'unblock']);
        }
        redirectToManagement([$updated ? 'message' : 'error' => $statusMessage]);
    }

    // ── Create / Update ──────────────────────────────────────────────────
    if ($action === 'create' || $action === 'update') {
        $firstName    = trim($_POST['first_name']  ?? '');
        $middleName   = trim($_POST['middle_name'] ?? '');
        $lastName     = trim($_POST['last_name']   ?? '');
        $username     = trim($_POST['username']    ?? '');
        $email        = trim($_POST['email']       ?? '');
        $selectedRole = strtoupper(trim($_POST['role'] ?? 'ADMIN'));
        $temporaryPassword = (string)($_POST['temporary_password'] ?? '');
        $temporaryPasswordConfirmation = (string)($_POST['temporary_password_confirmation'] ?? '');
        // Never accept an account ID from the request, including during edits.
        $idNumber = $target['id_number'] ?? '';

        // Status changes are handled only by the dedicated block/unblock actions.

        // ── Validate fields ──────────────────────────────────────────────
        if ($firstName === '' || $lastName === '') {
            $errors[] = 'First name and last name are required.';
        }
        if (!preg_match('/^[A-Za-z][A-Za-z -]{0,49}$/', $firstName)
            || ($middleName !== '' && !preg_match('/^[A-Za-z][A-Za-z -]{0,49}$/', $middleName))
            || !preg_match('/^[A-Za-z][A-Za-z -]{0,49}$/', $lastName)) {
            $errors[] = 'Names may contain letters, spaces, and hyphens only.';
        }
        if (!preg_match('/^[A-Za-z0-9]{5,40}$/', $username)) {
            $errors[] = 'Administrator username must be 5–40 alphanumeric characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($selectedRole, ['ADMIN', 'SUPER_ADMIN'], true)) {
            $errors[] = 'Invalid account role.';
        }
        if ($selectedRole === 'SUPER_ADMIN'
            && ($_SESSION['role'] ?? 'USER') !== 'SUPER_ADMIN') {
            $errors[] = 'Only a Super Administrator can create a Super Administrator account.';
        }

        if ($action === 'create') {
            $birthdate = is_string($_POST['birthdate'] ?? null) ? trim($_POST['birthdate']) : '';
            $age = profileAge($birthdate);
            if ($age === null) $errors[] = 'Enter a valid birthdate that is not in the future.';
            $passwordError = validateTemporaryPassword($temporaryPassword, $temporaryPasswordConfirmation);
            if ($passwordError) $errors[] = $passwordError;
        }

        // ── Duplicate check ──────────────────────────────────────────────
        $dupStmt = $pdo->prepare(
            'SELECT id, id_number, username, email
             FROM users
             WHERE (username = ? OR email = ?) AND id <> ?
             LIMIT 1'
        );
        $dupStmt->execute([
            $username, $email,
            $action === 'update' ? $targetId : 0
        ]);
        $duplicate = $dupStmt->fetch(PDO::FETCH_ASSOC);
        if ($duplicate) {
            if ($duplicate['username']  === $username)  $errors[] = 'Username already exists.';
            if ($duplicate['email']     === $email)     $errors[] = 'Email already exists.';
        }

        if ($action === 'update' && !$target) {
            $errors[] = 'Administrator not found.';
        }

        if (!$errors) {
            if ($action === 'create') {
                // The plaintext is used only for this email and is never persisted or logged.
                $tempPassword  = $temporaryPassword;
                $hashedPassword = password_hash($temporaryPassword, PASSWORD_DEFAULT);

                $pdo->beginTransaction();
                try {
                    if ($selectedRole === 'SUPER_ADMIN') {
                        requireSuperAdminSlot($pdo);
                    }
                    $idNumber = generateAccountId($pdo);
                    $stmt = $pdo->prepare(
                        "INSERT INTO users
                         (id_number, first_name, middle_name, last_name, extension_name,
                          birthdate, age, username, password, email,
                          sex, purok, barangay, city_municipality, province,
                          country, zip_code, role, status, first_login_required,
                          temporary_credential_expires_at, temporary_credential_used_at)
                         VALUES (?, ?, ?, ?, NULL,
                                 ?, ?, ?, ?, ?,
                                 'private', '', '', '', '',
                                 '', '', ?, 'INACTIVE', 1, ?, NULL)"
                    );
                    $stmt->execute([
                        $idNumber,
                        $firstName,
                        $middleName ?: null,
                        $lastName,
                        $birthdate,
                        $age,
                        $username,
                        $hashedPassword,
                        $email,
                        $selectedRole,
                        temporaryCredentialExpiresAt()
                    ]);
                    $newId    = (int)$pdo->lastInsertId();
                    $newAdmin = getAdmin($pdo, $newId);
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    // Clear temp password from memory before surfacing error
                    $tempPassword = '';
                    if ($e instanceof SuperAdminLimitReached) {
                        $superAdminLimitMessage = 'Two usable Super Administrator accounts already exist. Block one of the existing Super Administrators before creating another.';
                    } else {
                        $errors[] = 'Account could not be created. Please try again.';
                        error_log('Admin creation DB error: ' . $e->getMessage());
                    }
                    goto renderPage;
                }

                // ── Log account creation ──────────────────────────────────
                if ($newAdmin) {
                    logManagementEvent(
                        $pdo,
                        $selectedRole === 'SUPER_ADMIN' ? 'SUPER_ADMIN_CREATED' : 'ADMIN_CREATED',
                        $newAdmin
                    );
                }

                // ── Send credentials email ────────────────────────────────
                $fullName    = trim(implode(' ', array_filter([$firstName, $middleName, $lastName])));
                $emailSent   = sendAdminCreationEmail(
                    $pdo,
                    $email,
                    $fullName,
                    $idNumber,
                    $username,
                    $tempPassword,
                    $newId,
                    $selectedRole
                );

                // Wipe temp password from memory immediately after sending
                $tempPassword = str_repeat('0', 12);
                unset($tempPassword);

                if ($newAdmin) {
                    if ($emailSent) {
                        logManagementEvent($pdo, 'ADMIN_CREATION_EMAIL_SENT', $newAdmin);
                    } else {
                        // Log that email failed, but do NOT store any password detail
                        logManagementEvent(
                            $pdo,
                            'ADMIN_CREATION_EMAIL_SENT',
                            $newAdmin,
                            'Credentials email could not be delivered – check mail configuration'
                        );
                    }
                }

                $successMsg = ($selectedRole === 'SUPER_ADMIN' ? 'Super Administrator' : 'Administrator')
                    . ' account created. It will appear in the Administrators listing after first-login setup is completed. ';
                $successMsg .= $emailSent
                    ? 'Credentials email accepted for sending to ' . htmlspecialchars($email) . '. Inbox delivery is not confirmed.'
                    : 'WARNING: The credentials email could not be sent. Please contact support to check the mail configuration.';

                redirectToManagement(['message' => $successMsg]);
            }

            // ── Update ────────────────────────────────────────────────────
            $stmt = $pdo->prepare(
                "UPDATE users
                 SET first_name = ?, middle_name = ?,
                     last_name = ?, username = ?, email = ?
                 WHERE id = ? AND role = 'ADMIN'"
            );
            $stmt->execute([
                $firstName, $middleName ?: null,
                $lastName, $username, $email,
                $targetId
            ]);
            $updatedAdmin = getAdmin($pdo, $targetId);
            if ($updatedAdmin) logManagementEvent($pdo, 'ADMIN_UPDATED', $updatedAdmin);
            redirectToManagement(['message' => 'Administrator updated successfully.']);
        }

        // Re-populate form on validation failure
        $editAdmin = array_merge($target ?: [], [
            'id'          => $targetId,
            'id_number'   => $idNumber,
            'first_name'  => $firstName,
            'middle_name' => $middleName,
            'last_name'   => $lastName,
            'username'    => $username,
            'email'       => $email,
        ]);
    }
}

renderPage:
// ---------------------------------------------------------------------------
// GET handling – edit / view / list
// ---------------------------------------------------------------------------
if (isset($_GET['edit'])) {
    $editAdmin = getEditableAdmin($pdo, (int)$_GET['edit'], true);
    if (!$editAdmin) $errors[] = 'Administrator not found.';
}

if (isset($_GET['view'])) {
    $viewAdmin = getAdmin($pdo, (int)$_GET['view'], true);
    if (!$viewAdmin) $errors[] = 'Administrator not found.';
}

$q            = trim($_GET['q']      ?? '');
$statusFilter = strtoupper(trim($_GET['status'] ?? ''));

$sql    = "SELECT id, id_number, first_name, middle_name, last_name, username, email,
                  role, status, first_login_required, temporary_credential_expires_at, temporary_credential_used_at, created_at
           FROM users
                     WHERE role IN ('ADMIN', 'SUPER_ADMIN')
                         AND first_login_required = 0";
$params = [];
if ($q !== '') {
    $sql .= ' AND (id_number LIKE ? OR first_name LIKE ? OR middle_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ?)';
    $like   = '%' . $q . '%';
    $params = [$like, $like, $like, $like, $like, $like];
}
if (in_array($statusFilter, ['ACTIVE', 'INACTIVE', 'PENDING', 'BLOCKED', 'REJECTED'], true)) {
    $sql .= ' AND status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC, id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$administrators = $stmt->fetchAll(PDO::FETCH_ASSOC);

$emptyMessage = 'No completed administrator accounts found.';

$message = $_GET['message'] ?? '';
if (!empty($_GET['error'])) $errors[] = $_GET['error'];
$limitAction = $_GET['super_admin_limit'] ?? '';
if ($limitAction === 'unblock') {
    $superAdminLimitMessage = 'Two usable Super Administrator accounts already exist. Block one before unblocking another.';
}
if ($limitAction === 'create') {
    $superAdminLimitMessage = 'Two usable Super Administrator accounts already exist. Block one of the existing Super Administrators before creating another.';
}
$showSuperAdminLimit = $superAdminLimitMessage !== '';
$csrfToken = managementCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrators - FROSTLINE</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/superadmin.css?v=<?= filemtime(__DIR__ . '/../css/superadmin.css') ?>">
    <link rel="stylesheet" href="../css/admin_management.css?v=<?= filemtime(__DIR__ . '/../css/admin_management.css') ?>">
    <script src="../js/action_menu.js?v=<?= filemtime(__DIR__ . '/../js/action_menu.js') ?>" defer></script>
    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
</head>
<body class="superadmin-body">
    <main class="management-page">
        <header class="management-header">
            <div>
                <p class="eyebrow">ADMINISTRATION</p>
                <h1>Administrators</h1>
                <p class="management-subtitle">Manage administrator accounts and access status.</p>
            </div>
            <button class="btn btn-primary" type="button"
                onclick="document.getElementById('administrator-form').scrollIntoView({behavior:'smooth'})">
                <i class="fas fa-plus"></i> Create Administrative Account
            </button>
        </header>

        <?php if ($message): ?>
            <div class="management-alert success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php foreach ($errors as $error): ?>
            <div class="management-alert error"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
        <?php if ($showSuperAdminLimit): ?>
            <dialog open class="management-dialog" aria-labelledby="super-admin-limit-title">
                <form method="dialog">
                    <button type="button" class="management-dialog-close" data-management-dialog-close aria-label="Close dialog">&times;</button>
                    <h2 id="super-admin-limit-title">Super Administrator Limit Reached</h2>
                    <p><?= htmlspecialchars($superAdminLimitMessage) ?></p>
                    <button type="button" class="btn btn-primary" data-management-dialog-close>OK</button>
                </form>
            </dialog>
        <?php endif; ?>

        <!-- ── Administrator list ──────────────────────────────────────── -->
        <section class="management-panel">
            <form class="filter-bar" method="get" action="app.php">
                <input type="hidden" name="page" value="administrators">
                <label>Search
                    <input type="search" name="q" value="<?= htmlspecialchars($q) ?>"
                           placeholder="Search by ID No., name, or username">
                </label>
                <label>Status
                    <select name="status">
                        <option value="">All statuses</option>
                        <?php foreach (['ACTIVE','INACTIVE','PENDING','BLOCKED','REJECTED'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $statusFilter === $opt ? 'selected' : '' ?>>
                                <?= $opt ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn btn-secondary" type="submit"><i class="fas fa-search"></i> Search</button>
                <a class="btn btn-quiet" href="app.php?page=administrators">Clear</a>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$administrators): ?>
                        <tr><td colspan="9" class="empty-state"><?= htmlspecialchars($emptyMessage) ?></td></tr>
                    <?php endif; ?>
                    <?php foreach ($administrators as $admin): ?>
                        <tr data-account-id="<?= (int)$admin['id'] ?>">
                            <td class="data-employee-id"><?= htmlspecialchars($admin['id_number']) ?></td>
                            <td class="data-name"><?= htmlspecialchars(trim(implode(' ', array_filter([
                                    $admin['first_name'],
                                    $admin['middle_name'],
                                    $admin['last_name']
                                ])))) ?></td>
                            <td class="data-username"><?= htmlspecialchars($admin['username']) ?></td>
                            <td class="data-email"><?= htmlspecialchars($admin['email']) ?></td>
                            <td>
                                <span class="role-badge data-role-text <?= $admin['role'] === 'SUPER_ADMIN' ? 'super-admin' : '' ?>">
                                    <?= $admin['role'] === 'SUPER_ADMIN' ? 'SUPER ADMIN' : 'ADMIN' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= strtolower($admin['status']) ?> data-status-text">
                                    <?= htmlspecialchars($admin['status']) ?>
                                </span>
                            </td>
                            <td>
                                    <span class="status-badge active data-status-text" title="Account setup completed">
                                        <i class="fas fa-check-circle"></i> Complete
                                    </span>
                            </td>
                            <td class="data-date"><?= htmlspecialchars(date('Y-m-d H:i', strtotime($admin['created_at']))) ?></td>
                            <td class="action-cell">
                                <a class="btn btn-small btn-quiet"
                                   data-record-view data-record-key="administrators:<?= (int)$admin['id'] ?>" aria-haspopup="dialog" href="app.php?page=administrators&view=<?= (int)$admin['id'] ?>">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($admin['role'] === 'ADMIN' || ($admin['role'] === 'SUPER_ADMIN' && (int)$admin['id'] !== (int)$_SESSION['user_id'])): ?>
                                    <?php if ($admin['role'] === 'ADMIN'): ?>
                                        <a class="btn btn-small btn-secondary"
                                           href="app.php?page=administrators&edit=<?= (int)$admin['id'] ?>">
                                            <i class="fas fa-pen"></i> Edit
                                        </a>
                                    <?php endif; ?>
                                    <form method="post"
                                          onsubmit="return confirm('Change this administrator account status?')">
                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="hidden" name="admin_id"
                                               value="<?= (int)$admin['id'] ?>">
                                        <input type="hidden" name="action"
                                               value="<?= $admin['status'] === 'BLOCKED' ? 'unblock' : 'block' ?>">
                                        <button class="btn btn-small <?= $admin['status'] === 'BLOCKED' ? 'btn-approve' : 'btn-danger' ?>"
                                                type="submit">
                                            <i class="fas <?= $admin['status'] === 'BLOCKED' ? 'fa-unlock' : 'fa-lock' ?>"></i>
                                            <?= $admin['status'] === 'BLOCKED' ? 'Unblock' : 'Block' ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="locked-tag">Protected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- ── View panel ─────────────────────────────────────────────── -->
        <?php if ($viewAdmin): ?>
        <template data-record-details data-title="Administrator Details" data-record-key="administrators:<?= (int)$viewAdmin['id'] ?>" data-record-id="<?= htmlspecialchars($viewAdmin['id_number']) ?>">
<p class="record-details-name"><?= htmlspecialchars(trim(implode(' ', array_filter([
                            $viewAdmin['first_name'],
                            $viewAdmin['middle_name'],
                            $viewAdmin['last_name']
                        ])))) ?></p>
<dl class="detail-grid">
                <div><dt>ID No.</dt>
                     <dd><?= htmlspecialchars($viewAdmin['id_number']) ?></dd></div>
                <div><dt>Username</dt>
                     <dd><?= htmlspecialchars($viewAdmin['username']) ?></dd></div>
                <div><dt>Email</dt>
                     <dd><?= htmlspecialchars($viewAdmin['email']) ?></dd></div>
                <div><dt>Role</dt>
                     <dd><span class="role-badge <?= $viewAdmin['role'] === 'SUPER_ADMIN' ? 'super-admin' : '' ?>">
                         <?= $viewAdmin['role'] === 'SUPER_ADMIN' ? 'SUPER ADMIN' : 'ADMIN' ?>
                     </span></dd></div>
                <div><dt>Account Status</dt>
                     <dd><span class="status-badge <?= strtolower($viewAdmin['status']) ?>">
                         <?= htmlspecialchars($viewAdmin['status']) ?>
                     </span></dd></div>
                <div><dt>Setup Status</dt>
                    <dd><?= $viewAdmin['first_login_required']
                             ? '<span class="status-badge ' . (temporaryCredentialIsExpired($viewAdmin['temporary_credential_expires_at']) ? 'rejected' : 'pending') . '">' . (temporaryCredentialIsExpired($viewAdmin['temporary_credential_expires_at']) ? 'Credential Expired' : 'Pending First-Login Setup') . '</span>'
                             : '<span class="status-badge active">Setup Completed</span>' ?></dd></div>
                <div><dt>Date Created</dt>
                     <dd><?= htmlspecialchars(date('Y-m-d H:i', strtotime($viewAdmin['created_at']))) ?></dd></div>
            </dl>
        
</template>
        <?php endif; ?>

        <!-- ── Create / Edit form ──────────────────────────────────────── -->
        <section class="management-panel" id="administrator-form">
            <h2><?= ($editAdmin && !empty($editAdmin['id'])) ? 'Edit Administrator' : 'Create Administrative Account' ?></h2>
            <p class="form-note">
                <?php if ($editAdmin && !empty($editAdmin['id'])): ?>
                    Update administrator account details.
                <?php else: ?>
                    Create a new Administrator or Super Administrator account.
                    Enter a temporary password manually. It will be hashed, emailed once,
                    and accepted for 15 minutes before first-login setup is required.
                <?php endif; ?>
            </p>

            <form class="administrator-form" method="post" id="administrator-create-form">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="action"
                       value="<?= ($editAdmin && !empty($editAdmin['id'])) ? 'update' : 'create' ?>">
                <?php if ($editAdmin && !empty($editAdmin['id'])): ?>
                    <input type="hidden" name="admin_id" value="<?= (int)$editAdmin['id'] ?>">
                <?php endif; ?>

                <label>ID No.
                    <input readonly tabindex="-1"
                           value="<?= htmlspecialchars(($editAdmin['id_number'] ?? '') ?: 'Assigned automatically on creation') ?>">
                </label>
                <label>First Name
                    <input required name="first_name" maxlength="50"
                           value="<?= htmlspecialchars($editAdmin['first_name'] ?? '') ?>">
                </label>
                <label>Middle Name
                    <input name="middle_name" maxlength="50"
                           value="<?= htmlspecialchars($editAdmin['middle_name'] ?? '') ?>">
                </label>
                <label>Last Name
                    <input required name="last_name" maxlength="50"
                           value="<?= htmlspecialchars($editAdmin['last_name'] ?? '') ?>">
                </label>
                <label>Username
                    <input required name="username" maxlength="40"
                           value="<?= htmlspecialchars($editAdmin['username'] ?? '') ?>">
                </label>
                <label>Email
                    <input required type="email" name="email" maxlength="100"
                           value="<?= htmlspecialchars($editAdmin['email'] ?? '') ?>">
                </label>

                <?php if (!$editAdmin || empty($editAdmin['id'])): ?>
                    <label>Date of Birth
                        <input required type="date" name="birthdate" max="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars(is_string($_POST['birthdate'] ?? null) ? $_POST['birthdate'] : '') ?>">
                    </label>
                    <label>Temporary Password
                        <input required type="password" name="temporary_password" autocomplete="new-password">
                    </label>
                    <label>Confirm Temporary Password
                        <input required type="password" name="temporary_password_confirmation" autocomplete="new-password">
                    </label>
                    <label>Account Role
                        <select name="role" id="account-role">
                            <option value="ADMIN"
                                <?= $selectedRole === 'ADMIN' ? 'selected' : '' ?>>
                                Administrator
                            </option>
                            <option value="SUPER_ADMIN"
                                <?= $selectedRole === 'SUPER_ADMIN' ? 'selected' : '' ?>>
                                Super Administrator
                            </option>
                        </select>
                    </label>
                <?php endif; ?>

                <div class="form-actions">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-save"></i>
                        <?= ($editAdmin && !empty($editAdmin['id'])) ? 'Save Changes' : 'Create & Send Credentials' ?>
                    </button>
                    <?php if ($editAdmin && !empty($editAdmin['id'])): ?>
                        <a class="btn btn-quiet" href="app.php?page=administrators">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>
        <?php require __DIR__ . '/record_details_modal.php'; ?>
    </main>

    <?php if (!defined('APP_ENTRY')): ?><script src="../js/admin_management.js?v=<?= filemtime(__DIR__ . '/../js/admin_management.js') ?>" defer></script><?php endif; ?>
</body>
</html>
