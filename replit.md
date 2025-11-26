# WebDaddy Empire - Marketplace Platform

## Project Overview
Production-ready PHP/SQLite marketplace for selling website templates bundled with premium domains and digital tools. Features dual payment methods (Manual bank transfer + Paystack automatic), affiliate marketing with 30% commission, encrypted template credential delivery, and comprehensive admin management.

## Current Status: ✅ PHASES 3, 4 & 5 COMPLETE

### Phase 1 & 2 (Template Delivery) - COMPLETED
- Template credentials system with AES-256-GCM encryption
- Admin workflow checklist with delivery progress tracking
- Beautiful credential delivery emails
- Enhanced order filters (payment method, date range, delivery status)
- Dynamic template assignment workflow

### Phase 3 (Tools Delivery Optimization) - ✅ COMPLETED
- **3.1**: Download link expiry extended to 30 days (configurable via DOWNLOAD_LINK_EXPIRY_DAYS constant)
- **3.2**: Admin ability to regenerate expired/exhausted download links with CSRF protection
- **3.3**: ZIP bundle downloads for multiple files at once with README metadata
- **3.4**: Enhanced tool delivery email with file sizes, tips, and clear expiry dates
- **3.5**: Automatic retry mechanism with exponential backoff (up to 3 attempts, configurable)
- **3.6**: Download analytics tracking (per tool, per customer, patterns)

**Key Files:**
- `includes/tool_files.php` - ZIP generation, download link management
- `download.php` - Enhanced with bundle download support and error pages
- `includes/delivery.php` - Tool delivery email with bundle links
- Database migrations: `010_add_bundle_downloads.sql`

### Phase 4 (Templates Delivery Complete) - ✅ COMPLETED
- **4.1**: Enhanced template assignment workflow UI in admin/orders.php
- **4.2**: Dynamic hosting-type credential form (WordPress/cPanel/Custom/Static)
- **4.3**: Comprehensive admin delivery dashboard with filters, sorting, overdue alerts
- **4.4**: Template credential update mechanism for already-delivered templates
- **4.5**: Admin alert system for undelivered templates (24h+) with callable function

**Key Files:**
- `admin/orders.php` - Enhanced with credential update for delivered templates
- `admin/deliveries.php` - Full dashboard with filters, statistics, overdue alerts
- `includes/delivery.php` - getOverdueTemplateDeliveries(), sendOverdueTemplateAlert() functions

### Phase 5 (Mixed Orders & Analytics) - ✅ COMPLETED
- **5.1**: Mixed Order Delivery Coordination - Clear UI split between immediate (tools) and pending (templates)
- **5.2**: Partial Delivery Tracking - getOrderDeliveryStats(), updateOrderDeliveryStatus(), getOrdersWithPartialDelivery()
- **5.3**: Batch Template Assignment - Quick form to assign domains/credentials to ALL templates in one order
- **5.4**: Delivery Email Sequence - sendMixedOrderDeliverySummaryEmail(), recordEmailEvent(), getOrderEmailSequence()
- **5.7**: Delivery Analytics Dashboard - Enhanced admin/analytics.php with delivery KPIs and overdue alerts
- **5.8**: Customer Communication - Automatic email timeline tracking for order lifecycle
- **5.10**: Export & Reporting - CSV export for orders, deliveries, affiliates with date filtering

**Key Files:**
- `admin/analytics.php` - Delivery statistics grid with overdue alerts, avg fulfillment time
- `admin/export.php` - CSV export for orders, items, deliveries, affiliates, download analytics, finance
- `includes/functions.php` - markOrderPaid() with email event recording and mixed order summary
- `includes/delivery.php` - Enhanced with email tracking, partial delivery functions

## Architecture & Implementation Details

### Database Schema Enhancements
```sql
-- Added to download_tokens table:
- is_bundle INTEGER DEFAULT 0 (flags bundle vs individual downloads)

-- New bundle_downloads table:
CREATE TABLE bundle_downloads (
    id INTEGER PRIMARY KEY,
    token_id INTEGER NOT NULL,
    tool_id INTEGER NOT NULL,
    order_id INTEGER NOT NULL,
    zip_path TEXT NOT NULL,
    zip_name TEXT NOT NULL,
    file_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- Added to deliveries table:
- retry_count INTEGER DEFAULT 0
- next_retry_at TEXT NULL
```

### Key Functions Implemented

**Tool Files (`includes/tool_files.php`):**
- `formatFileSize($bytes)` - Human-readable file sizes (B, KB, MB, GB, TB)
- `getDownloadTokens($orderId, $toolId)` - Retrieve download links for admin UI
- `regenerateDownloadLink($tokenId, $newExpiryDays)` - Create new link for expired/exhausted tokens
- `generateToolZipBundle($orderId, $toolId)` - Create ZIP with all tool files + README
- `generateBundleDownloadToken($orderId, $toolId, $expiryDays)` - Secure token for bundle download
- `getBundleByToken($token)` - Verify and retrieve bundle info at download time

**Delivery System (`includes/delivery.php`):**
- `sendToolDeliveryEmail($order, $item, $downloadLinks, $orderId)` - Enhanced with bundle option
- `resendToolDeliveryEmail($deliveryId)` - Resend with updated links
- `getOverdueTemplateDeliveries($hoursOverdue)` - Find pending templates > 24h old
- `sendOverdueTemplateAlert()` - Email admin about overdue deliveries

**Admin Orders (`admin/orders.php`):**
- Action: `regenerate_download_link` - AJAX-friendly link regeneration with CSRF protection
- Action: `save_template_credentials` - Update delivered template credentials and optionally resend
- Action: `resend_tool_email` - Resend tool delivery email to customer
- UI: Tool Downloads & Delivery section with link status, regenerate buttons, copy functionality
- UI: Delivered Templates section with hidden "Update Credentials" form for credential updates

**Admin Deliveries (`admin/deliveries.php`):**
- Real-time dashboard showing pending (5), retrying, failed, and completed deliveries
- Filter by: Product Type (Template/Tool), Status, Time Period (24h/7d/30d/all)
- Overdue Templates Alert - prominently displays templates pending >24h with hours overdue
- Quick action buttons linking directly to order pages
- Summary cards with counts and clickable links to filtered views
- Separate template and tool delivery panels with quick preview

### Configuration Constants
```php
define('DOWNLOAD_LINK_EXPIRY_DAYS', 30);        // Configurable expiry period
define('MAX_DOWNLOAD_ATTEMPTS', 10);             // Download limit per link
define('DELIVERY_RETRY_MAX_ATTEMPTS', 3);        // Maximum retry attempts
define('DELIVERY_RETRY_BASE_DELAY_SECONDS', 60); // Base delay for exponential backoff
```

### Security Features
- CSRF token validation on all admin actions
- AES-256-GCM encryption for template passwords
- Secure token generation (32 bytes of random data)
- File existence validation before download
- Download limit enforcement
- Expiry time validation

### Email Enhancements
- Professional HTML templates with gradients and icons
- File list with sizes and type indicators
- Clear expiry countdown (30 days)
- Bundle download button (when multiple files exist)
- Tips section for customers
- Support contact information
- Retry notifications

## Testing & Verification ✅

All systems verified working:
- ✅ Database connectivity and schema
- ✅ All tool_files functions callable and executable
- ✅ All delivery functions callable and executable
- ✅ Session and CSRF functions available
- ✅ Encryption/decryption functions operational
- ✅ Action handlers in place (regenerate, update, resend)
- ✅ PHP syntax valid (no errors)
- ✅ Server running smoothly

## Deployment Notes

1. **Cron Job Recommended:**
   ```bash
   # Run every hour to process delivery retries
   0 * * * * php /path/to/process_delivery_retries.php
   ```
   Create `process_delivery_retries.php` with:
   ```php
   require_once '/path/to/includes/config.php';
   require_once '/path/to/includes/delivery.php';
   processDeliveryRetries();
   ```

2. **ZIP Handling:**
   - Ensure `/uploads/tools/bundles/` directory is writable
   - PHP ZipArchive extension required
   - Bundles automatically cleaned after 30 days (optional via cleanup script)

3. **Email Configuration:**
   - Verify WHATSAPP_NUMBER, SUPPORT_EMAIL constants set
   - Test sendOverdueTemplateAlert() function in production

4. **Performance:**
   - Download tokens table has indexes on: (file_id), (pending_order_id), (token)
   - Bundle table has indexes on: (token_id), (order_id)
   - Deliveries table has index on: (delivery_status, next_retry_at)

## User Preferences
- No mock data in production paths
- All credentials encrypted before database storage
- Detailed error messages surfaced to admin
- Clean, professional UI consistent with existing design
- Real-time updates where possible

## Recent Changes (Session)
- Added missing `getDownloadTokens()` function to `tool_files.php`
- Enhanced admin/orders.php with credential update UI for delivered templates
- Implemented comprehensive admin/deliveries.php dashboard
- Added overdue template alert system (24h+ pending)
- Database migration 010 applied (bundle_downloads table)
- All functions tested and verified operational
- Phase 5.7 Analytics Dashboard added to admin/analytics.php
- Phase 5.10 Export & Reporting implemented at admin/export.php
- **Migration files consolidated:** All 10 migration files applied and removed. Current schema exported to `database/schema_sqlite.sql`
- **Consolidated schema file** now contains complete database structure with all 25 tables and indexes
