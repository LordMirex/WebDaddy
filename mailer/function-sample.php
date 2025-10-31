<?php 
include 'config.php';
include 'db.php';

include 'mailer/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

require_once "mailer/PHPMailer.php";
require_once "mailer/SMTP.php";
require_once "mailer/Exception.php";

/**
 * Send email using PHPMailer with updated configuration
 * @param string $email Recipient email address
 * @param string $subject Email subject
 * @param string $message Email body
 * @return bool
 */
function sendMail($email, $subject, $message) {
    $mail = new PHPMailer(true);
    try {
        // SMTP Settings
        $mail->isSMTP();
        $mail->Host = "mail.teslareturns.online";
        $mail->SMTPAuth = true;
        $mail->Username = "support@teslareturns.online";
        $mail->Password = 'ItuZq%kF%5oE'; // Note: Replace with actual password
        $mail->Port = 465;
        $mail->SMTPSecure = "ssl";

        // Email Settings
        $mail->isHTML(true);
        $mail->setFrom('support@teslareturns.online', 'Tesla Returns');
        $mail->addAddress($email);
        $mail->AddReplyTo("support@teslareturns.online", "Tesla Returns");
        $mail->Subject = $subject;
        $mail->MsgHTML($message);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Create a unified email template for all email types
 * @param string $subject Email subject
 * @param string $message Email message content
 * @param string $user_name Recipient's full name
 * @param string $email_type Type of email (single|bulk|newsletter)
 * @return string HTML email content
 */
 function createUnifiedEmailTemplate($subject, $message, $user_name, $email_type = 'single') {
    global $siteurl, $sitename;
    $siteurl = $siteurl ?? 'https://teslareturns.online';
    $sitename = $sitename ?? 'Tesla Returns';

    // Escape variables for safe output
    $esc_subject = htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $esc_user_name = htmlspecialchars($user_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Convert newlines to <br> and escape
    $message_with_br = nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $esc_siteurl = htmlspecialchars($siteurl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $esc_sitename = htmlspecialchars($sitename, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>{$esc_subject}</title>
</head>
<body style="margin:0; padding:0; background:#f1f5f9; font-family: 'Segoe UI', Arial, sans-serif; color:#374151; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
  <div style="max-width:600px; margin:0.5rem auto; background:#ffffff; border-radius:10px; overflow:hidden;">
    <!-- Header -->
    <div style="background:#334155; padding:20px 10px; text-align:center; color:#ffffff;">
      <img src="{$esc_siteurl}/home/images/logo.png" alt="{$esc_sitename} Logo" width="80" style="height:auto; margin-bottom:8px; display:inline-block;" />
      <h1 style="font-size:24px; font-weight:600; margin:0; text-transform: capitalize; letter-spacing:0.5px;">{$esc_sitename}</h1>
    </div>
    <!-- Main Content -->
    <div style="padding:10px 8px; font-size:16px; line-height:1.4; color:#1f2937;">
      <p style="margin-bottom:20px; font-weight:500;">Hello {$esc_user_name},</p>

      <div style="background:#f9fafb; padding:20px; border-left:5px solid #2563eb; margin-bottom:20px; border-radius:8px; color:#374151;">
        <h2 style="font-size:20px; margin:0 0 15px 0; font-weight:400; color:#1e293b;">{$esc_subject}</h2>
        <div style="font-size:14px; line-height:1.5; color:#374151;">{$message_with_br}</div>
      </div>

      <div style="text-align:center; margin-bottom:15px;">
        <a href="{$esc_siteurl}/app/" style="display:inline-block; background:#1f2937; color:#f9fafb; padding:14px 32px; text-decoration:none; border-radius:8px; font-weight:600; font-size:16px; box-shadow:0 3px 8px rgba(31,41,55,0.3); transition: background-color 0.3s ease-in-out, color 0.3s ease-in-out;">
          Visit Our Platform
        </a>
      </div>

      <div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:20px; color:#6b7280; font-size:14px;">
        <p style="margin:0;">Best regards,<br />The {$esc_sitename} Team</p>
      </div>
    </div>
    <!-- Footer -->
    <div style="background:#1f2937; color:#9ca3af; padding:22px 15px; text-align:center; font-size:14px; user-select:none;">
      <p style="margin:5px 0;"><strong>{$esc_sitename}</strong></p>
      <p style="margin:5px 0;">Thank you for being a valued member of our community.</p>
      <div>
        <a href="{$esc_siteurl}/privacy.php" style="color:#60a5fa; text-decoration:none; margin:0 8px;">Privacy Policy</a> |
        <a href="{$esc_siteurl}/terms.php" style="color:#60a5fa; text-decoration:none; margin:0 8px;">Terms of Service</a> |
        <a href="{$esc_siteurl}/contact.php" style="color:#60a5fa; text-decoration:none; margin:0 8px;">Contact Support</a>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
}


/**
 * Send email to a single user
 * @param int $user_id User ID from database
 * @param string $subject Email subject
 * @param string $message Email message
 * @return array Result array with success status and message
 */
function sendSingleUserEmail($user_id, $subject, $message) {
    global $link;
    
    // Validate inputs
    if (empty($subject) || empty($message)) {
        return ['success' => false, 'message' => 'Subject and message are required'];
    }
    
    // Get user data
    $stmt = $link->prepare("SELECT id, first_name, last_name, email, STATUS FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    $user = $result->fetch_assoc();
    $user_name = trim($user['first_name'] . ' ' . $user['last_name']);
    $user_email = $user['email'];
    
    // Create email content
    $email_body = createUnifiedEmailTemplate($subject, $message, $user_name, 'single');
    
    // Send email
    if (sendMail($user_email, $subject, $email_body)) {
        return [
            'success' => true, 
            'message' => "Email sent successfully to {$user_name} ({$user_email})"
        ];
    } else {
        return [
            'success' => false, 
            'message' => 'Failed to send email. Please check your email configuration.'
        ];
    }
}

/**
 * Send bulk emails to users based on criteria
 * @param string $subject Email subject
 * @param string $message Email message
 * @param string $recipient_type Type of recipients (all|active|inactive)
 * @return array Result array with success status, sent count, and failed count
 */
function sendBulkEmail($subject, $message, $recipient_type = 'all') {
    global $link;
    
    // Validate inputs
    if (empty($subject) || empty($message)) {
        return [
            'success' => false, 
            'message' => 'Subject and message are required',
            'sent' => 0,
            'failed' => 0
        ];
    }
    
    // Build query based on recipient type
    $query = "SELECT email, first_name, last_name FROM users WHERE ";
    switch ($recipient_type) {
        case 'all':
            $query .= "email != ''";
            break;
        case 'active':
            $query .= "STATUS = 'active' AND email != ''";
            break;
        case 'inactive':
            $query .= "(STATUS = 'inactive' OR STATUS IS NULL OR STATUS = '') AND email != ''";
            break;
        default:
            return [
                'success' => false, 
                'message' => 'Invalid recipient type',
                'sent' => 0,
                'failed' => 0
            ];
    }
    
    $result = mysqli_query($link, $query);
    
    if (!$result || mysqli_num_rows($result) === 0) {
        return [
            'success' => false, 
            'message' => 'No users found for the selected criteria',
            'sent' => 0,
            'failed' => 0
        ];
    }
    
    $sent_count = 0;
    $failed_count = 0;
    
    // Send emails to each user
    while ($user = mysqli_fetch_assoc($result)) {
        $user_name = trim($user['first_name'] . ' ' . $user['last_name']);
        $user_email = $user['email'];
        
        // Skip if email is empty
        if (empty($user_email)) {
            $failed_count++;
            continue;
        }
        
        // Create personalized email content
        $email_body = createUnifiedEmailTemplate($subject, $message, $user_name, 'bulk');
        
        // Send email
        if (sendMail($user_email, $subject, $email_body)) {
            $sent_count++;
        } else {
            $failed_count++;
        }
        
        // Small delay to prevent overwhelming the server
        usleep(100000); // 0.1 second delay
    }
    
    // Prepare result message
    $total = $sent_count + $failed_count;
    if ($sent_count > 0 && $failed_count === 0) {
        $message = "All emails sent successfully! Sent to {$sent_count} users.";
        $success = true;
    } elseif ($sent_count > 0 && $failed_count > 0) {
        $message = "Emails sent to {$sent_count} users. {$failed_count} failed to send.";
        $success = true;
    } else {
        $message = "Failed to send any emails. Please check your email configuration.";
        $success = false;
    }
    
    return [
        'success' => $success,
        'message' => $message,
        'sent' => $sent_count,
        'failed' => $failed_count
    ];
}

/**
 * Get user counts for different categories
 * @return array Array with user counts
 */
function getUserCounts() {
    global $link;
    
    $counts = [];
    
    // Total users
    $result = mysqli_query($link, "SELECT COUNT(*) as count FROM users WHERE email != ''");
    $counts['total'] = mysqli_fetch_assoc($result)['count'] ?? 0;
    
    // Active users
    $result = mysqli_query($link, "SELECT COUNT(*) as count FROM users WHERE STATUS = 'active' AND email != ''");
    $counts['active'] = mysqli_fetch_assoc($result)['count'] ?? 0;
    
    // Inactive users
    $result = mysqli_query($link, "SELECT COUNT(*) as count FROM users WHERE (STATUS = 'inactive' OR STATUS IS NULL OR STATUS = '') AND email != ''");
    $counts['inactive'] = mysqli_fetch_assoc($result)['count'] ?? 0;
    
    return $counts;
}

/**
 * Display custom alert using SweetAlert
 * @param string $case Alert type (success|error)
 * @param string $content Alert message
 * @return string JavaScript alert code
 */
function customAlert($case, $content) {
    switch ($case) {
        case 'success':
            $mesg = '<script type="text/javascript">
                $(document).ready(function() {
                    swal({
                        title: "Success",
                        text: "' . htmlspecialchars($content) . '",
                        icon: "success",
                        button: "Ok",
                        timer: 5000
                    });    
                });
            </script>';
            break;
        case 'error':
            $mesg = '<script type="text/javascript">
                $(document).ready(function() {
                    swal({
                        title: "Error",
                        text: "' . htmlspecialchars($content) . '",
                        icon: "error",
                        button: "Ok",
                        timer: 5000
                    });    
                });
            </script>';
            break;
        default:
            $mesg = '';
            break;
    }
    return $mesg;
}

/**
 * Redirect to a page after specified seconds
 * @param int $sec Seconds to wait before redirect
 * @param string $route URL to redirect to
 * @return string Meta refresh tag
 */
function pageRedirect($sec, $route) {
    return "<meta http-equiv='refresh' content='$sec; url=$route' />";
}

/**
 * Generate random string of numbers
 * @return string Random number string
 */
function getRandomStrings() {
    $rnumbs = "1234567890123";
    $tnumbs = str_shuffle($rnumbs);
    return substr($tnumbs, 0, 30);
}

/**
 * Convert number to words
 * @param int $number Number to convert
 * @return string Number in words
 */
function numberToWords($number) {
    $words = [
        0 => 'zero', 1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four',
        5 => 'five', 6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine',
        10 => 'ten', 11 => 'eleven', 12 => 'twelve', 13 => 'thirteen',
        14 => 'fourteen', 15 => 'fifteen', 16 => 'sixteen', 17 => 'seventeen',
        18 => 'eighteen', 19 => 'nineteen', 20 => 'twenty', 30 => 'thirty',
        40 => 'forty', 50 => 'fifty', 60 => 'sixty', 70 => 'seventy',
        80 => 'eighty', 90 => 'ninety'
    ];

    if ($number == 0) {
        return 'zero';
    }

    $output = '';

    if ($number >= 1000) {
        $thousands = floor($number / 1000);
        $output .= $words[$thousands] . ' thousand ';
        $number %= 1000;
    }

    if ($number >= 100) {
        $hundreds = floor($number / 100);
        $output .= $words[$hundreds] . ' hundred ';
        $number %= 100;
    }

    if ($number > 0) {
        if ($number <= 20) {
            $output .= $words[$number];
        } else {
            $tens = floor($number / 10) * 10;
            $units = $number % 10;
            $output .= $words[$tens];
            if ($units) {
                $output .= '-' . $words[$units];
            }
        }
    }

    return trim($output);
}

/**
 * Sanitize text input
 * @param string $data Input data
 * @return string Sanitized data
 */
function text_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Currency Helper Functions

/**
 * Get all valid currencies
 * @return array
 */
function getValidCurrencies() {
    return ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];
}

/**
 * Get currency names mapping
 * @return array
 */
function getCurrencyNames() {
    return [
        'USD' => 'US Dollar',
        'EUR' => 'Euro',
        'GBP' => 'British Pound Sterling',
        'CAD' => 'Canadian Dollar',
        'AUD' => 'Australian Dollar'
    ];
}

/**
 * Get currency symbols mapping
 * @return array
 */
function getCurrencySymbols() {
    return [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'C$',
        'AUD' => 'A$'
    ];
}

/**
 * Get currency name by code
 * @param string $code
 * @return string
 */
function getCurrencyName($code) {
    $names = getCurrencyNames();
    return isset($names[$code]) ? $names[$code] : $code;
}

/**
 * Get currency symbol by code
 * @param string $code
 * @return string
 */
function getCurrencySymbol($code) {
    $symbols = getCurrencySymbols();
    return isset($symbols[$code]) ? $symbols[$code] : $code;
}

/**
 * Validate currency code
 * @param string $code
 * @return bool
 */
function isValidCurrency($code) {
    return in_array($code, getValidCurrencies());
}

/**
 * Format currency amount
 * @param float $amount
 * @param string $currency_code
 * @return string
 */
function formatCurrency($amount, $currency_code) {
    $symbol = getCurrencySymbol($currency_code);
    return $symbol . number_format($amount, 2);
}

/**
 * Get currency options for select dropdown
 * @return array
 */
function getCurrencyOptions() {
    $currencies = getValidCurrencies();
    $names = getCurrencyNames();
    $options = [];
    
    foreach ($currencies as $code) {
        $options[] = [
            'code' => $code,
            'name' => $names[$code],
            'display' => $code . ' - ' . $names[$code]
        ];
    }
    
    return $options;
}

/**
 * Generate currency select HTML options
 * @param string $selected_currency
 * @return string
 */
function generateCurrencyOptions($selected_currency = '') {
    $options = getCurrencyOptions();
    $html = '<option value="">Select Currency</option>';
    
    foreach ($options as $option) {
        $selected = ($selected_currency === $option['code']) ? ' selected' : '';
        $html .= sprintf(
            '<option value="%s"%s>%s</option>',
            htmlspecialchars($option['code']),
            $selected,
            htmlspecialchars($option['display'])
        );
    }
    
    return $html;
}

/**
 * Convert currency rates (placeholder - integrate with real API)
 * @param float $amount
 * @param string $from_currency
 * @param string $to_currency
 * @return float
 */
function convertCurrency($amount, $from_currency, $to_currency) {
    // Placeholder exchange rates - integrate with real API like exchangerate-api.com
    $rates = [
        'USD' => 1.0,
        'EUR' => 0.85,
        'GBP' => 0.73,
        'CAD' => 1.25,
        'AUD' => 1.35
    ];
    
    if (!isset($rates[$from_currency]) || !isset($rates[$to_currency])) {
        return $amount;
    }
    
    $usd_amount = $amount / $rates[$from_currency];
    return $usd_amount * $rates[$to_currency];
}
?>