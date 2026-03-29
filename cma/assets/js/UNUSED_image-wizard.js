/**
 * CMA Image Wizard
 * Modern image upload and cropping wizard
 * Uses Cropper.js for image manipulation
 */
(function() {
    'use strict';

    const CMA_IMAGE_WIZARD = {
        cropper: null,
        currentFile: null,
        options: {},
        onComplete: null,

        /**
         * Initialize the image wizard
         * @param {Object} options Configuration options
         * @param {number} options.maxWidth - Maximum image width
         * @param {number} options.maxHeight - Maximum image height
         * @param {string} options.mode - 'fixed' for exact size, 'max' for maximum size
         * @param {string} options.path - Target upload path
         * @param {boolean} options.randomName - Use random filename
         * @param {Function} options.onComplete - Callback when complete
         */
        init: function(options) {
            this.options = Object.assign({
                maxWidth: 800,
                maxHeight: 600,
                mode: 'max',
                path: '/images/',
                randomName: true,
                aspectRatio: null // null = free, number = fixed ratio
            }, options);

            if (options.mode === 'fixed' && options.maxWidth && options.maxHeight) {
                this.options.aspectRatio = options.maxWidth / options.maxHeight;
            }

            this.onComplete = options.onComplete || function() {};
            this.createModal();
        },

        /**
         * Create the wizard modal
         */
        createModal: function() {
            // Remove existing modal if any
            const existing = document.getElementById('cma-image-wizard-modal');
            if (existing) existing.remove();

            const modal = document.createElement('div');
            modal.id = 'cma-image-wizard-modal';
            modal.className = 'cma-img-wizard-overlay';
            modal.innerHTML = `
                <div class="cma-img-wizard-modal">
                    <div class="cma-img-wizard-header">
                        <h3>Afbeelding plaatsen</h3>
                        <button type="button" class="cma-img-wizard-close" onclick="CMA_IMAGE_WIZARD.close()">&times;</button>
                    </div>
                    <div class="cma-img-wizard-body">
                        <div class="cma-img-wizard-steps">
                            <span class="cma-img-wizard-step active" data-step="1">1</span>
                            <span class="cma-img-wizard-step-line"></span>
                            <span class="cma-img-wizard-step" data-step="2">2</span>
                        </div>

                        <!-- Step 1: Upload -->
                        <div class="cma-img-wizard-page" id="wizard-page-1">
                            <div class="cma-img-wizard-dropzone" id="wizard-dropzone">
                                <input type="file" id="wizard-file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
                                <div class="cma-img-wizard-dropzone-content">
                                    <span class="lnr lnr-upload"></span>
                                    <button type="button" class="cma-img-wizard-btn cma-img-wizard-btn-primary" onclick="document.getElementById('wizard-file-input').click()">
                                        Selecteer bestand
                                    </button>
                                    <p>of sleep bestanden hierheen</p>
                                    <p class="cma-img-wizard-hint">Ondersteund: JPG, PNG, GIF, WebP (max 10MB)</p>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Crop -->
                        <div class="cma-img-wizard-page" id="wizard-page-2" style="display:none">
                            <div class="cma-img-wizard-crop-container">
                                <img id="wizard-crop-image" src="" alt="Crop preview">
                            </div>
                            <div class="cma-img-wizard-toolbar">
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.rotate(-90)" title="Linksom draaien">
                                    <span class="lnr lnr-undo"></span>
                                </button>
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.rotate(90)" title="Rechtsom draaien">
                                    <span class="lnr lnr-redo"></span>
                                </button>
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.flip('h')" title="Horizontaal spiegelen">
                                    ↔
                                </button>
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.flip('v')" title="Verticaal spiegelen">
                                    ↕
                                </button>
                                <span class="cma-img-wizard-tool-separator"></span>
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.zoom(0.1)" title="Inzoomen">
                                    <span class="lnr lnr-plus-circle"></span>
                                </button>
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.zoom(-0.1)" title="Uitzoomen">
                                    <span class="lnr lnr-circle-minus"></span>
                                </button>
                                <button type="button" class="cma-img-wizard-tool" onclick="CMA_IMAGE_WIZARD.reset()" title="Reset">
                                    <span class="lnr lnr-sync"></span>
                                </button>
                            </div>
                            <div class="cma-img-wizard-info" id="wizard-crop-info"></div>
                        </div>
                    </div>
                    <div class="cma-img-wizard-footer">
                        <button type="button" class="cma-img-wizard-btn" onclick="CMA_IMAGE_WIZARD.close()">Annuleren</button>
                        <button type="button" class="cma-img-wizard-btn" id="wizard-back-btn" style="display:none" onclick="CMA_IMAGE_WIZARD.goToStep(1)">Terug</button>
                        <button type="button" class="cma-img-wizard-btn cma-img-wizard-btn-primary" id="wizard-finish-btn" style="display:none" onclick="CMA_IMAGE_WIZARD.finish()">
                            Plaatsen
                        </button>
                    </div>
                    <div class="cma-img-wizard-loading" id="wizard-loading" style="display:none">
                        <div class="cma-img-wizard-spinner"></div>
                        <p>Bezig met verwerken...</p>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);

            // Setup event handlers
            this.setupEventHandlers();
        },

        /**
         * Setup event handlers for file input and drag/drop
         */
        setupEventHandlers: function() {
            const fileInput = document.getElementById('wizard-file-input');
            const dropzone = document.getElementById('wizard-dropzone');

            fileInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) {
                    this.handleFile(e.target.files[0]);
                }
            });

            // Drag and drop
            dropzone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            });

            dropzone.addEventListener('dragleave', () => {
                dropzone.classList.remove('dragover');
            });

            dropzone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    this.handleFile(e.dataTransfer.files[0]);
                }
            });

            // Close on overlay click
            document.getElementById('cma-image-wizard-modal').addEventListener('click', (e) => {
                if (e.target.id === 'cma-image-wizard-modal') {
                    this.close();
                }
            });

            // Close on escape
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.close();
                }
            });
        },

        /**
         * Handle selected file
         * @param {File} file
         */
        handleFile: function(file) {
            // Validate file
            if (!file.type.match(/^image\/(jpeg|png|gif|webp)$/)) {
                alert('Alleen JPG, PNG, GIF en WebP afbeeldingen zijn toegestaan.');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('Het bestand is te groot. Maximum is 10MB.');
                return;
            }

            this.currentFile = file;

            // Read file and show in cropper
            const reader = new FileReader();
            reader.onload = (e) => {
                this.showCropper(e.target.result);
            };
            reader.readAsDataURL(file);
        },

        /**
         * Show the cropper for the image
         * @param {string} imageData Base64 image data
         */
        showCropper: function(imageData) {
            const img = document.getElementById('wizard-crop-image');
            if (!img) {
                cmaLog.error('[ImageWizard] Crop image element not found: wizard-crop-image');
                alert('Fout: Afbeeldingseditor niet beschikbaar');
                return;
            }
            img.src = imageData;

            this.goToStep(2);

            // Wait for image to load, then initialize cropper
            img.onload = () => {
                // Destroy existing cropper
                if (this.cropper) {
                    this.cropper.destroy();
                }

                // Initialize Cropper.js
                const cropperOptions = {
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    responsive: true,
                    restore: false,
                    guides: true,
                    center: true,
                    highlight: true,
                    cropBoxMovable: true,
                    cropBoxResizable: true,
                    toggleDragModeOnDblclick: false,
                    crop: (event) => {
                        this.updateCropInfo(event.detail);
                    }
                };

                // Set aspect ratio if fixed mode
                if (this.options.aspectRatio) {
                    cropperOptions.aspectRatio = this.options.aspectRatio;
                }

                try {
                    if (typeof Cropper === 'undefined') {
                        throw new Error('Cropper.js library is not loaded');
                    }
                    this.cropper = new Cropper(img, cropperOptions);
                } catch (e) {
                    cmaLog.error('[ImageWizard] Failed to initialize Cropper:', e.message);
                    alert('Fout bij initialiseren van afbeeldingseditor: ' + e.message);
                }
            };
        },

        /**
         * Update crop info display
         */
        updateCropInfo: function(detail) {
            const info = document.getElementById('wizard-crop-info');
            if (!info) return; // Silently skip if element not available yet

            const width = Math.round(detail.width);
            const height = Math.round(detail.height);

            let infoText = `Selectie: ${width} × ${height} px`;
            if (this.options.mode === 'fixed') {
                infoText += ` → ${this.options.maxWidth} × ${this.options.maxHeight} px`;
            } else if (this.options.mode === 'max') {
                const scale = Math.min(1, this.options.maxWidth / width, this.options.maxHeight / height);
                const finalW = Math.round(width * scale);
                const finalH = Math.round(height * scale);
                infoText += ` → ${finalW} × ${finalH} px`;
            }
            info.textContent = infoText;
        },

        /**
         * Go to a specific step
         * @param {number} step
         */
        goToStep: function(step) {
            document.querySelectorAll('.cma-img-wizard-page').forEach(p => p.style.display = 'none');
            const page = document.getElementById('wizard-page-' + step);
            if (page) {
                page.style.display = 'block';
            } else {
                cmaLog.error('[ImageWizard] Page not found: wizard-page-' + step);
            }

            document.querySelectorAll('.cma-img-wizard-step').forEach(s => s.classList.remove('active'));
            const stepEl = document.querySelector('.cma-img-wizard-step[data-step="' + step + '"]');
            if (stepEl) {
                stepEl.classList.add('active');
            }

            const backBtn = document.getElementById('wizard-back-btn');
            const finishBtn = document.getElementById('wizard-finish-btn');
            if (backBtn) backBtn.style.display = step > 1 ? '' : 'none';
            if (finishBtn) finishBtn.style.display = step === 2 ? '' : 'none';
        },

        /**
         * Rotate the image
         * @param {number} degrees
         */
        rotate: function(degrees) {
            if (this.cropper) {
                this.cropper.rotate(degrees);
            }
        },

        /**
         * Flip the image
         * @param {string} direction 'h' for horizontal, 'v' for vertical
         */
        flip: function(direction) {
            if (this.cropper) {
                const data = this.cropper.getData();
                if (direction === 'h') {
                    this.cropper.scaleX(data.scaleX === -1 ? 1 : -1);
                } else {
                    this.cropper.scaleY(data.scaleY === -1 ? 1 : -1);
                }
            }
        },

        /**
         * Zoom the image
         * @param {number} ratio
         */
        zoom: function(ratio) {
            if (this.cropper) {
                this.cropper.zoom(ratio);
            }
        },

        /**
         * Reset cropper to original state
         */
        reset: function() {
            if (this.cropper) {
                this.cropper.reset();
            }
        },

        /**
         * Finish and upload the cropped image
         */
        finish: function() {
            if (!this.cropper) return;

            const loadingEl = document.getElementById('wizard-loading');
            if (loadingEl) loadingEl.style.display = 'flex';

            // Get cropped canvas
            const canvas = this.cropper.getCroppedCanvas({
                maxWidth: this.options.maxWidth,
                maxHeight: this.options.maxHeight,
                imageSmoothingEnabled: true,
                imageSmoothingQuality: 'high'
            });

            // Convert to blob and upload
            canvas.toBlob((blob) => {
                this.uploadImage(blob);
            }, 'image/jpeg', 0.92);
        },

        /**
         * Upload the cropped image to server
         * @param {Blob} blob
         */
        uploadImage: function(blob) {
            const formData = new FormData();

            // Generate filename
            let filename = this.currentFile.name;
            if (this.options.randomName) {
                const ext = filename.split('.').pop().toLowerCase();
                filename = this.generateRandomName() + '.jpg';
            } else {
                // Force .jpg extension for cropped images
                filename = filename.replace(/\.[^.]+$/, '.jpg');
            }

            formData.append('action', 'uploadImage');
            formData.append('image', blob, filename);
            formData.append('path', this.options.path);
            formData.append('maxWidth', this.options.maxWidth);
            formData.append('maxHeight', this.options.maxHeight);

            fetch('/cma/form_api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status} ${response.statusText}`);
                }
                return response.json();
            })
            .then(result => {
                const loadingEl = document.getElementById('wizard-loading');
                if (loadingEl) loadingEl.style.display = 'none';

                if (result.success) {
                    this.onComplete({
                        filename: result.filename,
                        path: result.path,
                        width: result.width,
                        height: result.height,
                        fullPath: result.fullPath
                    });
                    this.close();
                } else {
                    cmaLog.error('[ImageWizard] Upload failed:', result.error || 'Unknown error');
                    alert('Fout bij uploaden: ' + (result.error || 'Onbekende fout'));
                }
            })
            .catch(error => {
                cmaLog.error('[ImageWizard] Upload error:', error.message);
                const loadingEl = document.getElementById('wizard-loading');
                if (loadingEl) loadingEl.style.display = 'none';
                alert('Fout bij uploaden: ' + error.message);
            });
        },

        /**
         * Generate random filename
         */
        generateRandomName: function() {
            const chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            let result = '';
            for (let i = 0; i < 12; i++) {
                result += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return result;
        },

        /**
         * Close the wizard
         */
        close: function() {
            if (this.cropper) {
                this.cropper.destroy();
                this.cropper = null;
            }
            const modal = document.getElementById('cma-image-wizard-modal');
            if (modal) {
                modal.remove();
            }
        },

        /**
         * Open the wizard from a form field
         * @param {string} fieldName The field name for the image
         * @param {Object} options Configuration options
         */
        openForField: function(fieldName, options) {
            options = options || {};
            options.onComplete = function(result) {
                // Update the hidden field with the filename
                const field = document.querySelector('[name="' + fieldName + '"]');
                if (field) {
                    field.value = result.filename;
                }

                // Update preview image
                const preview = document.querySelector('[name="' + fieldName + '_preview"]');
                if (preview) {
                    preview.src = result.fullPath;
                }

                // Update width/height hidden fields if they exist
                const widthField = document.querySelector('[name="' + fieldName + '_width"]');
                if (widthField) widthField.value = result.width;

                const heightField = document.querySelector('[name="' + fieldName + '_height"]');
                if (heightField) heightField.value = result.height;

                // Mark form as dirty
                if (typeof lib_mark_form_dirty === 'function') {
                    lib_mark_form_dirty();
                }
            };

            this.init(options);
        }
    };

    // Export to global scope
    window.CMA_IMAGE_WIZARD = CMA_IMAGE_WIZARD;
})();
