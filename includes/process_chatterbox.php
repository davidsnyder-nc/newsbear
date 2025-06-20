<?php

// Background processor for Chatterbox TTS jobs
require_once __DIR__ . '/ChatterboxTTS.php';

if ($argc < 2) {
    error_log("Usage: php process_chatterbox.php <job_id>");
    exit(1);
}

$jobId = $argv[1];

// Load settings
$settingsFile = __DIR__ . '/../config/user_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

$chatterbox = new ChatterboxTTS($settings);

error_log("Chatterbox: Starting background processing for job $jobId");

// Process the job
$result = $chatterbox->processJob($jobId);

if ($result) {
    error_log("Chatterbox: Job $jobId completed successfully: $result");
} else {
    error_log("Chatterbox: Job $jobId failed");
}

// Clean old jobs
$chatterbox->cleanOldJobs();