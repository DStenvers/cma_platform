/**
 * CMA Form Controller
 *
 * Manages form interactions via AJAX:
 * - List data loading and rendering
 * - Record loading and form population
 * - Form submission
 * - Subform handling
 * - Loading animations
 */

// Guard against double-execution (can happen when loaded via both bundle and lazy-loader)
// Check CMA.FormController (the exported reference) to avoid TDZ error with class declaration
if (window.CMA && window.CMA.FormController) {
    (window.cmaLog || console).warn('[form-controller.js] Already loaded (CMA.FormController exists), skipping re-execution');
}

// CMA_DEBUG and cmaLog are now defined in cma-utils.js (loaded first)
// This guard ensures backwards compatibility if cma-utils.js hasn't loaded
if (typeof CMA_DEBUG === 'undefined') {
    var CMA_DEBUG = false;
    var cmaLog = { log: function(){}, warn: function(){}, error: console.error };
}

// =============================================================================
// UTILITY MODULES
// These are now available as separate ES6 modules in /assets/js/modules/
// If modules are pre-loaded, use them; otherwise define inline for backward compat
// =============================================================================

// cmaApiError - API error handler
if (typeof window.cmaApiError === 'undefined') {
    var cmaApiError = {
        extractPhpError: function(html) {
            return window.cmaErrorParser ? window.cmaErrorParser.extract(html) : null;
        },
        handleResponse: async function(response, context = 'API call') {
            if (!response.ok) {
                const contentType = response.headers.get('content-type') || '';
                if (contentType.includes('application/json')) {
                    try {
                        const errorData = await response.json();
                        const error = new Error(errorData.error || `HTTP ${response.status}`);
                        error.statusCode = response.status;
                        error.errorType = errorData.errorType;
                        error.debug = errorData.debug;
                        throw error;
                    } catch (parseError) {
                        if (parseError.statusCode) throw parseError;
                        throw new Error(`${context}: HTTP ${response.status} ${response.statusText}`);
                    }
                } else {
                    let responseText = '';
                    try { responseText = await response.text(); } catch (e) { responseText = '[Response body unreadable]'; }
                    const phpError = this.extractPhpError(responseText);
                    if (phpError) {
                        const error = new Error(phpError.message);
                        error.statusCode = response.status;
                        error.errorType = phpError.type;
                        error.debug = { file: phpError.file, line: phpError.line };
                        throw error;
                    }
                    const error = new Error(`${context}: HTTP ${response.status} ${response.statusText}`);
                    error.statusCode = response.status;
                    error.responseText = responseText;
                    throw error;
                }
            }
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                let responseText = '';
                try { responseText = await response.text(); } catch (e) { responseText = '[Response body unreadable]'; }
                const phpError = this.extractPhpError(responseText);
                if (phpError) {
                    const error = new Error(phpError.message);
                    error.statusCode = 200;
                    error.errorType = phpError.type;
                    error.debug = { file: phpError.file, line: phpError.line };
                    throw error;
                }
                const error = new Error(`${context}: Server returned non-JSON response`);
                error.responseText = responseText;
                throw error;
            }
            return response.json();
        },
        formatError: function(error) {
            let message = error.message || 'Onbekende fout';
            if (CMA_DEBUG && error.debug) {
                const debug = error.debug;
                let details = [];
                if (debug.file) {
                    const shortFile = debug.file.split(/[\/\\]/).slice(-3).join('/');
                    details.push(`Bestand: ${shortFile}:${debug.line || '?'}`);
                }
                if (debug.diagnostics && debug.diagnostics.likelyCauses && debug.diagnostics.likelyCauses.length > 0) {
                    details.push('Mogelijke oorzaak: ' + debug.diagnostics.likelyCauses[0]);
                }
                if (details.length > 0) message += '\n\n' + details.join('\n');
            }
            return message;
        },
        showError: function(error, context = '') {
            const formattedMessage = this.formatError(error);
            cmaLog.error(`[API Error] ${context}:`, error.message);
            if (error.debug) cmaLog.error('Debug info:', error.debug);
            if (error.responseText) cmaLog.error('Response text (first 500 chars):', error.responseText.substring(0, 500));
            let details = '';
            if (window.CMA?.formConfig?.showDetails) {
                const detailParts = [];
                if (context) detailParts.push('Context: ' + context);
                if (error.errorType) detailParts.push('Type: ' + error.errorType);
                if (error.statusCode) detailParts.push('HTTP Status: ' + error.statusCode);
                if (error.debug) {
                    if (error.debug.file) detailParts.push('File: ' + error.debug.file);
                    if (error.debug.line) detailParts.push('Line: ' + error.debug.line);
                    if (error.debug.trace) detailParts.push('Trace:\n' + error.debug.trace);
                }
                if (error.responseText) detailParts.push('Response (first 500 chars):\n' + error.responseText.substring(0, 500));
                details = detailParts.join('\n');
            }
            if (typeof cmaNotification !== 'undefined') {
                cmaNotification.show(formattedMessage, 'error');
            } else if (typeof libMessage !== 'undefined') {
                const options = { type: 'error', closable: true };
                if (details) options.details = details;
                libMessage.create(formattedMessage, options);
            } else {
                libAlert(formattedMessage);
            }
            return formattedMessage;
        }
    };
} else {
    var cmaApiError = window.cmaApiError;
}

// cmaPerf - Performance monitoring (now available from modules/cma-perf.js)
if (typeof window.cmaPerf === 'undefined') {
    var cmaPerf = (function() {
    // Storage for timers, counters, and metrics
    var timers = {};
    var counters = {};
    var gauges = {};
    var measurements = [];
    // Use CMA_CONSOLE_LOGGING (respects user preference cookie) instead of just CMA_DEBUG
    var enabled = typeof window.CMA_CONSOLE_LOGGING !== 'undefined' ? window.CMA_CONSOLE_LOGGING : CMA_DEBUG;

    // Performance color coding for console
    var colors = {
        fast: 'color: #28a745; font-weight: bold',      // < 100ms
        medium: 'color: #ffc107; font-weight: bold',    // 100-500ms
        slow: 'color: #dc3545; font-weight: bold',      // > 500ms
        label: 'color: #6c757d',
        value: 'color: #007bff; font-weight: bold',
        header: 'color: #fff; background: #343a40; padding: 2px 6px; border-radius: 3px',
        subheader: 'color: #495057; font-weight: bold; border-bottom: 1px solid #dee2e6'
    };

    function getSpeedColor(ms) {
        if (ms < 100) return colors.fast;
        if (ms < 500) return colors.medium;
        return colors.slow;
    }

    function formatMs(ms) {
        if (ms < 1) return ms.toFixed(3) + 'ms';
        if (ms < 1000) return ms.toFixed(1) + 'ms';
        return (ms / 1000).toFixed(2) + 's';
    }

    function getTimestamp() {
        return performance.now();
    }

    /**
     * Check if server-side performance logging is enabled via cookie
     * @returns {boolean}
     */
    function isServerLoggingEnabled() {
        var match = document.cookie.match(/(?:^|; )cma_perf_logging=([^;]*)/);
        return match && match[1] === 'J';
    }

    return {
        /**
         * Check if performance monitoring is enabled
         */
        isEnabled: function() {
            return enabled;
        },

        /**
         * Enable/disable performance monitoring
         */
        setEnabled: function(value) {
            enabled = !!value;
            if (window.CMA_CONSOLE_LOGGING) {
                console.log('%c[CMA Perf] ' + (enabled ? 'Enabled' : 'Disabled'), colors.header);
            }
        },

        /**
         * Start a named timer
         * @param {string} label - Timer name
         * @param {object} meta - Optional metadata to attach
         */
        start: function(label, meta) {
            if (!enabled) return;
            timers[label] = {
                start: getTimestamp(),
                meta: meta || {}
            };
        },

        /**
         * End a named timer and log the duration
         * @param {string} label - Timer name
         * @param {object} extraMeta - Additional metadata to merge
         * @returns {number} Duration in milliseconds
         */
        end: function(label, extraMeta) {
            if (!enabled) return 0;
            var timer = timers[label];
            if (!timer) {
                cmaLog.warn('[CMA Perf] Timer not found:', label);
                return 0;
            }

            var duration = getTimestamp() - timer.start;
            var meta = Object.assign({}, timer.meta, extraMeta || {});

            // Store measurement
            measurements.push({
                label: label,
                duration: duration,
                meta: meta,
                timestamp: Date.now()
            });

            // Log with color coding
            var metaStr = Object.keys(meta).length > 0
                ? ' ' + JSON.stringify(meta)
                : '';
            console.log(
                '%c[Perf] %c' + label + '%c ' + formatMs(duration) + metaStr,
                colors.label,
                getSpeedColor(duration),
                colors.label
            );

            delete timers[label];
            return duration;
        },

        /**
         * Create a performance mark (uses Performance API)
         * @param {string} name - Mark name
         */
        mark: function(name) {
            if (!enabled) return;
            try {
                performance.mark('cma_' + name);
            } catch (e) {
                // Fallback for older browsers
                timers['mark_' + name] = { start: getTimestamp() };
            }
        },

        /**
         * Measure between two marks
         * @param {string} name - Measurement name
         * @param {string} startMark - Start mark name
         * @param {string} endMark - End mark name
         */
        measure: function(name, startMark, endMark) {
            if (!enabled) return;
            try {
                performance.measure('cma_' + name, 'cma_' + startMark, 'cma_' + endMark);
                var entries = performance.getEntriesByName('cma_' + name, 'measure');
                if (entries.length > 0) {
                    var duration = entries[entries.length - 1].duration;
                    console.log(
                        '%c[Perf] %c' + name + '%c ' + formatMs(duration),
                        colors.label,
                        getSpeedColor(duration),
                        colors.label
                    );
                }
            } catch (e) {
                // Fallback
                var start = timers['mark_' + startMark];
                var end = timers['mark_' + endMark];
                if (start && end) {
                    console.log('[Perf] ' + name + ': ' + formatMs(end.start - start.start));
                }
            }
        },

        /**
         * Increment a counter
         * @param {string} name - Counter name
         * @param {number} amount - Amount to increment (default 1)
         */
        count: function(name, amount) {
            if (!enabled) return;
            counters[name] = (counters[name] || 0) + (amount || 1);
        },

        /**
         * Set a gauge value
         * @param {string} name - Gauge name
         * @param {number} value - Value to set
         */
        gauge: function(name, value) {
            if (!enabled) return;
            gauges[name] = value;
        },

        /**
         * Log a group of related timing data
         * @param {string} groupName - Group name
         * @param {object} timings - Object with timing values in ms
         */
        group: function(groupName, timings) {
            if (!enabled) return;
            console.groupCollapsed('%c[Perf] ' + groupName, colors.header);
            for (var key in timings) {
                if (timings.hasOwnProperty(key)) {
                    var value = timings[key];
                    if (typeof value === 'number') {
                        console.log(
                            '%c  ' + key + ': %c' + formatMs(value),
                            colors.label,
                            getSpeedColor(value)
                        );
                    } else {
                        console.log('%c  ' + key + ': %c' + value, colors.label, colors.value);
                    }
                }
            }
            console.groupEnd();
        },

        /**
         * Log server timing from API response
         * @param {string} endpoint - API endpoint name
         * @param {object} timing - Server timing object
         */
        serverTiming: function(endpoint, timing) {
            if (!enabled || !timing) return;
            console.groupCollapsed('%c[Server] ' + endpoint, colors.header);
            if (timing.total !== undefined) {
                console.log('%c  Total: %c' + formatMs(timing.total), colors.label, getSpeedColor(timing.total));
            }
            if (timing.query !== undefined) {
                console.log('%c  Query: %c' + formatMs(timing.query), colors.label, getSpeedColor(timing.query));
            }
            if (timing.render !== undefined) {
                console.log('%c  Render: %c' + formatMs(timing.render), colors.label, getSpeedColor(timing.render));
            }
            if (timing.rows !== undefined) {
                console.log('%c  Rows: %c' + timing.rows, colors.label, colors.value);
            }
            if (timing.sql) {
                console.log('%c  SQL: %c' + timing.sql, colors.label, 'color: #6c757d; font-style: italic');
            }
            for (var key in timing) {
                if (!['total', 'query', 'render', 'rows', 'sql'].includes(key)) {
                    console.log('%c  ' + key + ': %c' + timing[key], colors.label, colors.value);
                }
            }
            console.groupEnd();
        },

        /**
         * Show summary of all collected metrics
         */
        summary: function() {
            console.log('%c═══════════════════════════════════════════════════════', colors.header);
            console.log('%c CMA Performance Summary', colors.header);
            console.log('%c═══════════════════════════════════════════════════════', colors.header);

            // Measurements summary
            if (measurements.length > 0) {
                console.log('%c\n📊 Recent Timings (' + measurements.length + ' measurements)', colors.subheader);

                // Group by label and calculate averages
                var byLabel = {};
                measurements.forEach(function(m) {
                    if (!byLabel[m.label]) {
                        byLabel[m.label] = { count: 0, total: 0, min: Infinity, max: 0 };
                    }
                    byLabel[m.label].count++;
                    byLabel[m.label].total += m.duration;
                    byLabel[m.label].min = Math.min(byLabel[m.label].min, m.duration);
                    byLabel[m.label].max = Math.max(byLabel[m.label].max, m.duration);
                });

                // Sort by total time descending
                var sorted = Object.keys(byLabel).sort(function(a, b) {
                    return byLabel[b].total - byLabel[a].total;
                });

                sorted.forEach(function(label) {
                    var stats = byLabel[label];
                    var avg = stats.total / stats.count;
                    console.log(
                        '%c  ' + label + ': %c' + formatMs(avg) + ' avg' +
                        '%c (min: ' + formatMs(stats.min) + ', max: ' + formatMs(stats.max) + ', count: ' + stats.count + ')',
                        colors.label,
                        getSpeedColor(avg),
                        colors.label
                    );
                });
            }

            // Counters
            if (Object.keys(counters).length > 0) {
                console.log('%c\n🔢 Counters', colors.subheader);
                for (var name in counters) {
                    console.log('%c  ' + name + ': %c' + counters[name], colors.label, colors.value);
                }
            }

            // Gauges
            if (Object.keys(gauges).length > 0) {
                console.log('%c\n📈 Gauges', colors.subheader);
                for (var name in gauges) {
                    console.log('%c  ' + name + ': %c' + gauges[name], colors.label, colors.value);
                }
            }

            // Active timers (possible leaks)
            var activeTimers = Object.keys(timers);
            if (activeTimers.length > 0) {
                console.log('%c\n⚠️ Active Timers (not ended)', 'color: #ffc107; font-weight: bold');
                activeTimers.forEach(function(label) {
                    var elapsed = getTimestamp() - timers[label].start;
                    console.log('%c  ' + label + ': %c' + formatMs(elapsed) + ' (running)', colors.label, colors.slow);
                });
            }

            console.log('%c\n═══════════════════════════════════════════════════════', colors.header);
        },

        /**
         * Clear all collected metrics
         */
        clear: function() {
            timers = {};
            counters = {};
            gauges = {};
            measurements = [];
            if (window.CMA_CONSOLE_LOGGING) {
                console.log('%c[CMA Perf] Metrics cleared', colors.header);
            }
        },

        /**
         * Get raw measurements data
         */
        getData: function() {
            return {
                measurements: measurements.slice(),
                counters: Object.assign({}, counters),
                gauges: Object.assign({}, gauges)
            };
        },

        /**
         * Send performance data to server for file logging
         * @param {boolean} clearAfterSend - Whether to clear metrics after sending
         * @returns {Promise} - Resolves when data is sent
         */
        sendToServer: function(clearAfterSend) {
            // Check if server-side logging is enabled
            if (!isServerLoggingEnabled()) {
                if (window.CMA_CONSOLE_LOGGING) {
                    console.log('%c[CMA Perf] Server logging disabled (enable in Preferences)', colors.label);
                }
                return Promise.resolve();
            }

            if (measurements.length === 0 && Object.keys(counters).length === 0 && Object.keys(gauges).length === 0) {
                if (window.CMA_CONSOLE_LOGGING) {
                    console.log('%c[CMA Perf] No data to send', colors.label);
                }
                return Promise.resolve();
            }

            var data = this.getData();
            data.page = window.location.pathname;
            data.timestamp = Date.now();
            data.userAgent = navigator.userAgent;

            var formData = new FormData();
            formData.append('action', 'logPerformance');
            formData.append('data', JSON.stringify(data));

            var self = this;
            return fetch('/cma/form_api.php', {
                method: 'POST',
                body: formData
            }).then(function(response) {
                if (response.ok) {
                    if (window.CMA_CONSOLE_LOGGING) {
                        console.log('%c[CMA Perf] Data sent to server (' + measurements.length + ' measurements)', colors.header);
                    }
                    if (clearAfterSend) {
                        self.clear();
                    }
                } else {
                    cmaLog.warn('[CMA Perf] Failed to send data:', response.status);
                }
                return response;
            }).catch(function(err) {
                cmaLog.warn('[CMA Perf] Error sending data:', err);
            });
        },

        /**
         * Auto-send performance data before page unload
         * Call this to enable automatic logging
         */
        enableAutoLog: function() {
            var self = this;
            window.addEventListener('beforeunload', function() {
                // Check if server-side logging is enabled
                if (!isServerLoggingEnabled()) {
                    return;
                }

                // Use sendBeacon for reliable delivery during unload
                if (navigator.sendBeacon && measurements.length > 0) {
                    var data = self.getData();
                    data.page = window.location.pathname;
                    data.timestamp = Date.now();
                    data.userAgent = navigator.userAgent;

                    var formData = new FormData();
                    formData.append('action', 'logPerformance');
                    formData.append('data', JSON.stringify(data));
                    navigator.sendBeacon('/cma/form_api.php', formData);
                }
            });
            if (window.CMA_CONSOLE_LOGGING) {
                console.log('%c[CMA Perf] Auto-logging enabled (sends on page unload)', colors.header);
            }
        }
    };
})();
} else {
    var cmaPerf = window.cmaPerf;
}

// cmaComboCache - Combo options cache (now available from modules/cma-combo-cache.js)
if (typeof window.cmaComboCache === 'undefined') {
    var cmaComboCache = (function() {
    var CACHE_PREFIX = 'cma_combo_';
    var CACHE_TTL = 5 * 60 * 1000; // 5 minutes in milliseconds
    var CACHE_VERSION_KEY = 'cma_combo_version';
    var CACHE_VERSION = '4'; // Increment to invalidate all caches

    /**
     * Check if sessionStorage is available
     */
    function isAvailable() {
        try {
            var test = '__cma_test__';
            sessionStorage.setItem(test, test);
            sessionStorage.removeItem(test);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Clear all combo caches (internal function)
     */
    function clearCache() {
        if (!isAvailable()) return;

        var keysToRemove = [];
        for (var i = 0; i < sessionStorage.length; i++) {
            var key = sessionStorage.key(i);
            if (key && key.indexOf(CACHE_PREFIX) === 0) {
                keysToRemove.push(key);
            }
        }

        keysToRemove.forEach(function(key) {
            sessionStorage.removeItem(key);
        });

        // cmaLog.log('[ComboCache] Cleared all caches (' + keysToRemove.length + ' entries)');
    }

    /**
     * Check and clear cache if version changed
     */
    function checkVersion() {
        if (!isAvailable()) return;
        var storedVersion = sessionStorage.getItem(CACHE_VERSION_KEY);
        if (storedVersion !== CACHE_VERSION) {
            clearCache();
            sessionStorage.setItem(CACHE_VERSION_KEY, CACHE_VERSION);
        }
    }

    // Check version on load
    checkVersion();

    return {
        /**
         * Build cache key
         * @param {string} formId - Form ID
         * @param {string} field - Field name
         * @param {string|null} recordId - Optional record ID for record-dependent combos
         */
        buildKey: function(formId, field, recordId) {
            var key = CACHE_PREFIX + formId + '_' + field;
            if (recordId) {
                key += '_' + recordId;
            }
            return key;
        },

        /**
         * Get cached combo data
         * @param {string} formId - Form ID
         * @param {string} field - Field name
         * @param {string|null} recordId - Optional record ID for record-dependent combos
         * @returns {Array|null} - Cached options or null if not found/expired
         */
        get: function(formId, field, recordId) {
            if (!isAvailable()) return null;

            var key = this.buildKey(formId, field, recordId);
            try {
                var cached = sessionStorage.getItem(key);
                if (!cached) return null;

                var data = JSON.parse(cached);
                if (Date.now() > data.expires) {
                    sessionStorage.removeItem(key);
                    return null;
                }

                // cmaLog.log('[ComboCache] HIT:', field, recordId ? '(record:' + recordId + ')' : '');
                return data.options;
            } catch (e) {
                return null;
            }
        },

        /**
         * Get cached data for multiple fields
         * @param {string} formId - Form ID
         * @param {Array} fields - Array of field names
         * @param {string|null} recordId - Optional record ID for record-dependent combos
         * @returns {Object} - { cached: {fieldName: options}, uncached: [fieldName] }
         */
        getMultiple: function(formId, fields, recordId) {
            var result = { cached: {}, uncached: [] };

            for (var i = 0; i < fields.length; i++) {
                var field = fields[i];
                var options = this.get(formId, field, recordId);
                if (options !== null) {
                    result.cached[field] = options;
                } else {
                    result.uncached.push(field);
                }
            }

            if (Object.keys(result.cached).length > 0) {
                // cmaLog.log('[ComboCache] Batch hit:', Object.keys(result.cached).length, 'cached,', result.uncached.length, 'uncached');
            }

            return result;
        },

        /**
         * Store combo data in cache
         * @param {string} formId - Form ID
         * @param {string} field - Field name
         * @param {Array} options - Options array
         * @param {string|null} recordId - Optional record ID for record-dependent combos
         */
        set: function(formId, field, options, recordId) {
            if (!isAvailable()) return;

            var key = this.buildKey(formId, field, recordId);
            try {
                var data = {
                    options: options,
                    expires: Date.now() + CACHE_TTL
                };
                sessionStorage.setItem(key, JSON.stringify(data));
                // cmaLog.log('[ComboCache] SET:', field, '(' + options.length + ' options)', recordId ? '(record:' + recordId + ')' : '');
            } catch (e) {
                // Storage full - clear old entries
                this.cleanup();
            }
        },

        /**
         * Store multiple combo data at once
         * @param {string} formId - Form ID
         * @param {Object} combos - { fieldName: options[] }
         * @param {string|null} recordId - Optional record ID for record-dependent combos
         */
        setMultiple: function(formId, combos, recordId) {
            for (var field in combos) {
                if (combos.hasOwnProperty(field)) {
                    this.set(formId, field, combos[field], recordId);
                }
            }
        },

        /**
         * Invalidate cache for a specific form
         * @param {string} formId - Form ID
         */
        invalidateForm: function(formId) {
            if (!isAvailable()) return;

            var prefix = CACHE_PREFIX + formId + '_';
            var keysToRemove = [];

            for (var i = 0; i < sessionStorage.length; i++) {
                var key = sessionStorage.key(i);
                if (key && key.indexOf(prefix) === 0) {
                    keysToRemove.push(key);
                }
            }

            keysToRemove.forEach(function(key) {
                sessionStorage.removeItem(key);
            });

            // cmaLog.log('[ComboCache] Invalidated form:', formId, '(' + keysToRemove.length + ' entries)');
        },

        /**
         * Clear all combo caches
         */
        clear: clearCache,

        /**
         * Remove expired entries to free up space
         */
        cleanup: function() {
            if (!isAvailable()) return;

            var now = Date.now();
            var keysToRemove = [];

            for (var i = 0; i < sessionStorage.length; i++) {
                var key = sessionStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0) {
                    try {
                        var data = JSON.parse(sessionStorage.getItem(key));
                        if (now > data.expires) {
                            keysToRemove.push(key);
                        }
                    } catch (e) {
                        keysToRemove.push(key);
                    }
                }
            }

            keysToRemove.forEach(function(key) {
                sessionStorage.removeItem(key);
            });

            if (keysToRemove.length > 0) {
                // cmaLog.log('[ComboCache] Cleanup removed', keysToRemove.length, 'expired entries');
            }
        },

        /**
         * Get cache statistics
         */
        stats: function() {
            if (!isAvailable()) return { entries: 0, size: 0 };

            var entries = 0;
            var size = 0;

            for (var i = 0; i < sessionStorage.length; i++) {
                var key = sessionStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0) {
                    entries++;
                    size += sessionStorage.getItem(key).length;
                }
            }

            return {
                entries: entries,
                size: Math.round(size / 1024) + 'KB'
            };
        }
    };
})();
} else {
    var cmaComboCache = window.cmaComboCache;
}

// cmaRequestCoalescer - Prevents duplicate in-flight requests (now available from modules/cma-request-coalescer.js)
if (typeof window.cmaRequestCoalescer === 'undefined') {
    var cmaRequestCoalescer = (function() {
    // Map of URL -> { promise, timestamp }
    var inFlight = new Map();
    var MAX_AGE = 5000; // Max time to keep a coalesced request (5 seconds)

    return {
        /**
         * Fetch with request coalescing
         * @param {string} url - URL to fetch
         * @param {object} options - Fetch options (optional)
         * @returns {Promise<{response: Response, data: any}>} - Response with parsed data
         */
        fetch: function(url, options) {
            var key = url + (options ? JSON.stringify(options) : '');

            // Check if we have an in-flight request for this URL
            var existing = inFlight.get(key);
            if (existing && (Date.now() - existing.timestamp) < MAX_AGE) {
                // cmaLog.log('[Coalescer] Reusing in-flight request:', url.substring(0, 60));
                cmaPerf.count('requestCoalescer.coalesced');
                return existing.promise;
            }

            // Create new request with proper error handling
            var promise = fetch(url, options)
                .then(function(response) {
                    if (!response.ok) {
                        var error = new Error('[Coalescer] HTTP ' + response.status + ' ' + response.statusText);
                        error.statusCode = response.status;
                        throw error;
                    }
                    return response.json().then(function(data) {
                        return { response: response, data: data };
                    }).catch(function(parseError) {
                        cmaLog.error('[Coalescer] JSON parse error:', parseError.message);
                        throw new Error('[Coalescer] Invalid JSON response from server');
                    });
                })
                .catch(function(error) {
                    cmaLog.error('[Coalescer] Request failed:', url.substring(0, 60), error.message);
                    // Re-throw so callers can handle it
                    throw error;
                })
                .finally(function() {
                    // Remove from in-flight after short delay (allows for rapid sequential calls)
                    setTimeout(function() {
                        inFlight.delete(key);
                    }, 100);
                });

            inFlight.set(key, { promise: promise, timestamp: Date.now() });
            cmaPerf.count('requestCoalescer.newRequest');

            return promise;
        },

        /**
         * Clear all in-flight requests
         */
        clear: function() {
            inFlight.clear();
        },

        /**
         * Get stats
         */
        stats: function() {
            return { inFlight: inFlight.size };
        }
    };
})();
} else {
    var cmaRequestCoalescer = window.cmaRequestCoalescer;
}

// cmaNotification - Toast notifications using lib-toaster component
if (typeof window.cmaNotification === 'undefined') {
    var cmaNotification = {
        show: function(message, type = 'info') {
            if (typeof libToast !== 'undefined' && libToast[type]) {
                libToast[type](message);
            } else if (typeof libToast !== 'undefined') {
                libToast.info(message);
            }
        },
        success: function(message) { if (typeof libToast !== 'undefined') libToast.success(message); },
        error: function(message) { if (typeof libToast !== 'undefined') libToast.error(message); },
        warning: function(message) { if (typeof libToast !== 'undefined') libToast.warning(message); },
        info: function(message) { if (typeof libToast !== 'undefined') libToast.info(message); }
    };
} else {
    var cmaNotification = window.cmaNotification;
}

/**
 * Capitalize first character of string
 * @param {string} str - Input string
 * @returns {string} String with first character capitalized
 */
function toFirstCaps(str) {
    if (!str) return str;
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

// =============================================================================
// DOM STATE HELPERS
// All form state is stored in DOM data attributes - no instance properties
// =============================================================================

/**
 * Get current record ID from DOM data attribute
 * @param {Element} [element] - Optional element to scope to nearest .form-layout
 * @returns {string|null} Record ID or null
 */
function cmaGetRecordId(element) {
    const formLayout = element ? element.closest('.form-layout') : document.querySelector('.form-layout');
    return formLayout?.dataset.recordId || null;
}

/**
 * Set current record ID in DOM data attribute
 * @param {string|number|null} value - Record ID to set
 * @param {Element} [element] - Optional element to scope to nearest .form-layout
 */
function cmaSetRecordId(value, element) {
    const formLayout = element ? element.closest('.form-layout') : document.querySelector('.form-layout');
    if (formLayout) {
        if (value !== null && value !== undefined && value !== '') {
            formLayout.dataset.recordId = String(value);
        } else {
            delete formLayout.dataset.recordId;
        }
    }
}

/**
 * Check if form is dirty from DOM data attribute
 * @param {Element} [element] - Optional element to scope to nearest .form-layout
 * @returns {boolean} True if form has unsaved changes
 */
function cmaGetIsDirty(element) {
    const formLayout = element ? element.closest('.form-layout') : document.querySelector('.form-layout');
    return formLayout?.dataset.isdirty === 'true';
}

/**
 * Set dirty state in DOM data attribute and body class
 * @param {boolean} value - Dirty state
 * @param {Element} [element] - Optional element to scope to nearest .form-layout
 */
function cmaSetIsDirty(value, element) {
    const formLayout = element ? element.closest('.form-layout') : document.querySelector('.form-layout');
    if (formLayout) {
        formLayout.dataset.isdirty = value ? 'true' : 'false';
        formLayout.classList.toggle('is-dirty', !!value);
    }
    document.body.classList.toggle('is-dirty', !!value);
}

class CmaFormController {
    /**
     * @param {number} formId - Form ID
     * @param {object} config - Configuration from server
     */
    constructor(formId, config) {
        // Instance ID for debugging
        this._instanceId = 'ctrl_' + Date.now() + '_' + Math.random().toString(36).substr(2, 5);
        // cmaLog.log('[CmaFormController] new instance:', this._instanceId, 'formId:', formId);

        this.formId = formId;
        this.config = config || {};
        // NOTE: currentRecordId is now stored in DOM via getter/setter (see below)
        // NOTE: isDirty is now computed from DOM state (see getter below)
        // NOTE: originalValues are now stored as data-original-value on each field
        this.loadingTimeout = 1000; // ms before showing loader (1 second delay to avoid flicker on fast loads)
        this.loadingTimer = null;
        this.listLoadingTimer = null;
        this.listLoadingDelay = 300; // ms before showing list loading indicator

        // JSON form is REQUIRED - all forms must be JSON-defined now
        this.jsonForm = this.config.jsonForm || null;
        this.jsonFormName = this.jsonForm; // Alias for compatibility
        this.isJsonForm = !!this.jsonForm;

        if (!this.jsonForm) {
            cmaLog.error('CmaFormController: jsonForm is required in config. Got:', this.config);
        }

        // Read state from data attributes on form-layout (stateless - no window globals!)
        const formLayout = document.querySelector('.form-layout');
        this.parentID = formLayout?.dataset.parentId || null;
        this.parentField = formLayout?.dataset.parentField || null;
        this._dataRecordId = formLayout?.dataset.recordId;
        this._dataCopyMode = formLayout?.dataset.copyMode === 'true';

        // DOM references (mainForm is now a getter, not cached)
        this.listPanel = document.getElementById('listPanel');
        this.listContent = document.getElementById('listContent');
        this.detailPanel = document.getElementById('detailPanel');
        this.detailContent = document.getElementById('detailContent');
        this.noDataMessage = document.getElementById('noDataMessage');
        // Note: mainForm is accessed via getter - always reads fresh from DOM
        this.loadingOverlay = document.getElementById('loadingOverlay');
        this.toolbarStatus = document.getElementById('toolbar-status');

        // State
        this.currentPage = 1;
        this.pageSize = 50;
        this.searchTerm = '';
        this.filters = {};
        this.searchFilters = {};  // Filters from the expandable search panel
        this.listData = null;
        this.combosLoaded = false;  // Track if combo options have been loaded
        this.pendingFormFields = null;  // Store fields to apply after combos load

        // List mode constants (matching PHP ListMode class)
        this.LIST_MODE = {
            DISPLAY_TREE: 1,
            DISPLAY_TABLE: 2,
            FILTER_NONE: 0,
            FILTER_USER_REQUESTED: 1,
            FILTER_TOO_MANY_RECORDS: 2,
            FILTER_REPOSITORY_FORCED: 3
        };

        // Load display mode from localStorage (default to table for JSON forms, tree for legacy forms)
        this.displayMode = this.loadDisplayMode();

        // Web component table mode - use lib-table component for high-performance rendering
        // Can be enabled via config.useWebComponentTable or localStorage preference
        this.useWebComponentTable = this.config.useWebComponentTable ||
            localStorage.getItem('cma_v2_use_web_component_table') === 'true';
        this._libTableInstance = null;

        // Cleanup tracking - prevents memory leaks from accumulated event listeners and intervals
        this._popupCheckInterval = null;        // Tracks popup closed check interval
        this._abortController = null;           // AbortController for cancelling in-flight requests
        this._boundTreeHandler = null;          // Bound tree click handler for removal
        this._boundListItemHandler = null;      // Bound list item click handler for removal
        this._boundPaginationHandler = null;    // Bound pagination handler for removal
        this._trackedListeners = [];            // Tracked event listeners for cleanup

        // Store reference to this controller on the form-layout element
        // This is the ONLY place the controller is stored - NO GLOBALS
        // Note: formLayout already declared above in this constructor
        if (formLayout) {
            formLayout._cmaController = this;
        }

        // Initialize
        this.init();
    }

    /**
     * Get the CmaFormController instance from any element or context
     * Searches up the DOM tree to find the nearest .form-layout and returns its controller
     * @param {Element} [element] - Optional element to start search from
     * @returns {CmaFormController|null} The controller instance or null
     */
    static getController(element) {
        // If element provided, search up from there
        if (element) {
            const layout = element.closest('.form-layout');
            if (layout && layout._cmaFormController) {
                return layout._cmaFormController;
            }
        }
        // Fallback: find the first .form-layout in document
        const layout = document.querySelector('.form-layout');
        return layout?._cmaFormController || null;
    }

    /**
     * Get record ID from the form-layout element
     * @param {Element} [element] - Optional element to start search from
     * @returns {string|number|null} The current record ID
     */
    static getRecordId(element) {
        const formLayout = element?.closest('.form-layout') || document.querySelector('.form-layout');
        return formLayout?.dataset.recordId || null;
    }

    // =========================================================================
    // EVENT LISTENER MANAGEMENT
    // Track event listeners so they can be properly removed in destroy()
    // =========================================================================

    /**
     * Add an event listener and track it for cleanup
     * @param {EventTarget} element - The element to add the listener to
     * @param {string} event - The event name
     * @param {Function} handler - The event handler
     * @param {Object} options - Event listener options
     */
    addTrackedListener(element, event, handler, options = false) {
        element.addEventListener(event, handler, options);
        this._trackedListeners.push({ element, event, handler, options });
    }

    /**
     * Remove all tracked event listeners
     * Called during destroy() to prevent memory leaks
     */
    removeTrackedListeners() {
        for (const listener of this._trackedListeners) {
            try {
                listener.element.removeEventListener(
                    listener.event,
                    listener.handler,
                    listener.options
                );
            } catch (e) {
                cmaLog.warn('Failed to remove event listener:', listener.event, e);
            }
        }
        this._trackedListeners = [];
    }

    // =========================================================================
    // DOM-BASED STATE PROPERTIES
    // These getters/setters store state in DOM data attributes instead of
    // instance properties. This prevents stale state issues when navigating
    // between forms - the state automatically goes away with the DOM.
    // =========================================================================

    // NOTE: currentRecordId and isDirty have been removed from the class.
    // Use the module-level helper functions instead:
    // - cmaGetRecordId() / cmaSetRecordId(value) for record ID
    // - cmaGetIsDirty() / cmaSetIsDirty(value) for dirty state
    // These read/write DOM data attributes directly - no class properties needed.

    /**
     * Get original field values from DOM data attributes
     * @returns {Object} Map of field names to original values
     */
    get originalValues() {
        const values = {};
        const mainForm = document.getElementById('mainForm');
        if (!mainForm) return values;

        mainForm.querySelectorAll('[data-original-value]').forEach(field => {
            const name = field.name || field.dataset.field;
            if (name) {
                values[name] = field.dataset.originalValue;
            }
        });
        return values;
    }

    /**
     * Set original values - stores as data-original-value on each field
     * @param {Object} values - Map of field names to original values
     */
    set originalValues(values) {
        const mainForm = document.getElementById('mainForm');
        if (!mainForm) return;

        // Clear existing original values
        mainForm.querySelectorAll('[data-original-value]').forEach(field => {
            delete field.dataset.originalValue;
        });

        // Set new original values
        if (values && typeof values === 'object') {
            for (const [name, value] of Object.entries(values)) {
                const field = mainForm.querySelector(`[name="${name}"]`);
                if (field) {
                    field.dataset.originalValue = value ?? '';
                }
            }
        }
    }

    /**
     * Get reference to main form element (always from DOM, never cached)
     * @returns {HTMLFormElement|null}
     */
    get mainForm() {
        return document.getElementById('mainForm');
    }

    /**
     * Setter for mainForm - no-op since we always read from DOM
     * Exists for backwards compatibility with code that sets this.mainForm = null
     */
    set mainForm(value) {
        // No-op - mainForm is always read fresh from DOM
        // This setter exists only for backwards compatibility
    }

    // =========================================================================
    // END DOM-BASED STATE PROPERTIES
    // =========================================================================

    /**
     * Get the form parameter for API calls
     * Reads from: this.jsonForm, config, or data-json-form attribute on form-layout
     * @returns {string} URL parameter string like "form=inventarisatie"
     */
    getFormParam() {
        // Priority: instance property > config > DOM data attribute
        let formName = this.jsonForm || this.config?.jsonForm;
        if (!formName) {
            const formLayout = document.querySelector('.form-layout');
            formName = formLayout?.dataset.jsonForm;
        }
        if (formName) {
            return `form=${encodeURIComponent(formName)}`;
        }
        // This is a configuration error - form must be provided
        cmaLog.error('getFormParam: No form configured. Check CMA.formConfig or data-json-form attribute.');
        throw new Error('form is required but not configured');
    }

    /**
     * Build a map of fieldName → filterByField from combo elements with data-filter-by-field.
     * Used to pass cascading combo dependencies to inline-edit.
     */
    getFilterByFieldMap() {
        const map = {};
        if (!this.mainForm) return map;
        this.mainForm.querySelectorAll('lib-combo[data-filter-by-field]').forEach(function(combo) {
            const name = combo.getAttribute('name');
            const filterBy = combo.dataset.filterByField;
            if (name && filterBy) {
                map[name] = filterBy;
            }
        });
        return map;
    }

    /**
     * @deprecated Use getFormParam() instead
     */
    getFormIdParam() {
        return this.getFormParam();
    }

    /**
     * Initialize the controller
     */
    init() {
        // Store controller reference on the form-layout element for DOM-based lookup
        // This eliminates global state pollution - each form has its own controller reference
        const formLayout = document.querySelector('.form-layout');
        if (formLayout) {
            formLayout._cmaFormController = this;
            // Also store form name as data attribute for debugging
            formLayout.dataset.jsonForm = this.jsonForm || '';
        }

        // loadRecord looks up controller from DOM via CMA.FormController.getController()
        // This is NOT a global controller reference - it finds the current form's controller
        window.loadRecord = function(recordId) {
            // cmaLog.log('[window.loadRecord] called with recordId:', recordId, 'type:', typeof recordId);
            console.trace('[window.loadRecord] stack trace');
            const controller = CMA.FormController.getController();
            if (controller) {
                controller.loadRecord(recordId);
            } else {
                cmaLog.error('loadRecord: No controller found on .form-layout element');
            }
        };

        // act() is a no-op kept for backwards compatibility
        window.act = function(element) {
            // No-op - active state is set by bindTreeEvents
        };

        // Global search functions - look up controller from DOM (data-driven)
        window.cmaHandleSearch = function() {
            const controller = CMA.FormController.getController();
            if (controller) controller.handleSearch();
        };
        window.cmaSearchAsYouType = function(value) {
            const controller = CMA.FormController.getController();
            if (controller) controller.searchAsYouType(value);
        };
        window.cmaApplySearchFilters = function() {
            const controller = CMA.FormController.getController();
            if (controller) controller.applySearchFilters();
        };
        window.cmaClearSearchFilters = function() {
            const controller = CMA.FormController.getController();
            if (controller) controller.clearSearchFilters();
        };
        window.cmaToggleSearchMore = function() {
            const controller = CMA.FormController.getController();
            if (controller) controller.toggleSearchMore();
        };
        // Legacy alias for isDirty check (reads from DOM data attribute)
        window.cmaIsDirty = function() {
            return cmaGetIsDirty();
        };

        // Add popup class if we're in a popup context
        // This ensures button texts are always visible in popups
        if (this.isInPopup()) {
            document.body.classList.add('popup');
        }

        // Calculate and apply label column width from field captions
        const labelWidth = this.calculateLabelWidth();
        const formEl = document.querySelector('.form-layout') || document.documentElement;
        formEl.style.setProperty('--label-column-width', labelWidth + 'px');

        this.bindEvents();
        this.initCombos();
        // Hide parent field when form is opened as a subform
        this.hideParentField();
        // Initialize groupbox collapse/expand state
        if (typeof grp_init === 'function') {
            grp_init(this.formId);
        }
        // HTML editors are initialized on-demand when detail panel is shown
        this.htmlEditorsInitialized = false;
        this.parseUrlParams();

        // Check for direct record mode (from data attributes on form-layout, NOT window globals)
        // cmaLog.log('init: data-record-id=', this._dataRecordId, 'data-copy-mode=', this._dataCopyMode, 'isInPopup=', this.isInPopup());

        // Check if view parameter was explicitly set (tree or table)
        const urlParams = new URLSearchParams(window.location.search);
        const hasExplicitView = urlParams.has('view');
        const explicitView = urlParams.get('view');

        if (this._dataRecordId !== undefined) {
            // Parse record ID: can be number or string (for GUIDs)
            this.directRecordId = this._dataRecordId === '' ? null :
                (isNaN(this._dataRecordId) ? this._dataRecordId : parseInt(this._dataRecordId, 10));

            // Use direct record mode (no tree/table) for:
            // - copy mode
            // - popup/sidepanel context (detail screens)
            // - ID with no explicit view parameter
            // BUT: If view=tree or view=table is explicitly set, show tree/table AND load record
            const useDirectMode = this._dataCopyMode ||
                this.isInPopup() ||
                (this.directRecordId !== null && !hasExplicitView);

            // cmaLog.log('init: directRecordId=', this.directRecordId, 'useDirectMode=', useDirectMode, 'hasExplicitView=', hasExplicitView);

            if (useDirectMode) {
                this.directRecordMode = true;
                this.initDirectRecordMode();
            } else if (hasExplicitView && this.directRecordId !== null) {
                // View parameter with ID: show tree/table and load the specific record
                // cmaLog.log('[FormController] view+ID mode: view=' + explicitView + ', recordId=' + this.directRecordId);
                this.directRecordMode = false;
                this.setDisplayModeClass(explicitView === 'table' ? 'table' : 'tree');
                this.loadToolbarFilter();
                // Capture record ID before async operation (in case it gets modified)
                const recordIdToLoad = this.directRecordId;
                // Hide form until data is loaded (PHP already sets this, ensure present for JS nav)
                document.body.classList.add('data-loading');
                // Use IIFE for clean async/await handling
                (async () => {
                    try {
                        // cmaLog.log('[FormController] Starting formInit...');
                        // Initialize form (loads list)
                        await this.formInit();
                        // cmaLog.log('[FormController] formInit completed, loading record:', recordIdToLoad);
                        // Load the specific record
                        if (recordIdToLoad) {
                            const loadResult = await this.loadRecord(recordIdToLoad);
                            // cmaLog.log('[FormController] loadRecord result:', loadResult);
                        }
                        // cmaLog.log('[FormController] view+ID mode complete');
                    } catch (err) {
                        cmaLog.error('[FormController] view+ID mode error:', err);
                    } finally {
                        document.body.classList.remove('data-loading');
                    }
                })();
            }
        } else if (document.body.classList.contains('is-creating')) {
            // New record mode (New=Y or add related) — show detail form only
            this.directRecordMode = true;
            this.setDisplayModeClass('detail');
            this.formInit();
        } else {
            // cmaLog.log('init: no data-record-id, calling formInit without record');
            this.directRecordMode = false;
            // Set initial display mode class
            this.setDisplayModeClass(this.isTableMode() ? 'table' : 'tree');
            // Load saved toolbar filter before loading list
            this.loadToolbarFilter();
            // Initialize form (loads list + combos in one request)
            this.formInit();
        }

        // Watch for toolbar overflow and auto-compact when buttons don't fit
        this.initToolbarOverflow();

        // Watch for viewport changes to switch to table view on mobile
        this.initMobileViewportWatcher();

        // Update breadcrumb in parent frame (main.php) with form name
        this.updateBreadcrumb();

        // Initialize fold bars (vertical and horizontal)
        // Must be done after DOM elements exist
        if (typeof initFoldBar === 'function') {
            initFoldBar();
        }
        if (typeof initHorizontalFoldBar === 'function') {
            initHorizontalFoldBar();
        }

        // Convert PNG icons to SVG (assets/icons/[name].png -> assets/icons/[name].svg)
        this.convertPngIconsToSvg();

        // Show debug overlay if:
        // 1. ?debug=1 in URL, OR
        // 2. Developer preference enabled in Voorkeuren (localStorage)
        const debugUrlParam = window.location.search.includes('debug=1');
        const debugPreference = localStorage.getItem('cma_debug_overlay') === 'J';
        if (debugUrlParam || debugPreference) {
            this.showDebugOverlay();
        }

        // Re-sync toolbar filter when page is restored from bfcache (back/forward navigation)
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) {
                this._checkToolbarFilterChanged();
            }
        });
    }

    /**
     * Show debug overlay with all form settings
     * - Persists in top window across sidepanel opens
     * - Has collapse/expand functionality
     * - Can be enabled via Voorkeuren (developer setting)
     */
    showDebugOverlay() {
        const self = this;
        // Use top window so overlay persists across sidepanel opens
        const topWin = window.top || window;
        const topDoc = topWin.document;

        // Check if overlay already exists in top window
        let overlay = topDoc.getElementById('formDebugOverlay');
        const isCollapsed = topWin._debugOverlayCollapsed || false;

        if (!overlay) {
            overlay = topDoc.createElement('div');
            overlay.id = 'formDebugOverlay';
            overlay.style.cssText = 'position:fixed;top:60px;right:10px;background:#000;color:#ffff00;padding:0;font-family:monospace;font-size:var(--font-size-xs);z-index:99999;border:2px solid #ffff00;border-radius:4px;box-shadow:0 4px 12px rgba(0,0,0,0.5);';
            topDoc.body.appendChild(overlay);
        }

        // Get current window/frame info
        const currentUrl = window.location.href;
        const urlParams = new URLSearchParams(window.location.search);
        const isSidepanel = window !== topWin;

        const info = {
            '=== CURRENT FRAME ===': isSidepanel ? 'Sidepanel' : 'Main',
            'URL': currentUrl.length > 60 ? currentUrl.substring(0, 60) + '...' : currentUrl,
            'form': urlParams.get('form'),
            'id': urlParams.get('id') || urlParams.get('ID'),
            'New': urlParams.get('New'),
            'copy': urlParams.get('copy'),
            'parentID': urlParams.get('parentID'),
            'parentField': urlParams.get('parentField'),
            '=== DATA ATTRIBUTES ===': '',
            'data-json-form': document.querySelector('.form-layout')?.dataset.jsonForm || 'N/A',
            'data-record-id': this._dataRecordId,
            'data-copy-mode': this._dataCopyMode,
            'data-parent-id': this.parentID,
            'data-parent-field': this.parentField,
            '=== CONTROLLER ===': '',
            'jsonForm': this.jsonForm,
            'currentRecordId': cmaGetRecordId(),
            'directRecordMode': this.directRecordMode,
            'isCopyMode': this.isCopyMode,
            '=== CONFIG ===': '',
            'config.formName': this.config?.formName,
            'config.sourceFormId': this.config?.sourceFormId
        };

        // Build header with collapse/expand (no close button - use Voorkeuren to disable)
        let html = `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#222;border-bottom:${isCollapsed ? 'none' : '1px solid #ffff00'};cursor:pointer;border-radius:${isCollapsed ? '2px' : '2px 2px 0 0'};" onclick="window._debugOverlayCollapsed=!window._debugOverlayCollapsed;document.getElementById('debugOverlayContent').style.display=window._debugOverlayCollapsed?'none':'block';this.style.borderBottom=window._debugOverlayCollapsed?'none':'1px solid #ffff00';this.style.borderRadius=window._debugOverlayCollapsed?'2px':'2px 2px 0 0';this.querySelector('.collapse-icon').textContent=window._debugOverlayCollapsed?'+':'-';">
                <b style="display:flex;align-items:center;gap:8px;">
                    <span class="collapse-icon" style="font-size:var(--font-size-md);width:14px;">${isCollapsed ? '+' : '-'}</span>
                    DEBUG
                </b>
            </div>
            <div id="debugOverlayContent" style="padding:12px;max-width:400px;max-height:70vh;overflow:auto;display:${isCollapsed ? 'none' : 'block'};color:white;">
        `;

        for (const [key, value] of Object.entries(info)) {
            if (key.startsWith('===')) {
                html += `<div style="margin-top:8px;color:#00ffff;font-weight:bold;">${key}</div>`;
            } else {
                const displayValue = value === undefined ? '<span style="color:#ff6666">undefined</span>' :
                                     value === null ? '<span style="color:#ff9999">null</span>' :
                                     value === '' ? '<span style="color:#999">(empty)</span>' : value;
                html += `<div><span style="color:#aaa">${key}:</span> ${displayValue}</div>`;
            }
        }

        html += '<div class="live-section"></div></div>';
        overlay.innerHTML = html;

        // Clear previous interval if exists
        if (topWin._debugOverlayInterval) {
            clearInterval(topWin._debugOverlayInterval);
        }

        // Use max safe z-index to always stay on top (avoids expensive getComputedStyle loop)
        overlay.style.zIndex = '2147483647';

        // Store previous values to detect changes (prevents flicker)
        topWin._debugOverlayLastValues = topWin._debugOverlayLastValues || {};

        // Update overlay every 1000ms to show live state changes (reduced from 500ms for performance)
        // Only polls when overlay is expanded to minimize CPU usage
        topWin._debugOverlayInterval = setInterval(() => {
            const currentOverlay = topDoc.getElementById('formDebugOverlay');
            if (!currentOverlay) {
                clearInterval(topWin._debugOverlayInterval);
                topWin._debugOverlayInterval = null;
                return;
            }

            // Skip polling if overlay is collapsed
            if (topWin._debugOverlayCollapsed) {
                return;
            }

            // Read data-record-id directly from DOM (single source of truth)
            const mainFormLayout = topDoc.querySelector('.form-layout');
            const sidepanel = topDoc.getElementById('sidepanel');
            const sidepanelIframe = sidepanel?.querySelector('iframe');
            const sidepanelFormLayout = sidepanelIframe?.contentDocument?.querySelector('.form-layout');

            const liveInfo = {
                'main data-record-id': mainFormLayout?.dataset.recordId || '(none)',
                'main data-isdirty': mainFormLayout?.dataset.isdirty || '(none)',
                'sidepanel data-record-id': sidepanelFormLayout?.dataset.recordId || '(none)',
                'sidepanel data-isdirty': sidepanelFormLayout?.dataset.isdirty || '(none)'
            };

            // Only update if values changed (prevents flicker)
            const liveInfoStr = JSON.stringify(liveInfo);
            if (topWin._debugOverlayLastValues.liveInfo === liveInfoStr) {
                return;
            }
            topWin._debugOverlayLastValues.liveInfo = liveInfoStr;

            let liveHtml = '<div style="margin-top:8px;color:#00ff00;font-weight:bold;">=== LIVE STATE ===</div>';
            for (const [key, value] of Object.entries(liveInfo)) {
                liveHtml += `<div><span style="color:#aaa">${key}:</span> ${value}</div>`;
            }
            const liveSection = currentOverlay.querySelector('.live-section');
            if (liveSection) {
                liveSection.innerHTML = liveHtml;
            }
        }, 1000);
    }

    /**
     * Convert PNG icons in the assets/icons/ folder to SVG format
     * This handles legacy icon references that point to .png files
     * when .svg versions are available
     */
    convertPngIconsToSvg() {
        // Find all img elements with src containing assets/icons/ and .png
        const pngIcons = document.querySelectorAll('img[src*="assets/icons/"][src$=".png"]');
        pngIcons.forEach(img => {
            const oldSrc = img.getAttribute('src');
            // Only convert icons in the assets/icons/ folder (not CKEditor plugins etc.)
            if (oldSrc && oldSrc.match(/assets\/icons\/\d{4}-[^/]+\.png$/)) {
                const newSrc = oldSrc.replace(/\.png$/, '.svg');
                img.setAttribute('src', newSrc);
                // cmaLog.log('Converted icon:', oldSrc, '->', newSrc);
            }
        });
    }

    /**
     * Update breadcrumb with form name
     * This ensures the breadcrumb shows the correct form name even after AJAX load
     * NOTE: Only updates when in main content area, NOT when in sidepanel/popup
     */
    updateBreadcrumb() {
        const formName = this.config?.formName || 'Form';

        try {
            // Skip breadcrumb update if we're in a sidepanel or popup (iframe context)
            // The breadcrumb should only reflect the main form, not subforms/panels
            if (window.parent && window.parent !== window) {
                // cmaLog.log('updateBreadcrumb: skipping - in iframe context (sidepanel/popup)');
                return;
            }

            // Also check if we're inside a sidepanel container (lib_sidepanel_container)
            // This catches cases where content is loaded directly without iframe
            if (typeof lib_IsInSidePanel === 'function' && lib_IsInSidePanel()) {
                // cmaLog.log('updateBreadcrumb: skipping - in sidepanel');
                return;
            }

            // Forms are loaded via AJAX into main.php's content area (same document)
            // The breadcrumb is in the main document
            const breadcrumb = document.getElementById('breadcrumb');
            if (breadcrumb) {
                breadcrumb.textContent = formName;
                // cmaLog.log('updateBreadcrumb: set to', formName);
            }
        } catch (e) {
            // Cross-origin or other error - silently ignore
            // cmaLog.log('updateBreadcrumb: error', e);
        }
    }

    /**
     * Initialize ResizeObserver on #detailToolbar to auto-compact when buttons overflow
     */
    initToolbarOverflow() {
        const toolbar = document.getElementById('detailToolbar');
        if (!toolbar) return;

        // Skip in popups - always show full text there
        if (this.isInPopup()) return;

        this._toolbarResizeObserver = new ResizeObserver(() => {
            this.checkToolbarOverflow();
        });
        this._toolbarResizeObserver.observe(toolbar);
    }

    /**
     * Check if toolbar buttons overflow and toggle compact mode (icon-only)
     */
    /**
     * Calculate optimal label column width from field captions.
     * Formula: (longestCaptionLength * 6.5) + 10, clamped 110–360px.
     */
    calculateLabelWidth() {
        const fields = this.config.fields;
        if (!fields || !fields.length) return 150;

        let longest = 0;
        for (const f of fields) {
            if (f.type === 'groupseparator' || !f.caption) continue;
            // Split on <br> variants, strip HTML tags/entities
            const lines = f.caption.split(/<br\s*\/?>/i);
            for (const line of lines) {
                const clean = line.replace(/<[^>]+>/g, '')
                                  .replace(/&[^;]+;/g, ' ')
                                  .trim();
                if (clean.length > longest) longest = clean.length;
            }
        }
        if (longest === 0) return 150;
        return Math.round(Math.max(110, Math.min(360, longest * 6.5 + 10)));
    }

    checkToolbarOverflow() {
        const toolbar = document.getElementById('detailToolbar');
        if (!toolbar) return;
        const left = toolbar.querySelector('.toolbar-left');
        if (!left) return;

        // Remove compact to measure natural (full text) width
        toolbar.classList.remove('compact');
        void toolbar.offsetWidth;

        // toolbar-left has min-width:fit-content so it never shrinks.
        // Compare its natural width against the toolbar's available width.
        const toolbarWidth = toolbar.clientWidth;
        const leftWidth = left.scrollWidth;
        const right = toolbar.querySelector('.toolbar-right');
        const rightWidth = right ? right.scrollWidth : 0;

        if (leftWidth + rightWidth > toolbarWidth) {
            toolbar.classList.add('compact');
        }
    }

    /**
     * Initialize media query watcher to switch to table view when entering mobile breakpoint
     */
    initMobileViewportWatcher() {
        const mobileQuery = window.matchMedia('(max-width: 768px)');

        const handleViewportChange = (e) => {
            if (e.matches && document.body.classList.contains('mode-tree') && !document.body.classList.contains('has-record')) {
                // Switched to mobile while in tree view (no record open) - switch to table view
                this.displayMode = this.LIST_MODE.DISPLAY_TABLE;
                this.setDisplayModeClass('table');
                // Reload list in table mode
                this.loadList();
            }
        };

        // Use addEventListener for modern browsers, addListener for older ones
        if (mobileQuery.addEventListener) {
            mobileQuery.addEventListener('change', handleViewportChange);
        } else if (mobileQuery.addListener) {
            mobileQuery.addListener(handleViewportChange);
        }
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════════
     * FORM INIT - Loads list + combos in ONE request for fast initial load
     * ═══════════════════════════════════════════════════════════════════════════
     */
    async formInit() {
        // Prevent duplicate formInit calls (returns in-flight promise if already running)
        if (this._formInitPromise) {
            cmaLog.warn('[formInit] Already running, returning in-flight promise');
            console.trace('[formInit] duplicate call stack');
            return this._formInitPromise;
        }
        this._formInitPromise = this._doFormInit();
        try {
            return await this._formInitPromise;
        } finally {
            this._formInitPromise = null;
        }
    }

    async _doFormInit() {
        const perfId = 'formInit_' + Date.now();
        cmaPerf.start(perfId, { formId: this.formId, mode: this.displayMode });
        // cmaLog.log('[Init] Starting form initialization');

        // Get uncached combo fields (client-side sessionStorage check)
        // Include recordId in cache key for record-dependent combos
        const comboFields = this.getComboFields();
        let cacheResult = { cached: {}, uncached: comboFields };
        if (typeof cmaComboCache !== 'undefined' && cmaComboCache.getMultiple) {
            cacheResult = cmaComboCache.getMultiple(this.formId, comboFields, cmaGetRecordId());
        }
        const uncachedFields = cacheResult.uncached || [];

        // Get uncached search panel combos
        const searchComboFields = this.getSearchPanelComboFields();
        let searchCacheResult = { cached: {}, uncached: searchComboFields };
        if (typeof cmaComboCache !== 'undefined' && cmaComboCache.getMultiple) {
            searchCacheResult = cmaComboCache.getMultiple(this.formId, searchComboFields, cmaGetRecordId());
        }
        const uncachedSearchFields = searchCacheResult.uncached || [];

        try {
            const params = new URLSearchParams({
                action: 'init',
                displayMode: this.displayMode,
            });

            // Add form identifier
            params.set('jsonForm', this.jsonForm);

            // Only request uncached combos
            if (uncachedFields.length > 0) {
                params.set('comboFields', uncachedFields.join(','));
            }
            if (uncachedSearchFields.length > 0) {
                params.set('searchComboFields', uncachedSearchFields.join(','));
            }

            // Add current record if set
            const recordIdForParams = cmaGetRecordId();
            if (recordIdForParams) {
                params.set('ID', recordIdForParams);
            }

            // Add parent context (for filtering list by parent record)
            if (this.parentID) {
                params.set('parentID', this.parentID);
            }
            if (this.parentField) {
                params.set('parentField', this.parentField);
            }

            // Add search filters
            if (this.searchFilters && Object.keys(this.searchFilters).length > 0) {
                params.set('filters', JSON.stringify(this.searchFilters));
            }

            const response = await fetch(`/cma/form_api.php?${params}`);
            if (!response.ok) {
                throw new Error(`[formInit] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();


            // Process list result - handle various response structures
            let listLoaded = false;
            if (data.list && data.list.success && (data.list.html || data.list.treeData)) {
                // Debug: log table view debug info
                if (data.list._debugTable) {
                    // cmaLog.log('[TableDebug]', data.list._debugTable);
                }
                this.processListData(data.list);
                listLoaded = true;
            } else if (data.list && data.list.error) {
                cmaLog.error('[Init] List load failed:', data.list.error);
            }

            // Cache combos from response
            if (data.combos) {
                this.cacheComboResults(data.combos);
            }
            if (data.searchCombos) {
                this.cacheComboResults(data.searchCombos);
            }

            // Apply cached combos (from sessionStorage + just loaded)
            // Merge cached data with freshly loaded combos
            const allCombos = { ...cacheResult.cached, ...data.combos };
            this.applyCachedCombos(comboFields, allCombos);
            this.loadSearchCombos();

            // Process record result if present
            let recordLoaded = false;
            if (data.record && data.record.success) {
                this.applyRecordData(data.record);
                recordLoaded = true;
            }

            cmaPerf.end(perfId, {
                listSuccess: listLoaded,
                combosLoaded: uncachedFields.length,
                combosFromCache: cacheResult.cached ? Object.keys(cacheResult.cached).length : 0
            });
            // cmaLog.log('[Init] Unified init complete:', data._initTiming || 'no timing');

            // If list didn't load via init, fall back to regular loadList
            // Pass skipRecordLoad=true if record was already loaded to prevent double-load
            if (!listLoaded) {
                // cmaLog.log('[Init] List not loaded via init, falling back to loadList, skipRecordLoad=', recordLoaded);
                await this.loadList(false, recordLoaded);
            }

            // Sync toolbar filter display with the filter value used in the query
            this._syncToolbarFilter();

        } catch (error) {
            cmaLog.error('[Init] Unified init failed, falling back:', error);
            cmaPerf.end(perfId, { error: error.message });
            // Fallback to regular sequential loading
            await this.loadList();
        }
    }

    /**
     * Apply record data from unified init response
     * This handles the same logic as loadRecord but without the fetch
     * @param {Object} data - Record data with fields, meta, etc.
     */
    async applyRecordData(data) {
        if (!data || !data.fields) {
            // cmaLog.log('applyRecordData: no fields in data');
            return;
        }

        const recordId = (data.id !== null && data.id !== undefined && data.id !== '') ? data.id : cmaGetRecordId();
        // cmaLog.log('applyRecordData: applying record', recordId);

        // Hide detail content during population to prevent visible "painting"
        // Uses visibility:hidden to preserve layout calculations
        const detailContent = this.detailPanel?.querySelector('.detail-content');
        if (detailContent) {
            detailContent.style.visibility = 'hidden';
        }

        cmaSetRecordId(recordId);
        this.saveLastRecordId(recordId);  // Remember for tree highlighting on mode switch

        // Mark data as loaded early (synchronously) to prevent duplicate loadRecord calls.
        // In view+ID mode, formInit() calls applyRecordData() without await, then loadRecord()
        // is called — the dataloaded flag must be set before the first await in this method.
        const formLayoutElEarly = document.querySelector('.form-layout');
        if (formLayoutElEarly) {
            formLayoutElEarly.dataset.dataloaded = 'true';
        }

        this.populateForm(data.fields);

        // Apply readonly and field states immediately after populateForm, BEFORE any await
        // This ensures per-field readonly (data-readonly) is enforced before Cypress or users
        // can interact with the populated form
        this.updateNewChangableOnlyFields(false);
        const canEdit = data.meta && data.meta.canEdit !== false;
        this.setFormReadonly(!canEdit);

        // Show detail panel and apply UI state immediately (before async loads)
        this.updateMeta(data.meta);
        this.showDetailPanel();
        // cmaLog.log('[applyRecordData] About to call initHtmlEditorsOnce, recordId:', recordId);
        this.initHtmlEditorsOnce();
        this.setDirty(false);
        // Initialize formval_nl input masking (digits-only for numbers, time formatting, etc.)
        if (typeof form_init_container === 'function') {
            form_init_container(this.mainForm);
        }
        this.captureOriginalValues(); // Capture form values after population for change tracking
        this.applyFilteredComboReload(); // Reload dependent combos with filtered options
        this.selectListItem(recordId);
        this.updateUrl();

        // Show detail content after all data is applied (single repaint)
        if (detailContent) {
            // Use requestAnimationFrame to batch the visibility change with browser's next paint
            requestAnimationFrame(() => {
                detailContent.style.visibility = '';
            });
        }

        // Update status text and sidepanel title
        const statusText = canEdit ? 'Wijzigen' : 'Bekijken';
        this.updateStatus(statusText);
        this.updateSidepanelTitle(canEdit ? 'wijzigen' : 'bekijken');

        // Set filter value if filterIdName is configured
        // This allows other forms filtering on this field to pick up the selected value
        if (this.config.filterIdName && recordId) {
            try {
                const key = 'cma_filter_field_' + this.config.filterIdName;
                localStorage.setItem(key, recordId);
            } catch (e) {
                // cmaLog.log('[localStorage] Filter field storage unavailable:', e.message);
            }
        }

        // Store prefetched first subform data (piggybacked on record API response)
        if (data.firstSubform) {
            this._prefetchedFirstSubform = data.firstSubform;
            // cmaLog.log('applyRecordData: stored prefetched first subform');
        } else {
            this._prefetchedFirstSubform = null;
        }

        // Load checklists, custom renderers, and subforms concurrently
        const [checklistResult, , subformResult] = await Promise.allSettled([
            this.loadChecklists(recordId),
            this.loadCustomRenderers(recordId),
            this.loadSubforms(recordId),
        ]);
        if (checklistResult.status === 'rejected') cmaLog.error('Checklist load error:', checklistResult.reason);
        if (subformResult.status === 'rejected') cmaLog.error('Subform load error:', subformResult.reason);

        // Update toolbar - remove is-creating since we now have a record
        document.body.classList.add('has-record');
        document.body.classList.remove('is-creating');
        const formLayoutEl = document.querySelector('.form-layout');
        if (formLayoutEl) {
            formLayoutEl.classList.add('has-record');
            formLayoutEl.classList.remove('is-creating');

            // Set data attributes for ID/GUID/GUID2 for data-driven button URLs
            formLayoutEl.dataset.recordId = recordId || '';
            // Look for guid/guid2 in fields (case-insensitive)
            if (data.fields) {
                const fieldsLower = {};
                Object.keys(data.fields).forEach(k => fieldsLower[k.toLowerCase()] = data.fields[k]);
                formLayoutEl.dataset.recordGuid = fieldsLower.guid || fieldsLower.code || '';
                formLayoutEl.dataset.recordGuid2 = fieldsLower.guid2 || '';
            }
        }

        // Update extra button URLs with resolved placeholders (after setting data attributes)
        this.updateExtraButtonUrls();

        // Re-check toolbar overflow (more buttons visible now)
        this.checkToolbarOverflow();

        // Execute onLoadJS from form definition (if configured)
        this.executeOnLoadJS(recordId);
    }

    /**
     * Execute the onLoadJS callback defined in the form definition
     * Called after a record is loaded and all data has been applied
     * @param {string|number} recordId - The loaded record ID
     */
    executeOnLoadJS(recordId) {
        const onLoadJS = this.config?.onLoadJS || CMA?.formConfig?.onLoadJS;
        if (!onLoadJS || typeof onLoadJS !== 'string' || onLoadJS.trim() === '') {
            return;
        }

        try {
            // cmaLog.log('executeOnLoadJS: executing', onLoadJS, 'with recordId:', recordId);
            // Create a function that has recordId in scope
            const fn = new Function('recordId', onLoadJS);
            fn(recordId);
        } catch (e) {
            cmaLog.error('executeOnLoadJS error:', e, 'code:', onLoadJS);
        }
    }

    /**
     * Set form to readonly mode
     * - Hides save/cancel buttons
     * - Shows "Alleen lezen" indicator in toolbar
     * - Makes all form fields readonly
     * @param {boolean} readonly - True to set form as readonly
     */
    setFormReadonly(readonly) {
        // Store readonly state
        this._isReadonly = readonly;

        // Toggle body class for CSS styling
        document.body.classList.toggle('form-readonly', readonly);

        // Show/hide save and cancel buttons (CSS handles this via body.form-readonly)
        const saveBtn = document.getElementById('toolbar_save');
        const cancelBtn = document.getElementById('toolbar_cancel');
        if (saveBtn) saveBtn.style.display = readonly ? 'none' : '';
        if (cancelBtn) cancelBtn.style.display = readonly ? 'none' : '';

        // Show/hide "Alleen lezen" indicator in toolbar
        let readonlyIndicator = document.getElementById('readonlyIndicator');
        if (readonly) {
            if (!readonlyIndicator) {
                // Create readonly indicator
                readonlyIndicator = document.createElement('span');
                readonlyIndicator.id = 'readonlyIndicator';
                readonlyIndicator.className = 'toolbar-readonly-indicator';
                readonlyIndicator.innerHTML = '<span class="lnr lnr-lock"></span> Alleen lezen';
                readonlyIndicator.dataset.tooltip = 'Alleen lezen';
                // Insert in detail toolbar-left for visibility
                const toolbarLeft = document.querySelector('#detailToolbar .toolbar-left');
                if (toolbarLeft) {
                    toolbarLeft.appendChild(readonlyIndicator);
                }
            }
            readonlyIndicator.style.display = '';
        } else if (readonlyIndicator) {
            readonlyIndicator.style.display = 'none';
        }

        // Make all form fields readonly
        if (this.mainForm) {
            const formElements = this.mainForm.querySelectorAll('input, textarea, select');
            formElements.forEach(el => {
                if (readonly) {
                    el.setAttribute('readonly', 'readonly');
                    el.setAttribute('disabled', 'disabled');
                    el.classList.add('is-readonly');
                } else {
                    // Only remove readonly if field doesn't have data-readonly="true"
                    if (el.dataset.readonly === 'true' || el.dataset.newChangableOnly === 'true') {
                        // Ensure per-field readonly stays enforced
                        // Use readonly (not disabled) so the value is still included in FormData
                        el.setAttribute('readonly', 'readonly');
                    } else {
                        el.removeAttribute('readonly');
                        el.removeAttribute('disabled');
                    }
                    el.classList.remove('is-readonly');
                }
            });

            // Disable CKEditor instances
            if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances && typeof CKEDITOR.instances === 'object') {
                Object.keys(CKEDITOR.instances).forEach(key => {
                    try {
                        const editor = CKEDITOR.instances[key];
                        // Check editor exists, is ready, and has setReadOnly method
                        if (editor && editor.status === 'ready' && typeof editor.setReadOnly === 'function') {
                            editor.setReadOnly(readonly);
                        }
                    } catch (e) {
                        cmaLog.warn('Error setting CKEditor read-only state for', key, ':', e);
                    }
                });
            }

            // Disable lib-switch components
            this.mainForm.querySelectorAll('lib-switch').forEach(sw => {
                if (readonly) {
                    sw.setAttribute('disabled', '');
                } else {
                    sw.removeAttribute('disabled');
                }
            });

            // Set readonly on lib-datepicker and lib-timepicker components
            this.mainForm.querySelectorAll('lib-datepicker, lib-timepicker').forEach(dp => {
                if (readonly) {
                    dp.setAttribute('readonly', '');
                } else {
                    // Only remove readonly if field doesn't have data-readonly="true"
                    if (dp.dataset.readonly !== 'true') {
                        dp.removeAttribute('readonly');
                    }
                }
            });

            // Set disabled on lib-combo components
            this.mainForm.querySelectorAll('lib-combo').forEach(combo => {
                if (readonly) {
                    combo.setAttribute('disabled', '');
                } else {
                    if (combo.dataset.readonly !== 'true') {
                        combo.removeAttribute('disabled');
                    }
                }
            });
        }
    }

    /**
     * Process list data from unified init or regular loadList
     */
    processListData(data) {
        // Refresh DOM reference
        this.listContent = document.getElementById('listContent');
        if (!this.listContent) return;

        // Hide loading indicator (lib-loader component)
        const loadingEl = document.getElementById('listLoader');
        if (loadingEl && loadingEl.hide) loadingEl.hide();

        // Check if filtering is required
        if (data.requiresFilter && data.filterMode === 2) {
            const panel = document.getElementById('searchPanel');
            const btn = document.getElementById('btn_search');
            if (panel) {
                panel.style.display = 'block';
                if (btn) btn.classList.add('active');
            }
        }


        // Update list content
        if (data.treeData && data.treeData.length > 0) {
            // Grouped tree — use cma-tree web component with JSON data
            cmaLog.log('processListData: using cma-tree with', data.treeData.length, 'root nodes');
            const storageKey = 'tree_' + (this.config.formName || this.formId);
            const formClass = this.config.formName ? this.config.formName.toLowerCase().replace(/ /g, '_') : '';
            this.listContent.innerHTML = '';
            this.listContent.style.display = 'block';

            var tree = this.listContent.querySelector('cma-tree');
            if (!tree) {
                tree = document.createElement('cma-tree');
                tree.setAttribute('storage-key', storageKey);
                if (formClass) tree.setAttribute('item-icon', formClass);
                tree.style.display = 'block';
                tree.style.height = '100%';
                this.listContent.appendChild(tree);
            }
            tree.setData(data.treeData);

            // Listen for item clicks — load the record
            if (!tree._formClickBound) {
                tree._formClickBound = true;
                const self = this;
                tree.addEventListener('item-click', function(e) {
                    const recordId = e.detail.id || e.detail.nodeId;
                    if (recordId) {
                        self.loadRecord(recordId);
                    }
                });
            }

            // Select active item if provided
            if (this._activeRecordId) {
                tree.selectById(String(this._activeRecordId));
            }
        } else if (data.html) {
            // Check for legacy embedded tree script (backward compat for old-style grouped trees)
            const scriptMatch = data.html.match(/<script[^>]*>([\s\S]*?)<\/script>/i);

            if (scriptMatch && scriptMatch[1] && scriptMatch[1].includes('gFld')) {
                const htmlWithoutScript = data.html.replace(/<script[^>]*>[\s\S]*?<\/script>/gi, '');
                this.listContent.innerHTML = htmlWithoutScript;
                this.listContent.style.display = 'block';

                if (typeof gFld !== 'function') {
                    cmaLog.error('ftiens4.js not loaded - tree functions unavailable');
                    this.listContent.innerHTML = '<div class="list-error">Tree bibliotheek niet geladen. <a href="javascript:location.reload()">Vernieuw de pagina</a>.</div>';
                    return;
                }

                try {
                    eval(scriptMatch[1]);
                    if (typeof initializeToElement === 'function') {
                        const formClass = this.config.formName ? this.config.formName.toLowerCase().replace(/ /g, '_') : '';
                        this.waitForJQuery(() => {
                            initializeToElement('listContent', 'tree_' + this.formId, '', formClass);
                        });
                    }
                } catch (e) {
                    cmaLog.error('Tree script error in processListData:', e);
                    this.listContent.innerHTML = '<div class="list-error">Fout bij laden boomstructuur: ' + this.escapeHtml(e.message) + '</div>';
                }
            } else {
                // Simple tree or table - just set HTML
                this.listContent.innerHTML = data.html;
                this.listContent.style.display = 'block';
            }
        }

        // Initialize tree/table handlers
        if (this.displayMode === this.LIST_MODE.DISPLAY_TREE) {
            this.bindTreeEvents();
            this.highlightSearchTerm();
            // Store field info for field chooser even in tree mode
            if (data.fields && data.fields.length > 0) {
                this._allFields = data.fields.map(f => ({
                    name: f.name,
                    caption: f.caption || f.name,
                    visible: true
                }));
            }
        } else {
            this.initTableFeatures(data);
            this.highlightSearchTerm();
        }

        // Enable predictive prefetching for faster record loading
        this.enablePrefetch();

        // Update toolbar button visibility based on list data (hasGrouping, etc.)
        this.updateToolbarButtons(data);

        // Show status
        if (data.count !== undefined) {
            const statusEl = document.getElementById('listStatus');
            if (statusEl) {
                statusEl.textContent = data.count + ' item' + (data.count !== 1 ? 's' : '');
            }
        }
    }

    /**
     * Cache combo results to sessionStorage
     */
    cacheComboResults(combos) {
        for (const [fieldName, result] of Object.entries(combos)) {
            if (result.success && result.options) {
                cmaComboCache.set(this.formId, fieldName, result.options, cmaGetRecordId());
            }
        }
    }

    /**
     * Apply cached combo options to form fields
     */
    applyCachedCombos(fieldNames, cachedData) {
        for (const fieldName of fieldNames) {
            let options = cachedData?.[fieldName];
            if (!options) {
                options = cmaComboCache.get(this.formId, fieldName, cmaGetRecordId());
            }
            if (options) {
                this.applyComboOptions(fieldName, options);
            }
        }
        this.combosLoaded = true;
    }

    /**
     * Get list of combo field names from form
     */
    getComboFields() {
        const selects = document.querySelectorAll('#mainForm select[data-combo="true"], #mainForm select.combo');
        return Array.from(selects).map(s => s.name).filter(n => n);
    }

    /**
     * Get list of search panel combo field names
     */
    getSearchPanelComboFields() {
        const selects = document.querySelectorAll('#searchPanel select[data-combo="true"], #searchPanel select.combo');
        return Array.from(selects).map(s => s.name).filter(n => n);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // PREDICTIVE PREFETCH - Preload record data on hover
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * Prefetch cache - stores prefetched records
     */
    _prefetchCache = new Map();
    _prefetchInFlight = new Set();

    /**
     * Enable predictive prefetching on tree/table items
     * Called after list is rendered
     */
    enablePrefetch() {
        // Add hover handlers to tree items and table rows
        const items = document.querySelectorAll('.tree-item[data-id], .listtable tbody tr[data-id]');
        items.forEach(item => {
            item.addEventListener('mouseenter', (e) => this.handlePrefetchHover(e), { passive: true });
        });
    }

    /**
     * Handle hover for prefetch
     */
    handlePrefetchHover(event) {
        const target = event.currentTarget;
        const recordId = target.dataset.id;
        if (!recordId || recordId === cmaGetRecordId()) return;

        // Don't prefetch if already in cache or in-flight
        if (this._prefetchCache.has(recordId) || this._prefetchInFlight.has(recordId)) return;

        // Delay prefetch slightly to avoid premature loads during quick scrolling
        setTimeout(() => {
            // Check if still hovering
            if (target.matches(':hover')) {
                this.prefetchRecord(recordId);
            }
        }, 150);
    }

    /**
     * Prefetch a record in the background
     */
    async prefetchRecord(recordId) {
        if (this._prefetchCache.has(recordId) || this._prefetchInFlight.has(recordId)) return;

        this._prefetchInFlight.add(recordId);

        try {
            const response = await fetch(`/cma/form_api.php?action=record&${this.getFormParam()}&id=${recordId}`);
            if (!response.ok) {
                throw new Error(`[prefetchRecord] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();
            if (data.success) {
                this._prefetchCache.set(recordId, data);
                // Limit cache size to prevent memory issues
                if (this._prefetchCache.size > 20) {
                    const firstKey = this._prefetchCache.keys().next().value;
                    this._prefetchCache.delete(firstKey);
                }
            }
        } catch (error) {
            // cmaLog.log('[Prefetch] Failed for record', recordId);
        } finally {
            this._prefetchInFlight.delete(recordId);
        }
    }

    /**
     * Get prefetched record if available
     */
    getPrefetchedRecord(recordId) {
        return this._prefetchCache.get(recordId) || null;
    }

    /**
     * Initialize direct record mode (when ID parameter is provided)
     * - Hides the list panel
     * - Shows only the detail area
     * - For ID=0: Add mode without subforms
     * - For ID>0: Edit mode with subforms
     * - For copy mode: Load record but treat as new
     */
    initDirectRecordMode() {
        // cmaLog.log('initDirectRecordMode: directRecordId=', this.directRecordId, 'isCopyMode=', this.isCopyMode);
        // Set body class for detail mode - CSS handles all visibility
        this.setDisplayModeClass('detail');

        // Check for copy mode (from data attribute, not window global)
        this.isCopyMode = this._dataCopyMode;

        if (this.directRecordId === null || this.directRecordId === undefined || this.directRecordId === '') {
            // cmaLog.log('initDirectRecordMode: new record mode');
            // Add mode: show empty form, hide subforms
            this.newRecord();
            // Make sure subforms stay hidden for new records (via CSS class)
            document.body.classList.remove('has-subform');
        } else if (this.isCopyMode) {
            // cmaLog.log('initDirectRecordMode: copy mode');
            // Hide form until data is loaded (PHP already sets this, ensure present for JS nav)
            document.body.classList.add('data-loading');
            // Copy mode: load record data but treat as new
            this.loadRecordForCopy(this.directRecordId).finally(() => {
                document.body.classList.remove('data-loading');
            });
        } else {
            // cmaLog.log('initDirectRecordMode: edit mode, calling loadRecord', this.directRecordId);
            // PHP already adds has-record and data-loading classes to body
            // Ensure they're present (e.g. when loaded via JS navigation)
            document.body.classList.add('has-record');
            document.body.classList.add('data-loading');
            // Edit mode: load the specific record
            this.loadRecord(this.directRecordId).then(result => {
                // cmaLog.log('initDirectRecordMode: loadRecord completed, result=', result);
            }).catch(err => {
                cmaLog.error('initDirectRecordMode: loadRecord error:', err);
                // Remove has-record if load failed
                document.body.classList.remove('has-record');
            }).finally(() => {
                // data-loading is removed inside _doLoadRecord, but ensure cleanup for edge cases
                document.body.classList.remove('data-loading');
                // Signal DOM ready via data attribute on form-layout (NO GLOBALS)
                const formLayout = document.querySelector('.form-layout');
                if (formLayout) {
                    formLayout.dataset.ready = 'true';
                }
            });
        }
    }

    /**
     * Set display mode class on body element and update button active states
     * @param {string} mode - 'tree', 'table', or 'detail'
     */
    setDisplayModeClass(mode) {
        const targetClass = 'mode-' + mode;
        // Only modify if needed - avoid removing/re-adding same class which causes flash
        if (!document.body.classList.contains(targetClass)) {
            document.body.classList.remove('mode-tree', 'mode-table', 'mode-detail');
            document.body.classList.add(targetClass);
        }

        // Update button active states
        const btnTree = document.getElementById('btn_treeview');
        const btnTable = document.getElementById('btn_tableview');
        if (btnTree) btnTree.classList.toggle('active', mode === 'tree');
        if (btnTable) btnTable.classList.toggle('active', mode === 'table');
    }

    /**
     * Check if viewport is mobile-sized
     * @returns {boolean} True if mobile viewport
     */
    isMobileViewport() {
        return window.innerWidth < 768;
    }

    /**
     * Load display mode from localStorage
     * On mobile, defaults to table view as tree is not suited for small screens
     * @returns {number} Display mode (DISPLAY_TREE=1 or DISPLAY_TABLE=2)
     */
    loadDisplayMode() {
        // Mobile always defaults to table mode - tree is not suited for small screens
        if (this.isMobileViewport()) {
            return this.LIST_MODE.DISPLAY_TABLE;
        }

        const storageKey = 'cma_listMode_' + this.jsonForm;

        try {
            // First, check for form-specific stored mode
            const stored = localStorage.getItem(storageKey);
            if (stored !== null) {
                const mode = parseInt(stored, 10);
                if (mode === this.LIST_MODE.DISPLAY_TABLE || mode === this.LIST_MODE.DISPLAY_TREE) {
                    return mode;
                }
            }

            // If no form-specific mode, fall back to global last-used view mode
            const globalMode = localStorage.getItem('cma_lastViewMode');
            if (globalMode !== null) {
                const mode = parseInt(globalMode, 10);
                if (mode === this.LIST_MODE.DISPLAY_TABLE || mode === this.LIST_MODE.DISPLAY_TREE) {
                    return mode;
                }
            }
        } catch (e) {
            // cmaLog.log('[localStorage] Display mode retrieval unavailable:', e.message);
        }

        // Default: table view (all forms are now JSON-based)
        return this.LIST_MODE.DISPLAY_TABLE;
    }

    /**
     * Save display mode to localStorage
     * @param {number} mode Display mode (DISPLAY_TREE=1 or DISPLAY_TABLE=2)
     */
    saveDisplayMode(mode) {
        const storageKey = 'cma_listMode_' + this.jsonForm;

        try {
            // Save form-specific mode
            localStorage.setItem(storageKey, mode.toString());
            // Also save as global last-used view mode (used as default for forms without saved preference)
            localStorage.setItem('cma_lastViewMode', mode.toString());
            this.displayMode = mode;
        } catch (e) {
            // cmaLog.log('[localStorage] Display mode save unavailable:', e.message);
        }
    }

    /**
     * Toggle display mode between tree and table
     */
    toggleDisplayMode() {
        const newMode = this.displayMode === this.LIST_MODE.DISPLAY_TREE
            ? this.LIST_MODE.DISPLAY_TABLE
            : this.LIST_MODE.DISPLAY_TREE;

        // Set mode class immediately to prevent flicker
        this.setDisplayModeClass(newMode === this.LIST_MODE.DISPLAY_TABLE ? 'table' : 'tree');

        // When switching to table mode, save last record ID and clear current selection
        if (newMode === this.LIST_MODE.DISPLAY_TABLE) {
            const currentId = cmaGetRecordId();
            if (currentId) {
                this.saveLastRecordId(currentId);
            }
            cmaSetRecordId(null);
            document.body.classList.remove('has-record');
        }

        this.saveDisplayMode(newMode);
        this.loadList();
    }

    /**
     * Save last selected record ID to localStorage
     * Used to restore tree highlighting when switching back to tree mode
     * @param {string|number} recordId - Record ID to save
     */
    saveLastRecordId(recordId) {
        const storageKey = 'cma_lastRecord_' + this.jsonForm;

        try {
            localStorage.setItem(storageKey, String(recordId));
        } catch (e) {
            // cmaLog.log('[localStorage] Last record ID save unavailable:', e.message);
        }
    }

    /**
     * Load last selected record ID from localStorage
     * @returns {string|null} Last record ID or null
     */
    loadLastRecordId() {
        const storageKey = 'cma_lastRecord_' + this.jsonForm;

        try {
            return localStorage.getItem(storageKey);
        } catch (e) {
            // cmaLog.log('[localStorage] Last record ID retrieval unavailable:', e.message);
            return null;
        }
    }

    /**
     * Clear last selected record ID from localStorage
     * Called after deleting a record to prevent loading a deleted record
     */
    clearLastRecordId() {
        const storageKey = 'cma_lastRecord_' + this.jsonForm;

        try {
            localStorage.removeItem(storageKey);
            // cmaLog.log('[localStorage] Cleared last record ID for', storageKey);
        } catch (e) {
            // cmaLog.log('[localStorage] Last record ID clear unavailable:', e.message);
        }
    }

    /**
     * Get current display mode
     * @returns {number} Current display mode
     */
    getDisplayMode() {
        return this.displayMode;
    }

    /**
     * Check if current mode is tree
     * @returns {boolean} True if tree mode
     */
    isTreeMode() {
        return this.displayMode === this.LIST_MODE.DISPLAY_TREE;
    }

    /**
     * Check if current mode is table
     * @returns {boolean} True if table mode
     */
    isTableMode() {
        return this.displayMode === this.LIST_MODE.DISPLAY_TABLE;
    }

    /**
     * Hide the parent field row when form is opened as a subform
     * The parent field (e.g., fkOpleiding) doesn't need to be shown when
     * the form is already filtered by that parent.
     * Note: Required fields are never hidden - they must remain visible for user input/verification.
     */
    hideParentField() {
        if (!this.parentField || !this.mainForm) {
            return;
        }

        // Find the form row for the parent field using data-field-row attribute
        // Try exact match first, then case-insensitive
        let parentRow = this.mainForm.querySelector('tr[data-field-row="' + this.parentField + '"]');

        // If not found, try case-insensitive search
        if (!parentRow) {
            const parentFieldLower = this.parentField.toLowerCase();
            const allRows = this.mainForm.querySelectorAll('tr[data-field-row]');
            for (const row of allRows) {
                if (row.dataset.fieldRow.toLowerCase() === parentFieldLower) {
                    parentRow = row;
                    break;
                }
            }
        }

        if (parentRow) {
            // Check if the field is required - never hide required fields
            const field = parentRow.querySelector('[data-required="true"]');
            if (field) {
                // cmaLog.log('hideParentField: NOT hiding required field', this.parentField);
                return;
            }

            parentRow.style.display = 'none';
            // cmaLog.log('hideParentField: hidden row for field', this.parentField);
        } else {
            // cmaLog.log('hideParentField: row not found for field', this.parentField);
        }
    }

    /**
     * Set parent field value when creating a new record in subform context
     * This ensures the new record is linked to the parent record
     * For SELECT/combo fields, waits for combos to be loaded and fetches label if needed
     */
    async setParentFieldValue() {
        if (!this.parentField || !this.parentID || !this.mainForm) {
            return;
        }

        // Wait for combos to be loaded (they have the options we need)
        if (!this.combosLoaded) {
            // cmaLog.log('setParentFieldValue: waiting for combos to load...');
            // Poll for combosLoaded (max 5 seconds)
            let waited = 0;
            while (!this.combosLoaded && waited < 5000) {
                await new Promise(resolve => setTimeout(resolve, 100));
                waited += 100;
            }
            // cmaLog.log('setParentFieldValue: combos loaded after', waited, 'ms');
        }

        // Find the field by name (case-insensitive)
        const parentFieldLower = this.parentField.toLowerCase();
        let field = null;

        // Try direct name match first
        field = this.mainForm.querySelector('[name="' + this.parentField + '"]');

        // If not found, try case-insensitive search
        if (!field) {
            const allFields = this.mainForm.querySelectorAll('input, select, textarea');
            for (const f of allFields) {
                if (f.name && f.name.toLowerCase() === parentFieldLower) {
                    field = f;
                    break;
                }
            }
        }

        if (field) {
            if (field.tagName === 'LIB-COMBO') {
                // lib-combo - fetch label and add option
                try {
                    const url = `/cma/form_api.php?action=combo&${this.getFormParam()}&field=${encodeURIComponent(this.parentField)}&id=${encodeURIComponent(this.parentID)}`;
                    const response = await fetch(url);
                    if (response.ok) {
                        const data = await response.json();
                        if (data.success && data.options && data.options.length > 0) {
                            const label = data.options[0].text || data.label || ('ID: ' + this.parentID);
                            field.addOption(String(this.parentID), label);
                        } else if (data.success && data.label) {
                            field.addOption(String(this.parentID), data.label);
                        }
                    }
                } catch (err) {
                    cmaLog.warn('setParentFieldValue: error fetching label', err);
                }
                field.value = String(this.parentID);
            } else {
                // Regular field - just set value
                field.value = this.parentID;
            }
        } else {
            // cmaLog.log('setParentFieldValue: field not found for', this.parentField);
        }
    }

    /**
     * Set filter field value when creating a new record with an active toolbar filter
     * This ensures the new record gets the current filter context (e.g., same opleiding)
     */
    setFilterFieldValue(capturedValue = null) {
        const filterFieldName = this.config.filterFieldName;
        if (!filterFieldName || !this.mainForm) {
            return;
        }

        // Priority: 1) searchFilters (from URL or toolbar), 2) capturedValue (from previous record), 3) nothing
        const filterValue = this.searchFilters?.[filterFieldName] || capturedValue;
        if (!filterValue) {
            return;
        }

        // Find the field by name (case-insensitive)
        const filterFieldLower = filterFieldName.toLowerCase();
        let field = this.mainForm.querySelector('[name="' + filterFieldName + '"]');

        // If not found, try case-insensitive search (include lib-combo)
        if (!field) {
            const allFields = this.mainForm.querySelectorAll('input, select, textarea, lib-combo');
            for (const f of allFields) {
                const fname = f.getAttribute('name');
                if (fname && fname.toLowerCase() === filterFieldLower) {
                    field = f;
                    break;
                }
            }
        }

        if (field) {
            field.value = filterValue;
        }
    }

    /**
     * Initialize lib-combo elements and load their options
     * @param {number} retryCount - Number of retry attempts (internal use)
     * @returns {Promise} Resolves when all combo options are loaded
     */
    initCombos(retryCount = 0) {
        // Collect all combo fields that need loading
        const combosToLoad = [];
        const comboElements = {};
        // Track checklists separately (they need record ID for selection state)
        this.checklistElements = {};

        // Find all lib-combo elements in the form
        const comboEls = this.mainForm?.querySelectorAll('lib-combo') || [];

        comboEls.forEach(combo => {
            const name = combo.getAttribute('name');
            if (!name) return;
            const sourceTable = combo.dataset.sourceTable;
            const isDynamic = combo.dataset.dynamic === 'true';
            const isChecklist = name.startsWith('chklst_');
            const hasAjaxUrl = combo.hasAttribute('ajax-url');

            // Dynamic/AJAX combos are self-sufficient via ajax-url attribute
            // Static combos and checklists need options loaded from server
            if (hasAjaxUrl) {
                // AJAX combo - lib-combo handles search internally
                // Just track for value setting later
                comboElements[name] = combo;
            } else if (sourceTable || !isDynamic) {
                if (isChecklist) {
                    this.checklistElements[name] = combo;
                } else {
                    combosToLoad.push(name);
                    comboElements[name] = combo;
                }
            }
        });

        // Store combo elements reference for later use
        this.comboElements = comboElements;

        // Set up cascading combo filter dependencies (filterByField)
        this.setupComboFilterDependencies();

        // Batch load all combo options in a single request
        if (combosToLoad.length > 0) {
            return this.loadAllComboOptions(combosToLoad, comboElements);
        }

        this.combosLoaded = true;
        return Promise.resolve();
    }

    /**
     * Set up cascading combo filter dependencies.
     * When a combo has data-filter-by-field="fkOpleiding", listen for changes on the
     * fkOpleiding field and reload this combo with filtered options.
     */
    setupComboFilterDependencies() {
        if (!this.mainForm) return;

        this.comboFilterDeps = {}; // parentFieldName -> [{fieldName, combo}]

        this.mainForm.querySelectorAll('lib-combo[data-filter-by-field]').forEach(combo => {
            const fieldName = combo.getAttribute('name');
            const parentFieldName = combo.dataset.filterByField;
            if (!fieldName || !parentFieldName) return;

            if (!this.comboFilterDeps[parentFieldName]) {
                this.comboFilterDeps[parentFieldName] = [];
            }
            this.comboFilterDeps[parentFieldName].push({ fieldName, combo });

            // Listen for changes on the parent field (lib-combo or native input)
            const parentField = this.mainForm.querySelector('[name="' + parentFieldName + '"]');
            if (parentField) {
                parentField.addEventListener('change', () => {
                    const parentValue = parentField.value || '';
                    this.reloadFilteredCombo(fieldName, parentFieldName, parentValue);
                });
            }
        });
    }

    /**
     * Reload a combo's options filtered by a parent field's value.
     * Clears the dependent combo value, fetches filtered options from server,
     * and optionally preserves the current value if still in the filtered set.
     * @param {string} fieldName - The dependent combo field name
     * @param {string} filterByField - The parent field name to filter by
     * @param {string} filterValue - The parent field's current value
     * @param {string} [preserveValue] - Optional value to preserve if still in filtered options
     */
    async reloadFilteredCombo(fieldName, filterByField, filterValue, preserveValue) {
        const combo = this.comboElements?.[fieldName];
        if (!combo) return;

        // If no parent value, clear the combo
        if (!filterValue) {
            combo.value = '';
            combo.setOptions([]);
            return;
        }

        // Invalidate sessionStorage cache for this combo
        const cacheKey = 'combo_' + this.getCacheFormId() + '_' + fieldName;
        try { sessionStorage.removeItem(cacheKey); } catch (e) { /* ignore */ }

        // Fetch filtered options
        const url = '/cma/form_api.php?action=combo&' + this.getFormParam() +
            '&field=' + encodeURIComponent(fieldName) +
            '&filterField=' + encodeURIComponent(filterByField) +
            '&filterValue=' + encodeURIComponent(filterValue);

        try {
            const response = await fetch(url);
            const data = await response.json();
            if (data.success && data.options) {
                const options = data.options.map(function(o) {
                    return { value: String(o.id), label: o.text };
                });
                combo.setOptions(options);

                // Preserve value if it exists in filtered options
                if (preserveValue) {
                    const found = options.some(function(o) { return o.value === String(preserveValue); });
                    if (found) {
                        combo.value = String(preserveValue);
                    }
                }
            } else {
                combo.setOptions([]);
            }
        } catch (error) {
            cmaLog.error('reloadFilteredCombo failed for ' + fieldName + ':', error);
        }
    }

    /**
     * After form population, reload dependent combos with filtered options.
     * This ensures combos show only valid options based on the parent field's value.
     */
    applyFilteredComboReload() {
        if (!this.comboFilterDeps) return;

        const self = this;
        Object.keys(this.comboFilterDeps).forEach(function(parentFieldName) {
            const parentField = self.mainForm.querySelector('[name="' + parentFieldName + '"]');
            if (!parentField || !parentField.value) return;

            const parentValue = parentField.value;
            self.comboFilterDeps[parentFieldName].forEach(function(dep) {
                const currentValue = dep.combo.value || '';
                self.reloadFilteredCombo(dep.fieldName, parentFieldName, parentValue, currentValue);
            });
        });
    }

    /**
     * Get the form identifier for caching purposes
     * All forms are now JSON-based, so we always use the form name
     */
    getCacheFormId() {
        return this.jsonForm;
    }

    /**
     * Initialize a dynamic combo with AJAX-based search
     * Used for large tables (>1000 records) where all options cannot be preloaded
     * @param {HTMLSelectElement} selectElement - The select element
     * @param {string} fieldName - Field name for API calls
     */
    initDynamicCombo(comboElement, fieldName) {
        const minSearchLength = parseInt(comboElement.dataset.minSearchLength) || 3;
        const self = this;

        // Build AJAX URL with filter context from URL params
        const urlParams = new URLSearchParams(window.location.search);
        const filterField = urlParams.get('filterField') || '';
        const filterValue = urlParams.get('filterValue') || '';
        const parentID = urlParams.get('parentID') || '';
        const parentField = urlParams.get('parentField') || '';

        let ajaxUrl = `/cma/form_api.php?action=combo&form=${encodeURIComponent(self.jsonForm)}&field=${encodeURIComponent(fieldName)}`;
        if (filterField && filterValue) {
            ajaxUrl += `&filterField=${encodeURIComponent(filterField)}&filterValue=${encodeURIComponent(filterValue)}`;
        }
        if (parentID && parentField) {
            ajaxUrl += `&parentID=${encodeURIComponent(parentID)}&parentField=${encodeURIComponent(parentField)}`;
        }

        // Set AJAX attributes on lib-combo
        comboElement.setAttribute('ajax-url', ajaxUrl);
        comboElement.setAttribute('ajax-id', 'id');
        comboElement.setAttribute('ajax-text', 'text');
        comboElement.setAttribute('min-search', String(minSearchLength));

        // Check pending fields for value/label
        let currentValue = comboElement.value;
        let currentLabel = '';

        if (this.pendingFormFields) {
            const pendingValue = this.pendingFormFields[fieldName];
            const pendingLabel = this.pendingFormFields[fieldName + '__label'];
            if (pendingValue) {
                currentValue = pendingValue;
                currentLabel = pendingLabel || '';
            }
        }

        // If there's a current value, set it with label
        if (currentValue) {
            if (currentLabel) {
                comboElement.addOption(String(currentValue), currentLabel);
                comboElement.value = String(currentValue);
            } else {
                // Fetch label from API
                const apiUrl = `/cma/form_api.php?action=combo&form=${encodeURIComponent(self.jsonForm)}&field=${encodeURIComponent(fieldName)}&id=${encodeURIComponent(currentValue)}`;
                fetch(apiUrl)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.label) {
                            comboElement.addOption(String(currentValue), data.label);
                            comboElement.value = String(currentValue);
                        } else if (data.success && data.options && data.options.length > 0) {
                            const opt = data.options.find(o => o.id == currentValue) || data.options[0];
                            comboElement.addOption(String(opt.id), opt.text);
                            comboElement.value = String(opt.id);
                        } else if (data.success) {
                            cmaLog.error('initDynamicCombo:', `Combobox "${fieldName}": waarde "${currentValue}" niet gevonden in brontabel`, { apiUrl });
                            comboElement.addOption(String(currentValue), `[${currentValue}] - niet gevonden`);
                            comboElement.value = String(currentValue);
                        } else {
                            cmaLog.error('initDynamicCombo:', `Combobox "${fieldName}": ${data.error || 'onbekende fout'}`, { apiUrl, data });
                            comboElement.addOption(String(currentValue), `[${currentValue}] - fout`);
                            comboElement.value = String(currentValue);
                        }
                    })
                    .catch(err => {
                        cmaLog.error('initDynamicCombo:', `Combobox "${fieldName}": netwerk fout - ${err.message}`, { apiUrl });
                        comboElement.addOption(String(currentValue), `[${currentValue}] - laad fout`);
                        comboElement.value = String(currentValue);
                    });
            }
        }

        // Update our reference in comboElements
        if (this.comboElements) {
            this.comboElements[fieldName] = comboElement;
        }
    }

    /**
     * Load all combo options in a single batch request
     * Uses client-side sessionStorage cache to reduce server load
     */
    async loadAllComboOptions(fieldNames, comboElements) {
        // cmaLog.log('loadAllComboOptions: loading', fieldNames.length, 'combos for formId:', this.getCacheFormId());
        try {
            const cacheFormId = this.getCacheFormId();
            const recordId = cmaGetRecordId();

            // Check cache first (include recordId for record-dependent combos)
            const cacheResult = cmaComboCache.getMultiple(cacheFormId, fieldNames, recordId);
            // cmaLog.log('loadAllComboOptions: cache result - cached:', Object.keys(cacheResult.cached), 'uncached:', cacheResult.uncached);

            // Apply cached options immediately
            for (const fieldName in cacheResult.cached) {
                const selectElement = comboElements[fieldName];
                if (selectElement) {
                    this.applyComboOptions(selectElement, cacheResult.cached[fieldName]);
                }
            }

            // Only fetch uncached fields from server
            if (cacheResult.uncached.length > 0) {
                const url = `/cma/form_api.php?action=combos&${this.getFormParam()}&fields=${encodeURIComponent(cacheResult.uncached.join(','))}`;
                // cmaLog.log('loadAllComboOptions: fetching', url);
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`[loadAllComboOptions] HTTP ${response.status} ${response.statusText}`);
                }
                const data = await response.json();
                // cmaLog.log('loadAllComboOptions: response', data.success, data.error || '', 'combos:', data.combos ? Object.keys(data.combos) : 'none');

                // Report API-level errors (cmaLog.error auto-reports to error panel via lib-log.js)
                if (!data.success) {
                    cmaLog.error('loadAllComboOptions:', `Combo's laden mislukt: ${data.error || 'Onbekende fout'}`, { url, response: data });
                }

                if (data.success && data.combos) {
                    const toCache = {};

                    // Apply options to each combo
                    for (const fieldName of cacheResult.uncached) {
                        const comboData = data.combos[fieldName];
                        const selectElement = comboElements[fieldName];

                        // Report individual combo errors (cmaLog.error auto-reports to error panel)
                        if (comboData && !comboData.success) {
                            cmaLog.error('loadAllComboOptions:', `Combobox "${fieldName}": ${comboData.error || 'Laden mislukt'}`, { url, comboData });
                        }

                        if (comboData && comboData.success && selectElement) {
                            // Check if this combo requires dynamic search (large table)
                            if (comboData.requires_search) {
                                selectElement.dataset.requiresSearch = 'true';
                                selectElement.dataset.minSearchLength = comboData.min_search_length || 3;
                                this.initDynamicCombo(selectElement, fieldName);
                                // cmaLog.log('loadAllComboOptions: field requires search', fieldName, {
                                //     tableCount: comboData.table_count,
                                //     minSearchLength: comboData.min_search_length
                                // });
                            } else if (comboData.options) {
                                this.applyComboOptions(selectElement, comboData.options);
                                // Prepare for caching
                                toCache[fieldName] = comboData.options;
                            }
                        }
                    }

                    // Store in cache (include recordId for record-dependent combos)
                    cmaComboCache.setMultiple(cacheFormId, toCache, recordId);
                }
            }
        } catch (error) {
            // Build informative error message
            const errorMsg = error?.message || (error && String(error) !== '[object Object]' ? String(error) : null) || 'Network or parsing error';
            // Use warning (not error) since we'll try individual fallback
            cmaLog.warn('Batch combo load failed, trying individual requests:', errorMsg);
            // Fallback to individual requests if batch fails
            for (const fieldName of fieldNames) {
                await this.loadComboOptions(fieldName, comboElements[fieldName]);
            }
        } finally {
            this.combosLoaded = true;
            // Re-apply pending fields if record was loaded before combos
            if (this.pendingFormFields) {
                this.populateForm(this.pendingFormFields);
                this.pendingFormFields = null;
                // Reload dependent combos with filtered options after deferred populate
                this.applyFilteredComboReload();
            }
            // Re-apply filter field value after combos load (new record mode)
            // When setFilterFieldValue() runs before combo options are loaded,
            // the select has no matching <option> and the value is lost.
            if (document.body.classList.contains('is-creating')) {
                this.setFilterFieldValue();
            }
        }
    }

    /**
     * Apply options to a combo element
     */
    applyComboOptions(comboElement, options) {
        const fieldName = comboElement.getAttribute('name');

        // Set options on the lib-combo element
        comboElement.setOptions(options.map(opt => ({
            value: String(opt.id),
            label: opt.text
        })));

        // Restore value - either from current value or from pending form fields
        let valueToRestore = comboElement.value;
        let labelToUse = null;

        if (!valueToRestore && this.pendingFormFields) {
            // Case-insensitive lookup in pending fields
            const lowerFieldName = fieldName.toLowerCase();
            for (const [key, val] of Object.entries(this.pendingFormFields)) {
                if (key.toLowerCase() === lowerFieldName) {
                    valueToRestore = val;
                    break;
                }
            }
        }

        // Get label from __label field if available
        if (valueToRestore && this.pendingFormFields) {
            const labelFieldName = fieldName + '__label';
            for (const [key, val] of Object.entries(this.pendingFormFields)) {
                if (key.toLowerCase() === labelFieldName.toLowerCase()) {
                    labelToUse = val;
                    break;
                }
            }
        }

        // If value doesn't exist in options but we have a label, add a temporary option
        if (valueToRestore && labelToUse) {
            const valueExists = options.some(o => String(o.id) == String(valueToRestore));
            if (!valueExists) {
                comboElement.addOption(String(valueToRestore), labelToUse);
            }
        }

        if (valueToRestore) {
            comboElement.value = String(valueToRestore);
        }
    }

    /**
     * Load combo options from server (single combo - for dynamic refresh)
     * @param {string} fieldName - Field name
     * @param {HTMLElement} selectElement - Select element
     * @param {boolean} forceRefresh - Skip cache if true
     */
    async loadComboOptions(fieldName, selectElement, forceRefresh = false) {
        try {
            const cacheFormId = this.getCacheFormId();
            const recordId = cmaGetRecordId();

            // Check cache first (unless forcing refresh)
            if (!forceRefresh) {
                const cachedOptions = cmaComboCache.get(cacheFormId, fieldName, recordId);
                if (cachedOptions) {
                    this.applyComboOptions(selectElement, cachedOptions);
                    return;
                }
            }

            const response = await fetch(`/cma/form_api.php?action=combo&${this.getFormParam()}&field=${encodeURIComponent(fieldName)}`);
            if (!response.ok) {
                throw new Error(`[loadComboOptions] HTTP ${response.status} ${response.statusText} for ${fieldName}`);
            }
            const data = await response.json();

            if (data.success) {
                // Check if this combo requires search (large table)
                if (data.requires_search) {
                    selectElement.dataset.requiresSearch = 'true';
                    selectElement.dataset.minSearchLength = data.min_search_length || 3;
                    // Reinitialize as dynamic AJAX combo
                    this.initDynamicCombo(selectElement, fieldName);
                    // cmaLog.log('loadComboOptions: field requires search', fieldName, {
                    //     tableCount: data.table_count,
                    //     minSearchLength: data.min_search_length
                    // });
                } else if (data.options) {
                    this.applyComboOptions(selectElement, data.options);
                    // Store in cache (include recordId for record-dependent combos)
                    cmaComboCache.set(cacheFormId, fieldName, data.options, recordId);
                }
            } else {
                // API returned success:false (cmaLog.error auto-reports to error panel)
                cmaLog.error('loadComboOptions:', `Combobox "${fieldName}": ${data.error || 'Laden mislukt'}`, { data });
            }
        } catch (error) {
            // Network/parsing error (cmaLog.error auto-reports to error panel)
            const errorMsg = error?.message || 'Network or parsing error';
            cmaLog.error('loadComboOptions:', `Combobox "${fieldName}": ${errorMsg}`, { originalError: error?.message, stack: error?.stack });
        }
    }

    /**
     * Load checklist options for all checklist fields
     * @param {string} recordId - The record ID to load selections for
     */
    async loadChecklists(recordId) {
        if (!this.checklistElements || Object.keys(this.checklistElements).length === 0) {
            return;
        }

        for (const [fieldName, selectElement] of Object.entries(this.checklistElements)) {
            try {
                // Extract control ID from field name (chklst_XXX -> XXX)
                const controlId = selectElement.dataset.controlId || fieldName.replace('chklst_', '');
                const response = await fetch(`/cma/form_api.php?action=checklist&${this.getFormParam()}&controlId=${encodeURIComponent(controlId)}&id=${encodeURIComponent(recordId || '-1')}`);

                if (!response.ok) {
                    throw new Error(`[Checklist] HTTP ${response.status} ${response.statusText} for ${fieldName}`);
                }

                const data = await response.json();

                if (data.success && Array.isArray(data.options)) {
                    // Build options and selected values for lib-combo
                    const selectedIds = [];
                    const comboOptions = data.options.map(opt => {
                        if (opt.selected) {
                            selectedIds.push(String(opt.id));
                        }
                        return { value: String(opt.id), label: opt.text };
                    });

                    selectElement.setOptions(comboOptions);
                    if (selectedIds.length > 0) {
                        selectElement.value = selectedIds;
                    }

                    // Update hidden fields for form submission
                    const allIds = data.options.map(o => o.id).join(',');
                    const infoField = this.mainForm.querySelector(`input[name="chklstinfo_${controlId}"]`);
                    const allField = this.mainForm.querySelector(`input[name="chklstall_${controlId}"]`);
                    if (infoField) infoField.value = data.options.length;
                    if (allField) allField.value = allIds;
                } else if (data.success && data.options && !Array.isArray(data.options)) {
                    cmaLog.error(`[Checklist] Invalid options format for ${fieldName}: expected array, got`, typeof data.options);
                }
            } catch (error) {
                cmaLog.error(`[Checklist] Error loading checklist ${fieldName}:`, error.message || error);
            }
        }
    }

    /**
     * Initialize HTML editors once (lazy initialization)
     * Only initializes on first call to avoid duplicate initialization
     * @returns {boolean} - true if initialization was triggered, false if skipped
     */
    initHtmlEditorsOnce() {
        // cmaLog.log('[initHtmlEditorsOnce] ENTRY - flag:', this.htmlEditorsInitialized, 'mainForm:', !!this.mainForm);

        if (this.htmlEditorsInitialized) {
            // cmaLog.log('[initHtmlEditorsOnce] SKIPPED - already initialized');
            return false;
        }
        this.htmlEditorsInitialized = true;
        // cmaLog.log('[initHtmlEditorsOnce] Flag set to true, registering jQuery ready handler');

        // Wrap in jQuery ready to ensure DOM is fully loaded
        try {
            jQuery(() => {
                // cmaLog.log('[initHtmlEditorsOnce] jQuery ready fired, document.readyState:', document.readyState);

                // Small delay to ensure all scripts have loaded
                setTimeout(() => {
                    // cmaLog.log('[initHtmlEditorsOnce] 50ms timeout fired, calling initHtmlEditors');
                    try {
                        const editorResult = this.initHtmlEditors();
                        // cmaLog.log('[initHtmlEditorsOnce] initHtmlEditors returned:', editorResult);
                    } catch (e) {
                        cmaLog.error('[initHtmlEditorsOnce] initHtmlEditors EXCEPTION:', e.message, e.stack);
                    }

                    // Initialize content block editing for fields with data-use-blockedit="true"
                    // blockedit_init() is provided by blockedit.js and looks for .blockedit containers
                    // cmaLog.log('[initHtmlEditorsOnce] blockedit_init available:', typeof blockedit_init === 'function');
                    if (typeof blockedit_init === 'function') {
                        // Wait for CKEditor instances to be created (longer delay for reliability)
                        setTimeout(() => {
                            // cmaLog.log('[initHtmlEditorsOnce] 300ms timeout fired, calling blockedit_init');
                            try {
                                blockedit_init();
                                // cmaLog.log('[initHtmlEditorsOnce] blockedit_init completed');
                            } catch (e) {
                                cmaLog.error('[initHtmlEditorsOnce] blockedit_init EXCEPTION:', e.message, e.stack);
                            }
                        }, 300);
                    }
                }, 50);
            });
            // cmaLog.log('[initHtmlEditorsOnce] jQuery ready handler registered successfully');
            return true;
        } catch (e) {
            cmaLog.error('[initHtmlEditorsOnce] EXCEPTION registering jQuery ready:', e.message, e.stack);
            return false;
        }
    }

    /**
     * Initialize HTML editors (CKEditor) for memo fields with allowHtml
     * @param {number} retryCount - Number of retry attempts (internal use)
     * @returns {string} - Status message describing what happened
     */
    initHtmlEditors(retryCount = 0) {
        // cmaLog.log('[initHtmlEditors] ENTRY - retryCount:', retryCount, 'mainForm:', !!this.mainForm);

        // Check if we have any textareas that need HTML editing
        const htmlTextareas = this.mainForm?.querySelectorAll('textarea[data-allow-html="true"]') || [];
        // cmaLog.log('[initHtmlEditors] Found textareas with data-allow-html="true":', htmlTextareas.length);

        if (htmlTextareas.length === 0) {
            // Also check for .blockedit containers
            const blockeditContainers = this.mainForm?.querySelectorAll('.blockedit') || [];
            // cmaLog.log('[initHtmlEditors] Found .blockedit containers:', blockeditContainers.length);
            // cmaLog.log('[initHtmlEditors] EXIT - no HTML textareas found');
            return 'no-textareas';
        }

        // Log each textarea found
        htmlTextareas.forEach((ta, i) => {
            // cmaLog.log('[initHtmlEditors] Textarea[' + i + ']:', {
            //     name: ta.name,
            //     id: ta.id,
            //     limitedHtml: ta.dataset.limitedHtml,
            //     noSpamJs: ta.dataset.noSpamJs,
            //     valueLength: ta.value?.length || 0,
            //     visible: ta.offsetParent !== null
            // });
        });

        // Ensure editor config is set from CMA.formConfig (JSON form templates
        // don't call SetFKEditorConfig — the config is in CMA.formConfig.editorConfig)
        if (CMA.formConfig?.editorConfig && typeof CMA.editor?.setConfig === 'function') {
            CMA.editor.setConfig(CMA.formConfig.editorConfig);
        }

        // Check if CKEDITOR and CreateFKEditor function are available
        const ckeditorAvailable = typeof CKEDITOR !== 'undefined';
        const createFKEditorAvailable = typeof CreateFKEditor === 'function';
        // cmaLog.log('[initHtmlEditors] CKEDITOR available:', ckeditorAvailable, 'CreateFKEditor available:', createFKEditorAvailable);

        if (!ckeditorAvailable || !createFKEditorAvailable) {
            // Retry up to 10 times with 200ms delay (2 seconds total)
            if (retryCount < 10) {
                // cmaLog.log('[initHtmlEditors] Dependencies not ready, scheduling retry', retryCount + 1, 'in 200ms');
                setTimeout(() => this.initHtmlEditors(retryCount + 1), 200);
                return 'retry-scheduled-' + (retryCount + 1);
            }
            cmaLog.error('[initHtmlEditors] EXIT - CKEDITOR/CreateFKEditor not available after 10 retries');
            return 'dependencies-failed';
        }

        // Log existing CKEDITOR instances
        const existingInstances = Object.keys(CKEDITOR.instances || {});
        // cmaLog.log('[initHtmlEditors] Existing CKEDITOR instances:', existingInstances);

        // Register instanceReady listener once (if not already done)
        // This ensures textarea data is synced to CKEditor when it becomes ready
        if (!this._ckeditorListenerRegistered) {
            this._ckeditorListenerRegistered = true;
            // cmaLog.log('[initHtmlEditors] Registering CKEDITOR instanceReady listener');
            CKEDITOR.on('instanceReady', (evt) => {
                const editor = evt.editor;
                const editorName = editor.name;

                // cmaLog.log('[CKEditor] instanceReady:', editorName);

                // Sync textarea value to CKEditor when it becomes ready
                const textarea = this.mainForm?.querySelector(`textarea[name="${editorName}"]`);
                // cmaLog.log('[CKEditor] textarea found:', !!textarea, 'value length:', textarea?.value?.length || 0);

                if (textarea && textarea.value) {
                    const currentData = editor.getData();
                    // cmaLog.log('[CKEditor] editor current data length:', currentData?.length || 0);
                    if (!currentData || currentData.trim() === '') {
                        // cmaLog.log('[CKEditor] syncing textarea value to editor:', editorName, 'value:', textarea.value.substring(0, 50));
                        editor.setData(textarea.value);
                    } else {
                        // cmaLog.log('[CKEditor] editor already has data, not overwriting');
                    }
                }

                // Track changes
                editor.on('change', () => this.setDirty(true));
            });
        } else {
            // cmaLog.log('[initHtmlEditors] instanceReady listener already registered');
        }

        let created = 0;
        let skipped = 0;
        let errors = 0;

        htmlTextareas.forEach(textarea => {
            const name = textarea.name;
            const isSimple = textarea.dataset.limitedHtml === 'true';
            const noSpamJs = textarea.dataset.noSpamJs === 'true';

            // Skip if already initialized
            if (CKEDITOR.instances[name]) {
                // cmaLog.log('[initHtmlEditors] Skipping', name, '- already has CKEDITOR instance');
                skipped++;
                return;
            }

            // Use CreateFKEditor from cma.js
            // Signature: CreateFKEditor(fieldname, nSize, bSpamJS, nHeight, bSimple, bNoToolbar)
            // Use the textarea's height so CKEditor starts at the right size (avoids flicker)
            // offsetHeight works when visible; style.height works when in hidden panel;
            // rows * 18 matches FormRenderer.php calculation as final fallback
            const rows = parseInt(textarea.getAttribute('rows')) || 0;
            const editorHeight = textarea.offsetHeight || parseInt(textarea.style.height) || (rows > 0 ? rows * 18 : 300);
            // cmaLog.log('[initHtmlEditors] Creating CKEditor for:', name, 'isSimple:', isSimple, 'noSpamJs:', noSpamJs, 'height:', editorHeight);
            try {
                CreateFKEditor(name, 0, noSpamJs, editorHeight, isSimple, false);
                // cmaLog.log('[initHtmlEditors] CreateFKEditor called successfully for:', name);
                created++;
            } catch (e) {
                cmaLog.error('[initHtmlEditors] CreateFKEditor EXCEPTION for', name, ':', e.message, e.stack);
                errors++;
            }
        });

        const result = 'created:' + created + ' skipped:' + skipped + ' errors:' + errors;
        // cmaLog.log('[initHtmlEditors] EXIT -', result);
        return result;
    }

    /**
     * Bind DOM events
     */
    bindEvents() {
        // Search input (legacy quickSearch)
        const searchInput = document.getElementById('quickSearch');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchTerm = e.target.value;
                    this.currentPage = 1;
                    this.loadList();
                }, 300);
            });
            // Prevent Enter from submitting form
            searchInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
        }

        // lib-search-input component (toolbar search)
        const libSearchInput = document.getElementById('searchfor');
        if (libSearchInput && libSearchInput.tagName === 'LIB-SEARCH-INPUT') {
            // cmaLog.log('[FormController] Setting up lib-search-input event listeners');
            // Search as you type
            libSearchInput.addEventListener('input', (e) => {
                // Handle both custom event (e.detail.value) and native input event (e.target.value)
                const value = e.detail?.value ?? e.target?.value ?? '';
                // cmaLog.log('[FormController] lib-search-input input event, value:', value);
                this.searchAsYouType(value);
            });
            // Enter key triggers server-side search
            libSearchInput.addEventListener('search', () => {
                // cmaLog.log('[FormController] lib-search-input search event');
                this.handleSearch();
            });
            // Clear also triggers filter reset
            libSearchInput.addEventListener('clear', () => {
                // cmaLog.log('[FormController] lib-search-input clear event');
                this.searchAsYouType('');

                // Also clear lib-table filters if present
                const libTable = document.querySelector('lib-table');
                if (libTable && typeof libTable.clearFilters === 'function') {
                    libTable.clearFilters();
                }

                // Force show all rows (in case searchAsYouType didn't find them)
                document.querySelectorAll('table.listtable tbody tr.listrow').forEach(row => {
                    row.style.display = '';
                });
                // Also try within lib-table
                if (libTable) {
                    libTable.querySelectorAll('tbody tr.listrow').forEach(row => {
                        row.style.display = '';
                    });
                }
            });
        } else if (libSearchInput) {
            // cmaLog.log('[FormController] searchfor found but not lib-search-input, tagName:', libSearchInput.tagName);
        }

        // Toolbar buttons - only bind if not already bound (prevents duplicate handlers)
        // IMPORTANT: Scope to .toolbar and .detail-toolbar to avoid binding context menu items
        // (.cma-context-menu also uses data-action attributes)
        const actionButtons = document.querySelectorAll('.toolbar [data-action], .detail-toolbar [data-action], .form-layout [data-action]');
        actionButtons.forEach(btn => {
            // Skip context menu items (they have their own handler in CmaInlineEdit)
            if (btn.closest('.cma-context-menu')) return;
            // Skip if already bound to prevent duplicate event handlers
            if (btn._cmaActionBound) return;
            btn._cmaActionBound = true;

            btn.addEventListener('click', (e) => {
                e.preventDefault();
                this.handleToolbarAction(btn.dataset.action, btn.dataset);
            });
        });

        // Form change detection
        if (this.mainForm) {
            this.mainForm.addEventListener('change', () => this.setDirty(true));
            // Debounce input events to avoid excessive setDirty calls on every keystroke
            const debouncedSetDirty = (typeof cmaDebounce === 'function')
                ? cmaDebounce(() => this.setDirty(true), 150)
                : () => this.setDirty(true);
            this.mainForm.addEventListener('input', debouncedSetDirty);

            // lib-combo change events bubble, but listen explicitly for clarity
            this.mainForm.querySelectorAll('lib-combo').forEach(combo => {
                combo.addEventListener('change', () => this.setDirty(true));
            });

            // Note: CKEditor instanceReady listener is registered in initHtmlEditors()
            // where we know CKEDITOR is available (after retry logic succeeds)

            // Checkbox/switch clicks
            this.mainForm.querySelectorAll('.switch').forEach(sw => {
                sw.addEventListener('click', () => this.setDirty(true));
            });

            // URL input changes - enable/disable preview button based on value
            this.mainForm.addEventListener('input', (e) => {
                if (e.target.matches('.url-display-input')) {
                    const fieldName = e.target.name;
                    const urlPreviewBtn = this.mainForm.querySelector(`[data-url-link="${fieldName}"]`);
                    if (urlPreviewBtn) {
                        const hasValue = e.target.value.trim() !== '';
                        urlPreviewBtn.href = hasValue ? e.target.value : '#';
                        urlPreviewBtn.classList.toggle('disabled', !hasValue);
                    }
                }
            });

            // Datepicker changes - custom datepicker uses .datefield class
            // The datepicker calls element.onchange() directly, so we need to set the onchange handler
            this.mainForm.querySelectorAll('.datefield').forEach(input => {
                const originalOnChange = input.onchange;
                input.onchange = () => {
                    if (originalOnChange) originalOnChange.call(input);
                    this.setDirty(true);
                };
            });

            // Time field shortcuts - format shortcuts on blur
            // Examples: "8" -> "8:00", "14" -> "14:00", "830" -> "8:30", "1430" -> "14:30"
            this.mainForm.querySelectorAll('.timefield, [data-type="time"], [data-validation="time"]').forEach(input => {
                input.addEventListener('blur', () => {
                    const val = input.value.trim();
                    if (!val) return;

                    let formatted = null;

                    // Just a number 0-23: treat as hour
                    if (/^\d{1,2}$/.test(val)) {
                        const hour = parseInt(val, 10);
                        if (hour >= 0 && hour <= 23) {
                            formatted = String(hour).padStart(2, '0') + ':00';
                        }
                    }
                    // 3 digits: h:mm (e.g., 830 -> 8:30)
                    else if (/^\d{3}$/.test(val)) {
                        const hour = parseInt(val[0], 10);
                        const min = parseInt(val.substring(1), 10);
                        if (hour >= 0 && hour <= 9 && min >= 0 && min <= 59) {
                            formatted = '0' + hour + ':' + String(min).padStart(2, '0');
                        }
                    }
                    // 4 digits: hhmm (e.g., 1430 -> 14:30)
                    else if (/^\d{4}$/.test(val)) {
                        const hour = parseInt(val.substring(0, 2), 10);
                        const min = parseInt(val.substring(2), 10);
                        if (hour >= 0 && hour <= 23 && min >= 0 && min <= 59) {
                            formatted = String(hour).padStart(2, '0') + ':' + String(min).padStart(2, '0');
                        }
                    }
                    // h:m or hh:m or h:mm (normalize to hh:mm)
                    else if (/^\d{1,2}:\d{1,2}$/.test(val)) {
                        const parts = val.split(':');
                        const hour = parseInt(parts[0], 10);
                        const min = parseInt(parts[1], 10);
                        if (hour >= 0 && hour <= 23 && min >= 0 && min <= 59) {
                            formatted = String(hour).padStart(2, '0') + ':' + String(min).padStart(2, '0');
                        }
                    }

                    if (formatted && formatted !== val) {
                        input.value = formatted;
                        this.setDirty(true);
                    }
                });
            });
        }

        // Keyboard shortcuts - tracked for cleanup
        this.addTrackedListener(document, 'keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                if (e.key === 's') {
                    e.preventDefault();
                    this.handleToolbarAction('save');
                }
            }
            // Delete key triggers record deletion when not in an editable field
            if (e.key === 'Delete' && !e.ctrlKey && !e.metaKey && !e.altKey) {
                const tag = e.target.tagName;
                const isEditable = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
                    || e.target.isContentEditable
                    || e.target.closest('lib-combo, lib-datepicker, lib-timepicker, cma-blockeditor');
                if (!isEditable && cmaGetRecordId()) {
                    e.preventDefault();
                    this.handleToolbarAction('delete');
                }
            }
        });

        // Prevent accidental navigation when dirty - tracked for cleanup
        this.addTrackedListener(window, 'beforeunload', (e) => {
            if (cmaGetIsDirty()) {
                e.preventDefault();
                e.returnValue = 'Je hebt niet-opgeslagen wijzigingen. Weet je zeker dat je wilt verlaten?';
                return e.returnValue;
            }
        });

        // Subform tabs - listen for tab-select event from cma-tabs component
        const subformTabs = document.getElementById('subformTabs');
        if (subformTabs) {
            subformTabs.addEventListener('tab-select', (e) => {
                this.activateSubformTab(e.detail.index, true, e.detail.id);
            });
        }

        // "Add related record" plus buttons next to comboboxes
        if (this.mainForm) {
            this.mainForm.addEventListener('click', (e) => {
                const addBtn = e.target.closest('.btn-add-related');
                if (!addBtn) return;
                e.preventDefault();
                e.stopPropagation();
                this.openAddRelatedPopup(addBtn);
            });

            // Password visibility toggle (pwd_view spans with data-toggle-field)
            this.mainForm.addEventListener('click', (e) => {
                const pwdToggle = e.target.closest('.pwd_view[data-toggle-field]');
                if (!pwdToggle) return;
                e.preventDefault();
                this.togglePasswordVisibility(pwdToggle);
            });

            // Image/file field controls - delegate to form level
            this.mainForm.addEventListener('click', (e) => {
                const target = e.target.closest('[data-select-field], [data-clear-field], [data-preview-field]');
                if (!target) return;

                e.preventDefault();
                const fieldName = target.dataset.selectField ||
                                  target.dataset.clearField || target.dataset.previewField;

                if (target.dataset.selectField) {
                    this.openImageFileSelector(fieldName, target.dataset.path);
                } else if (target.dataset.clearField) {
                    this.clearImageFile(fieldName);
                } else if (target.dataset.previewField) {
                    this.showImagePreview(fieldName);
                }
            });

            // URL preview button clicks - open URL in new tab
            this.mainForm.addEventListener('click', (e) => {
                const urlBtn = e.target.closest('.url-preview-btn');
                if (!urlBtn || urlBtn.classList.contains('disabled')) return;

                e.preventDefault();
                const url = urlBtn.href;
                if (url && url !== '#') {
                    window.open(url, '_blank');
                }
            });
        }
    }

    /**
     * Toggle password field visibility
     * Shows password for 3 seconds then hides it again
     * @param {HTMLElement} toggleEl The pwd_view span element
     */
    togglePasswordVisibility(toggleEl) {
        const fieldName = toggleEl.dataset.toggleField;
        if (!fieldName) return;

        const input = this.mainForm.querySelector('input[name="' + fieldName + '"]');
        if (!input) return;

        if (input.type === 'text') {
            // Already showing, hide immediately
            input.type = 'password';
            toggleEl.classList.remove('active');
        } else {
            // Show password
            input.type = 'text';
            toggleEl.classList.add('active');

            // Auto-hide after 3 seconds
            setTimeout(() => {
                input.type = 'password';
                toggleEl.classList.remove('active');
            }, 3000);
        }
    }

    // =========================================================================
    // Image/File Field Controls
    // =========================================================================

    /**
     * Open the image/file selector popup
     * @param {string} fieldName - Name of the image/file field
     * @param {string} path - Upload path for the file
     */
    openImageFileSelector(fieldName, path) {
        const field = this.mainForm.querySelector(`[name="${fieldName}"]`);
        if (!field) {
            cmaLog.warn('Image field not found:', fieldName);
            return;
        }

        const isImage = field.dataset.type === 'image';
        const currentValue = field.value || '';
        // layout=0 hides alignment/border/margin options (those are only for HTML editor)
        const popupUrl = isImage
            ? `wizards/file-browser.php?image=1&layout=0&basepath=${encodeURIComponent(path || '')}&fieldname=${encodeURIComponent(fieldName)}&file=${encodeURIComponent(currentValue)}`
            : `wizards/file-browser.php?basepath=${encodeURIComponent(path || '')}&fieldname=${encodeURIComponent(fieldName)}&file=${encodeURIComponent(currentValue)}`;

        // Store callback on form-layout element (NOT global)
        const formLayout = document.querySelector('.form-layout');
        if (formLayout) {
            formLayout._fileSelectCallback = (filename, width, height) => {
                this.setImageFileValue(fieldName, filename, width, height);
                delete formLayout._fileSelectCallback;
            };
        }

        if (typeof lib_OpenWindowCentered === 'function') {
            lib_OpenWindowCentered(popupUrl, 'Bestand selecteren', 1100, 700);
        } else {
            window.open(popupUrl, 'fileSelect', 'width=1100,height=700');
        }
    }

    /**
     * Clear the image/file field value
     * @param {string} fieldName - Name of the image/file field
     */
    async clearImageFile(fieldName) {
        const field = this.mainForm.querySelector(`[name="${fieldName}"]`);
        if (!field) return;

        // No confirmation needed - files are not actually deleted from disk,
        // just clearing the reference in the record

        // Clear the value
        field.value = '';
        this.setDirty(true);

        // Clear preview if it's an image
        const preview = this.mainForm.querySelector(`[data-image-preview="${fieldName}"]`);
        if (preview) {
            preview.src = '';
            preview.style.display = 'none';
        }

        // Disable preview and clear buttons (crop is NEVER disabled - it's used to upload new images)
        const previewBtn = this.mainForm.querySelector(`[data-preview-field="${fieldName}"]`);
        const clearBtn = this.mainForm.querySelector(`[data-clear-field="${fieldName}"]`);
        if (previewBtn) previewBtn.classList.add('disabled');
        if (clearBtn) clearBtn.classList.add('disabled');

        // Clear dimensions
        const widthField = this.mainForm.querySelector(`[data-image-width="${fieldName}"]`);
        const heightField = this.mainForm.querySelector(`[data-image-height="${fieldName}"]`);
        if (widthField) widthField.value = '';
        if (heightField) heightField.value = '';

        // Disable file view button and clear filename if it's a file field
        const fileViewBtn = this.mainForm.querySelector(`[data-file-link="${fieldName}"]`);
        const fileNameEl = this.mainForm.querySelector(`[data-file-name="${fieldName}"]`);
        const fileClearBtn = this.mainForm.querySelector(`[data-clear-field="${fieldName}"]`);
        if (fileViewBtn) fileViewBtn.classList.add('disabled');
        if (fileClearBtn) fileClearBtn.classList.add('disabled');
        if (fileNameEl) fileNameEl.textContent = '';
    }

    /**
     * Show image preview in a lightbox overlay
     * @param {string} fieldName - Name of the image field
     */
    showImagePreview(fieldName) {
        const field = this.mainForm.querySelector(`[name="${fieldName}"]`);
        if (!field || !field.value) return;

        // Get path from data-path attribute or from separate _path hidden input
        let path = field.dataset.path || '';
        if (!path) {
            const pathInput = this.mainForm.querySelector(`[name="${fieldName}_path"]`);
            if (pathInput) {
                path = pathInput.value || '';
            }
        }

        const imageUrl = path + field.value;

        // Create lightbox overlay for better preview experience
        const overlay = document.createElement('div');
        overlay.className = 'image-preview-overlay';
        overlay.innerHTML = `
            <div class="image-preview-container">
                <img src="${imageUrl}" alt="Preview" class="image-preview-large">
                <button class="image-preview-close" data-tooltip="Sluiten">&times;</button>
            </div>
        `;

        // Close on click outside image or on close button
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay || e.target.classList.contains('image-preview-close')) {
                overlay.remove();
            }
        });

        // Close on Escape key
        const handleKeydown = (e) => {
            if (e.key === 'Escape') {
                overlay.remove();
                document.removeEventListener('keydown', handleKeydown);
            }
        };
        document.addEventListener('keydown', handleKeydown);

        document.body.appendChild(overlay);
    }

    /**
     * Set the value of an image/file field (called from selector popup)
     * @param {string} fieldName - Name of the field
     * @param {string} filename - Selected filename
     * @param {number} width - Image width (optional)
     * @param {number} height - Image height (optional)
     */
    setImageFileValue(fieldName, filename, width, height) {
        const field = this.mainForm.querySelector(`[name="${fieldName}"]`);
        if (!field) return;

        field.value = filename;
        this.setDirty(true);

        const path = field.dataset.path || '';

        // Update preview if it's an image
        const preview = this.mainForm.querySelector(`[data-image-preview="${fieldName}"]`);
        if (preview) {
            preview.onerror = function() {
                this.style.display = 'none';
                this.src = '';
                const icon = this.parentElement.querySelector('.image-404');
                if (!icon) {
                    const el = document.createElement('span');
                    el.className = 'image-404 lnr lnr-picture';
                    el.title = 'Afbeelding niet gevonden';
                    this.parentElement.appendChild(el);
                }
            };
            const old404 = preview.parentElement.querySelector('.image-404');
            if (old404) old404.remove();
            preview.src = filename ? (path + filename) : '';
            preview.style.display = filename ? '' : 'none';
        }

        // Toggle disabled state on buttons based on whether image exists
        // Note: crop button is NEVER disabled - it's used to upload and crop new images
        const previewBtn = this.mainForm.querySelector(`[data-preview-field="${fieldName}"]`);
        const clearBtn = this.mainForm.querySelector(`[data-clear-field="${fieldName}"]`);
        if (previewBtn) previewBtn.classList.toggle('disabled', !filename);
        if (clearBtn) clearBtn.classList.toggle('disabled', !filename);

        // Update dimensions if provided
        if (width) {
            const widthField = this.mainForm.querySelector(`[data-image-width="${fieldName}"]`);
            if (widthField) widthField.value = width;
        }
        if (height) {
            const heightField = this.mainForm.querySelector(`[data-image-height="${fieldName}"]`);
            if (heightField) heightField.value = height;
        }

        // Update file link/name if it's a file field
        const fileViewBtn = this.mainForm.querySelector(`[data-file-link="${fieldName}"]`);
        const fileNameEl = this.mainForm.querySelector(`[data-file-name="${fieldName}"]`);
        const fileClearBtn = this.mainForm.querySelector(`[data-clear-field="${fieldName}"]`);
        if (fileViewBtn) {
            fileViewBtn.href = path + filename;
            fileViewBtn.classList.toggle('disabled', !filename);
        }
        if (fileClearBtn) {
            fileClearBtn.classList.toggle('disabled', !filename);
        }
        if (fileNameEl) {
            fileNameEl.textContent = filename || '';
        }
    }

    /**
     * Open popup to add a related record (for combobox plus button)
     * @param {HTMLElement} btn The plus button element
     */
    openAddRelatedPopup(btn) {
        // Guard against double-opens (e.g., from duplicate event handlers or multiple controllers)
        if (btn.dataset.opening) return;
        btn.dataset.opening = '1';
        setTimeout(() => { delete btn.dataset.opening; }, 1000);

        const fieldName = btn.dataset.field;
        const formName = btn.dataset.formName;

        if (!formName || !fieldName) {
            cmaLog.warn('Missing data for add related popup', btn.dataset);
            return;
        }

        // Build the popup URL - include New=Y to open in add mode
        // formName is the JSON form name (e.g., "deelnemers")
        const popupUrl = 'form.php?form=' + encodeURIComponent(formName) + '&New=Y&updatevalues=' + encodeURIComponent(fieldName);

        // Store reference to the field for later refresh
        this._pendingComboRefresh = fieldName;

        // Open using library function (respects user preference for sidepanel/popup)
        if (typeof lib_OpenPanel === 'function') {
            // Store callback on form-layout element (NOT global)
            const formLayout = document.querySelector('.form-layout');
            if (formLayout) {
                formLayout._addRelatedCallback = (newRecordId) => {
                    this.refreshComboOptions(fieldName, newRecordId).catch(error => {
                        cmaLog.error('[refreshComboOptions] Error:', error);
                    });
                    this._pendingComboRefresh = null;
                    delete formLayout._addRelatedCallback;
                };
            }

            // Use lib_OpenPanel which respects user preference
            lib_OpenPanel(popupUrl, 'addRelated', 800, 600);
        } else if (typeof lib_OpenWindowCentered === 'function') {
            // Fallback to centered popup
            lib_OpenWindowCentered(popupUrl, 'addRelated', 800, 600);
        } else {
            // Fallback to regular window.open
            window.open(popupUrl, 'addRelated', 'width=800,height=600');
        }
    }

    /**
     * Refresh combobox options after adding a related record
     * @param {string} fieldName Field name of the combobox
     * @param {string|null} newRecordId Optional ID of newly added record to select
     */
    refreshComboOptions(fieldName, newRecordId = null) {
        const combo = this.mainForm?.querySelector('[name="' + fieldName + '"]');
        if (!combo) {
            cmaLog.warn('[refreshComboOptions] Could not find combobox:', fieldName, 'in mainForm:', this.mainForm);
            return Promise.resolve();
        }

        cmaLog.log('[refreshComboOptions] START field:', fieldName, 'newRecordId:', newRecordId, 'combo tag:', combo.tagName, 'ajax:', combo.hasAttribute('ajax-url'));

        // Call API to get fresh options
        const params = new URLSearchParams();
        params.append('action', 'combo');
        params.append('field', fieldName);
        if (this.formId) params.append('formId', this.formId);
        if (this.jsonForm) params.append('jsonForm', this.jsonForm);
        params.append('_t', Date.now());

        const url = '/cma/form_api.php?' + params.toString();
        const isAjaxCombo = combo.tagName === 'LIB-COMBO' && combo.hasAttribute('ajax-url');

        return fetch(url, { cache: 'no-store' })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status + ' ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                cmaLog.log('[refreshComboOptions] API response:', data.success, 'options count:', data.options?.length, 'isAjaxCombo:', isAjaxCombo);
                if (data.success && Array.isArray(data.options)) {
                    if (isAjaxCombo) {
                        // Dynamic/AJAX combo - just add and select the new option
                        if (newRecordId) {
                            const newOpt = data.options.find(opt =>
                                String(opt.id ?? opt.value ?? '') === String(newRecordId));
                            const label = newOpt
                                ? (newOpt.text || newOpt.label || String(newRecordId))
                                : ('ID: ' + newRecordId);
                            cmaLog.log('[refreshComboOptions] AJAX: addOption value:', String(newRecordId), 'label:', label);
                            combo.addOption(String(newRecordId), label);
                            combo.value = String(newRecordId);
                            cmaLog.log('[refreshComboOptions] AJAX: after set, combo.value:', combo.value);
                            setTimeout(() => { cmaLog.log('[refreshComboOptions] AJAX: 500ms later, combo.value:', combo.value, '_selectedValues:', combo._selectedValues); }, 500);
                            this.setDirty(true);
                        }
                    } else {
                        // Static combo - rebuild all options
                        const currentValue = combo.value;
                        const mappedOptions = data.options.map(opt => ({
                            value: String(opt.value || opt.id || ''),
                            label: opt.label || opt.text || String(opt.value || opt.id || '')
                        }));
                        cmaLog.log('[refreshComboOptions] Static: setting', mappedOptions.length, 'options, newRecordId:', newRecordId);
                        combo.setOptions(mappedOptions);

                        if (newRecordId) {
                            const targetValue = String(newRecordId);
                            const matchingOpt = mappedOptions.find(o => o.value === targetValue);
                            cmaLog.log('[refreshComboOptions] Static: setting value to', targetValue, 'matching option:', matchingOpt);
                            combo.value = targetValue;
                            cmaLog.log('[refreshComboOptions] Static: after set, combo.value:', combo.value, '_selectedValues:', combo._selectedValues);
                            setTimeout(() => { cmaLog.log('[refreshComboOptions] Static: 500ms later, combo.value:', combo.value, '_selectedValues:', combo._selectedValues); }, 500);
                            this.setDirty(true);
                        } else if (currentValue) {
                            combo.value = currentValue;
                        }
                    }
                } else if (!data.success) {
                    cmaLog.error('refreshCombobox:', 'Combobox "' + fieldName + '" vernieuwen mislukt: ' + (data.error || 'Onbekende fout'));
                }
            })
            .catch(err => {
                cmaLog.error('refreshCombobox:', 'Combobox "' + fieldName + '" vernieuwen mislukt: ' + err.message);
            });
    }

    /**
     * Parse URL parameters
     */
    parseUrlParams() {
        const params = new URLSearchParams(window.location.search);

        // Note: We do NOT set currentRecordId here - that would cause loadRecord to skip
        // loading because it thinks the record is already loaded. Let the actual load
        // (loadRecord or formInit) set currentRecordId after data is fetched.

        // If view parameter is present, override the stored display mode
        if (params.has('view')) {
            const view = params.get('view');
            if (view === 'tree') {
                this.displayMode = this.LIST_MODE.DISPLAY_TREE;
            } else if (view === 'table') {
                this.displayMode = this.LIST_MODE.DISPLAY_TABLE;
            }
        }

        if (params.has('search')) {
            this.searchTerm = params.get('search');
            const searchInput = document.getElementById('searchfor');
            if (searchInput) {
                searchInput.value = this.searchTerm;
            }
        }

        // Parse filter context passed from parent window (for new record popups)
        // This allows the filter field to be prefilled with the parent's toolbar filter value
        if (params.has('filterField') && params.has('filterValue')) {
            const filterField = params.get('filterField');
            const filterValue = params.get('filterValue');
            this.searchFilters[filterField] = filterValue;
        }
    }

    /**
     * Handle toolbar button actions
     */
    handleToolbarAction(action, dataset = {}) {
        // cmaLog.log('handleToolbarAction:', action, 'isTableMode:', this.isTableMode(), 'directRecordMode:', this.directRecordMode, 'currentRecordId:', cmaGetRecordId());

        // In table mode, certain actions are disabled (handled by inline editing)
        // BUT: if we have a currentRecordId (viewing/editing a record), allow these actions
        // ALSO: In directRecordMode (popup/direct link), always allow these actions
        const tableDisabledActions = ['save', 'copy', 'delete', 'cancel'];
        if (this.isTableMode() && tableDisabledActions.includes(action) && !cmaGetRecordId() && !this.directRecordMode) {
            // cmaLog.log('Action blocked - table mode without record');
            return; // Ignore these actions in table mode when no record is loaded
        }

        switch (action) {
            case 'add':
                this.handleAddAction();
                break;
            case 'addInline':
                // Always use inline form for adding (used by detail toolbar)
                this.newRecord();
                break;
            case 'save':
                // cmaLog.log('Calling saveRecord...');
                // Auto-close popup after save if opened via window.opener or in iframe popup
                const closeAfterSave = this.isInPopup();
                this.saveRecord(closeAfterSave).catch(error => {
                    cmaLog.error('[saveRecord] Error:', error);
                    this.showError('Opslaan mislukt: ' + error.message);
                });
                break;
            case 'cancel':
                this.cancelChanges().catch(error => {
                    cmaLog.error('[cancelChanges] Error:', error);
                });
                break;
            case 'delete':
                this.deleteRecord().catch(error => {
                    cmaLog.error('[deleteRecord] Error:', error);
                    this.showError('Verwijderen mislukt: ' + error.message);
                });
                break;
            case 'copy':
                this.copyRecord();
                break;
            case 'close':
                this.closeForm();
                break;
            case 'preview':
                this.previewRecord();
                break;
            case 'search':
                this.showSearchDialog();
                break;
            case 'toggleSearch':
                this.toggleSearchPanel();
                break;
            case 'setlistmode':
                this.setListMode(dataset.mode);
                break;
            case 'selectColumns':
                this.showColumnSelector();
                break;
            case 'extra':
                // Handle extra buttons - url should be in dataset
                this.handleExtraButtonClick(dataset.url, dataset.title, dataset.openNewWindow === 'true');
                break;
        }
    }

    /**
     * Handle add action - in table mode shows popup, in tree mode shows inline form
     */
    handleAddAction() {
        if (this.isTableMode()) {
            // In table mode, open form.php in a popup for new record
            this.openFormPopup(null);
        } else {
            // In tree mode, use inline form
            this.newRecord();
        }
    }

    /**
     * Open form.php in a popup window using the unified openPopup function
     * @param {string|null} recordId - Record ID or null for new record
     */
    openFormPopup(recordId) {
        // All forms are now JSON-based
        const formIdentifier = this.config.jsonForm;
        const formName = this.config.formNameSingular || this.config.formName || 'Record';

        // Determine action suffix based on mode
        let actionSuffix = '';
        if (recordId === null || recordId === undefined || recordId === '') {
            actionSuffix = ' toevoegen';
        } else if (this.config.accessLevel < 2) {
            // accessLevel 0 or 1 = readonly
            actionSuffix = ' bekijken';
        } else {
            actionSuffix = ' wijzigen';
        }

        const self = this;
        this.openPopup({
            formId: formIdentifier,
            recordId: recordId,
            title: formName + actionSuffix,
            windowName: 'form_popup',
            cascadeOffset: false,
            onClose: function() {
                self.loadList();
            }
        });
    }

    /**
     * Open a form popup with standardized behavior
     * This is the unified function for opening any form in a popup
     *
     * @param {Object} options - Popup options
     * @param {string|number} options.formId - Form ID (numeric for legacy) or JSON form name (string)
     * @param {string|number|null} options.recordId - Record ID (null/0 for new, number for edit)
     * @param {string|null} options.parentId - Parent record ID for subforms
     * @param {string|null} options.parentField - Parent field name for subforms
     * @param {string} options.title - Window title
     * @param {string} options.windowName - Unique window name (for reuse)
     * @param {function|null} options.onClose - Callback when popup closes
     * @param {boolean} options.cascadeOffset - Apply cascading offset for multiple windows
     */
    openPopup(options) {
        const formId = options.formId;
        const recordId = options.recordId;
        const parentId = options.parentId || null;
        const parentField = options.parentField || '';
        const title = options.title || 'Form';
        const windowName = options.windowName || 'form_popup';
        const onClose = options.onClose || null;
        const cascadeOffset = options.cascadeOffset !== false;

        // DEBUG: Log all incoming options
        // cmaLog.log('[FormController] openPopup:', {
        //     formId: formId,
        //     recordId: recordId,
        //     parentId: parentId,
        //     parentField: parentField,
        //     title: title,
        //     windowName: windowName,
        //     jsonForm: this.jsonForm
        // });

        // Build URL - always use form= parameter
        let url = `form.php?form=${encodeURIComponent(formId)}`;
        if (recordId === null || recordId === undefined || recordId === '') {
            url += '&New=Y';
        } else {
            url += `&id=${recordId}`;
        }
        if (parentId) {
            url += `&parentID=${parentId}`;
        }
        if (parentField) {
            url += `&parentField=${encodeURIComponent(parentField)}`;
        }

        // Pass current toolbar filter value to ALL popups (new AND edit)
        // This ensures the filter context is available when clicking "Add" from within the popup
        const filterFieldName = this.config.filterFieldName;
        if (filterFieldName && this.searchFilters && this.searchFilters[filterFieldName]) {
            const filterValue = this.searchFilters[filterFieldName];
            url += `&filterField=${encodeURIComponent(filterFieldName)}`;
            url += `&filterValue=${encodeURIComponent(filterValue)}`;
            // cmaLog.log('[openPopup] Passing filter context:', filterFieldName, '=', filterValue);
        }

        // Clear any previous popup check interval to prevent leaks
        if (this._popupCheckInterval) {
            clearInterval(this._popupCheckInterval);
            this._popupCheckInterval = null;
        }

        // Calculate popup size - 85% of viewport with optional cascade offset
        let width = Math.round(window.innerWidth * 0.85);
        let height = Math.round(window.innerHeight * 0.85);

        if (cascadeOffset && typeof lib_OpenWindowCount === 'function') {
            const openWindows = lib_OpenWindowCount();
            width -= 20 + (50 * openWindows);
            height -= 50 + (75 * openWindows);
        }

        const self = this;

        // Check user preference for popup style
        const prefAvailable = typeof lib_getPopupStylePreference === 'function';
        const pref = prefAvailable ? lib_getPopupStylePreference() : 'popup';
        const useSidepanel = pref === 'sidepanel';
        // cmaLog.log('openPopup: prefAvailable=', prefAvailable, 'pref=', pref, 'useSidepanel=', useSidepanel, 'lib_OpenSidePanel available=', typeof lib_OpenSidePanel === 'function');

        if (useSidepanel && typeof lib_OpenSidePanel === 'function') {
            // Use sidepanel - opens from the right side
            lib_OpenSidePanel(url, windowName, width, toFirstCaps(title));

            // Update URL to reflect open sidepanel (for refresh persistence)
            // Uses clean URL format: /cma/form/formname/recordId/subform/subformId
            try {
                const topWin = window.top || window;

                if (topWin.CMA && topWin.CMA.url) {
                    // Use clean URL format
                    const currentState = topWin.CMA.url.parse();
                    const effectiveRecordId = (recordId !== null && recordId !== undefined && recordId !== '') ? recordId : null;

                    if (parentId) {
                        // This is a subform popup
                        topWin.CMA.url.update({
                            form: currentState.form,
                            recordId: parentId,
                            subform: formId,
                            subformId: effectiveRecordId,
                            isSubformNew: !effectiveRecordId
                        }, true);
                    } else {
                        // This is a main record popup
                        topWin.CMA.url.update({
                            form: formId,
                            recordId: effectiveRecordId,
                            isNew: !effectiveRecordId
                        }, true);
                    }
                    // cmaLog.log('[openPopup] Updated URL with clean format for:', formId, effectiveRecordId);
                } else {
                    // Fallback to legacy popupStack format
                    const popupParams = new URLSearchParams(topWin.location.search);
                    let stackStr = popupParams.get('popupStack') || '';
                    const stackItems = stackStr ? stackStr.split('|') : [];

                    const newItem = [
                        formId || '',
                        (recordId !== null && recordId !== undefined && recordId !== '') ? recordId : '0',
                        parentId || '',
                        parentField || ''
                    ].join(':');
                    stackItems.push(newItem);

                    popupParams.set('popupStack', stackItems.join('|'));
                    popupParams.delete('popup');
                    popupParams.delete('popupID');
                    popupParams.delete('popupParentID');
                    popupParams.delete('popupParentField');

                    const newUrl = topWin.location.pathname + '?' + popupParams.toString();
                    topWin.history.replaceState(null, '', newUrl);
                    // cmaLog.log('[openPopup] Updated URL with popup stack, depth:', stackItems.length);
                }
            } catch (e) {
                cmaLog.warn('[openPopup] Could not update URL for sidepanel:', e.message);
            }

            // Set up callback to execute onClose when sidepanel closes
            if (onClose) {
                // Clear any existing interval before setting a new one
                if (this._popupCheckInterval) {
                    clearInterval(this._popupCheckInterval);
                    this._popupCheckInterval = null;
                }
                let checkCount = 0;
                const maxChecks = 3600; // Max 30 minutes (3600 * 500ms)
                this._popupCheckInterval = setInterval(() => {
                    checkCount++;
                    // Must check top.lib_sidepanel_stack because lib_OpenSidePanel adds to top window
                    // The local lib_sidepanel_stack would always be empty if we're in an iframe
                    const topWindow = window.top || window;
                    const stack = topWindow.lib_sidepanel_stack;
                    if (typeof stack === 'undefined' || stack.length === 0) {
                        clearInterval(self._popupCheckInterval);
                        self._popupCheckInterval = null;
                        onClose();
                    } else if (checkCount >= maxChecks) {
                        // cmaLog.log('[openPopup] Sidepanel check timeout reached, clearing interval');
                        clearInterval(self._popupCheckInterval);
                        self._popupCheckInterval = null;
                    }
                }, 500);
            }
        } else if (typeof lib_OpenWindowCentered === 'function') {
            // Use centered popup
            lib_OpenWindowCentered(url, windowName, width, height, toFirstCaps(title));

            // Set up callback to execute onClose when popup closes
            if (onClose) {
                // Clear any existing interval before setting a new one
                if (this._popupCheckInterval) {
                    clearInterval(this._popupCheckInterval);
                    this._popupCheckInterval = null;
                }
                let checkCount = 0;
                const maxChecks = 3600; // Max 30 minutes (3600 * 500ms)
                this._popupCheckInterval = setInterval(() => {
                    checkCount++;
                    // Check if any centered window is open via lib_OpenGetTopmostWindow
                    // This searches for __lib_win1 through __lib_win20 in top.document
                    const hasOpenWindow = typeof lib_OpenGetTopmostWindow === 'function' && lib_OpenGetTopmostWindow() !== null;
                    if (!hasOpenWindow) {
                        clearInterval(self._popupCheckInterval);
                        self._popupCheckInterval = null;
                        onClose();
                    } else if (checkCount >= maxChecks) {
                        // cmaLog.log('[openPopup] Centered window check timeout reached, clearing interval');
                        clearInterval(self._popupCheckInterval);
                        self._popupCheckInterval = null;
                    }
                }, 500);
            }
        } else {
            // Fallback to standard window.open
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;

            const popup = window.open(
                url,
                windowName,
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`
            );

            // Set up callback to execute onClose when popup closes
            if (popup && onClose) {
                // Clear any existing interval before setting a new one
                if (this._popupCheckInterval) {
                    clearInterval(this._popupCheckInterval);
                    this._popupCheckInterval = null;
                }
                let checkCount = 0;
                const maxChecks = 3600; // Max 30 minutes (3600 * 500ms)
                this._popupCheckInterval = setInterval(() => {
                    checkCount++;
                    if (popup.closed) {
                        clearInterval(self._popupCheckInterval);
                        self._popupCheckInterval = null;
                        onClose();
                    } else if (checkCount >= maxChecks) {
                        // cmaLog.log('[openPopup] Window check timeout reached, clearing interval');
                        clearInterval(self._popupCheckInterval);
                        self._popupCheckInterval = null;
                    }
                }, 500);
            }
        }
    }

    /**
     * Set list display mode (1=tree, 2=table)
     */
    setListMode(mode) {
        const intMode = parseInt(mode, 10);
        // Set mode class immediately to prevent flicker
        this.setDisplayModeClass(intMode === this.LIST_MODE.DISPLAY_TABLE ? 'table' : 'tree');
        // Use the unified saveDisplayMode method
        this.saveDisplayMode(intMode);
        // Reload list with new mode
        this.loadList();
    }

    /**
     * Show column selector popup using lib_OpenWindowCentered
     * Allows user to choose which columns to display
     */
    async showColumnSelector() {
        // Guard against multiple rapid clicks
        if (this._columnSelectorLoading) {
            // cmaLog.log('showColumnSelector: already loading, ignoring click');
            return;
        }

        try {
            // Set loading flag immediately to prevent double-clicks
            this._columnSelectorLoading = true;

            // Close any existing column selector first to prevent duplicates
            const existingContent = document.getElementById('columnSelectorContent') ||
                                   (top !== window ? top.document.getElementById('columnSelectorContent') : null);
            if (existingContent) {
                // cmaLog.log('showColumnSelector: closing existing panel first');
                this.closeColumnSelector();
                // Wait for close animation
                await new Promise(resolve => setTimeout(resolve, 350));
            }

            // cmaLog.log('showColumnSelector: starting, isJsonForm:', this.isJsonForm, 'jsonForm:', this.jsonForm, 'formId:', this.formId);

            // Validate that we have a valid form identifier
            let formParam;
            try {
                formParam = this.getFormParam();
                // cmaLog.log('showColumnSelector: formParam:', formParam);
            } catch (e) {
                cmaLog.error('showColumnSelector: No valid form identifier', {jsonForm: this.jsonForm, config: this.config});
                this.showNotification('Geen geldig formulier ID beschikbaar', 'error');
                return;
            }

            // Fetch available columns from API
            const url = `/cma/form_api.php?action=columns&${this.getFormParam()}`;
            // cmaLog.log('showColumnSelector: fetching', url);
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`[showColumnSelector] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();
            // cmaLog.log('showColumnSelector: response:', data.success, 'columns count:', data.columns?.length, 'selected:', data.selected);

            if (!data.success) {
                cmaLog.error('showColumnSelector: API error:', data.error);
                this.showNotification(data.error || 'Fout bij laden kolommen', 'error');
                return;
            }

            // Build content HTML for lib_OpenWindowCentered
            const columns = data.columns || [];
            const selected = data.selected || [];
            // cmaLog.log('showColumnSelector: building popup with', columns.length, 'columns');

            // Get stored column order from localStorage
            const formId = this.jsonForm;
            let storedOrder = [];
            try {
                const prefs = JSON.parse(localStorage.getItem(`cma_v2_table_prefs_${formId}`) || '{}');
                storedOrder = prefs.columnOrder || [];
            } catch(e) {
                cmaLog.warn('[ColumnSelector] Corrupt localStorage preferences for form', formId, '- using defaults:', e.message);
            }

            // Sort columns by stored order, then alphabetically for remaining
            const orderedColumns = [...columns].sort((a, b) => {
                const aIndex = storedOrder.indexOf(a.name);
                const bIndex = storedOrder.indexOf(b.name);
                if (aIndex !== -1 && bIndex !== -1) return aIndex - bIndex;
                if (aIndex !== -1) return -1;
                if (bIndex !== -1) return 1;
                return 0;
            });

            // Map field types to Dutch names
            const typeMap = {
                'checkbox': 'vinkje',
                'bit': 'vinkje',
                'boolean': 'vinkje',
                'text': 'tekst',
                'textbox': 'tekst',
                'varchar': 'tekst',
                'nvarchar': 'tekst',
                'char': 'tekst',
                'select': 'lijst',
                'combobox': 'lijst',
                'dropdown': 'lijst',
                'combo': 'lijst',
                'memo': 'lange tekst',
                'textarea': 'lange tekst',
                'ntext': 'lange tekst',
                'int': 'tekst',
                'integer': 'tekst',
                'number': 'tekst',
                'decimal': 'tekst',
                'float': 'tekst',
                'date': 'tekst',
                'datetime': 'tekst'
            };

            let checkboxesHtml = '';
            orderedColumns.forEach(col => {
                const rawType = (col.type || '').toLowerCase();
                const typeDisplay = col.typeLabel || typeMap[rawType] || col.type || '';
                // Memo fields cannot be displayed in table view (Access ODBC limitation)
                const isMemo = ['memo', 'htmlstrip', 'textarea', 'ntext', 'lange tekst'].includes(rawType);
                const isDisabled = isMemo ? 'disabled' : '';
                const isChecked = (selected.includes(col.name) && !isMemo) ? 'checked' : '';
                const disabledClass = isMemo ? ' disabled' : '';
                const disabledTitle = isMemo ? ' data-tooltip="Lange tekst velden kunnen niet in tabelweergave worden getoond"' : '';
                checkboxesHtml += `
                    <div class="col-selector-item${disabledClass}" draggable="${!isMemo}" data-field="${col.name}"${disabledTitle}>
                        <span class="drag-handle">${isMemo ? '\u00A0' : '☰'}</span>
                        <label>
                            <input type="checkbox" name="col_${col.name}" value="${col.name}" ${isChecked} ${isDisabled}>
                            <span class="col-name">${this.escapeHtml(col.caption)}</span>
                            <span class="col-type">(${typeDisplay})</span>
                        </label>
                    </div>
                `;
            });

            const contentHtml = `
                <style>
                    .col-selector-toolbar { display:flex; align-items:center; gap:6px; padding:6px 8px; border-bottom:1px solid var(--border-color, #ddd); background:var(--bg-surface-alt, #f8f8f8); }
                    .col-selector-toolbar label { display:flex; align-items:center; margin:0; cursor:pointer; font-size:var(--font-size); }
                    .col-selector-toolbar label input { margin-right:6px; }
                    .col-selector-toolbar .toolbar-spacer { flex:1; }
                    .col-selector-list { height:calc(100% - 90px); overflow-y:auto; border:1px solid var(--border-color, #ddd); }
                    .col-selector-list:focus { outline:2px solid var(--color-primary, #007bff); outline-offset:-2px; }
                    .col-selector-item { display:flex; align-items:center; padding:5px 8px; border:1px solid transparent; border-bottom:1px solid #eee; cursor:grab; background:var(--bg-surface, #fff); }
                    .col-selector-item:hover { background:var(--bg-hover, #d0e8f8); }
                    .col-selector-item.focused { background:var(--bg-hover, #d0e8f8); border-color:var(--border-hover, #077ab2); }
                    .col-selector-item.dragging { opacity:0.5; }
                    .col-selector-item.drag-over { border-top:2px solid var(--color-primary, #007bff); }
                    .col-selector-item .drag-handle { color:#999; margin-right:8px; font-size:var(--font-size-md); }
                    .col-selector-item label { display:flex; align-items:center; flex:1; cursor:pointer; margin:0; }
                    .col-selector-item input { margin-right:8px; }
                    .col-selector-item .col-name { flex:1; }
                    .col-selector-item .col-type { color:#888; font-size:var(--font-size-xs); }
                    .col-selector-item.disabled { opacity:0.5; cursor:not-allowed; background:var(--bg-disabled, #f5f5f5); }
                    .col-selector-item.disabled:hover { background:var(--bg-disabled, #f5f5f5); border-color:transparent; }
                    .col-selector-item.disabled label { cursor:not-allowed; }
                    .col-selector-buttons { display:flex; justify-content:space-between; margin-top:10px; }
                </style>
                <div class="col-selector-toolbar">
                    <label>
                        <input type="checkbox" id="colSelectAll" checked>
                        Alles
                    </label>
                    <span class="toolbar-spacer"></span>
                    <a href="javascript:void(0)" id="colMoveUp" title="Omhoog"><span class="lnr lnr-chevron-up"></span></a>
                    <a href="javascript:void(0)" id="colMoveDown" title="Omlaag"><span class="lnr lnr-chevron-down"></span></a>
                </div>
                <div id="columnSelectorContent">
                    <div class="col-selector-list" tabindex="0">
                        ${checkboxesHtml}
                    </div>
                    <div class="col-selector-buttons">
                        <button type="button" onclick="CMA.FormController.getController()?.resetColumnPreferences()" class="btn-secondary">Standaard</button>
                        <button type="button" onclick="CMA.FormController.getController()?.saveColumnSelection()" class="btn-primary">Toon gewijzigde lijst</button>
                    </div>
                </div>
            `;

            // Use sidepanel or popup based on user preference
            const useSidepanel = typeof lib_getPopupStylePreference === 'function' &&
                                lib_getPopupStylePreference() === 'sidepanel';

            if (useSidepanel && typeof lib_OpenSidePanel === 'function') {
                lib_OpenSidePanel('', 'columnSelector', 400, 'Kolommen kiezen', contentHtml);
            } else {
                lib_OpenWindowCentered('', 'columnSelector', 400, 450, 'Kolommen kiezen', contentHtml);
            }

            // Initialize drag-drop after popup opens
            setTimeout(() => this.initColumnSelectorDragDrop(), 100);

        } catch (error) {
            cmaLog.error('Error showing column selector:', error);
            this.showNotification('Fout bij laden kolommen', 'error');
        } finally {
            // Reset loading flag
            this._columnSelectorLoading = false;
        }
    }

    /**
     * Close the column selector popup/sidepanel
     */
    closeColumnSelector() {
        // Close sidepanel or popup based on what's open
        if (typeof lib_ClosePanel === 'function') {
            lib_ClosePanel(true); // skipConfirm
        } else if (typeof lib_OpenWindowCenteredClose === 'function') {
            lib_OpenWindowCenteredClose();
        }
    }

    /**
     * Initialize drag-drop and keyboard navigation for column selector items.
     * Keyboard: Arrow keys to navigate, Space to toggle, Ctrl+Up/Down to reorder.
     */
    initColumnSelectorDragDrop() {
        // Find the content div - may be in top document
        let contentDiv = document.getElementById('columnSelectorContent');
        if (!contentDiv) {
            try {
                contentDiv = top.document.getElementById('columnSelectorContent');
            } catch (e) {
                // Cross-origin frame access
            }
        }
        if (!contentDiv) return;

        const list = contentDiv.querySelector('.col-selector-list');
        if (!list) return;

        let dragItem = null;
        let focusedIndex = -1;
        const self = this;

        function getItems() {
            return Array.from(list.querySelectorAll('.col-selector-item'));
        }

        function setFocused(index) {
            const items = getItems();
            if (index < 0 || index >= items.length) return;
            items.forEach(function(el) { el.classList.remove('focused'); });
            focusedIndex = index;
            items[index].classList.add('focused');
            items[index].scrollIntoView({ block: 'nearest' });
        }

        function moveItem(fromIndex, toIndex) {
            const items = getItems();
            if (toIndex < 0 || toIndex >= items.length) return;
            if (items[fromIndex].classList.contains('disabled')) return;
            var item = items[fromIndex];
            if (fromIndex < toIndex) {
                list.insertBefore(item, items[toIndex].nextSibling);
            } else {
                list.insertBefore(item, items[toIndex]);
            }
            setFocused(toIndex);
        }

        // Toolbar is outside #columnSelectorContent — find in parent container
        var container = contentDiv.parentElement || contentDiv;

        // Select all checkbox
        var selectAll = container.querySelector('#colSelectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                var checked = selectAll.checked;
                list.querySelectorAll('.col-selector-item:not(.disabled) input[type="checkbox"]').forEach(function(cb) {
                    cb.checked = checked;
                });
            });
        }

        // Move up/down buttons
        var btnUp = container.querySelector('#colMoveUp');
        var btnDown = container.querySelector('#colMoveDown');
        if (btnUp) {
            btnUp.addEventListener('click', function(e) {
                e.preventDefault();
                if (focusedIndex > 0) {
                    moveItem(focusedIndex, focusedIndex - 1);
                }
                list.focus();
            });
        }
        if (btnDown) {
            btnDown.addEventListener('click', function(e) {
                e.preventDefault();
                var items = getItems();
                if (focusedIndex >= 0 && focusedIndex < items.length - 1) {
                    moveItem(focusedIndex, focusedIndex + 1);
                }
                list.focus();
            });
        }

        // Click to focus item
        list.addEventListener('click', function(e) {
            var item = e.target.closest('.col-selector-item');
            if (!item) return;
            var items = getItems();
            var idx = items.indexOf(item);
            if (idx >= 0) setFocused(idx);
        });

        // Keyboard navigation on the list
        list.addEventListener('keydown', function(e) {
            var items = getItems();
            if (items.length === 0) return;

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (e.ctrlKey && focusedIndex >= 0 && focusedIndex < items.length - 1) {
                        moveItem(focusedIndex, focusedIndex + 1);
                    } else {
                        setFocused(Math.min((focusedIndex < 0 ? 0 : focusedIndex + 1), items.length - 1));
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (e.ctrlKey && focusedIndex > 0) {
                        moveItem(focusedIndex, focusedIndex - 1);
                    } else {
                        setFocused(Math.max((focusedIndex < 0 ? 0 : focusedIndex - 1), 0));
                    }
                    break;
                case ' ':
                    e.preventDefault();
                    if (focusedIndex >= 0) {
                        var cb = items[focusedIndex].querySelector('input[type="checkbox"]:not(:disabled)');
                        if (cb) cb.checked = !cb.checked;
                    }
                    break;
                case 'Home':
                    e.preventDefault();
                    setFocused(0);
                    break;
                case 'End':
                    e.preventDefault();
                    setFocused(items.length - 1);
                    break;
                case 'a':
                    if (e.ctrlKey) {
                        e.preventDefault();
                        var allChecked = true;
                        items.forEach(function(it) {
                            var c = it.querySelector('input[type="checkbox"]:not(:disabled)');
                            if (c && !c.checked) allChecked = false;
                        });
                        items.forEach(function(it) {
                            var c = it.querySelector('input[type="checkbox"]:not(:disabled)');
                            if (c) c.checked = !allChecked;
                        });
                        if (selectAll) selectAll.checked = !allChecked;
                    }
                    break;
            }
        });

        // Focus list and select first item
        list.focus();
        if (getItems().length > 0) setFocused(0);

        // Drag-drop support (mouse)
        list.querySelectorAll('.col-selector-item').forEach(function(item) {
            item.addEventListener('mousedown', function(e) {
                e.stopPropagation();
            });

            item.addEventListener('dragstart', function(e) {
                e.stopPropagation();
                dragItem = item;
                item.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            item.addEventListener('dragend', function(e) {
                e.stopPropagation();
                if (dragItem) dragItem.classList.remove('dragging');
                dragItem = null;
                list.querySelectorAll('.drag-over').forEach(function(el) { el.classList.remove('drag-over'); });
            });

            item.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (dragItem && dragItem !== item) {
                    item.classList.add('drag-over');
                }
            });

            item.addEventListener('dragleave', function() {
                item.classList.remove('drag-over');
            });

            item.addEventListener('drop', function(e) {
                e.preventDefault();
                item.classList.remove('drag-over');
                if (dragItem && dragItem !== item) {
                    var allItems = getItems();
                    var dragIndex = allItems.indexOf(dragItem);
                    var dropIndex = allItems.indexOf(item);
                    if (dragIndex < dropIndex) {
                        list.insertBefore(dragItem, item.nextSibling);
                    } else {
                        list.insertBefore(dragItem, item);
                    }
                    // Update focus to dragged item
                    setFocused(getItems().indexOf(dragItem));
                }
            });
        });
    }

    /**
     * Reset column preferences to default
     */
    resetColumnPreferences() {
        const formId = this.jsonForm;

        // Clear localStorage preferences
        try {
            localStorage.removeItem(`cma_v2_table_prefs_${formId}`);
        } catch(e) {
            cmaLog.warn('[ColumnSelector] Failed to clear localStorage preferences:', e.message);
        }

        // Close popup and reload
        this.closeColumnSelector();
        this.loadList(true); // Force refresh
        this.showNotification('Kolominstellingen gereset', 'success');
    }

    /**
     * Save column selection and reload table
     */
    async saveColumnSelection() {
        // Look in top document where lib_OpenWindowCentered places the popup
        let contentDiv = document.getElementById('columnSelectorContent');
        if (!contentDiv) {
            try {
                contentDiv = top.document.getElementById('columnSelectorContent');
            } catch (e) {
                // cmaLog.log('[ColumnSelector] Cross-origin access to top.document:', e.message);
            }
        }
        if (!contentDiv) {
            cmaLog.error('columnSelectorContent not found');
            return;
        }

        // Collect selected columns in current order
        const selectedColumns = [];
        const columnOrder = [];
        contentDiv.querySelectorAll('.col-selector-item').forEach(item => {
            const fieldName = item.dataset.field;
            columnOrder.push(fieldName);
            const cb = item.querySelector('input[type="checkbox"]');
            if (cb && cb.checked) {
                selectedColumns.push(fieldName);
            }
        });

        if (selectedColumns.length === 0) {
            this.showNotification('Selecteer ten minste één kolom', 'error');
            return;
        }

        // Save column order to localStorage
        const formId = this.jsonForm;
        try {
            const prefs = JSON.parse(localStorage.getItem(`cma_v2_table_prefs_${formId}`) || '{}');
            prefs.columnOrder = columnOrder;
            prefs.version = 1;
            localStorage.setItem(`cma_v2_table_prefs_${formId}`, JSON.stringify(prefs));
        } catch(e) {
            cmaLog.warn('Failed to save column order to localStorage:', e);
        }

        try {
            // Save to server (which sets the cookie)
            const formData = new FormData();
            formData.append('action', 'saveColumns');
            formData.append('columns', selectedColumns.join(','));

            // Add form identifier
            formData.append('jsonForm', this.jsonForm);

            const response = await fetch('/cma/form_api.php', {
                method: 'POST',
                body: formData
            });
            if (!response.ok) {
                throw new Error(`[saveColumnSelection] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();

            if (data.success) {
                this.closeColumnSelector();
                // Reload list to apply new columns
                this.loadList();
            } else {
                this.showNotification(data.error || 'Fout bij opslaan', 'error');
            }
        } catch (error) {
            cmaLog.error('Error saving column selection:', error);
            this.showNotification('Fout bij opslaan kolommen', 'error');
        }
    }

    /**
     * Escape HTML for safe display
     */
    escapeHtml(str) {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Show notification message (delegates to global cmaNotification)
     * @param {string} message - Message to display
     * @param {string} type - Notification type: 'success', 'error', 'info'
     */
    showNotification(message, type = 'info') {
        cmaNotification.show(message, type);
    }

    /**
     * Update toolbar button enabled/disabled state based on list data
     * Note: Tree/table toggle visibility is handled by CSS via body.mode-* classes
     */
    updateToolbarButtons(data) {
        const btnExpand = document.getElementById('btn_expand');
        const btnCollapse = document.getElementById('btn_collapse');

        // Expand/collapse buttons should only be visible when:
        // 1. Form has grouping configured (data.hasGrouping from API)
        // 2. We're in tree mode
        // 3. There are actually expandable folders in the rendered tree
        const hasGrouping = data && data.hasGrouping === true;

        if (!hasGrouping) {
            // No grouping configured - always hide expand/collapse buttons
            if (btnExpand) btnExpand.style.display = 'none';
            if (btnCollapse) btnCollapse.style.display = 'none';
            return;
        }

        // Expand/collapse buttons are hidden in table mode via CSS
        // In tree mode, they should be hidden when tree has no expandable folders
        if (this.displayMode === this.LIST_MODE.DISPLAY_TREE) {
            const hasCmaTree = this.listContent && this.listContent.querySelector('cma-tree') !== null;
            const hasExpandableFolders = hasCmaTree || (this.listContent && (
                this.listContent.querySelector('.f_open, .f_closed') !== null ||
                this.listContent.querySelector('li.f_open, li.f_closed') !== null
            ));

            if (btnExpand) {
                btnExpand.style.display = hasExpandableFolders ? '' : 'none';
            }
            if (btnCollapse) {
                btnCollapse.style.display = hasExpandableFolders ? '' : 'none';
            }
        }
    }

    /**
     * Update layout based on display mode (tree shows list+detail, table shows full-width list)
     * Uses body class for CSS-based layout control
     */
    updateLayoutForDisplayMode() {
        cmaLog.log('[updateLayoutForDisplayMode] displayMode:', this.displayMode, 'DISPLAY_TABLE:', this.LIST_MODE.DISPLAY_TABLE);
        const isTableMode = this.displayMode === this.LIST_MODE.DISPLAY_TABLE;

        // Set body class - CSS handles visibility of list, fold, detail panels
        this.setDisplayModeClass(isTableMode ? 'table' : 'tree');

        // In table mode without a record, ensure full-width list display
        if (isTableMode && !cmaGetRecordId()) {
            // Remove has-record class to hide detail panel and show full-width list
            document.body.classList.remove('has-record');
            document.body.classList.remove('has-subform');
        }
    }

    /**
     * Initialize table features (filtering, sorting, inline editing)
     * @param {Object} data Response data with fields and permissions
     */
    initTableFeatures(data) {
        const table = document.getElementById('listTable');
        if (!table) return;

        // Store table data for later use
        this._tableData = data;

        const rowCount = data.count || 0;
        const hasMore = data.hasMore === true;
        const totalCount = data.totalCount;

        // Display record count in toolbar
        this.updateRecordCount(rowCount, totalCount);

        // Always initialize filtering_init - it has its own threshold (MAX_FILTER_LENGTH=2500)
        // for skipping unique value checkboxes. This ensures we always have:
        // - Sorting (A-Z / Z-A)
        // - Search field in dropdown
        // - Export menu
        // Only unique value checkboxes are skipped for large datasets (>2500 rows)
        if (typeof window.filtering_init === 'function') {
            window.filtering_init(jQuery(table));
        }

        // Initialize sorting
        if (typeof window.sortables_initial === 'function') {
            window.sortables_initial(1); // Sort on first data column
        }
        if (typeof window.sortables_init === 'function') {
            window.sortables_init();
        }

        // Initialize inline editing
        if (typeof CmaInlineEdit !== 'undefined') {
            this.initInlineEdit(data);
        }

        // Initialize column manager (resize, reorder, preferences)
        this.initColumnManager(table, data);

        // Initialize infinite scroll only if there's more data
        if (hasMore) {
            // cmaLog.log('Initializing infinite scroll (hasMore:', hasMore, ', count:', rowCount, ')');
            this.initInfiniteScroll(data);

            // Auto-prefetch all remaining rows in background after initial render
            this._autoPrefetchRows();
        }
    }

    /**
     * Auto-prefetch all remaining rows in the background.
     * Loads batches sequentially until all data is fetched, updating the counter
     * after each batch. When complete, the counter is hidden (showing "1-X of X" is useless).
     */
    _autoPrefetchRows() {
        const scroller = this.infiniteScroll;
        if (!scroller || !scroller.hasMore) return;

        // Pause scroll-based loading to prevent race conditions with background fetches
        scroller.paused = true;

        const self = this;
        // Use setTimeout(0) to let the browser paint the initial batch first
        setTimeout(async function prefetchBatch() {
            if (!scroller.hasMore || scroller.destroyed) {
                scroller.paused = false;
                return;
            }
            try {
                await scroller.load();
            } catch (e) {
                cmaLog.error('[autoPrefetch] Error:', e);
                scroller.paused = false;
                return;
            }
            if (scroller.hasMore && !scroller.destroyed) {
                // Continue loading next batch
                setTimeout(prefetchBatch, 0);
            } else {
                // All data loaded — re-enable scroll handler and hide counter
                scroller.paused = false;
                self.updateRecordCount(scroller.currentCount, scroller.totalCount);
            }
        }, 0);
    }

    /**
     * Initialize only the export menu for large tables (skip dropdown filters)
     * This provides export functionality without the performance overhead of
     * building filter dropdowns for all rows.
     * @param {HTMLTableElement} table The table element
     */
    _initExportMenuOnly(table) {
        if (!table) return;

        // Ensure table has an ID for export
        if (!table.id) {
            table.id = 'listTable_' + Math.round(Math.random() * 65000);
        }

        // Check for data rows
        const rowCount = table.querySelectorAll('tbody tr:not(.nodata)').length;
        if (rowCount === 0) return;

        // Check if export is disabled
        if (table.dataset.export === 'n' || table.dataset.export === 'N') return;

        const firstTh = table.querySelector('thead th');
        if (!firstTh) return;

        // Skip if export menu already exists
        if (firstTh.querySelector('.menutrigger')) return;

        const tableName = table.dataset.name || table.id;

        // Limit: only CSV for large datasets (>15000 rows)
        const MAX_ROWS_FOR_FULL_EXPORT = 15000;
        const csvOnly = rowCount > MAX_ROWS_FOR_FULL_EXPORT;

        // Create simplified export menu (same as filtering_init but without filters)
        const trigger = document.createElement('div');
        trigger.className = 'menutrigger';

        if (csvOnly) {
            trigger.innerHTML = `
                <div class="cma-context-menu export-menu">
                    <ul>
                        <li class="export-notice" style="padding: 8px; font-size: var(--font-size-xs); color: #666; border-bottom: 1px solid #eee; cursor: default;">Bij meer dan 15.000 records is alleen CSV export beschikbaar</li>
                        <li class="exportCSV"><span class="lnr lnr-file-add"></span> Export naar CSV</li>
                    </ul>
                </div>
            `;
        } else {
            trigger.innerHTML = `
                <div class="cma-context-menu export-menu">
                    <ul>
                        <li class="exportXLS"><span class="lnr lnr-file-add"></span> Export naar Excel</li>
                        <li class="exportCSV"><span class="lnr lnr-file-add"></span> Export naar CSV</li>
                    </ul>
                </div>
            `;
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            trigger.classList.toggle('open');
        });

        // Close menu when clicking outside - tracked for cleanup
        this.addTrackedListener(document, 'click', () => {
            trigger.classList.remove('open');
        });

        // Export handlers - close menu after selection
        if (!csvOnly) {
            trigger.querySelector('.exportXLS').addEventListener('click', () => {
                trigger.classList.remove('open');
                this._exportTable(table, 'xlsx', tableName);
            });
        }
        trigger.querySelector('.exportCSV').addEventListener('click', () => {
            trigger.classList.remove('open');
            this._exportTable(table, 'csv', tableName);
        });

        // Insert inside th-header-wrapper if it exists, otherwise as first child of th
        const headerWrapper = firstTh.querySelector('.th-header-wrapper');
        if (headerWrapper) {
            headerWrapper.insertBefore(trigger, headerWrapper.firstChild);
        } else {
            firstTh.insertBefore(trigger, firstTh.firstChild);
        }
    }

    /**
     * Export table to file format
     * @param {HTMLTableElement} table The table element
     * @param {string} format Export format (xlsx, csv)
     * @param {string} tableName Name for the exported file
     */
    _exportTable(table, format, tableName) {
        // Lazy load table_functions.js if needed
        if (typeof window.lib_LoadTableFunctions === 'function') {
            window.lib_LoadTableFunctions(() => {
                this._doExportTable(table, format, tableName);
            });
        } else {
            this._doExportTable(table, format, tableName);
        }
    }

    /**
     * Extract a cell's export value from its content.
     * Handles: <a href>, <input>, <select>, <lib-switch>, checkbox, and falls back to innerText.
     * @param {HTMLTableCellElement} cell
     * @returns {string}
     */
    _extractCellValue(cell) {
        // Link: export the href
        const link = cell.querySelector('a[href]');
        if (link) {
            const href = link.getAttribute('href');
            if (href && href !== '#' && href !== 'javascript:void(0)') {
                return href;
            }
        }

        // lib-switch: export checked state
        const libSwitch = cell.querySelector('lib-switch');
        if (libSwitch) {
            return libSwitch.checked ? (libSwitch.getAttribute('label-on') || 'Ja') : (libSwitch.getAttribute('label-off') || 'Nee');
        }

        // Checkbox
        const checkbox = cell.querySelector('input[type="checkbox"]');
        if (checkbox) {
            return checkbox.checked ? 'Ja' : 'Nee';
        }

        // Select
        const select = cell.querySelector('select');
        if (select) {
            const opt = select.options[select.selectedIndex];
            return opt ? opt.text : '';
        }

        // Input / textarea
        const input = cell.querySelector('input, textarea');
        if (input) {
            return input.value || '';
        }

        return cell.innerText.trim();
    }

    /**
     * Perform the actual table export
     */
    _doExportTable(table, format, tableName) {
        if (typeof window.TableExport === 'undefined') {
            cmaLog.error('TableExport library not available');
            return;
        }

        // Create clean copy of the table
        const clone = table.cloneNode(true);
        clone.id = 'export_' + Math.random().toString(36).substr(2, 9);

        // Remove UI elements that shouldn't be exported
        clone.querySelectorAll('.menutrigger, .dropdown-filter-dropdown, .column-resize-handle').forEach(function(el) { el.remove(); });
        // Remove hidden rows/cells
        clone.querySelectorAll('tr, th, td').forEach(function(el) {
            if (el.offsetParent === null && el.parentNode) {
                // Check via style since clone is not yet in DOM — check source element visibility
                const idx = Array.from(el.parentNode.children).indexOf(el);
                const tagName = el.tagName.toLowerCase();
                const sourceParent = tagName === 'tr'
                    ? table.querySelector(el.parentNode.tagName.toLowerCase())
                    : null;
                // For rows, check source visibility
                if (tagName === 'tr' && sourceParent) {
                    const sourceRow = sourceParent.children[idx];
                    if (sourceRow && sourceRow.style.display === 'none') {
                        el.remove();
                    }
                }
            }
        });
        clone.querySelectorAll('.clicker').forEach(function(el) { el.classList.remove('clicker'); });

        // Replace cell contents with extracted values (form controls, links, etc.)
        clone.querySelectorAll('td').forEach(function(td, tdIndex) {
            // Find the matching source td to read live DOM values
            const row = td.closest('tr');
            const tbody = row ? row.closest('tbody') : null;
            const rowIndex = row ? Array.from(row.parentNode.children).indexOf(row) : -1;
            const colIndex = Array.from(row.children).indexOf(td);

            let sourceCell = null;
            if (tbody && rowIndex >= 0) {
                const sourceTbody = table.querySelector('tbody');
                if (sourceTbody && sourceTbody.children[rowIndex]) {
                    sourceCell = sourceTbody.children[rowIndex].children[colIndex];
                }
            }

            const cellToRead = sourceCell || td;
            const hasControls = cellToRead.querySelector('a[href], input, select, textarea, lib-switch');
            if (hasControls) {
                td.textContent = this._extractCellValue(cellToRead);
            }
        }.bind(this));

        // Append to body for TableExport to read
        clone.style.position = 'absolute';
        clone.style.left = '-9999px';
        document.body.appendChild(clone);

        TableExport.prototype.charset = 'charset=utf-8';

        const formatMap = {
            'xlsx': 'xlsx',
            'csv': 'csv'
        };

        const instance = new TableExport(clone, {
            formats: [formatMap[format]],
            exportButtons: false,
            trimWhitespace: true
        });

        const formatKey = format === 'xlsx' ? 'XLSX' : 'CSV';
        const exportData = instance.getExportData()[clone.id][instance.CONSTANTS.FORMAT[formatKey]];

        clone.remove();
        instance.export2file(exportData.data, exportData.mimeType, tableName, exportData.fileExtension);
    }

    /**
     * Initialize column manager for resize and reorder
     * @param {HTMLTableElement} table The table element
     * @param {Object} data Response data
     */
    initColumnManager(table, data) {
        // Destroy existing instance
        if (this.columnManager) {
            this.columnManager.destroy();
        }

        // Check if CmaTableColumnManager is available
        if (typeof CmaTableColumnManager === 'undefined') {
            cmaLog.warn('CmaTableColumnManager not loaded');
            return;
        }

        const formId = this.jsonForm;
        this.columnManager = new CmaTableColumnManager(table, formId, {
            onReset: () => this.loadList(true) // Force refresh on reset
        });

        // Store field info for field chooser
        this._allFields = (data.fields || []).map(f => ({
            name: f.name,
            caption: f.caption || f.name,
            visible: true
        }));
    }

    /**
     * Initialize infinite scroll for table view
     * @param {Object} data Response data with pagination info
     */
    initInfiniteScroll(data) {
        // Destroy existing instance
        if (this.infiniteScroll) {
            this.infiniteScroll.destroy();
        }

        // Check if CmaInfiniteScroll is available
        if (typeof CmaInfiniteScroll === 'undefined') {
            cmaLog.warn('CmaInfiniteScroll not loaded');
            return;
        }

        // IMPORTANT: The scrolling container is #c.listcontent (the parent of #listContent)
        // #listContent is just a wrapper div without overflow, so we need the parent element
        // HTML structure: <div id="c" class="listcontent"> → <div id="listContent"> → <table>
        const listContentEl = this.listContent || document.getElementById('listContent');
        const scrollContainer = listContentEl ? listContentEl.parentElement : null;
        const table = document.getElementById('listTable');

        if (!scrollContainer || !table) {
            cmaLog.warn('initInfiniteScroll: scroll container or table not found');
            return;
        }

        // cmaLog.log('initInfiniteScroll: using container', scrollContainer.id || scrollContainer.className);

        const self = this;
        this.infiniteScroll = new CmaInfiniteScroll({
            container: scrollContainer,
            table: table,
            formId: this.jsonForm,
            pageSize: data.pageSize || 500,
            loadMore: async (lastId, pageSize) => {
                return await self.loadMoreRows(lastId, pageSize);
            }
        });

        // Update from initial response
        this.infiniteScroll.updateFromResponse(data);
    }

    /**
     * Load more rows for infinite scroll
     * @param {string|number} lastId Last row ID from previous page
     * @param {number} pageSize Number of rows to fetch
     * @returns {Promise<Object>} Response with html, hasMore, lastId
     */
    async loadMoreRows(lastId, pageSize) {
        const params = new URLSearchParams({
            action: 'tree',
            displayMode: this.LIST_MODE.DISPLAY_TABLE,
            lastId: lastId,
            limit: pageSize
        });

        // Add form identifier
        params.set('jsonForm', this.jsonForm);

        // Pass through current search/filters
        if (this.searchTerm) {
            params.set('search', this.searchTerm);
        }
        if (this.searchFilters && Object.keys(this.searchFilters).length > 0) {
            params.set('filters', JSON.stringify(this.searchFilters));
        }

        try {
            const response = await fetch(`/cma/form_api.php?${params}`);
            if (!response.ok) {
                throw new Error(`[loadMoreRows] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();

            if (data.success) {
                return {
                    success: true,
                    html: data.html,
                    hasMore: data.hasMore,
                    lastId: data.lastId
                };
            }
            return { success: false, hasMore: false };
        } catch (error) {
            cmaLog.error('[loadMoreRows] Error:', error.message || error);
            return { success: false, hasMore: false };
        }
    }

    /**
     * Show field chooser modal
     */
    showFieldChooser() {
        if (typeof CmaFieldChooser === 'undefined') {
            cmaLog.warn('CmaFieldChooser not loaded');
            return;
        }

        const formId = this.jsonForm;
        const allFields = this._allFields || [];

        if (allFields.length === 0) {
            // Try to get fields from current table headers
            const table = document.getElementById('listTable');
            if (table) {
                const headers = table.querySelectorAll('thead th[data-field]');
                headers.forEach(th => {
                    allFields.push({
                        name: th.dataset.field,
                        caption: th.textContent.trim(),
                        visible: true
                    });
                });
            }
        }

        const chooser = new CmaFieldChooser({
            formId: formId,
            allFields: allFields,
            onSave: (result) => {
                // Ensure table mode stays active after save
                this.displayMode = this.LIST_MODE.DISPLAY_TABLE;
                this.saveDisplayMode(this.LIST_MODE.DISPLAY_TABLE);
                this.setDisplayModeClass('table');
                // cmaLog.log('[FieldChooser] onSave - forced displayMode to TABLE (2), result:', result);
                // Reload table to apply new column settings
                this.loadList(true);
            },
            onClose: () => {
                // Ensure table mode stays active even if cancelled
                this.displayMode = this.LIST_MODE.DISPLAY_TABLE;
                this.saveDisplayMode(this.LIST_MODE.DISPLAY_TABLE);
                this.setDisplayModeClass('table');
            }
        });

        chooser.show();
    }

    /**
     * Initialize inline editing for table view
     * @param {Object} data Response data with fields and permissions
     */
    initInlineEdit(data) {
        // Store afterPostUrl for form-controller save flow
        if (data.afterPostUrl) {
            this.afterPostUrl = data.afterPostUrl;
        }

        // Destroy existing instance if any to prevent memory leaks
        if (this.inlineEditor) {
            if (typeof this.inlineEditor.destroy === 'function') {
                this.inlineEditor.destroy();
            }
            this.inlineEditor = null;
        }

        const permissions = data.permissions || {};

        // Collect extra buttons from the detail toolbar
        const extraButtons = [];
        document.querySelectorAll('.extra-button').forEach(btn => {
            const link = btn.querySelector('a');
            if (link) {
                extraButtons.push({
                    url: link.dataset.urlTemplate || link.dataset.url || '',
                    title: link.getAttribute('title') || link.dataset.title || '',
                    icon: link.querySelector('span')?.className || '',
                    openInNewWindow: link.dataset.openNewWindow === 'true'
                });
            }
        });

        const config = {
            tableSelector: '#listTable',
            formId: this.formId,
            jsonForm: this.jsonForm,  // Pass JSON form name for popup URLs
            formName: this.config.formName || this.jsonForm || 'Form',  // Pass form name for popup titles
            formNameSingular: this.config.formNameSingular || this.config.formName || this.jsonForm || 'Form',  // Singular for action labels
            accessLevel: this.config.accessLevel || 0,
            canAdd: permissions.canAdd !== false,
            canEdit: permissions.canEdit !== false,
            canCopy: permissions.canCopy || false,
            canDelete: permissions.canDelete !== false,
            afterPostUrl: data.afterPostUrl || '',
            fields: data.fields || [],
            comboOptions: data.comboOptions || {}, // Pre-loaded combo options
            filterByFieldMap: this.getFilterByFieldMap(), // fieldName => parentFieldName for cascading combos
            addRelatedForms: data.addRelatedForms || {}, // fieldName => formName for plus buttons
            apiUrl: '/cma/form_api.php',
            extraButtons: extraButtons,
            // Callbacks for integration with form controller
            // Note: Don't set onRowClick - let CmaInlineEdit use its default behavior (openFormPopup)
            onSaveSuccess: (rowId, record) => {
                // Refresh the row data after save
                this.showNotification('Opgeslagen', 'success');
            },
            onDeleteSuccess: (rowId) => {
                this.showNotification('Verwijderd', 'success');
            }
        };

        // cmaLog.log('CmaInlineEdit: Initializing with', (config.fields || []).length, 'fields');
        this.inlineEditor = new CmaInlineEdit(config);
    }

    /**
     * Handle search form submission
     */
    handleSearch(e) {
        if (e && e.preventDefault) {
            e.preventDefault();
        }
        const searchInput = document.getElementById('searchfor');
        if (searchInput) {
            this.searchTerm = searchInput.value.trim();
            cmaLog.log('[handleSearch] displayMode:', this.displayMode, 'isTree:', this.isTreeMode(), 'searchTerm:', this.searchTerm);
            this.loadList();
        }
        return false;
    }

    /**
     * Search as you type - client-side filtering with internal debounce
     */
    searchAsYouType(value) {
        // OPTIMIZATION: Internal debounce to prevent excessive DOM queries during fast typing
        clearTimeout(this._searchAsYouTypeTimer);
        this._searchAsYouTypeTimer = setTimeout(() => {
            this._doSearchAsYouType(value);
        }, 100); // 100ms debounce for responsive feel
    }

    /**
     * Internal search implementation (called after debounce)
     */
    _doSearchAsYouType(value) {
        // Client-side filtering - no SQL calls, just show/hide items
        const trimmedValue = (value || '').trim().toLowerCase();

        // Store search term for highlighting (case-sensitive for display)
        this.searchTerm = (value || '').trim();

        // If cma-tree web component is present, use its built-in filter method
        const cmaTree = this.listContent ? this.listContent.querySelector('cma-tree') : null;
        if (cmaTree && cmaTree.filter) {
            cmaTree.filter(trimmedValue);
            return;
        }

        // Filter tree view items - check multiple possible structures:
        // 1. Simple tree: #simpletree a
        // 2. Complex tree: .complextree li (filter the li elements containing links)
        const simpleTreeItems = document.querySelectorAll('#simpletree > a');
        const complexTreeItems = document.querySelectorAll('.complextree li');
        const tableRows = document.querySelectorAll('table.listtable tbody tr.listrow');

        // If no data to filter, don't do anything
        if (simpleTreeItems.length === 0 && complexTreeItems.length === 0 && tableRows.length === 0) {
            return;
        }

        // cmaLog.log('[FormController] searchAsYouType: simpleTreeItems:', simpleTreeItems.length, 'complexTreeItems:', complexTreeItems.length, 'tableRows:', tableRows.length);

        // Filter simple tree items
        simpleTreeItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (trimmedValue === '' || text.includes(trimmedValue)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });

        // Filter complex tree items (li elements)
        complexTreeItems.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (trimmedValue === '' || text.includes(trimmedValue)) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });

        // Filter table rows
        tableRows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (trimmedValue === '' || text.includes(trimmedValue)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Apply search highlighting (only for terms > 3 characters)
        this.highlightSearchTerm();

        // Update pagination count to reflect visible rows after client-side filtering
        if (tableRows.length > 0) {
            const visibleCount = Array.from(tableRows).filter(r => r.style.display !== 'none').length;
            const countEl = document.getElementById('recordCount');
            if (countEl) {
                if (trimmedValue === '') {
                    // Search cleared - restore original count from server
                    const totalCount = this._tableData?.totalCount;
                    this.updateRecordCount(tableRows.length, totalCount);
                } else {
                    // Show filtered count
                    countEl.textContent = `${visibleCount} van ${tableRows.length} records`;
                    countEl.style.display = '';
                }
            }
        }

        // Count visible DATA items (excluding group headers) and auto-select if exactly one
        if (trimmedValue !== '') {
            this._autoSelectSingleVisibleItem(simpleTreeItems, complexTreeItems, tableRows);
        }
    }

    /**
     * Auto-select if exactly one visible data item after filtering
     * Only selects when there's a single match - doesn't select first of many
     */
    _autoSelectSingleVisibleItem(simpleTreeItems, complexTreeItems, tableRows) {
        let visibleDataItems = [];

        // Count visible simple tree items (these are all data items)
        for (const item of simpleTreeItems) {
            if (item.style.display !== 'none') {
                visibleDataItems.push({ type: 'simple', element: item });
            }
        }

        // Count visible complex tree DATA items (must have DIRECT child link with target="R")
        // Use :scope > to only match direct children, not descendants in nested tree structures
        for (const item of complexTreeItems) {
            if (item.style.display !== 'none') {
                // Only count as data item if it has a DIRECT child record link (not a group header)
                // Group headers contain nested <ul><li> with data links, so checking descendants would be wrong
                const link = item.querySelector(':scope > a[target="R"], :scope > a[data-id]');
                if (link) {
                    visibleDataItems.push({ type: 'complex', element: item, link: link });
                }
            }
        }

        // Count visible table rows
        for (const row of tableRows) {
            if (row.style.display !== 'none') {
                visibleDataItems.push({ type: 'table', element: row });
            }
        }

        // cmaLog.log('searchAsYouType: visible data items =', visibleDataItems.length);

        // Only auto-select if EXACTLY ONE visible data item
        if (visibleDataItems.length === 1) {
            const item = visibleDataItems[0];
            // cmaLog.log('searchAsYouType: auto-selecting single result');

            if (item.type === 'simple') {
                item.element.classList.add('selected', 'active');
                item.element.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                // Load the record
                const recordId = item.element.dataset.id || this._extractIdFromHref(item.element.href);
                if (recordId) {
                    this.loadRecord(recordId);
                }
            } else if (item.type === 'complex') {
                item.link.classList.add('selected', 'active');
                item.element.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                // Load the record
                const recordId = item.link.dataset.id || this._extractIdFromHref(item.link.href);
                if (recordId) {
                    this.loadRecord(recordId);
                }
            } else if (item.type === 'table') {
                item.element.classList.add('selected', 'active');
                item.element.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                // Load the record
                const recordId = item.element.dataset.id;
                if (recordId) {
                    this.loadRecord(recordId);
                }
            }
        }
    }

    /**
     * Extract record ID from href like "javascript:loadRecord('123')" or "#123"
     */
    _extractIdFromHref(href) {
        if (!href) return null;
        // Try loadRecord('123') pattern
        const loadRecordMatch = href.match(/loadRecord\(['"]?([^'")\s]+)/);
        if (loadRecordMatch) return loadRecordMatch[1];
        // Try #123 pattern
        const hashMatch = href.match(/#(\d+)/);
        if (hashMatch) return hashMatch[1];
        return null;
    }

    /**
     * Enable/disable search input based on data availability
     * Also restores the current search term to the input field
     */
    updateSearchInputState(count) {
        const searchInput = document.getElementById('searchfor');
        if (searchInput) {
            if (count > 0) {
                searchInput.disabled = false;
                searchInput.placeholder = 'Zoek...';
                // Restore search term if we have one (important when switching views)
                if (this.searchTerm && searchInput.value !== this.searchTerm) {
                    searchInput.value = this.searchTerm;
                }
                // Focus search input if visible and no search term active
                if (searchInput.offsetParent !== null && !this.searchTerm) {
                    searchInput.focus();
                }
            } else {
                searchInput.disabled = true;
                searchInput.placeholder = 'Geen gegevens';
                searchInput.value = '';
            }
        }
    }

    /**
     * Update record count display in toolbar
     * Shows "records 1-X van Y" format when total count is available
     * Only shows when the list content is scrollable (has overflow)
     * @param {number} currentCount Current number of loaded records
     * @param {number|null} totalCount Total records in dataset (null if unknown)
     */
    updateRecordCount(currentCount, totalCount) {
        const countEl = document.getElementById('recordCount');
        if (!countEl) return;

        // Only show for table mode
        if (!this.isTableMode()) {
            countEl.style.display = 'none';
            return;
        }

        // Hide when search panel filters are active
        if (this.searchFilters && Object.keys(this.searchFilters).length > 0) {
            countEl.style.display = 'none';
            return;
        }

        // Check if list content is scrollable - only show count if content overflows
        const listContent = this.listContent || document.getElementById('listContent');
        if (listContent) {
            const isScrollable = listContent.scrollHeight > listContent.clientHeight;
            if (!isScrollable) {
                countEl.style.display = 'none';
                return;
            }
        }

        if (totalCount !== null && totalCount !== undefined && totalCount > 0) {
            // Show "records 1-X van Y" format, but hide if showing all records
            const endRecord = Math.min(currentCount, totalCount);
            if (endRecord >= totalCount) {
                // All records shown - hide the count
                countEl.style.display = 'none';
            } else {
                countEl.textContent = `records 1-${endRecord} van ${totalCount}`;
                countEl.style.display = '';
            }
        } else if (currentCount > 0) {
            // No totalCount available, just show current count
            countEl.textContent = `${currentCount} records`;
            countEl.style.display = '';
        } else {
            countEl.style.display = 'none';
        }
    }

    /**
     * Clear search highlights from list content
     */
    clearSearchHighlights() {
        const container = this.listContent;
        if (!container) {
            return;
        }

        // Find all mark elements with search-highlight class and unwrap them
        const marks = container.querySelectorAll('mark.search-highlight');
        marks.forEach(mark => {
            const parent = mark.parentNode;
            while (mark.firstChild) {
                parent.insertBefore(mark.firstChild, mark);
            }
            parent.removeChild(mark);
        });
    }

    /**
     * Highlight search term in list content (tree or table)
     * Only highlights if search term is >= 3 characters
     */
    highlightSearchTerm() {
        // First clear existing highlights
        this.clearSearchHighlights();

        const term = this.searchTerm;
        // cmaLog.log('[highlight] searchTerm =', term, 'length =', term?.length);

        if (!term || term.length < 3) {
            // cmaLog.log('[highlight] skipping - term too short or empty');
            return;
        }

        const container = this.listContent;
        if (!container) {
            // cmaLog.log('[highlight] skipping - no container');
            return;
        }

        // cmaLog.log('[highlight] applying highlights for term:', term);

        // Escape regex special characters
        const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp('(' + escapedTerm + ')', 'gi');

        // Walk text nodes and wrap matches in <mark> tags
        const walker = document.createTreeWalker(
            container,
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip script tags and already highlighted content
                    const parent = node.parentNode;
                    if (parent.tagName === 'SCRIPT' || parent.tagName === 'STYLE' || parent.tagName === 'MARK') {
                        return NodeFilter.FILTER_REJECT;
                    }
                    // Only process nodes with matching text
                    if (regex.test(node.textContent)) {
                        regex.lastIndex = 0; // Reset regex state
                        return NodeFilter.FILTER_ACCEPT;
                    }
                    return NodeFilter.FILTER_REJECT;
                }
            }
        );

        const nodesToReplace = [];
        let node;
        while (node = walker.nextNode()) {
            nodesToReplace.push(node);
        }

        // Replace text nodes with highlighted HTML
        nodesToReplace.forEach(function(textNode) {
            const text = textNode.textContent;
            const parts = text.split(regex);
            if (parts.length <= 1) return;

            const fragment = document.createDocumentFragment();
            parts.forEach(function(part) {
                // Reset lastIndex BEFORE test (critical for global regex state)
                regex.lastIndex = 0;
                if (regex.test(part)) {
                    const mark = document.createElement('mark');
                    mark.className = 'search-highlight';
                    mark.textContent = part;
                    fragment.appendChild(mark);
                } else if (part) {
                    fragment.appendChild(document.createTextNode(part));
                }
            });

            textNode.parentNode.replaceChild(fragment, textNode);
        });
    }

    /**
     * Show advanced search dialog
     */
    showSearchDialog() {
        // For now just focus the search input
        const searchInput = document.getElementById('searchfor');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }
    }

    /**
     * Toggle the expandable search panel
     */
    toggleSearchPanel() {
        const panel = document.getElementById('searchPanel');
        const btn = document.getElementById('btn_search');

        if (!panel) return;

        const isVisible = panel.style.display !== 'none';

        if (isVisible) {
            panel.style.display = 'none';
            if (btn) btn.classList.remove('active');
        } else {
            panel.style.display = 'block';
            if (btn) btn.classList.add('active');
            // Load lazy combo options
            this.loadSearchCombos();
            // Focus first search input
            const firstInput = panel.querySelector('.search-input');
            if (firstInput) {
                firstInput.focus();
            }
            // Initialize datepickers if jQuery UI is available
            this.initSearchDatepickers();
            // Bind Enter key to search
            this.bindSearchEnterKey();
        }
    }

    /**
     * Bind Enter key to trigger search in search panel
     */
    bindSearchEnterKey() {
        const panel = document.getElementById('searchPanel');
        if (!panel || panel.dataset.enterBound) return;

        panel.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.applySearchFilters();
            }
        });
        panel.dataset.enterBound = 'true';
    }

    /**
     * Load options for lazy-loaded search combos
     * Uses client-side cache to reduce server requests
     */
    async loadSearchCombos() {
        const lazyCombos = document.querySelectorAll('.search-combo-lazy:not(.loaded)');
        if (lazyCombos.length === 0) return;

        const cacheFormId = this.getCacheFormId();
        const recordId = cmaGetRecordId();

        for (const combo of lazyCombos) {
            const fieldName = combo.dataset.field;
            if (!fieldName) continue;

            try {
                let options = null;

                // Check cache first (include recordId for record-dependent combos)
                const cachedOptions = cmaComboCache.get(cacheFormId, fieldName, recordId);
                if (cachedOptions) {
                    options = cachedOptions;
                } else {
                    // Fetch from server
                    const response = await fetch(`/cma/form_api.php?action=combo&${this.getFormParam()}&field=${encodeURIComponent(fieldName)}`);
                    if (!response.ok) {
                        throw new Error(`[loadSearchCombos] HTTP ${response.status} ${response.statusText} for ${fieldName}`);
                    }
                    const result = await response.json();

                    if (result.success && result.options) {
                        options = result.options;
                        cmaComboCache.set(cacheFormId, fieldName, options, recordId);
                    }
                }

                if (options) {
                    const currentValue = combo.value;

                    // For lib-combo: build options array including the "-- Alle --" empty option
                    const allOptions = [{ value: '', label: '-- Alle --' }];
                    options.forEach(opt => {
                        allOptions.push({ value: String(opt.id), label: opt.text });
                    });
                    combo.setOptions(allOptions);

                    // Restore selected value if any
                    if (currentValue) {
                        combo.value = currentValue;
                    }
                }

                // Mark as loaded
                combo.classList.add('loaded');
            } catch (e) {
                cmaLog.error('Failed to load search combo options:', fieldName, e);
            }
        }
    }

    /**
     * Initialize datepickers in search panel
     * Note: Now uses <lib-datepicker> web component which self-initializes
     */
    initSearchDatepickers() {
        // No-op: lib-datepicker web component initializes automatically
        // Kept for backwards compatibility in case called from other code
    }

    /**
     * Apply search filters from the search panel
     */
    async applySearchFilters() {
        cmaLog.log('[applySearchFilters] displayMode before search:', this.displayMode, 'isTreeMode:', this.displayMode === this.LIST_MODE.DISPLAY_TREE);
        const panel = document.getElementById('searchPanel');
        if (!panel) return;

        // Collect all filter values
        const filters = {};
        const inputs = panel.querySelectorAll('.search-input');

        inputs.forEach(input => {
            const name = input.name || input.getAttribute('name');
            const value = (input.value || '').trim();

            if (name && value) {
                // Remove 'search_' prefix for the actual field name
                const fieldName = name.replace(/^search_/, '').replace(/_from$|_to$/, '');
                const suffix = name.endsWith('_from') ? '_from' : (name.endsWith('_to') ? '_to' : '');

                if (suffix) {
                    // Date/number range field
                    if (!filters[fieldName] || typeof filters[fieldName] !== 'object') {
                        filters[fieldName] = {};
                    }
                    const key = suffix.replace('_', '');
                    filters[fieldName][key] = value;
                } else {
                    filters[fieldName] = value;
                }
            }
        });

        // Preserve toolbar filter if set (forms with FilterFieldName)
        const filterFieldName = this.config.filterFieldName;
        if (filterFieldName && this.searchFilters && this.searchFilters[filterFieldName]) {
            // Keep the toolbar filter value
            filters[filterFieldName] = this.searchFilters[filterFieldName];
        }

        // Store filters and reload list (showLoading is called by loadList)
        this.searchFilters = filters;
        this.updateSearchPanelState();

        // If on a detail view, switch back to list view
        if (document.body.classList.contains('has-record')) {
            this.hideDetailPanel();
            // Update URL to remove record ID
            if (typeof cmaUrlManager !== 'undefined') {
                cmaUrlManager.navigateTo(this.formId);
            }
        }

        await this.loadList();
    }

    /**
     * Apply toolbar filter (for forms with FilterFieldName)
     * @param {string} value - Selected filter value
     */
    applyToolbarFilter(value) {
        const filterFieldName = this.config.filterFieldName;
        if (!filterFieldName) return;

        // Save to localStorage
        this.saveToolbarFilter(value);

        // Update the toolbar filter while preserving other search filters
        if (value) {
            this.searchFilters = { ...this.searchFilters, [filterFieldName]: value };
        } else {
            const { [filterFieldName]: removed, ...rest } = this.searchFilters || {};
            this.searchFilters = rest;
        }

        this.loadList();
    }

    /**
     * Save toolbar filter value to localStorage
     * Uses filterFieldName as key so the same filter field shares values across forms
     */
    saveToolbarFilter(value) {
        try {
            const filterFieldName = this.config.filterFieldName;
            if (!filterFieldName) return;

            const key = 'cma_filter_field_' + filterFieldName;
            if (value) {
                localStorage.setItem(key, value);
            }
        } catch (e) {
            // cmaLog.log('[localStorage] Filter value storage unavailable:', e.message);
        }
    }

    /**
     * Load toolbar filter value from localStorage and apply it
     * Uses filterFieldName as key so the same filter field shares values across forms
     */
    loadToolbarFilter() {
        const combo = document.getElementById('toolbarFilter');
        if (!combo) return;

        const self = this;
        const filterFieldName = this.config.filterFieldName;

        // Bind change event on lib-combo
        combo.addEventListener('change', function() {
            self.applyToolbarFilter(this.value);
        });

        // Load saved value from localStorage and add to searchFilters for the API call
        if (!filterFieldName) return;

        try {
            const key = 'cma_filter_field_' + filterFieldName;
            const savedValue = localStorage.getItem(key);
            if (savedValue) {
                // Always trust localStorage — the option may not be in the DOM yet
                // (web component upgrade timing). _syncToolbarFilter will set the display later.
                this.searchFilters = { ...this.searchFilters, [filterFieldName]: savedValue };
            }
        } catch (e) {
            // localStorage unavailable
        }
    }

    /**
     * Sync toolbar filter combo display with current searchFilters value.
     * Called after formInit completes. Retries until the web component has its
     * options populated (handles upgrade timing race condition).
     */
    _syncToolbarFilter() {
        const combo = document.getElementById('toolbarFilter');
        if (!combo) return;

        const filterFieldName = this.config.filterFieldName;
        if (!filterFieldName) return;

        const filterValue = (this.searchFilters || {})[filterFieldName] || '';
        this._applyToolbarFilterDisplay(combo, filterValue, 0);
    }

    /**
     * Apply the filter value to the combo display, retrying if the component
     * hasn't loaded its options yet. Max 10 attempts over ~1s.
     */
    _applyToolbarFilterDisplay(combo, filterValue, attempt) {
        const maxAttempts = 10;
        const comboReady = combo._options && combo._options.length > 0;

        if (!comboReady && attempt < maxAttempts) {
            // Combo not ready yet — retry after a frame
            setTimeout(() => this._applyToolbarFilterDisplay(combo, filterValue, attempt + 1), 100);
            return;
        }

        if (filterValue) {
            // Check if value exists in the combo's options
            const hasOption = comboReady && combo._options.some(o => String(o.value) === String(filterValue));
            if (hasOption) {
                $('#toolbarFilter').val(filterValue);
            } else if (comboReady) {
                // Saved value no longer exists in the options (e.g. deleted record).
                // Clear stale filter so the list shows unfiltered data.
                const filterFieldName = this.config.filterFieldName;
                if (filterFieldName) {
                    cmaLog.warn('[Filter] Stale filter value', filterValue, 'not found in', filterFieldName, '- clearing');
                    const { [filterFieldName]: removed, ...rest } = this.searchFilters || {};
                    this.searchFilters = rest;
                    try {
                        localStorage.removeItem('cma_filter_field_' + filterFieldName);
                    } catch (e) {}
                    // Reload list without the stale filter
                    this.loadList();
                }
            }
        } else if (combo._open) {
            // Auto-open if no filter is selected
            combo._open();
        }
    }

    /**
     * Save record ID as a cross-form filter value when the form has filterIdName.
     * e.g. Opleidingen form has filterIdName="fkOpleiding" — opening opleiding 38
     * saves localStorage key cma_filter_field_fkOpleiding = 38, so rooster auto-filters.
     */
    _saveFilterId(recordId) {
        const filterIdName = this.config.filterIdName;
        if (!filterIdName || recordId === null || recordId === undefined || recordId === '') return;

        try {
            const key = 'cma_filter_field_' + filterIdName;
            localStorage.setItem(key, recordId);
        } catch (e) {}
    }

    /**
     * Check if toolbar filter changed in localStorage (e.g. from another form instance)
     * and refresh combo + data if needed. Called on pageshow (bfcache restore).
     */
    _checkToolbarFilterChanged() {
        const filterFieldName = this.config.filterFieldName;
        if (!filterFieldName) return;

        try {
            const key = 'cma_filter_field_' + filterFieldName;
            const storedValue = localStorage.getItem(key) || '';
            const currentValue = (this.searchFilters || {})[filterFieldName] || '';

            if (storedValue !== currentValue) {
                // Value changed — update combo and reload data
                if (storedValue) {
                    this.searchFilters = { ...this.searchFilters, [filterFieldName]: storedValue };
                } else {
                    const { [filterFieldName]: removed, ...rest } = this.searchFilters || {};
                    this.searchFilters = rest;
                }
                $('#toolbarFilter').val(storedValue);
                this.loadList();
            }
        } catch (e) {}
    }

    /**
     * Clear all search filters
     */
    clearSearchFilters() {
        const panel = document.getElementById('searchPanel');
        if (!panel) return;

        // Clear all inputs (both primary and extra fields)
        const inputs = panel.querySelectorAll('.search-input');
        inputs.forEach(input => {
            if (input.tagName === 'LIB-COMBO') {
                input.value = '';
            } else if (input.tagName === 'SELECT') {
                input.selectedIndex = 0;
            } else {
                input.value = '';
            }
        });

        // Clear quick search too
        const searchInput = document.getElementById('searchfor');
        if (searchInput) {
            searchInput.value = '';
        }

        // Clear stored filters and reload
        this.searchFilters = {};
        this.searchTerm = '';
        this.updateSearchPanelState();
        this.loadList();
    }

    /**
     * Toggle showing more search fields
     */
    toggleSearchMore() {
        const extraFields = document.getElementById('searchFieldsExtra');
        const btn = document.getElementById('searchMoreBtn');
        if (!extraFields || !btn) return;

        const isVisible = extraFields.style.display !== 'none';
        if (isVisible) {
            extraFields.style.display = 'none';
            btn.classList.remove('expanded');
            btn.innerHTML = '<span class="lnr lnr-chevron-down"></span> Meer velden';
        } else {
            extraFields.style.display = 'grid';
            btn.classList.add('expanded');
            btn.innerHTML = '<span class="lnr lnr-chevron-down"></span> Minder velden';
        }
    }

    /**
     * Update search panel visual state (show if filters active)
     */
    updateSearchPanelState() {
        const panel = document.getElementById('searchPanel');
        if (!panel) return;

        const hasFilters = this.searchFilters && Object.keys(this.searchFilters).length > 0;
        panel.classList.toggle('has-filters', hasFilters);

        // Show/hide reset button
        const resetBtn = document.getElementById('searchResetBtn');
        if (resetBtn) {
            resetBtn.style.display = hasFilters ? '' : 'none';
        }

        // Hide record count when search filters are active
        const countEl = document.getElementById('recordCount');
        if (countEl) {
            if (hasFilters) {
                countEl.style.display = 'none';
            }
            // When filters are cleared, updateRecordCount() will restore visibility on next list load
        }
    }

    // =========================================================================
    // List Operations
    // =========================================================================

    /**
     * Load list data via AJAX - uses tree HTML from form_api.php
     * Uses AbortController to cancel in-flight requests and prevent race conditions
     * @param {boolean} forceRefresh - If true, bypass cache with timestamp parameter
     */
    async loadList(forceRefresh = false, skipRecordLoad = false) {
        // Use web component for table mode if enabled
        if (this.useWebComponentTable && this.displayMode === this.LIST_MODE.DISPLAY_TABLE) {
            return this.loadListWebComponent(forceRefresh);
        }

        // Performance tracking
        const perfId = 'loadList_' + Date.now();
        cmaPerf.start(perfId, { formId: this.formId, mode: this.displayMode });
        cmaPerf.count('loadList.calls');

        // Refresh DOM references in case they became stale (e.g., after AJAX reload)
        this.listContent = document.getElementById('listContent');
        if (!this.listContent) {
            cmaLog.error('loadList: listContent element not found');
            cmaPerf.end(perfId, { error: 'listContent not found' });
            return;
        }

        this.showLoading('loadList');

        // Cancel any in-flight request to prevent race conditions
        if (this._abortController) {
            this._abortController.abort();
            cmaPerf.count('loadList.aborted');
        }
        this._abortController = new AbortController();
        const signal = this._abortController.signal;

        // Show list loading indicator (lib-loader has built-in delay)
        const loadingEl = document.getElementById('listLoader');
        if (loadingEl && loadingEl.show) loadingEl.show();

        cmaPerf.mark('loadList_fetchStart');

        try {
            cmaLog.log('[loadList] displayMode:', this.displayMode, 'isTreeMode:', this.displayMode === this.LIST_MODE.DISPLAY_TREE, 'filters:', this.searchFilters);
            const params = new URLSearchParams({
                action: 'tree',
                displayMode: this.displayMode,
            });

            // Add form identifier
            params.set('jsonForm', this.jsonForm);

            // Pass through nocache parameter if present in URL, or if forceRefresh is true
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('nocache') || forceRefresh) {
                params.set('_t', Date.now()); // Cache busting timestamp
            }

            const currentRecordIdForParams = cmaGetRecordId();
            if (currentRecordIdForParams) {
                params.set('ID', currentRecordIdForParams);
            }

            // Add parent context (for filtering list by parent record)
            if (this.parentID) {
                params.set('parentID', this.parentID);
            }
            if (this.parentField) {
                params.set('parentField', this.parentField);
            }

            if (this.searchTerm) {
                params.set('search', this.searchTerm);
                // cmaLog.log('loadList: searchTerm =', this.searchTerm);
            }

            // Add search panel filters
            if (this.searchFilters && Object.keys(this.searchFilters).length > 0) {
                params.set('filters', JSON.stringify(this.searchFilters));
                // cmaLog.log('loadList: searchFilters =', this.searchFilters);
            } else {
                // cmaLog.log('loadList: NO searchFilters (empty or not set)');
            }

            const url = `/cma/form_api.php?${params}`;
            // cmaLog.log('loadList: fetching', url);
            const response = await fetch(url, { signal });
            cmaPerf.mark('loadList_fetchEnd');
            cmaPerf.measure('loadList.network', 'loadList_fetchStart', 'loadList_fetchEnd');

            cmaPerf.mark('loadList_parseStart');
            let data;

            // Use improved API error handler for better error display
            try {
                data = await cmaApiError.handleResponse(response, 'Lijst laden');
            } catch (apiError) {
                // cmaApiError provides detailed error info
                cmaLog.error('loadList API error:', apiError);
                if (apiError.debug) {
                    cmaLog.error('Debug info:', apiError.debug);
                }
                throw apiError;
            }
            cmaPerf.mark('loadList_parseEnd');
            cmaPerf.measure('loadList.jsonParse', 'loadList_parseStart', 'loadList_parseEnd');

            if (data.success) {
                cmaPerf.mark('loadList_renderStart');

                // Hide the loading indicator
                clearTimeout(this.listLoadingTimer);
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }

                // DISABLED: With infinite scrolling, we no longer need to auto-open search for large datasets
                // Check if filtering is required - auto-open search panel only for TOO_MANY_RECORDS (filterMode=2)
                // Don't auto-open for REPOSITORY_FORCED (filterMode=3) since that uses toolbar filter
                // if (data.requiresFilter && data.filterMode === 2) {
                //     cmaLog.log('loadList: too many records, opening search panel');
                //     const panel = document.getElementById('searchPanel');
                //     const btn = document.getElementById('btn_search');
                //     if (panel) {
                //         panel.style.display = 'block';
                //         if (btn) btn.classList.add('active');
                //         this.initSearchDatepickers();
                //         this.bindSearchEnterKey();
                //         // Focus first input
                //         const firstInput = panel.querySelector('.search-input');
                //         if (firstInput) firstInput.focus();
                //     }
                // }

                // Insert tree HTML directly
                if (this.listContent) {
                    // cma-tree web component for grouped trees (new path)
                    if (data.treeData && data.treeData.length > 0) {
                        cmaLog.log('loadList: using cma-tree with', data.treeData.length, 'root nodes');
                        const storageKey = 'tree_' + (this.config.formName || this.formId);
                        const formClass = this.config.formName ? this.config.formName.toLowerCase().replace(/ /g, '_') : '';
                        this.listContent.innerHTML = '';
                        this.listContent.style.display = 'block';

                        var tree = this.listContent.querySelector('cma-tree');
                        if (!tree) {
                            tree = document.createElement('cma-tree');
                            tree.setAttribute('storage-key', storageKey);
                            if (formClass) tree.setAttribute('item-icon', formClass);
                            tree.style.display = 'block';
                            tree.style.height = '100%';
                            this.listContent.appendChild(tree);
                        }
                        tree.setData(data.treeData);

                        if (!tree._formClickBound) {
                            tree._formClickBound = true;
                            const self = this;
                            tree.addEventListener('item-click', function(e) {
                                const recordId = e.detail.id || e.detail.nodeId;
                                if (recordId) {
                                    self.loadRecord(recordId);
                                }
                            });
                        }

                        if (this._activeRecordId) {
                            tree.selectById(String(this._activeRecordId));
                        }
                    } else if (data.html && data.html.indexOf('<script>') !== -1) {
                        // Extract script content
                        const scriptMatch = data.html.match(/<script>([\s\S]*?)<\/script>/);
                        const htmlWithoutScript = data.html.replace(/<script>[\s\S]*?<\/script>/, '');

                        // Set HTML first (may be empty for grouped trees)
                        this.listContent.innerHTML = htmlWithoutScript;
                        this.listContent.style.display = 'block';

                        // Execute tree script to build tree structure
                        if (scriptMatch && scriptMatch[1]) {
                            // Check if ftiens4.js is loaded (gFld function should exist)
                            if (typeof gFld !== 'function') {
                                cmaLog.error('ftiens4.js not loaded - tree functions unavailable');
                                this.listContent.innerHTML = '<div class="list-error">Tree bibliotheek niet geladen. <a href="javascript:location.reload()">Vernieuw de pagina</a>.</div>';
                                // Don't return - let finally block clean up
                                throw new Error('ftiens4.js not loaded');
                            }

                            try {
                                // Execute tree script - it will store tree root on listContent element
                                // No window globals needed - tree uses local variables and element storage
                                eval(scriptMatch[1]);
                                // Initialize tree rendering to specific element (reads tree root from element._treeRoot)
                                if (typeof initializeToElement === 'function') {
                                    const formClass = this.config.formName ? this.config.formName.toLowerCase().replace(/ /g, '_') : '';
                                    // cmaLog.log('loadList: calling initializeToElement for tree_' + this.formId);
                                    // Wait for jQuery to be available before initializing tree
                                    this.waitForJQuery(() => {
                                        // cmaLog.log('loadList: initializeToElement executing now');
                                        initializeToElement('listContent', 'tree_' + this.formId, '', formClass);
                                    });
                                } else {
                                    cmaLog.error('initializeToElement not found - using document.write fallback');
                                }
                            } catch (e) {
                                cmaLog.error('Tree script error:', e);
                                // cmaLog.log('Script content:', scriptMatch[1].substring(0, 500) + '...');

                                // Provide more informative error message
                                let errorMsg = 'Fout bij laden boomstructuur';
                                if (e.message.includes('addChild')) {
                                    errorMsg = 'Boomstructuur fout: groepering data is inconsistent (lege groep waarde?)';
                                    cmaLog.error('Tree hierarchy error - likely empty group value in data. Check Group1Field/Group2Field/Group3Field values.');
                                } else if (e.message.includes('undefined')) {
                                    errorMsg = 'Boomstructuur fout: ontbrekende variabele (' + e.message + ')';
                                } else {
                                    errorMsg += ': ' + e.message;
                                }

                                this.listContent.innerHTML = '<div class="list-error">' + this.escapeHtml(errorMsg) + '</div>';
                            }
                        }
                    } else {
                        // Simple tree or table - just set HTML
                        // cmaLog.log('loadList: setting simple tree/table HTML, length:', data.html?.length);
                        this.listContent.innerHTML = data.html;
                        this.listContent.style.display = 'block';
                        // cmaLog.log('loadList: listContent updated, childElementCount:', this.listContent.childElementCount);

                        // Initialize table features for table view
                        if (this.isTableMode()) {
                            this.initTableFeatures(data);
                        } else if (data.fields && data.fields.length > 0) {
                            // Store field info for field chooser even in tree mode
                            this._allFields = data.fields.map(f => ({
                                name: f.name,
                                caption: f.caption || f.name,
                                visible: true
                            }));
                        }
                    }
                }

                // FK display values are resolved server-side in getTableHtml

                // Update count
                const countEl = document.getElementById('listCount');
                if (countEl) {
                    countEl.textContent = (data.count || 0) + ' items';
                }

                // Enable/disable search-as-you-type based on data availability
                this.updateSearchInputState(data.count || 0);

                // Bind click events to tree items
                this.bindTreeEvents();

                // Highlight search term in results (if > 3 chars)
                this.highlightSearchTerm();

                // Enable predictive prefetching for faster record loading
                this.enablePrefetch();

                // Update toolbar button visibility based on list type
                this.updateToolbarButtons(data);

                // Show/hide detail panel based on display mode
                this.updateLayoutForDisplayMode();

                // Load record if specified in URL (for popups opened with ID parameter)
                // Skip if skipRecordLoad is true (e.g., after save - form already has the data)
                const currentRecordIdForLoad = cmaGetRecordId();
                if (currentRecordIdForLoad && !skipRecordLoad) {
                    // cmaLog.log('loadList: currentRecordId is set, calling loadRecord:', currentRecordIdForLoad, 'forceRefresh:', forceRefresh);
                    await this.loadRecord(currentRecordIdForLoad, forceRefresh);
                    // cmaLog.log('loadList: loadRecord completed');
                    // Highlight the selected item in tree/list
                    this.selectListItem(currentRecordIdForLoad);
                } else if (currentRecordIdForLoad && skipRecordLoad) {
                    // cmaLog.log('loadList: skipping loadRecord (skipRecordLoad=true), just selecting item in list');
                    this.selectListItem(currentRecordIdForLoad);
                }
                // Auto-select single record: if only 1 result and no record selected yet
                else if (data.count === 1) {
                    // cmaLog.log('Auto-select: count=1, isTableMode=', this.isTableMode(), 'currentRecordId=', this.currentRecordId);

                    if (!this.isTableMode()) {
                        // Tree mode: find the single record link
                        const singleLink = this.listContent?.querySelector('a[target="R"][data-id]');
                        // cmaLog.log('Auto-select tree: singleLink=', singleLink);
                        if (singleLink) {
                            // Use data-id attribute directly (more reliable than parsing href)
                            const recordId = singleLink.dataset.id;
                            if (recordId) {
                                // cmaLog.log('Auto-selecting single record (tree):', recordId);
                                const success = await this.loadRecord(recordId);
                                if (success) {
                                    // Use selectListItem for consistent styling (same as click)
                                    this.selectListItem(recordId);
                                    // Also ensure the link itself has active class
                                    singleLink.classList.add('active');
                                }
                            }
                        } else {
                            // cmaLog.log('Auto-select tree: no link found with a[target="R"][data-id]');
                        }
                    } else {
                        // Table mode: find the single row
                        const singleRow = this.listContent?.querySelector('tr.listrow[data-id]');
                        // cmaLog.log('Auto-select table: singleRow=', singleRow);
                        if (singleRow) {
                            const recordId = singleRow.dataset.id;
                            if (recordId) {
                                // cmaLog.log('Auto-selecting single record (table):', recordId);
                                await this.loadRecord(recordId);
                                // Use selectListItem for consistent styling (same as click)
                                this.selectListItem(recordId);
                                singleRow.classList.add('active');
                            }
                        } else {
                            // cmaLog.log('Auto-select table: no row found with tr.listrow[data-id]');
                        }
                    }
                }
                // Tree mode: load and highlight last selected record
                // This shows the last viewed record when returning to tree mode
                else if (!this.isTableMode() && !cmaGetRecordId()) {
                    const lastRecordId = this.loadLastRecordId();
                    if (lastRecordId) {
                        // cmaLog.log('Tree mode: loading last selected record:', lastRecordId);
                        // Load the record and highlight it in the list
                        const success = await this.loadRecord(lastRecordId);
                        if (success) {
                            this.selectListItem(lastRecordId);
                        }
                    }
                }

                cmaPerf.mark('loadList_renderEnd');
                cmaPerf.measure('loadList.render', 'loadList_renderStart', 'loadList_renderEnd');
                cmaPerf.end(perfId, { rows: data.count || 0, success: true });
                cmaPerf.count('loadList.success');
            } else {
                // Hide loading, show error in list content
                clearTimeout(this.listLoadingTimer);
                if (loadingEl) {
                    loadingEl.style.display = 'none';
                }
                if (this.listContent) {
                    // Format error message - convert newlines to <br>
                    let errorMsg = data.error || 'Laden mislukt';
                    errorMsg = errorMsg.replace(/\n/g, '<br>');
                    this.listContent.innerHTML = '<lib-message type="error" style="margin: 20px;">' + errorMsg + '</lib-message>';
                    this.listContent.style.display = 'block';
                }
                cmaPerf.end(perfId, { error: data.error, success: false });
                cmaPerf.count('loadList.errors');
            }
        } catch (error) {
            // Ignore abort errors - they're intentional when a new request supersedes an old one
            if (error.name === 'AbortError') {
                // cmaLog.log('loadList: request aborted (superseded by newer request)');
                cmaPerf.count('loadList.aborted');
                return; // Don't show error or update UI for aborted requests
            }
            cmaLog.error('List load error:', error.message, error.stack);

            // Use improved error formatter for detailed error display
            const errorMsg = cmaApiError.formatError(error);
            this.showError(errorMsg);

            // Show error in list content using lib-message
            if (this.listContent) {
                let errorHtml = errorMsg;
                // Convert newlines to <br>
                errorHtml = errorHtml.replace(/\n/g, '<br>');
                // In debug mode, show additional info
                if (CMA_DEBUG && error.debug) {
                    const debug = error.debug;
                    if (debug.file) {
                        const shortFile = debug.file.split(/[\/\\]/).slice(-3).join('/');
                        errorHtml += '<br><small class="error-debug">Bestand: ' + this.escapeHtml(shortFile) + ':' + (debug.line || '?') + '</small>';
                    }
                }
                this.listContent.innerHTML = '<lib-message type="error" style="margin: 20px;">' + errorHtml + '</lib-message>';
                this.listContent.style.display = 'block';
            }
            cmaPerf.end(perfId, { error: error.message });
            cmaPerf.count('loadList.networkErrors');
        } finally {
            // Always hide loaders (lib-loader component)
            const listLoader = document.getElementById('listLoader');
            if (listLoader && listLoader.hide) listLoader.hide();
            this.hideLoading('loadList-finally');
        }
    }

    /**
     * Load list using lib-table web component
     * High-performance alternative to HTML table rendering
     */
    async loadListWebComponent(forceRefresh = false) {
        if (!this.listContent) {
            this.listContent = document.getElementById('listContent');
        }
        if (!this.listContent) {
            cmaLog.error('loadListWebComponent: listContent element not found');
            return;
        }

        this.showLoading('loadListWebComponent');

        try {
            // If forceRefresh and we have an existing instance, refresh it
            if (forceRefresh && this._libTableInstance && typeof this._libTableInstance.refresh === 'function') {
                // cmaLog.log('loadListWebComponent: forcing refresh of existing instance');
                await this._libTableInstance.refresh();
                this.hideLoading('loadListWebComponent');
                return;
            }

            // Create or reuse lib-table element
            if (!this._libTableInstance) {
                this._libTableInstance = document.createElement('lib-table');
                this._libTableInstance.setAttribute('data-form-id', this.formId);
                this._libTableInstance.setAttribute('data-url', `/cma/form_api.php?action=tableData&form=${this.jsonFormName}`);
                this._libTableInstance.setAttribute('page-size', '50');
                this._libTableInstance.setAttribute('row-height', '36');

                // Enable features
                this._libTableInstance.setAttribute('sortable', '');
                this._libTableInstance.setAttribute('filterable', '');
                this._libTableInstance.setAttribute('resizable', '');
                this._libTableInstance.setAttribute('reorderable', '');

                // Bind event listeners
                const self = this;
                this._libTableInstance.addEventListener('row-click', (e) => {
                    const row = e.detail.row;
                    if (row && row._id) {
                        self.loadRecord(row._id);
                    }
                });

                this._libTableInstance.addEventListener('row-dblclick', (e) => {
                    const row = e.detail.row;
                    if (row && row._id) {
                        // Open in popup for editing
                        self.openFormPopup(row._id);
                    }
                });

                this._libTableInstance.addEventListener('filter-change', (e) => {
                    // Update record count when lib-table column filters change
                    const table = self._libTableInstance.querySelector('table.listtable') || self._libTableInstance;
                    const allRows = table.querySelectorAll('tbody tr.listrow');
                    const visibleRows = Array.from(allRows).filter(r => r.style.display !== 'none');
                    const countEl = document.getElementById('recordCount');
                    if (countEl && allRows.length > 0) {
                        const hasFilters = e.detail?.filters && Object.keys(e.detail.filters).length > 0;
                        if (hasFilters) {
                            countEl.textContent = `${visibleRows.length} van ${allRows.length} records`;
                            countEl.style.display = '';
                        } else {
                            const totalCount = self._tableData?.totalCount;
                            self.updateRecordCount(allRows.length, totalCount);
                        }
                    }
                });

                this._libTableInstance.addEventListener('sort-change', (e) => {
                    // cmaLog.log('lib-table sort changed:', e.detail);
                });

                // Update record count when excelTableFilter column filters change
                this._libTableInstance.addEventListener('column-filter-change', (e) => {
                    const countEl = document.getElementById('recordCount');
                    if (countEl) {
                        const { visibleCount, totalCount } = e.detail;
                        if (visibleCount < totalCount) {
                            countEl.textContent = `${visibleCount} van ${totalCount} records`;
                            countEl.style.display = '';
                        } else {
                            const serverTotal = self._tableData?.totalCount;
                            self.updateRecordCount(totalCount, serverTotal);
                        }
                    }
                });

                // Clear existing content and add web component
                this.listContent.innerHTML = '';
                this.listContent.appendChild(this._libTableInstance);
            }

            // Apply current search/filter state
            if (this.searchTerm) {
                this._libTableInstance.setFilter({ _search: this.searchTerm });
            }
            if (Object.keys(this.searchFilters).length > 0) {
                this._libTableInstance.setFilter(this.searchFilters);
            }

            // Load data
            await this._libTableInstance.load();

        } catch (error) {
            cmaLog.error('loadListWebComponent error:', error);
            this.listContent.innerHTML = '<div class="list-error">Fout bij laden tabel: ' + error.message + '</div>';
        } finally {
            this.hideLoading('loadListWebComponent');
        }
    }

    /**
     * Toggle web component table mode
     * @param {boolean} enable - Enable or disable web component mode
     */
    setWebComponentTableMode(enable) {
        this.useWebComponentTable = !!enable;
        localStorage.setItem('cma_v2_use_web_component_table', this.useWebComponentTable ? 'true' : 'false');

        // Clear cached instance to force re-render
        if (this._libTableInstance) {
            this._libTableInstance.remove();
            this._libTableInstance = null;
        }

        // Reload list with new mode
        this.loadList(true);
    }

    /**
     * Refresh a single row in the list after edit
     * More efficient than reloading the entire list
     * @param {string} recordId - The record ID to refresh
     */
    async refreshRow(recordId) {
        if (!recordId) {
            cmaLog.warn('refreshRow: no recordId provided, falling back to loadList');
            return this.loadList();
        }

        try {
            // Collect current columns from table headers
            const table = document.getElementById('listTable');
            const currentColumns = [];
            if (table) {
                const thead = table.querySelector('thead');
                if (thead) {
                    thead.querySelectorAll('th[data-field]').forEach(th => {
                        const field = th.dataset.field;
                        if (field) currentColumns.push(field);
                    });
                }
            }

            // Fetch updated row data from server
            const params = new URLSearchParams({
                action: 'getRow',
                ID: recordId,
                displayMode: this.displayMode,
            });

            params.set('jsonForm', this.jsonForm);

            // Pass current columns so server returns matching columns
            if (currentColumns.length > 0) {
                params.set('columns', currentColumns.join(','));
            }

            const response = await fetch(`/cma/form_api.php?${params}`);
            if (!response.ok) {
                throw new Error(`[refreshRow] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();

            if (data.success && data.rowHtml) {
                // Find the row in the table and replace it
                const table = document.getElementById('listTable');
                if (table) {
                    const existingRow = table.querySelector(`tr[data-id="${recordId}"]`);
                    if (existingRow) {
                        // Create temp element to parse HTML
                        const temp = document.createElement('tbody');
                        temp.innerHTML = data.rowHtml;
                        const newRow = temp.querySelector('tr');
                        if (newRow) {
                            existingRow.replaceWith(newRow);
                            // cmaLog.log('refreshRow: row replaced successfully');
                            return;
                        }
                    }
                }

                // Also try tree view - find node by ID
                const treeNode = document.querySelector(`#listContent a[id="${recordId}"], #listContent a[data-id="${recordId}"]`);
                if (treeNode && data.displayText) {
                    // Update the text content of the tree node
                    const textNode = treeNode.querySelector('.node-text') || treeNode;
                    if (textNode) {
                        textNode.textContent = data.displayText;
                        // cmaLog.log('refreshRow: tree node text updated');
                        return;
                    }
                }
            }

            // Fallback: reload entire list
            // cmaLog.log('refreshRow: could not find row, falling back to loadList');
            await this.loadList();
        } catch (error) {
            cmaLog.error('refreshRow error:', error);
            // Fallback to full reload
            await this.loadList();
        }
    }

    /**
     * Remove a row from the list after delete
     * @param {string} recordId - The record ID to remove
     */
    removeRowFromList(recordId) {
        if (!recordId) {
            cmaLog.warn('removeRowFromList: no recordId provided');
            return;
        }

        // Try main table view first
        const table = document.getElementById('listTable');
        if (table) {
            const row = table.querySelector(`tr[data-id="${recordId}"]`);
            if (row) {
                row.remove();
                // cmaLog.log('removeRowFromList: row removed from table');
                // Update record count display
                this.adjustRecordCountByDelta(-1);
                return;
            }
        }

        // Try tree view - find and remove the node
        const treeNode = document.querySelector(`#listContent a[id="${recordId}"], #listContent a[data-id="${recordId}"]`);
        if (treeNode) {
            // Remove the entire tree item (li element or node container)
            const nodeContainer = treeNode.closest('div.clip, li');
            if (nodeContainer) {
                nodeContainer.remove();
                // cmaLog.log('removeRowFromList: tree node removed');
                // Update record count display
                this.adjustRecordCountByDelta(-1);
                return;
            }
        }

        // Try subform tables - find and remove row from any subform table
        const subformRow = document.querySelector(`.subform-table tr[data-id="${recordId}"], .subform-list tr[data-id="${recordId}"]`);
        if (subformRow) {
            subformRow.remove();
            // cmaLog.log('removeRowFromList: row removed from subform table');
            // Update subform count badge
            const subformTable = subformRow.closest('.subform-table, table');
            if (subformTable) {
                const pane = subformTable.closest('[id^="subform"]');
                if (pane) {
                    const index = pane.id.replace('subform', '');
                    const tabsComponent = document.getElementById('subformTabs');
                    if (tabsComponent && typeof tabsComponent.updateCount === 'function') {
                        const currentCount = parseInt(tabsComponent.tabs[index]?.count || '0', 10);
                        tabsComponent.updateCount(parseInt(index, 10), Math.max(0, currentCount - 1));
                    }
                }
            }
            return;
        }

        // If row not found anywhere, reload current record to refresh subforms
        const currentRecordId = cmaGetRecordId();
        if (currentRecordId) {
            // cmaLog.log('removeRowFromList: row not found, reloading current record to refresh subforms');
            this.loadRecord(currentRecordId);
        } else {
            // No current record - just reload the list
            // cmaLog.log('removeRowFromList: could not find row, reloading list');
            this.loadList();
        }
    }

    /**
     * Adjust the record count display by a delta value
     * @param {number} delta - Change in count (positive or negative)
     */
    adjustRecordCountByDelta(delta) {
        const countEl = document.getElementById('recordCount');
        if (countEl) {
            const currentText = countEl.textContent;
            // Match the "records 1-X van Y" format
            const match = currentText.match(/records 1-(\d+) van (\d+)/);
            if (match) {
                const currentEnd = parseInt(match[1], 10);
                const total = parseInt(match[2], 10);
                const newEnd = Math.max(0, currentEnd + delta);
                const newTotal = Math.max(0, total + delta);
                if (newEnd >= newTotal) {
                    // All records shown - hide the count
                    countEl.style.display = 'none';
                } else {
                    countEl.textContent = `records 1-${newEnd} van ${newTotal}`;
                    countEl.style.display = '';
                }
            }
        }
    }

    /**
     * Wait for jQuery to be available, then execute callback
     * Retries every 50ms for up to 5 seconds
     */
    waitForJQuery(callback, maxAttempts = 100) {
        let attempts = 0;
        const checkJQuery = () => {
            attempts++;
            // Check if jQuery is loaded AND has the required methods (not just a shim)
            if (typeof jQuery !== 'undefined' && typeof jQuery.fn !== 'undefined') {
                callback();
            } else if (attempts < maxAttempts) {
                setTimeout(checkJQuery, 50);
            } else {
                cmaLog.error('jQuery failed to load after ' + (maxAttempts * 50) + 'ms. jQuery type:', typeof jQuery);
                if (this.listContent) {
                    this.listContent.innerHTML = '<div class="list-error">jQuery niet geladen (Ctrl+F5 om te verversen). <a href="javascript:location.reload(true)">Opnieuw laden</a></div>';
                }
            }
        };
        checkJQuery();
    }

    /**
     * Bind click events to tree items using event delegation (prevents memory leaks)
     * Uses a single listener on listContent instead of per-element listeners
     * Uses addTrackedListener to ensure cleanup when controller is destroyed
     */
    bindTreeEvents() {
        if (!this.listContent) return;

        // Skip if using cma-tree web component (events are bound on the component directly)
        if (this.listContent.querySelector('cma-tree')) return;

        // Use event delegation - only bind once to the container
        // Check if we've already bound the delegated handler
        if (this._treeEventsBound) {
            // cmaLog.log('[bindTreeEvents] already bound, skipping');
            return;
        }
        this._treeEventsBound = true;
        // cmaLog.log('[bindTreeEvents] binding click handler to listContent:', this.listContent.id);

        const self = this;

        // Prevent <details> toggle when clicking <a> links inside <summary>
        // (toggle should only happen when clicking the arrow, not the form name)
        this.listContent.addEventListener('click', function(e) {
            const summary = e.target.closest('summary');
            if (summary && e.target.closest('a[target="R"]')) {
                e.preventDefault();
            }
        }, true); // Use capture phase to run before the default toggle

        // Create named click handler for proper cleanup
        this._treeClickHandler = async function(e) {
            try {
                // Find the clicked tree link (traverse up in case click was on child element)
                const link = e.target.closest('a[target="R"]');
                if (!link) return;

                // cmaLog.log('[bindTreeEvents] click captured, link href:', link.getAttribute('href'), 'target:', e.target.tagName, 'currentTarget:', e.currentTarget?.id, 'eventPhase:', e.eventPhase, 'isTrusted:', e.isTrusted);

                // Check if we already handled this exact click (use timestamp to detect duplicate events)
                const now = Date.now();
                if (link._lastClickTime && (now - link._lastClickTime) < 100) {
                    // cmaLog.log('[bindTreeEvents] DUPLICATE CLICK DETECTED, skipping (delta:', now - link._lastClickTime, 'ms)');
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
                link._lastClickTime = now;

                e.preventDefault();
                e.stopPropagation(); // Prevent event from bubbling further
                e.stopImmediatePropagation(); // Prevent other listeners on the same element

                // Extract ID from multiple sources:
                // 1. data-id attribute (simple trees)
                // 2. href URL format: ID=xxx
                // 3. href javascript format: loadRecord(xxx)
                // 4. href as raw ID (grouped trees: href='123')
                let recordId = null;

                // Try data-id attribute first (simple trees use this)
                recordId = link.getAttribute('data-id');

                // Try URL format: ID=xxx
                if (!recordId) {
                    const href = link.getAttribute('href') || '';
                    let match = href.match(/ID=([^&]+)/);
                    if (match) {
                        recordId = decodeURIComponent(match[1]);
                    }

                    // Try javascript format: loadRecord(xxx) or loadRecord('xxx')
                    if (!recordId) {
                        match = href.match(/loadRecord\(['"]?([^'")\s]+)['"]?\)/);
                        if (match) {
                            recordId = match[1];
                        }
                    }

                    // Try raw ID format (grouped trees use href='123' or href='guid')
                    // Match alphanumeric IDs, GUIDs, or numeric IDs
                    if (!recordId && href) {
                        // Remove surrounding quotes if present (href='123' becomes '123' after getAttribute)
                        const cleanHref = href.replace(/^['"]|['"]$/g, '');
                        // Check if it looks like an ID (number, GUID, or alphanumeric code)
                        if (/^[a-zA-Z0-9_-]+$/.test(cleanHref) && !cleanHref.includes(':')) {
                            recordId = cleanHref;
                        }
                    }
                }

                if (recordId) {
                    // Only set active state if loadRecord succeeds
                    const success = await self.loadRecord(recordId);
                    if (success) {
                        // Remove previous active state
                        self.listContent.querySelectorAll('a.active').forEach(a => a.classList.remove('active'));
                        // Add active state
                        link.classList.add('active');
                    }
                } else {
                    cmaLog.warn('Could not extract ID from href:', link.getAttribute('href'));
                }
            } catch (error) {
                cmaLog.error('[treeListClick] Error handling click:', error);
            }
        };

        // Create named dblclick handler for proper cleanup
        this._treeDblclickHandler = function(e) {
            const link = e.target.closest('a[target="R"]');
            if (!link) return;

            e.preventDefault();
            e.stopPropagation();

            // Extract record ID (same logic as click handler)
            let recordId = link.getAttribute('data-id');
            if (!recordId) {
                const href = link.getAttribute('href') || '';
                let match = href.match(/ID=([^&]+)/);
                if (match) {
                    recordId = decodeURIComponent(match[1]);
                }
                if (!recordId) {
                    match = href.match(/loadRecord\(['"]?([^'")\s]+)['"]?\)/);
                    if (match) {
                        recordId = match[1];
                    }
                }
                if (!recordId && href && /^[a-zA-Z0-9_-]+$/.test(href.replace(/^['"]|['"]$/g, ''))) {
                    recordId = href.replace(/^['"]|['"]$/g, '');
                }
            }

            if (recordId) {
                // cmaLog.log('[bindTreeEvents] dblclick, opening in popup:', recordId);
                self.openFormPopup(recordId);
            }
        };

        // Use addTrackedListener for proper cleanup when controller is destroyed
        this.addTrackedListener(this.listContent, 'click', this._treeClickHandler);
        this.addTrackedListener(this.listContent, 'dblclick', this._treeDblclickHandler);
    }

    /**
     * Render list items using event delegation (prevents memory leaks)
     */
    renderList(data) {
        if (!data.items || data.items.length === 0) {
            this.listContent.innerHTML = '<div class="list-loading">Geen records gevonden</div>';
            return;
        }

        let html = '';

        for (const item of data.items) {
            const id = item._id || item.ID || item.id;
            const isSelected = String(id) === String(cmaGetRecordId());

            // Get display columns (first 2-3 non-ID columns)
            const columns = Object.keys(item).filter(k => !k.startsWith('_') && k.toLowerCase() !== 'id');
            const title = item[columns[0]] || '';
            const subtitle = item[columns[1]] || '';
            const meta = item[columns[2]] || '';

            html += `
                <div class="list-item${isSelected ? ' selected' : ''}" data-id="${this.escapeHtml(id)}">
                    <div class="list-item-title">${this.escapeHtml(title)}</div>
                    ${subtitle ? `<div class="list-item-subtitle">${this.escapeHtml(subtitle)}</div>` : ''}
                    ${meta ? `<div class="list-item-meta">${this.escapeHtml(meta)}</div>` : ''}
                </div>
            `;
        }

        this.listContent.innerHTML = html;

        // Use event delegation for list items - bind once to container
        this.bindListItemEvents();
    }

    /**
     * Bind list item click events using event delegation (prevents memory leaks)
     */
    bindListItemEvents() {
        // Only bind once using event delegation
        if (this._listItemEventsBound) return;
        this._listItemEventsBound = true;

        const self = this;
        // Create named handler for proper cleanup
        this._listItemClickHandler = function(e) {
            const listItem = e.target.closest('.list-item[data-id]');
            if (!listItem) return;

            const id = listItem.dataset.id;
            self.selectListItem(id);
            self.loadRecord(id);
        };
        // Use addTrackedListener for proper cleanup when controller is destroyed
        this.addTrackedListener(this.listContent, 'click', this._listItemClickHandler);
    }

    /**
     * Select a list item
     */
    selectListItem(id) {
        // cmaLog.log('selectListItem: selecting id=', id, 'type=', typeof id);

        // Handle lib-table component (table mode)
        if (this._libTableInstance) {
            // Remove previous selection
            this._libTableInstance.shadowRoot?.querySelectorAll('.lib-table-row.selected').forEach(row => {
                row.classList.remove('selected');
            });
            // Select new row
            const row = this._libTableInstance.shadowRoot?.querySelector(`.lib-table-row[data-id="${id}"]`);
            // cmaLog.log('selectListItem: lib-table row found=', !!row);
            if (row) {
                row.classList.add('selected');
                row.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
            return;
        }

        // Remove previous selection (tree mode)
        this.listContent.querySelectorAll('.list-item.selected').forEach(item => {
            item.classList.remove('selected');
        });

        // Select new item
        const item = this.listContent.querySelector(`.list-item[data-id="${id}"]`);
        // cmaLog.log('selectListItem: .list-item[data-id] found=', !!item);
        if (item) {
            item.classList.add('selected');
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Also handle tree links with active class
        this.listContent.querySelectorAll('a.active').forEach(a => {
            a.classList.remove('active');
        });
        // Try data-id attribute first (preferred), then href patterns
        let link = this.listContent.querySelector(`a[data-id="${id}"]`);
        if (!link) {
            // Fallback to href patterns for older code (grouped trees use javascript:loadRecord(ID))
            // Handle numeric IDs, quoted IDs, and GUID IDs
            const escapedId = CSS.escape(String(id));
            link = this.listContent.querySelector(`a[href*="loadRecord(${id})"], a[href*="loadRecord('${id}')"], a[href*='loadRecord("${id}")']`);
            // Also try finding by searching all tree links if selectors don't match
            if (!link) {
                const allTreeLinks = this.listContent.querySelectorAll('a[target="R"]');
                for (const treeLink of allTreeLinks) {
                    const href = treeLink.getAttribute('href') || '';
                    // Match loadRecord(123), loadRecord('123'), loadRecord("123"), or raw href='123'
                    if (href.includes(`loadRecord(${id})`) ||
                        href.includes(`loadRecord('${id}')`) ||
                        href.includes(`loadRecord("${id}")`) ||
                        href === String(id)) {
                        link = treeLink;
                        break;
                    }
                }
            }
        }
        // cmaLog.log('selectListItem: tree link found=', !!link);
        if (link) {
            link.classList.add('active');

            // For grouped trees (complex trees), expand parent folders to make link visible
            this._expandTreeParentFolders(link);

            // Scroll tree link into view
            link.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Expand parent folders in a complex tree to make an item visible
     * Complex trees use li.f_closed for collapsed folders
     * @param {HTMLElement} link The tree item link element
     */
    _expandTreeParentFolders(link) {
        if (!link) return;

        let parent = link.parentElement;
        while (parent && parent !== this.listContent) {
            // Check if we're inside a collapsed folder (ul with f_closed class)
            if (parent.tagName === 'UL' && parent.classList.contains('f_closed')) {
                // Find the parent li.f_closed and expand it
                const parentLi = parent.closest('li.f_closed');
                if (parentLi) {
                    parentLi.classList.remove('f_closed');
                    parentLi.classList.add('f_open');
                    // Also expand the child UL
                    parent.classList.remove('f_closed');
                    parent.classList.add('f_open');
                }
            }
            parent = parent.parentElement;
        }
    }

    /**
     * Update pagination controls using event delegation (prevents memory leaks)
     */
    updatePagination(data) {
        const footer = document.getElementById('listFooter');
        const countEl = document.getElementById('listCount');
        const paginationEl = document.getElementById('listPagination');

        if (!footer) return;

        // Update count
        if (countEl) {
            const start = (data.page - 1) * data.pageSize + 1;
            const end = Math.min(data.page * data.pageSize, data.total);
            countEl.textContent = `${start}-${end} van ${data.total}`;
        }

        // Update pagination buttons
        if (paginationEl && data.pageCount > 1) {
            let html = '';

            // Previous button
            html += `<button class="btn" ${data.page <= 1 ? 'disabled' : ''} data-page="${data.page - 1}">&laquo;</button>`;

            // Page numbers
            const startPage = Math.max(1, data.page - 2);
            const endPage = Math.min(data.pageCount, data.page + 2);

            for (let p = startPage; p <= endPage; p++) {
                html += `<button class="btn${p === data.page ? ' active' : ''}" data-page="${p}">${p}</button>`;
            }

            // Next button
            html += `<button class="btn" ${data.page >= data.pageCount ? 'disabled' : ''} data-page="${data.page + 1}">&raquo;</button>`;

            paginationEl.innerHTML = html;

            // Use event delegation - bind once to pagination container
            this.bindPaginationEvents(paginationEl);
        } else if (paginationEl) {
            paginationEl.innerHTML = '';
        }
    }

    /**
     * Bind pagination click events using event delegation (prevents memory leaks)
     */
    bindPaginationEvents(paginationEl) {
        // Only bind once using event delegation
        if (this._paginationEventsBound) return;
        this._paginationEventsBound = true;

        const self = this;
        paginationEl.addEventListener('click', function(e) {
            const btn = e.target.closest('button[data-page]');
            if (!btn || btn.disabled) return;

            self.currentPage = parseInt(btn.dataset.page, 10);
            self.loadList();
        });
    }

    // =========================================================================
    // Record Operations
    // =========================================================================

    /**
     * Load record data via AJAX
     */
    async loadRecord(recordId, forceRefresh = false) {
        const currentRecordIdCheck = cmaGetRecordId();
        const formLayout = document.querySelector('.form-layout');
        const dataLoaded = formLayout?.dataset.dataloaded === 'true';
        // cmaLog.log('loadRecord: [' + this._instanceId + '] called with recordId=', recordId, '(type:', typeof recordId, ') forceRefresh=', forceRefresh, 'currentRecordId=', currentRecordIdCheck, 'dataLoaded=', dataLoaded);
        // cmaLog.log('loadRecord: [' + this._instanceId + '] _loadingRecordId=', this._loadingRecordId, '(type:', typeof this._loadingRecordId, ') hasPromise=', !!this._loadingRecordPromise);

        // Prevent duplicate concurrent calls for the same record
        // If already loading this record, return the in-flight promise
        // Use String comparison to handle both string and number IDs
        if (String(this._loadingRecordId) === String(recordId) && this._loadingRecordPromise) {
            // cmaLog.log('loadRecord: already loading this record, returning in-flight promise');
            return this._loadingRecordPromise;
        }

        // Ignore if already the current record AND data has been loaded (unless force refresh)
        if (!forceRefresh && currentRecordIdCheck == recordId && dataLoaded) {
            // cmaLog.log('loadRecord: early return - already current record and data loaded');
            return true;
        }

        // Mark this record as being loaded and create promise for deduplication
        this._loadingRecordId = recordId;
        const self = this;

        // Store the actual loading promise
        this._loadingRecordPromise = (async () => {
            try {
                // Clear data-loaded flag since we're loading new/different data
                if (formLayout) {
                    delete formLayout.dataset.dataloaded;
                }

                return await self._doLoadRecord(recordId, forceRefresh, formLayout);
            } finally {
                // Clear the in-flight tracking when done
                if (self._loadingRecordId === recordId) {
                    self._loadingRecordId = null;
                    self._loadingRecordPromise = null;
                }
            }
        })();

        return this._loadingRecordPromise;
    }

    /**
     * Internal record loading implementation
     * @private
     */
    async _doLoadRecord(recordId, forceRefresh, formLayout) {
        // Clean up subform editors when loading a new record
        // They will be recreated when subforms are rendered
        this.destroySubformEditors();

        // Performance tracking
        const perfId = 'loadRecord_' + Date.now();
        cmaPerf.start(perfId, { recordId: recordId, formId: this.formId, forceRefresh: forceRefresh });
        cmaPerf.count('loadRecord.calls');

        if (this.hasUnsavedChanges()) {
            const changeSummary = this.formatChangeSummary();
            const confirmed = await libConfirm('Je hebt niet-opgeslagen wijzigingen.' + changeSummary, {
                title: 'Niet-opgeslagen wijzigingen',
                confirmText: 'Verlaat scherm',
                cancelText: 'Blijf op scherm',
                type: 'warning',
                html: true
            });
            if (!confirmed) {
                cmaPerf.end(perfId, { cancelled: true });
                return false;
            }
        }

        this.showLoading('loadRecord');
        // Note: We intentionally do NOT add 'data-loading' here for record switches.
        // The old record stays visible during the ~200ms fetch, then gets swapped atomically
        // in applyRecordData() using visibility:hidden + requestAnimationFrame.

        // Check for prefetched data first (instant load if available)
        const prefetched = this.getPrefetchedRecord(recordId);
        let data;

        // Skip prefetch cache if forceRefresh is set (e.g., after save)
        if (!forceRefresh && prefetched) {
            cmaPerf.count('loadRecord.prefetchHit');
            // cmaLog.log('[Prefetch] Using prefetched data for record', recordId);
            data = prefetched;
            // Remove from prefetch cache since we're now using it
            this._prefetchCache.delete(recordId);
        } else {
            // Clear any prefetched data for this record when forcing refresh
            if (forceRefresh && prefetched) {
                this._prefetchCache.delete(recordId);
                // cmaLog.log('[Prefetch] Cleared stale prefetch data for record', recordId);
            }

            cmaPerf.mark('loadRecord_fetchStart');

            // Retry configuration
            const maxRetries = 3;
            const baseDelay = 200; // ms
            let lastError = null;

            for (let attempt = 1; attempt <= maxRetries; attempt++) {
                try {
                    // Add cache-busting timestamp when forcing refresh or on retry
                    const cacheBuster = (forceRefresh || attempt > 1) ? `&_t=${Date.now()}` : '';
                    const url = `/cma/form_api.php?action=record&${this.getFormParam()}&id=${recordId}${cacheBuster}`;

                    if (attempt > 1) {
                        cmaLog.warn(`[RETRY] loadRecord: attempt ${attempt}/${maxRetries} for record ${recordId}`);
                    } else {
                        // cmaLog.log('loadRecord: fetching URL:', url);
                    }

                    const response = await fetch(url);
                    cmaPerf.mark('loadRecord_fetchEnd');
                    cmaPerf.measure('loadRecord.network', 'loadRecord_fetchStart', 'loadRecord_fetchEnd');

                    cmaPerf.mark('loadRecord_parseStart');
                    // Use improved API error handler for better error display
                    data = await cmaApiError.handleResponse(response, 'Record laden');

                    // Check if we got valid data - if not, this might warrant a retry
                    if (!data || (!data.success && !data.error)) {
                        throw new Error('Empty or invalid response received');
                    }

                    // cmaLog.log('loadRecord: API response:', data);
                    cmaPerf.mark('loadRecord_parseEnd');
                    cmaPerf.measure('loadRecord.jsonParse', 'loadRecord_parseStart', 'loadRecord_parseEnd');

                    // Success - break out of retry loop
                    if (attempt > 1) {
                        // cmaLog.log(`loadRecord: succeeded on attempt ${attempt}`);
                        cmaPerf.count('loadRecord.retrySuccess');
                    }
                    break;

                } catch (error) {
                    lastError = error;
                    cmaLog.warn(`loadRecord: attempt ${attempt}/${maxRetries} failed:`, error.message);

                    if (attempt < maxRetries) {
                        // Exponential backoff: 200ms, 400ms, 800ms
                        const delay = baseDelay * Math.pow(2, attempt - 1);
                        // cmaLog.log(`loadRecord: waiting ${delay}ms before retry...`);
                        cmaPerf.count('loadRecord.retries');
                        await new Promise(resolve => setTimeout(resolve, delay));
                    } else {
                        // All retries exhausted
                        cmaLog.error('loadRecord: all retries exhausted, last error:', error);
                        const errorMessage = cmaApiError.formatError(error);
                        this.showError(errorMessage);
                        this.hideLoading();
                        cmaPerf.end(perfId, { error: error.message, retries: maxRetries });
                        cmaPerf.count('loadRecord.retryExhausted');
                        return false;
                    }
                }
            }
        }

        try {
            if (data.success) {
                // Use shared applyRecordData for core logic
                data.id = recordId; // Ensure ID is set
                await this.applyRecordData(data);

                // If form defines filterIdName, save the record ID as a cross-form filter
                // e.g. opleidingen has filterIdName="fkOpleiding" → opening opleiding 38
                // saves fkOpleiding=38 so rooster auto-filters to that opleiding
                this._saveFilterId(recordId);

                // Mark data as loaded to prevent duplicate loads
                const formLayoutForLoaded = document.querySelector('.form-layout');
                if (formLayoutForLoaded) {
                    formLayoutForLoaded.dataset.dataloaded = 'true';
                }

                cmaPerf.end(perfId, { success: true, fields: Object.keys(data.fields || {}).length });
                cmaPerf.count('loadRecord.success');
                return true;
            } else {
                cmaLog.error('Record load failed:', data.error);
                this.showError(data.error || 'Record laden mislukt');
                cmaPerf.end(perfId, { success: false, error: data.error });
                cmaPerf.count('loadRecord.errors');
                return false;
            }
        } catch (error) {
            cmaLog.error('Record load error:', error);
            // cmaLog.log('Error stack:', error.stack);
            this.showError('Fout bij laden record: ' + error.message);
            cmaPerf.end(perfId, { error: error.message });
            cmaPerf.count('loadRecord.networkErrors');
            return false;
        } finally {
            this.hideLoading('loadRecord-finally');
        }
    }

    /**
     * Load record for copy/duplicate mode
     * - Loads the record data
     * - Clears the ID field
     * - Hides subforms
     * - Sets changelog type to 'add'
     * - Sets copy tracking fields
     */
    async loadRecordForCopy(sourceRecordId) {
        // cmaLog.log('loadRecordForCopy: starting for ID:', sourceRecordId);
        this.showLoading('loadRecordForCopy');

        try {
            const response = await fetch(`/cma/form_api.php?action=record&${this.getFormParam()}&id=${sourceRecordId}`);
            if (!response.ok) {
                throw new Error(`[loadRecordForCopy] HTTP ${response.status} ${response.statusText}`);
            }
            const data = await response.json();

            // cmaLog.log('Record API response for copy:', data);
            if (data.success) {
                // Clear the record ID - this is a new record
                cmaSetRecordId(null);
                this.copySourceId = sourceRecordId;

                // Populate form with the source data
                this.populateForm(data.fields);

                // Set parent field value for subform context (override copied value if parent context differs)
                this.setParentFieldValue();
                // Set filter field value from toolbar filter context (override copied value if filter differs)
                this.setFilterFieldValue();

                // Clear ID and GUID fields to prevent duplicate key errors
                const fieldsToEmpty = ['ID', 'GUID', 'guid', 'Guid', 'guid2', 'Guid2', 'GUID2', 'UniqueID', 'uniqueid', 'UUID', 'uuid'];
                fieldsToEmpty.forEach(name => {
                    const field = this.mainForm.querySelector(`[name="${name}"]`);
                    if (field) {
                        field.value = '';
                    }
                });

                // Set changelog fields for copy tracking
                const changelogType = document.getElementById('_changelog_type');
                if (changelogType) {
                    changelogType.value = 'add';
                }

                // Set copy tracking fields (if they exist)
                const changelogCopy = document.getElementById('_changelog_copy');
                if (changelogCopy) {
                    changelogCopy.value = 'J';
                }
                const changelogCopyId = document.getElementById('_changelog_copy_id');
                if (changelogCopyId) {
                    changelogCopyId.value = sourceRecordId;
                }

                // Hide subforms - they don't apply to new records (via CSS class)
                document.body.classList.remove('has-subform');

                // Make "new changable only" fields editable (this is a new record)
                this.updateNewChangableOnlyFields(true);

                // Load custom renderers (security_groups, group_menu_rights, etc.) for copied records
                this.loadCustomRenderers('').catch(error => {
                    cmaLog.error('loadCustomRenderers error (copy):', error);
                });

                // Update status and toolbar
                // Copy mode is treated as creating a new record
                document.body.classList.remove('has-record');
                document.body.classList.add('is-creating');
                const formLayout = document.querySelector('.form-layout');
                if (formLayout) {
                    formLayout.classList.remove('has-record');
                    formLayout.classList.add('is-creating');
                }
                this.updateStatus('Toevoegen (kopie van ' + sourceRecordId + ')');
                // Initialize formval_nl input masking for copied record form
                if (typeof form_init_container === 'function') {
                    form_init_container(this.mainForm);
                }
                this.setDirty(true); // Mark as dirty since we have data to save

                return true;
            } else {
                cmaLog.error('Record load for copy failed:', data.error);
                this.showError(data.error || 'Record laden voor kopie mislukt');
                return false;
            }
        } catch (error) {
            cmaLog.error('Record load for copy error:', error);
            this.showError('Fout bij laden record voor kopie: ' + error.message);
            return false;
        } finally {
            this.hideLoading('loadRecordForCopy-finally');
            // Remove loading state - reveal form with data
            document.body.classList.remove('data-loading');
            // Signal DOM ready via data attribute (NO GLOBALS)
            const formLayoutReady = document.querySelector('.form-layout');
            if (formLayoutReady) {
                formLayoutReady.dataset.ready = 'true';
            }
        }
    }

    /**
     * Populate form fields with data
     */
    populateForm(fields) {
        // cmaLog.log('populateForm: called', {
        //     hasMainForm: !!this.mainForm,
        //     mainFormId: this.mainForm?.id,
        //     mainFormAction: this.mainForm?.action,
        //     fieldsCount: fields ? Object.keys(fields).length : 0,
        //     fieldNames: fields ? Object.keys(fields) : [],
        //     combosLoaded: this.combosLoaded,
        //     jsonForm: this.options?.jsonForm
        // });
        if (!this.mainForm || !fields) return;

        // Reset validation indicators from any previous validation
        this.mainForm.querySelectorAll('.invalid').forEach(el => {
            el.classList.remove('invalid');
        });
        this.mainForm.querySelectorAll('.validation-error').forEach(el => {
            el.remove();
        });

        // If combos aren't loaded yet, store fields to apply later
        if (!this.combosLoaded) {
            this.pendingFormFields = fields;
            // Still populate non-combo fields immediately
        }

        // Build case-insensitive lookup map of form fields (single pass)
        // Priority: data-field containers (for radiogroups) > name attributes
        const fieldMap = {};

        this.mainForm.querySelectorAll('[data-field], [name]').forEach(el => {
            // Skip anchors, buttons, and elements with btn- classes (UI controls, not form fields)
            if (el.tagName === 'A' || el.tagName === 'BUTTON' || el.classList.contains('btn-add-related')) {
                return;
            }

            const dataField = el.dataset?.field;
            const elName = el.name || el.getAttribute('name');

            if (dataField) {
                // data-field always wins
                fieldMap[dataField.toLowerCase()] = el;
            } else if (elName) {
                const nameLower = elName.toLowerCase();
                // Skip radio inputs if their parent radio-group container is already mapped
                if (el.type === 'radio') {
                    const container = el.closest('.radio-group');
                    if (container && container.dataset.field?.toLowerCase() === nameLower) {
                        return;
                    }
                }
                if (!fieldMap[nameLower]) {
                    fieldMap[nameLower] = el;
                }
            }
        });

        // Debug: log all field names from API response vs fieldMap for fk fields
        const apiFieldNames = Object.keys(fields);
        const mapFieldNames = Object.keys(fieldMap);
        const fkApiFields = apiFieldNames.filter(n => n.toLowerCase().startsWith('fk'));
        if (fkApiFields.length > 0) {
            // cmaLog.log('populateForm: fk fields from API:', fkApiFields);
            // cmaLog.log('populateForm: fieldMap has keys:', mapFieldNames.length, 'sample:', mapFieldNames.slice(0, 10));
            fkApiFields.forEach(fkName => {
                const inMap = fieldMap[fkName.toLowerCase()];
                // cmaLog.log('populateForm: fk field', fkName, 'found in fieldMap?', !!inMap, inMap ? 'tagName=' + inMap.tagName + ', name=' + inMap.name + ', type=' + inMap.type + ', data-type=' + inMap.dataset?.type : 'NOT FOUND');
            });
        }

        for (const [name, value] of Object.entries(fields)) {
            // Case-insensitive field lookup
            const field = fieldMap[name.toLowerCase()];
            if (!field) continue;

            // Determine field type - also check tagName for web components
            let type = field.dataset.type || field.type;
            // lib-switch is always a checkbox, even if data-type is missing
            if (field.tagName === 'LIB-SWITCH') {
                type = 'checkbox';
            }
            // lib-combo is always a combobox
            if (field.tagName === 'LIB-COMBO') {
                type = 'combobox';
            }

            switch (type) {
                case 'checkbox':
                    const isChecked = value === true || value === 'true' || value === '1' || value === 'True' || value === -1;
                    // Handle lib-switch web component
                    if (field.tagName === 'LIB-SWITCH') {
                        field.checked = isChecked;
                    } else {
                        field.checked = isChecked;
                        // Update visual state for legacy switch
                        const switchEl = field.closest('.switch');
                        if (switchEl) {
                            switchEl.classList.toggle('checked', field.checked);
                        }
                    }
                    break;

                case 'select-one':
                case 'combobox': {
                    const labelFieldName = name + '__label';
                    const labelValue = fields[labelFieldName];
                    const hasError = fields[name + '__error'] === true;

                    // Handle lib-combo web component
                    if (field.tagName === 'LIB-COMBO') {
                        if (value && labelValue) {
                            const displayText = hasError ? '⚠ ' + labelValue : labelValue;
                            const existingOption = field._options?.find(o => String(o.value) === String(value));
                            if (!existingOption) {
                                field.addOption(String(value), displayText);
                            }
                        }
                        field.value = value ? String(value) : '';
                        break;
                    }

                    // Plain select fallback
                    field.value = value;
                    break;
                }

                case 'select-multiple':
                case 'checklist':
                    if (Array.isArray(value)) {
                        if (field.tagName === 'LIB-COMBO') {
                            field.value = value;
                        } else {
                            Array.from(field.options).forEach(opt => {
                                opt.selected = value.includes(opt.value);
                            });
                        }
                    }
                    break;

                case 'image':
                    field.value = value || '';
                    const preview = this.mainForm.querySelector(`[name="${name}_preview"]`);
                    const previewBtn = this.mainForm.querySelector(`[data-preview-field="${name}"]`);
                    const clearBtn = this.mainForm.querySelector(`[data-clear-field="${name}"]`);
                    // Note: crop button is NEVER disabled - it's used to upload new images
                    if (preview && value) {
                        const path = field.dataset.path || '';
                        preview.onerror = function() {
                            this.style.display = 'none';
                            this.src = '';
                            const icon = this.parentElement.querySelector('.image-404');
                            if (!icon) {
                                const el = document.createElement('span');
                                el.className = 'image-404 lnr lnr-picture';
                                el.title = 'Afbeelding niet gevonden';
                                this.parentElement.appendChild(el);
                            }
                        };
                        // Remove previous 404 icon if re-loading
                        const old404 = preview.parentElement.querySelector('.image-404');
                        if (old404) old404.remove();
                        preview.src = value.startsWith('http') ? value : path + value;
                        preview.style.display = '';
                    } else if (preview) {
                        preview.src = '';
                        preview.style.display = 'none';
                        const old404 = preview.parentElement.querySelector('.image-404');
                        if (old404) old404.remove();
                    }
                    // Toggle disabled state on buttons based on whether image exists
                    // Note: crop button is NEVER disabled - it's used to upload and crop new images
                    if (previewBtn) {
                        previewBtn.classList.toggle('disabled', !value);
                    }
                    if (clearBtn) {
                        clearBtn.classList.toggle('disabled', !value);
                    }
                    break;

                case 'file':
                    // Set the hidden input value
                    field.value = value || '';
                    // Update file display (link and filename)
                    const filePath = field.dataset.path || '';
                    const fileViewBtn = this.mainForm.querySelector(`[data-file-link="${name}"]`);
                    const fileNameEl = this.mainForm.querySelector(`[data-file-name="${name}"]`);
                    const fileClearBtn = this.mainForm.querySelector(`[data-clear-field="${name}"]`);
                    if (fileViewBtn) {
                        fileViewBtn.href = value ? (filePath + value) : '';
                        fileViewBtn.classList.toggle('disabled', !value);
                    }
                    if (fileClearBtn) {
                        fileClearBtn.classList.toggle('disabled', !value);
                    }
                    if (fileNameEl) {
                        fileNameEl.textContent = value || '';
                    }
                    break;

                case 'url':
                    // Set the URL input value
                    field.value = value || '';
                    // Update URL preview button state
                    const urlPreviewBtn = this.mainForm.querySelector(`[data-url-link="${name}"]`);
                    if (urlPreviewBtn) {
                        urlPreviewBtn.href = value || '#';
                        urlPreviewBtn.classList.toggle('disabled', !value);
                    }
                    break;

                case 'datetime':
                    // Handle datetime values in either format:
                    // - European: dd-mm-yyyy hh:mm(:ss)
                    // - ISO: yyyy-mm-dd hh:mm(:ss)
                    // Set date in the main field (European format), time in companion _time field
                    if (value) {
                        let day, month, year, hours, minutes;

                        // Try European format first: dd-mm-yyyy hh:mm(:ss)
                        const euMatch = value.match(/^(\d{2})-(\d{2})-(\d{4})\s+(\d{2}):(\d{2})(?::\d{2})?$/);
                        if (euMatch) {
                            [, day, month, year, hours, minutes] = euMatch;
                        } else {
                            // Try ISO format: yyyy-mm-dd hh:mm(:ss)
                            const isoMatch = value.match(/^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})(?::\d{2})?$/);
                            if (isoMatch) {
                                [, year, month, day, hours, minutes] = isoMatch;
                            }
                        }

                        if (day && month && year) {
                            // Set date part in European format
                            field.value = `${day}-${month}-${year}`;
                            // Set time part in companion field (if time is not 00:00)
                            const timeField = this.mainForm.querySelector(`[name="${name}_time"]`);
                            if (timeField) {
                                if (hours !== '00' || minutes !== '00') {
                                    timeField.value = `${hours}:${minutes}`;
                                } else {
                                    timeField.value = '';
                                }
                            }
                        } else {
                            // No match, use value as-is
                            field.value = value;
                        }
                    } else {
                        field.value = '';
                        // Clear companion time field too
                        const timeField = this.mainForm.querySelector(`[name="${name}_time"]`);
                        if (timeField) {
                            timeField.value = '';
                        }
                    }
                    break;

                case 'date':
                    // Handle date/time values: dd-mm-yyyy hh:mm:ss
                    // If year is 1899, it's a time-only field → show hh:mm
                    // Otherwise, strip time part → show dd-mm-yyyy
                    if (value) {
                        const dateMatch = value.match(/^(\d{2})-(\d{2})-(\d{4})\s+(\d{2}):(\d{2})(?::\d{2})?$/);
                        if (dateMatch) {
                            const [, day, month, year, hours, minutes] = dateMatch;
                            if (year === '1899') {
                                // Time-only field
                                field.value = `${hours}:${minutes}`;
                            } else {
                                // Date field - strip time
                                field.value = `${day}-${month}-${year}`;
                            }
                        } else {
                            // No match, use value as-is (might already be formatted)
                            field.value = value;
                        }
                    } else {
                        field.value = '';
                    }
                    break;

                case 'time':
                    // Handle time values that may come with dummy date (30-12-1899 hh:mm:ss)
                    if (value) {
                        const timeMatch = value.match(/^\d{2}-\d{2}-1899\s+(\d{2}):(\d{2})(?::\d{2})?$/);
                        if (timeMatch) {
                            // Extract just the time part
                            field.value = `${timeMatch[1]}:${timeMatch[2]}`;
                        } else if (value.match(/^\d{2}:\d{2}(:\d{2})?$/)) {
                            // Already in hh:mm or hh:mm:ss format - strip seconds if present
                            field.value = value.substring(0, 5);
                        } else {
                            field.value = value;
                        }
                    } else {
                        field.value = '';
                    }
                    break;

                case 'radiogroup':
                    // Radio button group - find the matching radio by value
                    const radioGroup = field.querySelectorAll('input[type="radio"]');
                    // Convert value to string, handling null/undefined
                    // If empty/null, use the default value from data-default attribute
                    let radioValue = (value !== null && value !== undefined && value !== '') ? String(value) : '';
                    if (radioValue === '' && field.dataset.default) {
                        radioValue = field.dataset.default;
                    }
                    radioGroup.forEach(radio => {
                        radio.checked = radio.value === radioValue;
                    });
                    break;

                default:
                    if (field.tagName === 'TEXTAREA') {
                        // Debug: Log CKEditor field population
                        const isHtmlField = field.dataset.allowHtml === 'true';
                        // cmaLog.log('[populateForm] TEXTAREA', name,
                        //     'isHtmlField=', isHtmlField,
                        //     'valueLength=', (value || '').length,
                        //     'valuePreview=', (value || '').substring(0, 100));

                        field.value = value || '';

                        // Re-initialize CKEditor if needed
                        if (isHtmlField && typeof CKEDITOR !== 'undefined') {
                            const editorInstance = CKEDITOR.instances[name];
                            if (editorInstance) {
                                // cmaLog.log('[populateForm] Setting CKEditor data for', name, 'editor ready=', editorInstance.status === 'ready');
                                editorInstance.setData(value || '');
                            } else {
                                // cmaLog.log('[populateForm] CKEditor instance not found for', name, 'available instances=', Object.keys(CKEDITOR.instances));
                            }
                        }
                    } else if (field.tagName === 'DIV' || field.tagName === 'SPAN') {
                        // Read-only display fields (div/span with data-field)
                        // Use innerHTML for HTML fields to preserve formatting
                        if (field.dataset.allowHtml === 'true' || field.dataset.html === 'true') {
                            field.innerHTML = value || '';
                        } else {
                            // Format boolean values as Ja/Nee for better readability
                            // Check: data-type is checkbox/boolean, OR dataType is 11 (Access Yes/No), OR value looks boolean
                            let displayValue = value || '';
                            const isBooleanField = field.dataset.type === 'checkbox' ||
                                                   field.dataset.type === 'boolean' ||
                                                   field.dataset.datatype === '11';
                            const looksBoolean = value === true || value === false ||
                                                 value === 'True' || value === 'False' ||
                                                 value === '1' || value === '0' ||
                                                 value === 1 || value === 0 ||
                                                 value === -1 || value === '-1';

                            if (isBooleanField || looksBoolean) {
                                const isTrue = value === true || value === 'true' || value === '1' || value === 'True' || value === 1 || value === -1 || value === '-1';
                                const isFalse = value === false || value === 'false' || value === '0' || value === 'False' || value === 0 || value === '' || value === null;
                                if (isTrue) {
                                    displayValue = 'Ja';
                                } else if (isFalse) {
                                    displayValue = 'Nee';
                                }
                            }
                            field.textContent = displayValue;
                        }
                    } else if (field.value !== undefined) {
                        field.value = value || '';
                    }
            }

            // Populate _old_ field for passOnToPost tracking (notifications/changelog)
            const oldField = this.mainForm.querySelector(`[name="_old_${name}"]`);
            if (oldField && oldField.dataset.trackOriginal === 'true') {
                oldField.value = value || '';
            }
        }
    }

    /**
     * Load custom renderers for fields that have them
     * @param {string} recordId - Record ID to load renderers for
     */
    async loadCustomRenderers(recordId) {
        // cmaLog.log('[loadCustomRenderers] ENTRY recordId=', recordId);
        const renderers = document.querySelectorAll('.custom-renderer');
        // cmaLog.log('[loadCustomRenderers] Found', renderers.length, 'custom renderer(s)');
        if (renderers.length === 0) return;

        for (const container of renderers) {
            const renderer = container.dataset.renderer;
            const fieldName = container.dataset.field;
            // cmaLog.log('[loadCustomRenderers] Processing renderer:', renderer, 'field:', fieldName);

            try {
                const url = `/cma/form_api.php?action=renderer&renderer=${encodeURIComponent(renderer)}&field=${encodeURIComponent(fieldName)}&id=${encodeURIComponent(recordId)}&${this.getFormParam()}`;
                // cmaLog.log('[loadCustomRenderers] Fetching URL:', url);
                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status} ${response.statusText}`);
                }
                const data = await response.json();
                // cmaLog.log('[loadCustomRenderers] Response for', renderer, ':', data.success ? 'success' : 'failed', 'html length:', (data.html || '').length);

                if (data.success && data.html) {
                    container.innerHTML = data.html;
                    // Initialize rights-matrix if loaded
                    this.initRightsMatrix(container);
                    // cmaLog.log('[loadCustomRenderers] Renderer', renderer, 'loaded successfully');
                } else {
                    cmaLog.warn('[loadCustomRenderers] Renderer', renderer, 'failed:', data.error);
                    container.innerHTML = '<lib-message type="error">Laden mislukt: ' + (data.error || 'Onbekende fout') + '</lib-message>';
                }
            } catch (error) {
                cmaLog.error('[loadCustomRenderers] Error loading custom renderer:', renderer, error);
                container.innerHTML = '<lib-message type="error">Laden mislukt: ' + error.message + '</lib-message>';
            }
        }
        // cmaLog.log('[loadCustomRenderers] EXIT');
    }

    /**
     * Initialize rights-matrix event handlers
     * Called after custom renderer content is loaded via AJAX
     */
    initRightsMatrix(container) {
        const matrix = container.querySelector('.rights-matrix');
        if (!matrix) return;

        // Helper function to get selected access level for a row
        function getRowAccessLevel(row) {
            const checkedRadio = row.querySelector('input[type="radio"]:checked');
            return checkedRadio ? parseInt(checkedRadio.value) : 0;
        }

        // Helper function to update checkbox states based on access level
        function updateRowCheckboxes(row, accessLevel) {
            row.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
                cb.disabled = accessLevel === 0;
                if (accessLevel === 0) cb.checked = false;
            });
        }

        // Helper function to find child rows (using data-parent reverse lookup)
        function findChildRows(parentRowId) {
            return matrix.querySelectorAll('tr[data-parent="' + parentRowId + '"]');
        }

        // Helper function to recursively process subforms when parent access changes
        function processSubforms(parentRowId, parentDisabled) {
            const childRows = findChildRows(parentRowId);
            childRows.forEach(function(subRow) {
                const subRadios = subRow.querySelectorAll('input[type="radio"]');
                const subCheckboxes = subRow.querySelectorAll('input[type="checkbox"]');

                if (parentDisabled) {
                    // Parent disabled: disable all inputs and set to no access
                    subRadios.forEach(function(r) {
                        r.disabled = true;
                        if (r.value === '0') r.checked = true;
                    });
                    subCheckboxes.forEach(function(cb) {
                        cb.disabled = true;
                        cb.checked = false;
                    });
                } else {
                    // Parent enabled: re-enable radios, re-evaluate checkboxes
                    subRadios.forEach(function(r) { r.disabled = false; });
                    const subAccessLevel = getRowAccessLevel(subRow);
                    updateRowCheckboxes(subRow, subAccessLevel);
                }

                // Recursively process nested subforms
                const subRowId = subRow.dataset.rowId;
                if (subRowId) {
                    processSubforms(subRowId, parentDisabled || getRowAccessLevel(subRow) === 0);
                }
            });
        }

        // Cascade access level to all child rows (set same value recursively)
        function cascadeAccessToChildren(parentRowId, accessLevel) {
            const childRows = findChildRows(parentRowId);
            childRows.forEach(function(subRow) {
                // Find and select the radio with matching value
                const radio = subRow.querySelector('input[type="radio"][value="' + accessLevel + '"]');
                if (radio) {
                    radio.disabled = false; // Enable first
                    radio.checked = true;
                    // Update checkboxes for this row
                    updateRowCheckboxes(subRow, accessLevel);
                }

                // Recursively cascade to nested children
                const subRowId = subRow.dataset.rowId;
                if (subRowId) {
                    cascadeAccessToChildren(subRowId, accessLevel);
                }
            });
        }

        // Helper function to ensure parent has at least read access
        function ensureParentAccess(row) {
            const parentRowId = row.dataset.parent;
            if (!parentRowId) return;

            const parentRow = document.querySelector('tr[data-row-id="' + parentRowId + '"]');
            if (!parentRow) return;

            const parentAccessLevel = getRowAccessLevel(parentRow);
            if (parentAccessLevel === 0) {
                // Set parent to read access (value 1)
                const readRadio = parentRow.querySelector('input[type="radio"][value="1"]');
                if (readRadio) {
                    readRadio.checked = true;
                    // Trigger change event to update checkboxes
                    readRadio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }

            // Recursively ensure grandparent has access too
            ensureParentAccess(parentRow);
        }

        // Attach change handlers to radio buttons
        matrix.querySelectorAll('input[type="radio"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const row = this.closest('tr');
                const accessLevel = parseInt(this.value);

                // Enable/disable button checkboxes based on access level
                updateRowCheckboxes(row, accessLevel);

                // If setting access on a subform, ensure parent has at least read access
                if (accessLevel > 0) {
                    ensureParentAccess(row);
                }

                // Cascade access level to all child rows (they inherit parent's value)
                const rowId = row.dataset.rowId;
                if (rowId) {
                    cascadeAccessToChildren(rowId, accessLevel);
                }
            });
        });

        // Initialize checkbox states based on current access levels
        matrix.querySelectorAll('tr').forEach(function(row) {
            const accessLevel = getRowAccessLevel(row);
            updateRowCheckboxes(row, accessLevel);
        });

        // Initialize subform disabled state based on parent access levels
        matrix.querySelectorAll('tr[data-parent]').forEach(function(subRow) {
            const parentRowId = subRow.dataset.parent;
            const parentRow = document.querySelector('tr[data-row-id="' + parentRowId + '"]');
            if (parentRow) {
                const parentAccessLevel = getRowAccessLevel(parentRow);
                if (parentAccessLevel === 0) {
                    // Disable subform inputs if parent has no access
                    const subRadios = subRow.querySelectorAll('input[type="radio"]');
                    const subCheckboxes = subRow.querySelectorAll('input[type="checkbox"]');
                    subRadios.forEach(function(r) {
                        r.disabled = true;
                    });
                    subCheckboxes.forEach(function(cb) {
                        cb.disabled = true;
                    });
                }
            }
        });

        // Handle header radio buttons for bulk group selection
        matrix.querySelectorAll('.header-radio').forEach(function(headerRadio) {
            headerRadio.addEventListener('change', function() {
                const groupId = this.dataset.group;
                const newValue = this.dataset.value;

                // Find the section header row
                const headerRow = this.closest('tr.section-header');
                if (!headerRow) return;

                // Find all rows between this header and the next header (or end of table)
                // Update ALL rows including subforms
                let currentRow = headerRow.nextElementSibling;
                while (currentRow && !currentRow.classList.contains('section-header')) {
                    // Find the radio button with the matching value and select it
                    const radio = currentRow.querySelector('input[type="radio"][value="' + newValue + '"]');
                    if (radio) {
                        radio.disabled = false; // Enable first (might be disabled)
                        radio.checked = true;
                        // Update checkboxes for this row based on new access level
                        updateRowCheckboxes(currentRow, parseInt(newValue));
                    }
                    currentRow = currentRow.nextElementSibling;
                }

                // cmaLog.log('initRightsMatrix: Bulk set group', groupId, 'to value', newValue);
            });
        });

        // cmaLog.log('initRightsMatrix: Initialized', matrix.querySelectorAll('input[type="radio"]').length, 'radio buttons,',
        //     matrix.querySelectorAll('input[type="checkbox"]').length, 'checkboxes,',
        //     matrix.querySelectorAll('.header-radio').length, 'header radios');
    }

    /**
     * Update meta info display
     */
    updateMeta(meta) {
        if (!meta) return;

        const lastModified = document.getElementById('lastModified');
        if (lastModified && meta.lastModifiedUser) {
            const modifiedUser = document.getElementById('modifiedUser');
            const modifiedDate = document.getElementById('modifiedDate');
            if (modifiedUser) modifiedUser.textContent = meta.lastModifiedUser;
            if (modifiedDate) modifiedDate.textContent = meta.lastModifiedDate || '';
            lastModified.style.display = 'block';
        }
    }

    /**
     * Create new record
     */
    async newRecord() {
        if (this.hasUnsavedChanges()) {
            const changeSummary = this.formatChangeSummary();
            const confirmed = await libConfirm('Je hebt niet-opgeslagen wijzigingen.' + changeSummary, {
                title: 'Niet-opgeslagen wijzigingen',
                confirmText: 'Verlaat scherm',
                cancelText: 'Blijf op scherm',
                type: 'warning',
                html: true
            });
            if (!confirmed) {
                return;
            }
        }

        cmaSetRecordId(null);

        // Capture filter field value from current record BEFORE clearing the form
        // This allows new records to inherit the filter context from the record being viewed
        const filterFieldName = this.config.filterFieldName;
        let capturedFilterValue = null;
        if (filterFieldName && this.mainForm) {
            const filterField = this.mainForm.querySelector('[name="' + filterFieldName + '"]');
            if (filterField && filterField.value) {
                capturedFilterValue = filterField.value;
            }
        }

        // cmaLog.log('[newRecord] Calling clearForm');
        this.clearForm();
        this.applyDefaultValues(); // Apply default values from field definitions
        this.setParentFieldValue(); // Set parent field value for subform context
        this.setFilterFieldValue(capturedFilterValue); // Set filter field value from toolbar filter context or captured value
        this.showDetailPanel();
        // cmaLog.log('[newRecord] About to call initHtmlEditorsOnce (new record mode)');
        this.initHtmlEditorsOnce();
        this.setDirty(false);
        // Initialize formval_nl input masking for new record form
        if (typeof form_init_container === 'function') {
            form_init_container(this.mainForm);
        }
        this.captureOriginalValues(); // Capture default values as original for change tracking

        // Clear list selection
        this.listContent.querySelectorAll('.list-item.selected').forEach(item => {
            item.classList.remove('selected');
        });

        // Hide subforms for new records (via CSS class)
        document.body.classList.remove('has-subform');

        // Update toolbar - add is-creating class to show save/cancel buttons
        document.body.classList.remove('has-record');
        document.body.classList.add('is-creating');
        const formLayout = document.querySelector('.form-layout');
        if (formLayout) {
            formLayout.classList.remove('has-record');
            formLayout.classList.add('is-creating');
        }
        this.updateStatus('Toevoegen');
        this.updateUrl();

        // Re-check toolbar overflow (fewer buttons visible in create mode)
        this.checkToolbarOverflow();

        // Enable "new changable only" fields for new records
        this.updateNewChangableOnlyFields(true);

        // Load custom renderers (security_groups, group_menu_rights, etc.) for new records
        // cmaLog.log('[newRecord] About to call loadCustomRenderers');
        this.loadCustomRenderers('').catch(error => {
            cmaLog.error('loadCustomRenderers error (new record):', error);
        });

        // Expand groupboxes that contain empty required fields
        this.expandGroupboxesWithRequiredFields();

        // Validate that all required fields are visible and editable
        // This catches configuration errors where required fields are hidden or readonly in add mode
        this.validateRequiredFieldsAccessible();

        // Update sidepanel title if we're inside one
        this.updateSidepanelTitle('toevoegen');

        // Focus first editable field
        this.focusFirstEditableField();
    }

    /**
     * Focus the first editable (non-readonly, non-disabled, visible) field in the form
     */
    focusFirstEditableField() {
        if (!this.mainForm) return;

        // Find all input-like elements in form order
        const fields = this.mainForm.querySelectorAll(
            'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), ' +
            'select, textarea, lib-datepicker, lib-combo'
        );

        for (const field of fields) {
            // Skip readonly and disabled fields
            if (field.readOnly || field.disabled) continue;
            if (field.hasAttribute('readonly') || field.hasAttribute('disabled')) continue;

            // Skip invisible fields (hidden sections, display:none, etc.)
            if (field.offsetParent === null && field.tagName !== 'INPUT') continue;

            // For web components, focus the internal input
            if (field.tagName === 'LIB-DATEPICKER' || field.tagName === 'CMA-COMBO') {
                const inner = field.shadowRoot?.querySelector('input, select');
                if (inner) {
                    setTimeout(() => inner.focus(), 50);
                    return;
                }
                continue;
            }

            // Use setTimeout to ensure DOM is fully settled (e.g., after combo init)
            setTimeout(() => field.focus(), 50);
            return;
        }
    }

    /**
     * Update sidepanel title suffix (toevoegen/wijzigen)
     * @param {string} suffix - 'toevoegen' or 'wijzigen'
     */
    updateSidepanelTitle(suffix) {
        try {
            // Check if we're inside a sidepanel iframe
            if (self === top) return;

            const topDoc = top.document;
            const panels = topDoc.querySelectorAll('.lib_sidepanel_container');
            if (!panels || panels.length === 0) return;

            // Find our panel (the one containing our iframe)
            for (let i = panels.length - 1; i >= 0; i--) {
                const panel = panels[i];
                const iframe = panel.querySelector('iframe');
                if (iframe && iframe.contentWindow === window) {
                    const titleEl = panel.querySelector('.lib_sidepanel_title');
                    if (titleEl) {
                        // Use singular form name for action labels (e.g., "Opleiding toevoegen")
                        const formName = this.config.formNameSingular || this.config.formName || 'Record';
                        titleEl.textContent = formName + ' ' + suffix;
                        // Also update browser title
                        topDoc.title = formName + ' ' + suffix + ' - CMA';
                    }
                    break;
                }
            }
        } catch (e) {
            // cmaLog.log('[updateTitle] Cross-origin frame access:', e.message);
        }
    }

    /**
     * Clear form fields
     */
    clearForm() {
        // cmaLog.log('[clearForm] ENTRY - mainForm:', !!this.mainForm, 'htmlEditorsInitialized:', this.htmlEditorsInitialized);
        if (!this.mainForm) {
            // cmaLog.log('[clearForm] EXIT - no mainForm');
            return;
        }

        this.mainForm.reset();

        // Clear validation state
        this.mainForm.querySelectorAll('.invalid').forEach(el => {
            el.classList.remove('invalid');
        });
        this.mainForm.querySelectorAll('.validation-error').forEach(el => {
            el.remove();
        });

        // Clear DIV/SPAN elements with data-field (read-only label fields, including GUID)
        // These are populated via textContent in populateForm() and not reset by form.reset()
        // IMPORTANT: Skip radiocontrolgroup containers - textContent='' destroys their radio buttons
        this.mainForm.querySelectorAll('div[data-field], span[data-field]').forEach(el => {
            if (el.classList.contains('radiocontrolgroup')) return;
            el.textContent = '';
        });

        // Clear date inputs explicitly (datepickers may not reset properly)
        this.mainForm.querySelectorAll('input[data-type="date"], input[type="date"]').forEach(input => {
            input.value = '';
        });

        // Clear lib-datepicker web components (Shadow DOM, not reached by input selectors)
        this.mainForm.querySelectorAll('lib-datepicker').forEach(dp => {
            dp.value = '';
        });

        // Clear time inputs explicitly
        this.mainForm.querySelectorAll('input[data-type="time"], input[type="time"]').forEach(input => {
            input.value = '';
        });

        // Clear lib-timepicker web components (Shadow DOM)
        this.mainForm.querySelectorAll('lib-timepicker').forEach(tp => {
            tp.value = '';
        });

        // Reset lib-combo dropdowns
        this.mainForm.querySelectorAll('lib-combo').forEach(combo => {
            combo.value = '';
        });

        // Reset switches
        this.mainForm.querySelectorAll('.switch').forEach(sw => {
            sw.classList.remove('checked');
        });

        // Reset image previews
        this.mainForm.querySelectorAll('[data-image-preview]').forEach(img => {
            img.src = '';
        });

        // Clear CKEditor instances
        if (typeof CKEDITOR !== 'undefined') {
            const instanceNames = Object.keys(CKEDITOR.instances);
            // cmaLog.log('[clearForm] Clearing CKEDITOR instances:', instanceNames);
            for (const name in CKEDITOR.instances) {
                if (CKEDITOR.instances.hasOwnProperty(name)) {
                    try {
                        CKEDITOR.instances[name].setData('');
                        // cmaLog.log('[clearForm] Cleared CKEditor:', name);
                    } catch (e) {
                        cmaLog.error('[clearForm] Error clearing CKEditor', name, ':', e.message);
                    }
                }
            }
        } else {
            // cmaLog.log('[clearForm] CKEDITOR not available');
        }

        // Clear blockedit containers (content blocks)
        // cmaLog.log('[clearForm] blockedit_clear available:', typeof blockedit_clear === 'function');
        if (typeof blockedit_clear === 'function') {
            blockedit_clear();
        }

        // Reset HTML editors initialization flag so they can be re-initialized for new records
        // cmaLog.log('[clearForm] Resetting htmlEditorsInitialized flag from', this.htmlEditorsInitialized, 'to false');
        this.htmlEditorsInitialized = false;

        // Hide last modified
        const lastModified = document.getElementById('lastModified');
        if (lastModified) {
            lastModified.style.display = 'none';
        }

        // Reset extra button URLs to templates
        this.resetExtraButtonUrls();
    }

    /**
     * Apply default values to form fields for new records
     * Reads data-default attributes from fields and sets their values
     */
    applyDefaultValues() {
        if (!this.mainForm) return;

        // Find all elements with data-default attribute
        this.mainForm.querySelectorAll('[data-default]').forEach(field => {
            const defaultValue = field.getAttribute('data-default');
            if (defaultValue === '' || defaultValue === null) return;

            const fieldType = field.getAttribute('data-type');
            const fieldName = field.getAttribute('name') || field.getAttribute('data-field');

            // Handle different field types
            if (fieldType === 'checkbox' || field.tagName === 'LIB-SWITCH') {
                // Checkbox/switch: default is 'True', 'checked', or 'true'
                const isChecked = defaultValue === 'True' || defaultValue === 'checked' || defaultValue === 'true' || defaultValue === '1';
                if (field.tagName === 'LIB-SWITCH') {
                    field.checked = isChecked;
                } else if (field.type === 'checkbox') {
                    field.checked = isChecked;
                }
            } else if (fieldType === 'radiogroup') {
                // Radio group: find the radio with the default value
                const container = field.closest('.radio-group') || field;
                const radios = container.querySelectorAll('input[type="radio"]');
                radios.forEach(radio => {
                    if (radio.value === defaultValue) {
                        radio.checked = true;
                    }
                });
            } else if (field.tagName === 'LIB-COMBO') {
                // lib-combo dropdowns
                field.value = defaultValue;
            } else if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
                // Text inputs and textareas
                field.value = defaultValue;
            } else if (field.tagName === 'SELECT') {
                // Select dropdowns
                field.value = defaultValue;
            }

            // cmaLog.log('[applyDefaultValues] Set', fieldName, '=', defaultValue);
        });

        // Also handle lib-switch elements that may have data-default on their container
        this.mainForm.querySelectorAll('lib-switch[data-default]').forEach(sw => {
            const defaultValue = sw.getAttribute('data-default');
            const isChecked = defaultValue === 'True' || defaultValue === 'true' || defaultValue === '1';
            sw.checked = isChecked;
        });
    }

    /**
     * Update field readonly state for "new changable only" fields
     * These fields are editable when adding new records, but readonly when editing existing records
     * @param {boolean} isNewRecord - True if adding new record, false if editing existing
     */
    updateNewChangableOnlyFields(isNewRecord) {
        if (!this.mainForm) return;

        // Find all fields with data-new-changable-only attribute
        this.mainForm.querySelectorAll('[data-new-changable-only="true"]').forEach(field => {
            const shouldBeReadonly = !isNewRecord;

            // Update the data-readonly attribute
            field.dataset.readonly = shouldBeReadonly ? 'true' : 'false';

            // Update required status - newChangableOnly fields should only be required for new records
            // Store original required status on first call
            if (field.dataset.originalRequired === undefined) {
                field.dataset.originalRequired = field.dataset.required || 'false';
            }
            if (shouldBeReadonly) {
                // Editing existing record - not required
                field.dataset.required = 'false';
            } else {
                // New record - restore original required status
                field.dataset.required = field.dataset.originalRequired;
            }

            // Handle input/textarea elements
            if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
                field.readOnly = shouldBeReadonly;
                if (shouldBeReadonly) {
                    field.setAttribute('readonly', 'readonly');
                } else {
                    field.removeAttribute('readonly');
                }
            }

            // Handle select elements
            if (field.tagName === 'SELECT') {
                field.disabled = shouldBeReadonly;
            }

            // Handle checkboxes/switches
            if (field.type === 'checkbox') {
                field.disabled = shouldBeReadonly;
                const switchEl = field.closest('.switch');
                if (switchEl) {
                    switchEl.classList.toggle('disabled', shouldBeReadonly);
                }
            }

            // Handle lib-switch web components
            if (field.tagName === 'LIB-SWITCH') {
                field.disabled = shouldBeReadonly;
            }

            // Handle lib-combo elements
            if (field.tagName === 'LIB-COMBO') {
                if (shouldBeReadonly) {
                    field.setAttribute('disabled', '');
                } else {
                    field.removeAttribute('disabled');
                }
            }

            // Handle lib-timepicker and lib-datepicker web components
            if (field.tagName === 'LIB-TIMEPICKER' || field.tagName === 'LIB-DATEPICKER') {
                if (shouldBeReadonly) {
                    field.setAttribute('readonly', '');
                } else {
                    field.removeAttribute('readonly');
                }
            }
        });
    }

    /**
     * Save record via AJAX
     * @param {boolean} closeAfter - If true, close form after save
     */
    async saveRecord(closeAfter = false) {
        // Performance tracking
        const perfId = 'saveRecord_' + Date.now();
        const currentId = cmaGetRecordId();
        const isNew = currentId === null || currentId === undefined || currentId === '';
        cmaPerf.start(perfId, { formId: this.formId, isNew: isNew });
        cmaPerf.count('saveRecord.calls');

        // cmaLog.log('saveRecord called, mainForm:', this.mainForm, 'currentRecordId:', cmaGetRecordId());

        if (!this.mainForm) {
            cmaLog.error('saveRecord: mainForm is null');
            cmaPerf.end(perfId, { error: 'mainForm is null' });
            return;
        }

        // Validate
        cmaPerf.mark('saveRecord_validateStart');
        if (!this.validateForm()) {
            // cmaLog.log('saveRecord: validation failed');
            cmaPerf.end(perfId, { error: 'validation failed' });
            cmaPerf.count('saveRecord.validationErrors');
            return;
        }
        cmaPerf.mark('saveRecord_validateEnd');
        cmaPerf.measure('saveRecord.validate', 'saveRecord_validateStart', 'saveRecord_validateEnd');

        this.showLoading();

        try {
            // Collect form data
            cmaPerf.mark('saveRecord_collectStart');
            const formData = this.collectFormData();
            cmaPerf.mark('saveRecord_collectEnd');
            cmaPerf.measure('saveRecord.collectData', 'saveRecord_collectStart', 'saveRecord_collectEnd');
            cmaPerf.gauge('saveRecord.fieldCount', Object.keys(formData).length);

            // Debug: Log complete form data being saved
            // cmaLog.log('[FormController] saveRecord: Complete form data:', formData);

            // Build form data for POST
            const postData = new FormData();
            postData.append('action', 'save');
            postData.append('jsonForm', this.jsonForm);
            const currentRecordIdForSave = cmaGetRecordId();
            if (currentRecordIdForSave) {
                postData.append('ID', currentRecordIdForSave);
            }
            for (const [key, value] of Object.entries(formData)) {
                // Handle array fields (e.g., user_groups[]) - append each value separately
                if (Array.isArray(value)) {
                    for (const item of value) {
                        postData.append(key, item);
                    }
                } else {
                    postData.append(key, value);
                }
            }

            // cmaLog.log('saveRecord POST data:', 'formId=', this.formId, 'isJsonForm=', this.isJsonForm, 'currentRecordId=', cmaGetRecordId());

            cmaPerf.mark('saveRecord_fetchStart');
            const response = await fetch(`/cma/form_api.php`, {
                method: 'POST',
                body: postData,
            });
            cmaPerf.mark('saveRecord_fetchEnd');
            cmaPerf.measure('saveRecord.network', 'saveRecord_fetchStart', 'saveRecord_fetchEnd');

            if (!response.ok) {
                throw new Error(`[saveRecord] HTTP ${response.status} ${response.statusText}`);
            }

            // Get response as text first to check if it's JSON
            const responseText = await response.text();
            let result;

            try {
                result = JSON.parse(responseText);
            } catch (e) {
                // Non-JSON response - show in popup
                // cmaLog.log('saveRecord: non-JSON response, showing in popup');
                this.showHtmlResponsePopup(responseText, 'Resultaat');
                cmaPerf.end(perfId, { htmlResponse: true });
                return;
            }

            if (result.success) {
                // cmaLog.log('saveRecord: SUCCESS, id=', result.id, 'isNew=', result.isNew, 'message=', result.message);
                cmaSetRecordId(result.id);
                this.setDirty(false);
                this.updateStatus(result.message);

                // Update URL first
                this.updateUrl();

                // Show subforms if new record was saved (via CSS class)
                if (result.isNew) {
                    document.body.classList.add('has-subform');
                    document.body.classList.add('has-record');
                    document.body.classList.remove('is-creating');
                    this.updateFormLayoutState({ isCreating: false, hasRecord: true });
                }

                this.showSuccess(result.message || 'Record opgeslagen');

                // Execute afterpost URL if configured
                if (this.afterPostUrl) {
                    await this.executeAfterPost(result.id);
                }

                // Close form if requested (popup mode)
                if (closeAfter) {
                    // Pass record ID for targeted row refresh in parent
                    this.closeForm(cmaGetRecordId(), false);
                } else {
                    // For existing records, refresh just the changed row (more reliable)
                    // For new records, reload entire list to include the new item
                    if (result.isNew) {
                        // cmaLog.log('saveRecord: new record, id=', result.id, ', calling loadList(true, true)');
                        await this.loadList(true, true);
                        // cmaLog.log('saveRecord: loadList completed, now selecting item with id=', cmaGetRecordId());
                    } else {
                        // cmaLog.log('saveRecord: existing record, calling refreshRow');
                        await this.refreshRow(cmaGetRecordId());
                    }
                    // cmaLog.log('saveRecord: list refresh completed');

                    // Mark form as clean since save succeeded
                    this.setDirty(false);

                    // Ensure row is selected in list
                    const selectRecordId = cmaGetRecordId();
                    // cmaLog.log('saveRecord: selectListItem with id=', selectRecordId);
                    this.selectListItem(selectRecordId);

                    // Debug: Check if item was found in list
                    const foundItem = this.listContent?.querySelector(`[data-id="${selectRecordId}"], a[href*="loadRecord(${selectRecordId})"], a[href*="loadRecord('${selectRecordId}')"]`);
                    // cmaLog.log('saveRecord: item found in list=', !!foundItem, foundItem);
                }

                cmaPerf.end(perfId, { success: true, isNew: result.isNew, recordId: result.id });
                cmaPerf.count('saveRecord.success');
            } else {
                // SAVE FAILED - Log prominently for developer visibility
                const errorMsg = result.error || 'Opslaan mislukt (geen foutmelding van server)';
                cmaLog.error('SAVE FAILED:', errorMsg, {
                    form: this.jsonForm,
                    recordId: cmaGetRecordId(),
                    serverResponse: result,
                    formData: formData
                });

                // Show error to user
                this.showError(errorMsg);
                cmaPerf.end(perfId, { success: false, error: result.error });
                cmaPerf.count('saveRecord.errors');
            }
        } catch (error) {
            // NETWORK/PARSE ERROR - Log prominently
            cmaLog.error('SAVE NETWORK ERROR:', error.message, {
                form: this.jsonForm,
                recordId: cmaGetRecordId(),
                stack: error.stack
            });

            this.showError('Netwerkfout bij opslaan: ' + error.message);
            cmaPerf.end(perfId, { error: error.message });
            cmaPerf.count('saveRecord.networkErrors');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Cancel changes and reload the current record
     * - If editing existing record: reloads the record
     * - If creating new record: clears the form and returns to empty state
     * - If in popup: closes the popup window
     */
    async cancelChanges() {
        const isCreating = document.body.classList.contains('is-creating');

        // Only ask for confirmation if form has actual changes
        if (this.hasUnsavedChanges()) {
            const changeSummary = this.formatChangeSummary();
            const confirmed = await libConfirm('Weet je zeker dat je de wijzigingen wilt annuleren?' + changeSummary, {
                title: 'Wijzigingen annuleren?',
                confirmText: 'Annuleren',
                cancelText: 'Terug',
                type: 'warning',
                html: true
            });
            if (!confirmed) {
                return;
            }
        }

        // Reset dirty state
        this.setDirty(false);

        // If in popup, close the window
        if (this.isInPopup()) {
            this.closeForm(null, false);  // null recordId, not deleted
            return;
        }

        // Reload the current record to revert changes
        const currentRecordIdForCancel = cmaGetRecordId();
        if (currentRecordIdForCancel && !isCreating) {
            this.loadRecord(currentRecordIdForCancel);
        } else {
            // Creating new record or no record loaded - return to empty state
            cmaSetRecordId(null);
            document.body.classList.remove('is-creating');
            document.body.classList.remove('has-record');
            this.updateFormLayoutState({ isCreating: false, hasRecord: false });
            this.hideDetailPanel();
            this.updateUrl();  // Remove ID from URL
        }
    }

    /**
     * Collect form data for submission
     */
    collectFormData() {
        const data = {};

        if (!this.mainForm) return data;

        // Step 1: Collect content blocks and put them into CKEditor containers
        // This must happen BEFORE syncing CKEditor to textareas
        if (typeof blockedit_collect_htmls === 'function') {
            try {
                blockedit_collect_htmls();
                // cmaLog.log('collectFormData: blockedit_collect_htmls completed');
            } catch (e) {
                cmaLog.warn('blockedit_collect_htmls failed:', e);
            }
        }

        // Step 2: Sync CKEditor instances to their textareas
        // This must happen AFTER content blocks are collected
        if (typeof CKEDITOR !== 'undefined') {
            for (const name in CKEDITOR.instances) {
                if (CKEDITOR.instances.hasOwnProperty(name)) {
                    try {
                        CKEDITOR.instances[name].updateElement();
                    } catch (e) {
                        cmaLog.warn('CKEditor updateElement failed for', name, e);
                    }
                }
            }
        }

        // Build changelog before collecting data
        this.buildChangelog();

        // Debug: Check _changelog value after building
        const changelogEl = document.getElementById('_changelog');
        // cmaLog.log('[collectFormData] _changelog after buildChangelog:', changelogEl ? changelogEl.value.length : 'element not found', 'chars');
        // Always log changelog status for debugging Notificatie issue
        // console.info('[collectFormData] _changelog length:', changelogEl ? changelogEl.value.length : 'element not found');

        // Debug: Check if rights-matrix exists and is inside the form
        const rightsMatrix = document.querySelector('.rights-matrix');
        const customRenderer = document.querySelector('.custom-renderer[data-field="group_menu_rights"]');
        // cmaLog.log('[collectFormData] Rights matrix exists:', !!rightsMatrix);
        // cmaLog.log('[collectFormData] Custom renderer exists:', !!customRenderer);
        if (customRenderer) {
            const isInsideForm = this.mainForm.contains(customRenderer);
            // cmaLog.log('[collectFormData] Custom renderer inside mainForm:', isInsideForm);
        }

        // Debug: Count all inputs in rights-matrix before FormData
        const allRightsInputs = document.querySelectorAll('.rights-matrix input');
        // cmaLog.log('[collectFormData] Total rights-matrix inputs in DOM:', allRightsInputs.length);
        if (allRightsInputs.length > 0) {
            const radioInputs = Array.from(allRightsInputs).filter(i => i.type === 'radio');
            const checkboxInputs = Array.from(allRightsInputs).filter(i => i.type === 'checkbox');
            // cmaLog.log('[collectFormData] Radio inputs:', radioInputs.length, ', Checkbox inputs:', checkboxInputs.length);
            // cmaLog.log('[collectFormData] First 3 radio names:', radioInputs.slice(0, 3).map(i => ({ name: i.name, value: i.value, checked: i.checked })));
        }

        // Get all form fields
        const formData = new FormData(this.mainForm);

        // Debug: Log rights-matrix fields from FormData
        const rightsFields = [];
        for (const [name, value] of formData.entries()) {
            if (name.startsWith('group_menu_rights') || name.startsWith('group_report_rights')) {
                rightsFields.push({ name, value });
            }
        }
        if (rightsFields.length > 0) {
            // cmaLog.log('[collectFormData] Found', rightsFields.length, 'rights fields in FormData:', rightsFields.slice(0, 10));
        } else {
            // cmaLog.log('[collectFormData] WARNING: No rights fields found in FormData');
            // Check if they exist in DOM but not in FormData (possibly outside form element)
            if (allRightsInputs.length > 0) {
                // cmaLog.log('[collectFormData] Rights inputs exist in DOM but NOT in FormData - check if they are inside <form>');
                // Verify each input is inside the form
                const insideForm = Array.from(allRightsInputs).filter(i => this.mainForm.contains(i)).length;
                // cmaLog.log('[collectFormData] Rights inputs inside form element:', insideForm, 'of', allRightsInputs.length);
            }
        }

        // Allowed underscore fields (changelog fields that need to be sent)
        const allowedUnderscoreFields = [
            '_changelog', '_changelog_flds', '_changelog_type', '_changelog_email',
            '_changelog_user', '_changelog_form', '_changelog_formname', '_changelog_copy', '_changelog_copy_id',
            '__fully_loaded'
        ];

        for (const [name, value] of formData.entries()) {
            // Skip action fields
            if (name.startsWith('action')) continue;

            // Allow specific underscore fields, skip others
            if (name.startsWith('_') && !allowedUnderscoreFields.includes(name)) continue;

            // Handle array fields (e.g., user_groups[]) - collect multiple values into array
            if (name.endsWith('[]')) {
                if (!data[name]) {
                    data[name] = [];
                }
                data[name].push(value);
            } else {
                data[name] = value;
            }
        }

        // Debug: Log radio group values
        const radioGroups = this.mainForm.querySelectorAll('.radio-group');
        radioGroups.forEach(rg => {
            const fieldName = rg.dataset.field;
            const checkedRadio = rg.querySelector('input[type="radio"]:checked');
            // cmaLog.log('collectFormData: Radio group', fieldName, 'checked value:', checkedRadio ? checkedRadio.value : 'NONE');
            // Ensure radio value is in data
            if (checkedRadio && fieldName && !data[fieldName]) {
                cmaLog.warn('collectFormData: Radio group', fieldName, 'value was missing, adding:', checkedRadio.value);
                data[fieldName] = checkedRadio.value;
            }
        });

        // Handle checkboxes (unchecked ones aren't in FormData)
        // Skip array fields (e.g., user_groups[]) - they're handled by FormData iteration above
        this.mainForm.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (!cb.name.startsWith('_') && !cb.name.endsWith('[]')) {
                data[cb.name] = cb.checked ? 'True' : 'False';
            }
        });

        // Explicitly collect inputs from rights-matrix (FormData might miss them if outside form)
        // This ensures rights-matrix access levels and button checkboxes are captured
        const rightsMatrixRadios = document.querySelectorAll('.rights-matrix input[type="radio"]:checked');
        // cmaLog.log('[collectFormData] Found', rightsMatrixRadios.length, 'checked rights-matrix radios');
        rightsMatrixRadios.forEach(radio => {
            if (radio.name && !data[radio.name]) {
                data[radio.name] = radio.value;
                // Log first 10 rights radios for debugging
                if (radio.name.indexOf('group_menu_rights') === 0) {
                    // cmaLog.log('[collectFormData] Rights radio:', radio.name, '=', radio.value);
                }
            }
        });
        const rightsMatrixCheckboxes = document.querySelectorAll('.rights-matrix input[type="checkbox"]');
        // cmaLog.log('[collectFormData] Found', rightsMatrixCheckboxes.length, 'rights-matrix checkboxes');
        rightsMatrixCheckboxes.forEach(cb => {
            if (cb.name && !cb.name.startsWith('_')) {
                // Override any existing value - rights-matrix checkboxes need explicit handling
                data[cb.name] = cb.checked ? 'True' : 'False';
            }
        });

        // Explicitly collect inputs from checklist-inline (group_report_rights uses this class)
        // Same document-level query to capture checkboxes that might be loaded via AJAX
        const checklistInlineInputs = document.querySelectorAll('.checklist-inline input[type="checkbox"]');
        // cmaLog.log('[collectFormData] Found', checklistInlineInputs.length, 'checklist-inline checkboxes');
        checklistInlineInputs.forEach(cb => {
            if (cb.name && !cb.name.startsWith('_') && !cb.name.endsWith('[]')) {
                data[cb.name] = cb.checked ? 'True' : 'False';
                // cmaLog.log('[collectFormData] Checklist-inline checkbox:', cb.name, '=', data[cb.name]);
            }
        });

        // Handle lib-switch web components
        this.mainForm.querySelectorAll('lib-switch[name]').forEach(sw => {
            if (!sw.getAttribute('name').startsWith('_')) {
                data[sw.getAttribute('name')] = sw.checked ? 'True' : 'False';
            }
        });

        // Handle lib-combo web components
        this.mainForm.querySelectorAll('lib-combo[name]').forEach(combo => {
            const comboName = combo.getAttribute('name');
            if (comboName && !comboName.startsWith('_')) {
                data[comboName] = combo.value;
            }
        });

        // Handle CKEditor content
        if (typeof CKEDITOR !== 'undefined') {
            // cmaLog.log('collectFormData: CKEDITOR available, instances:', Object.keys(CKEDITOR.instances));
            for (const name in CKEDITOR.instances) {
                if (CKEDITOR.instances.hasOwnProperty(name)) {
                    const editorData = CKEDITOR.instances[name].getData();
                    // cmaLog.log('collectFormData: CKEditor field', name, 'data length:', editorData.length);
                    data[name] = editorData;
                }
            }
        } else {
            // cmaLog.log('collectFormData: CKEDITOR not available');
            // Check if there are HTML textareas that should have CKEditor
            const htmlTextareas = this.mainForm?.querySelectorAll('textarea[data-allow-html="true"]') || [];
            if (htmlTextareas.length > 0) {
                cmaLog.warn('collectFormData: Found', htmlTextareas.length, 'HTML textareas but CKEDITOR is not available');
                // Fallback: use textarea values directly
                htmlTextareas.forEach(ta => {
                    if (ta.name && ta.value) {
                        // cmaLog.log('collectFormData: Using textarea value for', ta.name);
                        data[ta.name] = ta.value;
                    }
                });
            }
        }

        // Debug: Check if _changelog made it into data - log content as well
        if (data['_changelog']) {
            // cmaLog.log('[collectFormData] _changelog in data:', data['_changelog'].length + ' chars');
            // cmaLog.log('[collectFormData] _changelog CONTENT:', data['_changelog']);
        } else {
            // cmaLog.log('[collectFormData] _changelog: NOT FOUND');
        }

        // Debug: Final count of rights fields in data object
        const finalRightsKeys = Object.keys(data).filter(k => k.startsWith('group_menu_rights') || k.startsWith('group_report_rights'));
        // cmaLog.log('[collectFormData] FINAL rights fields in data:', finalRightsKeys.length);
        if (finalRightsKeys.length > 0) {
            // cmaLog.log('[collectFormData] First 5 rights fields:', finalRightsKeys.slice(0, 5).map(k => ({ key: k, value: data[k] })));
        }

        return data;
    }

    /**
     * Build changelog for tracking changes
     * Based on formval.js implementation
     */
    buildChangelog() {
        if (!this.mainForm) {
            // cmaLog.log('[buildChangelog] mainForm is null, exiting');
            return false;
        }

        // Initialize changelog elements
        const frm_changelog = document.getElementById('_changelog');
        const frm_changelog_flds = document.getElementById('_changelog_flds');
        const frm_changelog_type = document.getElementById('_changelog_type');
        const frm_action = document.getElementById('actie') || document.getElementById('action');

        if (!frm_changelog) {
            cmaLog.warn('[buildChangelog] _changelog element not found, exiting');
            return false;
        }
        // cmaLog.log('[buildChangelog] started, isAdd:', !cmaGetRecordId(), 'form elements:', this.mainForm.elements.length);

        // Set changelog type
        const isAdd = !cmaGetRecordId();
        if (frm_changelog_type) {
            frm_changelog_type.value = isAdd ? 'add' : 'edit';
        }

        // Show all fields for add mode
        const bShowAll = frm_changelog && frm_changelog_type && frm_changelog_type.value === 'add';

        // Clear changelog
        frm_changelog.value = '';
        if (frm_changelog_flds) frm_changelog_flds.value = '';

        let blnRetval = false;

        // Track processed array fields to avoid duplicates in changelog_flds
        const processedArrayFields = new Set();

        // Iterate through all form elements
        for (let tel = 0; tel < this.mainForm.elements.length; tel++) {
            const objfield = this.mainForm.elements[tel];

            if (objfield.name) {
                // Skip certain field types
                if (objfield.name.substr(0, 10) === '_changelog' ||
                    objfield.name.substr(objfield.name.length - 7) === '__label' ||
                    objfield.name.substr(objfield.name.length - 6) === '_width' ||
                    objfield.name.substr(objfield.name.length - 7) === '_height' ||
                    objfield.name.substr(objfield.name.length - 5) === '_path' ||
                    objfield.name.substr(objfield.name.length - 11) === '_resizetype' ||
                    objfield.name.substr(objfield.name.length - 13) === '_resizeheight' ||
                    objfield.name.substr(objfield.name.length - 12) === '_resizewidth' ||
                    objfield.name.substr(0, 10) === 'blockedit_' ||
                    objfield.name.substr(0, 5) === '_old_') {
                    continue;
                }

                if (objfield.type === 'text' || objfield.type === 'file' || objfield.type === 'password' || objfield.type === 'textarea' || objfield.type === 'hidden') {
                    // Use data-original-value (captured after AJAX load) for comparison,
                    // fall back to empty string for AJAX forms (defaultValue is unreliable)
                    const originalValue = objfield.dataset.originalValue ?? objfield.defaultValue ?? '';
                    // Debug first few fields
                    if (tel < 5) {
                        // cmaLog.log('[buildChangelog] field:', objfield.name, 'type:', objfield.type, 'current:', objfield.value.substring(0, 30), 'original:', String(originalValue).substring(0, 30), 'hasDataOrig:', !!objfield.dataset.originalValue, 'bShowAll:', bShowAll);
                    }
                    if (objfield.value !== originalValue || bShowAll) {
                        let sOld, sNew;

                        // Handle lib-combo fields - get display text from selected option
                        if (objfield.tagName === 'LIB-COMBO') {
                            sOld = objfield.getAttribute('data-default_value') || objfield.dataset.originalValue || '';
                            const selectedOpts = objfield.selectedOptions;
                            sNew = selectedOpts && selectedOpts.length > 0 ? selectedOpts[0].label : objfield.value;
                        } else {
                            // Trim values - use data-original-value for AJAX-loaded forms
                            const trimmedValue = this.specTrim(objfield.value);
                            const trimmedDefault = this.specTrim(objfield.dataset.originalValue ?? objfield.defaultValue ?? '');

                            sOld = this.specCharReplace(trimmedDefault);
                            sNew = trimmedValue;
                        }

                        // For ADD mode (bShowAll), only include fields that have a value
                        // For EDIT mode, include if value changed (even if now empty)
                        const hasNewValue = sNew !== '' && sNew !== null && sNew !== undefined;
                        const valueChanged = sNew !== sOld;

                        if (objfield.name && (valueChanged || (bShowAll && hasNewValue))) {
                            // cmaLog.log('[buildChangelog] CHANGE detected:', objfield.name, 'from:', String(sOld).substring(0, 30), 'to:', String(sNew).substring(0, 30));
                            // console.info('[buildChangelog] CHANGE:', objfield.name, 'old:', String(sOld).substring(0, 50), 'new:', String(sNew).substring(0, 50));
                            this.formChangeAdd(objfield, sOld, sNew, frm_changelog, frm_changelog_flds, frm_changelog_type, frm_action);
                            blnRetval = true;
                        }
                    }
                } else if (objfield.type === 'select-one' || objfield.type === 'select' || objfield.type === 'select-multiple') {
                    let sNewSel = '';
                    let sOldSel = '';

                    // Sortlists require determination of changed order
                    if (objfield.name.substr(0, 6).toLowerCase() === 'srtlst') {
                        const orderField = this.mainForm.elements[objfield.name + '_info_order'];
                        if (orderField) {
                            sOldSel = orderField.value;
                            for (let opt = 0; opt < objfield.options.length; opt++) {
                                sNewSel = (sNewSel === '' ? '' : sNewSel + '<br>') + objfield.options[opt].text;
                            }
                        }
                    } else {
                        for (let opt = 0; opt < objfield.options.length; opt++) {
                            if (objfield.options[opt].defaultSelected) {
                                sOldSel = (sOldSel === '' ? '' : sOldSel + ';') + objfield.options[opt].text;
                            }
                            if (objfield.options[opt].selected) {
                                sNewSel = (sNewSel === '' ? '' : sNewSel + ';') + objfield.options[opt].text;
                            }
                        }
                    }

                    if ((sOldSel !== sNewSel) || bShowAll) {
                        this.formChangeAdd(objfield, sOldSel, sNewSel, frm_changelog, frm_changelog_flds, frm_changelog_type, frm_action);
                        blnRetval = true;
                    }
                } else if (objfield.type === 'checkbox' || objfield.type === 'radio') {
                    // For array fields (checkboxes with name ending in []), only add once to changelog_flds
                    const isArrayField = objfield.name.endsWith('[]');
                    if (isArrayField && processedArrayFields.has(objfield.name)) {
                        continue; // Skip - already processed this array field
                    }

                    // Check data-default attribute first
                    const sAttrValue = objfield.getAttribute('data-default');
                    let bDefaultValue;
                    if (sAttrValue !== null && sAttrValue !== '') {
                        bDefaultValue = (sAttrValue === 'checked');
                    } else {
                        bDefaultValue = objfield.defaultChecked;
                    }

                    if ((objfield.checked !== bDefaultValue) || bShowAll) {
                        this.formChangeAdd(objfield, bDefaultValue ? 'Aan' : 'Uit', objfield.checked ? 'Aan' : 'Uit', frm_changelog, frm_changelog_flds, frm_changelog_type, frm_action);
                        blnRetval = true;
                        if (isArrayField) {
                            processedArrayFields.add(objfield.name);
                        }
                    }
                }
            }
        }

        // Handle lib-switch web components (not in form.elements collection)
        this.mainForm.querySelectorAll('lib-switch[name]').forEach(sw => {
            const name = sw.getAttribute('name');
            if (name && !name.startsWith('_')) {
                const sAttrValue = sw.getAttribute('data-default');
                let bDefaultValue = false;
                if (sAttrValue !== null && sAttrValue !== '') {
                    bDefaultValue = (sAttrValue.toLowerCase() === 'true' || sAttrValue === '1');
                }

                if ((sw.checked !== bDefaultValue) || bShowAll) {
                    // Create a mock field object for formChangeAdd
                    const mockField = { name: name };
                    this.formChangeAdd(mockField, bDefaultValue ? 'Aan' : 'Uit', sw.checked ? 'Aan' : 'Uit', frm_changelog, frm_changelog_flds, frm_changelog_type, frm_action);
                    blnRetval = true;
                }
            }
        });

        // Close changelog table
        if (frm_changelog && frm_changelog.value !== '') {
            frm_changelog.value = frm_changelog.value + '\r\n</table>';
        }

        // Debug: Log changelog result
        // cmaLog.log('[buildChangelog] result:', blnRetval ? 'changes detected' : 'no changes', 'changelog length:', frm_changelog ? frm_changelog.value.length : 0);
        // Always log for debugging Notificatie issue
        // console.info('[buildChangelog] result:', blnRetval ? 'changes detected' : 'no changes', 'changelog length:', frm_changelog ? frm_changelog.value.length : 0, 'fields processed:', this.mainForm ? this.mainForm.elements.length : 0);

        return blnRetval;
    }

    /**
     * Add entry to changelog
     */
    formChangeAdd(oFld, sOld, sNew, frm_changelog, frm_changelog_flds, frm_changelog_type, frm_action) {
        if (!frm_changelog) return;

        const blnChange = frm_changelog_type && (frm_changelog_type.value === 'edit') && (!frm_action || frm_action.value !== 'delete');
        const thstyle = 'style="font-size:10pt;background-color:#002350;color:white;text-align:left"';

        // Initialize table header if empty
        if (frm_changelog.value === '') {
            let sHead = '<table cellspacing="0" cellpadding="3"><tr><th ' + thstyle + '>Veld</th>';
            if (blnChange) {
                sHead += '<th ' + thstyle + '>was</th><th ' + thstyle + '>gewijzigd in</th></tr>';
            } else {
                sHead += '<th ' + thstyle + '>Inhoud</th></tr>';
            }
            frm_changelog.value = sHead;
        }

        // Try to find matching label
        let sFld;
        const labelField = this.mainForm.elements[oFld.name + '__label'];
        if (labelField) {
            sFld = labelField.value;
        } else {
            sFld = oFld.name;
        }

        // Build table row
        let sLine = '\r\n<TR id="' + sFld + '" vAlign="Top"><TD style="border-bottom:1px solid #003366;border-left:1px solid #003366">' + sFld + '</TD>';
        if (blnChange) {
            sLine += '<TD style="border-bottom:1px solid #003366">' + sOld + '&nbsp;</TD>';
        }
        sLine += '<TD style="border-bottom:1px solid #003366;border-right:1px solid #003366">' + sNew + '&nbsp;</TD></TR>';

        frm_changelog.value = frm_changelog.value + sLine;

        // Add to comma-delimited list of changed fields
        if (frm_changelog_flds) {
            frm_changelog_flds.value = (frm_changelog_flds.value !== '' ? frm_changelog_flds.value + ',' : '') + oFld.name;
        }
    }

    /**
     * Trim whitespace, newlines from start and end
     */
    specTrim(strValue) {
        if (!strValue) return '';
        // Trim end
        while (strValue.length > 0 && (strValue.charCodeAt(strValue.length - 1) === 32 || strValue.charCodeAt(strValue.length - 1) === 10 || strValue.charCodeAt(strValue.length - 1) === 13)) {
            strValue = strValue.substr(0, strValue.length - 1);
        }
        // Trim start
        while (strValue.length > 0 && (strValue.charCodeAt(0) === 32 || strValue.charCodeAt(0) === 10 || strValue.charCodeAt(0) === 13)) {
            strValue = strValue.substr(1, strValue.length - 1);
        }
        return strValue;
    }

    /**
     * Replace special characters (Greek letters)
     */
    specCharReplace(stext) {
        if (!stext) return '';
        stext = stext.split('&#916;').join(String.fromCharCode(916));
        stext = stext.split('&#969;').join(String.fromCharCode(969));
        return stext;
    }

    /**
     * Validate form
     */
    validateForm() {
        if (!this.mainForm) return true;

        let isValid = true;
        const missingFields = [];

        // Clear previous validation states
        this.mainForm.querySelectorAll('.invalid').forEach(el => {
            el.classList.remove('invalid');
        });
        this.mainForm.querySelectorAll('.validation-error').forEach(el => {
            el.remove();
        });

        // Check required fields
        this.mainForm.querySelectorAll('[data-required="true"]').forEach(field => {
            let value = field.value;
            const fieldType = field.dataset.type || field.type;

            // Handle checkboxes
            if (field.type === 'checkbox') {
                value = field.checked ? 'checked' : '';
            }

            // Handle radio groups (container div with radio inputs inside)
            if (fieldType === 'radiogroup') {
                const checkedRadio = field.querySelector('input[type="radio"]:checked');
                value = checkedRadio ? checkedRadio.value : '';
            }

            // Handle lib-combo
            if (field.tagName === 'LIB-COMBO') {
                value = field.value;
            }

            if (!value || value === '') {
                field.classList.add('invalid');
                isValid = false;
                // Get field label
                const fieldName = this.getFieldLabel(field);
                if (fieldName) {
                    missingFields.push(fieldName);
                }
            }
        });

        // Format validation via formval_nl.js (email, postcode, telefoon, url, etc.)
        const formatErrors = [];
        if (typeof form_valid_field === 'function') {
            this.mainForm.querySelectorAll('[data-validation-type]').forEach(field => {
                if (!field.value || field.value === '') return; // skip empty (required check above handles that)
                // Clear previous error state
                field.setAttribute('data-error', '');
                field.setAttribute('data-error-short', '');
                if (!form_valid_field(field)) {
                    const errorMsg = field.getAttribute('data-error-short');
                    if (errorMsg) {
                        field.classList.add('invalid');
                        const fieldName = this.getFieldLabel(field);
                        formatErrors.push(fieldName ? fieldName + ': ' + errorMsg : errorMsg);
                    }
                    isValid = false;
                }
            });
        }

        if (!isValid) {
            const parts = [];
            if (missingFields.length > 0) {
                parts.push('Vul alle verplichte velden in: ' + missingFields.join(', '));
            }
            if (formatErrors.length > 0) {
                parts.push(formatErrors.join(', '));
            }
            this.showError(parts.join('. ') || 'Vul alle verplichte velden in');
        }

        return isValid;
    }

    /**
     * Expand groupboxes that contain empty required fields.
     * Called in add mode to ensure all required fields are immediately visible.
     */
    expandGroupboxesWithRequiredFields() {
        if (!this.mainForm) return;

        const requiredFields = this.mainForm.querySelectorAll('[data-required="true"]');
        const expandedGroups = new Set();

        requiredFields.forEach(field => {
            // Check if field is empty
            const value = field.value || '';
            if (value !== '') return;

            // Find the parent row with group info
            const parentRow = field.closest('tr[id^="_g"]');
            if (!parentRow) return;

            const match = parentRow.id.match(/^_g(\d+)_/);
            if (!match) return;

            const groupId = match[1];
            if (expandedGroups.has(groupId)) return;

            // Find the groupbox and open it if collapsed
            const groupbox = document.querySelector(`cma-groupbox[group-id="${groupId}"]`);
            if (groupbox && !groupbox.isOpen) {
                groupbox.open();
                expandedGroups.add(groupId);
            }
        });
    }

    /**
     * Ensure all required fields are visible and editable in Add mode.
     * Auto-fixes hidden/readonly required fields instead of blocking.
     */
    validateRequiredFieldsAccessible() {
        if (!this.mainForm) return;

        const requiredFields = this.mainForm.querySelectorAll('[data-required="true"]');

        // Get parent field info - if we have parentID and parentField, that field will be auto-filled
        const parentField = this.parentField || '';
        const parentID = this.parentID || '';
        const hasParentContext = parentField && parentID;
        const fixed = [];

        requiredFields.forEach(field => {
            const fieldName = field.name || field.id || 'unknown';
            const fieldLabel = this.getFieldLabel(field) || fieldName;

            // Check if this is the parent field that will be auto-filled
            const isParentField = hasParentContext && fieldName.toLowerCase() === parentField.toLowerCase();
            if (isParentField) return;

            // Auto-show hidden required fields
            const isHidden = this.isFieldHidden(field);
            if (isHidden) {
                // Make the field and its parent row visible
                const parentRow = field.closest('tr, .form-group, .field-row, .control-group');
                if (parentRow && window.getComputedStyle(parentRow).display === 'none') {
                    parentRow.style.display = '';
                }
                if (field.type === 'hidden') {
                    // Cannot auto-fix type=hidden - log warning
                    cmaLog.warn('validateRequiredFieldsAccessible: required field is type=hidden:', fieldName);
                } else {
                    field.style.display = '';
                    field.style.visibility = '';
                    fixed.push(fieldLabel);
                }
            }

            // Auto-fix readonly/disabled required fields for add mode
            const isReadonly = field.hasAttribute('readonly') || field.readOnly;
            const isDisabled = field.hasAttribute('disabled') || field.disabled;

            if (isReadonly || isDisabled) {
                field.removeAttribute('readonly');
                field.readOnly = false;
                field.removeAttribute('disabled');
                field.disabled = false;
                if (!fixed.includes(fieldLabel)) {
                    fixed.push(fieldLabel);
                }
            }
        });

        if (fixed.length > 0) {
            // cmaLog.log('validateRequiredFieldsAccessible: auto-fixed fields:', fixed.join(', '));
        }

        return true;
    }

    /**
     * Check if a field is hidden (not visible to the user)
     * Checks the field itself and its parent row/container
     */
    isFieldHidden(field) {
        // Check if field itself is hidden
        if (field.type === 'hidden') {
            // Hidden inputs are expected - but if they're required, that's a problem
            // unless they have a value set programmatically (like parent field)
            // Check if it's a parent field that gets set automatically
            if (field.dataset.parentField === 'true' || field.name === this.parentField) {
                return false; // Parent fields are set automatically, not a problem
            }
            return true;
        }

        // FIRST: Check if inside a collapsed groupbox - fields in collapsed groups are still accessible
        // The user can expand the groupbox to fill in the field
        // We need to check this BEFORE checking parent row visibility because
        // rows inside collapsed groupboxes have display:none
        const parentRow = field.closest('tr[id^="_g"]');
        if (parentRow && parentRow.id) {
            // Row id format is _g{groupId}_{rowNum} - extract groupId
            const match = parentRow.id.match(/^_g(\d+)_/);
            if (match) {
                const groupId = match[1];
                const groupbox = document.querySelector(`cma-groupbox[group-id="${groupId}"]`);
                if (groupbox) {
                    // Check if groupbox itself is visible (not display:none)
                    const groupboxStyle = window.getComputedStyle(groupbox);
                    if (groupboxStyle.display !== 'none') {
                        // Groupbox is visible (even if collapsed), field is accessible
                        return false;
                    }
                }
            }
        }

        const style = window.getComputedStyle(field);
        if (style.display === 'none' || style.visibility === 'hidden') {
            // lib-combo uses Shadow DOM - check host element visibility
            if (field.tagName === 'LIB-COMBO') {
                const comboStyle = window.getComputedStyle(field);
                if (comboStyle.display !== 'none' && comboStyle.visibility !== 'hidden') {
                    return false;
                }
            }
            return true;
        }

        // Check parent row (table row or form-group)
        // This catches truly hidden fields (not inside a groupbox)
        const anyParentRow = field.closest('tr, .form-group, .field-row, .control-group');
        if (anyParentRow) {
            const parentStyle = window.getComputedStyle(anyParentRow);
            if (parentStyle.display === 'none' || parentStyle.visibility === 'hidden') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the label for a field element
     */
    getFieldLabel(field) {
        // Try data-label attribute first
        if (field.dataset.label) {
            return field.dataset.label;
        }
        // Try associated label element
        const id = field.id || field.name;
        if (id) {
            const label = this.mainForm.querySelector(`label[for="${id}"]`);
            if (label) {
                return label.textContent.replace(/[*:]/g, '').trim();
            }
        }
        // Try parent form-group label
        const formGroup = field.closest('.form-group, .control-group, .field-row');
        if (formGroup) {
            const label = formGroup.querySelector('label');
            if (label) {
                return label.textContent.replace(/[*:]/g, '').trim();
            }
        }
        // Fallback to field name or placeholder
        return field.placeholder || field.name || null;
    }

    /**
     * Delete record
     */
    async deleteRecord() {
        if (!cmaGetRecordId()) return;

        const confirmed = await libConfirm('Weet je zeker dat je dit record wilt verwijderen?', {
            title: 'Verwijderen',
            confirmText: 'Verwijderen',
            cancelText: 'Niet verwijderen',
            type: 'danger'
        });

        if (!confirmed) {
            return;
        }

        this.showLoading();

        try {
            const response = await fetch(`/cma/form_api.php?action=delete&${this.getFormParam()}&id=${cmaGetRecordId()}`);
            if (!response.ok) {
                throw new Error(`[deleteRecord] HTTP ${response.status} ${response.statusText}`);
            }

            const result = await response.json();

            if (result.success) {
                const deletedRecordId = cmaGetRecordId();
                cmaSetRecordId(null);
                this.setDirty(false);  // Clear dirty state before closing

                // Clear last record ID to prevent loading deleted record on next list reload
                this.clearLastRecordId();

                // If in popup, close and refresh parent
                if (this.isInPopup()) {
                    this.closeForm(deletedRecordId, true);  // Pass deleted=true
                    return;
                }

                // Not in popup - update local UI
                cmaSetRecordId(null);  // Clear record ID
                this.clearForm();  // Clear form fields to avoid stale data
                this.hideDetailPanel();
                this.updateUrl();  // Remove ID from URL
                document.body.classList.remove('is-creating');  // Ensure creating state is cleared
                this.updateFormLayoutState({ isCreating: false, hasRecord: false });
                this.showSuccess(result.message || 'Record verwijderd');

                // Remove deleted row from list (preserves search/filter state)
                // cmaLog.log('deleteRecord: removing row from list for id', deletedRecordId);
                this.removeRowFromList(deletedRecordId);
            } else {
                this.showError(result.error || 'Verwijderen mislukt');
            }
        } catch (error) {
            cmaLog.error('Delete error:', error);
            this.showError('Netwerkfout bij verwijderen');
        } finally {
            this.hideLoading();
        }
    }

    /**
     * Copy record (load as new)
     */
    copyRecord() {
        if (!cmaGetRecordId()) return;

        // Keep data but clear ID - this creates a new record with copied data
        cmaSetRecordId(null);

        // Clear ID and GUID fields to prevent duplicate key errors
        const fieldsToEmpty = ['ID', 'GUID', 'guid', 'Guid', 'guid2', 'Guid2', 'GUID2', 'UniqueID', 'uniqueid', 'UUID', 'uuid'];
        fieldsToEmpty.forEach(name => {
            const field = this.mainForm?.querySelector(`[name="${name}"]`);
            if (field) {
                field.value = '';
            }
        });

        this.updateStatus('Gekopieerde gegevens toevoegen');
        document.body.classList.remove('has-record');
        document.body.classList.add('is-creating');
        this.setDirty(true);
    }

    /**
     * Preview record
     */
    previewRecord() {
        const currentRecordIdForPreview = cmaGetRecordId();
        if (!currentRecordIdForPreview || !this.config.previewUrl) return;

        let url = this.config.previewUrl;
        url = url.replace(/\[ID\]/gi, currentRecordIdForPreview);

        // Handle other placeholders
        const codeField = this.mainForm.querySelector('[name="code"]');
        if (codeField && url.includes('[code]')) {
            url = url.replace(/\[code\]/gi, codeField.value);
        }

        const guidField = this.mainForm.querySelector('[name="guid"]');
        if (guidField && url.includes('[guid]')) {
            url = url.replace(/\[guid\]/gi, guidField.value);
        }

        window.open(url, '_blank');
    }

    /**
     * Update extra button URLs with placeholders replaced by actual record IDs
     * Called after loading a record to enable buttons that require a record ID
     * Uses data attributes on .form-layout for ID/GUID values (data-driven approach)
     */
    updateExtraButtonUrls() {
        const extraButtons = document.querySelectorAll('.extra-button');

        // Get values from .form-layout data attributes (data-driven, no globals)
        const formLayout = document.querySelector('.form-layout');
        const recordId = formLayout?.dataset.recordId || cmaGetRecordId() || '';
        const recordGuid = formLayout?.dataset.recordGuid || '';
        const recordGuid2 = formLayout?.dataset.recordGuid2 || '';

        extraButtons.forEach(btn => {
            const link = btn.querySelector('a[data-url-template]');
            if (!link) return;

            let url = link.dataset.urlTemplate || '';

            // Replace [id] placeholder
            if (recordId) {
                url = url.replace(/\[id\]/gi, recordId);
            }

            // Replace [guid] placeholder
            if (recordGuid) {
                url = url.replace(/\[guid\]/gi, recordGuid);
            }

            // Replace [guid2] placeholder
            if (recordGuid2) {
                url = url.replace(/\[guid2\]/gi, recordGuid2);
            }

            // Replace [domein] placeholder with current domain (without https://)
            url = url.replace(/\[domein\]/gi, window.location.hostname);

            // Match protocol to current page (avoid https on localhost/IP)
            if (window.location.protocol === 'http:') {
                url = url.replace(/^https:\/\//i, 'http://');
            }

            // Update the data-url attribute with resolved URL
            link.dataset.url = url;
        });
    }

    /**
     * Reset extra button URLs to their templates (when clearing record)
     * Also clears the data attributes on .form-layout
     */
    resetExtraButtonUrls() {
        const extraButtons = document.querySelectorAll('.extra-button');

        extraButtons.forEach(btn => {
            const link = btn.querySelector('a[data-url-template]');
            if (!link) return;

            // Reset to template URL
            link.dataset.url = link.dataset.urlTemplate || '';
        });

        // Clear data attributes on .form-layout
        const formLayout = document.querySelector('.form-layout');
        if (formLayout) {
            delete formLayout.dataset.recordId;
            delete formLayout.dataset.recordGuid;
            delete formLayout.dataset.recordGuid2;
        }
    }

    /**
     * Handle extra toolbar button click
     */
    handleExtraButtonClick(url, title, openInNewWindow) {
        if (!url) return;

        // Get record ID from form layout data attribute
        const formLayout = document.querySelector('.form-layout');
        const recordId = formLayout ? (formLayout.dataset.recordId || '') : '';
        const recordGuid = formLayout ? (formLayout.dataset.recordGuid || '') : '';
        const recordGuid2 = formLayout ? (formLayout.dataset.recordGuid2 || '') : '';

        // Also try cmaGetRecordId() as fallback
        const fallbackId = (typeof cmaGetRecordId === 'function') ? cmaGetRecordId() : '';
        const id = recordId || fallbackId;

        // Try to resolve placeholders using current record data
        if (/\[(id|guid|guid2)\]/i.test(url)) {
            // cmaLog.log('handleExtraButtonClick: url=', url, 'recordId=', recordId, 'fallbackId=', fallbackId, 'id=', id);

            if (!id) {
                this.showError('Selecteer eerst een record');
                return;
            }

            // Replace placeholders
            url = url.replace(/\[id\]/gi, id);
            url = url.replace(/\[guid\]/gi, recordGuid);
            url = url.replace(/\[guid2\]/gi, recordGuid2);
        }

        // Replace [domein] placeholder with current domain (without https://)
        url = url.replace(/\[domein\]/gi, window.location.hostname);

        // Match protocol to current page (avoid https on localhost/IP)
        if (window.location.protocol === 'http:') {
            url = url.replace(/^https:\/\//i, 'http://');
        }

        // Handle javascript: URLs - execute as code
        if (url.startsWith('javascript:')) {
            try {
                const code = url.substring(11); // Remove 'javascript:' prefix
                // cmaLog.log('handleExtraButtonClick: executing JS:', code);
                // Create function with recordId in scope
                const fn = new Function('recordId', 'guid', 'guid2', code);
                fn(id, recordGuid, recordGuid2);
            } catch (e) {
                cmaLog.error('handleExtraButtonClick JS error:', e);
                this.showError('Fout bij uitvoeren actie: ' + e.message);
            }
            return;
        }

        // Open URL in new tab or popup overlay
        if (openInNewWindow) {
            window.open(url, '_blank');
        } else if (typeof lib_OpenWindowCentered === 'function') {
            lib_OpenWindowCentered(url, 'extra_action', 900, 700, title || 'Extra');
        } else {
            window.open(url, '_blank');
        }
    }

    // =========================================================================
    // Subform Operations
    // =========================================================================

    /**
     * Get or create a subform pane element
     * Creates the pane div structure on demand if it doesn't exist
     * @param {number} index - Tab index
     * @param {string} subformId - Subform ID from tab data attribute
     * @returns {HTMLElement} The pane element
     */
    getOrCreateSubformPane(index, subformId) {
        let pane = document.getElementById('subform' + index);
        if (!pane) {
            const container = document.getElementById('subformContent');
            if (!container) return null;

            pane = document.createElement('div');
            pane.className = 'tab-pane';
            pane.id = 'subform' + index;
            pane.dataset.subformId = subformId;
            pane.style.display = 'none';

            const toolbar = document.createElement('div');
            toolbar.className = 'toolbar';
            toolbar.id = 'subformToolbar' + index;
            pane.appendChild(toolbar);

            const list = document.createElement('div');
            list.className = 'subform-list';
            list.id = 'subformList' + index;
            pane.appendChild(list);

            container.appendChild(pane);
        }
        return pane;
    }

    /**
     * Update subform tab count badge
     * @param {number} index - Tab index
     * @param {number|string} count - Count to display (or '!' for error, '?' for unknown)
     */
    setSubformCount(index, count) {
        const tabsComponent = document.getElementById('subformTabs');
        if (tabsComponent && typeof tabsComponent.setCount === 'function') {
            tabsComponent.setCount(index, count);
        }
    }

    /**
     * Get the name/label of a subform tab by index
     * @param {number} index - Tab index
     * @returns {string} Tab name or fallback like "Subform 3"
     */
    getSubformName(index) {
        const tabsComponent = document.getElementById('subformTabs');
        if (tabsComponent) {
            const tabs = tabsComponent.tabs;
            if (tabs && tabs[index]) {
                return tabs[index].label || tabs[index].id || `Subform ${index + 1}`;
            }
        }
        return `Subform ${index + 1}`;
    }

    /**
     * Log a subform error with styled console output
     * @param {number} index - Tab index
     * @param {string} errorHtml - Error message (may contain HTML)
     */
    logSubformError(index, errorHtml) {
        const name = this.getSubformName(index);

        // Extract key info from HTML error message
        let fieldName = '';
        let tableName = '';
        const fieldMatch = errorHtml.match(/<code>([^<]+)<\/code>/);
        if (fieldMatch) fieldName = fieldMatch[1];
        const tableMatch = errorHtml.match(/Tabel:<\/b>\s*<code>([^<]+)<\/code>/i);
        if (tableMatch) tableName = tableMatch[1];

        // Styled console output
        const styles = {
            title: 'color: #d32f2f; font-weight: bold; font-size: var(--font-size);',
            label: 'color: #666; font-weight: normal;',
            value: 'color: #1976d2; font-weight: bold;',
            field: 'color: #d32f2f; font-weight: bold; background: #ffebee; padding: 2px 6px; border-radius: 3px;',
            table: 'color: #7b1fa2; font-weight: bold;'
        };

        if (fieldName && tableName) {
            cmaLog.error(
                '%c⚠ Subform fout: %c%s%c\n  Veld: %c%s%c niet gevonden in tabel %c%s',
                styles.title,
                styles.value, name,
                styles.label,
                styles.field, fieldName,
                styles.label,
                styles.table, tableName
            );
        } else {
            // Fallback: strip HTML tags for plain text output
            const plainError = errorHtml.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
            cmaLog.error(
                '%c⚠ Subform fout: %c%s%c\n  %s',
                styles.title,
                styles.value, name,
                styles.label,
                plainError
            );
        }
    }

    /**
     * Load all subforms for current record - smart loading:
     * 1. Load visible (first) tab immediately
     * 2. Batch load all remaining tabs in a single request
     */
    async loadSubforms(parentId) {
        const subformSection = document.getElementById('subformSection');
        if (!subformSection) {
            // This is normal for forms without subforms - not an error
            // cmaLog.log('loadSubforms: no subformSection element found');
            return;
        }

        // Add class to body so CSS controls visibility via !important rule
        // This ensures consistent display: flex for proper flex layout
        document.body.classList.add('has-subform');

        // Get tabs from cma-tabs component
        const tabsComponent = document.getElementById('subformTabs');
        if (!tabsComponent) {
            // cmaLog.log('loadSubforms: no subformTabs element found');
            return;
        }

        // Wait for custom element to be upgraded and initialized
        // This handles race condition where loadSubforms is called before connectedCallback runs
        // cmaLog.log('loadSubforms: checking cma-tabs state, tagName:', tabsComponent.tagName, 'constructor:', tabsComponent.constructor.name);

        // Wait for custom element definition if not yet defined
        if (typeof customElements !== 'undefined') {
            const isDefined = customElements.get('cma-tabs');
            // cmaLog.log('loadSubforms: cma-tabs isDefined:', !!isDefined);
            if (!isDefined) {
                // cmaLog.log('loadSubforms: waiting for cma-tabs to be defined...');
                await customElements.whenDefined('cma-tabs');
                // cmaLog.log('loadSubforms: cma-tabs now defined');
            }
            // Force upgrade the element if needed
            if (tabsComponent.constructor.name === 'HTMLElement') {
                // cmaLog.log('loadSubforms: forcing upgrade of cma-tabs element');
                customElements.upgrade(tabsComponent);
            }
        }

        // Wait for connectedCallback to run
        let waitAttempts = 0;
        while (!tabsComponent._initialized && waitAttempts < 20) {
            // cmaLog.log('loadSubforms: waiting for connectedCallback, attempt:', waitAttempts);
            await new Promise(resolve => setTimeout(resolve, 50));
            waitAttempts++;
        }

        // Final fallback: manually initialize if still not done
        if (!tabsComponent._initialized && typeof tabsComponent._parseTabItems === 'function') {
            // cmaLog.log('loadSubforms: manually initializing cma-tabs after wait');
            tabsComponent._parseTabItems();
            tabsComponent._render();
            tabsComponent._setupResponsive();
            tabsComponent._initialized = true;
        }

        // cmaLog.log('loadSubforms: final state - _initialized:', tabsComponent._initialized);

        let tabs = tabsComponent.tabs;
        if (tabs.length === 0) {
            // Retry: component may have initialized before children were parsed
            const retryAttempts = 3;
            for (let retry = 0; retry < retryAttempts && tabs.length === 0; retry++) {
                await new Promise(resolve => setTimeout(resolve, 200));
                // Re-parse children regardless of _initialized state —
                // it may have been set to true before tab-item children were available
                if (typeof tabsComponent._parseTabItems === 'function') {
                    tabsComponent._parseTabItems();
                    if (tabsComponent._tabs && tabsComponent._tabs.length > 0) {
                        tabsComponent._render();
                        tabsComponent._setupResponsive();
                    }
                }
                tabs = tabsComponent.tabs;
            }
            if (tabs.length === 0) {
                console.warn('[SUBFORM_TRACE] EMPTY TABS after retries - subformTabs component has 0 tabs.',
                    'tabsComponent._initialized:', tabsComponent._initialized,
                    'innerHTML length:', tabsComponent.innerHTML.length);
                subformSection.innerHTML = '<lib-message type="warning" style="margin:10px;">Subformulieren niet geladen (geen tabs gevonden)</lib-message>';
                return;
            }
        }

        // Activate first tab visually and create its pane
        const firstTab = tabs[0];
        this.getOrCreateSubformPane(0, firstTab.id);
        this.activateSubformTab(0, false, firstTab.id); // false = don't reload data yet

        // Step 1: Load first (visible) tab — use prefetched data if available
        const prefetchedFirst = this._prefetchedFirstSubform;
        this._prefetchedFirstSubform = null; // consume it

        if (prefetchedFirst && prefetchedFirst.success) {
            // cmaLog.log('loadSubforms: using prefetched first subform data');
            const pane = document.getElementById('subform0');
            if (pane) {
                pane.classList.add('loading');
                pane.classList.remove('loaded');
                const listEl = pane.querySelector('.subform-list');
                if (listEl) {
                    this.renderSubformList(listEl, prefetchedFirst);
                }
                const count = prefetchedFirst.count || prefetchedFirst.total || (prefetchedFirst.items ? prefetchedFirst.items.length : 0);
                this.setSubformCount(0, count);
                this.renderSubformToolbar(pane, 0, prefetchedFirst);
                pane.classList.remove('loading');
                pane.classList.add('loaded');
            }
        } else {
            // Fallback: load first tab via separate request
            await this.loadSubformDataAndCount(0, firstTab.id, parentId);
        }

        // Step 2: Batch load remaining tabs in a single request
        if (tabs.length > 1) {
            const remainingIndices = [];
            tabs.forEach((tab, index) => {
                if (index > 0) {
                    remainingIndices.push(index);
                    // Create pane on demand and show loading state
                    const pane = this.getOrCreateSubformPane(index, tab.id);
                    if (pane) {
                        const listEl = pane.querySelector('.subform-list');
                        if (listEl) {
                            listEl.innerHTML = '<div class="list-loading">...</div>';
                        }
                    }
                }
            });

            // Batch request for all remaining subforms
            await this.loadSubformsBatch(parentId, remainingIndices);
        }

    }

    /**
     * Batch load multiple subforms in a single request
     */
    async loadSubformsBatch(parentId, indices) {
        if (indices.length === 0) return;

        // Set loading state on all panes
        for (const index of indices) {
            const pane = document.getElementById('subform' + index);
            if (pane) {
                pane.classList.add('loading');
                pane.classList.remove('loaded');
            }
        }

        const url = `/cma/form_api.php?action=subforms&${this.getFormParam()}&ParentID=${parentId}&indices=${indices.join(',')}`;
        const requestId = window.CMA?.requestTracker?.start(url, 'GET', 'loadSubformsBatch') || null;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                const error = `HTTP ${response.status} ${response.statusText}`;
                if (requestId) window.CMA.requestTracker.end(requestId, false, { httpStatus: response.status, error: error });
                throw new Error(`[loadSubformsBatch] ${error}`);
            }
            const data = await response.json();

            // Check for login required
            if (this.checkRequireLogin(data)) {
                return;
            }

            if (data.success && data.subforms) {
                // Track successful completion
                if (requestId) {
                    window.CMA.requestTracker.end(requestId, true, {
                        httpStatus: response.status,
                        responseSize: JSON.stringify(data).length
                    });
                }

                // Process each subform result
                for (const [indexStr, result] of Object.entries(data.subforms)) {
                    const index = parseInt(indexStr);
                    const pane = document.getElementById('subform' + index);
                    if (!pane) continue;

                    const listEl = pane.querySelector('.subform-list');
                    if (!listEl) continue;

                    if (result.success) {
                        this.renderSubformList(listEl, result);

                        // Update count badge via cma-tabs component
                        const count = result.count || result.total || (result.items ? result.items.length : 0);
                        this.setSubformCount(index, count);

                        // Populate subform toolbar (was missing in batch load!)
                        this.renderSubformToolbar(pane, index, result);
                    } else {
                        // Display error with proper error styling
                        const errorMsg = result.error || 'Laden mislukt';
                        // Don't escape if it contains HTML (has <div or <details tags)
                        const hasHtml = /<div\s|<details\s|<span\s|<strong\s/i.test(errorMsg);
                        const displayMsg = hasHtml ? errorMsg : this.escapeHtml(errorMsg);
                        listEl.innerHTML = `<lib-message type="error" style="margin: 10px;">${displayMsg}</lib-message>`;
                        this.setSubformCount(index, '!');
                        this.logSubformError(index, errorMsg);
                    }

                    // Remove loading state for this pane
                    pane.classList.remove('loading');
                    pane.classList.add('loaded');
                }
            } else {
                // API returned success:false or no subforms
                if (requestId) {
                    window.CMA.requestTracker.end(requestId, false, {
                        httpStatus: response.status,
                        error: data.error || 'No subforms in response'
                    });
                }
                cmaLog.error('[SUBFORM_TRACE] Batch response not successful:', data.error || 'No subforms');
            }
        } catch (error) {
            cmaLog.error('[SUBFORM_TRACE] Batch request FAILED:', error.message || error);
            if (requestId) {
                window.CMA.requestTracker.end(requestId, false, { error: error.message || String(error) });
            }
            cmaLog.error('Batch subform load error:', error);
            // Show error in each pane and remove badge loading state
            for (const index of indices) {
                const pane = document.getElementById('subform' + index);
                if (pane) {
                    const listEl = pane.querySelector('.subform-list');
                    if (listEl) {
                        // Display network error with proper error styling
                        listEl.innerHTML = '<lib-message type="error" style="margin: 10px;">Netwerkfout bij laden subformulier</lib-message>';
                    }
                    pane.classList.remove('loading');
                    pane.classList.add('loaded');
                }
                this.setSubformCount(index, '!');
            }
        } finally {
            // Ensure all panes have loading state removed even if response was malformed
            for (const index of indices) {
                const pane = document.getElementById('subform' + index);
                if (pane && pane.classList.contains('loading')) {
                    pane.classList.remove('loading');
                    pane.classList.add('loaded');
                }
            }
        }
    }

    /**
     * Load subform data and update count badge
     */
    async loadSubformDataAndCount(index, subformId, parentId) {
        const pane = document.getElementById('subform' + index);
        if (!pane) {
            console.warn('[SUBFORM_TRACE] loadSubformDataAndCount: pane not found for index', index);
            return;
        }

        // Add loading state (fade effect)
        pane.classList.add('loading');
        pane.classList.remove('loaded');

        const listEl = pane.querySelector('.subform-list');
        if (listEl) {
            listEl.innerHTML = '<div class="list-loading">...</div>';
        }

        const url = `/cma/form_api.php?action=subform&${this.getFormParam()}&ParentID=${parentId}&SubformIndex=${index}`;
        const requestId = window.CMA?.requestTracker?.start(url, 'GET', 'loadSubformDataAndCount[' + index + ']') || null;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                const error = `HTTP ${response.status} ${response.statusText}`;
                if (requestId) window.CMA.requestTracker.end(requestId, false, { httpStatus: response.status, error: error });
                throw new Error(`[loadSubform] ${error}`);
            }
            const data = await response.json();

            // Check for login required
            if (this.checkRequireLogin(data)) {
                if (listEl) {
                    listEl.innerHTML = '<div class="list-loading">Sessie verlopen...</div>';
                }
                pane.classList.remove('loading');
                if (requestId) window.CMA.requestTracker.end(requestId, false, { error: 'Session expired' });
                return;
            }

            if (data.success) {
                if (requestId) {
                    window.CMA.requestTracker.end(requestId, true, {
                        httpStatus: response.status,
                        responseSize: JSON.stringify(data).length
                    });
                }

                // Render the subform list
                if (listEl) {
                    this.renderSubformList(listEl, data);
                }

                // Update count badge via cma-tabs component
                const count = data.count || data.total || (data.items ? data.items.length : 0);
                this.setSubformCount(index, count);

                // Populate subform toolbar with add button
                this.renderSubformToolbar(pane, index, data);
            } else {
                const errorMsg = data.error || 'Laden mislukt';
                this.logSubformError(index, errorMsg);
                if (requestId) window.CMA.requestTracker.end(requestId, false, { httpStatus: response.status, error: errorMsg });
                if (listEl) {
                    // Display error with proper error styling
                    // Don't escape if it contains HTML (has <div or <details tags)
                    const hasHtml = /<div\s|<details\s|<span\s|<strong\s/i.test(errorMsg);
                    const displayMsg = hasHtml ? errorMsg : this.escapeHtml(errorMsg);
                    listEl.innerHTML = `<lib-message type="error" style="margin: 10px;">${displayMsg}</lib-message>`;
                }
                this.setSubformCount(index, '!');
            }
        } catch (error) {
            cmaLog.error('%c⚠ Subform netwerkfout: %c' + this.getSubformName(index), 'color: #d32f2f; font-weight: bold;', 'color: #1976d2;', error.message || error);
            if (requestId) window.CMA.requestTracker.end(requestId, false, { error: error.message || String(error) });
            if (listEl) {
                // Display network error with proper error styling
                listEl.innerHTML = '<lib-message type="error" style="margin: 10px;">Netwerkfout bij laden subformulier</lib-message>';
            }
            this.setSubformCount(index, '!');
        } finally {
            // Remove loading state (unfade)
            pane.classList.remove('loading');
            pane.classList.add('loaded');
        }
    }

    /**
     * Activate a subform tab
     * @param {number} index - Tab index
     * @param {boolean} loadData - Whether to load data (default: true)
     * @param {string} subformId - Subform ID (optional, will be looked up if not provided)
     */
    async activateSubformTab(index, loadData = true, subformId = null) {
        const tabsComponent = document.getElementById('subformTabs');

        // Get subformId from component if not provided
        if (!subformId && tabsComponent) {
            const tabs = tabsComponent.tabs;
            if (tabs[index]) {
                subformId = tabs[index].id;
            }
        }

        // Update tab visual state via component (if called programmatically)
        if (tabsComponent && tabsComponent.selectedIndex !== index) {
            tabsComponent.selectTab(index, false); // false = don't emit event (avoid loop)
        }

        // Ensure pane exists (create on demand)
        if (subformId) {
            this.getOrCreateSubformPane(index, subformId);
        }

        // Show corresponding pane, hide others
        document.querySelectorAll('#subformContent > .tab-pane').forEach(pane => {
            const paneIndex = parseInt(pane.id.replace('subform', ''));
            pane.style.display = paneIndex === index ? 'block' : 'none';
        });

        // Load subform data if requested and not already loaded
        if (loadData) {
            const pane = document.getElementById('subform' + index);
            if (pane && cmaGetRecordId()) {
                // Skip loading if data is already loaded (pane has 'loaded' class)
                if (pane.classList.contains('loaded')) {
                    // cmaLog.log('activateSubformTab: skipping load for tab', index, '- already loaded');
                    return;
                }
                await this.loadSubformData(index, pane);
            }
        }
    }

    /**
     * Load subform data
     */
    async loadSubformData(index, pane) {
        const listEl = pane.querySelector('.subform-list');
        if (!listEl) {
            console.warn('[SUBFORM_TRACE] loadSubformData: listEl not found for index', index);
            return;
        }

        listEl.innerHTML = '<div class="list-loading">...</div>';

        const url = `/cma/form_api.php?action=subform&${this.getFormParam()}&ParentID=${cmaGetRecordId()}&SubformIndex=${index}`;
        const requestId = window.CMA?.requestTracker?.start(url, 'GET', 'loadSubformData[' + index + ']') || null;

        try {
            const response = await fetch(url);
            if (!response.ok) {
                const error = `HTTP ${response.status} ${response.statusText}`;
                if (requestId) window.CMA.requestTracker.end(requestId, false, { httpStatus: response.status, error: error });
                throw new Error(`[refreshSubform] ${error}`);
            }
            const data = await response.json();

            // Check for login required
            if (this.checkRequireLogin(data)) {
                listEl.innerHTML = '<div class="list-loading">Sessie verlopen...</div>';
                if (requestId) window.CMA.requestTracker.end(requestId, false, { error: 'Session expired' });
                return;
            }

            if (data.success) {
                if (requestId) {
                    window.CMA.requestTracker.end(requestId, true, {
                        httpStatus: response.status,
                        responseSize: JSON.stringify(data).length
                    });
                }

                this.renderSubformList(listEl, data);

                // Update count badge via cma-tabs component
                const count = data.count || data.total || 0;
                this.setSubformCount(index, count);

                // Populate subform toolbar
                this.renderSubformToolbar(pane, index, data);
            } else {
                const errorMsg = data.error || 'Laden mislukt';
                this.logSubformError(index, errorMsg);
                if (requestId) window.CMA.requestTracker.end(requestId, false, { httpStatus: response.status, error: errorMsg });

                // Check if this is a fixable error and user is developer
                if (data.fixable && data.fixType === 'missingParentField' && window.CMA_IS_DEVELOPER) {
                    this.showParentFieldFixDialog(listEl, data, index);
                } else {
                    // Display error with proper error styling
                    const hasHtml = /<div\s|<details\s|<span\s|<strong\s/i.test(errorMsg);
                    const displayMsg = hasHtml ? errorMsg : this.escapeHtml(errorMsg);
                    listEl.innerHTML = `<lib-message type="error" style="margin: 10px;">${displayMsg}</lib-message>`;
                }
            }
        } catch (error) {
            cmaLog.error('%c⚠ Subform netwerkfout: %c' + this.getSubformName(index), 'color: #d32f2f; font-weight: bold;', 'color: #1976d2;', error.message || error);
            if (requestId) window.CMA.requestTracker.end(requestId, false, { error: error.message || String(error) });
            listEl.innerHTML = '<div class="list-loading">Netwerkfout</div>';
        }
    }

    /**
     * Show dialog to fix missing parentField in subform configuration
     * This allows developers to select the correct FK field and update the JSON definition
     */
    showParentFieldFixDialog(listEl, errorData, subformIndex) {
        const candidates = errorData.candidateFields || [];
        const subformName = errorData.subformName || 'Subform';
        const parentFormName = errorData.parentFormName || '';
        const jsonFormName = errorData.jsonFormName || '';

        let html = '<div class="parentfield-fix-dialog">';
        html += '<div class="fix-error-message">' + (errorData.error || 'parentField ontbreekt') + '</div>';

        if (candidates.length > 0) {
            html += '<div class="fix-description">Selecteer het veld dat de koppeling met het bovenliggende record bevat:</div>';
            html += '<div class="fix-field-select">';
            html += '<select id="parentFieldSelect" class="form-control">';
            html += '<option value="">-- Selecteer veld --</option>';
            for (const field of candidates) {
                const label = field.caption || field.name;
                html += `<option value="${field.name}">${label} (${field.name})</option>`;
            }
            html += '</select>';
            html += '</div>';
            html += '<div class="fix-actions">';
            html += '<button type="button" class="btn btn-primary" id="fixParentFieldBtn">Opslaan in JSON</button>';
            html += '</div>';
        } else {
            html += '<div class="fix-description">Geen geschikte FK velden gevonden in subform.</div>';
        }
        html += '</div>';

        listEl.innerHTML = html;

        // Bind save button
        const saveBtn = listEl.querySelector('#fixParentFieldBtn');
        const selectEl = listEl.querySelector('#parentFieldSelect');
        const self = this;

        if (saveBtn && selectEl) {
            saveBtn.addEventListener('click', async function() {
                const selectedField = selectEl.value;
                if (!selectedField) {
                    lib_Alert('Selecteer eerst een veld');
                    return;
                }

                saveBtn.disabled = true;
                saveBtn.textContent = 'Opslaan...';

                try {
                    const formData = new FormData();
                    formData.append('action', 'updateSubformParentField');
                    formData.append('parentFormName', parentFormName);
                    formData.append('subformIndex', errorData.subformIndex);
                    formData.append('parentField', selectedField);

                    const response = await fetch('/cma/api/form_definition.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    if (result.success) {
                        lib_Alert('parentField opgeslagen. Pagina wordt herladen...');
                        // Reload page to apply the fix
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        lib_Alert('Fout: ' + (result.error || 'Opslaan mislukt'));
                        saveBtn.disabled = false;
                        saveBtn.textContent = 'Opslaan in JSON';
                    }
                } catch (error) {
                    cmaLog.error('Fix parentField error:', error);
                    lib_Alert('Netwerkfout bij opslaan');
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Opslaan in JSON';
                }
            });
        }
    }

    /**
     * Render subform toolbar
     */
    renderSubformToolbar(pane, index, data) {
        const toolbarEl = pane.querySelector('.toolbar');
        if (!toolbarEl) return;

        // Get singular form name for tooltip
        const subformConfig = this.config.subforms?.[index] || {};
        const singularName = (subformConfig.titleSingular || subformConfig.title || 'record').toLowerCase();
        const addTooltip = 'Voeg ' + singularName + ' toe';

        let html = '';

        // Always show add button - disable if not allowed
        const disabledClass = data.canAdd ? '' : ' disabled';
        const disabledAttr = data.canAdd ? '' : ' aria-disabled="true"';
        html += '<span class="tb-btn' + disabledClass + '" title="' + addTooltip + '">';
        html += '<a href="#" data-action="subform-add" data-subform-id="' + (data.subformId || '') + '" data-subform-index="' + index + '"' + disabledAttr + '>';
        html += '<span class="lnr lnr-file-add"></span>';
        html += '<span class="btn-text">Toevoegen</span>';
        html += '</a></span>';

        toolbarEl.innerHTML = html;

        // Store canAdd state on toolbar for the no-data message to check
        toolbarEl.dataset.canAdd = data.canAdd ? 'true' : 'false';

        // Bind subform add click event (only if enabled)
        if (data.canAdd) {
            toolbarEl.querySelectorAll('[data-action="subform-add"]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.addSubformRecord(btn.dataset.subformId, btn.dataset.subformIndex);
                });
            });
        }
    }

    /**
     * Add new subform record - uses unified openPopup function
     */
    addSubformRecord(subformId, subformIndex) {
        const subformConfig = this.config.subforms?.[subformIndex] || {};
        // Use linkField (JSON form schema) or parentField (legacy) for parent relationship
        const parentField = subformConfig.parentField || subformConfig.linkField || '';
        // Use subform title with action suffix
        const formName = subformConfig.titleSingular || subformConfig.title || subformId;
        const title = formName + ' toevoegen';

        const self = this;
        this.openPopup({
            formId: subformId,
            recordId: null, // null = new record
            parentId: cmaGetRecordId(),
            parentField: parentField,
            title: title,
            windowName: 'sub_form_details_' + subformId,
            cascadeOffset: true,
            onClose: function() {
                // Reload current record to refresh subforms
                if (cmaGetRecordId()) {
                    self.loadRecord(cmaGetRecordId());
                }
            }
        });
    }

    /**
     * Render subform list - uses server-generated table HTML with sorting/filtering
     */
    renderSubformList(listEl, data) {
        // Clean up old filter collection before replacing table HTML
        // This ensures fresh filters are created with new data after CRUD operations
        const oldTable = listEl.querySelector('table.filtering');
        if (oldTable && typeof jQuery !== 'undefined') {
            const $oldTable = jQuery(oldTable);
            if ($oldTable.data('excelTableFilter')) {
                $oldTable.removeData('excelTableFilter');
            }
        }

        // New format: server returns HTML directly
        if (data.html) {
            listEl.innerHTML = data.html;

            // Initialize table filtering (includes sorting, dropdown filters, export menu)
            const filterableTable = listEl.querySelector('table.filtering');
            if (filterableTable && typeof filtering_init === 'function') {
                filtering_init(jQuery(filterableTable));
            }

            // Initialize CmaInlineEdit for subform table (same class as main table)
            this.initTableEditor(listEl, data);
            return;
        }

        const columns = data.columns || (data.items && data.items.length > 0
            ? Object.keys(data.items[0]).filter(k => !k.startsWith('_') && k.toLowerCase() !== 'id').slice(0, 4)
            : []);

        if (!data.items || data.items.length === 0) {
            const message = 'Geen gegevens' + (data.canAdd ? ', klik op \'Toevoegen\' om een nieuw record aan te maken' : '');
            if (columns.length > 0) {
                // Render empty table with thead for consistency
                let html = '<lib-table><table class="listtable filtering sorttable" cellpadding="0" cellspacing="0">';
                html += '<thead><tr class="listheader">';
                for (const col of columns) {
                    html += '<th>' + this.escapeHtml(col) + '</th>';
                }
                html += '</tr></thead><tbody></tbody></table></lib-table>';
                html += '<div class="no-data">' + message + '</div>';
                listEl.innerHTML = html;
            } else {
                listEl.innerHTML = '<div class="no-data">' + message + '</div>';
            }
            return;
        }

        let html = '<lib-table><table class="listtable filtering sorttable" cellpadding="0" cellspacing="0">';
        html += '<thead><tr class="listheader">';
        for (const col of columns) {
            html += '<th>' + this.escapeHtml(col) + '</th>';
        }
        html += '</tr></thead>';

        html += '<tbody>';
        for (const item of data.items) {
            const id = item._id || item.ID || item.id;
            const menuTrigger = '<span class="row-menu-trigger" data-id="' + this.escapeHtml(id) + '">&#8942;</span>';
            html += '<tr class="listrow" data-id="' + this.escapeHtml(id) + '">';
            let isFirst = true;
            for (const col of columns) {
                const prefix = isFirst ? menuTrigger : '';
                isFirst = false;
                html += '<td>' + prefix + this.escapeHtml(item[col] || '') + '</td>';
            }
            html += '</tr>';
        }
        html += '</tbody></table></lib-table>';
        listEl.innerHTML = html;

        // Initialize CmaInlineEdit for subform table
        this.initTableEditor(listEl, data);
    }

    /**
     * Initialize CmaInlineEdit for any table (main or subform)
     * This is the single entry point for table editing - no duplicate code paths
     */
    initTableEditor(listEl, data) {
        if (typeof CmaInlineEdit === 'undefined') {
            cmaLog.error('initTableEditor: CmaInlineEdit not loaded');
            return;
        }

        const table = listEl.querySelector('table.subform-table, table.listtable');
        if (!table) {
            // No table on this page - normal for non-table views
            return;
        }

        // Ensure table has an ID for the selector
        if (!table.id) {
            const identifier = data.subformId || data.subformName || 'table_' + Date.now();
            table.id = 'table_' + identifier.replace(/[^a-zA-Z0-9_-]/g, '_');
        }

        // Clean up existing editor for this table
        if (!this.subformEditors) {
            this.subformEditors = new Map();
        }
        const existingEditor = this.subformEditors.get(table.id);
        if (existingEditor && typeof existingEditor.destroy === 'function') {
            existingEditor.destroy();
        }

        const self = this;
        const config = {
            tableSelector: '#' + table.id,
            formId: 0,
            jsonForm: data.subformId || data.subformName,
            formName: data.subformName || data.subformId || 'Subform',
            formNameSingular: data.subformName || data.subformId || 'Record',
            accessLevel: this.config.accessLevel || 0,
            canAdd: data.canAdd !== false,
            canEdit: data.canEdit !== false,
            canCopy: false,
            canDelete: data.canDelete !== false,
            fields: data.fields || [],
            comboOptions: data.comboOptions || {},
            apiUrl: '/cma/form_api.php',
            isSubform: true,
            parentRecordId: cmaGetRecordId(),
            parentField: data.parentField,
            // Callbacks - same pattern as main table
            onRowClick: (rowId) => {
                self.openSubformRecord(rowId, data);
            },
            onSaveSuccess: (rowId, record) => {
                self.showNotification('Opgeslagen', 'success');
            },
            onDeleteSuccess: (rowId) => {
                self.showNotification('Verwijderd', 'success');
                // Update subform count badge
                const pane = listEl.closest('[id^="subform"]');
                if (pane) {
                    const index = pane.id.replace('subform', '');
                    const currentCount = self.getSubformCount(index);
                    if (currentCount > 0) {
                        self.setSubformCount(index, currentCount - 1);
                    }
                }
            }
        };

        // cmaLog.log('initTableEditor: Initializing CmaInlineEdit for', table.id);
        const editor = new CmaInlineEdit(config);
        this.subformEditors.set(table.id, editor);
    }

    /**
     * Destroy all subform inline editors
     * Called when loading a new record or destroying the controller
     */
    destroySubformEditors() {
        if (!this.subformEditors || this.subformEditors.size === 0) {
            return;
        }
        // cmaLog.log('destroySubformEditors: Destroying', this.subformEditors.size, 'editors');
        this.subformEditors.forEach((editor, tableId) => {
            if (editor && typeof editor.destroy === 'function') {
                try {
                    editor.destroy();
                } catch (e) {
                    cmaLog.warn('Failed to destroy subform editor:', tableId, e);
                }
            }
        });
        this.subformEditors.clear();
    }

    /**
     * Open subform record in popup - uses unified openPopup function
     */
    openSubformRecord(recordId, data) {
        const subformId = data.subformId;
        const parentField = data.parentField || '';
        // Use subformName with action suffix
        const formName = data.subformName || subformId;
        // For subforms, check if parent form allows edit (accessLevel >= 2)
        const canEdit = this.config.accessLevel >= 2;
        const actionSuffix = canEdit ? ' wijzigen' : ' bekijken';
        const title = formName + actionSuffix;

        // cmaLog.log('openSubformRecord: subformId=', subformId, 'recordId=', recordId, 'parentField=', parentField);

        const self = this;
        this.openPopup({
            formId: subformId,
            recordId: recordId,
            parentId: cmaGetRecordId(),
            parentField: parentField,
            title: title,
            windowName: 'sub_form_details_' + subformId,
            cascadeOffset: true,
            onClose: function() {
                // Reload current record to refresh subforms
                if (cmaGetRecordId()) {
                    self.loadRecord(cmaGetRecordId());
                }
            }
        });
    }

    // =========================================================================
    // UI Helpers
    // =========================================================================

    /**
     * Show detail panel - uses CSS classes for visibility
     * CSS rules: body.has-record #detailContent, body.is-creating #detailContent { display: block }
     */
    showDetailPanel() {
        // Visibility is controlled by CSS via body.has-record or body.is-creating classes
        // The classes are managed by loadRecord(), prepareNewRecord(), etc.
        // This method is kept for backwards compatibility but the actual display
        // is determined by CSS based on body classes
    }

    /**
     * Hide detail panel - removes record state classes
     */
    hideDetailPanel() {
        document.body.classList.remove('has-record');
        document.body.classList.remove('is-creating');
        this.updateFormLayoutState({ hasRecord: false, isCreating: false });
    }

    /**
     * Show loading overlay
     */
    showLoading(source = 'unknown') {
        // Clear any previous error when starting a new operation
        this.clearError();
        this.loadingActive = true;
        this.loadingTimer = setTimeout(() => {
            // Only show if still active (not cancelled by hideLoading)
            if (this.loadingActive && this.loadingOverlay) {
                this.loadingOverlay.style.display = 'flex';
            }
        }, this.loadingTimeout);

        // Add spinner to detail toolbar for record loading
        if (source === 'loadRecord' || source === 'loadRecordForCopy') {
            this.showToolbarSpinner();
        }
    }

    /**
     * Hide loading overlay
     */
    hideLoading(source = 'unknown') {
        this.loadingActive = false;
        clearTimeout(this.loadingTimer);
        if (this.loadingOverlay) {
            this.loadingOverlay.style.display = 'none';
        }

        // Remove toolbar spinner
        this.hideToolbarSpinner();
    }

    /**
     * Show spinner in the detail toolbar right section
     */
    showToolbarSpinner() {
        // Find the detail toolbar by ID
        const toolbar = document.getElementById('detailToolbar');
        if (!toolbar) return;

        // Find or create the right section
        let toolbarRight = toolbar.querySelector('.toolbar-right');
        if (!toolbarRight) {
            toolbarRight = document.createElement('div');
            toolbarRight.className = 'toolbar-right';
            toolbar.appendChild(toolbarRight);
        }

        // Don't add if already present
        if (toolbarRight.querySelector('.toolbar-spinner')) return;

        // Create spinner element using lib-loader web component
        const spinner = document.createElement('div');
        spinner.className = 'toolbar-spinner';
        spinner.innerHTML = '<lib-loader active size="small" delay="0"></lib-loader>';

        toolbarRight.appendChild(spinner);
    }

    /**
     * Hide spinner from the detail toolbar
     */
    hideToolbarSpinner() {
        const spinner = document.querySelector('#detailToolbar .toolbar-spinner');
        if (spinner) {
            spinner.remove();
        }
    }

    /**
     * Set dirty state
     */
    setDirty(dirty) {
        cmaSetIsDirty(dirty);

        // Update body and form-layout class for CSS-based button state control
        document.body.classList.toggle('is-dirty', dirty);
        const formLayout = document.querySelector('.form-layout');
        if (formLayout) {
            formLayout.classList.toggle('is-dirty', dirty);
        }

        // Update save button visual state (grayed out when no changes, but still clickable)
        // Note: We only add 'muted' class for visual feedback, NOT 'disabled' which blocks clicks
        const saveBtn = document.querySelector('[data-action="save"]');
        if (saveBtn) {
            const tbBtn = saveBtn.closest('.tb-btn');
            if (tbBtn) {
                tbBtn.classList.toggle('muted', !dirty);
            }
        }

        // Update title dirty indicator without overwriting the page name
        var baseTitle = document.title.replace(/^\*\s*/, '');
        document.title = (dirty ? '* ' : '') + baseTitle;
    }

    /**
     * Check if form has real unsaved changes (not just dirty flag, but actual field changes)
     * This handles cases where isDirty flag is set but values haven't actually changed
     * (e.g., boolean empty → N which are semantically equal)
     * @returns {boolean} True if there are actual unsaved changes
     */
    hasUnsavedChanges() {
        // Quick check: if not dirty, no changes
        if (!cmaGetIsDirty()) return false;

        // If no record is loaded and not in "creating" mode, this is just an empty form
        // - no unsaved changes to worry about
        const isCreating = document.body.classList.contains('is-creating');
        if (!cmaGetRecordId() && !isCreating) {
            return false;
        }

        // Check for actual field changes (handles semantic equivalence)
        const changes = this.getChangedFields();
        return changes.length > 0;
    }

    /**
     * Update status text
     */
    updateStatus(text) {
        if (this.toolbarStatus) {
            this.toolbarStatus.textContent = text;
        }
    }

    /**
     * Get list of changed fields with their labels and values
     * @returns {Array} Array of { label, oldValue, newValue } objects
     */
    getChangedFields() {
        const mainForm = this.mainForm;
        if (!mainForm) return [];

        const changes = [];
        const formData = new FormData(mainForm);

        // Read original values once (getter reads from DOM data attributes)
        const origValues = this.originalValues;

        // Build a map of field labels from the form
        const labelMap = {};
        mainForm.querySelectorAll('label').forEach(label => {
            const forAttr = label.getAttribute('for');
            const text = label.textContent.trim().replace('*', '').trim();
            if (forAttr && text) {
                labelMap[forAttr.toLowerCase()] = text;
            }
        });
        // Also check data-label attributes on fields
        mainForm.querySelectorAll('[data-label]').forEach(field => {
            const name = field.name || field.dataset.field;
            if (name) {
                labelMap[name.toLowerCase()] = field.dataset.label;
            }
        });

        // Helper to check if value represents "false" for boolean fields
        const isBoolFalse = (val) => {
            const s = String(val || '').toLowerCase();
            return s === '' || s === 'n' || s === '0' || s === 'false' || s === 'nee' || s === 'null' || s === 'undefined';
        };
        const isBoolTrue = (val) => {
            const s = String(val || '').toLowerCase();
            return s === 'j' || s === 'y' || s === '1' || s === '-1' || s === 'true' || s === 'ja' || s === 'yes';
        };

        for (const [name, newValue] of formData.entries()) {
            // Skip hidden/system fields, __label fields (display-only), and _changelog fields
            if (name.startsWith('_') || name.endsWith('__label') || name === 'ID' || name === 'FormID') continue;

            const originalValue = origValues[name] ?? origValues[name.toLowerCase()] ?? '';
            const origStr = String(originalValue || '');
            const newStr = String(newValue || '');

            // Skip if values are the same
            if (origStr === newStr) continue;

            // For boolean fields (name starts with b or is checkbox-like value), normalize comparison
            // Empty and 'N' both mean false, so don't flag as change
            const isBoolField = name.startsWith('b') ||
                (isBoolFalse(origStr) && isBoolFalse(newStr)) ||
                (isBoolTrue(origStr) && isBoolTrue(newStr));

            if (isBoolField) {
                // Both are false-ish or both are true-ish = no change
                if ((isBoolFalse(origStr) && isBoolFalse(newStr)) ||
                    (isBoolTrue(origStr) && isBoolTrue(newStr))) {
                    continue;
                }
            }

            const label = labelMap[name.toLowerCase()] || name;
            changes.push({
                field: name,
                label: label,
                oldValue: origStr,
                newValue: newStr
            });
        }

        // Also check checkboxes (they don't appear in FormData when unchecked)
        mainForm.querySelectorAll('input[type="checkbox"], lib-switch').forEach(field => {
            const name = field.name;
            if (!name || name.startsWith('_')) return;

            const isChecked = field.checked;
            const originalValue = origValues[name] ?? origValues[name.toLowerCase()];
            const wasChecked = originalValue === true || originalValue === 'true' || originalValue === '1' || originalValue === 'True' || originalValue === -1;

            if (isChecked !== wasChecked) {
                const label = labelMap[name.toLowerCase()] || name;
                changes.push({
                    field: name,
                    label: label,
                    oldValue: wasChecked ? 'Ja' : 'Nee',
                    newValue: isChecked ? 'Ja' : 'Nee'
                });
            }
        });

        return changes;
    }

    /**
     * Capture current form values as original values for change tracking
     * Should be called after form is fully populated (including combos)
     */
    captureOriginalValues() {
        const mainForm = this.mainForm;
        if (!mainForm) return;

        // Clear any existing original values first
        mainForm.querySelectorAll('[data-original-value]').forEach(field => {
            delete field.dataset.originalValue;
        });

        // Set data-original-value directly on each field (DOM-based state)
        // IMPORTANT: Capture ALL fields including empty ones for proper changelog detection
        const formData = new FormData(mainForm);
        let capturedCount = 0;
        const processedFields = new Set();

        for (const [name, value] of formData.entries()) {
            // Skip underscore-prefixed fields (system/changelog/internal fields)
            if (name.startsWith('_')) continue;
            // Skip __label fields (display-only fields for combo/FK lookups)
            if (name.endsWith('__label')) continue;
            // Skip array fields in FormData iteration (handle separately)
            if (name.endsWith('[]')) continue;

            const field = mainForm.querySelector(`[name="${name}"]`);
            if (field) {
                // Capture value even if empty (important for changelog detection)
                field.dataset.originalValue = value ?? '';
                processedFields.add(name);
                capturedCount++;
            }
        }

        // Capture all text inputs, textareas, selects that weren't in FormData (e.g., disabled fields)
        mainForm.querySelectorAll('input[type="text"], input[type="hidden"], textarea, select').forEach(field => {
            const name = field.name;
            if (!name || processedFields.has(name)) return;
            if (name.startsWith('_') || name.endsWith('__label') || name.endsWith('[]')) return;

            field.dataset.originalValue = field.value ?? '';
            processedFields.add(name);
            capturedCount++;
        });

        // Capture checkboxes (they don't appear in FormData when unchecked)
        mainForm.querySelectorAll('input[type="checkbox"], lib-switch').forEach(field => {
            const name = field.name;
            if (name && !name.startsWith('_')) {
                field.dataset.originalValue = field.checked ? 'true' : 'false';
                capturedCount++;
            }
        });

        // Log capture result for debugging Notificatie issue
        // console.info('[captureOriginalValues] Captured', capturedCount, 'field values');
    }

    /**
     * Format changed fields for display in confirm dialog
     * @param {number} maxItems - Maximum number of changes to show (default 5)
     * @param {number} maxValueLength - Maximum length of value to show (default 50)
     * @returns {string} HTML string with change summary
     */
    formatChangeSummary(maxItems = 5, maxValueLength = 50) {
        const changes = this.getChangedFields();
        if (changes.length === 0) return '';

        const truncate = (str, len) => {
            if (!str || str.length <= len) return str;
            return str.substring(0, len) + '...';
        };

        // Wrap in <details> so changes are hidden by default
        let html = '<details style="margin-top:12px;font-size:var(--font-size-sm);text-align:left;">';
        const countLabel = changes.length === 1 ? '1 wijziging' : changes.length + ' wijzigingen';
        html += '<summary style="cursor:pointer;color:var(--color-primary, #204496);font-weight:500;user-select:none;">Toon ' + countLabel + '</summary>';

        // Table format for changes
        html += '<table style="border-collapse:collapse;width:100%;font-size:var(--font-size-xs);margin-top:8px;">';
        html += '<thead><tr style="background:var(--bg-surface-alt, #f5f5f5);border-bottom:1px solid var(--border-color, #ddd);">';
        html += '<th style="padding:4px 8px;text-align:left;font-weight:500;">Veld</th>';
        html += '<th style="padding:4px 8px;text-align:left;font-weight:500;">Was</th>';
        html += '<th style="padding:4px 8px;text-align:left;font-weight:500;">Wordt</th>';
        html += '</tr></thead><tbody>';

        const showCount = Math.min(changes.length, maxItems);
        for (let i = 0; i < showCount; i++) {
            const change = changes[i];
            const isLongValue = change.newValue.length > maxValueLength || change.oldValue.length > maxValueLength;
            const oldDisplay = isLongValue ? '<em>(gewijzigd)</em>' : (change.oldValue || '<em>leeg</em>');
            const newDisplay = isLongValue ? '<em>(gewijzigd)</em>' : (change.newValue || '<em>leeg</em>');

            html += `<tr style="border-bottom:1px solid var(--border-color-light, #eee);">`;
            html += `<td style="padding:4px 8px;font-weight:500;">${change.label}</td>`;
            html += `<td style="padding:4px 8px;color:var(--color-danger, #c00);">${truncate(oldDisplay, maxValueLength)}</td>`;
            html += `<td style="padding:4px 8px;color:var(--color-success, #060);">${truncate(newDisplay, maxValueLength)}</td>`;
            html += `</tr>`;
        }

        html += '</tbody></table>';

        if (changes.length > maxItems) {
            html += `<div style="margin-top:6px;font-style:italic;color:var(--text-muted, #666);">... en ${changes.length - maxItems} meer ...</div>`;
        }

        html += '</details>';
        return html;
    }

    /**
     * Update form-layout state classes (is-creating, has-record, is-dirty)
     * @param {Object} state - State flags: { isCreating, hasRecord, isDirty }
     */
    updateFormLayoutState(state) {
        const formLayout = document.querySelector('.form-layout');
        if (!formLayout) return;

        if (state.isCreating !== undefined) {
            formLayout.classList.toggle('is-creating', state.isCreating);
        }
        if (state.hasRecord !== undefined) {
            formLayout.classList.toggle('has-record', state.hasRecord);
        }
        if (state.isDirty !== undefined) {
            formLayout.classList.toggle('is-dirty', state.isDirty);
        }
    }

    /**
     * Update URL without reload
     * Updates the main window URL (either directly or via parent if in iframe)
     * so refresh preserves the current record selection.
     *
     * Uses clean URL format: /cma/form/formname/recordId
     */
    updateUrl() {
        const currentRecordIdForUrl = cmaGetRecordId();

        // Determine which window to update (parent if in iframe, current otherwise)
        let targetWindow = window;
        let inSidepanel = false;

        try {
            // Check if we're in an iframe
            if (window !== window.top && window.frameElement) {
                inSidepanel = !!window.frameElement.closest('.lib_sidepanel_container');
                if (!inSidepanel && window.parent) {
                    targetWindow = window.parent;
                }
            }
        } catch (e) {
            // Cross-origin - use current window
            cmaLog.warn('[updateUrl] Cross-origin check failed:', e.message);
        }

        // Skip URL update if we're in a sidepanel (those use popupStack)
        if (inSidepanel) {
            // cmaLog.log('[updateUrl] Skipping - in sidepanel');
            return;
        }

        try {
            // Use clean URL format via CMA.url manager
            if (targetWindow.CMA && targetWindow.CMA.url) {
                const formName = this.jsonForm || this.getFormName();
                if (formName) {
                    // Preserve any existing subform state from the current URL
                    // (e.g. when loading /form/rooster/67/rooster_aanwezigheid/4849 from scratch)
                    const currentState = targetWindow.CMA.url.parse();
                    const state = {
                        form: formName,
                        recordId: currentRecordIdForUrl || null,
                        isNew: false
                    };
                    // Keep subform info if the main form matches
                    if (currentState.subform && currentState.form === formName) {
                        state.subform = currentState.subform;
                        state.subformId = currentState.subformId;
                        state.isSubformNew = currentState.isSubformNew;
                        state.subsubform = currentState.subsubform;
                        state.subsubformId = currentState.subsubformId;
                        state.isSubsubformNew = currentState.isSubsubformNew;
                    }
                    const newUrl = targetWindow.CMA.url.build(state);
                    targetWindow.history.replaceState({ cmaState: state }, '', newUrl);
                    // cmaLog.log('[updateUrl] Updated URL:', newUrl, 'form:', formName, 'recordId:', currentRecordIdForUrl);
                }
            } else {
                cmaLog.warn('[updateUrl] CMA.url not available');
            }
        } catch (e) {
            cmaLog.warn('[updateUrl] Could not update URL:', e.message);
        }
    }

    /**
     * Check if API response requires login and handle redirect
     * @param {object} data - API response data
     * @returns {boolean} - true if login is required (redirect happens)
     */
    checkRequireLogin(data) {
        if (data && data.requireLogin) {
            this.showError('Sessie verlopen. Opnieuw inloggen...');
            setTimeout(() => {
                window.top.location = 'default.php?forcelogin=J';
            }, 1500);
            return true;
        }
        return false;
    }

    /**
     * Show error message persistently in the detail panel
     * @param {string} message - Error message to display
     * @param {boolean|object} options - If boolean: alsoNotify. If object: { alsoNotify, details }
     */
    showError(message, options = false) {
        // Handle legacy boolean parameter for backwards compatibility
        const opts = typeof options === 'object' ? options : { alsoNotify: options };
        let alsoNotify = opts.alsoNotify || false;
        const details = opts.details || '';
        const source = opts.source || 'FormController';

        cmaLog.error('[CMA Error]', message);
        if (details) {
            cmaLog.error('[CMA Error Details]', details);
        }

        // Report to dev error panel
        if (typeof CmaErrorHandler !== 'undefined') {
            CmaErrorHandler.reportServerError(source, message, {
                url: window.location.href,
                debug: details || null,
                form: this.jsonForm
            });
        }

        // Show persistent error in detail panel content area
        const detailContent = this.detailPanel?.querySelector('.detail-content');
        if (detailContent) {
            // Remove any existing error
            const existingError = detailContent.querySelector('lib-message.persistent-error, .persistent-error');
            if (existingError) existingError.remove();

            // Use lib-message web component if available
            if (customElements.get('lib-message')) {
                const errorEl = document.createElement('lib-message');
                errorEl.className = 'persistent-error';
                errorEl.setAttribute('type', 'error');
                errorEl.setAttribute('closable', '');
                // Only show details to admins/developers
                if (details && window.CMA?.formConfig?.showDetails) {
                    errorEl.setAttribute('details', details);
                }
                // If message contains HTML (like <br> from field captions), use innerHTML
                // Otherwise use textContent for safety
                if (message.includes('<br>') || message.includes('<br/>') || message.includes('<br />') || message.includes('<')) {
                    errorEl.innerHTML = message;
                } else {
                    errorEl.textContent = message;
                }
                detailContent.insertBefore(errorEl, detailContent.firstChild);
            } else {
                // Fallback to legacy HTML
                const errorDiv = document.createElement('div');
                errorDiv.className = 'persistent-error';
                let detailsHtml = '';
                if (details && window.CMA?.formConfig?.showDetails) {
                    detailsHtml = `<details class="error-details"><summary>Details</summary><pre>${this.escapeHtml(details)}</pre></details>`;
                }
                errorDiv.innerHTML = `
                    <div class="error-icon">⚠</div>
                    <div class="error-message">${this.escapeHtml(message)}${detailsHtml}</div>
                    <button class="error-close" onclick="this.parentElement.remove()">×</button>
                `;
                detailContent.insertBefore(errorDiv, detailContent.firstChild);
            }
        } else {
            // Fallback to top notification if no detail panel
            alsoNotify = true;
        }

        if (alsoNotify) {
            this.showTopNotification(message, 'error', 0); // 0 = persistent
        }
    }

    /**
     * Clear any persistent error display
     */
    clearError() {
        const errorDiv = this.detailPanel?.querySelector('lib-message.persistent-error, .persistent-error');
        if (errorDiv) {
            errorDiv.remove();
        }
    }

    /**
     * Show success message as top notification
     */
    showSuccess(message) {
        this.showTopNotification(message, 'success');
    }

    /**
     * Show a top notification bar using lib-toaster component
     * @param {string} message - Message to display
     * @param {string} type - 'success', 'error', 'warning', or 'info'
     * @param {number} duration - Duration in ms (0 for persistent)
     */
    showTopNotification(message, type = 'info', duration = 3000) {
        // Use libToast from lib-toaster.js
        // duration=0 means persistent (no auto-dismiss)
        if (typeof libToast !== 'undefined' && libToast[type]) {
            libToast[type](message, duration === 0 ? 0 : duration);
        } else if (typeof libToast !== 'undefined') {
            libToast.info(message, duration === 0 ? 0 : duration);
        }
    }

    /**
     * Execute afterpost URL and show popup if the response contains visible content.
     * Recognizes and skips responses that only contain redirects or window-close scripts.
     * @param {number|string} recordId - The saved record ID
     */
    async executeAfterPost(recordId) {
        const url = this.afterPostUrl.replace('[ID]', recordId);
        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status} ${response.statusText}`);
            }
            const html = await response.text();

            // Check if response has visible content (not just redirects/close scripts)
            if (html) {
                const withoutScripts = html.replace(/<script[\s\S]*?<\/script>/gi, '');
                const textOnly = withoutScripts.replace(/<[^>]*>/g, '').trim();
                if (textOnly.length > 0) {
                    this.showHtmlResponsePopup(html, 'Nabewerking');
                }
            }
        } catch (error) {
            cmaLog.error('[executeAfterPost] Failed:', error.message);
            this.showTopNotification('Nabewerking mislukt: ' + error.message, 'error', 0);
        }
    }

    /**
     * Show HTML response in a popup window
     * Used when form post returns HTML instead of JSON (e.g., cma_afterpost.php output)
     * @param {string} htmlContent - HTML content to display
     * @param {string} title - Window title
     */
    showHtmlResponsePopup(htmlContent, title = 'Resultaat') {
        // Use lib_OpenWindowCentered if available
        if (typeof lib_OpenWindowCentered === 'function') {
            // Create a data URL to show the content
            const win = lib_OpenWindowCentered('about:blank', 'post_result', 800, 600, title);
            if (win) {
                win.document.open();
                win.document.write(htmlContent);
                win.document.close();
            }
        } else {
            // Fallback: open new window manually
            const width = 800;
            const height = 600;
            const left = (screen.width - width) / 2;
            const top = (screen.height - height) / 2;

            const win = window.open('about:blank', 'post_result',
                `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`);
            if (win) {
                win.document.open();
                win.document.write(htmlContent);
                win.document.close();
            }
        }

        // Also refresh the list since a post occurred
        this.loadList();
    }

    /**
     * Check if we're running in a popup context (either window.open, iframe popup, or sidepanel)
     * NOTE: Being inside main.php's content iframe is NOT a popup - it's the main content area
     */
    isInPopup() {
        // Check for real popup (window.open)
        if (window.opener) {
            return true;
        }
        // Check for sidepanel
        try {
            if (typeof lib_IsInSidePanel === 'function' && lib_IsInSidePanel()) {
                return true;
            }
        } catch (e) {
            // cmaLog.log('[isInPopup] Cross-origin sidepanel check:', e.message);
        }
        // Check for iframe popup (lib_OpenWindowCentered creates iframe-based popups)
        // But exclude main.php's content iframe - that's not a popup
        try {
            if (self !== top) {
                // Check if we're in a centered popup (has close function) but NOT main content area
                const hasPopupClose = typeof parent.lib_OpenWindowCenteredClose === 'function';
                const isMainContent = parent.document && parent.document.getElementById('frmContent');
                // Only consider it a popup if it has popup close AND we're not the main content frame
                if (hasPopupClose && !isMainContent) {
                    return true;
                }
                // Also check if parent explicitly marked us as a popup
                if (parent.window && parent.window._isPopupContext) {
                    return true;
                }
            }
        } catch (e) {
            // cmaLog.log('[isInPopup] Cross-origin popup check:', e.message);
        }
        return false;
    }

    /**
     * Close form and refresh parent list
     * @param {string|null} recordId - Record ID for targeted refresh
     * @param {boolean} deleted - True if record was deleted
     */
    async closeForm(recordId = null, deleted = false) {
        if (this.hasUnsavedChanges()) {
            const changeSummary = this.formatChangeSummary();
            const confirmed = await libConfirm('Je hebt niet-opgeslagen wijzigingen.' + changeSummary, {
                title: 'Niet-opgeslagen wijzigingen',
                confirmText: 'Verlaat scherm',
                cancelText: 'Blijf op scherm',
                type: 'warning',
                html: true
            });
            if (!confirmed) {
                return;
            }
        }

        // Check if this is an "add related record" popup (updatevalues parameter)
        const urlParams = new URLSearchParams(window.location.search);
        const updateValuesField = urlParams.get('updatevalues');
        // cmaLog.log('closeForm: recordId=', recordId, 'deleted=', deleted, 'updateValuesField=', updateValuesField, 'url=', window.location.href);

        // Handle real popup (window.open)
        if (window.opener) {
            try {
                // Find the opener's cmaForm - check .form-layout first (sidebar layout)
                let openerCmaForm = null;
                const openerFormLayout = window.opener.document.querySelector('.form-layout');
                if (openerFormLayout && openerFormLayout._cmaController) {
                    openerCmaForm = openerFormLayout._cmaController;
                    // cmaLog.log('closeForm: Found _cmaController on opener .form-layout');
                } else if (window.opener.cmaForm) {
                    openerCmaForm = window.opener.cmaForm;
                    // cmaLog.log('closeForm: Found cmaForm on opener');
                }

                // Special handling for "add related record" - refresh specific combobox
                if (updateValuesField && recordId && !deleted && openerCmaForm) {
                    if (typeof openerCmaForm.refreshComboOptions === 'function') {
                        openerCmaForm.refreshComboOptions(updateValuesField, recordId);
                        // cmaLog.log('Refreshed combobox in opener:', updateValuesField, 'with new ID:', recordId);
                    }
                } else if (openerCmaForm) {
                    if (deleted && recordId && typeof openerCmaForm.removeRowFromList === 'function') {
                        // Delete: remove row immediately (sync)
                        openerCmaForm.removeRowFromList(recordId);
                    } else if (recordId && typeof openerCmaForm.refreshRow === 'function') {
                        // Save with recordId: use refreshRow (preserves search/filter state)
                        // refreshRow handles fallback to loadList if row not found (new record)
                        openerCmaForm.refreshRow(recordId);
                    } else if (typeof openerCmaForm.loadList === 'function') {
                        // Fallback: reload the list
                        openerCmaForm.loadList();
                    } else {
                        window.opener.location.reload();
                    }
                } else {
                    // Fall back to full page reload
                    window.opener.location.reload();
                }
            } catch (e) {
                // cmaLog.log('[closeForm] Cross-origin opener access, trying reload:', e.message);
                try {
                    window.opener.location.reload();
                } catch (e2) {
                    // cmaLog.log('[closeForm] Cannot access opener for reload:', e2.message);
                }
            }
            window.close();
            return;
        }

        // Handle sidepanel
        try {
            if (typeof lib_IsInSidePanel === 'function' && lib_IsInSidePanel()) {
                cmaLog.log('[closeForm] in sidepanel, updateValuesField=', updateValuesField, 'recordId=', recordId, 'deleted=', deleted);
                if (updateValuesField && recordId && !deleted) {
                    cmaLog.log('[closeForm] calling refreshParentCombobox');
                    this.refreshParentCombobox(updateValuesField, recordId);
                } else {
                    // Try to refresh the parent's list before closing
                    this.refreshParentList(recordId, deleted);
                }
                // Close the sidepanel
                parent.lib_CloseSidePanel(true); // skipConfirm since we already checked
                return;
            }
        } catch (e) {
            cmaLog.warn('Error closing sidepanel:', e);
        }

        // Handle iframe popup (lib_OpenWindowCentered)
        // Note: lib_OpenWindowCentered creates the popup in top.document, so parent=top
        try {
            // cmaLog.log('closeForm: self!==top:', self !== top, 'lib_OpenWindowCenteredClose exists:', typeof parent.lib_OpenWindowCenteredClose === 'function');
            if (self !== top && typeof parent.lib_OpenWindowCenteredClose === 'function') {
                // For "add related record" popup, refresh the combobox instead of the list
                // cmaLog.log('closeForm: iframe popup, updateValuesField=', updateValuesField, 'recordId=', recordId, 'deleted=', deleted);
                if (updateValuesField && recordId && !deleted) {
                    // cmaLog.log('closeForm: calling refreshParentCombobox');
                    this.refreshParentCombobox(updateValuesField, recordId);
                } else {
                    // Try to refresh the parent's list before closing
                    this.refreshParentList(recordId, deleted);
                }
                // Close the popup
                parent.lib_OpenWindowCenteredClose();
                return;
            }
        } catch (e) {
            cmaLog.warn('Error closing iframe popup:', e);
        }

        // Not in a popup - go back
        history.back();
    }

    /**
     * Refresh the parent page's list after save/delete in popup
     * Handles various page structures (form.php, frameset, etc.)
     * @param {string|null} recordId - If provided, try to update just this row
     * @param {boolean} deleted - If true, remove the row instead of updating it
     */
    refreshParentList(recordId = null, deleted = false) {
        try {
            // Find the parent's cmaForm instance
            let parentCmaForm = null;

            // Structure 1: Look for controller on parent's .form-layout element (sidebar layout)
            const parentFormLayout = parent.document.querySelector('.form-layout');
            if (parentFormLayout && parentFormLayout._cmaController &&
                typeof parentFormLayout._cmaController.loadList === 'function') {
                parentCmaForm = parentFormLayout._cmaController;
                // cmaLog.log('refreshParentList: Found _cmaController on parent .form-layout');
            }

            // Structure 2: Parent has cmaForm directly (legacy/global reference)
            if (!parentCmaForm && parent.cmaForm && typeof parent.cmaForm.loadList === 'function') {
                parentCmaForm = parent.cmaForm;
                // cmaLog.log('refreshParentList: Found cmaForm on parent');
            }

            // Structure 3: Parent has details_iframe containing form.php with controller
            if (!parentCmaForm) {
                const detailsIframe = parent.document.getElementById('details_iframe');
                if (detailsIframe && detailsIframe.contentWindow) {
                    const iframeFormLayout = detailsIframe.contentWindow.document.querySelector('.form-layout');
                    if (iframeFormLayout && iframeFormLayout._cmaController &&
                        typeof iframeFormLayout._cmaController.loadList === 'function') {
                        parentCmaForm = iframeFormLayout._cmaController;
                        // cmaLog.log('refreshParentList: Found _cmaController in details_iframe');
                    } else if (detailsIframe.contentWindow.cmaForm &&
                        typeof detailsIframe.contentWindow.cmaForm.loadList === 'function') {
                        parentCmaForm = detailsIframe.contentWindow.cmaForm;
                        // cmaLog.log('refreshParentList: Found cmaForm in details_iframe');
                    }
                }
            }

            // Structure 4: Look for controller in any iframe
            if (!parentCmaForm) {
                const iframes = parent.document.getElementsByTagName('iframe');
                for (let i = 0; i < iframes.length; i++) {
                    try {
                        const iframeFormLayout = iframes[i].contentWindow?.document?.querySelector('.form-layout');
                        if (iframeFormLayout && iframeFormLayout._cmaController &&
                            typeof iframeFormLayout._cmaController.loadList === 'function') {
                            parentCmaForm = iframeFormLayout._cmaController;
                            // cmaLog.log('refreshParentList: Found _cmaController in iframe', iframes[i].id);
                            break;
                        }
                        if (iframes[i].contentWindow &&
                            iframes[i].contentWindow.cmaForm &&
                            typeof iframes[i].contentWindow.cmaForm.loadList === 'function') {
                            parentCmaForm = iframes[i].contentWindow.cmaForm;
                            // cmaLog.log('refreshParentList: Found cmaForm in iframe', iframes[i].id);
                            break;
                        }
                    } catch (e) {
                        // cmaLog.log('[refreshParentList] Cross-origin iframe skipped:', e.message);
                    }
                }
            }

            // If we found cmaForm, refresh the list
            if (parentCmaForm) {
                if (deleted && recordId) {
                    // Delete: remove the row from the list immediately (sync operation)
                    if (typeof parentCmaForm.removeRowFromList === 'function') {
                        // cmaLog.log('refreshParentList: Removing row', recordId);
                        parentCmaForm.removeRowFromList(recordId);
                        return;
                    }
                }
                // For save: try refreshRow first (preserves search/filter state)
                // refreshRow updates just the changed row; if row not found (new record), it falls back to loadList
                if (recordId && typeof parentCmaForm.refreshRow === 'function') {
                    // cmaLog.log('refreshParentList: Using refreshRow for record', recordId);
                    // Call refreshRow - it handles fallback to loadList internally
                    parentCmaForm.refreshRow(recordId);
                    return;
                }
                // Fallback: reload the full list
                // cmaLog.log('refreshParentList: Reloading full list with forceRefresh');
                parentCmaForm.loadList(true);
                return;
            }

            // Structure 4: No cmaForm found - try to reload details_iframe or parent
            const detailsIframe = parent.document.getElementById('details_iframe');
            if (detailsIframe && detailsIframe.contentWindow) {
                // cmaLog.log('refreshParentList: Reloading details_iframe');
                detailsIframe.contentWindow.location.reload();
                return;
            }

            // Last resort - schedule reload after popup closes
            // cmaLog.log('refreshParentList: No cmaForm found, will reload parent');
            setTimeout(function() {
                try {
                    parent.location.reload();
                } catch (e) {
                    // cmaLog.log('[refreshParentList] Cannot reload parent:', e.message);
                }
            }, 100);
        } catch (e) {
            cmaLog.warn('refreshParentList error:', e);
        }
    }

    /**
     * Refresh a specific combobox in the parent page after adding a related record
     * @param {string} fieldName - Field name of the combobox to refresh
     * @param {string} newRecordId - ID of the newly created record to select
     */
    refreshParentCombobox(fieldName, newRecordId) {
        try {
            // Find the parent's cmaForm instance (same logic as refreshParentList)
            let parentCmaForm = null;

            // Structure 1: Parent has cmaForm directly
            if (parent.cmaForm && typeof parent.cmaForm.refreshComboOptions === 'function') {
                parentCmaForm = parent.cmaForm;
            }

            // Structure 2: Parent has details_iframe containing form.php with cmaForm
            if (!parentCmaForm) {
                const detailsIframe = parent.document.getElementById('details_iframe');
                if (detailsIframe && detailsIframe.contentWindow &&
                    detailsIframe.contentWindow.cmaForm &&
                    typeof detailsIframe.contentWindow.cmaForm.refreshComboOptions === 'function') {
                    parentCmaForm = detailsIframe.contentWindow.cmaForm;
                }
            }

            // Structure 3: Look for cmaForm in any iframe
            if (!parentCmaForm) {
                const iframes = parent.document.getElementsByTagName('iframe');
                for (let i = 0; i < iframes.length; i++) {
                    try {
                        if (iframes[i].contentWindow &&
                            iframes[i].contentWindow.cmaForm &&
                            typeof iframes[i].contentWindow.cmaForm.refreshComboOptions === 'function') {
                            parentCmaForm = iframes[i].contentWindow.cmaForm;
                            break;
                        }
                    } catch (e) {
                        // cmaLog.log('[refreshParentCombobox] Cross-origin iframe skipped:', e.message);
                    }
                }
            }

            // Check for inline edit callback first (set by CmaInlineEdit.openAddRelatedPopup)
            // This takes priority because the inline combobox is in the table, not in the detail form
            const parentFormLayout = parent.document.querySelector('.form-layout');
            if (parentFormLayout && typeof parentFormLayout._addRelatedCallback === 'function') {
                cmaLog.log('[refreshParentCombobox] Using formLayout._addRelatedCallback, newRecordId:', newRecordId);
                parentFormLayout._addRelatedCallback(newRecordId);
                return;
            }

            // If we found cmaForm, refresh the combobox (detail form context)
            if (parentCmaForm) {
                cmaLog.log('[refreshParentCombobox] Refreshing', fieldName, 'with new ID', newRecordId);
                parentCmaForm.refreshComboOptions(fieldName, newRecordId);
                return;
            }

            // Fallback: try the global callback if set (on parent or top)
            if (typeof parent._cmaAddRelatedCallback === 'function') {
                // cmaLog.log('refreshParentCombobox: Using parent callback');
                parent._cmaAddRelatedCallback(newRecordId);
                return;
            }
            if (typeof top._cmaAddRelatedCallback === 'function') {
                // cmaLog.log('refreshParentCombobox: Using top callback');
                top._cmaAddRelatedCallback(newRecordId);
                return;
            }

            cmaLog.warn('refreshParentCombobox: Could not find parent cmaForm or callback');
        } catch (e) {
            cmaLog.warn('refreshParentCombobox error:', e);
        }
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // =========================================================================
    // SUSPEND / RESUME - Used by DOM page cache for instant navigation
    // =========================================================================

    /**
     * Suspend the controller when caching the page.
     * Removes document/window-level listeners, aborts fetches, clears timers,
     * and saves body classes so the page can be detached from the DOM.
     */
    suspend() {
        if (this._suspended) return;
        this._suspended = true;
        // cmaLog.log('[CmaFormController] Suspending:', this._instanceId, 'form:', this.jsonForm);

        // Save form-related body classes before they get cleared
        const FORM_CLASSES = ['has-record', 'is-creating', 'mode-detail', 'mode-tree', 'mode-table', 'has-subform', 'is-dirty', 'data-loading'];
        this._savedBodyClasses = FORM_CLASSES.filter(cls => document.body.classList.contains(cls));

        // Remove document/window-level listeners (keep element-level ones intact)
        // These would fire for the wrong form if left attached while suspended
        this._suspendedListeners = [];
        const keepListeners = [];
        for (const entry of this._trackedListeners) {
            if (entry.element === document || entry.element === window) {
                try {
                    entry.element.removeEventListener(entry.event, entry.handler, entry.options);
                } catch (e) {
                    cmaLog.warn('[suspend] Failed to remove listener:', entry.event, e);
                }
                this._suspendedListeners.push(entry);
            } else {
                keepListeners.push(entry);
            }
        }
        this._trackedListeners = keepListeners;

        // Abort any pending fetches
        if (this._abortController) {
            this._abortController.abort();
            this._abortController = null;
        }

        // Note: cmaCheckUnsavedChanges is now a persistent DOM-driven handler,
        // no need to clear/reassign per form.

        // Clear timers
        if (this._popupCheckInterval) {
            clearInterval(this._popupCheckInterval);
            this._popupCheckInterval = null;
        }
        if (this.listLoadingTimer) {
            clearTimeout(this.listLoadingTimer);
            this.listLoadingTimer = null;
        }
        if (this.loadingTimer) {
            clearTimeout(this.loadingTimer);
            this.loadingTimer = null;
        }
    }

    /**
     * Resume a suspended controller when restoring from cache.
     * Re-adds document/window listeners, creates a new AbortController,
     * restores body classes, and refreshes list data in the background.
     */
    resume() {
        if (!this._suspended) return;
        this._suspended = false;
        // cmaLog.log('[CmaFormController] Resuming:', this._instanceId, 'form:', this.jsonForm);

        // Re-add suspended document/window listeners
        if (this._suspendedListeners) {
            for (const entry of this._suspendedListeners) {
                try {
                    entry.element.addEventListener(entry.event, entry.handler, entry.options);
                } catch (e) {
                    cmaLog.warn('[resume] Failed to re-add listener:', entry.event, e);
                }
                this._trackedListeners.push(entry);
            }
            this._suspendedListeners = null;
        }

        // Create new AbortController for future fetches
        this._abortController = new AbortController();

        // Restore saved body classes
        if (this._savedBodyClasses) {
            for (const cls of this._savedBodyClasses) {
                document.body.classList.add(cls);
            }
            this._savedBodyClasses = null;
        }

        // Note: cmaCheckUnsavedChanges is now a persistent DOM-driven handler,
        // no need to re-register per form.

        // Re-bind DOM references that loadList/loadRecord read from document
        // (the cached elements are back in the DOM now)
        this.listContent = document.getElementById('listContent');
        this.listPanel = document.getElementById('listPanel');
        this.detailPanel = document.getElementById('detailPanel');
        this.detailContent = document.getElementById('detailContent');
        this.loadingOverlay = document.getElementById('loadingOverlay');
        this.toolbarStatus = document.getElementById('toolbar-status');
        this.noDataMessage = document.getElementById('noDataMessage');

        // NOTE: We intentionally do NOT call loadList() here.
        // The cached DOM already contains the visible list data.
        // Calling loadList would clear the list and show a loading state,
        // defeating the purpose of instant cache restore.
    }

    /**
     * Destroy the form controller and clean up all resources
     * Called before creating a new controller when navigating between forms
     */
    destroy() {
        // cmaLog.log('CmaFormController: Destroying instance for formId:', this.formId);

        // State is now stored in DOM (data attributes and classes), so it automatically
        // cleans up when the DOM is replaced. We just need to clean up non-DOM references.
        // The getters/setters for isDirty, originalValues, mainForm, currentRecordId
        // all read from DOM, so no manual cleanup needed for those.

        // Clean up DOM reference to prevent stale controller access
        const formLayout = document.querySelector('.form-layout');
        if (formLayout && formLayout._cmaFormController === this) {
            delete formLayout._cmaFormController;
        }

        // Remove tracked event listeners (document, window, etc.)
        this.removeTrackedListeners();

        // Destroy all CKEditor instances to prevent stale references
        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances) {
            for (const name in CKEDITOR.instances) {
                if (CKEDITOR.instances.hasOwnProperty(name)) {
                    try {
                        CKEDITOR.instances[name].destroy(true);
                    } catch (e) {
                        cmaLog.warn('Failed to destroy CKEditor instance:', name, e);
                    }
                }
            }
        }

        // Destroy inline editor (removes document-level event listeners)
        if (this.inlineEditor && typeof this.inlineEditor.destroy === 'function') {
            this.inlineEditor.destroy();
            this.inlineEditor = null;
        }

        // Destroy all subform inline editors
        this.destroySubformEditors();
        this.subformEditors = null;

        // Destroy column manager
        if (this.columnManager && typeof this.columnManager.destroy === 'function') {
            this.columnManager.destroy();
            this.columnManager = null;
        }

        // Destroy infinite scroll
        if (this.infiniteScroll && typeof this.infiniteScroll.destroy === 'function') {
            this.infiniteScroll.destroy();
            this.infiniteScroll = null;
        }

        // Disconnect toolbar resize observer
        if (this._toolbarResizeObserver) {
            this._toolbarResizeObserver.disconnect();
            this._toolbarResizeObserver = null;
        }

        // Clear abort controller
        if (this._abortController) {
            this._abortController.abort();
            this._abortController = null;
        }

        // Clear popup check interval
        if (this._popupCheckInterval) {
            clearInterval(this._popupCheckInterval);
            this._popupCheckInterval = null;
        }

        // Clear list loading timer
        if (this.listLoadingTimer) {
            clearTimeout(this.listLoadingTimer);
            this.listLoadingTimer = null;
        }

        // Clear tree event handler references (listeners are removed by removeTrackedListeners)
        this._treeEventsBound = false;
        this._treeClickHandler = null;
        this._treeDblclickHandler = null;

        // Clear list item event handler references
        this._listItemEventsBound = false;
        this._listItemClickHandler = null;

        // Clean up callbacks stored on form-layout element (NO GLOBALS)
        // Use different name since formLayout already declared above in this method
        const formLayoutForCleanup = document.querySelector('.form-layout');
        if (formLayoutForCleanup) {
            delete formLayoutForCleanup._fileSelectCallback;
            delete formLayoutForCleanup._cropCallback;
            delete formLayoutForCleanup._addRelatedCallback;
            // Clear controller reference
            if (formLayoutForCleanup._cmaController === this) {
                formLayoutForCleanup._cmaController = null;
            }
        }
    }
}

// Export to CMA namespace (NOT directly on window)
// CMA is the single namespace for this application
window.CMA = window.CMA || {};
CMA.FormController = CmaFormController;

// Persistent unsaved changes handler — registered once, not per form.
// Looks up the active controller via DOM at check time.
window.cmaCheckUnsavedChanges = () => {
    return new Promise(resolve => {
        if (!document.body.classList.contains('is-dirty')) {
            resolve(true);
            return;
        }
        libConfirm('Er zijn niet-opgeslagen wijzigingen. Weet je zeker dat je wilt navigeren?', {
            title: 'Niet-opgeslagen wijzigingen',
            confirmText: 'Verlaat pagina',
            cancelText: 'Blijven',
            type: 'warning'
        }).then(function(leave) {
            resolve(leave);
        });
    });
};

/**
 * Get the storage key for form state (uses DOM-based controller lookup)
 */
function getFormStateKey() {
    // Try to get form name from multiple sources for robustness
    // 1. From controller (if initialized)
    const controller = CMA.FormController.getController();
    if (controller && controller.jsonForm) {
        // cmaLog.log('[FormState] Key from controller:', controller.jsonForm);
        return 'form_state_' + controller.jsonForm;
    }

    // 2. From URL parameter (works before controller is initialized)
    const urlParams = new URLSearchParams(window.location.search);
    const formParam = urlParams.get('form') || urlParams.get('jsonForm');
    if (formParam) {
        // cmaLog.log('[FormState] Key from URL param:', formParam);
        return 'form_state_' + formParam;
    }

    // 3. From clean URL path: /cma/form/{formName} or /cma/form/{formName}/{id}
    const pathMatch = window.location.pathname.match(/\/form\/([^\/]+)/);
    if (pathMatch && pathMatch[1]) {
        // cmaLog.log('[FormState] Key from clean URL:', pathMatch[1]);
        return 'form_state_' + pathMatch[1];
    }

    // 4. From data attribute on form-layout element
    const formLayout = document.querySelector('.form-layout[data-json-form]');
    if (formLayout && formLayout.dataset.jsonForm) {
        // cmaLog.log('[FormState] Key from data attribute:', formLayout.dataset.jsonForm);
        return 'form_state_' + formLayout.dataset.jsonForm;
    }

    // cmaLog.log('[FormState] No form key found');
    return null;
}

/**
 * Save form layout state (list panel width) to localStorage
 */
function saveFormState() {
    const formKey = getFormStateKey();
    // cmaLog.log('[FormState] saveFormState called, formKey=', formKey);
    if (!formKey) return;

    const leftList = document.getElementById('leftlist');
    const state = {};

    // Save list width (vertical fold position)
    if (leftList) {
        state.listWidth = leftList.style.width || leftList.offsetWidth + 'px';
    }

    // Save subform height (horizontal fold position)
    const subformHeight = getComputedStyle(document.querySelector('.form-layout') || document.documentElement).getPropertyValue('--subform-height');
    // cmaLog.log('[FormState] subformHeight from CSS var=', subformHeight);
    if (subformHeight && subformHeight.trim()) {
        state.subformHeight = subformHeight.trim();
    }

    // cmaLog.log('[FormState] saving state=', state);
    try {
        localStorage.setItem(formKey, JSON.stringify(state));
        // cmaLog.log('[FormState] saved to localStorage key:', formKey);
    } catch (e) {
        cmaLog.error('[FormState] localStorage error', e.message);
    }
}

/**
 * Calculate dynamic default subform height based on main form field count.
 * Forms with fewer fields get more space for subforms.
 * Rule: ~40px per visible field, remaining space goes to subforms.
 */
function calculateDynamicSubformHeight() {
    const subformSection = document.getElementById('subformSection');
    if (!subformSection) {
        return null; // No subforms, no need to calculate
    }

    const mainTable = document.getElementById('maintable');
    if (!mainTable) {
        return 250; // Default fallback
    }

    // Count visible field rows (exclude hidden rows, groupbox-end, etc.)
    const rows = mainTable.querySelectorAll('tr');
    let visibleFieldCount = 0;
    rows.forEach(function(row) {
        // Skip hidden rows
        if (row.style.display === 'none' || row.classList.contains('groupbox-end')) {
            return;
        }
        // Count as a field row
        visibleFieldCount++;
    });

    // cmaLog.log('[FormState] visible field count:', visibleFieldCount);

    // Calculate main form estimated height (40px per field)
    const estimatedMainFormHeight = visibleFieldCount * 40;

    // Get available height (detail panel minus toolbar and padding)
    const detailPanel = document.querySelector('.detail-panel');
    const detailContent = document.getElementById('detailContent');
    if (!detailPanel || !detailContent) {
        return 250; // Default fallback
    }

    // Total available height in detail area
    const detailPanelHeight = detailPanel.offsetHeight;
    const toolbarHeight = 45; // Approximate toolbar height
    const foldBarHeight = 12; // Horizontal fold bar
    const padding = 20; // Margins/padding

    // Available for subforms = total - mainForm - toolbar - foldBar - padding
    const availableForSubforms = detailPanelHeight - estimatedMainFormHeight - toolbarHeight - foldBarHeight - padding;

    // Ensure minimum and maximum bounds
    const minHeight = 150;
    const maxHeight = 500;
    const calculatedHeight = Math.max(minHeight, Math.min(maxHeight, availableForSubforms));

    // cmaLog.log('[FormState] dynamic subform height: fields=', visibleFieldCount,
    //     'mainFormHeight=', estimatedMainFormHeight,
    //     'panelHeight=', detailPanelHeight,
    //     'calculated=', calculatedHeight);

    return calculatedHeight;
}

/**
 * Restore form layout state from localStorage
 */
function restoreFormState() {
    const formKey = getFormStateKey();
    // cmaLog.log('[FormState] restoreFormState called, formKey=', formKey);
    if (!formKey) return;

    let hasUserSubformHeight = false;

    try {
        const saved = localStorage.getItem(formKey);
        // cmaLog.log('[FormState] loaded from localStorage:', saved);
        if (saved) {
            const state = JSON.parse(saved);
            // cmaLog.log('[FormState] parsed state=', state);

            // Restore list width (vertical fold position)
            const leftList = document.getElementById('leftlist');
            if (leftList && state.listWidth) {
                leftList.style.width = state.listWidth;
                // Disable transition for initial load
                leftList.style.transition = 'none';
                setTimeout(() => {
                    leftList.style.transition = '';
                }, 50);
            }

            // Restore subform height (horizontal fold position)
            if (state.subformHeight) {
                // cmaLog.log('[FormState] setting --subform-height to user saved:', state.subformHeight);
                (document.querySelector('.form-layout') || document.documentElement).style.setProperty('--subform-height', state.subformHeight);
                hasUserSubformHeight = true;
            }
        }
    } catch (e) {
        cmaLog.error('[FormState] restoreFormState error', e.message);
    }

    // If no user preference, calculate dynamic default based on field count
    if (!hasUserSubformHeight) {
        const dynamicHeight = calculateDynamicSubformHeight();
        if (dynamicHeight !== null) {
            // cmaLog.log('[FormState] setting --subform-height to dynamic default:', dynamicHeight + 'px');
            (document.querySelector('.form-layout') || document.documentElement).style.setProperty('--subform-height', dynamicHeight + 'px');
        }
    }
}

/**
 * Initialize draggable fold bar
 */
// @deprecated Fold bars now use <cma-fold> web component — these are no-op stubs
function initFoldBar() {}


/**
 * Initialize horizontal fold bar between detail form and subforms
 * Uses CSS variables for height calculations
 */
function initHorizontalFoldBar() {}


// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // cmaLog.log('[FormState] DOMContentLoaded fired');
    // Small delay to ensure cmaForm is initialized
    setTimeout(function() {
        // cmaLog.log('[FormState] Starting initialization after delay');
        restoreFormState();
        initFoldBar();
        initHorizontalFoldBar();
    }, 100);
});
