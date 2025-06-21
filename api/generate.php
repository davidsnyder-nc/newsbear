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

    // Select stories (simple selection)
    $audioLength = $settings['audioLength'] ?? '5-10';
    $storyCount = 10; // Default story count
    $selectedStories = array_slice($newsItems, 0, $storyCount);

    // Generate briefing content using simple text generation
    $prompt = "Generate a clean, professional news briefing text from these stories. Write ONLY the news content that should be read aloud - no production notes, music cues, or stage directions. Just the news text:\n\n";
    foreach ($selectedStories as $story) {
        $prompt .= "Title: " . ($story['title'] ?? 'Untitled') . "\n";
        $prompt .= "Content: " . ($story['content'] ?? $story['description'] ?? 'No content') . "\n\n";
    }
    $prompt .= "\nCreate a cohesive news briefing with clean, readable text suitable for text-to-speech. Do not include any production instructions, music cues, or formatting beyond basic paragraph breaks.";
    
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