# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a production-ready PHP/SQLite marketplace for selling website templates, premium domains, and digital tools. It features a robust dual payment system (manual bank transfer and Paystack), an affiliate marketing program with a 30% commission, secure encrypted template credential delivery, and comprehensive admin management. The platform is designed for high reliability and data integrity, ensuring seamless operations for both customers and administrators.

## Current Status
✅ **COMPREHENSIVE SYSTEM AUDIT & FIXES COMPLETE (Nov 26)**
- All financial pages display **"YOUR ACTUAL PROFIT"** with clear breakdown
- Dashboard now includes Quick Analytics Access Hub (5-card navigation)
- Analytics page fixed: loads at top instead of jumping to pagination
- Database integrity verified: all orphan records removed, NULL values fixed
- All systems operational: deliveries, analytics, affiliates, email queue verified
- System cache cleared and logs reset for fresh start

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

## System Architecture

### UI/UX Decisions
The platform features a clean, professional UI with consistent design elements. Admin dashboards provide real-time updates and clear visualizations for delivery statuses, commission tracking, and analytics.

### Technical Implementations
- **Template Delivery:** Implements AES-256-GCM encryption for credentials, a dynamic assignment workflow, and an admin delivery dashboard with overdue alerts.
- **Tools Delivery:** Supports ZIP bundle downloads, configurable download link expiry (30 days), and admin regeneration of expired links with CSRF protection. Includes enhanced email notifications with file details.
- **Mixed Orders:** Handles partial deliveries for orders containing both immediate (tools) and pending (templates) items, with clear UI separation and automated email sequences.
- **Affiliate System:** Features a 30% commission rate, with a unified commission processor for both Paystack and manual payments. The system ensures idempotency to prevent duplicate commission credits and uses the `sales` table as the single source of truth.
- **Security:** CSRF token validation on all admin actions, secure token generation, file existence validation, and download limit enforcement.

### Feature Specifications
- **Order Management:** Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management:** Comprehensive admin dashboards for both template and tool deliveries, with tracking, retry mechanisms (exponential backoff), and email notifications.
- **Analytics & Reporting:** Admin analytics dashboard with delivery KPIs, overdue alerts, and CSV export functionality for orders, deliveries, and affiliates.
- **Commission Management:** Automated commission calculation and crediting, reconciliation tools to prevent data discrepancies, and an audit trail for all commission transactions.

### System Design Choices
- **Database:** SQLite with a schema designed for robust tracking of orders, deliveries, downloads, and commissions.
    - `download_tokens`: `is_bundle` flag.
    - `bundle_downloads`: Stores ZIP bundle metadata.
    - `deliveries`: `retry_count`, `next_retry_at` for delivery retries.
    - `commission_log`: Audit trail for commission actions.
    - `commission_withdrawals`: Tracks affiliate withdrawal requests.
- **Configuration:** Key parameters like `DOWNLOAD_LINK_EXPIRY_DAYS`, `MAX_DOWNLOAD_ATTEMPTS`, `DELIVERY_RETRY_MAX_ATTEMPTS`, and `AFFILIATE_COMMISSION_RATE` are defined as constants.
- **Data Integrity:** Employs safeguarding functions like `syncAffiliateCommissions()` and `reconcileAffiliateBalance()` to maintain consistency between cached affiliate data and the `sales` table, which acts as the immutable source of truth.

## External Dependencies
- **Paystack:** Integrated for automatic payment processing.
- **PHP ZipArchive Extension:** Required for generating tool bundles.
- **Email Service:** Utilized for sending various notifications (delivery, overdue alerts, order summaries).