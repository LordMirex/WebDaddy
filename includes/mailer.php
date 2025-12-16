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
    // SECURITY: Validate FROM email is configured
    if (!defined('SMTP_FROM_EMAIL') || empty(SMTP_FROM_EMAIL)) {
        error_log("Email sending failed: SMTP_FROM_EMAIL not configured");
        return false;
    }
    
    $mail = new PHPMailer(true);
    
    // Set timeout to prevent hanging
    $mail->Timeout = 10; // 10 second timeout
    
    try {
        // Use SMTP with SSL (port 465)
        $mail->isSMTP();
        $mail->Host = 'mail.webdaddy.online';
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_FROM_EMAIL;
        $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->Port = 465;
        $mail->SMTPSecure = 'ssl';
        
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
        // Force UTF-8 encoding
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Email configuration
        $mail->isHTML(true);
        $mail->setFrom(SMTP_FROM_EMAIL, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME);
        $mail->addAddress($email);
        $mail->addReplyTo(SMTP_FROM_EMAIL, defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        // ============================================================
        // MINIMAL HEADERS - Prevent email bouncing
        // ============================================================
        
        // Critical headers for delivery
        $mail->ReturnPath = SMTP_FROM_EMAIL;
        
        // Mark as transactional (not bulk/marketing)
        $mail->addCustomHeader('X-Category', 'transaction');
        $mail->addCustomHeader('Precedence', 'bulk');
        
        // Add plain text alternative for better inbox placement
        $plainText = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], "\n", $message));
        $mail->AltBody = $plainText;

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed to {$email}: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Create professional email template wrapper
 * Enhanced for better deliverability and professional appearance
 * @param string $subject Email subject
 * @param string $content Main email content (HTML)
 * @param string $recipientName Recipient's name
 * @return string Complete HTML email
 */
function createEmailTemplate($subject, $content, $recipientName = 'Valued Customer') {
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $whatsapp = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '+2349132672126';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online';
    $supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'admin@webdaddy.online';
    $currentYear = date('Y');
    
    $esc_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $esc_name = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $esc_siteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    $esc_siteUrl = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');
    $cleanWhatsapp = preg_replace('/[^0-9]/', '', $whatsapp);
    
    return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <meta name="x-apple-disable-message-reformatting">
    <title>{$esc_subject}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; font-size: 15px; line-height: 1.65; color: #374151; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    <!-- Preheader text (hidden but visible in inbox preview) -->
    <div style="display:none; max-height:0; overflow:hidden; mso-hide:all;">
        {$esc_subject} - Thank you for choosing {$esc_siteName}
    </div>
    
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f4f6f9;">
        <tr>
            <td align="center" style="padding: 30px 15px;">
                <!-- Main Container -->
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%;">
                    
                    <!-- Header with Logo/Brand -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); background-color: #1e3a8a; padding: 30px 40px; text-align: center; border-radius: 12px 12px 0 0;">
                            <h1 style="margin: 0; font-size: 28px; color: #ffffff; font-weight: 700; letter-spacing: -0.5px;">{$esc_siteName}</h1>
                            <p style="margin: 8px 0 0 0; font-size: 14px; color: #bfdbfe; font-weight: 400;">Professional Website Templates &amp; Digital Tools</p>
                        </td>
                    </tr>
                    
                    <!-- Main Content Area -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 40px 40px 30px 40px;">
                            <p style="margin: 0 0 25px 0; font-size: 16px; color: #374151;">Hello <strong style="color: #1e3a8a;">{$esc_name}</strong>,</p>
                            
                            <div style="margin: 0; padding: 25px; background-color: #f8fafc; border-radius: 10px; border-left: 4px solid #3b82f6;">
                                {$content}
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Support Section -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 0 40px 30px 40px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #eff6ff; border-radius: 10px; padding: 25px;">
                                <tr>
                                    <td style="padding: 25px;">
                                        <p style="margin: 0 0 15px 0; font-size: 16px; color: #1e40af; font-weight: 600;">Need Help?</p>
                                        <p style="margin: 0 0 10px 0; font-size: 14px; color: #374151;">
                                            <span style="display: inline-block; width: 20px;">&#9993;</span>
                                            <a href="mailto:{$supportEmail}" style="color: #1e3a8a; text-decoration: none; font-weight: 500;">{$supportEmail}</a>
                                        </p>
                                        <p style="margin: 0; font-size: 14px; color: #374151;">
                                            <span style="display: inline-block; width: 20px;">&#128172;</span>
                                            <a href="https://wa.me/{$cleanWhatsapp}" style="color: #1e3a8a; text-decoration: none; font-weight: 500;">WhatsApp Support</a>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #1e293b; padding: 30px 40px; border-radius: 0 0 12px 12px; text-align: center;">
                            <p style="margin: 0 0 10px 0; font-size: 14px; color: #94a3b8;">
                                Best regards,<br>
                                <strong style="color: #ffffff;">The {$esc_siteName} Team</strong>
                            </p>
                            <p style="margin: 0 0 15px 0;">
                                <a href="{$esc_siteUrl}" style="color: #60a5fa; text-decoration: none; font-size: 14px;">{$esc_siteUrl}</a>
                            </p>
                            <p style="margin: 0; font-size: 12px; color: #64748b;">
                                &copy; {$currentYear} {$esc_siteName}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
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
 * Simple, clean format - won't trigger spam filters
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
    
    // Build product list with commas (not separate lines)
    $productNames = [];
    foreach ($context['items'] as $item) {
        $productNames[] = htmlspecialchars($item['name']);
    }
    $productListHtml = '<p style="margin: 5px 0; color: #374151;"><strong>Products:</strong> ' . implode(', ', $productNames) . '</p>';
    
    $formattedTotal = formatCurrency($context['total_amount']);
    
    $content = '<h2 style="color: #10b981; margin: 0 0 15px 0;">Payment Confirmed!</h2>';
    $content .= '<p style="color: #374151; line-height: 1.6; margin: 0 0 15px 0;">';
    $content .= 'Your payment has been received and verified. Your order is being processed and will be delivered shortly.';
    $content .= '</p>';
    
    $content .= '<p style="margin: 5px 0; color: #374151;"><strong>Order ID:</strong> #' . $orderId . '</p>';
    $content .= $productListHtml;
    $content .= '<p style="margin: 5px 0; color: #ea580c;"><strong>Amount Paid:</strong> ' . $formattedTotal . '</p>';
    
    $content .= '<p style="color: #374151; line-height: 1.6; margin: 15px 0 0 0;">';
    $content .= 'You will receive another email shortly with download links and delivery details.';
    $content .= '</p>';
    
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
/**
 * Send payment confirmation email to customer
 * Simple, clean format that won't trigger spam filters
 */
function sendPaymentConfirmationEmail($customerEmail, $customerName, $orderId, $totalAmount, $paymentMethod = 'paystack') {
    if (empty($customerEmail)) {
        error_log("Payment confirmation email: No email provided");
        return false;
    }
    
    $subject = "Payment Confirmed - Order #{$orderId}";
    
    $body = '<h2 style="color: #10b981; margin: 0 0 15px 0;">Payment Confirmed!</h2>';
    
    $body .= '<p style="color: #374151; margin: 0 0 15px 0; line-height: 1.6;">';
    $body .= 'Your payment has been received and verified. Your order is being processed and will be delivered shortly.';
    $body .= '</p>';
    
    $body .= '<p style="color: #374151; margin: 0 0 10px 0;"><strong>Order ID:</strong> #' . $orderId . '</p>';
    $body .= '<p style="color: #ea580c; margin: 0 0 15px 0;"><strong>Amount Paid:</strong> ₦' . number_format($totalAmount, 2) . '</p>';
    
    $body .= '<p style="color: #374151; margin: 0; line-height: 1.6;">';
    $body .= 'You will receive another email shortly with download links and delivery details.';
    $body .= '</p>';
    
    return sendEmail($customerEmail, $subject, createEmailTemplate($subject, $body, $customerName));
}

/**
 * Send order success email - simple confirmation
 * Used for manual payment orders
 */
function sendOrderSuccessEmail($customerEmail, $customerName, $orderId, $orderItems = [], $affiliateCode = null) {
    if (empty($customerEmail)) {
        error_log("Order success email: No email provided");
        return false;
    }
    
    $subject = "Order #{$orderId} Received";
    
    $body = '<h2 style="color: #1e3a8a; margin: 0 0 20px 0;">Order Received!</h2>';
    $body .= '<p style="color: #374151; margin: 0 0 15px 0;">Thank you for your order.</p>';
    
    $body .= '<p style="color: #374151; margin: 0 0 10px 0;"><strong>Order ID:</strong> #' . $orderId . '</p>';
    $body .= '<p style="color: #374151; margin: 0 0 20px 0;"><strong>Date:</strong> ' . date('F j, Y \a\t g:i A') . '</p>';
    
    if (!empty($orderItems)) {
        $body .= '<p style="color: #1e3a8a; margin: 20px 0 10px 0; font-weight: bold;">Order Items:</p>';
        foreach ($orderItems as $item) {
            $body .= '<p style="color: #374151; margin: 5px 0;">';
            $body .= htmlspecialchars($item['name'] ?? 'Product');
            if (!empty($item['price'])) {
                $body .= ' - ₦' . number_format($item['price'], 2);
            }
            $body .= '</p>';
        }
    }
    
    $body .= '<p style="color: #374151; margin: 25px 0 0 0; line-height: 1.6;">';
    $body .= 'Complete your payment to receive your download links and product access. We will notify you once payment is verified.';
    $body .= '</p>';
    
    return sendEmail($customerEmail, $subject, createEmailTemplate($subject, $body, $customerName));
}

function sendNewOrderNotificationToAdmin($orderId, $customerName, $customerPhone, $productNames, $price, $affiliateCode = null, $orderType = 'template') {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "New Order Received - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $orderTypeLabels = [
        'template' => 'Template Order',
        'tool' => 'Tool Order',
        'tools' => 'Tool Order',
        'mixed' => 'Mixed Order'
    ];
    $orderTypeLabel = $orderTypeLabels[$orderType] ?? 'Order';
    
    $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">New Order Received</h2>
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
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">Welcome to Our Affiliate Program!</h2>
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
    <p style="margin:0; color:#92400e; font-size:13px;"><strong>Important:</strong> This email may land in your spam/junk folder. Please check your spam folder and mark this as "Not Spam" to ensure you receive future updates about your earnings and withdrawals.</p>
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
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">You've Earned a Commission!</h2>
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
<h2 style="color:#f59e0b; margin:0 0 15px 0; font-size:22px;">New Withdrawal Request</h2>
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
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">Withdrawal Approved!</h2>
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
                        <strong style="color:#78350f;">Quick Tip:</strong> Share your affiliate link with potential customers to earn commissions. Log in to your dashboard to access your unique referral link and track your earnings.
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
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:20px;">New Reply to Your Support Ticket</h2>
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
 * Send notification when admin replies to customer support ticket
 */
function sendCustomerTicketReplyEmail($customerName, $customerEmail, $ticketId, $ticketSubject, $adminReply) {
    $subject = "Support Ticket Reply - #{$ticketId}";
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost:8080';
    $escReply = htmlspecialchars($adminReply, ENT_QUOTES, 'UTF-8');
    $escSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<h2 style="color:#3b82f6; margin:0 0 15px 0; font-size:20px;">New Reply to Your Support Ticket</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Our support team has replied to your ticket.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0; border:1px solid #e5e7eb;">
    <p style="margin:5px 0; color:#374151;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Subject:</strong> {$escSubject}</p>
</div>
<div style="background:#f0f9ff; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #3b82f6;">
    <p style="margin:0 0 8px 0; color:#1e40af; font-weight:600; font-size:13px;">SUPPORT REPLY:</p>
    <p style="margin:0; color:#374151; line-height:1.6; white-space:pre-wrap;">{$escReply}</p>
</div>
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    <a href="{$siteUrl}/user/ticket.php?id={$ticketId}" style="background:#f59e0b; color:#ffffff; padding:10px 20px; border-radius:6px; text-decoration:none; display:inline-block; font-weight:600;">View Ticket</a>
</p>
<p style="color:#6b7280; font-size:13px; margin:15px 0 0 0;">
    You can view this conversation and reply by logging into your account.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $customerName);
    return sendEmail($customerEmail, $subject, $emailBody);
}

/**
 * Send notification when support ticket is closed
 */
function sendSupportTicketClosedEmail($affiliateName, $affiliateEmail, $ticketId, $ticketSubject) {
    $subject = "Support Ticket Closed - #{$ticketId}";
    $escSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:20px;">Support Ticket Closed</h2>
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
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">New Support Ticket Received</h2>
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
    $subject = "Payment Received - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $orderTypeLabels = [
        'template' => 'Template Order',
        'tool' => 'Tool Order',
        'tools' => 'Tool Order',
        'mixed' => 'Mixed Order'
    ];
    $orderTypeLabel = $orderTypeLabels[$orderType] ?? 'Order';
    
    $content = <<<HTML
<h2 style="color:#16a34a; margin:0 0 15px 0; font-size:22px;">Payment Confirmed</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Payment has been successfully received for the {$orderTypeLabel}.
</p>
<div style="background:#f0fdf4; padding:15px; border-radius:6px; margin:15px 0; border-left:4px solid #16a34a;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Customer:</strong> {$customerName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Phone:</strong> {$customerPhone}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Products:</strong> {$productNames}</p>
    <p style="margin:5px 0; color:#16a34a; font-weight:bold;"><strong>Amount:</strong> {$price}</p>
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
    $subject = "Payment Failed - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $failureReasonHtml = '';
    if ($failureReason) {
        $failureReasonHtml = "<p style='margin:5px 0; color:#dc2626;'><strong>Reason:</strong> {$failureReason}</p>";
    }
    
    $orderTypeLabels = [
        'template' => 'Template Order',
        'tool' => 'Tool Order',
        'tools' => 'Tool Order',
        'mixed' => 'Mixed Order'
    ];
    $orderTypeLabel = $orderTypeLabels[$orderType] ?? 'Order';
    
    $content = <<<HTML
<h2 style="color:#dc2626; margin:0 0 15px 0; font-size:22px;">Payment Failed</h2>
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

// ============================================================================
// CUSTOMER ACCOUNT EMAIL FUNCTIONS
// Added for customer authentication, account management, and support
// ============================================================================

/**
 * Send OTP verification email (High Priority via Resend)
 * Used for email verification during registration/login
 * Uses Resend REST API for faster, more reliable delivery
 * @param string $email Customer email
 * @param string $otpCode The OTP code
 * @return bool Success status
 */
function sendOTPEmail($email, $otpCode) {
    require_once __DIR__ . '/resend.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $subject = "Your Verification Code - " . $siteName;
    $escOtp = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #1e3a8a; margin: 0 0 10px 0; font-size: 22px;">Email Verification</h2>
    <p style="color: #374151; margin: 0;">Use the code below to verify your email address.</p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <div style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); padding: 20px 40px; border-radius: 10px;">
        <span style="font-size: 32px; font-weight: 700; color: #ffffff; letter-spacing: 8px; font-family: 'Courier New', monospace;">{$escOtp}</span>
    </div>
</div>

<div style="text-align: center; margin-top: 20px;">
    <p style="color: #dc2626; font-size: 14px; margin: 0;">
        <strong>⏱️ This code expires in 10 minutes</strong>
    </p>
    <p style="color: #6b7280; font-size: 13px; margin: 10px 0 0 0;">
        If you didn't request this code, please ignore this email.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Customer');
    
    $result = sendResendEmail($email, $subject, $emailBody, $siteName, 'otp');
    
    if ($result['success']) {
        return true;
    }
    
    error_log("Resend OTP email failed, falling back to SMTP: " . ($result['error'] ?? 'Unknown error'));
    return sendEmail($email, $subject, $emailBody);
}

/**
 * Send welcome email to new customer (Normal Priority)
 * @param string $email Customer email
 * @param string $name Customer name
 * @return bool Success status
 */
function sendCustomerWelcomeEmail($email, $name) {
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online';
    $subject = "Welcome to " . $siteName . "!";
    $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $accountUrl = htmlspecialchars($siteUrl . '/user/', ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #1e3a8a; margin: 0 0 10px 0; font-size: 22px;">🎉 Welcome Aboard!</h2>
</div>

<p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0;">
    Your account has been successfully created. Here's what you can now do:
</p>

<div style="background: #ffffff; border-radius: 8px; padding: 15px; margin: 20px 0;">
    <ul style="color: #374151; margin: 0; padding-left: 20px; line-height: 2;">
        <li><strong>📦 Track Orders</strong> - Monitor all your purchases in one place</li>
        <li><strong>📥 Access Purchases</strong> - Download your tools and templates anytime</li>
        <li><strong>🎫 Get Support</strong> - Create and manage support tickets</li>
        <li><strong>⚡ Faster Checkout</strong> - Your details are saved for quick purchases</li>
    </ul>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$accountUrl}" style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-weight: 600; font-size: 16px;">Go to My Account</a>
</div>

<p style="color: #6b7280; font-size: 13px; text-align: center; margin: 20px 0 0 0;">
    Thank you for joining us. We're excited to have you!
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $escName);
    return queueEmail($email, 'customer_welcome', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}

/**
 * Send password set confirmation email (Normal Priority)
 * @param string $email Customer email
 * @param string $name Customer name
 * @return bool Success status
 */
function sendPasswordSetEmail($email, $name) {
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $subject = "Password Set Successfully - " . $siteName;
    $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $escEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <div style="display: inline-block; background: #dcfce7; border-radius: 50%; padding: 15px; margin-bottom: 10px;">
        <span style="font-size: 32px;">✅</span>
    </div>
    <h2 style="color: #16a34a; margin: 0; font-size: 22px;">Password Set Successfully</h2>
</div>

<p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0;">
    Your password has been successfully set. You can now log in to your account using your email and password.
</p>

<div style="background: #ffffff; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #16a34a;">
    <p style="color: #374151; margin: 0;"><strong>📧 Login Email:</strong> {$escEmail}</p>
</div>

<div style="background: #fef3c7; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="color: #92400e; margin: 0; font-size: 14px;">
        <strong>🔒 Security Tip:</strong> Never share your password with anyone. Our team will never ask for your password.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $escName);
    return queueEmail($email, 'password_set', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}

/**
 * Send password reset link email (High Priority)
 * @param string $email Customer email
 * @param string $name Customer name
 * @param string $resetLink The password reset URL
 * @return bool Success status
 */
function sendPasswordResetEmail($email, $name, $resetLink) {
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $subject = "Reset Your Password - " . $siteName;
    $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $escLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #1e3a8a; margin: 0 0 10px 0; font-size: 22px;">Password Reset Request</h2>
    <p style="color: #374151; margin: 0;">Click the button below to reset your password.</p>
</div>

<div style="text-align: center; margin: 30px 0;">
    <a href="{$escLink}" style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-weight: 600; font-size: 16px;">Reset Password</a>
</div>

<div style="text-align: center; margin: 20px 0;">
    <p style="color: #dc2626; font-size: 14px; margin: 0;">
        <strong>⏱️ This link expires in 1 hour</strong>
    </p>
</div>

<div style="background: #fef3c7; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="color: #92400e; margin: 0; font-size: 14px;">
        <strong>⚠️ Didn't request this?</strong> If you didn't request a password reset, please ignore this email. Your password will remain unchanged.
    </p>
</div>

<p style="color: #6b7280; font-size: 12px; margin: 20px 0 0 0; word-break: break-all;">
    If the button doesn't work, copy and paste this link: {$escLink}
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $escName);
    $queued = queueHighPriorityEmail($email, 'password_reset', $subject, $emailBody);
    if ($queued) {
        processHighPriorityEmails();
    }
    return $queued !== false;
}

/**
 * Send recovery OTP email for password reset (High Priority via Resend)
 * Different styling from regular OTP to indicate security-sensitive action
 * Uses Resend REST API for faster, more reliable delivery
 * @param string $email Customer email
 * @param string $otpCode The OTP code
 * @return bool Success status
 */
function sendRecoveryOTPEmail($email, $otpCode) {
    require_once __DIR__ . '/resend.php';
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $subject = "Password Reset Code - " . $siteName;
    $escOtp = htmlspecialchars($otpCode, ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #dc2626; margin: 0 0 10px 0; font-size: 22px;">🔐 Password Reset</h2>
    <p style="color: #374151; margin: 0;">Use this code to reset your password.</p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <div style="display: inline-block; background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%); padding: 20px 40px; border-radius: 10px;">
        <span style="font-size: 32px; font-weight: 700; color: #ffffff; letter-spacing: 8px; font-family: 'Courier New', monospace;">{$escOtp}</span>
    </div>
</div>

<div style="text-align: center; margin: 20px 0;">
    <p style="color: #dc2626; font-size: 14px; margin: 0;">
        <strong>⏱️ This code expires in 10 minutes</strong>
    </p>
</div>

<div style="background: #fef2f2; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #dc2626;">
    <p style="color: #991b1b; margin: 0; font-size: 14px;">
        <strong>🚨 Security Alert:</strong> If you did not request this password reset, someone may be trying to access your account. Please ignore this email and consider changing your password.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Customer');
    
    $result = sendResendEmail($email, $subject, $emailBody, $siteName, 'recovery_otp');
    
    if ($result['success']) {
        return true;
    }
    
    error_log("Resend recovery OTP email failed, falling back to SMTP queue: " . ($result['error'] ?? 'Unknown error'));
    $queued = queueHighPriorityEmail($email, 'recovery_otp', $subject, $emailBody);
    if ($queued) {
        processHighPriorityEmails();
    }
    return $queued !== false;
}

/**
 * Send template delivery notification (Normal Priority)
 * Notifies customer their website is live - credentials in dashboard only for security
 * @param string $email Customer email
 * @param string $name Customer name
 * @param int $orderId Order ID
 * @param string $templateName Template/product name
 * @param string $websiteUrl The live website URL
 * @return bool Success status
 */
function sendTemplateDeliveryNotification($email, $name, $orderId, $templateName, $websiteUrl) {
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online';
    $subject = "Your Website Is Live! - " . $siteName;
    $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $escTemplate = htmlspecialchars($templateName, ENT_QUOTES, 'UTF-8');
    $escWebsiteUrl = htmlspecialchars($websiteUrl, ENT_QUOTES, 'UTF-8');
    $dashboardUrl = htmlspecialchars($siteUrl . '/user/order-detail.php?id=' . intval($orderId), ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <div style="display: inline-block; background: #dcfce7; border-radius: 50%; padding: 15px; margin-bottom: 10px;">
        <span style="font-size: 32px;">🎉</span>
    </div>
    <h2 style="color: #16a34a; margin: 0; font-size: 22px;">Your Website Is Live!</h2>
</div>

<p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0;">
    Great news! Your <strong>{$escTemplate}</strong> website has been set up and is now live.
</p>

<div style="background: #f0fdf4; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: center; border: 2px solid #16a34a;">
    <p style="color: #374151; margin: 0 0 10px 0; font-size: 14px;">🌐 Your Website URL:</p>
    <a href="{$escWebsiteUrl}" style="color: #1e3a8a; font-size: 18px; font-weight: 600; word-break: break-all; text-decoration: none;">{$escWebsiteUrl}</a>
</div>

<div style="background: #ffffff; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #3b82f6;">
    <p style="color: #374151; margin: 0; font-size: 14px;">
        <strong>📋 Order ID:</strong> #{$orderId}
    </p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$dashboardUrl}" style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-weight: 600; font-size: 16px;">View Credentials in Dashboard</a>
</div>

<div style="background: #fef3c7; border-radius: 8px; padding: 15px; margin: 20px 0; border-left: 4px solid #f59e0b;">
    <p style="color: #92400e; margin: 0; font-size: 14px;">
        <strong>🔒 Security Note:</strong> For your protection, login credentials are only available in your dashboard. Never share them via email.
    </p>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $escName);
    return queueEmail($email, 'template_delivery_notification', $subject, strip_tags($content), $emailBody, $orderId, null, 'normal') !== false;
}

/**
 * Send ticket confirmation email (Normal Priority)
 * @param string $email Customer email
 * @param string $name Customer name
 * @param int $ticketId Ticket ID
 * @param string $ticketSubject Ticket subject
 * @return bool Success status
 */
function sendTicketConfirmationEmail($email, $name, $ticketId, $ticketSubject) {
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online';
    $subject = "Support Ticket #" . intval($ticketId) . " Created - " . $siteName;
    $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $escTicketSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    $ticketUrl = htmlspecialchars($siteUrl . '/user/ticket.php?id=' . intval($ticketId), ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #1e3a8a; margin: 0 0 10px 0; font-size: 22px;">🎫 Support Ticket Created</h2>
    <p style="color: #374151; margin: 0;">We've received your support request.</p>
</div>

<div style="background: #ffffff; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6;">
    <p style="color: #374151; margin: 0 0 10px 0;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="color: #374151; margin: 0;"><strong>Subject:</strong> {$escTicketSubject}</p>
</div>

<p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0;">
    Our support team will review your request and respond as soon as possible. You'll receive an email notification when we reply.
</p>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$ticketUrl}" style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-weight: 600; font-size: 16px;">View Ticket</a>
</div>

<p style="color: #6b7280; font-size: 13px; text-align: center; margin: 20px 0 0 0;">
    You can also reply to this ticket directly from your dashboard.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $escName);
    return queueEmail($email, 'ticket_confirmation', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}

/**
 * Send ticket reply notification email (Normal Priority)
 * @param string $email Customer email
 * @param string $name Customer name
 * @param int $ticketId Ticket ID
 * @param string $ticketSubject Ticket subject
 * @return bool Success status
 */
function sendTicketReplyNotificationEmail($email, $name, $ticketId, $ticketSubject) {
    require_once __DIR__ . '/email_queue.php';
    
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online';
    $subject = "New Reply to Ticket #" . intval($ticketId) . " - " . $siteName;
    $escName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $escTicketSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    $ticketUrl = htmlspecialchars($siteUrl . '/user/ticket.php?id=' . intval($ticketId), ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<div style="text-align: center; margin-bottom: 20px;">
    <h2 style="color: #1e3a8a; margin: 0 0 10px 0; font-size: 22px;">💬 New Reply to Your Ticket</h2>
</div>

<p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0;">
    Our support team has replied to your ticket. Click below to view the response.
</p>

<div style="background: #ffffff; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6;">
    <p style="color: #374151; margin: 0 0 10px 0;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="color: #374151; margin: 0;"><strong>Subject:</strong> {$escTicketSubject}</p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$ticketUrl}" style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-weight: 600; font-size: 16px;">View Ticket</a>
</div>

<p style="color: #6b7280; font-size: 13px; text-align: center; margin: 20px 0 0 0;">
    You can continue the conversation by replying in your dashboard.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $escName);
    return queueEmail($email, 'ticket_reply_notification', $subject, strip_tags($content), $emailBody, null, null, 'normal') !== false;
}

/**
 * Send new customer ticket notification to admin (Direct Send)
 * @param int $ticketId Ticket ID
 * @param string $customerEmail Customer email
 * @param string $customerName Customer name
 * @param string $ticketSubject Ticket subject
 * @param string $ticketCategory Ticket category
 * @return bool Success status
 */
function sendNewCustomerTicketNotification($ticketId, $customerEmail, $customerName, $ticketSubject, $ticketCategory) {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'https://webdaddy.online';
    $subject = "New Customer Ticket #" . intval($ticketId);
    
    $escCustomerName = htmlspecialchars($customerName, ENT_QUOTES, 'UTF-8');
    $escCustomerEmail = htmlspecialchars($customerEmail, ENT_QUOTES, 'UTF-8');
    $escTicketSubject = htmlspecialchars($ticketSubject, ENT_QUOTES, 'UTF-8');
    $escCategory = htmlspecialchars($ticketCategory, ENT_QUOTES, 'UTF-8');
    $ticketUrl = htmlspecialchars($siteUrl . '/admin/customer-tickets.php?view=' . intval($ticketId), ENT_QUOTES, 'UTF-8');
    
    $content = <<<HTML
<h2 style="color: #1e3a8a; margin: 0 0 15px 0; font-size: 22px;">🎫 New Customer Support Ticket</h2>

<p style="color: #374151; line-height: 1.6; margin: 0 0 20px 0;">
    A customer has submitted a new support ticket.
</p>

<div style="background: #ffffff; border-radius: 8px; padding: 20px; margin: 20px 0; border-left: 4px solid #3b82f6;">
    <p style="color: #374151; margin: 5px 0;"><strong>Ticket ID:</strong> #{$ticketId}</p>
    <p style="color: #374151; margin: 5px 0;"><strong>Category:</strong> {$escCategory}</p>
    <p style="color: #374151; margin: 5px 0;"><strong>Subject:</strong> {$escTicketSubject}</p>
</div>

<div style="background: #f3f4f6; border-radius: 8px; padding: 15px; margin: 20px 0;">
    <p style="color: #1e3a8a; margin: 0 0 10px 0; font-weight: 600;">Customer Details:</p>
    <p style="color: #374151; margin: 5px 0;"><strong>Name:</strong> {$escCustomerName}</p>
    <p style="color: #374151; margin: 5px 0;"><strong>Email:</strong> {$escCustomerEmail}</p>
</div>

<div style="text-align: center; margin: 25px 0;">
    <a href="{$ticketUrl}" style="display: inline-block; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%); color: #ffffff; text-decoration: none; padding: 14px 35px; border-radius: 8px; font-weight: 600; font-size: 16px;">View Ticket in Admin</a>
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, 'Admin');
    return sendEmail($adminEmail, $subject, $emailBody);
}
