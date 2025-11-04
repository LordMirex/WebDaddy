# WebDaddy Empire - Template Marketplace

## Overview
WebDaddy Empire is a PHP/SQLite template marketplace for selling website templates with pre-configured domains. It features a unique WhatsApp-first manual payment system, an admin management panel, and an affiliate tracking system. The platform aims to offer a professional and conversion-optimized experience for acquiring website templates, emphasizing simplicity and direct interaction for purchases. The application uses a single, portable SQLite database file (`webdaddy.db`).

## User Preferences
- Code style: PSR-12 compliant, 4 spaces, camelCase variables
- No frameworks - plain PHP only
- Bootstrap 5 for UI
- Professional, production-ready code
- Focus on conversion optimization and professional design
- UPDATE REPLIT.MD WITH EVERY CHANGE

## System Architecture

### UI/UX Decisions
The design is professional, clean, and conversion-focused, utilizing a brand identity around "WebDaddy Empire" with a royal crown logo and a color scheme of Royal Blue (#1e3a8a), Gold (#d4af37), and Accent Navy Blue (#0f172a). It prioritizes minimalism, consistency, and responsiveness, avoiding excessive animations. All sections (Landing Page, Admin Panel, Affiliate Portal) share a unified design with royal blue gradient navigation, professional white cards with colored accent borders, and consistent typography. The homepage is optimized for conversion, featuring templates above the fold, a simplified "How It Works" section, and an FAQ accordion. Mobile responsiveness has been comprehensively addressed with mobile-first CSS and appropriate Bootstrap column classes.

### Technical Implementations
The backend uses plain PHP 8.x and interacts with a SQLite database (`webdaddy.db`). The frontend is built with Bootstrap 5.3.2 and Vanilla JavaScript. Security measures include prepared statements for SQL injection prevention, `password_hash/verify` for authentication, session regeneration, HttpOnly + Secure cookies, HTTPS enforcement, and input sanitization. Foreign key constraints are enabled via PRAGMA. Error handling has been improved with database operation validation and user-friendly messages.

### Feature Specifications
- **Public Features:** Conversion-optimized homepage, template browsing with detail pages and live previews, 30-day affiliate tracking, an order form with domain selection and WhatsApp payment redirect, and a simple FAQ.
- **Admin Features:** Secure login, dashboard with statistics, CRUD operations for templates/domains, order processing, affiliate management, and CSV exports.
- **Affiliate Features:** Login dashboard, earnings/commission tracking (30% commission), settings for profile and bank account management, simplified withdrawal requests, and password update functionality.

### System Design Choices
The project is structured into `public/`, `admin/`, `affiliate/`, `includes/`, `assets/`, and `database/` folders. The database schema includes tables for `users`, `templates`, `domains`, `pending_orders`, `sales`, `affiliates`, `withdrawal_requests`, `activity_logs`, and `settings`. Key business rules include a 30% affiliate commission, 30-day affiliate persistence, a specific order flow, and a homepage template limit of 10.

## Recent Changes
- **Email System Fix (Nov 4, 2025):** 
  - Fixed "Email all affiliates" feature to use proper email templates with professional design
  - Fixed critical bug where `sanitizeInput()` was stripping all HTML formatting from emails
  - Added rich text editor (Quill) to "Email All Affiliates" modal for better formatting
  - Fixed JavaScript timing issue preventing form submission in email_affiliate.php
  - Added loading state feedback when sending emails (button shows "Sending..." with spinner)
  - All bulk emails now use the professional affiliate template with crown icon, gradient header, and consistent branding
  - Individual and bulk emails now both support formatted content (bold, lists, links, headings, etc.)
- **Security Enhancement (Nov 4, 2025):** Fixed XSS vulnerability in email HTML sanitization where unquoted href attributes bypassed security filters. Now properly sanitizes both quoted and unquoted href attributes.

## External Dependencies
- **Database:** SQLite (webdaddy.db)
- **Frontend Framework:** Bootstrap 5.3.2
- **Icons:** Bootstrap Icons
- **Hosting:** Any PHP environment
- **Email:** PHPMailer for SMTP email delivery