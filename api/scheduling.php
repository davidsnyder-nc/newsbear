<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once __DIR__ . '/../includes/ScheduleManager.php';

$scheduleManager = new ScheduleManager();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get all schedules
        $schedules = $scheduleManager->getAllSchedules();
        echo json_encode([
            'success' => true,
            'schedules' => $schedules
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input || !isset($input['action'])) {
            throw new Exception('Invalid request data');
        }
        
        switch ($input['action']) {
            case 'create_schedule':
                $result = $scheduleManager->createSchedule([
                    'name' => $input['name'] ?? '',
                    'time' => $input['time'] ?? '',
                    'days' => $input['days'] ?? [],
                    'active' => $input['active'] ?? true,
                    'settings' => $input['settings'] ?? []
                ]);
                
                echo json_encode([
                    'success' => true,
                    'id' => $result,
                    'message' => 'Schedule created successfully'
                ]);
                break;
                
            case 'toggle_schedule':
                if (!isset($input['id'])) {
                    throw new Exception('Schedule ID required');
                }
                
                $result = $scheduleManager->toggleSchedule($input['id']);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Schedule toggled successfully' : 'Failed to toggle schedule'
                ]);
                break;
                
            case 'delete_schedule':
                if (!isset($input['id'])) {
                    throw new Exception('Schedule ID required');
                }
                
                $result = $scheduleManager->deleteSchedule($input['id']);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Schedule deleted successfully' : 'Failed to delete schedule'
                ]);
                break;
                
            case 'run_schedule':
                if (!isset($input['id'])) {
                    throw new Exception('Schedule ID required');
                }
                
                $result = $scheduleManager->runSchedule($input['id']);
                echo json_encode([
                    'success' => $result,
                    'message' => $result ? 'Schedule executed successfully' : 'Failed to execute schedule'
                ]);
                break;
                
            default:
                throw new Exception('Unknown action: ' . $input['action']);
        }
    } else {
        throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>