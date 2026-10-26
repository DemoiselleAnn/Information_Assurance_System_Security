<?php
// Only the Administrators tab uses this transition; shared account workflows stay intact.
function transitionAdministratorStatus(PDO $pdo, int $targetId, string $action): array {
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    if (!$actorId || ($_SESSION['role'] ?? '') !== 'SUPER_ADMIN'
        || !in_array($action, ['block', 'unblock'], true) || $targetId === $actorId) {
        return [false, 'You cannot change this administrative account.'];
    }
    try {
        $pdo->beginTransaction();
        // Use the existing limit lock order before locking a possible Super Admin target.
        if ($action === 'unblock') $usableCount = usableSuperAdminCount($pdo);
        $stmt = $pdo->prepare('SELECT id, id_number, username, first_name, middle_name, last_name, role, status, first_login_required FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$targetId]);
        $target = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$target || !in_array($target['role'], ['ADMIN', 'SUPER_ADMIN'], true) || $target['first_login_required']) {
            throw new DomainException('Completed administrative account not found.');
        }
        $oldStatus = $target['status'];
        if ($action === 'unblock' && $oldStatus !== 'BLOCKED') {
            throw new DomainException('This account is no longer blocked. Refresh the page.');
        }
        if ($action === 'block' && !in_array($oldStatus, ['ACTIVE', 'INACTIVE'], true)) {
            throw new DomainException('Only an online or offline login-eligible account can be blocked. Refresh the page.');
        }
        if ($action === 'unblock' && $target['role'] === 'SUPER_ADMIN' && $usableCount >= 2) {
            throw new SuperAdminLimitReached('SUPER_ADMIN_LIMIT_UNBLOCK');
        }
        $newStatus = $action === 'block' ? 'BLOCKED' : 'INACTIVE';
        $update = $pdo->prepare('UPDATE users SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ? AND role = ? AND status = ? AND first_login_required = 0');
        $update->execute([$newStatus, $targetId, $target['role'], $oldStatus]);
        if ($update->rowCount() !== 1) throw new DomainException('The account changed before this action completed. Refresh the page.');
        if ($action === 'block') endUserPresence($pdo, $targetId, null);
        // The status, session revocation and audit record must commit together.
        logManagementEvent($pdo, $action === 'block' ? 'ADMIN_BLOCKED' : 'ADMIN_UNBLOCKED', $target, null, true);
        $pdo->commit();
        return [true, 'Administrative account ' . ($action === 'block' ? 'blocked' : 'unblocked') . ' successfully.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof SuperAdminLimitReached) return [false, 'SUPER_ADMIN_LIMIT_UNBLOCK'];
        if ($e instanceof DomainException) return [false, $e->getMessage()];
        error_log('Administrator status transition failed: ' . get_class($e));
        return [false, 'The account status could not be changed. Please try again.'];
    }
}
