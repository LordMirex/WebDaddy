<?php
/**
 * Floating Checkout Guide - Tutorial for users
 * Shows a friendly floating guide on the index page to help users discover the checkout feature
 * ALWAYS include on the page, just show/hide with JavaScript
 */

$isIndexPage = strpos($_SERVER['REQUEST_URI'], 'index.php') !== false || $_SERVER['REQUEST_URI'] === '/';

if (!$isIndexPage) return;
?>

<!-- Checkout Reminder Guide -->
<div id="floating-checkout-guide" class="fixed z-[1001] font-sans pointer-events-none" style="display: none;">
    <!-- Guide Card -->
    <div class="relative bg-gradient-to-br from-blue-500 via-blue-600 to-blue-700 text-white px-6 py-5 rounded-2xl shadow-2xl pointer-events-auto backdrop-blur-md border border-blue-400/40 max-w-xs">
        <!-- Pulsing background effect -->
        <div class="absolute inset-0 rounded-2xl opacity-0 bg-white/10" style="animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;"></div>
        
        <div class="relative z-10">
            <div class="flex items-start gap-3">
                <!-- Icon with animation -->
                <div class="flex-shrink-0">
                    <div class="relative w-10 h-10 bg-yellow-400 rounded-full flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-700 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="flex-1">
                    <h3 class="text-lg font-bold mb-1 leading-tight">Your items are ready! 🎉</h3>
                    <p class="text-sm leading-relaxed mb-3 opacity-95">You have items waiting. Complete your order now before they're gone!</p>
                    
                    <!-- Action button -->
                    <a href="/cart-checkout.php" class="inline-flex items-center gap-2 bg-yellow-400 hover:bg-yellow-300 text-blue-700 font-bold py-2 px-4 rounded-lg transition-all active:scale-95 whitespace-nowrap">
                        <span>Go to Checkout</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                        </svg>
                    </a>
                </div>
                
                <!-- Close button -->
                <button onclick="document.getElementById('floating-checkout-guide').style.display = 'none';" class="flex-shrink-0 text-white/70 hover:text-white transition-colors active:scale-90 p-1.5" aria-label="Close">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>
            
            <!-- Progress indicator -->
            <div class="mt-3 h-1 bg-blue-400/30 rounded-full overflow-hidden">
                <div class="h-full bg-yellow-400 rounded-full" style="animation: slideInProgress 8s linear forwards;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Pulsing Cart Badge -->
<style>
    #cart-button.pulse-attention::after,
    #cart-button-mobile-icon.pulse-attention::after {
        content: '';
        position: absolute;
        inset: -8px;
        border: 2px solid #fbbf24;
        border-radius: 50%;
        animation: pulse-ring 1.5s ease-out infinite;
    }
</style>

<script>
(function() {
    const guide = document.getElementById('floating-checkout-guide');
    const cartButtonDesktop = document.getElementById('cart-button');
    const cartButtonMobile = document.getElementById('cart-button-mobile-icon');
    let showTimeout;
    let hasShownGuide = false;
    const GUIDE_SHOWN_KEY = 'checkout_guide_shown_' + new Date().toDateString();
    let cartCount = 0;
    
    // Monitor cart count
    async function updateCartCount() {
        try {
            const response = await fetch('/api/cart-data.php');
            const data = await response.json();
            cartCount = data.count || 0;
            return cartCount;
        } catch (e) {
            return 0;
        }
    }
    
    function positionGuide() {
        const isDesktop = cartButtonDesktop && window.getComputedStyle(cartButtonDesktop).display !== 'none';
        const cartBtn = isDesktop ? cartButtonDesktop : cartButtonMobile;
        
        if (!cartBtn) return;
        
        const rect = cartBtn.getBoundingClientRect();
        const padding = 16;
        
        if (isDesktop) {
            // Desktop: position above cart icon
            guide.style.top = Math.max(padding, rect.top - 220) + 'px';
            guide.style.left = 'auto';
            guide.style.right = Math.max(padding, window.innerWidth - rect.right - 10) + 'px';
            guide.style.bottom = 'auto';
        } else {
            // Mobile: position in center-top area
            guide.style.top = (rect.bottom + 20) + 'px';
            guide.style.left = padding + 'px';
            guide.style.right = padding + 'px';
            guide.style.bottom = 'auto';
        }
    }
    
    async function showGuide() {
        // Check if guide was already shown today
        if (sessionStorage.getItem(GUIDE_SHOWN_KEY) === 'true') {
            return;
        }
        
        // Make sure there are items in cart
        const count = await updateCartCount();
        if (count <= 0) return;
        
        hasShownGuide = true;
        positionGuide();
        guide.style.display = 'block';
        guide.style.opacity = '1';
        guide.style.transition = 'none';
        
        // Animate in
        setTimeout(() => {
            guide.querySelector('div').style.animation = 'slideIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1)';
        }, 10);
        
        // Mark as shown
        sessionStorage.setItem(GUIDE_SHOWN_KEY, 'true');
        
        // Add pulse animation to cart button
        if (cartButtonDesktop) cartButtonDesktop.classList.add('pulse-attention');
        if (cartButtonMobile) cartButtonMobile.classList.add('pulse-attention');
        
        // Auto-hide after 8 seconds
        setTimeout(() => {
            guide.style.transition = 'opacity 0.4s ease';
            guide.style.opacity = '0';
            setTimeout(() => {
                guide.style.display = 'none';
                guide.style.opacity = '1';
                guide.style.transition = 'none';
            }, 400);
        }, 8000);
    }
    
    // Listen for cart updates - show guide immediately
    window.addEventListener('cart-updated', async () => {
        clearTimeout(showTimeout);
        const count = await updateCartCount();
        if (count > 0) {
            // Reset guide for this new product
            sessionStorage.removeItem(GUIDE_SHOWN_KEY);
            hasShownGuide = false;
            // Show immediately
            showGuide();
        }
    });
    
    // Initialize cart count on load
    window.addEventListener('load', async () => {
        await updateCartCount();
        if (cartCount > 0) {
            // Random reminder every 60-90 seconds if not shown yet
            function scheduleReminder() {
                const delay = Math.random() * 30000 + 60000;
                setTimeout(async () => {
                    const count = await updateCartCount();
                    if (count > 0 && sessionStorage.getItem(GUIDE_SHOWN_KEY) !== 'true') {
                        showGuide();
                    }
                    scheduleReminder();
                }, delay);
            }
            scheduleReminder();
        }
    });
    
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
                    transform: scale(0.85) translateY(-30px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            @keyframes pulse {
                0%, 100% { opacity: 0; }
                50% { opacity: 0.15; }
            }
            
            @keyframes pulse-ring {
                0% {
                    transform: scale(1);
                    opacity: 1;
                    box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7);
                }
                100% {
                    transform: scale(1.5);
                    opacity: 0;
                    box-shadow: 0 0 0 10px rgba(251, 191, 36, 0);
                }
            }
            
            @keyframes slideInProgress {
                from {
                    width: 100%;
                }
                to {
                    width: 0%;
                }
            }
        `;
        document.head.appendChild(style);
    }
})();
</script>
