<?php
/**
 * Delivery State Management System (Update 17)
 * Bulletproof delivery with state machine, SLA tracking, and auto-recovery
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

define('DELIVERY_STATES', [
    'pending', 'processing', 'ready', 'downloaded', 'completed',
    'failed', 'stalled', 'expired', 'issue'
]);

define('SLA_MINUTES', [
    'tool' => 10,
    'template' => 2880,
    'api_key' => 10
]);

/**
 * Update delivery state with history tracking
 */
function markDeliveryState($deliveryId, $newState, $reason = null) {
    $db = getDb();
    
    if (!in_array($newState, DELIVERY_STATES)) {
        return false;
    }
    
    $stmt = $db->prepare("SELECT delivery_state, state_history FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) return false;
    
    $history = json_decode($delivery['state_history'] ?? '[]', true) ?: [];
    $history[] = [
        'from' => $delivery['delivery_state'],
        'to' => $newState,
        'reason' => $reason,
        'at' => date('Y-m-d H:i:s')
    ];
    
    $stmt = $db->prepare("
        UPDATE deliveries SET 
            delivery_state = ?,
            state_changed_at = datetime('now'),
            state_history = ?
        WHERE id = ?
    ");
    
    return $stmt->execute([$newState, json_encode($history), $deliveryId]);
}

/**
 * Get delivery by ID with full details
 */
function getDeliveryById($deliveryId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, po.customer_id,
               COALESCE(t.name, tl.name) as product_display_name
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        LEFT JOIN templates t ON d.product_type = 'template' AND d.product_id = t.id
        LEFT JOIN tools tl ON d.product_type = 'tool' AND d.product_id = tl.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deliveryId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Set SLA deadline for a delivery
 */
function setDeliverySLA($deliveryId, $productType) {
    $db = getDb();
    $minutes = SLA_MINUTES[$productType] ?? SLA_MINUTES['tool'];
    $deadline = date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
    
    $stmt = $db->prepare("UPDATE deliveries SET sla_deadline = ? WHERE id = ?");
    return $stmt->execute([$deadline, $deliveryId]);
}

/**
 * Check and escalate deliveries approaching or past SLA
 */
function checkDeliverySLAs() {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, 
               COALESCE(t.name, tl.name) as product_name
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        LEFT JOIN templates t ON d.product_type = 'template' AND d.product_id = t.id
        LEFT JOIN tools tl ON d.product_type = 'tool' AND d.product_id = tl.id
        WHERE d.delivery_state NOT IN ('completed', 'downloaded')
        AND d.sla_deadline IS NOT NULL
        AND datetime(d.sla_deadline) <= datetime('now', '+30 minutes')
    ");
    $stmt->execute();
    $atRisk = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $escalated = 0;
    $warned = 0;
    
    foreach ($atRisk as $delivery) {
        $timeToSLA = strtotime($delivery['sla_deadline']) - time();
        
        if ($timeToSLA <= 0) {
            escalateDelivery($delivery['id'], 'sla_breach');
            $escalated++;
        } elseif ($timeToSLA <= 1800 && $delivery['escalation_level'] == 0) {
            alertAdminSLARisk($delivery);
            $warned++;
        }
    }
    
    return ['escalated' => $escalated, 'warned' => $warned];
}

/**
 * Escalate a delivery issue
 */
function escalateDelivery($deliveryId, $reason) {
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET escalation_level = COALESCE(escalation_level, 0) + 1,
            escalated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$deliveryId]);
    
    $delivery = getDeliveryById($deliveryId);
    
    switch ($delivery['escalation_level']) {
        case 1:
            sendAdminNotification("Delivery #{$deliveryId} needs attention: {$reason}");
            break;
        case 2:
            sendAdminNotification("URGENT: Delivery #{$deliveryId} - {$reason}");
            break;
        case 3:
            sendAdminNotification("CRITICAL: Delivery #{$deliveryId} requires immediate action - {$reason}");
            break;
    }
    
    error_log("Escalated delivery #{$deliveryId} to level {$delivery['escalation_level']}: {$reason}");
}

/**
 * Alert admin about SLA risk
 */
function alertAdminSLARisk($delivery) {
    $message = "SLA at risk for delivery #{$delivery['id']} - {$delivery['product_name']} for {$delivery['customer_name']}";
    error_log("SLA WARNING: " . $message);
    
    $stmt = getDb()->prepare("
        UPDATE deliveries SET escalation_level = 1 WHERE id = ? AND escalation_level = 0
    ");
    $stmt->execute([$delivery['id']]);
}

/**
 * Attempt auto-recovery for failed deliveries
 */
function attemptDeliveryRecovery($deliveryId) {
    $db = getDb();
    $delivery = getDeliveryById($deliveryId);
    
    if (!$delivery) return false;
    
    $maxRetries = $delivery['max_retries'] ?? 3;
    $retryCount = $delivery['retry_count'] ?? 0;
    
    if ($retryCount >= $maxRetries) {
        markDeliveryState($deliveryId, 'stalled', 'Max retries exceeded');
        return false;
    }
    
    $stmt = $db->prepare("
        UPDATE deliveries SET 
            retry_count = COALESCE(retry_count, 0) + 1,
            last_retry_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$deliveryId]);
    
    $failureReason = $delivery['failure_reason'] ?? 'unknown';
    
    switch ($failureReason) {
        case 'email_failed':
            return resendDeliveryEmail($deliveryId);
        case 'download_token_expired':
            return regenerateDeliveryToken($deliveryId);
        default:
            return genericRecoveryAttempt($deliveryId);
    }
}

/**
 * Resend delivery email
 */
function resendDeliveryEmail($deliveryId) {
    require_once __DIR__ . '/delivery.php';
    $result = resendToolDeliveryEmail($deliveryId);
    
    if ($result['success']) {
        markDeliveryState($deliveryId, 'ready', 'Email resent successfully');
    }
    
    return $result['success'];
}

/**
 * Regenerate delivery download token
 */
function regenerateDeliveryToken($deliveryId) {
    $db = getDb();
    $delivery = getDeliveryById($deliveryId);
    
    if (!$delivery || $delivery['product_type'] !== 'tool') {
        return false;
    }
    
    require_once __DIR__ . '/tool_files.php';
    
    $newToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    $stmt = $db->prepare("
        UPDATE download_tokens 
        SET is_active = 0, invalidated_at = datetime('now')
        WHERE delivery_id = ?
    ");
    $stmt->execute([$deliveryId]);
    
    $stmt = $db->prepare("
        INSERT INTO download_tokens (delivery_id, token, expires_at, customer_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$deliveryId, $newToken, $expiresAt, $delivery['customer_id']]);
    
    markDeliveryState($deliveryId, 'ready', 'Download token regenerated');
    
    return true;
}

/**
 * Generic recovery attempt
 */
function genericRecoveryAttempt($deliveryId) {
    $delivery = getDeliveryById($deliveryId);
    
    if ($delivery['product_type'] === 'tool') {
        return regenerateDeliveryToken($deliveryId);
    }
    
    markDeliveryState($deliveryId, 'stalled', 'Manual intervention required');
    escalateDelivery($deliveryId, 'Auto-recovery failed');
    
    return false;
}

/**
 * Process all failed deliveries (cron job)
 */
function processFailedDeliveries() {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT id FROM deliveries 
        WHERE delivery_state = 'failed'
        AND COALESCE(retry_count, 0) < COALESCE(max_retries, 3)
        AND (last_retry_at IS NULL OR datetime(last_retry_at) < datetime('now', '-5 minutes'))
        ORDER BY created_at ASC
        LIMIT 10
    ");
    $stmt->execute();
    $failed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $recovered = 0;
    foreach ($failed as $deliveryId) {
        if (attemptDeliveryRecovery($deliveryId)) {
            $recovered++;
        }
    }
    
    return ['processed' => count($failed), 'recovered' => $recovered];
}

/**
 * Get delivery status for customer API
 */
function getDeliveryStatusForOrder($orderId, $customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.id, d.product_name, d.product_type, d.delivery_state,
               d.state_changed_at, d.sla_deadline, d.delivery_link,
               d.customer_viewed_at, d.customer_download_count,
               d.created_at
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.pending_order_id = ? AND po.customer_id = ?
    ");
    $stmt->execute([$orderId, $customerId]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($deliveries as &$d) {
        $d['progress'] = getDeliveryProgress($d['delivery_state']);
        $d['eta'] = calculateDeliveryETA($d);
        $d['state_label'] = getStateLabel($d['delivery_state']);
    }
    
    return $deliveries;
}

/**
 * Calculate progress percentage based on state
 */
function getDeliveryProgress($state) {
    $progress = [
        'pending' => 10,
        'processing' => 50,
        'ready' => 90,
        'downloaded' => 100,
        'completed' => 100,
        'failed' => 0,
        'stalled' => 50,
        'expired' => 90,
        'issue' => 75
    ];
    return $progress[$state] ?? 0;
}

/**
 * Calculate ETA for delivery
 */
function calculateDeliveryETA($delivery) {
    if (in_array($delivery['delivery_state'], ['ready', 'downloaded', 'completed'])) {
        return null;
    }
    
    if ($delivery['sla_deadline']) {
        $remaining = strtotime($delivery['sla_deadline']) - time();
        if ($remaining > 0) {
            if ($remaining > 3600) {
                return round($remaining / 3600) . ' hours';
            }
            return round($remaining / 60) . ' minutes';
        }
    }
    
    return $delivery['product_type'] === 'template' ? '24-48 hours' : 'Soon';
}

/**
 * Get human-readable state label
 */
function getStateLabel($state) {
    $labels = [
        'pending' => 'Processing Started',
        'processing' => 'Being Prepared',
        'ready' => 'Ready',
        'downloaded' => 'Downloaded',
        'completed' => 'Complete',
        'failed' => 'Issue Detected',
        'stalled' => 'Under Review',
        'expired' => 'Link Expired',
        'issue' => 'Needs Attention'
    ];
    return $labels[$state] ?? ucfirst($state);
}

/**
 * Send delay notification to customer
 */
function sendDelayNotification($deliveryId) {
    $delivery = getDeliveryById($deliveryId);
    if (!$delivery) return false;
    
    $subject = "Update on Your Order #{$delivery['pending_order_id']}";
    
    $content = '<h2>Quick Update on Your Order</h2>';
    $content .= '<p>Hi ' . htmlspecialchars($delivery['customer_name']) . ',</p>';
    $content .= '<p>We\'re still working on your order and wanted to keep you in the loop. ';
    $content .= 'Setting up <strong>' . htmlspecialchars($delivery['product_display_name']) . '</strong> is taking a bit longer than usual, but we\'re on it!</p>';
    
    $content .= '<div style="background: #f0f9ff; padding: 15px; border-radius: 8px; margin: 20px 0;">';
    $content .= '<strong>Expected Ready Time:</strong> Within the next 2 hours<br>';
    $content .= '<strong>What\'s Happening:</strong> Our team is configuring your product';
    $content .= '</div>';
    
    $dashboardLink = SITE_URL . '/user/order-detail.php?id=' . $delivery['pending_order_id'];
    $content .= '<p>You can track progress in real-time from your dashboard:</p>';
    $content .= '<a href="' . $dashboardLink . '" style="display: inline-block; background: #3b82f6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none;">View Order Status</a>';
    
    return sendEmail($delivery['customer_email'], $subject, createEmailTemplate($subject, $content, $delivery['customer_name']));
}

/**
 * Mark delivery as viewed by customer
 */
function markDeliveryViewed($deliveryId, $customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE deliveries SET customer_viewed_at = datetime('now')
        WHERE id = ? AND customer_viewed_at IS NULL
        AND id IN (
            SELECT d.id FROM deliveries d
            JOIN pending_orders po ON d.pending_order_id = po.id
            WHERE po.customer_id = ?
        )
    ");
    
    return $stmt->execute([$deliveryId, $customerId]);
}

/**
 * Increment download count
 */
function incrementDeliveryDownloadCount($deliveryId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET customer_download_count = COALESCE(customer_download_count, 0) + 1
        WHERE id = ?
    ");
    $stmt->execute([$deliveryId]);
    
    $stmt = $db->prepare("SELECT delivery_state, customer_download_count FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($delivery && $delivery['delivery_state'] === 'ready') {
        markDeliveryState($deliveryId, 'downloaded', 'Customer downloaded');
    }
    
    return $delivery['customer_download_count'] ?? 1;
}
