<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/profile_recovery.php';

$challengeId = (int)($_SESSION['recovery_challenge_id'] ?? 0);
if (!$challengeId || ($_SESSION['recovery_stage'] ?? '') !== 'otp_verified') {
    $_SESSION['forgot_error'] = 'Please verify the email code before answering security questions.';
    header('Location: ' . recoveryLocation('start'));
    exit;
}

$challengeStmt = $pdo->prepare('SELECT user_id FROM password_recovery WHERE id = ? AND otp_verified = 1 AND used_at IS NULL LIMIT 1');
$challengeStmt->execute([$challengeId]);
$userId = (int)$challengeStmt->fetchColumn();
$account = recoveryAccount($pdo, $userId);
$eligibilityError = recoveryEligibilityMessage($account);
if (!$userId || $eligibilityError !== null) {
    unset($_SESSION['recovery_challenge_id'], $_SESSION['recovery_stage'], $_SESSION['otp_csrf']);
    $_SESSION['forgot_error'] = $eligibilityError ?: 'This recovery session is no longer valid.';
    header('Location: ' . recoveryLocation('start'));
    exit;
}

$questionCountStmt = $pdo->prepare('SELECT COUNT(*) FROM security_answers WHERE user_id = ?');
$questionCountStmt->execute([$userId]);
if ((int)$questionCountStmt->fetchColumn() < 3) {
    $_SESSION['forgot_error'] = 'Security questions are not available for this account.';
    header('Location: ' . recoveryLocation('start'));
    exit;
}

$_SESSION['forgot_user_id'] = $userId;
$_SESSION['security_questions_csrf'] = $_SESSION['security_questions_csrf'] ?? bin2hex(random_bytes(32));
$error = $_SESSION['forgot_error'] ?? '';
unset($_SESSION['forgot_error']);
$questionBank = recoveryQuestionBank();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FROSTLINE - Security Questions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="../css/forgot_password.css?v=<?= filemtime(__DIR__ . '/../css/forgot_password.css') ?>">
</head>
<body class="main-log security-questions-page">
<header class="main-header"><div class="header-content"><div class="logo-container"><span class="logo-mark" aria-hidden="true"><i class="fas fa-snowflake"></i></span><div class="logo-copy"><h1 class="logo">FROSTLINE</h1><span>SECURE RAIL SYSTEM</span></div></div><nav class="nav-links"><a href="app.php?page=login">Login</a></nav></div></header>
<main class="forgot-form security-questions-layout"><div class="form-box"><h1>Security Questions</h1><p class="form-description">Select the three security questions you previously registered and provide their answers. At least two question-and-answer pairs must be correct.</p><?php if ($error): ?><div class="error-message"><?= htmlspecialchars($error) ?></div><?php endif; ?><form method="post" action="process_forgot_password.php" id="securityQuestionsForm"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['security_questions_csrf']) ?>"><div class="form-content">
<?php for ($index = 1; $index <= 3; $index++): ?><div class="input-group security-question-group"><label for="question<?= $index ?>">Security Question <?= $index ?></label><select class="security-question-select" id="question<?= $index ?>" name="question<?= $index ?>" required><option value="">Select your security question</option><?php foreach ($questionBank as $question): ?><option value="<?= htmlspecialchars($question, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($question) ?></option><?php endforeach; ?></select><label for="answer<?= $index ?>">Answer</label><input type="password" id="answer<?= $index ?>" name="answer<?= $index ?>" placeholder="Enter your answer" autocomplete="off" required></div><?php endfor; ?>
</div><div class="form-actions"><button class="forgot-btn" type="submit">Verify Answers</button></div></form></div></main>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selects = Array.from(document.querySelectorAll('#securityQuestionsForm select'));
    function updateOptions() {
        const selected = selects.map(function (select) { return select.value; }).filter(Boolean);
        selects.forEach(function (select) {
            Array.from(select.options).forEach(function (option) {
                option.disabled = option.value !== '' && option.value !== select.value && selected.includes(option.value);
            });
        });
    }
    selects.forEach(function (select) { select.addEventListener('change', updateOptions); });
    updateOptions();
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
