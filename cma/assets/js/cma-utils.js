/**
 * CMA Shared Utilities
 *
 * Core utilities shared across all CMA JavaScript files.
 * This file should be loaded AFTER lib-log.js (which sets up the logging system).
 *
 * Contains:
 * - CMA_DEBUG - Environment detection for debug mode
 * - CMA_CONSOLE_LOGGING - User preference for console logging (set by lib-log.js)
 * - cmaLog - Uses LibLog if available, otherwise simple fallback
 * - cmaErrorParser - PHP error extraction utilities
 *
 * NOTE: lib-log.js should be loaded first and sets CMA_CONSOLE_LOGGING from cookie.
 * This file provides fallbacks if lib-log.js is not loaded.
 */

// Prevent re-declaration errors when script is loaded multiple times
if (typeof window.CMA_DEBUG === 'undefined') {

/**
 * Debug mode - enable console logging only for O and T environments
 * O = Ontwikkeling (Development), T = Test
 * Production environments (P, A, etc.) skip console logging
 */
window.CMA_DEBUG = (function() {
    const hostname = window.location.hostname.toLowerCase();
    // O and T environments: localhost, dev, test, or hostname starting with o/t
    return hostname === 'localhost' ||
           hostname === '127.0.0.1' ||
           hostname.indexOf('172.') === 0 ||  // Local network (dev)
           hostname.indexOf('.dev') !== -1 ||
           hostname.indexOf('.test') !== -1 ||
           hostname.indexOf('-o.') !== -1 ||  // -o. subdomain pattern
           hostname.indexOf('-t.') !== -1;    // -t. subdomain pattern
})();

/**
 * Console logging preference - use lib-log.js value if set, otherwise read from cookie
 * Cookie cma_debug_mode: 'J' = enabled, 'N' = disabled
 * When disabled, suppresses all console.log/warn output for performance
 * Errors are always logged regardless of this setting
 */
if (typeof window.CMA_CONSOLE_LOGGING === 'undefined') {
    window.CMA_CONSOLE_LOGGING = (function() {
        // Read cookie value
        const cookies = document.cookie.split(';');
        for (let i = 0; i < cookies.length; i++) {
            const cookie = cookies[i].trim();
            if (cookie.indexOf('cma_debug_mode=') === 0) {
                return cookie.substring('cma_debug_mode='.length) === 'J';
            }
        }
        // Default: use environment-based debug mode
        return window.CMA_DEBUG;
    })();
}

/**
 * Conditional console logging
 * If LibLog (from lib-log.js) is available, use it for full features
 * (server logging, batching, error panel integration).
 * Otherwise, fall back to simple console wrapper.
 */
// NOTE: never test `typeof LibLog !== 'undefined'` to decide whether the real
// logger is present. Any element with id="LibLog" makes window.LibLog a DOM node
// (named element access), so the identifier exists but has no log/warn/error.
// Always probe the method itself.
function cmaHasLibLog(method) {
    return !!window.LibLog && typeof window.LibLog[method] === 'function';
}

if (typeof window.cmaLog === 'undefined' || !cmaHasLibLog('log')) {
    // LibLog not loaded or cmaLog not set - provide fallback
    window.cmaLog = cmaHasLibLog('log') ? window.LibLog : {
        log: function(...args) {
            // Delegate to LibLog if available, otherwise use console directly
            if (cmaHasLibLog('log')) { window.LibLog.log(...args); }
            else if (window.CMA_CONSOLE_LOGGING) { console.log(...args); }
        },
        warn: function(...args) {
            if (cmaHasLibLog('warn')) { window.LibLog.warn(...args); }
            else if (window.CMA_CONSOLE_LOGGING) { console.warn(...args); }
        },
        error: function(...args) {
            // Always log errors - important for debugging production issues
            if (cmaHasLibLog('error')) { window.LibLog.error(...args); }
            else { console.error(...args); }
        },
        // Alias for convenience
        debug: function(...args) {
            if (cmaHasLibLog('log')) { window.LibLog.log('[DEBUG]', ...args); }
            else if (window.CMA_CONSOLE_LOGGING) { console.log('[DEBUG]', ...args); }
        },
        // Method to check if logging is enabled
        isEnabled: function() {
            return window.CMA_CONSOLE_LOGGING;
        }
    };
}
// If LibLog exists, cmaLog was already set by lib-log.js - don't overwrite

/**
 * PHP Error Parser - extracts error info from HTML responses
 *
 * Handles:
 * - [PHP_ERROR] markers embedded by ErrorHandler
 * - Standard PHP Fatal error output
 * - Standard PHP Parse error output
 */

/**
 * Copy text to the clipboard, working on BOTH secure (https/localhost) and
 * insecure (plain http, e.g. a LAN dev box) contexts. navigator.clipboard is
 * undefined on insecure origins, so a direct navigator.clipboard.writeText()
 * throws "Cannot read properties of undefined (reading 'writeText')". This
 * helper uses the async Clipboard API when available and falls back to a hidden
 * textarea + execCommand('copy'). Always returns a Promise so callers can keep
 * using .then()/await unchanged.
 * @param {string} text
 * @returns {Promise<void>}
 */
window.cmaCopyToClipboard = function(text) {
    text = (text === null || text === undefined) ? '' : String(text);
    function legacyCopy() {
        return new Promise(function(resolve, reject) {
            try {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.top = '-9999px';
                document.body.appendChild(ta);
                ta.select();
                ta.setSelectionRange(0, ta.value.length);
                var ok = document.execCommand('copy');
                document.body.removeChild(ta);
                ok ? resolve() : reject(new Error('execCommand copy failed'));
            } catch (e) { reject(e); }
        });
    }
    if (window.navigator && navigator.clipboard && navigator.clipboard.writeText) {
        // writeText can still reject when available (document not focused,
        // permission denied) - retry via the legacy textarea path
        return navigator.clipboard.writeText(text).catch(function() { return legacyCopy(); });
    }
    return legacyCopy();
};

window.cmaErrorParser = {
    /**
     * Extract PHP error from HTML response
     * Looks for [PHP_ERROR] markers embedded by ErrorHandler
     * Also handles standard PHP error output
     *
     * @param {string} html - HTML response text
     * @returns {object|null} - Parsed error info {type, message, file, line} or null
     */
    extract: function(html) {
        if (!html) return null;

        // Look for [PHP_ERROR] marker from ErrorHandler
        // Format: [PHP_ERROR] Type: X | Message: Y | File: Z | Line: N [/PHP_ERROR]
        const match = html.match(/\[PHP_ERROR\][^T]*Type:\s*([^|]+?)\s*\|\s*Message:\s*([^|]+?)\s*\|\s*File:\s*([^|]+?)\s*\|\s*Line:\s*(\d+)\s*\[\/PHP_ERROR\]/);
        if (match) {
            return {
                type: match[1].trim(),
                message: match[2].trim(),
                file: match[3].trim(),
                line: parseInt(match[4].trim(), 10)
            };
        }

        // Try standard PHP Fatal error format
        // Format: Fatal error: ... in /path/to/file.php on line N
        const fatalMatch = html.match(/Fatal error:\s*(.+?)\s+in\s+([^\s<]+)\s+on line\s+(\d+)/i);
        if (fatalMatch) {
            return {
                type: 'Fatal Error',
                message: fatalMatch[1].trim(),
                file: fatalMatch[2].trim(),
                line: parseInt(fatalMatch[3].trim(), 10)
            };
        }

        // Try Parse error format
        const parseMatch = html.match(/Parse error:\s*(.+?)\s+in\s+([^\s<]+)\s+on line\s+(\d+)/i);
        if (parseMatch) {
            return {
                type: 'Parse Error',
                message: parseMatch[1].trim(),
                file: parseMatch[2].trim(),
                line: parseInt(parseMatch[3].trim(), 10)
            };
        }

        return null;
    },

    /**
     * Clean verbose database error messages
     * @param {string} msg - Raw error message
     * @returns {string} - Cleaned message
     */
    cleanMessage: function(msg) {
        if (!msg) return msg;
        // Remove verbose prefixes and technical details
        const remove = [
            'Database query failed: ',
            'Database query failed:',
            'Exception: ',
            'Exception:',
            'Native ODBC error: ',
            'Native ODBC error:',
            '[Microsoft][ODBC Microsoft Access-stuurprogramma] ',
            '[Microsoft][ODBC Microsoft Access-stuurprogramma]',
            'De Microsoft Access-database-engine ',
            'De Microsoft Access-database-engine',
        ];
        let cleaned = msg;
        remove.forEach(function(prefix) {
            cleaned = cleaned.split(prefix).join('');
        });
        return cleaned.trim();
    },

    /**
     * Format PHP error for display
     * @param {object} phpError - Error object from extract()
     * @returns {string} - HTML formatted error message
     */
    format: function(phpError) {
        if (!phpError) return 'Onbekende fout';

        // Clean the message from verbose prefixes
        const cleanedMessage = this.cleanMessage(phpError.message);

        // Skip showing generic "Exception" types - not useful info
        // Matches: Exception, \Exception, ErrorException, RuntimeException, Error, etc.
        let message = '';
        const skipSuffixes = ['Exception', 'Error'];
        const typeToShow = phpError.type && !skipSuffixes.some(function(suffix) {
            return phpError.type === suffix || phpError.type.endsWith(suffix);
        });
        if (typeToShow) {
            message = '<span class="cma-js__strong">' + this.escapeHtml(phpError.type) + ':</span> ';
        }
        message += this.escapeHtml(cleanedMessage);

        // Always show file/line info for PHP errors - it's essential for debugging
        if (phpError.file) {
            // Shorten the file path to last 3 parts
            const shortFile = phpError.file.split(/[\/\\]/).slice(-3).join('/');
            message += '<br><small class="error-debug">Bestand: ' + shortFile + ':' + phpError.line + '</small>';
        }

        return message;
    },

    /**
     * Escape HTML special characters
     * @param {string} str - String to escape
     * @returns {string} - Escaped string
     */
    escapeHtml: function(str) {
        // Delegate to the canonical CMA.utils.escapeHtml defined below (assigned
        // at module load; this method only runs on error-render). Keeps one
        // escaping implementation with consistent quote handling.
        return window.escapeHtml(str);
    }
};

/**
 * Shared string/HTML utilities
 * Available as both CMA.utils.x() and global window.x()
 */
window.CMA = window.CMA || {};
CMA.utils = CMA.utils || {};

/**
 * Escape HTML special characters (safe for use in attributes)
 * @param {string} str - String to escape
 * @returns {string} - Escaped string
 */
CMA.utils.escapeHtml = function(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#039;');
};
window.escapeHtml = CMA.utils.escapeHtml;

/**
 * Format the toolbar record-count indicator text. Single source of truth for
 * the "records 1-N van M" string shared by FormController.updateRecordCount
 * and CmaInfiniteScroll.updateRecordCountDisplay, so the two can't drift.
 * Returns null when there is nothing to report (all records shown, or nothing
 * loaded) — pass that straight to setRecordCount().
 * @param {number} loaded   Records loaded so far
 * @param {number|null} total  Total in dataset (null/undefined if unknown)
 * @param {boolean} hasMore Still loading — appends the " (laden...)" suffix
 * @returns {string|null}
 */
CMA.utils.formatRecordCount = function(loaded, total, hasMore) {
    if (total !== null && total !== undefined && total > 0) {
        const endRecord = Math.min(loaded, total);
        if (endRecord >= total) return null; // all records shown -> nothing to report
        return `records 1-${endRecord} van ${total}${hasMore ? ' (laden...)' : ''}`;
    }
    if (loaded > 0) return `${loaded} records`;
    return null;
};

/**
 * Write the toolbar record-count indicator. Every caller goes through here.
 *
 * The indicator carries .table-mode-only, and that class is styled !important
 * in BOTH directions (hidden outside table mode, shown inside it). An inline
 * style.display written from JS therefore never lands: the stylesheet owns
 * whether the element is displayed, per mode. What JS owns is the TEXT — and
 * empty text is what "nothing to report" looks like, since the span has no box
 * of its own. Steering style.display instead left the previous batch's string
 * frozen on screen ("records 1-1600 van 1625 (laden...)" while all 1625 rows
 * had in fact loaded), because the hide branch was the one branch that never
 * rewrote the text.
 *
 * @param {string|null} text Text to show; null/'' clears the indicator
 */
CMA.utils.setRecordCount = function(text) {
    const countEl = document.getElementById('recordCount');
    if (!countEl) return;
    countEl.textContent = text || '';
};

/**
 * Build a form popup/detail title like "Order wijzigen". Single source of the
 * action-suffix precedence (new > copy > view > edit) that was previously
 * inlined at ~8 call sites in form-controller.js / inline-edit.js with
 * inconsistent branches. Callers still resolve `name` themselves (the singular
 * form/subform name comes from different data shapes).
 * @param {string} name    Singular form name, already resolved
 * @param {Object} opts
 * @param {*} opts.recordId  Record id; null/undefined/'' => new ("toevoegen")
 * @param {boolean} opts.canEdit  falsy => "bekijken" (pass an explicit boolean)
 * @param {boolean} opts.isCopy   true (and not new) => "kopiëren"
 * @returns {string}
 */
/**
 * Staat dit vinkje aan?
 *
 * Access levert een ja/nee-veld als -1 of 0, en of dat als GETAL of als TEKST bij de
 * browser aankomt hangt van de driver af. De twee plekken die dit beoordeelden hadden
 * elk hun eigen lijstje, en die liepen uiteen: de tabelweergave kende '-1' wel, het
 * formulier niet. Gevolg: in de lijst stond de schakelaar aan en in het formulier
 * eronder uit, voor precies hetzelfde record.
 *
 * @param {*} waarde
 * @returns {boolean}
 */
CMA.utils.isAangevinkt = function(waarde) {
    if (waarde === true) { return true; }
    if (waarde === 1 || waarde === -1) { return true; }
    if (typeof waarde !== 'string') { return false; }
    const w = waarde.trim().toLowerCase();
    return w === 'true' || w === '1' || w === '-1' || w === 'j' || w === 'ja'
        || w === 'y' || w === 'yes' || w === 'on';
};

CMA.utils.formActionTitle = function(name, opts) {
    opts = opts || {};
    const rid = opts.recordId;
    const isNew = rid === null || rid === undefined || rid === '';
    let suffix;
    if (isNew) suffix = 'toevoegen';
    else if (opts.isCopy) suffix = 'kopiëren';
    else if (!opts.canEdit) suffix = 'bekijken'; // matches inline-edit's !canEdit
    else suffix = 'wijzigen';
    return name + ' ' + suffix;
};

/**
 * Capitalize first letter of a string
 * @param {string} str
 * @returns {string}
 */
CMA.utils.ucfirst = function(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
};
window.ucfirst = CMA.utils.ucfirst;

/**
 * Open a record form (form.php) in a popup, sidepanel or window with unified
 * behavior: rapid double-open dedupe, clean-URL sync (up to 3 levels), popup
 * style preference dispatch and an optional close-watcher. Single source of
 * truth shared by CmaFormController.openPopup and CmaInlineEdit.openFormPopup.
 *
 * @param {Object} options
 * @param {string|number} options.formId - JSON form name (or legacy numeric id)
 * @param {string|number|null} options.recordId - Record id (null/'' = new record)
 * @param {string|number|null} [options.parentId] - Parent record id for subform records
 * @param {string} [options.parentField] - Parent FK field name for subform records
 * @param {boolean} [options.copy] - Open as a copy of recordId (skips URL sync)
 * @param {string} [options.title] - Window title (used as-is; caller casing wins)
 * @param {string} [options.windowName] - Window name for reuse, default 'form_popup'
 * @param {string} [options.filterField] - Toolbar filter field to inherit in the popup
 * @param {string} [options.filterValue] - Toolbar filter value
 * @param {function} [options.onClose] - Called once the popup/sidepanel closes
 * @param {boolean} [options.cascadeOffset] - Shrink size for stacked windows (default true)
 * @returns {string|null} The form.php URL that was opened, or null when deduped
 */
CMA.utils.openFormPopup = null; // assigned below (IIFE keeps the watcher private)
CMA.utils.cancelPopupWatch = null;
(function() {
    let checkInterval = null;

    function cancelWatch() {
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
        }
    }

    function watchClose(isClosed, onClose) {
        cancelWatch();
        let checkCount = 0;
        const maxChecks = 3600; // Max 30 minutes (3600 * 500ms)
        checkInterval = setInterval(function() {
            checkCount++;
            if (isClosed()) {
                cancelWatch();
                onClose();
            } else if (checkCount >= maxChecks) {
                cancelWatch();
            }
        }, 500);
    }

    /**
     * Reflect the opened record in the clean URL (refresh/deep-link
     * persistence). Depth-aware up to 3 levels: a parentId means we're opening
     * a child of the current detail view (subform record, or sub-subform when
     * already two levels deep); without parentId the level follows the window
     * nesting (main record, or one level deeper per sidepanel).
     */
    function syncUrl(formId, recordId, parentId) {
        try {
            const topWin = window.top || window;
            if (!topWin.CMA || !topWin.CMA.url) return;

            const cs = topWin.CMA.url.parse();
            const depth = topWin.CMA.url.getDepth();
            const isInSidepanel = window !== topWin;

            if (parentId) {
                if (!isInSidepanel || depth <= 1) {
                    // Subform record under the current main record
                    topWin.CMA.url.update({
                        form: cs.form,
                        recordId: parentId,
                        subform: formId,
                        subformId: recordId,
                        isSubformNew: !recordId
                    }, true);
                } else if (depth === 2) {
                    // Sub-subform record under the open subform record
                    topWin.CMA.url.update({
                        form: cs.form,
                        recordId: cs.recordId,
                        subform: cs.subform,
                        subformId: parentId,
                        subsubform: formId,
                        subsubformId: recordId,
                        isSubsubformNew: !recordId
                    }, true);
                }
                // 4th+ levels: not supported by the URL format
            } else {
                if (!isInSidepanel) {
                    // Main content area - a main form record (level 1)
                    topWin.CMA.url.update({
                        form: formId,
                        recordId: recordId,
                        isNew: !recordId
                    }, true);
                } else if (depth === 1) {
                    // First sidepanel - a subform record (level 2)
                    topWin.CMA.url.update({
                        form: cs.form,
                        recordId: cs.recordId,
                        subform: formId,
                        subformId: recordId,
                        isSubformNew: !recordId
                    }, true);
                } else if (depth === 2) {
                    // Second sidepanel - a sub-subform record (level 3)
                    topWin.CMA.url.update({
                        form: cs.form,
                        recordId: cs.recordId,
                        subform: cs.subform,
                        subformId: cs.subformId,
                        subsubform: formId,
                        subsubformId: recordId,
                        isSubsubformNew: !recordId
                    }, true);
                }
                // 4th+ levels: not supported by the URL format
            }
        } catch (e) {
            if (window.cmaLog) window.cmaLog.warn('[openFormPopup] Could not update URL:', e.message);
        }
    }

    CMA.utils.openFormPopup = function(options) {
        const formId = options.formId;
        const recordId = options.recordId;
        const parentId = options.parentId || null;
        const parentField = options.parentField || '';
        const isCopy = !!options.copy;
        const title = options.title || 'Form';
        const windowName = options.windowName || 'form_popup';
        const onClose = options.onClose || null;
        const cascadeOffset = options.cascadeOffset !== false;
        const hasRecordId = recordId !== null && recordId !== undefined && recordId !== '';

        // Base URL via url-manager (single owner of the form.php query format),
        // then popup-specific extras
        let url = CMA.url.toPageUrl({
            form: formId,
            recordId: hasRecordId ? String(recordId) : null,
            isNew: !hasRecordId
        });
        if (hasRecordId && isCopy) {
            url += '&copy=Y';
        }
        if (parentId) {
            url += '&parentID=' + encodeURIComponent(parentId);
        }
        if (parentField) {
            url += '&parentField=' + encodeURIComponent(parentField);
        }
        // Pass filter context to the popup so new records can inherit the filter field value
        if (options.filterField && options.filterValue) {
            url += '&filterField=' + encodeURIComponent(options.filterField);
            url += '&filterValue=' + encodeURIComponent(options.filterValue);
        }

        // Staat dit scherm al open, dan is er niets te doen — en dan mag de
        // browser-URL ook niet meebewegen. De vraag gaat naar de DOM (welke
        // vensters en panelen staan er open), niet naar een onthouden tijdstip.
        if (lib_IsOpenUrl(url)) {
            return null;
        }

        // Update the browser URL to include the record id (bookmarking/refresh
        // persistence). Not for copies: the URL would deep-link to the source record.
        if (!isCopy) {
            syncUrl(formId, hasRecordId ? recordId : null, parentId);
        }

        // Clear any previous popup check interval to prevent leaks
        cancelWatch();

        // Calculate popup size - 85% of viewport with optional cascade offset
        let width = Math.round(window.innerWidth * 0.85);
        let height = Math.round(window.innerHeight * 0.85);
        if (cascadeOffset && typeof lib_OpenWindowCount === 'function') {
            const openWindows = lib_OpenWindowCount();
            width -= 20 + (50 * openWindows);
            height -= 50 + (75 * openWindows);
        }

        // Check user preference for popup style
        const useSidepanel = lib_getPopupStylePreference() === 'sidepanel';

        if (useSidepanel) {
            // Use sidepanel - opens from the right side
            lib_OpenSidePanel(url, windowName, width, title);
            if (onClose) {
                watchClose(function() {
                    // Must check top.lib_sidepanel_stack because lib_OpenSidePanel adds to
                    // the top window - the local stack is always empty inside an iframe
                    const stack = (window.top || window).lib_sidepanel_stack;
                    return typeof stack === 'undefined' || stack.length === 0;
                }, onClose);
            }
        } else {
            // Use centered popup
            lib_OpenWindowCentered(url, windowName, width, height, title);
            if (onClose) {
                watchClose(function() {
                    // lib_OpenGetTopmostWindow searches __lib_win1..20 in top.document
                    return !(typeof lib_OpenGetTopmostWindow === 'function' && lib_OpenGetTopmostWindow() !== null);
                }, onClose);
            }
        }

        return url;
    };

    CMA.utils.cancelPopupWatch = cancelWatch;
})();

/**
 * Pretty-print HTML with indentation
 * @param {string} html - Raw HTML string
 * @returns {string} - Formatted HTML with newlines and indentation
 */
CMA.utils.formatHtml = function(html) {
    if (!html) return '';
    var indent = 0;
    var result = '';
    var parts = html.replace(/>\s*</g, '>\n<').split('\n');
    for (var i = 0; i < parts.length; i++) {
        var line = parts[i].trim();
        if (!line) continue;
        if (line.match(/^<\//)) indent = Math.max(0, indent - 1);
        result += '  '.repeat(indent) + line + '\n';
        if (line.match(/^<[a-zA-Z]/) && !line.match(/\/>$/) && !line.match(/^<(br|hr|img|input|meta|link)\b/i)) {
            indent++;
        }
    }
    return result;
};

/**
 * Replace all occurrences of a substring (regex-aware).
 * Migrated from CMA.util (cma.js) — kept here under the canonical CMA.utils
 * namespace; CMA.util remains as a back-compat alias.
 */
CMA.utils.replaceString = function(strWhat, strWith, strOrig) {
    while (strOrig.search(strWhat) !== -1) {
        strOrig = strOrig.replace(strWhat, strWith);
    }
    return strOrig;
};

/**
 * Read a cookie value by name.
 * Migrated from CMA.util.
 */
CMA.utils.getCookie = function(name) {
    const dc = document.cookie;
    const prefix = name + '=';
    let begin = dc.indexOf('; ' + prefix);
    if (begin === -1) {
        begin = dc.indexOf(prefix);
        if (begin !== 0) return null;
    } else {
        begin += 2;
    }
    let end = document.cookie.indexOf(';', begin);
    if (end === -1) end = dc.length;
    return unescape(dc.substring(begin + prefix.length, end));
};

/**
 * Set a cookie with a 1-year expiry on path=/.
 * Migrated from CMA.util.
 */
CMA.utils.setCookie = function(name, value) {
    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = name + '=' + escape(value) + ';expires=' + expires.toGMTString() + ';path=/';
};

// Back-compat alias — CMA.util (singular) used to be a separate object with
// its own copies of replaceString/getCookie/setCookie. Some legacy templates
// and inline scripts call CMA.util.x() directly. Aliasing keeps them working
// while CMA.utils is the canonical name going forward.
CMA.util = CMA.utils;

/**
 * Debounce utility - delays function execution until after wait milliseconds
 * have elapsed since the last time the debounced function was invoked.
 *
 * @param {Function} func - Function to debounce
 * @param {number} wait - Milliseconds to wait
 * @param {boolean} immediate - Trigger on leading edge instead of trailing
 * @returns {Function} - Debounced function
 */
window.cmaDebounce = function(func, wait, immediate) {
    let timeout;
    return function() {
        const context = this;
        const args = arguments;
        const later = function() {
            timeout = null;
            if (!immediate) func.apply(context, args);
        };
        const callNow = immediate && !timeout;
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
        if (callNow) func.apply(context, args);
    };
};

// Also expose as CMA_DEBUG and cmaLog vars for backwards compatibility
var CMA_DEBUG = window.CMA_DEBUG;
var cmaLog = window.cmaLog;

/**
 * Enhanced Tooltip System
 *
 * Handles tooltips that CSS pseudo-elements can't:
 * - Input, select, textarea elements
 * - Elements in containers with overflow:hidden (clipping issues)
 *
 * Uses position:fixed to ensure tooltips are always visible.
 * Auto-initializes on DOMContentLoaded.
 */
window.cmaTooltips = (function() {
    'use strict';

    let tooltipEl = null;
    let arrowEl = null;
    let activeElement = null;
    let hideTimeout = null;

    /**
     * Create the tooltip DOM element (singleton)
     */
    function createTooltip() {
        if (tooltipEl) return;

        tooltipEl = document.createElement('div');
        tooltipEl.className = 'cma-tooltip';
        tooltipEl.setAttribute('role', 'tooltip');

        arrowEl = document.createElement('div');
        arrowEl.className = 'cma-tooltip-arrow';
        tooltipEl.appendChild(arrowEl);

        document.body.appendChild(tooltipEl);
    }

    /**
     * Show tooltip for an element
     * @param {HTMLElement} el - Element with data-tooltip attribute
     * @param {number} mouseX - Mouse X coordinate (optional)
     * @param {number} mouseY - Mouse Y coordinate (optional)
     */
    function showTooltip(el, mouseX, mouseY) {
        const text = el.getAttribute('data-tooltip');
        if (!text) return;

        createTooltip();
        clearTimeout(hideTimeout);

        // Set content (text node after arrow)
        const textNode = tooltipEl.lastChild;
        if (textNode && textNode.nodeType === Node.TEXT_NODE) {
            textNode.nodeValue = text;
        } else {
            tooltipEl.appendChild(document.createTextNode(text));
        }

        // Get position preference
        const pos = el.getAttribute('data-tooltip-pos') || 'bottom';

        // Reset classes and arrow position
        tooltipEl.className = 'cma-tooltip pos-' + pos;
        arrowEl.style.left = '';
        arrowEl.style.right = '';

        // Show temporarily to measure
        tooltipEl.style.visibility = 'hidden';
        tooltipEl.classList.add('visible');

        // Calculate position
        const rect = el.getBoundingClientRect();
        const tipRect = tooltipEl.getBoundingClientRect();
        const gap = 10;

        let top, left;
        let actualPos = pos;

        switch (pos) {
            case 'top':
                top = rect.top - tipRect.height - gap;
                left = rect.left + (rect.width - tipRect.width) / 2;
                break;
            case 'right':
                top = rect.top + (rect.height - tipRect.height) / 2;
                left = rect.right + gap;
                break;
            case 'left':
                top = rect.top + (rect.height - tipRect.height) / 2;
                left = rect.left - tipRect.width - gap;
                break;
            case 'bottom':
            default:
                top = rect.bottom + gap;
                left = rect.left + (rect.width - tipRect.width) / 2;
                break;
        }

        // Keep within viewport
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        if (left < 5) left = 5;
        if (left + tipRect.width > viewportWidth - 5) {
            left = viewportWidth - tipRect.width - 5;
        }
        if (top < 5) {
            // Flip to bottom if top would be off-screen
            if (pos === 'top') {
                top = rect.bottom + gap;
                actualPos = 'bottom';
                tooltipEl.className = 'cma-tooltip pos-bottom';
            } else {
                top = 5;
            }
        }
        if (top + tipRect.height > viewportHeight - 5) {
            // Flip to top if bottom would be off-screen
            if (pos === 'bottom') {
                top = rect.top - tipRect.height - gap;
                actualPos = 'top';
                tooltipEl.className = 'cma-tooltip pos-top';
            } else {
                top = viewportHeight - tipRect.height - 5;
            }
        }

        tooltipEl.style.top = top + 'px';
        tooltipEl.style.left = left + 'px';

        // Position arrow to point at cursor (for top/bottom positions)
        if (mouseX !== undefined && (actualPos === 'top' || actualPos === 'bottom')) {
            const arrowX = mouseX - left;
            const minArrow = 12;
            const maxArrow = tipRect.width - 12;
            const clampedArrowX = Math.max(minArrow, Math.min(maxArrow, arrowX));
            arrowEl.style.left = clampedArrowX + 'px';
            arrowEl.style.transform = 'translateX(-50%)';
        }

        tooltipEl.style.visibility = '';

        // Add class to disable CSS pseudo-element tooltip
        el.classList.add('js-tooltip-active');
        activeElement = el;
    }

    /**
     * Hide the tooltip
     */
    function hideTooltip() {
        if (!tooltipEl) return;

        // Remove class from active element
        if (activeElement) {
            activeElement.classList.remove('js-tooltip-active');
        }

        hideTimeout = setTimeout(function() {
            tooltipEl.classList.remove('visible');
            activeElement = null;
        }, 50);
    }

    /**
     * Check if element needs JS tooltip (can't use CSS pseudo-elements)
     * @param {HTMLElement} el
     * @returns {boolean}
     */
    function needsJsTooltip(el) {
        const tagName = el.tagName.toLowerCase();
        // Input, select, textarea can't have pseudo-elements
        if (tagName === 'input' || tagName === 'select' || tagName === 'textarea') {
            return true;
        }
        // Image/video preview button: it's a tiny fixed-size box in tight form
        // layout whose ::before IS its icon, so the CSS ::after tooltip gets
        // clipped / fights the hover restyle ("tooltip doesn't show"). Use the
        // fixed-position JS tooltip, which escapes clipping and doesn't move with
        // any hover layout shift.
        if (el.classList && el.classList.contains('image-preview-btn')) {
            return true;
        }
        // Check if any ancestor has overflow:hidden (causes clipping)
        let parent = el.parentElement;
        while (parent && parent !== document.body) {
            const style = getComputedStyle(parent);
            if (style.overflow === 'hidden' || style.overflowX === 'hidden' || style.overflowY === 'hidden') {
                return true;
            }
            parent = parent.parentElement;
        }
        return false;
    }

    /**
     * Initialize tooltip handlers
     * Call this on DOMContentLoaded or after dynamic content is added
     */
    function init() {
        // Use event delegation for efficiency
        document.addEventListener('mouseenter', function(e) {
            // Ensure target is an Element (not text node, etc.)
            if (!e.target || typeof e.target.closest !== 'function') return;
            const el = e.target.closest('[data-tooltip]');
            if (el && needsJsTooltip(el)) {
                showTooltip(el, e.clientX, e.clientY);
            }
        }, true);

        document.addEventListener('mouseleave', function(e) {
            // Ensure target is an Element (not text node, etc.)
            if (!e.target || typeof e.target.closest !== 'function') return;
            const el = e.target.closest('[data-tooltip]');
            if (el && el === activeElement) {
                hideTooltip();
            }
        }, true);

        // Also hide on scroll to prevent orphaned tooltips
        document.addEventListener('scroll', hideTooltip, true);
    }

    return {
        init: init,
        show: showTooltip,
        hide: hideTooltip
    };
})();

/**
 * Convert title attributes to data-tooltip globally.
 * Runs before tooltip init so all titles become styled tooltips.
 * Also observes DOM for dynamically added elements.
 */
(function() {
    'use strict';

    function shouldSkip(el) {
        return el.tagName === 'IFRAME' || el.tagName === 'SCRIPT';
    }

    function convertTitles(root) {
        var elements = root.querySelectorAll('[title]');
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (shouldSkip(el)) continue;
            var title = el.getAttribute('title');
            if (title && !el.hasAttribute('data-tooltip')) {
                el.setAttribute('data-tooltip', title);
            }
            el.removeAttribute('title');
        }
    }

    function initConversion() {
        convertTitles(document);

        // Observe DOM for dynamically added elements with title
        var observer = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var mutation = mutations[i];
                // Check added nodes
                for (var j = 0; j < mutation.addedNodes.length; j++) {
                    var node = mutation.addedNodes[j];
                    if (node.nodeType === 1) {
                        if (!shouldSkip(node) && node.hasAttribute('title')) {
                            var title = node.getAttribute('title');
                            if (title && !node.hasAttribute('data-tooltip')) {
                                node.setAttribute('data-tooltip', title);
                            }
                            node.removeAttribute('title');
                        }
                        convertTitles(node);
                    }
                }
                // Check attribute changes (title set dynamically)
                if (mutation.type === 'attributes' && mutation.attributeName === 'title') {
                    var target = mutation.target;
                    if (shouldSkip(target)) continue;
                    var val = target.getAttribute('title');
                    if (val) {
                        if (!target.hasAttribute('data-tooltip')) {
                            target.setAttribute('data-tooltip', val);
                        }
                        target.removeAttribute('title');
                    }
                }
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['title']
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            initConversion();
            window.cmaTooltips.init();
        });
    } else {
        initConversion();
        window.cmaTooltips.init();
    }
})();

/**
 * CMA CKEditor Initializer
 *
 * Creates CKEditor instances with CMA's standard configuration.
 * Supports simple (limited toolbar) and full modes.
 *
 * Usage:
 *   CMA.initCKEditor('myTextareaId');                    // Simple toolbar
 *   CMA.initCKEditor('myTextareaId', { mode: 'full' }); // Full toolbar
 *   CMA.initCKEditor('myTextareaId', { height: 200, readOnly: true });
 *
 * Options:
 *   - mode: 'simple' (default) or 'full'
 *   - height: Editor height in pixels (default: 300)
 *   - readOnly: Make editor read-only (default: false)
 *   - contentsCss: Custom CSS for editor content
 *   - onChange: Callback function(editor) called on content change
 */
window.CMA = window.CMA || {};
window.CMA.initCKEditor = function(fieldId, options) {
    'use strict';

    if (typeof CKEDITOR === 'undefined') {
        cmaLog.error('[CMA.initCKEditor] CKEDITOR is not loaded');
        return null;
    }

    if (!fieldId) {
        cmaLog.error('[CMA.initCKEditor] fieldId is required');
        return null;
    }

    // Don't create duplicate instances
    if (CKEDITOR.instances[fieldId]) {
        return CKEDITOR.instances[fieldId];
    }

    options = options || {};
    const mode = options.mode || 'simple';
    const height = options.height || 300;

    const config = {
        language: 'nl',
        contentsLanguage: 'nl',
        defaultLanguage: 'nl',
        scayt_sLang: 'nl_NL',
        skin: 'office2013_modified',
        height: height + 'px',
        resize_enabled: false,
        pasteFromWordPromptCleanup: true,
        scayt_autoStartup: false,
        allowedContent: true,
        extraAllowedContent: '*(*){*}[*]',
        entities: true,
        basicEntities: true,
        latinEntities: true,
        greekEntities: true,
        toolbar: 'Full'
    };

    if (mode === 'simple') {
        config.toolbar_Full = [
            { name: 'basic', items: ['Cut', 'Copy', 'Paste', 'PasteText', '-', 'Find', 'Replace', '-', 'Undo', 'Redo', '-', 'Bold', 'Italic', '-', 'Styles', '-', 'BulletedList', 'NumberedList', '-', 'myRemoveFormat', '-', 'Image', '-', 'Link', 'Unlink', 'Source', 'myMaximize'] }
        ];
        config.extraPlugins = 'myMaximize,myRemoveFormat,stylescombo';
    } else {
        config.extraAllowedContent = 'script; area(*){*}[*]; table(*){*}[*]; h1; h2; h3; i; td(*){*}[*]; form(*){*}[*]; iframe(*){*}[*]; input(*){*}[*]; map(*){*}[*]; button(*){*}[*]; textarea(*){*}[*]; hr(*){*}[*]; tr(*){*}[*]; div(*){*}[*]; span(*){*}[*]; a(*){*}[*]; style(*){*}[*]; img(*){*}[*]; select(*){*}[*]; option(*){*}[*]; object(*){*}[*]; embed(*){*}[*]';
        config.extraPlugins = 'myMaximize,stylescombo,quicktable,imgtitle,videodetector,myRemoveFormat';
        config.startupOutlineShy = true;
        config.startupShowBorders = true;
        config.toolbarCanCollapse = false;
        config.toolbar_Full = [
            { name: 'basic', items: ['Cut', 'Copy', 'Paste', 'PasteText', '-', 'Find', 'Replace', '-', 'Undo', 'Redo', '-', 'Scayt', '-', 'Bold', 'Italic', 'Underline', '-', 'Styles'] },
            { name: 'paragraph', items: ['BulletedList', 'NumberedList', '-', 'myRemoveFormat', '-', 'Outdent', 'Indent', '-', 'JustifyLeft', 'JustifyCenter', 'JustifyRight', 'JustifyBlock', '-'] },
            { name: 'links', items: ['Link', 'Unlink'] },
            { name: 'insert', items: ['-', 'VideoDetector', 'Image', 'imgtitle', 'Table', 'SpecialChar', 'HorizontalRule'] },
            { name: 'tools', items: ['Source', 'myMaximize'] }
        ];
        config.qtBorder = '1';
        config.qtCellPadding = '4';
        config.qtCellSpacing = '0';
        config.qtStyle = { 'border-collapse': 'collapse', 'border': '1px solid #cccccc' };
        config.qtClass = 'cke_show_border';
        config.qtWidth = '100%';
    }

    // Register custom styles (once)
    if (!CKEDITOR.stylesSet.registered || !CKEDITOR.stylesSet.registered['my_styles']) {
        try {
            CKEDITOR.stylesSet.add('my_styles', [
                { name: 'Titel', element: 'h3', attributes: { 'class': 'sectionTitle__title' } },
                { name: 'SubTitel', element: 'h4', attributes: { 'class': 'sectionSubTitle__title' } }
            ]);
            CKEDITOR.stylesSet.registered = CKEDITOR.stylesSet.registered || {};
            CKEDITOR.stylesSet.registered['my_styles'] = true;
        } catch (e) {
            cmaLog.warn('Could not register CKEditor styles:', e);
        }
    }
    config.stylesSet = 'my_styles';

    // Apply custom options
    if (options.contentsCss) config.contentsCss = options.contentsCss;
    if (options.readOnly) config.readOnly = true;

    cmaLog.log('[CMA.initCKEditor] Creating', mode, 'editor for:', fieldId);

    try {
        var editor = CKEDITOR.replace(fieldId, config);
    } catch (e) {
        cmaLog.error('[CMA.initCKEditor] Failed for:', fieldId, e.message);
        return null;
    }

    // Wire up onChange callback
    if (options.onChange && editor) {
        editor.on('instanceReady', function() {
            editor.on('change', function() {
                options.onChange(editor);
            });
        });
    }

    // Apply dark mode to iframe content
    if (editor) {
        editor.on('instanceReady', function() {
            CMA._applyCKEditorDarkMode(editor);
        });
    }

    return editor;
};

/**
 * Apply/remove dark mode styling inside a CKEditor iframe.
 * Since CSS variables don't cross iframe boundaries, we inject
 * a <style> element with actual color values into the iframe document.
 */
window.CMA._ckeditorDarkCSS =
    'body { color: #e0e0e0 !important; background-color: #222222 !important; }' +
    'a { color: #7ac4f5 !important; }' +
    'a > img { outline-color: #7ac4f5 !important; }' +
    'hr { border-top-color: #444 !important; }' +
    'blockquote { border-color: #444 !important; }' +
    'img.right, img.left { border-color: #444 !important; }' +
    'figure { border-color: #444 !important; background: rgba(255,255,255,0.05) !important; }' +
    '.marker { background-color: #665500 !important; color: #ffd700 !important; }' +
    'table, td, th { border-color: #444 !important; }';

window.CMA._applyCKEditorDarkMode = function(editor) {
    if (!editor || !editor.document) return;

    var doc = editor.document.$;
    var isDark = document.documentElement.classList.contains('dark-mode');
    var styleId = 'cke-dark-mode-style';
    var existing = doc.getElementById(styleId);

    if (isDark && !existing) {
        var style = doc.createElement('style');
        style.id = styleId;
        style.textContent = CMA._ckeditorDarkCSS;
        doc.head.appendChild(style);
    } else if (!isDark && existing) {
        existing.remove();
    }
};

/**
 * Update dark mode for all active CKEditor instances.
 * Called when dark mode is toggled.
 */
window.CMA._updateAllCKEditorsDarkMode = function() {
    if (typeof CKEDITOR === 'undefined') return;
    for (var name in CKEDITOR.instances) {
        if (CKEDITOR.instances.hasOwnProperty(name)) {
            CMA._applyCKEditorDarkMode(CKEDITOR.instances[name]);
        }
    }
};

// Watch for dark mode class changes on <html> and update CKEditor iframes
(function() {
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            if (m.attributeName === 'class') {
                CMA._updateAllCKEditorsDarkMode();
            }
        });
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();

// Global handler: apply dark mode to any CKEditor instance (including legacy ones)
// CKEDITOR may load after this script, so try now and also on DOMContentLoaded
(function registerCKEditorDarkModeHandler() {
    if (typeof CKEDITOR !== 'undefined') {
        CKEDITOR.on('instanceReady', function(evt) {
            CMA._applyCKEditorDarkMode(evt.editor);
        });
    } else {
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof CKEDITOR !== 'undefined') {
                CKEDITOR.on('instanceReady', function(evt) {
                    CMA._applyCKEditorDarkMode(evt.editor);
                });
            }
        });
    }
})();

} // end if CMA_DEBUG undefined
;
