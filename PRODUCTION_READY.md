# Production Deployment Guide

## ✅ System Status

**Last Updated:** November 7, 2025  
**Status:** Production Ready  
**Platform:** PHP 8.x + SQLite + Tailwind CSS + Alpine.js

---

## Recent Updates

### Latest Fixes (November 7, 2025)
1. **Instant Search (AJAX)** - Search now works without page reload
2. **Cleanup** - Removed incomplete documentation files (refactor.md, TASK.MD)
3. **Search UX** - Real-time results with loading indicator
4. **Documentation** - Consolidated production guides

### Previous Updates
1. **Email System**: Optimized spacing, UTF-8 encoding for ₦ symbol
2. **CSV Exports**: UTF-8 BOM for Excel compatibility
3. **Analytics Dashboard**: Device tracking, IP filtering, comprehensive metrics
4. **Cron Jobs**: Automated backups and scheduled affiliate emails
5. **Announcement System**: Timer/duration with auto-expiry

---

## Critical Pre-Launch Tasks

### 1. Database Optimization
- [x] VACUUM optimization configured
- [x] Auto-optimization after heavy operations
- [x] Backup system (twice weekly + monthly email)
- [x] Database cleanup routines for old records

### 2. Security Hardening
- [x] Error display turned OFF in production (`DISPLAY_ERRORS = false`)
- [x] Session lifetime configured (1 hour)
- [x] Session hijacking protection
- [x] SQL injection protection via PDO prepared statements
- [x] XSS protection via htmlspecialchars()
- [x] CSRF protection on forms

### 3. File Permissions (Set these on server)
```bash
chmod 640 database/webdaddy.db
chmod 750 database/backups
chmod 644 includes/*.php
chmod 755 assets/
```

### 4. Email Configuration
- [x] SMTP credentials configured
- [x] UTF-8 encoding enabled
- [x] Spam folder warnings added to affiliate emails
- [x] Email templates optimized (reduced spacing)
- [x] Logo properly configured (/assets/images/webdaddy-logo.jpg)

### 5. Performance Optimization
- [x] Pagination on all data-heavy pages
- [x] Database indexes configured
- [x] Session management optimized
- [x] Instant search without page reload
- [x] Query optimization via ANALYZE

### 6. Timezone Configuration
- [x] Timezone set to Africa/Lagos (GMT+1)
- [x] All date functions use correct timezone

---

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

---

## Launch Day Tasks

### 1. Test all critical flows:
- [ ] User registration
- [ ] Order placement
- [ ] Affiliate signup
- [ ] Email delivery
- [ ] Payment confirmation
- [ ] Withdrawal requests
- [ ] Search functionality (instant results without page reload)

### 2. Performance check:
- [ ] Homepage loads < 2 seconds
- [ ] Database queries < 100ms average
- [ ] Email delivery functional
- [ ] Search responds instantly

### 3. Security verification:
- [ ] Error messages don't expose sensitive info
- [ ] File permissions set correctly
- [ ] Session security working

### 4. Backup verification:
- [ ] Cron jobs added to cPanel
- [ ] Manual backup created
- [ ] Monthly email backup tested

---

## Key Features

### Analytics Dashboard
- ✅ Visit tracking
- ✅ Bounce rate calculation
- ✅ Time on site metrics
- ✅ Top 10 templates by views/clicks
- ✅ Device tracking (Desktop/Mobile/Tablet)
- ✅ IP address filtering for Recent Visits
- ✅ CSV exports with UTF-8 BOM

### Affiliate System
- ✅ Commission tracking
- ✅ Withdrawal requests
- ✅ Support ticket system
- ✅ WhatsApp floating button
- ✅ Scheduled performance emails

### Search & Discovery
- ✅ Instant AJAX search (no page reload)
- ✅ Real-time results display
- ✅ Loading indicators
- ✅ Global template search

---

## Important: SQLite vs phpMyAdmin

**This project uses SQLite, NOT MySQL/MariaDB!**

- phpMyAdmin does NOT work with SQLite databases
- Use `admin/database.php` for database management
- To view structure: `PRAGMA table_info(table_name);`
- SQLite stores everything in a single file: `database/webdaddy.db`

---

## Post-Launch Monitoring

### Daily
- Check error logs: `tail -f /path/to/error.log`
- Monitor disk space for database
- Verify cron jobs ran successfully
- Check search performance

### Weekly
- Review analytics dashboard
- Check for failed emails
- Verify backups exist
- Monitor search queries

### Monthly
- Download and verify monthly backup
- Review affiliate payouts
- Database VACUUM optimization
- Clean up old activity logs (>90 days)

---

## Troubleshooting

### "Database structure won't load"
- This is expected! SQLite doesn't work with phpMyAdmin
- Use admin/database.php instead
- Run: `PRAGMA table_info(table_name);` to see structure

### "Emails landing in spam"
- SPF/DKIM/DMARC records configured?
- Warming up IP address gradually?
- Users aware to check spam folder?

### "Site is slow"
- Run VACUUM optimization (admin/database.php)
- Check disk space
- Review slow query log

### "Search not working"
- Check browser console for JavaScript errors
- Verify /api/search.php is accessible
- Clear browser cache

### "CSV exports broken"
- Ensure UTF-8 encoding
- Check for NULL values
- Verify column count matches headers

---

## Emergency Contacts

- **Admin Email:** admin@webdaddy.online
- **WhatsApp Support:** +2349132672126
- **Hosting Support:** (Add your hosting provider)

---

## Success Metrics

- **Response Time:** < 2 seconds average
- **Email Delivery Rate:** > 95%
- **Database Size:** Monitor weekly
- **Backup Success Rate:** 100%
- **Session Security:** No hijacking incidents
- **Search Performance:** < 300ms average

---

## Architecture Decisions

1. **SQLite over MySQL**: Easier deployment on shared hosting
2. **Tailwind CDN over compiled**: Faster development, no build step
3. **Session optimization**: Write-close pattern to prevent locks
4. **UTF-8 everywhere**: Proper character encoding for international symbols
5. **Twice-weekly backups**: Balance between safety and storage
6. **Instant AJAX search**: Better UX without full page reloads

---

## Next Steps

1. Set up production cron jobs
2. Configure email warmup schedule
3. Monitor analytics for first week
4. Gather user feedback on affiliate dashboard
5. Test search functionality thoroughly
6. Monitor search queries for insights

---

**Code like your rent is due tomorrow? Mission accomplished. Ship it proud! 🚀**

Last Updated: November 7, 2025
