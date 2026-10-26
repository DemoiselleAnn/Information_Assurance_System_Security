<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'authorization.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? 'USER') !== 'SUPER_ADMIN') {
    http_response_code(403);
    exit('Forbidden');
}
requirePermission($pdo, 'MANAGE_ROLES');
$permissions = ['VIEW_DASHBOARD'=>'View Dashboard','MANAGE_USERS'=>'Account Management','APPROVE_REGISTRATIONS'=>'Registration Approval','BLOCK_ACCOUNTS'=>'Block / Unblock Accounts','VIEW_SECURITY_LOGS'=>'Security Logs','MANAGE_ADMINISTRATORS'=>'Manage Administrators','MANAGE_ROLES'=>'Manage Roles & Permissions','SUBMIT_DELETION_REQUESTS'=>'Submit Deletion Requests','APPROVE_DELETIONS'=>'Approve Deletions'];
$roles = ['SUPER_ADMIN','ADMIN','USER'];
$csrf = $_SESSION['roles_csrf'] ??= bin2hex(random_bytes(32));
$errors = []; $message = $_GET['message'] ?? '';
function rpName(array $user): string { return trim(implode(' ', array_filter([$user['first_name'] ?? '', $user['middle_name'] ?? '', $user['last_name'] ?? '', $user['extension_name'] ?? '']))); }
function rpLog(PDO $pdo, array $target, string $before, string $after): void { $stmt=$pdo->prepare("SELECT id_number,username,first_name,middle_name,last_name,extension_name,role FROM users WHERE id=?"); $stmt->execute([(int)$_SESSION['user_id']]); $actor=$stmt->fetch(PDO::FETCH_ASSOC); if (!$actor) return; $log=$pdo->prepare("INSERT INTO security_logs (user_id,username,full_name,role,action,ip_address,user_agent,actor_id_number,target_user_id,target_username,status,failure_reason) VALUES (?,?,?,?,?,?,?,?,?,?, 'SUCCESS', ?)"); $log->execute([(int)$_SESSION['user_id'],$actor['username'],rpName($actor),$actor['role'],'PERMISSIONS_CHANGED',$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null,$actor['id_number'],$target['id'],$target['username'],'Previous: '.$before.' New: '.$after]); }
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrf, $_POST['csrf_token'])) { http_response_code(403); exit('Invalid request token'); }
    $targetId = is_scalar($_POST['user_id'] ?? null) ? (int)$_POST['user_id'] : 0;
    $submitted = $_POST['permissions'] ?? [];
    if (!is_array($submitted)) {
        http_response_code(422);
        $errors[] = 'Invalid permissions submission.';
    } else {
        try {
            $pdo->beginTransaction();
            $targetStmt = $pdo->prepare("SELECT id,id_number,first_name,middle_name,last_name,extension_name,username,email,role,status,created_at FROM users WHERE id=? AND (role NOT IN ('ADMIN', 'SUPER_ADMIN') OR first_login_required=0) FOR UPDATE");
            $targetStmt->execute([$targetId]);
            $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new InvalidArgumentException('Account not found.');
            validateIndividualPermissions($target['role'], $submitted);
            $selected = array_values(array_unique($submitted));
            $old = [];
            foreach ($permissions as $permission => $label) {
                if (hasPermission($pdo, $targetId, $permission)) $old[] = $permission;
            }
            // Keep unrelated overrides intact; neutralize legacy grants outside role boundaries.
            $insert = $pdo->prepare('INSERT INTO user_permissions (user_id,permission,enabled) VALUES (?,?,?) ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)');
            foreach (array_keys($permissions) as $permission) {
                if (permissionIsMandatory($target['role'], $permission)) continue;
                $insert->execute([$targetId, $permission, in_array($permission, $selected, true) ? 1 : 0]);
            }
            $after = [];
            foreach ($permissions as $permission => $label) {
                if (hasPermission($pdo, $targetId, $permission)) $after[] = $permission;
            }
            rpLog($pdo, $target, implode(', ', $old), implode(', ', $after));
            $pdo->commit();
            header('Location: app.php?page=roles_permissions&user_id='.$targetId.'&message='.rawurlencode('Permissions updated and logged successfully.'));
            exit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($e instanceof InvalidArgumentException) http_response_code(422);
            $errors[] = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Permissions could not be updated. Please try again.';
        }
    }
}
$usersStmt=$pdo->query("SELECT id,id_number,first_name,middle_name,last_name,extension_name,username,email,role,status,created_at FROM users WHERE (role NOT IN ('ADMIN', 'SUPER_ADMIN') OR first_login_required=0) ORDER BY role, last_name, first_name"); $users=$usersStmt->fetchAll(PDO::FETCH_ASSOC); $selectedId=(int)($_GET['user_id']??0); $selectedUser=null; foreach($users as $user) if((int)$user['id']===$selectedId)$selectedUser=$user;
$custom=[]; if($selectedUser){$stmt=$pdo->prepare('SELECT permission FROM user_permissions WHERE user_id=? AND enabled=1');$stmt->execute([$selectedId]);$custom=$stmt->fetchAll(PDO::FETCH_COLUMN);}
$roleDefaults=['SUPER_ADMIN'=>array_keys($permissions),'ADMIN'=>['VIEW_DASHBOARD','MANAGE_USERS','APPROVE_REGISTRATIONS','BLOCK_ACCOUNTS','VIEW_SECURITY_LOGS','SUBMIT_DELETION_REQUESTS'],'USER'=>['VIEW_DASHBOARD','VIEW_SECURITY_LOGS']];
// Show configured defaults subject to the same mandatory role boundaries as the backend.
$defaultRows = $pdo->query('SELECT role, permission, enabled FROM role_permissions')->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $matrixRole) {
    $roleDefaults[$matrixRole] = [];
    foreach ($permissions as $key => $label) {
        $configured = false;
        foreach ($defaultRows as $row) {
            if ($row['role'] === $matrixRole && $row['permission'] === $key) $configured = (bool)$row['enabled'];
        }
        if ($matrixRole === 'SUPER_ADMIN' || (permissionAllowedForRole($matrixRole, $key)
            && (permissionIsMandatory($matrixRole, $key) || $configured))) $roleDefaults[$matrixRole][] = $key;
    }
}
function rpPermissionNote(string $role, string $permission): string {
    if ($permission === 'VIEW_DASHBOARD') return 'Included for authenticated accounts';
    if ($permission === 'VIEW_SECURITY_LOGS') return match ($role) {
        'SUPER_ADMIN' => 'All security logs', 'ADMIN' => 'Administrator and User logs', default => 'Own User logs only; included access',
    };
    if ($permission === 'BLOCK_ACCOUNTS' && $role === 'ADMIN') return 'Requires Account Management permission as well';
    if (!permissionAllowedForRole($role, $permission)) return 'Unavailable for this account role';
    return 'Individual account permission';
}
$editMode = ($_GET['mode'] ?? 'view') === 'edit';
$effectivePermissions = [];
if ($selectedUser) {
    foreach ($permissions as $key => $label) {
        $effectivePermissions[$key] = hasPermission($pdo, $selectedId, $key);
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Roles & Permissions - FROSTLINE</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link rel="stylesheet" href="../css/superadmin.css?v=<?=filemtime(__DIR__.'/../css/superadmin.css')?>"><link rel="stylesheet" href="../css/admin_management.css?v=<?=filemtime(__DIR__.'/../css/admin_management.css')?>"><link rel="stylesheet" href="../css/roles_permissions.css?v=<?=filemtime(__DIR__.'/../css/roles_permissions.css')?>">    <link rel="stylesheet" href="../css/record_details.css?v=<?= filemtime(__DIR__ . '/../css/record_details.css') ?>">
    <script src="../js/record_details.js?v=<?= filemtime(__DIR__ . '/../js/record_details.js') ?>" defer></script>
</head><body class="superadmin-body"><main class="management-page"><header class="management-header"><div><p class="eyebrow">ACCOUNT SECURITY</p><h1>Roles &amp; Permissions</h1><p class="management-subtitle">Control account access without changing the account's fundamental role.</p></div></header><?php if($message):?><div class="management-alert success"><?=htmlspecialchars($message)?></div><?php endif;?><?php foreach($errors as $error):?><div class="management-alert error"><?=htmlspecialchars($error)?></div><?php endforeach;?><section class="management-panel"><h2>Permission Matrix</h2><p class="account-permission-note">Role defaults are shown below; eligible individual overrides may differ. Dashboard access is included. Security Logs: Super Administrators see all logs, Administrators see Administrator and User logs, and Users see only their own User logs.</p><div class="table-container"><table class="management-table matrix"><thead><tr><th>Permission</th><th>Super Admin</th><th>Administrator</th><th>User</th></tr></thead><tbody><?php foreach($permissions as $key=>$label):?><tr><td><?=htmlspecialchars($label)?></td><?php foreach($roles as $role):?><td><?=in_array($key,$roleDefaults[$role],true)?'<span class="allowed"><i class="fas fa-check"></i> Allowed</span>':'<span class="denied"><i class="fas fa-minus"></i> Restricted</span>'?></td><?php endforeach;?></tr><?php endforeach;?></tbody></table></div></section><section class="management-panel individual-accounts" id="individual-accounts">
    <h2>Manage Individual Account</h2>
    <div class="filter-bar account-filters">
        <label>Search accounts<input type="search" id="account-search" placeholder="Search by ID No., name, or username" autocomplete="off"></label>
        <label>Role<select id="account-role"><option value="">All Roles</option><option value="SUPER_ADMIN">Super Admin</option><option value="ADMIN">Admin</option><option value="USER">User</option></select></label>
        <label>Status<select id="account-status"><option value="">All Statuses</option><?php foreach (['ACTIVE','INACTIVE','PENDING','BLOCKED','REJECTED'] as $status): ?><option value="<?= $status ?>"><?= ucfirst(strtolower($status)) ?></option><?php endforeach; ?></select></label>
    </div>
    <p class="account-result-count" id="account-result-count" aria-live="polite"></p>
    <div class="table-container">
        <table class="management-table account-results">
            <thead><tr><th>ID NO.</th><th>Account Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr data-account-row data-search="<?= htmlspecialchars($user['id_number'].' '.rpName($user).' '.$user['username'], ENT_QUOTES, 'UTF-8') ?>" data-role="<?= htmlspecialchars($user['role']) ?>" data-status="<?= htmlspecialchars($user['status']) ?>">
                    <td><?= htmlspecialchars($user['id_number']) ?></td>
                    <td><?= htmlspecialchars(rpName($user)) ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><span class="role-badge <?= $user['role']==='SUPER_ADMIN'?'super-admin':'' ?>"><?= htmlspecialchars(str_replace('_',' ', $user['role'])) ?></span></td>
                    <td><span class="status-badge <?= strtolower($user['status']) ?>"><?= htmlspecialchars($user['status']) ?></span></td>
                    <td class="action-cell"><a data-record-view data-record-key="roles_permissions:<?= (int)$user['id'] ?>" aria-haspopup="dialog" href="app.php?page=roles_permissions&amp;user_id=<?= (int)$user['id'] ?>&amp;mode=view#account-permissions">View Permissions</a><?php if ($user['role'] !== 'SUPER_ADMIN'): ?><a href="app.php?page=roles_permissions&amp;user_id=<?= (int)$user['id'] ?>&amp;mode=edit#account-permissions">Edit Permissions</a><?php endif; ?>
                    <?php if ($user['role'] === 'SUPER_ADMIN' && (int)$user['id'] !== (int)$_SESSION['user_id']): ?>
                    <?php $demotionAccount = $user; require __DIR__ . '/superadmin_demotion_modal.php'; ?>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
                <tr id="account-empty" <?= $users?'hidden':'' ?>><td colspan="6" class="empty-state">No accounts found matching your search.</td></tr>
            </tbody>
        </table>
    </div>
    <?php if ($selectedUser): ?>
    <?php if (!$editMode): ?><template data-record-details data-title="Permissions Details" data-record-key="roles_permissions:<?= (int)$selectedUser['id'] ?>" data-record-id="<?= htmlspecialchars($selectedUser['id_number']) ?>"><?php endif; ?>
    <section id="account-permissions" class="individual-permissions" aria-labelledby="permissions-heading">
        <h3 id="permissions-heading"><?= $editMode?'Edit':'View' ?> Permissions</h3>
        <div class="account-summary"><strong><?= htmlspecialchars(rpName($selectedUser)) ?></strong><span><?= htmlspecialchars($selectedUser['id_number'].' | '.$selectedUser['username']) ?></span><span>Role: <b><?= htmlspecialchars($selectedUser['role']) ?></b> &middot; Status: <b><?= htmlspecialchars($selectedUser['status']) ?></b></span></div>
        <?php if ($selectedUser['role']==='SUPER_ADMIN'): ?><p class="account-permission-note">Super Admin has full privileges. Individual overrides do not restrict this role.</p><?php endif; ?>
        <?php if ($editMode && $selectedUser['role']!=='SUPER_ADMIN'): ?>
        <form method="post" onsubmit="return confirm('Save permission changes for this account? This action will be recorded in the security log.')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$selectedUser['id'] ?>">
            <div class="permission-list">
                <?php foreach ($permissions as $key=>$label): ?>
                <label class="permission-option"><input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($key) ?>" <?= $effectivePermissions[$key]?'checked':'' ?> <?= permissionCanBeCustomized($selectedUser['role'], $key) ? '' : 'disabled' ?>><span><b><?= htmlspecialchars($label) ?></b><small><?= htmlspecialchars(rpPermissionNote($selectedUser['role'], $key)) ?></small></span></label>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i> Save Permission Changes</button>
        </form>
        <?php else: ?>
        <div class="permission-list">
            <?php foreach ($permissions as $key=>$label): ?>
            <div class="permission-option"><span><b><?= htmlspecialchars($label) ?></b><span class="<?= $effectivePermissions[$key]?'allowed':'denied' ?>"><?= $effectivePermissions[$key]?'✓ Allowed':'— Restricted' ?></span><small><?= htmlspecialchars(rpPermissionNote($selectedUser['role'], $key)) ?></small></span></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
    <?php if (!$editMode): ?></template><?php endif; ?>
    <?php endif; ?>
</section>
<script src="../js/superadmin_demotion.js?v=<?= filemtime(__DIR__.'/../js/superadmin_demotion.js') ?>" defer></script>
<script src="js_gate.php?file=roles_permissions.js&amp;v=<?= filemtime(__DIR__.'/../js/roles_permissions.js') ?>" defer></script><?php require __DIR__ . '/record_details_modal.php'; ?></main></body></html>
