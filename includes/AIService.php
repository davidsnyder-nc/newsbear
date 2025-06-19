<?php

class AIService {
    private $openaiKey;
    private $geminiKey;
    private $claudeKey;
    private $openaiPrompt;
    private $geminiPrompt;
    
    public function __construct($settings = null) {
        if ($settings) {
            $this->openaiKey = ($settings['openaiEnabled'] ?? true) ? ($settings['openaiApiKey'] ?: getenv('OPENAI_API_KEY')) : null;
            $this->geminiKey = ($settings['geminiEnabled'] ?? true) ? ($settings['geminiApiKey'] ?: getenv('GEMINI_API_KEY')) : null;
            $this->claudeKey = ($settings['claudeEnabled'] ?? true) ? ($settings['claudeApiKey'] ?: getenv('CLAUDE_API_KEY')) : null;
            $this->openaiPrompt = $settings['openaiPrompt'] ?? 'You are a professional news editor and broadcaster. Generate clear, concise, and engaging content suitable for audio news briefings.';
            $this->geminiPrompt = $settings['geminiPrompt'] ?? 'You are a professional news editor and broadcaster. Generate clear, concise, and engaging content suitable for audio news briefings.';
        } else {
            $this->openaiKey = getenv('OPENAI_API_KEY');
            $this->geminiKey = getenv('GEMINI_API_KEY');
            $this->claudeKey = getenv('CLAUDE_API_KEY');
            $this->openaiPrompt = 'You are a professional news editor and broadcaster. Generate clear, concise, and engaging content suitable for audio news briefings.';
            $this->geminiPrompt = 'You are a professional news editor and broadcaster. Generate clear, concise, and engaging content suitable for audio news briefings.';
        }
    }
    
    public function generateText($prompt, $model = 'openai') {
        switch ($model) {
            case 'openai':
                return $this->callOpenAI($prompt);
            case 'gemini':
                return $this->callGemini($prompt);
            case 'claude':
                return $this->callClaude($prompt);
            default:
                throw new Exception("Unsupported AI model: {$model}");
        }
    }
    
    private function callOpenAI($prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $data = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $this->openaiPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => 2000,
            'temperature' => 0.7
        ];
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->openaiKey
        ];
        
        $response = $this->makeAPIRequest($url, $data, $headers);
        
        if ($response && isset($response['choices'][0]['message']['content'])) {
            return trim($response['choices'][0]['message']['content']);
        }
        
        throw new Exception('Failed to get response from OpenAI');
    }
    
    private function callGemini($prompt) {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->geminiKey}";
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $this->geminiPrompt . "\n\n" . $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2000
            ]
        ];
        
        $headers = [
            'Content-Type: application/json'
        ];
        
        $response = $this->makeAPIRequest($url, $data, $headers);
        
        if ($response && isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($response['candidates'][0]['content']['parts'][0]['text']);
        }
        
        throw new Exception('Failed to get response from Gemini');
    }
    
    private function callClaude($prompt) {
        $url = 'https://api.anthropic.com/v1/messages';
        
        $data = [
            'model' => 'claude-3-sonnet-20240229',
            'max_tokens' => 2000,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];
        
        $headers = [
            'Content-Type: application/json',
            'x-api-key: ' . $this->claudeKey,
            'anthropic-version: 2023-06-01'
        ];
        
        $response = $this->makeAPIRequest($url, $data, $headers);
        
        if ($response && isset($response['content'][0]['text'])) {
            return trim($response['content'][0]['text']);
        }
        
        throw new Exception('Failed to get response from Claude');
    }
    
    private function makeAPIRequest($url, $data, $headers) {
        $ch = curl_init();
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 45); // Reduced for faster generation
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            error_log("AI API cURL error: " . curl_error($ch));
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("AI API HTTP error: {$httpCode} - Response: {$response}");
            return null;
        }
        
        return json_decode($response, true);
    }
}
?>
