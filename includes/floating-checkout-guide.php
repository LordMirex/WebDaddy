<?php
/**
 * Floating Checkout Guide - Tutorial for users
 * Shows a friendly floating guide on the index page to help users discover the checkout feature
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';
$cartCount = getCartCount();

if (!$isIndexPage || $cartCount <= 0) return;
?>

<div id="floating-checkout-guide" class="fixed z-[1001] font-sans pointer-events-none" style="display: none;">
    <!-- Animated Background -->
    <div class="absolute inset-0 opacity-0" style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;"></div>
    
    <!-- Guide Tooltip -->
    <div class="relative bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 text-white px-5 py-4 rounded-2xl shadow-2xl pointer-events-auto backdrop-blur-md border border-white/20 max-w-sm">
        <!-- Top accent line -->
        <div class="absolute top-0 left-8 w-12 h-1 bg-gradient-to-r from-yellow-300 to-yellow-400 rounded-b-full"></div>
        
        <div class="flex items-start gap-3">
            <!-- Icon with pulse effect -->
            <div class="flex-shrink-0 mt-0.5">
                <div class="relative w-8 h-8">
                    <svg class="w-8 h-8 text-yellow-300 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <div class="absolute inset-0 rounded-full border-2 border-yellow-300 opacity-0" style="animation: pulse-ring 2s infinite;"></div>
                </div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 min-w-0">
                <h3 class="text-base font-bold leading-snug mb-1">Ready to checkout?</h3>
                <p class="text-sm opacity-95 leading-relaxed">Look for the <span class="font-semibold">🛒 cart icon</span> in the top right (or menu on mobile) to complete your order.</p>
                <div class="mt-2 text-xs opacity-80 flex items-center gap-1">
                    <span class="inline-block w-1 h-1 bg-yellow-300 rounded-full"></span>
                    <span>Tap or click to proceed</span>
                </div>
            </div>
            
            <!-- Close button -->
            <button onclick="document.getElementById('floating-checkout-guide').style.display = 'none';" class="flex-shrink-0 text-white/70 hover:text-white transition-colors active:scale-90 p-1" aria-label="Close">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
        
        <!-- Bottom accent -->
        <div class="absolute bottom-2 right-4 w-2 h-2 bg-yellow-300 rounded-full opacity-70"></div>
    </div>
</div>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    const cartButtonDesktop = document.getElementById('cart-button');
    const cartButtonMobile = document.getElementById('cart-button-mobile-icon');
    let showTimeout;
    let hasShownGuide = false;
    const GUIDE_SHOWN_KEY = 'checkout_guide_shown_' + new Date().toDateString();
    
    function positionGuide() {
        // Check which cart button is visible
        const isDesktop = cartButtonDesktop && window.getComputedStyle(cartButtonDesktop).display !== 'none';
        const cartBtn = isDesktop ? cartButtonDesktop : cartButtonMobile;
        
        if (!cartBtn) return;
        
        const rect = cartBtn.getBoundingClientRect();
        const padding = 20;
        const guideWidth = 300;
        
        if (isDesktop) {
            // Desktop: position above and to the left
            const topPos = rect.top - 180;
            const leftPos = rect.right - guideWidth - 10;
            
            guide.style.top = Math.max(padding, topPos) + 'px';
            guide.style.left = Math.max(padding, Math.min(leftPos, window.innerWidth - guideWidth - padding)) + 'px';
            guide.style.right = 'auto';
            guide.style.bottom = 'auto';
        } else {
            // Mobile: position below navbar, centered-ish
            guide.style.top = rect.bottom + 15 + 'px';
            guide.style.left = padding + 'px';
            guide.style.right = padding + 'px';
            guide.style.bottom = 'auto';
            guide.style.maxWidth = 'calc(100vw - ' + (padding * 2) + 'px)';
        }
    }
    
    function showGuide() {
        if (hasShownGuide) return;
        
        // Check if already shown today
        if (sessionStorage.getItem(GUIDE_SHOWN_KEY) === 'true') {
            return;
        }
        
        hasShownGuide = true;
        positionGuide();
        guide.style.display = 'block';
        guide.querySelector('div').style.animation = 'slideIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        
        // Mark as shown
        sessionStorage.setItem(GUIDE_SHOWN_KEY, 'true');
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            guide.style.opacity = '0';
            guide.style.transition = 'opacity 0.3s ease';
            setTimeout(() => {
                guide.style.display = 'none';
            }, 300);
        }, 8000);
    }
    
    // Listen for cart updates - show after 5 seconds
    window.addEventListener('cart-updated', () => {
        clearTimeout(showTimeout);
        hasShownGuide = false;
        sessionStorage.removeItem(GUIDE_SHOWN_KEY);
        showTimeout = setTimeout(showGuide, 5000);
    });
    
    // Random reminder every 45-90 seconds
    function scheduleRandomReminder() {
        const delay = Math.random() * 45000 + 45000;
        setTimeout(() => {
            if (!hasShownGuide && sessionStorage.getItem(GUIDE_SHOWN_KEY) !== 'true') {
                showGuide();
            }
            scheduleRandomReminder();
        }, delay);
    }
    
    scheduleRandomReminder();
    
    // Reposition on window resize
    window.addEventListener('resize', () => {
        if (guide.style.display !== 'none') {
            positionGuide();
        }
    });
    
    // Add animations
    if (!document.getElementById('checkout-guide-styles')) {
        const style = document.createElement('style');
        style.id = 'checkout-guide-styles';
        style.innerHTML = `
            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: scale(0.9) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 0; }
                50% { opacity: 0.1; }
            }
            
            @keyframes pulse-ring {
                0% {
                    transform: scale(1);
                    opacity: 1;
                }
                100% {
                    transform: scale(1.3);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
})();
</script>
