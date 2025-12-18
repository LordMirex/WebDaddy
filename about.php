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

$activeNav = 'about';
$affiliateCode = getAffiliateCode();
$cartCount = getCartCount();

// Meta tags
$pageTitle = 'About ' . SITE_NAME . ' - Professional Website Templates & Digital Tools';
$pageDescription = 'Learn about WebDaddy Empire, a marketplace for professional website templates, premium domains, and digital tools. Trusted by thousands of entrepreneurs to launch websites in 24 hours.';
$pageKeywords = 'about us, WebDaddy Empire, website templates, digital tools marketplace, African entrepreneurs';
$pageUrl = SITE_URL . '/about.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.jpg">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $pageDescription; ?>">
    
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "AboutPage",
        "name": "About <?php echo SITE_NAME; ?>",
        "description": "<?php echo htmlspecialchars($pageDescription); ?>",
        "url": "<?php echo $pageUrl; ?>",
        "organization": {
            "@type": "Organization",
            "name": "<?php echo SITE_NAME; ?>",
            "url": "<?php echo SITE_URL; ?>",
            "logo": "<?php echo SITE_URL; ?>/assets/images/webdaddy-logo.png"
        }
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
    
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-16 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">About <?php echo SITE_NAME; ?></h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">We're on a mission to democratize web design and digital tools for entrepreneurs across Africa.</p>
        </div>
    </section>
    
    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <!-- Our Story -->
        <section class="mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-6">Our Story</h2>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="text-gray-300 space-y-4">
                    <p>WebDaddy Empire was founded with a simple vision: to make professional web design and digital tools accessible to every entrepreneur, regardless of their technical skills or budget.</p>
                    <p>We recognized that many African entrepreneurs struggle to get online quickly and affordably. Website development can be expensive, time-consuming, and intimidating. We changed that.</p>
                    <p>Today, thousands of businesses use our templates and tools to launch their websites in 24 hours, with domains included. We're proud to be their trusted partner in growth.</p>
                </div>
                <div class="text-gray-300 space-y-4">
                    <p>Our marketplace offers premium website templates, domain names, and productivity tools - all curated to meet the highest quality standards.</p>
                    <p>Every product in our catalog has been tested and verified to ensure it meets our strict criteria for quality, performance, and reliability.</p>
                    <p>We stand behind every product with comprehensive support and a commitment to your success.</p>
                </div>
            </div>
        </section>
        
        <!-- Why Choose Us -->
        <section class="mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-12">Why Choose Us?</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                    <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Fast Launch</h3>
                    <p class="text-gray-400">Launch your website in 24 hours with our pre-designed, production-ready templates.</p>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                    <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Affordable</h3>
                    <p class="text-gray-400">Premium quality at prices that won't break your budget. Flexible payment options available.</p>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                    <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Professional Quality</h3>
                    <p class="text-gray-400">Every template is professionally designed and optimized for performance.</p>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                    <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">24/7 Support</h3>
                    <p class="text-gray-400">Get help whenever you need it. Our support team is always ready to assist.</p>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                    <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7.53-2.47a9 9 0 11-18.06 0"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">Money-Back Guarantee</h3>
                    <p class="text-gray-400">Not satisfied? Get a full refund within 30 days, no questions asked.</p>
                </div>
                
                <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                    <div class="w-12 h-12 bg-primary-500 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-navy" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-white mb-2">SEO Optimized</h3>
                    <p class="text-gray-400">All templates are built with search engine optimization best practices.</p>
                </div>
            </div>
        </section>
        
        <!-- Our Values -->
        <section class="mb-16">
            <h2 class="text-3xl sm:text-4xl font-bold text-white mb-6">Our Core Values</h2>
            <ul class="space-y-4 text-gray-300">
                <li class="flex items-start gap-4">
                    <span class="text-primary-500 font-bold text-xl mt-1">✓</span>
                    <div>
                        <h3 class="font-semibold text-white mb-1">Quality First</h3>
                        <p>We never compromise on quality. Every product is thoroughly tested and verified.</p>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-primary-500 font-bold text-xl mt-1">✓</span>
                    <div>
                        <h3 class="font-semibold text-white mb-1">Customer Success</h3>
                        <p>Your success is our success. We're committed to helping you achieve your goals.</p>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-primary-500 font-bold text-xl mt-1">✓</span>
                    <div>
                        <h3 class="font-semibold text-white mb-1">Transparency</h3>
                        <p>We believe in honest, clear communication with our customers and partners.</p>
                    </div>
                </li>
                <li class="flex items-start gap-4">
                    <span class="text-primary-500 font-bold text-xl mt-1">✓</span>
                    <div>
                        <h3 class="font-semibold text-white mb-1">Innovation</h3>
                        <p>We continuously innovate to bring you the latest tools and technologies.</p>
                    </div>
                </li>
            </ul>
        </section>
        
        <!-- CTA Section -->
        <section class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12 text-center">
            <h2 class="text-3xl sm:text-4xl font-bold text-navy mb-4">Ready to Get Started?</h2>
            <p class="text-navy/90 mb-8 max-w-2xl mx-auto">Browse our collection of professional templates and tools. Launch your website in 24 hours.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/?view=templates" class="inline-flex items-center justify-center px-8 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">
                    Browse Templates
                </a>
                <a href="/contact.php" class="inline-flex items-center justify-center px-8 py-3 bg-white/20 hover:bg-white/30 text-white font-semibold rounded-lg transition-colors">
                    Contact Us
                </a>
            </div>
        </section>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
</body>
</html>
