<?php
/**
 * Email Queue System
 * Reliable email delivery with retry logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Queue an email
 */
function queueEmail($recipientEmail, $emailType, $subject, $body, $htmlBody = null, $orderId = null, $deliveryId = null) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO email_queue (
            recipient_email, email_type, pending_order_id, delivery_id,
            subject, body, html_body
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $recipientEmail,
        $emailType,
        $orderId,
        $deliveryId,
        $subject,
        $body,
        $htmlBody
    ]);
    
    return $db->lastInsertId();
}

/**
 * Queue tool delivery email
 */
function queueToolDeliveryEmail($deliveryId) {
    $db = getDb();
    
    // Get delivery and order info
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, po.id as order_id
        FROM deliveries d
        INNER JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) return false;
    
    // Build email
    $subject = "Your {$delivery['product_name']} is Ready! - Order #{$delivery['order_id']}";
    
    $body = "Hi {$delivery['customer_name']},\n\n";
    $body .= "Great news! Your tool is ready for download.\n\n";
    $body .= "📦 Product: {$delivery['product_name']}\n";
    $body .= "📋 Order ID: #{$delivery['order_id']}\n\n";
    
    // Add download links
    $links = json_decode($delivery['delivery_link'], true);
    if ($links) {
        $body .= "📥 Download Your Files:\n\n";
        foreach ($links as $link) {
            $body .= "• {$link['name']}: {$link['url']}\n";
        }
    }
    
    $body .= "\n{$delivery['delivery_note']}\n\n";
    $body .= "Need help? Reply to this email or contact us on WhatsApp.\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    return queueEmail(
        $delivery['customer_email'],
        'tools_ready',
        $subject,
        $body,
        null,
        $delivery['order_id'],
        $deliveryId
    );
}

/**
 * Queue template pending email
 */
function queueTemplatePendingEmail($deliveryId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, po.id as order_id
        FROM deliveries d
        INNER JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) return false;
    
    $subject = "Your Template is Being Prepared - Order #{$delivery['order_id']}";
    
    $body = "Hi {$delivery['customer_name']},\n\n";
    $body .= "Thank you for your order! 🎉\n\n";
    $body .= "📦 Template: {$delivery['product_name']}\n";
    $body .= "📋 Order ID: #{$delivery['order_id']}\n";
    $body .= "⏱️ Ready in: 24 hours\n\n";
    $body .= "We're setting up your template with premium hosting and SSL certificate.\n";
    $body .= "You'll receive another email with your access link when it's ready!\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    return queueEmail(
        $delivery['customer_email'],
        'template_ready',
        $subject,
        $body,
        null,
        $delivery['order_id'],
        $deliveryId
    );
}

/**
 * Queue template ready email
 */
function queueTemplateReadyEmail($deliveryId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, po.id as order_id
        FROM deliveries d
        INNER JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) return false;
    
    $subject = "🎉 Your Template is Ready! - {$delivery['product_name']}";
    
    $body = "Hi {$delivery['customer_name']},\n\n";
    $body .= "Your template is now live and ready to use!\n\n";
    $body .= "🔗 Access URL: {$delivery['hosted_url']}\n";
    $body .= "📧 Login Email: {$delivery['customer_email']}\n\n";
    $body .= "Need help getting started? We're here for you!\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    return queueEmail(
        $delivery['customer_email'],
        'template_ready',
        $subject,
        $body,
        null,
        $delivery['order_id'],
        $deliveryId
    );
}

/**
 * Process email queue
 * This should be called by a cron job every few minutes
 */
function processEmailQueue() {
    $db = getDb();
    
    // Get pending emails
    $stmt = $db->query("
        SELECT * FROM email_queue 
        WHERE status = 'pending' AND attempts < max_attempts
        ORDER BY scheduled_at ASC
        LIMIT 10
    ");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emails as $email) {
        try {
            // TODO: Implement actual email sending using PHPMailer or mail()
            // For now, we'll just mark as sent
            // $sent = sendEmail($email['recipient_email'], $email['subject'], $email['body'], $email['html_body']);
            
            $sent = true; // Placeholder
            
            if ($sent) {
                // Mark as sent
                $updateStmt = $db->prepare("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = datetime('now')
                    WHERE id = ?
                ");
                $updateStmt->execute([$email['id']]);
            } else {
                throw new Exception('Failed to send email');
            }
        } catch (Exception $e) {
            // Increment attempts
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET attempts = attempts + 1, 
                    last_error = ?,
                    status = CASE WHEN attempts + 1 >= max_attempts THEN 'failed' ELSE 'retry' END
                WHERE id = ?
            ");
            $updateStmt->execute([$e->getMessage(), $email['id']]);
        }
    }
}
