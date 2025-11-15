# WebDaddy Empire - Production Setup Guide

## 🎉 Database Status: CLEAN & READY FOR PRODUCTION

This project has been cleaned and prepared for production deployment. All test data has been removed while preserving the complete database structure.

---

## 🔐 Default Admin Credentials

**IMPORTANT:** Change these credentials immediately after first login!

- **Email:** admin@example.com
- **Password:** admin123
- **Role:** Administrator

**Login URL:** `/admin/login.php`

---

## 📊 Database Information

### Database Type
- **Engine:** SQLite 3
- **Location:** `database/webdaddy.db`
- **Schema:** `database/schema_sqlite.sql`
- **Backup:** `database/webdaddy_backup_before_production_20251115_074953.db`

### Database Tables (20 Total)
Core tables with ZERO data (fresh slate):
- `users` - 1 admin user only
- `settings` - 4 default settings
- `affiliates` - Empty
- `templates` - Empty
- `tools` - Empty
- `domains` - Empty
- `pending_orders` - Empty
- `order_items` - Empty
- `sales` - Empty
- `withdrawal_requests` - Empty
- `announcements` - Empty
- `announcement_emails` - Empty
- `activity_logs` - Empty
- `affiliate_actions` - Empty
- `cart_items` - Empty
- `page_visits` - Empty
- `page_interactions` - Empty
- `session_summary` - Empty
- `support_tickets` - Empty
- `ticket_replies` - Empty

---

## ✅ Cleanup Completed

### Files & Folders Removed
- ✅ `database/backups/` - Old backup files removed
- ✅ `database/migrations/` - Development migration scripts removed
- ✅ `attached_assets/` - Test/temporary assets removed
- ✅ `database/schema_postgresql.sql` - PostgreSQL schema removed (using SQLite only)

### Files & Folders Kept
- ✅ `database/webdaddy.db` - Clean production database
- ✅ `database/schema_sqlite.sql` - Updated SQLite schema (includes tools table)
- ✅ `database/webdaddy_backup_before_production_*.db` - Backup before cleanup

---

## 🔧 Default Settings Configured

| Setting Key           | Default Value      |
|-----------------------|-------------------|
| whatsapp_number       | +2349132672126    |
| site_name             | WebDaddy Empire   |
| commission_rate       | 0.30 (30%)        |
| affiliate_cookie_days | 30                |

---

## 🚀 Next Steps for Production

### 1. Update Admin Credentials
```
1. Login at /admin/login.php
2. Go to Profile/Settings
3. Change email and password immediately
```

### 2. Configure Your Settings
```
1. Navigate to Admin → Settings
2. Update WhatsApp number
3. Set your email configuration
4. Configure affiliate commission rates
5. Set up backup schedules
```

### 3. Add Your Products
```
Templates:
- Admin → Templates → Add Template
- Upload images to /assets/images/
- Add domain names via Domains section

Tools:
- Admin → Tools → Add Tool
- Set pricing and stock levels
- Configure delivery instructions
```

### 4. Set Up Email System
```
1. Configure SMTP settings in includes/config.php
2. Update PHPMailer configuration
3. Test email delivery with a test affiliate
```

### 5. Create Affiliates (Optional)
```
Method 1: Self-registration
- Share /affiliate/register.php with partners

Method 2: Admin creation
- Admin → Affiliates → Create Affiliate
- System generates welcome announcement
```

---

## 🛡️ Security Checklist

- [ ] Change default admin password
- [ ] Update admin email address
- [ ] Review and update security settings in `includes/config.php`
- [ ] Set appropriate file permissions (644 for files, 755 for directories)
- [ ] Secure `database/` directory from web access (.htaccess)
- [ ] Enable HTTPS in production
- [ ] Configure backup automation via cron
- [ ] Test password reset functionality

---

## 📝 Important Notes

### SQLite Database
- The project uses SQLite, NOT PostgreSQL/MySQL
- Database file is portable - just copy `webdaddy.db`
- Supports up to moderate traffic (thousands of concurrent users)
- No separate database server required

### Timezone
- All timestamps: Africa/Lagos (GMT+1 / WAT)
- Set in `includes/config.php`

### Cron Jobs
- Set up via cPanel or server cron
- Scripts located in `cron.php`
- Handles backups, cleanup, scheduled emails

---

## 🐛 SQL Queries - All Verified ✅

All admin and affiliate queries have been verified for SQLite compatibility:
- ✅ Admin affiliates management queries
- ✅ Affiliate earnings queries (strftime usage)
- ✅ Withdrawal requests queries
- ✅ Sales tracking queries
- ✅ Analytics queries
- ✅ Tools and templates queries

---

## 📞 Support

For any issues with the codebase:
1. Check error logs in your server
2. Verify database permissions
3. Ensure PHP 8.x+ is installed
4. Confirm SQLite3 extension is enabled

---

## 🎯 Quick Start Command

To verify everything is working:
```bash
# Check database structure
sqlite3 database/webdaddy.db ".tables"

# Verify admin user
sqlite3 database/webdaddy.db "SELECT email, role FROM users WHERE role='admin';"

# Check settings
sqlite3 database/webdaddy.db "SELECT * FROM settings;"
```

---

**Last Updated:** November 15, 2025  
**Status:** ✅ Production Ready  
**Database:** Clean slate with structure intact
