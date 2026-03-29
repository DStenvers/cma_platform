/**
 * CMA Request Tracker
 *
 * Tracks all AJAX requests with:
 * - Unique request IDs for correlation
 * - Timing information
 * - Success/failure status
 * - Persistent logging for debugging
 *
 * This helps debug intermittent issues where requests silently fail.
 */
(function() {
    'use strict';

    const MAX_REQUESTS = 100;
    const STORAGE_KEY = 'cma_request_log';

    // Request log - kept in memory and localStorage
    let requestLog = [];
    let requestCounter = 0;

    /**
     * Generate a unique request ID
     */
    function generateRequestId() {
        requestCounter++;
        return 'req_' + Date.now() + '_' + requestCounter;
    }

    /**
     * Load request log from localStorage
     */
    function loadFromStorage() {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                requestLog = JSON.parse(stored);
            }
        } catch (e) {
            // Ignore
        }
    }

    /**
     * Save request log to localStorage
     */
    function saveToStorage() {
        try {
            // Keep only last MAX_REQUESTS
            const toSave = requestLog.slice(-MAX_REQUESTS);
            localStorage.setItem(STORAGE_KEY, JSON.stringify(toSave));
        } catch (e) {
            // Ignore quota errors
        }
    }

    /**
     * Log a request start
     * @param {string} url - Request URL
     * @param {string} method - HTTP method
     * @param {string} context - Where the request originated (e.g., 'loadSubforms')
     * @returns {string} Request ID for correlation
     */
    function startRequest(url, method, context) {
        const requestId = generateRequestId();
        const entry = {
            id: requestId,
            url: url,
            method: method || 'GET',
            context: context || 'unknown',
            startTime: Date.now(),
            startTimeISO: new Date().toISOString(),
            status: 'pending',
            endTime: null,
            duration: null,
            httpStatus: null,
            error: null,
            responseSize: null
        };

        requestLog.push(entry);
        saveToStorage();

        // Log to console in dev mode
        if (window.CMA_CONSOLE_LOGGING || window.CMA_DEBUG) {
            // console.log('[RequestTracker] START', requestId, context, url);
        }

        return requestId;
    }

    /**
     * Log a request completion
     * @param {string} requestId - Request ID from startRequest
     * @param {boolean} success - Whether the request succeeded
     * @param {object} details - Additional details (httpStatus, error, responseSize)
     */
    function endRequest(requestId, success, details) {
        const entry = requestLog.find(r => r.id === requestId);
        if (!entry) {
            console.warn('[RequestTracker] Unknown request ID:', requestId);
            return;
        }

        entry.endTime = Date.now();
        entry.duration = entry.endTime - entry.startTime;
        entry.status = success ? 'success' : 'failed';

        if (details) {
            entry.httpStatus = details.httpStatus;
            entry.error = details.error;
            entry.responseSize = details.responseSize;
        }

        saveToStorage();

        // Always log failures, log successes only in debug mode
        if (!success) {
            cmaLog.error('[RequestTracker] FAILED', requestId, entry.context, entry.url,
                'after', entry.duration + 'ms', details?.error || '');

            // Report to error handler if available
            if (window.CMAErrorHandler && typeof window.CMAErrorHandler.report === 'function') {
                window.CMAErrorHandler.report({
                    type: 'RequestFailed',
                    message: `${entry.context}: Request failed after ${entry.duration}ms`,
                    url: entry.url,
                    details: details?.error || 'No error details'
                });
            }
        } else if (window.CMA_CONSOLE_LOGGING || window.CMA_DEBUG) {
            // console.log('[RequestTracker] END', requestId, entry.context,
            //     entry.duration + 'ms', 'status:', details?.httpStatus);
        }
    }

    /**
     * Get all pending requests (not yet completed)
     * @returns {Array} Pending requests
     */
    function getPendingRequests() {
        return requestLog.filter(r => r.status === 'pending');
    }

    /**
     * Get failed requests from recent history
     * @param {number} minutes - How far back to look (default 5 minutes)
     * @returns {Array} Failed requests
     */
    function getFailedRequests(minutes) {
        const cutoff = Date.now() - (minutes || 5) * 60 * 1000;
        return requestLog.filter(r => r.status === 'failed' && r.startTime > cutoff);
    }

    /**
     * Get request statistics
     * @returns {object} Statistics
     */
    function getStats() {
        const now = Date.now();
        const last5min = requestLog.filter(r => r.startTime > now - 5 * 60 * 1000);

        return {
            total: requestLog.length,
            pending: requestLog.filter(r => r.status === 'pending').length,
            failed: requestLog.filter(r => r.status === 'failed').length,
            last5min: {
                total: last5min.length,
                failed: last5min.filter(r => r.status === 'failed').length,
                avgDuration: last5min.length > 0
                    ? Math.round(last5min.reduce((sum, r) => sum + (r.duration || 0), 0) / last5min.length)
                    : 0
            }
        };
    }

    /**
     * Clear the request log
     */
    function clearLog() {
        requestLog = [];
        localStorage.removeItem(STORAGE_KEY);
    }

    /**
     * Dump request log to console (for debugging)
     */
    function dumpLog() {
        console.table(requestLog.slice(-20));
        // console.log('Stats:', getStats());
    }

    // Initialize
    loadFromStorage();

    // Export to global namespace
    window.CMA = window.CMA || {};
    window.CMA.requestTracker = {
        start: startRequest,
        end: endRequest,
        getPending: getPendingRequests,
        getFailed: getFailedRequests,
        getStats: getStats,
        clear: clearLog,
        dump: dumpLog
    };

    // Also expose for debugging
    window.cmaRequestTracker = window.CMA.requestTracker;

})();
