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

// AUTO-RESTORE SAVED CART FROM PREVIOUS VISIT (if not already loaded)
if (empty(getCart())) {
    $db = getDb();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $stmt = $db->prepare('
        SELECT id, cart_snapshot FROM draft_orders 
        WHERE ip_address = ? AND created_at > datetime("now", "-7 days")
        ORDER BY created_at DESC
        LIMIT 1
    ');
    $stmt->execute([$ip]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($draft) {
        $draftData = json_decode($draft['cart_snapshot'], true);
        if (!empty($draftData['cart_items'])) {
            $_SESSION['cart'] = $draftData['cart_items'];
            if (!empty($draftData['affiliate_code'])) {
                $_SESSION['affiliate_code'] = $draftData['affiliate_code'];
            }
        }
    }
}

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
$pageImage = SITE_URL . '/assets/images/og-image.jpg';
$ogType = 'website';

if ($autoOpenTool) {
    $pageTitle = htmlspecialchars($autoOpenTool['name']) . ' - ' . SITE_NAME;
    $pageDescription = htmlspecialchars($autoOpenTool['short_description'] ?? $autoOpenTool['description']);
    $pageKeywords = !empty($autoOpenTool['seo_keywords']) ? htmlspecialchars($autoOpenTool['seo_keywords']) : (htmlspecialchars($autoOpenTool['category'] ?? 'digital tool') . ', ' . htmlspecialchars($autoOpenTool['tool_type'] ?? 'working tool') . ', ' . htmlspecialchars($autoOpenTool['name']));
    $pageUrl = SITE_URL . '/?tool=' . $autoOpenToolSlug;
    
    // Use tool-specific thumbnail if available
    $pageImage = SITE_URL . '/assets/images/og-image.jpg';
    if (!empty($autoOpenTool['thumbnail_url'])) {
        $toolImagePath = $autoOpenTool['thumbnail_url'];
        if (strpos($toolImagePath, 'http') !== 0) {
            $toolImagePath = SITE_URL . $toolImagePath;
        }
        $pageImage = $toolImagePath;
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
    <meta property="og:image" content="<?php echo $pageImage; ?>?v=<?php echo time(); ?>">
    <meta property="og:image:width" content="1500">
    <meta property="og:image:height" content="1000">
    <meta property="og:image:type" content="image/jpeg">
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
                            50: '#fdf9ef',
                            100: '#f9f0d9',
                            200: '#f2ddb0',
                            300: '#e9c67d',
                            400: '#c9a962',
                            500: '#b8923f',
                            600: '#c9a962',
                            700: '#a47a33',
                            800: '#88602c',
                            900: '#704f29',
                        },
                        gold: {
                            DEFAULT: '#c9a962',
                            50: '#fdf9ef',
                            100: '#f9f0d9',
                            200: '#f2ddb0',
                            300: '#e9c67d',
                            400: '#c9a962',
                            500: '#b8923f',
                            600: '#a47a33',
                            700: '#88602c',
                            800: '#704f29',
                            900: '#5e4225',
                        },
                        navy: {
                            DEFAULT: '#0f172a',
                            dark: '#0a1929',
                            light: '#1e293b',
                        }
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
<body class="bg-navy-dark">
    <!-- Navigation -->
    <nav id="mainNav" class="bg-navy border-b border-navy-light/50 sticky top-0 z-50" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-12 mr-3" loading="eager" decoding="async" onerror="this.style.display='none'">
                        <span class="text-xl font-bold text-white"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="<?php echo $currentView === 'templates' ? 'text-gold border-b-2 border-gold' : 'text-gray-300 hover:text-gold'; ?> font-medium transition-colors py-1">Templates</a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="<?php echo $currentView === 'tools' ? 'text-gold border-b-2 border-gold' : 'text-gray-300 hover:text-gold'; ?> font-medium transition-colors py-1">Tools</a>
                    <a href="#faq" class="text-gray-300 hover:text-gold font-medium transition-colors py-1">FAQ</a>
                    <a href="#" id="cart-button" onclick="toggleCartDrawer(); return false;" class="relative text-gray-300 hover:text-gold font-medium transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-1 -right-1 bg-gold text-navy text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                    </a>
                    <a href="#" class="text-gray-300 hover:text-gold transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                    </a>
                    <a href="/affiliate/register.php" class="inline-flex items-center px-5 py-2.5 text-sm font-semibold rounded-lg text-navy bg-gold hover:bg-gold-500 transition-colors shadow-lg">
                        Become an Affiliate
                    </a>
                </div>
                <div class="md:hidden flex items-center gap-4">
                    <a href="#" id="cart-button-mobile-icon" onclick="toggleCartDrawer(); return false;" class="relative text-gray-300">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count-mobile-icon" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-1 -right-1 bg-gold text-navy text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                    </a>
                    <button @click="open = !open" class="text-gray-300 hover:text-gold focus:outline-none">
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
        <div x-show="open" @click.away="open = false" class="md:hidden bg-navy border-t border-navy-light/50" style="display: none;">
            <div class="px-4 pt-2 pb-4 space-y-2">
                <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="block px-3 py-2 rounded-lg <?php echo $currentView === 'templates' ? 'text-gold bg-navy-light border-l-2 border-gold' : 'text-gray-300 hover:bg-navy-light hover:text-gold'; ?> font-medium transition-colors">Templates</a>
                <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="block px-3 py-2 rounded-lg <?php echo $currentView === 'tools' ? 'text-gold bg-navy-light border-l-2 border-gold' : 'text-gray-300 hover:bg-navy-light hover:text-gold'; ?> font-medium transition-colors">Tools</a>
                <a href="#faq" class="block px-3 py-2 rounded-lg text-gray-300 hover:bg-navy-light hover:text-gold font-medium transition-colors">FAQ</a>
                <a href="/affiliate/register.php" class="block px-3 py-2 rounded-lg text-navy bg-gold hover:bg-gold-500 font-semibold text-center transition-colors">Become an Affiliate</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section - Full 100vh with Stats -->
    <header class="relative bg-navy text-white min-h-[auto] sm:min-h-[calc(100vh-64px)] lg:h-[calc(100vh-64px)] flex flex-col justify-between overflow-hidden">
        <!-- Golden X Stripes Background Decoration - Both Desktop & Mobile -->
        <div class="absolute inset-0 pointer-events-none overflow-hidden">
            <!-- Top Left X Stripe -->
            <div class="absolute -top-20 -left-20 w-[400px] h-[2px] bg-gradient-to-r from-transparent via-gold/30 to-transparent transform rotate-45 origin-center"></div>
            <div class="absolute -top-10 -left-32 w-[500px] h-[2px] bg-gradient-to-r from-transparent via-gold/20 to-transparent transform rotate-45 origin-center"></div>
            <!-- Top Right X Stripe -->
            <div class="absolute -top-20 -right-20 w-[400px] h-[2px] bg-gradient-to-r from-transparent via-gold/30 to-transparent transform -rotate-45 origin-center"></div>
            <div class="absolute -top-10 -right-32 w-[500px] h-[2px] bg-gradient-to-r from-transparent via-gold/20 to-transparent transform -rotate-45 origin-center"></div>
            <!-- Desktop Only - Additional Decorative Stripes -->
            <div class="hidden lg:block">
                <div class="absolute top-0 right-0 w-[600px] h-[600px]">
                    <div class="absolute top-0 right-0 w-[3px] h-[500px] bg-gradient-to-b from-gold/40 via-gold/20 to-transparent transform rotate-[30deg] origin-top-right"></div>
                    <div class="absolute top-10 right-10 w-[3px] h-[450px] bg-gradient-to-b from-gold/30 via-gold/15 to-transparent transform rotate-[30deg] origin-top-right"></div>
                    <div class="absolute top-20 right-20 w-[2px] h-[400px] bg-gradient-to-b from-gold/20 via-gold/10 to-transparent transform rotate-[30deg] origin-top-right"></div>
                </div>
                <div class="absolute bottom-0 right-0 w-[600px] h-[600px]">
                    <div class="absolute bottom-0 right-0 w-[3px] h-[500px] bg-gradient-to-t from-gold/40 via-gold/20 to-transparent transform -rotate-[30deg] origin-bottom-right"></div>
                    <div class="absolute bottom-10 right-10 w-[3px] h-[450px] bg-gradient-to-t from-gold/30 via-gold/15 to-transparent transform -rotate-[30deg] origin-bottom-right"></div>
                    <div class="absolute bottom-20 right-20 w-[2px] h-[400px] bg-gradient-to-t from-gold/20 via-gold/10 to-transparent transform -rotate-[30deg] origin-bottom-right"></div>
                </div>
            </div>
            <!-- Mobile X Pattern -->
            <div class="lg:hidden">
                <svg class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] opacity-[0.06]" viewBox="0 0 200 200" fill="none">
                    <path d="M20 20L180 180M180 20L20 180" stroke="#c9a962" stroke-width="8" stroke-linecap="round"/>
                </svg>
            </div>
        </div>
        
        <!-- Main Content Area -->
        <div class="relative flex-1 flex items-center max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-6">
            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 lg:gap-12 items-center w-full">
                <!-- Left Side (60%) -->
                <div class="lg:col-span-3">
                    <h1 class="text-xl sm:text-2xl md:text-4xl lg:text-5xl xl:text-6xl font-extrabold mb-2 sm:mb-4 lg:mb-6 leading-tight">
                        Build Your Digital Presence with <span class="text-gold">Confidence.</span>
                    </h1>
                    <p class="text-xs sm:text-sm md:text-base lg:text-xl text-gray-400 mb-3 sm:mb-4 lg:mb-8 max-w-xl">
                        Premium website templates and powerful digital tools designed for growing businesses. Launch faster, scale smarter.
                    </p>
                    
                    <!-- CTA Buttons -->
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 lg:gap-4">
                        <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="inline-flex items-center justify-center px-5 lg:px-8 py-2.5 lg:py-4 text-sm lg:text-base font-semibold rounded-lg text-navy bg-gold hover:bg-gold-500 transition-all shadow-lg">
                            <svg class="w-4 h-4 lg:w-5 lg:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                            </svg>
                            Browse Templates
                        </a>
                        <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="inline-flex items-center justify-center px-5 lg:px-8 py-2.5 lg:py-4 text-sm lg:text-base font-semibold rounded-lg text-gold border-2 border-gold hover:bg-gold hover:text-navy transition-all">
                            <svg class="w-4 h-4 lg:w-5 lg:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            Explore Tools
                        </a>
                    </div>
                </div>
                
                <!-- Right Side (40%) - Animated Website Mockup Slideshow with Portfolio Images -->
                <div class="lg:col-span-2 hidden lg:block" x-data="{ 
                    currentSlide: 0,
                    slides: [
                        { image: '/attached_assets/673cf391555dd04aeb06488c_673cf043058ae62753b85be9_jasper-ai_1765359812987.jpeg', title: 'Jasper AI' },
                        { image: '/attached_assets/673cef5e85d139561a882612_673cef539f14468937589302_viralcuts_1765359813240.jpeg', title: 'Viralcuts' },
                        { image: '/attached_assets/673cf391555dd04aeb064892_673cf0e346300e72c673bd83_webflow_1765359813293.jpeg', title: 'Webflow' },
                        { image: '/attached_assets/6722ae41694d5b50ae789bf1_64ac9276557ed29aaabd9b80_intercom-bl_1765359813696.jpeg', title: 'Intercom' },
                        { image: '/attached_assets/673cef5e85d139561a882618_673cef03d1e3baecff16211b_glide-apps_1765359813790.jpeg', title: 'Glide Apps' },
                        { image: '/attached_assets/6722ae41694d5b50ae789bc6_64ac8f36557ed29aaabb4b64_notion_1765359813867.jpeg', title: 'Notion' },
                        { image: '/attached_assets/6722ae41694d5b50ae789bb6_64ac8ead557ed29aaabaf6ec_runway_1765359814004.jpeg', title: 'Runway' }
                    ],
                    init() {
                        setInterval(() => { this.currentSlide = (this.currentSlide + 1) % this.slides.length }, 4000)
                    }
                }">
                    <div class="relative group">
                        <!-- Shiny glow effect behind the laptop -->
                        <div class="absolute -inset-4 bg-gradient-to-r from-gold/20 via-gold/10 to-gold/20 rounded-2xl blur-xl opacity-60 group-hover:opacity-80 transition-opacity duration-500"></div>
                        <div class="absolute -inset-2 bg-gradient-to-br from-gold/10 via-transparent to-gold/10 rounded-xl opacity-50"></div>
                        
                        <div class="relative bg-navy-light rounded-xl border border-gold/30 shadow-2xl shadow-gold/10 overflow-hidden">
                            <!-- Safari-style browser header -->
                            <div class="bg-gradient-to-b from-gray-800 to-navy-light px-3 py-2 border-b border-gray-700 flex items-center gap-2">
                                <div class="w-2.5 h-2.5 rounded-full bg-red-500 shadow-sm shadow-red-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-yellow-500 shadow-sm shadow-yellow-500/50"></div>
                                <div class="w-2.5 h-2.5 rounded-full bg-green-500 shadow-sm shadow-green-500/50"></div>
                                <div class="flex-1 ml-3">
                                    <div class="bg-navy/80 backdrop-blur rounded-lg px-3 py-1 text-xs text-gray-400 max-w-xs flex items-center gap-2">
                                        <svg class="w-3 h-3 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                        <span x-text="slides[currentSlide].title.toLowerCase().replace(/\s+/g, '') + '.com'"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="aspect-[16/10] relative overflow-hidden bg-navy">
                                <template x-for="(slide, index) in slides" :key="index">
                                    <div class="absolute inset-0 transition-all duration-700 ease-in-out"
                                         :class="currentSlide === index ? 'opacity-100 scale-100' : 'opacity-0 scale-105'">
                                        <img :src="slide.image" :alt="slide.title" class="w-full h-full object-cover object-top">
                                    </div>
                                </template>
                            </div>
                            <!-- Slide Indicators with improved styling -->
                            <div class="flex justify-center gap-2 py-2.5 bg-gradient-to-t from-navy-light to-navy-light/90 border-t border-gray-700/50">
                                <template x-for="(slide, index) in slides" :key="index">
                                    <button @click="currentSlide = index" 
                                            class="h-2 rounded-full transition-all duration-300 focus:outline-none"
                                            :class="currentSlide === index ? 'bg-gold w-6 shadow-sm shadow-gold/50' : 'bg-gray-600 w-2 hover:bg-gold/50'"></button>
                                </template>
                            </div>
                        </div>
                        <!-- Enhanced glow effects -->
                        <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-gold/20 rounded-full blur-2xl animate-pulse"></div>
                        <div class="absolute -top-4 -left-4 w-28 h-28 bg-gold/10 rounded-full blur-3xl"></div>
                        <div class="absolute top-1/2 -right-6 w-16 h-32 bg-gold/15 rounded-full blur-xl transform -translate-y-1/2"></div>
                    </div>
                </div>
                
                <!-- Mobile Laptop Mockup Slider - Shows on mobile only -->
                <div class="lg:hidden mt-6" x-data="{ 
                    currentSlide: 0,
                    slides: [
                        { image: '/attached_assets/673cf391555dd04aeb06488c_673cf043058ae62753b85be9_jasper-ai_1765359812987.jpeg', title: 'Jasper AI' },
                        { image: '/attached_assets/673cef5e85d139561a882612_673cef539f14468937589302_viralcuts_1765359813240.jpeg', title: 'Viralcuts' },
                        { image: '/attached_assets/673cf391555dd04aeb064892_673cf0e346300e72c673bd83_webflow_1765359813293.jpeg', title: 'Webflow' },
                        { image: '/attached_assets/6722ae41694d5b50ae789bf1_64ac9276557ed29aaabd9b80_intercom-bl_1765359813696.jpeg', title: 'Intercom' },
                        { image: '/attached_assets/673cef5e85d139561a882618_673cef03d1e3baecff16211b_glide-apps_1765359813790.jpeg', title: 'Glide Apps' },
                        { image: '/attached_assets/6722ae41694d5b50ae789bc6_64ac8f36557ed29aaabb4b64_notion_1765359813867.jpeg', title: 'Notion' },
                        { image: '/attached_assets/6722ae41694d5b50ae789bb6_64ac8ead557ed29aaabaf6ec_runway_1765359814004.jpeg', title: 'Runway' }
                    ],
                    init() {
                        setInterval(() => { this.currentSlide = (this.currentSlide + 1) % this.slides.length }, 4000)
                    }
                }">
                    <div class="relative mx-auto max-w-sm">
                        <!-- Shiny glow effect -->
                        <div class="absolute -inset-2 bg-gradient-to-r from-gold/15 via-gold/5 to-gold/15 rounded-xl blur-lg opacity-70"></div>
                        
                        <div class="relative bg-navy-light rounded-lg border border-gold/20 shadow-xl shadow-gold/5 overflow-hidden">
                            <!-- Safari-style browser header -->
                            <div class="bg-gradient-to-b from-gray-800 to-navy-light px-2 py-1.5 border-b border-gray-700 flex items-center gap-1.5">
                                <div class="w-2 h-2 rounded-full bg-red-500"></div>
                                <div class="w-2 h-2 rounded-full bg-yellow-500"></div>
                                <div class="w-2 h-2 rounded-full bg-green-500"></div>
                                <div class="flex-1 ml-2">
                                    <div class="bg-navy/80 rounded px-2 py-0.5 text-[10px] text-gray-400 flex items-center gap-1">
                                        <svg class="w-2.5 h-2.5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/></svg>
                                        <span x-text="slides[currentSlide].title.toLowerCase().replace(/\s+/g, '') + '.com'"></span>
                                    </div>
                                </div>
                            </div>
                            <div class="aspect-[16/10] relative overflow-hidden bg-navy">
                                <template x-for="(slide, index) in slides" :key="index">
                                    <div class="absolute inset-0 transition-all duration-700"
                                         :class="currentSlide === index ? 'opacity-100' : 'opacity-0'">
                                        <img :src="slide.image" :alt="slide.title" class="w-full h-full object-cover object-top">
                                    </div>
                                </template>
                            </div>
                            <!-- Slide Indicators -->
                            <div class="flex justify-center gap-1.5 py-2 bg-navy-light border-t border-gray-700/50">
                                <template x-for="(slide, index) in slides" :key="index">
                                    <button @click="currentSlide = index" 
                                            class="h-1.5 rounded-full transition-all duration-300"
                                            :class="currentSlide === index ? 'bg-gold w-4' : 'bg-gray-600 w-1.5 hover:bg-gold/50'"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Bar - Inside Hero for 100vh -->
        <div class="relative py-2 lg:py-4 bg-navy/50 backdrop-blur-sm">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-navy-light/80 border border-gray-700/80 rounded-full py-1.5 lg:py-3 px-2 lg:px-6">
                    <div class="grid grid-cols-3 gap-1 lg:gap-4 text-center">
                        <div class="flex items-center justify-center gap-1 lg:gap-3">
                            <div class="w-6 h-6 lg:w-9 lg:h-9 bg-gold/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 lg:w-4 lg:h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="text-[10px] sm:text-xs lg:text-lg font-bold text-white">500+</div>
                                <div class="text-[8px] lg:text-xs text-gray-400">Websites</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center gap-1 lg:gap-3 border-x border-gray-700/50">
                            <div class="w-6 h-6 lg:w-9 lg:h-9 bg-gold/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 lg:w-4 lg:h-4 text-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="text-[10px] sm:text-xs lg:text-lg font-bold text-white">10k+</div>
                                <div class="text-[8px] lg:text-xs text-gray-400">Users</div>
                            </div>
                        </div>
                        <div class="flex items-center justify-center gap-1 lg:gap-3">
                            <div class="w-6 h-6 lg:w-9 lg:h-9 bg-gold/10 rounded-full flex items-center justify-center flex-shrink-0">
                                <svg class="w-3 h-3 lg:w-4 lg:h-4 text-gold" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <div class="text-[10px] sm:text-xs lg:text-lg font-bold text-white">4.9/5</div>
                                <div class="text-[8px] lg:text-xs text-gray-400">Rating</div>
                            </div>
                        </div>
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
                }, 9000);
            }, 15000);
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
                 x-transition:enter="transition ease-out duration-[2000ms]"
                 x-transition:enter-start="opacity-0 -translate-x-4"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 x-transition:leave="transition ease-in duration-[1000ms]"
                 x-transition:leave-start="opacity-100 translate-x-0"
                 x-transition:leave-end="opacity-0 -translate-x-full"
                 class="text-white font-semibold text-sm whitespace-nowrap pr-2 overflow-hidden">
                <span x-text="messages[currentIndex]"></span>
            </div>
        </a>
    </div>

    <!-- Products Section (Templates & Tools) -->
    <section class="py-12 bg-navy-dark" id="products">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- View Toggle Tabs with Gold Underline -->
            <div class="flex justify-center mb-8">
                <div class="inline-flex gap-8" role="group">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="pb-3 text-lg font-semibold transition-all whitespace-nowrap border-b-2 <?php echo $currentView === 'templates' ? 'text-white border-gold' : 'text-gray-400 border-transparent hover:text-gray-300'; ?>">
                        Website Templates
                    </a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="pb-3 text-lg font-semibold transition-all whitespace-nowrap border-b-2 <?php echo $currentView === 'tools' ? 'text-white border-gold' : 'text-gray-400 border-transparent hover:text-gray-300'; ?>">
                        Working Tools
                    </a>
                </div>
            </div>

            <!-- Search and Filter Container -->
            <div class="max-w-4xl mx-auto mb-8">
                <div class="flex flex-col md:flex-row gap-2 md:gap-4">
                    <!-- Search Input -->
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" 
                                   id="search-input"
                                   placeholder="Search <?php echo $currentView === 'templates' ? 'templates' : 'tools'; ?>..." 
                                   class="w-full px-4 py-3 pl-11 pr-10 bg-navy-light border border-gray-700 rounded-lg text-white placeholder-gray-500 focus:border-gold focus:ring-1 focus:ring-gold transition-all">
                            <svg class="w-5 h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <button id="clear-search" style="display: none;" 
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors z-10"
                                    title="Clear search">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                            <div id="search-loading" style="display: none;" class="absolute right-10 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-5 w-5 text-gold" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="w-full md:w-56">
                        <div class="relative">
                            <select id="category-filter" 
                                    class="w-full px-4 py-3 pl-4 pr-10 bg-navy-light border border-gray-700 rounded-lg text-white font-medium cursor-pointer focus:border-gold focus:ring-1 focus:ring-gold transition-all appearance-none">
                                <option value="">Category</option>
                                <?php 
                                $categories = $currentView === 'templates' ? $templateCategories : $toolCategories;
                                foreach ($categories as $cat): 
                                ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo (isset($_GET['category']) && $_GET['category'] === $cat) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="w-5 h-5 absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Content Area -->
            <div id="products-content-area">
            <?php if ($currentView === 'templates'): ?>
            
            <?php if (empty($templates)): ?>
            <div class="bg-navy-light border border-gray-700 rounded-xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gold mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h4 class="text-xl font-bold text-white mb-2">No templates available</h4>
                <p class="text-gray-400 mb-0">Please check back later or <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="font-semibold text-gold hover:text-gold-500">contact us on WhatsApp</a>.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-6" data-templates-grid>
                <?php foreach ($templates as $template): ?>
                <div class="group" 
                     data-template
                     data-template-name="<?php echo htmlspecialchars($template['name']); ?>"
                     data-template-category="<?php echo htmlspecialchars($template['category']); ?>"
                     data-template-price="<?php echo htmlspecialchars($template['price']); ?>">
                    <div class="bg-navy-light rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:border-gold/30 hover:-translate-y-1">
                        <div class="relative overflow-hidden h-48 bg-navy">
                            <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                                 alt="<?php echo htmlspecialchars($template['name']); ?>"
                                 class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                                 onerror="this.src='/assets/images/placeholder.jpg'">
                            <?php 
                            $mediaType = $template['media_type'] ?? 'banner';
                            $hasDemo = !empty($template['demo_url']) || !empty($template['demo_video_url']) || !empty($template['preview_youtube']);
                            $isYoutube = ($mediaType === 'youtube' && !empty($template['preview_youtube']));
                            $isVideo = ($mediaType === 'video' && !empty($template['demo_video_url']));
                            $isDemoUrl = ($mediaType === 'demo_url' && !empty($template['demo_url']));
                            
                            if ($hasDemo):
                            ?>
                            <?php if ($isYoutube): ?>
                            <button onclick="event.stopPropagation(); openYoutubeModal('<?php echo htmlspecialchars($template['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 shadow-lg transition-colors z-10 backdrop-blur-sm">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </button>
                            <button onclick="openYoutubeModal('<?php echo htmlspecialchars($template['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg font-medium shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview
                                </span>
                            </button>
                            <?php elseif ($isVideo): ?>
                            <button onclick="event.stopPropagation(); openVideoModal('<?php echo htmlspecialchars($template['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 shadow-lg transition-colors z-10 backdrop-blur-sm">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </button>
                            <button onclick="openVideoModal('<?php echo htmlspecialchars($template['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    data-video-trigger
                                    data-video-url="<?php echo htmlspecialchars($template['demo_video_url'], ENT_QUOTES); ?>"
                                    data-video-title="<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>"
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg font-medium shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview
                                </span>
                            </button>
                            <?php elseif ($isDemoUrl): ?>
                            <button onclick="event.stopPropagation(); openDemoFullscreen('<?php echo htmlspecialchars($template['demo_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                    class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 shadow-lg transition-colors z-10 backdrop-blur-sm">
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Preview
                            </button>
                            <button onclick="openDemoFullscreen('<?php echo htmlspecialchars($template['demo_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')" 
                                    class="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                <span class="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-lg font-medium shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    Preview
                                </span>
                            </button>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-base font-bold text-white flex-1 pr-2"><?php echo htmlspecialchars($template['name']); ?></h3>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold shrink-0">
                                    <?php echo htmlspecialchars($template['category']); ?>
                                </span>
                            </div>
                            <p class="text-gray-400 text-sm mb-4 line-clamp-2 min-h-[40px]"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                            <div class="flex items-center justify-between pt-3 border-t border-gray-700/50">
                                <div class="flex flex-col">
                                    <span class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">PRICE</span>
                                    <span class="text-lg font-bold text-gold"><?php echo formatCurrency($template['price']); ?></span>
                                </div>
                                <div class="flex gap-2">
                                    <a href="<?php echo getTemplateUrl($template, $affiliateCode); ?>" 
                                       class="inline-flex items-center justify-center px-4 py-2 border border-gray-600 text-xs font-semibold rounded-lg text-gray-300 bg-transparent hover:bg-navy hover:border-gray-500 transition-colors whitespace-nowrap">
                                        Details
                                    </a>
                                    <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '<?php echo addslashes($template['name']); ?>', this)" 
                                       class="inline-flex items-center justify-center px-4 py-2 border border-transparent text-xs font-semibold rounded-lg text-navy bg-gold hover:bg-gold-500 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
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
                       class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Prev
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $i])); ?>#products" 
                       class="<?php echo $i === $page ? 'bg-gold text-navy font-bold shadow-md shadow-gold/20' : 'bg-navy-light text-gray-300 border border-gray-600 hover:bg-gold/20 hover:text-gold hover:border-gold/50'; ?> inline-flex items-center justify-center w-10 h-10 text-sm font-semibold rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page + 1])); ?>#products" 
                       class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm font-medium text-gray-500">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> <span class="mx-1">•</span> <?php echo $totalTemplates; ?> products
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php else: ?>
            <!-- TOOLS VIEW -->
            <?php if (empty($tools)): ?>
            <div class="bg-navy-light border border-gray-700 rounded-xl p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gold mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <h4 class="text-xl font-bold text-white mb-2">No tools available</h4>
                <p class="text-gray-400 mb-0">Please check back later or <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER); ?>" class="font-semibold text-gold hover:text-gold-500">contact us on WhatsApp</a>.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-4 gap-6">
                <?php foreach ($tools as $tool): ?>
                <div class="tool-card group bg-navy-light rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:border-gold/30 hover:-translate-y-1" 
                     data-tool-id="<?php echo $tool['id']; ?>">
                    <div class="relative overflow-hidden h-40 bg-navy">
                        <img src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                             alt="<?php echo htmlspecialchars($tool['name']); ?>"
                             class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                             onerror="this.src='/assets/images/placeholder.jpg'">
                        <?php 
                        $toolMediaType = $tool['media_type'] ?? 'banner';
                        if ($toolMediaType === 'youtube' && !empty($tool['preview_youtube'])): 
                        ?>
                        <button onclick="openYoutubeModal('<?php echo htmlspecialchars($tool['preview_youtube'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                                class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-lg backdrop-blur-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Preview
                        </button>
                        <?php elseif ($toolMediaType === 'video' && !empty($tool['demo_video_url'])): ?>
                        <button onclick="openVideoModal('<?php echo htmlspecialchars($tool['demo_video_url'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($tool['name'], ENT_QUOTES); ?>')"
                                class="absolute top-3 left-3 px-3 py-1.5 bg-navy/90 hover:bg-navy text-white text-xs font-semibold rounded-full flex items-center gap-1.5 transition-all shadow-lg backdrop-blur-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Preview
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
                            <h3 class="text-sm font-bold text-white flex-1 pr-2"><?php echo htmlspecialchars($tool['name']); ?></h3>
                            <?php if (!empty($tool['category'])): ?>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gold/20 text-gold shrink-0">
                                <?php echo htmlspecialchars($tool['category']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-400 text-xs mb-3 line-clamp-2 min-h-[32px]"><?php echo htmlspecialchars($tool['short_description'] ?? ''); ?></p>
                        <div class="flex items-center justify-between pt-3 border-t border-gray-700/50">
                            <div class="flex flex-col">
                                <span class="text-[10px] text-gray-500 uppercase tracking-wider font-medium">PRICE</span>
                                <span class="text-lg font-extrabold text-gold"><?php echo formatCurrency($tool['price']); ?></span>
                            </div>
                            <button data-tool-id="<?php echo $tool['id']; ?>" 
                                    class="tool-preview-btn inline-flex items-center justify-center px-4 py-2 border border-gray-600 text-xs font-semibold rounded-lg text-gray-300 bg-transparent hover:bg-navy hover:border-gray-500 transition-all whitespace-nowrap">
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
                       class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Prev
                    </a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $i])); ?>#products" 
                       class="<?php echo $i === $page ? 'bg-gold text-navy font-bold shadow-md shadow-gold/20' : 'bg-navy-light text-gray-300 border border-gray-600 hover:bg-gold/20 hover:text-gold hover:border-gold/50'; ?> inline-flex items-center justify-center w-10 h-10 text-sm font-semibold rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page + 1])); ?>#products" 
                       class="inline-flex items-center px-4 py-2.5 text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm font-medium text-gray-500">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> <span class="mx-1">•</span> <?php echo $totalTools; ?> products
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-16 bg-navy">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-8">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">Trusted by Businesses Like Yours</h2>
                <p class="text-lg text-gray-400">See what our customers say about launching their online presence</p>
            </div>
        </div>
        
        <div class="carousel-wrapper mx-auto px-4" style="max-width: 1200px;">
            <div id="testimonialCarousel" class="carousel-container" style="display: flex; gap: 24px; overflow: hidden; scroll-behavior: auto; -webkit-overflow-scrolling: touch;">
                <!-- Items will be cloned by JavaScript for infinite scroll -->
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-navy-light rounded-xl shadow-md p-8 border border-gray-700/50 h-full flex flex-col">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-300 mb-6 flex-grow">"Amazing service! My restaurant website was live in 24 hours. The template looks professional and my customers love it."</p>
                        <div>
                            <div class="font-semibold text-white">Adebayo Johnson</div>
                            <div class="text-sm text-gray-500">Bella's Kitchen, Lagos</div>
                        </div>
                    </div>
                </div>
                
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-navy-light rounded-xl shadow-md p-8 border border-gray-700/50 h-full flex flex-col">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-300 mb-6 flex-grow">"From zero to online business in one day! The setup was seamless and the support team is incredible."</p>
                        <div>
                            <div class="font-semibold text-white">Ngozi Okoro</div>
                            <div class="text-sm text-gray-500">Fashion Boutique, Abuja</div>
                        </div>
                    </div>
                </div>
                
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-navy-light rounded-xl shadow-md p-8 border border-gray-700/50 h-full flex flex-col">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-300 mb-6 flex-grow">"Professional, fast, and affordable. My law firm website attracts new clients every week. Highly recommended!"</p>
                        <div>
                            <div class="font-semibold text-white">Barrister Emeka</div>
                            <div class="text-sm text-gray-500">Legal Services, Port Harcourt</div>
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
    <section class="py-12 bg-navy-dark" id="faq">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-white mb-4">Frequently Asked Questions</h2>
                <p class="text-lg text-gray-400">Everything you need to know</p>
            </div>
            <div class="space-y-4" x-data="{ selected: 1 }">
                <div class="bg-navy-light rounded-xl shadow-md border border-gray-700/50 overflow-hidden">
                    <button @click="selected = selected === 1 ? null : 1" class="w-full text-left px-6 py-4 font-semibold text-white flex justify-between items-center hover:bg-navy transition-colors">
                        <span>What's included in the price?</span>
                        <svg class="w-5 h-5 text-gold transform transition-transform" :class="selected === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 1" x-collapse class="px-6 pb-4 text-gray-300 border-t border-gray-700/50">
                        Complete website template, premium domain name, hosting setup, and full customization access. You get everything needed to launch your business online.
                    </div>
                </div>
                <div class="bg-navy-light rounded-xl shadow-md border border-gray-700/50 overflow-hidden">
                    <button @click="selected = selected === 2 ? null : 2" class="w-full text-left px-6 py-4 font-semibold text-white flex justify-between items-center hover:bg-navy transition-colors">
                        <span>How long does setup take?</span>
                        <svg class="w-5 h-5 text-gold transform transition-transform" :class="selected === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 2" x-collapse class="px-6 pb-4 text-gray-300 border-t border-gray-700/50">
                        Your website will be ready within 24 hours after payment confirmation. We handle all the technical setup so you can focus on your business.
                    </div>
                </div>
                <div class="bg-navy-light rounded-xl shadow-md border border-gray-700/50 overflow-hidden">
                    <button @click="selected = selected === 3 ? null : 3" class="w-full text-left px-6 py-4 font-semibold text-white flex justify-between items-center hover:bg-navy transition-colors">
                        <span>How do I get support?</span>
                        <svg class="w-5 h-5 text-gold transform transition-transform" :class="selected === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 3" x-collapse class="px-6 pb-4 text-gray-300 border-t border-gray-700/50">
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
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-10 mr-3" loading="eager" decoding="async" onerror="this.style.display='none'">
                        <span class="text-2xl font-bold"><?php echo SITE_NAME; ?></span>
                    </div>
                    <p class="text-gray-400 text-base mb-6 leading-relaxed">Professional website templates and digital working tools to power your business. Get custom domains, API keys, software licenses, and more. Launch in 24 hours or less.</p>
                </div>
                
                <!-- Contact Section -->
                <div>
                    <h3 class="text-xl font-bold mb-6">Get In Touch</h3>
                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" 
                           class="inline-flex items-center justify-center px-6 py-3 bg-green-600 hover:bg-green-700 rounded-full transition-colors font-semibold gap-2">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                            WhatsApp Support
                        </a>
                        
                        <a href="/affiliate/register.php" 
                           class="inline-flex items-center justify-center px-6 py-3 bg-gold hover:bg-gold-500 text-navy rounded-full transition-colors font-semibold gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Become an Affiliate
                        </a>
                    </div>
                    
                    <!-- Social Icons -->
                    <div class="flex gap-3">
                        <a href="#" class="w-10 h-10 bg-navy-light border border-gray-700/50 rounded-full flex items-center justify-center text-gray-400 hover:text-gold hover:border-gold/50 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-navy-light border border-gray-700/50 rounded-full flex items-center justify-center text-gray-400 hover:text-gold hover:border-gold/50 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-navy-light border border-gray-700/50 rounded-full flex items-center justify-center text-gray-400 hover:text-gold hover:border-gold/50 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0C8.74 0 8.333.015 7.053.072 5.775.132 4.905.333 4.14.63c-.789.306-1.459.717-2.126 1.384S.935 3.35.63 4.14C.333 4.905.131 5.775.072 7.053.012 8.333 0 8.74 0 12s.015 3.667.072 4.947c.06 1.277.261 2.148.558 2.913.306.788.717 1.459 1.384 2.126.667.666 1.336 1.079 2.126 1.384.766.296 1.636.499 2.913.558C8.333 23.988 8.74 24 12 24s3.667-.015 4.947-.072c1.277-.06 2.148-.262 2.913-.558.788-.306 1.459-.718 2.126-1.384.666-.667 1.079-1.335 1.384-2.126.296-.765.499-1.636.558-2.913.06-1.28.072-1.687.072-4.947s-.015-3.667-.072-4.947c-.06-1.277-.262-2.149-.558-2.913-.306-.789-.718-1.459-1.384-2.126C21.319 1.347 20.651.935 19.86.63c-.765-.297-1.636-.499-2.913-.558C15.667.012 15.26 0 12 0zm0 2.16c3.203 0 3.585.016 4.85.071 1.17.055 1.805.249 2.227.415.562.217.96.477 1.382.896.419.42.679.819.896 1.381.164.422.36 1.057.413 2.227.057 1.266.07 1.646.07 4.85s-.015 3.585-.074 4.85c-.061 1.17-.256 1.805-.421 2.227-.224.562-.479.96-.899 1.382-.419.419-.824.679-1.38.896-.42.164-1.065.36-2.235.413-1.274.057-1.649.07-4.859.07-3.211 0-3.586-.015-4.859-.074-1.171-.061-1.816-.256-2.236-.421-.569-.224-.96-.479-1.379-.899-.421-.419-.69-.824-.9-1.38-.165-.42-.359-1.065-.42-2.235-.045-1.26-.061-1.649-.061-4.844 0-3.196.016-3.586.061-4.861.061-1.17.255-1.814.42-2.234.21-.57.479-.96.9-1.381.419-.419.81-.689 1.379-.898.42-.166 1.051-.361 2.221-.421 1.275-.045 1.65-.06 4.859-.06l.045.03zm0 3.678c-3.405 0-6.162 2.76-6.162 6.162 0 3.405 2.76 6.162 6.162 6.162 3.405 0 6.162-2.76 6.162-6.162 0-3.405-2.76-6.162-6.162-6.162zM12 16c-2.21 0-4-1.79-4-4s1.79-4 4-4 4 1.79 4 4-1.79 4-4 4zm7.846-10.405c0 .795-.646 1.44-1.44 1.44-.795 0-1.44-.646-1.44-1.44 0-.794.646-1.439 1.44-1.439.793-.001 1.44.645 1.44 1.439z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-navy-light border border-gray-700/50 rounded-full flex items-center justify-center text-gray-400 hover:text-gold hover:border-gold/50 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                        <a href="#" class="w-10 h-10 bg-navy-light border border-gray-700/50 rounded-full flex items-center justify-center text-gray-400 hover:text-gold hover:border-gold/50 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700/50 pt-8">
                <div class="text-center">
                    <p class="text-gray-500 text-sm">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
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
