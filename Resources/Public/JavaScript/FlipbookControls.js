/**
 * FlipbookControls - Enhanced control functionality for flipbooks
 * 
 * @author Gmbit
 * @version 1.0.0
 */
class FlipbookControls {
    constructor(flipbookRenderer) {
        this.renderer = flipbookRenderer;
        this.container = flipbookRenderer.container;
        this.config = flipbookRenderer.config;
        this.isVisible = true;
        this.autoHideTimer = null;
        
        this.init();
    }

    /**
     * Initialize controls
     */
    init() {
        this.setupControlElements();
        this.bindAdvancedEvents();
        this.setupAutoHide();
        this.setupKeyboardShortcuts();
        this.setupContextMenu();
    }

    /**
     * Setup control elements
     */
    setupControlElements() {
        this.controls = this.container.querySelector('.flipbook-controls');
        if (!this.controls) return;

        // Page navigation elements
        this.pageInput = this.controls.querySelector('.flipbook-page-input');
        this.pageSlider = this.controls.querySelector('.flipbook-page-slider');
        
        // Zoom elements
        this.zoomSlider = this.controls.querySelector('.flipbook-zoom-slider');
        
        // Create additional control elements if needed
        this.createAdvancedControls();
    }

    /**
     * Create advanced control elements
     */
    createAdvancedControls() {
        // Create page slider if it doesn't exist
        if (!this.pageSlider && this.config.showPageSlider) {
            this.createPageSlider();
        }
        
        // Create zoom slider if it doesn't exist
        if (!this.zoomSlider && this.config.enableZoom) {
            this.createZoomSlider();
        }
        
        // Create speed control for autoplay
        if (this.config.autoplay && this.config.showSpeedControl) {
            this.createSpeedControl();
        }
    }

    /**
     * Create page slider
     */
    createPageSlider() {
        const sliderContainer = document.createElement('div');
        sliderContainer.className = 'flipbook-page-slider-container';
        
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.className = 'flipbook-page-slider';
        slider.min = '1';
        slider.max = this.renderer.totalPages.toString();
        slider.value = '1';
        slider.setAttribute('aria-label', 'Page navigation slider');
        
        const label = document.createElement('label');
        label.textContent = 'Page: ';
        label.appendChild(slider);
        
        sliderContainer.appendChild(label);
        
        // Insert into controls
        const navigationSection = this.controls.querySelector('.flipbook-controls-main');
        if (navigationSection) {
            navigationSection.appendChild(sliderContainer);
        }
        
        this.pageSlider = slider;
        
        // Bind slider events
        slider.addEventListener('input', (e) => {
            const page = parseInt(e.target.value);
            this.renderer.gotoPage(page);
        });
    }

    /**
     * Create zoom slider
     */
    createZoomSlider() {
        const sliderContainer = document.createElement('div');
        sliderContainer.className = 'flipbook-zoom-slider-container';
        
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.className = 'flipbook-zoom-slider';
        slider.min = '100';
        slider.max = (this.config.maxZoom * 100).toString();
        slider.value = '100';
        slider.step = (this.config.zoomStep * 100).toString();
        slider.setAttribute('aria-label', 'Zoom level slider');
        
        const label = document.createElement('label');
        label.textContent = 'Zoom: ';
        label.appendChild(slider);
        
        sliderContainer.appendChild(label);
        
        // Insert into zoom controls
        const zoomSection = this.controls.querySelector('.flipbook-zoom-controls');
        if (zoomSection) {
            zoomSection.appendChild(sliderContainer);
        }
        
        this.zoomSlider = slider;
        
        // Bind slider events
        slider.addEventListener('input', (e) => {
            const zoomLevel = parseInt(e.target.value) / 100;
            this.renderer.zoomLevel = zoomLevel;
            this.renderer.applyZoom();
        });
    }

    /**
     * Create speed control for autoplay
     */
    createSpeedControl() {
        const speedContainer = document.createElement('div');
        speedContainer.className = 'flipbook-speed-control';
        
        const slider = document.createElement('input');
        slider.type = 'range';
        slider.className = 'flipbook-speed-slider';
        slider.min = '1000';
        slider.max = '10000';
        slider.value = this.config.autoplayDelay.toString();
        slider.step = '500';
        slider.setAttribute('aria-label', 'Autoplay speed control');
        
        const label = document.createElement('label');
        label.textContent = 'Speed: ';
        label.appendChild(slider);
        
        speedContainer.appendChild(label);
        
        // Insert into autoplay controls
        const autoplaySection = this.controls.querySelector('.flipbook-controls-secondary');
        if (autoplaySection) {
            autoplaySection.appendChild(speedContainer);
        }
        
        // Bind slider events
        slider.addEventListener('input', (e) => {
            this.config.autoplayDelay = parseInt(e.target.value);
            this.renderer.config.autoplayDelay = this.config.autoplayDelay;
            
            // Restart autoplay if it's currently running
            if (this.renderer.autoplayTimer) {
                this.renderer.stopAutoplay();
                this.renderer.startAutoplay();
            }
        });
    }

    /**
     * Bind advanced event handlers
     */
    bindAdvancedEvents() {
        // Double-click to toggle fullscreen
        this.container.addEventListener('dblclick', (e) => {
            if (this.config.enableFullscreen) {
                e.preventDefault();
                this.renderer.toggleFullscreen();
            }
        });
        
        // Mouse wheel for zoom
        this.container.addEventListener('wheel', (e) => {
            if (this.config.enableZoom && e.ctrlKey) {
                e.preventDefault();
                
                if (e.deltaY < 0) {
                    this.renderer.zoomIn();
                } else {
                    this.renderer.zoomOut();
                }
            }
        }, { passive: false });
        
        // Page input validation
        if (this.pageInput) {
            this.pageInput.addEventListener('blur', (e) => {
                const page = parseInt(e.target.value);
                if (isNaN(page) || page < 1 || page > this.renderer.totalPages) {
                    e.target.value = this.renderer.currentPage;
                }
            });
        }
        
        // Drag and drop for PDF files (if in edit mode)
        if (this.config.allowFileUpload) {
            this.setupDragAndDrop();
        }
    }

    /**
     * Setup drag and drop functionality
     */
    setupDragAndDrop() {
        this.container.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.container.classList.add('flipbook-dragover');
        });
        
        this.container.addEventListener('dragleave', (e) => {
            if (!this.container.contains(e.relatedTarget)) {
                this.container.classList.remove('flipbook-dragover');
            }
        });
        
        this.container.addEventListener('drop', (e) => {
            e.preventDefault();
            this.container.classList.remove('flipbook-dragover');
            
            const files = Array.from(e.dataTransfer.files);
            const pdfFiles = files.filter(file => file.type === 'application/pdf');
            
            if (pdfFiles.length > 0) {
                this.handleFileUpload(pdfFiles[0]);
            }
        });
    }

    /**
     * Handle file upload
     */
    handleFileUpload(file) {
        if (this.config.onFileUpload && typeof this.config.onFileUpload === 'function') {
            this.config.onFileUpload(file);
        } else {
            console.log('File uploaded:', file.name);
            this.showMessage('File uploaded: ' + file.name);
        }
    }

    /**
     * Setup auto-hide functionality for controls
     */
    setupAutoHide() {
        if (!this.config.autoHideControls) return;
        
        const hideDelay = this.config.autoHideDelay || 3000;
        
        // Show controls on mouse movement
        this.container.addEventListener('mousemove', () => {
            this.showControls();
            this.resetAutoHideTimer(hideDelay);
        });
        
        // Keep controls visible when hovering over them
        if (this.controls) {
            this.controls.addEventListener('mouseenter', () => {
                this.clearAutoHideTimer();
            });
            
            this.controls.addEventListener('mouseleave', () => {
                this.resetAutoHideTimer(hideDelay);
            });
        }
        
        // Initial auto-hide timer
        this.resetAutoHideTimer(hideDelay);
    }

    /**
     * Show controls
     */
    showControls() {
        if (this.controls && !this.isVisible) {
            this.controls.style.opacity = '1';
            this.controls.style.pointerEvents = 'auto';
            this.isVisible = true;
        }
    }

    /**
     * Hide controls
     */
    hideControls() {
        if (this.controls && this.isVisible && !this.renderer.isFullscreen) {
            this.controls.style.opacity = '0';
            this.controls.style.pointerEvents = 'none';
            this.isVisible = false;
        }
    }

    /**
     * Reset auto-hide timer
     */
    resetAutoHideTimer(delay) {
        this.clearAutoHideTimer();
        this.autoHideTimer = setTimeout(() => {
            this.hideControls();
        }, delay);
    }

    /**
     * Clear auto-hide timer
     */
    clearAutoHideTimer() {
        if (this.autoHideTimer) {
            clearTimeout(this.autoHideTimer);
            this.autoHideTimer = null;
        }
    }

    /**
     * Setup keyboard shortcuts help
     */
    setupKeyboardShortcuts() {
        // Create help button if it doesn't exist
        let helpButton = this.controls?.querySelector('.flipbook-btn-help');
        if (!helpButton && this.config.showHelpButton) {
            helpButton = this.createHelpButton();
        }
        
        if (helpButton) {
            helpButton.addEventListener('click', (e) => {
                e.preventDefault();
                this.toggleKeyboardHelp();
            });
        }
    }

    /**
     * Create help button
     */
    createHelpButton() {
        const helpButton = document.createElement('button');
        helpButton.className = 'flipbook-btn flipbook-btn-help';
        helpButton.setAttribute('aria-label', 'Show keyboard shortcuts');
        helpButton.innerHTML = '<span aria-hidden="true">?</span>';
        
        // Insert into secondary controls
        const secondaryControls = this.controls?.querySelector('.flipbook-controls-secondary');
        if (secondaryControls) {
            secondaryControls.appendChild(helpButton);
        }
        
        return helpButton;
    }

    /**
     * Toggle keyboard shortcuts help
     */
    toggleKeyboardHelp() {
        let helpModal = this.container.querySelector('.flipbook-keyboard-help');
        
        if (!helpModal) {
            helpModal = this.createKeyboardHelpModal();
        }
        
        const isVisible = helpModal.style.display !== 'none';
        helpModal.style.display = isVisible ? 'none' : 'block';
        
        if (!isVisible) {
            // Focus on close button for accessibility
            const closeButton = helpModal.querySelector('.flipbook-btn-close-help');
            if (closeButton) {
                closeButton.focus();
            }
        }
    }

    /**
     * Create keyboard help modal
     */
    createKeyboardHelpModal() {
        const modal = document.createElement('div');
        modal.className = 'flipbook-keyboard-help';
        modal.style.display = 'none';
        
        modal.innerHTML = `
            <div class="flipbook-keyboard-help-content">
                <h4>Keyboard Shortcuts</h4>
                <ul>
                    <li><kbd>←</kbd> / <kbd>→</kbd> - Navigate pages</li>
                    <li><kbd>Space</kbd> - Next page</li>
                    <li><kbd>Home</kbd> / <kbd>End</kbd> - First/Last page</li>
                    <li><kbd>+</kbd> / <kbd>-</kbd> - Zoom in/out</li>
                    <li><kbd>F</kbd> - Toggle fullscreen</li>
                    <li><kbd>Esc</kbd> - Exit fullscreen</li>
                </ul>
                <button class="flipbook-btn flipbook-btn-close-help">Close</button>
            </div>
        `;
        
        this.container.appendChild(modal);
        
        // Bind close button
        const closeButton = modal.querySelector('.flipbook-btn-close-help');
        closeButton.addEventListener('click', () => {
            modal.style.display = 'none';
        });
        
        // Close on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.style.display !== 'none') {
                modal.style.display = 'none';
            }
        });
        
        return modal;
    }

    /**
     * Setup right-click context menu
     */
    setupContextMenu() {
        this.container.addEventListener('contextmenu', (e) => {
            if (this.config.enableContextMenu) {
                e.preventDefault();
                this.showContextMenu(e.clientX, e.clientY);
            }
        });
        
        // Hide context menu on click elsewhere
        document.addEventListener('click', () => {
            this.hideContextMenu();
        });
    }

    /**
     * Show context menu
     */
    showContextMenu(x, y) {
        let contextMenu = this.container.querySelector('.flipbook-context-menu');
        
        if (!contextMenu) {
            contextMenu = this.createContextMenu();
        }
        
        // Position menu
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        contextMenu.style.display = 'block';
        
        // Adjust position if menu goes off-screen
        const rect = contextMenu.getBoundingClientRect();
        if (rect.right > window.innerWidth) {
            contextMenu.style.left = (x - rect.width) + 'px';
        }
        if (rect.bottom > window.innerHeight) {
            contextMenu.style.top = (y - rect.height) + 'px';
        }
    }

    /**
     * Create context menu
     */
    createContextMenu() {
        const menu = document.createElement('div');
        menu.className = 'flipbook-context-menu';
        menu.style.display = 'none';
        
        const menuItems = [
            { label: 'Previous Page', action: () => this.renderer.prevPage(), disabled: () => this.renderer.currentPage <= 1 },
            { label: 'Next Page', action: () => this.renderer.nextPage(), disabled: () => this.renderer.currentPage >= this.renderer.totalPages },
            { separator: true },
            { label: 'Zoom In', action: () => this.renderer.zoomIn(), enabled: () => this.config.enableZoom },
            { label: 'Zoom Out', action: () => this.renderer.zoomOut(), enabled: () => this.config.enableZoom },
            { label: 'Reset Zoom', action: () => this.renderer.zoomReset(), enabled: () => this.config.enableZoom },
            { separator: true },
            { label: 'Fullscreen', action: () => this.renderer.toggleFullscreen(), enabled: () => this.config.enableFullscreen },
            { label: 'Toggle Autoplay', action: () => this.renderer.toggleAutoplay(), enabled: () => this.config.autoplay }
        ];
        
        menuItems.forEach(item => {
            if (item.separator) {
                const separator = document.createElement('div');
                separator.className = 'context-menu-separator';
                menu.appendChild(separator);
            } else if (item.enabled === undefined || item.enabled()) {
                const menuItem = document.createElement('div');
                menuItem.className = 'context-menu-item';
                if (item.disabled && item.disabled()) {
                    menuItem.classList.add('disabled');
                } else {
                    menuItem.addEventListener('click', (e) => {
                        e.stopPropagation();
                        item.action();
                        this.hideContextMenu();
                    });
                }
                menuItem.textContent = item.label;
                menu.appendChild(menuItem);
            }
        });
        
        this.container.appendChild(menu);
        return menu;
    }

    /**
     * Hide context menu
     */
    hideContextMenu() {
        const contextMenu = this.container.querySelector('.flipbook-context-menu');
        if (contextMenu) {
            contextMenu.style.display = 'none';
        }
    }

    /**
     * Update control states
     */
    updateControls() {
        // Update page slider
        if (this.pageSlider) {
            this.pageSlider.value = this.renderer.currentPage;
        }
        
        // Update zoom slider
        if (this.zoomSlider) {
            this.zoomSlider.value = Math.round(this.renderer.zoomLevel * 100);
        }
        
        // Update context menu items
        this.updateContextMenu();
    }

    /**
     * Update context menu item states
     */
    updateContextMenu() {
        const contextMenu = this.container.querySelector('.flipbook-context-menu');
        if (!contextMenu) return;
        
        const items = contextMenu.querySelectorAll('.context-menu-item');
        items.forEach((item) => {
            // Update disabled states based on current flipbook state
            if (item.textContent === 'Previous Page') {
                item.classList.toggle('disabled', this.renderer.currentPage <= 1);
            } else if (item.textContent === 'Next Page') {
                item.classList.toggle('disabled', this.renderer.currentPage >= this.renderer.totalPages);
            }
        });
    }

    /**
     * Show temporary message
     */
    showMessage(message, duration = 3000) {
        let messageElement = this.container.querySelector('.flipbook-message');
        
        if (!messageElement) {
            messageElement = document.createElement('div');
            messageElement.className = 'flipbook-message';
            this.container.appendChild(messageElement);
        }
        
        messageElement.textContent = message;
        messageElement.style.display = 'block';
        
        setTimeout(() => {
            messageElement.style.display = 'none';
        }, duration);
    }

    /**
     * Advanced page navigation with smooth transitions
     */
    gotoPageWithTransition(pageNumber, transitionType = 'default') {
        const originalTransition = this.renderer.config.animationType;
        
        if (transitionType !== 'default') {
            this.renderer.config.animationType = transitionType;
        }
        
        this.renderer.gotoPage(pageNumber);
        
        // Restore original transition
        if (transitionType !== 'default') {
            setTimeout(() => {
                this.renderer.config.animationType = originalTransition;
            }, this.renderer.config.animationDuration);
        }
    }

    /**
     * Create bookmark for current page
     */
    createBookmark() {
        const bookmark = {
            page: this.renderer.currentPage,
            timestamp: Date.now(),
            title: `Page ${this.renderer.currentPage}`
        };
        
        // Store in localStorage or trigger callback
        if (this.config.onBookmarkCreate) {
            this.config.onBookmarkCreate(bookmark);
        } else {
            const bookmarks = JSON.parse(localStorage.getItem('flipbook-bookmarks') || '[]');
            bookmarks.push(bookmark);
            localStorage.setItem('flipbook-bookmarks', JSON.stringify(bookmarks));
            this.showMessage('Bookmark created for page ' + this.renderer.currentPage);
        }
    }

    /**
     * Load bookmarks
     */
    loadBookmarks() {
        if (this.config.onBookmarkLoad) {
            return this.config.onBookmarkLoad();
        } else {
            return JSON.parse(localStorage.getItem('flipbook-bookmarks') || '[]');
        }
    }

    /**
     * Destroy controls
     */
    destroy() {
        this.clearAutoHideTimer();
        
        // Remove context menu
        const contextMenu = this.container.querySelector('.flipbook-context-menu');
        if (contextMenu) {
            contextMenu.remove();
        }
        
        // Remove keyboard help
        const helpModal = this.container.querySelector('.flipbook-keyboard-help');
        if (helpModal) {
            helpModal.remove();
        }
        
        console.log('FlipbookControls destroyed');
    }
}

// Extend FlipbookRenderer to include enhanced controls
if (typeof FlipbookRenderer !== 'undefined') {
    const originalInit = FlipbookRenderer.prototype.init;
    
    FlipbookRenderer.prototype.init = function() {
        originalInit.call(this);
        
        // Initialize enhanced controls if enabled
        if (this.config.enhancedControls !== false) {
            this.controls = new FlipbookControls(this);
        }
    };
    
    const originalDestroy = FlipbookRenderer.prototype.destroy;
    
    FlipbookRenderer.prototype.destroy = function() {
        if (this.controls && typeof this.controls.destroy === 'function') {
            this.controls.destroy();
        }
        
        if (originalDestroy) {
            originalDestroy.call(this);
        }
    };
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = FlipbookControls;
}