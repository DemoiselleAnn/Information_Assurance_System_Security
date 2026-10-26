<?php 
require_once __DIR__ . '/session_bootstrap.php';

// Check if account is locked
$remaining_time = 0;
$is_locked = false;
if (isset($_SESSION['lock_state'])) {
    $state = $_SESSION['lock_state'];
    $lockUntil = isset($state['until']) ? (int)$state['until'] : 0;
    if ($lockUntil > time()) {
        $remaining_time = $lockUntil - time();
        $is_locked = true;
    } else {
        unset($_SESSION['lock_state']);
    }
}

// Check if we're on the home page
$current_page = isset($_GET['page']) ? strtolower(trim($_GET['page'])) : 'home';
$is_home_page = ($current_page === 'home' || $current_page === 'homepage');
?>
<header class="main-header">
    <div class="header-content">
        <div class="logo-container">
            <img src="../GalleryImgs/cakelogo.png" alt="Cakes and Crafts Logo" class="logo-icon">
            <h1 class="logo">Cakes and Crafts</h1>
        </div>

        <nav class="nav-links">
            <?php if (!$is_home_page): ?>
                <a href="../php/app.php?page=home">Home</a>
            <?php endif; ?>
            <?php if (!empty($_SESSION['user_id'])): ?>
                <a href="../php/app.php?page=profile">Profile</a>
                <a href="../php/logout.php">Logout</a>
            <?php else: ?>
                <a href="../php/app.php?page=register" id="headerRegisterLink" <?= $is_locked ? 'style="pointer-events:none;opacity:0.5;cursor:not-allowed"' : '' ?>>Register</a>
                <a href="../php/app.php?page=login" id="loginLink">Login</a>
            <?php endif; ?>
        </nav>
    </div>

</header>
<?php if ($is_locked && $remaining_time > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const headerRegisterLink = document.getElementById('headerRegisterLink');
    if (headerRegisterLink) {
        headerRegisterLink.style.pointerEvents = 'none';
        headerRegisterLink.style.opacity = '0.5';
        headerRegisterLink.style.cursor = 'not-allowed';
        
        // Countdown timer to re-enable
        let timeLeft = <?= $remaining_time ?>;
        const countdown = setInterval(() => {
            timeLeft--;
            if (timeLeft <= 0) {
                clearInterval(countdown);
                headerRegisterLink.style.pointerEvents = 'auto';
                headerRegisterLink.style.opacity = '1';
                headerRegisterLink.style.cursor = 'pointer';
            }
        }, 1000);
    }
});
</script>
<?php endif; ?>
