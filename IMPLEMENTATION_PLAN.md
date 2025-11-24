# WebDaddy Empire - Paystack Payment & Delivery Implementation
**5-Phase Buildable Implementation Plan**

---

## 📋 OVERVIEW

This document outlines the complete implementation of automatic payment processing via Paystack and an advanced product delivery system for WebDaddy Empire. The implementation is structured into **5 buildable phases** that can be executed step-by-step.

### What We're Building

- **Dual Payment System**: Manual (WhatsApp/Bank Transfer) + Automatic (Paystack Card Payment)
- **Automatic Tool Delivery**: Digital tools delivered instantly via download links
- **Template Hosting System**: 24-hour template setup with hosted domains
- **Email Delivery System**: Reliable email queue with retry logic
- **Admin Management**: Complete dashboard for managing payments and deliveries

### Current vs New System

| Feature | Current | After Implementation |
|---------|---------|---------------------|
| Payment Method | WhatsApp + Manual Bank Transfer | WhatsApp OR Paystack Cards |
| Payment Verification | Manual by Admin | Automatic via Webhook |
| Tool Delivery | Manual by Admin | Instant Automatic Delivery |
| Template Setup | Manual | Tracked 24h Process |
| Customer Experience | Wait for Admin | Instant Confirmation |

---

## 🔑 PREREQUISITES - INFORMATION YOU NEED

Before starting Phase 1, you MUST provide:

### 1. Paystack API Keys
- **Secret Key**: `sk_test_xxxxx` or `sk_live_xxxxx`
- **Public Key**: `pk_test_xxxxx` or `pk_live_xxxxx`
- **Where to find**: Paystack Dashboard → Settings → API Keys & Webhooks

### 2. Operating Mode
- **Test Mode**: For development/testing (recommended to start)
- **Live Mode**: For real customer payments (switch later)

### 3. Business Email
- Your official business email for sending notifications
- Example: `support@webdaddyempire.com`

### 4. Paystack Account Setup
- Bank account verified in Paystack
- Business name configured
- Webhook URL will be provided after Phase 2

---

## 🏗️ PHASE 1: DATABASE & FOUNDATION

**Duration**: 30-45 minutes  
**Goal**: Create database tables, core PHP files, and environment setup

### 1.1 Database Schema - New Tables

#### Table 1: `payments`
Tracks all payment transactions (manual and Paystack)

```sql
CREATE TABLE IF NOT EXISTS payments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pending_order_id INTEGER NOT NULL UNIQUE,
  payment_method TEXT NOT NULL CHECK(payment_method IN ('manual', 'paystack')),
  
  -- Amount & Currency
  amount_requested REAL NOT NULL,
  amount_paid REAL,
  currency TEXT DEFAULT 'NGN',
  
  -- Payment Status
  status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'completed', 'failed', 'cancelled', 'refunded')),
  payment_verified_at TIMESTAMP NULL,
  
  -- Paystack Specific
  paystack_reference TEXT UNIQUE,
  paystack_access_code TEXT,
  paystack_authorization_url TEXT,
  paystack_customer_code TEXT,
  paystack_response TEXT, -- JSON stored as TEXT
  
  -- Manual Payment Specific
  manual_verified_by INTEGER NULL,
  manual_verified_at TIMESTAMP NULL,
  payment_note TEXT,
  
  -- Tracking
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX idx_payments_order ON payments(pending_order_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_reference ON payments(paystack_reference);
```

#### Table 2: `deliveries`
Tracks individual product deliveries within orders

```sql
CREATE TABLE IF NOT EXISTS deliveries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pending_order_id INTEGER NOT NULL,
  order_item_id INTEGER NULL,
  product_id INTEGER NOT NULL,
  product_type TEXT NOT NULL CHECK(product_type IN ('template', 'tool')),
  product_name TEXT,
  
  -- Delivery Configuration
  delivery_method TEXT NOT NULL CHECK(delivery_method IN ('email', 'download', 'hosted', 'manual')),
  delivery_type TEXT NOT NULL CHECK(delivery_type IN ('immediate', 'pending_24h', 'manual')),
  delivery_status TEXT DEFAULT 'pending' CHECK(delivery_status IN ('pending', 'in_progress', 'ready', 'sent', 'delivered', 'failed')),
  
  -- Delivery Content & Links
  delivery_link TEXT, -- JSON stored as TEXT
  delivery_instructions TEXT,
  delivery_note TEXT,
  file_path TEXT,
  hosted_domain TEXT,
  hosted_url TEXT,
  
  -- For Templates Only
  template_ready_at TIMESTAMP NULL,
  template_expires_at TIMESTAMP NULL,
  
  -- Delivery Tracking
  email_sent_at TIMESTAMP NULL,
  sent_to_email TEXT,
  delivered_at TIMESTAMP NULL,
  delivery_attempts INTEGER DEFAULT 0,
  last_attempt_at TIMESTAMP NULL,
  last_error TEXT,
  
  -- Admin Notes
  admin_notes TEXT,
  prepared_by INTEGER NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX idx_deliveries_order ON deliveries(pending_order_id);
CREATE INDEX idx_deliveries_status ON deliveries(delivery_status);
CREATE INDEX idx_deliveries_type ON deliveries(product_type);
CREATE INDEX idx_deliveries_ready ON deliveries(template_ready_at);
```

#### Table 3: `tool_files`
Stores downloadable files for digital tools

```sql
CREATE TABLE IF NOT EXISTS tool_files (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  tool_id INTEGER NOT NULL,
  
  -- File Details
  file_name TEXT NOT NULL,
  file_path TEXT NOT NULL,
  file_type TEXT NOT NULL CHECK(file_type IN ('attachment', 'zip_archive', 'code', 'text_instructions', 'image', 'access_key', 'link', 'video')),
  file_description TEXT,
  
  -- File Information
  file_size INTEGER,
  mime_type TEXT,
  download_count INTEGER DEFAULT 0,
  
  -- Access Control
  is_public INTEGER DEFAULT 0,
  access_expires_after_days INTEGER DEFAULT 30,
  require_password INTEGER DEFAULT 0,
  
  -- Ordering
  sort_order INTEGER DEFAULT 0,
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
);

CREATE INDEX idx_tool_files_tool ON tool_files(tool_id);
CREATE INDEX idx_tool_files_type ON tool_files(file_type);
```

#### Table 4: `download_tokens`
Secure, time-limited download links

```sql
CREATE TABLE IF NOT EXISTS download_tokens (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  file_id INTEGER NOT NULL,
  pending_order_id INTEGER NOT NULL,
  token TEXT NOT NULL UNIQUE,
  download_count INTEGER DEFAULT 0,
  max_downloads INTEGER DEFAULT 5,
  expires_at TIMESTAMP NOT NULL,
  last_downloaded_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (file_id) REFERENCES tool_files(id) ON DELETE CASCADE,
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE
);

CREATE INDEX idx_download_tokens_token ON download_tokens(token);
CREATE INDEX idx_download_tokens_expires ON download_tokens(expires_at);
```

#### Table 5: `email_queue`
Reliable email queue with retry logic

```sql
CREATE TABLE IF NOT EXISTS email_queue (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  recipient_email TEXT NOT NULL,
  email_type TEXT NOT NULL CHECK(email_type IN ('payment_received', 'tools_ready', 'template_ready', 'delivery_link', 'payment_verified', 'order_confirmation')),
  
  -- Related Records
  pending_order_id INTEGER,
  delivery_id INTEGER,
  
  -- Email Content
  subject TEXT NOT NULL,
  body TEXT NOT NULL,
  html_body TEXT,
  
  -- Status Tracking
  status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'sent', 'failed', 'bounced', 'retry')),
  attempts INTEGER DEFAULT 0,
  max_attempts INTEGER DEFAULT 3,
  last_error TEXT,
  
  -- Scheduling
  scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (delivery_id) REFERENCES deliveries(id) ON DELETE CASCADE
);

CREATE INDEX idx_email_queue_status ON email_queue(status);
CREATE INDEX idx_email_queue_type ON email_queue(email_type);
CREATE INDEX idx_email_queue_scheduled ON email_queue(scheduled_at);
```

#### Table 6: `payment_logs`
Complete audit trail of all payment events

```sql
CREATE TABLE IF NOT EXISTS payment_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  pending_order_id INTEGER,
  payment_id INTEGER,
  
  -- Event Details
  event_type TEXT,
  provider TEXT DEFAULT 'system' CHECK(provider IN ('paystack', 'manual', 'system')),
  status TEXT,
  amount REAL,
  
  -- Data
  request_data TEXT, -- JSON stored as TEXT
  response_data TEXT, -- JSON stored as TEXT
  error_message TEXT,
  
  -- Client Info
  ip_address TEXT,
  user_agent TEXT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (pending_order_id) REFERENCES pending_orders(id) ON DELETE CASCADE,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
);

CREATE INDEX idx_payment_logs_order ON payment_logs(pending_order_id);
CREATE INDEX idx_payment_logs_event ON payment_logs(event_type);
```

### 1.2 Modify Existing Tables

#### Update `pending_orders` table

```sql
ALTER TABLE pending_orders ADD COLUMN payment_method TEXT DEFAULT 'manual';
ALTER TABLE pending_orders ADD COLUMN payment_verified_at TIMESTAMP NULL;
ALTER TABLE pending_orders ADD COLUMN delivery_status TEXT DEFAULT 'pending';
ALTER TABLE pending_orders ADD COLUMN email_verified INTEGER DEFAULT 0;
ALTER TABLE pending_orders ADD COLUMN paystack_payment_id TEXT;
```

#### Update `tools` table

```sql
ALTER TABLE tools ADD COLUMN delivery_type TEXT DEFAULT 'both';
ALTER TABLE tools ADD COLUMN has_attached_files INTEGER DEFAULT 0;
ALTER TABLE tools ADD COLUMN requires_email INTEGER DEFAULT 1;
ALTER TABLE tools ADD COLUMN email_subject TEXT;
ALTER TABLE tools ADD COLUMN email_instructions TEXT;
ALTER TABLE tools ADD COLUMN delivery_note TEXT;
ALTER TABLE tools ADD COLUMN delivery_description TEXT;
ALTER TABLE tools ADD COLUMN total_files INTEGER DEFAULT 0;
```

#### Update `templates` table

```sql
ALTER TABLE templates ADD COLUMN delivery_type TEXT DEFAULT 'hosted_domain';
ALTER TABLE templates ADD COLUMN requires_email INTEGER DEFAULT 1;
ALTER TABLE templates ADD COLUMN delivery_wait_hours INTEGER DEFAULT 24;
ALTER TABLE templates ADD COLUMN delivery_note TEXT;
ALTER TABLE templates ADD COLUMN delivery_description TEXT;
ALTER TABLE templates ADD COLUMN domain_template TEXT;
```

### 1.3 Create Core PHP Files

#### File: `/includes/paystack.php`
Core Paystack API integration functions

```php
<?php
/**
 * Paystack Integration
 * Handles payment initialization, verification, and webhook processing
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Initialize a payment with Paystack
 */
function initializePayment($orderData) {
    $url = "https://api.paystack.co/transaction/initialize";
    
    $fields = [
        'email' => $orderData['email'],
        'amount' => $orderData['amount'] * 100, // Convert to kobo
        'currency' => $orderData['currency'] ?? 'NGN',
        'reference' => generatePaymentReference(),
        'callback_url' => $orderData['callback_url'] ?? SITE_URL . '/cart-checkout.php',
        'metadata' => [
            'order_id' => $orderData['order_id'],
            'customer_name' => $orderData['customer_name'] ?? '',
            'custom_fields' => []
        ]
    ];
    
    $response = paystackApiCall($url, $fields);
    
    if ($response['status']) {
        // Store payment record
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO payments (
                pending_order_id, payment_method, amount_requested, currency,
                paystack_reference, paystack_access_code, paystack_authorization_url
            ) VALUES (?, 'paystack', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $orderData['order_id'],
            $orderData['amount'],
            $fields['currency'],
            $fields['reference'],
            $response['data']['access_code'],
            $response['data']['authorization_url']
        ]);
        
        // Log event
        logPaymentEvent('initialize', 'paystack', 'success', $orderData['order_id'], null, $fields, $response);
        
        return [
            'success' => true,
            'reference' => $fields['reference'],
            'access_code' => $response['data']['access_code'],
            'authorization_url' => $response['data']['authorization_url']
        ];
    }
    
    // Log failure
    logPaymentEvent('initialize', 'paystack', 'failed', $orderData['order_id'], null, $fields, $response);
    
    return [
        'success' => false,
        'message' => $response['message'] ?? 'Payment initialization failed'
    ];
}

/**
 * Verify a payment with Paystack
 */
function verifyPayment($reference) {
    $url = "https://api.paystack.co/transaction/verify/" . $reference;
    
    $response = paystackApiCall($url, null, 'GET');
    
    if ($response['status'] && $response['data']['status'] === 'success') {
        // Update payment record
        $db = getDb();
        $stmt = $db->prepare("
            UPDATE payments 
            SET status = 'completed',
                amount_paid = ?,
                paystack_response = ?,
                payment_verified_at = CURRENT_TIMESTAMP
            WHERE paystack_reference = ?
        ");
        $stmt->execute([
            $response['data']['amount'] / 100, // Convert from kobo
            json_encode($response['data']),
            $reference
        ]);
        
        // Get payment details
        $payment = getPaymentByReference($reference);
        
        // Log event
        logPaymentEvent('verify', 'paystack', 'success', $payment['pending_order_id'], $payment['id'], null, $response);
        
        return [
            'success' => true,
            'order_id' => $payment['pending_order_id'],
            'amount' => $response['data']['amount'] / 100
        ];
    }
    
    // Log failure
    logPaymentEvent('verify', 'paystack', 'failed', null, null, ['reference' => $reference], $response);
    
    return [
        'success' => false,
        'message' => $response['message'] ?? 'Payment verification failed'
    ];
}

/**
 * Make API call to Paystack
 */
function paystackApiCall($url, $fields = null, $method = 'POST') {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json'
    ]);
    
    if ($method === 'POST' && $fields) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Generate unique payment reference
 */
function generatePaymentReference() {
    return 'WDE_' . time() . '_' . bin2hex(random_bytes(8));
}

/**
 * Get payment by reference
 */
function getPaymentByReference($reference) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM payments WHERE paystack_reference = ?");
    $stmt->execute([$reference]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get payment by order ID
 */
function getPaymentByOrderId($orderId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM payments WHERE pending_order_id = ?");
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Log payment event
 */
function logPaymentEvent($eventType, $provider, $status, $orderId = null, $paymentId = null, $request = null, $response = null) {
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO payment_logs (
            pending_order_id, payment_id, event_type, provider, status,
            request_data, response_data, ip_address, user_agent
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $paymentId,
        $eventType,
        $provider,
        $status,
        $request ? json_encode($request) : null,
        $response ? json_encode($response) : null,
        $_SERVER['REMOTE_ADDR'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}
```

#### File: `/includes/delivery.php`
Product delivery system functions

```php
<?php
/**
 * Delivery System
 * Handles tool and template delivery
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/email_queue.php';
require_once __DIR__ . '/tool_files.php';

/**
 * Create delivery records for an order
 */
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
    
    // Update order delivery status
    $stmt = $db->prepare("UPDATE pending_orders SET delivery_status = 'in_progress' WHERE id = ?");
    $stmt->execute([$orderId]);
}

/**
 * Create tool delivery
 */
function createToolDelivery($orderId, $item) {
    $db = getDb();
    
    // Get tool files
    $files = getToolFiles($item['product_id']);
    
    // Generate download links
    $downloadLinks = [];
    foreach ($files as $file) {
        $link = generateDownloadLink($file['id'], $orderId);
        if ($link) {
            $downloadLinks[] = $link;
        }
    }
    
    // Create delivery record
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_link, delivery_note
        ) VALUES (?, ?, ?, 'tool', ?, 'download', 'immediate', 'ready', ?, ?)
    ");
    $stmt->execute([
        $orderId,
        $item['id'],
        $item['product_id'],
        $item['product_name'],
        json_encode($downloadLinks),
        $item['delivery_note']
    ]);
    
    $deliveryId = $db->lastInsertId();
    
    // Queue delivery email
    queueToolDeliveryEmail($deliveryId);
    
    return $deliveryId;
}

/**
 * Create template delivery
 */
function createTemplateDelivery($orderId, $item) {
    $db = getDb();
    
    // Create delivery record (pending 24h setup)
    $stmt = $db->prepare("
        INSERT INTO deliveries (
            pending_order_id, order_item_id, product_id, product_type, product_name,
            delivery_method, delivery_type, delivery_status, delivery_note,
            template_ready_at
        ) VALUES (?, ?, ?, 'template', ?, 'hosted', 'pending_24h', 'pending', ?,
                  datetime('now', '+24 hours'))
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
    queueTemplatePendingEmail($deliveryId);
    
    return $deliveryId;
}

/**
 * Mark template as ready
 */
function markTemplateReady($deliveryId, $hostedUrl, $adminNotes = '') {
    $db = getDb();
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET delivery_status = 'ready',
            hosted_url = ?,
            admin_notes = ?,
            delivered_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$hostedUrl, $adminNotes, $deliveryId]);
    
    // Queue "template ready" email
    queueTemplateReadyEmail($deliveryId);
}

/**
 * Get delivery status for an order
 */
function getDeliveryStatus($orderId) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM deliveries WHERE pending_order_id = ? ORDER BY product_type ASC");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

#### File: `/includes/email_queue.php`
Email queue management

```php
<?php
/**
 * Email Queue System
 * Reliable email delivery with retry logic
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

/**
 * Queue an email
 */
function queueEmail($recipientEmail, $emailType, $subject, $body, $htmlBody = null, $orderId = null, $deliveryId = null) {
    $db = getDb();
    
    $stmt = $db->prepare("
        INSERT INTO email_queue (
            recipient_email, email_type, pending_order_id, delivery_id,
            subject, body, html_body
        ) VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $recipientEmail,
        $emailType,
        $orderId,
        $deliveryId,
        $subject,
        $body,
        $htmlBody
    ]);
    
    return $db->lastInsertId();
}

/**
 * Queue tool delivery email
 */
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
    if ($links) {
        $body .= "📥 Download Your Files:\n\n";
        foreach ($links as $link) {
            $body .= "• {$link['name']}: {$link['url']}\n";
        }
    }
    
    $body .= "\n{$delivery['delivery_note']}\n\n";
    $body .= "Need help? Reply to this email or contact us on WhatsApp.\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    return queueEmail(
        $delivery['customer_email'],
        'tools_ready',
        $subject,
        $body,
        null,
        $delivery['order_id'],
        $deliveryId
    );
}

/**
 * Queue template pending email
 */
function queueTemplatePendingEmail($deliveryId) {
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
    
    $subject = "Your Template is Being Prepared - Order #{$delivery['order_id']}";
    
    $body = "Hi {$delivery['customer_name']},\n\n";
    $body .= "Thank you for your order! 🎉\n\n";
    $body .= "📦 Template: {$delivery['product_name']}\n";
    $body .= "📋 Order ID: #{$delivery['order_id']}\n";
    $body .= "⏱️ Ready in: 24 hours\n\n";
    $body .= "We're setting up your template with premium hosting and SSL certificate.\n";
    $body .= "You'll receive another email with your access link when it's ready!\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    return queueEmail(
        $delivery['customer_email'],
        'template_ready',
        $subject,
        $body,
        null,
        $delivery['order_id'],
        $deliveryId
    );
}

/**
 * Queue template ready email
 */
function queueTemplateReadyEmail($deliveryId) {
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
    
    $subject = "🎉 Your Template is Ready! - {$delivery['product_name']}";
    
    $body = "Hi {$delivery['customer_name']},\n\n";
    $body .= "Your template is now live and ready to use!\n\n";
    $body .= "🔗 Access URL: {$delivery['hosted_url']}\n";
    $body .= "📧 Login Email: {$delivery['customer_email']}\n\n";
    $body .= "Need help getting started? We're here for you!\n\n";
    $body .= "Best regards,\nWebDaddy Empire Team";
    
    return queueEmail(
        $delivery['customer_email'],
        'template_ready',
        $subject,
        $body,
        null,
        $delivery['order_id'],
        $deliveryId
    );
}

/**
 * Process email queue
 */
function processEmailQueue() {
    $db = getDb();
    
    // Get pending emails
    $stmt = $db->query("
        SELECT * FROM email_queue 
        WHERE status = 'pending' AND attempts < max_attempts
        ORDER BY scheduled_at ASC
        LIMIT 10
    ");
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($emails as $email) {
        try {
            // Send email using PHPMailer
            $sent = sendEmail($email['recipient_email'], $email['subject'], $email['body'], $email['html_body']);
            
            if ($sent) {
                // Mark as sent
                $updateStmt = $db->prepare("
                    UPDATE email_queue 
                    SET status = 'sent', sent_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $updateStmt->execute([$email['id']]);
            } else {
                throw new Exception('Failed to send email');
            }
        } catch (Exception $e) {
            // Increment attempts
            $updateStmt = $db->prepare("
                UPDATE email_queue 
                SET attempts = attempts + 1, 
                    last_error = ?,
                    status = CASE WHEN attempts + 1 >= max_attempts THEN 'failed' ELSE 'retry' END
                WHERE id = ?
            ");
            $updateStmt->execute([$e->getMessage(), $email['id']]);
        }
    }
}
```

#### File: `/includes/tool_files.php`
Tool file management

```php
<?php
/**
 * Tool Files Management
 * Upload, download, and manage tool files
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Get all files for a tool
 */
function getToolFiles($toolId) {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT * FROM tool_files 
        WHERE tool_id = ? 
        ORDER BY sort_order ASC, created_at ASC
    ");
    $stmt->execute([$toolId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Generate download link with token
 */
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
        INSERT INTO download_tokens (file_id, pending_order_id, token, expires_at, max_downloads)
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

/**
 * Upload tool file
 */
function uploadToolFile($toolId, $uploadedFile, $fileType, $description = '', $sortOrder = 0) {
    // Validate file
    if (!isset($uploadedFile['tmp_name']) || !is_uploaded_file($uploadedFile['tmp_name'])) {
        throw new Exception('Invalid file upload');
    }
    
    // Create upload directory if not exists
    $uploadDir = __DIR__ . '/../uploads/tools/files/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($uploadedFile['name'], PATHINFO_EXTENSION);
    $fileName = 'tool_' . $toolId . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filePath = 'uploads/tools/files/' . $fileName;
    $fullPath = __DIR__ . '/../' . $filePath;
    
    // Move uploaded file
    if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
        throw new Exception('Failed to move uploaded file');
    }
    
    // Store in database
    $db = getDb();
    $stmt = $db->prepare("
        INSERT INTO tool_files (
            tool_id, file_name, file_path, file_type, file_description,
            file_size, mime_type, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $toolId,
        $uploadedFile['name'],
        $filePath,
        $fileType,
        $description,
        $uploadedFile['size'],
        $uploadedFile['type'],
        $sortOrder
    ]);
    
    // Update tool's total files count
    $db->exec("UPDATE tools SET total_files = (SELECT COUNT(*) FROM tool_files WHERE tool_id = {$toolId}) WHERE id = {$toolId}");
    
    return $db->lastInsertId();
}

/**
 * Track file download
 */
function trackDownload($fileId, $orderId) {
    $db = getDb();
    
    // Increment download count in tool_files
    $stmt = $db->prepare("UPDATE tool_files SET download_count = download_count + 1 WHERE id = ?");
    $stmt->execute([$fileId]);
}
```

### 1.4 Environment Variables Setup

Add these to Replit Secrets (or `.env` file):

```
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
PAYSTACK_MODE=test
SITE_URL=https://yourproject.repl.co
BUSINESS_EMAIL=support@webdaddyempire.com
```

### 1.5 Update Configuration File

**File: `/includes/config.php`**

Add these constants:

```php
// Paystack Configuration
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY'));
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY'));
define('PAYSTACK_MODE', getenv('PAYSTACK_MODE') ?: 'test');
define('SITE_URL', getenv('SITE_URL') ?: 'https://webdaddyempire.repl.co');
define('BUSINESS_EMAIL', getenv('BUSINESS_EMAIL'));

// Payment Settings
define('PAYMENT_CURRENCY', 'NGN');
define('DOWNLOAD_LINK_EXPIRY_DAYS', 7);
define('MAX_DOWNLOAD_ATTEMPTS', 5);
```

### 1.6 Phase 1 Checklist

- [x] Create 6 new database tables (payments, deliveries, tool_files, download_tokens, email_queue, payment_logs)
- [x] Update 3 existing tables (pending_orders, tools, templates)
- [x] Create `/includes/paystack.php`
- [x] Create `/includes/delivery.php`
- [x] Create `/includes/email_queue.php`
- [x] Create `/includes/tool_files.php`
- [x] Add environment variables in Replit Secrets
- [x] Update `/includes/config.php` with Paystack constants
- [x] Test: Run SQL migrations successfully
- [x] Test: Verify all PHP files load without errors

---

## 💳 PHASE 2: PAYSTACK PAYMENT INTEGRATION

**Duration**: 1-2 hours  
**Goal**: Implement complete Paystack payment flow from checkout to verification

### 2.1 Update Checkout Page UI

**File: `/cart-checkout.php`**

Add payment method tabs after the customer form section:

```php
<!-- After customer fills their details, before final submit -->
<div class="payment-method-section mt-4">
    <h4 class="mb-3">Choose Payment Method</h4>
    
    <!-- Payment Tabs -->
    <ul class="nav nav-tabs" id="paymentTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="manual-tab" data-bs-toggle="tab" 
                    data-bs-target="#manual-payment" type="button" role="tab">
                💰 Bank Transfer (Manual)
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="paystack-tab" data-bs-toggle="tab" 
                    data-bs-target="#paystack-payment" type="button" role="tab">
                💳 Pay with Card (Instant)
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content border border-top-0 p-4" id="paymentTabContent">
        
        <!-- Manual Payment Tab (Keep existing WhatsApp flow) -->
        <div class="tab-pane fade show active" id="manual-payment" role="tabpanel">
            <!-- Your existing bank details + WhatsApp buttons code -->
            <?php /* Keep all existing manual payment code here */ ?>
        </div>
        
        <!-- Paystack Payment Tab (NEW) -->
        <div class="tab-pane fade" id="paystack-payment" role="tabpanel">
            <div class="alert alert-info">
                ⚡ Pay instantly with your debit or credit card via Paystack
            </div>
            
            <!-- Order Summary -->
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Order Summary</strong>
                </div>
                <div class="card-body">
                    <?php foreach ($cart as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?php echo htmlspecialchars($item['name']); ?></span>
                        <span>₦<?php echo number_format($item['price'], 2); ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if ($affiliateCode): ?>
                    <div class="d-flex justify-content-between mb-2 text-success">
                        <span>Discount (20%)</span>
                        <span>-₦<?php echo number_format($totals['discount'], 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <hr>
                    <div class="d-flex justify-content-between">
                        <strong>Total</strong>
                        <strong>₦<?php echo number_format($totals['final'], 2); ?></strong>
                    </div>
                </div>
            </div>
            
            <!-- Pay Button -->
            <button type="button" id="pay-now-btn" class="btn btn-primary btn-lg w-100">
                💳 Pay ₦<?php echo number_format($totals['final'], 2); ?> Now
            </button>
            
            <p class="text-center text-muted mt-3 small">
                🔒 Secured by Paystack • Your payment information is encrypted
            </p>
        </div>
        
    </div>
</div>

<!-- Add Paystack Inline JS before closing body tag -->
<script src="https://js.paystack.co/v1/inline.js"></script>
<script src="/assets/js/paystack-payment.js"></script>
```

### 2.2 Create Paystack JavaScript Handler

**File: `/assets/js/paystack-payment.js`**

```javascript
/**
 * Paystack Payment Integration
 */

document.addEventListener('DOMContentLoaded', function() {
    const payNowBtn = document.getElementById('pay-now-btn');
    
    if (payNowBtn) {
        payNowBtn.addEventListener('click', initializePayment);
    }
});

async function initializePayment() {
    const payNowBtn = document.getElementById('pay-now-btn');
    payNowBtn.disabled = true;
    payNowBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Initializing...';
    
    try {
        // Get customer details from form
        const customerData = {
            name: document.getElementById('customer-name').value,
            email: document.getElementById('customer-email').value,
            phone: document.getElementById('customer-phone').value,
            business_name: document.getElementById('business-name')?.value || '',
            affiliate_code: getAffiliateCode()
        };
        
        // Validate required fields
        if (!customerData.name || !customerData.email || !customerData.phone) {
            alert('Please fill all required fields');
            resetPayButton();
            return;
        }
        
        // Validate email format
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
    document.getElementById('pay-now-btn').innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying payment...';
    
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
    if (payNowBtn) {
        payNowBtn.disabled = false;
        const amount = payNowBtn.getAttribute('data-amount') || '0';
        payNowBtn.innerHTML = '💳 Pay ₦' + amount + ' Now';
    }
}

function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function getAffiliateCode() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('aff') || '';
}
```

### 2.3 Create Backend API Endpoints

#### File: `/api/paystack-initialize.php`

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
    
    // Get cart
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
        'total_amount' => $totals['final']
    ], $cart);
    
    if (!$orderId) {
        throw new Exception('Failed to create order');
    }
    
    // Initialize Paystack payment
    $paymentData = initializePayment([
        'order_id' => $orderId,
        'customer_name' => $input['name'],
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

#### File: `/api/paystack-verify.php`

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paystack.php';
require_once __DIR__ . '/../includes/delivery.php';
require_once __DIR__ . '/../includes/cart.php';

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
    
    // Get payment record
    $payment = getPaymentByReference($input['reference']);
    if (!$payment) {
        throw new Exception('Payment record not found');
    }
    
    $db = getDb();
    
    // Mark order as paid
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

#### File: `/api/paystack-webhook.php`

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

// Get raw POST body
$input = @file_get_contents("php://input");

// Verify Paystack signature
$signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

if ($signature !== hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY)) {
    http_response_code(401);
    exit('Invalid signature');
}

// Parse webhook data
$event = json_decode($input, true);

// Log the event
logPaymentEvent('webhook_received', 'paystack', 'received', null, null, null, $event);

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
        logPaymentEvent('webhook_ignored', 'paystack', 'ignored', null, null, null, ['event' => $event['event']]);
}

http_response_code(200);
echo json_encode(['status' => 'success']);

function handleSuccessfulPayment($data) {
    $reference = $data['reference'];
    
    // Find payment record
    $payment = getPaymentByReference($reference);
    if (!$payment) {
        logPaymentEvent('payment_not_found', 'paystack', 'error', null, null, null, ['reference' => $reference]);
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
    
    logPaymentEvent('payment_completed', 'paystack', 'success', $payment['pending_order_id'], $payment['id'], null, $data);
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
    
    logPaymentEvent('payment_failed', 'paystack', 'failed', $payment['pending_order_id'], $payment['id'], null, $data);
}
```

### 2.4 Phase 2 Checklist

- [x] Update `/cart-checkout.php` with payment tabs
- [x] Create `/assets/js/paystack-payment.js`
- [x] Create `/api/paystack-initialize.php`
- [x] Create `/api/paystack-verify.php`
- [x] Create `/api/paystack-webhook.php`
- [x] Add Paystack Inline JS to checkout page
- [x] Test: Tabs switch correctly
- [x] Test: Pay button initializes Paystack popup
- [x] Test: Paystack test card works (4084 0840 8408 4081)
- [x] Test: Webhook signature verification works

---

## 📦 PHASE 3: PRODUCT DELIVERY SYSTEM

**Duration**: 2-3 hours  
**Goal**: Implement automatic tool delivery and template hosting tracking

### 3.1 Create Download Handler

**File: `/download.php`**

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
    WHERE dt.token = ? AND dt.expires_at > datetime('now')
");
$stmt->execute([$token]);
$download = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$download) {
    http_response_code(404);
    die('Download link expired or invalid. Please contact support.');
}

// Check download limit
if ($download['download_count'] >= $download['max_downloads']) {
    http_response_code(403);
    die('Download limit exceeded. Please contact support for a new link.');
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
trackDownload($download['file_id'], $download['pending_order_id']);

// Serve file
$filePath = __DIR__ . '/' . $download['file_path'];

if (!file_exists($filePath)) {
    http_response_code(404);
    die('File not found. Please contact support.');
}

header('Content-Type: ' . $download['mime_type']);
header('Content-Disposition: attachment; filename="' . $download['file_name'] . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filePath);
exit;
```

### 3.2 Update Confirmation Page with Delivery Info

**File: `/cart-checkout.php`** (Update confirmation section)

```php
<?php
// After order confirmation
if ($confirmedOrderId) {
    $order = getOrderById($confirmedOrderId);
    
    // Security: Only show if this session created the order
    if ($order && $order['session_id'] === session_id()) {
        // Get deliveries
        $deliveries = getDeliveryStatus($confirmedOrderId);
        
        // Separate by type
        $toolDeliveries = array_filter($deliveries, function($d) {
            return $d['product_type'] === 'tool';
        });
        
        $templateDeliveries = array_filter($deliveries, function($d) {
            return $d['product_type'] === 'template';
        });
        ?>
        
        <div class="container mt-5">
            <div class="alert alert-success">
                <h3>✅ Order Confirmed!</h3>
                <p class="mb-0">Order ID: <strong>#<?php echo $order['id']; ?></strong></p>
                <p class="mb-0">Total Paid: <strong>₦<?php echo number_format($order['total_amount'], 2); ?></strong></p>
                <p class="mb-0">Payment Method: <strong><?php echo ucfirst($order['payment_method']); ?></strong></p>
            </div>
            
            <div class="card mt-4">
                <div class="card-header">
                    <h4>📋 Your Deliverables</h4>
                </div>
                <div class="card-body">
                    
                    <?php if (!empty($toolDeliveries)): ?>
                    <div class="mb-4">
                        <h5>🔧 Digital Tools (Ready Now)</h5>
                        <?php foreach ($toolDeliveries as $tool): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($tool['product_name']); ?></h6>
                                <p class="text-muted small">
                                    <?php echo htmlspecialchars($tool['delivery_note']); ?>
                                </p>
                                
                                <?php
                                $links = json_decode($tool['delivery_link'], true);
                                if ($links):
                                ?>
                                <div class="mt-3">
                                    <strong>📥 Download Files:</strong>
                                    <ul class="list-unstyled mt-2">
                                        <?php foreach ($links as $link): ?>
                                        <li class="mb-2">
                                            <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                               class="btn btn-sm btn-primary" target="_blank">
                                                📄 <?php echo htmlspecialchars($link['name']); ?>
                                            </a>
                                            <small class="text-muted">
                                                (Expires: <?php echo date('M d, Y', strtotime($link['expires_at'])); ?>)
                                            </small>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                                
                                <div class="alert alert-info mt-3 mb-0">
                                    ✉️ Download links have also been sent to 
                                    <strong><?php echo htmlspecialchars($order['customer_email']); ?></strong>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($templateDeliveries)): ?>
                    <div>
                        <h5>🎨 Website Templates</h5>
                        <?php foreach ($templateDeliveries as $template): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($template['product_name']); ?></h6>
                                <p class="text-muted small">
                                    <?php echo htmlspecialchars($template['delivery_note']); ?>
                                </p>
                                
                                <?php if ($template['delivery_status'] === 'ready' && $template['hosted_url']): ?>
                                <div class="alert alert-success">
                                    ✅ Your template is ready!
                                    <a href="<?php echo htmlspecialchars($template['hosted_url']); ?>" 
                                       class="btn btn-success btn-sm ms-2" target="_blank">
                                        Access Template
                                    </a>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    ⏱️ Your template will be ready in approximately 24 hours.
                                    <br>
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
            </div>
        </div>
        
        <?php
    }
}
?>
```

### 3.3 Create Email Processing Cron Job

**File: `/cron/process-emails.php`**

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/email_queue.php';

// Process pending emails
processEmailQueue();

echo "Email queue processed at " . date('Y-m-d H:i:s') . "\n";
```

Add to Replit's `.replit` file or run manually:

```bash
*/5 * * * * php /path/to/cron/process-emails.php
```

### 3.4 Admin Tool File Upload Interface

**File: `/admin/tool-files.php`**

```php
<?php
$pageTitle = 'Tool Files Management';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/../includes/tool_files.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get all tools
$tools = $db->query("SELECT id, name FROM tools WHERE active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selectedToolId = $_GET['tool_id'] ?? null;
$toolFiles = [];

if ($selectedToolId) {
    $toolFiles = getToolFiles($selectedToolId);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    try {
        $toolId = $_POST['tool_id'];
        $fileType = $_POST['file_type'];
        $description = $_POST['description'] ?? '';
        
        if (isset($_FILES['tool_file']) && $_FILES['tool_file']['error'] === UPLOAD_ERR_OK) {
            uploadToolFile($toolId, $_FILES['tool_file'], $fileType, $description);
            $success = 'File uploaded successfully!';
            header('Location: /admin/tool-files.php?tool_id=' . $toolId . '&success=1');
            exit;
        } else {
            throw new Exception('File upload failed');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">📁 Tool Files Management</h1>
    
    <?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <!-- Tool Selection -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Select Tool</h5>
        </div>
        <div class="card-body">
            <form method="GET">
                <div class="row">
                    <div class="col-md-6">
                        <select name="tool_id" class="form-select" onchange="this.form.submit()">
                            <option value="">-- Select a Tool --</option>
                            <?php foreach ($tools as $tool): ?>
                            <option value="<?php echo $tool['id']; ?>" 
                                    <?php echo $selectedToolId == $tool['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tool['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <?php if ($selectedToolId): ?>
    
    <!-- Upload New File -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Upload New File</h5>
        </div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="tool_id" value="<?php echo $selectedToolId; ?>">
                
                <div class="mb-3">
                    <label class="form-label">Select File</label>
                    <input type="file" name="tool_file" class="form-control" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">File Type</label>
                    <select name="file_type" class="form-select" required>
                        <option value="zip_archive">ZIP Archive</option>
                        <option value="attachment">General Attachment</option>
                        <option value="text_instructions">Instructions/Documentation</option>
                        <option value="code">Code/Script</option>
                        <option value="access_key">Access Key/Credentials</option>
                        <option value="image">Image</option>
                        <option value="video">Video</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Description (Optional)</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>
                
                <button type="submit" name="upload_file" class="btn btn-primary">
                    📤 Upload File
                </button>
            </form>
        </div>
    </div>
    
    <!-- Existing Files -->
    <div class="card">
        <div class="card-header">
            <h5>Existing Files (<?php echo count($toolFiles); ?>)</h5>
        </div>
        <div class="card-body">
            <?php if (empty($toolFiles)): ?>
            <p class="text-muted">No files uploaded for this tool yet.</p>
            <?php else: ?>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Type</th>
                        <th>Size</th>
                        <th>Downloads</th>
                        <th>Uploaded</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($toolFiles as $file): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                        <td><span class="badge bg-info"><?php echo $file['file_type']; ?></span></td>
                        <td><?php echo number_format($file['file_size'] / 1024, 2); ?> KB</td>
                        <td><?php echo $file['download_count']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($file['created_at'])); ?></td>
                        <td>
                            <a href="/<?php echo htmlspecialchars($file['file_path']); ?>" 
                               class="btn btn-sm btn-primary" target="_blank">
                                Download
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

### 3.5 Phase 3 Checklist

- [x] Create `/download.php` secure download handler
- [x] Update `/cart-checkout.php` confirmation section with delivery info
- [x] Create `/cron/process-emails.php` email processor
- [x] Create `/admin/tool-files.php` file upload interface
- [x] Create `/uploads/tools/files/` directory with proper permissions
- [x] Test: Upload a file to a tool
- [x] Test: Download link generation works
- [x] Test: Download link expires after time
- [x] Test: Email queue processes successfully
- [x] Test: Tool delivery email sends with download links

---

## 👨‍💼 PHASE 4: ADMIN DASHBOARD & MANAGEMENT

**Duration**: 1-2 hours  
**Goal**: Give admins complete control over payments and deliveries

### 4.1 Create Deliveries Management Page

**File: `/admin/deliveries.php`**

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

// Group by status
$pendingCount = 0;
$inProgressCount = 0;
$completedCount = 0;

foreach ($deliveries as $d) {
    if ($d['delivery_status'] === 'pending') $pendingCount++;
    elseif ($d['delivery_status'] === 'in_progress') $inProgressCount++;
    elseif (in_array($d['delivery_status'], ['sent', 'delivered', 'ready'])) $completedCount++;
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">📦 Delivery Management</h1>
    
    <!-- Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo $pendingCount; ?></h3>
                    <p class="text-muted">Pending Deliveries</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo $inProgressCount; ?></h3>
                    <p class="text-muted">In Progress</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <h3><?php echo $completedCount; ?></h3>
                    <p class="text-muted">Completed</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Deliveries Table -->
    <div class="card">
        <div class="card-header">
            <h5>All Deliveries</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Product</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deliveries as $d): ?>
                    <tr>
                        <td>#<?php echo $d['id']; ?></td>
                        <td>#<?php echo $d['order_id']; ?></td>
                        <td>
                            <?php echo htmlspecialchars($d['customer_name']); ?><br>
                            <small><?php echo htmlspecialchars($d['customer_email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($d['product_name']); ?></td>
                        <td>
                            <?php if ($d['product_type'] === 'tool'): ?>
                                <span class="badge bg-info">Tool</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Template</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $statusBadges = [
                                'pending' => 'warning',
                                'in_progress' => 'info',
                                'ready' => 'success',
                                'sent' => 'success',
                                'delivered' => 'success',
                                'failed' => 'danger'
                            ];
                            $badge = $statusBadges[$d['delivery_status']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $badge; ?>">
                                <?php echo ucfirst($d['delivery_status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($d['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-sm btn-info" 
                                    onclick="viewDelivery(<?php echo $d['id']; ?>)">
                                View
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
function viewDelivery(deliveryId) {
    // TODO: Open modal with delivery details
    alert('View delivery #' + deliveryId);
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

### 4.2 Create Payment Logs Page

**File: `/admin/payment-logs.php`**

```php
<?php
$pageTitle = 'Payment Logs';

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/includes/auth.php';

startSecureSession();
requireAdmin();

$db = getDb();

// Get recent payment logs
$logs = $db->query("
    SELECT pl.*, po.id as order_id, po.customer_name
    FROM payment_logs pl
    LEFT JOIN pending_orders po ON pl.pending_order_id = po.id
    ORDER BY pl.created_at DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid">
    <h1 class="mb-4">📜 Payment Logs</h1>
    
    <div class="card">
        <div class="card-header">
            <h5>Recent Events (Last 100)</h5>
        </div>
        <div class="card-body">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Event</th>
                        <th>Provider</th>
                        <th>Order</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('M d, H:i:s', strtotime($log['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($log['event_type']); ?></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?php echo $log['provider']; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($log['order_id']): ?>
                                #<?php echo $log['order_id']; ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['amount']): ?>
                                ₦<?php echo number_format($log['amount'], 2); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo $log['status']; ?></td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
```

### 4.3 Update Admin Menu

**File: `/admin/includes/header.php`** (Update navigation)

Add these menu items:

```php
<li class="nav-item">
    <a class="nav-link" href="/admin/deliveries.php">
        <i class="bi bi-box-seam"></i> Deliveries
        <?php if ($pendingDeliveriesCount > 0): ?>
        <span class="badge bg-warning"><?php echo $pendingDeliveriesCount; ?></span>
        <?php endif; ?>
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="/admin/tool-files.php">
        <i class="bi bi-file-earmark-zip"></i> Tool Files
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="/admin/payment-logs.php">
        <i class="bi bi-receipt"></i> Payment Logs
    </a>
</li>
```

### 4.4 Update Admin Dashboard Stats

**File: `/admin/index.php`** (Add payment stats)

```php
// Add these queries
$paystackPaymentsCount = $db->query("
    SELECT COUNT(*) FROM payments WHERE payment_method = 'paystack' AND status = 'completed'
")->fetchColumn();

$paystackRevenue = $db->query("
    SELECT COALESCE(SUM(amount_paid), 0) FROM payments 
    WHERE payment_method = 'paystack' AND status = 'completed'
")->fetchColumn();

$manualPaymentsCount = $db->query("
    SELECT COUNT(*) FROM payments WHERE payment_method = 'manual' AND status = 'completed'
")->fetchColumn();

$pendingDeliveriesCount = $db->query("
    SELECT COUNT(*) FROM deliveries WHERE delivery_status = 'pending'
")->fetchColumn();
```

Add these cards to the dashboard:

```php
<div class="col-md-3">
    <div class="card">
        <div class="card-body">
            <h5>💳 Paystack Payments</h5>
            <h2><?php echo $paystackPaymentsCount; ?></h2>
            <p class="text-muted mb-0">₦<?php echo number_format($paystackRevenue, 2); ?></p>
        </div>
    </div>
</div>
```

### 4.5 Phase 4 Checklist

- [ ] Create `/admin/deliveries.php`
- [ ] Create `/admin/payment-logs.php`
- [ ] Create `/admin/tool-files.php` (from Phase 3)
- [ ] Update admin navigation menu
- [ ] Update admin dashboard with payment stats
- [ ] Test: Deliveries page loads with correct data
- [ ] Test: Payment logs display events
- [ ] Test: Tool files upload and display
- [ ] Test: Admin menu shows all new pages

---

## ✅ PHASE 5: TESTING, POLISH & LAUNCH

**Duration**: 1-2 hours  
**Goal**: Complete testing, fix bugs, polish UI, and prepare for launch

### 5.1 Complete Testing Checklist

#### Payment Flow Testing

**Test 1: Manual Payment (WhatsApp)**
- [ ] Add items to cart
- [ ] Go to checkout
- [ ] Fill customer details
- [ ] Select "Manual Payment" tab (should be default)
- [ ] Click "I've Sent the Money" button
- [ ] WhatsApp opens with pre-filled message
- [ ] Order appears in admin as "pending"
- [ ] Admin marks as paid
- [ ] Delivery records created
- [ ] Emails queued and sent
- [ ] Customer sees updated confirmation page

**Test 2: Automatic Payment (Paystack - Test Mode)**
- [ ] Add items to cart
- [ ] Go to checkout
- [ ] Fill customer details (valid email required)
- [ ] Select "Automatic Payment" tab
- [ ] Click "Pay Now" button
- [ ] Paystack popup opens
- [ ] Use test card: `4084 0840 8408 4081`, CVV: `408`, Expiry: `12/25`, PIN: `0000`
- [ ] Payment processes successfully
- [ ] Redirects to confirmation page
- [ ] Order marked as "paid" in database
- [ ] Payment record created
- [ ] Delivery records created
- [ ] Emails sent immediately

**Test 3: Webhook Handling**
- [ ] Make a payment via Paystack
- [ ] Check payment_logs table for webhook events
- [ ] Verify signature validation working
- [ ] Verify order status updated automatically
- [ ] Verify delivery triggered automatically

#### Delivery Testing

**Test 4: Tool Delivery**
- [ ] Upload test file to a tool in admin
- [ ] Create order with that tool
- [ ] Mark as paid (or use Paystack)
- [ ] Check delivery record created
- [ ] Check email queued
- [ ] Process email queue (run cron)
- [ ] Check email received with download links
- [ ] Click download link
- [ ] File downloads successfully
- [ ] Try downloading 6 times (should fail after 5)
- [ ] Wait 8 days and try download (should fail - expired)

**Test 5: Template Delivery**
- [ ] Create order with a template
- [ ] Mark as paid
- [ ] Check delivery record shows "pending" status
- [ ] Check "coming in 24h" email sent
- [ ] Go to admin deliveries page
- [ ] Find the template delivery
- [ ] Enter hosted URL manually
- [ ] Mark as ready
- [ ] Check "template ready" email sent
- [ ] Customer sees access link on confirmation page

**Test 6: Mixed Order (Template + Tools)**
- [ ] Add 1 template + 2 tools to cart
- [ ] Pay via Paystack
- [ ] Check all 3 delivery records created
- [ ] Tools show download links immediately
- [ ] Template shows "coming in 24h" message
- [ ] All items separated clearly on confirmation page

#### Admin Panel Testing

**Test 7: Admin Dashboard**
- [ ] Login to admin panel
- [ ] Check payment stats display correctly
- [ ] Check delivery counts accurate
- [ ] Check recent orders visible
- [ ] Click through to each management page

**Test 8: Deliveries Management**
- [ ] Open `/admin/deliveries.php`
- [ ] See all deliveries listed
- [ ] Filter by status
- [ ] View delivery details
- [ ] Resend delivery email (if function added)

**Test 9: Tool Files Upload**
- [ ] Open `/admin/tool-files.php`
- [ ] Select a tool
- [ ] Upload a ZIP file
- [ ] Verify file appears in list
- [ ] Download file from admin
- [ ] Verify download count increments

**Test 10: Payment Logs**
- [ ] Open `/admin/payment-logs.php`
- [ ] See all payment events
- [ ] Verify webhook events logged
- [ ] Check initialize/verify events present

### 5.2 Security Verification

- [ ] Paystack webhook signature verification working
- [ ] API keys stored in environment variables (not in code)
- [ ] Download tokens expire correctly
- [ ] SQL prepared statements used everywhere
- [ ] XSS prevention (htmlspecialchars on all outputs)
- [ ] File upload validation (type, size)
- [ ] Admin routes require authentication
- [ ] Session security configured properly

### 5.3 Performance Optimization

```sql
-- Run these optimizations on database
VACUUM;
ANALYZE;
```

**Caching Configuration:**
- [ ] Enable OPcache in PHP settings
- [ ] Set proper cache headers for static assets

### 5.4 UI/UX Polish

- [ ] Tab transitions smooth
- [ ] Loading spinners show during payment
- [ ] Success/error messages clear
- [ ] Mobile responsive (test on phone)
- [ ] Download buttons prominent
- [ ] Email templates professional
- [ ] Error messages helpful

### 5.5 Webhook Configuration in Paystack

**Instructions:**

1. Login to Paystack Dashboard
2. Go to **Settings** → **API Keys & Webhooks**
3. Scroll to **Webhooks** section
4. Click **Configure Webhook**
5. Enter Webhook URL: `https://yourproject.repl.co/api/paystack-webhook.php`
6. Click **Test Webhook** → Should see "success" response
7. Save configuration

### 5.6 Email Queue Cron Setup

Make sure email processing runs regularly:

```bash
# Run every 5 minutes
*/5 * * * * php /workspace/cron/process-emails.php >> /workspace/logs/email-cron.log 2>&1
```

Or setup in Replit's Always On feature.

### 5.7 Documentation

**Create Admin Guide:** `/admin/docs/quick-start.md`

Include:
- How to view payments
- How to verify manual payments
- How to upload tool files
- How to mark templates ready
- How to troubleshoot issues

### 5.8 Pre-Launch Checklist

- [ ] Switch to Live Mode (update environment variables)
- [ ] Use live API keys (`sk_live_...` and `pk_live_...`)
- [ ] Test with real card (small amount like ₦100)
- [ ] Verify webhook working in live mode
- [ ] Check all emails sending properly
- [ ] Verify download links work
- [ ] Test full customer journey end-to-end
- [ ] Backup database before launch
- [ ] Monitor payment logs for first few hours

### 5.9 Launch Day Monitoring

- [ ] Watch payment logs in real-time
- [ ] Check email queue processing
- [ ] Monitor for errors in logs
- [ ] Test customer support flow
- [ ] Verify affiliate tracking still works
- [ ] Check mobile experience

### 5.10 Rollback Plan

If issues occur:

1. **Disable Paystack tab temporarily**
   - Comment out Paystack tab in checkout page
   - Customers use manual payment only

2. **Check logs**
   - Review `/admin/payment-logs.php`
   - Check server error logs
   - Review email queue failures

3. **Fix and re-test**
   - Fix identified issue
   - Test in test mode first
   - Re-enable Paystack when confirmed working

### 5.11 Phase 5 Checklist

- [ ] Complete all testing scenarios
- [ ] Verify security measures
- [ ] Optimize database
- [ ] Polish UI/UX
- [ ] Configure webhook in Paystack
- [ ] Setup email cron job
- [ ] Write admin documentation
- [ ] Execute pre-launch checklist
- [ ] Monitor launch day
- [ ] Document any issues and fixes

---

## 📁 COMPLETE FILE STRUCTURE

After all 5 phases, your project structure:

```
webdaddy-empire/
├── admin/
│   ├── index.php (UPDATED - payment stats)
│   ├── orders.php (UPDATED - payment columns)
│   ├── deliveries.php (NEW)
│   ├── payment-logs.php (NEW)
│   ├── tool-files.php (NEW)
│   ├── docs/
│   │   └── quick-start.md (NEW)
│   └── includes/
│       └── header.php (UPDATED - menu items)
├── api/
│   ├── paystack-initialize.php (NEW)
│   ├── paystack-verify.php (NEW)
│   └── paystack-webhook.php (NEW)
├── assets/
│   └── js/
│       └── paystack-payment.js (NEW)
├── includes/
│   ├── config.php (UPDATED - Paystack constants)
│   ├── paystack.php (NEW)
│   ├── delivery.php (NEW)
│   ├── email_queue.php (NEW)
│   └── tool_files.php (NEW)
├── cron/
│   └── process-emails.php (NEW)
├── uploads/
│   └── tools/
│       └── files/ (NEW - tool files storage)
├── cart-checkout.php (UPDATED - tabs + delivery info)
├── download.php (NEW - secure downloads)
└── .env or Replit Secrets (UPDATED)
```

### Database Tables Summary

**New Tables (6):**
1. `payments` - All payment transactions
2. `deliveries` - Product delivery tracking
3. `tool_files` - Tool file attachments
4. `download_tokens` - Secure download links
5. `email_queue` - Email delivery queue
6. `payment_logs` - Complete audit trail

**Updated Tables (3):**
1. `pending_orders` - Payment fields added
2. `tools` - Delivery configuration added
3. `templates` - Delivery configuration added

---

## 🎯 SUCCESS METRICS

Track these after launch:

- **Payment Success Rate**: Target 95%+
- **Automatic vs Manual Payments**: Track ratio
- **Average Delivery Time**: Tools (instant), Templates (24h)
- **Email Delivery Rate**: Target 99%+
- **Customer Satisfaction**: Survey after delivery
- **Revenue from Paystack**: Compare to manual
- **Conversion Rate**: Before/after Paystack

---

## 📞 SUPPORT & TROUBLESHOOTING

### Common Issues

**Issue: Paystack popup doesn't open**
- Check public key is correct
- Verify JavaScript loaded
- Check browser console for errors
- Ensure Paystack Inline JS script included

**Issue: Webhook not firing**
- Verify webhook URL in Paystack dashboard
- Check signature verification code
- Review payment logs for webhook events
- Test webhook using Paystack's test tool

**Issue: Emails not sending**
- Check email queue table for failures
- Verify cron job running
- Check SMTP settings in mailer config
- Review last_error column in email_queue

**Issue: Download links expire too quickly**
- Adjust DOWNLOAD_LINK_EXPIRY_DAYS constant
- Generate new link from admin panel
- Check token expiry in database

**Issue: Template delivery delayed**
- Check deliveries table for status
- Verify admin marked as ready
- Check template_ready_at timestamp
- Resend email manually if needed

---

## 🎉 FINAL NOTES

You now have a complete, production-ready payment and delivery system!

**What You've Built:**
✅ Dual payment system (Manual + Paystack)  
✅ Automatic tool delivery with download links  
✅ Template hosting with 24h tracking  
✅ Reliable email queue system  
✅ Complete admin management dashboard  
✅ Secure file downloads with expiry  
✅ Full payment audit trail  
✅ Professional customer experience  

**Next Steps:**
1. Provide your Paystack credentials
2. I'll configure environment variables
3. We'll test payment flow end-to-end
4. Deploy to production
5. Monitor and optimize

Ready to start? Just provide your Paystack keys! 🚀
