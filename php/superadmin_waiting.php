<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin_session.php';
if (($_SESSION['role'] ?? '') !== 'SUPER_ADMIN') { header('Location: app.php?page=login'); exit; }
$waitingState = enforceSuperAdminSession($pdo);
if ($waitingState['status'] === 'active') { header('Location: app.php?page=account_management'); exit; }
header('Cache-Control: no-store');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Super Admin access pending</title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link rel="stylesheet" href="../css/superadmin_session.css"></head>
<body class="superadmin-access-page superadmin-pending-page"><main class="superadmin-access-card" aria-labelledby="pending-title">
<span class="pending-brand-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
<p class="pending-brand-name">FROSTLINE <span>SECURE RAIL SYSTEM</span></p>
<h1 id="pending-title">Super Admin Access Pending</h1><p class="pending-explanation">The current Super Admin keeps access until the countdown ends. Your dashboard will open when the server grants access.</p>
<div id="superadmin-session-warning" class="pending-countdown" data-status="waiting" data-remaining="<?= (int)$waitingState['remaining_seconds'] ?>"><p>Access begins in</p><b id="superadmin-session-countdown"><?= (int)$waitingState['remaining_seconds'] ?></b><span class="pending-countdown-unit">seconds</span></div>
<a class="pending-cancel" href="logout.php">Cancel and sign out</a><noscript><p>Refresh this page to check whether access has been granted.</p></noscript></main><script src="../js/superadmin_session.js"></script></body></html>
