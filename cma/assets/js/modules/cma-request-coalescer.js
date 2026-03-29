/**
 * REQUEST COALESCER - Prevents duplicate in-flight requests
 * If same URL is requested while a request is in-flight, returns same promise
 *
 * @module cma-request-coalescer
 */

// Use cmaLog if available, otherwise fallback
const log = window.cmaLog || { log: () => {}, warn: console.warn, error: console.error };

const cmaRequestCoalescer = (function() {
    // Map of URL -> { promise, timestamp }
    const inFlight = new Map();
    const MAX_AGE = 5000; // Max time to keep a coalesced request (5 seconds)

    return {
        /**
         * Fetch with request coalescing
         * @param {string} url - URL to fetch
         * @param {object} options - Fetch options (optional)
         * @returns {Promise<{response: Response, data: any}>} - Response with parsed data
         */
        fetch(url, options) {
            const key = url + (options ? JSON.stringify(options) : '');

            // Check if we have an in-flight request for this URL
            const existing = inFlight.get(key);
            if (existing && (Date.now() - existing.timestamp) < MAX_AGE) {
                log.log('[Coalescer] Reusing in-flight request:', url.substring(0, 60));
                if (typeof cmaPerf !== 'undefined') {
                    cmaPerf.count('requestCoalescer.coalesced');
                }
                return existing.promise;
            }

            // Create new request with proper error handling
            const promise = fetch(url, options)
                .then(function(response) {
                    if (!response.ok) {
                        const error = new Error('[Coalescer] HTTP ' + response.status + ' ' + response.statusText);
                        error.statusCode = response.status;
                        throw error;
                    }
                    return response.json().then(function(data) {
                        return { response: response, data: data };
                    }).catch(function(parseError) {
                        log.error('[Coalescer] JSON parse error:', parseError.message);
                        throw new Error('[Coalescer] Invalid JSON response from server');
                    });
                })
                .catch(function(error) {
                    log.error('[Coalescer] Request failed:', url.substring(0, 60), error.message);
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
            if (typeof cmaPerf !== 'undefined') {
                cmaPerf.count('requestCoalescer.newRequest');
            }

            return promise;
        },

        /**
         * Clear all in-flight requests
         */
        clear() {
            inFlight.clear();
        },

        /**
         * Get stats
         */
        stats() {
            return { inFlight: inFlight.size };
        }
    };
})();

// Attach to window for backward compatibility
if (typeof window !== 'undefined') {
    window.cmaRequestCoalescer = cmaRequestCoalescer;
}

export default cmaRequestCoalescer;
