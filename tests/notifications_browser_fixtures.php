<?php
// Included only by the temporary-table integration harness; never reads live inbox rows.
if(PHP_SAPI!=='cli'||!isset($pdo)){http_response_code(404);exit;}
require_once __DIR__.'/../php/session_bootstrap.php';
register_shutdown_function(function(){$_SESSION=[];if(session_status()===PHP_SESSION_ACTIVE)session_destroy();});
$destination=$argv[2]??'';if($destination==='')throw new RuntimeException('Fixture output path required');
$shell=file_get_contents(__DIR__.'/../php/superadmin_shell.php');
$aside=substr($shell,strpos($shell,'<aside class="sidebar">'),strpos($shell,'</aside>')+8-strpos($shell,'<aside class="sidebar">'));
$headStart=strpos($shell,'<header class="top-header">');$headerStop=strpos($shell,'<div id="superadmin-session-warning"',$headStart);$headerEnd=strrpos(substr($shell,0,$headerStop),'</header>')+9;$header=substr($shell,$headStart,$headerEnd-$headStart);
$historySource=file_get_contents(__DIR__.'/../php/notifications.php');
$historySource=str_replace("require_once __DIR__ . '/notifications_auth.php';",'',$historySource);
$historySource=str_replace('$actor=notificationRequireActor($pdo);','$actor=$exportActor;',$historySource);
$output=[];
foreach(['admin'=>[2,'ADMIN'],'super'=>[1,'SUPER_ADMIN']] as $name=>[$uid,$role]){
 $_SESSION=['user_id'=>$uid,'role'=>$role,'full_name'=>'Fixture Administrator','username'=>'fixture.admin','notifications_csrf'=>str_repeat('a',64)];$_SERVER['REQUEST_METHOD']='GET';
 $scopes=notificationScopes($pdo,$uid,$role);notificationSyncPending($pdo,$uid,$scopes);
 for($i=1;$i<=23;$i++)notificationInsert($pdo,$uid,'browser:'.$i,'account','Account message '.$i.' <safe>','Complete notification description '.$i.' <script>unsafe()</script>','','my_profile',null);
 $makeState=function()use($pdo,$uid,$scopes){return ['success'=>true,'count'=>notificationUnreadCount($pdo,$uid,$scopes),'items'=>notificationList($pdo,$uid,$scopes,'all',1,5)['items'],'csrf_token'=>notificationCsrf(),'destination'=>null];};
 $exportActor=['id'=>$uid,'role'=>$role];$currentRole=$role;$currentPage='notifications';$dashboardRole=$role==='ADMIN'?'Administrator':'Super Administrator';$sidebarPendingCounts=sidebarPendingCounts($pdo,$uid,$role);$canView=fn($permission)=>hasPermission($pdo,$uid,$permission);
 ob_start();eval('?>'.$aside);$sidebarHtml=ob_get_clean();ob_start();eval('?>'.$header);$headerHtml=ob_get_clean();
 $renderHistory=function($filter,$page)use($pdo,$historySource,$exportActor){$_GET=['notification_filter'=>$filter,'notification_page'=>$page];ob_start();eval('?>'.$historySource);$html=ob_get_clean();preg_match('/<body[^>]*>([\s\S]*)<\/body>/',$html,$match);return $match[1]??$html;};
 $data=['initial'=>$makeState(),'sidebar'=>$sidebarHtml,'header'=>$headerHtml,'history'=>[]];
 foreach([1,2,3] as $p)$data['history'][$p]=$renderHistory('all',$p);
 $first=$data['initial']['items'][0]['id'];$target=notificationAuthorizedDestination($pdo,$uid,$scopes,$first);notificationMarkRead($pdo,$uid,$scopes,$first);$data['afterRead']=$makeState();$data['afterRead']['destination']=$target;$data['readHistory']=$renderHistory('read',1);$data['unreadHistory']=$renderHistory('unread',1);
 notificationMarkRead($pdo,$uid,$scopes,null);$data['afterAll']=$makeState();$data['allReadHistory']=$renderHistory('all',1);$data['emptyUnreadHistory']=$renderHistory('unread',1);
 $output[$name]=$data;
}
file_put_contents($destination,json_encode($output));echo "Browser fixtures exported from connection-local temporary tables.\n";
