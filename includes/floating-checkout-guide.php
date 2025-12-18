<?php
/**
 * Floating Checkout Guide - Tutorial for users
 * Shows a floating guide on the index page to help users discover the checkout feature
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';
$cartCount = getCartCount();

if (!$isIndexPage || $cartCount <= 0) return;
?>

<div id="floating-checkout-guide" class="fixed z-[1001] font-sans pointer-events-none" style="display: none;">
    <!-- Guide Tooltip -->
    <div class="flex items-start gap-2 bg-gradient-to-br from-purple-600 to-purple-700 text-white px-4 py-3 rounded-lg shadow-lg pointer-events-auto backdrop-blur-sm border border-purple-400/30">
        <!-- Icon -->
        <div class="flex-shrink-0">
            <svg class="w-5 h-5 text-yellow-300 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
            </svg>
        </div>
        
        <!-- Content -->
        <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold leading-tight">Ready to checkout?</p>
            <p class="text-xs opacity-90 mt-0.5">Click the cart icon to proceed →</p>
        </div>
        
        <!-- Close button -->
        <button onclick="document.getElementById('floating-checkout-guide').style.display = 'none';" class="flex-shrink-0 text-purple-200 hover:text-white transition-colors ml-1">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
        
        <!-- Arrow pointing to cart icon -->
        <div class="absolute w-3 h-3 bg-gradient-to-br from-purple-600 to-purple-700 border-t border-r border-purple-400/30" style="transform: rotate(45deg);"></div>
    </div>
</div>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    const cartButtonDesktop = document.getElementById('cart-button');
    const cartButtonMobile = document.getElementById('cart-button-mobile-icon');
    let showTimeout;
    let hasShownGuide = false;
    const GUIDE_SHOWN_KEY = 'checkout_guide_shown_session';
    
    function positionGuide() {
        // Determine which cart button is visible
        const cartBtn = window.getComputedStyle(cartButtonDesktop).display !== 'none' ? cartButtonDesktop : cartButtonMobile;
        if (!cartBtn) return;
        
        const rect = cartBtn.getBoundingClientRect();
        const isDesktop = window.getComputedStyle(cartButtonDesktop).display !== 'none';
        
        // Position guide near the cart icon
        if (isDesktop) {
            // Desktop: position to the left of cart icon
            guide.style.bottom = 'auto';
            guide.style.top = (rect.top - 120) + 'px';
            guide.style.right = (window.innerWidth - rect.left + 15) + 'px';
            guide.style.left = 'auto';
        } else {
            // Mobile: position above cart icon
            guide.style.bottom = (window.innerHeight - rect.top + 15) + 'px';
            guide.style.top = 'auto';
            guide.style.right = '20px';
            guide.style.left = 'auto';
        }
    }
    
    function showGuide() {
        if (hasShownGuide) return;
        
        // Check if already shown in this session
        if (sessionStorage.getItem(GUIDE_SHOWN_KEY) === 'true') {
            return;
        }
        
        hasShownGuide = true;
        positionGuide();
        guide.style.display = 'block';
        guide.querySelector('div').classList.add('animate-fade-in');
        
        // Mark as shown in session
        sessionStorage.setItem(GUIDE_SHOWN_KEY, 'true');
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            guide.style.display = 'none';
        }, 8000);
    }
    
    // Listen for cart updates
    window.addEventListener('cart-updated', () => {
        clearTimeout(showTimeout);
        // Show guide 10 seconds after product is added
        showTimeout = setTimeout(showGuide, 10000);
    });
    
    // Random reminder every 30-60 seconds if guide hasn't been shown
    function scheduleRandomReminder() {
        const delay = Math.random() * 30000 + 30000; // 30-60 seconds
        setTimeout(() => {
            if (!hasShownGuide && sessionStorage.getItem(GUIDE_SHOWN_KEY) !== 'true') {
                showGuide();
            }
            scheduleRandomReminder();
        }, delay);
    }
    
    // Start the reminder loop
    scheduleRandomReminder();
    
    // Reposition guide on window resize
    window.addEventListener('resize', () => {
        if (guide.style.display !== 'none') {
            positionGuide();
        }
    });
    
    // Add CSS animations if not already present
    if (!document.getElementById('checkout-guide-styles')) {
        const style = document.createElement('style');
        style.id = 'checkout-guide-styles';
        style.innerHTML = `
            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }
            
            .animate-fade-in {
                animation: fadeIn 0.3s ease-out;
            }
        `;
        document.head.appendChild(style);
    }
})();
</script>
