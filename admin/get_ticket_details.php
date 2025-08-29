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

// Get ticket ID
if (!isset($_GET['ticket_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Ticket ID is required'
    ]);
    exit;
}

$ticketId = (int)$_GET['ticket_id'];

try {
    $query = "SELECT * FROM feedback_tickets WHERE ticket_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$ticket) {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket not found'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'ticket' => $ticket
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error getting ticket details: ' . $e->getMessage()
    ]);
}
?>