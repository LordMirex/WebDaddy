/**
 * Smart Navigation - Instant Feedback & Pre-loading
 * Shows loading state immediately on click, pre-fetches on hover
 */
(function() {
  // Create loading indicator
  const createProgressBar = () => {
    if (document.getElementById('nav-progress-bar')) return;
    
    const bar = document.createElement('div');
    bar.id = 'nav-progress-bar';
    bar.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      height: 3px;
      background: linear-gradient(90deg, #D4AF37, #F5D669, #D4AF37);
      box-shadow: 0 0 15px rgba(212, 175, 55, 0.8);
      z-index: 10000;
      transition: width 0.3s ease-out;
      width: 0%;
      opacity: 0;
    `;
    document.body.appendChild(bar);
    return bar;
  };

  const progressBar = createProgressBar();
  let progressInterval;

  // Start progress bar animation
  const startProgress = () => {
    progressBar.style.opacity = '1';
    let width = 10;
    
    progressInterval = setInterval(() => {
      width += Math.random() * 30;
      width = Math.min(width, 90);
      progressBar.style.width = width + '%';
    }, 200);
  };

  // Complete progress bar
  const completeProgress = () => {
    clearInterval(progressInterval);
    progressBar.style.width = '100%';
    
    setTimeout(() => {
      progressBar.style.opacity = '0';
      progressBar.style.width = '0%';
    }, 500);
  };

  // Pre-fetch link on hover
  const prefetchOnHover = (link) => {
    const href = link.getAttribute('href');
    if (!href || href.startsWith('#') || !href.startsWith('/')) return;
    
    if (!document.querySelector(`link[href="${href}"][rel="prefetch"]`)) {
      const prefetch = document.createElement('link');
      prefetch.rel = 'prefetch';
      prefetch.href = href;
      document.head.appendChild(prefetch);
    }
  };

  // Handle navigation link clicks
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;

    const href = link.getAttribute('href');
    
    // Only handle internal navigation links (not anchors, not external)
    if (!href || href.startsWith('#') || !href.startsWith('/')) return;
    
    // Don't interfere with special handlers (like cart button)
    if (link.onclick) return;

    // Show loading immediately
    startProgress();
    
    // Complete progress when page starts loading
    window.addEventListener('beforeunload', completeProgress, { once: true });
  }, true);

  // Pre-fetch on hover for instant readiness
  document.addEventListener('mouseover', (e) => {
    const link = e.target.closest('a[href]');
    if (link) {
      prefetchOnHover(link);
    }
  }, { passive: true });

  // Add instant feedback styling
  const addNavLinkFeedback = () => {
    const style = document.createElement('style');
    style.textContent = `
      /* Instant visual feedback on nav links */
      #mainNav a[href]:not([onclick]) {
        position: relative;
        transition: all 0.15s ease-out;
      }

      #mainNav a[href]:not([onclick]):active {
        opacity: 0.7;
        transform: scale(0.98);
      }

      #mainNav a[href]:not([onclick]):hover {
        transition: all 0.2s ease-out;
      }

      /* Progress bar smooth animation */
      #nav-progress-bar {
        will-change: width, opacity;
        pointer-events: none;
      }
    `;
    document.head.appendChild(style);
  };

  // Initialize when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', addNavLinkFeedback);
  } else {
    addNavLinkFeedback();
  }

  // Export for external use
  window.navSmartness = {
    startProgress,
    completeProgress,
    prefetch: prefetchOnHover
  };
})();
