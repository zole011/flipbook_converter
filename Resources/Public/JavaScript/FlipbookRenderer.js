/**
 * FlipbookRenderer - Main JavaScript class for flipbook functionality
 * 
 * @author Gmbit
 * @version 1.0.0
 */
class FlipbookRenderer {
    constructor(config) {
        this.config = this.mergeConfig(config);
        this.container = document.getElementById(this.config.uniqueId || `flipbook-${this.config.documentUid}`);
        this.currentPage = 1;
        this.totalPages = this.config.totalPages || 0;
        this.isFullscreen = false;
        this.zoomLevel = 1.0;
        this.autoplayTimer = null;
        this.touchStartX = 0;
        this.touchStartY = 0;
        this.isLoading = false;
        this.loadedPages = new Set();
        
        // Initialize if container exists
        if (this.container) {
            this.init();
        } else {
            console.error('FlipbookRenderer: Container not found', this.config);
        }
    }

    /**
     * Merge default config with provided config
     */
    mergeConfig(config) {
        const defaults = {
            width: 800,
            height: 600,
            backgroundColor: '#ffffff',
            showControls: true,
            showPageNumbers: true,
            enableZoom: true,
            enableFullscreen: true,
            enableKeyboard: true,
            enableTouch: true,
            autoplay: false,
            autoplayDelay: 3000,
            animationDuration: 500,
            animationType: 'slide',
            lazyLoading: true,
            preloadPages: 3,
            maxZoom: 3.0,
            zoomStep: 0.1,
            swipeThreshold: 50
        };
        
        return { ...defaults, ...config };
    }

    /**
     * Initialize flipbook
     */
    init() {
        try {
            this.setupElements();
            this.bindEvents();
            this.preloadInitialPages();
            this.hideLoading();
            this.updateControls();
            
            if (this.config.autoplay) {
                this.startAutoplay();
            }
            
            console.log('FlipbookRenderer initialized', this.config);
        } catch (error) {
            console.error('FlipbookRenderer initialization failed', error);
            this.showError('Initialization failed: ' + error.message);
        }
    }

    /**
     * Setup DOM elements
     */
    setupElements() {
        this.viewer = this.container.querySelector('.flipbook-viewer');
        this.pagesContainer = this.container.querySelector('.flipbook-pages');
        this.pages = this.container.querySelectorAll('.flipbook-page');
        this.controls = this.container.querySelector('.flipbook-controls');
        this.loading = this.container.querySelector('.flipbook-loading');
        this.errorElement = this.container.querySelector('.flipbook-error');
        
        // Set container dimensions
        if (this.config.width && this.config.height) {
            this.container.style.width = this.config.width + 'px';
            this.container.style.height = this.config.height + 'px';
        }
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Control buttons
        this.bindControlEvents();
        
        // Keyboard navigation
        if (this.config.enableKeyboard) {
            this.bindKeyboardEvents();
        }
        
        // Touch/swipe gestures
        if (this.config.enableTouch) {
            this.bindTouchEvents();
        }
        
        // Window resize
        window.addEventListener('resize', this.debounce(() => {
            this.handleResize();
        }, 250));
        
        // Fullscreen changes
        document.addEventListener('fullscreenchange', () => {
            this.handleFullscreenChange();
        });
    }

    /**
     * Bind control button events
     */
    bindControlEvents() {
        if (!this.controls) return;
        
        this.controls.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;
            if (!action) return;
            
            e.preventDefault();
            
            switch (action) {
                case 'prevPage':
                    this.prevPage();
                    break;
                case 'nextPage':
                    this.nextPage();
                    break;
                case 'gotoPage':
                    const page = parseInt(e.target.dataset.page);
                    if (page) this.gotoPage(page);
                    break;
                case 'zoomIn':
                    this.zoomIn();
                    break;
                case 'zoomOut':
                    this.zoomOut();
                    break;
                case 'zoomReset':
                    this.zoomReset();
                    break;
                case 'toggleFullscreen':
                    this.toggleFullscreen();
                    break;
                case 'toggleAutoplay':
                    this.toggleAutoplay();
                    break;
                case 'toggleThumbnails':
                    this.toggleThumbnails();
                    break;
            }
        });
        
        // Page input field
        const pageInput = this.controls.querySelector('.flipbook-page-input');
        if (pageInput) {
            pageInput.addEventListener('change', (e) => {
                const page = parseInt(e.target.value);
                if (page >= 1 && page <= this.totalPages) {
                    this.gotoPage(page);
                }
            });
        }
    }

    /**
     * Bind keyboard events
     */
    bindKeyboardEvents() {
        document.addEventListener('keydown', (e) => {
            // Only handle if flipbook is in focus/active
            if (!this.container.contains(document.activeElement) && 
                !this.isFullscreen) {
                return;
            }
            
            switch (e.key) {
                case 'ArrowLeft':
                case 'ArrowUp':
                    e.preventDefault();
                    this.prevPage();
                    break;
                case 'ArrowRight':
                case 'ArrowDown':
                case ' ':
                    e.preventDefault();
                    this.nextPage();
                    break;
                case 'Home':
                    e.preventDefault();
                    this.gotoPage(1);
                    break;
                case 'End':
                    e.preventDefault();
                    this.gotoPage(this.totalPages);
                    break;
                case '+':
                case '=':
                    e.preventDefault();
                    this.zoomIn();
                    break;
                case '-':
                    e.preventDefault();
                    this.zoomOut();
                    break;
                case 'f':
                case 'F':
                    if (this.config.enableFullscreen) {
                        e.preventDefault();
                        this.toggleFullscreen();
                    }
                    break;
                case 'Escape':
                    if (this.isFullscreen) {
                        e.preventDefault();
                        this.exitFullscreen();
                    }
                    break;
            }
        });
    }

    /**
     * Bind touch/swipe events
     */
    bindTouchEvents() {
        this.viewer.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1) {
                this.touchStartX = e.touches[0].clientX;
                this.touchStartY = e.touches[0].clientY;
            }
        }, { passive: true });
        
        this.viewer.addEventListener('touchend', (e) => {
            if (e.changedTouches.length === 1) {
                const touchEndX = e.changedTouches[0].clientX;
                const touchEndY = e.changedTouches[0].clientY;
                
                const deltaX = touchEndX - this.touchStartX;
                const deltaY = touchEndY - this.touchStartY;
                
                // Check if horizontal swipe is dominant
                if (Math.abs(deltaX) > Math.abs(deltaY) && 
                    Math.abs(deltaX) > this.config.swipeThreshold) {
                    
                    if (deltaX > 0) {
                        this.prevPage();
                    } else {
                        this.nextPage();
                    }
                }
            }
        }, { passive: true });
    }

    /**
     * Navigate to previous page
     */
    prevPage() {
        if (this.currentPage > 1) {
            this.gotoPage(this.currentPage - 1);
        }
    }

    /**
     * Navigate to next page
     */
    nextPage() {
        if (this.currentPage < this.totalPages) {
            this.gotoPage(this.currentPage + 1);
        }
    }

    /**
     * Navigate to specific page
     */
    gotoPage(pageNumber) {
        if (pageNumber < 1 || pageNumber > this.totalPages || 
            pageNumber === this.currentPage || this.isLoading) {
            return;
        }
        
        this.isLoading = true;
        const previousPage = this.currentPage;
        this.currentPage = pageNumber;
        
        // Load page if not already loaded
        this.loadPage(pageNumber).then(() => {
            this.showPage(pageNumber, previousPage);
            this.updateControls();
            this.isLoading = false;
            
            // Preload adjacent pages
            this.preloadAdjacentPages(pageNumber);
            
            // Announce page change for accessibility
            this.announcePageChange(pageNumber);
        }).catch((error) => {
            console.error('Failed to load page', pageNumber, error);
            this.currentPage = previousPage;
            this.isLoading = false;
            this.showError('Failed to load page ' + pageNumber);
        });
    }

    /**
     * Show specific page with animation
     */
    showPage(pageNumber, previousPage) {
        const currentPageElement = this.container.querySelector(`[data-page="${pageNumber}"]`);
        const previousPageElement = this.container.querySelector(`[data-page="${previousPage}"]`);
        
        if (!currentPageElement) {
            console.error('Page element not found', pageNumber);
            return;
        }
        
        // Hide all pages first
        this.pages.forEach(page => {
            page.style.display = 'none';
        });
        
        // Apply animation based on config
        this.animatePageTransition(currentPageElement, previousPageElement, pageNumber > previousPage);
    }

    /**
     * Animate page transition
     */
    animatePageTransition(currentPage, previousPage, isForward) {
        const duration = this.config.animationDuration;
        
        switch (this.config.animationType) {
            case 'fade':
                this.fadeTransition(currentPage, previousPage, duration);
                break;
            case 'slide':
                this.slideTransition(currentPage, previousPage, duration, isForward);
                break;
            case 'flip':
                this.flipTransition(currentPage, previousPage, duration, isForward);
                break;
            default:
                // No animation
                currentPage.style.display = 'block';
                break;
        }
    }

    /**
     * Fade transition animation
     */
    fadeTransition(currentPage, previousPage, duration) {
        currentPage.style.opacity = '0';
        currentPage.style.display = 'block';
        
        // Animate in
        currentPage.animate([
            { opacity: 0 },
            { opacity: 1 }
        ], {
            duration: duration,
            easing: this.config.easingFunction || 'ease-in-out',
            fill: 'forwards'
        });
    }

    /**
     * Slide transition animation
     */
    slideTransition(currentPage, previousPage, duration, isForward) {
        const direction = isForward ? 1 : -1;
        
        currentPage.style.transform = `translateX(${direction * 100}%)`;
        currentPage.style.display = 'block';
        
        currentPage.animate([
            { transform: `translateX(${direction * 100}%)` },
            { transform: 'translateX(0)' }
        ], {
            duration: duration,
            easing: this.config.easingFunction || 'ease-in-out',
            fill: 'forwards'
        });
    }

    /**
     * Flip transition animation
     */
    flipTransition(currentPage, previousPage, duration, isForward) {
        const perspective = 'perspective(1000px)';
        const rotateY = isForward ? 'rotateY(-180deg)' : 'rotateY(180deg)';
        
        currentPage.style.transform = perspective + ' ' + rotateY;
        currentPage.style.display = 'block';
        
        currentPage.animate([
            { transform: perspective + ' ' + rotateY },
            { transform: perspective + ' rotateY(0deg)' }
        ], {
            duration: duration,
            easing: this.config.easingFunction || 'ease-in-out',
            fill: 'forwards'
        });
    }

    /**
     * Load page image if not already loaded
     */
    async loadPage(pageNumber) {
        if (this.loadedPages.has(pageNumber)) {
            return Promise.resolve();
        }
        
        const pageElement = this.container.querySelector(`[data-page="${pageNumber}"]`);
        if (!pageElement) {
            return Promise.reject(new Error('Page element not found'));
        }
        
        const img = pageElement.querySelector('img[data-src], img.lazy');
        if (!img || img.src) {
            this.loadedPages.add(pageNumber);
            return Promise.resolve();
        }
        
        return new Promise((resolve, reject) => {
            const dataSrc = img.dataset.src;
            if (!dataSrc) {
                resolve();
                return;
            }
            
            img.onload = () => {
                img.classList.remove('lazy');
                this.loadedPages.add(pageNumber);
                resolve();
            };
            
            img.onerror = () => {
                reject(new Error('Failed to load image'));
            };
            
            img.src = dataSrc;
            img.removeAttribute('data-src');
        });
    }

    /**
     * Preload initial pages
     */
    preloadInitialPages() {
        const preloadCount = Math.min(this.config.preloadPages, this.totalPages);
        
        for (let i = 1; i <= preloadCount; i++) {
            this.loadPage(i).catch(error => {
                console.warn('Failed to preload page', i, error);
            });
        }
    }

    /**
     * Preload adjacent pages
     */
    preloadAdjacentPages(currentPage) {
        const preloadRange = 2;
        
        for (let i = Math.max(1, currentPage - preloadRange); 
             i <= Math.min(this.totalPages, currentPage + preloadRange); 
             i++) {
            if (i !== currentPage) {
                this.loadPage(i).catch(error => {
                    console.warn('Failed to preload adjacent page', i, error);
                });
            }
        }
    }

    /**
     * Update control states
     */
    updateControls() {
        if (!this.controls) return;
        
        // Previous button
        const prevBtn = this.controls.querySelector('.flipbook-btn-prev');
        if (prevBtn) {
            prevBtn.disabled = this.currentPage <= 1;
        }
        
        // Next button
        const nextBtn = this.controls.querySelector('.flipbook-btn-next');
        if (nextBtn) {
            nextBtn.disabled = this.currentPage >= this.totalPages;
        }
        
        // Page counter
        const currentPageSpan = this.controls.querySelector('.flipbook-current-page');
        if (currentPageSpan) {
            currentPageSpan.textContent = this.currentPage;
        }
        
        // Page input
        const pageInput = this.controls.querySelector('.flipbook-page-input');
        if (pageInput) {
            pageInput.value = this.currentPage;
        }
        
        // Progress bar
        this.updateProgress();
    }

    /**
     * Update progress bar
     */
    updateProgress() {
        const progressFill = this.container.querySelector('.flipbook-progress-fill');
        if (progressFill && this.totalPages > 0) {
            const progress = (this.currentPage / this.totalPages) * 100;
            progressFill.style.width = progress + '%';
        }
    }

    /**
     * Zoom functionality
     */
    zoomIn() {
        if (!this.config.enableZoom) return;
        
        this.zoomLevel = Math.min(this.config.maxZoom, this.zoomLevel + this.config.zoomStep);
        this.applyZoom();
    }

    zoomOut() {
        if (!this.config.enableZoom) return;
        
        this.zoomLevel = Math.max(1.0, this.zoomLevel - this.config.zoomStep);
        this.applyZoom();
    }

    zoomReset() {
        if (!this.config.enableZoom) return;
        
        this.zoomLevel = 1.0;
        this.applyZoom();
    }

    applyZoom() {
        const currentPageElement = this.container.querySelector(`[data-page="${this.currentPage}"]`);
        if (currentPageElement) {
            const img = currentPageElement.querySelector('img');
            if (img) {
                img.style.transform = `scale(${this.zoomLevel})`;
                img.style.transition = 'transform 0.3s ease';
            }
        }
        
        // Update zoom controls
        this.updateZoomControls();
    }

    updateZoomControls() {
        const zoomLevel = this.controls?.querySelector('.flipbook-zoom-level');
        if (zoomLevel) {
            zoomLevel.textContent = Math.round(this.zoomLevel * 100) + '%';
        }
        
        const zoomOutBtn = this.controls?.querySelector('.flipbook-btn-zoom-out');
        if (zoomOutBtn) {
            zoomOutBtn.disabled = this.zoomLevel <= 1.0;
        }
        
        const zoomInBtn = this.controls?.querySelector('.flipbook-btn-zoom-in');
        if (zoomInBtn) {
            zoomInBtn.disabled = this.zoomLevel >= this.config.maxZoom;
        }
    }

    /**
     * Fullscreen functionality
     */
    toggleFullscreen() {
        if (this.isFullscreen) {
            this.exitFullscreen();
        } else {
            this.enterFullscreen();
        }
    }

    enterFullscreen() {
        if (!this.config.enableFullscreen) return;
        
        if (this.container.requestFullscreen) {
            this.container.requestFullscreen();
        } else if (this.container.webkitRequestFullscreen) {
            this.container.webkitRequestFullscreen();
        } else if (this.container.msRequestFullscreen) {
            this.container.msRequestFullscreen();
        }
    }

    exitFullscreen() {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }

    handleFullscreenChange() {
        this.isFullscreen = !!(document.fullscreenElement || 
                               document.webkitFullscreenElement || 
                               document.msFullscreenElement);
        
        // Update fullscreen button icon
        const fullscreenBtn = this.controls?.querySelector('.flipbook-btn-fullscreen');
        if (fullscreenBtn) {
            const enterIcon = fullscreenBtn.querySelector('.flipbook-icon-fullscreen');
            const exitIcon = fullscreenBtn.querySelector('.flipbook-icon-fullscreen-exit');
            
            if (enterIcon && exitIcon) {
                enterIcon.style.display = this.isFullscreen ? 'none' : 'inline';
                exitIcon.style.display = this.isFullscreen ? 'inline' : 'none';
            }
        }
    }

    /**
     * Autoplay functionality
     */
    startAutoplay() {
        if (!this.config.autoplay || this.autoplayTimer) return;
        
        this.autoplayTimer = setInterval(() => {
            if (this.currentPage < this.totalPages) {
                this.nextPage();
            } else {
                this.stopAutoplay();
            }
        }, this.config.autoplayDelay);
        
        this.updateAutoplayButton(true);
    }

    stopAutoplay() {
        if (this.autoplayTimer) {
            clearInterval(this.autoplayTimer);
            this.autoplayTimer = null;
        }
        
        this.updateAutoplayButton(false);
    }

    toggleAutoplay() {
        if (this.autoplayTimer) {
            this.stopAutoplay();
        } else {
            this.startAutoplay();
        }
    }

    updateAutoplayButton(isPlaying) {
        const autoplayBtn = this.controls?.querySelector('.flipbook-btn-autoplay');
        if (!autoplayBtn) return;
        
        const playIcon = autoplayBtn.querySelector('.flipbook-icon-play');
        const pauseIcon = autoplayBtn.querySelector('.flipbook-icon-pause');
        
        if (playIcon && pauseIcon) {
            playIcon.style.display = isPlaying ? 'none' : 'inline';
            pauseIcon.style.display = isPlaying ? 'inline' : 'none';
        }
    }

    /**
     * Thumbnail functionality
     */
    toggleThumbnails() {
        const thumbnails = this.container.querySelector('.flipbook-thumbnails');
        if (!thumbnails) return;
        
        const isVisible = thumbnails.style.display !== 'none';
        thumbnails.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            this.loadThumbnails();
        }
    }

    loadThumbnails() {
        const thumbnails = this.container.querySelectorAll('.flipbook-thumbnail-image');
        thumbnails.forEach(img => {
            if (img.dataset.src && !img.src) {
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
            }
        });
    }

    /**
     * Error handling
     */
    showError(message) {
        if (this.errorElement) {
            this.errorElement.querySelector('.error-message').textContent = message;
            this.errorElement.style.display = 'block';
        }
        
        console.error('FlipbookRenderer Error:', message);
    }

    hideError() {
        if (this.errorElement) {
            this.errorElement.style.display = 'none';
        }
    }

    /**
     * Loading state
     */
    showLoading() {
        if (this.loading) {
            this.loading.style.display = 'block';
        }
    }

    hideLoading() {
        if (this.loading) {
            this.loading.style.display = 'none';
        }
    }

    /**
     * Accessibility
     */
    announcePageChange(pageNumber) {
        const announcement = `Page ${pageNumber} of ${this.totalPages}`;
        const liveRegion = this.container.querySelector('.flipbook-accessibility');
        
        if (liveRegion) {
            liveRegion.textContent = announcement;
        }
    }

    /**
     * Handle window resize
     */
    handleResize() {
        // Recalculate dimensions for responsive mode
        if (this.config.responsive) {
            this.updateResponsiveDimensions();
        }
    }

    updateResponsiveDimensions() {
        const containerWidth = this.container.offsetWidth;
        const aspectRatio = this.config.height / this.config.width;
        const newHeight = containerWidth * aspectRatio;
        
        this.container.style.height = newHeight + 'px';
    }

    /**
     * Utility function for debouncing
     */
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Destroy flipbook instance
     */
    destroy() {
        this.stopAutoplay();
        
        // Remove event listeners
        if (this.controls) {
            this.controls.removeEventListener('click', this.boundControlHandler);
        }
        
        // Clear loaded pages
        this.loadedPages.clear();
        
        console.log('FlipbookRenderer destroyed');
    }
}

// Global initialization
window.FlipbookRenderer = FlipbookRenderer;

// Auto-initialize flipbooks on page load
document.addEventListener('DOMContentLoaded', function() {
    // Look for flipbook containers with data-config
    const flipbookContainers = document.querySelectorAll('[data-config]');
    
    flipbookContainers.forEach(container => {
        try {
            const config = JSON.parse(container.dataset.config);
            new FlipbookRenderer(config);
        } catch (error) {
            console.error('Failed to auto-initialize flipbook', error);
        }
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FlipbookRenderer;
}