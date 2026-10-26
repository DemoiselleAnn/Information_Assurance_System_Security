<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'recovery_mail.php';
require_once 'profile_recovery.php';
requireProfileRecoveryAccount($pdo);

if (!isProfileRecovery() && ($_SESSION['recovery_stage'] ?? '') !== 'otp_pending') {
    $_SESSION['forgot_error'] = 'Please start account recovery again.';
    header('Location: ' . recoveryLocation('start'));
    exit;
}

$challengeId = (int)($_SESSION['recovery_challenge_id'] ?? 0);
if (!$challengeId) {
    header('Location: app.php?page=login');
    exit;
}

$stmt = $pdo->prepare("SELECT r.expires_at, r.attempts, u.id AS account_id, u.email, u.role, u.status, u.first_login_required FROM password_recovery r JOIN users u ON u.id = r.user_id WHERE r.id = ? AND r.used_at IS NULL AND r.otp_verified = 0 AND u.role IN ('USER', 'ADMIN', 'SUPER_ADMIN') LIMIT 1");
$stmt->execute([$challengeId]);
$challenge = $stmt->fetch(PDO::FETCH_ASSOC);
if ($challenge && !isProfileRecovery()) {
    $eligibilityError = recoveryEligibilityMessage(recoveryAccount($pdo, (int)$challenge['account_id']));
    if ($eligibilityError !== null) {
        $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ?')->execute([$challengeId]);
        unset($_SESSION['recovery_challenge_id'], $_SESSION['recovery_stage'], $_SESSION['otp_csrf']);
        $_SESSION['forgot_error'] = $eligibilityError;
        header('Location: ' . recoveryLocation('start'));
        exit;
    }
}
if ($challenge && isProfileRecovery()) {
    $owner = $pdo->prepare('SELECT user_id FROM password_recovery WHERE id = ?');
    $owner->execute([$challengeId]);
    requireProfileRecoveryAccount($pdo, (int)$owner->fetchColumn());
}
if (!$challenge) {
    unset($_SESSION['recovery_challenge_id'], $_SESSION['otp_csrf']);
    header('Location: ' . recoveryLocation('start'));
    exit;
}
$otpCsrf = $_SESSION['otp_csrf'] ??= bin2hex(random_bytes(32));
$error = $_SESSION['otp_error'] ?? '';
unset($_SESSION['otp_error']);
$maskedEmail = maskRecoveryEmail($challenge['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROSTLINE - Verify Code</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/forgot_password.css">
</head>
<body class="main-log">
<header class="main-header"><div class="header-content"><div class="logo-container"><span class="logo-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span><div class="logo-copy"><h1 class="logo">FROSTLINE</h1><span>SECURE RAIL SYSTEM</span></div></div><nav class="nav-links"><a href="app.php?page=login">Login</a></nav></div></header>
<main class="forgot-form"><div class="form-box" style="max-width:480px"><h1>Verify Your Email</h1><p style="text-align:center;margin-bottom:1rem;color:#666;font-size:.9rem">Enter the six-digit code sent to <?= htmlspecialchars($maskedEmail) ?>.</p><?php if ($error): ?><div class="error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post" action="process_otp.php" id="otpForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($otpCsrf) ?>"><input type="hidden" name="otp" id="otp_combined"><div class="form-content"><div class="input-group"><label for="otp">Verification code</label><div class="otp-container"><div class="otp-input-wrapper"><input type="password" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required><input type="password" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required><input type="password" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required><input type="password" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required><input type="password" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required><input type="password" class="otp-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="one-time-code" required></div></div><div class="otp-error" id="otpError"></div></div></div><div class="form-actions"><button class="forgot-btn" type="submit">Verify Code</button><button class="forgot-btn" type="submit" name="action" value="resend" formnovalidate>Send New Code</button></div></form></div></main>
<?php include 'footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const otpInputs = document.querySelectorAll('.otp-input');
    const otpCombined = document.getElementById('otp_combined');
    const otpError = document.getElementById('otpError');
    const otpForm = document.getElementById('otpForm');
    
    // Handle OTP input focus and navigation
    otpInputs.forEach((input, index) => {
        // Auto-focus next input on digit entry
        input.addEventListener('input', function(e) {
            const value = e.target.value;
            
            // Only allow numeric input
            if (!/^\d$/.test(value)) {
                e.target.value = '';
                return;
            }
            
            // Move to next input if value is entered
            if (value && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
            
            // Update combined OTP value
            updateCombinedOTP();
        });
        
        // Handle backspace navigation
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Backspace' && !e.target.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });
        
        // Handle paste event
        input.addEventListener('paste', function(e) {
            e.preventDefault();
            const pasteData = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, 6);
            if (!pasteData) return;
            otpInputs.forEach(box => { box.value = ''; });
            
            pasteData.split('').forEach((char, i) => {
                if (otpInputs[i]) {
                    otpInputs[i].value = char;
                }
            });
            
            // Focus the next empty input or the last input
            const nextEmptyIndex = Array.from(otpInputs).findIndex((inp, i) => i >= pasteData.length && !inp.value);
            if (nextEmptyIndex !== -1) {
                otpInputs[nextEmptyIndex].focus();
            } else {
                otpInputs[pasteData.length - 1].focus();
            }
            
            updateCombinedOTP();
        });
    });
    
    // Update combined OTP value
    function updateCombinedOTP() {
        const otpValue = Array.from(otpInputs).map(input => input.value).join('');
        otpCombined.value = otpValue;
        
        // Clear error if OTP is complete
        if (otpValue.length === 6) {
            otpError.textContent = '';
        }
    }
    
    // Form validation before submission
    otpForm.addEventListener('submit', function(e) {
        if (e.submitter && e.submitter.value === 'resend') return;
        updateCombinedOTP();
        const otpValue = Array.from(otpInputs).map(input => input.value).join('');
        
        if (otpValue.length !== 6) {
            e.preventDefault();
            otpError.textContent = 'Please enter the complete 6-digit verification code.';
            return false;
        }
        
        return true;
    });
});
</script>
</body>
</html>
