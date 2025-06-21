<?php



class TTSService {
    private $settings;
    private $ttsProvider;
    private $googleApiKey;
    private $voiceSelection;

    
    public function __construct($settings = null) {
        $this->settings = $settings ?: [];
        $this->ttsProvider = $this->settings['ttsProvider'] ?? 'google';
        
        // Google TTS setup - use settings first
        $this->googleApiKey = $this->settings['googleTtsApiKey'] ?? getenv('GOOGLE_TTS_API_KEY');
        $this->voiceSelection = $this->settings['voiceSelection'] ?? 'en-US-Neural2-D';
        


    }
    
    public function synthesizeSpeech($ssmlText) {
        error_log("TTS: Starting synthesis with provider - " . $this->ttsProvider);
        error_log("TTS: API Key available: " . ($this->googleApiKey ? 'YES (' . strlen($this->googleApiKey) . ' chars)' : 'NO'));
        error_log("TTS: Input text length: " . strlen($ssmlText));
        
        if (!$this->googleApiKey) {
            throw new Exception("Google TTS API key is missing. Please check your configuration.");
        }
        
        // Route to appropriate TTS provider
        switch ($this->ttsProvider) {
            case 'google':
            default:
                return $this->synthesizeWithGoogle($ssmlText);
        }
    }
    
    public function getJobStatus($jobId) {
        return null;
    }
    
    public function isAsyncProvider() {
        error_log("TTS: isAsyncProvider() called - provider: " . $this->ttsProvider);
        $isAsync = false; // No async providers available
        error_log("TTS: isAsyncProvider() returning: " . ($isAsync ? 'true' : 'false'));
        return $isAsync;
    }
    

    
    private function synthesizeWithGoogle($ssmlText) {
        // Check if Google API key is available
        if (!$this->googleApiKey) {
            throw new Exception("Google TTS API key is not configured");
        }
        
        // Clean up the SSML text
        $ssmlText = $this->cleanSSML($ssmlText);
        
        // Log the input text details
        error_log("Google TTS Input - Character count: " . strlen($ssmlText));
        error_log("Google TTS Input - Word count: " . str_word_count(strip_tags($ssmlText)));
        error_log("Google TTS Input - API Key available: " . ($this->googleApiKey ? 'YES' : 'NO'));
        
        // Check if text exceeds Google's 5000 byte limit
        if (strlen($ssmlText) > 4800) { // Increased threshold, leave smaller buffer
            error_log("Google TTS: Text length exceeds limit, using chunked synthesis");
            return $this->synthesizeLongSpeech($ssmlText);
        }
        
        error_log("Google TTS: Using single chunk synthesis");
        return $this->synthesizeSingleChunk($ssmlText);
    }
    
    private function synthesizeLongSpeech($ssmlText) {
        // Split text into chunks that fit within the limit
        $chunks = $this->splitTextIntoChunks($ssmlText, 4500);
        error_log("TTS: Split into " . count($chunks) . " chunks");
        $audioSegments = [];
        
        foreach ($chunks as $i => $chunk) {
            error_log("TTS: Processing chunk " . ($i + 1) . " - chars: " . strlen($chunk));
            $audioData = $this->synthesizeSingleChunk($chunk, false);
            error_log("TTS: Chunk " . ($i + 1) . " audio size: " . strlen($audioData) . " bytes");
            $audioSegments[] = $audioData;
        }
        
        // Combine all audio segments
        $combinedAudio = $this->combineAudioSegments($audioSegments);
        error_log("TTS: Combined audio size: " . strlen($combinedAudio) . " bytes");
        
        // Generate unique filename with timestamp
        $timeFrame = $this->getTimeFrame();
        $date = date('Y-m-d');
        $timestamp = date('His');
        $filename = "ai-news-{$timeFrame}-{$date}-{$timestamp}.mp3";
        $filepath = __DIR__ . "/../downloads/{$filename}";
        
        // Ensure downloads directory exists
        $downloadsDir = __DIR__ . "/../downloads";
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0755, true);
        }
        
        // Save combined audio file
        $bytesWritten = file_put_contents($filepath, $combinedAudio);
        error_log("TTS: Combined audio saved to {$filepath} ({$bytesWritten} bytes)");
        
        // Return relative path from web root
        return "downloads/{$filename}";
    }
    
    private function synthesizeSingleChunk($ssmlText, $saveFile = true) {
        $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->googleApiKey}";
        
        // Get voice configuration based on selection
        $voiceConfig = $this->getVoiceConfiguration($this->voiceSelection);
        
        $data = [
            'input' => [
                'ssml' => $ssmlText
            ],
            'voice' => [
                'languageCode' => $voiceConfig['languageCode'],
                'name' => $this->voiceSelection,
                'ssmlGender' => $voiceConfig['gender']
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => $voiceConfig['speakingRate'],
                'pitch' => $voiceConfig['pitch'],
                'volumeGainDb' => $voiceConfig['volumeGain'],
                'sampleRateHertz' => $voiceConfig['sampleRate'],
                'effectsProfileId' => $voiceConfig['effectsProfile']
            ]
        ];
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception("TTS cURL error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("TTS API Error - HTTP Code: $httpCode");
            error_log("TTS API Error - Response: " . substr($response, 0, 500));
            error_log("TTS API Error - Voice: " . $this->voiceSelection);
            error_log("TTS API Error - API Key length: " . strlen($this->googleApiKey));
            throw new Exception("Google TTS API returned HTTP $httpCode. Check your API key and quota.");
        }
        
        $responseData = json_decode($response, true);
        
        if (!$responseData || !isset($responseData['audioContent'])) {
            throw new Exception("Invalid TTS response format");
        }
        
        $audioData = base64_decode($responseData['audioContent']);
        
        if ($saveFile) {
            // Generate unique filename with timestamp
            $timeFrame = $this->getTimeFrame();
            $date = date('Y-m-d');
            $timestamp = date('His');
            $filename = "ai-news-{$timeFrame}-{$date}-{$timestamp}.mp3";
            $filepath = __DIR__ . "/../downloads/{$filename}";
            
            // Ensure downloads directory exists
            $downloadsDir = __DIR__ . "/../downloads";
            if (!is_dir($downloadsDir)) {
                mkdir($downloadsDir, 0755, true);
            }
            
            // Save audio file
            $bytesWritten = file_put_contents($filepath, $audioData);
            error_log("TTS: Saved audio file to {$filepath} ({$bytesWritten} bytes)");
            
            // Verify file was created
            if (!file_exists($filepath) || filesize($filepath) === 0) {
                error_log("TTS: File verification failed - {$filepath}");
                return null;
            }
            
            // Return relative path from web root
            return "downloads/{$filename}";
        }
        
        return $audioData;
    }
    
    private function splitTextIntoChunks($text, $maxChunkSize) {
        // Remove SSML wrapper if present
        $innerText = $text;
        if (strpos($text, '<speak>') !== false) {
            $innerText = preg_replace('/<speak>(.*?)<\/speak>/s', '$1', $text);
        }
        
        // Split by sentences to maintain natural breaks
        $sentences = preg_split('/(?<=[.!?])\s+/', $innerText, -1, PREG_SPLIT_NO_EMPTY);
        
        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            $testChunk = $currentChunk . ($currentChunk ? ' ' : '') . $sentence;
            $testChunkWithSSML = "<speak>{$testChunk}</speak>";
            
            if (strlen($testChunkWithSSML) > $maxChunkSize && !empty($currentChunk)) {
                // Current chunk is full, start new one
                $chunks[] = "<speak>{$currentChunk}</speak>";
                $currentChunk = $sentence;
            } else {
                $currentChunk = $testChunk;
            }
        }
        
        // Add the last chunk
        if (!empty($currentChunk)) {
            $chunks[] = "<speak>{$currentChunk}</speak>";
        }
        
        return $chunks;
    }
    
    private function combineAudioSegments($segments) {
        if (count($segments) === 1) {
            return $segments[0];
        }
        
        // Create temporary files for each segment
        $tempFiles = [];
        foreach ($segments as $i => $segment) {
            $tempFile = sys_get_temp_dir() . '/tts_segment_' . $i . '_' . time() . '.mp3';
            file_put_contents($tempFile, $segment);
            $tempFiles[] = $tempFile;
        }
        
        // Create output file
        $outputFile = sys_get_temp_dir() . '/tts_combined_' . time() . '.mp3';
        
        // Use ffmpeg to properly combine MP3 files if available
        if ($this->isCommandAvailable('ffmpeg')) {
            $this->combineWithFFmpeg($tempFiles, $outputFile);
        } else {
            // Fallback: simple concatenation
            $combinedData = '';
            foreach ($segments as $segment) {
                $combinedData .= $segment;
            }
            file_put_contents($outputFile, $combinedData);
        }
        
        // Read combined file
        $result = file_get_contents($outputFile);
        
        // Clean up temp files
        foreach ($tempFiles as $tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
        
        return $result;
    }
    
    private function isCommandAvailable($command) {
        $test = shell_exec("which $command 2>/dev/null");
        return !empty($test);
    }
    
    private function combineWithFFmpeg($inputFiles, $outputFile) {
        // Create a file list for ffmpeg
        $listFile = sys_get_temp_dir() . '/ffmpeg_list_' . time() . '.txt';
        $listContent = '';
        foreach ($inputFiles as $file) {
            $listContent .= "file '" . addslashes($file) . "'\n";
        }
        file_put_contents($listFile, $listContent);
        
        // Run ffmpeg to concatenate
        $command = "ffmpeg -f concat -safe 0 -i " . escapeshellarg($listFile) . " -c copy " . escapeshellarg($outputFile) . " 2>/dev/null";
        shell_exec($command);
        
        // Clean up list file
        if (file_exists($listFile)) {
            unlink($listFile);
        }
    }
    
    private function getTimeFrame() {
        $hour = intval(date('H'));
        if ($hour >= 5 && $hour < 12) return 'morning';
        if ($hour >= 12 && $hour < 17) return 'afternoon';
        return 'evening';
    }
    

    
    private function cleanSSML($ssml) {
        // Ensure the text is wrapped in proper SSML tags
        if (strpos($ssml, '<speak>') === false) {
            $ssml = "<speak>{$ssml}</speak>";
        }
        
        // Fix common SSML issues
        $ssml = str_replace('&', '&amp;', $ssml);
        $ssml = str_replace('<', '&lt;', $ssml);
        $ssml = str_replace('>', '&gt;', $ssml);
        
        // Restore SSML tags
        $ssmlTags = [
            '&lt;speak&gt;' => '<speak>',
            '&lt;/speak&gt;' => '</speak>',
            '&lt;break time=' => '<break time=',
            '/&gt;' => '/>',
            '&lt;emphasis level=' => '<emphasis level=',
            '&lt;/emphasis&gt;' => '</emphasis>',
            '&lt;prosody rate=' => '<prosody rate=',
            '&lt;/prosody&gt;' => '</prosody>',
            '&lt;p&gt;' => '<p>',
            '&lt;/p&gt;' => '</p>',
            '&lt;s&gt;' => '<s>',
            '&lt;/s&gt;' => '</s>'
        ];
        
        foreach ($ssmlTags as $encoded => $decoded) {
            $ssml = str_replace($encoded, $decoded, $ssml);
        }
        
        // Ensure quotes are properly handled in SSML attributes
        $ssml = preg_replace('/time=(["\'])([^"\']*?)\1/', 'time="$2"', $ssml);
        $ssml = preg_replace('/level=(["\'])([^"\']*?)\1/', 'level="$2"', $ssml);
        $ssml = preg_replace('/rate=(["\'])([^"\']*?)\1/', 'rate="$2"', $ssml);
        
        return $ssml;
    }
    
    private function getVoiceConfiguration($voiceSelection) {
        $voiceConfigs = [
            // American Male Voices
            'en-US-Neural2-D' => [
                'languageCode' => 'en-US',
                'gender' => 'MALE',
                'speakingRate' => 0.9,
                'pitch' => -2.0,
                'volumeGain' => 3.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-US-Neural2-J' => [
                'languageCode' => 'en-US',
                'gender' => 'MALE',
                'speakingRate' => 0.85,
                'pitch' => -3.0,
                'volumeGain' => 4.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-US-Wavenet-D' => [
                'languageCode' => 'en-US',
                'gender' => 'MALE',
                'speakingRate' => 0.9,
                'pitch' => -1.5,
                'volumeGain' => 5.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            
            // British Male Voices
            'en-GB-Neural2-D' => [
                'languageCode' => 'en-GB',
                'gender' => 'MALE',
                'speakingRate' => 0.9,
                'pitch' => -1.5,
                'volumeGain' => 3.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-GB-Neural2-B' => [
                'languageCode' => 'en-GB',
                'gender' => 'MALE',
                'speakingRate' => 0.85,
                'pitch' => -2.5,
                'volumeGain' => 4.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-GB-Standard-D' => [
                'languageCode' => 'en-GB',
                'gender' => 'MALE',
                'speakingRate' => 0.9,
                'pitch' => -1.0,
                'volumeGain' => 5.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            
            // Australian Male Voices
            'en-AU-Neural2-D' => [
                'languageCode' => 'en-AU',
                'gender' => 'MALE',
                'speakingRate' => 0.9,
                'pitch' => -1.8,
                'volumeGain' => 3.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-AU-Neural2-B' => [
                'languageCode' => 'en-AU',
                'gender' => 'MALE',
                'speakingRate' => 0.85,
                'pitch' => -2.8,
                'volumeGain' => 4.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-AU-Standard-D' => [
                'languageCode' => 'en-AU',
                'gender' => 'MALE',
                'speakingRate' => 0.9,
                'pitch' => -1.2,
                'volumeGain' => 5.0,
                'sampleRate' => 22050,
                'effectsProfile' => ['headphone-class-device']
            ],
            
            // Female Voices
            'en-US-Neural2-F' => [
                'languageCode' => 'en-US',
                'gender' => 'FEMALE',
                'speakingRate' => 0.9,
                'pitch' => 0.0,
                'volumeGain' => 3.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-GB-Neural2-F' => [
                'languageCode' => 'en-GB',
                'gender' => 'FEMALE',
                'speakingRate' => 0.9,
                'pitch' => 0.5,
                'volumeGain' => 3.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ],
            'en-AU-Neural2-A' => [
                'languageCode' => 'en-AU',
                'gender' => 'FEMALE',
                'speakingRate' => 0.9,
                'pitch' => 0.2,
                'volumeGain' => 3.0,
                'sampleRate' => 24000,
                'effectsProfile' => ['headphone-class-device']
            ]
        ];
        
        // Return configuration for selected voice, fallback to default if not found
        return $voiceConfigs[$voiceSelection] ?? $voiceConfigs['en-US-Neural2-D'];
    }
}
?>
