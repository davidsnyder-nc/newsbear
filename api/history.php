<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/BriefingHistory.php';

try {
    $history = new BriefingHistory();
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Get paginated briefings
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $perPage = 10;
        $offset = ($page - 1) * $perPage;
        
        $totalBriefings = $history->getBriefingCount();
        $totalPages = ceil($totalBriefings / $perPage);
        $briefings = $history->getBriefings($perPage, $offset);
        
        // Format briefings for JSON response
        $formattedBriefings = [];
        foreach ($briefings as $briefing) {
            $formattedBriefing = [
                'id' => $briefing['id'],
                'title' => $briefing['title'] ?? 'News Briefing',
                'timestamp' => $briefing['timestamp'],
                'categories' => isset($briefing['categories']) ? explode(',', $briefing['categories']) : ['General'],
                'audio_file' => $briefing['audio_file'] ?? $briefing['mp3_file'] ?? null,
                'text_file' => $briefing['text_file'] ?? null,
                'format' => isset($briefing['audio_file']) || isset($briefing['mp3_file']) ? 'mp3' : 'text',
                'duration' => $briefing['duration'] ?? 0,
                'topics' => [],
                'sources' => [],
                'text_content' => null
            ];
            
            // Check if audio file actually exists
            if ($formattedBriefing['audio_file'] && !file_exists('../' . $formattedBriefing['audio_file'])) {
                $formattedBriefing['audio_file'] = null;
                $formattedBriefing['format'] = 'text';
            }
            
            // Load topics from briefing data
            if (isset($briefing['topics'])) {
                if (is_string($briefing['topics'])) {
                    $topics = json_decode($briefing['topics'], true);
                    if (is_array($topics)) {
                        $formattedBriefing['topics'] = $topics;
                    }
                } elseif (is_array($briefing['topics'])) {
                    $formattedBriefing['topics'] = $briefing['topics'];
                }
            }
            
            // Load sources from briefing data
            if (isset($briefing['sources'])) {
                if (is_string($briefing['sources'])) {
                    $sources = json_decode($briefing['sources'], true);
                    if (is_array($sources)) {
                        $formattedBriefing['sources'] = $sources;
                    }
                } elseif (is_array($briefing['sources'])) {
                    $formattedBriefing['sources'] = $briefing['sources'];
                }
            }
            
            // Load text content
            if (isset($briefing['text_content'])) {
                $formattedBriefing['text_content'] = $briefing['text_content'];
            } elseif ($formattedBriefing['text_file'] && file_exists('../' . $formattedBriefing['text_file'])) {
                $formattedBriefing['text_content'] = file_get_contents('../' . $formattedBriefing['text_file']);
            }
            
            $formattedBriefings[] = $formattedBriefing;
        }
        
        echo json_encode([
            'success' => true,
            'briefings' => $formattedBriefings,
            'pagination' => [
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'totalBriefings' => $totalBriefings,
                'perPage' => $perPage
            ]
        ]);
        
    } elseif ($method === 'POST') {
        // Handle POST actions (delete, cleanup)
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'delete':
                $briefingId = $input['briefing_id'] ?? '';
                if (empty($briefingId)) {
                    throw new Exception('Briefing ID is required');
                }
                
                $success = $history->deleteBriefing($briefingId);
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Briefing deleted successfully' : 'Failed to delete briefing'
                ]);
                break;
                
            case 'clear_old':
                $days = intval($input['days'] ?? 30);
                $count = $history->clearOldBriefings($days);
                echo json_encode([
                    'success' => true,
                    'message' => "Deleted {$count} old briefings",
                    'count' => $count
                ]);
                break;
                
            case 'clear_all':
                $count = $history->clearAllBriefings();
                echo json_encode([
                    'success' => true,
                    'message' => "Deleted all {$count} briefings",
                    'count' => $count
                ]);
                break;
                
            case 'cleanup':
                $history->cleanupOrphanedFiles();
                echo json_encode([
                    'success' => true,
                    'message' => 'Cleaned up orphaned files'
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>