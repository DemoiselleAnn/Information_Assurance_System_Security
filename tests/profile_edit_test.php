<?php
require_once __DIR__ . '/../php/profile_edit.php';
function verify($ok, $message) { if (!$ok) throw new RuntimeException($message); }
class EditStatement extends PDOStatement {
    public function __construct(private EditDb $db, private string $sql) {}
    public function execute(?array $params=null): bool { $this->db->calls[] = [$this->sql,$params]; return true; }
    public function fetchColumn(int $column=0): mixed { return $this->db->duplicate ? 99 : false; }
}
class EditDb extends PDO {
    public array $calls = [];
    public bool $duplicate = false;
    public function __construct() {}
    public function prepare(string $query, array $options=[]): PDOStatement|false { return new EditStatement($this,$query); }
}
$input = array_fill_keys(array_keys(profileEditableFields('USER')), '');
$input = array_replace($input, ['first_name'=>'Ada','last_name'=>'Example','email'=>'ada@example.invalid','birthdate'=>'2000-02-29','sex'=>'private']);
foreach (['USER','ADMIN','SUPER_ADMIN'] as $role) {
    $_SESSION = ['user_id'=>41];
    $db = new EditDb();
    $values = saveOwnProfile($db, ['role'=>$role], $input + ['user_id'=>99,'id'=>99,'role'=>'SUPER_ADMIN','status'=>'BLOCKED','age'=>999]);
    [$sql,$params] = $db->calls[count($db->calls)-1];
    verify(array_slice($params,-2) === [41,$role], 'Session ownership and role guard');
    verify(!str_contains(explode(' WHERE ', $sql)[0], 'role=') && !str_contains(explode(' WHERE ', $sql)[0], 'status='), 'Protected fields');
    verify(($values['age'] ?? null) === ($role === 'USER' ? profileAge($input['birthdate']) : null), 'Derived age and role field boundary');
    verify(isset($values['email']) === ($role === 'USER'), 'Existing management field boundary');
}
foreach (['first_name'=>'','last_name'=>[], 'email'=>'bad','birthdate'=>'2099-02-30','sex'=>'invalid','zip_code'=>str_repeat('1',11)] as $field=>$value) {
    try { profileEditValues(array_replace($input, [$field=>$value]), 'USER'); throw new RuntimeException('Invalid '.$field.' accepted'); }
    catch (InvalidArgumentException $e) {}
}
$db = new EditDb(); $db->duplicate=true;
try { saveOwnProfile($db,['role'=>'USER'],$input); throw new RuntimeException('Duplicate accepted'); }
catch (InvalidArgumentException $e) { verify(count($db->calls)===1,'Duplicate must not write'); }
echo "PASS: all role save payloads, forged identity/role/status, derived age, invalid values, and duplicate email. Offline PDO; no database changed.\n";
