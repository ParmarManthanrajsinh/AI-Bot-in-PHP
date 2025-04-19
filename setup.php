<?php
/**
 * Database Setup Script for AI Bot
 * 
 * This script initializes the database tables required for the AI Bot application.
 * Run this script once to set up the database structure.
 */

// Include database connection
require_once 'db_connect.php';

// Check if connection is successful
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

echo "<h2>AI Bot Database Setup</h2>";

// Read the SQL setup file
$sql = file_get_contents('analytics_setup.sql');

if (!$sql) {
    die("Error reading setup file: analytics_setup.sql not found or empty.");
}

// Execute the SQL commands
echo "<p>Executing database setup script...</p>";

// Enable multi query execution
if ($conn->multi_query($sql)) {
    echo "<p style='color: green;'>Database setup completed successfully!</p>";
    
    // Process all result sets to clear them
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    
} else {
    echo "<p style='color: red;'>Error setting up database: " . $conn->error . "</p>";
}

echo "<p>You can now <a href='index.php'>return to the application</a>.</p>";

// Close the connection
$conn->close();