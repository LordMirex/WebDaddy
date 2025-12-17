<?php

function getRelatedItems($db, $postId, $type, $limit)
{
    $items = [];
    
    if ($type === 'related_posts' && $postId) {
        $stmt = $db->prepare("
            SELECT p.id, p.title, p.slug, p.excerpt as description, p.featured_image as image
            FROM blog_posts p
            WHERE p.id != ? AND p.status = 'published'
            ORDER BY p.publish_date DESC
            LIMIT ?
        ");
        $stmt->execute([$postId, $limit]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($posts as $post) {
            $items[] = [
                'url' => '/blog/' . $post['slug'] . '/',
                'title' => $post['title'],
                'description' => $post['description'],
                'image' => $post['image']
            ];
        }
    } elseif ($type === 'popular') {
        $stmt = $db->prepare("
            SELECT p.id, p.title, p.slug, p.excerpt as description, p.featured_image as image
            FROM blog_posts p
            WHERE p.status = 'published'
            ORDER BY p.view_count DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($posts as $post) {
            $items[] = [
                'url' => '/blog/' . $post['slug'] . '/',
                'title' => $post['title'],
                'description' => $post['description'],
                'image' => $post['image']
            ];
        }
    } elseif ($type === 'recent') {
        $stmt = $db->prepare("
            SELECT p.id, p.title, p.slug, p.excerpt as description, p.featured_image as image
            FROM blog_posts p
            WHERE p.status = 'published'
            ORDER BY p.publish_date DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($posts as $post) {
            $items[] = [
                'url' => '/blog/' . $post['slug'] . '/',
                'title' => $post['title'],
                'description' => $post['description'],
                'image' => $post['image']
            ];
        }
    }
    
    return $items;
}

function renderRelatedItemCard($item)
{
    $url = htmlspecialchars($item['url'] ?? '#');
    $title = htmlspecialchars($item['title'] ?? 'Untitled');
    $description = htmlspecialchars(substr($item['description'] ?? '', 0, 150));
    $image = $item['image'] ?? null;
    
    $html = '<div class="blog-related-item blog-related-item--card">';
    
    if ($image) {
        $html .= '<div class="blog-related-item__image">';
        $html .= '<img src="' . htmlspecialchars($image) . '" alt="' . $title . '" loading="lazy">';
        $html .= '</div>';
    }
    
    $html .= '<div class="blog-related-item__content">';
    $html .= '<h4><a href="' . $url . '">' . $title . '</a></h4>';
    $html .= '<p>' . $description . '</p>';
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function renderRelatedItemList($item)
{
    $url = htmlspecialchars($item['url'] ?? '#');
    $title = htmlspecialchars($item['title'] ?? 'Untitled');
    $image = $item['image'] ?? null;
    
    $html = '<div class="blog-related-item blog-related-item--list">';
    
    if ($image) {
        $html .= '<img src="' . htmlspecialchars($image) . '" alt="' . $title . '" class="blog-related-item__thumbnail" loading="lazy">';
    }
    
    $html .= '<a href="' . $url . '" class="blog-related-item__title">' . $title . '</a>';
    $html .= '</div>';
    
    return $html;
}

function renderRelatedItemCompact($item)
{
    $url = htmlspecialchars($item['url'] ?? '#');
    $title = htmlspecialchars($item['title'] ?? 'Untitled');
    
    return '<div class="blog-related-item blog-related-item--compact"><a href="' . $url . '">' . $title . '</a></div>';
}

function render_internal_authority($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $heading = htmlspecialchars($data['heading'] ?? 'Related Articles');
    $displayType = $data['display_type'] ?? 'cards';
    $source = $data['source'] ?? 'auto';
    $db = $context['db'] ?? null;
    
    $html = '<section class="blog-related-posts blog-related-posts--' . htmlspecialchars($displayType) . '">';
    $html .= '<h3 class="blog-related-posts__heading">' . $heading . '</h3>';
    
    $items = [];
    
    if ($source === 'manual' && !empty($data['manual_items'])) {
        $items = $data['manual_items'];
    } elseif ($source === 'auto' && $db) {
        $autoConfig = $data['auto_config'] ?? ['type' => 'related_posts', 'limit' => 3];
        $limit = (int)($autoConfig['limit'] ?? 3);
        $items = getRelatedItems($db, $block['post_id'] ?? null, $autoConfig['type'] ?? 'related_posts', $limit);
    }
    
    if (empty($items)) {
        $html .= '<p class="blog-related-posts__empty">No related content found.</p>';
    } else {
        $html .= '<div class="blog-related-posts__list">';
        
        foreach ($items as $item) {
            if ($displayType === 'cards') {
                $html .= renderRelatedItemCard($item);
            } elseif ($displayType === 'list') {
                $html .= renderRelatedItemList($item);
            } elseif ($displayType === 'compact') {
                $html .= renderRelatedItemCompact($item);
            }
        }
        
        $html .= '</div>';
    }
    
    $html .= '</section>';
    
    return $html;
}

function validate_internal_authority($data)
{
    if (empty($data['heading'])) {
        return ['error' => 'Heading is required'];
    }
    
    return ['valid' => true];
}
