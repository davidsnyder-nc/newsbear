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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        if ($curlError) {
            error_log("Chatterbox: Server not available at {$this->serverUrl}");
            return false;
        }
        
        if ($httpCode === 200) {
            error_log("Chatterbox: Gradio server available at {$this->serverUrl}");
            return true;
        }
        
        error_log("Chatterbox: Server not responding - HTTP $httpCode");
        return false;
    }
    
    private function sendToChatterbox($text, $voiceStyle) {
        // Gradio API endpoint from the documentation  
        $apiEndpoint = 'gradio_api/call/generate_tts_audio';
        
        // Check for sample audio file configuration
        $sampleAudio = $this->getSampleAudioConfig();
        
        // Exact format from Gradio API documentation
        $gradioData = [
            'data' => [
                $text, // [0] string - Text to synthesize
                $sampleAudio['audio_data'] ?? null, // [1] Reference Audio File (optional)
                0.25, // [2] number - Exaggeration (0.5 = neutral)
                0.05, // [3] number - Temperature 
                3, // [4] number - Random seed (0 for random)
                0.2, // [5] number - CFG/Pace
                100 // [6] number - Chunk Size for long text
            ]
        ];
        
        // Step 1: POST request to initiate generation
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, rtrim($this->serverUrl, '/') . '/' . $apiEndpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gradioData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Chatterbox: POST {$this->serverUrl}{$apiEndpoint} - HTTP {$httpCode}");
        error_log("Chatterbox: POST Response: " . substr($response, 0, 200));
        
        if ($httpCode !== 200 || !$response) {
            error_log("Chatterbox: POST request failed - HTTP {$httpCode}");
            return false;
        }
        
        // Extract EVENT_ID from response
        $responseData = json_decode($response, true);
        if (!isset($responseData['event_id'])) {
            error_log("Chatterbox: No event_id in response: " . $response);
            return false;
        }
        
        $eventId = $responseData['event_id'];
        if (empty($eventId)) {
            error_log("Chatterbox: Empty EVENT_ID received");
            return false;
        }
        
        error_log("Chatterbox: Got EVENT_ID: {$eventId}");
        
        // Step 2: GET request to fetch results
        $resultUrl = rtrim($this->serverUrl, '/') . '/' . $apiEndpoint . '/' . $eventId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $resultUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // Long timeout for generation
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $resultResponse = curl_exec($ch);
        $resultHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        error_log("Chatterbox: GET {$resultUrl} - HTTP {$resultHttpCode}");
        error_log("Chatterbox: GET Response: " . substr($resultResponse, 0, 200));
        
        if ($resultHttpCode === 200 && $resultResponse) {
            error_log("Chatterbox: Full streaming response: " . $resultResponse);
            
            // Parse the streaming response to get the final result
            $lines = explode("\n", $resultResponse);
            $finalData = null;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'data: ')) {
                    $jsonData = substr($line, 6);
                    $data = json_decode($jsonData, true);
                    
                    error_log("Chatterbox: Streaming data: " . json_encode($data));
                    
                    // The response is a direct array of file data, not wrapped in output structure
                    if (is_array($data) && isset($data[0])) {
                        $audioInfo = $data[0];
                        
                        // Check if we have a URL or path
                        if (isset($audioInfo['url'])) {
                            $audioUrl = $audioInfo['url'];
                            error_log("Chatterbox: Found audio URL: {$audioUrl}");
                            return $this->downloadAudioFile($audioUrl);
                        } elseif (isset($audioInfo['path'])) {
                            $audioPath = $audioInfo['path'];
                            $audioUrl = rtrim($this->serverUrl, '/') . '/file=' . ltrim($audioPath, '/');
                            error_log("Chatterbox: Constructed audio URL: {$audioUrl}");
                            return $this->downloadAudioFile($audioUrl);
                        }
                    }
                }
            }
        }
        
        error_log("Chatterbox: Failed to get audio result");
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