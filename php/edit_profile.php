<?php
// Compatibility entry only: all legacy rendering and writes are retired.
require_once __DIR__ . '/session_bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('This profile endpoint has been retired. Open My Profile to make changes.');
}
if (empty($_SESSION['user_id'])) {
    header('Location: app.php?page=login'); exit;
}
// The destination rechecks active status, setup, role, and session authorization.
header('Cache-Control: no-store');
header('Location: app.php?page=my_profile&edit=1');
exit;
