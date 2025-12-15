<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/delivery.php';

header('Content-Type: application/json');

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Handle POST requests for payment notifications
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'payment_notification') {
        $orderId = intval($input['order_id'] ?? 0);
        $customerId = $customer['customer_id'];
        
        if ($orderId <= 0) {
            echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
            exit;
        }
        
        $db = getDb();
        
        // Verify order belongs to customer and is pending
        $stmt = $db->prepare("SELECT id, status, final_amount, customer_email FROM pending_orders WHERE id = ? AND customer_id = ?");
        $stmt->execute([$orderId, $customerId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'error' => 'Order not found']);
            exit;
        }
        
        if ($order['status'] !== 'pending') {
            echo json_encode(['success' => false, 'error' => 'Order is not awaiting payment']);
            exit;
        }
        
        // Mark order as payment notified
        $updateStmt = $db->prepare("UPDATE pending_orders SET payment_notified = 1, payment_notified_at = datetime('now') WHERE id = ?");
        $updateStmt->execute([$orderId]);
        
        // Send admin notification email about pending payment
        try {
            require_once __DIR__ . '/../../includes/notifications.php';
            $adminEmail = ADMIN_EMAIL ?? 'webdaddyempire@gmail.com';
            $subject = "Manual Payment Notification - Order #$orderId";
            $message = "A customer has indicated they've made a bank transfer payment.\n\n";
            $message .= "Order ID: #$orderId\n";
            $message .= "Amount: ₦" . number_format($order['final_amount'], 2) . "\n";
            $message .= "Customer Email: " . $order['customer_email'] . "\n\n";
            $message .= "Please verify the payment in your bank account and update the order status.";
            
            if (function_exists('sendAdminNotification')) {
                sendAdminNotification($subject, $message);
            }
        } catch (Exception $e) {
            // Log but don't fail
            error_log("Failed to send admin notification for order #$orderId: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'Payment notification sent']);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$customerId = $customer['customer_id'];
$orderId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($orderId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$db = getDb();

$stmt = $db->prepare("
    SELECT 
        po.id,
        po.created_at,
        po.status,
        po.payment_method,
        po.original_price,
        po.discount_amount,
        po.final_amount,
        po.affiliate_code,
        po.delivery_status
    FROM pending_orders po
    WHERE po.id = ? AND po.customer_id = ?
");
$stmt->execute([$orderId, $customerId]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

$itemsStmt = $db->prepare("
    SELECT 
        oi.product_type,
        oi.product_id,
        oi.template_name,
        oi.tool_name,
        oi.price,
        oi.quantity,
        CASE 
            WHEN oi.product_type = 'template' THEN t.thumbnail_url
            ELSE tl.thumbnail_url
        END as thumbnail_url
    FROM order_items oi
    LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
    LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
    WHERE oi.pending_order_id = ?
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

$deliveriesStmt = $db->prepare("
    SELECT 
        d.id,
        d.product_type,
        d.product_id,
        d.product_name,
        d.delivery_status,
        d.hosted_domain,
        COALESCE(d.hosted_url, d.template_login_url) as domain_login_url,
        d.delivered_at,
        d.delivery_link
    FROM deliveries d
    WHERE d.pending_order_id = ?
");
$deliveriesStmt->execute([$orderId]);
$deliveries = $deliveriesStmt->fetchAll(PDO::FETCH_ASSOC);

$deliveryMap = [];
foreach ($deliveries as $delivery) {
    $key = $delivery['product_type'] . '_' . $delivery['product_id'];
    $deliveryMap[$key] = [
        'status' => $delivery['delivery_status'],
        'hosted_domain' => $delivery['hosted_domain'],
        'login_url' => $delivery['domain_login_url'],
        'delivered_at' => $delivery['delivered_at'],
        'download_links' => $delivery['delivery_link'] ? json_decode($delivery['delivery_link'], true) : null
    ];
}

$formattedItems = array_map(function($item) use ($deliveryMap) {
    $key = $item['product_type'] . '_' . $item['product_id'];
    $delivery = $deliveryMap[$key] ?? null;
    
    return [
        'product_type' => $item['product_type'],
        'product_id' => (int)$item['product_id'],
        'name' => $item['template_name'] ?: $item['tool_name'],
        'price' => (float)$item['price'],
        'quantity' => (int)$item['quantity'],
        'thumbnail_url' => $item['thumbnail_url'],
        'delivery' => $delivery
    ];
}, $items);

$timeline = [];
if (function_exists('getOrderTimeline')) {
    $timeline = getOrderTimeline($orderId, $customerId);
} else {
    $timeline[] = [
        'event' => 'order_created',
        'timestamp' => $order['created_at'],
        'description' => 'Order placed'
    ];
    
    if ($order['status'] === 'paid') {
        $timeline[] = [
            'event' => 'payment_confirmed',
            'timestamp' => $order['created_at'],
            'description' => 'Payment confirmed'
        ];
    }
    
    foreach ($deliveries as $d) {
        if ($d['delivered_at']) {
            $timeline[] = [
                'event' => 'delivery_delivered',
                'timestamp' => $d['delivered_at'],
                'description' => ($d['product_name'] ?? 'Item') . ' delivered'
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'order' => [
        'id' => (int)$order['id'],
        'created_at' => $order['created_at'],
        'status' => $order['status'],
        'payment_method' => $order['payment_method'],
        'original_price' => (float)$order['original_price'],
        'discount_amount' => (float)$order['discount_amount'],
        'final_amount' => (float)$order['final_amount'],
        'affiliate_code' => $order['affiliate_code'],
        'delivery_status' => $order['delivery_status']
    ],
    'items' => $formattedItems,
    'timeline' => $timeline
]);
