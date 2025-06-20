<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$serverUrl = $input['server_url'] ?? 'http://192.168.1.20:7861';

// Test all common Chatterbox API patterns
$testEndpoints = [
    // Common health/status endpoints
    '/' => 'Root endpoint',
    '/health' => 'Health check',
    '/api/health' => 'API health check',
    '/docs' => 'API documentation',
    '/api/docs' => 'API docs',
    '/status' => 'Status endpoint',
    '/api/status' => 'API status',
    
    // TTS endpoints based on common patterns
    '/api/tts' => 'TTS API endpoint',
    '/tts' => 'Direct TTS endpoint',
    '/synthesize' => 'Synthesize endpoint',
    '/api/synthesize' => 'API synthesize endpoint',
    '/generate' => 'Generate endpoint',
    '/api/generate' => 'API generate endpoint',
    '/speak' => 'Speak endpoint',
    '/api/speak' => 'API speak endpoint',
    '/v1/tts' => 'v1 TTS endpoint',
    '/api/v1/tts' => 'API v1 TTS endpoint'
];

$results = [];
$workingEndpoints = [];

foreach ($testEndpoints as $endpoint => $description) {
    $testUrl = rtrim($serverUrl, '/') . $endpoint;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $testUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'NewsBear-Debug/2.0');
    curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request for faster testing
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    $connectTime = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
    $totalTime = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    curl_close($ch);
    
    $result = [
        'endpoint' => $endpoint,
        'url' => $testUrl,
        'description' => $description,
        'http_code' => $httpCode,
        'success' => ($httpCode >= 200 && $httpCode < 400),
        'connect_time' => round($connectTime * 1000, 2) . 'ms',
        'total_time' => round($totalTime * 1000, 2) . 'ms'
    ];
    
    if ($error) {
        $result['error'] = $error;
    }
    
    if ($result['success']) {
        $workingEndpoints[] = $endpoint;
    }
    
    $results[] = $result;
}

// Test specific TTS request formats if any endpoints are working
$ttsTests = [];
if (!empty($workingEndpoints)) {
    $testData = [
        'text' => 'Hello, this is a test.',
        'voice' => 'default'
    ];
    
    foreach (['/api/tts', '/tts', '/synthesize'] as $endpoint) {
        if (in_array($endpoint, $workingEndpoints)) {
            $testUrl = rtrim($serverUrl, '/') . $endpoint;
            
            // Test JSON POST
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $testUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: audio/wav, audio/mp3, */*'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_NOBODY, true); // Don't download actual audio
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            $ttsTests[] = [
                'endpoint' => $endpoint,
                'method' => 'POST JSON',
                'http_code' => $httpCode,
                'content_type' => $contentType,
                'accepts_tts' => ($httpCode === 200 && strpos($contentType, 'audio') !== false)
            ];
        }
    }
}

echo json_encode([
    'server_url' => $serverUrl,
    'total_endpoints_tested' => count($testEndpoints),
    'working_endpoints' => count($workingEndpoints),
    'working_endpoint_list' => $workingEndpoints,
    'endpoint_results' => $results,
    'tts_tests' => $ttsTests,
    'recommendations' => [
        'If no endpoints work: Check if Chatterbox is running and accessible',
        'If health endpoints work but TTS fails: Check TTS API documentation',
        'If connection errors: Check firewall/network settings',
        'Working endpoints can be used for TTS integration'
    ]
]);