# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a PHP/SQLite marketplace platform for selling website templates, premium domains, and digital tools. It features a dual payment system (manual bank transfer and Paystack), a 30% commission affiliate marketing program, secure encrypted template credential delivery, and comprehensive admin management. The platform emphasizes high reliability, data integrity, and seamless operations for customers and administrators, with a focus on comprehensive system monitoring and security.

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
- **File Upload:** Production-grade chunked upload system with 20MB chunks, 3-concurrent queue management, stream-based reassembly, and atomic temp directory operations, handling files up to 2GB.
- **Template Delivery:** Implements AES-256-GCM encryption for credentials, dynamic assignment, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery:** Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection, including enhanced email notifications with file details.
- **Mixed Orders:** Handles partial deliveries for orders containing both immediate (tools) and pending (templates) items, with clear UI separation and automated email sequences.
- **Affiliate System:** Features a 30% commission rate, with a unified commission processor for both Paystack and manual payments, ensuring idempotency and using the `sales` table as the single source of truth.
- **Security:** Implements CSRF token validation on all admin actions, secure token generation, file existence validation, download limit enforcement, enterprise-grade webhook security (IP whitelisting, rate limiting, HMAC verification, security event logging, throttled email alerts), and a payment reconciliation system.
- **Email System:** Automated email notifications for delayed tool deliveries, individual emails for multiple tools, and spam folder warnings for customers.

### Feature Specifications
- **Order Management:** Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management:** Comprehensive admin dashboards for both template and tool deliveries, with tracking, retry mechanisms (exponential backoff), and email notifications.
- **Analytics & Reporting:** Admin analytics dashboard with delivery KPIs, overdue alerts, CSV export functionality for orders, deliveries, and affiliates, and a real-time system logs dashboard.
- **Commission Management:** Automated commission calculation and crediting, reconciliation tools, and an audit trail for all commission transactions.

### System Design Choices
- **Database:** SQLite with a schema designed for robust tracking of orders, deliveries, downloads, and commissions, including specific fields for bundle handling, delivery retries, and commission logging.
- **Configuration:** Key parameters like `DOWNLOAD_LINK_EXPIRY_DAYS`, `MAX_DOWNLOAD_ATTEMPTS`, `DELIVERY_RETRY_MAX_ATTEMPTS`, and `AFFILIATE_COMMISSION_RATE` are defined as constants within `includes/config.php`.
- **Data Integrity:** Employs safeguarding functions to maintain consistency between cached affiliate data and the `sales` table, which serves as the immutable source of truth.
- **Timezone:** All datetime queries consistently use a `+1 hour` offset for Nigeria time.

## External Dependencies
- **Paystack:** Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension:** Required for generating tool bundles.
- **Email Service:** Utilized for sending various notifications (delivery, overdue alerts, order summaries).