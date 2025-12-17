<?php

function render_inline_conversion($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $headline = htmlspecialchars($data['headline'] ?? '');
    $subheadline = htmlspecialchars($data['subheadline'] ?? '');
    $style = $data['style'] ?? 'card';
    $background = $data['background'] ?? 'gradient';
    $backgroundValue = $data['background_value'] ?? '';
    $ctaPrimary = $data['cta_primary'] ?? [];
    $ctaSecondary = $data['cta_secondary'] ?? [];
    $affiliateAware = $data['affiliate_aware'] ?? true;
    $urgencyText = $data['urgency_text'] ?? '';
    
    $classList = ['blog-inline-cta', 'blog-inline-cta--' . htmlspecialchars($style)];
    
    if ($behaviors['cta_aware'] ?? false) {
        $classList[] = 'blog-inline-cta--tracked';
    }
    
    $inlineStyle = '';
    if ($background === 'gradient' && $backgroundValue) {
        $inlineStyle = 'background: ' . $backgroundValue . ';';
    } elseif ($background === 'solid' && $backgroundValue) {
        $inlineStyle = 'background-color: ' . htmlspecialchars($backgroundValue) . ';';
    } elseif ($background === 'image' && $backgroundValue) {
        $inlineStyle = 'background-image: url(' . htmlspecialchars($backgroundValue) . ');';
    }
    
    $html = '<div class="' . implode(' ', $classList) . '"' . ($inlineStyle ? ' style="' . $inlineStyle . '"' : '') . '>';
    $html .= '<div class="blog-inline-cta__content">';
    $html .= '<h3 class="blog-inline-cta__headline">' . $headline . '</h3>';
    
    if ($subheadline) {
        $html .= '<p class="blog-inline-cta__subheadline">' . $subheadline . '</p>';
    }
    
    if ($urgencyText) {
        $html .= '<p class="blog-inline-cta__urgency">' . htmlspecialchars($urgencyText) . '</p>';
    }
    
    $html .= '<div class="blog-inline-cta__buttons">';
    
    if ($ctaPrimary && $ctaPrimary['url']) {
        $action = $ctaPrimary['action'] ?? 'link';
        $html .= '<a href="' . htmlspecialchars($ctaPrimary['url']) . '" class="blog-btn blog-btn--primary" data-cta-action="' . htmlspecialchars($action) . '">';
        $html .= htmlspecialchars($ctaPrimary['text'] ?? 'Get Started');
        $html .= '</a>';
    }
    
    if (($ctaSecondary['enabled'] ?? false) && $ctaSecondary['url']) {
        $html .= '<a href="' . htmlspecialchars($ctaSecondary['url']) . '" class="blog-btn blog-btn--secondary">';
        $html .= htmlspecialchars($ctaSecondary['text'] ?? 'Learn More');
        $html .= '</a>';
    }
    
    $html .= '</div>';
    
    if ($affiliateAware && (blogGetAffiliateCode() ?? false)) {
        $html .= '<p class="blog-inline-cta__affiliate-msg">Special offer for you!</p>';
    }
    
    $html .= '</div>';
    $html .= '</div>';
    
    return $html;
}

function validate_inline_conversion($data)
{
    if (empty($data['headline'])) {
        return ['error' => 'Headline is required'];
    }
    
    if (empty($data['cta_primary']['url'] ?? null)) {
        return ['error' => 'Primary CTA URL is required'];
    }
    
    return ['valid' => true];
}
