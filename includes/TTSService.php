<?php

class TTSService {
    private $apiKey;
    private $voiceSelection;
    
    public function __construct($settings = null) {
        if ($settings) {
            $this->apiKey = ($settings['googleTtsEnabled'] ?? true) ? ($settings['googleTtsApiKey'] ?: getenv('GOOGLE_TTS_API_KEY')) : null;
            $this->voiceSelection = $settings['voiceSelection'] ?? 'en-US-Neural2-D';
        } else {
            $this->apiKey = getenv('GOOGLE_TTS_API_KEY');
            $this->voiceSelection = 'en-US-Neural2-D';
        }
    }
    
    public function synthesizeSpeech($ssmlText) {
        // Clean up the SSML text
        $ssmlText = $this->cleanSSML($ssmlText);
        
        // Check if text exceeds Google's 5000 byte limit
        if (strlen($ssmlText) > 4500) { // Leave buffer for SSML tags
            return $this->synthesizeLongSpeech($ssmlText);
        }
        
        return $this->synthesizeSingleChunk($ssmlText);
    }
    
    private function synthesizeLongSpeech($ssmlText) {
        // Split text into chunks that fit within the limit
        $chunks = $this->splitTextIntoChunks($ssmlText, 4000);
        $audioSegments = [];
        
        foreach ($chunks as $i => $chunk) {
            $audioData = $this->synthesizeSingleChunk($chunk, false);
            $audioSegments[] = $audioData;
        }
        
        // Combine all audio segments
        $combinedAudio = $this->combineAudioSegments($audioSegments);
        
        // Generate unique filename with timestamp
        $timeFrame = $this->getTimeFrame();
        $date = date('Y-m-d');
        $timestamp = date('His');
        $filename = "ai-news-{$timeFrame}-{$date}-{$timestamp}.mp3";
        $filepath = __DIR__ . "/../data/history/{$filename}";
        
        // Ensure history directory exists
        $historyDir = __DIR__ . "/../data/history";
        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0755, true);
        }
        
        // Save combined audio file
        file_put_contents($filepath, $combinedAudio);
        
        // Return relative path from web root
        return "data/history/{$filename}";
    }
    
    private function synthesizeSingleChunk($ssmlText, $saveFile = true) {
        $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->apiKey}";
        
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
            throw new Exception("TTS cURL error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("TTS API error: HTTP {$httpCode} - {$response}");
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
            $filepath = __DIR__ . "/../data/history/{$filename}";
            
            // Ensure history directory exists
            $historyDir = __DIR__ . "/../data/history";
            if (!is_dir($historyDir)) {
                mkdir($historyDir, 0755, true);
            }
            
            // Save audio file
            file_put_contents($filepath, $audioData);
            
            // Return relative path from web root
            return "data/history/{$filename}";
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
        // For MP3 files, we can simply concatenate the binary data
        // This works because MP3 is a stream format
        return implode('', $segments);
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
