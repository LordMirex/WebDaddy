# Template Marketplace - Production System

## Overview
A production-ready PHP/MySQL template marketplace with WhatsApp-first order flow, admin panel, and affiliate tracking system. Built for celebrity affiliate campaigns with manual payment processing via WhatsApp.

## Project Status
**Current Phase:** Phase 1 - Ready for Development  
**Last Updated:** October 26, 2025

## Tech Stack
- **Backend:** Plain PHP 7.4+ (no frameworks)
- **Database:** MySQL 5.7+ with mysqli
- **Frontend:** Bootstrap 5 (CDN), Vanilla JavaScript
- **Email:** PHPMailer with SMTP
- **Hosting:** cPanel compatible

## Architecture

### Folder Structure
```
/
├── public/          # Public-facing storefront
├── admin/           # Admin management panel
├── affiliate/       # Affiliate dashboard
├── includes/        # Shared PHP (config, functions, db)
├── assets/          # CSS, JS, images
└── database/        # SQL schema and migrations
```

### Database Tables
1. `users` - Admin and affiliate accounts
2. `templates` - Website template catalog
3. `domains` - Pre-purchased domain inventory
4. `pending_orders` - Customer orders awaiting payment
5. `sales` - Completed transactions
6. `affiliates` - Affiliate tracking and commissions
7. `withdrawal_requests` - Affiliate payout requests
8. `activity_logs` - Audit trail

## Key Features

### Public Features
- Template listing with live iframe demos
- Affiliate tracking via ?aff=CODE (30-day persistence)
- Order form with domain selection
- WhatsApp redirect for payment (wa.me)

### Admin Features
- Secure login with password_hash()
- Template CRUD operations
- Domain inventory management
- Order processing (mark paid, assign domain)
- Affiliate management
- CSV exports

### Affiliate Features
- Login dashboard
- Earnings and commission tracking (30% automatic)
- Withdrawal request submission

## Business Rules
- **Commission:** 30% of template price for official affiliates
- **Affiliate Persistence:** 30 days via session + cookie
- **Order Flow:** Form → Pending → WhatsApp → Admin Confirms → Mark Paid → Assign Domain → Email Credentials
- **Domain Assignment:** Immediate; domains marked in_use disappear from public selection

## Security Measures
- Prepared statements for SQL injection prevention
- password_hash/verify for authentication
- Session regeneration on login
- HttpOnly + Secure cookies
- HTTPS enforcement
- Input sanitization with htmlspecialchars()
- XSS protection on all outputs

## User Preferences
- Code style: PSR-12 compliant, 4 spaces, camelCase variables
- No frameworks (Laravel, React) - plain PHP only
- Mobile-first Bootstrap UI
- GitHub-style neutral color palette
- Clean, production-ready code with security focus

## Recent Changes
- 2025-10-26: Project successfully imported to Replit environment
- 2025-10-26: PostgreSQL database configured and schema initialized
- 2025-10-26: Configuration file created with environment variables
- 2025-10-26: Server workflow started and verified working
- 2025-10-25: Project initialization, folder structure created
- 2025-10-25: Configuration files and .gitignore setup

## Documentation
All requirements specified across 16 professional documents in `attached_assets/`:
1. Executive Summary & Project Charter
2. Glossary and Acronyms
3. Product Requirements (PRD)
4. Functional Requirements (FRS)
5. Non-Functional Requirements (NFRS)
6. System Architecture
7. Database Design & DDL
8. UI Design & Wireframes
9. Integration Design (WhatsApp, PHPMailer)
10. Developer Implementation Plan
11. Code Standards & Guidelines
12. Testing Plan & QA Checklist
13. Acceptance Test Plan
14. Deployment Guide (cPanel)
15. Operations & Maintenance Guide
16. Risks, Assumptions, Dependencies

## Setup Instructions

### Local Development
1. Copy `includes/config.php.example` to `includes/config.php`
2. Update database credentials in `includes/config.php`
3. Import `database/schema.sql` into MySQL
4. Configure SMTP settings for email
5. Set WhatsApp number in config

### Production (cPanel)
1. Upload files to public_html/
2. Create MySQL database via cPanel
3. Import schema via phpMyAdmin
4. Configure includes/config.php with production values
5. Ensure PHP 7.4+ is active
6. Enable SSL certificate (HTTPS required)

## Next Steps
- Complete database schema creation
- Build core functions (includes/functions.php)
- Implement public storefront
- Build admin panel
- Add affiliate portal
- Integrate PHPMailer
- QA testing per checklist
