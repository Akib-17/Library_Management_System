<?php

// This file is included in other scripts, so no direct output should be made

// Use the existing database connection if available, otherwise create a new one
if (!isset($pdo)) {
    require_once __DIR__ . '/../db/db_connection.php';
}

try {
    // Check if the feedback_tickets table exists
    $tableExists = false;
    $tables = $pdo->query("SHOW TABLES LIKE 'feedback_tickets'")->fetchAll();
    if (count($tables) > 0) {
        $tableExists = true;
    }

    // Create the table if it doesn't exist
    if (!$tableExists) {
        $sql = "CREATE TABLE feedback_tickets (
            ticket_id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT(11) NULL,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('feedback', 'support') NOT NULL,
            status ENUM('pending', 'in_progress', 'resolved', 'closed') NOT NULL DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
            admin_response TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            assigned_to INT(11) NULL
        )";
        $pdo->exec($sql);
        
        // Don't output directly - use error_log instead
        error_log("Table 'feedback_tickets' created successfully.");
    }
} catch (PDOException $e) {
    error_log("Error creating feedback_tickets table: " . $e->getMessage());
    // Don't throw the exception here so it doesn't break the including script
}

// Return a value instead of outputting directly
return $tableExists;
?>