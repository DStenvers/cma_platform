/**
 * CMA Base Component
 *
 * Base class for all CMA web components providing:
 * - Error handling with visual indicators
 * - Ready state pattern (whenReady() promise)
 * - Automatic error reporting to global error handler
 * - Consistent lifecycle management
 *
 * Usage:
 *   class MyComponent extends CmaBaseComponent {
 *       // Override _connectedCallback instead of connectedCallback
 *       _connectedCallback() {
 *           // Your initialization code
 *       }
 *
 *       // Similarly for other lifecycle methods:
 *       // _disconnectedCallback()
 *       // _attributeChangedCallback(name, oldValue, newValue)
 *   }
 */
(function() {
    'use strict';

    // Check if already defined (prevent re-registration)
    if (window.CmaBaseComponent) {
        return;
    }

    class CmaBaseComponent extends HTMLElement {
        constructor() {
            super();

            // Ready state management
            this._ready = false;
            this._readyPromise = new Promise((resolve) => {
                this._resolveReady = resolve;
            });

            // Error state
            this._hasError = false;
            this._lastError = null;

            // Call subclass constructor if defined
            if (typeof this._constructor === 'function') {
                try {
                    this._constructor();
                } catch (e) {
                    this._handleError(e, 'constructor');
                }
            }
        }

        // =====================================================================
        // Ready State Pattern
        // =====================================================================

        /**
         * Check if component is fully initialized
         */
        get ready() {
            return this._ready;
        }

        /**
         * Wait for component to be ready
         * @returns {Promise} Resolves when component is initialized
         */
        whenReady() {
            return this._readyPromise;
        }

        /**
         * Mark component as ready (call from subclass when initialization complete)
         */
        _markReady() {
            if (this._ready) return;

            this._ready = true;
            this._resolveReady(this);
            this.dispatchEvent(new CustomEvent('component-ready', {
                bubbles: true,
                detail: { component: this }
            }));
        }

        // =====================================================================
        // Error Handling
        // =====================================================================

        /**
         * Check if component has encountered an error
         */
        get hasError() {
            return this._hasError;
        }

        /**
         * Get the last error
         */
        get lastError() {
            return this._lastError;
        }

        /**
         * Handle and report an error
         * @param {Error|string} error - The error
         * @param {string} context - Where the error occurred (e.g., 'connectedCallback')
         */
        _handleError(error, context) {
            this._hasError = true;
            this._lastError = error;

            const message = error instanceof Error ? error.message : String(error);
            const stack = error instanceof Error ? error.stack : null;

            // Log to console via cmaLog
            cmaLog.error(`[${this.tagName}] Error in ${context}:`, message, { stack: stack });

            // Visual indicator in dev mode
            if (this._isDevMode()) {
                this._showErrorIndicator(context, message);
            }

            // Report to global error handler
            window.dispatchEvent(new CustomEvent('cma-component-error', {
                detail: {
                    tagName: this.tagName,
                    context: context,
                    error: error,
                    component: this
                }
            }));

            // Dispatch error event on component
            this.dispatchEvent(new CustomEvent('component-error', {
                bubbles: true,
                detail: { context, error, message }
            }));
        }

        /**
         * Show visual error indicator on the component
         */
        _showErrorIndicator(context, message) {
            // Red border
            this.style.outline = '2px solid #ff0000';
            this.style.outlineOffset = '-2px';

            // Error attribute for CSS targeting
            this.setAttribute('data-error', 'true');

            // Tooltip with error info
            this.title = `Error in ${context}: ${message}`;

            // Add error badge if not already present
            if (!this.querySelector('.cma-error-badge')) {
                const badge = document.createElement('span');
                badge.className = 'cma-error-badge';
                badge.textContent = '⚠️';
                badge.style.cssText = `
                    position: absolute;
                    top: 0;
                    right: 0;
                    background: #ff0000;
                    color: white;
                    padding: 2px 6px;
                    font-size: 12px;
                    border-radius: 0 0 0 4px;
                    cursor: help;
                    z-index: 10000;
                `;
                badge.title = `${context}: ${message}`;

                // Make sure component has position for absolute child
                const style = window.getComputedStyle(this);
                if (style.position === 'static') {
                    this.style.position = 'relative';
                }

                this.appendChild(badge);
            }
        }

        /**
         * Clear error state
         */
        _clearError() {
            this._hasError = false;
            this._lastError = null;
            this.style.outline = '';
            this.style.outlineOffset = '';
            this.removeAttribute('data-error');
            this.title = '';

            const badge = this.querySelector('.cma-error-badge');
            if (badge) {
                badge.remove();
            }
        }

        /**
         * Check if running in development mode
         */
        _isDevMode() {
            if (typeof window.CMA_DEBUG !== 'undefined') {
                return window.CMA_DEBUG;
            }
            const hostname = window.location.hostname.toLowerCase();
            return hostname === 'localhost' ||
                   hostname === '127.0.0.1' ||
                   hostname.indexOf('172.') === 0;
        }

        // =====================================================================
        // Lifecycle Method Wrappers
        // =====================================================================

        connectedCallback() {
            try {
                // Call subclass implementation
                if (typeof this._connectedCallback === 'function') {
                    this._connectedCallback();
                }

                // Auto-mark ready if subclass doesn't do it manually
                // (delayed to allow async initialization)
                if (!this._ready) {
                    requestAnimationFrame(() => {
                        if (!this._ready && !this._hasError) {
                            this._markReady();
                        }
                    });
                }
            } catch (e) {
                this._handleError(e, 'connectedCallback');
            }
        }

        disconnectedCallback() {
            try {
                if (typeof this._disconnectedCallback === 'function') {
                    this._disconnectedCallback();
                }
            } catch (e) {
                this._handleError(e, 'disconnectedCallback');
            }
        }

        attributeChangedCallback(name, oldValue, newValue) {
            try {
                if (typeof this._attributeChangedCallback === 'function') {
                    this._attributeChangedCallback(name, oldValue, newValue);
                }
            } catch (e) {
                this._handleError(e, `attributeChangedCallback(${name})`);
            }
        }

        adoptedCallback() {
            try {
                if (typeof this._adoptedCallback === 'function') {
                    this._adoptedCallback();
                }
            } catch (e) {
                this._handleError(e, 'adoptedCallback');
            }
        }

        // =====================================================================
        // Utility Methods
        // =====================================================================

        /**
         * Safely query a selector, returning null if not found
         */
        _query(selector, context = document) {
            try {
                return context.querySelector(selector);
            } catch (e) {
                this._handleError(e, `_query(${selector})`);
                return null;
            }
        }

        /**
         * Safely query all matching elements
         */
        _queryAll(selector, context = document) {
            try {
                return Array.from(context.querySelectorAll(selector));
            } catch (e) {
                this._handleError(e, `_queryAll(${selector})`);
                return [];
            }
        }

        /**
         * Safely get a numeric attribute
         */
        _getNumericAttr(name, defaultValue = 0) {
            const value = this.getAttribute(name);
            if (value === null || value === '') return defaultValue;
            const num = parseFloat(value);
            return isNaN(num) ? defaultValue : num;
        }

        /**
         * Safely get a boolean attribute
         */
        _getBooleanAttr(name) {
            return this.hasAttribute(name);
        }

        /**
         * Emit a custom event
         */
        _emit(eventName, detail = {}, options = {}) {
            const event = new CustomEvent(eventName, {
                bubbles: options.bubbles !== false,
                cancelable: options.cancelable !== false,
                composed: options.composed || false,
                detail
            });
            return this.dispatchEvent(event);
        }

        /**
         * Safe JSON parse
         */
        _parseJSON(str, defaultValue = null) {
            try {
                return JSON.parse(str);
            } catch (e) {
                if (str) {
                    cmaLog.warn(`[${this.tagName}] Invalid JSON:`, str);
                }
                return defaultValue;
            }
        }

        /**
         * Log in dev mode only (respects CMA_CONSOLE_LOGGING flag)
         */
        _log(...args) {
            if (this._isDevMode()) {
                cmaLog.log(`[${this.tagName}]`, ...args);
            }
        }

        /**
         * Log warning (respects CMA_CONSOLE_LOGGING flag)
         */
        _warn(...args) {
            cmaLog.warn(`[${this.tagName}]`, ...args);
        }
    }

    // Export to window
    window.CmaBaseComponent = CmaBaseComponent;

})();
