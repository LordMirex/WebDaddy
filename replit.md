# WebDaddy Empire - Template Marketplace

## Overview

WebDaddy Empire is a PHP/SQLite marketplace for selling website templates bundled with premium domain names. It features WhatsApp-based payment processing, an affiliate marketing program with commission tracking, and comprehensive admin management. The platform allows customers to browse templates, select premium domains, place orders, and pay via WhatsApp. Affiliates earn commissions on successful sales, contributing to a robust ecosystem for digital product commerce. The project's ambition is to provide a production-ready, high-performance platform for template sales.

## User Preferences

Preferred communication style: Simple, everyday language.

## Recent Fixes (November 25, 2025)

**Email Queue Issue - FIXED**
- **Problem**: Emails were being queued but never sent because the email processor wasn't running automatically
- **Root Cause**: The `processEmailQueue()` function needed to be triggered after orders/registrations, but it wasn't being called
- **Solution**: Added automatic email processing triggers to:
  - `cart-checkout.php` - Processes emails after order creation
  - `affiliate/register.php` - Processes emails after affiliate registration
  - `api/paystack-verify.php` - Processes emails after payment verification
- **New File**: `includes/email_processor.php` - Safe trigger function to process the queue every 60 seconds per session
- **Status**: All pending emails have been sent successfully ✅

**CRITICAL DESIGN STANDARDS:**
- ALL UI/UX designs must be polished, professional, and visually clean
- NEVER implement fast/ugly designs - quality UI is non-negotiable for this website
- ALWAYS match existing design patterns (example: template.php social sharing section)
- ALWAYS center-align text properly and remove cluttered/awkward icon placements
- ALWAYS use proper spacing, shadows, hover effects, and responsive sizing
- ALWAYS test designs on mobile before delivery (user focuses on mobile experience)
- When in doubt about design, copy the exact pattern from proven pages (template.php, index.php)

## System Architecture

### Frontend

The frontend is built with **Vanilla JavaScript**, **Bootstrap**, and custom CSS, emphasizing progressive enhancement. Key patterns include a **Lazy Loading System** for media, **Performance Optimization** using `requestAnimationFrame`, **AJAX-Based Navigation**, and **Client-Side State Management** via `localStorage` for the shopping cart. A **Video Modal System** and **Image Cropper** enhance user interaction.

### Backend

The backend utilizes **PHP 8.2+** and **SQLite** for a file-based, portable database. It follows a **Procedural PHP with Modular Organization** pattern, using **Composer** for dependencies (e.g., PHPMailer). Data is stored across nine core tables, with JSON fields for flexible data. Security measures include password hashing, input validation, and session-based access control. Settings are database-driven, including new bank payment configurations. Performance is optimized with database indexes, query optimization, HTTP caching, Gzip compression, and lean API responses.

### Payment Processing

The platform supports a dual payment method flow:
- **Automatic Payment (Paystack)**: Integrates with Paystack for card payments, leveraging webhooks for payment verification and instant delivery.
- **Manual Payment (Bank Transfer)**: Allows customers to pay via bank transfer, with instructions and WhatsApp buttons for payment proof and queries, requiring admin manual review.
The checkout process is a single-page experience with sections for customer information, order summary, and payment method toggle.

### Affiliate Marketing

A **Cookie-Based Attribution** system tracks affiliate commissions, with configurable durations. It includes click and conversion tracking, multi-tier commission support, and a workflow for commission status management (Pending → Earned → Paid).

### File Uploads

Files are organized into `/uploads/templates/` and `/uploads/tools/` for images and videos, with a temporary folder for transient files. Security measures include file type validation, unique filename generation, size limits, and `.htaccess` rules to prevent PHP execution in upload directories.

### SEO Optimization

Comprehensive SEO features include **Custom OG Images** and **Open Graph Tags**, **Twitter Cards**, and **Structured Data (Schema.org)** for product and organization types. **Clean URL Structure**, **Canonical Tags**, and **301 Redirects** are implemented. A dynamic sitemap and `robots.txt` are generated for search engine discoverability.

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
-   **Navigator.connection**: (Optional) For network speed detection.
-   **RequestAnimationFrame**: (Optional) For smooth animations.
-   **File API**: (Optional) For client-side image cropping.