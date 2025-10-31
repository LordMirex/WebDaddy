# 📋 WebDaddy Configuration Guide

## ⚙️ Single Source of Truth: `includes/config.php`

All configuration is now centralized in **ONE FILE**: `includes/config.php`

---

## 🔧 What You Need to Configure

### **1. Database Settings** (Lines 8-14)
```php
define('DB_HOST', getenv('PGHOST') ?: 'db');
define('DB_NAME', getenv('PGDATABASE') ?: 'template_store');
define('DB_USER', getenv('PGUSER') ?: 'postgres');
define('DB_PASS', getenv('PGPASSWORD') ?: 'postgres');
define('DB_PORT', getenv('PGPORT') ?: 5432);
```

### **2. WhatsApp Number** (Line 18)
```php
$whatsappNumber = '+2349132672126'; // Default fallback
```
**Used in:** All emails, order forms, contact information

### **3. Email/SMTP Settings** (Lines 29-35)
```php
define('SMTP_HOST', 'mail.teslareturns.online');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl');
define('SMTP_USER', 'support@teslareturns.online');
define('SMTP_PASS', 'ItuZq%kF%5oE');
define('SMTP_FROM_EMAIL', 'support@teslareturns.online');
define('SMTP_FROM_NAME', 'WebDaddy Empire');
```
**Used in:** All email notifications (orders, affiliates, withdrawals)

### **4. Affiliate Settings** (Lines 38-40)
```php
define('AFFILIATE_COOKIE_DAYS', 30);        // How long affiliate cookies last
define('AFFILIATE_COMMISSION_RATE', 0.30);  // 30% commission rate
define('CUSTOMER_DISCOUNT_RATE', 0.20);     // 20% discount for customers
```
**Used in:** 
- Commission calculations
- Order pricing
- Affiliate welcome emails
- Dashboard displays

### **5. Site Information** (Lines 43-44)
```php
define('SITE_URL', 'http://localhost:8080');
define('SITE_NAME', 'WebDaddy Empire');
```
**Used in:** All emails, links, branding

### **6. Admin Credentials** (Lines 46-49)
```php
define('ADMIN_EMAIL', 'admin@example.com');
define('ADMIN_PASSWORD', 'admin123');  // CHANGE THIS!
define('ADMIN_NAME', 'Admin User');
define('ADMIN_PHONE', '08012345678');
```
**⚠️ IMPORTANT:** Change the default password before deploying!

### **7. Security Settings** (Line 52)
```php
define('SESSION_LIFETIME', 3600);  // Session timeout in seconds
```

### **8. Error Display** (Line 55)
```php
define('DISPLAY_ERRORS', true);  // Set to FALSE in production!
```

---

## ✅ Files That Use These Configurations

All these files automatically pull from `config.php`:

### Email System:
- ✅ `includes/mailer.php` - All email functions
- ✅ `order.php` - Order confirmation emails
- ✅ `affiliate/register.php` - Welcome emails
- ✅ `affiliate/withdrawals.php` - Withdrawal requests
- ✅ `admin/affiliates.php` - Withdrawal processing
- ✅ `admin/email_affiliate.php` - Custom emails

### Order Processing:
- ✅ `order.php` - Pricing with affiliate discounts
- ✅ `includes/functions.php` - Commission calculations

### Displays:
- ✅ `affiliate/index.php` - Dashboard showing rates
- ✅ All email templates - Showing commission/discount rates

---

## 🎯 Quick Configuration for Production

Before going live, update these in `includes/config.php`:

1. **SMTP Settings** - Use your real email server
2. **SITE_URL** - Change to your actual domain
3. **ADMIN_PASSWORD** - Change from 'admin123'
4. **DISPLAY_ERRORS** - Set to `false`
5. **WhatsApp Number** - Verify it's correct

---

## 📧 How Configuration Flows to Emails

### Example: Affiliate Welcome Email
```
config.php defines:
  AFFILIATE_COMMISSION_RATE = 0.30
  CUSTOMER_DISCOUNT_RATE = 0.20
  SITE_URL = 'http://localhost:8080'
  SITE_NAME = 'WebDaddy Empire'

↓ Used by ↓

mailer.php (sendAffiliateWelcomeEmail):
  - Converts 0.30 → "30%"
  - Converts 0.20 → "20%"
  - Uses SITE_URL for referral link
  - Uses SITE_NAME in email template

↓ Results in ↓

Email shows:
  "Commission Rate: 30%"
  "Customer Discount: 20%"
  "Your link: http://localhost:8080/?aff=ABC123"
  Branded as "WebDaddy Empire"
```

---

## 🔄 What Changes Automatically

When you update `config.php`, these automatically update everywhere:

| Change in config.php | Updates Automatically |
|---------------------|----------------------|
| `AFFILIATE_COMMISSION_RATE` | All commission calculations, affiliate emails, order pricing |
| `CUSTOMER_DISCOUNT_RATE` | Order discounts, affiliate welcome emails |
| `SMTP_FROM_EMAIL` | All email "From" addresses, admin notifications |
| `SMTP_FROM_NAME` | Email sender name, email branding |
| `SITE_URL` | All email links, referral URLs |
| `SITE_NAME` | All email headers, footers, branding |
| `WHATSAPP_NUMBER` | All contact links in emails and forms |

---

## ⚠️ No Hardcoded Values!

All hardcoded values have been removed. Everything pulls from `config.php`:
- ✅ Commission rates
- ✅ Discount rates  
- ✅ Email addresses
- ✅ Site URLs
- ✅ WhatsApp numbers
- ✅ Site names

**You only need to edit `includes/config.php`** - nothing else!

---

## 🧪 Testing Configuration Changes

1. Edit `includes/config.php`
2. Save the file
3. Restart your server (if needed)
4. Changes apply immediately to:
   - New orders
   - New affiliate registrations
   - All emails sent after the change

**No need to edit any other files!**
