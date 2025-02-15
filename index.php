<?php
session_start();

// Database Configuration
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'ai-chat';

// Create connection
$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate unique session ID for chat history
if (!isset($_SESSION['chat_session_id'])) {
    $_SESSION['chat_session_id'] = uniqid();
}
$sessionId = $_SESSION['chat_session_id'];

// API Configuration
$apiKey = 'AIzaSyBqDbUeIzw_v5IMDEQ5FVXyG17bmNVLYNw'; // Replace with your actual API key
$model = 'gemini-pro';

// Process user input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);

    if (!empty($message)) {
        // Insert user message into database
        insertMessage($conn, $sessionId, 'user', $message);

        // Get Gemini response
        $response = getGeminiResponse($message, $apiKey, $model);

        // Insert assistant response into database
        insertMessage($conn, $sessionId, 'assistant', $response);
    }
}

// Function to insert messages into database
function insertMessage($conn, $sessionId, $role, $content)
{
    $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, role, content) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $sessionId, $role, $content);
    if (!$stmt->execute()) {
        error_log("Error inserting message: " . $stmt->error);
    }
    $stmt->close();
}

// Retrieve chat history from database
$chatHistory = [];
$stmt = $conn->prepare("SELECT role, content FROM chat_messages WHERE session_id = ? ORDER BY timestamp ASC");
$stmt->bind_param("s", $sessionId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $chatHistory[] = $row;
}
$stmt->close();

// Close database connection
$conn->close();

// Gemini API function remains unchanged
function getGeminiResponse($message, $apiKey, $model)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";

    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $message]
                ]
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
        ],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Debugging output (remove in production)
    /*
    error_log("API Response:");
    error_log(print_r($response, true));
    error_log("HTTP Code: " . $httpCode);
    */

    if ($errno) {
        return "cURL Error ({$errno}): " . $error;
    }

    if ($httpCode !== 200) {
        return "API Error: HTTP {$httpCode} - " . ($response ?: 'No response');
    }

    $responseData = json_decode($response, true);

    if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
        return $responseData['candidates'][0]['content']['parts'][0]['text'];
    }

    // Enhanced error logging
    $errorMessage = 'Unknown error structure';
    if (isset($responseData['error']['message'])) {
        $errorMessage = $responseData['error']['message'];
    } elseif (isset($responseData['error'])) {
        $errorMessage = json_encode($responseData['error']);
    }

    return "API Error: " . $errorMessage;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Bot</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #121212; color: #ffffff; }
        .chat-container { max-width: 600px; margin: auto; background-color: #1e1e1e; padding: 15px; border-radius: 10px; }
        .chat-history { max-height: 400px; min-height: 300px; overflow-y: auto; padding: 15px; border: 1px solid #333; border-radius: 5px; background-color: #252525; }
        .message { padding: 10px; border-radius: 10px; margin-bottom: 10px; }
        .user { background-color: #007bff; color: white; align-self: flex-end; }
        .assistant { background-color: #444; color: white; align-self: flex-start; }
        .chat-input { margin-top: 15px; }
        .form-control, .btn { background-color: #333; color: white; border: 1px solid #555; }
        .btn-primary { background-color: #007bff; border: none; }
        .btn-primary:hover { background-color: #0056b3; }
    </style>
</head
<body>
    <div class="container my-5">
        <h1 class="mb-4 text-center">AI Chat Bot</h1>
        <div class="chat-container d-flex flex-column">
            <div class="chat-history d-flex flex-column">
                <?php foreach ($chatHistory as $entry): ?>
                    <div class="message <?= $entry['role'] ?> align-self-<?= $entry['role'] === 'user' ? 'end' : 'start' ?>">
                        <strong><?= ucfirst($entry['role']) ?>:</strong>
                        <?= nl2br(htmlspecialchars($entry['content'])) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="POST" class="chat-input">
                <div class="input-group">
                    <input type="text" name="message" class="form-control" placeholder="Type your message..." required>
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>