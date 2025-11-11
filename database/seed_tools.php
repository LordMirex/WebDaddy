<?php
/**
 * Tool Seeder Script
 * Adds 23 sample tools to the database for pagination testing
 * Run this from command line: php database/seed_tools.php
 */

require_once __DIR__ . '/../includes/db.php';

$db = getDb();

// Sample tools data with various categories
$tools = [
    // API & Integration Tools (5 items)
    [
        'name' => 'ChatGPT API Access',
        'slug' => 'chatgpt-api-access',
        'category' => 'API Keys',
        'short_description' => 'Full access to OpenAI ChatGPT API for your applications',
        'description' => 'Get unlimited access to ChatGPT API with this premium key. Perfect for chatbots, content generation, and AI-powered applications.',
        'features' => 'Unlimited requests, GPT-4 access, Priority support',
        'price' => 15000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Stripe Payment Gateway',
        'slug' => 'stripe-payment-gateway',
        'category' => 'API Keys',
        'short_description' => 'Accept payments online with Stripe integration',
        'description' => 'Complete Stripe API access for processing payments, subscriptions, and invoices on your website.',
        'features' => 'Payment processing, Subscription billing, Invoice management',
        'price' => 12000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'SendGrid Email API',
        'slug' => 'sendgrid-email-api',
        'category' => 'API Keys',
        'short_description' => 'Reliable email delivery service for your applications',
        'description' => 'SendGrid API key with 10,000 emails per month. Perfect for transactional emails and newsletters.',
        'features' => '10k emails/month, Analytics dashboard, Template builder',
        'price' => 8000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Google Maps API Key',
        'slug' => 'google-maps-api-key',
        'category' => 'API Keys',
        'short_description' => 'Integrate Google Maps into your website or app',
        'description' => 'Full Google Maps API access with geocoding, directions, and places features.',
        'features' => 'Maps display, Geocoding, Directions API',
        'price' => 9500,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Twilio SMS Gateway',
        'slug' => 'twilio-sms-gateway',
        'category' => 'API Keys',
        'short_description' => 'Send SMS and voice messages programmatically',
        'description' => 'Twilio API credentials for SMS, voice calls, and WhatsApp messaging.',
        'features' => 'SMS messaging, Voice calls, WhatsApp integration',
        'price' => 11000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    
    // Business Software (6 items)
    [
        'name' => 'Microsoft Office 365',
        'slug' => 'microsoft-office-365',
        'category' => 'Software',
        'short_description' => 'Complete Office suite with Word, Excel, PowerPoint',
        'description' => 'Lifetime Microsoft Office 365 license with all premium applications and 1TB cloud storage.',
        'features' => 'Word, Excel, PowerPoint, OneDrive 1TB',
        'price' => 25000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Adobe Creative Cloud',
        'slug' => 'adobe-creative-cloud',
        'category' => 'Software',
        'short_description' => 'Access to Photoshop, Illustrator, Premiere Pro and more',
        'description' => 'Full Adobe Creative Cloud subscription with all creative applications.',
        'features' => 'Photoshop, Illustrator, Premiere Pro, After Effects',
        'price' => 35000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Grammarly Premium',
        'slug' => 'grammarly-premium',
        'category' => 'Software',
        'short_description' => 'Advanced writing assistant for perfect grammar',
        'description' => 'Grammarly Premium account with advanced grammar checks, plagiarism detector, and tone suggestions.',
        'features' => 'Grammar check, Plagiarism detector, Tone suggestions',
        'price' => 18000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Canva Pro Account',
        'slug' => 'canva-pro-account',
        'category' => 'Software',
        'short_description' => 'Professional design tool for creating stunning graphics',
        'description' => 'Canva Pro subscription with premium templates, brand kit, and background remover.',
        'features' => 'Premium templates, Brand kit, Background remover',
        'price' => 16000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Zoom Pro License',
        'slug' => 'zoom-pro-license',
        'category' => 'Software',
        'short_description' => 'Host unlimited meetings with up to 100 participants',
        'description' => 'Zoom Pro license for professional video conferencing with recording features.',
        'features' => 'Unlimited meetings, 100 participants, Cloud recording',
        'price' => 22000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Slack Premium Workspace',
        'slug' => 'slack-premium-workspace',
        'category' => 'Software',
        'short_description' => 'Team collaboration tool with unlimited message history',
        'description' => 'Slack Premium workspace for seamless team communication and collaboration.',
        'features' => 'Unlimited messages, Screen sharing, Advanced security',
        'price' => 20000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    
    // SEO & Marketing Tools (6 items)
    [
        'name' => 'SEMrush Pro Account',
        'slug' => 'semrush-pro-account',
        'category' => 'Marketing',
        'short_description' => 'Complete SEO toolkit for keyword research and analytics',
        'description' => 'SEMrush Pro subscription with keyword research, site audit, and competitor analysis.',
        'features' => 'Keyword research, Site audit, Competitor analysis',
        'price' => 28000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Ahrefs Standard Plan',
        'slug' => 'ahrefs-standard-plan',
        'category' => 'Marketing',
        'short_description' => 'Backlink analysis and SEO research tool',
        'description' => 'Ahrefs subscription for comprehensive backlink analysis and SEO insights.',
        'features' => 'Backlink analysis, Keyword explorer, Content explorer',
        'price' => 32000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Mailchimp Premium',
        'slug' => 'mailchimp-premium',
        'category' => 'Marketing',
        'short_description' => 'Email marketing platform for growing your audience',
        'description' => 'Mailchimp Premium with advanced automation, A/B testing, and analytics.',
        'features' => 'Email automation, A/B testing, Advanced analytics',
        'price' => 24000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Buffer Business Plan',
        'slug' => 'buffer-business-plan',
        'category' => 'Marketing',
        'short_description' => 'Social media management and scheduling tool',
        'description' => 'Buffer Business plan for managing multiple social media accounts efficiently.',
        'features' => 'Schedule posts, Analytics, Team collaboration',
        'price' => 19000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Hootsuite Professional',
        'slug' => 'hootsuite-professional',
        'category' => 'Marketing',
        'short_description' => 'Manage all your social accounts in one dashboard',
        'description' => 'Hootsuite Professional subscription for comprehensive social media management.',
        'features' => 'Multi-account management, Analytics, Team features',
        'price' => 21000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Google Ads Credit',
        'slug' => 'google-ads-credit',
        'category' => 'Marketing',
        'short_description' => '₦50,000 advertising credit for Google Ads',
        'description' => 'Google Ads promotional credit to boost your online advertising campaigns.',
        'features' => '₦50k ad credit, Campaign setup guide, Support',
        'price' => 45000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    
    // Development Tools (6 items)
    [
        'name' => 'GitHub Copilot License',
        'slug' => 'github-copilot-license',
        'category' => 'Development',
        'short_description' => 'AI-powered code completion for faster development',
        'description' => 'GitHub Copilot subscription for intelligent code suggestions and auto-completion.',
        'features' => 'AI code completion, Multi-language support, IDE integration',
        'price' => 14000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'JetBrains All Products',
        'slug' => 'jetbrains-all-products',
        'category' => 'Development',
        'short_description' => 'Access to all JetBrains IDEs including IntelliJ',
        'description' => 'JetBrains All Products Pack license for professional development tools.',
        'features' => 'IntelliJ IDEA, PyCharm, WebStorm, PhpStorm',
        'price' => 30000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Postman Team Plan',
        'slug' => 'postman-team-plan',
        'category' => 'Development',
        'short_description' => 'API testing and collaboration platform',
        'description' => 'Postman Team subscription for collaborative API development and testing.',
        'features' => 'API testing, Team collaboration, Mock servers',
        'price' => 17000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Figma Professional',
        'slug' => 'figma-professional',
        'category' => 'Development',
        'short_description' => 'Collaborative design tool for UI/UX designers',
        'description' => 'Figma Professional plan for team-based design collaboration.',
        'features' => 'Unlimited files, Team libraries, Version history',
        'price' => 23000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'Vercel Pro Account',
        'slug' => 'vercel-pro-account',
        'category' => 'Development',
        'short_description' => 'Deploy and host web applications with ease',
        'description' => 'Vercel Pro subscription for fast deployment and hosting of web apps.',
        'features' => 'Unlimited deployments, Custom domains, Analytics',
        'price' => 26000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ],
    [
        'name' => 'AWS Developer Credits',
        'slug' => 'aws-developer-credits',
        'category' => 'Development',
        'short_description' => '$100 cloud hosting credits for AWS services',
        'description' => 'AWS promotional credits for cloud computing, storage, and database services.',
        'features' => '$100 credits, EC2, S3, RDS access',
        'price' => 38000,
        'thumbnail_url' => '/assets/images/placeholder.jpg',
        'stock_unlimited' => 1,
        'active' => 1
    ]
];

try {
    $db->beginTransaction();
    
    $insertedCount = 0;
    $skippedCount = 0;
    
    foreach ($tools as $tool) {
        // Check if tool already exists
        $stmt = $db->prepare("SELECT id FROM tools WHERE slug = ?");
        $stmt->execute([$tool['slug']]);
        
        if ($stmt->fetch()) {
            echo "Skipped: {$tool['name']} (already exists)\n";
            $skippedCount++;
            continue;
        }
        
        // Insert tool
        $stmt = $db->prepare("
            INSERT INTO tools (
                name, slug, category, short_description, description, features,
                price, thumbnail_url, stock_unlimited, active, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, datetime('now'))
        ");
        
        $stmt->execute([
            $tool['name'],
            $tool['slug'],
            $tool['category'],
            $tool['short_description'],
            $tool['description'],
            $tool['features'],
            $tool['price'],
            $tool['thumbnail_url'],
            $tool['stock_unlimited'],
            $tool['active']
        ]);
        
        echo "Added: {$tool['name']}\n";
        $insertedCount++;
    }
    
    $db->commit();
    
    echo "\n✅ Seeding complete!\n";
    echo "   Inserted: $insertedCount tools\n";
    echo "   Skipped: $skippedCount tools\n";
    
    // Show total count
    $stmt = $db->query("SELECT COUNT(*) as total FROM tools WHERE active = 1");
    $result = $stmt->fetch();
    echo "   Total active tools: {$result['total']}\n";
    
} catch (Exception $e) {
    $db->rollback();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
