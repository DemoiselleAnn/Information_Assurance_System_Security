<?php
require_once __DIR__ . '/../php/profile_edit.php';
function assertProfile($ok, $message) { if (!$ok) throw new RuntimeException($message); }
$input = array_fill_keys(array_keys(profileEditableFields('USER')), '');
$input += [];
$input = array_replace($input, ['first_name'=>'Ada','last_name'=>'Example','email'=>'ada@example.invalid','birthdate'=>'2000-02-29','sex'=>'private']);
$cases = [];
$sets = [
    'first_name'=>[['John',true],['john',true],['Maria Clara',true],['Anna-Marie',true],['John123',false],['0',false],['456',false],['John@',false],["O'Neil",false],['José',false],['jOhn',false],['John clara',false],['John  Smith',false],['Jooohn',false],['',false],['  John  ',true],[str_repeat('A',51),false],['A'.str_repeat('ab',24).'a',true]],
    'middle_name'=>[['',true],['  ',true],['Anne',true],['Anne2',false]],
    'last_name'=>[['',false],['Smith',true],['Smith999',false]],
    'extension_name'=>[['',true],['Jr.',true],['Jr',true],['SR.',true],['II',true],['III',true],['IV',true],['iX',true],['X',true],['XI',false],['123',false],['other',false]],
    'purok'=>[['',true],['Purok 3',true],['123 Main Street',true],['Main Street',true],['Purok3',false],['Park Avenue',false],['123',false],['main Street',false],['Street@',false]],
    'barangay'=>[['',true],['Barangay Buhang',true],['Barangay',true],['Buhang',false],['Barangay 3',false],['barangay Buhang',false],['BarangayBuhang',false]],
    'city_municipality'=>[['',true],['Magallanes',true],['San-Jose',true],['magallanes',false],['3 City',false],['San  Jose',false]],
    'province'=>[['',true],['Agusan del Norte',true],['Agusan  Norte',false],['Agusan2',false]],
    'country'=>[['',true],['Philippines',true],['philippines',false],['Country!',false]],
    'zip_code'=>[['',true],['8604',true],['12345',true],['123',false],['123456',false],['86A4',false]],
];
foreach (['USER','ADMIN','SUPER_ADMIN'] as $role) {
    foreach ($sets as $field=>$values) foreach ($values as [$value,$expected]) {
        $submitted = array_replace($input, [$field=>$value]);
        try { profileEditValues($submitted,$role); $valid=true; }
        catch (InvalidArgumentException $e) { $valid=false; }
        assertProfile($valid === $expected, "$role $field: ".json_encode($value));
        if ($role === 'USER') $cases[] = compact('field','value','expected');
    }
    $legacy = array_replace($input, ['first_name'=>"O'Neil",'barangay'=>'Buhang','country'=>'philippines','middle_name'=>null,'zip_code'=>'OLD']);
    $changed = array_replace($legacy,['purok'=>'Purok 3','middle_name'=>'']);
    $saved = profileEditValues($changed,$role,$legacy);
    assertProfile($saved['barangay']==='Buhang' && $saved['first_name']==="O'Neil" && $saved['middle_name']===null,'Legacy values must stay exact');
    try { profileEditValues(array_replace($changed,['barangay'=>'Other Legacy']),$role,$legacy); throw new RuntimeException('Changed legacy address accepted'); }
    catch (InvalidArgumentException $e) {}
    foreach ([[],null,42] as $bad) {
        try { profileEditValues(array_replace($input,['first_name'=>$bad]),$role); throw new RuntimeException('Malformed field accepted'); }
        catch (InvalidArgumentException $e) {}
    }
}
if (($argv[1] ?? '') === '--json') echo json_encode($cases,JSON_THROW_ON_ERROR);
else echo 'PASS: '.count($cases)." rule cases across all three roles; optional fields, exact legacy preservation, changed legacy rejection, and malformed input.\n";
