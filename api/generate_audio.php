<?php
session_start();
require_once '../includes/AuthManager.php';

$auth = new AuthManager();

// Check authentication if enabled
if ($auth->isAuthEnabled() && !$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    require_once '../includes/TTSService.php';
    require_once '../includes/BriefingHistory.php';

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['briefing_id']) || !isset($input['text_content'])) {
        throw new Exception('Missing required parameters');
    }
    
    $briefingId = $input['briefing_id'];
    $textContent = $input['text_content'];
    
    // Load user settings for TTS
    $userSettingsFile = '../config/user_settings.json';
    $settings = [];
    if (file_exists($userSettingsFile)) {
        $settings = json_decode(file_get_contents($userSettingsFile), true) ?: [];
    }
    
    // Generate audio
    $ttsService = new TTSService($settings);
    $audioFile = $ttsService->synthesizeSpeech($textContent);
    
    if (!$audioFile) {
        throw new Exception('Failed to generate audio file');
    }
    
    // Update the briefing record with the audio file
    $history = new BriefingHistory();
    $success = $history->updateBriefingAudioFile($briefingId, basename($audioFile));
    
    if (!$success) {
        throw new Exception('Failed to update briefing record with audio file');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Audio generated successfully',
        'audio_file' => $audioFile
    ]);
    
} catch (Exception $e) {
    error_log("Audio generation error: " . $e->getMessage());
    error_log("Audio generation stack trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to generate audio: ' . $e->getMessage()
    ]);
}
?>