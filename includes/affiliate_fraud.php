<?php
/**
 * Affiliate Fraud Detection System
 * Detects and flags suspicious affiliate activity including click fraud,
 * self-referrals, and commission manipulation
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

class AffiliateFraudDetector {
    private $db;
    private $rules = [
        'click_velocity' => ['threshold' => 100, 'window' => 3600], // 100 clicks/hour
        'same_ip_orders' => ['threshold' => 3, 'window' => 86400], // 3 orders from same IP/day
        'self_referral' => true,
        'bot_detection' => true,
        'suspicious_patterns' => true
    ];
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Analyze click for fraud
     */
    public function analyzeClick($affiliateId, $ipAddress, $userAgent) {
        $flags = [];
        
        // Check click velocity
        if ($this->checkClickVelocity($affiliateId, $ipAddress)) {
            $flags[] = ['type' => 'high_click_velocity', 'severity' => 'medium'];
        }
        
        // Check for bot signatures
        if ($this->isBot($userAgent)) {
            $flags[] = ['type' => 'bot_detected', 'severity' => 'high'];
        }
        
        // Check for VPN/Proxy
        if ($this->isVpnOrProxy($ipAddress)) {
            $flags[] = ['type' => 'vpn_proxy_detected', 'severity' => 'low'];
        }
        
        // Log flags
        if (!empty($flags)) {
            $this->logFraudFlags($affiliateId, 'click', $flags, $ipAddress);
        }
        
        return $flags;
    }
    
    /**
     * Analyze order for fraud
     */
    public function analyzeOrder($orderId, $affiliateId) {
        $flags = [];
        $db = $this->db;
        
        try {
            $stmt = $db->prepare("
                SELECT po.*, a.email as affiliate_email
                FROM pending_orders po
                LEFT JOIN affiliates af ON po.affiliate_code = af.code
                LEFT JOIN users a ON af.user_id = a.id
                WHERE po.id = ? AND af.id = ?
            ");
            $stmt->execute([$orderId, $affiliateId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) return $flags;
            
            // Check for self-referral
            if ($this->isSelfReferral($order)) {
                $flags[] = ['type' => 'self_referral', 'severity' => 'high'];
            }
            
            // Check for multiple orders from same IP
            if ($this->checkSameIpOrders($affiliateId, $order['ip_address'] ?? '')) {
                $flags[] = ['type' => 'same_ip_multiple_orders', 'severity' => 'medium'];
            }
            
            // Check for email pattern abuse
            if ($this->checkEmailPatternAbuse($affiliateId, $order['customer_email'])) {
                $flags[] = ['type' => 'email_pattern_abuse', 'severity' => 'medium'];
            }
            
            // Check conversion timing
            if ($this->checkSuspiciousTiming($affiliateId, $orderId)) {
                $flags[] = ['type' => 'suspicious_timing', 'severity' => 'low'];
            }
            
            // Log and handle flags
            if (!empty($flags)) {
                $this->logFraudFlags($affiliateId, 'order', $flags, null, $orderId);
                
                // Auto-action based on severity
                $highSeverity = array_filter($flags, fn($f) => $f['severity'] === 'high');
                if (count($highSeverity) > 0) {
                    $this->holdCommission($orderId);
                }
            }
        } catch (PDOException $e) {
            error_log('Error analyzing order for fraud: ' . $e->getMessage());
        }
        
        return $flags;
    }
    
    /**
     * Check for high click velocity
     */
    private function checkClickVelocity($affiliateId, $ipAddress) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM affiliate_clicks 
                WHERE affiliate_id = ? 
                AND ip_address = ?
                AND created_at > datetime('now', '-1 hour')
            ");
            $stmt->execute([$affiliateId, $ipAddress]);
            return $stmt->fetchColumn() > $this->rules['click_velocity']['threshold'];
        } catch (PDOException $e) {
            error_log('Error checking click velocity: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Detect bot user agents
     */
    private function isBot($userAgent) {
        if (empty($userAgent)) {
            return true; // Empty user agent is suspicious
        }
        
        $botPatterns = [
            '/bot/i', '/crawler/i', '/spider/i', '/curl/i', 
            '/wget/i', '/python/i', '/scrapy/i', '/headless/i',
            '/phantomjs/i', '/selenium/i', '/puppeteer/i'
        ];
        
        foreach ($botPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is from VPN or Proxy (basic check)
     */
    private function isVpnOrProxy($ipAddress) {
        if (empty($ipAddress)) {
            return false;
        }
        
        // Check for known datacenter IP ranges (simplified)
        $datacenterPrefixes = [
            '104.16.', '104.17.', '104.18.', '104.19.', '104.20.', // Cloudflare
            '172.64.', '172.65.', '172.66.', '172.67.', // Cloudflare
            '198.41.', // Cloudflare
        ];
        
        foreach ($datacenterPrefixes as $prefix) {
            if (strpos($ipAddress, $prefix) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check for self-referral
     */
    private function isSelfReferral($order) {
        if (empty($order['customer_email']) || empty($order['affiliate_email'])) {
            return false;
        }
        
        // Check if order email matches affiliate email
        if (strtolower($order['customer_email']) === strtolower($order['affiliate_email'])) {
            return true;
        }
        
        // Check for similar email patterns
        $orderDomain = substr($order['customer_email'], strpos($order['customer_email'], '@'));
        $affiliateDomain = substr($order['affiliate_email'], strpos($order['affiliate_email'], '@'));
        
        // If same custom domain (not common providers)
        $commonDomains = ['@gmail.com', '@yahoo.com', '@hotmail.com', '@outlook.com', '@live.com', '@icloud.com'];
        if (!in_array(strtolower($orderDomain), $commonDomains) && strtolower($orderDomain) === strtolower($affiliateDomain)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check for multiple orders from same IP
     */
    private function checkSameIpOrders($affiliateId, $ipAddress) {
        if (empty($ipAddress)) {
            return false;
        }
        
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM pending_orders po
                JOIN affiliates a ON po.affiliate_code = a.code
                WHERE a.id = ?
                AND po.ip_address = ?
                AND po.status = 'paid'
                AND date(po.paid_at) = date('now')
            ");
            $stmt->execute([$affiliateId, $ipAddress]);
            return $stmt->fetchColumn() >= $this->rules['same_ip_orders']['threshold'];
        } catch (PDOException $e) {
            error_log('Error checking same IP orders: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for email pattern abuse (multiple orders with similar emails)
     */
    private function checkEmailPatternAbuse($affiliateId, $customerEmail) {
        if (empty($customerEmail)) {
            return false;
        }
        
        try {
            // Get base email (before any + alias)
            $emailParts = explode('@', $customerEmail);
            $localPart = $emailParts[0];
            $domain = $emailParts[1] ?? '';
            
            // Remove any + alias
            $localPart = explode('+', $localPart)[0];
            $basePattern = $localPart . '%@' . $domain;
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM pending_orders po
                JOIN affiliates a ON po.affiliate_code = a.code
                WHERE a.id = ?
                AND po.customer_email LIKE ?
                AND po.status = 'paid'
                AND po.paid_at >= datetime('now', '-7 days')
            ");
            $stmt->execute([$affiliateId, $basePattern]);
            
            // More than 5 orders with similar email pattern in a week is suspicious
            return $stmt->fetchColumn() > 5;
        } catch (PDOException $e) {
            error_log('Error checking email pattern abuse: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check for suspicious timing between click and order
     */
    private function checkSuspiciousTiming($affiliateId, $orderId) {
        try {
            // Get order details
            $stmt = $this->db->prepare("
                SELECT po.created_at as order_time, po.ip_address
                FROM pending_orders po
                JOIN affiliates a ON po.affiliate_code = a.code
                WHERE po.id = ? AND a.id = ?
            ");
            $stmt->execute([$orderId, $affiliateId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order || empty($order['ip_address'])) {
                return false;
            }
            
            // Find the last click from this IP before order
            $stmt = $this->db->prepare("
                SELECT created_at FROM affiliate_clicks
                WHERE affiliate_id = ?
                AND ip_address = ?
                AND created_at <= ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$affiliateId, $order['ip_address'], $order['order_time']]);
            $click = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$click) {
                // Order without corresponding click
                return true;
            }
            
            // Check if conversion happened too quickly (less than 30 seconds)
            $clickTime = strtotime($click['created_at']);
            $orderTime = strtotime($order['order_time']);
            $timeDiff = $orderTime - $clickTime;
            
            // Very fast conversions (under 30 sec) are suspicious
            return $timeDiff < 30;
        } catch (PDOException $e) {
            error_log('Error checking suspicious timing: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log fraud flags to database
     */
    public function logFraudFlags($affiliateId, $context, $flags, $ipAddress = null, $orderId = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO affiliate_fraud_logs 
                (affiliate_id, context, flags, ip_address, order_id, created_at)
                VALUES (?, ?, ?, ?, ?, datetime('now'))
            ");
            $stmt->execute([
                $affiliateId, 
                $context, 
                json_encode($flags), 
                $ipAddress, 
                $orderId
            ]);
            
            // Notify admin for high severity
            $highSeverity = array_filter($flags, fn($f) => $f['severity'] === 'high');
            if (count($highSeverity) > 0) {
                $this->notifyAdminFraudAlert($affiliateId, $context, $flags);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log('Error logging fraud flags: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hold commission pending review
     */
    public function holdCommission($orderId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE pending_orders 
                SET affiliate_commission_held = 1,
                    affiliate_hold_reason = 'fraud_review'
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            return true;
        } catch (PDOException $e) {
            error_log('Error holding commission: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Release held commission after review
     */
    public function releaseCommission($orderId, $reviewedBy) {
        try {
            $stmt = $this->db->prepare("
                UPDATE pending_orders 
                SET affiliate_commission_held = 0,
                    affiliate_hold_reason = NULL
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Update the fraud log
            $stmt = $this->db->prepare("
                UPDATE affiliate_fraud_logs 
                SET is_reviewed = 1,
                    reviewed_by = ?,
                    reviewed_at = datetime('now'),
                    action_taken = 'released'
                WHERE order_id = ?
            ");
            $stmt->execute([$reviewedBy, $orderId]);
            
            return true;
        } catch (PDOException $e) {
            error_log('Error releasing commission: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Reject commission and flag affiliate
     */
    public function rejectCommission($orderId, $reviewedBy) {
        try {
            $stmt = $this->db->prepare("
                UPDATE pending_orders 
                SET affiliate_commission = 0,
                    affiliate_commission_held = 0,
                    affiliate_hold_reason = 'rejected_fraud'
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
            
            // Update the fraud log
            $stmt = $this->db->prepare("
                UPDATE affiliate_fraud_logs 
                SET is_reviewed = 1,
                    reviewed_by = ?,
                    reviewed_at = datetime('now'),
                    action_taken = 'rejected'
                WHERE order_id = ?
            ");
            $stmt->execute([$reviewedBy, $orderId]);
            
            return true;
        } catch (PDOException $e) {
            error_log('Error rejecting commission: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get pending fraud reviews for admin
     */
    public function getPendingReviews($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    fl.*,
                    a.code as affiliate_code,
                    u.name as affiliate_name,
                    u.email as affiliate_email,
                    po.final_amount,
                    po.affiliate_commission,
                    po.customer_email
                FROM affiliate_fraud_logs fl
                JOIN affiliates a ON fl.affiliate_id = a.id
                JOIN users u ON a.user_id = u.id
                LEFT JOIN pending_orders po ON fl.order_id = po.id
                WHERE fl.is_reviewed = 0
                ORDER BY fl.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error getting pending reviews: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get fraud statistics for admin dashboard
     */
    public function getFraudStats() {
        try {
            $stats = [];
            
            // Pending reviews
            $stmt = $this->db->query("SELECT COUNT(*) FROM affiliate_fraud_logs WHERE is_reviewed = 0");
            $stats['pending_reviews'] = $stmt->fetchColumn();
            
            // Held commissions total
            $stmt = $this->db->query("
                SELECT COALESCE(SUM(affiliate_commission), 0) 
                FROM pending_orders 
                WHERE affiliate_commission_held = 1
            ");
            $stats['held_commission_total'] = (float)$stmt->fetchColumn();
            
            // Confirmed fraud (30 days)
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM affiliate_fraud_logs 
                WHERE action_taken = 'rejected'
                AND reviewed_at >= datetime('now', '-30 days')
            ");
            $stats['confirmed_fraud_30d'] = $stmt->fetchColumn();
            
            // False positives (30 days)
            $stmt = $this->db->query("
                SELECT COUNT(*) FROM affiliate_fraud_logs 
                WHERE action_taken = 'released'
                AND reviewed_at >= datetime('now', '-30 days')
            ");
            $stats['false_positives_30d'] = $stmt->fetchColumn();
            
            return $stats;
        } catch (PDOException $e) {
            error_log('Error getting fraud stats: ' . $e->getMessage());
            return [
                'pending_reviews' => 0,
                'held_commission_total' => 0,
                'confirmed_fraud_30d' => 0,
                'false_positives_30d' => 0
            ];
        }
    }
    
    /**
     * Notify admin of fraud alert
     */
    private function notifyAdminFraudAlert($affiliateId, $context, $flags) {
        // Log the alert - in production, this could send an email or notification
        error_log("FRAUD ALERT: Affiliate ID $affiliateId, Context: $context, Flags: " . json_encode($flags));
        
        // Try to use sendAdminNotification if available
        if (function_exists('sendAdminNotification')) {
            sendAdminNotification("Affiliate fraud alert: High severity flags detected", [
                'affiliate_id' => $affiliateId,
                'context' => $context,
                'flags' => $flags
            ]);
        }
    }
}

/**
 * Helper function to get fraud detector instance
 */
function getAffiliateFraudDetector() {
    return new AffiliateFraudDetector();
}
