# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a PHP/SQLite marketplace platform designed for selling website templates, premium domains, and digital tools. It supports a dual payment system (manual bank transfer and Paystack), incorporates a 30% commission affiliate marketing program, and ensures secure encrypted delivery of template credentials. The platform offers comprehensive admin management, prioritizing high reliability, data integrity, and seamless operations for both customers and administrators, with a strong focus on system monitoring and security to achieve significant market potential.

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
The platform features a clean, professional UI with consistent design elements. Admin dashboards provide real-time updates and clear visualizations for delivery statuses, commission tracking, and analytics. Admin pages include proper spacing and a professional footer for improved usability and branding.

### Technical Implementations
- **File Upload System**: Production-grade chunked upload system supporting files up to 2GB, with 2MB sequential chunks for reliability, automatic retry logic (3 attempts per chunk with 60s timeout), manifest-based tracking, and atomic temporary directory operations. It includes real-time progress tracking and visual feedback.
- **File Type Support**: Comprehensive support for various file types including ZIP Archives, General Attachments, Instructions, Code, Access Keys, Images, Videos, and External Links, each with visual icons.
- **Admin Layout**: Features a responsive sidebar navigation and improved spacing for pagination visibility.
- **Template Delivery**: Utilizes AES-256-GCM encryption for credentials, dynamic assignment, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery**: Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection, including enhanced email notifications. Handles mixed orders with partial deliveries for immediate (tools) and pending (templates) items.
- **Affiliate System**: Implements a 30% commission rate with a unified, idempotent commission processor using the `sales` table as the single source of truth.
- **Security**: Includes CSRF token validation, secure token generation, file existence validation, download limit enforcement, enterprise-grade webhook security (IP whitelisting, rate limiting, HMAC verification), and a payment reconciliation system.
- **Payment Processing Idempotency**: Both paystack-verify.php (frontend callback) and paystack-webhook.php (server callback) check affected rows after UPDATE to prevent race conditions. Only the handler that successfully marks the order as 'paid' sends emails, preventing duplicates when both handlers run simultaneously.
- **Order Completion Locking**: Tools marked as "Files Ready for Delivery" (upload_complete) are locked - no file uploads or deletions allowed. The upload section is hidden (not just locked) when tools are marked complete. To make changes, admin must first unmark the tool, make changes, then re-mark it to notify customers of updates. File deletion only allowed when tool is unmarked.
- **Email System**: Enhanced email notifications for various events (payment confirmations, affiliate commissions, delayed tool deliveries, updates). Features professional HTML templates with modern design, preheader text for inbox previews, and proper meta tags for email client compatibility. Includes a priority-based email queue system for bulk sending, aggressive processing mode for high volumes, and automatic schema migration. Support email configured as admin@webdaddy.online.
- **Delivery Update Emails**: Universal `sendOrderDeliveryUpdateEmail()` function works for all order types (tools-only, templates-only, mixed). Shows delivery progress with visual progress bar, lists delivered vs pending items, and sends completion notification when all items are delivered. Includes idempotency protection to prevent duplicate emails when state hasn't changed.

### Feature Specifications
- **Order Management**: Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management**: Comprehensive admin dashboards for tracking template and tool deliveries, including retry mechanisms and email notifications.
- **Analytics & Reporting**: Admin analytics dashboard with delivery KPIs, overdue alerts, and CSV export functionality for orders, deliveries, and affiliates. Also includes a real-time system logs dashboard.
- **Commission Management**: Automated calculation and crediting, reconciliation tools, and an audit trail for all commission transactions.

### System Design Choices
- **Database**: SQLite, with a schema designed for robust tracking of orders, deliveries, downloads, and commissions, supporting bundle handling and delivery retries.
- **Configuration**: Key parameters like `DOWNLOAD_LINK_EXPIRY_DAYS`, `MAX_DOWNLOAD_ATTEMPTS`, `DELIVERY_RETRY_MAX_ATTEMPTS`, and `AFFILIATE_COMMISSION_RATE` are defined as constants.
- **Data Integrity**: Safeguarding functions maintain consistency between cached affiliate data and the `sales` table.
- **Timezone**: All datetime queries consistently use a `+1 hour` offset for Nigeria time.

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Email Service**: Utilized for sending various system notifications.