<?php
require_once 'includes/config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

echo "\n========================================\n";
echo "GMAIL SMTP OTP TEST\n";
echo "========================================\n";
echo "Gmail: " . GMAIL_OTP_USER . "\n";
echo "Testing port 587 with TLS...\n\n";

$mail = new PHPMailer(true);

try {
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->SMTPAuth = true;
    $mail->Username = GMAIL_OTP_USER;
    $mail->Password = GMAIL_OTP_APP_PASSWORD;
    $mail->Timeout = 20;
    $mail->SMTPKeepAlive = false;
    
    $mail->setFrom(GMAIL_OTP_USER, 'WebDaddy');
    $mail->addAddress('test@yopmail.com');
    $mail->isHTML(false);
    $mail->CharSet = 'UTF-8';
    $mail->Subject = 'Test OTP Code';
    $mail->Body = 'Your OTP is 123456. Expires in 10 minutes.';
    
    echo "Attempting to send...\n\n";
    if ($mail->send()) {
        echo "\n✅✅✅ SUCCESS! Email was sent!\n";
        echo "========================================\n";
    } else {
        echo "\n❌ Send failed: " . $mail->ErrorInfo . "\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ Exception: " . $e->getMessage() . "\n";
}
