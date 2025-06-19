<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Simple debug log API endpoint
$sessionId = $_GET['session'] ?? '';

if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Session ID required']);
    exit;
}

// Read PHP error log for the session
$logFile = "/tmp/newsbear_debug_$sessionId.log";
$logs = [];

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", trim($logContent));
    
    foreach ($lines as $line) {
        if (!empty($line)) {
            // Parse log entries
            if (strpos($line, 'ERROR:') !== false) {
                $logs[] = ['message' => $line, 'type' => 'error'];
            } elseif (strpos($line, 'WARNING:') !== false) {
                $logs[] = ['message' => $line, 'type' => 'warning'];
            } elseif (strpos($line, 'SUCCESS:') !== false) {
                $logs[] = ['message' => $line, 'type' => 'success'];
            } else {
                $logs[] = ['message' => $line, 'type' => 'info'];
            }
        }
    }
    
    // Clear the log file after reading
    file_put_contents($logFile, '');
}

echo json_encode(['logs' => $logs]);
?>