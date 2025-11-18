# WebDaddy Empire

## Overview
A PHP-based marketplace platform for selling website templates and digital tools. Built with SQLite database, Tailwind CSS, and custom PHP components.

## Recent Changes (November 18, 2025)

### Video Performance Optimization - Instant Loading & Progressive Streaming
**Problem:** Videos took too long to load when clicking preview. Modal showed loading spinner for extended periods, and videos didn't stream progressively. Users experienced slow, frustrating interactions with video content.

**Solution Implemented:**
1. **FFmpeg Video Processing** - Automatic thumbnail extraction and video optimization on upload
   - Thumbnails extracted at 1-second mark from uploaded videos
   - Videos optimized with H.264 codec and faststart flag for progressive streaming
   - All videos converted to .mp4 format for maximum compatibility
   
2. **Instant Modal Display** - Modal now appears immediately with poster image
   - Poster thumbnails shown while video buffers in background
   - No more waiting for video.load() before showing modal
   - Smooth user experience with visual feedback
   
3. **Progressive Video Streaming** - Videos start playing as soon as buffered
   - `movflags +faststart` enables progressive download and playback
   - Users don't wait for full video download
   - Bandwidth-efficient streaming

**Files Modified:**
- `includes/upload_handler.php` - Added processVideo() method for FFmpeg processing
- `assets/js/video-modal.js` - Updated to support instant display with poster
- Video thumbnails stored in `/uploads/{category}/videos/thumbnails/`

**Technical Details:**
- FFmpeg extracts thumbnail: `-ss 00:00:01 -vframes 1 -q:v 2`
- Video optimization: `-c:v libx264 -profile:v main -crf 23 -movflags +faststart -c:a aac`
- Poster URL auto-generated from video filename
- Works with all video formats (.mp4, .mov, .avi, .webm, .mkv, .flv)

**Benefits:**
- Modal appears instantly when clicking preview
- Videos stream progressively (no full download required)
- Better perceived performance and user experience
- Automatic thumbnail generation for all uploads
- All videos optimized for web delivery

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
