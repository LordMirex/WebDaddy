<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/functions.php';

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

$availableDomains = getAvailableDomains($templateId);
$affiliateCode = getAffiliateCode();
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
                <div class="hidden md:flex items-center">
                    <a href="/" class="inline-flex items-center px-4 py-2 text-sm font-medium text-gray-700 hover:text-primary-600 transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Back to Templates
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
                <section class="mb-12">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-6">What's Included</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($features as $feature): ?>
                        <div class="flex items-start p-4 bg-white rounded-lg border border-gray-200 hover:border-primary-300 transition-colors">
                            <svg class="w-6 h-6 text-green-500 mr-3 shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <h5 class="font-semibold text-gray-900"><?php echo htmlspecialchars(trim($feature)); ?></h5>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endif; ?>

                <section class="mb-12">
                    <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-6">What You Get</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                            <svg class="w-12 h-12 text-primary-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <h5 class="font-bold text-gray-900 mb-2">Premium Domain</h5>
                            <p class="text-gray-600 text-sm">Choose from <?php echo count($availableDomains); ?> available premium domain names</p>
                        </div>
                        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                            <svg class="w-12 h-12 text-primary-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <h5 class="font-bold text-gray-900 mb-2">Fast Setup</h5>
                            <p class="text-gray-600 text-sm">Your website will be ready within 24 hours of payment</p>
                        </div>
                        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                            <svg class="w-12 h-12 text-primary-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                            </svg>
                            <h5 class="font-bold text-gray-900 mb-2">Full Customization</h5>
                            <p class="text-gray-600 text-sm">Complete control to customize colors, text, and images</p>
                        </div>
                        <div class="bg-white rounded-xl p-6 border border-gray-200 hover:shadow-lg transition-shadow">
                            <svg class="w-12 h-12 text-primary-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                            <h5 class="font-bold text-gray-900 mb-2">24/7 Support</h5>
                            <p class="text-gray-600 text-sm">Get help anytime via WhatsApp support</p>
                        </div>
                    </div>
                </section>
            </div>

            <div class="lg:col-span-1">
                <div class="sticky top-24 space-y-6">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                        <div class="p-6 sm:p-8">
                            <div class="text-center mb-6 pb-6 border-b border-gray-200">
                                <div class="text-sm text-gray-500 font-semibold mb-2">Price</div>
                                <h2 class="text-4xl font-extrabold text-primary-600"><?php echo formatCurrency($template['price']); ?></h2>
                            </div>

                            <div class="mb-6">
                                <h5 class="font-bold text-gray-900 mb-4">Available Domains</h5>
                                <?php if (count($availableDomains) > 0): ?>
                                <div class="space-y-2">
                                    <?php foreach (array_slice($availableDomains, 0, 5) as $domain): ?>
                                    <div class="flex items-center p-2 bg-gray-50 rounded-lg">
                                        <svg class="w-5 h-5 text-green-500 mr-2 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="font-semibold text-gray-700 text-sm"><?php echo htmlspecialchars($domain['domain_name']); ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                    <?php if (count($availableDomains) > 5): ?>
                                    <div class="text-sm text-gray-500 mt-3">
                                        +<?php echo count($availableDomains) - 5; ?> more domains available
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-sm text-yellow-800">
                                    No domains currently available. Contact us for custom domains.
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="space-y-3 mb-6">
                                <a href="/order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                                   class="w-full inline-flex items-center justify-center px-6 py-3.5 border border-transparent text-base font-bold rounded-lg text-white bg-primary-600 hover:bg-primary-700 transition-all shadow-lg">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                    </svg>
                                    Order Now
                                </a>
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
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Secure Payment</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                    </svg>
                                    <span>24/7 Support</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-700">
                                    <svg class="w-5 h-5 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Free Updates</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-100 rounded-xl overflow-hidden">
                        <div class="p-6">
                            <h6 class="font-bold text-gray-900 mb-3 flex items-center">
                                <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                Need Help?
                            </h6>
                            <p class="text-sm text-gray-600 mb-4">Have questions? Contact us on WhatsApp</p>
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', '+2349132672126')); ?>" 
                               class="w-full inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-green-600 hover:bg-green-700 transition-all"
                               target="_blank">
                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                                </svg>
                                Chat with Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <section class="bg-gray-100 py-12 sm:py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl mx-auto text-center">
                <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 mb-4">Ready to Get Started?</h2>
                <p class="text-lg text-gray-600 mb-8">Join hundreds of businesses using our templates</p>
                <a href="/order.php?template=<?php echo $template['id']; ?><?php echo $affiliateCode ? '&aff=' . urlencode($affiliateCode) : ''; ?>" 
                   class="inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-lg text-white bg-primary-600 hover:bg-primary-700 transition-all shadow-lg">
                    Order This Template Now
                </a>
            </div>
        </div>
    </section>

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
