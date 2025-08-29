<?php


header('Content-Type: text/plain');

echo "==== FEEDBACK SYSTEM UNIT TEST ====\n\n";

// Include the database connection
require_once '../db/db_connection.php';

// Test 1: Create test feedback data
echo "Test 1: Creating test feedback data...\n";
$testData = [
    'name' => 'Test User',
    'email' => 'test@example.com',
    'subject' => 'Test Subject',
    'message' => 'This is a test message from the unit test.',
    'type' => 'feedback',
    'user_id' => null
];
echo "Test data created\n\n";

// Test 2: Insert test feedback into database
echo "Test 2: Inserting test feedback into database...\n";
try {
    $query = "INSERT INTO feedback_tickets (user_id, name, email, subject, message, type) 
              VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        $testData['user_id'], 
        $testData['name'], 
        $testData['email'], 
        $testData['subject'], 
        $testData['message'], 
        $testData['type']
    ]);
    
    $ticketId = $pdo->lastInsertId();
    echo "Feedback inserted successfully with ID: $ticketId\n\n";
} catch (Exception $e) {
    echo "Error inserting feedback: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 3: Retrieve the inserted feedback
echo "Test 3: Retrieving the inserted feedback...\n";
try {
    $query = "SELECT * FROM feedback_tickets WHERE ticket_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "✓ Feedback retrieved successfully\n";
        echo "  - Name: " . $result['name'] . "\n";
        echo "  - Email: " . $result['email'] . "\n";
        echo "  - Subject: " . $result['subject'] . "\n";
        echo "  - Type: " . $result['type'] . "\n";
        echo "  - Status: " . $result['status'] . "\n\n";
    } else {
        echo "Failed to retrieve feedback\n\n";
    }
} catch (Exception $e) {
    echo "Error retrieving feedback: " . $e->getMessage() . "\n\n";
}

// Test 4: Update the feedback status
echo "Test 4: Updating feedback status...\n";
try {
    $query = "UPDATE feedback_tickets SET status = 'in_progress' WHERE ticket_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId]);
    
    if ($stmt->rowCount() > 0) {
        echo "Feedback status updated successfully\n\n";
    } else {
        echo "Failed to update feedback status\n\n";
    }
} catch (Exception $e) {
    echo "Error updating feedback: " . $e->getMessage() . "\n\n";
}

// Test 5: Verify the status was updated
echo "Test 5: Verifying status update...\n";
try {
    $query = "SELECT status FROM feedback_tickets WHERE ticket_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['status'] === 'in_progress') {
        echo "Status was correctly updated to 'in_progress'\n\n";
    } else {
        echo "Status was not updated correctly\n\n";
    }
} catch (Exception $e) {
    echo "Error verifying status: " . $e->getMessage() . "\n\n";
}

// Test 6: Clean up (optional - comment out if you want to keep the test data)
echo "Test 6: Cleaning up test data...\n";
try {
    $query = "DELETE FROM feedback_tickets WHERE ticket_id = ?";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$ticketId]);
    
    if ($stmt->rowCount() > 0) {
        echo "Test data cleaned up successfully\n\n";
    } else {
        echo "Failed to clean up test data\n\n";
    }
} catch (Exception $e) {
    echo "Error cleaning up: " . $e->getMessage() . "\n\n";
}

echo "==== UNIT TEST COMPLETED ====\n";
?>