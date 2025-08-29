<?php
header('Content-Type: application/json');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

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
require_once '../auth/admin_auth_fun.php'; // For logging actions

// Helper function to sanitize input
if (!function_exists('sanitizeInput')) {
    // Helper function to sanitize input
    function sanitizeInput($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }
}

// Get the action from query string
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'add':
            // Add a new book
            addBook($pdo);
            break;
            
        case 'update':
            // Update existing book
            updateBook($pdo);
            break;
            
        case 'delete':
            // Delete a book
            deleteBook($pdo);
            break;
            
        case 'get':
            // Get book details
            getBook($pdo);
            break;
            
        case 'add_category':
            // Add a new category
            addCategory($pdo);
            break;
            
        case 'update_category':
            // Update existing category
            updateCategory($pdo);
            break;
            
        case 'delete_category':
            // Delete a category
            deleteCategory($pdo);
            break;
            
        case 'get_category':
            // Get category details
            getCategory($pdo);
            break;
            
        case 'update_copies':
            // Update book copies
            updateBookCopies($pdo);
            break;
            
        case 'update_location':
            // Update book shelf location
            updateBookLocation($pdo);
            break;
            
        default:
            // Invalid action
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action specified'
            ]);
            exit;
    }
} catch (Exception $e) {
    // Log error and return error message
    error_log('Book management error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
    exit;
}

// Function to add a new book
function addBook($pdo) {
    // Get POST data
    $isbn = sanitizeInput($_POST['isbn']);
    $title = sanitizeInput($_POST['title']);
    $author = sanitizeInput($_POST['author']);
    $publisher = isset($_POST['publisher']) ? sanitizeInput($_POST['publisher']) : null;
    $publicationYear = isset($_POST['publication_year']) ? (int)$_POST['publication_year'] : null;
    $categoryId = (int)$_POST['category_id'];
    $edition = isset($_POST['edition']) ? sanitizeInput($_POST['edition']) : null;
    $totalCopies = isset($_POST['total_copies']) ? (int)$_POST['total_copies'] : 1;
    $shelfLocation = isset($_POST['shelf_location']) ? sanitizeInput($_POST['shelf_location']) : null;
    $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : null;
    $coverImage = isset($_POST['cover_image']) ? sanitizeInput($_POST['cover_image']) : null;
    
    // Initial available copies = total copies for new books
    $availableCopies = $totalCopies;
    
    // Check if ISBN already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE isbn = ?");
    $stmt->execute([$isbn]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('A book with this ISBN already exists');
    }
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('Selected category does not exist');
    }
    
    // Insert book into database
    $stmt = $pdo->prepare("
        INSERT INTO books (isbn, title, author, publisher, publication_year, category_id, 
                          edition, total_copies, available_copies, shelf_location, description, cover_image)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $isbn, $title, $author, $publisher, $publicationYear, $categoryId,
        $edition, $totalCopies, $availableCopies, $shelfLocation, $description, $coverImage
    ]);
    
    $bookId = $pdo->lastInsertId();
    
    // Log the action
    logAction($_SESSION['user_id'], 'add_book', 'books', $bookId, 
              $_SERVER['REMOTE_ADDR'], "Added book: $title (ISBN: $isbn)", $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Book added successfully',
        'book_id' => $bookId
    ]);
}

// Function to update an existing book
function updateBook($pdo) {
    // Get book ID from POST data
    $bookId = (int)$_POST['book_id'];
    
    // Check if book exists
    $stmt = $pdo->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    // Get updated values
    $isbn = sanitizeInput($_POST['isbn']);
    $title = sanitizeInput($_POST['title']);
    $author = sanitizeInput($_POST['author']);
    $publisher = isset($_POST['publisher']) ? sanitizeInput($_POST['publisher']) : null;
    $publicationYear = isset($_POST['publication_year']) ? (int)$_POST['publication_year'] : null;
    $categoryId = (int)$_POST['category_id'];
    $edition = isset($_POST['edition']) ? sanitizeInput($_POST['edition']) : null;
    $totalCopies = isset($_POST['total_copies']) ? (int)$_POST['total_copies'] : 1;
    $shelfLocation = isset($_POST['shelf_location']) ? sanitizeInput($_POST['shelf_location']) : null;
    $description = isset($_POST['description']) ? sanitizeInput($_POST['description']) : null;
    $coverImage = isset($_POST['cover_image']) ? sanitizeInput($_POST['cover_image']) : $book['cover_image'];
    
    // Check if ISBN already exists for another book
    if ($isbn !== $book['isbn']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE isbn = ? AND book_id != ?");
        $stmt->execute([$isbn, $bookId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Another book with this ISBN already exists');
        }
    }
    
    // Ensure available copies don't exceed total copies
    $availableCopies = $book['available_copies'];
    if ($totalCopies < $availableCopies) {
        $availableCopies = $totalCopies;
    }
    
    // Update book in database
    $stmt = $pdo->prepare("
        UPDATE books SET 
            isbn = ?, 
            title = ?, 
            author = ?, 
            publisher = ?, 
            publication_year = ?, 
            category_id = ?,
            edition = ?, 
            total_copies = ?, 
            available_copies = ?, 
            shelf_location = ?, 
            description = ?, 
            cover_image = ?
        WHERE book_id = ?
    ");
    
    $stmt->execute([
        $isbn, $title, $author, $publisher, $publicationYear, $categoryId,
        $edition, $totalCopies, $availableCopies, $shelfLocation, $description, $coverImage,
        $bookId
    ]);
    
    // Log the action
    logAction($_SESSION['user_id'], 'update_book', 'books', $bookId, 
              $_SERVER['REMOTE_ADDR'], "Updated book: $title (ISBN: $isbn)", $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Book updated successfully'
    ]);
}

// Function to delete a book
function deleteBook($pdo) {
    // Get book ID from query string
    $bookId = (int)$_GET['id'];
    
    // Check if book exists
    $stmt = $pdo->prepare("SELECT title, isbn FROM books WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    // Check if book has any reservations
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM book_reservations WHERE book_id = ? AND status != 'cancelled'");
    $stmt->execute([$bookId]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Cannot delete book: there are active reservations for this book');
    }
    
    // Delete book from database
    $stmt = $pdo->prepare("DELETE FROM books WHERE book_id = ?");
    $stmt->execute([$bookId]);
    
    // Log the action
    logAction($_SESSION['user_id'], 'delete_book', 'books', $bookId, 
              $_SERVER['REMOTE_ADDR'], "Deleted book: {$book['title']} (ISBN: {$book['isbn']})", $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Book deleted successfully'
    ]);
}

// Function to get book details
function getBook($pdo) {
    // Get book ID from query string
    $bookId = (int)$_GET['id'];
    
    // Get book details
    $stmt = $pdo->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    // Return book details
    echo json_encode([
        'success' => true,
        'data' => $book
    ]);
}

// Function to add a new category
function addCategory($pdo) {
    // Get POST data
    $name = sanitizeInput($_POST['category_name']);
    $description = isset($_POST['category_description']) ? sanitizeInput($_POST['category_description']) : null;
    $parentCategoryId = isset($_POST['parent_category']) && !empty($_POST['parent_category']) ? 
                        (int)$_POST['parent_category'] : null;
    
    // Check if category name already exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('A category with this name already exists');
    }
    
    // Check if parent category exists
    if ($parentCategoryId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
        $stmt->execute([$parentCategoryId]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Selected parent category does not exist');
        }
    }
    
    // Insert category into database
    $stmt = $pdo->prepare("
        INSERT INTO categories (name, description, parent_category_id)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([$name, $description, $parentCategoryId]);
    
    $categoryId = $pdo->lastInsertId();
    
    // Log the action
    logAction($_SESSION['user_id'], 'add_category', 'categories', $categoryId, 
              $_SERVER['REMOTE_ADDR'], "Added category: $name", $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Category added successfully',
        'category_id' => $categoryId
    ]);
}

// Function to update an existing category
function updateCategory($pdo) {
    // Get category ID from POST data
    $categoryId = (int)$_POST['category_id'];
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception('Category not found');
    }
    
    // Get updated values
    $name = sanitizeInput($_POST['category_name']);
    $description = isset($_POST['category_description']) ? sanitizeInput($_POST['category_description']) : null;
    $parentCategoryId = isset($_POST['parent_category']) && !empty($_POST['parent_category']) ? 
                        (int)$_POST['parent_category'] : null;
    
    // Check if category name already exists for another category
    if ($name !== $category['name']) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ? AND category_id != ?");
        $stmt->execute([$name, $categoryId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Another category with this name already exists');
        }
    }
    
    // Prevent circular parent reference
    if ($parentCategoryId == $categoryId) {
        throw new Exception('A category cannot be its own parent');
    }
    
    // Check if parent category exists
    if ($parentCategoryId) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
        $stmt->execute([$parentCategoryId]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Selected parent category does not exist');
        }
    }
    
    // Update category in database
    $stmt = $pdo->prepare("
        UPDATE categories SET 
            name = ?, 
            description = ?, 
            parent_category_id = ?
        WHERE category_id = ?
    ");
    
    $stmt->execute([$name, $description, $parentCategoryId, $categoryId]);
    
    // Log the action
    logAction($_SESSION['user_id'], 'update_category', 'categories', $categoryId, 
              $_SERVER['REMOTE_ADDR'], "Updated category: $name", $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Category updated successfully'
    ]);
}

// Function to delete a category
function deleteCategory($pdo) {
    // Get category ID from query string
    $categoryId = (int)$_GET['id'];
    
    // Check if category exists
    $stmt = $pdo->prepare("SELECT name FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception('Category not found');
    }
    
    // Check if category has books
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM books WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Cannot delete category: there are books in this category');
    }
    
    // Check if category has child categories
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE parent_category_id = ?");
    $stmt->execute([$categoryId]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('Cannot delete category: it has child categories');
    }
    
    // Delete category from database
    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    
    // Log the action
    logAction($_SESSION['user_id'], 'delete_category', 'categories', $categoryId, 
              $_SERVER['REMOTE_ADDR'], "Deleted category: {$category['name']}", $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Category deleted successfully'
    ]);
}

// Function to get category details
function getCategory($pdo) {
    // Get category ID from query string
    $categoryId = (int)$_GET['id'];
    
    // Get category details
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        throw new Exception('Category not found');
    }
    
    // Return category details
    echo json_encode([
        'success' => true,
        'data' => $category
    ]);
}

// Function to update book copies
function updateBookCopies($pdo) {
    // Get POST data
    $bookId = (int)$_POST['book_id'];
    $totalCopies = (int)$_POST['total_copies'];
    $availableCopies = (int)$_POST['available_copies'];
    
    // Validate input
    if ($totalCopies < 1) {
        throw new Exception('Total copies must be at least 1');
    }
    
    if ($availableCopies < 0) {
        throw new Exception('Available copies cannot be negative');
    }
    
    if ($availableCopies > $totalCopies) {
        throw new Exception('Available copies cannot exceed total copies');
    }
    
    // Check if book exists
    $stmt = $pdo->prepare("SELECT title, isbn FROM books WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    // Update book copies in database
    $stmt = $pdo->prepare("
        UPDATE books SET 
            total_copies = ?, 
            available_copies = ?
        WHERE book_id = ?
    ");
    
    $stmt->execute([$totalCopies, $availableCopies, $bookId]);
    
    // Log the action
    logAction($_SESSION['user_id'], 'update_book_copies', 'books', $bookId, 
              $_SERVER['REMOTE_ADDR'], 
              "Updated copies for book: {$book['title']} (ISBN: {$book['isbn']}) - Total: $totalCopies, Available: $availableCopies", 
              $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Book copies updated successfully'
    ]);
}

// Function to update book shelf location
function updateBookLocation($pdo) {
    // Get POST data
    $bookId = (int)$_POST['book_id'];
    $shelfLocation = sanitizeInput($_POST['shelf_location']);
    
    // Check if book exists
    $stmt = $pdo->prepare("SELECT title, isbn FROM books WHERE book_id = ?");
    $stmt->execute([$bookId]);
    $book = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$book) {
        throw new Exception('Book not found');
    }
    
    // Update book shelf location in database
    $stmt = $pdo->prepare("
        UPDATE books SET 
            shelf_location = ?
        WHERE book_id = ?
    ");
    
    $stmt->execute([$shelfLocation, $bookId]);
    
    // Log the action
    logAction($_SESSION['user_id'], 'update_book_location', 'books', $bookId, 
              $_SERVER['REMOTE_ADDR'], 
              "Updated shelf location for book: {$book['title']} (ISBN: {$book['isbn']}) - Location: $shelfLocation", 
              $pdo);
    
    echo json_encode([
        'success' => true,
        'message' => 'Book shelf location updated successfully'
    ]);
}