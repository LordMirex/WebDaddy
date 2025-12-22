// Enhanced Scroll Position Restoration for Instant Navigation
(function() {
  const SCROLL_KEY = 'webdaddy_scroll_pos';
  const LOADER_SHOWN_KEY = 'webdaddy_loader_shown';
  
  // Save scroll position before page unload
  window.addEventListener('beforeunload', () => {
    const pageUrl = window.location.pathname;
    const scrollPositions = JSON.parse(sessionStorage.getItem(SCROLL_KEY) || '{}');
    scrollPositions[pageUrl] = window.scrollY;
    sessionStorage.setItem(SCROLL_KEY, JSON.stringify(scrollPositions));
  });

  // Restore scroll position after page load (for back button)
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
})();