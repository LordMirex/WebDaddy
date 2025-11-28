# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a production-ready PHP/SQLite marketplace for selling website templates, premium domains, and digital tools. It features a robust dual payment system (manual bank transfer and Paystack), an affiliate marketing program with a 30% commission, secure encrypted template credential delivery, and comprehensive admin management. The platform is designed for high reliability and data integrity, ensuring seamless operations for both customers and administrators.

## Current Status
✅ **ENTERPRISE-GRADE WEBHOOK CALLBACK SECURITY (Nov 28)**
- Implemented comprehensive security infrastructure in `includes/security.php`:
  - IP whitelisting for Paystack IPs (configurable via `WEBHOOK_IP_WHITELIST_ENABLED`)
  - Database-backed rate limiting (60 requests/minute per IP)
  - Structured security event logging
  - Throttled email alerts for suspicious activity (max 10/hour)
- Updated webhook handler with security gate, HMAC verification, and transaction-based processing
- Added real-time Webhook Security Dashboard in admin monitoring page:
  - Today's webhooks count, blocked requests, payment success/fail rates
  - Recent security events with IP tracking and severity indicators
- Created payment reconciliation system:
  - Compares payments, sales, and orders tables for discrepancies
  - Visual reporting with severity badges in Reports page
  - Detailed issue lists for amount mismatches and missing records
- Cron jobs for delivery retries (exponential backoff) and security log cleanup
- All configuration in `includes/config.php` (no environment variables)

✅ **DOWNLOAD URL VS LOCAL FILE FIX (Nov 27)**
- Fixed critical issue where local files were being redirected instead of downloaded
- Changed detection logic from checking `file_type === 'link'` to checking if file_path is an actual URL (starts with http:// or https://)
- Fixed database entry where local text file (id=14) was incorrectly marked as file_type='link'
- File downloads now return HTTP 200 with correct content type
- External link redirects return HTTP 302 to the correct URL
- Both manual payments and Paystack payments correctly trigger automatic tool delivery emails

✅ **CRITICAL DOWNLOAD BUG FIX - TIMEZONE INCONSISTENCY (Nov 27)**
- Fixed timezone offset bug in download.php that was causing valid download tokens to appear expired
- Line 57: Changed `datetime('now')` to `datetime('now', '+1 hour')` for Nigeria time consistency
- Now matches the +1 hour offset used throughout the platform for all datetime checks
- Download buttons now display correctly for tools with files on order confirmation page
- External links now work properly with correct redirect handling
- All download tokens are now properly validated with correct expiry checks

✅ **AUTOMATED EMAIL NOTIFICATION FOR DELAYED TOOL DELIVERIES (Nov 27)**
- When admin uploads files for tools that didn't have files at purchase time, emails are automatically sent to waiting customers
- `processPendingToolDeliveries($toolId)` function finds all pending deliveries without download links
- Generates fresh download tokens for each file, updates delivery status, and sends professional email with download links
- Admin tool-files.php shows success message with count of customers notified
- Integrates seamlessly with existing delivery pipeline including retry scheduling

✅ **PRODUCTION-GRADE CHUNKED UPLOAD SYSTEM (Nov 27)**
- Implemented intelligent chunked upload for files up to 2GB (optimized for 200MB+)
- **20MB chunks** with **3 concurrent uploads** = maximum speed without server strain
- Uses `stream_copy_to_stream()` for memory-efficient file reassembly
- Atomic file operations with temp directory management prevent corruption
- Independent chunk failure/retry (if one chunk fails, only that 20MB retries)
- Admin tool upload UI shows file size and chunk breakdown upfront
- API endpoint: `/api/upload-chunk.php` handles all chunking logic
- Tested with multiple upload scenarios - system ready for production

✅ **MIXED ORDER DELIVERY BUG FIX (Nov 27)**
- Fixed critical bug where template delivery records were not being created for mixed orders (orders containing both tools AND templates)
- Template Credentials & Delivery section now correctly displays for all mixed orders in admin order detail modal
- `createDeliveryRecords` now uses per-item idempotency checking to only create missing delivery records
- `markOrderPaid` relies on the new idempotency logic instead of all-or-nothing check
- Backfilled 11 missing template delivery records for affected paid orders
- All systems operational: template deliveries, tool deliveries, mixed orders verified

✅ **COMPREHENSIVE SYSTEM AUDIT & FIXES COMPLETE (Nov 26)**
- All financial pages display **"YOUR ACTUAL PROFIT"** with clear breakdown
- Dashboard now includes Quick Analytics Access Hub (5-card navigation)
- Analytics page fixed: loads at top instead of jumping to pagination
- Database integrity verified: all orphan records removed, NULL values fixed
- **TIMEZONE FIXED**: All 33 datetime queries now use `+1 hour` offset for Nigeria time
- Affiliate commission popup removed from customer payment confirmation
- Affiliates table now includes email column (synced from users table)
- System cache cleared and logs reset for fresh start
- All systems operational: deliveries, analytics, affiliates, email queue verified

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

## System Architecture

### UI/UX Decisions
The platform features a clean, professional UI with consistent design elements. Admin dashboards provide real-time updates and clear visualizations for delivery statuses, commission tracking, and analytics.

### Technical Implementations
- **File Upload:** Production-grade chunked upload system with 20MB chunks, 3-concurrent queue management, stream-based reassembly, and atomic temp directory operations. Handles files up to 2GB reliably.
- **Template Delivery:** Implements AES-256-GCM encryption for credentials, a dynamic assignment workflow, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery:** Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection. Includes enhanced email notifications with file details.
- **Mixed Orders:** Handles partial deliveries for orders containing both immediate (tools) and pending (templates) items, with clear UI separation and automated email sequences.
- **Affiliate System:** Features a 30% commission rate, with a unified commission processor for both Paystack and manual payments. The system ensures idempotency to prevent duplicate commission credits and uses the `sales` table as the single source of truth.
- **Security:** CSRF token validation on all admin actions, secure token generation, file existence validation, and download limit enforcement.

### Feature Specifications
- **Order Management:** Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management:** Comprehensive admin dashboards for both template and tool deliveries, with tracking, retry mechanisms (exponential backoff), and email notifications.
- **Analytics & Reporting:** Admin analytics dashboard with delivery KPIs, overdue alerts, and CSV export functionality for orders, deliveries, and affiliates.
- **Commission Management:** Automated commission calculation and crediting, reconciliation tools to prevent data discrepancies, and an audit trail for all commission transactions.

### System Design Choices
- **Database:** SQLite with a schema designed for robust tracking of orders, deliveries, downloads, and commissions.
    - `download_tokens`: `is_bundle` flag.
    - `bundle_downloads`: Stores ZIP bundle metadata.
    - `deliveries`: `retry_count`, `next_retry_at` for delivery retries.
    - `commission_log`: Audit trail for commission actions.
    - `commission_withdrawals`: Tracks affiliate withdrawal requests.
- **Configuration:** Key parameters like `DOWNLOAD_LINK_EXPIRY_DAYS`, `MAX_DOWNLOAD_ATTEMPTS`, `DELIVERY_RETRY_MAX_ATTEMPTS`, and `AFFILIATE_COMMISSION_RATE` are defined as constants.
- **Data Integrity:** Employs safeguarding functions like `syncAffiliateCommissions()` and `reconcileAffiliateBalance()` to maintain consistency between cached affiliate data and the `sales` table, which acts as the immutable source of truth.

## External Dependencies
- **Paystack:** Integrated for automatic payment processing.
- **PHP ZipArchive Extension:** Required for generating tool bundles.
- **Email Service:** Utilized for sending various notifications (delivery, overdue alerts, order summaries).