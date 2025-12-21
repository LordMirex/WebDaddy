/**
 * Alpine.js Fallback
 * Provides vanilla JavaScript alternatives when Alpine.js fails to load
 * Handles menu toggles, cart drawer, and other interactive elements
 */

(function() {
    // Wait for DOM to be ready
    function initFallbacks() {
        // Mobile menu toggle
        const mobileMenuButton = document.querySelector('[aria-label="Toggle menu"]');
        const mobileMenu = document.querySelector('[x-show="open"]')?.parentElement;
        
        if (mobileMenuButton && mobileMenu) {
            let menuOpen = false;
            mobileMenuButton.addEventListener('click', function() {
                menuOpen = !menuOpen;
                if (menuOpen) {
                    mobileMenu.style.display = 'block';
                    mobileMenu.classList.remove('hidden');
                } else {
                    mobileMenu.style.display = 'none';
                    mobileMenu.classList.add('hidden');
                }
            });
        }
        
        // Cart drawer toggle
        const cartButtons = document.querySelectorAll('#cart-button, #cart-button-mobile-icon');
        cartButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const cartDrawer = document.getElementById('cart-drawer');
                if (cartDrawer) {
                    const isVisible = cartDrawer.style.display !== 'none' && cartDrawer.offsetParent !== null;
                    cartDrawer.style.display = isVisible ? 'none' : 'block';
                }
            });
        });
        
        // Ensure all Alpine x-show elements are visible by default if Alpine doesn't load
        setTimeout(() => {
            if (typeof Alpine === 'undefined') {
                // Alpine failed to load - make interactive elements work with vanilla JS
                document.querySelectorAll('[x-show]').forEach(el => {
                    // Show all x-show elements by default since Alpine isn't working
                    el.style.display = 'block';
                });
                
                // Add fallback for any x-data elements
                document.querySelectorAll('[x-data]').forEach(el => {
                    // Make sure they're visible
                    el.style.visibility = 'visible';
                    el.style.opacity = '1';
                });
            }
        }, 2000); // Wait 2 seconds for Alpine to load
    }
    
    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initFallbacks);
    } else {
        initFallbacks();
    }
})();
