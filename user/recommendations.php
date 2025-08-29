<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Include database connection
require_once '../db/db_connection.php';

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

if (!$userId) {
    echo json_encode([
        'success' => false,
        'message' => 'User ID is required'
    ]);
    exit;
}

try {
    // Find the user's member_id from the users table
    $userQuery = "SELECT m.member_id FROM users u JOIN member_details m ON u.user_id = m.user_id WHERE u.user_id = ?";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([$userId]);
    $member = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$member) {
        throw new Exception('User not found or not a member');
    }
    
    $memberId = $member['member_id'];
    
    // Find the most reserved category by this member
    $categoryQuery = "
        SELECT 
            b.category_id,
            c.name AS category_name,
            COUNT(*) AS reservation_count
        FROM 
            book_reservations r
            JOIN books b ON r.book_id = b.book_id
            JOIN categories c ON b.category_id = c.category_id
        WHERE 
            r.member_id = ?
        GROUP BY 
            b.category_id
        ORDER BY 
            reservation_count DESC
        LIMIT 1
    ";
    
    $categoryStmt = $pdo->prepare($categoryQuery);
    $categoryStmt->execute([$memberId]);
    $preferredCategory = $categoryStmt->fetch(PDO::FETCH_ASSOC);
    
    // If the user has no reservations, use the most popular category
    if (!$preferredCategory) {
        $popularCategoryQuery = "
            SELECT 
                b.category_id,
                c.name AS category_name,
                COUNT(*) AS reservation_count
            FROM 
                book_reservations r
                JOIN books b ON r.book_id = b.book_id
                JOIN categories c ON b.category_id = c.category_id
            GROUP BY 
                b.category_id
            ORDER BY 
                reservation_count DESC
            LIMIT 1
        ";
        
        $popularCategoryStmt = $pdo->prepare($popularCategoryQuery);
        $popularCategoryStmt->execute();
        $preferredCategory = $popularCategoryStmt->fetch(PDO::FETCH_ASSOC);
        
        // If still no category found, just get the first category
        if (!$preferredCategory) {
            $firstCategoryQuery = "SELECT category_id, name AS category_name FROM categories LIMIT 1";
            $firstCategoryStmt = $pdo->prepare($firstCategoryQuery);
            $firstCategoryStmt->execute();
            $preferredCategory = $firstCategoryStmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    // Get books from the preferred category that the user hasn't reserved before
    $booksQuery = "
        SELECT 
            b.book_id,
            b.title,
            b.author,
            b.isbn,
            b.available_copies,
            b.cover_image
        FROM 
            books b
        WHERE 
            b.category_id = ? AND
            b.book_id NOT IN (
                SELECT book_id FROM book_reservations WHERE member_id = ?
            )
        LIMIT 4
    ";
    
    $booksStmt = $pdo->prepare($booksQuery);
    $booksStmt->execute([$preferredCategory['category_id'], $memberId]);
    $recommendedBooks = $booksStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If not enough books found, just get the newest books from the category
    if (count($recommendedBooks) < 4) {
        $additionalBooksQuery = "
            SELECT 
                b.book_id,
                b.title,
                b.author,
                b.isbn,
                b.available_copies,
                b.cover_image
            FROM 
                books b
            WHERE 
                b.category_id = ? AND
                b.book_id NOT IN (?)
            ORDER BY 
                b.added_date DESC
            LIMIT ?
        ";
        
        $excludeIds = array_column($recommendedBooks, 'book_id');
        $excludeIdsStr = !empty($excludeIds) ? implode(',', $excludeIds) : '0';
        
        $additionalBooksStmt = $pdo->prepare($additionalBooksQuery);
        $additionalBooksStmt->execute([
            $preferredCategory['category_id'], 
            $excludeIdsStr,
            4 - count($recommendedBooks)
        ]);
        
        $additionalBooks = $additionalBooksStmt->fetchAll(PDO::FETCH_ASSOC);
        $recommendedBooks = array_merge($recommendedBooks, $additionalBooks);
    }
    
    echo json_encode([
        'success' => true,
        'category' => $preferredCategory['category_name'],
        'books' => $recommendedBooks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>