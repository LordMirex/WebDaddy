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
        // CRITICAL FIX: For tools, only include those marked upload_complete = 1
        $stmt = $db->prepare("
            SELECT oi.id, oi.product_id, oi.product_type, oi.quantity,
                   COALESCE(t.name, tl.name) as product_name,
                   COALESCE(t.delivery_note, tl.delivery_note) as delivery_note,
                   COALESCE(tl.upload_complete, 1) as tool_upload_complete
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
        $failedCount = 0;
        $failedItems = [];
        
        foreach ($items as $item) {
            // FIX: Skip if delivery already exists for this order item
            if (in_array($item['id'], $existingDeliveryItemIds)) {
                error_log("⏭️  Skipping item {$item['id']} - delivery already exists");
                $skippedCount++;
                continue;
            }
            
            // NOTE: Always create delivery records for tools (even incomplete ones)
            // The upload_complete check is done in createToolDelivery() to control whether
            // download links are generated and emails are sent
            // This ensures processPendingToolDeliveries() can find these records later
            
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
                error_log("⚠️  Error creating delivery for item {$item['id']}: " . $itemError->getMessage());
                $failedCount++;
                $failedItems[] = [
                    'item_id' => $item['id'],
                    'product_name' => $item['product_name'],
                    'error' => $itemError->getMessage()
                ];
            }
        }
        
        // Update order delivery status
        $deliveryStatus = ($createdCount > 0) ? 'in_progress' : 'pending';
        $stmt = $db->prepare("UPDATE pending_orders SET delivery_status = ? WHERE id = ?");
        $stmt->execute([$deliveryStatus, $orderId]);
        
        error_log("✅ Deliveries for Order #$orderId: Created $createdCount, Skipped $skippedCount, Failed $failedCount");
        
        if ($failedCount > 0) {
            error_log("⚠️  Failed items for Order #$orderId: " . json_encode($failedItems));
        }
    } catch (Exception $e) {
        error_log("❌ Exception in createDeliveryRecords: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Create tool delivery
 * Phase 3: Enhanced with retry mechanism and improved email
 * 
 * FIXED: Now sets correct status based on whether files exist:
 * - 'pending' if no files yet (will be delivered via cron when files are uploaded)
 * - 'ready' if files exist but email not sent yet
 * - 'delivered' if files exist and email sent
 */
function createToolDelivery($orderId, $item, $retryAttempt = 0) {
    $db = getDb();
    
    $stmt = $db->prepare("SELECT customer_email, customer_name FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // CRITICAL FIX: Check if tool is marked as upload_complete before generating download links
    $stmt = $db->prepare("SELECT upload_complete FROM tools WHERE id = ?");
    $stmt->execute([$item['product_id']]);
    $toolStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    $isToolComplete = ($toolStatus && !empty($toolStatus['upload_complete']));
    
    $files = getToolFiles($item['product_id']);
    
    $downloadLinks = [];
    // Only generate download links if the tool is marked as complete
    if ($isToolComplete) {
        foreach ($files as $file) {
            $link = generateDownloadLink($file['id'], $orderId);
            if ($link) {
                $downloadLinks[] = $link;
            }
        }
    }
    
    // FIXED: Set correct initial status based on file availability AND tool completion status
    $hasFiles = !empty($downloadLinks);
    $initialStatus = ($isToolComplete && $hasFiles) ? 'ready' : 'pending';
    
    error_log("📦 createToolDelivery: Order #$orderId, Tool #{$item['product_id']}, Files: " . count($files) . ", Links: " . count($downloadLinks) . ", Status: $initialStatus");
    
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_link, delivery_note,
            retry_count
        ) VALUES (?, ?, ?, 'tool', ?, 'download', 'immediate', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        $initialStatus,
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
 * Send tool delivery email - simple, clean format
 */
function sendToolDeliveryEmail($order, $item, $downloadLinks, $orderId) {
    $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
    
    $subject = "Your {$item['product_name']} is Ready - Order #{$orderId}";
    
    $fileCount = count($downloadLinks);
    
    $bundleUrl = null;
    if ($fileCount > 1) {
        require_once __DIR__ . '/tool_files.php';
        $bundleResult = generateBundleDownloadToken($orderId, $item['product_id']);
        if ($bundleResult['success']) {
            $bundleUrl = $bundleResult['url'];
        }
    }
    
    $body = '<h2 style="color: #10b981; margin: 0 0 15px 0;">Your Product is Ready!</h2>';
    
    $body .= '<p style="color: #374151; margin: 0 0 10px 0;"><strong>Order ID:</strong> #' . $orderId . '</p>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;"><strong>Product:</strong> ' . htmlspecialchars($item['product_name']) . '</p>';
    
    if ($bundleUrl && $fileCount > 1) {
        $body .= '<p style="margin: 15px 0;"><a href="' . htmlspecialchars($bundleUrl) . '" style="background: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">Download All Files (ZIP)</a></p>';
    }
    
    $body .= '<p style="color: #374151; margin: 15px 0 10px 0;"><strong>Download Links:</strong></p>';
    
    foreach ($downloadLinks as $index => $link) {
        $fileName = htmlspecialchars($link['name'] ?? 'Download File');
        $fileUrl = htmlspecialchars($link['url'] ?? '');
        $isLink = ($link['file_type'] === 'link');
        
        $body .= '<p style="color: #374151; margin: 8px 0;">';
        $body .= $fileName . ' - ';
        $body .= '<a href="' . $fileUrl . '"' . ($isLink ? ' target="_blank"' : '') . ' style="color: #1e3a8a;">' . ($isLink ? 'Open Link' : 'Download') . '</a>';
        $body .= '</p>';
    }
    
    $body .= '<p style="color: #374151; margin: 15px 0 0 0;">';
    $body .= 'Links expire in ' . $expiryDays . ' days. Max ' . $maxDownloads . ' downloads per link.';
    $body .= '</p>';
    
    if (!empty($item['delivery_note'])) {
        $body .= '<p style="color: #374151; margin: 15px 0 0 0;"><strong>Notes:</strong> ' . htmlspecialchars($item['delivery_note']) . '</p>';
    }
    
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
 * Process pending tool deliveries when admin uploads files
 * This function is called after admin uploads files for a tool
 * It finds all pending deliveries without download links and sends emails
 */
function processPendingToolDeliveries($toolId) {
    $db = getDb();
    require_once __DIR__ . '/tool_files.php';
    
    // Get tool info
    $stmt = $db->prepare("SELECT id, name FROM tools WHERE id = ?");
    $stmt->execute([$toolId]);
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tool) {
        error_log("❌ processPendingToolDeliveries: Tool not found: $toolId");
        return ['success' => false, 'message' => 'Tool not found', 'processed' => 0, 'sent' => 0];
    }
    
    // Get tool files
    $files = getToolFiles($toolId);
    if (empty($files)) {
        error_log("❌ processPendingToolDeliveries: No files found for tool $toolId");
        return ['success' => false, 'message' => 'No files found for tool', 'processed' => 0, 'sent' => 0];
    }
    
    // Find pending deliveries for this tool that don't have download links yet
    // (delivery_link is NULL, empty, or contains empty array [])
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.product_id = ? 
          AND d.product_type = 'tool'
          AND po.status = 'paid'
          AND (d.delivery_link IS NULL OR d.delivery_link = '' OR d.delivery_link = '[]')
        ORDER BY d.created_at DESC
    ");
    $stmt->execute([$toolId]);
    $pendingDeliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("📧 processPendingToolDeliveries: Found " . count($pendingDeliveries) . " pending deliveries for tool $toolId ({$tool['name']})");
    
    $processed = 0;
    $sent = 0;
    
    foreach ($pendingDeliveries as $delivery) {
        $processed++;
        $orderId = $delivery['pending_order_id'];
        
        // Generate download links for each file
        $downloadLinks = [];
        foreach ($files as $file) {
            $link = generateDownloadLink($file['id'], $orderId);
            if ($link) {
                $downloadLinks[] = $link;
            }
        }
        
        if (empty($downloadLinks)) {
            error_log("⚠️ processPendingToolDeliveries: No download links generated for delivery {$delivery['id']}");
            continue;
        }
        
        // Update delivery with download links
        $updateStmt = $db->prepare("
            UPDATE deliveries SET 
                delivery_link = ?,
                delivery_status = 'ready'
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($downloadLinks), $delivery['id']]);
        
        // Send email to customer
        $item = [
            'product_name' => $delivery['product_name'],
            'delivery_note' => $delivery['delivery_note'],
            'product_id' => $delivery['product_id']
        ];
        $order = [
            'customer_email' => $delivery['customer_email'],
            'customer_name' => $delivery['customer_name']
        ];
        
        $emailSent = sendToolDeliveryEmail($order, $item, $downloadLinks, $orderId);
        
        if ($emailSent) {
            $sent++;
            $updateStmt = $db->prepare("
                UPDATE deliveries SET 
                    delivery_status = 'delivered',
                    email_sent_at = datetime('now', '+1 hour'),
                    delivered_at = datetime('now', '+1 hour')
                WHERE id = ?
            ");
            $updateStmt->execute([$delivery['id']]);
            
            // Record email event
            recordEmailEvent($orderId, 'tool_delivery_delayed', [
                'email' => $delivery['customer_email'],
                'subject' => "Your {$delivery['product_name']} is Ready to Download!",
                'sent' => true,
                'product_name' => $delivery['product_name'],
                'file_count' => count($downloadLinks),
                'delayed_delivery' => true
            ]);
            
            error_log("✅ processPendingToolDeliveries: Email sent to {$delivery['customer_email']} for order #$orderId");
        } else {
            error_log("❌ processPendingToolDeliveries: Email failed to {$delivery['customer_email']} for order #$orderId");
        }
    }
    
    error_log("📧 processPendingToolDeliveries: Completed - Processed: $processed, Emails Sent: $sent");
    
    return [
        'success' => true, 
        'message' => "Processed $processed deliveries, sent $sent emails",
        'processed' => $processed, 
        'sent' => $sent
    ];
}

/**
 * Send update emails when new files are added to a tool that's already marked complete
 * FIXED: Only sends emails for NEW files that haven't been delivered yet (prevents duplicates)
 * Uses download_tokens table to track which files have already been sent to each order
 * 
 * @param int $toolId The tool ID
 * @return array Result with success status and counts
 */
function sendToolUpdateEmails($toolId) {
    $db = getDb();
    require_once __DIR__ . '/tool_files.php';
    
    // Get tool info
    $stmt = $db->prepare("SELECT id, name, upload_complete FROM tools WHERE id = ?");
    $stmt->execute([$toolId]);
    $tool = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tool) {
        return ['success' => false, 'message' => 'Tool not found', 'sent' => 0];
    }
    
    // Only send updates for tools marked as complete
    if (empty($tool['upload_complete'])) {
        return ['success' => false, 'message' => 'Tool not marked as complete', 'sent' => 0];
    }
    
    // Get all current files for this tool
    $files = getToolFiles($toolId);
    if (empty($files)) {
        return ['success' => false, 'message' => 'No files found for tool', 'sent' => 0];
    }
    
    // Get file IDs for this tool
    $allFileIds = array_column($files, 'id');
    
    // Find all DELIVERED orders for this tool (already received initial delivery)
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.product_id = ? 
          AND d.product_type = 'tool'
          AND d.delivery_status = 'delivered'
          AND po.status = 'paid'
        ORDER BY d.delivered_at DESC
    ");
    $stmt->execute([$toolId]);
    $deliveredOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("📧 sendToolUpdateEmails: Found " . count($deliveredOrders) . " delivered orders for tool $toolId ({$tool['name']})");
    
    $sent = 0;
    $skipped = 0;
    
    foreach ($deliveredOrders as $delivery) {
        $orderId = $delivery['pending_order_id'];
        
        // CRITICAL FIX: Check which files have ALREADY been sent to this order
        // This prevents duplicate emails when new files are added
        $existingTokensStmt = $db->prepare("
            SELECT file_id FROM download_tokens 
            WHERE pending_order_id = ?
        ");
        $existingTokensStmt->execute([$orderId]);
        $alreadySentFileIds = $existingTokensStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Find NEW files that haven't been sent to this order yet
        $newFileIds = array_diff($allFileIds, $alreadySentFileIds);
        
        if (empty($newFileIds)) {
            // All files already delivered to this order - skip
            $skipped++;
            error_log("⏭️  sendToolUpdateEmails: Order #$orderId already has all files - skipping");
            continue;
        }
        
        // Generate download links ONLY for NEW files
        $newDownloadLinks = [];
        foreach ($files as $file) {
            if (in_array($file['id'], $newFileIds)) {
                $link = generateDownloadLink($file['id'], $orderId);
                if ($link) {
                    $newDownloadLinks[] = $link;
                }
            }
        }
        
        if (empty($newDownloadLinks)) {
            continue;
        }
        
        // Get existing links from delivery record and merge with new ones
        $existingLinks = json_decode($delivery['delivery_link'], true) ?? [];
        $allLinks = array_merge($existingLinks, $newDownloadLinks);
        
        // Update delivery record with ALL links (existing + new)
        $updateStmt = $db->prepare("
            UPDATE deliveries SET 
                delivery_link = ?
            WHERE id = ?
        ");
        $updateStmt->execute([json_encode($allLinks), $delivery['id']]);
        
        // Send update email with ONLY the NEW files
        $order = [
            'customer_email' => $delivery['customer_email'],
            'customer_name' => $delivery['customer_name']
        ];
        $item = [
            'product_name' => $delivery['product_name'],
            'delivery_note' => $delivery['delivery_note'],
            'product_id' => $delivery['product_id']
        ];
        
        // Pass only new download links to the email function
        $emailSent = sendToolUpdateEmail($order, $item, $newDownloadLinks, $orderId);
        
        if ($emailSent) {
            $sent++;
            
            // Record email event with count of NEW files only
            recordEmailEvent($orderId, 'tool_update', [
                'email' => $delivery['customer_email'],
                'subject' => "New Files Added - {$delivery['product_name']}",
                'sent' => true,
                'product_name' => $delivery['product_name'],
                'new_file_count' => count($newDownloadLinks),
                'total_file_count' => count($allLinks)
            ]);
            
            error_log("✅ sendToolUpdateEmails: Update email sent to {$delivery['customer_email']} for order #$orderId with " . count($newDownloadLinks) . " new files");
        }
    }
    
    error_log("📧 sendToolUpdateEmails: Completed - Sent $sent update emails, Skipped $skipped (no new files) for tool $toolId");
    
    return [
        'success' => true,
        'message' => "Sent $sent update emails" . ($skipped > 0 ? " (skipped $skipped - already have all files)" : ""),
        'sent' => $sent,
        'skipped' => $skipped,
        'total_orders' => count($deliveredOrders)
    ];
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
 * Send template delivery email with domain details - simple, clean format
 */
function sendTemplateDeliveryEmail($order, $delivery, $hostedDomain, $hostedUrl, $adminNotes = '') {
    $subject = "Your Website Template is Ready - Order #" . $delivery['pending_order_id'];
    
    $body = '<h2 style="color: #10b981; margin: 0 0 15px 0;">Your Template is Ready!</h2>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;">Your website template <strong>' . htmlspecialchars($delivery['product_name']) . '</strong> has been deployed and is ready to use!</p>';
    
    $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Domain:</strong> ' . htmlspecialchars($hostedDomain) . '</p>';
    $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Website URL:</strong> <a href="' . htmlspecialchars($hostedUrl) . '" style="color: #1e3a8a;">' . htmlspecialchars($hostedUrl) . '</a></p>';
    
    if (!empty($adminNotes)) {
        $body .= '<p style="color: #374151; margin: 15px 0 5px 0;"><strong>Special Instructions:</strong></p>';
        $body .= '<p style="color: #374151; margin: 0;">' . htmlspecialchars($adminNotes) . '</p>';
    }
    
    $body .= '<p style="margin: 20px 0;"><a href="' . htmlspecialchars($hostedUrl) . '" style="background: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">Visit Your Website</a></p>';
    
    $body .= '<p style="color: #374151; margin: 15px 0 0 0;">Your website is now live. If you have any questions, please reach out to us via WhatsApp.</p>';
    
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
    
    $subject = "Your Website Template is Ready - Order #" . $delivery['pending_order_id'];
    
    $body = '<h2 style="color: #10b981; margin: 0 0 15px 0;">Your Template is Ready!</h2>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;">Your website template <strong>' . htmlspecialchars($delivery['product_name']) . '</strong> has been deployed and is ready to use!</p>';
    
    $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Domain:</strong> ' . htmlspecialchars($hostedDomain) . '</p>';
    $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Website URL:</strong> <a href="' . htmlspecialchars($hostedUrl) . '" style="color: #1e3a8a;">' . htmlspecialchars($hostedUrl) . '</a></p>';
    
    if (!empty($adminUsername) || !empty($loginUrl)) {
        $body .= '<p style="color: #374151; margin: 15px 0 5px 0;"><strong>Login Credentials:</strong></p>';
        $body .= '<p style="color: #374151; font-size: 13px; margin-bottom: 10px;"><em>Important: Save these credentials securely. Change your password after first login.</em></p>';
        
        if (!empty($loginUrl)) {
            $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Admin URL:</strong> <a href="' . htmlspecialchars($loginUrl) . '" style="color: #1e3a8a;">' . htmlspecialchars($loginUrl) . '</a></p>';
        }
        if (!empty($adminUsername)) {
            $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Username:</strong> ' . htmlspecialchars($adminUsername) . '</p>';
        }
        if (!empty($decryptedPassword)) {
            $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Password:</strong> ' . htmlspecialchars($decryptedPassword) . '</p>';
        }
        
        $hostingLabels = [
            'wordpress' => 'WordPress',
            'cpanel' => 'cPanel',
            'custom' => 'Custom Admin',
            'static' => 'Static Site'
        ];
        $hostingLabel = $hostingLabels[$hostingProvider] ?? 'Custom';
        $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Hosting Type:</strong> ' . htmlspecialchars($hostingLabel) . '</p>';
    }
    
    if (!empty($adminNotes)) {
        $body .= '<p style="color: #374151; margin: 15px 0 5px 0;"><strong>Special Instructions:</strong></p>';
        $body .= '<p style="color: #374151; margin: 0;">' . htmlspecialchars($adminNotes) . '</p>';
    }
    
    $body .= '<p style="margin: 20px 0;"><a href="' . htmlspecialchars($hostedUrl) . '" style="background: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">Visit Your Website</a></p>';
    
    $body .= '<p style="color: #374151; margin: 15px 0 0 0;">Your website is now live. If you have any questions, please reach out to us via WhatsApp.</p>';
    
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
    $subject = 'Alert: ' . count($overdue) . ' Template(s) Pending Delivery for 24+ Hours';
    
    $body = '<div style="font-family: Arial, sans-serif; max-width: 600px;">';
    $body .= '<h2 style="color: #dc2626;">Delivery Alert</h2>';
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
    
    $subject = "Order #{$orderId} - Delivery Update";
    
    $body = '<h2 style="color: #1e3a8a; margin: 0 0 15px 0;">Order Delivery Update</h2>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;">Here is an update on your order delivery status.</p>';
    
    $body .= '<p style="color: #374151; margin: 5px 0;"><strong>Delivery Progress:</strong> ' . $stats['delivered_items'] . ' of ' . $stats['total_items'] . ' items delivered (' . $stats['delivery_percentage'] . '%)</p>';
    
    // Tools Section
    $toolDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'tool'; });
    if (!empty($toolDeliveries)) {
        $body .= '<p style="color: #374151; margin: 15px 0 10px 0;"><strong>Digital Tools:</strong></p>';
        foreach ($toolDeliveries as $td) {
            $status = in_array($td['delivery_status'], ['delivered', 'ready', 'sent']) ? 'Delivered' : 'Pending';
            $body .= '<p style="color: #374151; margin: 5px 0;">- ' . htmlspecialchars($td['product_name']) . ': ' . $status . '</p>';
        }
        if ($stats['tools']['delivered'] === $stats['tools']['total']) {
            $body .= '<p style="color: #374151; margin: 10px 0;">All tools have been delivered to your email.</p>';
        }
    }
    
    // Templates Section
    $templateDeliveries = array_filter($deliveries, function($d) { return $d['product_type'] === 'template'; });
    if (!empty($templateDeliveries)) {
        $body .= '<p style="color: #374151; margin: 15px 0 10px 0;"><strong>Website Templates:</strong></p>';
        foreach ($templateDeliveries as $td) {
            if ($td['delivery_status'] === 'delivered') {
                $status = 'Delivered';
            } elseif (!empty($td['hosted_domain'])) {
                $status = 'In Progress';
            } else {
                $status = 'Setting Up';
            }
            $body .= '<p style="color: #374151; margin: 5px 0;">- ' . htmlspecialchars($td['product_name']) . ': ' . $status . '</p>';
        }
        if ($stats['templates']['pending'] > 0) {
            $body .= '<p style="color: #374151; margin: 10px 0;">Our team is setting up your website template(s). You will receive login credentials via email once complete (usually within 24-48 hours).</p>';
        } else {
            $body .= '<p style="color: #374151; margin: 10px 0;">All templates have been delivered.</p>';
        }
    }
    
    $body .= '<p style="color: #374151; margin: 15px 0 0 0;">If you need any help, please contact us on WhatsApp.</p>';
    
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
    $stmt = $db->prepare("SELECT created_at, status, payment_verified_at FROM pending_orders WHERE id = ?");
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
        
        if ($order['payment_verified_at']) {
            $events[] = [
                'type' => 'payment_confirmed',
                'timestamp' => $order['payment_verified_at'],
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
            'description' => ($d['product_type'] === 'tool' ? 'Tool: ' : 'Template: ') . $d['product_name'],
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

/**
 * Send INDIVIDUAL tool delivery emails for each ready tool
 * Called automatically after createDeliveryRecords() when payment is confirmed
 * UPDATED: Sends separate emails for each ready tool instead of one combined email
 * This ensures customers receive dedicated emails per tool that is ready for download
 */
function sendAllToolDeliveryEmailsForOrder($orderId) {
    $db = getDb();
    
    error_log("📧 SENDING INDIVIDUAL TOOL DELIVERY EMAILS: Starting for Order #$orderId");
    
    try {
        // Get order and customer info
        $orderStmt = $db->prepare("SELECT customer_email, customer_name FROM pending_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order || empty($order['customer_email'])) {
            error_log("❌ TOOL DELIVERY EMAIL: No customer email found for Order #$orderId");
            return false;
        }
        
        // Get ALL tool deliveries for this order that have files ready
        $toolsStmt = $db->prepare("
            SELECT d.id, d.product_id, d.product_name, d.delivery_link, d.delivery_note
            FROM deliveries d
            WHERE d.pending_order_id = ? AND d.product_type = 'tool' AND d.delivery_status IN ('ready', 'delivered')
        ");
        $toolsStmt->execute([$orderId]);
        $toolDeliveries = $toolsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($toolDeliveries)) {
            error_log("⚠️  TOOL DELIVERY EMAIL: No ready tool deliveries found for Order #$orderId");
            return false;
        }
        
        // Filter to only tools that actually have download links (files are ready)
        $readyTools = [];
        foreach ($toolDeliveries as $toolDelivery) {
            $downloadLinks = json_decode($toolDelivery['delivery_link'], true) ?? [];
            if (!empty($downloadLinks)) {
                $toolDelivery['parsed_links'] = $downloadLinks;
                $readyTools[] = $toolDelivery;
            }
        }
        
        if (empty($readyTools)) {
            error_log("⚠️  TOOL DELIVERY EMAIL: No tools with download links ready for Order #$orderId");
            return false;
        }
        
        $totalTools = count($readyTools);
        error_log("📧 TOOL DELIVERY EMAIL: Found $totalTools tools with files ready for Order #$orderId - sending individual emails");
        
        require_once __DIR__ . '/mailer.php';
        
        $successCount = 0;
        $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
        $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
        
        // Send INDIVIDUAL email for each ready tool
        foreach ($readyTools as $index => $toolDelivery) {
            $toolNumber = $index + 1;
            $downloadLinks = $toolDelivery['parsed_links'];
            $productName = $toolDelivery['product_name'];
            
            // Calculate tool size
            $toolSize = 0;
            foreach ($downloadLinks as $link) {
                $toolSize += $link['file_size'] ?? 0;
            }
            $toolSizeFormatted = formatFileSize($toolSize);
            $fileCount = count($downloadLinks);
            
            // Generate bundle URL if multiple files
            $bundleUrl = null;
            if ($fileCount > 1) {
                require_once __DIR__ . '/tool_files.php';
                $bundleResult = generateBundleDownloadToken($orderId, $toolDelivery['product_id']);
                if ($bundleResult['success']) {
                    $bundleUrl = $bundleResult['url'];
                }
            }
            
            // Build individual email for this tool - simple, clean format
            $subject = "Your " . $productName . " is Ready - Order #" . $orderId;
            
            $body = '<h2 style="color: #10b981; margin: 0 0 15px 0;">Your Product is Ready!</h2>';
            
            $body .= '<p style="color: #374151; margin: 0 0 10px 0;"><strong>Order ID:</strong> #' . $orderId . '</p>';
            $body .= '<p style="color: #374151; margin: 0 0 15px 0;"><strong>Product:</strong> ' . htmlspecialchars($productName) . '</p>';
            
            // Bundle download option if multiple files
            if ($bundleUrl && $fileCount > 1) {
                $body .= '<p style="margin: 15px 0;"><a href="' . htmlspecialchars($bundleUrl) . '" style="background: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">Download All Files (ZIP)</a></p>';
            }
            
            // Individual download links
            $body .= '<p style="color: #374151; margin: 15px 0 10px 0;"><strong>Download Links:</strong></p>';
            
            foreach ($downloadLinks as $link) {
                $fileName = htmlspecialchars($link['name'] ?? 'Download File');
                $fileUrl = htmlspecialchars($link['url'] ?? '');
                $isLink = ($link['file_type'] === 'link');
                $isExternal = preg_match('/^https?:\/\//i', $link['file_path'] ?? '');
                
                $body .= '<p style="color: #374151; margin: 8px 0;">';
                $body .= $fileName . ' - ';
                if ($isLink || $isExternal) {
                    $body .= '<a href="' . $fileUrl . '" target="_blank" style="color: #1e3a8a;">Open Link</a>';
                } else {
                    $body .= '<a href="' . $fileUrl . '" style="color: #1e3a8a;">Download</a>';
                }
                $body .= '</p>';
            }
            
            // Expiry info
            $expiryDate = date('F j, Y', strtotime("+{$expiryDays} days"));
            $body .= '<p style="color: #374151; margin: 15px 0 0 0;">';
            $body .= 'Links expire on ' . $expiryDate . ' (' . $expiryDays . ' days). Max ' . $maxDownloads . ' downloads per link.';
            $body .= '</p>';
            
            // Delivery note if any
            if (!empty($toolDelivery['delivery_note'])) {
                $body .= '<p style="color: #374151; margin: 15px 0 0 0;"><strong>Notes:</strong> ' . htmlspecialchars($toolDelivery['delivery_note']) . '</p>';
            }
            
            // Send individual email for this tool
            $emailSent = sendEmail($order['customer_email'], $subject, createEmailTemplate($subject, $body, $order['customer_name']));
            
            if ($emailSent) {
                $successCount++;
                error_log("✅ TOOL DELIVERY EMAIL: Sent email $toolNumber/$totalTools for '" . $productName . "' to " . $order['customer_email']);
                
                // Update delivery status
                $updateStmt = $db->prepare("
                    UPDATE deliveries SET email_sent_at = datetime('now', '+1 hour'), delivery_status = 'delivered'
                    WHERE id = ?
                ");
                $updateStmt->execute([$toolDelivery['id']]);
                
                // Record email event
                recordEmailEvent($orderId, 'tool_delivery', [
                    'email' => $order['customer_email'],
                    'subject' => $subject,
                    'sent' => true,
                    'product_name' => $productName,
                    'file_count' => $fileCount,
                    'email_number' => $toolNumber,
                    'total_emails' => $totalTools
                ]);
            } else {
                error_log("❌ TOOL DELIVERY EMAIL: Failed to send email $toolNumber/$totalTools for '" . $productName . "'");
            }
            
            // Small delay between emails to avoid rate limiting
            if ($index < count($readyTools) - 1) {
                usleep(500000); // 0.5 second delay
            }
        }
        
        error_log("✅ TOOL DELIVERY EMAIL: Completed sending $successCount/$totalTools individual tool emails for Order #$orderId");
        
        return $successCount > 0;
        
    } catch (Exception $e) {
        error_log("❌ TOOL DELIVERY EMAIL: Exception for Order #$orderId: " . $e->getMessage());
        return false;
    }
}

/**
 * Process ALL pending tool deliveries across all tools
 * This is the main cron job function that runs every 20-30 minutes
 * 
 * It does TWO things:
 * 1. Finds pending deliveries where tool files now exist -> sends emails
 * 2. Finds delivered tools that now have MORE files -> sends update notifications
 */
function processAllPendingToolDeliveries() {
    $db = getDb();
    require_once __DIR__ . '/tool_files.php';
    
    $result = [
        'tools_scanned' => 0,
        'pending_found' => 0,
        'emails_sent' => 0,
        'updates_sent' => 0,
        'errors' => []
    ];
    
    error_log("📦 CRON: Starting processAllPendingToolDeliveries");
    
    try {
        // STEP 1: Get all tools that have at least one file uploaded AND are marked as upload complete
        // The upload_complete flag ensures we only send emails when admin is done uploading all files
        $toolsWithFiles = $db->query("
            SELECT DISTINCT t.id, t.name, t.upload_complete, COUNT(tf.id) as file_count
            FROM tools t
            INNER JOIN tool_files tf ON t.id = tf.tool_id
            WHERE t.active = 1 AND tf.file_name IS NOT NULL AND t.upload_complete = 1
            GROUP BY t.id
            ORDER BY t.id
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        $result['tools_scanned'] = count($toolsWithFiles);
        error_log("📦 CRON: Found {$result['tools_scanned']} tools with files");
        
        foreach ($toolsWithFiles as $tool) {
            $toolId = $tool['id'];
            $toolName = $tool['name'];
            $currentFileCount = $tool['file_count'];
            
            // STEP 2A: Find PENDING deliveries (no download links yet)
            // FIXED: Include both 'pending' (manual orders) and 'paid' (verified Paystack orders)
            $pendingStmt = $db->prepare("
                SELECT d.*, po.customer_email, po.customer_name
                FROM deliveries d
                JOIN pending_orders po ON d.pending_order_id = po.id
                WHERE d.product_id = ? 
                  AND d.product_type = 'tool'
                  AND po.status IN ('pending', 'paid')
                  AND (d.delivery_link IS NULL OR d.delivery_link = '' OR d.delivery_link = '[]')
                ORDER BY d.created_at ASC
            ");
            $pendingStmt->execute([$toolId]);
            $pendingDeliveries = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($pendingDeliveries)) {
                $result['pending_found'] += count($pendingDeliveries);
                error_log("📦 CRON: Tool #$toolId ($toolName) has " . count($pendingDeliveries) . " pending deliveries");
                
                // Process each pending delivery
                foreach ($pendingDeliveries as $delivery) {
                    try {
                        $sent = processAndSendToolDelivery($delivery, $toolId, false);
                        if ($sent) {
                            $result['emails_sent']++;
                        }
                    } catch (Exception $e) {
                        $result['errors'][] = "Tool $toolId, Delivery {$delivery['id']}: " . $e->getMessage();
                        error_log("❌ CRON: Error processing delivery {$delivery['id']}: " . $e->getMessage());
                    }
                }
            }
            
            // STEP 2B: Find DELIVERED tools that now have MORE files (update notification)
            // Get deliveries that were delivered and track how many files were in the original delivery
            // FIXED: Include both 'pending' and 'paid' order statuses
            $deliveredStmt = $db->prepare("
                SELECT d.*, po.customer_email, po.customer_name,
                       d.delivery_link as existing_links
                FROM deliveries d
                JOIN pending_orders po ON d.pending_order_id = po.id
                WHERE d.product_id = ? 
                  AND d.product_type = 'tool'
                  AND d.delivery_status = 'delivered'
                  AND po.status IN ('pending', 'paid')
                ORDER BY d.created_at ASC
            ");
            $deliveredStmt->execute([$toolId]);
            $deliveredItems = $deliveredStmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($deliveredItems as $delivery) {
                // Count how many files were in the original delivery
                $existingLinks = json_decode($delivery['existing_links'], true) ?? [];
                $existingFileCount = count($existingLinks);
                
                // Check if there are MORE files now than when originally delivered
                if ($currentFileCount > $existingFileCount) {
                    error_log("📦 CRON: Tool #$toolId has new files! Was $existingFileCount, now $currentFileCount - notifying order #{$delivery['pending_order_id']}");
                    
                    try {
                        $sent = processAndSendToolDelivery($delivery, $toolId, true);
                        if ($sent) {
                            $result['updates_sent']++;
                        }
                    } catch (Exception $e) {
                        $result['errors'][] = "Tool $toolId update, Delivery {$delivery['id']}: " . $e->getMessage();
                        error_log("❌ CRON: Error sending update for delivery {$delivery['id']}: " . $e->getMessage());
                    }
                }
            }
        }
        
        error_log("📦 CRON: Completed - Pending: {$result['pending_found']}, Emails: {$result['emails_sent']}, Updates: {$result['updates_sent']}");
        
    } catch (Exception $e) {
        $result['errors'][] = "Main error: " . $e->getMessage();
        error_log("❌ CRON: processAllPendingToolDeliveries failed: " . $e->getMessage());
    }
    
    return $result;
}

/**
 * Process a single tool delivery and send email
 * Used by both pending delivery processing and update notifications
 * 
 * @param array $delivery The delivery record
 * @param int $toolId The tool ID
 * @param bool $isUpdate True if this is an update notification (new files added)
 * @return bool True if email was sent successfully
 */
function processAndSendToolDelivery($delivery, $toolId, $isUpdate = false) {
    $db = getDb();
    require_once __DIR__ . '/tool_files.php';
    
    $orderId = $delivery['pending_order_id'];
    
    // Get all current files for this tool
    $files = getToolFiles($toolId);
    if (empty($files)) {
        error_log("⚠️ processAndSendToolDelivery: No files found for tool $toolId");
        return false;
    }
    
    // Generate fresh download links for all files
    $downloadLinks = [];
    foreach ($files as $file) {
        $link = generateDownloadLink($file['id'], $orderId);
        if ($link) {
            $downloadLinks[] = $link;
        }
    }
    
    if (empty($downloadLinks)) {
        error_log("⚠️ processAndSendToolDelivery: No download links generated for tool $toolId, order $orderId");
        return false;
    }
    
    // Update delivery record with new links
    $updateStmt = $db->prepare("
        UPDATE deliveries SET 
            delivery_link = ?,
            delivery_status = 'ready'
        WHERE id = ?
    ");
    $updateStmt->execute([json_encode($downloadLinks), $delivery['id']]);
    
    // Send email
    $order = [
        'customer_email' => $delivery['customer_email'],
        'customer_name' => $delivery['customer_name']
    ];
    $item = [
        'product_name' => $delivery['product_name'],
        'delivery_note' => $delivery['delivery_note'],
        'product_id' => $delivery['product_id']
    ];
    
    // Choose email type based on whether this is a new delivery or update
    if ($isUpdate) {
        $emailSent = sendToolUpdateEmail($order, $item, $downloadLinks, $orderId);
    } else {
        $emailSent = sendToolDeliveryEmail($order, $item, $downloadLinks, $orderId);
    }
    
    if ($emailSent) {
        // Update delivery status
        $updateStmt = $db->prepare("
            UPDATE deliveries SET 
                delivery_status = 'delivered',
                email_sent_at = datetime('now', '+1 hour'),
                delivered_at = datetime('now', '+1 hour')
            WHERE id = ?
        ");
        $updateStmt->execute([$delivery['id']]);
        
        // Record email event
        recordEmailEvent($orderId, $isUpdate ? 'tool_update' : 'tool_delivery_delayed', [
            'email' => $delivery['customer_email'],
            'subject' => $isUpdate 
                ? "New Files Added to {$delivery['product_name']}" 
                : "Your {$delivery['product_name']} is Ready!",
            'sent' => true,
            'product_name' => $delivery['product_name'],
            'file_count' => count($downloadLinks),
            'is_update' => $isUpdate
        ]);
        
        error_log("✅ processAndSendToolDelivery: Email sent to {$delivery['customer_email']} for order #$orderId" . ($isUpdate ? " (UPDATE)" : ""));
        return true;
    }
    
    error_log("❌ processAndSendToolDelivery: Email failed to {$delivery['customer_email']} for order #$orderId");
    return false;
}

/**
 * Send tool update notification email when new files are added
 * Different from initial delivery email - emphasizes the update
 */
function sendToolUpdateEmail($order, $item, $downloadLinks, $orderId) {
    $expiryDays = defined('DOWNLOAD_LINK_EXPIRY_DAYS') ? DOWNLOAD_LINK_EXPIRY_DAYS : 30;
    $maxDownloads = defined('MAX_DOWNLOAD_ATTEMPTS') ? MAX_DOWNLOAD_ATTEMPTS : 10;
    $fileCount = count($downloadLinks);
    
    $subject = "New Files Added - {$item['product_name']} - Order #{$orderId}";
    
    // Bundle URL for multiple files
    $bundleUrl = null;
    if ($fileCount > 1 && isset($item['product_id'])) {
        require_once __DIR__ . '/tool_files.php';
        $bundleResult = generateBundleDownloadToken($orderId, $item['product_id']);
        if ($bundleResult['success']) {
            $bundleUrl = $bundleResult['url'];
        }
    }
    
    $body = '<h2 style="color: #10b981; margin: 0 0 15px 0;">New Files Added!</h2>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;">New files have been added to your product: <strong>' . htmlspecialchars($item['product_name']) . '</strong></p>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;"><strong>Order ID:</strong> #' . $orderId . '</p>';
    
    // Bundle download option
    if ($bundleUrl && $fileCount > 1) {
        $body .= '<p style="margin: 15px 0;"><a href="' . htmlspecialchars($bundleUrl) . '" style="background: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;">Download All Files (ZIP)</a></p>';
    }
    
    // Individual download links
    $body .= '<p style="color: #374151; margin: 15px 0 10px 0;"><strong>Download Links:</strong></p>';
    
    foreach ($downloadLinks as $link) {
        $fileName = htmlspecialchars($link['name'] ?? 'Download File');
        $fileUrl = htmlspecialchars($link['url'] ?? '');
        $isLink = ($link['file_type'] === 'link');
        $isExternal = preg_match('/^https?:\/\//i', $link['file_path'] ?? '');
        
        $body .= '<p style="color: #374151; margin: 8px 0;">';
        $body .= $fileName . ' - ';
        if ($isLink || $isExternal) {
            $body .= '<a href="' . $fileUrl . '" target="_blank" style="color: #1e3a8a;">Open Link</a>';
        } else {
            $body .= '<a href="' . $fileUrl . '" style="color: #1e3a8a;">Download</a>';
        }
        $body .= '</p>';
    }
    
    $expiryDate = date('F j, Y', strtotime("+{$expiryDays} days"));
    $body .= '<p style="color: #374151; margin: 15px 0 0 0;">Links expire on ' . $expiryDate . ' (' . $expiryDays . ' days). Max ' . $maxDownloads . ' downloads per link.</p>';
    
    require_once __DIR__ . '/mailer.php';
    return sendEmail($order['customer_email'], $subject, createEmailTemplate($subject, $body, $order['customer_name']));
}
