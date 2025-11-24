# WebDaddy Empire - Template Marketplace

## Overview

WebDaddy Empire is a production-ready PHP/SQLite marketplace platform for selling website templates bundled with premium domain names. The system features WhatsApp-based payment processing, an affiliate marketing program with commission tracking, and comprehensive admin management. Customers can browse templates, select premium domains, submit orders, and complete payments via WhatsApp, while affiliates earn commissions on successful sales.

## User Preferences

Preferred communication style: Simple, everyday language.

**CRITICAL DESIGN STANDARDS:**
- ALL UI/UX designs must be polished, professional, and visually clean
- NEVER implement fast/ugly designs - quality UI is non-negotiable for this website
- ALWAYS match existing design patterns (example: template.php social sharing section)
- ALWAYS center-align text properly and remove cluttered/awkward icon placements
- ALWAYS use proper spacing, shadows, hover effects, and responsive sizing
- ALWAYS test designs on mobile before delivery (user focuses on mobile experience)
- When in doubt about design, copy the exact pattern from proven pages (template.php, index.php)

## System Architecture

### Frontend Architecture

**Technology Stack:**
- Vanilla JavaScript (no framework dependencies)
- Bootstrap for UI components
- Custom CSS with Tailwind-inspired utility classes
- Progressive enhancement approach

**Key Design Patterns:**
- **Lazy Loading System**: Images and videos load on-demand using IntersectionObserver API to optimize initial page load
- **Performance Optimization**: RequestAnimationFrame-based scroll handling, debouncing, and throttling for smooth animations
- **AJAX-Based Navigation**: Dynamic content loading for templates and tools without full page refreshes
- **Client-Side State Management**: Shopping cart persists in localStorage, maintains state across sessions
- **Video Modal System**: Lazy-loaded video player with autoplay, buffering states, and user instructions
- **Image Cropper**: Client-side image manipulation for uploads with aspect ratio control and zoom functionality

**Rationale**: Vanilla JavaScript chosen over frameworks to minimize bundle size and maintain full control over performance. Progressive enhancement ensures functionality on older browsers while providing enhanced experience on modern ones.

### Backend Architecture

**Core Technology:**
- **PHP 8.2+**: Modern PHP with strict typing support
- **SQLite Database**: File-based database for simplified deployment and portability
- **Composer**: Dependency management (PHPMailer for email functionality)

**Architecture Pattern:**
- **Procedural PHP with Modular Organization**: Not MVC framework-based, but organized into logical modules
- **Database-Driven Configuration**: Settings stored in database with fallback to PHP constants
- **Session-Based Authentication**: Role-based access control (admin/affiliate)
- **API Endpoints**: RESTful-style endpoints for AJAX operations (analytics tracking, cart operations)

**Data Storage:**
- **9 Core Database Tables**: Users, Templates, Domains, Pending Orders, Affiliates, Analytics, Settings, Tools, and related tracking tables
- **JSON Fields**: Used for flexible data (custom form fields, bank details) stored as JSON within SQLite
- **File Uploads**: Organized directory structure (`uploads/templates/`, `uploads/tools/`) with security measures

**Settings Configuration (November 2025 Payment Flow Update):**
- Settings stored in flexible key-value format in SQLite `settings` table
- Core settings: `whatsapp_number`, `site_name`, `commission_rate`, `affiliate_cookie_days`
- **New bank payment settings:**
  - `site_account_number`: Customer receives this for manual bank transfers
  - `site_bank_name`: Bank name displayed on order confirmation page
  - `site_bank_number`: Bank code for customer reference
- Admin can configure all settings via `/admin/settings.php` panel

**Security Measures:**
- Password hashing for user authentication
- PHP execution disabled in upload directories
- Input validation and sanitization
- Session-based access control
- CSRF protection patterns

**Rationale**: SQLite chosen for ease of deployment and no separate database server requirement. Suitable for small-to-medium traffic. Can be migrated to MySQL/PostgreSQL for high-traffic scenarios.

### Payment Processing Architecture

**WhatsApp-Based Flow (Updated November 2025):**
1. Customer submits order form with template and domain selection
2. Customer confirms and proceeds to order confirmation page
3. **NEW:** Confirmation page displays business bank account details (account number, bank name, bank code)
4. Customer manually transfers exact order amount to business account
5. Customer sends payment proof via WhatsApp using one of two options:
   - "⚡ I have sent the money" - Sends payment proof message with order details (instant confirmation)
   - "💬 Discuss more on WhatsApp" - Alternative discussion pathway for customers with questions
6. Admin reviews payment proof and marks order as paid
7. Domain automatically assigned to customer upon payment confirmation

**Rationale**: Improved flow eliminates delays - bank details shown upfront, no back-and-forth for account info. Customers make transfer immediately, send proof via WhatsApp. Faster payment confirmation, higher conversion. WhatsApp chosen for markets where informal payment methods are common, reduces gateway fees, provides direct communication channel.

### Affiliate Marketing System

**Commission Tracking:**
- **Cookie-Based Attribution**: Affiliate codes stored in browser cookies with configurable duration
- **Click and Conversion Tracking**: Database records affiliate clicks and completed sales
- **Multi-Tier Commission**: Global rate with per-affiliate override capability
- **Status Management**: Pending → Earned → Paid commission workflow

**Analytics Architecture:**
- Template click tracking
- Tool interaction tracking
- Affiliate performance metrics
- Session-based deduplication

**Rationale**: Cookie-based tracking balances simplicity with reasonable attribution accuracy. Session tracking prevents duplicate analytics entries.

### File Upload System

**Upload Organization:**
```
uploads/
├── templates/images/    # Template thumbnails
├── templates/videos/    # Template demos
├── tools/images/        # Tool thumbnails  
├── tools/videos/        # Tool demos
└── temp/                # Auto-cleaned temporary files
```

**Security Implementation:**
- File type validation (images: JPG, PNG, GIF, WebP; videos: MP4, WebM, MOV, AVI)
- SVG blocked to prevent XSS attacks
- Unique filename generation with timestamps and random strings
- Size limits: 50MB images, 10MB videos
- `.htaccess` rules block PHP execution in upload directories

**Rationale**: Organized structure separates concerns, security measures prevent common upload vulnerabilities, temp directory cleanup prevents storage bloat.

### Performance Optimization Strategy

**Frontend:**
- Lazy loading reduces initial payload
- RequestAnimationFrame prevents layout thrashing
- Video preloading on fast connections only
- Connection speed detection for adaptive loading
- Debounced/throttled event handlers
- Link prefetching for instant navigation

**Backend (November 2025 Optimizations):**
- **Database Indexes:** 8 performance indexes on templates/tools tables for instant filtering by active status, category, and stock
- **Query Optimization:** Minimal database queries through efficient schema design, no ORM overhead (direct SQLite PDO access)
- **HTTP Caching:** All API responses cached (60-300 seconds) for instant repeated navigation
- **Gzip Compression:** All JSON responses compressed, reducing transfer size by 60-70%
- **Lean API Responses:** Only essential fields returned (id, name, category, price, thumbnail_url, demo_url) - removed large description fields
- **Pagination:** SQL LIMIT clauses instead of loading all records into memory
- **Session-based caching:** Optimized for varying cache lifetimes

**Performance Metrics:**
- API response time: ~48ms
- Homepage load: Fast even on slow connections
- Template ↔ Tools navigation: Instant with caching
- Video streaming: Smooth buffering with progress indication

**Rationale**: Performance-first approach targets users on varying connection speeds and devices. Progressive enhancement ensures baseline functionality while optimizing for modern browsers. Database indexes and HTTP caching eliminate the bottlenecks that cause sluggishness during navigation.

### SEO Optimization (November 2025)

**Social Media Sharing:**
- **Custom OG Image**: Professional 1920×1080 banner (`assets/images/og-image.png`) for social media previews
- **Open Graph Tags**: Complete OG meta tags on all pages (homepage, templates, tools) with proper dimensions and alt text
- **Twitter Cards**: Summary large image cards for rich Twitter/X previews
- **Smart Fallback Logic**: Product pages prioritize item-specific thumbnails, falling back to branded banner only when missing

**Structured Data (Schema.org):**
- **Homepage**: Organization and WebSite schema with search action support
- **Templates**: Product schema with pricing, availability, and category information
- **Tools**: Product schema with stock status and delivery information
- **Consistent Fallbacks**: Schema.org image fields use same fallback logic as Open Graph

**URL Structure & Routing:**
- **Template Pages**: Clean URLs via slug (`/template-name`)
- **Tool Pages**: Namespaced URLs (`/tool/tool-name`)
- **Canonical Tags**: All pages include proper canonical URLs
- **301 Redirects**: ID-based URLs redirect to slug-based URLs for SEO

**Search Engine Optimization:**
- **Dynamic Sitemap**: `/sitemap.xml` auto-generates from database, includes all templates and tools
- **Dynamic Robots.txt**: `/robots.txt` served via PHP for environment-aware sitemap URL
- **Meta Tags**: Comprehensive title, description, keywords, author, and robots meta tags
- **Image Optimization**: All social images include width, height, alt text, and type attributes

**Implementation Details:**
- `sitemap.php`: Dynamically generates XML sitemap including all active templates and tools
- `robots.php`: Serves robots.txt with correct SITE_URL for sitemap reference
- `router.php`: Handles SEO-friendly URL routing for templates (`/slug`) and tools (`/tool/slug`)
- `tool.php`: New dedicated detail page for tools with full SEO optimization

**Rationale**: Comprehensive SEO approach ensures strong social media presence, search engine discoverability, and rich preview snippets. Product-specific images improve click-through rates while maintaining brand consistency through smart fallbacks.

## External Dependencies

### Third-Party Libraries

**PHPMailer (v6.9+)**
- Purpose: Email notifications and communications
- Used for: Order confirmations, affiliate notifications, admin alerts
- Integration: Composer-managed, autoloaded via PSR-4

### Database

**SQLite 3**
- File-based relational database
- Storage: Single `.db` file in `/database/` directory
- No separate server process required
- Built-in to PHP via PDO extension

**Migration Path**: Schema designed to be compatible with MySQL/PostgreSQL for future scaling needs.

### External Services

**WhatsApp Business API (Informal)**
- Integration: Deep links (`wa.me` URLs) with pre-filled messages
- No API key required
- One-way communication initiation from platform

**Potential Future Integrations:**
- Payment gateways (Stripe, PayPal) for automated payment processing
- Email service providers (SendGrid, Mailgun) for transactional emails
- CDN for static asset delivery
- Analytics platforms (Google Analytics) for enhanced tracking

### Browser APIs

**Required:**
- LocalStorage: Shopping cart persistence
- IntersectionObserver: Lazy loading (with fallback for older browsers)
- Fetch API: AJAX requests

**Optional Enhancement:**
- Navigator.connection: Network speed detection
- RequestAnimationFrame: Smooth animations
- File API: Client-side image cropping

### Development Dependencies

**Composer Packages:**
- `phpmailer/phpmailer`: Production email sending
- Development tools managed separately from production dependencies

**Asset Management:**
- No build process required
- Static JS/CSS served directly
- No transpilation or bundling

**Rationale**: Minimal external dependencies reduce maintenance burden and deployment complexity. SQLite eliminates database server requirement. Vanilla JavaScript avoids framework lock-in and npm dependency hell.