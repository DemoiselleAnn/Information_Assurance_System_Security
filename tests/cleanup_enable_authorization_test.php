<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../php/cleanup_enable_authorization.php';require_once __DIR__.'/../php/provisional_account_cleanup.php';
require __DIR__.'/../php/db.php';$source=$pdo;$db='frostline_step4b_test_'.bin2hex(random_bytes(8));$created=false;$fixtures=[];
function check(bool $ok,string $name){if(!$ok)throw new RuntimeException('FAIL: '.$name);echo 'PASS: '.$name.PHP_EOL;}
function fixture(array $payload):array{$shell=getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/powershell.exe';$script=file_get_contents(__DIR__.'/cleanup_authorization_fixture.ps1');$env=getenv();$env['PSModulePath']=getenv('SystemRoot').'/System32/WindowsPowerShell/v1.0/Modules';$pipes=[];$proc=proc_open([$shell,'-NoProfile','-NonInteractive','-Command',$script],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$env);fwrite($pipes[0],json_encode($payload));fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException('Fixture failed: '.$err);return json_decode($out,true,512,JSON_THROW_ON_ERROR);}
try{
 $source->exec('CREATE DATABASE '.$db);$created=true;$p=new PDO("mysql:host=$host;dbname=$db",$username,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec("SET time_zone='+08:00'");$tables=$source->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$tables=array_merge(['users'],array_diff($tables,['users']));foreach($tables as $t){if(!preg_match('/^[a-z_]+$/D',$t))throw new RuntimeException('Unexpected table');$p->exec($source->query('SHOW CREATE TABLE '.$t)->fetch(PDO::FETCH_NUM)[1]);}
 $p->prepare("INSERT INTO users(id_number,first_name,last_name,birthdate,age,username,password,email,sex,purok,barangay,city_municipality,province,country,zip_code,role,status,first_login_required,temporary_credential_expires_at) VALUES ('TEST-AUTH','Fixture','Auth','2000-01-01',26,'EnableFixture',?,'fixture@example.test','private','','','','','','','ADMIN','INACTIVE',1,DATE_SUB(NOW(),INTERVAL 1 SECOND))")->execute([password_hash('DummyTemporary123!',PASSWORD_DEFAULT)]);$id=(int)$p->lastInsertId();
 $p->prepare("INSERT INTO security_logs(user_id,username,full_name,role,action,status,actor_id_number) VALUES (?,'HistoricalFixture','Historical Fixture','ADMIN','LOGIN','SUCCESS','TEST-AUTH')")->execute([$id]);
 foreach(['missing','malformed','disabled','database_mismatch','hardlink','file_write','parent_write','inherited_write','redirect','owner_unverified','inspection_failed'] as $case){
  $base=str_replace('\\','/',sys_get_temp_dir()).'/frostline-authorization-'.bin2hex(random_bytes(8));$fixtures[]=$base;$f=fixture(['action'=>'create','base'=>$base,'case'=>$case,'database'=>$db]);$oldRoot=getenv('SystemRoot');$rejected=false;
  try{readAuthorizedCleanupEnableFile($p,$f['path'],true,in_array($case,['owner_unverified','inspection_failed'],true)?$case:null);}catch(Throwable $e){$rejected=true;}finally{putenv('SystemRoot='.$oldRoot);}
  check($rejected&&(int)$p->query('SELECT COUNT(*) FROM users WHERE id='.$id)->fetchColumn()===1,$case.' authorization rejected; disposable account unchanged');
 }
 $base=str_replace('\\','/',sys_get_temp_dir()).'/frostline-authorization-'.bin2hex(random_bytes(8));$fixtures[]=$base;$f=fixture(['action'=>'create','base'=>$base,'case'=>'valid','database'=>$db]);
 $rejected=false;try{readAuthorizedCleanupEnableFile($source,$f['path'],true);}catch(Throwable $e){$rejected=true;}check($rejected,'isolated mode cannot authorize live database');
 $rejected=false;try{readAuthorizedCleanupEnableFile($source,$f['path'],false);}catch(Throwable $e){$rejected=true;}check($rejected,'arbitrary file cannot authorize production execution');
 $rejected=false;try{readAuthorizedCleanupEnableFile($source,'C:/Users/PC/Documents/FROSTLINECleanup/../FROSTLINECleanup/enabled.json');}catch(Throwable $e){$rejected=true;}check($rejected,'production traversal path rejected before inspection');
 $config=readAuthorizedCleanupEnableFile($p,$f['path'],true);check($config['enabled']===true&&$config['database']===$db,'read-only runtime grants and trusted isolated owner pass real Windows NTFS inspection');
 $pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/../cli/cleanup_expired_administrators.php','--execute','--database='.$db,'--isolated-test','--enable-file='.$f['path'],'--log-file='.$f['log']],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);$result=json_decode($out,true);check($exit===0&&$err===''&&$result['deleted_ids']===[$id],'protected CLI execution succeeds only with explicit isolated authorization');
 check($p->query('SELECT user_id FROM security_logs')->fetchColumn()===null,'isolated authorized deletion preserves historical audit');
 echo 'PASS: no live enable file, task settings or live records changed.'.PHP_EOL;
}finally{
 $teardownError=null;foreach($fixtures as $base){try{fixture(['action'=>'remove','base'=>$base]);}catch(Throwable $e){$teardownError=$e;}}

 if($created){if(!preg_match('/^frostline_step4b_test_[a-f0-9]{16}$/D',$db)||$db===$dbname)throw new RuntimeException('Unsafe teardown');$p=null;$source->exec('DROP DATABASE '.$db);}
 if($teardownError)throw $teardownError;
}
