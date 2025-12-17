<?php

function render_visual_explanation($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $heading = htmlspecialchars($data['heading'] ?? '');
    $headingLevel = $data['heading_level'] ?? 'h2';
    $content = $data['content'] ?? '';
    $image = $data['image'] ?? [];
    $imagePosition = $data['image_position'] ?? 'right';
    $cta = $data['cta'] ?? [];
    
    if ($layout === 'split_left' || $layout === 'split_right') {
        $actualPosition = $layout === 'split_left' ? 'left' : 'right';
    } else {
        $actualPosition = $imagePosition;
    }
    
    $classList = ['blog-visual-explanation', 'blog-visual-explanation--image-' . htmlspecialchars($actualPosition)];
    
    if ($behaviors['animated'] ?? false) {
        $classList[] = 'blog-visual-explanation--animated';
    }
    
    $html = '<div class="' . implode(' ', $classList) . '">';
    
    $html .= '<div class="blog-visual-explanation__content">';
    $html .= '<' . htmlspecialchars($headingLevel) . ' class="blog-visual-explanation__heading">';
    $html .= $heading;
    $html .= '</' . htmlspecialchars($headingLevel) . '>';
    $html .= '<div class="blog-visual-explanation__text">';
    $html .= blogSanitizeHtml($content);
    $html .= '</div>';
    
    if ($cta['enabled'] ?? false) {
        $html .= '<a href="' . htmlspecialchars($cta['url'] ?? '#') . '" class="blog-btn blog-btn--' . htmlspecialchars($cta['style'] ?? 'primary') . '">';
        $html .= htmlspecialchars($cta['text'] ?? 'Learn More');
        $html .= '</a>';
    }
    
    $html .= '</div>';
    
    if ($image && $image['url']) {
        $html .= '<div class="blog-visual-explanation__image">';
        $html .= '<img src="' . htmlspecialchars($image['url']) . '" alt="' . htmlspecialchars($image['alt'] ?? $heading) . '" loading="lazy" decoding="async">';
        
        if ($image['caption'] ?? false) {
            $html .= '<figcaption>' . htmlspecialchars($image['caption']) . '</figcaption>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function validate_visual_explanation($data)
{
    if (empty($data['heading'])) {
        return ['error' => 'Heading is required'];
    }
    
    if (empty($data['content'])) {
        return ['error' => 'Content is required'];
    }
    
    return ['valid' => true];
}
