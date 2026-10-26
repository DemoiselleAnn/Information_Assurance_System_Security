<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin_session.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
    if (($_SESSION['role'] ?? '') !== 'SUPER_ADMIN') {
        http_response_code(401);
        echo json_encode(['status' => !empty($_SESSION['superadmin_takeover_ended']) ? 'expired' : 'missing', 'redirect' => 'app.php?page=login']);
        exit;
    }
    $state = superAdminSessionState($pdo);
    if (in_array($state['status'], ['expired','missing'], true)) {
        superAdminEndSession($pdo, 'Your Super Admin session has ended. Please log in again.');
    }
    session_write_close();
    echo json_encode(['status' => $state['status'], 'remaining_seconds' => (int)($state['remaining_seconds'] ?? 0), 'redirect' => 'app.php?page=login']);
} catch (Throwable $e) {
    error_log('Super Admin session status failed: '.get_class($e));
    http_response_code(503);
    echo json_encode(['status' => 'unavailable']);
}