# WebDaddy Blog System - Implementation Blueprint

## Overview

This document outlines the complete implementation plan for an enterprise-level blog system for WebDaddy Empire. The blog is designed as a **content engine** — not just posts, but a strategic tool for SEO dominance, user education, and conversion funneling back to templates and affiliate traffic.

**Design Philosophy:**
- Matches the premium WebDaddy index page aesthetic
- Plain PHP friendly (no complex frameworks)
- SEO-first architecture
- Conversion-optimized at every level
- Affiliate-aware throughout

---

## 1. Database Schema

### Core Tables

```sql
-- Blog Categories (Topic Clusters)
CREATE TABLE IF NOT EXISTS blog_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    meta_title TEXT,
    meta_description TEXT,
    parent_id INTEGER DEFAULT NULL,
    display_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES blog_categories(id) ON DELETE SET NULL
);

-- Blog Posts
CREATE TABLE IF NOT EXISTS blog_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    excerpt TEXT,
    featured_image TEXT,
    featured_image_alt TEXT,
    category_id INTEGER,
    author_name TEXT DEFAULT 'WebDaddy Team',
    author_avatar TEXT,
    status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'published', 'scheduled', 'archived')),
    publish_date DATETIME,
    reading_time_minutes INTEGER DEFAULT 5,
    
    -- SEO Fields
    meta_title TEXT,
    meta_description TEXT,
    canonical_url TEXT,
    focus_keyword TEXT,
    seo_score INTEGER DEFAULT 0,
    
    -- Social Sharing
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    twitter_title TEXT,
    twitter_description TEXT,
    twitter_image TEXT,
    
    -- Analytics
    view_count INTEGER DEFAULT 0,
    share_count INTEGER DEFAULT 0,
    
    -- Affiliate Integration
    primary_template_id INTEGER,
    show_affiliate_ctas INTEGER DEFAULT 1,
    
    -- Timestamps
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
);

-- Blog Blocks (Content Units)
CREATE TABLE IF NOT EXISTS blog_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    block_type TEXT NOT NULL,
    display_order INTEGER NOT NULL,
    
    -- 4-Layer Block Architecture
    semantic_role TEXT DEFAULT 'primary_content' CHECK(semantic_role IN (
        'primary_content', 'supporting_content', 'conversion_content', 'authority_content'
    )),
    layout_variant TEXT DEFAULT 'default',
    data_payload TEXT NOT NULL, -- JSON content
    behavior_config TEXT, -- JSON behavior settings
    
    -- Visibility
    is_visible INTEGER DEFAULT 1,
    visibility_conditions TEXT, -- JSON conditions
    
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Blog Tags (Many-to-Many)
CREATE TABLE IF NOT EXISTS blog_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

-- Internal Links Tracking (Topic Clusters)
CREATE TABLE IF NOT EXISTS blog_internal_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_post_id INTEGER NOT NULL,
    target_post_id INTEGER NOT NULL,
    anchor_text TEXT,
    link_type TEXT DEFAULT 'related' CHECK(link_type IN ('related', 'series', 'prerequisite', 'followup')),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Blog Analytics
CREATE TABLE IF NOT EXISTS blog_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    event_type TEXT NOT NULL CHECK(event_type IN ('view', 'scroll_25', 'scroll_50', 'scroll_75', 'scroll_100', 'cta_click', 'share', 'template_click')),
    session_id TEXT,
    referrer TEXT,
    affiliate_code TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Blog Comments (Optional - for future)
CREATE TABLE IF NOT EXISTS blog_comments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    customer_id INTEGER,
    author_name TEXT,
    author_email TEXT,
    content TEXT NOT NULL,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'approved', 'spam', 'deleted')),
    parent_id INTEGER,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_id) REFERENCES blog_comments(id) ON DELETE CASCADE
);

-- Indexes for Performance
CREATE INDEX IF NOT EXISTS idx_blog_posts_status ON blog_posts(status);
CREATE INDEX IF NOT EXISTS idx_blog_posts_category ON blog_posts(category_id);
CREATE INDEX IF NOT EXISTS idx_blog_posts_publish_date ON blog_posts(publish_date);
CREATE INDEX IF NOT EXISTS idx_blog_posts_slug ON blog_posts(slug);
CREATE INDEX IF NOT EXISTS idx_blog_blocks_post ON blog_blocks(post_id);
CREATE INDEX IF NOT EXISTS idx_blog_blocks_order ON blog_blocks(post_id, display_order);
CREATE INDEX IF NOT EXISTS idx_blog_analytics_post ON blog_analytics(post_id);
CREATE INDEX IF NOT EXISTS idx_blog_analytics_date ON blog_analytics(created_at);
```

---

## 2. Block System Architecture

### The 4-Layer Block Model

Each block is NOT just a UI piece. It's a **semantic content unit with layout intelligence**.

```
┌─────────────────────────────────────────────────────────────┐
│                      BLOCK STRUCTURE                        │
├─────────────────────────────────────────────────────────────┤
│  Layer 1: SEMANTIC ROLE (What Google sees)                 │
│  ├─ primary_content    → Main article content              │
│  ├─ supporting_content → Additional context, examples      │
│  ├─ conversion_content → CTAs, offers, promotions          │
│  └─ authority_content  → Proof, links, credentials         │
├─────────────────────────────────────────────────────────────┤
│  Layer 2: VISUAL LAYOUT (What user sees)                   │
│  ├─ default            → Standard centered layout          │
│  ├─ split_left         → Text left, media right            │
│  ├─ split_right        → Media left, text right            │
│  ├─ wide               → Full-bleed, edge-to-edge          │
│  ├─ contained          → Narrow, focused reading           │
│  ├─ card_grid          → Multiple items in grid            │
│  └─ timeline           → Sequential/stepped content        │
├─────────────────────────────────────────────────────────────┤
│  Layer 3: DATA PAYLOAD (What admin edits)                  │
│  └─ JSON structure specific to block type                  │
├─────────────────────────────────────────────────────────────┤
│  Layer 4: BEHAVIOR (How it acts)                           │
│  ├─ sticky             → Stays visible on scroll           │
│  ├─ collapsible        → Can expand/collapse               │
│  ├─ lazy_loaded        → Loads on scroll into view         │
│  ├─ animated           → Has entrance animation            │
│  └─ cta_aware          → Tracks conversion events          │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. Block Types Specification

### Block Type 1: Hero Editorial Block

**Purpose:** Article hero section with strong SERP preview potential

**Semantic Role:** `primary_content`

**Data Payload Schema:**
```json
{
    "h1_title": "string (required, max 60 chars for SEO)",
    "subtitle": "string (keyword-expanded, max 160 chars)",
    "featured_image": "string (URL)",
    "featured_image_alt": "string (auto-generated from title if empty)",
    "category": {
        "name": "string",
        "slug": "string"
    },
    "reading_time": "integer (minutes)",
    "publish_date": "datetime",
    "author": {
        "name": "string",
        "avatar": "string (URL)"
    },
    "share_buttons": ["whatsapp", "twitter", "facebook", "linkedin", "copy"]
}
```

**Layout Variants:**
- `default` - Centered hero with overlay text
- `split` - Image left, content right
- `minimal` - No image, typography-focused

**Behavior:**
- Generates Article schema automatically
- Enforces single H1 per page
- Auto-calculates reading time from content

**Rendering (PHP):**
```php
// includes/blog/blocks/hero_editorial.php
function render_hero_editorial($data, $layout = 'default', $behavior = []) {
    $schema = generate_article_schema($data);
    // ... render based on layout variant
}
```

---

### Block Type 2: Rich Text Editorial Block

**Purpose:** Main article body with advanced typography

**Semantic Role:** `primary_content`

**Data Payload Schema:**
```json
{
    "content": "string (HTML with allowed tags)",
    "allowed_elements": {
        "headings": ["h2", "h3", "h4"],
        "formatting": ["strong", "em", "u", "s"],
        "links": ["a"],
        "lists": ["ul", "ol", "li"],
        "media": ["img", "figure", "figcaption"],
        "special": ["blockquote", "code", "pre", "mark"]
    },
    "typography_settings": {
        "line_length": "optimal|wide|narrow",
        "paragraph_spacing": "normal|relaxed|compact"
    }
}
```

**Special Elements Supported:**
- **Inline Highlights:** `<mark class="highlight-yellow">text</mark>`
- **Callouts:** `<div class="callout callout-info">...</div>`
- **Pull Quotes:** `<blockquote class="pull-quote">...</blockquote>`
- **Code Blocks:** `<pre><code class="language-php">...</code></pre>`

**Layout Variants:**
- `default` - Optimal 65-75 character line length
- `wide` - Full container width
- `narrow` - Extra focused reading width

**Behavior:**
- Auto-generates anchor IDs for headings (table of contents)
- Lazy loads images
- Syntax highlighting for code blocks

---

### Block Type 3: Section Divider Block

**Purpose:** Reset attention, improve readability, increase scroll depth

**Semantic Role:** `supporting_content`

**Data Payload Schema:**
```json
{
    "divider_type": "line|gradient|labeled|space",
    "label_text": "string (for labeled type, e.g., 'Step 1', 'Why This Matters')",
    "label_style": "badge|inline|large",
    "spacing": "small|medium|large",
    "color_scheme": "default|accent|subtle"
}
```

**Layout Variants:**
- `line` - Simple horizontal rule
- `gradient` - Gradient fade divider
- `labeled` - Divider with centered label
- `space` - Just vertical spacing, no visual

---

### Block Type 4: Visual Explanation Block

**Purpose:** Explain processes, comparisons, or flows with text + image

**Semantic Role:** `primary_content` or `supporting_content`

**Data Payload Schema:**
```json
{
    "heading": "string (h2 or h3)",
    "heading_level": "h2|h3",
    "content": "string (HTML)",
    "image": {
        "url": "string",
        "alt": "string (auto-generated from heading if empty)",
        "caption": "string (optional)"
    },
    "image_position": "left|right",
    "cta": {
        "enabled": false,
        "text": "string",
        "url": "string",
        "style": "primary|secondary|ghost"
    }
}
```

**Layout Variants:**
- `split_left` - Text left, image right
- `split_right` - Image left, text right
- `stacked` - Image above text (mobile default)

**Behavior:**
- Auto-switches to stacked on mobile
- Image alt auto-generated from heading
- Optional embedded CTA

---

### Block Type 5: Inline Conversion Block (CRITICAL)

**Purpose:** Convert readers mid-article (not just at the end)

**Semantic Role:** `conversion_content`

**Data Payload Schema:**
```json
{
    "headline": "string (e.g., 'Want this done for you?')",
    "subheadline": "string (e.g., 'Get this website in 24 hours.')",
    "style": "card|banner|minimal|floating",
    "background": "gradient|solid|image",
    "background_value": "string (gradient CSS or color or image URL)",
    "cta_primary": {
        "text": "string (e.g., 'View Templates')",
        "url": "string",
        "action": "link|modal|whatsapp"
    },
    "cta_secondary": {
        "enabled": false,
        "text": "string",
        "url": "string"
    },
    "template_id": "integer (optional - link to specific template)",
    "affiliate_aware": true,
    "show_price": false,
    "urgency_text": "string (optional, e.g., 'Limited offer')"
}
```

**Layout Variants:**
- `card` - Contained card with shadow
- `banner` - Full-width accent banner
- `minimal` - Subtle inline with icon
- `floating` - Side-attached floating card

**Behavior:**
- `cta_aware` - Tracks clicks for analytics
- Shows affiliate message if `aff` parameter present
- Can trigger template modal

---

### Block Type 6: Internal Authority Block

**Purpose:** Reduce bounce, improve crawl depth, build topical authority

**Semantic Role:** `authority_content`

**Data Payload Schema:**
```json
{
    "heading": "string (e.g., 'Related Articles', 'Explore More')",
    "display_type": "cards|list|compact",
    "source": "auto|manual",
    "auto_config": {
        "type": "related_posts|same_category|popular|recent",
        "limit": 3
    },
    "manual_items": [
        {
            "type": "post|template|external",
            "id": "integer (for post/template)",
            "url": "string (for external)",
            "title": "string (override)",
            "description": "string (override)",
            "image": "string (override)"
        }
    ],
    "show_templates": true,
    "templates_heading": "string (e.g., 'Templates for You')",
    "template_limit": 3
}
```

**Layout Variants:**
- `cards` - Grid of cards with images
- `list` - Vertical list with thumbnails
- `compact` - Simple link list

**Behavior:**
- Auto-pulls related posts by category/tags
- Can mix blog posts and templates
- Tracks click-through for analytics

---

### Block Type 7: FAQ SEO Block

**Purpose:** Boost rankings with FAQ schema markup

**Semantic Role:** `authority_content`

**Data Payload Schema:**
```json
{
    "heading": "string (e.g., 'Frequently Asked Questions')",
    "heading_level": "h2|h3",
    "items": [
        {
            "question": "string (becomes h3)",
            "answer": "string (HTML allowed)",
            "is_open": false
        }
    ],
    "schema_enabled": true,
    "style": "accordion|expanded|simple"
}
```

**Layout Variants:**
- `accordion` - Collapsible FAQ items
- `expanded` - All visible, no collapse
- `simple` - Minimal styling

**Behavior:**
- Generates FAQPage schema automatically
- Each question renders as `<h3>`
- Answers concise for featured snippets

**Schema Output:**
```json
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
        {
            "@type": "Question",
            "name": "...",
            "acceptedAnswer": {
                "@type": "Answer",
                "text": "..."
            }
        }
    ]
}
```

---

### Block Type 8: Final Conversion Block

**Purpose:** Emotion + urgency at article end

**Semantic Role:** `conversion_content`

**Data Payload Schema:**
```json
{
    "headline": "string (emotional, action-oriented)",
    "subheadline": "string",
    "style": "hero|card|split|minimal",
    "background": "gradient|solid|image|pattern",
    "background_value": "string",
    "cta_config": {
        "type": "whatsapp|template_selector|affiliate|custom",
        "whatsapp": {
            "message": "string (pre-filled message)",
            "button_text": "string"
        },
        "template_selector": {
            "heading": "string",
            "category_filter": "integer|null",
            "limit": 6
        },
        "custom": {
            "button_text": "string",
            "url": "string"
        }
    },
    "trust_elements": {
        "enabled": true,
        "items": ["fast_delivery", "secure_payment", "support_24_7", "satisfaction"]
    },
    "affiliate_override": {
        "enabled": false,
        "message": "string",
        "discount_highlight": "string"
    }
}
```

**Layout Variants:**
- `hero` - Full-width with large typography
- `card` - Contained elevated card
- `split` - Image/illustration + CTA side by side
- `minimal` - Simple centered CTA

---

## 4. Sticky Conversion Rail

### Desktop Implementation

On screens >= 1024px, a sticky sidebar appears on the right side of blog content.

**Structure:**
```
┌─────────────────────────────────────────────────────────────┐
│  MAIN CONTENT (70%)              │  STICKY RAIL (30%)      │
│                                  │  ┌─────────────────┐    │
│  [Hero Block]                    │  │ Get a Website   │    │
│                                  │  │ ─────────────── │    │
│  [Content Blocks]                │  │ Browse our      │    │
│                                  │  │ premium         │    │
│  [More Blocks...]                │  │ templates       │    │
│                                  │  │                 │    │
│                                  │  │ [View Templates]│    │
│                                  │  ├─────────────────┤    │
│                                  │  │ Featured:       │    │
│                                  │  │ [Template Card] │    │
│                                  │  │ [Template Card] │    │
│                                  │  ├─────────────────┤    │
│                                  │  │ 💬 WhatsApp    │    │
│                                  │  │ Chat with us    │    │
│                                  │  ├─────────────────┤    │
│                                  │  │ {Affiliate Msg} │    │
│                                  │  └─────────────────┘    │
└─────────────────────────────────────────────────────────────┘
```

**Rail Contents:**
1. "Get a Website" CTA card
2. Featured template suggestions (2-3)
3. WhatsApp contact button
4. Affiliate message (if `aff` parameter present)

**Behavior:**
- `position: sticky` with `top: 100px`
- Stops before footer
- Tracks scroll position for analytics

### Mobile Implementation

On screens < 1024px, the rail converts to a bottom sticky bar.

```
┌─────────────────────────────────────────────────────────────┐
│                     MOBILE CONTENT                          │
│                                                             │
│  [Full-width blocks stacked]                               │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│  💬 WhatsApp    │    🛒 View Templates    │    ▲ Scroll    │
│                 │                          │                │
└─────────────────────────────────────────────────────────────┘
                  ↑ STICKY BOTTOM BAR ↑
```

**Mobile Bar Contents:**
- WhatsApp quick action
- View Templates button
- Scroll to top (appears after 50% scroll)

---

## 5. File Structure

```
webdaddy/
├── blog/
│   ├── index.php              # Blog listing page
│   ├── post.php               # Single blog post (router)
│   └── category.php           # Category archive
│
├── admin/
│   ├── blog/
│   │   ├── index.php          # Blog posts list
│   │   ├── editor.php         # Block editor interface
│   │   ├── categories.php     # Category management
│   │   ├── tags.php           # Tag management
│   │   └── analytics.php      # Blog analytics
│   │
│   └── api/
│       └── blog/
│           ├── posts.php      # CRUD for posts
│           ├── blocks.php     # Block operations
│           ├── upload.php     # Image uploads
│           └── preview.php    # Preview generation
│
├── includes/
│   └── blog/
│       ├── functions.php      # Core blog functions
│       ├── seo.php            # SEO generation
│       ├── schema.php         # JSON-LD schemas
│       ├── renderer.php       # Block renderer
│       │
│       └── blocks/            # Block renderers
│           ├── hero_editorial.php
│           ├── rich_text.php
│           ├── section_divider.php
│           ├── visual_explanation.php
│           ├── inline_conversion.php
│           ├── internal_authority.php
│           ├── faq_seo.php
│           └── final_conversion.php
│
├── assets/
│   ├── css/
│   │   └── blog.css           # Blog-specific styles
│   ├── js/
│   │   ├── blog.js            # Blog frontend JS
│   │   └── admin/
│   │       └── block-editor.js # Admin block editor
│   └── images/
│       └── blog/              # Blog images
│
└── api/
    └── blog/
        ├── analytics.php      # Track events
        └── share.php          # Share count updates
```

---

## 6. Admin Block Editor

### Editor Interface Design

The admin blog editor is a **visual block editor** — admins never touch HTML.

```
┌─────────────────────────────────────────────────────────────┐
│  📝 Edit Post: "Best Business Website in Nigeria"          │
│  ─────────────────────────────────────────────────────────  │
│  [Save Draft]  [Preview ▼]  [Publish]     SEO Score: 85/100│
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  BLOCKS                          │  SETTINGS               │
│  ┌─────────────────────────────┐ │  ┌───────────────────┐  │
│  │ 📰 Hero Editorial       ≡⋮ │ │  │ Post Settings     │  │
│  │ "Best Business Website..." │ │  │ ───────────────── │  │
│  └─────────────────────────────┘ │  │ Category: [▼]    │  │
│                                  │  │ Tags: [+ Add]    │  │
│  ┌─────────────────────────────┐ │  │ Author: [▼]      │  │
│  │ 📝 Rich Text            ≡⋮ │ │  │ Publish: [Date]  │  │
│  │ "In today's digital..."    │ │  ├───────────────────┤  │
│  └─────────────────────────────┘ │  │ SEO Settings      │  │
│                                  │  │ ───────────────── │  │
│  ┌─────────────────────────────┐ │  │ Focus Keyword:   │  │
│  │ 🖼️ Visual Explanation   ≡⋮ │ │  │ [____________]   │  │
│  │ [Image] + Text             │ │  │ Meta Title:      │  │
│  └─────────────────────────────┘ │  │ [____________]   │  │
│                                  │  │ Meta Desc:       │  │
│  ┌─────────────────────────────┐ │  │ [____________]   │  │
│  │ 💰 Inline Conversion    ≡⋮ │ │  │                   │  │
│  │ "Want this done for you?"  │ │  │ [SEO Analysis ▼] │  │
│  └─────────────────────────────┘ │  ├───────────────────┤  │
│                                  │  │ Internal Links    │  │
│  [+ Add Block ▼]                 │  │ ───────────────── │  │
│  ─────────────────────────────── │  │ Suggested:       │  │
│  Hero Editorial                  │  │ • How to Choose..│  │
│  Rich Text                       │  │ • Top 10 Website.│  │
│  Section Divider                 │  │ [+ Add Link]     │  │
│  Visual Explanation              │  └───────────────────┘  │
│  Inline Conversion               │                         │
│  Internal Authority              │                         │
│  FAQ SEO                         │                         │
│  Final Conversion                │                         │
│                                  │                         │
└─────────────────────────────────────────────────────────────┘
```

### Block Operations

- **Drag & Reorder:** Drag handle (≡) to reorder blocks
- **Block Menu (⋮):** Edit, Duplicate, Delete, Move Up/Down
- **Preview Modes:** Desktop / Tablet / Mobile toggle
- **Auto-save:** Draft saves every 30 seconds

### SEO Score Indicators

Real-time SEO analysis showing:
- [ ] Focus keyword in title
- [ ] Focus keyword in first paragraph
- [ ] Focus keyword in H2
- [ ] Meta description length (150-160 chars)
- [ ] Image alt tags present
- [ ] Internal links present (min 2)
- [ ] External links present (min 1)
- [ ] FAQ block present
- [ ] Reading time > 5 minutes

---

## 7. SEO Implementation

### URL Structure

```
/blog/                                    # Blog index
/blog/category/business-websites/         # Category archive
/blog/best-business-website-in-nigeria/   # Single post
```

### Automatic SEO Tags (Every Post)

```html
<!-- Primary Meta -->
<title>{meta_title} | WebDaddy Blog</title>
<meta name="description" content="{meta_description}">
<link rel="canonical" href="{canonical_url}">

<!-- Open Graph -->
<meta property="og:type" content="article">
<meta property="og:title" content="{og_title}">
<meta property="og:description" content="{og_description}">
<meta property="og:image" content="{og_image}">
<meta property="og:url" content="{canonical_url}">
<meta property="og:site_name" content="WebDaddy">
<meta property="article:published_time" content="{publish_date}">
<meta property="article:author" content="{author_name}">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{twitter_title}">
<meta name="twitter:description" content="{twitter_description}">
<meta name="twitter:image" content="{twitter_image}">

<!-- Additional -->
<meta name="robots" content="index, follow">
<meta name="author" content="{author_name}">
```

### JSON-LD Schemas

**Article Schema (Every Post):**
```json
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": "{title}",
    "description": "{excerpt}",
    "image": "{featured_image}",
    "author": {
        "@type": "Organization",
        "name": "WebDaddy"
    },
    "publisher": {
        "@type": "Organization",
        "name": "WebDaddy",
        "logo": {
            "@type": "ImageObject",
            "url": "https://webdaddy.online/assets/images/logo.png"
        }
    },
    "datePublished": "{publish_date}",
    "dateModified": "{updated_at}",
    "mainEntityOfPage": "{canonical_url}"
}
```

**Breadcrumb Schema:**
```json
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
        {"@type": "ListItem", "position": 1, "name": "Home", "item": "https://webdaddy.online/"},
        {"@type": "ListItem", "position": 2, "name": "Blog", "item": "https://webdaddy.online/blog/"},
        {"@type": "ListItem", "position": 3, "name": "{category}", "item": "https://webdaddy.online/blog/category/{category_slug}/"},
        {"@type": "ListItem", "position": 4, "name": "{title}"}
    ]
}
```

**FAQ Schema (When FAQ Block Present):**
```json
{
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [...]
}
```

### Internal Linking Strategy

```
                    ┌─────────────────┐
                    │   BLOG INDEX    │
                    │   /blog/        │
                    └────────┬────────┘
                             │
            ┌────────────────┼────────────────┐
            │                │                │
    ┌───────▼───────┐ ┌──────▼──────┐ ┌──────▼──────┐
    │  Category A   │ │ Category B  │ │ Category C  │
    │  /blog/cat/a/ │ │ /blog/cat/b/│ │ /blog/cat/c/│
    └───────┬───────┘ └──────┬──────┘ └──────┬──────┘
            │                │                │
       ┌────┴────┐      ┌────┴────┐      ┌────┴────┐
       │         │      │         │      │         │
    [Post 1] [Post 2] [Post 3] [Post 4] [Post 5] [Post 6]
       │         │      │         │      │         │
       └─────────┴──────┴─────────┴──────┴─────────┘
              CROSS-LINKING (Topic Clusters)
                         │
                         ▼
                   ┌───────────┐
                   │ TEMPLATES │
                   │  /        │
                   └───────────┘
```

**Linking Rules:**
- Every post links UP to its category
- Every post links SIDEWAYS to 2-3 related posts
- Every post links DOWN to relevant templates
- This forms **topic clusters**, not random posts

---

## 8. Frontend Rendering

### Post Page Structure

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- SEO Meta Tags -->
    <!-- JSON-LD Schemas -->
    <link rel="stylesheet" href="/assets/css/blog.css">
</head>
<body>
    <!-- Header (from includes) -->
    
    <main class="blog-post">
        <!-- Breadcrumbs -->
        <nav class="breadcrumbs">...</nav>
        
        <div class="blog-layout">
            <!-- Main Content Column -->
            <article class="blog-content">
                <!-- Blocks rendered in order -->
                <?php foreach ($blocks as $block): ?>
                    <?php render_block($block); ?>
                <?php endforeach; ?>
            </article>
            
            <!-- Sticky Conversion Rail (Desktop) -->
            <aside class="blog-rail">
                <?php include 'includes/blog/conversion_rail.php'; ?>
            </aside>
        </div>
    </article>
    
    <!-- Mobile Sticky Bar -->
    <div class="mobile-sticky-bar">
        <?php include 'includes/blog/mobile_bar.php'; ?>
    </div>
    
    <!-- Footer -->
    
    <script src="/assets/js/blog.js"></script>
</body>
</html>
```

### CSS Architecture

Blog styles extend the existing WebDaddy design system:

```css
/* blog.css - Uses existing design tokens */

:root {
    /* Inherits from main.css */
    /* --color-primary, --color-gold, --font-primary, etc. */
}

/* Blog-specific tokens */
.blog-post {
    --blog-content-width: 720px;
    --blog-wide-width: 1200px;
    --blog-reading-width: 65ch;
    --blog-rail-width: 300px;
}

/* Typography rhythm */
.blog-content {
    font-size: 1.125rem;
    line-height: 1.75;
}

.blog-content h2 {
    margin-top: 2.5rem;
    margin-bottom: 1rem;
}

.blog-content p {
    margin-bottom: 1.5rem;
}

/* Block-specific styles follow... */
```

---

## 9. Analytics Integration

### Events to Track

| Event | Trigger | Data Captured |
|-------|---------|---------------|
| `view` | Page load | post_id, referrer, affiliate_code |
| `scroll_25` | 25% scroll depth | post_id, time_on_page |
| `scroll_50` | 50% scroll depth | post_id, time_on_page |
| `scroll_75` | 75% scroll depth | post_id, time_on_page |
| `scroll_100` | 100% scroll depth | post_id, time_on_page |
| `cta_click` | Conversion block click | post_id, block_id, cta_type |
| `template_click` | Template link click | post_id, template_id |
| `share` | Share button click | post_id, platform |

### JavaScript Tracking

```javascript
// assets/js/blog.js
const BlogAnalytics = {
    postId: null,
    scrollMarkers: [25, 50, 75, 100],
    trackedMarkers: [],
    
    init(postId) {
        this.postId = postId;
        this.trackView();
        this.initScrollTracking();
        this.initCtaTracking();
    },
    
    trackEvent(eventType, data = {}) {
        fetch('/api/blog/analytics.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                post_id: this.postId,
                event_type: eventType,
                ...data
            })
        });
    },
    
    // ... implementation
};
```

---

## 10. Implementation Phases

### Phase 1: Foundation (Week 1)
- [ ] Create database tables
- [ ] Build `includes/blog/` core functions
- [ ] Create blog post listing page (`/blog/`)
- [ ] Create single post page (`/blog/{slug}`)
- [ ] Implement basic SEO (meta tags, schemas)

### Phase 2: Block System (Week 2)
- [ ] Build block renderer system
- [ ] Implement all 8 block types
- [ ] Create block styling (CSS)
- [ ] Add responsive layouts

### Phase 3: Admin Editor (Week 3)
- [ ] Build admin post list (`/admin/blog/`)
- [ ] Create block editor interface
- [ ] Implement drag-and-drop reordering
- [ ] Add preview functionality
- [ ] Build category/tag management

### Phase 4: Conversion & Analytics (Week 4)
- [ ] Implement sticky conversion rail
- [ ] Add mobile sticky bar
- [ ] Integrate affiliate awareness
- [ ] Build analytics tracking
- [ ] Create admin analytics dashboard

### Phase 5: Polish & SEO (Week 5)
- [ ] SEO score calculator
- [ ] Internal link suggestions
- [ ] Auto-related posts
- [ ] Performance optimization
- [ ] Final testing

---

## 11. API Endpoints

### Admin APIs

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/admin/api/blog/posts.php` | GET | List posts with filters |
| `/admin/api/blog/posts.php` | POST | Create new post |
| `/admin/api/blog/posts.php?id={id}` | PUT | Update post |
| `/admin/api/blog/posts.php?id={id}` | DELETE | Delete post |
| `/admin/api/blog/blocks.php` | POST | Add block to post |
| `/admin/api/blog/blocks.php?id={id}` | PUT | Update block |
| `/admin/api/blog/blocks.php?id={id}` | DELETE | Delete block |
| `/admin/api/blog/blocks.php/reorder` | POST | Reorder blocks |
| `/admin/api/blog/upload.php` | POST | Upload image |
| `/admin/api/blog/preview.php?id={id}` | GET | Generate preview |

### Public APIs

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/blog/analytics.php` | POST | Track events |
| `/api/blog/share.php` | POST | Update share count |

---

## 12. Security Considerations

- **CSRF Protection:** All admin forms include CSRF tokens
- **XSS Prevention:** All user content sanitized before render
- **SQL Injection:** Prepared statements for all queries
- **File Upload:** Validate image types, limit sizes, sanitize filenames
- **Rate Limiting:** Analytics API rate-limited per session
- **Admin Access:** All admin routes require authentication

---

## 13. Performance Optimizations

- **Lazy Loading:** Images loaded on scroll into view
- **Block Caching:** Rendered blocks cached in `cache/blog/`
- **Database Indexes:** Optimized queries with proper indexes
- **Asset Minification:** CSS/JS minified in production
- **Image Optimization:** Auto-resize uploaded images
- **CDN Ready:** Asset URLs configurable for CDN

---

## 14. Design Token Alignment

The blog uses the same design tokens as the main WebDaddy site:

| Token | Value | Usage |
|-------|-------|-------|
| `--color-primary` | #1a1a2e | Headers, text |
| `--color-gold` | #d4af37 | Accents, CTAs |
| `--color-navy` | #16213e | Backgrounds |
| `--font-primary` | 'Inter', sans-serif | Body text |
| `--font-heading` | 'Playfair Display', serif | Headings |
| `--spacing-base` | 1rem | Base spacing unit |
| `--border-radius` | 8px | Rounded corners |
| `--shadow-card` | 0 4px 20px rgba(0,0,0,0.1) | Card elevation |

---

## 15. Routing & Controller Integration

### Integration with Existing router.php

Add the following blog routes to `router.php` **BEFORE** the template slug routing (line 55):

```php
// router.php - Add after sitemap routing (line 41), before tool routing

// ============================================
// BLOG ROUTES - Add before template slug routing
// ============================================

// Blog index: /blog/
if ($path === '/blog' || $path === '/blog/') {
    $_SERVER['SCRIPT_NAME'] = '/blog/index.php';
    require __DIR__ . '/blog/index.php';
    exit;
}

// Blog sitemap: /blog-sitemap.xml
if ($path === '/blog-sitemap.xml') {
    $_SERVER['SCRIPT_NAME'] = '/blog/sitemap.php';
    require __DIR__ . '/blog/sitemap.php';
    exit;
}

// Blog category: /blog/category/slug/
if (preg_match('#^/blog/category/([a-z0-9-]+)/?$#i', $path, $matches)) {
    $_GET['category_slug'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/blog/category.php';
    require __DIR__ . '/blog/category.php';
    exit;
}

// Blog single post: /blog/post-slug/
if (preg_match('#^/blog/([a-z0-9-]+)/?$#i', $path, $matches)) {
    $_GET['slug'] = $matches[1];
    $_SERVER['SCRIPT_NAME'] = '/blog/post.php';
    require __DIR__ . '/blog/post.php';
    exit;
}

// ============================================
// END BLOG ROUTES
// ============================================
```

**Important:** Also add 'blog' to the excluded slugs array on line 60:
```php
$excluded = ['admin', 'affiliate', 'user', 'assets', 'api', 'uploads', 'mailer', 'index', 'template', 'sitemap', 'tool', 'robots', 'blog'];
```

### Route Ordering Strategy

**Critical:** Blog routes MUST be placed BEFORE the template slug catch-all routing (line 55-70 in current router.php) because:

1. Blog URLs like `/blog/my-post-title` would otherwise match the template slug pattern `#^/([a-z0-9_-]+)/?$#i`
2. By placing blog routes first, they're handled explicitly before the catch-all runs
3. Adding 'blog' to the excluded array provides a safety net

**Current router.php structure with blog integration:**
```php
// Line 29-41: robots.txt and sitemap routing
// Line 42-XX: INSERT BLOG ROUTES HERE ← New
// Line XX+1: Tool detail page routing
// Line XX+2: Template slug routing (catch-all) - MUST come after blog routes
// Line XX+3: Fallback 404/index
```

### Blog Controller Pattern

Each blog page follows the existing WebDaddy controller pattern:

```php
// blog/post.php
<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/blog/functions.php';
require_once __DIR__ . '/../includes/blog/seo.php';
require_once __DIR__ . '/../includes/blog/renderer.php';

$slug = $_GET['slug'] ?? '';
$post = getBlogPostBySlug($slug);

if (!$post || $post['status'] !== 'published') {
    http_response_code(404);
    require_once __DIR__ . '/../404.php';
    exit;
}

// Track view (with caching to prevent duplicates)
trackBlogView($post['id']);

// Get blocks for rendering
$blocks = getBlogBlocks($post['id']);

// Generate SEO data
$seoData = generateBlogSEO($post);
$schemas = generateBlogSchemas($post, $blocks);

// Get sidebar data
$sidebarTemplates = getFeaturedTemplates(3);
$affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;

// Include template
include __DIR__ . '/templates/single.php';
```

---

## 16. Caching Strategy

### Cache Locations

```
cache/
└── blog/
    ├── posts/           # Rendered post HTML
    │   ├── {slug}.html
    │   └── {slug}.json  # Post metadata
    ├── blocks/          # Pre-rendered blocks
    │   └── {post_id}_{block_id}.html
    ├── lists/           # List page caches
    │   ├── index.html
    │   └── category_{slug}.html
    └── sitemap.xml      # Blog sitemap cache
```

### Cache Implementation

```php
// includes/blog/cache.php

define('BLOG_CACHE_DIR', __DIR__ . '/../../cache/blog/');
define('BLOG_CACHE_TTL', 3600); // 1 hour

function getBlogCache($type, $key) {
    $file = BLOG_CACHE_DIR . $type . '/' . $key;
    if (file_exists($file) && (time() - filemtime($file)) < BLOG_CACHE_TTL) {
        return file_get_contents($file);
    }
    return false;
}

function setBlogCache($type, $key, $content) {
    $dir = BLOG_CACHE_DIR . $type . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dir . $key, $content);
}

function invalidateBlogCache($postId = null) {
    if ($postId) {
        // Invalidate specific post
        $post = getBlogPost($postId);
        if ($post) {
            @unlink(BLOG_CACHE_DIR . 'posts/' . $post['slug'] . '.html');
            @unlink(BLOG_CACHE_DIR . 'posts/' . $post['slug'] . '.json');
        }
        // Clear block caches for this post
        array_map('unlink', glob(BLOG_CACHE_DIR . 'blocks/' . $postId . '_*.html'));
    }
    
    // Always invalidate list caches when any post changes
    array_map('unlink', glob(BLOG_CACHE_DIR . 'lists/*.html'));
    
    // Invalidate sitemap
    @unlink(BLOG_CACHE_DIR . 'sitemap.xml');
}
```

### Cache Invalidation Triggers

| Action | Invalidates |
|--------|-------------|
| Post created/published | List caches, sitemap |
| Post updated | Post cache, list caches, sitemap |
| Post deleted | Post cache, list caches, sitemap |
| Block added/updated/deleted | Post cache only |
| Category changed | Category cache, sitemap |

### Admin CRUD Cache Invalidation Integration

Add these calls to the admin API endpoints:

```php
// admin/api/blog/posts.php

// After creating a post
function createBlogPost($data) {
    global $pdo;
    // ... insert logic ...
    $postId = $pdo->lastInsertId();
    
    // Invalidate caches
    invalidateBlogCache(null); // Clear list caches and sitemap
    
    return $postId;
}

// After updating a post
function updateBlogPost($id, $data) {
    global $pdo;
    // ... update logic ...
    
    // Invalidate this post's cache and lists
    invalidateBlogCache($id);
    
    return true;
}

// After deleting a post
function deleteBlogPost($id) {
    global $pdo;
    
    // Get slug before deletion for cache clearing
    $post = getBlogPost($id);
    
    // Delete from database
    $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = ?");
    $stmt->execute([$id]);
    
    // Invalidate caches
    if ($post) {
        @unlink(BLOG_CACHE_DIR . 'posts/' . $post['slug'] . '.html');
        @unlink(BLOG_CACHE_DIR . 'posts/' . $post['slug'] . '.json');
    }
    invalidateBlogCache(null); // Clear list caches
    
    return true;
}

// admin/api/blog/blocks.php

// After any block modification
function saveBlock($postId, $blockData) {
    // ... save logic ...
    
    // Only invalidate the specific post cache (blocks don't affect lists)
    $post = getBlogPost($postId);
    if ($post) {
        @unlink(BLOG_CACHE_DIR . 'posts/' . $post['slug'] . '.html');
        // Clear block caches for this post
        array_map('unlink', glob(BLOG_CACHE_DIR . 'blocks/' . $postId . '_*.html') ?: []);
    }
}

// After reordering blocks
function reorderBlocks($postId, $blockOrder) {
    // ... reorder logic ...
    
    // Invalidate post cache
    $post = getBlogPost($postId);
    if ($post) {
        @unlink(BLOG_CACHE_DIR . 'posts/' . $post['slug'] . '.html');
    }
}
```

### Cache Key Schema

| Cache Type | Key Format | Example |
|------------|------------|---------|
| Post HTML | `posts/{slug}.html` | `posts/best-business-website.html` |
| Post Meta | `posts/{slug}.json` | `posts/best-business-website.json` |
| Block | `blocks/{post_id}_{block_id}.html` | `blocks/42_156.html` |
| List Index | `lists/index.html` | `lists/index.html` |
| Category List | `lists/category_{slug}.html` | `lists/category_business.html` |
| Sitemap | `sitemap.xml` | `sitemap.xml` |

---

## 17. Sitemap Integration

### Blog Sitemap Generation

Integrates with existing `sitemap.php`:

```php
// Add to sitemap.php or create includes/blog/sitemap.php

function generateBlogSitemap() {
    global $pdo;
    
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
    $xml .= '        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">' . "\n";
    
    // Blog index
    $xml .= generateUrlEntry('/blog/', 'daily', '0.8');
    
    // Categories
    $categories = $pdo->query("SELECT slug, updated_at FROM blog_categories WHERE is_active = 1")->fetchAll();
    foreach ($categories as $cat) {
        $xml .= generateUrlEntry('/blog/category/' . $cat['slug'] . '/', 'weekly', '0.7', $cat['updated_at']);
    }
    
    // Posts
    $posts = $pdo->query("
        SELECT slug, updated_at, publish_date 
        FROM blog_posts 
        WHERE status = 'published' AND publish_date <= datetime('now', '+1 hour')
        ORDER BY publish_date DESC
    ")->fetchAll();
    
    foreach ($posts as $post) {
        $xml .= generateUrlEntry('/blog/' . $post['slug'] . '/', 'monthly', '0.6', $post['updated_at']);
    }
    
    $xml .= '</urlset>';
    
    return $xml;
}

function generateUrlEntry($path, $changefreq, $priority, $lastmod = null) {
    $url = 'https://webdaddy.online' . $path;
    $xml = "  <url>\n";
    $xml .= "    <loc>" . htmlspecialchars($url) . "</loc>\n";
    if ($lastmod) {
        $xml .= "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>\n";
    }
    $xml .= "    <changefreq>{$changefreq}</changefreq>\n";
    $xml .= "    <priority>{$priority}</priority>\n";
    $xml .= "  </url>\n";
    return $xml;
}
```

### robots.txt Update

```
# Add to robots.php or robots.txt
Sitemap: https://webdaddy.online/sitemap.xml
Sitemap: https://webdaddy.online/blog-sitemap.xml
```

---

## 18. Canonical URL & Breadcrumb Handling

### Canonical URL Generation

```php
// includes/blog/seo.php

function generateCanonicalUrl($post) {
    $baseUrl = 'https://webdaddy.online';
    
    // If custom canonical is set, use it
    if (!empty($post['canonical_url'])) {
        return $post['canonical_url'];
    }
    
    // Generate default canonical
    return $baseUrl . '/blog/' . $post['slug'] . '/';
}

function generateBlogSEO($post) {
    return [
        'title' => $post['meta_title'] ?: $post['title'] . ' | WebDaddy Blog',
        'description' => $post['meta_description'] ?: truncate($post['excerpt'], 160),
        'canonical' => generateCanonicalUrl($post),
        'og' => [
            'title' => $post['og_title'] ?: $post['title'],
            'description' => $post['og_description'] ?: truncate($post['excerpt'], 200),
            'image' => $post['og_image'] ?: $post['featured_image'],
            'type' => 'article',
            'url' => generateCanonicalUrl($post)
        ],
        'twitter' => [
            'card' => 'summary_large_image',
            'title' => $post['twitter_title'] ?: $post['title'],
            'description' => $post['twitter_description'] ?: truncate($post['excerpt'], 200),
            'image' => $post['twitter_image'] ?: $post['featured_image']
        ],
        'article' => [
            'published_time' => $post['publish_date'],
            'modified_time' => $post['updated_at'],
            'author' => $post['author_name']
        ]
    ];
}
```

### Breadcrumb Rendering

```php
// includes/blog/breadcrumbs.php

function renderBlogBreadcrumbs($post, $category = null) {
    $breadcrumbs = [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Blog', 'url' => '/blog/']
    ];
    
    if ($category) {
        $breadcrumbs[] = [
            'name' => $category['name'],
            'url' => '/blog/category/' . $category['slug'] . '/'
        ];
    }
    
    $breadcrumbs[] = [
        'name' => $post['title'],
        'url' => null // Current page, no link
    ];
    
    // Render HTML
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';
    
    foreach ($breadcrumbs as $index => $crumb) {
        $position = $index + 1;
        $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        
        if ($crumb['url']) {
            $html .= '<a itemprop="item" href="' . htmlspecialchars($crumb['url']) . '">';
            $html .= '<span itemprop="name">' . htmlspecialchars($crumb['name']) . '</span>';
            $html .= '</a>';
        } else {
            $html .= '<span itemprop="name">' . htmlspecialchars($crumb['name']) . '</span>';
        }
        
        $html .= '<meta itemprop="position" content="' . $position . '">';
        $html .= '</li>';
    }
    
    $html .= '</ol></nav>';
    
    return $html;
}

function generateBreadcrumbSchema($post, $category = null) {
    $items = [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => 'https://webdaddy.online/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => 'https://webdaddy.online/blog/']
    ];
    
    $position = 3;
    if ($category) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $category['name'],
            'item' => 'https://webdaddy.online/blog/category/' . $category['slug'] . '/'
        ];
    }
    
    $items[] = [
        '@type' => 'ListItem',
        'position' => $position,
        'name' => $post['title']
    ];
    
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    ];
}
```

---

## 19. Helper Functions (includes/blog/functions.php)

### Core Data Functions

```php
<?php
// includes/blog/functions.php

/**
 * Get published blog post by slug
 */
function getBlogPostBySlug($slug) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        WHERE p.slug = ? AND p.status = 'published'
        AND p.publish_date <= datetime('now', '+1 hour')
    ");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Get all blocks for a post, ordered
 */
function getBlogBlocks($postId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM blog_blocks 
        WHERE post_id = ? AND is_visible = 1
        ORDER BY display_order ASC
    ");
    $stmt->execute([$postId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get published posts with pagination
 */
function getBlogPosts($page = 1, $perPage = 10, $categoryId = null) {
    global $pdo;
    $offset = ($page - 1) * $perPage;
    
    $where = "status = 'published' AND publish_date <= datetime('now', '+1 hour')";
    $params = [];
    
    if ($categoryId) {
        $where .= " AND category_id = ?";
        $params[] = $categoryId;
    }
    
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, c.slug as category_slug
        FROM blog_posts p
        LEFT JOIN blog_categories c ON p.category_id = c.id
        WHERE {$where}
        ORDER BY publish_date DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get related posts (same category, excluding current)
 */
function getRelatedPosts($postId, $categoryId, $limit = 3) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.slug, p.excerpt, p.featured_image, p.reading_time_minutes
        FROM blog_posts p
        WHERE p.id != ? AND p.category_id = ? AND p.status = 'published'
        ORDER BY p.publish_date DESC
        LIMIT ?
    ");
    $stmt->execute([$postId, $categoryId, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Calculate reading time from blocks
 */
function calculateReadingTime($blocks) {
    $wordCount = 0;
    foreach ($blocks as $block) {
        $data = json_decode($block['data_payload'], true);
        if (isset($data['content'])) {
            $wordCount += str_word_count(strip_tags($data['content']));
        }
        if (isset($data['h1_title'])) {
            $wordCount += str_word_count($data['h1_title']);
        }
    }
    return max(1, ceil($wordCount / 200)); // 200 words per minute
}

/**
 * Track blog post view (with duplicate prevention)
 */
function trackBlogView($postId) {
    global $pdo;
    
    $sessionId = session_id();
    $cacheKey = "blog_view_{$postId}_{$sessionId}";
    
    // Check if already tracked this session (simple file-based check)
    $trackFile = sys_get_temp_dir() . '/' . md5($cacheKey);
    if (file_exists($trackFile) && (time() - filemtime($trackFile)) < 3600) {
        return; // Already tracked within last hour
    }
    
    // Track the view
    $stmt = $pdo->prepare("
        INSERT INTO blog_analytics (post_id, event_type, session_id, referrer, affiliate_code, user_agent)
        VALUES (?, 'view', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $postId,
        $sessionId,
        $_SERVER['HTTP_REFERER'] ?? null,
        $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null,
        $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
    
    // Update view count
    $pdo->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?")->execute([$postId]);
    
    // Mark as tracked
    touch($trackFile);
}

/**
 * Generate slug from title
 */
function generateBlogSlug($title) {
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Check if slug exists (for uniqueness)
 */
function blogSlugExists($slug, $excludeId = null) {
    global $pdo;
    $sql = "SELECT COUNT(*) FROM blog_posts WHERE slug = ?";
    $params = [$slug];
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn() > 0;
}
```

---

## 20. Affiliate & Template System Integration

This section details how the blog system integrates with existing WebDaddy modules.

### Required Existing Constants (from includes/config.php)

```php
// These constants must exist in config.php for blog affiliate integration
define('AFFILIATE_DISCOUNT_RATE', 0.20);  // 20% discount for affiliate visitors
define('WHATSAPP_NUMBER', '2348012345678'); // WebDaddy support WhatsApp
```

### Affiliate-Aware CTAs

```php
// includes/blog/affiliate.php

/**
 * Get affiliate context for blog CTAs
 * 
 * @return array|null Affiliate context or null if no affiliate
 *   - 'code' => string (affiliate code)
 *   - 'name' => string (affiliate display name)
 *   - 'discount' => int (discount percentage, e.g., 20)
 *   - 'message' => string (display message for user)
 */
function getBlogAffiliateContext() {
    $affiliateCode = $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;
    
    if (!$affiliateCode) {
        return null;
    }
    
    // Validate affiliate code
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, display_name FROM affiliates WHERE code = ? AND status = 'active'");
    $stmt->execute([$affiliateCode]);
    $affiliate = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$affiliate) {
        return null;
    }
    
    return [
        'code' => $affiliateCode,
        'name' => $affiliate['display_name'],
        'discount' => AFFILIATE_DISCOUNT_RATE * 100, // e.g., 20%
        'message' => "You're shopping with {$affiliate['display_name']} — enjoy " . (AFFILIATE_DISCOUNT_RATE * 100) . "% off!"
    ];
}

/**
 * Render affiliate message for conversion blocks
 */
function renderAffiliateMessage() {
    $context = getBlogAffiliateContext();
    if (!$context) {
        return '';
    }
    
    return '<div class="affiliate-badge">
        <span class="affiliate-icon">🎁</span>
        <span class="affiliate-text">' . htmlspecialchars($context['message']) . '</span>
    </div>';
}
```

### Template Integration for CTAs

```php
// includes/blog/templates.php

/**
 * Get featured templates for blog sidebar/CTAs
 */
function getFeaturedTemplates($limit = 3, $categoryFilter = null) {
    global $pdo;
    
    $where = "t.is_active = 1";
    $params = [];
    
    if ($categoryFilter) {
        $where .= " AND t.category = ?";
        $params[] = $categoryFilter;
    }
    
    $params[] = $limit;
    
    // Prioritize featured, then by sales
    $stmt = $pdo->prepare("
        SELECT t.id, t.name, t.slug, t.price, t.sale_price, t.thumbnail, t.category,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.template_id = t.id) as sales_count
        FROM templates t
        WHERE {$where}
        ORDER BY t.is_featured DESC, sales_count DESC
        LIMIT ?
    ");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get template by ID for inline conversion blocks
 */
function getTemplateForBlog($templateId) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT id, name, slug, price, sale_price, thumbnail, short_description, category
        FROM templates
        WHERE id = ? AND is_active = 1
    ");
    $stmt->execute([$templateId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Render template card for blog CTAs
 */
function renderBlogTemplateCard($template, $size = 'small') {
    $price = $template['sale_price'] ?: $template['price'];
    $originalPrice = $template['sale_price'] ? $template['price'] : null;
    
    $html = '<div class="blog-template-card blog-template-card--' . $size . '">';
    $html .= '<a href="/template.php?slug=' . htmlspecialchars($template['slug']) . '">';
    
    if ($template['thumbnail']) {
        $html .= '<img src="' . htmlspecialchars($template['thumbnail']) . '" alt="' . htmlspecialchars($template['name']) . '" loading="lazy">';
    }
    
    $html .= '<div class="blog-template-card__content">';
    $html .= '<h4>' . htmlspecialchars($template['name']) . '</h4>';
    $html .= '<div class="blog-template-card__price">';
    $html .= '₦' . number_format($price);
    if ($originalPrice) {
        $html .= ' <s>₦' . number_format($originalPrice) . '</s>';
    }
    $html .= '</div>';
    $html .= '</div>';
    
    $html .= '</a></div>';
    
    return $html;
}
```

### WhatsApp CTA Integration

```php
/**
 * Generate WhatsApp link for blog CTAs
 */
function generateBlogWhatsAppLink($message = null, $postTitle = null) {
    $phone = WHATSAPP_NUMBER; // From config.php
    
    if (!$message && $postTitle) {
        $message = "Hi! I just read your article \"{$postTitle}\" and I'm interested in getting a website.";
    } elseif (!$message) {
        $message = "Hi! I'm interested in getting a website from WebDaddy.";
    }
    
    return 'https://wa.me/' . $phone . '?text=' . urlencode($message);
}
```

---

## 21. Database Migration Strategy

### Migration File

Create `database/migrations/004_blog_system.sql`:

```sql
-- Blog System Migration
-- Run this after backing up the database

-- Check if migration already applied
CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Blog Categories
CREATE TABLE IF NOT EXISTS blog_categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    description TEXT,
    meta_title TEXT,
    meta_description TEXT,
    parent_id INTEGER DEFAULT NULL,
    display_order INTEGER DEFAULT 0,
    is_active INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES blog_categories(id) ON DELETE SET NULL
);

-- Blog Posts
CREATE TABLE IF NOT EXISTS blog_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    excerpt TEXT,
    featured_image TEXT,
    featured_image_alt TEXT,
    category_id INTEGER,
    author_name TEXT DEFAULT 'WebDaddy Team',
    author_avatar TEXT,
    status TEXT DEFAULT 'draft' CHECK(status IN ('draft', 'published', 'scheduled', 'archived')),
    publish_date DATETIME,
    reading_time_minutes INTEGER DEFAULT 5,
    meta_title TEXT,
    meta_description TEXT,
    canonical_url TEXT,
    focus_keyword TEXT,
    seo_score INTEGER DEFAULT 0,
    og_title TEXT,
    og_description TEXT,
    og_image TEXT,
    twitter_title TEXT,
    twitter_description TEXT,
    twitter_image TEXT,
    view_count INTEGER DEFAULT 0,
    share_count INTEGER DEFAULT 0,
    primary_template_id INTEGER,
    show_affiliate_ctas INTEGER DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
);

-- Blog Blocks
CREATE TABLE IF NOT EXISTS blog_blocks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    block_type TEXT NOT NULL,
    display_order INTEGER NOT NULL,
    semantic_role TEXT DEFAULT 'primary_content',
    layout_variant TEXT DEFAULT 'default',
    data_payload TEXT NOT NULL,
    behavior_config TEXT,
    is_visible INTEGER DEFAULT 1,
    visibility_conditions TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Tags
CREATE TABLE IF NOT EXISTS blog_tags (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS blog_post_tags (
    post_id INTEGER NOT NULL,
    tag_id INTEGER NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
);

-- Internal Links
CREATE TABLE IF NOT EXISTS blog_internal_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    source_post_id INTEGER NOT NULL,
    target_post_id INTEGER NOT NULL,
    anchor_text TEXT,
    link_type TEXT DEFAULT 'related',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (source_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE,
    FOREIGN KEY (target_post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Analytics
CREATE TABLE IF NOT EXISTS blog_analytics (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INTEGER NOT NULL,
    event_type TEXT NOT NULL,
    session_id TEXT,
    referrer TEXT,
    affiliate_code TEXT,
    user_agent TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES blog_posts(id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX IF NOT EXISTS idx_blog_posts_status ON blog_posts(status);
CREATE INDEX IF NOT EXISTS idx_blog_posts_category ON blog_posts(category_id);
CREATE INDEX IF NOT EXISTS idx_blog_posts_publish_date ON blog_posts(publish_date);
CREATE INDEX IF NOT EXISTS idx_blog_posts_slug ON blog_posts(slug);
CREATE INDEX IF NOT EXISTS idx_blog_blocks_post ON blog_blocks(post_id);
CREATE INDEX IF NOT EXISTS idx_blog_blocks_order ON blog_blocks(post_id, display_order);
CREATE INDEX IF NOT EXISTS idx_blog_analytics_post ON blog_analytics(post_id);
CREATE INDEX IF NOT EXISTS idx_blog_analytics_date ON blog_analytics(created_at);

-- Mark migration as complete
INSERT OR IGNORE INTO migrations (name) VALUES ('004_blog_system');
```

### Migration Rollback Procedure

If you need to rollback the blog migration:

```sql
-- database/migrations/004_blog_system_rollback.sql
-- WARNING: This will delete ALL blog data permanently

-- Remove tables in reverse dependency order
DROP TABLE IF EXISTS blog_analytics;
DROP TABLE IF EXISTS blog_internal_links;
DROP TABLE IF EXISTS blog_post_tags;
DROP TABLE IF EXISTS blog_tags;
DROP TABLE IF EXISTS blog_blocks;
DROP TABLE IF EXISTS blog_posts;
DROP TABLE IF EXISTS blog_categories;

-- Remove migration record
DELETE FROM migrations WHERE name = '004_blog_system';

-- Remove blog cache directory (run via PHP or shell)
-- rm -rf cache/blog/
```

**Rollback Steps:**
1. **Backup first:** `cp database/webdaddy.db database/webdaddy_backup_$(date +%Y%m%d).db`
2. **Run rollback SQL:** `sqlite3 database/webdaddy.db < database/migrations/004_blog_system_rollback.sql`
3. **Remove blog files:** Delete `blog/`, `includes/blog/`, `admin/blog/`, `assets/css/blog.css`, `assets/js/blog.js`
4. **Remove router entries:** Revert changes to `router.php`
5. **Clear cache:** `rm -rf cache/blog/`

### Runtime Migration Check

Add to `includes/db.php`:

```php
// Auto-apply blog migration if needed
function ensureBlogTablesExist() {
    global $pdo;
    
    // Check if blog_posts table exists
    $result = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='blog_posts'");
    if (!$result->fetch()) {
        // Run migration
        $migration = file_get_contents(__DIR__ . '/../database/migrations/004_blog_system.sql');
        $pdo->exec($migration);
    }
}

// Call during db connection
ensureBlogTablesExist();
```

---

## 22. Admin Navigation Update

Add to `admin/includes/header.php`:

```php
// In the navigation menu, add:
<li class="nav-item <?= strpos($_SERVER['REQUEST_URI'], '/admin/blog') !== false ? 'active' : '' ?>">
    <a class="nav-link" href="/admin/blog/">
        <i class="fas fa-blog"></i>
        <span>Blog</span>
    </a>
    <ul class="submenu">
        <li><a href="/admin/blog/">All Posts</a></li>
        <li><a href="/admin/blog/editor.php">New Post</a></li>
        <li><a href="/admin/blog/categories.php">Categories</a></li>
        <li><a href="/admin/blog/analytics.php">Analytics</a></li>
    </ul>
</li>
```

---

## Summary

This blog system is designed to be:

✅ **SEO Dominant** — Schema markup, internal linking, keyword optimization, sitemap integration
✅ **Conversion Focused** — CTAs throughout, affiliate-aware, template funneling
✅ **Plain PHP Friendly** — No complex frameworks, SQLite database
✅ **Design Consistent** — Matches WebDaddy premium aesthetic
✅ **Admin Friendly** — Visual block editor, no HTML touching
✅ **Performance Optimized** — Caching, lazy loading, indexed queries
✅ **Well-Integrated** — Works with existing router, affiliates, templates
✅ **Future-Proof** — Extensible block system, scalable architecture

This is how serious SaaS blogs are built, adapted to your PHP/SQLite stack.
