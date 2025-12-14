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

$stmt = $db->prepare("
    SELECT 
        dt.id as token_id,
        dt.token,
        dt.download_count,
        dt.max_downloads,
        dt.expires_at,
        dt.pending_order_id as order_id,
        dt.is_bundle,
        tf.id as file_id,
        tf.file_name,
        tf.file_size,
        tf.file_type,
        tf.tool_id,
        t.name as tool_name,
        t.thumbnail_url as tool_thumbnail
    FROM download_tokens dt
    JOIN pending_orders po ON dt.pending_order_id = po.id
    JOIN tool_files tf ON dt.file_id = tf.id
    JOIN tools t ON tf.tool_id = t.id
    WHERE po.customer_id = ? AND po.status = 'paid'
    ORDER BY t.name ASC, tf.file_name ASC
");
$stmt->execute([$customerId]);
$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$downloads = [];
$toolGroups = [];

foreach ($tokens as $token) {
    $toolId = $token['tool_id'];
    $now = time();
    $expiresAt = strtotime($token['expires_at']);
    $isExpired = $expiresAt < $now || $token['download_count'] >= $token['max_downloads'];
    
    if (!isset($toolGroups[$toolId])) {
        $toolGroups[$toolId] = [
            'order_id' => (int)$token['order_id'],
            'tool_id' => (int)$toolId,
            'tool_name' => $token['tool_name'],
            'tool_thumbnail' => $token['tool_thumbnail'],
            'files' => []
        ];
    }
    
    $toolGroups[$toolId]['files'][] = [
        'file_id' => (int)$token['file_id'],
        'file_name' => $token['file_name'],
        'file_size' => (int)$token['file_size'],
        'token' => $token['token'],
        'token_id' => (int)$token['token_id'],
        'download_count' => (int)$token['download_count'],
        'max_downloads' => (int)$token['max_downloads'],
        'expires_at' => $token['expires_at'],
        'is_expired' => $isExpired,
        'is_bundle' => (bool)$token['is_bundle']
    ];
}

$downloads = array_values($toolGroups);

echo json_encode([
    'success' => true,
    'downloads' => $downloads
]);
