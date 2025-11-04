# WebDaddy Empire - Template Marketplace

## Overview
WebDaddy Empire is a PHP/SQLite template marketplace for selling website templates with pre-configured domains. It features a unique WhatsApp-first manual payment system, an admin management panel, and an affiliate tracking system. The platform aims to offer a professional and conversion-optimized experience for acquiring website templates, emphasizing simplicity and direct interaction for purchases. The application uses a single, portable SQLite database file (`webdaddy.db`).

## User Preferences
- Code style: PSR-12 compliant, 4 spaces, camelCase variables
- No frameworks - plain PHP only
- Tailwind CSS via CDN (zero-install, portable approach) - NO npm, NO build tools
- Alpine.js via CDN for interactive components
- Professional, production-ready code
- Focus on conversion optimization and professional design
- UPDATE REPLIT.MD WITH EVERY CHANGE

## System Architecture

### UI/UX Decisions
The design is professional, clean, and conversion-focused, utilizing a brand identity around "WebDaddy Empire" with a royal crown logo and a color scheme of Royal Blue (#1e3a8a), Gold (#d4af37), and Accent Navy Blue (#0f172a). Tailwind CSS via CDN is used for modern utility-first styling, and Alpine.js handles interactive components (mobile menu, FAQ accordions) via CDN, ensuring portability. The UI prioritizes minimalism, consistency, and responsiveness with a unified design, royal blue gradient navigation, professional white cards with colored accent borders, and consistent typography. The homepage is optimized for conversion, featuring templates above the fold, a simplified "How It Works" section, and an FAQ accordion. Mobile responsiveness is comprehensively addressed with mobile-first Tailwind utilities and responsive grid classes.

### Technical Implementations
The backend uses plain PHP 8.x and interacts with a SQLite database (`webdaddy.db`). The frontend utilizes Tailwind CSS 3.x via CDN and Alpine.js 3.x via CDN for interactivity, ensuring zero-installation deployment by loading resources directly from unpkg/jsdelivr. Security measures include CSRF protection on all forms, rate limiting on login attempts, prepared statements for SQL injection prevention, `password_hash/verify` for authentication, session regeneration, HttpOnly + Secure cookies, HTTPS enforcement, input sanitization, and comprehensive security headers (X-XSS-Protection, CSP, HSTS). Foreign key constraints are enabled via PRAGMA. Error handling is improved with database operation validation and user-friendly messages. The system is SEO-ready with robots.txt and sitemap.xml, and includes professional error pages (404, 500).

### Feature Specifications
- **Public Features:** Conversion-optimized homepage, template browsing with detail pages and live previews, 30-day affiliate tracking, an order form with domain selection and WhatsApp payment redirect, and a simple FAQ.
- **Admin Features:** Secure login, dashboard with statistics, CRUD operations for templates/domains, order processing, affiliate management, and CSV exports.
- **Affiliate Features:** Login dashboard, earnings/commission tracking (30% commission), settings for profile and bank account management, simplified withdrawal requests, and password update functionality.

### System Design Choices
The project is structured into `public/`, `admin/`, `affiliate/`, `includes/`, `assets/`, and `database/` folders. The database schema includes tables for `users`, `templates`, `domains`, `pending_orders`, `sales`, `affiliates`, `withdrawal_requests`, `activity_logs`, and `settings`. Key business rules include a 30% affiliate commission, 30-day affiliate persistence, a specific order flow, and a homepage template limit of 10.

## External Dependencies
- **Database:** SQLite (webdaddy.db)
- **Frontend Framework:** Tailwind CSS 3.x (via CDN)
- **JavaScript Library:** Alpine.js 3.x (via CDN)
- **Icons:** Heroicons SVG (inline)
- **Email:** PHPMailer for SMTP email delivery