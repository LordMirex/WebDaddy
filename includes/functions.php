<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}


function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function formatCurrency($amount)
{
    return '₦' . number_format($amount, 2);
}

function formatNumber($number)
{
    return number_format($number);
}

function formatBytes($bytes, $precision = 2)
{
    if ($bytes == 0) {
        return '0 B';
    }
    
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

function truncateText($text, $length = 50)
{
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . '...';
}

function getRelativeTime($datetime)
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}

function getStatusBadge($status)
{
    $badges = [
        'pending' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 max-w-[120px]"><i class="bi bi-clock mr-1 flex-shrink-0"></i><span class="truncate">Pending</span></span>',
        'approved' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 max-w-[120px]"><i class="bi bi-check-circle mr-1 flex-shrink-0"></i><span class="truncate">Approved</span></span>',
        'paid' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 max-w-[120px]"><i class="bi bi-check2-circle mr-1 flex-shrink-0"></i><span class="truncate">Paid</span></span>',
        'rejected' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 max-w-[120px]"><i class="bi bi-x-circle mr-1 flex-shrink-0"></i><span class="truncate">Rejected</span></span>',
        'completed' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 max-w-[120px]"><i class="bi bi-check-circle mr-1 flex-shrink-0"></i><span class="truncate">Done</span></span>',
        'failed' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-800 max-w-[120px]"><i class="bi bi-x-circle mr-1 flex-shrink-0"></i><span class="truncate">Failed</span></span>',
        'available' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 max-w-[120px]"><span class="truncate">Available</span></span>',
        'assigned' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 max-w-[120px]"><span class="truncate">Assigned</span></span>',
        'reserved' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 max-w-[120px]"><span class="truncate">Reserved</span></span>',
        'active' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800 max-w-[120px]"><span class="truncate">Active</span></span>',
        'inactive' => '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 max-w-[120px]"><span class="truncate">Inactive</span></span>',
    ];
    
    return $badges[$status] ?? '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 max-w-[120px]"><span class="truncate">' . htmlspecialchars(ucfirst($status)) . '</span></span>';
}

function getTemplates($activeOnly = true, $category = null, $limit = null, $offset = 0)
{
    $db = getDb();
    
    $sql = "SELECT * FROM templates WHERE 1=1";
    if ($activeOnly) {
        $sql .= " AND active = true";
    }
    if ($category) {
        $sql .= " AND category = " . $db->quote($category);
    }
    $sql .= " ORDER BY created_at DESC";
    if ($limit) {
        $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    }
    
    try {
        $result = $db->query($sql);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching templates: ' . $e->getMessage());
        return [];
    }
}

function getTemplatesCount($activeOnly = true, $category = null)
{
    $db = getDb();
    
    $sql = "SELECT COUNT(*) FROM templates WHERE 1=1";
    if ($activeOnly) {
        $sql .= " AND active = true";
    }
    if ($category) {
        $sql .= " AND category = " . $db->quote($category);
    }
    
    try {
        $count = $db->query($sql)->fetchColumn();
        return $count;
    } catch (PDOException $e) {
        error_log('Error counting templates: ' . $e->getMessage());
        return 0;
    }
}

function getTemplateById($id)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM templates WHERE id = ? AND active = 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching template: ' . $e->getMessage());
        return null;
    }
}

function getAffiliateByCode($code)
{
    $db = getDb();
    try {
        $stmt = $db->prepare("SELECT * FROM affiliates WHERE code = ?");
        $stmt->execute([strtoupper($code)]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        return $affiliate ? $affiliate : null;
    } catch (PDOException $e) {
        error_log('Error fetching affiliate: ' . $e->getMessage());
        return null;
    }
}

function incrementAffiliateClick($code)
{
    $db = getDb();
    try {
        $stmt = $db->prepare("UPDATE affiliates SET total_clicks = total_clicks + 1 WHERE code = ?");
        return $stmt->execute([strtoupper($code)]);
    } catch (PDOException $e) {
        error_log('Error incrementing affiliate clicks: ' . $e->getMessage());
        return false;
    }
}

function getTotalEarnings($code)
{
    $db = getDb();
    try {
        $stmt = $db->prepare("SELECT SUM(s.commission_amount) as total FROM sales s JOIN affiliates a ON s.affiliate_id = a.id WHERE a.code = ?");
        $stmt->execute([strtoupper($code)]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['total'] ?? 0;
    } catch (PDOException $e) {
        error_log('Error calculating affiliate earnings: ' . $e->getMessage());
        return 0;
    }
}

function recordAffiliateClick($affiliateCode)
{
    if (!empty($affiliateCode)) {
        incrementAffiliateClick($affiliateCode);
    }
}

function computeFinalAmount($order)
{
    if (isset($order['final_amount']) && !is_null($order['final_amount'])) {
        return floatval($order['final_amount']);
    }
    
    $subtotal = 0;
    if (isset($order['original_price'])) {
        $subtotal = floatval($order['original_price']);
    }
    
    $discount = 0;
    if (isset($order['discount_amount'])) {
        $discount = floatval($order['discount_amount']);
    }
    
    if ($subtotal > 0 && $discount > 0) {
        $finalAmount = $subtotal - $discount;
        return max(0, $finalAmount);
    }
    
    error_log("WARNING: computeFinalAmount() returning 0 for order #{$order['id']}. Order may have incomplete data.");
    return 0;
}

function assignDomainToCustomer($domainId, $orderId)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            UPDATE domains 
            SET status = 'in_use', assigned_order_id = ?
            WHERE id = ? AND status = 'available'
        ");
        
        $stmt->execute([$orderId, $domainId]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        error_log('Error assigning domain: ' . $e->getMessage());
        return false;
    }
}

function getOrderById($orderId)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            SELECT po.*, t.name as template_name, t.price as template_price, 
                   tool.name as tool_name, tool.price as tool_price, d.domain_name 
            FROM pending_orders po
            LEFT JOIN templates t ON po.template_id = t.id
            LEFT JOIN tools tool ON po.tool_id = tool.id
            LEFT JOIN domains d ON po.chosen_domain_id = d.id
            WHERE po.id = ?
        ");
        
        $stmt->execute([$orderId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Error fetching order: ' . $e->getMessage());
        return null;
    }
}

function getOrderItems($orderId)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            SELECT oi.*, 
                   t.name as template_name, t.slug as template_slug,
                   tool.name as tool_name, tool.slug as tool_slug
            FROM order_items oi
            LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
            LEFT JOIN tools tool ON oi.product_type = 'tool' AND oi.product_id = tool.id
            WHERE oi.pending_order_id = ?
            ORDER BY oi.id ASC
        ");
        
        $stmt->execute([$orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Apply metadata fallback for items with missing product names
        foreach ($items as &$item) {
            $productName = $item['product_type'] === 'template' ? $item['template_name'] : $item['tool_name'];
            
            // Fallback to metadata if product name is NULL (deleted/invalid product)
            if (empty($productName)) {
                $metadata = [];
                if (!empty($item['metadata_json'])) {
                    $decoded = @json_decode($item['metadata_json'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $metadata = $decoded;
                    } else {
                        $metadata = [];
                    }
                }
                $item['template_name'] = $metadata['name'] ?? 'Unknown Product';
                $item['tool_name'] = $metadata['name'] ?? 'Unknown Product';
            }
        }
        
        return $items;
    } catch (PDOException $e) {
        error_log('Error fetching order items: ' . $e->getMessage());
        return [];
    }
}

function getProductRecommendations($orderId, $limit = 3)
{
    $db = getDb();
    
    try {
        // Get categories from ordered items
        $orderItems = getOrderItems($orderId);
        if (empty($orderItems)) {
            return [];
        }
        
        $categories = [];
        foreach ($orderItems as $item) {
            if ($item['product_type'] === 'template') {
                // Get category from template
                $templateStmt = $db->prepare("SELECT category FROM templates WHERE id = ?");
                $templateStmt->execute([$item['product_id']]);
                $template = $templateStmt->fetch(PDO::FETCH_ASSOC);
                if ($template && $template['category']) {
                    $categories[] = $template['category'];
                }
            } else {
                // Get category from tool
                $toolStmt = $db->prepare("SELECT category FROM tools WHERE id = ?");
                $toolStmt->execute([$item['product_id']]);
                $tool = $toolStmt->fetch(PDO::FETCH_ASSOC);
                if ($tool && $tool['category']) {
                    $categories[] = $tool['category'];
                }
            }
        }
        
        if (empty($categories)) {
            return [];
        }
        
        $categories = array_unique($categories);
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        
        // Get products from same categories (exclude already ordered items)
        $orderedProductIds = [];
        foreach ($orderItems as $item) {
            $orderedProductIds[] = $item['product_id'];
        }
        $productPlaceholders = implode(',', array_fill(0, count($orderedProductIds), '?'));
        
        $sql = "
            (SELECT 'template' as type, id, name, category, price, thumbnail_url FROM templates 
             WHERE active = 1 AND category IN ($placeholders) AND id NOT IN ($productPlaceholders)
             ORDER BY RANDOM() LIMIT ?)
            UNION ALL
            (SELECT 'tool' as type, id, name, category, price, thumbnail_url FROM tools 
             WHERE active = 1 AND category IN ($placeholders) AND id NOT IN ($productPlaceholders)
             ORDER BY RANDOM() LIMIT ?)
        ";
        
        $params = array_merge($categories, $orderedProductIds, [$limit], $categories, $orderedProductIds, [$limit]);
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching product recommendations: ' . $e->getMessage());
        return [];
    }
}

function sendAffiliateOpportunityEmail($customerName, $customerEmail)
{
    $subject = "🤝 Earn 20% Commission - Share WebDaddy Empire!";
    
    $content = "
    <h2 style='color: #2563eb;'>Thank You for Your Purchase! 🎉</h2>
    
    <p>Hi <strong>" . htmlspecialchars($customerName) . "</strong>,</p>
    
    <p>We appreciate your business! We wanted to let you know about an amazing opportunity to <strong>earn passive income</strong> by recommending WebDaddy Empire to others.</p>
    
    <h3 style='color: #2563eb;'>💰 Affiliate Program Benefits</h3>
    <ul style='line-height: 1.8;'>
        <li><strong>20% Commission</strong> on every referral that converts</li>
        <li><strong>Recurring Income</strong> from customers you refer</li>
        <li><strong>Easy Sharing</strong> - We give you a unique referral code</li>
        <li><strong>Real-time Tracking</strong> - Monitor your earnings anytime</li>
        <li><strong>No Caps</strong> - Earn as much as you can!</li>
    </ul>
    
    <h3 style='color: #2563eb;'>🚀 Get Started in 3 Steps</h3>
    <ol style='line-height: 1.8;'>
        <li>Reply to this email to express your interest</li>
        <li>We'll provide your unique affiliate code</li>
        <li>Start earning 20% on every referral!</li>
    </ol>
    
    <p style='margin-top: 30px; font-size: 14px; color: #666;'>Your unique referral link will be in the format: <code>webdaddy.com?aff=YOUR_CODE</code></p>
    
    <p><strong>Questions?</strong> Just reply to this email or contact us on WhatsApp!</p>
    ";
    
    $emailHtml = createEmailTemplate($subject, $content, $customerName);
    
    if (sendEmail($customerEmail, $subject, $emailHtml)) {
        error_log("Affiliate opportunity email sent to: $customerEmail");
        return true;
    } else {
        error_log("Failed to send affiliate opportunity email to: $customerEmail");
        return false;
    }
}

function logActivity($type, $message, $userId = null)
{
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $type,
            $message,
            $_SERVER['REMOTE_ADDR'] ?? ''
        ]);
    } catch (PDOException $e) {
        error_log('Error logging activity: ' . $e->getMessage());
        return false;
    }
}

function getOrders($status = null, $limit = 20, $offset = 0)
{
    $db = getDb();
    try {
        $sql = "SELECT * FROM pending_orders WHERE 1=1";
        $params = [];
        
        if ($status) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching orders: ' . $e->getMessage());
        return [];
    }
}

function createOrderWithItems($orderData, $orderItems)
{
    $db = getDb();
    
    try {
        $db->beginTransaction();
        
        // Create pending order
        $orderStmt = $db->prepare("
            INSERT INTO pending_orders (
                customer_name, customer_email, customer_phone,
                affiliate_code, session_id, message_text, ip_address,
                original_price, discount_amount, final_amount, cart_snapshot
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $orderStmt->execute([
            $orderData['customer_name'],
            $orderData['customer_email'],
            $orderData['customer_phone'],
            $orderData['affiliate_code'],
            $orderData['session_id'],
            $orderData['message_text'],
            $orderData['ip_address'],
            $orderData['original_price'],
            $orderData['discount_amount'],
            $orderData['final_amount'],
            $orderData['cart_snapshot']
        ]);
        
        $orderId = $db->lastInsertId();
        
        // Add order items
        $itemStmt = $db->prepare("
            INSERT INTO order_items (
                pending_order_id, product_type, product_id, quantity,
                unit_price, discount_amount, final_amount, metadata_json
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($orderItems as $item) {
            $itemStmt->execute([
                $orderId,
                $item['product_type'],
                $item['product_id'],
                $item['quantity'],
                $item['unit_price'],
                $item['discount_amount'],
                $item['final_amount'],
                json_encode($item['metadata'])
            ]);
        }
        
        $db->commit();
        
        return $orderId;
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Error creating order: ' . $e->getMessage());
        global $lastDbError;
        $lastDbError = $e->getMessage();
        return false;
    }
}

function cancelOrder($orderId, $reason = null, $adminId = null)
{
    $db = getDb();
    
    try {
        $db->beginTransaction();
        
        // Get order
        $order = getOrderById($orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        if ($order['status'] === 'cancelled') {
            throw new Exception('Order is already cancelled');
        }
        
        // Update order status
        $updateStmt = $db->prepare("UPDATE pending_orders SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $updateStmt->execute([$orderId]);
        
        // Release domains
        $releaseDomainsStmt = $db->prepare("
            UPDATE domains SET status = 'available', assigned_order_id = NULL
            WHERE assigned_order_id = ?
        ");
        $releaseDomainsStmt->execute([$orderId]);
        $domainsReleased = $releaseDomainsStmt->rowCount();
        
        // Get items affected
        $itemsStmt = $db->prepare("SELECT COUNT(*) as count FROM order_items WHERE pending_order_id = ?");
        $itemsStmt->execute([$orderId]);
        $itemsResult = $itemsStmt->fetch(PDO::FETCH_ASSOC);
        $itemsAffected = $itemsResult['count'] ?? 0;
        
        // Restore affiliate commission
        $restoreCommissionStmt = $db->prepare("
            SELECT id, affiliate_id, commission_amount
            FROM sales
            WHERE pending_order_id = ?
        ");
        $restoreCommissionStmt->execute([$orderId]);
        $salesRecord = $restoreCommissionStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($salesRecord && $salesRecord['affiliate_id']) {
            $affiliateStmt = $db->prepare("
                UPDATE affiliates 
                SET pending_commission = pending_commission - ?, total_commission = total_commission - ?
                WHERE id = ?
            ");
            $affiliateStmt->execute([
                $salesRecord['commission_amount'],
                $salesRecord['commission_amount'],
                $salesRecord['affiliate_id']
            ]);
        }
        
        require_once __DIR__ . '/tools.php';
        $orderItems = getOrderItems($orderId);
        foreach ($orderItems as $item) {
            if ($item['product_type'] === 'tool') {
                incrementToolStock($item['product_id'], $item['quantity']);
            }
        }
        
        $db->commit();
        
        if (!empty($order['customer_email'])) {
            sendOrderRejectionEmail(
                $orderId,
                $order['customer_name'],
                $order['customer_email'],
                $reason ?: 'Order cancelled by administrator'
            );
        }
        
        if ($adminId) {
            logActivity('order_cancelled', "Order #$orderId cancelled. Reason: " . ($reason ?: 'None provided'), $adminId);
        }
        
        error_log("Order #$orderId successfully cancelled: $itemsAffected items updated, $domainsReleased domains released");
        
        return ['success' => true, 'message' => 'Order cancelled successfully'];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Order cancellation error for order #' . $orderId . ': ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getMediaUrl($url) {
    if (empty($url)) {
        return $url;
    }
    
    if (preg_match('#^https?://#i', $url)) {
        if (preg_match('#^https?://[^/]+(/uploads/.+)$#i', $url, $matches)) {
            return $matches[1];
        }
        
        return $url;
    }
    
    if (strpos($url, '/uploads/') === 0) {
        return $url;
    }
    
    if (strpos($url, 'uploads/') === 0) {
        return '/' . $url;
    }
    
    return $url;
}
