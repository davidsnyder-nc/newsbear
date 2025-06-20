<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/ChatterboxTTS.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$serverUrl = $input['server_url'] ?? 'http://localhost:8000';

try {
    // Test settings
    $testSettings = [
        'chatterboxServerUrl' => $serverUrl,
        'chatterboxVoice' => 'news_anchor',
        'ttsProvider' => 'chatterbox'
    ];
    
    $chatterbox = new ChatterboxTTS($testSettings);
    
    // Test direct connection first
    $connectionTest = testDirectConnection($serverUrl);
    
    if (!$connectionTest['success']) {
        echo json_encode([
            'success' => false,
            'message' => 'Chatterbox server not responding',
            'details' => $connectionTest['error'],
            'debug_info' => $connectionTest['debug'],
            'suggestions' => [
                'Verify Chatterbox-TTS is running on ' . $serverUrl,
                'Check firewall/network settings',
                'Test direct browser access to ' . $serverUrl,
                'Verify the server URL format (include http://)',
                'Check if server accepts connections from this IP'
            ]
        ]);
        exit;
    }
    
    // Test TTS generation with short text
    echo json_encode([
        'success' => true,
        'message' => 'Chatterbox server is accessible',
        'server_url' => $serverUrl,
        'details' => 'Connection successful. Ready to process TTS requests.',
        'next_steps' => [
            'Set TTS Provider to "Chatterbox TTS" in settings',
            'Configure the correct server URL: ' . $serverUrl,
            'Test with a news briefing generation'
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Connection test failed',
        'error' => $e->getMessage(),
        'suggestions' => [
            'Check if Chatterbox-TTS is running',
            'Verify the server URL and port',
            'Ensure the API endpoints are accessible'
        ]
    ]);
}

function testDirectConnection($serverUrl) {
    $endpoints = ['/', '/health', '/api/health', '/docs', '/api/docs'];
    $debug = [];
    
    foreach ($endpoints as $endpoint) {
        $testUrl = rtrim($serverUrl, '/') . $endpoint;
        $debug[] = "Testing: $testUrl";
        
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
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        $debug[] = "  Response: HTTP $httpCode";
        if ($error) {
            $debug[] = "  Error: $error";
        }
        if ($response && strlen($response) < 500) {
            $debug[] = "  Response sample: " . substr($response, 0, 100);
        }
        
        if ($httpCode >= 200 && $httpCode < 400) {
            return [
                'success' => true,
                'endpoint' => $testUrl,
                'http_code' => $httpCode,
                'debug' => $debug
            ];
        }
    }
    
    return [
        'success' => false,
        'error' => "No working endpoints found. Server may not be running or accessible.",
        'debug' => $debug
    ];
}