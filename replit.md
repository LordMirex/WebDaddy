# WebDaddy Empire - Affiliate Platform

## Overview
WebDaddy Empire is an affiliate marketing platform designed for selling website templates and digital working tools, complete with domain integration. The platform aims to provide a robust, easy-to-deploy solution for cPanel environments, enabling users to manage templates, tools, affiliates, and sales efficiently. It focuses on a streamlined user experience for both administrators and affiliates, offering comprehensive analytics, automated email campaigns, and secure operations.

## Recent Changes (November 2025)

### Admin Panel UX Improvements
- **Tools Statistics Card**: Added dedicated statistics card on admin dashboard (`admin/index.php`) displaying active and total digital tools count alongside existing template metrics.
- **Compact Tool Forms**: Redesigned create/edit tool forms (`admin/tools.php`) with tighter spacing, 3-column layout for primary fields, reduced padding (py-2 instead of py-3), and smaller textareas (2 rows instead of 3-4) for better screen real estate usage.
- **Currency Display Consistency**: Updated all admin tool form labels from "Price (USD)" to "Price (₦)" to properly reflect Naira currency throughout the platform.

### User-Facing Website Enhancements
- **Template Preview Modal**: Improved preview modal (`index.php`) with wider viewport (max-w-7xl instead of max-w-6xl), added max-height constraint (900px), and better spacing to prevent content compression when viewing template demos.
- **Product Price Display**: Reduced price typography from `text-lg` to `text-base` on product cards for improved visual hierarchy and less overwhelming presentation.
- **WhatsApp Message Slider**: Optimized rotating message carousel to display immediately on page load (no 6-second delay) and rotate every 4 seconds instead of 6 seconds for better engagement.
- **Product Count Display**: Added visible statistics showing total templates/tools and category counts at the top of the products section for better transparency and user confidence.

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
- **AJAX Navigation**: Tab switching between Templates/Tools with no page reload, preserves scroll position, smooth animations.
- **Tool Preview Modal**: Popup system showing full tool details instead of dedicated pages for faster browsing.
- **Template Preview Modal**: Wide-screen modal (max-w-7xl, 90vh height with 900px max-height) to prevent iframe compression and provide optimal viewing experience.
- **Floating Cart**: Bottom-right FAB button showing live count and total, with slide-in drawer for quick management.
- **WhatsApp Integration**: Smart floating button with rotating contextual messages (4-second intervals), displays immediately on page load for better user engagement.
- **Product Display**: Compact price typography (text-base) for better visual hierarchy, with clear category badges and product counts visible in section headers.
- **Email Templates**: Cleaned up and simplified, removed logos and unnecessary CTAs for professionalism.
- **Search Experience**: Instant AJAX search with 300ms debounce, loading indicator, and XSS-safe implementation. Preserves scroll position and auto-resets when input is cleared.
- **Cron Job System**: Simplified for cPanel with click-to-copy commands and clear explanations.
- **Analytics Dashboard**: Bootstrap-styled, responsive, with cards, metrics, tables, and period filters for various data.
- **Admin Forms**: Compact design with 3-column layouts for primary fields, reduced padding/spacing, and consistent Naira (₦) currency labels throughout.

### Technical Implementations
- **Backend**: PHP 8.x+
- **Database**: SQLite (file-based, portable, `database/webdaddy.db`). Managed via `admin/database.php`.
- **Frontend Interactivity**: Alpine.js for lightweight client-side logic.
- **Styling**: Tailwind CSS (via CDN).
- **Email Handling**: PHPMailer for reliable email delivery.
- **Charting**: Chart.js for analytics visualizations.
- **Timezone**: All timestamps are set to Africa/Lagos (GMT+1 / WAT) via `includes/config.php`.
- **Security Features**: XSS protection, CSRF protection, session hijacking prevention, input sanitization, prepared statements for database queries, rate limiting on login attempts.
- **Analytics Tracking**: Tracks page visits, device types (Desktop/Mobile/Tablet), user searches (with result counts), affiliate actions (login, dashboard views, signup clicks).
- **Automated Tasks**: Simplified cron job system for daily/weekly/monthly backups, cleanup, and scheduled affiliate emails (performance updates, monthly summaries).
- **Optimizations**: Database VACUUM, ANALYZE, OPTIMIZE commands; session write optimization; UTF-8 encoding across the platform, including CSV exports with BOM for Excel compatibility.

### Feature Specifications
- **Dual Marketplace**: Templates (direct WhatsApp ordering) and Tools (cart-based multi-item ordering).
- **Template Management**: Support for displaying and searching multiple website templates with instant ordering via WhatsApp.
- **Tools Management**: Digital tools marketplace with popup previews, cart system, and batch checkout via WhatsApp.
- **Shopping Cart**: Session-based cart with floating button (count + total), slide-in drawer, quantity management, and multi-item checkout.
- **AJAX Navigation**: Seamless tab switching between Templates and Tools without page reload or scroll jump.
- **Affiliate System**: Affiliate registration, dashboard, tracking of clicks/sales, scheduled performance emails, announcement system. Works with both templates and tools.
- **Admin Panel**: Comprehensive dashboard for managing templates, tools, affiliates, analytics, database, and email communications.
- **Search Functionality**: Instant, dynamic search for templates with analytics tracking.
- **Email System**: Unified modal for emailing all or single affiliates, scheduled emails, and spam folder warnings.
- **Backup System**: Automated daily/weekly/monthly backups with configurable retention and email notifications.
- **Analytics**: Detailed dashboards for site visits, template views/clicks, top search terms, zero-result searches, device tracking, and IP filtering.
- **Support**: Integrated WhatsApp floating button, support ticket system, direct admin contact options.

### System Design Choices
- **SQLite over MySQL**: Chosen for ease of deployment on shared hosting environments.
- **Tailwind CDN**: Prioritized for faster development cycles and no build step.
- **Session Optimization**: Implemented write-close pattern to prevent session locks.
- **UTF-8 Everywhere**: Ensures proper character encoding for international symbols and compatibility.
- **Simplified Cron Jobs**: Designed for user-friendliness in cPanel, reducing complexity.

## External Dependencies

- **PHPMailer**: Used for all email delivery (SMTP settings configured in `includes/config.php`).
- **Tailwind CSS**: Integrated via CDN for styling.
- **Alpine.js**: Utilized for lightweight JavaScript interactivity.
- **Chart.js**: Employed for rendering charts in the analytics dashboard.