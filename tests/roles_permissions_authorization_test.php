<?php
// Offline tests only: no database connection, schema changes, or application sessions.
require_once __DIR__ . '/../php/authorization.php';
function verifyRp(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
class RpMemoryStatement extends PDOStatement {
    private array $rows = [];
    public function __construct(private RpMemoryPdo $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        $params ??= [];
        if (str_starts_with($this->sql, 'SELECT role FROM users')) $this->rows = [[$this->db->role]];
        elseif (str_starts_with($this->sql, 'SELECT enabled FROM user_permissions')) $this->rows = isset($this->db->overrides[$params[1]]) ? [[$this->db->overrides[$params[1]]]] : [];
        elseif (str_starts_with($this->sql, 'SELECT enabled FROM role_permissions')) $this->rows = isset($this->db->defaults[$params[1]]) ? [[$this->db->defaults[$params[1]]]] : [];
        elseif (str_contains($this->sql, 'FROM users WHERE id=?')) $this->rows = [str_starts_with($this->sql, 'SELECT id_number,') ? $this->db->actor : $this->db->target];
        elseif (str_starts_with($this->sql, 'INSERT INTO user_permissions')) $this->db->overrides[$params[1]] = $params[2];
        elseif (str_starts_with($this->sql, 'INSERT INTO security_logs')) $this->db->logs[] = $params;
        else throw new RuntimeException('Unexpected test query: '.$this->sql);
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchColumn(int $column = 0): mixed { $row = array_shift($this->rows); return $row === null ? false : array_values($row)[$column]; }
}
class RpMemoryPdo extends PDO {
    public string $role = 'ADMIN';
    public array $overrides = [], $defaults = [], $logs = [], $target = [], $actor = [];
    private array $snapshot = [];
    private bool $active = false;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new RpMemoryStatement($this, $query); }
    public function beginTransaction(): bool { $this->snapshot = [$this->overrides, $this->logs]; $this->active = true; return true; }
    public function inTransaction(): bool { return $this->active; }
    public function commit(): bool { $this->active = false; return true; }
    public function rollBack(): bool { [$this->overrides, $this->logs] = $this->snapshot; $this->active = false; return true; }
}
$source = file_get_contents(__DIR__.'/../php/roles_permissions.php');
foreach (explode("\n", $source) as $line) {
    if (str_starts_with($line, '$permissions =') || str_starts_with($line, 'function rpName(') || str_starts_with($line, 'function rpLog(')) eval($line);
}
set_error_handler(function ($severity, $message) { throw new ErrorException($message, 0, $severity); });
verifyRp(rpName(['first_name'=>'Ada', 'last_name'=>'Lovelace']) === 'Ada Lovelace', 'Optional name fields');
verifyRp(rpName(['first_name'=>'Ada', 'middle_name'=>'M', 'last_name'=>'Lovelace', 'extension_name'=>'Jr.']) === 'Ada M Lovelace Jr.', 'Name formatting');
$pdo = new RpMemoryPdo();
foreach (['ADMIN','USER'] as $role) {
    $pdo->role = $role;
    foreach (['MANAGE_ROLES','MANAGE_ADMINISTRATORS','APPROVE_DELETIONS','SYSTEM_SETTINGS'] as $key) {
        $pdo->overrides = [$key=>1]; $pdo->defaults = [$key=>1];
        verifyRp(!hasPermission($pdo, 2, $key), "$role legacy grant $key bypass");
        try { validateIndividualPermissions($role, [$key]); throw new RuntimeException('Invalid grant accepted'); } catch (InvalidArgumentException $e) {}
    }
    $pdo->overrides = ['VIEW_DASHBOARD'=>0, 'VIEW_SECURITY_LOGS'=>0];
    verifyRp(hasPermission($pdo, 2, 'VIEW_DASHBOARD'), 'Dashboard remains included');
    if ($role === 'USER') verifyRp(hasPermission($pdo, 2, 'VIEW_SECURITY_LOGS'), 'User scoped logs remain included');
}
$pdo->role = 'SUPER_ADMIN';
foreach (array_keys($permissions) as $key) verifyRp(hasPermission($pdo, 2, $key), 'Super Admin privileges');
try { validateIndividualPermissions('SUPER_ADMIN', []); throw new RuntimeException('Super Admin target accepted'); } catch (InvalidArgumentException $e) {}
$start = strpos($source, "if (\$_SERVER['REQUEST_METHOD']==='POST')");
$end = strpos($source, '$usersStmt=', $start);
$post = str_replace('exit();', 'return;', substr($source, $start, $end-$start));
$_SESSION = ['user_id'=>1]; $_SERVER['REQUEST_METHOD']='POST';
$pdo->role = 'ADMIN';
$pdo->actor = ['id_number'=>'A1','username'=>'actor','first_name'=>'Acting','last_name'=>'Admin','role'=>'SUPER_ADMIN'];
$pdo->target = ['id'=>2, 'role'=>'ADMIN', 'username'=>'target'];
$pdo->overrides = ['MANAGE_USERS'=>0, 'APPROVE_DELETIONS'=>1, 'UNRELATED_LEGACY_KEY'=>1];
$pdo->defaults = []; $csrf = 'offline-token';
foreach ([['MANAGE_USERS'], []] as $grants) {
    $_POST = ['csrf_token'=>$csrf, 'user_id'=>2, 'permissions'=>$grants]; $errors = [];
    eval($post);
    verifyRp(!$errors && !$pdo->inTransaction(), 'Valid save commits');
    verifyRp(hasPermission($pdo, 2, 'MANAGE_USERS') === (bool)$grants, 'Grant/revoke effective');
    verifyRp($pdo->overrides['APPROVE_DELETIONS'] === 0 && $pdo->overrides['UNRELATED_LEGACY_KEY'] === 1, 'Safe legacy handling');
}
verifyRp(count($pdo->logs) === 2 && $pdo->logs[0][4] === 'PERMISSIONS_CHANGED' && $pdo->logs[0][8] === 2 && str_contains($pdo->logs[0][10], 'MANAGE_USERS'), 'Actor, target and permission changes logged');
foreach ([['ADMIN',['APPROVE_DELETIONS']], ['SUPER_ADMIN',[]], ['USER',['MANAGE_USERS']], ['ADMIN',[[1]]], ['ADMIN','invalid']] as [$targetRole,$grants]) {
    $pdo->target['role'] = $targetRole; $before = $pdo->overrides; $count = count($pdo->logs);
    $_POST = ['csrf_token'=>$csrf, 'user_id'=>2, 'permissions'=>$grants]; $errors = [];
    eval($post);
    verifyRp((bool)$errors && $before === $pdo->overrides && $count === count($pdo->logs) && !$pdo->inTransaction(), 'Forged request must leave overrides and logs unchanged');
}
$deletion = file_get_contents(__DIR__.'/../php/deletion_requests.php');
$start = strpos($deletion, '$isSuperAdminRole ='); $end = strpos($deletion, "if (!\$isSuperAdminRole", $start);
$setup = substr($deletion, $start, $end-$start);
foreach (['ADMIN','USER','SUPER_ADMIN'] as $role) {
    $pdo->role = $role; $pdo->overrides = ['APPROVE_DELETIONS'=>1, 'SUBMIT_DELETION_REQUESTS'=>1];
    eval($setup);
    verifyRp($isDeletionReviewer === ($role === 'SUPER_ADMIN'), 'Deletion reviewer role boundary');
    if ($role === 'ADMIN') verifyRp($canSubmitDeletionRequest, 'Admin request workflow retained');
}
// Execute the real CSRF guard in separate offline processes (it intentionally exits).
$guardStart = strpos($source, '    if (!is_string($_POST');
$guardEnd = strpos($source, '    $targetId =', $guardStart);
$csrfGuard = substr($source, $guardStart, $guardEnd-$guardStart);
foreach (['bad-token', ['malformed']] as $badToken) {
    $code = '$csrf="offline-token"; $_POST=["csrf_token"=>'.var_export($badToken,true).'];'.$csrfGuard.'echo "BYPASSED";';
    $process = proc_open([PHP_BINARY, '-r', $code], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
    verifyRp($out === 'Invalid request token' && $err === '', 'CSRF rejects wrong and malformed tokens');
}
$logs = file_get_contents(__DIR__.'/../php/security_logs.php');
$begin = strpos($logs, 'function buildSecurityWhere('); $finish = strpos($logs, '$monthFilter =', $begin);
eval(substr($logs, $begin, $finish-$begin));
[$where,$params] = buildSecurityWhere('USER', 7);
verifyRp(str_contains($where, '.user_id = ?') && str_contains($where, '.role = \'USER\'') && $params === [7], 'User logs remain own User logs');
[$where,$params] = buildSecurityWhere('ADMIN', 7);
verifyRp(str_contains($where, ".role IN ('ADMIN', 'USER')"), 'Admin logs remain scoped');
[$where,$params] = buildSecurityWhere('SUPER_ADMIN', 7);
verifyRp($where === '1=1' && $params === [], 'Super Admin logs remain unrestricted');
// Execute the entry-point and deletion-review rejection guards without database access.
$gateStart = strpos($source, "if (!isset(\$_SESSION['user_id'])");
$gateEnd = strpos($source, "requirePermission(\$pdo, 'MANAGE_ROLES');", $gateStart);
$entryGuard = substr($source, $gateStart, $gateEnd-$gateStart);
$reviewStart = strpos($deletion, "    if (in_array(\$action, ['approve', 'reject'], true)");
$reviewEnd = strpos($deletion, "    if (\$action === 'submit'", $reviewStart);
$reviewGuard = substr($deletion, $reviewStart, $reviewEnd-$reviewStart);
foreach (['ADMIN','USER'] as $role) {
    foreach ([
        '$_SESSION=["user_id"=>2,"role"=>'.var_export($role,true).'];'.$entryGuard,
        '$action="approve"; $isDeletionReviewer=false;'.$reviewGuard,
    ] as $code) {
        $process = proc_open([PHP_BINARY, '-r', $code.'echo "BYPASSED";'], [1=>['pipe','w'],2=>['pipe','w']], $pipes);
        $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        verifyRp($out==='Forbidden' && $err==='', 'Lower role URL/review guard rejects');
    }
}
restore_error_handler();
echo "PASS: role boundaries, legacy overrides, mandatory dashboard/scoped logs, actual permission POST grant/revoke/rejection with in-memory PDO, rollback, name warnings, audit payloads, deletion reviewer checks. No live database used.\n";
