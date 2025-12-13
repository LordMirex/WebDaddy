# Email Templates

## Overview

This document details new and modified email templates for the customer account system.

## New Email Templates

### 1. OTP Verification Email

**Function:** `sendOTPEmail($email, $otpCode)`

**Subject:** "Your Verification Code - WebDaddy Empire"

```php
function sendOTPEmail($email, $otpCode) {
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
    return sendEmail($email, $subject, $emailBody);
}
```

### 2. Customer Welcome Email

**Function:** `sendCustomerWelcomeEmail($email, $name)`

**Subject:** "Welcome to WebDaddy Empire!"

```php
function sendCustomerWelcomeEmail($email, $name) {
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
    return sendEmail($email, $subject, $emailBody);
}
```

### 3. Password Set Confirmation

**Function:** `sendPasswordSetEmail($email, $name)`

**Subject:** "Password Set Successfully"

```php
function sendPasswordSetEmail($email, $name) {
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
    return sendEmail($email, $subject, $emailBody);
}
```

### 4. Password Reset Request

**Function:** `sendPasswordResetEmail($email, $name, $resetLink)`

**Subject:** "Reset Your Password"

```php
function sendPasswordResetEmail($email, $name, $resetLink) {
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

<div style="background: #f3f4f6; border-radius: 8px; padding: 15px; margin: 25px 0;">
    <p style="color: #6b7280; margin: 0; font-size: 12px;">
        If the button doesn't work, copy and paste this link:<br>
        <span style="color: #3b82f6; word-break: break-all;">{$resetLink}</span>
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $name);
    return sendEmail($email, $subject, $emailBody);
}
```

### 5. New Support Ticket Confirmation

**Function:** `sendTicketConfirmationEmail($email, $name, $ticketId, $subject)`

```php
function sendTicketConfirmationEmail($email, $name, $ticketId, $ticketSubject) {
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
    return sendEmail($email, $subject, $emailBody);
}
```

### 6. Ticket Reply Notification

**Function:** `sendTicketReplyNotification($email, $name, $ticketId, $replyPreview)`

```php
function sendTicketReplyNotification($email, $name, $ticketId, $replyPreview) {
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
    return sendEmail($email, $subject, $emailBody);
}
```

## Modified Email Templates

### 1. Payment Confirmation - Updated

Add dashboard link to existing payment confirmation:

```php
// In sendEnhancedPaymentConfirmationEmail()
// Add after the product list section:

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

### 2. Delivery Email - Updated

Add dashboard reference to delivery emails:

```php
// In sendToolDeliveryEmail() and sendTemplateCredentialsEmail()

$dashboardLink = SITE_URL . '/user/order-detail.php?id=' . $orderId;

$content .= '<div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin: 20px 0;">';
$content .= '<p style="color: #1e40af; margin: 0; font-size: 14px;">';
$content .= '<strong>Tip:</strong> You can access your downloads and credentials anytime from your ';
$content .= '<a href="' . $dashboardLink . '" style="color: #1e40af;">account dashboard</a>.';
$content .= '</p>';
$content .= '</div>';
```

## SMS Templates (Termii)

### OTP SMS Template

```
Your WebDaddy verification code is: {OTP_CODE}

Valid for 10 minutes. Do not share this code.
```

### Order Confirmation SMS (Optional)

```
Order #{ORDER_ID} confirmed! Amount: N{AMOUNT}. Track at: {SHORT_LINK}
```

## Email Function Locations

All new email functions should be added to `includes/mailer.php`:

```php
// Customer Account Emails
function sendOTPEmail($email, $otpCode) { ... }
function sendCustomerWelcomeEmail($email, $name) { ... }
function sendPasswordSetEmail($email, $name) { ... }
function sendPasswordResetEmail($email, $name, $resetLink) { ... }

// Support Ticket Emails
function sendTicketConfirmationEmail($email, $name, $ticketId, $subject) { ... }
function sendTicketReplyNotification($email, $name, $ticketId, $replyPreview) { ... }

// Notify Admin of Customer Ticket
function sendNewCustomerTicketNotification($ticketId) { ... }
```

## Testing Checklist

- [ ] OTP email sends and displays correctly
- [ ] OTP code is clearly visible
- [ ] Welcome email sends on account creation
- [ ] Password set email sends when password updated
- [ ] Password reset email contains valid link
- [ ] Ticket confirmation email includes ticket ID
- [ ] Ticket reply notification shows preview
- [ ] Dashboard links work in all emails
- [ ] Emails render correctly on mobile
- [ ] Spam folder warning is visible
