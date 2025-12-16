# File Structure

## Overview

This document provides a complete list of all new files to create and existing files to modify for the customer account system.

## New Files to Create

### Database Migrations

```
database/migrations/
├── 20250113_001_create_customers_table.sql
├── 20250113_002_create_customer_sessions.sql
├── 20250113_003_create_customer_otp_codes.sql
├── 20250113_004_create_customer_password_resets.sql
├── 20250113_005_create_customer_activity_log.sql
├── 20250113_006_create_customer_support_tickets.sql
├── 20250113_007_create_customer_ticket_replies.sql
├── 20250113_008_alter_pending_orders_add_customer_id.sql
├── 20250113_009_alter_sales_add_customer_id.sql
├── 20250113_010_alter_deliveries_add_customer_fields.sql
├── 20250113_011_alter_download_tokens_add_customer_id.sql
├── 20250113_012_alter_cart_items_add_customer_id.sql
├── 20250113_013_backfill_customers_from_orders.sql
└── run_migrations.php
```

### Customer Portal (/user/)

```
user/
├── index.php                 # Dashboard home
├── orders.php                # Orders list
├── order-detail.php          # Single order view
├── downloads.php             # Download center
├── support.php               # Support tickets list
├── ticket.php                # Single ticket view
├── new-ticket.php            # Create ticket
├── profile.php               # Profile settings
├── security.php              # Password & sessions
├── login.php                 # Customer login
├── logout.php                # Logout handler
├── forgot-password.php       # Password reset request
├── reset-password.php        # Password reset form
└── includes/
    ├── auth.php              # Customer auth middleware
    ├── header.php            # Dashboard header
    ├── footer.php            # Dashboard footer
    └── nav.php               # Navigation component
```

### Customer API Endpoints

```
api/customer/
├── check-email.php           # Check email existence
├── request-otp.php           # Send OTP
├── verify-otp.php            # Verify OTP
├── login.php                 # Password login
├── logout.php                # Logout
├── profile.php               # Get/update profile
├── orders.php                # Get orders
├── order-detail.php          # Get single order
├── downloads.php             # Get downloads
├── regenerate-token.php      # Regenerate download token
├── tickets.php               # Get/create tickets
├── ticket-reply.php          # Add ticket reply
├── sessions.php              # Manage sessions
└── set-password.php          # Set password (first time)
```

### Include Files

```
includes/
├── customer_auth.php         # Customer authentication functions
├── customer_session.php      # Session management
├── customer_otp.php          # OTP generation/verification
├── customer_helpers.php      # Helper functions
├── customer_orders.php       # Order-related functions
├── customer_tickets.php      # Ticket functions
└── sms-removed.php                # SMS Provider (Removed) SMS API integration
```

### Admin Extensions

```
admin/
├── customers.php             # Customer list (NEW)
├── customer-detail.php       # Customer detail view (NEW)
└── customer-tickets.php      # Customer support tickets (NEW)
```

### JavaScript

```
assets/js/
├── customer-auth.js          # Customer auth module
└── checkout-auth.js          # Checkout authentication component
```

## Files to Modify

### Core Files

| File | Changes |
|------|---------|
| `includes/config.php` | Add SMS_REMOVED_API_KEY constant, customer session settings |
| `includes/session.php` | Add customer session functions |
| `includes/mailer.php` | Add new email templates (OTP, welcome, tickets) |
| `includes/functions.php` | Add customer helper functions |
| `includes/delivery.php` | Add customer_id linking, dashboard access functions |
| `includes/cart.php` | Add customer cart persistence |
| `database/schema_sqlite.sql` | Add all new table definitions |

### Checkout & Frontend

| File | Changes |
|------|---------|
| `cart-checkout.php` | Replace personal info form with auth flow, link orders to customers |
| `index.php` | Add account link to navbar |
| `assets/css/style.css` | Add OTP input styles, auth step animations |

### Admin Files

| File | Changes |
|------|---------|
| `admin/index.php` | Add customer stats cards |
| `admin/orders.php` | Add customer column, customer filter, order linking |
| `admin/reports.php` | Add customer analytics |
| `admin/includes/header.php` | Add Customers nav section |

### API Files

| File | Changes |
|------|---------|
| `api/paystack-initialize.php` | Accept customer_id, skip redundant fields |
| `api/paystack-verify.php` | Link verified payments to customers |

## File Contents Summary

### includes/customer_auth.php

```php
<?php
/**
 * Customer Authentication Functions
 */

// Check if customer email exists
function checkCustomerEmail($email) { ... }

// Create customer account
function createCustomerAccount($email, $phone = null, $fullName = null) { ... }

// Login with password
function loginCustomerWithPassword($email, $password) { ... }

// Set password for customer
function setCustomerPassword($customerId, $password) { ... }

// Create session
function createCustomerSession($customerId, $daysValid = 365) { ... }

// Validate session
function validateCustomerSession() { ... }

// Logout
function logoutCustomer($allDevices = false) { ... }

// Get customer by ID
function getCustomerById($customerId) { ... }

// Update customer profile
function updateCustomerProfile($customerId, $data) { ... }

// Log customer activity
function logCustomerActivity($customerId, $action, $details = null) { ... }
```

### includes/customer_otp.php

```php
<?php
/**
 * OTP Generation and Verification
 */

// Generate and send OTP
function generateCustomerOTP($email, $phone = null, $type = 'email_verify') { ... }

// Verify OTP code
function verifyCustomerOTP($email, $code, $type = 'email_verify') { ... }

// Send OTP via email
function sendOTPEmail($email, $otpCode) { ... }

// Send OTP via SMS (SMS Provider (Removed))
function sendSMS Provider (Removed)OTP($phone, $otpCode, $otpId) { ... }
```

### includes/sms-removed.php

```php
<?php
/**
 * SMS Provider (Removed) SMS API Integration
 */

// Send SMS via SMS Provider (Removed)
function sendSMS Provider (Removed)SMS($phone, $message) { ... }

// Send OTP via SMS Provider (Removed)
function sendSMS Provider (Removed)OTP($phone, $otp, $otpId) { ... }

// Verify SMS Provider (Removed) delivery status
function checkSMS Provider (Removed)DeliveryStatus($messageId) { ... }
```

### user/includes/auth.php

```php
<?php
/**
 * Customer Authentication Middleware
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';

// Require authenticated customer
function requireCustomer() { ... }

// Get current customer (nullable)
function getCurrentCustomer() { ... }

// Get customer ID
function getCustomerId() { ... }

// Get customer display name
function getCustomerName() { ... }
```

## Directory Creation Commands

```bash
# Create new directories
mkdir -p user/includes
mkdir -p api/customer
mkdir -p database/migrations

# Set permissions
chmod 755 user user/includes api/customer database/migrations
```

## File Count Summary

| Category | New Files | Modified Files |
|----------|-----------|----------------|
| Database | 14 | 1 |
| User Portal | 17 | 0 |
| API | 14 | 2 |
| Includes | 7 | 6 |
| Admin | 3 | 4 |
| Frontend | 2 | 3 |
| **Total** | **57** | **16** |

## Import Order

When implementing, create files in this order:

1. Database migrations (tables must exist first)
2. Core includes (customer_auth.php, customer_otp.php, sms-removed.php)
3. User portal includes (auth.php)
4. API endpoints
5. User portal pages
6. Admin pages
7. Frontend modifications
8. Checkout integration
