<?php


session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.html');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback & Support Management - Library Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 48px 0 0;
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
        }
        
        .sidebar .nav-link {
            font-weight: 500;
            color: #333;
        }
        
        .sidebar .nav-link.active {
            color: #2470dc;
        }
        
        .sidebar-heading {
            font-size: .75rem;
            text-transform: uppercase;
        }
        
        .navbar-brand {
            padding-top: .75rem;
            padding-bottom: .75rem;
            font-size: 1rem;
            background-color: rgba(0, 0, 0, .25);
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .25);
        }
        
        .ticket-priority-high {
            background-color: #ffebee;
        }
        
        .ticket-priority-medium {
            background-color: #fff8e1;
        }
        
        .ticket-priority-low {
            background-color: #f1f8e9;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-in_progress {
            background-color: #17a2b8;
            color: white;
        }
        
        .status-resolved {
            background-color: #28a745;
            color: white;
        }
        
        .status-closed {
            background-color: #6c757d;
            color: white;
        }
        
        .ticket-detail-header {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .ticket-type-badge {
            display: inline-block;
            padding: 0.25em 0.6em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
            margin-right: 10px;
        }
        
        .ticket-type-feedback {
            background-color: #e3f2fd;
            color: #0d6efd;
        }
        
        .ticket-type-support {
            background-color: #f8d7da;
            color: #dc3545;
        }
        .head {
            margin-left: 20px;
        }

    </style>
</head>
<body>
<header>
    <div class="head">
        <div class="d-flex align-items-center">
            <a href="../index.html" class="back-link me-3">
                <i class="bi bi-arrow-left"></i>
            </a>
            <div class="logo">
                <h1><i class="fas fa-book"></i> Home</h1>
            </div>
        </div>
    </div>
</header>


        <main class="col-12 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Feedback & Support Management</h1>
            </div>
                
                <!-- Tabs for different ticket types -->
                <ul class="nav nav-tabs mb-4" id="ticketTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab" aria-controls="all" aria-selected="true">All Tickets</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="false">Pending</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="in-progress-tab" data-bs-toggle="tab" data-bs-target="#in-progress" type="button" role="tab" aria-controls="in-progress" aria-selected="false">In Progress</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="resolved-tab" data-bs-toggle="tab" data-bs-target="#resolved" type="button" role="tab" aria-controls="resolved" aria-selected="false">Resolved</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="closed-tab" data-bs-toggle="tab" data-bs-target="#closed" type="button" role="tab" aria-controls="closed" aria-selected="false">Closed</button>
                    </li>
                </ul>
                
                <!-- Tab content -->
                <div class="tab-content" id="ticketTabsContent">
                    <div class="tab-pane fade show active" id="all" role="tabpanel" aria-labelledby="all-tab">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="tickets-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Subject</th>
                                        <th>Name</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Priority</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Will be populated by AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Other tabs will use JS to filter the main table -->
                    <!-- <div class="tab-pane fade" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                      
                    </div>
                    <div class="tab-pane fade" id="in-progress" role="tabpanel" aria-labelledby="in-progress-tab">
                        
                    </div>
                    <div class="tab-pane fade" id="resolved" role="tabpanel" aria-labelledby="resolved-tab">
                        
                    </div>
                    <div class="tab-pane fade" id="closed" role="tabpanel" aria-labelledby="closed-tab">
                        
                    </div> -->
                </div>
                
                <!-- Ticket Detail Modal -->
                <div class="modal fade" id="ticketDetailModal" tabindex="-1" aria-labelledby="ticketDetailModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="ticketDetailModalLabel">Ticket Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="ticket-detail-header">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h4 id="ticket-subject"></h4>
                                            <div>
                                                <span id="ticket-type-badge" class="ticket-type-badge"></span>
                                                <span id="ticket-status-badge" class="status-badge"></span>
                                            </div>
                                            <p class="mt-2 mb-0">From: <strong id="ticket-name"></strong> (<span id="ticket-email"></span>)</p>
                                            <p class="mb-0">Date: <span id="ticket-date"></span></p>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="ticket-priority" class="form-label">Priority</label>
                                                <select class="form-select" id="ticket-priority">
                                                    <option value="low">Low</option>
                                                    <option value="medium">Medium</option>
                                                    <option value="high">High</option>
                                                </select>
                                            </div>
                                            <div class="form-group mt-2">
                                                <label for="ticket-status" class="form-label">Status</label>
                                                <select class="form-select" id="ticket-status">
                                                    <option value="pending">Pending</option>
                                                    <option value="in_progress">In Progress</option>
                                                    <option value="resolved">Resolved</option>
                                                    <option value="closed">Closed</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5>Message</h5>
                                        <div class="card mb-3">
                                            <div class="card-body" id="ticket-message"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-12">
                                        <h5>Response</h5>
                                        <textarea id="admin-response" class="form-control" rows="5" placeholder="Enter your response here..."></textarea>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                <button type="button" class="btn btn-primary" id="save-response-btn">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            const ticketsTable = $('#tickets-table').DataTable({
                responsive: true,
                order: [[4, 'desc']], // Sort by date (newest first)
                columnDefs: [
                    { orderable: false, targets: 7 } // No sorting for actions column
                ]
            });
            
            // Load tickets
            loadTickets();
            
            // Tab change handler
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                const targetId = e.target.getAttribute('data-bs-target').substring(1); // Remove the # character
                filterTableByStatus(targetId);
            });
            
            // Save response button click handler
            $('#save-response-btn').click(function() {
                updateTicket();
            });
            
            // Load tickets function
            function loadTickets() {
                $.ajax({
                    url: '../../Backend/admin/get_tickets.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            populateTicketsTable(response.tickets);
                        } else {
                            alert('Error loading tickets: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error loading tickets. Please try again.');
                    }
                });
            }
            
            // Function to populate tickets table
            function populateTicketsTable(tickets) {
                // Clear existing data
                ticketsTable.clear();
                
                // Add new data
                $.each(tickets, function(index, ticket) {
                    // Format status badge
                    let statusBadge = `<span class="status-badge status-${ticket.status}">${formatStatus(ticket.status)}</span>`;
                    
                    // Create type badge
                    let typeBadge = `<span class="ticket-type-badge ticket-type-${ticket.type}">${ticket.type === 'feedback' ? 'Feedback' : 'Support'}</span>`;
                    
                    // Create actions buttons
                    let actions = `
                        <button class="btn btn-sm btn-primary view-ticket" data-id="${ticket.ticket_id}">View</button>
                    `;
                    
                    // Add row to table
                    ticketsTable.row.add([
                        `#${ticket.ticket_id}`,
                        typeBadge,
                        ticket.subject,
                        ticket.name,
                        formatDate(ticket.created_at),
                        statusBadge,
                        formatPriority(ticket.priority),
                        actions
                    ]);
                });
                
                // Draw the table
                ticketsTable.draw();
                
                // Apply row classes based on priority
                $('#tickets-table tbody tr').each(function(index) {
                    const priorityText = $(this).find('td:eq(6)').text().toLowerCase().trim();
                    $(this).addClass(`ticket-priority-${priorityText}`);
                });
                
                // Show the active tab
                const activeTabId = $('.nav-tabs .active').attr('aria-controls');
                filterTableByStatus(activeTabId);
            }
            
            // Filter table by status
            function filterTableByStatus(status) {
                if (status === 'all') {
                    ticketsTable.search('').columns().search('').draw();
                } else {
                    ticketsTable.columns(5).search(formatStatus(status), true, false).draw();
                }
            }
            
            // View ticket click handler
            $(document).on('click', '.view-ticket', function() {
                const ticketId = $(this).data('id');
                
                $.ajax({
                    url: '../../Backend/admin/get_ticket_details.php',
                    type: 'GET',
                    data: { ticket_id: ticketId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            populateTicketModal(response.ticket);
                            $('#ticketDetailModal').modal('show');
                        } else {
                            alert('Error getting ticket details: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error getting ticket details. Please try again.');
                    }
                });
            });
            
            // Function to populate ticket modal
            function populateTicketModal(ticket) {
                // Set the current ticket ID as a data attribute
                $('#ticketDetailModal').data('ticket-id', ticket.ticket_id);
                
                // Populate ticket details
                $('#ticket-subject').text(ticket.subject);
                $('#ticket-name').text(ticket.name);
                $('#ticket-email').text(ticket.email);
                $('#ticket-date').text(formatDate(ticket.created_at));
                $('#ticket-message').text(ticket.message);
                
                // Set the admin response
                $('#admin-response').val(ticket.admin_response || '');
                
                // Set type badge
                $('#ticket-type-badge')
                    .text(ticket.type === 'feedback' ? 'Feedback' : 'Support')
                    .removeClass()
                    .addClass(`ticket-type-badge ticket-type-${ticket.type}`);
                
                // Set status badge
                $('#ticket-status-badge')
                    .text(formatStatus(ticket.status))
                    .removeClass()
                    .addClass(`status-badge status-${ticket.status}`);
                
                // Set dropdowns
                $('#ticket-priority').val(ticket.priority);
                $('#ticket-status').val(ticket.status);
            }
            
            // Update ticket function
            function updateTicket() {
                const ticketId = $('#ticketDetailModal').data('ticket-id');
                const adminResponse = $('#admin-response').val();
                const status = $('#ticket-status').val();
                const priority = $('#ticket-priority').val();
                
                $.ajax({
                    url: '../../Backend/admin/update_ticket.php',
                    type: 'POST',
                    data: {
                        ticket_id: ticketId,
                        admin_response: adminResponse,
                        status: status,
                        priority: priority
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $('#ticketDetailModal').modal('hide');
                            loadTickets(); // Reload tickets list
                        } else {
                            alert('Error updating ticket: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Error updating ticket. Please try again.');
                    }
                });
            }
            
            // Helper functions
            function formatDate(dateString) {
                const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' };
                return new Date(dateString).toLocaleDateString('en-US', options);
            }
            
            function formatStatus(status) {
                switch(status) {
                    case 'pending': return 'Pending';
                    case 'in_progress': return 'In Progress';
                    case 'resolved': return 'Resolved';
                    case 'closed': return 'Closed';
                    default: return status.charAt(0).toUpperCase() + status.slice(1);
                }
            }
            
            function formatPriority(priority) {
                return priority.charAt(0).toUpperCase() + priority.slice(1);
            }
        });
    </script>
</body>
</html>