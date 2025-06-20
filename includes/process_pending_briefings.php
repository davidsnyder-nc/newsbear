<?php

// Background processor to check completed TTS jobs and finalize briefings
require_once __DIR__ . '/TTSService.php';
require_once __DIR__ . '/BriefingHistory.php';

$pendingFile = __DIR__ . '/../data/pending_briefings.json';
$statusDir = __DIR__ . '/../downloads';

if (!file_exists($pendingFile)) {
    exit(0);
}

$pending = json_decode(file_get_contents($pendingFile), true) ?: [];
$updated = false;

// Load settings
$settingsFile = __DIR__ . '/../config/user_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

$tts = new TTSService($settings);

foreach ($pending as $jobId => $briefingData) {
    $jobStatus = $tts->getJobStatus($jobId);
    
    if (!$jobStatus) {
        // Job not found, remove from pending
        unset($pending[$jobId]);
        $updated = true;
        continue;
    }
    
    if ($jobStatus['status'] === 'completed' && !empty($jobStatus['audio_file'])) {
        error_log("Processing completed briefing for job: $jobId");
        
        // Finalize the briefing
        try {
            $history = new BriefingHistory();
            
            // Add to history
            $historyId = $history->saveBriefing([
                'text' => $briefingData['content'],
                'audio_file' => $jobStatus['audio_file'],
                'duration' => $briefingData['settings']['audioLength'] ?? 5,
                'format' => 'wav',
                'sources' => $briefingData['stories'] ?? [],
                'topics' => []
            ]);
            
            // Update session status file
            $sessionId = $briefingData['session_id'];
            $statusFile = $statusDir . "/status_briefing_{$sessionId}.json";
            
            $finalStatus = [
                'message' => 'Complete!',
                'progress' => 100,
                'complete' => true,
                'success' => true,
                'downloadUrl' => $jobStatus['audio_file'],
                'briefingText' => $briefingData['content'],
                'timestamp' => time()
            ];
            
            file_put_contents($statusFile, json_encode($finalStatus));
            
            error_log("Briefing finalized: $historyId, audio: " . $jobStatus['audio_file']);
            
        } catch (Exception $e) {
            error_log("Error finalizing briefing: " . $e->getMessage());
        }
        
        // Remove from pending
        unset($pending[$jobId]);
        $updated = true;
        
    } elseif ($jobStatus['status'] === 'failed') {
        error_log("TTS job failed for briefing: $jobId");
        
        // Update session with failure
        $sessionId = $briefingData['session_id'];
        $statusFile = $statusDir . "/status_briefing_{$sessionId}.json";
        
        $failureStatus = [
            'message' => 'Audio generation failed',
            'progress' => 0,
            'complete' => true,
            'success' => false,
            'error' => 'TTS processing failed',
            'timestamp' => time()
        ];
        
        file_put_contents($statusFile, json_encode($failureStatus));
        
        // Remove from pending
        unset($pending[$jobId]);
        $updated = true;
    }
}

// Save updated pending list
if ($updated) {
    file_put_contents($pendingFile, json_encode($pending, JSON_PRETTY_PRINT));
}