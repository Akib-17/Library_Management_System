<?php

header('Content-Type: application/json');

// Include database connection
require_once '../db/db_connection.php';

try {
    // Get all categories
    $sql = "SELECT category_id, name FROM categories ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>