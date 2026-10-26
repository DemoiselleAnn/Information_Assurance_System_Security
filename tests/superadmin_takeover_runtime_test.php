<?php
// Real MySQL execution, isolated by one outer rollback. Never alters real accounts.
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(static function ($level, $message, $file, $line): never { throw new ErrorException($message, 0, $level, $file, $line); });
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__.'/../php/session_bootstrap.php';
require_once __DIR__.'/../php/db.php';
require_once __DIR__.'/../php/superadmin_session.php';
$pdo->beginTransaction();
function takeoverCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function actor(int $id, string $token): void { $_SESSION = ['user_id'=>$id,'role'=>'SUPER_ADMIN','superadmin_session_token'=>$token]; }
function expireDeadline(PDO $pdo): void { $pdo->exec('UPDATE superadmin_sessions s JOIN superadmin_session_state a ON a.token_hash=s.token_hash SET s.superseded_expires_at=UNIX_TIMESTAMP()-1 WHERE a.id=1'); }
try {
    $ids = $pdo->query("SELECT id FROM users WHERE role='SUPER_ADMIN' ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
    takeoverCheck(count($ids)===2, 'Two fixture accounts required.');
    [$a,$b] = array_map('intval',$ids);
    $pdo->prepare("UPDATE users SET status='ACTIVE',first_login_required=0,takeover_cooldown_until=NULL WHERE id IN (?,?)")->execute([$a,$b]);
    $pdo->exec('UPDATE superadmin_session_state SET token_hash=NULL,user_id=NULL,pending_token_hash=NULL,pending_user_id=NULL WHERE id=1');
    $startLogs = (int)$pdo->query('SELECT COUNT(*) FROM security_logs')->fetchColumn();
    $aToken=activateSuperAdminSession($pdo,$a); actor($a,$aToken);
    takeoverCheck(superAdminSessionState($pdo)['status']==='active','Empty slot login failed.');
    $bToken=activateSuperAdminSession($pdo,$b);
    $aState=superAdminSessionState($pdo);
    takeoverCheck($pdo->query("SELECT status FROM users WHERE id=$b")->fetchColumn()==='INACTIVE','Waiting requester displayed ACTIVE.');
    takeoverCheck($aState['status']==='active' && $aState['remaining_seconds']>0,'Owner lost access before deadline.');
    $deadline=$pdo->query('SELECT s.superseded_expires_at FROM superadmin_session_state a JOIN superadmin_sessions s ON s.token_hash=a.token_hash WHERE a.id=1')->fetchColumn();
    actor($b,$bToken); takeoverCheck(superAdminSessionState($pdo)['status']==='waiting','Requester got premature ownership.');
    for ($i=0;$i<3;$i++) superAdminSessionState($pdo);
    takeoverCheck($deadline==$pdo->query('SELECT s.superseded_expires_at FROM superadmin_session_state a JOIN superadmin_sessions s ON s.token_hash=a.token_hash WHERE a.id=1')->fetchColumn(),'Polling reset deadline.');
    try { activateSuperAdminSession($pdo,$b); throw new LogicException('Duplicate request allowed.'); } catch (RuntimeException $e) { takeoverCheck(str_contains($e->getMessage(),'already pending'),'Wrong duplicate response.'); }
    expireDeadline($pdo); actor($a,$aToken);
    takeoverCheck(superAdminSessionState($pdo)['status']==='expired','Owner not revoked after server deadline.');
    actor($b,$bToken); takeoverCheck(superAdminSessionState($pdo)['status']==='active','Requester not activated.');
    takeoverCheck((int)$pdo->query('SELECT COUNT(*) FROM security_logs')->fetchColumn()===$startLogs+5,'Expected exactly five transition audit entries.');
    for ($i=0;$i<3;$i++) superAdminSessionState($pdo);
    takeoverCheck((int)$pdo->query('SELECT COUNT(*) FROM security_logs')->fetchColumn()===$startLogs+5,'Polling duplicated audit entries.');
    takeoverCheck($pdo->query("SELECT status FROM users WHERE id=$a")->fetchColumn()==='INACTIVE' && $pdo->query("SELECT status FROM users WHERE id=$b")->fetchColumn()==='ACTIVE','Takeover failed to synchronize account activity.');
    actor($a,$aToken);
    startUserPresence($pdo,$a,'revoked-tab');
    touchUserPresence($pdo,$a,'revoked-tab');
    takeoverCheck($pdo->query("SELECT status FROM users WHERE id=$a")->fetchColumn()==='INACTIVE','Old tab restored activity.');
    actor($b,$bToken);
    $attempts=$pdo->query("SELECT * FROM login_attempts WHERE user_id IN ($a,$b)")->fetchAll(PDO::FETCH_ASSOC);
    try { activateSuperAdminSession($pdo,$a); throw new LogicException('Retaliation allowed.'); } catch (SuperAdminTakeoverUnavailable $e) { takeoverCheck($e->remainingSeconds>590 && $e->remainingSeconds<=600,'Wrong cooldown.'); }
    takeoverCheck($attempts===$pdo->query("SELECT * FROM login_attempts WHERE user_id IN ($a,$b)")->fetchAll(PDO::FETCH_ASSOC),'Cooldown changed login counters.');
    takeoverCheck(superAdminSessionState($pdo)['status']==='active','Cooldown disturbed owner.');
    releaseSuperAdminSession($pdo);
    $aToken=activateSuperAdminSession($pdo,$a); actor($a,$aToken);
    takeoverCheck(superAdminSessionState($pdo)['status']==='active','Empty slot did not bypass cooldown.');
    $pdo->prepare('UPDATE users SET takeover_cooldown_until=UNIX_TIMESTAMP()-1 WHERE id=?')->execute([$b]);
    $bToken=activateSuperAdminSession($pdo,$b); actor($b,$bToken);
    takeoverCheck(superAdminSessionState($pdo)['status']==='waiting','Expired cooldown did not allow countdown.');
    releaseSuperAdminSession($pdo); actor($a,$aToken);
    takeoverCheck(superAdminSessionState($pdo)['status']==='active' && superAdminSessionState($pdo)['remaining_seconds']===0,'Cancellation left countdown running.');
    $bToken=activateSuperAdminSession($pdo,$b);
    $pdo->prepare("UPDATE users SET role='ADMIN' WHERE id=?")->execute([$b]);
    takeoverCheck(superAdminSessionState($pdo)['status']==='active' && superAdminSessionState($pdo)['remaining_seconds']===0,'Demoted requester took over.');
    $pdo->prepare("UPDATE users SET role='SUPER_ADMIN',takeover_cooldown_until=NULL WHERE id=?")->execute([$b]);
    $bToken=activateSuperAdminSession($pdo,$b);
    expireDeadline($pdo);
    releaseSuperAdminSession($pdo);
    $q=$pdo->prepare('SELECT takeover_cooldown_until > UNIX_TIMESTAMP() FROM users WHERE id=?');$q->execute([$a]);
    takeoverCheck((bool)$q->fetchColumn(),'Logout at an expired deadline bypassed cooldown.');
    actor($b,$bToken);releaseSuperAdminSession($pdo);
    takeoverCheck($pdo->query("SELECT status FROM users WHERE id=$b")->fetchColumn()==='INACTIVE','Logout retained ACTIVE status.');
    $pdo->prepare("UPDATE users SET status='BLOCKED' WHERE id=?")->execute([$a]);
    settleSuperAdminTakeover($pdo);
    takeoverCheck($pdo->query("SELECT status FROM users WHERE id=$a")->fetchColumn()==='BLOCKED','Cleanup overwrote BLOCKED.');
    echo "PASS: ownership, server deadline, refresh, duplicate requests, cooldown, empty-slot exception, cancellation, roles, account statuses and exactly-once audit. All data rolled back.\n";
} finally { if ($pdo->inTransaction()) $pdo->rollBack(); session_destroy(); }
