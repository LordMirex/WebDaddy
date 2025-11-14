<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/analytics.php';

startSecureSession();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $input['action'] ?? '';
$templateId = isset($input['template_id']) ? (int)$input['template_id'] : 0;
$toolId = isset($input['tool_id']) ? (int)$input['tool_id'] : 0;

try {
    switch ($action) {
        case 'track_template_click':
            if ($templateId > 0) {
                $result = trackTemplateClick($templateId);
                echo json_encode(['success' => $result]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid template ID']);
            }
            break;
            
        case 'track_tool_click':
            if ($toolId > 0) {
                $db = getDb();
                if (!isset($_SESSION['analytics_session_id'])) {
                    $_SESSION['analytics_session_id'] = bin2hex(random_bytes(16));
                }
                
                $stmt = $db->prepare("
                    INSERT INTO page_interactions (session_id, page_url, action_type, action_target, template_id)
                    VALUES (?, ?, 'click', 'tool', ?)
                ");
                $stmt->execute([
                    $_SESSION['analytics_session_id'],
                    $_SERVER['HTTP_REFERER'] ?? '',
                    $toolId
                ]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid tool ID']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log('Analytics tracking error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Tracking failed']);
}
