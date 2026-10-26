<?php
// Recheck the database so sessions established before setup enforcement cannot bypass it.
function requireCompletedAccountSetup(PDO $pdo): void {
    if (empty($_SESSION['user_id'])) return;
    $stmt = $pdo->prepare("SELECT first_login_required FROM users WHERE id = ? AND role IN ('USER', 'ADMIN', 'SUPER_ADMIN') LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    if ((int)$stmt->fetchColumn() !== 1) return;
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['login_flash'] = [
        'error_message' => 'Your account setup is not complete. Please log in to continue setup.',
        'remaining_time' => 0, 'attempt_count_display' => 0, 'locked_username' => '',
    ];
    header('Location: app.php?page=login');
    exit;
}

// Mandatory role boundaries apply before defaults or legacy individual overrides.
function permissionAllowedForRole(string $role, string $permission): bool {
    if ($role === 'SUPER_ADMIN') return true;
    if ($role === 'ADMIN') return in_array($permission, [
        'VIEW_DASHBOARD', 'MANAGE_USERS', 'APPROVE_REGISTRATIONS',
        'BLOCK_ACCOUNTS', 'VIEW_SECURITY_LOGS', 'SUBMIT_DELETION_REQUESTS',
    ], true);
    return $role === 'USER' && in_array($permission, ['VIEW_DASHBOARD', 'VIEW_SECURITY_LOGS'], true);
}

function permissionIsMandatory(string $role, string $permission): bool {
    return in_array($role, ['SUPER_ADMIN', 'ADMIN', 'USER'], true)
        && ($permission === 'VIEW_DASHBOARD' || ($role === 'USER' && $permission === 'VIEW_SECURITY_LOGS'));
}

function permissionCanBeCustomized(string $role, string $permission): bool {
    return $role !== 'SUPER_ADMIN' && permissionAllowedForRole($role, $permission)
        && !permissionIsMandatory($role, $permission);
}

function validateIndividualPermissions(string $role, array $selected): void {
    if (!in_array($role, ['ADMIN', 'USER'], true)) {
        throw new InvalidArgumentException('Super Administrator privileges cannot be modified through individual permissions.');
    }
    foreach ($selected as $permission) {
        if (!is_string($permission) || !permissionCanBeCustomized($role, $permission)) {
            throw new InvalidArgumentException('A selected permission cannot be assigned to this account role.');
        }
    }
}

function hasPermission(PDO $pdo, int $userId, string $permission): bool {
    $role = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $role->execute([$userId]);
    $accountRole = $role->fetchColumn();
    if ($accountRole === 'SUPER_ADMIN') return true;
    if (!permissionAllowedForRole((string)$accountRole, $permission)) return false;
    if (permissionIsMandatory((string)$accountRole, $permission)) return true;

    $override = $pdo->prepare('SELECT enabled FROM user_permissions WHERE user_id = ? AND permission = ? LIMIT 1');
    $override->execute([$userId, $permission]);
    $value = $override->fetchColumn();
    if ($value !== false) return (bool)$value;
    $default = $pdo->prepare('SELECT enabled FROM role_permissions WHERE role = ? AND permission = ? LIMIT 1');
    $default->execute([$accountRole, $permission]);
    return (bool)$default->fetchColumn();
}

function requirePermission(PDO $pdo, string $permission): void {
    requireCompletedAccountSetup($pdo);
    require_once __DIR__ . '/superadmin_session.php';
    if (strtoupper((string)($_SESSION['role'] ?? '')) === 'SUPER_ADMIN') {
        enforceSuperAdminSession($pdo, $_SERVER['REQUEST_METHOD'] === 'POST');
    }
    if (!hasPermission($pdo, (int)($_SESSION['user_id'] ?? 0), $permission)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
