<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/cart.php';

startSecureSession();
handleAffiliateTracking();

$currentView = $_GET['view'] ?? 'templates';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$category = $_GET['category'] ?? null;
$affiliateCode = getAffiliateCode();
$cartCount = getCartCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title><?php echo SITE_NAME; ?> - Professional Website Templates & Digital Tools</title>
    <meta name="description" content="Create your business website in 24 hours with our professional templates. Get custom domains, hosting, and API access included. Nigeria's #1 website builder platform.">
    <link rel="canonical" href="<?php echo SITE_URL; ?>">
    
    <meta name="keywords" content="website template, website builder, digital tools, website design, Nigeria, business website, ecommerce template, professional template">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    <meta name="robots" content="index, follow">
    <meta name="googlebot" content="index, follow">
    
    <meta property="og:url" content="<?php echo SITE_URL; ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?php echo SITE_NAME; ?> - Professional Website Templates & Tools">
    <meta property="og:description" content="Create your business website in 24 hours. Professional templates + hosting + domain included.">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.png">
    <meta property="og:site_name" content="<?php echo SITE_NAME; ?>">
    <meta property="og:locale" content="en_NG">
    
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo SITE_NAME; ?> - Website Templates & Digital Tools">
    <meta name="twitter:description" content="Professional website templates for businesses in Nigeria. Get online in 24 hours.">
    <meta name="twitter:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.png">
    
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script defer>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
    <style>
        @keyframes carousel-scroll {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .carousel-infinite {
            animation: carousel-scroll 30s linear infinite;
        }
        .carousel-infinite:hover {
            animation-play-state: paused;
        }
    </style>
    <script src="/assets/js/forms.js" defer></script>
    <script src="/assets/js/cart-and-tools.js" defer></script>
    <script src="/assets/js/lazy-load.js" defer></script>
    <script src="/assets/js/performance.js" defer></script>
    <script src="/assets/js/video-preloader.js" defer></script>
    <script src="/assets/js/video-modal.js" defer></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav id="mainNav" class="bg-white shadow-sm sticky top-0 z-50" x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <a href="/" class="flex items-center">
                        <img src="/assets/images/webdaddy-logo.png" alt="WebDaddy Empire" class="h-14 mr-3" onerror="this.style.display='none'">
                        <span class="text-xl font-bold text-primary-900"><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/" class="text-sm sm:text-base font-medium text-gray-700 hover:text-primary-600 transition-colors">Websites</a>
                    <a href="?view=tools" class="text-sm sm:text-base font-medium text-gray-700 hover:text-primary-600 transition-colors">Tools</a>
                    <a href="#faq" class="text-sm sm:text-base font-medium text-gray-700 hover:text-primary-600 transition-colors">FAQ</a>
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" target="_blank" class="text-sm sm:text-base font-medium text-gray-700 hover:text-primary-600 transition-colors">Contact</a>
                    <a href="#" onclick="toggleCartDrawer(); return false;" class="relative text-gray-700 hover:text-primary-600 font-medium transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                    </a>
                </div>
                <div class="md:hidden flex items-center space-x-4">
                    <a href="#" onclick="toggleCartDrawer(); return false;" class="relative text-gray-700 hover:text-primary-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count-mobile" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                    </a>
                    <button @click="open = !open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-700 hover:text-primary-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div x-show="open" x-collapse class="md:hidden pb-4">
                <a href="/" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-primary-600">Websites</a>
                <a href="?view=tools" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-primary-600">Tools</a>
                <a href="#faq" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-primary-600">FAQ</a>
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" target="_blank" class="block px-3 py-2 text-base font-medium text-gray-700 hover:text-primary-600">Contact</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="bg-gradient-to-r from-primary-900 to-primary-700 text-white py-12 sm:py-16 lg:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-8">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold mb-6 leading-tight">Turn Your Ideas Into Reality</h1>
                <p class="text-xl text-primary-100">Choose from our <span class="font-bold">ready-made templates</span> or get <span class="font-bold">powerful digital tools</span> to grow your business</p>
                <p class="text-primary-100 mt-4">Domain included • Fast setup • Professional design</p>
            </div>

            <div class="flex justify-center gap-4 mb-8">
                <div class="flex items-center gap-2 text-primary-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2.893 5.138a4 4 0 016.946-1.946c.064.063.128.126.192.188L10 6.979l.969-.969c.064-.062.128-.125.192-.188a4 4 0 116.946 1.946L10 17.414l-7.107-12.276z"/></svg>
                    <span class="text-sm sm:text-base">30-Day Money Back</span>
                </div>
                <div class="flex items-center gap-2 text-primary-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/></svg>
                    <span class="text-sm sm:text-base">24hr Setup</span>
                </div>
                <div class="flex items-center gap-2 text-primary-100">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"/></svg>
                    <span class="text-sm sm:text-base">24/7 Support</span>
                </div>
            </div>

            <div class="flex justify-center gap-4 mb-8">
                <a href="?view=templates" class="inline-flex items-center gap-2 px-6 sm:px-8 py-3 sm:py-4 bg-white text-primary-900 font-semibold rounded-lg hover:bg-primary-50 transition-all shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="hidden sm:inline">Browse Templates</span>
                    <span class="sm:hidden">Templates</span>
                </a>
                <a href="?view=tools" class="inline-flex items-center gap-2 px-6 sm:px-8 py-3 sm:py-4 bg-primary-100 text-primary-900 font-semibold rounded-lg hover:bg-primary-200 transition-all shadow-lg">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span class="hidden sm:inline">Explore Tools</span>
                    <span class="sm:hidden">Tools</span>
                </a>
            </div>

            <div class="grid grid-cols-3 gap-4 sm:gap-8 max-w-2xl mx-auto text-center">
                <div>
                    <div class="text-3xl sm:text-4xl font-extrabold">500+</div>
                    <p class="text-sm sm:text-base text-primary-100 mt-2">Websites Launched</p>
                </div>
                <div>
                    <div class="text-3xl sm:text-4xl font-extrabold">98%</div>
                    <p class="text-sm sm:text-base text-primary-100 mt-2">Happy Customers</p>
                </div>
                <div>
                    <div class="text-3xl sm:text-4xl font-extrabold">24hrs</div>
                    <p class="text-sm sm:text-base text-primary-100 mt-2">Average Setup</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Products Section -->
    <section class="py-12 bg-white" id="products">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Unified Search Interface -->
            <div class="mb-4 sm:mb-8">
                <div class="max-w-4xl mx-auto">
                    <div class="relative">
                        <input type="text" 
                               id="search-input"
                               placeholder="Search templates and tools..."
                               class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all"
                               autocomplete="off">
                        <svg class="absolute left-3 top-3.5 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- View Switcher Tabs (Responsive) -->
            <div class="flex justify-center gap-2 sm:gap-4 mb-8">
                <a href="/" class="inline-flex items-center gap-2 px-3 sm:px-6 py-2 sm:py-3 font-medium transition-all <?php echo $currentView === 'templates' ? 'bg-primary-600 text-white rounded-lg shadow-md' : 'text-gray-700 hover:text-primary-600'; ?>">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                    </svg>
                    <span class="text-xs sm:text-base">Websites</span>
                </a>
                <a href="?view=tools" class="inline-flex items-center gap-2 px-3 sm:px-6 py-2 sm:py-3 font-medium transition-all <?php echo $currentView === 'tools' ? 'bg-primary-600 text-white rounded-lg shadow-md' : 'text-gray-700 hover:text-primary-600'; ?>">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span class="text-xs sm:text-base">Tools</span>
                </a>
            </div>

            <!-- Dynamic Content Area -->
            <div id="products-content-area">
            <?php if ($currentView === 'templates'): ?>
            <?php
                $perPage = 18;
                $allTemplates = getTemplates(true, $category, null, null, true);
                
                if (filter_var($affiliateCode, FILTER_VALIDATE_EMAIL)) {
                    usort($allTemplates, function($a, $b) {
                        return ($a['favorite'] ? 0 : 1) <=> ($b['favorite'] ? 0 : 1);
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
                $templateCategories = getTemplateCategories();
            ?>
            
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
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="absolute left-3 top-3.5 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($templates)): ?>
                <div class="text-center py-12">
                    <h4 class="text-2xl font-bold text-gray-900 mb-3">No templates available</h4>
                    <p class="text-gray-600">Please try a different category or check back later.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                    <?php foreach ($templates as $idx => $template): ?>
                    <div class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-xl border border-gray-200 transition-all">
                        <a href="/<?php echo htmlspecialchars($template['slug']); ?>" class="block">
                            <div class="relative h-56 bg-gray-200 overflow-hidden">
                                <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($template['name']); ?>"
                                     <?php echo $idx < 3 ? 'loading="eager"' : 'loading="lazy"'; ?>
                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                            </div>
                        </a>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-gray-900 text-lg hover:text-primary-600">
                                    <a href="/<?php echo htmlspecialchars($template['slug']); ?>">
                                        <?php echo htmlspecialchars(substr($template['name'], 0, 30)); ?>
                                    </a>
                                </h3>
                                <button onclick="toggleFavorite('template', <?php echo $template['id']; ?>)" class="text-gray-300 hover:text-red-500 transition-colors">
                                    <svg class="w-6 h-6 fill-current" viewBox="0 0 24 24">
                                        <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                    </svg>
                                </button>
                            </div>
                            <p class="text-gray-600 text-sm mb-3 display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars(substr($template['description'] ?? '', 0, 80) . (strlen($template['description'] ?? '') > 80 ? '...' : '')); ?></p>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-lg font-bold text-primary-600">₦<?php echo number_format($template['price']); ?></span>
                                <span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded"><?php echo htmlspecialchars($template['category']); ?></span>
                            </div>
                            <button onclick="addToCart('template', <?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name']); ?>', <?php echo $template['price']; ?>)" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2 rounded-lg transition-all">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <nav class="flex flex-col sm:flex-row justify-center items-center gap-3 mt-8">
                    <?php if ($page > 1): ?>
                    <a href="?<?php echo http_build_query(['page' => $page - 1, 'category' => $category]); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?<?php echo http_build_query(['page' => $i, 'category' => $category]); ?>#products" 
                       class="inline-flex items-center justify-center w-10 h-10 font-semibold rounded-lg <?php echo $i === $page ? 'bg-primary-600 text-white shadow-md' : 'text-gray-700 bg-white border-2 border-gray-300 hover:border-primary-600 hover:text-primary-600'; ?> transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo http_build_query(['page' => $page + 1, 'category' => $category]); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm font-medium text-gray-600 text-center mt-4">
                    Page <span class="text-primary-600 font-bold"><?php echo $page; ?></span> of <?php echo $totalPages; ?> <span class="text-gray-400 mx-1">•</span> <?php echo $totalTemplates; ?> templates
                </p>
            <?php endif; ?>
            <?php endif; ?>
            
            <?php else: ?>
            <?php
                $perPage = 18;
                $allTools = getTools(true, $category, null, null, true);
                
                // Sort by priority first (null=999), then by date
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
            ?>
            
            <?php if (!empty($toolCategories)): ?>
            <!-- Category Filter for Tools -->
            <div class="mb-3 sm:mb-6 max-w-4xl mx-auto">
                <div class="relative">
                    <select id="tools-category-filter" 
                            class="w-full px-4 py-3 pl-11 pr-10 border-2 border-gray-300 rounded-lg focus:border-primary-500 focus:ring-2 focus:ring-primary-200 transition-all appearance-none bg-white text-gray-900 font-medium cursor-pointer">
                        <option value="" <?php echo !isset($_GET['category']) ? 'selected' : ''; ?>>
                            All Categories
                        </option>
                        <?php foreach ($toolCategories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($category === $cat) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="absolute left-3 top-3.5 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (empty($tools)): ?>
                <div class="text-center py-12">
                    <h4 class="text-2xl font-bold text-gray-900 mb-3">No tools available</h4>
                    <p class="text-gray-600">Please try a different category or check back later.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
                    <?php foreach ($tools as $idx => $tool): ?>
                    <div class="bg-white rounded-xl overflow-hidden shadow-md hover:shadow-xl border border-gray-200 transition-all">
                        <a href="/tool/<?php echo htmlspecialchars($tool['slug']); ?>" class="block">
                            <div class="relative h-56 bg-gray-200 overflow-hidden">
                                <img src="<?php echo htmlspecialchars($tool['thumbnail_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($tool['name']); ?>"
                                     <?php echo $idx < 3 ? 'loading="eager"' : 'loading="lazy"'; ?>
                                     class="w-full h-full object-cover hover:scale-105 transition-transform duration-300"
                                     onerror="this.src='/assets/images/placeholder.jpg'">
                            </div>
                        </a>
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="font-bold text-gray-900 text-lg hover:text-primary-600">
                                    <a href="/tool/<?php echo htmlspecialchars($tool['slug']); ?>">
                                        <?php echo htmlspecialchars(substr($tool['name'], 0, 30)); ?>
                                    </a>
                                </h3>
                            </div>
                            <p class="text-gray-600 text-sm mb-3 display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"><?php echo htmlspecialchars($tool['short_description']); ?></p>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-lg font-bold text-primary-600">₦<?php echo number_format($tool['price']); ?></span>
                                <span class="text-xs bg-primary-100 text-primary-700 px-2 py-1 rounded"><?php echo htmlspecialchars($tool['tool_type'] ?? 'Tool'); ?></span>
                            </div>
                            <button onclick="addToCart('tool', <?php echo $tool['id']; ?>, '<?php echo htmlspecialchars($tool['name']); ?>', <?php echo $tool['price']; ?>)" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-2 rounded-lg transition-all">
                                Add to Cart
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <nav class="flex flex-col sm:flex-row justify-center items-center gap-3 mt-8">
                    <?php if ($page > 1): ?>
                    <a href="?view=tools&<?php echo http_build_query(['page' => $page - 1, 'category' => $category]); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Previous
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="?view=tools&<?php echo http_build_query(['page' => $i, 'category' => $category]); ?>#products" 
                       class="inline-flex items-center justify-center w-10 h-10 font-semibold rounded-lg <?php echo $i === $page ? 'bg-primary-600 text-white shadow-md' : 'text-gray-700 bg-white border-2 border-gray-300 hover:border-primary-600 hover:text-primary-600'; ?> transition-all">
                        <?php echo $i; ?>
                    </a>
                    <?php endforeach; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <a href="?view=tools&<?php echo http_build_query(['page' => $page + 1, 'category' => $category]); ?>#products" 
                       class="inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 bg-white border-2 border-gray-300 rounded-lg hover:bg-primary-50 hover:border-primary-600 hover:text-primary-600 transition-all shadow-sm">
                        Next
                        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    <?php endif; ?>
                </nav>
                <p class="text-sm font-medium text-gray-600 text-center mt-4">
                    Page <span class="text-primary-600 font-bold"><?php echo $page; ?></span> of <?php echo $totalPages; ?> <span class="text-gray-400 mx-1">•</span> <?php echo $totalTools; ?> tools
                </p>
            <?php endif; ?>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Testimonials Section with Mobile Carousel -->
    <section class="py-12 bg-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center mb-12">
                <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">Trusted by Businesses Like Yours</h2>
                <p class="text-xl text-gray-600">See what our customers say about launching their online presence</p>
            </div>
            
            <!-- Desktop Grid (Hidden on Mobile) -->
            <div class="hidden md:grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200">
                    <div class="flex gap-1 mb-4">
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                    <p class="text-gray-700 mb-4">"Amazing service! My restaurant website was live in 24 hours. The template looks professional and my customers love it."</p>
                    <div>
                        <div class="font-semibold text-gray-900">Adebayo Johnson</div>
                        <div class="text-sm text-gray-600">Bella's Kitchen, Lagos</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200">
                    <div class="flex gap-1 mb-4">
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                    <p class="text-gray-700 mb-4">"From zero to online business in one day! The setup was seamless and the support team is incredible."</p>
                    <div>
                        <div class="font-semibold text-gray-900">Ngozi Okoro</div>
                        <div class="text-sm text-gray-600">Fashion Boutique, Abuja</div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200">
                    <div class="flex gap-1 mb-4">
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </div>
                    <p class="text-gray-700 mb-4">"Professional, fast, and affordable. My law firm website attracts new clients every week. Highly recommended!"</p>
                    <div>
                        <div class="font-semibold text-gray-900">Barrister Emeka</div>
                        <div class="text-sm text-gray-600">Legal Services, Port Harcourt</div>
                    </div>
                </div>
            </div>
            
            <!-- Mobile Carousel (Only visible on Mobile) -->
            <div class="md:hidden overflow-hidden">
                <div class="carousel-infinite flex gap-6 w-fit px-2">
                    <!-- Testimonial 1 -->
                    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 flex-shrink-0 w-80">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-4">"Amazing service! My restaurant website was live in 24 hours. The template looks professional and my customers love it."</p>
                        <div>
                            <div class="font-semibold text-gray-900">Adebayo Johnson</div>
                            <div class="text-sm text-gray-600">Bella's Kitchen, Lagos</div>
                        </div>
                    </div>
                    <!-- Testimonial 2 -->
                    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 flex-shrink-0 w-80">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-4">"From zero to online business in one day! The setup was seamless and the support team is incredible."</p>
                        <div>
                            <div class="font-semibold text-gray-900">Ngozi Okoro</div>
                            <div class="text-sm text-gray-600">Fashion Boutique, Abuja</div>
                        </div>
                    </div>
                    <!-- Testimonial 3 -->
                    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 flex-shrink-0 w-80">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-4">"Professional, fast, and affordable. My law firm website attracts new clients every week. Highly recommended!"</p>
                        <div>
                            <div class="font-semibold text-gray-900">Barrister Emeka</div>
                            <div class="text-sm text-gray-600">Legal Services, Port Harcourt</div>
                        </div>
                    </div>
                    <!-- Repeat first for infinite effect -->
                    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200 flex-shrink-0 w-80">
                        <div class="flex gap-1 mb-4">
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <p class="text-gray-700 mb-4">"Amazing service! My restaurant website was live in 24 hours. The template looks professional and my customers love it."</p>
                        <div>
                            <div class="font-semibold text-gray-900">Adebayo Johnson</div>
                            <div class="text-sm text-gray-600">Bella's Kitchen, Lagos</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
                        <span>Do you offer support?</span>
                        <svg class="w-5 h-5 transform transition-transform" :class="selected === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 3" x-collapse class="px-6 pb-4 text-gray-600">
                        Yes! We provide 24/7 support via WhatsApp, email, and phone. Our team is always ready to help you get the most out of your website and tools.
                    </div>
                </div>
                <div class="bg-white rounded-lg shadow-md border border-gray-200">
                    <button @click="selected = selected === 4 ? null : 4" class="w-full text-left px-6 py-4 font-semibold text-gray-900 flex justify-between items-center hover:bg-gray-50 transition-colors">
                        <span>Can I customize the template?</span>
                        <svg class="w-5 h-5 transform transition-transform" :class="selected === 4 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="selected === 4" x-collapse class="px-6 pb-4 text-gray-600">
                        Absolutely! All our templates are fully customizable. You can modify colors, text, images, and add your own branding to make it uniquely yours.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">
                <div>
                    <h3 class="text-lg font-bold text-white mb-4">WebDaddy Empire</h3>
                    <p class="text-sm">Professional website templates and digital working tools to power your business. Get custom domains, API keys, software licenses, and more. Launch in 24 hours or less.</p>
                </div>
                
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
                                <div class="text-sm text-green-100">Chat with us live</div>
                            </div>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-xl font-bold mb-6">Quick Links</h3>
                    <div class="space-y-3 text-sm">
                        <a href="/?view=templates" class="hover:text-white transition-colors">Browse Templates</a>
                        <a href="/?view=tools" class="block hover:text-white transition-colors">Explore Tools</a>
                        <a href="#faq" class="block hover:text-white transition-colors">FAQ</a>
                        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" class="block hover:text-white transition-colors">Contact Us</a>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8">
                <div class="text-center text-sm">
                    <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                    <p class="mt-2">Designed to help Nigerian businesses grow online</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Cart Drawer & Modals (at end of body) -->
    <div id="cartDrawer" class="fixed right-0 top-0 w-full sm:w-96 h-screen bg-white shadow-lg z-50 transform translate-x-full transition-transform duration-300">
        <div class="flex justify-between items-center p-6 border-b">
            <h2 class="text-2xl font-bold">Your Cart</h2>
            <button onclick="toggleCartDrawer()" class="text-gray-500 hover:text-gray-700">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="cartItems" class="flex-1 overflow-y-auto p-6">
            <p class="text-gray-500 text-center py-10">Your cart is empty</p>
        </div>
        <div class="border-t p-6 space-y-4">
            <div class="flex justify-between text-lg font-semibold">
                <span>Total:</span>
                <span id="cartTotal">₦0</span>
            </div>
            <button onclick="proceedToCheckout()" class="w-full bg-primary-600 hover:bg-primary-700 text-white font-semibold py-3 rounded-lg transition-all">
                Proceed to Checkout
            </button>
            <button onclick="toggleCartDrawer()" class="w-full bg-gray-200 hover:bg-gray-300 text-gray-900 font-semibold py-3 rounded-lg transition-all">
                Continue Shopping
            </button>
        </div>
    </div>

    <script src="/assets/js/share.js"></script>
    <script src="/assets/js/cart-and-tools.js"></script>
</body>
</html>
