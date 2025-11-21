/**
 * Social Sharing Functions
 * Vanilla JavaScript - No dependencies
 */

// Template data (populated from page)
const TEMPLATE_DATA = {
    url: window.location.href,
    title: document.querySelector('title')?.textContent || '',
    description: document.querySelector('meta[name="description"]')?.content || '',
    image: document.querySelector('meta[property="og:image"]')?.content || '',
    slug: null
};

// Extract slug from canonical URL (more reliable than parsing window.location)
(function initializeSlug() {
    const canonicalUrl = document.querySelector('link[rel="canonical"]')?.href || window.location.href;
    const pathname = new URL(canonicalUrl).pathname;
    
    // Remove leading/trailing slashes and extract slug
    const slug = pathname.replace(/^\/+|\/+$/g, '').split('/').pop() || '';
    
    TEMPLATE_DATA.slug = slug;
})();

/**
 * Share via WhatsApp
 */
function shareViaWhatsApp() {
    const text = `Check out this amazing template: ${TEMPLATE_DATA.title}!\n\n${TEMPLATE_DATA.url}`;
    const whatsappUrl = `https://wa.me/?text=${encodeURIComponent(text)}`;
    
    window.open(whatsappUrl, '_blank', 'width=600,height=400');
    
    // Track share event (analytics)
    if (typeof trackShare === 'function') {
        trackShare('whatsapp', TEMPLATE_DATA.url);
    }
}

/**
 * Share via Facebook
 */
function shareViaFacebook() {
    const facebookUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(TEMPLATE_DATA.url)}`;
    
    window.open(facebookUrl, '_blank', 'width=600,height=400');
    
    // Track share event
    if (typeof trackShare === 'function') {
        trackShare('facebook', TEMPLATE_DATA.url);
    }
}

/**
 * Share via Twitter/X
 */
function shareViaTwitter() {
    const twitterText = `${TEMPLATE_DATA.title}`;
    const twitterUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(twitterText)}&url=${encodeURIComponent(TEMPLATE_DATA.url)}`;
    
    window.open(twitterUrl, '_blank', 'width=600,height=400');
    
    // Track share event
    if (typeof trackShare === 'function') {
        trackShare('twitter', TEMPLATE_DATA.url);
    }
}

/**
 * Copy template link to clipboard (deprecated - use copyTemplateLink or copyToolShareLink)
 */
async function copyTemplateLink() {
    try {
        // Modern Clipboard API
        if (navigator.clipboard && navigator.clipboard.writeText) {
            await navigator.clipboard.writeText(TEMPLATE_DATA.url);
        } else {
            // Fallback for older browsers
            const tempInput = document.createElement('input');
            tempInput.value = TEMPLATE_DATA.url;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand('copy');
            document.body.removeChild(tempInput);
        }
        
        // Show success message
        const successMsg = document.getElementById('copy-success');
        if (successMsg) {
            successMsg.classList.remove('hidden');
            
            // Hide after 3 seconds
            setTimeout(() => {
                successMsg.classList.add('hidden');
            }, 3000);
        }
        
        // Track copy event
        if (typeof trackShare === 'function') {
            trackShare('copy_link', TEMPLATE_DATA.url);
        }
        
    } catch (err) {
        console.error('Failed to copy link:', err);
        alert('Failed to copy link. Please copy manually: ' + TEMPLATE_DATA.url);
    }
}

/**
 * Track share events (optional analytics)
 */
function trackShare(platform, url) {
    // Send to analytics endpoint
    const data = new FormData();
    data.append('action', 'track_share');
    data.append('platform', platform);
    data.append('url', url);
    data.append('template_slug', TEMPLATE_DATA.slug);
    
    fetch('/api/analytics.php', {
        method: 'POST',
        body: data
    }).catch(err => console.log('Analytics tracking failed:', err));
}
