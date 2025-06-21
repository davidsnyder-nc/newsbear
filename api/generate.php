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

// Generate unique session ID
$sessionId = uniqid('briefing_', true);

// Create status file immediately
$statusFile = "../downloads/status_{$sessionId}.json";
if (!is_dir('../downloads')) {
    mkdir('../downloads', 0777, true);
}

// Initial status
$statusData = [
    'status' => 'processing',
    'progress' => 0,
    'message' => 'Starting briefing generation...',
    'complete' => false,
    'sessionId' => $sessionId,
    'created_at' => time()
];
file_put_contents($statusFile, json_encode($statusData));

// Return ONLY the session ID - no other output
echo json_encode([
    'status' => 'processing',
    'sessionId' => $sessionId
]);

// Flush and close connection to prevent any additional output
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    if (ob_get_level()) {
        ob_end_flush();
    }
    flush();
}

// Now run the background processing
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

    // Update status
    $statusData['progress'] = 10;
    $statusData['message'] = 'Fetching news...';
    file_put_contents($statusFile, json_encode($statusData));

    // Initialize services
    $newsAPI = new NewsAPI($settings);
    $aiService = new AIService($settings);
    $history = new BriefingHistory();

    // Get selected categories
    $selectedCategories = $settings['categories'] ?? [];
    
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
        throw new Exception('No content available. Please enable at least one news source or content type.');
    }

    // Update status
    $statusData['progress'] = 40;
    $statusData['message'] = 'Selecting stories...';
    file_put_contents($statusFile, json_encode($statusData));

    // Select stories (simple selection for now)
    $audioLength = $settings['audioLength'] ?? '5-10';
    $storyCount = 10; // Default story count
    $selectedStories = array_slice($newsItems, 0, $storyCount);

    // Update status
    $statusData['progress'] = 60;
    $statusData['message'] = 'Generating briefing...';
    file_put_contents($statusFile, json_encode($statusData));

    // Generate briefing content
    $briefingContent = $aiService->generateBriefing($selectedStories, $settings);
    
    if (empty($briefingContent)) {
        throw new Exception('Failed to generate briefing content');
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
        'audio_file' => null,
        'duration' => 0,
        'format' => 'text',
        'sources' => $sourcesData
    ]);

    // Final status update
    $statusData = [
        'status' => 'success',
        'progress' => 100,
        'message' => 'Briefing generated successfully',
        'complete' => true,
        'sessionId' => $sessionId,
        'briefingText' => $briefingContent,
        'success' => true
    ];
    file_put_contents($statusFile, json_encode($statusData));

} catch (Exception $e) {
    error_log("Briefing generation error: " . $e->getMessage());
    
    // Error status update
    $statusData = [
        'status' => 'error',
        'progress' => 0,
        'message' => 'Generation failed: ' . $e->getMessage(),
        'complete' => true,
        'sessionId' => $sessionId,
        'error' => $e->getMessage(),
        'success' => false
    ];
    file_put_contents($statusFile, json_encode($statusData));
}
?>