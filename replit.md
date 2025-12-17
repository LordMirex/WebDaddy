# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a PHP/SQLite marketplace platform for selling website templates, premium domains, and digital tools. It features a dual payment system (manual bank transfer and Paystack), a 30% commission affiliate program, and secure encrypted delivery of digital assets. The platform prioritizes high reliability, data integrity, and seamless operations, focusing on system monitoring and security to achieve significant market potential in the digital products market.

## User Preferences
- No mock data in production paths
- All credentials encrypted before database storage
- Hardcoded configuration (no environment variables)
- Detailed error messages surfaced to admin
- Clean, professional UI consistent with existing design
- Real-time updates where possible
- Systematic testing with automated verification
- Clear profit breakdown on all admin pages
- No duplicate or confusing card displays
- Proper spacing on admin pages for pagination visibility

## System Architecture

### UI/UX Decisions
The platform features a clean, professional UI with consistent design elements, including a vibrant luxury gold and navy color palette. Admin dashboards provide real-time updates and clear visualizations. A premium page loader with a centered logo, glowing animations, and aggressive image preloading enhances the user experience. The index page includes a portfolio image slider and enhanced mobile responsiveness.

### Technical Implementations
The system includes a production-grade chunked file upload system (up to 2GB) with retry logic, manifest tracking, and duplicate prevention. It supports various file types for digital products. Template delivery uses AES-256-GCM encryption with dynamic assignment and an admin delivery dashboard. Tools delivery supports ZIP bundles, configurable download link expiry, and regeneration. An idempotent 30% commission affiliate system is implemented. Security features include CSRF token validation, secure token generation, file existence validation, download limits, and enterprise-grade webhook security. Payment processing ensures idempotency to prevent race conditions. Order completion locks prevent file modifications unless unmarked by an admin. A comprehensive version control email system notifies customers of updates. An enhanced, professional HTML email system with a priority-based queue handles notifications. Admin-managed bonus codes with CRUD operations, usage tracking, and auto-expiration are supported. A payment verification recovery system includes a frontend API, `PaymentManager` with session storage backup, and a recovery modal. The system also includes an affiliate withdrawal balance fix, bulk action corrections for admin orders, and enhanced priority featured products. A bulletproof delivery system incorporates a state machine, SLA tracking, auto-recovery, and self-service APIs. Customer account features include a robust authentication system, a full user dashboard with order management, downloads, and support tickets, and an extensive set of customer-facing API endpoints. Infrastructure improvements include a caching system, a job queue for background processing, a centralized error logger, and an automated backup manager. Affiliate enhancements cover real-time statistics and fraud detection.

### Feature Specifications
- **Order Management**: Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management**: Comprehensive admin dashboards for tracking deliveries, including retry mechanisms.
- **Analytics & Reporting**: Admin dashboard with delivery KPIs, overdue alerts, CSV export, and real-time logs.
- **Commission Management**: Automated calculation, reconciliation tools, and audit trails.

### System Design Choices
The platform utilizes SQLite for its database, with a schema designed for robust tracking of orders, deliveries, downloads, and commissions. Key parameters are defined as constants. Data integrity functions maintain consistency between cached affiliate data and sales records. All datetime queries consistently use a `+1 hour` offset for Nigeria time.

## Recent Changes (December 2024)

### User Referral System (December 17, 2024)
- **Customer Referral Program**: Regular customers can now share referral links and earn 20% commission on sales
  - User referral codes use `ref=` parameter (vs `aff=` for affiliates)
  - Priority: bonus codes > affiliate codes > user referral codes
  - Customers get 20% discount when using either affiliate or referral codes
  - Affiliates get 30% commission, user referrers get 30% commission (both calculated on final paid amount after 20% customer discount)
- **Self-Referral Prevention**: Users cannot use their own referral code to get discounts (spam prevention)
  - Check in `getCartTotal()` compares logged-in customer ID with referral code owner
  - Blocked attempts are logged for monitoring
- **Database Tables**: user_referrals, user_referral_sales, user_referral_withdrawals, user_referral_clicks
- **Admin Management**: New `/admin/user-referral-withdrawals.php` page for managing customer referral withdrawals
- **Financial Analytics**: Reports now separate affiliate commissions from user referral commissions
  - Profit calculation: Revenue - Affiliate Commissions - User Referral Commissions
  - New functions: `getCommissionBreakdown()`, `getUserReferralMetrics()` in finance_metrics.php
- **Idempotent Processing**: Both `processOrderCommission()` and `processUserReferralCommission()` check for existing records before processing
- **Constants Added**: `USER_REFERRAL_COMMISSION_RATE` (0.20), `USER_REFERRAL_DISCOUNT_RATE` (0.20)
- **Referral Page UX**: Tabs now switch instantly using Alpine.js (no page reload)
  - Tab values are whitelisted to prevent XSS injection
- **Files Updated**: includes/config.php, includes/cart.php, cart-checkout.php, includes/functions.php, includes/finance_metrics.php, admin/reports.php, admin/user-referral-withdrawals.php, admin/includes/header.php, user/referral.php

### User Campaign Management System (December 17, 2024)
- **User Announcements**: New system for admins to post announcements visible on user dashboards
  - Database tables: `user_announcements`, `user_announcement_emails`
  - Announcement types: info (blue), success (green), warning (yellow), danger (red)
  - Target options: All users or specific individual user
  - Duration: Permanent or timed (auto-expire after X days)
- **Email Campaign**: Bulk email functionality for all active users or individual users
  - Rich text editor (Quill) for message formatting
  - Email tracking and delivery statistics
- **Welcome Announcement**: Automatic 7-day welcome announcement created on user registration
  - Personalized greeting with username
  - Quick start tips for new users
- **Dashboard Display**: User dashboard shows active announcements with proper styling
- **Admin Page**: `/admin/user-campaign.php` - Full campaign management interface
- **Files Updated**: includes/db.php, includes/mailer.php, admin/user-campaign.php, admin/includes/header.php, user/index.php, includes/customer_auth.php

### Admin Modal Form State Fix (December 17, 2024)
- **Modal State Reset Fixed**: Admin modal forms (templates, domains, tools) now properly reset when closing
- **Video Type Field Fixed**: Changed `video_type_create` to `video_type` in templates create modal so video type is correctly submitted
- **Enhanced Form Reset**: Improved `resetCreateForm()` functions to comprehensively clear all input fields, checkboxes, radio buttons, selects, and hidden fields when modals are closed
- **Dynamic Tool Types**: Tool type dropdown now works like template categories - when you create a custom type using "Others", it gets saved and appears in the dropdown for future tools
- **Files Updated**: admin/templates.php, admin/domains.php, admin/tools.php

### Admin & Payment System Improvements (December 17, 2024)
- **Admin OTP Generation Fixed**: Replaced failing cURL calls with direct function implementation
- **OTP Rate Limiting**: 5 OTPs per hour limit per customer to prevent abuse
- **OTP Email Notifications**: Automatic email sent when admin generates OTP for customer verification
- **Quick OTP Button**: Added OTP generation button directly in customers table for faster access
- **Customer Detail UI**: Fixed overflow issues with responsive design improvements
- **Payment Method Tracking**: Manual orders correctly set `payment_method = 'manual'` in database
- **Failed Order Retry**: Failed orders can now retry with both Paystack and manual bank transfer
- **Order View Links**: All admin order links now use direct modal view (`?view=`) instead of search

### SMS Removal & Email-Only Verification (December 16, 2024)
- **SMS/Termii Completely Removed**: All Termii SMS API integration and related functionality removed
- **Email-Only OTP**: All customer verification now uses email-only OTP via Resend API
- **Registration Flow Simplified**: Now 3 steps instead of 4:
  1. Email + OTP Verification (via Resend API)
  2. Username + Password + WhatsApp Number (mandatory)
  3. Success + Dashboard Guide
- **WhatsApp Number Field**: Phone number inputs replaced with WhatsApp number throughout
- **Email Routing Updated**:
  - **Resend API**: All user-facing emails (OTP, notifications, deliveries) - from no-reply@webdaddy.online
  - **SMTP**: Admin-only internal emails - from admin@webdaddy.online
  - **SUPPORT_EMAIL**: support@webdaddy.online - displayed in footer for users to contact
- **Resend Webhook**: Created at `/api/resend-webhook.php` for email delivery tracking
- **Removed Files**:
  - `cron/check_termii_balance.php` - Termii balance monitoring
  - `api/customer/send-phone-otp.php` - Phone SMS OTP sending
  - `api/customer/verify-phone-otp.php` - Phone SMS OTP verification

### Email Configuration
- **Support Email**: support@webdaddy.online (for user communications)
- **Admin Email**: admin@webdaddy.online (for internal notifications)
- **Resend Webhook URL**: /api/resend-webhook.php

### Checkout & Registration Flow Updates
- **Post-Payment Redirect**: After successful payment, users are redirected to `/user/order-detail.php?id={orderId}` instead of the old confirmation page
- **Account Completion Modal**: 3-step modal on order detail page for new users:
  1. Username + password setup (username auto-generated from email)
  2. WhatsApp number (mandatory)
  3. Complete
- **Manual Payment Section**: Bank transfer details (Opay) with "I Have Paid" button displayed on order detail page for pending orders
- **Auth Flow**: Incomplete accounts redirected to their order detail page (with modal) instead of register page
- **Runtime Schema Migration**: Auto-applies database schema changes (payment_notified columns) on first request

### Design Decisions
- Username auto-generated format: `emailpart_randomnumber`
- WhatsApp number mandatory for order updates and support
- No phone SMS verification - email-only
- Customer accounts only require: Username, Password, WhatsApp number (no full_name field)

### Resend Email Integration
- **OTP Emails via Resend**: All OTP verification and password reset emails now use Resend REST API for faster, more reliable delivery
- **SMTP Fallback**: Automatic fallback to SMTP if Resend fails
- **Webhook Tracking**: Delivery status tracked via webhooks (sent, delivered, opened, bounced)
- **Admin Dashboard**: Email delivery logs at `/admin/email-logs.php` with statistics and event tracking
- **Configuration**: API key in `includes/config.php` (RESEND_API_KEY, RESEND_WEBHOOK_SECRET)
- **Webhook URL**: `/api/resend-webhook.php` - add to Resend dashboard for delivery events

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Email Service**: SMTP for admin emails, Resend for user-facing emails.
- **Resend**: Integrated for fast, reliable email delivery with delivery tracking.
