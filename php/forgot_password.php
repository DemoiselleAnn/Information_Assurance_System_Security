<?php 
require_once __DIR__ . '/session_bootstrap.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Frostline Secure Rail System - Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/forgot_password.css?v=<?= filemtime(__DIR__ . '/../css/forgot_password.css') ?>">
</head>
<body class="main-log">
    <header class="main-header">
        <div class="header-content">
            <div class="logo-container">
                <span class="logo-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
                <div class="logo-copy">
                    <h1 class="logo">FROSTLINE</h1>
                    <span>SECURE RAIL SYSTEM</span>
                </div>
            </div>
            <nav class="nav-links">
                <a href="../php/app.php?page=home">Home</a>
                <a href="../php/app.php?page=login" id="loginLink">Login</a>
            </nav>
        </div>
    </header>

    <div class="forgot-form">
        <div class="form-box">
            <h1>Forgot Password</h1>
            <p class="form-description">Enter your ID No. or Username to continue.</p>
            
            <?php if (isset($_SESSION['forgot_error'])): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($_SESSION['forgot_error']); ?>
                </div>
                <?php unset($_SESSION['forgot_error']); ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['forgot_success'])): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($_SESSION['forgot_success']); ?>
                </div>
                <?php unset($_SESSION['forgot_success']); ?>
            <?php endif; ?>

            <!-- Step 1: Account Verification -->
            <div id="step1" class="form-step">
                <form id="idVerifyForm" method="post" action="verify_id_forgot.php" novalidate>
                    <div class="form-content">
                        <div class="input-group">
                            <label for="identifier">ID No. or Username</label>
                            <input type="text" id="identifier" name="identifier" placeholder="2023-0000 or name.name" maxlength="40" autocomplete="off" aria-describedby="identifier_error" required>
                            <span class="error" id="identifier_error" aria-live="polite"></span>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button class="forgot-btn" type="submit" id="verifyIdBtn">Continue</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
    <script src="js_gate.php?file=forgot_password.js&amp;v=<?= filemtime(__DIR__ . '/../js/forgot_password.js') ?>"></script>
    <script>
    // Rewrite current history entry to Login before navigating (trap Back)
    document.addEventListener('DOMContentLoaded', function() {
        var l1 = document.getElementById('loginLink');
        if (l1) l1.addEventListener('click', function(e){ e.preventDefault(); var url = '../php/app.php?page=login'; try { history.replaceState(null, '', url); } catch(e){} window.location.href = url; });
        // If a Login stay flag exists, redirect back to Login (do not affect normal navigation)
        try {
            var flag = sessionStorage.getItem('stayOnLogin') === '1';
            if (flag) {
                window.location.replace('app.php?page=login');
            }
        } catch (e) {}
    });
    </script>
    <footer class="frostline-footer">
        <span>FROSTLINE SECURE RAIL SYSTEM</span>
        <span>&copy; <?= date('Y') ?> Frostline</span>
    </footer>
</body>
</html>
