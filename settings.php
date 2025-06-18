<?php
$settingsFile = 'config/user_settings.json';

// RSS Feed Processing Functions
function processRssFeeds($rssFeeds) {
    $processedFeeds = [];
    
    if (!is_array($rssFeeds)) {
        return $processedFeeds;
    }
    
    foreach ($rssFeeds as $feed) {
        if (!empty($feed['url']) && !empty($feed['name']) && !empty($feed['category'])) {
            $processedFeed = [
                'url' => filter_var($feed['url'], FILTER_SANITIZE_URL),
                'name' => htmlspecialchars($feed['name'], ENT_QUOTES, 'UTF-8'),
                'category' => $feed['category']
            ];
            
            // Handle custom category
            if ($feed['category'] === 'custom' && !empty($feed['customCategory'])) {
                $processedFeed['customCategory'] = htmlspecialchars($feed['customCategory'], ENT_QUOTES, 'UTF-8');
            }
            
            $processedFeeds[] = $processedFeed;
        }
    }
    
    return $processedFeeds;
}

function getRssFeeds() {
    require_once 'includes/RSSFeedHandler.php';
    $rssHandler = new RSSFeedHandler();
    return $rssHandler->getRssFeeds();
}

// Handle form submission
if ($_POST) {
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
        'categories' => $_POST['categories'] ?? ['general'],
        'debugMode' => isset($_POST['debugMode']) ? true : false,
        'verboseLogging' => isset($_POST['verboseLogging']) ? true : false,
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
        'voiceSelection' => $_POST['voiceSelection'] ?? 'en-US-Neural2-D',
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
        'rssFeeds' => processRssFeeds($_POST['rssFeeds'] ?? []),
        'lastUpdated' => date('c')
    ];
    
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    header('Location: index.php?saved=1');
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
    'categories' => ['general', 'technology', 'science', 'health', 'entertainment'],
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
    'voiceSelection' => 'en-US-Neural2-D',
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
    'verboseLogging' => false
];

$settings = array_merge($defaults, $settings);

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

function getRssCustomCategories() {
    try {
        require_once 'includes/RSSFeedHandler.php';
        $rssHandler = new RSSFeedHandler();
        return $rssHandler->getCustomCategories();
    } catch (Exception $e) {
        return [];
    }
}
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
                <button type="button" onclick="saveAndGoHome()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm sm:text-base">
                    <i class="fas fa-save mr-2"></i>Save and Back to Home
                </button>
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
                        <option value="advanced">⚙️ Advanced</option>
                    </select>
                </div>
                
                <!-- Desktop tabs with icons only -->
                <nav class="hidden sm:flex -mb-px justify-center" role="tablist">
                    <div class="flex space-x-6">
                        <button type="button" onclick="showTab('basic')" id="basic-tab" class="py-3 px-3 border-b-2 border-blue-500 font-medium text-blue-600 hover:text-blue-700" role="tab" title="Basic Settings">
                            <i class="fas fa-cog text-lg"></i>
                        </button>
                        <button type="button" onclick="showTab('content')" id="content-tab" class="py-3 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" role="tab" title="Content & Categories">
                            <i class="fas fa-newspaper text-lg"></i>
                        </button>
                        <button type="button" onclick="showTab('rss')" id="rss-tab" class="py-3 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" role="tab" title="RSS Feeds">
                            <i class="fas fa-rss text-lg"></i>
                        </button>
                        <button type="button" onclick="showTab('api')" id="api-tab" class="py-3 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" role="tab" title="API Keys">
                            <i class="fas fa-key text-lg"></i>
                        </button>
                        <button type="button" onclick="showTab('ai')" id="ai-tab" class="py-3 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" role="tab" title="AI Services">
                            <i class="fas fa-robot text-lg"></i>
                        </button>
                        <button type="button" onclick="showTab('advanced')" id="advanced-tab" class="py-3 px-3 border-b-2 border-transparent font-medium text-gray-500 hover:text-gray-700 hover:border-gray-300" role="tab" title="Advanced">
                            <i class="fas fa-sliders-h text-lg"></i>
                        </button>
                    </div>
                </nav>

            </div>

            <form method="POST" class="space-y-8">
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
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Voice Selection</label>
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
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="space-y-4">
                                <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Content Filters</h3>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Blocked Terms</label>
                                    <textarea name="blockedTerms" rows="4" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" placeholder="Enter terms to block, separated by commas"><?= getValue('blockedTerms') ?></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Articles containing these terms will be excluded</p>
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
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-800">RSS Feeds</h3>
                            <button type="button" onclick="addRssFeed()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm px-4 py-2 rounded-md">
                                <i class="fas fa-plus mr-2"></i>Add RSS Feed
                            </button>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <p class="text-sm text-blue-800">
                                <i class="fas fa-info-circle mr-2"></i>
                                RSS feeds will be treated like news API sources. Each feed can be assigned to existing categories or create new ones.
                            </p>
                        </div>
                        
                        <div id="rss-feeds-container" class="space-y-4">
                            <!-- RSS feeds will be dynamically added here -->
                        </div>
                        
                        <div class="text-center text-gray-500 text-sm" id="no-rss-message">
                            No RSS feeds configured. Click "Add RSS Feed" to get started.
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
                                        <p class="text-xs text-gray-500 ml-7">Logs detailed API calls and processing steps</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="text-center">
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-6 rounded-md">
                        <i class="fas fa-save mr-2"></i>Save All Settings
                    </button>
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
}

function saveAndGoHome() {
    // Submit the form to save settings
    document.querySelector('form').submit();
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

function addRssFeed(url = '', name = '', category = '') {
    const container = document.getElementById('rss-feeds-container');
    const noMessage = document.getElementById('no-rss-message');
    
    rssFeedCounter++;
    const feedId = 'rss_feed_' + rssFeedCounter;
    
    const feedHtml = `
        <div class="border border-gray-200 rounded-lg p-4 bg-white" id="${feedId}">
            <div class="flex justify-between items-start mb-4">
                <h4 class="font-medium text-gray-800">RSS Feed ${rssFeedCounter}</h4>
                <button type="button" onclick="removeRssFeed('${feedId}')" class="text-red-600 hover:text-red-800">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Feed URL</label>
                    <input type="url" name="rssFeeds[${rssFeedCounter}][url]" value="${url}" placeholder="https://example.com/feed.xml" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Display Name</label>
                    <input type="text" name="rssFeeds[${rssFeedCounter}][name]" value="${name}" placeholder="Source Name" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                    <select name="rssFeeds[${rssFeedCounter}][category]" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <option value="general" ${category === 'general' ? 'selected' : ''}>General</option>
                        <option value="business" ${category === 'business' ? 'selected' : ''}>Business</option>
                        <option value="entertainment" ${category === 'entertainment' ? 'selected' : ''}>Entertainment</option>
                        <option value="health" ${category === 'health' ? 'selected' : ''}>Health</option>
                        <option value="science" ${category === 'science' ? 'selected' : ''}>Science</option>
                        <option value="sports" ${category === 'sports' ? 'selected' : ''}>Sports</option>
                        <option value="technology" ${category === 'technology' ? 'selected' : ''}>Technology</option>
                        <option value="custom" ${category === 'custom' ? 'selected' : ''}>Custom Category</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 hidden" id="custom-category-${rssFeedCounter}">
                <label class="block text-sm font-medium text-gray-700 mb-2">Custom Category Name</label>
                <input type="text" name="rssFeeds[${rssFeedCounter}][customCategory]" placeholder="Enter custom category name" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
            </div>
        </div>
    `;
    
    container.insertAdjacentHTML('beforeend', feedHtml);
    noMessage.style.display = 'none';
    
    // Add event listener for custom category toggle
    const categorySelect = document.querySelector(`select[name="rssFeeds[${rssFeedCounter}][category]"]`);
    categorySelect.addEventListener('change', function() {
        const customDiv = document.getElementById(`custom-category-${rssFeedCounter}`);
        if (this.value === 'custom') {
            customDiv.classList.remove('hidden');
        } else {
            customDiv.classList.add('hidden');
        }
    });
}

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
    
    // Load existing RSS feeds
    loadExistingRssFeeds();
});

function loadExistingRssFeeds() {
    // This will be populated from PHP when feeds exist
    const existingFeeds = <?php echo json_encode(getRssFeeds()); ?>;
    
    if (existingFeeds && existingFeeds.length > 0) {
        existingFeeds.forEach(feed => {
            addRssFeed(feed.url, feed.name, feed.category);
            
            // Set custom category if applicable
            if (feed.category === 'custom' && feed.customCategory) {
                const customInput = document.querySelector(`input[name="rssFeeds[${rssFeedCounter}][customCategory]"]`);
                if (customInput) {
                    customInput.value = feed.customCategory;
                    document.getElementById(`custom-category-${rssFeedCounter}`).classList.remove('hidden');
                }
            }
        });
    }
}
</script>
</body>
</html>