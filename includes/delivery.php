<?php
/**
 * Delivery System
 * Handles tool and template delivery
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/tool_files.php';

if (!defined('DELIVERY_RETRY_MAX_ATTEMPTS')) {
    define('DELIVERY_RETRY_MAX_ATTEMPTS', 3);
}

/**
 * Create delivery records for an order
 * FIXED: Now checks for existing deliveries and only creates MISSING ones
 * This fixes the mixed order bug where template deliveries were skipped if tool delivery already existed
 */
function createDeliveryRecords($orderId) {
    $db = getDb();
    
    error_log("📦 Creating delivery records for Order #$orderId");
    
    try {
        // Get order items - ensure we get ALL items
        $stmt = $db->prepare("
            SELECT oi.id, oi.product_id, oi.product_type, oi.quantity,
                   COALESCE(t.name, tl.name) as product_name,
                   COALESCE(t.delivery_note, tl.delivery_note) as delivery_note
            FROM order_items oi
            LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
            LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
            WHERE oi.pending_order_id = ?
            ORDER BY oi.id ASC
        ");
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("📦 Found " . count($items) . " items for Order #$orderId");
        
        if (empty($items)) {
            error_log("⚠️  No order items found for Order #$orderId");
            return;
        }
        
        // FIX: Get existing deliveries for this order (keyed by order_item_id)
        $existingDeliveriesStmt = $db->prepare("SELECT order_item_id FROM deliveries WHERE pending_order_id = ?");
        $existingDeliveriesStmt->execute([$orderId]);
        $existingDeliveryItemIds = $existingDeliveriesStmt->fetchAll(PDO::FETCH_COLUMN);
        
        error_log("📦 Existing deliveries for Order #$orderId: " . count($existingDeliveryItemIds) . " items");
        
        $createdCount = 0;
        $skippedCount = 0;
        
        foreach ($items as $item) {
            // FIX: Skip if delivery already exists for this order item
            if (in_array($item['id'], $existingDeliveryItemIds)) {
                error_log("⏭️  Skipping item {$item['id']} - delivery already exists");
                $skippedCount++;
                continue;
            }
            
            error_log("📦 Processing item: ID={$item['id']}, Type={$item['product_type']}, ProductID={$item['product_id']}");
            
            try {
                if ($item['product_type'] === 'tool') {
                    createToolDelivery($orderId, $item);
                } else {
                    createTemplateDelivery($orderId, $item);
                }
                error_log("✅ Delivery created for item {$item['id']}");
                $createdCount++;
            } catch (Exception $itemError) {
                error_log("❌ Error creating delivery for item {$item['id']}: " . $itemError->getMessage());
                throw $itemError;
            }
        }
        
        // Update order delivery status
        $stmt = $db->prepare("UPDATE pending_orders SET delivery_status = 'in_progress' WHERE id = ?");
        $stmt->execute([$orderId]);
        
        error_log("✅ Deliveries for Order #$orderId: Created $createdCount, Skipped $skippedCount (already existed)");
    } catch (Exception $e) {
        error_log("❌ Exception in createDeliveryRecords: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Create tool delivery
 * Phase 3: Enhanced with retry mechanism and improved email
 */
function createToolDelivery($orderId, $item, $retryAttempt = 0) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT customer_email, customer_name FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $files = getToolFiles($item['product_id']);
    
    $downloadLinks = [];
    foreach ($files as $file) {
        $link = generateDownloadLink($file['id'], $orderId);
        if ($link) {
            $downloadLinks[] = $link;
        }
    }
    
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_link, delivery_note,
            retry_count
        ) VALUES (?, ?, ?, 'tool', ?, 'download', 'immediate', 'ready', ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        json_encode($downloadLinks),
        $item['delivery_note'],
        $retryAttempt
    ]);
    
    $deliveryId = $db->lastInsertId();
    
    if ($order && $order['customer_email'] && !empty($downloadLinks)) {
        $emailSent = sendToolDeliveryEmail($order, $item, $downloadLinks, $orderId);
        
        // Phase 5.4: Record email event for timeline
        recordEmailEvent($orderId, 'tool_delivery', [
            'email' => $order['customer_email'],
            'subject' => "Your {$item['product_name']} is Ready to Download! - Order #{$orderId}",
            'sent' => $emailSent,
            'product_name' => $item['product_name'],
            'file_count' => count($downloadLinks)
        ]);
        
        if (!$emailSent && $retryAttempt < DELIVERY_RETRY_MAX_ATTEMPTS) {
            scheduleDeliveryRetry($deliveryId, 'tool', $retryAttempt + 1);
        }
        
        $stmt = $db->prepare("
            UPDATE deliveries SET 
                delivery_status = ?,
                email_sent_at = CASE WHEN ? = 1 THEN datetime('now', '+1 hour') ELSE email_sent_at END,
                delivered_at = CASE WHEN ? = 1 THEN datetime('now', '+1 hour') ELSE delivered_at END
            WHERE id = ?
        ");
        $stmt->execute([
            $emailSent ? 'delivered' : 'pending',
            $emailSent ? 1 : 0,
            $emailSent ? 1 : 0,
            $deliveryId
        ]);
    }
    
    return $deliveryId;
}

/**
 * Send tool delivery email with enhanced template
 * Phase 3: Professional email with file sizes, tips, clear expiry, and bundle download
 */
function sendToolDeliveryEmail($order, $item, $downloadLinks, $orderId) {
    $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
    
    $subject = "📥 Your {$item['product_name']} is Ready to Download! - Order #{$orderId}";
    
    $totalSize = 0;
    foreach ($downloadLinks as $link) {
        $totalSize += $link['file_size'] ?? 0;
    }
    $totalSizeFormatted = formatFileSize($totalSize);
    $fileCount = count($downloadLinks);
    
    $bundleUrl = null;
    if ($fileCount > 1) {
        require_once __DIR__ . '/tool_files.php';
        $bundleResult = generateBundleDownloadToken($orderId, $item['product_id']);
        if ($bundleResult['success']) {
            $bundleUrl = $bundleResult['url'];
        }
    }
    
    $body = '<div style="text-align: center; margin-bottom: 25px;">';
    $body .= '<h2 style="color: #1e3a8a; margin: 0;">🎉 Your Digital Product is Ready!</h2>';
    $body .= '<p style="color: #666; margin-top: 10px;">Order #' . $orderId . '</p>';
    $body .= '</div>';
    
    $body .= '<div style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 20px; border-radius: 10px; margin-bottom: 25px; text-align: center;">';
    $body .= '<h3 style="margin: 0 0 10px 0;">' . htmlspecialchars($item['product_name']) . '</h3>';
    $body .= '<p style="margin: 0; font-size: 14px; opacity: 0.9;">' . $fileCount . ' file' . ($fileCount > 1 ? 's' : '') . ' • ' . $totalSizeFormatted . ' total</p>';
    $body .= '</div>';
    
    if ($bundleUrl && $fileCount > 1) {
        $body .= '<div style="background: linear-gradient(135deg, #059669, #10b981); color: white; padding: 20px; border-radius: 10px; margin-bottom: 25px; text-align: center;">';
        $body .= '<h4 style="margin: 0 0 10px 0;">📦 Download Everything at Once</h4>';
        $body .= '<p style="margin: 0 0 15px 0; font-size: 14px; opacity: 0.9;">Get all ' . $fileCount . ' files in a single ZIP bundle</p>';
        $body .= '<a href="' . htmlspecialchars($bundleUrl) . '" style="background: white; color: #059669; padding: 12px 30px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px;">📥 Download All (' . $totalSizeFormatted . ')</a>';
        $body .= '</div>';
    }
    
    $body .= '<div style="background-color: #f8fafc; padding: 25px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #e2e8f0;">';
    $body .= '<h4 style="color: #1e3a8a; margin: 0 0 20px 0;">📥 Individual Download Links</h4>';
    
    foreach ($downloadLinks as $index => $link) {
        $fileName = htmlspecialchars($link['name'] ?? 'Download File');
        $fileUrl = htmlspecialchars($link['url'] ?? '');
        $fileSize = $link['file_size_formatted'] ?? formatFileSize($link['file_size'] ?? 0);
        $fileType = ucfirst(str_replace('_', ' ', $link['file_type'] ?? 'file'));
        $expiryFormatted = $link['expires_formatted'] ?? date('F j, Y', strtotime("+{$expiryDays} days"));
        $isLink = ($link['file_type'] === 'link');
        
        $body .= '<div style="background: white; padding: 15px; border-radius: 8px; margin-bottom: 12px; border: 1px solid #e2e8f0;">';
        $body .= '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">';
        $body .= '<div style="margin-bottom: 10px;">';
        $body .= '<strong style="color: #1e3a8a; font-size: 15px;">' . ($isLink ? '🔗 ' : '📥 ') . $fileName . '</strong>';
        $body .= '<div style="color: #666; font-size: 12px; margin-top: 4px;">' . $fileType . ($isLink ? ' • External URL' : ' • ' . $fileSize) . '</div>';
        $body .= '</div>';
        $body .= '</div>';
        
        if ($isLink) {
            $body .= '<a href="' . $fileUrl . '" target="_blank" style="background: #059669; color: white; padding: 10px 25px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold; font-size: 14px; margin-top: 5px;">🔗 Visit Link (opens in new tab)</a>';
        } else {
            $body .= '<a href="' . $fileUrl . '" style="background: #1e3a8a; color: white; padding: 10px 25px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold; font-size: 14px; margin-top: 5px;">📥 Download File</a>';
        }
        
        $body .= '</div>';
    }
    
    $body .= '</div>';
    
    $body .= '<div style="background-color: #fef3c7; padding: 20px; border-radius: 10px; margin-bottom: 25px; border-left: 4px solid #f59e0b;">';
    $body .= '<h4 style="color: #92400e; margin: 0 0 10px 0;">⏰ Important: Download Expiry</h4>';
    $body .= '<p style="color: #92400e; margin: 0; line-height: 1.6;">';
    $body .= 'Your download links will expire in <strong>' . $expiryDays . ' days</strong> (on ' . date('F j, Y', strtotime("+{$expiryDays} days")) . ').<br>';
    $body .= 'Each link allows up to <strong>' . $maxDownloads . ' downloads</strong>. Save your files to a secure location after downloading.';
    $body .= '</p>';
    $body .= '</div>';
    
    $body .= '<div style="background-color: #ecfdf5; padding: 20px; border-radius: 10px; margin-bottom: 25px;">';
    $body .= '<h4 style="color: #065f46; margin: 0 0 15px 0;">💡 Tips for Best Experience</h4>';
    $body .= '<ul style="color: #065f46; margin: 0; padding-left: 20px; line-height: 1.8;">';
    $body .= '<li>Use a stable internet connection for large downloads</li>';
    $body .= '<li>Extract ZIP files after downloading to access contents</li>';
    $body .= '<li>Read any README or documentation files included</li>';
    $body .= '<li>Keep a backup copy in cloud storage for safety</li>';
    $body .= '</ul>';
    $body .= '</div>';
    
    if (!empty($item['delivery_note'])) {
        $body .= '<div style="background-color: #f0f9ff; padding: 20px; border-radius: 10px; margin-bottom: 25px; border: 1px solid #bae6fd;">';
        $body .= '<h4 style="color: #0369a1; margin: 0 0 10px 0;">📝 Product Notes</h4>';
        $body .= '<p style="color: #0369a1; margin: 0; line-height: 1.6;">' . htmlspecialchars($item['delivery_note']) . '</p>';
        $body .= '</div>';
    }
    
    $body .= '<div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8fafc; border-radius: 10px;">';
    $body .= '<p style="color: #64748b; margin: 0 0 10px 0; font-size: 14px;">Need help? Contact us anytime:</p>';
    $body .= '<a href="https://wa.me/' . preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER) . '" style="color: #1e3a8a; font-weight: bold; text-decoration: none;">💬 WhatsApp: ' . WHATSAPP_NUMBER . '</a>';
    $body .= '</div>';
    
    require_once __DIR__ . '/mailer.php';
    return sendEmail($order['customer_email'], $subject, createEmailTemplate($subject, $body, $order['customer_name']));
}

/**
 * Schedule delivery retry with exponential backoff
 * Phase 3: Auto-retry mechanism
 */
function scheduleDeliveryRetry($deliveryId, $deliveryType, $attemptNumber) {
    $db = getDb();
    
    $baseDelay = defined('DELIVERY_RETRY_BASE_DELAY_SECONDS') ? DELIVERY_RETRY_BASE_DELAY_SECONDS : 60;
    $delay = $baseDelay * pow(2, $attemptNumber - 1);
    $scheduledAt = date('Y-m-d H:i:s', time() + $delay);
    
    $stmt = $db->prepare("
        UPDATE deliveries SET 
            retry_count = ?,
            next_retry_at = ?,
            delivery_status = 'pending_retry'
        WHERE id = ?
    ");
    $stmt->execute([$attemptNumber, $scheduledAt, $deliveryId]);
    
    error_log("Scheduled delivery retry #{$attemptNumber} for delivery #{$deliveryId} at {$scheduledAt}");
    
    return $scheduledAt;
}

/**
 * Process pending delivery retries
 * Phase 3: Called by cron job
 */
function processDeliveryRetries() {
    $db = getDb();
    $maxAttempts = defined('DELIVERY_RETRY_MAX_ATTEMPTS') ? DELIVERY_RETRY_MAX_ATTEMPTS : 3;
    
    $stmt = $db->query("
        SELECT d.*, po.customer_email, po.customer_name
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.delivery_status = 'pending_retry'
          AND d.next_retry_at <= datetime('now', '+1 hour')
          AND d.retry_count < {$maxAttempts}
        ORDER BY d.next_retry_at ASC
        LIMIT 10
    ");
    $retries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processed = 0;
    $successful = 0;
    
    foreach ($retries as $delivery) {
        $processed++;
        
        if ($delivery['product_type'] === 'tool') {
            $downloadLinks = json_decode($delivery['delivery_link'], true) ?? [];
            $item = [
                'product_name' => $delivery['product_name'],
                'delivery_note' => $delivery['delivery_note']
            ];
            $order = [
                'customer_email' => $delivery['customer_email'],
                'customer_name' => $delivery['customer_name']
            ];
            
            $emailSent = sendToolDeliveryEmail($order, $item, $downloadLinks, $delivery['pending_order_id']);
            
            if ($emailSent) {
                $updateStmt = $db->prepare("
                    UPDATE deliveries SET 
                        delivery_status = 'delivered',
                        email_sent_at = datetime('now', '+1 hour'),
                        delivered_at = datetime('now', '+1 hour'),
                        next_retry_at = NULL
                    WHERE id = ?
                ");
                $updateStmt->execute([$delivery['id']]);
                $successful++;
                error_log("Delivery retry successful for delivery #{$delivery['id']}");
            } else {
                $newAttempt = $delivery['retry_count'] + 1;
                if ($newAttempt < $maxAttempts) {
                    scheduleDeliveryRetry($delivery['id'], 'tool', $newAttempt);
                } else {
                    $failStmt = $db->prepare("
                        UPDATE deliveries SET 
                            delivery_status = 'failed',
                            next_retry_at = NULL
                        WHERE id = ?
                    ");
                    $failStmt->execute([$delivery['id']]);
                    error_log("Delivery permanently failed after {$maxAttempts} attempts for delivery #{$delivery['id']}");
                }
            }
        }
    }
    
    return ['processed' => $processed, 'successful' => $successful];
}

/**
 * Resend tool delivery email (manual admin action)
 * Phase 3: Admin can resend emails
 */
function resendToolDeliveryEmail($deliveryId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ? AND d.product_type = 'tool'
    ");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        return ['success' => false, 'message' => 'Tool delivery not found'];
    }
    
    $downloadLinks = json_decode($delivery['delivery_link'], true) ?? [];
    if (empty($downloadLinks)) {
        return ['success' => false, 'message' => 'No download links found for this delivery'];
    }
    
    $item = [
        'product_name' => $delivery['product_name'],
        'delivery_note' => $delivery['delivery_note']
    ];
    $order = [
        'customer_email' => $delivery['customer_email'],
        'customer_name' => $delivery['customer_name']
    ];
    
    $emailSent = sendToolDeliveryEmail($order, $item, $downloadLinks, $delivery['pending_order_id']);
    
    if ($emailSent) {
        $updateStmt = $db->prepare("
            UPDATE deliveries SET 
                email_sent_at = datetime('now', '+1 hour')
            WHERE id = ?
        ");
        $updateStmt->execute([$deliveryId]);
        return ['success' => true, 'message' => 'Tool delivery email resent successfully'];
    }
    
    return ['success' => false, 'message' => 'Failed to send email. Please try again.'];
}

/**
 * Create template delivery
 */
function createTemplateDelivery($orderId, $item) {
    $db = getDb();
    
    // Create delivery record (pending 24h setup)
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_note,
            template_ready_at
        ) VALUES (?, ?, ?, 'template', ?, 'hosted', 'pending_24h', 'pending', ?,
                  datetime('now', '+24 hours'))
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        $item['delivery_note']
    ]);
    
    $deliveryId = $db->lastInsertId();
    
    // Send "template pending" email directly - NOTE: Get customer email from order
    // This will be sent after order is confirmed
    
    return $deliveryId;
}

/**
 * Mark template as ready and send email
 */
function markTemplateReady($deliveryId, $hostedDomain, $hostedUrl, $adminNotes = '') {
    $db = getDb();
    
    // Get delivery details
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        throw new Exception('Delivery not found');
    }
    
    // Get order to get customer email
    $stmt = $db->prepare("SELECT customer_name, customer_email, customer_phone FROM pending_orders WHERE id = ?");
    $stmt->execute([$delivery['pending_order_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update delivery status
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET delivery_status = 'delivered',
            hosted_domain = ?,
            hosted_url = ?,
            admin_notes = ?,
            delivered_at = datetime('now', '+1 hour'),
            email_sent_at = datetime('now', '+1 hour')
        WHERE id = ?
    ");
    $stmt->execute([$hostedDomain, $hostedUrl, $adminNotes, $deliveryId]);
    
    // Send "template ready" email to customer
    if ($order && $order['customer_email']) {
        sendTemplateDeliveryEmail($order, $delivery, $hostedDomain, $hostedUrl, $adminNotes);
    }
}

/**
 * Send template delivery email with domain details
 */
function sendTemplateDeliveryEmail($order, $delivery, $hostedDomain, $hostedUrl, $adminNotes = '') {
    $subject = "🎉 Your Website Template is Ready! Domain: " . htmlspecialchars($hostedDomain) . " - Order #" . $delivery['pending_order_id'];
    
    $body = '<p>Great news! 🎉</p>';
    $body .= '<p>Your website template <strong>' . htmlspecialchars($delivery['product_name']) . '</strong> has been deployed and is ready to use!</p>';
    
    $body .= '<div style="background-color: #f0f0f0; padding: 20px; border-radius: 5px; margin: 20px 0;">';
    $body .= '<h3 style="color: #333; margin-top: 0;">🌐 Your Domain Details:</h3>';
    $body .= '<p style="color: #666;"><strong>Domain:</strong> <span style="font-size: 18px; color: #1e3a8a; font-weight: bold;">' . htmlspecialchars($hostedDomain) . '</span></p>';
    $body .= '<p style="color: #666;"><strong>Website URL:</strong> <a href="' . htmlspecialchars($hostedUrl) . '" style="color: #1e3a8a; font-weight: bold;">' . htmlspecialchars($hostedUrl) . '</a></p>';
    
    if (!empty($adminNotes)) {
        $body .= '<p style="color: #666;"><strong>📝 Special Instructions:</strong></p>';
        $body .= '<p style="background-color: white; padding: 10px; border-left: 3px solid #1e3a8a; color: #666;">' . htmlspecialchars($adminNotes) . '</p>';
    }
    
    $body .= '<p style="margin-top: 20px;"><a href="' . htmlspecialchars($hostedUrl) . '" style="background-color: #1e3a8a; color: white; padding: 12px 30px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">✨ Visit Your Website</a></p>';
    $body .= '</div>';
    
    $body .= '<p style="color: #999; font-size: 12px; margin-top: 20px;">Your website is now live and ready to impress your audience. If you have any questions or need further customization, please reach out to us via WhatsApp.</p>';
    
    require_once __DIR__ . '/mailer.php';
    sendEmail($order['customer_email'], $subject, createEmailTemplate($subject, $body, $order['customer_name']));
}

/**
 * Get delivery status for an order
 */
function getDeliveryStatus($orderId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE pending_order_id = ? ORDER BY product_type ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Save template credentials and optionally deliver to customer
 * Phase 1: Template Credentials System
 * 
 * @param int $deliveryId Delivery record ID
 * @param array $credentials Credential data (username, password, login_url, hosting_provider)
 * @param string $hostedDomain Domain name
 * @param string $hostedUrl Full URL to the website
 * @param string $adminNotes Additional notes for customer
 * @param bool $sendEmail Whether to send delivery email immediately
 * @return array Result with success status and message
 */
function saveTemplateCredentials($deliveryId, $credentials, $hostedDomain, $hostedUrl, $adminNotes = '', $sendEmail = true) {
    $db = getDb();
    require_once __DIR__ . '/functions.php';
    
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        return ['success' => false, 'message' => 'Delivery record not found'];
    }
    
    if ($delivery['product_type'] !== 'template') {
        return ['success' => false, 'message' => 'Credentials can only be set for template deliveries'];
    }
    
    $encryptedPassword = $delivery['template_admin_password'] ?? '';
    if (!empty($credentials['password'])) {
        $newEncryptedPassword = encryptCredential($credentials['password']);
        if ($newEncryptedPassword === false) {
            return ['success' => false, 'message' => 'Failed to encrypt password. Please try again.'];
        }
        $encryptedPassword = $newEncryptedPassword;
    }
    
    if ($sendEmail) {
        if (empty($hostedDomain)) {
            return ['success' => false, 'message' => 'Domain is required before sending delivery email.'];
        }
        $hostingType = $credentials['hosting_provider'] ?? 'custom';
        if ($hostingType !== 'static') {
            if (empty($credentials['username']) && empty($delivery['template_admin_username'])) {
                return ['success' => false, 'message' => 'Username is required for ' . ucfirst($hostingType) . ' sites. Enter credentials or select "Static Site" for no login.'];
            }
            if (empty($encryptedPassword)) {
                return ['success' => false, 'message' => 'Password is required for ' . ucfirst($hostingType) . ' sites. Enter credentials or select "Static Site" for no login.'];
            }
        }
    }
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET hosted_domain = ?,
            hosted_url = ?,
            template_admin_username = ?,
            template_admin_password = ?,
            template_login_url = ?,
            hosting_provider = ?,
            admin_notes = ?,
            updated_at = datetime('now', '+1 hour')
        WHERE id = ?
    ");
    
    $stmt->execute([
        $hostedDomain,
        $hostedUrl,
        $credentials['username'] ?? $delivery['template_admin_username'] ?? '',
        $encryptedPassword,
        $credentials['login_url'] ?? $delivery['template_login_url'] ?? '',
        $credentials['hosting_provider'] ?? 'custom',
        $adminNotes,
        $deliveryId
    ]);
    
    if ($sendEmail) {
        return deliverTemplateWithCredentials($deliveryId);
    }
    
    return ['success' => true, 'message' => 'Credentials saved successfully'];
}

/**
 * Deliver template to customer with credentials
 * Marks delivery as complete and sends email
 * 
 * @param int $deliveryId Delivery record ID
 * @return array Result with success status and message
 */
function deliverTemplateWithCredentials($deliveryId) {
    $db = getDb();
    require_once __DIR__ . '/functions.php';
    
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        return ['success' => false, 'message' => 'Delivery record not found'];
    }
    
    $stmt = $db->prepare("SELECT customer_name, customer_email, customer_phone FROM pending_orders WHERE id = ?");
    $stmt->execute([$delivery['pending_order_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order || empty($order['customer_email'])) {
        return ['success' => false, 'message' => 'Customer email not found'];
    }
    
    $decryptedPassword = '';
    if (!empty($delivery['template_admin_password'])) {
        $decryptedPassword = decryptCredential($delivery['template_admin_password']);
    }
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET delivery_status = 'delivered',
            delivered_at = datetime('now', '+1 hour'),
            email_sent_at = datetime('now', '+1 hour'),
            credentials_sent_at = datetime('now', '+1 hour')
        WHERE id = ?
    ");
    $stmt->execute([$deliveryId]);
    
    $emailSent = sendTemplateDeliveryEmailWithCredentials($order, $delivery, $decryptedPassword);
    
    // Phase 5.4: Record email event for timeline
    recordEmailEvent($delivery['pending_order_id'], 'template_credentials', [
        'email' => $order['customer_email'],
        'subject' => "Your Website Template is Ready! Domain: " . ($delivery['hosted_domain'] ?? 'N/A'),
        'sent' => $emailSent,
        'product_name' => $delivery['product_name'],
        'hosted_domain' => $delivery['hosted_domain'] ?? ''
    ]);
    
    // Update order delivery status after template delivery
    updateOrderDeliveryStatus($delivery['pending_order_id']);
    
    if (!$emailSent) {
        return ['success' => true, 'message' => 'Template marked as delivered but email sending failed. Please retry email manually.'];
    }
    
    return ['success' => true, 'message' => 'Template delivered successfully! Customer has been notified by email.'];
}

/**
 * Send template delivery email with full credentials
 * Phase 1: Enhanced email with login details
 * 
 * @param array $order Order details
 * @param array $delivery Delivery record with credentials
 * @param string $decryptedPassword Decrypted password for email
 * @return bool Whether email was sent successfully
 */
function sendTemplateDeliveryEmailWithCredentials($order, $delivery, $decryptedPassword) {
    $hostedDomain = $delivery['hosted_domain'] ?? '';
    $hostedUrl = $delivery['hosted_url'] ?? '';
    $adminUsername = $delivery['template_admin_username'] ?? '';
    $loginUrl = $delivery['template_login_url'] ?? '';
    $hostingProvider = $delivery['hosting_provider'] ?? 'custom';
    $adminNotes = $delivery['admin_notes'] ?? '';
    
    $subject = "🎉 Your Website Template is Ready! Domain: " . htmlspecialchars($hostedDomain) . " - Order #" . $delivery['pending_order_id'];
    
    $body = '<p>Great news! 🎉</p>';
    $body .= '<p>Your website template <strong>' . htmlspecialchars($delivery['product_name']) . '</strong> has been deployed and is ready to use!</p>';
    
    $body .= '<div style="background-color: #f0f9ff; padding: 25px; border-radius: 8px; margin: 20px 0; border: 1px solid #bae6fd;">';
    $body .= '<h3 style="color: #0369a1; margin-top: 0;">🌐 Your Website Details</h3>';
    $body .= '<table style="width: 100%; border-collapse: collapse;">';
    $body .= '<tr><td style="padding: 8px 0; color: #666; font-weight: bold; width: 140px;">Domain:</td><td style="padding: 8px 0; font-size: 18px; color: #1e3a8a; font-weight: bold;">' . htmlspecialchars($hostedDomain) . '</td></tr>';
    $body .= '<tr><td style="padding: 8px 0; color: #666; font-weight: bold;">Website URL:</td><td style="padding: 8px 0;"><a href="' . htmlspecialchars($hostedUrl) . '" style="color: #1e3a8a; font-weight: bold;">' . htmlspecialchars($hostedUrl) . '</a></td></tr>';
    $body .= '</table>';
    $body .= '</div>';
    
    if (!empty($adminUsername) || !empty($loginUrl)) {
        $body .= '<div style="background-color: #fefce8; padding: 25px; border-radius: 8px; margin: 20px 0; border: 1px solid #fde047;">';
        $body .= '<h3 style="color: #854d0e; margin-top: 0;">🔐 Login Credentials</h3>';
        $body .= '<p style="color: #854d0e; font-size: 13px; margin-bottom: 15px;"><strong>⚠️ IMPORTANT:</strong> Save these credentials securely. Change your password after first login.</p>';
        $body .= '<table style="width: 100%; border-collapse: collapse; background: white; border-radius: 6px; overflow: hidden;">';
        
        if (!empty($loginUrl)) {
            $body .= '<tr style="border-bottom: 1px solid #f3f4f6;"><td style="padding: 12px; color: #666; font-weight: bold; width: 140px; background: #fafafa;">Admin URL:</td><td style="padding: 12px;"><a href="' . htmlspecialchars($loginUrl) . '" style="color: #1e3a8a; font-weight: bold;">' . htmlspecialchars($loginUrl) . '</a></td></tr>';
        }
        if (!empty($adminUsername)) {
            $body .= '<tr style="border-bottom: 1px solid #f3f4f6;"><td style="padding: 12px; color: #666; font-weight: bold; background: #fafafa;">Username:</td><td style="padding: 12px; font-family: monospace; font-size: 15px; font-weight: bold;">' . htmlspecialchars($adminUsername) . '</td></tr>';
        }
        if (!empty($decryptedPassword)) {
            $body .= '<tr style="border-bottom: 1px solid #f3f4f6;"><td style="padding: 12px; color: #666; font-weight: bold; background: #fafafa;">Password:</td><td style="padding: 12px; font-family: monospace; font-size: 15px; font-weight: bold; letter-spacing: 1px;">' . htmlspecialchars($decryptedPassword) . '</td></tr>';
        }
        
        $hostingLabels = [
            'wordpress' => 'WordPress',
            'cpanel' => 'cPanel',
            'custom' => 'Custom Admin',
            'static' => 'Static Site'
        ];
        $hostingLabel = $hostingLabels[$hostingProvider] ?? 'Custom';
        $body .= '<tr><td style="padding: 12px; color: #666; font-weight: bold; background: #fafafa;">Hosting Type:</td><td style="padding: 12px;">' . htmlspecialchars($hostingLabel) . '</td></tr>';
        
        $body .= '</table>';
        $body .= '</div>';
    }
    
    if (!empty($adminNotes)) {
        $body .= '<div style="background-color: #f0fdf4; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #86efac;">';
        $body .= '<h4 style="color: #166534; margin-top: 0;">📝 Special Instructions from Admin</h4>';
        $body .= '<p style="color: #166534; line-height: 1.6; white-space: pre-wrap;">' . htmlspecialchars($adminNotes) . '</p>';
        $body .= '</div>';
    }
    
    $body .= '<div style="text-align: center; margin: 30px 0;">';
    $body .= '<a href="' . htmlspecialchars($hostedUrl) . '" style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; padding: 15px 40px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 16px; box-shadow: 0 4px 15px rgba(30, 58, 138, 0.3);">✨ Visit Your Website</a>';
    $body .= '</div>';
    
    $body .= '<div style="background-color: #f8fafc; padding: 20px; border-radius: 8px; margin: 20px 0;">';
    $body .= '<h4 style="color: #475569; margin-top: 0;">🔒 Security Tips</h4>';
    $body .= '<ul style="color: #64748b; line-height: 1.8; margin: 0; padding-left: 20px;">';
    $body .= '<li>Change your password after first login</li>';
    $body .= '<li>Keep your credentials in a secure password manager</li>';
    $body .= '<li>Enable two-factor authentication if available</li>';
    $body .= '<li>Backup your site regularly</li>';
    $body .= '</ul>';
    $body .= '</div>';
    
    $body .= '<p style="color: #999; font-size: 12px; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 20px;">Your website is now live and ready to impress your audience. If you have any questions or need further customization, please reach out to us via WhatsApp at ' . (defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '+2349132672126') . '.</p>';
    
    require_once __DIR__ . '/mailer.php';
    return sendEmail($order['customer_email'], $subject, createEmailTemplate($subject, $body, $order['customer_name']));
}

/**
 * Get delivery record by ID with decrypted password (for admin viewing)
 * 
 * @param int $deliveryId Delivery ID
 * @param bool $decryptPassword Whether to decrypt password for display
 * @return array|null Delivery record or null if not found
 */
function getDeliveryById($deliveryId, $decryptPassword = false) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($delivery && $decryptPassword && !empty($delivery['template_admin_password'])) {
        require_once __DIR__ . '/functions.php';
        $delivery['decrypted_password'] = decryptCredential($delivery['template_admin_password']);
    }
    
    return $delivery;
}

/**
 * Get pending template deliveries (for admin dashboard)
 * Shows templates that need credentials or domain assignment
 * 
 * @return array List of pending template deliveries
 */
function getPendingTemplateDeliveries() {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT d.*, po.customer_name, po.customer_email, po.customer_phone, po.created_at as order_date
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.product_type = 'template' 
          AND d.delivery_status IN ('pending', 'in_progress', 'ready')
        ORDER BY d.created_at ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check if template delivery is complete
 * 
 * @param int $deliveryId Delivery ID
 * @return array Status information
 */
function getTemplateDeliveryProgress($deliveryId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        return ['found' => false];
    }
    
    $steps = [
        'payment_confirmed' => ['status' => true, 'label' => 'Payment Confirmed'],
        'domain_assigned' => ['status' => !empty($delivery['hosted_domain']), 'label' => 'Domain Assigned'],
        'credentials_set' => ['status' => !empty($delivery['template_admin_username']), 'label' => 'Credentials Set'],
        'instructions_added' => ['status' => !empty($delivery['admin_notes']), 'label' => 'Instructions Added'],
        'email_sent' => ['status' => !empty($delivery['credentials_sent_at']), 'label' => 'Email Sent to Customer']
    ];
    
    $completedCount = 0;
    foreach ($steps as $step) {
        if ($step['status']) $completedCount++;
    }
    
    return [
        'found' => true,
        'delivery' => $delivery,
        'steps' => $steps,
        'completed' => $completedCount,
        'total' => count($steps),
        'percentage' => round(($completedCount / count($steps)) * 100),
        'is_complete' => $delivery['delivery_status'] === 'delivered'
    ];
}

/**
 * Get undelivered templates older than 24 hours
 * Phase 4.5: Admin reminder system
 */
function getOverdueTemplateDeliveries($hoursOverdue = 24) {
    $db = getDb();
    $cutoffTime = date('Y-m-d H:i:s', time() - ($hoursOverdue * 3600));
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_name, po.customer_email, po.id as order_id
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.product_type = 'template'
          AND d.delivery_status = 'pending'
          AND d.created_at < ?
        ORDER BY d.created_at ASC
    ");
    $stmt->execute([$cutoffTime]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Send admin alert for overdue template deliveries
 * Phase 4.5: Notifies admin of undelivered templates
 */
function sendOverdueTemplateAlert() {
    $overdue = getOverdueTemplateDeliveries(24);
    
    if (empty($overdue)) {
        return ['success' => true, 'message' => 'No overdue deliveries', 'count' => 0];
    }
    
    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'admin@webdaddyempire.com';
    $subject = '⏰ Alert: ' . count($overdue) . ' Template(s) Pending Delivery for 24+ Hours';
    
    $body = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body .= '<h2 style="color: #dc2626;">⏰ Delivery Alert</h2>';
    $body .= '<p style="color: #666;">The following templates have been pending delivery for over 24 hours:</p>';
    
    $body .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
    $body .= '<thead><tr style="background: #f3f4f6; border-bottom: 2px solid #d1d5db;">';
    $body .= '<th style="padding: 12px; text-align: left; font-weight: bold;">Template</th>';
    $body .= '<th style="padding: 12px; text-align: left; font-weight: bold;">Customer</th>';
    $body .= '<th style="padding: 12px; text-align: left; font-weight: bold;">Order ID</th>';
    $body .= '<th style="padding: 12px; text-align: left; font-weight: bold;">Hours Pending</th>';
    $body .= '</tr></thead>';
    $body .= '<tbody>';
    
    foreach ($overdue as $delivery) {
        $hoursPending = round((time() - strtotime($delivery['created_at'])) / 3600);
        $body .= '<tr style="border-bottom: 1px solid #e5e7eb;">';
        $body .= '<td style="padding: 12px;">' . htmlspecialchars($delivery['product_name']) . '</td>';
        $body .= '<td style="padding: 12px;">' . htmlspecialchars($delivery['customer_name']) . '</td>';
        $body .= '<td style="padding: 12px;"><a href="' . SITE_URL . '/admin/orders.php?view=' . $delivery['order_id'] . '" style="color: #6366f1; text-decoration: none;">#' . $delivery['order_id'] . '</a></td>';
        $body .= '<td style="padding: 12px;"><strong>' . $hoursPending . 'h</strong></td>';
        $body .= '</tr>';
    }
    
    $body .= '</tbody></table>';
    
    $body .= '<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin: 20px 0;">';
    $body .= '<strong style="color: #92400e;">Action Required:</strong><br>';
    $body .= 'Please review these orders and complete the credential setup to deliver the templates to your customers.';
    $body .= '</div>';
    
    $body .= '<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">';
    $body .= '<a href="' . SITE_URL . '/admin/deliveries.php?type=template&status=pending" style="background: #dc2626; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;">';
    $body .= 'View Pending Deliveries →</a>';
    $body .= '</div>';
    
    $body .= '</div>';
    
    require_once __DIR__ . '/mailer.php';
    $result = sendEmail($adminEmail, $subject, createEmailTemplate($subject, $body, 'Admin'));
    
    return [
        'success' => $result,
        'message' => count($overdue) . ' overdue template(s) alert sent',
        'count' => count($overdue)
    ];
}

/**
 * Send mixed order delivery summary email
 * Phase 5.4: Email sequence for mixed orders showing delivery split
 */
function sendMixedOrderDeliverySummaryEmail($orderId) {
    $db = getDb();
    
    // Get order details
    $stmt = $db->prepare("SELECT * FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order || empty($order['customer_email'])) {
        return ['success' => false, 'message' => 'Order not found or no email'];
    }
    
    $stats = getOrderDeliveryStats($orderId);
    
    // Only send if it's actually a mixed order
    if ($stats['tools']['total'] === 0 || $stats['templates']['total'] === 0) {
        return ['success' => false, 'message' => 'Not a mixed order'];
    }
    
    // Get delivery details
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE pending_order_id = ? ORDER BY product_type ASC, id ASC");
    $stmt->execute([$orderId]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $subject = "📦 Order #{$orderId} - Delivery Update";
    
    $body = '<p>Hello ' . htmlspecialchars($order['customer_name']) . ',</p>';
    $body .= '<p>Here\'s an update on your order delivery status.</p>';
    
    // Delivery Progress
    $body .= '<div style="background: linear-gradient(135deg, #3b82f6, #6366f1); padding: 20px; border-radius: 10px; margin: 20px 0; color: white;">';
    $body .= '<h3 style="margin: 0 0 10px 0; color: white;">📊 Delivery Progress</h3>';
    $body .= '<div style="background: rgba(255,255,255,0.2); border-radius: 8px; padding: 3px; margin: 10px 0;">';
    $body .= '<div style="background: white; border-radius: 6px; height: 20px; width: ' . $stats['delivery_percentage'] . '%;"></div>';
    $body .= '</div>';
    $body .= '<p style="margin: 0; font-size: 18px;"><strong>' . $stats['delivered_items'] . ' of ' . $stats['total_items'] . ' items delivered (' . $stats['delivery_percentage'] . '%)</strong></p>';
    $body .= '</div>';
    
    // Tools Section - Immediate Delivery
    $body .= '<div style="background: #f0fdf4; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #86efac;">';
    $body .= '<h3 style="margin: 0 0 15px 0; color: #166534;">🔧 Digital Tools - Immediate Delivery</h3>';
    
    $toolDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'tool'; });
    if (!empty($toolDeliveries)) {
        $body .= '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($toolDeliveries as $td) {
            $statusBadge = in_array($td['delivery_status'], ['delivered', 'ready', 'sent']) 
                ? '<span style="background: #22c55e; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">✓ DELIVERED</span>'
                : '<span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">⏳ PENDING</span>';
            
            $body .= '<tr>';
            $body .= '<td style="padding: 10px 0; border-bottom: 1px solid #dcfce7;">' . htmlspecialchars($td['product_name']) . '</td>';
            $body .= '<td style="padding: 10px 0; border-bottom: 1px solid #dcfce7; text-align: right;">' . $statusBadge . '</td>';
            $body .= '</tr>';
        }
        $body .= '</table>';
        
        if ($stats['tools']['delivered'] === $stats['tools']['total']) {
            $body .= '<p style="color: #166534; margin: 15px 0 0 0;"><strong>✅ All tools have been delivered to your email!</strong></p>';
        }
    }
    $body .= '</div>';
    
    // Templates Section - Pending Delivery
    $body .= '<div style="background: #fefce8; padding: 20px; border-radius: 10px; margin: 20px 0; border: 2px solid #fde047;">';
    $body .= '<h3 style="margin: 0 0 15px 0; color: #854d0e;">🎨 Website Templates - Pending Setup</h3>';
    
    $templateDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'template'; });
    if (!empty($templateDeliveries)) {
        $body .= '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($templateDeliveries as $td) {
            if ($td['delivery_status'] === 'delivered') {
                $statusBadge = '<span style="background: #22c55e; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">✓ DELIVERED</span>';
            } elseif (!empty($td['hosted_domain'])) {
                $statusBadge = '<span style="background: #3b82f6; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">🔧 IN PROGRESS</span>';
            } else {
                $statusBadge = '<span style="background: #f59e0b; color: white; padding: 4px 10px; border-radius: 12px; font-size: 11px;">⏳ SETTING UP</span>';
            }
            
            $body .= '<tr>';
            $body .= '<td style="padding: 10px 0; border-bottom: 1px solid #fef9c3;">' . htmlspecialchars($td['product_name']) . '</td>';
            $body .= '<td style="padding: 10px 0; border-bottom: 1px solid #fef9c3; text-align: right;">' . $statusBadge . '</td>';
            $body .= '</tr>';
        }
        $body .= '</table>';
        
        if ($stats['templates']['pending'] > 0) {
            $body .= '<div style="background: #fff7ed; padding: 15px; border-radius: 8px; margin: 15px 0 0 0; border-left: 4px solid #f97316;">';
            $body .= '<p style="color: #9a3412; margin: 0;"><strong>📋 What\'s Next?</strong></p>';
            $body .= '<p style="color: #78350f; margin: 10px 0 0 0;">Our team is setting up your website template(s) on a premium domain. You\'ll receive login credentials via email once complete (usually within 24-48 hours).</p>';
            $body .= '</div>';
        } else {
            $body .= '<p style="color: #166534; margin: 15px 0 0 0;"><strong>✅ All templates have been delivered!</strong></p>';
        }
    }
    $body .= '</div>';
    
    // Need help section
    $body .= '<div style="background: #f1f5f9; padding: 15px; border-radius: 8px; margin: 20px 0;">';
    $body .= '<p style="color: #475569; margin: 0;"><strong>Need Help?</strong> Reply to this email or contact us on WhatsApp. We\'re here to assist!</p>';
    $body .= '</div>';
    
    require_once __DIR__ . '/mailer.php';
    $emailBody = createEmailTemplate($subject, $body, $order['customer_name']);
    $result = sendEmail($order['customer_email'], $subject, $emailBody);
    
    return [
        'success' => $result,
        'message' => $result ? 'Delivery summary email sent' : 'Failed to send email'
    ];
}

/**
 * Record email sent event for delivery timeline
 * Phase 5.4: Track email events for order timeline
 */
function recordEmailEvent($orderId, $eventType, $details = []) {
    $db = getDb();
    
    try {
        // Check if email_events table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='email_events'");
        if (!$tableCheck->fetch()) {
            // Create table if not exists
            $db->exec("
                CREATE TABLE IF NOT EXISTS email_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    pending_order_id INTEGER NOT NULL,
                    event_type TEXT NOT NULL,
                    recipient_email TEXT,
                    subject TEXT,
                    status TEXT DEFAULT 'sent',
                    details TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");
        }
        
        $stmt = $db->prepare("
            INSERT INTO email_events (pending_order_id, event_type, recipient_email, subject, details)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderId,
            $eventType,
            $details['email'] ?? null,
            $details['subject'] ?? null,
            json_encode($details)
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Error recording email event: " . $e->getMessage());
        return false;
    }
}

/**
 * Get email sequence for an order
 * Phase 5.4: Shows all emails sent for an order
 */
function getOrderEmailSequence($orderId) {
    $db = getDb();
    
    try {
        // Check if table exists
        $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='email_events'");
        if (!$tableCheck->fetch()) {
            return [];
        }
        
        $stmt = $db->prepare("
            SELECT * FROM email_events 
            WHERE pending_order_id = ? 
            ORDER BY created_at ASC
        ");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching email sequence: " . $e->getMessage());
        return [];
    }
}

/**
 * Get order delivery stats for partial delivery tracking
 * Phase 5.2: Tracks partial fulfillment status for mixed orders
 */
function getOrderDeliveryStats($orderId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT 
            d.*,
            CASE 
                WHEN d.product_type = 'tool' AND d.delivery_status IN ('delivered', 'ready', 'sent') THEN 1
                WHEN d.product_type = 'template' AND d.delivery_status = 'delivered' THEN 1
                ELSE 0
            END as is_delivered
        FROM deliveries d
        WHERE d.pending_order_id = ?
    ");
    $stmt->execute([$orderId]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stats = [
        'order_id' => $orderId,
        'total_items' => count($deliveries),
        'delivered_items' => 0,
        'pending_items' => 0,
        'tools' => [
            'total' => 0,
            'delivered' => 0,
            'pending' => 0
        ],
        'templates' => [
            'total' => 0,
            'delivered' => 0,
            'pending' => 0,
            'in_progress' => 0
        ],
        'delivery_percentage' => 0,
        'is_fully_delivered' => false,
        'is_partially_delivered' => false,
        'pending_actions' => []
    ];
    
    foreach ($deliveries as $d) {
        if ($d['product_type'] === 'tool') {
            $stats['tools']['total']++;
            if ($d['is_delivered']) {
                $stats['tools']['delivered']++;
                $stats['delivered_items']++;
            } else {
                $stats['tools']['pending']++;
                $stats['pending_items']++;
                $stats['pending_actions'][] = [
                    'type' => 'tool_pending',
                    'delivery_id' => $d['id'],
                    'product_name' => $d['product_name'],
                    'action' => 'Check tool delivery status'
                ];
            }
        } else {
            $stats['templates']['total']++;
            if ($d['is_delivered']) {
                $stats['templates']['delivered']++;
                $stats['delivered_items']++;
            } else {
                $stats['templates']['pending']++;
                $stats['pending_items']++;
                
                // Check if in progress (has some credentials)
                if (!empty($d['hosted_domain']) || !empty($d['template_admin_username'])) {
                    $stats['templates']['in_progress']++;
                }
                
                $stats['pending_actions'][] = [
                    'type' => 'template_pending',
                    'delivery_id' => $d['id'],
                    'product_name' => $d['product_name'],
                    'action' => 'Assign domain and credentials'
                ];
            }
        }
    }
    
    if ($stats['total_items'] > 0) {
        $stats['delivery_percentage'] = round(($stats['delivered_items'] / $stats['total_items']) * 100);
        $stats['is_fully_delivered'] = ($stats['delivered_items'] === $stats['total_items']);
        $stats['is_partially_delivered'] = ($stats['delivered_items'] > 0 && $stats['delivered_items'] < $stats['total_items']);
    }
    
    return $stats;
}

/**
 * Get all orders with partial delivery status
 * Phase 5.2: For admin dashboard showing orders needing attention
 */
function getOrdersWithPartialDelivery() {
    $db = getDb();
    
    // Get all paid orders with deliveries
    $stmt = $db->prepare("
        SELECT DISTINCT po.id, po.customer_name, po.customer_email, 
               po.order_type, po.final_amount, po.status, po.created_at
        FROM pending_orders po
        JOIN deliveries d ON po.id = d.pending_order_id
        WHERE po.status = 'paid'
        ORDER BY po.created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $result = [
        'fully_delivered' => [],
        'partially_delivered' => [],
        'not_started' => []
    ];
    
    foreach ($orders as $order) {
        $stats = getOrderDeliveryStats($order['id']);
        $order['delivery_stats'] = $stats;
        
        if ($stats['is_fully_delivered']) {
            $result['fully_delivered'][] = $order;
        } elseif ($stats['is_partially_delivered']) {
            $result['partially_delivered'][] = $order;
        } else {
            $result['not_started'][] = $order;
        }
    }
    
    return $result;
}

/**
 * Update order delivery status based on delivery completion
 * Phase 5.2: Auto-update order status when all items delivered
 */
function updateOrderDeliveryStatus($orderId) {
    $db = getDb();
    $stats = getOrderDeliveryStats($orderId);
    
    $newStatus = 'in_progress';
    if ($stats['is_fully_delivered']) {
        $newStatus = 'completed';
    } elseif ($stats['is_partially_delivered']) {
        $newStatus = 'partial';
    }
    
    $stmt = $db->prepare("UPDATE pending_orders SET delivery_status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $orderId]);
    
    return $newStatus;
}

/**
 * Get delivery timeline for an order
 * Phase 5.2: Shows chronological delivery events
 */
function getDeliveryTimeline($orderId) {
    $db = getDb();
    
    $events = [];
    
    // Get order creation
    $stmt = $db->prepare("SELECT created_at, status, paid_at FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        $events[] = [
            'type' => 'order_created',
            'timestamp' => $order['created_at'],
            'title' => 'Order Placed',
            'description' => 'Customer submitted order',
            'icon' => 'bi-cart-check',
            'color' => 'blue'
        ];
        
        if ($order['paid_at']) {
            $events[] = [
                'type' => 'payment_confirmed',
                'timestamp' => $order['paid_at'],
                'title' => 'Payment Confirmed',
                'description' => 'Order marked as paid',
                'icon' => 'bi-credit-card',
                'color' => 'green'
            ];
        }
    }
    
    // Get delivery events
    $stmt = $db->prepare("
        SELECT id, product_type, product_name, delivery_status, 
               email_sent_at, delivered_at, credentials_sent_at, created_at
        FROM deliveries 
        WHERE pending_order_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$orderId]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($deliveries as $d) {
        // Delivery record created
        $events[] = [
            'type' => 'delivery_created',
            'timestamp' => $d['created_at'],
            'title' => 'Delivery Initiated',
            'description' => ($d['product_type'] === 'tool' ? '🔧 ' : '🎨 ') . $d['product_name'],
            'icon' => 'bi-box',
            'color' => 'gray'
        ];
        
        // Email sent
        if ($d['email_sent_at']) {
            $events[] = [
                'type' => 'email_sent',
                'timestamp' => $d['email_sent_at'],
                'title' => 'Email Sent',
                'description' => ($d['product_type'] === 'tool' ? 'Download links' : 'Credentials') . ' sent to customer',
                'icon' => 'bi-envelope-check',
                'color' => 'indigo'
            ];
        }
        
        // Template credentials sent
        if ($d['product_type'] === 'template' && $d['credentials_sent_at']) {
            $events[] = [
                'type' => 'credentials_sent',
                'timestamp' => $d['credentials_sent_at'],
                'title' => 'Template Delivered',
                'description' => $d['product_name'] . ' credentials sent to customer',
                'icon' => 'bi-key',
                'color' => 'green'
            ];
        }
        
        // Delivered
        if ($d['delivered_at']) {
            $events[] = [
                'type' => 'delivered',
                'timestamp' => $d['delivered_at'],
                'title' => ($d['product_type'] === 'tool' ? 'Tool' : 'Template') . ' Delivered',
                'description' => $d['product_name'] . ' successfully delivered',
                'icon' => 'bi-check-circle-fill',
                'color' => 'green'
            ];
        }
    }
    
    // Sort by timestamp
    usort($events, function($a, $b) {
        return strtotime($a['timestamp']) - strtotime($b['timestamp']);
    });
    
    return $events;
}
