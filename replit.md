# WebDaddy Empire - Template Marketplace

## Overview

WebDaddy Empire is a PHP/SQLite marketplace for selling website templates bundled with premium domain names. It features WhatsApp-based payment processing, an affiliate marketing program with commission tracking, and comprehensive admin management. The platform allows customers to browse templates, select premium domains, place orders, and pay via WhatsApp. Affiliates earn commissions on successful sales, contributing to a robust ecosystem for digital product commerce. The project's ambition is to provide a production-ready, high-performance platform for template sales.

## User Preferences

Preferred communication style: Simple, everyday language.

## Latest Updates (November 25, 2025) - Unified Beautiful Checkout ✨

### UNIFIED CHECKOUT EXPERIENCE (No Separate Success Page)
- **Everything in ONE file** - `cart-checkout.php` handles both manual & automatic payment flows
- **Beautiful payment animations**:
  - Smooth loader overlay with gradient background and blur effect
  - Rotating spinner with 1.2s smooth animation
  - Green checkmark celebration on success
  - Fast 1.2s redirect to order confirmation
- **Smart Delivery Messaging**:
  - **Templates**: Shows "⏱️ Available within 24 hours" + "Admin assigns premium domain after payment"
  - **Tools**: Shows "⚡ Ready to download now" + "Download links sent to your email"
  - Products displayed separately by type for clarity
- **Professional Error Handling**:
  - Failed Paystack payments show reason + nice error message (not blank)
  - Manual payment errors handled gracefully
  - User-friendly error states throughout

### Payment Method Choice - Crystal Clear UI
- **Payment method selection BIG and prominent** - Boxes with clear descriptions
- **Button text changes dynamically**:
  - Manual: "Confirm Order - Manual Payment"
  - Automatic: "Proceed to Card Payment →"
- **Overlay appears ONLY AFTER automatic is chosen** - No blocking before decision
- **Manual payment skips overlay** - Goes directly to order confirmation

### Payment Processing Flow (Unified)
- **Manual Payment**: Form → Bank details → Confirmation page (inline in cart-checkout.php)
- **Automatic Payment**: Form → Paystack popup → Verification overlay with animations → Confirmation page (inline)
- **Success Page** (inline): Order approved, products listed by type, delivery info, files ready
- **Emails**: Confirmation sent immediately with file links
- **Affiliates**: Invitation sent automatically to new customers

### Technical Improvements
- ✅ Fixed Paystack secret key access (was using `getenv()` instead of constant)
- ✅ Database supports 'failed' payment status for error tracking
- ✅ Email queue processes immediately
- ✅ Beautiful animated loader (no JavaScript alerts)
- ✅ File delivery records created automatically
- ✅ Removed separate `cart-payment-success.php` - unified experience
- ✅ Tailwind dark theme consistent throughout (matches public pages)
- ✅ Responsive design - mobile friendly with proper scaling

## System Architecture

### Frontend

The frontend is built with Vanilla JavaScript, Bootstrap, and custom CSS, emphasizing progressive enhancement. Key patterns include a Lazy Loading System for media, Performance Optimization using `requestAnimationFrame`, AJAX-Based Navigation, and Client-Side State Management via `localStorage` for the shopping cart. A Video Modal System and Image Cropper enhance user interaction. All UI/UX designs must be polished, professional, and visually clean, always matching existing design patterns and tested on mobile devices.

### Backend

The backend utilizes PHP 8.2+ and SQLite for a file-based, portable database. It follows a Procedural PHP with Modular Organization pattern, using Composer for dependencies. Data is stored across core tables, with JSON fields for flexible data. Security measures include password hashing, input validation, and session-based access control. Settings are database-driven, including bank payment configurations. Performance is optimized with database indexes, query optimization, HTTP caching, Gzip compression, and lean API responses.

### Payment Processing

The platform supports a dual payment method flow:
-   **Automatic Payment (Paystack)**: Integrates with Paystack for card payments, leveraging webhooks for payment verification and instant delivery.
-   **Manual Payment (Bank Transfer)**: Allows customers to pay via bank transfer, requiring admin manual review.
The checkout process is a single-page experience.

### Affiliate Marketing

A Cookie-Based Attribution system tracks affiliate commissions, with configurable durations. It includes click and conversion tracking, multi-tier commission support, and a workflow for commission status management (Pending → Earned → Paid).

### File Uploads

Files are organized into `/uploads/templates/` and `/uploads/tools/` with a temporary folder for transient files. Security measures include file type validation, unique filename generation, size limits, and `.htaccess` rules to prevent PHP execution in upload directories.

### SEO Optimization

Comprehensive SEO features include Custom OG Images and Open Graph Tags, Twitter Cards, and Structured Data (Schema.org) for product and organization types. Clean URL Structure, Canonical Tags, and 301 Redirects are implemented. A dynamic sitemap and `robots.txt` are generated for search engine discoverability.

## External Dependencies

### Third-Party Libraries

-   **PHPMailer (v6.9+)**: For all email notifications and communications (Composer-managed).

### Database

-   **SQLite 3**: File-based relational database.

### External Services

-   **WhatsApp Business API (Informal)**: Integrated via deep links (`wa.me` URLs) for customer communication and payment proof.
-   **Paystack**: For automatic card payment processing, integrated with live public and secret keys, and webhook/verification APIs.

### Browser APIs

-   **LocalStorage**: For shopping cart persistence.
-   **IntersectionObserver**: For lazy loading of content.
-   **Fetch API**: For AJAX requests.