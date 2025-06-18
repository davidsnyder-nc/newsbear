<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../includes/BriefingHistory.php';

$history = new BriefingHistory();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            if ($action === 'today_topics') {
                echo json_encode([
                    'success' => true,
                    'topics' => $history->getTodaysTopics()
                ]);
            } elseif ($action === 'all') {
                echo json_encode([
                    'success' => true,
                    'briefings' => $history->getAllBriefings()
                ]);
            } elseif ($action === 'by_date' && isset($_GET['date'])) {
                echo json_encode([
                    'success' => true,
                    'briefings' => $history->getBriefingsByDate($_GET['date'])
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action or missing parameters'
                ]);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if ($action === 'clear_old' && isset($input['days'])) {
                $count = $history->clearOldBriefings(intval($input['days']));
                echo json_encode([
                    'success' => true,
                    'message' => "Deleted {$count} old briefings",
                    'deleted_count' => $count
                ]);
            } elseif ($action === 'clear_all') {
                $count = $history->clearAllBriefings();
                echo json_encode([
                    'success' => true,
                    'message' => "Deleted all {$count} briefings",
                    'deleted_count' => $count
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action or missing parameters'
                ]);
            }
            break;
            
        case 'DELETE':
            if ($action === 'briefing' && isset($_GET['id'])) {
                $success = $history->deleteBriefing($_GET['id']);
                echo json_encode([
                    'success' => $success,
                    'message' => $success ? 'Briefing deleted successfully' : 'Failed to delete briefing'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid action or missing briefing ID'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed'
            ]);
            break;
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>