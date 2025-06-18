<?php
// Set timezone for correct timestamps
date_default_timezone_set('America/New_York');

require_once 'includes/BriefingHistory.php';

$history = new BriefingHistory();
$action = $_GET['action'] ?? '';
$message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'delete' && isset($_POST['briefing_id'])) {
        if ($history->deleteBriefing($_POST['briefing_id'])) {
            $message = 'Briefing deleted successfully.';
        } else {
            $message = 'Failed to delete briefing.';
        }
    } elseif ($action === 'clear_old' && isset($_POST['days'])) {
        $days = intval($_POST['days']);
        $count = $history->clearOldBriefings($days);
        $message = "Deleted {$count} old briefings.";
    } elseif ($action === 'clear_all') {
        $count = $history->clearAllBriefings();
        $message = "Deleted all {$count} briefings.";
    } elseif ($action === 'cleanup') {
        $history->cleanupOrphanedFiles();
        $message = "Cleaned up orphaned files in downloads folder.";
    }
    
    // Redirect to prevent form resubmission
    header('Location: history.php?msg=' . urlencode($message));
    exit;
}

// Get message from redirect
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}

// Pagination settings
$briefingsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $briefingsPerPage;

// Get total count and paginated briefings
$totalBriefings = $history->getBriefingCount();
$totalPages = ceil($totalBriefings / $briefingsPerPage);
$briefings = $history->getBriefings($briefingsPerPage, $offset);
$todaysTopics = $history->getTodaysTopics();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NewsBear - Briefing History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="container mx-auto px-4 py-4 md:py-8">
        <!-- Header -->
        <div class="text-center mb-6 md:mb-8">
            <div class="flex flex-col sm:flex-row justify-center items-center mb-4">
                <img src="attached_assets/newsbear_brown_logo.png" alt="NewsBear" class="h-12 w-12 sm:h-16 sm:w-16 mb-2 sm:mb-0 sm:mr-4">
                <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-800">Briefing History</h1>
            </div>
            <div class="flex flex-col sm:flex-row justify-center gap-2 sm:gap-4 mt-4">
                <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 sm:px-6 py-2 rounded-lg text-sm sm:text-base">
                    <i class="fas fa-home mr-2"></i>Home
                </a>
                <a href="settings.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 sm:px-6 py-2 rounded-lg text-sm sm:text-base">
                    <i class="fas fa-cog mr-2"></i>Settings
                </a>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Today's Topics -->
        <?php if (!empty($todaysTopics)): ?>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 md:p-6 mb-6 md:mb-8">
            <h2 class="text-lg md:text-xl font-semibold text-blue-800 mb-3">
                <i class="fas fa-clock mr-2"></i>Topics Covered Today
            </h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach (array_slice($todaysTopics, 0, 15) as $topic): ?>
                <?php if (is_array($topic) && !empty($topic['url'])): ?>
                <a href="<?php echo htmlspecialchars($topic['url']); ?>" target="_blank" rel="noopener noreferrer" 
                   class="bg-blue-100 hover:bg-blue-200 text-blue-800 hover:text-blue-900 px-2 md:px-3 py-1 rounded-full text-xs md:text-sm transition-colors duration-200 inline-flex items-center group">
                    <?php echo htmlspecialchars(is_array($topic) ? $topic['title'] : $topic); ?>
                    <i class="fas fa-external-link-alt ml-1 text-xs opacity-60 group-hover:opacity-100"></i>
                </a>
                <?php else: ?>
                <span class="bg-blue-100 text-blue-800 px-2 md:px-3 py-1 rounded-full text-xs md:text-sm">
                    <?php echo htmlspecialchars(is_array($topic) ? $topic['title'] : $topic); ?>
                </span>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php if (count($todaysTopics) > 15): ?>
                <span class="text-blue-600 text-xs md:text-sm">+ <?php echo count($todaysTopics) - 15; ?> more</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Management Actions -->
        <div class="bg-white rounded-lg shadow-md p-4 md:p-6 mb-6 md:mb-8">
            <h2 class="text-lg md:text-xl font-semibold text-gray-800 mb-4">
                <i class="fas fa-tools mr-2"></i>Manage Briefings
            </h2>
            <div class="flex flex-col sm:flex-row gap-3 md:gap-4">
                <button onclick="showClearOldModal()" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded text-sm md:text-base">
                    <i class="fas fa-calendar-times mr-2"></i>Clear Old Briefings
                </button>
                <button onclick="showClearAllModal()" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm md:text-base">
                    <i class="fas fa-trash-alt mr-2"></i>Clear All Briefings
                </button>
                <form method="POST" action="history.php?action=cleanup" class="inline">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm md:text-base">
                        <i class="fas fa-broom mr-2"></i>Clean Downloads
                    </button>
                </form>
            </div>
        </div>

        <!-- Briefing List -->
        <div class="bg-white rounded-lg shadow-md">
            <div class="p-4 md:p-6 border-b border-gray-200">
                <h2 class="text-lg md:text-xl font-semibold text-gray-800">
                    <i class="fas fa-history mr-2"></i>All Briefings (<?php echo $totalBriefings; ?>)
                </h2>
                <?php if ($totalPages > 1): ?>
                <p class="text-sm text-gray-600 mt-1">
                    Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?> 
                    (Showing <?php echo count($briefings); ?> of <?php echo $totalBriefings; ?> briefings)
                </p>
                <?php endif; ?>
            </div>
            
            <?php if (empty($briefings)): ?>
            <div class="p-6 md:p-8 text-center text-gray-500">
                <i class="fas fa-inbox text-3xl md:text-4xl mb-4"></i>
                <p class="text-base md:text-lg">No briefings found.</p>
                <p class="text-sm md:text-base">Generate your first briefing to see it here!</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($briefings as $briefing): ?>
                <div class="p-4 md:p-6">
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start mb-3 gap-3">
                        <div class="flex-1">
                            <h3 class="text-base md:text-lg font-semibold text-gray-800">
                                <?php echo date('M j, Y - g:i A', $briefing['timestamp']); ?>
                            </h3>
                            <div class="flex items-center text-xs md:text-sm text-gray-600 mt-1">
                                <i class="fas fa-<?php echo $briefing['format'] === 'mp3' ? 'volume-up' : 'file-text'; ?> mr-2"></i>
                                <?php echo ucfirst($briefing['format']); ?>
                                <?php if ($briefing['duration'] > 0): ?>
                                | <?php echo $briefing['duration']; ?> min
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php if ($briefing['audio_file'] && file_exists($briefing['audio_file'])): ?>
                            <button onclick="toggleAudio('<?php echo $briefing['id']; ?>')" 
                                    class="bg-green-600 hover:bg-green-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                                <i class="fas fa-play mr-1"></i>Play
                            </button>
                            <a href="<?php echo $briefing['audio_file']; ?>" 
                               class="bg-green-700 hover:bg-green-800 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm"
                               download>
                                <i class="fas fa-download mr-1"></i>Download
                            </a>
                            <?php else: ?>
                            <button onclick="generateAudio('<?php echo $briefing['id']; ?>')" 
                                    class="bg-purple-600 hover:bg-purple-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                                <i class="fas fa-microphone mr-1"></i>Create MP3
                            </button>
                            <?php endif; ?>
                            <button onclick="toggleText('<?php echo $briefing['id']; ?>')" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                                <i class="fas fa-eye mr-1"></i>Text
                            </button>
                            <button onclick="deleteBriefing('<?php echo $briefing['id']; ?>')" 
                                    class="bg-red-600 hover:bg-red-700 text-white px-2 md:px-3 py-1 rounded text-xs md:text-sm">
                                <i class="fas fa-trash mr-1"></i>Delete
                            </button>
                        </div>
                    </div>
                    
                    <!-- Topics -->
                    <?php if (!empty($briefing['topics'])): ?>
                    <div class="mb-3">
                        <div class="flex flex-wrap gap-1">
                            <?php foreach (array_slice($briefing['topics'], 0, 8) as $topic): ?>
                            <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-xs">
                                <?php 
                                if (is_array($topic) && isset($topic['title'])) {
                                    echo htmlspecialchars($topic['title']);
                                } else {
                                    echo htmlspecialchars((string)$topic);
                                }
                                ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Source Links (if available) -->
                    <?php if (!empty($briefing['sources'])): ?>
                    <div class="mb-3">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">
                            <i class="fas fa-link mr-1"></i>News Sources:
                        </h4>
                        <div class="space-y-1">
                            <?php foreach ($briefing['sources'] as $source): ?>
                            <div class="text-xs bg-blue-50 border border-blue-200 rounded p-2">
                                <a href="<?php echo htmlspecialchars($source['url']); ?>" 
                                   target="_blank" 
                                   class="text-blue-700 hover:text-blue-900 font-medium">
                                    <?php echo htmlspecialchars($source['title']); ?>
                                </a>
                                <span class="text-gray-500 ml-2">
                                    (<?php echo htmlspecialchars($source['source']); ?>)
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Enhanced Audio Player (Hidden by default) -->
                    <?php if ($briefing['audio_file'] && file_exists($briefing['audio_file'])): ?>
                    <div id="audio-<?php echo $briefing['id']; ?>" class="hidden mt-4 audio-container">
                        <div class="mb-3">
                            <i class="fas fa-volume-up text-blue-600 mr-2"></i>
                            <span class="text-sm font-medium text-gray-700">Audio Playback</span>
                        </div>
                        
                        <!-- Custom Audio Player -->
                        <div class="custom-audio-player" data-audio-src="<?php echo $briefing['audio_file']; ?>">
                            <audio preload="auto" class="hidden">
                                <source src="<?php echo $briefing['audio_file']; ?>" type="audio/mpeg">
                            </audio>
                            
                            <!-- Player Controls -->
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
                                    <div class="volume-slider-container hidden">
                                        <input type="range" class="volume-slider" min="0" max="100" value="100" style="width: 60px;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Fallback for browsers that don't support custom controls -->
                            <noscript>
                                <audio controls class="w-full">
                                    <source src="<?php echo $briefing['audio_file']; ?>" type="audio/mpeg">
                                    Your browser does not support the audio element.
                                </audio>
                            </noscript>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Briefing Text (Hidden by default) -->
                    <div id="text-<?php echo $briefing['id']; ?>" class="hidden mt-4 p-4 bg-gray-50 rounded border">
                        <div class="text-sm text-gray-800 whitespace-pre-wrap">
                            <?php echo htmlspecialchars($briefing['text']); ?>
                        </div>
                        <button onclick="copyBriefingText('text-<?php echo $briefing['id']; ?>')" 
                                class="mt-3 bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded text-sm">
                            <i class="fas fa-copy mr-1"></i>Copy Text
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Pagination Controls -->
            <?php if ($totalPages > 1): ?>
            <div class="p-4 md:p-6 border-t border-gray-200">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-gray-600">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $briefingsPerPage, $totalBriefings); ?> of <?php echo $totalBriefings; ?> briefings
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <?php if ($currentPage > 1): ?>
                        <a href="?page=1" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">
                            First
                        </a>
                        <a href="?page=<?php echo $currentPage - 1; ?>" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">
                            Previous
                        </a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" 
                           class="px-3 py-1 text-sm rounded <?php echo $i == $currentPage ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($currentPage < $totalPages): ?>
                        <a href="?page=<?php echo $currentPage + 1; ?>" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">
                            Next
                        </a>
                        <a href="?page=<?php echo $totalPages; ?>" class="px-3 py-1 text-sm bg-gray-200 hover:bg-gray-300 rounded">
                            Last
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Clear Old Modal -->
    <div id="clearOldModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Clear Old Briefings</h3>
                <p class="text-gray-600 mb-4">Delete briefings older than how many days?</p>
                <form method="POST" action="history.php?action=clear_old">
                    <select name="days" class="w-full p-2 border rounded mb-4">
                        <option value="7">7 days</option>
                        <option value="14">14 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                    </select>
                    <div class="flex space-x-4">
                        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-4 py-2 rounded">
                            Clear Old
                        </button>
                        <button type="button" onclick="hideClearOldModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Clear All Modal -->
    <div id="clearAllModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 max-w-md w-full">
                <h3 class="text-lg font-semibold mb-4">Clear All Briefings</h3>
                <p class="text-gray-600 mb-4">Are you sure you want to delete ALL briefings? This cannot be undone.</p>
                <div class="flex space-x-4">
                    <form method="POST" action="history.php?action=clear_all" class="inline">
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                            Delete All
                        </button>
                    </form>
                    <button type="button" onclick="hideClearAllModal()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleText(briefingId) {
            const textDiv = document.getElementById('text-' + briefingId);
            textDiv.classList.toggle('hidden');
        }

        function toggleAudio(briefingId) {
            const audioDiv = document.getElementById('audio-' + briefingId);
            const button = event.target.closest('button');
            
            if (audioDiv) {
                const isHidden = audioDiv.classList.contains('hidden');
                
                if (isHidden) {
                    // Show audio player
                    audioDiv.classList.remove('hidden');
                    // Initialize the custom player if not already done
                    if (!audioDiv.querySelector('.custom-audio-player').initialized) {
                        initializeCustomAudioPlayers();
                    }
                    button.innerHTML = '<i class="fas fa-pause mr-1"></i>Pause';
                } else {
                    // Hide audio player and pause
                    audioDiv.classList.add('hidden');
                    const audio = audioDiv.querySelector('audio');
                    if (audio) {
                        audio.pause();
                    }
                    button.innerHTML = '<i class="fas fa-play mr-1"></i>Play';
                }
            }
        }

        function generateAudio(briefingId) {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Generating...';
            button.disabled = true;
            
            fetch('api/generate_audio.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ briefingId: briefingId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to generate audio: ' + (data.error || 'Unknown error'));
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to generate audio');
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        function copyBriefingText(elementId) {
            const element = document.getElementById(elementId);
            const text = element.querySelector('.whitespace-pre-wrap').textContent;
            navigator.clipboard.writeText(text).then(() => {
                showToast('Text copied to clipboard!', 'success');
            });
        }

        function deleteBriefing(briefingId) {
            if (confirm('Are you sure you want to delete this briefing?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'history.php?action=delete';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'briefing_id';
                input.value = briefingId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function showClearOldModal() {
            document.getElementById('clearOldModal').classList.remove('hidden');
        }

        function hideClearOldModal() {
            document.getElementById('clearOldModal').classList.add('hidden');
        }

        function showClearAllModal() {
            document.getElementById('clearAllModal').classList.remove('hidden');
        }

        function hideClearAllModal() {
            document.getElementById('clearAllModal').classList.add('hidden');
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-6 py-3 rounded-lg text-white z-50 ${
                type === 'success' ? 'bg-green-600' : 'bg-blue-600'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Initialize audio event listeners when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeCustomAudioPlayers();
        });

        function initializeCustomAudioPlayers() {
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
                const volumeSlider = player.querySelector('.volume-slider');
                const volumeContainer = player.querySelector('.volume-slider-container');
                
                let isDragging = false;
                
                // Play/Pause functionality
                playPauseBtn.addEventListener('click', () => {
                    if (audio.paused) {
                        audio.play();
                        playPauseBtn.innerHTML = '<i class="fas fa-pause text-sm"></i>';
                    } else {
                        audio.pause();
                        playPauseBtn.innerHTML = '<i class="fas fa-play text-sm"></i>';
                    }
                });
                
                // Update progress and time
                audio.addEventListener('timeupdate', () => {
                    if (!isDragging) {
                        const progress = (audio.currentTime / audio.duration) * 100;
                        progressBar.style.width = progress + '%';
                        progressHandle.style.left = progress + '%';
                        currentTimeSpan.textContent = formatTime(audio.currentTime);
                    }
                });
                
                // Load duration when metadata is loaded
                audio.addEventListener('loadedmetadata', () => {
                    durationSpan.textContent = formatTime(audio.duration);
                });
                
                // Progress bar clicking and dragging
                progressContainer.addEventListener('mousedown', (e) => {
                    isDragging = true;
                    e.preventDefault();
                    updateProgress(e);
                });
                
                progressContainer.addEventListener('mousemove', (e) => {
                    if (isDragging) {
                        e.preventDefault();
                        updateProgress(e);
                    }
                });
                
                document.addEventListener('mouseup', () => {
                    if (isDragging) {
                        isDragging = false;
                    }
                });
                
                // Also handle click events separately for immediate seeking
                progressContainer.addEventListener('click', (e) => {
                    if (!isDragging) {
                        updateProgress(e);
                    }
                });
                
                function updateProgress(e) {
                    const rect = progressContainer.getBoundingClientRect();
                    const pos = (e.clientX - rect.left) / rect.width;
                    const clampedPos = Math.max(0, Math.min(1, pos));
                    
                    if (isNaN(audio.duration) || audio.duration === 0) {
                        return;
                    }
                    
                    const newTime = clampedPos * audio.duration;
                    
                    // Direct seeking without complex pause/resume logic
                    if (audio.readyState >= 2) { // HAVE_CURRENT_DATA or higher
                        audio.currentTime = newTime;
                    }
                    
                    // Update visual elements
                    const progress = clampedPos * 100;
                    progressBar.style.width = progress + '%';
                    progressHandle.style.left = progress + '%';
                    currentTimeSpan.textContent = formatTime(newTime);
                }
                
                // Volume control
                volumeBtn.addEventListener('click', () => {
                    volumeContainer.classList.toggle('hidden');
                });
                
                volumeSlider.addEventListener('input', () => {
                    audio.volume = volumeSlider.value / 100;
                    updateVolumeIcon();
                });
                
                function updateVolumeIcon() {
                    const volume = audio.volume;
                    const icon = volumeBtn.querySelector('i');
                    
                    if (volume === 0) {
                        icon.className = 'fas fa-volume-mute';
                    } else if (volume < 0.5) {
                        icon.className = 'fas fa-volume-down';
                    } else {
                        icon.className = 'fas fa-volume-up';
                    }
                }
                
                // Reset when audio ends
                audio.addEventListener('ended', () => {
                    playPauseBtn.innerHTML = '<i class="fas fa-play text-sm"></i>';
                    progressBar.style.width = '0%';
                    progressHandle.style.left = '0%';
                    currentTimeSpan.textContent = '0:00';
                });
                
                // Show handle on hover
                progressContainer.addEventListener('mouseenter', () => {
                    progressHandle.style.opacity = '1';
                });
                
                progressContainer.addEventListener('mouseleave', () => {
                    if (!isDragging) {
                        progressHandle.style.opacity = '0';
                    }
                });
            });
        }
        
        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = Math.floor(seconds % 60);
            return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
        }
    </script>
</body>
</html>