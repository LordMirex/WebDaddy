<?php

function render_section_divider($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $dividerType = $data['divider_type'] ?? 'line';
    $labelText = $data['label_text'] ?? '';
    $labelStyle = $data['label_style'] ?? 'badge';
    $spacing = $data['spacing'] ?? 'medium';
    $colorScheme = $data['color_scheme'] ?? 'default';
    
    $classList = ['blog-divider', 'blog-divider--' . htmlspecialchars($dividerType), 'blog-divider--spacing-' . htmlspecialchars($spacing), 'blog-divider--' . htmlspecialchars($colorScheme)];
    
    $html = '<div class="' . implode(' ', $classList) . '">';
    
    if ($dividerType === 'labeled' && $labelText) {
        $html .= '<div class="blog-divider__label blog-divider__label--' . htmlspecialchars($labelStyle) . '">';
        $html .= htmlspecialchars($labelText);
        $html .= '</div>';
    } elseif ($dividerType === 'gradient') {
        $html .= '<div class="blog-divider__line blog-divider__line--gradient"></div>';
    } elseif ($dividerType === 'line') {
        $html .= '<div class="blog-divider__line"></div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

function validate_section_divider($data)
{
    $validTypes = ['line', 'gradient', 'labeled', 'space'];
    $type = $data['divider_type'] ?? 'line';
    
    if (!in_array($type, $validTypes)) {
        return ['error' => 'Invalid divider type'];
    }
    
    return ['valid' => true];
}
