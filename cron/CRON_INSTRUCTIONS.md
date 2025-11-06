# Cron Job Setup Instructions

## Backup System - Twice Weekly + Monthly Email

### Step 1: Access cPanel Cron Jobs
1. Log in to your cPanel account
2. Navigate to **Advanced** → **Cron Jobs**

### Step 2: Add Backup Cron Jobs

**Twice Weekly Backups (Tuesday & Friday at 2 AM WAT):**
```
0 2 * * 2,5 /usr/bin/php /home/YOUR_USERNAME/public_html/cron/backup.php weekly
```

**Monthly Backup with Email (1st of month at 3 AM WAT):**
```
0 3 1 * * /usr/bin/php /home/YOUR_USERNAME/public_html/cron/backup.php monthly
```

### Step 3: Replace Placeholders
- Replace `YOUR_USERNAME` with your actual cPanel username
- Replace `/home/YOUR_USERNAME/public_html` with the full path to your project

### Step 4: Verify PHP Path
To find your PHP path, run in SSH:
```bash
which php
```

Common PHP paths:
- `/usr/bin/php` (most common)
- `/usr/local/bin/php`
- `/opt/cpanel/ea-php82/root/usr/bin/php` (cPanel EA-PHP)

### Step 5: Test Manually
Test backups manually via SSH:
```bash
php /path/to/project/cron/backup.php weekly
php /path/to/project/cron/backup.php monthly
```

## Backup Retention Policy

**Weekly Backups:**
- Keeps last 4 weekly backups
- Runs on Tuesday (day 2) and Friday (day 5)
- Automatically deletes older backups

**Monthly Backups:**
- Keeps last 12 monthly backups
- Runs on 1st of each month
- Emails backup file to admin@webdaddy.online
- Automatically deletes backups older than 12 months

## Backup Locations

All backups are stored in:
```
/database/backups/
├── weekly_backup_YYYY-MM-DD_HH-MM-SS.db
└── monthly_backup_YYYY-MM-DD_HH-MM-SS.db
```

## Email Notifications

Monthly backups will send an email to:
- **Recipient:** admin@webdaddy.online (defined in SMTP_FROM_EMAIL)
- **Subject:** "📦 Monthly Database Backup - [Month Year]"
- **Attachment:** Database backup file

Make sure to:
- Check your spam folder for the first backup email
- Whitelist admin@webdaddy.online to prevent spam filtering

## Troubleshooting

### Cron job not running?
1. Check cron log: `grep CRON /var/log/syslog`
2. Verify file permissions: `ls -la /path/to/cron/backup.php`
3. Ensure PHP path is correct
4. Check for syntax errors: `php -l backup.php`

### Backup files not created?
1. Check directory permissions:
   ```bash
   chmod 755 /path/to/database/backups
   ```
2. Verify disk space: `df -h`
3. Check error logs in cron output

### Email not sending?
1. Verify SMTP credentials in `includes/config.php`
2. Test email manually
3. Check spam folder
4. Verify attachment size limit (most mail servers limit 25MB)

## Recommended Additional Cron Jobs

### Database Cleanup (Daily at 4 AM)
Removes old activity logs (>90 days) and cancelled orders (>30 days):
```
0 4 * * * /usr/bin/php /path/to/project/cron/cleanup.php
```

### Session Cleanup (Hourly)
Removes expired sessions:
```
0 * * * * /usr/bin/php /path/to/project/cron/session_cleanup.php
```

## Monitoring Backups

### Via Admin Dashboard
1. Go to `/admin/database.php`
2. View "Last Backup" stat card
3. Download recent backups

### Via SSH
```bash
# List all backups
ls -lh /path/to/database/backups/

# Check backup file size
du -h /path/to/database/backups/*.db

# Verify backup integrity (should not error)
sqlite3 backup_file.db "PRAGMA integrity_check;"
```

## Security Best Practices

1. **Store backups off-server:** Download monthly email backups and store securely
2. **Test restores:** Periodically test restoring from backups
3. **Monitor disk space:** Ensure adequate space for backups
4. **Secure backup directory:** Set proper permissions (750)

```bash
chmod 750 database/backups
chmod 640 database/backups/*.db
```

## Emergency Restore Procedure

If you need to restore from backup:

1. **Stop all services** (optional but recommended)
2. **Backup current database:**
   ```bash
   cp database/webdaddy.db database/webdaddy_before_restore.db
   ```
3. **Restore from backup:**
   ```bash
   cp database/backups/weekly_backup_2025-11-06.db database/webdaddy.db
   ```
4. **Verify integrity:**
   ```bash
   sqlite3 database/webdaddy.db "PRAGMA integrity_check;"
   ```
5. **Set permissions:**
   ```bash
   chmod 640 database/webdaddy.db
   ```
6. **Test application** - verify everything works

## Quick Reference

| Task | Frequency | Time | Command |
|------|-----------|------|---------|
| Weekly Backup | Tue & Fri | 2:00 AM | `php cron/backup.php weekly` |
| Monthly Backup | 1st of month | 3:00 AM | `php cron/backup.php monthly` |
| Database Cleanup | Daily | 4:00 AM | `php cron/cleanup.php` |

## Support

For issues with backups, contact:
- **WhatsApp:** +2349132672126
- **Email:** admin@webdaddy.online
