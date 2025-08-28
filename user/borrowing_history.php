<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page if not logged in
    header('Location: login.html');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowing History - Library Management System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <style>
        .book-history-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            transition: transform 0.2s;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .book-history-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .book-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-issued {
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        
        .status-overdue {
            background-color: #f8d7da;
            color: #dc3545;
        }
        
        .status-returned {
            background-color: #d1e7dd;
            color: #198754;
        }
        
        .status-lost {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .due-date {
            font-weight: 600;
        }
        
        .overdue {
            color: #dc3545;
        }
        
        .history-tabs .nav-link {
            color: #495057;
        }
        
        .history-tabs .nav-link.active {
            font-weight: 600;
            color: #0d6efd;
            border-bottom: 2px solid #0d6efd;
        }
        
        .loading-spinner {
            text-align: center;
            padding: 40px 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 0;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="logo">
                <h1><i class="fas fa-book"></i>XYZ Library</h1>
            </div>
            <nav>
                <ul>
                    <li><a href="../index.html">Home</a></li>

                </ul>
            </nav>
            <div class="user-menu" id="user-greeting">
                <span class="welcome-user" id="username-display">Hello, User</span>
                <button onclick="handleLogout()" class="btn-logout">Logout</button>
            </div>
        </div>
    </header>

    <section class="page-content">
        <div class="container">
            <h2 class="section-title">My Borrowing History</h2>
            
            <!-- Tabs for different status views -->
            <ul class="nav nav-tabs history-tabs mb-4">
                <li class="nav-item">
                    <button class="nav-link active" id="current-tab" data-bs-toggle="tab" data-bs-target="#current" type="button">
                        Current Books <span class="badge bg-primary" id="current-count">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                        Borrowing History <span class="badge bg-secondary" id="history-count">0</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="overdue-tab" data-bs-toggle="tab" data-bs-target="#overdue" type="button">
                        Overdue <span class="badge bg-danger" id="overdue-count">0</span>
                    </button>
                </li>
            </ul>
            
            <!-- Tab content -->
            <div class="tab-content">
                <!-- Current issued books -->
                <div class="tab-pane fade show active" id="current">
                    <div class="loading-spinner" id="current-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading your current books...</p>
                    </div>
                    
                    <div class="empty-state" id="current-empty" style="display: none;">
                        <i class="fas fa-book"></i>
                        <h4>No Books Currently Borrowed</h4>
                        <p>You don't have any books checked out at the moment.</p>
                    </div>
                    
                    <div class="row" id="current-books"></div>
                </div>
                
                <!-- Borrowing history -->
                <div class="tab-pane fade" id="history">
                    <div class="loading-spinner" id="history-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading your borrowing history...</p>
                    </div>
                    
                    <div class="empty-state" id="history-empty" style="display: none;">
                        <i class="fas fa-history"></i>
                        <h4>No Borrowing History</h4>
                        <p>You haven't borrowed any books yet.</p>
                    </div>
                    
                    <div class="row" id="history-books"></div>
                </div>
                
                <!-- Overdue books -->
                <div class="tab-pane fade" id="overdue">
                    <div class="loading-spinner" id="overdue-loading">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading your overdue books...</p>
                    </div>
                    
                    <div class="empty-state" id="overdue-empty" style="display: none;">
                        <i class="fas fa-check-circle"></i>
                        <h4>No Overdue Books</h4>
                        <p>You don't have any overdue books. Keep up the good work!</p>
                    </div>
                    
                    <div class="row" id="overdue-books"></div>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2><i class="fas fa-book"></i>XYZ Library</h2>
                    <p>"Today a reader, tomorrow a leader." - Margaret Fuller</p>
                </div>
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="../index.html">Home</a></li>
                        <li><a href="reservation_status.php">My Reservations</a></li>
                        <li><a href="borrowing_history.php">Borrowing History</a></li>
                        <li><a href="feedback.html">Feedback</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-envelope"></i> xyz_info@librarysystem.com</p>
                    <p><i class="fas fa-phone"></i> +88 01* **** ****</p>
                    <p><i class="fas fa-map-marker-alt"></i> XYZ Library Street, City</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Library Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Get user data from localStorage
        const user = JSON.parse(localStorage.getItem('libraryUser')) || null;
        
        // Check if user is logged in
        if (!user) {
            window.location.href = 'login.html';
        } else {
            // Display username
            document.getElementById('username-display').textContent = `Hello, ${user.name}`;
            
            // Fetch borrowing history data
            fetchBorrowingHistory();
        }
        
        // Handle logout action
        function handleLogout() {
            localStorage.removeItem('libraryUser');
            window.location.href = '../index.html?logout=true';
        }
        
        // Fetch user's borrowing history
        function fetchBorrowingHistory() {
            fetch(`../../Backend/user/get_borrowing_history.php?user_id=${user.id}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Failed to fetch data');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        displayBooks(data.loans);
                    } else {
                        throw new Error(data.message || 'Error fetching borrowing history');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.querySelectorAll('.loading-spinner').forEach(spinner => {
                        spinner.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i> 
                                Failed to load borrowing history: ${error.message}
                            </div>
                        `;
                    });
                });
        }
        
        // Display books in appropriate tabs
        function displayBooks(loans) {
            // Create categories
            const currentBooks = loans.filter(loan => loan.status === 'issued');
            const overdueBooks = loans.filter(loan => loan.status === 'overdue');
            const historyBooks = loans.filter(loan => loan.status === 'returned' || loan.status === 'lost');
            
            // Update counters
            document.getElementById('current-count').textContent = currentBooks.length;
            document.getElementById('overdue-count').textContent = overdueBooks.length;
            document.getElementById('history-count').textContent = historyBooks.length;
            
            // Display each category
            displayBookCategory(currentBooks, 'current');
            displayBookCategory(overdueBooks, 'overdue');
            displayBookCategory(historyBooks, 'history');
        }
        
        // Display books for a specific category
        function displayBookCategory(books, category) {
            const container = document.getElementById(`${category}-books`);
            const loadingElement = document.getElementById(`${category}-loading`);
            const emptyElement = document.getElementById(`${category}-empty`);
            
            // Hide loading spinner
            loadingElement.style.display = 'none';
            
            // Check if there are books to display
            if (books.length === 0) {
                emptyElement.style.display = 'block';
                return;
            }
            
            // Display books
            books.forEach(book => {
                const dueDate = new Date(book.due_date);
                const returnDate = book.return_date ? new Date(book.return_date) : null;
                const today = new Date();
                
                // Calculate days remaining/overdue
                let daysText = '';
                if (book.status === 'issued') {
                    const daysLeft = Math.ceil((dueDate - today) / (1000 * 60 * 60 * 24));
                    daysText = daysLeft > 0 
                        ? `<span class="text-success">${daysLeft} days left</span>` 
                        : `<span class="text-danger">Due today!</span>`;
                } else if (book.status === 'overdue') {
                    const daysLate = Math.ceil((today - dueDate) / (1000 * 60 * 60 * 24));
                    daysText = `<span class="text-danger">${daysLate} days overdue</span>`;
                }
                
                // Generate book card
                const bookCard = `
                    <div class="col-md-6 col-lg-4">
                        <div class="book-history-card">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="book-status status-${book.status}">${formatStatus(book.status)}</span>
                                <span class="issue-date small">${formatDate(book.issue_date)}</span>
                            </div>
                            <h4>${book.title}</h4>
                            <p class="text-muted mb-1">ISBN: ${book.isbn}</p>
                            
                            <div class="d-flex flex-column mt-3">
                                ${book.status === 'returned' ? 
                                    `<p class="mb-1">Returned on: <span class="text-success fw-semibold">${formatDate(book.return_date)}</span></p>` : 
                                    book.status === 'lost' ? 
                                    `<p class="mb-1">Marked as lost on: <span class="text-secondary fw-semibold">${formatDate(book.return_date)}</span></p>` : 
                                    `<p class="mb-1">Due date: <span class="due-date ${isOverdue(book.due_date) ? 'overdue' : ''}">${formatDate(book.due_date)}</span> ${daysText}</p>`
                                }
                                ${book.fine_amount ? 
                                    `<div class="alert alert-warning mt-2 mb-0 py-2 small">
                                        <i class="fas fa-exclamation-triangle"></i> 
                                        Fine: $${parseFloat(book.fine_amount).toFixed(2)} 
                                        <span class="ms-1">${book.fine_paid ? '(Paid)' : '(Unpaid)'}</span>
                                    </div>` : ''
                                }
                            </div>
                        </div>
                    </div>
                `;
                
                container.innerHTML += bookCard;
            });
        }
        
        // Helper function to format status
        function formatStatus(status) {
            switch(status) {
                case 'issued': return 'Issued';
                case 'overdue': return 'Overdue';
                case 'returned': return 'Returned';
                case 'lost': return 'Lost';
                default: return status.charAt(0).toUpperCase() + status.slice(1);
            }
        }
        
        // Helper function to format date
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString(undefined, options);
        }
        
        // Helper function to check if a date is overdue
        function isOverdue(dateString) {
            return new Date(dateString) < new Date();
        }
    </script>
</body>
</html>