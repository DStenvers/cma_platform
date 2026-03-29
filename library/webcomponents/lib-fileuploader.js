/**
 * lib-fileuploader Web Component
 *
 * Modern replacement for the FineUploader jQuery plugin.
 * Uses native fetch() + FormData for file uploads with Shadow DOM encapsulation.
 *
 * Usage:
 *   <lib-fileuploader
 *       field="filename"
 *       path="uploads/portfolio/"
 *       path-extra="OPL123/Student_20260213_"
 *       extensions="pdf,doc,docx,rtf,jpg,jpeg"
 *       max-size="10485760"
 *       button-text="Plaats bestand">
 *   </lib-fileuploader>
 *
 * Attributes:
 *   - endpoint:    Upload handler URL (default: "upload_handler.php")
 *   - field:       ID of hidden input to store uploaded filename (required)
 *   - path:        Base upload directory
 *   - path-extra:  Dynamic prefix prepended to filename
 *   - extensions:  Comma-separated allowed file extensions (default: "pdf,doc,docx,rtf,jpg,jpeg")
 *   - max-size:    Max file size in bytes (default: 10485760 = 10MB)
 *   - button-text: Custom button label (default: "Plaats bestand")
 *   - type-error:  Custom type error message (auto-generated from extensions if omitted)
 *   - multiple:    Allow multiple file uploads (default: false)
 *   - show-link:   Show "Bekijk bestand" link after upload (default: true)
 *   - link-base:   Base URL for the file link (defaults to path value)
 *
 * Events:
 *   - upload-complete: detail: { filename, originalName, path }
 *   - upload-error:    detail: { error, fileName }
 *
 * Methods:
 *   - reset(): Clear upload state and reset to initial state
 */

if (!customElements.get('lib-fileuploader')) {

class LibFileUploader extends HTMLElement {
    static get observedAttributes() {
        return ['endpoint', 'field', 'path', 'path-extra', 'extensions', 'max-size',
                'button-text', 'type-error', 'multiple', 'show-link', 'link-base'];
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._uploading = false;
        this._uploadedFiles = [];
    }

    connectedCallback() {
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            try {
                LibSharedStyles.adopt(this.shadowRoot, 'base', 'button');
            } catch (e) { /* cross-document stylesheet sharing not allowed */ }
        }
        this.render();
        this._bindEvents();
    }

    disconnectedCallback() {
        // Cleanup
        if (this._dragoverHandler) {
            this.shadowRoot.querySelector('.upload-zone').removeEventListener('dragover', this._dragoverHandler);
        }
    }

    attributeChangedCallback(name, oldVal, newVal) {
        if (oldVal !== newVal && this.shadowRoot.querySelector('.upload-zone')) {
            if (name === 'button-text') {
                const btn = this.shadowRoot.querySelector('.upload-btn span');
                if (btn) btn.textContent = newVal || 'Plaats bestand';
            }
        }
    }

    // --- Public API ---

    get endpoint() { return this.getAttribute('endpoint') || 'upload_handler.php'; }
    get field() { return this.getAttribute('field') || ''; }
    get path() { return this.getAttribute('path') || ''; }
    get pathExtra() { return this.getAttribute('path-extra') || ''; }
    get extensions() {
        const ext = this.getAttribute('extensions');
        return ext ? ext.split(',').map(function(e) { return e.trim().toLowerCase(); }) : ['pdf', 'doc', 'docx', 'rtf', 'jpg', 'jpeg'];
    }
    get maxSize() { return parseInt(this.getAttribute('max-size'), 10) || 10485760; }
    get buttonText() { return this.getAttribute('button-text') || 'Plaats bestand'; }
    get typeError() { return this.getAttribute('type-error') || ''; }
    get isMultiple() { return this.hasAttribute('multiple'); }
    get showLink() { return this.getAttribute('show-link') !== 'false'; }
    get linkBase() { return this.getAttribute('link-base') || this.path; }

    /** Reset the uploader to initial state */
    reset() {
        this._uploadedFiles = [];
        this._uploading = false;
        const fileInput = this.shadowRoot.querySelector('input[type="file"]');
        if (fileInput) fileInput.value = '';

        const zone = this.shadowRoot.querySelector('.upload-zone');
        if (zone) zone.classList.remove('success', 'error');

        const fileList = this.shadowRoot.querySelector('.file-list');
        if (fileList) fileList.innerHTML = '';

        const progress = this.shadowRoot.querySelector('.progress-bar');
        if (progress) {
            progress.style.display = 'none';
            progress.querySelector('.progress-fill').style.width = '0%';
        }

        // Clear the associated hidden input
        if (this.field) {
            const hiddenInput = document.getElementById(this.field);
            if (hiddenInput) hiddenInput.value = '';
        }
    }

    // --- Internal ---

    render() {
        // Get icon styles if available (CMA context)
        var iconStyles = '';
        if (typeof CMA !== 'undefined' && typeof CMA.getIconStylesFor === 'function') {
            iconStyles = CMA.getIconStylesFor(['upload', 'file-check', 'cross', 'file-empty']);
        }

        this.shadowRoot.innerHTML = `
            <style>
                ${iconStyles}

                :host {
                    display: block;
                    margin-top: 3px;
                    margin-bottom: 12px;
                    max-width: 380px;
                    font-family: var(--font-family, 'Open Sans', sans-serif);
                    font-size: var(--font-size, 13px);
                }

                .upload-zone {
                    position: relative;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 4px;
                    background: var(--bg-surface, #fff);
                    transition: border-color 0.2s ease, background 0.2s ease;
                }

                .upload-zone.dragover {
                    border-color: var(--color-primary, #003366);
                    background: var(--bg-hover, #f0f4f8);
                    border-style: dashed;
                }

                .upload-zone.success {
                    border-color: var(--color-success, #5DA30C);
                }

                .upload-zone.error {
                    border-color: var(--color-danger, #D60000);
                }

                .upload-btn {
                    display: flex;
                    align-items: center;
                    gap: 6px;
                    padding: 0 8px;
                    height: 25px;
                    background: var(--bg-surface-alt, #eff2f4);
                    border: none;
                    border-radius: 4px;
                    color: var(--text-primary, #002350);
                    font-family: inherit;
                    font-size: inherit;
                    font-weight: normal;
                    cursor: pointer;
                    width: 100%;
                    text-align: left;
                    transition: background 0.15s ease;
                }

                .upload-btn:hover {
                    background: var(--bg-hover, #d0d7df);
                }

                .upload-btn:focus {
                    outline: none;
                    box-shadow: 0 0 0 3px var(--input-focus-shadow, rgba(0,51,102,0.2));
                }

                .upload-btn .icon {
                    font-family: 'Linearicons';
                    font-size: var(--font-size-md);
                    line-height: 1;
                }

                .upload-btn .icon::before {
                    content: "\\e8f4"; /* upload icon */
                }

                input[type="file"] {
                    display: none;
                }

                .progress-bar {
                    display: none;
                    height: 4px;
                    background: var(--border-color, #ddd);
                    border-radius: 0 0 4px 4px;
                    overflow: hidden;
                }

                .progress-fill {
                    height: 100%;
                    width: 0%;
                    background: var(--color-primary, #003366);
                    transition: width 0.3s ease;
                    border-radius: 0 0 4px 4px;
                }

                .file-list {
                    list-style: none;
                    margin: 0;
                    padding: 0;
                }

                .file-item {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    padding: 4px 8px;
                    height: 25px;
                    background: var(--bg-surface-alt, #eff2f4);
                    border-top: 1px solid var(--border-color, #ddd);
                    font-size: var(--font-size-sm);
                    color: var(--text-primary, #002350);
                    border-radius: 0 4px 4px 0;
                }

                .file-item a {
                    color: var(--text-primary, #002350);
                    text-decoration: none;
                    font-weight: normal;
                }

                .file-item a:hover {
                    text-decoration: underline;
                    color: var(--color-primary, #003366);
                }

                .file-item .file-icon {
                    font-family: 'Linearicons';
                    font-size: var(--font-size);
                    color: var(--color-success, #5DA30C);
                }

                .file-item .file-icon::before {
                    content: "\\e6b5"; /* file-check icon */
                }

                .file-item .remove-btn {
                    margin-left: auto;
                    background: none;
                    border: none;
                    cursor: pointer;
                    font-family: 'Linearicons';
                    font-size: var(--font-size-sm);
                    color: var(--text-secondary, #999);
                    padding: 2px;
                    line-height: 1;
                }

                .file-item .remove-btn:hover {
                    color: var(--color-danger, #D60000);
                }

                .file-item .remove-btn::before {
                    content: "\\e92a"; /* cross icon */
                }

                .error-msg {
                    padding: 4px 8px;
                    font-size: var(--font-size-xs);
                    color: var(--color-danger, #D60000);
                    background: var(--bg-danger-light, #fff0f0);
                    border-top: 1px solid var(--border-color, #ddd);
                }

                /* When zone has success class, show button and file side by side */
                .upload-zone.success .upload-btn {
                    border-radius: 4px 0 0 4px;
                    width: auto;
                    min-width: 155px;
                }

                .upload-zone.success {
                    display: flex;
                    flex-wrap: wrap;
                }

                .upload-zone.success .file-list {
                    flex: 1;
                }

                .upload-zone.success .file-item {
                    border-top: none;
                }

                .upload-zone.success .progress-bar {
                    width: 100%;
                }

                .uploading .upload-btn {
                    opacity: 0.6;
                    pointer-events: none;
                }
            </style>

            <div class="upload-zone" part="zone">
                <button type="button" class="upload-btn" part="button">
                    <span class="icon"></span>
                    <span>${this._escHtml(this.buttonText)}</span>
                </button>
                <input type="file"${this.isMultiple ? ' multiple' : ''}
                       accept="${this.extensions.map(function(e) { return '.' + e; }).join(',')}">
                <ul class="file-list" part="file-list"></ul>
                <div class="progress-bar" part="progress">
                    <div class="progress-fill"></div>
                </div>
            </div>
        `;

        // Hide the associated hidden input (matching FineUploader behavior from uploader_buttonsinit)
        if (this.field) {
            var hiddenInput = document.getElementById(this.field);
            if (hiddenInput) hiddenInput.style.display = 'none';
        }
    }

    _bindEvents() {
        var self = this;
        var zone = this.shadowRoot.querySelector('.upload-zone');
        var btn = this.shadowRoot.querySelector('.upload-btn');
        var fileInput = this.shadowRoot.querySelector('input[type="file"]');

        // Click button to open file picker
        btn.addEventListener('click', function() {
            if (!self._uploading) fileInput.click();
        });

        // File selected
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                self._handleFiles(fileInput.files);
            }
        });

        // Drag and drop
        this._dragoverHandler = function(e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.add('dragover');
        };
        zone.addEventListener('dragover', this._dragoverHandler);

        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
        });

        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files.length > 0) {
                self._handleFiles(e.dataTransfer.files);
            }
        });
    }

    _handleFiles(files) {
        var self = this;
        var fileArray = Array.from(files);

        if (!this.isMultiple) {
            // Single file mode: only upload the first file
            fileArray = [fileArray[0]];
        }

        for (var i = 0; i < fileArray.length; i++) {
            var file = fileArray[i];

            // Validate extension
            var ext = (file.name.split('.').pop() || '').toLowerCase();
            if (this.extensions.indexOf(ext) === -1) {
                var errorMsg = this.typeError || this._buildTypeErrorMessage();
                this._showError(errorMsg, file.name);
                continue;
            }

            // Validate size
            if (file.size > this.maxSize) {
                var sizeMB = Math.round(this.maxSize / 1048576);
                this._showError('Bestand te groot (max ' + sizeMB + ' MB)', file.name);
                continue;
            }

            this._uploadFile(file);
        }
    }

    _uploadFile(file) {
        var self = this;
        var zone = this.shadowRoot.querySelector('.upload-zone');
        var progressBar = this.shadowRoot.querySelector('.progress-bar');
        var progressFill = this.shadowRoot.querySelector('.progress-fill');

        this._uploading = true;
        zone.classList.add('uploading');
        zone.classList.remove('error');
        progressBar.style.display = 'block';
        progressFill.style.width = '0%';

        // Clear any previous error message
        var errorEl = this.shadowRoot.querySelector('.error-msg');
        if (errorEl) errorEl.remove();

        // Build FormData
        var formData = new FormData();
        formData.append('qqfile', file); // qqfile key for backward compat with upload_handler.php

        // Build endpoint URL with query parameters
        var url = this.endpoint;
        var params = [];
        if (this.path) params.push('path=' + encodeURIComponent(this.path));
        if (this.pathExtra) params.push('path_extra=' + encodeURIComponent(this.pathExtra));
        if (params.length > 0) {
            url += (url.indexOf('?') === -1 ? '?' : '&') + params.join('&');
        }

        // Use XMLHttpRequest for progress tracking
        var xhr = new XMLHttpRequest();

        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var pct = Math.round((e.loaded / e.total) * 100);
                progressFill.style.width = pct + '%';
            }
        });

        xhr.addEventListener('load', function() {
            self._uploading = false;
            zone.classList.remove('uploading');
            progressBar.style.display = 'none';

            var response;
            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                self._showError('Ongeldige server respons', file.name);
                self._fireError('Ongeldige server respons', file.name);
                return;
            }

            if (response.success) {
                var filename = response.filename || file.name;
                zone.classList.add('success');
                zone.classList.remove('error');

                // Update hidden input field
                if (self.field) {
                    var hiddenInput = document.getElementById(self.field);
                    if (hiddenInput) {
                        var newValue = self.pathExtra + filename;
                        if (self.isMultiple && hiddenInput.value !== '') {
                            hiddenInput.value += ';' + newValue;
                        } else {
                            hiddenInput.value = newValue;
                        }
                    }
                }

                // Add file to list
                self._addFileItem(filename, file.name);

                // Fire event
                self.dispatchEvent(new CustomEvent('upload-complete', {
                    bubbles: true,
                    detail: {
                        filename: filename,
                        originalName: file.name,
                        path: response.path || ''
                    }
                }));
            } else {
                zone.classList.add('error');
                var errMsg = response.error || 'Upload mislukt';
                self._showError(errMsg, file.name);
                self._fireError(errMsg, file.name);
            }
        });

        xhr.addEventListener('error', function() {
            self._uploading = false;
            zone.classList.remove('uploading');
            progressBar.style.display = 'none';
            zone.classList.add('error');
            self._showError('Netwerkfout bij uploaden', file.name);
            self._fireError('Netwerkfout bij uploaden', file.name);
        });

        xhr.open('POST', url, true);
        xhr.send(formData);
    }

    _addFileItem(filename, originalName) {
        var self = this;
        var fileList = this.shadowRoot.querySelector('.file-list');

        // In single-file mode, clear previous entries
        if (!this.isMultiple) {
            fileList.innerHTML = '';
        }

        var li = document.createElement('li');
        li.className = 'file-item';

        var iconSpan = document.createElement('span');
        iconSpan.className = 'file-icon';
        li.appendChild(iconSpan);

        if (this.showLink) {
            var linkBase = this.linkBase;
            if (linkBase && !linkBase.endsWith('/')) linkBase += '/';
            var a = document.createElement('a');
            a.href = linkBase + this.pathExtra + filename;
            a.target = '_blank';
            a.textContent = 'Bekijk bestand';
            li.appendChild(a);
        } else {
            var nameSpan = document.createElement('span');
            nameSpan.textContent = filename;
            li.appendChild(nameSpan);
        }

        // Remove button
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-btn';
        removeBtn.title = 'Verwijderen';
        removeBtn.addEventListener('click', function() {
            li.remove();

            // Update hidden input
            if (self.field) {
                var hiddenInput = document.getElementById(self.field);
                if (hiddenInput) {
                    // Remove this file from the value
                    var fullName = self.pathExtra + filename;
                    var parts = hiddenInput.value.split(';').filter(function(v) {
                        return v !== fullName;
                    });
                    hiddenInput.value = parts.join(';');
                }
            }

            // If no more files, remove success state
            if (fileList.children.length === 0) {
                var zone = self.shadowRoot.querySelector('.upload-zone');
                zone.classList.remove('success');
            }
        });
        li.appendChild(removeBtn);

        fileList.appendChild(li);
        this._uploadedFiles.push(filename);
    }

    _showError(message, fileName) {
        // Remove previous error
        var existing = this.shadowRoot.querySelector('.error-msg');
        if (existing) existing.remove();

        var zone = this.shadowRoot.querySelector('.upload-zone');
        var div = document.createElement('div');
        div.className = 'error-msg';
        div.innerHTML = message;
        zone.appendChild(div);

        // Also try using libAlert if available (matches FineUploader behavior of modal_alert)
        if (typeof window.libAlert === 'function') {
            window.libAlert(message, { type: 'danger' });
        } else if (typeof window.modal_alert === 'function') {
            window.modal_alert(message);
        }
    }

    _fireError(error, fileName) {
        this.dispatchEvent(new CustomEvent('upload-error', {
            bubbles: true,
            detail: { error: error, fileName: fileName }
        }));
    }

    _buildTypeErrorMessage() {
        var extList = this.extensions;
        var labels = {
            'pdf': 'Adobe Acrobat (.pdf)',
            'doc': 'Word (.doc)',
            'docx': 'Word (.docx)',
            'rtf': 'Rich Text (.rtf)',
            'jpg': 'JPG afbeeldingen',
            'jpeg': 'JPEG afbeeldingen',
            'ppt': 'Powerpoint (.ppt)',
            'pptx': 'Powerpoint (.pptx)',
            'm4a': 'Audio (.m4a)',
            'mov': 'Video (.mov)',
            'mp4': 'Video (.mp4)'
        };

        var items = [];
        var seen = {};
        for (var i = 0; i < extList.length; i++) {
            var ext = extList[i];
            // Combine jpg/jpeg
            if (ext === 'jpeg' && seen['jpg']) continue;
            if (ext === 'jpg') seen['jpg'] = true;
            if (ext === 'docx' && seen['doc']) continue;
            if (ext === 'doc') seen['doc'] = true;
            if (ext === 'pptx' && seen['ppt']) continue;
            if (ext === 'ppt') seen['ppt'] = true;

            var label = labels[ext] || '.' + ext;
            items.push('- ' + label);
        }

        return 'De volgende bestandstypen zijn toegestaan:<br>' + items.join('<br>');
    }

    _escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
}

customElements.define('lib-fileuploader', LibFileUploader);

// Export for global access
window.LibFileUploader = LibFileUploader;

} // end guard
