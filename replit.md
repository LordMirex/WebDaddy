# WebDaddy Empire - Affiliate Platform

## Project Overview
A complete affiliate marketing platform for selling website templates with domains included. Built with PHP 8.x and SQLite for easy deployment on cPanel environments.

## Recent Updates (November 2025)

### Latest Changes (November 8, 2025) - Session 4
1. **Fixed Instant Search Functionality**:
   - Removed page reload issues that were causing 404 errors
   - Search now works instantly as users type (300ms debounce)
   - No more page navigation - results appear immediately on the same page
   - Added `.prevent` to button click to stop form submission
   - Search button now triggers immediate search without delay
   
2. **New Search Analytics Dashboard** (`/admin/search_analytics.php`):
   - View all user searches in real-time
   - See top search terms with frequency counts
   - Track zero-result searches (to identify missing templates)
   - Average results per search metric
   - Export search data to CSV
   - Recent searches log (last 100)
   - Filter by period: Today, 7 days, 30 days, 90 days, All time
   
3. **Search Tracking Improvements**:
   - Already tracking what users search for in `page_interactions` table
   - Search terms stored with result counts
   - Admin can now see what customers want but can't find

### Previous Changes (November 7, 2025) - Session 3
1. **Instant AJAX Search** (NEW):
   - Search now works without page reload
   - 300ms debounce for smooth user experience
   - Real-time results display with loading indicator
   - XSS-safe implementation using DOM manipulation
   - Event delegation for dynamic demo buttons
   - Preserves affiliate codes during search

2. **Security Hardening**:
   - Fixed DOM XSS vulnerability in search results display
   - Eliminated inline onclick handlers (XSS risk)
   - Added HTML escaping for all template fields
   - Used data attributes + addEventListener for safe event handling
   - Production-ready and security-reviewed

3. **Documentation Cleanup**:
   - Deleted incomplete files: refactor.md (2,841 lines), TASK.MD (454 lines)
   - Consolidated PRODUCTION_CHECKLIST.md into PRODUCTION_READY.md
   - Single comprehensive production guide maintained

### Previous Changes (November 7, 2025) - Session 2
1. **Announcement Double-Submission Fix**:
   - Added Alpine.js-based form submission protection to prevent duplicate announcements
   - Button disables automatically after first click
   - Prevents accidental double-posting
   
2. **Device Tracking Analytics** (NEW):
   - Added comprehensive device detection (Desktop/Mobile/Tablet)
   - Device type shown in Recent Visits table with color-coded badges
   - User agent parsing detects device type automatically
   - Device stats tracked in page_visits table
   
3. **IP Address Filtering**:
   - New filter input on Analytics Dashboard
   - Filter visits by specific IP address
   - Quick clear button to remove filter
   - Maintains period selection when filtering
   
4. **Affiliate Action Tracking** (NEW):
   - Created `affiliate_actions` table to track:
     * Login events
     * Dashboard views
     * Signup button clicks
   - Automatic tracking on affiliate login
   - Dashboard view tracking implemented
   - Includes IP address and user agent for security monitoring
   
5. **Cron Jobs Optimization**:
   - **REMOVED**: 2 AM and 4 AM backup jobs (user no longer wants them)
   - **KEPT**: Weekly backups (Tuesday & Friday at 2 AM WAT)
   - **KEPT**: Monthly backups with email (1st of month at 3 AM WAT)
   - **KEPT**: Scheduled affiliate emails (Tuesday & Friday at 10 AM WAT, Monthly on 1st at 9 AM WAT)

### Previous Changes (November 7, 2025) - Session 1
1. **Unified Email Modal**: 
   - Combined "Email All Affiliates" and "Email Single Affiliate" into one modal
   - Audience dropdown selector (All Active Affiliates or Single Affiliate)
   - Cleaner UI with dynamic form fields
2. **Announcement System**:
   - System-generated welcome announcements now hidden from management board
   - Only admin-created announcements appear in the management interface
3. **Scheduled Affiliate Emails**:
   - Created `cron/database.php` for automated email campaigns
   - **Twice-weekly performance updates**: Every Tuesday & Friday at 10 AM WAT
   - **Monthly summary reports**: 1st of each month at 9 AM WAT
   - Includes metrics: clicks, sales, earnings (weekly and all-time)
4. **Cron Documentation**: Updated `cron/CRON_SETUP.md` with scheduled email job instructions

### Production Readiness Improvements
1. **Homepage Pagination**: Fixed to show 10 templates per page instead of 9
2. **Email System**: 
   - Optimized template spacing (reduced padding/margins)
   - Fixed character encoding for₦ (Naira symbol) and emojis
   - Added spam folder warnings to affiliate emails
   - Logo properly configured (JPG format)
3. **CSV Exports**: 
   - Fixed UTF-8 encoding with BOM for Excel compatibility
   - Proper column alignment and quoting
   - Null value handling
4. **Affiliate Communication**:
   - WhatsApp floating button on all affiliate pages
   - Support ticket system fully functional
   - Direct admin contact via WhatsApp
5. **Database Optimization**:
   - VACUUM, ANALYZE, OPTIMIZE commands available
   - Manual and automated optimization
   - Session write optimization
6. **Analytics**: 
   - Comprehensive dashboard with charts
   - Top 10 templates tracking (views + clicks)
   - Bounce rate and time on site metrics
   - CSV export for all analytics data
   - Device tracking (Desktop/Mobile/Tablet)
   - IP address filtering for Recent Visits
   - Color-coded device badges in analytics table

## Important: SQLite vs phpMyAdmin

**This project uses SQLite, NOT MySQL/MariaDB!**

- phpMyAdmin does NOT work with SQLite databases
- Use `admin/database.php` for database management
- To view structure: `PRAGMA table_info(table_name);`
- SQLite stores everything in a single file: `database/webdaddy.db`

## Project Structure

```
/
├── admin/              # Admin panel
├── affiliate/          # Affiliate dashboard  
├── public/             # Public-facing pages (templates, homepage)
├── includes/           # Shared PHP code
├── database/           # SQLite database + backups
├── cron/               # Backup and maintenance scripts
├── assets/             # CSS, JS, images
└── mailer/             # PHPMailer library
```

## Key Technologies

- **PHP** 8.x+
- **SQLite** (portable, file-based database)
- **Tailwind CSS** (via CDN)
- **Alpine.js** (lightweight interactivity)
- **PHPMailer** (email delivery)
- **Chart.js** (analytics charts)

## Timezone Configuration

All timestamps use **Africa/Lagos (GMT+1 / WAT)**
- Set in `includes/config.php`
- Applies to all date/time functions across the application

## Backup System

### Configuration
- **Twice Weekly**: Tuesday & Friday at 2 AM WAT
- **Monthly**: 1st of each month at 3 AM WAT (with email)
- **Retention**: Last 4 weekly + 12 monthly backups
- See `cron/CRON_INSTRUCTIONS.md` for setup details

### Cron Jobs to Add
```bash
# Weekly backups (Tuesday & Friday at 2 AM WAT)
0 2 * * 2,5 /usr/bin/php /path/to/cron/backup.php weekly

# Monthly backups with email (1st of month at 3 AM WAT)
0 3 1 * * /usr/bin/php /path/to/cron/backup.php monthly

# Scheduled affiliate emails - Twice weekly (Tuesday & Friday at 10 AM WAT)
0 10 * * 2,5 /usr/bin/php /path/to/cron/database.php weekly

# Monthly affiliate summary (1st of month at 9 AM WAT)
0 9 1 * * /usr/bin/php /path/to/cron/database.php monthly
```

**NOTE**: Previous 2 AM and 4 AM backup jobs have been removed per user request.

## Production Deployment

**Before going live, review:**
1. `PRODUCTION_CHECKLIST.md` - Complete checklist
2. `cron/CRON_INSTRUCTIONS.md` - Backup configuration
3. Set file permissions:
   ```bash
   chmod 640 database/webdaddy.db
   chmod 750 database/backups
   chmod 644 includes/*.php
   ```
4. Verify `DISPLAY_ERRORS = false` in `includes/config.php`
5. Test email delivery (check spam folder)
6. Set up cron jobs for backups

## Database Management

Access: `/admin/database.php`

Features:
- Execute SQL queries
- VACUUM optimization
- Manual backups
- Database cleanup
- View table statistics

Common queries:
```sql
-- View all tables
SELECT name FROM sqlite_master WHERE type='table';

-- View table structure
PRAGMA table_info(templates);

-- Database optimization
VACUUM;
ANALYZE;
PRAGMA optimize;
```

## Email Configuration

SMTP settings in `includes/config.php`:
- **Host**: mail.webdaddy.online
- **Port**: 465 (SSL)
- **From**: admin@webdaddy.online

**Important**: Emails include spam folder warnings for affiliates.

## User Preferences

### Coding Style
- Consistent indentation (4 spaces)
- Clear, descriptive function names
- Security-first approach (sanitize all inputs)
- Use prepared statements for all database queries

### Performance
- Optimize database regularly (VACUUM)
- Pagination on all large datasets
- Session optimization enabled
- Database connection pooling via singleton

### Security
- CSRF protection on forms
- XSS protection via htmlspecialchars()
- Session hijacking prevention
- Rate limiting on login attempts
- Error messages don't expose sensitive info

## Common Tasks

### Run Database Optimization
```bash
php admin/database.php --vacuum
```

### Create Manual Backup
```bash
php cron/backup.php weekly
```

### View Analytics
Navigate to: `/admin/analytics.php`

## Troubleshooting

### "Database structure won't load in phpMyAdmin"
- **Expected behavior!** SQLite doesn't work with phpMyAdmin
- Use `/admin/database.php` instead

### "Emails landing in spam"
- Ensure SPF/DKIM/DMARC records configured
- Affiliates are warned to check spam folder
- Warm up IP address gradually

### "CSV exports have broken columns"
- Fixed! Now includes UTF-8 BOM and proper quoting
- Should open correctly in Excel

### "Site time is wrong"
- Already fixed! Set to Africa/Lagos timezone
- Verify in `includes/config.php`

## Support

- **WhatsApp**: +2349132672126
- **Admin Email**: admin@webdaddy.online
- **Affiliate Support**: Via ticket system or WhatsApp button

## Recent Architecture Decisions

1. **SQLite over MySQL**: Easier deployment on shared hosting
2. **Tailwind CDN over compiled**: Faster development, no build step
3. **Session optimization**: Write-close pattern to prevent locks
4. **UTF-8 everywhere**: Proper character encoding for international symbols
5. **Twice-weekly backups**: Balance between safety and storage

## Next Steps

1. Test all features in staging environment
2. Set up production cron jobs
3. Configure email warmup schedule
4. Monitor analytics for first week
5. Gather user feedback on affiliate dashboard

---

Last Updated: November 7, 2025
