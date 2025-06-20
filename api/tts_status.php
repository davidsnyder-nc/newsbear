<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../includes/TTSService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$jobId = $_GET['job_id'] ?? null;

if (!$jobId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing job_id parameter']);
    exit;
}

try {
    // Load settings
    $settingsFile = __DIR__ . '/../config/user_settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    
    $tts = new TTSService($settings);
    $status = $tts->getJobStatus($jobId);
    
    if (!$status) {
        http_response_code(404);
        echo json_encode(['error' => 'Job not found']);
        exit;
    }
    
    $response = [
        'job_id' => $status['id'],
        'status' => $status['status'],
        'progress' => $status['progress'],
        'created_at' => $status['created_at'],
        'estimated_duration' => $status['estimated_duration']
    ];
    
    if ($status['status'] === 'completed' && $status['audio_file']) {
        $response['audio_file'] = $status['audio_file'];
    }
    
    if (isset($status['updated_at'])) {
        $response['updated_at'] = $status['updated_at'];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("TTS Status API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}