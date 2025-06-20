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
$sampleFile = $input['sample_file'] ?? '';

try {
    // Test settings
    $testSettings = [
        'chatterboxServerUrl' => $serverUrl,
        'chatterboxVoice' => 'news_anchor',
        'chatterboxSampleFile' => $sampleFile,
        'ttsProvider' => 'chatterbox'
    ];
    
    $chatterbox = new ChatterboxTTS($testSettings);
    
    // Short test text
    $testText = "Hello, this is a quick test of Chatterbox TTS integration with NewsBear.";
    
    // Generate test audio directly (bypass queue for short text)
    $startTime = microtime(true);
    
    // Use reflection to call sendToChatterbox directly for immediate results
    $reflection = new ReflectionClass($chatterbox);
    $sendMethod = $reflection->getMethod('sendToChatterbox');
    $sendMethod->setAccessible(true);
    
    $audioData = $sendMethod->invoke($chatterbox, $testText, 'news_anchor');
    $processingTime = round((microtime(true) - $startTime), 2);
    
    if ($audioData) {
        // Save test audio file
        $testFileName = 'chatterbox_test_' . time() . '.wav';
        $testFilePath = __DIR__ . '/../downloads/' . $testFileName;
        
        if (file_put_contents($testFilePath, $audioData)) {
            $fileSize = strlen($audioData);
            
            echo json_encode([
                'success' => true,
                'message' => 'Chatterbox TTS test successful!',
                'details' => [
                    'processing_time' => $processingTime . ' seconds',
                    'audio_size' => round($fileSize / 1024, 2) . ' KB',
                    'test_file' => $testFileName,
                    'download_url' => 'downloads/' . $testFileName
                ],
                'test_text' => $testText,
                'server_url' => $serverUrl
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Audio generated but failed to save file',
                'details' => 'Check file permissions in downloads folder',
                'debug_info' => [
                    'audio_size' => strlen($audioData),
                    'file_path' => $testFilePath,
                    'downloads_writable' => is_writable(__DIR__ . '/../downloads/')
                ]
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'TTS generation failed',
            'details' => 'No audio data received from Chatterbox server',
            'debug_info' => [
                'server_url' => $serverUrl,
                'test_text_length' => strlen($testText),
                'processing_time' => $processingTime . 's'
            ],
            'suggestions' => [
                'Verify Chatterbox server is running at: ' . $serverUrl,
                'Check if server accepts the API format',
                'Review Chatterbox server logs for errors',
                'Try the Debug Endpoints button for more details'
            ]
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'TTS test failed',
        'error' => $e->getMessage(),
        'suggestions' => [
            'Check Chatterbox server status',
            'Verify network connectivity',
            'Review server configuration'
        ]
    ]);
}