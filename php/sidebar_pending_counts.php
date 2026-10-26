<?php
require_once __DIR__ . '/authorization.php';

/** Return only actionable counts the current dashboard role is allowed to process. */
function sidebarPendingCounts(PDO $pdo, int $userId, string $role): array {
    if ($userId <= 0 || !in_array($role, ['SUPER_ADMIN', 'ADMIN'], true)) {
        return [];
    }

    $workflows = [
        'registration_approval' => [
            'permission' => 'APPROVE_REGISTRATIONS',
            'sql' => 'SELECT COUNT(*) FROM users WHERE role = ? AND status = ?',
            'params' => ['USER', 'PENDING'],
        ],
        'deletion_requests' => [
            'permission' => 'APPROVE_DELETIONS',
            'sql' => 'SELECT COUNT(*) FROM deletion_requests WHERE status = ?',
            'params' => ['PENDING'],
        ],
    ];
    $counts = [];
    foreach ($workflows as $page => $workflow) {
        try {
            if (!hasPermission($pdo, $userId, $workflow['permission'])) continue;
            $stmt = $pdo->prepare($workflow['sql']);
            $stmt->execute($workflow['params']);
            $counts[$page] = max(0, (int)$stmt->fetchColumn());
        } catch (PDOException $e) {
            // A badge failure must not interrupt navigation or expose database diagnostics.
            error_log('Sidebar pending count unavailable: ' . $page);
        }
    }
    return $counts;
}

function sidebarPendingBadge(int $count, string $label): string {
    $count = max(0, $count);
    $text = $count > 99 ? '99+' : (string)$count;
    $attributes = $count > 0
        ? ' aria-label="' . htmlspecialchars($count . ' ' . $label, ENT_QUOTES, 'UTF-8') . '"'
        : ' hidden';
    return '<span class="sidebar-pending-slot" data-sidebar-pending-slot aria-live="polite" aria-atomic="true">'
        . '<span class="sidebar-pending-badge"' . $attributes . '>'
        . ($count > 0 ? $text : '') . '</span></span>';
}
