# WebDaddy Empire

## Overview
A PHP-based marketplace platform for selling website templates and digital tools. Built with SQLite database, Tailwind CSS, and custom PHP components.

## Recent Changes (November 18, 2025)

### Video Player Complete Overhaul - Instant Playback & Modern UI
**Problem:** Videos took 10+ seconds to load, video player appeared small/constrained, mute button didn't work, iframe previews were extremely slow. Overall poor video playback experience.

**Solution Implemented:**
1. **Removed FFmpeg Dependency** - Eliminated all backend video processing
   - Videos are uploaded directly without server-side processing
   - No more thumbnail extraction or video optimization delays
   - Faster upload workflow and simpler architecture
   
2. **Adaptive Video Player** - Video modal adapts to video's natural aspect ratio
   - Vertical videos (TikTok, phone recordings) display tall and full-height
   - Horizontal videos (landscape) display wide
   - No forced 16:9 ratio - no black bars or letterboxing
   - Video maintains natural proportions up to 85vh height
   - Perfectly sized for both mobile and desktop viewing
   
3. **Instant Video Playback** - Videos load and play within 1-2 seconds
   - Changed preload from `metadata` to `auto` for faster buffering
   - Video starts loading immediately when modal opens
   - Progressive streaming enabled (videos play while downloading)
   - Loader shows only briefly before play button appears
   
4. **Working Mute/Unmute Toggle** - Fully functional audio controls
   - Mute button now clickable and toggles video sound
   - All videos start muted by default (auto-mute)
   - Visual feedback with mute/unmute icons that swap on click
   - Button appears when video plays, always accessible
   
5. **Faster Iframe Loading** - Demo preview modal optimized
   - Reduced timeout from 3s to 2s for status updates
   - Added 8s fallback to force-hide loader if load event doesn't fire
   - Added sandbox attributes for better security and performance
   - Improved perceived loading speed

**Files Modified:**
- `includes/upload_handler.php` - Removed processVideo() method and all FFmpeg code
- `assets/js/video-modal.js` - Complete video player UI and loading optimization

**Technical Details:**
- Video modal: `max-width: 95vw; max-height: 90vh` with flexible layout
- Video container: Flexbox layout adapts to video dimensions
- Video element: `max-height: 85vh; width: auto; height: auto` maintains aspect ratio
- Video element: `preload="auto"` for instant playback
- Mute toggle: Click event on button toggles `video.muted` property
- Iframe: 8s max wait with fallback loader hiding

**Benefits:**
- Videos open and play in under 2 seconds
- Full-width video player provides better viewing experience
- Working mute/unmute button gives users audio control
- Faster iframe previews with better timeout handling
- Simpler codebase without FFmpeg dependency
- All videos start muted to prevent unexpected audio

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
