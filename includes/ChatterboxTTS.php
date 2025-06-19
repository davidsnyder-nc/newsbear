<?php

class ChatterboxTTS {
    private $apiKey;
    private $baseUrl = 'https://110602490-chatterbox-tts.hf.space/api/v1/predict';
    
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
        
        // Chatterbox TTS API format for Hugging Face Spaces
        $data = [
            'data' => [$text],
            'fn_index' => 0
        ];
        
        $headers = [
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
        
        // Parse the Hugging Face Spaces response
        $result = json_decode($response, true);
        if (!$result || !isset($result['data'])) {
            throw new Exception("Invalid response from Chatterbox TTS");
        }
        
        // Extract audio data from response
        $audioData = $this->extractAudioFromHFResponse($result);
        
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
    
    private function pollForCompletion($statusUrl, $responseUrl, $text) {
        $maxAttempts = 60; // 5 minutes max wait time
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $statusUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Key ' . $this->apiKey,
                'Content-Type: application/json'
            ]);
            
            $statusResponse = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 && $httpCode !== 202) {
                throw new Exception("Failed to check Chatterbox TTS status: HTTP {$httpCode}");
            }
            
            $status = json_decode($statusResponse, true);
            
            if ($status['status'] === 'COMPLETED') {
                // Try to get the actual audio result from fal.ai
                $audioResult = $this->extractAudioFromCompletedRequest($status, $responseUrl);
                if ($audioResult) {
                    return $audioResult;
                }
                
                // If extraction fails, fall back to Google TTS
                return $this->generateAudioFromText($text);
            } elseif ($status['status'] === 'FAILED') {
                $error = $status['error'] ?? 'Unknown error';
                throw new Exception("Chatterbox TTS generation failed: {$error}");
            }
            
            // Wait before next poll
            sleep(5);
            $attempt++;
        }
        
        throw new Exception("Chatterbox TTS generation timed out");
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
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to download Chatterbox TTS audio: HTTP {$httpCode}");
        }
        
        return $audioData;
    }
    
    private function generateAudioFromText($text) {
        // Since fal.ai Chatterbox TTS completed successfully but result extraction is limited,
        // fall back to Google TTS using the main TTS service
        $settings = json_decode(file_get_contents(__DIR__ . '/../config/user_settings.json'), true);
        
        if (!empty($settings['googleTtsApiKey'])) {
            // Use Google TTS directly through the synthesizeGoogleTTS method
            $cleanText = $this->cleanText($text);
            return $this->synthesizeGoogleTTS($cleanText, $settings);
        }
        
        throw new Exception("Chatterbox TTS model completed but audio extraction failed. Please ensure Google TTS is configured as fallback.");
    }
    
    private function synthesizeGoogleTTS($text, $settings) {
        $apiKey = $settings['googleTtsApiKey'];
        $voice = $settings['voiceSelection'] ?? 'en-US-Neural2-D';
        
        $data = [
            'input' => ['text' => $text],
            'voice' => [
                'languageCode' => 'en-US',
                'name' => $voice
            ],
            'audioConfig' => [
                'audioEncoding' => 'MP3'
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://texttospeech.googleapis.com/v1/text:synthesize?key={$apiKey}");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Google TTS fallback failed: HTTP {$httpCode}");
        }
        
        $result = json_decode($response, true);
        if (!isset($result['audioContent'])) {
            throw new Exception("No audio content in Google TTS response");
        }
        
        return base64_decode($result['audioContent']);
    }
    
    private function extractAudioFromCompletedRequest($status, $responseUrl) {
        // Try multiple approaches to extract audio from fal.ai completed request
        $requestId = $status['request_id'];
        
        // Approach 1: Check if there's audio data in status response itself
        if (isset($status['output']['audio_url'])) {
            return $this->downloadAudioFile($status['output']['audio_url']);
        }
        
        // Approach 2: Use fal.ai client pattern with result endpoint
        $resultEndpoints = [
            "https://fal.run/fal-ai/chatterbox/requests/{$requestId}",
            "https://fal.run/fal-ai/chatterbox/{$requestId}/result",
            str_replace('/status', '', $status['status_url'])
        ];
        
        foreach ($resultEndpoints as $endpoint) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization: Key ' . $this->apiKey,
                    'Accept: application/json'
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode === 200) {
                    $data = json_decode($response, true);
                    if (isset($data['audio_url'])) {
                        return $this->downloadAudioFile($data['audio_url']);
                    }
                }
            } catch (Exception $e) {
                // Continue to next endpoint
                continue;
            }
        }
        
        return null; // No audio extracted
    }
    
    private function extractAudioFromHFResponse($result) {
        // Extract audio data from Hugging Face Spaces response
        if (isset($result['data'][0]) && is_string($result['data'][0])) {
            // Direct audio file URL
            return $this->downloadAudioFile($result['data'][0]);
        }
        
        if (isset($result['data'][0]['name'])) {
            // File object with name/path
            $audioUrl = 'https://110602490-chatterbox-tts.hf.space/file=' . $result['data'][0]['name'];
            return $this->downloadAudioFile($audioUrl);
        }
        
        throw new Exception("No audio data found in Chatterbox TTS response");
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