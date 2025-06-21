<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$sessionId = $_GET['session'] ?? $_POST['session'] ?? '';

if ($sessionId) {
    // Remove session files
    $files = glob(__DIR__ . '/../data/*' . $sessionId . '*');
    foreach ($files as $file) {
        unlink($file);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Session stopped and cleaned up',
        'files_removed' => count($files)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'No session ID provided'
    ]);
}
?>