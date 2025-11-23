<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/cart.php';

startSecureSession();
header('Content-Type: application/json');

try {
    $cartItems = getCart();
    $affiliateCode = getAffiliateCode();
    
    if (empty($cartItems)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }
    
    $draftData = [
        'cart_items' => $cartItems,
        'affiliate_code' => $affiliateCode,
        'saved_at' => date('Y-m-d H:i:s'),
        'session_id' => session_id(),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];
    
    $db = getDb();
    
    $stmt = $db->prepare('
        INSERT INTO draft_orders (cart_snapshot, session_id, ip_address, created_at)
        VALUES (?, ?, ?, CURRENT_TIMESTAMP)
    ');
    
    $result = $stmt->execute([
        json_encode($draftData),
        session_id(),
        $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    if (!$result) {
        throw new Exception('Failed to auto-save cart');
    }
    
    $draftId = $db->lastInsertId();
    echo json_encode([
        'success' => true,
        'draft_id' => $draftId,
        'message' => 'Cart auto-saved'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
