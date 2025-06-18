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
                    
                    <!-- Audio Player (Hidden by default) -->
                    <?php if ($briefing['audio_file'] && file_exists($briefing['audio_file'])): ?>
                    <div id="audio-<?php echo $briefing['id']; ?>" class="hidden mt-4 p-4 bg-gray-50 rounded border">
                        <audio controls class="w-full">
                            <source src="<?php echo $briefing['audio_file']; ?>" type="audio/mpeg">
                            Your browser does not support the audio element.
                        </audio>
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
            if (audioDiv) {
                audioDiv.classList.toggle('hidden');
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
    </script>
</body>
</html>