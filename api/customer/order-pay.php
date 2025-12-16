<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/paystack.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $customer = getCurrentCustomer();
    if (!$customer) {
        throw new Exception('Please log in to continue');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['order_id'])) {
        throw new Exception('Order ID is required');
    }
    
    $orderId = (int)$input['order_id'];
    $action = $input['action'] ?? 'pay_with_paystack';
    
    $db = getDb();
    
    $stmt = $db->prepare("SELECT * FROM pending_orders WHERE id = ? AND customer_id = ? AND status IN ('pending', 'failed')");
    $stmt->execute([$orderId, $customer['id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found or already paid');
    }
    
    if ($action === 'pay_with_paystack') {
        $updateStmt = $db->prepare("UPDATE pending_orders SET payment_method = 'paystack', updated_at = datetime('now') WHERE id = ?");
        $updateStmt->execute([$orderId]);
        
        $paymentData = initializePayment([
            'order_id' => $orderId,
            'customer_name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'amount' => $order['final_amount'],
            'currency' => 'NGN',
            'callback_url' => SITE_URL . '/user/order-detail.php?id=' . $orderId
        ]);
        
        if (!$paymentData['success']) {
            throw new Exception($paymentData['message'] ?? 'Payment initialization failed');
        }
        
        echo json_encode([
            'success' => true,
            'authorization_url' => $paymentData['authorization_url'],
            'reference' => $paymentData['reference'],
            'access_code' => $paymentData['access_code'],
            'public_key' => PAYSTACK_PUBLIC_KEY
        ]);
        
    } elseif ($action === 'retry_payment') {
        $paymentStmt = $db->prepare("SELECT * FROM payments WHERE pending_order_id = ? ORDER BY created_at DESC LIMIT 1");
        $paymentStmt->execute([$orderId]);
        $lastPayment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lastPayment) {
            $db->prepare("UPDATE payments SET retry_count = retry_count + 1, updated_at = datetime('now') WHERE id = ?")
               ->execute([$lastPayment['id']]);
        }
        
        $db->prepare("UPDATE pending_orders SET status = 'pending', updated_at = datetime('now') WHERE id = ?")
           ->execute([$orderId]);
        
        $paymentData = initializePayment([
            'order_id' => $orderId,
            'customer_name' => $order['customer_name'],
            'email' => $order['customer_email'],
            'amount' => $order['final_amount'],
            'currency' => 'NGN',
            'callback_url' => SITE_URL . '/user/order-detail.php?id=' . $orderId
        ]);
        
        if (!$paymentData['success']) {
            throw new Exception($paymentData['message'] ?? 'Payment retry failed');
        }
        
        echo json_encode([
            'success' => true,
            'authorization_url' => $paymentData['authorization_url'],
            'reference' => $paymentData['reference'],
            'access_code' => $paymentData['access_code'],
            'public_key' => PAYSTACK_PUBLIC_KEY,
            'message' => 'Payment retry initialized'
        ]);
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
