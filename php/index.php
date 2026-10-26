<?php
require_once __DIR__ . '/session_bootstrap.php';

$show_login_notification = isset($_SESSION['login_success']);
$show_logout_notification = isset($_SESSION['logout_success']);
unset($_SESSION['login_success'], $_SESSION['logout_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frostline Secure Rail System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/index.css?v=<?= filemtime(__DIR__ . '/../css/index.css') ?>">
    <link rel="stylesheet" href="../css/public_header.css?v=<?= filemtime(__DIR__ . '/../css/public_header.css') ?>">
    <script src="../js/prevent_placeholder_links.js?v=<?= filemtime(__DIR__ . '/../js/prevent_placeholder_links.js') ?>" defer></script>
</head>
<body class="frostline-home">
    <header class="frostline-header public-header-alignment">
        <a class="frostline-brand" href="app.php?page=home" aria-label="Frostline home">
            <span class="brand-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
            <span class="brand-copy">
                <strong>FROSTLINE</strong>
                <small>SECURE RAIL SYSTEM</small>
            </span>
        </a>
        <nav class="frostline-nav" aria-label="Main navigation">
            <a href="app.php?page=login">Login</a>
            <a class="nav-register" href="app.php?page=register">Register</a>
        </nav>
    </header>

    <?php if ($show_login_notification || $show_logout_notification): ?>
        <div class="frostline-notification" role="status">
            <i class="fas fa-circle-check" aria-hidden="true"></i>
            <span><?= $show_login_notification ? 'Welcome back to Frostline.' : 'You have been safely signed out.' ?></span>
        </div>
    <?php endif; ?>

    <main class="landing-main">
        <section class="hero-section" aria-labelledby="hero-title">
            <div class="hero-wash" aria-hidden="true"></div>
            <div class="section-content">
                <div class="hero-details">
                    <span class="hero-kicker"><i class="fas fa-route" aria-hidden="true"></i> NORTHERN RAIL NETWORK</span>
                    <h1 class="title" id="hero-title">Welcome Aboard Frostline</h1>
                    <h2 class="subtitle">Your journey begins here.</h2>
                </div>
            </div>
        </section>
    </main>

    <footer class="frostline-footer">
        <span>FROSTLINE SECURE RAIL SYSTEM</span>
        <span>© 2026 Frostline</span>
    </footer>
</body>
</html>
