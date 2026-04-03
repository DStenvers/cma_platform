/**
 * CMA JavaScript Error Handler
 *
 * Captures unhandled errors and:
 * 1. Shows visual error panel in dev mode (so errors can't be missed)
 * 2. Sends them to the server for logging
 *
 * Enhanced with visual feedback for development.
 */
(function() {
    'use strict';

    // Prevent double-loading: error-handler.js is loaded standalone (before jQuery)
    // AND inside the minify.php bundle. The standalone load ensures it's active even
    // when the bundle has a SyntaxError. This guard prevents duplicate event listeners.
    if (window.CmaErrorHandler) return;

    // =========================================================================
    // Configuration
    // =========================================================================

    const MAX_ERRORS_PER_MINUTE = 10;
    const MAX_ERRORS_IN_PANEL = 50;
    const DEDUP_WINDOW_MS = 60000;
    const STORAGE_KEY = 'cma_v2_js_errors';
    const ERROR_TTL_MS = 3600000; // Keep errors for 1 hour

    // State
    let errorCount = 0;
    let lastResetTime = Date.now();
    const recentErrors = new Set();
    let panelErrors = [];
    let errorPanel = null;
    let isMinimized = false;

    // =========================================================================
    // LocalStorage Persistence
    // =========================================================================

    function loadErrorsFromStorage() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (!stored) return;
            const data = JSON.parse(stored);
            const now = Date.now();
            // Filter out old errors (older than TTL)
            panelErrors = (data.errors || []).filter(function(err) {
                return err.timestamp && (now - err.timestamp) < ERROR_TTL_MS;
            });
        } catch (e) {
            // Ignore storage errors
        }
    }

    function saveErrorsToStorage() {
        try {
            localStorage.setItem(STORAGE_KEY, JSON.stringify({
                errors: panelErrors.slice(-MAX_ERRORS_IN_PANEL)
            }));
        } catch (e) {
            // Ignore storage errors (quota exceeded, etc)
        }
    }

    function clearStoredErrors() {
        try {
            localStorage.removeItem(STORAGE_KEY);
        } catch (e) {
            // Ignore
        }
    }

    // Load persisted errors on startup
    loadErrorsFromStorage();

    // =========================================================================
    // Dev Mode Detection
    // =========================================================================

    function isDevMode() {
        if (typeof window.CMA_DEBUG !== 'undefined') {
            return window.CMA_DEBUG;
        }
        const hostname = window.location.hostname.toLowerCase();
        return hostname === 'localhost' ||
               hostname === '127.0.0.1' ||
               hostname.indexOf('172.') === 0 ||
               hostname.indexOf('.dev') !== -1 ||
               hostname.indexOf('.test') !== -1;
    }

    // Check if we're in an iframe and should delegate to top window
    function isInIframe() {
        try {
            return window !== window.top;
        } catch (e) {
            // Cross-origin iframe - treat as iframe
            return true;
        }
    }

    // Get the top-level error handler if available
    function getTopErrorHandler() {
        try {
            if (isInIframe() && window.top && window.top.CmaErrorHandler) {
                return window.top.CmaErrorHandler;
            }
        } catch (e) {
            // Cross-origin - can't access top window
        }
        return null;
    }

    // =========================================================================
    // Visual Error Panel (Dev Mode Only)
    // =========================================================================

    function createErrorPanel() {
        if (errorPanel) return;

        // Don't create panel in iframes - delegate to top window
        if (isInIframe() && getTopErrorHandler()) {
            return;
        }

        errorPanel = document.createElement('div');
        errorPanel.id = 'cma-error-panel';
        errorPanel.innerHTML = `
            <style>
                #cma-error-panel {
                    position: fixed;
                    bottom: 0;
                    left: 0;
                    right: 0;
                    background-color: #1a0000;
                    border-top: 3px solid #ff3333;
                    font-family: Consolas, Monaco, monospace;
                    font-size: var(--font-size-sm);
                    z-index: 999999;
                    box-shadow: 0 -4px 20px rgba(255,0,0,0.3);
                }
                #cma-error-panel.minimized #cma-error-list {
                    display: none;
                }
                #cma-error-panel .error-header {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 8px 12px;
                    background-color: #330000;
                    color: #ff6666;
                    cursor: pointer;
                    user-select: none;
                }
                #cma-error-panel .error-header:hover {
                    background-color: #440000;
                }
                #cma-error-panel .error-title {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                #cma-error-panel .error-badge {
                    background-color: #ff3333;
                    color: #ffffff;
                    padding: 2px 8px;
                    border-radius: 10px;
                    font-weight: bold;
                    font-size: var(--font-size-xs);
                }
                #cma-error-panel .error-chevron {
                    display: inline-block;
                    width: 0;
                    height: 0;
                    border-left: 5px solid transparent;
                    border-right: 5px solid transparent;
                    border-top: 6px solid #ff6666;
                    margin-right: 8px;
                    transition: transform 0.2s;
                }
                #cma-error-panel.minimized .error-chevron {
                    transform: rotate(-90deg);
                }
                #cma-error-panel .error-actions {
                    display: flex;
                    gap: 8px;
                }
                #cma-error-panel .error-btn {
                    background-color: transparent;
                    border: 1px solid #ff6666;
                    color: #ff6666;
                    padding: 4px 10px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: var(--font-size-xs);
                    font-family: Consolas, Monaco, monospace;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                }
                #cma-error-panel .error-btn .lnr {
                    width: 14px;
                    height: 14px;
                }
                #cma-error-panel .error-btn .lnr::before {
                    color: red;
                    font-size: 12px;
                    line-height: 14px;
                    width: 14px;
                    height: 14px;
                }
                #cma-error-panel .error-btn:hover {
                    background-color: #ff3333;
                    color: #ffffff;
                }
                #cma-error-panel #cma-error-list {
                    max-height: 250px;
                    overflow-y: auto;
                    padding: 0;
                    margin: 0;
                    background-color: #1a0000;
                }
                #cma-error-panel .error-item {
                    padding: 10px 12px;
                    border-bottom: 1px solid #330000;
                    color: #ffaaaa;
                    background-color: #1a0000;
                }
                #cma-error-panel .error-item:hover {
                    background-color: #220000;
                }
                #cma-error-panel .error-type {
                    display: inline-block;
                    padding: 2px 6px;
                    border-radius: 3px;
                    font-size: var(--font-size-2xs);
                    font-weight: bold;
                    margin-right: 8px;
                    text-transform: uppercase;
                }
                #cma-error-panel .error-type.js { background-color: #663300; color: #ffcc66; }
                #cma-error-panel .error-type.promise { background-color: #333366; color: #aaaaff; }
                #cma-error-panel .error-type.component { background-color: #336633; color: #aaffaa; }
                #cma-error-panel .error-type.api { background-color: #663366; color: #ffaaff; }
                #cma-error-panel .error-type.manual { background-color: #444444; color: #cccccc; }
                #cma-error-panel .error-type.warning { background-color: #664400; color: #f59e0b; }
                #cma-error-panel .error-message {
                    color: #ffffff;
                    word-break: break-word;
                }
                #cma-error-panel .error-location {
                    color: #888888;
                    font-size: var(--font-size-xs);
                    margin-top: 4px;
                }
                #cma-error-panel .error-time {
                    color: #666666;
                    font-size: var(--font-size-2xs);
                    float: right;
                }
                #cma-error-panel .error-stack {
                    margin-top: 6px;
                    padding: 6px;
                    background-color: #110000;
                    border-radius: 3px;
                    font-size: var(--font-size-2xs);
                    color: #888888;
                    max-height: 80px;
                    overflow: auto;
                    white-space: pre-wrap;
                    word-break: break-all;
                }
                @keyframes cma-error-pulse {
                    0%, 100% { border-color: #ff3333; }
                    50% { border-color: #ff0000; box-shadow: 0 -4px 30px rgba(255,0,0,0.5); }
                }
                #cma-error-panel.has-new-error {
                    animation: cma-error-pulse 0.5s ease-in-out 3;
                }
            </style>
            <div class="error-header" onclick="window.CmaErrorHandler.toggle()">
                <div class="error-title">
                    <span class="error-chevron"></span>
                    <span style="font-size: var(--font-size-md); color: #ff6666;">Javascript errors</span>
                    <span class="error-badge" id="cma-error-count">0</span>
                </div>
                <div class="error-actions">
                    <button class="error-btn" onclick="event.stopPropagation(); window.CmaErrorHandler.clear();"><span class="lnr lnr-trash"></span> Clear</button>
                    <button class="error-btn error-btn-copy" onclick="event.stopPropagation(); window.CmaErrorHandler.copy();"><span class="lnr lnr-copy"></span> Copy</button>
                </div>
            </div>
            <div id="cma-error-list"></div>
        `;

        if (document.body) {
            document.body.appendChild(errorPanel);
        } else {
            // Panel created before body exists - append on DOMContentLoaded
            // Also call updatePanel after appending to ensure count is correct
            document.addEventListener('DOMContentLoaded', function() {
                if (document.body && errorPanel && !errorPanel.parentNode) {
                    document.body.appendChild(errorPanel);
                    updatePanel(); // Update count now that elements are in DOM
                }
            });
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    function updatePanel() {
        if (!errorPanel) return;

        const countEl = document.getElementById('cma-error-count');
        const listEl = document.getElementById('cma-error-list');

        if (countEl) {
            countEl.textContent = panelErrors.length;
        }

        if (listEl) {
            listEl.innerHTML = panelErrors.map(function(err) {
                const stack = err.stack ?
                    `<div class="error-stack">${escapeHtml(err.stack.substring(0, 500))}</div>` : '';
                const location = err.file ?
                    `<div class="error-location">${escapeHtml(err.file)}:${err.line || 0}</div>` : '';

                return `
                    <div class="error-item">
                        <span class="error-time">${err.time}</span>
                        <span class="error-type ${err.type}">${err.type}</span>
                        <span class="error-message">${escapeHtml(err.message)}</span>
                        ${location}
                        ${stack}
                    </div>
                `;
            }).reverse().join('');
        }

        // Pulse animation
        if (errorPanel) {
            errorPanel.classList.add('has-new-error');
            setTimeout(function() {
                if (errorPanel) {
                    errorPanel.classList.remove('has-new-error');
                }
            }, 1500);
        }
    }

    function addToPanel(errorInfo) {
        // If in iframe, delegate to top-level window's error handler
        const topHandler = getTopErrorHandler();
        if (topHandler && topHandler !== window.CmaErrorHandler) {
            // Add source indicator for iframe errors
            errorInfo.message = '[iframe] ' + errorInfo.message;
            topHandler._addToPanel(errorInfo);
            return;
        }

        errorInfo.time = new Date().toLocaleTimeString();
        errorInfo.timestamp = Date.now(); // For persistence TTL

        if (panelErrors.length >= MAX_ERRORS_IN_PANEL) {
            panelErrors.shift();
        }

        panelErrors.push(errorInfo);
        saveErrorsToStorage(); // Persist errors across page transitions

        if (isDevMode()) {
            createErrorPanel();
            updatePanel();
        }
    }

    // =========================================================================
    // Rate Limiting & Deduplication
    // =========================================================================

    function getErrorFingerprint(message, url, line, column) {
        return `${message}|${url}|${line}|${column}`;
    }

    function isRateLimited() {
        const now = Date.now();
        if (now - lastResetTime > 60000) {
            errorCount = 0;
            lastResetTime = now;
            recentErrors.clear();
        }
        return errorCount >= MAX_ERRORS_PER_MINUTE;
    }

    // =========================================================================
    // Server Logging
    // =========================================================================

    function sendError(errorData) {
        if (isRateLimited()) {
            cmaLog.warn('[CMA Error] Rate limit reached');
            return;
        }

        const fingerprint = getErrorFingerprint(
            errorData.message,
            errorData.url,
            errorData.line,
            errorData.column
        );

        if (recentErrors.has(fingerprint)) {
            return;
        }

        recentErrors.add(fingerprint);
        errorCount++;

        // Send to server
        const formData = new FormData();
        formData.append('action', 'logJsError');
        formData.append('message', errorData.message || '');
        formData.append('url', errorData.url || '');
        formData.append('line', errorData.line || 0);
        formData.append('column', errorData.column || 0);
        formData.append('stack', errorData.stack || '');
        formData.append('pageUrl', window.location.href);
        formData.append('userAgent', navigator.userAgent);
        formData.append('extraInfo', JSON.stringify(errorData.extra || {}));

        const endpoint = 'form_api.php';

        if (navigator.sendBeacon) {
            navigator.sendBeacon(endpoint, formData);
        } else {
            fetch(endpoint, {
                method: 'POST',
                body: formData,
                keepalive: true
            }).catch(function(err) {
                // Log error reporting failures - important for debugging connectivity issues
                cmaLog.error('[ErrorHandler] Failed to send error report:', err.message || err);
            });
        }
    }

    // =========================================================================
    // Global Error Handlers
    // =========================================================================

    // Using addEventListener for better capture
    window.addEventListener('error', function(event) {
        // Ignore browser extension errors
        if (event.filename && (event.filename.includes('chrome-extension://') ||
            event.filename.includes('moz-extension://'))) {
            return;
        }

        // Ignore ResizeObserver errors
        if (event.message && event.message.includes('ResizeObserver')) {
            return;
        }

        const errorInfo = {
            type: 'js',
            message: event.message || 'Unknown error',
            file: event.filename,
            line: event.lineno,
            column: event.colno,
            stack: event.error ? event.error.stack : null
        };

        // Add to visual panel
        addToPanel(errorInfo);

        // Send to server
        sendError({
            message: errorInfo.message,
            url: errorInfo.file,
            line: errorInfo.line,
            column: errorInfo.column,
            stack: errorInfo.stack,
            extra: { type: 'window.error' }
        });
    });

    window.addEventListener('unhandledrejection', function(event) {
        const reason = event.reason;
        let message = 'Unhandled Promise Rejection';
        let stack = '';

        if (reason instanceof Error) {
            message = reason.message;
            stack = reason.stack || '';
        } else if (typeof reason === 'string') {
            message = reason;
        } else if (reason) {
            try {
                message = JSON.stringify(reason);
            } catch (e) {
                message = String(reason);
            }
        }

        const errorInfo = {
            type: 'promise',
            message: message,
            stack: stack
        };

        addToPanel(errorInfo);

        sendError({
            message: message,
            url: window.location.href,
            line: 0,
            column: 0,
            stack: stack,
            extra: { type: 'unhandledrejection' }
        });
    });

    // Resource loading errors (CSS, JS, images) — must use capture phase
    window.addEventListener('error', function(event) {
        // Only handle resource errors (elements that failed to load)
        var target = event.target;
        if (!target || target === window || !target.tagName) return;

        var tag = target.tagName.toLowerCase();
        var url = target.src || target.href || '';
        if (!url) return;

        // Ignore CMA image preview 404s — handled by onerror on the element
        if (tag === 'img' && target.hasAttribute('data-image-preview')) return;

        // Ignore browser extension resources
        if (url.includes('chrome-extension://') || url.includes('moz-extension://')) return;

        // Gather context: parent element, field name, closest form field
        var context = '';
        if (tag === 'img') {
            var parentEl = target.closest('[data-field], [name], textarea, .cke_editable');
            var fieldName = parentEl ? (parentEl.dataset.field || parentEl.getAttribute('name') || parentEl.id || '') : '';
            var inEditor = !!target.closest('.cke_editable, .cke_contents, iframe');
            context = (fieldName ? ' in field "' + fieldName + '"' : '') + (inEditor ? ' (CKEditor content)' : '');
        }

        var errorInfo = {
            type: 'resource',
            message: 'Failed to load ' + tag + ': ' + url + context,
            file: url
        };

        addToPanel(errorInfo);

        sendError({
            message: errorInfo.message,
            url: url,
            line: 0,
            column: 0,
            stack: null,
            extra: { type: 'resource', element: tag }
        });
    }, true); // true = capture phase, required for resource errors

    // Component errors (from CmaBaseComponent or manual dispatch)
    window.addEventListener('cma-component-error', function(event) {
        const detail = event.detail || {};
        const errorInfo = {
            type: 'component',
            message: `[${detail.tagName || 'unknown'}] ${detail.context || ''}: ${detail.error?.message || detail.error || 'Unknown'}`,
            stack: detail.error?.stack
        };

        addToPanel(errorInfo);

        sendError({
            message: errorInfo.message,
            url: window.location.href,
            line: 0,
            column: 0,
            stack: errorInfo.stack,
            extra: {
                type: 'component',
                tagName: detail.tagName,
                context: detail.context
            }
        });
    });

    // =========================================================================
    // Public API
    // =========================================================================

    window.CmaErrorHandler = {
        getErrors: function() { return [...panelErrors]; },
        getCount: function() { return panelErrors.length; },

        // Internal: called by iframes to add errors to the top-level panel
        _addToPanel: function(errorInfo) {
            errorInfo.time = new Date().toLocaleTimeString();
            errorInfo.timestamp = Date.now();

            if (panelErrors.length >= MAX_ERRORS_IN_PANEL) {
                panelErrors.shift();
            }

            panelErrors.push(errorInfo);
            saveErrorsToStorage();

            if (isDevMode()) {
                createErrorPanel();
                updatePanel();
            }
        },

        // Test function to verify error panel is working
        test: function() {
            // cmaLog.log('[CMA Error Handler] Running test...');
            // cmaLog.log('  isDevMode():', isDevMode());
            // cmaLog.log('  panelErrors:', panelErrors.length);
            // cmaLog.log('  errorPanel exists:', !!errorPanel);

            // Force show a test error
            addToPanel({
                type: 'manual',
                message: 'Test error from CmaErrorHandler.test() - if you see this panel, error handling is working!',
                file: 'error-handler.js',
                line: 0
            });

            // cmaLog.log('[CMA Error Handler] Test error added. Panel should be visible at bottom of page.');
            return 'Check bottom of page for red error panel';
        },

        clear: function() {
            panelErrors.length = 0;
            clearStoredErrors(); // Also clear from localStorage
            // Remove the panel from DOM
            if (errorPanel && errorPanel.parentNode) {
                errorPanel.parentNode.removeChild(errorPanel);
            }
            errorPanel = null; // Allow recreation if new errors occur
        },

        toggle: function() {
            if (errorPanel) {
                isMinimized = !isMinimized;
                errorPanel.classList.toggle('minimized', isMinimized);
            }
        },

        copy: function() {
            const text = panelErrors.map(function(err) {
                // Format: [time] TYPE: message, optionally with "at file:line" if file is set
                let output = `[${err.time}] ${err.type.toUpperCase()}: ${err.message}`;
                if (err.file) {
                    output += '\n  at ' + err.file;
                    // Only append line number if it's defined and not empty
                    if (err.line !== undefined && err.line !== null && err.line !== '') {
                        output += ':' + err.line;
                    }
                }
                return output;
            }).join('\n\n');

            const copyBtn = errorPanel ? errorPanel.querySelector('.error-btn-copy') : null;
            navigator.clipboard.writeText(text).then(function() {
                if (copyBtn) {
                    copyBtn.innerHTML = '<span class="lnr lnr-checkmark-circle"></span> Gekopieerd';
                    setTimeout(function() { copyBtn.innerHTML = '<span class="lnr lnr-copy"></span> Copy'; }, 2000);
                }
            }).catch(function() {
                // cmaLog.log('Errors:\n' + text);
            });
        },

        // Manual error reporting
        report: function(type, message, extra) {
            const errorInfo = {
                type: type || 'manual',
                message: message,
                stack: extra?.stack
            };
            addToPanel(errorInfo);
            sendError({
                message: message,
                url: window.location.href,
                line: 0,
                column: 0,
                stack: extra?.stack || '',
                extra: Object.assign({ type: 'manual' }, extra || {})
            });
        },

        // API error reporting helper
        reportApiError: function(url, status, message) {
            const errorInfo = {
                type: 'api',
                message: `${status} ${message} - ${url}`,
                file: url
            };
            addToPanel(errorInfo);
        },

        // Warning reporting (non-critical issues)
        reportWarning: function(message, context) {
            const errorInfo = {
                type: 'warning',
                message: message,
                file: context?.source || context?.url || null
            };
            addToPanel(errorInfo);
            // Also log to console in dev mode
            if (isDevMode()) {
                cmaLog.warn('[CMA Warning]', message, context || '');
            }
        },

        // Server-side error reporting (not found, validation errors, etc.)
        reportServerError: function(source, message, context) {
            const errorInfo = {
                type: 'api',
                message: `[${source}] ${message}`,
                file: context?.url || window.location.href,
                stack: context?.debug ? JSON.stringify(context.debug, null, 2) : null
            };
            addToPanel(errorInfo);
            // Send to server log as well
            sendError({
                message: `[Server] ${source}: ${message}`,
                url: context?.url || window.location.href,
                line: 0,
                column: 0,
                stack: '',
                extra: Object.assign({ type: 'server', source: source }, context || {})
            });
        },

        // Report "not found" type errors (form, record, field, etc.)
        reportNotFound: function(what, identifier, context) {
            const message = `${what} niet gevonden: ${identifier}`;
            const errorInfo = {
                type: 'warning',
                message: message,
                file: context?.source || context?.url || null
            };
            addToPanel(errorInfo);
            if (isDevMode()) {
                cmaLog.warn('[CMA Not Found]', what, identifier, context || '');
            }
        },

        isDevMode: isDevMode,
        show: function() {
            if (isDevMode()) {
                createErrorPanel();
                isMinimized = false;
                if (errorPanel) errorPanel.classList.remove('minimized');
            }
        }
    };

    // Legacy API compatibility
    window.cmaLogError = function(message, extra) {
        window.CmaErrorHandler.report('manual', message, extra);
    };

    // Show panel if there are persisted errors from previous page
    if (isDevMode() && panelErrors.length > 0) {
        // Wait for DOM to be ready before showing panel
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                createErrorPanel();
                updatePanel();
            });
        } else {
            createErrorPanel();
            updatePanel();
        }
    }

    // Log initialization - always log in dev mode for debugging
    // Use console directly here since cmaLog may not be loaded yet
    if (isDevMode() && typeof console !== 'undefined') {
        // console.log('[CMA Error Handler] Loaded | DevMode:', isDevMode(),
        //     '| Persisted errors:', panelErrors.length,
        //     '| Host:', window.location.hostname);
        // console.log('[CMA Error Handler] Run CmaErrorHandler.test() to verify error panel');
    }

})();
