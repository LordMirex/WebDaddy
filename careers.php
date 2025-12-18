<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

header('Cache-Control: no-cache, no-store, must-revalidate', false);
startSecureSession();
handleAffiliateTracking();

$activeNav = 'careers';
$affiliateCode = getAffiliateCode();

$pageTitle = 'Careers - Join ' . SITE_NAME;
$pageDescription = 'Join our team! We\'re hiring talented individuals to help us serve African entrepreneurs.';
$pageUrl = SITE_URL . '/careers.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/premium.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        document.documentElement.classList.add('dark');
        if (typeof tailwind !== 'undefined') {
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: {
                            primary: { 50: '#FDF9ED', 500: '#D4AF37', 600: '#B8942E', 700: '#9A7B26' },
                            navy: { DEFAULT: '#0f172a', light: '#1e293b' }
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-navy">
    <?php include 'includes/layout/header.php'; ?>
    
    <section class="bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-16 sm:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6">Join Our Team</h1>
            <p class="text-lg sm:text-xl text-white/90 max-w-2xl">Help us democratize web design for entrepreneurs across Africa.</p>
        </div>
    </section>
    
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
        <section class="grid md:grid-cols-2 gap-12 mb-16">
            <div>
                <h2 class="text-3xl font-bold text-white mb-6">Why Work With Us?</h2>
                <ul class="space-y-4 text-gray-300">
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Meaningful Impact:</strong> Help thousands of African entrepreneurs build their digital presence and grow their businesses</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Growth Opportunities:</strong> Learn and grow in a fast-paced, innovative environment with mentorship from industry experts</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Remote-First Culture:</strong> Work from anywhere with flexible hours that suit your lifestyle</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Competitive Compensation:</strong> Fair salaries, performance bonuses, and incentive programs</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <span class="text-primary-400 font-bold text-xl">✓</span>
                        <span><strong class="text-white">Passionate Team:</strong> Collaborate with talented professionals who are dedicated to our mission of empowering African entrepreneurs</span>
                    </li>
                </ul>
            </div>
            
            <div>
                <h2 class="text-3xl font-bold text-white mb-6">Current Openings</h2>
                <div class="space-y-4">
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Web Developer</h3>
                        <p class="text-gray-400 text-sm mb-4">Build and maintain our web platforms. Work with modern technologies like PHP, JavaScript, and responsive design. Help create seamless experiences for our users.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Customer Care Specialist</h3>
                        <p class="text-gray-400 text-sm mb-4">Be the voice of our customers. Provide exceptional support via WhatsApp, email, and live chat. Solve problems and ensure customer satisfaction at the highest level.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Content Creator</h3>
                        <p class="text-gray-400 text-sm mb-4">Create compelling blog posts, case studies, tutorials, and marketing content. Tell stories that inspire and educate African entrepreneurs about digital transformation.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                    
                    <div class="bg-gray-800/50 border border-gray-700 rounded-lg p-6 hover:border-primary-500 transition-colors">
                        <h3 class="text-xl font-semibold text-white mb-2">Marketing Manager</h3>
                        <p class="text-gray-400 text-sm mb-4">Drive our marketing strategy and campaigns. Reach more African entrepreneurs, build brand awareness, and grow our community through innovative marketing initiatives.</p>
                        <button onclick="document.location='#apply'" class="text-primary-400 hover:text-primary-300 font-medium text-sm">Learn More →</button>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Application Section -->
        <section id="apply" class="bg-gradient-to-r from-primary-600 to-primary-700 rounded-lg p-8 sm:p-12 text-center">
            <h2 class="text-3xl font-bold text-navy mb-4">Interested in Joining Us?</h2>
            <p class="text-navy/90 mb-8 max-w-2xl mx-auto">Have what it takes to join our team? Send us an email with your CV and tell us which role interests you.</p>
            
            <a href="mailto:admin@webdaddy.online?subject=I'm%20interested%20in%20joining%20WebDaddy%20Empire&body=Careers%20Page:%20<?= urlencode(SITE_URL . '/careers.php') ?>" class="inline-block px-8 py-3 bg-navy hover:bg-navy/80 text-white font-semibold rounded-lg transition-colors">
                Send Your Application
            </a>
        </section>
    </main>
    
    <?php include 'includes/layout/footer.php'; ?>
    <script src="/assets/js/cart-and-tools.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/loader-controller.js?v=<?php echo time(); ?>"></script>
    <script src="/assets/js/nav-smartness.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setupCartDrawer();
            updateCartBadge();
            setInterval(updateCartBadge, 5000);
        });
    </script>
</body>
</html>
