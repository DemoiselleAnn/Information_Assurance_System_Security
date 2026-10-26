<?php
require_once __DIR__ . '/session_bootstrap.php';
header('Cache-Control: no-store');
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta http-equiv="refresh" content="3;url=app.php?page=login"><title>Time's Up</title><link rel="stylesheet" href="../css/superadmin_session.css"></head>
<body class="superadmin-access-page"><main class="superadmin-access-card" role="alertdialog" aria-modal="true" aria-labelledby="expired-title"><h1 id="expired-title">Time's Up</h1><p>Your Super Admin session has ended because another Super Admin has taken over the active session.</p><p>Returning to Login…</p></main></body></html>
