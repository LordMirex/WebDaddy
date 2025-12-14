# Admin Automation System

## Overview

This document outlines automation features to reduce admin workload while ensuring no orders fall through the cracks. The goal is to automate routine tasks and provide instant visibility into business health.

## Goals

- **Reduce Manual Work** - Automate repetitive tasks
- **Zero Missed Orders** - Auto-escalation prevents forgotten deliveries
- **Instant Insights** - KPIs at a glance
- **Faster Response** - Canned responses for common situations

---

## 1. Auto-Rules Engine

### Rule Types

```
AUTO-RULE CATEGORIES:

1. ESCALATION RULES
   - If delivery stalled > 2 hours → Notify admin
   - If delivery stalled > 4 hours → Mark as urgent
   - If SLA breach → Auto-escalate + log

2. NOTIFICATION RULES
   - New paid order → Email + dashboard alert
   - Support ticket created → Email notification
   - Refund request → Priority notification

3. ASSIGNMENT RULES
   - Template orders → Assign to setup team
   - Tool orders → Auto-deliver (no assignment)
   - High-value orders (>₦50k) → Priority handling

4. WORKFLOW RULES
   - Order paid + tools only → Auto-create delivery
   - Payment confirmed → Send confirmation email
   - Delivery completed → Send satisfaction survey
```

### Database Schema

```sql
CREATE TABLE admin_auto_rules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    rule_type TEXT NOT NULL CHECK(rule_type IN ('escalation', 'notification', 'assignment', 'workflow')),
    trigger_event TEXT NOT NULL,
    conditions TEXT, -- JSON: conditions to match
    actions TEXT NOT NULL, -- JSON: actions to execute
    is_active INTEGER DEFAULT 1,
    priority INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin_rule_executions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    rule_id INTEGER NOT NULL REFERENCES admin_auto_rules(id) ON DELETE CASCADE,
    trigger_data TEXT, -- JSON: what triggered the rule
    result TEXT, -- JSON: what happened
    success INTEGER DEFAULT 1,
    error_message TEXT,
    executed_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_rules_type ON admin_auto_rules(rule_type);
CREATE INDEX idx_rules_active ON admin_auto_rules(is_active);
CREATE INDEX idx_executions_rule ON admin_rule_executions(rule_id);
```

### Rule Engine

```php
/**
 * Auto-rule engine
 */
class AutoRuleEngine {
    private $db;
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Process an event through all matching rules
     */
    public function processEvent($eventType, $eventData) {
        $stmt = $this->db->prepare("
            SELECT * FROM admin_auto_rules 
            WHERE is_active = 1 AND trigger_event = ?
            ORDER BY priority DESC
        ");
        $stmt->execute([$eventType]);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rules as $rule) {
            if ($this->matchesConditions($rule, $eventData)) {
                $this->executeActions($rule, $eventData);
            }
        }
    }
    
    /**
     * Check if event matches rule conditions
     */
    private function matchesConditions($rule, $eventData) {
        if (empty($rule['conditions'])) {
            return true;
        }
        
        $conditions = json_decode($rule['conditions'], true);
        
        foreach ($conditions as $field => $expected) {
            $actual = $eventData[$field] ?? null;
            
            if (is_array($expected)) {
                // Operator-based condition
                $operator = $expected['op'] ?? '=';
                $value = $expected['value'];
                
                switch ($operator) {
                    case '>':
                        if (!($actual > $value)) return false;
                        break;
                    case '<':
                        if (!($actual < $value)) return false;
                        break;
                    case '>=':
                        if (!($actual >= $value)) return false;
                        break;
                    case 'in':
                        if (!in_array($actual, $value)) return false;
                        break;
                    case 'contains':
                        if (strpos($actual, $value) === false) return false;
                        break;
                }
            } else {
                // Simple equality
                if ($actual !== $expected) return false;
            }
        }
        
        return true;
    }
    
    /**
     * Execute rule actions
     */
    private function executeActions($rule, $eventData) {
        $actions = json_decode($rule['actions'], true);
        $results = [];
        
        foreach ($actions as $action) {
            try {
                $result = $this->executeAction($action, $eventData);
                $results[] = ['action' => $action['type'], 'success' => true, 'result' => $result];
            } catch (Exception $e) {
                $results[] = ['action' => $action['type'], 'success' => false, 'error' => $e->getMessage()];
            }
        }
        
        // Log execution
        $stmt = $this->db->prepare("
            INSERT INTO admin_rule_executions (rule_id, trigger_data, result, success)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $rule['id'],
            json_encode($eventData),
            json_encode($results),
            !in_array(false, array_column($results, 'success')) ? 1 : 0
        ]);
        
        return $results;
    }
    
    /**
     * Execute a single action
     */
    private function executeAction($action, $eventData) {
        switch ($action['type']) {
            case 'send_email':
                return $this->sendEmail($action, $eventData);
            case 'send_notification':
                return $this->sendNotification($action, $eventData);
            case 'assign_to':
                return $this->assignTo($action, $eventData);
            case 'update_status':
                return $this->updateStatus($action, $eventData);
            case 'escalate':
                return $this->escalate($action, $eventData);
            case 'create_ticket':
                return $this->createTicket($action, $eventData);
            default:
                throw new Exception("Unknown action type: {$action['type']}");
        }
    }
}

// Usage
$engine = new AutoRuleEngine();

// When order is paid
$engine->processEvent('order_paid', [
    'order_id' => $orderId,
    'amount' => $order['final_amount'],
    'product_type' => $order['items'][0]['product_type'],
    'customer_email' => $order['customer_email']
]);
```

### Default Rules

```php
$defaultRules = [
    [
        'name' => 'Stalled Delivery Alert',
        'rule_type' => 'escalation',
        'trigger_event' => 'delivery_check', // Runs via cron
        'conditions' => json_encode([
            'delivery_state' => 'processing',
            'hours_since_creation' => ['op' => '>', 'value' => 2]
        ]),
        'actions' => json_encode([
            ['type' => 'send_notification', 'to' => 'admin', 'message' => 'Delivery #{delivery_id} stalled for {hours} hours'],
            ['type' => 'update_status', 'field' => 'priority', 'value' => 'high']
        ])
    ],
    [
        'name' => 'High Value Order Alert',
        'rule_type' => 'notification',
        'trigger_event' => 'order_paid',
        'conditions' => json_encode([
            'amount' => ['op' => '>=', 'value' => 50000]
        ]),
        'actions' => json_encode([
            ['type' => 'send_notification', 'to' => 'admin', 'message' => 'High value order received: ₦{amount}'],
            ['type' => 'assign_to', 'admin_id' => 1] // Assign to primary admin
        ])
    ],
    [
        'name' => 'Auto-Deliver Tools',
        'rule_type' => 'workflow',
        'trigger_event' => 'order_paid',
        'conditions' => json_encode([
            'has_templates' => false // Tools only
        ]),
        'actions' => json_encode([
            ['type' => 'create_delivery'],
            ['type' => 'send_email', 'template' => 'delivery_ready']
        ])
    ]
];
```

---

## 2. KPI Dashboard

### Admin Dashboard Overview

```html
<!-- Admin KPI Dashboard -->
<div class="kpi-dashboard">
    <h2 class="text-xl font-bold mb-6">Business Overview</h2>
    
    <!-- Key Metrics Row -->
    <div class="grid grid-cols-4 gap-4 mb-8">
        <!-- Today's Revenue -->
        <div class="kpi-card">
            <div class="kpi-icon bg-green-100">
                <i class="bi bi-currency-dollar text-green-600"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">Today's Revenue</span>
                <span class="kpi-value">₦125,000</span>
                <span class="kpi-change positive">+12% vs yesterday</span>
            </div>
        </div>
        
        <!-- Orders Pending -->
        <div class="kpi-card">
            <div class="kpi-icon bg-yellow-100">
                <i class="bi bi-hourglass-split text-yellow-600"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">Pending Delivery</span>
                <span class="kpi-value">5</span>
                <span class="kpi-change warning">2 approaching SLA</span>
            </div>
        </div>
        
        <!-- Support Tickets -->
        <div class="kpi-card">
            <div class="kpi-icon bg-blue-100">
                <i class="bi bi-chat-dots text-blue-600"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">Open Tickets</span>
                <span class="kpi-value">3</span>
                <span class="kpi-change">1 new today</span>
            </div>
        </div>
        
        <!-- SLA Health -->
        <div class="kpi-card">
            <div class="kpi-icon bg-purple-100">
                <i class="bi bi-speedometer2 text-purple-600"></i>
            </div>
            <div class="kpi-content">
                <span class="kpi-label">SLA Health</span>
                <span class="kpi-value">98%</span>
                <span class="kpi-change positive">On target</span>
            </div>
        </div>
    </div>
    
    <!-- Action Items -->
    <div class="action-items mb-8">
        <h3 class="font-semibold mb-4">Requires Attention</h3>
        
        <div class="space-y-2">
            <!-- Urgent Item -->
            <div class="action-item urgent">
                <span class="badge badge-red">URGENT</span>
                <span>Order #1234 - Template setup overdue (3 hours)</span>
                <a href="/admin/order-detail.php?id=1234" class="btn btn-sm btn-primary">View</a>
            </div>
            
            <!-- Warning Item -->
            <div class="action-item warning">
                <span class="badge badge-yellow">WARNING</span>
                <span>Support ticket #56 - No response in 4 hours</span>
                <a href="/admin/ticket.php?id=56" class="btn btn-sm btn-secondary">Reply</a>
            </div>
            
            <!-- Normal Item -->
            <div class="action-item">
                <span class="badge badge-gray">INFO</span>
                <span>5 new customer registrations today</span>
                <a href="/admin/customers.php" class="btn btn-sm btn-secondary">View</a>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-2 gap-6">
        <!-- Revenue Chart -->
        <div class="chart-card">
            <h3 class="font-semibold mb-4">Revenue (Last 7 Days)</h3>
            <canvas id="revenueChart"></canvas>
        </div>
        
        <!-- Orders Chart -->
        <div class="chart-card">
            <h3 class="font-semibold mb-4">Orders by Status</h3>
            <canvas id="ordersChart"></canvas>
        </div>
    </div>
</div>
```

### KPI Data API

```php
/**
 * Get KPI data for admin dashboard
 */
function getAdminKPIs() {
    $db = getDb();
    
    // Today's revenue
    $stmt = $db->query("
        SELECT COALESCE(SUM(final_amount), 0) as today_revenue
        FROM pending_orders 
        WHERE status = 'paid' 
        AND date(created_at) = date('now')
    ");
    $todayRevenue = $stmt->fetchColumn();
    
    // Yesterday's revenue (for comparison)
    $stmt = $db->query("
        SELECT COALESCE(SUM(final_amount), 0) as yesterday_revenue
        FROM pending_orders 
        WHERE status = 'paid' 
        AND date(created_at) = date('now', '-1 day')
    ");
    $yesterdayRevenue = $stmt->fetchColumn();
    
    // Pending deliveries
    $stmt = $db->query("
        SELECT COUNT(*) FROM deliveries 
        WHERE delivery_state IN ('pending', 'processing')
    ");
    $pendingDeliveries = $stmt->fetchColumn();
    
    // Deliveries approaching SLA
    $stmt = $db->query("
        SELECT COUNT(*) FROM deliveries 
        WHERE delivery_state IN ('pending', 'processing')
        AND sla_deadline IS NOT NULL
        AND datetime(sla_deadline) <= datetime('now', '+2 hours')
    ");
    $approachingSLA = $stmt->fetchColumn();
    
    // Open support tickets
    $stmt = $db->query("
        SELECT COUNT(*) FROM customer_support_tickets 
        WHERE status = 'open'
    ");
    $openTickets = $stmt->fetchColumn();
    
    // SLA breach rate (last 30 days)
    $stmt = $db->query("
        SELECT 
            COUNT(CASE WHEN sla_breached = 1 THEN 1 END) as breached,
            COUNT(*) as total
        FROM deliveries 
        WHERE created_at > datetime('now', '-30 days')
        AND sla_deadline IS NOT NULL
    ");
    $slaData = $stmt->fetch(PDO::FETCH_ASSOC);
    $slaHealth = $slaData['total'] > 0 
        ? round((1 - ($slaData['breached'] / $slaData['total'])) * 100) 
        : 100;
    
    return [
        'today_revenue' => $todayRevenue,
        'revenue_change' => $yesterdayRevenue > 0 
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100) 
            : 0,
        'pending_deliveries' => $pendingDeliveries,
        'approaching_sla' => $approachingSLA,
        'open_tickets' => $openTickets,
        'sla_health' => $slaHealth
    ];
}
```

---

## 3. Customer Health Scores

### Health Score Calculation

```php
/**
 * Calculate customer health score (0-100)
 */
function calculateCustomerHealth($customerId) {
    $db = getDb();
    
    $scores = [];
    
    // 1. Order history (0-25 points)
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as order_count,
            COALESCE(SUM(CASE WHEN status = 'paid' THEN final_amount ELSE 0 END), 0) as total_spent,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
        FROM pending_orders 
        WHERE customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $orderScore = min(25, ($orderData['order_count'] * 5) - ($orderData['cancelled_count'] * 10));
    $scores['orders'] = max(0, $orderScore);
    
    // 2. Support interactions (0-25 points) - fewer is better
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM customer_support_tickets WHERE customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $ticketCount = $stmt->fetchColumn();
    
    $scores['support'] = max(0, 25 - ($ticketCount * 5));
    
    // 3. Engagement (0-25 points)
    $stmt = $db->prepare("
        SELECT 
            last_login_at,
            (SELECT COUNT(*) FROM deliveries d 
             JOIN pending_orders po ON d.order_id = po.id 
             WHERE po.customer_id = ? AND d.customer_download_count > 0) as downloads
        FROM customers WHERE id = ?
    ");
    $stmt->execute([$customerId, $customerId]);
    $engagement = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $daysSinceLogin = $engagement['last_login_at'] 
        ? (time() - strtotime($engagement['last_login_at'])) / 86400 
        : 999;
    
    $engagementScore = 0;
    if ($daysSinceLogin < 7) $engagementScore += 15;
    elseif ($daysSinceLogin < 30) $engagementScore += 10;
    elseif ($daysSinceLogin < 90) $engagementScore += 5;
    
    $engagementScore += min(10, $engagement['downloads'] * 2);
    $scores['engagement'] = $engagementScore;
    
    // 4. Payment behavior (0-25 points)
    $stmt = $db->prepare("
        SELECT 
            COUNT(CASE WHEN status = 'paid' AND payment_method = 'automatic' THEN 1 END) as auto_paid,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
        FROM pending_orders WHERE customer_id = ?
    ");
    $stmt->execute([$customerId]);
    $paymentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $paymentScore = min(25, ($paymentData['auto_paid'] * 5) - ($paymentData['pending'] * 3));
    $scores['payment'] = max(0, $paymentScore);
    
    $totalScore = array_sum($scores);
    
    return [
        'score' => $totalScore,
        'breakdown' => $scores,
        'status' => $totalScore >= 75 ? 'healthy' : ($totalScore >= 50 ? 'neutral' : 'at_risk')
    ];
}
```

### Health Score Display

```html
<!-- Customer List with Health Scores -->
<table class="admin-table">
    <thead>
        <tr>
            <th>Customer</th>
            <th>Email</th>
            <th>Orders</th>
            <th>Health Score</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <div class="flex items-center">
                    <img src="/assets/avatar.png" class="w-8 h-8 rounded-full mr-2">
                    <span>John Doe</span>
                </div>
            </td>
            <td>john@example.com</td>
            <td>5 orders (₦125,000)</td>
            <td>
                <div class="health-score">
                    <div class="health-bar">
                        <div class="health-fill" style="width: 85%"></div>
                    </div>
                    <span class="text-sm">85/100</span>
                </div>
            </td>
            <td><span class="badge badge-green">Healthy</span></td>
            <td><a href="#">View</a></td>
        </tr>
        <tr>
            <td>
                <div class="flex items-center">
                    <img src="/assets/avatar.png" class="w-8 h-8 rounded-full mr-2">
                    <span>Jane Smith</span>
                </div>
            </td>
            <td>jane@example.com</td>
            <td>2 orders (₦30,000)</td>
            <td>
                <div class="health-score">
                    <div class="health-bar">
                        <div class="health-fill warning" style="width: 45%"></div>
                    </div>
                    <span class="text-sm">45/100</span>
                </div>
            </td>
            <td><span class="badge badge-yellow">At Risk</span></td>
            <td><a href="#">View</a></td>
        </tr>
    </tbody>
</table>
```

---

## 4. Canned Responses

### Response Templates

```sql
CREATE TABLE canned_responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    category TEXT NOT NULL,
    shortcut TEXT UNIQUE, -- e.g., /download, /refund
    content TEXT NOT NULL,
    variables TEXT, -- JSON: placeholders like {customer_name}, {order_id}
    use_count INTEGER DEFAULT 0,
    created_by INTEGER REFERENCES users(id),
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_canned_category ON canned_responses(category);
CREATE INDEX idx_canned_shortcut ON canned_responses(shortcut);
```

### Default Responses

```php
$cannedResponses = [
    [
        'title' => 'Download Link Regenerated',
        'category' => 'delivery',
        'shortcut' => '/download',
        'content' => "Hi {customer_name},\n\nI've regenerated your download link. You can access your file here:\n\n{download_url}\n\nThis link is valid for 7 days. Let me know if you have any issues!\n\nBest regards,\nWebDaddy Support",
        'variables' => json_encode(['customer_name', 'download_url'])
    ],
    [
        'title' => 'Refund Approved',
        'category' => 'refund',
        'shortcut' => '/refundok',
        'content' => "Hi {customer_name},\n\nGood news! Your refund request for Order #{order_id} has been approved.\n\nRefund amount: ₦{amount}\nProcessing time: 3-5 business days\n\nThe funds will be returned to your original payment method. You'll receive a confirmation once it's processed.\n\nThank you for your patience.\n\nBest regards,\nWebDaddy Support",
        'variables' => json_encode(['customer_name', 'order_id', 'amount'])
    ],
    [
        'title' => 'Credentials Ready',
        'category' => 'delivery',
        'shortcut' => '/creds',
        'content' => "Hi {customer_name},\n\nGreat news! Your website is ready. Here are your login details:\n\n🌐 Website: {website_url}\n👤 Username: {username}\n🔑 Password: {password}\n\nPlease change your password after first login.\n\nIf you need help getting started, check out our guide: {guide_url}\n\nBest regards,\nWebDaddy Support",
        'variables' => json_encode(['customer_name', 'website_url', 'username', 'password', 'guide_url'])
    ],
    [
        'title' => 'Delay Apology',
        'category' => 'support',
        'shortcut' => '/delay',
        'content' => "Hi {customer_name},\n\nI sincerely apologize for the delay with your order. We're experiencing higher than usual volume, but rest assured your order is our priority.\n\nYour {product_name} will be ready within the next {eta}. You'll receive an immediate notification once it's done.\n\nThank you for your patience and understanding.\n\nBest regards,\nWebDaddy Support",
        'variables' => json_encode(['customer_name', 'product_name', 'eta'])
    ],
    [
        'title' => 'General Follow-up',
        'category' => 'support',
        'shortcut' => '/followup',
        'content' => "Hi {customer_name},\n\nI'm just checking in to see if you were able to resolve your issue. Is there anything else I can help you with?\n\nIf everything is working well, feel free to close this ticket. Otherwise, just reply and I'll assist you further.\n\nBest regards,\nWebDaddy Support",
        'variables' => json_encode(['customer_name'])
    ]
];
```

### Canned Response UI

```html
<!-- Quick Response Selector -->
<div x-data="cannedResponses()" class="canned-response-selector">
    <div class="flex items-center gap-2 mb-2">
        <button @click="showSelector = !showSelector" class="btn btn-sm btn-secondary">
            <i class="bi bi-lightning-charge mr-1"></i> Quick Response
        </button>
        
        <input type="text" 
               x-model="shortcut"
               @keydown.enter="insertByShortcut()"
               placeholder="Type /shortcut..."
               class="input-sm w-32">
    </div>
    
    <!-- Response Picker -->
    <div x-show="showSelector" class="response-picker">
        <div class="response-categories">
            <button @click="category = 'all'" 
                    :class="category === 'all' ? 'active' : ''">All</button>
            <button @click="category = 'delivery'" 
                    :class="category === 'delivery' ? 'active' : ''">Delivery</button>
            <button @click="category = 'refund'" 
                    :class="category === 'refund' ? 'active' : ''">Refund</button>
            <button @click="category = 'support'" 
                    :class="category === 'support' ? 'active' : ''">Support</button>
        </div>
        
        <div class="response-list">
            <template x-for="response in filteredResponses" :key="response.id">
                <div @click="selectResponse(response)" class="response-item">
                    <span class="font-medium" x-text="response.title"></span>
                    <span class="text-xs text-gray-500" x-text="response.shortcut"></span>
                </div>
            </template>
        </div>
    </div>
</div>
```

---

## 5. Unified Search

### Global Search Implementation

```php
/**
 * Global admin search
 */
function adminGlobalSearch($query, $limit = 20) {
    $db = getDb();
    $results = [];
    $searchTerm = '%' . $query . '%';
    
    // Search orders
    $stmt = $db->prepare("
        SELECT id, customer_name, customer_email, final_amount, status, 'order' as type
        FROM pending_orders 
        WHERE customer_name LIKE ? OR customer_email LIKE ? OR id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, (int)$query]);
    $results['orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search customers
    $stmt = $db->prepare("
        SELECT id, full_name, email, phone, 'customer' as type
        FROM customers 
        WHERE full_name LIKE ? OR email LIKE ? OR phone LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $results['customers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search tickets
    $stmt = $db->prepare("
        SELECT id, subject, status, 'ticket' as type
        FROM customer_support_tickets 
        WHERE subject LIKE ? OR id = ?
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, (int)$query]);
    $results['tickets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Search products
    $stmt = $db->prepare("
        SELECT id, name, 'template' as type FROM templates WHERE name LIKE ?
        UNION ALL
        SELECT id, name, 'tool' as type FROM tools WHERE name LIKE ?
        LIMIT 5
    ");
    $stmt->execute([$searchTerm, $searchTerm]);
    $results['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $results;
}
```

### Search UI

```html
<!-- Global Search Bar -->
<div x-data="globalSearch()" class="global-search">
    <div class="search-input-container">
        <i class="bi bi-search"></i>
        <input type="text" 
               x-model="query"
               @input.debounce.300ms="search()"
               @focus="showResults = true"
               @keydown.escape="showResults = false"
               placeholder="Search orders, customers, tickets..."
               class="search-input">
        <kbd class="search-shortcut">⌘K</kbd>
    </div>
    
    <!-- Search Results Dropdown -->
    <div x-show="showResults && hasResults" class="search-results-dropdown">
        <!-- Orders -->
        <div x-show="results.orders?.length" class="result-section">
            <h4 class="result-section-title">Orders</h4>
            <template x-for="order in results.orders" :key="order.id">
                <a :href="`/admin/order-detail.php?id=${order.id}`" class="result-item">
                    <i class="bi bi-receipt"></i>
                    <div>
                        <span x-text="`#${order.id} - ${order.customer_name}`"></span>
                        <span class="text-sm text-gray-500" x-text="`₦${order.final_amount?.toLocaleString()}`"></span>
                    </div>
                    <span class="badge" :class="getStatusClass(order.status)" x-text="order.status"></span>
                </a>
            </template>
        </div>
        
        <!-- Customers -->
        <div x-show="results.customers?.length" class="result-section">
            <h4 class="result-section-title">Customers</h4>
            <template x-for="customer in results.customers" :key="customer.id">
                <a :href="`/admin/customer-detail.php?id=${customer.id}`" class="result-item">
                    <i class="bi bi-person"></i>
                    <div>
                        <span x-text="customer.full_name || customer.email"></span>
                        <span class="text-sm text-gray-500" x-text="customer.email"></span>
                    </div>
                </a>
            </template>
        </div>
        
        <!-- Tickets -->
        <div x-show="results.tickets?.length" class="result-section">
            <h4 class="result-section-title">Support Tickets</h4>
            <template x-for="ticket in results.tickets" :key="ticket.id">
                <a :href="`/admin/ticket.php?id=${ticket.id}`" class="result-item">
                    <i class="bi bi-chat-dots"></i>
                    <div>
                        <span x-text="`#${ticket.id} - ${ticket.subject}`"></span>
                    </div>
                    <span class="badge" :class="getTicketStatusClass(ticket.status)" x-text="ticket.status"></span>
                </a>
            </template>
        </div>
        
        <!-- No Results -->
        <div x-show="!hasResults && query.length > 2" class="p-4 text-center text-gray-500">
            No results found for "<span x-text="query"></span>"
        </div>
    </div>
</div>
```

---

## 6. Implementation Checklist

### Phase 1: Auto-Rules Engine
- [ ] Create database tables
- [ ] Implement rule engine class
- [ ] Add default rules
- [ ] Create admin UI for rule management
- [ ] Integrate with event system

### Phase 2: KPI Dashboard
- [ ] Implement KPI data functions
- [ ] Build dashboard UI
- [ ] Add charts (Chart.js)
- [ ] Create action items list
- [ ] Set up auto-refresh

### Phase 3: Customer Health
- [ ] Implement health score calculation
- [ ] Add to customer list view
- [ ] Create health detail breakdown
- [ ] Add filtering by health status

### Phase 4: Canned Responses
- [ ] Create database table
- [ ] Add default responses
- [ ] Build response picker UI
- [ ] Implement shortcut system
- [ ] Add variable substitution

### Phase 5: Unified Search
- [ ] Implement search API
- [ ] Build search UI component
- [ ] Add keyboard shortcuts
- [ ] Optimize search performance

---

## Related Documents

- [06_ADMIN_UPDATES.md](./06_ADMIN_UPDATES.md) - Admin panel structure
- [17_BULLETPROOF_DELIVERY_SYSTEM.md](./17_BULLETPROOF_DELIVERY_SYSTEM.md) - Delivery automation
- [18_SELF_SERVICE_EXPERIENCE.md](./18_SELF_SERVICE_EXPERIENCE.md) - Customer self-service
