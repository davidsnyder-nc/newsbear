<?php
/**
 * NewsBear Auto-Installer
 * One-command setup for complete NewsBear installation
 */

echo "🐻 NewsBear Auto-Installer\n";
echo "========================\n\n";

// Check PHP version
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    echo "❌ Error: PHP 8.0+ required. Current version: " . PHP_VERSION . "\n";
    echo "Please upgrade PHP and try again.\n";
    exit(1);
}

echo "✅ PHP " . PHP_VERSION . " detected\n";

// Check required PHP extensions
$requiredExtensions = ['curl', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    echo "❌ Missing PHP extensions: " . implode(', ', $missingExtensions) . "\n";
    echo "Install with: sudo apt-get install php-" . implode(' php-', $missingExtensions) . "\n";
    exit(1);
}

echo "✅ Required PHP extensions available\n";

// Create directory structure
$directories = [
    'data',
    'data/history',
    'data/cache',
    'data/schedules',
    'downloads',
    'config'
];

echo "\n📁 Creating directory structure...\n";
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "   Created: $dir\n";
    } else {
        echo "   Exists: $dir\n";
    }
}

// Set proper permissions
echo "\n🔒 Setting permissions...\n";
foreach ($directories as $dir) {
    chmod($dir, 0755);
    echo "   Set 755: $dir\n";
}

// Create default configuration
$configFile = 'config/user_settings.json';
if (!file_exists($configFile)) {
    echo "\n⚙️  Creating default configuration...\n";
    
    $defaultConfig = [
        'generateMp3' => false,
        'includeWeather' => true,
        'includeLocal' => false,
        'includeTV' => true,
        'zipCode' => '10001',
        'timeFrame' => 'auto',
        'audioLength' => '3-5',
        'darkTheme' => false,
        'customHeader' => '',
        'aiSelection' => 'gemini',
        'aiGeneration' => 'gemini',
        'blockedTerms' => '',
        'preferredTerms' => '',
        'categories' => [],
        'debugMode' => false,
        'verboseLogging' => false,
        'showLogWindow' => false,
        'authEnabled' => false,
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
        'chatterboxSampleFile' => '',
        'gnewsEnabled' => true,
        'newsApiEnabled' => true,
        'guardianEnabled' => true,
        'nytEnabled' => true,
        'weatherEnabled' => true,
        'tmdbEnabled' => true,
        'openaiEnabled' => false,
        'geminiEnabled' => true,
        'claudeEnabled' => false,
        'googleTtsEnabled' => true,
        'lastUpdated' => date('c')
    ];
    
    file_put_contents($configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    echo "   Created: $configFile\n";
} else {
    echo "\n⚙️  Configuration file already exists\n";
}

// Create default RSS feeds
$rssFile = 'data/rss_feeds.json';
if (!file_exists($rssFile)) {
    echo "\n📰 Setting up default RSS feeds...\n";
    
    $defaultFeeds = [
        [
            'url' => 'https://www.theverge.com/rss/index.xml',
            'name' => 'The Verge',
            'category' => 'Technology'
        ],
        [
            'url' => 'https://feeds.arstechnica.com/arstechnica/index',
            'name' => 'Ars Technica',
            'category' => 'Technology'
        ],
        [
            'url' => 'https://kotaku.com/rss',
            'name' => 'Kotaku',
            'category' => 'Gaming'
        ]
    ];
    
    file_put_contents($rssFile, json_encode($defaultFeeds, JSON_PRETTY_PRINT));
    echo "   Created: $rssFile\n";
}

// Initialize TTS queue
$queueFile = 'data/tts_queue.json';
if (!file_exists($queueFile)) {
    file_put_contents($queueFile, '[]');
    echo "   Created: $queueFile\n";
}

// Initialize API status
$statusFile = 'data/api_status.json';
if (!file_exists($statusFile)) {
    file_put_contents($statusFile, '{}');
    echo "   Created: $statusFile\n";
}

// Check for database connectivity
echo "\n🗄️  Checking database connectivity...\n";
$databaseUrl = getenv('DATABASE_URL');
if ($databaseUrl) {
    echo "   PostgreSQL available: " . substr($databaseUrl, 0, 20) . "...\n";
} else {
    echo "   Using file-based storage (no database required)\n";
}

// Test internet connectivity
echo "\n🌐 Testing internet connectivity...\n";
$testUrls = [
    'https://api.gnews.io' => 'GNews API',
    'https://newsapi.org' => 'NewsAPI',
    'https://content.guardianapis.com' => 'Guardian API'
];

foreach ($testUrls as $url => $name) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 200 && $httpCode < 400) {
        echo "   ✅ $name reachable\n";
    } else {
        echo "   ⚠️  $name not reachable (check internet connection)\n";
    }
}

// Installation complete
echo "\n🎉 Installation Complete!\n";
echo "========================\n\n";

echo "Next steps:\n";
echo "1. Start the server: php start.php\n";
echo "2. Open browser to displayed URL\n";
echo "3. Go to Settings and configure API keys\n";
echo "4. Generate your first briefing\n\n";

echo "Optional:\n";
echo "• Set up Chatterbox TTS for local voice generation\n";
echo "• Configure scheduled briefings\n";
echo "• Add custom RSS feeds\n\n";

echo "For help: php start.php --help\n";
echo "Documentation: README.md and DEPLOYMENT.md\n\n";

echo "🐻 NewsBear is ready to serve your news briefings!\n";