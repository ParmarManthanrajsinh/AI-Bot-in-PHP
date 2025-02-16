<?php

// API Configuration
$apiKey = 'AIzaSyBqDbUeIzw_v5IMDEQ5FVXyG17bmNVLYNw'; // Replace with your actual API key
$model = 'gemini-pro';

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