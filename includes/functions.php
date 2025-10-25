<?php

require_once __DIR__ . '/db.php';

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

function getTemplates($activeOnly = true)
{
    $db = getDb();
    
    $sql = "SELECT * FROM templates WHERE 1=1";
    if ($activeOnly) {
        $sql .= " AND active = 1";
    }
    $sql .= " ORDER BY created_at DESC";
    
    try {
        $result = $db->query($sql);
        return $result->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching templates: ' . $e->getMessage());
        return [];
    }
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
        $stmt = $db->prepare("SELECT * FROM domains WHERE template_id = ? AND status = 'available' ORDER BY domain_name");
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
             business_name, custom_fields, affiliate_code, session_id, message_text, ip_address, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        
        $stmt->execute([
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
            $data['ip_address']
        ]);
        
        $dbType = getDbType();
        if ($dbType === 'pgsql') {
            return $db->lastInsertId('pending_orders_id_seq');
        } else {
            return $db->lastInsertId();
        }
    } catch (PDOException $e) {
        error_log('Error creating pending order: ' . $e->getMessage());
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
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Error fetching orders: ' . $e->getMessage());
        return [];
    }
}

function markOrderPaid($orderId, $adminId, $amountPaid, $paymentNotes = '')
{
    $db = getDb();
    
    $db->begin_transaction();
    
    try {
        $order = getOrderById($orderId);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        $stmt = $db->prepare("UPDATE pending_orders SET status = 'paid' WHERE id = ?");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        
        $commissionAmount = 0;
        $affiliateId = null;
        
        if (!empty($order['affiliate_code'])) {
            $affiliate = getAffiliateByCode($order['affiliate_code']);
            if ($affiliate) {
                $template = getTemplateById($order['template_id']);
                $commissionAmount = $template['price'] * AFFILIATE_COMMISSION_RATE;
                $affiliateId = $affiliate['id'];
                
                updateAffiliateCommission($affiliateId, $commissionAmount);
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO sales (pending_order_id, admin_id, amount_paid, commission_amount, affiliate_id, payment_notes)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iiddis', $orderId, $adminId, $amountPaid, $commissionAmount, $affiliateId, $paymentNotes);
        $stmt->execute();
        
        $db->commit();
        return true;
    } catch (Exception $e) {
        $db->rollback();
        error_log('Error marking order paid: ' . $e->getMessage());
        return false;
    }
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
            SELECT po.*, t.name as template_name, t.price as template_price, d.domain_name 
            FROM pending_orders po
            LEFT JOIN templates t ON po.template_id = t.id
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

function getAffiliateByCode($code)
{
    $db = getDb();
    
    try {
        $stmt = $db->prepare("SELECT * FROM affiliates WHERE code = ? AND status = 'active'");
        $stmt->execute([$code]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result : null;
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

function generateWhatsAppLink($orderData, $template)
{
    $number = WHATSAPP_NUMBER;
    
    $message = "Hello! I would like to order a website:\n\n";
    $message .= "Template: " . $template['name'] . "\n";
    $message .= "Name: " . $orderData['customer_name'] . "\n";
    $message .= "Email: " . $orderData['customer_email'] . "\n";
    $message .= "Phone: " . $orderData['customer_phone'] . "\n";
    
    if (!empty($orderData['business_name'])) {
        $message .= "Business: " . $orderData['business_name'] . "\n";
    }
    
    if (!empty($orderData['domain_name'])) {
        $message .= "Domain: " . $orderData['domain_name'] . "\n";
    }
    
    $message .= "\nOrder ID: " . $orderData['order_id'] . "\n";
    $message .= "Price: " . formatCurrency($template['price']);
    
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
