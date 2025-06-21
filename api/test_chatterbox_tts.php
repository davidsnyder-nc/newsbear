<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    // Load settings
    $settingsFile = __DIR__ . '/../config/user_settings.json';
    if (!file_exists($settingsFile)) {
        throw new Exception('Settings file not found');
    }
    
    $settings = json_decode(file_get_contents($settingsFile), true);
    if (!$settings) {
        throw new Exception('Invalid settings file');
    }
    
    $serverUrl = $settings['chatterboxServerUrl'] ?? 'http://127.0.0.1:8000';
    
    // Test TTS generation with a simple request
    $testUrl = rtrim($serverUrl, '/') . '/generate';
    
    $testData = [
        'text' => 'This is a test of the Chatterbox TTS system.',
        'voice' => $settings['chatterboxVoice'] ?? 'default'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'NewsBear/2.0');
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception("Connection error: $error");
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Server returned HTTP $httpCode. Response: " . substr($response, 0, 200));
    }
    
    // Parse response
    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception("Invalid JSON response from server: " . substr($response, 0, 200));
    }
    
    if (!isset($data['job_id'])) {
        throw new Exception("No job_id in response: " . json_encode($data));
    }
    
    // Wait a moment for processing to start
    sleep(2);
    
    // Check job status
    $statusUrl = rtrim($serverUrl, '/') . '/status/' . $data['job_id'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $statusUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'NewsBear/2.0');
    
    $statusResponse = curl_exec($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $statusError = curl_error($ch);
    curl_close($ch);
    
    if ($statusError) {
        throw new Exception("Status check error: $statusError");
    }
    
    if ($statusCode !== 200) {
        throw new Exception("Status check failed with HTTP $statusCode");
    }
    
    $statusData = json_decode($statusResponse, true);
    if (!$statusData) {
        throw new Exception("Invalid status response");
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'TTS generation test successful',
        'job_id' => $data['job_id'],
        'status' => $statusData['status'] ?? 'unknown',
        'progress' => $statusData['progress'] ?? 0,
        'server_url' => $serverUrl
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>