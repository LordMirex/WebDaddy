<?php
/**
 * Floating Checkout Guide - Modern Glassmorphism Design (2025)
 * Premium onboarding with semi-transparent backdrop blur effect
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';

if (!$isIndexPage) return;
?>

<!-- Modern Glassmorphic Checkout Guide -->
<div id="floating-checkout-guide" class="fixed z-[1001] font-sans" style="display: none;">
    <style>
        @supports (backdrop-filter: blur(10px)) {
            #floating-checkout-guide .glass-card {
                background: rgba(255, 193, 7, 0.85);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border: 1px solid rgba(255, 255, 255, 0.3);
            }
        }
        
        #floating-checkout-guide .glass-card {
            background: rgba(255, 193, 7, 0.95);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15), 
                        0 8px 24px rgba(0, 0, 0, 0.1),
                        inset 0 1px 1px rgba(255, 255, 255, 0.5);
        }
        
        #floating-checkout-guide .arrow-pointer {
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.12));
        }
        
        @keyframes slideInGuide {
            from {
                opacity: 0;
                transform: scale(0.85) translateY(-15px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        #floating-checkout-guide .glass-card {
            animation: slideInGuide 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
    </style>
    
    <div class="relative">
        <!-- Glass Card with Blur Effect -->
        <div class="glass-card text-slate-900 rounded-3xl p-5 max-w-xs border-0 shadow-2xl">
            <!-- Content Container -->
            <div class="flex items-start gap-3">
                <!-- Shopping Cart Icon - Circular Background -->
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
                    <p class="text-sm font-bold text-slate-900 leading-tight">Items Added!</p>
                    <p class="text-xs text-slate-800 mt-1.5 leading-snug">Tap to view and checkout</p>
                    
                    <!-- Action Button -->
                    <button onclick="toggleCartDrawer(); document.getElementById('floating-checkout-guide').style.display='none';" class="mt-3 w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-2.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg hover:shadow-xl">
                        <span>Open Cart</span>
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1h7a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"/>
                            <path d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM4 12a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </button>
                </div>
                
                <!-- Close X Button -->
                <button onclick="document.getElementById('floating-checkout-guide').style.display='none';" class="flex-shrink-0 text-slate-700 hover:text-slate-900 transition-colors p-1 mt-0.5 hover:bg-white/50 rounded-full">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Premium Arrow Pointer - Pointing UP to checkout icon -->
        <div class="arrow-pointer absolute top-[-16px] right-[55px]">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Arrow pointing upward -->
                <path d="M14 0L28 14L14 28L0 14Z" fill="rgba(255, 193, 7, 0.95)"/>
            </svg>
        </div>
    </div>
</div>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    
    function positionGuide() {
        if (guide.style.display === 'none') return;
        
        const isDesktop = window.innerWidth >= 768;
        
        if (isDesktop) {
            // Desktop: Top-right near checkout icon
            guide.style.top = '75px';
            guide.style.right = '15px';
            guide.style.left = 'auto';
            guide.style.bottom = 'auto';
        } else {
            // Mobile: Top-right near checkout icon
            guide.style.top = '75px';
            guide.style.right = '12px';
            guide.style.left = 'auto';
            guide.style.bottom = 'auto';
        }
    }
    
    function showGuide() {
        guide.style.display = 'block';
        positionGuide();
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            if (guide.style.display !== 'none') {
                guide.style.opacity = '0';
                guide.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    guide.style.display = 'none';
                    guide.style.opacity = '1';
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
