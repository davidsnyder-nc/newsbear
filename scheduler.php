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
    require_once __DIR__ . '/includes/ScheduleManager.php';
    
    $scheduleManager = new ScheduleManager();
    $dueSchedules = $scheduleManager->getDueSchedules();
    
    if (empty($dueSchedules)) {
        echo "No schedules due to run at this time.\n";
    } else {
        echo "Found " . count($dueSchedules) . " schedule(s) due to run.\n";
        
        foreach ($dueSchedules as $schedule) {
            echo "Running schedule: " . $schedule['name'] . "\n";
            $scheduleManager->executeSchedule($schedule);
        }
    }
} catch (Exception $e) {
    error_log("Schedule processing error: " . $e->getMessage());
    echo "Schedule processing error: " . $e->getMessage() . "\n";
}

// Process any pending briefings without TTS dependencies
try {
    require_once __DIR__ . '/includes/process_pending_briefings.php';
} catch (Exception $e) {
    error_log("TTS processing error: " . $e->getMessage());
}

echo "Scheduler completed.\n";
?>