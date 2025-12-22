/**
 * Smooth Navigation System - Prevents page reloads, handles back/forward smoothly
 * Uses History API + AJAX to load pages without full reload
 */
(function() {
  const MAIN_CONTENT_ID = 'main-content';
  const CACHE_KEY = 'navCache_';
  const CACHE_DURATION = 10 * 60 * 1000; // 10 minutes
  
  // Pages that should NOT use smooth navigation (require full reload)
  const EXCLUDE_PATTERNS = [
    '/user/',
    '/blog/',
    '/blog',
    '/cart-checkout.php',
    '/api/',
    '/admin/',
    '.pdf',
    '.json'
  ];
  
  let isNavigating = false;
  let pageCache = new Map();
  let currentUrl = window.location.href;
  
  /**
   * Check if link should use smooth navigation
   */
  function shouldUseSmoothNav(href) {
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return false;
    if (!href.startsWith('/')) return false;
    
    return !EXCLUDE_PATTERNS.some(pattern => href.includes(pattern));
  }
  
  /**
   * Extract main content from HTML
   */
  function extractContent(html) {
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      
      // Get main content
      const mainContent = doc.getElementById(MAIN_CONTENT_ID) || 
                          doc.querySelector('main') ||
                          doc.querySelector('.content');
      
      // Get header for nav updates
      const header = doc.querySelector('nav') || doc.getElementById('mainNav');
      
      return {
        main: mainContent ? mainContent.innerHTML : html,
        header: header ? header.outerHTML : null,
        title: doc.title,
        url: doc.querySelector('meta[property="og:url"]')?.content || currentUrl
      };
    } catch (e) {
      console.error('Failed to extract content:', e);
      return null;
    }
  }
  
  /**
   * Load page via AJAX
   */
  async function loadPageAjax(url) {
    try {
      // Check cache first
      const cached = getCachedPage(url);
      if (cached) return cached;
      
      const response = await fetch(url, {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/html'
        }
      });
      
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      
      const html = await response.text();
      const content = extractContent(html);
      
      if (content) {
        cachePage(url, content);
        return content;
      }
    } catch (error) {
      console.error('Failed to load page:', error);
    }
    
    return null;
  }
  
  /**
   * Cache page content
   */
  function cachePage(url, content) {
    pageCache.set(url, {
      content,
      timestamp: Date.now()
    });
  }
  
  /**
   * Get cached page if not expired
   */
  function getCachedPage(url) {
    const cached = pageCache.get(url);
    if (!cached) return null;
    
    const age = Date.now() - cached.timestamp;
    if (age > CACHE_DURATION) {
      pageCache.delete(url);
      return null;
    }
    
    return cached.content;
  }
  
  /**
   * Update page content smoothly
   */
  async function updatePageContent(url) {
    if (isNavigating) return;
    isNavigating = true;
    
    try {
      const content = await loadPageAjax(url);
      if (!content) {
        // Fallback to traditional navigation
        window.location.href = url;
        return;
      }
      
      // Update main content with fade effect
      const mainEl = document.getElementById(MAIN_CONTENT_ID) || 
                     document.querySelector('main') ||
                     document.querySelector('.content');
      
      if (mainEl) {
        // Fade out
        mainEl.style.opacity = '0';
        mainEl.style.transition = 'opacity 0.3s ease';
        
        await new Promise(r => setTimeout(r, 300));
        
        // Update content
        mainEl.innerHTML = content.main;
        
        // Update header if available
        if (content.header) {
          const headerEl = document.querySelector('nav') || document.getElementById('mainNav');
          if (headerEl) {
            const newHeader = document.createElement('div');
            newHeader.innerHTML = content.header;
            headerEl.replaceWith(newHeader.firstElementChild);
          }
        }
        
        // Update page title
        if (content.title) {
          document.title = content.title;
        }
        
        // Re-initialize scripts for new content
        reinitializePageScripts();
        
        // Fade in
        mainEl.style.opacity = '1';
        
        // Update history
        window.history.pushState({ url }, content.title, url);
        currentUrl = url;
        
        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    } catch (error) {
      console.error('Navigation error:', error);
      window.location.href = url;
    } finally {
      isNavigating = false;
    }
  }
  
  /**
   * Re-initialize scripts for dynamically loaded content
   */
  function reinitializePageScripts() {
    // Re-initialize cart badge if cart was loaded
    if (window.updateCartBadge) {
      window.updateCartBadge();
    }
    
    // Re-trigger any AJAX loaders for dynamic content
    if (window.location.pathname === '/' || window.location.pathname.includes('index.php')) {
      // Trigger product AJAX loaders if needed
      const loaders = document.querySelectorAll('[data-ajax-load]');
      loaders.forEach(el => {
        if (el.dataset.ajaxLoad === 'products') {
          // Trigger product load
          const view = el.dataset.view || 'templates';
          fetch(`/api/ajax-products.php?action=load_view&view=${view}&page=1`)
            .then(r => r.text())
            .then(html => el.innerHTML = html)
            .catch(e => console.error('Product load failed:', e));
        }
      });
    }
  }
  
  /**
   * Handle back/forward button
   */
  window.addEventListener('popstate', (e) => {
    const url = window.location.href;
    
    // Clear isNavigating flag for back navigation
    isNavigating = false;
    
    // Reload content from new URL
    updatePageContent(url).catch(err => {
      console.error('Popstate navigation failed:', err);
      window.location.href = url;
    });
  });
  
  /**
   * Intercept navigation link clicks
   */
  document.addEventListener('click', async (e) => {
    const link = e.target.closest('a[href]');
    if (!link) return;
    
    const href = link.getAttribute('href');
    if (!shouldUseSmoothNav(href)) return;
    
    // Don't interfere with special handlers or buttons with onclick
    if (link.onclick || link.target) return;
    if (link.hasAttribute('onclick')) return;
    
    // Skip cart buttons and other special UI elements
    if (link.id && (link.id.includes('cart') || link.id.includes('toggle') || link.id.includes('modal'))) return;
    
    e.preventDefault();
    
    // Skip if same page
    const absoluteHref = new URL(href, window.location.origin).href;
    if (absoluteHref === currentUrl) return;
    
    // Show progress bar
    const progressBar = document.getElementById('nav-progress-bar');
    if (progressBar) {
      progressBar.style.opacity = '1';
      progressBar.style.width = '30%';
    }
    
    // Load page
    await updatePageContent(href);
    
    // Hide progress bar
    if (progressBar) {
      progressBar.style.width = '100%';
      setTimeout(() => {
        progressBar.style.opacity = '0';
        progressBar.style.width = '0%';
      }, 500);
    }
  }, true);
  
  // Store initial page in cache
  cachePage(currentUrl, {
    title: document.title,
    main: document.getElementById(MAIN_CONTENT_ID)?.innerHTML || 
          document.querySelector('main')?.innerHTML,
  });
})();
