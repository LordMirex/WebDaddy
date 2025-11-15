# WebDaddy Empire - Affiliate Platform

## Overview
WebDaddy Empire is an affiliate marketing platform designed for selling website templates and digital working tools, complete with domain integration. The platform aims to provide a robust, easy-to-deploy solution for cPanel environments, enabling users to manage templates, tools, affiliates, and sales efficiently. It focuses on a streamlined user experience for both administrators and affiliates, offering comprehensive analytics, automated email campaigns, and secure operations.

## Recent Changes (November 15, 2025)
### Cart Checkout Form Preservation Fix
1. **Affiliate Code Application** - Fixed issue where applying an affiliate code on the cart checkout page would clear all previously entered customer information (name, email, phone)
2. **Field Preservation** - Added hidden fields and JavaScript to capture and preserve customer form values during affiliate code submission
3. **Improved UX** - Users can now safely apply discount codes without losing their entered information, eliminating the frustration of re-typing details

### WhatsApp Message Enhancement
1. **Improved Visual Formatting** - Enhanced WhatsApp messages with visual separators (━━━━), emojis (🛒, 📋, 🎨, 🔧, ✅, 💳), and bold text formatting using WhatsApp's native syntax (*text*)
2. **Clear Product Categorization** - Messages now clearly distinguish between Templates and Tools with dedicated sections, emoji indicators, and item counts for each category
3. **Professional Structure** - Added header "NEW ORDER REQUEST", order ID display, and structured layout that makes messages more visually appealing and easier to read

### Admin Modal UX Fix
1. **Eliminated Duplicate Forms** - Fixed the order confirmation modal to only show "Update All Changes" form for already-confirmed orders (non-pending status)
2. **Smart Conditional Rendering** - "Confirm Order" form now exclusively appears for pending orders, while "Update All Changes" is reserved for post-confirmation updates
3. **Clean Interface** - Removed confusing duplicate domain selection and notes fields that previously appeared when viewing pending orders

### Order Confirmation Streamlining
1. **Single-Step Order Confirmation** - Unified domain assignment and payment confirmation into one streamlined form, eliminating the need for multiple page reloads
2. **Integrated Domain Selection** - Domain dropdowns now appear directly in the confirmation form (optional), allowing admins to select domains and confirm orders in a single action
3. **Enhanced User Experience** - Removed separate "Assign Domain" buttons, confusing delay messages, and page reloads. Order confirmation now shows one clean form with domain dropdowns, payment notes field, and a single "Confirm Order" button

### Admin Reports Page Fixes
1. **Total Discount Calculation** - Fixed hardcoded ₦0.00 value by implementing proper SQL query to sum discount_amount from sales table, now accurately showing total discounts given to customers via affiliate codes
2. **Top Selling Products Revenue** - Fixed ₦0.00 display issue by changing revenue calculation from complex proportional formula to direct summation of order_items.final_amount, ensuring accurate product revenue reporting

### Analytics Dashboard Fixes
1. **HTML Rendering Fix** - Fixed missing closing `>` tag in admin/analytics.php that prevented the page from loading correctly
2. **Bounce Rate Tracking** - Implemented proper bounce tracking by setting `is_bounce=1` on new sessions and updating to `is_bounce=0` when users visit multiple pages
3. **Data Migration Script** - Created `database/migrations/fix_bounce_tracking.php` to backfill existing session data with correct bounce values
4. **Statistics Verification** - Confirmed all analytics metrics are calculating correctly: visits (2,048), unique visitors (1,667), bounce rate (94.3%), revenue tracking, template views, and IP filtering
5. **Production Ready** - All analytics features now working: period filters, IP search, CSV exports, traffic sources, and chart visualizations

## Recent Changes (November 14, 2025)
### Critical Bug Fixes & Security Enhancements
1. **Order Cancellation System** - Implemented centralized `cancelOrder()` function with status guards to prevent cancellation of paid/completed orders, protecting financial data integrity
2. **Multi-Item Domain Assignment** - Created `setOrderItemDomain()` function to properly assign domains per item in multi-template orders
3. **Analytics Session Tracking** - Added `ensureAnalyticsSession()` to fix session initialization issues causing bounce rate and template click tracking failures
4. **WhatsApp Payment Security** - Removed editable amounts from WhatsApp messages to prevent user tampering with payment amounts
5. **Amount Calculation Lockdown** - Enforced server-side-only amount calculation using `computeFinalAmount()` with legacy order fallbacks
6. **Order Confirmation Simplification** - Removed manual amount input from admin interface, displaying auto-calculated amounts only
7. **Legacy Order Support** - Enhanced `computeFinalAmount()` with fallback to `amount_paid` for historical orders without item records

## User Preferences
### Coding Style
- Consistent indentation (4 spaces)
- Clear, descriptive function names
- Security-first approach (sanitize all inputs)
- Use prepared statements for all database queries

### Performance
- Optimize database regularly (VACUUM)
- Pagination on all large datasets
- Session optimization enabled
- Database connection pooling via singleton

### Security
- CSRF protection on forms
- XSS protection via htmlspecialchars()
- Session hijacking prevention
- Rate limiting on login attempts
- Error messages don't expose sensitive info

## System Architecture
### UI/UX Decisions
- **Design Language**: Tailwind CSS for public-facing pages, Bootstrap for admin interfaces.
- **Dual Order Flow**: Templates use instant WhatsApp ordering (one-click), Tools use cart-based checkout (multi-item bundling).
- **AJAX Navigation**: Tab switching between Templates/Tools with no page reload, preserves scroll position, smooth animations. Category state persists during filtering, resets only when switching between views.
- **Category Filtering**: Full-width dropdown selectors for both templates and tools (scalable to 50+ categories). Clone-and-replace event binding pattern ensures clean AJAX updates without duplicate handlers.
- **Preview Modals**: Popup systems for both tool details (top-right positioning) and template demos (wider, taller modals).
- **Floating Cart**: Top-right FAB button (optimized positioning to avoid interference with WhatsApp at bottom-left) showing item count only. Cart badge uses red background for visibility, positioned at top-right of icon. Simplified design for faster performance - removed total amount display and complex animations. Slide-in drawer for quick cart management.
- **WhatsApp Integration**: Smart floating button with rotating contextual messages, displays immediately on page load at bottom-left.
- **Product Display**: Compact price typography, clear category badges, and product counts visible in section headers.
- **Email Templates**: Cleaned up and simplified for professionalism.
- **Search Experience**: Instant AJAX search with 500ms debounce, loading indicator, and XSS-safe implementation.
- **Cron Job System**: Simplified for cPanel with click-to-copy commands and clear explanations.
- **Analytics Dashboard**: Bootstrap-styled, responsive, with cards, metrics, tables, and period filters.
- **Admin Forms**: Compact design with 3-column layouts, reduced padding/spacing, and consistent Naira (₦) currency labels.

### Technical Implementations
- **Backend**: PHP 8.x+
- **Database**: SQLite (file-based, portable, `database/webdaddy.db`).
- **Frontend Interactivity**: Alpine.js for lightweight client-side logic.
- **Styling**: Tailwind CSS (via CDN).
- **Email Handling**: PHPMailer for reliable email delivery.
- **Charting**: Chart.js for analytics visualizations.
- **Timezone**: All timestamps are set to Africa/Lagos (GMT+1 / WAT).
- **Security Features**: XSS protection, CSRF protection, session hijacking prevention, input sanitization, prepared statements, rate limiting.
- **Analytics Tracking**: Tracks page visits, device types, user searches (with result counts), affiliate actions.
- **Automated Tasks**: Simplified cron job system for daily/weekly/monthly backups, cleanup, and scheduled affiliate emails.
- **Optimizations**: Database VACUUM, ANALYZE, OPTIMIZE commands; session write optimization; UTF-8 encoding across the platform, including CSV exports.

### Feature Specifications
- **Dual Marketplace**: Templates (direct WhatsApp ordering) and Tools (cart-based multi-item ordering).
- **Template Management**: Display and search for templates with instant WhatsApp ordering.
- **Tools Management**: Digital tools marketplace with popup previews, cart system, and batch checkout.
- **Shopping Cart**: Session-based cart with floating button, slide-in drawer, quantity management, and multi-item checkout.
- **AJAX Navigation**: Seamless tab switching between Templates and Tools.
- **Affiliate System**: Registration, dashboard, tracking of clicks/sales, scheduled performance emails, announcement system.
- **Admin Panel**: Comprehensive dashboard for managing templates, tools, affiliates, analytics, database, and email communications.
- **Search Functionality**: Instant, dynamic search for templates and tools with analytics tracking.
- **Email System**: Unified modal for emailing affiliates, scheduled emails, and spam folder warnings.
- **Backup System**: Automated daily/weekly/monthly backups with configurable retention and email notifications.
- **Analytics**: Detailed dashboards for site visits, template views/clicks, search terms, device tracking, and IP filtering.
- **Support**: Integrated WhatsApp floating button on all pages (index and template details) with contextual messages, support ticket system, direct admin contact options.

### System Design Choices
- **SQLite over MySQL**: Chosen for ease of deployment on shared hosting environments.
- **Tailwind CDN**: Prioritized for faster development cycles and no build step.
- **Session Optimization**: Implemented write-close pattern to prevent session locks.
- **UTF-8 Everywhere**: Ensures proper character encoding for international symbols and compatibility.
- **Simplified Cron Jobs**: Designed for user-friendliness in cPanel.

## External Dependencies
- **PHPMailer**: Used for all email delivery.
- **Tailwind CSS**: Integrated via CDN for styling.
- **Alpine.js**: Utilized for lightweight JavaScript interactivity.
- **Chart.js**: Employed for rendering charts in the analytics dashboard.