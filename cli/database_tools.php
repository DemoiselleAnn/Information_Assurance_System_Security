<?php
// CLI support only; no connection is opened by this library.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
function frostlineRunSql(PDO $pdo,string $sql): void {
 $delimiter=';';$buffer='';
 foreach(preg_split('/\r?\n/',$sql) as $line){
  if(preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i',$line,$m)){
   if(trim($buffer)!=='')throw new RuntimeException('SQL delimiter changed with pending statement');
   $delimiter=$m[1];continue;
  }
  if(preg_match('/^\s*--/',$line)||trim($line)==='')continue;
  $buffer.=$line."\n";
  if(str_ends_with(rtrim($buffer),$delimiter)){
   $statement=substr(rtrim($buffer),0,-strlen($delimiter));
   try{$pdo->exec($statement);}catch(Throwable $e){throw new RuntimeException('SQL step failed: '.substr(preg_replace('/\s+/',' ',$statement),0,100).' ['.$e->getMessage().']',0,$e);}
   $buffer='';
  }
 }
 if(trim($buffer)!=='')throw new RuntimeException('Unterminated SQL statement');
}
function frostlineTables(PDO $pdo):array{return $pdo->query('SHOW FULL TABLES WHERE Table_type = \'BASE TABLE\'')->fetchAll(PDO::FETCH_COLUMN);}
function frostlineIdentifier(string $id):string{return chr(96).str_replace(chr(96),chr(96).chr(96),$id).chr(96);}
function frostlineRows(PDO $pdo,string $table):array{$rows=$pdo->query('SELECT * FROM '.frostlineIdentifier($table))->fetchAll(PDO::FETCH_ASSOC);usort($rows,fn($a,$b)=>strcmp(serialize($a),serialize($b)));return $rows;}
function frostlineSnapshot(PDO $pdo):array {
 if($pdo->inTransaction())throw new RuntimeException('Snapshot requires an independent connection');
 // Do not call a table-only backup complete if custom programmable objects exist.
 foreach(['VIEWS'=>'TABLE_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','TRIGGERS'=>'TRIGGER_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $kind=>$schemaColumn){
  if((int)$pdo->query('SELECT COUNT(*) FROM information_schema.'.$kind.' WHERE '.$schemaColumn.'=DATABASE()')->fetchColumn()>0)throw new RuntimeException('Custom views/routines/triggers/events require a full reviewed SQL backup; table-only backup refused');
 }
 $pdo->exec('SET TRANSACTION READ ONLY');$pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
 try{$result=[];foreach(frostlineTables($pdo) as $t){$result[$t]=['ddl'=>$pdo->query('SHOW CREATE TABLE '.frostlineIdentifier($t))->fetch(PDO::FETCH_NUM)[1],'rows'=>frostlineRows($pdo,$t)];}ksort($result);return $result;}finally{$pdo->rollBack();}
}
function frostlineDisposable(string $name):void{if(!preg_match('/^frostline_sql_(fresh|restore|legacy)_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Refused non-disposable database');}
function frostlineRestore(PDO $pdo,array $snapshot):void {
 $name=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();frostlineDisposable($name);
 if(frostlineTables($pdo))throw new RuntimeException('Restore requires an empty database');
 $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
 try{foreach($snapshot as $t=>$data)$pdo->exec($data['ddl']);foreach($snapshot as $t=>$data){if(!$data['rows'])continue;$columns=array_keys($data['rows'][0]);$stmt=$pdo->prepare('INSERT INTO '.frostlineIdentifier($t).' ('.implode(',',array_map('frostlineIdentifier',$columns)).') VALUES ('.implode(',',array_fill(0,count($columns),'?')).')');foreach($data['rows'] as $row)$stmt->execute(array_values($row));}}finally{$pdo->exec('SET FOREIGN_KEY_CHECKS=1');}
}
function frostlineVerifySnapshot(PDO $pdo,array $snapshot):void {
 foreach($snapshot as $t=>$data){$ddl=$pdo->query('SHOW CREATE TABLE '.frostlineIdentifier($t))->fetch(PDO::FETCH_NUM)[1];if($ddl!==$data['ddl']||frostlineRows($pdo,$t)!==$data['rows'])throw new RuntimeException('Restore verification failed for '.$t);}
 if(count(frostlineTables($pdo))!==count($snapshot))throw new RuntimeException('Restore table mismatch');
}
