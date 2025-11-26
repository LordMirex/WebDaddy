<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/email_queue.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['reference']) || empty($input['order_id'])) {
        throw new Exception('Missing required parameters: reference=' . ($input['reference'] ?? 'EMPTY') . ', order_id=' . ($input['order_id'] ?? 'EMPTY'));
    }
    
    $orderId = (int)$input['order_id'];
    $reference = $input['reference'];
    
    error_log("🔍 PAYSTACK VERIFY: Starting verification for Order #$orderId, Ref: $reference");
    
    // Verify payment with Paystack
    $verification = verifyPayment($reference);
    
    error_log("🔍 PAYSTACK VERIFY: Verification result: " . json_encode($verification));
    
    $db = getDb();
    
    // Get order details for notifications
    $stmt = $db->prepare("SELECT id, customer_name, customer_phone, affiliate_code, original_price, final_amount, status FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    error_log("🔍 PAYSTACK VERIFY: Order details: " . json_encode($order));
    
    if (!$order) {
        throw new Exception('Order #' . $orderId . ' not found');
    }
    
    // Get order items (FIXED: use pending_order_id not order_id)
    $stmt = $db->prepare("
        SELECT 
            COALESCE(t.name, tl.name) as name, 
            oi.product_type,
            COUNT(*) as qty
        FROM order_items oi
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
        WHERE oi.pending_order_id = ?
        GROUP BY oi.product_id, oi.product_type
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("🔍 PAYSTACK VERIFY: Order items found: " . count($items));
    
    $productNames = implode(', ', array_map(function($item) {
        return $item['name'] . ($item['qty'] > 1 ? ' (x' . $item['qty'] . ')' : '');
    }, $items));
    
    // Determine order type
    $hasTemplates = false;
    $hasTools = false;
    foreach ($items as $item) {
        if ($item['product_type'] === 'template') $hasTemplates = true;
        if ($item['product_type'] === 'tool') $hasTools = true;
    }
    
    $orderType = 'template';
    if ($hasTemplates && $hasTools) {
        $orderType = 'mixed';
    } elseif ($hasTemplates) {
        $orderType = 'template';
    } else {
        $orderType = 'tool';
    }
    
    // SECURITY: Use transaction to prevent race conditions
    $db->beginTransaction();
    
    try {
        // Check current status
        $stmt = $db->prepare("SELECT status FROM pending_orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $currentOrder = $stmt->fetch(PDO::FETCH_ASSOC);
        
        error_log("🔍 PAYSTACK VERIFY: Current order status: " . ($currentOrder['status'] ?? 'NOT_FOUND'));
        
        if ($currentOrder && $currentOrder['status'] === 'paid') {
            // Already processed
            $db->rollBack();
            error_log("✅ PAYSTACK VERIFY: Order #$orderId already marked as paid");
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'message' => 'Payment already verified'
            ]);
            exit;
        }
        
        if ($verification['success']) {
            // PAYMENT SUCCEEDED: Mark order as PAID
            error_log("✅ PAYSTACK VERIFY: Payment succeeded! Marking order as PAID");
            
            $stmt = $db->prepare("
                UPDATE pending_orders 
                SET status = 'paid', 
                    payment_verified_at = datetime('now'),
                    payment_method = 'paystack'
                WHERE id = ? AND status IN ('pending', 'failed')
            ");
            $updateResult = $stmt->execute([$orderId]);
            $affectedRows = $stmt->rowCount();
            
            error_log("✅ PAYSTACK VERIFY: Update executed. Affected rows: " . $affectedRows);
            
            $db->commit();
            
            error_log("✅ PAYSTACK VERIFY: Transaction committed");
            
            // Send admin notification about successful payment (outside transaction)
            error_log("✅ PAYSTACK VERIFY: Sending admin notification");
            sendPaymentSuccessNotificationToAdmin(
                $orderId,
                $order['customer_name'],
                $order['customer_phone'],
                $productNames,
                formatCurrency($order['final_amount']),
                $order['affiliate_code'],
                $orderType
            );
            
            // Create delivery records
            error_log("✅ PAYSTACK VERIFY: Creating delivery records");
            try {
                createDeliveryRecords($orderId);
                error_log("✅ PAYSTACK VERIFY: Delivery records created successfully");
            } catch (Exception $deliveryError) {
                error_log("❌ PAYSTACK VERIFY: Error creating delivery records: " . $deliveryError->getMessage());
                // Don't fail the entire payment - just log it
                // Delivery can be created manually or retried later
            }
            
            // Queue customer confirmation and affiliate emails - PROCESS IMMEDIATELY
            error_log("✅ PAYSTACK VERIFY: Queueing confirmation emails");
            
            // Queue payment confirmation email to customer
            if (!empty($order['customer_email'])) {
                queueEmail(
                    $order['customer_email'],
                    'payment_confirmed',
                    '✅ Payment Confirmed - Your Order #' . $orderId,
                    'Your payment has been confirmed. Your order is being processed.',
                    null,
                    $orderId
                );
                error_log("✅ PAYSTACK VERIFY: Payment confirmation email queued for: " . $order['customer_email']);
                
                // Queue affiliate invitation if applicable
                if (empty($order['affiliate_code']) && !isEmailAffiliate($order['customer_email'])) {
                    if (!hasAffiliateInvitationBeenSent($order['customer_email'])) {
                        sendAffiliateOpportunityEmail($order['customer_name'], $order['customer_email']);
                        error_log("✅ PAYSTACK VERIFY: Affiliate invitation email queued");
                    }
                }
            }
            
            // CRITICAL: Process queued emails immediately so they get sent
            processEmailQueue();
            error_log("✅ PAYSTACK VERIFY: Queued emails processed");
            
            clearCart();
            
            error_log("✅ PAYSTACK VERIFY: Order #$orderId complete! Payment verified, deliveries created");
            
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'message' => 'Payment verified successfully'
            ], JSON_UNESCAPED_SLASHES);
        } else {
            // PAYMENT FAILED: Mark order as FAILED
            $failureReason = $verification['message'] ?? 'Payment verification failed';
            
            error_log("❌ PAYSTACK VERIFY: Payment failed: " . $failureReason);
            
            $stmt = $db->prepare("
                UPDATE pending_orders 
                SET status = 'failed',
                    payment_verified_at = datetime('now'),
                    payment_method = 'paystack'
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            $db->commit();
            
            // Send admin notification about failed payment (outside transaction)
            sendPaymentFailureNotificationToAdmin(
                $orderId,
                $order['customer_name'],
                $order['customer_phone'],
                $productNames,
                formatCurrency($order['final_amount']),
                $failureReason,
                $order['affiliate_code'],
                $orderType
            );
            
            throw new Exception('Payment verification failed: ' . $failureReason);
        }
        
    } catch (Exception $e) {
        error_log("❌ PAYSTACK VERIFY: Exception in transaction: " . $e->getMessage());
        $db->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log("❌ PAYSTACK VERIFY: Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
