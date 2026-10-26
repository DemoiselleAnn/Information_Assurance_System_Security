<?php
// Offline state-machine tests. No live connection, schema, emails or sessions.
require_once __DIR__.'/../php/administrator_status.php';
function ck($ok,$message){if(!$ok)throw new RuntimeException($message);}
class SuperAdminLimitReached extends RuntimeException {}
function usableSuperAdminCount(PDO $pdo, ?int $excluded=null): int{return $pdo->usable;}
function endUserPresence(PDO $pdo,int $id,?string $key): void{$pdo->ended++;}
function logManagementEvent(PDO $pdo,string $action,array $target,?string $reason=null,bool $strict=false): void {
 ck($strict && $pdo->inTransaction(),'Audit must be strict and transactional');
 if($pdo->failAudit)throw new RuntimeException('offline audit failure');
 $pdo->events[]=$action;
}
class AdminStatement extends PDOStatement {
 public function __construct(private AdminDb $db,private string $sql){}
 public function execute(?array $params=null):bool{
  ck(substr_count($this->sql,'?')===count($params),'SQL binding count');
  if(str_starts_with($this->sql,'UPDATE')){
   ck(str_contains($this->sql,'AND status = ? AND first_login_required = 0'),'Conditional update required');
   ck($params[1]===$this->db->target['id'] && $params[2]===$this->db->target['role'] && $params[3]===$this->db->target['status'],'Target constraint');
   if(!$this->db->race)$this->db->target['status']=$params[0];
  }
  return true;
 }
 public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0):mixed{return $this->db->target;}
 public function rowCount():int{return $this->db->race?0:1;}
}
class AdminDb extends PDO{
 public array|false $target;public array $events=[];public int $ended=0,$usable=1;public bool $race=false,$failAudit=false,$txn=false;private array $snapshot;
 public function __construct(string $role='ADMIN',string $status='ACTIVE'){$this->target=['id'=>22,'role'=>$role,'status'=>$status,'first_login_required'=>0];}
 public function prepare(string $query,array $options=[]):PDOStatement|false{return new AdminStatement($this,$query);}
 public function beginTransaction():bool{$this->snapshot=[$this->target,$this->events,$this->ended];return $this->txn=true;}
 public function inTransaction():bool{return $this->txn;}
 public function commit():bool{$this->txn=false;return true;}
 public function rollBack():bool{[$this->target,$this->events,$this->ended]=$this->snapshot;$this->txn=false;return true;}
}
$_SESSION=['user_id'=>11,'role'=>'SUPER_ADMIN'];
foreach(['ADMIN','SUPER_ADMIN'] as $role){
 $db=new AdminDb($role);
 ck(transitionAdministratorStatus($db,22,'block')[0],'Block '.$role);
 ck($db->target['status']==='BLOCKED' && $db->events===['ADMIN_BLOCKED'] && $db->ended===1,'Block effects');
 ck(!transitionAdministratorStatus($db,22,'block')[0] && count($db->events)===1,'Duplicate block');
 ck(transitionAdministratorStatus($db,22,'unblock')[0],'Unblock '.$role);
 ck($db->target['status']==='INACTIVE' && $db->events===['ADMIN_BLOCKED','ADMIN_UNBLOCKED'],'Unblock effects');
 ck(!transitionAdministratorStatus($db,22,'unblock')[0] && count($db->events)===2,'Duplicate unblock');
}
foreach(['BLOCKED','PENDING','REJECTED'] as $status){$db=new AdminDb('ADMIN',$status);ck(!transitionAdministratorStatus($db,22,'block')[0] && !$db->events,'Invalid block');}
foreach(['ACTIVE','INACTIVE','PENDING','REJECTED'] as $status){$db=new AdminDb('ADMIN',$status);ck(!transitionAdministratorStatus($db,22,'unblock')[0] && !$db->events,'Invalid unblock');}
$db=new AdminDb('USER');ck(!transitionAdministratorStatus($db,22,'block')[0],'Regular User target');
$db=new AdminDb();$db->target=false;ck(!transitionAdministratorStatus($db,22,'block')[0],'Missing target');
$db=new AdminDb();$db->target['first_login_required']=1;ck(!transitionAdministratorStatus($db,22,'block')[0],'Incomplete setup');
$db=new AdminDb();ck(!transitionAdministratorStatus($db,11,'block')[0],'Self protection');
foreach(['ADMIN','USER'] as $role){$_SESSION['role']=$role;ck(!transitionAdministratorStatus($db,22,'block')[0],'Unauthorized actor');}$_SESSION['role']='SUPER_ADMIN';
$db=new AdminDb('SUPER_ADMIN','BLOCKED');$db->usable=2;ck(transitionAdministratorStatus($db,22,'unblock')===[false,'SUPER_ADMIN_LIMIT_UNBLOCK'] && $db->target['status']==='BLOCKED','Super Admin limit');
foreach(['race','failAudit'] as $failure){$db=new AdminDb();$db->$failure=true;ck(!transitionAdministratorStatus($db,22,'block')[0] && $db->target['status']==='ACTIVE' && !$db->events && !$db->ended,'Rollback '.$failure);}
// Execute actual list query construction, verifying all statuses with combined search.
$source=file_get_contents(__DIR__.'/../php/admin_management.php');
$start=strpos($source,'$q            =');$end=strpos($source,'$stmt = $pdo->prepare($sql);',$start);$query=substr($source,$start,$end-$start);
foreach(['','ACTIVE','INACTIVE','BLOCKED','PENDING','REJECTED'] as $filter){$_GET=['q'=>'2026-','status'=>$filter];eval($query);ck(count($params)===($filter===''?6:7),'Search + status bindings');if($filter!=='')ck(end($params)===$filter && str_contains($sql,'AND status = ?'),'Status SQL '.$filter);}
// Execute actual edit UPDATE payload with malicious status values; none reaches SQL.
$start=strpos($source,'$stmt = $pdo->prepare(',strpos($source,'// ── Update ─'));$end=strpos($source,'$updatedAdmin =',$start);$edit=substr($source,$start,$end-$start);
class EditStatement extends PDOStatement{public function __construct(private string $sql){}public function execute(?array $params=null):bool{ck(!str_contains($this->sql,'status =') && !str_contains($this->sql,'role = ?'),'Protected columns absent');ck(count($params)===6 && end($params)===22,'Edit bindings');return true;}}
class EditDb extends PDO{public function __construct(){}public function prepare(string $query,array $options=[]):PDOStatement|false{return new EditStatement($query);}}
$pdo=new EditDb();$firstName='Ada';$middleName='';$lastName='Example';$username='example';$email='test@example.invalid';$targetId=22;
foreach(['BLOCKED','ACTIVE','INVALID'] as $forged){$_POST=['status'=>$forged];eval($edit);}
$form=substr($source,strpos($source,'id="administrator-create-form"'));
ck(!str_contains($form,'name="status"'),'Edit status control removed');
ck(str_contains($source,'ADMIN_UPDATED'),'Update event preserved');
ck(str_contains($source,"requireSuperAdminSlot($".'pdo)'),'Creation limit retained');
echo "PASS: actual transition helper, duplicate/stale/invalid requests, role/self/setup restrictions, Super Admin limit, conditional update, audit/presence rollback, search/status SQL and forged edit status. No live database used.\n";
