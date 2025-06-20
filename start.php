<?php
/**
 * NewsBear Server Launcher
 * Simple script to start the server on any available port
 */

// Parse arguments
$port = 5000;
$host = '0.0.0.0';

foreach ($argv as $i => $arg) {
    if ($i === 0) continue; // Skip script name
    
    if ($arg === '--help' || $arg === '-h') {
        echo "NewsBear Server Launcher\n";
        echo "Usage: php start.php [port] [host]\n";
        echo "       php start.php --port 8080 --host localhost\n\n";
        echo "Examples:\n";
        echo "  php start.php                    # Default: port 5000, all interfaces\n";
        echo "  php start.php 8080               # Port 8080, all interfaces\n";
        echo "  php start.php 3000 localhost     # Port 3000, localhost only\n";
        echo "  php start.php --port 9000        # Port 9000 with named argument\n";
        exit(0);
    }
    
    if ($arg === '--port' && isset($argv[$i + 1])) {
        $port = (int)$argv[$i + 1];
        continue;
    }
    
    if ($arg === '--host' && isset($argv[$i + 1])) {
        $host = $argv[$i + 1];
        continue;
    }
    
    // Simple positional arguments
    if (is_numeric($arg)) {
        $port = (int)$arg;
    } elseif (strpos($arg, '.') !== false || $arg === 'localhost') {
        $host = $arg;
    }
}

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