# Security Hardening

## Overview

This document outlines security improvements to protect the platform, customer data, and admin access. Focus areas include session security, encryption, and monitoring.

**Note:** MFA for Admin/Affiliate is NOT included in this update per business requirements.

---

## 1. Session Monitoring

### Suspicious Activity Detection

```php
/**
 * Session security monitoring
 */
class SessionMonitor {
    private $db;
    private $suspiciousThresholds = [
        'failed_logins_per_hour' => 5,
        'location_changes_per_day' => 3,
        'concurrent_sessions_max' => 5,
        'rapid_requests_per_minute' => 100
    ];
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Check for suspicious login patterns
     */
    public function checkLoginSecurity($userId, $userType, $ipAddress, $userAgent) {
        $alerts = [];
        
        // Check failed login attempts
        $failedAttempts = $this->getFailedLoginCount($userId, $userType);
        if ($failedAttempts >= $this->suspiciousThresholds['failed_logins_per_hour']) {
            $alerts[] = [
                'type' => 'brute_force_suspected',
                'message' => "Multiple failed login attempts detected: {$failedAttempts} in the last hour"
            ];
        }
        
        // Check for location/IP change
        $lastSession = $this->getLastSession($userId, $userType);
        if ($lastSession && $lastSession['ip_address'] !== $ipAddress) {
            $alerts[] = [
                'type' => 'new_location',
                'message' => "Login from new IP address: {$ipAddress} (previous: {$lastSession['ip_address']})"
            ];
        }
        
        // Check for device change
        if ($lastSession && $lastSession['user_agent'] !== $userAgent) {
            $alerts[] = [
                'type' => 'new_device',
                'message' => "Login from new device detected"
            ];
        }
        
        // Check concurrent sessions
        $activeSessions = $this->getActiveSessionCount($userId, $userType);
        if ($activeSessions >= $this->suspiciousThresholds['concurrent_sessions_max']) {
            $alerts[] = [
                'type' => 'too_many_sessions',
                'message' => "High number of concurrent sessions: {$activeSessions}"
            ];
        }
        
        // Log alerts
        foreach ($alerts as $alert) {
            $this->logSecurityAlert($userId, $userType, $alert);
        }
        
        return $alerts;
    }
    
    /**
     * Log security alert
     */
    private function logSecurityAlert($userId, $userType, $alert) {
        $stmt = $this->db->prepare("
            INSERT INTO security_alerts 
            (user_id, user_type, alert_type, message, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, datetime('now'))
        ");
        $stmt->execute([
            $userId,
            $userType,
            $alert['type'],
            $alert['message'],
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
        
        // Send notification for high-severity alerts
        if (in_array($alert['type'], ['brute_force_suspected', 'too_many_sessions'])) {
            $this->notifyAdmin($alert);
        }
    }
}
```

### Database Schema

```sql
CREATE TABLE security_alerts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER,
    user_type TEXT CHECK(user_type IN ('admin', 'affiliate', 'customer')),
    alert_type TEXT NOT NULL,
    message TEXT NOT NULL,
    ip_address TEXT,
    is_resolved INTEGER DEFAULT 0,
    resolved_by INTEGER REFERENCES users(id),
    resolved_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL,
    user_type TEXT CHECK(user_type IN ('admin', 'affiliate', 'customer')),
    ip_address TEXT,
    user_agent TEXT,
    success INTEGER DEFAULT 0,
    failure_reason TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_security_alerts_user ON security_alerts(user_id, user_type);
CREATE INDEX idx_security_alerts_type ON security_alerts(alert_type);
CREATE INDEX idx_login_attempts_email ON login_attempts(email);
CREATE INDEX idx_login_attempts_ip ON login_attempts(ip_address);
CREATE INDEX idx_login_attempts_created ON login_attempts(created_at);
```

### Session List UI (Customer Dashboard)

```html
<!-- Active Sessions -->
<div class="security-section">
    <h3 class="font-semibold mb-4">Active Sessions</h3>
    
    <div class="session-list">
        <!-- Current Session -->
        <div class="session-item current">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="session-icon">
                        <i class="bi bi-laptop"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium">
                            Chrome on Windows
                            <span class="badge badge-green ml-2">Current</span>
                        </p>
                        <p class="text-sm text-gray-500">Lagos, Nigeria • Last active: Just now</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Other Sessions -->
        <div class="session-item">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <div class="session-icon">
                        <i class="bi bi-phone"></i>
                    </div>
                    <div class="ml-3">
                        <p class="font-medium">Safari on iPhone</p>
                        <p class="text-sm text-gray-500">Abuja, Nigeria • Last active: 2 hours ago</p>
                    </div>
                </div>
                <button @click="revokeSession(sessionId)" class="btn btn-sm btn-danger">
                    Revoke
                </button>
            </div>
        </div>
    </div>
    
    <button @click="revokeAllOtherSessions()" class="btn btn-secondary mt-4">
        <i class="bi bi-shield-x mr-2"></i>
        Sign Out All Other Sessions
    </button>
</div>
```

---

## 2. Encryption Improvements

### Credential Encryption

```php
/**
 * Encryption service for sensitive data
 */
class EncryptionService {
    private $key;
    private $cipher = 'aes-256-gcm';
    
    public function __construct() {
        $this->key = $this->getEncryptionKey();
    }
    
    /**
     * Get or generate encryption key
     */
    private function getEncryptionKey() {
        $keyFile = __DIR__ . '/../.encryption_key';
        
        if (!file_exists($keyFile)) {
            // Generate new key (run once during setup)
            $key = sodium_crypto_secretbox_keygen();
            file_put_contents($keyFile, base64_encode($key));
            chmod($keyFile, 0600);
        }
        
        return base64_decode(file_get_contents($keyFile));
    }
    
    /**
     * Encrypt sensitive data
     */
    public function encrypt($plaintext) {
        if (empty($plaintext)) {
            return null;
        }
        
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
        
        return base64_encode($nonce . $ciphertext);
    }
    
    /**
     * Decrypt sensitive data
     */
    public function decrypt($encrypted) {
        if (empty($encrypted)) {
            return null;
        }
        
        $decoded = base64_decode($encrypted);
        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key);
        
        if ($plaintext === false) {
            throw new Exception('Decryption failed');
        }
        
        return $plaintext;
    }
}

// Usage for storing website credentials
function storeTemplateCredentials($deliveryId, $credentials) {
    $encryption = new EncryptionService();
    $db = getDb();
    
    $encryptedCreds = $encryption->encrypt(json_encode($credentials));
    
    $stmt = $db->prepare("
        UPDATE deliveries 
        SET credentials_encrypted = ?, credentials_set_at = datetime('now')
        WHERE id = ?
    ");
    $stmt->execute([$encryptedCreds, $deliveryId]);
}

function getTemplateCredentials($deliveryId) {
    $encryption = new EncryptionService();
    $db = getDb();
    
    $stmt = $db->prepare("SELECT credentials_encrypted FROM deliveries WHERE id = ?");
    $stmt->execute([$deliveryId]);
    $encrypted = $stmt->fetchColumn();
    
    if (!$encrypted) {
        return null;
    }
    
    return json_decode($encryption->decrypt($encrypted), true);
}
```

### Database Updates

```sql
-- Add encrypted credentials column
ALTER TABLE deliveries ADD COLUMN credentials_encrypted TEXT;
ALTER TABLE deliveries ADD COLUMN credentials_set_at TEXT;

-- Migrate existing plaintext credentials (run migration script)
-- After migration, drop the old plaintext column
```

---

## 3. Rate Limiting

### Implementation

```php
/**
 * Rate limiter for API endpoints
 */
class RateLimiter {
    private $db;
    private $limits = [
        'login' => ['requests' => 5, 'window' => 300], // 5 per 5 minutes
        'otp_request' => ['requests' => 3, 'window' => 3600], // 3 per hour
        'api_general' => ['requests' => 100, 'window' => 60], // 100 per minute
        'download' => ['requests' => 10, 'window' => 60], // 10 per minute
        'password_reset' => ['requests' => 3, 'window' => 3600] // 3 per hour
    ];
    
    public function __construct() {
        $this->db = getDb();
    }
    
    /**
     * Check if action is allowed
     */
    public function isAllowed($action, $identifier) {
        $limit = $this->limits[$action] ?? $this->limits['api_general'];
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM rate_limit_log
            WHERE action = ? AND identifier = ?
            AND created_at > datetime('now', '-' || ? || ' seconds')
        ");
        $stmt->execute([$action, $identifier, $limit['window']]);
        $count = $stmt->fetchColumn();
        
        if ($count >= $limit['requests']) {
            $this->logRateLimitHit($action, $identifier);
            return false;
        }
        
        // Log this request
        $stmt = $this->db->prepare("
            INSERT INTO rate_limit_log (action, identifier, created_at)
            VALUES (?, ?, datetime('now'))
        ");
        $stmt->execute([$action, $identifier]);
        
        return true;
    }
    
    /**
     * Get remaining requests
     */
    public function getRemaining($action, $identifier) {
        $limit = $this->limits[$action] ?? $this->limits['api_general'];
        
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM rate_limit_log
            WHERE action = ? AND identifier = ?
            AND created_at > datetime('now', '-' || ? || ' seconds')
        ");
        $stmt->execute([$action, $identifier, $limit['window']]);
        $count = $stmt->fetchColumn();
        
        return max(0, $limit['requests'] - $count);
    }
    
    /**
     * Clean old entries (run via cron)
     */
    public function cleanup() {
        $this->db->exec("
            DELETE FROM rate_limit_log 
            WHERE created_at < datetime('now', '-1 day')
        ");
    }
}

// Database table
/*
CREATE TABLE rate_limit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    action TEXT NOT NULL,
    identifier TEXT NOT NULL,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_rate_limit_action ON rate_limit_log(action, identifier);
CREATE INDEX idx_rate_limit_created ON rate_limit_log(created_at);
*/

// Usage in API
$rateLimiter = new RateLimiter();
$identifier = $_SERVER['REMOTE_ADDR'];

if (!$rateLimiter->isAllowed('login', $identifier)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many attempts. Please try again later.'
    ]);
    exit;
}
```

---

## 4. Input Validation & Sanitization

### Validation Functions

```php
/**
 * Enhanced input validation
 */
class InputValidator {
    /**
     * Validate and sanitize email
     */
    public static function email($email) {
        $email = trim(strtolower($email));
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Invalid email address');
        }
        
        // Check for disposable email domains
        $disposableDomains = ['tempmail.com', 'throwaway.com', '10minutemail.com'];
        $domain = substr($email, strpos($email, '@') + 1);
        
        if (in_array($domain, $disposableDomains)) {
            throw new ValidationException('Disposable email addresses are not allowed');
        }
        
        return $email;
    }
    
    /**
     * Validate password strength
     */
    public static function password($password) {
        if (strlen($password) < 8) {
            throw new ValidationException('Password must be at least 8 characters');
        }
        
        // Check for common weak passwords
        $weakPasswords = ['password', '12345678', 'qwerty123', 'admin123'];
        if (in_array(strtolower($password), $weakPasswords)) {
            throw new ValidationException('Password is too common. Please choose a stronger password.');
        }
        
        return $password;
    }
    
    /**
     * Validate and sanitize phone number
     */
    public static function phone($phone) {
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            throw new ValidationException('Invalid phone number');
        }
        
        return $phone;
    }
    
    /**
     * Sanitize text input (prevent XSS)
     */
    public static function text($input, $maxLength = 1000) {
        if (empty($input)) {
            return '';
        }
        
        $sanitized = strip_tags(trim($input));
        $sanitized = htmlspecialchars($sanitized, ENT_QUOTES, 'UTF-8');
        
        if (strlen($sanitized) > $maxLength) {
            $sanitized = substr($sanitized, 0, $maxLength);
        }
        
        return $sanitized;
    }
    
    /**
     * Validate integer
     */
    public static function integer($value, $min = null, $max = null) {
        $int = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($int === false) {
            throw new ValidationException('Invalid number');
        }
        
        if ($min !== null && $int < $min) {
            throw new ValidationException("Value must be at least {$min}");
        }
        
        if ($max !== null && $int > $max) {
            throw new ValidationException("Value must be at most {$max}");
        }
        
        return $int;
    }
}
```

---

## 5. Security Headers

### Implementation

```php
/**
 * Set security headers
 * Call this early in every request
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // XSS protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (adjust as needed)
    $csp = "default-src 'self'; ";
    $csp .= "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://js.paystack.co; ";
    $csp .= "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; ";
    $csp .= "font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; ";
    $csp .= "img-src 'self' data: https:; ";
    $csp .= "connect-src 'self' https://api.paystack.co;";
    header('Content-Security-Policy: ' . $csp);
    
    // Strict Transport Security (HTTPS only)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
```

---

## 6. Admin Security Dashboard

### Security Overview

```html
<!-- Admin Security Dashboard -->
<div class="security-dashboard">
    <h2 class="text-xl font-bold mb-6">Security Overview</h2>
    
    <!-- Security Score -->
    <div class="security-score-card mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold">Security Score</h3>
                <p class="text-gray-500">Based on current configuration and recent activity</p>
            </div>
            <div class="text-4xl font-bold text-green-600">92/100</div>
        </div>
        
        <div class="mt-4 space-y-2">
            <div class="flex items-center text-green-600">
                <i class="bi bi-check-circle mr-2"></i>
                HTTPS enabled
            </div>
            <div class="flex items-center text-green-600">
                <i class="bi bi-check-circle mr-2"></i>
                Rate limiting active
            </div>
            <div class="flex items-center text-green-600">
                <i class="bi bi-check-circle mr-2"></i>
                Credential encryption enabled
            </div>
            <div class="flex items-center text-yellow-600">
                <i class="bi bi-exclamation-circle mr-2"></i>
                1 admin without recent password change
            </div>
        </div>
    </div>
    
    <!-- Recent Alerts -->
    <div class="security-alerts mb-6">
        <h3 class="font-semibold mb-4">Recent Security Alerts</h3>
        
        <div class="alert-list">
            <div class="alert-item warning">
                <div class="flex items-center">
                    <i class="bi bi-exclamation-triangle text-yellow-500 mr-3"></i>
                    <div>
                        <p class="font-medium">Multiple failed login attempts</p>
                        <p class="text-sm text-gray-500">IP: 102.89.xx.xx • 5 attempts in 10 minutes</p>
                    </div>
                </div>
                <span class="text-sm text-gray-400">2 hours ago</span>
            </div>
            
            <div class="alert-item info">
                <div class="flex items-center">
                    <i class="bi bi-info-circle text-blue-500 mr-3"></i>
                    <div>
                        <p class="font-medium">New device login: Admin User</p>
                        <p class="text-sm text-gray-500">Chrome on macOS • Lagos, Nigeria</p>
                    </div>
                </div>
                <span class="text-sm text-gray-400">Yesterday</span>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="security-actions">
        <h3 class="font-semibold mb-4">Quick Actions</h3>
        
        <div class="grid grid-cols-3 gap-4">
            <button class="action-card">
                <i class="bi bi-people text-2xl mb-2"></i>
                <span>View All Sessions</span>
            </button>
            
            <button class="action-card">
                <i class="bi bi-shield-check text-2xl mb-2"></i>
                <span>Security Audit Log</span>
            </button>
            
            <button class="action-card">
                <i class="bi bi-key text-2xl mb-2"></i>
                <span>Rotate Encryption Key</span>
            </button>
        </div>
    </div>
</div>
```

---

## 7. Implementation Checklist

### Phase 1: Session Security
- [ ] Create security_alerts table
- [ ] Create login_attempts table
- [ ] Implement SessionMonitor class
- [ ] Add session list to customer dashboard
- [ ] Add session list to admin panel

### Phase 2: Encryption
- [ ] Implement EncryptionService class
- [ ] Add encrypted credentials column
- [ ] Migrate existing credentials
- [ ] Update credential display logic

### Phase 3: Rate Limiting
- [ ] Create rate_limit_log table
- [ ] Implement RateLimiter class
- [ ] Apply to login endpoints
- [ ] Apply to OTP endpoints
- [ ] Apply to password reset

### Phase 4: Input Validation
- [ ] Create InputValidator class
- [ ] Update all form processing
- [ ] Add client-side validation
- [ ] Implement security headers

### Phase 5: Admin Dashboard
- [ ] Build security overview page
- [ ] Add security score calculation
- [ ] Create alert review interface
- [ ] Add audit log viewer

---

## Related Documents

- [02_CUSTOMER_AUTH.md](./02_CUSTOMER_AUTH.md) - Customer authentication
- [10_SECURITY.md](./10_SECURITY.md) - Original security considerations
- [19_ADMIN_AUTOMATION.md](./19_ADMIN_AUTOMATION.md) - Admin notifications
