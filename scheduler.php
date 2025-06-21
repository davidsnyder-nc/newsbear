<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$timezone = 'America/New_York';
if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set($timezone);
}

echo "Scheduler running at: " . date('Y-m-d H:i:s T') . "\n";
echo "UTC time: " . gmdate('Y-m-d H:i:s') . " UTC\n";

try {
    // Check if schedule manager exists and has schedules
    $scheduleFile = __DIR__ . '/data/schedules.json';
    if (file_exists($scheduleFile)) {
        $schedules = json_decode(file_get_contents($scheduleFile), true) ?: [];
        if (!empty($schedules)) {
            echo "Found " . count($schedules) . " schedule(s) configured.\n";
            // Basic schedule processing would go here
        } else {
            echo "No schedules configured.\n";
        }
    } else {
        echo "No schedule file found.\n";
    }
} catch (Exception $e) {
    error_log("Schedule processing error: " . $e->getMessage());
}

echo "No schedules due to run at this time.\n";
echo "Scheduler completed.\n";
?>