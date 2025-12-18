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
$cartCount = getCartCount();

$pageTitle = 'FAQ - Frequently Asked Questions - ' . SITE_NAME;
$pageDescription = 'Find answers to common questions about WebDaddy Empire templates, tools, pricing, delivery, refunds, and support.';
$pageUrl = SITE_URL . '/faq.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {"@type": "Question", "name": "How do I get started?", "acceptedAnswer": {"@type": "Answer", "text": "Browse our templates or tools, add to cart, and checkout. You'll get instant access to download."}},
            {"@type": "Question", "name": "How long does delivery take?", "acceptedAnswer": {"@type": "Answer", "text": "Instant! You receive download links immediately after payment confirmation."}},
            {"@type": "Question", "name": "Can I customize the templates?", "acceptedAnswer": {"@type": "Answer", "text": "Yes! All templates are fully editable. You can customize colors, text, and layouts to match your brand."}}
        ]
    }
    </script>
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">Find answers to your questions about our templates, tools, pricing, and support.</p>
        </div>
    </section>
    
    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <div class="space-y-4">
            <!-- Getting Started -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">How do I get started?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Browse our templates or tools, add what you need to your cart, and checkout using any supported payment method. You'll get instant access to download your files immediately after payment confirmation.
                </div>
            </div>
            
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">How long does delivery take?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    <strong>Instant!</strong> You receive your download links immediately after your payment is confirmed. No waiting, no delays.
                </div>
            </div>
            
            <!-- Customization & Use -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Can I customize the templates?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Yes! All templates are fully editable. You can customize colors, text, images, layouts, and more to match your brand perfectly. No coding knowledge required.
                </div>
            </div>
            
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Do the templates come with a domain?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Premium templates include a premium domain. Basic templates don't, but you can purchase a domain separately from our marketplace or use your own.
                </div>
            </div>
            
            <!-- Payment & Refunds -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">What payment methods do you accept?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    We accept Paystack, bank transfers, and other secure payment methods. All payments are processed securely and encrypted.
                </div>
            </div>
            
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Do you offer refunds?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Yes! We offer a <strong>30-day money-back guarantee</strong> on all purchases. If you're not satisfied for any reason, contact us for a full refund—no questions asked.
                </div>
            </div>
            
            <!-- Support & Technical -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Is technical support included?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Yes! All purchases include free technical support via email. Premium customers get priority support and can reach us via WhatsApp or live chat.
                </div>
            </div>
            
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Do you provide setup/installation help?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Yes! Professional template purchases include free setup assistance. We can help you get your website online in 24 hours. Contact us for details.
                </div>
            </div>
            
            <!-- Licensing & Usage -->
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Can I use templates for multiple sites?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Each template purchase grants a license for personal or business use. Check the specific template license for multi-site usage rights or contact us for custom licensing.
                </div>
            </div>
            
            <div class="bg-gray-800/50 border border-gray-700 rounded-lg overflow-hidden" x-data="{ open: false }">
                <button @click="open = !open" class="w-full px-6 py-4 flex items-center justify-between hover:bg-gray-800/70 transition-colors text-left">
                    <h3 class="text-lg font-semibold text-white">Are there updates included?</h3>
                    <svg class="w-5 h-5 text-primary-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                    </svg>
                </button>
                <div x-show="open" class="px-6 pb-4 text-gray-300">
                    Yes! All template and tool purchases include free updates for life. New features, improvements, and security patches are always free.
                </div>
            </div>
        </div>
        
        <!-- CTA Section -->
        <section class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12 text-center mt-16">
            <h2 class="text-3xl font-bold text-navy mb-4">Didn't find your answer?</h2>
            <p class="text-navy/90 mb-6">Our support team is ready to help. Contact us anytime.</p>
            <a href="/contact.php" class="inline-flex items-center gap-2 px-8 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">Get Help Now</a>
        </section>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
</body>
</html>
