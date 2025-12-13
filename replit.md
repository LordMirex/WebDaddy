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

## Planned Updates

### Customer Account System (system_update/ folder)
Comprehensive documentation for adding customer accounts to the platform. Documentation created in `system_update/` folder includes:
- **Database Schema**: 7 new tables (customers, customer_sessions, customer_otp_codes, etc.)
- **Customer Authentication**: Email-first login with OTP (Termii SMS) or password
- **User Dashboard**: New `/user/` folder with order tracking, downloads, support tickets
- **Checkout Flow**: Frictionless auth integration maintaining conversion optimization
- **Termii Integration**: SMS OTP for Nigerian market (used by Paystack)
- **Implementation Guide**: 10-phase step-by-step deployment plan

See `system_update/00_OVERVIEW.md` for executive summary.

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Email Service**: Utilized for sending various system notifications.
- **Termii (Planned)**: SMS OTP service for customer verification.