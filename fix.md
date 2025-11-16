# Media Handling Fix - Task Checklist

## 🎯 Problem Summary
The current admin forms confuse different media types:
- Templates mix demo URLs (iframe), banner images, and videos in the same field
- Tools have unnecessary video/demo inputs
- Image cropper lacks UX feedback
- Frontend doesn't know whether to show iframe, video modal, or just image

## ✅ Solution Overview
1. Add `media_type` column to templates table
2. Templates admin form: Choose ONE media type (demo URL, banner, or video)
3. Tools admin form: Remove video/demo inputs, only banner image
4. Improve image cropper UX with feedback and presets
5. Update frontend to check media_type before rendering

---

## 📋 Task Checklist

### 1️⃣ Database Schema Updates
- [x] Add `media_type` column to templates table
- [x] Add `demo_video_url` column to templates table (separate from demo_url)
- [x] Create migration script to classify existing templates
- [x] Test migration on development database
- [ ] Verify all existing templates still display correctly

### 2️⃣ Templates Admin Form (admin/templates.php)
- [x] Add "Media Type" section with radio buttons (Demo URL / Banner / Video)
- [x] Show/hide inputs based on selected media type
- [x] Add helper text for each option
- [x] Update backend INSERT/UPDATE to save media_type and demo_video_url
- [x] Backend: Clear unused media fields based on selected media_type
- [x] Backend: Validate only one media type has data
- [x] Update edit mode to pre-select correct media type from database
- [x] Add client-side validation before form submit

### 3️⃣ Tools Admin Form (admin/tools.php)
- [x] Frontend: Remove demo URL input section from form UI (N/A - never existed)
- [x] Frontend: Remove video upload section from form UI (N/A - never existed)
- [x] Frontend: Keep only thumbnail image section
- [x] Frontend: Make thumbnail required with validation (client + server-side)
- [x] Frontend: Update form labels to clarify "Product Banner Image"
- [x] Backend: Update INSERT/UPDATE handlers to reject demo/video fields (N/A - never processed)
- [x] Backend: Remove demo/video processing logic from save handlers (removed legacy JS)
- [x] Migration: Clear any legacy demo/video data from existing tools (N/A - columns don't exist)

### 4️⃣ Image Cropper UX Improvements (assets/js/image-cropper.js)
- [x] Add aspect ratio presets (16:9, 4:3, 1:1, Free) - Already implemented
- [x] Add image preview before cropping - Handled by cropper display
- [x] Show cropping area dimensions (live feedback)
- [x] Add loading spinner during crop processing - Implemented in form submit
- [x] Show success/error messages - Implemented via alerts
- [x] Better instructions and file size limits displayed

### 5️⃣ Frontend Display Updates
- [x] template.php: Check media_type and show iframe/video/banner accordingly
- [x] index.php: Update templates grid to use media_type (uses server-side rendering, no changes needed)
- [x] index.php: Keep tools as-is (banner only) - already correct
- [x] api/ajax-products.php: Updated template rendering to use media_type

### 6️⃣ Testing & Validation
- [ ] Create new template with demo_url → verify iframe works
- [ ] Create new template with banner → verify displays correctly
- [ ] Create new template with video → verify video modal works
- [ ] Create new tool with banner → verify displays correctly
- [ ] Test image cropper with all aspect ratios
- [ ] Test lazy loading on all pages
- [ ] Verify no console errors

### 7️⃣ Cleanup & Documentation
- [ ] Remove unused code
- [ ] Update replit.md with media type system
- [ ] Verify no PHP errors in logs

---

## 🎉 Completion Criteria
- [ ] Admin can create templates with demo URL → iframe works
- [ ] Admin can create templates with banner only → displays correctly
- [ ] Admin can create templates with video → video modal works
- [ ] Admin can create tools with banner → displays correctly
- [ ] Image cropper provides clear feedback and presets
- [ ] All existing templates/tools still work after migration
