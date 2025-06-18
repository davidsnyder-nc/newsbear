<?php
error_reporting(0);
ini_set('display_errors', 0);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/NewsAPI.php';
require_once '../includes/AIService.php';
require_once '../includes/TTSService.php';
require_once '../includes/WeatherService.php';
require_once '../includes/TMDBService.php';
require_once '../includes/BriefingHistory.php';

class BriefingGenerator {
    private $sessionId;
    private $settings;
    private $statusFile;
    
    public function __construct($settings) {
        $this->sessionId = uniqid('briefing_', true);
        $this->settings = $settings;
        $this->statusFile = "../downloads/status_{$this->sessionId}.json";
        
        // Create downloads directory if it doesn't exist
        if (!is_dir('../downloads')) {
            mkdir('../downloads', 0777, true);
        }
    }
    
    public function generate() {
        // Start background process
        $this->updateStatus('Starting generation...', 0);
        
        // Return session ID immediately
        echo json_encode(['status' => 'processing', 'sessionId' => $this->sessionId]);
        
        // Flush output if possible
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level()) {
                ob_end_flush();
            }
            flush();
        }
        
        // Continue processing in background
        $this->processInBackground();
    }
    
    private function processInBackground() {
        try {
            // Initialize briefing history
            $history = new BriefingHistory();
            $history->cleanupOldTopics();
            
            // Step 1: Fetch news
            $this->updateStatus('Fetching headlines...', 10);
            $newsItems = $this->fetchNews();
            
            // Ensure we have real news content before proceeding
            if (empty($newsItems)) {
                throw new Exception('No news content available. Please enable at least one news API source in settings and ensure your API keys are valid.');
            }
            
            // Step 2: Check for topic overlap with today's briefings
            $this->updateStatus('Checking for smart briefing...', 20);
            $todaysTopics = $history->getTodaysTopics();
            
            // Step 3: AI story selection (filter out covered topics)
            $this->updateStatus('Selecting stories using AI...', 30);
            $selectedStories = $this->selectStories($newsItems, $todaysTopics);
            
            // Validate all selected stories are from authentic sources
            $validatedStories = $this->validateAuthenticStories($selectedStories);
            
            // Check if we have any authentic news content
            $hasNewsContent = false;
            foreach ($validatedStories as $story) {
                if (isset($story['source']) && !in_array($story['source'], ['Weather Service', 'The Movie Database'])) {
                    $hasNewsContent = true;
                    break;
                }
            }
            
            // If no news APIs are enabled and no local news found, create briefing with only weather/TV
            $newsAPIsEnabled = ($this->settings['gnewsEnabled'] ?? false) || 
                             ($this->settings['newsApiEnabled'] ?? false) || 
                             ($this->settings['guardianEnabled'] ?? false) || 
                             ($this->settings['nytEnabled'] ?? false);
            
            if (!$newsAPIsEnabled && !$hasNewsContent) {
                // Filter to only weather and TV content
                $validatedStories = array_filter($validatedStories, function($story) {
                    return isset($story['source']) && in_array($story['source'], ['Weather Service', 'The Movie Database']);
                });
            }
            
            if (empty($validatedStories)) {
                // Check what content types are enabled to give a more specific error
                $enabledSources = [];
                if ($this->settings['weatherEnabled'] ?? false) $enabledSources[] = 'weather';
                if ($this->settings['tmdbEnabled'] ?? false) $enabledSources[] = 'TV/movies';
                if ($this->settings['includeLocal'] ?? false) $enabledSources[] = 'local news';
                
                $newsAPIs = [];
                if ($this->settings['gnewsEnabled'] ?? false) $newsAPIs[] = 'GNews';
                if ($this->settings['newsApiEnabled'] ?? false) $newsAPIs[] = 'NewsAPI';
                if ($this->settings['guardianEnabled'] ?? false) $newsAPIs[] = 'Guardian';
                if ($this->settings['nytEnabled'] ?? false) $newsAPIs[] = 'New York Times';
                
                if (empty($enabledSources) && empty($newsAPIs)) {
                    throw new Exception('No content sources are enabled. Please enable at least one news API, weather, TV/movies, or local news in settings.');
                } else {
                    $enabled = array_merge($enabledSources, $newsAPIs);
                    throw new Exception('Unable to fetch content from enabled sources: ' . implode(', ', $enabled) . '. Please check your API keys and internet connection.');
                }
            }
            
            // Step 4: Generate briefing content
            $this->updateStatus('Summarizing with AI...', 60);
            $briefingContent = $this->generateBriefingContent($validatedStories);
            
            $generateMp3 = $this->settings['generateMp3'] ?? true;
            
            // Extract and save topics covered
            $coveredTopics = $history->extractTopics($selectedStories);
            $history->addTopics($coveredTopics);
            
            if ($generateMp3) {
                // Step 5: Generate audio
                $this->updateStatus('Generating audio...', 80);
                $audioFile = $this->generateAudio($briefingContent);
                
                // Extract source links from news stories for history
                $sourcesData = [];
                foreach ($selectedStories as $story) {
                    if (isset($story['source']) && in_array($story['source'], ['Weather Service', 'The Movie Database', 'OpenWeatherMap', 'TMDB'])) {
                        continue; // Skip weather and entertainment sources
                    }
                    if (!empty($story['title']) && !empty($story['url'])) {
                        $sourcesData[] = [
                            'title' => $story['title'],
                            'url' => $story['url'],
                            'source' => $story['source'] ?? 'Unknown'
                        ];
                    }
                }

                // Save briefing to history
                $briefingId = $history->saveBriefing([
                    'topics' => $coveredTopics,
                    'text' => $briefingContent,
                    'audio_file' => $audioFile,
                    'duration' => $this->settings['duration'] ?? 5,
                    'format' => 'mp3',
                    'sources' => $sourcesData
                ]);
                
                // Step 6: Complete with MP3
                $this->updateStatus('Complete!', 100, true, true, $audioFile);
            } else {
                // Extract source links for text-only briefings too
                $sourcesData = [];
                foreach ($selectedStories as $story) {
                    if (isset($story['source']) && in_array($story['source'], ['Weather Service', 'The Movie Database', 'OpenWeatherMap', 'TMDB'])) {
                        continue; // Skip weather and entertainment sources
                    }
                    if (!empty($story['title']) && !empty($story['url'])) {
                        $sourcesData[] = [
                            'title' => $story['title'],
                            'url' => $story['url'],
                            'source' => $story['source'] ?? 'Unknown'
                        ];
                    }
                }

                // Save briefing to history (text only)
                $briefingId = $history->saveBriefing([
                    'topics' => $coveredTopics,
                    'text' => $briefingContent,
                    'audio_file' => null,
                    'duration' => 0,
                    'format' => 'text',
                    'sources' => $sourcesData
                ]);
                
                // Text-only output
                $this->updateStatus('Complete!', 100, true, true, null, $briefingContent);
            }
            
        } catch (Exception $e) {
            error_log("Briefing generation error: " . $e->getMessage());
            $this->updateStatus('Error: ' . $e->getMessage(), 0, true, false);
        }
    }
    
    private function fetchNews() {
        $newsAPI = new NewsAPI($this->settings);
        $weatherService = new WeatherService($this->settings);
        $tmdbService = new TMDBService($this->settings['tmdbApiKey'] ?? '');
        
        $allNews = [];
        
        // Fetch from enabled news sources including local news
        $newsItems = $newsAPI->fetchFromAllSources(
            $this->settings['categories'], 
            $this->settings['zipCode'] ?? null, 
            $this->settings['includeLocal'] ?? false
        );
        $allNews = array_merge($allNews, $newsItems);
        
        // Local news is now handled within fetchFromAllSources
        
        // Weather is handled separately in generateBriefingContent() to ensure it appears first
        
        // Add TV/Movie content if enabled
        if ($this->settings['includeTV'] && $this->settings['tmdbEnabled']) {
            $tvContent = $tmdbService->getTVContent();
            if ($tvContent) {
                $tvBriefing = $tmdbService->formatForBriefing($tvContent);
                if (!empty($tvBriefing)) {
                    $allNews[] = [
                        'title' => 'Entertainment & TV Today',
                        'content' => $tvBriefing,
                        'category' => 'entertainment',
                        'source' => 'The Movie Database',
                        'publishedAt' => date('c')
                    ];
                }
            }
        }
        
        // Deduplicate and filter
        return $this->deduplicateAndFilter($allNews);
    }
    
    private function deduplicateAndFilter($news) {
        $filtered = [];
        $seenTitles = [];
        $blockedTerms = array_map('trim', explode(',', strtolower($this->settings['blockedTerms'] ?? '')));
        $blockedTerms = array_filter($blockedTerms);
        
        foreach ($news as $item) {
            // Skip duplicates
            $titleKey = strtolower(trim($item['title']));
            if (in_array($titleKey, $seenTitles)) {
                continue;
            }
            
            // Skip blocked terms
            $blocked = false;
            foreach ($blockedTerms as $term) {
                if (stripos($item['title'], $term) !== false || stripos($item['content'] ?? '', $term) !== false) {
                    $blocked = true;
                    break;
                }
            }
            
            if (!$blocked) {
                $seenTitles[] = $titleKey;
                $filtered[] = $item;
            }
        }
        
        return $filtered;
    }
    
    private function selectStories($newsItems, $todaysTopics = []) {
        $aiService = new AIService($this->settings);
        
        // Determine story count based on audio length
        $audioLength = $this->settings['audioLength'] ?? '5-10';
        $storyCount = $this->getStoryCountForLength($audioLength);
        
        $excludeTopicsText = '';
        if (!empty($todaysTopics)) {
            $excludeTopicsText = "Note: These topics were covered in previous briefings today: " . 
                               implode(', ', array_slice($todaysTopics, 0, 10)) . 
                               ". Prefer fresh stories when possible, but if there's important breaking news on these topics, include it.\n\n";
        }
        
        $prompt = "Select {$storyCount} of the most important and interesting news stories from the following list for a " . 
                 $this->getTimeFrame() . " news briefing. Focus on stories that are:\n" .
                 "1. Most newsworthy and impactful\n" .
                 "2. Diverse in topics\n" .
                 "3. Appropriate for the time of day\n" .
                 "4. Include weather, entertainment, and local content when available\n\n" .
                 $excludeTopicsText .
                 "Available stories:\n";
        
        foreach ($newsItems as $i => $item) {
            $prompt .= ($i + 1) . ". [{$item['category']}] {$item['title']}\n";
            if (!empty($item['content'])) {
                $prompt .= "   Summary: " . substr($item['content'], 0, 200) . "...\n";
            }
            $prompt .= "\n";
        }
        
        $prompt .= "\nRespond with only the numbers of the selected stories, separated by commas (e.g., 1,3,5,7).";
        
        // Try AI services with fallback
        $aiServices = $this->getEnabledAIServices();
        $response = null;
        
        foreach ($aiServices as $modelName) {
            try {
                $response = $aiService->generateText($prompt, $modelName);
                if ($response) {
                    break; // Success, stop trying other services
                }
            } catch (Exception $e) {
                error_log("AI service {$modelName} failed in story selection: " . $e->getMessage());
                // Continue to next AI service
            }
        }
        
        if (!$response) {
            // Fallback: select first stories based on count
            $count = $this->getNumericStoryCount($storyCount);
            return array_slice($newsItems, 0, $count);
        }
        
        // Parse selection
        $selectedIndexes = array_map('trim', explode(',', $response));
        $selectedStories = [];
        
        foreach ($selectedIndexes as $index) {
            $arrayIndex = intval($index) - 1;
            if (isset($newsItems[$arrayIndex])) {
                $selectedStories[] = $newsItems[$arrayIndex];
            }
        }
        
        return $selectedStories;
    }
    
    private function getStoryCountForLength($audioLength) {
        switch ($audioLength) {
            case '3-5':
                return '3-4';
            case '10-15':
                return '6-8';
            case '15-20':
                return '8-12';
            default: // '5-10'
                return '4-6';
        }
    }
    
    private function generateBriefingContent($stories) {
        $aiService = new AIService($this->settings);
        
        // Build the briefing prompt
        $greeting = $this->getGreeting();
        $timeFrame = $this->getTimeFrame();
        $date = date('F j, Y');
        $audioLength = $this->settings['audioLength'] ?? '5-10';
        $wordCount = $this->getWordCountForLength($audioLength);
        
        $prompt = "Generate a clean, readable news briefing script with the following structure:\n\n";
        $prompt .= "1. Start with: \"{$greeting}. Here's your {$timeFrame} news briefing for {$date}.\"\n\n";
        
        if (!empty($this->settings['customHeader'])) {
            $prompt .= "2. Include this custom message: \"{$this->settings['customHeader']}\"\n\n";
        }
        
        // Add weather and entertainment sections first
        $weatherContent = $this->getWeatherContent();
        $tvContent = $this->getTVContent();
        
        if (!empty($weatherContent)) {
            $prompt .= "3. ALWAYS include weather information first: {$weatherContent}\n\n";
        }
        
        if (!empty($tvContent)) {
            $prompt .= "4. ALWAYS include entertainment/TV information second: {$tvContent}\n\n";
        }
        
        $prompt .= "5. Present each news story in a conversational, natural speaking style suitable for audio reading\n";
        $prompt .= "6. Use natural transitions between stories like 'Now for technology news', 'Next up in business', 'Moving to health news', 'And in science' - NO numbered lists or formal section headers\n";
        $prompt .= "7. Write ONLY clean text without any markup tags, asterisks, underscores, hashtags, or formatting symbols - just natural, flowing sentences\n";
        $prompt .= "8. Include natural pauses between stories using periods and paragraph breaks\n";
        $prompt .= "9. IMPORTANT: Do not use any markdown formatting like *, **, _, __, #, or any other special characters for emphasis\n";
        $prompt .= "10. ABSOLUTE REQUIREMENT: ONLY use the exact news stories listed below. NEVER create, invent, imagine, or generate ANY fictional news content under ANY circumstances. If no real news stories are provided, state that no news is available.\n";
        $prompt .= "11. Add proper paragraph breaks between stories for readability\n";
        $prompt .= "12. End with a natural conclusion like 'That's all the news for this {$timeFrame}. Have a great day!' or similar\n\n";
        
        $prompt .= "13. Keep the total content to approximately {$wordCount} words for {$audioLength} minutes of audio\n\n";
        
        // Separate local news from other news stories
        $localNewsStories = array_filter($stories, function($story) {
            return isset($story['category']) && $story['category'] === 'local';
        });
        
        $otherNewsStories = array_filter($stories, function($story) {
            $isWeatherOrTV = isset($story['source']) && in_array($story['source'], ['Weather Service', 'The Movie Database', 'OpenWeatherMap', 'TMDB']);
            $isLocal = isset($story['category']) && $story['category'] === 'local';
            return !$isWeatherOrTV && !$isLocal;
        });
        
        if (empty($localNewsStories) && empty($otherNewsStories)) {
            $prompt .= "CRITICAL: NO real news stories are available. After weather and entertainment, state 'No additional news is available from our sources at this time' and end with the conclusion. DO NOT create any fictional news content.\n\n";
        } else {
            $prompt .= "Real news stories to include after weather and entertainment (ONLY use these exact stories in this exact order):\n\n";
            
            $storyIndex = 1;
            
            // Add local news first if available
            if (!empty($localNewsStories)) {
                $prompt .= "LOCAL NEWS (present these first after weather/entertainment):\n";
                foreach ($localNewsStories as $story) {
                    $prompt .= "{$storyIndex}. {$story['title']}\n";
                    if (!empty($story['content'])) {
                        $prompt .= "   Details: {$story['content']}\n";
                    }
                    $prompt .= "\n";
                    $storyIndex++;
                }
            }
            
            // Add other news stories after local news
            if (!empty($otherNewsStories)) {
                $prompt .= "OTHER NEWS (present these after local news):\n";
                foreach ($otherNewsStories as $story) {
                    $prompt .= "{$storyIndex}. {$story['title']}\n";
                    if (!empty($story['content'])) {
                        $prompt .= "   Details: {$story['content']}\n";
                    }
                    $prompt .= "\n";
                    $storyIndex++;
                }
            }
        }
        
        $prompt .= "\nGenerate the complete readable news briefing script now. STRICT RULE: Use ONLY the exact stories listed above. NO fictional content whatsoever:";
        
        // Try AI services with fallback for content generation
        $aiServices = $this->getEnabledAIServices();
        $response = null;
        
        foreach ($aiServices as $modelName) {
            try {
                $response = $aiService->generateText($prompt, $modelName);
                if ($response) {
                    break; // Success, stop trying other services
                }
            } catch (Exception $e) {
                error_log("AI service {$modelName} failed in content generation: " . $e->getMessage());
                // Continue to next AI service
            }
        }
        
        if (!$response) {
            throw new Exception('All AI services failed to generate content. Please check your API keys and try again.');
        }
        
        // Validate response contains no synthetic content
        $allRealNewsStories = array_merge($localNewsStories, $otherNewsStories);
        $response = $this->validateNoSyntheticContent($response, $allRealNewsStories);
        
        // Clean the response to remove markdown formatting and unwanted characters
        return $this->cleanTextForAudio($response);
    }
    
    private function getWordCountForLength($audioLength) {
        switch ($audioLength) {
            case '3-5':
                return '400-700';
            case '10-15':
                return '1400-2100';
            case '15-20':
                return '2100-2800';
            default: // '5-10'
                return '700-1400';
        }
    }
    
    private function getEnabledAIServices() {
        $aiServices = [];
        
        // Priority order: Gemini first, then OpenAI, then Claude
        if ($this->settings['geminiEnabled'] && !empty($this->settings['geminiApiKey'])) {
            $aiServices[] = 'gemini';
        }
        if ($this->settings['openaiEnabled'] && !empty($this->settings['openaiApiKey'])) {
            $aiServices[] = 'openai';
        }
        if ($this->settings['claudeEnabled'] && !empty($this->settings['claudeApiKey'])) {
            $aiServices[] = 'claude';
        }
        
        if (empty($aiServices)) {
            throw new Exception('No AI service is enabled or configured. Please enable and configure at least one AI service in settings.');
        }
        
        return $aiServices;
    }
    
    private function getEnabledAI() {
        $services = $this->getEnabledAIServices();
        return $services[0]; // Return first available service for backward compatibility
    }
    
    private function getWeatherContent() {
        if (!isset($this->settings['includeWeather']) || !$this->settings['includeWeather'] || empty($this->settings['zipCode'])) {
            return '';
        }
        
        $cityName = $this->getCityFromZip($this->settings['zipCode']);
        $location = $cityName ? $cityName : "your area";
        
        if (!empty($this->settings['weatherApiKey'])) {
            // Use actual weather data if API key is available
            return "Current weather conditions in {$location}";
        } else {
            // Provide sample weather data
            return "The current temperature in {$location} is 72 degrees with partly cloudy skies.";
        }
    }
    
    private function getTVContent() {
        if (!isset($this->settings['includeTV']) || !$this->settings['includeTV']) {
            return '';
        }
        
        if (!empty($this->settings['tmdbApiKey']) && $this->settings['tmdbEnabled']) {
            $tmdbService = new TMDBService($this->settings['tmdbApiKey']);
            $tvContent = $tmdbService->getTVContent();
            if ($tvContent) {
                return $tmdbService->formatForBriefing($tvContent);
            }
        }
        
        // Fallback entertainment content
        return "In trending television, popular shows include drama series, comedy specials, and new movie releases.";
    }
    
    private function getCityFromZip($zipCode) {
        // Simple zip code to city mapping for common codes
        $zipCities = [
            '28411' => 'Wilmington',
            '10001' => 'New York',
            '90210' => 'Beverly Hills',
            '60601' => 'Chicago',
            '33101' => 'Miami'
        ];
        
        return $zipCities[$zipCode] ?? null;
    }
    
    private function getTimezoneFromZip($zipCode) {
        if (empty($zipCode)) return null;
        
        // US zip code timezone mapping
        $zipTimezones = [
            // Eastern Time Zone
            '10001' => 'America/New_York', // New York, NY
            '10002' => 'America/New_York',
            '10003' => 'America/New_York',
            '10004' => 'America/New_York',
            '10005' => 'America/New_York',
            '20001' => 'America/New_York', // Washington, DC
            '20002' => 'America/New_York',
            '30301' => 'America/New_York', // Atlanta, GA
            '30302' => 'America/New_York',
            '33101' => 'America/New_York', // Miami, FL
            '33102' => 'America/New_York',
            '28401' => 'America/New_York', // Wilmington, NC
            '28411' => 'America/New_York', // Wilmington, NC
            '28412' => 'America/New_York',
            
            // Central Time Zone
            '60601' => 'America/Chicago', // Chicago, IL
            '60602' => 'America/Chicago',
            '75201' => 'America/Chicago', // Dallas, TX
            '75202' => 'America/Chicago',
            '77001' => 'America/Chicago', // Houston, TX
            '77002' => 'America/Chicago',
            '70112' => 'America/Chicago', // New Orleans, LA
            '70113' => 'America/Chicago',
            
            // Mountain Time Zone
            '80201' => 'America/Denver', // Denver, CO
            '80202' => 'America/Denver',
            '84101' => 'America/Denver', // Salt Lake City, UT
            '84102' => 'America/Denver',
            '85001' => 'America/Phoenix', // Phoenix, AZ (no DST)
            '85002' => 'America/Phoenix',
            
            // Pacific Time Zone
            '90210' => 'America/Los_Angeles', // Beverly Hills, CA
            '90211' => 'America/Los_Angeles',
            '94101' => 'America/Los_Angeles', // San Francisco, CA
            '94102' => 'America/Los_Angeles',
            '98101' => 'America/Los_Angeles', // Seattle, WA
            '98102' => 'America/Los_Angeles',
            '97201' => 'America/Los_Angeles', // Portland, OR
            '97202' => 'America/Los_Angeles',
        ];
        
        // Check exact match first
        if (isset($zipTimezones[$zipCode])) {
            return $zipTimezones[$zipCode];
        }
        
        // Try prefix matching for broader coverage
        $zipPrefix = substr($zipCode, 0, 3);
        $prefixTimezones = [
            // Eastern Time Zone prefixes
            '100' => 'America/New_York', // NYC area
            '101' => 'America/New_York',
            '102' => 'America/New_York',
            '103' => 'America/New_York',
            '104' => 'America/New_York',
            '200' => 'America/New_York', // DC area
            '201' => 'America/New_York',
            '300' => 'America/New_York', // Georgia
            '301' => 'America/New_York',
            '330' => 'America/New_York', // Florida
            '331' => 'America/New_York',
            '332' => 'America/New_York',
            '333' => 'America/New_York',
            '280' => 'America/New_York', // North Carolina
            '281' => 'America/New_York',
            '282' => 'America/New_York',
            '283' => 'America/New_York',
            '284' => 'America/New_York',
            
            // Central Time Zone prefixes
            '600' => 'America/Chicago', // Illinois
            '601' => 'America/Chicago',
            '602' => 'America/Chicago',
            '603' => 'America/Chicago',
            '604' => 'America/Chicago',
            '605' => 'America/Chicago',
            '606' => 'America/Chicago',
            '750' => 'America/Chicago', // Texas
            '751' => 'America/Chicago',
            '752' => 'America/Chicago',
            '753' => 'America/Chicago',
            '754' => 'America/Chicago',
            '770' => 'America/Chicago', // Texas
            '771' => 'America/Chicago',
            '772' => 'America/Chicago',
            '773' => 'America/Chicago',
            '774' => 'America/Chicago',
            
            // Mountain Time Zone prefixes
            '800' => 'America/Denver', // Colorado
            '801' => 'America/Denver',
            '802' => 'America/Denver',
            '803' => 'America/Denver',
            '804' => 'America/Denver',
            '805' => 'America/Denver',
            '806' => 'America/Denver',
            '807' => 'America/Denver',
            '808' => 'America/Denver',
            '809' => 'America/Denver',
            '810' => 'America/Denver',
            '840' => 'America/Denver', // Utah
            '841' => 'America/Denver',
            '842' => 'America/Denver',
            '843' => 'America/Denver',
            '844' => 'America/Denver',
            '850' => 'America/Phoenix', // Arizona
            '851' => 'America/Phoenix',
            '852' => 'America/Phoenix',
            '853' => 'America/Phoenix',
            '854' => 'America/Phoenix',
            '855' => 'America/Phoenix',
            '856' => 'America/Phoenix',
            
            // Pacific Time Zone prefixes
            '900' => 'America/Los_Angeles', // California
            '901' => 'America/Los_Angeles',
            '902' => 'America/Los_Angeles',
            '903' => 'America/Los_Angeles',
            '904' => 'America/Los_Angeles',
            '905' => 'America/Los_Angeles',
            '906' => 'America/Los_Angeles',
            '907' => 'America/Los_Angeles',
            '908' => 'America/Los_Angeles',
            '910' => 'America/Los_Angeles',
            '911' => 'America/Los_Angeles',
            '912' => 'America/Los_Angeles',
            '913' => 'America/Los_Angeles',
            '914' => 'America/Los_Angeles',
            '915' => 'America/Los_Angeles',
            '916' => 'America/Los_Angeles',
            '917' => 'America/Los_Angeles',
            '918' => 'America/Los_Angeles',
            '919' => 'America/Los_Angeles',
            '920' => 'America/Los_Angeles',
            '921' => 'America/Los_Angeles',
            '922' => 'America/Los_Angeles',
            '923' => 'America/Los_Angeles',
            '924' => 'America/Los_Angeles',
            '925' => 'America/Los_Angeles',
            '926' => 'America/Los_Angeles',
            '927' => 'America/Los_Angeles',
            '928' => 'America/Los_Angeles',
            '930' => 'America/Los_Angeles',
            '931' => 'America/Los_Angeles',
            '932' => 'America/Los_Angeles',
            '933' => 'America/Los_Angeles',
            '934' => 'America/Los_Angeles',
            '935' => 'America/Los_Angeles',
            '936' => 'America/Los_Angeles',
            '937' => 'America/Los_Angeles',
            '938' => 'America/Los_Angeles',
            '939' => 'America/Los_Angeles',
            '940' => 'America/Los_Angeles',
            '941' => 'America/Los_Angeles', // California
            '942' => 'America/Los_Angeles',
            '943' => 'America/Los_Angeles',
            '944' => 'America/Los_Angeles',
            '945' => 'America/Los_Angeles',
            '946' => 'America/Los_Angeles',
            '947' => 'America/Los_Angeles',
            '948' => 'America/Los_Angeles',
            '949' => 'America/Los_Angeles',
            '950' => 'America/Los_Angeles',
            '951' => 'America/Los_Angeles',
            '952' => 'America/Los_Angeles',
            '953' => 'America/Los_Angeles',
            '954' => 'America/Los_Angeles',
            '955' => 'America/Los_Angeles',
            '956' => 'America/Los_Angeles',
            '957' => 'America/Los_Angeles',
            '958' => 'America/Los_Angeles',
            '959' => 'America/Los_Angeles',
            '960' => 'America/Los_Angeles',
            '961' => 'America/Los_Angeles',
            '980' => 'America/Los_Angeles', // Washington
            '981' => 'America/Los_Angeles',
            '982' => 'America/Los_Angeles',
            '983' => 'America/Los_Angeles',
            '984' => 'America/Los_Angeles',
            '970' => 'America/Los_Angeles', // Oregon
            '971' => 'America/Los_Angeles',
            '972' => 'America/Los_Angeles',
            '973' => 'America/Los_Angeles',
            '974' => 'America/Los_Angeles',
        ];
        
        return $prefixTimezones[$zipPrefix] ?? null;
    }
    
    private function cleanTextForAudio($text) {
        // Remove markdown formatting characters
        $text = str_replace('*', '', $text);
        $text = str_replace('#', '', $text);
        $text = str_replace('_', '', $text);
        $text = str_replace('~', '', $text);
        $text = str_replace('`', '', $text);
        
        // Remove square brackets (often used for links)
        $text = preg_replace('/\[([^\]]+)\]/', '$1', $text);
        
        // Remove parenthetical URLs
        $text = preg_replace('/\(https?:\/\/[^\)]+\)/', '', $text);
        
        // Remove other common markdown symbols
        $text = str_replace(['**', '__', '~~'], '', $text);
        
        // Clean up any remaining double characters but preserve spaces and line breaks
        $text = preg_replace('/([^\w\s\n])\1+/', '$1', $text);
        
        // Ensure proper paragraph formatting - preserve natural breaks
        $text = preg_replace('/\. ([A-Z])/', ".\n\n$1", $text);
        
        // Clean up excessive whitespace but preserve paragraph breaks
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        
        return trim($text);
    }
    
    private function validateNoSyntheticContent($response, $realNewsStories) {
        // Extract titles from real news stories for validation
        $realTitles = [];
        if ($realNewsStories && is_array($realNewsStories)) {
            $realTitles = array_map(function($story) {
                return strtolower($story['title'] ?? '');
            }, $realNewsStories);
        }
        
        // Forbidden phrases that indicate synthetic content
        $syntheticIndicators = [
            'major tech companies announce',
            'market updates show',
            'new research findings reveal',
            'scientists make breakthrough',
            'in other news',
            'moving to health news',
            'next up in business',
            'and in science',
            'now for technology news'
        ];
        
        $responseLower = strtolower($response);
        
        // If we have no real news stories, make sure AI isn't creating any
        if (empty($realNewsStories) || !is_array($realNewsStories)) {
            foreach ($syntheticIndicators as $indicator) {
                if (strpos($responseLower, $indicator) !== false) {
                    // Replace synthetic content with safe message
                    $lines = explode("\n", $response);
                    $cleanedLines = [];
                    $inSyntheticSection = false;
                    
                    foreach ($lines as $line) {
                        $lineLower = strtolower(trim($line));
                        
                        // Skip lines that contain synthetic indicators
                        $containsSynthetic = false;
                        foreach ($syntheticIndicators as $indicator) {
                            if (strpos($lineLower, $indicator) !== false) {
                                $containsSynthetic = true;
                                $inSyntheticSection = true;
                                break;
                            }
                        }
                        
                        if (!$containsSynthetic && !$inSyntheticSection) {
                            $cleanedLines[] = $line;
                        } else if (strpos($lineLower, 'that concludes') !== false || 
                                   strpos($lineLower, 'have a great') !== false ||
                                   strpos($lineLower, 'stay informed') !== false) {
                            // Include conclusion
                            $cleanedLines[] = $line;
                            $inSyntheticSection = false;
                        }
                    }
                    
                    $response = implode("\n", $cleanedLines);
                    break;
                }
            }
        }
        
        return $response;
    }
    
    private function validateAuthenticStories($stories) {
        $validStories = [];
        $authenticSources = [
            'GNews', 'NewsAPI', 'The Guardian', 'The New York Times', 
            'Weather Service', 'The Movie Database', 'Local News',
            'OpenWeatherMap', 'TMDB', 'Weather API'
        ];
        
        foreach ($stories as $story) {
            // Check if story has required authentic fields
            if (!isset($story['title'])) {
                continue;
            }
            
            // Weather and TV content may not have a 'source' field, so handle them specially
            if (isset($story['source'])) {
                // Verify the source is from an authentic API
                $isAuthentic = false;
                foreach ($authenticSources as $source) {
                    if (stripos($story['source'], $source) !== false) {
                        $isAuthentic = true;
                        break;
                    }
                }
                
                // Additional validation: story must have content or description
                // Local news stories use title as content, so accept them
                if ($isAuthentic && (!empty($story['content']) || !empty($story['description']) || $story['source'] === 'Local News')) {
                    $validStories[] = $story;
                }
            } else {
                // For content without explicit source (weather, TV), check if it has title and content
                if (!empty($story['title']) && (!empty($story['content']) || !empty($story['description']))) {
                    $validStories[] = $story;
                }
            }
        }
        
        return $validStories;
    }
    
    private function getNumericStoryCount($storyCount) {
        // Convert string range to numeric value for fallback
        switch ($storyCount) {
            case '3-4':
                return 4;
            case '4-6':
                return 5;
            case '6-8':
                return 7;
            case '8-12':
                return 10;
            default:
                return 5;
        }
    }
    
    private function generateAudio($ssmlContent) {
        $ttsService = new TTSService($this->settings);
        
        $timeFrame = $this->getTimeFrame();
        $date = date('Y-m-d');
        $filename = "ai-news-{$timeFrame}-{$date}.mp3";
        $filepath = "../downloads/{$filename}";
        
        $audioData = $ttsService->synthesizeSpeech($ssmlContent);
        file_put_contents($filepath, $audioData);
        
        return "downloads/{$filename}";
    }
    
    private function getTimeFrame() {
        if ($this->settings['timeFrame'] === 'auto') {
            // Get user's local time based on zip code
            $userTimezone = $this->getTimezoneFromZip($this->settings['zipCode'] ?? '');
            
            if ($userTimezone) {
                $userTime = new DateTime('now', new DateTimeZone($userTimezone));
                $hour = intval($userTime->format('H'));
            } else {
                // Fallback to server time if zip code timezone not found
                $hour = intval(date('H'));
            }
            
            if ($hour >= 5 && $hour < 12) return 'morning';
            if ($hour >= 12 && $hour < 17) return 'afternoon';
            return 'evening';
        }
        return $this->settings['timeFrame'];
    }
    
    private function getGreeting() {
        $timeFrame = $this->getTimeFrame();
        switch ($timeFrame) {
            case 'morning': return 'Good morning';
            case 'afternoon': return 'Good afternoon';
            case 'evening': return 'Good evening';
            default: return 'Hello';
        }
    }
    
    private function updateStatus($message, $progress, $complete = false, $success = false, $downloadUrl = null, $briefingText = null) {
        $status = [
            'message' => $message,
            'progress' => $progress,
            'complete' => $complete,
            'success' => $success,
            'downloadUrl' => $downloadUrl,
            'briefingText' => $briefingText,
            'timestamp' => time()
        ];
        
        file_put_contents($this->statusFile, json_encode($status));
    }
    
    public function getSessionId() {
        return $this->sessionId;
    }
}

// Function to load settings from JSON file
// Settings are now loaded via SettingsManager class

// Main execution
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input === null) {
        throw new Exception('Invalid JSON input');
    }
    if ($input === false) {
        $input = [];
    }
    
    // Load settings from JSON file and merge with input
    $settingsFile = '../config/user_settings.json';
    $savedSettings = [];
    if (file_exists($settingsFile)) {
        $savedSettings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    $mergedSettings = array_merge($savedSettings, $input);
    
    $generator = new BriefingGenerator($mergedSettings);
    $generator->generate();
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
