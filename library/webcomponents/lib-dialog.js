/**
 * lib-dialog Web Component
 *
 * A unified modal dialog component supporting both declarative and programmatic usage.
 *
 * Declarative Usage (with slots):
 *   <lib-dialog id="myDialog" heading="Dialog Title">
 *       <p>Dialog content goes here</p>
 *       <div slot="footer">
 *           <button onclick="document.getElementById('myDialog').close()">Cancel</button>
 *           <button class="btn-primary">OK</button>
 *       </div>
 *   </lib-dialog>
 *
 * Programmatic Usage (static methods):
 *   // Alert - single button, returns Promise<void>
 *   await LibDialog.alert('Operation completed', { title: 'Success', type: 'success' });
 *
 *   // Confirm - two buttons, returns Promise<boolean>
 *   const confirmed = await LibDialog.confirm('Are you sure?', { type: 'warning' });
 *
 *   // Prompt - input field, returns Promise<string|null>
 *   const name = await LibDialog.prompt('What is your name?', { title: 'Name', defaultValue: 'John' });
 *   if (name !== null) { console.log('Hello', name); }
 *
 * Global functions (backward compatibility):
 *   libAlert(message, options)   - Same as LibDialog.alert()
 *   libConfirm(message, options) - Same as LibDialog.confirm()
 *   libPrompt(message, options)  - Same as LibDialog.prompt()
 *
 * Attributes:
 *   - heading: Dialog header title (preferred; 'title' also works for backward compat but causes browser tooltip)
 *   - type: "info" | "warning" | "danger" | "success" (affects icon and button colors)
 *   - size: "small" | "medium" | "large" (default: medium)
 *   - closable: If "false", hides close button AND disables Escape/backdrop close (default: true)
 *
 * Events:
 *   - dialog-open: Fired when dialog opens
 *   - dialog-close: Fired when dialog closes (detail: { confirmed: boolean })
 */

// Guard against double declaration when script is loaded multiple times
if (!customElements.get('lib-dialog')) {

/**
 * Get the top window body element for rendering overlays.
 * When inside an iframe (e.g. sidepanel), this returns top.document.body
 * so dialogs cover the entire screen instead of just the iframe.
 */
function _getTopBody() {
    try {
        if (window.self !== window.top && top.document && top.document.body) {
            return top.document.body;
        }
    } catch (e) { /* cross-origin iframe - fall back to current document */ }
    return document.body;
}

class LibDialog extends HTMLElement {
    static get observedAttributes() {
        return ['heading', 'title', 'type', 'size', 'maximizable', 'no-maximize', 'maximized']; // 'heading' preferred, 'title' for backward compat
    }

    constructor() {
        super();
        this.attachShadow({ mode: 'open' });
        this._isOpen = false;
        this._resolvePromise = null;
    }

    connectedCallback() {
        // Adopt shared styles if available
        // Wrapped in try-catch: when dialog is rendered in top window from an iframe,
        // constructed stylesheets can't be shared across documents (NotAllowedError)
        if (typeof LibSharedStyles !== 'undefined' && LibSharedStyles.isSupported()) {
            try {
                LibSharedStyles.adopt(this.shadowRoot, 'base', 'button', 'input', 'animation');
            } catch (e) { /* cross-document stylesheet sharing not allowed - inline styles suffice */ }
        }

        this.render();
        this._bindEvents();
    }

    disconnectedCallback() {
        if (this._keydownHandler && this._keydownDoc) {
            this._keydownDoc.removeEventListener('keydown', this._keydownHandler);
            this._keydownHandler = null;
            this._keydownDoc = null;
        }
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue !== newValue && this.shadowRoot && this.shadowRoot.querySelector('.dialog-content')) {
            if (name === 'heading' || name === 'title') {
                const titleEl = this.shadowRoot.querySelector('.dialog-title-text');
                if (titleEl) {
                    // Prefer heading over title
                    const heading = this.getAttribute('heading') || this.getAttribute('title') || '';
                    titleEl.textContent = heading;
                }
            } else if (name === 'type') {
                this._updateType(newValue);
            }
        }
    }

    get isOpen() {
        return this._isOpen;
    }

    get isClosable() {
        return this.getAttribute('closable') !== 'false';
    }

    get isMaximized() {
        return this.hasAttribute('maximized');
    }

    /**
     * Maximize the dialog to near-fullscreen
     */
    maximize() {
        if (this.hasAttribute('no-maximize')) return;
        this.setAttribute('maximized', '');
        this._resetDragPosition();
        this._updateMaximizeButton(true);
    }

    /**
     * Restore the dialog to its original size
     */
    restore() {
        this.removeAttribute('maximized');
        this._resetDragPosition();
        this._updateMaximizeButton(false);
    }

    _updateMaximizeButton(maximized) {
        const btn = this.shadowRoot.querySelector('.dialog-maximize');
        if (!btn) return;
        const icon = btn.querySelector('.lnr');
        if (icon) {
            icon.classList.toggle('lnr-frame-expand', !maximized);
            icon.classList.toggle('lnr-frame-contract', maximized);
        }
        btn.setAttribute('title', maximized ? 'Herstellen venstergrootte' : 'Maximaliseren venstergrootte');
        btn.setAttribute('aria-label', maximized ? 'Herstellen' : 'Maximaliseren');
    }

    /**
     * Open the dialog
     * @returns {Promise} Resolves when closed (with boolean for confirm dialogs)
     */
    open() {
        this._isOpen = true;
        this.setAttribute('open', '');
        this._resetDragPosition();

        // Use unified z-index manager if available
        this._dialogId = 'lib-dialog-' + Date.now();
        if (typeof lib_zindex_manager !== 'undefined') {
            const zIndex = lib_zindex_manager.push(this._dialogId, 'dialog');
            const backdrop = this.shadowRoot.querySelector('.dialog-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = zIndex;
            }
        }

        this.dispatchEvent(new CustomEvent('dialog-open', { bubbles: true }));

        // Focus first focusable element
        requestAnimationFrame(() => {
            const focusable = this.querySelector('input, button, select, textarea, [tabindex]:not([tabindex="-1"])') ||
                              this.shadowRoot.querySelector('.dialog-btn-confirm, .dialog-btn-cancel');
            if (focusable) focusable.focus();
        });

        return new Promise(resolve => {
            this._resolvePromise = resolve;
        });
    }

    /**
     * Close the dialog
     * @param {boolean} confirmed - Whether the dialog was confirmed (for confirm dialogs)
     */
    close(confirmed = false) {
        if (!this._isOpen) return;

        this._isOpen = false;

        // Reset maximized state so dialog opens normal next time
        if (this.hasAttribute('maximized')) {
            this.removeAttribute('maximized');
            this._updateMaximizeButton(false);
        }

        // Remove from z-index manager
        if (this._dialogId && typeof lib_zindex_manager !== 'undefined') {
            lib_zindex_manager.pop(this._dialogId);
        }

        const backdrop = this.shadowRoot.querySelector('.dialog-backdrop');
        if (backdrop) {
            backdrop.classList.add('closing');
            setTimeout(() => {
                this.removeAttribute('open');
                backdrop.classList.remove('closing');
            }, 200);
        } else {
            this.removeAttribute('open');
        }

        this.dispatchEvent(new CustomEvent('dialog-close', { bubbles: true, detail: { confirmed } }));

        if (this._resolvePromise) {
            this._resolvePromise(confirmed);
            this._resolvePromise = null;
        }
    }

    _updateType(type) {
        const content = this.shadowRoot.querySelector('.dialog-content');
        if (content) {
            content.className = 'dialog-content' + (type ? ' dialog-' + type : '');
        }
        const icon = this.shadowRoot.querySelector('.dialog-icon');
        if (icon) {
            icon.textContent = this._getIcon(type);
        }
    }

    _getIcon(type) {
        switch (type) {
            case 'danger': return '\ue95a';    // lnr-cross-circle
            case 'warning': return '\ue955';   // lnr-warning (triangle !)
            case 'success': return '\ue959';   // lnr-checkmark-circle
            case 'info':
            default: return 'i';               // serif italic i in circle
        }
    }

    _bindEvents() {
        const backdrop = this.shadowRoot.querySelector('.dialog-backdrop');

        // Close on backdrop click (only if closable)
        backdrop.addEventListener('click', (e) => {
            if (e.target.classList.contains('dialog-backdrop') && this.isClosable) {
                this.close(false);
            }
        });

        // Close button
        const closeBtn = this.shadowRoot.querySelector('.dialog-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.close(false));
        }

        // Footer buttons (for programmatic dialogs)
        const confirmBtn = this.shadowRoot.querySelector('.dialog-btn-confirm');
        const cancelBtn = this.shadowRoot.querySelector('.dialog-btn-cancel');
        if (confirmBtn) confirmBtn.addEventListener('click', () => this.close(true));
        if (cancelBtn) cancelBtn.addEventListener('click', () => this.close(false));

        // Drag by header
        this._bindDrag();

        // Maximize button
        this._bindMaximize();

        // Escape key closes with false (cancel), Enter confirms
        // Use ownerDocument so the listener works when dialog is rendered in top window from an iframe
        this._keydownDoc = this.ownerDocument || document;
        this._keydownHandler = (e) => {
            if (!this._isOpen) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                // Always allow Escape to cancel (close with false)
                this.close(false);
            } else if (e.key === 'Enter' && !e.target.matches('textarea, button')) {
                e.preventDefault();
                this.close(true);
            }
        };
        this._keydownDoc.addEventListener('keydown', this._keydownHandler);
    }

    _bindMaximize() {
        const maximizeBtn = this.shadowRoot.querySelector('.dialog-maximize');
        if (!maximizeBtn) return;
        maximizeBtn.addEventListener('click', () => {
            if (this.isMaximized) {
                this.restore();
            } else {
                this.maximize();
            }
        });
    }

    _resetDragPosition() {
        var content = this.shadowRoot.querySelector('.dialog-content');
        if (content) {
            content.style.left = '';
            content.style.top = '';
            content.style.animation = '';
        }
    }

    _bindDrag() {
        const header = this.shadowRoot.querySelector('.dialog-header');
        const content = this.shadowRoot.querySelector('.dialog-content');
        if (!header || !content) return;

        let isDragging = false;
        let startX = 0, startY = 0, origLeft = 0, origTop = 0;

        header.addEventListener('mousedown', (e) => {
            // Don't drag when clicking buttons/links inside header
            if (e.target.closest('.dialog-close, .dialog-maximize, a, button')) return;
            if (this.isMaximized) return;

            isDragging = true;
            startX = e.clientX;
            startY = e.clientY;
            origLeft = parseInt(content.style.left || '0', 10);
            origTop = parseInt(content.style.top || '0', 10);

            // Disable animation during drag
            content.style.animation = 'none';

            e.preventDefault();
        });

        // Use ownerDocument for mouse events (works across iframes)
        const doc = this.ownerDocument || document;

        doc.addEventListener('mousemove', (e) => {
            if (!isDragging) return;
            content.style.left = (origLeft + e.clientX - startX) + 'px';
            content.style.top = (origTop + e.clientY - startY) + 'px';
        });

        doc.addEventListener('mouseup', () => {
            isDragging = false;
        });
    }

    render() {
        // Support both 'heading' (preferred) and 'title' (backward compat) attributes
        const title = this.getAttribute('heading') || this.getAttribute('title') || '';
        const type = this.getAttribute('type') || '';
        const closable = this.isClosable;
        const maximizable = !this.hasAttribute('no-maximize');
        const icon = this._getIcon(type);

        // Build HTML without title interpolation to avoid timing issues
        this.shadowRoot.innerHTML = `
            <style>
                :host {
                    display: none;
                }

                :host([open]) {
                    display: block;
                }

                .dialog-backdrop {
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.5);
                    z-index: 2000;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    animation: dialogFadeIn 0.15s ease;
                }

                .dialog-backdrop.closing {
                    animation: dialogFadeOut 0.2s ease forwards;
                }

                .dialog-backdrop.closing .dialog-content {
                    animation: dialogSlideOut 0.2s ease forwards;
                }

                @keyframes dialogFadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes dialogFadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }

                @keyframes dialogSlideIn {
                    from { transform: translateY(-20px); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }

                @keyframes dialogSlideOut {
                    from { transform: translateY(0); opacity: 1; }
                    to { transform: translateY(-20px); opacity: 0; }
                }

                .dialog-content {
                    position: relative;
                    background: var(--bg-surface, #fff);
                    border-radius: 8px;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
                    width: 100%;
                    max-height: 90vh;
                    display: flex;
                    flex-direction: column;
                    animation: dialogSlideIn 0.2s ease;
                    font-family: var(--font-family);
                }

                /* Size variants - can be overridden with --lib-dialog-max-width */
                :host([size="small"]) .dialog-content { max-width: var(--lib-dialog-max-width, 320px); }
                :host([size="medium"]) .dialog-content,
                .dialog-content { max-width: var(--lib-dialog-max-width, 420px); }
                :host([size="large"]) .dialog-content { max-width: var(--lib-dialog-max-width, 640px); }
                :host([size="auto"]) .dialog-content { max-width: 90vw; width: fit-content; min-width: 280px; }
                :host([size="fullscreen"]) .dialog-content { max-width: 95vw; min-width: 800px; height: 95vh; }
                :host([size="fullscreen"]) .dialog-body { padding: 0; flex: 1; overflow: hidden; border-radius: 0 0 8px 8px; }
                :host([size="fullscreen"]) .dialog-body ::slotted(iframe) { border-radius: 0 0 8px 8px; }
                :host([size="fullscreen"]) .dialog-footer { display: none; }

                .dialog-header {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    padding: 12px 16px;
                    background: var(--popup-caption-bg, #eef6ff);
                    border-radius: 8px 8px 0 0;
                    cursor: move;
                    user-select: none;
                }

                .dialog-header .dialog-close,
                .dialog-header .dialog-maximize {
                    cursor: pointer;
                }

                .dialog-icon {
                    font-family: 'Linearicons';
                    font-size: var(--font-size-2xl);
                    font-weight: normal;
                    font-style: normal;
                    line-height: 1;
                    flex-shrink: 0;
                }

                .dialog-info .dialog-icon {
                    color: var(--color-info, #077ab2);
                    border: 1px solid #077ab2;
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    text-align: center;
                    font-style: italic;
                    line-height: 18px;
                    font-family: serif;
                    font-size: var(--font-size-md);
                }
                .dialog-warning .dialog-icon { color: #f0ad40; }
                .dialog-danger .dialog-icon { color: var(--color-error, #e01f3d); }
                .dialog-success .dialog-icon { color: var(--color-success, #5cb85c); }

                .dialog-title {
                    flex: 1;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    min-width: 0;
                }

                .dialog-title-text {
                    margin: 0;
                    font-size: 15px;
                    font-weight: 600;
                    font-family: "Trebuchet MS", Verdana, Arial, sans-serif;
                    color: var(--popup-caption-text, #204496);
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }

                /* Close button - styled like lib_window_close */
                .dialog-close {
                    position: relative;
                    width: 24px;
                    height: 24px;
                    background: transparent;
                    cursor: pointer;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    text-decoration: none;
                    transition: background 0.15s ease;
                }

                .dialog-close-x {
                    position: relative;
                    width: 14px;
                    height: 14px;
                }

                .dialog-close-x::before,
                .dialog-close-x::after {
                    content: '';
                    position: absolute;
                    height: 2px;
                    width: 100%;
                    top: 50%;
                    left: 0;
                    margin-top: -1px;
                    background: var(--popup-close-bg, #bbbbbb);
                    transition: background 0.15s ease;
                }

                .dialog-close-x::before {
                    transform: rotate(45deg);
                }

                .dialog-close-x::after {
                    transform: rotate(-45deg);
                }

                .dialog-close:hover {
                    background: var(--popup-close-hover, #E01F3D);
                }

                .dialog-close:hover .dialog-close-x::before,
                .dialog-close:hover .dialog-close-x::after {
                    background: var(--text-inverse, white);
                }

                .dialog-body {
                    padding: 20px;
                    overflow-y: auto;
                    flex: 1;
                }

                .dialog-body p {
                    margin: 0;
                    font-size: var(--font-size-md);
                    line-height: 1.5;
                    color: var(--text-secondary, #666);
                }

                .dialog-footer {
                    padding: 16px 20px;
                    border-top: 0px;
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                }

                .dialog-footer:empty {
                    display: none;
                }

                /* Cancel buttons align left (push to left using margin-right: auto) */
                .dialog-footer ::slotted(.btn-cancel),
                .dialog-footer ::slotted([class*="cancel"]),
                .dialog-footer .dialog-btn-cancel {
                    margin-right: auto;
                }

                /* Style slotted buttons */
                .dialog-footer ::slotted(button) {
                    min-width: 80px;
                    height: 22px;
                    border-radius: 4px;
                    font-family: var(--font-family);
                    font-size: var(--font-size-sm);
                    font-weight: 500;
                    cursor: pointer;
                    border: 1px solid var(--border-color, #ccc);
                    background: var(--bg-surface, #f5f5f5);
                    color: var(--text-primary, #333);
                    transition: background 0.15s ease, border-color 0.15s ease;
                    box-sizing: border-box;
                }

                .dialog-footer ::slotted(button:hover) {
                    background: var(--bg-hover, #e9e9e9);
                }

                .dialog-footer ::slotted(.btn-primary) {
                    background: var(--bg-button-primary, #204496);
                    border-color: var(--bg-button-primary, #204496);
                    color: #fff;
                }

                .dialog-footer ::slotted(.btn-primary:hover) {
                    background: var(--bg-button-primary-hover, #1a3a7d);
                    border-color: var(--bg-button-primary-hover, #1a3a7d);
                }

                .dialog-footer ::slotted(.btn-primary:active) {
                    background: var(--bg-button-primary-active, #152c5c);
                    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35);
                }

                .dialog-footer ::slotted(.btn-secondary) {
                    background: var(--bg-button-secondary, #204496);
                    border-color: var(--bg-button-secondary, #204496);
                    color: #fff;
                }

                .dialog-footer ::slotted(.btn-secondary:hover) {
                    background: var(--bg-button-secondary-hover, #1a3a7d);
                    border-color: var(--bg-button-secondary-hover, #1a3a7d);
                }

                .dialog-footer ::slotted(.btn-secondary:active) {
                    background: var(--bg-button-secondary-active, #152c5c);
                    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35);
                }

                .dialog-footer ::slotted(.btn-cancel) {
                    background: var(--bg-button-cancel, #c4c4c4);
                    border-color: var(--bg-button-cancel, #c4c4c4);
                    color: var(--text-primary, #333);
                }

                .dialog-footer ::slotted(.btn-cancel:hover) {
                    background: var(--bg-button-cancel-hover, #e8e8e8);
                }

                .dialog-footer ::slotted(.btn-cancel:active) {
                    background: var(--bg-button-cancel-active, #aaa);
                    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.25);
                }

                /* Built-in buttons for programmatic dialogs */
                .dialog-btn {
                    min-width: 80px;
                    height: 22px;
                    padding: 0 16px;
                    border-radius: 4px;
                    font-family: var(--font-family);
                    font-size: var(--font-size-sm);
                    font-weight: 500;
                    cursor: pointer;
                    border: 1px solid transparent;
                    transition: background 0.15s ease, border-color 0.15s ease;
                    box-sizing: border-box;
                }

                .dialog-btn-cancel {
                    background: var(--color-error, #dc3545);
                    border-color: var(--color-error, #dc3545);
                    color: #fff;
                }

                .dialog-btn-cancel:hover {
                    background: #c82333;
                    border-color: #bd2130;
                }

                .dialog-btn-confirm {
                    background: var(--color-success, #28a745);
                    border-color: var(--color-success, #28a745);
                    color: #fff;
                }

                .dialog-btn-confirm:hover {
                    background: #218838;
                    border-color: #1e7e34;
                }

                .dialog-danger .dialog-btn-confirm {
                    background: var(--color-error, #dc3545);
                    border-color: var(--color-error, #dc3545);
                }

                .dialog-danger .dialog-btn-confirm:hover {
                    background: #c82333;
                    border-color: #bd2130;
                }

                .dialog-btn:focus {
                    outline: none;
                    box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.3);
                }

                .dialog-btn-cancel:focus {
                    box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.3);
                }

                /* Slotted content styles */
                ::slotted(input),
                ::slotted(textarea),
                ::slotted(select) {
                    width: 100%;
                    padding: 10px 12px;
                    margin-bottom: 12px;
                    border: 1px solid var(--border-color, #ddd);
                    border-radius: 4px;
                    font-family: var(--font-family);
                    font-size: var(--font-size-md);
                    font-weight: normal;
                    background: var(--input-bg, #fff);
                    color: var(--text-primary, #333);
                    box-sizing: border-box;
                }

                ::slotted(input:focus),
                ::slotted(textarea:focus),
                ::slotted(select:focus) {
                    outline: none;
                    border-color: var(--color-primary, #204496);
                }

                ::slotted(button) {
                    min-width: 80px;
                    height: 22px;
                    padding: 0 16px;
                    border-radius: 4px;
                    font-family: var(--font-family);
                    font-size: var(--font-size-sm);
                    font-weight: 500;
                    cursor: pointer;
                    box-sizing: border-box;
                }

                ::slotted(.btn-primary) {
                    background: var(--bg-button-primary, #204496);
                    color: #fff;
                    border: 1px solid var(--bg-button-primary, #204496);
                }

                ::slotted(.btn-secondary) {
                    background: var(--bg-button-secondary, #204496);
                    color: #fff;
                    border: 1px solid var(--bg-button-secondary, #204496);
                }

                ::slotted(.btn-cancel) {
                    background: var(--bg-button-cancel, #c4c4c4);
                    border: 1px solid var(--bg-button-cancel, #c4c4c4);
                    color: var(--text-primary, #333);
                }

                ::slotted(.dialog-message) {
                    padding: 10px 12px;
                    border-radius: 4px;
                    margin-bottom: 12px;
                    font-size: var(--font-size);
                }

                ::slotted(.dialog-message.error) {
                    background: var(--color-error-bg, #f8d7da);
                    color: var(--color-error-text, #721c24);
                }

                ::slotted(.dialog-message.success) {
                    background: var(--color-success-bg, #d4edda);
                    color: var(--color-success-text, #155724);
                }

                /* Maximize button */
                .dialog-maximize {
                    width: 24px;
                    height: 24px;
                    background: transparent;
                    cursor: pointer;
                    border-radius: 4px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    flex-shrink: 0;
                    text-decoration: none;
                    color: var(--text-muted, #999);
                    transition: color 0.15s ease, background 0.15s ease;
                }

                .dialog-maximize:hover {
                    background: var(--border-color, #ddd);
                    color: var(--text-primary, #333);
                }

                .dialog-maximize .lnr {
                    font-family: 'Linearicons';
                    font-style: normal;
                    font-weight: normal;
                    font-size: 20px;
                    line-height: 1;
                    width: auto;
                    height: auto;
                    display: inline-block;
                }

                .dialog-maximize .lnr-frame-expand::before { content: "\\e952"; }
                .dialog-maximize .lnr-frame-contract::before { content: "\\e953"; }

                /* Maximized state */
                :host([maximized]) .dialog-content {
                    max-width: 95vw !important;
                    width: 95vw;
                    max-height: 95vh;
                    height: 95vh;
                    transition: width 0.2s ease, height 0.2s ease, max-width 0.2s ease, max-height 0.2s ease;
                }

                :host([maximized]) .dialog-body {
                    flex: 1;
                    overflow-y: auto;
                }

                :host([maximized]) .dialog-header {
                    border-radius: 8px 8px 0 0;
                    cursor: default;
                }

                /* Dark mode */
                @media (prefers-color-scheme: dark) {
                    .dialog-content {
                        background: var(--bg-surface, #2a2a2a);
                    }

                    .dialog-header {
                        background: var(--popup-caption-bg, #333);
                    }

                    .dialog-footer {
                        border-color: var(--border-color, #444);
                    }

                    .dialog-btn-cancel {
                        background: var(--bg-surface-alt, #333);
                        border-color: var(--border-color, #555);
                        color: var(--text-secondary, #aaa);
                    }
                }
            </style>
            <div class="dialog-backdrop">
                <div class="dialog-content${type ? ' dialog-' + type : ''}">
                    <div class="dialog-header">
                        <div class="dialog-title">
                            ${type ? '<span class="dialog-icon">' + icon + '</span>' : ''}
                            <h3 class="dialog-title-text"></h3>
                        </div>
                        ${maximizable ? '<a href="javascript:void(0)" class="dialog-maximize" aria-label="Maximaliseren" title="Maximaliseren venstergrootte"><span class="lnr lnr-frame-expand"></span></a>' : ''}
                        ${closable ? '<a href="javascript:void(0)" class="dialog-close" aria-label="Sluiten" title="Sluiten"><span class="dialog-close-x"></span></a>' : ''}
                    </div>
                    <div class="dialog-body" part="body">
                        <slot></slot>
                    </div>
                    <div class="dialog-footer" part="footer">
                        <slot name="footer"></slot>
                    </div>
                </div>
            </div>
        `;

        // Set title via DOM manipulation to ensure it's set correctly
        // (template literal interpolation can have timing issues with custom elements)
        const titleEl = this.shadowRoot.querySelector('.dialog-title-text');
        if (titleEl) {
            titleEl.textContent = title;
        }
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // =========================================================================
    // Static Methods for Programmatic Usage
    // =========================================================================

    /**
     * Show an alert dialog (single button, closable)
     * @param {string} message - The message to display
     * @param {Object} options - Configuration options
     * @param {string} options.title - Dialog title (default: 'Melding')
     * @param {string} options.buttonText - Button text (default: 'OK')
     * @param {string} options.type - 'info' | 'warning' | 'danger' | 'success' (default: 'info')
     * @param {string} options.details - Technical details (shown in collapsible section, admin/dev only)
     * @returns {Promise<void>}
     */
    static alert(message, options = {}) {
        const title = options.title || 'Melding';
        const buttonText = options.buttonText || 'OK';
        const type = options.type || 'info';
        const details = options.details || '';
        // Auto-detect HTML if not explicitly specified
        const html = options.html !== undefined ? options.html : LibDialog._containsHtml(message);

        // Remove any existing programmatic dialog
        const targetBody = _getTopBody();
        const existing = targetBody.querySelector('lib-dialog[data-programmatic]');
        if (existing) existing.remove();

        // Create element in the target document (important for cross-document/iframe scenarios)
        const dialog = (targetBody.ownerDocument || document).createElement('lib-dialog');
        dialog.setAttribute('data-programmatic', '');
        dialog.setAttribute('no-maximize', '');
        dialog.setAttribute('heading', title);
        dialog.setAttribute('type', type);
        dialog.setAttribute('size', details ? 'medium' : 'small');

        // Build dialog content - escape HTML unless html option is true
        let content = html ? message : `<p>${LibDialog._escapeHtml(message)}</p>`;

        // Add details section if provided (collapsible)
        if (details) {
            content += `
                <details class="lib-dialog-details">
                    <summary>Details</summary>
                    <pre class="lib-dialog-details-content">${LibDialog._escapeHtml(details)}</pre>
                </details>
                <style>
                    .lib-dialog-details { margin-top: 12px; font-size: var(--font-size-sm); }
                    .lib-dialog-details summary { cursor: pointer; color: var(--text-secondary, #666); padding: 4px 0; }
                    .lib-dialog-details summary:hover { color: var(--text-primary, #333); }
                    .lib-dialog-details-content {
                        margin-top: 8px;
                        padding: 10px;
                        background: var(--bg-code, #f5f5f5);
                        border: 1px solid var(--border-color, #ddd);
                        border-radius: 4px;
                        overflow-x: auto;
                        font-family: monospace;
                        font-size: var(--font-size-xs);
                        line-height: 1.4;
                        max-height: 200px;
                        overflow-y: auto;
                        white-space: pre-wrap;
                        word-break: break-word;
                    }
                </style>
            `;
        }

        content += `
            <div slot="footer">
                <button class="btn-primary" onclick="this.closest('lib-dialog').close(true)">${LibDialog._escapeHtml(buttonText)}</button>
            </div>
        `;

        dialog.innerHTML = content;

        targetBody.appendChild(dialog);

        return dialog.open().then(() => {
            dialog.remove();
        });
    }

    /**
     * Show a confirm dialog (two buttons, NOT closable - must choose)
     * @param {string} message - The message to display
     * @param {Object} options - Configuration options
     * @param {string} options.title - Dialog title (default: 'Bevestigen')
     * @param {string} options.confirmText - Confirm button text (default: 'Ja')
     * @param {string} options.cancelText - Cancel button text (default: 'Nee')
     * @param {string} options.type - 'info' | 'warning' | 'danger' (default: 'warning')
     * @param {boolean} options.html - If true, message is treated as HTML (default: false)
     * @returns {Promise<boolean>}
     */
    static confirm(message, options = {}) {
        const title = options.title || 'Bevestigen';
        const confirmText = options.confirmText || 'Ja';
        const cancelText = options.cancelText || 'Nee';
        const type = options.type || 'warning';
        // Auto-detect HTML if not explicitly specified
        const allowHtml = options.html !== undefined ? options.html : LibDialog._containsHtml(message);

        // Remove any existing programmatic dialog
        const targetBody = _getTopBody();
        const existing = targetBody.querySelector('lib-dialog[data-programmatic]');
        if (existing) existing.remove();

        // Create element in the target document (important for cross-document/iframe scenarios)
        const dialog = (targetBody.ownerDocument || document).createElement('lib-dialog');
        dialog.setAttribute('data-programmatic', '');
        dialog.setAttribute('no-maximize', '');
        dialog.setAttribute('heading', title);
        dialog.setAttribute('type', type);
        dialog.setAttribute('size', 'small');
        // Confirm is NOT closable - user MUST click a button
        dialog.setAttribute('closable', 'false');

        // Handle message - allow HTML if explicitly requested
        const messageHtml = allowHtml ? message : LibDialog._escapeHtml(message);
        dialog.innerHTML = `
            <div>${messageHtml}</div>
            <div slot="footer">
                <button class="btn-cancel" onclick="this.closest('lib-dialog').close(false)">${LibDialog._escapeHtml(cancelText)}</button>
                <button class="btn-primary" onclick="this.closest('lib-dialog').close(true)">${LibDialog._escapeHtml(confirmText)}</button>
            </div>
        `;

        targetBody.appendChild(dialog);

        return dialog.open().then(confirmed => {
            dialog.remove();
            return confirmed;
        });
    }

    /**
     * Show a prompt dialog with an input field
     * @param {string} message - The message/question to display
     * @param {Object} options - Configuration options
     * @param {string} [options.title='Invoer'] - Dialog title
     * @param {string} [options.defaultValue=''] - Default input value
     * @param {string} [options.placeholder=''] - Input placeholder text
     * @param {string} [options.confirmText='OK'] - Confirm button text
     * @param {string} [options.cancelText='Annuleren'] - Cancel button text
     * @param {string} [options.type='info'] - Dialog type (info, warning, danger, success)
     * @param {string} [options.inputType='text'] - Input type (text, number, email, etc.)
     * @param {boolean} [options.required=false] - Whether input is required
     * @param {boolean} [options.multiline=false] - Use textarea instead of input
     * @returns {Promise<string|null>} The input value, or null if cancelled
     */
    static prompt(message, options = {}) {
        const title = options.title || 'Invoer';
        const defaultValue = options.defaultValue || '';
        const placeholder = options.placeholder || '';
        const confirmText = options.confirmText || 'OK';
        const cancelText = options.cancelText || 'Annuleren';
        const type = options.type || 'info';
        const inputType = options.inputType || 'text';
        const required = options.required === true;
        const multiline = options.multiline === true;

        // Remove any existing programmatic dialog
        const targetBody = _getTopBody();
        const existing = targetBody.querySelector('lib-dialog[data-programmatic]');
        if (existing) existing.remove();

        // Create element in the target document (important for cross-document/iframe scenarios)
        const dialog = (targetBody.ownerDocument || document).createElement('lib-dialog');
        dialog.setAttribute('data-programmatic', '');
        dialog.setAttribute('no-maximize', '');
        dialog.setAttribute('heading', title);
        dialog.setAttribute('type', type);
        dialog.setAttribute('size', 'small');
        // Prompt is closable - close/Escape treated as cancel (returns null)

        const inputId = 'lib-dialog-prompt-input-' + Date.now();
        const reqAttrs = required ? 'required data-required="true"' : '';
        const inputHtml = multiline
            ? `<textarea id="${inputId}" class="lib-dialog-prompt-input" placeholder="${LibDialog._escapeHtml(placeholder)}" ${reqAttrs}>${LibDialog._escapeHtml(defaultValue)}</textarea>`
            : `<input type="${inputType}" id="${inputId}" class="lib-dialog-prompt-input" value="${LibDialog._escapeHtml(defaultValue)}" placeholder="${LibDialog._escapeHtml(placeholder)}" ${reqAttrs}>`;

        dialog.innerHTML = `
            <style>
                .lib-dialog-prompt-input {
                    width: 100%;
                    padding: 8px 10px;
                    font-family: var(--font-family);
                    font-size: var(--font-size-md);
                    font-weight: normal;
                    border: 1px solid var(--border-color, #ccc);
                    border-radius: 4px;
                    margin-top: 8px;
                    box-sizing: border-box;
                }
                .lib-dialog-prompt-input:focus {
                    outline: none;
                    border-color: var(--primary-color, #0066cc);
                    box-shadow: 0 0 0 2px rgba(0, 102, 204, 0.2);
                }
                .lib-dialog-prompt-input.invalid {
                    border-color: var(--color-error, #dc3545) !important;
                    box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.25);
                }
                .validation-error {
                    color: var(--color-error, #dc3545);
                    font-size: var(--font-size-xs);
                    margin-top: 2px;
                    display: none;
                }
                .validation-error.visible {
                    display: block;
                }
                textarea.lib-dialog-prompt-input {
                    min-height: 80px;
                    resize: vertical;
                }
            </style>
            <div>
                <p style="margin: 0 0 4px 0;">${LibDialog._escapeHtml(message)}</p>
                ${inputHtml}
                ${required ? '<div class="validation-error">Dit veld is verplicht</div>' : ''}
            </div>
            <div slot="footer">
                <button class="btn-cancel" data-action="cancel">${LibDialog._escapeHtml(cancelText)}</button>
                <button class="btn-primary" data-action="confirm">${LibDialog._escapeHtml(confirmText)}</button>
            </div>
        `;

        targetBody.appendChild(dialog);

        // Store input reference
        const input = dialog.querySelector('#' + inputId);
        const validationMsg = dialog.querySelector('.validation-error');

        // Handle button clicks
        const cancelBtn = dialog.querySelector('[data-action="cancel"]');
        const confirmBtn = dialog.querySelector('[data-action="confirm"]');

        cancelBtn.addEventListener('click', () => {
            dialog._promptValue = null;
            dialog.close(false);
        });

        confirmBtn.addEventListener('click', () => {
            if (required && !input.value.trim()) {
                input.classList.add('invalid');
                if (validationMsg) validationMsg.classList.add('visible');
                input.focus();
                return;
            }
            dialog._promptValue = input.value;
            dialog.close(true);
        });

        // Clear validation on input
        if (required) {
            input.addEventListener('input', () => {
                if (input.value.trim()) {
                    input.classList.remove('invalid');
                    if (validationMsg) validationMsg.classList.remove('visible');
                }
            });
        }

        // Handle Enter key in input
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !multiline) {
                e.preventDefault();
                confirmBtn.click();
            }
        });

        // Focus input when dialog opens
        return dialog.open().then(confirmed => {
            const value = confirmed ? dialog._promptValue : null;
            dialog.remove();
            return value;
        }).finally(() => {
            // Focus input after open animation
            setTimeout(() => {
                if (input && (document.body.contains(input) || targetBody.contains(input))) {
                    input.focus();
                    input.select();
                }
            }, 50);
        });
    }

    static _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Detect if a string contains HTML tags
     * @param {string} str - The string to check
     * @returns {boolean} True if string appears to contain HTML
     */
    static _containsHtml(str) {
        if (!str || typeof str !== 'string') return false;
        // Match common HTML tags (opening tags with optional attributes)
        return /<[a-z][\s\S]*?>/i.test(str);
    }
}

// Register the component
customElements.define('lib-dialog', LibDialog);

// Expose LibDialog globally for programmatic usage
window.LibDialog = LibDialog;

// =========================================================================
// Global Functions for Backward Compatibility
// =========================================================================

/**
 * Display a styled confirm dialog
 * @param {string} message - The message to display
 * @param {Object} options - Configuration options
 * @returns {Promise<boolean>}
 */
function libConfirm(message, options) {
    return LibDialog.confirm(message, options);
}

/**
 * Display a styled alert dialog
 * @param {string} message - The message to display
 * @param {Object} options - Configuration options
 * @returns {Promise<void>}
 */
function libAlert(message, options) {
    return LibDialog.alert(message, options);
}

/**
 * Display a styled prompt dialog with an input field
 * @param {string} message - The message/question to display
 * @param {Object} options - Configuration options
 * @param {string} [options.title='Invoer'] - Dialog title
 * @param {string} [options.defaultValue=''] - Default input value
 * @param {string} [options.placeholder=''] - Input placeholder text
 * @param {string} [options.confirmText='OK'] - Confirm button text
 * @param {string} [options.cancelText='Annuleren'] - Cancel button text
 * @param {string} [options.type='info'] - Dialog type (info, warning, danger, success)
 * @param {string} [options.inputType='text'] - Input type (text, number, email, etc.)
 * @param {boolean} [options.required=false] - Whether input is required
 * @param {boolean} [options.multiline=false] - Use textarea instead of input
 * @returns {Promise<string|null>} The input value, or null if cancelled
 */
function libPrompt(message, options) {
    return LibDialog.prompt(message, options);
}

} // End of LibDialog guard
