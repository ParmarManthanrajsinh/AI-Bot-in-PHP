<?php
include "db_connect.php";
include "api_connect.php";

// Modified insert function without session ID
function insertMessage($conn, $role, $content)
{
    $stmt = $conn->prepare("INSERT INTO chat_messages (role, content) VALUES (?, ?)");
    $stmt->bind_param("ss", $role, $content);
    if (!$stmt->execute()) {
        error_log("Error inserting message: " . $stmt->execute());
    }
    $stmt->close();
}

function deleteAllMessage($conn)
{
    $stmt = $conn->prepare("TRUNCATE TABLE `ai-chat`.`chat_messages`");
    if (!$stmt->execute()) {
        error_log("Error inserting message: " . $stmt->execute());
    }
    $stmt->close();
}

// Process user input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if(isset($_POST['message']))
    {
        $message = trim($_POST['message']);

        if (!empty($message)) {
            // Insert user message into database without session ID
            insertMessage($conn, 'user', $message);

            // Get Gemini response
            $response = getGeminiResponse($message, $apiKey, $model);

            // Insert assistant response into database without session ID
            insertMessage($conn, 'assistant', $response);
        }
    }

    if (isset($_POST['delete'])) {
        deleteAllMessage($conn);
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

    <!-- Google Font for modern look -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="container">
        <h1 class="text-center my-4">AI Chat Bot</h1>
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
                    <button type="submit" class="btn btn-primary">Send</button>
                </div>
            </form>

            <form method="POST" class="delete-chat-container">
                <button type="submit" class="btn btn-danger" name="delete">Delete Chat</button>
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