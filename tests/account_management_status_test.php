<?php
// Focused offline regression checks; never connect to the application database.
$source = str_replace("\r\n", "\n", file_get_contents(__DIR__ . '/../php/account_management.php'));
function checkAccountManagement(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}

checkAccountManagement(!str_contains($source, "\$_POST['status']"), 'Edit must ignore forged status parameters.');
checkAccountManagement(substr_count($source, 'select name="status"') === 1, 'Only the search status filter should remain.');
checkAccountManagement(!str_contains($source, 'value="unblock"'), 'Account Management must not render Unblock.');
$start = strpos($source, "    if (!in_array(\$action,");
$end = strpos($source, '    $targetId =', $start);
$guard = substr($source, $start, $end - $start);
// Execute the actual action guard in a child process because it exits on rejection.
$process = proc_open([PHP_BINARY, '-r', '$action = "unblock";' . $guard], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
checkAccountManagement(is_resource($process), 'Could not run action guard.');
$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
checkAccountManagement($errors === '' && $output === 'Unsupported account action', 'Forged unblock must be rejected.');
checkAccountManagement(str_contains($source, "if (\$action === 'block') {\n        requirePermission(\$pdo, 'BLOCK_ACCOUNTS');"), 'Blocking must require BLOCK_ACCOUNTS.');
echo "PASS: edit/filter markup, forged status isolation, executed unblock rejection, block permission guard\n";

