<?php

class ChatterboxTTS {
    private $settings;
    private $serverUrl;
    private $voiceStyle;
    private $queueFile;
    
    public function __construct($settings = null) {
        $this->settings = $settings ?: [];
        $this->serverUrl = $this->settings['chatterboxServerUrl'] ?? 'http://localhost:8000';
        $this->voiceStyle = $this->settings['chatterboxVoice'] ?? 'news_anchor';
        $this->queueFile = __DIR__ . '/../data/tts_queue.json';
        
        // Ensure queue file exists
        if (!file_exists($this->queueFile)) {
            file_put_contents($this->queueFile, json_encode([]));
        }
    }
    
    public function synthesizeSpeech($ssmlText) {
        // Clean SSML for plain text
        $plainText = strip_tags($ssmlText);
        
        // Generate unique job ID
        $jobId = uniqid('chatterbox_', true);
        
        // Create job entry
        $job = [
            'id' => $jobId,
            'text' => $plainText,
            'voice_style' => $this->voiceStyle,
            'status' => 'queued',
            'created_at' => time(),
            'progress' => 0,
            'estimated_duration' => $this->estimateProcessingTime($plainText),
            'audio_file' => null
        ];
        
        // Add to queue
        $this->addToQueue($job);
        
        // Start processing asynchronously
        $this->startAsyncProcessing($jobId);
        
        // Return job ID for status tracking
        return $jobId;
    }
    
    private function estimateProcessingTime($text) {
        // Estimate processing time based on text length
        $wordCount = str_word_count($text);
        $baseTime = 30; // Base 30 seconds
        $timePerWord = 0.5; // 0.5 seconds per word
        return $baseTime + ($wordCount * $timePerWord);
    }
    
    private function addToQueue($job) {
        $queue = $this->getQueue();
        $queue[] = $job;
        file_put_contents($this->queueFile, json_encode($queue, JSON_PRETTY_PRINT));
    }
    
    private function getQueue() {
        if (!file_exists($this->queueFile)) {
            return [];
        }
        return json_decode(file_get_contents($this->queueFile), true) ?: [];
    }
    
    private function updateJobStatus($jobId, $status, $progress = null, $audioFile = null) {
        $queue = $this->getQueue();
        foreach ($queue as &$job) {
            if ($job['id'] === $jobId) {
                $job['status'] = $status;
                if ($progress !== null) $job['progress'] = $progress;
                if ($audioFile !== null) $job['audio_file'] = $audioFile;
                $job['updated_at'] = time();
                break;
            }
        }
        file_put_contents($this->queueFile, json_encode($queue, JSON_PRETTY_PRINT));
    }
    
    public function getJobStatus($jobId) {
        $queue = $this->getQueue();
        foreach ($queue as $job) {
            if ($job['id'] === $jobId) {
                return $job;
            }
        }
        return null;
    }
    
    private function startAsyncProcessing($jobId) {
        // Start background processing
        $cmd = "php " . __DIR__ . "/process_chatterbox.php " . escapeshellarg($jobId) . " > /dev/null 2>&1 &";
        exec($cmd);
    }
    
    public function processJob($jobId) {
        $job = $this->getJobStatus($jobId);
        if (!$job) {
            error_log("Chatterbox: Job $jobId not found");
            return false;
        }
        
        try {
            $this->updateJobStatus($jobId, 'processing', 10);
            
            // Check if Chatterbox server is available
            if (!$this->isServerAvailable()) {
                $this->updateJobStatus($jobId, 'failed', 0);
                error_log("Chatterbox: Server not available at " . $this->serverUrl);
                return false;
            }
            
            $this->updateJobStatus($jobId, 'processing', 30);
            
            // Send request to Chatterbox server
            $audioData = $this->sendToChatterbox($job['text'], $job['voice_style']);
            
            if (!$audioData) {
                $this->updateJobStatus($jobId, 'failed', 0);
                return false;
            }
            
            $this->updateJobStatus($jobId, 'processing', 80);
            
            // Save audio file
            $audioFile = $this->saveAudioFile($audioData, $jobId);
            
            if ($audioFile) {
                $this->updateJobStatus($jobId, 'completed', 100, $audioFile);
                return $audioFile;
            } else {
                $this->updateJobStatus($jobId, 'failed', 0);
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Chatterbox processing error: " . $e->getMessage());
            $this->updateJobStatus($jobId, 'failed', 0);
            return false;
        }
    }
    
    private function isServerAvailable() {
        // Try multiple common endpoints to check server availability
        $endpoints = [
            '/api/health',
            '/health', 
            '/api/status',
            '/status',
            '/api/v1/health',
            '/' // Base endpoint as fallback
        ];
        
        foreach ($endpoints as $endpoint) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->serverUrl . $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                error_log("Chatterbox: Server available at {$this->serverUrl}{$endpoint}");
                return true;
            }
        }
        
        error_log("Chatterbox: Server not responding on any known endpoints");
        return false;
    }
    
    private function sendToChatterbox($text, $voiceStyle) {
        // Try multiple common API endpoint formats
        $apiEndpoints = [
            '/api/tts',
            '/api/synthesize', 
            '/synthesize',
            '/api/v1/tts',
            '/tts',
            '/generate'
        ];
        
        // Check for sample audio file configuration
        $sampleAudio = $this->getSampleAudioConfig();
        
        $commonFormats = [
            // Format 1: JSON with text and voice
            [
                'data' => array_merge([
                    'text' => $text,
                    'voice' => $voiceStyle,
                    'format' => 'wav'
                ], $sampleAudio),
                'headers' => ['Content-Type: application/json', 'Accept: audio/wav']
            ],
            // Format 2: JSON with voice_style
            [
                'data' => array_merge([
                    'text' => $text,
                    'voice_style' => $voiceStyle,
                    'format' => 'wav'
                ], $sampleAudio),
                'headers' => ['Content-Type: application/json', 'Accept: audio/wav']
            ],
            // Format 3: Chatterbox-TTS specific format
            [
                'data' => array_merge([
                    'text' => $text,
                    'speaker_wav' => $sampleAudio['sample_file'] ?? null,
                    'language' => 'en',
                    'format' => 'wav'
                ], $sampleAudio),
                'headers' => ['Content-Type: application/json', 'Accept: audio/wav']
            ],
            // Format 4: Form data
            [
                'data' => "text=" . urlencode($text) . "&voice=" . urlencode($voiceStyle) . "&format=wav",
                'headers' => ['Content-Type: application/x-www-form-urlencoded', 'Accept: audio/wav']
            ],
            // Format 5: Simple text parameter
            [
                'data' => array_merge([
                    'text' => $text,
                    'speaker' => $voiceStyle
                ], $sampleAudio),
                'headers' => ['Content-Type: application/json', 'Accept: audio/wav']
            ]
        ];
        
        foreach ($apiEndpoints as $endpoint) {
            foreach ($commonFormats as $format) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->serverUrl . $endpoint);
                curl_setopt($ch, CURLOPT_POST, true);
                
                if (is_array($format['data'])) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($format['data']));
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $format['data']);
                }
                
                curl_setopt($ch, CURLOPT_HTTPHEADER, $format['headers']);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 300);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
                curl_close($ch);
                
                if ($httpCode === 200 && $response && (strpos($contentType, 'audio') !== false || strlen($response) > 1000)) {
                    error_log("Chatterbox: Success with endpoint {$endpoint} using format " . json_encode($format['data']));
                    return $response;
                }
                
                if ($httpCode !== 404 && $httpCode !== 405) {
                    error_log("Chatterbox: Tried {$this->serverUrl}{$endpoint} - HTTP {$httpCode}, Content-Type: {$contentType}");
                }
            }
        }
        
        error_log("Chatterbox API error: No working endpoint found");
        return false;
    }
    
    private function getSampleAudioConfig() {
        $config = [];
        
        // Check for sample audio file setting
        if (isset($this->settings['chatterboxSampleFile']) && !empty($this->settings['chatterboxSampleFile'])) {
            $sampleFile = $this->settings['chatterboxSampleFile'];
            
            // If it's a local file path (relative to data folder)
            if (file_exists(__DIR__ . '/../data/' . $sampleFile)) {
                // Option 1: Send file data in request (for servers that accept base64)
                $audioData = file_get_contents(__DIR__ . '/../data/' . $sampleFile);
                $config['sample_audio'] = base64_encode($audioData);
                $config['sample_format'] = pathinfo($sampleFile, PATHINFO_EXTENSION);
            } else {
                // Option 2: Reference file by name (for servers with local access)
                $config['sample_file'] = $sampleFile;
                $config['speaker_wav'] = $sampleFile;
            }
        }
        
        return $config;
    }
    
    private function saveAudioFile($audioData, $jobId) {
        $timeFrame = $this->getTimeFrame();
        $date = date('Y-m-d');
        $timestamp = date('His');
        $filename = "chatterbox-news-{$timeFrame}-{$date}-{$timestamp}.wav";
        $filepath = __DIR__ . "/../data/history/{$filename}";
        
        // Ensure directory exists
        $dir = dirname($filepath);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        if (file_put_contents($filepath, $audioData)) {
            return "data/history/{$filename}";
        }
        
        return false;
    }
    
    private function getTimeFrame() {
        $hour = (int)date('H');
        if ($hour < 12) return 'morning';
        if ($hour < 17) return 'afternoon';
        return 'evening';
    }
    
    public function cleanOldJobs() {
        $queue = $this->getQueue();
        $cutoff = time() - (24 * 3600); // 24 hours ago
        
        $cleaned = array_filter($queue, function($job) use ($cutoff) {
            return $job['created_at'] > $cutoff;
        });
        
        file_put_contents($this->queueFile, json_encode(array_values($cleaned), JSON_PRETTY_PRINT));
    }
}