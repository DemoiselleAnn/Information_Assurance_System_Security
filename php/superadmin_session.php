<?php
const SUPERADMIN_SESSION_GRACE_SECONDS = 60;
require_once __DIR__ . '/session_presence.php';
require_once __DIR__ . '/security_log_helpers.php';

function superAdminSessionHash(string $token): string {
    return hash('sha256', $token);
}

// Called while holding the existing singleton lock: ownership drives SA activity.
function syncSuperAdminActivity(PDO $pdo): void {
    $pdo->exec("UPDATE users u LEFT JOIN superadmin_session_state a ON a.id=1 AND a.user_id=u.id
        LEFT JOIN superadmin_sessions s ON s.token_hash=a.token_hash AND s.user_id=u.id
        SET u.status=CASE WHEN s.ended_at IS NULL AND s.token_hash IS NOT NULL AND u.first_login_required=0 THEN 'ACTIVE' ELSE 'INACTIVE' END
        WHERE u.role='SUPER_ADMIN' AND u.status IN ('ACTIVE','INACTIVE')");
}

function superAdminSessionState(PDO $pdo): array {
    settleSuperAdminTakeover($pdo);
    $token = (string)($_SESSION['superadmin_session_token'] ?? '');
    if ($token === '') return ['status' => 'missing'];

    $tokenHash = superAdminSessionHash($token);
    $stmt = $pdo->prepare(
        'SELECT s.superseded_at, s.superseded_expires_at, s.ended_at, s.user_id, u.role AS account_role, u.status AS account_status,
         a.token_hash AS authoritative_token_hash, a.pending_token_hash, UNIX_TIMESTAMP() AS server_now
         FROM superadmin_sessions s
         INNER JOIN users u ON u.id = s.user_id
         LEFT JOIN superadmin_session_state a ON a.id = 1
         WHERE s.token_hash = ? LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session || $session['account_role'] !== 'SUPER_ADMIN' || in_array($session['account_status'], ['BLOCKED', 'REJECTED', 'PENDING'], true)
        || (int)$session['user_id'] !== (int)($_SESSION['user_id'] ?? 0)) return ['status' => 'missing'];
    if ($session['ended_at'] !== null) return ['status' => $session['superseded_expires_at'] !== null ? 'expired' : 'missing', 'remaining_seconds' => 0];
    if (hash_equals((string)$session['authoritative_token_hash'], $tokenHash)) {
        return ['status' => 'active', 'remaining_seconds' => empty($session['pending_token_hash']) ? 0 : max(0, (int)$session['superseded_expires_at'] - (int)$session['server_now'])];
    }
    if (hash_equals((string)$session['pending_token_hash'], $tokenHash)) {
        $deadline = $pdo->query('SELECT s.superseded_expires_at FROM superadmin_session_state a JOIN superadmin_sessions s ON s.token_hash = a.token_hash WHERE a.id = 1')->fetchColumn();
        return ['status' => 'waiting', 'remaining_seconds' => max(0, (int)$deadline - (int)$session['server_now'])];
    }
    return ['status' => 'missing'];
}

class SuperAdminTakeoverUnavailable extends RuntimeException {
    public function __construct(public readonly int $remainingSeconds) {
        parent::__construct('Another Super Admin is currently active. You can request access again after the takeover cooldown expires.');
    }
}

// Caller holds the singleton lock. No client timestamp participates in decisions.
function settleLockedSuperAdminTakeover(PDO $pdo, array $state): array {
    $now = (int)$pdo->query('SELECT UNIX_TIMESTAMP()')->fetchColumn();
    $eligible = $pdo->prepare("SELECT s.*, u.role, u.status, u.first_login_required,
        UNIX_TIMESTAMP(s.started_at) AS started_epoch,
        (SELECT UNIX_TIMESTAMP(MAX(p.last_activity_at)) FROM user_sessions p WHERE p.user_id=s.user_id AND p.ended_at IS NULL) AS activity_epoch
        FROM superadmin_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? FOR UPDATE");
    $eligible->execute([$state['token_hash']]);
    $owner = $eligible->fetch(PDO::FETCH_ASSOC);
    $valid = static fn($s) => $s && $s['ended_at'] === null && $s['role'] === 'SUPER_ADMIN' && in_array($s['status'], ['ACTIVE', 'INACTIVE'], true) && !(int)$s['first_login_required']
        && max((int)$s['started_epoch'], (int)$s['activity_epoch']) >= $now - USER_SESSION_TIMEOUT_SECONDS;
    $ownerValid = $valid($owner);
    if (!empty($state['pending_token_hash'])) {
        $eligible->execute([$state['pending_token_hash']]);
        $pending = $eligible->fetch(PDO::FETCH_ASSOC);
        if (!$valid($pending)) {
            $pdo->exec('UPDATE superadmin_session_state SET pending_token_hash=NULL, pending_user_id=NULL WHERE id=1');
            $pdo->prepare('UPDATE superadmin_sessions SET superseded_at=NULL, superseded_expires_at=NULL WHERE token_hash=? AND ended_at IS NULL')->execute([$state['token_hash']]);
            $state['pending_token_hash'] = $state['pending_user_id'] = null;
        } elseif (!$ownerValid || $now >= (int)$owner['superseded_expires_at']) {
            if ($ownerValid) {
                $pdo->prepare('UPDATE superadmin_sessions SET ended_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([$state['token_hash']]);
                $pdo->prepare('UPDATE users SET takeover_cooldown_until=? WHERE id=?')->execute([$now + 600, $owner['user_id']]);
                // Revocation and activity cleanup commit together with ownership.
                $pdo->prepare('UPDATE user_sessions SET ended_at=CURRENT_TIMESTAMP WHERE user_id=? AND ended_at IS NULL')->execute([$owner['user_id']]);
                superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_AUTO_LOGOUT', 'Takeover: session displaced by account '.$pending['user_id'], (int)$owner['user_id'], true);
                superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_SUPERSEDED', 'Takeover: 10-minute account cooldown applied.', (int)$owner['user_id'], true);
            }
            $pdo->prepare('UPDATE superadmin_session_state SET token_hash=?, user_id=?, activated_at=CURRENT_TIMESTAMP, pending_token_hash=NULL, pending_user_id=NULL WHERE id=1')->execute([$state['pending_token_hash'], $pending['user_id']]);
            superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_STARTED', 'Takeover completed; active ownership granted.', (int)$pending['user_id'], true);
            $state['token_hash'] = $state['pending_token_hash'];
            $state['user_id'] = $pending['user_id'];
            $state['pending_token_hash'] = $state['pending_user_id'] = null;
            return $state;
        }
    }
    if (!$ownerValid) {
        if ($owner && $owner['ended_at'] === null) {
            $pdo->prepare('UPDATE superadmin_sessions SET ended_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([$state['token_hash']]);
            $pdo->prepare('UPDATE user_sessions SET ended_at=CURRENT_TIMESTAMP WHERE user_id=? AND ended_at IS NULL')->execute([$owner['user_id']]);
        }
        $pdo->exec('UPDATE superadmin_session_state SET token_hash=NULL, user_id=NULL, activated_at=NULL WHERE id=1');
        $state['token_hash'] = $state['user_id'] = null;
    }
    return $state;
}

function settleSuperAdminTakeover(PDO $pdo): void {
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $state = $pdo->query('SELECT * FROM superadmin_session_state WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        if ($state) settleLockedSuperAdminTakeover($pdo, $state);
        syncSuperAdminActivity($pdo);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function superAdminSessionLog(PDO $pdo, string $action, string $reason, ?int $actorId = null, bool $required = false): void {
    try {
        $userId = $actorId ?? (int)($_SESSION['user_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT username, first_name, middle_name, last_name, extension_name, role, id_number FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) return;
        $fullName = securityLogFullName($user);
        $pdo->prepare(
            "INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, session_key, actor_id_number, status, failure_reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'SUCCESS', ?)"
        )->execute([
            $userId,
            $user['username'],
            $fullName,
            $user['role'],
            $action,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $actorId === null ? ($_SESSION['security_session_key'] ?? null) : null,
            $user['id_number'],
            $reason,
        ]);
    } catch (Throwable $e) {
        error_log('Super Admin session log error: ' . get_class($e) . ' code=' . $e->getCode());
        if ($required) throw $e;
    }
}

function superAdminEndSession(PDO $pdo, string $message): void {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $displaced = superAdminSessionState($pdo)['status'] === 'expired';
    if ($userId > 0 && !$displaced) {
        endUserPresence($pdo, $userId, $_SESSION['security_session_key'] ?? null);
    }
    $token = (string)($_SESSION['superadmin_session_token'] ?? '');
    if ($token !== '') {
        $pdo->prepare('UPDATE superadmin_sessions SET ended_at = CURRENT_TIMESTAMP WHERE token_hash = ? AND ended_at IS NULL')
            ->execute([superAdminSessionHash($token)]);
    }
    if (!$displaced) superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_AUTO_LOGOUT', $message);
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['login_flash'] = [
        'error_message' => $message,
        'remaining_time' => 0,
        'attempt_count_display' => 0,
        'locked_username' => '',
    ];
    if ($displaced) $_SESSION['superadmin_takeover_ended'] = true;
}

function enforceSuperAdminSession(PDO $pdo, bool $stateChanging = false): array {
    if (strtoupper((string)($_SESSION['role'] ?? '')) !== 'SUPER_ADMIN') return ['status' => 'not_applicable'];
    $state = superAdminSessionState($pdo);
    if ($state['status'] === 'active') return $state;
    if ($state['status'] === 'waiting') {
        if ($stateChanging) { http_response_code(403); exit('Super Admin access is pending.'); }
        if (($_GET['page'] ?? '') === 'superadmin_waiting') return $state;
        header('Location: app.php?page=superadmin_waiting');
        exit;
    }
    if ($state['status'] === 'missing') {
        superAdminEndSession($pdo, 'Your Super Administrator session is no longer valid. Please log in again.');
        header('Location: app.php?page=login');
        exit;
    }
    if ($state['status'] === 'expired') {
        superAdminEndSession($pdo, 'Your Super Administrator session ended because another Super Administrator signed in.');
        header('Location: app.php?page=superadmin_session_ended');
        exit;
    }
    return $state;
}

function activateSuperAdminSession(PDO $pdo, int $userId): string {
    $token = bin2hex(random_bytes(32));
    $hash = superAdminSessionHash($token);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $pdo->exec('INSERT IGNORE INTO superadmin_session_state (id) VALUES (1)');
        $state = $pdo->query('SELECT * FROM superadmin_session_state WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        $state = settleLockedSuperAdminTakeover($pdo, $state);
        $stmt = $pdo->prepare('SELECT role, status, first_login_required, takeover_cooldown_until, UNIX_TIMESTAMP() AS server_now FROM users WHERE id=? FOR UPDATE');
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account || $account['role'] !== 'SUPER_ADMIN' || !in_array($account['status'], ['ACTIVE', 'INACTIVE'], true) || (int)$account['first_login_required']) {
            throw new RuntimeException('This account is no longer eligible for a Super Administrator session. Please log in again.');
        }
        $otherOwner = !empty($state['token_hash']) && (int)$state['user_id'] !== $userId;
        $remaining = max(0, (int)$account['takeover_cooldown_until'] - (int)$account['server_now']);
        if ($otherOwner && $remaining > 0) throw new SuperAdminTakeoverUnavailable($remaining);
        // A second browser must not replace an already waiting request or restart its deadline.
        if (!empty($state['pending_token_hash'])) throw new RuntimeException('A Super Admin takeover request is already pending. Please use the browser that requested access.');
        $pdo->prepare('INSERT INTO superadmin_sessions (token_hash,user_id,started_at) VALUES (?,?,CURRENT_TIMESTAMP)')->execute([$hash,$userId]);
        if ($otherOwner) {
            $pdo->prepare('UPDATE superadmin_sessions SET superseded_at=CURRENT_TIMESTAMP, superseded_expires_at=? WHERE token_hash=?')->execute([(int)$account['server_now'] + SUPERADMIN_SESSION_GRACE_SECONDS,$state['token_hash']]);
            $pdo->prepare('UPDATE superadmin_session_state SET pending_token_hash=?,pending_user_id=? WHERE id=1')->execute([$hash,$userId]);
            superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_SUPERSEDED', 'Takeover requested for active account '.$state['user_id'], $userId, true);
            superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_SUPERSEDED', 'Takeover countdown started: 60 seconds.', (int)$state['user_id'], true);
        } else {
            if (!empty($state['token_hash'])) $pdo->prepare('UPDATE superadmin_sessions SET ended_at=CURRENT_TIMESTAMP WHERE token_hash=?')->execute([$state['token_hash']]);
            $pdo->prepare('UPDATE superadmin_session_state SET token_hash=?,user_id=?,activated_at=CURRENT_TIMESTAMP WHERE id=1')->execute([$hash,$userId]);
        }
        syncSuperAdminActivity($pdo);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            if ($e instanceof SuperAdminTakeoverUnavailable) $pdo->commit();
            else $pdo->rollBack();
        }
        throw $e;
    }
    return $token;
}

function releaseSuperAdminSession(PDO $pdo): void {
    $token = (string)($_SESSION['superadmin_session_token'] ?? '');
    if ($token === '') return;
    $hash = superAdminSessionHash($token);
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) $pdo->beginTransaction();
    try {
        $state = $pdo->query('SELECT * FROM superadmin_session_state WHERE id=1 FOR UPDATE')->fetch(PDO::FETCH_ASSOC);
        // Settle an elapsed deadline before logout can make a forced displacement
        // look voluntary and evade the account cooldown.
        if ($state) $state = settleLockedSuperAdminTakeover($pdo, $state);
        $pdo->prepare('UPDATE superadmin_sessions SET ended_at=CURRENT_TIMESTAMP WHERE token_hash=? AND ended_at IS NULL')->execute([$hash]);
        if ($state && hash_equals((string)$state['pending_token_hash'], $hash)) {
            $pdo->exec('UPDATE superadmin_session_state SET pending_token_hash=NULL,pending_user_id=NULL WHERE id=1');
            $pdo->prepare('UPDATE superadmin_sessions SET superseded_at=NULL,superseded_expires_at=NULL WHERE token_hash=? AND ended_at IS NULL')->execute([$state['token_hash']]);
        } elseif ($state && hash_equals((string)$state['token_hash'], $hash)) {
            settleLockedSuperAdminTakeover($pdo, $state);
        }
        syncSuperAdminActivity($pdo);
        if ($ownsTransaction) $pdo->commit();
    } catch (Throwable $e) {
        if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
