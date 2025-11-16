/**
 * Performance Optimizations - Phase 9.2
 * RequestAnimationFrame optimizations for smooth animations and scrolling
 */

// Debounce function for performance
function debounce(func, wait = 100) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Throttle function for scroll/resize events
function throttle(func, limit = 100) {
    let inThrottle;
    return function(...args) {
        if (!inThrottle) {
            func.apply(this, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}

// Optimized scroll handler with requestAnimationFrame
class ScrollOptimizer {
    constructor() {
        this.ticking = false;
        this.lastScrollY = window.scrollY;
        this.callbacks = [];
        
        this.init();
    }

    init() {
        window.addEventListener('scroll', () => {
            this.lastScrollY = window.scrollY;
            this.requestTick();
        }, { passive: true });
    }

    requestTick() {
        if (!this.ticking) {
            requestAnimationFrame(() => {
                this.update();
                this.ticking = false;
            });
            this.ticking = true;
        }
    }

    update() {
        this.callbacks.forEach(callback => {
            callback(this.lastScrollY);
        });
    }

    addCallback(callback) {
        if (typeof callback === 'function') {
            this.callbacks.push(callback);
        }
    }

    removeCallback(callback) {
        const index = this.callbacks.indexOf(callback);
        if (index > -1) {
            this.callbacks.splice(index, 1);
        }
    }
}

// Optimized resize handler
class ResizeOptimizer {
    constructor() {
        this.ticking = false;
        this.callbacks = [];
        
        this.init();
    }

    init() {
        window.addEventListener('resize', () => {
            this.requestTick();
        }, { passive: true });
    }

    requestTick() {
        if (!this.ticking) {
            requestAnimationFrame(() => {
                this.update();
                this.ticking = false;
            });
            this.ticking = true;
        }
    }

    update() {
        this.callbacks.forEach(callback => {
            callback(window.innerWidth, window.innerHeight);
        });
    }

    addCallback(callback) {
        if (typeof callback === 'function') {
            this.callbacks.push(callback);
        }
    }

    removeCallback(callback) {
        const index = this.callbacks.indexOf(callback);
        if (index > -1) {
            this.callbacks.splice(index, 1);
        }
    }
}

// Smooth scroll to top with requestAnimationFrame
function smoothScrollTo(targetY, duration = 600) {
    const startY = window.scrollY;
    const distance = targetY - startY;
    const startTime = performance.now();

    function step(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // Easing function (easeInOutCubic)
        const ease = progress < 0.5
            ? 4 * progress * progress * progress
            : 1 - Math.pow(-2 * progress + 2, 3) / 2;
        
        window.scrollTo(0, startY + distance * ease);
        
        if (progress < 1) {
            requestAnimationFrame(step);
        }
    }

    requestAnimationFrame(step);
}

// Navbar scroll effect (show/hide on scroll)
function initNavbarScrollEffect() {
    const navbar = document.getElementById('mainNav');
    if (!navbar) return;

    let lastScrollY = window.scrollY;
    let navbarHeight = navbar.offsetHeight;

    const scrollOptimizer = new ScrollOptimizer();
    
    scrollOptimizer.addCallback((scrollY) => {
        if (scrollY > navbarHeight) {
            if (scrollY > lastScrollY) {
                // Scrolling down - hide navbar
                navbar.style.transform = `translateY(-${navbarHeight}px)`;
            } else {
                // Scrolling up - show navbar
                navbar.style.transform = 'translateY(0)';
            }
        } else {
            navbar.style.transform = 'translateY(0)';
        }
        lastScrollY = scrollY;
    });

    // Add smooth transition
    navbar.style.transition = 'transform 0.3s ease-in-out';
}

// Prefetch links on hover (for faster navigation)
function initLinkPrefetch() {
    const prefetchedUrls = new Set();

    document.addEventListener('mouseover', (e) => {
        const link = e.target.closest('a[href]');
        if (!link) return;

        const href = link.href;
        
        // Only prefetch internal links
        if (href && href.startsWith(window.location.origin) && !prefetchedUrls.has(href)) {
            prefetchedUrls.add(href);
            
            const prefetchLink = document.createElement('link');
            prefetchLink.rel = 'prefetch';
            prefetchLink.href = href;
            document.head.appendChild(prefetchLink);
        }
    }, { passive: true });
}

// Optimize images: add loading="lazy" attribute to images without it
function optimizeImages() {
    const images = document.querySelectorAll('img:not([loading])');
    images.forEach(img => {
        // Skip if image is above the fold (in viewport)
        const rect = img.getBoundingClientRect();
        const isAboveFold = rect.top < window.innerHeight && rect.bottom > 0;
        
        if (!isAboveFold) {
            img.loading = 'lazy';
        }
    });
}

// Critical CSS loader (load non-critical CSS asynchronously)
function loadDeferredStyles() {
    const deferredStyles = document.querySelectorAll('link[rel="preload"][as="style"]');
    deferredStyles.forEach(link => {
        link.rel = 'stylesheet';
    });
}

// Initialize all performance optimizations
function initPerformanceOptimizations() {
    // Wait for DOM to be fully loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            runOptimizations();
        });
    } else {
        runOptimizations();
    }
}

function runOptimizations() {
    // Optimize images
    optimizeImages();
    
    // Load deferred styles
    loadDeferredStyles();
    
    // Init link prefetch
    initLinkPrefetch();
    
    // Navbar scroll effect (optional, commented out by default)
    // initNavbarScrollEffect();
}

// Initialize optimizers
const scrollOptimizer = new ScrollOptimizer();
const resizeOptimizer = new ResizeOptimizer();

// Export for global access
window.scrollOptimizer = scrollOptimizer;
window.resizeOptimizer = resizeOptimizer;
window.smoothScrollTo = smoothScrollTo;
window.debounce = debounce;
window.throttle = throttle;

// Auto-initialize
initPerformanceOptimizations();
