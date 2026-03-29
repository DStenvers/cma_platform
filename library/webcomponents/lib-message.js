/**
 * lib-message Web Component (No Shadow DOM)
 *
 * A unified message/alert component for displaying inline messages.
 * Uses shared CSS classes for styling (see lib-components.css).
 *
 * Usage:
 *   <lib-message type="success" closable>Operation completed successfully</lib-message>
 *   <lib-message type="error">Something went wrong</lib-message>
 *   <lib-message type="warning" closable auto-dismiss="5000">This will auto-close</lib-message>
 *   <lib-message type="info" icon="false">No icon message</lib-message>
 *
 * Attributes:
 *   - type: info (default), success, warning, error
 *   - closable: Show close button (no value needed)
 *   - auto-dismiss: Auto-close after X milliseconds (e.g., "5000")
 *   - icon: Show icon (default: true, set to "false" to hide)
 *   - compact: Smaller padding for inline use
 *
 * Methods:
 *   - close(): Programmatically close the message
 *   - show(): Show a hidden message
 *
 * Events:
 *   - lib-message-close: Fired when message is closed
 */
// Guard against double registration
if (!customElements.get('lib-message')) {

class LibMessage extends HTMLElement {
    static get observedAttributes() {
        return ['type', 'closable', 'auto-dismiss', 'icon', 'compact', 'details'];
    }

    constructor() {
        super();
        this._autoDismissTimer = null;
        this._rendered = false;
        this._originalContent = '';
    }

    connectedCallback() {
        if (!this._rendered) {
            // Static mode: server already rendered the inner HTML, skip rendering
            if (this.hasAttribute('static')) {
                this._rendered = true;
                this._setupAutoDismiss();
                return;
            }
            // Defer rendering until DOM is ready.
            // When connectedCallback runs during HTML parsing, innerHTML may be empty
            // because the content inside the element hasn't been parsed yet.
            if (document.readyState === 'loading') {
                // Document still loading - wait for DOMContentLoaded
                document.addEventListener('DOMContentLoaded', () => this._initRender(), { once: true });
            } else {
                // Document already loaded (e.g., dynamic insertion) - defer to next frame
                requestAnimationFrame(() => this._initRender());
            }
        } else {
            this._setupAutoDismiss();
        }
    }

    _initRender() {
        if (this._rendered) return;
        // Store original content before rendering
        this._originalContent = this.innerHTML;
        this.render();
        this._rendered = true;
        this._setupAutoDismiss();
    }

    disconnectedCallback() {
        this._clearAutoDismiss();
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (oldValue === newValue) return;
        if (this._rendered) {
            // Re-render only for significant changes
            if (name === 'type' || name === 'icon' || name === 'compact' || name === 'closable') {
                this.render();
            }
            if (name === 'auto-dismiss') {
                this._setupAutoDismiss();
            }
        }
    }

    _getType() {
        return this.getAttribute('type') || 'info';
    }

    _isClosable() {
        return this.hasAttribute('closable');
    }

    _showIcon() {
        return this.getAttribute('icon') !== 'false';
    }

    _isCompact() {
        return this.hasAttribute('compact');
    }

    _canCopy() {
        // Explicitly disabled via attribute
        if (this.getAttribute('copy') === 'false') return false;
        // Always show copy button for error messages
        if (this._getType() === 'error') {
            return true;
        }
        // For other types, only show to admins or developers
        return (typeof window.CMA_IS_ADMIN !== 'undefined' && window.CMA_IS_ADMIN === true) ||
               (typeof window.CMA_IS_DEVELOPER !== 'undefined' && window.CMA_IS_DEVELOPER === true);
    }

    _getIcon(type) {
        const icons = {
            success: 'lnr-checkmark-circle',
            error: 'lnr-cross-circle',
            warning: 'lnr-warning',
            info: 'lnr-question-circle'
        };
        const iconClass = icons[type] || icons.info;
        return `<span class="lnr ${iconClass}"></span>`;
    }

    _getDetails() {
        return this.getAttribute('details') || '';
    }

    render() {
        const type = this._getType();
        const closable = this._isClosable();
        const showIcon = this._showIcon();
        const compact = this._isCompact();
        const details = this._getDetails();
        const canCopy = this._canCopy();

        // Build class list
        const classes = ['lib-message', `lib-message--${type}`];
        if (compact) classes.push('lib-message--compact');
        if (details) classes.push('lib-message--has-details');

        // Build details section if provided
        let detailsHtml = '';
        if (details) {
            detailsHtml = `
                <details class="lib-message__details">
                    <summary>Details</summary>
                    <pre class="lib-message__details-content">${this._escapeHtml(details)}</pre>
                </details>
            `;
        }

        // Copy button (clipboard icon)
        const copyButtonHtml = canCopy ? `
            <button class="lib-message__copy" type="button" aria-label="Kopiëren" title="Kopieer naar klembord">
                <span class="lnr lnr-copy"></span>
            </button>
        ` : '';

        this.innerHTML = `
            <div class="${classes.join(' ')}" role="alert">
                ${showIcon ? `<span class="lib-message__icon">${this._getIcon(type)}</span>` : ''}
                <div class="lib-message__content">
                    ${this._originalContent}
                    ${detailsHtml}
                </div>
                <div class="lib-message__actions">
                    ${copyButtonHtml}
                    ${closable ? `
                        <button class="lib-message__close" type="button" aria-label="Sluiten">
                            <span class="lnr lnr-cross"></span>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;

        // Bind copy button
        if (canCopy) {
            const copyBtn = this.querySelector('.lib-message__copy');
            if (copyBtn) {
                copyBtn.addEventListener('click', () => this._copyToClipboard());
            }
        }

        // Bind close button
        if (closable) {
            const closeBtn = this.querySelector('.lib-message__close');
            if (closeBtn) {
                closeBtn.addEventListener('click', () => this.close());
            }
        }
    }

    async _copyToClipboard() {
        // Get text content to copy (message + details if present)
        const contentEl = this.querySelector('.lib-message__content');
        const details = this._getDetails();

        let textToCopy = contentEl ? contentEl.textContent.trim() : this._originalContent;
        if (details) {
            textToCopy += '\n\nDetails:\n' + details;
        }

        try {
            await navigator.clipboard.writeText(textToCopy);
            // Visual feedback - briefly change icon to checkmark
            const copyBtn = this.querySelector('.lib-message__copy');
            if (copyBtn) {
                const originalHtml = copyBtn.innerHTML;
                copyBtn.innerHTML = `<span class="lnr lnr-checkmark-circle"></span>`;
                copyBtn.classList.add('lib-message__copy--success');
                setTimeout(() => {
                    copyBtn.innerHTML = originalHtml;
                    copyBtn.classList.remove('lib-message__copy--success');
                }, 1500);
            }
        } catch (err) {
            console.error('Failed to copy:', err);
        }
    }

    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    _setupAutoDismiss() {
        this._clearAutoDismiss();
        const duration = parseInt(this.getAttribute('auto-dismiss'));
        if (duration > 0) {
            this._autoDismissTimer = setTimeout(() => this.close(), duration);
        }
    }

    _clearAutoDismiss() {
        if (this._autoDismissTimer) {
            clearTimeout(this._autoDismissTimer);
            this._autoDismissTimer = null;
        }
    }

    close() {
        this._clearAutoDismiss();
        const messageEl = this.querySelector('.lib-message');
        if (messageEl) {
            messageEl.classList.add('lib-message--removing');
            messageEl.addEventListener('animationend', () => {
                this.dispatchEvent(new CustomEvent('lib-message-close', { bubbles: true }));
                this.remove();
            }, { once: true });
        } else {
            this.remove();
        }
    }

    /**
     * Show the message. Optionally set content and type.
     * @param {string} [message] - Message text to display
     * @param {string} [type] - Message type: info, success, warning, error
     */
    show(message, type) {
        if (message !== undefined) {
            this._originalContent = String(message);
            if (type) {
                this.setAttribute('type', type);
            }
            this._rendered = true;
            this.render();
        }
        this.hidden = false;
    }
}

// Register the custom element
customElements.define('lib-message', LibMessage);

} // end guard

/**
 * Global helper functions for creating messages programmatically
 */
window.libMessage = {
    /**
     * Create and insert a message element
     * @param {string} message - The message text or HTML
     * @param {Object} options - Configuration options
     * @param {string} options.type - Message type: info, success, warning, error
     * @param {boolean} options.closable - Show close button
     * @param {number} options.autoDismiss - Auto-dismiss after X ms
     * @param {boolean} options.icon - Show icon (default: true)
     * @param {boolean} options.compact - Use compact styling
     * @param {Element} options.container - Container to insert into (default: body)
     * @param {string} options.position - 'prepend' or 'append' (default: prepend)
     * @returns {LibMessage} The created message element
     */
    create: function(message, options = {}) {
        const {
            type = 'info',
            closable = true,
            autoDismiss = 0,
            icon = true,
            compact = false,
            container = document.body,
            position = 'prepend'
        } = options;

        const el = document.createElement('lib-message');
        el.setAttribute('type', type);
        if (closable) el.setAttribute('closable', '');
        if (autoDismiss > 0) el.setAttribute('auto-dismiss', autoDismiss.toString());
        if (!icon) el.setAttribute('icon', 'false');
        if (compact) el.setAttribute('compact', '');
        el.textContent = message;

        if (position === 'prepend') {
            container.prepend(el);
        } else {
            container.appendChild(el);
        }

        return el;
    },

    info: function(message, options = {}) {
        return this.create(message, { ...options, type: 'info' });
    },

    success: function(message, options = {}) {
        return this.create(message, { ...options, type: 'success' });
    },

    warning: function(message, options = {}) {
        return this.create(message, { ...options, type: 'warning' });
    },

    error: function(message, options = {}) {
        return this.create(message, { ...options, type: 'error' });
    },

    /**
     * Clear all messages in a container
     * @param {Element} container - Container to clear (default: body)
     */
    clearAll: function(container = document.body) {
        container.querySelectorAll('lib-message').forEach(el => el.close());
    }
};

/**
 * Replacement for Lib_ToonTopNotificatie
 * Shows a top notification that auto-dismisses
 * @param {string} text - Message text
 * @param {boolean} fixed - If true, message stays until clicked (default: false)
 * @param {string} type - Message type: info, success, warning, error (default: info)
 */
window.Lib_ToonTopNotificatie = function(text, fixed, type) {
    // Determine type from old color parameters or use provided type
    if (!type) {
        type = 'info';
    }

    // Remove any existing top notification
    const existingTop = document.getElementById('lib-top-notification');
    if (existingTop) existingTop.remove();

    // Create container if needed
    let container = document.getElementById('lib-top-notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'lib-top-notification-container';
        container.style.cssText = 'position:fixed;top:16px;left:50%;transform:translateX(-50%);z-index:10001;max-width:600px;width:calc(100% - 32px);';
        document.body.appendChild(container);
    }

    const el = document.createElement('lib-message');
    el.id = 'lib-top-notification';
    el.setAttribute('type', type);
    el.setAttribute('closable', '');
    if (!fixed) {
        el.setAttribute('auto-dismiss', '3000');
    }
    el.textContent = text;

    container.appendChild(el);

    return el;
};
