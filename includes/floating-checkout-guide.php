<?php
/**
 * Floating Checkout Guide - Simple & Always Ready
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';

if (!$isIndexPage) return;
?>

<!-- Checkout Guide Card -->
<div id="floating-checkout-guide" class="fixed z-[1001] font-sans pointer-events-none" style="display: none; top: 100px; right: 20px;">
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-5 py-4 rounded-2xl shadow-2xl backdrop-blur-sm border border-blue-400/30 max-w-sm pointer-events-auto">
        <div class="flex items-start gap-3">
            <!-- Shopping Cart Icon -->
            <div class="flex-shrink-0">
                <div class="w-10 h-10 bg-yellow-400 rounded-full flex items-center justify-center flex-shrink-0">
                    <svg class="w-6 h-6 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1h7a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"/>
                        <path d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM4 12a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
            </div>
            
            <!-- Content -->
            <div class="flex-1">
                <h3 class="text-lg font-bold mb-1">Items in Cart! 🎉</h3>
                <p class="text-sm opacity-90 mb-3">Click below to checkout now</p>
                <a href="/cart-checkout.php" class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-300 text-blue-700 font-bold py-2 px-4 rounded-lg transition-all active:scale-95 text-sm whitespace-nowrap">
                    <span>Go to Checkout</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
            
            <!-- Close button -->
            <button onclick="document.getElementById('floating-checkout-guide').style.display='none';" class="text-white/60 hover:text-white transition-colors p-1 flex-shrink-0">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    const cartButtonDesktop = document.getElementById('cart-button');
    const cartButtonMobile = document.getElementById('cart-button-mobile-icon');
    let guideTimeout;
    
    function positionGuide() {
        const isDesktop = cartButtonDesktop && window.getComputedStyle(cartButtonDesktop).display !== 'none';
        const cartBtn = isDesktop ? cartButtonDesktop : cartButtonMobile;
        
        if (!cartBtn) return;
        
        const rect = cartBtn.getBoundingClientRect();
        
        if (isDesktop) {
            guide.style.top = Math.max(20, rect.top - 160) + 'px';
            guide.style.right = Math.max(20, window.innerWidth - rect.right - 10) + 'px';
            guide.style.left = 'auto';
        } else {
            guide.style.top = (rect.bottom + 15) + 'px';
            guide.style.left = '20px';
            guide.style.right = '20px';
        }
    }
    
    function showGuide() {
        guide.style.display = 'block';
        guide.firstElementChild.style.animation = 'none';
        setTimeout(() => {
            guide.firstElementChild.style.animation = 'slideInGuide 0.4s ease-out';
            positionGuide();
        }, 10);
        
        // Auto-hide after 8 seconds
        clearTimeout(guideTimeout);
        guideTimeout = setTimeout(() => {
            guide.style.display = 'none';
        }, 8000);
    }
    
    // Show guide when product is added to cart
    window.addEventListener('cart-updated', showGuide);
    
    // Reposition on resize
    window.addEventListener('resize', () => {
        if (guide.style.display !== 'none') {
            positionGuide();
        }
    });
    
    // Add animation styles
    if (!window.checkoutGuideStylesAdded) {
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes slideInGuide {
                from {
                    opacity: 0;
                    transform: scale(0.85) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
        window.checkoutGuideStylesAdded = true;
    }
})();
</script>
