<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';

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
$allDevices = !empty($input['all_devices']);

if ($allDevices) {
    revokeAllCustomerSessions($customerId, 'logout_all_devices');
    logCustomerActivity($customerId, 'logout_all_devices', 'Customer logged out from all devices');
} else {
    destroyCustomerSession();
    logCustomerActivity($customerId, 'logout', 'Customer logged out');
}

echo json_encode([
    'success' => true,
    'message' => $allDevices ? 'Logged out from all devices successfully' : 'Logged out successfully'
]);
