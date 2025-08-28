<?php
session_start();

// Check if a session user exists first (server-side authentication)
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'admin') {
    // Redirect to login page with lowercase path
    header('Location: /library_management_system/Frontend/Admin/login.html');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
    <script>
    // Check localStorage as well (client-side authentication)
    document.addEventListener('DOMContentLoaded', function() {
        const user = JSON.parse(localStorage.getItem('libraryUser') || '{}');
        if (!user.loggedIn || user.role !== 'admin') {
            window.location.href = '/library_management_system/Frontend/Admin/login.html';
        }
    });
    </script>
</head>
<body>
    <h1>Welcome Admin!</h1>
    <!-- Dashboard content here -->
</body>
</html>