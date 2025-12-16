<?php
/**
 * Resend Webhook Handler
 * 
 * Receives webhook events from Resend for email delivery tracking
 * Events: email.sent, email.delivered, email.bounced, email.complained, email.opened, email.clicked
 * 
 * Documentation: https://resend.com/docs/webhooks
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/resend.php';

// Set JSON header
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get raw POST data
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_SVIX_SIGNATURE'] ?? '';
$webhookId = $_SERVER['HTTP_SVIX_ID'] ?? '';
$timestamp = $_SERVER['HTTP_SVIX_TIMESTAMP'] ?? '';

// Log incoming webhook
error_log("Resend Webhook received: ID={$webhookId}");

// Verify webhook signature (if secret is configured)
$webhookSecret = defined('RESEND_WEBHOOK_SECRET') ? RESEND_WEBHOOK_SECRET : '';

if (!empty($webhookSecret) && !empty($signature)) {
    // Verify the signature using the webhook secret
    // Resend uses Svix for webhooks
    $signedContent = "{$webhookId}.{$timestamp}.{$payload}";
    
    // Parse the signature header - format: v1,signature
    $signatures = explode(' ', $signature);
    $verified = false;
    
    foreach ($signatures as $sig) {
        $parts = explode(',', $sig);
        if (count($parts) === 2) {
            $version = $parts[0];
            $sigValue = $parts[1];
            
            if ($version === 'v1') {
                // Extract the secret key (remove 'whsec_' prefix)
                $secretKey = $webhookSecret;
                if (strpos($secretKey, 'whsec_') === 0) {
                    $secretKey = substr($secretKey, 6);
                }
                
                // Decode base64 secret
                $secretBytes = base64_decode($secretKey);
                
                // Calculate expected signature
                $expectedSig = base64_encode(hash_hmac('sha256', $signedContent, $secretBytes, true));
                
                if (hash_equals($expectedSig, $sigValue)) {
                    $verified = true;
                    break;
                }
            }
        }
    }
    
    if (!$verified) {
        error_log("Resend Webhook: Invalid signature");
        http_response_code(401);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

// Parse the payload
$data = json_decode($payload, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("Resend Webhook: Invalid JSON payload");
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

// Extract event type and data
$eventType = $data['type'] ?? '';
$eventData = $data['data'] ?? [];
$emailId = $eventData['email_id'] ?? null;

if (empty($eventType)) {
    error_log("Resend Webhook: Missing event type");
    http_response_code(400);
    echo json_encode(['error' => 'Missing event type']);
    exit;
}

// Log the event
error_log("Resend Webhook: Event={$eventType}, EmailID={$emailId}");

try {
    // Map Resend events to our status
    $statusMap = [
        'email.sent' => 'sent',
        'email.delivered' => 'delivered',
        'email.bounced' => 'bounced',
        'email.complained' => 'complained',
        'email.opened' => 'opened',
        'email.clicked' => 'clicked',
        'email.delivery_delayed' => 'delayed'
    ];
    
    $status = $statusMap[$eventType] ?? 'unknown';
    
    // Get error message for bounced/complained emails
    $errorMessage = null;
    if ($eventType === 'email.bounced') {
        $errorMessage = $eventData['bounce']['message'] ?? 'Email bounced';
    } elseif ($eventType === 'email.complained') {
        $errorMessage = 'Recipient marked as spam';
    }
    
    // Update the email status in our database
    if ($emailId) {
        $updated = updateResendEmailStatus($emailId, $status, $eventData, $errorMessage);
        
        if ($updated) {
            error_log("Resend Webhook: Updated status for EmailID={$emailId} to {$status}");
        }
    }
    
    // Handle specific event types
    switch ($eventType) {
        case 'email.bounced':
            // Log bounced emails for admin review
            error_log("RESEND BOUNCE: EmailID={$emailId}, To=" . ($eventData['to'][0] ?? 'unknown'));
            break;
            
        case 'email.complained':
            // User marked as spam - important for sender reputation
            error_log("RESEND COMPLAINT: EmailID={$emailId}, To=" . ($eventData['to'][0] ?? 'unknown'));
            break;
            
        case 'email.delivered':
            // Success - email was delivered
            break;
    }
    
    // Return success
    http_response_code(200);
    echo json_encode(['success' => true, 'status' => $status]);
    
} catch (Exception $e) {
    error_log("Resend Webhook Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
