<?php
// Download handler for MP3 files with forced download headers
if (!isset($_GET['file'])) {
    http_response_code(400);
    die('No file specified');
}

$filename = $_GET['file'];
$filepath = 'downloads/' . basename($filename); // Sanitize path

// Security check - only allow files in downloads directory
if (!file_exists($filepath) || strpos($filename, '..') !== false) {
    http_response_code(404);
    die('File not found');
}

// Only allow specific file types
$allowedExtensions = ['mp3', 'txt', 'wav'];
$extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions)) {
    http_response_code(403);
    die('File type not allowed');
}

// Set headers for forced download
$downloadName = basename($filename);
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file content
readfile($filepath);
exit;
?>