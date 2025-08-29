<?php
// filepath: c:\xampp\htdocs\Library_management_system\Backend\tests\test_book_details.php

header('Content-Type: text/plain');

echo "==== BOOK DETAILS API UNIT TEST ====\n\n";

// Include the database connection
require_once '../db/db_connection.php';

// Test 1: Find a valid book ID from the database to test
echo "Test 1: Finding a valid book ID to test...\n";
try {
    $findBookQuery = "SELECT book_id FROM books ORDER BY RAND() LIMIT 1";
    $findBookStmt = $pdo->query($findBookQuery);
    $book = $findBookStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($book) {
        $validBookId = $book['book_id'];
        echo "✓ Found valid book ID: $validBookId\n\n";
    } else {
        echo "✗ No books found in database. Please add books first.\n";
        exit;
    }
} catch (Exception $e) {
    echo "✗ Error finding test book: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Test with valid book ID
echo "Test 2: Testing with valid book ID...\n";
$url = "http://localhost/Library_management_system/Backend/user/get_book_details.php?book_id=$validBookId";
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data['success']) {
    echo "✓ Successfully retrieved book details\n";
    echo "  - Title: {$data['book']['title']}\n";
    echo "  - Author: {$data['book']['author']}\n";
    echo "  - Category: {$data['book']['category_name']}\n\n";
} else {
    echo "✗ Failed to retrieve book details: {$data['message']}\n\n";
}

// Test 3: Missing book ID parameter (should fail)
echo "Test 3: Testing without book ID parameter (should fail)...\n";
$url = "http://localhost/Library_management_system/Backend/user/get_book_details.php";
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!$data['success'] && strpos($data['message'], 'required') !== false) {
    echo "✓ Correctly handled missing book ID parameter\n";
    echo "  - Error message: {$data['message']}\n\n";
} else {
    echo "✗ Failed test: API should require book ID parameter\n\n";
}

// Test 4: Invalid book ID (should fail)
echo "Test 4: Testing with invalid book ID (should fail)...\n";
$invalidBookId = 999999; // Assuming this ID doesn't exist
$url = "http://localhost/Library_management_system/Backend/user/get_book_details.php?book_id=$invalidBookId";
$response = file_get_contents($url);
$data = json_decode($response, true);

if (!$data['success'] && strpos($data['message'], 'not found') !== false) {
    echo "✓ Correctly handled invalid book ID\n";
    echo "  - Error message: {$data['message']}\n\n";
} else {
    echo "✗ Failed test: API should reject invalid book IDs\n\n";
}

// Test 5: DELIBERATELY BROKEN - Testing with non-integer book ID
echo "Test 5: Testing with non-integer book ID (deliberately broken)...\n";
$nonIntegerBookId = "abc"; // Non-integer value
$url = "http://localhost/Library_management_system/Backend/user/get_book_details.php?book_id=$nonIntegerBookId";
$response = file_get_contents($url);
$data = json_decode($response, true);

// This test is deliberately broken - the API actually casts non-integer values to 0
// and then rejects them as missing book ID, but our test expects it to
// specifically identify non-integer inputs
if (!$data['success'] && strpos(strtolower($data['message']), 'must be a number') !== false) {
    echo "✓ Correctly identified non-integer book ID\n";
    echo "  - Error message: {$data['message']}\n\n";
} else {
    echo "✗ BROKEN TEST: API should reject non-integer book IDs with a specific message\n";
    echo "  - Actual response: " . json_encode($data) . "\n\n";
}

// Test 6: Test with user_id parameter
echo "Test 6: Testing with user_id parameter...\n";
// First, find a valid user ID
$findUserQuery = "SELECT user_id FROM users ORDER BY RAND() LIMIT 1";
$findUserStmt = $pdo->query($findUserQuery);
$user = $findUserStmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    $validUserId = $user['user_id'];
    $url = "http://localhost/Library_management_system/Backend/user/get_book_details.php?book_id=$validBookId&user_id=$validUserId";
    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if ($data['success']) {
        echo "✓ Successfully retrieved book details with user context\n";
        echo "  - Reservation status: " . ($data['reservation_status'] ?: "Not reserved") . "\n\n";
    } else {
        echo "✗ Failed to retrieve book details with user context: {$data['message']}\n\n";
    }
} else {
    echo "✗ No users found in database. Skipping user context test.\n\n";
}

echo "==== UNIT TEST COMPLETED ====\n";
echo "NOTE: Test 5 is deliberately broken to show how to fix it!\n";
?>