# Payment Flow Update Implementation Plan

**Project:** WebDaddy Empire - Bank Transfer Payment Flow  
**Date:** November 24, 2025  
**Status:** In Progress

---

## Overview

This document tracks the implementation of a new payment flow where customers:
1. View bank account details on the confirmation page (NOT customer details)
2. Manually transfer the exact amount to the business account
3. Send proof of payment via WhatsApp with two options:
   - "I have sent the money" (instant confirmation pathway)
   - "Discuss more on WhatsApp" (discussion pathway)

---

## Phase 1: Admin Panel Bank Settings Addition

**Objective:** Add three new bank account fields to admin settings that can be configured by the admin.

### Tasks:

- [ ] **1.1** Add three new setting keys to `admin/settings.php`:
  - `site_account_number` - The bank account number to display to customers
  - `site_bank_name` - The name of the bank (e.g., "Access Bank", "GTBank")
  - `site_bank_number` - The bank code/number for identification

- [ ] **1.2** Update the settings form in `admin/settings.php`:
  - Add three new input fields in the "General Settings" form (around line 106-149)
  - Include proper labels with icons (e.g., 🏦 for bank name)
  - Add helpful descriptions under each field
  - Ensure form sends these values in POST request

- [ ] **1.3** Update the settings display section in `admin/settings.php`:
  - Add three new display cards in the "Current Settings" section (around line 169-189)
  - Show the current values of account number, bank name, and bank number
  - Use the same styling as existing settings cards (gray background, text formatting)

- [ ] **1.4** Update database save logic in `admin/settings.php`:
  - Ensure the three new settings are saved to database using INSERT...ON CONFLICT pattern (like existing settings at line 32-40)
  - All three should be processed in the same settings array loop
  - Add proper sanitization for all three inputs

**Success Criteria:**
- Admin can save all three bank details on settings page
- Values persist in database
- Values display correctly in "Current Settings" preview section

---

## Phase 2: Confirmation Page UI Redesign

**Objective:** Replace customer details card with bank payment details card and update instructions.

### Tasks:

- [ ] **2.1** Replace Customer Details Card (lines 571-590 in `cart-checkout.php`):
  - **REMOVE:** The entire "Customer Details" section that shows name, WhatsApp, email
  - **REPLACE:** With new "Bank Payment Details" card showing:
    - Bank Account Number (with copy button/icon)
    - Bank Name
    - Bank Number/Code
  - Retrieve these values from settings using `getSetting()` function
  - Add styling: blue/bank-themed background, larger font for account number, copy icon

- [ ] **2.2** Update "Next Steps" instruction section (around line 592-598):
  - Change instruction text from "Click button to send order details"
  - TO: "Send the exact amount (₦{amount}) to the account details above, then click the button below with proof"
  - Make instructions clearer about needing exact amount transfer
  - Add note: "Screenshot your payment receipt to send as proof"

- [ ] **2.3** Replace WhatsApp button with two-button system (around line 601-607):
  - **Button 1:** "⚡ I have sent the money"
    - Badge/label: "Instant Confirmation"
    - Links to WhatsApp with "payment proof message"
  - **Button 2:** "💬 Discuss more on WhatsApp"
    - Alternative option
    - Links to WhatsApp with "discussion message"
  - Both buttons styled differently but professional
  - Stack on mobile, side-by-side on desktop

**Success Criteria:**
- Bank details display instead of customer details
- Two WhatsApp buttons visible and clickable
- Instructions are clear about exact amount requirement

---

## Phase 3: WhatsApp Message Templates Update

**Objective:** Update message generation logic to support two different message flows based on button clicked.

### Tasks:

- [ ] **3.1** Update WhatsApp message generation in `cart-checkout.php` (around line 325-372):
  - **Current flow:** Single message asking for payment details from admin
  - **NEW - Message Type 1 (I have sent the money):**
    - Keep order ID, items, total amount
    - Add: "Send the exact amount [₦amount] to account details provided on website"
    - Add customer's bank details (account number, bank name, bank code)
    - End message: "Attached is the screenshot of my payment receipt"
    - Include instruction: "Please screenshot your payment transaction and send as proof"
  - **NEW - Message Type 2 (Discuss more):**
    - Keep order ID, items, total amount
    - Message: "I need more information about this order" or similar
    - Option to ask questions before paying
    - Different closing

- [ ] **3.2** Implement two separate WhatsApp URLs in PHP:
  - `$whatsappUrlPaymentProof` - For "I have sent the money" button
  - `$whatsappUrlDiscussion` - For "Discuss more" button
  - Both use proper message formatting (bold, emojis, line breaks)
  - Both properly URL-encoded

- [ ] **3.3** Update confirmation data array (around line 379-386):
  - Pass both WhatsApp URLs to the template: `$confirmationData['whatsappUrlPaymentProof']` and `$confirmationData['whatsappUrlDiscussion']`
  - Ensure bank settings are available in confirmation data for display

- [ ] **3.4** Message format specifications:
  - **Message Type 1 (Payment Proof):**
    ```
    🛒 *NEW ORDER REQUEST*
    ━━━━━━━━━━━━━━━━━━━━
    
    📋 *Order ID:* #[order_id]
    
    🎨 *TEMPLATES* ([count]):
    ✅ [product names]
    
    💳 *Amount to Pay:* [amount_formatted]
    
    🏦 *Payment Details:*
    Bank: [bank_name]
    Account: [account_number]
    Code: [bank_code]
    
    📸 Attached is the screenshot of my payment receipt
    ```
  
  - **Message Type 2 (Discussion):**
    ```
    🛒 *ORDER INQUIRY*
    ━━━━━━━━━━━━━━━━━━━━
    
    📋 *Order ID:* #[order_id]
    
    I need more information about this order before proceeding with payment. Let me discuss the details with you.
    ```

**Success Criteria:**
- Two different WhatsApp links generate with different messages
- Buttons link to correct WhatsApp messages
- Messages display order info, payment details, and appropriate calls-to-action
- URL encoding works properly (no broken links)

---

## Files to Modify

1. **`admin/settings.php`** - Add bank settings fields and save logic
2. **`cart-checkout.php`** - Update confirmation page UI and WhatsApp message generation

---

## Testing Checklist

- [ ] Admin can add/edit bank account details in settings
- [ ] Bank details save correctly to database
- [ ] Confirmation page displays bank details (not customer details)
- [ ] Both WhatsApp buttons appear and are clickable
- [ ] "I have sent the money" button generates payment proof message
- [ ] "Discuss more on WhatsApp" button generates discussion message
- [ ] WhatsApp messages format correctly with order details
- [ ] Mobile layout looks good (buttons stack properly)
- [ ] Customer can copy account number easily

---

## Notes & Rationale

**Why this approach:**
- Removes manual back-and-forth delays for payment confirmation
- Shows bank details upfront - clear call-to-action
- Two message options accommodate different customer needs
- Payment proof in WhatsApp provides instant order confirmation capability

**Message localization:** Currently in English - adjust emojis/text for your market as needed

---

## Phase Completion Tracking

| Phase | Description | Status | Completed At |
|-------|-------------|--------|--------------|
| Phase 1 | Admin bank settings addition | ⏳ Pending | - |
| Phase 2 | Confirmation page UI redesign | ⏳ Pending | - |
| Phase 3 | WhatsApp message templates | ⏳ Pending | - |
| Overall | Full implementation complete | ⏳ Pending | - |

