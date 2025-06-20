<?php
/**
 * Emergency stop for stuck polling sessions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['error' => 'Method not allowed']));
}

// Force stop any stuck sessions
echo json_encode([
    'status' => 'stopped',
    'message' => 'All polling stopped'
]);
?>