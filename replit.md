# WebDaddy Empire - Affiliate Platform

## Overview
WebDaddy Empire is an affiliate marketing platform designed for selling website templates and digital working tools, complete with domain integration. The platform aims to provide a robust, easy-to-deploy solution for cPanel environments, enabling users to manage templates, tools, affiliates, and sales efficiently. It focuses on a streamlined user experience for both administrators and affiliates, offering comprehensive analytics, automated email campaigns, and secure operations.

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
- **Floating Cart**: Bottom-right FAB button showing live count and total, with slide-in drawer for quick management. Cart badge uses black background (not red), smaller sizing (h-4 w-4, 10px font), positioned inside icon to avoid WhatsApp button interference.
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
- **Support**: Integrated WhatsApp floating button, support ticket system, direct admin contact options.

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