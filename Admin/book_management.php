
<?php
session_start();

// Check if a session user exists and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: /library_management_system/Frontend/Admin/login.html');
    exit;
}

// Include database connection
require_once '../../Backend/db/db_connection.php';

// Check if categories table is empty and initialize with default categories
$checkCategoriesQuery = "SELECT COUNT(*) as count FROM categories";
$checkCategoriesStmt = $pdo->prepare($checkCategoriesQuery);
$checkCategoriesStmt->execute();
$categoryCount = $checkCategoriesStmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($categoryCount == 0) {
    // Default categories to initialize
    $defaultCategories = [
        ['name' => 'Fiction', 'description' => 'Fictional literature and novels'],
        ['name' => 'Non-Fiction', 'description' => 'Factual and informative books'],
        ['name' => 'Science', 'description' => 'Books related to scientific topics'],
        ['name' => 'History', 'description' => 'Historical books and documents'],
        ['name' => 'Biography', 'description' => 'Life stories and autobiographies'],
        ['name' => 'Technology', 'description' => 'Books about technology and computing'],
        ['name' => 'Philosophy', 'description' => 'Philosophical works and theories'],
        ['name' => 'Arts', 'description' => 'Books about various art forms'],
        ['name' => 'Reference', 'description' => 'Reference materials and guides'],
        ['name' => 'Children', 'description' => 'Books for children and young readers']
    ];
    
    try {
        // Begin transaction to ensure all categories are added
        $pdo->beginTransaction();
        
        $insertCategoryQuery = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $insertCategoryStmt = $pdo->prepare($insertCategoryQuery);
        
        foreach ($defaultCategories as $category) {
            $insertCategoryStmt->execute([$category['name'], $category['description']]);
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        // Log the error but continue - we don't want to block the page loading
        error_log("Error initializing default categories: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="../assets/css/admin-style.css">
    <style>
        .nav-tabs .nav-link {
            cursor: pointer;
        }
        .book-cover {
            max-width: 50px;
            max-height: 70px;
            object-fit: cover;
        }
        .status-available {
            color: green;
            font-weight: bold;
        }
        .status-unavailable {
            color: red;
            font-weight: bold;
        }
        #bulk-upload-form .form-text {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .template-link {
            color: #0d6efd;
            text-decoration: underline;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar removed -->
            
            <main class="col-12 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Book Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#addBookModal">
                            <i class="bi bi-plus-circle"></i> Add New Book
                        </button>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#bulkUploadModal">
                            <i class="bi bi-cloud-upload"></i> Bulk Upload
                        </button>
                    </div>
                </div>

                <!-- Tabs for different book management functions -->
                <ul class="nav nav-tabs mb-3" id="bookTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-books-tab" data-bs-toggle="tab" data-bs-target="#all-books" type="button">All Books</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button">Categories</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="availability-tab" data-bs-toggle="tab" data-bs-target="#availability" type="button">Availability</button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="bookTabsContent">
                    <!-- All Books Tab -->
                    <div class="tab-pane fade show active" id="all-books" role="tabpanel">
                        <div class="table-responsive">
                            <table id="books-table" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Cover</th>
                                        <th>ISBN</th>
                                        <th>Title</th>
                                        <th>Author</th>
                                        <th>Category</th>
                                        <th>Available</th>
                                        <th>Total</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Fetch books with category names
                                    $query = "SELECT b.*, c.name as category_name 
                                             FROM books b 
                                             LEFT JOIN categories c ON b.category_id = c.category_id 
                                             ORDER BY b.title";
                                    $stmt = $pdo->prepare($query);
                                    $stmt->execute();
                                    
                                    while ($book = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                        // Use the cover_image URL directly, with a default if not provided
                                        $coverImage = !empty($book['cover_image']) ? $book['cover_image'] : 'https://via.placeholder.com/50x70?text=No+Cover';
                                        $availability = $book['available_copies'] > 0 ? 
                                            '<span class="status-available">Available</span>' : 
                                            '<span class="status-unavailable">Unavailable</span>';
                                        
                                        echo '<tr>
                                            <td><img src="' . $coverImage . '" class="book-cover" alt="Book Cover"></td>
                                            <td>' . htmlspecialchars($book['isbn']) . '</td>
                                            <td>' . htmlspecialchars($book['title']) . '</td>
                                            <td>' . htmlspecialchars($book['author']) . '</td>
                                            <td>' . htmlspecialchars($book['category_name']) . '</td>
                                            <td>' . $book['available_copies'] . '</td>
                                            <td>' . $book['total_copies'] . '</td>
                                            <td>
                                                <button class="btn btn-sm btn-primary edit-book" data-id="' . $book['book_id'] . '">Edit</button>
                                                <button class="btn btn-sm btn-danger delete-book" data-id="' . $book['book_id'] . '">Delete</button>
                                            </td>
                                        </tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Categories Tab -->
                    <div class="tab-pane fade" id="categories" role="tabpanel">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Add/Edit Category</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="category-form">
                                            <input type="hidden" id="category_id" name="category_id" value="">
                                            <div class="mb-3">
                                                <label for="category_name" class="form-label">Category Name</label>
                                                <input type="text" class="form-control" id="category_name" name="category_name" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="category_description" class="form-label">Description</label>
                                                <textarea class="form-control" id="category_description" name="category_description" rows="3"></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label for="parent_category" class="form-label">Parent Category (Optional)</label>
                                                <select class="form-select" id="parent_category" name="parent_category">
                                                    <option value="">None</option>
                                                    <?php
                                                    // Fetch categories for dropdown
                                                    $categoryQuery = "SELECT category_id, name FROM categories ORDER BY name";
                                                    $categoryStmt = $pdo->prepare($categoryQuery);
                                                    $categoryStmt->execute();
                                                    
                                                    while ($category = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
                                                        echo '<option value="' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Save Category</button>
                                            <button type="reset" class="btn btn-secondary" id="reset-category-form">Reset</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Categories List</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table id="categories-table" class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Category Name</th>
                                                        <th>Parent Category</th>
                                                        <th>Book Count</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php
                                                    // Fetch categories with parent names and book counts
                                                    $query = "SELECT c.category_id, c.name, c.parent_category_id, 
                                                             p.name as parent_name, COUNT(b.book_id) as book_count 
                                                             FROM categories c 
                                                             LEFT JOIN categories p ON c.parent_category_id = p.category_id 
                                                             LEFT JOIN books b ON c.category_id = b.category_id 
                                                             GROUP BY c.category_id 
                                                             ORDER BY c.name";
                                                    $stmt = $pdo->prepare($query);
                                                    $stmt->execute();
                                                    
                                                    while ($category = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                        $parentName = $category['parent_name'] ? htmlspecialchars($category['parent_name']) : 'None';
                                                        
                                                        echo '<tr>
                                                            <td>' . $category['category_id'] . '</td>
                                                            <td>' . htmlspecialchars($category['name']) . '</td>
                                                            <td>' . $parentName . '</td>
                                                            <td>' . $category['book_count'] . '</td>
                                                            <td>
                                                                <button class="btn btn-sm btn-primary edit-category" data-id="' . $category['category_id'] . '">Edit</button>
                                                                <button class="btn btn-sm btn-danger delete-category" data-id="' . $category['category_id'] . '" ' . ($category['book_count'] > 0 ? 'disabled' : '') . '>Delete</button>
                                                            </td>
                                                        </tr>';
                                                    }
                                                    ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Availability Tab -->
                    <div class="tab-pane fade" id="availability" role="tabpanel">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Book Availability Management</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table id="availability-table" class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ISBN</th>
                                                <th>Title</th>
                                                <th>Available Copies</th>
                                                <th>Total Copies</th>
                                                <th>Shelf Location</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Fetch books for availability management
                                            $query = "SELECT book_id, isbn, title, available_copies, total_copies, shelf_location FROM books ORDER BY title";
                                            $stmt = $pdo->prepare($query);
                                            $stmt->execute();
                                            
                                            while ($book = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                                echo '<tr>
                                                    <td>' . htmlspecialchars($book['isbn']) . '</td>
                                                    <td>' . htmlspecialchars($book['title']) . '</td>
                                                    <td>' . $book['available_copies'] . '</td>
                                                    <td>' . $book['total_copies'] . '</td>
                                                    <td>' . htmlspecialchars($book['shelf_location']) . '</td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary update-copies" data-id="' . $book['book_id'] . '">Update Copies</button>
                                                        <button class="btn btn-sm btn-secondary update-location" data-id="' . $book['book_id'] . '">Change Location</button>
                                                    </td>
                                                </tr>';
                                            }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add/Edit Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1" aria-labelledby="addBookModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addBookModalLabel">Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="book-form" enctype="multipart/form-data">
                        <input type="hidden" id="book_id" name="book_id" value="">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="isbn" class="form-label">ISBN</label>
                                    <input type="text" class="form-control" id="isbn" name="isbn" required>
                                </div>
                                <div class="mb-3">
                                    <label for="title" class="form-label">Title</label>
                                    <input type="text" class="form-control" id="title" name="title" required>
                                </div>
                                <div class="mb-3">
                                    <label for="author" class="form-label">Author</label>
                                    <input type="text" class="form-control" id="author" name="author" required>
                                </div>
                                <div class="mb-3">
                                    <label for="publisher" class="form-label">Publisher</label>
                                    <input type="text" class="form-control" id="publisher" name="publisher">
                                </div>
                                <div class="mb-3">
                                    <label for="publication_year" class="form-label">Publication Year</label>
                                    <input type="number" class="form-control" id="publication_year" name="publication_year" min="1800" max="<?= date('Y') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php
                                        // Fetch categories for dropdown
                                        $categoryQuery = "SELECT category_id, name FROM categories ORDER BY name";
                                        $categoryStmt = $pdo->prepare($categoryQuery);
                                        $categoryStmt->execute();
                                        
                                        while ($category = $categoryStmt->fetch(PDO::FETCH_ASSOC)) {
                                            echo '<option value="' . $category['category_id'] . '">' . htmlspecialchars($category['name']) . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="edition" class="form-label">Edition</label>
                                    <input type="text" class="form-control" id="edition" name="edition">
                                </div>
                                <div class="mb-3">
                                    <label for="total_copies" class="form-label">Total Copies</label>
                                    <input type="number" class="form-control" id="total_copies" name="total_copies" min="1" value="1">
                                </div>
                                <div class="mb-3">
                                    <label for="shelf_location" class="form-label">Shelf Location</label>
                                    <input type="text" class="form-control" id="shelf_location" name="shelf_location">
                                </div>
                                <div class="mb-3">
                                    <label for="cover_image" class="form-label">Cover Image URL</label>
                                    <input type="url" class="form-control" id="cover_image" name="cover_image" placeholder="https://example.com/book-cover.jpg">
                                    <small class="form-text text-muted">Enter a direct URL to the book cover image</small>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-book">Save Book</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Upload Modal -->
    <div class="modal fade" id="bulkUploadModal" tabindex="-1" aria-labelledby="bulkUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkUploadModalLabel">Bulk Upload Books</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="bulk-upload-form" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">Upload a CSV file with book information.</div>
                        </div>
                        <div class="mb-3">
                            <p>CSV Format: <span class="template-link" id="download-template">Download Template</span></p>
                            <p>Required columns: ISBN, Title, Author, Category ID</p>
                            <p>Optional columns: Publisher, Publication Year, Edition, Total Copies, Shelf Location, Description</p>
                        </div>
                    </form>
                    <div class="progress mb-3 d-none" id="upload-progress-container">
                        <div class="progress-bar" id="upload-progress" role="progressbar" style="width: 0%"></div>
                    </div>
                    <div id="upload-result" class="alert d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="start-bulk-upload">Upload</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Copies Modal -->
    <div class="modal fade" id="updateCopiesModal" tabindex="-1" aria-labelledby="updateCopiesModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateCopiesModalLabel">Update Book Copies</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="update-copies-form">
                        <input type="hidden" id="update_book_id" name="update_book_id">
                        <div class="mb-3">
                            <label for="book_title" class="form-label">Book Title</label>
                            <input type="text" class="form-control" id="book_title" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="update_total_copies" class="form-label">Total Copies</label>
                            <input type="number" class="form-control" id="update_total_copies" name="update_total_copies" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="update_available_copies" class="form-label">Available Copies</label>
                            <input type="number" class="form-control" id="update_available_copies" name="update_available_copies" min="0" required>
                            <div class="form-text text-warning">Note: Available copies should not exceed total copies.</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-copies">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Location Modal -->
    <div class="modal fade" id="updateLocationModal" tabindex="-1" aria-labelledby="updateLocationModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateLocationModalLabel">Update Shelf Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="update-location-form">
                        <input type="hidden" id="location_book_id" name="location_book_id">
                        <div class="mb-3">
                            <label for="location_book_title" class="form-label">Book Title</label>
                            <input type="text" class="form-control" id="location_book_title" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="update_shelf_location" class="form-label">Shelf Location</label>
                            <input type="text" class="form-control" id="update_shelf_location" name="update_shelf_location" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="save-location">Save Location</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Required JavaScript -->
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTables
            $('#books-table').DataTable({
                responsive: true,
                lengthChange: true,
                pageLength: 10,
                order: [[2, 'asc']] // Sort by title by default
            });
            
            $('#categories-table').DataTable({
                responsive: true,
                lengthChange: false,
                pageLength: 10,
                order: [[1, 'asc']] // Sort by name by default
            });
            
            $('#availability-table').DataTable({
                responsive: true,
                lengthChange: true,
                pageLength: 10,
                order: [[1, 'asc']] // Sort by title by default
            });
            
            // Handle add/edit book form submission
            $('#save-book').click(function() {
                const formData = new FormData(document.getElementById('book-form'));
                const bookId = $('#book_id').val();
                const action = bookId ? 'update' : 'add';
                
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=' + action,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#addBookModal').modal('hide');
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle edit book button click
            $(document).on('click', '.edit-book', function() {
                const bookId = $(this).data('id');
                
                // Reset form
                $('#book-form')[0].reset();
                $('#book_id').val(bookId);
                $('#addBookModalLabel').text('Edit Book');
                
                // Fetch book details
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=get&id=' + bookId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            const book = response.data;
                            $('#isbn').val(book.isbn);
                            $('#title').val(book.title);
                            $('#author').val(book.author);
                            $('#publisher').val(book.publisher);
                            $('#publication_year').val(book.publication_year);
                            $('#category_id').val(book.category_id);
                            $('#edition').val(book.edition);
                            $('#total_copies').val(book.total_copies);
                            $('#shelf_location').val(book.shelf_location);
                            $('#description').val(book.description);
                            
                            $('#addBookModal').modal('show');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle delete book button click
            $(document).on('click', '.delete-book', function() {
                if (confirm('Are you sure you want to delete this book? This action cannot be undone.')) {
                    const bookId = $(this).data('id');
                    
                    $.ajax({
                        url: '../../Backend/admin/books/book_handler.php?action=delete&id=' + bookId,
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                window.location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr) {
                            alert('Error: ' + xhr.responseText);
                        }
                    });
                }
            });
            
            // Handle category form submission
            $('#category-form').submit(function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                const categoryId = $('#category_id').val();
                const action = categoryId ? 'update_category' : 'add_category';
                
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=' + action,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#category-form')[0].reset();
                            $('#category_id').val('');
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle edit category button click
            $(document).on('click', '.edit-category', function() {
                const categoryId = $(this).data('id');
                
                // Reset form
                $('#category-form')[0].reset();
                $('#category_id').val(categoryId);
                
                // Fetch category details
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=get_category&id=' + categoryId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            const category = response.data;
                            $('#category_name').val(category.name);
                            $('#category_description').val(category.description);
                            $('#parent_category').val(category.parent_category_id || '');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle delete category button click
            $(document).on('click', '.delete-category', function() {
                if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
                    const categoryId = $(this).data('id');
                    
                    $.ajax({
                        url: '../../Backend/admin/books/book_handler.php?action=delete_category&id=' + categoryId,
                        type: 'GET',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                window.location.reload();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function(xhr) {
                            alert('Error: ' + xhr.responseText);
                        }
                    });
                }
            });
            
            // Handle update copies button click
            $(document).on('click', '.update-copies', function() {
                const bookId = $(this).data('id');
                
                // Fetch book details
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=get&id=' + bookId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            const book = response.data;
                            $('#update_book_id').val(book.book_id);
                            $('#book_title').val(book.title);
                            $('#update_total_copies').val(book.total_copies);
                            $('#update_available_copies').val(book.available_copies);
                            
                            $('#updateCopiesModal').modal('show');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle save copies button click
            $('#save-copies').click(function() {
                const bookId = $('#update_book_id').val();
                const totalCopies = parseInt($('#update_total_copies').val());
                const availableCopies = parseInt($('#update_available_copies').val());
                
                if (availableCopies > totalCopies) {
                    alert('Available copies cannot exceed total copies.');
                    return;
                }
                
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=update_copies',
                    type: 'POST',
                    data: {
                        book_id: bookId,
                        total_copies: totalCopies,
                        available_copies: availableCopies
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#updateCopiesModal').modal('hide');
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle update location button click
            $(document).on('click', '.update-location', function() {
                const bookId = $(this).data('id');
                
                // Fetch book details
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=get&id=' + bookId,
                    type: 'GET',
                    success: function(response) {
                        if (response.success) {
                            const book = response.data;
                            $('#location_book_id').val(book.book_id);
                            $('#location_book_title').val(book.title);
                            $('#update_shelf_location').val(book.shelf_location);
                            
                            $('#updateLocationModal').modal('show');
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle save location button click
            $('#save-location').click(function() {
                const bookId = $('#location_book_id').val();
                const shelfLocation = $('#update_shelf_location').val();
                
                $.ajax({
                    url: '../../Backend/admin/books/book_handler.php?action=update_location',
                    type: 'POST',
                    data: {
                        book_id: bookId,
                        shelf_location: shelfLocation
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            $('#updateLocationModal').modal('hide');
                            window.location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error: ' + xhr.responseText);
                    }
                });
            });
            
            // Handle bulk upload
            $('#start-bulk-upload').click(function() {
                const formData = new FormData(document.getElementById('bulk-upload-form'));
                
                $('#upload-progress-container').removeClass('d-none');
                $('#upload-result').removeClass('d-none alert-success alert-danger').addClass('d-none');
                
                $.ajax({
                    url: '../../Backend/admin/books/bulk_upload_handler.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(e) {
                            if (e.lengthComputable) {
                                const percentComplete = Math.round((e.loaded / e.total) * 100);
                                $('#upload-progress').css('width', percentComplete + '%').attr('aria-valuenow', percentComplete);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#upload-result').removeClass('d-none alert-danger').addClass('alert-success')
                                .html(`<strong>Success!</strong> ${response.message}<br>` +
                                     `Books added: ${response.stats.added}<br>` +
                                     `Errors: ${response.stats.errors}`);
                                     
                            // Reload after 3 seconds if books were added
                            if (response.stats.added > 0) {
                                setTimeout(function() {
                                    window.location.reload();
                                }, 3000);
                            }
                        } else {
                            $('#upload-result').removeClass('d-none alert-success').addClass('alert-danger')
                                .html(`<strong>Error!</strong> ${response.message}`);
                        }
                    },
                    error: function(xhr) {
                        $('#upload-result').removeClass('d-none alert-success').addClass('alert-danger')
                            .html('<strong>Error!</strong> ' + xhr.responseText);
                    }
                });
            });
            
            // Handle template download
            $('#download-template').click(function() {
                window.location.href = '../../Backend/admin/books/download_template.php';
            });
            
            // Reset modals on close
            $('#addBookModal').on('hidden.bs.modal', function() {
                $('#book-form')[0].reset();
                $('#book_id').val('');
                $('#addBookModalLabel').text('Add New Book');
            });
            
            $('#bulkUploadModal').on('hidden.bs.modal', function() {
                $('#bulk-upload-form')[0].reset();
                $('#upload-progress-container').addClass('d-none');
                $('#upload-progress').css('width', '0%').attr('aria-valuenow', 0);
                $('#upload-result').addClass('d-none');
            });
            
            // Reset category form
            $('#reset-category-form').click(function() {
                $('#category_id').val('');
            });
        });
    </script>
</body>
</html>