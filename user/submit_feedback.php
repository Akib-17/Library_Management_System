<?php

// Set content type before any output
header('Content-Type: application/json');

// Add CORS headers to handle cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Enable error reporting for debugging
ini_set('display_errors', 0); // Turn off display_errors for production
error_reporting(E_ALL);

// Start output buffering to catch any unexpected output
ob_start();

try {
    // Include database connection
    require_once '../db/db_connection.php';
    
    // Include the table creation script to ensure table exists
    // But don't allow it to output anything
    $tableExists = require_once '../setup/create_feedback_table.php';
    
    // Get JSON input
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    // Log the request for debugging
    file_put_contents('feedback_debug.log', date('Y-m-d H:i:s') . " Request: " . $rawInput . "\n", FILE_APPEND);
    
    // Validate input
    if (!isset($data['name']) || !isset($data['email']) || !isset($data['subject']) || !isset($data['message']) || !isset($data['type'])) {
        throw new Exception('Missing required fields');
    }
    
    // Sanitize input
    $name = htmlspecialchars(trim($data['name']));
    $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
    $subject = htmlspecialchars(trim($data['subject']));
    $message = htmlspecialchars(trim($data['message']));
    $type = in_array($data['type'], ['feedback', 'support']) ? $data['type'] : 'feedback';
    $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Please enter a valid email address');
    }
    
    // Insert feedback into database
    $query = "INSERT INTO feedback_tickets (user_id, name, email, subject, message, type) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$userId, $name, $email, $subject, $message, $type]);
    
    // Get the ticket ID
    $ticketId = $pdo->lastInsertId();
    
    // Clear any output that might have been generated
    ob_clean();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Thank you for your ' . ($type === 'feedback' ? 'feedback' : 'support request') . '! Your ticket number is #' . $ticketId . '.',
        'ticket_id' => $ticketId
    ]);
    
} catch (Exception $e) {
    // Log the error for debugging
    $errorMsg = date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n";
    file_put_contents('feedback_error.log', $errorMsg, FILE_APPEND);
    
    // Clear any output that might have been generated
    ob_clean();
    
    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>