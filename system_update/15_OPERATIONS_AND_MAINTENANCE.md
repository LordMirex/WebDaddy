# Operations and Maintenance Guide

## Overview

This document outlines the daily, weekly, and monthly operational tasks required to maintain the customer account system. Following these procedures ensures system reliability, security, and optimal performance.

---

## Daily Operations

### Morning Checklist (9:00 AM)

| Task | Priority | Duration | Action |
|------|----------|----------|--------|
| Check error logs | High | 5 min | Review for overnight errors |
| Verify system health | High | 2 min | Run health check endpoint |
| Check Termii balance | Medium | 1 min | Ensure SMS credits available |
| Review failed deliveries | Medium | 5 min | Check for stuck orders |
| Check email queue | Low | 2 min | Verify emails sending |

### Error Log Review

```bash
# Check PHP error log (last 100 lines)
tail -100 /var/log/php_errors.log | grep -i "error\|warning\|fatal"

# Check for customer-related errors
grep -i "customer" /var/log/php_errors.log | tail -50

# Check for OTP failures
grep -i "otp\|termii" /var/log/php_errors.log | tail -20
```

### Health Check

```bash
# Quick health check
curl -s https://yoursite.com/health.php | jq .

# Expected output:
# {
#   "status": "ok",
#   "checks": {
#     "database": "ok",
#     "customers": "ok"
#   }
# }
```

### Termii Balance Check

```php
<?php
// cron/check_termii_balance.php
require_once __DIR__ . '/../includes/termii.php';
require_once __DIR__ . '/../includes/mailer.php';

$balance = getTermiiBalance();
$amount = $balance['balance'] ?? 0;

// Log daily
error_log("Termii Balance: NGN {$amount}");

// Alert if low (below 1000 NGN)
if ($amount < 1000) {
    sendEmail(
        ADMIN_EMAIL,
        'ALERT: Low Termii SMS Balance',
        "Current balance: NGN {$amount}. Please top up soon to avoid OTP delivery failures."
    );
}
```

### Failed Deliveries Check

```sql
-- Check for stuck deliveries (pending > 1 hour for tools)
SELECT 
    d.id,
    d.order_id,
    d.product_type,
    d.status,
    d.created_at,
    julianday('now') - julianday(d.created_at) as hours_pending
FROM deliveries d
WHERE d.status = 'pending'
AND julianday('now') - julianday(d.created_at) > 0.04  -- > 1 hour
ORDER BY d.created_at ASC;

-- Check for failed deliveries needing attention
SELECT 
    d.id,
    d.order_id,
    d.product_type,
    d.failure_reason,
    d.retry_count
FROM deliveries d
WHERE d.status = 'failed'
AND d.retry_count >= 3;
```

---

## Weekly Operations

### Monday Tasks

| Task | Duration | Description |
|------|----------|-------------|
| Database backup | 10 min | Full backup with verification |
| Customer analytics | 15 min | Review signup/activity metrics |
| Support ticket review | 20 min | Check unresolved tickets |
| Security log review | 10 min | Check for suspicious activity |

### Database Backup

```bash
#!/bin/bash
# weekly_backup.sh

BACKUP_DIR="/path/to/backups/weekly"
DB_PATH="/path/to/database/webdaddy.db"
DATE=$(date +%Y%m%d)

# Create backup
sqlite3 $DB_PATH ".backup $BACKUP_DIR/webdaddy_$DATE.db"

# Verify backup
if sqlite3 $BACKUP_DIR/webdaddy_$DATE.db "SELECT COUNT(*) FROM customers;" > /dev/null 2>&1; then
    echo "Backup verified: webdaddy_$DATE.db"
    
    # Compress older backups
    find $BACKUP_DIR -name "*.db" -mtime +7 -exec gzip {} \;
    
    # Delete backups older than 30 days
    find $BACKUP_DIR -name "*.gz" -mtime +30 -delete
else
    echo "ERROR: Backup verification failed!"
    # Send alert
fi
```

### Customer Analytics Report

```sql
-- Weekly customer signup report
SELECT 
    DATE(created_at) as date,
    COUNT(*) as new_customers,
    SUM(CASE WHEN email_verified = 1 THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN password_hash IS NOT NULL THEN 1 ELSE 0 END) as with_password
FROM customers
WHERE created_at > datetime('now', '-7 days')
GROUP BY DATE(created_at)
ORDER BY date DESC;

-- Customer activity summary
SELECT 
    COUNT(DISTINCT customer_id) as active_customers,
    COUNT(*) as total_logins
FROM customer_sessions
WHERE last_activity_at > datetime('now', '-7 days');

-- Top customers by orders
SELECT 
    c.email,
    c.full_name,
    COUNT(po.id) as order_count,
    SUM(po.final_amount) as total_spent
FROM customers c
JOIN pending_orders po ON po.customer_id = c.id
WHERE po.status = 'paid'
AND po.created_at > datetime('now', '-7 days')
GROUP BY c.id
ORDER BY total_spent DESC
LIMIT 10;
```

### Support Ticket Review

```sql
-- Open tickets older than 48 hours
SELECT 
    t.id,
    t.subject,
    t.status,
    t.priority,
    c.email as customer_email,
    t.created_at,
    julianday('now') - julianday(t.created_at) as days_open
FROM customer_support_tickets t
JOIN customers c ON t.customer_id = c.id
WHERE t.status IN ('open', 'pending')
AND julianday('now') - julianday(t.created_at) > 2
ORDER BY t.priority DESC, t.created_at ASC;

-- Ticket resolution stats
SELECT 
    status,
    COUNT(*) as count,
    AVG(julianday(resolved_at) - julianday(created_at)) as avg_resolution_days
FROM customer_support_tickets
WHERE created_at > datetime('now', '-7 days')
GROUP BY status;
```

### Security Log Review

```sql
-- Failed login attempts (potential brute force)
SELECT 
    email,
    COUNT(*) as attempts,
    MAX(created_at) as last_attempt
FROM customer_activity_log
WHERE action = 'login_failed'
AND created_at > datetime('now', '-7 days')
GROUP BY email
HAVING COUNT(*) > 5
ORDER BY attempts DESC;

-- Suspicious OTP requests (rate limit hits)
SELECT 
    email,
    COUNT(*) as otp_requests,
    COUNT(DISTINCT DATE(created_at)) as days_active
FROM customer_otp_codes
WHERE created_at > datetime('now', '-7 days')
GROUP BY email
HAVING COUNT(*) > 10
ORDER BY otp_requests DESC;

-- New device logins
SELECT 
    c.email,
    cs.device_name,
    cs.ip_address,
    cs.created_at
FROM customer_sessions cs
JOIN customers c ON cs.customer_id = c.id
WHERE cs.created_at > datetime('now', '-7 days')
ORDER BY cs.created_at DESC
LIMIT 50;
```

---

## Monthly Operations

### First Week of Month

| Task | Duration | Description |
|------|----------|-------------|
| Full system audit | 1 hour | Review all components |
| Performance check | 30 min | Database optimization |
| Security updates | 30 min | Apply any patches |
| Documentation review | 30 min | Update if needed |

### Database Optimization

```sql
-- Analyze tables for query optimization
ANALYZE customers;
ANALYZE customer_sessions;
ANALYZE customer_otp_codes;
ANALYZE pending_orders;
ANALYZE deliveries;

-- Check database size
SELECT 
    name,
    SUM(pgsize) as size_bytes,
    ROUND(SUM(pgsize) / 1024.0 / 1024.0, 2) as size_mb
FROM dbstat
GROUP BY name
ORDER BY size_bytes DESC;

-- Vacuum database (run during low traffic)
VACUUM;

-- Reindex for performance
REINDEX;
```

### Session Cleanup

```sql
-- Delete expired sessions (keep last 30 days of inactive)
DELETE FROM customer_sessions 
WHERE is_active = 0 
AND last_activity_at < datetime('now', '-30 days');

-- Expire old sessions that haven't been used
UPDATE customer_sessions
SET is_active = 0, revoked_at = datetime('now'), revoke_reason = 'expired_inactivity'
WHERE is_active = 1
AND last_activity_at < datetime('now', '-90 days');

-- Count cleaned up
SELECT changes() as sessions_cleaned;
```

### OTP Code Cleanup

```sql
-- Delete old OTP codes (older than 7 days)
DELETE FROM customer_otp_codes
WHERE created_at < datetime('now', '-7 days');

SELECT changes() as otp_codes_cleaned;
```

### Monthly Metrics Report

```sql
-- Monthly customer summary
SELECT 
    strftime('%Y-%m', created_at) as month,
    COUNT(*) as new_customers,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN password_hash IS NOT NULL THEN 1 ELSE 0 END) as with_password
FROM customers
GROUP BY strftime('%Y-%m', created_at)
ORDER BY month DESC
LIMIT 12;

-- Monthly order summary by customer type
SELECT 
    strftime('%Y-%m', po.created_at) as month,
    COUNT(*) as total_orders,
    COUNT(DISTINCT po.customer_id) as unique_customers,
    SUM(po.final_amount) as total_revenue,
    AVG(po.final_amount) as avg_order_value
FROM pending_orders po
WHERE po.status = 'paid'
GROUP BY strftime('%Y-%m', po.created_at)
ORDER BY month DESC
LIMIT 12;

-- Customer retention (returned within 30 days)
SELECT 
    strftime('%Y-%m', first_order) as cohort_month,
    COUNT(*) as customers,
    SUM(CASE WHEN orders_count > 1 THEN 1 ELSE 0 END) as returned,
    ROUND(SUM(CASE WHEN orders_count > 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as return_rate
FROM (
    SELECT 
        customer_id,
        MIN(created_at) as first_order,
        COUNT(*) as orders_count
    FROM pending_orders
    WHERE status = 'paid'
    AND customer_id IS NOT NULL
    GROUP BY customer_id
) 
GROUP BY strftime('%Y-%m', first_order)
ORDER BY cohort_month DESC
LIMIT 12;
```

---

## Automated Cron Jobs

### Cron Schedule

```bash
# /etc/cron.d/webdaddy-customer-system

# Every 5 minutes: Check SLA breaches
*/5 * * * * www-data php /path/to/cron/check_delivery_sla.php >> /var/log/cron/sla.log 2>&1

# Every 15 minutes: Process failed deliveries
*/15 * * * * www-data php /path/to/cron/process_failed_deliveries.php >> /var/log/cron/delivery.log 2>&1

# Every hour: Clean expired OTPs
0 * * * * www-data php /path/to/cron/cleanup_expired_otp.php >> /var/log/cron/cleanup.log 2>&1

# Daily 2 AM: Check Termii balance
0 2 * * * www-data php /path/to/cron/check_termii_balance.php >> /var/log/cron/termii.log 2>&1

# Daily 3 AM: Daily backup
0 3 * * * www-data /path/to/scripts/daily_backup.sh >> /var/log/cron/backup.log 2>&1

# Weekly Sunday 4 AM: Database optimization
0 4 * * 0 www-data php /path/to/cron/optimize_database.php >> /var/log/cron/optimize.log 2>&1

# Monthly 1st at 5 AM: Full cleanup
0 5 1 * * www-data php /path/to/cron/monthly_cleanup.php >> /var/log/cron/monthly.log 2>&1
```

### Cron Job Scripts

#### Check Delivery SLA

```php
<?php
// cron/check_delivery_sla.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$db = getDb();

// Find deliveries past SLA
$stmt = $db->query("
    SELECT d.*, po.customer_email, po.customer_name
    FROM deliveries d
    JOIN pending_orders po ON d.order_id = po.id
    WHERE d.status = 'pending'
    AND (
        (d.product_type = 'tool' AND julianday('now') - julianday(d.created_at) > 0.007)  -- 10 min
        OR (d.product_type = 'template' AND julianday('now') - julianday(d.created_at) > 1)  -- 24 hours
    )
");

$breaches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($breaches) > 0) {
    $message = "SLA Breaches Detected:\n\n";
    foreach ($breaches as $d) {
        $message .= "- Order #{$d['order_id']} ({$d['product_type']}): {$d['customer_email']}\n";
    }
    
    sendEmail(ADMIN_EMAIL, 'ALERT: Delivery SLA Breaches', $message);
    error_log("SLA Alert: " . count($breaches) . " breaches detected");
}
```

#### Cleanup Expired OTPs

```php
<?php
// cron/cleanup_expired_otp.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Delete OTPs older than 24 hours
$stmt = $db->prepare("
    DELETE FROM customer_otp_codes 
    WHERE created_at < datetime('now', '-24 hours')
");
$stmt->execute();

$deleted = $stmt->rowCount();
error_log("OTP Cleanup: Deleted {$deleted} expired codes");
```

---

## Monitoring & Alerts

### Key Metrics to Monitor

| Metric | Normal Range | Alert Threshold | Action |
|--------|--------------|-----------------|--------|
| Error rate | < 1% | > 5% | Investigate immediately |
| Response time | < 500ms | > 2000ms | Check database/server load |
| OTP success rate | > 95% | < 90% | Check Termii/email |
| Failed deliveries | < 2% | > 10% | Manual intervention |
| Termii balance | > NGN 5000 | < NGN 1000 | Top up account |

### Alert Escalation

```
Level 1 (Warning):
- Email to admin
- Log to monitoring dashboard

Level 2 (Error):
- Email + SMS to admin
- Create urgent ticket

Level 3 (Critical):
- Email + SMS + Phone call
- Automatic incident creation
- Consider maintenance mode
```

### Health Check Endpoint

```php
<?php
// health.php - Enhanced health check
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json');

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'checks' => []
];

// Database connectivity
try {
    $start = microtime(true);
    $db = getDb();
    $db->query("SELECT 1");
    $health['checks']['database'] = [
        'status' => 'ok',
        'response_time_ms' => round((microtime(true) - $start) * 1000, 2)
    ];
} catch (Exception $e) {
    $health['status'] = 'critical';
    $health['checks']['database'] = ['status' => 'error', 'message' => 'Connection failed'];
}

// Critical tables
$tables = ['customers', 'customer_sessions', 'pending_orders', 'deliveries'];
foreach ($tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $health['checks']['table_' . $table] = ['status' => 'ok', 'count' => (int)$count];
    } catch (Exception $e) {
        $health['status'] = 'warning';
        $health['checks']['table_' . $table] = ['status' => 'error'];
    }
}

// Recent errors check
$recentErrors = $db->query("
    SELECT COUNT(*) FROM customer_activity_log 
    WHERE action LIKE '%error%' 
    AND created_at > datetime('now', '-1 hour')
")->fetchColumn();

if ($recentErrors > 10) {
    $health['status'] = 'warning';
    $health['checks']['recent_errors'] = ['status' => 'elevated', 'count' => (int)$recentErrors];
} else {
    $health['checks']['recent_errors'] = ['status' => 'ok', 'count' => (int)$recentErrors];
}

http_response_code($health['status'] === 'ok' ? 200 : ($health['status'] === 'warning' ? 200 : 503));
echo json_encode($health, JSON_PRETTY_PRINT);
```

---

## Incident Response

### Severity Levels

| Level | Description | Response Time | Example |
|-------|-------------|---------------|---------|
| P1 | Site down / Payment broken | 15 minutes | Database unreachable |
| P2 | Major feature broken | 1 hour | OTP not sending |
| P3 | Minor feature broken | 4 hours | Dashboard slow |
| P4 | Cosmetic / Non-urgent | 24 hours | Typo in email |

### Incident Checklist

```markdown
## Incident: [TITLE]
**Severity:** P1/P2/P3/P4
**Reported:** [DATE TIME]
**Status:** Investigating / Identified / Resolved

### Timeline
- HH:MM - Issue reported
- HH:MM - Investigation started
- HH:MM - Root cause identified
- HH:MM - Fix deployed
- HH:MM - Resolved

### Root Cause
[Description of what caused the issue]

### Resolution
[Steps taken to fix]

### Prevention
[How to prevent in future]

### Customer Impact
- Users affected: [N]
- Duration: [X minutes/hours]
- Compensation needed: Yes/No
```

---

## Related Documents

- [14_DEPLOYMENT_GUIDE.md](./14_DEPLOYMENT_GUIDE.md) - Deployment procedures
- [16_RISKS_ASSUMPTIONS_DEPENDENCIES.md](./16_RISKS_ASSUMPTIONS_DEPENDENCIES.md) - Known risks
- [10_SECURITY.md](./10_SECURITY.md) - Security procedures
- [17_BULLETPROOF_DELIVERY_SYSTEM.md](./17_BULLETPROOF_DELIVERY_SYSTEM.md) - Delivery SLAs
