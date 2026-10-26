<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once 'db.php';
require_once 'recovery_mail.php';
require_once 'profile_recovery.php';

function recoveryError(string $message): void {
    $_SESSION['forgot_error'] = $message;
    header('Location: ' . recoveryLocation('questions'));
    exit;
}

function normalizeSecurityAnswer(string $answer): string {
    return strtolower(trim(preg_replace('/\s+/', ' ', $answer)));
}

function securityVerificationFailed(): void {
    $attempts = (int)($_SESSION['forgot_question_attempts'] ?? 0) + 1;
    if ($attempts >= 5) {
        unset($_SESSION['forgot_user_id'], $_SESSION['forgot_questions'], $_SESSION['forgot_csrf'], $_SESSION['forgot_question_attempts']);
        recoveryError('Too many failed security verification attempts. Please start recovery again later.');
    }
    $_SESSION['forgot_question_attempts'] = $attempts;
    recoveryError('Security verification failed. Please check your answers.');
}

if (!isProfileRecovery()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST'
        || !isset($_SESSION['forgot_user_id'], $_SESSION['recovery_challenge_id'])
        || ($_SESSION['recovery_stage'] ?? '') !== 'otp_verified') {
        $_SESSION['forgot_error'] = 'Please verify the email code before answering security questions.';
        header('Location: ' . recoveryLocation('start'));
        exit;
    }
    if (!hash_equals((string)($_SESSION['security_questions_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
        $_SESSION['forgot_error'] = 'Your recovery session has expired. Please try again.';
        header('Location: ' . recoveryLocation('start'));
        exit;
    }

    $userId = (int)$_SESSION['forgot_user_id'];
    $challengeId = (int)$_SESSION['recovery_challenge_id'];
    $challengeStmt = $pdo->prepare('SELECT id FROM password_recovery WHERE id = ? AND user_id = ? AND otp_verified = 1 AND used_at IS NULL LIMIT 1');
    $challengeStmt->execute([$challengeId, $userId]);
    $account = recoveryAccount($pdo, $userId);
    $eligibilityError = recoveryEligibilityMessage($account);
    if (!$challengeStmt->fetchColumn() || $eligibilityError !== null) {
        $_SESSION['forgot_error'] = $eligibilityError ?: 'This recovery session is no longer valid.';
        header('Location: ' . recoveryLocation('start'));
        exit;
    }

    $questionBank = recoveryQuestionBank();
    $selectedQuestions = [];
    $answers = [];
    $correctAnswers = 0;
    for ($index = 1; $index <= 3; $index++) {
        $question = trim((string)($_POST['question' . $index] ?? ''));
        $answer = trim((string)($_POST['answer' . $index] ?? ''));
        $selectedQuestions[] = $question;
        $answers[] = $answer;
    }
    if (count(array_unique($selectedQuestions)) !== 3 || in_array('', $selectedQuestions, true)
        || count(array_filter($selectedQuestions, static fn(string $question): bool => in_array($question, $questionBank, true))) !== 3
        || in_array('', $answers, true)) {
        $_SESSION['forgot_error'] = count(array_unique($selectedQuestions)) !== 3
            ? 'Please select three different security questions.'
            : 'The selected security questions or answers could not be verified.';
        header('Location: ' . recoveryLocation('questions'));
        exit;
    }

    $rowStmt = $pdo->prepare('SELECT question, answer FROM security_answers WHERE user_id = ?');
    $rowStmt->execute([$userId]);
    $storedByQuestion = [];
    foreach ($rowStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $storedByQuestion[$row['question']] = $row['answer'];
    }
    foreach ($selectedQuestions as $index => $question) {
        $hash = $storedByQuestion[$question] ?? '';
        if ($hash !== '' && password_verify(normalizeSecurityAnswer($answers[$index]), $hash)) {
            $correctAnswers++;
        }
    }
    if ($correctAnswers < 2) {
        $attempts = (int)($_SESSION['security_question_attempts'] ?? 0) + 1;
        $_SESSION['security_question_attempts'] = $attempts;
        if ($attempts >= 5) {
            $pdo->prepare('UPDATE password_recovery SET used_at = NOW() WHERE id = ? AND used_at IS NULL')->execute([$challengeId]);
            unset($_SESSION['recovery_challenge_id'], $_SESSION['recovery_stage'], $_SESSION['forgot_user_id'], $_SESSION['forgot_question_ids'], $_SESSION['security_questions_csrf']);
            $_SESSION['forgot_error'] = 'Too many failed security verification attempts. Please start recovery again later.';
            header('Location: ' . recoveryLocation('start'));
            exit;
        }
        $_SESSION['forgot_error'] = 'The provided security answers could not be verified.';
        header('Location: ' . recoveryLocation('questions'));
        exit;
    }

    $_SESSION['security_verified'] = true;
    $_SESSION['recovery_stage'] = 'security_verified';
    $_SESSION['reset_user_id'] = $userId;
    $_SESSION['reset_challenge_id'] = $challengeId;
    $_SESSION['reset_csrf'] = bin2hex(random_bytes(32));
    unset($_SESSION['security_questions_csrf'], $_SESSION['security_question_attempts'], $_SESSION['forgot_error']);
    header('Location: ' . recoveryLocation('password'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_SESSION['forgot_user_id'], $_SESSION['forgot_questions'])) {
    recoveryError('Please verify your account first.');
}
if (!hash_equals((string)($_SESSION['forgot_csrf'] ?? ''), (string)($_POST['csrf_token'] ?? ''))) {
    recoveryError('Your recovery session has expired. Please try again.');
}

$userId = (int)$_SESSION['forgot_user_id'];
requireProfileRecoveryAccount($pdo, $userId);
$answers = [];
for ($index = 1; $index <= 3; $index++) {
    $question = trim((string)($_POST['question' . $index] ?? ''));
    $answer = trim((string)($_POST['answer' . $index] ?? ''));
    $expectedQuestion = $_SESSION['forgot_questions'][$index - 1] ?? '';
    if ($question === '' || $answer === '' || !hash_equals($expectedQuestion, $question)) {
        securityVerificationFailed();
    }
    $answers[] = [$question, $answer];
}

$stmt = $pdo->prepare('SELECT question, answer FROM security_answers WHERE user_id = ?');
$stmt->execute([$userId]);
$stored = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $stored[$row['question']] = $row['answer'];

$correctAnswers = 0;
if (isProfileRecovery()) {
    // Bind answers to the three rows chosen by the server, not browser question text.
    $rowStmt = $pdo->prepare('SELECT id, answer FROM security_answers WHERE user_id = ? ORDER BY id ASC');
    $rowStmt->execute([$userId]);
    $rowHashes = $rowStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $questionIds = $_SESSION['forgot_question_ids'] ?? [];
    if (count($questionIds) !== 3 || count(array_unique($questionIds)) !== 3) securityVerificationFailed();
    foreach ($questionIds as $index => $rowId) {
        $submitted = $answers[$index][1];
        $hash = $rowHashes[$rowId] ?? '';
        if ($hash !== '' && (password_verify($submitted, $hash) || password_verify(normalizeSecurityAnswer($submitted), $hash))) $correctAnswers++;
    }
} else {
foreach ($answers as [$question, $answer]) {
    $storedAnswer = $stored[$question] ?? '';
    if ($storedAnswer !== '' && password_verify(normalizeSecurityAnswer($answer), $storedAnswer)) $correctAnswers++;
}

if ((!isProfileRecovery() && count($stored) < 3) || $correctAnswers < 2) securityVerificationFailed();

if (!filter_var($_SESSION['forgot_email'] ?? '', FILTER_VALIDATE_EMAIL)) {
    error_log(sprintf('Recovery mail rejected: missing or invalid registered email; account_id=%d', $userId));
    recoveryError('Your account does not have a valid recovery email. Please contact the administrator.');
}
}

$otp = (string)random_int(100000, 999999);
$now = date('Y-m-d H:i:s');
$expiresAt = date('Y-m-d H:i:s', time() + 600);
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE password_recovery SET used_at = ?, otp_verified = 0 WHERE user_id = ? AND used_at IS NULL')->execute([$now, $userId]);
    $insert = $pdo->prepare('INSERT INTO password_recovery (user_id, otp_hash, expires_at, last_sent_at) VALUES (?, ?, ?, ?)');
    $insert->execute([$userId, password_hash($otp, PASSWORD_DEFAULT), $expiresAt, $now]);
    $challengeId = (int)$pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    recoveryError('Recovery is temporarily unavailable. Please try again.');
}

$message = "Your FROSTLINE account recovery code is: {$otp}\n\nThis code expires in 10 minutes and can be used once.";
if (!sendRecoveryEmail($_SESSION['forgot_email'], 'FROSTLINE account recovery code', $message, $userId, (string)($_SESSION['forgot_role'] ?? 'UNKNOWN'))) {
    $pdo->prepare('UPDATE password_recovery SET used_at = ? WHERE id = ?')->execute([$now, $challengeId]);
    recoveryError('The verification email could not be sent. Please contact the administrator.');
}

$_SESSION['recovery_challenge_id'] = $challengeId;
unset($_SESSION['forgot_user_id'], $_SESSION['forgot_questions'], $_SESSION['forgot_csrf'], $_SESSION['forgot_question_attempts']);
header('Location: ' . recoveryLocation('otp'));
exit;
