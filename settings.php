<?php
session_start();
require_once 'includes/AuthManager.php';

$auth = new AuthManager();
$auth->requireAuth();

$settingsFile = 'config/user_settings.json';

// RSS Feed Processing Functions
function processRssFeeds($rssFeeds) {
    $processedFeeds = [];
    
    if (!is_array($rssFeeds)) {
        return $processedFeeds;
    }
    
    foreach ($rssFeeds as $feed) {
        if (!empty($feed['url']) && !empty($feed['name']) && !empty($feed['category'])) {
            // Validate category first
            $standardCategories = ['general', 'business', 'entertainment', 'health', 'science', 'sports', 'technology', 'gaming'];
            $categoryLower = strtolower($feed['category']);
            
            if (!in_array($categoryLower, $standardCategories)) {
                $categoryLower = 'general'; // Default to general for any invalid category
            }
            
            // Use proper capitalization for display
            $categoryDisplayMap = [
                'general' => 'General',
                'business' => 'Business', 
                'entertainment' => 'Entertainment',
                'health' => 'Health',
                'science' => 'Science',
                'sports' => 'Sports',
                'technology' => 'Technology',
                'gaming' => 'Gaming'
            ];
            
            $processedFeed = [
                'url' => filter_var($feed['url'], FILTER_SANITIZE_URL),
                'name' => htmlspecialchars($feed['name'], ENT_QUOTES, 'UTF-8'),
                'category' => $categoryDisplayMap[$categoryLower]
            ];
            
            $processedFeeds[] = $processedFeed;
        }
    }
    
    return $processedFeeds;
}

function getRssFeeds() {
    require_once __DIR__ . '/includes/RSSFeedHandler.php';
    $rssHandler = new RSSFeedHandler();
    return $rssHandler->getRssFeeds();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'get_categories') {
        header('Content-Type: application/json');
        
        // Load current settings to get all categories
        $settings = [];
        if (file_exists('config/user_settings.json')) {
            $settings = json_decode(file_get_contents('config/user_settings.json'), true);
        }
        
        // Standard categories
        $categories = [
            ['value' => 'general', 'label' => 'General'],
            ['value' => 'business', 'label' => 'Business'],
            ['value' => 'technology', 'label' => 'Technology'],
            ['value' => 'science', 'label' => 'Science'],
            ['value' => 'health', 'label' => 'Health'],
            ['value' => 'entertainment', 'label' => 'Entertainment'],
            ['value' => 'sports', 'label' => 'Sports'],
            ['value' => 'gaming', 'label' => 'Gaming']
        ];
        
        // Only predefined categories are used - no custom categories
        
        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }
}

// Handle form submission
if ($_POST && !isset($_POST['action'])) {
    $settings = [
        'generateMp3' => isset($_POST['generateMp3']) ? true : false,
        'includeWeather' => isset($_POST['includeWeather']) ? true : false,
        'includeLocal' => isset($_POST['includeLocal']) ? true : false,
        'includeTV' => isset($_POST['includeTV']) ? true : false,
        'zipCode' => $_POST['zipCode'] ?? '',
        'timeFrame' => $_POST['timeFrame'] ?? 'auto',
        'audioLength' => $_POST['audioLength'] ?? '5-10',
        'darkTheme' => isset($_POST['darkTheme']) ? true : false,
        'customHeader' => $_POST['customHeader'] ?? '',
        'aiSelection' => $_POST['aiSelection'] ?? 'openai',
        'aiGeneration' => $_POST['aiGeneration'] ?? 'gemini',
        'blockedTerms' => $_POST['blockedTerms'] ?? '',
        'preferredTerms' => $_POST['preferredTerms'] ?? '',
        'categories' => isset($_POST['categories']) && is_array($_POST['categories']) ? $_POST['categories'] : [],
        'debugMode' => isset($_POST['debugMode']) ? true : false,
        'verboseLogging' => isset($_POST['verboseLogging']) ? true : false,
        'showLogWindow' => isset($_POST['showLogWindow']) ? true : false,
        'authEnabled' => isset($_POST['authEnabled']) ? true : false,
        'gnewsApiKey' => $_POST['gnewsApiKey'] ?? '',
        'newsApiKey' => $_POST['newsApiKey'] ?? '',
        'guardianApiKey' => $_POST['guardianApiKey'] ?? '',
        'nytApiKey' => $_POST['nytApiKey'] ?? '',
        'weatherApiKey' => $_POST['weatherApiKey'] ?? '',
        'tmdbApiKey' => $_POST['tmdbApiKey'] ?? '',
        'openaiApiKey' => $_POST['openaiApiKey'] ?? '',
        'openaiPrompt' => $_POST['openaiPrompt'] ?? 'Create a professional news script.',
        'geminiApiKey' => $_POST['geminiApiKey'] ?? '',
        'geminiPrompt' => $_POST['geminiPrompt'] ?? 'Transform articles into news script.',
        'claudeApiKey' => $_POST['claudeApiKey'] ?? '',
        'claudePrompt' => $_POST['claudePrompt'] ?? 'Generate news briefing script.',
        'googleTtsApiKey' => $_POST['googleTtsApiKey'] ?? '',
        'ttsProvider' => $_POST['ttsProvider'] ?? 'google',
        'voiceSelection' => $_POST['voiceSelection'] ?? 'en-US-Neural2-D',
        'chatterboxServerUrl' => $_POST['chatterboxServerUrl'] ?? 'http://localhost:8000',
        'chatterboxVoice' => $_POST['chatterboxVoice'] ?? 'news_anchor',
        'gnewsEnabled' => isset($_POST['gnewsEnabled']) ? true : false,
        'newsApiEnabled' => isset($_POST['newsApiEnabled']) ? true : false,
        'guardianEnabled' => isset($_POST['guardianEnabled']) ? true : false,
        'nytEnabled' => isset($_POST['nytEnabled']) ? true : false,
        'weatherEnabled' => isset($_POST['weatherEnabled']) ? true : false,
        'tmdbEnabled' => isset($_POST['tmdbEnabled']) ? true : false,
        'openaiEnabled' => isset($_POST['openaiEnabled']) ? true : false,
        'geminiEnabled' => isset($_POST['geminiEnabled']) ? true : false,
        'claudeEnabled' => isset($_POST['claudeEnabled']) ? true : false,
        'googleTtsEnabled' => isset($_POST['googleTtsEnabled']) ? true : false,
        // RSS feeds are handled separately in data/rss_feeds.json, not in main settings
        'lastUpdated' => date('c')
    ];
    
    // Debug log the settings being saved
    error_log("DEBUG: Saving settings with preferredTerms: '" . ($settings['preferredTerms'] ?? 'NOT SET') . "'");
    error_log("DEBUG: POST data for preferredTerms: '" . ($_POST['preferredTerms'] ?? 'NOT IN POST') . "'");
    error_log("DEBUG: Categories being saved: " . json_encode($settings['categories']));
    error_log("DEBUG: POST categories data: " . json_encode($_POST['categories'] ?? 'NOT SET'));
    error_log("DEBUG: RSS Feeds POST data: " . json_encode($_POST['rssFeeds'] ?? 'NOT SET'));
    
    // Handle RSS feeds separately
    if (isset($_POST['rssFeeds']) && is_array($_POST['rssFeeds'])) {
        $processedFeeds = processRssFeeds($_POST['rssFeeds']);
        $rssFile = 'data/rss_feeds.json';
        
        // Create data directory if it doesn't exist
        if (!is_dir('data')) {
            mkdir('data', 0755, true);
        }
        
        file_put_contents($rssFile, json_encode($processedFeeds, JSON_PRETTY_PRINT));
        error_log("DEBUG: Saved RSS feeds to file: " . json_encode($processedFeeds));
    } else {
        // No RSS feeds in POST data, save empty array
        $rssFile = 'data/rss_feeds.json';
        if (!is_dir('data')) {
            mkdir('data', 0755, true);
        }
        file_put_contents($rssFile, json_encode([], JSON_PRETTY_PRINT));
        error_log("DEBUG: No RSS feeds in POST data, saved empty array");
    }
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    header('Location: settings.php?saved=1');
    exit;
}

// Load current settings
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// Default values
$defaults = [
    'generateMp3' => true,
    'showDemoMode' => true,
    'includeWeather' => true,
    'includeLocal' => true,
    'includeTV' => true,
    'zipCode' => '28411',
    'timeFrame' => 'auto',
    'audioLength' => '5-10',
    'darkTheme' => false,
    'customHeader' => '',
    'aiSelection' => 'openai',
    'aiGeneration' => 'gemini',
    'blockedTerms' => '',
    'preferredTerms' => '',
    'categories' => [],
    'rssFeeds' => [],
    'gnewsApiKey' => '',
    'newsApiKey' => '',
    'guardianApiKey' => '',
    'nytApiKey' => '',
    'weatherApiKey' => '',
    'tmdbApiKey' => '',
    'openaiApiKey' => '',
    'openaiPrompt' => 'Create a professional news script from the provided articles.',
    'geminiApiKey' => '',
    'geminiPrompt' => 'Transform these news articles into a clear, professional news briefing script.',
    'claudeApiKey' => '',
    'claudePrompt' => 'Generate a comprehensive news briefing script from the provided articles.',
    'googleTtsApiKey' => '',

    'ttsProvider' => 'google',
    'voiceSelection' => 'en-US-Neural2-D',
    'chatterboxServerUrl' => 'http://localhost:8000',
    'chatterboxVoice' => 'news_anchor',

    'gnewsEnabled' => true,
    'newsApiEnabled' => true,
    'guardianEnabled' => true,
    'nytEnabled' => true,
    'weatherEnabled' => true,
    'tmdbEnabled' => true,
    'openaiEnabled' => true,
    'geminiEnabled' => true,
    'claudeEnabled' => true,
    'googleTtsEnabled' => true,

    'debugMode' => false,
    'verboseLogging' => false,
    'showLogWindow' => false,
    'authEnabled' => false
];

// Merge defaults but preserve empty arrays for categories
$settings = array_merge($defaults, $settings);

// Special handling for categories - don't merge defaults if categories were explicitly set (even to empty)
if (isset($settings['categories']) && is_array($settings['categories'])) {
    // Keep the explicitly set categories (including empty array)
} else {
    // Only use defaults if categories weren't set at all
    $settings['categories'] = $defaults['categories'];
}

function isChecked($setting) {
    global $settings;
    return !empty($settings[$setting]) ? 'checked' : '';
}

function getValue($setting) {
    global $settings;
    return htmlspecialchars($settings[$setting] ?? '');
}

function isSelected($setting, $value) {
    global $settings;
    return ($settings[$setting] ?? '') === $value ? 'selected' : '';
}

function isCategoryChecked($category) {
    global $settings;
    return in_array($category, $settings['categories'] ?? []) ? 'checked' : '';
}

// Custom categories removed - using predefined categories only


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - NewsBear</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script>
    // Apply dark theme immediately to prevent flash
    (function() {
        const savedTheme = localStorage.getItem('darkTheme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'true' || (savedTheme === null && systemPrefersDark)) {
            document.documentElement.classList.add('dark-theme-loading');
        }
    })();
    </script>
    <style>
    .dark-theme-loading {
        background-color: #1a1a1a !important;
        color: #e0e0e0 !important;
    }
    .dark-theme-loading * {
        background-color: inherit !important;
        color: inherit !important;
    }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-2 sm:px-4 py-4 sm:py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 lg:p-8">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between mb-6 gap-4">
                <h1 class="text-xl sm:text-2xl font-bold text-gray-800">
                    <i class="fas fa-cog mr-2"></i>Settings
                </h1>
                <div class="flex gap-3">
                    <button type="submit" form="settings-form" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm sm:text-base">
                        <i class="fas fa-save mr-2"></i>Save Settings
                    </button>
                    <a href="/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm sm:text-base inline-flex items-center">
                        <i class="fas fa-home mr-2"></i>Go Home
                    </a>
                </div>
            </div>
            
            <?php if (isset($_GET['saved'])): ?>
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-green-800">Settings saved successfully!</span>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Tab Navigation - Mobile Responsive -->
            <div class="border-b border-gray-200 mb-6">
                <!-- Mobile dropdown for tabs -->
                <div class="sm:hidden mb-4">
                    <select id="mobile-tab-select" onchange="showTab(this.value)" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="basic">🔧 Basic Settings</option>
                        <option value="content">📰 Content & Categories</option>
                        <option value="rss">📡 RSS Feeds</option>
                        <option value="api">🔑 API Keys</option>
                        <option value="ai">🤖 AI Services</option>
                        <option value="scheduling">⏰ Scheduling</option>
                        <option value="history">📜 History</option>
                        <option value="advanced">⚙️ Advanced</option>
                    </select>
                </div>
                
                <!-- Desktop tabs with icons only -->
                <nav class="hidden sm:flex -mb-px justify-center" role="tablist">
                    <div class="flex space-x-6">
                        <button type="button" onclick="showTab('basic')" id="basic-tab" class="py-2 px-3 border-b-2 border-blue-500 font-medium text-blue-600 hover:text-blue-700 flex flex-col items-center" role="tab" title="Basic Settings">
                            <i class="fas fa-cog text-lg"></i>
                            <span class="text-xs mt-1">Basic</span>
                        </button>
                        <button type="button" onclick="showTab('content')" id="content-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="Content & Categories">
                            <i class="fas fa-newspaper text-lg"></i>
                            <span class="text-xs mt-1">Content</span>
                        </button>
                        <button type="button" onclick="showTab('rss')" id="rss-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="RSS Feeds">
                            <i class="fas fa-rss text-lg"></i>
                            <span class="text-xs mt-1">RSS</span>
                        </button>
                        <button type="button" onclick="showTab('api')" id="api-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="API Keys">
                            <i class="fas fa-key text-lg"></i>
                            <span class="text-xs mt-1">API</span>
                        </button>
                        <button type="button" onclick="showTab('ai')" id="ai-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="AI Services">
                            <i class="fas fa-robot text-lg"></i>
                            <span class="text-xs mt-1">AI</span>
                        </button>
                        <button type="button" onclick="showTab('scheduling')" id="scheduling-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="Scheduling">
                            <i class="fas fa-clock text-lg"></i>
                            <span class="text-xs mt-1">Schedule</span>
                        </button>
                        <button type="button" onclick="showTab('history')" id="history-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="History">
                            <i class="fas fa-history text-lg"></i>
                            <span class="text-xs mt-1">History</span>
                        </button>
                        <button type="button" onclick="showTab('advanced')" id="advanced-tab" class="py-2 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300 flex flex-col items-center" role="tab" title="Advanced">
                            <i class="fas fa-tools text-lg"></i>
                            <span class="text-xs mt-1">Advanced</span>
                        </button>
                    </div>
                </nav>

            </div>

            <!-- History Tab (Outside Form) -->
            <div id="history-content" class="tab-content hidden">
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-800">Briefing History</h3>
                        <div class="flex space-x-2">
                            <button type="button" onclick="refreshHistory()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-md">
                                <i class="fas fa-refresh mr-2"></i>Refresh
                            </button>
                            <button type="button" onclick="showCleanupModal()" class="bg-red-600 hover:bg-red-700 text-white text-sm px-4 py-2 rounded-md">
                                <i class="fas fa-trash mr-2"></i>Cleanup
                            </button>
                        </div>
                    </div>
                    
                    <div id="history-loading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-2xl mb-4"></i>
                        <p class="text-gray-600">Loading briefing history...</p>
                    </div>
                    
                    <div id="history-list" class="hidden space-y-4 bg-white rounded-lg shadow-md divide-y divide-gray-200">
                        <!-- History items will be loaded here -->
                    </div>
                    
                    <div id="history-empty" class="hidden text-center py-8">
                        <i class="fas fa-history text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600">No briefings found in history.</p>
                    </div>
                    
                    <div id="history-pagination" class="hidden flex justify-center items-center space-x-4 mt-6">
                        <button type="button" onclick="loadHistoryPage(currentHistoryPage - 1)" id="prev-page" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm disabled:opacity-50" disabled>
                            <i class="fas fa-chevron-left mr-2"></i>Previous
                        </button>
                        <span id="page-info" class="text-sm text-gray-600"></span>
                        <button type="button" onclick="loadHistoryPage(currentHistoryPage + 1)" id="next-page" class="bg-gray-300 hover:bg-gray-400 text-gray-700 px-4 py-2 rounded-md text-sm disabled:opacity-50" disabled>
                            Next<i class="fas fa-chevron-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Scheduling Tab (Outside Form) -->
            <div id="scheduling-content" class="tab-content hidden">
                <div class="space-y-6">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-800">Automated Briefing Schedules</h3>
                        <button type="button" onclick="showNewScheduleModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm">
                            <i class="fas fa-plus mr-2"></i>Create Schedule
                        </button>
                    </div>
                    
                    <div id="schedules-loading" class="text-center py-8">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-2xl mb-4"></i>
                        <p class="text-gray-600">Loading schedules...</p>
                    </div>
                    
                    <div id="schedules-list" class="hidden space-y-4">
                        <!-- Schedule items will be loaded here -->
                    </div>
                    
                    <div id="schedules-empty" class="hidden text-center py-8">
                        <i class="fas fa-clock text-gray-400 text-4xl mb-4"></i>
                        <p class="text-gray-600">No scheduled briefings configured.</p>
                        <p class="text-gray-500 text-sm mt-2">Create a schedule to automatically generate briefings at specific times.</p>
                    </div>
                </div>
            </div>

            <form id="settings-form" method="POST" class="space-y-8">
                <!-- Basic Settings Tab -->
                <div id="basic-content" class="tab-content">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">General Settings</h3>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Time Frame</label>
                                    <select name="timeFrame" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                        <option value="auto" <?= isSelected('timeFrame', 'auto') ?>>Auto</option>
                                        <option value="morning" <?= isSelected('timeFrame', 'morning') ?>>Morning</option>
                                        <option value="afternoon" <?= isSelected('timeFrame', 'afternoon') ?>>Afternoon</option>
                                        <option value="evening" <?= isSelected('timeFrame', 'evening') ?>>Evening</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Audio Length</label>
                                    <select name="audioLength" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                        <option value="3-5" <?= isSelected('audioLength', '3-5') ?>>3-5 minutes</option>
                                        <option value="5-10" <?= isSelected('audioLength', '5-10') ?>>5-10 minutes</option>
                                        <option value="10-15" <?= isSelected('audioLength', '10-15') ?>>10-15 minutes</option>
                                        <option value="15-20" <?= isSelected('audioLength', '15-20') ?>>15-20 minutes</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">ZIP Code</label>
                                    <input type="text" name="zipCode" value="<?= getValue('zipCode') ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Enter ZIP code for local news">
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Features</h3>
                                <div class="space-y-3">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="generateMp3" <?= isChecked('generateMp3') ?> class="mr-3 h-4 w-4">
                                        Generate MP3 Audio File
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="includeWeather" <?= isChecked('includeWeather') ?> class="mr-3 h-4 w-4">
                                        Include Weather
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="includeLocal" <?= isChecked('includeLocal') ?> class="mr-3 h-4 w-4">
                                        Include Local News
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="includeTV" <?= isChecked('includeTV') ?> class="mr-3 h-4 w-4">
                                        Include TV Shows/Movies
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="darkTheme" id="darkTheme" <?= isChecked('darkTheme') ?> class="mr-3 h-4 w-4" onchange="toggleDarkThemeFromSettings()">
                                        Dark Theme
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Text-to-Speech Provider</label>
                                    <select name="ttsProvider" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" onchange="toggleTtsOptions()">
                                        <option value="google" <?= isSelected('ttsProvider', 'google') ?>>Google TTS (Premium Quality)</option>
                                        <option value="chatterbox" <?= isSelected('ttsProvider', 'chatterbox') ?>>Chatterbox TTS (Local Server)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Choose your preferred text-to-speech engine</p>
                                </div>
                                
                                <div id="google-voice-options" style="display: <?= ($settings['ttsProvider'] ?? 'google') === 'google' ? 'block' : 'none' ?>">
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Voice Selection (Google TTS)</label>
                                    <select name="voiceSelection" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                        <optgroup label="American Male Voices">
                                            <option value="en-US-Neural2-D" <?= isSelected('voiceSelection', 'en-US-Neural2-D') ?>>David - Standard American ($)</option>
                                            <option value="en-US-Neural2-J" <?= isSelected('voiceSelection', 'en-US-Neural2-J') ?>>John - Deep American ($$)</option>
                                            <option value="en-US-Wavenet-D" <?= isSelected('voiceSelection', 'en-US-Wavenet-D') ?>>Oliver - Studio Quality ($$$)</option>
                                        </optgroup>
                                        <optgroup label="British Male Voices">
                                            <option value="en-GB-Neural2-D" <?= isSelected('voiceSelection', 'en-GB-Neural2-D') ?>>Daniel - Standard British ($)</option>
                                            <option value="en-GB-Neural2-B" <?= isSelected('voiceSelection', 'en-GB-Neural2-B') ?>>Benjamin - Refined British ($$)</option>
                                            <option value="en-GB-Standard-D" <?= isSelected('voiceSelection', 'en-GB-Standard-D') ?>>David - Premium British ($$$)</option>
                                        </optgroup>
                                        <optgroup label="Australian Male Voices">
                                            <option value="en-AU-Neural2-D" <?= isSelected('voiceSelection', 'en-AU-Neural2-D') ?>>Dylan - Standard Australian ($)</option>
                                            <option value="en-AU-Neural2-B" <?= isSelected('voiceSelection', 'en-AU-Neural2-B') ?>>Blake - Deep Australian ($$)</option>
                                            <option value="en-AU-Standard-D" <?= isSelected('voiceSelection', 'en-AU-Standard-D') ?>>David - Premium Australian ($$$)</option>
                                        </optgroup>
                                        <optgroup label="Female Voices">
                                            <option value="en-US-Neural2-F" <?= isSelected('voiceSelection', 'en-US-Neural2-F') ?>>Fiona - American Female ($)</option>
                                            <option value="en-GB-Neural2-F" <?= isSelected('voiceSelection', 'en-GB-Neural2-F') ?>>Victoria - British Female ($)</option>
                                            <option value="en-AU-Neural2-A" <?= isSelected('voiceSelection', 'en-AU-Neural2-A') ?>>Olivia - Australian Female ($)</option>
                                        </optgroup>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">$ = Standard quality, $$ = Enhanced quality, $$$ = Studio quality</p>
                                </div>
                                
                                <div id="chatterbox-options" style="display: <?= ($settings['ttsProvider'] ?? 'google') === 'chatterbox' ? 'block' : 'none' ?>">
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Chatterbox Server URL</label>
                                            <input type="url" name="chatterboxServerUrl" value="<?= getValue('chatterboxServerUrl') ?>" placeholder="http://localhost:8000" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                            <p class="text-xs text-gray-500 mt-1">URL of your local Chatterbox TTS server</p>
                                        </div>
                                        
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Voice Style</label>
                                            <select name="chatterboxVoice" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                                <option value="news_anchor" <?= isSelected('chatterboxVoice', 'news_anchor') ?>>News Anchor - Professional delivery</option>
                                                <option value="conversational" <?= isSelected('chatterboxVoice', 'conversational') ?>>Conversational - Natural tone</option>
                                                <option value="dramatic" <?= isSelected('chatterboxVoice', 'dramatic') ?>>Dramatic - Expressive</option>
                                                <option value="calm" <?= isSelected('chatterboxVoice', 'calm') ?>>Calm - Relaxed delivery</option>
                                            </select>
                                        </div>
                                        
                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                            <h4 class="text-sm font-medium text-blue-800 mb-2">Local Chatterbox Setup</h4>
                                            <ul class="text-xs text-blue-700 space-y-1">
                                                <li>• Requires local Chatterbox server running</li>
                                                <li>• Processing takes longer than cloud TTS</li>
                                                <li>• Audio requests are queued automatically</li>
                                                <li>• Status updates provided during generation</li>
                                            </ul>
                                            <button type="button" onclick="testChatterboxConnection()" class="mt-3 px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 transition-colors">
                                                Test Connection
                                            </button>
                                            <div id="chatterbox-test-result" class="mt-2 text-xs"></div>
                                        </div>
                                    </div>
                                </div>
                                

                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content & Categories Tab -->
                <div id="content-content" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">News Categories</h3>
                                <div class="space-y-3">
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="general" <?= isCategoryChecked('general') ?> class="mr-3 h-4 w-4">
                                        General
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="technology" <?= isCategoryChecked('technology') ?> class="mr-3 h-4 w-4">
                                        Technology
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="science" <?= isCategoryChecked('science') ?> class="mr-3 h-4 w-4">
                                        Science
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="health" <?= isCategoryChecked('health') ?> class="mr-3 h-4 w-4">
                                        Health
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="entertainment" <?= isCategoryChecked('entertainment') ?> class="mr-3 h-4 w-4">
                                        Entertainment
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="business" <?= isCategoryChecked('business') ?> class="mr-3 h-4 w-4">
                                        Business
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="sports" <?= isCategoryChecked('sports') ?> class="mr-3 h-4 w-4">
                                        Sports
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="categories[]" value="gaming" <?= isCategoryChecked('gaming') ?> class="mr-3 h-4 w-4">
                                        Gaming
                                    </label>
                                    

                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Content Filters</h3>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Blocked Terms</label>
                                    <textarea name="blockedTerms" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="trump, elon musk, bitcoin"><?= getValue('blockedTerms') ?></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Enter comma-separated terms. Articles containing any of these terms will be excluded from your briefings.</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Preferred Terms</label>
                                    <textarea name="preferredTerms" rows="3" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="ai, technology, climate"><?= getValue('preferredTerms') ?></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Enter comma-separated terms. Articles containing these terms will be prioritized in your briefings.</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Custom Header</label>
                                    <input type="text" name="customHeader" value="<?= getValue('customHeader') ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Custom briefing header text">
                                    <p class="text-xs text-gray-500 mt-1">Optional custom introduction for your briefings</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- RSS Feeds Tab -->
                <div id="rss-content" class="tab-content hidden">
                    <div class="space-y-6">
                        <!-- RSS Sub-tabs -->
                        <div class="border-b border-gray-200">
                            <nav class="-mb-px flex space-x-8">
                                <button type="button" onclick="showRssSubTab('feeds')" class="rss-sub-tab active border-transparent text-blue-600 border-blue-500 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                    RSS Sources
                                </button>
                                <button type="button" onclick="showRssSubTab('server')" class="rss-sub-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm">
                                    Podcast Server
                                </button>
                            </nav>
                        </div>

                        <!-- RSS Sources Sub-tab -->
                        <div id="rss-feeds-subtab" class="rss-subtab-content">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-800">RSS Feed Sources</h3>
                                <button type="button" onclick="addRssFeed()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-md">
                                    <i class="fas fa-plus mr-2"></i>Add RSS Feed
                                </button>
                            </div>
                            
                            <div id="rss-feeds-container" class="space-y-4 mt-6">
                                <!-- RSS feeds will be dynamically added here -->
                            </div>
                            
                            <div class="text-center text-gray-500 text-sm" id="no-rss-message">
                                No RSS feeds configured. Click "Add RSS Feed" to get started.
                            </div>
                        </div>

                        <!-- Podcast Server Sub-tab -->
                        <div id="rss-server-subtab" class="rss-subtab-content hidden">
                            <div class="text-center py-12">
                                <div class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-8">
                                    <i class="fas fa-podcast text-4xl text-gray-400 mb-4"></i>
                                    <h3 class="text-lg font-medium text-gray-800 mb-2">Podcast Server</h3>
                                    <p class="text-gray-600 mb-4">Transform your news briefings into a custom podcast feed.</p>
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                                        <p class="text-sm text-blue-800">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            This feature will allow you to host your generated briefings as a podcast RSS feed that can be subscribed to in podcast apps.
                                        </p>
                                    </div>
                                    <p class="text-sm text-gray-500">Coming in a future update</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- API Keys Tab -->
                <div id="api-content" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">News API Keys</h3>
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="gnewsEnabled" <?= isChecked('gnewsEnabled') ?> class="mr-3 h-4 w-4">
                                        GNews API
                                    </label>
                                    <input type="password" name="gnewsApiKey" value="<?= getValue('gnewsApiKey') ?>" placeholder="GNews API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://gnews.io" target="_blank" class="text-blue-600 hover:underline">gnews.io</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="newsApiEnabled" <?= isChecked('newsApiEnabled') ?> class="mr-3 h-4 w-4">
                                        NewsAPI.org
                                    </label>
                                    <input type="password" name="newsApiKey" value="<?= getValue('newsApiKey') ?>" placeholder="NewsAPI Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://newsapi.org/register" target="_blank" class="text-blue-600 hover:underline">newsapi.org</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="guardianEnabled" <?= isChecked('guardianEnabled') ?> class="mr-3 h-4 w-4">
                                        Guardian API
                                    </label>
                                    <input type="password" name="guardianApiKey" value="<?= getValue('guardianApiKey') ?>" placeholder="Guardian API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://open-platform.theguardian.com/access/" target="_blank" class="text-blue-600 hover:underline">theguardian.com</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="nytEnabled" <?= isChecked('nytEnabled') ?> class="mr-3 h-4 w-4">
                                        NY Times API
                                    </label>
                                    <input type="password" name="nytApiKey" value="<?= getValue('nytApiKey') ?>" placeholder="NY Times API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://developer.nytimes.com/get-started" target="_blank" class="text-blue-600 hover:underline">developer.nytimes.com</a></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Other Services</h3>
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="weatherEnabled" <?= isChecked('weatherEnabled') ?> class="mr-3 h-4 w-4">
                                        OpenWeatherMap API
                                    </label>
                                    <input type="password" name="weatherApiKey" value="<?= getValue('weatherApiKey') ?>" placeholder="OpenWeatherMap API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://openweathermap.org/api" target="_blank" class="text-blue-600 hover:underline">openweathermap.org</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="tmdbEnabled" <?= isChecked('tmdbEnabled') ?> class="mr-3 h-4 w-4">
                                        TMDB (TV/Movies)
                                    </label>
                                    <input type="password" name="tmdbApiKey" value="<?= getValue('tmdbApiKey') ?>" placeholder="TMDB API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://www.themoviedb.org/settings/api" target="_blank" class="text-blue-600 hover:underline">themoviedb.org</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="googleTtsEnabled" <?= isChecked('googleTtsEnabled') ?> class="mr-3 h-4 w-4">
                                        Google Text-to-Speech
                                    </label>
                                    <input type="password" name="googleTtsApiKey" value="<?= getValue('googleTtsApiKey') ?>" placeholder="Google TTS API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your API key at <a href="https://console.cloud.google.com/" target="_blank" class="text-blue-600 hover:underline">Google Cloud Console</a></p>
                                </div>
                                

                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI Services Tab -->
                <div id="ai-content" class="tab-content hidden">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">AI Configuration</h3>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">AI for Article Selection</label>
                                    <select name="aiSelection" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                        <option value="openai" <?= isSelected('aiSelection', 'openai') ?>>OpenAI</option>
                                        <option value="gemini" <?= isSelected('aiSelection', 'gemini') ?>>Gemini</option>
                                        <option value="claude" <?= isSelected('aiSelection', 'claude') ?>>Claude</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">AI service used to select the most important articles</p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">AI for Content Generation</label>
                                    <select name="aiGeneration" class="w-full border border-gray-300 rounded-md px-3 py-2">
                                        <option value="openai" <?= isSelected('aiGeneration', 'openai') ?>>OpenAI</option>
                                        <option value="gemini" <?= isSelected('aiGeneration', 'gemini') ?>>Gemini</option>
                                        <option value="claude" <?= isSelected('aiGeneration', 'claude') ?>>Claude</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">AI service used to generate the news briefing content</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">AI API Keys</h3>
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="openaiEnabled" <?= isChecked('openaiEnabled') ?> class="mr-3 h-4 w-4">
                                        OpenAI API
                                    </label>
                                    <input type="password" name="openaiApiKey" value="<?= getValue('openaiApiKey') ?>" placeholder="OpenAI API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your API key at <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 hover:underline">platform.openai.com</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="geminiEnabled" <?= isChecked('geminiEnabled') ?> class="mr-3 h-4 w-4">
                                        Gemini API
                                    </label>
                                    <input type="password" name="geminiApiKey" value="<?= getValue('geminiApiKey') ?>" placeholder="Gemini API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-blue-600 hover:underline">Google AI Studio</a></p>
                                </div>
                                
                                <div>
                                    <label class="flex items-center mb-2 text-sm">
                                        <input type="checkbox" name="claudeEnabled" <?= isChecked('claudeEnabled') ?> class="mr-3 h-4 w-4">
                                        Claude API
                                    </label>
                                    <input type="password" name="claudeApiKey" value="<?= getValue('claudeApiKey') ?>" placeholder="Claude API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your API key at <a href="https://console.anthropic.com/" target="_blank" class="text-blue-600 hover:underline">console.anthropic.com</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Settings Tab -->
                <div id="advanced-content" class="tab-content hidden">
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 lg:gap-8">
                            <div class="space-y-6">
                                <div class="space-y-4">
                                    <h3 class="text-lg font-medium text-gray-800 border-b pb-2">AI Prompts</h3>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">OpenAI Custom Prompt</label>
                                        <textarea name="openaiPrompt" rows="3" placeholder="Custom prompt for OpenAI" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('openaiPrompt') ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">Custom instructions for OpenAI content generation</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Gemini Custom Prompt</label>
                                        <textarea name="geminiPrompt" rows="3" placeholder="Custom prompt for Gemini" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('geminiPrompt') ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">Custom instructions for Gemini content generation</p>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Claude Custom Prompt</label>
                                        <textarea name="claudePrompt" rows="3" placeholder="Custom prompt for Claude" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('claudePrompt') ?></textarea>
                                        <p class="text-xs text-gray-500 mt-1">Custom instructions for Claude content generation</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-6">
                                <div class="space-y-4">
                                    <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Debug & Development</h3>
                                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                        <p class="text-sm text-yellow-800">
                                            <i class="fas fa-exclamation-triangle mr-2"></i>
                                            Advanced settings for debugging and development. Only modify if you understand the implications.
                                        </p>
                                    </div>
                                    
                                    <div class="space-y-3">
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" name="debugMode" <?= isChecked('debugMode') ?> class="mr-3 h-4 w-4">
                                            Enable Debug Mode
                                        </label>
                                        <p class="text-xs text-gray-500 ml-7">Shows detailed error messages and logging information</p>
                                        
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" name="verboseLogging" <?= isChecked('verboseLogging') ?> class="mr-3 h-4 w-4">
                                            Verbose Logging
                                        </label>
                                        <p class="text-xs text-gray-500 ml-7">Logs detailed information about system operations</p>
                                        
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" name="showLogWindow" <?= isChecked('showLogWindow') ?> class="mr-3 h-4 w-4">
                                            Show Debug Log Window
                                        </label>
                                        <p class="text-xs text-gray-500 ml-7">Display real-time generation logs on the main page during briefing creation</p>
                                        
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" name="authEnabled" <?= isChecked('authEnabled') ?> class="mr-3 h-4 w-4">
                                            Enable Authentication
                                        </label>
                                        <p class="text-xs text-gray-500 ml-7">Require login for access to settings and briefing generation</p>
                                        
                                        <?php 
                                        $authStatus = $auth->getAuthStatus();
                                        if ($authStatus['enabled'] && $authStatus['loggedIn']): ?>
                                        <div class="ml-7 mt-2">
                                            <a href="logout.php" class="text-sm text-red-600 hover:text-red-800 flex items-center">
                                                <i class="fas fa-sign-out-alt mr-2"></i>Logout (<?= htmlspecialchars($authStatus['username']) ?>)
                                            </a>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

<script>
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active styles from all tabs
    const tabs = document.querySelectorAll('[role="tab"]');
    tabs.forEach(tab => {
        tab.classList.remove('border-blue-500', 'text-blue-600', 'text-blue-700');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show the selected tab content
    const selectedContent = document.getElementById(tabName + '-content');
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
    }
    
    // Add active styles to the selected tab
    const selectedTab = document.getElementById(tabName + '-tab');
    if (selectedTab) {
        selectedTab.classList.remove('border-transparent', 'text-gray-500');
        selectedTab.classList.add('border-blue-500', 'text-blue-600');
    }
    
    // Update mobile dropdown to match
    const mobileSelect = document.getElementById('mobile-tab-select');
    if (mobileSelect) {
        mobileSelect.value = tabName;
    }
    

    
    // Load history data when history tab is selected
    if (tabName === 'history') {
        loadHistoryPage(1);
    }
    
    // Load schedules when scheduling tab is selected
    if (tabName === 'scheduling') {
        loadSchedules();
    }
}



function toggleDarkThemeFromSettings() {
    const darkThemeToggle = document.getElementById('darkTheme');
    if (darkThemeToggle.checked) {
        document.body.classList.add('dark-theme');
        localStorage.setItem('darkTheme', 'true');
    } else {
        document.body.classList.remove('dark-theme');
        localStorage.setItem('darkTheme', 'false');
    }
}

// RSS Feed Management
let rssFeedCounter = 0;
// Custom categories removed - using predefined categories only

// RSS Sub-tab Management
function showRssSubTab(tabName) {
    // Hide all subtab content
    document.querySelectorAll('.rss-subtab-content').forEach(content => {
        content.classList.add('hidden');
    });
    
    // Remove active class from all sub-tabs
    document.querySelectorAll('.rss-sub-tab').forEach(tab => {
        tab.classList.remove('active', 'text-blue-600', 'border-blue-500');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected subtab content
    if (tabName === 'feeds') {
        document.getElementById('rss-feeds-subtab').classList.remove('hidden');
        // Activate the feeds tab
        const feedsTab = document.querySelector('[onclick="showRssSubTab(\'feeds\')"]');
        if (feedsTab) {
            feedsTab.classList.add('active', 'text-blue-600', 'border-blue-500');
            feedsTab.classList.remove('border-transparent', 'text-gray-500');
        }
    } else if (tabName === 'server') {
        document.getElementById('rss-server-subtab').classList.remove('hidden');
        // Activate the server tab
        const serverTab = document.querySelector('[onclick="showRssSubTab(\'server\')"]');
        if (serverTab) {
            serverTab.classList.add('active', 'text-blue-600', 'border-blue-500');
            serverTab.classList.remove('border-transparent', 'text-gray-500');
        }
    }
}

// Load existing custom categories from settings
function loadCustomCategories() {
    fetch('settings.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_categories'
    })
    .then(response => response.json())
    .then(data => {
        // Categories loaded successfully - only predefined categories are used now
    });
}

function buildCategoryOptions(selectedCategory = '') {
    const standardCategories = [
        { value: 'general', label: 'General' },
        { value: 'business', label: 'Business' },
        { value: 'entertainment', label: 'Entertainment' },
        { value: 'health', label: 'Health' },
        { value: 'science', label: 'Science' },
        { value: 'sports', label: 'Sports' },
        { value: 'technology', label: 'Technology' },
        { value: 'gaming', label: 'Gaming' }
    ];
    
    let options = '';
    
    // Normalize selected category for comparison (convert to lowercase)
    const selectedLower = selectedCategory.toLowerCase();
    
    // Add standard categories only
    standardCategories.forEach(cat => {
        const selected = selectedLower === cat.value ? 'selected' : '';
        options += `<option value="${cat.value}" ${selected}>${cat.label}</option>`;
    });
    
    return options;
}

function addRssFeed(url = '', name = '', category = '', isNewFeed = true) {
    const container = document.getElementById('rss-feeds-container');
    const noMessage = document.getElementById('no-rss-message');
    
    rssFeedCounter++;
    const feedId = 'rss_feed_' + rssFeedCounter;
    const displayName = name || `RSS Feed ${rssFeedCounter}`;
    const isEditMode = isNewFeed;
    
    // Ensure category defaults to 'general' if invalid
    const validCategory = category || 'general';
    
    const feedHtml = `
        <div class="border border-gray-200 rounded-lg bg-white" id="${feedId}">
            <!-- Compact View -->
            <div class="feed-compact-view ${isEditMode ? 'hidden' : ''}" id="compact-${feedId}">
                <div class="flex items-center justify-between p-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-3">
                            <h4 class="font-medium text-gray-800" id="display-name-${feedId}">${displayName}</h4>
                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full" id="category-badge-${feedId}">${getCategoryDisplayName(validCategory)}</span>
                        </div>
                        <p class="text-sm text-gray-500 mt-1 truncate" id="url-display-${feedId}">${url}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" onclick="editRssFeed('${feedId}')" class="text-blue-600 hover:text-blue-800 text-sm">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </button>
                        <button type="button" onclick="removeRssFeed('${feedId}')" class="text-red-600 hover:text-red-800 text-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Edit View -->
            <div class="feed-edit-view ${isEditMode ? '' : 'hidden'}" id="edit-${feedId}">
                <div class="p-4">
                    <div class="flex justify-between items-start mb-4">
                        <h4 class="font-medium text-gray-800">Edit RSS Feed</h4>
                        <div class="flex gap-2">
                            <button type="button" onclick="saveRssFeed('${feedId}')" class="bg-green-600 hover:bg-green-700 text-white text-sm px-3 py-2 rounded-md">
                                <i class="fas fa-save mr-1"></i>Save
                            </button>
                            <button type="button" onclick="cancelEditRssFeed('${feedId}')" class="bg-gray-500 hover:bg-gray-600 text-white text-sm px-3 py-2 rounded-md">
                                Cancel
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Feed URL</label>
                            <input type="url" name="rssFeeds[${rssFeedCounter}][url]" value="${url}" placeholder="https://example.com/feed.xml" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required id="url-input-${feedId}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                            <input type="text" name="rssFeeds[${rssFeedCounter}][name]" value="${name}" placeholder="Source Name" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required id="name-input-${feedId}">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select name="rssFeeds[${rssFeedCounter}][category]" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" id="category-select-${feedId}">
                                ${buildCategoryOptions(validCategory)}
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('afterbegin', feedHtml);
    noMessage.style.display = 'none';
}

function getCategoryDisplayName(category) {
    const standardCategories = {
        'general': 'General',
        'business': 'Business',
        'entertainment': 'Entertainment',
        'health': 'Health',
        'science': 'Science',
        'sports': 'Sports',
        'technology': 'Technology',
        'gaming': 'Gaming'
    };
    
    // Return the proper category display name
    
    return standardCategories[category] || category;
}

function editRssFeed(feedId) {
    document.getElementById(`compact-${feedId}`).classList.add('hidden');
    document.getElementById(`edit-${feedId}`).classList.remove('hidden');
}

function saveRssFeed(feedId) {
    const nameInput = document.getElementById(`name-input-${feedId}`);
    const urlInput = document.getElementById(`url-input-${feedId}`);
    const categorySelect = document.getElementById(`category-select-${feedId}`);
    
    // Validate inputs
    if (!nameInput.value.trim() || !urlInput.value.trim()) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Update compact view
    document.getElementById(`display-name-${feedId}`).textContent = nameInput.value;
    document.getElementById(`url-display-${feedId}`).textContent = urlInput.value;
    document.getElementById(`category-badge-${feedId}`).textContent = getCategoryDisplayName(categorySelect.value);
    
    // Switch to compact view
    document.getElementById(`edit-${feedId}`).classList.add('hidden');
    document.getElementById(`compact-${feedId}`).classList.remove('hidden');
}

function cancelEditRssFeed(feedId) {
    // If this is a new feed being cancelled, remove it entirely
    const nameInput = document.getElementById(`name-input-${feedId}`);
    const urlInput = document.getElementById(`url-input-${feedId}`);
    
    if (!nameInput.value.trim() && !urlInput.value.trim()) {
        removeRssFeed(feedId);
        return;
    }
    
    // Otherwise, just switch back to compact view
    document.getElementById(`edit-${feedId}`).classList.add('hidden');
    document.getElementById(`compact-${feedId}`).classList.remove('hidden');
}

// Custom category functions removed - using predefined categories only

// updateAllCategorySelects function removed - no longer needed without custom categories

function removeRssFeed(feedId) {
    const feedElement = document.getElementById(feedId);
    if (feedElement) {
        feedElement.remove();
        
        // Check if any feeds remain
        const container = document.getElementById('rss-feeds-container');
        const noMessage = document.getElementById('no-rss-message');
        if (container.children.length === 0) {
            noMessage.style.display = 'block';
        }
    }
}

// History management variables
let currentHistoryPage = 1;
let totalHistoryPages = 1;

// History management functions
function refreshHistory() {
    loadHistoryPage(1);
}

function loadHistoryPage(page) {
    currentHistoryPage = page;
    const loadingDiv = document.getElementById('history-loading');
    const listDiv = document.getElementById('history-list');
    const emptyDiv = document.getElementById('history-empty');
    const paginationDiv = document.getElementById('history-pagination');
    
    // Show loading
    loadingDiv.classList.remove('hidden');
    listDiv.classList.add('hidden');
    emptyDiv.classList.add('hidden');
    paginationDiv.classList.add('hidden');
    
    // Fetch history data
    fetch(`api/history.php?page=${page}`)
        .then(response => response.json())
        .then(data => {
            loadingDiv.classList.add('hidden');
            
            if (data.success && data.briefings && data.briefings.length > 0) {
                displayHistoryItems(data.briefings);
                updatePagination(data.pagination);
                listDiv.classList.remove('hidden');
                paginationDiv.classList.remove('hidden');
            } else {
                emptyDiv.classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Error loading history:', error);
            loadingDiv.classList.add('hidden');
            emptyDiv.classList.remove('hidden');
        });
}

function displayHistoryItems(briefings) {
    const listDiv = document.getElementById('history-list');
    listDiv.innerHTML = '';
    
    briefings.forEach(briefing => {
        const item = document.createElement('div');
        item.className = 'border-b border-gray-200 p-4 md:p-6';
        
        const date = new Date(briefing.timestamp * 1000).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Build topics HTML
        let topicsHtml = '';
        if (briefing.topics && briefing.topics.length > 0) {
            topicsHtml = `
                <div class="mb-3">
                    <div class="flex flex-wrap gap-1">
                        ${briefing.topics.slice(0, 8).map(topic => {
                            const topicTitle = typeof topic === 'object' ? topic.title : topic;
                            return `<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">${topicTitle}</span>`;
                        }).join('')}
                    </div>
                </div>
            `;
        }
        
        // Build sources HTML
        let sourcesHtml = '';
        if (briefing.sources && briefing.sources.length > 0) {
            sourcesHtml = `
                <div class="mb-3">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-link mr-1"></i>News Sources:
                    </h4>
                    <div class="space-y-1">
                        ${briefing.sources.map(source => `
                            <div class="text-xs bg-blue-50 border border-blue-200 rounded p-2">
                                <a href="${source.url}" target="_blank" class="text-blue-700 hover:text-blue-900 font-medium">
                                    ${source.title}
                                </a>
                                <span class="text-gray-500 ml-2">(${source.source})</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        
        // Build audio player HTML
        let audioHtml = '';
        if (briefing.audio_file) {
            audioHtml = `
                <div id="audio-${briefing.id}" class="hidden mt-4 audio-container">
                    <div class="mb-3">
                        <i class="fas fa-volume-up text-blue-600 mr-2"></i>
                        <span class="text-sm font-medium text-gray-700">Audio Playback</span>
                    </div>
                    <div class="custom-audio-player" data-audio-src="${briefing.audio_file}">
                        <audio preload="auto" class="hidden">
                            <source src="${briefing.audio_file}" type="audio/mpeg">
                        </audio>
                        <div class="flex items-center space-x-3 mb-3">
                            <button class="play-pause-btn bg-blue-600 hover:bg-blue-700 text-white rounded-full p-2 w-10 h-10 flex items-center justify-center transition-colors">
                                <i class="fas fa-play text-sm"></i>
                            </button>
                            <div class="flex-1">
                                <div class="progress-container bg-gray-300 rounded-full h-2 cursor-pointer relative">
                                    <div class="progress-bar bg-blue-600 rounded-full h-2 transition-all duration-100" style="width: 0%"></div>
                                    <div class="progress-handle absolute top-1/2 transform -translate-y-1/2 w-4 h-4 bg-blue-600 rounded-full shadow-md cursor-pointer opacity-0 hover:opacity-100 transition-opacity" style="left: 0%"></div>
                                </div>
                            </div>
                            <div class="time-display text-sm text-gray-600 font-mono min-w-max">
                                <span class="current-time">0:00</span> / <span class="duration">0:00</span>
                            </div>
                            <div class="volume-control flex items-center space-x-2">
                                <button class="volume-btn text-gray-600 hover:text-blue-600 transition-colors">
                                    <i class="fas fa-volume-up"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Build text content HTML
        let textHtml = '';
        if (briefing.text_content) {
            textHtml = `
                <div id="text-${briefing.id}" class="hidden mt-4 text-container">
                    <div class="mb-3">
                        <i class="fas fa-file-text text-green-600 mr-2"></i>
                        <span class="text-sm font-medium text-gray-700">Briefing Text</span>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded p-4 text-sm leading-relaxed max-h-96 overflow-y-auto">
                        ${briefing.text_content.replace(/\n/g, '<br>')}
                    </div>
                </div>
            `;
        } else {
            // If no text content, create placeholder
            textHtml = `
                <div id="text-${briefing.id}" class="hidden mt-4 text-container">
                    <div class="mb-3">
                        <i class="fas fa-file-text text-green-600 mr-2"></i>
                        <span class="text-sm font-medium text-gray-700">Briefing Text</span>
                    </div>
                    <div class="bg-gray-50 border border-gray-200 rounded p-4 text-sm leading-relaxed">
                        <p class="text-gray-500">Text content not available for this briefing.</p>
                    </div>
                </div>
            `;
        }
        
        item.innerHTML = `
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-3 gap-3">
                <div class="flex-1">
                    <h3 class="text-base md:text-lg font-semibold text-gray-800">
                        ${date}
                    </h3>
                    <div class="flex items-center text-xs md:text-sm text-gray-600 mt-1">
                        <i class="fas fa-${briefing.audio_file ? 'volume-up' : 'file-text'} mr-2"></i>
                        ${briefing.audio_file ? 'Audio' : 'Text'}
                        ${briefing.duration ? ` | ${briefing.duration} min` : ''}
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    ${briefing.audio_file ? `
                        <button onclick="toggleHistoryAudio('${briefing.id}')" class="bg-green-600 hover:bg-green-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                            <i class="fas fa-play mr-1"></i>Play
                        </button>
                        <a href="${briefing.audio_file}" class="bg-green-700 hover:bg-green-800 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm" download>
                            <i class="fas fa-download mr-1"></i>Download
                        </a>
                    ` : `
                        <button onclick="generateHistoryAudio('${briefing.id}')" class="bg-purple-600 hover:bg-purple-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                            <i class="fas fa-microphone mr-1"></i>Create MP3
                        </button>
                    `}
                    <button onclick="toggleHistoryText('${briefing.id}')" class="bg-blue-600 hover:bg-blue-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                        <i class="fas fa-eye mr-1"></i>Text
                    </button>
                    <button onclick="deleteHistoryItem('${briefing.id}')" class="bg-red-600 hover:bg-red-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                        <i class="fas fa-trash mr-1"></i>Delete
                    </button>
                </div>
            </div>
            
            ${audioHtml}
            ${textHtml}
            ${topicsHtml}
            ${sourcesHtml}
        `;
        
        listDiv.appendChild(item);
    });
    
    // Initialize audio players after DOM is updated
    setTimeout(() => {
        initializeHistoryAudioPlayers();
    }, 100);
}

function updatePagination(pagination) {
    totalHistoryPages = pagination.totalPages;
    currentHistoryPage = pagination.currentPage;
    
    document.getElementById('page-info').textContent = `Page ${currentHistoryPage} of ${totalHistoryPages}`;
    document.getElementById('prev-page').disabled = currentHistoryPage <= 1;
    document.getElementById('next-page').disabled = currentHistoryPage >= totalHistoryPages;
}

function deleteHistoryItem(briefingId) {
    if (confirm('Are you sure you want to delete this briefing?')) {
        fetch('api/history.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete',
                briefing_id: briefingId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadHistoryPage(currentHistoryPage);
            } else {
                alert('Failed to delete briefing');
            }
        })
        .catch(error => {
            console.error('Error deleting briefing:', error);
            alert('Error deleting briefing');
        });
    }
}

function showCleanupModal() {
    const modal = confirm('Choose cleanup option:\nOK = Delete briefings older than 30 days\nCancel = Delete all briefings');
    
    if (modal === true) {
        cleanupHistory(30);
    } else if (modal === false) {
        if (confirm('Are you sure you want to delete ALL briefings? This cannot be undone.')) {
            cleanupHistory(0);
        }
    }
}

function cleanupHistory(days) {
    const action = days > 0 ? 'clear_old' : 'clear_all';
    
    fetch('api/history.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: action,
            days: days
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message || 'Cleanup completed');
            loadHistoryPage(1);
        } else {
            alert('Cleanup failed');
        }
    })
    .catch(error => {
        console.error('Error during cleanup:', error);
        alert('Error during cleanup');
    });
}

// History-specific functions
function toggleHistoryAudio(briefingId) {
    const audioDiv = document.getElementById('audio-' + briefingId);
    if (audioDiv) {
        audioDiv.classList.toggle('hidden');
    }
}

function toggleHistoryText(briefingId) {
    const textDiv = document.getElementById('text-' + briefingId);
    if (textDiv) {
        textDiv.classList.toggle('hidden');
    }
}

function generateHistoryAudio(briefingId) {
    // Implementation for generating audio from existing briefing
    alert('Audio generation for existing briefings will be implemented in the next update.');
}

function initializeHistoryAudioPlayers() {
    const audioPlayers = document.querySelectorAll('.custom-audio-player');
    
    audioPlayers.forEach(player => {
        const audio = player.querySelector('audio');
        const playPauseBtn = player.querySelector('.play-pause-btn');
        const progressContainer = player.querySelector('.progress-container');
        const progressBar = player.querySelector('.progress-bar');
        const progressHandle = player.querySelector('.progress-handle');
        const currentTimeSpan = player.querySelector('.current-time');
        const durationSpan = player.querySelector('.duration');
        const volumeBtn = player.querySelector('.volume-btn');
        
        if (!audio || !playPauseBtn) return;
        
        // Play/Pause functionality
        playPauseBtn.addEventListener('click', () => {
            if (audio.paused) {
                // Pause all other audio players
                document.querySelectorAll('.custom-audio-player audio').forEach(otherAudio => {
                    if (otherAudio !== audio && !otherAudio.paused) {
                        otherAudio.pause();
                        const otherBtn = otherAudio.closest('.custom-audio-player').querySelector('.play-pause-btn i');
                        if (otherBtn) otherBtn.className = 'fas fa-play text-sm';
                    }
                });
                
                audio.play();
                playPauseBtn.querySelector('i').className = 'fas fa-pause text-sm';
            } else {
                audio.pause();
                playPauseBtn.querySelector('i').className = 'fas fa-play text-sm';
            }
        });
        
        // Progress tracking
        audio.addEventListener('timeupdate', () => {
            if (audio.duration) {
                const progress = (audio.currentTime / audio.duration) * 100;
                progressBar.style.width = progress + '%';
                progressHandle.style.left = progress + '%';
                
                if (currentTimeSpan) {
                    currentTimeSpan.textContent = formatTime(audio.currentTime);
                }
            }
        });
        
        // Duration display
        audio.addEventListener('loadedmetadata', () => {
            if (durationSpan) {
                durationSpan.textContent = formatTime(audio.duration);
            }
        });
        
        // Progress seeking
        if (progressContainer) {
            progressContainer.addEventListener('click', (e) => {
                const rect = progressContainer.getBoundingClientRect();
                const clickX = e.clientX - rect.left;
                const progress = clickX / rect.width;
                audio.currentTime = progress * audio.duration;
            });
        }
        
        // Auto-reset when ended
        audio.addEventListener('ended', () => {
            playPauseBtn.querySelector('i').className = 'fas fa-play text-sm';
            progressBar.style.width = '0%';
            progressHandle.style.left = '0%';
            if (currentTimeSpan) {
                currentTimeSpan.textContent = '0:00';
            }
        });
    });
}

function formatTime(seconds) {
    if (isNaN(seconds)) return '0:00';
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = Math.floor(seconds % 60);
    return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
}

// Transition from loading theme to proper dark theme
document.addEventListener('DOMContentLoaded', function() {
    showTab('basic');
    
    const savedTheme = localStorage.getItem('darkTheme');
    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const darkThemeToggle = document.getElementById('darkTheme');
    
    if (savedTheme === 'true' || (savedTheme === null && systemPrefersDark)) {
        document.documentElement.classList.remove('dark-theme-loading');
        document.body.classList.add('dark-theme');
        if (darkThemeToggle) {
            darkThemeToggle.checked = true;
        }
    }
    
    // Load custom categories first, then existing RSS feeds
    loadCustomCategories();
    loadExistingRssFeeds();
});

function loadExistingRssFeeds() {
    // This will be populated from PHP when feeds exist
    const existingFeeds = <?php echo json_encode(getRssFeeds()); ?>;
    
    if (existingFeeds && existingFeeds.length > 0) {
        // Add the feeds (existing feeds start in compact view)
        existingFeeds.forEach(feed => {
            addRssFeed(feed.url, feed.name, feed.category, false); // false = not new feed, show in compact view
        });
    }
}

// Scheduling functions
function showNewScheduleModal() {
    const modal = document.getElementById('schedule-modal');
    if (modal) {
        loadAvailableCategories();
        modal.classList.remove('hidden');
    }
}

function loadAvailableCategories() {
    // Load user settings to get all available categories
    return fetch('settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=get_categories'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            populateScheduleCategories(data.categories);
            return data.categories;
        }
        throw new Error('Failed to load categories');
    })
    .catch(error => {
        console.error('Error loading categories:', error);
        // Fallback to default categories
        const defaultCategories = [
            { value: 'general', label: 'General' },
            { value: 'business', label: 'Business' },
            { value: 'technology', label: 'Technology' },
            { value: 'science', label: 'Science' },
            { value: 'health', label: 'Health' },
            { value: 'entertainment', label: 'Entertainment' },
            { value: 'sports', label: 'Sports' }
        ];
        populateScheduleCategories(defaultCategories);
        return defaultCategories;
    });
}

function populateScheduleCategories(categories) {
    const container = document.getElementById('schedule-categories-container');
    if (!container) return;
    
    container.innerHTML = '';
    
    categories.forEach(category => {
        const label = document.createElement('label');
        label.className = 'flex items-center';
        
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'scheduleCategories';
        checkbox.value = category.value;
        checkbox.className = 'mr-2';
        
        const span = document.createElement('span');
        span.className = 'text-sm';
        span.textContent = category.label;
        
        label.appendChild(checkbox);
        label.appendChild(span);
        container.appendChild(label);
    });
}

function hideScheduleModal() {
    const modal = document.getElementById('schedule-modal');
    if (modal) {
        modal.classList.add('hidden');
        document.getElementById('schedule-form').reset();
        document.getElementById('schedule-id').value = '';
        document.querySelector('#schedule-modal h3').textContent = 'Create New Schedule';
    }
}

function saveSchedule() {
    const form = document.getElementById('schedule-form');
    const formData = new FormData(form);
    const isEdit = document.getElementById('schedule-id').value;
    
    const scheduleData = {
        action: isEdit ? 'update_schedule' : 'create_schedule',
        id: isEdit || undefined,
        name: formData.get('scheduleName'),
        time: formData.get('scheduleTime'),
        days: formData.getAll('scheduleDays'),
        active: formData.get('scheduleActive') === 'on',
        settings: {
            generateMp3: formData.get('scheduleGenerateMp3') === 'on',
            includeWeather: formData.get('scheduleIncludeWeather') === 'on',
            includeLocal: formData.get('scheduleIncludeLocal') === 'on',
            includeTV: formData.get('scheduleIncludeTV') === 'on',
            zipCode: formData.get('scheduleZipCode'),
            timeFrame: formData.get('scheduleTimeFrame'),
            audioLength: formData.get('scheduleAudioLength'),
            aiSelection: formData.get('scheduleAiSelection'),
            customHeader: formData.get('scheduleCustomHeader'),
            categories: formData.getAll('scheduleCategories')
        }
    };
    
    fetch('api/scheduling.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(scheduleData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideScheduleModal();
            schedulesCache = null; // Clear cache
            loadSchedules(true); // Force refresh
        } else {
            alert('Failed to save schedule: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error creating schedule:', error);
        alert('Error creating schedule');
    });
}

let schedulesCache = null;
let loadingSchedules = false;

function loadSchedules(forceRefresh = false) {
    // Use cache if available and not forcing refresh
    if (!forceRefresh && schedulesCache !== null) {
        displaySchedules(schedulesCache);
        return;
    }
    
    // Prevent multiple simultaneous requests
    if (loadingSchedules) return;
    loadingSchedules = true;
    
    document.getElementById('schedules-loading').classList.remove('hidden');
    document.getElementById('schedules-list').classList.add('hidden');
    document.getElementById('schedules-empty').classList.add('hidden');
    
    // Add timeout to prevent hanging
    const fetchPromise = fetch('api/scheduling.php');
    const timeoutPromise = new Promise((_, reject) => 
        setTimeout(() => reject(new Error('Request timeout')), 10000)
    );
    
    Promise.race([fetchPromise, timeoutPromise])
    .then(response => {
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    })
    .then(data => {
        loadingSchedules = false;
        document.getElementById('schedules-loading').classList.add('hidden');
        
        if (data.success && data.schedules && data.schedules.length > 0) {
            schedulesCache = data.schedules;
            displaySchedules(data.schedules);
        } else {
            document.getElementById('schedules-empty').classList.remove('hidden');
        }
    })
    .catch(error => {
        loadingSchedules = false;
        console.error('Error loading schedules:', error);
        document.getElementById('schedules-loading').classList.add('hidden');
        document.getElementById('schedules-empty').classList.remove('hidden');
        
        // Show user-friendly error
        const emptyDiv = document.getElementById('schedules-empty');
        emptyDiv.innerHTML = '<div class="text-center py-8"><p class="text-red-600">Failed to load schedules. <button onclick="loadSchedules(true)" class="text-blue-600 underline">Try again</button></p></div>';
    });
}

function displaySchedules(schedules) {
    const container = document.getElementById('schedules-list');
    container.innerHTML = '';
    
    schedules.forEach(schedule => {
        const scheduleCard = document.createElement('div');
        scheduleCard.className = 'bg-white border border-gray-200 rounded-lg p-4 shadow-sm';
        
        const daysText = schedule.days.length === 7 ? 'Daily' : schedule.days.join(', ');
        const statusColor = schedule.active ? 'text-green-600' : 'text-gray-500';
        const statusText = schedule.active ? 'Active' : 'Inactive';
        
        scheduleCard.innerHTML = `
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h4 class="font-medium text-gray-900">${schedule.name}</h4>
                    <p class="text-sm text-gray-600">
                        <i class="fas fa-clock mr-1"></i>${schedule.time} - ${daysText}
                    </p>
                </div>
                <div class="flex items-center space-x-2">
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 ${statusColor}">${statusText}</span>
                    <button onclick="editSchedule('${schedule.id}')" class="text-blue-400 hover:text-blue-600">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button onclick="toggleSchedule('${schedule.id}')" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-power-off"></i>
                    </button>
                    <button onclick="deleteSchedule('${schedule.id}')" class="text-red-400 hover:text-red-600">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="text-xs text-gray-500">
                <span class="mr-3">
                    <i class="fas fa-file-${schedule.settings.generateMp3 ? 'audio' : 'text'} mr-1"></i>
                    ${schedule.settings.generateMp3 ? 'Audio' : 'Text'}
                </span>
                ${schedule.settings.includeWeather ? '<span class="mr-3"><i class="fas fa-cloud mr-1"></i>Weather</span>' : ''}
                ${schedule.settings.includeLocal ? '<span class="mr-3"><i class="fas fa-map-marker-alt mr-1"></i>Local</span>' : ''}
                ${schedule.settings.includeTV ? '<span class="mr-3"><i class="fas fa-tv mr-1"></i>TV/Movies</span>' : ''}
                ${(schedule.settings.categories || []).map(cat => `<span class="mr-3"><i class="fas fa-tag mr-1"></i>${cat.charAt(0).toUpperCase() + cat.slice(1)}</span>`).join('')}
            </div>
        `;
        
        container.appendChild(scheduleCard);
    });
    
    container.classList.remove('hidden');
}

function toggleSchedule(scheduleId) {
    fetch('api/scheduling.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'toggle_schedule',
            id: scheduleId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadSchedules();
        } else {
            alert('Failed to toggle schedule');
        }
    })
    .catch(error => {
        console.error('Error toggling schedule:', error);
        alert('Error toggling schedule');
    });
}

function deleteSchedule(scheduleId) {
    if (confirm('Are you sure you want to delete this schedule?')) {
        fetch('api/scheduling.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'delete_schedule',
                id: scheduleId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                schedulesCache = null; // Clear cache
                loadSchedules(true); // Force refresh
            } else {
                alert('Failed to delete schedule');
            }
        })
        .catch(error => {
            console.error('Error deleting schedule:', error);
            alert('Error deleting schedule');
        });
    }
}

function editSchedule(scheduleId) {
    fetch('api/scheduling.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.schedules) {
            const schedule = data.schedules.find(s => s.id === scheduleId);
            if (schedule) {
                // Show modal first with loading state
                showNewScheduleModal();
                
                // Load categories first, then populate form
                loadAvailableCategories().then(() => {
                    // Wait for categories to be fully rendered
                    setTimeout(() => {
                        populateScheduleForm(schedule);
                    }, 200);
                });
            }
        }
    })
    .catch(error => {
        console.error('Error loading schedule:', error);
        alert('Error loading schedule for editing');
    });
}

function populateScheduleForm(schedule) {
    // Wait for categories to be fully rendered
    const categoriesContainer = document.getElementById('schedule-categories-container');
    if (!categoriesContainer || categoriesContainer.children.length === 0) {
        // Categories not ready yet, retry in a moment
        setTimeout(() => populateScheduleForm(schedule), 100);
        return;
    }
    
    // Set schedule ID for edit mode
    document.getElementById('schedule-id').value = schedule.id;
    
    // Update modal title
    document.querySelector('#schedule-modal h3').textContent = 'Edit Schedule';
    
    // Populate basic fields
    document.querySelector('input[name="scheduleName"]').value = schedule.name;
    document.querySelector('input[name="scheduleTime"]').value = schedule.time;
    
    // Clear and set days
    document.querySelectorAll('input[name="scheduleDays"]').forEach(checkbox => {
        checkbox.checked = schedule.days.includes(checkbox.value);
    });
    
    // Set settings
    const settings = schedule.settings || {};
    document.querySelector('input[name="scheduleGenerateMp3"]').checked = settings.generateMp3 || false;
    document.querySelector('input[name="scheduleIncludeWeather"]').checked = settings.includeWeather || false;
    document.querySelector('input[name="scheduleIncludeLocal"]').checked = settings.includeLocal || false;
    document.querySelector('input[name="scheduleIncludeTV"]').checked = settings.includeTV || false;
    
    // Set other fields
    document.querySelector('input[name="scheduleZipCode"]').value = settings.zipCode || '';
    document.querySelector('select[name="scheduleTimeFrame"]').value = settings.timeFrame || 'auto';
    document.querySelector('select[name="scheduleAudioLength"]').value = settings.audioLength || '5-10';
    document.querySelector('select[name="scheduleAiSelection"]').value = settings.aiSelection || 'gemini';
    document.querySelector('input[name="scheduleCustomHeader"]').value = settings.customHeader || '';
    
    // Set categories - handle case sensitivity and ensure categories are loaded
    const categoryCheckboxes = document.querySelectorAll('input[name="scheduleCategories"]');
    if (categoryCheckboxes.length > 0) {
        categoryCheckboxes.forEach(checkbox => {
            const categories = settings.categories || [];
            const isChecked = categories.some(cat => cat.toLowerCase() === checkbox.value.toLowerCase());
            checkbox.checked = isChecked;
            console.log(`Category ${checkbox.value}: ${isChecked ? 'checked' : 'unchecked'}`);
        });
    }
    
    // Set active status
    document.querySelector('input[name="scheduleActive"]').checked = schedule.active !== false;
}

function toggleTtsOptions() {
    const ttsProvider = document.querySelector('select[name="ttsProvider"]').value;
    const googleOptions = document.getElementById('google-voice-options');
    const chatterboxOptions = document.getElementById('chatterbox-options');
    
    if (ttsProvider === 'google') {
        googleOptions.style.display = 'block';
        chatterboxOptions.style.display = 'none';
    } else if (ttsProvider === 'chatterbox') {
        googleOptions.style.display = 'none';
        chatterboxOptions.style.display = 'block';
    } else {
        googleOptions.style.display = 'none';
        chatterboxOptions.style.display = 'none';
    }
}

async function testChatterboxConnection() {
    const serverUrl = document.querySelector('input[name="chatterboxServerUrl"]').value;
    const resultDiv = document.getElementById('chatterbox-test-result');
    const button = event.target;
    
    button.disabled = true;
    button.textContent = 'Testing...';
    resultDiv.innerHTML = '<div class="text-blue-600">Testing connection...</div>';
    
    try {
        const response = await fetch('api/test_chatterbox.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ server_url: serverUrl })
        });
        
        const result = await response.json();
        
        if (result.success) {
            resultDiv.innerHTML = `
                <div class="text-green-600 font-medium">${result.message}</div>
                <div class="text-green-700 mt-1">${result.details}</div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="text-red-600 font-medium">${result.message}</div>
                <div class="text-red-700 mt-1">${result.details || result.error}</div>
            `;
        }
    } catch (error) {
        resultDiv.innerHTML = `
            <div class="text-red-600 font-medium">Connection failed</div>
            <div class="text-red-700 mt-1">Error: ${error.message}</div>
        `;
    } finally {
        button.disabled = false;
        button.textContent = 'Test Connection';
    }
}
</script>

<!-- Schedule Creation Modal -->
<div id="schedule-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Create New Schedule</h3>
                <button onclick="hideScheduleModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form id="schedule-form" class="space-y-4">
                <input type="hidden" id="schedule-id" name="scheduleId" value="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Schedule Name</label>
                        <input type="text" name="scheduleName" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Morning News Brief">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                        <input type="time" name="scheduleTime" required class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Days of Week</label>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Monday" class="mr-2">
                            <span class="text-sm">Monday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Tuesday" class="mr-2">
                            <span class="text-sm">Tuesday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Wednesday" class="mr-2">
                            <span class="text-sm">Wednesday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Thursday" class="mr-2">
                            <span class="text-sm">Thursday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Friday" class="mr-2">
                            <span class="text-sm">Friday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Saturday" class="mr-2">
                            <span class="text-sm">Saturday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleDays" value="Sunday" class="mr-2">
                            <span class="text-sm">Sunday</span>
                        </label>
                    </div>
                </div>
                
                <div class="border-t pt-4">
                    <h4 class="text-md font-medium text-gray-800 mb-3">Briefing Settings</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="scheduleGenerateMp3" class="mr-2">
                                <span class="text-sm">Generate Audio (MP3)</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="scheduleIncludeWeather" class="mr-2">
                                <span class="text-sm">Include Weather</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="scheduleIncludeLocal" class="mr-2">
                                <span class="text-sm">Include Local News</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" name="scheduleIncludeTV" class="mr-2">
                                <span class="text-sm">Include TV/Movie News</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">News Categories</label>
                        <div id="schedule-categories-container" class="grid grid-cols-2 md:grid-cols-3 gap-3">
                            <!-- Categories will be populated dynamically -->
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Zip Code</label>
                            <input type="text" name="scheduleZipCode" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="12345">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Time Frame</label>
                            <select name="scheduleTimeFrame" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="auto">Auto-detect</option>
                                <option value="morning">Morning (5 AM - 11 AM)</option>
                                <option value="afternoon">Afternoon (12 PM - 5 PM)</option>
                                <option value="evening">Evening (6 PM - 11 PM)</option>
                                <option value="night">Night (12 AM - 4 AM)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Audio Length</label>
                            <select name="scheduleAudioLength" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <option value="2-3">2-3 minutes</option>
                                <option value="5-10">5-10 minutes</option>
                                <option value="10-15">10-15 minutes</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">AI Service</label>
                            <select name="scheduleAiSelection" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                <?php if ($settings['openaiEnabled'] ?? false): ?>
                                <option value="openai">OpenAI (GPT)</option>
                                <?php endif; ?>
                                <?php if ($settings['geminiEnabled'] ?? false): ?>
                                <option value="gemini">Google Gemini</option>
                                <?php endif; ?>
                                <?php if ($settings['claudeEnabled'] ?? false): ?>
                                <option value="claude">Claude</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Custom Header (Optional)</label>
                        <input type="text" name="scheduleCustomHeader" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Good morning! Here's your personalized news briefing...">
                    </div>
                    
                    <div class="mt-4">
                        <label class="flex items-center">
                            <input type="checkbox" name="scheduleActive" checked class="mr-2">
                            <span class="text-sm">Active (start running this schedule immediately)</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="hideScheduleModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 hover:bg-gray-300 rounded-md">
                        Cancel
                    </button>
                    <button type="button" onclick="saveSchedule()" class="px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-md">
                        <i class="fas fa-save mr-2"></i>Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>
