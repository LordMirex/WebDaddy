<?php
/**
 * Email Queue System
 * Enhanced reliable email delivery with retry logic, priority handling, and bulk support
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

/**
 * Ensure email_queue table has the priority column (idempotent migration)
 * This ensures fresh deployments and existing deployments both work
 */
function ensureEmailQueueSchema() {
    static $checked = false;
    if ($checked) return;
    
    $db = getDb();
    
    try {
        // Check if priority column exists
        $result = $db->query("PRAGMA table_info(email_queue)")->fetchAll(PDO::FETCH_ASSOC);
        $hasPriority = false;
        foreach ($result as $column) {
            if ($column['name'] === 'priority') {
                $hasPriority = true;
                break;
            }
        }
        
        // Add priority column if it doesn't exist
        if (!$hasPriority) {
            $db->exec("ALTER TABLE email_queue ADD COLUMN priority INTEGER DEFAULT 5");
            error_log("📧 EMAIL QUEUE: Added priority column to email_queue table");
        }
        
        $checked = true;
    } catch (Exception $e) {
        // Silently fail - column might already exist or table not created yet
        error_log("EMAIL QUEUE schema check: " . $e->getMessage());
    }
}

// Run schema check on include
ensureEmailQueueSchema();

/**
 * Queue an email with optional priority
 * @param string $recipientEmail Recipient email address
 * @param string $emailType Type of email (order_confirmation, commission_earned, etc.)
 * @param string $subject Email subject
 * @param string $body Plain text body
 * @param string|null $htmlBody HTML body (optional, auto-generated from body if null)
 * @param int|null $orderId Related order ID (optional)
 * @param int|null $deliveryId Related delivery ID (optional)
 * @param string $priority Priority level: 'high', 'normal', 'low' (default: 'normal')
 * @return int|false Queue ID or false on failure
 */
function queueEmail($recipientEmail, $emailType, $subject, $body, $htmlBody = null, $orderId = null, $deliveryId = null, $priority = 'normal') {
    $db = getDb();
    
    // Priority order: high=1, normal=5, low=10 (lower number = higher priority)
    $priorityValues = ['high' => 1, 'normal' => 5, 'low' => 10];
    $priorityOrder = $priorityValues[$priority] ?? 5;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO email_queue (
                recipient_email, email_type, pending_order_id, delivery_id,
                subject, body, html_body, priority
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $recipientEmail,
            $emailType,
            $orderId,
            $deliveryId,
            $subject,
            $body,
            $htmlBody,
            $priorityOrder
        ]);
        
        return $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Failed to queue email: " . $e->getMessage());
        return false;
    }
}

/**
 * Queue multiple emails for bulk sending (e.g., announcements, newsletters)
 * @param array $emails Array of ['email' => 'x@y.com', 'name' => 'Name', 'subject' => '...', 'body' => '...']
 * @param string $emailType Type of email
 * @param string $priority Priority level
 * @return array ['queued' => count, 'failed' => count]
 */
function queueBulkEmails($emails, $emailType = 'announcement', $priority = 'low') {
    $db = getDb();
    $results = ['queued' => 0, 'failed' => 0];
    
    $priorityValues = ['high' => 1, 'normal' => 5, 'low' => 10];
    $priorityOrder = $priorityValues[$priority] ?? 10;
    
    $stmt = $db->prepare("
        INSERT INTO email_queue (
            recipient_email, email_type, subject, body, html_body, priority
        ) VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($emails as $email) {
        try {
            $htmlBody = $email['html_body'] ?? null;
            if (!$htmlBody && isset($email['body']) && isset($email['name'])) {
                $htmlBody = createEmailTemplate($email['subject'], $email['body'], $email['name']);
            }
            
            $stmt->execute([
                $email['email'],
                $emailType,
                $email['subject'],
                $email['body'] ?? '',
                $htmlBody,
                $priorityOrder
            ]);
            $results['queued']++;
        } catch (Exception $e) {
            error_log("Failed to queue bulk email to {$email['email']}: " . $e->getMessage());
            $results['failed']++;
        }
    }
    
    return $results;
}

/**
 * Queue a high-priority email (payment confirmations, order updates)
 * These are processed first
 */
function queueHighPriorityEmail($recipientEmail, $emailType, $subject, $htmlBody, $orderId = null) {
    return queueEmail($recipientEmail, $emailType, $subject, strip_tags($htmlBody), $htmlBody, $orderId, null, 'high');
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
 * Process email queue with priority and batch handling
 * @param int $batchSize Number of emails to process in one run (default: 25)
 * @param bool $aggressive If true, process more emails and retry immediately
 * @return array Processing stats
 */
function processEmailQueue($batchSize = 25, $aggressive = false) {
    $db = getDb();
    $stats = ['sent' => 0, 'failed' => 0, 'retrying' => 0];
    
    // Increase batch size in aggressive mode for bulk sending
    if ($aggressive) {
        $batchSize = min($batchSize * 2, 100);
    }
    
    // Get pending emails ordered by priority (lower number = higher priority)
    // Also include retrying emails for aggressive processing
    $statusCondition = $aggressive 
        ? "(status = 'pending' OR status = 'retry')"
        : "status = 'pending'";
    
    $stmt = $db->query("
        SELECT * FROM email_queue 
        WHERE {$statusCondition} AND attempts < max_attempts
        ORDER BY COALESCE(priority, 5) ASC, scheduled_at ASC
        LIMIT {$batchSize}
    ");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($emails)) {
        return $stats;
    }
    
    error_log("📧 EMAIL QUEUE: Processing " . count($emails) . " emails (aggressive: " . ($aggressive ? 'yes' : 'no') . ")");
    
    foreach ($emails as $email) {
        try {
            // Build HTML body from template if needed
            $htmlBody = $email['html_body'];
            if (empty($htmlBody) && !empty($email['body'])) {
                $htmlBody = createEmailTemplate(
                    $email['subject'], 
                    nl2br(htmlspecialchars($email['body'], ENT_QUOTES, 'UTF-8')),
                    'Valued Customer'
                );
            }
            
            // Send email using PHPMailer
            $sent = sendEmail($email['recipient_email'], $email['subject'], $htmlBody);
            
            if ($sent) {
                // Mark as sent
                $updateStmt = $db->prepare("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = datetime('now', '+1 hour')
                    WHERE id = ?
                ");
                $updateStmt->execute([$email['id']]);
                $stats['sent']++;
                error_log("✅ EMAIL QUEUE: Sent to {$email['recipient_email']} (Type: {$email['email_type']})");
            } else {
                throw new Exception('Email send returned false');
            }
        } catch (Exception $e) {
            // Increment attempts
            $newAttempts = ($email['attempts'] ?? 0) + 1;
            $maxAttempts = $email['max_attempts'] ?? 3;
            $newStatus = $newAttempts >= $maxAttempts ? 'failed' : 'retry';
            
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET attempts = ?, 
                    last_error = ?,
                    status = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newAttempts, $e->getMessage(), $newStatus, $email['id']]);
            
            if ($newStatus === 'failed') {
                $stats['failed']++;
                error_log("❌ EMAIL QUEUE: Failed permanently for {$email['recipient_email']}: " . $e->getMessage());
            } else {
                $stats['retrying']++;
                error_log("⚠️  EMAIL QUEUE: Will retry for {$email['recipient_email']} (Attempt {$newAttempts}/{$maxAttempts})");
            }
        }
        
        // Small delay between emails to avoid rate limiting
        usleep(100000); // 100ms delay
    }
    
    error_log("📧 EMAIL QUEUE: Batch complete - Sent: {$stats['sent']}, Failed: {$stats['failed']}, Retrying: {$stats['retrying']}");
    
    return $stats;
}

/**
 * Process high-priority emails only (for critical operations like payment confirmations)
 * Called immediately after queueing important emails
 */
function processHighPriorityEmails() {
    $db = getDb();
    
    // Only process high priority emails (priority = 1)
    $stmt = $db->query("
        SELECT * FROM email_queue 
        WHERE (status = 'pending' OR status = 'retry') 
        AND attempts < max_attempts
        AND COALESCE(priority, 5) <= 1
        ORDER BY scheduled_at ASC
        LIMIT 10
    ");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emails as $email) {
        try {
            $htmlBody = $email['html_body'] ?: createEmailTemplate(
                $email['subject'],
                nl2br(htmlspecialchars($email['body'], ENT_QUOTES, 'UTF-8')),
                'Valued Customer'
            );
            
            $sent = sendEmail($email['recipient_email'], $email['subject'], $htmlBody);
            
            if ($sent) {
                $updateStmt = $db->prepare("UPDATE email_queue SET status = 'sent', sent_at = datetime('now', '+1 hour') WHERE id = ?");
                $updateStmt->execute([$email['id']]);
                error_log("✅ HIGH PRIORITY EMAIL: Sent to {$email['recipient_email']}");
            } else {
                throw new Exception('Email send returned false');
            }
        } catch (Exception $e) {
            $updateStmt = $db->prepare("UPDATE email_queue SET attempts = attempts + 1, last_error = ? WHERE id = ?");
            $updateStmt->execute([$e->getMessage(), $email['id']]);
            error_log("⚠️  HIGH PRIORITY EMAIL: Failed for {$email['recipient_email']}: " . $e->getMessage());
        }
    }
    
    return count($emails);
}

/**
 * Get email queue statistics
 * @return array Queue stats for monitoring
 */
function getEmailQueueStats() {
    $db = getDb();
    
    $stats = [
        'pending' => 0,
        'sent' => 0,
        'failed' => 0,
        'retrying' => 0,
        'high_priority_pending' => 0
    ];
    
    try {
        $stats['pending'] = (int) $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
        $stats['sent'] = (int) $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'sent' AND sent_at >= datetime('now', '-24 hours')")->fetchColumn();
        $stats['failed'] = (int) $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'failed' AND scheduled_at >= datetime('now', '-24 hours')")->fetchColumn();
        $stats['retrying'] = (int) $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'retry'")->fetchColumn();
        $stats['high_priority_pending'] = (int) $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending' AND COALESCE(priority, 5) <= 1")->fetchColumn();
    } catch (Exception $e) {
        error_log("Failed to get email queue stats: " . $e->getMessage());
    }
    
    return $stats;
}

/**
 * Clean up old sent emails from the queue (retention: 7 days)
 * @return int Number of deleted records
 */
function cleanupEmailQueue() {
    $db = getDb();
    
    try {
        $stmt = $db->exec("DELETE FROM email_queue WHERE status = 'sent' AND sent_at < datetime('now', '-7 days')");
        $deleted = $db->lastInsertId() ?: 0;
        error_log("🧹 EMAIL QUEUE CLEANUP: Removed {$deleted} old sent emails");
        return $deleted;
    } catch (Exception $e) {
        error_log("Failed to cleanup email queue: " . $e->getMessage());
        return 0;
    }
}
