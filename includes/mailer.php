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
    $mail = new PHPMailer(true);
    
    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.example.com';
        $mail->SMTPAuth = true;
        $mail->Username = defined('SMTP_USER') ? SMTP_USER : 'noreply@example.com';
        $mail->Password = defined('SMTP_PASS') ? SMTP_PASS : '';
        $mail->Port = defined('SMTP_PORT') ? SMTP_PORT : 587;
        $mail->SMTPSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';

        // Email Settings
        $mail->isHTML(true);
        $mail->setFrom(
            defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@example.com',
            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME
        );
        $mail->addAddress($email);
        $mail->addReplyTo(
            defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'noreply@example.com',
            defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : SITE_NAME
        );
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
    $siteUrl = defined('SITE_URL') ? SITE_URL : 'http://localhost:8080';
    $siteName = defined('SITE_NAME') ? SITE_NAME : 'WebDaddy Empire';
    $whatsapp = defined('WHATSAPP_NUMBER') ? WHATSAPP_NUMBER : '+2349132672126';
    
    $esc_subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $esc_name = htmlspecialchars($recipientName, ENT_QUOTES, 'UTF-8');
    $esc_siteUrl = htmlspecialchars($siteUrl, ENT_QUOTES, 'UTF-8');
    $esc_siteName = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');
    
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
            <p style="color:rgba(255,255,255,0.9); margin:8px 0 0 0; font-size:14px;">Professional Website Templates</p>
        </div>
        
        <!-- Main Content -->
        <div style="padding:30px 25px;">
            <p style="margin:0 0 20px 0; font-size:16px; color:#374151;">Hello <strong>{$esc_name}</strong>,</p>
            
            <div style="background:#f9fafb; padding:25px; border-left:4px solid #3b82f6; border-radius:8px; margin-bottom:25px;">
                {$content}
            </div>
            
            <div style="text-align:center; margin:30px 0;">
                <a href="{$esc_siteUrl}" style="display:inline-block; background:#1e3a8a; color:#ffffff; padding:14px 32px; text-decoration:none; border-radius:8px; font-weight:600; font-size:16px;">
                    Visit Our Website
                </a>
            </div>
            
            <div style="margin-top:30px; padding-top:20px; border-top:1px solid #e5e7eb; color:#6b7280; font-size:14px;">
                <p style="margin:0;">Need help? Contact us on WhatsApp: <a href="https://wa.me/{$whatsapp}" style="color:#3b82f6; text-decoration:none;">{$whatsapp}</a></p>
                <p style="margin:10px 0 0 0;">Best regards,<br><strong>The {$esc_siteName} Team</strong></p>
            </div>
        </div>
        
        <!-- Footer -->
        <div style="background:#1f2937; color:#9ca3af; padding:20px; text-align:center; font-size:13px;">
            <p style="margin:0 0 10px 0;">&copy; 2025 {$esc_siteName}. All rights reserved.</p>
            <p style="margin:0;">
                <a href="{$esc_siteUrl}" style="color:#60a5fa; text-decoration:none; margin:0 10px;">Home</a> |
                <a href="{$esc_siteUrl}/admin/login.php" style="color:#60a5fa; text-decoration:none; margin:0 10px;">Admin</a> |
                <a href="{$esc_siteUrl}/affiliate/login.php" style="color:#60a5fa; text-decoration:none; margin:0 10px;">Affiliate</a>
            </p>
        </div>
    </div>
</body>
</html>
HTML;
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

/**
 * Send payment confirmation and domain details to customer
 */
function sendPaymentConfirmationEmail($customerName, $customerEmail, $templateName, $domainName, $credentials = null) {
    $subject = "Payment Confirmed - Your Website is Ready!";
    
    $credentialsHtml = '';
    if ($credentials) {
        $credentialsHtml = <<<HTML
<div style="background:#fff; padding:15px; border-radius:6px; margin:15px 0; border:2px dashed #3b82f6;">
    <h3 style="color:#1e3a8a; margin:0 0 10px 0; font-size:16px;">🔐 Your Login Credentials</h3>
    <p style="margin:5px 0; color:#374151;"><strong>Domain:</strong> {$domainName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Username:</strong> {$credentials['username']}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Password:</strong> {$credentials['password']}</p>
</div>
HTML;
    }
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">🎉 Payment Confirmed!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Great news! Your payment has been confirmed and your website is now ready.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Template:</strong> {$templateName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Domain:</strong> {$domainName}</p>
</div>
{$credentialsHtml}
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Your website is now live and ready to use! If you have any questions or need assistance, please don't hesitate to contact us.
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $customerName);
    return sendEmail($customerEmail, $subject, $emailBody);
}

/**
 * Send new order notification to admin
 */
function sendNewOrderNotificationToAdmin($orderId, $customerName, $customerPhone, $templateName, $price, $affiliateCode = null) {
    $adminEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'admin@example.com';
    $subject = "New Order Received - Order #{$orderId}";
    
    $affiliateInfo = '';
    if ($affiliateCode) {
        $affiliateInfo = "<p style='margin:5px 0; color:#374151;'><strong>Affiliate Code:</strong> {$affiliateCode}</p>";
    }
    
    $content = <<<HTML
<h2 style="color:#1e3a8a; margin:0 0 15px 0; font-size:22px;">📦 New Order Received</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    A new order has been placed on your website.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Customer:</strong> {$customerName}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Phone:</strong> {$customerPhone}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Template:</strong> {$templateName}</p>
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
<p style="color:#374151; line-height:1.6; margin:15px 0 0 0;">
    Share your referral link with potential customers. When they purchase through your link, you'll earn {$commissionRate} commission and they'll get a {$discountRate} discount!
</p>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}

/**
 * Send commission earned notification to affiliate
 */
function sendCommissionEarnedEmail($affiliateName, $affiliateEmail, $orderId, $commissionAmount, $templateName) {
    $formattedAmount = number_format($commissionAmount, 2);
    $subject = "Commission Earned - ₦{$formattedAmount}";
    
    $content = <<<HTML
<h2 style="color:#10b981; margin:0 0 15px 0; font-size:22px;">💰 You've Earned a Commission!</h2>
<p style="color:#374151; line-height:1.6; margin:0 0 15px 0;">
    Great news! You've earned a commission from a successful sale.
</p>
<div style="background:#ffffff; padding:15px; border-radius:6px; margin:15px 0;">
    <p style="margin:5px 0; color:#374151;"><strong>Order ID:</strong> #{$orderId}</p>
    <p style="margin:5px 0; color:#374151;"><strong>Template:</strong> {$templateName}</p>
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
    $content = <<<HTML
<div style="color:#374151; line-height:1.6;">
    {$message}
</div>
HTML;
    
    $emailBody = createEmailTemplate($subject, $content, $affiliateName);
    return sendEmail($affiliateEmail, $subject, $emailBody);
}
