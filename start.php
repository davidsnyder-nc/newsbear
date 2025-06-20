<?php
/**
 * NewsBear Server Launcher
 * Simple script to start the server on any available port
 */

$port = $argv[1] ?? 5000;
$host = $argv[2] ?? '0.0.0.0';

// Ensure required directories exist
$dirs = ['data', 'data/history', 'data/cache', 'downloads', 'config'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Created directory: $dir\n";
    }
}

// Create default config if it doesn't exist
$configFile = 'config/user_settings.json';
if (!file_exists($configFile)) {
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
    echo "Created default configuration: $configFile\n";
}

echo "Starting NewsBear server...\n";
echo "Host: $host\n";
echo "Port: $port\n";
echo "URL: http://" . ($host === '0.0.0.0' ? 'localhost' : $host) . ":$port\n";
echo "\nPress Ctrl+C to stop the server\n";
echo "Go to Settings to configure your API keys\n\n";

// Start the server
$command = "php -S $host:$port -t .";
passthru($command);