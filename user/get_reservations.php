<?php

header('Content-Type: application/json');
require_once '../db/db_connection.php';

// Get user ID from request
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if (!$userId) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit;
}

try {
    // Get the member ID from user ID
    $memberQuery = "SELECT member_id FROM member_details WHERE user_id = ?";
    $memberStmt = $pdo->prepare($memberQuery);
    $memberStmt->execute([$userId]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        // Create a new member record if one doesn't exist
        $createMemberQuery = "INSERT INTO member_details (user_id, registration_date) VALUES (?, NOW())";
        $createMemberStmt = $pdo->prepare($createMemberQuery);
        $createMemberStmt->execute([$userId]);
        
        $memberId = $pdo->lastInsertId();
    } else {
        $memberId = $member['member_id'];
    }
    
    // Get reservations for the member
    $reservationsQuery = "SELECT r.*, b.title, b.author, b.isbn, b.cover_image 
                         FROM book_reservations r
                         JOIN books b ON r.book_id = b.book_id
                         WHERE r.member_id = ?
                         ORDER BY r.reservation_date DESC";
    $reservationsStmt = $pdo->prepare($reservationsQuery);
    $reservationsStmt->execute([$memberId]);
    $reservations = $reservationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'reservations' => $reservations
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching reservations: ' . $e->getMessage()
    ]);
}
?>