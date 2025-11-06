# Production Deployment Checklist

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
- [x] Query optimization via ANALYZE

### 6. Timezone Configuration
- [x] Timezone set to Africa/Lagos (GMT+1)
- [x] All date functions use correct timezone

### 7. Backup System
**Cron Configuration:**

Add these to your cPanel cron jobs:

**Twice Weekly Backups (Tuesday & Friday at 2 AM):**
```
0 2 * * 2,5 /usr/bin/php /path/to/project/cron/backup.php weekly
```

**Monthly Backup (1st of month at 3 AM with email):**
```
0 3 1 * * /usr/bin/php /path/to/project/cron/backup.php monthly
```

**Database Cleanup (Daily at 4 AM):**
```
0 4 * * * /usr/bin/php /path/to/project/admin/database.php cleanup_cli
```

### 8. Analytics & Monitoring
- [x] Page visit tracking enabled
- [x] Bounce rate calculation
- [x] Time on site tracking
- [x] Top 10 templates tracking (views & clicks)
- [x] CSV export functionality

### 9. Affiliate Communication
- [x] Support ticket system
- [x] WhatsApp floating button on all affiliate pages
- [x] Email notifications for all key events
- [x] Announcement system with timers

### 10. CSV Export Quality
- [x] UTF-8 BOM for Excel compatibility
- [x] Proper column alignment
- [x] Character encoding fixed (₦ symbol)
- [x] All fields properly escaped

## SQLite vs phpMyAdmin Important Note

**Your database uses SQLite, not MySQL/MariaDB.**

- phpMyAdmin is designed for MySQL/MariaDB databases
- SQLite databases cannot be managed through phpMyAdmin
- Use the admin/database.php page instead for database management

**To view database structure:**
1. Go to admin/database.php
2. Use SQL query: `SELECT * FROM sqlite_master WHERE type='table';`
3. For specific table structure: `PRAGMA table_info(table_name);`

## Launch Day Tasks

1. **Test all critical flows:**
   - [ ] User registration
   - [ ] Order placement
   - [ ] Affiliate signup
   - [ ] Email delivery
   - [ ] Payment confirmation
   - [ ] Withdrawal requests

2. **Performance check:**
   - [ ] Homepage loads < 2 seconds
   - [ ] Database queries < 100ms average
   - [ ] Email delivery functional

3. **Security verification:**
   - [ ] Error messages don't expose sensitive info
   - [ ] File permissions set correctly
   - [ ] Session security working

4. **Backup verification:**
   - [ ] Cron jobs added to cPanel
   - [ ] Manual backup created
   - [ ] Monthly email backup tested

## Post-Launch Monitoring

### Daily
- Check error logs: `tail -f /path/to/error.log`
- Monitor disk space for database
- Verify cron jobs ran successfully

### Weekly
- Review analytics dashboard
- Check for failed emails
- Verify backups exist

### Monthly
- Download and verify monthly backup
- Review affiliate payouts
- Database VACUUM optimization
- Clean up old activity logs (>90 days)

## Emergency Contacts

- **Admin Email:** admin@webdaddy.online
- **WhatsApp Support:** +2349132672126
- **Hosting Support:** (Add your hosting provider)

## Troubleshooting Common Issues

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

### "CSV exports broken"
- Ensure UTF-8 encoding
- Check for NULL values
- Verify column count matches headers

## Success Metrics

- **Response Time:** < 2 seconds average
- **Email Delivery Rate:** > 95%
- **Database Size:** Monitor weekly
- **Backup Success Rate:** 100%
- **Session Security:** No hijacking incidents
