class VideoPreloader {
    constructor() {
        this.preloadedVideos = new Map();
        this.maxPreloadedVideos = 3;
        this.preloadQueue = new Set();
        this.isSlowConnection = false;
        this.pageFullyLoaded = false;
        this.preloadDelay = 2000;
        
        this.init();
    }
    
    init() {
        this.detectConnectionSpeed();
        
        window.addEventListener('load', () => {
            setTimeout(() => {
                this.pageFullyLoaded = true;
                this.setupPreloading();
            }, this.preloadDelay);
        });
        
        if (document.readyState === 'complete') {
            setTimeout(() => {
                this.pageFullyLoaded = true;
                this.setupPreloading();
            }, this.preloadDelay);
        }
    }
    
    detectConnectionSpeed() {
        if ('connection' in navigator) {
            const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            
            if (conn.saveData) {
                this.isSlowConnection = true;
                console.log('📡 Data Saver mode detected - preloading on hover only');
                return;
            }
            
            const effectiveType = conn.effectiveType;
            if (effectiveType === 'slow-2g' || effectiveType === '2g') {
                this.isSlowConnection = true;
                console.log('📡 Slow connection detected - preloading on hover only');
                return;
            }
            
            console.log('📡 Fast connection detected - aggressive preloading enabled');
        }
    }
    
    setupPreloading() {
        this.attachHoverListeners();
        
        if (!this.isSlowConnection) {
            this.setupIntersectionObserver();
        }
    }
    
    attachHoverListeners() {
        const attachToElements = (elements) => {
            elements.forEach(element => {
                if (element.dataset.preloadAttached) return;
                
                const videoUrl = element.dataset.videoUrl;
                if (!videoUrl) return;
                
                element.addEventListener('mouseenter', () => {
                    this.preloadVideo(videoUrl);
                }, { once: false });
                
                element.dataset.preloadAttached = 'true';
            });
        };
        
        const videoTriggers = document.querySelectorAll('[data-video-trigger]');
        attachToElements(videoTriggers);
        
        const watchButtons = document.querySelectorAll('button[onclick*="openVideoModal"]');
        watchButtons.forEach(button => {
            if (button.dataset.preloadAttached) return;
            
            const onclickAttr = button.getAttribute('onclick');
            const urlMatch = onclickAttr.match(/openVideoModal\(['"]([^'"]+)['"]/);
            
            if (urlMatch && urlMatch[1]) {
                const videoUrl = urlMatch[1];
                button.addEventListener('mouseenter', () => {
                    this.preloadVideo(videoUrl);
                }, { once: false });
                
                button.dataset.preloadAttached = 'true';
            }
        });
        
        const observer = new MutationObserver(() => {
            const newVideoTriggers = document.querySelectorAll('[data-video-trigger]:not([data-preload-attached])');
            attachToElements(newVideoTriggers);
            
            const newWatchButtons = document.querySelectorAll('button[onclick*="openVideoModal"]:not([data-preload-attached])');
            newWatchButtons.forEach(button => {
                if (button.dataset.preloadAttached) return;
                
                const onclickAttr = button.getAttribute('onclick');
                const urlMatch = onclickAttr.match(/openVideoModal\(['"]([^'"]+)['"]/);
                
                if (urlMatch && urlMatch[1]) {
                    const videoUrl = urlMatch[1];
                    button.addEventListener('mouseenter', () => {
                        this.preloadVideo(videoUrl);
                    }, { once: false });
                    
                    button.dataset.preloadAttached = 'true';
                }
            });
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    setupIntersectionObserver() {
        const options = {
            root: null,
            rootMargin: '200px',
            threshold: 0.1
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && this.pageFullyLoaded) {
                    const videoUrl = entry.target.dataset.videoUrl;
                    if (videoUrl) {
                        setTimeout(() => {
                            this.preloadVideo(videoUrl, true);
                        }, 500);
                    }
                }
            });
        }, options);
        
        const observeElements = () => {
            const videoTriggers = document.querySelectorAll('[data-video-trigger]');
            videoTriggers.forEach(element => {
                if (!element.dataset.observing) {
                    observer.observe(element);
                    element.dataset.observing = 'true';
                }
            });
        };
        
        observeElements();
        
        const mutationObserver = new MutationObserver(() => {
            observeElements();
        });
        
        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    preloadVideo(videoUrl, isLowPriority = false) {
        if (this.preloadedVideos.has(videoUrl)) {
            return this.preloadedVideos.get(videoUrl);
        }
        
        if (this.preloadQueue.has(videoUrl)) {
            return;
        }
        
        if (this.preloadedVideos.size >= this.maxPreloadedVideos) {
            if (isLowPriority) {
                return;
            }
            this.cleanupOldestVideo();
        }
        
        this.preloadQueue.add(videoUrl);
        console.log(`🎬 Preloading video: ${videoUrl.substring(videoUrl.lastIndexOf('/') + 1)}`);
        
        const video = document.createElement('video');
        video.preload = 'auto';
        video.muted = true;
        video.playsInline = true;
        video.style.display = 'none';
        video.style.position = 'absolute';
        video.style.top = '-9999px';
        video.setAttribute('data-preloaded-url', videoUrl);
        
        const startTime = Date.now();
        
        video.addEventListener('loadeddata', () => {
            const loadTime = ((Date.now() - startTime) / 1000).toFixed(2);
            console.log(`✅ Video buffered in ${loadTime}s: ${videoUrl.substring(videoUrl.lastIndexOf('/') + 1)}`);
            
            this.preloadedVideos.set(videoUrl, {
                element: video,
                timestamp: Date.now(),
                used: false
            });
            this.preloadQueue.delete(videoUrl);
        }, { once: true });
        
        video.addEventListener('error', (e) => {
            console.error(`❌ Failed to preload video: ${videoUrl}`, e);
            this.preloadQueue.delete(videoUrl);
            if (video.parentNode) {
                video.parentNode.removeChild(video);
            }
        }, { once: true });
        
        video.src = videoUrl;
        video.load();
        
        document.body.appendChild(video);
        
        video.play().then(() => {
            video.pause();
            video.currentTime = 0;
        }).catch(() => {
        });
        
        return video;
    }
    
    getPreloadedVideo(videoUrl) {
        if (this.preloadedVideos.has(videoUrl)) {
            const videoData = this.preloadedVideos.get(videoUrl);
            videoData.used = true;
            console.log(`🎯 Using preloaded video: ${videoUrl.substring(videoUrl.lastIndexOf('/') + 1)}`);
            return videoData.element;
        }
        return null;
    }
    
    cleanupOldestVideo() {
        let oldestUrl = null;
        let oldestTime = Infinity;
        
        this.preloadedVideos.forEach((data, url) => {
            if (!data.used && data.timestamp < oldestTime) {
                oldestTime = data.timestamp;
                oldestUrl = url;
            }
        });
        
        if (oldestUrl) {
            this.removePreloadedVideo(oldestUrl);
        } else {
            const firstUrl = this.preloadedVideos.keys().next().value;
            if (firstUrl) {
                this.removePreloadedVideo(firstUrl);
            }
        }
    }
    
    removePreloadedVideo(videoUrl) {
        if (this.preloadedVideos.has(videoUrl)) {
            const videoData = this.preloadedVideos.get(videoUrl);
            if (videoData.element && videoData.element.parentNode) {
                videoData.element.parentNode.removeChild(videoData.element);
            }
            this.preloadedVideos.delete(videoUrl);
            console.log(`🗑️ Cleaned up preloaded video: ${videoUrl.substring(videoUrl.lastIndexOf('/') + 1)}`);
        }
    }
    
    cleanupUsedVideo(videoUrl) {
        setTimeout(() => {
            this.removePreloadedVideo(videoUrl);
        }, 60000);
    }
}

window.videoPreloader = new VideoPreloader();
