# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a PHP/SQLite marketplace platform for selling website templates, premium domains, and digital tools. It features a dual payment system (manual bank transfer and Paystack), a 30% commission affiliate program, and secure encrypted delivery of digital assets. The platform prioritizes high reliability, data integrity, and seamless operations, focusing on system monitoring and security to achieve significant market potential in the digital products market.

## User Preferences
- No mock data in production paths
- All credentials encrypted before database storage
- Hardcoded configuration (no environment variables)
- Detailed error messages surfaced to admin
- Clean, professional UI consistent with existing design
- Real-time updates where possible
- Systematic testing with automated verification
- Clear profit breakdown on all admin pages
- No duplicate or confusing card displays
- Proper spacing on admin pages for pagination visibility

## System Architecture

### UI/UX Decisions
The platform features a clean, professional UI with consistent design elements, including a vibrant luxury gold and navy color palette. Admin dashboards provide real-time updates and clear visualizations. A premium page loader with a centered logo, glowing animations, and aggressive image preloading enhances the user experience. The index page includes a portfolio image slider and enhanced mobile responsiveness.

### Technical Implementations
The system includes a production-grade chunked file upload system (up to 2GB) with retry logic, manifest tracking, and duplicate prevention. It supports various file types for digital products. Template delivery uses AES-256-GCM encryption with dynamic assignment and an admin delivery dashboard. Tools delivery supports ZIP bundles, configurable download link expiry, and regeneration. An idempotent 30% commission affiliate system is implemented. Security features include CSRF token validation, secure token generation, file existence validation, download limits, and enterprise-grade webhook security. Payment processing ensures idempotency to prevent race conditions. Order completion locks prevent file modifications unless unmarked by an admin. A comprehensive version control email system notifies customers of updates. An enhanced, professional HTML email system with a priority-based queue handles notifications. Admin-managed bonus codes with CRUD operations, usage tracking, and auto-expiration are supported. A payment verification recovery system includes a frontend API, `PaymentManager` with session storage backup, and a recovery modal. The system also includes an affiliate withdrawal balance fix, bulk action corrections for admin orders, and enhanced priority featured products. A bulletproof delivery system incorporates a state machine, SLA tracking, auto-recovery, and self-service APIs. Customer account features include a robust authentication system, a full user dashboard with order management, downloads, and support tickets, and an extensive set of customer-facing API endpoints. Infrastructure improvements include a caching system, a job queue for background processing, a centralized error logger, and an automated backup manager. Affiliate enhancements cover real-time statistics and fraud detection.

### Feature Specifications
- **Order Management**: Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management**: Comprehensive admin dashboards for tracking deliveries, including retry mechanisms.
- **Analytics & Reporting**: Admin dashboard with delivery KPIs, overdue alerts, CSV export, and real-time logs.
- **Commission Management**: Automated calculation, reconciliation tools, and audit trails.

### System Design Choices
The platform utilizes SQLite for its database, with a schema designed for robust tracking of orders, deliveries, downloads, and commissions. Key parameters are defined as constants. Data integrity functions maintain consistency between cached affiliate data and sales records. All datetime queries consistently use a `+1 hour` offset for Nigeria time.

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Email Service**: Utilized for sending various system notifications.
- **Termii**: Integrated for SMS OTP service for customer verification and notifications.