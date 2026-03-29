/**
 * CMA Performance Logger
 *
 * Client-side performance logging that syncs with server logs.
 * Automatically captures page load metrics, API calls, and custom timers.
 *
 * Usage:
 *   cmaPerf.start('myOperation');
 *   // ... do work ...
 *   cmaPerf.end('myOperation', { extra: 'context' });
 *
 *   cmaPerf.mark('pointA');
 *   // ... later ...
 *   cmaPerf.measure('operation', 'pointA');
 *
 *   cmaPerf.log('custom', 'eventName', 123, { data: 'here' });
 */

(function(global) {
    'use strict';

    const LOG_ENDPOINT = '/cma/api/log.php';
    const BATCH_SIZE = 20;
    const BATCH_INTERVAL = 5000; // 5 seconds
    const MAX_QUEUE_SIZE = 200; // Prevent unbounded queue growth
    // Only enable when explicitly requested via CMA_PERF_LOG flag
    const ENABLED = !!window.CMA_PERF_LOG;

    let queue = [];
    let timers = {};
    let marks = {};
    let batchTimer = null;
    let pageLoadTime = performance.now();
    let requestId = null;

    /**
     * Generate a short unique ID
     */
    function generateId() {
        return Math.random().toString(36).substring(2, 10);
    }

    /**
     * Get or generate request ID
     */
    function getRequestId() {
        if (!requestId) {
            requestId = generateId();
        }
        return requestId;
    }

    /**
     * Add entry to queue
     */
    function addEntry(type, name, duration, context) {
        if (!ENABLED) return;

        // Prevent unbounded queue growth - drop oldest entries if at max
        if (queue.length >= MAX_QUEUE_SIZE) {
            queue.splice(0, BATCH_SIZE); // Remove oldest batch
        }

        queue.push({
            type: type,
            name: name,
            duration: Math.round(duration * 100) / 100,
            context: context || {},
            ts: Date.now(),
            reqId: getRequestId()
        });

        // Schedule batch send
        if (!batchTimer) {
            batchTimer = setTimeout(flushQueue, BATCH_INTERVAL);
        }

        // Send immediately if queue is full
        if (queue.length >= BATCH_SIZE) {
            flushQueue();
        }
    }

    /**
     * Send queued entries to server
     */
    function flushQueue() {
        if (batchTimer) {
            clearTimeout(batchTimer);
            batchTimer = null;
        }

        if (queue.length === 0) return;

        const entries = queue.splice(0, BATCH_SIZE);

        // Use sendBeacon if available (works on page unload)
        if (navigator.sendBeacon) {
            const blob = new Blob([JSON.stringify({ entries: entries })], {
                type: 'application/json'
            });
            navigator.sendBeacon(LOG_ENDPOINT, blob);
        } else {
            // Fallback to fetch
            fetch(LOG_ENDPOINT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ entries: entries }),
                keepalive: true
            }).catch(() => {
                // Silently fail - logging should not break the app
            });
        }

        // Continue flushing if more entries
        if (queue.length > 0) {
            batchTimer = setTimeout(flushQueue, 100);
        }
    }

    /**
     * Start a timer
     */
    function start(name) {
        if (!ENABLED) return;
        timers[name] = performance.now();
    }

    /**
     * End a timer and log duration
     */
    function end(name, context) {
        if (!ENABLED) return 0;
        if (!timers[name]) {
            cmaLog.warn('cmaPerf: No timer started for', name);
            return 0;
        }

        const duration = performance.now() - timers[name];
        delete timers[name];

        addEntry('timer', name, duration, context);
        return duration;
    }

    /**
     * Mark a point in time
     */
    function mark(name) {
        marks[name] = performance.now();
    }

    /**
     * Measure time between marks
     */
    function measure(name, startMark, endMark) {
        const startTime = marks[startMark] || pageLoadTime;
        const endTime = endMark ? (marks[endMark] || performance.now()) : performance.now();
        const duration = endTime - startTime;

        addEntry('measure', name, duration, {
            from: startMark,
            to: endMark || 'now'
        });

        return duration;
    }

    /**
     * Log a custom entry
     */
    function log(type, name, duration, context) {
        addEntry(type, name, duration || 0, context);
    }

    /**
     * Log an API call
     */
    function logApi(action, duration, context) {
        addEntry('api', action, duration, context);
    }

    /**
     * Log a fetch request
     */
    function logFetch(url, duration, context) {
        // Extract action from URL
        const urlObj = new URL(url, window.location.origin);
        const action = urlObj.searchParams.get('action') || urlObj.pathname.split('/').pop();

        addEntry('fetch', action, duration, {
            url: url.substring(0, 200),
            ...context
        });
    }

    /**
     * Log a render operation
     */
    function logRender(component, duration, context) {
        addEntry('render', component, duration, context);
    }

    /**
     * Increment a counter
     */
    function count(name, value) {
        addEntry('count', name, value || 1, {});
    }

    /**
     * Set a gauge value (point-in-time measurement)
     */
    function gauge(name, value) {
        addEntry('gauge', name, value, {});
    }

    /**
     * Log server timing data (from response headers or JSON)
     */
    function serverTiming(operation, timingData) {
        if (!timingData) return;
        addEntry('serverTiming', operation, 0, timingData);
    }

    /**
     * Get timing statistics
     */
    function getStats() {
        return {
            queueLength: queue.length,
            activeTimers: Object.keys(timers),
            marks: Object.keys(marks),
            requestId: getRequestId(),
            pageLoadTime: Math.round(performance.now())
        };
    }

    /**
     * Capture page load metrics
     */
    function capturePageLoad() {
        if (!performance || !performance.timing) return;

        // Wait for load event
        if (document.readyState !== 'complete') {
            window.addEventListener('load', capturePageLoad);
            return;
        }

        const timing = performance.timing;
        const nav = performance.getEntriesByType('navigation')[0];

        if (nav) {
            // Get page name from URL path (e.g., "form.php?form=users" -> "form.php")
            const pagePath = window.location.pathname.split('/').pop() || 'index';
            const pageName = pagePath + (window.location.search ? window.location.search.substring(0, 50) : '');

            addEntry('pageload', pageName, nav.loadEventEnd - nav.startTime, {
                dns: Math.round(nav.domainLookupEnd - nav.domainLookupStart),
                tcp: Math.round(nav.connectEnd - nav.connectStart),
                ttfb: Math.round(nav.responseStart - nav.requestStart),
                download: Math.round(nav.responseEnd - nav.responseStart),
                domParse: Math.round(nav.domInteractive - nav.responseEnd),
                domReady: Math.round(nav.domContentLoadedEventEnd - nav.startTime),
                load: Math.round(nav.loadEventEnd - nav.startTime),
                transferSize: nav.transferSize,
                type: nav.type
            });
        }

        // Log resource timings for slow resources
        const resources = performance.getEntriesByType('resource');
        const slowResources = resources.filter(r => r.duration > 100);

        slowResources.forEach(r => {
            addEntry('resource', r.name.split('/').pop().substring(0, 50), r.duration, {
                type: r.initiatorType,
                size: r.transferSize,
                cached: r.transferSize === 0
            });
        });
    }

    /**
     * Wrap fetch to automatically log API calls
     */
    function wrapFetch() {
        const originalFetch = window.fetch;

        window.fetch = function(url, options) {
            const startTime = performance.now();
            const urlStr = typeof url === 'string' ? url : url.url;

            return originalFetch.apply(this, arguments)
                .then(response => {
                    const duration = performance.now() - startTime;

                    // Only log CMA API calls
                    if (urlStr.includes('api/') || urlStr.includes('form_api.php')) {
                        logFetch(urlStr, duration, {
                            status: response.status,
                            ok: response.ok
                        });
                    }

                    return response;
                })
                .catch(error => {
                    const duration = performance.now() - startTime;
                    logFetch(urlStr, duration, {
                        error: error.message
                    });
                    throw error;
                });
        };
    }

    /**
     * Initialize logger
     */
    function init() {
        // Capture page load metrics after load
        if (document.readyState === 'complete') {
            setTimeout(capturePageLoad, 100);
        } else {
            window.addEventListener('load', () => setTimeout(capturePageLoad, 100));
        }

        // Wrap fetch for automatic API logging
        wrapFetch();

        // Flush on page unload
        window.addEventListener('beforeunload', flushQueue);
        window.addEventListener('pagehide', flushQueue);

        // Mark initialization
        mark('perfLoggerInit');
    }

    // Export API
    const cmaPerf = {
        start: start,
        end: end,
        mark: mark,
        measure: measure,
        log: log,
        logApi: logApi,
        logFetch: logFetch,
        logRender: logRender,
        count: count,
        gauge: gauge,
        serverTiming: serverTiming,
        flush: flushQueue,
        stats: getStats,
        getRequestId: getRequestId,
        init: init
    };

    // Auto-initialize
    init();

    // Expose globally
    global.cmaPerf = cmaPerf;

})(typeof window !== 'undefined' ? window : this);
