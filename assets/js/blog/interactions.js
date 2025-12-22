/**
 * WebDaddy Blog - Sticky Rail & Mobile CTA Interactions
 * Handles scroll-to-top, sticky sidebar behavior, and mobile CTA visibility
 */

(function() {
    'use strict';

    // Configuration
    const CONFIG = {
        scrollThreshold: 0.5, // Show scroll-to-top after 50% scroll
        stickyTopOffset: 100, // Sticky sidebar top offset (matches CSS)
        footerStopOffset: 100, // Stop sticky before footer
    };

    // State
    let state = {
        scrollToTopVisible: false,
        isMobile: window.innerWidth < 768,
        footerPosition: 0,
    };

    // DOM Elements
    let elements = {
        scrollToTopBtn: null,
        mobileCTA: null,
        sidebarSticky: null,
        article: null,
        footer: null,
    };

    /**
     * Initialize all elements
     */
    function initElements() {
        elements.sidebarSticky = document.querySelector('.blog-sidebar-sticky');
        elements.mobileCTA = document.querySelector('.blog-mobile-cta');
        elements.article = document.querySelector('.blog-article');
        elements.footer = document.querySelector('.blog-footer');

        // Create scroll-to-top button if not exists
        if (!document.querySelector('.blog-scroll-to-top')) {
            createScrollToTopButton();
        }
        elements.scrollToTopBtn = document.querySelector('.blog-scroll-to-top');
    }

    /**
     * Create scroll-to-top button
     */
    function createScrollToTopButton() {
        const btn = document.createElement('button');
        btn.className = 'blog-scroll-to-top';
        btn.setAttribute('aria-label', 'Scroll to top');
        btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="18 15 12 9 6 15"></polyline></svg>';
        btn.onclick = scrollToTop;
        document.body.appendChild(btn);
    }

    /**
     * Handle smooth scroll to top
     */
    function scrollToTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    /**
     * Calculate footer position for scroll stop
     */
    function updateFooterPosition() {
        if (!elements.footer) return;
        const rect = elements.footer.getBoundingClientRect();
        elements.footerPosition = rect.top + window.scrollY;
    }

    /**
     * Handle scroll events
     */
    function handleScroll() {
        if (!elements.scrollToTopBtn) return;

        const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight));
        const shouldShowBtn = scrollPercent >= CONFIG.scrollThreshold;

        // Update scroll-to-top visibility
        if (shouldShowBtn !== state.scrollToTopVisible) {
            state.scrollToTopVisible = shouldShowBtn;
            elements.scrollToTopBtn.classList.toggle('visible', shouldShowBtn);
        }

        // Update sticky sidebar position (stop before footer)
        updateStickyPosition();

        // Update mobile CTA bar visibility
        updateMobileCTAVisibility();
    }

    /**
     * Update sticky sidebar position to stop before footer
     */
    function updateStickyPosition() {
        if (!elements.sidebarSticky) return;

        const scrollY = window.scrollY;
        const footerTop = elements.footerPosition;
        const sidebarHeight = elements.sidebarSticky.offsetHeight;
        const viewportHeight = window.innerHeight;

        // Calculate where sticky should stop
        const stopPoint = footerTop - sidebarHeight - CONFIG.footerStopOffset;

        if (scrollY + viewportHeight > footerTop - CONFIG.footerStopOffset) {
            // User is near footer, adjust sticky position
            const maxTranslate = Math.max(0, stopPoint - CONFIG.stickyTopOffset);
            elements.sidebarSticky.style.transform = `translateY(${Math.max(0, scrollY - maxTranslate)}px)`;
        } else {
            // Normal sticky behavior
            elements.sidebarSticky.style.transform = 'translateY(0)';
        }
    }

    /**
     * Update mobile CTA visibility based on scroll
     */
    function updateMobileCTAVisibility() {
        if (!elements.mobileCTA || window.innerWidth >= 768) return;

        const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight));

        // Show mobile CTA after 30% scroll
        if (scrollPercent > 0.3) {
            elements.mobileCTA.classList.add('visible');
        } else {
            elements.mobileCTA.classList.remove('visible');
        }

        // Hide before footer
        if (window.innerHeight + window.scrollY > elements.footer.offsetTop - 100) {
            elements.mobileCTA.classList.add('at-footer');
        } else {
            elements.mobileCTA.classList.remove('at-footer');
        }
    }

    /**
     * Handle window resize
     */
    function handleResize() {
        const wasMobile = state.isMobile;
        state.isMobile = window.innerWidth < 768;

        if (wasMobile !== state.isMobile) {
            // Mobile state changed
            if (state.isMobile) {
                // Switch to mobile mode
                if (elements.mobileCTA) {
                    elements.mobileCTA.style.display = 'flex';
                }
            } else {
                // Switch to desktop mode
                if (elements.mobileCTA) {
                    elements.mobileCTA.style.display = 'none';
                }
            }
        }

        updateFooterPosition();
    }

    /**
     * Handle affiliate code in URL
     */
    function handleAffiliateCode() {
        const params = new URLSearchParams(window.location.search);
        const affCode = params.get('aff');

        if (affCode) {
            // Store affiliate code in session storage
            sessionStorage.setItem('affiliate_code', affCode);
            
            // Update all internal links to include affiliate code
            updateLinksWithAffiliateCode(affCode);
        }
    }

    /**
     * Update all internal links with affiliate code
     */
    function updateLinksWithAffiliateCode(affCode) {
        const links = document.querySelectorAll('a[href*="/#templates"], a[href*="/templates"]');
        links.forEach(link => {
            const href = link.href;
            const separator = href.includes('?') ? '&' : '?';
            if (!href.includes('aff=')) {
                link.href = href + separator + 'aff=' + encodeURIComponent(affCode);
            }
        });
    }

    /**
     * Handle scroll-to-top button click animation
     */
    function initScrollToTopButton() {
        if (elements.scrollToTopBtn) {
            elements.scrollToTopBtn.addEventListener('click', (e) => {
                e.preventDefault();
                scrollToTop();
                // Add animation
                elements.scrollToTopBtn.classList.add('clicked');
                setTimeout(() => {
                    elements.scrollToTopBtn.classList.remove('clicked');
                }, 300);
            });
        }
    }

    /**
     * Initialize mobile CTA bar animations
     */
    function initMobileCTABar() {
        if (!elements.mobileCTA) return;

        // Add smooth entrance animation
        elements.mobileCTA.style.transition = 'all 0.3s ease';

        // Handle WhatsApp button
        const whatsappBtn = elements.mobileCTA.querySelector('.blog-mobile-cta-whatsapp');
        if (whatsappBtn) {
            whatsappBtn.addEventListener('click', (e) => {
                // Track click if analytics available
                trackEvent('mobile_cta_whatsapp_click');
            });
        }

        // Handle Templates button
        const templatesBtn = elements.mobileCTA.querySelector('.blog-mobile-cta-templates');
        if (templatesBtn) {
            templatesBtn.addEventListener('click', (e) => {
                trackEvent('mobile_cta_templates_click');
            });
        }
    }

    /**
     * Track conversion events
     */
    function trackEvent(eventName) {
        // This will be connected to analytics in Phase 5.4
        console.log('Event tracked:', eventName);
        
        // Send to analytics endpoint if available
        const analyticsUrl = '/api/blog/analytics.php';
        const post_id = document.querySelector('[data-post-id]')?.getAttribute('data-post-id');
        
        if (post_id) {
            fetch(analyticsUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    post_id: post_id,
                    event_type: eventName,
                    session_id: sessionStorage.getItem('session_id'),
                    affiliate_code: sessionStorage.getItem('affiliate_code')
                })
            }).catch(err => console.log('Analytics not yet available'));
        }
    }

    /**
     * Initialize all interactions
     */
    function init() {
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initAfterDOM);
        } else {
            initAfterDOM();
        }
    }

    /**
     * Initialize after DOM is loaded
     */
    function initAfterDOM() {
        initElements();
        updateFooterPosition();
        initScrollToTopButton();
        initMobileCTABar();
        handleAffiliateCode();

        // Attach event listeners
        window.addEventListener('scroll', handleScroll, { passive: true });
        window.addEventListener('resize', handleResize, { passive: true });

        // Initial scroll handling
        handleScroll();
        handleResize();
    }

    // Start initialization
    init();
})();
