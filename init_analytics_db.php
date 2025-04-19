<?php
/**
 * Analytics Database Initialization Script
 * 
 * This script checks for and creates the necessary tables for the analytics system.
 * It should be included at the beginning of analytics.php to ensure all required tables exist.
 */

// Ensure we have a database connection
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once 'db_connect.php';
}

// Check if users table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'users'");
if ($tableCheck->num_rows === 0) {
    // Create users table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS `users` (
        `user_id` varchar(64) NOT NULL,
        `first_seen` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `last_seen` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        `user_agent` varchar(255) DEFAULT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `session_count` int DEFAULT 0,
        PRIMARY KEY (`user_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");
}

// Check if sessions table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'sessions'");
if ($tableCheck->num_rows === 0) {
    // Create sessions table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS `sessions` (
        `session_id` varchar(64) NOT NULL,
        `user_id` varchar(64) NOT NULL,
        `start_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `end_time` timestamp NULL DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT 1,
        `device_type` varchar(50) DEFAULT NULL,
        `browser` varchar(50) DEFAULT NULL,
        PRIMARY KEY (`session_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");
}

// Check if error_logs table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'error_logs'");
if ($tableCheck->num_rows === 0) {
    // Create error_logs table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS `error_logs` (
        `error_id` int NOT NULL AUTO_INCREMENT,
        `user_id` varchar(64) DEFAULT NULL,
        `session_id` varchar(64) DEFAULT NULL,
        `error_type` varchar(50) DEFAULT NULL,
        `error_message` text,
        `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`error_id`),
        KEY `user_id` (`user_id`),
        KEY `session_id` (`session_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;");
}