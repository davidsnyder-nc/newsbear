<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

require_once '../includes/BriefingHistory.php';
require_once '../includes/TTSService.php';

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['briefingId'])) {
        throw new Exception('Briefing ID is required');
    }
    
    $briefingId = $input['briefingId'];
    
    // Get briefing from history
    $history = new BriefingHistory();
    $briefing = $history->getBriefingById($briefingId);
    
    if (!$briefing) {
        throw new Exception('Briefing not found');
    }
    
    if ($briefing['audio_file'] && file_exists($briefing['audio_file'])) {
        throw new Exception('Audio file already exists for this briefing');
    }
    
    if (empty($briefing['text'])) {
        throw new Exception('No text content available for audio generation');
    }
    
    // Load user settings for TTS
    $settings = [];
    if (file_exists('../config/user_settings.json')) {
        $settingsJson = file_get_contents('../config/user_settings.json');
        $settings = json_decode($settingsJson, true) ?: [];
    }
    
    // Generate audio using TTS service
    $ttsService = new TTSService($settings);
    $audioFile = $ttsService->synthesizeSpeech($briefing['text']);
    
    if (!$audioFile) {
        throw new Exception('Failed to generate audio file');
    }
    
    // Verify the file was actually created
    if (!file_exists($audioFile)) {
        throw new Exception('Audio file was not saved properly');
    }
    
    // Update briefing record with audio file
    $allBriefings = $history->getAllBriefings();
    foreach ($allBriefings as &$b) {
        if ($b['id'] === $briefingId) {
            $b['audio_file'] = $audioFile;
            break;
        }
    }
    
    // Save updated briefings
    file_put_contents('../data/history/briefings.json', json_encode($allBriefings, JSON_PRETTY_PRINT));
    
    echo json_encode([
        'success' => true,
        'audio_file' => $audioFile
    ]);
    
} catch (Exception $e) {
    error_log("Audio generation error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>