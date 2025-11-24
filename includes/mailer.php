<?php

require_once __DIR__ . '/../mailer/PHPMailer.php';
require_once __DIR__ . '/../mailer/SMTP.php';
require_once __DIR__ . '/../mailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Send email using PHPMailer
 * @param string $email Recipient email address
 * @param string $subject Email subject
 * @param string $message HTML email body
 * @return bool
 */
function sendEmail($email, $subject, $message) {
    // SECURITY: Validate SMTP credentials are configured
    if (!defined('SMTP_USER') || !defined('SMTP_PASS') || !defined('SMTP_FROM_EMAIL') ||
        empty(SMTP_USER) || empty(SMTP_PASS) || empty(SMTP_FROM_EMAIL)) {
        error_log("Email sending failed: SMTP credentials not configured");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.example.com';
        $mail->SMTPAuth = true; // REQUIRED: Always authenticate
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 465;
        
        // SECURITY: Enforce encrypted connection (SSL/TLS)
        $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'ssl';
        if ($smtpSecure === 'ssl') {
            $mail->SMTPSecure = 'ssl'; // SSL on port 465
        } else {
            $mail->SMTPSecure = 'tls'; // TLS on port 587
        }
        
        // SECURITY: Enforce TLS peer verification (prevent MITM attacks)
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false
            ]
        ];
        
        // Fix encoding issues - force UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Email Settings
        $mail->isHTML(true);
        $mail->setFrom(SMTP_FROM_EMAIL, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME);
        $mail->Subject = $subject;
        $mail->Body = $message;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed to {$email}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Create email template wrapper
 * @param string $subject Email subject
 * @param string $content Main email content (HTML)
 * @param string $recipientName Recipient's name
 * @return string Complete HTML email
 */
function createEmailTemplate($subject, $content, $recipientName = 'Valued Customer') {
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $whatsapp = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '+2349132672126';
    
    $esc_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $esc_name = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $esc_siteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$esc_subject}</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">
    <div style="max-width:600px; margin:10px auto; background:#ffffff; border-radius:6px; overflow:hidden; box-shadow:0 2px 4px rgba(0,0,0,0.1);">
        <div style="background:linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding:20px; text-align:center;">
            <h1 style="color:#ffffff; margin:0; font-size:24px; font-weight:700;">{$esc_siteName}</h1>
            <p style="color:rgba(255,255,255,0.9); margin:5px 0 0 0; font-size:13px;">Professional Website Templates</p>
        </div>
        
        <div style="padding:20px;">
            <p style="margin:0 0 15px 0; font-size:14px; color:#374151;">Hello <strong>{$esc_name}</strong>,</p>
            
            <div style="background:#f9fafb; padding:15px; border-left:3px solid #3b82f6; border-radius:4px; margin-bottom:15px;">
                {$content}
            </div>
            
            <div style="margin-top:20px; padding-top:15px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:12px;">
                <p style="margin:0 0 8px 0;">Need help? Contact us on WhatsApp: <a href="https://wa.me/{$whatsapp}" style="color:#3b82f6; text-decoration:none; font-weight:600;">{$whatsapp}</a></p>
                <p style="margin:0;">Best regards,<br><strong>The {$esc_siteName} Team</strong></p>
            </div>
        </div>
        
        <div style="background:#1f2937; color:#9ca3af; padding:15px; text-align:center; font-size:11px;">
            <p style="margin:0;">&copy; 2025 {$esc_siteName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Build normalized email context from order and items data
 * @param array $order Order header data from pending_orders table
 * @param array $orderItems Array of items from order_items table
 * @return array Normalized context for email templating
 */
function buildOrderEmailContext($order, $orderItems) {
    $context = [
        'order_id' => $order['id'] ?? null,
        'customer_name' => $order['customer_name'] ?? 'Customer',
        'order_type' => $order['order_type'] ?? 'tool',
        'total_amount' => $order['final_amount'] ?? 0,
        'original_amount' => $order['original_price'] ?? 0,
        'discount_amount' => $order['discount_amount'] ?? 0,
        'has_discount' => !empty($order['discount_amount']) && $order['discount_amount'] > 0,
        'affiliate_code' => $order['affiliate_code'] ?? null,
        'items' => [],
        'has_templates' => false,
        'has_tools' => false,
        'template_domain' => null,
        'template_credentials' => null
    ];
    
    foreach ($orderItems as $item) {
        $productType = $item['product_type'] ?? 'tool';
        $metadata = !empty($item['metadata_json']) ? json_decode($item['metadata_json'], true) : [];
        
        $itemData = [
            'type' => $productType,
            'name' => $metadata['name'] ?? 'Product',
            'quantity' => $item['quantity'] ?? 1,
            'unit_price' => $item['unit_price'] ?? 0,
            'final_amount' => $item['final_amount'] ?? 0,
            'badge_color' => $productType === 'template' ? '#3b82f6' : '#8b5cf6',
            'badge_text' => $productType === 'template' ? 'Template' : 'Tool'
        ];
        
        if ($productType === 'template') {
            $context['has_templates'] = true;
        } else {
            $context['has_tools'] = true;
        }
        
        $context['items'][] = $itemData;
    }
    
    return $context;
}

/**
 * Send enhanced payment confirmation email for all order types
 * Handles templates, tools, and mixed orders with appropriate fulfillment instructions
 * @param array $order Order header data
 * @param array $orderItems Array of order items
 * @param string|null $domainName Domain name for template orders
 * @param array|null $credentials Login credentials for template orders
 * @return bool Success status
 */
function sendEnhancedPaymentConfirmationEmail($order, $orderItems, $domainName = null, $credentials = null) {
    if (empty($order['customer_email'])) {
        return false;
    }
    
    $context = buildOrderEmailContext($order, $orderItems);
    $customerName = $context['customer_name'];
    $orderId = $context['order_id'];
    
    $subject = "Payment Confirmed - Order #{$orderId}";
    
    $productListHtml = '';
    foreach ($context['items'] as $item) {
        $formattedUnitPrice = formatCurrency($item['unit_price']);
        $formattedTotal = formatCurrency($item['final_amount']);
        $badgeColor = htmlspecialchars($item['badge_color']);
        $badgeText = htmlspecialchars($item['badge_text']);
        $itemName = htmlspecialchars($item['name']);
        $quantity = (int)$item['quantity'];
        
        $productListHtml .= <<<HTML
<tr style="border-bottom:1px solid #e5e7eb;">
    <td style="padding:12px 8px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="background:{$badgeColor}; color:#fff; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; white-space:nowrap;">{$badgeText}</span>
            <span style="color:#374151; font-size:14px;">{$itemName}</span>
        </div>
    </td>
    <td style="padding:12px 8px; text-align:center; color:#6b7280; font-size:14px;">{$quantity}</td>
    <td style="padding:12px 8px; text-align:right; color:#374151; font-size:14px; font-weight:600;">{$formattedTotal}</td>
</tr>
HTML;
    }
    
    $fulfillmentHtml = '';
    
    if ($context['has_templates'] && $domainName) {
        $escapedDomain = htmlspecialchars($domainName);
        $credentialsHtml = '';
        if ($credentials) {
            $username = htmlspecialchars($credentials['username'] ?? '');
            $password = htmlspecialchars($credentials['password'] ?? '');
            $credentialsHtml = <<<HTML
<p style="margin:5px 0; color:#374151;"><strong>Username:</strong> {$username}</p>
<p style="margin:5px 0; color:#374151;"><strong>Password:</strong> {$password}</p>
HTML;
        }
        
        $fulfillmentHtml .= <<<HTML
<div style="background:#dbeafe; border-left:3px solid #3b82f6; padding:15px; border-radius:4px; margin:15px 0;">
    <h3 style="color:#1e40af; margin:0 0 10px 0; font-size:16px;">🎨 Template Access</h3>
    <p style="margin:5px 0; color:#374151;"><strong>Domain:</strong> {$escapedDomain}</p>
    {$credentialsHtml}
    <p style="margin:10px 0 0 0; color:#374151; font-size:13px;">Your template is now live and ready to use!</p>
</div>
HTML;
    }
    
    if ($context['has_tools']) {
        $fulfillmentHtml .= <<<HTML
<div style="background:#f3e8ff; border-left:3px solid #8b5cf6; padding:15px; border-radius:4px; margin:15px 0;">
    <h3 style="color:#6b21a8; margin:0 0 10px 0; font-size:16px;">🔧 Tool Access</h3>
    <p style="margin:0; color:#374151; font-size:13px;">Your digital tools are ready! You will receive access details via WhatsApp or email shortly.</p>
</div>
HTML;
    }
    
    $totalsHtml = '';
    if ($context['has_discount']) {
        $formattedOriginal = formatCurrency($context['original_amount']);
        $formattedDiscount = formatCurrency($context['discount_amount']);
        $totalsHtml = <<<HTML
<p style="margin:5px 0; color:#6b7280;">Subtotal: {$formattedOriginal}</p>
<p style="margin:5px 0; color:#10b981;">Affiliate Discount (20%): -{$formattedDiscount}</p>
HTML;
    }
    
    $formattedTotal = formatCurrency($context['total_amount']);
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">🎉 Payment Confirmed!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Great news! Your payment has been confirmed and your order is now being processed.
</p>

<div style="background:#ffffff; padding:0; border-radius:6px; margin:15px 0; overflow:hidden; border:1px solid #e5e7eb;">
    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="background:#f9fafb; border-bottom:2px solid #e5e7eb;">
                <th style="padding:10px 8px; text-align:left; color:#6b7280; font-size:12px; font-weight:600; text-transform:uppercase;">Product</th>
                <th style="padding:10px 8px; text-align:center; color:#6b7280; font-size:12px; font-weight:600; text-transform:uppercase;">Qty</th>
                <th style="padding:10px 8px; text-align:right; color:#6b7280; font-size:12px; font-weight:600; text-transform:uppercase;">Amount</th>
            </tr>
        </thead>
        <tbody>
            {$productListHtml}
        </tbody>
    </table>
</div>

<div style="background:#f9fafb; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    {$totalsHtml}
    <p style="margin:5px 0; color:#374151; font-weight:700;">Total Paid: {$formattedTotal}</p>
</div>

{$fulfillmentHtml}

<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Thank you for your purchase! If you have any questions, please don't hesitate to contact us.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $customerName);
    return sendEmail($order['customer_email'], $subject, $emailBody);
}

/**
 * Send order confirmation email to customer
 */
function sendOrderConfirmationEmail($orderId, $customerName, $customerEmail, $templateName, $price) {
    $subject = "Order Received - Order #{$orderId}";
    
    $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">Your Order Has Been Received!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Thank you for your order! We've received your request for the <strong>{$templateName}</strong> template.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Template:</strong> {$templateName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Price:</strong> {$price}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Our team will process your order shortly. You'll receive another email once payment is confirmed and your website is ready.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $customerName);
    return sendEmail($customerEmail, $subject, $emailBody);
}

function sendOrderRejectionEmail($orderId, $customerName, $customerEmail, $reason = '') {
    $subject = "Order Cancelled - Order #{$orderId}";
    
    $reasonHtml = '';
    if (!empty($reason)) {
        $reasonHtml = <<<HTML
<div style="background:#fef3c7; border-left:3px solid #f59e0b; padding:15px; border-radius:4px; margin:15px 0;">
    <h3 style="color:#92400e; margin:0 0 8px 0; font-size:16px;">Cancellation Reason</h3>
    <p style="color:#78350f; margin:0; line-height:1.6;">{$reason}</p>
</div>
HTML;
    }
    
    $content = <<<HTML
<h2 style="color:#dc2626; margin:0 0 15px 0; font-size:22px;">Order Cancelled</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    We regret to inform you that your order <strong>#{$orderId}</strong> has been cancelled.
</p>
{$reasonHtml}
<p style="color:#374151; line-height:1.6; margin:15px 0;">
    If you believe this is an error or would like to place a new order, please contact our support team. We're here to help!
</p>
<div style="background:#dbeafe; border-left:3px solid #3b82f6; padding:15px; border-radius:4px; margin:15px 0;">
    <h3 style="color:#1e40af; margin:0 0 8px 0; font-size:16px;">Need Assistance?</h3>
    <p style="color:#1e3a8a; margin:0;">Our support team is available 24/7 on WhatsApp to assist you with any questions or help you place a new order.</p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $customerName);
    $result = sendEmail($customerEmail, $subject, $emailBody);
    
    if ($result) {
        error_log("Order rejection email sent successfully for order #{$orderId}");
    } else {
        error_log("Failed to send order rejection email for order #{$orderId}");
    }
    
    return $result;
}

/**
 * Send new order notification to admin
 * @param int $orderId Order ID
 * @param string $customerName Customer name
 * @param string $customerPhone Customer phone
 * @param string $productNames Product names (comma-separated if multiple)
 * @param string $price Formatted price
 * @param string|null $affiliateCode Affiliate code if applicable
 * @param string $orderType Order type (template/tools/mixed)
 */
function sendNewOrderNotificationToAdmin($orderId, $customerName, $customerPhone, $productNames, $price, $affiliateCode = null, $orderType = 'template') {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "New Order Received - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $orderTypeLabels = [
        'template' => '🎨 Template Order',
        'tool' => '🔧 Tool Order',
        'tools' => '🔧 Tool Order',
        'mixed' => '📦 Mixed Order'
    ];
    $orderTypeLabel = $orderTypeLabels[$orderType] ?? '📦 Order';
    
    $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">📦 New Order Received</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    A new {$orderTypeLabel} has been placed on your website.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Customer:</strong> {$customerName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Phone:</strong> {$customerPhone}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Products:</strong> {$productNames}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Price:</strong> {$price}</p>
    {$affiliateInfo}
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Please check the admin panel to view this order.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody);
}

/**
 * Send affiliate registration confirmation
 */
function sendAffiliateWelcomeEmail($affiliateName, $affiliateEmail, $affiliateCode) {
    $subject = "Welcome to Our Affiliate Program!";
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost:8080';
    $commissionRate = (AFFILIATE_COMMISSION_RATE * 100) . '%';
    $discountRate = (CUSTOMER_DISCOUNT_RATE * 100) . '%';
    
    $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">🎉 Welcome to Our Affiliate Program!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Congratulations! Your affiliate account has been successfully created. You can now start earning commissions.
</p>
<div style="background:#fff; padding:20px; border-radius:6px; margin:15px 0; border:2px solid #3b82f6;">
    <h3 style="color:#1e3a8a; margin:0 0 15px 0; font-size:18px;">Your Affiliate Details</h3>
    <p style="margin:8px 0; color:#374151;"><strong>Affiliate Code:</strong> <span style="background:#f3f4f6; padding:4px 8px; border-radius:4px; font-family:monospace;">{$affiliateCode}</span></p>
    <p style="margin:8px 0; color:#374151;"><strong>Commission Rate:</strong> {$commissionRate}</p>
    <p style="margin:8px 0; color:#374151;"><strong>Customer Discount:</strong> {$discountRate}</p>
</div>
<div style="background:#f9fafb; padding:15px; border-radius:6px; margin:15px 0;">
    <h3 style="color:#1e3a8a; margin:0 0 10px 0; font-size:16px;">Your Referral Link</h3>
    <p style="margin:0; word-break:break-all; color:#374151; font-size:14px;">
        <code style="background:#fff; padding:8px; display:block; border-radius:4px; border:1px solid #e5e7eb;">{$siteUrl}/?aff={$affiliateCode}</code>
    </p>
</div>
<div style="background:#fef3c7; border-left:3px solid #f59e0b; padding:12px; border-radius:4px; margin:15px 0;">
    <p style="margin:0; color:#92400e; font-size:13px;"><strong>⚠️ Important:</strong> This email may land in your spam/junk folder. Please check your spam folder and mark this as "Not Spam" to ensure you receive future updates about your earnings and withdrawals.</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Share your referral link with potential customers. When they purchase through your link, you'll earn {$commissionRate} commission and they'll get a {$discountRate} discount!
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send commission earned notification to affiliate
 * @param string $affiliateName Affiliate name
 * @param string $affiliateEmail Affiliate email
 * @param int $orderId Order ID
 * @param float $commissionAmount Commission amount
 * @param string $productName Product name or description (works for templates, tools, or mixed orders)
 */
function sendCommissionEarnedEmail($affiliateName, $affiliateEmail, $orderId, $commissionAmount, $productName) {
    $formattedAmount = number_format($commissionAmount, 2);
    $subject = "Commission Earned - ₦{$formattedAmount}";
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">💰 You've Earned a Commission!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Great news! You've earned a commission from a successful sale.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Product(s):</strong> {$productName}</p>
    <p style="margin:5px 0; color:#10b981; font-size:20px;"><strong>Commission:</strong> ₦{$formattedAmount}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Your commission is now pending. You can request a withdrawal once you reach the minimum threshold. Log in to your affiliate dashboard to view your earnings.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send withdrawal request notification to admin
 */
function sendWithdrawalRequestToAdmin($affiliateName, $affiliateEmail, $amount, $withdrawalId) {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "Withdrawal Request - ₦{$amount}";
    
    $content = <<<HTML
<h2 style="color:#f59e0b; margin:0 0 15px 0; font-size:22px;">💳 New Withdrawal Request</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    An affiliate has requested a withdrawal.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Withdrawal ID:</strong> #{$withdrawalId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Affiliate:</strong> {$affiliateName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Email:</strong> {$affiliateEmail}</p>
    <p style="margin:5px 0; color:#374151; font-size:18px;"><strong>Amount:</strong> ₦{$amount}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Please review and process this withdrawal request in the admin panel.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody);
}

/**
 * Send withdrawal approved notification to affiliate
 */
function sendWithdrawalApprovedEmail($affiliateName, $affiliateEmail, $amount, $withdrawalId) {
    $subject = "Withdrawal Approved - ₦{$amount}";
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">✅ Withdrawal Approved!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Good news! Your withdrawal request has been approved and is being processed.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Withdrawal ID:</strong> #{$withdrawalId}</p>
    <p style="margin:5px 0; color:#10b981; font-size:20px;"><strong>Amount:</strong> ₦{$amount}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    The payment will be processed to your registered bank account within 24-48 hours. Thank you for being a valued affiliate partner!
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send withdrawal rejected notification to affiliate
 */
function sendWithdrawalRejectedEmail($affiliateName, $affiliateEmail, $amount, $withdrawalId, $reason = '') {
    $subject = "Withdrawal Request Update - ID #{$withdrawalId}";
    
    $reasonText = $reason ? "<p style='color:#374151; margin:10px 0;'><strong>Reason:</strong> {$reason}</p>" : '';
    
    $content = <<<HTML
<h2 style="color:#ef4444; margin:0 0 15px 0; font-size:22px;">Withdrawal Request Update</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    We regret to inform you that your withdrawal request could not be processed at this time.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Withdrawal ID:</strong> #{$withdrawalId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Amount:</strong> ₦{$amount}</p>
    {$reasonText}
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    If you have any questions or concerns, please contact us via WhatsApp. We're here to help!
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send custom email to affiliate (from admin panel)
 */
function sendCustomEmailToAffiliate($affiliateName, $affiliateEmail, $subject, $message) {
    // Sanitize HTML content - remove dangerous attributes while preserving formatting
    $message = sanitizeEmailHtml($message);
    
    // Apply professional styling to the custom message
    $styledMessage = <<<HTML
<div style="color:#374151; line-height:1.8; font-size:15px;">
    {$message}
</div>
HTML;
    
    // Use the affiliate-specific template with crown icon and enhanced styling
    $emailBody = createAffiliateEmailTemplate($subject, $styledMessage, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Sanitize HTML content for emails - removes dangerous tags and attributes
 */
function sanitizeEmailHtml($html) {
    // First, strip all tags except safe formatting ones
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><h2><h3><h4><ul><ol><li><a><span><div>');
    
    // Remove all event handler attributes (onclick, onload, onerror, etc.)
    $html = preg_replace('/<([a-z][a-z0-9]*)\s+[^>]*?(on\w+\s*=\s*["\'][^"\']*["\'])/i', '<$1>', $html);
    $html = preg_replace('/<([a-z][a-z0-9]*)\s+[^>]*?(on\w+\s*=\s*\w+)/i', '<$1>', $html);
    
    // Sanitize all href attributes to remove dangerous protocols
    // First, handle quoted href attributes
    $html = preg_replace_callback(
        '/<a\s+([^>]*href\s*=\s*["\'])([^"\']*)(["\'[^>]*>)/i',
        function($matches) {
            $url = $matches[2];
            
            // Decode HTML entities and trim whitespace
            $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            $url = trim($url);
            
            // Convert to lowercase for protocol check
            $urlLower = strtolower($url);
            
            // Only allow http, https, and mailto protocols (or relative URLs)
            if (preg_match('/^(https?|mailto):/i', $urlLower) || 
                preg_match('/^[\/\.]/', $url) || 
                preg_match('/^#/', $url)) {
                // URL is safe, re-encode and return
                return '<a ' . $matches[1] . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . $matches[3];
            }
            
            // Dangerous or unknown protocol - replace with #
            return '<a ' . $matches[1] . '#' . $matches[3];
        },
        $html
    );
    
    // Second, handle unquoted href attributes (security fix for bypasses)
    $html = preg_replace_callback(
        '/<a\s+([^>]*href\s*=\s*)([^\s>]+)([^>]*>)/i',
        function($matches) {
            // Skip if this is already a quoted attribute (handled above)
            if (preg_match('/^["\']/', $matches[2])) {
                return $matches[0];
            }
            
            $url = $matches[2];
            
            // Decode HTML entities and trim whitespace
            $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
            $url = trim($url);
            
            // Convert to lowercase for protocol check
            $urlLower = strtolower($url);
            
            // Only allow http, https, and mailto protocols (or relative URLs)
            if (preg_match('/^(https?|mailto):/i', $urlLower) || 
                preg_match('/^[\/\.]/', $url) || 
                preg_match('/^#/', $url)) {
                // URL is safe, re-encode with quotes and return
                return '<a ' . $matches[1] . '"' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"' . $matches[3];
            }
            
            // Dangerous or unknown protocol - replace with #
            return '<a ' . $matches[1] . '"#"' . $matches[3];
        },
        $html
    );
    
    // Remove style attributes that contain expressions (IE-specific attacks)
    $html = preg_replace('/\s*style\s*=\s*["\'][^"\']*expression\s*\([^"\']*["\']/', '', $html);
    
    // Remove data-* attributes that could be dangerous
    $html = preg_replace('/\s+data-[a-z0-9_-]+\s*=\s*["\'][^"\']*["\']/i', '', $html);
    
    // Remove all other potentially dangerous attributes
    $dangerous_attrs = ['formaction', 'action', 'onclick', 'onerror', 'onload', 'onmouseover', 
                       'onfocus', 'onblur', 'onchange', 'onsubmit', 'srcdoc', 'src'];
    foreach ($dangerous_attrs as $attr) {
        $html = preg_replace('/\s+' . preg_quote($attr, '/') . '\s*=\s*["\'][^"\']*["\']/i', '', $html);
    }
    
    return $html;
}

/**
 * Create affiliate-specific email template with enhanced styling
 */
function createAffiliateEmailTemplate($subject, $content, $affiliateName = 'Valued Affiliate') {
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost:8080';
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $whatsapp = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '+2349132672126';
    
    $esc_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $esc_name = htmlspecialchars($affiliateName, ENT_QUOTES, 'UTF-8');
    $esc_siteUrl = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');
    $esc_siteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $esc_whatsapp = htmlspecialchars($whatsapp, ENT_QUOTES, 'UTF-8');
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$esc_subject}</title>
</head>
<body style="margin:0; padding:0; background:#f9fafb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">
    <div style="max-width:600px; margin:20px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 6px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background:linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding:30px 20px; text-align:center;">
            <h1 style="color:#ffffff; margin:0; font-size:28px; font-weight:700; letter-spacing:0.5px;">{$esc_siteName}</h1>
            <p style="color:rgba(255,255,255,0.9); margin:8px 0 0 0; font-size:14px;">Affiliate Program</p>
        </div>
        
        <!-- Main Content -->
        <div style="padding:35px 30px;">
            <div style="margin-bottom:25px;">
                <p style="margin:0; font-size:16px; color:#374151;">Hello <strong style="color:#1e3a8a;">{$esc_name}</strong>,</p>
            </div>
            
            <div style="background:#f9fafb; padding:25px; border-left:4px solid #d4af37; border-radius:8px; margin-bottom:30px;">
                {$content}
            </div>
            
            <div style="text-align:center; margin:35px 0;">
                <a href="{$esc_siteUrl}/affiliate/" style="display:inline-block; background:linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color:#ffffff; padding:14px 32px; text-decoration:none; border-radius:8px; font-weight:600; font-size:16px; box-shadow:0 4px 6px rgba(30,58,138,0.3);">
                    View Affiliate Dashboard
                </a>
            </div>
            
            <div style="margin-top:30px; padding-top:25px; border-top:2px solid #e5e7eb;">
                <div style="background:#fffbeb; border-left:4px solid #d4af37; padding:15px; border-radius:6px; margin-bottom:20px;">
                    <p style="margin:0; color:#92400e; font-size:14px; line-height:1.6;">
                        <strong style="color:#78350f;">💡 Quick Tip:</strong> Share your affiliate link with potential customers to earn commissions. Log in to your dashboard to access your unique referral link and track your earnings.
                    </p>
                </div>
                
                <div style="color:#6b7280; font-size:14px; line-height:1.6;">
                    <p style="margin:0 0 10px 0;">Need assistance? We're here to help!</p>
                    <p style="margin:0;">
                        <strong>WhatsApp:</strong> <a href="https://wa.me/{$esc_whatsapp}" style="color:#3b82f6; text-decoration:none; font-weight:600;">{$esc_whatsapp}</a>
                    </p>
                    <p style="margin:15px 0 0 0;">
                        Best regards,<br>
                        <strong style="color:#1e3a8a;">The {$esc_siteName} Team</strong>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="background:#1f2937; color:#9ca3af; padding:20px; text-align:center; font-size:12px;">
            <p style="margin:0;">&copy; 2025 {$esc_siteName}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Send notification when admin replies to support ticket
 */
function sendSupportTicketReplyEmail($affiliateName, $affiliateEmail, $ticketId, $ticketSubject, $adminReply) {
    $subject = "Support Ticket Reply - #{$ticketId}";
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost:8080';
    $escReply = htmlspecialchars($adminReply, ENT_QUOTES, 'UTF-8');
    $escSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:20px;">💬 New Reply to Your Support Ticket</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Our support team has replied to your ticket.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <p style="margin:5px 0; color:#374151;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Subject:</strong> {$escSubject}</p>
</div>
<div style="background:#f0f9ff; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #3b82f6;">
    <p style="margin:0 0 8px 0; color:#1e40af; font-weight:600; font-size:13px;">ADMIN REPLY:</p>
    <p style="margin:0; color:#374151; line-height:1.6; white-space:pre-wrap;">{$escReply}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    You can view this conversation and reply by logging into your affiliate portal.
</p>
HTML;
    
    $emailBody = createAffiliateEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send notification when support ticket is closed
 */
function sendSupportTicketClosedEmail($affiliateName, $affiliateEmail, $ticketId, $ticketSubject) {
    $subject = "Support Ticket Closed - #{$ticketId}";
    $escSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:20px;">✅ Support Ticket Closed</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Your support ticket has been resolved and marked as closed.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <p style="margin:5px 0; color:#374151;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Subject:</strong> {$escSubject}</p>
    <p style="margin:5px 0; color:#10b981;"><strong>Status:</strong> Closed</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    If you need further assistance, feel free to open a new support ticket anytime. We're here to help!
</p>
HTML;
    
    $emailBody = createAffiliateEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send notification to admin when new support ticket is created
 */
function sendNewSupportTicketNotificationToAdmin($ticketId, $affiliateName, $ticketSubject, $ticketMessage, $priority) {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "New Support Ticket - #{$ticketId}";
    $escSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    $escMessage = htmlspecialchars($ticketMessage, ENT_QUOTES, 'UTF-8');
    $escAffiliate = htmlspecialchars($affiliateName, ENT_QUOTES, 'UTF-8');
    
    $priorityColor = $priority === 'high' ? '#ef4444' : ($priority === 'medium' ? '#f59e0b' : '#6b7280');
    $priorityLabel = ucfirst($priority) . ' Priority';
    
    $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">🎫 New Support Ticket Received</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    An affiliate has submitted a new support ticket.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <p style="margin:5px 0; color:#374151;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Affiliate:</strong> {$escAffiliate}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Subject:</strong> {$escSubject}</p>
    <p style="margin:5px 0;"><strong>Priority:</strong> <span style="color:{$priorityColor}; font-weight:600;">{$priorityLabel}</span></p>
</div>
<div style="background:#f9fafb; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:0 0 8px 0; color:#6b7280; font-weight:600; font-size:13px;">MESSAGE:</p>
    <p style="margin:0; color:#374151; line-height:1.6; white-space:pre-wrap;">{$escMessage}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Please check the admin panel to respond to this ticket.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody);
}

/**
 * Send announcement email to a single affiliate
 * @param int $announcementId Announcement ID for tracking
 * @param int $affiliateId Affiliate ID
 * @param string $affiliateName Affiliate name
 * @param string $affiliateEmail Affiliate email
 * @param string $title Announcement title
 * @param string $message Announcement message (HTML)
 * @param string $type Announcement type (info, success, warning, danger)
 * @param PDO $db Database connection for tracking
 * @return bool Success status
 */
function sendAnnouncementEmail($announcementId, $affiliateId, $affiliateName, $affiliateEmail, $title, $message, $type = 'info', $db = null) {
    $subject = "📢 Announcement: " . $title;
    
    // Map announcement types to colors and icons
    $typeConfig = [
        'success' => ['color' => '#10b981', 'icon' => '✅', 'label' => 'Success'],
        'warning' => ['color' => '#f59e0b', 'icon' => '⚠️', 'label' => 'Warning'],
        'danger' => ['color' => '#ef4444', 'icon' => '🚨', 'label' => 'Important'],
        'info' => ['color' => '#3b82f6', 'icon' => '📢', 'label' => 'Information']
    ];
    
    $config = $typeConfig[$type] ?? $typeConfig['info'];
    
    // Sanitize the message HTML
    $sanitizedMessage = sanitizeEmailHtml($message);
    
    $content = <<<HTML
<div style="background:{$config['color']}; color:#ffffff; padding:12px 20px; border-radius:8px; margin-bottom:20px; text-align:center;">
    <p style="margin:0; font-size:18px; font-weight:700;">
        {$config['icon']} {$config['label']}: {$title}
    </p>
</div>
<div style="color:#374151; line-height:1.8; font-size:15px;">
    {$sanitizedMessage}
</div>
HTML;
    
    $emailBody = createAffiliateEmailTemplate($subject, $content, $affiliateName);
    $success = sendEmail($affiliateEmail, $subject, $emailBody);
    
    // Track email delivery in database if connection provided
    if ($db && $announcementId) {
        try {
            $stmt = $db->prepare("
                INSERT INTO announcement_emails 
                (announcement_id, affiliate_id, email_address, failed, error_message) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $failed = $success ? 0 : 1;
            $errorMsg = $success ? null : 'Email sending failed';
            
            $stmt->execute([
                $announcementId,
                $affiliateId,
                $affiliateEmail,
                $failed,
                $errorMsg
            ]);
        } catch (Exception $e) {
            error_log("Failed to track announcement email: " . $e->getMessage());
        }
    }
    
    return $success;
}

/**
 * Send announcement emails to multiple affiliates with batch processing
 * @param int $announcementId Announcement ID
 * @param string $title Announcement title
 * @param string $message Announcement message (HTML)
 * @param string $type Announcement type
 * @param array $affiliates Array of affiliate data (id, name, email)
 * @param PDO $db Database connection
 * @return array Statistics ['total' => int, 'sent' => int, 'failed' => int]
 */
function sendAnnouncementEmails($announcementId, $title, $message, $type, $affiliates, $db) {
    $stats = [
        'total' => count($affiliates),
        'sent' => 0,
        'failed' => 0
    ];
    
    if (empty($affiliates)) {
        return $stats;
    }
    
    // Batch processing: Send 50 emails at a time with 100ms delay
    $batchSize = 50;
    $delay = 100000; // 100ms in microseconds
    
    foreach ($affiliates as $index => $affiliate) {
        $success = sendAnnouncementEmail(
            $announcementId,
            $affiliate['id'],
            $affiliate['name'],
            $affiliate['email'],
            $title,
            $message,
            $type,
            $db
        );
        
        if ($success) {
            $stats['sent']++;
        } else {
            $stats['failed']++;
        }
        
        // Add delay after every batch to prevent overwhelming SMTP server
        if (($index + 1) % $batchSize === 0 && ($index + 1) < count($affiliates)) {
            usleep($delay);
        }
    }
    
    return $stats;
}

/**
 * Send payment success notification to admin (when Paystack payment succeeds)
 */
function sendPaymentSuccessNotificationToAdmin($orderId, $customerName, $customerPhone, $productNames, $price, $affiliateCode = null, $orderType = 'template') {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "✅ Payment Received - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $orderTypeLabels = [
        'template' => '🎨 Template Order',
        'tool' => '🔧 Tool Order',
        'tools' => '🔧 Tool Order',
        'mixed' => '📦 Mixed Order'
    ];
    $orderTypeLabel = $orderTypeLabels[$orderType] ?? '📦 Order';
    
    $content = <<<HTML
<h2 style="color:#16a34a; margin:0 0 15px 0; font-size:22px;">✅ Payment Confirmed</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Payment has been successfully received for the {$orderTypeLabel}.
</p>
<div style="background:#f0fdf4; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #16a34a;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Customer:</strong> {$customerName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Phone:</strong> {$customerPhone}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Products:</strong> {$productNames}</p>
    <p style="margin:5px 0; color:#16a34a; font-weight:bold;"><strong>Amount:</strong> {$price} ✅</p>
    {$affiliateInfo}
    <p style="margin:10px 0 0 0; color:#16a34a; font-weight:bold;"><strong>Status:</strong> PAID</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Proceed with order fulfillment. Customer delivery is automated.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody);
}

/**
 * Send payment failure notification to admin (when Paystack payment fails)
 */
function sendPaymentFailureNotificationToAdmin($orderId, $customerName, $customerPhone, $productNames, $price, $failureReason = '', $affiliateCode = null, $orderType = 'template') {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "❌ Payment Failed - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $failureReasonHtml = '';
    if ($failureReason) {
        $failureReasonHtml = "<p style='margin:5px 0; color:#dc2626;'><strong>Reason:</strong> {$failureReason}</p>";
    }
    
    $orderTypeLabels = [
        'template' => '🎨 Template Order',
        'tool' => '🔧 Tool Order',
        'tools' => '🔧 Tool Order',
        'mixed' => '📦 Mixed Order'
    ];
    $orderTypeLabel = $orderTypeLabels[$orderType] ?? '📦 Order';
    
    $content = <<<HTML
<h2 style="color:#dc2626; margin:0 0 15px 0; font-size:22px;">❌ Payment Failed</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    A payment attempt for the {$orderTypeLabel} has failed.
</p>
<div style="background:#fef2f2; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #dc2626;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Customer:</strong> {$customerName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Phone:</strong> {$customerPhone}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Products:</strong> {$productNames}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Amount:</strong> {$price}</p>
    {$failureReasonHtml}
    {$affiliateInfo}
    <p style="margin:10px 0 0 0; color:#dc2626; font-weight:bold;"><strong>Status:</strong> FAILED</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Customer was notified. They may retry or contact via WhatsApp.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody);
}
