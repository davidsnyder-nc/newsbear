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
    
    // Test connection to Chatterbox server
    $testUrl = rtrim($serverUrl, '/') . '/health';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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
        // Try the root endpoint as fallback
        $testUrl = rtrim($serverUrl, '/') . '/';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
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
            throw new Exception("Server returned HTTP $httpCode");
        }
    }
    
    // Parse response
    $data = json_decode($response, true);
    if (!$data) {
        // If not JSON, check if it's a valid response
        if (!empty($response)) {
            $data = ['status' => 'running', 'service' => 'detected'];
        } else {
            throw new Exception("Invalid response from server");
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Connection successful',
        'server_url' => $serverUrl,
        'server_status' => $data['status'] ?? 'unknown',
        'service' => $data['service'] ?? 'Chatterbox TTS'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>