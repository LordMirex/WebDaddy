# API Endpoints

## Overview

This document specifies all new API endpoints required for the customer account system.

## New Endpoints

### Directory Structure

```
/api/customer/
├── check-email.php     # Check if email exists
├── request-otp.php     # Send OTP (SMS + Email)
├── verify-otp.php      # Verify OTP code
├── login.php           # Password login
├── logout.php          # Logout handler
├── profile.php         # Get/update profile
├── orders.php          # Get customer orders
├── order-detail.php    # Get single order
├── downloads.php       # Get download tokens
├── regenerate-token.php # Regenerate expired download
├── tickets.php         # Get support tickets
├── ticket-reply.php    # Add ticket reply
└── sessions.php        # Manage active sessions
```

---

## 1. Check Email

**Endpoint:** `POST /api/customer/check-email.php`

**Purpose:** Check if email exists in customer database

**Request:**
```json
{
    "email": "user@example.com"
}
```

**Response (Existing user with password):**
```json
{
    "success": true,
    "exists": true,
    "has_password": true,
    "full_name": "John Doe"
}
```

**Response (Existing user without password):**
```json
{
    "success": true,
    "exists": true,
    "has_password": false
}
```

**Response (New user):**
```json
{
    "success": true,
    "exists": false
}
```

**Implementation:**
```php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$result = checkCustomerEmail($email);
echo json_encode(array_merge(['success' => true], $result));
```

---

## 2. Request OTP

**Endpoint:** `POST /api/customer/request-otp.php`

**Purpose:** Send OTP via SMS (Termii) and/or Email

**Request:**
```json
{
    "email": "user@example.com",
    "phone": "+2348012345678",
    "type": "email_verify"
}
```

**Response (Success):**
```json
{
    "success": true,
    "message": "Verification code sent",
    "delivery": {
        "sms": true,
        "email": true
    },
    "expires_in": 600
}
```

**Response (Rate Limited):**
```json
{
    "success": false,
    "message": "Too many requests. Please wait 15 minutes.",
    "retry_after": 900
}
```

**Implementation:**
```php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_otp.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$type = $input['type'] ?? 'email_verify';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

$result = generateCustomerOTP($email, $phone, $type);
echo json_encode($result);
```

---

## 3. Verify OTP

**Endpoint:** `POST /api/customer/verify-otp.php`

**Purpose:** Verify OTP and create/login customer

**Request:**
```json
{
    "email": "user@example.com",
    "code": "123456",
    "type": "email_verify"
}
```

**Response (Success - New Customer):**
```json
{
    "success": true,
    "message": "Email verified",
    "is_new": true,
    "customer_id": 42,
    "full_name": null,
    "phone": null,
    "needs_profile_completion": true
}
```

**Response (Success - Existing Customer):**
```json
{
    "success": true,
    "message": "Email verified",
    "is_new": false,
    "customer_id": 42,
    "full_name": "John Doe",
    "phone": "+2348012345678",
    "needs_profile_completion": false
}
```

**Response (Invalid OTP):**
```json
{
    "success": false,
    "message": "Invalid or expired code",
    "attempts_remaining": 3
}
```

**Implementation:**
```php
<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/customer_auth.php';
require_once __DIR__ . '/../../includes/customer_otp.php';

startSecureSession();
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$code = trim($input['code'] ?? '');
$type = $input['type'] ?? 'email_verify';

// Verify OTP
$verification = verifyCustomerOTP($email, $code, $type);

if (!$verification['success']) {
    echo json_encode($verification);
    exit;
}

// Check if customer exists
$customerCheck = checkCustomerEmail($email);

if ($customerCheck['exists']) {
    // Existing customer - create session
    $customerId = $customerCheck['customer_id'];
    $token = createCustomerSession($customerId);
    
    $customer = getCustomerById($customerId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Email verified',
        'is_new' => false,
        'customer_id' => $customerId,
        'full_name' => $customer['full_name'],
        'phone' => $customer['phone'],
        'needs_profile_completion' => empty($customer['full_name']) || empty($customer['phone'])
    ]);
} else {
    // New customer - create account
    $result = createCustomerAccount($email);
    
    if ($result['success']) {
        $token = createCustomerSession($result['customer_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Account created',
            'is_new' => true,
            'customer_id' => $result['customer_id'],
            'full_name' => null,
            'phone' => null,
            'needs_profile_completion' => true
        ]);
    } else {
        echo json_encode($result);
    }
}
```

---

## 4. Login

**Endpoint:** `POST /api/customer/login.php`

**Purpose:** Password-based login for existing customers

**Request:**
```json
{
    "email": "user@example.com",
    "password": "secret123"
}
```

**Response (Success):**
```json
{
    "success": true,
    "customer": {
        "id": 42,
        "email": "user@example.com",
        "full_name": "John Doe",
        "phone": "+2348012345678"
    }
}
```

**Response (Failure):**
```json
{
    "success": false,
    "message": "Invalid email or password"
}
```

**Response (Rate Limited):**
```json
{
    "success": false,
    "message": "Too many attempts. Please wait 15 minutes.",
    "retry_after": 900
}
```

---

## 5. Logout

**Endpoint:** `POST /api/customer/logout.php`

**Request:**
```json
{
    "all_devices": false
}
```

**Response:**
```json
{
    "success": true,
    "message": "Logged out successfully"
}
```

---

## 6. Profile

**Endpoint:** `GET /api/customer/profile.php`

**Purpose:** Get current customer profile

**Response:**
```json
{
    "success": true,
    "customer": {
        "id": 42,
        "email": "user@example.com",
        "full_name": "John Doe",
        "phone": "+2348012345678",
        "username": "johndoe",
        "created_at": "2025-01-15T10:30:00Z"
    }
}
```

**Endpoint:** `POST /api/customer/profile.php`

**Purpose:** Update customer profile

**Request:**
```json
{
    "full_name": "John Smith",
    "phone": "+2348012345678",
    "username": "johnsmith"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Profile updated"
}
```

---

## 7. Orders

**Endpoint:** `GET /api/customer/orders.php`

**Purpose:** Get customer orders with pagination

**Query Parameters:**
- `status` - Filter by status (pending, paid, completed, all)
- `page` - Page number (default: 1)
- `limit` - Items per page (default: 10, max: 50)

**Response:**
```json
{
    "success": true,
    "orders": [
        {
            "id": 123,
            "created_at": "2025-01-15T10:30:00Z",
            "status": "paid",
            "total": 50000,
            "item_count": 2,
            "items_preview": ["Premium Template", "SEO Tool"],
            "delivery_status": "delivered"
        }
    ],
    "pagination": {
        "current_page": 1,
        "total_pages": 5,
        "total_items": 45
    }
}
```

---

## 8. Order Detail

**Endpoint:** `GET /api/customer/order-detail.php?id=123`

**Response:**
```json
{
    "success": true,
    "order": {
        "id": 123,
        "created_at": "2025-01-15T10:30:00Z",
        "status": "paid",
        "payment_method": "paystack",
        "original_price": 60000,
        "discount_amount": 12000,
        "final_amount": 48000,
        "affiliate_code": "JOHN2024"
    },
    "items": [
        {
            "product_type": "template",
            "product_id": 5,
            "name": "Premium Template",
            "price": 35000,
            "quantity": 1,
            "thumbnail_url": "/uploads/template.jpg",
            "delivery": {
                "status": "delivered",
                "hosted_domain": "mysite.webdaddy.online",
                "login_url": "https://mysite.webdaddy.online/wp-admin",
                "delivered_at": "2025-01-15T12:00:00Z"
            }
        }
    ],
    "timeline": [
        {"event": "order_created", "timestamp": "2025-01-15T10:30:00Z", "description": "Order placed"},
        {"event": "payment_confirmed", "timestamp": "2025-01-15T10:35:00Z", "description": "Payment confirmed"},
        {"event": "delivery_delivered", "timestamp": "2025-01-15T12:00:00Z", "description": "Premium Template delivered"}
    ]
}
```

---

## 9. Downloads

**Endpoint:** `GET /api/customer/downloads.php`

**Response:**
```json
{
    "success": true,
    "downloads": [
        {
            "order_id": 123,
            "tool_id": 7,
            "tool_name": "SEO Tool Pro",
            "tool_thumbnail": "/uploads/seo-tool.jpg",
            "files": [
                {
                    "file_id": 15,
                    "file_name": "seo-tool-v2.1.zip",
                    "file_size": 5242880,
                    "token": "abc123...",
                    "download_count": 2,
                    "max_downloads": 10,
                    "expires_at": "2025-02-15T10:30:00Z",
                    "is_expired": false
                }
            ]
        }
    ]
}
```

---

## 10. Regenerate Token

**Endpoint:** `POST /api/customer/regenerate-token.php`

**Purpose:** Regenerate expired download token

**Request:**
```json
{
    "token_id": 15
}
```

**Response:**
```json
{
    "success": true,
    "new_token": "xyz789...",
    "expires_at": "2025-01-22T10:30:00Z",
    "max_downloads": 5
}
```

---

## 11. Support Tickets

**Endpoint:** `GET /api/customer/tickets.php`

**Response:**
```json
{
    "success": true,
    "tickets": [
        {
            "id": 8,
            "subject": "Cannot access my download",
            "category": "delivery",
            "status": "awaiting_reply",
            "priority": "normal",
            "created_at": "2025-01-14T09:00:00Z",
            "last_reply_at": "2025-01-14T11:30:00Z",
            "last_reply_by": "admin",
            "linked_order_id": 123
        }
    ]
}
```

**Endpoint:** `POST /api/customer/tickets.php`

**Purpose:** Create new ticket

**Request:**
```json
{
    "subject": "Cannot access my download",
    "category": "delivery",
    "message": "I tried to download but the link says expired...",
    "order_id": 123
}
```

---

## 12. Ticket Reply

**Endpoint:** `POST /api/customer/ticket-reply.php`

**Request:**
```json
{
    "ticket_id": 8,
    "message": "Thank you, it's working now!"
}
```

**Response:**
```json
{
    "success": true,
    "reply_id": 25
}
```

---

## 13. Sessions

**Endpoint:** `GET /api/customer/sessions.php`

**Purpose:** Get active sessions

**Response:**
```json
{
    "success": true,
    "sessions": [
        {
            "id": 101,
            "device_name": "Chrome on Windows",
            "ip_address": "41.58.xxx.xxx",
            "last_activity": "2025-01-15T10:30:00Z",
            "is_current": true
        },
        {
            "id": 99,
            "device_name": "Safari on iPhone",
            "ip_address": "41.58.xxx.xxx",
            "last_activity": "2025-01-10T15:00:00Z",
            "is_current": false
        }
    ]
}
```

**Endpoint:** `DELETE /api/customer/sessions.php?id=99`

**Purpose:** Revoke a session

**Response:**
```json
{
    "success": true,
    "message": "Session revoked"
}
```

---

## Authentication Middleware

All customer API endpoints (except check-email, request-otp, verify-otp, login) require authentication:

```php
<?php
// At top of protected endpoint
require_once __DIR__ . '/../../includes/customer_auth.php';

$customer = validateCustomerSession();
if (!$customer) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$customerId = $customer['id'];
```

## Error Response Format

All endpoints use consistent error format:

```json
{
    "success": false,
    "message": "Human-readable error message",
    "error_code": "OPTIONAL_ERROR_CODE"
}
```

## Rate Limiting

| Endpoint | Limit | Window |
|----------|-------|--------|
| check-email | 10/min | Per IP |
| request-otp | 3/hour | Per email |
| verify-otp | 5 attempts | Per OTP |
| login | 5/15min | Per email |
| Other endpoints | 60/min | Per customer |
