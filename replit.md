# WebDaddy Empire - Template Marketplace

## Overview
WebDaddy Empire is a PHP/SQLite template marketplace designed for selling website templates bundled with pre-configured domains. It features a unique WhatsApp-first manual payment system, an administrative management panel, and an affiliate tracking system. The platform aims to provide a professional and conversion-optimized experience for acquiring website templates, focusing on simplicity and direct interaction for purchases. The application uses a single, portable SQLite database file (`webdaddy.db`). This project envisions becoming a leading platform for ready-to-launch websites, simplifying the online presence creation for small businesses and individuals.

**Current Refactoring Progress:** 100% Complete (27/27 issues resolved)
- ✅ Phase 1: Critical Functionality Fixes - COMPLETED
- ✅ Phase 2: Mobile Responsive Fixes - COMPLETED (November 5, 2025)
- ✅ Phase 3: Branding & Navigation - COMPLETED (November 5, 2025)
- ✅ Phase 4: Landing Page UX - COMPLETED (November 5, 2025)
- ✅ Phase 5: Polish & Testing - COMPLETED (November 5, 2025)

**Database & Communication Features Fix:** (November 5, 2025)
- ✅ Database initialized with complete schema (all tables created)
- ✅ Announcements feature enhanced to support individual affiliate targeting
- ✅ Email affiliate functionality verified and working
- ✅ Bulk email to all affiliates functionality verified and working
- ✅ Schema files updated to include affiliate_id column for fresh installations
- ✅ All features tested and production-ready

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
The design is professional, clean, and conversion-focused, built around the "WebDaddy Empire" brand with a royal crown logo and a color scheme of Royal Blue (#1e3a8a), Gold (#d4af37), and Accent Navy Blue (#0f172a). Tailwind CSS via CDN is used for modern utility-first styling, and Alpine.js handles interactive components (e.g., mobile menu, FAQ accordions) via CDN, ensuring portability. The UI prioritizes minimalism, consistency, and responsiveness with a unified design, royal blue gradient navigation, professional white cards with colored accent borders, and consistent typography. The homepage is optimized for conversion, featuring templates above the fold, a modern instant search bar with category filtering, a simplified "How It Works" section, an FAQ accordion, and optimized spacing throughout. The template browsing experience includes real-time JavaScript-powered search and category filtering with a live results counter. Mobile responsiveness is comprehensively addressed with mobile-first Tailwind utilities and responsive grid classes across all public, affiliate, and admin interfaces. **Branding is consistently implemented across all pages using the webdaddy-logo.png file (assets/images/webdaddy-logo.png) in headers (admin, affiliate), login pages, and registration pages, replacing all previous icon-based logos for professional brand presentation.**

### Technical Implementations
The backend uses plain PHP 8.x and interacts with a SQLite database (`webdaddy.db`). The frontend utilizes Tailwind CSS 3.x via CDN and Alpine.js 3.x via CDN for interactivity, ensuring zero-installation deployment by loading resources directly from unpkg/jsdelivr. Security measures include CSRF protection on all forms, rate limiting on login attempts, prepared statements for SQL injection prevention, `password_hash/verify` for authentication, session regeneration, HttpOnly + Secure cookies, HTTPS enforcement, input sanitization, and comprehensive security headers (X-XSS-Protection, CSP, HSTS). Foreign key constraints are enabled via PRAGMA. Error handling is improved with database operation validation and user-friendly messages. The system is SEO-ready with robots.txt and sitemap.xml, and includes professional error pages (404, 500). All Bootstrap dependencies have been completely removed and replaced with Tailwind CSS and Alpine.js for a leaner, faster frontend.

### Feature Specifications
- **Public Features:** Conversion-optimized homepage, template browsing with detail pages and live previews, 30-day affiliate tracking, an order form with domain selection and WhatsApp payment redirect, and a simple FAQ.
- **Admin Features:** Secure login, dashboard with statistics, CRUD operations for templates/domains, order processing, affiliate management (including withdrawal processing and commission rate management), and CSV exports. The admin panel includes comprehensive tools for managing users, sales, and content.
- **Affiliate Features:** Login dashboard, earnings/commission tracking (30% commission), settings for profile and bank account management, simplified withdrawal requests, and password update functionality. Includes marketing tools like referral link variants and social media/email copy templates.

### System Design Choices
The project is structured into `public/`, `admin/`, `affiliate/`, `includes/`, `assets/`, and `database/` folders. The database schema includes tables for `users`, `templates`, `domains`, `pending_orders`, `sales`, `affiliates`, `withdrawal_requests`, `activity_logs`, `settings`, and `announcements`. Key business rules include a 30% affiliate commission, 30-day affiliate persistence, a specific order flow, and a homepage template limit of 10. Modals are implemented using Alpine.js `x-show`/`x-data` patterns with Tailwind for styling, replacing all Bootstrap modal functionalities.

**Announcements System:** The announcements table supports both broadcast announcements (for all affiliates) and targeted announcements (for specific affiliates). Admin can create announcements via the affiliate management panel and select either "All Affiliates" or a specific affiliate. Affiliates see announcements that are either global (affiliate_id IS NULL) or specifically targeted to them (affiliate_id matches their ID). The system uses a simple yet powerful filtering mechanism to ensure proper announcement delivery.

## External Dependencies
- **Database:** SQLite (webdaddy.db)
- **Frontend Framework:** Tailwind CSS 3.x (via CDN)
- **JavaScript Library:** Alpine.js 3.x (via CDN)
- **Icons:** Heroicons SVG (inline)
- **Email:** PHPMailer for SMTP email delivery
- **Rich Text Editor:** Quill (for admin bulk email functionality)