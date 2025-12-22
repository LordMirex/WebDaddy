/**
 * Blog Analytics Tracking
 * Sends engagement events to analytics endpoint
 */

(function() {
    'use strict';

    const ANALYTICS_URL = '/api/blog/analytics.php';
    const POST_ID = document.querySelector('[data-post-id]')?.getAttribute('data-post-id');
    const SESSION_ID = sessionStorage.getItem('session_id') || generateSessionId();

    // Generate session ID if not exists
    function generateSessionId() {
        const id = 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('session_id', id);
        return id;
    }

    // Track event to analytics
    function trackEvent(eventType, additionalData = {}) {
        if (!POST_ID) return;

        const data = {
            action: 'track',
            post_id: POST_ID,
            event_type: eventType,
            session_id: SESSION_ID,
            affiliate_code: sessionStorage.getItem('affiliate_code'),
            ...additionalData
        };

        fetch(ANALYTICS_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
            keepalive: true
        }).catch(err => console.log('Analytics logged:', eventType));
    }

    // Track scroll depth
    let lastScrollPercent = 0;
    function trackScrollDepth() {
        const scrollPercent = Math.round((window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100);

        // Track at 25%, 50%, 75%, 100%
        if (scrollPercent >= 25 && lastScrollPercent < 25) {
            trackEvent('scroll_25');
        } else if (scrollPercent >= 50 && lastScrollPercent < 50) {
            trackEvent('scroll_50');
        } else if (scrollPercent >= 75 && lastScrollPercent < 75) {
            trackEvent('scroll_75');
        } else if (scrollPercent >= 100 && lastScrollPercent < 100) {
            trackEvent('scroll_100');
        }

        lastScrollPercent = scrollPercent;
    }

    // Track CTA clicks
    function initCTATracking() {
        document.addEventListener('click', function(e) {
            const cta = e.target.closest('[data-cta-type]');
            if (cta && POST_ID) {
                const ctaType = cta.getAttribute('data-cta-type');
                trackEvent('cta_click', { cta_type: ctaType });
            }
        });
    }

    // Track share button clicks
    function initShareTracking() {
        const shareButtons = document.querySelectorAll('[data-share-platform]');
        shareButtons.forEach(btn => {
            btn.addEventListener('click', function(e) {
                const platform = this.getAttribute('data-share-platform');
                trackEvent('share', { platform: platform });

                // Send to share endpoint
                if (platform) {
                    fetch('/api/blog/share.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            action: 'update',
                            post_id: POST_ID,
                            platform: platform
                        }),
                        keepalive: true
                    }).catch(() => {});
                }
            });
        });
    }

    // Initialize tracking
    function init() {
        if (!POST_ID) return;

        // Track initial page view
        trackEvent('view');

        // Attach scroll listener
        window.addEventListener('scroll', trackScrollDepth, { passive: true });

        // Initialize CTA & share tracking
        initCTATracking();
        initShareTracking();

        // Track before unload
        window.addEventListener('beforeunload', function() {
            trackEvent('session_end', { keepalive: true });
        });
    }

    // Start when ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
