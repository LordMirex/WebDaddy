# Deployment Guide

## Overview

This document provides step-by-step instructions for deploying the customer account system to production. Follow these steps carefully to ensure a smooth, zero-downtime deployment.

## Pre-Deployment Checklist

### 1. Code Readiness

- [ ] All phases from `12_IMPLEMENTATION_GUIDE.md` completed
- [ ] All test cases passed (see `13_ACCEPTANCE_TEST_PLAN.md` if available)
- [ ] Code reviewed and approved
- [ ] No console.log or error_log debug statements in production code
- [ ] All API endpoints tested with Postman/cURL

### 2. Environment Preparation

- [ ] Production server access verified
- [ ] Database backup created (within last hour)
- [ ] Termii API key configured in production secrets
- [ ] SMTP credentials verified for production
- [ ] SSL certificate valid and not expiring soon

### 3. Database Backup

```bash
# Create timestamped backup
cp database/webdaddy.db database/backups/webdaddy_$(date +%Y%m%d_%H%M%S).db

# Verify backup integrity
sqlite3 database/backups/webdaddy_*.db "SELECT COUNT(*) FROM pending_orders;"
```

### 4. Rollback Plan Ready

- [ ] Previous working version tagged in git
- [ ] Database backup accessible
- [ ] Rollback procedure documented and tested

---

## Deployment Steps

### Step 1: Enable Maintenance Mode (Optional)

If downtime is acceptable (recommended for first deployment):

```php
// Create maintenance.flag in web root
file_put_contents(__DIR__ . '/maintenance.flag', date('Y-m-d H:i:s'));
```

Add to `index.php` and other entry points:

```php
if (file_exists(__DIR__ . '/maintenance.flag')) {
    http_response_code(503);
    include 'maintenance.html';
    exit;
}
```

### Step 2: Deploy Files

#### Option A: Git Deployment (Recommended)

```bash
# On production server
cd /path/to/webdaddy

# Fetch latest changes
git fetch origin

# Check current branch
git branch

# Pull changes
git pull origin main

# Verify files
ls -la user/
ls -la api/customer/
ls -la includes/customer_*.php
```

#### Option B: FTP/SFTP Deployment

Upload these directories/files in order:

1. **Database migrations first:**
   ```
   database/migrations/*.sql
   database/migrations/run_migrations.php
   ```

2. **Core includes:**
   ```
   includes/config.php (if updated)
   includes/termii.php
   includes/customer_auth.php
   includes/customer_otp.php
   includes/mailer.php (updates)
   includes/delivery.php (updates)
   includes/session.php (updates)
   ```

3. **API endpoints:**
   ```
   api/customer/ (entire folder)
   ```

4. **User portal:**
   ```
   user/ (entire folder)
   ```

5. **Modified files:**
   ```
   cart-checkout.php
   index.php (navbar updates)
   download.php (updates)
   ```

6. **Admin updates:**
   ```
   admin/customers.php
   admin/customer-detail.php
   admin/customer-tickets.php
   admin/includes/header.php (updates)
   admin/index.php (updates)
   admin/orders.php (updates)
   ```

7. **Assets:**
   ```
   assets/js/customer-auth.js
   assets/css/style.css (updates)
   ```

### Step 3: Run Database Migrations

```bash
# Navigate to migrations folder
cd database/migrations

# Run migrations
php run_migrations.php

# Expected output:
# Running: 001_create_customers_table.sql
# Done.
# Running: 002_create_customer_sessions.sql
# Done.
# ... (all migrations)
# All migrations complete.
```

#### Verify Migration Success

```sql
-- Check new tables exist
SELECT name FROM sqlite_master WHERE type='table' AND name LIKE 'customer%';

-- Verify customers table structure
PRAGMA table_info(customers);

-- Check indexes
SELECT name FROM sqlite_master WHERE type='index' AND name LIKE 'idx_customer%';
```

### Step 4: Run Customer Backfill (If Applicable)

Link historical orders to new customer accounts:

```bash
php database/migrations/backfill_customers.php
```

Verify:

```sql
-- Check customers created
SELECT COUNT(*) as total_customers FROM customers;

-- Check orders linked
SELECT COUNT(*) as linked_orders FROM pending_orders WHERE customer_id IS NOT NULL;

-- Sample customer data
SELECT email, full_name, created_at FROM customers LIMIT 10;
```

### Step 5: Set Environment Variables

Ensure these are set in production:

```bash
# Termii SMS
TERMII_API_KEY=your_production_api_key

# Verify in PHP
php -r "echo getenv('TERMII_API_KEY') ? 'Set' : 'Missing';"
```

For Replit, add to Secrets tab:
- `TERMII_API_KEY` - Your Termii API key

### Step 6: Clear Any Caches

```bash
# Clear OPcache if enabled
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo 'Cleared'; }"

# Clear session files (optional, will log out all users)
# rm -rf /tmp/php_sessions/*
```

### Step 7: Disable Maintenance Mode

```bash
rm maintenance.flag
```

### Step 8: Smoke Tests

Run these immediately after deployment:

#### Test 1: Homepage Loads
```bash
curl -I https://yoursite.com/
# Expected: HTTP/2 200
```

#### Test 2: User Login Page
```bash
curl -I https://yoursite.com/user/login.php
# Expected: HTTP/2 200
```

#### Test 3: Customer API
```bash
curl -X POST https://yoursite.com/api/customer/check-email.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'
# Expected: {"success":true,"exists":false}
```

#### Test 4: Admin Dashboard
- Login to admin panel
- Check "Customers" menu item appears
- View customer list (may be empty initially)

#### Test 5: Checkout Flow
- Add item to cart
- Go to checkout
- Enter email
- Verify OTP input appears (new users)

---

## Post-Deployment Verification

### Immediate Checks (First 30 Minutes)

| Check | Command/Action | Expected Result |
|-------|----------------|-----------------|
| Error logs | `tail -f /var/log/php_errors.log` | No new errors |
| Homepage | Visit site | Loads normally |
| Customer login | Visit `/user/login.php` | Page renders |
| Checkout | Add item, go to checkout | Auth flow works |
| Admin | Login to admin | Customers menu visible |

### Extended Checks (First 24 Hours)

- [ ] Monitor error logs every 2-4 hours
- [ ] Check email delivery (OTP, welcome emails)
- [ ] Verify SMS delivery via Termii dashboard
- [ ] Test full checkout with real payment
- [ ] Verify order appears in customer dashboard
- [ ] Test password reset flow
- [ ] Check admin customer management

---

## Rollback Procedure

If critical issues are found:

### Quick Rollback (< 5 minutes)

```bash
# 1. Restore database
cp database/backups/webdaddy_YYYYMMDD_HHMMSS.db database/webdaddy.db

# 2. Revert to previous git commit
git checkout HEAD~1

# 3. Clear caches
php -r "opcache_reset();"

# 4. Verify site works
curl -I https://yoursite.com/
```

### Full Rollback

```bash
# 1. Enable maintenance mode
touch maintenance.flag

# 2. Restore database from backup
cp database/backups/webdaddy_BEFORE_DEPLOY.db database/webdaddy.db

# 3. Revert all file changes
git reset --hard PREVIOUS_COMMIT_HASH

# 4. Remove new directories
rm -rf user/
rm -rf api/customer/

# 5. Clear caches
php -r "opcache_reset();"

# 6. Disable maintenance mode
rm maintenance.flag

# 7. Notify team
# Send message about rollback
```

---

## Environment-Specific Configurations

### Development

```php
// includes/config.php
define('ENVIRONMENT', 'development');
define('DEBUG_MODE', true);
define('TERMII_API_KEY', 'test_key_xxx');
```

### Staging

```php
define('ENVIRONMENT', 'staging');
define('DEBUG_MODE', true);
define('TERMII_API_KEY', getenv('TERMII_API_KEY'));
```

### Production

```php
define('ENVIRONMENT', 'production');
define('DEBUG_MODE', false);
define('TERMII_API_KEY', getenv('TERMII_API_KEY'));

// Error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
```

---

## Deployment Automation (Future)

### Simple Deployment Script

```bash
#!/bin/bash
# deploy.sh

set -e  # Exit on error

echo "Starting deployment..."

# Backup
echo "Creating backup..."
cp database/webdaddy.db database/backups/webdaddy_$(date +%Y%m%d_%H%M%S).db

# Pull latest
echo "Pulling latest code..."
git pull origin main

# Run migrations
echo "Running migrations..."
php database/migrations/run_migrations.php

# Clear cache
echo "Clearing cache..."
php -r "if (function_exists('opcache_reset')) { opcache_reset(); }"

# Health check
echo "Running health check..."
curl -sf https://yoursite.com/health.php || exit 1

echo "Deployment complete!"
```

### Health Check Endpoint

Create `/health.php`:

```php
<?php
require_once __DIR__ . '/includes/db.php';

$health = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'checks' => []
];

// Database check
try {
    $db = getDb();
    $db->query("SELECT 1");
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['status'] = 'error';
    $health['checks']['database'] = 'failed';
}

// Required tables
$tables = ['customers', 'customer_sessions', 'customer_otp_codes'];
foreach ($tables as $table) {
    try {
        $db->query("SELECT 1 FROM {$table} LIMIT 1");
        $health['checks'][$table] = 'ok';
    } catch (Exception $e) {
        $health['status'] = 'warning';
        $health['checks'][$table] = 'missing';
    }
}

header('Content-Type: application/json');
echo json_encode($health, JSON_PRETTY_PRINT);
```

---

## Troubleshooting Common Issues

### Issue: 500 Error After Deployment

```bash
# Check PHP error log
tail -100 /var/log/php_errors.log

# Common causes:
# - Missing PHP extension
# - File permission issues
# - Syntax error in uploaded file
```

### Issue: Database Errors

```bash
# Check database file permissions
ls -la database/webdaddy.db
# Should be readable/writable by web server user

# Fix permissions
chmod 664 database/webdaddy.db
chown www-data:www-data database/webdaddy.db
```

### Issue: OTP Not Sending

```php
// Test Termii directly
require_once 'includes/termii.php';
$balance = getTermiiBalance();
print_r($balance);
// Check if balance > 0
```

### Issue: Sessions Not Working

```bash
# Check session directory
ls -la /tmp/php_sessions/
# Should be writable

# Check session config in php.ini
php -i | grep session.save_path
```

---

## Sign-Off Checklist

Before considering deployment complete, ensure:

- [ ] All smoke tests passed
- [ ] No new errors in logs for 30+ minutes
- [ ] At least one test checkout completed
- [ ] Email/SMS delivery verified
- [ ] Admin panel fully functional
- [ ] Rollback procedure tested (or documented)
- [ ] Team notified of successful deployment
- [ ] This checklist completed and saved

---

## Related Documents

- [12_IMPLEMENTATION_GUIDE.md](./12_IMPLEMENTATION_GUIDE.md) - Implementation steps
- [15_OPERATIONS_AND_MAINTENANCE.md](./15_OPERATIONS_AND_MAINTENANCE.md) - Ongoing operations
- [10_SECURITY.md](./10_SECURITY.md) - Security considerations
