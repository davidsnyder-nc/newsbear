<?php
/**
 * Cleanup orphaned sessions and stuck polling
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

try {
    $cleanedUp = 0;
    
    // Clean up old debug logs (older than 1 hour)
    $debugDir = __DIR__ . '/../data/debug';
    if (is_dir($debugDir)) {
        $files = glob($debugDir . '/*.log');
        $oneHourAgo = time() - 3600;
        
        foreach ($files as $file) {
            if (filemtime($file) < $oneHourAgo) {
                unlink($file);
                $cleanedUp++;
            }
        }
    }
    
    // Clean up old status files
    $downloadsDir = __DIR__ . '/../downloads';
    if (is_dir($downloadsDir)) {
        $files = glob($downloadsDir . '/status_*.json');
        $oneHourAgo = time() - 3600;
        
        foreach ($files as $file) {
            if (filemtime($file) < $oneHourAgo) {
                unlink($file);
                $cleanedUp++;
            }
        }
    }
    
    echo json_encode([
        'status' => 'success',
        'cleaned_files' => $cleanedUp,
        'message' => 'Session cleanup completed'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>