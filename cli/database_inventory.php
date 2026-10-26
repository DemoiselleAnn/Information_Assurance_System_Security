<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../php/db.php';
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$out=['version'=>$pdo->query('SELECT VERSION()')->fetchColumn(),'tables'=>[]];
foreach($tables as $t){$out['tables'][$t]=['ddl'=>$pdo->query('SHOW CREATE TABLE '.chr(96).str_replace(chr(96),chr(96).chr(96),$t).chr(96))->fetch(PDO::FETCH_NUM)[1],'count'=>(int)$pdo->query('SELECT COUNT(*) FROM '.chr(96).str_replace(chr(96),chr(96).chr(96),$t).chr(96))->fetchColumn()];}
echo json_encode($out,JSON_PRETTY_PRINT);
