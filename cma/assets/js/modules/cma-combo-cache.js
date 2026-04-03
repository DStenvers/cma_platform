/**
 * Client-side combo cache using sessionStorage
 * Reduces server load by caching combo options locally for the session
 *
 * @module cma-combo-cache
 */

// Use cmaLog if available, otherwise fallback
const log = window.cmaLog || { log: () => {}, warn: console.warn, error: console.error };

const cmaComboCache = (function() {
    const CACHE_PREFIX = 'cma_combo_';
    const CACHE_TTL = 5 * 60 * 1000; // 5 minutes in milliseconds
    const CACHE_VERSION_KEY = 'cma_combo_version';
    const CACHE_VERSION = '2'; // Increment to invalidate all caches

    /**
     * Check if sessionStorage is available
     */
    function isAvailable() {
        try {
            const test = '__cma_test__';
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

        const keysToRemove = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key && key.indexOf(CACHE_PREFIX) === 0) {
                keysToRemove.push(key);
            }
        }

        keysToRemove.forEach(function(key) {
            sessionStorage.removeItem(key);
        });

        log.log('[ComboCache] Cleared all caches (' + keysToRemove.length + ' entries)');
    }

    /**
     * Check and clear cache if version changed
     */
    function checkVersion() {
        if (!isAvailable()) return;
        const storedVersion = sessionStorage.getItem(CACHE_VERSION_KEY);
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
        buildKey(formId, field, recordId) {
            let key = CACHE_PREFIX + formId + '_' + field;
            if (recordId !== null && recordId !== undefined && recordId !== '') {
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
        get(formId, field, recordId) {
            if (!isAvailable()) return null;

            const key = this.buildKey(formId, field, recordId);
            try {
                const cached = sessionStorage.getItem(key);
                if (!cached) return null;

                const data = JSON.parse(cached);
                if (Date.now() > data.expires) {
                    sessionStorage.removeItem(key);
                    return null;
                }

                log.log('[ComboCache] HIT:', field, recordId ? '(record:' + recordId + ')' : '');
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
        getMultiple(formId, fields, recordId) {
            const result = { cached: {}, uncached: [] };

            for (let i = 0; i < fields.length; i++) {
                const field = fields[i];
                const options = this.get(formId, field, recordId);
                if (options !== null) {
                    result.cached[field] = options;
                } else {
                    result.uncached.push(field);
                }
            }

            if (Object.keys(result.cached).length > 0) {
                log.log('[ComboCache] Batch hit:', Object.keys(result.cached).length, 'cached,', result.uncached.length, 'uncached');
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
        set(formId, field, options, recordId) {
            if (!isAvailable()) return;

            const key = this.buildKey(formId, field, recordId);
            try {
                const data = {
                    options: options,
                    expires: Date.now() + CACHE_TTL
                };
                sessionStorage.setItem(key, JSON.stringify(data));
                log.log('[ComboCache] SET:', field, '(' + options.length + ' options)', recordId ? '(record:' + recordId + ')' : '');
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
        setMultiple(formId, combos, recordId) {
            for (const field in combos) {
                if (combos.hasOwnProperty(field)) {
                    this.set(formId, field, combos[field], recordId);
                }
            }
        },

        /**
         * Invalidate cache for a specific form
         * @param {string} formId - Form ID
         */
        invalidateForm(formId) {
            if (!isAvailable()) return;

            const prefix = CACHE_PREFIX + formId + '_';
            const keysToRemove = [];

            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.indexOf(prefix) === 0) {
                    keysToRemove.push(key);
                }
            }

            keysToRemove.forEach(function(key) {
                sessionStorage.removeItem(key);
            });

            log.log('[ComboCache] Invalidated form:', formId, '(' + keysToRemove.length + ' entries)');
        },

        /**
         * Clear all combo caches
         */
        clear: clearCache,

        /**
         * Remove expired entries to free up space
         */
        cleanup() {
            if (!isAvailable()) return;

            const now = Date.now();
            const keysToRemove = [];

            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0) {
                    try {
                        const data = JSON.parse(sessionStorage.getItem(key));
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
                log.log('[ComboCache] Cleanup removed', keysToRemove.length, 'expired entries');
            }
        },

        /**
         * Get cache statistics
         */
        stats() {
            if (!isAvailable()) return { entries: 0, size: 0 };

            let entries = 0;
            let size = 0;

            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
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

// Attach to window for backward compatibility
if (typeof window !== 'undefined') {
    window.cmaComboCache = cmaComboCache;
}

export default cmaComboCache;
