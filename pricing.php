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

$activeNav = 'pricing';
$affiliateCode = getAffiliateCode();
$cartCount = getCartCount();

$pageTitle = 'Pricing - ' . SITE_NAME;
$pageDescription = 'Affordable website templates and digital tools. Transparent pricing with no hidden fees. Get premium templates from ₦2,000 or digital tools from ₦1,000.';
$pageUrl = SITE_URL . '/pricing.php';
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
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <script src="https://cdn.tailwindcss.com"></script>
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
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">Simple, Transparent Pricing</h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">No hidden fees. Choose what you need and launch in 24 hours.</p>
        </div>
    </section>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <!-- Templates Pricing -->
        <section class="mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-12">Website Templates</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-8 hover:border-primary-500 transition-colors">
                    <h3 class="text-2xl font-bold text-white mb-2">Basic</h3>
                    <p class="text-primary-400 text-4xl font-bold mb-4">₦2,000</p>
                    <p class="text-gray-300 mb-6">Perfect to get started</p>
                    <ul class="space-y-3 mb-8 text-gray-300 text-sm">
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Fully responsive design</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Customizable colors</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Email support</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Free updates for 6 months</span></li>
                    </ul>
                    <a href="/?view=templates" class="block w-full text-center px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">View Templates</a>
                </div>
                
                <div class="bg-gray-800/50 border-2 border-primary-500 rounded-lg p-8 relative transform scale-105">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2 bg-primary-500 text-navy px-4 py-1 rounded-full text-sm font-bold">POPULAR</div>
                    <h3 class="text-2xl font-bold text-white mb-2">Professional</h3>
                    <p class="text-primary-400 text-4xl font-bold mb-4">₦5,000</p>
                    <p class="text-gray-300 mb-6">For serious entrepreneurs</p>
                    <ul class="space-y-3 mb-8 text-gray-300 text-sm">
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Everything in Basic</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Premium domain included</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Priority support</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Free setup assistance</span></li>
                    </ul>
                    <a href="/?view=templates" class="block w-full text-center px-4 py-2.5 bg-primary-500 hover:bg-primary-600 text-navy font-semibold rounded-lg transition-colors">View Templates</a>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-8 hover:border-primary-500 transition-colors">
                    <h3 class="text-2xl font-bold text-white mb-2">Enterprise</h3>
                    <p class="text-primary-400 text-4xl font-bold mb-4">Custom</p>
                    <p class="text-gray-300 mb-6">For large-scale projects</p>
                    <ul class="space-y-3 mb-8 text-gray-300 text-sm">
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Custom template design</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Unlimited revisions</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Dedicated account manager</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>24/7 phone support</span></li>
                    </ul>
                    <a href="/contact.php" class="block w-full text-center px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Get Quote</a>
                </div>
            </div>
        </section>
        
        <!-- Tools Pricing -->
        <section class="mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-12">Digital Tools</h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-8 hover:border-primary-500 transition-colors">
                    <h3 class="text-2xl font-bold text-white mb-2">Individual Tools</h3>
                    <p class="text-primary-400 text-3xl font-bold mb-4">₦1,000 - ₦3,000</p>
                    <p class="text-gray-300 mb-6">Pick what you need</p>
                    <ul class="space-y-3 mb-8 text-gray-300 text-sm">
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Instant download</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Lifetime access</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Free updates</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Email support</span></li>
                    </ul>
                    <a href="/?view=tools" class="block w-full text-center px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">Browse Tools</a>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-8 hover:border-primary-500 transition-colors">
                    <h3 class="text-2xl font-bold text-white mb-2">Tool Bundles</h3>
                    <p class="text-primary-400 text-3xl font-bold mb-4">₦5,000 - ₦10,000</p>
                    <p class="text-gray-300 mb-6">Save 30% on bundled tools</p>
                    <ul class="space-y-3 mb-8 text-gray-300 text-sm">
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>6-12 tools included</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Lifetime access to all</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Priority support</span></li>
                        <li class="flex items-start gap-2"><span class="text-primary-400 mt-1">✓</span> <span>Free future tool additions</span></li>
                    </ul>
                    <a href="/?view=tools" class="block w-full text-center px-4 py-2.5 bg-primary-600 hover:bg-primary-700 text-navy font-semibold rounded-lg transition-colors">View Bundles</a>
                </div>
            </div>
        </section>
        
        <!-- Money-back guarantee -->
        <section class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12 text-center mb-16">
            <h2 class="text-3xl font-bold text-navy mb-4">30-Day Money-Back Guarantee</h2>
            <p class="text-navy/90 max-w-2xl mx-auto mb-6">Not 100% satisfied? Get a full refund within 30 days, no questions asked. Your satisfaction is our top priority.</p>
            <a href="/contact.php" class="inline-flex items-center gap-2 px-8 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">Get Help</a>
        </section>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
</body>
</html>
