<?php
/**
 * Check Payment Status API
 * Allows frontend to check if a payment was already processed (e.g., by webhook)
 * This helps recover from network issues during verification
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $orderId = !empty($input['order_id']) ? (int)$input['order_id'] : null;
    $reference = !empty($input['reference']) ? trim($input['reference']) : null;
    
    if (!$orderId && !$reference) {
        throw new Exception('Order ID or payment reference required');
    }
    
    $db = getDb();
    
    $order = null;
    $payment = null;
    
    if ($orderId) {
        $stmt = $db->prepare("SELECT id, status, customer_email, final_amount, session_id FROM pending_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if ($reference) {
        $stmt = $db->prepare("SELECT id, pending_order_id, status, amount_paid FROM payments WHERE paystack_reference = ?");
        $stmt->execute([$reference]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($payment && !$order) {
            $stmt = $db->prepare("SELECT id, status, customer_email, final_amount, session_id FROM pending_orders WHERE id = ?");
            $stmt->execute([$payment['pending_order_id']]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$order) {
        echo json_encode([
            'success' => false,
            'status' => 'not_found',
            'message' => 'Order not found'
        ]);
        exit;
    }
    
    $isOwner = ($order['session_id'] === session_id());
    
    if ($order['status'] === 'paid') {
        echo json_encode([
            'success' => true,
            'status' => 'paid',
            'order_id' => $order['id'],
            'message' => 'Payment has been verified successfully',
            'can_view' => $isOwner
        ]);
    } elseif ($order['status'] === 'failed') {
        echo json_encode([
            'success' => false,
            'status' => 'failed',
            'order_id' => $order['id'],
            'message' => 'Payment verification failed',
            'can_retry' => true
        ]);
    } elseif ($order['status'] === 'pending') {
        echo json_encode([
            'success' => false,
            'status' => 'pending',
            'order_id' => $order['id'],
            'message' => 'Payment is still being processed',
            'can_check_again' => true
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'status' => $order['status'],
            'order_id' => $order['id'],
            'message' => 'Order status: ' . $order['status']
        ]);
    }
    
} catch (Exception $e) {
    error_log("CHECK PAYMENT STATUS ERROR: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
