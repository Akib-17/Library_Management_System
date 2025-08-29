<?php
header('Content-Type: application/json');
session_start();

// Include database connection
require_once '../db/db_connection.php';

// Validate request
if (!isset($_GET['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit;
}

$userId = (int)$_GET['user_id'];

try {
    // First, get the member_id associated with this user
    $memberQuery = "SELECT member_id FROM member_details WHERE user_id = ?";
    $memberStmt = $pdo->prepare($memberQuery);
    $memberStmt->execute([$userId]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        // Create a member record if it doesn't exist
        $createMemberQuery = "INSERT INTO member_details (user_id, registration_date) 
                              VALUES (?, NOW())";
        $createMemberStmt = $pdo->prepare($createMemberQuery);
        $createMemberStmt->execute([$userId]);
        
        $memberId = $pdo->lastInsertId();
    } else {
        $memberId = $member['member_id'];
    }
    
    // Get all loans for this member
    $loansQuery = "
        SELECT 
            i.issue_id,
            i.book_id,
            i.issue_date,
            i.due_date,
            i.return_date,
            i.status,
            b.title,
            b.author,
            b.isbn,
            b.cover_image,
            f.fine_amount,
            f.status AS fine_status
        FROM 
            book_issues i
        JOIN 
            books b ON i.book_id = b.book_id
        LEFT JOIN 
            fines f ON i.issue_id = f.issue_id
        WHERE 
            i.member_id = ?
        ORDER BY 
            CASE 
                WHEN i.status = 'overdue' THEN 1
                WHEN i.status = 'issued' THEN 2
                ELSE 3
            END,
            i.issue_date DESC
    ";
    
    $loansStmt = $pdo->prepare($loansQuery);
    $loansStmt->execute([$memberId]);
    $loans = $loansStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Process each loan to add fine_paid flag
    foreach ($loans as &$loan) {
        $loan['fine_paid'] = ($loan['fine_status'] === 'paid');
    }
    
    echo json_encode([
        'success' => true,
        'loans' => $loans
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching borrowing history: ' . $e->getMessage()
    ]);
}
?>