<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/tools.php';

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

/**
 * Extract YouTube video ID from various URL formats or raw ID
 * Supports: youtu.be/ID, youtube.com/watch?v=ID, youtube.com/embed/ID, or just ID
 * 
 * @param string $input YouTube URL or video ID
 * @return string|null Video ID or null if invalid
 */
function extractYoutubeVideoId($input)
{
    if (empty($input)) {
        return null;
    }
    
    $input = trim($input);
    
    // If it's already just a video ID (11 chars, alphanumeric + dash/underscore)
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $input)) {
        return $input;
    }
    
    // Try to extract from URL patterns
    $patterns = [
        '/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
        '/^https?:\/\/(?:www\.)?youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input, $matches)) {
            return $matches[1];
        }
    }
    
    return null;
}

/**
 * Build YouTube embed URL with optimal parameters
 * 
 * @param string $videoId YouTube video ID
 * @param bool $autoplay Whether to autoplay
 * @return string Full embed URL
 */
function buildYoutubeEmbedUrl($videoId, $autoplay = true)
{
    if (empty($videoId)) {
        return '';
    }
    
    $params = [
        'mute' => 1,
        'loop' => 1,
        'playlist' => $videoId,
        'controls' => 1,
        'modestbranding' => 1,
        'rel' => 0,
        'showinfo' => 0,
        'iv_load_policy' => 3,
        'playsinline' => 1,
        'start' => 0
    ];
    
    if ($autoplay) {
        $params['autoplay'] = 1;
    }
    
    return 'https://www.youtube-nocookie.com/embed/' . $videoId . '?' . http_build_query($params);
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
        $sql .= " AND active = 1";
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
        $sql .= " AND active = 1";
    }
    if ($category) {
        $sql .= " AND category = " . $db->quote($category);
    }
    
    try {
        return (int)$db->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        error_log('Error counting templates: ' . $e->getMessage());
        return 0;
    }
}

function getTemplateCategories()
{
    $db = getDb();
    
    $stmt = $db->query("
        SELECT DISTINCT category 
        FROM templates 
        WHERE category IS NOT NULL 
          AND category != '' 
          AND active = 1
        ORDER BY category
    ");
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getToolTypes()
{
    $db = getDb();
    
    $stmt = $db->query("
        SELECT DISTINCT tool_type 
        FROM tools 
        WHERE tool_type IS NOT NULL 
          AND tool_type != '' 
          AND active = 1
          AND (stock_unlimited = 1 OR stock_quantity > 0)
        ORDER BY tool_type
    ");
    
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

function getTemplateById($id)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM templates WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Error fetching template: ' . $e->getMessage());
        return null;
    }
}

function getTemplateBySlug($slug)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM templates WHERE slug = ?");
        $stmt->execute([$slug]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Error fetching template by slug: ' . $e->getMessage());
        return null;
    }
}

function getTemplateUrl($template, $affiliateCode = null)
{
    if (is_array($template)) {
        $slug = $template['slug'] ?? '';
        $id = $template['id'] ?? '';
    } else {
        $slug = $template;
        $id = '';
    }
    
    // Use slug if available, otherwise fallback to ID
    $identifier = !empty($slug) ? $slug : $id;
    
    // Clean URL format: /{slug}
    // Router.php handles this for PHP built-in server
    // .htaccess handles this for Apache/cPanel
    $url = '/' . urlencode($identifier);
    
    if ($affiliateCode) {
        $url .= '?aff=' . urlencode($affiliateCode);
    }
    
    return $url;
}

function getToolUrl($tool, $affiliateCode = null)
{
    if (is_array($tool)) {
        $slug = $tool['slug'] ?? '';
        $id = $tool['id'] ?? '';
    } else {
        $slug = $tool;
        $id = '';
    }
    
    // Use slug if available, otherwise fallback to ID
    $identifier = !empty($slug) ? $slug : $id;
    
    // Clean URL format: /tool/{slug}
    // This routes to index.php?tool={slug} which opens the tool modal
    // Router.php handles this for PHP built-in server
    // .htaccess handles this for Apache/cPanel
    $url = '/tool/' . urlencode($identifier);
    
    if ($affiliateCode) {
        $url .= '?aff=' . urlencode($affiliateCode);
    }
    
    return $url;
}

function getAvailableDomains($templateId)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM domains WHERE template_id = ? AND status = 'available' AND assigned_order_id IS NULL ORDER BY domain_name");
        $stmt->execute([$templateId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching available domains: ' . $e->getMessage());
        return [];
    }
}

function createPendingOrder($data)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO pending_orders 
            (template_id, chosen_domain_id, customer_name, customer_email, customer_phone, 
             business_name, custom_fields, affiliate_code, session_id, message_text, ip_address, status, payment_method,
             original_price, discount_amount, final_amount, order_type, customer_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, datetime('now', '+1 hour'), datetime('now', '+1 hour'))
        ");
        
        if (!$stmt) {
            throw new PDOException('Failed to prepare statement');
        }
        
        // Try to find customer_id by email if not provided
        $customerId = $data['customer_id'] ?? null;
        if (!$customerId && !empty($data['customer_email'])) {
            $custStmt = $db->prepare("SELECT id FROM customers WHERE LOWER(email) = LOWER(?)");
            $custStmt->execute([$data['customer_email']]);
            $custResult = $custStmt->fetch(PDO::FETCH_ASSOC);
            if ($custResult) {
                $customerId = $custResult['id'];
            }
        }
        
        $params = [
            $data['template_id'],
            $data['chosen_domain_id'],
            $data['customer_name'],
            $data['customer_email'],
            $data['customer_phone'],
            $data['business_name'],
            $data['custom_fields'],
            $data['affiliate_code'],
            $data['session_id'],
            $data['message_text'],
            $data['ip_address'],
            $data['payment_method'] ?? 'manual',
            $data['original_price'] ?? null,
            $data['discount_amount'] ?? 0,
            $data['final_amount'] ?? null,
            $data['order_type'] ?? 'template',
            $customerId
        ];
        
        $result = $stmt->execute($params);
        
        $lastId = $db->lastInsertId('pending_orders_id_seq');
        if (!$lastId) {
            $lastId = $db->lastInsertId();
        }
        
        if ($lastId && isset($data['order_items']) && is_array($data['order_items'])) {
            foreach ($data['order_items'] as $item) {
                $itemStmt = $db->prepare("
                    INSERT INTO order_items 
                    (pending_order_id, product_type, product_id, quantity, unit_price, discount_amount, final_amount, metadata_json)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $itemStmt->execute([
                    $lastId,
                    $item['product_type'],
                    $item['product_id'],
                    $item['quantity'] ?? 1,
                    $item['unit_price'],
                    $item['discount_amount'] ?? 0,
                    $item['final_amount'],
                    isset($item['metadata']) ? json_encode($item['metadata']) : null
                ]);
            }
        }
        
        return $lastId !== false ? $lastId : false;
    } catch (PDOException $e) {
        error_log('Error creating pending order: ' . $e->getMessage());
        // Store error in global for display in development
        global $lastDbError;
        $lastDbError = $e->getMessage();
        return false;
    }
}

function createOrderWithItems($orderData, $items = [])
{
    $db = getDb();
    $db->beginTransaction();
    
    try {
        $hasTemplates = false;
        $hasTools = false;
        foreach ($items as $item) {
            if ($item['product_type'] === 'template') $hasTemplates = true;
            if ($item['product_type'] === 'tool') $hasTools = true;
        }
        
        $orderType = 'template';
        if ($hasTemplates && $hasTools) {
            $orderType = 'mixed';
        } elseif (!$hasTemplates && $hasTools) {
            $orderType = 'tool';
        }
        
        // FIXED: Validate affiliate code exists before inserting (foreign key constraint)
        $affiliateCode = $orderData['affiliate_code'] ?? null;
        if (!empty($affiliateCode)) {
            $affiliateCheck = $db->prepare("SELECT id FROM affiliates WHERE UPPER(code) = UPPER(?) LIMIT 1");
            $affiliateCheck->execute([$affiliateCode]);
            if (!$affiliateCheck->fetch()) {
                // Affiliate code doesn't exist - set to NULL instead of failing
                error_log("⚠️  Invalid affiliate code ignored: $affiliateCode");
                $affiliateCode = null;
            }
        }
        
        // Validate referral code exists if provided
        $referralCode = $orderData['referral_code'] ?? null;
        if (!empty($referralCode)) {
            $refCheck = $db->prepare("SELECT id FROM user_referrals WHERE UPPER(referral_code) = UPPER(?) LIMIT 1");
            $refCheck->execute([$referralCode]);
            if (!$refCheck->fetch()) {
                error_log("⚠️  Invalid referral code ignored: $referralCode");
                $referralCode = null;
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO pending_orders 
            (template_id, tool_id, order_type, chosen_domain_id, customer_name, customer_email, 
             customer_phone, business_name, custom_fields, affiliate_code, referral_code, session_id, 
             message_text, ip_address, status, payment_method, original_price, discount_amount, final_amount, 
             quantity, cart_snapshot, payment_notes, customer_id, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, datetime('now', '+1 hour'), datetime('now', '+1 hour'))
        ");
        
        $templateId = $orderData['template_id'] ?? null;
        $toolId = $orderData['tool_id'] ?? null;
        
        // Try to find customer_id by email if not provided
        $customerId = $orderData['customer_id'] ?? null;
        if (!$customerId && !empty($orderData['customer_email'])) {
            $custStmt = $db->prepare("SELECT id FROM customers WHERE LOWER(email) = LOWER(?)");
            $custStmt->execute([$orderData['customer_email']]);
            $custResult = $custStmt->fetch(PDO::FETCH_ASSOC);
            if ($custResult) {
                $customerId = $custResult['id'];
            }
        }
        
        $params = [
            $templateId,
            $toolId,
            $orderType,
            $orderData['chosen_domain_id'] ?? null,
            $orderData['customer_name'],
            $orderData['customer_email'],
            $orderData['customer_phone'],
            $orderData['business_name'] ?? null,
            $orderData['custom_fields'] ?? null,
            $affiliateCode,
            $referralCode,
            $orderData['session_id'] ?? session_id(),
            $orderData['message_text'] ?? null,
            $orderData['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            $orderData['payment_method'] ?? 'manual',
            $orderData['original_price'] ?? null,
            $orderData['discount_amount'] ?? 0,
            $orderData['final_amount'],
            $orderData['quantity'] ?? 1,
            $orderData['cart_snapshot'] ?? null,
            $orderData['payment_notes'] ?? null,
            $customerId
        ];
        
        $stmt->execute($params);
        $orderId = $db->lastInsertId();
        
        if (!$orderId) {
            throw new PDOException('Failed to get order ID');
        }
        
        if (!empty($items)) {
            // Validate all items before inserting
            foreach ($items as $item) {
                if (empty($item['product_id']) || !is_numeric($item['product_id']) || $item['product_id'] <= 0) {
                    error_log('CRITICAL: Invalid product_id in order item: ' . json_encode($item));
                    throw new PDOException('Invalid product_id: cannot create order with null or invalid product references');
                }
            }
            
            $itemStmt = $db->prepare("
                INSERT INTO order_items 
                (pending_order_id, product_type, product_id, quantity, unit_price, discount_amount, final_amount, metadata_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($items as $item) {
                $itemStmt->execute([
                    $orderId,
                    $item['product_type'],
                    $item['product_id'],
                    $item['quantity'] ?? 1,
                    $item['unit_price'],
                    $item['discount_amount'] ?? 0,
                    $item['final_amount'],
                    isset($item['metadata']) ? json_encode($item['metadata']) : null
                ]);
            }
        }
        
        $db->commit();
        return $orderId;
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Error creating order with items: ' . $e->getMessage());
        global $lastDbError;
        $lastDbError = $e->getMessage();
        return false;
    }
}

function getOrders($status = null)
{
    $db = getDb();
    
    try {
        if ($status !== null) {
            $stmt = $db->prepare("
                SELECT po.*, t.name as template_name, d.domain_name 
                FROM pending_orders po
                LEFT JOIN templates t ON po.template_id = t.id
                LEFT JOIN domains d ON po.chosen_domain_id = d.id
                WHERE po.status = ?
                ORDER BY po.created_at DESC
            ");
            $stmt->execute([$status]);
        } else {
            $stmt = $db->query("
                SELECT po.*, t.name as template_name, d.domain_name 
                FROM pending_orders po
                LEFT JOIN templates t ON po.template_id = t.id
                LEFT JOIN domains d ON po.chosen_domain_id = d.id
                ORDER BY po.created_at DESC
            ");
        }
        
        $ordersRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $orders = [];
        foreach ($ordersRaw as $order) {
            $itemStmt = $db->prepare("
                SELECT oi.*, 
                       CASE 
                           WHEN oi.product_type = 'template' THEN t.name
                           WHEN oi.product_type = 'tool' THEN tl.name
                       END as product_name
                FROM order_items oi
                LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
                LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
                WHERE oi.pending_order_id = ?
            ");
            $itemStmt->execute([$order['id']]);
            $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $productList = [];
            $productNames = [];
            foreach ($items as $item) {
                $productName = $item['product_name'];
                
                if (empty($productName) && !empty($item['metadata_json'])) {
                    $metadata = @json_decode($item['metadata_json'], true);
                    if (is_array($metadata) && isset($metadata['name'])) {
                        $productName = $metadata['name'];
                    }
                }
                
                if (empty($productName)) {
                    $productName = 'Unknown Product';
                }
                
                $productList[] = [
                    'name' => $productName,
                    'type' => $item['product_type'],
                    'quantity' => $item['quantity']
                ];
                $productNames[] = $productName . ($item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '');
            }
            
            $order['products'] = $productList;
            $order['product_count'] = count($productList);
            $order['product_names_display'] = !empty($productNames) ? implode(', ', $productNames) : ($order['template_name'] ?? 'No products');
            
            $orders[] = $order;
        }
        
        return $orders;
    } catch (PDOException $e) {
        error_log('Error fetching orders: ' . $e->getMessage());
        return [];
    }
}

function markOrderPaid($orderId, $adminId, $amountPaid, $paymentNotes = '')
{
    $db = getDb();
    
    $db->beginTransaction();
    
    try {
        $order = getOrderById($orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // IMPORTANT: Set payment_method to 'manual' when admin approves (not paystack)
        // This ensures accurate tracking in reports and analytics
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'paid', payment_method = 'manual', payment_notes = ? WHERE id = ?");
        $stmt->execute([$paymentNotes, $orderId]);
        
        $commissionAmount = 0;
        $affiliateId = null;
        
        // Extract price breakdown from order
        $originalPrice = $order['original_price'] ?? $order['template_price'] ?? 0;
        $discountAmount = $order['discount_amount'] ?? 0;
        $finalAmount = $order['final_amount'] ?? $amountPaid;
        
        // Get order items from canonical source (order_items table)
        $orderItems = getOrderItems($orderId);
        $orderType = $order['order_type'] ?? 'template';
        
        // Validate final_amount matches sum of order items (if items exist)
        if (!empty($orderItems)) {
            $calculatedTotal = 0;
            foreach ($orderItems as $item) {
                $calculatedTotal += $item['final_amount'];
            }
            
            // Allow small floating point differences (0.01)
            if (abs($calculatedTotal - $finalAmount) > 0.01) {
                error_log("WARNING: Order #{$orderId} final_amount mismatch. Header: {$finalAmount}, Items sum: {$calculatedTotal}. Using items sum.");
                $finalAmount = $calculatedTotal;
            }
        }
        
        // Handle stock deduction for ALL tool items (works for 'tools', 'mixed', and legacy orders)
        require_once __DIR__ . '/tools.php';
        
        if (!empty($orderItems)) {
            // Use order_items table as source of truth
            foreach ($orderItems as $item) {
                if ($item['product_type'] === 'tool') {
                    $toolId = $item['product_id'];
                    $quantity = $item['quantity'];
                    $toolName = $item['tool_name'] ?? "Tool ID {$toolId}";
                    
                    $success = decrementToolStock($toolId, $quantity);
                    if (!$success) {
                        throw new Exception("Failed to decrement stock for '{$toolName}' (ID: {$toolId}, Quantity: {$quantity}). Insufficient stock available.");
                    }
                }
            }
        } else {
            // Fallback to cart_snapshot for legacy orders without order_items
            // Support both 'tool' and 'tools' for backward compatibility
            if (($orderType === 'tool' || $orderType === 'tools' || $orderType === 'mixed') && !empty($order['cart_snapshot'])) {
                $cartData = json_decode($order['cart_snapshot'], true);
                if ($cartData && isset($cartData['items'])) {
                    foreach ($cartData['items'] as $item) {
                        $productType = $item['product_type'] ?? 'tool';
                        if ($productType === 'tool') {
                            $toolId = $item['tool_id'] ?? $item['product_id'] ?? null;
                            $quantity = $item['quantity'] ?? 1;
                            $toolName = $item['name'] ?? "Tool ID {$toolId}";
                            
                            if ($toolId && $quantity > 0) {
                                $success = decrementToolStock($toolId, $quantity);
                                if (!$success) {
                                    throw new Exception("Failed to decrement stock for '{$toolName}' (ID: {$toolId}, Quantity: {$quantity}). Insufficient stock available.");
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // UNIFIED COMMISSION PROCESSOR: Process commission via new function (works for both manual and paystack)
        
        // FIX: Create or update payment record for manual payments to ensure reconciliation works
        // This mirrors what automatic payments do in api/paystack-verify.php (UPDATED: improved idempotency)
        $existingPayment = $db->prepare("SELECT id, status FROM payments WHERE pending_order_id = ? LIMIT 1");
        $existingPayment->execute([$orderId]);
        $paymentRow = $existingPayment->fetch();
        
        if (!$paymentRow) {
            // No payment record exists - create one for manual payment with all fields
            $paymentStmt = $db->prepare("
                INSERT INTO payments (
                    pending_order_id, payment_method, amount_requested, amount_paid,
                    currency, status, payment_verified_at, manual_verified_by, manual_verified_at,
                    payment_note, created_at
                ) VALUES (?, 'manual', ?, ?, 'NGN', 'completed', datetime('now', '+1 hour'), ?, datetime('now', '+1 hour'), ?, datetime('now', '+1 hour'))
            ");
            $paymentStmt->execute([$orderId, $finalAmount, $amountPaid, $adminId, $paymentNotes ?: 'Manual payment verified by admin']);
            error_log("✅ MARK ORDER PAID: Created payment record for manual order #$orderId");
        } elseif ($paymentRow['status'] !== 'completed') {
            // Payment record exists but not completed - update it
            $updatePayment = $db->prepare("
                UPDATE payments SET 
                    status = 'completed',
                    amount_paid = ?,
                    payment_verified_at = datetime('now', '+1 hour'),
                    manual_verified_by = ?,
                    manual_verified_at = datetime('now', '+1 hour'),
                    payment_note = COALESCE(payment_note, ?) || ' [Verified by admin]'
                WHERE pending_order_id = ? AND status != 'completed'
            ");
            $updatePayment->execute([$amountPaid, $adminId, $paymentNotes ?: '', $orderId]);
            error_log("✅ MARK ORDER PAID: Updated existing payment record for order #$orderId");
        } else {
            error_log("✅ MARK ORDER PAID: Payment record already completed for order #$orderId - no action needed");
        }
        
        $db->commit();
        
        error_log("✅ MARK ORDER PAID: Order #$orderId marked as paid");
        
        // Call unified commission processor (handles affiliate validation, prevents duplicates, logs everything)
        error_log("✅ MARK ORDER PAID: Processing affiliate commission for Order #$orderId");
        $commissionResult = processOrderCommission($orderId);
        if ($commissionResult['success']) {
            $commissionAmount = $commissionResult['commission_amount'];
            $affiliateId = $commissionResult['affiliate_id'];
            error_log("✅ MARK ORDER PAID: Commission processed - Amount: ₦" . number_format($commissionAmount, 2));
        } else {
            error_log("⚠️  MARK ORDER PAID: Commission processing - " . $commissionResult['message']);
            $commissionAmount = 0;
        }
        
        // STEP 1: Send payment confirmation email FIRST (before tool delivery)
        // This ensures the customer sees confirmation email before individual tool emails
        if (!empty($order['customer_email'])) {
            // Get domain name for template orders
            $domainName = !empty($order['domain_name']) ? $order['domain_name'] : null;
            
            // Note: Credentials are null as they are generated separately via admin panel
            $credentials = null;
            
            // Send enhanced email with full order details
            $emailSent = sendEnhancedPaymentConfirmationEmail($order, $orderItems, $domainName, $credentials);
            
            // Phase 5.4: Record email event for timeline
            if (function_exists('recordEmailEvent')) {
                recordEmailEvent($orderId, 'payment_confirmation', [
                    'email' => $order['customer_email'],
                    'subject' => 'Payment Confirmed - Order #' . $orderId,
                    'sent' => $emailSent
                ]);
            }
            
            error_log("✅ MARK ORDER PAID: Confirmation email sent to customer");
        }
        
        // STEP 2: Create delivery records for automatic tool delivery and template tracking (Phase 3)
        // IDEMPOTENCY: createDeliveryRecords now handles its own idempotency - checks for existing deliveries
        // and only creates MISSING ones. This fixes the mixed order bug where templates were skipped.
        // ERROR HANDLING: Track failures for admin visibility but don't block payment confirmation
        $deliveryError = null;
        try {
            require_once __DIR__ . '/delivery.php';
            // Always call - function now internally checks for missing deliveries
            createDeliveryRecords($orderId);
            
            // STEP 3: Send automatic tool delivery emails AFTER confirmation email
            error_log("✅ MARK ORDER PAID: Sending tool delivery emails");
            sendAllToolDeliveryEmailsForOrder($orderId);
            error_log("✅ MARK ORDER PAID: Tool delivery emails sent");
        } catch (Exception $e) {
            $deliveryError = $e->getMessage();
            error_log("Failed to create delivery records for order #{$orderId}: " . $deliveryError);
            // Don't throw - payment is already confirmed, admin can manually retry delivery creation
        }
        
        // UNIFIED EMAIL: Send styled delivery status email for orders with pending template items
        // This replaces the old sendMixedOrderDeliverySummaryEmail which sent plain-style duplicate emails
        // Only send if there are delivery records and pending template items to track
        if (!empty($order['customer_email']) && function_exists('sendOrderDeliveryUpdateEmail')) {
            try {
                $stats = getOrderDeliveryStats($orderId);
                // Only send delivery update if we have delivery records and pending templates
                // Tools are delivered immediately via sendAllToolDeliveryEmailsForOrder above
                if ($stats && $stats['total_items'] > 0 && $stats['templates']['pending'] > 0) {
                    error_log("✅ MARK ORDER PAID: Sending delivery status email for Order #$orderId with {$stats['templates']['pending']} pending templates");
                    sendOrderDeliveryUpdateEmail($orderId, 'initial_order_confirmation');
                } else {
                    error_log("✅ MARK ORDER PAID: No pending templates for Order #$orderId, skipping delivery update email");
                }
            } catch (Exception $e) {
                error_log("⚠️  MARK ORDER PAID: Failed to send delivery update email: " . $e->getMessage());
                // Don't throw - non-critical, customer still gets confirmation and tool delivery emails
            }
        }
        
        // NOTE: Commission email is already sent by processOrderCommission() above - no duplicate needed
        
        // Process any pending emails in queue (affiliate invitations, etc.)
        // This ensures queued emails are sent immediately after payment confirmation
        try {
            require_once __DIR__ . '/email_queue.php';
            $queueResult = processEmailQueue(10, true); // Aggressive mode for immediate processing
            if ($queueResult['sent'] > 0) {
                error_log("✅ MARK ORDER PAID: Processed {$queueResult['sent']} queued emails for Order #$orderId");
            }
        } catch (Exception $e) {
            error_log("⚠️  MARK ORDER PAID: Email queue processing error: " . $e->getMessage());
            // Don't throw - non-critical, emails will be processed by cron
        }
        
        // Return success with delivery status information
        return [
            'success' => true,
            'delivery_error' => $deliveryError,
            'order_id' => $orderId
        ];
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error marking order paid: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

function computeFinalAmount($order, $orderItems = null)
{
    if ($orderItems === null && !empty($order['id'])) {
        $orderItems = getOrderItems($order['id']);
    }
    
    if (!empty($order['final_amount']) && $order['final_amount'] > 0) {
        return (float)$order['final_amount'];
    }
    
    if (!empty($order['original_price']) && $order['original_price'] > 0) {
        return (float)$order['original_price'];
    }
    
    $totalAmount = 0;
    
    if (!empty($orderItems)) {
        foreach ($orderItems as $item) {
            $totalAmount += (float)($item['final_amount'] ?? 0);
        }
    }
    
    if ($totalAmount > 0) {
        return $totalAmount;
    }
    
    $basePrice = (float)($order['template_price'] ?? $order['tool_price'] ?? 0);
    
    if ($basePrice > 0 && !empty($order['affiliate_code'])) {
        $discountAmount = $basePrice * CUSTOMER_DISCOUNT_RATE;
        return max(0, $basePrice - $discountAmount);
    }
    
    if ($basePrice > 0) {
        return $basePrice;
    }
    
    if (!empty($order['amount_paid']) && $order['amount_paid'] > 0) {
        return (float)$order['amount_paid'];
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
                        error_log('Invalid metadata JSON for order item #' . $item['id'] . ': ' . $item['metadata_json']);
                    }
                }
                
                // Extra safety: ensure $metadata is always an array before access
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                
                $fallbackName = $metadata['name'] ?? 'Unknown Product';
                
                // Update the appropriate name field so all consumers get the fallback
                if ($item['product_type'] === 'template') {
                    $item['template_name'] = $fallbackName;
                } else {
                    $item['tool_name'] = $fallbackName;
                }
            }
        }
        
        return $items;
    } catch (PDOException $e) {
        error_log('Error fetching order items: ' . $e->getMessage());
        return [];
    }
}

function getAffiliateByCode($code)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            SELECT a.*, u.name, u.email
            FROM affiliates a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.code = ? AND a.status = 'active'
        ");
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Error fetching affiliate: ' . $e->getMessage());
        return null;
    }
}

function getUserById($userId)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
    } catch (PDOException $e) {
        error_log('Error fetching user: ' . $e->getMessage());
        return null;
    }
}

function incrementAffiliateClick($code)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("UPDATE affiliates SET total_clicks = total_clicks + 1 WHERE code = ?");
        $stmt->execute([$code]);
    } catch (PDOException $e) {
        error_log('Error incrementing affiliate click: ' . $e->getMessage());
    }
}

// ============================================
// USER REFERRAL SYSTEM FUNCTIONS
// ============================================

function generateUserReferralCode($customerId)
{
    $db = getDb();
    
    // Generate a random 6-character alphanumeric code (uppercase letters and numbers only)
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $charLength = strlen($characters);
    
    // Ensure uniqueness with multiple attempts
    $attempts = 0;
    while ($attempts < 20) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, $charLength - 1)];
        }
        
        // Check if code already exists
        $check = $db->prepare("SELECT id FROM user_referrals WHERE referral_code = ?");
        $check->execute([$code]);
        if (!$check->fetch()) {
            // Also check affiliates table to avoid conflicts
            $checkAff = $db->prepare("SELECT id FROM affiliates WHERE code = ?");
            $checkAff->execute([$code]);
            if (!$checkAff->fetch()) {
                return $code;
            }
        }
        $attempts++;
    }
    
    return null;
}

function createUserReferral($customerId)
{
    $db = getDb();
    
    try {
        // Check if user already has a referral code
        $stmt = $db->prepare("SELECT id, referral_code FROM user_referrals WHERE customer_id = ?");
        $stmt->execute([$customerId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            return $existing;
        }
        
        // Generate new referral code
        $code = generateUserReferralCode($customerId);
        if (!$code) {
            return null;
        }
        
        $stmt = $db->prepare("
            INSERT INTO user_referrals (customer_id, referral_code, created_at) 
            VALUES (?, ?, datetime('now'))
        ");
        $stmt->execute([$customerId, $code]);
        
        return [
            'id' => $db->lastInsertId(),
            'customer_id' => $customerId,
            'referral_code' => $code
        ];
    } catch (PDOException $e) {
        error_log('Error creating user referral: ' . $e->getMessage());
        return null;
    }
}

function getUserReferralByCode($code)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            SELECT ur.*, c.email, c.username, c.full_name
            FROM user_referrals ur
            JOIN customers c ON ur.customer_id = c.id
            WHERE ur.referral_code = ? AND ur.status = 'active'
        ");
        $stmt->execute([$code]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('Error fetching user referral: ' . $e->getMessage());
        return null;
    }
}

function getUserReferralByCustomerId($customerId)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            SELECT ur.*, c.email, c.username, c.full_name
            FROM user_referrals ur
            JOIN customers c ON ur.customer_id = c.id
            WHERE ur.customer_id = ?
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $e) {
        error_log('Error fetching user referral by customer: ' . $e->getMessage());
        return null;
    }
}

function incrementUserReferralClick($code)
{
    $db = getDb();
    
    try {
        // First get the referral id
        $stmt = $db->prepare("SELECT id FROM user_referrals WHERE referral_code = ? AND status = 'active'");
        $stmt->execute([$code]);
        $referral = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$referral) {
            return;
        }
        
        // Update click count
        $db->prepare("UPDATE user_referrals SET total_clicks = total_clicks + 1 WHERE id = ?")->execute([$referral['id']]);
        
        // Log the click
        $stmt = $db->prepare("
            INSERT INTO user_referral_clicks (referral_id, ip_address, user_agent, referrer, created_at) 
            VALUES (?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            $referral['id'],
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $_SERVER['HTTP_REFERER'] ?? null
        ]);
    } catch (PDOException $e) {
        error_log('Error incrementing user referral click: ' . $e->getMessage());
    }
}

function getUserReferralStats($customerId)
{
    $db = getDb();
    
    try {
        // Get referral record
        $referral = getUserReferralByCustomerId($customerId);
        if (!$referral) {
            return null;
        }
        
        // Calculate earnings from sales table (source of truth)
        $stmt = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total_earned FROM user_referral_sales WHERE referrer_id = ?");
        $stmt->execute([$referral['id']]);
        $totalEarned = (float)$stmt->fetchColumn();
        
        // Get paid withdrawals
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM user_referral_withdrawals WHERE referral_id = ? AND status = 'paid'");
        $stmt->execute([$referral['id']]);
        $totalPaid = (float)$stmt->fetchColumn();
        
        // Get in-progress withdrawals (pending, approved - not yet paid or rejected)
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total_in_progress FROM user_referral_withdrawals WHERE referral_id = ? AND status NOT IN ('paid', 'rejected')");
        $stmt->execute([$referral['id']]);
        $inProgress = (float)$stmt->fetchColumn();
        
        // Available balance = Earned - Paid - In Progress
        $availableBalance = $totalEarned - $totalPaid - $inProgress;
        
        // Get total sales count
        $stmt = $db->prepare("SELECT COUNT(*) FROM user_referral_sales WHERE referrer_id = ?");
        $stmt->execute([$referral['id']]);
        $totalSales = (int)$stmt->fetchColumn();
        
        return [
            'referral' => $referral,
            'total_clicks' => (int)$referral['total_clicks'],
            'total_sales' => $totalSales,
            'total_earned' => $totalEarned,
            'total_paid' => $totalPaid,
            'in_progress' => $inProgress,
            'available_balance' => $availableBalance,
            'commission_rate' => USER_REFERRAL_COMMISSION_RATE * 100 . '%'
        ];
    } catch (PDOException $e) {
        error_log('Error getting user referral stats: ' . $e->getMessage());
        return null;
    }
}

function processUserReferralCommission($orderId)
{
    $db = getDb();
    error_log("💰 USER REFERRAL COMMISSION: Starting for Order #$orderId");
    
    try {
        // Get order details
        $orderStmt = $db->prepare("SELECT id, referral_code, final_amount, customer_email FROM pending_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        if (empty($order['referral_code'])) {
            return ['success' => true, 'message' => 'No user referral code'];
        }
        
        // Check if already processed
        $checkStmt = $db->prepare("SELECT id FROM user_referral_sales WHERE pending_order_id = ? LIMIT 1");
        $checkStmt->execute([$orderId]);
        if ($checkStmt->fetch()) {
            return ['success' => true, 'message' => 'User referral commission already processed'];
        }
        
        // Get referral details
        $referral = getUserReferralByCode($order['referral_code']);
        if (!$referral) {
            error_log("⚠️  USER REFERRAL COMMISSION: Invalid referral code '{$order['referral_code']}'");
            return ['success' => false, 'message' => 'Invalid referral code'];
        }
        
        // Calculate commission (20% of final amount)
        $commissionAmount = $order['final_amount'] * USER_REFERRAL_COMMISSION_RATE;
        
        // Create sales record
        $stmt = $db->prepare("
            INSERT INTO user_referral_sales (pending_order_id, referrer_id, amount_paid, commission_amount, payment_confirmed_at, created_at)
            VALUES (?, ?, ?, ?, datetime('now'), datetime('now'))
        ");
        $stmt->execute([$orderId, $referral['id'], $order['final_amount'], $commissionAmount]);
        
        // Update referral totals
        $stmt = $db->prepare("
            UPDATE user_referrals 
            SET total_sales = total_sales + 1,
                commission_earned = commission_earned + ?,
                commission_pending = commission_pending + ?,
                updated_at = datetime('now')
            WHERE id = ?
        ");
        $stmt->execute([$commissionAmount, $commissionAmount, $referral['id']]);
        
        // Update order with referral commission
        $stmt = $db->prepare("UPDATE pending_orders SET referral_commission = ? WHERE id = ?");
        $stmt->execute([$commissionAmount, $orderId]);
        
        error_log("✅ USER REFERRAL COMMISSION: ₦" . number_format($commissionAmount, 2) . " credited to User Referrer #{$referral['id']}");
        
        return [
            'success' => true,
            'commission_amount' => $commissionAmount,
            'referral_id' => $referral['id']
        ];
    } catch (PDOException $e) {
        error_log('Error processing user referral commission: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function getUserReferralRecentSales($customerId, $limit = 10)
{
    $db = getDb();
    
    try {
        $referral = getUserReferralByCustomerId($customerId);
        if (!$referral) {
            return [];
        }
        
        $stmt = $db->prepare("
            SELECT urs.*, po.customer_name, po.customer_email, po.order_type
            FROM user_referral_sales urs
            JOIN pending_orders po ON urs.pending_order_id = po.id
            WHERE urs.referrer_id = ?
            ORDER BY urs.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$referral['id'], $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting user referral sales: ' . $e->getMessage());
        return [];
    }
}

function getUserReferralWithdrawalHistory($customerId)
{
    $db = getDb();
    
    try {
        $referral = getUserReferralByCustomerId($customerId);
        if (!$referral) {
            return [];
        }
        
        $stmt = $db->prepare("
            SELECT * FROM user_referral_withdrawals 
            WHERE referral_id = ?
            ORDER BY requested_at DESC
        ");
        $stmt->execute([$referral['id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error getting user referral withdrawals: ' . $e->getMessage());
        return [];
    }
}

function createUserReferralWithdrawal($customerId, $amount, $bankDetails)
{
    $db = getDb();
    
    try {
        $stats = getUserReferralStats($customerId);
        if (!$stats) {
            return ['success' => false, 'message' => 'Referral account not found'];
        }
        
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Invalid withdrawal amount'];
        }
        
        if ($amount > $stats['available_balance']) {
            return ['success' => false, 'message' => 'Insufficient balance. Available: ₦' . number_format($stats['available_balance'], 2)];
        }
        
        // Validate bank details
        if (empty($bankDetails['bank_name']) || empty($bankDetails['account_number']) || empty($bankDetails['account_name'])) {
            return ['success' => false, 'message' => 'Please provide complete bank details'];
        }
        
        $db->beginTransaction();
        
        // Create withdrawal request
        $stmt = $db->prepare("
            INSERT INTO user_referral_withdrawals (referral_id, amount, bank_details_json, status, requested_at)
            VALUES (?, ?, ?, 'pending', datetime('now'))
        ");
        $stmt->execute([
            $stats['referral']['id'],
            $amount,
            json_encode($bankDetails)
        ]);
        
        $withdrawalId = $db->lastInsertId();
        
        $db->commit();
        
        error_log("✅ USER REFERRAL WITHDRAWAL: Request #{$withdrawalId} created for ₦" . number_format($amount, 2));
        
        return [
            'success' => true,
            'withdrawal_id' => $withdrawalId,
            'message' => 'Withdrawal request submitted successfully'
        ];
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Error creating user referral withdrawal: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Database error. Please try again.'];
    }
}

// ============================================
// END USER REFERRAL SYSTEM FUNCTIONS
// ============================================

/**
 * UNIFIED COMMISSION PROCESSOR: Called for both Paystack and Manual payments
 * Ensures commissions are calculated and credited identically regardless of payment method
 * Also sends affiliate commission notification emails
 */
function processOrderCommission($orderId)
{
    $db = getDb();
    error_log("💰 COMMISSION PROCESSOR: Starting for Order #$orderId");
    
    try {
        // Get order details
        $orderStmt = $db->prepare("SELECT id, affiliate_code, final_amount, customer_email FROM pending_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            error_log("❌ COMMISSION PROCESSOR: Order #$orderId not found");
            return ['success' => false, 'message' => 'Order not found'];
        }
        
        // Check if commission already credited (prevent duplicates)
        $checkStmt = $db->prepare("SELECT id FROM sales WHERE pending_order_id = ? LIMIT 1");
        $checkStmt->execute([$orderId]);
        $existingSales = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSales) {
            error_log("⚠️  COMMISSION PROCESSOR: Order #$orderId already has sales record - skipping");
            return ['success' => true, 'message' => 'Commission already processed'];
        }
        
        $commissionAmount = 0;
        $affiliateId = null;
        $affiliateData = null;
        
        // Calculate commission only if affiliate code exists
        if (!empty($order['affiliate_code'])) {
            $affiliate = getAffiliateByCode($order['affiliate_code']);
            
            if ($affiliate && $affiliate['status'] === 'active') {
                // Commission base is final_amount (already discounted)
                $commissionBase = $order['final_amount'];
                $commissionRate = $affiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                $commissionAmount = $commissionBase * $commissionRate;
                $affiliateId = $affiliate['id'];
                $affiliateData = $affiliate;
                
                // Update affiliate balance
                $updateStmt = $db->prepare("
                    UPDATE affiliates 
                    SET total_sales = total_sales + 1,
                        commission_earned = commission_earned + ?,
                        commission_pending = commission_pending + ?
                    WHERE id = ? AND status = 'active'
                ");
                $updateStmt->execute([$commissionAmount, $commissionAmount, $affiliateId]);
                
                // Log commission transaction for audit trail (Phase 4)
                logCommissionTransaction($orderId, $affiliateId, 'commission_earned', $commissionAmount, 'Affiliate commission calculated from order');
                
                error_log("✅ COMMISSION PROCESSOR: Commission ₦" . number_format($commissionAmount, 2) . " credited to Affiliate #$affiliateId");
            } else {
                error_log("⚠️  COMMISSION PROCESSOR: Affiliate '{$order['affiliate_code']}' inactive or not found");
            }
        } else {
            error_log("ℹ️  COMMISSION PROCESSOR: No affiliate code - Order #$orderId is direct sale");
        }
        
        // Create sales record (for revenue tracking)
        // admin_id is NULL for system-generated commission records
        $salesStmt = $db->prepare("
            INSERT INTO sales (pending_order_id, admin_id, amount_paid, commission_amount, affiliate_id, payment_confirmed_at)
            VALUES (?, NULL, ?, ?, ?, datetime('now', '+1 hour'))
        ");
        $salesStmt->execute([$orderId, $order['final_amount'], $commissionAmount, $affiliateId]);
        
        // Log sales record creation
        logCommissionTransaction($orderId, $affiliateId, 'sales_record_created', $commissionAmount, 'Revenue recorded in sales table');
        
        // SAFEGUARD: Sync affiliate commission totals with sales table to prevent discrepancies
        if ($affiliateId) {
            syncAffiliateCommissions($affiliateId);
        }
        
        // CRITICAL FIX: Send commission earned email to affiliate (was missing!)
        if ($affiliateData && $commissionAmount > 0) {
            try {
                $affiliateName = $affiliateData['name'] ?? 'Affiliate';
                $affiliateEmail = $affiliateData['email'] ?? null;
                
                if (!empty($affiliateEmail)) {
                    // Get order items to build product list for email
                    $orderItems = getOrderItems($orderId);
                    $productNames = [];
                    
                    if (!empty($orderItems)) {
                        foreach ($orderItems as $item) {
                            $name = $item['product_type'] === 'template' ? ($item['template_name'] ?? 'Template') : ($item['tool_name'] ?? 'Tool');
                            if (!empty($name)) {
                                $productNames[] = htmlspecialchars($name);
                            }
                        }
                    }
                    
                    $productList = !empty($productNames) ? implode(', ', $productNames) : 'Product(s)';
                    
                    $emailSent = sendCommissionEarnedEmail(
                        $affiliateName,
                        $affiliateEmail,
                        $orderId,
                        $commissionAmount,
                        $productList
                    );
                    
                    if ($emailSent) {
                        error_log("✅ COMMISSION PROCESSOR: Commission email sent to affiliate {$affiliateEmail} for Order #$orderId");
                    } else {
                        error_log("⚠️  COMMISSION PROCESSOR: Failed to send commission email to {$affiliateEmail}");
                    }
                } else {
                    error_log("⚠️  COMMISSION PROCESSOR: Affiliate #$affiliateId has no email address");
                }
            } catch (Exception $emailEx) {
                error_log("⚠️  COMMISSION PROCESSOR: Email error: " . $emailEx->getMessage());
            }
        }
        
        error_log("✅ COMMISSION PROCESSOR: Sales record created for Order #$orderId. Commission: ₦" . number_format($commissionAmount, 2));
        
        // ALSO PROCESS USER REFERRAL COMMISSION (separate from affiliate)
        // User referral commissions are processed independently and stored in user_referral_sales table
        $userReferralResult = processUserReferralCommission($orderId);
        if ($userReferralResult && $userReferralResult['success'] && isset($userReferralResult['commission_amount'])) {
            error_log("✅ COMMISSION PROCESSOR: User referral commission also processed for Order #$orderId: ₦" . number_format($userReferralResult['commission_amount'], 2));
        }
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'commission_amount' => $commissionAmount,
            'affiliate_id' => $affiliateId,
            'user_referral_commission' => $userReferralResult['commission_amount'] ?? 0,
            'message' => 'Commission processed successfully'
        ];
        
    } catch (Exception $e) {
        error_log("❌ COMMISSION PROCESSOR: Error - " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * SAFEGUARD: Sync affiliate commission totals with sales table
 * Prevents discrepancies between affiliates table cache and sales table (single source of truth)
 * Called automatically after commission is credited
 */
function syncAffiliateCommissions($affiliateId = null)
{
    $db = getDb();
    try {
        $query = "SELECT id FROM affiliates WHERE status = 'active'";
        $params = [];
        
        if ($affiliateId) {
            $query .= " AND id = ?";
            $params[] = $affiliateId;
        }
        
        $affiliates = $db->prepare($query)->execute($params);
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $affiliates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($affiliates as $aff) {
            $id = $aff['id'];
            
            // Get actual totals from sales table (SOURCE OF TRUTH)
            $earned = $db->query("SELECT COALESCE(SUM(commission_amount), 0) as total FROM sales WHERE affiliate_id=$id")->fetchColumn();
            $paid = $db->query("SELECT COALESCE(SUM(amount), 0) as total FROM withdrawal_requests WHERE affiliate_id=$id AND status='paid'")->fetchColumn();
            $pending = $earned - $paid;
            
            // Update affiliates table with correct values
            $updateStmt = $db->prepare("UPDATE affiliates SET commission_earned=?, commission_pending=?, commission_paid=? WHERE id=?");
            $updateStmt->execute([$earned, $pending, $paid, $id]);
        }
        
        error_log("✅ COMMISSION SYNC: Affiliate commission totals synced from sales table");
        return true;
    } catch (Exception $e) {
        error_log("❌ COMMISSION SYNC ERROR: " . $e->getMessage());
        return false;
    }
}

/**
 * PHASE 4: Log commission transaction for audit trail
 * Tracks all commission movements for reconciliation
 * Has unique constraint on (order_id, action) to prevent duplicates
 */
function logCommissionTransaction($orderId, $affiliateId, $action, $amount, $details = '')
{
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO commission_log (order_id, affiliate_id, action, amount, details, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now', '+1 hour'))
        ");
        $stmt->execute([$orderId, $affiliateId, $action, $amount, $details]);
        error_log("📝 COMMISSION LOG: Order #$orderId - Action: $action | Amount: ₦" . number_format($amount, 2));
        return true;
    } catch (Exception $e) {
        // Check if this is a duplicate entry (unique constraint violation)
        if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false || 
            strpos($e->getMessage(), 'idx_commission_log_unique') !== false) {
            error_log("⚠️ COMMISSION LOG: Duplicate entry skipped - Order #$orderId, Action: $action");
            return false;
        }
        error_log("❌ COMMISSION LOG ERROR: " . $e->getMessage());
        return false;
    }
}

/**
 * PHASE 5: Get pending commissions for an affiliate or all affiliates
 * Used for withdrawal requests and monitoring
 */
function getPendingCommissions($affiliateId = null)
{
    $db = getDb();
    try {
        $query = "SELECT id, code, commission_pending, commission_earned, total_sales FROM affiliates WHERE status = 'active'";
        $params = [];
        
        if ($affiliateId) {
            $query .= " AND id = ?";
            $params[] = $affiliateId;
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $affiliateId ? $stmt->fetch(PDO::FETCH_ASSOC) : $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("❌ GET PENDING COMMISSIONS ERROR: " . $e->getMessage());
        return $affiliateId ? null : [];
    }
}

/**
 * PHASE 5: Create commission withdrawal request
 * Tracks affiliate payout requests
 */
function createCommissionWithdrawal($affiliateId, $amount, $paymentMethod = '', $bankDetails = '')
{
    $db = getDb();
    try {
        // Verify affiliate has enough pending commission
        $affiliate = getPendingCommissions($affiliateId);
        if (!$affiliate || $affiliate['commission_pending'] < $amount) {
            error_log("❌ WITHDRAWAL: Insufficient pending commission. Requested: ₦" . number_format($amount, 2) . ", Available: ₦" . number_format($affiliate['commission_pending'] ?? 0, 2));
            return ['success' => false, 'message' => 'Insufficient pending commission'];
        }
        
        $stmt = $db->prepare("
            INSERT INTO commission_withdrawals (affiliate_id, amount_requested, payment_method, bank_details, requested_at, status)
            VALUES (?, ?, ?, ?, datetime('now', '+1 hour'), 'pending')
        ");
        $stmt->execute([$affiliateId, $amount, $paymentMethod, $bankDetails]);
        
        error_log("✅ WITHDRAWAL REQUEST: Affiliate #$affiliateId requested ₦" . number_format($amount, 2));
        return ['success' => true, 'message' => 'Withdrawal request created'];
    } catch (Exception $e) {
        error_log("❌ WITHDRAWAL ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * PHASE 5: Process commission payout
 * Moves commission from pending to paid
 */
function processCommissionPayout($withdrawalId)
{
    $db = getDb();
    try {
        $db->beginTransaction();
        
        // Get withdrawal request
        $stmt = $db->prepare("SELECT affiliate_id, amount_requested FROM commission_withdrawals WHERE id = ? AND status = 'pending'");
        $stmt->execute([$withdrawalId]);
        $withdrawal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$withdrawal) {
            throw new Exception('Withdrawal request not found or already processed');
        }
        
        $affiliateId = $withdrawal['affiliate_id'];
        $amount = $withdrawal['amount_requested'];
        
        // Update affiliate balance
        $updateStmt = $db->prepare("
            UPDATE affiliates 
            SET commission_pending = commission_pending - ?,
                commission_paid = commission_paid + ?
            WHERE id = ?
        ");
        $updateStmt->execute([$amount, $amount, $affiliateId]);
        
        // Mark withdrawal as processed
        $processStmt = $db->prepare("
            UPDATE commission_withdrawals 
            SET status = 'processed', processed_at = datetime('now', '+1 hour')
            WHERE id = ?
        ");
        $processStmt->execute([$withdrawalId]);
        
        // Create alert for affiliate
        $alertStmt = $db->prepare("
            INSERT INTO commission_alerts (affiliate_id, alert_type, message, amount)
            VALUES (?, 'payout_processed', ?, ?)
        ");
        $alertStmt->execute([$affiliateId, 'Your commission payout of ₦' . number_format($amount, 2) . ' has been processed', $amount]);
        
        $db->commit();
        error_log("✅ PAYOUT PROCESSED: ₦" . number_format($amount, 2) . " for Affiliate #$affiliateId");
        return ['success' => true, 'message' => 'Payout processed successfully'];
    } catch (Exception $e) {
        $db->rollBack();
        error_log("❌ PAYOUT ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * PHASE 5: Get commission report for dashboard
 */
function getCommissionReport()
{
    $db = getDb();
    try {
        $report = [];
        
        // Total commission metrics
        $totals = $db->query("
            SELECT 
                SUM(commission_pending) as total_pending,
                SUM(commission_earned) as total_earned,
                SUM(commission_paid) as total_paid
            FROM affiliates WHERE status = 'active'
        ")->fetch(PDO::FETCH_ASSOC);
        
        $report['totals'] = $totals;
        
        // Top earning affiliates
        $report['top_earners'] = $db->query("
            SELECT id, code, commission_earned, commission_pending, total_sales
            FROM affiliates 
            WHERE status = 'active' AND commission_earned > 0
            ORDER BY commission_earned DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Pending withdrawals
        $report['pending_withdrawals'] = $db->query("
            SELECT cw.id, a.code, cw.amount_requested, cw.requested_at
            FROM commission_withdrawals cw
            JOIN affiliates a ON cw.affiliate_id = a.id
            WHERE cw.status = 'pending'
            ORDER BY cw.requested_at DESC
            LIMIT 20
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Recent payouts
        $report['recent_payouts'] = $db->query("
            SELECT cw.id, a.code, cw.amount_requested, cw.processed_at
            FROM commission_withdrawals cw
            JOIN affiliates a ON cw.affiliate_id = a.id
            WHERE cw.status = 'processed'
            ORDER BY cw.processed_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        return $report;
    } catch (Exception $e) {
        error_log("❌ COMMISSION REPORT ERROR: " . $e->getMessage());
        return null;
    }
}

/**
 * PHASE 4 CONTINUED: Balance reconciliation - verify affiliate balance accuracy
 * Compares expected commission from sales table vs. affiliate balance
 */
function reconcileAffiliateBalance($affiliateId)
{
    $db = getDb();
    try {
        // Get affiliate current balance
        $stmt = $db->prepare("SELECT id, code, commission_earned, commission_pending, commission_paid FROM affiliates WHERE id = ?");
        $stmt->execute([$affiliateId]);
        $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$affiliate) {
            return ['success' => false, 'message' => 'Affiliate not found'];
        }
        
        // Calculate expected commission from sales table (total earned from sales records)
        $stmt = $db->prepare("SELECT COALESCE(SUM(commission_amount), 0) as total FROM sales WHERE affiliate_id = ?");
        $stmt->execute([$affiliateId]);
        $salesTotal = $stmt->fetch(PDO::FETCH_ASSOC);
        $expectedEarned = floatval($salesTotal['total']);
        
        // Calculate commission from commission_log (audit trail verification)
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM commission_log 
            WHERE affiliate_id = ? AND action = 'commission_earned'
        ");
        $stmt->execute([$affiliateId]);
        $logTotal = $stmt->fetch(PDO::FETCH_ASSOC);
        $loggedEarned = floatval($logTotal['total']);
        
        // Get withdrawal totals for paid amount verification
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM withdrawal_requests 
            WHERE affiliate_id = ? AND status = 'paid'
        ");
        $stmt->execute([$affiliateId]);
        $paidTotal = $stmt->fetch(PDO::FETCH_ASSOC);
        $expectedPaid = floatval($paidTotal['total']);
        
        // Calculate expected pending = earned - paid
        $expectedPending = $expectedEarned - $expectedPaid;
        
        // Current stored values
        $storedEarned = floatval($affiliate['commission_earned']);
        $storedPending = floatval($affiliate['commission_pending']);
        $storedPaid = floatval($affiliate['commission_paid']);
        
        // Calculate discrepancies
        $earnedDiscrepancy = abs($expectedEarned - $storedEarned);
        $pendingDiscrepancy = abs($expectedPending - $storedPending);
        $paidDiscrepancy = abs($expectedPaid - $storedPaid);
        $salesVsLogDiscrepancy = abs($expectedEarned - $loggedEarned);
        
        $isBalanced = ($earnedDiscrepancy < 0.01 && $pendingDiscrepancy < 0.01 && $paidDiscrepancy < 0.01);
        
        return [
            'success' => true,
            'affiliate_id' => $affiliateId,
            'affiliate_code' => $affiliate['code'],
            'balanced' => $isBalanced,
            'expected' => [
                'earned' => $expectedEarned,
                'pending' => $expectedPending,
                'paid' => $expectedPaid,
                'logged' => $loggedEarned
            ],
            'stored' => [
                'earned' => $storedEarned,
                'pending' => $storedPending,
                'paid' => $storedPaid
            ],
            'discrepancies' => [
                'earned' => $earnedDiscrepancy,
                'pending' => $pendingDiscrepancy,
                'paid' => $paidDiscrepancy,
                'sales_vs_log' => $salesVsLogDiscrepancy
            ]
        ];
    } catch (Exception $e) {
        error_log("❌ RECONCILIATION ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Reconcile all active affiliates and return summary
 */
function reconcileAllAffiliateBalances()
{
    $db = getDb();
    $results = [];
    $issuesFound = 0;
    
    try {
        $affiliates = $db->query("SELECT id, code FROM affiliates WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($affiliates as $affiliate) {
            $result = reconcileAffiliateBalance($affiliate['id']);
            if ($result['success']) {
                if (!$result['balanced']) {
                    $issuesFound++;
                    error_log("⚠️ BALANCE DISCREPANCY: Affiliate #{$affiliate['id']} ({$affiliate['code']}) - Earned diff: ₦" . number_format($result['discrepancies']['earned'], 2));
                }
                $results[] = $result;
            }
        }
        
        return [
            'success' => true,
            'total_affiliates' => count($affiliates),
            'issues_found' => $issuesFound,
            'all_balanced' => $issuesFound === 0,
            'details' => $results
        ];
    } catch (Exception $e) {
        error_log("❌ RECONCILE ALL ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * DATA INTEGRITY: Verify users and affiliates relationship
 * Ensures every affiliate user has a corresponding affiliate record
 * Creates missing affiliate records to prevent orphaned users
 */
function verifyUserAffiliateIntegrity()
{
    $db = getDb();
    $results = ['fixed' => 0, 'errors' => [], 'orphaned' => []];
    
    try {
        // Find affiliate users without affiliate records
        $orphans = $db->query("
            SELECT u.id, u.name, u.email FROM users u 
            WHERE u.role = 'affiliate' 
            AND u.id NOT IN (SELECT user_id FROM affiliates WHERE user_id IS NOT NULL)
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orphans as $user) {
            // Generate unique affiliate code
            $code = strtoupper(substr($user['email'], 0, strpos($user['email'], '@')));
            $originalCode = $code;
            $counter = 1;
            
            while ($db->query("SELECT id FROM affiliates WHERE code = '$code'")->fetchColumn()) {
                $code = $originalCode . $counter;
                $counter++;
            }
            
            // Create affiliate record
            $stmt = $db->prepare("
                INSERT INTO affiliates (user_id, code, status, created_at, updated_at)
                VALUES (?, ?, 'active', datetime('now', '+1 hour'), datetime('now', '+1 hour'))
            ");
            
            if ($stmt->execute([$user['id'], $code])) {
                $results['fixed']++;
                error_log("✅ DATA INTEGRITY: Created affiliate record for User #{$user['id']} with code $code");
            } else {
                $results['errors'][] = "Failed to create affiliate for User #{$user['id']}";
                $results['orphaned'][] = $user;
            }
        }
        
        return [
            'success' => true,
            'orphaned_found' => count($orphans),
            'fixed_count' => $results['fixed'],
            'errors' => $results['errors'],
            'details' => $results['orphaned']
        ];
    } catch (Exception $e) {
        error_log("❌ DATA INTEGRITY ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get data integrity report (users, affiliates, commissions)
 * Used for monitoring system health
 */
function getDataIntegrityReport()
{
    $db = getDb();
    
    try {
        $report = [];
        
        // User-Affiliate Relationship
        $report['users_total'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $report['users_admin'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
        $report['users_affiliate'] = $db->query("SELECT COUNT(*) FROM users WHERE role = 'affiliate'")->fetchColumn();
        
        $report['affiliates_total'] = $db->query("SELECT COUNT(*) FROM affiliates")->fetchColumn();
        $report['affiliates_active'] = $db->query("SELECT COUNT(*) FROM affiliates WHERE status = 'active'")->fetchColumn();
        
        // Check for orphaned users
        $orphanedCount = $db->query("
            SELECT COUNT(*) FROM users u 
            WHERE u.role = 'affiliate' 
            AND u.id NOT IN (SELECT user_id FROM affiliates WHERE user_id IS NOT NULL)
        ")->fetchColumn();
        $report['orphaned_affiliate_users'] = $orphanedCount;
        
        // Commission integrity
        $report['sales_total'] = $db->query("SELECT COUNT(*) FROM sales")->fetchColumn();
        $report['commissions_logged'] = $db->query("SELECT COUNT(*) FROM commission_log")->fetchColumn();
        $report['total_commission_earned'] = $db->query("SELECT COALESCE(SUM(commission_earned), 0) FROM affiliates")->fetchColumn();
        
        // Data consistency check
        $report['is_healthy'] = ($orphanedCount == 0);
        
        return [
            'success' => true,
            'timestamp' => date('Y-m-d H:i:s'),
            'report' => $report
        ];
    } catch (Exception $e) {
        error_log("❌ DATA INTEGRITY REPORT ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * PHASE 6: Log rotation and cleanup
 * Keeps database size manageable by archiving/deleting old log entries
 */
function cleanupOldLogs($daysToKeep = 90)
{
    $db = getDb();
    $results = [];
    
    try {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        
        // Activity logs - keep last N days
        $stmt = $db->prepare("DELETE FROM activity_logs WHERE created_at < ?");
        $stmt->execute([$cutoffDate]);
        $results['activity_logs'] = $stmt->rowCount();
        
        // Commission logs - keep last N days (but preserve commission_earned and sales_record_created)
        $stmt = $db->prepare("
            DELETE FROM commission_log 
            WHERE created_at < ? 
            AND action NOT IN ('commission_earned', 'sales_record_created')
        ");
        $stmt->execute([$cutoffDate]);
        $results['commission_log'] = $stmt->rowCount();
        
        // Old expired pending orders (older than 30 days and still expired)
        $expiredCutoff = date('Y-m-d H:i:s', strtotime("-30 days"));
        $stmt = $db->prepare("DELETE FROM pending_orders WHERE status = 'expired' AND created_at < ?");
        $stmt->execute([$expiredCutoff]);
        $results['expired_orders'] = $stmt->rowCount();
        
        // Vacuum the database to reclaim space (SQLite specific)
        $db->exec("VACUUM");
        
        $totalDeleted = array_sum($results);
        error_log("🧹 LOG CLEANUP: Deleted $totalDeleted records (Activity: {$results['activity_logs']}, Commission: {$results['commission_log']}, Expired Orders: {$results['expired_orders']})");
        
        return [
            'success' => true,
            'total_deleted' => $totalDeleted,
            'details' => $results,
            'cutoff_date' => $cutoffDate
        ];
    } catch (Exception $e) {
        error_log("❌ LOG CLEANUP ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Get log statistics for monitoring
 */
function getLogStats()
{
    $db = getDb();
    
    try {
        $stats = [];
        
        // Activity logs
        $stats['activity_logs'] = [
            'total' => $db->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn(),
            'last_7_days' => $db->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= datetime('now', '-7 days')")->fetchColumn(),
            'oldest' => $db->query("SELECT MIN(created_at) FROM activity_logs")->fetchColumn()
        ];
        
        // Commission logs
        $stats['commission_log'] = [
            'total' => $db->query("SELECT COUNT(*) FROM commission_log")->fetchColumn(),
            'by_action' => $db->query("SELECT action, COUNT(*) as count FROM commission_log GROUP BY action")->fetchAll(PDO::FETCH_KEY_PAIR),
            'oldest' => $db->query("SELECT MIN(created_at) FROM commission_log")->fetchColumn()
        ];
        
        // Pending orders
        $stats['pending_orders'] = [
            'total' => $db->query("SELECT COUNT(*) FROM pending_orders")->fetchColumn(),
            'by_status' => $db->query("SELECT status, COUNT(*) as count FROM pending_orders GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR)
        ];
        
        // Database size
        $dbFile = __DIR__ . '/../database/webdaddy.db';
        if (file_exists($dbFile)) {
            $stats['database_size'] = filesize($dbFile);
            $stats['database_size_formatted'] = formatBytes(filesize($dbFile));
        } else {
            $stats['database_size'] = 0;
            $stats['database_size_formatted'] = '0 B';
        }
        
        return ['success' => true, 'stats' => $stats];
    } catch (Exception $e) {
        error_log("❌ LOG STATS ERROR: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function updateAffiliateCommission($affiliateId, $commissionAmount)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("
            UPDATE affiliates 
            SET total_sales = total_sales + 1,
                commission_earned = commission_earned + ?,
                commission_pending = commission_pending + ?
            WHERE id = ?
        ");
        
        $stmt->execute([$commissionAmount, $commissionAmount, $affiliateId]);
        return true;
    } catch (PDOException $e) {
        error_log('Error updating affiliate commission: ' . $e->getMessage());
        return false;
    }
}

function logActivity($action, $details = '', $userId = null)
{
    $db = getDb();
    
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $stmt = $db->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$userId, $action, $details, $ipAddress, $userAgent]);
        return true;
    } catch (PDOException $e) {
        error_log('Error logging activity: ' . $e->getMessage());
        return false;
    }
}

function generateWhatsAppLink($orderData, $template = null)
{
    $number = preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126'));
    
    $message = "🛒 *NEW ORDER REQUEST*\n";
    $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
    
    if (!empty($orderData['order_id'])) {
        $message .= "📋 *Order ID:* #" . $orderData['order_id'] . "\n\n";
        
        $order = getOrderById($orderData['order_id']);
        if ($order) {
            $orderItems = getOrderItems($orderData['order_id']);
            
            if (!empty($orderItems)) {
                $templateCount = 0;
                $toolCount = 0;
                $templates = [];
                $tools = [];
                
                foreach ($orderItems as $item) {
                    if ($item['product_type'] === 'template') {
                        $templateCount++;
                        $qty = $item['quantity'] > 1 ? ' *(x' . $item['quantity'] . ')*' : '';
                        $templates[] = "  ✅ " . $item['template_name'] . $qty;
                    } else {
                        $toolCount++;
                        $qty = $item['quantity'] > 1 ? ' *(x' . $item['quantity'] . ')*' : '';
                        $tools[] = "  ✅ " . $item['tool_name'] . $qty;
                    }
                }
                
                if ($templateCount > 0) {
                    $message .= "🎨 *TEMPLATES* (" . $templateCount . "):\n";
                    $message .= implode("\n", $templates) . "\n";
                    if ($toolCount > 0) {
                        $message .= "\n";
                    }
                }
                
                if ($toolCount > 0) {
                    $message .= "🔧 *TOOLS* (" . $toolCount . "):\n";
                    $message .= implode("\n", $tools) . "\n";
                }
            } elseif ($template) {
                $message .= "🎨 *TEMPLATES* (1):\n";
                $message .= "  ✅ " . $template['name'] . "\n";
            }
            
            $message .= "\n━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💳 *Please proceed to payment to continue.*\n";
        } elseif ($template) {
            $message .= "🎨 *TEMPLATES* (1):\n";
            $message .= "  ✅ " . $template['name'] . "\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💳 *Please proceed to payment to continue.*\n";
        }
    } elseif ($template) {
        $message .= "🎨 *TEMPLATES* (1):\n";
        $message .= "  ✅ " . $template['name'] . "\n\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💳 *Please proceed to payment to continue.*\n";
    }
    
    $encodedMessage = rawurlencode($message);
    
    return "https://wa.me/" . $number . "?text=" . $encodedMessage;
}

function getSetting($key, $default = null)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        error_log('Error getting setting: ' . $e->getMessage());
        return $default;
    }
}

function calculateAffiliateCommission($orderId)
{
    $order = getOrderById($orderId);
    if (!$order) {
        return 0;
    }
    
    $template = getTemplateById($order['template_id']);
    if (!$template) {
        return 0;
    }
    
    return $template['price'] * AFFILIATE_COMMISSION_RATE;
}

function renderOrderStatusBadge($status)
{
    $statusColors = [
        'pending' => 'bg-yellow-100 text-yellow-800',
        'paid' => 'bg-green-100 text-green-800',
        'cancelled' => 'bg-red-100 text-red-800'
    ];
    $statusIcons = [
        'pending' => 'hourglass-split',
        'paid' => 'check-circle',
        'cancelled' => 'x-circle'
    ];
    $color = $statusColors[$status] ?? 'bg-gray-100 text-gray-800';
    $icon = $statusIcons[$status] ?? 'circle';
    
    return sprintf(
        '<span class="inline-flex items-center px-3 py-1 %s rounded-full text-xs font-semibold"><i class="bi bi-%s mr-1"></i>%s</span>',
        $color,
        $icon,
        ucfirst($status)
    );
}

function renderOrderTypeProductList($orderItems, $fallbackTemplateName = null, $fallbackToolName = null, $maxDisplay = 2)
{
    if (!empty($orderItems)) {
        $itemCount = count($orderItems);
        $hasTemplates = false;
        $hasTools = false;
        
        foreach ($orderItems as $item) {
            if ($item['product_type'] === 'template') $hasTemplates = true;
            if ($item['product_type'] === 'tool') $hasTools = true;
        }
        
        $typeBadge = '';
        if ($hasTemplates && $hasTools) {
            $typeBadge = '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-800"><i class="bi bi-box-seam mr-1"></i>Mixed</span></div>';
        } elseif ($hasTools) {
            $typeBadge = '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tools</span></div>';
        } else {
            $typeBadge = '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div>';
        }
        
        $productHtml = '<div class="text-sm text-gray-900">';
        foreach (array_slice($orderItems, 0, $maxDisplay) as $item) {
            $productType = $item['product_type'];
            $productName = $productType === 'template' ? $item['template_name'] : $item['tool_name'];
            $typeIcon = ($productType === 'template') ? '🎨' : '🔧';
            $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
            $productHtml .= $typeIcon . ' ' . htmlspecialchars($productName) . $qty . '<br/>';
        }
        if ($itemCount > $maxDisplay) {
            $productHtml .= '<span class="text-xs text-gray-500">+' . ($itemCount - $maxDisplay) . ' more item' . ($itemCount - $maxDisplay > 1 ? 's' : '') . '</span>';
        }
        $productHtml .= '</div>';
        
        return $typeBadge . $productHtml;
    } elseif ($fallbackTemplateName) {
        return '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800"><i class="bi bi-palette mr-1"></i>Template</span></div><div class="text-gray-900 text-sm">🎨 ' . htmlspecialchars($fallbackTemplateName) . '</div>';
    } elseif ($fallbackToolName) {
        return '<div class="mb-1"><span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800"><i class="bi bi-tools mr-1"></i>Tool</span></div><div class="text-gray-900 text-sm">🔧 ' . htmlspecialchars($fallbackToolName) . '</div>';
    } else {
        return '<span class="text-gray-400">No items</span>';
    }
}

function setOrderItemDomain($orderItemId, $domainId, $orderId)
{
    $db = getDb();
    
    try {
        $db->beginTransaction();
        
        $itemStmt = $db->prepare("SELECT id, metadata_json, product_type FROM order_items WHERE id = ? AND pending_order_id = ?");
        $itemStmt->execute([$orderItemId, $orderId]);
        $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception('Order item not found');
        }
        
        if ($item['product_type'] !== 'template') {
            throw new Exception('Domains can only be assigned to template items');
        }
        
        $checkDomainStmt = $db->prepare("SELECT id, domain_name, status, assigned_order_id FROM domains WHERE id = ?");
        $checkDomainStmt->execute([$domainId]);
        $domain = $checkDomainStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$domain) {
            throw new Exception('Domain not found');
        }
        
        // Domain must be available OR already assigned to this same order (for reassignment)
        if ($domain['status'] !== 'available' && 
            !($domain['status'] === 'in_use' && $domain['assigned_order_id'] == $orderId)) {
            throw new Exception('Domain is not available');
        }
        
        $metadata = [];
        if (!empty($item['metadata_json'])) {
            $metadata = json_decode($item['metadata_json'], true) ?: [];
        }
        
        // Release previous domain if it's different from the new one
        if (isset($metadata['domain_id']) && $metadata['domain_id'] > 0 && $metadata['domain_id'] != $domainId) {
            $previousDomainId = $metadata['domain_id'];
            $releasePreviousStmt = $db->prepare("
                UPDATE domains 
                SET status = 'available', assigned_order_id = NULL 
                WHERE id = ? AND assigned_order_id = ?
            ");
            $releasePreviousStmt->execute([$previousDomainId, $orderId]);
        }
        
        $metadata['domain_id'] = $domainId;
        $metadata['domain_name'] = $domain['domain_name'];
        
        $updateItemStmt = $db->prepare("UPDATE order_items SET metadata_json = ? WHERE id = ?");
        $updateItemStmt->execute([json_encode($metadata), $orderItemId]);
        
        $updateDomainStmt = $db->prepare("
            UPDATE domains 
            SET status = 'in_use', assigned_order_id = ? 
            WHERE id = ?
        ");
        $updateDomainStmt->execute([$orderId, $domainId]);
        
        $db->commit();
        
        return ['success' => true, 'message' => 'Domain assigned to item successfully'];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Domain assignment error for item #' . $orderItemId . ': ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function cancelOrder($orderId, $reason = '', $adminId = null)
{
    $db = getDb();
    
    try {
        $db->beginTransaction();
        
        $order = getOrderById($orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        if ($order['status'] === 'cancelled') {
            throw new Exception('Order is already cancelled');
        }
        
        if (in_array($order['status'], ['paid', 'completed', 'fulfilled'])) {
            throw new Exception('Cannot cancel a paid or completed order. Please contact support for refunds.');
        }
        
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'cancelled', cancellation_reason = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$reason, $orderId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Failed to update order status');
        }
        
        // Note: order_items table doesn't have a status column - cancellation is tracked via pending_orders.status
        // We just need to release domains and restore stock for cancelled orders
        
        $domainStmt = $db->prepare("
            UPDATE domains 
            SET status = 'available', assigned_order_id = NULL 
            WHERE assigned_order_id = ? AND status IN ('reserved', 'in_use')
        ");
        $domainStmt->execute([$orderId]);
        $domainsReleased = $domainStmt->rowCount();
        
        $releasedDomainItemsStmt = $db->prepare("
            SELECT oi.id, oi.metadata_json
            FROM order_items oi
            WHERE oi.pending_order_id = ? AND oi.product_type = 'template'
        ");
        $releasedDomainItemsStmt->execute([$orderId]);
        $orderItems = $releasedDomainItemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($orderItems as $item) {
            if (!empty($item['metadata_json'])) {
                $metadata = json_decode($item['metadata_json'], true);
                if (isset($metadata['domain_id']) && $metadata['domain_id'] > 0) {
                    $releaseDomainStmt = $db->prepare("
                        UPDATE domains 
                        SET status = 'available', assigned_order_id = NULL 
                        WHERE id = ? AND status IN ('reserved', 'in_use')
                    ");
                    $releaseDomainStmt->execute([$metadata['domain_id']]);
                }
            }
        }
        
        if ($order['status'] === 'paid') {
            $restoreCommissionStmt = $db->prepare("
                SELECT affiliate_id, commission_amount
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
        return UPLOAD_URL . substr($url, 8);
    }
    
    if (strpos($url, 'uploads/') === 0) {
        return UPLOAD_URL . '/' . substr($url, 8);
    }
    
    return UPLOAD_URL . '/' . $url;
}

/**
 * Check if an email is already registered as an affiliate in the USERS table
 */
function isEmailAffiliate($email) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM users 
        WHERE email = ? AND role = 'affiliate' AND status = 'active'
    ");
    $stmt->execute([strtolower(trim($email))]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Check if affiliate invitation has already been sent to this email
 * Checks email_queue table for previous invitation sends
 */
function hasAffiliateInvitationBeenSent($email) {
    $db = getDb();
    // Check if this email already has an affiliate invitation queued or sent
    // This is more reliable than checking pending_orders since it tracks actual sends
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM email_queue 
        WHERE recipient_email = ? 
        AND email_type = 'affiliate_invitation'
        AND status IN ('pending', 'sent', 'failed')
    ");
    $stmt->execute([strtolower(trim($email))]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

function sendAffiliateOpportunityEmail($customerName, $customerEmail)
{
    // Queue the affiliate invitation email for TRACKING
    // This ensures we can check if it was already sent on the next purchase
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "🤝 Earn 30% Commission - Join WebDaddy Empire Affiliates!";
    $affiliateRegisterUrl = (defined('SITE_URL') ? SITE_URL : 'https://webdaddy.com') . '/affiliate/register.php';
    
    $content = "
    <h2 style='color: #2563eb;'>Thank You for Your Purchase! 🎉</h2>
    
    <p>Hi <strong>" . htmlspecialchars($customerName) . "</strong>,</p>
    
    <p>We appreciate your business! We wanted to let you know about an amazing opportunity to <strong>earn passive income</strong> by recommending WebDaddy Empire to others.</p>
    
    <h3 style='color: #2563eb;'>💰 Affiliate Program Benefits</h3>
    <ul style='line-height: 1.8;'>
        <li><strong>30% Commission</strong> on every referral that converts</li>
        <li><strong>Recurring Income</strong> from customers you refer</li>
        <li><strong>Easy Sharing</strong> - We give you a unique referral code</li>
        <li><strong>Real-time Tracking</strong> - Monitor your earnings anytime</li>
        <li><strong>No Caps</strong> - Earn as much as you can!</li>
    </ul>
    
    <h3 style='color: #2563eb;'>🚀 Get Started Now</h3>
    <p><strong><a href=\"" . htmlspecialchars($affiliateRegisterUrl) . "\" style='color: #2563eb; text-decoration: none; font-weight: bold;'>👉 Click Here to Register as an Affiliate →</a></strong></p>
    
    <p style='margin-top: 20px; line-height: 1.8;'>Once registered, you'll receive:</p>
    <ul style='line-height: 1.8;'>
        <li>✅ Your unique affiliate code</li>
        <li>✅ Real-time earnings dashboard</li>
        <li>✅ Marketing materials to share</li>
        <li>✅ Support from our team</li>
    </ul>
    
    <p style='margin-top: 30px; font-size: 14px; color: #666;'>Your unique referral link will be in the format: <code>webdaddy.com?aff=YOUR_CODE</code></p>
    
    <p><strong>Questions?</strong> Just reply to this email or contact us on WhatsApp!</p>
    ";
    
    $emailHtml = createEmailTemplate($subject, $content, $customerName);
    
    // QUEUE the email for proper tracking in email_queue table
    // This ensures we can detect duplicates on next purchase
    $emailId = queueEmail(
        $customerEmail,
        'affiliate_invitation',
        $subject,
        strip_tags($content),
        $emailHtml
    );
    
    if ($emailId) {
        error_log("Affiliate opportunity email queued for: $customerEmail (ID: $emailId)");
        return true;
    } else {
        error_log("Failed to queue affiliate opportunity email for: $customerEmail");
        return false;
    }
}

/**
 * Encrypt credential data using AES-256-GCM
 * Used for template admin passwords and sensitive delivery credentials
 * 
 * @param string $data The data to encrypt
 * @return string|false Base64 encoded encrypted data with IV prefix, or false on failure
 */
function encryptCredential($data) {
    if (empty($data)) {
        return '';
    }
    
    $encryptionKey = getEncryptionKey();
    $cipher = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $tag = '';
    
    $encrypted = openssl_encrypt($data, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
    
    if ($encrypted === false) {
        error_log('Credential encryption failed: ' . openssl_error_string());
        return false;
    }
    
    return base64_encode($iv . $tag . $encrypted);
}

/**
 * Mask a password for display (show first 2 and last 2 characters)
 * Phase 1: Template Credentials System
 * 
 * @param string $password The password to mask
 * @return string The masked password
 */
function maskPassword($password) {
    if (strlen($password) <= 4) {
        return str_repeat('*', strlen($password));
    }
    $first = substr($password, 0, 2);
    $last = substr($password, -2);
    $middle = str_repeat('*', max(4, strlen($password) - 4));
    return $first . $middle . $last;
}

/**
 * Decrypt credential data using AES-256-GCM
 * 
 * @param string $encryptedData Base64 encoded encrypted data
 * @return string|false Decrypted data, or false on failure
 */
function decryptCredential($encryptedData) {
    if (empty($encryptedData)) {
        return '';
    }
    
    $encryptionKey = getEncryptionKey();
    $cipher = 'aes-256-gcm';
    $ivLength = openssl_cipher_iv_length($cipher);
    $tagLength = 16;
    
    $decoded = base64_decode($encryptedData);
    if ($decoded === false) {
        error_log('Credential decryption failed: Invalid base64 encoding');
        return false;
    }
    
    if (strlen($decoded) < $ivLength + $tagLength) {
        error_log('Credential decryption failed: Data too short');
        return false;
    }
    
    $iv = substr($decoded, 0, $ivLength);
    $tag = substr($decoded, $ivLength, $tagLength);
    $encrypted = substr($decoded, $ivLength + $tagLength);
    
    $decrypted = openssl_decrypt($encrypted, $cipher, $encryptionKey, OPENSSL_RAW_DATA, $iv, $tag);
    
    if ($decrypted === false) {
        error_log('Credential decryption failed: ' . openssl_error_string());
        return false;
    }
    
    return $decrypted;
}

/**
 * Get encryption key for credential storage
 * Uses a STABLE site-specific key - NEVER use values that change like file modification times
 * 
 * @return string 32-byte encryption key
 */
function getEncryptionKey() {
    $keyComponents = [
        'webdaddy_empire_credential_encryption_v2_stable',
        defined('SMTP_PASS') ? SMTP_PASS : 'default_salt_webdaddy',
        'fixed_nonce_2024_credential_storage'
    ];
    
    return hash('sha256', implode(':', $keyComponents), true);
}

