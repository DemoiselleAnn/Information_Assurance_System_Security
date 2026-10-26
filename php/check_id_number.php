<?php
require_once __DIR__ . '/session_bootstrap.php';
include("db.php");

header('Content-Type: application/json');

try {
    // Handle both POST and GET for testing
    $id_number = null;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id_number'])) {
        $id_number = trim($_POST['id_number']);
    } elseif ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id_number'])) {
        $id_number = trim($_GET['id_number']);
    }

    if ($id_number !== null) {
        // Validate format first
        if (!preg_match('/^\d{4}-\d{4}$/', $id_number)) {
            echo json_encode(['exists' => false, 'invalid_format' => true, 'message' => 'Invalid format']);
            exit;
        }
        
        // Check if ID number exists - exact match
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id_number = ?");
        $stmt->execute([$id_number]);
        $exists = $stmt->fetch() ? true : false;
        
        echo json_encode(['exists' => $exists, 'invalid_format' => false, 'checked' => $id_number]);
        exit;
    }

    // If no ID number provided, return error
    echo json_encode(['exists' => false, 'invalid_format' => true, 'message' => 'No ID No. provided']);
    exit;
} catch (Exception $e) {
    // Return error in JSON format
    echo json_encode(['exists' => false, 'invalid_format' => false, 'error' => true, 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>

