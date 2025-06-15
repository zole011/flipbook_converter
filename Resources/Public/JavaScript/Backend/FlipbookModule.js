/**
 * FlipbookBackend - Backend module functionality for TYPO3
 * 
 * @author Gmbit
 * @version 1.0.0
 */
window.FlipbookBackend = (function() {
    'use strict';
    
    let initialized = false;
    let processingQueue = [];
    let statisticsCache = null;
    let refreshInterval = null;
    
    /**
     * Initialize backend module
     */
    function init() {
        if (initialized) return;
        
        setupBulkOperations();
        setupFileUpload();
        setupPreview();
        setupProcessingStatus();
        setupStatistics();
        setupFormValidation();
        setupKeyboardShortcuts();
        
        initialized = true;
        console.log('FlipbookBackend initialized');
    }
    
    /**
     * Setup bulk operations functionality
     */
    function setupBulkOperations() {
        const selectAllCheckbox = document.getElementById('select-all');
        const documentCheckboxes = document.querySelectorAll('.document-checkbox');
        const bulkForm = document.getElementById('bulk-form');
        const bulkSubmit = document.getElementById('bulk-submit');
        const selectedCountSpan = document.getElementById('selected-count');
        
        if (!selectAllCheckbox || !bulkForm) return;
        
        // Select all functionality
        selectAllCheckbox.addEventListener('change', function() {
            documentCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectedCount();
        });
        
        // Individual checkbox changes
        documentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateSelectedCount();
                
                // Update select-all state
                const checkedCount = document.querySelectorAll('.document-checkbox:checked').length;
                selectAllCheckbox.checked = checkedCount === documentCheckboxes.length;
                selectAllCheckbox.indeterminate = checkedCount > 0 && checkedCount < documentCheckboxes.length;
            });
        });
        
        // Bulk form submission
        bulkForm.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('.document-checkbox:checked').length;
            const action = document.querySelector('[name="bulkAction"]').value;
            
            if (selectedCount === 0) {
                e.preventDefault();
                showAlert('Please select at least one document.', 'warning');
                return;
            }
            
            if (!action) {
                e.preventDefault();
                showAlert('Please select an action.', 'warning');
                return;
            }
            
            // Confirm destructive actions
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${selectedCount} document(s)? This action cannot be undone.`)) {
                    e.preventDefault();
                    return;
                }
            }
            
            // Show processing indicator
            if (bulkSubmit) {
                bulkSubmit.disabled = true;
                bulkSubmit.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
            }
        });
        
        function updateSelectedCount() {
            const count = document.querySelectorAll('.document-checkbox:checked').length;
            if (selectedCountSpan) {
                selectedCountSpan.textContent = count;
            }
            
            if (bulkSubmit) {
                bulkSubmit.disabled = count === 0;
            }
        }
    }
    
    /**
     * Setup file upload with progress tracking
     */
    function setupFileUpload() {
        const fileInputs = document.querySelectorAll('input[type="file"]');
        
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                // Validate file type
                if (file.type !== 'application/pdf') {
                    showAlert('Please select a PDF file.', 'error');
                    e.target.value = '';
                    return;
                }
                
                // Validate file size (100MB limit)
                const maxSize = 100 * 1024 * 1024;
                if (file.size > maxSize) {
                    showAlert('File size must be less than 100MB.', 'error');
                    e.target.value = '';
                    return;
                }
                
                // Show file info
                showFileInfo(file, input);
            });
        });
        
        // Drag and drop functionality
        setupDragAndDrop();
    }
    
    /**
     * Setup drag and drop for file upload
     */
    function setupDragAndDrop() {
        const dropZones = document.querySelectorAll('.flipbook-file-upload');
        
        dropZones.forEach(zone => {
            zone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            zone.addEventListener('dragleave', function(e) {
                if (!this.contains(e.relatedTarget)) {
                    this.classList.remove('dragover');
                }
            });
            
            zone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = Array.from(e.dataTransfer.files);
                const pdfFile = files.find(file => file.type === 'application/pdf');
                
                if (pdfFile) {
                    const fileInput = this.querySelector('input[type="file"]');
                    if (fileInput) {
                        // Create new FileList with dropped file
                        const dt = new DataTransfer();
                        dt.items.add(pdfFile);
                        fileInput.files = dt.files;
                        
                        // Trigger change event
                        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                } else {
                    showAlert('Please drop a PDF file.', 'error');
                }
            });
        });
    }
    
    /**
     * Show file information
     */
    function showFileInfo(file, inputElement) {
        let infoContainer = inputElement.parentNode.querySelector('.file-info');
        
        if (!infoContainer) {
            infoContainer = document.createElement('div');
            infoContainer.className = 'file-info alert alert-info';
            inputElement.parentNode.appendChild(infoContainer);
        }
        
        const fileSize = formatFileSize(file.size);
        infoContainer.innerHTML = `
            <strong>Selected file:</strong> ${file.name}<br>
            <strong>Size:</strong> ${fileSize}<br>
            <strong>Type:</strong> ${file.type}
        `;
    }
    
    /**
     * Setup preview functionality
     */
    function setupPreview() {
        const previewButtons = document.querySelectorAll('.load-preview');
        
        previewButtons.forEach(button => {
            button.addEventListener('click', function() {
                const documentUid = this.dataset.documentUid;
                loadPreview(documentUid, this);
            });
        });
    }
    
    /**
     * Load flipbook preview
     */
    function loadPreview(documentUid, button) {
        const container = button.closest('.flipbook-preview-container');
        if (!container) return;
        
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Loading...';
        
        // Fetch document data
        fetch(`/typo3/module/flipbook/preview/${documentUid}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Create preview flipbook
                    createPreviewFlipbook(container, data.config);
                    button.style.display = 'none';
                } else {
                    throw new Error(data.message || 'Failed to load preview');
                }
            })
            .catch(error => {
                console.error('Preview loading failed:', error);
                showAlert('Failed to load preview: ' + error.message, 'error');
                button.disabled = false;
                button.innerHTML = '<i class="fa fa-refresh"></i> Load Preview';
            });
    }
    
    /**
     * Create preview flipbook
     */
    function createPreviewFlipbook(container, config) {
        container.innerHTML = `
            <div class="flipbook-container flipbook-preview" 
                 id="preview-${config.documentUid}"
                 data-config='${JSON.stringify(config)}'
                 style="width: ${config.width}px; height: ${config.height}px; margin: 0 auto;">
                <div class="flipbook-loading">
                    <div class="loading-spinner"></div>
                    <div class="loading-text">Loading preview...</div>
                </div>
            </div>
        `;
        
        // Initialize flipbook if renderer is available
        if (typeof FlipbookRenderer !== 'undefined') {
            new FlipbookRenderer(config);
        }
    }
    
    /**
     * Setup processing status monitoring
     */
    function setupProcessingStatus() {
        const processingElements = document.querySelectorAll('.status-processing');
        
        if (processingElements.length > 0) {
            startStatusMonitoring();
        }
        
        // Setup processing action buttons
        const processButtons = document.querySelectorAll('[data-action="process"]');
        processButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const documentUid = this.dataset.documentUid;
                if (documentUid) {
                    processDocument(documentUid, this);
                }
            });
        });
    }
    
    /**
     * Start monitoring processing status
     */
    function startStatusMonitoring() {
        if (refreshInterval) return;
        
        refreshInterval = setInterval(() => {
            checkProcessingStatus();
        }, 5000); // Check every 5 seconds
    }
    
    /**
     * Stop monitoring processing status
     */
    function stopStatusMonitoring() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
    
    /**
     * Check processing status of documents
     */
    function checkProcessingStatus() {
        const processingElements = document.querySelectorAll('.status-processing');
        
        if (processingElements.length === 0) {
            stopStatusMonitoring();
            return;
        }
        
        const documentUids = Array.from(processingElements).map(el => {
            return el.closest('[data-uid]').dataset.uid;
        });
        
        fetch('/typo3/module/flipbook/status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ documents: documentUids })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateDocumentStatuses(data.statuses);
            }
        })
        .catch(error => {
            console.error('Status check failed:', error);
        });
    }
    
    /**
     * Update document status indicators
     */
    function updateDocumentStatuses(statuses) {
        Object.entries(statuses).forEach(([uid, status]) => {
            const row = document.querySelector(`[data-uid="${uid}"]`);
            if (!row) return;
            
            const statusCell = row.querySelector('.status-cell');
            const statusBadge = statusCell?.querySelector('.flipbook-status-processing, .flipbook-status-completed, .flipbook-status-error');
            
            if (statusBadge && statusBadge.textContent !== status.label) {
                // Status changed, refresh the page
                location.reload();
            }
        });
    }
    
    /**
     * Process document manually
     */
    function processDocument(documentUid, button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
        
        fetch(`/typo3/module/flipbook/process/${documentUid}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('Document processing started successfully.', 'success');
                
                // Start monitoring if not already started
                startStatusMonitoring();
                
                // Refresh after delay
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } else {
                throw new Error(data.message || 'Processing failed');
            }
        })
        .catch(error => {
            console.error('Processing failed:', error);
            showAlert('Failed to start processing: ' + error.message, 'error');
            button.disabled = false;
            button.innerHTML = '<i class="fa fa-play"></i> Process';
        });
    }
    
    /**
     * Setup statistics functionality
     */
    function setupStatistics() {
        const statsButton = document.getElementById('show-statistics');
        if (!statsButton) return;
        
        statsButton.addEventListener('click', function() {
            showStatisticsModal();
        });
        
        // Refresh statistics cache periodically
        setInterval(() => {
            statisticsCache = null;
        }, 60000); // Refresh every minute
    }
    
    /**
     * Show statistics modal
     */
    function showStatisticsModal() {
        const modal = createModal('Flipbook Statistics', 'Loading statistics...');
        modal.show();
        
        loadStatistics().then(stats => {
            const content = createStatisticsContent(stats);
            modal.setContent(content);
        }).catch(error => {
            modal.setContent('<div class="alert alert-danger">Failed to load statistics</div>');
        });
    }
    
    /**
     * Load statistics data
     */
    function loadStatistics() {
        if (statisticsCache) {
            return Promise.resolve(statisticsCache);
        }
        
        return fetch('/typo3/module/flipbook/statistics')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statisticsCache = data.statistics;
                    return statisticsCache;
                } else {
                    throw new Error(data.message || 'Failed to load statistics');
                }
            });
    }
    
    /**
     * Create statistics content
     */
    function createStatisticsContent(stats) {
        return `
            <div class="statistics-container">
                <div class="row">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa fa-file-pdf-o fa-3x"></i></div>
                            <div class="stat-value">${stats.totalDocuments}</div>
                            <div class="stat-label">Total Documents</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa fa-check-circle fa-3x text-success"></i></div>
                            <div class="stat-value">${stats.completedDocuments}</div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa fa-clock-o fa-3x text-warning"></i></div>
                            <div class="stat-value">${stats.pendingDocuments}</div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon"><i class="fa fa-exclamation-triangle fa-3x text-danger"></i></div>
                            <div class="stat-value">${stats.errorDocuments}</div>
                            <div class="stat-label">Errors</div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h4>Processing Statistics</h4>
                        <table class="table table-sm">
                            <tr>
                                <td>Total Pages Processed:</td>
                                <td><strong>${stats.totalPages}</strong></td>
                            </tr>
                            <tr>
                                <td>Average Processing Time:</td>
                                <td><strong>${stats.avgProcessingTime}s</strong></td>
                            </tr>
                            <tr>
                                <td>Total Disk Usage:</td>
                                <td><strong>${formatFileSize(stats.totalDiskUsage)}</strong></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h4>Recent Activity</h4>
                        <table class="table table-sm">
                            <tr>
                                <td>Documents Today:</td>
                                <td><strong>${stats.documentsToday}</strong></td>
                            </tr>
                            <tr>
                                <td>Documents This Week:</td>
                                <td><strong>${stats.documentsThisWeek}</strong></td>
                            </tr>
                            <tr>
                                <td>Documents This Month:</td>
                                <td><strong>${stats.documentsThisMonth}</strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                ${stats.topDocuments ? `
                    <hr>
                    <h4>Most Viewed Documents</h4>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Views</th>
                                <th>Last Viewed</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${stats.topDocuments.map(doc => `
                                <tr>
                                    <td>${doc.title}</td>
                                    <td>${doc.viewCount}</td>
                                    <td>${formatDate(doc.lastViewed)}</td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                ` : ''}
            </div>
        `;
    }
    
    /**
     * Setup form validation
     */
    function setupFormValidation() {
        const forms = document.querySelectorAll('.needs-validation');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                form.classList.add('was-validated');
            });
            
            // Real-time validation for inputs
            const inputs = form.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.addEventListener('blur', function() {
                    validateInput(this);
                });
                
                input.addEventListener('input', function() {
                    if (this.classList.contains('is-invalid')) {
                        validateInput(this);
                    }
                });
            });
        });
    }
    
    /**
     * Validate single input
     */
    function validateInput(input) {
        const isValid = input.checkValidity();
        
        if (isValid) {
            input.classList.remove('is-invalid');
            input.classList.add('is-valid');
        } else {
            input.classList.remove('is-valid');
            input.classList.add('is-invalid');
        }
        
        // Show/hide error message
        const errorElement = input.parentNode.querySelector('.invalid-feedback');
        if (errorElement) {
            errorElement.style.display = isValid ? 'none' : 'block';
        }
    }
    
    /**
     * Setup keyboard shortcuts
     */
    function setupKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N: New document
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                const newButton = document.querySelector('[data-action="new"]');
                if (newButton) newButton.click();
            }
            
            // Ctrl/Cmd + S: Save (in forms)
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                const activeForm = document.querySelector('form:focus-within');
                if (activeForm) {
                    e.preventDefault();
                    activeForm.dispatchEvent(new Event('submit'));
                }
            }
            
            // Ctrl/Cmd + F: Focus search
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[type="search"]');
                if (searchInput) searchInput.focus();
            }
            
            // Escape: Close modals
            if (e.key === 'Escape') {
                const activeModal = document.querySelector('.modal.show');
                if (activeModal) {
                    activeModal.querySelector('[data-dismiss="modal"]')?.click();
                }
            }
        });
    }
    
    /**
     * Show alert message
     */
    function showAlert(message, type = 'info') {
        const alertContainer = document.getElementById('alert-container') || createAlertContainer();
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="close" data-dismiss="alert">
                <span>&times;</span>
            </button>
        `;
        
        alertContainer.appendChild(alert);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        }, 5000);
        
        // Manual dismiss
        alert.querySelector('.close').addEventListener('click', function() {
            alert.classList.remove('show');
            setTimeout(() => alert.remove(), 150);
        });
    }
    
    /**
     * Create alert container
     */
    function createAlertContainer() {
        const container = document.createElement('div');
        container.id = 'alert-container';
        container.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;';
        document.body.appendChild(container);
        return container;
    }
    
    /**
     * Create modal
     */
    function createModal(title, content) {
        const modalId = 'modal-' + Date.now();
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${title}</h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            ${content}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        const modalElement = document.getElementById(modalId);
        
        // Setup close handlers
        modalElement.querySelectorAll('[data-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', () => {
                modalElement.classList.remove('show');
                modalElement.style.display = 'none';
                document.body.classList.remove('modal-open');
                setTimeout(() => modalElement.remove(), 300);
            });
        });
        
        return {
            show: function() {
                modalElement.style.display = 'block';
                setTimeout(() => {
                    modalElement.classList.add('show');
                    document.body.classList.add('modal-open');
                }, 10);
            },
            hide: function() {
                modalElement.querySelector('[data-dismiss="modal"]').click();
            },
            setContent: function(newContent) {
                modalElement.querySelector('.modal-body').innerHTML = newContent;
            }
        };
    }
    
    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    /**
     * Format date
     */
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
    }
    
    // Public API
    return {
        init: init,
        loadPreview: loadPreview,
        processDocument: processDocument,
        showAlert: showAlert,
        showStatistics: showStatisticsModal
    };
})();

// Initialize on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', FlipbookBackend.init);
} else {
    FlipbookBackend.init();
}