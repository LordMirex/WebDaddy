<?php
/**
 * Floating Checkout Guide - Responsive Mobile & Desktop
 * Positioned like notification, doesn't cover checkout button
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';

if (!$isIndexPage) return;
?>

<!-- Checkout Guide Card - Positioned like notification -->
<div id="floating-checkout-guide" class="fixed z-[1001] font-sans pointer-events-none" style="display: none;">
    <!-- Guide Tooltip - Like the notification -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white px-4 py-3 rounded-xl shadow-lg backdrop-blur-sm border border-blue-400/30 pointer-events-auto flex items-start gap-3 max-w-xs">
        <!-- Icon -->
        <div class="flex-shrink-0 mt-0.5">
            <div class="w-9 h-9 bg-yellow-400 rounded-full flex items-center justify-center">
                <svg class="w-5 h-5 text-blue-700" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1h7a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"/>
                    <path d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM4 12a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
        </div>
        
        <!-- Content -->
        <div class="flex-1">
            <p class="text-sm font-bold leading-tight">Items ready! ✨</p>
            <p class="text-xs opacity-90 mt-1 leading-tight">Tap cart to checkout</p>
            <a href="/cart-checkout.php" class="inline-flex items-center gap-1.5 mt-2 bg-yellow-400 hover:bg-yellow-300 text-blue-700 font-bold py-1.5 px-3 rounded-lg transition-all text-xs whitespace-nowrap">
                <span>Checkout</span>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        </div>
        
        <!-- Close button -->
        <button onclick="document.getElementById('floating-checkout-guide').style.display='none';" class="flex-shrink-0 text-white/60 hover:text-white transition-colors p-0.5">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
            </svg>
        </button>
    </div>
</div>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    
    function positionGuide() {
        if (guide.style.display === 'none') return;
        
        // Check screen size
        const isDesktop = window.innerWidth >= 768;
        const padding = 16;
        
        if (isDesktop) {
            // Desktop: Top right, like notification (where the other notifications show)
            guide.style.top = '90px';
            guide.style.right = '20px';
            guide.style.left = 'auto';
            guide.style.bottom = 'auto';
        } else {
            // Mobile: Top of screen, full width with padding
            guide.style.top = '80px';
            guide.style.left = padding + 'px';
            guide.style.right = padding + 'px';
            guide.style.bottom = 'auto';
        }
    }
    
    function showGuide() {
        guide.style.display = 'block';
        guide.style.opacity = '0';
        guide.style.transition = 'none';
        
        // Force reflow
        void guide.offsetWidth;
        
        // Animate in
        guide.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        guide.style.opacity = '1';
        guide.firstElementChild.style.transform = 'scale(1) translateY(0)';
        
        positionGuide();
        
        // Auto-hide after 7 seconds
        setTimeout(() => {
            guide.style.transition = 'opacity 0.3s ease';
            guide.style.opacity = '0';
            setTimeout(() => {
                guide.style.display = 'none';
            }, 300);
        }, 7000);
    }
    
    // Listen for cart updated event
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
            #floating-checkout-guide > div {
                animation: notificationSlide 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            }
            
            @keyframes notificationSlide {
                from {
                    opacity: 0;
                    transform: scale(0.8) translateY(-20px);
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
