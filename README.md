# WebDaddy Empire - Template & Digital Tools Marketplace

A complete PHP/SQLite platform for selling **professional website templates and digital tools**, bundled with premium domains. Features WhatsApp-based payment processing, comprehensive affiliate marketing system, and an advanced admin management suite with 18 dedicated pages for complete business control.

---

## 🎯 System Overview

WebDaddy Empire is a production-ready, dual-product marketplace where customers can purchase professionally designed **website templates** and **digital tools** that come with pre-configured **premium domain names**. The platform handles the complete sales cycle from browsing to payment via WhatsApp, with sophisticated affiliate tracking, real-time analytics, comprehensive admin tools, and an extensive affiliate portal for partner management.

**Unique Features:**
- Sell templates AND digital tools in one unified marketplace
- Customers can purchase templates, tools, or bundles in a single order
- WhatsApp-first payment processing with manual bank transfer
- Two-step WhatsApp pathways (payment proof + discussion option)
- Real-time affiliate tracking and commission management
- Comprehensive analytics dashboard with multiple views
- Complete audit trail and activity logging
- Responsive design optimized for all devices

---

## 📊 Data Architecture

### Database Tables & Data Storage

The system manages **14 core data entities**:

#### **1. Settings**
- Global site configuration (WhatsApp number, site name, commission rates, affiliate cookie duration, bank account details)
- All settings are database-driven and can be updated via admin panel
- Fallback to config.php constants if database values don't exist
- **Bank Payment Settings (Nov 2025):**
  - `site_account_number`: Business bank account number displayed to customers on order confirmation
  - `site_bank_name`: Bank name (e.g., "Access Bank", "GTBank")
  - `site_bank_number`: Bank code/identification number displayed as "Account Name"

#### **2. Users**
- Stores both admin and affiliate accounts
- Fields: name, email, phone, password (hashed), role (admin/affiliate), bank details (JSON), status (active/inactive/suspended), created_at, updated_at
- Bank details stored as JSON for affiliate withdrawal processing
- Role-based access control for admin vs affiliate privileges

#### **3. Templates**
- Website template catalog with complete product details
- Fields: name, slug, price, category, description, features (multi-line text), demo_url, thumbnail_url, video_url, priority_order, active status, timestamps
- Features stored as newline-separated text for flexible list display
- Slug field for SEO-friendly URLs and sharing
- Priority order controls featured template positioning
- Category field enables browsing and filtering by business type

#### **4. Tools**
- Digital tools/software products marketplace (NEW - Nov 2025)
- Fields: name, slug, tool_type (e.g., "API", "Dashboard", "Automation Tool"), price, category, short_description, description, features (comma-separated), thumbnail_url, demo_url, documentation_url, api_info (JSON), stock_unlimited (boolean), stock_quantity, active status, timestamps
- Similar to templates but supports digital tool attributes
- API information stored as JSON for tools with API access
- Stock management for limited-availability tools
- Tool types help categorize different tool classes

#### **5. Domains**
- Premium domain inventory linked to templates
- Fields: template_id (foreign key), domain_name, status (available/in_use/suspended), assigned_customer_id, assigned_order_id, notes, timestamps
- Each template can have multiple available domains
- Domains get assigned to customers when orders are marked as paid
- In-use domains cannot be deleted; suspended domains are hidden from customers
- Tracks complete domain lifecycle from available → assigned → in-use

#### **6. Pending Orders**
- Customer order submissions before payment confirmation
- Fields: customer_name, customer_email, customer_phone, business_name, custom_fields (JSON), affiliate_code, session_id, whatsapp_message_text, status (pending/paid/cancelled), ip_address, timestamps
- Stores complete order details but no product references (see order_items table)
- WhatsApp message text is pre-generated at order time
- Session tracking for analytics and cart recovery
- IP tracking for security and fraud prevention

#### **7. Order Items** (NEW - Nov 2025)
- Line-item breakdown within orders
- Fields: pending_order_id (foreign key), item_type (template/tool), product_id (template_id or tool_id), domain_id (nullable for tools), quantity, unit_price, item_subtotal, timestamps
- Supports templates, tools, or both in single order
- Enables accurate pricing calculations with multiple items
- Domain assignment tracked per template item
- Quantity support for purchasing multiples

#### **8. Draft Orders** (NEW - Nov 2025)
- Auto-saved cart snapshots for cart recovery
- Fields: ip_address, cart_snapshot (JSON), affiliate_code, created_at, updated_at
- Stores complete cart state including items and affiliate attribution
- Enables users to return to partial orders after browser closes
- IP-based tracking (7-day retention)
- Reduces cart abandonment through automatic recovery

#### **9. Sales**
- Confirmed paid transactions
- Fields: pending_order_id (foreign key), admin_id (who confirmed), amount_paid, commission_amount, affiliate_id (if referred), payment_method, payment_notes, paid_at (timestamp), timestamps
- Created when admin marks an order as paid
- Automatically calculates and records affiliate commissions
- Tracks payment confirmation timestamp for audit trail
- Links to affiliate for commission attribution

#### **10. Affiliates**
- Extended profile for affiliate users
- Fields: user_id (foreign key), unique_code, clicks_count, sales_count, commission_earned (total), commission_pending (available for withdrawal), commission_paid (already withdrawn), custom_commission_rate (nullable), status, timestamps
- Tracks complete affiliate performance metrics in real-time
- Custom commission rate allows per-affiliate profit sharing variation
- Separate tracking of earned/pending/paid commissions

#### **11. Withdrawal Requests**
- Affiliate payout requests and processing
- Fields: affiliate_id (foreign key), amount, bank_details (JSON snapshot), status (pending/approved/rejected/paid), admin_notes, requested_at, processed_at, processed_by_admin_id, timestamps
- Stores complete bank details snapshot at request time
- Amount requested must not exceed pending commission balance
- Status workflow: pending → (approved or rejected) → paid
- Admin notes explain approval or rejection reason

#### **12. Activity Logs**
- Complete audit trail of system actions
- Fields: user_id (nullable for system actions), action_type, description, ip_address, timestamp
- Tracks all major admin and affiliate actions for security audits
- Stores action type (orders_paid, domains_assigned, templates_created, etc.)
- IP tracking for security incident investigation
- Queryable by user, action type, or date range

#### **13. Announcements** (NEW - Nov 2025)
- Important system messages for affiliates
- Fields: title, content, created_by_admin_id, created_at, updated_at
- Admin-created announcements displayed on affiliate dashboard
- Rich content support for communication

#### **14. Announcement Emails** (NEW - Nov 2025)
- Email delivery tracking for bulk announcements
- Fields: announcement_id, affiliate_id, sent_at, read_at
- Tracks which affiliates received which announcements
- Email open tracking capability

---

## 🌐 Public Customer Features

### Template & Tool Browsing & Discovery
- **Template Catalog**: Browse all active templates with pagination
- **Tools Marketplace**: Separate view for digital tools/products
- **Category Filtering**: Browse templates and tools by business category or tool type
- **Search Functionality**: Search templates and tools by name
- **Live Previews**: Each template/tool includes demo URL with iframe preview capability
- **Detail Pages**: Full product pages showing pricing, features, available domains, and benefits
- **Featured Display**: Customizable priority order for homepage prominence
- **Responsive Cards**: Mobile-optimized product display with thumbnails

### Shopping Cart & Wishlist
- **Multi-Product Cart**: Add templates, tools, or both to single cart
- **Persistent Cart**: Auto-save cart across sessions using draft orders table
- **Cart Recovery**: Automatic cart restoration when customer returns
- **Real-Time Calculations**: Instant price updates with affiliate discounts
- **Item Management**: Add/remove/update quantities in cart

### Affiliate Tracking System
- **Cookie-Based Tracking**: 30-day affiliate cookie persistence when visitors arrive via affiliate links
- **Session Tracking**: Affiliate code stored in session for immediate attribution
- **URL Parameter Support**: Accept affiliate codes via `?aff=CODE` parameter
- **Automatic Discount**: 20% discount automatically applied when using affiliate links
- **Discount Display**: Clear visual indication of savings during checkout
- **Attribution Priority**: Session > Cookie > URL parameter for multi-channel tracking

### Order & Payment Flow (Updated Nov 2025)
- **Multi-Step Checkout Form**: Collect customer details (name, email, phone, business name)
- **Product Selection**: Choose templates, tools, or combinations
- **Domain Selection**: Choose from available premium domains for template selections
- **Custom Fields**: Optional custom requirements field for special requests
- **Affiliate Code Input**: Manual affiliate code entry during checkout
- **Price Calculation**: Real-time price updates with affiliate discounts
- **Order Confirmation Page**: Display bank account details upfront, no delays
- **Bank Details Display**: 
  - Account Number (large, monospace font with copy button)
  - Bank Name (clear display)
  - Account Name (clearly labeled)
- **Two WhatsApp Pathways:**
  - **"⚡ I have sent the money"** - Send payment proof message with order details (instant confirmation)
  - **"💬 Discuss more on WhatsApp"** - Alternative discussion pathway for customers with questions
- **Payment Instructions**: Clear 3-step process (send amount, screenshot, send proof)
- **Order Status**: Orders stored as "pending" until admin confirms payment

### Trust & Conversion Elements
- **Money-Back Guarantee**: 30-day refund promise displayed prominently
- **24-Hour Setup**: Fast deployment commitment
- **24/7 Support**: WhatsApp-based customer support
- **Success Metrics**: Display of websites launched and customer satisfaction
- **FAQ Section**: Accordion-style answers to common questions
- **SSL Security**: Secure payment processing indicators

---

## 👑 Admin Panel Features (18 Pages)

### **1. Dashboard & Analytics**
- **Key Metrics Overview**: Templates count, active templates, tools count, pending orders, total sales, revenue, active affiliates, pending withdrawals
- **Recent Orders Display**: Last 5-10 pending orders with quick access
- **Recent Sales**: Recent confirmed transactions
- **Real-Time Data**: All metrics update immediately with database changes
- **Quick Actions**: Shortcuts to common tasks

### **2. Orders Management**
- **Order Queue**: View all orders with status filtering (pending/paid/cancelled)
- **Product Filtering**: Filter by templates, tools, or combined orders
- **Mark as Paid**: Convert pending orders to sales with payment confirmation
- **Payment Details**: Record amount paid, payment method, and notes
- **Domain Assignment**: Assign available domains to paid template orders
- **Commission Calculation**: Automatic affiliate commission calculation on payment
- **Bulk Actions**: Mark multiple orders as paid or cancelled simultaneously
- **Order Cancellation**: Cancel pending orders with status update
- **CSV Export**: Export order data for external reporting
- **Order Details Modal**: View complete order including products, customer, affiliate info
- **Payment Proof Review**: View customer-submitted payment details

### **3. Template Management**
- **CRUD Operations**: Create, read, update, delete templates
- **Template Details**: Name, slug, price, category, description, features list, priority order
- **Media Management**: Upload thumbnail URLs and demo URLs
- **Video Links**: Store video demonstration links
- **Active/Inactive Toggle**: Control template visibility on public site
- **Search & Filter**: Filter by search term, category, and active status
- **Feature Lists**: Multi-line feature input with newline separation
- **Bulk Management**: Edit multiple templates at once

### **4. Tools Management** (NEW - Nov 2025)
- **CRUD Operations**: Create, read, update, delete digital tools
- **Tool Details**: Name, slug, type, price, category, descriptions, features
- **API Configuration**: Store API endpoint info and documentation URLs
- **Stock Management**: Set unlimited stock or quantity limits
- **Media Management**: Thumbnails, demo URLs, documentation links
- **Feature Lists**: Comma-separated features for digital products
- **Active/Inactive Toggle**: Control tool visibility
- **Search & Filter**: Find tools by name, type, category
- **Category Organization**: Manage tool categories

### **5. Domain Management**
- **Single Domain Add**: Add individual domains linked to templates
- **Bulk Import**: Import multiple domains via textarea (one per line)
- **Domain Editing**: Update domain name, template assignment, status, and notes
- **Status Control**: Set domains as available, in_use, or suspended
- **Domain Assignment Tracking**: View which customer/order a domain is assigned to
- **Deletion Protection**: Only available domains can be deleted
- **Search & Filter**: Find domains by name or template

### **6. Affiliate Management**
- **Create Affiliate Accounts**: Generate new affiliate users with unique codes
- **Affiliate Dashboard**: View all affiliates with performance metrics
- **Status Management**: Activate, suspend, or deactivate affiliates
- **Custom Commission Rates**: Set per-affiliate commission overrides (default 30%)
- **Performance Tracking**: View clicks, sales, commissions (pending/earned/paid)
- **Sales History**: Detailed view of each affiliate's referral sales
- **Email Communication**: Send custom emails to individual affiliates
- **View Affiliate Details**: Full profile with bank information
- **Bulk Actions**: Manage multiple affiliates

### **7. Reports & Analytics Dashboard**
- **Time-Based Filtering**: View data by today, week, month, or custom date range
- **Revenue Metrics**: Total revenue, net revenue (after commissions), average order value
- **Commission Tracking**: Total commissions paid, pending, and earned
- **Top Templates**: Best-selling templates by sales count and revenue
- **Top Tools**: Best-selling tools by sales count and revenue
- **Top Affiliates**: Highest-performing affiliates by commission earned
- **Recent Sales**: Chronological list of recent transactions
- **Sales Trends**: Visual charts showing sales over time
- **Monthly Breakdown**: Sales and revenue by month
- **Export Capabilities**: Download reports as CSV/JSON

### **8. Search & Analytics**
- **Search Templates**: Find specific templates by name/category
- **Search Tools**: Find specific tools by type/category
- **View Analytics Data**: Review raw analytics events
- **Filter by Date Range**: Analyze specific time periods
- **Export Analytics**: Download analytics data

### **9. Activity Logs & Audit Trail**
- **Complete Log Viewer**: All system actions logged
- **Action Type Filtering**: Filter by orders_paid, domains_assigned, etc.
- **User Filtering**: See actions by specific admin or affiliate
- **Date Range Filtering**: Query specific time periods
- **IP Address Tracking**: See where actions came from
- **Search Logs**: Find specific entries
- **Export Logs**: Download for external auditing

### **10. Monitoring & System Health**
- **Database Statistics**: Table sizes, row counts
- **System Performance**: Query performance metrics
- **Error Tracking**: Recent errors and issues
- **Activity Rate**: User activity over time
- **Resource Usage**: System resource monitoring
- **Health Indicators**: Overall system status

### **11. Withdrawal Requests Processing**
- **Request Queue**: All withdrawal requests pending approval
- **Approve/Reject**: Process requests with admin notes
- **Amount Validation**: System checks available balance
- **Payment Status**: Track paid/rejected/approved
- **Bulk Processing**: Process multiple withdrawals
- **Note Taking**: Add reason for rejection or approval
- **Export Records**: Download withdrawal history

### **12. Site Settings**
- **WhatsApp Configuration**: Update WhatsApp business number
- **Site Name & Branding**: Change site name and basic info
- **Commission Settings**: Set default affiliate commission rate
- **Cookie Duration**: Configure affiliate cookie persistence (days)
- **Bank Account Details**: Update bank details for manual transfers
  - Account Number
  - Bank Name
  - Account Name (labeled correctly)
- **Email Settings**: SMTP configuration for email sending
- **Security Settings**: Session configuration
- **Settings History**: View setting changes

### **13. Admin Profile Management**
- **Update Profile**: Change name, email, phone
- **Change Password**: Update admin password with current password verification
- **Security Settings**: Configure login preferences
- **Session Management**: View active sessions

### **14. Database Viewer & Management**
- **Read-Only Table Browser**: View any database table
- **JSON Export**: Export table data to JSON format
- **Row Counting**: See total records per table
- **Search in Tables**: Find specific records
- **Schema Viewer**: See database structure
- **Reset Database**: Option to reset to fresh state (with confirmation)

### **15. Admin Login & Authentication**
- **Secure Login**: Username/email and password authentication
- **Session Management**: Secure session persistence
- **Remember Me**: Optional persistent login
- **Password Reset**: Email-based password recovery

### **16. Admin Logout & Session Management**
- **Clean Logout**: Proper session termination
- **Auto-Logout**: Timeout after inactivity

### **17. Support & Help Center** (Admin)
- **FAQ Management**: Create and display FAQs
- **Support Tickets**: Track customer support issues
- **Email Support**: View and respond to support emails
- **Help Documentation**: Links to system documentation

### **18. Announcements** (Admin)
- **Create Announcements**: Post important messages for affiliates
- **Distribution**: Send via email to all or specific affiliates
- **Delivery Tracking**: See which affiliates received announcements
- **Archive**: View past announcements

---

## 💰 Affiliate Portal Features (8 Pages)

### **1. Affiliate Dashboard**
- **Performance Overview**: Total clicks, sales, pending commission, paid commission
- **Recent Sales**: Last 10 referral sales with customer and product details
- **Announcements**: Important updates from admin team
- **Referral Link**: Unique affiliate link with one-click copy functionality
- **Commission Summary**: Visual breakdown of earned vs pending vs paid
- **Quick Stats**: Key performance indicators

### **2. Earnings & Sales Tracking**
- **Complete Sales History**: All referral sales with pagination (20 per page)
- **Monthly Breakdown**: Sales count and commission totals by month (last 12 months)
- **Transaction Details**: Customer name, product purchased, amount, commission
- **Date Tracking**: Payment confirmation timestamps for each sale
- **Summary Cards**: Quick view of total earned, pending, and paid commissions
- **Export History**: Download sales data as CSV

### **3. Withdrawal System**
- **Request Payouts**: Submit withdrawal requests for pending commissions
- **Bank Details Management**: Save bank account information for easy withdrawals
- **Manual Entry Option**: Enter bank details per request if not saved
- **Withdrawal History**: View all past requests with status tracking
- **Status Display**: Pending, approved, rejected, or paid indicators
- **Admin Notes**: View reasons for rejections or approval notes
- **Balance Checking**: System prevents requesting more than available balance
- **Request Confirmation**: Review request details before submission

### **4. Account Settings**
- **Profile Management**: Update name and phone number
- **Bank Information**: Save and update bank account details (bank name, account number, account name)
- **Password Change**: Secure password update with current password verification
- **Account Details**: View affiliate code, email, and registration date
- **Email Preferences**: Configure notification settings
- **Two-Factor Security**: Optional security enhancements

### **5. Promotional Materials & Tools** (Affiliate)
- **Marketing Resources**: Ready-to-use marketing content
- **Social Media Banners**: Pre-designed graphics for sharing
- **Email Templates**: Copy-paste email templates for promotion
- **Link Customization**: Generate referral links with custom parameters
- **Share Tools**: Direct sharing to social platforms
- **Performance Analytics**: See which marketing materials perform best

### **6. Affiliate Registration**
- **Self-Service Signup**: Public registration for new affiliates
- **Custom Code Selection**: Choose unique 4-20 character affiliate code (letters/numbers)
- **Email Validation**: Prevent duplicate email registrations
- **Code Availability Check**: Verify affiliate code isn't already taken
- **Auto-Login**: Automatic login after successful registration
- **Affiliate Tracking**: Inherit any active affiliate cookie during registration
- **Terms & Conditions**: Display affiliate program terms

### **7. Affiliate Login & Authentication**
- **Secure Login**: Email and password authentication
- **Session Management**: Secure session persistence
- **Remember Me**: Optional persistent login
- **Password Reset**: Email-based password recovery

### **8. Affiliate Support & Help Center**
- **FAQ Management**: View frequently asked questions
- **Contact Support**: Message admin for help
- **Email Support**: Support ticket tracking
- **Help Documentation**: Links to affiliate program documentation
- **Video Tutorials**: How-to guides for affiliates

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
- **Real-Time Calculation**: Commission calculated immediately when order marked paid

### Order Processing Flow (Updated Nov 2025)
1. **Customer Submission**: Customer selects templates/tools, enters details, creates pending_order record with order_items
2. **Order Confirmation Page**: Bank details displayed upfront - no delays
3. **Two WhatsApp Options**: Customer chooses payment proof or discussion pathway
4. **Payment Confirmation**: Admin manually confirms payment received
5. **Sale Creation**: System creates sale record, calculates commission
6. **Domain Assignment**: Admin assigns chosen domain to customer (for template items)
7. **Status Updates**: Order status changes to "paid", domain status to "in_use"
8. **Commission Allocation**: Affiliate's pending commission increases
9. **Activity Logging**: All actions logged for audit trail

### Product Assignment Logic
- **Templates**: Come with domain selection during checkout
- **Tools**: Digital products without domain requirement
- **Mixed Orders**: Single order can contain both templates and tools
- **Pricing**: Each item priced independently, affiliate discount applies to total
- **Inventory**: Tools support stock management (unlimited or quantity-limited)

### Domain Assignment Logic
- Domains start as "available" status
- Customer selects preferred domain during template selection
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
7. **Email Notifications**: Affiliate notified of decision

### Activity Logging
- Automatic logging of critical actions
- Tracks: order payments, domain assignments, template changes, tool changes, affiliate actions, withdrawals
- Stores: user ID, action type, description, IP address, timestamp
- Used for security audits and debugging
- Queryable by user, action type, or date range

---

## 🔐 Security Features

### Authentication & Authorization
- **Password Hashing**: BCrypt password hashing via `password_hash()`
- **Session Security**: Secure session configuration with HttpOnly and Secure flags
- **Session Regeneration**: ID regeneration on login to prevent fixation
- **Role-Based Access**: Separate admin and affiliate authentication systems
- **Custom Session Path**: `/tmp/php_sessions` for proper session persistence
- **Session Timeout**: Auto-logout after inactivity

### Data Protection
- **Prepared Statements**: All database queries use PDO prepared statements
- **Input Sanitization**: `sanitizeInput()` function for user-submitted data
- **Email Validation**: Server-side email format validation
- **SQL Injection Prevention**: Parameterized queries throughout
- **XSS Prevention**: HTML escaping on all output via `htmlspecialchars()`
- **CSRF Protection**: Token-based protection on forms

### Access Control
- **Route Protection**: `requireAdmin()` and `requireAffiliate()` guards
- **Status Checking**: Inactive/suspended users cannot log in
- **Direct Access Prevention**: `.htaccess` blocks direct access to includes folder
- **File Type Blocking**: Sensitive files (.env, .sql, .md) blocked via .htaccess
- **IP Tracking**: Monitor and track user IP addresses for security

---

## 📧 Email & Communication

### Email Functionality
- **SMTP Configuration**: PHPMailer with SMTP settings in config
- **Admin Notifications**: Email alerts for new withdrawal requests
- **Affiliate Emails**: Custom email composer for admin-to-affiliate communication
- **Bulk Announcements**: Send same message to all affiliates with delivery tracking
- **HTML Email Support**: Rich formatted emails with templates
- **Email Verification**: Validate email addresses before sending

### WhatsApp Integration (Nov 2025)
- **Two-Pathway System**: 
  - Payment Proof Message: For customers ready to pay (includes bank details)
  - Discussion Message: For customers with questions
- **Pre-Filled Messages**: Auto-generated order details with products, amount, bank info
- **Contact Links**: Click-to-WhatsApp buttons throughout site
- **Support Channel**: 24/7 WhatsApp support links
- **Bank Details in Message**: Account details included in payment proof message
- **Message Formatting**: Bold text, emojis, line breaks for readability
- **URL Encoding**: Proper encoding for reliable WhatsApp link generation

---

## 📊 Analytics & Monitoring

### Page Visit Tracking
- **Automatic Tracking**: All page visits logged to analytics
- **Session Deduplication**: Prevent counting same visitor multiple times per session
- **Referrer Tracking**: Track where visitors came from
- **Page URLs**: Store exact page accessed
- **Timestamps**: Record when visit occurred

### Tool View Analytics
- **Tool Analytics**: Track which tools users view
- **View Counting**: Count views per tool
- **Popular Tools**: Identify most viewed tools
- **Tool Recommendations**: Use view data to recommend tools

### Search Analytics
- **Search Query Tracking**: Log what users search for
- **Popular Searches**: Identify trending searches
- **Search Refinement**: Use data to improve search functionality
- **Trending Topics**: See what customers want

### Affiliate Click Tracking
- **Click Recording**: Each affiliate link click logged
- **Attribution Tracking**: Link clicks to subsequent sales
- **Performance Metrics**: Show affiliates their click-to-conversion rate
- **Real-Time Updates**: Click counts update immediately

### Reports & Analytics Dashboard
- **Revenue Reports**: Total, net, average order value
- **Top Products**: Best-selling templates and tools
- **Top Affiliates**: Highest-earning affiliates
- **Sales Trends**: Visual charts and graphs
- **Time Period Filters**: View data for any date range
- **Export Reports**: Download as CSV or JSON

---

## 🎨 UI/UX Features

### Design System
- **Bootstrap 5**: Complete responsive framework
- **Bootstrap Icons**: Comprehensive icon library
- **Custom CSS**: 800+ lines of custom styling
- **Color Scheme**: Royal Blue (#1e3a8a), Gold (#d4af37), Navy Blue (#0f172a)
- **Unified Navigation**: Matching gradient headers across all sections
- **Professional Cards**: White cards with colored accent borders
- **Gradient Backgrounds**: Branded gradients for key sections

### Responsive Features
- **Mobile Optimization**: 400+ lines of mobile-specific CSS
- **Viewport Configuration**: Proper meta tags on all pages
- **Breakpoint System**: Tablet (≤768px), Mobile (≤576px)
- **Column Stacking**: Proper Bootstrap grid usage for mobile
- **Table Scrolling**: Horizontal scroll for admin tables on mobile
- **Touch-Friendly**: Appropriate button and input sizing
- **Orientation Support**: Landscape and portrait optimizations

### Bank Payment UI (Nov 2025)
- **Bank Details Card**: Large, prominent display on confirmation page
- **Account Number Display**: Monospace font for clarity, copy button
- **Payment Instructions**: Clear 3-step process with icons
- **Two-Button System**: Green (payment proof) + Blue (discussion)
- **Visual Hierarchy**: Bank details above other content
- **Mobile Responsive**: Proper stacking on mobile devices

### Conversion Optimization
- **Above-the-Fold Products**: Products displayed before scroll
- **Compact Hero**: Minimal hero section to surface products quickly
- **Trust Badges**: Money-back guarantee, SSL, support badges
- **Social Proof**: Customer count and satisfaction metrics
- **Clear CTAs**: Prominent action buttons
- **FAQ Accordion**: Address objections before checkout
- **Live Previews**: Reduce uncertainty with product demos

---

## ⚙️ Technical Specifications

### Backend Stack
- **Language**: PHP 8.2+
- **Database**: SQLite with foreign keys and indexes
- **Database Access**: PDO with prepared statements
- **Session Storage**: Custom file-based sessions in `/tmp/php_sessions`
- **Email**: PHPMailer for email functionality
- **Dependency Management**: Composer

### Frontend Stack
- **Framework**: Bootstrap 5.3.2
- **Icons**: Bootstrap Icons 1.11.1
- **JavaScript**: Vanilla JS (no frameworks, minimal bundle)
- **Styling**: Custom CSS with CSS variables
- **Responsive**: Mobile-first design approach

### Database Features
- **ENUM Types**: Typed status fields (role, status, domain_status, order_status, withdrawal_status)
- **Foreign Keys**: Referential integrity with CASCADE and RESTRICT
- **Indexes**: Performance optimization on frequently queried columns
- **Timestamps**: Automatic created_at and updated_at fields
- **JSON Storage**: Bank details, custom fields, API info, cart snapshots stored as JSON
- **Data Validation**: Database constraints for data integrity

### API Endpoints

#### **Customer-Facing APIs**
- `api/cart.php` - Cart operations (add, remove, update)
- `api/cart-autosave.php` - Auto-save cart to draft orders
- `api/restore-cart.php` - Restore saved cart
- `api/draft-orders.php` - Manage draft orders
- `api/search.php` - Search templates and tools
- `api/products/` - Product listing and filtering
- `api/analytics/track-view.php` - Track page/tool views
- `api/analytics/analytics.php` - View analytics data
- `api/analytics-report.php` - Generate analytics reports

#### **Admin APIs**
- `api/monitoring.php` - System monitoring data
- `api/tools.php` - Tool management endpoints
- `api/upload.php` - File upload handling

---

## 📁 File Structure

```
/
├── index.php                    # Public homepage (templates & tools)
├── template.php                 # Template detail pages
├── tool.php                     # Tool detail pages
├── cart-checkout.php            # Checkout flow (updated Nov 2025)
├── order.php                    # Order form (archived/legacy)
├── admin/                       # Admin panel (18 pages)
│   ├── index.php               # Dashboard
│   ├── orders.php              # Order management
│   ├── templates.php           # Template CRUD
│   ├── tools.php               # Tool CRUD (NEW)
│   ├── domains.php             # Domain management
│   ├── affiliates.php          # Affiliate management
│   ├── reports.php             # Sales analytics & reports
│   ├── search_analytics.php    # Search & analytics viewer
│   ├── activity_logs.php       # Audit trail
│   ├── monitoring.php          # System monitoring (NEW)
│   ├── settings.php            # Site configuration
│   ├── profile.php             # Admin profile
│   ├── database.php            # Database viewer
│   ├── support.php             # Support/Help center
│   ├── reset-database.php      # Database reset
│   ├── login.php               # Admin authentication
│   ├── logout.php              # Admin logout
│   ├── includes/               # Admin headers/footers/auth
│   └── includes/auth.php       # Admin auth checks
├── affiliate/                   # Affiliate portal (8 pages)
│   ├── index.php               # Affiliate dashboard
│   ├── earnings.php            # Sales history
│   ├── withdrawals.php         # Payout requests
│   ├── settings.php            # Profile & bank details
│   ├── tools.php               # Promotional materials (NEW)
│   ├── support.php             # Support center
│   ├── register.php            # Affiliate signup
│   ├── login.php               # Affiliate authentication
│   ├── logout.php              # Affiliate logout
│   └── includes/               # Affiliate headers/footers/auth
├── api/                        # API endpoints
│   ├── cart.php               # Cart operations
│   ├── cart-autosave.php      # Cart auto-save
│   ├── draft-orders.php       # Draft order management
│   ├── search.php             # Search functionality
│   ├── tools.php              # Tool APIs
│   ├── upload.php             # File uploads
│   ├── monitoring.php         # System monitoring
│   ├── analytics/             # Analytics endpoints
│   │   ├── analytics.php      # Analytics data
│   │   ├── track-view.php     # Track page views
│   │   └── analytics-report.php
│   └── ajax-products.php      # AJAX product operations
├── includes/                   # Shared PHP backend
│   ├── config.php             # Configuration constants
│   ├── db.php                 # Database connection
│   ├── session.php            # Session management
│   ├── functions.php          # Business logic functions (600+ lines)
│   ├── tools.php              # Tool-specific functions (NEW)
│   ├── analytics.php          # Analytics functions
│   ├── cart.php               # Cart functions
│   └── mailer.php             # Email functionality
├── assets/                     # Static files
│   ├── css/style.css          # Custom styling (800+ lines)
│   └── images/                # Logos, placeholders, template images
├── database/
│   └── schema_sqlite.sql      # SQLite schema with sample data
├── mailer/                     # PHPMailer library
├── uploads/                    # User uploads
│   ├── templates/images/      # Template thumbnails
│   ├── templates/videos/      # Template demos
│   ├── tools/images/          # Tool thumbnails (NEW)
│   ├── tools/videos/          # Tool demos (NEW)
│   └── temp/                  # Temporary files
├── logs/                       # System logs
├── cache/                      # Caching directory
├── PAYMENT_FLOW_IMPLEMENTATION.md  # Payment system docs (Nov 2025)
├── README.md                   # This file
└── replit.md                   # Project configuration
```

---

## 🔄 Current System State

### Sample Data Included
- **1 Admin User**: admin@example.com / admin123
- **11+ Sample Templates**: Across various business categories
- **5+ Sample Tools**: Different tool types and categories
- **44+ Sample Domains**: Distributed across templates
- **System Settings**: Default WhatsApp, commission rates, site name
- **Bank Details**: Pre-configured for payment flow demo

### Active Features
- ✅ Complete order-to-sale workflow
- ✅ Template and tool marketplace (dual product support)
- ✅ Multi-item cart with bundling
- ✅ Cart auto-save and recovery
- ✅ Affiliate tracking and commission system
- ✅ Domain inventory management
- ✅ WhatsApp payment integration with bank details
- ✅ Two-button WhatsApp pathways
- ✅ Admin analytics and reporting
- ✅ Withdrawal request processing
- ✅ Activity logging for security
- ✅ Mobile-responsive design
- ✅ Email notification system
- ✅ Database-driven settings
- ✅ Tool management and inventory
- ✅ Advanced admin panel (18 pages)
- ✅ Comprehensive affiliate portal (8 pages)

### Production-Ready Elements
- ✅ Security: Prepared statements, password hashing, input sanitization
- ✅ Session management: Secure, persistent sessions
- ✅ Error handling: Try-catch blocks, graceful failures
- ✅ Data validation: Server-side validation on all forms
- ✅ Audit trail: Complete activity logging
- ✅ Role separation: Admin vs affiliate access control
- ✅ Mobile optimization: Fully responsive across devices
- ✅ Performance: Database indexes on key columns
- ✅ Payment flow: Manual bank transfer with proof verification
- ✅ API coverage: 15+ endpoints for various operations

---

## 🚀 Quick Start

### Admin Access
- **URL**: `/admin/login.php`
- **Email**: admin@example.com
- **Password**: admin123
- **⚠️ Change password immediately in production!**

### Bank Payment Configuration (Admin)
1. Go to `/admin/settings.php`
2. Enter:
   - Account Number
   - Bank Name
   - Account Name (displays as third field)
3. Save - customers will see these on confirmation page

### Affiliate Registration
- **URL**: `/affiliate/register.php`
- **Self-service signup available**
- **Choose custom affiliate code**

### Customer Flow
1. Browse templates at `/` (or view=templates)
2. Browse tools at `/?view=tools`
3. Select products and add to cart
4. Checkout - enter customer details
5. View order confirmation with bank details
6. Choose WhatsApp pathway (payment proof or discussion)
7. Send payment via WhatsApp
8. Admin confirms payment and assigns domains
9. Customer receives login credentials

---

## 💡 Key Business Numbers

- **Affiliate Commission**: 30% (customizable per affiliate)
- **Customer Discount**: 20% with affiliate link
- **Cookie Duration**: 30 days
- **Setup Time**: 24 hours promised
- **Support**: 24/7 via WhatsApp
- **Product Types**: Templates + Tools (dual marketplace)
- **Admin Pages**: 18 dedicated pages for complete control
- **Affiliate Pages**: 8 pages for partner management
- **Database Tables**: 14 tables for comprehensive data tracking

---

## 📝 Recent Updates (November 2025)

### Payment Flow Enhancement
- **Bank Details Display**: Added upfront bank account details on order confirmation page
- **Two WhatsApp Options**: 
  - Payment proof pathway (instant confirmation)
  - Discussion pathway (for hesitant customers)
- **Admin Settings**: Bank account number, bank name, account name configuration
- **Label Correction**: Third field labeled "Account Name" instead of "Bank Code"
- **Message Templates**: Two separate WhatsApp message types with proper formatting

### System Expansion
- **Tools Marketplace**: Added digital tools/software products alongside templates
- **Order Items**: Introduced order_items table for flexible multi-product orders
- **Draft Orders**: Auto-save cart snapshots for cart recovery
- **Announcements System**: Admin can post updates for affiliates
- **Analytics Enhancement**: Track page visits, tool views, searches
- **Monitoring Page**: Real-time system health and performance
- **Support Pages**: Dedicated support section for admins and affiliates

---

**WebDaddy Empire** – A production-ready, dual-product marketplace (templates + tools) with built-in affiliate marketing, WhatsApp-first payment processing with manual bank transfer, comprehensive admin suite (18 pages), and extensive affiliate portal (8 pages).
