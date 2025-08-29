<?php

header('Content-Type: application/json');
session_start();

// For debugging - log the raw input
file_put_contents('reserve_log.txt', file_get_contents('php://input') . "\n", FILE_APPEND);

// Include database connection
require_once '../db/db_connection.php';

// Get JSON input and convert to PHP array
$data = json_decode(file_get_contents('php://input'), true);

// Log the decoded data
file_put_contents('reserve_log.txt', "Decoded data: " . print_r($data, true) . "\n", FILE_APPEND);

// Check if all required data is present
if (!isset($data['book_id']) || !isset($data['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing required information: ' . (!isset($data['book_id']) ? 'book_id ' : '') . 
                    (!isset($data['user_id']) ? 'user_id' : '')
    ]);
    exit;
}

$bookId = (int)$data['book_id'];
$userId = (int)$data['user_id'];

try {
    // Log the values we're working with
    file_put_contents('reserve_log.txt', "Working with book_id: $bookId, user_id: $userId\n", FILE_APPEND);
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Get the member_id from the user_id
    $memberQuery = "SELECT member_id FROM member_details WHERE user_id = ?";
    $memberStmt = $pdo->prepare($memberQuery);
    $memberStmt->execute([$userId]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    
    // Log the member data
    file_put_contents('reserve_log.txt', "Member data: " . print_r($member, true) . "\n", FILE_APPEND);
    
    if (!$member) {
        // If member not found, create one
        $createMemberQuery = "INSERT INTO member_details (user_id, registration_date) VALUES (?, NOW())";
        $createMemberStmt = $pdo->prepare($createMemberQuery);
        $createMemberStmt->execute([$userId]);
        
        // Get the new member ID
        $memberId = $pdo->lastInsertId();
        file_put_contents('reserve_log.txt', "Created new member with ID: $memberId\n", FILE_APPEND);
    } else {
        $memberId = $member['member_id'];
    }
    
    // Check if the book exists
    $bookQuery = "SELECT book_id, available_copies FROM books WHERE book_id = ?";
    $bookStmt = $pdo->prepare($bookQuery);
    $bookStmt->execute([$bookId]);
    $book = $bookStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    // Check if the book is already issued to someone (not returned)
    $issuedBookQuery = "SELECT i.issue_id 
                      FROM book_issues i 
                      WHERE i.book_id = ? 
                      AND i.status IN ('issued', 'overdue')
                      LIMIT 1";
    $issuedBookStmt = $pdo->prepare($issuedBookQuery);
    $issuedBookStmt->execute([$bookId]);
    $issuedBook = $issuedBookStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($issuedBook) {
        throw new Exception('This book is currently issued to another member and cannot be reserved');
    }
    
    // Check if the book has available copies
    if ($book['available_copies'] <= 0) {
        throw new Exception('This book is not available for reservation');
    }
    
    // Check if the member has already reserved this book
    $existingReservationQuery = "SELECT reservation_id, status FROM book_reservations 
                               WHERE book_id = ? AND member_id = ? AND 
                               status IN ('pending', 'fulfilled')";
    $existingReservationStmt = $pdo->prepare($existingReservationQuery);
    $existingReservationStmt->execute([$bookId, $memberId]);
    $existingReservation = $existingReservationStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingReservation) {
        echo json_encode([
            'success' => false,
            'error' => 'already_reserved',
            'message' => 'You have already reserved this book'
        ]);
        exit;
    }
    
    // Check if the member has reached their reservation limit (max 5 active reservations)
    $activeReservationsQuery = "SELECT COUNT(*) as count FROM book_reservations 
                             WHERE member_id = ? AND status IN ('pending', 'fulfilled')";
    $activeReservationsStmt = $pdo->prepare($activeReservationsQuery);
    $activeReservationsStmt->execute([$memberId]);
    $activeReservations = $activeReservationsStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($activeReservations['count'] >= 5) {
        throw new Exception('You have reached the maximum number of active reservations (5)');
    }
    
    // Calculate expiry date (7 days from now)
    $expiryDate = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Create the reservation with 'pending' status
    $insertQuery = "INSERT INTO book_reservations (book_id, member_id, expiry_date, status) 
                   VALUES (?, ?, ?, 'pending')";
    $insertStmt = $pdo->prepare($insertQuery);
    $insertStmt->execute([$bookId, $memberId, $expiryDate]);
    
    // Get the reservation ID
    $reservationId = $pdo->lastInsertId();
    
    // NOTE: We no longer reduce available_copies here since that happens when the admin fulfills the reservation
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Book reserved successfully! An administrator will review and approve your reservation.',
        'reservation_id' => $reservationId,
        'expiry_date' => $expiryDate
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log the error
    file_put_contents('reserve_log.txt', "Error: " . $e->getMessage() . "\n", FILE_APPEND);
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>