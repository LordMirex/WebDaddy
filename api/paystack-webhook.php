<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

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
    
    // FALLBACK: If payment record not found, try to extract order ID from reference and create it
    if (!$payment) {
        $orderId = extractOrderIdFromReference($reference);
        if ($orderId) {
            $db = getDb();
            
            // Check if order exists and is pending
            $stmt = $db->prepare("SELECT id, status, final_amount FROM pending_orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order && $order['status'] === 'pending') {
                // Create payment record on the fly
                $stmt = $db->prepare("
                    INSERT INTO payments (
                        pending_order_id, payment_method, amount_requested, currency,
                        paystack_reference, status, created_at
                    ) VALUES (?, 'paystack', ?, 'NGN', ?, 'pending', datetime('now', '+1 hour'))
                ");
                $stmt->execute([$orderId, $order['final_amount'], $reference]);
                $paymentId = $db->lastInsertId();
                
                error_log("🔧 WEBHOOK: Created fallback payment #$paymentId for Order #$orderId");
                
                // Fetch the newly created payment
                $payment = getPaymentByReference($reference);
            } elseif ($order && $order['status'] === 'paid') {
                logPaymentEvent('order_already_paid', 'paystack', 'info', $orderId, null, null, $data);
                return;
            }
        }
    }
    
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
        $affectedRows = $stmt->rowCount();
        
        // IDEMPOTENCY: If 0 rows affected, order was already processed by verify endpoint
        if ($affectedRows === 0) {
            $db->rollBack();
            error_log("✅ WEBHOOK: Order #{$payment['pending_order_id']} already processed (0 affected rows). Skipping to avoid duplicates.");
            logPaymentEvent('order_already_processed', 'paystack', 'info', $payment['pending_order_id'], $payment['id'], null, $data);
            return;
        }
        
        // Commit transaction
        $db->commit();
        
        // CRITICAL FIX: Send emails in CORRECT order - Confirmation FIRST, then tool delivery
        // This ensures customers receive order confirmation before product files
        
        // Send notification emails for automatic payment
        try {
            $order = getOrderById($payment['pending_order_id']);
            if ($order) {
                $orderItems = getOrderItems($payment['pending_order_id']);
                $productNames = [];
                if (!empty($orderItems)) {
                    foreach ($orderItems as $item) {
                        $name = $item['product_type'] === 'template' ? $item['template_name'] : $item['tool_name'];
                        $productNames[] = $name;
                    }
                } elseif (!empty($order['template_name'])) {
                    $productNames[] = $order['template_name'];
                } elseif (!empty($order['tool_name'])) {
                    $productNames[] = $order['tool_name'];
                }
                
                // Step 1: Send payment confirmation email to CUSTOMER FIRST
                if (!empty($order['customer_email'])) {
                    $customerEmailSent = sendEnhancedPaymentConfirmationEmail($order, $orderItems);
                    if ($customerEmailSent) {
                        error_log("✅ WEBHOOK: Customer payment confirmation sent to {$order['customer_email']} for Order #{$payment['pending_order_id']}");
                    } else {
                        error_log("⚠️  WEBHOOK: Failed to send customer payment confirmation for Order #{$payment['pending_order_id']}");
                    }
                }
                
                // Step 2: Send admin notification email
                sendPaymentSuccessNotificationToAdmin(
                    $payment['pending_order_id'],
                    $order['customer_name'],
                    $order['customer_phone'],
                    implode(', ', $productNames),
                    formatCurrency($order['final_amount'] ?? $data['amount'] / 100),
                    $order['affiliate_code'] ?? null,
                    $order['order_type'] ?? 'template'
                );
                error_log("✅ WEBHOOK: Admin payment notification sent for Order #{$payment['pending_order_id']}");
            }
        } catch (Exception $emailEx) {
            error_log("⚠️  WEBHOOK: Failed to send notification emails: " . $emailEx->getMessage());
        }
        
        // Step 3: Create delivery records
        createDeliveryRecords($payment['pending_order_id']);
        
        // Step 4: Send tool delivery emails AFTER confirmation (if files are ready)
        // FIXED: This was missing - now matches paystack-verify.php behavior
        sendAllToolDeliveryEmailsForOrder($payment['pending_order_id']);
        
        // Process commission and create sales record for revenue tracking
        // This also sends affiliate commission notification emails
        processOrderCommission($payment['pending_order_id']);
        
        logPaymentEvent('payment_completed', 'paystack', 'success', $payment['pending_order_id'], $payment['id'], null, $data);
        
    } catch (Exception $e) {
        $db->rollBack();
        logPaymentEvent('payment_processing_failed', 'paystack', 'error', $payment['pending_order_id'], $payment['id'], null, ['error' => $e->getMessage(), 'data' => $data]);
        sendWebhookFailureAlert('Payment processing failed: ' . $e->getMessage(), $data);
    }
}

function handleFailedPayment($data) {
    $reference = $data['reference'];
    
    // Find payment record
    $payment = getPaymentByReference($reference);
    
    // FALLBACK: If payment record not found, try to extract order ID from reference and create it
    if (!$payment) {
        $orderId = extractOrderIdFromReference($reference);
        
        if ($orderId) {
            $db = getDb();
            
            // Check if order exists
            $stmt = $db->prepare("SELECT id, status, final_amount FROM pending_orders WHERE id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order) {
                // Don't process if already paid or failed
                if ($order['status'] === 'paid') {
                    logPaymentEvent('order_already_paid', 'paystack', 'info', $orderId, null, null, $data);
                    return;
                }
                
                if ($order['status'] === 'failed') {
                    logPaymentEvent('order_already_failed', 'paystack', 'info', $orderId, null, null, $data);
                    return;
                }
                
                // Create payment record on the fly with status='failed'
                $stmt = $db->prepare("
                    INSERT INTO payments (
                        pending_order_id, payment_method, amount_requested, currency,
                        paystack_reference, status, paystack_response, created_at
                    ) VALUES (?, 'paystack', ?, 'NGN', ?, 'failed', ?, datetime('now', '+1 hour'))
                ");
                $stmt->execute([
                    $orderId, 
                    $order['final_amount'], 
                    $reference,
                    json_encode($data)
                ]);
                $paymentId = $db->lastInsertId();
                
                error_log("🔧 WEBHOOK: Created fallback FAILED payment #$paymentId for Order #$orderId");
                
                // Update order status to failed
                $stmt = $db->prepare("
                    UPDATE pending_orders 
                    SET status = 'failed',
                        updated_at = datetime('now', '+1 hour')
                    WHERE id = ? AND status = 'pending'
                ");
                $stmt->execute([$orderId]);
                $affectedRows = $stmt->rowCount();
                
                if ($affectedRows > 0) {
                    error_log("❌ WEBHOOK: Order #$orderId marked as failed");
                }
                
                // Log the payment failure event
                logPaymentEvent('payment_failed', 'paystack', 'failed', $orderId, $paymentId, null, $data);
                return;
            }
        }
        
        // If we still can't find/create a payment, log the orphan failed payment
        error_log("⚠️ WEBHOOK: Failed payment received but no matching order found. Reference: $reference");
        logPaymentEvent('payment_failed_orphan', 'paystack', 'failed', null, null, null, [
            'reference' => $reference,
            'reason' => 'No matching order found',
            'data' => $data
        ]);
        return;
    }
    
    $db = getDb();
    
    // Update existing payment record
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
    
    // Also update order status to failed
    $stmt = $db->prepare("
        UPDATE pending_orders 
        SET status = 'failed',
            updated_at = datetime('now', '+1 hour')
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$payment['pending_order_id']]);
    $affectedRows = $stmt->rowCount();
    
    if ($affectedRows > 0) {
        error_log("❌ WEBHOOK: Order #{$payment['pending_order_id']} marked as failed (existing payment)");
    }
    
    logPaymentEvent('payment_failed', 'paystack', 'failed', $payment['pending_order_id'], $payment['id'], null, $data);
}

/**
 * Extract order ID from payment reference
 * Reference format: ORDER-{orderId}-{timestamp}-{random}
 */
function extractOrderIdFromReference($reference) {
    if (preg_match('/^ORDER-(\d+)-/', $reference, $matches)) {
        return (int)$matches[1];
    }
    return null;
}
