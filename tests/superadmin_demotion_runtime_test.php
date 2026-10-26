<?php
// Exercise the real database queries while rolling back every fixture and change.
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/../php/account_status.php';
require_once __DIR__ . '/../php/authorization.php';
require_once __DIR__ . '/../php/superadmin_session.php';
ini_set('session.save_path', sys_get_temp_dir());
session_start();
class DemotionTestPDO extends PDO {
    public function beginTransaction(): bool { return $this->inTransaction() ? true : parent::beginTransaction(); }
    public function commit(): bool { return true; } // Keep all changes inside the test rollback.
}
$test = new DemotionTestPDO('mysql:host=localhost;dbname=cakey_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$ids = $test->query("SELECT id FROM users WHERE role = 'SUPER_ADMIN' ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
if (count($ids) !== 2) throw new RuntimeException('This test requires two existing Super Admin accounts.');
[$actorId, $targetId] = array_map('intval', $ids);
function checkDemotion(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function demotionFixture(PDO $db, int $actorId, int $targetId): void {
    $db->beginTransaction();
    $db->prepare("UPDATE users SET status = 'INACTIVE' WHERE id = ?")->execute([$targetId]);
    $token = bin2hex(random_bytes(32));
    $_SESSION = ['user_id' => $actorId, 'role' => 'SUPER_ADMIN', 'superadmin_session_token' => $token, 'security_session_key' => 'demotion-runtime-test'];
    $db->prepare("UPDATE users SET password = ?, status = 'ACTIVE', first_login_required = 0 WHERE id = ?")
        ->execute([password_hash('DemotionTest123!', PASSWORD_DEFAULT), $actorId]);
    $db->prepare('INSERT INTO superadmin_sessions (token_hash, user_id, started_at) VALUES (?, ?, NOW())')->execute([superAdminSessionHash($token), $actorId]);
    $db->prepare('UPDATE superadmin_session_state SET token_hash = ?, user_id = ?, activated_at = NOW() WHERE id = 1')->execute([superAdminSessionHash($token), $actorId]);
    $db->prepare('INSERT INTO superadmin_sessions (token_hash, user_id, started_at) VALUES (?, ?, NOW())')->execute([superAdminSessionHash('target-test-token'), $targetId]);
    $db->prepare('INSERT INTO user_sessions (session_hash, user_id, last_activity_at) VALUES (?, ?, NOW())')->execute([sessionPresenceHash('target-test-presence'), $targetId]);
    $db->prepare("INSERT INTO user_permissions (user_id, permission, enabled) VALUES (?, 'MANAGE_ROLES', 1) ON DUPLICATE KEY UPDATE enabled = 1")->execute([$targetId]);
}
// Optional modes cover the actual JSON endpoint as well as its transactional helper.
$endpointCases = ['success' => [null, 200], 'password' => ['invalid_password', 422], 'csrf' => ['invalid_csrf', 419], 'csrf-array' => ['invalid_csrf', 419], 'missing' => ['missing_target', 422], 'invalid-target' => ['invalid_target', 422], 'self' => ['invalid_target', 422], 'actor' => ['invalid_actor', 403], 'expired' => ['unauthenticated_session', 401], 'invalid-role' => ['invalid_role_transition', 422], 'method' => ['invalid_request', 405]];
$endpointCase = preg_replace('/^--endpoint-(?:routed-)?/', '', $argv[1] ?? '');
if (str_starts_with($argv[1] ?? '', '--endpoint-') && isset($endpointCases[$endpointCase])) {
    $expectSuccess = $endpointCase === 'success';
    [$expectedCode, $expectedStatus] = $endpointCases[$endpointCase];
    demotionFixture($test, $actorId, $targetId);
    $_SESSION['account_management_csrf'] = 'endpoint-test-token';
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    $_SERVER['HTTP_X_CAKEDECO_ACTION'] = 'demote_super_admin';
    $_GET = ['page' => 'account_management'];
    $_POST = ['action' => 'demote_super_admin', 'account_id' => $targetId, 'csrf_token' => 'endpoint-test-token', 'current_password' => $expectSuccess ? 'DemotionTest123!' : 'wrong'];
    if ($endpointCase !== 'password') $_POST['current_password'] = 'DemotionTest123!';
    if ($endpointCase === 'csrf') $_POST['csrf_token'] = 'wrong';
    if ($endpointCase === 'csrf-array') $_POST['csrf_token'] = ['wrong'];
    if ($endpointCase === 'missing') unset($_POST['account_id']);
    if ($endpointCase === 'invalid-target') $_POST['account_id'] = -1 + PHP_INT_MAX;
    if ($endpointCase === 'self') $_POST['account_id'] = $actorId;
    if ($endpointCase === 'actor') $_SESSION['role'] = 'ADMIN';
    if ($endpointCase === 'expired') unset($_SESSION['user_id']);
    if ($endpointCase === 'method') $_SERVER['REQUEST_METHOD'] = 'GET';
    if ($endpointCase === 'invalid-role') $test->prepare("UPDATE users SET role = 'ADMIN' WHERE id = ?")->execute([$targetId]);
    $baselineRole = getAccount($test, $targetId)['role'];
    $stmt = $test->prepare("SELECT COUNT(*) FROM security_logs WHERE user_id = ? AND target_user_id = ? AND action = 'PERMISSIONS_CHANGED'");
    $stmt->execute([$actorId, $targetId]);
    $baselineLogs = (int)$stmt->fetchColumn();
    $pdo = $test;
    ob_start();
    register_shutdown_function(function () use ($test, $actorId, $targetId, $baselineLogs, $expectSuccess, $expectedCode, $expectedStatus, $baselineRole, $endpointCase) {
        $output = ob_get_clean();
        try {
            $json = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            checkDemotion($json['success'] === $expectSuccess, 'Unexpected endpoint response.');
            if (!$expectSuccess) {
                checkDemotion(($json['code'] ?? '') === $expectedCode && http_response_code() === $expectedStatus, 'Unexpected error classification/status.');
            }
            $expectedRole = $expectSuccess ? 'ADMIN' : ($test->inTransaction() ? $baselineRole : 'SUPER_ADMIN');
            checkDemotion(getAccount($test, $targetId)['role'] === $expectedRole, 'Unexpected target role.');
            $stmt = $test->prepare("SELECT COUNT(*) FROM security_logs WHERE user_id = ? AND target_user_id = ? AND action = 'PERMISSIONS_CHANGED'");
            $stmt->execute([$actorId, $targetId]);
            checkDemotion((int)$stmt->fetchColumn() === $baselineLogs + ($expectSuccess ? 1 : 0), 'Unexpected audit count.');
            if ($expectSuccess) checkDemotion((int)$_SESSION['user_id'] === $actorId && $_SESSION['role'] === 'SUPER_ADMIN', 'Actor logged out.');
            echo 'PASS: JSON endpoint '.$endpointCase.", response classification, role and audit count.\n";
        } finally {
            if ($test->inTransaction()) $test->rollBack();
            $_SESSION = [];
            session_destroy();
        }
    });
    if (str_contains($argv[1], 'routed')) require __DIR__ . '/../php/app.php';
    else require __DIR__ . '/../php/account_management.php';
    exit;
}
try {
    foreach (['self', 'wrong_password', 'admin_actor', 'user_actor', 'stale_session', 'non_super_target', 'database_actor_role'] as $scenario) {
        demotionFixture($test, $actorId, $targetId);
        $selectedTarget = $scenario === 'self' ? $actorId : $targetId;
        if ($scenario === 'admin_actor') $_SESSION['role'] = 'ADMIN';
        if ($scenario === 'user_actor') $_SESSION['role'] = 'USER';
        if ($scenario === 'stale_session') $_SESSION['superadmin_session_token'] = 'stale';
        if ($scenario === 'non_super_target') $test->prepare("UPDATE users SET role = 'ADMIN' WHERE id = ?")->execute([$targetId]);
        if ($scenario === 'database_actor_role') $test->prepare("UPDATE users SET role = 'ADMIN' WHERE id = ?")->execute([$actorId]);
        $denied = false;
        try { demoteOtherSuperAdmin($test, $selectedTarget, $scenario === 'wrong_password' ? 'wrong' : 'DemotionTest123!'); }
        catch (RuntimeException $e) { $denied = true; }
        checkDemotion($denied, 'Unauthorized action accepted: '.$scenario);
        if ($test->inTransaction()) $test->rollBack();
    }
    demotionFixture($test, $actorId, $targetId);
    $beforeCount = usableSuperAdminCount($test);
    $stmt = $test->prepare("SELECT COUNT(*) FROM security_logs WHERE user_id = ? AND target_user_id = ? AND action = 'PERMISSIONS_CHANGED'");
    $stmt->execute([$actorId, $targetId]);
    $beforeLogs = (int)$stmt->fetchColumn();
    demoteOtherSuperAdmin($test, $targetId, 'DemotionTest123!');
    $stmt->execute([$actorId, $targetId]);
    checkDemotion((int)$stmt->fetchColumn() === $beforeLogs + 1, 'Success did not create exactly one audit entry.');
    checkDemotion(getAccount($test, $targetId)['role'] === 'ADMIN', 'Database role was not updated.');
    checkDemotion(usableSuperAdminCount($test) === $beforeCount - 1, 'Demoted account still counted.');
    checkDemotion(!hasPermission($test, $targetId, 'MANAGE_ROLES'), 'Super Admin override survived.');
    checkDemotion(hasPermission($test, $targetId, 'MANAGE_USERS'), 'Admin defaults were not applied.');
    checkDemotion(superAdminSessionState($test)['status'] === 'active', 'Actor session was revoked.');
    $pdo = $test;
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    ob_start();
    require __DIR__ . '/../php/account_management.php';
    $accountHtml = ob_get_clean();
    preg_match('/<tr data-account-id="'.$targetId.'">(.*?)<\/tr>/s', $accountHtml, $renderedRow);
    checkDemotion(isset($renderedRow[1]) && str_contains($renderedRow[1], '<span class="role-badge data-role-text">ADMIN</span>'), 'Account table did not display Admin.');
    $stmt = $test->prepare('SELECT COUNT(*) FROM superadmin_sessions WHERE user_id = ? AND ended_at IS NULL');
    $stmt->execute([$targetId]);
    checkDemotion((int)$stmt->fetchColumn() === 0, 'Target Super Admin sessions survived.');
    $stmt = $test->prepare('SELECT COUNT(*) FROM user_sessions WHERE user_id = ? AND ended_at IS NULL');
    $stmt->execute([$targetId]);
    checkDemotion((int)$stmt->fetchColumn() === 0, 'Target presence sessions survived.');
    $stmt = $test->prepare("SELECT failure_reason, session_key FROM security_logs WHERE user_id = ? AND target_user_id = ? AND action = 'PERMISSIONS_CHANGED' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$actorId, $targetId]);
    $log = $stmt->fetch(PDO::FETCH_ASSOC);
    checkDemotion($log && str_contains($log['failure_reason'], 'Previous role: SUPER_ADMIN; New role: ADMIN') && $log['session_key'] === 'demotion-runtime-test', 'Audit details missing.');
    $_SESSION['user_id'] = $targetId;
    $_SESSION['superadmin_session_token'] = 'target-test-token';
    checkDemotion(superAdminSessionState($test)['status'] === 'missing', 'Old target token remained authorized.');
    $test->rollBack();
    echo "PASS: demotion, Admin defaults, account count, audit, session revocation, actor session preservation, and seven denied scenarios. All database changes rolled back.\n";
} finally {
    if ($test->inTransaction()) $test->rollBack();
    $_SESSION = [];
    session_destroy();
}
