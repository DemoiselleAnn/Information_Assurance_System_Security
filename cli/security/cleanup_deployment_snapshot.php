<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ob_start(); require __DIR__.'/../../php/db.php'; ob_end_clean();
require_once __DIR__.'/../../php/provisional_account_cleanup.php';
if (in_array('--authorize',$argv,true)) {
    require_once __DIR__.'/../../php/cleanup_enable_authorization.php';
    readAuthorizedCleanupEnableFile($pdo,'C:/Users/PC/Documents/FROSTLINECleanup/enabled.json');
}
verifyProvisionalCleanupSchema($pdo);
$snapshot=['database'=>$pdo->query('SELECT DATABASE()')->fetchColumn()];
foreach (['users','security_logs'] as $table) {
    $rows=$pdo->query('SELECT * FROM '.$table.' ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $snapshot[$table.'_count']=count($rows);
    $snapshot[$table.'_sha256']=hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
$snapshot['completed_count']=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE first_login_required=0')->fetchColumn();
$snapshot['eligible_count']=(int)$pdo->query('SELECT COUNT(*) FROM users WHERE '.PROVISIONAL_CLEANUP_PREDICATE)->fetchColumn();
echo json_encode($snapshot,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
