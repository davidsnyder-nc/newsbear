<?php
session_start();
require_once 'includes/AuthManager.php';

$auth = new AuthManager();
$authStatus = $auth->getAuthStatus();

// Load settings
$settingsFile = 'config/user_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}

// Check for saved message
$savedMessage = '';
if (isset($_GET['saved']) && $_GET['saved'] == '1') {
    $savedMessage = 'Settings saved successfully!';
}

// Default values
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NewsBear - Personalized News Brief</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
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
    <!-- Authentication Header -->
    <?php if ($authStatus['enabled']): ?>
    <header class="bg-white shadow-sm border-b">
        <div class="container mx-auto px-4 py-3 max-w-4xl">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <?php if ($authStatus['loggedIn']): ?>
                        Welcome, <?php echo htmlspecialchars($authStatus['username']); ?>
                    <?php else: ?>
                        Authentication Required
                    <?php endif; ?>
                </div>
                <div class="flex items-center space-x-4">
                    <?php if ($authStatus['loggedIn']): ?>
                        <a href="logout.php" class="text-sm text-red-600 hover:text-red-800">
                            <i class="fas fa-sign-out-alt mr-1"></i>Logout
                        </a>
                    <?php else: ?>
                        <a href="login.php" class="text-sm text-blue-600 hover:text-blue-800">
                            <i class="fas fa-sign-in-alt mr-1"></i>Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
    
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <!-- Main Content -->
        <div class="relative flex flex-col items-center justify-center min-h-[60vh]">
            <!-- Logo and Title Section -->
            <div class="text-center mb-8">
                <?php if ($authStatus['enabled'] && !$authStatus['loggedIn']): ?>
                    <div class="inline-flex flex-col items-center cursor-pointer" onclick="showAuthRequired('settings')">
                        <img src="attached_assets/newsbear_brown_logo.png" alt="NewsBear Logo" class="w-48 h-48 sm:w-64 sm:h-64 -mb-8">
                        <h1 class="text-4xl sm:text-5xl font-bold leading-none" style="color: #3A2B1F;">NewsBear</h1>
                    </div>
                <?php else: ?>
                    <a href="settings.php" class="inline-flex flex-col items-center hover:opacity-80 transition-opacity">
                        <img src="attached_assets/newsbear_brown_logo.png" alt="NewsBear Logo" class="w-48 h-48 sm:w-64 sm:h-64 -mb-8">
                        <h1 class="text-4xl sm:text-5xl font-bold leading-none" style="color: #3A2B1F;">NewsBear</h1>
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Hidden New Button -->
            <button id="new-btn" class="text-green-600 hover:text-green-800 transition duration-200 px-3 py-2 rounded-lg border border-green-300 hover:bg-green-50 text-sm hidden mb-4">
                <i class="fas fa-plus mr-2"></i>
                New
            </button>
            <!-- Status Display -->
            <div id="status-container" class="mb-8 hidden">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-8 mx-8">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-xl mr-3"></i>
                        <span id="status-text" class="text-blue-800 font-medium">Generating briefing...</span>
                    </div>
                </div>
            </div>

            <!-- Success Display -->
            <div id="success-container" class="mb-8 hidden">
                <div class="bg-green-50 border border-green-200 rounded-lg p-6">
                    <div class="text-center mb-4">
                        <i class="fas fa-check-circle text-green-600 text-3xl mb-2"></i>
                        <h3 class="text-lg font-semibold text-green-800 mb-2">Briefing Complete!</h3>
                        <p class="text-green-700">Your personalized news briefing is ready.</p>
                    </div>
                    
                    <div id="download-section" class="mb-4 hidden">
                        <div class="audio-container">
                            <div class="mb-3 flex justify-between items-center">
                                <div>
                                    <i class="fas fa-volume-up text-blue-600 mr-2"></i>
                                    <span class="text-sm font-medium text-gray-700">Your News Briefing</span>
                                </div>
                                <a id="download-link" href="#" download class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm flex items-center">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                            
                            <!-- Enhanced Audio Player -->
                            <div class="custom-audio-player" id="main-audio-player">
                                <audio id="briefing-player" preload="auto" class="hidden">
                                    <source id="audio-source" src="" type="audio/mpeg">
                                </audio>
                                
                                <!-- Player Controls -->
                                <div class="flex items-center space-x-3">
                                    <button class="play-pause-btn bg-blue-600 hover:bg-blue-700 text-white rounded-full p-3 w-12 h-12 flex items-center justify-center transition-colors">
                                        <i class="fas fa-play"></i>
                                    </button>
                                    
                                    <div class="flex-1">
                                        <div class="progress-container bg-gray-200 rounded-full h-2 cursor-pointer">
                                            <div class="progress-bar bg-blue-600 h-2 rounded-full transition-all duration-150" style="width: 0%"></div>
                                            <div class="progress-handle bg-blue-700 w-4 h-4 rounded-full absolute -top-1 border-2 border-white shadow-md opacity-0 transition-opacity" style="left: 0; margin-left: -8px;"></div>
                                        </div>
                                        <div class="flex justify-between text-xs text-gray-500 mt-1">
                                            <span class="current-time">0:00</span>
                                            <span class="duration">0:00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="volume-control flex items-center space-x-2">
                                        <button class="volume-btn text-gray-600 hover:text-blue-600 transition-colors">
                                            <i class="fas fa-volume-up"></i>
                                        </button>
                                        <input type="range" class="volume-slider w-16 h-1 bg-gray-200 rounded-lg appearance-none cursor-pointer" min="0" max="100" value="100">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div id="text-section" class="mb-4 hidden">
                        <div class="mb-3 flex justify-between items-center">
                            <div>
                                <i class="fas fa-file-text text-blue-600 mr-2"></i>
                                <span class="text-sm font-medium text-gray-700">Text Version</span>
                            </div>
                            <button id="copy-text-btn" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm flex items-center">
                                <i class="fas fa-copy mr-1"></i>Copy
                            </button>
                        </div>
                        
                        <div class="bg-white rounded-lg p-4 max-h-96 overflow-y-auto border border-gray-200">
                            <pre id="briefing-text" class="whitespace-pre-wrap text-sm text-gray-800"></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Display -->
            <div id="error-container" class="mb-8 hidden">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl mr-2"></i>
                        <span id="error-text" class="text-red-800 font-medium">An error occurred</span>
                    </div>
                </div>
            </div>

            <!-- Generate Button -->
            <?php if ($authStatus['enabled'] && !$authStatus['loggedIn']): ?>
                <button 
                    onclick="showAuthRequired('briefing')"
                    class="bg-blue-500 hover:bg-blue-600 text-white font-semibold text-xl px-8 py-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center space-x-3"
                >
                    <i class="fas fa-microphone text-2xl"></i>
                    <span>Create My News Brief</span>
                </button>
            <?php else: ?>
                <button 
                    id="generate-btn" 
                    class="bg-blue-500 hover:bg-blue-600 text-white font-semibold text-xl px-8 py-4 rounded-full shadow-lg hover:shadow-xl transition-all duration-300 flex items-center space-x-3"
                >
                    <i class="fas fa-microphone text-2xl"></i>
                    <span>Create My News Brief</span>
                </button>
            <?php endif; ?>
        </div>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
    <script>
    // Transition from loading theme to proper dark theme
    document.addEventListener('DOMContentLoaded', function() {
        const savedTheme = localStorage.getItem('darkTheme');
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        
        if (savedTheme === 'true' || (savedTheme === null && systemPrefersDark)) {
            document.documentElement.classList.remove('dark-theme-loading');
            document.body.classList.add('dark-theme');
        }
    });
    </script>
</body>
</html>