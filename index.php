<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/cart.php';

// Set cache headers - no caching for dynamic pages
header('Cache-Control: no-cache, no-store, must-revalidate', false);
header('Pragma: no-cache', false);
header('Expires: 0', false);

startSecureSession();
handleAffiliateTracking();
handleUserReferralTracking();

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
    $pageUrl = SITE_URL . '/tool/' . $autoOpenToolSlug;
    
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
    
    <!-- Preload critical images for instant display -->
    <link rel="preload" as="image" href="/assets/images/webdaddy-logo.png" fetchpriority="high">
    <link rel="preload" as="image" href="/assets/images/mockups/viralcuts.jpg" fetchpriority="high">
    <link rel="preload" as="image" href="/assets/images/mockups/jasper-ai.jpg">
    <link rel="preload" as="image" href="/assets/images/mockups/webflow.jpg">
    <link rel="preload" as="image" href="/assets/images/mockups/intercom.jpg">
    <link rel="preload" as="image" href="/assets/images/mockups/glide-apps.jpg">
    <link rel="preload" as="image" href="/assets/images/mockups/notion.jpg">
    <link rel="preload" as="image" href="/assets/images/mockups/runway.jpg">
    <link rel="dns-prefetch" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdn.tailwindcss.com" crossorigin>
    
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
    
    <!-- Premium Fonts - Inter and Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Premium UI/UX Styles -->
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
                        primary: {
                            50: '#FDF9ED',
                            100: '#FAF0D4',
                            200: '#F5E1A8',
                            300: '#EFCF72',
                            400: '#E8BB45',
                            500: '#D4AF37',
                            600: '#B8942E',
                            700: '#9A7B26',
                            800: '#7D6320',
                            900: '#604B18',
                        },
                        gold: {
                            DEFAULT: '#D4AF37',
                            50: '#FDF9ED',
                            100: '#FAF0D4',
                            200: '#F5E1A8',
                            300: '#EFCF72',
                            400: '#E8BB45',
                            500: '#D4AF37',
                            600: '#B8942E',
                            700: '#9A7B26',
                            800: '#7D6320',
                            900: '#604B18',
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
    <style>
        .btn-gold-shine {
            background: linear-gradient(135deg, #F5D669 0%, #D4AF37 50%, #B8942E 100%);
            box-shadow: 0 4px 15px rgba(212,175,55,0.35), inset 0 1px 0 rgba(255,255,255,0.25);
            text-shadow: 0 1px 1px rgba(0,0,0,0.15);
        }
        .btn-gold-shine:hover {
            background: linear-gradient(135deg, #FADE7A 0%, #E8BB45 50%, #D4AF37 100%);
            box-shadow: 0 6px 25px rgba(212,175,55,0.5), inset 0 1px 0 rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }
        .gold-text-shine {
            background: linear-gradient(135deg, #F5D669 0%, #D4AF37 50%, #F5D669 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .border-gold-shine {
            border-color: #D4AF37;
            box-shadow: 0 0 15px rgba(212,175,55,0.25);
        }
        
        /* Hide broken images completely */
        img[alt]:not([src]),
        img[alt][src=""]:not([loading]),
        img.image-broken {
            display: none !important;
            visibility: hidden !important;
        }
        
        /* Prevent browser default broken image icon */
        img:broken {
            display: none !important;
        }
        
        /* Animated word swapping styles */
        .animate-word-swap {
            display: inline-block;
            min-width: 120px;
            position: relative;
            vertical-align: baseline;
        }
        
        /* EXIT ANIMATION 1: Fade Upward with Glow Burst */
        @keyframes exitFadeUp {
            0% { opacity: 1; transform: translateY(0) scale(1); text-shadow: 0 0 0px #D4AF37; }
            40% { text-shadow: 0 0 25px #D4AF37, 0 0 40px rgba(212,175,55,0.5); }
            100% { opacity: 0; transform: translateY(-60px) scale(0.8); text-shadow: 0 0 60px #D4AF37; filter: blur(8px); }
        }
        
        /* EXIT ANIMATION 2: Wipe Right with Shine */
        @keyframes exitWipeRight {
            0% { opacity: 1; transform: translateX(0); clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%); }
            60% { opacity: 0.3; text-shadow: 0 0 20px #D4AF37; }
            100% { opacity: 0; transform: translateX(80px); clip-path: polygon(100% 0, 100% 0, 100% 100%, 100% 100%); }
        }
        
        /* EXIT ANIMATION 3: Golden Fade with Blur */
        @keyframes exitGoldenFade {
            0% { opacity: 1; filter: brightness(1) blur(0px); text-shadow: 0 0 0px #D4AF37; }
            30% { text-shadow: 0 0 30px #D4AF37, 0 0 20px #D4AF37; }
            60% { opacity: 0.5; filter: brightness(2) blur(5px); }
            100% { opacity: 0; filter: brightness(0.2) blur(15px); }
        }
        
        /* EXIT ANIMATION 4: Spin Away */
        @keyframes exitSpinAway {
            0% { opacity: 1; transform: rotate(0deg) scale(1); text-shadow: 0 0 0px #D4AF37; }
            50% { text-shadow: 0 0 25px #D4AF37; }
            100% { opacity: 0; transform: rotate(360deg) translateY(-80px) scale(0.3); filter: blur(10px); }
        }
        
        /* ENTRANCE ANIMATION 1: Slide Down Smooth */
        @keyframes enterSlideDown {
            0% { opacity: 0; transform: translateY(-60px); filter: blur(12px); text-shadow: 0 0 0px #D4AF37; }
            60% { text-shadow: 0 0 25px #D4AF37; }
            100% { opacity: 1; transform: translateY(0); filter: blur(0px); text-shadow: 0 0 10px #D4AF37; }
        }
        
        /* ENTRANCE ANIMATION 2: Expand Pulse Glow */
        @keyframes enterExpandPulse {
            0% { opacity: 0; transform: scale(0.1); filter: blur(15px); text-shadow: 0 0 0px #D4AF37; }
            50% { opacity: 0.8; transform: scale(1.3); text-shadow: 0 0 40px #D4AF37, 0 0 25px #D4AF37; }
            100% { opacity: 1; transform: scale(1); filter: blur(0px); text-shadow: 0 0 10px #D4AF37; }
        }
        
        /* ENTRANCE ANIMATION 3: 3D Flip In */
        @keyframes enterFlipIn {
            0% { opacity: 0; transform: perspective(1000px) rotateY(90deg) rotateX(20deg); filter: blur(8px); }
            70% { text-shadow: 0 0 30px #D4AF37; }
            100% { opacity: 1; transform: perspective(1000px) rotateY(0deg) rotateX(0deg); filter: blur(0px); }
        }
        
        /* ENTRANCE ANIMATION 4: Split Reveal */
        @keyframes enterSplitReveal {
            0% { opacity: 0; clip-path: polygon(50% 0%, 50% 0%, 50% 100%, 50% 100%); filter: blur(10px); text-shadow: 0 0 0px #D4AF37; }
            40% { text-shadow: 0 0 30px #D4AF37, 0 0 15px #D4AF37; }
            100% { opacity: 1; clip-path: polygon(0% 0%, 100% 0%, 100% 100%, 0% 100%); filter: blur(0px); }
        }
        
        /* ENTRANCE ANIMATION 5: Rise with Shimmer */
        @keyframes enterRiseShimmer {
            0% { opacity: 0; transform: translateY(60px) scaleY(0.5); filter: blur(12px); text-shadow: 0 0 0px #D4AF37; }
            40% { opacity: 0.7; text-shadow: 0 0 25px #D4AF37, 0 0 40px rgba(212,175,55,0.3); }
            100% { opacity: 1; transform: translateY(0) scaleY(1); filter: blur(0px); text-shadow: 0 0 10px #D4AF37; }
        }
        
        /* Animation classes */
        .anim-exit-1 { animation: exitFadeUp 1.2s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards; }
        .anim-exit-2 { animation: exitWipeRight 1.0s ease-in forwards; }
        .anim-exit-3 { animation: exitGoldenFade 1.3s ease-out forwards; }
        .anim-exit-4 { animation: exitSpinAway 1.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        
        .anim-enter-1 { animation: enterSlideDown 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        .anim-enter-2 { animation: enterExpandPulse 1.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards; }
        .anim-enter-3 { animation: enterFlipIn 1.1s ease-out forwards; }
        .anim-enter-4 { animation: enterSplitReveal 1.5s ease-out forwards; }
        .anim-enter-5 { animation: enterRiseShimmer 1.8s ease-out forwards; }
        
        /* ========== PREMIUM SLICED X LOADER WITH SPARKLING EFFECTS ========== */
        #page-loader {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 9999;
            background: radial-gradient(ellipse at 50% 50%, #0f1f2e 0%, #0a1929 50%, #050d14 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: all;
            overflow: hidden;
        }
        
        #page-loader.loader-hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
            transition: opacity 0.3s ease-out, visibility 0.3s;
        }
        
        .loader-slices {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .slice-line {
            animation: sliceGlowSync 0.6s ease-in-out infinite;
        }
        .slice-line.s2 { animation-delay: 0.15s; }
        .slice-line.s3 { animation-delay: 0.3s; }
        .slice-line.s4 { animation-delay: 0.2s; }
        .slice-line.s5 { animation-delay: 0.08s; }
        .slice-line.s7 { animation-delay: 0.3s; }
        .slice-line.s8 { animation-delay: 0.4s; }
        
        @keyframes sliceGlowSync {
            0% { opacity: 0.45; stroke-width: 2.2; filter: url(#glow1); }
            50% { opacity: 1; stroke-width: 4; filter: url(#glow2); }
            100% { opacity: 0.45; stroke-width: 2.2; filter: url(#glow1); }
        }
        
        @keyframes sliceZoomEvaporate {
            0% { opacity: 1; stroke-width: 4; filter: url(#glow2); transform: scale(1); }
            70% { opacity: 1; stroke-width: 4; filter: url(#glow2); transform: scale(1.1); }
            100% { opacity: 0; stroke-width: 2; filter: url(#glow1); transform: scale(1.8); }
        }
        
        
        .loader-center-glow {
            position: fixed;
            top: 50%;
            left: 50%;
            width: 450px;
            height: 450px;
            background: radial-gradient(circle, rgba(212,175,55,0.3) 0%, rgba(212,175,55,0.1) 40%, transparent 100%);
            transform: translate(-50%, -50%);
            animation: centerGlowPulse 2.4s ease-in-out infinite;
            filter: blur(45px);
            pointer-events: none;
        }
        
        .loader-logo-container {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        
        .loader-logo {
            width: 140px;
            height: auto;
            max-width: none;
            animation: logoGlowPulse 0.6s ease-in-out infinite;
            filter: drop-shadow(0 0 25px rgba(212,175,55,0.9));
            opacity: 1;
        }
        
        
        @keyframes centerGlowPulse {
            0%, 100% { transform: translate(-50%, -50%) scale(0.85); opacity: 0.6; }
            50% { transform: translate(-50%, -50%) scale(1.15); opacity: 1; }
        }
        
        @keyframes logoGlowPulse {
            0% { transform: scale(0.92); opacity: 0.95; }
            50% { transform: scale(1.12); opacity: 1; }
            100% { transform: scale(0.92); opacity: 0.95; }
        }
        
        @keyframes logoZoomEvaporate {
            0% { transform: scale(1.12); opacity: 1; }
            60% { transform: scale(1.12); opacity: 1; }
            100% { transform: scale(2); opacity: 0; }
        }
        
        /* Premium luxury exit animation */
        #page-loader.loader-exit {
            animation: loaderFadeOut 0.35s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        #page-loader.loader-exit .loader-slices {
            animation: slicesZoomDecay 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        #page-loader.loader-exit .slice-line {
            animation: sliceZoomEvaporate 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
            transform-origin: 960px 728px;
        }
        
        #page-loader.loader-exit .loader-center-glow {
            animation: glowDustFade 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        #page-loader.loader-exit .loader-logo {
            animation: logoZoomEvaporate 0.3s cubic-bezier(0.4, 0, 0.2, 1) forwards;
        }
        
        @keyframes loaderFadeOut {
            0% { opacity: 1; }
            100% { opacity: 0; }
        }
        
        @keyframes slicesZoomDecay {
            0% { 
                opacity: 1; 
                transform: scale(1);
                filter: blur(0);
            }
            60% {
                opacity: 0.7;
                transform: scale(1.3);
                filter: blur(2px);
            }
            100% { 
                opacity: 0; 
                transform: scale(1.6);
                filter: blur(6px);
            }
        }
        
        @keyframes sliceZoomFade {
            0% { 
                opacity: 1; 
                stroke-width: 3;
            }
            50% {
                opacity: 0.6;
                stroke-width: 5;
            }
            100% { 
                opacity: 0; 
                stroke-width: 8;
            }
        }
        
        @keyframes glowDustFade {
            0% { opacity: 1; transform: translate(-50%, -50%) scale(1); filter: blur(40px); }
            50% { opacity: 0.8; transform: translate(-50%, -50%) scale(1.5); filter: blur(50px); }
            100% { opacity: 0; transform: translate(-50%, -50%) scale(2); filter: blur(60px); }
        }
        
        @keyframes logoGoldenDust {
            0% { 
                transform: scale(1); 
                opacity: 1; 
                filter: drop-shadow(0 0 20px rgba(212,175,55,0.9)) blur(0); 
            }
            50% { 
                transform: scale(1.3); 
                opacity: 0.9; 
                filter: drop-shadow(0 0 40px rgba(212,175,55,1)) drop-shadow(0 0 60px rgba(255,225,123,0.8)) blur(1px); 
            }
            100% { 
                transform: scale(1.3); 
                opacity: 0; 
                filter: drop-shadow(0 0 80px rgba(212,175,55,0.3)) blur(8px); 
            }
        }
        /* ========== END LOADER STYLES ========== */
        
        /* ========== CUSTOMER AUTH STYLES ========== */
        .otp-input {
            width: 3.5rem;
            height: 4rem;
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            border: 2px solid #d1d5db;
            border-radius: 0.75rem;
            transition: all 0.2s;
        }
        .otp-input:focus {
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            outline: none;
        }
        .otp-input.filled {
            border-color: #10b981;
            background-color: #ecfdf5;
        }
        .auth-step {
            animation: authFadeIn 0.3s ease-out;
        }
        @keyframes authFadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .customer-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 500;
            color: #1e40af;
        }
        .account-dropdown {
            position: relative;
        }
        .account-dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: #1e293b;
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 0.5rem;
            padding: 0.5rem 0;
            min-width: 180px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
            z-index: 50;
        }
        .account-dropdown-menu a {
            display: block;
            padding: 0.5rem 1rem;
            color: #e5e7eb;
            transition: all 0.2s;
        }
        .account-dropdown-menu a:hover {
            background: rgba(212, 175, 55, 0.1);
            color: #D4AF37;
        }
        /* ========== END CUSTOMER AUTH STYLES ========== */
    </style>
    <script src="/assets/js/loader-controller.js"></script>
    <script src="/assets/js/nav-smartness.js"></script>
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
<body class="bg-navy-dark loader-active">
    <style>
        /* Instantly hide page content while loader displays */
        #page-loader { display: flex; }
        body.loader-active > *:not(#page-loader) {
            opacity: 0;
            pointer-events: none;
            visibility: hidden;
        }
    </style>
    <!-- Premium WebDaddy Loader with Sparkling Effects -->
    <div id="page-loader">
        <svg class="loader-slices" viewBox="0 0 1920 1080" preserveAspectRatio="xMidYMid slice">
            <defs>
                <linearGradient id="goldGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" style="stop-color:transparent"/>
                    <stop offset="15%" style="stop-color:rgba(212,175,55,0.25)"/>
                    <stop offset="50%" style="stop-color:#F5D669"/>
                    <stop offset="85%" style="stop-color:rgba(212,175,55,0.25)"/>
                    <stop offset="100%" style="stop-color:transparent"/>
                </linearGradient>
                <linearGradient id="goldGrad2" x1="100%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:transparent"/>
                    <stop offset="15%" style="stop-color:rgba(212,175,55,0.2)"/>
                    <stop offset="50%" style="stop-color:#FFE17B"/>
                    <stop offset="85%" style="stop-color:rgba(212,175,55,0.2)"/>
                    <stop offset="100%" style="stop-color:transparent"/>
                </linearGradient>
                <filter id="glow1" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="6" result="coloredBlur"/>
                    <feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
                <filter id="glow2" x="-50%" y="-50%" width="200%" height="200%">
                    <feGaussianBlur stdDeviation="10" result="coloredBlur"/>
                    <feMerge><feMergeNode in="coloredBlur"/><feMergeNode in="SourceGraphic"/></feMerge>
                </filter>
            </defs>
            <!-- Crown/W shape: 3 peaks with center highest - bottom points touch at center -->
            <line class="slice-line s1" x1="880" y1="1020" x2="520" y2="200" stroke="url(#goldGrad1)" stroke-width="2.5" filter="url(#glow2)"/>
            <line class="slice-line s2" x1="520" y1="200" x2="760" y2="880" stroke="url(#goldGrad2)" stroke-width="2.5" filter="url(#glow2)"/>
            <line class="slice-line s3" x1="760" y1="880" x2="960" y2="90" stroke="url(#goldGrad1)" stroke-width="3" filter="url(#glow2)"/>
            <line class="slice-line s4" x1="960" y1="90" x2="1160" y2="880" stroke="url(#goldGrad2)" stroke-width="3" filter="url(#glow2)"/>
            <line class="slice-line s5" x1="1160" y1="880" x2="1400" y2="200" stroke="url(#goldGrad1)" stroke-width="2.5" filter="url(#glow2)"/>
            <line class="slice-line s6" x1="1400" y1="200" x2="1040" y2="1020" stroke="url(#goldGrad2)" stroke-width="2.5" filter="url(#glow2)"/>
            <!-- Center X intersection at (960, 728) -->
            <line class="slice-line s7" x1="560" y1="160" x2="1180" y2="1040" stroke="url(#goldGrad1)" stroke-width="2" filter="url(#glow1)"/>
            <line class="slice-line s8" x1="1360" y1="160" x2="740" y2="1040" stroke="url(#goldGrad2)" stroke-width="2" filter="url(#glow1)"/>
        </svg>
        <div class="loader-center-glow"></div>
        <div class="loader-logo-container">
            <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy" class="loader-logo" fetchpriority="high">
        </div>
    </div>
    
    <?php 
    $activeNav = 'home';
    $showCart = true;
    include 'includes/layout/header.php'; 
    ?>

    <!-- Hero Section - Full 100vh with Stats -->
    <header class="relative bg-navy text-white min-h-[auto] sm:min-h-[calc(100vh-64px)] lg:h-[calc(100vh-64px)] flex flex-col justify-between overflow-hidden">
        <!-- Golden X Stripes Background Decoration - Same for Desktop & Mobile -->
        <svg class="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 1280 800" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
            <!-- Forward slash lines (/) -->
            <line x1="600" y1="0" x2="800" y2="800" stroke="#D4AF37" stroke-width="4" opacity="0.35"/>
            <line x1="300" y1="0" x2="500" y2="800" stroke="#D4AF37" stroke-width="3" opacity="0.25"/>
            <line x1="900" y1="0" x2="1100" y2="800" stroke="#D4AF37" stroke-width="3" opacity="0.25"/>
            
            <!-- Backslash lines (\) -->
            <line x1="800" y1="0" x2="600" y2="800" stroke="#D4AF37" stroke-width="4" opacity="0.35"/>
            <line x1="1100" y1="0" x2="900" y2="800" stroke="#D4AF37" stroke-width="3" opacity="0.25"/>
            <line x1="500" y1="0" x2="300" y2="800" stroke="#D4AF37" stroke-width="3" opacity="0.25"/>
        </svg>
        
        <!-- Main Content Area -->
        <div class="relative flex-1 flex flex-col md:flex-row items-center max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-1 gap-4 md:gap-8">
            <!-- Left Side (60%) - Text Content Only -->
            <div class="md:w-3/5 order-1 md:order-1">
                <h1 class="text-xl sm:text-2xl md:text-4xl lg:text-4xl xl:text-5xl font-extrabold mb-2 sm:mb-4 md:mb-6 leading-tight">
                    Launch Your Business Online with <span class="text-gold animate-word-swap" id="animatedWord">Confidence.</span>
                </h1>
                <p class="text-xs sm:text-sm md:text-base lg:text-xl text-gray-400 mb-3 sm:mb-4 md:mb-8 max-w-xl">
                    Professional website templates and digital tools built for African entrepreneurs. Get online in 24 hours, scale your business faster, dominate your market.
                </p>
                
                <!-- Desktop CTA Buttons - Hidden on mobile -->
                <div class="hidden md:flex flex-row gap-3 md:gap-4">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="btn-gold-shine flex items-center justify-center px-4 md:px-6 py-2.5 md:py-3 text-sm md:text-base font-semibold rounded-lg text-navy transition-all whitespace-nowrap">
                        <svg class="w-4 h-4 md:w-5 md:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                        </svg>
                        Browse Templates
                    </a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="flex items-center justify-center px-4 md:px-6 py-2.5 md:py-3 text-sm md:text-base font-semibold rounded-lg text-gold border-2 border-gold-shine hover:bg-gold hover:text-navy transition-all whitespace-nowrap">
                        <svg class="w-4 h-4 md:w-5 md:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                        </svg>
                        Explore Tools
                    </a>
                </div>
            </div>
                
            <!-- Right Side (40%) - Animated Website Mockup Slideshow with Portfolio Images -->
            <div class="md:w-2/5 hidden md:block order-2 md:order-2" x-data="{ 
                    currentSlide: 0,
                    slides: [
                        { image: '/assets/images/mockups/viralcuts.jpg', title: 'Viralcuts' },
                        { image: '/assets/images/mockups/jasper-ai.jpg', title: 'Jasper AI' },
                        { image: '/assets/images/mockups/webflow.jpg', title: 'Webflow' },
                        { image: '/assets/images/mockups/intercom.jpg', title: 'Intercom' },
                        { image: '/assets/images/mockups/glide-apps.jpg', title: 'Glide Apps' },
                        { image: '/assets/images/mockups/notion.jpg', title: 'Notion' },
                        { image: '/assets/images/mockups/runway.jpg', title: 'Runway' }
                    ],
                    init() {
                        setInterval(() => { this.currentSlide = (this.currentSlide + 1) % this.slides.length }, 4000)
                    }
                }">
                    <div class="relative group">
                        <!-- Subtle glow effect behind the laptop -->
                        <div class="absolute -inset-4 bg-gradient-to-r from-gold/8 via-gold/4 to-gold/8 rounded-2xl blur-2xl opacity-40 group-hover:opacity-60 transition-opacity duration-500"></div>
                        <div class="absolute -inset-2 bg-gradient-to-br from-gold/5 via-transparent to-gold/5 rounded-xl opacity-30"></div>
                        
                        <div class="relative bg-navy-light rounded-xl border border-gold/25 shadow-2xl shadow-gold/5 overflow-hidden">
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
                            <div class="aspect-[16/10] relative overflow-hidden bg-gradient-to-br from-gray-800 via-navy to-gray-900">
                                <img src="/assets/images/mockups/viralcuts.jpg" alt="Viralcuts" loading="eager" fetchpriority="high" class="absolute inset-0 w-full h-full object-cover object-top transition-opacity duration-700" :class="currentSlide === 0 ? 'opacity-100' : 'opacity-0'" onerror="this.classList.add('image-broken'); this.style.display='none';" onload="this.classList.remove('image-broken');">
                                <template x-for="(slide, index) in slides" :key="index">
                                    <div class="absolute inset-0 transition-all duration-700 ease-in-out"
                                         :class="currentSlide === index ? 'opacity-100 scale-100' : 'opacity-0 scale-105'"
                                         x-show="index > 0 || currentSlide !== 0">
                                        <img :src="slide.image" :alt="slide.title" loading="lazy" class="w-full h-full object-cover object-top" onerror="this.classList.add('image-broken'); this.style.display='none';" onload="this.classList.remove('image-broken');">
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
                        <!-- Subtle glow effects -->
                        <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-gold/8 rounded-full blur-2xl animate-pulse opacity-60"></div>
                        <div class="absolute -top-4 -left-4 w-28 h-28 bg-gold/5 rounded-full blur-3xl opacity-40"></div>
                        <div class="absolute top-1/2 -right-6 w-16 h-32 bg-gold/6 rounded-full blur-xl transform -translate-y-1/2 opacity-50"></div>
                    </div>
                </div>
                
            <!-- Mobile Laptop Mockup Slider - Shows on mobile only -->
            <div class="md:hidden w-full order-2 md:order-2" x-data="{ 
                    currentSlide: 0,
                    slides: [
                        { image: '/assets/images/mockups/viralcuts.jpg', title: 'Viralcuts' },
                        { image: '/assets/images/mockups/jasper-ai.jpg', title: 'Jasper AI' },
                        { image: '/assets/images/mockups/webflow.jpg', title: 'Webflow' },
                        { image: '/assets/images/mockups/intercom.jpg', title: 'Intercom' },
                        { image: '/assets/images/mockups/glide-apps.jpg', title: 'Glide Apps' },
                        { image: '/assets/images/mockups/notion.jpg', title: 'Notion' },
                        { image: '/assets/images/mockups/runway.jpg', title: 'Runway' }
                    ],
                    init() {
                        setInterval(() => { this.currentSlide = (this.currentSlide + 1) % this.slides.length }, 4000)
                    }
                }">
                    <div class="relative mx-auto max-w-sm">
                        <!-- Subtle glow effect -->
                        <div class="absolute -inset-2 bg-gradient-to-r from-gold/8 via-gold/3 to-gold/8 rounded-xl blur-lg opacity-50"></div>
                        
                        <div class="relative bg-navy-light rounded-lg border border-gold/20 shadow-lg shadow-gold/3 overflow-hidden">
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
                            <div class="aspect-[16/10] relative overflow-hidden bg-gradient-to-br from-gray-800 via-navy to-gray-900">
                                <img src="/assets/images/mockups/viralcuts.jpg" alt="Viralcuts" loading="eager" fetchpriority="high" class="absolute inset-0 w-full h-full object-cover object-top transition-opacity duration-700" :class="currentSlide === 0 ? 'opacity-100' : 'opacity-0'" onerror="this.classList.add('image-broken'); this.style.display='none';" onload="this.classList.remove('image-broken');">
                                <template x-for="(slide, index) in slides" :key="index">
                                    <div class="absolute inset-0 transition-all duration-700"
                                         :class="currentSlide === index ? 'opacity-100' : 'opacity-0'"
                                         x-show="index > 0 || currentSlide !== 0">
                                        <img :src="slide.image" :alt="slide.title" loading="lazy" class="w-full h-full object-cover object-top" onerror="this.classList.add('image-broken'); this.style.display='none';" onload="this.classList.remove('image-broken');">
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
            
            <!-- Mobile CTA Buttons - Below Mockup on Mobile -->
            <div class="md:hidden w-full order-3 flex flex-col gap-2 sm:gap-3">
                <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="btn-gold-shine flex items-center justify-center px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm font-semibold rounded-lg text-navy transition-all">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
                    </svg>
                    Browse Templates
                </a>
                <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" class="flex items-center justify-center px-4 sm:px-5 py-2 sm:py-2.5 text-xs sm:text-sm font-semibold rounded-lg text-gold border-2 border-gold hover:bg-gold hover:text-navy transition-all">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    Explore Tools
                </a>
            </div>
        </div>
        
        <!-- Stats Bar - Inside Hero for 100vh -->
        <div class="relative py-4 lg:py-6 bg-navy/50 backdrop-blur-sm mb-8 lg:mb-12">
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
        isNearHeaderOrFooter: false,
        init() {
            this.showMessage = true;
            setInterval(() => {
                this.showMessage = false;
                setTimeout(() => {
                    this.currentIndex = (this.currentIndex + 1) % this.messages.length;
                    this.showMessage = true;
                }, 9000);
            }, 15000);
            
            // Detect when user is near header or footer
            window.addEventListener('scroll', () => {
                const scrollPos = window.scrollY;
                const windowHeight = window.innerHeight;
                const docHeight = document.documentElement.scrollHeight;
                const footerStart = docHeight - (windowHeight * 1.5); // Hide when within 1.5x viewport height of footer
                
                // Hide message when near header (top 300px) or footer
                this.isNearHeaderOrFooter = scrollPos < 300 || scrollPos > footerStart;
            });
            
            // Check initial position
            const scrollPos = window.scrollY;
            const windowHeight = window.innerHeight;
            const docHeight = document.documentElement.scrollHeight;
            const footerStart = docHeight - (windowHeight * 1.5);
            this.isNearHeaderOrFooter = scrollPos < 300 || scrollPos > footerStart;
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
           x-transition:enter="transition ease-out duration-[2000ms]"
           x-transition:enter-start="opacity-0 -translate-x-4"
           x-transition:enter-end="opacity-100 translate-x-0"
           x-transition:leave="transition ease-in duration-[1000ms]"
           x-transition:leave-start="opacity-100 translate-x-0"
           x-transition:leave-end="opacity-0 -translate-x-full"
           class="btn-gold-shine flex items-center gap-2 rounded-r-full pl-3 pr-3 py-2"
           aria-label="Chat on WhatsApp">
            <!-- Icon -->
            <svg class="w-10 h-10 text-navy-dark" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
            </svg>
            
            <!-- Sliding Message (Hidden near header/footer) -->
            <div x-show="showMessage && !isNearHeaderOrFooter" 
                 class="text-navy-dark font-semibold text-sm whitespace-nowrap pr-2 overflow-hidden">
                <span x-text="messages[currentIndex]"></span>
            </div>
        </a>
    </div>

    <!-- Products Section (Templates & Tools) -->
    <section class="py-6 md:py-12 bg-navy-dark" id="products">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- View Toggle Tabs with Gold Underline -->
            <div class="flex justify-center mb-6 sm:mb-8">
                <div class="inline-flex gap-8 sm:gap-16" role="group">
                    <a href="?view=templates<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="pb-2 text-sm sm:text-base font-semibold transition-colors border-b-2 <?php echo $currentView === 'templates' ? 'text-white border-gold' : 'text-gray-500 border-transparent hover:text-gray-300'; ?>" style="background: none !important;">
                        Website Templates
                    </a>
                    <a href="?view=tools<?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>#products" 
                       class="pb-2 text-sm sm:text-base font-semibold transition-colors border-b-2 <?php echo $currentView === 'tools' ? 'text-white border-gold' : 'text-gray-500 border-transparent hover:text-gray-300'; ?>" style="background: none !important;">
                        Working Tools
                    </a>
                </div>
            </div>

            <!-- Search and Filter Container -->
            <div class="max-w-4xl mx-auto mb-4 md:mb-8">
                <div class="flex flex-col md:flex-row gap-2 md:gap-4">
                    <!-- Search Input -->
                    <div class="flex-1">
                        <div class="relative">
                            <input type="text" 
                                   id="search-input"
                                   placeholder="Search <?php echo $currentView === 'templates' ? 'templates' : 'tools'; ?>..." 
                                   class="w-full px-2 py-1.5 md:px-4 md:py-2.5 pl-8 md:pl-10 pr-7 md:pr-10 bg-navy-light border border-gray-700 rounded-lg text-xs md:text-sm text-white placeholder-gray-500 focus:border-gold focus:ring-1 focus:ring-gold transition-all">
                            <svg class="w-4 h-4 md:w-5 md:h-5 absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <button id="clear-search" style="display: none;" 
                                    class="absolute right-2 md:right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300 transition-colors z-10"
                                    title="Clear search">
                                <svg class="w-4 h-4 md:w-5 md:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                            <div id="search-loading" style="display: none;" class="absolute right-8 md:right-10 top-1/2 -translate-y-1/2">
                                <svg class="animate-spin h-4 w-4 md:h-5 md:w-5 text-gold" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="w-full md:w-auto">
                        <div class="relative">
                            <select id="category-filter" 
                                    class="w-full px-2 py-1.5 md:px-4 md:py-2.5 pl-3 md:pl-4 pr-7 md:pr-10 bg-navy-light border border-gray-700 rounded-lg text-xs md:text-sm text-white font-medium cursor-pointer focus:border-gold focus:ring-1 focus:ring-gold transition-all appearance-none">
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
                            <svg class="w-4 h-4 md:w-5 md:h-5 absolute right-2 md:right-3 top-1/2 -translate-y-1/2 text-gray-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-3 md:gap-6" data-templates-grid>
                <?php foreach ($templates as $template): ?>
                <div class="group" 
                     data-template
                     data-template-name="<?php echo htmlspecialchars($template['name']); ?>"
                     data-template-category="<?php echo htmlspecialchars($template['category']); ?>"
                     data-template-price="<?php echo htmlspecialchars($template['price']); ?>">
                    <div class="bg-navy-light rounded-lg md:rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:border-gold/30 hover:-translate-y-1 h-full flex flex-col">
                        <div class="relative overflow-hidden h-32 md:h-48 bg-navy">
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
                        <div class="p-3 md:p-4 flex-1 flex flex-col">
                            <div class="flex justify-between items-start mb-1 md:mb-2">
                                <h3 class="text-sm md:text-base font-bold text-white flex-1 pr-2 line-clamp-1"><?php echo htmlspecialchars($template['name']); ?></h3>
                                <span class="inline-flex items-center px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium bg-gold/20 text-gold shrink-0">
                                    <?php echo htmlspecialchars($template['category']); ?>
                                </span>
                            </div>
                            <p class="text-gray-400 text-xs md:text-sm mb-2 md:mb-4 line-clamp-2 min-h-[28px] md:min-h-[40px] flex-1"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                            <div class="flex items-center justify-between pt-2 md:pt-3 border-t border-gray-700/50 mt-auto">
                                <div class="flex flex-col">
                                    <span class="text-[8px] md:text-[10px] text-gray-500 uppercase tracking-wider font-medium">PRICE</span>
                                    <span class="text-base md:text-lg font-bold text-gold"><?php echo formatCurrency($template['price']); ?></span>
                                </div>
                                <div class="flex gap-1.5 md:gap-2">
                                    <a href="<?php echo getTemplateUrl($template, $affiliateCode); ?>" 
                                       class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 border border-gray-600 text-[10px] md:text-xs font-semibold rounded-md md:rounded-lg text-gray-300 bg-transparent hover:bg-navy hover:border-gray-500 transition-colors whitespace-nowrap">
                                        Details
                                    </a>
                                    <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '<?php echo addslashes($template['name']); ?>', this)" 
                                       class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 border border-transparent text-[10px] md:text-xs font-semibold rounded-md md:rounded-lg text-navy bg-gold hover:bg-gold-500 transition-colors whitespace-nowrap disabled:opacity-50 disabled:cursor-not-allowed">
                                        <svg class="w-3 h-3 md:w-3.5 md:h-3.5 mr-0.5 md:mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                        </svg>
                                        Add
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
            <div class="mt-6 md:mt-12 flex flex-col items-center gap-2 md:gap-4">
                <nav class="flex items-center gap-1 md:gap-2">
                    <?php
                    $paginationParams = ['view' => $currentView];
                    if ($affiliateCode) $paginationParams['aff'] = $affiliateCode;
                    if (isset($_GET['category'])) $paginationParams['category'] = $_GET['category'];
                    ?>
                    
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page - 1])); ?>#products" 
                       class="inline-flex items-center px-2.5 md:px-4 py-1.5 md:py-2.5 text-xs md:text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-md md:rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        <svg class="w-3 h-3 md:w-4 md:h-4 mr-1 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                       class="<?php echo $i === $page ? 'bg-gold text-navy font-bold shadow-md shadow-gold/20' : 'bg-navy-light text-gray-300 border border-gray-600 hover:bg-gold/20 hover:text-gold hover:border-gold/50'; ?> inline-flex items-center justify-center w-8 h-8 md:w-10 md:h-10 text-xs md:text-sm font-semibold rounded-md md:rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page + 1])); ?>#products" 
                       class="inline-flex items-center px-2.5 md:px-4 py-1.5 md:py-2.5 text-xs md:text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-md md:rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        Next
                        <svg class="w-3 h-3 md:w-4 md:h-4 ml-1 md:ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-xs md:text-sm font-medium text-gray-500">
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
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-3 md:gap-6">
                <?php foreach ($tools as $tool): ?>
                <div class="tool-card group bg-navy-light rounded-lg md:rounded-xl shadow-md overflow-hidden border border-gray-700/50 transition-all duration-300 hover:shadow-xl hover:border-gold/30 hover:-translate-y-1 h-full flex flex-col" 
                     data-tool-id="<?php echo $tool['id']; ?>">
                    <div class="relative overflow-hidden h-28 md:h-40 bg-navy">
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
                    <div class="p-3 md:p-4 flex-1 flex flex-col">
                        <div class="flex justify-between items-start mb-1 md:mb-2">
                            <h3 class="text-xs md:text-sm font-bold text-white flex-1 pr-2 line-clamp-1"><?php echo htmlspecialchars($tool['name']); ?></h3>
                            <?php if (!empty($tool['category'])): ?>
                            <span class="inline-flex items-center px-1.5 md:px-2 py-0.5 rounded-full text-[10px] md:text-xs font-medium bg-gold/20 text-gold shrink-0">
                                <?php echo htmlspecialchars($tool['category']); ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-gray-400 text-[11px] md:text-xs mb-2 md:mb-3 line-clamp-2 min-h-[24px] md:min-h-[32px] flex-1"><?php echo htmlspecialchars($tool['short_description'] ?? ''); ?></p>
                        <div class="flex items-center justify-between pt-2 md:pt-3 border-t border-gray-700/50 mt-auto">
                            <div class="flex flex-col">
                                <span class="text-[8px] md:text-[10px] text-gray-500 uppercase tracking-wider font-medium">PRICE</span>
                                <span class="text-base md:text-lg font-extrabold text-gold"><?php echo formatCurrency($tool['price']); ?></span>
                            </div>
                            <button onclick="openToolModal(<?php echo $tool['id']; ?>)" 
                               class="inline-flex items-center justify-center px-2.5 md:px-4 py-1.5 md:py-2 border border-gray-600 text-[10px] md:text-xs font-semibold rounded-md md:rounded-lg text-gray-300 bg-transparent hover:bg-navy hover:border-gray-500 transition-all whitespace-nowrap">
                                <svg class="w-3 h-3 md:w-3.5 md:h-3.5 mr-1 md:mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Details
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination for tools -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-6 md:mt-12 flex flex-col items-center gap-2 md:gap-4">
                <nav class="flex items-center gap-1 md:gap-2">
                    <?php
                    $paginationParams = ['view' => 'tools'];
                    if ($affiliateCode) $paginationParams['aff'] = $affiliateCode;
                    if (isset($_GET['category'])) $paginationParams['category'] = $_GET['category'];
                    ?>
                    
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page - 1])); ?>#products" 
                       class="inline-flex items-center px-2.5 md:px-4 py-1.5 md:py-2.5 text-xs md:text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-md md:rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        <svg class="w-3 h-3 md:w-4 md:h-4 mr-1 md:mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                       class="<?php echo $i === $page ? 'bg-gold text-navy font-bold shadow-md shadow-gold/20' : 'bg-navy-light text-gray-300 border border-gray-600 hover:bg-gold/20 hover:text-gold hover:border-gold/50'; ?> inline-flex items-center justify-center w-8 h-8 md:w-10 md:h-10 text-xs md:text-sm font-semibold rounded-md md:rounded-lg transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(array_merge($paginationParams, ['page' => $page + 1])); ?>#products" 
                       class="inline-flex items-center px-2.5 md:px-4 py-1.5 md:py-2.5 text-xs md:text-sm font-semibold text-white bg-navy-light border border-gray-600 rounded-md md:rounded-lg hover:bg-gold hover:text-navy hover:border-gold transition-all">
                        Next
                        <svg class="w-3 h-3 md:w-4 md:h-4 ml-1 md:ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-xs md:text-sm font-medium text-gray-500">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> <span class="mx-1">•</span> <?php echo $totalTools; ?> products
                </p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="py-20 bg-gradient-to-br from-slate-900 via-blue-900/5 to-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-12">
            <div class="max-w-3xl mx-auto text-center">
                <span class="inline-block px-4 py-2 bg-blue-500/10 text-blue-400 text-sm font-semibold rounded-full mb-4 border border-blue-500/20">CUSTOMER STORIES</span>
                <h2 class="text-4xl sm:text-5xl font-bold text-white mb-6 tracking-tight">Trusted by Industry Leaders</h2>
                <p class="text-xl text-gray-400 max-w-2xl mx-auto">Join hundreds of successful entrepreneurs who transformed their business with our platform</p>
            </div>
        </div>
        
        <div class="carousel-wrapper mx-auto px-4" style="max-width: 1200px;">
            <div id="testimonialCarousel" class="carousel-container" style="display: flex; gap: 24px; overflow: hidden; scroll-behavior: auto; -webkit-overflow-scrolling: touch;">
                <!-- Items will be cloned by JavaScript for infinite scroll -->
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-gradient-to-br from-slate-800/80 to-slate-900/40 rounded-2xl shadow-lg p-8 border border-blue-500/20 h-full flex flex-col hover:border-blue-500/40 transition-all duration-300">
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
                    <div class="bg-gradient-to-br from-slate-800/80 to-slate-900/40 rounded-2xl shadow-lg p-8 border border-blue-500/20 h-full flex flex-col hover:border-blue-500/40 transition-all duration-300">
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
                    <div class="bg-gradient-to-br from-slate-800/80 to-slate-900/40 rounded-2xl shadow-lg p-8 border border-blue-500/20 h-full flex flex-col hover:border-blue-500/40 transition-all duration-300">
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
                
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-gradient-to-br from-slate-800/80 to-slate-900/40 rounded-2xl shadow-lg p-8 border border-blue-500/20 h-full flex flex-col hover:border-blue-500/40 transition-all duration-300">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-300 mb-6 flex-grow">"Customer support is amazing! Had a question about customization and got help within minutes. Will definitely recommend!"</p>
                        <div>
                            <div class="font-semibold text-white">Chioma Okafor</div>
                            <div class="text-sm text-gray-500">E-commerce Store, Ibadan</div>
                        </div>
                    </div>
                </div>
                
                <div class="carousel-item original-item" style="flex: 0 0 calc(50% - 12px); min-width: 280px;">
                    <div class="bg-gradient-to-br from-slate-800/80 to-slate-900/40 rounded-2xl shadow-lg p-8 border border-blue-500/20 h-full flex flex-col hover:border-blue-500/40 transition-all duration-300">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-300 mb-6 flex-grow">"Best investment for my consulting business! The templates are sleek, modern, and my conversion rate increased by 40%."</p>
                        <div>
                            <div class="font-semibold text-white">Dr. Tunde Ajayi</div>
                            <div class="text-sm text-gray-500">Business Consulting, Abeokuta</div>
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
                #testimonialCarousel {
                    user-select: none;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                }
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
                const isMobile = window.innerWidth <= 768;
                
                function startAutoScroll() {
                    clearInterval(autoScrollId);
                    let speed = isMobile ? 2.5 : 0.8; // faster on mobile, smooth on desktop
                    
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
                
                // Touch support - faster scroll momentum on mobile
                carousel.addEventListener('touchstart', (e) => {
                    startX = e.touches[0].pageX - carousel.offsetLeft;
                    scrollLeft = carousel.scrollLeft;
                    clearInterval(autoScrollId);
                }, false);
                
                carousel.addEventListener('touchmove', (e) => {
                    const x = e.touches[0].pageX - carousel.offsetLeft;
                    const walk = (x - startX) * (isMobile ? 2.5 : 1); // faster drag on mobile
                    carousel.scrollLeft = scrollLeft - walk;
                }, false);
                
                carousel.addEventListener('touchend', () => {
                    startAutoScroll();
                }, false);
            })();
        </script>
    </section>

    <!-- FAQ Section -->
    <section class="py-20 bg-gradient-to-br from-blue-950/40 via-slate-950 to-blue-950/40" id="faq">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="inline-block px-4 py-2 bg-blue-500/10 text-blue-400 text-sm font-semibold rounded-full mb-4 border border-blue-500/20">HELP CENTER</span>
                <h2 class="text-4xl sm:text-5xl font-bold text-white mb-6 tracking-tight">Common Questions Answered</h2>
                <p class="text-xl text-gray-400 max-w-2xl mx-auto">Find answers to frequently asked questions about our services</p>
            </div>
            <div class="space-y-4" x-data="{ selected: 1 }">
                <div class="bg-gradient-to-r from-slate-800/60 to-slate-900/30 rounded-xl shadow-md border border-blue-500/15 overflow-hidden hover:border-blue-500/30 transition-all duration-300">
                    <button @click="selected = selected === 1 ? null : 1" class="w-full text-left px-6 py-4 font-semibold text-white flex justify-between items-center hover:bg-blue-500/5 transition-colors">
                        <span>What's included in the price?</span>
                        <svg class="w-5 h-5 text-gold transform transition-transform" :class="selected === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 1" x-collapse class="px-6 pb-4 text-gray-300 border-t border-gray-700/50">
                        Complete website template, premium domain name, hosting setup, and full customization access. You get everything needed to launch your business online.
                    </div>
                </div>
                <div class="bg-gradient-to-r from-slate-800/60 to-slate-900/30 rounded-xl shadow-md border border-blue-500/15 overflow-hidden hover:border-blue-500/30 transition-all duration-300">
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
                <div class="bg-gradient-to-r from-slate-800/60 to-slate-900/30 rounded-xl shadow-md border border-blue-500/15 overflow-hidden hover:border-blue-500/30 transition-all duration-300">
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


    <?php include 'includes/layout/footer.php'; ?>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                const href = this.getAttribute('href');
                // Skip if href is just "#" (no target selector)
                if (!href || href === '#') return;
                e.preventDefault();
                const target = document.querySelector(href);
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

        // Professional word animations with period included
        const wordElement = document.getElementById('animatedWord');
        if (wordElement) {
            const words = ['Confidence.', 'Growth.', 'Impact.', 'Excellence.', 'Success.', 'Mastery.'];
            const exitAnimations = ['anim-exit-1', 'anim-exit-2', 'anim-exit-3', 'anim-exit-4'];
            const enterAnimations = ['anim-enter-1', 'anim-enter-2', 'anim-enter-3', 'anim-enter-4', 'anim-enter-5'];
            
            let currentIndex = 0;
            let animationSequence = [];
            
            // Pregenerate animation sequence to ensure variety
            function generateSequence() {
                animationSequence = [];
                for (let i = 0; i < 12; i++) {
                    animationSequence.push({
                        exit: exitAnimations[Math.floor(Math.random() * exitAnimations.length)],
                        enter: enterAnimations[Math.floor(Math.random() * enterAnimations.length)]
                    });
                }
            }
            
            generateSequence();
            
            function animateWordChange() {
                const nextIndex = (currentIndex + 1) % words.length;
                const nextWord = words[nextIndex];
                const animSeq = animationSequence[currentIndex];
                
                // Apply exit animation
                wordElement.classList.add(animSeq.exit);
                
                // Calculate exit duration
                const exitDuration = animSeq.exit === 'anim-exit-1' ? 1200 : 
                                    animSeq.exit === 'anim-exit-2' ? 1000 : 
                                    animSeq.exit === 'anim-exit-3' ? 1300 : 1400;
                
                setTimeout(() => {
                    wordElement.textContent = nextWord;
                    wordElement.classList.remove(...exitAnimations);
                    
                    // Apply enter animation
                    wordElement.classList.add(animSeq.enter);
                    currentIndex = nextIndex;
                    
                    // Schedule next transition
                    const enterDuration = animSeq.enter === 'anim-enter-1' ? 1200 : 
                                        animSeq.enter === 'anim-enter-2' ? 1600 : 
                                        animSeq.enter === 'anim-enter-3' ? 1100 : 
                                        animSeq.enter === 'anim-enter-4' ? 1500 : 1800;
                    
                    setTimeout(animateWordChange, 3500);
                }, exitDuration);
            }
            
            wordElement.textContent = words[0];
            setTimeout(animateWordChange, 2500);
        }
    </script>
    
    <!-- Premium Loader Controller -->
    <script>
        (function() {
            const LOADER_SHOWN_KEY = 'webdaddy_loader_shown';
            const loader = document.getElementById('page-loader');
            if (!loader) return;

            // Check if loader already shown
            const loaderShown = sessionStorage.getItem(LOADER_SHOWN_KEY);
            
            // Skip loader on back button or repeat visits
            if (loaderShown === 'true' || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
                document.body.classList.remove('loader-active');
                loader.style.display = 'none';
                return;
            }

            // Mark loader as shown
            sessionStorage.setItem(LOADER_SHOWN_KEY, 'true');

            // Dismiss loader after 1.5 seconds
            let loaderDismissed = false;
            function dismissLoader() {
                if (loaderDismissed) return;
                loaderDismissed = true;
                
                loader.classList.add('loader-exit');
                document.body.classList.remove('loader-active');
                
                setTimeout(() => {
                    loader.classList.add('loader-hidden');
                    loader.remove();
                }, 300);
            }

            setTimeout(dismissLoader, 1500);
        })();
        
        // Global image error handler - hide broken images completely
        document.addEventListener('error', function(event) {
            if (event.target && event.target.tagName === 'IMG') {
                event.target.classList.add('image-broken');
                event.target.style.display = 'none';
                event.target.style.visibility = 'hidden';
            }
        }, true);
    </script>
    
    <!-- Customer Auth Module -->
    <script src="/assets/js/customer-auth.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.store('customer', {
                data: null,
                loading: true,
                async init() {
                    this.loading = true;
                    this.data = await checkCustomerSession();
                    this.loading = false;
                }
            });
            
            Alpine.data('customerNav', () => ({
                customer: null,
                async init() {
                    this.customer = await checkCustomerSession();
                }
            }));
        });
    </script>
    
    <?php include __DIR__ . '/includes/floating-cart-widget.php'; ?>
</body>
</html>
