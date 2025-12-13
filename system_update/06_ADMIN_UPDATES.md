# Admin Panel Updates

## Overview

This document details modifications to the admin panel to support customer account management, customer-order linking, and enhanced analytics.

## New Admin Pages

### 1. Customer Management (/admin/customers.php)

**Purpose:** List and manage all customer accounts.

**Features:**
- Customer list with search and filters
- Columns: Email, Name, Phone, Orders, Total Spent, Status, Joined
- Actions: View, Suspend, Activate
- Export to CSV
- Pagination

**Data Query:**
```php
$customers = $db->query("
    SELECT 
        c.*,
        COUNT(DISTINCT po.id) as order_count,
        COALESCE(SUM(CASE WHEN po.status = 'paid' THEN po.final_amount ELSE 0 END), 0) as total_spent,
        MAX(po.created_at) as last_order_date
    FROM customers c
    LEFT JOIN pending_orders po ON po.customer_id = c.id
    GROUP BY c.id
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
")->fetchAll();
```

**Filters:**
- Status: All, Active, Inactive, Suspended, Unverified
- Date range: Joined date
- Search: Email, name, phone

### 2. Customer Detail (/admin/customer-detail.php)

**Purpose:** View complete customer profile and history.

**Features:**
- Profile information
- Order history with links
- Support tickets
- Activity log
- Session management
- Quick actions (send email, create order, suspend)

**URL Parameter:** `?id=CUSTOMER_ID`

**Data Sections:**
```php
// Customer profile
$customer = getCustomerById($customerId);

// Orders
$orders = getCustomerOrders($customerId, 20);

// Tickets
$tickets = getCustomerTickets($customerId, 10);

// Recent activity
$activity = getCustomerActivity($customerId, 20);

// Active sessions
$sessions = getCustomerSessions($customerId);
```

### 3. Customer Tickets (/admin/customer-tickets.php)

**Purpose:** Manage all customer support tickets.

**Features:**
- Ticket list with status badges
- Filter by status, priority, category
- Assign to admin
- Quick reply
- Bulk actions

**Different from Affiliate Tickets:**
- Uses `customer_support_tickets` table
- Links to customers instead of affiliates
- Can link to orders

## Modified Admin Pages

### 1. Orders Page (/admin/orders.php)

**Additions:**
- Customer column with link
- Customer filter dropdown
- "View Customer" button in order detail

**Modified Query:**
```php
// Add customer join
$sql = "
    SELECT 
        po.*,
        c.email as customer_account_email,
        c.full_name as customer_account_name,
        c.id as customer_account_id
    FROM pending_orders po
    LEFT JOIN customers c ON po.customer_id = c.id
    WHERE 1=1
";

// Add customer filter
if (!empty($_GET['customer_id'])) {
    $sql .= " AND po.customer_id = ?";
    $params[] = $_GET['customer_id'];
}
```

**Order Detail Modal Updates:**
```html
<!-- Customer Account Section -->
<?php if ($order['customer_account_id']): ?>
<div class="bg-blue-50 p-4 rounded-lg mb-4">
    <h6 class="font-semibold text-blue-800 mb-2">
        <i class="bi bi-person-badge"></i> Customer Account
    </h6>
    <p><strong>Name:</strong> <?= htmlspecialchars($order['customer_account_name'] ?? 'Not set') ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($order['customer_account_email']) ?></p>
    <a href="/admin/customer-detail.php?id=<?= $order['customer_account_id'] ?>" 
       class="text-blue-600 underline">View Customer Profile</a>
</div>
<?php endif; ?>
```

### 2. Dashboard (/admin/index.php)

**New Stats Cards:**
```php
// Add customer stats
$totalCustomers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$newCustomersToday = $db->query("
    SELECT COUNT(*) FROM customers 
    WHERE DATE(created_at) = DATE('now')
")->fetchColumn();
$customersWithOrders = $db->query("
    SELECT COUNT(DISTINCT customer_id) FROM pending_orders 
    WHERE customer_id IS NOT NULL AND status = 'paid'
")->fetchColumn();
```

**New Dashboard Section:**
```html
<!-- Customer Overview -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6">
    <h5 class="text-lg font-bold mb-4">
        <i class="bi bi-people text-primary-600"></i> Customer Accounts
    </h5>
    <div class="grid grid-cols-3 gap-4">
        <div>
            <div class="text-2xl font-bold"><?= number_format($totalCustomers) ?></div>
            <div class="text-gray-500 text-sm">Total Customers</div>
        </div>
        <div>
            <div class="text-2xl font-bold text-green-600">+<?= $newCustomersToday ?></div>
            <div class="text-gray-500 text-sm">New Today</div>
        </div>
        <div>
            <div class="text-2xl font-bold"><?= number_format($customersWithOrders) ?></div>
            <div class="text-gray-500 text-sm">Paying Customers</div>
        </div>
    </div>
</div>
```

### 3. Reports (/admin/reports.php)

**New Customer Analytics:**
```php
// Customer acquisition by month
$customersByMonth = $db->query("
    SELECT 
        strftime('%Y-%m', created_at) as month,
        COUNT(*) as new_customers
    FROM customers
    GROUP BY strftime('%Y-%m', created_at)
    ORDER BY month DESC
    LIMIT 12
")->fetchAll();

// Customer lifetime value
$customerLTV = $db->query("
    SELECT 
        c.id,
        c.email,
        COUNT(po.id) as order_count,
        SUM(po.final_amount) as total_spent,
        MIN(po.created_at) as first_order,
        MAX(po.created_at) as last_order
    FROM customers c
    JOIN pending_orders po ON po.customer_id = c.id
    WHERE po.status = 'paid'
    GROUP BY c.id
    ORDER BY total_spent DESC
    LIMIT 20
")->fetchAll();

// Repeat customer rate
$repeatCustomers = $db->query("
    SELECT COUNT(*) FROM (
        SELECT customer_id, COUNT(*) as orders
        FROM pending_orders
        WHERE customer_id IS NOT NULL AND status = 'paid'
        GROUP BY customer_id
        HAVING orders > 1
    )
")->fetchColumn();
```

### 4. Navigation (/admin/includes/header.php)

**Add Customer Section:**
```php
$navItems = [
    // ... existing items
    [
        'label' => 'Customers',
        'icon' => 'bi-people',
        'items' => [
            ['url' => '/admin/customers.php', 'label' => 'All Customers'],
            ['url' => '/admin/customer-tickets.php', 'label' => 'Support Tickets', 'badge' => $openCustomerTickets],
        ]
    ],
    // ... rest of items
];
```

## Admin Helper Functions

### Customer Management

```php
/**
 * Get customer by ID with stats
 */
function getCustomerById($customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT 
            c.*,
            COUNT(DISTINCT po.id) as total_orders,
            SUM(CASE WHEN po.status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
            COALESCE(SUM(CASE WHEN po.status = 'paid' THEN po.final_amount ELSE 0 END), 0) as total_spent,
            (SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = c.id) as ticket_count
        FROM customers c
        LEFT JOIN pending_orders po ON po.customer_id = c.id
        WHERE c.id = ?
        GROUP BY c.id
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update customer status
 */
function updateCustomerStatus($customerId, $status, $adminId = null) {
    $db = getDb();
    
    $stmt = $db->prepare("UPDATE customers SET status = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$status, $customerId]);
    
    // Revoke all sessions if suspended
    if ($status === 'suspended') {
        $db->prepare("
            UPDATE customer_sessions 
            SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'account_suspended'
            WHERE customer_id = ?
        ")->execute([$customerId]);
    }
    
    // Log activity
    logActivity('customer_status_changed', "Customer #$customerId status changed to $status", $adminId);
    
    return true;
}

/**
 * Get customer activity log
 */
function getCustomerActivity($customerId, $limit = 50) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT * FROM customer_activity_log 
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$customerId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Manually create customer account (admin action)
 */
function adminCreateCustomer($data, $adminId) {
    $db = getDb();
    
    // Check if email exists
    $check = $db->prepare("SELECT id FROM customers WHERE email = ?");
    $check->execute([$data['email']]);
    if ($check->fetch()) {
        return ['success' => false, 'error' => 'Email already exists'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO customers (email, phone, full_name, status, email_verified, created_at)
        VALUES (?, ?, ?, 'active', 1, datetime('now'))
    ");
    $stmt->execute([$data['email'], $data['phone'] ?? null, $data['full_name'] ?? null]);
    
    $customerId = $db->lastInsertId();
    
    logActivity('customer_created', "Admin created customer #$customerId", $adminId);
    
    return ['success' => true, 'customer_id' => $customerId];
}
```

### Ticket Management

```php
/**
 * Get customer tickets for admin
 */
function getCustomerTicketsForAdmin($filters = [], $limit = 20, $offset = 0) {
    $db = getDb();
    
    $sql = "
        SELECT 
            t.*,
            c.email as customer_email,
            c.full_name as customer_name,
            po.id as linked_order_id,
            u.name as assigned_admin_name
        FROM customer_support_tickets t
        JOIN customers c ON t.customer_id = c.id
        LEFT JOIN pending_orders po ON t.order_id = po.id
        LEFT JOIN users u ON t.assigned_admin_id = u.id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($filters['status'])) {
        $sql .= " AND t.status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['priority'])) {
        $sql .= " AND t.priority = ?";
        $params[] = $filters['priority'];
    }
    
    if (!empty($filters['category'])) {
        $sql .= " AND t.category = ?";
        $params[] = $filters['category'];
    }
    
    $sql .= " ORDER BY 
        CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END,
        t.created_at DESC
        LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Reply to customer ticket (admin)
 */
function adminReplyToTicket($ticketId, $adminId, $message, $isInternal = false) {
    $db = getDb();
    
    // Get admin name
    $admin = $db->prepare("SELECT name FROM users WHERE id = ?")->execute([$adminId])->fetch();
    
    $stmt = $db->prepare("
        INSERT INTO customer_ticket_replies 
        (ticket_id, author_type, author_id, author_name, message, is_internal)
        VALUES (?, 'admin', ?, ?, ?, ?)
    ");
    $stmt->execute([$ticketId, $adminId, $admin['name'], $message, $isInternal ? 1 : 0]);
    
    // Update ticket status
    $newStatus = $isInternal ? null : 'awaiting_reply';
    if ($newStatus) {
        $db->prepare("
            UPDATE customer_support_tickets 
            SET status = ?, 
                last_reply_at = datetime('now'), 
                last_reply_by = 'admin',
                updated_at = datetime('now')
            WHERE id = ?
        ")->execute([$newStatus, $ticketId]);
    }
    
    // Send email notification to customer (unless internal)
    if (!$isInternal) {
        notifyCustomerOfTicketReply($ticketId);
    }
    
    return true;
}
```

## Linking Historical Orders

Admin can manually link orders to customers:

```php
/**
 * Link existing order to customer account
 */
function linkOrderToCustomer($orderId, $customerId, $adminId) {
    $db = getDb();
    
    // Verify customer exists
    $customer = $db->prepare("SELECT id, email FROM customers WHERE id = ?")->execute([$customerId])->fetch();
    if (!$customer) {
        return ['success' => false, 'error' => 'Customer not found'];
    }
    
    // Update order
    $stmt = $db->prepare("UPDATE pending_orders SET customer_id = ?, updated_at = datetime('now') WHERE id = ?");
    $stmt->execute([$customerId, $orderId]);
    
    // Update related records
    $db->prepare("UPDATE sales SET customer_id = ? WHERE pending_order_id = ?")->execute([$customerId, $orderId]);
    $db->prepare("UPDATE download_tokens SET customer_id = ? WHERE pending_order_id = ?")->execute([$customerId, $orderId]);
    
    logActivity('order_linked', "Order #$orderId linked to customer #$customerId", $adminId);
    
    return ['success' => true];
}
```

---

## Admin Generate OTP for User Verification

This feature allows admin to generate a 6-digit OTP code for a specific user. The OTP appears as a **notification on the user's dashboard** (visible for about 5 minutes). This is used for identity verification when a user contacts you via WhatsApp for manual payment issues.

### Use Case
1. User contacts admin via WhatsApp claiming they have an account issue
2. Admin goes to User Management and clicks "Generate OTP" for that user
3. A 6-digit code is created and displayed as a notification on the user's dashboard
4. User sees the notification, copies the code, and sends it to admin via WhatsApp
5. Admin verifies the code matches - confirming the user's identity
6. Admin can now safely help with the issue or send credentials

### Admin UI - Generate OTP Button

In the customer detail page (`/admin/customer-detail.php`), add a "Generate Verification OTP" button:

```html
<!-- Admin Generate OTP Section -->
<div class="bg-white rounded-xl shadow-md p-6 mb-6">
    <h5 class="text-lg font-bold mb-4">
        <i class="bi bi-shield-check text-warning"></i> Identity Verification
    </h5>
    <p class="text-gray-600 text-sm mb-4">
        Generate a one-time code that will appear on the user's dashboard. 
        Ask them to provide this code to verify their identity.
    </p>
    
    <button 
        onclick="generateUserOTP(<?= $customer['id'] ?>)" 
        class="bg-amber-500 hover:bg-amber-600 text-white px-4 py-2 rounded-lg font-medium transition"
        id="generate-otp-btn"
    >
        <i class="bi bi-key"></i> Generate Verification OTP
    </button>
    
    <!-- Result display -->
    <div id="otp-result" class="mt-4 hidden">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
            <p class="text-sm text-green-800 mb-2">OTP sent to user's dashboard:</p>
            <div class="text-3xl font-mono font-bold text-green-700 tracking-widest" id="generated-otp"></div>
            <p class="text-xs text-green-600 mt-2">Expires in 5 minutes. User will see this on their dashboard.</p>
        </div>
    </div>
</div>

<script>
async function generateUserOTP(customerId) {
    const btn = document.getElementById('generate-otp-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Generating...';
    
    try {
        const response = await fetch('/admin/api/generate-user-otp.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ customer_id: customerId })
        });
        
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('generated-otp').textContent = data.otp_code;
            document.getElementById('otp-result').classList.remove('hidden');
            btn.innerHTML = '<i class="bi bi-check"></i> OTP Generated';
            btn.classList.remove('bg-amber-500', 'hover:bg-amber-600');
            btn.classList.add('bg-green-500');
            
            // Re-enable after 30 seconds
            setTimeout(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-key"></i> Generate New OTP';
                btn.classList.remove('bg-green-500');
                btn.classList.add('bg-amber-500', 'hover:bg-amber-600');
            }, 30000);
        } else {
            alert('Error: ' + data.error);
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-key"></i> Generate Verification OTP';
        }
    } catch (err) {
        alert('Failed to generate OTP');
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-key"></i> Generate Verification OTP';
    }
}
</script>
```

### Backend Function - Generate Admin OTP

```php
/**
 * Admin generates OTP for user identity verification
 * This OTP is displayed as a notification on the user's dashboard
 * 
 * @param int $customerId Customer ID
 * @param int $adminId Admin who generated the OTP
 * @return array Result with OTP code
 */
function adminGenerateVerificationOTP($customerId, $adminId) {
    $db = getDb();
    
    // Verify customer exists
    $customer = getCustomerById($customerId);
    if (!$customer) {
        return ['success' => false, 'error' => 'Customer not found'];
    }
    
    // Rate limit: max 5 admin OTPs per customer per hour
    $rateCheck = $db->prepare("
        SELECT COUNT(*) FROM admin_verification_otps 
        WHERE customer_id = ? 
        AND created_at > datetime('now', '-1 hour')
    ");
    $rateCheck->execute([$customerId]);
    if ($rateCheck->fetchColumn() >= 5) {
        return ['success' => false, 'error' => 'Too many OTPs generated. Please wait.'];
    }
    
    // Invalidate any previous OTPs for this customer
    $db->prepare("
        UPDATE admin_verification_otps 
        SET is_used = 1 
        WHERE customer_id = ? AND is_used = 0
    ")->execute([$customerId]);
    
    // Generate 6-digit OTP
    $otpCode = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Set expiry (5 minutes)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Insert OTP record
    $stmt = $db->prepare("
        INSERT INTO admin_verification_otps 
        (customer_id, admin_id, otp_code, expires_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$customerId, $adminId, $otpCode, $expiresAt]);
    $otpId = $db->lastInsertId();
    
    // Create dashboard notification for the user
    createCustomerNotification($customerId, [
        'type' => 'admin_verification_otp',
        'title' => 'Verification Code',
        'message' => 'Your identity verification code is: ' . $otpCode,
        'otp_code' => $otpCode,
        'expires_at' => $expiresAt,
        'priority' => 'high',
        'auto_dismiss' => true,
        'dismiss_after_seconds' => 300 // 5 minutes
    ]);
    
    // Log activity
    logActivity('admin_otp_generated', "Admin generated verification OTP for customer #$customerId", $adminId);
    logCustomerActivity($customerId, 'verification_otp_received', 'Admin generated verification OTP');
    
    return [
        'success' => true,
        'otp_code' => $otpCode,
        'expires_at' => $expiresAt,
        'message' => 'OTP sent to user dashboard'
    ];
}
```

### Admin API Endpoint

Create `/admin/api/generate-user-otp.php`:

```php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Require admin auth
$admin = requireAdmin();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$customerId = $input['customer_id'] ?? null;

if (!$customerId) {
    echo json_encode(['success' => false, 'error' => 'Customer ID required']);
    exit;
}

// Generate OTP
$result = adminGenerateVerificationOTP($customerId, $admin['id']);

echo json_encode($result);
```

### Database Schema for Admin OTPs

```sql
CREATE TABLE IF NOT EXISTS admin_verification_otps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL,
    admin_id INTEGER NOT NULL,
    otp_code VARCHAR(6) NOT NULL,
    is_used INTEGER DEFAULT 0,
    used_at DATETIME,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
);

CREATE INDEX idx_admin_otp_customer ON admin_verification_otps(customer_id);
CREATE INDEX idx_admin_otp_code ON admin_verification_otps(otp_code);
```

---

## Testing Checklist

- [ ] Customer list page loads with all data
- [ ] Customer detail page shows complete info
- [ ] Customer search and filters work
- [ ] Suspend/activate customer works
- [ ] Sessions revoked on suspend
- [ ] Orders page shows customer links
- [ ] Dashboard shows customer stats
- [ ] Customer tickets management works
- [ ] Admin can reply to tickets
- [ ] Manual order linking works
- [ ] Reports show customer analytics
- [ ] **Admin Generate OTP button appears on customer detail page**
- [ ] **Admin can generate verification OTP for any customer**
- [ ] **OTP appears as notification on user's dashboard**
- [ ] **OTP notification expires after 5 minutes**
- [ ] **Rate limiting works (max 5 OTPs per customer per hour)**
