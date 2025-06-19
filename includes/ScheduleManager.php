<?php

class ScheduleManager {
    private $schedulesFile;
    private $schedulesDir;
    
    public function __construct() {
        // Set timezone to Eastern Time for correct timestamps
        date_default_timezone_set('America/New_York');
        
        $this->schedulesDir = __DIR__ . '/../data/schedules';
        $this->ensureDirectoryExists($this->schedulesDir);
        $this->schedulesFile = $this->schedulesDir . '/schedules.json';
    }
    
    private function ensureDirectoryExists($dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    /**
     * Get all schedules
     */
    public function getAllSchedules() {
        if (!file_exists($this->schedulesFile)) {
            return [];
        }
        
        $content = file_get_contents($this->schedulesFile);
        $schedules = json_decode($content, true) ?: [];
        
        // Sort by name
        usort($schedules, function($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });
        
        return $schedules;
    }
    
    /**
     * Create a new schedule
     */
    public function createSchedule($scheduleData) {
        $schedules = $this->getAllSchedules();
        
        // Validate required fields
        if (empty($scheduleData['name']) || empty($scheduleData['time']) || empty($scheduleData['days'])) {
            throw new Exception('Schedule name, time, and days are required');
        }
        
        // Validate time format
        if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $scheduleData['time'])) {
            throw new Exception('Invalid time format. Use HH:MM format');
        }
        
        // Validate days
        $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        foreach ($scheduleData['days'] as $day) {
            if (!in_array($day, $validDays)) {
                throw new Exception('Invalid day: ' . $day);
            }
        }
        
        $schedule = [
            'id' => uniqid('sched_'),
            'name' => htmlspecialchars($scheduleData['name'], ENT_QUOTES, 'UTF-8'),
            'time' => $scheduleData['time'],
            'days' => $scheduleData['days'],
            'active' => $scheduleData['active'] ?? true,
            'settings' => $scheduleData['settings'] ?? [],
            'created_at' => time(),
            'last_run' => null,
            'next_run' => $this->calculateNextRun($scheduleData['time'], $scheduleData['days'])
        ];
        
        $schedules[] = $schedule;
        
        file_put_contents($this->schedulesFile, json_encode($schedules, JSON_PRETTY_PRINT));
        
        return $schedule['id'];
    }
    
    /**
     * Toggle schedule active/inactive
     */
    public function toggleSchedule($scheduleId) {
        $schedules = $this->getAllSchedules();
        
        foreach ($schedules as &$schedule) {
            if ($schedule['id'] === $scheduleId) {
                $schedule['active'] = !$schedule['active'];
                
                if ($schedule['active']) {
                    $schedule['next_run'] = $this->calculateNextRun($schedule['time'], $schedule['days']);
                } else {
                    $schedule['next_run'] = null;
                }
                
                file_put_contents($this->schedulesFile, json_encode($schedules, JSON_PRETTY_PRINT));
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Delete a schedule
     */
    public function deleteSchedule($scheduleId) {
        $schedules = $this->getAllSchedules();
        $originalCount = count($schedules);
        
        $schedules = array_filter($schedules, function($schedule) use ($scheduleId) {
            return $schedule['id'] !== $scheduleId;
        });
        
        if (count($schedules) < $originalCount) {
            file_put_contents($this->schedulesFile, json_encode(array_values($schedules), JSON_PRETTY_PRINT));
            return true;
        }
        
        return false;
    }
    
    /**
     * Get schedules that should run now
     */
    public function getSchedulesToRun() {
        $schedules = $this->getAllSchedules();
        $now = time();
        $currentDay = date('l'); // Monday, Tuesday, etc.
        $currentTime = date('H:i');
        
        $schedulesToRun = [];
        
        foreach ($schedules as $schedule) {
            if (!$schedule['active']) {
                continue;
            }
            
            // Check if today is in the schedule days
            if (!in_array($currentDay, $schedule['days'])) {
                continue;
            }
            
            // Check if it's time to run (within 1 minute window)
            $scheduleTime = strtotime($schedule['time']);
            $currentTimeSeconds = strtotime($currentTime);
            
            // Allow 1 minute window for execution
            if (abs($currentTimeSeconds - $scheduleTime) <= 60) {
                // Check if not already run today
                if (!$schedule['last_run'] || date('Y-m-d', $schedule['last_run']) !== date('Y-m-d')) {
                    $schedulesToRun[] = $schedule;
                }
            }
        }
        
        return $schedulesToRun;
    }
    
    /**
     * Execute a specific schedule
     */
    public function runSchedule($scheduleId) {
        require_once __DIR__ . '/NewsAPI.php';
        require_once __DIR__ . '/AIService.php';
        require_once __DIR__ . '/TTSService.php';
        require_once __DIR__ . '/BriefingHistory.php';
        
        $schedules = $this->getAllSchedules();
        
        foreach ($schedules as &$schedule) {
            if ($schedule['id'] === $scheduleId) {
                try {
                    // Generate briefing using the schedule's settings
                    $briefingResult = $this->generateScheduledBriefing($schedule['settings']);
                    
                    // Update last run time
                    $schedule['last_run'] = time();
                    $schedule['next_run'] = $this->calculateNextRun($schedule['time'], $schedule['days']);
                    
                    // Save updated schedules
                    file_put_contents($this->schedulesFile, json_encode($schedules, JSON_PRETTY_PRINT));
                    
                    // Save to briefing history
                    $briefingHistory = new BriefingHistory();
                    $briefingHistory->saveBriefing([
                        'topics' => $briefingResult['topics'] ?? [],
                        'text' => $briefingResult['text'] ?? '',
                        'audio_file' => $briefingResult['audio_file'] ?? null,
                        'duration' => $briefingResult['duration'] ?? 0,
                        'format' => 'mp3',
                        'sources' => $briefingResult['sources'] ?? []
                    ]);
                    
                    return true;
                } catch (Exception $e) {
                    error_log("Failed to run schedule {$scheduleId}: " . $e->getMessage());
                    return false;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Generate briefing based on schedule settings
     */
    private function generateScheduledBriefing($settings) {
        $newsAPI = new NewsAPI();
        $aiService = new AIService();
        
        // Get news based on settings
        $newsData = [];
        
        // Weather
        if ($settings['includeWeather'] ?? false) {
            $weatherData = $newsAPI->getWeatherData($settings['zipCode'] ?? '');
            if ($weatherData) {
                $newsData['weather'] = $weatherData;
            }
        }
        
        // TV/Movie news
        if ($settings['includeTV'] ?? false) {
            $tvData = $newsAPI->getTVMovieNews();
            if ($tvData) {
                $newsData['entertainment'] = $tvData;
            }
        }
        
        // Local news
        if ($settings['includeLocal'] ?? false) {
            $localData = $newsAPI->getLocalNews($settings['zipCode'] ?? '');
            if ($localData) {
                $newsData['local'] = $localData;
            }
        }
        
        // General news
        $generalNews = $newsAPI->getNews();
        if ($generalNews) {
            $newsData['general'] = $generalNews;
        }
        
        // Generate briefing text
        $aiModel = $settings['aiSelection'] ?? 'openai';
        $timeFrame = $settings['timeFrame'] ?? 'auto';
        $audioLength = $settings['audioLength'] ?? '5-10';
        $customHeader = $settings['customHeader'] ?? '';
        
        $briefingText = $aiService->generateBriefing($newsData, $aiModel, $timeFrame, $audioLength, $customHeader);
        
        $result = [
            'text' => $briefingText,
            'topics' => $this->extractTopicsFromNews($newsData),
            'sources' => $this->extractSourcesFromNews($newsData)
        ];
        
        // Generate audio if requested
        if ($settings['generateMp3'] ?? false) {
            $ttsService = new TTSService();
            $audioResult = $ttsService->generateAudio($briefingText);
            
            if ($audioResult['success']) {
                $result['audio_file'] = $audioResult['file'];
                $result['duration'] = $audioResult['duration'] ?? 0;
            }
        }
        
        return $result;
    }
    
    /**
     * Calculate next run time for a schedule
     */
    private function calculateNextRun($time, $days) {
        $currentDay = date('l');
        $currentTime = date('H:i');
        
        // Find next occurrence
        for ($i = 0; $i < 7; $i++) {
            $checkDate = date('Y-m-d', strtotime("+$i days"));
            $checkDay = date('l', strtotime($checkDate));
            
            if (in_array($checkDay, $days)) {
                // If it's today and time hasn't passed yet
                if ($i === 0 && $time > $currentTime) {
                    return strtotime("$checkDate $time");
                }
                // If it's a future day
                if ($i > 0) {
                    return strtotime("$checkDate $time");
                }
            }
        }
        
        return null;
    }
    
    /**
     * Extract topics from news data
     */
    private function extractTopicsFromNews($newsData) {
        $topics = [];
        
        foreach ($newsData as $category => $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['title'])) {
                        $topics[] = [
                            'title' => $item['title'],
                            'category' => $category,
                            'timestamp' => time()
                        ];
                    }
                }
            }
        }
        
        return $topics;
    }
    
    /**
     * Extract sources from news data
     */
    private function extractSourcesFromNews($newsData) {
        $sources = [];
        
        foreach ($newsData as $category => $items) {
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['url']) && isset($item['source'])) {
                        $sources[] = [
                            'title' => $item['title'] ?? 'News Article',
                            'url' => $item['url'],
                            'source' => $item['source'],
                            'category' => $category
                        ];
                    }
                }
            }
        }
        
        return $sources;
    }
    
    /**
     * Check and run due schedules (called by cron or scheduler)
     */
    public function runDueSchedules() {
        $schedulesToRun = $this->getSchedulesToRun();
        $results = [];
        
        foreach ($schedulesToRun as $schedule) {
            $result = $this->runSchedule($schedule['id']);
            $results[] = [
                'schedule_id' => $schedule['id'],
                'schedule_name' => $schedule['name'],
                'success' => $result
            ];
        }
        
        return $results;
    }
}
?>