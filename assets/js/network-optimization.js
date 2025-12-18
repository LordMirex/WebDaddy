// Aggressive Network & Cache Optimization for Desktop & Mobile
(function() {
  // Pre-cache all navigation links for instant loading
  const CACHE_NAME = 'webdaddy-cache-v1';
  const PREFETCH_URLS = [
    '/',
    '/about.php',
    '/contact.php',
    '/faq.php',
    '/careers.php',
    '/blog/',
    '/affiliate/register.php'
  ];

  // Register service worker for offline support and caching
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/service-worker.php', { scope: '/' })
      .then(() => console.log('✅ Service Worker registered for offline speed'))
      .catch(() => {
        // Service worker failed, fall back to prefetch
        prefetchCriticalPages();
      });
  } else {
    prefetchCriticalPages();
  }

  function prefetchCriticalPages() {
    if ('requestIdleCallback' in window) {
      requestIdleCallback(() => prefetchPages());
    } else {
      setTimeout(prefetchPages, 2000);
    }
  }

  function prefetchPages() {
    PREFETCH_URLS.forEach(url => {
      const link = document.createElement('link');
      link.rel = 'prefetch';
      link.href = url;
      document.head.appendChild(link);
    });
  }

  // Preload critical assets
  function preloadCriticalAssets() {
    const criticalAssets = [
      { href: '/assets/css/premium.css', as: 'style' },
      { href: '/assets/js/performance.js', as: 'script' },
      { href: '/assets/images/webdaddy-logo.png', as: 'image' }
    ];

    criticalAssets.forEach(asset => {
      if (!document.querySelector(`link[href="${asset.href}"]`)) {
        const link = document.createElement('link');
        link.rel = 'preload';
        link.href = asset.href;
        link.as = asset.as;
        link.crossOrigin = asset.as === 'font' ? 'anonymous' : '';
        document.head.appendChild(link);
      }
    });
  }

  // Optimize network connection timing
  function optimizeNetworkTiming() {
    // Use high priority for critical resources
    const observer = new PerformanceObserver((list) => {
      for (const entry of list.getEntries()) {
        if (entry.duration > 1000) {
          // Log slow resources for monitoring
          console.warn(`⚠️ Slow resource: ${entry.name} (${entry.duration.toFixed(0)}ms)`);
        }
      }
    });

    observer.observe({ entryTypes: ['resource', 'navigation'] });
  }

  // Cache images aggressively
  function setupImageCaching() {
    const images = document.querySelectorAll('img[src]');
    images.forEach(img => {
      if (!img.hasAttribute('loading')) {
        img.setAttribute('loading', 'lazy');
      }
      if (!img.hasAttribute('decoding')) {
        img.setAttribute('decoding', 'async');
      }
    });
  }

  // Defer non-critical scripts
  function deferNonCriticalScripts() {
    const scripts = document.querySelectorAll('script:not([async]):not([defer]):not([type="application/ld+json"])');
    scripts.forEach(script => {
      if (!script.src) return;
      if (!script.src.includes('instant.page') && !script.src.includes('alpine')) {
        script.defer = true;
      }
    });
  }

  // Minimize repaints/reflows
  function optimizeRenderingPath() {
    const style = document.createElement('style');
    style.textContent = `
      * { box-sizing: border-box; }
      img { max-width: 100%; height: auto; }
      video { max-width: 100%; height: auto; }
    `;
    document.head.appendChild(style);
  }

  // Execute optimizations
  document.addEventListener('DOMContentLoaded', () => {
    preloadCriticalAssets();
    setupImageCaching();
    optimizeRenderingPath();
    optimizeNetworkTiming();
  });

  // Prefetch on idle
  if ('requestIdleCallback' in window) {
    requestIdleCallback(() => {
      deferNonCriticalScripts();
      prefetchCriticalPages();
    });
  }
})();