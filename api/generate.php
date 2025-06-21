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

try {
    require_once '../includes/NewsAPI.php';
    require_once '../includes/AIService.php';
    require_once '../includes/TTSService.php';
    require_once '../includes/WeatherService.php';
    require_once '../includes/TMDBService.php';
    require_once '../includes/BriefingHistory.php';

    // Get settings from POST data
    $input = json_decode(file_get_contents('php://input'), true);
    $settings = $input ?: [];

    // Load user settings as defaults
    $userSettingsFile = '../config/user_settings.json';
    $userSettings = [];
    if (file_exists($userSettingsFile)) {
        $userSettings = json_decode(file_get_contents($userSettingsFile), true) ?: [];
    }
    
    // Merge with posted settings
    $settings = array_merge($userSettings, $settings);

    // Initialize services
    $newsAPI = new NewsAPI($settings);
    $aiService = new AIService($settings);
    $history = new BriefingHistory();
    
    // Initialize debug logging
    $sessionId = $input['session_id'] ?? 'briefing_' . time() . '_' . rand(1000, 9999);
    $debugLogFile = "../data/debug_log_{$sessionId}.json";
    
    function debugLog($message, $sessionId) {
        $logFile = "../data/debug_log_{$sessionId}.json";
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = [
            'timestamp' => $timestamp,
            'message' => $message,
            'type' => 'info'
        ];
        
        $existingLogs = [];
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            if ($content) {
                $existingLogs = json_decode($content, true) ?: [];
            }
        }
        
        $existingLogs[] = $logEntry;
        file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        
        // Also log to error log for console visibility
        error_log("NewsBear Debug [$timestamp]: $message");
    }
    
    debugLog("=== Starting briefing generation ===", $sessionId);
    debugLog("Session ID: $sessionId", $sessionId);

    // Get selected categories
    $selectedCategories = $settings['categories'] ?? [];
    debugLog("Selected categories: " . implode(', ', $selectedCategories), $sessionId);
    
    // Check if any content is enabled
    $hasOtherContent = ($settings['includeWeather'] ?? false) || ($settings['includeTV'] ?? false);
    debugLog("Include weather: " . ($settings['includeWeather'] ? 'YES' : 'NO'), $sessionId);
    debugLog("Include TV: " . ($settings['includeTV'] ? 'YES' : 'NO'), $sessionId);
    debugLog("Include local news: " . ($settings['includeLocal'] ? 'YES' : 'NO'), $sessionId);
    debugLog("ZIP code: " . ($settings['zipCode'] ?? 'none'), $sessionId);
    
    if (empty($selectedCategories) && !$hasOtherContent) {
        throw new Exception('No content categories selected. Please select at least one news category or enable weather/TV content in settings.');
    }

    debugLog("=== STEP 1: FETCHING CONTENT ===", $sessionId);
    
    // Collect priority content first (local weather, local news, TV/movies)
    $priorityItems = [];
    $regularNewsItems = [];

    // Add weather FIRST if enabled
    if ($settings['includeWeather'] ?? false) {
        debugLog("Fetching weather for ZIP: " . ($settings['zipCode'] ?? 'none'), $sessionId);
        $weatherService = new WeatherService($settings);
        $weatherItems = $weatherService->getWeatherBriefing($settings['zipCode'] ?? null);
        if (!empty($weatherItems)) {
            debugLog("Weather: Found " . count($weatherItems) . " weather items", $sessionId);
            foreach ($weatherItems as $item) {
                $item['priority'] = 1; // Highest priority
                $priorityItems[] = $item;
            }
        } else {
            debugLog("Weather: No weather data available", $sessionId);
        }
    }

    // Add TV/Movie content SECOND if enabled
    if ($settings['includeTV'] ?? false) {
        debugLog("Fetching TV/movie content from TMDB", $sessionId);
        $tmdbService = new TMDBService($settings['tmdbApiKey'] ?? '');
        $tvContent = $tmdbService->getTVContent();
        if ($tvContent) {
            $tvBriefing = $tmdbService->formatForBriefing($tvContent);
            if (!empty($tvBriefing)) {
                debugLog("TV/Movies: Generated entertainment briefing content", $sessionId);
                $priorityItems[] = [
                    'title' => 'Entertainment & TV Today',
                    'content' => $tvBriefing,
                    'category' => 'entertainment',
                    'source' => 'The Movie Database',
                    'publishedAt' => date('c'),
                    'priority' => 2
                ];
            } else {
                debugLog("TV/Movies: No content generated from TMDB", $sessionId);
            }
        } else {
            debugLog("TV/Movies: No TV content available from TMDB", $sessionId);
        }
    }

    // Fetch regular news (including local news which gets priority = 3)
    if (!empty($selectedCategories)) {
        debugLog("Fetching news from all sources for categories: " . implode(', ', $selectedCategories), $sessionId);
        $fetchedNews = $newsAPI->fetchFromAllSources(
            $selectedCategories, 
            $settings['zipCode'] ?? null, 
            $settings['includeLocal'] ?? false
        );
        
        debugLog("Total articles fetched: " . count($fetchedNews), $sessionId);
        
        // FIRST: Apply AI categorization to properly classify all articles
        try {
            require_once __DIR__ . '/../includes/CategoryClassifier.php';
            $classifier = new CategoryClassifier($settings);
            $fetchedNews = $classifier->classifyArticles($fetchedNews);
            debugLog("AI categorization complete", $sessionId);
        } catch (Exception $e) {
            debugLog("Category classification error: " . $e->getMessage(), $sessionId);
        }
        
        // THEN: Apply strict category filtering after categorization
        $enabledCategories = $selectedCategories; // Only user-selected categories
        $filteredFetchedNews = [];
        
        foreach ($fetchedNews as $item) {
            $itemCategory = strtolower($item['category'] ?? '');
            
            // Allow through if it matches selected categories OR is a special system category
            $isSystemCategory = in_array($itemCategory, ['local', 'entertainment', 'weather']);
            $isSelectedCategory = in_array($itemCategory, $enabledCategories);
            
            if ($isSelectedCategory || ($isSystemCategory && ($settings['includeLocal'] || $settings['includeTV'] || $settings['includeWeather']))) {
                $filteredFetchedNews[] = $item;
                debugLog("INCLUDED: Article '" . ($item['title'] ?? 'Untitled') . "' - category '$itemCategory'", $sessionId);
            } else {
                debugLog("FILTERED OUT: Article '" . ($item['title'] ?? 'Untitled') . "' - category '$itemCategory' not in selected categories", $sessionId);
            }
        }
        
        debugLog("Articles after AI categorization and filtering: " . count($filteredFetchedNews) . " (from " . count($fetchedNews) . ")", $sessionId);
        
        // Separate local news from regular news
        $localCount = 0;
        $regularCount = 0;
        foreach ($filteredFetchedNews as $item) {
            if (($item['category'] ?? '') === 'local') {
                $item['priority'] = 3; // Third priority
                $priorityItems[] = $item;
                $localCount++;
            } else {
                $item['priority'] = 4; // Regular news
                $regularNewsItems[] = $item;
                $regularCount++;
            }
        }
        
        debugLog("Local news articles: $localCount", $sessionId);
        debugLog("Regular news articles: $regularCount", $sessionId);
    }

    // Combine priority items first, then regular news
    $newsItems = array_merge($priorityItems, $regularNewsItems);
    debugLog("Total content items after priority sorting: " . count($newsItems), $sessionId);

    if (empty($newsItems)) {
        debugLog("ERROR: No content available from enabled sources", $sessionId);
        throw new Exception('No content available from enabled sources. Please check your API keys and settings.');
    }

    debugLog("=== STEP 2: STORY SELECTION ===", $sessionId);
    
    // Select stories based on audio length setting
    $audioLength = $settings['audioLength'] ?? '5-10';
    debugLog("Target audio length: $audioLength minutes", $sessionId);
    
    // Calculate story count based on audio length
    switch ($audioLength) {
        case '1-3':
            $storyCount = 8;
            break;
        case '3-5':
            $storyCount = 12;
            break;
        case '5-10':
            $storyCount = 20;
            break;
        case '10-15':
            $storyCount = 30;
            break;
        case '15-20':
            $storyCount = 40;
            break;
        case '20-30':
            $storyCount = 50;
            break;
        default:
            $storyCount = 20;
    }
    
    debugLog("Target story count: $storyCount", $sessionId);
    
    // Filter out stories with insufficient content and prioritize longer descriptions
    $filteredStories = [];
    foreach ($newsItems as $story) {
        $content = $story['content'] ?? $story['description'] ?? '';
        $title = $story['title'] ?? '';
        
        // Skip stories with very brief content (less than 50 characters total)
        if (strlen($content . $title) < 50) {
            continue;
        }
        
        // Skip stories that are just headlines with no description
        if (strlen($content) < 20 && strlen($title) > 0) {
            continue;
        }
        
        // Add content length score for sorting
        $story['content_score'] = strlen($content) + (strlen($title) * 0.5);
        $filteredStories[] = $story;
    }
    
    // Sort by priority first, then content richness
    usort($filteredStories, function($a, $b) {
        $priorityA = $a['priority'] ?? 4;
        $priorityB = $b['priority'] ?? 4;
        
        // Lower priority number = higher importance
        if ($priorityA !== $priorityB) {
            return $priorityA - $priorityB;
        }
        
        // If same priority, sort by content richness
        return $b['content_score'] - $a['content_score'];
    });
    
    // Select the best stories up to our target count
    $storyCount = min($storyCount, count($filteredStories));
    $selectedStories = array_slice($filteredStories, 0, $storyCount);
    
    debugLog("Stories after filtering: " . count($filteredStories), $sessionId);
    debugLog("Final selected stories: " . count($selectedStories), $sessionId);
    
    // Log story details
    foreach ($selectedStories as $index => $story) {
        $priority = $story['priority'] ?? 4;
        $priorityLabel = match($priority) {
            1 => '[WEATHER]',
            2 => '[TV/MOVIES]', 
            3 => '[LOCAL NEWS]',
            default => '[NEWS]'
        };
        debugLog("Story " . ($index + 1) . " $priorityLabel: " . ($story['title'] ?? 'Untitled') . " (Source: " . ($story['source'] ?? 'Unknown') . ")", $sessionId);
    }

    // Generate briefing content with proper opening/closing
    $hour = intval(date('H'));
    $greeting = '';
    $closing = '';
    
    if ($hour >= 5 && $hour < 12) {
        $greeting = "Good morning.";
        $closing = "That's your morning news update. We'll be back with more throughout the day.";
    } elseif ($hour >= 12 && $hour < 17) {
        $greeting = "Good afternoon.";
        $closing = "That concludes your afternoon news briefing. Stay informed, and we'll see you later.";
    } elseif ($hour >= 17 && $hour < 22) {
        $greeting = "Good evening.";
        $closing = "That's all for your evening news update. Have a great rest of your evening.";
    } else {
        $greeting = "Good evening.";
        $closing = "That wraps up tonight's news. Stay safe, and we'll catch you tomorrow.";
    }
    
    debugLog("=== STEP 3: AI GENERATION ===", $sessionId);
    debugLog("Using AI model: " . ($settings['aiSelection'] ?? 'gemini'), $sessionId);
    debugLog("Target word count: " . (intval(substr($audioLength, 0, 2)) * 150), $sessionId);
    
    $prompt = "Generate a comprehensive news briefing for a $audioLength minute broadcast. Start with '$greeting Here are today's top stories.' and end with '$closing' 

CRITICAL RULES - VIOLATION WILL RESULT IN REJECTION:
1. Use ONLY the stories and sources listed below - NO OTHER SOURCES
2. DO NOT mention any publication names, news outlets, or sources not explicitly listed
3. DO NOT reference 'The New York Times', 'CNN', 'BBC' or any outlet unless it appears in the source list below
4. DO NOT fabricate quotes, specific details, names, dates, or events
5. ONLY expand with general context and reasonable inferences from provided facts
6. If you cannot complete a story with available information, move to the next story
7. NEVER truncate mid-sentence - always complete your thoughts

STORIES TO USE (and ONLY these stories):\n\n";
    
    // Add story content with more detail for longer broadcasts
    foreach ($selectedStories as $index => $story) {
        $prompt .= "Story " . ($index + 1) . ":\n";
        $prompt .= "Headline: " . ($story['title'] ?? 'Untitled') . "\n";
        $prompt .= "Brief Description: " . ($story['content'] ?? $story['description'] ?? 'No content') . "\n";
        $prompt .= "Source: " . ($story['source'] ?? 'Unknown') . "\n";
        $prompt .= "URL: " . ($story['url'] ?? 'No URL') . "\n\n";
    }
    
    $prompt .= "\nSTRICT BRIEFING REQUIREMENTS:

1. SOURCE RESTRICTION: Use ONLY the stories listed above. Do not mention any news outlet or publication not explicitly shown in the source list.

2. CONTENT LIMITS: 
   - Expand stories using ONLY provided facts plus general context
   - NO specific details, quotes, or events not in source material
   - NO references to unnamed sources or publications
   
3. COMPLETION REQUIREMENT: 
   - ALWAYS complete your sentences and thoughts
   - If running out of content, conclude professionally with the provided closing
   - NEVER stop mid-sentence or reference phantom sources

4. PROFESSIONAL PRESENTATION: 
   - Natural news anchor style with smooth transitions
   - Plain text only - no formatting, asterisks, or section headers
   - Target " . (intval(substr($audioLength, 0, 2)) * 150) . " words total

5. QUALITY CONTROL: Focus on meaningful expansion of PROVIDED stories only. Build context around given facts without inventing specifics.

MANDATORY ENDING: You MUST end the briefing with the exact closing message: '$closing'

Do not forget or omit this closing message under any circumstances.";
    
    $aiSelection = $settings['aiSelection'] ?? 'gemini';
    debugLog("Sending prompt to AI service...", $sessionId);
    $briefingContent = $aiService->generateText($prompt, $aiSelection);
    
    if (empty($briefingContent)) {
        debugLog("ERROR: AI service returned empty content", $sessionId);
        throw new Exception('Failed to generate briefing content');
    }
    
    $wordCount = str_word_count($briefingContent);
    debugLog("AI generation complete - word count: $wordCount", $sessionId);

    debugLog("=== STEP 4: AUDIO GENERATION ===", $sessionId);
    
    // Check if audio generation is enabled
    $generateMp3 = $settings['generateMp3'] ?? false;
    debugLog("Audio generation enabled: " . ($generateMp3 ? 'YES' : 'NO'), $sessionId);
    
    $audioFile = null;
    $downloadUrl = null;

    if ($generateMp3) {
        debugLog("Starting TTS synthesis...", $sessionId);
        $ttsService = new TTSService($settings);
        $audioFile = $ttsService->synthesizeSpeech($briefingContent);
        if ($audioFile) {
            $downloadUrl = 'downloads/' . basename($audioFile);
            debugLog("Audio file generated: " . basename($audioFile), $sessionId);
        } else {
            debugLog("ERROR: Audio generation failed", $sessionId);
        }
    }

    // Save to history
    $sourcesData = [];
    foreach ($selectedStories as $story) {
        if (!empty($story['title']) && !empty($story['url'])) {
            $sourcesData[] = [
                'title' => $story['title'],
                'url' => $story['url'],
                'source' => $story['source'] ?? 'Unknown'
            ];
        }
    }

    $briefingId = $history->saveBriefing([
        'topics' => $sourcesData,
        'text' => $briefingContent,
        'audio_file' => $audioFile ? basename($audioFile) : null,
        'duration' => 0,
        'format' => $generateMp3 ? 'mp3' : 'text',
        'sources' => $sourcesData
    ]);

    // Return response
    $response = [
        'success' => true,
        'status' => 'success',
        'message' => 'Briefing generated successfully',
        'progress' => 100,
        'complete' => true,
        'briefingText' => $briefingContent
    ];

    if ($downloadUrl) {
        $response['downloadUrl'] = $downloadUrl;
    }

    echo json_encode($response);

} catch (Exception $e) {
    error_log("Briefing generation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'status' => 'error',
        'message' => $e->getMessage(),
        'progress' => 0,
        'complete' => true
    ]);
}
?>