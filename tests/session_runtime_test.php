<?php
// Each mode runs as its own request. All account/session-table fixtures roll back.
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../php/session_bootstrap.php';
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/../php/superadmin_session.php';
class SessionTestPDO extends PDO {
    public function beginTransaction(): bool { return $this->inTransaction() ? true : parent::beginTransaction(); }
    public function commit(): bool { return true; }
}
$pdo = new SessionTestPDO('mysql:host=localhost;dbname=cakey_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$pdo->exec('UPDATE superadmin_session_state SET token_hash=NULL,user_id=NULL,pending_token_hash=NULL,pending_user_id=NULL WHERE id=1');
$mode = $argv[1] ?? 'home';
$role = $argv[2] ?? 'guest';
$account = null;
$oldSessionId = session_id();
$_SERVER['REQUEST_METHOD'] = in_array($mode, ['login', 'ownership'], true) ? 'POST' : 'GET';
$_GET = ['page' => $mode === 'ownership' ? 'account_management' : $mode];
$_POST = [];
if ($role !== 'guest') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE role = ? ORDER BY id LIMIT 1');
    $stmt->execute([$role]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account) throw new RuntimeException('Missing fixture account for '.$role);
    $pdo->prepare("UPDATE users SET status = 'ACTIVE', first_login_required = 0 WHERE id = ?")->execute([$account['id']]);
    if ($mode === 'login') {
        $username = $role === 'USER' ? 'sessiontest.lastname' : 'SessionTestAdmin';
        $pdo->prepare('UPDATE users SET username = ?, password = ? WHERE id = ?')->execute([$username, password_hash('SessionTest123!', PASSWORD_DEFAULT), $account['id']]);
        $pdo->prepare('DELETE FROM login_attempts WHERE user_id = ?')->execute([$account['id']]);
        $_POST = ['username' => $username, 'password' => 'SessionTest123!'];
    } else {
        $_SESSION = ['user_id' => (int)$account['id'], 'username' => $account['username'], 'role' => $role, 'full_name' => 'Session test', 'security_session_key' => bin2hex(random_bytes(32))];
        startUserPresence($pdo, (int)$account['id'], $_SESSION['security_session_key']);
        if ($role === 'SUPER_ADMIN') {
            $_SESSION['superadmin_session_token'] = activateSuperAdminSession($pdo, (int)$account['id']);
            if ($mode === 'ownership') {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'SUPER_ADMIN' AND id <> ? LIMIT 1");
                $stmt->execute([$account['id']]);
                $otherId = (int)$stmt->fetchColumn();
                if (!$otherId) throw new RuntimeException('Second Super Admin is required for ownership test.');
                $pdo->prepare("UPDATE users SET status = 'INACTIVE',first_login_required=0,takeover_cooldown_until=NULL WHERE id = ?")->execute([$otherId]);
                activateSuperAdminSession($pdo, $otherId);
                if (superAdminSessionState($pdo)['status'] !== 'active') throw new RuntimeException('Owner lost access before deadline.');
                $pdo->exec('UPDATE superadmin_sessions s JOIN superadmin_session_state a ON a.token_hash=s.token_hash SET s.superseded_expires_at=UNIX_TIMESTAMP()-1 WHERE a.id=1');
            }
        }
    }
}
ob_start();
$captureLevel = ob_get_level();
register_shutdown_function(function () use ($pdo, $mode, $role, $account, $oldSessionId, $captureLevel) {
    $output = '';
    while (ob_get_level() >= $captureLevel) $output = ob_get_clean().$output;
    try {
        if (error_get_last() && in_array(error_get_last()['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) throw new RuntimeException('Fatal request error.');
        if (preg_match('/(?:Notice|Warning|Fatal error):/', $output)) throw new RuntimeException('Public PHP diagnostic in response.');
        if ($mode === 'ownership') {
            if (isset($_SESSION['user_id']) || empty($_SESSION['superadmin_takeover_ended']) || str_contains($output, '<h1>Account Management</h1>')) throw new RuntimeException('Expired session was allowed to change state.');
        } elseif ($mode === 'login') {
            if (($_SESSION['role'] ?? '') !== $role || (int)($_SESSION['user_id'] ?? 0) !== (int)$account['id'] || session_id() === $oldSessionId) throw new RuntimeException('Login/session regeneration failed.');
            if ($role === 'SUPER_ADMIN' && superAdminSessionState($pdo)['status'] !== 'active') throw new RuntimeException('Super Admin ownership failed.');
        } elseif ($mode === 'logout') {
            if (isset($_SESSION['user_id'], $_SESSION['role']) || empty($_SESSION['logout_success']) || session_id() === $oldSessionId) throw new RuntimeException('Logout did not clear and replace session.');
            if (is_file(session_save_path().'/sess_'.$oldSessionId)) throw new RuntimeException('Old authenticated session file survived logout.');
            if ($role === 'SUPER_ADMIN') {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM superadmin_session_state WHERE id = 1 AND user_id = ?');
                $stmt->execute([$account['id']]);
                if ((int)$stmt->fetchColumn() !== 0) throw new RuntimeException('Logout retained Super Admin ownership.');
            }
        } elseif ($mode === 'account_management') {
            if (!str_contains($output, '<h1>Account Management</h1>') || ($_SESSION['role'] ?? '') !== $role) throw new RuntimeException('Account Management failed to render.');
        } elseif ($mode === 'home') {
            if (!str_contains($output, 'frostline-home')) throw new RuntimeException('Homepage failed to render.');
            if ($role !== 'guest' && ($_SESSION['role'] ?? '') !== $role) throw new RuntimeException('Homepage lost authentication.');
        }
        echo 'PASS: '.$mode.' '.$role."; E_ALL enabled, no PHP diagnostics.\n";
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
});
if ($mode === 'logout') require __DIR__ . '/../php/logout.php';
else require __DIR__ . '/../php/app.php';
