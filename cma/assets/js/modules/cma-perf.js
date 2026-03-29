/**
 * Performance monitoring utility for CMA
 * Provides detailed timing information for debugging performance issues
 *
 * Usage:
 *   cmaPerf.start('loadList');           // Start a timer
 *   // ... do work ...
 *   cmaPerf.end('loadList');             // End timer, logs duration
 *
 *   cmaPerf.mark('fetchStart');          // Create a performance mark
 *   cmaPerf.mark('fetchEnd');
 *   cmaPerf.measure('fetch', 'fetchStart', 'fetchEnd');  // Measure between marks
 *
 *   cmaPerf.count('apiCalls');           // Increment a counter
 *   cmaPerf.gauge('recordCount', 150);   // Set a gauge value
 *
 *   cmaPerf.summary();                   // Show all collected metrics
 *   cmaPerf.clear();                     // Reset all metrics
 *
 * @module cma-perf
 */

// Use cmaLog if available, otherwise fallback
const log = window.cmaLog || { log: () => {}, warn: console.warn, error: console.error };

const cmaPerf = (function() {
    // Storage for timers, counters, and metrics
    let timers = {};
    let counters = {};
    let gauges = {};
    let measurements = [];
    // Use CMA_CONSOLE_LOGGING (respects user preference cookie) instead of just CMA_DEBUG
    let enabled = typeof window.CMA_CONSOLE_LOGGING !== 'undefined'
        ? window.CMA_CONSOLE_LOGGING
        : (typeof CMA_DEBUG !== 'undefined' ? CMA_DEBUG : false);

    // Performance color coding for console
    const colors = {
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
        const match = document.cookie.match(/(?:^|; )cma_perf_logging=([^;]*)/);
        return match && match[1] === 'J';
    }

    return {
        /**
         * Check if performance monitoring is enabled
         */
        isEnabled() {
            return enabled;
        },

        /**
         * Enable/disable performance monitoring
         */
        setEnabled(value) {
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
        start(label, meta) {
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
        end(label, extraMeta) {
            if (!enabled) return 0;
            const timer = timers[label];
            if (!timer) {
                log.warn('[CMA Perf] Timer not found:', label);
                return 0;
            }

            const duration = getTimestamp() - timer.start;
            const meta = Object.assign({}, timer.meta, extraMeta || {});

            // Store measurement
            measurements.push({
                label: label,
                duration: duration,
                meta: meta,
                timestamp: Date.now()
            });

            // Log with color coding
            const metaStr = Object.keys(meta).length > 0
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
        mark(name) {
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
        measure(name, startMark, endMark) {
            if (!enabled) return;
            try {
                performance.measure('cma_' + name, 'cma_' + startMark, 'cma_' + endMark);
                const entries = performance.getEntriesByName('cma_' + name, 'measure');
                if (entries.length > 0) {
                    const duration = entries[entries.length - 1].duration;
                    console.log(
                        '%c[Perf] %c' + name + '%c ' + formatMs(duration),
                        colors.label,
                        getSpeedColor(duration),
                        colors.label
                    );
                }
            } catch (e) {
                // Fallback
                const start = timers['mark_' + startMark];
                const end = timers['mark_' + endMark];
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
        count(name, amount) {
            if (!enabled) return;
            counters[name] = (counters[name] || 0) + (amount || 1);
        },

        /**
         * Set a gauge value
         * @param {string} name - Gauge name
         * @param {number} value - Value to set
         */
        gauge(name, value) {
            if (!enabled) return;
            gauges[name] = value;
        },

        /**
         * Log a group of related timing data
         * @param {string} groupName - Group name
         * @param {object} timings - Object with timing values in ms
         */
        group(groupName, timings) {
            if (!enabled) return;
            console.groupCollapsed('%c[Perf] ' + groupName, colors.header);
            for (const key in timings) {
                if (timings.hasOwnProperty(key)) {
                    const value = timings[key];
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
        serverTiming(endpoint, timing) {
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
            for (const key in timing) {
                if (!['total', 'query', 'render', 'rows', 'sql'].includes(key)) {
                    console.log('%c  ' + key + ': %c' + timing[key], colors.label, colors.value);
                }
            }
            console.groupEnd();
        },

        /**
         * Show summary of all collected metrics
         */
        summary() {
            console.log('%c═══════════════════════════════════════════════════════', colors.header);
            console.log('%c CMA Performance Summary', colors.header);
            console.log('%c═══════════════════════════════════════════════════════', colors.header);

            // Measurements summary
            if (measurements.length > 0) {
                console.log('%c\n📊 Recent Timings (' + measurements.length + ' measurements)', colors.subheader);

                // Group by label and calculate averages
                const byLabel = {};
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
                const sorted = Object.keys(byLabel).sort(function(a, b) {
                    return byLabel[b].total - byLabel[a].total;
                });

                sorted.forEach(function(label) {
                    const stats = byLabel[label];
                    const avg = stats.total / stats.count;
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
                for (const name in counters) {
                    console.log('%c  ' + name + ': %c' + counters[name], colors.label, colors.value);
                }
            }

            // Gauges
            if (Object.keys(gauges).length > 0) {
                console.log('%c\n📈 Gauges', colors.subheader);
                for (const name in gauges) {
                    console.log('%c  ' + name + ': %c' + gauges[name], colors.label, colors.value);
                }
            }

            // Active timers (possible leaks)
            const activeTimers = Object.keys(timers);
            if (activeTimers.length > 0) {
                console.log('%c\n⚠️ Active Timers (not ended)', 'color: #ffc107; font-weight: bold');
                activeTimers.forEach(function(label) {
                    const elapsed = getTimestamp() - timers[label].start;
                    console.log('%c  ' + label + ': %c' + formatMs(elapsed) + ' (running)', colors.label, colors.slow);
                });
            }

            console.log('%c\n═══════════════════════════════════════════════════════', colors.header);
        },

        /**
         * Clear all collected metrics
         */
        clear() {
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
        getData() {
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
        sendToServer(clearAfterSend) {
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

            const data = this.getData();
            data.page = window.location.pathname;
            data.timestamp = Date.now();
            data.userAgent = navigator.userAgent;

            const formData = new FormData();
            formData.append('action', 'logPerformance');
            formData.append('data', JSON.stringify(data));

            const self = this;
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
                    log.warn('[CMA Perf] Failed to send data:', response.status);
                }
                return response;
            }).catch(function(err) {
                log.warn('[CMA Perf] Error sending data:', err);
            });
        },

        /**
         * Auto-send performance data before page unload
         * Call this to enable automatic logging
         */
        enableAutoLog() {
            const self = this;
            window.addEventListener('beforeunload', function() {
                // Check if server-side logging is enabled
                if (!isServerLoggingEnabled()) {
                    return;
                }

                // Use sendBeacon for reliable delivery during unload
                if (navigator.sendBeacon && measurements.length > 0) {
                    const data = self.getData();
                    data.page = window.location.pathname;
                    data.timestamp = Date.now();
                    data.userAgent = navigator.userAgent;

                    const formData = new FormData();
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

// Attach to window for backward compatibility
if (typeof window !== 'undefined') {
    window.cmaPerf = cmaPerf;
}

export default cmaPerf;
