<?php
// CLI integration checks use connection-local temporary tables only.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')==='--api-suite'){
 foreach(['csrf'=>419,'foreign'=>404,'member'=>403,'anonymous'=>401,'read'=>200,'readall'=>200] as $case=>$expected){
  $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'--api-'.$case],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
  $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
  if($exit!==0||!str_contains($out,'HTTP_STATUS='.$expected)){fwrite(STDERR,'FAIL API '.$case.': '.$out.$err);exit(1);}
  echo 'PASS API '.$case.' HTTP '.$expected."\n";
 }exit;
}
require_once __DIR__.'/../php/db.php';
require_once __DIR__.'/../php/notifications_store.php';
require_once __DIR__.'/../php/sidebar_pending_counts.php';
function expectNotification(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
try{
 $ddl=$pdo->query('SHOW CREATE TABLE notifications')->fetchColumn(1);
 $ddl=preg_replace('/,\s*CONSTRAINT[^\n]+/','',$ddl);
 $pdo->exec(str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl));
 $pdo->exec("CREATE TEMPORARY TABLE users (id int PRIMARY KEY,role varchar(20),status varchar(20),first_login_required int DEFAULT 0,created_at datetime DEFAULT CURRENT_TIMESTAMP)");
 $pdo->exec('CREATE TEMPORARY TABLE role_permissions (role varchar(20),permission varchar(50),enabled int)');
 $pdo->exec('CREATE TEMPORARY TABLE user_permissions (user_id int,permission varchar(50),enabled int)');
 $pdo->exec('CREATE TEMPORARY TABLE deletion_requests (id int PRIMARY KEY,status varchar(20),requested_by int,created_at datetime DEFAULT CURRENT_TIMESTAMP)');
 $pdo->exec("INSERT INTO users(id,role,status,first_login_required) VALUES(1,'SUPER_ADMIN','ACTIVE',0),(2,'ADMIN','ACTIVE',0),(3,'ADMIN','ACTIVE',0),(4,'USER','PENDING',0),(5,'USER','PENDING',0),(6,'ADMIN','PENDING',1),(7,'ADMIN','INACTIVE',1),(8,'USER','ACTIVE',0)");
 $pdo->exec("INSERT INTO role_permissions VALUES('ADMIN','APPROVE_REGISTRATIONS',1),('ADMIN','SUBMIT_DELETION_REQUESTS',1),('ADMIN','VIEW_SECURITY_LOGS',1)");
 $pdo->exec("INSERT INTO user_permissions VALUES(3,'APPROVE_DELETIONS',1),(2,'MANAGE_ADMINISTRATORS',1)");
 $pdo->exec("INSERT INTO deletion_requests(id,status,requested_by) VALUES(10,'PENDING',2),(11,'REJECTED',2)");
 expectNotification(notificationsReady($pdo),'Migration unavailable');
 $scopes=notificationScopes($pdo,2,'ADMIN');$reviewer=notificationScopes($pdo,3,'ADMIN');$super=notificationScopes($pdo,1,'SUPER_ADMIN');
 expectNotification(!in_array('MANAGE_ADMINISTRATORS',$scopes,true),'Admin-only role restriction bypass');
 notificationSyncPending($pdo,2,$scopes);expectNotification(notificationUnreadCount($pdo,2,$scopes)===2,'Pending registrations seed');
 notificationSyncPending($pdo,2,$scopes);expectNotification(notificationUnreadCount($pdo,2,$scopes)===2,'Duplicate seeded notifications');
 notificationSyncPending($pdo,3,$reviewer);expectNotification(notificationUnreadCount($pdo,3,$reviewer)===3,'Reviewer pending scope');
 $first=notificationList($pdo,2,$scopes)['items'][0]['id'];$foreign=notificationList($pdo,3,$reviewer)['items'][0]['id'];
 if(($argv[1]??'')==='--browser-fixtures'){require __DIR__.'/notifications_browser_fixtures.php';exit;}
 if(isset($argv[1])&&str_starts_with($argv[1],'--api-')){
  require_once __DIR__.'/../php/session_bootstrap.php';$_SESSION=['user_id'=>2,'role'=>'ADMIN','notifications_csrf'=>str_repeat('a',64)];$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'read','notification_id'=>$first,'csrf_token'=>str_repeat('a',64)];
  if($argv[1]==='--api-readall')$_POST['action']='read_all';
  if($argv[1]==='--api-csrf')$_POST['csrf_token']='invalid';
  if($argv[1]==='--api-foreign')$_POST['notification_id']=$foreign;
  if($argv[1]==='--api-member')$_SESSION['user_id']=8;
  if($argv[1]==='--api-anonymous')$_SESSION=[];
  register_shutdown_function(function(){echo "\nHTTP_STATUS=".(http_response_code()?:200)."\n";$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();});require __DIR__.'/../php/notification_status.php';exit;
 }
 expectNotification(!notificationMarkRead($pdo,2,$scopes,$foreign),'Cross-recipient read accepted');
 $pendingBefore=sidebarPendingCounts($pdo,2,'ADMIN');
 expectNotification(notificationMarkRead($pdo,2,$scopes,$first),'Read owned notification');
 expectNotification(notificationUnreadCount($pdo,2,$scopes)===1,'Only one read');
 notificationSyncPending($pdo,2,$scopes);expectNotification(notificationUnreadCount($pdo,2,$scopes)===1,'Sync reset read state');
 expectNotification(sidebarPendingCounts($pdo,2,'ADMIN')===$pendingBefore,'Reading resolved pending tasks');
 notifyRegistrationSubmitted($pdo,4);notifyRegistrationSubmitted($pdo,4);expectNotification(notificationUnreadCount($pdo,2,$scopes)===1,'Event hook duplicated seeded event');
 notifyRegistrationDecision($pdo,4,'approve');notifyRegistrationDecision($pdo,4,'approve');expectNotification(notificationUnreadCount($pdo,2,$scopes)===2,'Decision deduplication');
 expectNotification((int)$pdo->query('SELECT COUNT(*) FROM notifications WHERE recipient_user_id=4')->fetchColumn()===0,'Private member notification leakage');
 notifyDeletionSubmitted($pdo,10);expectNotification((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE recipient_user_id=2 AND event_key='deletion_submitted:10'")->fetchColumn()===0,'Submit-only inbox got reviewer notification');
 notifyDeletionDecision($pdo,10,2,'REJECTED');$own=$pdo->query("SELECT * FROM notifications WHERE recipient_user_id=2 AND event_key='deletion_rejected:10'")->fetch(PDO::FETCH_ASSOC);expectNotification($own&&$own['required_permission']==='DELETION_REQUEST_ACCESS','Requester decision delivery');expectNotification(notificationDestination($own,$scopes)==='app.php?page=deletion_requests&view=10','Authorized request link');
 notifyAdministratorEvent($pdo,7,999,'ADMIN_CREATED');notifyAdministratorEvent($pdo,7,999,'ADMIN_CREATED');expectNotification((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE recipient_user_id=7 AND event_key='administrator_log:999'")->fetchColumn()===1,'Personal setup account message or duplicate');
 expectNotification(notificationDestination(['destination_page'=>'administrators','related_id'=>7],$scopes)===null,'Restricted admin destination');expectNotification(notificationDestination(['destination_page'=>'https://evil.test','related_id'=>7],$super)===null,'Unsafe destination accepted');
 $pdo->exec("INSERT INTO user_permissions VALUES(2,'APPROVE_REGISTRATIONS',0)");$revoked=notificationScopes($pdo,2,'ADMIN');$hidden=(int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE recipient_user_id=2 AND required_permission='APPROVE_REGISTRATIONS' AND read_at IS NULL")->fetchColumn();notificationMarkRead($pdo,2,$revoked,null);expectNotification((int)$pdo->query("SELECT COUNT(*) FROM notifications WHERE recipient_user_id=2 AND required_permission='APPROVE_REGISTRATIONS' AND read_at IS NULL")->fetchColumn()===$hidden,'Mark all crossed revoked scope');
 expectNotification(notificationList($pdo,2,$revoked)['total']===1,'Revoked scope still visible');
 for($i=0;$i<23;$i++)notificationInsert($pdo,2,'pagination:'.$i,'account','Fixture title','Long fixture text','','my_profile',null);
 $seen=[];$total=notificationList($pdo,2,$revoked)['total'];$lastPage=(int)ceil($total/10);for($p=1;$p<=$lastPage;$p++){$list=notificationList($pdo,2,$revoked,'all',$p);expectNotification(count($list['items'])<=10,'Page size');foreach($list['items'] as $item){expectNotification(!in_array($item['id'],$seen,true),'Duplicate page record');$seen[]=$item['id'];}}expectNotification(count($seen)===$total,'Skipped records');
 $clamped=notificationList($pdo,2,$revoked,'all',999);expectNotification($clamped['page']===$lastPage,'Page clamp');
 expectNotification(notificationList($pdo,2,[])['total']===0,'Empty scope exposed records');
 $otherUnread=notificationUnreadCount($pdo,3,$reviewer);notificationMarkRead($pdo,2,$revoked,null);expectNotification(notificationUnreadCount($pdo,2,$revoked)===0,'Mark all owned unread');expectNotification(notificationUnreadCount($pdo,3,$reviewer)===$otherUnread,'Mark all touched another recipient');expectNotification(notificationList($pdo,2,$revoked,'unread')['total']===0,'Unread filter');expectNotification(notificationList($pdo,2,$revoked,'read')['total']===$total,'Read history filter');expectNotification(sidebarPendingCounts($pdo,2,'ADMIN')===[],'Revoked sidebar permission changed');
 echo "PASS: MySQL temporary-table integration: recipient ownership, permission revocation, real event hooks, duplicate prevention, read persistence, separate sidebar counts, safe destinations, 10-row pagination/tie ordering, filters, and scoped Mark All. No production notification/account/request/log rows were changed.\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.$e->getMessage()."\n");exit(1);}
