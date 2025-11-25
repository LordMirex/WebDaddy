<?php
/**
 * Delivery System
 * Handles tool and template delivery
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/tool_files.php';

/**
 * Create delivery records for an order
 */
function createDeliveryRecords($orderId) {
    $db = getDb();
    
    // Get order items
    $stmt = $db->prepare("
        SELECT oi.*, 
               CASE 
                   WHEN oi.product_type = 'template' THEN t.name
                   WHEN oi.product_type = 'tool' THEN tl.name
               END as product_name,
               CASE 
                   WHEN oi.product_type = 'template' THEN t.delivery_note
                   WHEN oi.product_type = 'tool' THEN tl.delivery_note
               END as delivery_note
        FROM order_items oi
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
        WHERE oi.pending_order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        if ($item['product_type'] === 'tool') {
            createToolDelivery($orderId, $item);
        } else {
            createTemplateDelivery($orderId, $item);
        }
    }
    
    // Update order delivery status
    $stmt = $db->prepare("UPDATE pending_orders SET delivery_status = 'in_progress' WHERE id = ?");
    $stmt->execute([$orderId]);
}

/**
 * Create tool delivery
 */
function createToolDelivery($orderId, $item) {
    $db = getDb();
    
    // Get customer email from order
    $stmt = $db->prepare("SELECT customer_email, customer_name FROM pending_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get tool files
    $files = getToolFiles($item['product_id']);
    
    // Generate download links
    $downloadLinks = [];
    foreach ($files as $file) {
        $link = generateDownloadLink($file['id'], $orderId);
        if ($link) {
            $downloadLinks[] = $link;
        }
    }
    
    // Create delivery record
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_link, delivery_note
        ) VALUES (?, ?, ?, 'tool', ?, 'download', 'immediate', 'ready', ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        json_encode($downloadLinks),
        $item['delivery_note']
    ]);
    
    $deliveryId = $db->lastInsertId();
    
    // Send delivery email with download links
    if ($order && $order['customer_email'] && !empty($downloadLinks)) {
        $subject = "Your {$item['product_name']} is Ready to Download! - Order #{$orderId}";
        
        // Build download links HTML
        $downloadLinksHtml = '<p><strong>📥 Download Your Files:</strong></p>';
        $downloadLinksHtml .= '<div style="background-color: #f0f0f0; padding: 15px; border-radius: 5px; margin: 15px 0;">';
        
        foreach ($downloadLinks as $link) {
            $fileName = htmlspecialchars($link['name'] ?? 'Download File');
            $fileUrl = htmlspecialchars($link['url'] ?? '');
            $expiryDate = htmlspecialchars($link['expires_at'] ?? 'Not specified');
            
            $downloadLinksHtml .= '<div style="margin-bottom: 12px;">';
            $downloadLinksHtml .= '<a href="' . $fileUrl . '" style="background-color: #1e3a8a; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; font-weight: bold;">📥 Download: ' . $fileName . '</a>';
            $downloadLinksHtml .= '<br><small style="color: #666;">Expires: ' . $expiryDate . '</small>';
            $downloadLinksHtml .= '</div>';
        }
        
        $downloadLinksHtml .= '</div>';
        $downloadLinksHtml .= '<p style="color: #666; font-size: 12px;">Links expire on ' . htmlspecialchars($downloadLinks[0]['expires_at'] ?? 'the expiry date') . '. Download and save your files before they expire.</p>';
        
        $body = '<p>Great news! Your tool <strong>' . htmlspecialchars($item['product_name']) . '</strong> is ready for download!</p>' . $downloadLinksHtml;
        
        sendEmail($order['customer_email'], $subject, createEmailTemplate($subject, $body, $order['customer_name']));
    }
    
    return $deliveryId;
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
 * Mark template as ready
 */
function markTemplateReady($deliveryId, $hostedUrl, $adminNotes = '') {
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET delivery_status = 'ready',
            hosted_url = ?,
            admin_notes = ?,
            delivered_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$hostedUrl, $adminNotes, $deliveryId]);
    
    // Send "template ready" email directly
    // This will be sent by admin when template is ready
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
