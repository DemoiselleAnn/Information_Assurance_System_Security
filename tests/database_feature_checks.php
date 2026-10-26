<?php
require_once __DIR__.'/../php/account_status.php';
require_once __DIR__.'/../php/authorization.php';
require_once __DIR__.'/../php/first_login_activation.php';
require_once __DIR__.'/../php/notifications_store.php';
function frostlineDbAssert(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('Feature check failed: '.$label);}
function frostlineDatabaseChecks(PDO $db):void {
 frostlineDisposable((string)$db->query('SELECT DATABASE()')->fetchColumn());
 $required=['users','security_answers','login_attempts','password_recovery','security_logs','account_id_sequence','role_permissions','user_permissions','user_sessions','superadmin_sessions','superadmin_session_state','deletion_requests','notifications','schema_migrations'];
 foreach($required as $table)frostlineDbAssert(in_array($table,frostlineTables($db),true),'required table '.$table);
 $db->beginTransaction();
 try{
  $newAccount=function(string $role,string $status,int $setup=0)use($db):int{
   $idNumber=generateAccountId($db);$suffix=bin2hex(random_bytes(5));
   $stmt=$db->prepare("INSERT INTO users(id_number,first_name,last_name,birthdate,age,username,password,email,sex,purok,barangay,city_municipality,province,country,zip_code,role,status,first_login_required,temporary_credential_expires_at) VALUES (?,'SQL','Fixture','1991-12-31',34,?,?,?,'private','','','','','','',?,?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE))");
   $stmt->execute([$idNumber,'fixture'.$suffix,password_hash('Temporary123!',PASSWORD_DEFAULT),'fixture'.$suffix.'@example.invalid',$role,$status,$setup]);return(int)$db->lastInsertId();
  };
  $actor=$newAccount('SUPER_ADMIN','ACTIVE');$admin=$newAccount('ADMIN','INACTIVE',1);$user=$newAccount('USER','PENDING');
  $_SESSION=['user_id'=>$actor,'role'=>'SUPER_ADMIN'];
  $stmt=$db->prepare('SELECT birthdate,age,password,temporary_credential_expires_at FROM users WHERE id=?');$stmt->execute([$admin]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
  frostlineDbAssert($row['birthdate']==='1991-12-31'&&password_verify('Temporary123!',$row['password'])&&$row['temporary_credential_expires_at']!==null,'created admin fields and hashed temporary password');
  $db->prepare('INSERT INTO password_recovery(user_id,otp_hash,expires_at,last_sent_at,otp_verified) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),1)')->execute([$admin,password_hash('123456',PASSWORD_DEFAULT)]);
  foreach(['One','Two','Three'] as $question)$db->prepare('INSERT INTO security_answers(user_id,question,answer) VALUES (?,?,?)')->execute([$admin,$question,password_hash('normalized answer',PASSWORD_DEFAULT)]);
  lockFirstLoginAccountForSetup($db,$admin,'ADMIN');completeFirstLoginAccount($db,$admin,'ADMIN',password_hash('Permanent123!',PASSWORD_DEFAULT));
  $stmt=$db->prepare('SELECT first_login_required,password FROM users WHERE id=?');$stmt->execute([$admin]);$row=$stmt->fetch(PDO::FETCH_ASSOC);frostlineDbAssert((int)$row['first_login_required']===0&&password_verify('Permanent123!',$row['password']),'first login activation');
  $db->prepare("UPDATE users SET status='INACTIVE' WHERE id=? AND status='PENDING'")->execute([$user]);
  $db->commit();
  frostlineDbAssert(updateAccountStatus($db,$user,'BLOCKED')[0],'block helper');frostlineDbAssert(updateAccountStatus($db,$user,'INACTIVE')[0],'unblock helper');
  $stmt=$db->prepare('SELECT status FROM users WHERE id=?');$stmt->execute([$user]);frostlineDbAssert($stmt->fetchColumn()==='INACTIVE','unblock saves INACTIVE');
  $db->prepare("INSERT INTO user_permissions(user_id,permission,enabled) VALUES (?,'APPROVE_DELETIONS',1)")->execute([$admin]);frostlineDbAssert(!hasPermission($db,$admin,'APPROVE_DELETIONS'),'role boundary despite override');
  $db->prepare("INSERT INTO user_sessions(session_hash,user_id) VALUES (?,?)")->execute([hash('sha256','fixture'.$user),$user]);
  $db->prepare("INSERT INTO deletion_requests(user_id,employee_id,full_name,username,email,account_role,requested_by,reason) SELECT id,id_number,CONCAT(first_name,' ',last_name),username,email,role,?,'Disposable SQL verification' FROM users WHERE id=?")->execute([$admin,$user]);$requestId=(int)$db->lastInsertId();notifyDeletionSubmitted($db,$requestId);
  $db->prepare("INSERT INTO security_logs(user_id,username,full_name,role,action,status,actor_id_number,session_key) SELECT id,username,'Original SQL Fixture',role,'LOGIN','SUCCESS',id_number,'fixture-session' FROM users WHERE id=?")->execute([$user]);$logId=(int)$db->lastInsertId();
  $original=$db->query('SELECT * FROM security_logs WHERE id='.$logId)->fetch(PDO::FETCH_ASSOC);
  $db->prepare("INSERT INTO security_logs(user_id,username,full_name,role,action,status,actor_id_number,session_key) SELECT id,username,'Original SQL Fixture',role,'LOGOUT','SUCCESS',id_number,'fixture-session' FROM users WHERE id=?")->execute([$user]);
  $db->prepare("DELETE FROM users WHERE id=? AND role<>'SUPER_ADMIN'")->execute([$user]);
  $after=$db->query('SELECT * FROM security_logs WHERE id='.$logId)->fetch(PDO::FETCH_ASSOC);$expected=$original;$expected['user_id']=null;frostlineDbAssert($after===$expected,'historical login survives deletion unchanged except actor FK');
  // Approval logging after deletion deliberately retains the numeric target ID without a target FK.
  $db->prepare("INSERT INTO security_logs(user_id,username,full_name,role,action,status,target_user_id,target_username) VALUES (?,'reviewer','SQL Reviewer','SUPER_ADMIN','DELETION_REQUEST_APPROVED','SUCCESS',?,'deleted-fixture')")->execute([$actor,$user]);
  $db->prepare("UPDATE deletion_requests SET status='APPROVED',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$actor,$requestId]);notifyDeletionDecision($db,$requestId,$admin,'APPROVED');
  $stmt=$db->prepare('SELECT reason,full_name FROM deletion_requests WHERE id=?');$stmt->execute([$requestId]);$request=$stmt->fetch(PDO::FETCH_ASSOC);frostlineDbAssert($request['reason']==='Disposable SQL verification'&&$request['full_name']==='SQL Fixture','deletion snapshot retained');
  echo 'PASS: disposable ID generation, registration/account inserts, hashes, OTP/questions storage, first-login activation, block/unblock, permissions, notification calls and deletion audit preservation.'.PHP_EOL;
 }finally{if($db->inTransaction())$db->rollBack();}
}

function frostlineCompareSchema(PDO $expected, PDO $actual):void {
 foreach(frostlineTables($expected) as $table){
  $getColumns=function(PDO $p)use($table):array{$s=$p->prepare('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);$rows=[];foreach($s->fetchAll(PDO::FETCH_ASSOC) as $row)$rows[$row['COLUMN_NAME']]=$row;return $rows;};
  $required=$getColumns($expected);$existing=$getColumns($actual);
  foreach($required as $name=>$column){frostlineDbAssert(isset($existing[$name]),'column '.$table.'.'.$name);$got=$existing[$name];frostlineDbAssert($got['DATA_TYPE']===$column['DATA_TYPE'],'column type '.$table.'.'.$name);if($column['CHARACTER_MAXIMUM_LENGTH']!==null)frostlineDbAssert($got['CHARACTER_MAXIMUM_LENGTH']>=$column['CHARACTER_MAXIMUM_LENGTH'],'column capacity '.$table.'.'.$name);if($column['DATA_TYPE']==='enum'){preg_match_all("/'([^']+)'/",$column['COLUMN_TYPE'],$a);preg_match_all("/'([^']+)'/",$got['COLUMN_TYPE'],$b);frostlineDbAssert(!array_diff($a[1],$b[1]),'enum values '.$table.'.'.$name);}}
  $getIndexes=function(PDO $p)use($table):array{$s=$p->prepare('SELECT INDEX_NAME,NON_UNIQUE,GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY INDEX_NAME,NON_UNIQUE');$s->execute([$table]);return $s->fetchAll(PDO::FETCH_ASSOC);};$got=$getIndexes($actual);
  foreach($getIndexes($expected) as $idx){$match=false;foreach($got as $candidate)if($candidate['columns_list']===$idx['columns_list']&&($idx['NON_UNIQUE']||!$candidate['NON_UNIQUE']))$match=true;frostlineDbAssert($match,'index '.$table.'.'.$idx['INDEX_NAME']);}
  $getFks=function(PDO $p)use($table):array{$s=$p->prepare('SELECT kc.COLUMN_NAME,kc.REFERENCED_TABLE_NAME,kc.REFERENCED_COLUMN_NAME,rc.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE kc JOIN information_schema.REFERENTIAL_CONSTRAINTS rc ON rc.CONSTRAINT_SCHEMA=kc.CONSTRAINT_SCHEMA AND rc.TABLE_NAME=kc.TABLE_NAME AND rc.CONSTRAINT_NAME=kc.CONSTRAINT_NAME WHERE kc.TABLE_SCHEMA=DATABASE() AND kc.TABLE_NAME=?');$s->execute([$table]);return $s->fetchAll(PDO::FETCH_ASSOC);};$got=$getFks($actual);foreach($getFks($expected) as $fk)frostlineDbAssert(in_array($fk,$got,true),'foreign key '.$table.'.'.$fk['COLUMN_NAME']);
 }
 echo 'PASS: every canonical table, column/type/capacity, enum, index and FK is present.'.PHP_EOL;
}
