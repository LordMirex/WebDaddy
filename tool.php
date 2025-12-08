<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tools.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/cart.php';

startSecureSession();
handleAffiliateTracking();

$slug = $_GET['slug'] ?? null;
$toolId = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!empty($slug)) {
    $tool = getToolBySlug($slug);
} elseif ($toolId) {
    $tool = getToolById($toolId);
    
    if ($tool && !empty($tool['slug'])) {
        $affiliateParam = isset($_GET['aff']) ? '?aff=' . urlencode($_GET['aff']) : '';
        header('Location: /tool/' . $tool['slug'] . $affiliateParam, true, 301);
        exit;
    }
} else {
    header('Location: /?view=tools');
    exit;
}

if (!$tool) {
    http_response_code(404);
    header('Location: /?view=tools&error=tool_not_found');
    exit;
}

trackPageVisit($_SERVER['REQUEST_URI'], 'Tool: ' . $tool['name']);

// Track tool view with explicit casting and verification
if (!empty($tool['id'])) {
    trackToolView((int)$tool['id']);
}

$affiliateCode = getAffiliateCode();
$cartCount = getCartCount();
$features = $tool['features'] ? explode(',', $tool['features']) : [];
$isInStock = $tool['stock_unlimited'] || $tool['stock_quantity'] > 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo htmlspecialchars($tool['name']); ?> - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($tool['short_description'] ?? $tool['description']); ?>">
    <link rel="canonical" href="<?php echo SITE_URL . '/tool/' . $tool['slug']; ?>">
    
    <meta name="keywords" content="<?php echo !empty($tool['seo_keywords']) ? htmlspecialchars($tool['seo_keywords']) : (htmlspecialchars($tool['category'] ?? 'digital tool') . ', ' . htmlspecialchars($tool['tool_type'] ?? 'working tool') . ', ' . htmlspecialchars($tool['name']) . ', digital tools, business tools, API, software'); ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    
    <meta property="og:url" content="<?php echo SITE_URL . '/tool/' . $tool['slug']; ?>">
    <meta property="og:type" content="product">
    <meta property="og:title" content="<?php echo htmlspecialchars($tool['name']); ?> - <?php echo SITE_NAME; ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($tool['short_description'] ?? $tool['description']); ?>">
    <?php
    // Use banner image for social preview (larger, more impactful), fallback to thumbnail
    $ogImage = !empty($tool['banner_url']) ? $tool['banner_url'] : (!empty($tool['thumbnail_url']) ? $tool['thumbnail_url'] : '/assets/images/og-image.png');
    // Make image URL absolute for social sharing (required for Facebook, WhatsApp, etc.)
    if (!empty($ogImage) && strpos($ogImage, 'http') !== 0) {
        $ogImage = SITE_URL . $ogImage;
    }
    ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="<?php echo htmlspecialchars($tool['name']); ?> - Digital Tool">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:locale" content="en_NG">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($tool['name']); ?> - <?php echo SITE_NAME; ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($tool['short_description'] ?? $tool['description']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($ogImage); ?>">
    <meta name="twitter:image:alt" content="<?php echo htmlspecialchars($tool['name']); ?> - Digital Tool">
    
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Product",
      "name": "<?php echo htmlspecialchars($tool['name']); ?>",
      "description": "<?php echo htmlspecialchars($tool['description']); ?>",
      "image": "<?php echo htmlspecialchars($ogImage); ?>",
      "url": "<?php echo SITE_URL . '/tool/' . $tool['slug']; ?>",
      "offers": {
        "@type": "Offer",
        "price": "<?php echo $tool['price']; ?>",
        "priceCurrency": "NGN",
        "availability": "<?php echo $isInStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'; ?>",
        "url": "<?php echo SITE_URL . '/tool/' . $tool['slug']; ?>"
      },
      "brand": {
        "@type": "Brand",
        "name": "<?php echo SITE_NAME; ?>"
      },
      "category": "<?php echo htmlspecialchars($tool['category'] ?? 'Digital Tool'); ?>"
    }
    </script>
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        if (typeof tailwind !== 'undefined') {
        tailwind.config = {
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
    </script>
    <script src="/assets/js/cart-and-tools.js" defer></script>
    <script src="/assets/js/share.js" defer></script>
    <script src="/assets/js/lazy-load.js" defer></script>
    <script src="/assets/js/performance.js" defer></script>
</head>
<body class="bg-gray-900">
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
                    <a href="/?view=tools" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-100 hover:text-primary-400 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Tools
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
                <a href="/?view=tools" class="block px-3 py-2 rounded-md text-gray-100 hover:bg-gray-800 font-medium">Back to Tools</a>
            </div>
        </div>
    </nav>

    <header class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-12 sm:py-16">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl">
                <span class="inline-block bg-gray-800/10 text-white px-4 py-2 rounded-lg text-sm font-semibold mb-4"><?php echo htmlspecialchars($tool['category'] ?? 'Digital Tool'); ?></span>
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold mb-4"><?php echo htmlspecialchars($tool['name']); ?></h1>
                <p class="text-lg sm:text-xl text-white/90 mb-6"><?php echo htmlspecialchars($tool['short_description'] ?? $tool['description']); ?></p>
                <div class="flex items-center gap-4">
                    <div>
                        <div class="text-sm text-white/70 mb-1">Price</div>
                        <h2 class="text-3xl sm:text-4xl font-extrabold"><?php echo formatCurrency($tool['price']); ?></h2>
                    </div>
                    <?php if (!$tool['stock_unlimited']): ?>
                    <div class="ml-4">
                        <div class="text-sm text-white/70 mb-1">Stock</div>
                        <div class="text-2xl font-bold"><?php echo $isInStock ? $tool['stock_quantity'] . ' available' : 'Out of stock'; ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 lg:gap-10">
            <div class="lg:col-span-2">
                <div class="mb-8">
                    <?php 
                    $mediaType = $tool['media_type'] ?? 'banner';
                    $hasVideoPreview = ($mediaType === 'video' && !empty($tool['demo_video_url']));
                    $hasYoutubePreview = ($mediaType === 'youtube' && !empty($tool['preview_youtube']));
                    $hasDemoUrlPreview = ($mediaType === 'demo_url' && !empty($tool['demo_url']));
                    ?>
                    
                    <?php if ($hasVideoPreview || $hasYoutubePreview || $hasDemoUrlPreview): ?>
                    <div class="relative cursor-pointer group video-preview-container" 
                         onclick="<?php 
                             if ($hasYoutubePreview) {
                                 echo "openYoutubeModal('" . htmlspecialchars($tool['preview_youtube'], ENT_QUOTES) . "', '" . htmlspecialchars($tool['name'], ENT_QUOTES) . "')";
                             } elseif ($hasVideoPreview) {
                                 echo "openVideoModal('" . htmlspecialchars($tool['demo_video_url'], ENT_QUOTES) . "', '" . htmlspecialchars($tool['name'], ENT_QUOTES) . "')";
                             } else {
                                 echo "openDemoModal('" . htmlspecialchars($tool['demo_url'], ENT_QUOTES) . "', '" . htmlspecialchars($tool['name'], ENT_QUOTES) . "')";
                             }
                         ?>">
                        <img src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                             alt="<?php echo htmlspecialchars($tool['name']); ?>" 
                             class="w-full rounded-xl shadow-2xl"
                             onerror="this.src='/assets/images/placeholder.jpg'">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/50 transition-all rounded-xl">
                            <div class="w-20 h-20 bg-white/90 rounded-full flex items-center justify-center shadow-xl transform group-hover:scale-110 transition-transform">
                                <svg class="w-10 h-10 text-purple-600 ml-1" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="absolute bottom-4 left-4 bg-black/70 text-white px-3 py-1 rounded-lg text-sm font-medium">
                            <?php echo $hasYoutubePreview ? 'Watch on YouTube' : ($hasVideoPreview ? 'Watch Preview' : 'View Demo'); ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <img src="<?php echo htmlspecialchars($tool['thumbnail_url'] ?? '/assets/images/placeholder.jpg'); ?>"
                         alt="<?php echo htmlspecialchars($tool['name']); ?>" 
                         class="w-full rounded-xl shadow-2xl"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                    <?php endif; ?>
                </div>

                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 p-6 sm:p-8 mb-8">
                    <h3 class="text-2xl font-bold text-white mb-4">Description</h3>
                    <p class="text-gray-100 leading-relaxed whitespace-pre-line"><?php echo htmlspecialchars($tool['description']); ?></p>
                </div>

                <?php if (!empty($features)): ?>
                <div class="bg-gray-800 rounded-xl shadow-md border border-gray-700 p-6 sm:p-8 mb-8">
                    <h3 class="text-2xl font-bold text-white mb-4">Features</h3>
                    <ul class="space-y-3">
                        <?php foreach ($features as $feature): 
                            $feature = trim($feature);
                            if (!empty($feature)): ?>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-gray-100"><?php echo htmlspecialchars($feature); ?></span>
                        </li>
                        <?php endif; endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (!empty($tool['delivery_instructions'])): ?>
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-6 sm:p-8">
                    <h3 class="text-xl font-bold text-blue-900 mb-3 flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Delivery Information
                    </h3>
                    <p class="text-blue-200 whitespace-pre-line"><?php echo htmlspecialchars($tool['delivery_instructions']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-6 sticky top-24">
                    <h3 class="text-xl font-bold text-white mb-4">Get This Tool</h3>
                    
                    <div class="mb-6">
                        <div class="flex justify-between items-baseline mb-2">
                            <span class="text-gray-300">Price:</span>
                            <span class="text-3xl font-extrabold text-primary-600"><?php echo formatCurrency($tool['price']); ?></span>
                        </div>
                    </div>

                    <?php if ($isInStock): ?>
                    <button onclick="addToolToCart(<?php echo $tool['id']; ?>)" 
                            class="w-full bg-primary-600 hover:bg-primary-700 text-white font-bold py-4 px-6 rounded-lg transition-all shadow-md hover:shadow-xl flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        Add to Cart
                    </button>
                    <?php else: ?>
                    <button disabled class="w-full bg-gray-400 text-white font-bold py-4 px-6 rounded-lg cursor-not-allowed">
                        Out of Stock
                    </button>
                    <?php endif; ?>

                    <div class="mt-6 pt-6 border-t border-gray-700">
                        <h4 class="font-semibold text-white mb-3">What you get:</h4>
                        <ul class="space-y-2 text-sm text-gray-300">
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Instant delivery
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Full instructions
                            </li>
                            <li class="flex items-center">
                                <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                24/7 support
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="video-modal" class="fixed inset-0 z-50 hidden">
        <div class="absolute inset-0 bg-black/90" onclick="closeVideoModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4">
            <div class="relative w-full max-w-5xl bg-black rounded-xl overflow-hidden shadow-2xl">
                <button onclick="closeVideoModal()" class="absolute -top-12 right-0 text-white hover:text-gray-300 z-10">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
                <div id="video-modal-content" class="aspect-video bg-black">
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/js/video-modal.js"></script>
</body>
</html>
