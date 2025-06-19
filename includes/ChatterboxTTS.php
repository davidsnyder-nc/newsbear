<?php

class ChatterboxTTS {
    private $apiKey;
    private $baseUrl = 'https://fal.run/fal-ai/resemble-chatterbox-tts';
    
    public function __construct($settings = null) {
        if ($settings) {
            $this->apiKey = $settings['falApiKey'] ?? getenv('FAL_API_KEY');
        } else {
            $this->apiKey = getenv('FAL_API_KEY');
        }
    }
    
    public function synthesizeSpeech($text) {
        if (!$this->apiKey) {
            throw new Exception('Hugging Face API key is required for Chatterbox TTS');
        }
        
        // Clean the text for TTS
        $cleanText = $this->cleanText($text);
        
        // Log input details
        error_log("Chatterbox TTS Input - Character count: " . strlen($cleanText));
        error_log("Chatterbox TTS Input - Word count: " . str_word_count($cleanText));
        
        // Check if text is too long (Chatterbox has limits)
        if (strlen($cleanText) > 1000) {
            error_log("Chatterbox TTS: Text length exceeds limit, using chunked synthesis");
            return $this->synthesizeLongSpeech($cleanText);
        }
        
        return $this->synthesizeSingleChunk($cleanText);
    }
    
    private function synthesizeLongSpeech($text) {
        // Split text into smaller chunks
        $chunks = $this->splitTextIntoChunks($text, 800);
        error_log("Chatterbox TTS: Split into " . count($chunks) . " chunks");
        $audioSegments = [];
        
        foreach ($chunks as $i => $chunk) {
            error_log("Chatterbox TTS: Processing chunk " . ($i + 1) . " - chars: " . strlen($chunk));
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
        // Get voice settings from user preferences
        $voiceSettings = $this->getChatterboxVoiceSettings();
        
        // fal.ai API format based on documentation
        $data = [
            'input' => $text
        ];
        
        $headers = [
            'Authorization: Key ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->baseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            throw new Exception("Chatterbox TTS cURL error: " . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode === 503) {
            throw new Exception("Chatterbox model is loading. Please try again in a few moments.");
        }
        
        if ($httpCode !== 200) {
            $errorInfo = json_decode($response, true);
            $errorMsg = $errorInfo['error'] ?? $response;
            throw new Exception("Chatterbox TTS API error: HTTP {$httpCode} - {$errorMsg}");
        }
        
        // For TTS models, the response is direct audio data
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
    
    private function splitTextIntoChunks($text, $maxChunkSize) {
        // Split by sentences to maintain natural breaks
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            $testChunk = $currentChunk . ($currentChunk ? ' ' : '') . $sentence;
            
            if (strlen($testChunk) > $maxChunkSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = $sentence;
            } else {
                $currentChunk = $testChunk;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = $currentChunk;
        }
        
        return $chunks;
    }
    
    private function combineAudioSegments($segments) {
        if (count($segments) === 1) {
            return $segments[0];
        }
        
        // Simple concatenation for now - could be improved with proper audio mixing
        $combinedData = '';
        foreach ($segments as $segment) {
            $combinedData .= $segment;
        }
        
        return $combinedData;
    }
    
    private function cleanText($text) {
        // Remove SSML tags if present
        $text = preg_replace('/<[^>]+>/', '', $text);
        
        // Clean up common problematic characters
        $text = str_replace(['&amp;', '&lt;', '&gt;'], ['&', '<', '>'], $text);
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    private function generateFilename() {
        $timeFrame = $this->getTimeFrame();
        $date = date('Y-m-d');
        $timestamp = date('His');
        return "chatterbox-news-{$timeFrame}-{$date}-{$timestamp}.wav";
    }
    
    private function getTimeFrame() {
        $hour = intval(date('H'));
        if ($hour >= 5 && $hour < 12) return 'morning';
        if ($hour >= 12 && $hour < 17) return 'afternoon';
        return 'evening';
    }
    
    private function getChatterboxVoiceSettings() {
        $voiceStyle = $this->settings['chatterboxVoice'] ?? 'default';
        
        switch ($voiceStyle) {
            case 'news_anchor':
                return [
                    'exaggeration' => 0.3,
                    'cfg' => 0.6,
                    'speed' => 0.9,
                    'voice_description' => 'Professional news anchor with clear, authoritative delivery'
                ];
            case 'conversational':
                return [
                    'exaggeration' => 0.5,
                    'cfg' => 0.5,
                    'speed' => 1.0,
                    'voice_description' => 'Natural, conversational tone for everyday listening'
                ];
            case 'dramatic':
                return [
                    'exaggeration' => 0.7,
                    'cfg' => 0.3,
                    'speed' => 0.8,
                    'voice_description' => 'Expressive, dramatic delivery with emphasis'
                ];
            case 'calm':
                return [
                    'exaggeration' => 0.2,
                    'cfg' => 0.7,
                    'speed' => 0.85,
                    'voice_description' => 'Calm, soothing voice ideal for relaxed listening'
                ];
            default:
                return [
                    'exaggeration' => 0.5,
                    'cfg' => 0.5,
                    'speed' => 1.0,
                    'voice_description' => 'Balanced default voice settings'
                ];
        }
    }
}
?>