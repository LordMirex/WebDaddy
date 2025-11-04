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
- **Page Simplification & Affiliate Section Redesign (Nov 4, 2025):**
  - **Template Page Simplification (template.php):**
    - Removed "What You Get" section with 4 benefit cards to reduce information overload
    - Simplified "What's Included" to show only first 6 features in a single compact card
    - Streamlined sidebar: removed domain list and WhatsApp help card
    - Sidebar now shows only pricing, CTAs, and 3 essential bullet points
    - Cleaner, more focused page that highlights template preview and order button
  - **Order Page Affiliate Section Redesign (order.php):**
    - Completely redesigned affiliate section with prominent gold-gradient design
    - When no code: Large "Save 20% Instantly!" heading with eye-catching gold-accented card
    - When code applied: Green gradient success banner showing savings amount prominently
    - Enlarged input field (text-lg) and button for better mobile UX
    - Input auto-converts to uppercase and supports Enter key submission
    - Removed "What Happens Next?" section for cleaner, simpler page
  - **JavaScript Improvements:**
    - Separate event handling for affiliate button vs. main order submit button
    - Affiliate button shows "Applying..." loading state during validation
    - Fixed issue where affiliate button would incorrectly trigger WhatsApp redirect
    - Affiliate code validation now happens independently of order submission
  - **Conversion Optimization:**
    - Affiliate section now much more visually prominent to encourage code usage
    - Simplified pages focus user attention on key actions (preview, order, save)
    - Reduced cognitive load by removing redundant information
- **Complete Tailwind Migration - Order & Template Pages (Nov 4, 2025):**
  - **Order Page (order.php):** Fully converted from Bootstrap to Tailwind CSS
    - Responsive navigation with consistent branding
    - Modern gradient hero section with improved typography
    - Step-by-step form sections with numbered badges
    - Enhanced customer information inputs with focus states
    - Redesigned order summary sidebar with sticky positioning
    - All form elements use Tailwind utility classes
    - Professional error/warning messages with icons
    - Responsive grid layout (3-column on desktop, stacked on mobile)
  - **Template Detail Page (template.php):** Fully converted from Bootstrap to Tailwind CSS
    - Mobile-responsive navigation with Alpine.js toggle
    - Gradient hero section matching home page design
    - Large template preview with shadow effects
    - Live preview iframe with responsive height
    - Responsive CTA section and footer
  - **Technical Details:**
    - Both pages now use Tailwind CSS 3.x via CDN
    - Alpine.js for mobile navigation interactivity
    - Same custom color palette as home page (primary blues, gold, navy)
    - Removed all Bootstrap dependencies (CSS, JS, Icons)
    - Maintained all PHP functionality and business logic
    - Responsive breakpoints: mobile-first design with sm/md/lg variants
    - Consistent spacing, shadows, and border radius across all pages
- **WhatsApp Integration & Hero Enhancement (Nov 4, 2025):**
  - **Enhanced Hero Section:**
    - Updated headline: "Turn Your Website Idea Into Reality" - emphasizes both templates and custom development
    - Clear dual value proposition: "ready-made templates" OR "custom website built just for you"
    - Two prominent CTA buttons side-by-side (responsive: stacked on mobile, row on desktop)
    - "Browse Templates" button (outlined white)
    - "Get Custom Website" button with WhatsApp icon (solid white, opens WhatsApp)
  - **Floating WhatsApp Button:**
    - Fixed position bottom-right corner (green circular button)
    - WhatsApp icon with hover tooltip: "Chat with us on WhatsApp"
    - Smooth scale animation on hover
    - Pre-filled message for custom website inquiries
    - Always visible and accessible across all pages
  - **Responsive Improvements:**
    - Trust badges resize properly on all screen sizes
    - CTA buttons stack vertically on mobile, horizontal on tablet+
    - Success metrics scale smoothly from mobile to desktop
    - Optimized spacing and padding for all breakpoints
- **Preview Badge & Demo Modal Enhancement (Nov 4, 2025):**
  - **Visible Preview Badge:** Added a prominent "Preview" badge in the top-right corner of each template card (blue button, always visible)
  - **Improved Demo Modal:** Fixed the demo preview functionality with proper modal open/close behavior
  - **Modal Features:**
    - Full-screen responsive modal (90vh height, max-width 6xl)
    - Click outside to close
    - ESC key to close
    - Clean header with template name
    - Proper iframe loading for template demos
  - **Dual Preview Access:** Users can click either the visible "Preview" badge or the hover overlay "Click to Preview" button
  - **Better UX:** Preview badge makes it immediately clear that templates can be previewed without requiring hover
- **Homepage Redesign & Simplification (Nov 4, 2025):**
  - **Hero Section:** Removed cloudy black overlay for cleaner, more professional appearance
  - **Navigation:** Removed "How It Works" links from desktop and mobile menus
  - **Removed Section:** Completely removed "How It Works" section to streamline user flow
  - **Simplified Filters:** Removed search bar and results count - kept only clean category buttons with improved styling
  - **Category Filter UX:** Enhanced button active states with proper color toggling (primary blue when active, white with border when inactive)
  - **Footer Redesign:** 
    - Simplified 4-column to 2-column layout
    - Added trust/security badges (Secure Payment, SSL Protected, Trusted by 500+)
    - Prominent WhatsApp and Affiliate contact cards with icons
    - Removed Quick Links column
    - Removed Admin Login link for cleaner public-facing footer
  - **JavaScript Updates:** Streamlined TemplateFilter class to remove search and pagination logic
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
