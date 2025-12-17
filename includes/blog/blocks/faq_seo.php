<?php

function render_faq_seo($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $heading = htmlspecialchars($data['heading'] ?? 'Frequently Asked Questions');
    $headingLevel = $data['heading_level'] ?? 'h2';
    $items = $data['items'] ?? [];
    $schemaEnabled = $data['schema_enabled'] ?? true;
    $style = $data['style'] ?? 'accordion';
    
    $classList = ['blog-faq', 'blog-faq--' . htmlspecialchars($style)];
    
    if ($behaviors['collapsible'] ?? true) {
        $classList[] = 'blog-faq--collapsible';
    }
    
    $html = '<section class="' . implode(' ', $classList) . '">';
    $html .= '<' . htmlspecialchars($headingLevel) . ' class="blog-faq__heading">' . $heading . '</' . htmlspecialchars($headingLevel) . '>';
    
    if ($style === 'accordion' || $style === 'expanded') {
        $html .= '<div class="blog-faq__items">';
        
        foreach ($items as $index => $item) {
            $itemId = 'faq-' . $block['id'] . '-' . $index;
            $isOpen = $item['is_open'] ?? ($index === 0);
            
            $html .= '<div class="blog-faq__item' . ($isOpen ? ' blog-faq__item--open' : '') . '">';
            $html .= '<button class="blog-faq__toggle" data-toggle="' . htmlspecialchars($itemId) . '" aria-expanded="' . ($isOpen ? 'true' : 'false') . '">';
            $html .= '<h3 class="blog-faq__question">' . htmlspecialchars($item['question'] ?? '') . '</h3>';
            $html .= '<span class="blog-faq__icon"></span>';
            $html .= '</button>';
            $html .= '<div class="blog-faq__answer" id="' . htmlspecialchars($itemId) . '" style="' . ($style === 'expanded' ? '' : 'display: none;') . '">';
            $html .= blogSanitizeHtml($item['answer'] ?? '');
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    } else {
        $html .= '<div class="blog-faq__items blog-faq__items--simple">';
        
        foreach ($items as $item) {
            $html .= '<div class="blog-faq__item">';
            $html .= '<h3 class="blog-faq__question">' . htmlspecialchars($item['question'] ?? '') . '</h3>';
            $html .= '<div class="blog-faq__answer">';
            $html .= blogSanitizeHtml($item['answer'] ?? '');
            $html .= '</div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    if ($schemaEnabled && !empty($items)) {
        $schema = blogGenerateFAQSchema($items);
        if ($schema) {
            $html .= '<script type="application/ld+json">';
            $html .= json_encode($schema);
            $html .= '</script>';
        }
    }
    
    $html .= '</section>';
    
    return $html;
}

function validate_faq_seo($data)
{
    if (empty($data['items'])) {
        return ['error' => 'At least one FAQ item is required'];
    }
    
    foreach ($data['items'] as $item) {
        if (empty($item['question']) || empty($item['answer'])) {
            return ['error' => 'Each FAQ item must have a question and answer'];
        }
    }
    
    return ['valid' => true];
}
