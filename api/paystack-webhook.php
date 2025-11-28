<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/security.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get raw POST body
$input = @file_get_contents("php://input");

// Get signature from header
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// SECURITY: Perform all security checks (IP whitelist, rate limit, signature)
$securityCheck = performWebhookSecurityCheck($input, $signature);

if (!$securityCheck['passed']) {
    http_response_code(401);
    logPaymentEvent('webhook_security_failed', 'paystack', 'blocked', null, null, null, [
        'reason' => $securityCheck['reason'],
        'ip' => getClientIP()
    ]);
    sendWebhookFailureAlert($securityCheck['reason']);
    exit('Security check failed: ' . $securityCheck['reason']);
}

// Parse webhook data
$event = json_decode($input, true);

// Log the event
logPaymentEvent('webhook_received', 'paystack', 'received', null, null, null, $event);

// Handle different event types
switch ($event['event']) {
    case 'charge.success':
        handleSuccessfulPayment($event['data']);
        break;
        
    case 'charge.failed':
        handleFailedPayment($event['data']);
        break;
        
    default:
        // Log but don't process
        logPaymentEvent('webhook_ignored', 'paystack', 'ignored', null, null, null, ['event' => $event['event']]);
}

http_response_code(200);
echo json_encode(['status' => 'success']);

function handleSuccessfulPayment($data) {
    $reference = $data['reference'];
    
    // Find payment record
    $payment = getPaymentByReference($reference);
    if (!$payment) {
        logPaymentEvent('payment_not_found', 'paystack', 'error', null, null, null, ['reference' => $reference]);
        return;
    }
    
    // IDEMPOTENCY: Already processed?
    if ($payment['status'] === 'completed') {
        logPaymentEvent('payment_already_completed', 'paystack', 'info', $payment['pending_order_id'], $payment['id'], null, $data);
        return;
    }
    
    $db = getDb();
    
    // SECURITY: Use transaction to prevent race conditions
    $db->beginTransaction();
    
    try {
        // IDEMPOTENCY: Check order status
        $stmt = $db->prepare("SELECT status FROM pending_orders WHERE id = ?");
        $stmt->execute([$payment['pending_order_id']]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // IDEMPOTENCY: Skip if already paid (prevent duplicate delivery)
        if ($order['status'] === 'paid') {
            $db->rollBack();
            logPaymentEvent('order_already_paid', 'paystack', 'info', $payment['pending_order_id'], $payment['id'], null, $data);
            return;
        }
        
        // Update payment record
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed',
                amount_paid = ?,
                paystack_response = ?,
                payment_verified_at = datetime('now', '+1 hour')
            WHERE id = ? AND status != 'completed'
        ");
        $stmt->execute([
            $data['amount'] / 100, // Convert from kobo
            json_encode($data),
            $payment['id']
        ]);
        
        // Update order status
        $stmt = $db->prepare("
            UPDATE pending_orders 
            SET status = 'paid',
                payment_verified_at = datetime('now', '+1 hour'),
                payment_method = 'paystack'
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$payment['pending_order_id']]);
        
        // Commit transaction before delivery
        $db->commit();
        
        // Trigger delivery (outside transaction)
        createDeliveryRecords($payment['pending_order_id']);
        
        // Send automatic tool delivery email with all tools ready for download
        error_log("✅ WEBHOOK: Sending tool delivery emails");
        sendAllToolDeliveryEmailsForOrder($payment['pending_order_id']);
        error_log("✅ WEBHOOK: Tool delivery emails sent");
        
        logPaymentEvent('payment_completed', 'paystack', 'success', $payment['pending_order_id'], $payment['id'], null, $data);
        
    } catch (Exception $e) {
        $db->rollBack();
        logPaymentEvent('payment_processing_failed', 'paystack', 'error', $payment['pending_order_id'], $payment['id'], null, ['error' => $e->getMessage(), 'data' => $data]);
        sendWebhookFailureAlert('Payment processing failed: ' . $e->getMessage(), $data);
    }
}

function handleFailedPayment($data) {
    $reference = $data['reference'];
    
    $payment = getPaymentByReference($reference);
    if (!$payment) {
        return;
    }
    
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE payments 
        SET status = 'failed',
            paystack_response = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($data),
        $payment['id']
    ]);
    
    logPaymentEvent('payment_failed', 'paystack', 'failed', $payment['pending_order_id'], $payment['id'], null, $data);
}
