<?php

header('Content-Type: text/html');
// Start the PHP session
session_start();

// Include database connection
require_once '../../Backend/db/db_connection.php';

// Get user ID from URL parameter first (for localStorage users)
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

// If not in URL, try from session (for traditional PHP session users)
if (!$userId && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
}

// Load the page normally, we'll handle unauthenticated users with JavaScript
if ($userId) {
    // Get the user's member ID
    $memberQuery = "SELECT member_id FROM member_details WHERE user_id = ?";
    $memberStmt = $pdo->prepare($memberQuery);
    $memberStmt->execute([$userId]);
    $member = $memberStmt->fetch(PDO::FETCH_ASSOC);

    if ($member) {
        $memberId = $member['member_id'];

        // Get the user's reservations including pending ones
        $reservationsQuery = "SELECT r.*, b.title, b.author, b.isbn, b.cover_image 
                         FROM book_reservations r
                         JOIN books b ON r.book_id = b.book_id
                         WHERE r.member_id = ?
                         ORDER BY r.reservation_date DESC";
        $reservationsStmt = $pdo->prepare($reservationsQuery);
        $reservationsStmt->execute([$memberId]);
        $reservations = $reservationsStmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // No member record found
        $reservations = [];
    }
} else {
    // No user ID found, we'll handle this with JavaScript
    $reservations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Library Management System</title>
    <link rel="stylesheet" href="../style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Your existing styles here */
    html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        main {
            flex: 1 0 auto; 
        }
        
        footer {
            flex-shrink: 0; 
        }
        
       
        .reservations-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 20px;
            min-height: 300px;
        }
        
        .reservation-card {
            display: flex;
            margin-bottom: 20px;
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .reservation-cover {
            width: 120px;
            height: 180px;
            background-size: cover;
            background-position: center;
        }
        
        .reservation-details {
            flex-grow: 1;
            padding: 15px;
        }
        
        .reservation-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .reservation-author {
            color: #666;
            margin-bottom: 10px;
        }
        
        .reservation-info {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .reservation-item {
            flex: 1;
            min-width: 200px;
        }
        
        .item-label {
            font-size: 0.9rem;
            color: #777;
            margin-bottom: 3px;
        }
        
        .item-value {
            font-weight: 500;
        }
        
        .reservation-actions {
            margin-top: 15px;
        }
        
        .btn-cancel {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-cancel:hover {
            background-color: #c0392b;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-pending {
            background-color: #f39c12;
            color: white;
        }
        
        .status-fulfilled {
            background-color: #2ecc71;
            color: white;
        }
        
        .status-cancelled {
            background-color: #e74c3c;
            color: white;
        }
        
        .status-expired {
            background-color: #95a5a6;
            color: white;
        }
        
        .no-reservations {
            text-align: center;
            padding: 40px;
            background-color: #f8f9fa;
            border-radius: 8px;
            color: #666;
        }
        
        #loading-indicator {
            text-align: center;
            padding: 20px;
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
                    <li><a href="#">Books</a></li>
                </ul>
            </nav>
            <div class="user-menu">
                <span class="welcome-user" id="user-greeting">Hello, Guest</span>
                
            </div>
        </div>
    </header>
    
    <main>
        <div class="container">
            <h2 class="page-title">My Reservations</h2>
            
            <div class="reservations-container" id="reservations-container">
                <div id="loading-indicator">
                    <i class="fas fa-spinner fa-spin fa-2x"></i>
                    <p>Loading your reservations...</p>
                </div>
                
                <!-- Reservations will be loaded here -->
            </div>
        </div>
    </main>
    
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <h2><i class="fas fa-book"></i>ABC Library</h2>
                    <p>"Today a reader, tomorrow a leader." - Margaret Fuller</p>
                </div>
                <div class="footer-links">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="../index.html">Home</a></li>
                        <li><a href="#">About Us</a></li>
                        <li><a href="feedback.html">Feedback</a></li>
                    </ul>
                </div>
                <div class="footer-contact">
                    <h3>Contact Us</h3>
                    <p><i class="fas fa-envelope"></i> abc_info@librarysystem.com</p>
                    <p><i class="fas fa-phone"></i> +88 01* **** ****</p>
                    <p><i class="fas fa-map-marker-alt"></i> ABC Library Street, Dhaka</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2025 Library Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get user data from localStorage
        const userDataString = localStorage.getItem('libraryUser');
        if (userDataString) {
            try {
                const userData = JSON.parse(userDataString);
                if (userData && userData.name) {
                    // Update greeting
                    document.getElementById('user-greeting').textContent = 'Hello, ' + userData.name;
                    
                    // Now load reservations
                    if (userData.id) {
                        loadReservations(userData.id);
                    } else {
                        showError("User ID not found in your profile. Please log out and log in again.");
                    }
                }
            } catch (e) {
                console.error('Error parsing user data from localStorage:', e);
                showError("Error loading your profile. Please log out and log in again.");
            }
        } else {
            // If no user in localStorage, redirect to login
            window.location.href = 'login.html';
        }
    });
    
    function loadReservations(userId) {
        const container = document.getElementById('reservations-container');
        
        // First try to use PHP-rendered reservations if any
        const phpReservations = <?php echo json_encode($reservations); ?>;
        
        if (phpReservations && phpReservations.length > 0) {
            renderReservations(phpReservations);
            return;
        }
        
        // Otherwise, fetch them via AJAX
        fetch(`../../Backend/user/get_reservations.php?user_id=${userId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    renderReservations(data.reservations);
                } else {
                    throw new Error(data.message || 'Failed to load reservations');
                }
            })
            .catch(error => {
                console.error('Error loading reservations:', error);
                showError("Couldn't load your reservations. Please try again later.");
            });
    }
    
    function renderReservations(reservations) {
        const container = document.getElementById('reservations-container');
        
        // Remove loading indicator
        document.getElementById('loading-indicator').remove();
        
        if (reservations.length === 0) {
            container.innerHTML = `
                <div class="no-reservations">
                    <i class="fas fa-book-open fa-3x" style="margin-bottom: 15px; color: #bdc3c7;"></i>
                    <h3>No Reservations Found</h3>
                    <p>You have not reserved any books yet. Browse our collection and reserve books that interest you.</p>
                    <a href="../index.html" class="btn btn-primary" style="margin-top: 15px;">Browse Books</a>
                </div>
            `;
            return;
        }
        
        // Create HTML for each reservation
        let html = '';
        reservations.forEach(reservation => {
            const coverUrl = reservation.cover_image || 'https://via.placeholder.com/120x180?text=No+Cover';
            const statusClass = 'status-' + reservation.status;
            const statusLabel = reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1);
            const reservationDate = new Date(reservation.reservation_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'});
            const expiryDate = new Date(reservation.expiry_date).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'});
            
            html += `
                <div class="reservation-card">
                    <div class="reservation-cover" style="background-image: url('${coverUrl}')"></div>
                    <div class="reservation-details">
                        <div class="reservation-title">${reservation.title}</div>
                        <div class="reservation-author">by ${reservation.author}</div>
                        
                        <span class="status-badge ${statusClass}">${statusLabel}</span>
                        
                        <div class="reservation-info">
                            <div class="reservation-item">
                                <div class="item-label">Reservation Date</div>
                                <div class="item-value">${reservationDate}</div>
                            </div>
                            <div class="reservation-item">
                                <div class="item-label">Expiry Date</div>
                                <div class="item-value">${expiryDate}</div>
                            </div>
                            <div class="reservation-item">
                                <div class="item-label">ISBN</div>
                                <div class="item-value">${reservation.isbn || 'N/A'}</div>
                            </div>
                        </div>
                        
                        ${reservation.status === 'pending' ? `
                        <div class="reservation-actions">
                            <button class="btn-cancel" onclick="cancelReservation(${reservation.reservation_id})">Cancel Reservation</button>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    function showError(message) {
        const container = document.getElementById('reservations-container');
        document.getElementById('loading-indicator').remove();
        
        container.innerHTML = `
            <div class="no-reservations" style="color: #e74c3c;">
                <i class="fas fa-exclamation-circle fa-3x" style="margin-bottom: 15px;"></i>
                <h3>Error</h3>
                <p>${message}</p>
                <a href="../index.html" class="btn btn-primary" style="margin-top: 15px;">Back to Home</a>
            </div>
        `;
    }
    
    function cancelReservation(reservationId) {
        if (confirm('Are you sure you want to cancel this reservation?')) {
            fetch('../../Backend/user/cancel_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    reservation_id: reservationId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Reservation cancelled successfully');
                    location.reload();
                } else {
                    alert(data.message || 'An error occurred while cancelling the reservation');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again later.');
            });
        }
    }
    </script>
</body>
</html>