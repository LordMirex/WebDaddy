// Initialize Alpine data after page loads
// This separates complex logic from inline x-data attributes for CSP compatibility

document.addEventListener('DOMContentLoaded', function() {
    // Initialize navigation menu
    if (typeof Alpine !== 'undefined') {
        // Simple data objects without methods to pass CSP restrictions
        // Methods will be called from this external file instead
        window.navMenu = {
            open: false
        };
    }
});
