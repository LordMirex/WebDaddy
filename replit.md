# WebDaddy Empire - Template Marketplace

## Overview
WebDaddy Empire is a production-ready PHP/PostgreSQL template marketplace designed for selling website templates with pre-configured domains. Its core purpose is to provide a platform for customers to purchase templates with a unique WhatsApp-first manual payment processing system, supported by an admin management panel and an affiliate tracking system. The business vision is to offer a professional and conversion-optimized platform for acquiring website templates, targeting a market that values simplicity and direct interaction for purchases.

## User Preferences
- Code style: PSR-12 compliant, 4 spaces, camelCase variables
- No frameworks - plain PHP only
- Bootstrap 5 for UI
- Professional, production-ready code
- Focus on conversion optimization and professional design
- UPDATE REPLIT.MD WITH EVERY CHANGE

## Recent Changes

### October 30, 2025 - Comprehensive Mobile Responsiveness Overhaul (COMPLETED)
- **Mobile-First CSS:** Added 400+ lines of comprehensive mobile-responsive CSS with media queries for tablets (≤768px), mobile devices (≤576px), and orientation-specific optimizations
- **Viewport Meta Tags:** Updated all pages (public, admin, affiliate) with mobile-friendly viewport settings (removed user-scalable=no and maximum-scale restrictions for better accessibility)
- **Responsive Column Classes Fixed:** Corrected all Bootstrap column classes across entire site for proper mobile stacking:
  - Homepage templates: `col-12 col-sm-6 col-lg-4` (1→2→3 columns)
  - Homepage testimonials/steps: `col-12 col-md-4` (1→3 columns)
  - Admin stat cards: `col-6 col-md-3` (2→4 columns on mobile)
  - Order form fields: `col-12 col-md-6` (1→2 columns)
  - Affiliate dashboards: `col-6 col-md-3` (2→4 columns on mobile)
- **Admin Tables:** All tables wrapped in `.table-responsive` for horizontal scrolling on mobile
- **Hero Section Optimization:** Responsive typography scaling from 2.5rem desktop down to 1.65rem mobile, adaptive padding and spacing
- **Trust Badges & Metrics:** Fully responsive stacking and sizing for mobile viewports
- **Template Cards:** Mobile-optimized card layouts with full-width buttons and proper spacing on small screens
- **Forms & Inputs:** Compact, touch-friendly form controls with appropriate sizing for mobile devices
- **Navigation:** Responsive navbar with proper mobile menu behavior across all sections
- **Typography:** Dynamic font scaling across all heading levels (h1-h4) for optimal mobile readability
- **Buttons & CTAs:** Mobile-optimized button sizing and full-width stacking where appropriate
- **Modal Dialogs:** Mobile-friendly modal sizing with proper margins and padding
- **Landscape & Portrait:** Specific optimizations for both device orientations
- **Files Modified:** `assets/css/style.css`, `index.php`, `order.php`, `template.php`, all admin pages, all affiliate pages, login pages
- **Architect Review:** Passed comprehensive review - proper stacking behavior confirmed, no conflicts or regressions
- **Target Audience:** Fully optimized for mobile users as the primary user base

### October 30, 2025 - Project Import to Replit Environment Completed
- **Database Setup:** Created PostgreSQL database using Replit's built-in database service
- **Configuration Update:** Modified `includes/config.php` to use Replit environment variables (PGHOST, PGDATABASE, PGUSER, PGPASSWORD, PGPORT)
- **Database Schema:** Successfully executed `database/schema.sql` to create all tables, types, and sample data
- **Admin Password:** Updated admin password hash for proper authentication
- **Verification:** Tested homepage and admin login page - both working perfectly
- **Server Status:** PHP development server running on port 5000 with webview output
- **Import Status:** Project import completed successfully and ready for development

### October 28, 2025 - Session & Admin Panel Fix
- **Critical Session Fix:** Configured PHP session save path to `/tmp/php_sessions` - sessions now persist correctly
- **Admin Login Fix:** Admin panel now works properly - no more redirect loops
- **Button Responsiveness:** Fixed Bootstrap JS loading and session issues that prevented buttons from working
- **UI Refinement:** Reduced stat card number sizes and padding for more compact admin dashboard
- **Files Modified:** `includes/session.php`, `assets/css/style.css`

### October 28, 2025 - Project Import to Replit Environment & Admin Login Fix
- **Environment Setup:** Successfully migrated project to Replit environment
- **PHP Installation:** Installed PHP 8.2 module with Composer package manager
- **PostgreSQL Database:** Created and configured PostgreSQL database with full schema
- **Database Tables:** Created all 9 tables (settings, users, templates, affiliates, domains, pending_orders, sales, withdrawal_requests, activity_logs)
- **Sample Data:** Inserted default admin user, 3 sample templates, and 6 sample domains
- **Configuration:** Created `includes/config.php` with environment-based configuration for Replit PostgreSQL
- **Web Server:** Configured PHP development server on port 5000 with webview output
- **Admin Login Fix:** Fixed password hash issue - regenerated correct hash for admin account
- **Admin Credentials:** Email: admin@example.com | Password: admin123
- **Verification:** Tested homepage and admin login - both working perfectly
- **Files Created/Modified:** `includes/config.php`, `.gitignore`

### October 28, 2025 - Frontend UI Enhancement
- **Comprehensive CSS Overhaul:** Added 500+ lines of professional styling for admin and affiliate sections in `assets/css/style.css`, ensuring visual consistency across all three main sections (Landing Page, Admin Panel, Affiliate Portal)
- **Unified Navigation:** Replaced dark/colored headers with professional royal blue gradient navigation matching landing page design in both admin and affiliate sections
- **Professional Card Design:** Redesigned dashboard stat cards and info cards with white backgrounds and colored accent borders instead of Bootstrap's colored backgrounds, creating a cleaner, more professional appearance
- **Responsive Design:** Enhanced mobile and tablet responsiveness across all admin and affiliate pages with proper breakpoints and spacing
- **JavaScript Error Fix:** Resolved classList null reference error by adding missing `id="mainNav"` to public landing page navbar
- **Zero Console Errors:** All pages now load without JavaScript errors or warnings
- **Files Modified:** `assets/css/style.css`, `admin/includes/header.php`, `affiliate/includes/header.php`, `affiliate/index.php`, `public/index.php`

## System Architecture

### UI/UX Decisions
The design theme is professional, clean, and conversion-focused, utilizing a brand identity centered around "WebDaddy Empire" with a royal crown logo and a color scheme of Royal Blue (#1e3a8a), Gold (#d4af37), and Accent Navy Blue (#0f172a). The design prioritizes minimalism, consistency, and responsiveness across all devices, eliminating excessive animations or "gimmicky" elements. All three main sections (Landing Page, Admin Panel, Affiliate Portal) now share unified design language with royal blue gradient navigation, professional white cards with colored accent borders, and consistent spacing and typography. The homepage is specifically designed for conversion, placing templates above the fold with a compact hero section, simplified "How It Works," and an FAQ accordion.

### Technical Implementations
The backend is built with plain PHP 8.x, interacting with a PostgreSQL database. The frontend uses Bootstrap 5.3.2 for styling and Vanilla JavaScript for interactivity. The system employs prepared statements for SQL injection prevention, `password_hash/verify` for authentication, session regeneration, HttpOnly + Secure cookies, HTTPS enforcement, and input sanitization to ensure security.

### Feature Specifications
- **Public Features:** Conversion-optimized homepage, template browsing with detail pages and live previews, affiliate tracking (30-day persistence), order form with domain selection and WhatsApp payment redirect, and a simple FAQ.
- **Admin Features:** Secure login, dashboard with statistics, template/domain CRUD operations, order processing, affiliate management, and CSV exports.
- **Affiliate Features:** Login dashboard, earnings/commission tracking (30% commission), settings for profile and bank account management, simplified withdrawal requests, and password update functionality.

### System Design Choices
The folder structure is organized into `public/` (customer-facing), `admin/` (admin panel), `affiliate/` (affiliate dashboard), `includes/` (shared PHP), `assets/` (CSS, JS, images), and `database/` (SQL schema). The database schema includes tables for `users`, `templates`, `domains`, `pending_orders`, `sales`, `affiliates`, `withdrawal_requests`, `activity_logs`, and `settings`. Business rules dictate a 30% affiliate commission, 30-day affiliate persistence, a specific order flow from form submission to domain assignment, and a homepage template limit of 10 for optimal conversion.

## External Dependencies
- **Database:** PostgreSQL (Neon-backed via Replit)
- **Frontend Framework:** Bootstrap 5.3.2
- **Icons:** Bootstrap Icons
- **Hosting:** Replit Development Environment