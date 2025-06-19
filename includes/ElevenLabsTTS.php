<?php

class ElevenLabsTTS {
    private $apiKey;
    private $baseUrl = 'https://api.elevenlabs.io/v1/text-to-speech';
    private $voiceId = 'pNInz6obpgDQGcFmaJgB'; // Adam voice
    
    public function __construct($settings = null) {
        if ($settings) {
            $this->apiKey = $settings['elevenLabsApiKey'] ?? getenv('ELEVENLABS_API_KEY');
        } else {
            $this->apiKey = getenv('ELEVENLABS_API_KEY');
        }
    }
    
    public function synthesizeSpeech($text) {
        if (!$this->apiKey) {
            throw new Exception('ElevenLabs API key is required for ElevenLabs TTS');
        }
        
        // Clean the text for TTS
        $cleanText = $this->cleanText($text);
        
        // Log input details
        error_log("ElevenLabs TTS Input - Character count: " . strlen($cleanText));
        error_log("ElevenLabs TTS Input - Word count: " . str_word_count($cleanText));
        
        // Check if text is too long (ElevenLabs has limits)
        if (strlen($cleanText) > 2500) {
            error_log("ElevenLabs TTS: Text length exceeds limit, using chunked synthesis");
            return $this->synthesizeLongSpeech($cleanText);
        }
        
        return $this->synthesizeSingleChunk($cleanText);
    }
    
    private function synthesizeLongSpeech($text) {
        // Split text into smaller chunks
        $chunks = $this->splitTextIntoChunks($text, 2000);
        error_log("ElevenLabs TTS: Split into " . count($chunks) . " chunks");
        $audioSegments = [];
        
        foreach ($chunks as $i => $chunk) {
            error_log("ElevenLabs TTS: Processing chunk " . ($i + 1) . " - chars: " . strlen($chunk));
            $audioData = $this->synthesizeSingleChunk($chunk, false);
            $audioSegments[] = $audioData;
        }
        
        // Combine audio segments
        $combinedAudio = $this->combineAudioSegments($audioSegments);
        
        // Save to file
        $filename = $this->generateFilename();
        $filepath = __DIR__ . "/../data/history/{$filename}";
        
        // Ensure history directory exists
        $historyDir = __DIR__ . "/../data/history";
        if (!is_dir($historyDir)) {
            mkdir($historyDir, 0755, true);
        }
        
        file_put_contents($filepath, $combinedAudio);
        return "data/history/{$filename}";
    }
    
    private function synthesizeSingleChunk($text, $saveFile = true) {
        $data = [
            'text' => $text,
            'model_id' => 'eleven_monolingual_v1',
            'voice_settings' => [
                'stability' => 0.5,
                'similarity_boost' => 0.5
            ]
        ];
        
        $headers = [
            'xi-api-key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '/' . $this->voiceId);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new Exception("ElevenLabs TTS cURL error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode === 503) {
            throw new Exception("ElevenLabs service is temporarily unavailable. Please try again in a few moments.");
        }
        
        if ($httpCode !== 200) {
            $errorInfo = json_decode($response, true);
            $errorMsg = $errorInfo['detail']['message'] ?? $errorInfo['message'] ?? $response;
            throw new Exception("ElevenLabs TTS API error: HTTP {$httpCode} - {$errorMsg}");
        }
        
        // Response is direct audio data
        $audioData = $response;
        
        if ($saveFile) {
            $filename = $this->generateFilename();
            $filepath = __DIR__ . "/../data/history/{$filename}";
            
            // Ensure history directory exists
            $historyDir = __DIR__ . "/../data/history";
            if (!is_dir($historyDir)) {
                mkdir($historyDir, 0755, true);
            }
            
            file_put_contents($filepath, $audioData);
            return "data/history/{$filename}";
        }
        
        return $audioData;
    }
    
    private function cleanText($text) {
        // Remove SSML tags
        $text = preg_replace('/<[^>]+>/', '', $text);
        
        // Fix common issues
        $text = str_replace(['&amp;', '&lt;', '&gt;'], ['&', '<', '>'], $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    private function splitTextIntoChunks($text, $maxLength) {
        $chunks = [];
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $currentChunk = '';
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk . ' ' . $sentence) > $maxLength && !empty($currentChunk)) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $sentence;
            } else {
                $currentChunk .= (empty($currentChunk) ? '' : ' ') . $sentence;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return $chunks;
    }
    
    private function combineAudioSegments($segments) {
        // Simple concatenation of audio data
        return implode('', $segments);
    }
    
    private function generateFilename() {
        return 'elevenlabs_tts_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.mp3';
    }
}