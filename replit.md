# WebDaddy Empire - Template Marketplace

## Overview

WebDaddy Empire is a PHP/SQLite marketplace for selling website templates bundled with premium domain names. It features WhatsApp-based payment processing, an affiliate marketing program with commission tracking, and comprehensive admin management. The platform allows customers to browse templates, select premium domains, place orders, and pay via WhatsApp. Affiliates earn commissions on successful sales, contributing to a robust ecosystem for digital product commerce. The project's ambition is to provide a production-ready, high-performance platform for template sales.

## User Preferences

Preferred communication style: Simple, everyday language.

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