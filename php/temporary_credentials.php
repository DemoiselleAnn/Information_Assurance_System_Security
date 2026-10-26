<?php
const TEMPORARY_CREDENTIAL_TTL_SECONDS = 900;

function temporaryCredentialExpiresAt(): string {
    return date('Y-m-d H:i:s', time() + TEMPORARY_CREDENTIAL_TTL_SECONDS);
}

function temporaryCredentialIsExpired(?string $expiresAt): bool {
    return !$expiresAt || strtotime($expiresAt) <= time();
}

function validateTemporaryPassword(string $password, string $confirmation = ''): ?string {
    if ($password === '') return 'Temporary password is required.';
    if (strlen($password) < 8 || strlen($password) > 20
        || !preg_match('/[A-Z]/', $password)
        || !preg_match('/[a-z]/', $password)
        || !preg_match('/[0-9]/', $password)
        || !preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Temporary password must be 8-20 characters and contain uppercase, lowercase, a number, and a special character.';
    }
    if ($confirmation !== '' && !hash_equals($password, $confirmation)) {
        return 'Temporary passwords do not match.';
    }
    return null;
}

function sendTemporaryCredentialEmail(
    string $recipientEmail,
    string $recipientName,
    string $idNumber,
    string $username,
    string $temporaryPassword,
    int $userId,
    string $role
): bool {
    require_once __DIR__ . '/recovery_mail.php';
    $subject = 'FROSTLINE - Your Account Has Been Created';
    $body = "Hello {$recipientName},\n\n"
        . 'An account has been created for you in the FROSTLINE Secure Rail System.'
        . "\n\n---- TEMPORARY LOGIN CREDENTIALS ----\n"
        . "  ID No. : {$idNumber}\n"
        . "  Username    : {$username}\n"
        . "  Password    : {$temporaryPassword}\n"
        . "-------------------------------------\n"
        . "These temporary login credentials are valid for 15 minutes. Please sign in before they expire.\n"
        . "If these credentials expire before you sign in, contact an authorized administrator for assistance.\n\n"
        . "After successful login, you will receive a separate one-time verification code and complete your account setup.\n"
        . "Do not share this email or your temporary password.\n\n"
        . "Login page: app.php?page=login\n\n"
        . "-- FROSTLINE Secure Rail System --";
    return sendRecoveryEmail($recipientEmail, $subject, $body, $userId, $role);
}
