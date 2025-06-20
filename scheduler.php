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
            if ($job['status'] === 'queued' || $job['status'] === 'failed') {
                error_log("Processing Chatterbox job: " . $job['id'] . " (status: " . $job['status'] . ")");
                
                // Load settings for Chatterbox
                $settingsFile = __DIR__ . '/config/user_settings.json';
                $settings = [];
                if (file_exists($settingsFile)) {
                    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
                }
                
                // Set unlimited execution time for audio processing
                set_time_limit(0); // No time limit
                ini_set('memory_limit', '1G');
                
                $chatterbox = new ChatterboxTTS($settings);
                $result = $chatterbox->processJob($job['id']);
                
                if ($result) {
                    error_log("Chatterbox job completed: " . $job['id']);
                    // Update pending briefing with audio file
                    updatePendingBriefingWithAudio($job['id'], $result, __DIR__);
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

// Helper function to update pending briefings with completed audio
function updatePendingBriefingWithAudio($jobId, $audioFile, $baseDir) {
    $pendingFile = $baseDir . '/../data/pending_briefings.json';
    if (!file_exists($pendingFile)) return;
    
    $pending = json_decode(file_get_contents($pendingFile), true) ?: [];
    
    foreach ($pending as $index => $briefing) {
        if (isset($briefing['tts_job_id']) && $briefing['tts_job_id'] === $jobId) {
            // Complete the briefing with audio
            require_once dirname($baseDir) . '/includes/BriefingHistory.php';
            $history = new BriefingHistory();
            
            $briefingId = $history->saveBriefing([
                'topics' => $briefing['topics'] ?? [],
                'text' => $briefing['text'],
                'audio_file' => $audioFile,
                'duration' => $briefing['duration'] ?? 5,
                'format' => 'mp3',
                'sources' => $briefing['sources'] ?? []
            ]);
            
            // Update status file
            $statusFile = dirname($baseDir) . '/downloads/status_' . $briefing['session_id'] . '.json';
            if (file_exists($statusFile)) {
                $status = json_decode(file_get_contents($statusFile), true);
                $status['complete'] = true;
                $status['success'] = true;
                $status['downloadUrl'] = '/downloads/' . basename($audioFile);
                $status['message'] = 'Briefing completed successfully';
                file_put_contents($statusFile, json_encode($status));
            }
            
            // Remove from pending
            unset($pending[$index]);
            break;
        }
    }
    
    file_put_contents($pendingFile, json_encode(array_values($pending), JSON_PRETTY_PRINT));
}

echo "Scheduler completed.\n";
?>