<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }
    
    $sessionId = $input['sessionId'] ?? null;
    if (!$sessionId) {
        throw new Exception('Session ID required');
    }
    
    $statusFile = "../downloads/status_{$sessionId}.json";
    
    if (!file_exists($statusFile)) {
        throw new Exception('Session not found');
    }
    
    $status = json_decode(file_get_contents($statusFile), true);
    if (!$status) {
        throw new Exception('Invalid status file');
    }
    
    // Check for timeout (if generation has been running for more than 2 minutes)
    $createdTime = $status['created_at'] ?? time();
    $currentTime = time();
    $elapsed = $currentTime - $createdTime;
    
    // If timeout and we have briefing text, complete without audio
    if ($elapsed > 120 && !$status['complete'] && isset($status['briefingText']) && !empty($status['briefingText'])) {
        // Save text-only briefing to history
        require_once '../includes/BriefingHistory.php';
        $history = new BriefingHistory();
        
        $briefingId = $history->saveBriefing([
            'topics' => $status['topics'] ?? [],
            'text' => $status['briefingText'],
            'audio_file' => null,
            'duration' => 0,
            'format' => 'text',
            'sources' => $status['sources'] ?? []
        ]);
        
        // Update status file to completed
        $status['complete'] = true;
        $status['success'] = true;
        $status['message'] = 'Text briefing completed (audio generation timed out)';
        $status['progress'] = 100;
        file_put_contents($statusFile, json_encode($status));
        
        echo json_encode([
            'status' => 'success',
            'message' => $status['message'],
            'progress' => 100,
            'downloadUrl' => null,
            'briefingText' => $status['briefingText']
        ]);
        return;
    }
    
    // Return the status
    echo json_encode([
        'status' => $status['complete'] ? ($status['success'] ? 'success' : 'error') : 'processing',
        'message' => $status['message'] ?? '',
        'progress' => $status['progress'] ?? 0,
        'downloadUrl' => $status['downloadUrl'] ?? null,
        'briefingText' => $status['briefingText'] ?? null
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>