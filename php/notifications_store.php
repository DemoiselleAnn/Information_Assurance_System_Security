<?php
require_once __DIR__ . '/authorization.php';

function notificationsReady(PDO $pdo): bool {
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!array_key_exists($key, $ready)) {
        try { $pdo->query('SELECT 1 FROM notifications LIMIT 0'); $ready[$key] = true; }
        catch (PDOException $e) { $ready[$key] = false; error_log('Notification inbox unavailable; use the verified sql/upgrade_existing_database.sql workflow.'); }
    }
    return $ready[$key];
}

function notificationScopes(PDO $pdo, int $userId, string $role): array {
    if (!in_array($role, ['ADMIN', 'SUPER_ADMIN'], true)) return [];
    $scopes = ['']; // Private account messages, still bound to this recipient.
    foreach (['APPROVE_REGISTRATIONS', 'APPROVE_DELETIONS'] as $permission) {
        if (hasPermission($pdo, $userId, $permission)) $scopes[] = $permission;
    }
    if ($role === 'SUPER_ADMIN' && hasPermission($pdo, $userId, 'MANAGE_ADMINISTRATORS')) $scopes[] = 'MANAGE_ADMINISTRATORS';
    if (in_array('APPROVE_DELETIONS', $scopes, true) || hasPermission($pdo, $userId, 'SUBMIT_DELETION_REQUESTS')) $scopes[] = 'DELETION_REQUEST_ACCESS';
    if (hasPermission($pdo, $userId, 'VIEW_SECURITY_LOGS')) $scopes[] = 'VIEW_SECURITY_LOGS';
    return $scopes;
}

function notificationWhere(int $userId, array $scopes, string $filter = 'all'): array {
    if (!$scopes) return ['1 = 0', []];
    $where = 'recipient_user_id = ? AND required_permission IN (' . implode(',', array_fill(0, count($scopes), '?')) . ')';
    $params = array_merge([$userId], $scopes);
    if ($filter === 'unread') $where .= ' AND read_at IS NULL';
    elseif ($filter === 'read') $where .= ' AND read_at IS NOT NULL';
    return [$where, $params];
}

function notificationDestination(array $row, array $scopes): ?string {
    $page = $row['destination_page'] ?? '';
    $required = ['registration_approval'=>'APPROVE_REGISTRATIONS', 'deletion_requests'=>'DELETION_REQUEST_ACCESS', 'administrators'=>'MANAGE_ADMINISTRATORS', 'security_logs'=>'VIEW_SECURITY_LOGS', 'my_profile'=>''];
    if (!array_key_exists($page, $required) || !in_array($required[$page], $scopes, true)) return null;
    $params = ['page'=>$page];
    if (in_array($page, ['registration_approval','deletion_requests','administrators','security_logs'], true) && (int)($row['related_id'] ?? 0) > 0) $params['view'] = (int)$row['related_id'];
    return 'app.php?' . http_build_query($params);
}

function notificationInsert(PDO $pdo, int $recipient, string $key, string $category, string $title, string $description, string $permission, ?string $page, ?int $relatedId): void {
    $stmt = $pdo->prepare('INSERT INTO notifications (recipient_user_id,event_key,category,title,description,required_permission,destination_page,related_id) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE id=id');
    $stmt->execute([$recipient,$key,$category,$title,$description,$permission,$page,$relatedId]);
}

/** Fan out only to recipients already authorized by the existing permissions. */
function notificationPublish(PDO $pdo, string $key, string $category, string $title, string $description, string $permission, ?string $page, ?int $relatedId = null): void {
    try {
        if (!notificationsReady($pdo)) return;
        $recipients = $pdo->query("SELECT id,role FROM users WHERE role IN ('ADMIN','SUPER_ADMIN') AND first_login_required=0 AND status IN ('ACTIVE','INACTIVE')")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($recipients as $recipient) {
            $scopes = notificationScopes($pdo, (int)$recipient['id'], $recipient['role']);
            if (in_array($permission, $scopes, true)) notificationInsert($pdo,(int)$recipient['id'],$key,$category,$title,$description,$permission,$page,$relatedId);
        }
    } catch (PDOException $e) { error_log('Notification delivery unavailable.'); }
}

function notificationPersonal(PDO $pdo, int $userId, string $key, string $category, string $title, string $description, string $permission = '', ?string $page = 'my_profile', ?int $relatedId = null): void {
    try {
        if ($userId <= 0 || !notificationsReady($pdo)) return;
        $stmt = $pdo->prepare("SELECT role,status FROM users WHERE id=? AND role IN ('ADMIN','SUPER_ADMIN')");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || in_array($user['status'], ['BLOCKED','REJECTED'], true)) return;
        if (!in_array($permission, notificationScopes($pdo,$userId,$user['role']), true)) return;
        notificationInsert($pdo,$userId,$key,$category,$title,$description,$permission,$page,$relatedId);
    } catch (PDOException $e) { error_log('Private notification delivery unavailable.'); }
}

function notifyRegistrationSubmitted(PDO $pdo, int $id): void {
    notificationPublish($pdo,'registration_submitted:'.$id,'registration','Registration awaiting approval','A new user registration is awaiting an administrator decision.','APPROVE_REGISTRATIONS','registration_approval',$id);
}
function notifyRegistrationDecision(PDO $pdo, int $id, string $action): void {
    $decision = $action === 'approve' ? 'approved' : 'rejected';
    notificationPublish($pdo,'registration_'.$decision.':'.$id,'registration','Registration '.$decision,'A user registration was '.$decision.'.','APPROVE_REGISTRATIONS','registration_approval',$id);
}
function notifyDeletionSubmitted(PDO $pdo, int $id): void {
    notificationPublish($pdo,'deletion_submitted:'.$id,'request','Deletion request awaiting review','Deletion request #'.$id.' is awaiting review.','APPROVE_DELETIONS','deletion_requests',$id);
}
function notifyDeletionDecision(PDO $pdo, int $id, int $requesterId, string $status): void {
    $decision = $status === 'APPROVED' ? 'approved' : 'rejected';
    $key = 'deletion_'.$decision.':'.$id;
    $title = 'Deletion request '.$decision;
    $description = 'Deletion request #'.$id.' was '.$decision.'.';
    notificationPersonal($pdo,$requesterId,$key,'request',$title,$description,'DELETION_REQUEST_ACCESS','deletion_requests',$id);
    notificationPublish($pdo,$key,'request',$title,$description,'APPROVE_DELETIONS','deletion_requests',$id);
}
function notifyAdministratorEvent(PDO $pdo, int $id, int $logId, string $action): void {
    if (!in_array($action, ['ADMIN_CREATED','SUPER_ADMIN_CREATED','ADMIN_UPDATED'], true) || $logId <= 0) return;
    $created = $action !== 'ADMIN_UPDATED';
    $key = 'administrator_log:'.$logId;
    $title = $created ? 'Administrator account created' : 'Administrator account updated';
    notificationPersonal($pdo,$id,$key,'account',$title,'Your administrator account was '.($created?'created.':'updated.'));
    notificationPublish($pdo,$key,'account',$title,'An administrator account was '.($created?'created.':'updated.'),'MANAGE_ADMINISTRATORS','administrators',$created?null:$id);
}

/** Seed genuine currently pending events, including pre-migration requests, without resetting read state. */
function notificationSyncPending(PDO $pdo, int $userId, array $scopes): void {
    if (in_array('APPROVE_REGISTRATIONS',$scopes,true)) {
        $stmt=$pdo->prepare("INSERT INTO notifications (recipient_user_id,event_key,category,title,description,required_permission,destination_page,related_id,created_at)
            SELECT ?,CONCAT('registration_submitted:',id),'registration','Registration awaiting approval','A user registration is awaiting an administrator decision.','APPROVE_REGISTRATIONS','registration_approval',id,created_at
            FROM users WHERE role='USER' AND status='PENDING' ON DUPLICATE KEY UPDATE id=notifications.id");
        $stmt->execute([$userId]);
    }
    if (in_array('APPROVE_DELETIONS',$scopes,true)) {
        $stmt=$pdo->prepare("INSERT INTO notifications (recipient_user_id,event_key,category,title,description,required_permission,destination_page,related_id,created_at)
            SELECT ?,CONCAT('deletion_submitted:',id),'request','Deletion request awaiting review',CONCAT('Deletion request #',id,' is awaiting review.'),'APPROVE_DELETIONS','deletion_requests',id,created_at
            FROM deletion_requests WHERE status='PENDING' ON DUPLICATE KEY UPDATE id=notifications.id");
        $stmt->execute([$userId]);
    }
}

function notificationList(PDO $pdo, int $userId, array $scopes, string $filter = 'all', int $page = 1, int $perPage = 10): array {
    if (!in_array($filter,['all','unread','read'],true)) $filter='all';
    $perPage=max(1,min(10,$perPage));
    [$where,$params]=notificationWhere($userId,$scopes,$filter);
    $count=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE '.$where); $count->execute($params); $total=(int)$count->fetchColumn();
    $pages=max(1,(int)ceil($total/$perPage));$page=max(1,min($pages,$page));$offset=($page-1)*$perPage;
    $stmt=$pdo->prepare('SELECT id,category,title,description,created_at,read_at,destination_page,related_id FROM notifications WHERE '.$where.' ORDER BY created_at DESC,id DESC LIMIT '.$perPage.' OFFSET '.$offset);
    $stmt->execute($params);$items=$stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($items as &$item){$item['id']=(int)$item['id'];$item['unread']=$item['read_at']===null;$item['url']=notificationDestination($item,$scopes);unset($item['destination_page'],$item['related_id']);}unset($item);
    return ['items'=>$items,'total'=>$total,'page'=>$page,'pages'=>$pages,'from'=>$total?$offset+1:0,'to'=>min($total,$offset+$perPage)];
}
function notificationUnreadCount(PDO $pdo, int $userId, array $scopes): int {
    [$where,$params]=notificationWhere($userId,$scopes,'unread');$stmt=$pdo->prepare('SELECT COUNT(*) FROM notifications WHERE '.$where);$stmt->execute($params);return (int)$stmt->fetchColumn();
}
function notificationMarkRead(PDO $pdo, int $userId, array $scopes, ?int $id): bool {
    [$where,$params]=notificationWhere($userId,$scopes);
    if ($id !== null) {$where.=' AND id = ?';$params[]=$id;$check=$pdo->prepare('SELECT id FROM notifications WHERE '.$where);$check->execute($params);if(!$check->fetchColumn())return false;}
    $stmt=$pdo->prepare('UPDATE notifications SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE '.$where);$stmt->execute($params);return true;
}
function notificationCsrf(): string { return $_SESSION['notifications_csrf'] ??= bin2hex(random_bytes(32)); }

function notificationAuthorizedDestination(PDO $pdo, int $userId, array $scopes, int $id): ?string {
    [$where,$params]=notificationWhere($userId,$scopes);$params[]=$id;
    $stmt=$pdo->prepare('SELECT destination_page,related_id FROM notifications WHERE '.$where.' AND id=?');$stmt->execute($params);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? notificationDestination($row,$scopes) : null;
}
