<?php
// These helpers operate inside the existing first-login setup transaction.
class FirstLoginActivationUnavailable extends RuntimeException {}

function lockFirstLoginAccountForSetup(PDO $pdo, int $userId, string $role): void {
    if (!$pdo->inTransaction()) throw new LogicException('Setup requires a transaction.');
    $lock = $pdo->prepare(
        "SELECT id FROM users
         WHERE id = ? AND role = ? AND first_login_required = 1 AND status <> 'BLOCKED'
         FOR UPDATE"
    );
    $lock->execute([$userId, $role]);
    if (!$lock->fetchColumn()) throw new FirstLoginActivationUnavailable('Setup is no longer available.');
}

function completeFirstLoginAccount(PDO $pdo, int $userId, string $role, string $passwordHash): void {
    if (!$pdo->inTransaction()) throw new LogicException('Setup requires a transaction.');
    // This separate statement runs AFTER the user lock is acquired and AFTER the
    // answers/challenge writes. Its server timestamp is fresh even after a lock wait.
    $update = $pdo->prepare(
        "UPDATE users
         SET password = ?, first_login_required = 0, updated_at = CURRENT_TIMESTAMP
         WHERE id = ? AND role = ? AND status <> 'BLOCKED' AND first_login_required = 1
           AND (role NOT IN ('ADMIN', 'SUPER_ADMIN')
                OR (temporary_credential_expires_at IS NOT NULL
                    AND temporary_credential_expires_at > CURRENT_TIMESTAMP))"
    );
    $update->execute([$passwordHash, $userId, $role]);
    if ($update->rowCount() !== 1) {
        throw new FirstLoginActivationUnavailable('Setup is no longer available or the initial credentials have expired.');
    }
}
