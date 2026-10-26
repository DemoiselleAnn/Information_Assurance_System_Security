<?php
require_once __DIR__ . '/security_log_helpers.php';
require_once __DIR__ . '/session_presence.php';
require_once __DIR__ . '/demotion_request.php';

function getAccount(PDO $pdo, int $id, bool $managementOnly = false): ?array {
    $stmt = $pdo->prepare(
        "SELECT u.id, u.id_number, u.first_name, u.middle_name, u.last_name, u.username, u.email, u.role, u.status,
            u.first_login_required, u.temporary_credential_expires_at, u.temporary_credential_used_at, u.created_at,
                (SELECT MAX(sl.created_at)
                 FROM security_logs sl
                 WHERE sl.user_id = u.id AND sl.action = 'LOGIN' AND sl.status = 'SUCCESS') AS last_login
         FROM users u
         WHERE u.id = ?
           AND (? = 0 OR u.role NOT IN ('ADMIN', 'SUPER_ADMIN') OR u.first_login_required = 0)
         LIMIT 1"
    );
    $stmt->execute([$id, (int)$managementOnly]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    return $account ?: null;
}

function logAccountEvent(PDO $pdo, string $action, array $target): void {
    try {
        $actorId = (int)$_SESSION['user_id'];
        $actorStmt = $pdo->prepare('SELECT id_number, username, first_name, middle_name, last_name, extension_name, role FROM users WHERE id = ? LIMIT 1');
        $actorStmt->execute([$actorId]);
        $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);

        if (!$actor || !$target) {
            return;
        }

        $actorName = securityLogFullName($actor);
        $targetName = trim(implode(' ', array_filter([$target['first_name'], $target['middle_name'], $target['last_name']])));
        $details = 'Target: ' . $targetName . ' (' . $target['id_number'] . ')';
        $logStmt = $pdo->prepare(
            "INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, actor_id_number, target_user_id, target_username, status, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'SUCCESS', ?)"
        );
        $logStmt->execute([
            $actorId,
            $actor['username'],
            $actorName,
            $actor['role'],
            $action,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $actor['id_number'],
            $target['id'],
            $target['username'],
            $details,
        ]);
    } catch (Throwable $e) {
        error_log('Account management log error: ' . $e->getMessage());
    }
}

function accountCsrfToken(): string {
    if (empty($_SESSION['account_management_csrf'])) {
        $_SESSION['account_management_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['account_management_csrf'];
}

final class SuperAdminLimitReached extends RuntimeException {}

// The caller holds the singleton session lock until this transaction commits.
function demoteOtherSuperAdmin(PDO $pdo, int $targetId, $currentPassword): void {
    require_once __DIR__ . '/superadmin_session.php';
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    if (($_SESSION['role'] ?? '') !== 'SUPER_ADMIN' || !$actorId) {
        throw new DemotionValidationException('invalid_actor', 'Only an authenticated Super Admin can perform this action.', 403);
    }
    if ($targetId === $actorId) {
        throw new DemotionValidationException('invalid_target', 'You cannot demote your own Super Admin account.');
    }
    $pdo->beginTransaction();
    try {
        $state = $pdo->query('SELECT token_hash, user_id FROM superadmin_session_state WHERE id = 1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        $token = (string)($_SESSION['superadmin_session_token'] ?? '');
        if (!$state || (int)$state['user_id'] !== $actorId || $token === ''
            || !hash_equals((string)$state['token_hash'], superAdminSessionHash($token))
            || superAdminSessionState($pdo)['status'] !== 'active') {
            throw new DemotionValidationException('unauthenticated_session', 'Your session has expired. Please log in again.', 401);
        }
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$actorId]);
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$actor || $actor['role'] !== 'SUPER_ADMIN' || $actor['status'] === 'BLOCKED' || $actor['first_login_required']) {
            throw new DemotionValidationException('invalid_actor', 'The acting Super Admin could not be verified. Please log in again.', 403);
        }
        if (!is_string($currentPassword) || !password_verify($currentPassword, $actor['password'])) {
            throw new DemotionValidationException('invalid_password', 'Incorrect password. Please try again.');
        }
        $stmt->execute([$targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target) {
            throw new DemotionValidationException('invalid_target', 'The selected account could not be found.');
        }
        if ($target['role'] !== 'SUPER_ADMIN') {
            throw new DemotionValidationException('invalid_role_transition', 'The selected account is no longer a Super Admin. Refresh the page and try again.');
        }
        $update = $pdo->prepare("UPDATE users SET role = 'ADMIN', updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role = 'SUPER_ADMIN' AND id <> ?");
        $update->execute([$targetId, $actorId]);
        if ($update->rowCount() !== 1) throw new RuntimeException('The account role could not be changed.');
        // Removing overrides makes the account inherit the existing Admin defaults.
        $pdo->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$targetId]);
        $pdo->prepare('UPDATE superadmin_sessions SET ended_at = CURRENT_TIMESTAMP WHERE user_id = ? AND ended_at IS NULL')->execute([$targetId]);
        endUserPresence($pdo, $targetId, null);
        $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, session_key, actor_id_number, target_user_id, target_username, status, failure_reason) VALUES (?, ?, ?, ?, 'PERMISSIONS_CHANGED', ?, ?, ?, ?, ?, ?, 'SUCCESS', ?)")
            ->execute([$actorId, $actor['username'], securityLogFullName($actor), $actor['role'], $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null, $_SESSION['security_session_key'] ?? null, $actor['id_number'], $targetId, $target['username'], 'Target: '.$target['id_number'].' ('.$target['username'].') Previous role: SUPER_ADMIN; New role: ADMIN. Super Admin sessions revoked; Admin default permissions applied.']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function usableSuperAdminCount(PDO $pdo, ?int $excludedUserId = null): int {
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'SUPER_ADMIN' AND status <> 'BLOCKED' FOR UPDATE");
    $usableCount = 0;
    while ($userId = $stmt->fetchColumn()) {
        if ($excludedUserId === null || (int)$userId !== $excludedUserId) {
            $usableCount++;
        }
    }
    return $usableCount;
}

function hasAvailableSuperAdminSlot(PDO $pdo, ?int $excludedUserId = null): bool {
    return usableSuperAdminCount($pdo, $excludedUserId) < 2;
}

function requireSuperAdminSlot(PDO $pdo, ?int $excludedUserId = null): void {
    if (!hasAvailableSuperAdminSlot($pdo, $excludedUserId)) {
        throw new SuperAdminLimitReached('Two usable Super Administrator accounts already exist.');
    }
}

function updateAccountStatus(PDO $pdo, int $targetId, string $newStatus): array {
    $target = getAccount($pdo, $targetId);
    $actorRole = strtoupper($_SESSION['role'] ?? 'USER');
    $actorId = (int)($_SESSION['user_id'] ?? 0);

    if (!$target || !in_array($newStatus, ['INACTIVE', 'BLOCKED'], true)) {
        return [false, 'This account cannot be changed from this page.', null];
    }
    if ((int)$target['id'] === $actorId) {
        return [false, 'This account cannot be changed from this page.', $target];
    }
    if (!in_array($actorRole, ['ADMIN', 'SUPER_ADMIN'], true)
        || !in_array($target['role'], ['USER', 'ADMIN', 'SUPER_ADMIN'], true)
        || ($target['role'] === 'SUPER_ADMIN' && $actorRole !== 'SUPER_ADMIN')) {
        return [false, 'You are not authorized to change this account.', $target];
    }

    try {
        $pdo->beginTransaction();
        if ($target['role'] === 'SUPER_ADMIN' && $newStatus === 'INACTIVE') {
            requireSuperAdminSlot($pdo, $targetId);
        }
        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role IN ('USER', 'ADMIN', 'SUPER_ADMIN')");
        $stmt->execute([$newStatus, $targetId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('The account status was not changed.');
        }
        $pdo->commit();
        if ($newStatus === 'BLOCKED') {
            endUserPresence($pdo, $targetId, null);
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof SuperAdminLimitReached) {
            return [false, 'SUPER_ADMIN_LIMIT_UNBLOCK', $target];
        }
        return [false, $e->getMessage(), $target];
    }

    logAccountEvent($pdo, $newStatus === 'BLOCKED' ? 'ACCOUNT_BLOCKED' : 'ACCOUNT_UNBLOCKED', $target);
    return [true, $newStatus === 'BLOCKED' ? 'Account blocked successfully.' : 'Account unblocked successfully.', $target];
}
