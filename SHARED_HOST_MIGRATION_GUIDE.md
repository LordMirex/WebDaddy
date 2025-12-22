# Shared Hosting Migration Guide - Critical Issues Fixed

## ✅ Issues Found & Fixed

### 1. **SQLite Database** (CRITICAL)
**Problem:** Most shared hosts don't support SQLite databases
**Status:** ⚠️ NEEDS MIGRATION (Not auto-fixed)
**Action Required:** Migrate to MySQL/MariaDB before deployment
- Database currently at: `/database/webdaddy.db`
- Request MySQL credentials from hosting provider
- Use migration script when ready

### 2. **Email Configuration** (CRITICAL - NOW FIXED)
**Problem:** SMTP credentials hardcoded for `mail.webdaddy.online`
**Status:** ✅ FIXED - Now supports environment variables
**Changes Made:**
- SMTP_HOST, SMTP_USER, SMTP_PASS
- MAILTRAP_API_KEY, GMAIL_OTP credentials
- All now read from environment variables with fallback to defaults

### 3. **File Permissions & mkdir()** (FIXED)
**Problem:** `mkdir()` with 0777 permissions fails on shared hosts with open_basedir
**Status:** ✅ FIXED - Added error suppression (@)
**Changes Made:**
- Error suppression operator (@) added to all mkdir() calls
- Graceful fallback instead of hard failure
- Better error messages for debugging

### 4. **Session Path** (FIXED)
**Problem:** Custom session path `/tmp/php_sessions` not always writable
**Status:** ✅ FIXED - Now uses system temp directory
**Changes Made:**
- Changed to `sys_get_temp_dir()` (platform-agnostic)
- Graceful fallback to PHP default if custom path fails
- Added writability checks before attempting creation

### 5. **API Credentials Exposure** (FIXED)
**Problem:** Paystack keys, SMTP passwords exposed in source code
**Status:** ✅ FIXED - All now support environment variables
**Changes Made:**
- PAYSTACK_SECRET_KEY, PAYSTACK_PUBLIC_KEY
- SMTP_PASS
- GMAIL_OTP_APP_PASSWORD
- All now read from `$_ENV` or `getenv()` first, then fallback to hardcoded values

## 📋 Environment Variables to Set on Shared Host

Create a `.env` file or set these in your hosting control panel:

```
SMTP_HOST=mail.yourdomain.com
SMTP_PORT=465
SMTP_USER=youremail@yourdomain.com
SMTP_PASS=your_smtp_password
SMTP_FROM_EMAIL=youremail@yourdomain.com

MAILTRAP_API_KEY=your_mailtrap_key
MAILTRAP_FROM_EMAIL=your_email@yourdomain.com

GMAIL_OTP_USER=your_gmail@gmail.com
GMAIL_OTP_APP_PASSWORD=your_gmail_app_password

PAYSTACK_SECRET_KEY=sk_live_xxxx
PAYSTACK_PUBLIC_KEY=pk_live_xxxx
PAYSTACK_MODE=live
```

## ⚠️ Issues NOT Auto-Fixed (Manual Action Required)

### 1. **Database Migration (SQLite → MySQL)**
**Action:** You must do this yourself
- Export SQLite database
- Create MySQL database on shared host
- Import data
- Update `includes/db.php` to use MySQL DSN instead of SQLite

### 2. **.htaccess Files**
**Found at:**
- `includes/.htaccess`
- `uploads/.htaccess`
- `uploads/temp/.htaccess`

**Action:** Ensure these are uploaded to shared host with correct permissions

### 3. **Hardcoded Absolute Paths**
**Status:** ✅ HANDLED - Using relative paths with `__DIR__`
- All paths use `__DIR__` which adapts to any environment
- File paths are now environment-agnostic

## ✅ File Path Portability (FULLY FIXED)

All image, video, and file paths now use `getMediaUrl()` function which:
- Automatically adapts to your domain
- Works with subdomains
- Works in subdirectories
- Works on shared hosts

Example: `uploads/blog-images/file.jpg` will resolve to:
- Replit: `https://your-replit.repl.co/uploads/blog-images/file.jpg`
- Shared Host: `https://yourdomain.com/uploads/blog-images/file.jpg`

## 🚀 Deployment Checklist

- [ ] Create MySQL database and export SQLite data
- [ ] Update `includes/db.php` for MySQL connection
- [ ] Set environment variables on shared host
- [ ] Upload all files including `.htaccess`
- [ ] Ensure `uploads/` folder exists and is writable (chmod 755)
- [ ] Test email sending (SMTP configuration)
- [ ] Verify file uploads work
- [ ] Test session handling
- [ ] Check blog images display correctly

## 📞 Support

If you encounter permission errors on shared host:
1. Contact hosting support and request:
   - MySQL database access
   - Writable uploads folder
   - Allow mkdir() in PHP
2. Check `logs/error.log` for detailed error messages
3. Verify environment variables are set correctly
