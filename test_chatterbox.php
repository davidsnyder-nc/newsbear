<?php
// Quick Chatterbox connection test

$serverUrl = 'http://192.168.1.20:7861';

echo "Testing Chatterbox server at: $serverUrl\n";

// Test 1: Basic connectivity
echo "1. Testing basic connectivity...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $serverUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo "   FAILED: $curlError\n";
} else {
    echo "   HTTP $httpCode\n";
}

// Test 2: Check Gradio API endpoints
echo "2. Testing Gradio API endpoints...\n";
$endpoints = ['/api/predict', '/run/predict', '/call/predict'];

foreach ($endpoints as $endpoint) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serverUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "   $endpoint: FAILED ($curlError)\n";
    } else {
        echo "   $endpoint: HTTP $httpCode\n";
    }
}

echo "\nDone.\n";
?>