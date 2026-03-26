<?php
// test_beem_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include config
require_once dirname(dirname(__FILE__)) . '/includes/config.php';

echo "<h1>Beem Africa API Test</h1>";

// Check if constants are defined
echo "<h3>Configuration Check:</h3>";
echo "BEEM_AFRICA_API_KEY: " . (defined('BEEM_AFRICA_API_KEY') ? substr(BEEM_AFRICA_API_KEY, 0, 8) . '...' : 'NOT DEFINED') . "<br>";
echo "BEEM_AFRICA_SECRET_KEY: " . (defined('BEEM_AFRICA_SECRET_KEY') ? substr(BEEM_AFRICA_SECRET_KEY, 0, 8) . '...' : 'NOT DEFINED') . "<br>";
echo "BEEM_AFRICA_API_URL: " . (defined('BEEM_AFRICA_API_URL') ? BEEM_AFRICA_API_URL : 'NOT DEFINED') . "<br>";
echo "BEEM_AFRICA_SENDER_ID: " . (defined('BEEM_AFRICA_SENDER_ID') ? BEEM_AFRICA_SENDER_ID : 'NOT DEFINED') . "<br>";

// Test API call
if (defined('BEEM_AFRICA_API_KEY') && defined('BEEM_AFRICA_SECRET_KEY')) {
    echo "<h3>Testing API Call:</h3>";

    $phone = '255682123456'; // Test number
    $message = 'Test message from API';

    $apiData = [
        'source_addr' => BEEM_AFRICA_SENDER_ID,
        'schedule_time' => '',
        'encoding' => 0,
        'message' => $message,
        'recipients' => [
            [
                'recipient_id' => 1,
                'dest_addr' => $phone
            ]
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, BEEM_AFRICA_API_URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode(BEEM_AFRICA_API_KEY . ':' . BEEM_AFRICA_SECRET_KEY)
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    echo "<strong>Request:</strong><br>";
    echo "<pre>" . json_encode($apiData, JSON_PRETTY_PRINT) . "</pre>";

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    echo "<strong>Response (HTTP $httpCode):</strong><br>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";

    if (curl_error($ch)) {
        echo "<strong>cURL Error:</strong> " . curl_error($ch) . "<br>";
    }

    curl_close($ch);

    // Try to decode JSON
    $json = json_decode($response, true);
    if ($json) {
        echo "<strong>Parsed JSON:</strong><br>";
        echo "<pre>" . print_r($json, true) . "</pre>";
    }
} else {
    echo "<div style='color: red;'>API credentials not configured!</div>";
}
?>