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

    // Get selected categories
    $selectedCategories = $settings['categories'] ?? [];
    
    // Check if any content is enabled
    $hasOtherContent = ($settings['includeWeather'] ?? false) || ($settings['includeTV'] ?? false);
    
    if (empty($selectedCategories) && !$hasOtherContent) {
        throw new Exception('No content categories selected. Please select at least one news category or enable weather/TV content in settings.');
    }

    // Fetch news
    $newsItems = [];
    if (!empty($selectedCategories)) {
        $newsItems = $newsAPI->fetchFromAllSources(
            $selectedCategories, 
            $settings['zipCode'] ?? null, 
            $settings['includeLocal'] ?? false
        );
    }

    // Add weather if enabled
    if ($settings['includeWeather'] ?? false) {
        $weatherService = new WeatherService($settings);
        $weatherItems = $weatherService->getWeatherBriefing($settings['zipCode'] ?? null);
        if (!empty($weatherItems)) {
            $newsItems = array_merge($newsItems, $weatherItems);
        }
    }

    // Add TV/Movie content if enabled
    if ($settings['includeTV'] ?? false) {
        $tmdbService = new TMDBService($settings['tmdbApiKey'] ?? '');
        $tvContent = $tmdbService->getTVContent();
        if ($tvContent) {
            $tvBriefing = $tmdbService->formatForBriefing($tvContent);
            if (!empty($tvBriefing)) {
                $newsItems[] = [
                    'title' => 'Entertainment & TV Today',
                    'content' => $tvBriefing,
                    'category' => 'entertainment',
                    'source' => 'The Movie Database',
                    'publishedAt' => date('c')
                ];
            }
        }
    }

    if (empty($newsItems)) {
        throw new Exception('No content available from enabled sources. Please check your API keys and settings.');
    }

    // Select stories based on audio length setting
    $audioLength = $settings['audioLength'] ?? '5-10';
    
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
    
    // Sort by content richness (longer descriptions first)
    usort($filteredStories, function($a, $b) {
        return $b['content_score'] - $a['content_score'];
    });
    
    // Select the best stories up to our target count
    $storyCount = min($storyCount, count($filteredStories));
    $selectedStories = array_slice($filteredStories, 0, $storyCount);

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
    
    $prompt = "Generate a comprehensive news briefing for a $audioLength minute broadcast. Start with '$greeting Here are today's top stories.' and end with '$closing' 

IMPORTANT: Use ONLY the factual information provided. DO NOT fabricate details, quotes, or events. You may:
- Provide general context about similar situations
- Explain significance and implications
- Add transitions and professional commentary
- Expand on the information given with reasonable inferences

DO NOT invent specific details, names, dates, or quotes not in the source material.\n\n";
    
    // Add story content with more detail for longer broadcasts
    foreach ($selectedStories as $index => $story) {
        $prompt .= "Story " . ($index + 1) . ":\n";
        $prompt .= "Headline: " . ($story['title'] ?? 'Untitled') . "\n";
        $prompt .= "Brief Description: " . ($story['content'] ?? $story['description'] ?? 'No content') . "\n";
        $prompt .= "Source: " . ($story['source'] ?? 'Unknown') . "\n";
        $prompt .= "URL: " . ($story['url'] ?? 'No URL') . "\n\n";
    }
    
    $prompt .= "\nBRIEFING REQUIREMENTS FOR $audioLength MINUTE BROADCAST:

1. FACTUAL EXPANSION ONLY: Expand each story using only the provided information plus general context and implications

2. STORY DEVELOPMENT: For each story, include:
   - The core facts as provided
   - General context about this type of situation
   - Why this matters to the audience
   - Broader implications or significance
   
3. PROFESSIONAL PRESENTATION: Write as a news anchor with natural transitions between stories

4. TARGET LENGTH: Aim for approximately " . (intval(substr($audioLength, 0, 2)) * 150) . " words total

5. NO FABRICATION: Do not invent specific details, quotes, numbers, or events not in the source material

6. QUALITY OVER QUANTITY: Focus on meaningful expansion rather than filler content

Use the provided facts as your foundation and build professionally around them with context and analysis.";
    
    $aiSelection = $settings['aiSelection'] ?? 'gemini';
    $briefingContent = $aiService->generateText($prompt, $aiSelection);
    
    if (empty($briefingContent)) {
        throw new Exception('Failed to generate briefing content');
    }

    // Check if audio generation is enabled
    $generateMp3 = $settings['generateMp3'] ?? false;
    $audioFile = null;
    $downloadUrl = null;

    if ($generateMp3) {
        // Generate audio
        $ttsService = new TTSService($settings);
        $audioFile = $ttsService->synthesizeSpeech($briefingContent);
        if ($audioFile) {
            $downloadUrl = 'downloads/' . basename($audioFile);
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