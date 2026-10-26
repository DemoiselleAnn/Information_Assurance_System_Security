<?php
require_once __DIR__ . '/../php/sidebar_pending_counts.php';

function checkBadge(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

class SidebarBadgeTestPDO extends PDO {
    public string $actorRole = 'SUPER_ADMIN';
    public array $overrides = [];
    public array $defaults = ['APPROVE_REGISTRATIONS' => 1, 'SUBMIT_DELETION_REQUESTS' => 1];
    public array $users = [
        ['role'=>'USER','status'=>'PENDING'], ['role'=>'USER','status'=>'PENDING'], ['role'=>'USER','status'=>'PENDING'],
        ['role'=>'ADMIN','status'=>'PENDING'], ['role'=>'USER','status'=>'ACTIVE'], ['role'=>'USER','status'=>'INACTIVE'], ['role'=>'USER','status'=>'REJECTED'],
    ];
    public array $requests = ['PENDING','PENDING','APPROVED','REJECTED'];
    public array $queries = [];
    public ?string $failTable = null;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $this->queries[] = $query;
        if ($this->failTable && str_contains($query, 'COUNT(*) FROM ' . $this->failTable)) throw new PDOException('Sensitive database diagnostic');
        return new SidebarBadgeTestStatement($this, $query);
    }
    public function countQueries(): int { return count(array_filter($this->queries, fn($q)=>str_contains($q,'COUNT(*)'))); }
}
class SidebarBadgeTestStatement extends PDOStatement {
    private array $values = [];
    public function __construct(private SidebarBadgeTestPDO $db, private string $sql) {}
    public function execute(?array $params = null): bool { $this->values = $params ?? []; return true; }
    public function fetchColumn(int $column = 0): mixed {
        if (str_contains($this->sql, 'SELECT role FROM users')) return $this->db->actorRole;
        if (str_contains($this->sql, 'FROM user_permissions')) return $this->db->overrides[$this->values[1]] ?? false;
        if (str_contains($this->sql, 'FROM role_permissions')) return $this->db->defaults[$this->values[1]] ?? false;
        if ($this->sql === 'SELECT COUNT(*) FROM users WHERE role = ? AND status = ?') return count(array_filter($this->db->users, fn($r)=>$r['role']===$this->values[0] && $r['status']===$this->values[1]));
        if ($this->sql === 'SELECT COUNT(*) FROM deletion_requests WHERE status = ?') return count(array_filter($this->db->requests, fn($s)=>$s===$this->values[0]));
        throw new RuntimeException('Unexpected query: '.$this->sql);
    }
}
$db = new SidebarBadgeTestPDO();
checkBadge(sidebarPendingCounts($db,1,'SUPER_ADMIN') === ['registration_approval'=>3,'deletion_requests'=>2], 'Super Admin counts');
checkBadge($db->countQueries()===2, 'Two COUNT queries, no request datasets');
$db->actorRole='ADMIN'; $db->queries=[];
checkBadge(sidebarPendingCounts($db,2,'ADMIN') === ['registration_approval'=>3], 'Default Admin must not see review-only deletion counts');
checkBadge($db->countQueries()===1, 'Unauthorized deletion count was queried');
$db->overrides=['APPROVE_DELETIONS'=>1];
checkBadge(sidebarPendingCounts($db,2,'ADMIN') === ['registration_approval'=>3,'deletion_requests'=>2], 'Admin reviewer overrides');
$db->overrides=['APPROVE_DELETIONS'=>0,'APPROVE_REGISTRATIONS'=>0];$db->queries=[];
checkBadge(sidebarPendingCounts($db,2,'ADMIN') === [], 'Revoked permissions');checkBadge($db->countQueries()===0, 'Unauthorized counts queried');
$db->queries=[];checkBadge(sidebarPendingCounts($db,3,'USER')===[], 'Member counts leaked');checkBadge($db->queries===[], 'Member database queries');
checkBadge(sidebarPendingCounts($db,0,'SUPER_ADMIN')===[], 'Invalid user');
$db->actorRole='SUPER_ADMIN';$db->overrides=[];
array_shift($db->users);checkBadge(sidebarPendingCounts($db,1,'SUPER_ADMIN')['registration_approval']===2, 'Approved registration refresh');
$db->requests[0]='REJECTED';checkBadge(sidebarPendingCounts($db,1,'SUPER_ADMIN')['deletion_requests']===1, 'Rejected request refresh');
$db->users=[];$db->requests=['APPROVED','REJECTED'];checkBadge(sidebarPendingCounts($db,1,'SUPER_ADMIN')===['registration_approval'=>0,'deletion_requests'=>0], 'Resolved records');
$zero=sidebarPendingBadge(0,'registrations awaiting approval');checkBadge(str_contains($zero,' hidden>') && !str_contains($zero,'>0<'), 'Zero badge must be empty and hidden');
$large=sidebarPendingBadge(150,'requests awaiting review');checkBadge(str_contains($large,'>99+<') && str_contains($large,'150 requests awaiting review'), 'Capped count with exact accessible count');
checkBadge(str_contains(sidebarPendingBadge(1,'<unsafe>'),'&lt;unsafe&gt;'), 'Escaped badge label');
$db->failTable='users';$counts=sidebarPendingCounts($db,1,'SUPER_ADMIN');checkBadge(!isset($counts['registration_approval']) && $counts['deletion_requests']===0,'Count failure must be isolated');
echo "PASS: real helper queries, pending-only scopes, both roles and permission overrides, processed counts, zero/99+, escaping, query efficiency, and safe isolated count failures.\n";
