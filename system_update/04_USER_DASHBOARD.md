# User Dashboard System

## Overview

This document details the new `/user/` folder structure for the customer dashboard, including all pages, components, and functionality.

## Folder Structure

```
/user/
├── index.php              # Dashboard home (order overview)
├── orders.php             # All orders list with filters
├── order-detail.php       # Single order detail + deliveries
├── downloads.php          # All available downloads
├── support.php            # Support tickets list
├── ticket.php             # Single ticket view + replies
├── new-ticket.php         # Create new support ticket
├── profile.php            # Profile settings
├── security.php           # Password + sessions management
├── login.php              # Customer login page
├── logout.php             # Logout handler
├── forgot-password.php    # Password reset request
├── reset-password.php     # Password reset form
├── verify-email.php       # Email verification landing
└── includes/
    ├── auth.php           # Customer auth middleware
    ├── header.php         # Dashboard header with nav
    ├── footer.php         # Dashboard footer
    └── sidebar.php        # Mobile sidebar navigation
```

## Authentication Middleware

### /user/includes/auth.php

```php
<?php
/**
 * Customer Authentication Middleware
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';

/**
 * Require customer to be authenticated
 * Redirects to login if not authenticated
 */
function requireCustomer() {
    $customer = validateCustomerSession();
    
    if (!$customer) {
        // Store intended destination
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /user/login.php');
        exit;
    }
    
    return $customer;
}

/**
 * Get current customer info
 * Returns null if not authenticated (no redirect)
 */
function getCurrentCustomer() {
    return validateCustomerSession();
}

/**
 * Get customer ID or null
 */
function getCustomerId() {
    $customer = getCurrentCustomer();
    return $customer ? $customer['id'] : null;
}

/**
 * Get customer name for display
 */
function getCustomerName() {
    $customer = getCurrentCustomer();
    if (!$customer) return 'Guest';
    return $customer['full_name'] ?: $customer['username'] ?: explode('@', $customer['email'])[0];
}

/**
 * Get customer email
 */
function getCustomerEmail() {
    $customer = getCurrentCustomer();
    return $customer ? $customer['email'] : null;
}
```

## Page Specifications

### 1. Dashboard Home (/user/index.php)

**Purpose:** Overview of customer account with quick stats and recent activity.

**Features:**
- Welcome message with customer name
- Quick stats: Total orders, Active deliveries, Open tickets
- Recent orders (last 5)
- Pending actions (incomplete profile, unpaid orders)
- Quick links to common actions

**Data Required:**
```php
$customer = requireCustomer();

// Stats
$orderCount = getCustomerOrderCount($customer['id']);
$pendingDeliveries = getCustomerPendingDeliveries($customer['id']);
$openTickets = getCustomerOpenTickets($customer['id']);

// Recent orders
$recentOrders = getCustomerOrders($customer['id'], 5);

// Profile completeness
$profileComplete = !empty($customer['full_name']) && !empty($customer['phone']);
```

### 2. Orders List (/user/orders.php)

**Purpose:** List all customer orders with filtering and status tracking.

**Features:**
- Order cards with status badges
- Filter by: All, Pending, Paid, Completed
- Sort by: Date (newest/oldest)
- Pagination
- "Confirmed" banner for new orders
- Quick action buttons (View, Download, Support)

**URL Parameters:**
- `?status=pending|paid|completed|all`
- `?confirmed=ORDER_ID` - Highlight just-completed order
- `?page=N` - Pagination

**Data Required:**
```php
$orders = getCustomerOrders($customerId, $perPage, $offset, $statusFilter);
$totalOrders = getCustomerOrderCount($customerId, $statusFilter);
```

### 3. Order Detail (/user/order-detail.php)

**Purpose:** Full details of a single order including items and deliveries.

**Features:**
- Order summary (ID, date, status, amount)
- Payment status and method
- List of items purchased
- Delivery status per item
- Template credentials (if template order)
- Download links (if tool order)
- Support ticket link for this order
- Order timeline (placed → paid → delivered)

**URL Parameters:**
- `?id=ORDER_ID` - Required

**Security:**
```php
$order = getOrderForCustomer($orderId, $customerId);
if (!$order) {
    header('Location: /user/orders.php');
    exit;
}
```

**Credential Access:**
```php
// Template credentials are decrypted on-demand
if ($delivery['product_type'] === 'template' && $delivery['delivery_status'] === 'delivered') {
    $credentials = decryptTemplateCredentials($delivery);
    // Show: domain, login URL, username, password
}
```

### 4. Downloads (/user/downloads.php)

**Purpose:** Centralized access to all downloadable products.

**Features:**
- List all tools purchased
- Download buttons with remaining count
- Expiry dates
- Bundle download option (ZIP all files)
- Re-generate link option (if expired)

**Data Required:**
```php
$downloads = getCustomerDownloads($customerId);
// Returns: tool name, files, download counts, expiry dates
```

### 5. Support Tickets (/user/support.php)

**Purpose:** List and manage support tickets.

**Features:**
- Ticket list with status badges
- Filter by: All, Open, Awaiting Reply, Resolved
- New ticket button
- Unread reply indicators

**Data Required:**
```php
$tickets = getCustomerTickets($customerId, $statusFilter);
```

### 6. Ticket Detail (/user/ticket.php)

**Purpose:** View and reply to a support ticket.

**Features:**
- Ticket details (subject, category, status)
- Linked order (if applicable)
- Conversation thread
- Reply form with file upload
- Close ticket button

**URL Parameters:**
- `?id=TICKET_ID` - Required

### 7. New Ticket (/user/new-ticket.php)

**Purpose:** Create a new support ticket.

**Features:**
- Subject input
- Category dropdown (Order, Delivery, Refund, Technical, Account, General)
- Optional: Link to order dropdown
- Message textarea
- File upload (optional)

**Form Processing:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketId = createCustomerTicket([
        'customer_id' => $customerId,
        'subject' => $_POST['subject'],
        'category' => $_POST['category'],
        'order_id' => $_POST['order_id'] ?: null,
        'message' => $_POST['message'],
        'attachments' => handleTicketAttachments($_FILES)
    ]);
    
    // Notify admin
    sendNewCustomerTicketNotification($ticketId);
    
    header("Location: /user/ticket.php?id=$ticketId&created=1");
    exit;
}
```

### 8. Profile (/user/profile.php)

**Purpose:** Manage customer profile information.

**Features:**
- Edit: Full name, Username, Phone
- View: Email (non-editable), Account created date
- Avatar upload (optional)
- Save changes

**Form Processing:**
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    updateCustomerProfile($customerId, [
        'full_name' => $_POST['full_name'],
        'username' => $_POST['username'],
        'phone' => $_POST['phone']
    ]);
    $success = 'Profile updated successfully';
}
```

### 9. Security (/user/security.php)

**Purpose:** Password and session management.

**Features:**
- Change password (current + new + confirm)
- Set password (for OTP-only accounts)
- Active sessions list with devices
- Revoke session button
- Logout all devices button

**Sessions List:**
```php
$sessions = getCustomerSessions($customerId);
// Shows: Device name, IP, Last active, Current indicator
```

## Helper Functions

### Customer Orders

```php
/**
 * Get customer orders with optional filtering
 */
function getCustomerOrders($customerId, $limit = 20, $offset = 0, $status = null) {
    $db = getDb();
    
    $sql = "
        SELECT po.*, 
               (SELECT COUNT(*) FROM order_items WHERE pending_order_id = po.id) as item_count,
               (SELECT COUNT(*) FROM deliveries WHERE pending_order_id = po.id AND delivery_status = 'delivered') as delivered_count
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

/**
 * Get single order with full details for customer
 */
function getOrderForCustomer($orderId, $customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT po.*, 
               GROUP_CONCAT(DISTINCT oi.product_type) as product_types
        FROM pending_orders po
        LEFT JOIN order_items oi ON oi.pending_order_id = po.id
        WHERE po.id = ? AND po.customer_id = ?
        GROUP BY po.id
    ");
    $stmt->execute([$orderId, $customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get order items with delivery status
 */
function getOrderItemsWithDelivery($orderId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT 
            oi.*,
            COALESCE(t.name, tl.name) as product_name,
            COALESCE(t.thumbnail_url, tl.thumbnail_url) as product_thumbnail,
            d.id as delivery_id,
            d.delivery_status,
            d.delivered_at,
            d.hosted_domain,
            d.domain_login_url,
            d.download_link
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
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### Customer Downloads

```php
/**
 * Get all customer downloads
 */
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
        ORDER BY po.created_at DESC, t.name
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

### Customer Tickets

```php
/**
 * Create customer support ticket
 */
function createCustomerTicket($data) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO customer_support_tickets 
        (customer_id, order_id, subject, message, category, attachments)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $data['customer_id'],
        $data['order_id'],
        $data['subject'],
        $data['message'],
        $data['category'],
        $data['attachments'] ? json_encode($data['attachments']) : null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Add reply to ticket
 */
function addTicketReply($ticketId, $customerId, $message, $attachments = null) {
    $db = getDb();
    
    // Verify ticket belongs to customer
    $check = $db->prepare("SELECT id FROM customer_support_tickets WHERE id = ? AND customer_id = ?");
    $check->execute([$ticketId, $customerId]);
    if (!$check->fetch()) {
        return false;
    }
    
    $stmt = $db->prepare("
        INSERT INTO customer_ticket_replies 
        (ticket_id, author_type, author_id, message, attachments)
        VALUES (?, 'customer', ?, ?, ?)
    ");
    $stmt->execute([
        $ticketId,
        $customerId,
        $message,
        $attachments ? json_encode($attachments) : null
    ]);
    
    // Update ticket
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
```

## Navigation Structure

```php
$navItems = [
    ['url' => '/user/', 'icon' => 'bi-speedometer2', 'label' => 'Dashboard', 'active' => $page === 'dashboard'],
    ['url' => '/user/orders.php', 'icon' => 'bi-bag', 'label' => 'My Orders', 'active' => $page === 'orders', 'badge' => $pendingOrders],
    ['url' => '/user/downloads.php', 'icon' => 'bi-download', 'label' => 'Downloads', 'active' => $page === 'downloads'],
    ['url' => '/user/support.php', 'icon' => 'bi-chat-dots', 'label' => 'Support', 'active' => $page === 'support', 'badge' => $openTickets],
    ['url' => '/user/profile.php', 'icon' => 'bi-person', 'label' => 'Profile', 'active' => $page === 'profile'],
    ['url' => '/user/security.php', 'icon' => 'bi-shield-lock', 'label' => 'Security', 'active' => $page === 'security'],
];
```

## UI Design Notes

- Use same Tailwind CSS + Alpine.js as admin/affiliate panels
- Consistent color scheme with main site
- Mobile-responsive with collapsible sidebar
- Status badges: 
  - Pending = Yellow
  - Paid = Blue  
  - Delivered = Green
  - Failed = Red
- Empty states with helpful messages
- Loading states for async operations
