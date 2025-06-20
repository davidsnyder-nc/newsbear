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
        // Test Gradio root endpoint
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serverUrl . '/');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            error_log("Chatterbox: Gradio server available at {$this->serverUrl}");
            return true;
        }
        
        error_log("Chatterbox: Gradio server not responding - HTTP $httpCode");
        return false;
    }
    
    private function sendToChatterbox($text, $voiceStyle) {
        // Standard Gradio endpoints for HF Spaces
        $possibleEndpoints = [
            '/run/predict', // Standard Gradio predict
            '/call/predict', // Gradio call format  
            '/api/predict' // API predict format
        ];
        
        // Check for sample audio file configuration
        $sampleAudio = $this->getSampleAudioConfig();
        
        // Standard Gradio predict format for any function
        $gradioData = [
            'data' => [
                $text, // text_input (Text to synthesize)
                $sampleAudio['audio_data'] ?? null, // audio_prompt_path_input (Reference Audio File)
                0.5, // exaggeration_input (Exaggeration)
                0.05, // temperature_input (Temperature - lower for more stable)
                3, // seed_num_input (Random seed)
                0.2, // cfgw_input (CFG/Pace)
                100 // chunk_size (Chunk Size for long text)
            ],
            'fn_index' => 0 // Use function index 0 (first/main function)
        ];
        
        foreach ($possibleEndpoints as $apiEndpoint) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->serverUrl . $apiEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gradioData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);
            
            error_log("Chatterbox: Trying {$this->serverUrl}{$apiEndpoint} - HTTP {$httpCode}");
            
            if ($httpCode === 200 && $response) {
                $responseData = json_decode($response, true);
                
                // Gradio returns the audio file path/URL in the response
                if (isset($responseData['data']) && isset($responseData['data'][0])) {
                    $audioInfo = $responseData['data'][0];
                    
                    // If it's a file path, download the audio
                    if (isset($audioInfo['name']) || isset($audioInfo['path'])) {
                        $audioUrl = $audioInfo['name'] ?? $audioInfo['path'];
                        
                        // Make sure it's a full URL
                        if (!str_starts_with($audioUrl, 'http')) {
                            $audioUrl = rtrim($this->serverUrl, '/') . '/file=' . ltrim($audioUrl, '/');
                        }
                        
                        error_log("Chatterbox: Success with endpoint {$apiEndpoint}");
                        return $this->downloadAudioFile($audioUrl);
                    }
                }
                
                error_log("Chatterbox: Response from {$apiEndpoint}: " . substr($response, 0, 200));
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
            
            // For Gradio/Chatterbox, we need to provide actual audio data
            if (file_exists(__DIR__ . '/../data/' . $sampleFile)) {
                // Read the audio file and encode for Gradio
                $audioData = file_get_contents(__DIR__ . '/../data/' . $sampleFile);
                $config['audio_data'] = [
                    'name' => $sampleFile,
                    'data' => 'data:audio/wav;base64,' . base64_encode($audioData),
                    'size' => strlen($audioData),
                    'orig_name' => $sampleFile
                ];
            }
        }
        
        return $config;
    }
    
    private function downloadAudioFile($audioUrl) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $audioUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $audioData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $audioData) {
            return $audioData;
        }
        
        error_log("Chatterbox: Failed to download audio from $audioUrl - HTTP $httpCode");
        return false;
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