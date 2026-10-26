<?php
require_once __DIR__ . '/../php/db.php';

$superId = (int)$pdo->query("SELECT id FROM users WHERE role = 'SUPER_ADMIN' ORDER BY id LIMIT 1")->fetchColumn();
if (!$superId) {
    fwrite(STDERR, "NO_SUPER_ADMIN\n");
    exit(1);
}

$seed = time();
$username = 'temp.delete.' . $seed;
$email = 'temp.delete.' . $seed . '@example.com';

try {
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        "INSERT INTO users (id_number, first_name, middle_name, last_name, birthdate, age, username, password, role, status, first_login_required, email, sex, purok, barangay, city_municipality, province, country, zip_code, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'USER', 'ACTIVE', 0, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $insert->execute([
        'TMP-' . $seed,
        'Temp',
        '',
        'DeleteUser',
        '1998-01-01',
        27,
        $username,
        password_hash('Abcd1234!', PASSWORD_DEFAULT),
        $email,
        'Female',
        'Purok 1',
        'Barangay 1',
        'City',
        'Province',
        'Country',
        '1000',
    ]);
    $targetId = (int)$pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "CREATE_TARGET_FAILED: " . $e->getMessage() . "\n");
    exit(2);
}

$tempFile = __DIR__ . '/_direct_delete_request.php';
file_put_contents($tempFile, <<<'PHP'
<?php
session_start();
require __DIR__ . '/../php/db.php';
$_SESSION['user_id'] = __SUPER_ID__;
$_SESSION['role'] = 'SUPER_ADMIN';
$_SESSION['account_management_csrf'] = '__CSRF__';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'csrf_token' => '__CSRF__',
    'action' => 'delete_account',
    'account_id' => __TARGET_ID__,
    'deletion_reason' => 'Disposable runtime verification delete.',
];
require __DIR__ . '/../php/account_management.php';
PHP
);

$tempFileContent = file_get_contents($tempFile);
$tempFileContent = str_replace('__SUPER_ID__', (string)$superId, $tempFileContent);
$tempFileContent = str_replace('__CSRF__', 'test-delete-csrf', $tempFileContent);
$tempFileContent = str_replace('__TARGET_ID__', (string)$targetId, $tempFileContent);
file_put_contents($tempFile, $tempFileContent);

$cmd = sprintf('php %s', escapeshellarg($tempFile));
$process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__);
if (!is_resource($process)) {
    unlink($tempFile);
    fwrite(STDERR, "PROC_OPEN_FAILED\n");
    exit(3);
}
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
$exitCode = proc_close($process);
unlink($tempFile);

$remaining = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE id = {$targetId}")->fetchColumn();
$record = (int)$pdo->query("SELECT COUNT(*) FROM deletion_requests WHERE user_id = {$targetId} AND status = 'APPROVED' AND reason = 'Disposable runtime verification delete.'")->fetchColumn();

if ($exitCode !== 0) {
    fwrite(STDERR, $stderr ?: 'DELETE_PROCESS_FAILED');
    exit(4);
}
if ($remaining !== 0) {
    fwrite(STDERR, "DELETE_FAILED\n");
    exit(5);
}
if ($record !== 1) {
    fwrite(STDERR, "AUDIT_MISSING\n");
    exit(6);
}

echo "DIRECT_DELETE_OK\n";
