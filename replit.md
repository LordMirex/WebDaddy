# WebDaddy Empire - E-Commerce Platform

## Overview
WebDaddy Empire is a sophisticated PHP-based e-commerce platform for selling website templates and digital tools to African entrepreneurs. The platform includes customer dashboards, affiliate programs, admin panels, and integration with Paystack for payments.

## Current Status (Dec 22, 2025)
✅ **FIXED** - Routing issue resolved. Website now accessible.

## Recent Fix
- **Issue**: 500 Internal Server Error when accessing `/user/order-detail.php?id=21`
- **Root Cause**: PHP development server's router.php was incorrectly handling URL-encoded query strings, causing 404 errors
- **Solution**: Removed router.php and configured PHP built-in server to serve files directly with `php -S 0.0.0.0:5000`
- **Result**: Site now loads properly at http://localhost:5000

## Project Structure
```
├── index.php - Main homepage
├── admin/ - Admin dashboard
├── affiliate/ - Affiliate program dashboard
├── user/ - Customer dashboard (orders, downloads, support tickets)
├── api/ - Backend API endpoints
├── blog/ - Blog system with posts, categories, tags
├── includes/ - Core functions and utilities
├── uploads/ - Media files and user-generated content
├── database/ - SQLite database
└── assets/ - CSS, JS, images
```

## Workflow Configuration
**Current**: `php -S 0.0.0.0:5000` (direct file serving, no router)
- Binds to port 5000 for Replit web preview
- Serves all PHP files directly
- No complex routing rules

## Known Issues to Address (Next Steps)
1. **Tailwind CSS CDN** - Should use PostCSS/Tailwind CLI for production
2. **Alpine.js Collapse Plugin** - x-collapse directive needs plugin installation
3. **CSP Violations** - Paystack stylesheet not whitelisted in Content Security Policy
4. **Service Worker Issues** - Some fetch requests failing (likely auth-related)
5. **Analytics Foreign Key Errors** - Old error logs show database constraint violations (Nov 2025)

## User Authentication
- Customer authentication via `/user/login.php`
- Admin authentication via `/admin/login.php`
- Affiliate authentication via `/affiliate/login.php`
- Session management via customer_session.php

## Database
- SQLite database at `./database/webdaddy.db`
- Tables include: customers, pending_orders, order_items, deliveries, tools, templates, payments
- Backup available at `./database/backups/`

## Key Features
- Order management system
- Digital product delivery (templates & tools)
- Affiliate program with commission tracking
- Customer support tickets
- Blog with analytics
- Admin analytics dashboard
- Service worker for offline functionality
