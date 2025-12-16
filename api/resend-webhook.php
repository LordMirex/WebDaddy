<?php
/**
 * Resend Webhook Handler
 * 
 * Receives webhook events from Resend for email delivery tracking
 * Events: email.sent, email.delivered, email.bounced, email.complained, email.opened, email.clicked
 * 
 * Webhook URL: https://webdaddy.online/api/resend-webhook.php
 * Documentation: https://resend.com/docs/webhooks
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/resend.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '';
$svixId = $_SERVER['HTTP_SVIX_ID'] ?? '';
$svixTimestamp = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '';

error_log("Resend Webhook received: ID={$svixId}");

$signingSecret = defined('RESEND_WEBHOOK_SECRET') ? RESEND_WEBHOOK_SECRET : '';

if (!empty($signingSecret)) {
    $signedContent = "$svixId.$svixTimestamp.$payload";
    
    $secretPart = $signingSecret;
    if (strpos($signingSecret, 'whsec_') === 0) {
        $secretPart = substr($signingSecret, 6);
    }
    $secretBytes = base64_decode($secretPart);
    
    if ($secretBytes === false) {
        error_log("Resend Webhook: Failed to decode signing secret");
        http_response_code(500);
        echo json_encode(['error' => 'Server configuration error']);
        exit;
    }
    
    $computedSignature = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));
    
    $passedSignatures = explode(' ', $signature);
    $signatureValid = false;
    
    foreach ($passedSignatures as $sig) {
        $parts = explode(',', $sig);
        if (count($parts) === 2 && $parts[0] === 'v1') {
            if (hash_equals($computedSignature, $parts[1])) {
                $signatureValid = true;
                break;
            }
        }
    }
    
    if (!$signatureValid) {
        http_response_code(400);
        error_log("Resend Webhook: Invalid signature - computed: $computedSignature");
        logWebhookEvent('signature_invalid', null, null, ['signature' => $signature, 'svix_id' => $svixId]);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
} else {
    error_log("Resend Webhook: No signing secret configured - accepting without verification (dev mode)");
}

$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    error_log("Resend Webhook: Invalid payload");
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventType = $event['type'];
$eventData = $event['data'] ?? [];
$emailId = $eventData['email_id'] ?? null;
$recipient = $eventData['to'][0] ?? ($eventData['email'] ?? null);

error_log("Resend Webhook: Received $eventType for email $emailId");

try {
    switch ($eventType) {
        case 'email.sent':
            updateResendEmailStatus($emailId, 'sent', $eventData);
            logWebhookEvent('email.sent', $emailId, $recipient, $eventData);
            break;
            
        case 'email.delivered':
            updateResendEmailStatus($emailId, 'delivered', $eventData);
            logWebhookEvent('email.delivered', $emailId, $recipient, $eventData);
            error_log("Resend: Email $emailId delivered to $recipient");
            break;
            
        case 'email.delivery_delayed':
            updateResendEmailStatus($emailId, 'delayed', $eventData);
            logWebhookEvent('email.delivery_delayed', $emailId, $recipient, $eventData);
            error_log("Resend: Email $emailId delayed for $recipient");
            break;
            
        case 'email.complained':
            updateResendEmailStatus($emailId, 'complained', $eventData, 'Recipient marked as spam');
            logWebhookEvent('email.complained', $emailId, $recipient, $eventData);
            error_log("RESEND COMPLAINT: EmailID={$emailId}, To={$recipient}");
            break;
            
        case 'email.bounced':
            $bounceType = $eventData['bounce']['type'] ?? 'unknown';
            $bounceMessage = $eventData['bounce']['message'] ?? "Bounce type: $bounceType";
            updateResendEmailStatus($emailId, 'bounced', $eventData, $bounceMessage);
            logWebhookEvent('email.bounced', $emailId, $recipient, $eventData);
            error_log("RESEND BOUNCE: EmailID={$emailId}, To={$recipient} ($bounceType)");
            break;
            
        case 'email.opened':
            updateResendEmailStatus($emailId, 'opened', $eventData);
            logWebhookEvent('email.opened', $emailId, $recipient, $eventData);
            break;
            
        case 'email.clicked':
            logWebhookEvent('email.clicked', $emailId, $recipient, $eventData);
            break;
            
        default:
            logWebhookEvent($eventType, $emailId, $recipient, $eventData);
            error_log("Resend Webhook: Unhandled event type: $eventType");
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'status' => 'received', 'event' => $eventType]);
    
} catch (Exception $e) {
    error_log("Resend Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Log webhook event to database
 */
function logWebhookEvent($eventType, $emailId, $recipient, $data) {
    try {
        $db = getDb();
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS resend_webhook_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                resend_email_id TEXT,
                recipient_email TEXT,
                event_data TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $db->prepare("
            INSERT INTO resend_webhook_logs 
            (event_type, resend_email_id, recipient_email, event_data, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now', '+1 hour'))
        ");
        $stmt->execute([
            $eventType,
            $emailId,
            $recipient,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log Resend webhook event: " . $e->getMessage());
    }
}
