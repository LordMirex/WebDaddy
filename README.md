# WebDaddy Empire - Template Marketplace

A complete PHP/PostgreSQL platform for selling website templates bundled with premium domains, featuring WhatsApp-based payment processing, affiliate marketing system, and comprehensive admin management.

---

## 🎯 System Overview

WebDaddy Empire is a production-ready marketplace where customers can purchase professionally designed website templates that come with pre-configured premium domain names. The platform handles the complete sales cycle from browsing to payment via WhatsApp, with built-in affiliate tracking and comprehensive admin tools.

---

## 📊 Data Architecture

### Database Tables & Data Storage

The system manages 9 core data entities:

#### **1. Settings**
- Global site configuration (WhatsApp number, site name, commission rates, affiliate cookie duration)
- All settings are database-driven and can be updated via admin panel
- Fallback to config.php constants if database values don't exist

#### **2. Users**
- Stores both admin and affiliate accounts
- Fields: name, email, phone, password (hashed), role (admin/affiliate), bank details (JSON), status (active/inactive/suspended)
- Bank details stored as JSON for affiliate withdrawal processing

#### **3. Templates**
- Website template catalog with full details
- Fields: name, slug, price, category, description, features (multi-line), demo URL, thumbnail URL, video links, active status
- Features stored as newline-separated text
- Controls what templates are displayed to customers

#### **4. Affiliates**
- Extended profile for affiliate users
- Fields: user ID reference, unique code, click tracking, sales count, commission amounts (earned/pending/paid), custom commission rate, status
- Tracks complete affiliate performance metrics

#### **5. Domains**
- Premium domain inventory linked to templates
- Fields: template ID, domain name, status (available/in_use/suspended), assigned customer ID, assigned order ID, notes
- Each template can have multiple available domains
- Domains get assigned to customers when orders are marked as paid

#### **6. Pending Orders**
- Customer order submissions before payment confirmation
- Fields: template ID, chosen domain ID, customer details (name/email/phone), business name, custom fields (JSON), affiliate code, session ID, WhatsApp message text, status (pending/paid/cancelled), IP address
- Stores the complete order flow from form submission to payment

#### **7. Sales**
- Confirmed paid transactions
- Fields: pending order ID reference, admin ID (who confirmed), amount paid, commission amount, affiliate ID (if referred), payment method, payment notes, payment confirmation timestamp
- Created when admin marks an order as paid
- Automatically calculates and records affiliate commissions

#### **8. Withdrawal Requests**
- Affiliate payout requests
- Fields: affiliate ID, amount, bank details (JSON snapshot), status (pending/approved/rejected/paid), admin notes, request timestamp, processing timestamp, processed by admin ID
- Stores bank details snapshot at time of request

#### **9. Activity Logs**
- Complete audit trail of system actions
- Fields: user ID, action type, description, IP address, timestamp
- Tracks all major admin and affiliate actions for security and debugging

---

## 🌐 Public Customer Features

### Template Browsing & Discovery
- **Template Catalog**: Browse up to 10 featured templates on homepage (limit for conversion optimization)
- **Live Previews**: Each template includes demo URL with iframe preview capability
- **Template Details**: Full template pages showing pricing, features, available domains, and benefits
- **Category Filtering**: Templates organized by business category
- **Responsive Cards**: Mobile-optimized template display with thumbnail images

### Affiliate Tracking System
- **Cookie-Based Tracking**: 30-day affiliate cookie persistence when visitors arrive via affiliate links
- **Session Tracking**: Affiliate code stored in session for immediate attribution
- **URL Parameter Support**: Accept affiliate codes via `?aff=CODE` parameter
- **Automatic Discount**: 20% discount automatically applied when using affiliate links
- **Discount Display**: Clear visual indication of savings during checkout

### Order & Payment Flow
- **Multi-Step Form**: Collect customer details (name, email, phone, business name)
- **Domain Selection**: Choose from available premium domains for selected template
- **Custom Fields**: Optional custom requirements field for special requests
- **Affiliate Code Input**: Manual affiliate code entry during checkout
- **Price Calculation**: Real-time price updates with affiliate discounts
- **WhatsApp Redirect**: Generate pre-filled WhatsApp message with order details
- **Order Confirmation**: Orders stored as "pending" until admin confirms payment

### Trust & Conversion Elements
- **Money-Back Guarantee**: 30-day refund promise displayed prominently
- **24-Hour Setup**: Fast deployment commitment
- **24/7 Support**: WhatsApp-based customer support
- **Success Metrics**: Display of websites launched and customer satisfaction
- **FAQ Section**: Accordion-style answers to common questions
- **SSL Security**: Secure payment processing badges

---

## 👑 Admin Panel Features

### Dashboard & Analytics
- **Key Metrics Overview**: Total templates, active templates, pending orders, total sales, revenue, active affiliates, pending withdrawals
- **Recent Orders Display**: Last 5 pending orders with quick access
- **Real-Time Data**: All metrics update immediately with database changes

### Order Management
- **Order Queue**: View all orders with status filtering (pending/paid/cancelled)
- **Template Filtering**: Filter orders by specific template
- **Mark as Paid**: Convert pending orders to sales with payment confirmation
- **Payment Details**: Record amount paid, payment method, and notes
- **Domain Assignment**: Assign available domains to paid orders
- **Commission Calculation**: Automatic affiliate commission calculation on payment
- **Bulk Actions**: Mark multiple orders as paid or cancelled simultaneously
- **Order Cancellation**: Cancel pending orders with status update
- **CSV Export**: Export order data for external reporting
- **Order Details Modal**: View complete order information including customer details and affiliate info

### Template Management
- **CRUD Operations**: Create, read, update, delete templates
- **Template Details**: Name, slug, price, category, description, features list
- **Media Management**: Upload thumbnail URLs and demo URLs
- **Video Links**: Store video demonstration links
- **Active/Inactive Toggle**: Control template visibility on public site
- **Search & Filter**: Filter by search term, category, and active status
- **Feature Lists**: Multi-line feature input with newline separation

### Domain Management
- **Single Domain Add**: Add individual domains linked to templates
- **Bulk Import**: Import multiple domains via textarea (one per line)
- **Domain Editing**: Update domain name, template assignment, status, and notes
- **Status Control**: Set domains as available, in_use, or suspended
- **Domain Assignment Tracking**: View which customer/order a domain is assigned to
- **Deletion Protection**: Only available domains can be deleted
- **Search & Filter**: Find domains by name or template

### Affiliate Program Management
- **Create Affiliate Accounts**: Generate new affiliate users with unique codes
- **Affiliate Dashboard**: View all affiliates with performance metrics
- **Status Management**: Activate, suspend, or deactivate affiliates
- **Custom Commission Rates**: Set per-affiliate commission overrides (default 30%)
- **Performance Tracking**: View clicks, sales, commissions (pending/earned/paid)
- **Sales History**: Detailed view of each affiliate's referral sales
- **Email Communication**: Send custom emails to individual affiliates
- **Bulk Announcements**: Email all affiliates with important updates
- **Withdrawal Processing**: Review and approve/reject payout requests
- **Payment Notes**: Add admin notes to withdrawal decisions

### Reports & Analytics
- **Time-Based Filtering**: View data by today, week, month, or custom date range
- **Revenue Metrics**: Total revenue, net revenue (after commissions), average order value
- **Commission Tracking**: Total commissions paid to affiliates
- **Top Templates**: Best-selling templates by sales count and revenue
- **Top Affiliates**: Highest-performing affiliates by commission earned
- **Recent Sales**: Chronological list of recent transactions
- **Sales Trends**: Visual charts showing sales over time
- **Monthly Breakdown**: Sales and revenue by month

### System Administration
- **Activity Logs**: Complete audit trail of system actions
- **Log Filtering**: Filter by action type, user, or date
- **User Actions**: Track who did what and when
- **Database Viewer**: Read-only view of all database tables
- **JSON Export**: Export table data to JSON format
- **Site Settings**: Update WhatsApp number, site name, commission rates, cookie duration
- **Admin Profile**: Update own profile details and password
- **Session Management**: Secure login/logout with session regeneration

---

## 💰 Affiliate Portal Features

### Affiliate Dashboard
- **Performance Overview**: Total clicks, sales, pending commission, paid commission
- **Recent Sales**: Last 10 referral sales with customer and template details
- **Announcements**: Important updates from admin team
- **Referral Link**: Unique affiliate link with one-click copy functionality
- **Commission Summary**: Visual breakdown of earned vs pending vs paid

### Earnings & Sales Tracking
- **Complete Sales History**: All referral sales with pagination (20 per page)
- **Monthly Breakdown**: Sales count and commission totals by month (last 12 months)
- **Transaction Details**: Customer name, template purchased, amount, commission
- **Date Tracking**: Payment confirmation timestamps for each sale
- **Summary Cards**: Quick view of total earned, pending, and paid commissions

### Withdrawal System
- **Request Payouts**: Submit withdrawal requests for pending commissions
- **Bank Details Management**: Save bank account information for easy withdrawals
- **Manual Entry Option**: Enter bank details per request if not saved
- **Withdrawal History**: View all past requests with status tracking
- **Status Display**: Pending, approved, rejected, or paid indicators
- **Admin Notes**: View reasons for rejections or approval notes
- **Balance Checking**: System prevents requesting more than available balance

### Account Settings
- **Profile Management**: Update name and phone number
- **Bank Information**: Save and update bank account details (bank name, account number, account name)
- **Password Change**: Secure password update with current password verification
- **Account Details**: View affiliate code, email, and registration date

### Affiliate Registration
- **Self-Service Signup**: Public registration for new affiliates
- **Custom Code Selection**: Choose unique 4-20 character affiliate code (letters/numbers)
- **Email Validation**: Prevent duplicate email registrations
- **Code Availability Check**: Verify affiliate code isn't already taken
- **Auto-Login**: Automatic login after successful registration
- **Affiliate Tracking**: Inherit any active affiliate cookie during registration

---

## 🔄 Business Logic & Workflows

### Affiliate Commission System
- **Default Commission**: 30% of sale price (configurable per affiliate)
- **Customer Discount**: 20% discount for customers using affiliate links
- **Cookie Duration**: 30-day tracking window (configurable)
- **Attribution Priority**: Session > Cookie > URL parameter
- **Commission Types**:
  - **Earned**: Total all-time commissions from sales
  - **Pending**: Available for withdrawal (not yet requested)
  - **Paid**: Successfully withdrawn amounts

### Order Processing Flow
1. **Customer Submission**: Order form creates pending_order record
2. **WhatsApp Communication**: Customer contacts business via WhatsApp with pre-filled message
3. **Payment Confirmation**: Admin manually confirms payment received
4. **Sale Creation**: System creates sale record, calculates commission
5. **Domain Assignment**: Admin assigns chosen domain to customer
6. **Status Updates**: Order status changes to "paid", domain status to "in_use"
7. **Commission Allocation**: Affiliate's pending commission increases
8. **Activity Logging**: All actions logged for audit trail

### Domain Assignment Logic
- Domains start as "available" status
- Customer selects preferred domain during order
- Domain remains available until order is paid
- When order marked paid, domain becomes "in_use"
- Domain linked to specific customer and order
- In-use domains cannot be deleted
- Admins can suspend problematic domains

### Withdrawal Request Flow
1. **Affiliate Requests**: Submit amount and bank details
2. **Balance Validation**: System checks available pending commission
3. **Admin Review**: Admin sees request in withdrawal queue
4. **Approval/Rejection**: Admin decides with optional notes
5. **Payment Processing**: If approved, admin marks as paid (external payment)
6. **Commission Deduction**: Pending commission decreases, paid commission increases
7. **Email Notifications**: Admin notified of new requests

### Activity Logging
- Automatic logging of critical actions
- Tracks: order payments, domain assignments, template changes, affiliate actions, withdrawals
- Stores: user ID, action type, description, IP address, timestamp
- Used for security audits and debugging

---

## 🔐 Security Features

### Authentication & Authorization
- **Password Hashing**: BCrypt password hashing via `password_hash()`
- **Session Security**: Secure session configuration with HttpOnly and Secure flags
- **Session Regeneration**: ID regeneration on login to prevent fixation
- **Role-Based Access**: Separate admin and affiliate authentication systems
- **Custom Session Path**: `/tmp/php_sessions` for proper session persistence

### Data Protection
- **Prepared Statements**: All database queries use PDO prepared statements
- **Input Sanitization**: `sanitizeInput()` function for user-submitted data
- **Email Validation**: Server-side email format validation
- **SQL Injection Prevention**: Parameterized queries throughout
- **XSS Prevention**: HTML escaping on all output via `htmlspecialchars()`

### Access Control
- **Route Protection**: `requireAdmin()` and `requireAffiliate()` guards
- **Status Checking**: Inactive/suspended users cannot log in
- **Direct Access Prevention**: `.htaccess` blocks direct access to includes folder
- **File Type Blocking**: Sensitive files (.env, .sql, .md) blocked via .htaccess

---

## 📧 Email & Communication

### Email Functionality
- **SMTP Configuration**: PHPMailer with SMTP settings in config
- **Admin Notifications**: Email alerts for new withdrawal requests
- **Affiliate Emails**: Custom email composer for admin-to-affiliate communication
- **Bulk Announcements**: Send same message to all affiliates
- **HTML Email Support**: Rich formatted emails with templates

### WhatsApp Integration
- **Payment Processing**: WhatsApp-first manual payment confirmation
- **Pre-Filled Messages**: Auto-generated order details message
- **Contact Links**: Click-to-WhatsApp buttons throughout site
- **Support Channel**: 24/7 WhatsApp support links
- **Order Details**: Customer name, template, domain, price included in message

---

## 🎨 UI/UX Features

### Design System
- **Bootstrap 5**: Complete responsive framework
- **Bootstrap Icons**: Comprehensive icon library
- **Custom CSS**: 500+ lines of custom styling
- **Color Scheme**: Royal Blue (#1e3a8a), Gold (#d4af37), Navy Blue (#0f172a)
- **Unified Navigation**: Matching gradient headers across all sections
- **Professional Cards**: White cards with colored accent borders
- **Mobile-First**: Comprehensive responsive design with breakpoints

### Responsive Features
- **Mobile Optimization**: 400+ lines of mobile-specific CSS
- **Viewport Configuration**: Proper meta tags on all pages
- **Breakpoint System**: Tablet (≤768px), Mobile (≤576px)
- **Column Stacking**: Proper Bootstrap grid usage for mobile
- **Table Scrolling**: Horizontal scroll for admin tables on mobile
- **Touch-Friendly**: Appropriate button and input sizing
- **Orientation Support**: Landscape and portrait optimizations

### Conversion Optimization
- **Above-the-Fold Templates**: Templates displayed before scroll
- **Compact Hero**: Minimal hero section to surface templates quickly
- **Trust Badges**: Money-back guarantee, SSL, support badges
- **Social Proof**: Customer count and satisfaction metrics
- **Clear CTAs**: Prominent "Order Now" buttons
- **FAQ Accordion**: Address objections before checkout
- **Live Previews**: Reduce uncertainty with template demos

---

## ⚙️ Technical Specifications

### Backend Stack
- **Language**: PHP 8.2+
- **Database**: PostgreSQL 15 with foreign keys, enums, indexes
- **Database Access**: PDO with prepared statements
- **Session Storage**: Custom file-based sessions in `/tmp/php_sessions`

### Frontend Stack
- **Framework**: Bootstrap 5.3.2
- **Icons**: Bootstrap Icons 1.11.1
- **JavaScript**: Vanilla JS (no frameworks)
- **Styling**: Custom CSS with CSS variables

### Database Features
- **ENUM Types**: Typed status fields (role, status, domain_status, order_status, withdrawal_status)
- **Foreign Keys**: Referential integrity with CASCADE and RESTRICT
- **Indexes**: Performance optimization on frequently queried columns
- **Timestamps**: Automatic created_at and updated_at fields
- **JSON Storage**: Bank details and custom fields stored as JSON

### Configuration
- **Environment Variables**: PostgreSQL credentials from Replit environment
- **Config File**: `includes/config.php` for all hardcoded settings
- **Database Settings**: Settings table overrides config constants
- **Flexible Setup**: Works with both environment and manual configuration

---

## 📁 File Structure

```
/
├── index.php                    # Public homepage
├── template.php                 # Template detail pages
├── order.php                    # Order form with WhatsApp redirect
├── admin/                       # Admin panel (17 pages)
│   ├── index.php               # Dashboard
│   ├── orders.php              # Order management
│   ├── templates.php           # Template CRUD
│   ├── domains.php             # Domain management
│   ├── bulk_import_domains.php # Bulk domain import
│   ├── affiliates.php          # Affiliate management
│   ├── email_affiliate.php     # Email composer
│   ├── reports.php             # Sales analytics
│   ├── activity_logs.php       # Audit trail
│   ├── settings.php            # Site configuration
│   ├── profile.php             # Admin profile
│   ├── database.php            # Database viewer
│   ├── login.php               # Admin authentication
│   └── includes/               # Admin headers/footers/auth
├── affiliate/                   # Affiliate portal (8 pages)
│   ├── index.php               # Affiliate dashboard
│   ├── earnings.php            # Sales history
│   ├── withdrawals.php         # Payout requests
│   ├── settings.php            # Profile & bank details
│   ├── tools.php               # Promotional materials
│   ├── register.php            # Affiliate signup
│   ├── login.php               # Affiliate authentication
│   └── includes/               # Affiliate headers/footers/auth
├── includes/                    # Shared PHP backend
│   ├── config.php              # Configuration constants
│   ├── db.php                  # Database connection
│   ├── session.php             # Session management
│   ├── functions.php           # Business logic functions
│   └── mailer.php              # Email functionality
├── assets/                      # Static files
│   ├── css/style.css           # Custom styling (800+ lines)
│   └── images/                 # Logos, placeholders, template images
├── database/
│   └── schema.sql              # PostgreSQL schema with sample data
└── mailer/                      # PHPMailer library
```

---

## 🔄 Current System State

### Sample Data Included
- **1 Admin User**: admin@example.com / admin123
- **11 Sample Templates**: Across various business categories
- **44 Sample Domains**: Distributed across templates
- **System Settings**: Default WhatsApp, commission rates, site name

### Active Features
- ✅ Complete order-to-sale workflow
- ✅ Affiliate tracking and commission system
- ✅ Domain inventory management
- ✅ WhatsApp payment integration
- ✅ Admin analytics and reporting
- ✅ Withdrawal request processing
- ✅ Activity logging for security
- ✅ Mobile-responsive design
- ✅ Email notification system
- ✅ Database-driven settings

### Production-Ready Elements
- ✅ Security: Prepared statements, password hashing, input sanitization
- ✅ Session management: Secure, persistent sessions
- ✅ Error handling: Try-catch blocks, graceful failures
- ✅ Data validation: Server-side validation on all forms
- ✅ Audit trail: Complete activity logging
- ✅ Role separation: Admin vs affiliate access control
- ✅ Mobile optimization: Fully responsive across devices
- ✅ Performance: Database indexes on key columns

---

## 🚀 Quick Start

### Admin Access
- **URL**: `/admin/login.php`
- **Email**: admin@example.com
- **Password**: admin123
- **Change password immediately in production!**

### Affiliate Registration
- **URL**: `/affiliate/register.php`
- **Self-service signup available**
- **Choose custom affiliate code**

### Customer Flow
1. Browse templates at `/`
2. Click template for details
3. Submit order form
4. Contact via WhatsApp
5. Admin confirms payment
6. Receive login credentials

---

## 💡 Key Business Numbers

- **Affiliate Commission**: 30% (customizable per affiliate)
- **Customer Discount**: 20% with affiliate link
- **Cookie Duration**: 30 days
- **Setup Time**: 24 hours promised
- **Template Limit**: 10 on homepage (conversion optimization)
- **Support**: 24/7 via WhatsApp

---

**WebDaddy Empire** – A complete template marketplace with built-in affiliate marketing and WhatsApp-first payment processing.
