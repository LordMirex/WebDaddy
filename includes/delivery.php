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
            delivered_at = datetime('now'),
            email_sent_at = datetime('now')
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
            updated_at = datetime('now')
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
            delivered_at = datetime('now'),
            email_sent_at = datetime('now'),
            credentials_sent_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$deliveryId]);
    
    $emailSent = sendTemplateDeliveryEmailWithCredentials($order, $delivery, $decryptedPassword);
    
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
