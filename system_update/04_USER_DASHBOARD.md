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

---

## Customer Notification System

The dashboard includes a notification system for important alerts, including:
- Admin-generated verification OTPs
- Template delivery notifications
- Order status updates
- Support ticket replies

### Notification Database Schema

```sql
CREATE TABLE IF NOT EXISTS customer_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    type VARCHAR(50) NOT NULL,  -- admin_verification_otp, template_delivered, order_update, ticket_reply
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,  -- Additional data (otp_code, order_id, etc.)
    priority VARCHAR(20) DEFAULT 'normal',  -- low, normal, high
    is_read INTEGER DEFAULT 0,
    read_at DATETIME,
    auto_dismiss INTEGER DEFAULT 0,
    dismiss_after_seconds INTEGER,
    expires_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id)
);

CREATE INDEX idx_notifications_customer ON customer_notifications(customer_id);
CREATE INDEX idx_notifications_unread ON customer_notifications(customer_id, is_read);
```

### Notification Functions

```php
/**
 * Create a notification for a customer
 */
function createCustomerNotification($customerId, $data) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO customer_notifications 
        (customer_id, type, title, message, data, priority, auto_dismiss, dismiss_after_seconds, expires_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $customerId,
        $data['type'],
        $data['title'],
        $data['message'],
        isset($data['otp_code']) ? json_encode(['otp_code' => $data['otp_code']]) : null,
        $data['priority'] ?? 'normal',
        $data['auto_dismiss'] ?? 0,
        $data['dismiss_after_seconds'] ?? null,
        $data['expires_at'] ?? null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Get unread notifications for customer (with expiry check)
 */
function getCustomerNotifications($customerId, $unreadOnly = true) {
    $db = getDb();
    
    $sql = "
        SELECT * FROM customer_notifications 
        WHERE customer_id = ?
        AND (expires_at IS NULL OR expires_at > datetime('now'))
    ";
    
    if ($unreadOnly) {
        $sql .= " AND is_read = 0";
    }
    
    $sql .= " ORDER BY priority DESC, created_at DESC LIMIT 20";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$customerId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Mark notification as read
 */
function markNotificationRead($notificationId, $customerId) {
    $db = getDb();
    $db->prepare("
        UPDATE customer_notifications 
        SET is_read = 1, read_at = datetime('now')
        WHERE id = ? AND customer_id = ?
    ")->execute([$notificationId, $customerId]);
}
```

### Notification Display Component (Dashboard Header)

```html
<!-- Notification Bell in Header -->
<div class="relative" x-data="notificationBell()">
    <button @click="toggle()" class="relative p-2 text-gray-600 hover:text-gray-900">
        <i class="bi bi-bell text-xl"></i>
        <span x-show="unreadCount > 0" 
              x-text="unreadCount" 
              class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
        </span>
    </button>
    
    <!-- Dropdown -->
    <div x-show="open" 
         @click.away="open = false"
         x-transition
         class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border z-50">
        <div class="p-4 border-b">
            <h3 class="font-bold">Notifications</h3>
        </div>
        
        <div class="max-h-96 overflow-y-auto">
            <template x-for="notification in notifications" :key="notification.id">
                <div class="p-4 border-b hover:bg-gray-50 cursor-pointer"
                     :class="{'bg-blue-50': notification.priority === 'high'}"
                     @click="markRead(notification.id)">
                    
                    <!-- High Priority OTP Notification -->
                    <template x-if="notification.type === 'admin_verification_otp'">
                        <div class="text-center">
                            <p class="text-sm font-semibold text-amber-600 mb-2" x-text="notification.title"></p>
                            <div class="bg-amber-100 rounded-lg p-3 mb-2">
                                <span class="text-3xl font-mono font-bold text-amber-800 tracking-widest"
                                      x-text="JSON.parse(notification.data).otp_code"></span>
                            </div>
                            <p class="text-xs text-gray-500">Share this code to verify your identity</p>
                            <p class="text-xs text-red-500 mt-1" x-show="notification.expires_at">
                                Expires soon - copy now!
                            </p>
                        </div>
                    </template>
                    
                    <!-- Template Delivered Notification -->
                    <template x-if="notification.type === 'template_delivered'">
                        <div>
                            <p class="text-sm font-semibold text-green-600" x-text="notification.title"></p>
                            <p class="text-sm text-gray-600 mt-1" x-text="notification.message"></p>
                            <a href="#" class="text-sm text-primary-600 underline mt-2 inline-block">View Credentials</a>
                        </div>
                    </template>
                    
                    <!-- Default Notification -->
                    <template x-if="!['admin_verification_otp', 'template_delivered'].includes(notification.type)">
                        <div>
                            <p class="text-sm font-semibold" x-text="notification.title"></p>
                            <p class="text-sm text-gray-600 mt-1" x-text="notification.message"></p>
                        </div>
                    </template>
                </div>
            </template>
            
            <div x-show="notifications.length === 0" class="p-8 text-center text-gray-500">
                No new notifications
            </div>
        </div>
    </div>
</div>

<script>
function notificationBell() {
    return {
        open: false,
        notifications: [],
        unreadCount: 0,
        
        init() {
            this.loadNotifications();
            // Poll every 30 seconds for new notifications
            setInterval(() => this.loadNotifications(), 30000);
        },
        
        toggle() {
            this.open = !this.open;
            if (this.open) this.loadNotifications();
        },
        
        async loadNotifications() {
            try {
                const response = await fetch('/api/customer/notifications.php');
                const data = await response.json();
                this.notifications = data.notifications;
                this.unreadCount = data.unread_count;
            } catch (err) {
                console.error('Failed to load notifications');
            }
        },
        
        async markRead(id) {
            try {
                await fetch('/api/customer/notifications.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'mark_read', id })
                });
                this.loadNotifications();
            } catch (err) {
                console.error('Failed to mark notification read');
            }
        }
    };
}
</script>
```

---

## Testing Checklist

- [ ] Dashboard home loads with stats
- [ ] Orders page shows all customer orders
- [ ] Order detail shows items and deliveries
- [ ] Template credentials display correctly in dashboard
- [ ] Downloads page shows available files
- [ ] Support tickets can be created and replied to
- [ ] Profile page allows editing
- [ ] Security page shows sessions and allows password change
- [ ] **Notification bell shows unread count**
- [ ] **Admin-generated OTP appears as high-priority notification**
- [ ] **OTP notification expires after 5 minutes**
- [ ] **Template delivery notification appears when template is delivered**
