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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Assistant</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #121212;
            height: 100vh;
            display: flex;
            flex-direction: column;
            color: #e0e0e0;
        }

        .chat-container {
            flex: 1;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: rgba(60, 60, 60, 0.5) rgba(30, 30, 30, 0.3);
        }

        .chat-container::-webkit-scrollbar {
            width: 6px;
        }

        .chat-container::-webkit-scrollbar-track {
            background: rgba(30, 30, 30, 0.3);
            border-radius: 3px;
        }

        .chat-container::-webkit-scrollbar-thumb {
            background: rgba(60, 60, 60, 0.5);
            border-radius: 3px;
        }

        .chat-container::-webkit-scrollbar-thumb:hover {
            background: rgba(80, 80, 80, 0.7);
        }

        .message-ai {
            background-color: #2d2d2d;
            border-radius: 0 18px 18px 18px;
            max-width: 80%;
            color: #e0e0e0;
        }

        .message-user {
            background-color: #3b82f6;
            color: white;
            border-radius: 18px 0 18px 18px;
            max-width: 80%;
        }

        .typing-indicator::after {
            content: '...';
            animation: typing 1.5s steps(3, end) infinite;
            display: inline-block;
            overflow: hidden;
            vertical-align: bottom;
        }

        @keyframes typing {
            from {
                width: 0
            }

            to {
                width: 100%
            }
        }

        .input-area {
            border-top: 1px solid #444444;
            background-color: #1e1e1e;
        }

        .send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border: none;
            color: white;
            transition: all 0.2s ease;
        }

        .send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .message-meta {
            font-size: 0.75rem;
            opacity: 0.8;
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="bg-dark shadow-sm py-3 px-4 text-light">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <div class="bg-primary bg-gradient rounded-circle d-flex align-items-center justify-content-center"
                    style="width: 40px; height: 40px;">
                    <i class="fas fa-robot text-white"></i>
                </div>
                <h1 class="h5 mb-0 ms-3">AI Assistant</h1>
            </div>
            <div>
                <form method="POST" class="d-inline">
                    <button type="submit" name="delete" class="btn btn-sm btn-outline-danger me-2 text-light">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                    <button type="submit" name="admin" class="btn btn-sm btn-outline-success text-light">
                        <i class="fas fa-lock"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Chat container -->
    <div class="chat-container p-3" id="chatBox">
        <!-- Welcome message -->
        <div class="d-flex mb-3">
            <div class="message-ai p-3 shadow-sm">
                <p class="mb-1">Hello! I'm your AI assistant. How can I help you today?</p>
                <div class="message-meta mt-2">
                    <span>AI</span>
                    <span class="mx-1">•</span>
                    <span>Just now</span>
                </div>
            </div>
        </div>

        <!-- Display chat history -->
        <?php foreach ($chatHistory as $entry): ?>
            <div class="d-flex mb-3 <?php echo $entry['role'] === 'user' ? 'justify-content-end' : 'justify-content-start'; ?>">
                <div class="<?php echo $entry['role'] === 'user' ? 'message-user' : 'message-ai'; ?> p-3 shadow-sm">
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($entry['content'])); ?></p>
                    <div class="message-meta mt-2">
                        <span><?php echo $entry['role'] === 'user' ? 'You' : 'AI'; ?></span>
                        <span class="mx-1">•</span>
                        <span>Sent</span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Input area -->
    <div class="input-area p-3">
        <form method="POST">
            <div class="d-flex align-items-end">
                <div class="flex-grow-1 bg-dark rounded-pill px-3 py-2 me-2 border border-secondary">
                    <textarea name="message" id="userInput" placeholder="Type your message..." rows="1"
                        class="form-control border-0 bg-transparent shadow-none resize-none text-light"
                        style="overflow-y: auto; max-height: 120px;"></textarea>
                </div>
                <button type="submit" id="sendButton" class="send-btn" style="background: linear-gradient(135deg, #2563eb, #7c3aed);">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </form>
        <p class="text-secondary text-center small mt-2 mb-0">AI may produce inaccurate information. Consider checking
            important details.</p>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const chatBox = document.getElementById('chatBox');
            const userInput = document.getElementById('userInput');

            // Auto-resize textarea
            userInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });

            // Send message on Enter key (but allow Shift+Enter for new lines)
            userInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });

            // Initial scroll to bottom
            chatBox.scrollTop = chatBox.scrollHeight;
        });
    </script>
</body>

</html>