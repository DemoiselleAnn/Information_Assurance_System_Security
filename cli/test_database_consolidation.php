<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/database_tools.php';
require __DIR__.'/../php/db.php';
$source=$pdo;$sourceName=$dbname;$tag=bin2hex(random_bytes(6));$created=[];
$backupPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'frostline_verified_'.$tag.'.backup.gz';
$connect=function(string $name)use($host,$username,$password):PDO{$p=new PDO('mysql:host='.$host.';dbname='.$name.';charset=utf8mb4',$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("SET time_zone='+08:00'");return $p;};
$create=function(string $kind)use($source,$tag,&$created,$connect):PDO{$name='frostline_sql_'.$kind.'_'.$tag;frostlineDisposable($name);$source->exec('CREATE DATABASE '.frostlineIdentifier($name).' CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci');$created[]=$name;return $connect($name);};
try{
 $snapshot=frostlineSnapshot($source);$raw=serialize(['database'=>$sourceName,'snapshot'=>$snapshot]);
 if(file_put_contents($backupPath,gzencode($raw,9),LOCK_EX)===false)throw new RuntimeException('Backup write failed');
 $loaded=unserialize(gzdecode(file_get_contents($backupPath)),['allowed_classes'=>false]);if($loaded['snapshot']!==$snapshot)throw new RuntimeException('Backup readback failed');
 echo 'Backup readback verified; SHA256 '.hash_file('sha256',$backupPath).PHP_EOL;
 $copy=$create('restore');frostlineRestore($copy,$loaded['snapshot']);frostlineVerifySnapshot($copy,$snapshot);echo 'PASS: full backup restored; every schema and row matches.'.PHP_EOL;
 $fresh=$create('fresh');frostlineRunSql($fresh,file_get_contents(__DIR__.'/../sql/final_cakedeco_database.sql'));echo 'PASS: fresh schema import into empty disposable database.'.PHP_EOL;
 if((int)$fresh->query("SELECT COUNT(*) FROM role_permissions WHERE permission='SYSTEM_SETTINGS'")->fetchColumn()!==0)throw new RuntimeException('Retired permission seeded');
 if((int)$fresh->query('SELECT COUNT(*) FROM users')->fetchColumn()!==0)throw new RuntimeException('Sample accounts seeded');
 $guarded=false;try{frostlineRunSql($fresh,file_get_contents(__DIR__.'/../sql/final_cakedeco_database.sql'));}catch(RuntimeException $e){$guarded=str_contains($e->getMessage(),'not empty');}finally{$fresh->exec('DROP PROCEDURE IF EXISTS frostline_fresh_guard_20261026');}if(!$guarded)throw new RuntimeException('Fresh import did not refuse populated schema');
 $guarded=false;try{frostlineRunSql($fresh,file_get_contents(__DIR__.'/../sql/upgrade_existing_database.sql'));}catch(RuntimeException $e){$guarded=str_contains($e->getMessage(),'verify restored backup');}finally{$fresh->exec('DROP PROCEDURE IF EXISTS frostline_upgrade_20261026');}if(!$guarded)throw new RuntimeException('Upgrade did not require backup approval');
 echo 'PASS: fresh refuses nonempty database; upgrade refuses missing backup/approval; no sample accounts or retired permissions.'.PHP_EOL;

 $copy->exec('SET @frostline_backup_verified=DATABASE(), @frostline_upgrade_approved=DATABASE()');
 frostlineRunSql($copy,file_get_contents(__DIR__.'/../sql/upgrade_existing_database.sql'));echo 'PASS: upgrade on verified restored copy.'.PHP_EOL;
 frostlineRunSql($copy,file_get_contents(__DIR__.'/../sql/upgrade_existing_database.sql'));echo 'PASS: repeated upgrade.'.PHP_EOL;
 foreach($snapshot as $t=>$data){$after=frostlineRows($copy,$t);foreach($data['rows'] as $row){if(!in_array($row,$after,true))throw new RuntimeException('Upgrade changed original rows: '.$t);}if($t!=='role_permissions' && count($after)!==count($data['rows']))throw new RuntimeException('Unexpected extra rows: '.$t);}echo 'PASS: original rows and ID sequence unchanged after both upgrades.'.PHP_EOL;

 $legacy=$create('legacy');
 $legacySource=file_get_contents(__DIR__.'/../archive/sql/2026-10-26/ibarangaydocs_db.sql');
 preg_match('/CREATE TABLE \x60users\x60 \([\s\S]*?\) ENGINE=InnoDB[^;]*;/',$legacySource,$m);
 $legacy->exec($m[0]);$legacy->exec('ALTER TABLE users ADD PRIMARY KEY(id), MODIFY COLUMN id int NOT NULL AUTO_INCREMENT');
 $oldRole=file_get_contents(__DIR__.'/../archive/sql/2026-10-26/add_role_based_auth.sql');preg_match('/CREATE TABLE IF NOT EXISTS \x60security_logs\x60 \([\s\S]*?\) ENGINE=InnoDB[^;]*;/',$oldRole,$m);$legacy->exec($m[0]);
 $legacy->exec('ALTER TABLE security_logs ADD CONSTRAINT legacy_audit_cascade FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE');
 $legacy->exec('CREATE TABLE account_id_sequence(id tinyint unsigned NOT NULL PRIMARY KEY,last_number bigint unsigned NOT NULL) ENGINE=InnoDB');$legacy->exec('INSERT INTO account_id_sequence VALUES(1,9999)');
 $legacy->exec('CREATE TABLE role_permissions(role varchar(20) NOT NULL,permission varchar(50) NOT NULL,enabled tinyint NOT NULL,PRIMARY KEY(role,permission)) ENGINE=InnoDB');$legacy->exec("INSERT INTO role_permissions VALUES('ADMIN','MANAGE_USERS',0)");
 $legacyBackup=frostlineSnapshot($legacy);$legacyName=(string)$legacy->query('SELECT DATABASE()')->fetchColumn();
 $legacyPath=sys_get_temp_dir().DIRECTORY_SEPARATOR.'frostline_legacy_'.$tag.'.backup.gz';file_put_contents($legacyPath,gzencode(serialize($legacyBackup),9));
 $loadedLegacy=unserialize(gzdecode(file_get_contents($legacyPath)),['allowed_classes'=>false]);if($loadedLegacy!==$legacyBackup)throw new RuntimeException('Legacy backup readback failed');
 $legacyVerifyName='frostline_sql_restore_'.bin2hex(random_bytes(6));frostlineDisposable($legacyVerifyName);$source->exec('CREATE DATABASE '.frostlineIdentifier($legacyVerifyName));$created[]=$legacyVerifyName;$legacyVerify=$connect($legacyVerifyName);frostlineRestore($legacyVerify,$loadedLegacy);frostlineVerifySnapshot($legacyVerify,$legacyBackup);
 $legacy->exec('SET @frostline_backup_verified=DATABASE(), @frostline_upgrade_approved=DATABASE()');frostlineRunSql($legacy,file_get_contents(__DIR__.'/../sql/upgrade_existing_database.sql'));frostlineRunSql($legacy,file_get_contents(__DIR__.'/../sql/upgrade_existing_database.sql'));
 if((int)$legacy->query('SELECT last_number FROM account_id_sequence WHERE id=1')->fetchColumn()!==9999||(int)$legacy->query("SELECT enabled FROM role_permissions WHERE role='ADMIN' AND permission='MANAGE_USERS'")->fetchColumn()!==0)throw new RuntimeException('Counter or customized permission reset');
 echo 'PASS: backed-up legacy schema upgraded twice, including CASCADE to SET NULL; high counter and disabled grant preserved.'.PHP_EOL;


 $runCli=function(array $args):string{$pipes=[];$process=proc_open(array_merge([PHP_BINARY,__DIR__.'/upgrade_database.php'],$args),[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$status=proc_close($process);if($status!==0)throw new RuntimeException('Consolidated CLI test failed: '.$err);return $out;};
 $copyName=(string)$copy->query('SELECT DATABASE()')->fetchColumn();$backupOutput=$runCli(['--backup','--database',$copyName]);if(!preg_match('/Verified backup: (.+)/',$backupOutput,$match))throw new RuntimeException('CLI backup did not verify');
 $applyOutput=$runCli(['--apply','--database',$copyName,'--backup-file',trim($match[1]),'--approve-database',$copyName]);if(!str_contains($applyOutput,'Upgrade completed'))throw new RuntimeException('CLI apply did not complete');echo 'PASS: consolidated CLI backup and approved apply on disposable restored copy.'.PHP_EOL;
 require __DIR__.'/../tests/database_feature_checks.php';
 foreach([$fresh,$copy,$legacy] as $testConnection){ frostlineCompareSchema($fresh,$testConnection); frostlineDatabaseChecks($testConnection); }
 echo 'BACKUP_PATH='.$backupPath.PHP_EOL;
}finally{foreach(array_reverse($created) as $name){frostlineDisposable($name);$source->exec('DROP DATABASE '.frostlineIdentifier($name));}}
