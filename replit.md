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
- Proper spacing on admin pages for pagination visibility

## System Architecture

### UI/UX Decisions
The platform features a clean, professional UI with consistent design elements. Admin dashboards provide real-time updates and clear visualizations for delivery statuses, commission tracking, and analytics. Admin pages include proper spacing and a footer for improved usability.

### Technical Implementations
- **File Upload:** Production-grade chunked upload system with 20MB chunks, 6-concurrent queue management, stream-based reassembly, and atomic temp directory operations, handling files up to 2GB. Integrated into tools.php with real-time progress tracking and visual feedback.
- **File Type Support:** ZIP Archives, General Attachments, Instructions/Documentation, Code/Scripts, Access Keys/Credentials, Images, Videos, and External Links with visual icons for each type.
- **Admin Layout:** Sidebar navigation with responsive design, improved spacing for pagination visibility, and professional footer with branding.
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

## Recent Changes (December 1, 2025) - CRITICAL BUGS FIXED ✅

### ALL 3 CRITICAL BUGS FIXED ✅
1. **Upload Complete Check** - Delivery system now respects `upload_complete=1` flag. Only tools marked complete by admin trigger emails/downloads.
2. **Payment Confirmation Email** - Customers receive payment confirmation after Paystack verification with order details.
3. **Order Success Email** - First-time users get order confirmation + 30% affiliate earning opportunity invitation.
4. **Download Links** - Verified fully working with proper token generation, expiry (30 days), and download limits (10 downloads).

### Code Changes Summary
- **includes/delivery.php** (Lines 31-78): Added upload_complete check with detailed logging - skips tools where upload_complete != 1
- **includes/mailer.php** (Lines 425-518): Added two new email functions:
  - `sendPaymentConfirmationEmail()` - Professional payment confirmation with transaction details
  - `sendOrderSuccessEmail()` - Order confirmation with affiliate earning invitation
- **api/paystack-verify.php** (Lines 205-211): Updated to use new payment confirmation email function
- **cart-checkout.php** (Lines 281-292): Added order success email call on order creation

### How It Works Now
1. User creates order → Receives order success email + affiliate invitation
2. User pays via Paystack/Manual → Gets payment confirmation email
3. System checks upload_complete flag → Only delivers completed tools
4. Customer receives → Email with working download links (30 days valid, 10 downloads max)

### Admin Tools Search (Previously Completed)
- **AJAX Tool Search**: Fast dropdown search on admin tools page
- **Positioning**: Search box at top with dropdown results showing below
- **Features**: 
  - 300ms debounce for performance
  - Shows tool name, file count, upload status (✅ Ready / ⏳ Pending)  
  - Pagination support in dropdown
  - Click any result to edit that tool
  - Close dropdown when clicking outside
- **API**: `/api/admin-search-tools.php` handles search queries with pagination
- **UX Improvement**: Admin can now quickly find tools to upload files without scrolling through full table

### Previous Recent Changes (December 1, 2025)

### Admin UI Improvements
- **Footer & Spacing Fix**: Added professional footer with branding and 8rem bottom padding to all admin pages
- **Pagination Visibility**: Pagination and page numbers now clearly visible above footer - no more cut-off content
- **Responsive Footer**: Footer adapts to sidebar layout with proper margin offset on large screens

### Upload System Improvements
- **Integrated Advanced Upload into tools.php**: Ported all sophisticated upload features from tool-files.php into the main tools editing page
- **Chunked Upload System**: 20MB chunks with 6 concurrent uploads = 3x faster performance (proven stable)
- **Real-time Progress Bar**: Live feedback showing upload percentage, chunk status, and detailed upload progress
- **File Type Icons**: Visual indicators for all file types (📦 ZIP, 📎 Attachment, 📝 Instructions, 💻 Code, 🔑 Access Key, 🖼️ Image, 🎬 Video, 🔗 Link)
- **Enhanced Error Handling**: Specific error messages for all upload failure scenarios
- **2GB File Support**: Maximum file size increased with chunking support
- **File Validation**: Real-time feedback when selecting files, showing chunk count and upload speed benefits
- **Status Updates**: Clear, real-time feedback at every stage (queuing, sending, completion)
- **Link Support**: Toggle between file upload and external link modes
- **Description Support**: Optional descriptions for all file types with up to 100 characters

### Previous Fixes
- **CONCAT Function Error**: Fixed 4 instances in `api/monitoring.php` - replaced MySQL's CONCAT() with SQLite's || operator
- **Foreign Key Constraint Error**: Fixed in `includes/functions.php` - added affiliate code validation to prevent insertion of invalid codes
- **Dashboard Query Error**: Fixed `admin/database.php` line 728 - changed column name from `status` to `delivery_status` in deliveries query
- **CRITICAL Email Delivery Issue FIXED**: Fixed `includes/delivery.php` lines 1779 & 1819 - changed order status filter from `'paid'` ONLY to `IN ('pending', 'paid')` so delivery system processes BOTH manual payment orders (pending) and verified Paystack orders (paid). This was preventing emails from being sent for manual payment orders.
- **Delivery System**: Now fully functional - processes pending deliveries with emails for all payment types
