<?php

header('Content-Type: application/json');
require_once '../db/db_connection.php';

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

// Check if reservation ID is present
if (!isset($data['reservation_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Reservation ID is required'
    ]);
    exit;
}

$reservationId = (int)$data['reservation_id'];

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get reservation details
    $reservationQuery = "SELECT * FROM book_reservations WHERE reservation_id = ?";
    $reservationStmt = $pdo->prepare($reservationQuery);
    $reservationStmt->execute([$reservationId]);
    $reservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        throw new Exception('Reservation not found');
    }
    
    // Check if reservation can be cancelled (only pending reservations can be cancelled)
    if ($reservation['status'] !== 'pending') {
        throw new Exception('Only pending reservations can be cancelled');
    }
    
    // Update reservation status to 'cancelled'
    $updateQuery = "UPDATE book_reservations SET status = 'cancelled' WHERE reservation_id = ?";
    $updateStmt = $pdo->prepare($updateQuery);
    $updateStmt->execute([$reservationId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Reservation cancelled successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>