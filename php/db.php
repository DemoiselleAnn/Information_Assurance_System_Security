<?php
date_default_timezone_set('Asia/Manila');

$host = 'localhost';
$dbname = 'cakey_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone = '+08:00'");
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

/** Reserve one shared account number in the caller's insert transaction.
 * The singleton row lock lasts until commit/rollback; previews must not call this.
 */
function generateAccountId(PDO $pdo): string {
    if (!$pdo->inTransaction()) {
        throw new LogicException('Account IDs require an active transaction.');
    }
    $number = $pdo->query("SELECT last_number FROM account_id_sequence WHERE id = 1 FOR UPDATE")->fetchColumn();
    if ($number === false) {
        throw new RuntimeException('Run the verified sql/upgrade_existing_database.sql workflow before creating accounts.');
    }
    $year = date('Y');
    $exists = $pdo->prepare('SELECT id FROM users WHERE id_number = ?');
    do {
        $number = (int)$number + 1;
        $accountId = $year . '-' . str_pad((string)$number, 4, '0', STR_PAD_LEFT);
        $exists->execute([$accountId]);
    } while ($exists->fetchColumn() !== false);
    $pdo->prepare('UPDATE account_id_sequence SET last_number = ? WHERE id = 1')->execute([$number]);
    return $accountId;
}
