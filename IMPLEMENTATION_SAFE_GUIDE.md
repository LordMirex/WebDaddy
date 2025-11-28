# WebDaddy Empire - Safe Implementation & Testing Guide

## Core Principle: VERIFY BEFORE DEPLOY, TEST AT EVERY STEP

This guide ensures your implementation is bulletproof. Follow it exactly to avoid breaking anything.

---

## Phase 1: Pre-Implementation Safety Checks (DO THIS FIRST)

### 1.1 Database Backup
```bash
# Backup your database before ANY changes
cp database/webdaddy.db database/webdaddy.db.backup.$(date +%s)
echo "✅ Database backed up"
```

### 1.2 Current System Verification
```bash
# Verify server is running and healthy
curl -s http://localhost:5000/api/cart.php?action=get | head -20
# Expected: JSON response with no errors

# Verify database is accessible
sqlite3 database/webdaddy.db "SELECT COUNT(*) FROM pending_orders;"
# Expected: Returns a number (count of orders)

# Verify existing payment logs exist
sqlite3 database/webdaddy.db "SELECT COUNT(*) FROM payment_logs;"
# Expected: Returns a number (existing logs)
```

### 1.3 Code Syntax Check (Run Now)
```bash
# Check for PHP syntax errors BEFORE deploying
php -l api/paystack-webhook.php
php -l includes/delivery.php
php -l api/paystack-verify.php
php -l includes/functions.php
# Expected: "No syntax errors detected" for each file
```

---

## Phase 2: Verification of Changes (NOT BREAKING ANYTHING)

### 2.1 Function Existence Check
```bash
# Verify the new email function exists
grep -n "function sendAllToolDeliveryEmailsForOrder" includes/delivery.php
# Expected: Single line showing function definition at line 1497

# Verify function is callable
grep -c "sendAllToolDeliveryEmailsForOrder" api/paystack-webhook.php
# Expected: 1 (function called in webhook handler)

grep -c "sendAllToolDeliveryEmailsForOrder" api/paystack-verify.php
# Expected: 1 (function called in verify handler)

grep -c "sendAllToolDeliveryEmailsForOrder" includes/functions.php
# Expected: 1 (function called in manual payment handler)
```

### 2.2 Dependency Verification
All functions called by the email system must exist:
```bash
# Check formatFileSize exists
grep -n "^function formatFileSize" includes/tool_files.php
# Expected: Found (used by email function)

# Check sendEmail exists
grep -n "^function sendEmail" includes/mailer.php
# Expected: Found (used by email function)

# Check createEmailTemplate exists
grep -n "^function createEmailTemplate" includes/mailer.php
# Expected: Found (used by email function)

# Check getDb exists
grep -n "^function getDb" includes/db.php
# Expected: Found (used by email function)
```

### 2.3 Database Table Check
```bash
# Verify all required tables exist
sqlite3 database/webdaddy.db ".tables" | grep -E "pending_orders|deliveries|payment_logs"
# Expected: All three tables present

# Verify deliveries table has required columns
sqlite3 database/webdaddy.db ".schema deliveries" | grep -E "email_sent_at|delivery_status"
# Expected: Both columns present
```

---

## Phase 3: Integration Testing (SAFE LOCAL TEST)

### 3.1 Test Email Function in Isolation
Create temporary test file (DELETE AFTER):
```php
// File: test-email-function.php
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/delivery.php';
require_once 'includes/mailer.php';
require_once 'includes/tool_files.php';

echo "Testing sendAllToolDeliveryEmailsForOrder function...\n";

// Find an existing order with tools
$db = getDb();
$stmt = $db->query("
    SELECT po.id, COUNT(d.id) as delivery_count
    FROM pending_orders po
    LEFT JOIN deliveries d ON d.pending_order_id = po.id
    WHERE po.status = 'paid' AND d.id IS NOT NULL
    LIMIT 1
");
$testOrder = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$testOrder) {
    echo "❌ No paid orders with deliveries found for testing\n";
    exit(1);
}

echo "Found test order: #{$testOrder['id']} with {$testOrder['delivery_count']} deliveries\n";

// Test the function (won't actually send without email configured)
ob_start();
$result = sendAllToolDeliveryEmailsForOrder($testOrder['id']);
$output = ob_get_clean();

if ($result) {
    echo "✅ Function executed successfully\n";
} else {
    echo "⚠️  Function returned false (likely no email config, but no errors)\n";
}

echo "Done.\n";
?>
```

Run test:
```bash
php test-email-function.php
# Expected: ✅ Function executed successfully (or warning about email config)
# DO NOT expect actual emails - function is just tested for errors
```

### 3.2 Test Webhook Handler Structure
```bash
# Verify webhook file is valid and complete
grep -c "function handleSuccessfulPayment" api/paystack-webhook.php
# Expected: 1

grep -c "function handleFailedPayment" api/paystack-webhook.php
# Expected: 1

grep -c "if (\$_SERVER\['REQUEST_METHOD'\] !== 'POST')" api/paystack-webhook.php
# Expected: 1 (security check present)

grep -c "hash_hmac('sha512'" api/paystack-webhook.php
# Expected: 1 (HMAC verification present)
```

### 3.3 Verify Payment Flow Integration
```bash
# Check client callback calls email function
grep -A 3 "paystack-verify.php" cart-checkout.php | grep -c "sendAllToolDeliveryEmailsForOrder"
# Expected: Function is called OR client-side verification handles it

# Check manual payment handler calls email function
grep -B 5 -A 5 "sendAllToolDeliveryEmailsForOrder" includes/functions.php | head -15
# Expected: Function integrated in markOrderPaid()
```

---

## Phase 4: Staged Deployment (MINIMAL RISK)

### 4.1 Enable Debug Logging
```bash
# Add to includes/config.php temporarily
define('DEBUG_MODE', true);
define('DEBUG_EMAIL_LOG', true);

# Restart server
pkill -f "php.*router.php"
sleep 2
php -d post_max_size=2100M -d upload_max_filesize=2048M -d memory_limit=512M -d max_execution_time=3600 -S 0.0.0.0:5000 router.php &
```

### 4.2 Test with Manual Payment First (SAFEST)
```
STEP 1: Create test order manually
- Go to admin dashboard
- Create order manually with test customer email (your email)
- Set customer email to: test@yourdomain.com

STEP 2: Mark as paid manually
- Admin panel → Orders
- Find test order
- Click "Mark as Paid"
- Watch logs for: ✅ TOOL DELIVERY EMAIL or ❌ ERROR

EXPECTED FLOW:
[Admin clicks Mark as Paid]
  → markOrderPaid() called
  → createDeliveryRecords() runs
  → sendAllToolDeliveryEmailsForOrder() runs
  → error_log shows: "✅ TOOL DELIVERY EMAIL: Successfully sent"

If error appears:
  → Check logs: tail -f logs/error.log
  → Look for "TOOL DELIVERY EMAIL" messages
  → Debug and fix before Paystack testing
```

### 4.3 Monitor Logs During Test
```bash
# In separate terminal, watch logs in real-time
tail -f logs/error.log | grep -E "TOOL DELIVERY|WEBHOOK|EMAIL"

# Expected to see:
# ✅ TOOL DELIVERY EMAIL: Starting for Order #123
# ✅ TOOL DELIVERY EMAIL: Found 2 tools ready for Order #123
# ✅ TOOL DELIVERY EMAIL: Successfully sent all 2 tool(s) to customer@email.com
```

---

## Phase 5: Paystack Webhook Testing (AFTER MANUAL SUCCESS)

### 5.1 Update Paystack Dashboard URLs
**CRITICAL STEP - DO THIS BEFORE WEBHOOK TESTS:**
1. Log into Paystack Dashboard
2. Settings → API Keys & Webhooks
3. Update Webhook URL: `https://your-new-domain/api/paystack-webhook.php`
4. Update Callback URL: `https://your-new-domain/cart-checkout.php`
5. Test webhook delivery in Paystack dashboard (they provide a button)

### 5.2 Test with Paystack Test Card
```
STEP 1: Make test payment
- Go to store website
- Add tool to cart
- Checkout → Pay with Card
- Use test card: 4111 1111 1111 1111
- Any future expiry date
- Any CVV (e.g., 123)
- Complete payment

STEP 2: Watch for email
- Check your email for delivery notification
- Should arrive within 5 seconds
- Should contain all download links

STEP 3: Check logs
- Look for webhook entries:
  ✅ WEBHOOK: Sending tool delivery emails
  ✅ WEBHOOK: Tool delivery emails sent
  ✅ WEBHOOK: Order marked as paid

If NO email received:
  → Check payment_logs table
  → Verify webhook was called
  → Check error logs for email errors
```

---

## Phase 6: Rollback Procedure (IF SOMETHING BREAKS)

### 6.1 Emergency Rollback
```bash
# If something breaks, restore backup
cp database/webdaddy.db.backup.[timestamp] database/webdaddy.db

# Restart server
pkill -f "php.*router.php"
sleep 2
php -d post_max_size=2100M -d upload_max_filesize=2048M -d memory_limit=512M -d max_execution_time=3600 -S 0.0.0.0:5000 router.php &

# Verify working
curl -s http://localhost:5000/api/cart.php?action=get | head -5
```

### 6.2 Code Rollback
```bash
# If code has issue, revert changes
git status
git diff api/paystack-webhook.php  # Review changes
git checkout api/paystack-webhook.php  # Revert if needed
```

---

## Phase 7: Production Monitoring (AFTER DEPLOYMENT)

### 7.1 Daily Checks
```bash
# Every morning, check these:

# 1. Payment processing status
sqlite3 database/webdaddy.db "SELECT COUNT(*) FROM payment_logs WHERE DATE(created_at) = DATE('now');"
# Should show recent payment logs

# 2. Email success rate
sqlite3 database/webdaddy.db "SELECT COUNT(*) FROM deliveries WHERE email_sent_at IS NOT NULL AND DATE(email_sent_at) = DATE('now');"
# Should be equal to paid orders

# 3. Check for errors
tail -n 100 logs/error.log | grep -i "error\|failed"
# Should show no critical errors

# 4. Verify webhook received
grep "charge.success" logs/error.log | tail -5
# Should show recent webhook events
```

### 7.2 Weekly Report
```php
// Create weekly report file (for admin review)
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

$db = getDb();

echo "=== WebDaddy Empire Weekly Payment Report ===\n\n";

// Payments this week
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_payments,
        SUM(amount_paid) as total_amount,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM payments
    WHERE created_at >= datetime('now', '-7 days')
");
$payments = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Payments: {$payments['total_payments']} total, {$payments['successful']} successful, {$payments['failed']} failed\n";
echo "Total Revenue: ₦" . number_format($payments['total_amount'], 2) . "\n\n";

// Email success rate
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_deliveries,
        SUM(CASE WHEN email_sent_at IS NOT NULL THEN 1 ELSE 0 END) as emails_sent
    FROM deliveries
    WHERE created_at >= datetime('now', '-7 days')
");
$deliveries = $stmt->fetch(PDO::FETCH_ASSOC);
$emailRate = ($deliveries['emails_sent'] / $deliveries['total_deliveries']) * 100;
echo "Email Delivery Rate: {$emailRate}% ({$deliveries['emails_sent']}/{$deliveries['total_deliveries']})\n";
?>
```

---

## Verification Checklist (DO THIS NOW)

- [ ] Database backed up
- [ ] PHP syntax verified (no errors)
- [ ] Email function exists in delivery.php
- [ ] Email function called in webhook handler
- [ ] Email function called in verify handler
- [ ] Email function called in manual payment handler
- [ ] All dependencies exist (formatFileSize, sendEmail, createEmailTemplate)
- [ ] All database tables exist
- [ ] Test file runs successfully
- [ ] Logs show no existing errors
- [ ] Server responds HTTP 200

---

## Testing Sequence (IN THIS ORDER)

1. **Manual Payment Test** → Verify email works in controlled environment
2. **Check Logs** → See actual function execution
3. **Update Paystack URLs** → CRITICAL before webhook tests
4. **Paystack Test Payment** → Verify end-to-end flow
5. **Monitor for Issues** → Watch logs for errors
6. **Go Live** → After 3 successful test payments

---

## Expected Log Messages (When Working)

### Successful Payment Flow:
```
✅ WEBHOOK: Sending tool delivery emails
✅ TOOL DELIVERY EMAIL: Starting for Order #123
✅ TOOL DELIVERY EMAIL: Found 2 tools ready for Order #123
✅ TOOL DELIVERY EMAIL: Successfully sent all 2 tool(s) to customer@email.com
✅ WEBHOOK: Tool delivery emails sent
```

### If Something's Wrong:
```
❌ TOOL DELIVERY EMAIL: No customer email found
❌ TOOL DELIVERY EMAIL: No ready tool deliveries found
❌ TOOL DELIVERY EMAIL: Failed to send email
```

---

## Critical Do's and Don'ts

### DO:
- ✅ Backup database before ANY changes
- ✅ Test with manual payment FIRST
- ✅ Monitor logs during testing
- ✅ Keep Paystack URLs updated
- ✅ Test with actual Paystack test card
- ✅ Verify logs show success messages

### DON'T:
- ❌ Make changes to production without backup
- ❌ Test directly with Paystack webhook without manual test
- ❌ Ignore error logs
- ❌ Deploy without verifying syntax
- ❌ Skip the manual payment test
- ❌ Update Paystack URLs without updating code

---

## Success Criteria

**System is working when:**
1. Manual payment → Order marked paid → Email sent ✅
2. Paystack test payment → Webhook called → Email sent ✅
3. All logs show ✅ (no ❌) ✅
4. Customer receives email with download links ✅
5. Download links in email work ✅
6. Affiliate commission credited (if applicable) ✅

**GO LIVE when all above are true.**

---

## Emergency Support

If anything breaks:
1. **Check logs first** - `tail -f logs/error.log`
2. **Verify database** - `sqlite3 database/webdaddy.db ".tables"`
3. **Restore backup** - `cp database/webdaddy.db.backup.* database/webdaddy.db`
4. **Restart server** - `pkill -f "php.*router.php" && php -S 0.0.0.0:5000 router.php &`
5. **Test again** - Verify system is working

**Remember:** Every issue has a log message. Read the logs first.
