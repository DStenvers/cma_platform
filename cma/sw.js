/**
 * CMA Service Worker
 *
 * Provides persistent browser caching for form templates using stale-while-revalidate strategy.
 * Form data is still fetched fresh via form_api.php - only empty templates are cached.
 *
 * Cache invalidation:
 * - Automatic: Version bump clears all caches
 * - Manual: CMA.clearFormCache() from JavaScript
 * - ETag: Server-side form definition changes trigger revalidation
 */

// Version for cache busting - increment to clear all cached templates
const CACHE_VERSION = 'cma-v1';
const FORM_CACHE = `cma-forms-${CACHE_VERSION}`;

// Simple logging helper for Service Worker context
// SW runs in separate context - only log errors to avoid console spam
// Debug logs disabled since SW has no access to preference cookie
const swLog = {
    log: () => {}, // Silent - SW logs not useful for users
    warn: () => {}, // Silent
    error: (...args) => console.error('[SW]', ...args) // Always log errors
};

// Install: Skip waiting to activate immediately
self.addEventListener('install', (event) => {
    swLog.log('Installing, version:', CACHE_VERSION);
    event.waitUntil(self.skipWaiting());
});

// Activate: Delete old version caches and claim clients
self.addEventListener('activate', (event) => {
    swLog.log('Activating, claiming clients');
    event.waitUntil(
        caches.keys()
            .then(keys => {
                const oldCaches = keys.filter(k => k.startsWith('cma-forms-') && k !== FORM_CACHE);
                if (oldCaches.length > 0) {
                    swLog.log('Clearing old caches:', oldCaches);
                }
                return Promise.all(oldCaches.map(k => caches.delete(k)));
            })
            .then(() => self.clients.claim())
    );
});

// Fetch: Cache form templates with stale-while-revalidate strategy
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Only cache form.php requests (empty templates)
    // Must have 'form' parameter to identify it as a form template request
    if (url.pathname.endsWith('/form.php') && url.searchParams.has('form')) {
        const formName = url.searchParams.get('form');

        // Skip caching if nocache parameter is present
        if (url.searchParams.has('nocache')) {
            swLog.log('Skipping cache (nocache param):', formName);
            return;
        }

        swLog.log('Intercepting form request:', formName);
        event.respondWith(staleWhileRevalidate(event.request, formName));
    }
});

/**
 * Stale-While-Revalidate caching strategy
 *
 * 1. Return cached response immediately if available
 * 2. Fetch fresh response in background and update cache
 * 3. If no cache, wait for network response
 * 4. If network fails, fall back to cache
 */
async function staleWhileRevalidate(request, formName) {
    const cache = await caches.open(FORM_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        swLog.log('Cache HIT:', formName);
    } else {
        swLog.log('Cache MISS:', formName);
    }

    // Create a fetch promise that updates the cache
    const fetchPromise = fetch(request)
        .then(response => {
            // Only cache successful responses
            if (response.ok) {
                swLog.log('Caching response:', formName, '(status:', response.status + ')');
                // Clone before caching (response can only be consumed once)
                cache.put(request, response.clone());
            } else if (response.status === 304) {
                swLog.log('Not modified (304):', formName);
            }
            return response;
        })
        .catch(error => {
            // Network error - return cached version if available
            if (cached) {
                swLog.warn('Network error, using cache:', formName);
                return cached;
            }
            swLog.error('Network error, no cache:', formName, error.message);
            throw error;
        });

    // Return cached immediately if available, otherwise wait for fetch
    return cached || fetchPromise;
}

// Message handler for cache control from main thread
self.addEventListener('message', (event) => {
    if (event.data === 'clearFormCache') {
        swLog.log('Clearing form cache:', FORM_CACHE);
        caches.delete(FORM_CACHE).then(() => {
            swLog.log('Form cache cleared');
            // Notify the caller that cache was cleared
            if (event.ports && event.ports[0]) {
                event.ports[0].postMessage({ cleared: true, cache: FORM_CACHE });
            }
        });
    } else if (event.data === 'getCacheInfo') {
        swLog.log('Getting cache info');
        // Return cache info for debugging
        caches.open(FORM_CACHE).then(cache => {
            cache.keys().then(keys => {
                swLog.log('Cache entries:', keys.length);
                if (event.ports && event.ports[0]) {
                    event.ports[0].postMessage({
                        version: CACHE_VERSION,
                        cache: FORM_CACHE,
                        entries: keys.map(k => k.url)
                    });
                }
            });
        });
    }
});
