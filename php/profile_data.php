<?php
// Profile dates are calendar dates in the application's local timezone.
function profileBirthdate($value, ?DateTimeImmutable $today = null): ?DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
    $zone = new DateTimeZone('Asia/Manila');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
    $errors = DateTimeImmutable::getLastErrors();
    $today = ($today ?? new DateTimeImmutable('today', $zone))->setTimezone($zone)->setTime(0, 0);
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
        || $date->format('Y-m-d') !== $value || $date->format('Y') === '0000' || $date > $today) return null;
    return $date;
}
function profileAge($birthdate, ?DateTimeImmutable $today = null): ?int {
    $today = ($today ?? new DateTimeImmutable('today', new DateTimeZone('Asia/Manila')))
        ->setTimezone(new DateTimeZone('Asia/Manila'))->setTime(0, 0);
    $date = profileBirthdate($birthdate, $today);
    return $date ? $date->diff($today)->y : null;
}
function profileBirthdateLabel($birthdate): string {
    return profileBirthdate($birthdate)?->format('F j, Y') ?? 'Not provided';
}
function profileAgeLabel($birthdate): string {
    $age = profileAge($birthdate);
    return $age === null ? 'Not provided' : $age . ' years';
}

function profileCreatedLabel($value, bool $includeTime = false): string {
    if (!is_string($value)) return 'Not provided';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Manila'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))
        || $date->format('Y-m-d H:i:s') !== $value || $date->format('Y') === '0000') return 'Not provided';
    return $date->format($includeTime ? 'F j, Y · g:i A' : 'F j, Y');
}
