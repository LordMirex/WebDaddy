<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/cart.php';

// Set cache headers - no caching for dynamic pages
header('Cache-Control: no-cache, no-store, must-revalidate', false);
header('Pragma: no-cache', false);
header('Expires: 0', false);

startSecureSession();
handleAffiliateTracking();
handleUserReferralTracking();

// Accept either slug or ID for backward compatibility
$slug = $_GET['slug'] ?? null;
$templateId = isset($_GET['id']) ? (int)$_GET['id'] : null;

// Try to get template by slug first (from URL path)
if (!empty($slug)) {
    $template = getTemplateBySlug($slug);
} elseif ($templateId) {
    // Fallback to ID (for backward compatibility)
    $template = getTemplateById($templateId);
    
    // Redirect to slug URL for SEO (301 permanent redirect)
    if ($template && !empty($template['slug'])) {
        $affiliateParam = isset($_GET['aff']) ? '?aff=' . urlencode($_GET['aff']) : '';
        header('Location: /' . $template['slug'] . $affiliateParam, true, 301);
        exit;
    }
} else {
    header('Location: /');
    exit;
}

if (!$template) {
    // Template not found - show 404
    http_response_code(404);
    header('Location: /?error=template_not_found');
    exit;
}

// At this point, $template is loaded successfully
trackPageVisit($_SERVER['REQUEST_URI'], 'Template: ' . $template['name']);
trackTemplateView($template['id']);

$availableDomains = getAvailableDomains($template['id']);
$affiliateCode = getAffiliateCode();
$cartCount = getCartCount();
$features = $template['features'] ? explode(',', $template['features']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo htmlspecialchars($template['name']); ?> - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars(!empty($template['description']) ? $template['description'] : ($template['name'] . ' - Professional website template from WebDaddy Empire')); ?>">
    <link rel="canonical" href="<?php echo SITE_URL . '/' . $template['slug']; ?>">
    
    <!-- SEO: Enhanced Meta Tags -->
    <meta name="keywords" content="<?php echo !empty($template['seo_keywords']) ? htmlspecialchars($template['seo_keywords']) : (htmlspecialchars($template['category'] ?? 'website template') . ', website design, template, ' . htmlspecialchars($template['name']) . ', Nigeria'); ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    
    <!-- Open Graph Tags (Social Sharing) -->
    <?php
    // Use banner image for social preview (larger, more impactful), fallback to thumbnail
    $ogImage = !empty($template['banner_url']) ? $template['banner_url'] : (!empty($template['thumbnail_url']) ? $template['thumbnail_url'] : '/assets/images/og-image.png');
    // Make image URL absolute for social sharing (required for Facebook, WhatsApp, etc.)
    if (!empty($ogImage) && strpos($ogImage, 'http') !== 0) {
        $ogImage = SITE_URL . $ogImage;
    }
    ?>
    <meta property="og:url" content="<?php echo SITE_URL . '/' . $template['slug']; ?>">
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?php echo htmlspecialchars($template['name']); ?> - <?php echo SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars(!empty($template['description']) ? $template['description'] : ($template['name'] . ' - Professional website template from WebDaddy Empire')); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($template['name']); ?> - Website Template Preview">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:locale" content="en_NG">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($template['name']); ?> - <?php echo SITE_NAME; ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars(!empty($template['description']) ? $template['description'] : ($template['name'] . ' - Professional website template from WebDaddy Empire')); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($template['name']); ?> - Website Template Preview">
    
    <!-- Structured Data (Schema.org) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Product",
      "name": "<?php echo htmlspecialchars($template['name']); ?>",
      "description": "<?php echo htmlspecialchars(!empty($template['description']) ? $template['description'] : ($template['name'] . ' - Professional website template from WebDaddy Empire')); ?>",
      "image": "<?php echo htmlspecialchars($ogImage); ?>",
      "url": "<?php echo SITE_URL . '/' . $template['slug']; ?>",
      "offers": {
        "@type": "Offer",
        "price": "<?php echo $template['price']; ?>",
        "priceCurrency": "NGN",
        "availability": "https://schema.org/InStock",
        "url": "<?php echo SITE_URL . '/' . $template['slug']; ?>"
      },
      "brand": {
        "@type": "Brand",
        "name": "<?php echo SITE_NAME; ?>"
      },
      "category": "<?php echo htmlspecialchars($template['category'] ?? 'Website Template'); ?>"
    }
    </script>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    
    <!-- Premium Fonts - Inter and Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Premium UI/UX Styles -->
    <link rel="stylesheet" href="/assets/css/premium.css">
    
    <script src="https://cdn.tailwindcss.com?v=<?php echo time(); ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/csp@3/dist/cdn.min.js"></script>
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
    </style>
    <script src="/assets/js/forms.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/cart-and-tools.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/share.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/lazy-load.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/performance.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/video-preloader.js?v=<?php echo time(); ?>" defer></script>
    <script src="/assets/js/video-modal.js?v=<?php echo time(); ?>" defer></script>
</head>
<body class="bg-gray-900">
    <!-- Navigation -->
    <nav id="mainNav" class="bg-gray-800 shadow-sm sticky top-0 z-50" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-14 mr-3" onerror="this.style.display='none'">
                        <span class="text-xl font-bold text-primary-900 dark:text-white"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-100 hover:text-primary-400 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Templates
                    </a>
                    <a href="#" id="cart-button" onclick="toggleCartDrawer(); return false;" class="relative text-gray-100 hover:text-primary-400 font-medium transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                    </a>
                </div>
                <div class="md:hidden flex items-center">
                    <button @click="open = !open" class="text-gray-100 hover:text-primary-400 focus:outline-none">
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
        <div x-show="open" @click.away="open = false" class="md:hidden bg-gray-800 border-t border-gray-700" style="display: none;">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="/" class="block px-3 py-2 rounded-md text-gray-100 hover:bg-gray-800 font-medium">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Templates
                </a>
            </div>
        </div>
    </nav>

    <!-- Smart WhatsApp Button with Message Carousel -->
    <div x-data="{ 
        messages: [
            'Need help with this template?',
            'Let\'s bring your idea to life',
            '24/7 support on WhatsApp',
            'Custom setup available'
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
            return 'Hi! I\'m viewing <?php echo htmlspecialchars($template['name']); ?> template and need help.';
        }
    }" 
    class="fixed bottom-4 left-0 z-50">
        <!-- WhatsApp Icon Button -->
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

    <!-- Hero Section -->
    <header class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-12 sm:py-16">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl">
                <span class="inline-block bg-gray-800/10 text-white px-4 py-2 rounded-lg text-sm font-semibold mb-4"><?php echo htmlspecialchars($template['category']); ?></span>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold mb-4"><?php echo htmlspecialchars($template['name']); ?></h1>
                <p class="text-lg sm:text-xl text-white/90 mb-6"><?php echo htmlspecialchars($template['description']); ?></p>
                <div class="flex items-center gap-4">
                    <div>
                        <div class="text-sm text-white/70 mb-1">Starting at</div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold"><?php echo formatCurrency($template['price']); ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
            <div class="lg:col-span-2">
                <div class="mb-8">
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($template['name']); ?>" 
                         class="w-full rounded-xl shadow-2xl"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                </div>

                <?php 
                // Determine media type - use media_type column or fallback to legacy detection
                $mediaType = $template['media_type'] ?? 'banner'; // Default to banner if not set
                $mediaUrl = null;
                $youtubeId = null;
                
                if ($mediaType === 'demo_url' && !empty($template['demo_url'])) {
                    $mediaUrl = $template['demo_url'];
                } elseif ($mediaType === 'video' && !empty($template['demo_video_url'])) {
                    $mediaUrl = $template['demo_video_url'];
                } elseif ($mediaType === 'youtube' && !empty($template['preview_youtube'])) {
                    $youtubeId = $template['preview_youtube'];
                }
                
                // Only show preview section if media type is demo_url, video, or youtube
                if (($mediaType === 'demo_url' && $mediaUrl) || ($mediaType === 'video' && $mediaUrl) || ($mediaType === 'youtube' && $youtubeId)): 
                ?>
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 mb-8 overflow-hidden">
                    <div class="bg-gray-900 border-b border-gray-700 p-4 sm:p-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <h5 class="text-lg font-bold text-white flex items-center">
                            <?php if ($mediaType === 'youtube'): ?>
                            <svg class="w-5 h-5 mr-2 text-red-500" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.8 3.5 12 3.5 12 3.5s-7.8 0-9.5.5c-1 .3-1.7 1.1-2 2.1C0 7.9 0 12 0 12s0 4.1.5 5.8c.3 1 1 1.8 2 2.1 1.7.6 9.5.6 9.5.6s7.8 0 9.5-.5c1-.3 1.7-1.1 2-2.1.5-1.7.5-5.8.5-5.8s0-4.1-.5-5.9zM9.5 15.5v-7l6.4 3.5-6.4 3.5z"/>
                            </svg>
                            YouTube Demo
                            <?php elseif ($mediaType === 'video'): ?>
                            <svg class="w-5 h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Demo Video
                            <?php else: ?>
                            <svg class="w-5 h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Live Preview
                            <?php endif; ?>
                        </h5>
                        <div class="flex gap-2">
                            <?php if ($mediaType === 'youtube'): ?>
                                <button onclick="openYoutubeModal('<?php echo htmlspecialchars($youtubeId, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M23.5 6.2c-.3-1-1-1.8-2-2.1C19.8 3.5 12 3.5 12 3.5s-7.8 0-9.5.5c-1 .3-1.7 1.1-2 2.1C0 7.9 0 12 0 12s0 4.1.5 5.8c.3 1 1 1.8 2 2.1 1.7.6 9.5.6 9.5.6s7.8 0 9.5-.5c1-.3 1.7-1.1 2-2.1.5-1.7.5-5.8.5-5.8s0-4.1-.5-5.9zM9.5 15.5v-7l6.4 3.5-6.4 3.5z"/>
                                    </svg>
                                    Watch Demo
                                </button>
                                <a href="https://youtube.com/watch?v=<?php echo htmlspecialchars($youtubeId); ?>" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-md text-gray-100 bg-gray-800 hover:bg-gray-900 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Open on YouTube
                                </a>
                            <?php elseif ($mediaType === 'video'): ?>
                                <button onclick="openVideoModal('<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>')"
                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors"
                                        data-video-trigger
                                        data-video-url="<?php echo htmlspecialchars($mediaUrl, ENT_QUOTES); ?>"
                                        data-video-title="<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Watch Demo
                                </button>
                                <a href="<?php echo htmlspecialchars($mediaUrl); ?>" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-md text-gray-100 bg-gray-800 hover:bg-gray-900 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Open in New Tab
                                </a>
                            <?php else: ?>
                                <a href="<?php echo htmlspecialchars($mediaUrl); ?>" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 border border-gray-600 text-sm font-medium rounded-md text-gray-100 bg-gray-800 hover:bg-gray-900 transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                    Open in New Tab
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($mediaType === 'demo_url'): ?>
                    <div class="h-96 sm:h-[600px]">
                        <iframe src="<?php echo htmlspecialchars($mediaUrl); ?>" 
                                class="w-full h-full border-0"
                                loading="lazy">
                        </iframe>
                    </div>
                    <?php elseif ($mediaType === 'youtube'): ?>
                    <div class="aspect-video">
                        <iframe src="https://www.youtube-nocookie.com/embed/<?php echo htmlspecialchars($youtubeId); ?>?rel=0&modestbranding=1" 
                                class="w-full h-full border-0"
                                loading="lazy"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen>
                        </iframe>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Social Sharing Section -->
                <div class="mb-8">
                    <div class="bg-gradient-to-r from-gray-800 to-gray-700 rounded-xl shadow-sm border border-gray-600 p-6">
                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <svg class="w-6 h-6 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                                </svg>
                                <div>
                                    <h3 class="text-lg font-bold text-white">Love this template?</h3>
                                    <p class="text-sm text-gray-100">Share it with your friends!</p>
                                </div>
                            </div>
                            
                            <!-- Share Buttons -->
                            <div class="flex items-center gap-3 flex-wrap justify-center">
                                <!-- WhatsApp Share -->
                                <button onclick="shareViaWhatsApp()" 
                                        class="flex items-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-all shadow-md hover:shadow-lg">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                    </svg>
                                    <span class="hidden sm:inline">WhatsApp</span>
                                </button>
                                
                                <!-- Facebook Share -->
                                <button onclick="shareViaFacebook()" 
                                        class="flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-all shadow-md hover:shadow-lg">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                    </svg>
                                    <span class="hidden sm:inline">Facebook</span>
                                </button>
                                
                                <!-- Twitter/X Share -->
                                <button onclick="shareViaTwitter()" 
                                        class="flex items-center gap-2 px-4 py-2.5 bg-gray-900 hover:bg-black text-white rounded-lg transition-all shadow-md hover:shadow-lg">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                                    </svg>
                                    <span class="hidden sm:inline">Twitter</span>
                                </button>
                                
                                <!-- Copy Link -->
                                <button onclick="copyTemplateLink()" 
                                        class="flex items-center gap-2 px-4 py-2.5 bg-gray-600 hover:bg-gray-500 text-white rounded-lg transition-all shadow-md hover:shadow-lg">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                                    </svg>
                                    <span class="hidden sm:inline">Copy Link</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Copy Success Message -->
                        <div id="copy-success" class="hidden mt-3 p-3 bg-green-100 border border-green-300 rounded-lg text-green-800 text-sm">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            Link copied to clipboard! Share it anywhere.
                        </div>
                    </div>
                </div>

                <?php if (!empty($features)): ?>
                <section class="mb-8">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-white mb-6">What's Included</h2>
                    <div class="bg-gray-800 rounded-xl p-6 border border-gray-700">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach (array_slice($features, 0, 6) as $feature): ?>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-100"><?php echo htmlspecialchars(trim($feature)); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($features) > 6): ?>
                        <p class="mt-4 text-sm text-gray-400 text-center">+ <?php echo count($features) - 6; ?> more features included</p>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="sticky top-24">
                    <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 overflow-hidden">
                        <div class="p-6 sm:p-8">
                            <div class="text-center mb-6 pb-6 border-b border-gray-700">
                                <div class="text-sm text-gray-400 font-semibold mb-2">Starting at</div>
                                <h2 class="text-4xl font-extrabold text-primary-600"><?php echo formatCurrency($template['price']); ?></h2>
                                <p class="text-sm text-gray-400 mt-2">Includes domain & hosting</p>
                            </div>

                            <div class="space-y-3 mb-6">
                                <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>', this)" 
                                   class="btn-gold-shine w-full inline-flex items-center justify-center px-6 py-4 text-lg font-bold rounded-lg text-navy transition-all disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    Add to Cart
                                </button>
                            </div>

                            <div class="space-y-3 border-t border-gray-700 pt-6">
                                <div class="flex items-center text-sm text-gray-100">
                                    <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Ready in 24 hours</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-100">
                                    <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Free domain included</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-100">
                                    <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>24/7 WhatsApp support</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row justify-between items-center">
                <p class="text-sm text-gray-400 mb-4 sm:mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                <a href="/" class="text-sm text-gray-400 hover:text-white transition-colors">Home</a>
            </div>
        </div>
    </footer>

    <!-- Demo Modal -->
    <div id="demoModal" class="fixed inset-0 z-50 hidden">
        <div class="flex items-center justify-center min-h-screen px-4 py-8">
            <div onclick="closeDemoFullscreen()" class="fixed inset-0 bg-black bg-opacity-75"></div>
            <div class="relative bg-gray-800 rounded-xl shadow-2xl" style="width: 90vw; max-width: min(90vw, 1400px); height: 90vh; max-height: 90vh;">
                <div class="flex items-center justify-between px-6 py-3 border-b border-gray-700 bg-gray-900 rounded-t-xl">
                    <h5 class="text-lg font-bold text-white" id="demoTitle">Template Preview</h5>
                    <button onclick="closeDemoFullscreen()" class="text-gray-400 hover:text-gray-300 transition-colors p-1">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
                <div class="overflow-hidden rounded-b-xl" style="height: calc(100% - 57px);">
                    <iframe id="demoFrame" src="" frameborder="0" class="w-full h-full rounded-b-xl"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openDemoFullscreen(url, title) {
            const modal = document.getElementById('demoModal');
            const iframe = document.getElementById('demoFrame');
            const titleEl = document.getElementById('demoTitle');
            
            titleEl.textContent = title + ' - Preview';
            iframe.src = url;
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDemoFullscreen() {
            const modal = document.getElementById('demoModal');
            const iframe = document.getElementById('demoFrame');
            
            modal.classList.add('hidden');
            iframe.src = '';
            document.body.style.overflow = '';
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDemoFullscreen();
            }
        });
    </script>
    
    <?php include __DIR__ . '/includes/floating-cart-widget.php'; ?>
    <script src="/assets/js/loader-controller.js?v=<?php echo time(); ?>"></script>
</body>
</html>
