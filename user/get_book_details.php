<?php

header('Content-Type: application/json');
require_once '../db/db_connection.php';

// Get book ID from request
$bookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;

if (!$bookId) {
    echo json_encode([
        'success' => false,
        'message' => 'Book ID is required'
    ]);
    exit;
}

try {
    // Get book details from database
    $query = "SELECT b.*, c.name as category_name
              FROM books b
              LEFT JOIN categories c ON b.category_id = c.category_id
              WHERE b.book_id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        echo json_encode([
            'success' => false,
            'message' => 'Book not found'
        ]);
        exit;
    }
    
    // Skip the reviews query since the table doesn't exist
    // We'll use dummy reviews from the frontend instead
    $reviews = [];
    
    // Check if book is already reserved by current user (if user_id is provided)
    $reservationStatus = null;
    if (isset($_GET['user_id']) && !empty($_GET['user_id'])) {
        $userId = (int)$_GET['user_id'];
        
        // Get member ID
        $memberQuery = "SELECT member_id FROM member_details WHERE user_id = ?";
        $memberStmt = $pdo->prepare($memberQuery);
        $memberStmt->execute([$userId]);
        $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($member) {
            $reservationQuery = "SELECT status FROM book_reservations 
                                WHERE book_id = ? AND member_id = ? 
                                AND status IN ('pending', 'fulfilled')";
            $reservationStmt = $pdo->prepare($reservationQuery);
            $reservationStmt->execute([$bookId, $member['member_id']]);
            $reservation = $reservationStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation) {
                $reservationStatus = $reservation['status'];
            }
        }
    }
    
    // Return book details without reviews
    echo json_encode([
        'success' => true,
        'book' => $book,
        'reviews' => $reviews,
        'reservation_status' => $reservationStatus
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching book details: ' . $e->getMessage()
    ]);
}
?>