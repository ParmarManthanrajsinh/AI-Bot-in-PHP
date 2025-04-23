<?php

// API Configuration
$apiKey = 'YOUR_API_KEY'; // Replace with your actual API key
$model = 'gemini-2.0-flash';

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

// Gemini API function with combined cURL options
function getGeminiResponse($message, $apiKey, $model, $userId = null, $sessionId = null)
{
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

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true, // Return response as a string
        CURLOPT_POST => true,           // Use POST method
        CURLOPT_POSTFIELDS => json_encode($data), // Send JSON payload
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json', // Inform the API of JSON data
        ],
        CURLOPT_SSL_VERIFYPEER => true, // Ensure SSL certificate verification
        CURLOPT_CAINFO => __DIR__ . '/cacert.pem', // Path to certificate authority file
        CURLOPT_TIMEOUT => 30,          // Timeout after 30 seconds
    ]);


    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Calculate response time
    $responseTime = microtime(true) - $startTime;

    // Store response time for analytics
    logApiPerformance($responseTime, $httpCode, $model);


    if ($errno) {
        $errorMessage = "cURL Error ({$errno}): " . $error;

        // Log error if analytics is available
        if (function_exists('logError') && $userId && $sessionId) {
            global $conn;
            logError($conn, 'API_CURL_ERROR', $errorMessage, $userId, $sessionId);
        }

        return $errorMessage;
    }

    if ($httpCode !== 200) {
        $errorMessage = "API Error: HTTP {$httpCode} - " . ($response ?: 'No response');

        // Log error if analytics is available
        if (function_exists('logError') && $userId && $sessionId) {
            global $conn;
            logError($conn, 'API_HTTP_ERROR', $errorMessage, $userId, $sessionId);
        }

        return $errorMessage;
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
