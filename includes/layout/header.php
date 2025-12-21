<?php
/**
 * WebDaddy Empire - Shared Premium Header/Navigation Component
 * 
 * Usage: Include this file after setting the following variables:
 * - $activeNav: string ('home', 'templates', 'tools', 'blog', 'faq')
 * - $affiliateCode: string|null (affiliate tracking code)
 * - $cartCount: int (number of items in cart, default 0)
 * - $showCart: bool (whether to show cart icon, default true)
 * - $pageType: string ('home', 'blog', 'legal', 'user') for SEO schema
 */

// Set defaults
$activeNav = $activeNav ?? 'home';
$affiliateCode = $affiliateCode ?? ($_SESSION['affiliate_code'] ?? null);
$cartCount = $cartCount ?? (isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0);
$showCart = $showCart ?? true;
$pageType = $pageType ?? 'page';

// Build affiliate query string
$affQuery = $affiliateCode ? '&aff=' . urlencode($affiliateCode) : '';
$affQueryStart = $affiliateCode ? '?aff=' . urlencode($affiliateCode) : '';

// Check customer session using the new cookie-based system
$isLoggedIn = false;
$customerName = null;

// Import customer session functions - must be done after session is started
if (!function_exists('getCustomerFromSession')) {
    require_once __DIR__ . '/../customer_session.php';
}

// Use the cookie-based session system
$customerSession = @getCustomerFromSession();
if ($customerSession && !empty($customerSession['id'])) {
    $isLoggedIn = true;
    if (!empty($customerSession['name'])) {
        $customerName = $customerSession['name'];
    }
}
?>
<!-- Navigation Schema for SEO -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "SiteNavigationElement",
    "name": ["Templates", "Tools", "Pricing", "FAQ", "Blog", "About", "Contact", "Careers", "Security", "Affiliate Program"],
    "url": [
        "<?= SITE_URL ?>/?view=templates",
        "<?= SITE_URL ?>/?view=tools",
        "<?= SITE_URL ?>/pricing.php",
        "<?= SITE_URL ?>/faq.php",
        "<?= SITE_URL ?>/blog/",
        "<?= SITE_URL ?>/about.php",
        "<?= SITE_URL ?>/contact.php",
        "<?= SITE_URL ?>/careers.php",
        "<?= SITE_URL ?>/security.php",
        "<?= SITE_URL ?>/affiliate/register.php"
    ]
}
</script>

<!-- Premium Navigation -->
<nav id="mainNav" class="bg-navy border-b border-navy-light/50 sticky top-0 z-50 overflow-visible" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 overflow-visible">
        <div class="flex justify-between h-16 overflow-visible">
            <!-- Logo -->
            <div class="flex items-center">
                <a href="/" class="flex items-center group" aria-label="<?= SITE_NAME ?> Home">
                    <img src="/assets/images/webdaddy-logo.png" alt="<?= SITE_NAME ?>" class="h-12 mr-3 group-hover:scale-110 transition-transform duration-300" loading="eager" decoding="async">
                    <span class="text-lg sm:text-2xl font-black bg-gradient-to-r from-yellow-300 via-gold to-yellow-400 bg-clip-text text-transparent tracking-wider" style="letter-spacing: 0.08em; text-shadow: 0 4px 12px rgba(217, 119, 6, 0.4), 0 0 20px rgba(217, 119, 6, 0.2); filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));"><?= SITE_NAME ?></span>
                </a>
            </div>
            
            <!-- Desktop Navigation -->
            <div class="hidden md:flex items-center gap-0.5 lg:gap-1 overflow-visible">
                <a href="/" 
                   class="px-2 py-1 text-sm font-medium transition-colors border-b-2 <?= $activeNav === 'home' ? 'text-gold border-gold' : 'text-gray-300 border-transparent hover:text-gold'; ?>" 
                   style="background: none !important;">Home</a>
                <a href="/blog/" 
                   class="px-2 py-1 text-sm font-medium transition-colors border-b-2 <?= $activeNav === 'blog' ? 'text-gold border-gold' : 'text-gray-300 border-transparent hover:text-gold'; ?>" 
                   style="background: none !important;">Blog</a>
                <a href="/about.php" 
                   class="px-2 py-1 text-sm font-medium transition-colors border-b-2 <?= $activeNav === 'about' ? 'text-gold border-gold' : 'text-gray-300 border-transparent hover:text-gold'; ?>" 
                   style="background: none !important;">About</a>
                <a href="/contact.php" 
                   class="px-2 py-1 text-sm font-medium transition-colors border-b-2 <?= $activeNav === 'contact' ? 'text-gold border-gold' : 'text-gray-300 border-transparent hover:text-gold'; ?>" 
                   style="background: none !important;">Contact</a>
                
                <!-- Login Link -->
                <a href="<?= $isLoggedIn ? '/user/' : '/user/login.php'; ?>" class="px-2 py-1 text-sm font-medium text-gray-300 hover:text-gold transition-colors inline-flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                        <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                    </svg>
                    <span><?= $isLoggedIn ? 'My Account Dashboard' : 'Login'; ?></span>
                </a>
                
                <?php if ($showCart): ?>
                <!-- Cart Button -->
                <a href="#" id="cart-button" onclick="toggleCartDrawer(); return false;" class="relative inline-flex items-center justify-center text-gray-300 hover:text-gold transition-colors px-3 py-2 hover:bg-navy-light/50 rounded-lg ml-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span id="cart-count" class="absolute -top-1 -right-2 bg-gold text-navy font-bold rounded-full h-5 w-5 flex items-center justify-center text-xs font-mono <?= $cartCount > 0 ? '' : 'hidden'; ?>"><?= $cartCount; ?></span>
                </a>
                <?php endif; ?>
                
                <!-- Affiliate CTA -->
                <a href="/affiliate/register.php" class="btn-premium-gold px-3 py-1 text-sm font-semibold rounded-md text-navy transition-all whitespace-nowrap ml-1">
                    Become an Affiliate
                </a>
            </div>
            
            <!-- Mobile Menu Button -->
            <div class="md:hidden flex items-center gap-4">
                <?php if ($showCart): ?>
                <a href="#" id="cart-button-mobile-icon" onclick="toggleCartDrawer(); return false;" class="relative text-gray-300">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span id="cart-count-mobile-icon" class="<?= $cartCount > 0 ? '' : 'hidden'; ?> absolute -top-1 -right-1 bg-gold text-navy text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?= $cartCount; ?></span>
                </a>
                <?php endif; ?>
                <button @click="open = !open" class="text-gray-300 hover:text-gold focus:outline-none" aria-label="Toggle menu">
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
    
    <!-- Mobile Navigation Menu -->
    <div x-show="open" class="md:hidden bg-navy border-t border-navy-light/50" style="display: none;">
        <div class="px-2 pt-2 pb-4 space-y-1">
            <!-- Home -->
            <a href="/" @click="open = false" 
               class="block px-4 py-3 rounded-lg <?= $activeNav === 'home' ? 'text-gold bg-gold/10 border-l-3 border-gold' : 'text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold'; ?> font-medium transition-all">Home</a>
            
            <!-- Blog -->
            <a href="/blog/" @click="open = false" 
               class="block px-4 py-3 rounded-lg <?= $activeNav === 'blog' ? 'text-gold bg-gold/10 border-l-3 border-gold' : 'text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold'; ?> font-medium transition-all">Blog</a>
            
            <!-- About -->
            <a href="/about.php" @click="open = false" 
               class="block px-4 py-3 rounded-lg <?= $activeNav === 'about' ? 'text-gold bg-gold/10 border-l-3 border-gold' : 'text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold'; ?> font-medium transition-all">About</a>
            
            <!-- Contact -->
            <a href="/contact.php" @click="open = false" 
               class="block px-4 py-3 rounded-lg <?= $activeNav === 'contact' ? 'text-gold bg-gold/10 border-l-3 border-gold' : 'text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold'; ?> font-medium transition-all">Contact</a>
            
            <!-- Login/My Account -->
            <a href="<?= $isLoggedIn ? '/user/' : '/user/login.php'; ?>" @click="open = false" class="flex items-center px-4 py-3 rounded-lg text-gray-300 border-l-3 border-transparent hover:bg-navy-light hover:text-gold font-medium transition-all">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"/>
                    <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8zm8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1z"/>
                </svg>
                <span><?= $isLoggedIn ? 'My Account' : 'Login'; ?></span>
            </a>
            
            <!-- Become an Affiliate -->
            <a href="/affiliate/register.php" @click="open = false" class="btn-premium-gold block px-4 py-3 rounded-lg text-navy font-semibold text-center transition-all mt-2">Become an Affiliate</a>
        </div>
    </div>
</nav>

