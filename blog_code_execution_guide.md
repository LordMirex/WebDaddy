# WebDaddy Blog System - Code Execution Guide

## Purpose

This document serves as a **step-by-step execution tracker** for implementing the WebDaddy Blog System. It breaks the entire implementation into **5 sequential phases**, each with subsections and checkboxes for tracking progress.

**Rules:**
- Complete each phase fully before moving to the next
- Mark tasks with ✅ when completed, ❌ if blocked, ⏳ if in progress
- Each phase must be explicitly approved before proceeding to the next
- By Phase 5, all core functionality should be working

---

## Phase 1: Foundation Setup

**Goal:** Establish database structure, file organization, and base utilities.

**Prerequisites:** None - This is the starting point.

**Expected Outcome:** Database tables exist, folder structure is in place, helper functions are ready.

### 1.1 Database Schema Implementation

| Status | Task | Notes |
|--------|------|-------|
| ✅ | Create `blog_categories` table | Topic clusters with parent-child support |
| ✅ | Create `blog_posts` table | Core posts table with SEO fields |
| ✅ | Create `blog_blocks` table | 4-layer block architecture storage |
| ✅ | Create `blog_tags` table | Tag definitions |
| ✅ | Create `blog_post_tags` junction table | Many-to-many relationship |
| ✅ | Create `blog_internal_links` table | Topic cluster link tracking |
| ✅ | Create `blog_analytics` table | Event tracking |
| ✅ | Create `blog_comments` table | Optional comments system |
| ✅ | Add all performance indexes | As specified in schema |
| ✅ | Run migration and verify tables | Confirm all tables created |

### 1.2 File & Folder Structure

| Status | Task | Notes |
|--------|------|-------|
| ✅ | Create `blog/` directory | Public blog pages |
| ✅ | Create `blog/index.php` placeholder | Blog listing page |
| ✅ | Create `blog/post.php` placeholder | Single post router |
| ✅ | Create `blog/category.php` placeholder | Category archive |
| ✅ | Create `admin/blog/` directory | Admin blog management |
| ✅ | Create `admin/api/blog/` directory | Blog API endpoints |
| ✅ | Create `includes/blog/` directory | Blog includes/classes |
| ✅ | Create `includes/blog/blocks/` directory | Block renderers |
| ✅ | Create `assets/css/blog/` directory | Blog stylesheets |
| ✅ | Create `assets/js/blog/` directory | Blog JavaScript |
| ✅ | Create `uploads/blog/` directory | Blog image uploads |

### 1.3 Base Classes & Helpers

| Status | Task | Notes |
|--------|------|-------|
| ✅ | Create `includes/blog/Blog.php` | Core Blog class |
| ✅ | Create `includes/blog/BlogPost.php` | Post model class |
| ✅ | Create `includes/blog/BlogCategory.php` | Category model class |
| ✅ | Create `includes/blog/BlogBlock.php` | Block model class |
| ✅ | Create `includes/blog/BlogTag.php` | Tag model class |
| ✅ | Create `includes/blog/helpers.php` | Utility functions (slug generation, reading time calc, etc.) |
| ✅ | Create `includes/blog/schema.php` | JSON-LD schema generators |

### Phase 1 Sign-off

- [x] All database tables created and verified
- [x] File structure matches implementation plan
- [x] Base classes instantiate without errors
- [x] Helper functions tested

**Phase 1 Status:** ✅ Completed

---

## Phase 2: Core Blog Engine

**Goal:** Implement blog data models, CRUD operations, and basic routing.

**Prerequisites:** Phase 1 completed.

**Expected Outcome:** Can create, read, update, delete posts and categories. Basic URLs work.

### 2.1 Category Management

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement `BlogCategory::create()` | Insert new category |
| ⬜ | Implement `BlogCategory::update()` | Edit category |
| ⬜ | Implement `BlogCategory::delete()` | Soft/hard delete |
| ⬜ | Implement `BlogCategory::getAll()` | List all categories |
| ⬜ | Implement `BlogCategory::getById()` | Single category fetch |
| ⬜ | Implement `BlogCategory::getBySlug()` | URL-based lookup |
| ⬜ | Implement parent-child hierarchy | Nested categories support |
| ⬜ | Auto-generate slugs | From category name |

### 2.2 Tag Management

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement `BlogTag::create()` | Insert new tag |
| ⬜ | Implement `BlogTag::delete()` | Remove tag |
| ⬜ | Implement `BlogTag::getAll()` | List all tags |
| ⬜ | Implement `BlogTag::getByPost()` | Tags for a specific post |
| ⬜ | Implement `BlogTag::attachToPost()` | Add tag to post |
| ⬜ | Implement `BlogTag::detachFromPost()` | Remove tag from post |

### 2.3 Post Management (Basic)

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement `BlogPost::create()` | Insert new post (draft) |
| ⬜ | Implement `BlogPost::update()` | Edit post metadata |
| ⬜ | Implement `BlogPost::delete()` | Move to archived/delete |
| ⬜ | Implement `BlogPost::getById()` | Single post fetch |
| ⬜ | Implement `BlogPost::getBySlug()` | URL-based lookup |
| ⬜ | Implement `BlogPost::getPublished()` | List published posts |
| ⬜ | Implement `BlogPost::getByCategory()` | Posts in category |
| ⬜ | Implement `BlogPost::getByTag()` | Posts with tag |
| ⬜ | Implement status transitions | draft → published → archived |
| ⬜ | Implement scheduled publishing | Auto-publish at date |
| ⬜ | Implement reading time calculation | Based on content length |
| ⬜ | Auto-generate slugs | From post title |

### 2.4 Basic Routing

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement `blog/index.php` routing | Display post list |
| ⬜ | Implement `blog/post.php` routing | Display single post by slug |
| ⬜ | Implement `blog/category.php` routing | Display category archive |
| ⬜ | Implement pagination logic | For post listings |
| ⬜ | Implement 404 handling | For invalid slugs |

### Phase 2 Sign-off

- [ ] Can create/edit/delete categories via code
- [ ] Can create/edit/delete tags via code
- [ ] Can create/edit/delete/publish posts via code
- [ ] Blog listing page shows posts
- [ ] Single post page displays correctly
- [ ] Category pages filter correctly

**Phase 2 Status:** ⬜ Not Started

---

## Phase 3: Block System Implementation

**Goal:** Build the complete block rendering system with all 8 block types.

**Prerequisites:** Phase 2 completed.

**Expected Outcome:** All block types render correctly when added to posts.

### 3.1 Block Architecture

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `BlockRenderer` base class | Core block rendering logic |
| ⬜ | Implement block type registry | Map type to renderer |
| ⬜ | Implement `BlogBlock::create()` | Insert block for post |
| ⬜ | Implement `BlogBlock::update()` | Edit block data |
| ⬜ | Implement `BlogBlock::delete()` | Remove block |
| ⬜ | Implement `BlogBlock::reorder()` | Change block order |
| ⬜ | Implement `BlogBlock::getByPost()` | Get all blocks for post |
| ⬜ | Implement 4-layer model | Semantic, Layout, Data, Behavior |
| ⬜ | Implement JSON data validation | Per block type |

### 3.2 Block Type Renderers

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `blocks/hero_editorial.php` | Block Type 1 - Hero section |
| ⬜ | Create `blocks/rich_text.php` | Block Type 2 - Main content |
| ⬜ | Create `blocks/section_divider.php` | Block Type 3 - Dividers |
| ⬜ | Create `blocks/visual_explanation.php` | Block Type 4 - Text + image |
| ⬜ | Create `blocks/inline_conversion.php` | Block Type 5 - Mid-article CTAs |
| ⬜ | Create `blocks/internal_authority.php` | Block Type 6 - Related content |
| ⬜ | Create `blocks/faq_seo.php` | Block Type 7 - FAQ schema |
| ⬜ | Create `blocks/final_conversion.php` | Block Type 8 - End CTAs |

### 3.3 Block Layouts & Variants

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement layout variant system | Each block has layout options |
| ⬜ | Implement `default` layout for all blocks | Base styling |
| ⬜ | Implement `split_left` / `split_right` layouts | For applicable blocks |
| ⬜ | Implement `wide` / `contained` layouts | Width variants |
| ⬜ | Implement mobile responsive layouts | Auto-stacking behavior |

### 3.4 Block Behaviors

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement lazy loading behavior | For images/heavy blocks |
| ⬜ | Implement collapsible behavior | For FAQ blocks |
| ⬜ | Implement CTA tracking behavior | For conversion blocks |
| ⬜ | Implement visibility conditions | Conditional block display |
| ⬜ | Implement animation entrance | Optional entrance effects |

### Phase 3 Sign-off

- [ ] All 8 block types render without errors
- [ ] Blocks save/load JSON data correctly
- [ ] Layout variants display correctly
- [ ] Mobile responsiveness works
- [ ] Blocks can be reordered

**Phase 3 Status:** ⬜ Not Started

---

## Phase 4: Admin Interface

**Goal:** Build the admin dashboard for managing blog content.

**Prerequisites:** Phase 3 completed.

**Expected Outcome:** Full admin UI for creating/editing posts with block editor.

### 4.1 Admin Blog Dashboard

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `admin/blog/index.php` | Posts list with filters |
| ⬜ | Implement post status filters | Draft, Published, Scheduled, Archived |
| ⬜ | Implement category filters | Filter by category |
| ⬜ | Implement search functionality | Search posts |
| ⬜ | Implement bulk actions | Delete, change status |
| ⬜ | Implement sorting | By date, title, views |
| ⬜ | Add quick stats | Total posts, views, etc. |

### 4.2 Category & Tag Admin

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `admin/blog/categories.php` | Category management |
| ⬜ | Implement category CRUD UI | Add, edit, delete |
| ⬜ | Implement category hierarchy UI | Parent-child display |
| ⬜ | Create `admin/blog/tags.php` | Tag management |
| ⬜ | Implement tag CRUD UI | Add, delete, view usage |

### 4.3 Block Editor Interface

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `admin/blog/editor.php` | Main editor page |
| ⬜ | Implement block palette | List of available blocks |
| ⬜ | Implement drag-and-drop block adding | Add blocks to post |
| ⬜ | Implement block reordering | Drag to reorder |
| ⬜ | Implement block editing modal | Edit block content/settings |
| ⬜ | Implement block deletion | Remove blocks |
| ⬜ | Implement block duplication | Copy existing block |
| ⬜ | Implement layout variant selector | Per block |
| ⬜ | Implement behavior toggles | Per block |
| ⬜ | Implement live preview | Real-time preview |
| ⬜ | Create block-specific edit forms | For each block type |

### 4.4 Post Settings & Meta

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement post title/slug editing | Basic post info |
| ⬜ | Implement category selector | Assign to category |
| ⬜ | Implement tag selector | Multi-select tags |
| ⬜ | Implement featured image upload | Hero image |
| ⬜ | Implement SEO meta panel | Title, description, keywords |
| ⬜ | Implement social sharing panel | OG/Twitter meta |
| ⬜ | Implement publish settings | Status, schedule date |
| ⬜ | Implement author settings | Name, avatar |

### 4.5 Admin API Endpoints

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `admin/api/blog/posts.php` | Post CRUD API |
| ⬜ | Create `admin/api/blog/blocks.php` | Block operations API |
| ⬜ | Create `admin/api/blog/upload.php` | Image upload API |
| ⬜ | Create `admin/api/blog/preview.php` | Preview generation |
| ⬜ | Create `admin/api/blog/categories.php` | Category API |
| ⬜ | Create `admin/api/blog/tags.php` | Tag API |
| ⬜ | Implement proper authentication | Admin-only access |
| ⬜ | Implement CSRF protection | Secure forms |

### Phase 4 Sign-off

- [ ] Can manage categories from admin
- [ ] Can manage tags from admin
- [ ] Can create new post with blocks
- [ ] Can edit existing posts/blocks
- [ ] Can set post SEO metadata
- [ ] Can upload images
- [ ] Can preview posts
- [ ] Can publish/schedule posts

**Phase 4 Status:** ⬜ Not Started

---

## Phase 5: Frontend, SEO & Conversion

**Goal:** Polish public pages, implement SEO features, and add conversion elements.

**Prerequisites:** Phase 4 completed.

**Expected Outcome:** Fully functional blog with SEO optimization and conversion tracking.

### 5.1 Public Blog Styling

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Create `assets/css/blog/main.css` | Core blog styles |
| ⬜ | Create `assets/css/blog/blocks.css` | Block-specific styles |
| ⬜ | Create `assets/css/blog/responsive.css` | Mobile responsiveness |
| ⬜ | Style blog listing page | Matches WebDaddy aesthetic |
| ⬜ | Style single post page | Premium reading experience |
| ⬜ | Style category archive page | Clean archive layout |
| ⬜ | Implement dark mode support | If applicable |
| ⬜ | Implement typography optimization | Line length, spacing |

### 5.2 Sticky Conversion Rail

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement desktop sticky sidebar | Right-side rail |
| ⬜ | Add "Get a Website" CTA card | Primary conversion |
| ⬜ | Add featured template suggestions | 2-3 templates |
| ⬜ | Add WhatsApp contact button | Quick contact |
| ⬜ | Implement affiliate message display | If `aff` parameter |
| ⬜ | Implement mobile bottom sticky bar | Mobile conversion |
| ⬜ | Add scroll-to-top button | After 50% scroll |
| ⬜ | Implement scroll-stop before footer | Clean cutoff |

### 5.3 SEO Implementation

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement Article schema generation | Automatic JSON-LD |
| ⬜ | Implement FAQPage schema | For FAQ blocks |
| ⬜ | Implement BreadcrumbList schema | Navigation breadcrumbs |
| ⬜ | Implement canonical URLs | Prevent duplicates |
| ⬜ | Implement meta title generation | With fallbacks |
| ⬜ | Implement meta description generation | With fallbacks |
| ⬜ | Implement Open Graph tags | Facebook/LinkedIn |
| ⬜ | Implement Twitter Card tags | Twitter sharing |
| ⬜ | Implement sitemap generation | Blog URLs in sitemap |
| ⬜ | Implement heading hierarchy check | Single H1 enforcement |
| ⬜ | Implement auto-generated alt text | From headings |

### 5.4 Analytics & Tracking

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement page view tracking | `blog_analytics` insert |
| ⬜ | Implement scroll depth tracking | 25%, 50%, 75%, 100% |
| ⬜ | Implement CTA click tracking | Conversion events |
| ⬜ | Implement share button tracking | Social shares |
| ⬜ | Implement template click tracking | Template referrals |
| ⬜ | Create `api/blog/analytics.php` | Public analytics endpoint |
| ⬜ | Create `api/blog/share.php` | Share count updates |
| ⬜ | Create `admin/blog/analytics.php` | Analytics dashboard |
| ⬜ | Display view counts | On admin and optionally public |

### 5.5 Affiliate Integration

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Implement `aff` parameter detection | Track affiliate code |
| ⬜ | Implement affiliate message in CTAs | Custom messaging |
| ⬜ | Implement affiliate tracking in analytics | Log affiliate clicks |
| ⬜ | Implement affiliate-aware template links | Pass through aff code |

### 5.6 Seed Content Setup

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Insert pre-defined blog categories | 9 categories from Content Strategy (includes Nigeria Business) |
| ⬜ | Create initial topic cluster tags | Tags matching the 21 clusters (includes Nigeria) |
| ⬜ | Set up priority content schedule | 10 high-priority posts identified |
| ⬜ | Create sample pillar post template | Using Cluster 1 as example |
| ⬜ | Document content creation workflow | For admin content writers |

**Pre-Seed Categories (from blog_implementation.md Section 23):**
- Getting Started
- Website Design  
- SEO & Marketing
- E-commerce
- Industry Guides
- Domain Names
- Tools & Resources
- Success Stories
- **Nigeria Business** (Local SEO boost)

**Priority Posts to Create First:**
1. How Much Does a Small Business Website Cost in 2025?
2. Best Website Templates for Nigerian Businesses in 2025
3. How to Choose the Perfect Domain Name for Your Business
4. Complete SEO Guide for Small Business Websites
5. Start Selling Online in Nigeria: Complete E-commerce Guide
6. Website Conversion Secrets: Turn Visitors Into Customers
7. Nigerian Business Success Stories
8. Start Earning with Affiliate Marketing in Nigeria
9. **Why Nigerian Businesses Need a Professional Website in 2025** (Nigeria SEO)
10. **Best Payment Gateways for Nigerian Websites: Paystack, Flutterwave & More** (Nigeria SEO)

---

### 5.7 Final Polish & Testing

| Status | Task | Notes |
|--------|------|-------|
| ⬜ | Test all block types on published post | Rendering verification |
| ⬜ | Test mobile responsiveness | All breakpoints |
| ⬜ | Test SEO meta output | Validate with tools |
| ⬜ | Test schema markup | Google Rich Results Test |
| ⬜ | Test conversion elements | Clicks tracked |
| ⬜ | Test affiliate flow | End-to-end |
| ⬜ | Test admin permissions | Proper access control |
| ⬜ | Performance check | Page load speed |
| ⬜ | Cross-browser testing | Chrome, Firefox, Safari, Edge |

### Phase 5 Sign-off

- [ ] Blog pages match WebDaddy design aesthetic
- [ ] Sticky rail works on desktop and mobile
- [ ] All SEO schema validates correctly
- [ ] Analytics events fire and record
- [ ] Affiliate tracking works end-to-end
- [ ] No critical bugs or errors
- [ ] Performance acceptable

**Phase 5 Status:** ⬜ Not Started

---

## Execution Summary

| Phase | Name | Status | Dependencies |
|-------|------|--------|--------------|
| 1 | Foundation Setup | ✅ Completed | None |
| 2 | Core Blog Engine | ⬜ Not Started | Phase 1 |
| 3 | Block System | ⬜ Not Started | Phase 2 |
| 4 | Admin Interface | ⬜ Not Started | Phase 3 |
| 5 | Frontend, SEO & Conversion | ⬜ Not Started | Phase 4 |

---

## Status Legend

| Symbol | Meaning |
|--------|---------|
| ⬜ | Not started |
| ⏳ | In progress |
| ✅ | Completed |
| ❌ | Blocked / Issue |
| 🔄 | Needs revision |

---

## Changelog

| Date | Phase | Change | Notes |
|------|-------|--------|-------|
| 2024-12-17 | 1 | Phase 1 Complete | All database tables, directories, and base classes created |

---

**Document Created:** 2024-12-17  
**Last Updated:** 2024-12-17  
**Current Phase:** Phase 1 - Completed (Ready for Phase 2)
