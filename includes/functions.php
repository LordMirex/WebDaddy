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
             business_name, custom_fields, affiliate_code, session_id, message_text, ip_address, status,
             original_price, discount_amount, final_amount, order_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            throw new PDOException('Failed to prepare statement');
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
            $data['original_price'] ?? null,
            $data['discount_amount'] ?? 0,
            $data['final_amount'] ?? null,
            $data['order_type'] ?? 'template'
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
        
        $stmt = $db->prepare("
            INSERT INTO pending_orders 
            (template_id, tool_id, order_type, chosen_domain_id, customer_name, customer_email, 
             customer_phone, business_name, custom_fields, affiliate_code, session_id, 
             message_text, ip_address, status, original_price, discount_amount, final_amount, 
             quantity, cart_snapshot)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
        ");
        
        $templateId = $orderData['template_id'] ?? null;
        $toolId = $orderData['tool_id'] ?? null;
        
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
            $orderData['affiliate_code'] ?? null,
            $orderData['session_id'] ?? session_id(),
            $orderData['message_text'] ?? null,
            $orderData['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            $orderData['original_price'] ?? null,
            $orderData['discount_amount'] ?? 0,
            $orderData['final_amount'],
            $orderData['quantity'] ?? 1,
            $orderData['cart_snapshot'] ?? null
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
        
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'paid' WHERE id = ?");
        $stmt->execute([$orderId]);
        
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
        
        // Calculate commission from final amount (customer's actual payment after discount)
        if (!empty($order['affiliate_code'])) {
            $affiliate = getAffiliateByCode($order['affiliate_code']);
            if ($affiliate) {
                // Commission calculated from DISCOUNTED price (what customer actually paid)
                // Example: Product ₦10,000 → 20% discount → Customer pays ₦8,000 → Affiliate gets 30% of ₦8,000 = ₦2,400
                $commissionBase = $finalAmount;
                
                // Use custom commission rate if set, otherwise use default
                $commissionRate = $affiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                $commissionAmount = $commissionBase * $commissionRate;
                $affiliateId = $affiliate['id'];
                
                updateAffiliateCommission($affiliateId, $commissionAmount);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO sales (pending_order_id, admin_id, amount_paid, commission_amount, affiliate_id, payment_notes,
                             original_price, discount_amount, final_amount)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$orderId, $adminId, $amountPaid, $commissionAmount, $affiliateId, $paymentNotes,
                       $originalPrice, $discountAmount, $finalAmount]);
        
        $db->commit();
        
        // Send enhanced payment confirmation email to customer
        if (!empty($order['customer_email'])) {
            // Get domain name for template orders
            $domainName = !empty($order['domain_name']) ? $order['domain_name'] : null;
            
            // TODO: Add credentials if available (would need to be stored in order or generated)
            $credentials = null;
            
            // Send enhanced email with full order details
            sendEnhancedPaymentConfirmationEmail($order, $orderItems, $domainName, $credentials);
        }
        
        // Send commission earned email to affiliate
        if ($affiliateId && $affiliate) {
            $affiliateUser = getUserById($affiliate['user_id']);
            if ($affiliateUser && !empty($affiliateUser['email'])) {
                // Build detailed product names list from order items
                if (!empty($orderItems)) {
                    $productNames = [];
                    foreach ($orderItems as $item) {
                        // Try template_name/tool_name first, then fallback to metadata, then generic
                        $name = null;
                        if (!empty($item['template_name'])) {
                            $name = $item['template_name'];
                        } elseif (!empty($item['tool_name'])) {
                            $name = $item['tool_name'];
                        } elseif (!empty($item['metadata_json'])) {
                            $metadata = json_decode($item['metadata_json'], true);
                            if (is_array($metadata)) {
                                $name = $metadata['name'] ?? null;
                            }
                        }
                        $name = $name ?? 'Product';
                        
                        $qty = $item['quantity'];
                        $productNames[] = $qty > 1 ? "{$name} (×{$qty})" : $name;
                    }
                    $productName = implode(', ', $productNames);
                } else {
                    // Fallback for legacy orders
                    $template = getTemplateById($order['template_id']);
                    $productName = $template['name'] ?? 'Product';
                }
                
                sendCommissionEarnedEmail(
                    $affiliateUser['name'],
                    $affiliateUser['email'],
                    $orderId,
                    $commissionAmount,
                    $productName
                );
            }
        }
        
        return true;
    } catch (Exception $e) {
        $db->rollBack();
        error_log('Error marking order paid: ' . $e->getMessage());
        return false;
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
    
    $message = "Hello! I have a new order:\n\n";
    
    if (!empty($orderData['order_id'])) {
        $message .= "Order ID: #" . $orderData['order_id'] . "\n\n";
        
        $order = getOrderById($orderData['order_id']);
        if ($order) {
            $orderItems = getOrderItems($orderData['order_id']);
            
            if (!empty($orderItems)) {
                $message .= "Items:\n";
                foreach ($orderItems as $item) {
                    $productName = $item['product_type'] === 'template' ? $item['template_name'] : $item['tool_name'];
                    $qty = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
                    $message .= "• " . $productName . $qty . "\n";
                }
            } elseif ($template) {
                $message .= "Items:\n";
                $message .= "• " . $template['name'] . "\n";
            }
            
            $message .= "\nPlease proceed to payment to continue.";
        } elseif ($template) {
            $message .= "Items:\n";
            $message .= "• " . $template['name'] . "\n";
            $message .= "\nPlease proceed to payment to continue.";
        }
    } elseif ($template) {
        $message .= "Items:\n";
        $message .= "• " . $template['name'] . "\n";
        $message .= "\nPlease proceed to payment to continue.";
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
        
        $itemsStmt = $db->prepare("UPDATE order_items SET status = 'cancelled' WHERE pending_order_id = ?");
        $itemsStmt->execute([$orderId]);
        $itemsAffected = $itemsStmt->rowCount();
        
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
