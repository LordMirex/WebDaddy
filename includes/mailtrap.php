<?php
/**
 * Mailtrap Email Integration
 * Fast, reliable email delivery via Mailtrap REST API
 * Used specifically for OTP/verification emails for better deliverability
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Send email via Mailtrap REST API
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $htmlContent HTML email body
 * @param string $fromName Sender name (default: WebDaddy Empire)
 * @param string $emailType Type of email for logging (otp, recovery_otp, etc.)
 * @return array ['success' => bool, 'email_id' => string|null, 'error' => string|null]
 */
function sendMailtrapEmail($to, $subject, $htmlContent, $fromName = null, $emailType = 'otp') {
    $apiKey = defined('MAILTRAP_API_KEY') ? MAILTRAP_API_KEY : '';
    $fromEmail = defined('MAILTRAP_FROM_EMAIL') ? MAILTRAP_FROM_EMAIL : 'hello@webdaddy.online';
    $defaultFromName = defined('MAILTRAP_FROM_NAME') ? MAILTRAP_FROM_NAME : 'WebDaddy Empire';
    $apiHost = defined('MAILTRAP_API_HOST') ? MAILTRAP_API_HOST : 'send.api.mailtrap.io';
    $fromName = $fromName ?: $defaultFromName;
    
    if (empty($apiKey)) {
        error_log("Mailtrap: API key not configured");
        return ['success' => false, 'error' => 'Mailtrap API key not configured'];
    }
    
    // Build request data for Mailtrap API
    $data = [
        'from' => [
            'email' => $fromEmail,
            'name' => $fromName
        ],
        'to' => [
            [
                'email' => $to
            ]
        ],
        'subject' => $subject,
        'html' => $htmlContent,
        // Headers for inbox delivery (prevent spam filtering)
        'headers' => [
            'X-Priority' => '3',
            'Importance' => 'normal',
            'X-Mailer' => 'WebDaddy-Mailtrap/1.0',
            'Precedence' => 'bulk',
            'List-Unsubscribe' => '<mailto:' . $fromEmail . '>'
        ]
    ];
    
    $ch = curl_init('https://' . $apiHost . '/api/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        error_log("Mailtrap cURL error: $curlError");
        logMailtrapEmail(null, $to, $emailType, $subject, 'failed', null, $curlError);
        return ['success' => false, 'error' => 'Connection error: ' . $curlError];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode === 200 && isset($result['success']) && $result['success']) {
        $emailId = $result['message_id'] ?? $result['id'] ?? null;
        error_log("✅ Mailtrap: Email sent successfully to $to (ID: $emailId)");
        logMailtrapEmail($emailId, $to, $emailType, $subject, 'sent', null, null);
        return ['success' => true, 'email_id' => $emailId];
    }
    
    $errorMsg = $result['message'] ?? $result['error'] ?? $response;
    error_log("❌ Mailtrap: Failed to send email to $to - HTTP $httpCode - $errorMsg");
    logMailtrapEmail(null, $to, $emailType, $subject, 'failed', null, $errorMsg);
    return ['success' => false, 'error' => $errorMsg];
}

/**
 * Log Mailtrap email event to database
 */
function logMailtrapEmail($emailId, $recipient, $emailType, $subject, $status, $webhookData = null, $errorMessage = null) {
    try {
        $db = getDb();
        
        ensureMailtrapLogsTable($db);
        
        if ($emailId) {
            $existingStmt = $db->prepare("SELECT id FROM mailtrap_email_logs WHERE mailtrap_email_id = ?");
            $existingStmt->execute([$emailId]);
            $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                $stmt = $db->prepare("
                    UPDATE mailtrap_email_logs 
                    SET status = ?, 
                        webhook_data = COALESCE(?, webhook_data),
                        error_message = COALESCE(?, error_message),
                        updated_at = datetime('now', '+1 hour')
                    WHERE mailtrap_email_id = ?
                ");
                $stmt->execute([$status, $webhookData ? json_encode($webhookData) : null, $errorMessage, $emailId]);
                return;
            }
        }
        
        $stmt = $db->prepare("
            INSERT INTO mailtrap_email_logs 
            (mailtrap_email_id, recipient_email, email_type, subject, status, error_message, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, datetime('now', '+1 hour'), datetime('now', '+1 hour'))
        ");
        $stmt->execute([$emailId, $recipient, $emailType, $subject, $status, $errorMessage]);
        
    } catch (Exception $e) {
        error_log("Failed to log Mailtrap email: " . $e->getMessage());
    }
}

/**
 * Update Mailtrap email status from webhook
 */
function updateMailtrapEmailStatus($emailId, $status, $webhookData = null, $errorMessage = null) {
    if (empty($emailId)) {
        error_log("updateMailtrapEmailStatus: No email ID provided, skipping update");
        return false;
    }
    
    try {
        $db = getDb();
        ensureMailtrapLogsTable($db);
        
        $checkStmt = $db->prepare("SELECT id FROM mailtrap_email_logs WHERE mailtrap_email_id = ?");
        $checkStmt->execute([$emailId]);
        
        if (!$checkStmt->fetch()) {
            $recipient = null;
            if ($webhookData && isset($webhookData['to'])) {
                $recipient = is_array($webhookData['to']) ? $webhookData['to'][0] : $webhookData['to'];
            } elseif ($webhookData && isset($webhookData['email'])) {
                $recipient = $webhookData['email'];
            }
            
            $stmt = $db->prepare("
                INSERT INTO mailtrap_email_logs 
                (mailtrap_email_id, recipient_email, email_type, status, webhook_data, error_message, created_at, updated_at)
                VALUES (?, ?, 'unknown', ?, ?, ?, datetime('now', '+1 hour'), datetime('now', '+1 hour'))
            ");
            $stmt->execute([$emailId, $recipient, $status, $webhookData ? json_encode($webhookData) : null, $errorMessage]);
            return true;
        }
        
        $stmt = $db->prepare("
            UPDATE mailtrap_email_logs 
            SET status = ?, 
                webhook_data = ?,
                error_message = COALESCE(?, error_message),
                updated_at = datetime('now', '+1 hour')
            WHERE mailtrap_email_id = ?
        ");
        $stmt->execute([$status, $webhookData ? json_encode($webhookData) : null, $errorMessage, $emailId]);
        
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("Failed to update Mailtrap email status: " . $e->getMessage());
        return false;
    }
}

/**
 * Ensure mailtrap_email_logs table exists
 */
function ensureMailtrapLogsTable($db = null) {
    static $checked = false;
    if ($checked) return;
    
    if (!$db) $db = getDb();
    
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS mailtrap_email_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                mailtrap_email_id TEXT,
                recipient_email TEXT NOT NULL,
                email_type TEXT DEFAULT 'otp',
                subject TEXT,
                status TEXT DEFAULT 'pending',
                error_message TEXT,
                webhook_data TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mailtrap_email_id ON mailtrap_email_logs(mailtrap_email_id)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mailtrap_status ON mailtrap_email_logs(status)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mailtrap_recipient ON mailtrap_email_logs(recipient_email)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_mailtrap_created ON mailtrap_email_logs(created_at)");
        
        $checked = true;
    } catch (Exception $e) {
        error_log("Failed to create mailtrap_email_logs table: " . $e->getMessage());
    }
}

/**
 * Get Mailtrap email logs with pagination
 */
function getMailtrapEmailLogs($limit = 100, $offset = 0, $status = null, $emailType = null) {
    $db = getDb();
    ensureMailtrapLogsTable($db);
    
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = "status = ?";
        $params[] = $status;
    }
    
    if ($emailType) {
        $where[] = "email_type = ?";
        $params[] = $emailType;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare("
        SELECT * FROM mailtrap_email_logs 
        $whereClause
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get Mailtrap email statistics
 */
function getMailtrapEmailStats() {
    $db = getDb();
    ensureMailtrapLogsTable($db);
    
    $stats = [
        'total' => 0,
        'sent' => 0,
        'delivered' => 0,
        'opened' => 0,
        'failed' => 0,
        'bounced' => 0,
        'today' => 0
    ];
    
    try {
        $result = $db->query("SELECT COUNT(*) FROM mailtrap_email_logs")->fetchColumn();
        $stats['total'] = (int)$result;
        
        $statusCounts = $db->query("
            SELECT status, COUNT(*) as count 
            FROM mailtrap_email_logs 
            GROUP BY status
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($statusCounts as $row) {
            $status = strtolower($row['status']);
            if (isset($stats[$status])) {
                $stats[$status] = (int)$row['count'];
            }
        }
        
        $today = $db->query("
            SELECT COUNT(*) FROM mailtrap_email_logs 
            WHERE date(created_at) = date('now', '+1 hour')
        ")->fetchColumn();
        $stats['today'] = (int)$today;
        
    } catch (Exception $e) {
        error_log("Failed to get Mailtrap email stats: " . $e->getMessage());
    }
    
    return $stats;
}
