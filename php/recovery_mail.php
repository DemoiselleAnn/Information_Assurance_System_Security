<?php
function maskRecoveryEmail(string $email): string {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return 'your registered email';
    [$local, $domain] = explode('@', $email, 2);
    $visible = strlen($local) > 2 ? $local[0] . '*****' . substr($local, -1) : '*****';
    return $visible . '@' . $domain;
}

function sendRecoveryEmail(string $recipient, string $subject, string $message, int $accountId, string $role): bool {
    if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        error_log(sprintf('Recovery mail rejected: invalid recipient; account_id=%d role=%s', $accountId, $role));
        return false;
    }

        $from = trim((string)(getenv('FROSTLINE_MAIL_FROM') ?: ini_get('sendmail_from')));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            error_log(sprintf('Recovery mail rejected: configure FROSTLINE_MAIL_FROM or php.ini sendmail_from; account_id=%d role=%s', $accountId, $role));
            return false;
        }

    // sendmail_path is PHP_INI_SYSTEM: ini_set() cannot configure mail transport.
    $sendmailPath = trim((string)ini_get('sendmail_path'));
        if ($sendmailPath === '') {
            error_log(sprintf('Recovery mail rejected: configure php.ini sendmail_path and restart Apache; account_id=%d role=%s', $accountId, $role));
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        preg_match('/^(?:"([^"]+)"|(\S+))/', $sendmailPath, $pathMatch);
        $executable = $pathMatch[1] ?: ($pathMatch[2] ?? '');
        if (!is_file($executable)) {
            error_log(sprintf('Recovery mail rejected: sendmail executable unavailable; account_id=%d role=%s', $accountId, $role));
            return false;
        }
    }

    $headers = implode("\r\n", [
        'From: ' . $from,
        'Reply-To: ' . $from,
        'Content-Type: text/plain; charset=UTF-8',
    ]) . "\r\n";
    $warning = null;
    set_error_handler(function (int $severity, string $message) use (&$warning): bool {
        $warning = $message;
        return true;
    });
    try {
        $sent = mail($recipient, $subject, $message, $headers);
    } catch (Throwable $e) {
        // Do not log exception text: it may contain recipients or transport secrets.
        error_log(sprintf('Recovery mail failed: mail_exception; account_id=%d role=%s', $accountId, $role));
        return false;
    } finally {
        restore_error_handler();
    }

    if (!$sent) {
        $category = $warning && stripos($warning, 'mailserver') !== false ? 'transport_unavailable' : 'mail_returned_false';
        error_log(sprintf('Recovery mail failed: category=%s account_id=%d role=%s', $category, $accountId, $role));
    }

    return $sent;
}
