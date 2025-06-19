<?php
/**
 * Scheduler Runner - Checks and executes due schedules
 * This script should be run every minute via cron or a similar scheduler
 */

require_once __DIR__ . '/includes/ScheduleManager.php';

// Set timezone to Eastern Time for consistency
date_default_timezone_set('America/New_York');

echo "Scheduler running at: " . date('Y-m-d H:i:s') . " Eastern Time\n";
echo "UTC time: " . gmdate('Y-m-d H:i:s') . " UTC\n";

try {
    $scheduleManager = new ScheduleManager();
    $results = $scheduleManager->runDueSchedules();
    
    if (empty($results)) {
        echo "No schedules due to run at this time.\n";
    } else {
        echo "Executed " . count($results) . " scheduled briefings:\n";
        foreach ($results as $result) {
            $status = $result['success'] ? 'SUCCESS' : 'FAILED';
            echo "  - {$result['schedule_name']} ({$result['schedule_id']}): {$status}\n";
        }
    }
    
    // Log execution
    $logEntry = date('Y-m-d H:i:s') . " - Scheduler executed, " . count($results) . " briefings processed\n";
    file_put_contents(__DIR__ . '/data/scheduler.log', $logEntry, FILE_APPEND | LOCK_EX);
    
} catch (Exception $e) {
    echo "Scheduler error: " . $e->getMessage() . "\n";
    
    // Log error
    $errorEntry = date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n";
    file_put_contents(__DIR__ . '/data/scheduler.log', $errorEntry, FILE_APPEND | LOCK_EX);
}

echo "Scheduler completed.\n";
?>