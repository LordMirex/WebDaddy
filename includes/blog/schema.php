<?php

function blogGenerateArticleSchema($post, $blocks = [])
{
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'description' => $post['meta_description'] ?? $post['excerpt'] ?? '',
        'author' => [
            '@type' => 'Person',
            'name' => $post['author_name'] ?? 'WebDaddy Team'
        ],
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'WebDaddy Empire',
            'logo' => [
                '@type' => 'ImageObject',
                'url' => SITE_URL . '/assets/img/logo.png'
            ]
        ],
        'datePublished' => $post['publish_date'] ?? $post['created_at'],
        'dateModified' => $post['updated_at'],
        'mainEntityOfPage' => [
            '@type' => 'WebPage',
            '@id' => blogGetCanonicalUrl($post)
        ]
    ];
    
    if (!empty($post['featured_image'])) {
        $schema['image'] = [
            '@type' => 'ImageObject',
            'url' => $post['featured_image'],
            'caption' => $post['featured_image_alt'] ?? $post['title']
        ];
    }
    
    if (!empty($post['category_name'])) {
        $schema['articleSection'] = $post['category_name'];
    }
    
    if (!empty($post['focus_keyword'])) {
        $schema['keywords'] = $post['focus_keyword'];
    }
    
    return $schema;
}

function blogGenerateFAQSchema($faqItems)
{
    if (empty($faqItems)) {
        return null;
    }
    
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => []
    ];
    
    foreach ($faqItems as $item) {
        $schema['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => strip_tags($item['answer'])
            ]
        ];
    }
    
    return $schema;
}

function blogGenerateBreadcrumbSchema($post, $category = null)
{
    $items = [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => SITE_URL . '/'
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Blog',
            'item' => SITE_URL . '/blog/'
        ]
    ];
    
    $position = 3;
    
    if ($category) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => $category['name'],
            'item' => SITE_URL . '/blog/category/' . $category['slug'] . '/'
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

function blogGenerateWebPageSchema($title, $description, $url)
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'WebPage',
        'name' => $title,
        'description' => $description,
        'url' => $url,
        'publisher' => [
            '@type' => 'Organization',
            'name' => 'WebDaddy Empire'
        ]
    ];
}

function blogRenderSchemaScripts($schemas)
{
    $output = '';
    foreach ($schemas as $schema) {
        if ($schema) {
            $output .= '<script type="application/ld+json">' . "\n";
            $output .= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            $output .= "\n</script>\n";
        }
    }
    return $output;
}

function blogGenerateSEOData($post)
{
    return [
        'title' => $post['meta_title'] ?: $post['title'] . ' | WebDaddy Blog',
        'description' => $post['meta_description'] ?: blogTruncate($post['excerpt'] ?? '', 160),
        'canonical' => blogGetCanonicalUrl($post),
        'og' => [
            'title' => $post['og_title'] ?: $post['title'],
            'description' => $post['og_description'] ?: blogTruncate($post['excerpt'] ?? '', 200),
            'image' => $post['og_image'] ?: $post['featured_image'],
            'type' => 'article',
            'url' => blogGetCanonicalUrl($post)
        ],
        'twitter' => [
            'card' => 'summary_large_image',
            'title' => $post['twitter_title'] ?: $post['title'],
            'description' => $post['twitter_description'] ?: blogTruncate($post['excerpt'] ?? '', 200),
            'image' => $post['twitter_image'] ?: $post['featured_image']
        ],
        'article' => [
            'published_time' => $post['publish_date'],
            'modified_time' => $post['updated_at'],
            'author' => $post['author_name'] ?? 'WebDaddy Team'
        ]
    ];
}

function blogRenderMetaTags($seoData)
{
    $output = '';
    
    $output .= '<meta name="description" content="' . htmlspecialchars($seoData['description']) . '">' . "\n";
    $output .= '<link rel="canonical" href="' . htmlspecialchars($seoData['canonical']) . '">' . "\n";
    
    $output .= '<meta property="og:title" content="' . htmlspecialchars($seoData['og']['title']) . '">' . "\n";
    $output .= '<meta property="og:description" content="' . htmlspecialchars($seoData['og']['description']) . '">' . "\n";
    $output .= '<meta property="og:type" content="' . $seoData['og']['type'] . '">' . "\n";
    $output .= '<meta property="og:url" content="' . htmlspecialchars($seoData['og']['url']) . '">' . "\n";
    if (!empty($seoData['og']['image'])) {
        $output .= '<meta property="og:image" content="' . htmlspecialchars($seoData['og']['image']) . '">' . "\n";
    }
    
    $output .= '<meta name="twitter:card" content="' . $seoData['twitter']['card'] . '">' . "\n";
    $output .= '<meta name="twitter:title" content="' . htmlspecialchars($seoData['twitter']['title']) . '">' . "\n";
    $output .= '<meta name="twitter:description" content="' . htmlspecialchars($seoData['twitter']['description']) . '">' . "\n";
    if (!empty($seoData['twitter']['image'])) {
        $output .= '<meta name="twitter:image" content="' . htmlspecialchars($seoData['twitter']['image']) . '">' . "\n";
    }
    
    if (!empty($seoData['article']['published_time'])) {
        $output .= '<meta property="article:published_time" content="' . date('c', strtotime($seoData['article']['published_time'])) . '">' . "\n";
    }
    if (!empty($seoData['article']['modified_time'])) {
        $output .= '<meta property="article:modified_time" content="' . date('c', strtotime($seoData['article']['modified_time'])) . '">' . "\n";
    }
    if (!empty($seoData['article']['author'])) {
        $output .= '<meta property="article:author" content="' . htmlspecialchars($seoData['article']['author']) . '">' . "\n";
    }
    
    return $output;
}

function blogRenderBreadcrumbsHtml($post, $category = null)
{
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    $html .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';
    
    $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
    $html .= '<a itemprop="item" href="/"><span itemprop="name">Home</span></a>';
    $html .= '<meta itemprop="position" content="1">';
    $html .= '</li>';
    
    $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
    $html .= '<a itemprop="item" href="/blog/"><span itemprop="name">Blog</span></a>';
    $html .= '<meta itemprop="position" content="2">';
    $html .= '</li>';
    
    $position = 3;
    
    if ($category) {
        $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        $html .= '<a itemprop="item" href="/blog/category/' . htmlspecialchars($category['slug']) . '/">';
        $html .= '<span itemprop="name">' . htmlspecialchars($category['name']) . '</span>';
        $html .= '</a>';
        $html .= '<meta itemprop="position" content="' . $position++ . '">';
        $html .= '</li>';
    }
    
    $html .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
    $html .= '<span itemprop="name">' . htmlspecialchars($post['title']) . '</span>';
    $html .= '<meta itemprop="position" content="' . $position . '">';
    $html .= '</li>';
    
    $html .= '</ol></nav>';
    
    return $html;
}
