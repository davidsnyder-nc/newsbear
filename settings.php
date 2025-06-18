<?php
$settingsFile = 'config/user_settings.json';

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
    'googleTtsEnabled' => true
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - NewsBear</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold text-gray-800">
                    <i class="fas fa-cog mr-2"></i>Settings
                </h1>
                <button type="button" onclick="saveAndGoHome()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
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
            
            <form method="POST" class="space-y-8">
                <!-- Settings Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-8">
                    <!-- Left Column -->
                    <div class="space-y-6 md:space-y-8">
                        <!-- Basic Settings -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Basic Settings</h3>
                            
                            <div class="space-y-4">
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
                                
                                <div class="space-y-3">
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
                                        <input type="checkbox" name="generateMp3" <?= isChecked('generateMp3') ?> class="mr-3 h-4 w-4">
                                        Generate MP3 Audio File
                                    </label>
                                    <label class="flex items-center text-sm">
                                        <input type="checkbox" name="darkTheme" <?= isChecked('darkTheme') ?> class="mr-3 h-4 w-4">
                                        Dark Theme
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- News Categories -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-800 border-b pb-2">News Categories</h3>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-1 md:gap-2">
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
                    
                    <!-- Right Column -->
                    <div class="space-y-6 md:space-y-8">
                        <!-- API Keys -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-medium text-gray-800 border-b pb-2">API Keys</h3>
                            
                            <div class="space-y-4">
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
                                        <input type="checkbox" name="guardianEnabled" <?= isChecked('guardianEnabled') ?> class="mr-3 h-4 w-4">
                                        Guardian API
                                    </label>
                                    <input type="password" name="guardianApiKey" value="<?= getValue('guardianApiKey') ?>" placeholder="Guardian API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                                    <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://open-platform.theguardian.com/access/" target="_blank" class="text-blue-600 hover:underline">theguardian.com</a></p>
                                </div>
                    
                    <div>
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="nytEnabled" <?= isChecked('nytEnabled') ?> class="mr-2">
                            NY Times API
                        </label>
                        <input type="password" name="nytApiKey" value="<?= getValue('nytApiKey') ?>" placeholder="NY Times API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://developer.nytimes.com/get-started" target="_blank" class="text-blue-600 hover:underline">developer.nytimes.com</a></p>
                    </div>
                    
                    <div>
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="geminiEnabled" <?= isChecked('geminiEnabled') ?> class="mr-2">
                            Gemini API
                        </label>
                        <input type="password" name="geminiApiKey" value="<?= getValue('geminiApiKey') ?>" placeholder="Gemini API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Get your free API key at <a href="https://makersuite.google.com/app/apikey" target="_blank" class="text-blue-600 hover:underline">Google AI Studio</a></p>
                    </div>
                </div>
                
                <!-- AI Service Selection -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-800 border-b pb-2">AI Service Configuration</h3>
                    
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
                    
                    <div>
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="openaiEnabled" <?= isChecked('openaiEnabled') ?> class="mr-2">
                            OpenAI API
                        </label>
                        <input type="password" name="openaiApiKey" value="<?= getValue('openaiApiKey') ?>" placeholder="OpenAI API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Get your API key at <a href="https://platform.openai.com/api-keys" target="_blank" class="text-blue-600 hover:underline">platform.openai.com</a></p>
                    </div>
                    
                    <div>
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="claudeEnabled" <?= isChecked('claudeEnabled') ?> class="mr-2">
                            Claude API
                        </label>
                        <input type="password" name="claudeApiKey" value="<?= getValue('claudeApiKey') ?>" placeholder="Claude API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Get your API key at <a href="https://console.anthropic.com/" target="_blank" class="text-blue-600 hover:underline">console.anthropic.com</a></p>
                    </div>
                    
                    <div>
                        <label class="flex items-center mb-2">
                            <input type="checkbox" name="googleTtsEnabled" <?= isChecked('googleTtsEnabled') ?> class="mr-2">
                            Google Text-to-Speech
                        </label>
                        <input type="password" name="googleTtsApiKey" value="<?= getValue('googleTtsApiKey') ?>" placeholder="Google TTS API Key" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1">Get your API key at <a href="https://console.cloud.google.com/" target="_blank" class="text-blue-600 hover:underline">Google Cloud Console</a></p>
                    </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Settings - Full Width -->
                <div class="space-y-4">
                    <h3 class="text-lg font-medium text-gray-800 border-b pb-2">Advanced Settings</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Custom Header</label>
                            <input type="text" name="customHeader" value="<?= getValue('customHeader') ?>" placeholder="Custom briefing header" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <p class="text-xs text-gray-500 mt-1">Custom text to include at the start of each briefing</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Blocked Terms</label>
                            <textarea name="blockedTerms" rows="3" placeholder="Enter terms to exclude from news (comma-separated)" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('blockedTerms') ?></textarea>
                            <p class="text-xs text-gray-500 mt-1">Articles containing these terms will be filtered out</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">OpenAI Custom Prompt</label>
                            <textarea name="openaiPrompt" rows="2" placeholder="Custom prompt for OpenAI" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('openaiPrompt') ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Gemini Custom Prompt</label>
                            <textarea name="geminiPrompt" rows="2" placeholder="Custom prompt for Gemini" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('geminiPrompt') ?></textarea>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Claude Custom Prompt</label>
                            <textarea name="claudePrompt" rows="2" placeholder="Custom prompt for Claude" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm"><?= getValue('claudePrompt') ?></textarea>
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
function saveAndGoHome() {
    // Submit the form to save settings
    document.querySelector('form').submit();
}
</script>
</body>
</html>