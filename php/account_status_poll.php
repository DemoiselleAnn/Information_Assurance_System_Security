<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/authorization.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$role = strtoupper((string)($_SESSION['role'] ?? ''));
$actorStmt = $pdo->prepare('SELECT role, status, first_login_required FROM users WHERE id=? LIMIT 1');
$actorStmt->execute([(int)($_SESSION['user_id'] ?? 0)]);
$actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
if (!$actor || $actor['status'] === 'BLOCKED' || (int)$actor['first_login_required']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}
$role = $actor['role'];
if (!isset($_SESSION['user_id']) || !in_array($role, ['ADMIN', 'SUPER_ADMIN'], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}
if ($role !== 'SUPER_ADMIN'
    && !hasPermission($pdo, (int)$_SESSION['user_id'], 'MANAGE_USERS')
    && !hasPermission($pdo, (int)$_SESSION['user_id'], 'APPROVE_REGISTRATIONS')
    && !hasPermission($pdo, (int)$_SESSION['user_id'], 'BLOCK_ACCOUNTS')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}
if ($role === 'SUPER_ADMIN') {
    require_once __DIR__ . '/superadmin_session.php';
    if (superAdminSessionState($pdo)['status'] !== 'active') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Super Admin access is unavailable']);
        exit;
    }
}

$rawIds = trim((string)($_GET['ids'] ?? ''));
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $rawIds)), static fn(int $id): bool => $id > 0)));
if (!$ids) {
    echo json_encode(['success' => true, 'accounts' => []]);
    exit;
}
$ids = array_slice($ids, 0, 100);
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $pdo->prepare("SELECT id, status FROM users WHERE id IN ($placeholders) AND (role NOT IN ('ADMIN', 'SUPER_ADMIN') OR first_login_required = 0)");
$stmt->execute($ids);
$accounts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $account) {
    $accounts[(string)(int)$account['id']] = ['id' => (int)$account['id'], 'status' => (string)$account['status']];
}
echo json_encode(['success' => true, 'accounts' => $accounts]);
