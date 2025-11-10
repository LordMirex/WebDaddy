<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/analytics.php';
require_once __DIR__ . '/includes/cart.php';

startSecureSession();
handleAffiliateTracking();

$templateId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$templateId) {
    header('Location: /');
    exit;
}

$template = getTemplateById($templateId);
if (!$template) {
    header('Location: /');
    exit;
}

trackPageVisit($_SERVER['REQUEST_URI'], 'Template: ' . $template['name']);
trackTemplateView($templateId);

$availableDomains = getAvailableDomains($templateId);
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
    <meta name="description" content="<?php echo htmlspecialchars($template['description']); ?>">
    <link rel="icon" type="image/png" href="/assets/images/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
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
    </script>
    <script src="/assets/js/forms.js" defer></script>
    <script src="/assets/js/cart-and-tools.js" defer></script>
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
                    <a href="/" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary-600 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Templates
                    </a>
                    <a href="#" id="cart-button" onclick="toggleCartDrawer(); return false;" class="relative text-gray-700 hover:text-primary-600 font-medium transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                        <span id="cart-count" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                    </a>
                </div>
                <div class="md:hidden flex items-center">
                    <button @click="open = !open" class="text-gray-700 hover:text-primary-600 focus:outline-none">
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
        <div x-show="open" @click.away="open = false" class="md:hidden bg-white border-t border-gray-200" style="display: none;">
            <div class="px-2 pt-2 pb-3 space-y-1">
                <a href="/" class="block px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100 font-medium">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to Templates
                </a>
                <a href="#" id="cart-button-mobile" onclick="toggleCartDrawer(); return false;" class="block px-3 py-2 rounded-md text-gray-700 hover:bg-gray-100 font-medium flex items-center">
                    Cart
                    <span id="cart-count-mobile" class="<?php echo $cartCount > 0 ? '' : 'hidden'; ?> ml-2 bg-red-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo $cartCount; ?></span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="relative bg-gradient-to-br from-primary-900 via-primary-800 to-navy text-white py-12 sm:py-16">
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl">
                <span class="inline-block bg-white/10 text-white px-4 py-2 rounded-lg text-sm font-semibold mb-4"><?php echo htmlspecialchars($template['category']); ?></span>
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
                    <img src="<?php echo htmlspecialchars($template['thumbnail_url']); ?>" 
                         alt="<?php echo htmlspecialchars($template['name']); ?>" 
                         class="w-full rounded-xl shadow-2xl"
                         onerror="this.src='/assets/images/placeholder.jpg'">
                </div>

                <?php if ($template['demo_url']): ?>
                <div class="bg-white rounded-xl shadow-md border border-gray-200 mb-8 overflow-hidden">
                    <div class="bg-gray-50 border-b border-gray-200 p-4 sm:p-6 flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                        <h5 class="text-lg font-bold text-gray-900 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                            Live Preview
                        </h5>
                        <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                           target="_blank" 
                           class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 transition-colors">
                            Open in New Tab
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                    </div>
                    <div class="h-96 sm:h-[600px]">
                        <iframe src="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                                class="w-full h-full border-0">
                        </iframe>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($features)): ?>
                <section class="mb-8">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-6">What's Included</h2>
                    <div class="bg-white rounded-xl p-6 border border-gray-200">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach (array_slice($features, 0, 6) as $feature): ?>
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-gray-700"><?php echo htmlspecialchars(trim($feature)); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (count($features) > 6): ?>
                        <p class="mt-4 text-sm text-gray-500 text-center">+ <?php echo count($features) - 6; ?> more features included</p>
                        <?php endif; ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>

            <div class="lg:col-span-1">
                <div class="sticky top-24">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 sm:p-8">
                            <div class="text-center mb-6 pb-6 border-b border-gray-200">
                                <div class="text-sm text-gray-500 font-semibold mb-2">Starting at</div>
                                <h2 class="text-4xl font-extrabold text-primary-600"><?php echo formatCurrency($template['price']); ?></h2>
                                <p class="text-sm text-gray-500 mt-2">Includes domain & hosting</p>
                            </div>

                            <div class="space-y-3 mb-6">
                                <button onclick="addTemplateToCart(<?php echo $template['id']; ?>, '<?php echo htmlspecialchars($template['name'], ENT_QUOTES); ?>', this)" 
                                   class="w-full inline-flex items-center justify-center px-6 py-4 border border-transparent text-lg font-bold rounded-lg text-white bg-primary-600 hover:bg-primary-700 transition-all shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    Add to Cart
                                </button>
                                <?php if ($template['demo_url']): ?>
                                <a href="<?php echo htmlspecialchars($template['demo_url']); ?>" 
                                   target="_blank" 
                                   class="w-full inline-flex items-center justify-center px-6 py-3 border border-primary-600 text-base font-medium rounded-lg text-primary-600 bg-white hover:bg-primary-50 transition-all">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                    View Live Demo
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-3 border-t border-gray-200 pt-6">
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Ready in 24 hours</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="w-5 h-5 text-green-500 mr-3 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Free domain included</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
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
</body>
</html>
