<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
set_error_handler(static function ($level,$message,$file,$line): never { throw new ErrorException($message,0,$level,$file,$line); });
ini_set('session.save_path',sys_get_temp_dir());
require_once __DIR__.'/../php/session_bootstrap.php';
require_once __DIR__.'/../php/db.php';
require_once __DIR__.'/../php/superadmin_session.php';
class TakeoverRequestPDO extends PDO { public function commit(): bool { return true; } }
$pdo=new TakeoverRequestPDO('mysql:host=localhost;dbname=cakey_db','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET time_zone='+08:00'");
$pdo->beginTransaction();
$mode=$argv[1]??'cooldown';
[$a,$b]=array_map('intval',$pdo->query("SELECT id FROM users WHERE role='SUPER_ADMIN' ORDER BY id LIMIT 2")->fetchAll(PDO::FETCH_COLUMN));
$pdo->prepare("UPDATE users SET status='ACTIVE',first_login_required=0,takeover_cooldown_until=NULL WHERE id IN (?,?)")->execute([$a,$b]);
$pdo->exec('UPDATE superadmin_session_state SET token_hash=NULL,user_id=NULL,pending_token_hash=NULL,pending_user_id=NULL WHERE id=1');
$aToken=activateSuperAdminSession($pdo,$a);
$bToken=activateSuperAdminSession($pdo,$b);
$_SESSION=['user_id'=>$a,'role'=>'SUPER_ADMIN','superadmin_session_token'=>$aToken];
$_SERVER['REQUEST_METHOD']='GET';
$_GET=['page'=>'account_management'];
if (in_array($mode,['cooldown','expired','expired-poll','expired-profile'],true)) {
    $pdo->exec('UPDATE superadmin_sessions s JOIN superadmin_session_state a ON a.token_hash=s.token_hash SET s.superseded_expires_at=UNIX_TIMESTAMP()-1 WHERE a.id=1');
    superAdminSessionState($pdo);
}
$attempts=null;
if ($mode==='cooldown') {
    $pdo->prepare('UPDATE users SET username=?,password=? WHERE id=?')->execute(['TakeoverLoginFixture',password_hash('Fixture123!',PASSWORD_DEFAULT),$a]);
    $pdo->prepare('UPDATE login_attempts SET attempt_count=2,denied_until=NULL WHERE user_id=?')->execute([$a]);
    $q=$pdo->prepare('SELECT * FROM login_attempts WHERE user_id=?');$q->execute([$a]);$attempts=$q->fetchAll(PDO::FETCH_ASSOC);
    $_SESSION=['failed_login_attempts'=>2];
    $_SERVER['REQUEST_METHOD']='POST';
    $_POST=['username'=>'TakeoverLoginFixture','password'=>'Fixture123!'];
    $_GET=['page'=>'login'];
} elseif (in_array($mode,['waiting','waiting-protected'],true)) {
    $_SESSION=['user_id'=>$b,'role'=>'SUPER_ADMIN','superadmin_session_token'=>$bToken];
    if ($mode==='waiting') $_GET=['page'=>'superadmin_waiting'];
} elseif ($mode==='expired-profile') {
    $_SESSION['profile_recovery_user_id']=$a;
    $_SERVER['REQUEST_METHOD']='POST';
}
ob_start();
register_shutdown_function(function () use ($pdo,$mode,$a,$b,$attempts) {
    $output=ob_get_clean();
    try {
        if (preg_match('/(?:Notice|Warning|Fatal error):/',$output)) throw new RuntimeException('Public PHP diagnostic.');
        if ($mode==='cooldown') {
            if (isset($_SESSION['user_id']) || ($_SESSION['failed_login_attempts']??0)!==2 || empty($_SESSION['login_flash']['takeover_remaining'])) throw new RuntimeException('Cooldown denial changed authentication/counters.');
            $q=$pdo->prepare('SELECT * FROM login_attempts WHERE user_id=?');$q->execute([$a]);
            if ($q->fetchAll(PDO::FETCH_ASSOC)!==$attempts) throw new RuntimeException('Database counters changed.');
        } elseif ($mode==='waiting') {
            if (!str_contains($output,'Super Admin access pending') || str_contains($output,'<h1>Account Management</h1>')) throw new RuntimeException('Wrong waiting screen.');
        } elseif ($mode==='waiting-protected') {
            if (str_contains($output,'<h1>Account Management</h1>') || superAdminSessionState($pdo)['status']!=='waiting') throw new RuntimeException('Waiting requester accessed protected page.');
        } elseif ($mode==='expired-poll') {
            if ((json_decode($output,true)['status']??'')!=='expired' || isset($_SESSION['user_id'])) throw new RuntimeException('Expired polling failed.');
        } else {
            if (isset($_SESSION['user_id']) || empty($_SESSION['superadmin_takeover_ended']) || str_contains($output,'<h1>Account Management</h1>')) throw new RuntimeException('Expired request retained authentication.');
        }
        echo "PASS: takeover request $mode; actual handlers, no PHP diagnostics, all data rolled back.\n";
    } finally { if($pdo->inTransaction())$pdo->rollBack(); if(session_status()===PHP_SESSION_ACTIVE)session_destroy(); }
});
if ($mode==='cooldown') require __DIR__.'/../php/login.php';
elseif ($mode==='expired-poll') require __DIR__.'/../php/superadmin_session_status.php';
elseif ($mode==='expired-profile') { require __DIR__.'/../php/profile_recovery.php'; requireProfileRecoveryAccount($pdo,$a); }
else require __DIR__.'/../php/app.php';
