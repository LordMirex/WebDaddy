/**
 * Lazy Loading System - Phase 9.1
 * Optimized image lazy loading with IntersectionObserver
 */

class LazyLoader {
    constructor(options = {}) {
        this.options = {
            rootMargin: options.rootMargin || '50px',
            threshold: options.threshold || 0.01,
            loadingClass: options.loadingClass || 'lazy-loading',
            loadedClass: options.loadedClass || 'lazy-loaded',
            errorClass: options.errorClass || 'lazy-error'
        };
        
        this.observer = null;
        this.init();
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
        const src = img.dataset.src || img.src;
        
        if (!src) {
            this.observer.unobserve(img);
            return;
        }

        // Create a new image to preload
        const tempImg = new Image();
        
        tempImg.onload = () => {
            // Use requestAnimationFrame for smooth rendering
            requestAnimationFrame(() => {
                img.src = src;
                img.classList.remove(this.options.loadingClass);
                img.classList.add(this.options.loadedClass);
                
                // Remove data-src to prevent reloading
                if (img.dataset.src) {
                    delete img.dataset.src;
                }
                
                // Unobserve the image
                this.observer.unobserve(img);
            });
        };
        
        tempImg.onerror = () => {
            img.classList.remove(this.options.loadingClass);
            img.classList.add(this.options.errorClass);
            this.observer.unobserve(img);
        };
        
        tempImg.src = src;
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
let lazyLoader;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        lazyLoader = new LazyLoader();
    });
} else {
    lazyLoader = new LazyLoader();
}

// Export for global access
window.lazyLoader = lazyLoader;
