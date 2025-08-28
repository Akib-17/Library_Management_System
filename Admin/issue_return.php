<?php
session_start();

// Check if a session user exists and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: /library_management_system/Frontend/Admin/login.html');
    exit;
}

// Include database connection
require_once '../../Backend/db/db_connection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Circulation Management</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .nav-tabs .nav-link {
            cursor: pointer;
        }
        .status-issued {
            color: #28a745;
            font-weight: bold;
        }
        .status-returned {
            color: #007bff;
            font-weight: bold;
        }
        .status-overdue {
            color: #dc3545;
            font-weight: bold;
        }
        .status-lost {
            color: #fd7e14;
            font-weight: bold;
        }
        .status-pending {
            color: #6c757d;
            font-weight: bold;
        }
        .status-fulfilled {
            color: #28a745;
            font-weight: bold;
        }
        .status-cancelled {
            color: #dc3545;
            font-weight: bold;
        }
        .status-expired {
            color: #fd7e14;
            font-weight: bold;
        }
        .search-results {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 10px;
            margin-top: 15px;
        }
        .search-item {
            cursor: pointer;
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .search-item:hover {
            background-color: #f8f9fa;
        }
        .member-card, .book-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .receipt {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ddd;
            font-family: 'Courier New', monospace;
        }
        .receipt-header, .receipt-footer {
            text-align: center;
            margin-bottom: 20px;
        }
        .loan-limit-exceeded {
            color: #dc3545;
            font-weight: bold;
        }
        .loan-limit-warning {
            color: #ffc107;
            font-weight: bold;
        }
        #printReceipt {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <main class="col-12 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Book Circulation Management</h1>
                </div>

                <!-- Tabs for different circulation functions -->
                <ul class="nav nav-tabs mb-3" id="circulationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="issue-tab" data-bs-toggle="tab" data-bs-target="#issue" type="button">Issue Books</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="return-tab" data-bs-toggle="tab" data-bs-target="#return" type="button">Return Books</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="renew-tab" data-bs-toggle="tab" data-bs-target="#renew" type="button">Renew Loans</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations" type="button">Reservations</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="all-loans-tab" data-bs-toggle="tab" data-bs-target="#all-loans" type="button">All Loans</button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="circulationTabsContent">
                    <!-- Issue Books Tab -->
                    <div class="tab-pane fade show active" id="issue" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Find Member</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="member-search-form">
                                            <div class="mb-3">
                                                <label for="member-search" class="form-label">Search by Name, Email or Membership Number</label>
                                                <input type="text" class="form-control" id="member-search" placeholder="Enter search term">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Search</button>
                                        </form>
                                        <div id="member-search-results" class="search-results mt-3" style="display: none;"></div>
                                    </div>
                                </div>
                                
                                <div id="member-details" class="card mb-4" style="display: none;">
                                    <div class="card-header d-flex justify-content-between">
                                        <h5 class="card-title mb-0">Member Details</h5>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-member">Clear</button>
                                    </div>
                                    <div class="card-body">
                                        <div id="member-info"></div>
                                        <div id="member-limit" class="alert mt-2"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Find Book</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="book-search-form">
                                            <div class="mb-3">
                                                <label for="book-search" class="form-label">Search by Title, Author or ISBN</label>
                                                <input type="text" class="form-control" id="book-search" placeholder="Enter search term">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Search</button>
                                        </form>
                                        <div id="book-search-results" class="search-results mt-3" style="display: none;"></div>
                                    </div>
                                </div>
                                
                                <div id="book-details" class="card mb-4" style="display: none;">
                                    <div class="card-header d-flex justify-content-between">
                                        <h5 class="card-title mb-0">Book Details</h5>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-book">Clear</button>
                                    </div>
                                    <div class="card-body">
                                        <div id="book-info"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="issue-section" class="card mb-4" style="display: none;">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Issue Book</h5>
                            </div>
                            <div class="card-body">
                                <form id="issue-form">
                                    <input type="hidden" id="issue-member-id" name="member_id">
                                    <input type="hidden" id="issue-book-id" name="book_id">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="issue-date" class="form-label">Issue Date</label>
                                                <input type="date" class="form-control" id="issue-date" name="issue_date" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="due-date" class="form-label">Due Date</label>
                                                <input type="date" class="form-control" id="due-date" name="due_date" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="check-reservation" name="check_reservation">
                                            <label class="form-check-label" for="check-reservation">
                                                Check if this fulfills a reservation
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-success" id="issue-btn">Issue Book</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div id="issue-result" class="alert" style="display: none;"></div>
                    </div>

                    <!-- Return Books Tab -->
                    <div class="tab-pane fade" id="return" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Search Issued Books</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="return-search-form">
                                            <div class="mb-3">
                                                <label for="return-search" class="form-label">Search by Book Title, ISBN or Member Name</label>
                                                <input type="text" class="form-control" id="return-search" placeholder="Enter search term">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Search</button>
                                        </form>
                                        <div id="return-search-results" class="search-results mt-3" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div id="return-details" class="card mb-4" style="display: none;">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Process Return</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="issue-details"></div>
                                        <hr>
                                        <form id="return-form" class="mt-3">
                                            <input type="hidden" id="return-issue-id" name="issue_id">
                                            <input type="hidden" id="return-book-id" name="book_id">
                                            
                                            <div class="mb-3">
                                                <label for="return-date" class="form-label">Return Date</label>
                                                <input type="date" class="form-control" id="return-date" name="return_date" value="<?= date('Y-m-d') ?>" required>
                                            </div>
                                            
                                            <div id="fine-section" class="mb-3" style="display: none;">
                                                <div class="alert alert-warning">
                                                    <h6>Late Return Fine</h6>
                                                    <p id="fine-amount-text">Fine Amount: <span id="fine-amount-display">$0.00</span></p>
                                                    <input type="hidden" id="fine-amount" name="fine_amount" value="0">
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="fine-reason" class="form-label">Fine Reason</label>
                                                    <select class="form-select" id="fine-reason" name="fine_reason">
                                                        <option value="overdue">Overdue Return</option>
                                                        <option value="damage">Book Damage</option>
                                                        <option value="lost">Book Lost</option>
                                                    </select>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="book-damaged" name="book_damaged">
                                                    <label class="form-check-label" for="book-damaged">
                                                        Book is damaged
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="book-lost" name="book_lost">
                                                    <label class="form-check-label" for="book-lost">
                                                        Book is lost
                                                    </label>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-primary">Process Return</button>
                                                <button type="button" class="btn btn-secondary" id="generate-receipt">Generate Receipt</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <div id="return-result" class="alert" style="display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Renew Books Tab -->
                    <div class="tab-pane fade" id="renew" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Search Issued Books for Renewal</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="renew-search-form">
                                            <div class="mb-3">
                                                <label for="renew-search" class="form-label">Search by Book Title, ISBN or Member Name</label>
                                                <input type="text" class="form-control" id="renew-search" placeholder="Enter search term">
                                            </div>
                                            <button type="submit" class="btn btn-primary">Search</button>
                                        </form>
                                        <div id="renew-search-results" class="search-results mt-3" style="display: none;"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div id="renew-details" class="card mb-4" style="display: none;">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0">Process Renewal</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="renew-issue-details"></div>
                                        <hr>
                                        <form id="renew-form" class="mt-3">
                                            <input type="hidden" id="renew-issue-id" name="issue_id">
                                            
                                            <div class="mb-3">
                                                <label for="new-due-date" class="form-label">New Due Date</label>
                                                <input type="date" class="form-control" id="new-due-date" name="new_due_date" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <button type="submit" class="btn btn-primary">Renew Book</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                
                                <div id="renew-result" class="alert" style="display: none;"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Reservations Tab -->
                    <div class="tab-pane fade" id="reservations" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Manage Reservations</h5>
                            </div>
                            <div class="card-body">
                                <ul class="nav nav-pills mb-3" id="reservationTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="pending-tab" data-bs-toggle="pill" data-bs-target="#pending" type="button">Pending</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="fulfilled-tab" data-bs-toggle="pill" data-bs-target="#fulfilled" type="button">Fulfilled</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="cancelled-tab" data-bs-toggle="pill" data-bs-target="#cancelled" type="button">Cancelled/Expired</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="reservationTabsContent">
                                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-striped" id="pending-reservations-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Book</th>
                                                        <th>Member</th>
                                                        <th>Reservation Date</th>
                                                        <th>Expiry Date</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Will be populated via AJAX -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <div class="tab-pane fade" id="fulfilled" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-striped" id="fulfilled-reservations-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Book</th>
                                                        <th>Member</th>
                                                        <th>Reservation Date</th>
                                                        <th>Reservation Date</th> <!-- Changed from "Fulfilled Date" to "Reservation Date" -->
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Will be populated via AJAX -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    
                                    <div class="tab-pane fade" id="cancelled" role="tabpanel">
                                        <div class="table-responsive">
                                            <table class="table table-striped" id="cancelled-reservations-table">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Book</th>
                                                        <th>Member</th>
                                                        <th>Reservation Date</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Will be populated via AJAX -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="reservation-result" class="alert" style="display: none;"></div>
                    </div>

                    <!-- All Loans Tab -->
                    <div class="tab-pane fade" id="all-loans" role="tabpanel">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">All Book Loans</h5>
                                <div>
                                    <button class="btn btn-sm btn-outline-secondary" id="refresh-loans">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh
                                    </button>
                                    <button class="btn btn-sm btn-outline-primary" id="export-loans">
                                        <i class="bi bi-download"></i> Export
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="all-loans-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Book</th>
                                                <th>Member</th>
                                                <th>Issue Date</th>
                                                <th>Due Date</th>
                                                <th>Return Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Will be populated via AJAX -->
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

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">Return Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="receipt-content" class="receipt">
                        <div class="receipt-header">
                            <h4>LIBRARY MANAGEMENT SYSTEM</h4>
                            <p>Book Return Receipt</p>
                            <p id="receipt-date"></p>
                        </div>
                        <div class="receipt-body">
                            <p><strong>Member: </strong><span id="receipt-member"></span></p>
                            <p><strong>Book: </strong><span id="receipt-book"></span></p>
                            <p><strong>Issue Date: </strong><span id="receipt-issue-date"></span></p>
                            <p><strong>Due Date: </strong><span id="receipt-due-date"></span></p>
                            <p><strong>Return Date: </strong><span id="receipt-return-date"></span></p>
                            <div id="receipt-fine-section" style="display: none;">
                                <hr>
                                <p><strong>Fine Amount: </strong><span id="receipt-fine-amount"></span></p>
                                <p><strong>Fine Reason: </strong><span id="receipt-fine-reason"></span></p>
                                <p><strong>Fine Status: </strong><span id="receipt-fine-status"></span></p>
                            </div>
                        </div>
                        <div class="receipt-footer">
                            <p>Thank you for using our library services!</p>
                            <p>Receipt ID: <span id="receipt-id"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <!-- <button type="button" class="btn btn-primary" id="print-receipt">Print Receipt</button> -->
                </div>
            </div>
        </div>
    </div>

    <!-- Printable Receipt (Hidden) -->
    <div id="printReceipt" class="receipt"></div>

    <!-- Required JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Global variables
            let selectedMemberId = null;
            let selectedBookId = null;
            let memberBorrowingLimit = 0;
            let currentLoans = 0;
            
            // Initialize DataTables
            const allLoansTable = $('#all-loans-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[3, 'desc']] // Sort by issue date (newest first)
            });
            
            const pendingReservationsTable = $('#pending-reservations-table').DataTable({
                responsive: true,
                pageLength: 10,
                order: [[3, 'desc']] // Sort by reservation date (newest first)
            });

            const fulfilledReservationsTable = $('#fulfilled-reservations-table').DataTable({
    responsive: true,
    pageLength: 10,
    order: [[3, 'desc']] // Sort by reservation date (newest first)
});

const cancelledReservationsTable = $('#cancelled-reservations-table').DataTable({
    responsive: true,
    pageLength: 10,
    order: [[3, 'desc']] // Sort by reservation date (newest first)
});

// Update the loadReservations function to use reservation_date instead of fulfilled_date
function loadReservations() {
    $.ajax({
        url: '../../Backend/admin/loans/reservation_handler.php?action=get_all_reservations',
        type: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Clear existing data in all tables
                pendingReservationsTable.clear();
                fulfilledReservationsTable.clear();
                cancelledReservationsTable.clear();
                
                // Add pending reservations
                $.each(response.data.pending, function(index, reservation) {
                    const actions = `
                        <button class="btn btn-sm btn-success fulfill-reservation" data-id="${reservation.reservation_id}">Fulfill</button>
                        <button class="btn btn-sm btn-danger cancel-reservation" data-id="${reservation.reservation_id}">Cancel</button>
                    `;
                    
                    pendingReservationsTable.row.add([
                        reservation.reservation_id,
                        reservation.book_title,
                        reservation.member_name,
                        reservation.reservation_date,
                        reservation.expiry_date,
                        `<span class="status-pending">PENDING</span>`,
                        actions
                    ]);
                });
                
                // Add fulfilled reservations - using reservation_date instead of fulfilled_date
                $.each(response.data.fulfilled, function(index, reservation) {
                    const actions = `
                        <button class="btn btn-sm btn-info view-reservation" data-id="${reservation.reservation_id}">View Details</button>
                    `;
                    
                    fulfilledReservationsTable.row.add([
                        reservation.reservation_id,
                        reservation.book_title,
                        reservation.member_name,
                        reservation.reservation_date,
                        reservation.reservation_date, // Use reservation_date instead of fulfilled_date
                        actions
                    ]);
                });
                
                // Add cancelled/expired reservations
                $.each([...response.data.cancelled, ...response.data.expired], function(index, reservation) {
                    const actions = `
                        <button class="btn btn-sm btn-info view-reservation" data-id="${reservation.reservation_id}">View Details</button>
                    `;
                    
                    const statusClass = reservation.status === 'cancelled' ? 'status-cancelled' : 'status-expired';
                    const statusText = reservation.status.toUpperCase();
                    
                    cancelledReservationsTable.row.add([
                        reservation.reservation_id,
                        reservation.book_title,
                        reservation.member_name,
                        reservation.reservation_date,
                        `<span class="${statusClass}">${statusText}</span>`,
                        actions
                    ]);
                });
                
                // Draw all tables
                pendingReservationsTable.draw();
                fulfilledReservationsTable.draw();
                cancelledReservationsTable.draw();
            } else {
                alert('Error loading reservations: ' + response.message);
            }
        },
        error: function() {
            alert('Error loading reservations. Please try again.');
        }
    });
}
            
            // Load all loans on page load and tab switch
            function loadAllLoans() {
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=get_all_loans',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Clear existing data
                            allLoansTable.clear();
                            
                            // Add new data
                            $.each(response.data, function(index, loan) {
                                let statusClass = '';
                                switch(loan.status) {
                                    case 'issued': statusClass = 'status-issued'; break;
                                    case 'returned': statusClass = 'status-returned'; break;
                                    case 'overdue': statusClass = 'status-overdue'; break;
                                    case 'lost': statusClass = 'status-lost'; break;
                                }
                                
                                const returnDate = loan.return_date ? loan.return_date : 'Not Returned';
                                
                                let actions = '';
                                if (loan.status === 'issued' || loan.status === 'overdue') {
                                    actions += `<button class="btn btn-sm btn-primary process-return" data-id="${loan.issue_id}">Return</button> `;
                                    actions += `<button class="btn btn-sm btn-secondary process-renew" data-id="${loan.issue_id}">Renew</button>`;
                                } else if (loan.status === 'returned') {
                                    actions += `<button class="btn btn-sm btn-info view-receipt" data-id="${loan.issue_id}">None</button>`;
                                }
                                
                                allLoansTable.row.add([
                                    loan.issue_id,
                                    loan.book_title,
                                    loan.member_name,
                                    loan.issue_date,
                                    loan.due_date,
                                    returnDate,
                                    `<span class="${statusClass}">${loan.status.toUpperCase()}</span>`,
                                    actions
                                ]);
                            });
                            
                            allLoansTable.draw();
                        } else {
                            alert('Error loading loans: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error loading loans. Please try again.');
                    }
                });
            }
            
            // Load reservations

            
            // Search for members
            $('#member-search-form').submit(function(e) {
                e.preventDefault();
                const searchTerm = $('#member-search').val();
                
                if (searchTerm.trim() === '') {
                    alert('Please enter a search term');
                    return;
                }
                
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=search_members',
                    type: 'POST',
                    data: { search_term: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            if (response.data.length === 0) {
                                html = '<p>No members found. Please try a different search term.</p>';
                            } else {
                                $.each(response.data, function(index, member) {
                                    html += `
                                        <div class="search-item select-member" data-id="${member.member_id}" 
                                             data-limit="${member.max_books_allowed}" data-loans="${member.current_loans}">
                                            <strong>${member.first_name} ${member.last_name}</strong> (${member.membership_number})<br>
                                            <small>Email: ${member.email} | Type: ${member.membership_type}</small>
                                        </div>
                                    `;
                                });
                            }
                            
                            $('#member-search-results').html(html).show();
                        } else {
                            alert('Error searching for members: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error searching for members. Please try again.');
                    }
                });
            });
            
            // Search for books
            $('#book-search-form').submit(function(e) {
                e.preventDefault();
                const searchTerm = $('#book-search').val();
                
                if (searchTerm.trim() === '') {
                    alert('Please enter a search term');
                    return;
                }
                
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=search_books',
                    type: 'POST',
                    data: { search_term: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            let html = '';
                            if (response.data.length === 0) {
                                html = '<p>No books found. Please try a different search term.</p>';
                            } else {
                                $.each(response.data, function(index, book) {
                                    let availability = '';
                                    if (book.available_copies > 0) {
                                        availability = `<span class="text-success">Available (${book.available_copies}/${book.total_copies})</span>`;
                                    } else {
                                        availability = `<span class="text-danger">Unavailable (0/${book.total_copies})</span>`;
                                    }
                                    
                                    html += `
                                        <div class="search-item select-book" data-id="${book.book_id}" 
                                             data-available="${book.available_copies > 0 ? 'true' : 'false'}">
                                            <strong>${book.title}</strong> by ${book.author}<br>
                                            <small>ISBN: ${book.isbn} | ${availability}</small>
                                        </div>
                                    `;
                                });
                            }
                            
                            $('#book-search-results').html(html).show();
                        } else {
                            alert('Error searching for books: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error searching for books. Please try again.');
                    }
                });
            });
            
            // Select member from search results
            $(document).on('click', '.select-member', function() {
                const memberId = $(this).data('id');
                memberBorrowingLimit = $(this).data('limit');
                currentLoans = $(this).data('loans');
                
                // Get member details
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=get_member_details',
                    type: 'POST',
                    data: { member_id: memberId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            selectedMemberId = memberId;
                            const member = response.data.member;
                            const loans = response.data.loans;
                            
                            // Display member info
                            let memberInfo = `
                                <p><strong>Name:</strong> ${member.first_name} ${member.last_name}</p>
                                <p><strong>Membership Number:</strong> ${member.membership_number}</p>
                                <div class="mb-3">
                                    <label for="membership-type-select" class="form-label"><strong>Membership Type:</strong></label>
                                    <select class="form-select" id="membership-type-select">
                                        <option value="regular" ${member.membership_type === 'regular' ? 'selected' : ''}>Regular (5 books)</option>
                                        <option value="premium" ${member.membership_type === 'premium' ? 'selected' : ''}>Premium (10 books)</option>
                                        <option value="student" ${member.membership_type === 'student' ? 'selected' : ''}>Student (50 books)</option>
                                        <option value="faculty" ${member.membership_type === 'faculty' ? 'selected' : ''}>Faculty (50 books)</option>
                                    </select>
                                </div>
                                <p><strong>Email:</strong> ${member.email}</p>
                                <p><strong>Current Loans:</strong> <span id="current-loans-display">${currentLoans} / <span id="borrowing-limit-display">${memberBorrowingLimit}</span></span></p>
                            `;
                            
                            $('#member-info').html(memberInfo);
                            
                            // Add event listener to membership type dropdown
                            $('#membership-type-select').change(function() {
                                const newType = $(this).val();
                                updateMembershipType(memberId, newType);
                            });
                            
                            // Display borrowing limit status
                            updateBorrowingLimitDisplay(currentLoans, memberBorrowingLimit);
                            
                            // Show member details
                            $('#member-details').show();
                            $('#member-search-results').hide();
                            
                            // Check if both member and book are selected
                            checkIssueEligibility();
                        } else {
                            alert('Error getting member details: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error getting member details. Please try again.');
                    }
                });
            });
            // Add this function to update the borrowing limit display
            function updateBorrowingLimitDisplay(currentLoans, limit) {
                let limitHtml = '';
                if (currentLoans >= limit) {
                    limitHtml = `
                        <div class="loan-limit-exceeded">
                            <i class="bi bi-exclamation-triangle-fill"></i> 
                            Borrowing limit reached. This member cannot borrow more books.
                        </div>
                    `;
                } else if (currentLoans >= (limit * 0.8)) {
                    limitHtml = `
                        <div class="loan-limit-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            Member approaching borrowing limit (${currentLoans}/${limit}).
                        </div>
                    `;
                } else {
                    limitHtml = `
                        <div class="text-success">
                            <i class="bi bi-check-circle"></i> 
                            Member can borrow up to ${limit - currentLoans} more books.
                        </div>
                    `;
                }
                
                $('#member-limit').html(limitHtml);
            }

            // Add this function to update the membership type
            function updateMembershipType(memberId, membershipType) {
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=update_membership_type',
                    type: 'POST',
                    data: { 
                        member_id: memberId,
                        membership_type: membershipType
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Update the global variable
                            memberBorrowingLimit = response.data.max_books_allowed;
                            
                            // Update the display
                            $('#borrowing-limit-display').text(memberBorrowingLimit);
                            updateBorrowingLimitDisplay(currentLoans, memberBorrowingLimit);
                            
                            // Check issue eligibility again
                            checkIssueEligibility();
                            
                            // Show success message
                            const alertHtml = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    Membership type updated successfully! New book limit: ${memberBorrowingLimit}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            $('#member-info').prepend(alertHtml);
                        } else {
                            alert('Error updating membership type: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error updating membership type. Please try again.');
                    }
                });
            }
            
            // Select book from search results
            $(document).on('click', '.select-book', function() {
                const bookId = $(this).data('id');
                const isAvailable = $(this).attr('data-available') === 'true';
                console.log('Book ID:', bookId);
                console.log('isAvailable:', $(this).data('available'), isAvailable);
                
                if (!isAvailable) {
                    alert('This book is not available for loan.');
                    return;
                }
                
                // Get book details
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=get_book_details',
                    type: 'POST',
                    data: { book_id: bookId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            selectedBookId = bookId;
                            const book = response.data;
                            
                            // Display book info
                            let bookInfo = `
                                <p><strong>Title:</strong> ${book.title}</p>
                                <p><strong>Author:</strong> ${book.author}</p>
                                <p><strong>ISBN:</strong> ${book.isbn}</p>
                                <p><strong>Category:</strong> ${book.category_name}</p>
                                <p><strong>Availability:</strong> ${book.available_copies} of ${book.total_copies} copies available</p>
                                <p><strong>Shelf Location:</strong> ${book.shelf_location || 'Not specified'}</p>
                            `;
                            
                            $('#book-info').html(bookInfo);
                            
                            // Show book details
                            $('#book-details').show();
                            $('#book-search-results').hide();
                            
                            // Check if both member and book are selected
                            checkIssueEligibility();
                        } else {
                            alert('Error getting book details: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error getting book details. Please try again.');
                    }
                });
            });
            
            // Check if book can be issued to member
            function checkIssueEligibility() {
                if (selectedMemberId && selectedBookId) {
                    // Set hidden fields in the issue form
                    $('#issue-member-id').val(selectedMemberId);
                    $('#issue-book-id').val(selectedBookId);
                    
                    // Show the issue section
                    $('#issue-section').show();
                    
                    // Check if member has reached borrowing limit
                    if (currentLoans >= memberBorrowingLimit) {
                        $('#issue-btn').prop('disabled', true).html('<i class="bi bi-x-circle"></i> Borrowing Limit Reached');
                    } else {
                        $('#issue-btn').prop('disabled', false).html('<i class="bi bi-book"></i> Issue Book');
                    }
                }
            }
            
            // Clear selected member
            $('#clear-member').click(function() {
                selectedMemberId = null;
                memberBorrowingLimit = 0;
                currentLoans = 0;
                $('#member-details').hide();
                $('#member-search').val('');
                
                if (!selectedBookId) {
                    $('#issue-section').hide();
                }
                
                checkIssueEligibility();
            });
            
            // Clear selected book
            $('#clear-book').click(function() {
                selectedBookId = null;
                $('#book-details').hide();
                $('#book-search').val('');
                
                if (!selectedMemberId) {
                    $('#issue-section').hide();
                }
                
                checkIssueEligibility();
            });
            
            // Issue book form submission
            $('#issue-form').submit(function(e) {
                e.preventDefault();
                
                if (!selectedMemberId || !selectedBookId) {
                    alert('Please select both a member and a book.');
                    return;
                }
                
                if (currentLoans >= memberBorrowingLimit) {
                    alert('This member has reached their borrowing limit.');
                    return;
                }
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=issue_book',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#issue-result').removeClass('alert-danger').addClass('alert-success')
                                .html(`<strong>Success!</strong> ${response.message}`).show();
                            
                            // Reset form and selections
                            $('#issue-form')[0].reset();
                            $('#clear-member').click();
                            $('#clear-book').click();
                            $('#issue-section').hide();
                            
                            // Set default dates
                            $('#issue-date').val(new Date().toISOString().split('T')[0]);
                            $('#due-date').val(new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);
                            
                            // Reload all loans table
                            loadAllLoans();
                        } else {
                            $('#issue-result').removeClass('alert-success').addClass('alert-danger')
                                .html(`<strong>Error!</strong> ${response.message}`).show();
                        }
                    },
                    error: function() {
                        $('#issue-result').removeClass('alert-success').addClass('alert-danger')
                            .html('<strong>Error!</strong> An unexpected error occurred. Please try again.').show();
                    }
                });
            });

            // Load the loans and reservations when the page loads
            loadAllLoans();
            loadReservations();
            
            // Refresh loans table
            $('#refresh-loans').click(function() {
                loadAllLoans();
            });
            
            // Handle tab switches
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                const targetId = $(e.target).attr('id');
                
                if (targetId === 'all-loans-tab') {
                    loadAllLoans();
                } else if (targetId === 'reservations-tab') {
                    loadReservations();
                }
            });
            
            // Process return from all loans table
            $(document).on('click', '.process-return', function() {
                const issueId = $(this).data('id');
                
                // Switch to return tab and load the issue details
                $('#return-tab').tab('show');
                
                // Fetch issue details and populate return form
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=get_issue_details',
                    type: 'POST',
                    data: { issue_id: issueId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const issue = response.data;
                            
                            // Populate issue details
                            let issueHtml = `
                                <p><strong>Book:</strong> ${issue.book_title}</p>
                                <p><strong>Member:</strong> ${issue.member_name}</p>
                                <p><strong>Issue Date:</strong> ${issue.issue_date}</p>
                                <p><strong>Due Date:</strong> ${issue.due_date}</p>
                                <p><strong>Status:</strong> <span class="status-${issue.status}">${issue.status.toUpperCase()}</span></p>
                            `;
                            
                            if (issue.status === 'overdue') {
                                const dueDate = new Date(issue.due_date);
                                const today = new Date();
                                const diffDays = Math.ceil((today - dueDate) / (1000 * 60 * 60 * 24));
                                const fineAmount = diffDays * 0.50; // $0.50 per day
                                
                                issueHtml += `
                                    <p><strong>Days Overdue:</strong> ${diffDays}</p>
                                `;
                                
                                $('#fine-amount-display').text('$' + fineAmount.toFixed(2));
                                $('#fine-amount').val(fineAmount.toFixed(2));
                                $('#fine-section').show();
                            } else {
                                $('#fine-section').hide();
                            }
                            
                            $('#issue-details').html(issueHtml);
                            
                            // Set hidden fields
                            $('#return-issue-id').val(issue.issue_id);
                            $('#return-book-id').val(issue.book_id);
                            
                            // Show return details
                            $('#return-details').show();
                            $('#return-result').hide();
                        } else {
                            alert('Error getting issue details: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error getting issue details. Please try again.');
                    }
                });
            });
            
            // Process return form submission
            $('#return-form').submit(function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                const isLost = $('#book-lost').is(':checked');
                const isDamaged = $('#book-damaged').is(':checked');
                
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=return_book',
                    type: 'POST',
                    data: formData + '&is_lost=' + isLost + '&is_damaged=' + isDamaged,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#return-result').removeClass('alert-danger').addClass('alert-success')
                                .html(`<strong>Success!</strong> ${response.message}`).show();
                            
                            // Reset form
                            $('#return-form')[0].reset();
                            $('#return-details').hide();
                            
                            // Reload all loans table
                            loadAllLoans();
                            
                            // Generate receipt
                            if (response.data && response.data.issue_id) {
                                generateReceipt(response.data.issue_id);
                            }
                        } else {
                            $('#return-result').removeClass('alert-success').addClass('alert-danger')
                                .html(`<strong>Error!</strong> ${response.message}`).show();
                        }
                    },
                    error: function() {
                        $('#return-result').removeClass('alert-success').addClass('alert-danger')
                            .html('<strong>Error!</strong> An unexpected error occurred. Please try again.').show();
                    }
                });
            });
            
            // Handle generate receipt button
            $('#generate-receipt').click(function() {
                const issueId = $('#return-issue-id').val();
                if (issueId) {
                    generateReceipt(issueId);
                } else {
                    alert('No loan selected for receipt generation.');
                }
            });
            
            // Generate receipt
            function generateReceipt(issueId) {
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=get_receipt_data',
                    type: 'POST',
                    data: { issue_id: issueId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            
                            // Populate receipt modal
                            $('#receipt-date').text(new Date().toLocaleDateString());
                            $('#receipt-member').text(data.member_name);
                            $('#receipt-book').text(data.book_title);
                            $('#receipt-issue-date').text(data.issue_date);
                            $('#receipt-due-date').text(data.due_date);
                            $('#receipt-return-date').text(data.return_date || 'Not Returned');
                            $('#receipt-id').text('LIB-' + issueId + '-' + new Date().getTime().toString().substr(-6));
                            
                            // Show fine section if applicable
                            if (data.fine_amount && parseFloat(data.fine_amount) > 0) {
                                $('#receipt-fine-amount').text('$' + parseFloat(data.fine_amount).toFixed(2));
                                $('#receipt-fine-reason').text(data.fine_reason || 'Overdue');
                                $('#receipt-fine-status').text(data.fine_paid ? 'Paid' : 'Pending');
                                $('#receipt-fine-section').show();
                            } else {
                                $('#receipt-fine-section').hide();
                            }
                            
                            // Show receipt modal
                            $('#receiptModal').modal('show');
                        } else {
                            alert('Error generating receipt: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error generating receipt. Please try again.');
                    }
                });
            }
            
          


            // Process renew from all loans table
            $(document).on('click', '.process-renew', function() {
                const issueId = $(this).data('id');
                
                // Switch to renew tab
                $('#renew-tab').tab('show');
                
                // Fetch issue details and populate renew form
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=get_issue_details',
                    type: 'POST',
                    data: { issue_id: issueId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            const issue = response.data;
                            
                            // Populate issue details
                            let issueHtml = `
                                <p><strong>Book:</strong> ${issue.book_title}</p>
                                <p><strong>Member:</strong> ${issue.member_name}</p>
                                <p><strong>Issue Date:</strong> ${issue.issue_date}</p>
                                <p><strong>Due Date:</strong> ${issue.due_date}</p>
                                <p><strong>Status:</strong> <span class="status-${issue.status}">${issue.status.toUpperCase()}</span></p>
                            `;
                            
                            $('#renew-issue-details').html(issueHtml);
                            
                            // Set hidden fields
                            $('#renew-issue-id').val(issue.issue_id);
                            
                            // Set default new due date (current due date + 14 days)
                            const dueDate = new Date(issue.due_date);
                            const newDueDate = new Date(dueDate.getTime() + 14 * 24 * 60 * 60 * 1000);
                            $('#new-due-date').val(newDueDate.toISOString().split('T')[0]);
                            
                            // Show renew details
                            $('#renew-details').show();
                            $('#renew-result').hide();
                        } else {
                            alert('Error getting issue details: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error getting issue details. Please try again.');
                    }
                });
            });

            // Process renew form submission
            $('#renew-form').submit(function(e) {
                e.preventDefault();
                
                const formData = $(this).serialize();
                
                $.ajax({
                    url: '../../Backend/admin/loans/loan_handler.php?action=renew_loan',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#renew-result').removeClass('alert-danger').addClass('alert-success')
                                .html(`<strong>Success!</strong> ${response.message}`).show();
                            
                            // Reset form
                            $('#renew-form')[0].reset();
                            $('#renew-details').hide();
                            
                            // Reload all loans table
                            loadAllLoans();
                        } else {
                            $('#renew-result').removeClass('alert-success').addClass('alert-danger')
                                .html(`<strong>Error!</strong> ${response.message}`).show();
                        }
                    },
                    error: function() {
                        $('#renew-result').removeClass('alert-success').addClass('alert-danger')
                            .html('<strong>Error!</strong> An unexpected error occurred. Please try again.').show();
                    }
                });
            });

            // Handle reservation actions
            $(document).on('click', '.fulfill-reservation', function() {
                const reservationId = $(this).data('id');
                
                $.ajax({
                    url: '../../Backend/admin/loans/reservation_handler.php?action=fulfill_reservation',
                    type: 'POST',
                    data: { reservation_id: reservationId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            loadReservations();
                            loadAllLoans();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error processing reservation. Please try again.');
                    }
                });
            });

            $(document).on('click', '.cancel-reservation', function() {
                if (confirm('Are you sure you want to cancel this reservation?')) {
                    const reservationId = $(this).data('id');
                    
                    $.ajax({
                        url: '../../Backend/admin/loans/reservation_handler.php?action=cancel_reservation',
                        type: 'POST',
                        data: { reservation_id: reservationId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                alert(response.message);
                                loadReservations();
                            } else {
                                alert('Error: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Error cancelling reservation. Please try again.');
                        }
                    });
                }
            });

            // Export loans data
            $('#export-loans').click(function() {
                window.location.href = '../../Backend/admin/loans/loan_handler.php?action=export_loans';
            });
        });
    </script>
</body>
</html>