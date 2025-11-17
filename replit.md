# WebDaddy Empire - Affiliate Platform

## Overview
WebDaddy Empire is an affiliate marketing platform for selling website templates and digital tools, with integrated domain management. It aims to provide an easy-to-deploy solution for cPanel environments, enabling efficient management of templates, tools, affiliates, and sales. The platform focuses on a streamlined user experience for both administrators and affiliates, offering comprehensive analytics, automated email campaigns, and secure operations. The business vision is to provide a robust platform for digital product sales, tapping into the growing market for online business tools and website assets.

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
- **Design Language**: Tailwind CSS for public pages, Bootstrap for admin interfaces.
- **Dual Order Flow**: Instant WhatsApp ordering for templates; cart-based checkout for tools.
- **AJAX Navigation**: Tab switching for Templates/Tools with scroll position preservation and smooth animations.
- **Category Filtering**: Full-width dropdowns for scalable category selection with clean AJAX updates.
- **Preview Modals**: Dedicated popup systems for tool details and template demos with 90% screen coverage (90vw × 80vh), optimized for vertical/portrait content like mobile videos and YouTube Shorts. Video modals use dynamic aspect ratios to display content naturally, and iframe modals show persistent loading indicators until content is ready.
- **Floating Cart**: Top-right FAB with item count, red badge, and slide-in drawer for quick management.
- **WhatsApp Integration**: Smart floating button at bottom-left with rotating contextual messages.
- **Product Display**: Compact price typography, clear category badges, and product counts.
- **Email Templates**: Simplified and professional design.
- **Search Experience**: Instant AJAX search with debounce and loading indicator.
- **Cron Job System**: Simplified for cPanel with click-to-copy commands.
- **Analytics Dashboard**: Bootstrap-styled, responsive, with cards, metrics, tables, and period filters.
- **Admin Forms**: Compact 3-column layouts with reduced padding and consistent Naira (₦) currency labels.

### Technical Implementations
- **Backend**: PHP 8.x+
- **Database**: SQLite (file-based: `database/webdaddy.db`).
- **Frontend Interactivity**: Alpine.js.
- **Styling**: Tailwind CSS (via CDN).
- **Email Handling**: PHPMailer.
- **Charting**: Chart.js.
- **Timezone**: Africa/Lagos (GMT+1 / WAT) for all timestamps.
- **Security Features**: XSS, CSRF, session hijacking prevention, input sanitization, prepared statements, rate limiting.
- **Analytics Tracking**: Page visits, device types, user searches, affiliate actions.
- **Automated Tasks**: Cron jobs for backups, cleanup, and scheduled emails.
- **Optimizations**: Database VACUUM/ANALYZE/OPTIMIZE, session write optimization, UTF-8 encoding.

### Feature Specifications
- **Dual Marketplace**: Templates (WhatsApp order) and Tools (cart-based).
- **Management**: Comprehensive admin panels for templates, tools, affiliates, and orders.
- **Shopping Cart**: Session-based with quantity management and multi-item checkout.
- **Affiliate System**: Registration, dashboard, tracking, scheduled emails, announcements.
- **Search Functionality**: Instant, dynamic search with analytics.
- **Email System**: Unified modal for affiliate emails, scheduled sends, spam warnings.
- **Backup System**: Automated daily/weekly/monthly backups with email notifications.
- **Analytics**: Detailed dashboards for site activity, product views, search terms, device tracking, IP filtering.
- **Support**: Integrated WhatsApp floating button with contextual messages, support ticket system.
- **File Upload System**: Lightweight direct upload system for images and videos with enhanced error handling, diagnostics, and security features (extension/MIME validation, malicious content scanning, PHP code detection). Max limits: 5MB for images, 10MB for videos. **No external dependencies required** - works on any PHP hosting (no FFmpeg needed). Videos are uploaded and served in their original quality for fast, reliable playback.
- **Image Cropping System**: Vanilla JavaScript cropper with aspect ratio support (16:9, 4:3, 1:1, free), live preview, zoom controls, and integration into admin forms for 1280x720 JPEG output. Features live dimension display and file size limits.
- **Media Type Management**: Clean separation of template media types:
  - Templates support three media types via `media_type` ENUM column: 'demo_url' (iframe preview), 'banner' (image only), 'video' (demo video modal)
  - Tools only support banner images (no demo URLs or videos)
  - Media type selection via radio buttons with conditional field display in admin forms
  - Frontend checks media_type before rendering iframe/video/banner content

### System Design Choices
- **SQLite over MySQL**: For ease of deployment on shared hosting.
- **Tailwind CDN**: For faster development without a build step.
- **Session Optimization**: Write-close pattern to prevent locks.
- **UTF-8 Everywhere**: For international character support.
- **Simplified Cron Jobs**: User-friendly for cPanel environments.

## External Dependencies
- **PHPMailer**: For email delivery.
- **Tailwind CSS**: For styling.
- **Alpine.js**: For frontend interactivity.
- **Chart.js**: For analytics visualizations.