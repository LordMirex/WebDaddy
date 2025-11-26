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

function getTemplateUrl($template, $affiliateCode = null)
{
    if (is_array($template)) {
        $slug = $template['slug'] ?? '';
    } else {
        $slug = $template;
    }
    
    // Direct link to template.php with slug parameter (works in both development and production)
    $url = '/template.php?slug=' . urlencode($slug);
    
    if ($affiliateCode) {
        $url .= '&aff=' . urlencode($affiliateCode);
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
             original_price, discount_amount, final_amount, order_type)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)
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
            $data['payment_method'] ?? 'manual',
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
             message_text, ip_address, status, payment_method, original_price, discount_amount, final_amount, 
             quantity, cart_snapshot, payment_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?)
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
            $orderData['payment_method'] ?? 'manual',
            $orderData['original_price'] ?? null,
            $orderData['discount_amount'] ?? 0,
            $orderData['final_amount'],
            $orderData['quantity'] ?? 1,
            $orderData['cart_snapshot'] ?? null,
            $orderData['payment_notes'] ?? null
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
        
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'paid', payment_notes = ? WHERE id = ?");
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
        
        // Create delivery records for automatic tool delivery and template tracking (Phase 3)
        // IDEMPOTENCY: Only create deliveries if they don't already exist
        // ERROR HANDLING: Track failures for admin visibility but don't block payment confirmation
        $deliveryError = null;
        try {
            require_once __DIR__ . '/delivery.php';
            $existingDeliveries = getDeliveryStatus($orderId);
            if (empty($existingDeliveries)) {
                createDeliveryRecords($orderId);
            }
        } catch (Exception $e) {
            $deliveryError = $e->getMessage();
            error_log("Failed to create delivery records for order #{$orderId}: " . $deliveryError);
            // Don't throw - payment is already confirmed, admin can manually retry delivery creation
        }
        
        // Send enhanced payment confirmation email to customer
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
            
            // For mixed orders, send delivery summary email explaining what happens next
            if ($order['order_type'] === 'mixed' && function_exists('sendMixedOrderDeliverySummaryEmail')) {
                sendMixedOrderDeliverySummaryEmail($orderId);
            }
            
            // NOTE: Affiliate opportunity email is sent IMMEDIATELY when order is created (PENDING)
            // See cart-checkout.php for the actual send - this prevents duplicate emails
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

/**
 * UNIFIED COMMISSION PROCESSOR: Called for both Paystack and Manual payments
 * Ensures commissions are calculated and credited identically regardless of payment method
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
        
        // Calculate commission only if affiliate code exists
        if (!empty($order['affiliate_code'])) {
            $affiliate = getAffiliateByCode($order['affiliate_code']);
            
            if ($affiliate && $affiliate['status'] === 'active') {
                // Commission base is final_amount (already discounted)
                $commissionBase = $order['final_amount'];
                $commissionRate = $affiliate['custom_commission_rate'] ?? AFFILIATE_COMMISSION_RATE;
                $commissionAmount = $commissionBase * $commissionRate;
                $affiliateId = $affiliate['id'];
                
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
            VALUES (?, NULL, ?, ?, ?, datetime('now'))
        ");
        $salesStmt->execute([$orderId, $order['final_amount'], $commissionAmount, $affiliateId]);
        
        // Log sales record creation
        logCommissionTransaction($orderId, $affiliateId, 'sales_record_created', $commissionAmount, 'Revenue recorded in sales table');
        
        error_log("✅ COMMISSION PROCESSOR: Sales record created for Order #$orderId. Commission: ₦" . number_format($commissionAmount, 2));
        
        return [
            'success' => true,
            'order_id' => $orderId,
            'commission_amount' => $commissionAmount,
            'affiliate_id' => $affiliateId,
            'message' => 'Commission processed successfully'
        ];
        
    } catch (Exception $e) {
        error_log("❌ COMMISSION PROCESSOR: Error - " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * PHASE 4: Log commission transaction for audit trail
 * Tracks all commission movements for reconciliation
 */
function logCommissionTransaction($orderId, $affiliateId, $action, $amount, $details = '')
{
    $db = getDb();
    try {
        $stmt = $db->prepare("
            INSERT INTO commission_log (order_id, affiliate_id, action, amount, details, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([$orderId, $affiliateId, $action, $amount, $details]);
        error_log("📝 COMMISSION LOG: Order #$orderId - Action: $action | Amount: ₦" . number_format($amount, 2));
        return true;
    } catch (Exception $e) {
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
            VALUES (?, ?, ?, ?, datetime('now'), 'pending')
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
            SET status = 'processed', processed_at = datetime('now')
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
 * Uses a site-specific key derived from multiple sources for security
 * 
 * @return string 32-byte encryption key
 */
function getEncryptionKey() {
    $dbPath = __DIR__ . '/../database/webdaddy.db';
    $keyComponents = [
        'webdaddy_empire_credential_encryption_v1',
        defined('SMTP_PASS') ? SMTP_PASS : 'default_salt',
        file_exists($dbPath) ? filemtime($dbPath) : 'no_db'
    ];
    
    return hash('sha256', implode(':', $keyComponents), true);
}

