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

### October 28, 2025 - Project Import to Replit Environment
- **Environment Setup:** Successfully migrated project to Replit environment
- **PHP Installation:** Installed PHP 8.2 module with Composer package manager
- **PostgreSQL Database:** Created and configured PostgreSQL database with full schema
- **Database Tables:** Created all 9 tables (settings, users, templates, affiliates, domains, pending_orders, sales, withdrawal_requests, activity_logs)
- **Sample Data:** Inserted default admin user (email: admin@example.com, password: admin123), 3 sample templates, and 6 sample domains
- **Configuration:** Created `includes/config.php` with environment-based configuration for Replit PostgreSQL
- **Web Server:** Configured PHP development server on port 5000 with webview output
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