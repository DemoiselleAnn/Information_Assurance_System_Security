<?php
require_once __DIR__ . '/notifications_auth.php';
header('Content-Type: application/json; charset=UTF-8');
try {
    $actor=notificationRequireActor($pdo);
    if (!notificationsReady($pdo)) notificationApiError(503,'Notification history is unavailable.');
    $scopes=notificationScopes($pdo,$actor['id'],$actor['role']);
    notificationSyncPending($pdo,$actor['id'],$scopes);
    if(!empty($actor['state']['remaining_seconds'])) {
        // Reuse the real existing takeover warning; store no session token or credentials.
        $key='session_warning:'.hash('sha256',(string)($_SESSION['superadmin_session_token']??''));
        notificationPersonal($pdo,$actor['id'],$key,'security','Super Administrator session warning','Another Super Administrator has signed in. This session will end after the security grace period.','','security_logs');
    }
    $destination=null;
    if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
        $csrf=$_POST['csrf_token']??null;
        if(!is_string($csrf)||!hash_equals(notificationCsrf(),$csrf))notificationApiError(419,'Request verification failed. Refresh the page and try again.');
        $action=$_POST['action']??'';
        if(!in_array($action,['read','read_all'],true))notificationApiError(400,'Invalid notification action.');
        $id=null;
        if($action==='read'){$id=filter_var($_POST['notification_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$id)notificationApiError(400,'Invalid notification.');}
        if($id!==null)$destination=notificationAuthorizedDestination($pdo,$actor['id'],$scopes,$id);
        if(!notificationMarkRead($pdo,$actor['id'],$scopes,$id))notificationApiError(404,'Notification unavailable.');
    } elseif (($_SERVER['REQUEST_METHOD']??'GET')!=='GET') notificationApiError(405,'Method not allowed.');
    $result=notificationList($pdo,$actor['id'],$scopes,'all',1,5);
    echo json_encode(['success'=>true,'count'=>notificationUnreadCount($pdo,$actor['id'],$scopes),'items'=>$result['items'],'csrf_token'=>notificationCsrf(),'destination'=>$destination],JSON_UNESCAPED_SLASHES);
} catch (PDOException $e) { error_log('Notification request unavailable.'); notificationApiError(503,'Notifications could not be loaded. Please try again.'); }
