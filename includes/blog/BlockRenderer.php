<?php

class BlockRenderer
{
    protected $db;
    
    const BLOCK_TYPES = [
        'hero_editorial',
        'rich_text',
        'section_divider',
        'visual_explanation',
        'inline_conversion',
        'internal_authority',
        'faq_seo',
        'final_conversion'
    ];
    
    const LAYOUT_VARIANTS = [
        'default', 'split_left', 'split_right', 'wide', 'contained', 'card_grid', 'timeline'
    ];
    
    const BEHAVIORS = [
        'sticky', 'collapsible', 'lazy_loaded', 'animated', 'cta_aware'
    ];
    
    public function __construct($db)
    {
        $this->db = $db;
    }
    
    public function render($block, $context = [])
    {
        $type = $block['block_type'];
        $layoutVariant = $block['layout_variant'] ?? 'default';
        $behaviors = $block['behavior_config'] ?? [];
        
        if (!in_array($type, self::BLOCK_TYPES)) {
            throw new InvalidArgumentException("Unknown block type: $type");
        }
        
        $renderFunction = "render_{$type}";
        if (!function_exists($renderFunction)) {
            throw new RuntimeException("Renderer not found for block type: $type");
        }
        
        return $renderFunction($block, $layoutVariant, $behaviors, $context);
    }
    
    public function validate($type, $data)
    {
        if (!in_array($type, self::BLOCK_TYPES)) {
            return ['error' => "Invalid block type: $type"];
        }
        
        $validator = "validate_{$type}";
        if (function_exists($validator)) {
            return $validator($data);
        }
        
        return ['valid' => true];
    }
    
    public function renderWithWrapper($block, $context = [])
    {
        $behaviors = $block['behavior_config'] ?? [];
        $classList = ['blog-block', 'blog-block--' . $block['block_type']];
        
        if ($behaviors['sticky'] ?? false) {
            $classList[] = 'blog-block--sticky';
        }
        if ($behaviors['animated'] ?? false) {
            $classList[] = 'blog-block--animated';
        }
        if ($behaviors['lazy_loaded'] ?? false) {
            $classList[] = 'blog-block--lazy-loaded';
        }
        
        $html = '<div class="' . implode(' ', $classList) . '" data-block-type="' . htmlspecialchars($block['block_type']) . '" data-block-id="' . (int)$block['id'] . '">';
        $html .= $this->render($block, $context);
        $html .= '</div>';
        
        return $html;
    }
    
    public static function getBlockSchema($type)
    {
        $schemas = [
            'hero_editorial' => [
                'h1_title' => 'string (max 60 chars)',
                'subtitle' => 'string (max 160 chars)',
                'featured_image' => 'string (URL)',
                'featured_image_alt' => 'string',
                'category' => ['name' => 'string', 'slug' => 'string'],
                'reading_time' => 'integer',
                'publish_date' => 'datetime',
                'author' => ['name' => 'string', 'avatar' => 'string (URL)'],
                'share_buttons' => 'array'
            ],
            'rich_text' => [
                'content' => 'string (HTML)',
                'typography_settings' => ['line_length' => 'string', 'paragraph_spacing' => 'string']
            ],
            'section_divider' => [
                'divider_type' => 'string (line|gradient|labeled|space)',
                'label_text' => 'string',
                'label_style' => 'string',
                'spacing' => 'string (small|medium|large)',
                'color_scheme' => 'string'
            ],
            'visual_explanation' => [
                'heading' => 'string',
                'heading_level' => 'string (h2|h3)',
                'content' => 'string (HTML)',
                'image' => ['url' => 'string', 'alt' => 'string', 'caption' => 'string'],
                'image_position' => 'string (left|right)',
                'cta' => ['enabled' => 'bool', 'text' => 'string', 'url' => 'string', 'style' => 'string']
            ],
            'inline_conversion' => [
                'headline' => 'string',
                'subheadline' => 'string',
                'style' => 'string (card|banner|minimal|floating)',
                'background' => 'string',
                'background_value' => 'string',
                'cta_primary' => ['text' => 'string', 'url' => 'string', 'action' => 'string'],
                'cta_secondary' => ['enabled' => 'bool', 'text' => 'string', 'url' => 'string'],
                'template_id' => 'integer',
                'affiliate_aware' => 'bool',
                'show_price' => 'bool',
                'urgency_text' => 'string'
            ],
            'internal_authority' => [
                'heading' => 'string',
                'display_type' => 'string (cards|list|compact)',
                'source' => 'string (auto|manual)',
                'auto_config' => ['type' => 'string', 'limit' => 'integer'],
                'manual_items' => 'array',
                'show_templates' => 'bool',
                'templates_heading' => 'string',
                'template_limit' => 'integer'
            ],
            'faq_seo' => [
                'heading' => 'string',
                'heading_level' => 'string (h2|h3)',
                'items' => 'array',
                'schema_enabled' => 'bool',
                'style' => 'string (accordion|expanded|simple)'
            ],
            'final_conversion' => [
                'headline' => 'string',
                'subheadline' => 'string',
                'style' => 'string (hero|card|split|minimal)',
                'background' => 'string',
                'background_value' => 'string',
                'cta_config' => 'object',
                'trust_elements' => ['enabled' => 'bool', 'items' => 'array'],
                'affiliate_override' => ['enabled' => 'bool', 'message' => 'string', 'discount_highlight' => 'string']
            ]
        ];
        
        return $schemas[$type] ?? null;
    }
}
