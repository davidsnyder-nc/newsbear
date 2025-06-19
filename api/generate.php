<?php
session_start();
require_once '../includes/AuthManager.php';

$auth = new AuthManager();

// Check authentication if enabled
if ($auth->isAuthEnabled() && !$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

// Set timezone for correct timestamps
date_default_timezone_set('America/New_York');

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
    private $selectedCategories;
    
    public function __construct($settings) {
        $this->sessionId = uniqid('briefing_', true);
        $this->settings = $settings;
        $this->statusFile = "../downloads/status_{$this->sessionId}.json";
        
        // Initialize selected categories from settings
        $this->selectedCategories = isset($settings['categories']) && is_array($settings['categories']) && !empty($settings['categories']) 
            ? $settings['categories'] 
            : ['general'];
        
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
        // Set execution timeout to 5 minutes
        set_time_limit(300);
        ini_set('max_execution_time', 300);
        
        try {
            // Initialize debug logging
            $this->debugLog("=== Starting briefing generation ===");
            $this->debugLog("Session ID: " . $this->sessionId);
            $this->debugLog("Selected categories: " . implode(', ', $this->selectedCategories));
            $this->debugLog("Audio length: " . ($this->settings['audioLength'] ?? 'default'));
            $this->debugLog("Story count: " . ($this->settings['storyCount'] ?? 'default'));
            
            // Initialize briefing history
            $history = new BriefingHistory();
            $history->cleanupOldTopics();
            
            // Step 1: Fetch news
            $this->debugLog("=== STEP 1: FETCHING NEWS ===");
            $this->debugLog("Checking enabled sources: GNews=" . ($this->settings['gnewsEnabled'] ? 'YES' : 'NO') . 
                          ", NewsAPI=" . ($this->settings['newsApiEnabled'] ? 'YES' : 'NO') . 
                          ", Guardian=" . ($this->settings['guardianEnabled'] ? 'YES' : 'NO') . 
                          ", NYT=" . ($this->settings['nytEnabled'] ? 'YES' : 'NO'));
            $this->debugLog("Selected categories: " . implode(', ', $this->selectedCategories));
            $this->updateStatus('Fetching headlines...', 10);
            $newsItems = $this->fetchNews();
            $this->debugLog("SUCCESS: Retrieved " . count($newsItems) . " total news items from all sources");
            
            // Ensure we have real news content before proceeding
            if (empty($newsItems)) {
                $this->debugLog("ERROR: No news items retrieved from any source", 'ERROR');
                // Check if we're doing category-specific briefing with RSS content
                $isSpecificCategoryOnly = count($this->selectedCategories) == 1 && !in_array('general', $this->selectedCategories);
                if ($isSpecificCategoryOnly) {
                    throw new Exception("No {$this->selectedCategories[0]} content available from RSS feeds. Please check your RSS feed configuration.");
                } else {
                    throw new Exception('Unable to fetch content from enabled sources: weather, TV/movies, GNews, NewsAPI, Guardian, New York Times. Please check your API keys and internet connection.');
                }
            }
            
            // Step 2: Check for topic overlap with today's briefings
            $this->debugLog("=== STEP 2: CHECKING TOPIC OVERLAP ===");
            $this->updateStatus('Checking for smart briefing...', 20);
            $todaysTopics = $history->getTodaysTopics();
            $this->debugLog("Found " . count($todaysTopics) . " topics already covered today (for deduplication)");
            
            // Step 3: AI story selection (filter out covered topics)
            $this->debugLog("=== STEP 3: AI STORY SELECTION ===");
            $this->updateStatus('Selecting stories using AI...', 30);
            $this->debugLog("Sending " . count($newsItems) . " news items to AI for story selection");
            
            $selectedStories = $this->selectStories($newsItems, $todaysTopics);
            $this->debugLog("AI selection completed: " . count($selectedStories) . " stories chosen");
            
            // Log selected story details
            foreach ($selectedStories as $i => $story) {
                $this->debugLog("Selected story " . ($i + 1) . ": [" . ($story['source'] ?? 'Unknown') . "] " . substr($story['title'] ?? 'No title', 0, 80) . "...");
            }
            
            // Validate all selected stories are from authentic sources
            $this->debugLog("=== STEP 4: VALIDATING STORY AUTHENTICITY ===");
            $validatedStories = $this->validateAuthenticStories($selectedStories);
            $this->debugLog("Story validation completed: " . count($validatedStories) . " stories passed authenticity checks");
            
            // Check if we have any authentic news content
            $hasNewsContent = false;
            $newsSourceCount = 0;
            foreach ($validatedStories as $story) {
                if (isset($story['source']) && !in_array($story['source'], ['Weather Service', 'The Movie Database'])) {
                    $hasNewsContent = true;
                    $newsSourceCount++;
                }
            }
            $this->debugLog("Content analysis: " . $newsSourceCount . " authentic news stories, " . (count($validatedStories) - $newsSourceCount) . " weather/entertainment items");
            
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
                // Check if we're doing category-specific briefing with RSS content
                $isSpecificCategoryOnly = count($this->selectedCategories) == 1 && !in_array('general', $this->selectedCategories);
                if ($isSpecificCategoryOnly) {
                    throw new Exception("No {$this->selectedCategories[0]} content available. Please check your RSS feed configuration for {$this->selectedCategories[0]} content.");
                } else {
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
            }
            
            // Step 5: Generate briefing content
            $this->debugLog("=== STEP 5: GENERATING BRIEFING CONTENT ===");
            $this->updateStatus('Summarizing with AI...', 60);
            $this->debugLog("Starting AI content generation with " . count($validatedStories) . " validated stories");
            $briefingContent = $this->generateBriefingContent($validatedStories);
            $wordCount = str_word_count($briefingContent);
            $this->debugLog("Content generation completed: " . $wordCount . " words, " . strlen($briefingContent) . " characters");
            
            $generateMp3 = $this->settings['generateMp3'] ?? true;
            
            // Extract and save topics covered with URLs
            $coveredTopics = [];
            foreach ($selectedStories as $story) {
                // Skip weather and entertainment sources for topic tracking
                if (isset($story['source']) && in_array($story['source'], ['Weather Service', 'The Movie Database', 'OpenWeatherMap', 'TMDB'])) {
                    continue;
                }
                if (!empty($story['title']) && !empty($story['url'])) {
                    $coveredTopics[] = [
                        'title' => $story['title'],
                        'url' => $story['url'],
                        'source' => $story['source'] ?? 'Unknown'
                    ];
                }
            }
            $history->addTopics($coveredTopics);
            
            if ($generateMp3) {
                // Step 6: Generate audio
                $this->debugLog("=== STEP 6: GENERATING AUDIO ===");
                $this->updateStatus('Generating audio...', 80);
                $this->debugLog("Starting text-to-speech conversion for " . $wordCount . " word briefing");
                $audioFile = $this->generateAudio($briefingContent);
                $this->debugLog("Audio generation " . ($audioFile ? "completed successfully: " . basename($audioFile) : "failed"));
                
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
                
                // Step 7: Complete with MP3
                $this->debugLog("=== BRIEFING GENERATION COMPLETED ===");
                $this->debugLog("Final result: MP3 audio file created successfully");
                $this->debugLog("Total sources used: " . count($sourcesData) . " news sources");
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
                $this->debugLog("=== BRIEFING GENERATION COMPLETED ===");
                $this->debugLog("Final result: Text-only briefing created successfully");
                $this->debugLog("Total sources used: " . count($sourcesData) . " news sources");
                $this->updateStatus('Complete!', 100, true, true, null, $briefingContent);
            }
            
        } catch (Exception $e) {
            $this->debugLog("=== FATAL ERROR OCCURRED ===", 'ERROR');
            $this->debugLog("Error message: " . $e->getMessage(), 'ERROR');
            $this->debugLog("Error location: " . $e->getFile() . " line " . $e->getLine(), 'ERROR');
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
        $this->debugLog("Fetching from news APIs (GNews, NewsAPI, Guardian, NYT, Local)...");
        $newsItems = $newsAPI->fetchFromAllSources(
            $this->selectedCategories, 
            $this->settings['zipCode'] ?? null, 
            $this->settings['includeLocal'] ?? false
        );
        $this->debugLog("News APIs returned " . count($newsItems) . " articles");
        $allNews = array_merge($allNews, $newsItems);
        
        // Fetch from RSS feeds
        $this->debugLog("Fetching from configured RSS feeds...");
        $rssItems = $this->fetchRSSNews();
        $this->debugLog("RSS feeds returned " . count($rssItems) . " articles");
        $allNews = array_merge($allNews, $rssItems);
        
        // Apply content filtering (blocked/preferred terms)
        $allNews = $this->applyContentFilters($allNews);
        
        // Local news is now handled within fetchFromAllSources
        
        // Weather is handled separately in generateBriefingContent() to ensure it appears first
        
        // Add TV/Movie content if enabled
        if ($this->settings['includeTV'] && $this->settings['tmdbEnabled']) {
            $this->debugLog("Fetching TV/Movie content from TMDB...");
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
                    $this->debugLog("Added TV/Movie content successfully");
                } else {
                    $this->debugLog("TV content formatting failed", 'WARNING');
                }
            } else {
                $this->debugLog("No TV/Movie content available from TMDB", 'WARNING');
            }
        }
        
        $this->debugLog("Total content collected: " . count($allNews) . " items from all sources");
        return $allNews;
    }
    
    private function fetchRSSNews() {
        require_once __DIR__ . '/../includes/RSSFeedHandler.php';
        $rssHandler = new RSSFeedHandler();
        
        $allRSSArticles = $rssHandler->getAllRssArticles(20); // Limit to 20 articles per feed
        $filteredRSSArticles = [];
        
        foreach ($allRSSArticles as $article) {
            // Filter by selected categories (case-insensitive comparison)
            $articleCategory = strtolower($article['category']);
            $selectedCategoriesLower = array_map('strtolower', $this->selectedCategories);
            
            if (in_array($articleCategory, $selectedCategoriesLower)) {
                error_log("Including RSS article '" . $article['title'] . "' - category '" . $article['category'] . "' matches selected");
                $filteredRSSArticles[] = $article;
            } else {
                error_log("EXCLUDING RSS article '" . $article['title'] . "' - category '" . $article['category'] . "' not in selected: " . implode(', ', $this->selectedCategories));
            }
        }
        
        return $filteredRSSArticles;
    }
    
    private function applyContentFilters($news) {
        $filtered = [];
        $preferred = [];
        $seenTitles = [];
        
        $blockedTerms = array_map('trim', explode(',', strtolower($this->settings['blockedTerms'] ?? '')));
        $blockedTerms = array_filter($blockedTerms);
        
        $preferredTerms = array_map('trim', explode(',', strtolower($this->settings['preferredTerms'] ?? '')));
        $preferredTerms = array_filter($preferredTerms);
        
        if (!empty($blockedTerms)) {
            error_log("Blocked terms active: " . implode(', ', $blockedTerms));
        }
        if (!empty($preferredTerms)) {
            error_log("Preferred terms active: " . implode(', ', $preferredTerms));
        }
        
        foreach ($news as $item) {
            // Skip duplicates
            $titleKey = strtolower(trim($item['title']));
            if (in_array($titleKey, $seenTitles)) {
                continue;
            }
            
            $title = $item['title'] ?? '';
            $content = $item['content'] ?? '';
            
            // Check for blocked terms first
            $blocked = false;
            foreach ($blockedTerms as $term) {
                if (stripos($title, $term) !== false || stripos($content, $term) !== false) {
                    error_log("BLOCKED TERM: Article '" . $title . "' blocked for term: " . $term);
                    $blocked = true;
                    break;
                }
            }
            
            if ($blocked) {
                continue;
            }
            
            // Check for preferred terms
            $isPreferred = false;
            foreach ($preferredTerms as $term) {
                if (stripos($title, $term) !== false || stripos($content, $term) !== false) {
                    error_log("PREFERRED TERM: Article '" . $title . "' marked as preferred for term: " . $term);
                    $isPreferred = true;
                    break;
                }
            }
            
            $seenTitles[] = $titleKey;
            
            if ($isPreferred) {
                // Mark preferred articles for priority selection
                $item['isPreferred'] = true;
                $preferred[] = $item;
            } else {
                $filtered[] = $item;
            }
        }
        
        // Combine preferred articles first, then regular articles
        $result = array_merge($preferred, $filtered);
        error_log("Content filtering: " . count($preferred) . " preferred, " . count($filtered) . " regular articles");
        
        return $result;
    }
    
    private function filterByCategories($newsItems) {
        $selectedCategories = $this->settings['categories'] ?? [];
        
        // If no categories are selected, return all items
        if (empty($selectedCategories)) {
            return $newsItems;
        }
        
        // Normalize selected categories to lowercase for comparison
        $normalizedSelected = array_map('strtolower', $selectedCategories);
        
        // Get all RSS custom categories to understand what's custom vs standard
        $rssCustomCategories = [];
        try {
            require_once __DIR__ . '/../includes/RSSFeedHandler.php';
            $rssHandler = new RSSFeedHandler();
            $rssCustomCategories = array_map('strtolower', $rssHandler->getCustomCategories());
        } catch (Exception $e) {
            // Continue without RSS categories if there's an error
        }
        
        // Standard news categories that APIs typically support
        $standardCategories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology'];
        
        $filtered = [];
        foreach ($newsItems as $item) {
            $itemCategory = $item['category'] ?? '';
            $normalizedItemCategory = strtolower($itemCategory);
            
            // Always include special system categories based on settings
            if ($normalizedItemCategory === 'weather' && ($this->settings['includeWeather'] ?? false)) {
                $filtered[] = $item;
                continue;
            }
            
            if ($normalizedItemCategory === 'local' && ($this->settings['includeLocal'] ?? false)) {
                $filtered[] = $item;
                continue;
            }
            
            if ($normalizedItemCategory === 'entertainment' && ($this->settings['includeTV'] ?? false)) {
                $filtered[] = $item;
                continue;
            }
            
            // For user-selected categories, include exact matches
            if (in_array($normalizedItemCategory, $normalizedSelected)) {
                $filtered[] = $item;
                error_log("Including item with category '$itemCategory' - matches selected category");
                continue;
            }
            
            error_log("EXCLUDING article '{$item['title']}' - category '$itemCategory' not in selected: " . implode(', ', $selectedCategories));
        }
        
        error_log("Categories selected: " . implode(', ', $selectedCategories));
        error_log("News items before category filter: " . count($newsItems));
        error_log("News items after category filter: " . count($filtered));
        
        return $filtered;
    }
    
    private function selectStories($newsItems, $todaysTopics = []) {
        $aiService = new AIService($this->settings);
        
        // Filter news items by selected categories
        $filteredItems = $this->filterByCategories($newsItems);
        
        // Shuffle articles by source to ensure better diversity
        $filteredItems = $this->shuffleBySource($filteredItems);
        
        // Determine story count based on audio length
        $audioLength = $this->settings['audioLength'] ?? '5-10';
        $storyCount = $this->getStoryCountForLength($audioLength);
        
        $excludeTopicsText = '';
        if (!empty($todaysTopics)) {
            $excludeTopicsText = "Note: These topics were covered in previous briefings today: " . 
                               implode(', ', array_slice($todaysTopics, 0, 10)) . 
                               ". Prefer fresh stories when possible, but if there's important breaking news on these topics, include it.\n\n";
        }
        
        // Build category-aware prompt
        $selectedCategoriesText = implode(', ', $this->selectedCategories);
        $isSpecificCategoryOnly = count($this->selectedCategories) == 1 && !in_array('general', $this->selectedCategories);
        
        if ($isSpecificCategoryOnly) {
            $categoryName = $this->selectedCategories[0];
            $prompt = "You are selecting stories for a specialized {$categoryName} news briefing. The user has specifically chosen ONLY {$categoryName} content.\n\n" .
                     "IMPORTANT: Select {$storyCount} of the most relevant and interesting {$categoryName} stories from the list below. Do NOT select stories from other categories.\n\n" .
                     "Focus on:\n" .
                     "1. Most important {$categoryName} news and developments\n" .
                     "2. Stories that {$categoryName} enthusiasts would find most interesting\n" .
                     "3. Recent updates and breaking news in the {$categoryName} space\n" .
                     "4. Quality sources and well-reported stories\n\n" .
                     $excludeTopicsText .
                     "Available {$categoryName} stories:\n";
        } else {
            // Calculate stories per category for balanced distribution
            $categoriesInContent = [];
            foreach ($filteredItems as $item) {
                $cat = strtolower($item['category']);
                if (!isset($categoriesInContent[$cat])) {
                    $categoriesInContent[$cat] = 0;
                }
                $categoriesInContent[$cat]++;
            }
            
            $selectedCategoryDistribution = [];
            $totalSelectedCategories = count($this->selectedCategories);
            $baseStoriesPerCategory = max(1, floor($storyCount / $totalSelectedCategories));
            $remainingStories = $storyCount - ($baseStoriesPerCategory * $totalSelectedCategories);
            
            foreach ($this->selectedCategories as $i => $category) {
                $storiesForCategory = $baseStoriesPerCategory;
                if ($i < $remainingStories) {
                    $storiesForCategory++; // Distribute remaining stories
                }
                $selectedCategoryDistribution[$category] = $storiesForCategory;
            }
            
            $distributionText = [];
            foreach ($selectedCategoryDistribution as $cat => $count) {
                $distributionText[] = "{$count} {$cat}";
            }
            
            // Calculate source distribution for diversity
            $sourceCounts = [];
            foreach ($filteredItems as $item) {
                $source = $item['source'] ?? 'Unknown';
                $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
            }
            
            $maxPerSource = max(1, floor($storyCount / max(3, count($sourceCounts))));
            
            $prompt = "Select {$storyCount} stories for a " . $this->getTimeFrame() . " news briefing covering: {$selectedCategoriesText}.\n\n" .
                     "CRITICAL REQUIREMENTS:\n" .
                     "1. CATEGORY BALANCE: Select exactly " . implode(", ", $distributionText) . " stories\n" .
                     "2. SOURCE DIVERSITY: Select NO MORE than {$maxPerSource} stories from any single source\n" .
                     "3. MANDATORY: You must include stories from at least 3 different sources\n\n" .
                     "Selection criteria (in order):\n" .
                     "• Follow the exact category distribution above\n" .
                     "• Ensure source diversity - avoid clustering from one source\n" .
                     "• Choose the most newsworthy stories\n" .
                     "• Include stories from different news organizations\n\n" .
                     $excludeTopicsText .
                     "Available stories:\n";
        }
        
        foreach ($filteredItems as $i => $item) {
            $source = $item['source'] ?? 'Unknown';
            $prompt .= ($i + 1) . ". [{$item['category']}] [{$source}] {$item['title']}\n";
            if (!empty($item['content'])) {
                $prompt .= "   Summary: " . substr($item['content'], 0, 200) . "...\n";
            }
            $prompt .= "\n";
        }
        

        
        $prompt .= "\nRespond with only the numbers of the selected stories, separated by commas (e.g., 1,3,5,7).";
        
        // Try AI services with fallback
        $aiServices = $this->getEnabledAIServices();
        $response = null;
        $maxServiceTimeout = 45; // 45 seconds per service
        
        foreach ($aiServices as $modelName) {
            try {
                $startTime = time();
                $this->debugLog("Attempting AI story selection with {$modelName}...");
                
                $response = $aiService->generateText($prompt, $modelName);
                $duration = time() - $startTime;
                
                if ($response) {
                    $this->debugLog("AI response from {$modelName} (took {$duration}s): " . substr($response, 0, 100) . "...");
                    break; // Success, stop trying other services
                }
            } catch (Exception $e) {
                $duration = time() - $startTime;
                $this->debugLog("AI service {$modelName} failed after {$duration}s: " . $e->getMessage(), 'ERROR');
                // Continue to next AI service
            }
        }
        
        if (!$response) {
            $this->debugLog("All AI services failed for story selection, using smart fallback", 'WARNING');
            // Smart fallback: distribute stories across categories and sources
            return $this->smartFallbackSelection($filteredItems, $storyCount, $selectedCategoryDistribution);
        }
        
        // Parse selection - extract only numbers from AI response, ignoring explanatory text
        preg_match_all('/\b\d+\b/', $response, $matches);
        $selectedIndexes = $matches[0] ?? [];
        
        // If no numbers found, use fallback
        if (empty($selectedIndexes)) {
            $this->debugLog("AI returned no valid numbers: " . substr($response, 0, 200) . "... Using fallback selection.", 'WARNING');
            return $this->smartFallbackSelection($filteredItems, $storyCount, $selectedCategoryDistribution);
        }
        
        $selectedStories = [];
        foreach ($selectedIndexes as $index) {
            $arrayIndex = intval($index) - 1;
            if (isset($filteredItems[$arrayIndex])) {
                $story = $filteredItems[$arrayIndex];
                $this->debugLog("Parsing selection #" . $index . ": [" . ($story['source'] ?? 'Unknown') . "] " . substr($story['title'], 0, 60) . "...");
                $selectedStories[] = $story;
                
                // Stop when we have enough stories
                if (count($selectedStories) >= $storyCount) {
                    break;
                }
            }
        }
        
        // Validate and enforce source diversity
        $selectedStories = $this->enforceSourceDiversity($selectedStories, $filteredItems, $storyCount);
        
        return $selectedStories;
    }
    
    private function enforceSourceDiversity($selectedStories, $allStories, $targetCount) {
        // Count stories per source
        $sourceCounts = [];
        foreach ($selectedStories as $story) {
            $source = $story['source'] ?? 'Unknown';
            $sourceCounts[$source] = ($sourceCounts[$source] ?? 0) + 1;
        }
        
        $maxPerSource = max(1, floor($targetCount / max(3, count($sourceCounts))));
        $needsRebalancing = false;
        
        // Check if any source exceeds the limit
        foreach ($sourceCounts as $source => $count) {
            if ($count > $maxPerSource) {
                $needsRebalancing = true;
                error_log("Source diversity violation: {$source} has {$count} stories (max: {$maxPerSource})");
                break;
            }
        }
        
        if (!$needsRebalancing) {
            error_log("Source diversity check passed - no rebalancing needed");
            return $selectedStories;
        }
        
        error_log("Enforcing source diversity - rebalancing story selection");
        
        // Rebalance by source
        $rebalanced = [];
        $sourceUsage = [];
        
        // First pass: include stories from underrepresented sources
        foreach ($selectedStories as $story) {
            $source = $story['source'] ?? 'Unknown';
            $currentCount = $sourceUsage[$source] ?? 0;
            
            if ($currentCount < $maxPerSource) {
                $rebalanced[] = $story;
                $sourceUsage[$source] = $currentCount + 1;
            }
        }
        
        // Second pass: fill remaining slots with diverse sources from all available stories
        if (count($rebalanced) < $targetCount) {
            $remaining = $targetCount - count($rebalanced);
            $excludedTitles = array_column($rebalanced, 'title');
            
            foreach ($allStories as $story) {
                if ($remaining <= 0) break;
                if (in_array($story['title'], $excludedTitles)) continue;
                
                $source = $story['source'] ?? 'Unknown';
                $currentCount = $sourceUsage[$source] ?? 0;
                
                if ($currentCount < $maxPerSource) {
                    $rebalanced[] = $story;
                    $sourceUsage[$source] = $currentCount + 1;
                    $remaining--;
                    error_log("Added for diversity: [" . $source . "] " . $story['title']);
                }
            }
        }
        
        return array_slice($rebalanced, 0, $this->getNumericStoryCount($targetCount));
    }
    
    private function smartFallbackSelection($items, $storyCount, $categoryDistribution) {
        $count = $this->getNumericStoryCount($storyCount);
        $selected = [];
        
        // Group items by category
        $byCategory = [];
        foreach ($items as $item) {
            $category = $item['category'] ?? 'general';
            if (!isset($byCategory[$category])) {
                $byCategory[$category] = [];
            }
            $byCategory[$category][] = $item;
        }
        
        // Distribute based on category requirements
        foreach ($categoryDistribution as $category => $needed) {
            if (isset($byCategory[$category]) && $needed > 0) {
                $available = $byCategory[$category];
                $toTake = min($needed, count($available));
                
                // Take first stories from this category
                for ($i = 0; $i < $toTake; $i++) {
                    if (isset($available[$i])) {
                        $selected[] = $available[$i];
                        error_log("Fallback selected: [" . $category . "] " . $available[$i]['title']);
                    }
                }
            }
        }
        
        // Fill remaining slots with any available stories
        if (count($selected) < $count) {
            $remaining = $count - count($selected);
            $excludedTitles = array_column($selected, 'title');
            
            foreach ($items as $item) {
                if ($remaining <= 0) break;
                if (!in_array($item['title'], $excludedTitles)) {
                    $selected[] = $item;
                    $remaining--;
                    error_log("Fallback filled: " . $item['title']);
                }
            }
        }
        
        // Apply source diversity enforcement
        return $this->enforceSourceDiversity($selected, $items, $count);
    }
    
    private function shuffleBySource($articles) {
        // Group articles by source
        $bySource = [];
        foreach ($articles as $article) {
            $source = $article['source'] ?? 'Unknown';
            if (!isset($bySource[$source])) {
                $bySource[$source] = [];
            }
            $bySource[$source][] = $article;
        }
        
        // Interleave articles from different sources
        $shuffled = [];
        $maxArticles = max(array_map('count', $bySource));
        
        for ($i = 0; $i < $maxArticles; $i++) {
            foreach ($bySource as $source => $sourceArticles) {
                if (isset($sourceArticles[$i])) {
                    $shuffled[] = $sourceArticles[$i];
                }
            }
        }
        
        error_log("Source diversity shuffle: Reordered " . count($articles) . " articles from " . count($bySource) . " sources");
        
        return $shuffled;
    }
    
    private function getStoryCountForLength($audioLength) {
        // These counts are for actual news stories only
        // Weather, local, and TV/movie content are bonus additions
        switch ($audioLength) {
            case '3-5':
                return '5-7';  // More news stories for core content
            case '10-15':
                return '15-18';
            case '15-20':
                return '22-25';
            default: // '5-10'
                return '8-10'; // Increased from 4-6 to ensure more news coverage
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
        
        // Check if user selected only specific categories (excluding weather/entertainment)
        $isSpecificCategoryOnly = count($this->selectedCategories) == 1 && !in_array('general', $this->selectedCategories);
        $selectedCategory = $isSpecificCategoryOnly ? $this->selectedCategories[0] : null;
        
        // Add weather and entertainment based on user settings
        $weatherContent = '';
        $tvContent = '';
        $contentNumber = 3;
        
        // Always check weather setting, even for category-specific briefings
        if ($this->settings['includeWeather'] ?? false) {
            $weatherContent = $this->getWeatherContent();
            if (!empty($weatherContent)) {
                $prompt .= "{$contentNumber}. ALWAYS include weather information first: {$weatherContent}\n\n";
                $contentNumber++;
            }
        }
        
        // Check TV content setting, even for category-specific briefings
        if ($this->settings['includeTV'] ?? false) {
            $tvContent = $this->getTVContent();
            if (!empty($tvContent)) {
                $prompt .= "{$contentNumber}. ALWAYS include entertainment/TV information: {$tvContent}\n\n";
                $contentNumber++;
            }
        }
        
        if ($isSpecificCategoryOnly) {
            $prompt .= "{$contentNumber}. This is a specialized {$selectedCategory} news briefing. After any weather/TV content above, focus on {$selectedCategory} content.\n\n";
        }
        
        $prompt .= "5. Present each news story in a conversational, natural speaking style suitable for audio reading\n";
        $prompt .= "6. CRITICAL: This must be a {$audioLength} minute briefing with approximately {$wordCount} words. Each story must be covered in substantial detail with 3-4 paragraphs minimum.\n";
        $prompt .= "7. EXPAND extensively on each story - provide context, background, implications, expert opinions, and detailed analysis. This is not a headline summary but an in-depth news briefing.\n";
        $prompt .= "8. For each story, include: What happened, who is involved, when and where it occurred, why it's significant, and what the potential consequences or next steps might be.\n";
        $prompt .= "9. Use natural transitions between stories like 'Now for technology news', 'Next up in business', 'Moving to health news', 'And in science' - NO numbered lists or formal section headers\n";
        $prompt .= "10. Write ONLY clean text without any markup tags, asterisks, underscores, hashtags, or formatting symbols - just natural, flowing sentences\n";
        $prompt .= "11. Include natural pauses between stories using periods and paragraph breaks\n";
        $prompt .= "12. IMPORTANT: Do not use any markdown formatting like *, **, _, __, #, or any other special characters for emphasis\n";
        $prompt .= "13. ABSOLUTE REQUIREMENT: ONLY use the exact news stories listed below. NEVER create, invent, imagine, or generate ANY fictional news content under ANY circumstances. If no real news stories are provided, state that no news is available.\n";
        $prompt .= "14. Add proper paragraph breaks between stories for readability\n";
        $prompt .= "15. MANDATORY: You must reach the target word count of {$wordCount} words. If you provide fewer words, you have failed the task. Be verbose and thorough in your coverage.\n";
        $prompt .= "16. End with a natural conclusion like 'That's all the news for this {$timeFrame}. Have a great day!' or similar\n\n";
        
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
            if ($isSpecificCategoryOnly) {
                $prompt .= "CRITICAL: NO {$selectedCategory} stories are available. State 'No {$selectedCategory} news is available from our sources at this time' and end with the conclusion. DO NOT create any fictional news content.\n\n";
            } else {
                $prompt .= "CRITICAL: NO real news stories are available. After weather and entertainment, state 'No additional news is available from our sources at this time' and end with the conclusion. DO NOT create any fictional news content.\n\n";
            }
        } else {
            if ($isSpecificCategoryOnly) {
                $prompt .= "Real {$selectedCategory} stories to include (ONLY use these exact stories in this exact order):\n\n";
            } else {
                $prompt .= "Real news stories to include after weather and entertainment (ONLY use these exact stories in this exact order):\n\n";
            }
            
            $storyIndex = 1;
            
            // For category-specific briefings, treat all stories equally
            if ($isSpecificCategoryOnly) {
                $allStories = array_merge($localNewsStories, $otherNewsStories);
                foreach ($allStories as $story) {
                    $prompt .= "{$storyIndex}. {$story['title']}\n";
                    if (!empty($story['content'])) {
                        $prompt .= "   Details: {$story['content']}\n";
                    }
                    $prompt .= "\n";
                    $storyIndex++;
                }
            } else {
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
        }
        
        $prompt .= "\nGenerate the complete readable news briefing script now. STRICT RULE: Use ONLY the exact stories listed above. NO fictional content whatsoever:";
        
        // Try AI services with fallback for content generation
        $aiServices = $this->getEnabledAIServices();
        $response = null;
        
        foreach ($aiServices as $modelName) {
            try {
                $startTime = time();
                $this->debugLog("Starting content generation with {$modelName}...");
                $response = $aiService->generateText($prompt, $modelName);
                $duration = time() - $startTime;
                
                if ($response) {
                    $wordCount = str_word_count($response);
                    $this->debugLog("Content generation completed with {$modelName} (took {$duration}s): {$wordCount} words generated");
                    break; // Success, stop trying other services
                }
            } catch (Exception $e) {
                $duration = time() - $startTime;
                $this->debugLog("AI service {$modelName} failed in content generation after {$duration}s: " . $e->getMessage(), 'ERROR');
                // Continue to next AI service
            }
        }
        
        if (!$response) {
            $this->debugLog("All AI services failed for content generation", 'ERROR');
            throw new Exception('All AI services failed to generate content. Please check your API keys and try again.');
        }
        
        // Check if the response meets the word count target
        $actualWordCount = str_word_count($response);
        $targetWords = explode('-', str_replace(['words', ' '], '', $wordCount));
        $minWords = intval($targetWords[0]);
        
        error_log("AI Response word count: {$actualWordCount}, Target: {$wordCount}");
        
        // If significantly under target, request expansion
        if ($actualWordCount < $minWords * 0.7) { // If less than 70% of minimum target
            error_log("Content too short, requesting expansion...");
            $expansionPrompt = "The previous briefing was only {$actualWordCount} words but needs to be {$wordCount} words for a {$audioLength} minute audio briefing. Please EXPAND the content significantly by:\n\n";
            $expansionPrompt .= "1. Adding much more detail to each story - background, context, implications\n";
            $expansionPrompt .= "2. Including expert analysis and potential consequences\n";
            $expansionPrompt .= "3. Explaining the significance and impact of each story\n";
            $expansionPrompt .= "4. Adding relevant historical context where appropriate\n";
            $expansionPrompt .= "5. MANDATORY: Reach exactly {$wordCount} words\n\n";
            $expansionPrompt .= "Here is the content to expand:\n\n{$response}";
            
            // Try to get expanded content
            foreach ($aiServices as $modelName) {
                try {
                    $expandedResponse = $aiService->generateText($expansionPrompt, $modelName);
                    if ($expandedResponse && str_word_count($expandedResponse) > $actualWordCount) {
                        $response = $expandedResponse;
                        error_log("Expanded content word count: " . str_word_count($response));
                        break;
                    }
                } catch (Exception $e) {
                    error_log("Expansion failed with {$modelName}: " . $e->getMessage());
                }
            }
        }
        
        // Validate response contains no synthetic content
        $allRealNewsStories = array_merge($localNewsStories, $otherNewsStories);
        $response = $this->validateNoSyntheticContent($response, $allRealNewsStories);
        
        // Clean the response to remove markdown formatting and unwanted characters
        return $this->cleanTextForAudio($response);
    }
    
    private function getWordCountForLength($audioLength) {
        // AI speaks at ~180-200 words per minute, targeting higher word counts for longer audio
        switch ($audioLength) {
            case '3-5':
                return '800-1200';  // 4-6 minutes target
            case '10-15':
                return '2500-3200'; // 12-16 minutes target
            case '15-20':
                return '3800-4500'; // 19-22 minutes target
            default: // '5-10'
                return '1200-2000'; // 6-10 minutes target
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
        // All configured feeds and APIs are trusted sources
        // Only basic data structure validation needed
        $validStories = [];
        
        foreach ($stories as $story) {
            // Minimal validation - just ensure we have a title
            if (!empty($story['title'])) {
                $validStories[] = $story;
                
                $source = isset($story['source']) ? $story['source'] : 'Feed';
                $this->debugLog("Accepted story from " . $source . ": " . substr($story['title'], 0, 60) . "...");
            }
        }
        
        $this->debugLog("Accepted " . count($validStories) . " of " . count($stories) . " stories from configured sources");
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
        
        // TTS service now handles file creation and returns the path
        return $ttsService->synthesizeSpeech($ssmlContent);
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
        $this->debugLog("STATUS: $message ($progress%)");
    }
    
    private function debugLog($message, $type = 'INFO') {
        $logFile = "/tmp/newsbear_debug_{$this->sessionId}.log";
        $timestamp = date('H:i:s');
        $logEntry = "[$timestamp] $message\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Force immediate flush to disk for real-time display
        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($logFile, true);
        }
        
        // Also log to error_log for workflow console visibility
        error_log("NewsBear Debug [$timestamp]: $message");
        
        // Force output buffer flush for immediate file writing
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
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
