<?php
require_once __DIR__ . '/session_bootstrap.php';

if (strtoupper($_SESSION['role'] ?? '') === 'USER') {
    $_GET['view'] = 'profile';
    require __DIR__ . '/customer_dashboard.php';
    return;
}

require __DIR__ . '/superadmin_shell.php';
