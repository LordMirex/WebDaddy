<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_session.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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

$status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(50, max(1, intval($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$whereClause = "WHERE po.customer_id = ?";
$params = [$customerId];

if ($status !== 'all' && in_array($status, ['pending', 'paid', 'completed', 'cancelled', 'failed'])) {
    $whereClause .= " AND po.status = ?";
    $params[] = $status;
}

$countStmt = $db->prepare("
    SELECT COUNT(DISTINCT po.id) as total
    FROM pending_orders po
    $whereClause
");
$countStmt->execute($params);
$totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalItems / $limit);

$stmt = $db->prepare("
    SELECT 
        po.id,
        po.created_at,
        po.status,
        po.final_amount as total,
        po.delivery_status,
        COUNT(oi.id) as item_count,
        GROUP_CONCAT(COALESCE(oi.template_name, oi.tool_name), ', ') as items_preview
    FROM pending_orders po
    LEFT JOIN order_items oi ON po.id = oi.pending_order_id
    $whereClause
    GROUP BY po.id
    ORDER BY po.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $limit;
$params[] = $offset;
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$formattedOrders = array_map(function($order) {
    $preview = $order['items_preview'] ? explode(', ', $order['items_preview']) : [];
    $preview = array_slice(array_filter($preview), 0, 3);
    
    return [
        'id' => (int)$order['id'],
        'created_at' => $order['created_at'],
        'status' => $order['status'],
        'total' => (float)$order['total'],
        'item_count' => (int)$order['item_count'],
        'items_preview' => $preview,
        'delivery_status' => $order['delivery_status']
    ];
}, $orders);

echo json_encode([
    'success' => true,
    'orders' => $formattedOrders,
    'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_items' => (int)$totalItems
    ]
]);
