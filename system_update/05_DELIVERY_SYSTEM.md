# Delivery System Updates

## Overview

This document details modifications to the existing delivery system to support customer dashboard access. The goal is to allow customers to view delivery status, access credentials, and download files from their dashboard.

## Current Delivery System

### Existing Flow
1. Order marked as paid
2. `createDeliveryRecords()` creates delivery entries
3. For tools: Download tokens generated, email sent
4. For templates: Admin assigns credentials, email sent
5. Customer accesses via email links only

### Existing Tables Used
- `deliveries` - Delivery records per order item
- `download_tokens` - Secure download links for tools
- `tool_files` - Files associated with tools

## Updated Delivery Flow

### New Flow
1. Order marked as paid
2. `createDeliveryRecords()` creates delivery entries
3. Delivery linked to customer_id
4. For tools: Download tokens generated with customer_id
5. For templates: Admin assigns credentials
6. **Customer can view from dashboard immediately**
7. Email still sent as notification
8. Customer can access anytime from `/user/order-detail.php`

## Database Changes

### deliveries table additions

```sql
-- Track customer access to deliveries
ALTER TABLE deliveries ADD COLUMN customer_viewed_at TEXT;
ALTER TABLE deliveries ADD COLUMN customer_download_count INTEGER DEFAULT 0;
```

### download_tokens table additions

```sql
-- Link tokens to customers for dashboard access
ALTER TABLE download_tokens ADD COLUMN customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL;
CREATE INDEX idx_download_tokens_customer ON download_tokens(customer_id);
```

## Updated Functions

### createDeliveryRecords() - Modified

```php
/**
 * Create delivery records for an order
 * UPDATED: Now includes customer_id linking
 */
function createDeliveryRecords($orderId) {
    $db = getDb();
    
    error_log("📦 Creating delivery records for Order #$orderId");
    
    // Get order with customer_id
    $orderStmt = $db->prepare("SELECT customer_id FROM pending_orders WHERE id = ?");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
    $customerId = $order['customer_id'] ?? null;
    
    // ... existing item fetching code ...
    
    foreach ($items as $item) {
        // Create delivery record (existing logic)
        // ...
        
        // For tools, create download tokens with customer_id
        if ($item['product_type'] === 'tool') {
            createToolDownloadTokens($orderId, $item['product_id'], $customerId);
        }
    }
}
```

### createToolDownloadTokens() - New Helper

```php
/**
 * Create download tokens for tool files
 * Links to both order and customer for dashboard access
 */
function createToolDownloadTokens($orderId, $toolId, $customerId = null) {
    $db = getDb();
    
    // Get tool files
    $files = getToolFiles($toolId);
    
    foreach ($files as $file) {
        // Check if token already exists
        $existing = $db->prepare("
            SELECT id FROM download_tokens 
            WHERE file_id = ? AND pending_order_id = ?
        ");
        $existing->execute([$file['id'], $orderId]);
        
        if (!$existing->fetch()) {
            // Generate new token
            $token = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));
            
            $stmt = $db->prepare("
                INSERT INTO download_tokens 
                (file_id, pending_order_id, customer_id, token, max_downloads, expires_at)
                VALUES (?, ?, ?, ?, 10, ?)
            ");
            $stmt->execute([$file['id'], $orderId, $customerId, $token, $expiresAt]);
            
            error_log("✅ Created download token for File #{$file['id']}, Order #$orderId");
        }
    }
}
```

### getCustomerDeliveries() - New Function

```php
/**
 * Get all deliveries for a customer
 * Used in user dashboard
 */
function getCustomerDeliveries($customerId, $status = null) {
    $db = getDb();
    
    $sql = "
        SELECT 
            d.*,
            po.id as order_id,
            po.created_at as order_date,
            po.status as order_status,
            COALESCE(t.name, tl.name) as product_name,
            COALESCE(t.thumbnail_url, tl.thumbnail_url) as product_thumbnail,
            oi.product_type,
            oi.price
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        JOIN order_items oi ON oi.pending_order_id = po.id 
                           AND oi.product_type = d.product_type 
                           AND oi.product_id = d.product_id
        LEFT JOIN templates t ON d.product_type = 'template' AND d.product_id = t.id
        LEFT JOIN tools tl ON d.product_type = 'tool' AND d.product_id = tl.id
        WHERE po.customer_id = ?
    ";
    $params = [$customerId];
    
    if ($status) {
        $sql .= " AND d.delivery_status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY po.created_at DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### getDeliveryForCustomer() - New Function

```php
/**
 * Get single delivery with security check
 */
function getDeliveryForCustomer($deliveryId, $customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_id
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ? AND po.customer_id = ?
    ");
    $stmt->execute([$deliveryId, $customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
```

## Template Credential Access

### Decrypt Credentials for Customer View

```php
/**
 * Get decrypted template credentials for customer
 * Only returns if delivery is complete
 */
function getTemplateCredentialsForCustomer($deliveryId, $customerId) {
    $db = getDb();
    
    // Verify ownership and delivery status
    $stmt = $db->prepare("
        SELECT d.*
        FROM deliveries d
        JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ? 
        AND po.customer_id = ?
        AND d.product_type = 'template'
        AND d.delivery_status = 'delivered'
    ");
    $stmt->execute([$deliveryId, $customerId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) {
        return null;
    }
    
    // Decrypt password
    $password = null;
    if (!empty($delivery['template_admin_password_encrypted']) && 
        !empty($delivery['template_admin_password_iv'])) {
        $password = decryptPassword(
            $delivery['template_admin_password_encrypted'],
            $delivery['template_admin_password_iv']
        );
    }
    
    // Track customer view
    $db->prepare("
        UPDATE deliveries 
        SET customer_viewed_at = COALESCE(customer_viewed_at, datetime('now'))
        WHERE id = ?
    ")->execute([$deliveryId]);
    
    // Log access
    logCustomerActivity($customerId, 'credential_view', "Viewed credentials for delivery #$deliveryId");
    
    return [
        'domain' => $delivery['hosted_domain'],
        'login_url' => $delivery['domain_login_url'],
        'username' => $delivery['template_admin_username'],
        'password' => $password,
        'hosting_type' => $delivery['hosting_type']
    ];
}
```

## Tool Download Access

### Customer Download Handler

```php
/**
 * Process download request from customer dashboard
 * Location: /user/download.php or /download.php (modified)
 */
function processCustomerDownload($token, $customerId = null) {
    $db = getDb();
    
    // Validate token
    $stmt = $db->prepare("
        SELECT dt.*, tf.file_path, tf.file_name, tf.file_type,
               po.customer_id as order_customer_id
        FROM download_tokens dt
        JOIN tool_files tf ON dt.file_id = tf.id
        JOIN pending_orders po ON dt.pending_order_id = po.id
        WHERE dt.token = ?
        AND dt.expires_at > datetime('now')
        AND dt.download_count < dt.max_downloads
        AND po.status = 'paid'
    ");
    $stmt->execute([$token]);
    $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$tokenData) {
        return ['success' => false, 'error' => 'Invalid or expired download link'];
    }
    
    // If customer ID provided, verify ownership
    if ($customerId && $tokenData['order_customer_id'] != $customerId) {
        return ['success' => false, 'error' => 'Access denied'];
    }
    
    // Increment download count
    $db->prepare("UPDATE download_tokens SET download_count = download_count + 1 WHERE id = ?")
       ->execute([$tokenData['id']]);
    
    // Update delivery record
    $db->prepare("
        UPDATE deliveries 
        SET customer_download_count = customer_download_count + 1
        WHERE pending_order_id = ? AND product_type = 'tool'
    ")->execute([$tokenData['pending_order_id']]);
    
    // Log activity
    if ($tokenData['order_customer_id']) {
        logCustomerActivity($tokenData['order_customer_id'], 'file_download', 
            "Downloaded file: {$tokenData['file_name']}");
    }
    
    return [
        'success' => true,
        'file_path' => $tokenData['file_path'],
        'file_name' => $tokenData['file_name'],
        'file_type' => $tokenData['file_type'],
        'remaining_downloads' => $tokenData['max_downloads'] - $tokenData['download_count'] - 1
    ];
}
```

### Regenerate Expired Token

```php
/**
 * Regenerate download token for customer
 * Used when original token expired
 */
function regenerateDownloadToken($oldTokenId, $customerId) {
    $db = getDb();
    
    // Get old token and verify ownership
    $stmt = $db->prepare("
        SELECT dt.*, po.customer_id
        FROM download_tokens dt
        JOIN pending_orders po ON dt.pending_order_id = po.id
        WHERE dt.id = ? AND po.customer_id = ?
    ");
    $stmt->execute([$oldTokenId, $customerId]);
    $oldToken = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldToken) {
        return ['success' => false, 'error' => 'Token not found'];
    }
    
    // Generate new token
    $newToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Insert new token
    $stmt = $db->prepare("
        INSERT INTO download_tokens 
        (file_id, pending_order_id, customer_id, token, max_downloads, expires_at)
        VALUES (?, ?, ?, ?, 5, ?)
    ");
    $stmt->execute([
        $oldToken['file_id'],
        $oldToken['pending_order_id'],
        $customerId,
        $newToken,
        $expiresAt
    ]);
    
    logCustomerActivity($customerId, 'token_regenerated', 
        "Regenerated download token for file #{$oldToken['file_id']}");
    
    return [
        'success' => true,
        'token' => $newToken,
        'expires_at' => $expiresAt
    ];
}
```

## Delivery Timeline Display

### Get Order Timeline

```php
/**
 * Build delivery timeline for order detail page
 */
function getOrderTimeline($orderId, $customerId) {
    $db = getDb();
    
    // Verify ownership
    $orderCheck = $db->prepare("SELECT id FROM pending_orders WHERE id = ? AND customer_id = ?");
    $orderCheck->execute([$orderId, $customerId]);
    if (!$orderCheck->fetch()) {
        return [];
    }
    
    $timeline = [];
    
    // Get order events
    $stmt = $db->prepare("
        SELECT 
            'order_created' as event,
            created_at as timestamp,
            'Order placed' as description
        FROM pending_orders WHERE id = ?
        
        UNION ALL
        
        SELECT 
            'payment_confirmed' as event,
            payment_verified_at as timestamp,
            'Payment confirmed' as description
        FROM pending_orders WHERE id = ? AND payment_verified_at IS NOT NULL
        
        UNION ALL
        
        SELECT 
            'delivery_' || delivery_status as event,
            COALESCE(delivered_at, updated_at) as timestamp,
            CASE delivery_status
                WHEN 'pending' THEN 'Delivery processing'
                WHEN 'ready' THEN 'Ready for delivery'
                WHEN 'sent' THEN 'Delivery sent'
                WHEN 'delivered' THEN product_name || ' delivered'
                ELSE 'Delivery update'
            END as description
        FROM deliveries WHERE pending_order_id = ?
        
        ORDER BY timestamp ASC
    ");
    
    // Execute with same orderId for all unions
    $stmt->execute([$orderId, $orderId, $orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

## Email Updates

### Modified Delivery Emails

Delivery emails now include a link to the customer dashboard:

```php
// In sendToolDeliveryEmail():
$dashboardLink = SITE_URL . '/user/order-detail.php?id=' . $orderId;

$content .= '<p style="margin-top: 15px;">';
$content .= 'You can also access your downloads anytime from your ';
$content .= '<a href="' . $dashboardLink . '" style="color: #3b82f6;">account dashboard</a>.';
$content .= '</p>';
```

## Admin Visibility

Admin can see customer access stats:

```php
// In admin/orders.php order detail view
$delivery = getDeliveryWithCustomerStats($deliveryId);
// Shows: customer_viewed_at, customer_download_count
```

## Testing Checklist

- [ ] Deliveries linked to customer_id
- [ ] Customer can view delivery status from dashboard
- [ ] Template credentials shown only when delivered
- [ ] Download tokens linked to customer
- [ ] Customer can download from dashboard
- [ ] Expired tokens can be regenerated
- [ ] Download counts tracked per customer
- [ ] Timeline shows all events
- [ ] Emails include dashboard links
- [ ] Admin can see customer access stats
