/**
 * Client-side form definition cache using localStorage
 *
 * Caches form definitions (JSON) locally to avoid repeated server requests.
 * Uses file modification time hash for cache invalidation.
 *
 * Performance benefits:
 * - Instant form loads after first visit (typically 2-10KB saved per form)
 * - Reduces server load for form definition parsing
 * - Works across browser sessions (unlike sessionStorage)
 *
 * @module cma-form-cache
 */

const log = window.cmaLog || { log: () => {}, warn: console.warn, error: console.error };

const cmaFormCache = (function() {
    'use strict';

    const CACHE_PREFIX = 'cma_formdef_';
    const VERSION_PREFIX = 'cma_formdef_v_';
    const CACHE_TTL = 24 * 60 * 60 * 1000; // 24 hours in milliseconds (fallback if no version check)
    const CACHE_VERSION_KEY = 'cma_formdef_cache_version';
    const CACHE_VERSION = '1'; // Increment to invalidate all cached forms

    /**
     * Check if localStorage is available
     */
    function isAvailable() {
        try {
            const test = '__cma_formcache_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Clear all form definition caches (internal function)
     */
    function clearAllCache() {
        if (!isAvailable()) return;

        const keysToRemove = [];
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key && (key.indexOf(CACHE_PREFIX) === 0 || key.indexOf(VERSION_PREFIX) === 0)) {
                keysToRemove.push(key);
            }
        }

        keysToRemove.forEach(function(key) {
            localStorage.removeItem(key);
        });

        log.log('[FormCache] Cleared all form caches (' + keysToRemove.length + ' entries)');
    }

    /**
     * Check and clear cache if global version changed
     */
    function checkVersion() {
        if (!isAvailable()) return;
        const storedVersion = localStorage.getItem(CACHE_VERSION_KEY);
        if (storedVersion !== CACHE_VERSION) {
            clearAllCache();
            localStorage.setItem(CACHE_VERSION_KEY, CACHE_VERSION);
            log.log('[FormCache] Cache version changed, cleared all entries');
        }
    }

    // Check version on load
    checkVersion();

    /**
     * Build cache key for form definition
     * @param {string} formName - Form name
     * @returns {string} Cache key
     */
    function buildKey(formName) {
        return CACHE_PREFIX + formName.toLowerCase();
    }

    /**
     * Build version key for form definition
     * @param {string} formName - Form name
     * @returns {string} Version key
     */
    function buildVersionKey(formName) {
        return VERSION_PREFIX + formName.toLowerCase();
    }

    return {
        /**
         * Get cached form definition
         * @param {string} formName - Form name
         * @returns {Object|null} - Cached definition or null if not found/expired
         */
        get(formName) {
            if (!isAvailable()) return null;

            const key = buildKey(formName);
            try {
                const cached = localStorage.getItem(key);
                if (!cached) return null;

                const data = JSON.parse(cached);

                // Check TTL expiration (fallback if version check wasn't done)
                if (data.expires && Date.now() > data.expires) {
                    localStorage.removeItem(key);
                    localStorage.removeItem(buildVersionKey(formName));
                    log.log('[FormCache] EXPIRED:', formName);
                    return null;
                }

                log.log('[FormCache] HIT:', formName);
                return data.definition;
            } catch (e) {
                log.warn('[FormCache] Parse error for', formName, e);
                return null;
            }
        },

        /**
         * Get cached form definition with version check
         * Returns cached data only if version matches
         * @param {string} formName - Form name
         * @param {string} serverVersion - Server-side version hash
         * @returns {Object|null} - Cached definition or null if version mismatch
         */
        getIfVersionMatch(formName, serverVersion) {
            if (!isAvailable() || !serverVersion) return null;

            const versionKey = buildVersionKey(formName);
            const cachedVersion = localStorage.getItem(versionKey);

            if (cachedVersion !== serverVersion) {
                log.log('[FormCache] Version mismatch:', formName, 'cached:', cachedVersion, 'server:', serverVersion);
                return null;
            }

            return this.get(formName);
        },

        /**
         * Store form definition in cache
         * @param {string} formName - Form name
         * @param {Object} definition - Form definition object
         * @param {string} version - Server-side version hash
         */
        set(formName, definition, version) {
            if (!isAvailable()) return;

            const key = buildKey(formName);
            const versionKey = buildVersionKey(formName);

            try {
                const data = {
                    definition: definition,
                    expires: Date.now() + CACHE_TTL,
                    cachedAt: new Date().toISOString()
                };
                localStorage.setItem(key, JSON.stringify(data));

                if (version) {
                    localStorage.setItem(versionKey, version);
                }

                const size = JSON.stringify(data).length;
                log.log('[FormCache] SET:', formName, '(' + Math.round(size / 1024) + 'KB)', version ? 'v:' + version.substring(0, 8) : '');
            } catch (e) {
                if (e.name === 'QuotaExceededError') {
                    // Storage full - cleanup old entries and retry
                    this.cleanup();
                    try {
                        localStorage.setItem(key, JSON.stringify({
                            definition: definition,
                            expires: Date.now() + CACHE_TTL
                        }));
                        if (version) {
                            localStorage.setItem(versionKey, version);
                        }
                    } catch (retryError) {
                        log.warn('[FormCache] Storage full, could not cache:', formName);
                    }
                } else {
                    log.warn('[FormCache] Error caching:', formName, e);
                }
            }
        },

        /**
         * Get stored version for a form
         * @param {string} formName - Form name
         * @returns {string|null} - Cached version or null
         */
        getVersion(formName) {
            if (!isAvailable()) return null;
            return localStorage.getItem(buildVersionKey(formName));
        },

        /**
         * Invalidate cache for a specific form
         * @param {string} formName - Form name
         */
        invalidate(formName) {
            if (!isAvailable()) return;

            localStorage.removeItem(buildKey(formName));
            localStorage.removeItem(buildVersionKey(formName));
            log.log('[FormCache] Invalidated:', formName);
        },

        /**
         * Clear all form definition caches
         */
        clear: clearAllCache,

        /**
         * Remove expired entries to free up space
         */
        cleanup() {
            if (!isAvailable()) return;

            const now = Date.now();
            const keysToRemove = [];

            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0) {
                    try {
                        const data = JSON.parse(localStorage.getItem(key));
                        if (data.expires && now > data.expires) {
                            keysToRemove.push(key);
                            // Also remove version key
                            const formName = key.substring(CACHE_PREFIX.length);
                            keysToRemove.push(buildVersionKey(formName));
                        }
                    } catch (e) {
                        keysToRemove.push(key);
                    }
                }
            }

            keysToRemove.forEach(function(key) {
                localStorage.removeItem(key);
            });

            if (keysToRemove.length > 0) {
                log.log('[FormCache] Cleanup removed', keysToRemove.length, 'expired entries');
            }
        },

        /**
         * Get cache statistics
         * @returns {Object} Stats object with entries count and size
         */
        stats() {
            if (!isAvailable()) return { entries: 0, size: '0KB', forms: [] };

            let entries = 0;
            let size = 0;
            const forms = [];

            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0) {
                    entries++;
                    const itemSize = localStorage.getItem(key).length;
                    size += itemSize;
                    forms.push({
                        name: key.substring(CACHE_PREFIX.length),
                        size: Math.round(itemSize / 1024) + 'KB'
                    });
                }
            }

            return {
                entries: entries,
                size: Math.round(size / 1024) + 'KB',
                forms: forms
            };
        },

        /**
         * Check if a form is cached
         * @param {string} formName - Form name
         * @returns {boolean} True if cached
         */
        has(formName) {
            if (!isAvailable()) return false;
            return localStorage.getItem(buildKey(formName)) !== null;
        },

        /**
         * Preload multiple form definitions
         * Useful for preloading commonly used forms
         * @param {Array<string>} formNames - Array of form names to preload
         * @param {Function} fetchFn - Async function to fetch form definition: (formName) => Promise<{definition, version}>
         * @returns {Promise<Object>} Results object with success/failure counts
         */
        async preload(formNames, fetchFn) {
            const results = { loaded: 0, cached: 0, failed: 0 };

            for (const formName of formNames) {
                if (this.has(formName)) {
                    results.cached++;
                    continue;
                }

                try {
                    const result = await fetchFn(formName);
                    if (result && result.definition) {
                        this.set(formName, result.definition, result.version);
                        results.loaded++;
                    } else {
                        results.failed++;
                    }
                } catch (e) {
                    log.warn('[FormCache] Preload failed for:', formName, e);
                    results.failed++;
                }
            }

            log.log('[FormCache] Preload complete:', results);
            return results;
        }
    };
})();

// Attach to window for global access
if (typeof window !== 'undefined') {
    window.cmaFormCache = cmaFormCache;
}
