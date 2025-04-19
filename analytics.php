<?php

/**
 * Analytics Module for AI Bot
 * 
 * This file implements user tracking, session management, and interaction logging
 * for the AI Bot analytics system.
 **/

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connect.php';
require_once 'init_analytics_db.php';

// Get or create a unique user identifier
function getUserId(): string
{
    // Check if user ID exists in session
    if (!isset($_SESSION['user_id'])) {
        // Generate a new user ID
        $_SESSION['user_id'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['user_id'];
}

// Get or create a unique session identifier
function getSessionId(): string
{
    // Check if session ID exists
    if (!isset($_SESSION['analytics_session_id'])) {
        // Generate a new session ID
        $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
        // Record session start time
        $_SESSION['session_start_time'] = time();
    }
    return $_SESSION['analytics_session_id'];
}

// Get user's IP address
function getIpAddress(): string
{
    // Check various server variables for IP address
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    return $ip;
}

// Get user agent information
function getUserAgentInfo(): array
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Default values
    $info = [
        'user_agent' => $userAgent,
        'device_type' => 'unknown',
        'browser' => 'unknown'
    ];

    // Detect device type
    if (preg_match('/(android|iphone|ipad|ipod|blackberry|windows phone)/i', $userAgent)) {
        $info['device_type'] = 'mobile';
    } elseif (preg_match('/(tablet|ipad)/i', $userAgent)) {
        $info['device_type'] = 'tablet';
    } else {
        $info['device_type'] = 'desktop';
    }

    // Detect browser
    if (preg_match('/MSIE|Trident/i', $userAgent)) {
        $info['browser'] = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $userAgent)) {
        $info['browser'] = 'Firefox';
    } elseif (preg_match('/Chrome/i', $userAgent)) {
        $info['browser'] = 'Chrome';
    } elseif (preg_match('/Safari/i', $userAgent)) {
        $info['browser'] = 'Safari';
    } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
        $info['browser'] = 'Opera';
    } elseif (preg_match('/Edge/i', $userAgent)) {
        $info['browser'] = 'Edge';
    }

    return $info;
}

// Track user in the database
function trackUser($conn): string
{
    $userId = getUserId();
    $userInfo = getUserAgentInfo();
    $ipAddress = getIpAddress();

    // Check if user exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // New user - insert record
        $stmt = $conn->prepare("INSERT INTO users (user_id, user_agent, ip_address) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $userId, $userInfo['user_agent'], $ipAddress);
    } else {
        // Existing user - update last seen
        $stmt = $conn->prepare("UPDATE users SET last_seen = CURRENT_TIMESTAMP, ip_address = ? WHERE user_id = ?");
        $stmt->bind_param("ss", $ipAddress, $userId);
    }

    $stmt->execute();
    $stmt->close();

    return $userId;
}

// Track session in the database
function trackSession($conn, $userId): string
{
    $sessionId = getSessionId();
    $userInfo = getUserAgentInfo();

    // Check if session exists
    $stmt = $conn->prepare("SELECT session_id FROM sessions WHERE session_id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // New session - insert record
        $stmt = $conn->prepare("INSERT INTO sessions (session_id, user_id, device_type, browser) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $sessionId, $userId, $userInfo['device_type'], $userInfo['browser']);
        $stmt->execute();

        // Update user's session count
        $stmt = $conn->prepare("UPDATE users SET session_count = session_count + 1 WHERE user_id = ?");
        $stmt->bind_param("s", $userId);
        $stmt->execute();
    }

    $stmt->close();

    return $sessionId;
}

// Log interaction in the database
function logInteraction(mysqli $conn, string $role, string $content, string $userId, string $sessionId, $responseTime = null): bool|int
{
    $ipAddress = getIpAddress();

    // Estimate token count (very rough approximation)
    $tokenCount = ceil(str_word_count($content) * 1.3);

    $stmt = $conn->prepare("INSERT INTO chat_messages (role, content, user_id, session_id, response_time, token_count, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssdis", $role, $content, $userId, $sessionId, $responseTime, $tokenCount, $ipAddress);

    if (!$stmt->execute()) {
        error_log("Error logging interaction: " . $stmt->error);
        $stmt->close();
        return false;
    }

    $messageId = $conn->insert_id;
    $stmt->close();

    return $messageId;
}

// Log an error in the database
function logError(mysqli $conn, string $errorType, string $errorMessage, $userId = null, $sessionId = null): void
{
    if ($userId === null) {
        $userId = getUserId();
    }

    if ($sessionId === null) {
        $sessionId = getSessionId();
    }

    $stmt = $conn->prepare("INSERT INTO error_logs (user_id, session_id, error_type, error_message) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $userId, $sessionId, $errorType, $errorMessage);

    if (!$stmt->execute()) {
        error_log("Error logging error: " . $stmt->error);
    }

    $stmt->close();
}

// Update daily metrics
function updateDailyMetrics(mysqli $conn): void
{
    $today = date('Y-m-d');

    // Check if today's record exists
    $stmt = $conn->prepare("SELECT date FROM metrics_daily WHERE date = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Create new record for today
        $stmt = $conn->prepare("INSERT INTO metrics_daily (date) VALUES (?)");
        $stmt->bind_param("s", $today);
        $stmt->execute();
    }

    // Count new users today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(first_seen) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $newUsers = $stmt->get_result()->fetch_assoc()['count'];

    // Count total users active today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(last_seen) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $totalUsers = $stmt->get_result()->fetch_assoc()['count'];

    // Count total sessions today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM sessions WHERE DATE(start_time) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $totalSessions = $stmt->get_result()->fetch_assoc()['count'];

    // Count total interactions today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE DATE(timestamp) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $totalInteractions = $stmt->get_result()->fetch_assoc()['count'];

    // Calculate average response time today
    $stmt = $conn->prepare("SELECT AVG(response_time) as avg_time FROM chat_messages WHERE DATE(timestamp) = ? AND role = 'assistant' AND response_time IS NOT NULL");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $avgResponseTime = $stmt->get_result()->fetch_assoc()['avg_time'] ?? 0;

    // Count errors today
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM error_logs WHERE DATE(timestamp) = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $errorCount = $stmt->get_result()->fetch_assoc()['count'];

    // Update metrics
    $stmt = $conn->prepare("UPDATE metrics_daily SET 
        total_users = ?, 
        new_users = ?, 
        total_sessions = ?, 
        total_interactions = ?, 
        avg_response_time = ?, 
        error_count = ? 
        WHERE date = ?");
    $stmt->bind_param("iiiiids", $totalUsers, $newUsers, $totalSessions, $totalInteractions, $avgResponseTime, $errorCount, $today);
    $stmt->execute();

    $stmt->close();
}

// Initialize analytics tracking
function initAnalytics($conn): array
{
    $userId = trackUser($conn);
    $sessionId = trackSession($conn, $userId);

    // Update metrics once per session
    if (!isset($_SESSION['metrics_updated_today']) || $_SESSION['metrics_updated_today'] !== date('Y-m-d')) {
        updateDailyMetrics($conn);
        $_SESSION['metrics_updated_today'] = date('Y-m-d');
    }

    return [
        'user_id' => $userId,
        'session_id' => $sessionId
    ];
}
