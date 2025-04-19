<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    // Not logged in, redirect to login page
    header('Location: login.php');
    exit;
}

// Admin is authenticated, continue with page
?>