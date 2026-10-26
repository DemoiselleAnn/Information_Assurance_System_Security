<?php
// Response/diagnostic helpers for the existing account-management action only.
final class DemotionValidationException extends RuntimeException {
    public function __construct(public string $reason, string $message, public int $httpStatus = 422) {
        parent::__construct($message);
    }
}

function isSuperAdminDemotionRequest(): bool {
    return ($_POST['action'] ?? null) === 'demote_super_admin'
        || ($_SERVER['HTTP_X_CAKEDECO_ACTION'] ?? '') === 'demote_super_admin';
}

function demotionRequestError(string $reason, string $message, int $status): never {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    // Never log passwords, cookies, session keys, CSRF tokens, or request bodies.
    error_log('CakeDeco demotion failure: ' . json_encode([
        'reason' => $reason, 'http_status' => $status,
        'actor_id' => (int)($_SESSION['user_id'] ?? 0),
        'target_id' => is_scalar($_POST['account_id'] ?? null) ? (int)$_POST['account_id'] : 0,
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    ]));
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(['success' => false, 'code' => $reason, 'error' => $message]);
        exit;
    }
    header('Location: app.php?page=account_management&error=' . rawurlencode($message));
    exit;
}
