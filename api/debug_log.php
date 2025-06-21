<?php
header('Content-Type: application/json');

$sessionId = $_GET['session'] ?? '';
if (empty($sessionId)) {
    http_response_code(400);
    echo json_encode(['error' => 'No session ID provided']);
    exit;
}

$logFile = "../data/debug_log_{$sessionId}.json";

// Check if log file exists
if (!file_exists($logFile)) {
    // Return empty if no logs yet
    echo json_encode(['logs' => [], 'has_more' => true]);
    exit;
}

$content = file_get_contents($logFile);
if (empty($content)) {
    echo json_encode(['logs' => [], 'has_more' => true]);
    exit;
}

$logs = json_decode($content, true) ?: [];

// Get the starting point from the last request
$lastCount = (int)($_GET['last_count'] ?? 0);
$newLogs = array_slice($logs, $lastCount);

// Check if generation is complete
$isComplete = false;
foreach ($logs as $log) {
    if (stripos($log['message'], 'generation complete') !== false || 
        stripos($log['message'], 'briefing generated successfully') !== false) {
        $isComplete = true;
        break;
    }
}

echo json_encode([
    'logs' => $newLogs,
    'total_count' => count($logs),
    'has_more' => !$isComplete,
    'stop_polling' => $isComplete
]);
?>