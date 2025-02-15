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

// Gemini API function with combined cURL options
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
        CURLOPT_CAINFO => __DIR__ . '/cacert.pem',
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AI Chat Bot</title>
  <!-- Google Font for modern look -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- External CSS file -->
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="container">
    <h1 class="text-center my-4">AI Chat Bot</h1>
    <div class="chat-container">
      <div class="chat-history">
        <?php foreach ($chatHistory as $entry): ?>
          <div class="chat-message <?= $entry['role'] ?>">
            <?php if ($entry['role'] === 'assistant'): ?>
              <div class="avatar">A</div>
            <?php endif; ?>
            <div class="message-bubble">
              <strong><?= ucfirst($entry['role']) ?>:</strong><br>
              <?= nl2br(htmlspecialchars($entry['content'])) ?>
            </div>
            <?php if ($entry['role'] === 'user'): ?>
              <div class="avatar">U</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <form method="POST" class="chat-input">
        <div class="input-group">
          <input type="text" name="message" class="form-control" placeholder="Type your message..." required autocomplete="off">
          <button type="submit" class="btn btn-primary">Send</button>
        </div>
      </form>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-scroll chat history to bottom on load
    const chatHistoryDiv = document.querySelector('.chat-history');
    chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;
  </script>
</body>
</html>
