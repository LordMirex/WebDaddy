// Smart Loader Controller - Only show on first visit, skip on back/navigation
(function() {
  const LOADER_SHOWN_KEY = 'webdaddy_loader_shown';
  const loader = document.getElementById('page-loader');
  if (!loader) return;

  // Check if we should show loader
  const shouldShowLoader = () => {
    // Check if loader was already shown in this session
    const loaderShown = sessionStorage.getItem(LOADER_SHOWN_KEY);
    
    // If navigation type is back, always skip loader
    if (window.performance && window.performance.navigation) {
      if (window.performance.navigation.type === 2) {
        // type 2 = back button
        return false;
      }
    }
    
    // If coming from another page in same session, skip loader
    if (loaderShown === 'true') {
      return false;
    }
    
    // First visit - show loader
    return true;
  };

  // If we shouldn't show loader, hide it immediately
  if (!shouldShowLoader()) {
    document.body.classList.remove('loader-active');
    loader.style.display = 'none';
    loader.classList.add('loader-hidden');
    return;
  }

  // Mark that we've shown the loader
  sessionStorage.setItem(LOADER_SHOWN_KEY, 'true');

  // Original loader logic
  let loaderDismissed = false;
  const BREATHING_CYCLES = 2;
  const CYCLE_DURATION = 600;
  const DISPLAY_TIME = (BREATHING_CYCLES * CYCLE_DURATION) + 300;

  // Critical assets to preload during loader display
  const criticalAssets = [
    '/assets/images/webdaddy-logo.png',
    '/assets/images/mockups/viralcuts.jpg',
    '/assets/images/mockups/jasper-ai.jpg',
    '/assets/images/mockups/webflow.jpg'
  ];

  // Preload critical images
  function preloadCriticalAssets() {
    criticalAssets.forEach(src => {
      const img = new Image();
      img.src = src;
    });
  }

  preloadCriticalAssets();

  function dismissLoader() {
    if (loaderDismissed) return;
    loaderDismissed = true;

    loader.classList.add('loader-exit');
    document.body.classList.remove('loader-active');

    setTimeout(() => {
      loader.classList.add('loader-hidden');
      loader.remove();
    }, 300);
  }

  // Dismiss after 2 normal breaths + 3rd breath zoom/evaporate
  setTimeout(dismissLoader, DISPLAY_TIME);
})();