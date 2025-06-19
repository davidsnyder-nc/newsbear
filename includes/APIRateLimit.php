<?php

class APIRateLimit {
    private $statusFile = '../data/api_status.json';
    
    public function __construct() {
        if (!is_dir('../data')) {
            mkdir('../data', 0777, true);
        }
    }
    
    public function isAPIPaused($apiName) {
        $status = $this->getAPIStatus();
        
        if (!isset($status[$apiName])) {
            return false;
        }
        
        $apiStatus = $status[$apiName];
        
        // Check if API is paused and if pause period has expired
        if (isset($apiStatus['paused_until'])) {
            if (time() < $apiStatus['paused_until']) {
                return true; // Still paused
            } else {
                // Pause period expired, remove pause
                unset($status[$apiName]['paused_until']);
                $this->saveAPIStatus($status);
                return false;
            }
        }
        
        return false;
    }
    
    public function pauseAPI($apiName, $reason = 'Rate limit exceeded', $durationMinutes = 30) {
        $status = $this->getAPIStatus();
        
        $status[$apiName] = [
            'paused_until' => time() + ($durationMinutes * 60),
            'reason' => $reason,
            'paused_at' => date('c'),
            'duration_minutes' => $durationMinutes
        ];
        
        $this->saveAPIStatus($status);
        error_log("API $apiName paused for $durationMinutes minutes: $reason");
    }
    
    public function recordSuccess($apiName) {
        $status = $this->getAPIStatus();
        
        // Clear any pause status on successful request
        if (isset($status[$apiName])) {
            unset($status[$apiName]);
            $this->saveAPIStatus($status);
        }
    }
    
    public function recordFailure($apiName, $reason) {
        $status = $this->getAPIStatus();
        
        if (!isset($status[$apiName])) {
            $status[$apiName] = [];
        }
        
        $status[$apiName]['last_failure'] = date('c');
        $status[$apiName]['last_failure_reason'] = $reason;
        
        // Auto-pause on rate limit
        if (strpos($reason, 'Rate limit') !== false || strpos($reason, '429') !== false) {
            $this->pauseAPI($apiName, $reason, 30);
        } elseif (strpos($reason, '403') !== false) {
            $this->pauseAPI($apiName, $reason, 60); // Longer pause for access forbidden
        }
        
        $this->saveAPIStatus($status);
    }
    
    public function getAPIStatus() {
        if (!file_exists($this->statusFile)) {
            return [];
        }
        
        $content = file_get_contents($this->statusFile);
        return json_decode($content, true) ?: [];
    }
    
    public function saveAPIStatus($status) {
        file_put_contents($this->statusFile, json_encode($status, JSON_PRETTY_PRINT));
    }
    
    public function getPausedAPIs() {
        $status = $this->getAPIStatus();
        $paused = [];
        
        foreach ($status as $apiName => $apiData) {
            if ($this->isAPIPaused($apiName)) {
                $paused[$apiName] = $apiData;
            }
        }
        
        return $paused;
    }
}
?>