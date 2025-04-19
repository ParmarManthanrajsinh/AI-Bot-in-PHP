<?php
include "db_connect.php";
include "api_connect.php";
include "analytics.php"; // Include analytics module

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

function deleteAllMessage($conn)
{
    $stmt = $conn->prepare("TRUNCATE TABLE `ai-chat`.`chat_messages`");
    if (!$stmt->execute()) {
        error_log("Error inserting message: " . $stmt->execute());
    }
    $stmt->close();
}

// Initialize analytics
$analytics = initAnalytics($conn);
$userId = $analytics['user_id'];
$sessionId = $analytics['session_id'];

// Process user input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['message'])) {
        $message = trim($_POST['message']);

        if (!empty($message)) {
            // Insert user message into database with analytics
            insertMessage($conn, 'user', $message, $userId, $sessionId);

            // Get Gemini response with analytics tracking
            $response = getGeminiResponse($message, $apiKey, $model, $userId, $sessionId);

            // Calculate response time (approximate since we can't measure exact API time here)
            $responseTime = null; // This is now handled inside getGeminiResponse

            // Insert assistant response into database with analytics
            insertMessage($conn, 'assistant', $response, $userId, $sessionId, $responseTime);
        }
    }

    if (isset($_POST['delete'])) {
        deleteAllMessage($conn);
    }

    if (isset($_POST['admin'])) {
        header("Location: admin\login.php");
    }
}

// Retrieve all chat history from database
$chatHistory = [];
$result = $conn->query("SELECT role, content FROM chat_messages ORDER BY timestamp ASC");
while ($row = $result->fetch_assoc()) {
    $chatHistory[] = $row;
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Chat Bot</title>

    <!-- Enhanced Google Fonts for professional typography -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1 class="text-center my-4">AI Chat Assistant</h1>
        <div class="chat-container">
            <div class="chat-history">
                <?php
                foreach ($chatHistory as $entry) {

                    // prevents malicious code injection.
                    $role = htmlspecialchars($entry['role']);
                    // converts newline characters into HTML line breaks
                    $content = nl2br(htmlspecialchars($entry['content']));

                    echo "<div class=\"chat-message {$role}\">";
                    echo "<div class=\"message-bubble\">{$content}</div>";
                    echo "</div>";
                }
                ?>
            </div>
            <form method="POST" class="chat-input">
                <div class="input-group">
                    <input type="text" name="message" class="form-control" placeholder="Type your message..." required>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-1"></i> Send</button>
                </div>
            </form>

            <div class="d-flex justify-content-center">
                <form method="POST" class="delete-chat-container">
                    <button type="submit" class="btn btn-danger" name="delete"><i class="fas fa-trash-alt me-1"></i> Clear Chat</button>
                    <button type="submit" class="btn btn-success" name="admin"><i class="fas fa-lock me-2"></i>Admin Login</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-scroll chat history to bottom on load with smooth animation
        const chatHistoryDiv = document.querySelector('.chat-history');
        chatHistoryDiv.scrollTop = chatHistoryDiv.scrollHeight;

        // Add focus to input field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="message"]').focus();
        });

        // Add smooth scrolling when new messages are added
        const form = document.querySelector('.chat-input');
        form.addEventListener('submit', function() {
            // Add a small delay to ensure the DOM is updated with the new message
            setTimeout(function() {
                chatHistoryDiv.scrollTo({
                    top: chatHistoryDiv.scrollHeight,
                    behavior: 'smooth'
                });
            }, 100);
        });
    </script>
</body>

</html>