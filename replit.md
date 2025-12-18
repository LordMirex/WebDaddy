# WebDaddy Empire - Marketplace Platform

## Overview
WebDaddy Empire is a PHP/SQLite marketplace platform for selling website templates, premium domains, and digital tools. It features a dual payment system (manual bank transfer and Paystack), a 30% commission affiliate program, and secure encrypted delivery of digital assets. The platform prioritizes high reliability, data integrity, and seamless operations, focusing on system monitoring and security to achieve significant market potential in the digital products market. A comprehensive, enterprise-level blog system is also planned to enhance content marketing and SEO.

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

## Recent Changes (Dec 18, 2025)
- **FINAL FIX: Instant Cart Display (ZERO Delay)** - Eliminated the 3-second empty cart message:
  - Changed initial cart HTML from "Your cart is empty" to "Loading your cart..."
  - Cart now ALWAYS shows cached products first (if available) - no waiting
  - Removed 2-second age check - displays ANY cached data immediately
  - Added IMMEDIATE preload trigger (fires as soon as script loads, not waiting for page load)
  - Background refresh happens after cached display (seamless update)
  - Result: Products show INSTANTLY when opening cart, no "empty" delay
- **COMPLETE: Cart Pre-Loading on ALL Public Pages** - Cart products now display instantly before clicking:
  - Added `setupCartDrawer()` initialization to all public pages: index, about, contact, faq, careers, cart-checkout, blog pages
  - Cart drawer setup + real-time badge polling (5-second intervals)
  - Automatic cart data pre-loading on page load (background, non-blocking)
  - Cached data displays instantly when user opens cart (within 2 seconds)
  - NO "empty cart" message - products show immediately
- **OPTIMIZATION: Instant Cart Loading** - Pre-loads cart data on page load for zero-delay display:
  - Added global `cartDataCache` system to store cart items/totals
  - Cart data auto-loads in background immediately when page loads (via `window.load` event)
  - When user clicks cart icon, previously loaded data displays instantly (no "empty cart" message)
  - Fresh data refreshes in background if older than 2 seconds
  - Applies to all pages: index, about, contact, faq, careers, template, tool, cart-checkout, blog pages
- **CRITICAL FIX: Blog Cart System** - Fixed critical cart functionality on blog pages:
  - Removed bogus `toggleCartDrawer()` function override in blog pages that redirected to home instead of opening cart
  - Added `cart-and-tools.js` script to all blog pages (index, post, category)
  - Implemented real-time badge counter updates: cart badge now polls every 5 seconds across all pages
- **CRITICAL FIX: Cart Display Bug** - Fixed API response inconsistency where POST endpoints returned `cart_count` but JavaScript expected `count`, causing cart to show 0 products after adding items. All POST responses (add/update/remove/clear) now consistently use `count` field matching GET responses.
- Added **pricing.php** - Templates and tools pricing with 3 pricing tiers
- Added **faq.php** - Dedicated FAQ page with 12+ questions, schema.org FAQPage markup
- Added **security.php** - Security, privacy, and trust certifications page
- Added **careers.php** - Careers page with job listings and team benefits
- **Reorganized Footer** - Professional 5-column layout (Brand, Products, Support, Company, Contact)
- Footer responsive: 5 columns desktop → 2 columns tablet → 1 column mobile
- Updated header navigation to include: Templates, Tools, Pricing, FAQ, Blog, About, Contact
- Updated mobile navigation menu with all pages (grouped by category)
- Enhanced SEO schema with comprehensive SiteNavigationElement markup
- All 10+ pages properly integrated and tested - fully linked throughout site

## System Architecture

### UI/UX Decisions
The platform features a clean, professional UI with consistent design elements, including a vibrant luxury gold and navy color palette. Admin dashboards provide real-time updates and clear visualizations. A premium page loader with a centered logo, glowing animations, and aggressive image preloading enhances the user experience. The index page includes a portfolio image slider and enhanced mobile responsiveness.

### Centralized Navigation Architecture
Shared navigation components in `includes/layout/` provide consistent header and footer across all public pages:
- **header.php**: Premium nav with Tailwind CSS, gold/navy styling, SEO SiteNavigationElement schema, mobile hamburger menu (Alpine.js), cart icon, customer login state, and affiliate tracking. Accepts parameters: `$activeNav`, `$affiliateCode`, `$cartCount`, `$showCart`. Navigation includes: Templates, Tools, Blog, About, Contact, FAQ, Affiliate Program.
- **footer.php**: Premium footer with Organization schema, social links, legal links, WhatsApp CTA, and optional mobile sticky CTA. Accepts parameters: `$affiliateCode`, `$showMobileCTA`. Footer links: Templates, Tools, Blog, About, Contact, FAQ, Affiliate.
- Public pages using shared components: index.php, about.php, contact.php, blog/index.php, blog/post.php, blog/category.php
- Cart button on blog pages redirects to templates (since blog doesn't have full cart drawer markup).

### Technical Implementations
The system includes a production-grade chunked file upload system (up to 2GB) with retry logic, manifest tracking, and duplicate prevention. Template delivery uses AES-256-GCM encryption with dynamic assignment. Tools delivery supports ZIP bundles, configurable download link expiry, and regeneration. An idempotent 30% commission affiliate system is implemented, alongside a customer referral program offering 30% commission and a 20% customer discount. Security features include CSRF token validation, secure token generation, file existence validation, download limits, and enterprise-grade webhook security. Payment processing ensures idempotency. Order completion locks prevent file modifications. A comprehensive version control and enhanced HTML email system handles notifications. Admin-managed bonus codes are supported. A payment verification recovery system includes a frontend API, `PaymentManager` with session storage backup, and a recovery modal. A bulletproof delivery system incorporates a state machine, SLA tracking, auto-recovery, and self-service APIs. Customer accounts feature robust authentication, a full user dashboard with order management, downloads, and support tickets, and extensive customer-facing API endpoints. Infrastructure improvements include caching, a job queue for background processing, a centralized error logger, and an automated backup manager. User announcements and bulk email campaigns are managed via an admin interface. Customer verification uses an email-only OTP system via Resend API.

### Feature Specifications
- **Order Management**: Enhanced filters for payment method, date range, and delivery status.
- **Delivery Management**: Comprehensive admin dashboards for tracking deliveries, including retry mechanisms.
- **Analytics & Reporting**: Admin dashboard with delivery KPIs, overdue alerts, CSV export, and real-time logs.
- **Commission Management**: Automated calculation, reconciliation tools, and audit trails for both affiliates and user referrals.
- **User Campaign Management**: Admin system for posting announcements and sending bulk emails to users.
- **Blog System**: Block-based content system with 8 block types (hero, rich_text, divider, visual, inline_conversion, internal_authority, faq_seo, final_conversion). SEO-first design with Article, FAQPage, and BreadcrumbList schemas. 107 posts, 555+ strategic internal links for topic clustering. Centralized navigation via shared header.php/footer.php components.

### System Design Choices
The platform utilizes SQLite for its database, with a schema designed for robust tracking of orders, deliveries, downloads, commissions, user referrals, and user campaigns. Key parameters are defined as constants. Data integrity functions maintain consistency. All datetime queries consistently use a `+1 hour` offset for Nigeria time. The authentication flow is simplified to 3 steps: Email + OTP Verification, Username + Password + WhatsApp Number, and Success. All customer verification is email-only via Resend API.

## External Dependencies
- **Paystack**: Integrated for automatic payment processing and webhooks.
- **PHP ZipArchive Extension**: Used for generating tool bundles.
- **Resend**: Integrated for fast, reliable email delivery for user-facing emails (OTP, notifications, deliveries) with webhook tracking.
- **SMTP**: Used for admin-only internal emails.