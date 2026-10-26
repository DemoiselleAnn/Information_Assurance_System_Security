<?php
require_once __DIR__.'/../php/profile_data.php';
function check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$today = new DateTimeImmutable('2026-10-25',new DateTimeZone('Asia/Manila'));
foreach (['2000-01-01'=>26,'2000-10-25'=>26,'2000-10-26'=>25,'2000-12-31'=>25,'2026-10-25'=>0] as $dob=>$expected) check(profileAge($dob,$today)===$expected,$dob);
foreach (['','0000-00-00','0000-01-01','2000-02-30','2025-02-29','2026-10-26','01/01/2000',null,[], '2000-01-01junk'] as $dob) check(profileAge($dob,$today)===null,'Invalid date accepted');
check(profileAge('2000-02-29',new DateTimeImmutable('2025-02-28'))===24,'Leap birthday before anniversary');
check(profileAge('2000-02-29',new DateTimeImmutable('2025-03-01'))===25,'Leap birthday after anniversary');
check(profileBirthdateLabel('2000-01-01')==='January 1, 2000','Date format');
check(profileAgeLabel('bad')==='Not provided','Missing age');
// Render each management role using the actual profile template and an offline PDO.
class ProfileStatement extends PDOStatement {
    public function __construct(private ProfileDb $db,private string $sql) {}
    public function execute(?array $params=null): bool {
        check($params===[$_SESSION['user_id']],'Profile query must use session ID');
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $orientation=PDO::FETCH_ORI_NEXT,int $offset=0): mixed {return $this->db->record;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,mixed ...$args): array {return [];}
}
class ProfileDb extends PDO {
    public array $record;
    public function __construct() {}
    public function prepare(string $query,array $options=[]): PDOStatement|false { return new ProfileStatement($this,$query); }
}
// Skip only connection/session bootstrap in this offline harness.
$source=str_replace("\r\n", "\n", file_get_contents(__DIR__.'/../php/my_profile.php'));
require_once __DIR__.'/../php/profile_edit.php';
$source=str_replace("require_once __DIR__ . '/profile_edit.php';", '', $source);
$source=preg_replace('/^require_once .*session_bootstrap.php.*;$/m','',$source);
$source=preg_replace('/^require_once .*db.php.*;$/m','',$source);
$source=str_replace("require_once __DIR__ . '/profile_data.php';",'', $source);
$source=str_replace("header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');",'', $source);
$source=preg_replace('/function profileText\(\$value\): string \{.*?\n\}/s','',$source);
function profileText($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
$pdo=new ProfileDb(); $_SERVER['REQUEST_METHOD']='GET'; $_SESSION=['user_id'=>41];
foreach (['SUPER_ADMIN','ADMIN','USER'] as $role) {
    $pdo->record=array_fill_keys(['id_number','first_name','middle_name','last_name','extension_name','username','email','role','status','birthdate','age','sex','purok','barangay','city_municipality','province','country','zip_code','created_at'],'');
    $pdo->record=array_replace($pdo->record,['id'=>41,'first_name'=>'Ada','last_name'=>'Example','extension_name'=>'Jr.','role'=>$role,'status'=>'ACTIVE','birthdate'=>'2000-01-01','age'=>18]);
    ob_start(); eval('?>'.$source); $html=ob_get_clean();
    check(str_contains($html,'Ada Example Jr.'),'Full name extension');
    check(str_contains($html,profileAgeLabel('2000-01-01')),'Calculated age '.$role);
    check(!str_contains($html,'18 years'),'Stored age leaked');
    check(str_contains($html,'Not provided'),'Missing address');
}
echo "Profile date and role rendering tests passed. No database accessed.\n";

// Execute the real creation INSERT with captured parameters, without creating an account.
$adminSource=file_get_contents(__DIR__.'/../php/admin_management.php');
check(!str_contains($adminSource, "'2000-01-01', 18"),'Fabricated creation values remain');
check(str_contains($adminSource, 'name="birthdate"'),'Creation form must collect birthdate');
$start=strpos($adminSource, '$stmt = $pdo->prepare(', strpos($adminSource,'$idNumber = generateAccountId'));
$end=strpos($adminSource,'$newId', $start);
$insert=substr($adminSource,$start,$end-$start);
class InsertStatement extends PDOStatement {
 public function __construct(private string $sql) {}
 public function execute(?array $params=null): bool {
  check(substr_count($this->sql,'?')===count($params),'Creation parameter count');
  check($params[4]==='1991-12-31' && $params[5]===profileAge('1991-12-31'),'Genuine DOB and derived stored age');
  check(str_contains($this->sql,"'INACTIVE', 1"),'Initial status/setup changed');
  return true;
 }
}
class InsertDb extends PDO { public function __construct(){} public function prepare(string $query,array $options=[]): PDOStatement|false {return new InsertStatement($query);} }
function temporaryCredentialExpiresAt(){return '2026-10-25 20:00:00';}
$pdo=new InsertDb(); $idNumber='TEST'; $firstName='Ada';$middleName='';$lastName='Example';$birthdate='1991-12-31';$age=profileAge($birthdate);$username='offline';$hashedPassword='offline';$email='test@example.invalid';$selectedRole='ADMIN';
eval($insert);
$legacy=file_get_contents(__DIR__.'/../php/edit_profile.php');
check(str_contains($legacy, 'http_response_code(405)'), 'Legacy POST must be rejected');
check(!str_contains($legacy, 'UPDATE users'), 'Legacy handler must not write');
check(str_contains($legacy, 'page=my_profile&edit=1'), 'Legacy GET opens modern editor');
check(str_contains($source, 'saveOwnProfile($pdo, $profile, $_POST)'), 'Current shared save handler');

echo "Creation payload and profile editor checks passed. No database accessed.\n";
