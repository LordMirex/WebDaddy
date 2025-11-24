# WebDaddy Empire - Paystack Integration
## 5-Phase Implementation Plan

**Version 1.0 - Complete Roadmap**  
**Last Updated:** November 24, 2025

---

## 📋 TABLE OF CONTENTS

1. [Overview & Prerequisites](#overview--prerequisites)
2. [Phase 1: Foundation & Database Setup](#phase-1-foundation--database-setup)
3. [Phase 2: Paystack Payment Integration](#phase-2-paystack-payment-integration)
4. [Phase 3: Product Delivery System](#phase-3-product-delivery-system)
5. [Phase 4: Admin Dashboard & Management](#phase-4-admin-dashboard--management)
6. [Phase 5: Testing, Polish & Launch](#phase-5-testing-polish--launch)
7. [File Structure Overview](#file-structure-overview)

---

## 🎯 OVERVIEW & PREREQUISITES

### What We're Building

This project adds **automatic payment processing via Paystack** to your existing WebDaddy Empire marketplace. Customers will be able to:
- Choose between **Manual Payment (WhatsApp)** or **Automatic Payment (Paystack)**
- Pay instantly with credit/debit cards via Paystack
- Receive digital tools immediately after payment
- Get template hosting within 24 hours

### Current System vs New System

| Feature | Current (Manual) | After Implementation |
|---------|-----------------|---------------------|
| Payment Method | WhatsApp + Bank Transfer | WhatsApp OR Paystack Card Payment |
| Payment Verification | Manual by Admin | Automatic via Webhook |
| Tool Delivery | Manual by Admin | Automatic via Email |
| Template Hosting | Manual Setup | Automatic Notification + 24h Setup |
| Customer Experience | Wait for admin | Instant confirmation |

### Prerequisites (What You Need to Provide)

Before starting Phase 1, you must provide:
1. ✅ Paystack Secret Key (`sk_test_...` or `sk_live_...`)
2. ✅ Paystack Public Key (`pk_test_...` or `pk_live_...`)
3. ✅ Mode Selection (Test Mode or Live Mode)
4. ✅ Business Email (for notifications)

---

## 🔧 PHASE 1: FOUNDATION & DATABASE SETUP

**Duration:** 30-45 minutes  
**Complexity:** Medium  
**Goal:** Set up database tables, environment variables, and core infrastructure

### 1.1 Database Schema Updates

#### New Tables to Create

**Table 1: `payments`**
- Purpose: Track all payment transactions (both manual and Paystack)
- Fields:
  - `id` - Primary key
  - `pending_order_id` - Link to order
  - `payment_method` - 'manual' or 'paystack'
  - `amount_requested` - Total amount
  - `amount_paid` - Actual amount received
  - `currency` - NGN
  - `status` - pending/completed/failed/cancelled/refunded
  - `paystack_reference` - Unique Paystack transaction reference
  - `paystack_access_code` - For payment initialization
  - `paystack_authorization_url` - Redirect URL
  - `paystack_response` - Full JSON response from Paystack
  - `manual_verified_by` - Admin ID who verified (for manual payments)
  - `manual_verified_at` - Timestamp
  - `payment_note` - Admin notes
  - `created_at`, `updated_at`

**Table 2: `deliveries`**
- Purpose: Track individual product deliveries within orders
- Fields:
  - `id` - Primary key
  - `pending_order_id` - Link to order
  - `order_item_id` - Link to specific item
  - `product_id` - Template or Tool ID
  - `product_type` - 'template' or 'tool'
  - `product_name` - Name for display
  - `delivery_method` - email/download/hosted/manual
  - `delivery_type` - immediate/pending_24h/manual
  - `delivery_status` - pending/in_progress/ready/sent/delivered/failed
  - `delivery_link` - Download URL or hosted URL
  - `delivery_instructions` - Setup instructions
  - `delivery_note` - What customer receives
  - `file_path` - Server file location
  - `hosted_domain` - For templates
  - `hosted_url` - Full URL
  - `template_ready_at` - When template hosting is ready
  - `email_sent_at` - Email delivery timestamp
  - `sent_to_email` - Recipient
  - `delivered_at` - Confirmation timestamp
  - `delivery_attempts` - Retry counter
  - `last_error` - Error message if failed
  - `admin_notes` - Internal notes
  - `created_at`, `updated_at`

**Table 3: `tool_files`**
- Purpose: Store attachable files for digital tools
- Fields:
  - `id` - Primary key
  - `tool_id` - Link to tool
  - `file_name` - Display name
  - `file_path` - Server path
  - `file_type` - attachment/zip_archive/code/text_instructions/image/access_key/link/video
  - `file_description` - What this file contains
  - `file_size` - In bytes
  - `mime_type` - File MIME type
  - `download_count` - Track downloads
  - `is_public` - Can anyone access?
  - `access_expires_after_days` - Default 30 days
  - `require_password` - Security flag
  - `sort_order` - Display order
  - `created_at`, `updated_at`

**Table 4: `email_queue`**
- Purpose: Queue emails for reliable delivery
- Fields:
  - `id` - Primary key
  - `recipient_email`
  - `email_type` - payment_received/tools_ready/template_ready/delivery_link/etc
  - `pending_order_id` - Related order
  - `delivery_id` - Related delivery
  - `subject` - Email subject
  - `body` - Plain text body
  - `html_body` - HTML version
  - `status` - pending/sent/failed/bounced/retry
  - `attempts` - Retry counter
  - `max_attempts` - Default 3
  - `last_error` - Error message
  - `scheduled_at` - When to send
  - `sent_at` - Actual send time
  - `created_at`, `updated_at`

**Table 5: `payment_logs`**
- Purpose: Log all payment events for debugging
- Fields:
  - `id` - Primary key
  - `pending_order_id`
  - `payment_id`
  - `event_type` - initialize/verify/webhook/etc
  - `provider` - paystack/manual/system
  - `status` - success/failed
  - `amount`
  - `request_data` - JSON
  - `response_data` - JSON
  - `error_message`
  - `ip_address` - Client IP
  - `user_agent`
  - `created_at`

#### Modify Existing Tables

**Update `pending_orders` table:**
- Add `payment_method` - 'manual' or 'paystack'
- Add `payment_verified_at` - Timestamp
- Add `delivery_status` - pending/in_progress/fulfilled/failed
- Add `email_verified` - Boolean
- Add `paystack_payment_id` - Reference

**Update `tools` table:**
- Add `delivery_type` - email_attachment/file_download/both/video_link/code_access
- Add `has_attached_files` - Boolean
- Add `requires_email` - Boolean
- Add `email_subject` - Custom email subject
- Add `email_instructions` - Setup instructions
- Add `delivery_note` - What customer receives
- Add `delivery_description` - Detailed description
- Add `total_files` - File count

**Update `templates` table:**
- Add `delivery_type` - hosted_domain/file_download/both
- Add `requires_email` - Boolean
- Add `delivery_wait_hours` - Default 24
- Add `delivery_note` - What customer receives
- Add `delivery_description` - Detailed description
- Add `domain_template` - Hosting pattern

### 1.2 New PHP Files to Create

**File:** `/includes/paystack.php`
- Purpose: Core Paystack API integration
- Functions:
  - `initializePayment($orderData)` - Start payment
  - `verifyPayment($reference)` - Verify transaction
  - `logPaymentEvent($event, $data)` - Log to database
  - `getPaymentByReference($reference)` - Retrieve payment
  - `getPaymentByOrderId($orderId)` - Retrieve by order

**File:** `/includes/delivery.php`
- Purpose: Handle product delivery
- Functions:
  - `createDeliveryRecords($orderId)` - Create delivery entries
  - `processToolDelivery($deliveryId)` - Send tool files
  - `processTemplateDelivery($deliveryId)` - Setup template hosting
  - `sendDeliveryEmail($deliveryId)` - Email customer
  - `getDeliveryStatus($orderId)` - Check status
  - `retryFailedDelivery($deliveryId)` - Retry failed delivery

**File:** `/includes/email_queue.php`
- Purpose: Queue and send emails reliably
- Functions:
  - `queueEmail($recipient, $type, $subject, $body, $orderId)` - Add to queue
  - `processEmailQueue()` - Send pending emails
  - `retryFailedEmails()` - Retry failed sends
  - `getQueuedEmails($status)` - Get emails by status

**File:** `/includes/tool_files.php`
- Purpose: Manage tool file uploads and delivery
- Functions:
  - `uploadToolFile($toolId, $fileData)` - Upload file
  - `getToolFiles($toolId)` - Get all files for a tool
  - `deleteToolFile($fileId)` - Remove file
  - `generateDownloadLink($fileId, $orderId)` - Create secure link
  - `trackDownload($fileId, $orderId)` - Log download

### 1.3 Environment Variables Setup

**Create/Update:** `.env` file (or use Replit Secrets)

```env
# Paystack Configuration
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
PAYSTACK_MODE=test
PAYSTACK_CALLBACK_URL=https://yoursite.repl.co/api/paystack-callback.php
PAYSTACK_WEBHOOK_URL=https://yoursite.repl.co/api/paystack-webhook.php

# Business Configuration
BUSINESS_EMAIL=youremail@example.com
SITE_URL=https://yoursite.repl.co

# Email Configuration (if using SMTP)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=youremail@example.com
SMTP_PASSWORD=your_app_password
SMTP_FROM_EMAIL=noreply@yourdomain.com
SMTP_FROM_NAME=WebDaddy Empire
```

### 1.4 File Structure After Phase 1

```
webdaddy-empire/
├── includes/
│   ├── config.php (updated with Paystack constants)
│   ├── db.php (existing)
│   ├── paystack.php (NEW)
│   ├── delivery.php (NEW)
│   ├── email_queue.php (NEW)
│   ├── tool_files.php (NEW)
│   └── ...existing files
├── database/
│   ├── migrations/
│   │   ├── 001_add_payments_table.sql (NEW)
│   │   ├── 002_add_deliveries_table.sql (NEW)
│   │   ├── 003_add_tool_files_table.sql (NEW)
│   │   ├── 004_add_email_queue_table.sql (NEW)
│   │   ├── 005_add_payment_logs_table.sql (NEW)
│   │   └── 006_update_existing_tables.sql (NEW)
│   └── backups/
└── .env (NEW or UPDATED)
```

### 1.5 Phase 1 Checklist

- [ ] Create all 5 new database tables
- [ ] Update 3 existing tables (pending_orders, tools, templates)
- [ ] Create `/includes/paystack.php`
- [ ] Create `/includes/delivery.php`
- [ ] Create `/includes/email_queue.php`
- [ ] Create `/includes/tool_files.php`
- [ ] Setup environment variables
- [ ] Test database connections
- [ ] Verify all tables created successfully

### 1.6 Testing Phase 1

Run these checks:
1. Database tables exist with correct columns
2. PHP files load without errors
3. Environment variables accessible via `getenv()`
4. No syntax errors in new PHP files

---

## 💳 PHASE 2: PAYSTACK PAYMENT INTEGRATION

**Duration:** 1-2 hours  
**Complexity:** High  
**Goal:** Implement Paystack payment flow from checkout to verification

### 2.1 Update Checkout Page UI

**File to Modify:** `/cart-checkout.php`

#### Changes:

**Add Tab System** (after customer fills form)
```html
<!-- NEW: Payment Method Tabs -->
<div class="payment-tabs mb-4">
  <ul class="nav nav-tabs" role="tablist">
    <li class="nav-item">
      <a class="nav-link active" data-bs-toggle="tab" href="#manual-payment">
        💰 Manual Payment (Bank Transfer)
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link" data-bs-toggle="tab" href="#paystack-payment">
        💳 Pay with Card (Instant)
      </a>
    </li>
  </ul>
</div>

<!-- Tab Content -->
<div class="tab-content">
  <!-- Tab 1: Manual Payment (existing WhatsApp flow) -->
  <div id="manual-payment" class="tab-pane fade show active">
    <!-- Keep existing bank details + WhatsApp buttons -->
  </div>
  
  <!-- Tab 2: Paystack Payment (NEW) -->
  <div id="paystack-payment" class="tab-pane fade">
    <div class="paystack-payment-container">
      <div class="alert alert-info">
        ⚡ Pay instantly with your debit or credit card
      </div>
      
      <!-- Order Summary -->
      <div class="order-summary mb-4">
        <h5>Order Summary</h5>
        <div class="summary-items">
          <!-- Display cart items here -->
        </div>
        <div class="summary-total">
          <strong>Total: ₦<?php echo number_format($totals['final'], 2); ?></strong>
        </div>
      </div>
      
      <!-- Payment Button -->
      <button id="pay-now-btn" class="btn btn-primary btn-lg w-100">
        💳 Pay ₦<?php echo number_format($totals['final'], 2); ?> with Paystack
      </button>
      
      <div class="text-center mt-3 text-muted small">
        🔒 Secured by Paystack • Your payment information is encrypted
      </div>
    </div>
  </div>
</div>
```

### 2.2 Create Paystack JavaScript Integration

**File to Create:** `/assets/js/paystack-payment.js`

```javascript
// Initialize Paystack when page loads
document.addEventListener('DOMContentLoaded', function() {
    const payNowBtn = document.getElementById('pay-now-btn');
    
    if (payNowBtn) {
        payNowBtn.addEventListener('click', initializePaystackPayment);
    }
});

async function initializePaystackPayment() {
    const payNowBtn = document.getElementById('pay-now-btn');
    payNowBtn.disabled = true;
    payNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Initializing...';
    
    try {
        // Get customer details from form
        const customerData = {
            name: document.getElementById('customer-name').value,
            email: document.getElementById('customer-email').value,
            phone: document.getElementById('customer-phone').value,
            business_name: document.getElementById('business-name').value,
            affiliate_code: getAffiliateCode()
        };
        
        // Validate email
        if (!validateEmail(customerData.email)) {
            alert('Please enter a valid email address');
            resetPayButton();
            return;
        }
        
        // Initialize payment via backend
        const response = await fetch('/api/paystack-initialize.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(customerData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Open Paystack popup
            const handler = PaystackPop.setup({
                key: data.public_key,
                email: customerData.email,
                amount: data.amount * 100, // Convert to kobo
                ref: data.reference,
                currency: 'NGN',
                metadata: {
                    order_id: data.order_id,
                    customer_name: customerData.name,
                    custom_fields: [
                        {
                            display_name: "Business Name",
                            variable_name: "business_name",
                            value: customerData.business_name
                        }
                    ]
                },
                callback: function(response) {
                    verifyPayment(response.reference);
                },
                onClose: function() {
                    resetPayButton();
                    alert('Payment window closed');
                }
            });
            
            handler.openIframe();
        } else {
            alert('Error: ' + data.message);
            resetPayButton();
        }
    } catch (error) {
        console.error('Payment initialization error:', error);
        alert('Failed to initialize payment. Please try again.');
        resetPayButton();
    }
}

async function verifyPayment(reference) {
    document.getElementById('pay-now-btn').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Verifying payment...';
    
    try {
        const response = await fetch('/api/paystack-verify.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ reference: reference })
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Redirect to confirmation page
            window.location.href = '/cart-checkout.php?confirmed=' + data.order_id + '&payment=paystack';
        } else {
            alert('Payment verification failed: ' + data.message);
            resetPayButton();
        }
    } catch (error) {
        console.error('Payment verification error:', error);
        alert('Failed to verify payment. Please contact support with reference: ' + reference);
        resetPayButton();
    }
}

function resetPayButton() {
    const payNowBtn = document.getElementById('pay-now-btn');
    payNowBtn.disabled = false;
    payNowBtn.innerHTML = '💳 Pay with Paystack';
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function getAffiliateCode() {
    // Get from session or URL parameter
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('aff') || '';
}
```

### 2.3 Create Backend API Endpoints

**File to Create:** `/api/paystack-initialize.php`

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart.php';
require_once __DIR__ . '/../includes/paystack.php';

header('Content-Type: application/json');

startSecureSession();

try {
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($input['name']) || empty($input['email']) || empty($input['phone'])) {
        throw new Exception('Missing required fields');
    }
    
    // Get cart totals
    $cart = getCartContents();
    if (empty($cart)) {
        throw new Exception('Cart is empty');
    }
    
    $affiliateCode = $input['affiliate_code'] ?? null;
    $totals = getCartTotal(null, $affiliateCode);
    
    // Create pending order
    $orderId = createPendingOrder([
        'customer_name' => sanitizeInput($input['name']),
        'customer_email' => sanitizeInput($input['email']),
        'customer_phone' => sanitizeInput($input['phone']),
        'business_name' => sanitizeInput($input['business_name'] ?? ''),
        'payment_method' => 'paystack',
        'affiliate_code' => $affiliateCode,
        'amount' => $totals['final']
    ], $cart);
    
    if (!$orderId) {
        throw new Exception('Failed to create order');
    }
    
    // Initialize Paystack payment
    $paymentData = initializePayment([
        'order_id' => $orderId,
        'email' => $input['email'],
        'amount' => $totals['final'],
        'currency' => 'NGN',
        'callback_url' => SITE_URL . '/cart-checkout.php?confirmed=' . $orderId
    ]);
    
    if (!$paymentData['success']) {
        throw new Exception($paymentData['message']);
    }
    
    echo json_encode([
        'success' => true,
        'order_id' => $orderId,
        'reference' => $paymentData['reference'],
        'access_code' => $paymentData['access_code'],
        'authorization_url' => $paymentData['authorization_url'],
        'amount' => $totals['final'],
        'public_key' => PAYSTACK_PUBLIC_KEY
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

**File to Create:** `/api/paystack-verify.php`

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';

header('Content-Type: application/json');

startSecureSession();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['reference'])) {
        throw new Exception('Missing payment reference');
    }
    
    // Verify payment with Paystack
    $verification = verifyPayment($input['reference']);
    
    if (!$verification['success']) {
        throw new Exception($verification['message']);
    }
    
    // Get order ID from payment
    $payment = getPaymentByReference($input['reference']);
    if (!$payment) {
        throw new Exception('Payment record not found');
    }
    
    // Mark order as paid
    $db = getDb();
    $stmt = $db->prepare("
        UPDATE pending_orders 
        SET status = 'paid', 
            payment_verified_at = CURRENT_TIMESTAMP,
            payment_method = 'paystack'
        WHERE id = ?
    ");
    $stmt->execute([$payment['pending_order_id']]);
    
    // Create delivery records and send emails
    createDeliveryRecords($payment['pending_order_id']);
    
    // Clear cart
    clearCart();
    
    echo json_encode([
        'success' => true,
        'order_id' => $payment['pending_order_id'],
        'message' => 'Payment verified successfully'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

**File to Create:** `/api/paystack-webhook.php`

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Verify Paystack signature
$input = @file_get_contents("php://input");
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if ($signature !== hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Parse webhook data
$event = json_decode($input, true);

// Log the event
logPaymentEvent('webhook_received', $event);

// Handle different event types
switch ($event['event']) {
    case 'charge.success':
        handleSuccessfulPayment($event['data']);
        break;
        
    case 'charge.failed':
        handleFailedPayment($event['data']);
        break;
        
    default:
        // Log but don't process
        logPaymentEvent('webhook_ignored', ['event' => $event['event']]);
}

http_response_code(200);
echo json_encode(['status' => 'success']);

function handleSuccessfulPayment($data) {
    $reference = $data['reference'];
    
    // Find payment record
    $payment = getPaymentByReference($reference);
    if (!$payment) {
        logPaymentEvent('payment_not_found', ['reference' => $reference]);
        return;
    }
    
    // Already processed?
    if ($payment['status'] === 'completed') {
        return;
    }
    
    $db = getDb();
    
    // Update payment record
    $stmt = $db->prepare("
        UPDATE payments 
        SET status = 'completed',
            amount_paid = ?,
            paystack_response = ?,
            payment_verified_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([
        $data['amount'] / 100, // Convert from kobo
        json_encode($data),
        $payment['id']
    ]);
    
    // Update order
    $stmt = $db->prepare("
        UPDATE pending_orders 
        SET status = 'paid',
            payment_verified_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$payment['pending_order_id']]);
    
    // Trigger delivery
    createDeliveryRecords($payment['pending_order_id']);
    
    logPaymentEvent('payment_completed', ['order_id' => $payment['pending_order_id']]);
}

function handleFailedPayment($data) {
    $reference = $data['reference'];
    
    $payment = getPaymentByReference($reference);
    if (!$payment) {
        return;
    }
    
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE payments 
        SET status = 'failed',
            paystack_response = ?
        WHERE id = ?
    ");
    $stmt->execute([
        json_encode($data),
        $payment['id']
    ]);
    
    logPaymentEvent('payment_failed', ['order_id' => $payment['pending_order_id']]);
}
```

### 2.4 Update Configuration File

**File to Modify:** `/includes/config.php`

Add these constants:

```php
// Paystack Configuration
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY'));
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY'));
define('PAYSTACK_MODE', getenv('PAYSTACK_MODE') ?: 'test');
define('PAYSTACK_CALLBACK_URL', getenv('PAYSTACK_CALLBACK_URL'));
define('PAYSTACK_WEBHOOK_URL', getenv('PAYSTACK_WEBHOOK_URL'));

// Payment Settings
define('PAYMENT_CURRENCY', 'NGN');
define('PAYMENT_TIMEOUT', 600); // 10 minutes
```

### 2.5 Add Paystack Inline JS to Checkout Page

**File to Modify:** `/cart-checkout.php`

Add before closing `</body>` tag:

```html
<!-- Paystack Inline JS -->
<script src="https://js.paystack.co/v1/inline.js"></script>

<!-- Our Paystack Integration -->
<script src="/assets/js/paystack-payment.js"></script>
```

### 2.6 Phase 2 File Structure

```
webdaddy-empire/
├── api/
│   ├── paystack-initialize.php (NEW)
│   ├── paystack-verify.php (NEW)
│   └── paystack-webhook.php (NEW)
├── assets/
│   └── js/
│       └── paystack-payment.js (NEW)
├── includes/
│   ├── config.php (UPDATED - add Paystack constants)
│   └── paystack.php (from Phase 1)
└── cart-checkout.php (UPDATED - add tabs and Paystack UI)
```

### 2.7 Phase 2 Checklist

- [ ] Update `cart-checkout.php` with tab UI
- [ ] Create `/assets/js/paystack-payment.js`
- [ ] Create `/api/paystack-initialize.php`
- [ ] Create `/api/paystack-verify.php`
- [ ] Create `/api/paystack-webhook.php`
- [ ] Update `/includes/config.php` with Paystack constants
- [ ] Add Paystack Inline JS to checkout page
- [ ] Test tab switching works
- [ ] Test "Pay Now" button appears

### 2.8 Testing Phase 2

1. Load checkout page → Tabs should appear
2. Click "Pay with Card" tab → Order summary shows
3. Click "Pay Now" button → Paystack popup appears
4. Use test card: `4084 0840 8408 4081` (test mode)
5. Complete payment → Redirects to confirmation page
6. Check database → Order status = 'paid'

---

## 📦 PHASE 3: PRODUCT DELIVERY SYSTEM

**Duration:** 2-3 hours  
**Complexity:** High  
**Goal:** Automatically deliver digital tools and setup template hosting

### 3.1 Tool File Management System

#### Admin Interface for Uploading Tool Files

**File to Create:** `/admin/tool-files.php`

This page allows admins to:
- Upload files for each tool (ZIP, PDF, images, code, instructions)
- Set file types (attachment, access key, instructions, etc.)
- Write delivery instructions
- Preview what customer will receive

**UI Structure:**
```
Tool Files Management
├── Select Tool (dropdown)
├── Upload New File
│   ├── File Upload Field
│   ├── File Type Selection
│   ├── Description
│   └── [Upload] button
├── Existing Files List
│   ├── File Name | Type | Size | Downloads | Actions
│   └── [Delete] [Edit] buttons
└── Delivery Preview
    └── "What customer will receive" preview
```

**File to Create:** `/admin/includes/tool-file-upload-handler.php`

Handles:
- File uploads with validation
- Storage in `/uploads/tools/files/`
- Database entry in `tool_files` table
- Security checks (file type, size, malware scan if possible)

### 3.2 Implement Delivery Functions

**Update:** `/includes/delivery.php`

#### Function 1: `createDeliveryRecords($orderId)`

```php
function createDeliveryRecords($orderId) {
    $db = getDb();
    
    // Get order items
    $stmt = $db->prepare("
        SELECT oi.*, 
               CASE 
                   WHEN oi.product_type = 'template' THEN t.name
                   WHEN oi.product_type = 'tool' THEN tl.name
               END as product_name,
               CASE 
                   WHEN oi.product_type = 'template' THEN t.delivery_note
                   WHEN oi.product_type = 'tool' THEN tl.delivery_note
               END as delivery_note
        FROM order_items oi
        LEFT JOIN templates t ON oi.product_type = 'template' AND oi.product_id = t.id
        LEFT JOIN tools tl ON oi.product_type = 'tool' AND oi.product_id = tl.id
        WHERE oi.pending_order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        if ($item['product_type'] === 'tool') {
            createToolDelivery($orderId, $item);
        } else {
            createTemplateDelivery($orderId, $item);
        }
    }
}
```

#### Function 2: `createToolDelivery($orderId, $item)`

```php
function createToolDelivery($orderId, $item) {
    $db = getDb();
    
    // Get tool files
    $stmt = $db->prepare("
        SELECT * FROM tool_files 
        WHERE tool_id = ? 
        ORDER BY sort_order ASC
    ");
    $stmt->execute([$item['product_id']]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate download links
    $downloadLinks = [];
    foreach ($files as $file) {
        $downloadLinks[] = generateDownloadLink($file['id'], $orderId);
    }
    
    // Create delivery record
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_link,
            delivery_instructions, delivery_note
        ) VALUES (?, ?, ?, 'tool', ?, 'download', 'immediate', 'ready', ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        json_encode($downloadLinks),
        getToolInstructions($item['product_id']),
        $item['delivery_note']
    ]);
    
    $deliveryId = $db->lastInsertId();
    
    // Queue delivery email
    queueToolDeliveryEmail($deliveryId);
    
    return $deliveryId;
}
```

#### Function 3: `createTemplateDelivery($orderId, $item)`

```php
function createTemplateDelivery($orderId, $item) {
    $db = getDb();
    
    // Create delivery record (pending 24h setup)
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_note,
            template_ready_at
        ) VALUES (?, ?, ?, 'template', ?, 'hosted', 'pending_24h', 'pending', ?,
                  DATETIME('now', '+24 hours'))
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        $item['delivery_note']
    ]);
    
    $deliveryId = $db->lastInsertId();
    
    // Queue "coming in 24 hours" email
    queueTemplateReadyEmail($deliveryId, 'pending');
    
    return $deliveryId;
}
```

### 3.3 Email Delivery System

**Update:** `/includes/email_queue.php`

#### Function: `queueToolDeliveryEmail($deliveryId)`

```php
function queueToolDeliveryEmail($deliveryId) {
    $db = getDb();
    
    // Get delivery and order info
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, po.id as order_id
        FROM deliveries d
        INNER JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) return false;
    
    // Build email
    $subject = "Your {$delivery['product_name']} is Ready! - Order #{$delivery['order_id']}";
    
    $body = "Hi {$delivery['customer_name']},\n\n";
    $body .= "Great news! Your tool is ready for download.\n\n";
    $body .= "📦 Product: {$delivery['product_name']}\n";
    $body .= "📋 Order ID: #{$delivery['order_id']}\n\n";
    
    // Add download links
    $links = json_decode($delivery['delivery_link'], true);
    $body .= "📥 Download Your Files:\n\n";
    foreach ($links as $link) {
        $body .= "• {$link['name']}: {$link['url']}\n";
    }
    
    $body .= "\n" . $delivery['delivery_instructions'] . "\n\n";
    $body .= "Need help? Reply to this email or contact us on WhatsApp.\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    // HTML version
    $htmlBody = buildToolDeliveryEmailHTML($delivery, $links);
    
    // Queue email
    queueEmail(
        $delivery['customer_email'],
        'tools_ready',
        $subject,
        $body,
        $htmlBody,
        $delivery['order_id'],
        $deliveryId
    );
    
    return true;
}
```

#### Function: `queueTemplateReadyEmail($deliveryId, $status)`

```php
function queueTemplateReadyEmail($deliveryId, $status = 'pending') {
    $db = getDb();
    
    $stmt = $db->prepare("
        SELECT d.*, po.customer_email, po.customer_name, po.id as order_id
        FROM deliveries d
        INNER JOIN pending_orders po ON d.pending_order_id = po.id
        WHERE d.id = ?
    ");
    $stmt->execute([$deliveryId]);
    $delivery = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$delivery) return false;
    
    if ($status === 'pending') {
        // "Coming in 24 hours" email
        $subject = "Your Template is Being Prepared - Order #{$delivery['order_id']}";
        
        $body = "Hi {$delivery['customer_name']},\n\n";
        $body .= "Thank you for your order! 🎉\n\n";
        $body .= "📦 Template: {$delivery['product_name']}\n";
        $body .= "📋 Order ID: #{$delivery['order_id']}\n";
        $body .= "⏱️ Ready in: 24 hours\n\n";
        $body .= "We're setting up your template with premium hosting and SSL certificate.\n";
        $body .= "You'll receive another email with your access link when it's ready!\n\n";
        $body .= "Best regards,\nWebDaddy Empire Team";
        
    } else {
        // "Template is ready" email
        $subject = "🎉 Your Template is Ready! - {$delivery['product_name']}";
        
        $body = "Hi {$delivery['customer_name']},\n\n";
        $body .= "Your template is now live and ready to use!\n\n";
        $body .= "🔗 Access URL: {$delivery['hosted_url']}\n";
        $body .= "📧 Login Email: {$delivery['customer_email']}\n";
        $body .= "🔑 Password: (check your email for setup instructions)\n\n";
        $body .= $delivery['delivery_instructions'] . "\n\n";
        $body .= "Need help getting started? We're here for you!\n\n";
        $body .= "Best regards,\nWebDaddy Empire Team";
    }
    
    $htmlBody = buildTemplateEmailHTML($delivery, $status);
    
    queueEmail(
        $delivery['customer_email'],
        $status === 'pending' ? 'template_pending' : 'template_ready',
        $subject,
        $body,
        $htmlBody,
        $delivery['order_id'],
        $deliveryId
    );
    
    return true;
}
```

### 3.4 Download Link Generation

**Update:** `/includes/tool_files.php`

```php
function generateDownloadLink($fileId, $orderId, $expiryDays = 7) {
    $db = getDb();
    
    // Get file info
    $stmt = $db->prepare("SELECT * FROM tool_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) return null;
    
    // Create secure token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));
    
    // Store download token
    $stmt = $db->prepare("
        INSERT INTO download_tokens (file_id, order_id, token, expires_at, max_downloads)
        VALUES (?, ?, ?, ?, 5)
    ");
    $stmt->execute([$fileId, $orderId, $token, $expiresAt]);
    
    // Generate URL
    $downloadUrl = SITE_URL . "/download.php?token={$token}";
    
    return [
        'name' => $file['file_name'],
        'url' => $downloadUrl,
        'expires_at' => $expiresAt,
        'file_type' => $file['file_type']
    ];
}
```

### 3.5 Create Download Handler

**File to Create:** `/download.php`

```php
<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/tool_files.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    die('Invalid download link');
}

// Verify token
$db = getDb();
$stmt = $db->prepare("
    SELECT dt.*, tf.file_path, tf.file_name, tf.mime_type
    FROM download_tokens dt
    INNER JOIN tool_files tf ON dt.file_id = tf.id
    WHERE dt.token = ? AND dt.expires_at > CURRENT_TIMESTAMP
");
$stmt->execute([$token]);
$download = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$download) {
    http_response_code(404);
    die('Download link expired or invalid');
}

// Check download limit
if ($download['download_count'] >= $download['max_downloads']) {
    http_response_code(403);
    die('Download limit exceeded. Please contact support.');
}

// Increment download count
$stmt = $db->prepare("
    UPDATE download_tokens 
    SET download_count = download_count + 1,
        last_downloaded_at = CURRENT_TIMESTAMP
    WHERE id = ?
");
$stmt->execute([$download['id']]);

// Track download
trackDownload($download['file_id'], $download['order_id']);

// Serve file
$filePath = __DIR__ . '/' . $download['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found');
}

header('Content-Type: ' . $download['mime_type']);
header('Content-Disposition: attachment; filename="' . $download['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filePath);
exit;
```

### 3.6 Confirmation Page Updates

**File to Modify:** `/cart-checkout.php`

Update the confirmation section to show delivery information:

```php
<?php
// After order confirmation
if ($confirmedOrderId) {
    // Get deliveries
    $stmt = $db->prepare("
        SELECT * FROM deliveries 
        WHERE pending_order_id = ? 
        ORDER BY product_type ASC, id ASC
    ");
    $stmt->execute([$confirmedOrderId]);
    $deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate by type
    $toolDeliveries = [];
    $templateDeliveries = [];
    
    foreach ($deliveries as $delivery) {
        if ($delivery['product_type'] === 'tool') {
            $toolDeliveries[] = $delivery;
        } else {
            $templateDeliveries[] = $delivery;
        }
    }
    ?>
    
    <div class="delivery-section">
        <h3>📋 Your Deliverables</h3>
        
        <?php if (!empty($toolDeliveries)): ?>
        <div class="tools-delivery mb-4">
            <h4>🔧 Digital Tools (Ready Now)</h4>
            <?php foreach ($toolDeliveries as $tool): ?>
            <div class="delivery-item card mb-3">
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($tool['product_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($tool['delivery_note']); ?></p>
                    
                    <?php
                    $links = json_decode($tool['delivery_link'], true);
                    if ($links):
                    ?>
                    <div class="download-links mt-3">
                        <strong>Download Files:</strong>
                        <ul class="list-unstyled mt-2">
                            <?php foreach ($links as $link): ?>
                            <li class="mb-2">
                                <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                   class="btn btn-primary btn-sm" target="_blank">
                                    📥 <?php echo htmlspecialchars($link['name']); ?>
                                </a>
                                <small class="text-muted">
                                    (Expires: <?php echo date('M d, Y', strtotime($link['expires_at'])); ?>)
                                </small>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info mt-3">
                        ✉️ These files have also been emailed to you at 
                        <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($templateDeliveries)): ?>
        <div class="templates-delivery">
            <h4>🎨 Website Templates (Coming in 24 Hours)</h4>
            <?php foreach ($templateDeliveries as $template): ?>
            <div class="delivery-item card mb-3">
                <div class="card-body">
                    <h5><?php echo htmlspecialchars($template['product_name']); ?></h5>
                    <p class="text-muted"><?php echo htmlspecialchars($template['delivery_note']); ?></p>
                    
                    <?php if ($template['delivery_status'] === 'ready'): ?>
                    <div class="alert alert-success">
                        ✅ Your template is ready!
                        <a href="<?php echo htmlspecialchars($template['hosted_url']); ?>" 
                           class="btn btn-success btn-sm ml-2" target="_blank">
                            Access Template
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        ⏱️ Your template will be ready in approximately 24 hours.
                        We'll email you the access link at 
                        <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php
}
?>
```

### 3.7 New Database Table for Download Tokens

```sql
CREATE TABLE download_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    file_id INTEGER NOT NULL,
    order_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    download_count INTEGER DEFAULT 0,
    max_downloads INTEGER DEFAULT 5,
    expires_at TIMESTAMP NOT NULL,
    last_downloaded_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (file_id) REFERENCES tool_files(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX idx_download_token ON download_tokens(token);
CREATE INDEX idx_download_expires ON download_tokens(expires_at);
```

### 3.8 Phase 3 File Structure

```
webdaddy-empire/
├── admin/
│   ├── tool-files.php (NEW - file management)
│   └── includes/
│       └── tool-file-upload-handler.php (NEW)
├── includes/
│   ├── delivery.php (UPDATED - add all delivery functions)
│   ├── email_queue.php (UPDATED - add email templates)
│   └── tool_files.php (UPDATED - add download functions)
├── uploads/
│   └── tools/
│       └── files/ (NEW directory for tool files)
├── download.php (NEW - secure download handler)
└── cart-checkout.php (UPDATED - show delivery info)
```

### 3.9 Phase 3 Checklist

- [ ] Create `download_tokens` database table
- [ ] Create `/admin/tool-files.php` page
- [ ] Create file upload handler
- [ ] Update `/includes/delivery.php` with all functions
- [ ] Update `/includes/email_queue.php` with email templates
- [ ] Update `/includes/tool_files.php` with download functions
- [ ] Create `/download.php` secure handler
- [ ] Update `/cart-checkout.php` confirmation section
- [ ] Test tool file upload
- [ ] Test download link generation
- [ ] Test email delivery

### 3.10 Testing Phase 3

1. **Upload Tool Files:**
   - Go to `/admin/tool-files.php`
   - Select a tool
   - Upload a ZIP file
   - Verify file appears in list

2. **Test Tool Delivery:**
   - Create test order with a tool
   - Mark as paid (or use Paystack test payment)
   - Check email received
   - Click download link
   - File should download successfully

3. **Test Template Delivery:**
   - Create test order with a template
   - Mark as paid
   - Check "coming in 24 hours" email
   - Manually mark template as ready in admin
   - Check "template ready" email
   - Access link should work

---

## 👨‍💼 PHASE 4: ADMIN DASHBOARD & MANAGEMENT

**Duration:** 1-2 hours  
**Complexity:** Medium  
**Goal:** Give admins full control over payments, deliveries, and order management

### 4.1 Admin Orders Page Updates

**File to Modify:** `/admin/orders.php`

#### Add Payment Status Column

```php
// In the orders table, add payment method and status columns
<table class="table">
    <thead>
        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Products</th>
            <th>Amount</th>
            <th>Payment Method</th>
            <th>Payment Status</th>
            <th>Delivery Status</th>
            <th>Date</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($orders as $order): ?>
        <tr>
            <td>#<?php echo $order['id']; ?></td>
            <td>
                <?php echo htmlspecialchars($order['customer_name']); ?><br>
                <small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
            </td>
            <td>
                <?php echo $order['order_type']; ?>
                <small class="text-muted">(<?php echo $order['item_count']; ?> items)</small>
            </td>
            <td>₦<?php echo number_format($order['total_amount'], 2); ?></td>
            <td>
                <?php if ($order['payment_method'] === 'paystack'): ?>
                    <span class="badge bg-primary">💳 Paystack</span>
                <?php else: ?>
                    <span class="badge bg-secondary">💰 Manual</span>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($order['status'] === 'paid'): ?>
                    <span class="badge bg-success">✅ Paid</span>
                <?php else: ?>
                    <span class="badge bg-warning">⏳ Pending</span>
                <?php endif; ?>
            </td>
            <td>
                <?php
                $deliveryBadge = getDeliveryStatusBadge($order['delivery_status']);
                echo $deliveryBadge;
                ?>
            </td>
            <td><?php echo date('M d, Y', strtotime($order['created_at'])); ?></td>
            <td>
                <button class="btn btn-sm btn-info" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                    View
                </button>
                <?php if ($order['status'] === 'pending'): ?>
                <button class="btn btn-sm btn-success" onclick="markAsPaid(<?php echo $order['id']; ?>)">
                    Mark Paid
                </button>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
```

### 4.2 Create Delivery Management Page

**File to Create:** `/admin/deliveries.php`

```php
<?php
$pageTitle = 'Delivery Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get all deliveries with order info
$stmt = $db->query("
    SELECT d.*, 
           po.customer_name, 
           po.customer_email,
           po.id as order_id
    FROM deliveries d
    INNER JOIN pending_orders po ON d.pending_order_id = po.id
    ORDER BY d.created_at DESC
");
$deliveries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate by status
$pendingDeliveries = array_filter($deliveries, function($d) {
    return $d['delivery_status'] === 'pending';
});

$inProgressDeliveries = array_filter($deliveries, function($d) {
    return $d['delivery_status'] === 'in_progress';
});

$completedDeliveries = array_filter($deliveries, function($d) {
    return in_array($d['delivery_status'], ['sent', 'delivered']);
});

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">📦 Delivery Management</h1>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>⏳ Pending Deliveries</h5>
                    <h2><?php echo count($pendingDeliveries); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>🔄 In Progress</h5>
                    <h2><?php echo count($inProgressDeliveries); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5>✅ Completed</h5>
                    <h2><?php echo count($completedDeliveries); ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabs -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#pending">
                Pending (<?php echo count($pendingDeliveries); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#in-progress">
                In Progress (<?php echo count($inProgressDeliveries); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#completed">
                Completed (<?php echo count($completedDeliveries); ?>)
            </a>
        </li>
    </ul>
    
    <div class="tab-content">
        <!-- Pending Deliveries -->
        <div id="pending" class="tab-pane fade show active">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Delivery Type</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingDeliveries as $delivery): ?>
                    <tr>
                        <td>#<?php echo $delivery['id']; ?></td>
                        <td>#<?php echo $delivery['order_id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($delivery['customer_name']); ?><br>
                            <small><?php echo htmlspecialchars($delivery['customer_email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($delivery['product_name']); ?></td>
                        <td>
                            <?php if ($delivery['product_type'] === 'tool'): ?>
                                <span class="badge bg-info">🔧 Tool</span>
                            <?php else: ?>
                                <span class="badge bg-primary">🎨 Template</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($delivery['delivery_type'] === 'immediate'): ?>
                                Immediate
                            <?php else: ?>
                                24 Hours
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($delivery['created_at'])); ?></td>
                        <td>
                            <?php if ($delivery['product_type'] === 'tool'): ?>
                                <button class="btn btn-sm btn-success" 
                                        onclick="sendToolDelivery(<?php echo $delivery['id']; ?>)">
                                    Send Now
                                </button>
                            <?php else: ?>
                                <button class="btn btn-sm btn-primary" 
                                        onclick="setupTemplate(<?php echo $delivery['id']; ?>)">
                                    Setup Template
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- In Progress Deliveries -->
        <div id="in-progress" class="tab-pane fade">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Ready At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inProgressDeliveries as $delivery): ?>
                    <tr>
                        <td>#<?php echo $delivery['id']; ?></td>
                        <td>#<?php echo $delivery['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($delivery['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($delivery['product_name']); ?></td>
                        <td>
                            <?php if ($delivery['product_type'] === 'tool'): ?>
                                <span class="badge bg-info">🔧 Tool</span>
                            <?php else: ?>
                                <span class="badge bg-primary">🎨 Template</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($delivery['template_ready_at']): ?>
                                <?php echo date('M d, Y g:i A', strtotime($delivery['template_ready_at'])); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success" 
                                    onclick="markAsReady(<?php echo $delivery['id']; ?>)">
                                Mark as Ready
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Completed Deliveries -->
        <div id="completed" class="tab-pane fade">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Delivered At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completedDeliveries as $delivery): ?>
                    <tr>
                        <td>#<?php echo $delivery['id']; ?></td>
                        <td>#<?php echo $delivery['order_id']; ?></td>
                        <td><?php echo htmlspecialchars($delivery['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($delivery['product_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($delivery['delivered_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" 
                                    onclick="resendDelivery(<?php echo $delivery['id']; ?>)">
                                Resend Email
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function sendToolDelivery(deliveryId) {
    if (!confirm('Send tool delivery email now?')) return;
    
    fetch('/admin/api/send-delivery.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delivery_id: deliveryId, type: 'tool' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Delivery email sent!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function setupTemplate(deliveryId) {
    // Open modal for template setup
    window.location.href = '/admin/template-setup.php?delivery_id=' + deliveryId;
}

function markAsReady(deliveryId) {
    if (!confirm('Mark this delivery as ready and send customer notification?')) return;
    
    fetch('/admin/api/mark-delivery-ready.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delivery_id: deliveryId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Delivery marked as ready and email sent!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function resendDelivery(deliveryId) {
    if (!confirm('Resend delivery email to customer?')) return;
    
    fetch('/admin/api/resend-delivery.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delivery_id: deliveryId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Email resent successfully!');
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

### 4.3 Create Payment Logs Page

**File to Create:** `/admin/payment-logs.php`

Shows all payment events for debugging:
- Payment initializations
- Webhook events
- Verification attempts
- Errors and failures

Structure:
```
Payment Logs
├── Filter by Date Range
├── Filter by Event Type
├── Filter by Status
└── Logs Table
    ├── Timestamp
    ├── Event Type
    ├── Order ID
    ├── Amount
    ├── Status
    ├── Provider (Paystack/Manual)
    ├── Details (JSON)
    └── Actions (View Full Details)
```

### 4.4 Add Delivery Management to Admin Menu

**File to Modify:** `/admin/includes/header.php`

Add menu item:

```html
<li class="nav-item">
    <a class="nav-link" href="/admin/deliveries.php">
        <i class="bi bi-box-seam"></i> Deliveries
        <?php if ($pendingDeliveriesCount > 0): ?>
        <span class="badge bg-warning"><?php echo $pendingDeliveriesCount; ?></span>
        <?php endif; ?>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="/admin/payment-logs.php">
        <i class="bi bi-receipt"></i> Payment Logs
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="/admin/tool-files.php">
        <i class="bi bi-file-earmark-zip"></i> Tool Files
    </a>
</li>
```

### 4.5 Create Admin API Endpoints

**Files to Create:**

1. `/admin/api/send-delivery.php` - Manually trigger delivery
2. `/admin/api/mark-delivery-ready.php` - Mark template as ready
3. `/admin/api/resend-delivery.php` - Resend delivery email

### 4.6 Update Dashboard with Payment Stats

**File to Modify:** `/admin/index.php`

Add cards for:
- Paystack Payments (Count & Total)
- Manual Payments (Count & Total)
- Pending Deliveries
- Failed Deliveries

### 4.7 Phase 4 File Structure

```
webdaddy-empire/
├── admin/
│   ├── deliveries.php (NEW)
│   ├── payment-logs.php (NEW)
│   ├── tool-files.php (from Phase 3)
│   ├── template-setup.php (NEW - for manual template setup)
│   ├── orders.php (UPDATED - add payment columns)
│   ├── index.php (UPDATED - add payment stats)
│   ├── api/
│   │   ├── send-delivery.php (NEW)
│   │   ├── mark-delivery-ready.php (NEW)
│   │   └── resend-delivery.php (NEW)
│   └── includes/
│       └── header.php (UPDATED - add menu items)
```

### 4.8 Phase 4 Checklist

- [ ] Create `/admin/deliveries.php`
- [ ] Create `/admin/payment-logs.php`
- [ ] Create `/admin/template-setup.php`
- [ ] Update `/admin/orders.php` with payment columns
- [ ] Update `/admin/index.php` with payment stats
- [ ] Create admin API endpoints (3 files)
- [ ] Update admin menu in header
- [ ] Test manual delivery send
- [ ] Test mark as ready
- [ ] Test resend email

### 4.9 Testing Phase 4

1. Login to admin panel
2. Check "Deliveries" page loads
3. Create test order
4. See order appear in "Pending" tab
5. Click "Send Now" for tool → Email sent
6. Click "Setup Template" → Setup page opens
7. Mark template as ready → Email sent
8. Check "Payment Logs" page → See all events
9. Test resend email functionality

---

## ✅ PHASE 5: TESTING, POLISH & LAUNCH

**Duration:** 1-2 hours  
**Complexity:** Medium  
**Goal:** Test everything, fix bugs, polish UI, and launch to production

### 5.1 Complete Testing Checklist

#### Frontend Testing

**Checkout Page:**
- [ ] Tab switching works (Manual ↔ Paystack)
- [ ] Order summary displays correctly
- [ ] Affiliate code applies discount
- [ ] Cart items display properly
- [ ] Validation errors show clearly
- [ ] Mobile responsive design

**Paystack Payment Flow:**
- [ ] Click "Pay Now" → Paystack popup opens
- [ ] Test card works: `4084 0840 8408 4081`
- [ ] Success → Redirects to confirmation
- [ ] Failed payment → Error message shows
- [ ] Close popup → Button resets properly

**Confirmation Page:**
- [ ] Order details display
- [ ] Tool download links work
- [ ] Template "coming in 24h" message shows
- [ ] Delivery instructions clear
- [ ] Email sent notification shows
- [ ] Mobile responsive

#### Backend Testing

**Database:**
- [ ] All tables created successfully
- [ ] Foreign keys working
- [ ] Indexes created
- [ ] Data inserting correctly
- [ ] Queries running efficiently

**Payment Processing:**
- [ ] Paystack initialization works
- [ ] Payment verification succeeds
- [ ] Webhook handling correct
- [ ] Payment logs recording
- [ ] Status updates properly

**Delivery System:**
- [ ] Tool delivery creates records
- [ ] Download links generate
- [ ] Emails queue properly
- [ ] Template delivery creates records
- [ ] 24h scheduling works

**Email System:**
- [ ] Queue processing works
- [ ] Emails sending successfully
- [ ] HTML formatting correct
- [ ] Links in emails work
- [ ] Retry logic functional

#### Admin Panel Testing

**Orders Management:**
- [ ] Payment method shows correctly
- [ ] Delivery status displays
- [ ] Manual mark as paid works
- [ ] Order details modal opens
- [ ] CSV export works

**Deliveries Page:**
- [ ] Pending deliveries show
- [ ] Send delivery works
- [ ] Mark as ready works
- [ ] Resend email works
- [ ] Status updates correctly

**Tool Files:**
- [ ] File upload works
- [ ] File types validated
- [ ] Files stored correctly
- [ ] File list displays
- [ ] Delete file works

**Payment Logs:**
- [ ] All events logging
- [ ] Filter by date works
- [ ] Filter by type works
- [ ] JSON details viewable
- [ ] Pagination works

### 5.2 Security Checks

#### Critical Security Items

- [ ] Environment variables NOT in code
- [ ] API keys NOT committed to git
- [ ] Paystack signature verification working
- [ ] SQL injection prevention (prepared statements)
- [ ] XSS prevention (htmlspecialchars on output)
- [ ] CSRF tokens on forms
- [ ] File upload validation
- [ ] Download token expiration working
- [ ] Admin routes protected
- [ ] Session security configured

#### File Permissions

```bash
chmod 755 /uploads/tools/files/
chmod 644 /uploads/tools/files/*.zip
chmod 600 .env
```

### 5.3 Performance Optimization

**Database Optimization:**
```sql
-- Add indexes for better performance
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_deliveries_status ON deliveries(delivery_status);
CREATE INDEX idx_email_queue_status ON email_queue(status);
CREATE INDEX idx_payment_logs_created ON payment_logs(created_at);

-- Vacuum database
VACUUM;

-- Analyze tables
ANALYZE;
```

**Caching:**
- [ ] Enable OPcache for PHP
- [ ] Cache Paystack public key
- [ ] Cache tool files list
- [ ] Cache delivery counts

### 5.4 UI/UX Polish

**Payment Tabs:**
- [ ] Smooth tab transitions
- [ ] Clear active state
- [ ] Icons consistent
- [ ] Colors match brand
- [ ] Mobile-friendly tabs

**Loading States:**
- [ ] Spinner on "Pay Now"
- [ ] Disabled state during processing
- [ ] Progress indicators
- [ ] Loading messages clear

**Error Handling:**
- [ ] Friendly error messages
- [ ] Contact support info in errors
- [ ] Recovery instructions
- [ ] Log errors for debugging

**Success States:**
- [ ] Celebration on confirmation
- [ ] Clear next steps
- [ ] Download buttons prominent
- [ ] Email confirmation message

### 5.5 Documentation

**Create Admin Guide:**

**File to Create:** `/admin/docs/payment-delivery-guide.md`

Include:
- How to view payments
- How to manage deliveries
- How to upload tool files
- How to setup templates
- How to handle issues
- How to read payment logs
- Troubleshooting common problems

**Update README.md:**

Add sections:
- Paystack Integration Setup
- Environment Variables Required
- Webhook Configuration
- Testing Payment Flow
- Delivery System Overview

### 5.6 Webhook Setup in Paystack Dashboard

**Instructions for User:**

1. Login to Paystack Dashboard
2. Go to Settings → API Keys & Webhooks
3. Click "Configure Webhook"
4. Enter Webhook URL: `https://yoursite.repl.co/api/paystack-webhook.php`
5. Test webhook → Should receive "success" response
6. Save configuration

### 5.7 Environment Variables Checklist

**Verify all required variables set:**

```bash
# Check environment variables
echo $PAYSTACK_SECRET_KEY
echo $PAYSTACK_PUBLIC_KEY
echo $PAYSTACK_MODE
echo $BUSINESS_EMAIL
echo $SITE_URL
```

All should output values (not blank).

### 5.8 Email Queue Cron Job

**File to Create:** `/cron/process-emails.php`

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_queue.php';

// Process pending emails
processEmailQueue();

// Retry failed emails
retryFailedEmails();

echo "Email queue processed at " . date('Y-m-d H:i:s') . "\n";
```

**Setup cron job** (in Replit or server):
```bash
*/5 * * * * php /path/to/cron/process-emails.php
```

### 5.9 Launch Checklist

**Pre-Launch:**
- [ ] Switch to Live Mode (if ready)
- [ ] Update PAYSTACK_MODE=live
- [ ] Use live API keys
- [ ] Test with real card (small amount)
- [ ] Verify webhook working in live mode
- [ ] Check all emails sending
- [ ] Verify download links work
- [ ] Test full customer journey

**Launch Day:**
- [ ] Monitor payment logs
- [ ] Check email queue processing
- [ ] Watch for errors
- [ ] Test customer support flow
- [ ] Verify affiliate tracking still works
- [ ] Check mobile experience

**Post-Launch:**
- [ ] Review first 10 orders
- [ ] Fix any reported issues
- [ ] Optimize based on usage
- [ ] Collect customer feedback
- [ ] Document lessons learned

### 5.10 Rollback Plan

**If something goes wrong:**

1. **Disable Paystack tab temporarily**
   - Comment out Paystack tab in `/cart-checkout.php`
   - Customers fall back to manual payment

2. **Check logs**
   - Review `/admin/payment-logs.php`
   - Check server error logs
   - Review email queue failures

3. **Fix and re-test**
   - Fix identified issue
   - Test in test mode
   - Re-enable Paystack tab

4. **Communication**
   - Notify customers of temporary issue
   - Provide manual payment alternative
   - Update status page if you have one

### 5.11 Phase 5 Deliverables

**Files Created:**
- `/admin/docs/payment-delivery-guide.md`
- `/cron/process-emails.php`
- Updated README.md

**Tasks Completed:**
- All testing complete
- Security verified
- Performance optimized
- Documentation written
- Cron job setup
- Webhook configured
- Launch checklist executed

### 5.12 Success Metrics

**Track These After Launch:**
- Paystack payment success rate
- Average delivery time
- Email delivery rate
- Customer satisfaction
- Support ticket volume
- Revenue from automatic payments
- Conversion rate improvement

---

## 📁 FILE STRUCTURE OVERVIEW

### Complete Project Structure After All 5 Phases

```
webdaddy-empire/
├── admin/
│   ├── index.php (UPDATED)
│   ├── orders.php (UPDATED)
│   ├── deliveries.php (NEW)
│   ├── payment-logs.php (NEW)
│   ├── tool-files.php (NEW)
│   ├── template-setup.php (NEW)
│   ├── docs/
│   │   └── payment-delivery-guide.md (NEW)
│   ├── api/
│   │   ├── send-delivery.php (NEW)
│   │   ├── mark-delivery-ready.php (NEW)
│   │   └── resend-delivery.php (NEW)
│   └── includes/
│       ├── header.php (UPDATED)
│       └── auth.php (existing)
├── api/
│   ├── paystack-initialize.php (NEW)
│   ├── paystack-verify.php (NEW)
│   └── paystack-webhook.php (NEW)
├── assets/
│   ├── css/
│   │   └── (existing styles)
│   └── js/
│       ├── paystack-payment.js (NEW)
│       └── (existing scripts)
├── includes/
│   ├── config.php (UPDATED)
│   ├── db.php (existing)
│   ├── paystack.php (NEW)
│   ├── delivery.php (NEW)
│   ├── email_queue.php (NEW)
│   ├── tool_files.php (NEW)
│   └── (other existing files)
├── database/
│   ├── migrations/
│   │   ├── 001_add_payments_table.sql (NEW)
│   │   ├── 002_add_deliveries_table.sql (NEW)
│   │   ├── 003_add_tool_files_table.sql (NEW)
│   │   ├── 004_add_email_queue_table.sql (NEW)
│   │   ├── 005_add_payment_logs_table.sql (NEW)
│   │   ├── 006_update_existing_tables.sql (NEW)
│   │   └── 007_add_download_tokens_table.sql (NEW)
│   └── backups/
├── cron/
│   └── process-emails.php (NEW)
├── uploads/
│   └── tools/
│       └── files/ (NEW - tool deliverables)
├── cart-checkout.php (UPDATED)
├── download.php (NEW)
├── .env (NEW/UPDATED)
├── README.md (UPDATED)
└── PAYSTACK_IMPLEMENTATION_5_PHASES.md (THIS FILE)
```

### Database Tables Summary

**New Tables (7):**
1. `payments` - Payment transactions
2. `deliveries` - Product deliveries
3. `tool_files` - Tool attachments
4. `email_queue` - Email queue
5. `payment_logs` - Payment events
6. `download_tokens` - Secure download links
7. `template_hosting` - Template hosting records (optional)

**Updated Tables (3):**
1. `pending_orders` - Add payment fields
2. `tools` - Add delivery fields
3. `templates` - Add delivery fields

### Key Files by Function

**Payment Processing:**
- `/api/paystack-initialize.php`
- `/api/paystack-verify.php`
- `/api/paystack-webhook.php`
- `/includes/paystack.php`

**Delivery System:**
- `/includes/delivery.php`
- `/includes/email_queue.php`
- `/includes/tool_files.php`
- `/download.php`

**Admin Management:**
- `/admin/deliveries.php`
- `/admin/payment-logs.php`
- `/admin/tool-files.php`
- `/admin/template-setup.php`

**Customer Experience:**
- `/cart-checkout.php`
- `/assets/js/paystack-payment.js`

---

## 🎯 FINAL NOTES

### Development Best Practices

1. **Always Test in Test Mode First**
   - Use test API keys
   - Use test cards
   - Verify webhook works
   - Check all emails send

2. **Code Quality**
   - Use prepared statements for all SQL
   - Validate all user inputs
   - Escape all outputs
   - Handle errors gracefully
   - Log important events

3. **Security**
   - Never commit API keys to git
   - Use environment variables
   - Verify webhook signatures
   - Implement rate limiting
   - Monitor for suspicious activity

4. **Performance**
   - Use indexes on database tables
   - Cache frequently accessed data
   - Optimize email queue processing
   - Monitor server resource usage
   - Implement lazy loading where possible

### Support & Maintenance

**Regular Tasks:**
- Monitor payment logs daily
- Check email queue for failures
- Review delivery statuses
- Update tool files as needed
- Backup database regularly

**Monthly Tasks:**
- Review payment success rates
- Analyze customer feedback
- Update documentation
- Security audit
- Performance review

### Future Enhancements

**Potential Phase 6 (Optional):**
- Customer accounts dashboard
- Order history for customers
- Automatic template setup (no manual 24h wait)
- Multiple payment methods (Flutterwave, etc.)
- Subscription products
- Automated refunds
- Customer reviews system
- Advanced analytics

---

**End of 5-Phase Implementation Plan**

Once you provide your Paystack credentials, I'll implement all 5 phases systematically. Each phase builds on the previous one, ensuring a stable and functional system at every step.

Ready to start Phase 1 when you are! 🚀
