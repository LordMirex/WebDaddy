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
$db = getDb();

$input = json_decode(file_get_contents('php://input'), true);
$ticketId = isset($input['ticket_id']) ? intval($input['ticket_id']) : 0;
$message = isset($input['message']) ? trim($input['message']) : '';

if ($ticketId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ticket ID is required']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if (strlen($message) < 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message must be at least 5 characters']);
    exit;
}

$stmt = $db->prepare("
    SELECT id, status, subject FROM customer_support_tickets 
    WHERE id = ? AND customer_id = ?
");
$stmt->execute([$ticketId, $customerId]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Ticket not found']);
    exit;
}

if (in_array($ticket['status'], ['closed', 'resolved'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Cannot reply to a closed ticket']);
    exit;
}

$customerName = $customer['full_name'] ?? 'Customer';

$stmt = $db->prepare("
    INSERT INTO customer_ticket_replies 
    (ticket_id, author_type, author_id, author_name, message, created_at)
    VALUES (?, 'customer', ?, ?, ?, datetime('now'))
");
$stmt->execute([$ticketId, $customerId, $customerName, $message]);
$replyId = $db->lastInsertId();

$stmt = $db->prepare("
    UPDATE customer_support_tickets 
    SET status = 'awaiting_reply', 
        last_reply_at = datetime('now'), 
        last_reply_by = 'customer',
        updated_at = datetime('now')
    WHERE id = ?
");
$stmt->execute([$ticketId]);

logCustomerActivity($customerId, 'ticket_reply', "Replied to support ticket #$ticketId");

echo json_encode([
    'success' => true,
    'reply_id' => (int)$replyId,
    'message' => 'Reply added successfully'
]);
