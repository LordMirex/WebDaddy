# WebDaddy Empire - Affiliate Platform

## Project Overview
A complete affiliate marketing platform for selling website templates with domains included. Built with PHP 8.x and SQLite for easy deployment on cPanel environments.

## Recent Updates (November 2025)

### Latest Changes (November 7, 2025)
1. **Unified Email Modal**: 
   - Combined "Email All Affiliates" and "Email Single Affiliate" into one modal
   - Audience dropdown selector (All Active Affiliates or Single Affiliate)
   - Cleaner UI with dynamic form fields
2. **Announcement System**:
   - System-generated welcome announcements now hidden from management board
   - Only admin-created announcements appear in the management interface
3. **Scheduled Affiliate Emails** (NEW):
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

Last Updated: November 6, 2025
