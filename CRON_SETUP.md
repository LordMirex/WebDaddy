# Cron Jobs Setup Guide

## Available Cron Jobs

### 1. Process Delivery Retries
**What it does:** Retries failed template/tool deliveries with exponential backoff

```bash
php /path/to/cron.php process-retries
```

**How often:** Every 5 minutes
**Why:** Ensures customers get their downloads even if server was briefly down

**Cron entry:**
```
*/5 * * * * /usr/bin/php /home/user/public_html/cron.php process-retries
```

### 2. Cleanup Security Logs
**What it does:** Removes old rate limit entries and security logs

```bash
php /path/to/cron.php cleanup-security
```

**How often:** Every hour
**Why:** Keeps database clean and prevents unlimited growth

**Cleanup details:**
- Rate limit entries older than 1 hour
- Security logs older than 30 days

**Cron entry:**
```
0 * * * * /usr/bin/php /home/user/public_html/cron.php cleanup-security
```

### 3. Weekly Report (Optional)
**What it does:** Generates detailed report + database backup, emails to admin

```bash
php /path/to/cron.php weekly-report
```

**How often:** Mondays only
**Why:** Automatic weekly summary + backup for disaster recovery

**Cron entry (Mondays 3 AM):**
```
0 3 * * 1 /usr/bin/php /home/user/public_html/cron.php weekly-report
```

### 4. Database Optimization (Optional)
**What it does:** Optimizes database structure and reclaims space

```bash
php /path/to/cron.php optimize
```

**How often:** Once per week
**Why:** Keeps queries fast and database file lean

**Cron entry (Sundays 2 AM):**
```
0 2 * * 0 /usr/bin/php /home/user/public_html/cron.php optimize
```

## Platform-Specific Setup

### cPanel / WHM
1. Log into cPanel
2. Go to **Cron Jobs**
3. Add New Cron Job
4. Enter command: `/usr/bin/php /home/username/public_html/cron.php process-retries`
5. Select "Every 5 minutes"
6. Click "Add New Cron Job"
7. Repeat for other jobs

### Plesk
1. Log into Plesk
2. Go to **Tools & Settings** → **Scheduled Tasks**
3. Click **Add Task**
4. Task name: `WebDaddy - Process Retries`
5. Command: `/usr/bin/php /path/to/cron.php process-retries`
6. Run every: 5 minutes
7. Click **OK**
8. Repeat for other jobs

### Linux crontab (Direct Access)
1. SSH into your server
2. Run: `crontab -e`
3. Add these lines:
```
*/5 * * * * /usr/bin/php /home/user/public_html/cron.php process-retries
0 * * * * /usr/bin/php /home/user/public_html/cron.php cleanup-security
0 3 * * 1 /usr/bin/php /home/user/public_html/cron.php weekly-report
0 2 * * 0 /usr/bin/php /home/user/public_html/cron.php optimize
```
4. Save (Ctrl+X, then Y, then Enter)
5. Verify: `crontab -l`

### AWS Lambda / Scheduled Tasks
1. Create Lambda function with PHP runtime
2. Trigger on schedule:
   - Every 5 minutes for `process-retries`
   - Every hour for `cleanup-security`
3. Lambda function runs: `/usr/bin/php /path/to/cron.php process-retries`

## Verification

### Test Cron Manually
```bash
# Test process-retries
php /path/to/cron.php process-retries

# Test cleanup-security
php /path/to/cron.php cleanup-security

# Expected output:
# ✅ Task completed successfully!
```

### Check Cron Logs (Linux)
```bash
# View cron execution log
grep CRON /var/log/syslog

# Or for newer systems
journalctl -u cron -n 50
```

### Verify in Admin Panel
1. After cron runs, check Admin → System Monitoring
2. Look for:
   - Webhook Security Dashboard updated
   - Recent events logged
   - No error indicators

## Troubleshooting

### "Command not found"
**Problem:** PHP not in expected path
**Solution:** Find correct path:
```bash
which php          # Or
whereis php3       # Or in cPanel: select PHP path from dropdown
```

### "Permission denied"
**Problem:** PHP file doesn't have execute permissions
**Solution:**
```bash
chmod +x /path/to/cron.php
```

### Cron runs but does nothing
**Problem:** Script running but not producing output
**Solution:** 
1. Add error logging in cron job
2. Check `/var/log/cron` for errors
3. Verify database connection is working

### "Database locked" errors
**Problem:** Multiple cron jobs accessing database simultaneously
**Solution:** Stagger job times (e.g., 5, 10, 15 min instead of all at 5)

### Job not running at all
**Problem:** Cron service not active
**Solution:**
```bash
# Check if cron is running
systemctl status cron

# Start if not running
sudo systemctl start cron

# Enable on boot
sudo systemctl enable cron
```

## Best Practices

✅ **DO:**
- Stagger cron times to avoid database locks
- Test jobs manually before scheduling
- Monitor system logs for errors
- Keep logs around for troubleshooting (cleanup after 30 days)
- Schedule heavy jobs (optimize, weekly-report) during low-traffic hours

❌ **DON'T:**
- Run all jobs at the same time (database conflicts)
- Schedule jobs every minute (unnecessary)
- Leave stdout/stderr going nowhere (hard to debug)
- Run jobs without proper error handling

## Monitoring Cron Health

### Add Health Check Webhook (Optional)
After each cron job, call a health endpoint to verify it ran:
```bash
# Example for process-retries
php /path/to/cron.php process-retries && curl https://your-domain.com/api/health-check
```

### Manual Verification Checklist
After each cron job, verify:
- [ ] No errors in error.log
- [ ] Database still responsive
- [ ] Webhook Security Dashboard updated
- [ ] No stuck delivery jobs
- [ ] No rate limit issues

## Examples

### Minimal Setup (Recommended)
```bash
# Only the essential job
*/5 * * * * /usr/bin/php /home/user/public_html/cron.php process-retries
```

### Full Setup (Recommended)
```bash
# Process failed deliveries
*/5 * * * * /usr/bin/php /home/user/public_html/cron.php process-retries

# Cleanup old logs
0 * * * * /usr/bin/php /home/user/public_html/cron.php cleanup-security

# Weekly backup
0 3 * * 1 /usr/bin/php /home/user/public_html/cron.php weekly-report

# Database optimization
0 2 * * 0 /usr/bin/php /home/user/public_html/cron.php optimize
```

### High-Volume Setup (With Error Handling)
```bash
# Retry more frequently
*/2 * * * * /usr/bin/php /home/user/public_html/cron.php process-retries >> /var/log/cron-output.log 2>&1

# Cleanup more frequently
*/30 * * * * /usr/bin/php /home/user/public_html/cron.php cleanup-security >> /var/log/cron-output.log 2>&1

# Weekly tasks
0 3 * * 1 /usr/bin/php /home/user/public_html/cron.php weekly-report >> /var/log/cron-output.log 2>&1
0 2 * * 0 /usr/bin/php /home/user/public_html/cron.php optimize >> /var/log/cron-output.log 2>&1
```

## Support

For issues with specific hosting providers:
- **cPanel:** https://documentation.cpanel.net/display/
- **Plesk:** https://docs.plesk.com/
- **AWS:** https://docs.aws.amazon.com/elasticbeanstalk/

For WebDaddy Empire cron issues:
- Check error.log in logs directory
- Verify all required functions exist
- Ensure database file is writable
