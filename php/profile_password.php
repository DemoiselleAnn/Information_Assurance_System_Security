<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/profile_recovery.php';
require_once __DIR__ . '/recovery_mail.php';
if (!isProfileRecovery()) { header('Location: app.php?page=my_profile'); exit; }
requireProfileRecoveryAccount($pdo);
$stage = $_GET['stage'] ?? 'questions';
if (in_array($stage, ['otp', 'password'], true)) {
    // Reuse the public stage's handler and form, placing only its main content in the dashboard.
    ob_start();
    require __DIR__ . ($stage === 'otp' ? '/otp_verification.php' : '/change_password.php');
    $stageHtml = ob_get_clean();
    preg_match('/<main\b[^>]*>(.*?)<\/main>/is', $stageHtml, $stageMain);
    preg_match_all('/<script\b[^>]*>.*?<\/script>/is', $stageHtml, $stageScripts);
    echo '<div class="profile-recovery"><a class="btn btn-quiet" href="app.php?page=my_profile">Cancel and return to My Profile</a>' . ($stageMain[1] ?? '') . '</div>' . implode('', $stageScripts[0]);
    return;
}
if (!isset($_SESSION['forgot_user_id'], $_SESSION['forgot_questions'], $_SESSION['forgot_csrf'])) { header('Location: app.php?page=my_profile'); exit; }
requireProfileRecoveryAccount($pdo, (int)$_SESSION['forgot_user_id']);
$questionError = $_SESSION['forgot_error'] ?? '';
unset($_SESSION['forgot_error']);
?>
<main class="management-page profile-recovery"><header class="management-header"><div><p class="eyebrow">ACCOUNT SECURITY</p><h1>Change Password</h1><p class="management-subtitle">Answer your three security questions. At least two answers must be correct.</p></div></header>
<section class="management-panel">
<?php if ($questionError): ?><div class="management-alert error"><?= htmlspecialchars($questionError) ?></div><?php endif; ?>
<form method="post" action="process_forgot_password.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['forgot_csrf']) ?>">
<?php foreach ($_SESSION['forgot_questions'] as $index=>$question): ?><div class="input-group"><label for="profile-answer-<?= $index ?>"><?= htmlspecialchars($question) ?></label><input type="hidden" name="question<?= $index+1 ?>" value="<?= htmlspecialchars($question) ?>"><input type="password" id="profile-answer-<?= $index ?>" name="answer<?= $index+1 ?>" autocomplete="off" required></div><?php endforeach; ?>
<button class="btn btn-primary" type="submit">Verify Answers</button> <a class="btn btn-quiet" href="app.php?page=my_profile">Cancel</a></form></section></main>
