// Smart Loader Controller - Only show on first visit, skip on all navigation
(function() {
  const LOADER_SHOWN_KEY = 'webdaddy_loader_shown';
  
  // Immediately check and hide loader BEFORE DOM rendering
  const loaderShown = sessionStorage.getItem(LOADER_SHOWN_KEY);
  
  // If loader was shown before, immediately remove the class and hide loader on THIS page load
  if (loaderShown === 'true') {
    document.body.classList.remove('loader-active');
    // Hide via CSS override at highest priority
    const style = document.createElement('style');
    style.textContent = '#page-loader { display: none !important; visibility: hidden !important; opacity: 0 !important; }';
    document.head.appendChild(style);
    return;
  }

  // First visit - mark that loader will be shown
  sessionStorage.setItem(LOADER_SHOWN_KEY, 'true');
  
  const loader = document.getElementById('page-loader');
  if (!loader) return;

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