<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $sessionId = $_GET['session'] ?? '';
    
    if (empty($sessionId)) {
        throw new Exception('Session ID is required');
    }
    
    // Debug log file path
    $logFile = __DIR__ . '/../data/debug/' . $sessionId . '.log';
    
    // Ensure debug directory exists
    $debugDir = dirname($logFile);
    if (!is_dir($debugDir)) {
        mkdir($debugDir, 0755, true);
    }
    
    $logs = [];
    
    if (file_exists($logFile)) {
        $content = file_get_contents($logFile);
        $lines = explode("\n", trim($content));
        
        foreach ($lines as $line) {
            if (!empty($line)) {
                $logs[] = [
                    'timestamp' => time(),
                    'message' => $line,
                    'type' => 'info'
                ];
            }
        }
    } else {
        // Check if session exists in any form
        $sessionPattern = $sessionId;
        $sessionFiles = glob(__DIR__ . '/../data/*' . $sessionId . '*');
        
        // If no session files exist and session ID looks old, stop polling
        if (empty($sessionFiles)) {
            // Extract timestamp from session ID if it contains one
            $sessionParts = explode('_', $sessionId);
            if (count($sessionParts) > 1) {
                $sessionTime = floatval($sessionParts[1]);
                if ($sessionTime > 0 && time() - $sessionTime > 120) { // 2 minutes for testing
                    http_response_code(410); // Gone
                    echo json_encode([
                        'success' => false,
                        'error' => 'Session expired',
                        'stop_polling' => true
                    ]);
                    return;
                }
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'logs' => $logs
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>