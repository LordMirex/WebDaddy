<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
header('Pragma: no-cache', false);
header('Expires: 0', false);

startSecureSession();
handleAffiliateTracking();

$activeNav = 'faq';
$affiliateCode = getAffiliateCode();

$pageTitle = 'FAQ - Professional Website Templates & Digital Tools - ' . SITE_NAME;
$pageDescription = 'Comprehensive guide to WebDaddy Empire. Learn about website templates, digital tools, pricing, delivery, customization, support, refunds, and how to launch your online business in 24 hours.';
$pageUrl = SITE_URL . '/faq.php';
$pageKeywords = 'website templates FAQ, digital tools, pricing, delivery, customization, support, refund policy';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {"@type": "Question", "name": "How do I get started with WebDaddy Empire?", "acceptedAnswer": {"@type": "Answer", "text": "Getting started is simple: (1) Browse our templates or digital tools collection, (2) Add your chosen items to your cart, (3) Proceed to checkout and select your payment method, (4) Complete payment securely through Paystack, (5) Receive instant download links via email. You can start building your website immediately without any delays."}},
            {"@type": "Question", "name": "How long does website template delivery take?", "acceptedAnswer": {"@type": "Answer", "text": "Delivery is instant! After your payment is confirmed, you receive your download links immediately via email. There is no waiting period. You can download and start using your templates right away, allowing you to launch your website in as little as 24 hours."}},
            {"@type": "Question", "name": "Can I customize the website templates?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! All our templates are fully editable and customizable. You can modify colors, text, images, fonts, layouts, sections, and more to match your brand perfectly. No coding knowledge is required—all templates are built with user-friendly editors like Elementor, Beaver Builder, or basic HTML/CSS that anyone can edit."}},
            {"@type": "Question", "name": "Do the templates come with a domain name?", "acceptedAnswer": {"@type": "Answer", "text": "Premium templates include a premium domain. Basic templates do not include a domain, but you can purchase one separately from our marketplace at affordable rates, or you can use your own domain if you already have one. We provide setup assistance for domain configuration."}},
            {"@type": "Question", "name": "What payment methods does WebDaddy Empire accept?", "acceptedAnswer": {"@type": "Answer", "text": "We accept multiple secure payment methods including Paystack (accepts all major debit/credit cards), bank transfers, and mobile money options. All payments are processed securely with 256-bit SSL encryption to protect your financial information. We also support international payments for global customers."}},
            {"@type": "Question", "name": "Is there a refund guarantee or money-back policy?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! We offer a 30-day money-back guarantee on all purchases. If you're not satisfied with your template or tool for any reason, contact our support team for a full refund—no questions asked. This gives you complete peace of mind when purchasing from us."}},
            {"@type": "Question", "name": "Is technical support included with my purchase?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! All purchases include free technical support via email. Premium customers receive priority support and can reach us via WhatsApp or live chat for faster assistance. Our support team is available to help you troubleshoot issues, answer questions, and ensure your success."}},
            {"@type": "Question", "name": "Do you provide setup and installation help?", "acceptedAnswer": {"@type": "Answer", "text": "Professional template purchases include free setup assistance. Our team can help you configure your domain, set up hosting, customize your website, and get everything online. We aim to have you launched within 24 hours. Premium customers receive hands-on setup support from our experts."}},
            {"@type": "Question", "name": "Can I use the templates for multiple websites?", "acceptedAnswer": {"@type": "Answer", "text": "Each template purchase grants a license for one website (personal or business use). If you need to use a template for multiple sites, please contact us to discuss multi-site licensing options. We offer flexible licensing plans for businesses and agencies."}},
            {"@type": "Question", "name": "Are updates and improvements included?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! All template and tool purchases include free lifetime updates. You'll automatically receive new features, design improvements, security patches, and compatibility updates at no additional cost. This ensures your website stays modern and secure for years to come."}},
            {"@type": "Question", "name": "What technical skills do I need to use your templates?", "acceptedAnswer": {"@type": "Answer", "text": "No technical skills are required! Our templates are designed for beginners with drag-and-drop editors and intuitive interfaces. However, if you have coding knowledge, you can dive deeper and customize advanced aspects. Our support team can guide you through any technical questions."}},
            {"@type": "Question", "name": "Which hosting provider do I need to use?", "acceptedAnswer": {"@type": "Answer", "text": "Our templates are compatible with any major web hosting provider including Bluehost, SiteGround, Hostinger, Namecheap, and many others. We recommend hosts that offer good performance and support. We can recommend providers and help with setup if needed."}},
            {"@type": "Question", "name": "Can I sell products or services using these templates?", "acceptedAnswer": {"@type": "Answer", "text": "Absolutely! Our templates are perfect for e-commerce, service businesses, digital products, and more. Many include built-in payment gateways, shopping carts, and product management features. You can start selling online immediately with our business-ready templates."}},
            {"@type": "Question", "name": "Are the templates SEO-friendly for search engines?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! All our templates are optimized for search engines (SEO). They include proper heading structure, meta tags, fast loading speeds, mobile responsiveness, and schema markup. We follow Google's best practices to help your website rank well in search results."}},
            {"@type": "Question", "name": "Can I integrate third-party tools and plugins?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! Our templates support integration with popular tools like email marketing platforms (Mailchimp, ConvertKit), payment processors (Stripe, PayPal), analytics (Google Analytics), social media, and thousands of other plugins. You have flexibility to extend functionality as needed."}}
        ]
    }
    </script>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="apple-touch-icon" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="/assets/alpine.csp.min.js"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        fontFamily: {
                            sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'Roboto', 'sans-serif'],
                            display: ['Plus Jakarta Sans', 'Inter', 'sans-serif'],
                        },
                        colors: {
                            primary: { 50: '#FDF9ED', 100: '#FAF0D4', 200: '#F5E1A8', 300: '#EFCF72', 400: '#E8BB45', 500: '#D4AF37', 600: '#B8942E', 700: '#9A7B26', 800: '#7D6320', 900: '#604B18' },
                            gold: { DEFAULT: '#D4AF37', 50: '#FDF9ED', 100: '#FAF0D4', 200: '#F5E1A8', 300: '#EFCF72', 400: '#E8BB45', 500: '#D4AF37', 600: '#B8942E', 700: '#9A7B26', 800: '#7D6320', 900: '#604B18' },
                            navy: { DEFAULT: '#0f172a', dark: '#0a1929', light: '#1e293b' }
                        }
                    }
                }
            }
        }
        document.documentElement.classList.add('dark');
    </script>
</head>
<body class="bg-navy dark:bg-slate-900">
    <?php include 'includes/layout/header.php'; ?>
    
    <section class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-16 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">Frequently Asked Questions</h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">Everything you need to know about WebDaddy Empire templates, digital tools, pricing, support, and launching your online presence in 24 hours.</p>
        </div>
    </section>
    
    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <!-- Quick Navigation -->
        <div class="mb-12 bg-gray-800/30 border border-gray-700 rounded-lg p-6">
            <h2 class="text-xl font-bold text-white mb-4">Browse by Category</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                <a href="#getting-started" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Getting Started</a>
                <a href="#purchasing" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Purchasing & Payment</a>
                <a href="#customization" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Customization</a>
                <a href="#support" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Support & Setup</a>
                <a href="#technical" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Technical</a>
                <a href="#licensing" class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Licensing</a>
            </div>
        </div>

        <div class="space-y-4">
            <!-- Getting Started Section -->
            <div id="getting-started" class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-4">Getting Started</h2>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="faqItem">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">How do I get started with WebDaddy Empire?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3">Getting started is simple and takes just a few minutes:</p>
                        <ol class="list-decimal pl-5 space-y-2 text-sm">
                            <li>Browse our collection of website templates or digital tools</li>
                            <li>Click "Add to Cart" on your chosen item</li>
                            <li>Review your cart and proceed to checkout</li>
                            <li>Select your payment method (Paystack, bank transfer, or mobile money)</li>
                            <li>Complete payment securely</li>
                            <li>Receive instant download links via email</li>
                            <li>Download and start building your website immediately</li>
                        </ol>
                        <p class="mt-3 pt-3 border-t border-gray-700">Most users are launching their websites within 24 hours of purchase!</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">How long does it take to receive my purchase?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p><strong class="text-primary-400">Instant delivery!</strong> After your payment is confirmed, you receive your download links immediately via email (usually within 5-10 minutes). There are no delays—you can start using your templates right away. This allows you to launch your website in as little as 24 hours.</p>
                    </div>
                </div>
            </div>

            <!-- Purchasing Section -->
            <div id="purchasing" class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-4">Purchasing & Payment</h2>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="faqItem">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">What payment methods do you accept?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3">We accept multiple secure payment methods:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li><strong>Paystack</strong> - Accepts all major credit/debit cards (Visa, Mastercard, Verve), bank transfers, and USSD</li>
                            <li><strong>Bank Transfer</strong> - Direct account-to-account transfers (₦5,000 minimum)</li>
                            <li><strong>Mobile Money</strong> - Available in select regions</li>
                            <li><strong>International Cards</strong> - Visa and Mastercard from outside Nigeria</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">All payments are encrypted with 256-bit SSL security and processed through trusted payment processors.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Is there a refund guarantee or money-back guarantee?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! 30-day money-back guarantee.</strong> We're confident in the quality of our templates and tools, so we offer a full 30-day refund guarantee on all purchases.</p>
                        <p class="mb-3"><strong>Here's how it works:</strong></p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Purchase any template or tool with confidence</li>
                            <li>If you're not satisfied for any reason within 30 days, contact us</li>
                            <li>We'll process a full refund—no questions asked</li>
                            <li>No complicated return process</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">This guarantee gives you complete peace of mind when purchasing from us. We're here to ensure your success!</p>
                    </div>
                </div>
            </div>

            <!-- Customization Section -->
            <div id="customization" class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-4">Customization & Features</h2>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="faqItem">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Can I customize the website templates?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Absolutely! All templates are 100% customizable.</strong></p>
                        <p class="mb-3"><strong>You can modify:</strong></p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Colors, fonts, and typography</li>
                            <li>Text, images, and content</li>
                            <li>Layout and page structure</li>
                            <li>Sections and components</li>
                            <li>Forms and contact options</li>
                            <li>Product listings and pricing</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700"><strong>No coding required!</strong> Our templates use drag-and-drop editors that anyone can use. If you have coding knowledge, you can dive deeper and modify the code as well.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Do the templates come with a domain name?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong>It depends on which template you choose:</strong></p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li><strong>Premium templates</strong> - Include a premium domain (usually a .com or .ng)</li>
                            <li><strong>Basic templates</strong> - Don't include a domain, but you can purchase one separately from our marketplace at affordable rates</li>
                            <li><strong>Your own domain</strong> - You can use any domain you already own with our templates</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">Our support team can help you configure your domain and set up hosting. Everything is included in our 24-hour setup service.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Can I sell products or services using these templates?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! Perfect for e-commerce and service businesses.</strong></p>
                        <p class="mb-3">Our templates support:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Product catalogs and shopping carts</li>
                            <li>Payment processing (Paystack, Stripe, PayPal)</li>
                            <li>Digital product delivery</li>
                            <li>Service booking and scheduling</li>
                            <li>Subscription management</li>
                            <li>Customer accounts and order history</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">Start selling online immediately! Many of our clients are using our templates to generate revenue within days of launch.</p>
                    </div>
                </div>
            </div>

            <!-- Support Section -->
            <div id="support" class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-4">Support & Setup</h2>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="faqItem">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Is technical support included with my purchase?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! Comprehensive support is included.</strong></p>
                        <p class="mb-3"><strong>All purchases include:</strong></p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li><strong>Email support</strong> - Free with all purchases</li>
                            <li><strong>Priority support</strong> - Premium customers get faster responses</li>
                            <li><strong>WhatsApp support</strong> - Direct messaging for quick answers</li>
                            <li><strong>Live chat</strong> - Available during business hours</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">Our support team is ready to help you troubleshoot issues, answer questions, and ensure your website success!</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Do you provide setup and installation help?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! We offer comprehensive setup assistance.</strong></p>
                        <p class="mb-3">Professional template purchases include:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Domain configuration and setup</li>
                            <li>Hosting selection and installation</li>
                            <li>Template installation and customization</li>
                            <li>Content import and organization</li>
                            <li>SEO optimization</li>
                            <li>Performance testing</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700"><strong>Our goal is to have you live within 24 hours!</strong> Our team handles the technical work so you can focus on your business.</p>
                    </div>
                </div>
            </div>

            <!-- Technical Section -->
            <div id="technical" class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-4">Technical Questions</h2>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="faqItem">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">What technical skills do I need?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p><strong class="text-primary-400">No technical skills required!</strong> Our templates are designed for beginners with intuitive drag-and-drop interfaces. If you can use a word processor, you can use our templates. However, if you have coding knowledge, you can customize at a deeper level.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Which hosting provider do I need to use?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3">Our templates are compatible with any major web hosting provider, including:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Bluehost</li>
                            <li>SiteGround</li>
                            <li>Hostinger</li>
                            <li>Namecheap</li>
                            <li>A2 Hosting</li>
                            <li>HostGator</li>
                            <li>And many others...</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">We recommend hosts with good uptime, support, and performance. Our team can recommend providers and help with setup if needed.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Are the templates SEO-friendly for search engines?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! All templates are SEO-optimized.</strong></p>
                        <p class="mb-3">Built-in SEO features:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Proper heading structure (H1, H2, H3, etc.)</li>
                            <li>Meta tags and descriptions</li>
                            <li>Optimized page load speed</li>
                            <li>Mobile-responsive design (Google's top ranking factor)</li>
                            <li>Schema markup for rich snippets</li>
                            <li>Sitemap generation</li>
                            <li>SEO-friendly URLs</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">We follow Google's best practices to help your website rank well in search results. Our support team can provide additional SEO optimization tips.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Can I integrate third-party tools and plugins?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! Full integration support.</strong></p>
                        <p class="mb-3">Our templates easily integrate with:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>Email marketing (Mailchimp, ConvertKit, ActiveCampaign)</li>
                            <li>Payment processors (Stripe, PayPal, Paystack)</li>
                            <li>Analytics (Google Analytics, Hotjar)</li>
                            <li>Social media platforms</li>
                            <li>CRM systems</li>
                            <li>And thousands of other plugins and tools</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">You have flexibility to extend functionality as your business grows. Our team can help with complex integrations.</p>
                    </div>
                </div>
            </div>

            <!-- Licensing Section -->
            <div id="licensing" class="mb-8">
                <h2 class="text-2xl font-bold text-white mb-4">Licensing & Updates</h2>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="faqItem">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Can I use the templates for multiple websites?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3">Standard license: <strong>One template purchase = One website</strong></p>
                        <p class="mb-3">However, we offer flexible options:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li><strong>Single license</strong> - Use on one website (personal or business)</li>
                            <li><strong>Multi-site license</strong> - Contact us for custom licensing for agencies or businesses needing multiple sites</li>
                            <li><strong>Reseller license</strong> - Available for developers and agencies</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">Contact our sales team to discuss your specific needs. We have flexible options for different business sizes.</p>
                    </div>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden mt-4" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                        <h3 class="text-lg font-semibold text-white">Are updates and new features included?</h3>
                        <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                    </button>
                    <div x-show="open" class="px-6 pb-4 text-gray-300">
                        <p class="mb-3"><strong class="text-primary-400">Yes! Free updates for life!</strong></p>
                        <p class="mb-3">All template and tool purchases include:</p>
                        <ul class="list-disc pl-5 space-y-2 text-sm">
                            <li>New feature additions</li>
                            <li>Design improvements</li>
                            <li>Security patches and updates</li>
                            <li>Compatibility updates (for new browser versions, etc.)</li>
                            <li>Performance optimizations</li>
                        </ul>
                        <p class="mt-3 pt-3 border-t border-gray-700">We continuously improve our templates to keep them modern, secure, and competitive. You'll benefit from all improvements at no additional cost!</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- CTA Section -->
        <section class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12 mt-16">
            <div class="max-w-3xl mx-auto">
                <h2 class="text-3xl font-bold text-navy mb-4 text-center">Didn't find your answer?</h2>
                <p class="text-navy/90 mb-8 text-center">Have a specific question? Our support team is standing by to help you get started. We're available via email, WhatsApp, and live chat.</p>
                <div class="grid sm:grid-cols-3 gap-4">
                    <a href="/contact.php" class="block text-center px-6 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">Contact Us</a>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER) ?>" target="_blank" class="block text-center px-6 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">WhatsApp Support</a>
                    <a href="/?view=templates" class="block text-center px-6 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">Browse Templates</a>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
    <script src="/assets/js/cart-and-tools.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/loader-controller.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/nav-smartness.js"></script>
    <script>
        // Alpine.js CSP FAQ Accordion Component
        document.addEventListener('alpine:init', () => {
            Alpine.data('faqItem', () => ({
                open: false
            }));
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            setupCartDrawer();
            updateCartBadge();
            setInterval(updateCartBadge, 5000);
        });
    </script>
</body>
</html>
