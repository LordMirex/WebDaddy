<?php
/**
 * Floating Checkout Guide - Arrow pointing to checkout button
 * Positioned right next to checkout icon
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';

if (!$isIndexPage) return;
?>

<!-- Checkout Guide with Arrow -->
<div id="floating-checkout-guide" class="fixed z-[1001] font-sans" style="display: none;">
    <div class="relative">
        <!-- Main Guide Card -->
        <div class="bg-gradient-to-br from-yellow-400 to-yellow-500 text-slate-900 rounded-2xl shadow-2xl p-4 max-w-sm border-2 border-yellow-300">
            <!-- Content -->
            <div class="flex items-start gap-3">
                <!-- Icon -->
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-white rounded-full flex items-center justify-center shadow-md">
                        <svg class="w-6 h-6 text-yellow-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1h7a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"/>
                            <path d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM4 12a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                </div>
                
                <!-- Text & Button -->
                <div class="flex-1">
                    <p class="text-sm font-bold text-slate-900 leading-tight">✨ Items Added!</p>
                    <p class="text-xs text-slate-800 mt-2 leading-snug">Click below to see your cart and checkout</p>
                    
                    <!-- Main Action Button -->
                    <button onclick="toggleCartDrawer(); document.getElementById('floating-checkout-guide').style.display='none';" class="w-full mt-3 bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 rounded-lg transition-all flex items-center justify-center gap-2 shadow-lg">
                        <span>Open Cart</span>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1h7a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"/>
                            <path d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM4 12a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Close X Button -->
                <button onclick="document.getElementById('floating-checkout-guide').style.display='none';" class="flex-shrink-0 text-slate-700 hover:text-slate-900 transition-colors p-1 mt-0.5">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Arrow Pointer (pointing down-right to checkout button) -->
        <div class="absolute bottom-[-12px] right-[60px] w-6 h-6 bg-yellow-500 border-2 border-yellow-300 transform rotate-45"></div>
    </div>
</div>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    const cartIcon = document.querySelector('[data-cart-button]') || document.querySelector('a[href*="cart"]') || document.querySelector('button[onclick*="cart"]');
    
    function positionGuide() {
        if (guide.style.display === 'none') return;
        
        const isDesktop = window.innerWidth >= 768;
        
        if (isDesktop) {
            // Desktop: Below and to the LEFT of checkout button (near icon)
            guide.style.bottom = 'auto';
            guide.style.top = '75px';
            guide.style.right = '15px';
            guide.style.left = 'auto';
        } else {
            // Mobile: Below and to the LEFT of checkout button
            guide.style.bottom = 'auto';
            guide.style.top = '75px';
            guide.style.right = '12px';
            guide.style.left = 'auto';
        }
    }
    
    function showGuide() {
        guide.style.display = 'block';
        guide.style.opacity = '0';
        guide.style.transform = 'scale(0.8) translateY(-10px)';
        guide.style.transition = 'none';
        
        // Force reflow
        void guide.offsetWidth;
        
        // Animate in
        guide.style.transition = 'opacity 0.4s cubic-bezier(0.34, 1.56, 0.64, 1), transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        guide.style.opacity = '1';
        guide.style.transform = 'scale(1) translateY(0)';
        
        positionGuide();
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            if (guide.style.display !== 'none') {
                guide.style.transition = 'opacity 0.3s ease';
                guide.style.opacity = '0';
                setTimeout(() => {
                    guide.style.display = 'none';
                }, 300);
            }
        }, 8000);
    }
    
    // Listen for cart updated event
    window.addEventListener('cart-updated', showGuide);
    
    // Reposition on resize
    window.addEventListener('resize', () => {
        if (guide.style.display !== 'none') {
            positionGuide();
        }
    });
})();
</script>
