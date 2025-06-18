<?php

class TTSService {
    private $apiKey;
    
    public function __construct($settings = null) {
        if ($settings) {
            $this->apiKey = ($settings['googleTtsEnabled'] ?? true) ? ($settings['googleTtsApiKey'] ?: getenv('GOOGLE_TTS_API_KEY')) : null;
        } else {
            $this->apiKey = getenv('GOOGLE_TTS_API_KEY');
        }
    }
    
    public function synthesizeSpeech($ssmlText) {
        // Clean up the SSML text
        $ssmlText = $this->cleanSSML($ssmlText);
        
        $url = "https://texttospeech.googleapis.com/v1/text:synthesize?key={$this->apiKey}";
        
        $data = [
            'input' => [
                'ssml' => $ssmlText
            ],
            'voice' => [
                'languageCode' => 'en-US',
                'name' => 'en-US-Neural2-D', // Best quality neural male voice
                'ssmlGender' => 'MALE'
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3',
                'speakingRate' => 0.9, // Professional news anchor pace
                'pitch' => -2.0, // Deep, authoritative tone
                'volumeGainDb' => 3.0, // Clear volume level
                'sampleRateHertz' => 24000, // Studio quality
                'effectsProfileId' => ['headphone-class-device'] // Premium audio profile
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
}
?>
