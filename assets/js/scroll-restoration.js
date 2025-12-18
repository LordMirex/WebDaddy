// Enhanced Scroll Position Restoration for Instant Navigation
(function() {
  const SCROLL_KEY = 'webdaddy_scroll_pos';
  
  // Save scroll position before page unload
  window.addEventListener('beforeunload', () => {
    const pageUrl = window.location.pathname;
    const scrollPositions = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || '{}');
    scrollPositions[pageUrl] = window.scrollY;
    sessionStorage.setItem(SCROLL_KEY, JSON.stringify(scrollPositions));
  });

  // Restore scroll position after page load
  window.addEventListener('load', () => {
    const pageUrl = window.location.pathname;
    const scrollPositions = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || '{}');
    const savedScroll = scrollPositions[pageUrl];
    
    if (savedScroll !== undefined && savedScroll > 0) {
      // Restore scroll position
      window.scrollTo(0, savedScroll);
    }
  });

  // Also handle instant.page navigation
  document.addEventListener('instantclick:receive', () => {
    const pageUrl = window.location.pathname;
    const scrollPositions = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || '{}');
    const savedScroll = scrollPositions[pageUrl];
    
    if (savedScroll !== undefined && savedScroll > 0) {
      setTimeout(() => window.scrollTo(0, savedScroll), 50);
    }
  });

  // Clear scroll position on normal link click to prevent jank
  document.addEventListener('click', (e) => {
    const link = e.target.closest('a');
    if (link && link.href && !link.target && !link.hasAttribute('data-instant-loading')) {
      // Link will navigate, scroll will be saved by beforeunload
    }
  });
})();