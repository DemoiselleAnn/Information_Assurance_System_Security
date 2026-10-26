<?php
const USER_SESSION_TIMEOUT_SECONDS = 1800;

function sessionPresenceHash(string $sessionKey): string {
    return hash('sha256', $sessionKey);
}

function cleanupStaleUserSessions(PDO $pdo): void {
    $pdo->exec(
        "UPDATE users u
         INNER JOIN user_sessions s ON s.user_id = u.id
         SET u.status = 'INACTIVE', s.ended_at = CURRENT_TIMESTAMP
         WHERE s.ended_at IS NULL
           AND s.last_activity_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1800 SECOND)
           AND u.status <> 'BLOCKED'"
    );
}

function startUserPresence(PDO $pdo, int $userId, string $sessionKey): void {
    if (!canPublishUserPresence($pdo, $userId)) return;
    cleanupStaleUserSessions($pdo);
    $sessionHash = sessionPresenceHash($sessionKey);
    $pdo->prepare(
        'INSERT INTO user_sessions (session_hash, user_id, last_activity_at)
         VALUES (?, ?, CURRENT_TIMESTAMP)
         ON DUPLICATE KEY UPDATE last_activity_at = CURRENT_TIMESTAMP, ended_at = NULL'
    )->execute([$sessionHash, $userId]);
    publishActiveUserStatus($pdo, $userId);
}

function touchUserPresence(PDO $pdo, int $userId, string $sessionKey): bool {
    if (!canPublishUserPresence($pdo, $userId)) return false;
    cleanupStaleUserSessions($pdo);
    $sessionHash = sessionPresenceHash($sessionKey);
    $stmt = $pdo->prepare(
        'UPDATE user_sessions s
         INNER JOIN users u ON u.id = s.user_id
         SET s.last_activity_at = CURRENT_TIMESTAMP
         WHERE s.session_hash = ? AND s.user_id = ? AND s.ended_at IS NULL
           AND u.status <> \'BLOCKED\''
    );
    $stmt->execute([$sessionHash, $userId]);
    if ($stmt->rowCount() > 0) {
        publishActiveUserStatus($pdo, $userId);
        return true;
    }
    return false;
}

function canPublishUserPresence(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id=?');
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() !== 'SUPER_ADMIN') return true;
    require_once __DIR__ . '/superadmin_session.php';
    return (int)($_SESSION['user_id'] ?? 0) === $userId && superAdminSessionState($pdo)['status'] === 'active';
}

function publishActiveUserStatus(PDO $pdo, int $userId): void {
    // Recheck ownership in the write itself so revocation between the presence
    // check and this update cannot restore an old Super Admin's ACTIVE flag.
    $tokenHash = hash('sha256', (string)($_SESSION['superadmin_session_token'] ?? ''));
    $pdo->prepare("UPDATE users SET status='ACTIVE' WHERE id=? AND status <> 'BLOCKED'
        AND (role <> 'SUPER_ADMIN' OR EXISTS (SELECT 1 FROM superadmin_session_state a
        JOIN superadmin_sessions s ON s.token_hash=a.token_hash WHERE a.id=1 AND a.user_id=users.id
        AND a.token_hash=? AND s.ended_at IS NULL))")->execute([$userId, $tokenHash]);
}

function endUserPresence(PDO $pdo, int $userId, ?string $sessionKey): void {
    if ($sessionKey !== null && $sessionKey !== '') {
        $pdo->prepare(
            'UPDATE user_sessions SET ended_at = CURRENT_TIMESTAMP
             WHERE user_id = ? AND session_hash = ? AND ended_at IS NULL'
        )->execute([$userId, sessionPresenceHash($sessionKey)]);
    } else {
        $pdo->prepare(
            'UPDATE user_sessions SET ended_at = CURRENT_TIMESTAMP
             WHERE user_id = ? AND ended_at IS NULL'
        )->execute([$userId]);
    }
    $active = $pdo->prepare(
        'SELECT 1 FROM user_sessions WHERE user_id = ? AND ended_at IS NULL
         AND last_activity_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 1800 SECOND) LIMIT 1'
    );
    $active->execute([$userId]);
    if (!$active->fetchColumn()) {
        $pdo->prepare("UPDATE users SET status = 'INACTIVE' WHERE id = ? AND status <> 'BLOCKED'")
            ->execute([$userId]);
    }
}
