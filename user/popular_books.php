<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

// Include database connection
require_once '../db/db_connection.php';

try {
    // Get most popular books based on reservations
    $query = "
        SELECT 
            b.book_id,
            b.title,
            b.author,
            b.isbn,
            b.available_copies,
            b.cover_image,
            COUNT(r.reservation_id) AS reservation_count
        FROM 
            books b
            LEFT JOIN book_reservations r ON b.book_id = r.book_id
        GROUP BY 
            b.book_id
        ORDER BY 
            reservation_count DESC,
            b.added_date DESC
        LIMIT 4
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $popularBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If not enough popular books, add some recent books
    if (count($popularBooks) < 4) {
        $recentQuery = "
            SELECT 
                book_id,
                title,
                author,
                isbn,
                available_copies,
                cover_image
            FROM 
                books
            WHERE 
                book_id NOT IN (?)
            ORDER BY 
                added_date DESC
            LIMIT ?
        ";
        
        $excludeIds = array_column($popularBooks, 'book_id');
        $excludeIdsStr = !empty($excludeIds) ? implode(',', $excludeIds) : '0';
        
        $recentStmt = $pdo->prepare($recentQuery);
        $recentStmt->execute([$excludeIdsStr, 4 - count($popularBooks)]);
        $recentBooks = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $popularBooks = array_merge($popularBooks, $recentBooks);
    }
    
    echo json_encode([
        'success' => true,
        'books' => $popularBooks
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>