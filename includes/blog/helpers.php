<?php

function blogGenerateSlug($text)
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

function blogCalculateReadingTime($blocks)
{
    $wordCount = 0;
    foreach ($blocks as $block) {
        $data = is_array($block['data_payload']) ? $block['data_payload'] : json_decode($block['data_payload'], true);
        if (isset($data['content'])) {
            $wordCount += str_word_count(strip_tags($data['content']));
        }
        if (isset($data['h1_title'])) {
            $wordCount += str_word_count($data['h1_title']);
        }
        if (isset($data['heading'])) {
            $wordCount += str_word_count($data['heading']);
        }
    }
    return max(1, ceil($wordCount / 200));
}

function blogTruncate($text, $length = 160)
{
    $text = strip_tags($text);
    if (strlen($text) <= $length) {
        return $text;
    }
    $text = substr($text, 0, $length);
    $lastSpace = strrpos($text, ' ');
    if ($lastSpace !== false) {
        $text = substr($text, 0, $lastSpace);
    }
    return $text . '...';
}

function blogFormatDate($datetime, $format = 'F j, Y')
{
    $timestamp = strtotime($datetime);
    return date($format, $timestamp);
}

function blogGetExcerpt($post, $maxLength = 160)
{
    if (!empty($post['excerpt'])) {
        return blogTruncate($post['excerpt'], $maxLength);
    }
    if (!empty($post['meta_description'])) {
        return blogTruncate($post['meta_description'], $maxLength);
    }
    return '';
}

function blogGetFeaturedImageAlt($post)
{
    if (!empty($post['featured_image_alt'])) {
        return $post['featured_image_alt'];
    }
    return $post['title'];
}

function blogGetCanonicalUrl($post)
{
    if (!empty($post['canonical_url'])) {
        return $post['canonical_url'];
    }
    return SITE_URL . '/blog/' . $post['slug'] . '/';
}

function blogGetPostUrl($post, $affiliateCode = null)
{
    $url = '/blog/' . (is_array($post) ? $post['slug'] : $post) . '/';
    if ($affiliateCode) {
        $url .= '?aff=' . urlencode($affiliateCode);
    }
    return $url;
}

function blogGetCategoryUrl($category, $affiliateCode = null)
{
    $slug = is_array($category) ? $category['slug'] : $category;
    $url = '/blog/category/' . $slug . '/';
    if ($affiliateCode) {
        $url .= '?aff=' . urlencode($affiliateCode);
    }
    return $url;
}

function blogSanitizeHtml($html, $allowedTags = null)
{
    if ($allowedTags === null) {
        $allowedTags = '<p><br><strong><em><u><s><a><ul><ol><li><h2><h3><h4><blockquote><pre><code><img><figure><figcaption><mark>';
    }
    return strip_tags($html, $allowedTags);
}

function blogGenerateAnchorId($heading)
{
    $id = strtolower(trim($heading));
    $id = preg_replace('/[^a-z0-9]+/', '-', $id);
    $id = trim($id, '-');
    return $id;
}

function blogAddHeadingAnchors($html)
{
    return preg_replace_callback(
        '/<(h[2-4])([^>]*)>(.*?)<\/\1>/i',
        function ($matches) {
            $tag = $matches[1];
            $attrs = $matches[2];
            $content = $matches[3];
            $id = blogGenerateAnchorId(strip_tags($content));
            
            if (preg_match('/id=["\'][^"\']*["\']/', $attrs)) {
                return $matches[0];
            }
            
            return "<{$tag} id=\"{$id}\"{$attrs}>{$content}</{$tag}>";
        },
        $html
    );
}

function blogExtractTableOfContents($blocks)
{
    $toc = [];
    
    foreach ($blocks as $block) {
        $data = is_array($block['data_payload']) ? $block['data_payload'] : json_decode($block['data_payload'], true);
        
        if ($block['block_type'] === 'hero_editorial' && isset($data['h1_title'])) {
            continue;
        }
        
        if ($block['block_type'] === 'rich_text' && isset($data['content'])) {
            preg_match_all('/<(h[2-3])[^>]*>(.*?)<\/\1>/i', $data['content'], $matches);
            foreach ($matches[2] as $index => $heading) {
                $toc[] = [
                    'level' => (int)substr($matches[1][$index], 1),
                    'text' => strip_tags($heading),
                    'id' => blogGenerateAnchorId(strip_tags($heading))
                ];
            }
        }
        
        if (in_array($block['block_type'], ['visual_explanation', 'faq_seo']) && isset($data['heading'])) {
            $level = isset($data['heading_level']) ? (int)substr($data['heading_level'], 1) : 2;
            $toc[] = [
                'level' => $level,
                'text' => strip_tags($data['heading']),
                'id' => blogGenerateAnchorId($data['heading'])
            ];
        }
    }
    
    return $toc;
}

function blogGetAffiliateCode()
{
    return $_GET['aff'] ?? $_SESSION['affiliate_code'] ?? null;
}

function blogTrackView($db, $postId)
{
    $sessionId = session_id();
    $cacheKey = "blog_view_{$postId}_{$sessionId}";
    $trackFile = sys_get_temp_dir() . '/' . md5($cacheKey);
    
    if (file_exists($trackFile) && (time() - filemtime($trackFile)) < 3600) {
        return false;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO blog_analytics (post_id, event_type, session_id, referrer, affiliate_code, user_agent, created_at)
            VALUES (?, 'view', ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            $postId,
            $sessionId,
            $_SERVER['HTTP_REFERER'] ?? null,
            blogGetAffiliateCode(),
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        $db->prepare("UPDATE blog_posts SET view_count = view_count + 1 WHERE id = ?")->execute([$postId]);
        
        touch($trackFile);
        return true;
    } catch (Exception $e) {
        error_log("Blog view tracking error: " . $e->getMessage());
        return false;
    }
}

function blogGetRelativeTime($datetime)
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $timestamp);
    }
}
