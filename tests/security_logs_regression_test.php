<?php
// Offline rendering and audit-payload tests. No application database connection.
require_once __DIR__.'/../php/security_log_helpers.php';
function slCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
class SlStatement extends PDOStatement {
    private array $rows = [];
    public function __construct(private SlPdo $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        $params ??= [];
        slCheck(substr_count($this->sql, '?') === count($params), 'SQL parameter count: '.$this->sql);
        $this->db->queries[] = [$this->sql, $params];
        if (str_starts_with($this->sql, 'SELECT id_number, first_name')) $this->rows = [$this->db->identity];
        elseif (str_starts_with($this->sql, 'SELECT id_number, username, first_name')) $this->rows = [$this->db->identity + ['username'=>'fixture','role'=>'SUPER_ADMIN']];
        elseif (str_starts_with($this->sql, 'SELECT username, first_name')) $this->rows = [$this->db->identity + ['username'=>'fixture','role'=>'SUPER_ADMIN']];
        elseif (str_starts_with($this->sql, 'INSERT INTO security_logs')) $this->db->inserts[] = [$this->sql,$params];
        elseif (str_contains($this->sql, 'SELECT COUNT(*)')) $this->rows = [[23]];
        elseif (str_contains($this->sql, 'AS time_in')) $this->rows = $this->db->sessions;
        elseif (str_contains($this->sql, 'WHERE sl.id = ?')) $this->rows = [$this->db->event];
        else $this->rows = [$this->db->event];
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return array_shift($this->rows) ?: false; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { $row=array_shift($this->rows); return $row===null?false:array_values($row)[$column]; }
}
class SlPdo extends PDO {
    public array $queries=[], $inserts=[], $sessions=[];
    public array $identity=['id_number'=>'2026-0007','first_name'=>'Ada','middle_name'=>'M','last_name'=>'Example','extension_name'=>'Jr.'];
    public array $event=['id'=>9,'user_id'=>7,'employee_id'=>'OLD-ID','first_name'=>'Changed','middle_name'=>null,'last_name'=>'Name','snapshot_full_name'=>'Historical Snapshot','username'=>'fixture','role'=>'USER','action'=>'LOGIN','status'=>'SUCCESS','failure_reason'=>null,'ip_address'=>'127.0.0.1','user_agent'=>'offline','actor_id_number'=>'OLD-ID','target_user_id'=>null,'target_username'=>null,'created_at'=>'2026-10-25 12:00:00'];
    public function __construct() {}
    public function lastInsertId(?string $name = null): string|false { return '1'; }
    public function prepare(string $query, array $options = []): PDOStatement|false { return new SlStatement($this,$query); }
}
if (($argv[1]??'')==='render') {
    function requirePermission(PDO $pdo,string $permission): void {}
    $_GET=json_decode($argv[2],true); $_SESSION=['user_id'=>7,'username'=>'fixture','role'=>$_GET['_role']??'SUPER_ADMIN'];
    $pdo=new SlPdo(); $pdo->event['role']=$_GET['_event_role']??'USER'; $pdo->event['user_id']=(int)($_GET['_event_user']??7);
    $currentPage='security_logs';
    $base=['employee_id'=>'OLD-ID','full_name'=>'Session Snapshot','username'=>'fixture','role'=>'USER','time_in'=>'2026-10-25 12:00:00','time_out'=>null];
    foreach ([['presence_active'=>1],['time_out'=>'2026-10-25 12:10:00','presence_active'=>1],['presence_ended_at'=>'2026-10-25 12:10:00'],['presence_last_activity'=>'2020-01-01 12:00:00'],[]] as $extra) $pdo->sessions[]=array_replace($base,$extra);
    $source=file_get_contents(__DIR__.'/../php/security_logs.php');
    $source=preg_replace("~^require_once __DIR__ \\. '/(?:session_bootstrap|db|authorization)\\.php';\\R~m", '', $source);
    $source=str_replace('__DIR__',var_export(dirname(__DIR__).'/php',true),$source);
    ob_start();
    register_shutdown_function(function () use ($pdo, &$currentPage) {
        $html=ob_get_clean(); echo json_encode(['html'=>$html,'navigation'=>$currentPage,'queries'=>$pdo->queries,'http'=>http_response_code()]);
    });
    eval('?>'.$source); exit;
}
function slRender(array $params): array {
    $process=proc_open([PHP_BINARY,__FILE__,'render',json_encode($params)], [1=>['pipe','w'],2=>['pipe','w']],$pipes);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);
    slCheck($status===0 && $err==='', 'Render failed: '.$err);
    $result=json_decode($out,true);slCheck(is_array($result),'Invalid fixture output: '.$out);return $result;
}
set_error_handler(function($severity,$message){throw new ErrorException($message,0,$severity);});
slCheck(securityLogFullName(['first_name'=>'Ada','last_name'=>'Example'])==='Ada Example','Optional names');
slCheck(securityLogFullName(['first_name'=>'Ada','middle_name'=>'M','last_name'=>'Example','extension_name'=>'Jr.'])==='Ada M Example Jr.','Extension format');
foreach ([['time_out'=>'2026-01-01','presence_active'=>1,'expected'=>'Completed'],['presence_active'=>1,'expected'=>'Active'],['presence_ended_at'=>'2026-01-01','expected'=>'Ended'],['presence_last_activity'=>'2020-01-01','expected'=>'Expired'],['expected'=>'Unclosed']] as $case) slCheck(securityLogSessionLabel($case)===$case['expected'],'Session label');
$allQueries=[];
foreach (['SUPER_ADMIN','ADMIN','USER'] as $role) {
    $result=slRender(['_role'=>$role,'month'=>'9','date'=>'2026-10-25','page_no'=>2,'sessions_page'=>2,'q'=>'OLD-ID','role'=>'USER','log_type'=>'LOGIN','status'=>'SUCCESS']);
    slCheck($result['navigation']==='security_logs','Pagination overwrote shell navigation');
    $shell=file_get_contents(__DIR__.'/../php/superadmin_shell.php');
    $currentPage=$result['navigation'];
    foreach (['account_management','roles_permissions','security_logs'] as $tab) {
        preg_match('~<a href="app.php\?page='.$tab.'" class="nav-item [^\n]+~',$shell,$anchor);
        slCheck(isset($anchor[0]),'Sidebar anchor exists: '.$tab);
        ob_start();eval('?>'.$anchor[0]);$anchorHtml=ob_get_clean();
        slCheck(str_contains($anchorHtml,'class="nav-item active"')===($tab==='security_logs'),'Only Security Logs highlights');
    }
    slCheck(str_contains($result['html'],'security_logs.css') && str_contains($result['html'],'Historical Snapshot'),'Styles/snapshot render');
    foreach (['Active','Completed','Ended','Expired','Unclosed'] as $label) slCheck(str_contains($result['html'],$label),'Missing session state '.$label);
    slCheck(str_contains($result['html'],'href="app.php?page=security_logs"'), 'Clear filter route');
    slCheck(str_contains($result['html'],'date=2026-10-25') && str_contains($result['html'],'q=OLD-ID') && str_contains($result['html'],'page_no=2'),'Pagination filter preservation');
    foreach ($result['queries'] as [$sql,$params]) {
        if (str_contains($sql,'FROM security_logs sl')) {
            if ($role==='ADMIN') slCheck(str_contains($sql,"sl.role IN ('ADMIN', 'USER')"),'Admin scope');
            if ($role==='USER') slCheck(str_contains($sql,'sl.user_id = ?') && str_contains($sql,"sl.role = 'USER'"),'User scope');
        }
        if (str_contains($sql,'FROM security_logs login')) {
            slCheck(str_contains($sql,'SHA2(login.session_key, 256)') && str_contains($sql,'presence.user_id = login.user_id'),'Exact session presence correlation');
            if ($role==='ADMIN') slCheck(str_contains($sql,"login.role IN ('ADMIN', 'USER')"),'Session Admin scope');
            if ($role==='USER') slCheck(str_contains($sql,'login.user_id = ?') && str_contains($sql,"login.role = 'USER'"),'Session User scope');
        }
        slCheck(!preg_match('/^\s*(INSERT|UPDATE|DELETE|ALTER|CREATE)/i',$sql),'Read-only logs page');
        $allQueries[]=[$sql,$params];
    }
}
foreach ([['ADMIN','SUPER_ADMIN',7],['USER','ADMIN',7],['USER','USER',8]] as [$role,$eventRole,$eventUser]) {
    $result=slRender(['_role'=>$role,'_event_role'=>$eventRole,'_event_user'=>$eventUser,'view'=>9,'details_only'=>'1']);
    slCheck($result['http']===404 && !str_contains($result['html'],'Historical Snapshot'),'Unauthorized details');
}
$result=slRender(['_role'=>'USER','view'=>9,'details_only'=>'1']);slCheck(str_contains($result['html'],'Historical Snapshot') && str_contains($result['html'],'OLD-ID'),'Authorized historical details');
foreach ([['date'=>'2026-02-30','month'=>99],['log_type'=>'LOGOUT','status'=>'FAILED'],['log_type'=>'SECURITY_ACTION'],['log_type'=>'FAILED_LOGIN']] as $filters) {
    $result=slRender($filters);foreach($result['queries'] as $query)$allQueries[]=$query;
}
// Extract and run existing event-writer functions without executing their page flows.
function slFunction(string $file,string $wanted): string {
    $tokens=token_get_all(file_get_contents(__DIR__.'/../php/'.$file));$capture=false;$found=false;$depth=0;$code='';
    foreach($tokens as $token){
        if(is_array($token)&&$token[0]===T_FUNCTION){$capture=true;$code='';$found=false;}
        if(!$capture)continue;$text=is_array($token)?$token[1]:$token;$code.=$text;
        if(is_array($token)&&$token[0]===T_STRING&&!$found){if($text!==$wanted){$capture=false;continue;}$found=true;}
        if(!is_array($token)&&$token==='{')$depth++;
        if(!is_array($token)&&$token==='}'&&--$depth===0)return $code;
    }throw new RuntimeException('Function missing '.$wanted);
}
eval(slFunction('login.php','logSecurityEvent'));eval(slFunction('superadmin_session.php','superAdminSessionLog'));eval(slFunction('process_first_login_setup.php','logFirstLoginEvent'));
$pdo=new SlPdo();$_SESSION=['user_id'=>7,'security_session_key'=>'fixture-key'];
logSecurityEvent($pdo,7,'fixture',securityLogFullName($pdo->identity),'USER','LOGIN','SUCCESS');
logFirstLoginEvent($pdo,7,'fixture','Old Name','USER','FIRST_LOGIN_SETUP_COMPLETED','SUCCESS');
superAdminSessionLog($pdo,'SUPERADMIN_SESSION_STARTED','Offline verification');
$user_id=7;$userId=7;$sessionUserId=7;$username='fixture';$full_name='Old Name';$role='USER';$sessionKey='fixture-key';$fname='Old Name';$uname='fixture';$challenge=['role'=>'USER'];$sent=true;$account=['id'=>7,'username'=>'fixture','role'=>'USER'];
foreach (['logout.php','first_login_otp.php','authenticated_change_password.php','change_password.php','process_change_password.php'] as $file) {
    $source=file_get_contents(__DIR__.'/../php/'.$file);$offset=0;
    while(($start=strpos($source,'$identity = securityLogAccountIdentity(', $offset))!==false){
        $execute=strpos($source,'->execute(', $start); $end=strpos($source,']);',$execute)+3;eval(substr($source,$start,$end-$start));$offset=$end;
    }
}
slCheck(count($pdo->inserts)===11,'All authentication writers exercised; count='.count($pdo->inserts));
function notifyAdministratorEvent(PDO $pdo, int $targetId, int $logId, string $action): void {}
foreach ([['account_status.php','logAccountEvent'],['admin_management.php','logManagementEvent'],['registration_approval.php','approvalName'],['registration_approval.php','logApprovalEvent'],['deletion_requests.php','deletionName'],['deletion_requests.php','deletionLog']] as [$file,$function]) eval(slFunction($file,$function));
$target=$pdo->identity+['id'=>8,'username'=>'target','role'=>'USER'];
logAccountEvent($pdo,'ACCOUNT_UPDATED',$target);
logManagementEvent($pdo,'ADMIN_UPDATED',$target);
logApprovalEvent($pdo,'REGISTRATION_APPROVED',$target);
deletionLog($pdo,'DELETION_REQUEST_SUBMITTED',$target,'Offline test');
slCheck(count($pdo->inserts)===15,'Management audit payloads recorded');
foreach($pdo->inserts as [$sql,$params]){
    slCheck(str_contains($sql,'actor_id_number') && in_array('2026-0007',$params,true),'ID snapshot missing');
    slCheck(in_array('Ada M Example Jr.',$params,true),'Extended full name snapshot missing');
}
if (($argv[1] ?? '') === '--read-only-sql') {
    // Explicit opt-in only. This connection cannot write application data or create tables.
    $read = new PDO('mysql:host=127.0.0.1;dbname=cakey_db', 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>3]);
    $read->exec("SET time_zone = '+08:00'");
    $read->exec('SET SESSION max_statement_time = 3');
    $read->exec('SET TRANSACTION READ ONLY');
    $read->beginTransaction();
    $checked = [];
    try {
        foreach ($allQueries as [$sql,$params]) {
            $key = json_encode([$sql,$params]);
            if (isset($checked[$key])) continue;
            $query=$read->prepare('EXPLAIN '.$sql); $query->execute($params); $query->fetchAll();
            $query=$read->prepare($sql); $query->execute($params); $query->fetchAll();
            $checked[$key]=true;
        }
    } finally { $read->rollBack(); }
    echo 'PASS: '.count($checked)." distinct generated queries explained and executed in a read-only transaction. No records returned in this report.\n";
}
if(($argv[1]??'')==='queries'){echo json_encode($allQueries);exit;}
echo "PASS: offline page rendering, navigation scope, session labels, role/detail guards, SQL bindings, filters and pagination, historical snapshots, and 15 authentication/management audit payloads. No live database writes.\n";
