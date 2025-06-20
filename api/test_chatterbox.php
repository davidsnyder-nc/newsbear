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
    
    // Test server availability
    $reflection = new ReflectionClass($chatterbox);
    $isServerAvailable = $reflection->getMethod('isServerAvailable');
    $isServerAvailable->setAccessible(true);
    
    if (!$isServerAvailable->invoke($chatterbox)) {
        echo json_encode([
            'success' => false,
            'message' => 'Chatterbox server not responding',
            'details' => 'Server at ' . $serverUrl . ' is not accessible. Check if Chatterbox is running and the port is correct.',
            'suggestions' => [
                'Verify Chatterbox-TTS is running',
                'Check if the port matches your web UI port',
                'Try accessing ' . $serverUrl . ' in your browser',
                'Check firewall settings'
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