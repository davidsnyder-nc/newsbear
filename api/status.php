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