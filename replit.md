# WebDaddy Empire - E-Commerce Platform

## Overview
WebDaddy Empire is a PHP-based e-commerce platform designed to sell website templates and digital tools, primarily targeting African entrepreneurs. The platform includes a customer dashboard, an affiliate program, an admin panel, and integrated payment processing via Paystack. Its core purpose is to provide a robust and production-ready solution for digital product sales.

## User Preferences
- PHP-based architecture (no Node.js)
- SQLite for simplicity
- Token-based auth for reliability
- Shared hosting compatible
- Email-based OTP for verification
- Paystack LIVE keys (production-ready)
- Alpine.js CSP build for shared hosting compatibility

## System Architecture

### UI/UX Decisions
The platform features customer, affiliate, and admin dashboards. Design prioritizes clarity and functionality. Alpine.js CSP build is used for interactive elements, ensuring compatibility with various hosting environments by avoiding inline JavaScript and utilizing `Alpine.data()` for complex components.

### Technical Implementations
- **Authentication**: Token-based authentication for admins with HTTP-only cookies and database persistence. Customer authentication uses an email-based OTP verification process for registration, followed by email/password login with session persistence.
- **Cart System**: Fully functional, persisting items using both `session_id` and `customer_id` for iframe compatibility and user login states. Cart updates are real-time, with no caching of cart API responses.
- **Checkout Flow**: A simplified OTP-based checkout process where customers enter their email, verify with a 6-digit OTP, and then proceed to payment. Hidden fields auto-populate upon OTP verification.
- **Order Management**: Comprehensive order creation, tracking, and status updates are supported, generating download tokens for digital products upon successful payment.
- **Payment Processing**: Integrates with Paystack for automatic card payments and offers a manual bank transfer option. Critical fixes ensure `SameSite=None+Secure` cookie consistency for iframe compatibility and proper JSON header responses for all payment-related endpoints.
- **Shared Hosting Compatibility**: Extensive effort has been made to ensure compatibility with shared hosting environments, including replacing PHP 8.0+ `match` expressions with `switch` statements for PHP 7.4+ support and providing fallback solutions for Paystack webhooks (e.g., manual bank transfer).
- **Error Handling**: Implemented retry logic for Paystack payment verification to handle "no active transaction" errors.

### Feature Specifications
- **Admin Panel**: Secure, token-based access for managing products, orders, customers, and site settings.
- **Customer Dashboard**: Allows customers to view orders, access downloads, and manage support tickets.
- **Affiliate Program**: Dedicated dashboard for affiliates to track referrals and earnings.
- **Blog System**: Integrated blog with posts, categories, and tags.
- **API Endpoints**: Structured API for customer-specific actions (e.g., order payment, OTP verification) and admin functionalities.

### System Design Choices
- **PHP-based Backend**: Leverages PHP for server-side logic, optimized for performance and maintainability.
- **SQLite Database**: Utilizes SQLite (`./database/webdaddy.db`) for simplicity and ease of deployment, with auto-backup functionality.
- **Modular Structure**: Organized codebase with clear separation of concerns (admin, affiliate, user, API, includes, assets).
- **Workflow Configuration**: Designed to run with PHP's built-in web server (`php -S 0.0.0.0:5000`) for development, with considerations for `.htaccess` rewrites on shared hosting.

## External Dependencies

-   **Paystack**: Integrated for payment processing, supporting both automatic card payments and handling webhooks for transaction verification. Live API keys are configured.
-   **OPay**: Used for manual bank transfers, with a designated OPay account (7043609930 - WebDaddy Empire) for receiving payments.
-   **Alpine.js**: Utilized for front-end interactivity and dynamic UI components, specifically the CSP-compatible build to ensure broad hosting compatibility.