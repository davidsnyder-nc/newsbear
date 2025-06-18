<?php
// Load settings
$settingsFile = 'config/user_settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
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
    <link href="style.css" rel="stylesheet">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-4xl">
        <div class="bg-white rounded-lg shadow-lg p-8">
        <!-- Header -->
        <header class="mb-8 relative">
            <!-- Logo and Title Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <!-- Logo and Title -->
                <div class="flex items-center justify-center sm:justify-start mb-4 sm:mb-0">
                    <img src="attached_assets/newsbear_blue_logo.png" alt="NewsBear Logo" class="w-20 h-20 sm:w-24 sm:h-24 mr-3 sm:mr-4">
                    <div class="text-left">
                        <h1 class="text-3xl sm:text-4xl font-bold text-gray-800 leading-none mb-0">NewsBear</h1>
                        <p class="text-gray-600 text-base sm:text-lg leading-none -mt-1">Personalized News Brief</p>
                    </div>
                </div>
                
                <!-- Navigation Buttons -->
                <div class="flex justify-center sm:justify-end space-x-3">
                    <button id="new-btn" class="text-green-600 hover:text-green-800 transition duration-200 px-3 py-2 rounded-lg border border-green-300 hover:bg-green-50 text-sm hidden">
                        <i class="fas fa-plus mr-2"></i>
                        New
                    </button>
                    <a href="settings.php" class="text-gray-600 hover:text-gray-800 transition duration-200 px-3 py-2 rounded-lg border border-gray-300 hover:bg-gray-50 text-sm">
                        <i class="fas fa-cog mr-2"></i>
                        Settings
                    </a>
                    <a href="history.php" class="text-purple-600 hover:text-purple-800 transition duration-200 px-3 py-2 rounded-lg border border-purple-300 hover:bg-purple-50 text-sm">
                        <i class="fas fa-history mr-2"></i>
                        History
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="relative">
            <!-- Status Display -->
            <div id="status-container" class="mb-8 hidden">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-spinner fa-spin text-blue-600 text-xl mr-3"></i>
                        <div>
                            <span id="status-text" class="text-blue-800 font-medium">Generating briefing...</span>
                            <div class="w-64 bg-blue-200 rounded-full h-2 mt-2">
                                <div id="progress-bar" class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
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
                        <div class="bg-white border border-gray-200 rounded-lg p-4 mb-4">
                            <div class="flex items-center space-x-4">
                                <audio id="briefing-player" controls class="flex-1">
                                    <source id="audio-source" src="" type="audio/mpeg">
                                    Your browser does not support the audio element.
                                </audio>
                                <a id="download-link" href="#" download class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm flex items-center">
                                    <i class="fas fa-download mr-1"></i>Download
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div id="briefing-text-section" class="hidden">
                        <div class="bg-white border border-gray-200 rounded-lg p-4 max-h-64 overflow-y-auto">
                            <div class="flex justify-between items-center mb-2">
                                <h4 class="font-medium text-gray-800">Briefing Text:</h4>
                                <button id="copy-btn" class="text-blue-600 hover:text-blue-800 text-sm">
                                    <i class="fas fa-copy mr-1"></i>Copy
                                </button>
                            </div>
                            <div id="briefing-text" class="text-sm text-gray-700 whitespace-pre-wrap"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Error Display -->
            <div id="error-container" class="mb-8 hidden">
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-600 text-xl mr-3"></i>
                        <span id="error-text" class="text-red-800 font-medium">An error occurred</span>
                    </div>
                </div>
            </div>

            <!-- Simple Description -->
            <div class="text-center mb-12">
                <p class="text-gray-500 max-w-md mx-auto">Powered by AI, tailored to your preferences, delivered in seconds.</p>
            </div>

            <!-- Generate Button -->
            <div class="flex justify-center">
                <button 
                    id="generate-btn" 
                    class="bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white font-semibold text-xl px-12 py-6 rounded-2xl shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105 flex items-center space-x-3"
                >
                    <i class="fas fa-microphone text-2xl"></i>
                    <span>Create My News Brief</span>
                </button>
            </div>
        </div>
        </div>
    </div>

    <script src="script.js"></script>
</body>
</html>