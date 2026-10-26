<?php
// Actual handler execution against in-memory PDO; never connects to a database.
require_once __DIR__.'/../php/deletion_history.php';
require_once __DIR__.'/../php/profile_data.php';
function verify($ok,$message){if(!$ok)throw new RuntimeException($message);}
function notifyDeletionSubmitted(PDO $pdo,int $id):void{$pdo->notifications[]=['submit',$id];}
function notifyDeletionDecision(PDO $pdo,int $id,int $requester,string $status):void{$pdo->notifications[]=[$status,$id,$requester];}
$source=file_get_contents(__DIR__.'/../php/deletion_requests.php');
$functions=substr($source,strpos($source,'function deletionName'),strpos($source,'if (' . '$_SERVER' . "['REQUEST_METHOD'] === 'POST')")-strpos($source,'function deletionName'));
$functions=preg_replace('/function deletionRedirect\(string \$message\): void \{.*?\n\}/s','function deletionRedirect(string $message): void {}',$functions);
eval($functions);
class RequestStatement extends PDOStatement {
 private mixed $rows=[];private int $affected=1;
 public function __construct(private RequestDb $db,private string $sql){}
 public function execute(?array $params=null):bool{
  $params??=[];verify(substr_count($this->sql,'?')===count($params),'Binding count '.$this->sql);
  $this->db->queries[]=[$this->sql,$params];
  if(str_contains($this->sql,'SELECT COUNT(*) FROM deletion_requests')) $this->rows=[[23]];
  elseif(str_starts_with($this->sql,'SELECT dr.*')) {
    $this->rows=[array_merge($this->db->request,['employee_id'=>'TEST22','full_name'=>'Ada Example Jr.','username'=>'target','email'=>'test@example.invalid','account_role'=>'USER','reason'=>'Fixture reason','created_at'=>'2026-09-21 12:00:00','requester_name'=>'Admin Fixture','live_created_at'=>null,'live_email'=>null,'live_role'=>null,'live_status'=>null,'deletion_origin'=>null,'live_birthdate'=>null,'live_age'=>null,'live_sex'=>null,'live_purok'=>null,'live_barangay'=>null,'live_city'=>null,'live_province'=>null,'live_country'=>null,'live_zip'=>null])];
  }
  elseif(str_starts_with($this->sql,'SELECT * FROM deletion_requests')) $this->rows=$this->db->request['status']==='PENDING'?[$this->db->request]:[];
  elseif(str_starts_with($this->sql,'SELECT id FROM deletion_requests')) $this->rows=$this->db->pending?[[91]]:[];
  elseif(str_contains($this->sql,'SELECT id_number, username')) $this->rows=[$this->db->actor];
  elseif(str_starts_with($this->sql,'SELECT id, id_number')) $this->rows=$this->db->target?[$this->db->target]:[];
  elseif(str_starts_with($this->sql,'INSERT INTO deletion_requests')){$this->db->snapshots[]=$params;$this->db->pending=true;}
  elseif(str_starts_with($this->sql,'INSERT INTO security_logs')){
    if($this->db->failLog)throw new PDOException('offline log failure');
    $this->db->logs[]=$params;
  }
  elseif(str_starts_with($this->sql,'DELETE FROM users')){
   $this->affected=$this->db->target && $this->db->target['role']!=='SUPER_ADMIN'?1:0;
   if($this->affected)$this->db->target=null;
  }
  elseif(str_starts_with($this->sql,'UPDATE deletion_requests')){$this->db->request['status']=$params[0];}
  return true;
 }
 public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return array_shift($this->rows)?:false;}
 public function fetchColumn(int $column=0):mixed{$row=array_shift($this->rows);return $row?array_values($row)[$column]:false;}
 public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args):array{return $this->rows;}
 public function rowCount():int{return $this->affected;}
}
class RequestDb extends PDO{
 public array $queries=[],$logs=[],$notifications=[],$snapshots=[],$request,$actor;public ?array $target;public bool $pending=false,$failLog=false,$txn=false;private array $saved;
 public function __construct(){
 $this->actor=['id_number'=>'ACTOR','username'=>'reviewer','first_name'=>'Review','middle_name'=>'','last_name'=>'Actor','extension_name'=>'','role'=>'SUPER_ADMIN'];
 $this->target=['id'=>22,'id_number'=>'TEST22','first_name'=>'Ada','middle_name'=>'','last_name'=>'Example','extension_name'=>'Jr.','username'=>'target','email'=>'test@example.invalid','role'=>'USER','status'=>'INACTIVE','created_at'=>'2026-09-01 10:00:00'];
 $this->request=['id'=>91,'user_id'=>22,'username'=>'target','employee_id'=>'TEST22','full_name'=>'Ada Example Jr.','requested_by'=>33,'status'=>'PENDING'];
 }
 public function prepare(string $query,array $options=[]):PDOStatement|false{return new RequestStatement($this,$query);}
 public function lastInsertId(?string $name=null):string|false{return '91';}
 public function beginTransaction():bool{$this->saved=[$this->target,$this->request,$this->pending,$this->logs,$this->snapshots,$this->notifications];return $this->txn=true;}
 public function commit():bool{$this->txn=false;return true;}
 public function inTransaction():bool{return $this->txn;}
 public function rollBack():bool{[$this->target,$this->request,$this->pending,$this->logs,$this->snapshots,$this->notifications]=$this->saved;$this->txn=false;return true;}
}
$start=strpos($source,'if (' . '$_SERVER' . "['REQUEST_METHOD'] === 'POST')");$end=strpos($source,'$viewId =',$start);
$handler=substr($source,$start,$end-$start);$handler=str_replace('deletionRedirect(','return deletionRedirect(',$handler);
if (($argv[1] ?? '') === '--invalid-csrf') {
 $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>[]];$_SESSION=['deletion_requests_csrf'=>'fixture'];
 $csrfToken='fixture';$csrfKey='deletion_requests_csrf';$isDeletionReviewer=true;$canSubmitDeletionRequest=false;$pdo=new RequestDb();
 eval($handler);throw new RuntimeException('Invalid CSRF reached handler');
}
function exercise(string $action,RequestDb $pdo):array{
 global $handler;
 $_SESSION=['user_id'=>11,'role'=>'SUPER_ADMIN','deletion_requests_csrf'=>'fixture'];$_SERVER['REQUEST_METHOD']='POST';
 $_POST=['action'=>$action,'request_id'=>91,'user_id'=>22,'csrf_token'=>'fixture','reason'=>str_repeat('reason ',70)];
 $csrfKey='deletion_requests_csrf';$csrfToken='fixture';$errors=[];$canSubmitDeletionRequest=$action==='submit';$isDeletionReviewer=!$canSubmitDeletionRequest;
 eval($handler);return $errors;
}
$db=new RequestDb();verify(exercise('submit',$db)===[],'Submit');verify($db->pending && count($db->snapshots)===1 && $db->snapshots[0][2]==='Ada Example Jr.','Snapshot');verify($db->snapshots[0][7]===trim(str_repeat('reason ',70)),'Full reason retained');verify(strlen($db->logs[0][10])<=255,'Bounded audit details');verify(count($db->notifications)===1,'Submit notification');
verify(exercise('submit',$db)!==[] && count($db->snapshots)===1,'Duplicate pending prevented');
$db=new RequestDb();verify(exercise('approve',$db)===[] && !$db->target && $db->request['status']==='APPROVED','Approve correct account');verify(count($db->logs)===2 && $db->logs[1][4]==='DELETION_REQUEST_APPROVED','Approval logs');verify(str_starts_with($db->logs[1][10],'Deletion history #91; Origin: Approved Request;'),'Explicit request origin');verify(count($db->notifications)===1,'Decision notification');verify(exercise('approve',$db)!==[] && count($db->logs)===2,'Repeated approval rejected');
$db=new RequestDb();verify(exercise('reject',$db)===[] && $db->target && $db->request['status']==='REJECTED','Reject retains account');verify($db->logs[0][4]==='DELETION_REQUEST_REJECTED','Rejection log');
$db=new RequestDb();$db->target['role']='SUPER_ADMIN';verify(exercise('approve',$db)!==[] && $db->target && !$db->logs,'Protected target');
$db=new RequestDb();$db->failLog=true;verify(exercise('approve',$db)!==[] && $db->target && $db->request['status']==='PENDING','Audit rollback');
$db=new RequestDb();$db->failLog=true;verify(exercise('submit',$db)!==[] && !$db->snapshots && !$db->pending,'Submission rollback');
verify(deletionAccountCreated(null)==='Unavailable','Missing original timestamp');verify(deletionAccountCreated('bad')==='Unavailable','Invalid timestamp');verify(deletionAccountCreated('2026-09-01 10:00:00')==='2026-09-01 10:00','Original timestamp');
foreach(['direct'=>'Direct Deletion','request'=>'Approved Request', ''=>'APPROVED (origin unavailable)'] as $origin=>$label)verify(deletionHistoryLabel(['status'=>'APPROVED','deletion_origin'=>$origin])===$label,'Origin label');
verify(!str_contains($source,"live_created_at'] ?: $"."viewRequest['created_at']"),'Request date cannot replace account date');
$direct=file_get_contents(__DIR__.'/../php/account_management.php');verify(str_contains($direct,"Origin: Direct Deletion;"),'Direct evidence marker');verify(strpos($direct,'$snapshotStmt =')<strpos($direct,'$historyId ='),'History ID captured after snapshot');
echo "PASS: actual submit/approve/reject handlers, snapshots/full reasons, duplicate and reviewed requests, target protection, audit rollback, notification calls, bounded logs, date/origin labels. Offline PDO only.\n";

// Render actual historical detail, filters, action links and middle-page navigation.
$_GET=['page'=>'deletion_requests','view'=>91,'q'=>'TEST22','status'=>'APPROVED','p'=>2];
$pdo=new RequestDb();$pdo->request['status']='APPROVED';$isDeletionReviewer=true;$isSuperAdmin=true;$canSubmitDeletionRequest=false;$errors=[];$message='';$csrfToken='fixture';
$tail=substr($source,strpos($source,'$viewId ='));
$tail=preg_replace('/<\?php require __DIR__ .*record_details_modal.php.*; \?>/','',$tail);
ob_start();eval($tail);$html=ob_get_clean();
verify(str_contains($html,'Account Created</dt><dd>Unavailable'),'Unavailable account creation rendered');
verify(str_contains($html,'Request Date</dt><dd>2026-09-21 12:00'),'Separate request date rendered');
verify(str_contains($html,'APPROVED (origin unavailable)'),'No inferred historical origin');
verify(str_contains($html,'Previous deletion request page') && str_contains($html,'Next deletion request page'),'Previous/Next on middle page');
verify(str_contains($html,'q=TEST22') && str_contains($html,'status=APPROVED'),'Pager preserves filters');
verify(!str_contains($html,'Approve Deletion'),'Reviewed record cannot be approved again');
echo "PASS: actual historical detail rendering, separate dates, filter-preserving Previous/Next and reviewed-action visibility.\n";

// All supported filters and search columns use bound values in actual query construction.
$start=strpos($source,"$"."q = trim(");$end=strpos($source,'$countStmt =',$start);$filters=substr($source,$start,$end-$start);
foreach(['','PENDING','APPROVED','REJECTED'] as $status){$_GET=['q'=>'TEST22','status'=>$status];$isDeletionReviewer=true;eval($filters);verify(count($params)===($status===''?4:5),'Filter bindings');if($status!=='')verify(end($params)===$status,'Selected filter value');verify(str_contains($where,'dr.employee_id LIKE ?')&&str_contains($where,'dr.full_name LIKE ?')&&str_contains($where,'dr.username LIKE ?'),'Search columns');}
verify(str_contains($html,'href="app.php?page=deletion_requests">Clear'),'Clear filter link');
echo "PASS: actual filter/search bindings and Clear link.\n";
