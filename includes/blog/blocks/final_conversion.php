<?php

function render_final_conversion($block, $layout = 'default', $behaviors = [], $context = [])
{
    $data = $block['data_payload'] ?? [];
    $headline = htmlspecialchars($data['headline'] ?? '');
    $subheadline = htmlspecialchars($data['subheadline'] ?? '');
    $style = $data['style'] ?? 'hero';
    $background = $data['background'] ?? 'gradient';
    $backgroundValue = $data['background_value'] ?? '';
    $ctaConfig = $data['cta_config'] ?? [];
    $trustElements = $data['trust_elements'] ?? [];
    $affiliateOverride = $data['affiliate_override'] ?? [];
    
    $classList = ['blog-final-cta', 'blog-final-cta--' . htmlspecialchars($style)];
    
    $inlineStyle = '';
    if ($background === 'gradient' && $backgroundValue) {
        $inlineStyle = 'background: ' . $backgroundValue . ';';
    } elseif ($background === 'solid' && $backgroundValue) {
        $inlineStyle = 'background-color: ' . htmlspecialchars($backgroundValue) . ';';
    }
    
    $html = '<section class="' . implode(' ', $classList) . '"' . ($inlineStyle ? ' style="' . $inlineStyle . '"' : '') . '>';
    $html .= '<div class="blog-final-cta__content">';
    
    $html .= '<h2 class="blog-final-cta__headline">' . $headline . '</h2>';
    
    if ($subheadline) {
        $html .= '<p class="blog-final-cta__subheadline">' . $subheadline . '</p>';
    }
    
    $html .= '<div class="blog-final-cta__action">';
    
    if ($ctaConfig['type'] === 'whatsapp') {
        $message = urlencode($ctaConfig['whatsapp']['message'] ?? 'Hi! I\'m interested in your services.');
        $html .= '<a href="https://wa.me/?text=' . $message . '" class="blog-btn blog-btn--whatsapp" target="_blank">';
        $html .= htmlspecialchars($ctaConfig['whatsapp']['button_text'] ?? 'Chat on WhatsApp');
        $html .= '</a>';
    } elseif ($ctaConfig['type'] === 'template_selector') {
        $html .= '<div class="blog-template-selector">';
        $html .= '<p>' . htmlspecialchars($ctaConfig['template_selector']['heading'] ?? 'Browse our templates') . '</p>';
        $html .= '<button class="blog-btn blog-btn--primary" onclick="openTemplateModal()">View Templates</button>';
        $html .= '</div>';
    } elseif ($ctaConfig['type'] === 'custom') {
        $html .= '<a href="' . htmlspecialchars($ctaConfig['custom']['url'] ?? '#') . '" class="blog-btn blog-btn--primary">';
        $html .= htmlspecialchars($ctaConfig['custom']['button_text'] ?? 'Get Started');
        $html .= '</a>';
    }
    
    $html .= '</div>';
    
    if (($trustElements['enabled'] ?? true) && !empty($trustElements['items'])) {
        $html .= '<div class="blog-final-cta__trust">';
        
        foreach ($trustElements['items'] as $element) {
            $html .= '<div class="blog-trust-element blog-trust-element--' . htmlspecialchars($element) . '">';
            $html .= getTrustElementContent($element);
            $html .= '</div>';
        }
        
        $html .= '</div>';
    }
    
    if (($affiliateOverride['enabled'] ?? false) && (blogGetAffiliateCode() ?? false)) {
        $html .= '<p class="blog-final-cta__affiliate">' . htmlspecialchars($affiliateOverride['message'] ?? 'Special offer for you!') . '</p>';
    }
    
    $html .= '</div>';
    $html .= '</section>';
    
    return $html;
}

function getTrustElementContent($element)
{
    $elements = [
        'fast_delivery' => '✓ Fast Delivery',
        'secure_payment' => '✓ Secure Payment',
        'support_24_7' => '✓ 24/7 Support',
        'satisfaction' => '✓ Money-Back Guarantee'
    ];
    
    return htmlspecialchars($elements[$element] ?? $element);
}

function validate_final_conversion($data)
{
    if (empty($data['headline'])) {
        return ['error' => 'Headline is required'];
    }
    
    if (empty($data['cta_config'])) {
        return ['error' => 'CTA configuration is required'];
    }
    
    return ['valid' => true];
}
