# WebDaddy Empire - Implementation Fix Guide

**Date:** December 15, 2025  
**Purpose:** Document all required changes to align checkout, registration, and user account flows with the correct specifications.

---

## Table of Contents
1. [Current State Issues](#current-state-issues)
2. [Checkout Flow - New Users (Conversion-Focused)](#checkout-flow---new-users)
3. [Checkout Flow - Existing Users](#checkout-flow---existing-users)
4. [Registration Page - 3-Step Wizard](#registration-page---3-step-wizard)
5. [Manual Payment Flow](#manual-payment-flow)
6. [Automatic Payment Flow (Paystack)](#automatic-payment-flow-paystack)
7. [Account Completion Modal](#account-completion-modal)
8. [Database Schema Changes](#database-schema-changes)
9. [Admin Panel Changes](#admin-panel-changes)
10. [File-by-File Changes](#file-by-file-changes)

---

## Current State Issues

### What's Wrong Now:
1. **Checkout page asks for "Full Name"** - Should ONLY ask for email + OTP verification
2. **Account creation stores full_name** - Should auto-generate username from email instead
3. **After payment, stays on checkout confirmation page** - Should redirect to `/user/order/{id}`
4. **No account completion modal** - Users can't set password/WhatsApp after purchase
5. **Manual payment shows bank details on checkout** - Should show on user order page
6. **Registration page is single-step** - Should be 3-step wizard with SMS OTP
7. **Admin orders show "Full Name"** - Should show "Username"

---

## Checkout Flow - New Users

### Goal: Maximize conversion by minimizing friction

### Current Flow (WRONG):
```
Email → OTP → [Full Name Input] → Payment → Confirmation Page
```

### Correct Flow:
```
Email → OTP → Payment → /user/order/{id} → Account Completion Modal
```

### Step-by-Step:

1. **User enters email**
   - Check if email exists in `customers` table
   - If NOT exists → Send OTP, show OTP input

2. **User verifies OTP**
   - Validate OTP code
   - Create customer account SILENTLY with:
     - `email` = verified email
     - `username` = auto-generated from email (e.g., "user_abc123")
     - `full_name` = NULL (not needed)
     - `password_hash` = NULL (set later)
     - `phone` = NULL (set later)
     - `whatsapp_number` = NULL (set later)
     - `registration_step` = 'email_verified' (new field)
     - `account_complete` = 0 (new field)
   - Create session, set auth cookie
   - Return: `{ success: true, isNewUser: true, username: "user_abc123" }`

3. **Checkout form is now ready**
   - Personal information section is REMOVED/HIDDEN
   - Show: "Logged in as user_abc123 (email@example.com)"
   - User selects payment method (Paystack or Manual)
   - Clicks "Place Order" or "Proceed to Payment"

4. **Order is created**
   - Link order to `customer_id`
   - For Paystack: Initialize payment with user's email
   - For Manual: Create order with status 'pending_payment'

5. **After successful payment (Paystack)**
   - Redirect to `/user/order/{order_id}` NOT checkout confirmation
   - Order page loads with delivery information

6. **Account Completion Modal appears**
   - Blocks the page until completed
   - Multi-step modal (no page reloads)
   - See [Account Completion Modal](#account-completion-modal) section

---

## Checkout Flow - Existing Users

### Correct Flow:
```
Email → [Email exists] → Password → Login → Payment → /user/order/{id}
```

### Step-by-Step:

1. **User enters email**
   - Check if email exists in `customers` table
   - If EXISTS and has password → Show password input
   - If EXISTS but no password (incomplete account) → Send OTP instead

2. **User enters correct password**
   - Validate password hash
   - Create session, set auth cookie
   - Return: `{ success: true, isNewUser: false, username: "existing_user" }`

3. **Checkout continues normally**
   - User already has complete account, no modal needed after purchase
   - After payment, redirect to `/user/order/{id}`

---

## Registration Page - 3-Step Wizard

### Location: `/user/register.php`

### Current State (WRONG):
- Single page with all fields
- No step progression
- No SMS OTP verification

### Correct Flow:
```
Step 1: Email → OTP Verification
Step 2: Username + Password + Confirm Password
Step 3: WhatsApp Number + Phone Number (SMS OTP verified)
Final: Success + Dashboard Guide
```

### Step 1: Email Verification
```
┌─────────────────────────────────────┐
│  Step 1 of 3: Verify Your Email     │
│                                     │
│  Email: [___________________]       │
│                                     │
│  [Send Verification Code]           │
│                                     │
│  ─────────────────────────────────  │
│                                     │
│  Enter OTP: [______]                │
│                                     │
│  [Verify & Continue →]              │
└─────────────────────────────────────┘
```

**API:** `POST /api/customer/request-otp.php`
**Verify:** `POST /api/customer/verify-otp.php`

### Step 2: Create Credentials
```
┌─────────────────────────────────────┐
│  Step 2 of 3: Create Your Account   │
│                                     │
│  Username: [___________________]    │
│  (pre-filled with auto-generated)   │
│                                     │
│  Password: [___________________]    │
│                                     │
│  Confirm Password: [_____________]  │
│                                     │
│  [Continue →]                       │
└─────────────────────────────────────┘
```

**API:** `POST /api/customer/set-credentials.php` (NEW)

### Step 3: Contact Information
```
┌─────────────────────────────────────────────┐
│  Step 3 of 3: Contact Information           │
│                                             │
│  WhatsApp Number: [+234 _______________]    │
│  (We'll contact you here for support)       │
│                                             │
│  Phone Number: [+234 _______________]       │
│  (SMS will be sent to verify this number)   │
│                                             │
│  [Send SMS Code]                            │
│                                             │
│  Enter SMS Code: [______]                   │
│                                             │
│  [Complete Registration →]                  │
└─────────────────────────────────────────────┘
```

**API:** `POST /api/customer/send-phone-otp.php` (NEW - uses Termii)
**Verify:** `POST /api/customer/verify-phone.php` (NEW)

### Final: Success Screen
```
┌─────────────────────────────────────────────┐
│  ✓ Account Created Successfully!            │
│                                             │
│  Welcome to WebDaddy Empire!                │
│                                             │
│  Here's how to use your dashboard:          │
│  • Browse templates and tools               │
│  • Track your orders                        │
│  • Access your downloads                    │
│  • View delivery credentials                │
│                                             │
│  [Go to Dashboard →]                        │
└─────────────────────────────────────────────┘
```

---

## Manual Payment Flow

### Current State (WRONG):
- Bank account details shown on checkout page
- User stays on checkout page

### Correct Flow:
```
Email → OTP → Select Manual → Create Order → /user/order/{id} → Bank Details Shown → "I've Paid" → WhatsApp Link
```

### On `/user/order/{id}` for Pending Manual Payment:

```
┌─────────────────────────────────────────────────────────┐
│  Order #12345 - Pending Payment                         │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │  Payment Instructions                            │   │
│  │                                                  │   │
│  │  Amount: ₦25,000                                 │   │
│  │                                                  │   │
│  │  Bank: First Bank                                │   │
│  │  Account Number: 1234567890                      │   │
│  │  Account Name: WebDaddy Empire                   │   │
│  │                                                  │   │
│  │  [Copy Account Number]                           │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  Items:                                                 │
│  • Premium Template - ₦15,000                           │
│  • SEO Tool Bundle - ₦10,000                            │
│                                                         │
│  Status: ⏳ Awaiting Payment                            │
│                                                         │
│  [I've Made Payment →]                                  │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### After "I've Made Payment":
- Open WhatsApp with pre-filled message
- Message includes: Order ID, Amount, Items
- Admin verifies and updates order status

### Account Completion Modal:
- Same as automatic flow
- Appears after user lands on order page (first-time users only)

---

## Automatic Payment Flow (Paystack)

### Correct Flow:
```
Email → OTP → Select Paystack → Paystack Popup → Payment Success → Redirect to /user/order/{id} → Account Completion Modal (first-time only)
```

### Key Changes:

1. **Paystack initialization:**
   - Use customer's verified email
   - Callback URL: Current behavior
   - On success: Redirect to `/user/order/{id}` instead of checkout confirmation

2. **In `cart-checkout.php` or webhook handler:**
   ```php
   // After successful Paystack payment
   $redirectUrl = "/user/order/" . $orderId;
   // NOT: $redirectUrl = "/cart-checkout.php?confirmed=" . $orderId;
   ```

3. **Order page checks if account is complete:**
   ```php
   if ($customer['account_complete'] == 0) {
       // Show account completion modal
   }
   ```

---

## Account Completion Modal

### Purpose:
Allow first-time purchasers to complete their account AFTER buying (not before).

### Triggers:
- User lands on `/user/order/{id}` 
- AND `customer.account_complete = 0`
- AND this is their first visit to this order

### Modal Design (Multi-Step, No Page Reloads):

#### Step 1: Credentials
```
┌─────────────────────────────────────────────┐
│  Complete Your Account                      │
│  Step 1 of 2                                │
│                                             │
│  Your auto-generated username:              │
│  [user_abc123___________] (editable)        │
│                                             │
│  Create a password:                         │
│  [_________________________]                │
│                                             │
│  Confirm password:                          │
│  [_________________________]                │
│                                             │
│  [Continue →]                               │
└─────────────────────────────────────────────┘
```

#### Step 2: Contact Info + Phone Verification
```
┌─────────────────────────────────────────────┐
│  Complete Your Account                      │
│  Step 2 of 2                                │
│                                             │
│  WhatsApp Number:                           │
│  [+234 ___________________]                 │
│  (For order updates and support)            │
│                                             │
│  Phone Number:                              │
│  [+234 ___________________]                 │
│  (We'll send SMS to verify)                 │
│                                             │
│  [Send SMS Code]                            │
│                                             │
│  Enter Code: [______]                       │
│                                             │
│  [Complete Account →]                       │
└─────────────────────────────────────────────┘
```

### After Completion:
- Modal closes
- User can now see full order details and deliveries
- `customer.account_complete = 1`
- `customer.registration_step = 'completed'`

---

## Database Schema Changes

### `customers` Table Updates:

```sql
-- Add new columns
ALTER TABLE customers ADD COLUMN username VARCHAR(50) UNIQUE;
ALTER TABLE customers ADD COLUMN registration_step VARCHAR(20) DEFAULT 'started';
-- Values: 'started', 'email_verified', 'credentials_set', 'phone_verified', 'completed'

ALTER TABLE customers ADD COLUMN account_complete INTEGER DEFAULT 0;
-- 0 = incomplete, 1 = complete

ALTER TABLE customers ADD COLUMN whatsapp_number VARCHAR(20);
ALTER TABLE customers ADD COLUMN phone_verified INTEGER DEFAULT 0;
ALTER TABLE customers ADD COLUMN phone_verified_at DATETIME;

-- full_name can remain but is no longer required
-- It will be NULL for checkout-created accounts
```

### Username Auto-Generation Logic:

```php
function generateUsernameFromEmail($email) {
    // Extract local part before @
    $localPart = explode('@', $email)[0];
    
    // Clean it (remove special chars)
    $base = preg_replace('/[^a-zA-Z0-9]/', '', $localPart);
    
    // Limit length
    $base = substr($base, 0, 15);
    
    // Add random suffix for uniqueness
    $suffix = substr(md5(uniqid()), 0, 4);
    
    $username = $base . '_' . $suffix;
    
    // Check uniqueness, regenerate if needed
    $db = getDb();
    $stmt = $db->prepare("SELECT id FROM customers WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        // Regenerate with different suffix
        return generateUsernameFromEmail($email);
    }
    
    return $username;
}

// Example outputs:
// john.doe@gmail.com → johndoe_a3f2
// user123@yahoo.com → user123_b7c9
```

---

## Admin Panel Changes

### Orders List (`admin/orders.php`):
- Replace "Full Name" column with "Username"
- Show email alongside username
- Format: `username (email@example.com)`

### Order Detail (`admin/order-detail.php`):
- Customer section shows: Username, Email, WhatsApp, Phone
- No "Full Name" field

### Query Changes:
```php
// OLD:
$stmt = $db->prepare("
    SELECT o.*, c.full_name, c.email
    FROM pending_orders o
    LEFT JOIN customers c ON o.customer_id = c.id
");

// NEW:
$stmt = $db->prepare("
    SELECT o.*, c.username, c.email, c.whatsapp_number, c.phone
    FROM pending_orders o
    LEFT JOIN customers c ON o.customer_id = c.id
");
```

---

## File-by-File Changes

### 1. `cart-checkout.php`

**Remove:**
- Full Name input field (lines ~1030-1045)
- `newName` Alpine.js variable
- Name validation in form submission

**Modify:**
- Personal information section: Only show "Logged in as {username} ({email})"
- After Paystack success: Redirect to `/user/order/{id}`
- After Manual order creation: Redirect to `/user/order/{id}`
- Remove confirmation page logic (or redirect it)

**Add:**
- Logic to detect `?confirmed={id}` and redirect to `/user/order/{id}`

### 2. `api/customer/verify-otp.php`

**Modify:**
- On new user creation: Generate username, set `account_complete = 0`
- Return username in response (not full_name)

```php
// Current response:
return ['success' => true, 'name' => $customer['full_name']];

// New response:
return [
    'success' => true, 
    'username' => $customer['username'],
    'isNewUser' => $isNewUser,
    'accountComplete' => $customer['account_complete']
];
```

### 3. `user/order-detail.php`

**Add:**
- Check if `customer.account_complete = 0`
- If incomplete: Include account completion modal HTML
- Modal JavaScript for multi-step flow

**Modify:**
- For manual payments: Show bank account details
- Show "I've Made Payment" button that opens WhatsApp

### 4. `user/register.php`

**Complete Rewrite:**
- Change from single-step to 3-step wizard
- Step 1: Email + OTP
- Step 2: Username + Password
- Step 3: WhatsApp + Phone (with SMS OTP)
- Use Alpine.js for step transitions without page reload

### 5. `includes/customer_auth.php`

**Add Functions:**
- `generateUsernameFromEmail($email)`
- `setCustomerCredentials($customerId, $username, $password)`
- `setCustomerContactInfo($customerId, $whatsapp, $phone)`
- `completeCustomerAccount($customerId)`
- `isAccountComplete($customerId)`

**Modify:**
- `createCustomer()` - Accept username instead of full_name
- `getCustomerById()` - Return new fields

### 6. `includes/customer_otp.php`

**Modify:**
- `sendOTPEmail()` - Already fixed, uses 2 parameters
- OTP creation should work with the new account structure

### 7. New API Endpoints Needed:

```
api/customer/set-credentials.php    - POST: Set username & password
api/customer/set-contact-info.php   - POST: Set WhatsApp & phone
api/customer/send-phone-otp.php     - POST: Send SMS OTP via Termii
api/customer/verify-phone.php       - POST: Verify phone SMS OTP
api/customer/complete-account.php   - POST: Mark account as complete
```

### 8. `assets/js/customer-auth.js`

**Modify:**
- Remove full name handling
- Update `setAuthenticatedState` to use username
- Handle new response format from verify-otp

### 9. Admin Files:
- `admin/orders.php` - Change column from "Full Name" to "Username"
- `admin/order-detail.php` - Update customer info display
- `admin/customers.php` - Update customer list display

---

## Implementation Priority

### Phase 1: Core Checkout Fix (HIGH PRIORITY)
1. Remove name field from checkout
2. Update verify-otp to auto-generate username
3. Redirect to /user/order/{id} after payment
4. Database migration for new columns

### Phase 2: Account Completion Modal
1. Create modal component
2. Add to order-detail.php
3. Create new API endpoints for credentials/contact

### Phase 3: Registration Page Rewrite
1. Implement 3-step wizard
2. Integrate Termii SMS OTP
3. Add phone verification flow

### Phase 4: Manual Payment Flow
1. Move bank details to order page
2. Add "I've Made Payment" button
3. WhatsApp integration

### Phase 5: Admin Updates
1. Update order displays
2. Change full_name to username everywhere

---

## Testing Checklist

- [ ] New user can checkout with just email OTP
- [ ] Account is created with auto-generated username
- [ ] Paystack payment redirects to /user/order/{id}
- [ ] Manual payment shows bank details on order page
- [ ] Account completion modal appears for first-time users
- [ ] Modal multi-step works without page reload
- [ ] Username can be edited in modal
- [ ] Password is saved correctly
- [ ] WhatsApp number is saved
- [ ] Phone number SMS OTP works
- [ ] Registration page 3-step flow works
- [ ] Admin orders show username, not full name
- [ ] Existing users can login with password
- [ ] Existing users don't see account completion modal

---

## Notes

1. **WhatsApp vs Phone:** User clarified WhatsApp number is NOT verified (just stored), but Phone number IS verified via SMS OTP.

2. **Session Persistence:** Login sessions should be long-lasting so users don't need to login frequently.

3. **Conversion Focus:** The whole point is users can buy BEFORE thinking about registration. Account details come after they've already committed.

4. **Auto-generated Username:** Can be edited by user in the account completion modal or profile settings later.
