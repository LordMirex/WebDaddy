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
- [ ] Add "Media Type" section with radio buttons (Demo URL / Banner / Video)
- [ ] Show/hide inputs based on selected media type
- [ ] Add helper text for each option
- [ ] Update backend INSERT/UPDATE to save media_type and demo_video_url
- [ ] Backend: Clear unused media fields based on selected media_type
- [ ] Backend: Validate only one media type has data
- [ ] Update edit mode to pre-select correct media type from database
- [ ] Add client-side validation before form submit

### 3️⃣ Tools Admin Form (admin/tools.php)
- [ ] Frontend: Remove demo URL input section from form UI
- [ ] Frontend: Remove video upload section from form UI
- [ ] Frontend: Keep only thumbnail image section
- [ ] Frontend: Make thumbnail required with validation
- [ ] Frontend: Update form labels to clarify "Product Banner Image"
- [ ] Backend: Update INSERT/UPDATE handlers to reject demo/video fields
- [ ] Backend: Remove demo/video processing logic from save handlers
- [ ] Migration: Clear any legacy demo/video data from existing tools

### 4️⃣ Image Cropper UX Improvements (assets/js/image-cropper.js)
- [ ] Add aspect ratio presets (16:9, 4:3, 1:1, Free)
- [ ] Add image preview before cropping
- [ ] Show cropping area dimensions
- [ ] Add loading spinner during crop processing
- [ ] Show success/error messages
- [ ] Better instructions and file limits

### 5️⃣ Frontend Display Updates
- [ ] template.php: Check media_type and show iframe/video/banner accordingly
- [ ] index.php: Update templates grid to use media_type
- [ ] index.php: Keep tools as-is (banner only)
- [ ] api/ajax-products.php: Include media_type in JSON response

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
