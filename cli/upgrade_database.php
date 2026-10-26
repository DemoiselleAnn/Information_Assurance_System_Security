<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/database_tools.php';
$options=getopt('',['plan','backup','apply','database:','backup-file:','approve-database:']);
$target=$options['database']??'';
if(!preg_match('/^[A-Za-z0-9_]+$/D',$target)||in_array($target,['mysql','sys','information_schema','performance_schema'],true)){fwrite(STDERR,"Specify --database explicitly. Use --plan, --backup or --apply.\n");exit(1);}
require __DIR__.'/../php/db.php';
$targetPdo=new PDO('mysql:host='.$host.';dbname='.$target.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$targetPdo->exec("SET time_zone='+08:00'");
$verificationDb=null;$exitCode=0;
try{
 if(isset($options['plan'])){
  $fresh=file_get_contents(__DIR__.'/../sql/final_cakedeco_database.sql');preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?\x60?(\w+)\x60? \(/',$fresh,$matches);
  $existing=frostlineTables($targetPdo);echo 'Selected database: '.$target.PHP_EOL;
  foreach($matches[1] as $table){echo $table.': '.(in_array($table,$existing,true)?'present; upgrade checks columns/indexes/FKs':'missing; upgrade will create').PHP_EOL;}
  preg_match_all('/CREATE TABLE \x60(\w+)\x60 \(([\s\S]*?)\) ENGINE=InnoDB/',$fresh,$definitions,PREG_SET_ORDER);
  foreach($definitions as $definition){$table=$definition[1];if(!in_array($table,$existing,true))continue;
   $stmt=$targetPdo->prepare('SELECT COLUMN_NAME,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$stmt->execute([$table]);$columns=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $column)$columns[$column['COLUMN_NAME']]=$column;
   preg_match_all('/^\s*\x60(\w+)\x60 ([^\n]+)/m',$definition[2],$expected,PREG_SET_ORDER);
   foreach($expected as $column){if(!isset($columns[$column[1]]))echo '  ADD COLUMN '.$table.'.'.$column[1].PHP_EOL;elseif(preg_match('/^varchar\((\d+)\)/',$column[2],$width)&&$columns[$column[1]]['CHARACTER_MAXIMUM_LENGTH']<$width[1])echo '  WIDEN '.$table.'.'.$column[1].' to at least '.$width[1].PHP_EOL;}
  }
  echo "No changes applied. This column/table inventory is not a complete dry-run: the SQL also checks enums, indexes, FKs, duplicate keys and orphans. Stop writes, use --backup, review these checks, then explicitly approve --apply.\n";exit;
 }
 if(!isset($options['backup'])&&!isset($options['apply']))throw new RuntimeException('Choose --plan, --backup or --apply');
 $snapshot=frostlineSnapshot($targetPdo);
 if(isset($options['backup'])){
  $path=sys_get_temp_dir().DIRECTORY_SEPARATOR.'frostline_'.$target.'_'.bin2hex(random_bytes(8)).'.backup.gz';
  if(file_put_contents($path,gzencode(serialize(['database'=>$target,'snapshot'=>$snapshot]),9),LOCK_EX)===false)throw new RuntimeException('Backup write failed');
 }else{
  if(($options['approve-database']??'')!==$target)throw new RuntimeException('--approve-database must exactly match the selected database');
  $path=$options['backup-file']??'';if(!is_file($path))throw new RuntimeException('A verified backup file is required');
 }
 $loaded=unserialize(gzdecode(file_get_contents($path)),['allowed_classes'=>false]);
 if(!is_array($loaded)||($loaded['database']??null)!==$target||($loaded['snapshot']??null)!==$snapshot)throw new RuntimeException('Backup differs from current selected database; stop writes and create a new backup');
 $verificationDb='frostline_sql_restore_'.bin2hex(random_bytes(6));frostlineDisposable($verificationDb);$pdo->exec('CREATE DATABASE '.frostlineIdentifier($verificationDb).' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');
 $verify=new PDO('mysql:host='.$host.';dbname='.$verificationDb.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$verify->exec("SET time_zone='+08:00'");
 frostlineRestore($verify,$loaded['snapshot']);frostlineVerifySnapshot($verify,$snapshot);
 echo 'Verified backup: '.$path.PHP_EOL.'SHA256: '.hash_file('sha256',$path).PHP_EOL;
 if(isset($options['apply'])){
  $targetPdo->exec('SET @frostline_backup_verified=DATABASE(), @frostline_upgrade_approved=DATABASE()');
  frostlineRunSql($targetPdo,file_get_contents(__DIR__.'/../sql/upgrade_existing_database.sql'));echo "Upgrade completed. Existing records were retained.\n";
 }else echo "Backup restored and verified; no application database changes applied.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);$exitCode=1;}finally{if($verificationDb!==null){frostlineDisposable($verificationDb);$pdo->exec('DROP DATABASE '.frostlineIdentifier($verificationDb));}}

exit($exitCode);
