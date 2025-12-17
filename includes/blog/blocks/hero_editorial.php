<?php

function render_hero_editorial($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $h1Title = htmlspecialchars($data['h1_title'] ?? 'Untitled Article');
    $subtitle = htmlspecialchars($data['subtitle'] ?? '');
    $featuredImage = $data['featured_image'] ?? null;
    $featuredImageAlt = htmlspecialchars($data['featured_image_alt'] ?? $h1Title);
    $category = $data['category'] ?? [];
    $readingTime = (int)($data['reading_time'] ?? 5);
    $publishDate = $data['publish_date'] ?? null;
    $author = $data['author'] ?? ['name' => 'WebDaddy Team'];
    $shareButtons = $data['share_buttons'] ?? [];
    
    $html = '';
    
    if ($layout === 'default' || $layout === 'split') {
        $html .= '<section class="blog-hero blog-hero--' . htmlspecialchars($layout) . '">';
        
        if ($featuredImage) {
            $html .= '<div class="blog-hero__image">';
            $html .= '<img src="' . htmlspecialchars($featuredImage) . '" alt="' . $featuredImageAlt . '" loading="lazy" decoding="async">';
            $html .= '</div>';
        }
        
        $html .= '<div class="blog-hero__content">';
        
        if ($category) {
            $html .= '<span class="blog-hero__category">' . htmlspecialchars($category['name'] ?? '') . '</span>';
        }
        
        $html .= '<h1 class="blog-hero__title">' . $h1Title . '</h1>';
        
        if ($subtitle) {
            $html .= '<p class="blog-hero__subtitle">' . $subtitle . '</p>';
        }
        
        $html .= '<div class="blog-hero__meta">';
        
        if ($author) {
            if ($author['avatar'] ?? false) {
                $html .= '<img src="' . htmlspecialchars($author['avatar']) . '" alt="' . htmlspecialchars($author['name']) . '" class="blog-hero__avatar">';
            }
            $html .= '<span class="blog-hero__author">' . htmlspecialchars($author['name']) . '</span>';
        }
        
        if ($publishDate) {
            $html .= '<span class="blog-hero__date">' . date('M d, Y', strtotime($publishDate)) . '</span>';
        }
        
        $html .= '<span class="blog-hero__reading-time">' . $readingTime . ' min read</span>';
        
        $html .= '</div>';
        
        if (!empty($shareButtons)) {
            $html .= '<div class="blog-hero__share">';
            foreach ($shareButtons as $button) {
                $html .= '<button class="blog-share-btn blog-share-btn--' . htmlspecialchars($button) . '" data-share="' . htmlspecialchars($button) . '">Share on ' . ucfirst($button) . '</button>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        $html .= '</section>';
    } elseif ($layout === 'minimal') {
        $html .= '<section class="blog-hero blog-hero--minimal">';
        $html .= '<h1 class="blog-hero__title">' . $h1Title . '</h1>';
        
        if ($subtitle) {
            $html .= '<p class="blog-hero__subtitle">' . $subtitle . '</p>';
        }
        
        $html .= '<div class="blog-hero__meta">';
        if ($readingTime) {
            $html .= '<span class="blog-hero__reading-time">' . $readingTime . ' min read</span>';
        }
        $html .= '</div>';
        $html .= '</section>';
    }
    
    return $html;
}

function validate_hero_editorial($data)
{
    if (empty($data['h1_title'])) {
        return ['error' => 'H1 title is required'];
    }
    
    if (strlen($data['h1_title']) > 60) {
        return ['error' => 'H1 title must be 60 characters or less'];
    }
    
    if (isset($data['subtitle']) && strlen($data['subtitle']) > 160) {
        return ['error' => 'Subtitle must be 160 characters or less'];
    }
    
    return ['valid' => true];
}
