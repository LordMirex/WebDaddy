<?php
/**
 * Customer Notifications API
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_session.php';

header('Content-Type: application/json');

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$db = getDb();

$tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='customer_notifications'");
if (!$tableCheck->fetch()) {
    $db->exec("
        CREATE TABLE customer_notifications (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            customer_id INTEGER NOT NULL,
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            data TEXT,
            priority VARCHAR(20) DEFAULT 'normal',
            is_read INTEGER DEFAULT 0,
            read_at TEXT,
            expires_at TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id)
        )
    ");
    $db->exec("CREATE INDEX idx_notifications_customer ON customer_notifications(customer_id)");
    $db->exec("CREATE INDEX idx_notifications_unread ON customer_notifications(customer_id, is_read)");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'mark_read' && !empty($input['id'])) {
        $db->prepare("
            UPDATE customer_notifications 
            SET is_read = 1, read_at = datetime('now')
            WHERE id = ? AND customer_id = ?
        ")->execute([$input['id'], $customer['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'mark_all_read') {
        $db->prepare("
            UPDATE customer_notifications 
            SET is_read = 1, read_at = datetime('now')
            WHERE customer_id = ? AND is_read = 0
        ")->execute([$customer['id']]);
        echo json_encode(['success' => true]);
        exit;
    }
}

$stmt = $db->prepare("
    SELECT * FROM customer_notifications 
    WHERE customer_id = ?
    AND (expires_at IS NULL OR expires_at > datetime('now'))
    AND is_read = 0
    ORDER BY 
        CASE priority WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END,
        created_at DESC
    LIMIT 20
");
$stmt->execute([$customer['id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$countStmt = $db->prepare("
    SELECT COUNT(*) FROM customer_notifications 
    WHERE customer_id = ? AND is_read = 0
    AND (expires_at IS NULL OR expires_at > datetime('now'))
");
$countStmt->execute([$customer['id']]);
$unreadCount = $countStmt->fetchColumn();

echo json_encode([
    'notifications' => $notifications,
    'unread_count' => (int)$unreadCount
]);
