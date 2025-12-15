<?php
/**
 * Customer Authentication Middleware for User Dashboard
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_session.php';

function requireCustomer() {
    $customer = validateCustomerSession();
    
    if (!$customer) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /user/login.php');
        exit;
    }
    
    return $customer;
}

function getCustomerId() {
    $customer = getCurrentCustomer();
    return $customer ? $customer['id'] : null;
}

function getCustomerName() {
    $customer = getCurrentCustomer();
    if (!$customer) return 'Guest';
    return $customer['full_name'] ?: $customer['username'] ?: explode('@', $customer['email'])[0];
}

function getCustomerEmail() {
    $customer = getCurrentCustomer();
    return $customer ? $customer['email'] : null;
}

function getCustomerOrderCount($customerId, $status = null) {
    $db = getDb();
    $sql = "SELECT COUNT(*) FROM pending_orders WHERE customer_id = ?";
    $params = [$customerId];
    
    if ($status && $status !== 'all') {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function getCustomerPendingDeliveries($customerId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE po.customer_id = ? AND d.delivery_status != 'delivered'
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchColumn();
}

function getCustomerOpenTickets($customerId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM customer_support_tickets 
        WHERE customer_id = ? AND status NOT IN ('resolved', 'closed')
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchColumn();
}

function getCustomerOrders($customerId, $limit = 20, $offset = 0, $status = null) {
    $db = getDb();
    
    $sql = "
        SELECT po.*, 
               (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count
        FROM pending_orders po
        WHERE po.customer_id = ?
    ";
    $params = [$customerId];
    
    if ($status && $status !== 'all') {
        $sql .= " AND po.status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY po.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getOrderForCustomer($orderId, $customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT po.* FROM pending_orders po
        WHERE po.id = ? AND po.customer_id = ?
    ");
    $stmt->execute([$orderId, $customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getOrderItemsWithDelivery($orderId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT 
            oi.*,
            oi.final_amount as price,
            COALESCE(t.name, tl.name) as product_name,
            COALESCE(t.thumbnail_url, tl.thumbnail_url) as product_thumbnail,
            d.id as delivery_id,
            d.delivery_status,
            d.delivered_at,
            d.hosted_domain,
            d.hosted_url as domain_login_url,
            d.delivery_link as download_link,
            d.template_admin_username as admin_username,
            d.template_admin_password as admin_password_encrypted,
            d.template_login_url as login_url,
            d.delivery_note as delivery_note,
            d.delivery_instructions as delivery_instructions,
            d.admin_notes as admin_notes,
            tl.delivery_note as tool_delivery_note
        FROM order_items oi
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
        LEFT JOIN deliveries d ON d.pending_order_id = oi.pending_order_id 
                               AND d.product_type = oi.product_type 
                               AND d.product_id = oi.product_id
        WHERE oi.pending_order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    require_once __DIR__ . '/../../includes/functions.php';
    
    foreach ($items as &$item) {
        $item['admin_password'] = '';
        if (!empty($item['admin_password_encrypted'])) {
            $decrypted = decryptCredential($item['admin_password_encrypted']);
            if ($decrypted !== false) {
                $item['admin_password'] = $decrypted;
            }
        }
        unset($item['admin_password_encrypted']);
        
        if ($item['product_type'] === 'tool') {
            $item['delivery_note'] = $item['delivery_note'] ?: $item['delivery_instructions'] ?: $item['tool_delivery_note'] ?: '';
        }
    }
    unset($item);
    
    return $items;
}

function getCustomerDownloads($customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT 
            dt.id as token_id,
            dt.token,
            dt.download_count,
            dt.max_downloads,
            dt.expires_at,
            tf.id as file_id,
            tf.file_name,
            tf.file_size,
            t.name as tool_name,
            t.thumbnail_url as tool_thumbnail,
            po.id as order_id,
            po.created_at as order_date
        FROM download_tokens dt
        JOIN tool_files tf ON dt.file_id = tf.id
        JOIN tools t ON tf.tool_id = t.id
        JOIN pending_orders po ON dt.pending_order_id = po.id
        WHERE po.customer_id = ?
        AND po.status = 'paid'
        AND dt.id = (
            SELECT dt2.id 
            FROM download_tokens dt2 
            WHERE dt2.file_id = dt.file_id 
            AND dt2.pending_order_id = dt.pending_order_id 
            ORDER BY dt2.created_at DESC 
            LIMIT 1
        )
        ORDER BY po.created_at DESC, t.name
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCustomerTickets($customerId, $status = null) {
    $db = getDb();
    
    $sql = "SELECT * FROM customer_support_tickets WHERE customer_id = ?";
    $params = [$customerId];
    
    if ($status && $status !== 'all') {
        if ($status === 'open') {
            $sql .= " AND status NOT IN ('resolved', 'closed')";
        } else {
            $sql .= " AND status = ?";
            $params[] = $status;
        }
    }
    
    $sql .= " ORDER BY updated_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTicketForCustomer($ticketId, $customerId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM customer_support_tickets WHERE id = ? AND customer_id = ?");
    $stmt->execute([$ticketId, $customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTicketReplies($ticketId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM customer_ticket_replies WHERE ticket_id = ? ORDER BY created_at ASC");
    $stmt->execute([$ticketId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function createCustomerTicket($data) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO customer_support_tickets 
        (customer_id, order_id, subject, message, category, attachments)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['customer_id'],
        $data['order_id'] ?: null,
        $data['subject'],
        $data['message'],
        $data['category'] ?? 'general',
        !empty($data['attachments']) ? json_encode($data['attachments']) : null
    ]);
    
    return $db->lastInsertId();
}

function addTicketReply($ticketId, $customerId, $message) {
    $db = getDb();
    
    $check = $db->prepare("SELECT id FROM customer_support_tickets WHERE id = ? AND customer_id = ?");
    $check->execute([$ticketId, $customerId]);
    if (!$check->fetch()) {
        return false;
    }
    
    // Get customer name for the reply
    $customerStmt = $db->prepare("SELECT full_name, email FROM customers WHERE id = ?");
    $customerStmt->execute([$customerId]);
    $customerData = $customerStmt->fetch(PDO::FETCH_ASSOC);
    $authorName = $customerData['full_name'] ?? $customerData['email'] ?? 'Customer';
    
    $stmt = $db->prepare("
        INSERT INTO customer_ticket_replies 
        (ticket_id, author_type, author_id, author_name, message)
        VALUES (?, 'customer', ?, ?, ?)
    ");
    $stmt->execute([$ticketId, $customerId, $authorName, $message]);
    
    $db->prepare("
        UPDATE customer_support_tickets 
        SET last_reply_at = datetime('now'), 
            last_reply_by = 'customer',
            status = CASE WHEN status = 'awaiting_reply' THEN 'open' ELSE status END,
            updated_at = datetime('now')
        WHERE id = ?
    ")->execute([$ticketId]);
    
    return $db->lastInsertId();
}

function updateCustomerProfile($customerId, $data) {
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE customers 
        SET full_name = ?,
            phone = ?,
            updated_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([
        $data['full_name'] ?? null,
        $data['phone'] ?? null,
        $customerId
    ]);
    
    return true;
}

function getCustomerSessions($customerId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT * FROM customer_sessions 
        WHERE customer_id = ? AND is_active = 1 AND expires_at > datetime('now')
        ORDER BY last_activity_at DESC
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
