<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$body=stream_get_contents(STDIN);
// Only boolean test results are persisted; never store message bodies or secrets.
$record=['accepted'=>true,'initial'=>str_contains($body,'Your Account Has Been Created'),'otp'=>str_contains($body,'First Login Verification'),'dummy_password_present'=>str_contains($body,'DummyTemporary123!'),'id_label'=>str_contains($body,'ID No.'),'deadline'=>str_contains($body,'15 minutes')];
if(($argv[1]??'')==='--fail')exit(1);
if(!isset($argv[1])||file_put_contents($argv[1],json_encode($record))===false)exit(1);
