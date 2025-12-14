# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a PHP/SQLite marketplace platform for selling website templates, premium domains, and digital tools. It features a dual payment system (manual bank transfer and Paystack), a 30% commission affiliate program, and secure encrypted delivery of digital assets. The platform prioritizes high reliability, data integrity, and seamless operations for users and administrators, focusing on system monitoring and security to achieve significant market potential.

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
The platform features a clean, professional UI with consistent design elements. Admin dashboards provide real-time updates and clear visualizations. Admin pages include proper spacing and a professional footer. Recent updates include a vibrant luxury gold color palette and a navy/gold redesign for the index page, including a portfolio image slider in the hero section and enhanced mobile responsiveness.

**Premium Page Loader**: Enhanced loader featuring:
- Centered logo with glowing pulse animation
- W-shaped gold stripes with connected bottom points (crown design)
- Glowing/blinking stripes with dynamic synchronized animations
- 3-blink effect before evaporation for polished exit
- Fast exit animation with zoom decay and golden dust effects
- Optimized for quick display (2s display + 0.9s blinks + 0.5s exit = ~3.4s total)
- Aggressive image preloading during loader display for seamless hero reveal

### Technical Implementations
- **File Upload System**: Production-grade chunked upload system supporting files up to 2GB with automatic retry logic, manifest-based tracking, and duplicate file prevention.
- **File Type Support**: Comprehensive support for various file types including ZIP Archives, General Attachments, Instructions, Code, Access Keys, Images, Videos, and External Links.
- **Template Delivery**: Uses AES-256-GCM encryption for credentials, dynamic assignment, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery**: Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection. Handles mixed orders with partial deliveries.
- **Affiliate System**: Implements a 30% commission rate with a unified, idempotent commission processor.
- **Security**: Includes CSRF token validation, secure token generation, file existence validation, download limit enforcement, and enterprise-grade webhook security (IP whitelisting, rate limiting, HMAC verification).
- **Payment Processing Idempotency**: Prevents race conditions during payment verification by checking affected rows and ensuring only one handler sends order completion emails.
- **Order Completion Locking**: Tools marked as "Files Ready for Delivery" are locked, preventing file modifications unless explicitly unmarked by an admin.
- **Version Control Email System**: Comprehensive update notifications for existing customers, detailing new, updated, existing, and removed files using the `tool_file_deletion_log` table. Emails are personalized and sent via a queue system.
- **Email System**: Enhanced, professional HTML email notifications for various events with a priority-based queue system for bulk sending and automatic schema migration. Features centralized delivery status updates upon successful email sending.
- **Bonus Code System**: Admin-managed promotional discount codes with CRUD operations, usage tracking, auto-expiration, and a priority system over affiliate codes. Only one bonus code can be active at a time.
- **Payment Verification Recovery System**: Frontend API to check payment status and a robust `PaymentManager` with session storage backup, automatic retry logic, and a recovery modal.
- **Affiliate Withdrawal Balance Fix**: Correctly subtracts all in-progress withdrawals from the available balance to prevent duplicate requests.
- **Admin Orders Bulk Actions Fix**: Corrected JavaScript deduplication for unique order ID submission in bulk actions and added `cancellation_reason` to `pending_orders`.
- **Priority Featured Products Enhancement**: Expanded to "Top 10" with validation, UI feedback for taken slots, and visual indicators in admin listings.

### Feature Specifications
- **Order Management**: Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management**: Comprehensive admin dashboards for tracking template and tool deliveries, including retry mechanisms.
- **Analytics & Reporting**: Admin analytics dashboard with delivery KPIs, overdue alerts, CSV export, and real-time system logs.
- **Commission Management**: Automated calculation, reconciliation tools, and audit trails for commission transactions.

### System Design Choices
- **Database**: SQLite, with a schema designed for robust tracking of orders, deliveries, downloads, and commissions.
- **Configuration**: Key parameters like download link expiry, download attempts, delivery retries, and affiliate commission rate are defined as constants.
- **Data Integrity**: Safeguarding functions maintain consistency between cached affiliate data and the `sales` table.
- **Timezone**: All datetime queries consistently use a `+1 hour` offset for Nigeria time.

## Customer Account System Progress

### Completed (Updates 01-06)
- **Database Schema (01)**: All 7 customer tables created (customers, customer_sessions, customer_otp_codes, customer_password_resets, customer_activity_log, customer_support_tickets, customer_ticket_replies) + customer_notifications table
- **Existing Tables Modified**: customer_id added to pending_orders, sales, deliveries, download_tokens, cart_items
- **Customer Authentication (02)**: Core auth files created:
  - `includes/customer_auth.php` - Login, registration, password recovery
  - `includes/customer_session.php` - Long-lasting session tokens
  - `includes/customer_otp.php` - OTP generation and verification
- **Checkout Flow API (03)**: Customer API endpoints created:
  - `api/customer/check-email.php` - Check if customer exists
  - `api/customer/request-otp.php` - Send OTP for email verification
  - `api/customer/verify-otp.php` - Verify OTP and create session
  - `api/customer/login.php` - Password login
  - `api/customer/notifications.php` - Customer notifications
- **User Dashboard (04)**: Full `/user/` customer portal with 15 pages:
  - Dashboard home, orders list, order detail, downloads
  - Support tickets list, ticket detail, new ticket
  - Profile settings, security settings
  - Login, logout, forgot-password, reset-password
  - Includes: auth middleware, header, footer
- **Delivery System Updates (05)**: Customer dashboard delivery functions:
  - `getCustomerDeliveries()` - Get all deliveries for a customer
  - `getDeliveryForCustomer()` - Single delivery with security check
  - `getTemplateCredentialsForCustomer()` - Decrypted credentials for dashboard
  - `processCustomerDownload()` - Handle downloads from dashboard
  - `regenerateDownloadToken()` - Regenerate expired tokens
  - `getOrderTimeline()` - Build delivery timeline
  - `createToolDownloadTokens()` - Link tokens to customers
  - `getCustomerDownloadTokens()` - Get tokens for order
  - `getDeliveryWithCustomerStats()` - Admin view with customer stats
  - `linkDownloadTokensToCustomer()` - Link tokens after account creation
  - Updated `createDeliveryRecords()` with customer_id linking
  - Updated `sendToolDeliveryEmail()` with dashboard access link
- **Admin Panel Updates (06)**: New pages and API endpoints for customer management

### Completed (Updates 07-08)
- **API Endpoints (07)**: 9 new customer API endpoints created in `/api/customer/`:
  - `logout.php` - Customer logout with optional all-device revocation
  - `profile.php` - GET/POST for customer profile management
  - `orders.php` - Paginated order list with status filtering
  - `order-detail.php` - Single order with items, deliveries, timeline
  - `downloads.php` - Customer download tokens grouped by tool
  - `regenerate-token.php` - Regenerate expired download tokens (with ownership validation)
  - `tickets.php` - GET/POST for support tickets
  - `ticket-reply.php` - Add replies to support tickets
  - `sessions.php` - GET/DELETE for session management
- **Email Templates (08)**: 8 new email functions added to `includes/mailer.php`:
  - `sendOTPEmail()` - High-priority OTP verification email
  - `sendCustomerWelcomeEmail()` - Welcome email with dashboard link
  - `sendPasswordSetEmail()` - Password set confirmation
  - `sendPasswordResetEmail()` - High-priority password reset link
  - `sendTemplateDeliveryNotification()` - Website-is-live notification (dashboard-first, no credentials in email)
  - `sendTicketConfirmationEmail()` - Support ticket created confirmation
  - `sendTicketReplyNotificationEmail()` - Ticket reply notification
  - `sendNewCustomerTicketNotification()` - Admin notification for new customer tickets

### Completed (Updates 09-16)
- **Frontend Changes (09)**: Full checkout auth flow with email verification, password login, OTP, Alpine.js checkoutAuth() component
- **Security (10)**: Security headers, rate_limits table, includes/rate_limiter.php with API rate limiting
- **File Structure (11)**: All required files verified (user portal 13 pages, api/customer 14 endpoints, customer includes 5 files)
- **Implementation Guide (12)**: Implementation phases 1-8 completed with security measures
- **Termii Integration (13)**: includes/termii.php with full Termii API integration (sendTermiiSMS, sendTermiiOTPSMS, sendTermiiVoiceOTP, getTermiiBalance)
- **Deployment Guide (14)**: health.php endpoint created for system monitoring
- **Operations & Maintenance (15)**: Cron scripts: check_termii_balance.php, cleanup_expired_otp.php, check_delivery_sla.php, monthly_cleanup.php
- **Risks & Dependencies (16)**: Risk register reviewed, mitigations in place (email fallback, rate limiting, backups)

### Completed (Updates 17-20)
- **Bulletproof Delivery System (17)**: 
  - Delivery state machine in `includes/delivery_state.php` (pending→processing→delivered/failed)
  - SLA tracking with escalation levels and deadlines
  - Auto-recovery for failed deliveries
  - Self-service APIs: delivery-status.php, regenerate-download.php, reset-credentials.php
  - Database: 9 new columns in deliveries table, credential_reset_requests and download_token_regenerations tables
- **Self-Service Experience (18)**: Database tables (help_articles, help_article_feedback, order_events)
- **Admin Automation (19)**: Database tables (admin_auto_rules, admin_rule_executions, canned_responses)
- **Security Hardening (20)**: Database tables (security_alerts, login_attempts)

### Pending Updates (21-25)
See `system_update/00_OVERVIEW.md` for full tracking. Remaining phases include:
- Infrastructure Improvements (21) - Caching and background jobs
- Affiliate Enhancements (22) - Affiliate tracking updates
- UI/UX Premium Upgrade (23) - Visual design overhaul
- Floating Cart Widget (24) - Persistent cart feature
- Index Page User Profile (25) - Homepage user integration

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Email Service**: Utilized for sending various system notifications.
- **Termii (Planned)**: SMS OTP service for customer verification.