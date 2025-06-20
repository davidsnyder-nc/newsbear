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

// Process pending TTS jobs
try {
    include_once __DIR__ . '/includes/process_pending_briefings.php';
} catch (Exception $e) {
    error_log("TTS processing error: " . $e->getMessage());
}

// Process Chatterbox TTS queue
try {
    require_once __DIR__ . '/includes/ChatterboxTTS.php';
    
    $queueFile = __DIR__ . '/data/tts_queue.json';
    if (file_exists($queueFile)) {
        $queue = json_decode(file_get_contents($queueFile), true) ?: [];
        $updated = false;
        
        foreach ($queue as $index => $job) {
            if ($job['status'] === 'queued') {
                error_log("Processing queued Chatterbox job: " . $job['id']);
                
                // Load settings for Chatterbox
                $settingsFile = __DIR__ . '/config/user_settings.json';
                $settings = [];
                if (file_exists($settingsFile)) {
                    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
                }
                
                $chatterbox = new ChatterboxTTS($settings);
                $result = $chatterbox->processJob($job['id']);
                
                if ($result) {
                    error_log("Chatterbox job completed: " . $job['id']);
                    unset($queue[$index]);
                    $updated = true;
                } else {
                    error_log("Chatterbox job failed: " . $job['id']);
                    $queue[$index]['status'] = 'failed';
                    $updated = true;
                }
                
                // Process one job per scheduler run to avoid timeouts
                break;
            }
        }
        
        if ($updated) {
            file_put_contents($queueFile, json_encode(array_values($queue), JSON_PRETTY_PRINT));
        }
    }
} catch (Exception $e) {
    error_log("Chatterbox queue processing error: " . $e->getMessage());
}

echo "Scheduler completed.\n";
?>