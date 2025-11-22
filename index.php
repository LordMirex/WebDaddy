<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/cart.php';

// Set cache headers for static content - works with .htaccess on main server
header('Cache-Control: public, max-age=31536000, immutable', false);

startSecureSession();
handleAffiliateTracking();

// Determine current view: 'templates' or 'tools'
$currentView = $_GET['view'] ?? 'templates';
$currentView = in_array($currentView, ['templates', 'tools']) ? $currentView : 'templates';

// Track page visit
trackPageVisit($_SERVER['REQUEST_URI'], 'Home - ' . ucfirst($currentView));

// Get database connection
$db = getDb();

// Get affiliate code
$affiliateCode = getAffiliateCode();

// Get cart count for badge
$cartCount = getCartCount();

// Get total active templates and tools (unfiltered by category)
$totalActiveTemplates = count(getTemplates(true));
$totalActiveTools = getToolsCount(true, null, true);

// Initialize variables
$templates = [];
$tools = [];
$totalTemplates = 0;
$totalTools = 0;
$totalPages = 1;
$page = max(1, (int)($_GET['page'] ?? 1));

// Auto-open tool from share link
$autoOpenTool = null;
$autoOpenToolSlug = isset($_GET['tool']) ? sanitizeInput($_GET['tool']) : null;
if ($autoOpenToolSlug) {
    $autoOpenTool = getToolBySlug($autoOpenToolSlug);
    if ($autoOpenTool) {
        $currentView = 'tools'; // Force tools view if opening a tool from share link
        // Track tool view when opened from share link
        trackToolView($autoOpenTool['id']);
    }
}

if ($currentView === 'templates') {
    // TEMPLATES VIEW
    $perPage = 18;
    $allTemplates = getTemplates(true);
    $templateCategories = array_unique(array_column($allTemplates, 'category'));
    sort($templateCategories);
    
    if ($category = $_GET['category'] ?? null) {
        $allTemplates = array_filter($allTemplates, function($t) use ($category) {
            return $t['category'] === $category;
        });
    }
    
    // Sort by priority first (null=999), then by newest date
    usort($allTemplates, function($a, $b) {
        $aPriority = ($a['priority_order'] !== null && $a['priority_order'] !== '') ? intval($a['priority_order']) : 999;
        $bPriority = ($b['priority_order'] !== null && $b['priority_order'] !== '') ? intval($b['priority_order']) : 999;
        if ($aPriority != $bPriority) {
            return $aPriority <=> $bPriority;
        }
        $aDate = strtotime($a['created_at'] ?? '0');
        $bDate = strtotime($b['created_at'] ?? '0');
        return $bDate <=> $aDate;
    });
    
    $totalTemplates = count($allTemplates);
    $totalPages = max(1, ceil($totalTemplates / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $templates = array_slice($allTemplates, $offset, $perPage);
} else {
    // TOOLS VIEW
    $perPage = 18;
    $category = $_GET['category'] ?? null;
    
    // Get all tools first to sort by priority
    $db = getDb();
    $allTools = getTools(true, $category, null, null, true);
    
    // Sort by priority first (null=999), then by newest date
    usort($allTools, function($a, $b) {
        $aPriority = ($a['priority_order'] !== null && $a['priority_order'] !== '') ? intval($a['priority_order']) : 999;
        $bPriority = ($b['priority_order'] !== null && $b['priority_order'] !== '') ? intval($b['priority_order']) : 999;
        if ($aPriority != $bPriority) {
            return $aPriority <=> $bPriority;
        }
        $aDate = strtotime($a['created_at'] ?? '0');
        $bDate = strtotime($b['created_at'] ?? '0');
        return $bDate <=> $aDate;
    });
    
    $totalTools = count($allTools);
    $totalPages = max(1, ceil($totalTools / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    $tools = array_slice($allTools, $offset, $perPage);
    $toolCategories = getToolCategories();
}

// Prepare meta tags - use tool data if sharing a tool, otherwise use default homepage
$pageTitle = SITE_NAME . ' - Professional Website Templates & Digital Working Tools';
$pageDescription = 'Get professional website templates and digital working tools for your business. API keys, software licenses, automation tools, and custom web templates. Launch your website in 24 hours with domain included.';
$pageKeywords = 'website templates, digital tools, API keys, business software, working tools, automation software, web templates, software licenses, ChatGPT API, digital products, online tools';
$pageUrl = SITE_URL;
$pageImage = SITE_URL . '/assets/images/og-image.png';
$ogType = 'website';

if ($autoOpenTool) {
    $pageTitle = htmlspecialchars($autoOpenTool['name']) . ' - ' . SITE_NAME;
    $pageDescription = htmlspecialchars($autoOpenTool['short_description'] ?? $autoOpenTool['description']);
    $pageKeywords = !empty($autoOpenTool['seo_keywords']) ? htmlspecialchars($autoOpenTool['seo_keywords']) : (htmlspecialchars($autoOpenTool['category'] ?? 'digital tool') . ', ' . htmlspecialchars($autoOpenTool['tool_type'] ?? 'working tool') . ', ' . htmlspecialchars($autoOpenTool['name']));
    $pageUrl = SITE_URL . '/?tool=' . $autoOpenToolSlug;
    $pageImage = !empty($autoOpenTool['banner_url']) ? $autoOpenTool['banner_url'] : (!empty($autoOpenTool['thumbnail_url']) ? $autoOpenTool['thumbnail_url'] : SITE_URL . '/assets/images/og-image.png');
    if (!empty($pageImage) && strpos($pageImage, 'http') !== 0) {
        $pageImage = SITE_URL . $pageImage;
    }
    $ogType = 'product';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo $pageTitle; ?></title>
    <meta name="description" content="<?php echo $pageDescription; ?>">
    <meta name="keywords" content="<?php echo $pageKeywords; ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?php echo $pageUrl; ?>">
    
    <!-- Open Graph / Social Media Meta Tags -->
    <meta property="og:type" content="<?php echo $ogType; ?>">
    <meta property="og:title" content="<?php echo $pageTitle; ?>">
    <meta property="og:description" content="<?php echo $pageDescription; ?>">
    <meta property="og:url" content="<?php echo $pageUrl; ?>">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:image" content="<?php echo $pageImage; ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">
    <meta property="og:locale" content="en_NG">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $pageTitle; ?>">
    <meta name="twitter:description" content="<?php echo $pageDescription; ?>">
    <meta name="twitter:image" content="<?php echo $pageImage; ?>">
    <meta name="twitter:image:alt" content="<?php echo SITE_NAME; ?> - Professional Templates and Digital Tools">
    
    <!-- Structured Data (Schema.org) for Organization -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "name": "<?php echo SITE_NAME; ?>",
      "url": "<?php echo SITE_URL; ?>",
      "logo": "<?php echo SITE_URL; ?>/assets/images/webdaddy-logo.png",
      "description": "Professional website templates and digital working tools marketplace",
      "sameAs": [],
      "contactPoint": {
        "@type": "ContactPoint",
        "contactType": "Customer Service",
        "availableLanguage": "English"
      }
    }
    </script>
    
    <!-- Structured Data (Schema.org) for WebSite -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "<?php echo SITE_NAME; ?>",
      "url": "<?php echo SITE_URL; ?>",
      "potentialAction": {
        "@type": "SearchAction",
        "target": {
          "@type": "EntryPoint",
          "urlTemplate": "<?php echo SITE_URL; ?>/?view=templates&search={search_term_string}"
        },
        "query-input": "required name=search_term_string"
      }
    }
    </script>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        },
                        gold: '#d4af37',
                        navy: '#0f172a'
                    }
                }
            }
        }
        }
        document.documentElement.classList.add('dark');
    </script>
    <script src="/assets/js/forms.js" defer></script>
    <script src="/assets/js/cart-and-tools.js" defer></script>
    <script src="/assets/js/lazy-load.js" defer></script>
    <script src="/assets/js/performance.js" defer></script>
    <script src="/assets/js/video-preloader.js" defer></script>
    <script src="/assets/js/video-modal.js" defer></script>
    <script src="/assets/js/share.js"></script>
    <?php if ($autoOpenTool): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                if (window.openToolModalFromShare) {
                    window.openToolModalFromShare(<?php echo json_encode($autoOpenTool); ?>);
                }
            }, 500);
        });
    </script>
    <?php endif; ?>
</head>
<body class="bg-gray-50 dark:bg-gray-900">
    <!-- Navigation -->
    <nav id="mainNav" class="bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-14 mr-3" onerror="this.style.display='none'">
                        <span class="text-xl font-bold text-primary-900 dark:text-white"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors">Templates</a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors">Tools</a>
                    <a href="#faq" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors">FAQ</a>
                    <!-- Cart Badge -->
                    <a href="#" id="cart-button" onclick="toggleCartDrawer(); return false;" class="relative text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute top-0 right-0 bg-gray-900 text-white text-xs font-bold rounded-full h-4 w-4 flex items-center justify-center" style="font-size: 10px;"><?php echo $cartCount; ?></span>
                    </a>
                    <a href="/affiliate/register.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors">
                        Become an Affiliate
                    </a>
                </div>
                <div class="md:hidden flex items-center">
                    <button @click="open = !open" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 focus:outline-none">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="!open">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="open" style="display: none;">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div x-show="open" @click.away="open = false" class="md:hidden bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700" style="display: none;">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium">Templates</a>
                <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium">Tools</a>
                <a href="#faq" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium">FAQ</a>
                <a href="#" id="cart-button-mobile" onclick="toggleCartDrawer(); return false;" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 font-medium flex items-center">
                    Cart
                    <span id="cart-count-mobile" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> ml-2 bg-gray-900 text-white text-xs font-bold rounded-full h-4 w-4 flex items-center justify-center" style="font-size: 10px;"><?php echo $cartCount; ?></span>
                </a>
                <a href="/affiliate/register.php" class="block px-3 py-2 rounded-md text-white bg-primary-600 hover:bg-primary-700 font-medium">Become an Affiliate</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-12 sm:py-16 lg:py-20">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6 leading-tight">Turn Your Ideas Into Reality</h1>
                <p class="text-lg sm:text-xl lg:text-2xl text-white/90 mb-4 max-w-3xl mx-auto">
                    Choose from our <span class="font-bold text-white">ready-made templates</span> or get 
                    <span class="font-bold text-white">powerful digital tools</span> to grow your business
                </p>
                <p class="text-base sm:text-lg text-white/75 mb-10">Domain included • Fast setup • Professional design</p>
                
                <!-- Trust Elements -->
                <div class="flex flex-wrap justify-center gap-3 sm:gap-4 mb-10">
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-lg px-3 sm:px-4 py-2">
                        <svg class="w-5 h-5 text-green-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                        <span class="text-xs sm:text-sm font-semibold">30-Day Money Back</span>
                    </div>
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-lg px-3 sm:px-4 py-2">
                        <svg class="w-5 h-5 text-yellow-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                        <span class="text-xs sm:text-sm font-semibold">24hr Setup</span>
                    </div>
                    <div class="flex items-center bg-white/10 backdrop-blur-sm rounded-lg px-3 sm:px-4 py-2">
                        <svg class="w-5 h-5 text-primary-300 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <span class="text-xs sm:text-sm font-semibold">24/7 Support</span>
                    </div>
                </div>
                
                <!-- CTA Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center items-center mb-10">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 border-2 border-white text-base font-semibold rounded-lg text-white bg-transparent hover:bg-white hover:text-primary-900 transition-all shadow-lg">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                        Browse Templates
                    </a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="w-full sm:w-auto inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-base font-semibold rounded-lg text-primary-900 bg-white hover:bg-gray-50 transition-all shadow-xl">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        Explore Tools
                    </a>
                </div>
                
                <!-- Success Metrics -->
                <div class="grid grid-cols-3 gap-4 sm:gap-6 lg:gap-8 max-w-2xl mx-auto">
                    <div>
                        <div class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white mb-1">500+</div>
                        <div class="text-xs sm:text-sm text-white/75">Websites Launched</div>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white mb-1">98%</div>
                        <div class="text-xs sm:text-sm text-white/75">Happy Customers</div>
                    </div>
                    <div>
                        <div class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white mb-1">24hrs</div>
                        <div class="text-xs sm:text-sm text-white/75">Average Setup</div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Smart WhatsApp Button with Message Carousel -->
    <div x-data="{ 
        messages: [
            'Need a custom website?',
            'Let\'s bring your idea to life',
            '24/7 support on WhatsApp',
            'Templates from ₦150,000'
        ],
        currentIndex: 0,
        showMessage: false,
        init() {
            this.showMessage = true;
            setInterval(() => {
                this.showMessage = false;
                setTimeout(() => {
                    this.currentIndex = (this.currentIndex + 1) % this.messages.length;
                    this.showMessage = true;
                }, 8000);
            }, 40000);
        },
        getContextualMessage() {
            const page = window.location.pathname;
            if (page.includes('template')) return 'Hi! I\'m viewing a template and need help.';
            if (page.includes('order')) return 'Hi! I\'m on the order page and need assistance.';
            return 'Hi! I\'m on <?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'your website'); ?> and need help.';
        }
    }" 
    class="fixed bottom-4 left-0 z-50">
        <!-- WhatsApp Icon Button (40px) -->
        <a :href="'https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>?text=' + encodeURIComponent(getContextualMessage())" 
           target="_blank"
           class="flex items-center gap-2 bg-green-600 hover:bg-green-700 rounded-r-full shadow-lg hover:shadow-xl transition-all duration-300 pl-3 pr-3 py-2"
           aria-label="Chat on WhatsApp">
            <!-- Icon -->
            <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            
            <!-- Sliding Message -->
            <div x-show="showMessage" 
                 x-transition:enter="transition ease-out duration-[2500ms]"
                 x-transition:enter-start="opacity-0 -translate-x-8"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-[2500ms]"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 -translate-x-8"
                 class="text-white font-semibold text-sm whitespace-nowrap pr-2">
                <span x-text="messages[currentIndex]"></span>
            </div>
        </a>
    </div>

    <!-- Products Section (Templates & Tools) -->
    <section class="py-12 bg-white" id="products">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header with Tabs -->
            <div class="max-w-3xl mx-auto text-center mb-8">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">
                    <?php echo $currentView === 'tools' ? 'Digital Working Tools' : 'Choose Your Template'; ?>
                </h2>
                <p class="text-xl text-gray-600 mb-2">
                    <?php echo $currentView === 'tools' ? 'Get powerful digital tools to grow your business' : 'Pick a professionally designed website and get started instantly'; ?>
                </p>
                <div class="flex justify-center gap-8 text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-semibold"><span class="text-primary-600"><?php echo $totalActiveTemplates; ?></span> Active Templates</span>
                    <span class="font-semibold"><span class="text-primary-600"><?php echo $totalActiveTools; ?></span> Active Tools</span>
                </div>
            </div>

            <!-- View Toggle Tabs -->
            <div class="flex justify-center mb-8">
                <div class="inline-flex rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-1 shadow-sm" role="group">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="px-4 sm:px-8 py-2.5 text-sm sm:text-base font-medium rounded-md transition-all whitespace-nowrap <?php echo $currentView === 'templates' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-50'; ?>">
                        <svg class="w-4 h-4 inline mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                        Websites
                    </a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="px-4 sm:px-8 py-2.5 text-sm sm:text-base font-medium rounded-md transition-all whitespace-nowrap <?php echo $currentView === 'tools' ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-700 hover:bg-gray-50'; ?>">
                        <svg class="w-4 h-4 inline mr-2 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        Working Tools
                    </a>
                </div>
            </div>

            <!-- Unified Search Interface -->
            <div class="mb-4 sm:mb-8">
                <div class="max-w-4xl mx-auto">
                    <div class="relative">
                        <input type="text" 
                               id="search-input"
                               placeholder="Search <?php echo $currentView === 'templates' ? 'templates' : 'tools'; ?>..." 
                               class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all">
                        <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <button id="clear-search" style="display: none;" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors z-10"
                                title="Clear search">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                        <div id="search-loading" style="display: none;" class="absolute right-10 top-1/2 -translate-y-1/2">
                            <svg class="animate-spin h-5 w-5 text-primary-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div id="products-content-area">
            <?php if ($currentView === 'templates'): ?>
            <?php if (!empty($templateCategories)): ?>
            <!-- Category Filter for Templates -->
            <div class="mb-3 sm:mb-6 max-w-4xl mx-auto">
                <div class="relative">
                    <select id="templates-category-filter" 
                            class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all appearance-none bg-white text-gray-900 font-medium cursor-pointer">
                        <option value="" <?php echo !isset($_GET['category']) ? 'selected' : ''; ?>>
                            All Categories
                        </option>
                        <?php foreach ($templateCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($templates)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h4 class="text-xl font-bold text-gray-900 mb-2">No templates available</h4>
                <p class="text-gray-600 mb-0">Please check back later or <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="font-semibold text-primary-600 hover:text-primary-700">contact us on WhatsApp</a>.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6" data-templates-grid>
                <?php foreach ($templates as $template): ?>
                <div class="group" 
                     data-template
                     data-template-name="<?php echo htmlspecialchars($template['name']); ?>"
                     data-template-category="<?php echo htmlspecialchars($template['category']); ?>"
                     data-template-price="<?php echo htmlspecialchars($template['price']); ?>">
                    <div class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1">
                        <div class="relative overflow-hidden h-48 bg-gray-100">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                                 alt="<?php echo htmlspecialchars($template['name']); ?>"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <?php 
                            $hasDemo = !empty($template['demo_url']) || !empty($template['demo_video_url']);
                            if ($hasDemo):
                                $demoUrl = !empty($template['demo_video_url']) ? $template['demo_video_url'] : $template['demo_url'];
                                $hasVideoExtension = preg_match('/\.(mp4|webm|mov|avi)$/i', $demoUrl);
                                $isVideo = $hasVideoExtension;
                            ?>
                            <?php if ($isVideo): ?>
                            <button onclick="event.stopPropagation(); openVideoModal('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    class="absolute top-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded shadow-lg transition-colors z-10">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                Video
                            </button>
                            <?php else: ?>
                            <button onclick="event.stopPropagation(); openDemoFullscreen('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    class="absolute top-2 right-2 px-3 py-1 bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold rounded shadow-lg transition-colors z-10">
                                Preview
                            </button>
                            <?php endif; ?>
                            <?php if ($isVideo): ?>
                            <button onclick="openVideoModal('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    data-video-trigger
                                    data-video-url="<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>"
                                    data-video-title="<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="inline-flex items-center px-4 py-2 bg-white text-gray-900 rounded-lg font-medium shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Watch Demo
                                </span>
                            </button>
                            <?php else: ?>
                            <button onclick="openDemoFullscreen('<?php echo htmlspecialchars($demoUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')" 
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="inline-flex items-center px-4 py-2 bg-white text-gray-900 rounded-lg font-medium shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Click to Preview
                                </span>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-base font-bold text-gray-900 flex-1 pr-2"><?php echo htmlspecialchars($template['name']); ?></h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 shrink-0">
                                    <?php echo htmlspecialchars($template['category']); ?>
                                </span>
                            </div>
                            <p class="text-gray-600 text-xs mb-3 line-clamp-2"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                            <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                                <div class="flex flex-col">
                                    <span class="text-xs text-gray-500 uppercase tracking-wide">Price</span>
                                    <span class="text-base font-bold text-primary-600"><?php echo formatCurrency($template['price']); ?></span>
                                </div>
                                <div class="flex gap-2">
                                    <a href="<?php echo getTemplateUrl($template, $affiliateCode); ?>" 
                                       class="inline-flex items-center justify-center px-3 py-1.5 border border-gray-300 text-xs font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors whitespace-nowrap">
                                        Details
                                    </a>
                                    <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '', this)" 
                                       class="inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                                        <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-12 flex flex-col items-center gap-4">
                <nav class="flex items-center gap-2">
                    <?php
                    $paginationParams = ['view' => $currentView];
                    if ($affiliateCode) $paginationParams['aff'] = $affiliateCode;
                    if (isset($_GET['category'])) $paginationParams['category'] = $_GET['category'];
                    ?>
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page - 1])); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $i])); ?>#products" 
                       class="<?php echo $i === $page ? 'bg-primary-600 text-white border-primary-600 shadow-lg scale-110' : 'bg-white text-gray-700 border-gray-300 hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600'; ?> inline-flex items-center justify-center w-10 h-10 text-sm font-bold border-2 rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page + 1])); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm font-medium text-gray-600">
                    Page <span class="text-primary-600 font-bold"><?php echo $page; ?></span> of <?php echo $totalPages; ?> <span class="text-gray-400 mx-1">•</span> <?php echo $totalTemplates; ?> templates
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- TOOLS VIEW -->
            <?php if (!empty($toolCategories)): ?>
            <!-- Category Filter -->
            <div class="mb-6 max-w-4xl mx-auto">
                <div class="relative">
                    <select id="tools-category-filter" 
                            class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all appearance-none bg-white text-gray-900 font-medium cursor-pointer">
                        <option value="" <?php echo !isset($_GET['category']) ? 'selected' : ''; ?>>
                            All Categories
                        </option>
                        <?php foreach ($toolCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                    <svg class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($tools)): ?>
            <div class="bg-blue-50 border border-blue-200 rounded-2xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h4 class="text-xl font-bold text-gray-900 mb-2">No tools available</h4>
                <p class="text-gray-600 mb-0">Please check back later or <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="font-semibold text-primary-600 hover:text-primary-700">contact us on WhatsApp</a>.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($tools as $tool): ?>
                <div class="tool-card group bg-white rounded-xl shadow-md overflow-hidden border border-gray-200 transition-all duration-300 hover:shadow-xl hover:-translate-y-1" 
                     data-tool-id="<?php echo $tool['id']; ?>">
                    <div class="relative overflow-hidden h-40 bg-gray-100">
                        <img src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                             alt="<?php echo htmlspecialchars($tool['name']); ?>"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                             onerror="this.src='/assets/images/placeholder.jpg'">
                        <?php if (!empty($tool['demo_video_url'])): ?>
                        <button onclick="openVideoModal('<?php echo htmlspecialchars($tool['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                                class="absolute top-2 left-2 px-3 py-1.5 bg-black/70 hover:bg-black/90 text-white text-xs font-bold rounded-full flex items-center gap-1 transition-all shadow-lg">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            Watch Video
                        </button>
                        <?php endif; ?>
                        <?php if ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= $tool['low_stock_threshold'] && $tool['stock_quantity'] > 0): ?>
                        <div class="absolute top-2 right-2 px-2 py-1 bg-yellow-500 text-white text-xs font-bold rounded">
                            Limited Stock
                        </div>
                        <?php elseif ($tool['stock_unlimited'] == 0 && $tool['stock_quantity'] <= 0): ?>
                        <div class="absolute top-2 right-2 px-2 py-1 bg-red-500 text-white text-xs font-bold rounded">
                            Out of Stock
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <div class="flex justify-between items-start mb-2">
                            <h3 class="text-sm font-bold text-gray-900 flex-1 pr-2"><?php echo htmlspecialchars($tool['name']); ?></h3>
                            <?php if (!empty($tool['category'])): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 shrink-0">
                                <?php echo htmlspecialchars($tool['category']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($tool['short_description'])): ?>
                        <p class="text-gray-600 text-xs mb-3 line-clamp-2"><?php echo htmlspecialchars($tool['short_description']); ?></p>
                        <?php endif; ?>
                        <div class="flex items-center justify-between pt-3 border-t border-gray-200">
                            <div class="flex flex-col">
                                <span class="text-xs text-gray-500 uppercase tracking-wide">Price</span>
                                <span class="text-lg font-extrabold text-primary-600"><?php echo formatCurrency($tool['price']); ?></span>
                            </div>
                            <button data-tool-id="<?php echo $tool['id']; ?>" 
                                    class="tool-preview-btn inline-flex items-center justify-center px-4 py-2 border-2 border-primary-600 text-xs font-semibold rounded-lg text-primary-600 bg-white hover:bg-primary-50 transition-all shadow-sm hover:shadow-md whitespace-nowrap">
                                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination for tools -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-12 flex flex-col items-center gap-4">
                <nav class="flex items-center gap-2">
                    <?php
                    $paginationParams = ['view' => 'tools'];
                    if ($affiliateCode) $paginationParams['aff'] = $affiliateCode;
                    if (isset($_GET['category'])) $paginationParams['category'] = $_GET['category'];
                    ?>
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page - 1])); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $i])); ?>#products" 
                       class="<?php echo $i === $page ? 'bg-primary-600 text-white border-primary-600 shadow-lg scale-110' : 'bg-white text-gray-700 border-gray-300 hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600'; ?> inline-flex items-center justify-center w-10 h-10 text-sm font-bold border-2 rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page + 1])); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm font-medium text-gray-600">
                    Page <span class="text-primary-600 font-bold"><?php echo $page; ?></span> of <?php echo $totalPages; ?> <span class="text-gray-400 mx-1">•</span> <?php echo $totalTools; ?> tools
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Infinite Carousel Section -->
    <section class="py-12 bg-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Trusted by Businesses Like Yours</h2>
                <p class="text-xl text-gray-600">See what our customers say about launching their online presence</p>
            </div>
        </div>
        
        <div class="carousel-wrapper mx-auto px-4" style="max-width: 1200px;">
            <div id="testimonialCarousel" class="carousel-container" style="display: flex; gap: 24px; overflow-x: auto; overflow-y: hidden; scroll-behavior: auto; -webkit-overflow-scrolling: touch; padding: 20px 0;">
                <!-- Items will be cloned by JavaScript for infinite scroll -->
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-white rounded-xl shadow-md p-8 border border-gray-200 h-full flex flex-col">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-6 flex-grow">"Amazing service! My restaurant website was live in 24 hours. The template looks professional and my customers love it."</p>
                        <div>
                            <div class="font-semibold text-gray-900">Adebayo Johnson</div>
                            <div class="text-sm text-gray-600">Bella's Kitchen, Lagos</div>
                        </div>
                    </div>
                </div>
                
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-white rounded-xl shadow-md p-8 border border-gray-200 h-full flex flex-col">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-6 flex-grow">"From zero to online business in one day! The setup was seamless and the support team is incredible."</p>
                        <div>
                            <div class="font-semibold text-gray-900">Ngozi Okoro</div>
                            <div class="text-sm text-gray-600">Fashion Boutique, Abuja</div>
                        </div>
                    </div>
                </div>
                
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-white rounded-xl shadow-md p-8 border border-gray-200 h-full flex flex-col">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-6 flex-grow">"Professional, fast, and affordable. My law firm website attracts new clients every week. Highly recommended!"</p>
                        <div>
                            <div class="font-semibold text-gray-900">Barrister Emeka</div>
                            <div class="text-sm text-gray-600">Legal Services, Port Harcourt</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            #testimonialCarousel { scroll-behavior: smooth; }
            .carousel-item { flex-shrink: 0; }
            
            @media (max-width: 768px) {
                .carousel-item { flex: 0 0 calc(100% - 16px) !important; }
            }
            @media (min-width: 769px) and (max-width: 1024px) {
                .carousel-item { flex: 0 0 calc(50% - 12px) !important; }
            }
            @media (min-width: 1025px) {
                .carousel-item { flex: 0 0 calc(33.333% - 16px) !important; }
            }
        </style>

        <script>
            (function() {
                const carousel = document.getElementById('testimonialCarousel');
                const originals = Array.from(carousel.querySelectorAll('.original-item'));
                
                // Clone the original set only once for infinite scroll
                originals.forEach(item => {
                    carousel.appendChild(item.cloneNode(true));
                });
                
                let isDown = false, startX = 0, scrollLeft = 0;
                let autoScrollId;
                const itemWidth = originals[0].offsetWidth + 24; // width + gap
                const originalSetWidth = itemWidth * originals.length;
                
                function startAutoScroll() {
                    clearInterval(autoScrollId);
                    let speed = 0.8; // pixels per frame - very smooth speed
                    
                    autoScrollId = setInterval(() => {
                        carousel.scrollLeft += speed;
                        
                        // Seamless infinite loop - reset when reaching the cloned set end
                        if (carousel.scrollLeft >= originalSetWidth) {
                            carousel.scrollLeft = 0;
                        }
                    }, 30);
                }
                
                // Start auto-scroll after a brief delay to ensure DOM is ready
                setTimeout(startAutoScroll, 100);
                
                // Pause on mouse down (drag)
                carousel.addEventListener('mousedown', (e) => {
                    isDown = true;
                    startX = e.pageX - carousel.offsetLeft;
                    scrollLeft = carousel.scrollLeft;
                    clearInterval(autoScrollId);
                });
                
                carousel.addEventListener('mouseleave', () => { isDown = false; });
                carousel.addEventListener('mouseup', () => { 
                    isDown = false;
                    startAutoScroll();
                });
                
                carousel.addEventListener('mousemove', (e) => {
                    if (!isDown) return;
                    e.preventDefault();
                    const x = e.pageX - carousel.offsetLeft;
                    const walk = (x - startX);
                    carousel.scrollLeft = scrollLeft - walk;
                });
                
                // Touch support
                carousel.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].pageX - carousel.offsetLeft;
                    scrollLeft = carousel.scrollLeft;
                    clearInterval(autoScrollId);
                }, false);
                
                carousel.addEventListener('touchmove', (e) => {
                    const x = e.touches[0].pageX - carousel.offsetLeft;
                    const walk = (x - startX);
                    carousel.scrollLeft = scrollLeft - walk;
                }, false);
                
                carousel.addEventListener('touchend', () => {
                    startAutoScroll();
                }, false);
            })();
        </script>
    </section>

    <!-- FAQ Section -->
    <section class="py-12 bg-gray-100" id="faq">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Frequently Asked Questions</h2>
                <p class="text-xl text-gray-600">Everything you need to know</p>
            </div>
            <div class="space-y-4" x-data="{ selected: 1 }">
                <div class="bg-white rounded-lg shadow-md border border-gray-200">
                    <button @click="selected = selected === 1 ? null : 1" class="w-full text-left px-6 py-4 font-semibold text-gray-900 flex justify-between items-center hover:bg-gray-50 transition-colors">
                        <span>What's included in the price?</span>
                        <svg class="w-5 h-5 transform transition-transform" :class="selected === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 1" x-collapse class="px-6 pb-4 text-gray-600">
                        Complete website template, premium domain name, hosting setup, and full customization access. You get everything needed to launch your business online.
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md border border-gray-200">
                    <button @click="selected = selected === 2 ? null : 2" class="w-full text-left px-6 py-4 font-semibold text-gray-900 flex justify-between items-center hover:bg-gray-50 transition-colors">
                        <span>How long does setup take?</span>
                        <svg class="w-5 h-5 transform transition-transform" :class="selected === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 2" x-collapse class="px-6 pb-4 text-gray-600">
                        Your website will be ready within 24 hours after payment confirmation. We handle all the technical setup so you can focus on your business.
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md border border-gray-200">
                    <button @click="selected = selected === 3 ? null : 3" class="w-full text-left px-6 py-4 font-semibold text-gray-900 flex justify-between items-center hover:bg-gray-50 transition-colors">
                        <span>How do I get support?</span>
                        <svg class="w-5 h-5 transform transition-transform" :class="selected === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 3" x-collapse class="px-6 pb-4 text-gray-600">
                        We offer 24/7 support via WhatsApp at <?php echo WHATSAPP_NUMBER; ?>. Our team is always ready to help you with any questions or issues.
                    </div>
                </div>
            </div>
        </div>
    </section>


    <!-- Footer -->
    <footer class="bg-navy text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-12">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center mb-4">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-10 mr-3" onerror="this.style.display='none'">
                        <span class="text-2xl font-bold"><?php echo SITE_NAME; ?></span>
                    </div>
                    <p class="text-gray-300 text-base mb-6 leading-relaxed">Professional website templates and digital working tools to power your business. Get custom domains, API keys, software licenses, and more. Launch in 24 hours or less.</p>
                </div>
                
                <!-- Contact Section -->
                <div>
                    <h3 class="text-xl font-bold mb-6">Get In Touch</h3>
                    <div class="space-y-4">
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" 
                           class="flex items-center p-4 bg-green-600 hover:bg-green-700 rounded-lg transition-colors group">
                            <div class="flex-shrink-0 w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-white font-semibold mb-1">WhatsApp Support</div>
                                <div class="text-green-100 text-sm">Chat with us 24/7</div>
                            </div>
                        </a>
                        
                        <a href="/affiliate/register.php" 
                           class="flex items-center p-4 bg-primary-600 hover:bg-primary-700 rounded-lg transition-colors group">
                            <div class="flex-shrink-0 w-12 h-12 bg-white/20 rounded-lg flex items-center justify-center mr-4">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                            </div>
                            <div>
                                <div class="text-white font-semibold mb-1">Become an Affiliate</div>
                                <div class="text-primary-100 text-sm">Earn 30% commission</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 pt-8">
                <div class="text-center">
                    <p class="text-gray-400 text-sm">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Navbar scroll effect
        const navbar = document.getElementById('mainNav');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('shadow-lg');
            } else {
                navbar.classList.remove('shadow-lg');
            }
        });
    </script>
</body>
</html>
