<?php
require_once __DIR__ . '/session_bootstrap.php';
include("db.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $exists = $stmt->fetch() ? true : false;
    
    header('Content-Type: application/json');
    echo json_encode(['exists' => $exists]);
    exit;
}
?>