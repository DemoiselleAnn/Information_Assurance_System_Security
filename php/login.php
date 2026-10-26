<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin_session.php';
require_once __DIR__ . '/temporary_credentials.php';
require_once __DIR__ . '/session_presence.php';
require_once __DIR__ . '/security_log_helpers.php';

// Prevent back-forward cache and cached reloads on the login page
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$error_message = '';
$success_message = '';
$remaining_time = 0;
$failed_login_attempts = max(0, (int)($_SESSION['failed_login_attempts'] ?? 0));
$attempt_count_display = $failed_login_attempts;
$locked_username = '';
$takeover_remaining = 0;

// Flash message loader for PRG pattern (GET after POST)
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['login_flash'])) {
    $flash = $_SESSION['login_flash'];
    $error_message = isset($flash['error_message']) ? (string)$flash['error_message'] : '';
    $remaining_time = isset($flash['remaining_time']) ? (int)$flash['remaining_time'] : 0;
    $attempt_count_display = max(
        $failed_login_attempts,
        isset($flash['attempt_count_display']) ? (int)$flash['attempt_count_display'] : 0
    );
    $locked_username = isset($flash['locked_username']) ? (string)$flash['locked_username'] : '';
    $takeover_remaining = max(0, (int)($flash['takeover_remaining'] ?? 0));
    unset($_SESSION['login_flash']);
}

// Registration success banner
if (isset($_SESSION['success_message'])) {
    $success_message = (string)$_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Password change success banner
if (isset($_SESSION['password_change_success'])) {
    $success_message = (string)$_SESSION['password_change_success'];
    unset($_SESSION['password_change_success']);
}

// Persist lock across refresh (scoped per locked username)
if (isset($_SESSION['lock_state'])) {
    $state = $_SESSION['lock_state'];
    $locked_username = isset($state['username']) ? (string)$state['username'] : '';
    $lockUntil = isset($state['until']) ? (int)$state['until'] : 0;
    if ($lockUntil > time()) {
        $remaining_time = $lockUntil - time();
        $error_message = "Account locked.";
    } else {
        unset($_SESSION['lock_state']);
        $locked_username = '';
    }
}

// Log security event
function logSecurityEvent(PDO $pdo, $user_id, $username, $full_name, $role, $action, $status, $failure_reason = null) {
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    try {
        $identity = securityLogAccountIdentity($pdo, (int)$user_id);
        $sessionKey = $_SESSION['security_session_key'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO security_logs (user_id, username, full_name, role, action, ip_address, user_agent, session_key, actor_id_number, status, failure_reason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $username, $full_name, $role, $action, $ip_address, $user_agent, $sessionKey, $identity['id_number'] ?? null, $status, $failure_reason]);
    } catch (PDOException $e) {
        // Log error but don't break login flow
        error_log("Security log error: " . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    // Presentation context only; failed-attempt counters remain untouched.
    unset($_SESSION['login_attempt_user_id']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {

        // Check if user exists (include first_login_required for first-login detection)
        $stmt = $pdo->prepare("SELECT id, id_number, username, password, role, status, email,
                          first_name, middle_name, last_name, extension_name, first_login_required,
                          temporary_credential_expires_at, temporary_credential_used_at
                               FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $user_id = $user['id'];
            $full_name = securityLogFullName($user);

            // Check if role field exists, default to USER if not
            $user_role = isset($user['role']) ? $user['role'] : 'USER';
            $user_status = isset($user['status']) ? $user['status'] : 'ACTIVE';

            // Keep the username format restriction for regular users only.
            if ($user_role === 'USER' && !preg_match('/^[a-z]{5,}\.[a-z]{2,}$/', $username)) {
                $error_message = "Username must be in format: name.lastname.";
                $failed_login_attempts++;
                $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                $_SESSION['login_flash'] = [
                    'error_message' => $error_message,
                    'remaining_time' => 0,
                    'attempt_count_display' => 0,
                    'locked_username' => '',
                ];
                header('Location: app.php?page=login');
                exit;
            }

            // Check account status before proceeding
            if ($user_status === 'PENDING') {
                $error_message = "Your account is still awaiting approval.";
                logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'FAILED_LOGIN', 'FAILED', 'Account pending approval');
                $failed_login_attempts++;
                $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                $_SESSION['login_flash'] = [
                    'error_message' => $error_message,
                    'remaining_time' => 0,
                    'attempt_count_display' => 0,
                    'locked_username' => '',
                ];
                header('Location: app.php?page=login');
                exit;
            } elseif ($user_status === 'BLOCKED') {
                $error_message = "Your account has been blocked. Please contact an administrator.";
                logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'ACCOUNT_BLOCKED', 'FAILED', 'Account blocked');
                $failed_login_attempts++;
                $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                $_SESSION['login_flash'] = [
                    'error_message' => $error_message,
                    'remaining_time' => 0,
                    'attempt_count_display' => 0,
                    'locked_username' => '',
                ];
                header('Location: app.php?page=login');
                exit;
            } elseif ($user_status === 'REJECTED') {
                $error_message = "Your account registration has been rejected.";
                logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'FAILED_LOGIN', 'FAILED', 'Account rejected');
                $failed_login_attempts++;
                $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                $_SESSION['login_flash'] = [
                    'error_message' => $error_message,
                    'remaining_time' => 0,
                    'attempt_count_display' => 0,
                    'locked_username' => '',
                ];
                header('Location: app.php?page=login');
                exit;
            }

            // Get login attempts for user
            $_SESSION['login_attempt_user_id'] = (int)$user_id;
            $stmt = $pdo->prepare("SELECT * FROM login_attempts WHERE user_id = ? LIMIT 1");
            $stmt->execute([$user_id]);
            $attempt = $stmt->fetch(PDO::FETCH_ASSOC);

            $attempt_count = $attempt ? $attempt['attempt_count'] : 0;
            $denied_until = $attempt && $attempt['denied_until'] ? strtotime($attempt['denied_until']) : 0;

            // Check if account is locked
            if ($denied_until > time()) {
                $remaining_time = $denied_until - time();
                $error_message = "Account locked.";
                logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'FAILED_LOGIN', 'FAILED', 'Account locked due to failed attempts');
                $_SESSION['lock_state'] = ['username' => $username, 'until' => $denied_until];
                $locked_username = $username;
                // PRG: flash and redirect to avoid POST resubmission on refresh
                $failed_login_attempts++;
                $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                $_SESSION['login_flash'] = [
                    'error_message' => $error_message,
                    'remaining_time' => $remaining_time,
                    'attempt_count_display' => $failed_login_attempts,
                    'locked_username' => $locked_username,
                ];
                header('Location: app.php?page=login');
                exit;
            } else {
                if (password_verify($password, $user['password'])) {
                    // ── Check for first-login requirement for all Super Admin-created roles ──
                    $firstLoginRequired = !empty($user['first_login_required']);

                    if ($firstLoginRequired && in_array($user_role, ['USER', 'ADMIN', 'SUPER_ADMIN'], true)) {
                        if (temporaryCredentialIsExpired($user['temporary_credential_expires_at'])) {
                            logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'TEMPORARY_CREDENTIAL_EXPIRED', 'FAILED', 'Temporary credential expired');
                            $error_message = 'Temporary credentials expired. Please contact an administrator for assistance.';
                            $_SESSION['login_flash'] = [
                                'error_message' => $error_message,
                                'remaining_time' => 0,
                                'attempt_count_display' => 0,
                                'locked_username' => '',
                            ];
                            header('Location: app.php?page=login');
                            exit;
                        }

                        // Consume only an unexpired credential. This prevents two concurrent
                        // requests from starting separate first-login sessions.
                        $consumeCredential = $pdo->prepare(
                            'UPDATE users
                             SET temporary_credential_used_at = CURRENT_TIMESTAMP
                             WHERE id = ? AND first_login_required = 1
                               AND temporary_credential_used_at IS NULL
                               AND temporary_credential_expires_at > CURRENT_TIMESTAMP'
                        );
                        $consumeCredential->execute([$user_id]);
                        if ($consumeCredential->rowCount() !== 1) {
                            $error_message = 'Temporary credentials are no longer valid. Please contact an administrator for assistance.';
                            $_SESSION['login_flash'] = [
                                'error_message' => $error_message,
                                'remaining_time' => 0,
                                'attempt_count_display' => 0,
                                'locked_username' => '',
                            ];
                            header('Location: app.php?page=login');
                            exit;
                        }

                        // Reset lockout on successful credential check
                        $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = 0, denied_until = NULL WHERE user_id = ?");
                        $stmt->execute([$user_id]);
                        unset($_SESSION['failed_login_attempts'], $_SESSION['login_attempt_user_id'], $_SESSION['lock_state']);

                        // Generate OTP using the existing password_recovery infrastructure
                        require_once 'recovery_mail.php';
                        $otp       = (string)random_int(100000, 999999);
                        $now       = date('Y-m-d H:i:s');
                        $expiresAt = date('Y-m-d H:i:s', time() + 600);

                        // Invalidate any existing unused OTP records for this user
                        $pdo->prepare('UPDATE password_recovery SET used_at = ? WHERE user_id = ? AND used_at IS NULL')
                            ->execute([$now, $user_id]);

                        $insertOtp = $pdo->prepare(
                            'INSERT INTO password_recovery (user_id, otp_hash, expires_at, last_sent_at)
                             VALUES (?, ?, ?, ?)'
                        );
                        $insertOtp->execute([$user_id, password_hash($otp, PASSWORD_DEFAULT), $expiresAt, $now]);
                        $challengeId = (int)$pdo->lastInsertId();

                        // Send OTP email – never log the OTP value itself
                        $otpSubject = 'FROSTLINE – First Login Verification Code';
                        $otpBody    = "Hello {$full_name},\n\n"
                            . "Your FROSTLINE first-login verification code is: {$otp}\n\n"
                            . "This code expires in 10 minutes and can only be used once.\n\n"
                            . "If you did not attempt to log in, contact a Super Administrator immediately.\n\n"
                            . "-- FROSTLINE Secure Rail System --";

                        $emailSent = sendRecoveryEmail($user['email'], $otpSubject, $otpBody, $user_id, $user_role);

                        // Log first-login attempt + OTP dispatch (no OTP value in logs)
                        logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'FIRST_LOGIN_ATTEMPTED', 'SUCCESS');
                        logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role,
                            'FIRST_LOGIN_OTP_SENT',
                            $emailSent ? 'SUCCESS' : 'FAILED',
                            $emailSent ? null : 'OTP email delivery failed'
                        );

                        // Clear any previous full authentication before entering setup.
                        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'], $_SESSION['full_name'], $_SESSION['security_session_key']);
                        unset($_SESSION['superadmin_session_token'], $_SESSION['superadmin_superseded_logged']);
                        session_regenerate_id(true);

                        // Store minimal first-login session state (no full auth yet)
                        $_SESSION['first_login_user_id']      = $user_id;
                        $_SESSION['first_login_username']     = $user['username'];
                        $_SESSION['first_login_full_name']    = $full_name;
                        $_SESSION['first_login_email']        = $user['email'];
                        $_SESSION['first_login_role']         = $user_role;
                        $_SESSION['first_login_challenge_id'] = $challengeId;
                        $_SESSION['first_login_stage']        = 'otp_pending';
                        $_SESSION['first_login_otp_csrf']     = bin2hex(random_bytes(32));

                        header('Location: app.php?page=first_login_otp');
                        exit;
                    }

                    // ── Normal authenticated login ────────────────────────────────
                    $superAdminToken = null;
                    if ($user_role === 'SUPER_ADMIN') {
                        try {
                            $superAdminToken = activateSuperAdminSession($pdo, (int)$user['id']);
                        } catch (Throwable $takeoverError) {
                            $_SESSION['login_flash'] = ['error_message' => $takeoverError instanceof SuperAdminTakeoverUnavailable ? $takeoverError->getMessage() : 'Super Admin access is unavailable. A request may already be pending. Please try again.',
                                'takeover_remaining' => $takeoverError instanceof SuperAdminTakeoverUnavailable ? $takeoverError->remainingSeconds : 0,
                                'remaining_time' => 0, 'attempt_count_display' => $failed_login_attempts, 'locked_username' => ''];
                            header('Location: app.php?page=login');
                            exit;
                        }
                    }
                    $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = 0, denied_until = NULL WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user_role;
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['security_session_key'] = bin2hex(random_bytes(32));
                    if ($user_role === 'SUPER_ADMIN') {
                        $_SESSION['superadmin_session_token'] = $superAdminToken;
                        if (superAdminSessionState($pdo)['status'] === 'active') superAdminSessionLog($pdo, 'SUPERADMIN_SESSION_STARTED', 'A new Super Administrator session became authoritative.');
                    }
                    startUserPresence($pdo, (int)$user['id'], $_SESSION['security_session_key']);
                    unset($_SESSION['failed_login_attempts']);
                    unset($_SESSION['login_attempt_user_id']);

                    // Log successful login
                    logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'LOGIN', 'SUCCESS');

                    unset($_SESSION['lock_state']);

                    // Role-based redirection
                    if ($user_role === 'SUPER_ADMIN') {
                        header("Location: app.php?page=account_management");
                        exit;
                    } elseif ($user_role === 'ADMIN') {
                        header("Location: app.php?page=account_management");
                        exit;
                    } else {
                        // Regular user
                        $_SESSION['login_success'] = true;
                        header('Location: app.php?page=customer_dashboard');
                        exit;
                    }
                } else {
                    // Failed login
                    $attempt_count++;
                    $attempt_count_display = $attempt_count;

                    // Log failed login attempt
                    logSecurityEvent($pdo, $user_id, $username, $full_name, $user_role, 'FAILED_LOGIN', 'FAILED', 'Incorrect password');

                    // Determine lockout time
                    $lockout_seconds = 0;
                    if ($attempt_count % 3 == 0) {
                        $group = intdiv($attempt_count, 3);
                        if ($group == 1) $lockout_seconds = 15;
                        elseif ($group == 2) $lockout_seconds = 30;
                        else {
                            $lockout_seconds = 60;
                            $attempt_count = 0; // reset after 3 cycles
                        }
                    }

                    $new_denied_until = $lockout_seconds > 0 ? date('Y-m-d H:i:s', time() + $lockout_seconds) : null;

                    if ($attempt) {
                        $stmt = $pdo->prepare("UPDATE login_attempts SET attempt_count = ?, denied_until = ? WHERE user_id = ?");
                        $stmt->execute([$attempt_count, $new_denied_until, $user_id]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, attempt_count, denied_until) VALUES (?, ?, ?)");
                        $stmt->execute([$user_id, $attempt_count, $new_denied_until]);
                    }

                    if ($lockout_seconds > 0) {
                        $error_message = "Account locked.";
                        $remaining_time = $lockout_seconds;
                        $_SESSION['lock_state'] = ['username' => $username, 'until' => time() + $lockout_seconds];
                        $locked_username = $username;
                        // PRG: flash and redirect
                        $failed_login_attempts++;
                        $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                        $_SESSION['login_flash'] = [
                            'error_message' => $error_message,
                            'remaining_time' => $remaining_time,
                            'attempt_count_display' => $failed_login_attempts,
                            'locked_username' => $locked_username,
                        ];
                        header('Location: app.php?page=login');
                        exit;
                    } else {
                        $attempts_left = 3 - ($attempt_count % 3);
                        $error_message = "Incorrect password. You have $attempts_left attempts left.";
                        // PRG: flash and redirect so refresh won't resubmit
                        $failed_login_attempts++;
                        $_SESSION['failed_login_attempts'] = $failed_login_attempts;
                        $_SESSION['login_flash'] = [
                            'error_message' => $error_message,
                            'remaining_time' => 0,
                            'attempt_count_display' => $failed_login_attempts,
                            'locked_username' => '',
                        ];
                        header('Location: app.php?page=login');
                        exit;
                    }
                }
            }

            // (Moved into branches with PRG redirect)

        } else {
            $error_message = "Invalid username or password.";
            $failed_login_attempts++;
            $_SESSION['failed_login_attempts'] = $failed_login_attempts;
            $_SESSION['login_flash'] = [
                'error_message' => $error_message,
                'remaining_time' => 0,
                'attempt_count_display' => $failed_login_attempts,
                'locked_username' => '',
            ];
            header('Location: app.php?page=login');
            exit;
        }

    } else {
        $error_message = "Please fill in all fields.";
        $failed_login_attempts++;
        $_SESSION['failed_login_attempts'] = $failed_login_attempts;
        $_SESSION['login_flash'] = [
            'error_message' => $error_message,
            'remaining_time' => 0,
                    'attempt_count_display' => $failed_login_attempts,
            'locked_username' => '',
        ];
        header('Location: app.php?page=login');
        exit;
    }
}
// Every rejected submission counts for recovery visibility, including unknown
// usernames. The separate database counter continues to enforce account locks.
$show_forgot_password = $failed_login_attempts >= 2;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Frostline Secure Rail System - Login</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="../css/login.css?v=<?= filemtime(__DIR__ . '/../css/login.css') ?>">
<link rel="stylesheet" href="../css/public_header.css?v=<?= filemtime(__DIR__ . '/../css/public_header.css') ?>">
</head>
<body class="main-log" data-lock-remaining="<?= $remaining_time ?>" data-attempts="<?= $attempt_count_display ?>" data-locked-username="<?= htmlspecialchars($locked_username) ?>">
<header class="frostline-header public-header-alignment">
        <div class="frostline-brand">
            <span class="brand-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span>
            <span class="brand-copy">
                <strong>FROSTLINE</strong>
                <small>SECURE RAIL SYSTEM</small>
            </span>
        </div>
        <nav class="frostline-nav" aria-label="Main navigation">
            <a class="nav-route" href="../php/index.php" data-href="../php/app.php?page=home">Home</a>
            <a class="nav-route nav-register" href="../php/register.php" data-href="../php/app.php?page=register" id="headerRegisterBtn">Register</a>
        </nav>
</header>

<main class="login-main">
    <div class="login-section">
        <div class="login-content">
            <div class="login-branding">
                <div class="branding-logo-wrapper">
                    <span class="branding-mark" aria-hidden="true"><i class="fas fa-train"></i></span>
                </div>
                <h1 class="branding-title">FROSTLINE</h1>
                <p class="branding-subtitle">SECURE RAIL SYSTEM</p>
            </div>
            
            <div class="login-form-wrapper">
                <div class="form-box">
                    <h1>Login Form</h1>

                    <?php if (!empty($success_message)): ?>
                        <div class="success-message" style="background:#e6ffe6;border:1px solid #7dd67d;color:#1e7e34;padding:10px 12px;border-radius:6px;margin-bottom:12px;">
                            <?= htmlspecialchars($success_message) ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error_message)): ?>
                        <?php if ($takeover_remaining > 0): ?>
                        <div class="error-message takeover-cooldown-notice" role="alert">
                            <div class="takeover-cooldown-heading"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><strong>Super Admin Session Unavailable</strong></div>
                            <p><?= htmlspecialchars($error_message) ?></p>
                            <span id="takeover-cooldown" data-remaining="<?= $takeover_remaining ?>">Try again in<?= "\n" . sprintf('%02d:%02d', intdiv($takeover_remaining, 60), $takeover_remaining % 60) ?></span>
                            <script src="../js/superadmin_cooldown.js"></script>
                        </div>
                        <?php else: ?>
                        <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <form action="" method="POST" id="loginForm" novalidate>
                        <div class="input-group">
                            <label for="username">Username <span class="required">*</span></label>
                            <input type="text" id="username" name="username" placeholder="Enter Username" autocomplete="off">
                        </div>

                        <div class="input-group">
                            <label for="password">Password <span class="required">*</span></label>
                            <div class="password-container">
                                <input type="password" id="password" name="password" placeholder="Enter Password"
                                minlength="6" maxlength="50">
                                <span class="toggle-password" onclick="togglePasswordVisibility()"><i class="fas fa-eye"></i></span>
                            </div>
                        </div>

                        <button class="login-btn" type="submit" id="loginBtn">Login</button>

                        <?php if (strtolower(trim($_GET['page'] ?? 'login')) === 'login' && $show_forgot_password): ?>
                            <div class="forgot-inline forgot-inline-visible" style="text-align:center;margin-top:0.5rem;font-size:0.9rem;">
                                <a class="nav-route" href="forgot_password.php" data-href="app.php?page=forgot_password" style="color:#147BB7;">Forgot Password?</a>
                            </div>
                        <?php endif; ?>

                        <div class="register-cta">
                            <a href="app.php?page=register" id="registerLink">Please register here!</a>
                        </div>

                        <div class="timer-display" id="timerDisplay" style="display:none;">
                            <p><i class="fas fa-lock"></i> Account locked for <span id="timer"><?= $remaining_time ?></span> seconds</p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Forgot Password section intentionally removed per request -->
    </div>
</main>

<footer class="main-footer">
    <div class="footer-content">
        <span>FROSTLINE SECURE RAIL SYSTEM</span>
        <span>&copy; 2026 Frostline</span>
    </div>
</footer>
<script src="js_gate.php?file=show_password.js&amp;v=<?= filemtime(__DIR__ . '/../js/show_password.js') ?>"></script>
<script src="js_gate.php?file=val_login.js&amp;v=<?= filemtime(__DIR__ . '/../js/val_login.js') ?>"></script>
<script src="js_gate.php?file=login.js&amp;v=<?= filemtime(__DIR__ . '/../js/login.js') ?>"></script>
<script src="js_gate.php?file=countdown.js&amp;v=<?= filemtime(__DIR__ . '/../js/countdown.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('a.nav-route[data-href]').forEach(function(a) {
        a.setAttribute('href', a.getAttribute('data-href'));
    });
});
</script>
</body>
</html>
