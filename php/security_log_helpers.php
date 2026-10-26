<?php
// Event snapshots only; these helpers never rewrite historical audit records.
function securityLogFullName(array $account): string {
    return trim(implode(' ', array_filter([
        $account['first_name'] ?? '', $account['middle_name'] ?? '',
        $account['last_name'] ?? '', $account['extension_name'] ?? '',
    ], static fn($part) => $part !== null && $part !== '')));
}

function securityLogAccountIdentity(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare('SELECT id_number, first_name, middle_name, last_name, extension_name FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function securityLogSessionLabel(array $session): string {
    if (!empty($session['time_out'])) return 'Completed';
    if (!empty($session['presence_active'])) return 'Active';
    if (!empty($session['presence_ended_at'])) return 'Ended';
    if (!empty($session['presence_last_activity'])) return 'Expired';
    return 'Unclosed';
}
