<?php
// filepath: c:\xampp\htdocs\Library_management_system\Backend\admin\loans\loan_handler.php
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
        case 'get_all_loans':
            getAllLoans($pdo);
            break;

        case 'search_members':
            searchMembers($pdo);
            break;

        case 'search_books':
            searchBooks($pdo);
            break;

        case 'get_member_details':
            getMemberDetails($pdo);
            break;

        case 'get_book_details':
            getBookDetails($pdo);
            break;

        case 'issue_book':
            issueBook($pdo);
            break;

        case 'get_issue_details':
            getIssueDetails($pdo);
            break;

        case 'return_book':
            returnBook($pdo);
            break;

        case 'renew_loan':
            renewLoan($pdo);
            break;

        case 'get_receipt_data':
            getReceiptData($pdo);
            break;

        case 'export_loans':
            exportLoans($pdo);
            break;
        case 'update_membership_type':
            updateMembershipType($pdo);
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
 * Get all book loans with details
 */
function getAllLoans($pdo) {
    // Check for overdue books first
    updateOverdueStatus($pdo);
    
    // Get all loans with book and member details
    $query = "
        SELECT 
            i.issue_id,
            i.book_id,
            i.member_id,
            i.issue_date,
            i.due_date,
            i.return_date,
            i.status,
            b.title AS book_title,
            b.isbn,
            CONCAT(u.first_name, ' ', u.last_name) AS member_name,
            m.membership_number
        FROM book_issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN member_details m ON i.member_id = m.member_id
        JOIN users u ON m.user_id = u.user_id
        ORDER BY i.issue_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $loans
    ]);
}

/**
 * Search for members by name, email or membership number
 */
function searchMembers($pdo) {
    if (!isset($_POST['search_term']) || empty($_POST['search_term'])) {
        throw new Exception('Search term is required');
    }
    
    $searchTerm = '%' . $_POST['search_term'] . '%';
    
    // Search for members
    $query = "
        SELECT 
            m.member_id,
            m.membership_number,
            m.membership_type,
            m.max_books_allowed,
            u.first_name,
            u.last_name,
            u.email,
            (SELECT COUNT(*) FROM book_issues WHERE member_id = m.member_id AND status IN ('issued', 'overdue')) AS current_loans
        FROM member_details m
        JOIN users u ON m.user_id = u.user_id
        WHERE 
            m.membership_number LIKE ? OR
            u.first_name LIKE ? OR
            u.last_name LIKE ? OR
            u.email LIKE ?
        ORDER BY u.first_name, u.last_name
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $members
    ]);
}


/**
 * Update a member's membership type and max books allowed
 */
function updateMembershipType($pdo) {
    if (!isset($_POST['member_id']) || !isset($_POST['membership_type'])) {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required parameters'
        ]);
        return;
    }
    
    $memberId = (int)$_POST['member_id'];
    $membershipType = $_POST['membership_type'];
    
    // Validate membership type
    $validTypes = ['regular', 'premium', 'student', 'faculty'];
    if (!in_array($membershipType, $validTypes)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid membership type'
        ]);
        return;
    }
    
    // Determine max_books_allowed based on membership type
    $maxBooksAllowed = 5; // Default for 'regular'
    
    switch ($membershipType) {
        case 'premium':
            $maxBooksAllowed = 10;
            break;
        case 'student':
        case 'faculty':
            $maxBooksAllowed = 50;
            break;
    }
    
    try {
        // Update membership type and max_books_allowed
        $query = "UPDATE member_details SET membership_type = ?, max_books_allowed = ? WHERE member_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$membershipType, $maxBooksAllowed, $memberId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('No changes made or member not found');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Membership type updated successfully',
            'data' => [
                'membership_type' => $membershipType,
                'max_books_allowed' => $maxBooksAllowed
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error updating membership type: ' . $e->getMessage()
        ]);
    }
}

/**
 * Search for books by title, author or ISBN
 */
function searchBooks($pdo) {
    if (!isset($_POST['search_term']) || empty($_POST['search_term'])) {
        throw new Exception('Search term is required');
    }
    
    $searchTerm = '%' . $_POST['search_term'] . '%';
    
    // Search for books
    $query = "
        SELECT 
            b.book_id,
            b.title,
            b.author,
            b.isbn,
            b.total_copies,
            b.available_copies,
            b.shelf_location,
            c.name AS category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.category_id
        WHERE 
            b.title LIKE ? OR
            b.author LIKE ? OR
            b.isbn LIKE ?
        ORDER BY b.title
        LIMIT 20
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $books
    ]);
}

/**
 * Get member details by ID including current loans
 */
function getMemberDetails($pdo) {
    if (!isset($_POST['member_id'])) {
        throw new Exception('Member ID is required');
    }
    
    $memberId = (int)$_POST['member_id'];
    
    // Get member details
    $query = "
        SELECT 
            m.*,
            u.first_name,
            u.last_name,
            u.email,
            (SELECT COUNT(*) FROM book_issues WHERE member_id = m.member_id AND status IN ('issued', 'overdue')) AS current_loans
        FROM member_details m
        JOIN users u ON m.user_id = u.user_id
        WHERE m.member_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        throw new Exception('Member not found');
    }
    
    // Get current loans for this member
    $loansQuery = "
        SELECT 
            i.issue_id,
            i.issue_date,
            i.due_date,
            i.status,
            b.title AS book_title
        FROM book_issues i
        JOIN books b ON i.book_id = b.book_id
        WHERE i.member_id = ? AND i.status IN ('issued', 'overdue')
        ORDER BY i.issue_date DESC
    ";
    
    $stmt = $pdo->prepare($loansQuery);
    $stmt->execute([$memberId]);
    $loans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'member' => $member,
            'loans' => $loans
        ]
    ]);
}

/**
 * Get book details by ID
 */
function getBookDetails($pdo) {
    if (!isset($_POST['book_id'])) {
        throw new Exception('Book ID is required');
    }
    
    $bookId = (int)$_POST['book_id'];
    
    // Get book details
    $query = "
        SELECT 
            b.*,
            c.name AS category_name
        FROM books b
        LEFT JOIN categories c ON b.category_id = c.category_id
        WHERE b.book_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $book
    ]);
}

/**
 * Issue book to member
 */
function issueBook($pdo) {
    // Check required fields
    if (!isset($_POST['member_id']) || !isset($_POST['book_id']) || 
        !isset($_POST['issue_date']) || !isset($_POST['due_date'])) {
        throw new Exception('Required fields are missing');
    }
    
    $memberId = (int)$_POST['member_id'];
    $bookId = (int)$_POST['book_id'];
    $issueDate = $_POST['issue_date'];
    $dueDate = $_POST['due_date'];
    $checkReservation = isset($_POST['check_reservation']) && $_POST['check_reservation'] === 'on';
    
    // Start a transaction
    $pdo->beginTransaction();
    
    try {
        // Check if book is available
        $bookQuery = "SELECT title, available_copies FROM books WHERE book_id = ?";
        $stmt = $pdo->prepare($bookQuery);
        $stmt->execute([$bookId]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$book) {
            throw new Exception('Book not found');
        }
        
        // Debug - log the actual value
        error_log('Book ID: ' . $bookId . ', Available copies: ' . $book['available_copies'] . ' (type: ' . gettype($book['available_copies']) . ')');

        if ((int)$book['available_copies'] <= 0) {
            
            return false;
        }
        
        // Check if member exists and has not reached borrowing limit
        $memberQuery = "
            SELECT 
                m.*, 
                (SELECT COUNT(*) FROM book_issues WHERE member_id = m.member_id AND status IN ('issued', 'overdue')) AS current_loans
            FROM member_details m
            WHERE m.member_id = ?
        ";
        
        $stmt = $pdo->prepare($memberQuery);
        $stmt->execute([$memberId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            throw new Exception('Member not found');
        }
        
        if ($member['current_loans'] >= $member['max_books_allowed']) {
            throw new Exception('Member has reached their borrowing limit');
        }
        
        // Check if this book is already issued to this member
        $checkQuery = "
            SELECT COUNT(*) FROM book_issues 
            WHERE book_id = ? AND member_id = ? AND status IN ('issued', 'overdue')
        ";
        
        $stmt = $pdo->prepare($checkQuery);
        $stmt->execute([$bookId, $memberId]);
        
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('This book is already issued to this member');
        }
        
        // Check if there's a reservation for this book by this member if requested
        if ($checkReservation) {
            $reservationQuery = "
                SELECT reservation_id FROM book_reservations 
                WHERE book_id = ? AND member_id = ? AND status = 'pending'
                ORDER BY reservation_date ASC
                LIMIT 1
            ";
            
            $stmt = $pdo->prepare($reservationQuery);
            $stmt->execute([$bookId, $memberId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($reservation) {
                // Mark reservation as fulfilled
                $updateReservationQuery = "
                    UPDATE book_reservations 
                    SET status = 'fulfilled' 
                    WHERE reservation_id = ?
                ";
                
                $stmt = $pdo->prepare($updateReservationQuery);
                $stmt->execute([$reservation['reservation_id']]);
            }
        }
        
        // Create the issue record
        $issueQuery = "
            INSERT INTO book_issues (
                book_id, member_id, issue_date, due_date, issued_by, status
            ) VALUES (?, ?, ?, ?, ?, 'issued')
        ";
        
        $stmt = $pdo->prepare($issueQuery);
        $stmt->execute([
            $bookId,
            $memberId,
            date('Y-m-d H:i:s', strtotime($issueDate)),
            date('Y-m-d H:i:s', strtotime($dueDate)),
            $_SESSION['user_id']
        ]);
        
        $issueId = $pdo->lastInsertId();
        
        // Update book availability
        $updateBookQuery = "
            UPDATE books 
            SET available_copies = available_copies - 1 
            WHERE book_id = ?
        ";
        
        $stmt = $pdo->prepare($updateBookQuery);
        $stmt->execute([$bookId]);
        
        // Commit the transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Book issued successfully',
            'data' => [
                'issue_id' => $issueId
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get loan/issue details by ID
 */
function getIssueDetails($pdo) {
    if (!isset($_POST['issue_id'])) {
        throw new Exception('Issue ID is required');
    }
    
    $issueId = (int)$_POST['issue_id'];
    
    // Get issue details
    $query = "
        SELECT 
            i.*,
            b.title AS book_title,
            b.isbn,
            CONCAT(u.first_name, ' ', u.last_name) AS member_name
        FROM book_issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN member_details m ON i.member_id = m.member_id
        JOIN users u ON m.user_id = u.user_id
        WHERE i.issue_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$issue) {
        throw new Exception('Issue record not found');
    }
    
    echo json_encode([
        'success' => true,
        'data' => $issue
    ]);
}

/**
 * Process book return
 */
function returnBook($pdo) {
    // Check required fields
    if (!isset($_POST['issue_id']) || !isset($_POST['book_id']) || !isset($_POST['return_date'])) {
        throw new Exception('Required fields are missing');
    }
    
    $issueId = (int)$_POST['issue_id'];
    $bookId = (int)$_POST['book_id'];
    $returnDate = $_POST['return_date'];
    $isLost = isset($_POST['is_lost']) && $_POST['is_lost'] === 'true';
    $isDamaged = isset($_POST['is_damaged']) && $_POST['is_damaged'] === 'true';
    $fineAmount = isset($_POST['fine_amount']) ? (float)$_POST['fine_amount'] : 0;
    $fineReason = isset($_POST['fine_reason']) ? $_POST['fine_reason'] : 'overdue';
    
    // Start a transaction
    $pdo->beginTransaction();
    
    try {
        // Get issue details
        $issueQuery = "
            SELECT * FROM book_issues 
            WHERE issue_id = ? AND status != 'returned'
        ";
        
        $stmt = $pdo->prepare($issueQuery);
        $stmt->execute([$issueId]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$issue) {
            throw new Exception('Issue record not found or already returned');
        }
        
        // Update the issue record
        $updateIssueQuery = "
            UPDATE book_issues 
            SET 
                return_date = ?, 
                returned_to = ?,
                status = ?
            WHERE issue_id = ?
        ";
        
        $newStatus = 'returned';
        if ($isLost) {
            $newStatus = 'lost';
        }
        
        $stmt = $pdo->prepare($updateIssueQuery);
        $stmt->execute([
            date('Y-m-d H:i:s', strtotime($returnDate)),
            $_SESSION['user_id'],
            $newStatus,
            $issueId
        ]);
        
        // Update book availability if not lost
        if (!$isLost) {
            $updateBookQuery = "
                UPDATE books 
                SET available_copies = available_copies + 1 
                WHERE book_id = ?
            ";
            
            $stmt = $pdo->prepare($updateBookQuery);
            $stmt->execute([$bookId]);
        }
        
        // Create fine record if there's a fine
        if ($fineAmount > 0 || $isDamaged || $isLost) {
            // Determine fine reason
            if ($isLost) {
                $fineReason = 'lost';
            } else if ($isDamaged) {
                $fineReason = 'damage';
            }
            
            // Add damage fee if book is damaged
            if ($isDamaged && $fineReason === 'damage') {
                $fineAmount += 10.00; // Add $10 damage fee
            }
            
            // Add replacement fee if book is lost
            if ($isLost) {
                // Get book price or use default replacement fee
                $bookQuery = "SELECT price FROM books WHERE book_id = ?";
                $stmt = $pdo->prepare($bookQuery);
                $stmt->execute([$bookId]);
                $book = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $replacementFee = isset($book['price']) && $book['price'] > 0 ? $book['price'] : 50.00; // Default to $50 if no price
                $fineAmount += $replacementFee;
            }
            
            $fineQuery = "
                INSERT INTO fines (
                    issue_id, member_id, fine_amount, fine_reason, status
                ) VALUES (?, ?, ?, ?, 'pending')
            ";
            
            $stmt = $pdo->prepare($fineQuery);
            $stmt->execute([
                $issueId,
                $issue['member_id'],
                $fineAmount,
                $fineReason
            ]);
        }
        
        // Commit the transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Book returned successfully' . ($fineAmount > 0 ? ' with fine of $' . number_format($fineAmount, 2) : ''),
            'data' => [
                'issue_id' => $issueId,
                'fine_amount' => $fineAmount,
                'fine_reason' => $fineReason
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback the transaction
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Renew/extend a book loan
 */
function renewLoan($pdo) {
    if (!isset($_POST['issue_id']) || !isset($_POST['new_due_date'])) {
        throw new Exception('Required fields are missing');
    }
    
    $issueId = (int)$_POST['issue_id'];
    $newDueDate = $_POST['new_due_date'];
    
    try {
        // Get issue details first before starting transaction
        $issueQuery = "SELECT * FROM book_issues WHERE issue_id = ?";
        $stmt = $pdo->prepare($issueQuery);
        $stmt->execute([$issueId]);
        $issue = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$issue) {
            throw new Exception('Issue record not found');
        }
        
        if ($issue['status'] === 'returned') {
            throw new Exception('Cannot renew a returned book');
        }

        // Start transaction AFTER validation
        $pdo->beginTransaction();
        
        // Check if column exists in the table before using it
        try {
            $checkColumnQuery = "SHOW COLUMNS FROM book_issues LIKE 'renewed_count'";
            $stmt = $pdo->prepare($checkColumnQuery);
            $stmt->execute();
            $columnExists = $stmt->fetch();
            
            // Create the column if it doesn't exist
            if (!$columnExists) {
                $alterTableQuery = "ALTER TABLE book_issues ADD COLUMN renewed_count INT DEFAULT 0";
                $pdo->exec($alterTableQuery);
                
                // Also add renewal_date column if needed
                $alterTableQuery = "ALTER TABLE book_issues ADD COLUMN renewal_date DATETIME NULL";
                $pdo->exec($alterTableQuery);
            }
        } catch (Exception $e) {
            // If checking column fails, assume it exists and continue
            error_log('Error checking columns: ' . $e->getMessage());
        }
        
        // Now check the renewed count
        $renewedCount = isset($issue['renewed_count']) ? (int)$issue['renewed_count'] : 0;
        
        // Check if already renewed multiple times (limit to 3 renewals)
        if ($renewedCount >= 3) {
            throw new Exception('Maximum number of renewals reached for this loan');
        }
        
        // Update due date
        $updateQuery = "
            UPDATE book_issues 
            SET 
                due_date = ?, 
                renewed_count = IFNULL(renewed_count, 0) + 1,
                renewal_date = NOW(),
                status = 'issued'
            WHERE issue_id = ?
        ";
        
        $stmt = $pdo->prepare($updateQuery);
        $stmt->execute([
            date('Y-m-d H:i:s', strtotime($newDueDate)),
            $issueId
        ]);
        
        // Commit transaction
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Loan renewed successfully. New due date: ' . date('Y-m-d', strtotime($newDueDate)),
            'data' => [
                'issue_id' => $issueId,
                'new_due_date' => date('Y-m-d', strtotime($newDueDate)),
                'renewed_count' => $renewedCount + 1
            ]
        ]);
        
    } catch (Exception $e) {
        // Only rollback if transaction was actually started
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * Get data for return receipt
 */
function getReceiptData($pdo) {
    if (!isset($_POST['issue_id'])) {
        throw new Exception('Issue ID is required');
    }
    
    $issueId = (int)$_POST['issue_id'];
    
    // Get issue details
    $query = "
        SELECT 
            i.*,
            b.title AS book_title,
            b.isbn,
            CONCAT(u.first_name, ' ', u.last_name) AS member_name,
            m.membership_number
        FROM book_issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN member_details m ON i.member_id = m.member_id
        JOIN users u ON m.user_id = u.user_id
        WHERE i.issue_id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$issueId]);
    $issue = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$issue) {
        throw new Exception('Issue record not found');
    }
    
    // Get fine details if any
    $fineQuery = "
        SELECT * FROM fines
        WHERE issue_id = ?
        ORDER BY fine_date DESC
        LIMIT 1
    ";
    
    $stmt = $pdo->prepare($fineQuery);
    $stmt->execute([$issueId]);
    $fine = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fine) {
        $issue['fine_amount'] = $fine['fine_amount'];
        $issue['fine_reason'] = $fine['fine_reason'];
        $issue['fine_paid'] = $fine['status'] === 'paid';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $issue
    ]);
}

/**
 * Export loans data as CSV
 */
function exportLoans($pdo) {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="loans_export_' . date('Y-m-d') . '.csv"');
    
    // Create a file pointer
    $output = fopen('php://output', 'w');
    
    // Add CSV header row
    fputcsv($output, [
        'Issue ID', 
        'Book Title', 
        'ISBN', 
        'Member Name', 
        'Membership Number',
        'Issue Date', 
        'Due Date', 
        'Return Date', 
        'Status',
        'Fine Amount'
    ]);
    
    // Get all loans with book and member details
    $query = "
        SELECT 
            i.issue_id,
            b.title AS book_title,
            b.isbn,
            CONCAT(u.first_name, ' ', u.last_name) AS member_name,
            m.membership_number,
            i.issue_date,
            i.due_date,
            i.return_date,
            i.status,
            COALESCE(f.fine_amount, 0) AS fine_amount
        FROM book_issues i
        JOIN books b ON i.book_id = b.book_id
        JOIN member_details m ON i.member_id = m.member_id
        JOIN users u ON m.user_id = u.user_id
        LEFT JOIN fines f ON i.issue_id = f.issue_id
        ORDER BY i.issue_date DESC
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    
    // Output each row of the data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['issue_id'],
            $row['book_title'],
            $row['isbn'],
            $row['member_name'],
            $row['membership_number'],
            $row['issue_date'],
            $row['due_date'],
            $row['return_date'] ?: 'Not Returned',
            strtoupper($row['status']),
            $row['fine_amount'] > 0 ? '$' . number_format($row['fine_amount'], 2) : '$0.00'
        ]);
    }
    
    // Close the file pointer
    fclose($output);
    exit; // Exit to prevent any additional output
}

/**
 * Helper function to update overdue status of loans
 */
function updateOverdueStatus($pdo) {
    $currentDate = date('Y-m-d H:i:s');
    
    $query = "
        UPDATE book_issues 
        SET status = 'overdue' 
        WHERE status = 'issued' AND due_date < ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$currentDate]);
}
?>