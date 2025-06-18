<?php

class BriefingHistory {
    private $historyDir;
    private $dailyTopicsFile;
    private $briefingsFile;
    
    public function __construct() {
        $this->historyDir = __DIR__ . '/../data/history';
        $this->ensureDirectoryExists($this->historyDir);
        
        $today = date('Y-m-d');
        $this->dailyTopicsFile = $this->historyDir . "/topics_$today.json";
        $this->briefingsFile = $this->historyDir . "/briefings.json";
    }
    
    private function ensureDirectoryExists($dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    /**
     * Get topics already covered today - with time-based filtering
     */
    public function getTodaysTopics() {
        if (!file_exists($this->dailyTopicsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->dailyTopicsFile);
        $allTopics = json_decode($content, true) ?: [];
        
        // Get current time period
        $currentHour = (int)date('H');
        $currentPeriod = $this->getTimePeriod($currentHour);
        
        // Only filter topics if we're in a different time period
        // Morning: 5-11, Afternoon: 12-17, Evening: 18-23, Night: 0-4
        $filteredTopics = [];
        
        foreach ($allTopics as $topic) {
            if (isset($topic['timestamp'])) {
                $topicHour = (int)date('H', $topic['timestamp']);
                $topicPeriod = $this->getTimePeriod($topicHour);
                
                // Only include topics from earlier time periods
                if ($topicPeriod !== $currentPeriod) {
                    $filteredTopics[] = $topic['title'] ?? $topic;
                }
            } else {
                // Legacy format - include all topics
                $filteredTopics[] = $topic;
            }
        }
        
        return $filteredTopics;
    }
    
    private function getTimePeriod($hour) {
        if ($hour >= 5 && $hour <= 11) return 'morning';
        if ($hour >= 12 && $hour <= 17) return 'afternoon';
        if ($hour >= 18 && $hour <= 23) return 'evening';
        return 'night';
    }
    
    /**
     * Add topics covered in current briefing with timestamps
     */
    public function addTopics($topics) {
        // Load all topics (not filtered by time)
        $allTopics = [];
        if (file_exists($this->dailyTopicsFile)) {
            $content = file_get_contents($this->dailyTopicsFile);
            $allTopics = json_decode($content, true) ?: [];
        }
        
        // Add new topics with timestamps
        foreach ($topics as $topic) {
            $allTopics[] = [
                'title' => $topic,
                'timestamp' => time()
            ];
        }
        
        file_put_contents($this->dailyTopicsFile, json_encode($allTopics, JSON_PRETTY_PRINT));
    }
    
    /**
     * Save a briefing record
     */
    public function saveBriefing($briefingData) {
        $briefings = $this->getAllBriefings();
        
        $briefingRecord = [
            'id' => uniqid(),
            'timestamp' => time(),
            'date' => date('Y-m-d H:i:s'),
            'topics' => $briefingData['topics'] ?? [],
            'text' => $briefingData['text'] ?? '',
            'audio_file' => $briefingData['audio_file'] ?? null,
            'duration' => $briefingData['duration'] ?? 0,
            'format' => $briefingData['format'] ?? 'mp3',
            'sources' => $briefingData['sources'] ?? []
        ];
        
        array_unshift($briefings, $briefingRecord);
        
        // Keep only last 100 briefings
        if (count($briefings) > 100) {
            $briefings = array_slice($briefings, 0, 100);
        }
        
        file_put_contents($this->briefingsFile, json_encode($briefings, JSON_PRETTY_PRINT));
        
        return $briefingRecord['id'];
    }
    
    /**
     * Get all briefing records
     */
    public function getAllBriefings() {
        if (!file_exists($this->briefingsFile)) {
            return [];
        }
        
        $content = file_get_contents($this->briefingsFile);
        return json_decode($content, true) ?: [];
    }

    /**
     * Get total count of briefings
     */
    public function getBriefingCount() {
        return count($this->getAllBriefings());
    }

    /**
     * Get briefings with pagination
     */
    public function getBriefings($limit = 10, $offset = 0) {
        $allBriefings = $this->getAllBriefings();
        
        // Sort by timestamp descending (newest first)
        usort($allBriefings, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        return array_slice($allBriefings, $offset, $limit);
    }
    
    /**
     * Get briefings for a specific date
     */
    public function getBriefingsByDate($date) {
        $briefings = $this->getAllBriefings();
        return array_filter($briefings, function($briefing) use ($date) {
            return strpos($briefing['date'], $date) === 0;
        });
    }
    
    /**
     * Get a specific briefing by ID
     */
    public function getBriefingById($id) {
        $briefings = $this->getAllBriefings();
        foreach ($briefings as $briefing) {
            if ($briefing['id'] === $id) {
                return $briefing;
            }
        }
        return null;
    }
    
    /**
     * Extract topics from news content for tracking
     */
    public function extractTopics($newsContent) {
        $topics = [];
        
        // Extract topics from structured content
        if (is_array($newsContent)) {
            foreach ($newsContent as $item) {
                if (isset($item['title'])) {
                    $topics[] = $this->normalizeTopicTitle($item['title']);
                }
                if (isset($item['description'])) {
                    $keywords = $this->extractKeywords($item['description']);
                    $topics = array_merge($topics, $keywords);
                }
            }
        } else {
            // Extract from plain text
            $keywords = $this->extractKeywords($newsContent);
            $topics = array_merge($topics, $keywords);
        }
        
        return array_unique($topics);
    }
    
    private function normalizeTopicTitle($title) {
        // Remove common words and normalize
        $title = strtolower(trim($title));
        $title = preg_replace('/[^\w\s-]/', '', $title);
        return $title;
    }
    
    private function extractKeywords($text) {
        $text = strtolower($text);
        
        // Common news keywords to track
        $patterns = [
            '/\b(trump|biden|harris|congress|election|vote|campaign)\b/',
            '/\b(ukraine|russia|china|israel|gaza|iran|syria)\b/',
            '/\b(covid|vaccine|health|hospital|medical)\b/',
            '/\b(climate|weather|storm|hurricane|wildfire)\b/',
            '/\b(economy|inflation|market|stock|crypto|bitcoin)\b/',
            '/\b(tech|apple|google|microsoft|amazon|meta|tesla)\b/',
            '/\b(sports|nfl|nba|mlb|olympics|world cup)\b/',
        ];
        
        $keywords = [];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $text, $matches);
            $keywords = array_merge($keywords, $matches[0]);
        }
        
        return array_unique($keywords);
    }
    
    /**
     * Check if topics have been covered today
     */
    public function areTopicsCovered($topics) {
        $todaysTopics = $this->getTodaysTopics();
        
        foreach ($topics as $topic) {
            foreach ($todaysTopics as $coveredTopic) {
                if (stripos($coveredTopic, $topic) !== false || stripos($topic, $coveredTopic) !== false) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Delete a specific briefing by ID
     */
    public function deleteBriefing($id) {
        $briefings = $this->getAllBriefings();
        $briefingIndex = -1;
        $briefingToDelete = null;
        
        foreach ($briefings as $index => $briefing) {
            if ($briefing['id'] === $id) {
                $briefingIndex = $index;
                $briefingToDelete = $briefing;
                break;
            }
        }
        
        if ($briefingIndex === -1) {
            return false;
        }
        
        // Delete audio file if it exists
        if ($briefingToDelete['audio_file'] && file_exists($briefingToDelete['audio_file'])) {
            unlink($briefingToDelete['audio_file']);
        }
        
        // Remove from array
        array_splice($briefings, $briefingIndex, 1);
        
        // Save updated briefings
        file_put_contents($this->briefingsFile, json_encode($briefings, JSON_PRETTY_PRINT));
        
        return true;
    }
    
    /**
     * Clear all briefings older than specified days
     */
    public function clearOldBriefings($daysOld = 30) {
        $briefings = $this->getAllBriefings();
        $cutoff = time() - ($daysOld * 24 * 60 * 60);
        $deletedCount = 0;
        
        foreach ($briefings as $index => $briefing) {
            if ($briefing['timestamp'] < $cutoff) {
                // Delete audio file if it exists
                if ($briefing['audio_file'] && file_exists($briefing['audio_file'])) {
                    unlink($briefing['audio_file']);
                }
                unset($briefings[$index]);
                $deletedCount++;
            }
        }
        
        // Reindex array
        $briefings = array_values($briefings);
        
        // Save updated briefings
        file_put_contents($this->briefingsFile, json_encode($briefings, JSON_PRETTY_PRINT));
        
        return $deletedCount;
    }
    
    /**
     * Clear all briefings
     */
    public function clearAllBriefings() {
        $briefings = $this->getAllBriefings();
        $deletedCount = count($briefings);
        
        // Delete all audio files
        foreach ($briefings as $briefing) {
            if ($briefing['audio_file'] && file_exists($briefing['audio_file'])) {
                unlink($briefing['audio_file']);
            }
        }
        
        // Clear briefings file
        file_put_contents($this->briefingsFile, json_encode([], JSON_PRETTY_PRINT));
        
        return $deletedCount;
    }
    
    /**
     * Clean up old topic files (older than 7 days)
     */
    public function cleanupOldTopics() {
        $files = glob($this->historyDir . '/topics_*.json');
        $cutoff = time() - (7 * 24 * 60 * 60); // 7 days ago
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }
}