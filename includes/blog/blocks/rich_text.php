<?php

function render_rich_text($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $content = $data['content'] ?? '';
    $typographySettings = $data['typography_settings'] ?? [];
    $lineLength = $typographySettings['line_length'] ?? 'optimal';
    
    $classList = ['blog-rich-text', 'blog-rich-text--' . htmlspecialchars($lineLength)];
    
    if ($behaviors['lazy_loaded'] ?? false) {
        $classList[] = 'blog-rich-text--lazy';
    }
    
    $html = '<div class="' . implode(' ', $classList) . '">';
    
    $content = blogAddHeadingAnchors(blogSanitizeHtml($content));
    $html .= $content;
    
    $html .= '</div>';
    
    return $html;
}

function validate_rich_text($data)
{
    if (empty($data['content'])) {
        return ['error' => 'Content is required'];
    }
    
    return ['valid' => true];
}
