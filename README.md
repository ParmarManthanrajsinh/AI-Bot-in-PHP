# AI Chat Bot with Analytics Dashboard

## Abstract

This project is a comprehensive AI-powered chat bot system with an integrated analytics dashboard. It leverages the Gemini AI model to provide intelligent responses to user queries while collecting and analyzing interaction data. The system includes a secure admin interface for monitoring usage patterns, performance metrics, and user engagement statistics.

## Introduction

### Background

AI chat bots have become essential tools for businesses and organizations seeking to provide immediate assistance to users. This project addresses the need for an accessible, web-based AI assistant that not only delivers helpful responses but also provides administrators with valuable insights into user interactions.

### Objectives

- Create a responsive, user-friendly chat interface for interacting with the Gemini AI model
- Implement comprehensive analytics tracking for all user interactions
- Develop a secure admin dashboard for monitoring system performance and usage patterns
- Ensure data privacy and security through proper authentication mechanisms
- Provide detailed metrics on API performance and user engagement

### Intended Use

This system is designed for:
- Customer support automation
- Information retrieval assistance
- User behavior analysis and pattern recognition
- Performance monitoring of AI model responses
- Data-driven decision making through analytics

## Modules

### 1. Chat Interface Module

The core user-facing component that provides a clean, intuitive interface for interacting with the AI. Features include:
- Real-time message display with proper formatting
- Code syntax highlighting for technical responses
- Message history persistence
- Responsive design for mobile and desktop use

### 2. API Connection Module

Handles communication with the Gemini AI model through Google's Generative Language API. This module:
- Manages API authentication and requests
- Formats user queries for optimal AI processing
- Parses and processes AI responses
- Tracks API performance metrics including response time and success rates

### 3. Analytics Module

Captures and processes user interaction data to generate meaningful insights. Functionality includes:
- User session tracking with unique identifiers
- Interaction logging (queries, responses, timestamps)
- Device and browser detection
- Geographic location tracking (IP-based)
- Response time measurement

### 4. Admin Dashboard Module

Provides administrators with a secure interface to monitor system performance and user engagement. Features include:
- User authentication and access control
- Interactive data visualizations
- Performance metrics reporting
- Error logging and monitoring
- User interaction history

### 5. Database Module

Manages data persistence across the application with tables for:
- Chat messages history
- User interaction logs
- API performance metrics
- Admin user accounts
- Session data

## Installation Guide

Follow these steps to set up the AI Chat Bot with Analytics Dashboard on your local development environment.

### Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Google Generative Language API key

### Step 1: Clone the Repository

```bash
git clone https://github.com/yourusername/ai-bot.git
cd ai-bot
```

### Step 2: Database Setup

1. Create a new MySQL database for the project

```sql
CREATE DATABASE ai_bot_db;
```

2. Import the database schema

```bash
mysql -u username -p ai_bot_db < database/schema.sql
```

### Step 3: API Configuration

1. Obtain a Google Generative Language API key from the [Google AI Studio](https://ai.google.dev/)
2. Add your API key to the configuration file

```php
// API configuration
$api_config = [
    'gemini_api_key' => 'YOUR_GEMINI_API_KEY',
    'model' => 'gemini-pro'
];
```

### Step 4: Web Server Configuration

#### For Apache (with WAMP/XAMPP)

Ensure your project is in the web server's document root (e.g., `www` or `htdocs` folder).

### Step 5: Launch the Application

1. Start your web server and MySQL services
2. Open your browser and navigate to:
   - http://localhost/ai-bot (for WAMP/XAMPP)
   - http://ai-bot.local (if using custom domain configuration)

### Step 6: Admin Dashboard Access

1. Navigate to http://localhost/ai-bot/admin
2. Log in with the default credentials:
   - Username: `admin`
   - Password: `admin123`
3. **Important**: Change the default password immediately after first login

### Troubleshooting

- **Database Connection Issues**: Verify your database credentials and ensure MySQL service is running
- **API Key Errors**: Confirm your Gemini API key is valid and has the necessary permissions
- **Permission Problems**: Ensure the web server has read/write permissions for the project directory

## Coding

### Core Chat Processing Logic

Below is the core logic for processing user input and generating AI responses with analytics tracking:

```php
// Process user input with analytics tracking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['message'])) {
        $message = trim($_POST['message']);

        if (!empty($message)) {
            // Insert user message into database with analytics
            insertMessage($conn, 'user', $message, $userId, $sessionId);

            // Get Gemini response with analytics tracking
            $response = getGeminiResponse($message, $apiKey, $model, $userId, $sessionId);

            // Insert assistant response into database with analytics
            insertMessage($conn, 'assistant', $response, $userId, $sessionId, $responseTime);
        }
    }
}

// Gemini API function with performance tracking
function getGeminiResponse($message, $apiKey, $model, $userId = null, $sessionId = null) {
    // Start timing for response time measurement
    $startTime = microtime(true);

    $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$apiKey";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $message]
                ]
            ]
        ]
    ];

    // API request handling and response processing
    // ...

    // Calculate response time
    $endTime = microtime(true);
    $responseTime = $endTime - $startTime;

    // Log API performance
    logApiPerformance($responseTime, $httpCode, $model);

    return $responseText;
}
```

This core logic demonstrates how the system processes user input, communicates with the Gemini AI API, and tracks performance metrics for analytics purposes.

### Analytics Tracking System

The analytics module captures detailed user interaction data for comprehensive insights:

```php
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

// Log API performance metrics
function logApiPerformance(float $responseTime, int $httpCode, string $model): void
{
    global $conn;

    // Only log if we have a database connection
    if (!isset($conn) || !($conn instanceof mysqli)) {
        return;
    }

    $isSuccess = ($httpCode === 200) ? 1 : 0;

    // Log performance data
    $stmt = $conn->prepare("INSERT INTO api_performance (model, response_time, http_code, is_success) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sdii", $model, $responseTime, $httpCode, $isSuccess);
    $stmt->execute();
    $stmt->close();
}
```

### Admin Dashboard Metrics

The admin dashboard provides comprehensive analytics through SQL queries that generate insightful metrics:

```php
// Get summary metrics for dashboard
$metrics = [
    'total_users' => 0,
    'new_users' => 0,
    'total_sessions' => 0,
    'total_interactions' => 0,
    'avg_response_time' => 0,
    'error_count' => 0
];

// Total users
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users");
$stmt->execute();
$metrics['total_users'] = $stmt->get_result()->fetch_assoc()['count'];

// New users in date range
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE DATE(first_seen) BETWEEN ? AND ?");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$metrics['new_users'] = $stmt->get_result()->fetch_assoc()['count'];

// Average response time in date range
$stmt = $conn->prepare("SELECT AVG(response_time) as avg_time FROM chat_messages WHERE DATE(timestamp) BETWEEN ? AND ? AND role = 'assistant' AND response_time IS NOT NULL");
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();
$metrics['avg_response_time'] = $result['avg_time'] ?? 0;
```

These code snippets demonstrate the system's ability to track user interactions, measure API performance, and generate meaningful analytics for administrative oversight.

### Chat Interface Implementation

The chat interface combines responsive design with real-time message handling:

```php
// Modified insert function with analytics tracking
function insertMessage($conn, $role, $content, $userId = null, $sessionId = null, $responseTime = null)
{
    // If analytics is enabled and we have user/session IDs
    if ($userId && $sessionId) {
        return logInteraction($conn, $role, $content, $userId, $sessionId, $responseTime);
    } else {
        // Legacy insert without analytics
        $stmt = $conn->prepare("INSERT INTO chat_messages (role, content) VALUES (?, ?)");
        $stmt->bind_param("ss", $role, $content);
        if (!$stmt->execute()) {
            error_log("Error inserting message: " . $stmt->execute());
        }
        $stmt->close();
        return $conn->insert_id;
    }
}
```

The system uses a combination of server-side processing, client-side rendering, and database operations to create a seamless chat experience while collecting valuable analytics data.