// Blog Image Validator - Ensures only working images display
document.addEventListener('DOMContentLoaded', function() {
    validateBlogImages();
});

function validateBlogImages() {
    const blogImages = document.querySelectorAll('[data-validate-image]');
    
    blogImages.forEach(img => {
        const placeholderId = img.getAttribute('data-placeholder-id');
        
        // Create image to test loading
        const testImg = new Image();
        testImg.onload = function() {
            // Image loaded successfully, keep showing it
            img.classList.remove('image-loading');
            img.classList.add('image-loaded');
        };
        testImg.onerror = function() {
            // Image failed to load, show placeholder
            showImagePlaceholder(img, placeholderId);
        };
        
        // Set timeout for slow/unresponsive images
        const timeoutId = setTimeout(function() {
            if (!img.classList.contains('image-loaded')) {
                showImagePlaceholder(img, placeholderId);
            }
        }, 5000);
        
        img.onload = function() {
            clearTimeout(timeoutId);
            img.classList.remove('image-loading');
            img.classList.add('image-loaded');
        };
        
        img.onerror = function() {
            clearTimeout(timeoutId);
            showImagePlaceholder(img, placeholderId);
        };
        
        // Start loading
        testImg.src = img.src;
        img.classList.add('image-loading');
    });
}

function showImagePlaceholder(img, placeholderId) {
    img.style.display = 'none';
    img.classList.add('image-broken');
    
    const parent = img.parentElement;
    const placeholder = parent.querySelector('#' + placeholderId);
    if (placeholder) {
        placeholder.style.display = 'flex';
    }
}

// Retry failed images periodically
setInterval(function() {
    const brokenImages = document.querySelectorAll('.image-broken');
    brokenImages.forEach(img => {
        // Try reloading every 30 seconds
        const testImg = new Image();
        testImg.onload = function() {
            img.style.display = '';
            img.classList.remove('image-broken');
            const parent = img.parentElement;
            const placeholder = parent.querySelector('[id$="-placeholder"]');
            if (placeholder) {
                placeholder.style.display = 'none';
            }
        };
        testImg.src = img.src;
    });
}, 30000);
