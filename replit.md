# WebDaddy Empire

## Overview
A PHP-based marketplace platform for selling website templates and digital tools. Built with SQLite database, Tailwind CSS, and custom PHP components.

## Recent Changes (November 18, 2025)

### URL Storage Migration - Environment Portability Fix
**Problem:** When files were uploaded, the system stored absolute URLs (e.g., `https://old-domain.replit.dev/uploads/...`). When moving between environments (development, staging, production, or different Replit instances), these URLs would break because they pointed to the old domain.

**Solution Implemented:**
1. **Upload Handler Update** - Modified `includes/upload_handler.php` to store relative paths (`/uploads/...`) instead of absolute URLs
2. **URL Utility Class** - Created `includes/url_utils.php` with helper functions for URL normalization and conversion
3. **Migration Tool** - Created `admin/migrate-urls.php` to convert existing database URLs from absolute to relative paths
4. **Helper Function** - Added `getMediaUrl()` to `includes/functions.php` for backward compatibility

**Files Modified:**
- `includes/upload_handler.php` - Changed line 112 to use relative URLs
- `includes/url_utils.php` - New utility class for URL handling
- `includes/functions.php` - Added getMediaUrl() helper function
- `admin/migrate-urls.php` - New migration tool for existing data

**To Complete the Fix:**
1. Login to admin panel
2. Navigate to `/admin/migrate-urls.php`
3. Click the migration button to convert existing URLs
4. All new uploads will automatically use relative paths

**Benefits:**
- URLs work in any environment without modification
- Easy migration between development, staging, and production
- No more broken images/videos when moving environments
- Backward compatible with external URLs (placeholder.com, etc.)

## Project Architecture

### Core Components
- **Database:** SQLite (`database/webdaddy.db`)
- **Upload System:** Local file storage in `uploads/` directory
- **Frontend:** Tailwind CSS, vanilla JavaScript
- **Backend:** PHP 8.2+ with custom routing via `router.php`

### Key Directories
- `admin/` - Administrative interface
- `includes/` - Core PHP classes and utilities
- `api/` - REST API endpoints
- `assets/` - Static CSS, JS, images
- `uploads/` - User-uploaded media files
- `database/` - SQLite database file

### Configuration
- `includes/config.php` - Main configuration file
- `php.ini` - PHP upload and execution limits
- Site URL auto-detected based on HTTP_HOST

## Development Notes
- Server runs on port 5000 via `php -c php.ini -S 0.0.0.0:5000 router.php`
- Upload limits: 20MB images, 100MB videos
- Timezone: Africa/Lagos (GMT+1)
