<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../php/provisional_account_cleanup.php';
require_once __DIR__.'/../php/cleanup_enable_authorization.php';
$options=getopt('', ['dry-run','execute','database:','enable-file:','log-file:','limit:','isolated-test']);
if (isset($options['execute'])===isset($options['dry-run'])) { fwrite(STDERR,"Choose exactly one of --dry-run or --execute.\n"); exit(2); }
try {
    $connected=false;
    register_shutdown_function(function() use (&$connected) {
        if (!$connected) {while(ob_get_level()) ob_end_clean();fwrite(STDERR,"Cleanup stopped: database unavailable.\n");exit(1);}
    });
    ob_start();require __DIR__.'/../php/db.php';ob_end_clean();$connected=true;
    if (!isset($pdo) || !($pdo instanceof PDO)) throw new RuntimeException('Database unavailable.');
    $database=$options['database']??$dbname;
    if ($database!==$dbname && !preg_match('/^frostline_step4b_test_[a-f0-9]{16}$/D',$database)) throw new RuntimeException('Unsupported database target.');
    if ($database!==$dbname) $pdo->exec('USE '. $database);
    if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==$database) throw new RuntimeException('Database mismatch.');
    $logger=null;$handle=null;
    if (isset($options['execute'])) {
        $privatePath=function($value) {
            $p=realpath($value);if (!$p) throw new RuntimeException('Private control/log file must already exist.');
            $p=strtolower(str_replace('\\','/',$p));
            foreach (['c:/xampp/htdocs/','c:/xampp/phpmyadmin/'] as $web) if (str_starts_with($p,$web)) throw new RuntimeException('Controls and logs must be outside web roots.');
            return $value;
        };
        $config=readAuthorizedCleanupEnableFile($pdo,(string)($options['enable-file']??''),isset($options['isolated-test']));
        $logPath=$privatePath($options['log-file']??'');
        $handle=fopen($logPath,'ab');if (!$handle||!flock($handle,LOCK_EX|LOCK_NB)) throw new RuntimeException('Logger unavailable or another cleanup is running.');
        $logger=function($record) use($handle,$database) {
            $line=json_encode(['time'=>date(DATE_ATOM),'database'=>$database]+$record,JSON_THROW_ON_ERROR).PHP_EOL;
            if (fwrite($handle,$line)!==strlen($line)||!fflush($handle)||!fsync($handle)) throw new RuntimeException('Could not persist cleanup log.');
        };
    }
    $result=cleanupExpiredAdministrators($pdo,isset($options['dry-run']),(int)($options['limit']??100),$logger);
    echo json_encode(['database'=>$database]+$result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
    if ($handle) {flock($handle,LOCK_UN);fclose($handle);}
} catch (Throwable $e) {
    // Never print transport/database exception messages or credentials.
    fwrite(STDERR,'Cleanup stopped: '.get_class($e).'; no retry or fallback deletion was attempted.'.PHP_EOL);exit(1);
}
