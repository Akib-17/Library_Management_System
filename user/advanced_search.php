<?php

header('Content-Type: application/json');

// Include database connection
require_once '../db/db_connection.php';

try {
    // Get search parameters
    $query = isset($_GET['query']) ? trim($_GET['query']) : null;
    $title = isset($_GET['title']) ? trim($_GET['title']) : null;
    $author = isset($_GET['author']) ? trim($_GET['author']) : null;
    $category = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $year = isset($_GET['year']) ? (int)$_GET['year'] : null;
    $isbn = isset($_GET['isbn']) ? trim($_GET['isbn']) : null;
    $availability = isset($_GET['availability']) ? $_GET['availability'] : 'all';

    // Base SQL query
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.category_id
            WHERE 1=1";
    
    $params = [];
    
    // Add conditions based on search parameters
    if ($query) {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR c.name LIKE ?)";
        $searchTerm = "%$query%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    if ($title) {
        $sql .= " AND b.title LIKE ?";
        $params[] = "%$title%";
    }
    
    if ($author) {
        $sql .= " AND b.author LIKE ?";
        $params[] = "%$author%";
    }
    
    if ($category) {
        $sql .= " AND b.category_id = ?";
        $params[] = $category;
    }
    
    if ($year) {
        $sql .= " AND b.publication_year = ?";
        $params[] = $year;
    }
    
    if ($isbn) {
        $sql .= " AND b.isbn LIKE ?";
        $params[] = "%$isbn%";
    }
    
    if ($availability === 'available') {
        $sql .= " AND b.available_copies > 0";
    }
    
    // Order by
    $sql .= " ORDER BY b.title ASC";
    
    // Prepare and execute query
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'books' => $books,
        'count' => count($books)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during search: ' . $e->getMessage()
    ]);
}
?>