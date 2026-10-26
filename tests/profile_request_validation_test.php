<?php
require_once __DIR__ . '/../php/profile_edit.php';
class RequestProfileStatement extends PDOStatement {
    public function __construct(private RequestProfileDb $db) {}
    public function execute(?array $params=null): bool { return true; }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed { return $this->db->record; }
}
class RequestProfileDb extends PDO {
    public array $record;
    public int $updates=0;
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        if (str_starts_with($query,'UPDATE')) $this->updates++;
        return new RequestProfileStatement($this);
    }
}
function requireCompletedAccountSetup(PDO $pdo): void {}
$role=$argv[1] ?? 'USER';
$csrf=($argv[2] ?? '') === 'csrf';
$pdo=new RequestProfileDb();
$pdo->record=array_fill_keys(array_keys(profileEditableFields('USER')),'');
$pdo->record=array_replace($pdo->record,['id'=>41,'role'=>$role,'status'=>'ACTIVE','first_name'=>'Ada','last_name'=>'Example','email'=>'ada@example.invalid','birthdate'=>'2000-01-01','sex'=>'private']);
$_SESSION=['user_id'=>41,'username'=>'offline','profile_csrf'=>'test-token'];
$_GET=['page'=>'my_profile','view'=>'profile'];
$_SERVER['REQUEST_METHOD']='POST';
$_POST=$pdo->record + ['action'=>'edit_profile','csrf_token'=>$csrf?'wrong':'test-token'];
$_POST['first_name']='John123'; $_POST['user_id']=99; $_POST['id']=99; $_POST['role']='SUPER_ADMIN'; $_POST['status']='BLOCKED';
$file=$role==='USER'?'customer_dashboard.php':'my_profile.php';
$source=file_get_contents(__DIR__.'/../php/'.$file);
$source=str_replace("\r\n","\n",$source);
$source=preg_replace('/^require_once .*;$/m','',$source);
$end=strpos($source,$role==='USER'?'$displayName = trim(':'function profileText(');
$source=substr($source,0,$end);
if ($csrf) register_shutdown_function(static function() { echo '\nHTTP '.http_response_code(); });
eval('?>'.$source);
if ($pdo->updates || !isset($profileFieldErrors['first_name'])) throw new RuntimeException('Invalid forged request reached database or failed to display field error');
echo 'PASS: '.$role." real POST handler rejected invalid name and forged identity/role/status without an update.\n";
