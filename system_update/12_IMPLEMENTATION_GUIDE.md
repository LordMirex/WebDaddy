# Implementation Guide

## Overview

This guide provides step-by-step instructions for implementing the customer account system. Follow these phases in order to ensure smooth integration.

## Prerequisites

Before starting implementation:

1. **Backup database:** `cp database/webdaddy.db database/webdaddy.db.backup`
2. **Get SMS Provider (Removed) API key:** Sign up at sms-removed.com, get API credentials
3. **Test environment:** Work on development before production
4. **Read all docs:** Familiarize with all system_update/*.md files

## Phase 1: Database Setup (Day 1)

### Step 1.1: Create Migration Files

Create all migration SQL files in `database/migrations/`:

```bash
mkdir -p database/migrations
```

Create each migration file per `01_DATABASE_SCHEMA.md`.

### Step 1.2: Run Migrations

```php
// database/migrations/run_migrations.php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

$db = getDb();

$migrations = glob(__DIR__ . '/*.sql');
sort($migrations);

foreach ($migrations as $file) {
    echo "Running: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    $db->exec($sql);
    echo "Done.\n";
}

echo "\nAll migrations complete.\n";
```

Run: `php database/migrations/run_migrations.php`

### Step 1.3: Verify Tables

```sql
-- Check tables created
SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;

-- Verify customers table
PRAGMA table_info(customers);

-- Check customer_sessions
PRAGMA table_info(customer_sessions);
```

### Step 1.4: Backfill Customers

Run the backfill migration to create customer accounts from existing orders:

```sql
-- Check backfill result
SELECT COUNT(*) as total_customers FROM customers;
SELECT COUNT(*) as linked_orders FROM pending_orders WHERE customer_id IS NOT NULL;
```

---

## Phase 2: Core Includes (Day 1-2)

### Step 2.1: Create SMS Provider (Removed) Integration

Create `includes/sms-removed.php` per `13_SMS_REMOVED_INTEGRATION.md`.

Test SMS sending:
```php
$result = sendSMS Provider (Removed)SMS('+2348012345678', 'Test message');
var_dump($result);
```

### Step 2.2: Create Customer Auth Functions

Create `includes/customer_auth.php` per `02_CUSTOMER_AUTH.md`.

Functions to implement:
- `checkCustomerEmail()`
- `createCustomerAccount()`
- `loginCustomerWithPassword()`
- `setCustomerPassword()`
- `createCustomerSession()`
- `validateCustomerSession()`
- `logoutCustomer()`
- `getCustomerById()`
- `updateCustomerProfile()`
- `logCustomerActivity()`

### Step 2.3: Create OTP Functions

Create `includes/customer_otp.php` per `02_CUSTOMER_AUTH.md`.

Functions to implement:
- `generateCustomerOTP()`
- `verifyCustomerOTP()`
- `sendOTPEmail()`

### Step 2.4: Add Email Templates

Add to `includes/mailer.php` per `08_EMAIL_TEMPLATES.md`:
- `sendOTPEmail()`
- `sendCustomerWelcomeEmail()`
- `sendPasswordSetEmail()`
- `sendPasswordResetEmail()`
- `sendTicketConfirmationEmail()`
- `sendTicketReplyNotification()`

### Step 2.5: Test Core Functions

```php
// Test email check
$result = checkCustomerEmail('test@example.com');
var_dump($result);

// Test OTP generation (with real email)
$result = generateCustomerOTP('your@email.com');
var_dump($result);
```

---

## Phase 3: API Endpoints (Day 2-3)

### Step 3.1: Create API Directory

```bash
mkdir -p api/customer
```

### Step 3.2: Implement Endpoints

Create each file per `07_API_ENDPOINTS.md`:

1. `api/customer/check-email.php`
2. `api/customer/request-otp.php`
3. `api/customer/verify-otp.php`
4. `api/customer/login.php`
5. `api/customer/logout.php`
6. `api/customer/profile.php`
7. `api/customer/orders.php`
8. `api/customer/order-detail.php`
9. `api/customer/downloads.php`
10. `api/customer/regenerate-token.php`
11. `api/customer/tickets.php`
12. `api/customer/ticket-reply.php`
13. `api/customer/sessions.php`
14. `api/customer/set-password.php`

### Step 3.3: Test API Endpoints

```bash
# Test check-email
curl -X POST http://localhost:5000/api/customer/check-email.php \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com"}'

# Test request-otp
curl -X POST http://localhost:5000/api/customer/request-otp.php \
  -H "Content-Type: application/json" \
  -d '{"email":"your@email.com","type":"email_verify"}'
```

---

## Phase 4: User Portal (Day 3-4)

### Step 4.1: Create Directory Structure

```bash
mkdir -p user/includes
```

### Step 4.2: Create Auth Middleware

Create `user/includes/auth.php` per `04_USER_DASHBOARD.md`.

### Step 4.3: Create Header/Footer

Create `user/includes/header.php` and `user/includes/footer.php`.

Style consistently with admin/affiliate panels.

### Step 4.4: Implement Pages

Order of implementation:

1. `user/login.php` - Login page
2. `user/logout.php` - Logout handler
3. `user/index.php` - Dashboard home
4. `user/orders.php` - Orders list
5. `user/order-detail.php` - Order detail
6. `user/downloads.php` - Download center
7. `user/profile.php` - Profile settings
8. `user/security.php` - Password/sessions
9. `user/support.php` - Tickets list
10. `user/ticket.php` - Ticket detail
11. `user/new-ticket.php` - Create ticket
12. `user/forgot-password.php` - Password reset
13. `user/reset-password.php` - Reset form

### Step 4.5: Test User Portal

1. Login via OTP
2. View dashboard
3. Check orders list
4. View order detail
5. Access downloads
6. Create support ticket

---

## Phase 5: Checkout Integration (Day 4-5)

### Step 5.1: Create Frontend JS

Create `assets/js/customer-auth.js` per `09_FRONTEND_CHANGES.md`.

### Step 5.2: Modify Checkout Page

Update `cart-checkout.php`:

1. Add Alpine.js auth component
2. Replace personal info form with auth flow
3. Add customer_id to order creation
4. Update redirect to user dashboard

### Step 5.3: Test Checkout Flow

Test scenarios:
- [ ] New user: Email → OTP → Verify → Payment
- [ ] Existing user: Email → Password → Login → Payment
- [ ] Existing user without password: Email → OTP → Verify → Payment
- [ ] Failed OTP verification
- [ ] Failed password login
- [ ] Automatic payment completion
- [ ] Manual payment flow

---

## Phase 6: Admin Integration (Day 5)

### Step 6.1: Create Admin Pages

1. `admin/customers.php` - Customer list
2. `admin/customer-detail.php` - Customer detail
3. `admin/customer-tickets.php` - Support tickets

### Step 6.2: Update Existing Admin Pages

Per `06_ADMIN_UPDATES.md`:

1. `admin/index.php` - Add customer stats
2. `admin/orders.php` - Add customer column/filter
3. `admin/includes/header.php` - Add Customers nav

### Step 6.3: Test Admin Features

- [ ] View customer list
- [ ] Search customers
- [ ] View customer detail
- [ ] Suspend/activate customer
- [ ] View customer orders
- [ ] Respond to customer tickets

---

## Phase 7: Frontend Updates (Day 5-6)

### Step 7.1: Update Navbar

Per `09_FRONTEND_CHANGES.md`:

Update `index.php` navbar with account link.

### Step 7.2: Add CSS

Add styles to `assets/css/style.css` for:
- OTP input boxes
- Auth step animations
- Customer badge

### Step 7.3: Test Frontend

- [ ] Account link shows in navbar
- [ ] Shows customer name when logged in
- [ ] Mobile menu works
- [ ] Checkout auth flow works

---

## Phase 8: Delivery System Updates (Day 6)

### Step 8.1: Update Delivery Functions

Per `05_DELIVERY_SYSTEM.md`:

Update `includes/delivery.php`:
- `createDeliveryRecords()` - Add customer_id
- Add `getCustomerDeliveries()`
- Add `getDeliveryForCustomer()`
- Add `getTemplateCredentialsForCustomer()`

### Step 8.2: Update Email Templates

Update delivery emails to include dashboard links.

### Step 8.3: Test Delivery Integration

- [ ] New orders create customer-linked deliveries
- [ ] Customer can view deliveries in dashboard
- [ ] Template credentials accessible
- [ ] Download tokens work from dashboard

---

## Phase 9: Testing & QA (Day 6-7)

### Complete Test Checklist

**Authentication:**
- [ ] New user OTP flow
- [ ] Existing user password login
- [ ] Password reset flow
- [ ] Session persistence (12 months)
- [ ] Logout single/all devices

**Checkout:**
- [ ] Auth flow integration
- [ ] Automatic payment
- [ ] Manual payment
- [ ] Order linked to customer
- [ ] Redirect to dashboard

**User Dashboard:**
- [ ] Orders list loads
- [ ] Order detail shows correctly
- [ ] Delivery status accurate
- [ ] Downloads work
- [ ] Profile update works
- [ ] Support tickets work

**Admin:**
- [ ] Customer list/search
- [ ] Customer detail view
- [ ] Order-customer linking
- [ ] Ticket management
- [ ] Stats accurate

**Security:**
- [ ] Rate limiting works
- [ ] Invalid OTP rejected
- [ ] Session tokens secure
- [ ] Credentials encrypted

---

## Phase 10: Deployment

### Pre-Deployment

1. Run full test suite
2. Backup production database
3. Review error logs

### Deployment Steps

1. Upload new files
2. Run database migrations
3. Clear any caches
4. Test critical flows

### Post-Deployment

1. Monitor error logs
2. Check customer signups
3. Verify email delivery
4. Test checkout flow

---

## Rollback Procedure

If issues arise:

1. Restore database backup
2. Revert file changes
3. Clear sessions

```bash
# Restore database
cp database/webdaddy.db.backup database/webdaddy.db

# Clear sessions
rm -rf /tmp/php_sessions/*
```

---

## Estimated Timeline

| Phase | Duration | Dependencies |
|-------|----------|--------------|
| 1. Database | 0.5 day | None |
| 2. Core Includes | 1 day | Phase 1 |
| 3. API Endpoints | 1 day | Phase 2 |
| 4. User Portal | 1.5 days | Phase 3 |
| 5. Checkout | 1 day | Phase 4 |
| 6. Admin | 0.5 day | Phase 2 |
| 7. Frontend | 0.5 day | Phase 5 |
| 8. Delivery | 0.5 day | Phase 4 |
| 9. Testing | 1 day | All phases |
| 10. Deployment | 0.5 day | Phase 9 |

**Total: ~8 days**
