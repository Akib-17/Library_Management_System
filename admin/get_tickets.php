<?php

header('Content-Type: application/json');
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

require_once '../db/db_connection.php';
require_once '../setup/create_feedback_table.php';

try {
    $query = "SELECT * FROM feedback_tickets ORDER BY created_at DESC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tickets' => $tickets
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching tickets: ' . $e->getMessage()
    ]);
}
?>