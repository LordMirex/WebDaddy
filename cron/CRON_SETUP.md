# Automated Cron Jobs Setup

## Overview
This system includes two automated cron jobs:

### 1. Database Backups
- **Weekly backups**: Every Tuesday and Friday at 2 AM WAT (keeps last 4)
- **Monthly backups**: 1st of every month at 3 AM WAT with email attachment (keeps last 12)

### 2. Scheduled Affiliate Emails  
- **Twice-weekly updates**: Every Tuesday and Friday at 10 AM WAT (performance summaries)
- **Monthly reports**: 1st of every month at 9 AM WAT (comprehensive monthly summaries)

**Timezone**: All times are in Africa/Lagos timezone (WAT - GMT+1)

## Cron Job Configuration

Add these lines to your crontab (`crontab -e`):

### Database Backups - Tuesday & Friday at 2 AM WAT
```bash
0 2 * * 2 /usr/bin/php /path/to/your/project/cron/backup.php weekly >> /path/to/your/project/cron/backup.log 2>&1
0 2 * * 5 /usr/bin/php /path/to/your/project/cron/backup.php weekly >> /path/to/your/project/cron/backup.log 2>&1
```

### Database Monthly Backup - 1st of month at 3 AM WAT
```bash
0 3 1 * * /usr/bin/php /path/to/your/project/cron/backup.php monthly >> /path/to/your/project/cron/backup.log 2>&1
```

### Scheduled Affiliate Emails - Tuesday & Friday at 10 AM WAT
```bash
0 10 * * 2 /usr/bin/php /path/to/your/project/cron/database.php weekly >> /path/to/your/project/cron/email.log 2>&1
0 10 * * 5 /usr/bin/php /path/to/your/project/cron/database.php weekly >> /path/to/your/project/cron/email.log 2>&1
```

### Monthly Affiliate Emails - 1st of month at 9 AM WAT
```bash
0 9 1 * * /usr/bin/php /path/to/your/project/cron/database.php monthly >> /path/to/your/project/cron/email.log 2>&1
```

**Note**: These times are configured for Africa/Lagos timezone (WAT - GMT+1). Ensure your server is set to this timezone in `includes/config.php`.

## Installation Steps

1. **Make backup script executable:**
   ```bash
   chmod +x /path/to/your/project/cron/backup.php
   ```

2. **Find your PHP path:**
   ```bash
   which php
   ```
   
3. **Get your project path:**
   ```bash
   pwd
   ```
   (Run this from your project directory)

4. **Edit crontab:**
   ```bash
   crontab -e
   ```
   
5. **Add the cron jobs** (replace `/path/to/your/project` with your actual path):
   ```
   # Weekly backups - Tuesday & Friday at 2 AM WAT (Africa/Lagos timezone)
   0 2 * * 2 /usr/bin/php /home/username/webdaddy/cron/backup.php weekly >> /home/username/webdaddy/cron/backup.log 2>&1
   0 2 * * 5 /usr/bin/php /home/username/webdaddy/cron/backup.php weekly >> /home/username/webdaddy/cron/backup.log 2>&1
   
   # Monthly backup - 1st of month at 3 AM WAT with email notification
   0 3 1 * * /usr/bin/php /home/username/webdaddy/cron/backup.php monthly >> /home/username/webdaddy/cron/backup.log 2>&1
   ```

## Testing

### Test weekly backup:
```bash
php /path/to/your/project/cron/backup.php weekly
```

### Test monthly backup:
```bash
php /path/to/your/project/cron/backup.php monthly
```

## Backup Locations

- **Weekly backups**: `database/backups/weekly_backup_YYYY-MM-DD_HH-MM-SS.db`
- **Monthly backups**: `database/backups/monthly_backup_YYYY-MM-DD_HH-MM-SS.db`

## Retention Policy

- **Weekly**: Keeps last 4 backups (automatically deletes older ones)
- **Monthly**: Keeps last 12 backups (automatically deletes older ones)

## Email Notifications

Monthly backups are automatically emailed to the admin email address configured in `includes/config.php` (SMTP_FROM_EMAIL).

## Troubleshooting

1. **Check cron execution:**
   ```bash
   tail -f /path/to/your/project/cron/backup.log
   ```

2. **Verify file permissions:**
   ```bash
   ls -la /path/to/your/project/database/backups/
   ```

3. **Test email sending** (for monthly backups):
   - Ensure SMTP credentials in `includes/config.php` are correct
   - Check spam folder if emails don't arrive

## Security Note

The backup files contain your entire database. Ensure the `database/backups/` directory is:
- Not publicly accessible via web browser
- Has proper file permissions (0755 for directory, 0644 for backup files)
- Backed up to an external location regularly
