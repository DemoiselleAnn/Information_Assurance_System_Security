<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function (int $severity, string $message, string $file, int $line): never { throw new ErrorException($message, 0, $severity, $file, $line); });
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../php/session_bootstrap.php';
require_once __DIR__ . '/../php/db.php';
class FirstSetupTestPDO extends PDO {
    public function beginTransaction(): bool { return $this->inTransaction() ? true : parent::beginTransaction(); }
    public function commit(): bool { return true; }
}
$pdo = new FirstSetupTestPDO('mysql:host=localhost;dbname=cakey_db', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$role = $argv[1] ?? 'SUPER_ADMIN';
$mode = $argv[2] ?? 'success';
$stmt = $pdo->prepare('SELECT id, username FROM users WHERE role = ? ORDER BY id LIMIT 1');
$stmt->execute([$role]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) throw new RuntimeException('Missing fixture account.');
$userId = (int)$user['id'];
$temporaryPassword = 'Temporary123!';
$newPassword = 'Configured123!';
$pdo->prepare("UPDATE users SET first_login_required = 1, status = 'INACTIVE', password = ?, first_name = 'Setup', middle_name = NULL, last_name = 'Fixture', temporary_credential_expires_at = DATE_ADD(NOW(), INTERVAL 1 DAY), temporary_credential_used_at = NOW() WHERE id = ?")->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $userId]);
$pdo->prepare('INSERT INTO password_recovery (user_id, otp_hash, expires_at, last_sent_at, otp_verified) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), NOW(), ?)')->execute([$userId, password_hash('123456', PASSWORD_DEFAULT), $mode === 'otp' ? 0 : 1]);
$challengeId = (int)$pdo->lastInsertId();
$_SESSION = ['first_login_user_id' => $userId, 'first_login_username' => $user['username'], 'first_login_full_name' => 'Setup Fixture', 'first_login_email' => 'fixture@example.com', 'first_login_role' => $role, 'first_login_challenge_id' => $challengeId, 'first_login_stage' => $mode === 'otp' ? 'otp_pending' : 'otp_verified', 'first_login_setup_csrf' => 'fixture-setup-token', 'first_login_otp_csrf' => 'fixture-otp-token'];
$_SERVER['REQUEST_METHOD'] = $mode === 'render' ? 'GET' : 'POST';
$_GET = ['page' => $mode === 'render' ? 'first_login_setup' : 'first_login_otp'];
$_POST = ['csrf_token' => 'fixture-setup-token', 'new_password' => $newPassword, 'confirm_password' => $newPassword, 'question1' => 'Who is your best friend in Elementary?', 'question2' => 'What was the name of your first school?', 'question3' => 'What was your childhood nickname?', 'answer1' => 'Fixture One', 'answer2' => 'Fixture Two', 'answer3' => 'Fixture Three'];
if ($mode === 'otp') $_POST = ['csrf_token' => 'fixture-otp-token', 'otp' => '123456', 'action' => 'verify'];
if ($mode === 'csrf') $_POST['csrf_token'] = 'wrong';
if ($mode === 'duplicates') $_POST['question2'] = $_POST['question1'];
$oldId = session_id();
if ($mode === 'success') $_SESSION['superadmin_session_token'] = 'stale-pre-setup-token';
ob_start();
register_shutdown_function(function () use ($pdo, $userId, $challengeId, $role, $mode, $newPassword, $temporaryPassword, $oldId, $argv) {
    $output = ob_get_clean();
    try {
        $stmt = $pdo->prepare('SELECT password, first_login_required, role FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mode === 'success') {
            if ((int)$account['first_login_required'] !== 0 || !password_verify($newPassword, $account['password']) || password_verify($temporaryPassword, $account['password'])) throw new RuntimeException('Password/setup flags not updated.');
            if (($_SESSION['role'] ?? '') !== $role || ($_SESSION['user_id'] ?? 0) !== $userId || isset($_SESSION['first_login_stage']) || session_id() === $oldId) throw new RuntimeException('Authenticated session not established correctly.');
            if ($role !== 'SUPER_ADMIN' && isset($_SESSION['superadmin_session_token'])) throw new RuntimeException('Stale Super Admin token leaked into a normal account session.');
            $stmt = $pdo->prepare('SELECT answer FROM security_answers WHERE user_id = ? ORDER BY id');
            $stmt->execute([$userId]);
            $answers = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (count($answers) !== 3 || !password_verify('fixture one', $answers[0])) throw new RuntimeException('Security answers not stored securely.');
            $stmt = $pdo->prepare('SELECT used_at FROM password_recovery WHERE id = ?');
            $stmt->execute([$challengeId]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Challenge not consumed.');
        } elseif ($mode === 'otp') {
            if (($_SESSION['first_login_stage'] ?? '') !== 'otp_verified' || isset($_SESSION['user_id'])) throw new RuntimeException('OTP did not advance the pre-authentication session.');
        } elseif ($mode === 'render') {
            if (!str_contains($output, 'id="setupForm"') || ($_SESSION['first_login_stage'] ?? '') !== 'otp_verified' || isset($_SESSION['user_id'])) throw new RuntimeException('Router lost verified setup state.');
            if (isset($argv[3])) file_put_contents($argv[3], $output);
        } else {
            if ((int)$account['first_login_required'] !== 1 || empty($_SESSION['first_login_setup_errors'])) throw new RuntimeException('Invalid setup was accepted or error missing.');
        }
        if (preg_match('/(?:Warning|Notice|Fatal error):/', $output)) throw new RuntimeException('PHP diagnostic in response.');
        echo "PASS: first-login $role $mode. All database changes rolled back.\n";
    } finally {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }
});
if ($mode === 'render') require __DIR__ . '/../php/app.php';
elseif ($mode === 'otp') require __DIR__ . '/../php/first_login_otp.php';
else require __DIR__ . '/../php/process_first_login_setup.php';
