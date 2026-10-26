<?php
require_once __DIR__ . '/session_bootstrap.php';

// Prevent back-forward cache for protected pages.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: app.php?page=login');
    exit();
}

if (($_SESSION['role'] ?? 'USER') !== 'ADMIN') {
    if (($_SESSION['role'] ?? 'USER') === 'SUPER_ADMIN') {
        header('Location: app.php?page=account_management');
    } else {
        header('Location: app.php?page=login');
    }
    exit();
}
header('Location: app.php?page=account_management');
exit();
