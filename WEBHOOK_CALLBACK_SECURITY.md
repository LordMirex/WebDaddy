# WebDaddy Empire - Server-Side Payment Verification Security Upgrade

## Executive Summary
Your current system uses **client-side verification only**, which is vulnerable to fraud. This document outlines the complete security upgrade to implement **server-to-server webhook verification** from Paystack, making your payment system enterprise-grade secure.

**Critical Risk:** Without this upgrade, hackers could:
- Intercept browser requests and fake payment verification
- Download tools/templates without paying
- Get affiliate commissions without real sales
- Compromise your revenue stream

---

## Current Architecture (VULNERABLE)

```
User Payment Flow (Client-Side Only):
┌─────────────┐
│   Paystack  │
└──────┬──────┘
       │ (1) User completes payment
       │
       ├─────────────────────────────────────┐
       │ (2) Paystack redirects to browser   │
       │     with payment reference          │
       │                                     │
       ▼                                     │
┌──────────────────┐                        │
│  User's Browser  │                        │
│ (Client-Side)    │                        │
│                  │                        │
│ Verifies payment │                        │
│ via callback()   │◄────────────────────────┘
└────────┬─────────┘
         │ (3) Browser calls /api/paystack-verify.php
         │ (VULNERABLE - can be intercepted/faked)
         │
         ▼
┌──────────────────────┐
│  Your Server         │
│  (verification only) │
│                      │
│  Mark order as PAID  │
│  Send delivery email │
└──────────────────────┘
         │
         ▼
    ❌ VULNERABLE TO FRAUD
```

**Vulnerabilities:**
1. ✗ Browser request can be hijacked/faked
2. ✗ No real-time confirmation from Paystack
3. ✗ Single point of failure (browser callback)
4. ✗ Webhook URL not configured = backup verification missing
5. ✗ No transaction signature verification
6. ✗ No HMAC validation from Paystack

---

## Target Architecture (SECURE)

```
Secure Payment Flow (Server-to-Server):
┌─────────────┐
│   Paystack  │
└──────┬──────┘
       │ (1) User completes payment
       │
       ├─────────────────────────────────────┐
       │ (2) Paystack redirects to browser   │
       │     with payment reference          │
       │                                     │
       ▼                                     │
┌──────────────────┐                        │
│  User's Browser  │                        │
│                  │                        │
│ Optional callback│◄────────────────────────┘
│ (UX only)        │
└────────┬─────────┘
         │ (3) Browser calls /api/paystack-verify.php
         │ (For immediate UX feedback only)
         │
         ▼
┌──────────────────────────────────────────┐
│  Your Server - Verify Immediately        │
│  ✓ Check order status                    │
│  ✓ Verify with Paystack API              │
│  ✓ Double-check amounts match            │
│  ✓ Mark order as "pending_webhook"       │
│  ✓ (Don't process deliveries yet)        │
└──────────────────────────────────────────┘

ALSO HAPPENING IN BACKGROUND:
┌────────────────────────────────────────────────────┐
│ (4) Paystack Server → Your Server (Webhook)        │
│     Direct server-to-server notification           │
│     ✓ HMAC signature verification                  │
│     ✓ Transaction details verified                 │
│     ✓ Amount validation                            │
│     ✓ Reference validation                         │
│     ✓ NOT affected by browser interception         │
└────────────────────────────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────┐
│  Your Server - Webhook Handler           │
│  (webhook-handler.php)                   │
│                                          │
│  ✓ Verify HMAC signature                 │
│  ✓ Validate transaction details          │
│  ✓ Mark order as "paid"                  │
│  ✓ Process deliveries                    │
│  ✓ Send confirmation emails              │
│  ✓ Process affiliates                    │
│  ✓ Log all events                        │
└──────────────────────────────────────────┘
         │
         ▼
    ✅ FULLY SECURE - UNHACKABLE
```

**Why This Is Secure:**
1. ✓ Webhook is server-to-server (browser cannot intercept)
2. ✓ HMAC signature prevents fake requests
3. ✓ Amount and reference must match exactly
4. ✓ Real-time notification from Paystack source
5. ✓ Double verification (browser + webhook)
6. ✓ Delivery only happens after webhook confirms

---

## Implementation Checklist

### Phase 1: Webhook Handler Implementation
- [ ] **Create `/api/webhook-handler.php`**
  - [ ] Parse incoming webhook payload from Paystack
  - [ ] Extract: reference, status, amount, customer data
  - [ ] Implement HMAC signature verification
  - [ ] Log all webhook events with timestamps
  - [ ] Return HTTP 200 to Paystack immediately
  - [ ] Handle duplicate webhook attempts (idempotency)

- [ ] **HMAC Signature Verification**
  - [ ] Read raw request body (not parsed JSON)
  - [ ] Calculate SHA512 hash using secret key
  - [ ] Compare with `X-Paystack-Signature` header
  - [ ] Reject if signature doesn't match (return 403)
  - [ ] Log failed attempts as security incidents

- [ ] **Transaction Validation**
  - [ ] Verify reference format: `ORDER-{id}-{timestamp}-{random}`
  - [ ] Extract order ID from reference
  - [ ] Query database for pending order
  - [ ] Validate amount matches exactly (in kobo)
  - [ ] Check status is "succeeded" only
  - [ ] Prevent processing if status is "failed" or "cancelled"

- [ ] **Idempotency Check**
  - [ ] Query `webhook_events` table for duplicate reference
  - [ ] If found: return success without reprocessing
  - [ ] If new: record event and continue processing
  - [ ] Prevents duplicate commissions and emails

- [ ] **Safe Payment Processing**
  - [ ] Begin transaction
  - [ ] Lock order row (SELECT FOR UPDATE)
  - [ ] Verify order still pending
  - [ ] Mark order as "paid"
  - [ ] Create delivery records
  - [ ] Process affiliate commission
  - [ ] Commit transaction atomically

### Phase 2: Database Changes
- [ ] **Create `webhook_events` table**
  ```sql
  CREATE TABLE webhook_events (
      id INTEGER PRIMARY KEY,
      reference TEXT UNIQUE NOT NULL,
      event_type TEXT,
      payload JSON,
      hmac_verified BOOLEAN,
      processed BOOLEAN DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
  );
  ```

- [ ] **Add columns to `pending_orders` table**
  - [ ] `webhook_verified_at` - timestamp when webhook confirmed
  - [ ] `webhook_status` - "pending_webhook" | "webhook_confirmed" | "failed"
  - [ ] `final_verification_method` - "browser_callback" | "webhook" | "both"

### Phase 3: Client-Side Callback Modification
- [ ] **Update `/api/paystack-verify.php`**
  - [ ] Mark order as "pending_webhook" (not "paid" directly)
  - [ ] Show UI message: "Payment received, waiting for final confirmation..."
  - [ ] Set short timeout (2-3 seconds) before redirecting
  - [ ] Return to `/cart-checkout.php?confirmed={order_id}` 
  - [ ] Display order status: "Payment pending verification"
  - [ ] Optionally show "Download will be available shortly" message

- [ ] **Conditional Processing**
  - [ ] If status is "pending_webhook": show "Processing..." message
  - [ ] If status is "paid": show download links immediately
  - [ ] If status is "failed": show error and retry button
  - [ ] Refresh page every 2 seconds until webhook processes

- [ ] **Remove Premature Delivery Triggering**
  - [ ] Don't call `createDeliveryRecords()` in browser callback
  - [ ] Don't call `sendAllToolDeliveryEmailsForOrder()` in browser callback
  - [ ] Only do these in webhook handler (trusted source)

### Phase 4: Paystack Dashboard Configuration
- [ ] **Set Webhook URL**
  - [ ] URL: `https://your-new-domain.replit.dev/api/webhook-handler.php`
  - [ ] Method: POST
  - [ ] Test webhook delivery after saving

- [ ] **Verify API Keys**
  - [ ] Public Key: in `includes/config.php` (public, safe)
  - [ ] Secret Key: in `includes/config.php` (private, keep secret)
  - [ ] Webhook Secret: used for HMAC verification

- [ ] **Enable Webhooks Events**
  - [ ] charge.success
  - [ ] charge.failed
  - [ ] transfer.success
  - [ ] transfer.failed

### Phase 5: Security Hardening
- [ ] **HTTPS Enforcement**
  - [ ] All payment endpoints must be HTTPS only
  - [ ] Verify Paystack webhook comes from Paystack IP ranges
  - [ ] Reject HTTP requests to payment endpoints

- [ ] **Rate Limiting**
  - [ ] Webhook handler: 100 requests per minute per IP
  - [ ] Prevent DDoS attacks on webhook endpoint
  - [ ] Return 429 if limit exceeded

- [ ] **Logging & Monitoring**
  - [ ] Log all webhook attempts (success & failed)
  - [ ] Log all HMAC verification failures
  - [ ] Log all amount mismatches
  - [ ] Create alert for suspicious activity

- [ ] **Error Handling**
  - [ ] Never expose error details in webhook response
  - [ ] Always return HTTP 200 to Paystack
  - [ ] Log detailed errors internally only
  - [ ] Send alert email if webhook processing fails

### Phase 6: Testing & Validation
- [ ] **Paystack Test Credentials**
  - [ ] Use test API keys from Paystack dashboard
  - [ ] Test with test card: 4111 1111 1111 1111
  - [ ] Verify webhook fires on test transactions

- [ ] **Test Scenarios**
  - [ ] [ ] Successful payment → webhook processes → order marked paid
  - [ ] [ ] Browser callback arrives before webhook → status remains pending
  - [ ] [ ] Webhook arrives first → order marked paid → callback sees paid status
  - [ ] [ ] Duplicate webhook → idempotency prevents double processing
  - [ ] [ ] Invalid HMAC signature → webhook rejected (403)
  - [ ] [ ] Amount mismatch → webhook rejected
  - [ ] [ ] Fake webhook from hacker → HMAC fails, rejected

- [ ] **Delivery Verification**
  - [ ] [ ] Tools only available after webhook confirms
  - [ ] [ ] Download links have valid tokens
  - [ ] [ ] Affiliate commissions credited only after webhook
  - [ ] [ ] Customer emails sent only after webhook

### Phase 7: Documentation
- [ ] **Update replit.md**
  - [ ] Document webhook implementation
  - [ ] Document idempotency strategy
  - [ ] Document HMAC verification process
  - [ ] Document payment flow diagram

- [ ] **Create admin guide**
  - [ ] How to verify payments in database
  - [ ] How to view webhook logs
  - [ ] How to manually retry failed webhooks
  - [ ] How to refund payments

---

## Code Implementation Files (To Be Created/Modified)

### New Files to Create:
1. **`api/webhook-handler.php`** (Core security - 200+ lines)
   - HMAC verification
   - Transaction validation
   - Idempotency checking
   - Atomic payment processing

2. **`api/webhook-utils.php`** (Helper functions)
   - HMAC verification function
   - Reference parsing
   - Amount validation

3. **`database/migrations/add-webhook-tables.php`**
   - Create webhook_events table
   - Add webhook columns to pending_orders

### Files to Modify:
1. **`api/paystack-verify.php`**
   - Change from marking order paid → mark as pending_webhook
   - Remove delivery processing
   - Show "processing" message instead

2. **`includes/delivery.php`**
   - Keep `createDeliveryRecords()` function
   - Keep `sendAllToolDeliveryEmailsForOrder()` function
   - Will be called from webhook handler

3. **`includes/functions.php`**
   - Keep `markOrderPaid()` function
   - Will be called from webhook handler

4. **`cart-checkout.php`**
   - Update confirmation page UI
   - Show "Payment processing..." state
   - Auto-refresh until paid or failed

5. **`includes/config.php`**
   - Add webhook secret key (request from user via secrets)

---

## Security Best Practices Implemented

### 1. HMAC Signature Verification
```
✓ Every webhook request signed with Paystack secret key
✓ SHA512 hash algorithm (industry standard)
✓ Tampering detection: Any change invalidates signature
✓ Header: X-Paystack-Signature contains the hash
```

### 2. Transaction Validation
```
✓ Reference must match format: ORDER-{id}-{timestamp}-{random}
✓ Order must exist in database
✓ Amount in kobo must match exactly
✓ Status must be "succeeded"
✓ Order must be in "pending" state (not already paid)
```

### 3. Idempotency (Duplicate Prevention)
```
✓ Record every webhook in webhook_events table
✓ Check for reference duplicates
✓ If duplicate: return success but skip reprocessing
✓ Prevents double payments, double commissions, double emails
```

### 4. Atomic Transactions
```
✓ Begin transaction before processing
✓ Lock order row to prevent race conditions
✓ All changes succeed or all fail together
✓ Commit only after all processing complete
```

### 5. Immediate Response to Paystack
```
✓ Return HTTP 200 immediately to Paystack
✓ Don't make Paystack wait for email sending
✓ Prevents webhook timeout and retries
✓ All processing happens after response sent
```

---

## Security Checklist - Vulnerabilities Fixed

| Vulnerability | Current Risk | After Upgrade | How It's Fixed |
|---|---|---|---|
| Browser callback can be faked | 🔴 Critical | 🟢 Eliminated | Webhook verification required |
| Single point of failure | 🔴 Critical | 🟢 Fixed | Dual verification (browser + webhook) |
| No signature verification | 🔴 Critical | 🟢 Implemented | HMAC-SHA512 on all webhooks |
| Duplicate payments possible | 🟡 High | 🟢 Prevented | Idempotency checking |
| Amount mismatch not detected | 🟡 High | 🟢 Fixed | Strict amount validation |
| No transaction logging | 🟡 High | 🟢 Implemented | All events logged with timestamps |
| Race conditions possible | 🟡 High | 🟢 Fixed | Row-level locking in transactions |
| Tools delivered before paid | 🔴 Critical | 🟢 Fixed | Only after webhook confirms |

---

## Timeline & Effort Estimate

| Phase | Effort | Time | Priority |
|-------|--------|------|----------|
| Phase 1: Webhook Handler | 4-5 hours | 1 day | 🔴 Critical - Do First |
| Phase 2: Database Changes | 1-2 hours | 2-3 hours | 🔴 Critical |
| Phase 3: Client Callback Update | 2-3 hours | 4-5 hours | 🟡 Important |
| Phase 4: Paystack Config | 30 mins | 30 mins | 🔴 Critical |
| Phase 5: Security Hardening | 2-3 hours | 4-5 hours | 🟡 Important |
| Phase 6: Testing | 3-4 hours | 1 day | 🔴 Critical |
| Phase 7: Documentation | 1-2 hours | 2-3 hours | 🟢 Nice-to-have |

**Total Estimated Time:** 14-20 hours

**Recommended Approach:**
1. Start with Phase 1 & 2 (webhook handler + database) - MUST do first
2. Then Phase 4 (Paystack config) - Test with Paystack
3. Then Phase 3 (client updates) - Update UI
4. Then Phase 5 & 6 (security + testing) - Full testing before live
5. Phase 7 (documentation) - Update docs

---

## Next Steps

1. **Request Webhook Secret Key**
   - Get from Paystack dashboard
   - Store in `includes/config.php` as `PAYSTACK_SECRET_KEY`
   - Used for HMAC verification

2. **Approve Security Implementation**
   - Confirm you want full server-to-server security
   - Confirm timeline is acceptable

3. **Start Implementation**
   - Create webhook handler
   - Update database
   - Test with Paystack sandbox
   - Deploy to production

---

## Questions?

- What's your webhook secret key? (We need this for HMAC verification)
- Should we implement IP whitelisting for Paystack IPs?
- Do you want email alerts for failed webhook attempts?
- Should we add payment reconciliation report for admins?

---

**Status:** Ready for implementation approval
**Security Level After Upgrade:** Enterprise-Grade ✅
**Risk of Fraud After:** Virtually Zero ✅
