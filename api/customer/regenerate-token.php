<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/delivery.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$customerId = $customer['customer_id'];
$input = json_decode(file_get_contents('php://input'), true);
$tokenId = isset($input['token_id']) ? intval($input['token_id']) : 0;

if ($tokenId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Token ID is required']);
    exit;
}

$result = regenerateDownloadToken($tokenId, $customerId);

if (!$result['success']) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $result['error'] ?? 'Failed to regenerate token'
    ]);
    exit;
}

logCustomerActivity($customerId, 'token_regenerated', "Regenerated download token #$tokenId");

echo json_encode([
    'success' => true,
    'new_token' => $result['token'],
    'expires_at' => $result['expires_at'],
    'max_downloads' => $result['max_downloads']
]);
