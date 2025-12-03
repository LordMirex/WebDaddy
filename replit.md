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
- **File Upload System**: Production-grade chunked upload system supporting files up to 2GB, with 20MB chunks, 6-concurrent queue management, stream-based reassembly, and atomic temporary directory operations. It includes real-time progress tracking and visual feedback.
- **File Type Support**: Comprehensive support for various file types including ZIP Archives, General Attachments, Instructions, Code, Access Keys, Images, Videos, and External Links, each with visual icons.
- **Admin Layout**: Features a responsive sidebar navigation and improved spacing for pagination visibility.
- **Template Delivery**: Utilizes AES-256-GCM encryption for credentials, dynamic assignment, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery**: Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection, including enhanced email notifications. Handles mixed orders with partial deliveries for immediate (tools) and pending (templates) items.
- **Affiliate System**: Implements a 30% commission rate with a unified, idempotent commission processor using the `sales` table as the single source of truth.
- **Security**: Includes CSRF token validation, secure token generation, file existence validation, download limit enforcement, enterprise-grade webhook security (IP whitelisting, rate limiting, HMAC verification), and a payment reconciliation system.
- **Order Completion Locking**: Files that have been delivered to customers (in paid/completed orders) are protected from accidental deletion. The system checks for existing download tokens before allowing file removal. Admins can use force delete for exceptional cases with proper logging.
- **Email System**: Enhanced email notifications for various events (payment confirmations, affiliate commissions, delayed tool deliveries, updates). Features professional HTML templates with modern design, preheader text for inbox previews, and proper meta tags for email client compatibility. Includes a priority-based email queue system for bulk sending, aggressive processing mode for high volumes, and automatic schema migration. Support email configured as admin@webdaddy.online.

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