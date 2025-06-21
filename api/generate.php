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
    
    // Ensure we don't exceed available stories
    $storyCount = min($storyCount, count($newsItems));
    $selectedStories = array_slice($newsItems, 0, $storyCount);

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

NOTE: The content provided below are just brief headlines/descriptions. You must EXPAND each one into a full, detailed news story with context, analysis, and implications.\n\n";
    
    // Add story content with more detail for longer broadcasts
    foreach ($selectedStories as $index => $story) {
        $prompt .= "Story " . ($index + 1) . ":\n";
        $prompt .= "Headline: " . ($story['title'] ?? 'Untitled') . "\n";
        $prompt .= "Brief Description: " . ($story['content'] ?? $story['description'] ?? 'No content') . "\n";
        $prompt .= "Source: " . ($story['source'] ?? 'Unknown') . "\n";
        $prompt .= "URL: " . ($story['url'] ?? 'No URL') . "\n\n";
    }
    
    $prompt .= "\nCRITICAL INSTRUCTIONS FOR $audioLength MINUTE BRIEFING:

1. EXPAND EVERY STORY: The descriptions above are just headlines/summaries. Transform each into a full 3-5 sentence news story with:
   - Background context and details
   - Why this matters to listeners
   - What led to this situation
   - Potential implications or next steps

2. STORY LENGTH: Each story should be 60-100 words minimum (not just 1-2 sentences)

3. ADD PROFESSIONAL ANALYSIS: Include context like 'This comes after...' or 'Officials say this represents...' or 'The decision follows...'

4. USE NATURAL TRANSITIONS: Connect stories with phrases like 'In related news...' or 'Turning to business...'

5. TARGET LENGTH: Write approximately " . (intval(substr($audioLength, 0, 2)) * 150) . " words total

6. CONVERSATIONAL TONE: Write as a professional news anchor would speak, with natural flow

7. NO SHORTCUTS: Do not just repeat the brief descriptions - create full news coverage

Example: If given 'Carson announces fireworks policy', expand to: 'The city of Carson has announced a strict new zero-tolerance policy on illegal fireworks, with fines reaching up to $5,000. This comes in response to growing complaints from residents about noise and safety concerns during holiday celebrations. City officials say the new enforcement measures will begin immediately and include both citations and confiscation of illegal fireworks. The policy represents one of the toughest stances in the region...'

Write substantial, informative content for each story.";
    
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