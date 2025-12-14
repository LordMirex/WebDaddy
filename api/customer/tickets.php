<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';

header('Content-Type: application/json');

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$customerId = $customer['customer_id'];
$db = getDb();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare("
        SELECT 
            id,
            subject,
            category,
            status,
            priority,
            order_id as linked_order_id,
            created_at,
            last_reply_at,
            last_reply_by
        FROM customer_support_tickets
        WHERE customer_id = ?
        ORDER BY 
            CASE WHEN status IN ('open', 'awaiting_reply') THEN 0 ELSE 1 END,
            created_at DESC
    ");
    $stmt->execute([$customerId]);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $formattedTickets = array_map(function($ticket) {
        return [
            'id' => (int)$ticket['id'],
            'subject' => $ticket['subject'],
            'category' => $ticket['category'],
            'status' => $ticket['status'],
            'priority' => $ticket['priority'],
            'linked_order_id' => $ticket['linked_order_id'] ? (int)$ticket['linked_order_id'] : null,
            'created_at' => $ticket['created_at'],
            'last_reply_at' => $ticket['last_reply_at'],
            'last_reply_by' => $ticket['last_reply_by']
        ];
    }, $tickets);
    
    echo json_encode([
        'success' => true,
        'tickets' => $formattedTickets
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $subject = isset($input['subject']) ? trim($input['subject']) : '';
    $category = isset($input['category']) ? trim($input['category']) : 'general';
    $message = isset($input['message']) ? trim($input['message']) : '';
    $orderId = isset($input['order_id']) ? intval($input['order_id']) : null;
    
    if (empty($subject)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject is required']);
        exit;
    }
    
    if (strlen($subject) < 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Subject must be at least 5 characters']);
        exit;
    }
    
    if (empty($message)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit;
    }
    
    if (strlen($message) < 10) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Message must be at least 10 characters']);
        exit;
    }
    
    $validCategories = ['general', 'order', 'delivery', 'refund', 'technical', 'account'];
    if (!in_array($category, $validCategories)) {
        $category = 'general';
    }
    
    if ($orderId) {
        $orderCheck = $db->prepare("SELECT id FROM pending_orders WHERE id = ? AND customer_id = ?");
        $orderCheck->execute([$orderId, $customerId]);
        if (!$orderCheck->fetch()) {
            $orderId = null;
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO customer_support_tickets 
        (customer_id, order_id, subject, message, category, status, priority, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, 'open', 'normal', datetime('now'), datetime('now'))
    ");
    $stmt->execute([$customerId, $orderId, $subject, $message, $category]);
    $ticketId = $db->lastInsertId();
    
    logCustomerActivity($customerId, 'ticket_created', "Created support ticket #$ticketId: $subject");
    
    if (function_exists('queueEmail')) {
        require_once __DIR__ . '/../../includes/email_queue.php';
        require_once __DIR__ . '/../../includes/mailer.php';
        
        $emailBody = '<h2 style="color: #1e3a8a; margin: 0 0 15px 0;">Support Ticket Created</h2>';
        $emailBody .= '<p style="color: #374151;">Your support ticket has been created and our team will respond shortly.</p>';
        $emailBody .= '<p style="color: #374151;"><strong>Ticket ID:</strong> #' . $ticketId . '</p>';
        $emailBody .= '<p style="color: #374151;"><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>';
        $emailBody .= '<p style="color: #374151;"><strong>Category:</strong> ' . ucfirst($category) . '</p>';
        
        $emailSubject = "Support Ticket #$ticketId Created - " . substr($subject, 0, 50);
        $emailHtml = createEmailTemplate($emailSubject, $emailBody, $customer['full_name'] ?? 'Customer');
        
        queueEmail(
            $customer['email'],
            'ticket_created',
            $emailSubject,
            strip_tags($emailBody),
            $emailHtml,
            null,
            null,
            'normal'
        );
    }
    
    echo json_encode([
        'success' => true,
        'ticket_id' => (int)$ticketId,
        'message' => 'Support ticket created successfully'
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
