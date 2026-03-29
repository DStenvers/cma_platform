/**
 * Client-side record data cache using sessionStorage
 *
 * Caches record data for faster repeated access within a session.
 * Uses sessionStorage (not localStorage) because:
 * - Record data can change between sessions
 * - Shorter TTL is appropriate for data that may be edited
 * - Clears automatically when tab/browser closes
 *
 * @module cma-record-cache
 */

const cmaRecordCache = (function() {
    'use strict';
    const _log = window.cmaLog || { log: () => {}, warn: console.warn, error: console.error };

    const CACHE_PREFIX = 'cma_record_';
    const CACHE_TTL = 5 * 60 * 1000; // 5 minutes - short TTL for record data
    const MAX_CACHED_RECORDS = 50; // Limit to avoid storage issues

    /**
     * Check if sessionStorage is available
     */
    function isAvailable() {
        try {
            const test = '__cma_recordcache_test__';
            sessionStorage.setItem(test, test);
            sessionStorage.removeItem(test);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Build cache key for record
     * @param {string} formName - Form name
     * @param {string|number} recordId - Record ID
     * @returns {string} Cache key
     */
    function buildKey(formName, recordId) {
        return CACHE_PREFIX + formName.toLowerCase() + '_' + recordId;
    }

    /**
     * Build cache key for subform
     * @param {string} formName - Form name
     * @param {string|number} parentId - Parent record ID
     * @param {number} subformIndex - Subform index
     * @returns {string} Cache key
     */
    function buildSubformKey(formName, parentId, subformIndex) {
        return CACHE_PREFIX + 'subform_' + formName.toLowerCase() + '_' + parentId + '_' + subformIndex;
    }

    /**
     * Get count of cached records
     */
    function getCacheCount() {
        if (!isAvailable()) return 0;
        let count = 0;
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key && key.indexOf(CACHE_PREFIX) === 0) {
                count++;
            }
        }
        return count;
    }

    /**
     * Remove oldest cache entries to make room
     */
    function pruneOldest() {
        if (!isAvailable()) return;

        const entries = [];
        for (let i = 0; i < sessionStorage.length; i++) {
            const key = sessionStorage.key(i);
            if (key && key.indexOf(CACHE_PREFIX) === 0) {
                try {
                    const data = JSON.parse(sessionStorage.getItem(key));
                    entries.push({ key, cachedAt: data.cachedAt || 0 });
                } catch (e) {
                    // Invalid entry, remove it
                    sessionStorage.removeItem(key);
                }
            }
        }

        // Sort by cachedAt (oldest first) and remove half
        entries.sort((a, b) => a.cachedAt - b.cachedAt);
        const toRemove = Math.ceil(entries.length / 2);
        for (let i = 0; i < toRemove; i++) {
            sessionStorage.removeItem(entries[i].key);
        }

        _log.log('[RecordCache] Pruned', toRemove, 'old entries');
    }

    return {
        /**
         * Get cached record data
         * @param {string} formName - Form name
         * @param {string|number} recordId - Record ID
         * @returns {Object|null} - Cached record data or null if not found/expired
         */
        get(formName, recordId) {
            if (!isAvailable() || !formName || !recordId) return null;

            const key = buildKey(formName, recordId);
            try {
                const cached = sessionStorage.getItem(key);
                if (!cached) return null;

                const data = JSON.parse(cached);

                // Check TTL expiration
                if (data.expires && Date.now() > data.expires) {
                    sessionStorage.removeItem(key);
                    _log.log('[RecordCache] EXPIRED:', formName, recordId);
                    return null;
                }

                _log.log('[RecordCache] HIT:', formName, recordId);
                return data.record;
            } catch (e) {
                _log.warn('[RecordCache] Parse error for', formName, recordId, e);
                return null;
            }
        },

        /**
         * Store record data in cache
         * @param {string} formName - Form name
         * @param {string|number} recordId - Record ID
         * @param {Object} recordData - Record data object (full API response)
         */
        set(formName, recordId, recordData) {
            if (!isAvailable() || !formName || !recordId || !recordData) return;

            // Prune if at limit
            if (getCacheCount() >= MAX_CACHED_RECORDS) {
                pruneOldest();
            }

            const key = buildKey(formName, recordId);
            try {
                const data = {
                    record: recordData,
                    expires: Date.now() + CACHE_TTL,
                    cachedAt: Date.now()
                };
                sessionStorage.setItem(key, JSON.stringify(data));
                _log.log('[RecordCache] SET:', formName, recordId);
            } catch (e) {
                if (e.name === 'QuotaExceededError') {
                    pruneOldest();
                    try {
                        sessionStorage.setItem(key, JSON.stringify({
                            record: recordData,
                            expires: Date.now() + CACHE_TTL,
                            cachedAt: Date.now()
                        }));
                    } catch (retryError) {
                        _log.warn('[RecordCache] Storage full, could not cache:', formName, recordId);
                    }
                } else {
                    _log.warn('[RecordCache] Error caching:', formName, recordId, e);
                }
            }
        },

        /**
         * Invalidate cache for a specific record
         * @param {string} formName - Form name
         * @param {string|number} recordId - Record ID
         */
        invalidate(formName, recordId) {
            if (!isAvailable()) return;
            sessionStorage.removeItem(buildKey(formName, recordId));
            _log.log('[RecordCache] Invalidated:', formName, recordId);
        },

        /**
         * Invalidate all cached records for a form
         * @param {string} formName - Form name
         */
        invalidateForm(formName) {
            if (!isAvailable() || !formName) return;

            const prefix = CACHE_PREFIX + formName.toLowerCase() + '_';
            const keysToRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.indexOf(prefix) === 0) {
                    keysToRemove.push(key);
                }
            }

            keysToRemove.forEach(key => sessionStorage.removeItem(key));
            _log.log('[RecordCache] Invalidated form:', formName, '(' + keysToRemove.length + ' entries)');
        },

        /**
         * Clear all record caches
         */
        clear() {
            if (!isAvailable()) return;

            const keysToRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0) {
                    keysToRemove.push(key);
                }
            }

            keysToRemove.forEach(key => sessionStorage.removeItem(key));
            _log.log('[RecordCache] Cleared all caches (' + keysToRemove.length + ' entries)');
        },

        /**
         * Check if a record is cached
         * @param {string} formName - Form name
         * @param {string|number} recordId - Record ID
         * @returns {boolean} True if cached and not expired
         */
        has(formName, recordId) {
            return this.get(formName, recordId) !== null;
        },

        /**
         * Get cache statistics
         * @returns {Object} Stats object
         */
        stats() {
            if (!isAvailable()) return { entries: 0, size: '0KB' };

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
        },

        // =====================================================================
        // Subform caching methods
        // =====================================================================

        /**
         * Get cached subform data
         * @param {string} formName - Form name
         * @param {string|number} parentId - Parent record ID
         * @param {number} subformIndex - Subform index
         * @returns {Object|null} - Cached subform data or null if not found/expired
         */
        getSubform(formName, parentId, subformIndex) {
            if (!isAvailable() || !formName || !parentId || subformIndex === undefined) return null;

            const key = buildSubformKey(formName, parentId, subformIndex);
            try {
                const cached = sessionStorage.getItem(key);
                if (!cached) return null;

                const data = JSON.parse(cached);

                // Check TTL expiration
                if (data.expires && Date.now() > data.expires) {
                    sessionStorage.removeItem(key);
                    return null;
                }

                _log.log('[RecordCache] Subform HIT:', formName, parentId, 'index', subformIndex);
                return data.subform;
            } catch (e) {
                _log.warn('[RecordCache] Subform parse error:', formName, parentId, subformIndex, e);
                return null;
            }
        },

        /**
         * Store subform data in cache
         * @param {string} formName - Form name
         * @param {string|number} parentId - Parent record ID
         * @param {number} subformIndex - Subform index
         * @param {Object} subformData - Subform data object (full API response)
         */
        setSubform(formName, parentId, subformIndex, subformData) {
            if (!isAvailable() || !formName || !parentId || subformIndex === undefined || !subformData) return;

            // Prune if at limit
            if (getCacheCount() >= MAX_CACHED_RECORDS) {
                pruneOldest();
            }

            const key = buildSubformKey(formName, parentId, subformIndex);
            try {
                const data = {
                    subform: subformData,
                    expires: Date.now() + CACHE_TTL,
                    cachedAt: Date.now()
                };
                sessionStorage.setItem(key, JSON.stringify(data));
                _log.log('[RecordCache] Subform SET:', formName, parentId, 'index', subformIndex);
            } catch (e) {
                if (e.name === 'QuotaExceededError') {
                    pruneOldest();
                    // Don't retry for subforms to avoid quota issues
                }
                _log.warn('[RecordCache] Subform cache error:', formName, parentId, subformIndex, e);
            }
        },

        /**
         * Invalidate all subforms for a parent record
         * @param {string} formName - Form name
         * @param {string|number} parentId - Parent record ID
         */
        invalidateSubforms(formName, parentId) {
            if (!isAvailable() || !formName || !parentId) return;

            const prefix = CACHE_PREFIX + 'subform_' + formName.toLowerCase() + '_' + parentId + '_';
            const keysToRemove = [];
            for (let i = 0; i < sessionStorage.length; i++) {
                const key = sessionStorage.key(i);
                if (key && key.indexOf(prefix) === 0) {
                    keysToRemove.push(key);
                }
            }

            keysToRemove.forEach(key => sessionStorage.removeItem(key));
            if (keysToRemove.length > 0) {
                _log.log('[RecordCache] Invalidated subforms:', formName, parentId, '(' + keysToRemove.length + ' entries)');
            }
        }
    };
})();

// Attach to window for global access
if (typeof window !== 'undefined') {
    window.cmaRecordCache = cmaRecordCache;
}
