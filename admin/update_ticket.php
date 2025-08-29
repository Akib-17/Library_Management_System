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

// Validate input
if (!isset($_POST['ticket_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Ticket ID is required'
    ]);
    exit;
}

$ticketId = (int)$_POST['ticket_id'];
$adminResponse = isset($_POST['admin_response']) ? htmlspecialchars(trim($_POST['admin_response'])) : null;
$status = isset($_POST['status']) ? $_POST['status'] : null;
$priority = isset($_POST['priority']) ? $_POST['priority'] : null;

// Validate status
$validStatuses = ['pending', 'in_progress', 'resolved', 'closed'];
if ($status && !in_array($status, $validStatuses)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid status'
    ]);
    exit;
}

// Validate priority
$validPriorities = ['low', 'medium', 'high'];
if ($priority && !in_array($priority, $validPriorities)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid priority'
    ]);
    exit;
}

try {
    // Start building the query
    $query = "UPDATE feedback_tickets SET ";
    $params = [];
    $updates = [];
    
    // Add admin response if present
    if ($adminResponse !== null) {
        $updates[] = "admin_response = ?";
        $params[] = $adminResponse;
    }
    
    // Add status if present
    if ($status) {
        $updates[] = "status = ?";
        $params[] = $status;
    }
    
    // Add priority if present
    if ($priority) {
        $updates[] = "priority = ?";
        $params[] = $priority;
    }
    
    // Add assigned_to
    $updates[] = "assigned_to = ?";
    $params[] = $_SESSION['user_id'];
    
    // If no updates, exit
    if (empty($updates)) {
        echo json_encode([
            'success' => false,
            'message' => 'No updates provided'
        ]);
        exit;
    }
    
    // Complete the query
    $query .= implode(", ", $updates);
    $query .= " WHERE ticket_id = ?";
    $params[] = $ticketId;
    
    // Execute the query
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Ticket not found or no changes made'
        ]);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Ticket updated successfully'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating ticket: ' . $e->getMessage()
    ]);
}
?>