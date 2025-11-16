/**
 * Video Modal System - Phase 8
 * Vanilla JavaScript video player with lazy loading and autoplay
 */

class VideoModal {
    constructor() {
        this.modal = null;
        this.video = null;
        this.isOpen = false;
        this.observer = null;
        this.init();
    }

    init() {
        this.createModal();
        this.attachEvents();
        this.initLazyLoading();
    }

    createModal() {
        const modalHTML = `
            <div id="videoModal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true" aria-labelledby="videoModalTitle">
                <div class="flex items-center justify-center min-h-screen px-4 py-8">
                    <!-- Backdrop -->
                    <div class="fixed inset-0 bg-black transition-opacity duration-300" 
                         style="opacity: 0.95;" 
                         data-video-backdrop></div>
                    
                    <!-- Modal Content -->
                    <div class="relative bg-black rounded-xl shadow-2xl w-full max-w-6xl transform transition-all duration-300"
                         style="max-height: 90vh;"
                         data-video-container>
                        
                        <!-- Header -->
                        <div class="flex items-center justify-between px-4 sm:px-6 py-3 bg-gray-900/90 backdrop-blur-sm rounded-t-xl border-b border-gray-700">
                            <h5 id="videoModalTitle" class="text-base sm:text-lg font-bold text-white truncate pr-4">Video Preview</h5>
                            <button data-close-modal 
                                    class="text-gray-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-gray-800"
                                    aria-label="Close video modal">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Video Container -->
                        <div class="relative bg-black rounded-b-xl overflow-hidden" style="aspect-ratio: 16/9;">
                            <!-- Loading Spinner -->
                            <div data-video-loader class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-600 border-t-white"></div>
                                    <p class="text-gray-400 text-sm">Loading video...</p>
                                </div>
                            </div>
                            
                            <!-- Video Element -->
                            <video id="modalVideo" 
                                   class="w-full h-full object-contain"
                                   preload="metadata"
                                   playsinline
                                   controlsList="nodownload"
                                   style="display: none;">
                                <source data-video-source type="video/mp4">
                                Your browser does not support the video tag.
                            </video>
                            
                            <!-- Custom Play Button Overlay -->
                            <div data-play-overlay class="absolute inset-0 flex items-center justify-center bg-black/30 cursor-pointer transition-opacity duration-300 hover:bg-black/20" style="display: none;">
                                <button class="bg-white/90 hover:bg-white rounded-full p-6 shadow-2xl transition-all transform hover:scale-110"
                                        aria-label="Play video">
                                    <svg class="w-12 h-12 text-gray-900" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                                    </svg>
                                </button>
                            </div>
                            
                            <!-- Error Message -->
                            <div data-video-error class="hidden absolute inset-0 flex items-center justify-center bg-gray-900 text-white p-6">
                                <div class="text-center max-w-md">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <h3 class="text-xl font-bold mb-2">Video Unavailable</h3>
                                    <p class="text-gray-400">Unable to load video. Please try again later.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mobile-friendly controls hint -->
                        <div class="sm:hidden px-4 py-2 bg-gray-900/90 text-xs text-gray-400 text-center">
                            Tap video to play/pause
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        this.modal = document.getElementById('videoModal');
        this.video = document.getElementById('modalVideo');
        this.loader = this.modal.querySelector('[data-video-loader]');
        this.videoSource = this.modal.querySelector('[data-video-source]');
        this.playOverlay = this.modal.querySelector('[data-play-overlay]');
        this.errorContainer = this.modal.querySelector('[data-video-error]');
        this.title = document.getElementById('videoModalTitle');
    }

    attachEvents() {
        // Close button
        this.modal.querySelector('[data-close-modal]').addEventListener('click', () => this.close());
        
        // Backdrop click
        this.modal.querySelector('[data-video-backdrop]').addEventListener('click', () => this.close());
        
        // ESC key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
        
        // Video events
        this.video.addEventListener('loadeddata', () => this.onVideoLoaded());
        this.video.addEventListener('error', () => this.onVideoError());
        this.video.addEventListener('play', () => this.onVideoPlay());
        this.video.addEventListener('pause', () => this.onVideoPause());
        this.video.addEventListener('ended', () => this.onVideoEnded());
        
        // Play overlay click
        this.playOverlay.addEventListener('click', () => this.playVideo());
        
        // Video click (play/pause toggle)
        this.video.addEventListener('click', () => this.togglePlayPause());
        
        // Prevent modal content clicks from closing
        this.modal.querySelector('[data-video-container]').addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    initLazyLoading() {
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const trigger = entry.target;
                        const videoUrl = trigger.dataset.videoUrl;
                        const videoTitle = trigger.dataset.videoTitle;
                        
                        if (videoUrl) {
                            trigger.classList.add('video-ready');
                        }
                    }
                });
            }, {
                rootMargin: '50px'
            });
            
            document.querySelectorAll('[data-video-trigger]').forEach(trigger => {
                this.observer.observe(trigger);
            });
        }
    }

    open(videoUrl, title = 'Video Preview') {
        if (!videoUrl) {
            console.error('VideoModal: No video URL provided');
            return;
        }

        this.isOpen = true;
        this.title.textContent = title;
        
        // Show modal
        this.modal.classList.remove('hidden');
        
        // Prevent body scroll
        document.body.style.overflow = 'hidden';
        
        // Show loader
        this.loader.style.display = 'flex';
        this.video.style.display = 'none';
        this.playOverlay.style.display = 'none';
        this.errorContainer.classList.add('hidden');
        
        // Set video source
        this.videoSource.src = videoUrl;
        this.video.load();
        
        // Track analytics
        if (typeof trackEvent === 'function') {
            trackEvent('video_modal_opened', { url: videoUrl, title: title });
        }
    }

    close() {
        if (!this.isOpen) return;
        
        this.isOpen = false;
        
        // Pause and reset video
        this.video.pause();
        this.video.currentTime = 0;
        this.videoSource.src = '';
        
        // Hide modal
        this.modal.classList.add('hidden');
        
        // Restore body scroll
        document.body.style.overflow = '';
        
        // Track analytics
        if (typeof trackEvent === 'function') {
            trackEvent('video_modal_closed');
        }
    }

    playVideo() {
        const playPromise = this.video.play();
        
        if (playPromise !== undefined) {
            playPromise.catch(error => {
                console.error('VideoModal: Autoplay failed', error);
                // Show play button if autoplay fails
                this.playOverlay.style.display = 'flex';
            });
        }
    }

    togglePlayPause() {
        if (this.video.paused) {
            this.playVideo();
        } else {
            this.video.pause();
        }
    }

    onVideoLoaded() {
        // Hide loader
        this.loader.style.display = 'none';
        
        // Show video
        this.video.style.display = 'block';
        
        // Attempt autoplay (muted for mobile compatibility)
        this.video.muted = true;
        this.playVideo();
        
        // Unmute after a brief moment (user gesture requirement)
        setTimeout(() => {
            this.video.muted = false;
        }, 100);
    }

    onVideoError() {
        console.error('VideoModal: Video failed to load');
        this.loader.style.display = 'none';
        this.video.style.display = 'none';
        this.errorContainer.classList.remove('hidden');
    }

    onVideoPlay() {
        this.playOverlay.style.display = 'none';
    }

    onVideoPause() {
        if (!this.video.ended) {
            this.playOverlay.style.display = 'flex';
        }
    }

    onVideoEnded() {
        this.playOverlay.style.display = 'flex';
        this.video.currentTime = 0;
    }
}

// Initialize on DOM ready
function initVideoModal() {
    if (!window.videoModal) {
        window.videoModal = new VideoModal();
        console.log('VideoModal initialized');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initVideoModal);
} else {
    initVideoModal();
}

// Global function for opening video modal
window.openVideoModal = function(videoUrl, title) {
    if (!window.videoModal) {
        initVideoModal();
    }
    if (window.videoModal) {
        window.videoModal.open(videoUrl, title);
    } else {
        console.error('VideoModal not initialized');
    }
};

// Global function for closing video modal
window.closeVideoModal = function() {
    if (window.videoModal) {
        window.videoModal.close();
    }
};

class DemoModal {
    constructor() {
        this.modal = null;
        this.iframe = null;
        this.isOpen = false;
        this.init();
    }

    init() {
        this.createModal();
        this.attachEvents();
    }

    createModal() {
        const modalHTML = `
            <div id="demoModal" class="fixed inset-0 z-[60] hidden" role="dialog" aria-modal="true" aria-labelledby="demoModalTitle">
                <div class="flex items-center justify-center min-h-screen px-4 py-8">
                    <div class="fixed inset-0 bg-black transition-opacity duration-300" 
                         style="opacity: 0.95;" 
                         data-demo-backdrop></div>
                    
                    <div class="relative bg-black rounded-xl shadow-2xl w-full max-w-6xl transform transition-all duration-300"
                         style="max-height: 90vh;"
                         data-demo-container>
                        
                        <div class="flex items-center justify-between px-4 sm:px-6 py-3 bg-gray-900/90 backdrop-blur-sm rounded-t-xl border-b border-gray-700">
                            <h5 id="demoModalTitle" class="text-base sm:text-lg font-bold text-white truncate pr-4">Live Preview</h5>
                            <button data-close-demo-modal 
                                    class="text-gray-400 hover:text-white transition-colors p-1.5 rounded-lg hover:bg-gray-800"
                                    aria-label="Close demo modal">
                                <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        
                        <div class="relative bg-white rounded-b-xl overflow-hidden" style="height: 70vh;">
                            <div data-demo-loader class="absolute inset-0 flex items-center justify-center bg-gray-900">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-gray-600 border-t-white"></div>
                                    <p class="text-gray-400 text-sm">Loading preview...</p>
                                </div>
                            </div>
                            
                            <iframe id="modalIframe" 
                                   class="w-full h-full border-0"
                                   style="display: none;"
                                   loading="eager">
                            </iframe>
                            
                            <div data-demo-error class="hidden absolute inset-0 flex items-center justify-center bg-gray-900 text-white p-6">
                                <div class="text-center max-w-md">
                                    <svg class="w-16 h-16 mx-auto mb-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <h3 class="text-xl font-bold mb-2">Preview Unavailable</h3>
                                    <p class="text-gray-400">Unable to load preview. Please try opening in a new tab.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        this.modal = document.getElementById('demoModal');
        this.iframe = document.getElementById('modalIframe');
        this.loader = this.modal.querySelector('[data-demo-loader]');
        this.errorContainer = this.modal.querySelector('[data-demo-error]');
        this.title = document.getElementById('demoModalTitle');
    }

    attachEvents() {
        this.modal.querySelector('[data-close-demo-modal]').addEventListener('click', () => this.close());
        
        this.modal.querySelector('[data-demo-backdrop]').addEventListener('click', () => this.close());
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.isOpen) {
                this.close();
            }
        });
        
        this.iframe.addEventListener('load', () => this.onIframeLoaded());
        this.iframe.addEventListener('error', () => this.onIframeError());
        
        this.modal.querySelector('[data-demo-container]').addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    open(url, title = 'Live Preview') {
        if (!url) {
            console.error('DemoModal: No URL provided');
            return;
        }

        this.isOpen = true;
        this.title.textContent = title;
        
        this.modal.classList.remove('hidden');
        
        document.body.style.overflow = 'hidden';
        
        this.loader.style.display = 'flex';
        this.iframe.style.display = 'none';
        this.errorContainer.classList.add('hidden');
        
        this.iframe.src = url;
        
        setTimeout(() => this.onIframeLoaded(), 3000);
        
        if (typeof trackEvent === 'function') {
            trackEvent('demo_modal_opened', { url: url, title: title });
        }
    }

    close() {
        if (!this.isOpen) return;
        
        this.isOpen = false;
        
        this.iframe.src = '';
        
        this.modal.classList.add('hidden');
        
        document.body.style.overflow = '';
        
        if (typeof trackEvent === 'function') {
            trackEvent('demo_modal_closed');
        }
    }

    onIframeLoaded() {
        this.loader.style.display = 'none';
        this.iframe.style.display = 'block';
    }

    onIframeError() {
        console.error('DemoModal: Failed to load preview');
        this.loader.style.display = 'none';
        this.iframe.style.display = 'none';
        this.errorContainer.classList.remove('hidden');
    }
}

function initDemoModal() {
    if (!window.demoModal) {
        window.demoModal = new DemoModal();
        console.log('DemoModal initialized');
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDemoModal);
} else {
    initDemoModal();
}

window.openDemoFullscreen = function(url, title) {
    if (!window.demoModal) {
        initDemoModal();
    }
    if (window.demoModal) {
        window.demoModal.open(url, title);
    } else {
        console.error('DemoModal not initialized');
    }
};

window.closeDemoModal = function() {
    if (window.demoModal) {
        window.demoModal.close();
    }
};
