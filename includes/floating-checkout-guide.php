<?php
/**
 * Floating Checkout Guide - Professional Speech Bubble Design
 * Clean, polished onboarding with smooth pointer
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';

if (!$isIndexPage) return;
?>

<!-- Professional Checkout Guide with Speech Bubble -->
<div id="floating-checkout-guide" class="fixed z-[1001] font-sans" style="display: none;">
    <style>
        @keyframes slideInGuide {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-10px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }
        
        #floating-checkout-guide .guide-card {
            animation: slideInGuide 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: #ffffff;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12), 0 2px 8px rgba(0, 0, 0, 0.08);
            border-radius: 16px;
            position: relative;
        }
        
        /* Speech bubble pointer */
        #floating-checkout-guide .guide-card::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 12px solid transparent;
            border-right: 12px solid transparent;
            border-bottom: 12px solid #ffffff;
            filter: drop-shadow(0 -2px 4px rgba(0, 0, 0, 0.08));
        }
    </style>
    
    <div class="guide-card text-gray-900 p-4 max-w-xs">
        <!-- Content -->
        <div class="flex items-start gap-3">
            <!-- Icon with Badge -->
            <div class="flex-shrink-0 relative">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1h7a1 1 0 00.894-.553l3-6A1 1 0 0017 6H6.28l-.31-1.243A1 1 0 005 4H3z"/>
                        <path d="M16 16a2 2 0 11-4 0 2 2 0 014 0zM4 12a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <!-- New Badge -->
                <div class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">New</div>
            </div>
            
            <!-- Text & Button -->
            <div class="flex-1">
                <p class="text-sm font-bold text-gray-900">Items Added!</p>
                <p class="text-xs text-gray-600 mt-1">Click to view and checkout</p>
                
                <!-- Action Button -->
                <button onclick="toggleCartDrawer(); document.getElementById('floating-checkout-guide').style.display='none';" class="mt-3 w-full bg-gray-900 hover:bg-gray-800 text-white font-semibold py-2 px-3 rounded-lg transition-all text-sm">
                    Got it!
                </button>
            </div>
            
            <!-- Close X -->
            <button onclick="document.getElementById('floating-checkout-guide').style.display='none';" class="flex-shrink-0 text-gray-400 hover:text-gray-600 transition-colors p-0.5">
                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
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
            guide.style.top = '75px';
            guide.style.right = '15px';
            guide.style.left = 'auto';
            guide.style.bottom = 'auto';
        } else {
            guide.style.top = '75px';
            guide.style.right = '12px';
            guide.style.left = 'auto';
            guide.style.bottom = 'auto';
        }
    }
    
    function showGuide() {
        guide.style.display = 'block';
        positionGuide();
        
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
    
    window.addEventListener('cart-updated', showGuide);
    
    window.addEventListener('resize', () => {
        if (guide.style.display !== 'none') {
            positionGuide();
        }
    });
})();
</script>
