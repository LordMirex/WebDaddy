<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/analytics.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

startSecureSession();

$data = json_decode(file_get_contents('php://input'), true);
$toolId = isset($data['tool_id']) ? (int)$data['tool_id'] : 0;
$source = isset($data['source']) ? $data['source'] : 'unknown';

if ($toolId > 0) {
    trackToolView($toolId);
    echo json_encode(['success' => true, 'source' => $source]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid tool_id']);
}
