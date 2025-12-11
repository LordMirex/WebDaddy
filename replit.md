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
- **File Upload System**: Production-grade chunked upload system supporting files up to 2GB, with 2MB sequential chunks for reliability, automatic retry logic (3 attempts per chunk with 60s timeout), manifest-based tracking, and atomic temporary directory operations. It includes real-time progress tracking, visual feedback, and **duplicate file prevention** - files with the same name cannot be uploaded twice for the same tool, preventing accidental duplicates.
- **File Type Support**: Comprehensive support for various file types including ZIP Archives, General Attachments, Instructions, Code, Access Keys, Images, Videos, and External Links, each with visual icons.
- **Admin Layout**: Features a responsive sidebar navigation and improved spacing for pagination visibility.
- **Template Delivery**: Utilizes AES-256-GCM encryption for credentials, dynamic assignment, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery**: Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection, including enhanced email notifications. Handles mixed orders with partial deliveries for immediate (tools) and pending (templates) items.
- **Affiliate System**: Implements a 30% commission rate with a unified, idempotent commission processor using the `sales` table as the single source of truth.
- **Security**: Includes CSRF token validation, secure token generation, file existence validation, download limit enforcement, enterprise-grade webhook security (IP whitelisting, rate limiting, HMAC verification), and a payment reconciliation system.
- **Payment Processing Idempotency**: Both paystack-verify.php (frontend callback) and paystack-webhook.php (server callback) check affected rows after UPDATE to prevent race conditions. Only the handler that successfully marks the order as 'paid' sends emails, preventing duplicates when both handlers run simultaneously.
- **Order Completion Locking**: Tools marked as "Files Ready for Delivery" (upload_complete) are locked - no file uploads or deletions allowed. The upload section is hidden (not just locked) when tools are marked complete. To make changes, admin must first unmark the tool, make changes, then re-mark it to notify customers of updates. File deletion only allowed when tool is unmarked.
- **Version Control Email System**: Comprehensive version update notifications for existing customers. When tool is re-marked as complete after file changes, existing customers receive detailed emails showing: (1) New files added with download links (green section), (2) Updated/modified files with new version links (orange section), (3) Files they already have (blue section), (4) Files that were removed (red section with strikethrough). Uses the `tool_file_deletion_log` table to track deleted and modified files. Email queue system handles bulk sending (>10 recipients) to prevent system crashes. The `sendToolVersionUpdateEmails()` function automatically processes all delivered orders and sends personalized update emails based on what each customer previously received.
- **Email System**: Enhanced email notifications for various events (payment confirmations, affiliate commissions, delayed tool deliveries, updates). Features professional HTML templates with modern design, preheader text for inbox previews, and proper meta tags for email client compatibility. Includes a priority-based email queue system for bulk sending, aggressive processing mode for high volumes, and automatic schema migration. Support email configured as admin@webdaddy.online. Unified email flow for both manual and automatic payments: confirmation → admin notification → delivery records → tool delivery emails → commission email. **Centralized Delivery Status Updates**: The processEmailQueue() function now handles delivery status transitions (ready → delivered) when tool_delivery emails are successfully sent, ensuring consistent behavior for both immediate and cron-based processing. Deliveries stay at 'ready' until email send is confirmed, preserving resend/retry capabilities for failed emails.
- **Delivery Update Emails**: Universal `sendOrderDeliveryUpdateEmail()` function works for all order types (tools-only, templates-only, mixed). Shows delivery progress with visual progress bar, lists delivered vs pending items, and sends completion notification when all items are delivered. Includes idempotency protection to prevent duplicate emails when state hasn't changed. Old plain-style `sendMixedOrderDeliverySummaryEmail` function deprecated in favor of styled emails.

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

## Recent Changes (December 2025)

### Webhook Failed Payment Handling Fix
- **handleFailedPayment() in paystack-webhook.php**: Complete rewrite to properly record failed payments:
  - Added fallback logic to create payment records if none exists (mirrors handleSuccessfulPayment behavior)
  - Extracts order ID from reference using extractOrderIdFromReference()
  - Creates payment record with status='failed' and Paystack response data
  - Updates pending_orders status to 'failed'
  - Always logs via logPaymentEvent('payment_failed', ...) for monitoring visibility
  - Orphan failed payments (no matching order) are logged with 'payment_failed_orphan' event type
  - Added detailed error_log messages for debugging

### Payment Verification Recovery System
- **New API `/api/check-payment-status.php`**: Allows frontend to check if a payment was already processed by the webhook during network issues.
- **Robust PaymentManager (assets/js/paystack-payment.js)**: Complete rewrite with:
  - Session storage backup for pending payments (survives page reloads)
  - Automatic retry logic (3 attempts with exponential backoff)
  - Recovery modal with "Retry Verification", "Check Payment Status", and "View Order Status" options
  - Proper cleanup of pending payment data on success

### Database Cleanup
- All test data cleared safely while preserving core system data
- Templates: 45 items preserved
- Tools: 59 items preserved (reset to upload_complete=0)
- Domains: 27 items preserved (reset to available status)
- Settings and admin accounts preserved

### Cron System Review
All cron jobs confirmed as necessary and properly implemented:
- `process-pending-deliveries`: Every 20 min - Sends delivery emails for completed tools
- `process-email-queue`: Every 5 min - Sends queued emails
- `process-retries`: Every 15 min - Retries failed deliveries
- `cleanup-security`: Hourly - Cleans old security logs
- `optimize`: Weekly (Sunday 2 AM) - Database optimization
- `weekly-report`: Weekly (Monday 3 AM) - Analytics report

### Affiliate Withdrawal Balance Fix (December 2025)
- **Critical Bug Fix**: Available balance now correctly subtracts all in-progress withdrawals (any status NOT IN 'paid', 'rejected')
- Prevents affiliates from requesting duplicate withdrawals for the same funds
- Future-proof design covers potential new intermediate statuses like 'processing' or 'approved'

### Admin Orders Bulk Actions Fix
- **JavaScript Deduplication**: Fixed double-counting of orders in bulk actions
- Both desktop and mobile views had checkboxes with same name causing duplicates
- Now uses Set-based deduplication to submit only unique order IDs
- Added `cancellation_reason` column to `pending_orders` table for order cancellation

### Priority Featured Products Enhancement
- Expanded from "Top 3" to "Top 10" for both tools and templates
- Validation logic and dropdown options updated to accept positions 1-10
- **Priority Column in Admin Listings**: Both templates and tools admin tables now display the assigned priority slot with visual star badges
- **Duplicate Prevention UI**: Priority dropdowns show which slots are already taken (with product name) and disable selection, preventing accidental duplicate assignments

### Order Cancellation Fix
- Removed invalid update to non-existent `order_items.status` column in `cancelOrder()` function
- Cancellations now properly tracked via `pending_orders.status` column only

### Withdrawal Balance Refresh Fix
- After successful withdrawal request, the affiliate dashboard now properly refreshes all balance components
- Re-fetches total earned, total paid, and in-progress withdrawals to display accurate available balance

### Vibrant Luxury Gold Color Upgrade (December 2025)
- **Gold Palette Overhaul**: Replaced muted brownish-gold (#D4A574) with vibrant luxury gold (#D4AF37)
- **New Gold Color Scale**: 
  - 50: #FDF9ED (lightest cream)
  - 100-300: #FAF0D4 → #EFCF72 (light golds)
  - 400-500: #E8BB45 → #D4AF37 (primary vibrant gold)
  - 600-900: #B8942E → #604B18 (darker golds)
- **Button Gradients**: Updated btn-gold-shine with vibrant gradient (#F5D669 → #D4AF37 → #B8942E)
- **Enhanced Shadows**: Gold button shadows now use rgba(212,175,55,0.35) for better glow effect
- **AJAX Products**: api/ajax-products.php updated with matching vibrant gold theme
- **Cache Cleared**: Old cached content removed to ensure new colors display immediately

### Index.php Navy/Gold Redesign (December 2025)
- **Color Scheme**: Complete redesign with navy (#0f172a) primary background and gold (#D4AF37) accents
- **FAQ Section**: Updated to navy-dark background with gold arrow icons and navy-light card backgrounds
- **Footer**: Gold "Become an Affiliate" button, 5 social media icons (Facebook, Twitter, Instagram, LinkedIn, YouTube), rounded pill-style buttons
- **AJAX Products**: api/ajax-products.php updated with matching gold/navy theme for dynamically loaded products
- **Mobile Enhancement**: Golden X SVG background decoration for hero section on mobile devices
- **Consistent Styling**: All product cards, preview buttons, prices, and pagination now use gold/navy color palette

### Hero Section Upgrade (December 2025)
- **Portfolio Image Slider**: Safari-style browser mockup showcasing 7 portfolio website images (Jasper AI, Viralcuts, Webflow, Intercom, Glide Apps, Notion, Runway) with auto-rotation every 4 seconds
- **Shiny Professional Effects**: Added glow effects around browser mockup with gold gradients, blur effects, and pulse animations for premium appearance
- **100vh Viewport Fit**: Hero section adjusted to fit within viewport height on desktop (h-[calc(100vh-64px)]) and min-height on mobile
- **Mobile Laptop Mockup**: Added Safari-style browser slider on mobile view, positioned after CTA buttons and before stats bar
- **Compact Stats Bar**: Reduced sizing for better 100vh fit - smaller icons, text, and padding
- **Navbar Active States**: Added gold underline for active view (Templates/Tools) on desktop, gold left-border on mobile
- **Category Filter Label**: Changed dropdown from "Filter" to "Category" for clarity
- **Pagination Improvements**: Added Previous button, improved button colors with hover effects (gold glow on active, hover transitions)

### Bonus Code System (December 2025)
- **Admin-Managed Promotional Codes**: New system for creating promotional discount codes (e.g., CHRISTMAS2025 → 40% off)
- **Database**: New `bonus_codes` table with fields: id, code, discount_percent, is_active, expires_at, usage_count, total_sales_generated
- **Admin Interface**: New "Bonus Codes" tab in admin/affiliates.php with full CRUD operations, stats cards, and professional UI
- **Single Active Code**: Only ONE bonus code can be active at a time - activating a new code automatically deactivates others
- **Priority System**: Bonus codes take precedence over affiliate codes (bonus code discount with NO affiliate commission)
- **Dynamic Checkout Banner**: Replaced hardcoded "HUSTLE" banner with dynamic display of active bonus code
- **Tracking**: Usage count and total sales generated are tracked automatically when orders are placed
- **Auto-Expiration**: Expired codes are automatically deactivated
- **Files Modified**: includes/bonus_codes.php (new), includes/cart.php, admin/affiliates.php, cart-checkout.php

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Email Service**: Utilized for sending various system notifications.