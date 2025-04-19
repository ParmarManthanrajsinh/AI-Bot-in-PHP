<?php
session_start();

/**
 * Admin Authentication Check
 * 
 * This file verifies that a user is logged in as an admin before allowing
 * access to protected admin pages. If not logged in, redirects to login page.
 */

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Not logged in, redirect to login page
    header('Location: login.php');
    exit;
}

// Admin is authenticated, continue with page
?>