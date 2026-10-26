<?php
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/superadmin_session.php';
require_once __DIR__ . '/notifications_store.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function notificationApiError(int $status, string $message): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['success'=>false,'error'=>$message]);
    exit;
}

function notificationRequireActor(PDO $pdo): array {
    $id=(int)($_SESSION['user_id']??0);
    if ($id<=0) notificationApiError(401,'Authentication required.');
    $stmt=$pdo->prepare('SELECT role,status,first_login_required FROM users WHERE id=? LIMIT 1');$stmt->execute([$id]);$account=$stmt->fetch(PDO::FETCH_ASSOC);
    if (!$account || !in_array($account['role'],['ADMIN','SUPER_ADMIN'],true) || !in_array($account['status'],['ACTIVE','INACTIVE'],true) || (int)$account['first_login_required']===1) notificationApiError(403,'Notifications are unavailable for this account.');
    if (isset($_SESSION['superadmin_session_token']) && $account['role']!=='SUPER_ADMIN') notificationApiError(401,'This session is no longer authorized.');
    $state=['status'=>'not_applicable'];
    if($account['role']==='SUPER_ADMIN'){$state=superAdminSessionState($pdo);if($state['status']!=='active')notificationApiError(401,'Super Admin access is unavailable.');}
    $_SESSION['role']=$account['role'];
    return ['id'=>$id,'role'=>$account['role'],'state'=>$state];
}
