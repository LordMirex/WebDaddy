# Email Templates Documentation

## Overview

This document provides comprehensive documentation for the WebDaddy Empire email system, covering all existing email functions, their purposes, and required adaptations for the customer account system.

---

## Part 1: Existing Email System Inventory

### Email Infrastructure

| Component | File | Purpose |
|-----------|------|---------|
| Core Sender | `includes/mailer.php` | PHPMailer wrapper with all email functions |
| Email Queue | `includes/email_queue.php` | Priority-based queue with retry logic |
| Queue Processor | `cron/process-emails.php` | Cron job for background email processing |
| Session Trigger | `includes/email_processor.php` | Trigger processing on user activity |

### Email Template Functions

The system uses `createEmailTemplate()` as the base wrapper for customer/general emails and `createAffiliateEmailTemplate()` for affiliate-specific emails.

---

## Part 2: Complete Email Function Matrix

> **Legend:** Functions marked with ✓ exist in the current codebase. Functions marked with ⊕ are proposed additions for customer accounts.

### Customer-Facing Emails

| Function | Purpose | Trigger | Customer Account Changes | Status |
|----------|---------|---------|--------------------------|--------|
| `sendOrderConfirmationEmail()` | Order received confirmation | Order creation | Add dashboard link, customer ID | ✓ Existing |
| `sendOrderSuccessEmail()` | Manual payment order received | Bank transfer checkout | Add "View in Dashboard" button | ✓ Existing |
| `sendPaymentConfirmationEmail()` | Simple payment confirmed | Payment verified | Add dashboard order link | ✓ Existing |
| `sendEnhancedPaymentConfirmationEmail()` | Detailed payment confirmation | Payment verified (multi-item) | Add dashboard link + order tracking CTA | ✓ Existing |
| `sendOrderRejectionEmail()` | Order cancelled notification | Admin cancels order | Include reason + support ticket link | ✓ Existing |
| `sendToolDeliveryEmail()` | Tool download links delivery | Tool ready for download | **Dashboard-first delivery** - credentials in dashboard | ✓ Existing |
| `sendToolUpdateEmail()` | Tool version update notification | Files updated for existing order | Add dashboard link for all downloads | ✓ Existing |
| `sendTemplateDeliveryEmail()` | Website is live notification | Template deployed | **No credentials in email** - dashboard only | ✓ Existing |
| `sendTemplateDeliveryEmailWithCredentials()` | Template with inline credentials | Template ready (legacy) | **Deprecate** - use dashboard delivery | ✓ Existing |
| `sendMixedOrderDeliverySummaryEmail()` | Summary for mixed orders | Mixed order complete | Add unified dashboard view link | ✓ Existing |
| `sendOrderDeliveryUpdateEmail()` | Status update notification | Delivery status change | Include state-machine status badge | ✓ Existing |

### Admin-Facing Emails

> **Note:** Admin notification emails send directly via `sendEmail()` (not queued) for immediate delivery.

| Function | Purpose | Trigger | Customer Account Changes | Status |
|----------|---------|---------|--------------------------|--------|
| `sendNewOrderNotificationToAdmin()` | New order alert | Order created | Add customer email/ID + account status | ✓ Existing |
| `sendPaymentSuccessNotificationToAdmin()` | Payment received alert | Paystack success | Include customer profile link | ✓ Existing |
| `sendPaymentFailureNotificationToAdmin()` | Payment failed alert | Paystack failure | Include customer email for follow-up | ✓ Existing |
| `sendWithdrawalRequestToAdmin()` | Affiliate withdrawal request | Affiliate requests withdrawal | No changes needed | ✓ Existing |
| `sendNewSupportTicketNotificationToAdmin()` | New support ticket alert | Ticket created | Show customer name + order history count | ✓ Existing |
| `sendNewCustomerTicketNotification()` | Customer support ticket alert | Customer creates ticket | Include customer account details | ⊕ Proposed |

### Affiliate-Facing Emails

| Function | Purpose | Trigger | Customer Account Changes | Status |
|----------|---------|---------|--------------------------|--------|
| `sendAffiliateWelcomeEmail()` | Welcome + referral code | Registration approved | No changes needed | ✓ Existing |
| `sendCommissionEarnedEmail()` | Commission notification | Sale completed | Include customer name (if consented) | ✓ Existing |
| `sendWithdrawalApprovedEmail()` | Withdrawal approved | Admin approves | No changes needed | ✓ Existing |
| `sendWithdrawalRejectedEmail()` | Withdrawal rejected | Admin rejects | No changes needed | ✓ Existing |
| `sendCustomEmailToAffiliate()` | Admin custom message | Manual send | No changes needed | ✓ Existing |
| `sendSupportTicketReplyEmail()` | Ticket reply notification | Admin replies | No changes needed | ✓ Existing |
| `sendSupportTicketClosedEmail()` | Ticket closed notification | Ticket resolved | No changes needed | ✓ Existing |
| `sendAnnouncementEmail()` | Single announcement email | Admin broadcasts | No changes needed | ✓ Existing |
| `sendAnnouncementEmails()` | Bulk announcement batch sender | Admin broadcasts to all | No changes needed | ✓ Existing |

### Delivery Helper Functions (includes/delivery.php)

| Function | Purpose | Trigger | Customer Account Changes | Status |
|----------|---------|---------|--------------------------|--------|
| `sendAllToolDeliveryEmailsForOrder()` | Send all tool emails for an order | Order tools ready | Add dashboard link for all downloads | ✓ Existing |
| `resendToolDeliveryEmail()` | Resend delivery email | Admin requests resend | Add dashboard link | ✓ Existing |
| `sendToolVersionUpdateEmails()` | Batch update emails for tool | Tool files updated | Add dashboard link | ✓ Existing |
| `buildToolDeliveryEmailContent()` | Build email HTML content | Called by senders | Include dashboard reference | ✓ Existing |
| `saveTemplateCredentials()` | Save + optionally send email | Template delivery | Dashboard-first approach | ✓ Existing |

### Queue-Based Email Functions (includes/email_queue.php)

| Function | Purpose | Trigger | Processing | Status |
|----------|---------|---------|------------|--------|
| `queueEmail()` | Queue email with priority | Any email queuing | Processed by cron or session trigger | ✓ Existing |
| `queueHighPriorityEmail()` | Queue urgent email (priority=1) | Payment confirmations, OTPs | Processed immediately via `processHighPriorityEmails()` | ✓ Existing |
| `queueBulkEmails()` | Queue multiple emails at once | Announcements, newsletters | Processed in batches (priority=10) | ✓ Existing |
| `queueToolDeliveryEmail()` | Queue tool delivery notification | Tool ready | Links to `deliveries` table | ✓ Existing |
| `queueTemplatePendingEmail()` | Queue template preparation notice | Template being setup | ETA notification | ✓ Existing |
| `queueTemplateReadyEmail()` | Queue template live notification | Template deployed | Access URL notification | ✓ Existing |
| `processEmailQueue($batchSize, $aggressive)` | Process pending queue | Cron job / session trigger | Batch processing with retry (see note below) | ✓ Existing |
| `processHighPriorityEmails()` | Process urgent emails only | After queueing critical emails | Processes priority=1 emails immediately | ✓ Existing |

#### Queue Processing Modes

- **Standard Mode:** `processEmailQueue(25)` - Processes 25 emails per batch, pending status only
- **Aggressive Mode:** `processEmailQueue(25, true)` - Doubles batch size (up to 100), includes retry status emails. Use for bulk sending scenarios like announcements.

#### Customer Account Email Queue Integration

When implementing customer account emails, use the queue system with appropriate priorities:

| Email Type | Queue Function | Priority | Rationale |
|------------|---------------|----------|-----------|
| OTP Verification | `queueHighPriorityEmail()` | High (1) | Time-sensitive, expires in 10 minutes |
| Password Reset OTP | `queueHighPriorityEmail()` | High (1) | Security-critical, time-sensitive |
| Welcome Email | `queueEmail(..., 'normal')` | Normal (5) | Important but not urgent |
| Password Set Confirmation | `queueEmail(..., 'normal')` | Normal (5) | Confirmation, can wait briefly |
| Ticket Confirmation | `queueEmail(..., 'normal')` | Normal (5) | Acknowledgment email |

**Implementation Pattern for OTPs:**
```php
// After generating OTP code, queue with high priority and process immediately
$emailBody = createOTPEmailContent($otpCode);
queueHighPriorityEmail($email, 'otp_verification', $subject, $emailBody);
processHighPriorityEmails(); // Process immediately - don't wait for cron
```

---

## Part 3: New Customer Account Email Templates

### 1. OTP Verification Email

**Function:** `sendOTPEmail($email, $otpCode)`

**Subject:** "Your Verification Code - WebDaddy Empire"

**Queue Strategy:** Uses `queueHighPriorityEmail()` + immediate `processHighPriorityEmails()` for time-sensitive delivery.

```php
function sendOTPEmail($email, $otpCode) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Your Verification Code - " . SITE_NAME;
    
    $content = <<<HTML
<div style="text-align: center; padding: 20px 0;">
    <h2 style="color: #1e3a8a; margin: 0 0 15px 0; font-size: 24px;">Verify Your Email</h2>
    
    <p style="color: #374151; margin: 0 0 25px 0; font-size: 16px;">
        Use this code to complete your verification:
    </p>
    
    <div style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%); 
                border-radius: 12px; 
                padding: 25px 40px; 
                display: inline-block; 
                margin: 0 0 25px 0;">
        <span style="font-family: 'Courier New', monospace; 
                     font-size: 36px; 
                     font-weight: bold; 
                     color: #ffffff; 
                     letter-spacing: 8px;">
            {$otpCode}
        </span>
    </div>
    
    <p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
        This code expires in <strong>10 minutes</strong>.
    </p>
    
    <p style="color: #6b7280; font-size: 14px; margin: 0;">
        If you didn't request this code, you can safely ignore this email.
    </p>
</div>

<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 25px 0; border-radius: 4px;">
    <p style="color: #92400e; margin: 0; font-size: 13px;">
        <strong>Can't find this email?</strong> Check your spam or junk folder. 
        Mark emails from {SMTP_FROM_EMAIL} as "Not Spam" to ensure delivery.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Customer');
    
    // Queue with HIGH priority and process immediately (OTP is time-sensitive)
    $queued = queueHighPriorityEmail($email, 'otp_verification', $subject, $emailBody);
    if ($queued) {
        processHighPriorityEmails(); // Send immediately - don't wait for cron
    }
    return $queued !== false;
}
```

### 2. Customer Welcome Email

**Function:** `sendCustomerWelcomeEmail($email, $name)`

**Subject:** "Welcome to WebDaddy Empire!"

**Queue Strategy:** Uses `queueEmail()` with normal priority - important but not time-critical.

```php
function sendCustomerWelcomeEmail($email, $name) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Welcome to " . SITE_NAME . "!";
    $dashboardUrl = SITE_URL . '/user/';
    
    $content = <<<HTML
<h2 style="color: #1e3a8a; margin: 0 0 20px 0; font-size: 24px;">
    Welcome, {$name}!
</h2>

<p style="color: #374151; line-height: 1.7; margin: 0 0 20px 0;">
    Your account has been created successfully. You can now:
</p>

<ul style="color: #374151; line-height: 2; margin: 0 0 25px 0; padding-left: 20px;">
    <li><strong>Track your orders</strong> - View status and delivery updates</li>
    <li><strong>Access your purchases</strong> - Download files and view credentials anytime</li>
    <li><strong>Get support</strong> - Submit tickets and get help quickly</li>
    <li><strong>Faster checkout</strong> - No need to enter your details again</li>
</ul>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$dashboardUrl}" 
       style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
              color: #ffffff;
              padding: 14px 35px;
              border-radius: 8px;
              text-decoration: none;
              font-weight: bold;
              font-size: 16px;
              display: inline-block;">
        Go to My Account
    </a>
</div>

<p style="color: #6b7280; font-size: 14px; margin: 25px 0 0 0;">
    Need help? Just reply to this email or visit our support page.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with NORMAL priority - processed by cron or session trigger
    return queueEmail($email, 'customer_welcome', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}
```

### 3. Password Set Confirmation

**Function:** `sendPasswordSetEmail($email, $name)`

**Queue Strategy:** Uses `queueEmail()` with normal priority.

```php
function sendPasswordSetEmail($email, $name) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Password Set Successfully - " . SITE_NAME;
    
    $content = <<<HTML
<h2 style="color: #10b981; margin: 0 0 15px 0; font-size: 22px;">
    <span style="margin-right: 8px;">✓</span> Password Set Successfully
</h2>

<p style="color: #374151; line-height: 1.7; margin: 0 0 20px 0;">
    Your account password has been set. You can now log in with your email and password 
    for faster access to your account.
</p>

<div style="background: #f3f4f6; border-radius: 8px; padding: 15px; margin: 20px 0;">
    <p style="color: #374151; margin: 0;">
        <strong>Your login email:</strong> {$email}
    </p>
</div>

<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="color: #92400e; margin: 0; font-size: 13px;">
        <strong>Security tip:</strong> If you didn't set this password, please contact support immediately.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with NORMAL priority
    return queueEmail($email, 'password_set', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}
```

### 4. Password Reset Request

**Function:** `sendPasswordResetEmail($email, $name, $resetLink)`

**Queue Strategy:** Uses `queueHighPriorityEmail()` + immediate processing - security-critical and time-sensitive.

```php
function sendPasswordResetEmail($email, $name, $resetLink) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Reset Your Password - " . SITE_NAME;
    
    $content = <<<HTML
<h2 style="color: #1e3a8a; margin: 0 0 15px 0; font-size: 22px;">
    Password Reset Request
</h2>

<p style="color: #374151; line-height: 1.7; margin: 0 0 20px 0;">
    We received a request to reset your password. Click the button below to create a new password:
</p>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$resetLink}" 
       style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
              color: #ffffff;
              padding: 14px 35px;
              border-radius: 8px;
              text-decoration: none;
              font-weight: bold;
              font-size: 16px;
              display: inline-block;">
        Reset Password
    </a>
</div>

<p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
    This link expires in <strong>1 hour</strong>.
</p>

<p style="color: #6b7280; font-size: 14px; margin: 0;">
    If you didn't request this, you can safely ignore this email. Your password won't change.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with HIGH priority and process immediately (security-critical)
    $queued = queueHighPriorityEmail($email, 'password_reset', $subject, $emailBody);
    if ($queued) {
        processHighPriorityEmails();
    }
    return $queued !== false;
}
```

### 5. Account Recovery OTP Email

**Function:** `sendRecoveryOTPEmail($email, $otpCode)`

**Queue Strategy:** Uses `queueHighPriorityEmail()` + immediate processing - security-critical OTP.

```php
function sendRecoveryOTPEmail($email, $otpCode) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Password Reset Code - " . SITE_NAME;
    
    $content = <<<HTML
<div style="text-align: center; padding: 20px 0;">
    <h2 style="color: #dc2626; margin: 0 0 15px 0; font-size: 24px;">Password Reset Request</h2>
    
    <p style="color: #374151; margin: 0 0 25px 0; font-size: 16px;">
        Use this code to reset your password:
    </p>
    
    <div style="background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%); 
                border-radius: 12px; 
                padding: 25px 40px; 
                display: inline-block; 
                margin: 0 0 25px 0;">
        <span style="font-family: 'Courier New', monospace; 
                     font-size: 36px; 
                     font-weight: bold; 
                     color: #ffffff; 
                     letter-spacing: 8px;">
            {$otpCode}
        </span>
    </div>
    
    <p style="color: #6b7280; font-size: 14px; margin: 0 0 10px 0;">
        This code expires in <strong>10 minutes</strong>.
    </p>
</div>

<div style="background: #fee2e2; border-left: 4px solid #dc2626; padding: 15px; margin: 25px 0; border-radius: 4px;">
    <p style="color: #991b1b; margin: 0; font-size: 13px;">
        <strong>Security Alert:</strong> Never share this code with anyone. 
        Our team will never ask for your verification codes.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Customer');
    
    // Queue with HIGH priority and process immediately (security-critical OTP)
    $queued = queueHighPriorityEmail($email, 'recovery_otp', $subject, $emailBody);
    if ($queued) {
        processHighPriorityEmails();
    }
    return $queued !== false;
}
```

---

## Part 4: Template Delivery Notification (Website Is Live)

**CRITICAL:** This email is sent when admin delivers a TEMPLATE (website). It does **NOT** include credentials in the email (to avoid spam filters). Instead, it directs the user to their dashboard.

**Function:** `sendTemplateDeliveryNotification($email, $name, $orderId, $templateName, $websiteUrl)`

**Queue Strategy:** Uses `queueEmail()` with normal priority.

```php
function sendTemplateDeliveryNotification($email, $name, $orderId, $templateName, $websiteUrl) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Your Website Is Live! - " . SITE_NAME;
    $dashboardUrl = SITE_URL . '/user/order-detail.php?id=' . $orderId;
    
    $content = <<<HTML
<div style="text-align: center; padding: 20px 0;">
    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
                border-radius: 50%; 
                width: 80px; 
                height: 80px; 
                margin: 0 auto 20px; 
                display: flex; 
                align-items: center; 
                justify-content: center;">
        <span style="font-size: 40px; color: white;">✓</span>
    </div>
    
    <h2 style="color: #10b981; margin: 0 0 15px 0; font-size: 28px;">
        Your Website Is Live!
    </h2>
</div>

<p style="color: #374151; line-height: 1.7; margin: 0 0 20px 0; font-size: 16px;">
    Great news, {$name}! Your <strong>{$templateName}</strong> website has been set up and is now live.
</p>

<div style="background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%); 
            border: 2px solid #10b981; 
            border-radius: 12px; 
            padding: 25px; 
            margin: 25px 0;
            text-align: center;">
    <p style="color: #166534; margin: 0 0 15px 0; font-size: 14px; font-weight: bold;">
        YOUR WEBSITE URL
    </p>
    <a href="{$websiteUrl}" 
       style="color: #059669; 
              font-size: 20px; 
              font-weight: bold; 
              text-decoration: none;
              word-break: break-all;">
        {$websiteUrl}
    </a>
</div>

<div style="background: #fef3c7; 
            border-left: 4px solid #f59e0b; 
            padding: 20px; 
            margin: 25px 0; 
            border-radius: 4px;">
    <p style="color: #92400e; margin: 0; font-size: 15px;">
        <strong>🔐 Important:</strong> Your login credentials are available in your account dashboard. 
        For security reasons, we don't include passwords in emails.
    </p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$dashboardUrl}" 
       style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
              color: #ffffff;
              padding: 16px 40px;
              border-radius: 8px;
              text-decoration: none;
              font-weight: bold;
              font-size: 16px;
              display: inline-block;">
        View Credentials in Dashboard
    </a>
</div>

<hr style="border: none; border-top: 1px solid #e5e7eb; margin: 30px 0;">

<p style="color: #9ca3af; font-size: 13px; margin: 0;">
    <strong>Next Steps:</strong><br>
    1. Visit your dashboard to get your login credentials<br>
    2. Log into your website admin panel<br>
    3. Start customizing your website content<br>
    4. Contact support if you need any help
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with NORMAL priority - customer notification, not urgent
    return queueEmail($email, 'template_delivery', $subject, strip_tags($content), $emailBody, $orderId, null, 'normal') !== false;
}
```

---

## Part 5: Support Ticket Email Templates

### New Support Ticket Confirmation

**Function:** `sendTicketConfirmationEmail($email, $name, $ticketId, $ticketSubject)`

**Queue Strategy:** Uses `queueEmail()` with normal priority.

```php
function sendTicketConfirmationEmail($email, $name, $ticketId, $ticketSubject) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Support Ticket #{$ticketId} Created - " . SITE_NAME;
    $ticketUrl = SITE_URL . "/user/ticket.php?id={$ticketId}";
    
    $content = <<<HTML
<h2 style="color: #1e3a8a; margin: 0 0 15px 0; font-size: 22px;">
    Support Ticket Created
</h2>

<p style="color: #374151; line-height: 1.7; margin: 0 0 20px 0;">
    We've received your support request. Our team will respond as soon as possible.
</p>

<div style="background: #f3f4f6; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="color: #374151; margin: 0 0 10px 0;">
        <strong>Ticket ID:</strong> #{$ticketId}
    </p>
    <p style="color: #374151; margin: 0;">
        <strong>Subject:</strong> {$ticketSubject}
    </p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$ticketUrl}" 
       style="background: #374151;
              color: #ffffff;
              padding: 12px 30px;
              border-radius: 8px;
              text-decoration: none;
              font-weight: bold;
              display: inline-block;">
        View Ticket
    </a>
</div>

<p style="color: #6b7280; font-size: 14px; margin: 0;">
    You'll receive an email notification when we reply.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with NORMAL priority
    return queueEmail($email, 'ticket_confirmation', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}
```

### Ticket Reply Notification

**Function:** `sendTicketReplyNotification($email, $name, $ticketId, $replyPreview)`

**Queue Strategy:** Uses `queueEmail()` with normal priority.

```php
function sendTicketReplyNotification($email, $name, $ticketId, $replyPreview) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "New Reply on Ticket #{$ticketId} - " . SITE_NAME;
    $ticketUrl = SITE_URL . "/user/ticket.php?id={$ticketId}";
    
    $preview = strlen($replyPreview) > 200 ? substr($replyPreview, 0, 200) . '...' : $replyPreview;
    
    $content = <<<HTML
<h2 style="color: #1e3a8a; margin: 0 0 15px 0; font-size: 22px;">
    New Reply on Your Ticket
</h2>

<p style="color: #374151; margin: 0 0 15px 0;">
    Our support team has replied to your ticket <strong>#{$ticketId}</strong>:
</p>

<div style="background: #f0f9ff; border-left: 4px solid #3b82f6; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="color: #1e40af; margin: 0; font-style: italic;">
        "{$preview}"
    </p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$ticketUrl}" 
       style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
              color: #ffffff;
              padding: 12px 30px;
              border-radius: 8px;
              text-decoration: none;
              font-weight: bold;
              display: inline-block;">
        View Full Reply
    </a>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with NORMAL priority
    return queueEmail($email, 'ticket_reply', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}
```

---

## Part 6: Delayed Tool Delivery Workflow (Preorder/Pending Vendor Acquisition)

### Overview

Some tools may require admin to first purchase from the original vendor before delivery. This creates a "preorder" scenario where:

1. **First Customer** experiences delayed delivery (admin must buy the asset first)
2. **Subsequent Customers** get instant delivery (files already uploaded)

### Delivery States for Tools

| State | Description | Customer Email |
|-------|-------------|----------------|
| `pending` | Order received, awaiting payment | Order confirmation |
| `paid` | Payment confirmed, awaiting files | Payment confirmed + ETA notice |
| `pending_acquisition` | **NEW** - Admin needs to purchase asset | Acquisition notice with ETA |
| `ready` | Files uploaded, ready for delivery | Delivery email with download links |
| `delivered` | Customer has received files | (No additional email) |

### New Email: Pending Acquisition Notice

**Function:** `sendToolAcquisitionNoticeEmail($email, $name, $orderId, $toolName, $estimatedDays = 3)`

**Trigger:** When admin marks a tool order as "pending_acquisition" (first-time purchase)

**Queue Strategy:** Uses `queueEmail()` with normal priority.

```php
function sendToolAcquisitionNoticeEmail($email, $name, $orderId, $toolName, $estimatedDays = 3) {
    require_once __DIR__ . '/email_queue.php';
    
    $subject = "Your Order is Being Prepared - Order #{$orderId}";
    $dashboardUrl = SITE_URL . '/user/order-detail.php?id=' . $orderId;
    $estimatedDate = date('F j, Y', strtotime("+{$estimatedDays} days"));
    
    $content = <<<HTML
<h2 style="color: #f59e0b; margin: 0 0 15px 0; font-size: 22px;">
    ⏳ Your Order is Being Prepared
</h2>

<p style="color: #374151; line-height: 1.7; margin: 0 0 20px 0;">
    Thank you for your order! Your payment has been confirmed and we're now preparing your digital product.
</p>

<div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 12px; padding: 20px; margin: 20px 0;">
    <p style="color: #92400e; margin: 0 0 10px 0; font-size: 16px;">
        <strong>📦 Product:</strong> {$toolName}
    </p>
    <p style="color: #92400e; margin: 0 0 10px 0; font-size: 16px;">
        <strong>📋 Order ID:</strong> #{$orderId}
    </p>
    <p style="color: #92400e; margin: 0; font-size: 16px;">
        <strong>📅 Estimated Ready By:</strong> {$estimatedDate}
    </p>
</div>

<div style="background: #f3f4f6; border-left: 4px solid #6b7280; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="color: #374151; margin: 0; line-height: 1.6;">
        <strong>Why the wait?</strong> This is a premium product that we're acquiring exclusively for you. 
        You'll be among the first to receive it, and we'll notify you immediately once it's ready for download.
    </p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$dashboardUrl}" 
       style="background: linear-gradient(135deg, #3b82f6 0%, #1e40af 100%);
              color: #ffffff;
              padding: 14px 35px;
              border-radius: 8px;
              text-decoration: none;
              font-weight: bold;
              font-size: 16px;
              display: inline-block;">
        Track Your Order
    </a>
</div>

<p style="color: #6b7280; font-size: 14px; margin: 0;">
    You'll receive an email with download links as soon as your product is ready. 
    Thank you for your patience!
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    
    // Queue with NORMAL priority - customer notification
    return queueEmail($email, 'acquisition_notice', $subject, strip_tags($content), $emailBody, $orderId, null, 'normal') !== false;
}
```

### Admin Notification: Acquisition Required

**Function:** `sendAcquisitionRequiredNotificationToAdmin($orderId, $customerName, $toolName)`

> **Note:** Admin notifications send directly via `sendEmail()` (not queued) for immediate delivery.

```php
function sendAcquisitionRequiredNotificationToAdmin($orderId, $customerName, $toolName, $customerEmail) {
    // Admin emails send directly - not queued
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "🔔 Action Required: Acquire Tool for Order #{$orderId}";
    
    $content = <<<HTML
<h2 style="color: #dc2626; margin: 0 0 15px 0; font-size: 22px;">
    ⚠️ Tool Acquisition Required
</h2>

<p style="color: #374151; line-height: 1.6; margin: 0 0 15px 0;">
    A customer has ordered a tool that needs to be acquired from the vendor first.
</p>

<div style="background: #fef2f2; border: 2px solid #dc2626; border-radius: 8px; padding: 20px; margin: 20px 0;">
    <p style="margin: 5px 0; color: #991b1b;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin: 5px 0; color: #991b1b;"><strong>Customer:</strong> {$customerName}</p>
    <p style="margin: 5px 0; color: #991b1b;"><strong>Email:</strong> {$customerEmail}</p>
    <p style="margin: 5px 0; color: #991b1b;"><strong>Tool Needed:</strong> {$toolName}</p>
</div>

<div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 15px; margin: 20px 0; border-radius: 4px;">
    <p style="color: #92400e; margin: 0; font-weight: 600;">Action Required:</p>
    <ol style="color: #92400e; margin: 10px 0 0 0; padding-left: 20px;">
        <li>Purchase the tool from the original vendor</li>
        <li>Upload the files to the tool's file section in admin</li>
        <li>Mark the tool as "Files Ready for Delivery"</li>
        <li>The system will automatically notify the customer</li>
    </ol>
</div>

<p style="color: #374151; margin: 15px 0 0 0;">
    The customer has been notified that their order is being prepared.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody); // Direct send for admin
}
```

### Workflow Summary with Queue Integration

```
Customer Orders Tool → Payment Confirmed
                            ↓
    ┌────────────────────────┴────────────────────────┐
    │                                                  │
[Files Exist?]                                 [Files Missing?]
    │                                                  │
    ↓                                                  ↓
Instant Delivery                          Set status: pending_acquisition
├─ queueToolDeliveryEmail()               ├─ queueEmail() via sendToolAcquisitionNoticeEmail()
├─ processEmailQueue() via cron           └─ sendEmail() via sendAcquisitionRequiredNotificationToAdmin()
└─ Updates delivery status                    (admin emails are direct, customer queued)
                                                    │
                                                    ↓
                                          Admin uploads files
                                                    │
                                                    ↓
                                          Set status: ready
                                          ├─ queueToolDeliveryEmail($deliveryId)
                                          └─ processEmailQueue() sends email
                                                    │
                                                    ↓
                                          Delivery Email Sent
                                          └─ delivery_status = 'delivered'
```

### Key Queue Functions for Delayed Delivery

| Step | Function | File | Purpose |
|------|----------|------|---------|
| 1 | `queueToolDeliveryEmail($deliveryId)` | `email_queue.php` | Queue delivery notification |
| 2 | `processEmailQueue()` | `email_queue.php` | Process queued emails (cron/session) |
| 3 | Email callback updates `deliveries.delivery_status` | `email_queue.php` | Mark as delivered on send |
| 4 | `sendAllToolDeliveryEmailsForOrder($orderId)` | `delivery.php` | Batch queue all tools for order |

### Database States for Tracking

```sql
-- Check pending acquisitions
SELECT * FROM deliveries 
WHERE delivery_status = 'pending_acquisition' 
ORDER BY created_at ASC;

-- Process ready deliveries
SELECT * FROM deliveries 
WHERE delivery_status = 'ready' 
  AND email_sent_at IS NULL;
```

---

## Part 7: Tool Version Control Update Emails

### Overview

When admin updates files for an existing tool, all customers who previously purchased it receive update notification emails. The system tracks:

- **New files** added to the tool
- **Updated files** (same name, new content)
- **Existing files** unchanged
- **Removed files** deleted from the tool

### Existing Function: `sendToolVersionUpdateEmails($toolId)`

This function:
1. Finds all delivered orders for the specified tool
2. Compares current files with previously delivered files
3. Generates new download links for new/updated files
4. Queues personalized update emails via the email queue

### Update Email Content

**Function:** `sendToolUpdateEmail($order, $item, $downloadLinks, $orderId)`

Already exists in `includes/delivery.php` and handles:

- Subject: "Update Available: {Tool Name}"
- Lists new files with download links
- Lists updated files with fresh download links
- Notes any removed files
- Directs to dashboard for all downloads

### Customer Account Enhancement

Add dashboard link to existing update emails:

```php
// In sendToolUpdateEmail() - add after file list section:

$dashboardLink = SITE_URL . '/user/order-detail.php?id=' . $orderId;

$content .= '<div style="text-align: center; margin: 30px 0;">';
$content .= '<a href="' . $dashboardLink . '" ';
$content .= 'style="background: #10b981; color: #ffffff; padding: 14px 35px; ';
$content .= 'border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block;">';
$content .= 'View All Files in Dashboard</a>';
$content .= '</div>';

$content .= '<p style="color: #6b7280; font-size: 14px; text-align: center;">';
$content .= 'All your downloads are always available in your account dashboard.';
$content .= '</p>';
```

---

## Part 8: Modified Existing Email Templates

### Payment Confirmation - Add Dashboard Link

```php
// In sendEnhancedPaymentConfirmationEmail() - add after product list:

$dashboardLink = SITE_URL . '/user/order-detail.php?id=' . $orderId;

$content .= '<div style="text-align: center; margin: 25px 0;">';
$content .= '<a href="' . $dashboardLink . '" ';
$content .= 'style="background: #10b981; color: #ffffff; padding: 12px 30px; ';
$content .= 'border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block;">';
$content .= 'View Order in Dashboard</a>';
$content .= '</div>';

$content .= '<p style="color: #6b7280; font-size: 14px; text-align: center;">';
$content .= 'You can always access your orders and downloads from your account.';
$content .= '</p>';
```

### Delivery Emails - Add Dashboard Reference

```php
// In sendToolDeliveryEmail() and sendTemplateDeliveryEmail():

$dashboardLink = SITE_URL . '/user/order-detail.php?id=' . $orderId;

$content .= '<div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin: 20px 0;">';
$content .= '<p style="color: #1e40af; margin: 0; font-size: 14px;">';
$content .= '<strong>Tip:</strong> You can access your downloads and credentials anytime from your ';
$content .= '<a href="' . $dashboardLink . '" style="color: #1e40af;">account dashboard</a>.';
$content .= '</p>';
$content .= '</div>';
```

### Admin Order Notification - Add Customer Context

```php
// In sendNewOrderNotificationToAdmin() - add customer account info:

// Check if customer has an account
$customerAccountInfo = '';
if (!empty($order['customer_id'])) {
    $customerAccountInfo = '<p style="margin:5px 0; color:#10b981;"><strong>Account:</strong> Registered Customer</p>';
} else {
    $customerAccountInfo = '<p style="margin:5px 0; color:#f59e0b;"><strong>Account:</strong> Guest (No Account)</p>';
}

// Add to email content
$content .= $customerAccountInfo;
```

---

## Part 9: SMS Templates (Termii Integration)

### OTP SMS Template

```
Your WebDaddy verification code is: {OTP_CODE}

Valid for 10 minutes. Do not share this code.
```

### Order Confirmation SMS (Optional)

```
Order #{ORDER_ID} confirmed! Amount: N{AMOUNT}. Track at: {SHORT_LINK}
```

### Delivery Ready SMS (Optional)

```
Your order #{ORDER_ID} is ready! Download at: {SHORT_LINK}
```

---

## Part 10: Email Function Locations

All **new** email functions (marked ⊕ in Part 2) should be added to `includes/mailer.php` when implementing the customer account system:

```php
// =====================================================
// CUSTOMER ACCOUNT EMAILS (⊕ NEW - Add when implementing customer accounts)
// =====================================================
function sendOTPEmail($email, $otpCode) { ... }
function sendCustomerWelcomeEmail($email, $name) { ... }
function sendPasswordSetEmail($email, $name) { ... }
function sendPasswordResetEmail($email, $name, $resetLink) { ... }
function sendRecoveryOTPEmail($email, $otpCode) { ... }

// =====================================================
// TEMPLATE DELIVERY (⊕ NEW - Dashboard-first approach)
// =====================================================
function sendTemplateDeliveryNotification($email, $name, $orderId, $templateName, $websiteUrl) { ... }

// =====================================================
// SUPPORT TICKET EMAILS (⊕ NEW - Customer-facing tickets)
// =====================================================
function sendTicketConfirmationEmail($email, $name, $ticketId, $subject) { ... }
function sendTicketReplyNotification($email, $name, $ticketId, $replyPreview) { ... }

// =====================================================
// DELAYED DELIVERY / PREORDER EMAILS (⊕ NEW)
// =====================================================
function sendToolAcquisitionNoticeEmail($email, $name, $orderId, $toolName, $estimatedDays) { ... }
function sendAcquisitionRequiredNotificationToAdmin($orderId, $customerName, $toolName, $customerEmail) { ... }

// =====================================================
// ADMIN NOTIFICATIONS (⊕ NEW - Customer context)
// =====================================================
function sendNewCustomerTicketNotification($ticketId) { ... }
```

> **Note:** Existing functions (marked ✓ in Part 2) only need modification to add dashboard links and customer account context - they don't need to be rewritten.

---

## Part 11: Testing Checklist

### New Customer Account Emails
- [ ] OTP email sends and displays correctly
- [ ] OTP code is clearly visible with large font
- [ ] Welcome email sends on account creation
- [ ] Password set email sends when password updated
- [ ] Password reset OTP email works
- [ ] Dashboard links work in all emails

### Modified Existing Emails
- [ ] Payment confirmation includes dashboard link
- [ ] Delivery emails reference dashboard for credentials
- [ ] Admin notifications show customer account status
- [ ] Tool update emails include dashboard link

### Delayed Delivery Workflow
- [ ] Acquisition notice email sends when tool marked "pending_acquisition"
- [ ] Admin receives acquisition required notification
- [ ] Customer receives delivery email when files uploaded
- [ ] Dashboard shows correct status at each stage

### Template Delivery (Dashboard-First)
- [ ] Template delivery email does NOT contain credentials
- [ ] Email directs user to dashboard for credentials
- [ ] Dashboard correctly displays credentials when accessed

### Email Rendering
- [ ] All emails render correctly on mobile
- [ ] Spam folder warning is visible
- [ ] All CTAs (buttons) are functional
- [ ] Dark mode compatible (optional)

---

## Part 12: Email Priority Levels

| Priority | Level | Use Cases |
|----------|-------|-----------|
| High (1) | Immediate | Payment confirmations, OTP codes, security alerts |
| Normal (5) | Standard | Order updates, delivery notifications, ticket replies |
| Low (10) | Batch | Announcements, newsletters, bulk notifications |

The email queue system processes high-priority emails first using `processHighPriorityEmails()` for time-sensitive communications.
