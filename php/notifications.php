<?php
require_once __DIR__ . '/notifications_auth.php';
$actor=notificationRequireActor($pdo);
$available=notificationsReady($pdo);
$scopes=notificationScopes($pdo,$actor['id'],$actor['role']);
$filter=is_string($_GET['notification_filter']??null)?$_GET['notification_filter']:'all';
if(!in_array($filter,['all','unread','read'],true))$filter='all';
$notificationPage=filter_var($_GET['notification_page']??1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1;
$notificationError='';
$history=['items'=>[],'total'=>0,'pages'=>1,'page'=>1,'from'=>0,'to'=>0];
try {
    if($available){
        notificationSyncPending($pdo,$actor['id'],$scopes);
        if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
            $token=$_POST['csrf_token']??null;
            if(!is_string($token)||!hash_equals(notificationCsrf(),$token)){http_response_code(419);exit('Invalid request token');}
            if(($_POST['action']??'')!=='read_all'){http_response_code(400);exit('Invalid notification action');}
            notificationMarkRead($pdo,$actor['id'],$scopes,null);
            header('Location: app.php?page=notifications');exit;
        }
        $history=notificationList($pdo,$actor['id'],$scopes,$filter,$notificationPage,10);
    }else $notificationError='Notification history is unavailable. Please try again later.';
}catch(PDOException $e){error_log('Notification history unavailable.');$notificationError='Notifications could not be loaded. Please try again.';}
$historyQuery=http_build_query(['page'=>'notifications','notification_filter'=>$filter]);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Notifications - FROSTLINE</title></head>
<body class="superadmin-body"><main class="management-page notification-history" data-notification-history>
<header class="management-header"><div><p class="eyebrow">YOUR INBOX</p><h1>Notifications</h1><p class="management-subtitle">Messages about your account and authorized administrative activity.</p></div></header>
<section class="management-panel">
    <div class="notification-history-toolbar">
        <form class="filter-bar" method="get" action="app.php"><input type="hidden" name="page" value="notifications"><label>Show<select name="notification_filter"><?php foreach(['all'=>'All Notifications','unread'=>'Unread','read'=>'Read'] as $value=>$label): ?><option value="<?= $value ?>" <?= $filter===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><button type="submit" class="btn btn-secondary">Apply</button></form>
        <?php if($available): ?><form method="post" action="app.php?page=notifications" data-notification-action="read_all"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(notificationCsrf()) ?>"><button class="btn btn-secondary" type="submit" name="action" value="read_all">Mark All as Read</button></form><?php endif; ?>
    </div>
    <p class="notification-feedback" role="status" data-notification-feedback></p>
    <?php if($notificationError): ?><p role="alert"><?= htmlspecialchars($notificationError) ?></p>
    <?php elseif(!$history['items']): ?><div class="empty-state"><?= $filter==='all'?'No notifications yet.':'No notifications match this filter.' ?></div>
    <?php else: ?><div class="notification-history-list">
        <?php foreach($history['items'] as $item): ?>
        <button type="button" class="notification-history-item <?= $item['unread']?'is-unread':'' ?>" data-notification-open data-notification-id="<?= $item['id'] ?>">
            <span class="notification-item-heading"><strong><?= htmlspecialchars($item['title']) ?></strong><span class="notification-read-state"><?= $item['unread']?'Unread':'Read' ?></span></span>
            <span class="notification-description"><?= htmlspecialchars($item['description']) ?></span>
            <span class="notification-meta"><span class="notification-category"><?= htmlspecialchars(ucfirst($item['category'])) ?></span><time datetime="<?= htmlspecialchars(str_replace(' ','T',$item['created_at'])) ?>"><?= htmlspecialchars(date('M j, Y · h:i A',strtotime($item['created_at']))) ?></time></span>
        </button>
        <?php endforeach; ?>
    </div><?php endif; ?>
    <?php if(!$notificationError): ?><footer class="notification-pagination"><span>Showing <?= $history['from'] ?>–<?= $history['to'] ?> of <?= $history['total'] ?> notifications</span>
        <nav aria-label="Notification pages"><?php if($history['page']<=1): ?><span class="notification-page disabled" aria-disabled="true">Previous</span><?php else: ?><a class="notification-page" href="app.php?<?= htmlspecialchars($historyQuery) ?>&notification_page=<?= $history['page']-1 ?>">Previous</a><?php endif; ?>
        <?php $windowStart=max(1,$history['page']-2);$windowEnd=min($history['pages'],$history['page']+2);$numbers=array_unique(array_merge([1],range($windowStart,$windowEnd),[$history['pages']]));$last=0;foreach($numbers as $number): ?><?php if($last&&$number>$last+1): ?><span>…</span><?php endif; ?><a class="notification-page <?= $number===$history['page']?'active':'' ?>" <?= $number===$history['page']?'aria-current="page"':'' ?> href="app.php?<?= htmlspecialchars($historyQuery) ?>&notification_page=<?= $number ?>"><?= $number ?></a><?php $last=$number;endforeach; ?>
        <?php if($history['page']>=$history['pages']): ?><span class="notification-page disabled" aria-disabled="true">Next</span><?php else: ?><a class="notification-page" href="app.php?<?= htmlspecialchars($historyQuery) ?>&notification_page=<?= $history['page']+1 ?>">Next</a><?php endif; ?></nav>
    </footer><?php endif; ?>
</section></main></body></html>
