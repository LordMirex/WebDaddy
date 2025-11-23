<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/cart.php';

startSecureSession();
header('Content-Type: application/json');

try {
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $db->prepare('
        SELECT id, cart_snapshot FROM draft_orders 
        WHERE ip_address = ? AND created_at > datetime("now", "-7 days")
        ORDER BY created_at DESC
        LIMIT 1
    ');
    
    $stmt->execute([$ip]);
    $draft = $stmt->fetch();
    
    if (!$draft) {
        echo json_encode(['success' => false, 'message' => 'No saved cart found']);
        exit;
    }
    
    $draftData = json_decode($draft['cart_snapshot'], true);
    
    if (!$draftData || empty($draftData['cart_items'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid saved cart']);
        exit;
    }
    
    $_SESSION['cart'] = $draftData['cart_items'];
    if (!empty($draftData['affiliate_code'])) {
        $_SESSION['affiliate_code'] = $draftData['affiliate_code'];
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cart restored',
        'items_count' => count($draftData['cart_items']),
        'draft_id' => $draft['id']
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
