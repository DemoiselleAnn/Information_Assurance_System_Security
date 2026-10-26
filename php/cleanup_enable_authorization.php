<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
const CLEANUP_PRODUCTION_ENABLE_FILE = 'C:/Users/PC/Documents/FROSTLINECleanup/enabled.json';

function readAuthorizedCleanupEnableFile(PDO $pdo, string $path, bool $isolatedTest = false, ?string $testFault = null): array {
    $database=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($isolatedTest && !preg_match('/^frostline_step4b_test_[a-f0-9]{16}$/D',$database)) throw new RuntimeException('Isolated authorization cannot target production.');
    if (!$isolatedTest && $database!=='cakey_db') throw new RuntimeException('Production authorization requires cakey_db.');
    if ($testFault!==null && (!$isolatedTest || !in_array($testFault,['owner_unverified','inspection_failed'],true))) throw new RuntimeException('Unsupported isolated authorization fault.');
    if (PHP_OS_FAMILY!=='Windows') throw new RuntimeException('Windows authorization inspection required.');
    $normal=str_replace('\\','/',$path);
    $expected=CLEANUP_PRODUCTION_ENABLE_FILE;
    if ($isolatedTest) {
        $base=str_replace('\\','/',sys_get_temp_dir());
        if (!preg_match('~^'.preg_quote($base,'~').'/frostline-authorization-[a-f0-9]{16}/enabled[.]json$~iD',$normal)) throw new RuntimeException('Unexpected isolated authorization path.');
        $expected=$normal;
    }
    if (strcasecmp($normal,$expected)!==0) throw new RuntimeException('Authorization path is not approved.');
    // No traversal, UNC/device paths, alternate streams, short names, or substitutions.
    if (!preg_match('~^[A-Za-z]:/~D',$normal) || preg_match('~(^|/)[.]{1,2}(/|$)~',$normal)) throw new RuntimeException('Noncanonical authorization path.');
    $script=file_get_contents(__DIR__.'/../cli/security/inspect_cleanup_authorization.ps1');
    if ($script===false) throw new RuntimeException('Authorization inspector unavailable.');
    $systemRoot='C:/Windows'; // Reviewed system location; do not trust a caller's environment override.
    $shell=rtrim($systemRoot,'/\\').'/System32/WindowsPowerShell/v1.0/powershell.exe';
    if (!is_file($shell)) throw new RuntimeException('Windows inspector executable unavailable.');
    if ($isolatedTest && $testFault==='inspection_failed') $script="[Console]::Out.Write('invalid-inspection-response')";
    $payload=json_encode(['path'=>$normal,'expected'=>$expected,'database'=>$database,'isolated'=>$isolatedTest,'test_fault'=>$testFault],JSON_THROW_ON_ERROR);
    $environment=getenv();$environment['PSModulePath']=rtrim($systemRoot,'/\\').'/System32/WindowsPowerShell/v1.0/Modules';
    $pipes=[];$process=proc_open([$shell,'-NoProfile','-NonInteractive','-Command',$script],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes,null,$environment);
    if (!is_resource($process)) throw new RuntimeException('Authorization inspection failed.');
    fwrite($pipes[0],$payload);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    if ($exit!==0 || $err!=='') throw new RuntimeException('Windows file authorization failed.');
    $result=json_decode($out,true,512,JSON_THROW_ON_ERROR);
    if (($result['ok']??false)!==true || !is_string($result['contents_base64']??null)) throw new RuntimeException('Invalid permission inspection response.');
    $bytes=base64_decode($result['contents_base64'],true);if ($bytes===false) throw new RuntimeException('Invalid checked control bytes.');
    $config=json_decode($bytes,true,512,JSON_THROW_ON_ERROR);
    if (!is_array($config) || ($config['enabled']??false)!==true || ($config['database']??null)!==$database) throw new RuntimeException('Cleanup is disabled or authorization database mismatches.');
    return $config;
}
