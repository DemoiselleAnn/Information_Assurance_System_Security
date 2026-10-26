<?php
// Exercise recovery verification and password changes without sending email.
error_reporting(E_ALL); ini_set('display_errors','1');
set_error_handler(static function($level,$message,$file,$line): never { throw new ErrorException($message,0,$level,$file,$line); });
ini_set('session.save_path',sys_get_temp_dir());
require_once __DIR__.'/../php/session_bootstrap.php';
require_once __DIR__.'/../php/db.php';
require_once __DIR__.'/../php/superadmin_session.php';
require_once __DIR__.'/../php/profile_recovery.php';
class RecoveryRegressionPDO extends PDO {
    public function beginTransaction(): bool { return $this->inTransaction()?true:parent::beginTransaction(); }
    public function commit(): bool { return true; }
}
$pdo=new RecoveryRegressionPDO('mysql:host=localhost;dbname=cakey_db','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec("SET time_zone='+08:00'");$pdo->beginTransaction();
$role=$argv[1]??'SUPER_ADMIN';$mode=$argv[2]??'otp';
$q=$pdo->prepare('SELECT id FROM users WHERE role=? ORDER BY id LIMIT 1');$q->execute([$role]);$id=(int)$q->fetchColumn();
if (!$id) throw new RuntimeException('Missing fixture account.');
$pdo->prepare("UPDATE users SET status='ACTIVE',first_login_required=0,email='fixture@example.com' WHERE id=?")->execute([$id]);
$pdo->prepare('INSERT INTO password_recovery (user_id,otp_hash,expires_at,last_sent_at,otp_verified) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),?)')->execute([$id,password_hash('123456',PASSWORD_DEFAULT),$mode==='otp'?0:1]);
$challenge=(int)$pdo->lastInsertId();
$_SERVER['REQUEST_METHOD']='POST';
$_SESSION=['forgot_user_id'=>$id,'recovery_challenge_id'=>$challenge,'recovery_stage'=>'otp_verified','otp_csrf'=>'fixture-token','security_questions_csrf'=>'fixture-token','reset_user_id'=>$id,'reset_challenge_id'=>$challenge,'reset_csrf'=>'fixture-token','security_verified'=>true];
$_POST=['csrf_token'=>'fixture-token','otp'=>'123456','new_password'=>'Regression123!','reenter_password'=>'Regression123!'];
if ($mode==='reset') $_SESSION['recovery_stage']='security_verified';
if ($mode==='questions') {
    $pdo->prepare('DELETE FROM security_answers WHERE user_id=?')->execute([$id]);
    foreach(array_slice(recoveryQuestionBank(),0,3) as $n=>$question) {
        $pdo->prepare('INSERT INTO security_answers (user_id,question,answer) VALUES (?,?,?)')->execute([$id,$question,password_hash('fixture answer',PASSWORD_DEFAULT)]);
        $_POST['question'.($n+1)]=$question;$_POST['answer'.($n+1)]='Fixture Answer';
    }
}
if ($mode==='profile-change') {
    $_SESSION['user_id']=$id;$_SESSION['role']=$role;$_SESSION['profile_recovery_user_id']=$id;
    if ($role==='SUPER_ADMIN') {
        $pdo->exec('UPDATE superadmin_session_state SET token_hash=NULL,user_id=NULL,pending_token_hash=NULL,pending_user_id=NULL WHERE id=1');
        $_SESSION['superadmin_session_token']=activateSuperAdminSession($pdo,$id);
    }
}
ob_start();
register_shutdown_function(function() use($pdo,$id,$challenge,$role,$mode) {
    $output=ob_get_clean();
    try {
        if (preg_match('/(?:Notice|Warning|Fatal error):/',$output))throw new RuntimeException('Public PHP diagnostic.');
        if ($mode==='otp' && ($_SESSION['recovery_stage']??'')!=='otp_verified')throw new RuntimeException('OTP failed.');
        if ($mode==='questions' && ($_SESSION['recovery_stage']??'')!=='security_verified')throw new RuntimeException('Security questions failed.');
        if (in_array($mode,['reset','profile-change'],true)) {
            $q=$pdo->prepare('SELECT password FROM users WHERE id=?');$q->execute([$id]);
            if(!password_verify('Regression123!',$q->fetchColumn()))throw new RuntimeException('Password change failed.');
            $q=$pdo->prepare('SELECT used_at FROM password_recovery WHERE id=?');$q->execute([$challenge]);
            if(!$q->fetchColumn())throw new RuntimeException('Challenge not consumed.');
        }
        echo "PASS: recovery $role $mode; no email sent, all data rolled back.\n";
    } finally {if($pdo->inTransaction())$pdo->rollBack();if(session_status()===PHP_SESSION_ACTIVE)session_destroy();}
});
require __DIR__.'/../php/'.['otp'=>'process_otp.php','questions'=>'process_forgot_password.php','reset'=>'process_change_password.php','profile-change'=>'process_change_password.php'][$mode];
