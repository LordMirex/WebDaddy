# WebDaddy Empire - Template Marketplace

## Overview
WebDaddy Empire is a PHP/SQLite template marketplace for selling website templates with pre-configured domains. It features a unique WhatsApp-first manual payment system, an admin management panel, and an affiliate tracking system. The platform aims to offer a professional and conversion-optimized experience for acquiring website templates, emphasizing simplicity and direct interaction for purchases. The application uses a single, portable SQLite database file (`webdaddy.db`).

## User Preferences
- Code style: PSR-12 compliant, 4 spaces, camelCase variables
- No frameworks - plain PHP only
- **UPDATED Nov 4, 2025:** Tailwind CSS via CDN (zero-install, portable approach) - NO npm, NO build tools
- Alpine.js via CDN for interactive components
- Professional, production-ready code
- Focus on conversion optimization and professional design
- UPDATE REPLIT.MD WITH EVERY CHANGE

## System Architecture

### UI/UX Decisions
The design is professional, clean, and conversion-focused, utilizing a brand identity around "WebDaddy Empire" with a royal crown logo and a color scheme of Royal Blue (#1e3a8a), Gold (#d4af37), and Accent Navy Blue (#0f172a). **UPDATED Nov 4, 2025:** Migrated from Bootstrap 5 to Tailwind CSS via CDN for modern utility-first styling while maintaining complete portability (zero installation). Alpine.js handles interactive components (mobile menu, FAQ accordions) via CDN. It prioritizes minimalism, consistency, and responsiveness, avoiding excessive animations. All sections share a unified design with royal blue gradient navigation, professional white cards with colored accent borders, and consistent typography. The homepage is optimized for conversion, featuring templates above the fold, a simplified "How It Works" section, and an FAQ accordion. Mobile responsiveness has been comprehensively addressed with mobile-first Tailwind utilities and responsive grid classes.

### Technical Implementations
The backend uses plain PHP 8.x and interacts with a SQLite database (`webdaddy.db`). **UPDATED Nov 4, 2025:** The frontend now uses Tailwind CSS 3.x via CDN (completely portable, no build step required) and Alpine.js 3.x via CDN for interactivity. All CDN resources are loaded directly from unpkg/jsdelivr for zero-installation deployment. Security measures include CSRF protection on all forms, rate limiting on login attempts, prepared statements for SQL injection prevention, `password_hash/verify` for authentication, session regeneration, HttpOnly + Secure cookies, HTTPS enforcement, input sanitization, and comprehensive security headers (X-XSS-Protection, CSP, HSTS). Foreign key constraints are enabled via PRAGMA. Error handling has been improved with database operation validation and user-friendly messages. SEO-ready with robots.txt and sitemap.xml, professional error pages (404, 500).

### Feature Specifications
- **Public Features:** Conversion-optimized homepage, template browsing with detail pages and live previews, 30-day affiliate tracking, an order form with domain selection and WhatsApp payment redirect, and a simple FAQ.
- **Admin Features:** Secure login, dashboard with statistics, CRUD operations for templates/domains, order processing, affiliate management, and CSV exports.
- **Affiliate Features:** Login dashboard, earnings/commission tracking (30% commission), settings for profile and bank account management, simplified withdrawal requests, and password update functionality.

### System Design Choices
The project is structured into `public/`, `admin/`, `affiliate/`, `includes/`, `assets/`, and `database/` folders. The database schema includes tables for `users`, `templates`, `domains`, `pending_orders`, `sales`, `affiliates`, `withdrawal_requests`, `activity_logs`, and `settings`. Key business rules include a 30% affiliate commission, 30-day affiliate persistence, a specific order flow, and a homepage template limit of 10.

## Recent Changes
- **Frontend Migration to Tailwind CSS (Nov 4, 2025):**
  - **Zero-Installation Approach:** Migrated from Bootstrap 5 to Tailwind CSS 3.x via CDN for complete portability
  - **Alpine.js Integration:** Added Alpine.js 3.x and Collapse plugin via CDN for interactive components (mobile menu, FAQ accordions)
  - **Homepage Refactor:** Completely redesigned index.php with modern Tailwind utility classes:
    - Responsive navigation with mobile hamburger menu (Alpine.js)
    - Gradient hero section with trust badges and statistics
    - Modern template cards with hover effects and smooth transitions
    - Searchable template grid with category filtering
    - Testimonial cards with 5-star ratings
    - "How It Works" section with numbered steps
    - FAQ accordion with Alpine.js collapse animations
    - Professional footer with WhatsApp integration
  - **Custom Tailwind Config:** Extended color palette with primary blues, gold accents, and navy background
  - **No Build Tools Required:** All resources loaded via CDN (Tailwind, Alpine.js, Collapse plugin) - paste and run anywhere!
  - **Browser Console Clean:** Fixed Alpine.js collapse plugin warnings by adding proper CDN import
- **100% Task Completion - Security & Production Readiness (Nov 4, 2025):**
  - **CSRF Protection:** Implemented complete CSRF token system across all forms (admin login, affiliate login/register, order forms) using `generateCsrfToken()`, `validateCsrfToken()`, and `csrfTokenField()` functions
  - **Rate Limiting:** Added login attempt tracking and rate limiting for both admin and affiliate logins (5 attempts, 15-minute lockout) with `trackLoginAttempt()`, `isRateLimited()`, and `clearLoginAttempts()` functions
  - **Security Headers:** Enhanced .htaccess with X-XSS-Protection, Strict-Transport-Security, and Content-Security-Policy headers for comprehensive protection
  - **Removed Security Risk:** Deleted default credential display from admin login page (was showing admin123 publicly)
  - **SEO Files:** Created robots.txt (disallows admin/affiliate/includes/database) and sitemap.xml (with all 11 templates and key pages)
  - **Error Pages:** Professional 404.php and 500.php error pages with branded design and helpful navigation
  - **Code Quality:** All implementations follow PSR-12 standards, use prepared statements, and include proper error handling
- **Email System Fix (Nov 4, 2025):** 
  - Fixed "Email all affiliates" feature to use proper email templates with professional design
  - Fixed critical bug where `sanitizeInput()` was stripping all HTML formatting from emails
  - Added rich text editor (Quill) to "Email All Affiliates" modal for better formatting
  - Fixed JavaScript timing issue preventing form submission in email_affiliate.php
  - Fixed HTML5 validation blocking form submission on hidden textarea fields (removed `required` attribute, validation now handled by JavaScript)
  - Added loading state feedback when sending emails (button shows "Sending..." with spinner)
  - All bulk emails now use the professional affiliate template with crown icon, gradient header, and consistent branding
  - Individual and bulk emails now both support formatted content (bold, lists, links, headings, etc.)
- **Security Enhancement (Nov 4, 2025):** Fixed XSS vulnerability in email HTML sanitization where unquoted href attributes bypassed security filters. Now properly sanitizes both quoted and unquoted href attributes.

## External Dependencies
- **Database:** SQLite (webdaddy.db)
- **Frontend Framework:** Tailwind CSS 3.x (via CDN - completely portable)
- **JavaScript Library:** Alpine.js 3.x (via CDN - interactive components)
- **Icons:** Heroicons SVG (inline) and Bootstrap Icons (legacy pages)
- **Hosting:** Any PHP environment
- **Email:** PHPMailer for SMTP email delivery
