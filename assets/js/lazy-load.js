/**
 * Lazy Loading System - Phase 9.1
 * Optimized image lazy loading with IntersectionObserver
 */

class LazyLoader {
    constructor(options = {}) {
        this.options = {
            rootMargin: options.rootMargin || '200px',
            threshold: options.threshold || 0.01,
            loadingClass: options.loadingClass || 'lazy-loading',
            loadedClass: options.loadedClass || 'lazy-loaded',
            errorClass: options.errorClass || 'lazy-error'
        };
        
        this.observer = null;
        this.loadedCount = 0;
        this.init();
        this.preloadAboveFold();
    }
    
    preloadAboveFold() {
        const viewportHeight = window.innerHeight;
        const images = document.querySelectorAll('img[data-src], img[loading="lazy"]');
        images.forEach(img => {
            const rect = img.getBoundingClientRect();
            if (rect.top < viewportHeight * 1.5) {
                this.loadImage(img);
            }
        });
    }

    init() {
        if (!('IntersectionObserver' in window)) {
            // Fallback: load all images immediately
            this.loadAllImages();
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadImage(entry.target);
                }
            });
        }, {
            rootMargin: this.options.rootMargin,
            threshold: this.options.threshold
        });

        this.observeImages();
    }

    observeImages() {
        const images = document.querySelectorAll('img[data-src], img[loading="lazy"]');
        images.forEach(img => {
            // Skip if already loaded
            if (img.classList.contains(this.options.loadedClass)) {
                return;
            }
            
            // Add loading class
            img.classList.add(this.options.loadingClass);
            
            // Observe the image
            this.observer.observe(img);
        });
    }

    loadImage(img) {
        if (img.classList.contains(this.options.loadedClass)) return;
        
        const src = img.dataset.src || img.src;
        
        if (!src) {
            if (this.observer) this.observer.unobserve(img);
            return;
        }

        img.src = src;
        img.classList.remove(this.options.loadingClass);
        img.classList.add(this.options.loadedClass);
        
        if (img.dataset.src) {
            delete img.dataset.src;
        }
        
        if (this.observer) this.observer.unobserve(img);
        this.loadedCount++;
    }

    loadAllImages() {
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            if (img.dataset.src) {
                img.src = img.dataset.src;
                delete img.dataset.src;
            }
        });
    }

    refresh() {
        if (this.observer) {
            this.observeImages();
        }
    }

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}

// Initialize on DOM ready
function initLazyLoader() {
    if (!window.lazyLoader) {
        window.lazyLoader = new LazyLoader();
        console.log('LazyLoader initialized');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initLazyLoader);
} else {
    initLazyLoader();
}
