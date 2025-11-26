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

// Support both JSON and form data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input && !empty($_POST)) {
    // Use POST data if JSON parsing failed
    $input = $_POST;
}

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
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
        
        case 'track_share':
            require_once __DIR__ . '/../includes/functions.php';
            
            $platform = sanitizeInput($input['platform'] ?? '');
            $url = sanitizeInput($input['url'] ?? '');
            $templateSlug = sanitizeInput($input['template_slug'] ?? '');
            
            // Get template ID from slug
            $db = getDb();
            $stmt = $db->prepare("SELECT id FROM templates WHERE slug = ?");
            $stmt->execute([$templateSlug]);
            $template = $stmt->fetch();
            
            if ($template) {
                // Log share event
                $stmt = $db->prepare("
                    INSERT INTO activity_logs (activity_type, description, user_id, ip_address, created_at)
                    VALUES (?, ?, ?, ?, datetime('now', '+1 hour'))
                ");
                
                $description = "Template shared via $platform: $templateSlug";
                $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
                
                $stmt->execute(['template_shared', $description, null, $ipAddress]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Template not found']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }
} catch (Exception $e) {
    error_log('Analytics tracking error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Tracking failed']);
}
