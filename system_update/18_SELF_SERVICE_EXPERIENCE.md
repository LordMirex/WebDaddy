# Self-Service Experience System

## Overview

This document outlines the self-service features that enable customers to resolve 70%+ of their issues without contacting support. The goal is to provide instant answers, guided troubleshooting, and transparent status visibility.

## Target: 70% Self-Service Resolution Rate

---

## 1. Knowledge Base

### Structure

```
HELP CENTER (/user/help/)

├── Getting Started
│   ├── How to access your purchases
│   ├── Understanding your dashboard
│   └── First-time setup guide
│
├── Downloads & Files
│   ├── How to download your tools
│   ├── Download link expired? Here's what to do
│   ├── File won't open? Troubleshooting guide
│   └── Supported file formats
│
├── Website Templates
│   ├── How to log in to your new website
│   ├── Forgot your website password?
│   ├── How to update your website content
│   └── Domain and hosting explained
│
├── Orders & Payments
│   ├── Understanding your order status
│   ├── Payment methods we accept
│   ├── Request a refund
│   └── Apply a discount code
│
├── Account
│   ├── Update your profile
│   ├── Change your password
│   ├── Manage notification preferences
│   └── Delete your account
│
└── Troubleshooting
    ├── Common issues and solutions
    ├── Contact support
    └── Emergency support
```

### Database Schema

```sql
CREATE TABLE help_articles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    category TEXT NOT NULL,
    content TEXT NOT NULL,
    summary TEXT,
    keywords TEXT,
    view_count INTEGER DEFAULT 0,
    helpful_yes INTEGER DEFAULT 0,
    helpful_no INTEGER DEFAULT 0,
    is_published INTEGER DEFAULT 1,
    display_order INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_help_articles_category ON help_articles(category);
CREATE INDEX idx_help_articles_slug ON help_articles(slug);
CREATE INDEX idx_help_articles_published ON help_articles(is_published);

CREATE TABLE help_article_feedback (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    article_id INTEGER NOT NULL REFERENCES help_articles(id) ON DELETE CASCADE,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    is_helpful INTEGER NOT NULL,
    feedback_text TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
```

### UI Component

```html
<!-- Help Center Search -->
<div class="help-center-search">
    <div class="max-w-2xl mx-auto text-center py-12">
        <h1 class="text-3xl font-bold mb-4">How can we help you?</h1>
        
        <div class="relative">
            <input type="text" 
                   x-model="searchQuery"
                   @input.debounce.300ms="searchArticles()"
                   placeholder="Search for answers..."
                   class="w-full px-6 py-4 text-lg border-2 border-gray-200 rounded-xl 
                          focus:border-primary-500 focus:ring-0">
            <i class="bi bi-search absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-xl"></i>
        </div>
        
        <!-- Quick Links -->
        <div class="flex flex-wrap justify-center gap-2 mt-4">
            <a href="#downloads" class="tag">Download Issues</a>
            <a href="#login" class="tag">Login Problems</a>
            <a href="#refund" class="tag">Refunds</a>
            <a href="#templates" class="tag">Website Help</a>
        </div>
    </div>
</div>

<!-- Search Results -->
<div x-show="searchResults.length > 0" class="search-results">
    <template x-for="article in searchResults" :key="article.id">
        <a :href="`/user/help/article.php?slug=${article.slug}`" 
           class="block p-4 border-b hover:bg-gray-50">
            <h3 x-text="article.title" class="font-medium text-primary-600"></h3>
            <p x-text="article.summary" class="text-sm text-gray-600 mt-1"></p>
        </a>
    </template>
</div>
```

---

## 2. Guided Troubleshooters

### Interactive Problem Solvers

```
TROUBLESHOOTER: "I can't download my file"

Step 1: Have you tried the download link?
  [Yes, it doesn't work] → Step 2
  [No, I can't find it] → Show: "Go to Dashboard > Orders > Click on your order > Find Downloads section"

Step 2: What happens when you click the link?
  [It says expired] → Action: Show "Regenerate Link" button
  [Error message] → Step 3
  [Nothing happens] → Show: "Try a different browser or disable popup blocker"

Step 3: What's the error message?
  [File not found] → Auto-create support ticket with context
  [Permission denied] → Show: "Log in first, then try again"
  [Other] → Show text input for error, then create ticket
```

### Troubleshooter Engine

```php
/**
 * Troubleshooter flow definition
 */
$troubleshooters = [
    'download_issue' => [
        'title' => "I can't download my file",
        'steps' => [
            [
                'id' => 'tried_link',
                'question' => 'Have you tried clicking the download link from your order?',
                'options' => [
                    ['label' => "Yes, it doesn't work", 'next' => 'what_happens'],
                    ['label' => "No, I can't find it", 'action' => 'show_download_location']
                ]
            ],
            [
                'id' => 'what_happens',
                'question' => 'What happens when you click the download link?',
                'options' => [
                    ['label' => 'It says the link expired', 'action' => 'regenerate_link'],
                    ['label' => 'I see an error message', 'next' => 'error_type'],
                    ['label' => 'Nothing happens at all', 'action' => 'browser_tips']
                ]
            ],
            [
                'id' => 'error_type',
                'question' => 'What error message do you see?',
                'options' => [
                    ['label' => 'File not found', 'action' => 'create_ticket'],
                    ['label' => 'Permission denied', 'action' => 'login_first'],
                    ['label' => 'Something else', 'action' => 'describe_error']
                ]
            ]
        ],
        'actions' => [
            'show_download_location' => [
                'type' => 'info',
                'content' => 'Go to your Dashboard → Orders → Click on the order → Find the Downloads section'
            ],
            'regenerate_link' => [
                'type' => 'button',
                'label' => 'Generate New Download Link',
                'api' => '/api/customer/regenerate-download.php'
            ],
            'browser_tips' => [
                'type' => 'info',
                'content' => 'Try: 1) Use a different browser 2) Disable popup blocker 3) Clear cache'
            ],
            'create_ticket' => [
                'type' => 'ticket',
                'category' => 'download_issue',
                'priority' => 'high'
            ]
        ]
    ],
    
    'login_issue' => [
        'title' => "I can't log in to my website",
        // ... similar structure
    ],
    
    'refund_request' => [
        'title' => "I want a refund",
        // ... similar structure
    ]
];
```

### UI Component

```html
<!-- Troubleshooter Widget -->
<div x-data="troubleshooter('download_issue')" class="troubleshooter-widget">
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-semibold mb-4" x-text="title"></h3>
        
        <!-- Current Step -->
        <div class="step-content">
            <p class="text-gray-700 mb-4" x-text="currentStep.question"></p>
            
            <div class="space-y-2">
                <template x-for="option in currentStep.options" :key="option.label">
                    <button @click="selectOption(option)"
                            class="w-full text-left p-4 border rounded-lg hover:border-primary-500 
                                   hover:bg-primary-50 transition-colors">
                        <span x-text="option.label"></span>
                        <i class="bi bi-chevron-right float-right text-gray-400"></i>
                    </button>
                </template>
            </div>
        </div>
        
        <!-- Action Result -->
        <div x-show="showAction" class="action-result mt-4 p-4 bg-blue-50 rounded-lg">
            <template x-if="actionType === 'info'">
                <div>
                    <i class="bi bi-info-circle text-blue-500 mr-2"></i>
                    <span x-text="actionContent"></span>
                </div>
            </template>
            
            <template x-if="actionType === 'button'">
                <button @click="executeAction()" 
                        class="btn btn-primary w-full"
                        :disabled="executing">
                    <span x-text="actionLabel"></span>
                </button>
            </template>
        </div>
        
        <!-- Back Button -->
        <button x-show="stepHistory.length > 0" 
                @click="goBack()"
                class="mt-4 text-gray-500 hover:text-gray-700">
            <i class="bi bi-arrow-left mr-1"></i> Back
        </button>
    </div>
</div>
```

---

## 3. Delivery Health Indicators

### Visual Status System

```html
<!-- Order Card with Health Indicator -->
<div class="order-card">
    <div class="flex items-center justify-between mb-4">
        <div>
            <span class="text-sm text-gray-500">Order #1234</span>
            <h3 class="font-semibold">Premium Business Template</h3>
        </div>
        
        <!-- Health Badge -->
        <div class="health-badge" :class="healthClass">
            <span class="health-dot"></span>
            <span x-text="healthLabel"></span>
        </div>
    </div>
    
    <!-- Progress Timeline -->
    <div class="timeline">
        <div class="timeline-item completed">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <span class="font-medium">Payment Confirmed</span>
                <span class="text-sm text-gray-500">Dec 14, 10:30 AM</span>
            </div>
        </div>
        
        <div class="timeline-item current">
            <div class="timeline-dot pulse"></div>
            <div class="timeline-content">
                <span class="font-medium">Setting Up Website</span>
                <span class="text-sm text-gray-500">In progress...</span>
            </div>
        </div>
        
        <div class="timeline-item pending">
            <div class="timeline-dot"></div>
            <div class="timeline-content">
                <span class="font-medium">Credentials Ready</span>
                <span class="text-sm text-gray-500">Waiting</span>
            </div>
        </div>
    </div>
</div>
```

### Health Status Logic

```javascript
function getDeliveryHealth(delivery) {
    const now = new Date();
    const created = new Date(delivery.created_at);
    const hoursSinceCreation = (now - created) / (1000 * 60 * 60);
    
    // Define expected times by product type
    const expectedTimes = {
        'tool': 0.5,      // 30 minutes
        'template': 24,   // 24 hours
        'api_key': 0.25   // 15 minutes
    };
    
    const expected = expectedTimes[delivery.product_type] || 24;
    
    if (delivery.delivery_state === 'completed' || delivery.delivery_state === 'downloaded') {
        return { status: 'success', label: 'Delivered', class: 'bg-green-100 text-green-800' };
    }
    
    if (delivery.delivery_state === 'ready') {
        return { status: 'ready', label: 'Ready', class: 'bg-blue-100 text-blue-800' };
    }
    
    if (delivery.delivery_state === 'failed' || delivery.delivery_state === 'stalled') {
        return { status: 'issue', label: 'Issue Detected', class: 'bg-red-100 text-red-800' };
    }
    
    // Check if on track
    if (hoursSinceCreation <= expected * 0.5) {
        return { status: 'on_track', label: 'On Track', class: 'bg-green-100 text-green-800' };
    }
    
    if (hoursSinceCreation <= expected) {
        return { status: 'processing', label: 'Processing', class: 'bg-yellow-100 text-yellow-800' };
    }
    
    // Delayed
    return { status: 'delayed', label: 'Delayed', class: 'bg-orange-100 text-orange-800' };
}
```

---

## 4. Refund Request System

### Self-Service Refund Flow

```
REFUND REQUEST FLOW:

1. User clicks "Request Refund" on order
2. System checks eligibility:
   - Order within refund window (7 days)?
   - Product type allows refunds?
   - Has user already downloaded/used?
3. If eligible → Show refund form
4. User selects reason + optional details
5. Submit creates ticket with priority
6. Admin reviews and processes
7. User notified of outcome
```

### Eligibility Check

```php
/**
 * Check if order is eligible for refund
 */
function checkRefundEligibility($orderId, $customerId) {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT po.*, 
               julianday('now') - julianday(po.created_at) as days_since_order
        FROM pending_orders po
        WHERE po.id = ? AND po.customer_id = ? AND po.status = 'paid'
    ");
    $stmt->execute([$orderId, $customerId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        return ['eligible' => false, 'reason' => 'Order not found or not paid'];
    }
    
    // Check time window
    $refundWindowDays = defined('REFUND_WINDOW_DAYS') ? REFUND_WINDOW_DAYS : 7;
    if ($order['days_since_order'] > $refundWindowDays) {
        return ['eligible' => false, 'reason' => 'Refund window has expired'];
    }
    
    // Check if any items were downloaded/accessed
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM deliveries 
        WHERE order_id = ? AND (customer_download_count > 0 OR delivery_state = 'downloaded')
    ");
    $stmt->execute([$orderId]);
    $downloadedCount = $stmt->fetchColumn();
    
    if ($downloadedCount > 0) {
        return [
            'eligible' => true, 
            'partial' => true,
            'reason' => 'Some items were already downloaded. Partial refund may apply.'
        ];
    }
    
    return ['eligible' => true, 'partial' => false];
}
```

### Refund Request Form

```html
<!-- Refund Request Modal -->
<div x-show="showRefundModal" class="modal-overlay">
    <div class="modal-content max-w-lg">
        <h3 class="text-xl font-semibold mb-4">Request Refund</h3>
        
        <!-- Eligibility Check -->
        <div x-show="checking" class="text-center py-4">
            <div class="spinner"></div>
            <p class="text-gray-500 mt-2">Checking eligibility...</p>
        </div>
        
        <!-- Eligible -->
        <div x-show="!checking && eligible">
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                <i class="bi bi-check-circle text-green-500 mr-2"></i>
                <span class="text-green-800">This order is eligible for a refund.</span>
            </div>
            
            <form @submit.prevent="submitRefundRequest()">
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Reason for refund</label>
                    <select x-model="refundReason" required class="input w-full">
                        <option value="">Select a reason...</option>
                        <option value="not_as_described">Product not as described</option>
                        <option value="technical_issue">Technical issues</option>
                        <option value="changed_mind">Changed my mind</option>
                        <option value="duplicate_purchase">Duplicate purchase</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium mb-2">Additional details (optional)</label>
                    <textarea x-model="refundDetails" 
                              rows="3" 
                              class="input w-full"
                              placeholder="Tell us more about why you want a refund..."></textarea>
                </div>
                
                <div class="flex gap-3">
                    <button type="button" @click="showRefundModal = false" 
                            class="btn btn-secondary flex-1">Cancel</button>
                    <button type="submit" class="btn btn-primary flex-1">Submit Request</button>
                </div>
            </form>
        </div>
        
        <!-- Not Eligible -->
        <div x-show="!checking && !eligible">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                <i class="bi bi-exclamation-triangle text-yellow-500 mr-2"></i>
                <span class="text-yellow-800" x-text="ineligibleReason"></span>
            </div>
            
            <p class="text-gray-600 mb-4">
                You can still contact our support team to discuss your situation.
            </p>
            
            <a href="/user/new-ticket.php?category=refund&order_id=<?= $orderId ?>" 
               class="btn btn-primary w-full">Contact Support</a>
        </div>
    </div>
</div>
```

---

## 5. Order Timeline

### Complete Order History

```html
<!-- Order Detail - Timeline -->
<div class="order-timeline">
    <h3 class="text-lg font-semibold mb-4">Order Timeline</h3>
    
    <div class="timeline-container">
        <!-- Each event -->
        <div class="timeline-event">
            <div class="timeline-marker completed"></div>
            <div class="timeline-line"></div>
            <div class="timeline-content">
                <div class="flex justify-between">
                    <span class="font-medium">Order Placed</span>
                    <span class="text-sm text-gray-500">Dec 14, 10:25 AM</span>
                </div>
                <p class="text-sm text-gray-600">You placed an order for Premium Business Template</p>
            </div>
        </div>
        
        <div class="timeline-event">
            <div class="timeline-marker completed"></div>
            <div class="timeline-line"></div>
            <div class="timeline-content">
                <div class="flex justify-between">
                    <span class="font-medium">Payment Confirmed</span>
                    <span class="text-sm text-gray-500">Dec 14, 10:28 AM</span>
                </div>
                <p class="text-sm text-gray-600">Payment of ₦25,000 received via Paystack</p>
            </div>
        </div>
        
        <div class="timeline-event">
            <div class="timeline-marker current pulse"></div>
            <div class="timeline-line dashed"></div>
            <div class="timeline-content">
                <div class="flex justify-between">
                    <span class="font-medium">Setting Up Your Website</span>
                    <span class="text-sm text-gray-500">In Progress</span>
                </div>
                <p class="text-sm text-gray-600">Our team is configuring your website. ETA: 2 hours</p>
            </div>
        </div>
        
        <div class="timeline-event">
            <div class="timeline-marker pending"></div>
            <div class="timeline-content">
                <div class="flex justify-between">
                    <span class="font-medium text-gray-400">Credentials Ready</span>
                    <span class="text-sm text-gray-400">Waiting</span>
                </div>
            </div>
        </div>
    </div>
</div>
```

### Event Types

```php
/**
 * Order event types
 */
$orderEventTypes = [
    'order_placed' => 'Order Placed',
    'payment_pending' => 'Awaiting Payment',
    'payment_confirmed' => 'Payment Confirmed',
    'payment_failed' => 'Payment Failed',
    'processing_started' => 'Processing Started',
    'delivery_created' => 'Delivery Created',
    'credentials_assigned' => 'Credentials Assigned',
    'download_ready' => 'Download Ready',
    'customer_downloaded' => 'Customer Downloaded',
    'delivery_completed' => 'Delivery Completed',
    'refund_requested' => 'Refund Requested',
    'refund_approved' => 'Refund Approved',
    'refund_denied' => 'Refund Denied',
    'ticket_created' => 'Support Ticket Created',
    'ticket_resolved' => 'Support Ticket Resolved'
];

/**
 * Log order event
 */
function logOrderEvent($orderId, $eventType, $details = null, $customerId = null) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO order_events (order_id, event_type, details, customer_id, created_at)
        VALUES (?, ?, ?, ?, datetime('now'))
    ");
    $stmt->execute([$orderId, $eventType, $details, $customerId]);
}
```

### Database Table

```sql
CREATE TABLE order_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id INTEGER NOT NULL REFERENCES pending_orders(id) ON DELETE CASCADE,
    event_type TEXT NOT NULL,
    details TEXT,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_order_events_order ON order_events(order_id);
CREATE INDEX idx_order_events_type ON order_events(event_type);
CREATE INDEX idx_order_events_created ON order_events(created_at);
```

---

## 6. Secure Customer Inbox

### Direct Messaging with Admin

```html
<!-- Customer Inbox -->
<div class="inbox-container" x-data="inbox()">
    <!-- Message List -->
    <div class="inbox-sidebar">
        <div class="p-4 border-b">
            <button @click="composeNew()" class="btn btn-primary w-full">
                <i class="bi bi-plus mr-2"></i> New Message
            </button>
        </div>
        
        <div class="message-list">
            <template x-for="thread in threads" :key="thread.id">
                <div @click="openThread(thread)" 
                     :class="{'bg-blue-50': thread.id === activeThread?.id, 'font-semibold': thread.unread}"
                     class="p-4 border-b cursor-pointer hover:bg-gray-50">
                    <div class="flex justify-between mb-1">
                        <span x-text="thread.subject" class="truncate"></span>
                        <span class="text-xs text-gray-500" x-text="formatDate(thread.last_message_at)"></span>
                    </div>
                    <p class="text-sm text-gray-600 truncate" x-text="thread.last_message_preview"></p>
                </div>
            </template>
        </div>
    </div>
    
    <!-- Message View -->
    <div class="inbox-main">
        <template x-if="activeThread">
            <div class="h-full flex flex-col">
                <!-- Header -->
                <div class="p-4 border-b">
                    <h3 x-text="activeThread.subject" class="font-semibold"></h3>
                    <p class="text-sm text-gray-500">
                        Re: Order #<span x-text="activeThread.order_id"></span>
                    </p>
                </div>
                
                <!-- Messages -->
                <div class="flex-1 overflow-y-auto p-4 space-y-4">
                    <template x-for="msg in activeThread.messages" :key="msg.id">
                        <div :class="msg.is_admin ? 'message-admin' : 'message-customer'">
                            <div class="message-bubble">
                                <p x-text="msg.content"></p>
                            </div>
                            <span class="text-xs text-gray-500" x-text="formatDateTime(msg.created_at)"></span>
                        </div>
                    </template>
                </div>
                
                <!-- Reply Input -->
                <div class="p-4 border-t">
                    <form @submit.prevent="sendReply()">
                        <textarea x-model="replyContent" 
                                  rows="3" 
                                  class="input w-full mb-2"
                                  placeholder="Type your message..."></textarea>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send mr-2"></i> Send
                        </button>
                    </form>
                </div>
            </div>
        </template>
        
        <template x-if="!activeThread">
            <div class="h-full flex items-center justify-center text-gray-400">
                <div class="text-center">
                    <i class="bi bi-envelope text-4xl mb-2"></i>
                    <p>Select a conversation or start a new one</p>
                </div>
            </div>
        </template>
    </div>
</div>
```

### Database Schema

```sql
CREATE TABLE customer_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    thread_id INTEGER NOT NULL,
    customer_id INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    admin_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    content TEXT NOT NULL,
    is_admin INTEGER DEFAULT 0,
    is_read INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE customer_message_threads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    order_id INTEGER REFERENCES pending_orders(id) ON DELETE SET NULL,
    subject TEXT NOT NULL,
    status TEXT DEFAULT 'open' CHECK(status IN ('open', 'closed', 'archived')),
    last_message_at TEXT DEFAULT CURRENT_TIMESTAMP,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_messages_thread ON customer_messages(thread_id);
CREATE INDEX idx_threads_customer ON customer_message_threads(customer_id);
CREATE INDEX idx_threads_status ON customer_message_threads(status);
```

---

## 7. Implementation Checklist

### Phase 1: Knowledge Base
- [ ] Create help_articles table
- [ ] Build help center UI (/user/help/)
- [ ] Create initial articles (10-15 core topics)
- [ ] Implement search functionality
- [ ] Add feedback mechanism

### Phase 2: Troubleshooters
- [ ] Define troubleshooter flows
- [ ] Build interactive UI component
- [ ] Integrate with existing APIs
- [ ] Add analytics tracking

### Phase 3: Delivery Health
- [ ] Implement health status logic
- [ ] Update order/delivery UI
- [ ] Add real-time status polling
- [ ] Create progress indicators

### Phase 4: Refund System
- [ ] Build eligibility checker
- [ ] Create refund request form
- [ ] Add admin processing workflow
- [ ] Implement notifications

### Phase 5: Timeline & Inbox
- [ ] Create order_events table
- [ ] Build timeline UI
- [ ] Implement messaging system
- [ ] Add notification badges

---

## 8. Success Metrics

| Metric | Current | Target |
|--------|---------|--------|
| Support tickets per order | Unknown | < 5% |
| Self-service resolution | 0% | > 70% |
| Knowledge base usage | N/A | > 50% of customers |
| Avg. time to resolution | Unknown | < 2 hours |
| Customer satisfaction | Unknown | > 90% |

---

## Related Documents

- [17_BULLETPROOF_DELIVERY_SYSTEM.md](./17_BULLETPROOF_DELIVERY_SYSTEM.md) - Delivery system
- [04_USER_DASHBOARD.md](./04_USER_DASHBOARD.md) - Customer dashboard
- [08_EMAIL_TEMPLATES.md](./08_EMAIL_TEMPLATES.md) - Email notifications
