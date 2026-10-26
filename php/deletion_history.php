<?php
// Origin is asserted only by explicit server-generated audit evidence, never by equal actors/dates.
function deletionOriginSql(string $alias): string {
    if ($alias !== 'dr') throw new InvalidArgumentException('Unsupported history alias');
    return "CASE WHEN EXISTS (SELECT 1 FROM security_logs origin_log WHERE origin_log.status = 'SUCCESS' AND origin_log.action = 'ACCOUNT_DELETED' AND origin_log.failure_reason LIKE CONCAT('Deletion history #', dr.id, '; Origin: Direct Deletion;%')) THEN 'direct' WHEN EXISTS (SELECT 1 FROM security_logs origin_log WHERE origin_log.status = 'SUCCESS' AND origin_log.action = 'DELETION_REQUEST_APPROVED' AND origin_log.failure_reason LIKE CONCAT('Deletion history #', dr.id, '; Origin: Approved Request;%')) THEN 'request' ELSE NULL END";
}
function deletionHistoryLabel(array $request): string {
    if (($request['status'] ?? '') !== 'APPROVED') return (string)($request['status'] ?? 'Unavailable');
    return match ($request['deletion_origin'] ?? null) {
        'direct' => 'Direct Deletion',
        'request' => 'Approved Request',
        default => 'APPROVED (origin unavailable)',
    };
}
function deletionAccountCreated(?string $value): string {
    if (!$value || $value === '0000-00-00 00:00:00') return 'Unavailable';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Manila'));
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d H:i:s') !== $value) return 'Unavailable';
    return $date->format('Y-m-d H:i');
}
