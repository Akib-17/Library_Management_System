<?php
// filepath: c:\xampp\htdocs\Library_management_system\Backend\admin\loans\reservation_handler.php
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

// Include database connection
require_once '../../db/db_connection.php';

// Get action parameter
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'get_all_reservations':
            getAllReservations($pdo);
            break;

        case 'fulfill_reservation':
            fulfillReservation($pdo);
            break;

        case 'cancel_reservation':
            cancelReservation($pdo);
            break;

        case 'add_reservation':
            addReservation($pdo);
            break;

        case 'check_reservation_status':
            checkReservationStatus($pdo);
            break;

        default:
            throw new Exception('Invalid action specified');
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get all reservations grouped by status
 */
function getAllReservations($pdo) {
    // Prepare the query to fetch all reservations with related book and member info
    $query = "
        SELECT 
            r.reservation_id, 
            r.book_id, 
            r.member_id,
            r.reservation_date,
            r.expiry_date,
            r.status,
            b.title AS book_title,
            b.isbn,
            CONCAT(u.first_name, ' ', u.last_name) AS member_name,
            m.membership_number
        FROM book_reservations r
        JOIN books b ON r.book_id = b.book_id
        JOIN member_details m ON r.member_id = m.member_id
        JOIN users u ON m.user_id = u.user_id
        ORDER BY r.reservation_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group reservations by status
    $grouped = [
        'pending' => [],
        'fulfilled' => [],
        'cancelled' => [],
        'expired' => []
    ];
    
    foreach ($reservations as $reservation) {
        $status = $reservation['status'];
        $grouped[$status][] = $reservation;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $grouped
    ]);
}

/**
 * Fulfill a reservation and create a book issue
 */
function fulfillReservation($pdo) {
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        $reservationId = (int)$_POST['reservation_id'];
        
        // Get reservation details
        $query = "
            SELECT 
                r.*, 
                b.title AS book_title,
                b.available_copies,
                b.total_copies,
                m.max_books_allowed,
                (SELECT COUNT(*) FROM book_issues WHERE member_id = r.member_id AND status = 'issued') AS current_loans
            FROM book_reservations r
            JOIN books b ON r.book_id = b.book_id
            JOIN member_details m ON r.member_id = m.member_id
            WHERE r.reservation_id = ?
        ";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reservation) {
            throw new Exception("Reservation not found");
        }
        
        if ($reservation['status'] !== 'pending') {
            throw new Exception("This reservation is already {$reservation['status']}");
        }
        
        // Check if book is available
        if ($reservation['available_copies'] <= 0) {
            throw new Exception("Book is not available for issuing");
        }
        
        // Check if member has reached borrowing limit
        if ($reservation['current_loans'] >= $reservation['max_books_allowed']) {
            throw new Exception("Member has reached their borrowing limit");
        }
        
        // Update reservation status
        $updateQuery = "UPDATE book_reservations SET status = 'fulfilled' WHERE reservation_id = ?";
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute([$reservationId]);
        
        // Calculate due date (14 days from now by default)
        $issueDate = date('Y-m-d H:i:s');
        $dueDate = date('Y-m-d H:i:s', strtotime('+14 days'));
        
        // Insert into book_issues
        $issueQuery = "
            INSERT INTO book_issues (book_id, member_id, issue_date, due_date, issued_by, status)
            VALUES (?, ?, ?, ?, ?, 'issued')
        ";
        
        $stmt = $pdo->prepare($issueQuery);
        $stmt->execute([
            $reservation['book_id'],
            $reservation['member_id'],
            $issueDate,
            $dueDate,
            $_SESSION['user_id']
        ]);
        
        $issueId = $pdo->lastInsertId();
        
        // Update book available copies
        $updateBookQuery = "
            UPDATE books 
            SET available_copies = available_copies - 1 
            WHERE book_id = ?
        ";
        
        $stmt = $pdo->prepare($updateBookQuery);
        $stmt->execute([$reservation['book_id']]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Reservation fulfilled successfully. Book has been issued.",
            'data' => [
                'issue_id' => $issueId,
                'due_date' => $dueDate
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Cancel a reservation
 */
function cancelReservation($pdo) {
    $reservationId = (int)$_POST['reservation_id'];
    
    // Get reservation details
    $query = "SELECT * FROM book_reservations WHERE reservation_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        throw new Exception("Reservation not found");
    }
    
    if ($reservation['status'] !== 'pending') {
        throw new Exception("This reservation is already {$reservation['status']}");
    }
    
    // Update reservation status
    $updateQuery = "UPDATE book_reservations SET status = 'cancelled' WHERE reservation_id = ?";
    $stmt = $pdo->prepare($updateQuery);
    $stmt->execute([$reservationId]);
    
    echo json_encode([
        'success' => true,
        'message' => "Reservation cancelled successfully"
    ]);
}

/**
 * Add a new reservation
 */
function addReservation($pdo) {
    // Check if required parameters are provided
    if (!isset($_POST['book_id']) || !isset($_POST['member_id'])) {
        throw new Exception("Required parameters missing");
    }
    
    $bookId = (int)$_POST['book_id'];
    $memberId = (int)$_POST['member_id'];
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Check if book exists and has copies
        $bookQuery = "SELECT title, total_copies FROM books WHERE book_id = ?";
        $stmt = $pdo->prepare($bookQuery);
        $stmt->execute([$bookId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book) {
            throw new Exception("Book not found");
        }
        
        // Check if member exists and has not reached borrowing limit
        $memberQuery = "
            SELECT 
                m.*, 
                (SELECT COUNT(*) FROM book_issues WHERE member_id = m.member_id AND status = 'issued') AS current_loans
            FROM member_details m
            WHERE m.member_id = ?
        ";
        
        $stmt = $pdo->prepare($memberQuery);
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            throw new Exception("Member not found");
        }
        
        if ($member['current_loans'] >= $member['max_books_allowed']) {
            throw new Exception("Member has reached their borrowing limit");
        }
        
        // Check if there's already a pending reservation for this book by this member
        $checkQuery = "
            SELECT COUNT(*) FROM book_reservations 
            WHERE book_id = ? AND member_id = ? AND status = 'pending'
        ";
        
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$bookId, $memberId]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Member already has a pending reservation for this book");
        }
        
        // Set reservation expiry date to 7 days from now
        $reservationDate = date('Y-m-d H:i:s');
        $expiryDate = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Insert reservation
        $insertQuery = "
            INSERT INTO book_reservations (book_id, member_id, reservation_date, expiry_date, status)
            VALUES (?, ?, ?, ?, 'pending')
        ";
        
        $stmt = $pdo->prepare($insertQuery);
        $stmt->execute([$bookId, $memberId, $reservationDate, $expiryDate]);
        
        $reservationId = $pdo->lastInsertId();
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => "Book reserved successfully",
            'data' => [
                'reservation_id' => $reservationId,
                'expiry_date' => $expiryDate
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Check status of a member's reservation
 */
function checkReservationStatus($pdo) {
    if (!isset($_POST['member_id']) || !isset($_POST['book_id'])) {
        throw new Exception("Required parameters missing");
    }
    
    $memberId = (int)$_POST['member_id'];
    $bookId = (int)$_POST['book_id'];
    
    $query = "
        SELECT * FROM book_reservations 
        WHERE member_id = ? AND book_id = ? 
        ORDER BY reservation_date DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$memberId, $bookId]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reservation) {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => "No reservation found for this member and book"
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'exists' => true,
            'data' => $reservation,
            'message' => "Reservation found with status: {$reservation['status']}"
        ]);
    }
}

/**
 * Helper function to check for expired reservations and update their status
 * This should be called periodically, possibly via a cron job
 */
function updateExpiredReservations($pdo) {
    $currentDateTime = date('Y-m-d H:i:s');
    
    $query = "
        UPDATE book_reservations 
        SET status = 'expired' 
        WHERE status = 'pending' AND expiry_date < ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentDateTime]);
    
    $affectedRows = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "$affectedRows expired reservations updated"
    ]);
}
?>