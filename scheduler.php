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

// Process TTS queue (with fallback for cloud environments)
try {
    require_once __DIR__ . '/includes/ChatterboxTTS.php';
    require_once __DIR__ . '/config/user_settings.json';
    
    $settings = json_decode(file_get_contents(__DIR__ . '/config/user_settings.json'), true) ?: [];
    
    // Check if running in cloud environment
    $hostname = gethostname();
    $isReplit = (strpos($hostname, 'replit') !== false || getenv('REPL_ID'));
    
    if ($isReplit && isset($settings['ttsProvider']) && $settings['ttsProvider'] === 'chatterbox') {
        error_log("Scheduler: Cloud environment detected, processing TTS queue with Google fallback");
        
        // Process failed Chatterbox jobs with Google TTS
        $queueFile = __DIR__ . '/data/tts_queue.json';
        if (file_exists($queueFile)) {
            $queue = json_decode(file_get_contents($queueFile), true) ?: [];
            
            foreach ($queue as $index => $job) {
                if ($job['status'] === 'failed' || $job['status'] === 'pending') {
                    error_log("Scheduler: Processing failed TTS job {$job['id']} with Google TTS");
                    
                    // Switch to Google TTS for this job
                    require_once __DIR__ . '/includes/GoogleTTS.php';
                    $googleTTS = new GoogleTTS($settings);
                    
                    $result = $googleTTS->generateAudio($job['text'], $job['voice_style']);
                    
                    if ($result && isset($result['audio_file'])) {
                        // Update job status
                        $queue[$index]['status'] = 'completed';
                        $queue[$index]['audio_file'] = $result['audio_file'];
                        $queue[$index]['progress'] = 100;
                        
                        // Save updated queue
                        file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT));
                        
                        // Finalize briefing
                        if (isset($job['briefing_id'])) {
                            require_once __DIR__ . '/includes/BriefingHistory.php';
                            $history = new BriefingHistory();
                            
                            $history->saveBriefing([
                                'text' => $job['text'],
                                'audio_file' => $result['audio_file'],
                                'duration' => $job['estimated_duration'] ?? 300,
                                'format' => 'mp3',
                                'sources' => [],
                                'topics' => []
                            ]);
                            
                            error_log("Scheduler: Briefing {$job['briefing_id']} completed with Google TTS");
                        }
                    }
                }
            }
        }
    } else {
        // Normal Chatterbox processing
    
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