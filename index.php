<?php
session_start();

// Configuration
$apiKey = 'AIzaSyBqDbUeIzw_v5IMDEQ5FVXyG17bmNVLYNw'; // Replace with your actual API key
$model = 'gemini-pro';

// Initialize chat history
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// Process user input
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = trim($_POST['message']);

    if (!empty($message)) {
        $_SESSION['chat_history'][] = ['role' => 'user', 'content' => $message];
        $response = getGeminiResponse($message, $apiKey, $model);
        $_SESSION['chat_history'][] = ['role' => 'assistant', 'content' => $response];
    }
}

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
    <link rel="stylesheet" href="style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container my-5">
        <h1 class="mb-4">AI Chat Bot (Gemini)</h1>

        <div class="chat-container">
            <div class="chat-history">
                <?php foreach ($_SESSION['chat_history'] as $entry): ?>
                    <div class="message <?= $entry['role'] ?>">
                        <div class="role"><?= ucfirst($entry['role']) ?>:</div>
                        <div class="content"><?= nl2br(htmlspecialchars($entry['content'])) ?></div>
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