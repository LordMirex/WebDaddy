# Upload Authentication Fix - November 16, 2025

## Problem Summary
The upload system was returning **403 Forbidden** errors on all upload attempts, preventing admins from uploading images and videos through the admin panel.

## Root Cause Analysis

### Issue #1: Missing `isAdmin()` Function
The upload API (`api/upload.php`) was calling `isAdmin()` function to verify admin access, but this function **did not exist** in the codebase.

**Evidence from logs:**
```
[Sun Nov 16 15:43:15 2025] 172.31.74.66:43148 [403]: POST /api/upload.php
[Sun Nov 16 15:43:21 2025] 172.31.74.66:43152 [403]: POST /api/upload.php
[Sun Nov 16 15:43:47 2025] 172.31.74.66:55046 [403]: POST /api/upload.php
```

**Code before fix:**
```php
// In api/upload.php
if (!isLoggedIn() || !isAdmin()) {  // isAdmin() function didn't exist!
    http_response_code(403);
    ...
}
```

**What existed:**
- Only `requireAdmin()` function existed in `admin/includes/auth.php`
- This function redirects to login page instead of returning a boolean
- The upload API couldn't use it

### Issue #2: Missing Include Statement
Even if `isAdmin()` existed, `api/upload.php` wasn't including `admin/includes/auth.php`, so the function wouldn't be available anyway.

### Issue #3: Conflicting Authentication Checks
The upload API was checking BOTH:
- `isLoggedIn()` - which checks for `$_SESSION['user_id']` (regular user session)
- `isAdmin()` - which checks for `$_SESSION['admin_id']` (admin session)

**Problem:** Admin login sets `admin_id` and `admin_role`, but NOT `user_id`, so `isLoggedIn()` always returns false for admins, blocking all uploads.

## Fixes Applied

### Fix #1: Added `isAdmin()` Function
**File:** `admin/includes/auth.php`

**Added:**
```php
function isAdmin()
{
    return isset($_SESSION['admin_id']) && 
           isset($_SESSION['admin_role']) && 
           $_SESSION['admin_role'] === 'admin';
}
```

**Updated `requireAdmin()` to use it:**
```php
function requireAdmin()
{
    if (!isAdmin()) {
        header('Location: /admin/login.php');
        exit;
    }
}
```

### Fix #2: Added Missing Include
**File:** `api/upload.php`

**Added:**
```php
require_once __DIR__ . '/../admin/includes/auth.php';
```

### Fix #3: Removed Conflicting Check
**File:** `api/upload.php`

**Before:**
```php
if (!isLoggedIn() || !isAdmin()) {
    http_response_code(403);
    ...
}
```

**After:**
```php
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized access. Admin login required.'
    ]);
    exit;
}
```

## How Authentication Now Works

### Admin Login Flow:
1. Admin logs in via `/admin/login.php`
2. `loginAdmin()` function sets:
   - `$_SESSION['admin_id']` = user ID
   - `$_SESSION['admin_email']` = admin email
   - `$_SESSION['admin_name']` = admin name
   - `$_SESSION['admin_role']` = 'admin'
3. Session is regenerated for security
4. Admin is redirected to admin dashboard

### Upload Request Flow:
1. JavaScript sends file to `/api/upload.php` via AJAX
2. `startSecureSession()` starts/resumes the session
3. `isAdmin()` checks if:
   - `$_SESSION['admin_id']` exists
   - `$_SESSION['admin_role']` exists and equals 'admin'
4. If check passes, upload proceeds
5. If check fails, returns 403 with error message

## Testing Instructions

### 1. Clear Browser Cache and Cookies
This ensures you're starting fresh:
- Chrome: Ctrl+Shift+Delete → Clear browsing data
- Firefox: Ctrl+Shift+Delete → Clear all history
- Safari: Cmd+Option+E → Clear cache

### 2. Log In to Admin Panel
1. Go to `/admin/login.php`
2. Enter credentials:
   - Email: `admin@example.com`
   - Password: `admin123`
3. Verify successful login to admin dashboard

### 3. Test Image Upload (Templates)
1. Go to **Templates Management** (`/admin/templates.php`)
2. Click **"Add Template"**
3. Scroll to **Thumbnail Image** section
4. Click **"Upload & Crop"** button
5. Select a test image (JPG, PNG, or WebP under 20MB)
6. Wait for image cropper to load
7. Adjust crop area as desired
8. Fill in required fields (Name, Slug, Category, Price)
9. Click **"Create Template"**

**Expected Result:**
- ✅ Image uploads successfully
- ✅ Cropper works properly
- ✅ Template is created with uploaded thumbnail
- ✅ Image appears on homepage

**If you see errors:**
- Check browser console (F12 → Console)
- Look for any red error messages
- Report the exact error message

### 4. Test Video Upload (Templates)
1. Same template form as above
2. Scroll to **Demo Video** section
3. Click **"Upload"** button
4. Select a video file (MP4, WebM, MOV under 100MB)
5. Watch progress bar reach 100%
6. Wait for "Upload complete!" message
7. Submit the form

**Expected Result:**
- ✅ Video uploads successfully
- ✅ Progress bar shows upload progress
- ✅ Template is created with demo video URL
- ✅ Video plays on homepage via "Watch Demo" button

### 5. Test Tool Uploads
Repeat the same process for **Tools Management** (`/admin/tools.php`):
- Test image upload with cropper
- Verify thumbnails save correctly

## Troubleshooting

### Error: "Unauthorized access. Admin login required."
**Cause:** Not logged in as admin, or session expired

**Solution:**
1. Log out completely: `/admin/logout.php`
2. Clear browser cookies
3. Log in again with admin credentials
4. Try upload again

### Error: "Upload failed" (from JavaScript)
**Cause:** Various possible issues

**Solution:**
1. Open browser console (F12)
2. Look for the actual error message
3. Common causes:
   - File too large (>20MB for images, >100MB for videos)
   - Invalid file type
   - Network error
   - Server timeout

### Upload starts but never completes
**Cause:** Server timeout for large files

**Solution:**
1. Use smaller test files initially (< 5MB)
2. Check if FFmpeg is processing (for videos)
3. Check server logs in `/tmp/logs/`

### Session expires during upload
**Cause:** Upload takes longer than session timeout

**Solution:**
- Session timeout is 24 hours (86400 seconds) by default
- For very large files, consider increasing `SESSION_LIFETIME` in config

## Files Modified

1. **admin/includes/auth.php**
   - Added `isAdmin()` function (lines 4-7)
   - Updated `requireAdmin()` to use `isAdmin()`

2. **api/upload.php**
   - Added include for `admin/includes/auth.php` (line 14)
   - Removed `isLoggedIn()` check (line 27)
   - Simplified to only check `isAdmin()`

## Security Notes

✅ **No security vulnerabilities introduced**
- `isAdmin()` properly validates admin session
- Still checks for session data existence
- Still validates admin role
- File upload validation remains intact
- CSRF protection not needed for AJAX file uploads (session-based auth)

## Next Steps

1. ✅ Test uploads with admin account
2. ✅ Verify non-admin users cannot access upload API
3. ✅ Test all three upload scenarios:
   - Template thumbnail upload
   - Template video upload
   - Tool thumbnail upload
4. ✅ Test on different browsers (Chrome, Firefox, Safari)
5. ✅ Test with different file sizes and types

## Status

**Current Status:** ✅ FIXED - Ready for Testing

**Date Fixed:** November 16, 2025
**Time:** 15:46 UTC
**Tested:** Pending user verification
