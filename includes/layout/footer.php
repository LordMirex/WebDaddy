<?php
/**
 * WebDaddy Empire - Shared Premium Footer Component
 * 
 * Usage: Include this file after setting the following variables:
 * - $affiliateCode: string|null (affiliate tracking code)
 * - $showMobileCTA: bool (whether to show mobile sticky CTA, default false)
 * - $pageType: string ('home', 'blog', 'legal', 'user') for SEO schema
 */

// Set defaults
$affiliateCode = $affiliateCode ?? ($_SESSION['affiliate_code'] ?? null);
$showMobileCTA = $showMobileCTA ?? false;
$pageType = $pageType ?? 'page';

// Build affiliate query string
$affQuery = $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '';
$affQueryStart = $affiliateCode ? '?aff=' . urlencode($affiliateCode) : '';
?>

<!-- Organization Schema for SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "<?= SITE_NAME ?>",
    "url": "<?= SITE_URL ?>",
    "logo": "<?= SITE_URL ?>/assets/images/webdaddy-logo.png",
    "description": "Professional website templates and digital tools for African entrepreneurs",
    "contactPoint": {
        "@type": "ContactPoint",
        "telephone": "<?= WHATSAPP_NUMBER ?>",
        "contactType": "customer service",
        "availableLanguage": "English"
    },
    "sameAs": [
        <?php 
        $socialUrls = [];
        $socialPlatforms = ['facebook', 'twitter', 'instagram', 'linkedin', 'youtube', 'tiktok'];
        foreach ($socialPlatforms as $platform) {
            $url = getSetting('social_' . $platform, '');
            if (!empty($url)) {
                $socialUrls[] = '"' . htmlspecialchars($url) . '"';
            }
        }
        echo implode(",\n        ", $socialUrls);
        ?>
    ]
}
</script>

<!-- Premium Footer -->
<footer class="bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 text-white border-t border-blue-500/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 md:py-16">
        <!-- Brand Section - Centered on Mobile, Left on Desktop -->
        <div class="mb-8 lg:mb-0 lg:grid lg:grid-cols-12 lg:gap-8 lg:items-start">
            <div class="lg:col-span-4 text-center lg:text-left mb-8 lg:mb-0">
                <div class="flex items-center gap-3 mb-4 justify-center lg:justify-start">
                    <img src="/assets/images/webdaddy-logo.png" alt="<?= SITE_NAME ?>" class="h-10 md:h-12" loading="lazy" decoding="async">
                    <span class="text-xl md:text-lg font-bold"><?= SITE_NAME ?></span>
                </div>
                <p class="text-gray-400 text-xs md:text-sm mb-4 font-medium">Professional websites & digital tools. Launch in 24 hours.</p>
                <div class="flex gap-2 flex-wrap justify-center lg:justify-start">
                    <?php 
                    $socials = [
                        ['facebook', 'Facebook', '<svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'],
                        ['twitter', 'Twitter/X', '<svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417a9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>'],
                        ['instagram', 'Instagram', '<svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 24 24"><rect x="2" y="2" width="20" height="20" rx="5" ry="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4"/><circle cx="18.5" cy="5.5" r="1.5"/></svg>'],
                        ['linkedin', 'LinkedIn', '<svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.225 0z"/></svg>'],
                        ['youtube', 'YouTube', '<svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>'],
                        ['tiktok', 'TikTok', '<svg class="w-3 h-3 md:w-4 md:h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M16.6915026,4.4744748 L16.6915026,4.4744748 C15.8827356,4.4744748 15.4399899,5.2615254 15.4399899,6.0472455 L15.4399899,13.4744748 C15.4399899,15.1598765 14.0631058,16.5 12.3456618,16.5 C10.6282178,16.5 9.25133358,15.1598765 9.25133358,13.4744748 C9.25133358,11.7890732 10.6282178,10.4489497 12.3456618,10.4489497 L12.3456618,9.28389829 C12.3456618,9.28389829 10.2127841,9.28389829 8.10604828,11.2797855 C6.4669767,12.8193454 5.59628668,14.8146968 5.59628668,16.9863542 C5.59628668,20.7020703 8.74539883,23.7133244 12.5,23.7133244 C16.2546012,23.7133244 19.4037133,20.7020703 19.4037133,16.9863542 L19.4037133,10.0153374 C20.3667504,10.6451065 21.5,11.1409288 22.7134387,11.1409288 L22.7134387,9.97585739 C22.7134387,9.97585739 21.2134387,8.94365979 19.6915026,8.35756183 Z"/></svg>']
                    ];
                    foreach ($socials as [$settingKey, $label, $svg]) {
                        $url = getSetting('social_' . $settingKey, '');
                        if (!empty($url)) {
                            echo '<a href="' . htmlspecialchars($url) . '" target="_blank" rel="noopener noreferrer" class="w-7 h-7 md:w-8 md:h-8 bg-navy-light border border-gray-700/50 rounded-full flex items-center justify-center text-gray-400 hover:text-gold hover:border-gold/50 transition-colors" aria-label="' . htmlspecialchars($label) . '">';
                            echo $svg;
                            echo '</a>';
                        }
                    }
                    ?>
                </div>
            </div>

            <!-- Links Section - 2 Balanced Columns on Mobile, 3 on Desktop -->
            <div class="lg:col-span-4 grid grid-cols-2 gap-8 mb-8 lg:mb-0">
                <!-- Support Column -->
                <div class="text-center lg:text-left">
                    <h4 class="text-white font-semibold text-sm mb-4">Support</h4>
                    <ul class="space-y-2.5">
                        <li><a href="/faq.php" class="text-gray-400 hover:text-gold text-xs md:text-sm transition-colors">FAQ</a></li>
                        <li><a href="/contact.php" class="text-gray-400 hover:text-gold text-xs md:text-sm transition-colors">Contact</a></li>
                        <li><a href="/blog/<?= $affQueryStart ?>" class="text-gray-400 hover:text-gold text-xs md:text-sm transition-colors">Blog</a></li>
                    </ul>
                </div>

                <!-- Contact Column -->
                <div class="text-center lg:text-left">
                    <h4 class="text-white font-semibold text-sm mb-4">Contact</h4>
                    <ul class="space-y-2.5">
                        <li><a href="/about.php" class="text-gray-400 hover:text-gold text-xs md:text-sm transition-colors">About</a></li>
                        <li><a href="/careers.php" class="text-gray-400 hover:text-gold text-xs md:text-sm transition-colors">Careers</a></li>
                        <li><a href="/affiliate/register.php" class="text-gray-400 hover:text-gold text-xs md:text-sm transition-colors">Affiliate</a></li>
                    </ul>
                </div>
            </div>

            <!-- WhatsApp Column -->
            <div class="lg:col-span-4">
                <h4 class="text-white font-semibold text-sm mb-4 text-center lg:text-left">Get Connected</h4>
                <div class="space-y-3">
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', WHATSAPP_NUMBER)); ?>" 
                       target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 rounded-lg text-xs md:text-sm font-medium transition-colors w-full text-white">
                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                        </svg>
                        Message on WhatsApp
                    </a>
                    <div class="text-center lg:text-left">
                        <p class="text-gray-400 text-xs md:text-sm">
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', getSetting('whatsapp_number', WHATSAPP_NUMBER)); ?>" 
                               target="_blank" rel="noopener noreferrer"
                               class="text-green-400 hover:text-green-300 font-medium transition-colors">
                                WhatsApp Number - +2349132672126
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom bar -->
        <div class="mt-8 pt-6 border-t border-gray-700/50 flex flex-col sm:flex-row justify-between items-center gap-4 text-xs text-gray-500">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?></p>
            <div class="flex gap-6">
                <a href="/legal/privacy.php" class="hover:text-gold transition-colors">Privacy</a>
                <a href="/legal/terms.php" class="hover:text-gold transition-colors">Terms</a>
                <a href="/legal/status.php" class="hover:text-gold transition-colors">Status</a>
            </div>
        </div>
    </div>
</footer>

<?php if ($showMobileCTA): ?>
<!-- Mobile Sticky CTA Bar -->
<div class="blog-mobile-cta md:hidden fixed bottom-0 left-0 right-0 bg-navy border-t border-gold/20 p-3 z-40 flex items-center justify-center gap-3" <?= $affiliateCode ? 'data-affiliate-code="' . htmlspecialchars($affiliateCode) . '"' : '' ?>>
    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', WHATSAPP_NUMBER) ?>" 
       class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-green-600 hover:bg-green-700 rounded-lg text-white text-sm font-medium transition-colors" 
       target="_blank" rel="noopener">
        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
        </svg>
        WhatsApp
    </a>
    <a href="/#templates<?= $affQueryStart ?>" 
       class="flex-1 inline-flex items-center justify-center px-4 py-2.5 btn-gold-shine rounded-lg text-navy text-sm font-semibold transition-colors">
        View Templates
    </a>
    <?php if ($affiliateCode): ?>
    <span class="absolute -top-2 right-3 px-2 py-0.5 bg-gold text-navy text-[10px] font-bold rounded">Partner Link</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Instant Page Load - Prefetches pages on hover for instant navigation -->
<script src="https://instant.page/5.2.0" type="module" integrity="sha384-jnZyxPjiipYXnSU0ez8Mcp8KO4jxRoLVY0U0ZsxaZVB1AvZuCy8qX3qMF4Yh+Q5A" crossorigin="anonymous"></script>
<!-- Scroll Position Restoration - Remembers where you were on each page -->
<script src="/assets/js/scroll-restoration.js" defer></script>
<!-- Aggressive Network & Cache Optimization - Desktop & Mobile Speed -->
<script src="/assets/js/network-optimization.js" defer></script>
