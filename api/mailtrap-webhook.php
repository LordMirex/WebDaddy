<?php
/**
 * Mailtrap Webhook Handler
 * 
 * Receives webhook events from Mailtrap for email delivery tracking
 * Events: sent, delivered, bounced, complained, opened, clicked
 * 
 * Webhook URL: https://webdaddy.online/api/mailtrap-webhook.php
 * Documentation: https://mailtrap.io/webhooks
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailtrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$payload = file_get_contents('php://input');
error_log("Mailtrap Webhook received");

$event = json_decode($payload, true);

if (!$event || !isset($event['type'])) {
    http_response_code(400);
    error_log("Mailtrap Webhook: Invalid payload");
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

$eventType = $event['type'];
$eventData = $event['data'] ?? [];
$messageId = $event['message_id'] ?? ($eventData['message_id'] ?? null);
$recipient = $event['to'] ?? ($eventData['to'] ?? null);

error_log("Mailtrap Webhook: Received $eventType for message $messageId");

try {
    switch ($eventType) {
        case 'sent':
            updateMailtrapEmailStatus($messageId, 'sent', $eventData);
            logWebhookEvent('sent', $messageId, $recipient, $eventData);
            break;
            
        case 'delivered':
            updateMailtrapEmailStatus($messageId, 'delivered', $eventData);
            logWebhookEvent('delivered', $messageId, $recipient, $eventData);
            error_log("Mailtrap: Email $messageId delivered to $recipient");
            break;
            
        case 'bounced':
            $bounceType = $eventData['bounce_type'] ?? 'unknown';
            $bounceMessage = $eventData['bounce_message'] ?? "Bounce type: $bounceType";
            updateMailtrapEmailStatus($messageId, 'bounced', $eventData, $bounceMessage);
            logWebhookEvent('bounced', $messageId, $recipient, $eventData);
            error_log("MAILTRAP BOUNCE: MessageID={$messageId}, To={$recipient} ($bounceType)");
            break;
            
        case 'complained':
            updateMailtrapEmailStatus($messageId, 'complained', $eventData, 'Recipient marked as spam');
            logWebhookEvent('complained', $messageId, $recipient, $eventData);
            error_log("MAILTRAP COMPLAINT: MessageID={$messageId}, To={$recipient}");
            break;
            
        case 'opened':
            updateMailtrapEmailStatus($messageId, 'opened', $eventData);
            logWebhookEvent('opened', $messageId, $recipient, $eventData);
            break;
            
        case 'clicked':
            logWebhookEvent('clicked', $messageId, $recipient, $eventData);
            break;
            
        default:
            logWebhookEvent($eventType, $messageId, $recipient, $eventData);
            error_log("Mailtrap Webhook: Unhandled event type: $eventType");
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'status' => 'received', 'event' => $eventType]);
    
} catch (Exception $e) {
    error_log("Mailtrap Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Log webhook event to database
 */
function logWebhookEvent($eventType, $messageId, $recipient, $data) {
    try {
        $db = getDb();
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS mailtrap_webhook_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                event_type TEXT NOT NULL,
                mailtrap_message_id TEXT,
                recipient_email TEXT,
                event_data TEXT,
                ip_address TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $stmt = $db->prepare("
            INSERT INTO mailtrap_webhook_logs 
            (event_type, mailtrap_message_id, recipient_email, event_data, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now', '+1 hour'))
        ");
        $stmt->execute([
            $eventType,
            $messageId,
            $recipient,
            json_encode($data),
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
    } catch (Exception $e) {
        error_log("Failed to log Mailtrap webhook event: " . $e->getMessage());
    }
}
