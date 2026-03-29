/**
 * CMA Utility Modules
 * Central export point for all extracted utility modules
 *
 * @module cma-modules
 */

export { default as cmaApiError } from './cma-api-error.js';
export { default as cmaPerf } from './cma-perf.js';
export { default as cmaComboCache } from './cma-combo-cache.js';
export { default as cmaRequestCoalescer } from './cma-request-coalescer.js';
export { default as cmaNotification } from './cma-notification.js';

// Also attach to CMA namespace if available
if (typeof window !== 'undefined' && window.CMA) {
    window.CMA.modules = {
        apiError: window.cmaApiError,
        perf: window.cmaPerf,
        comboCache: window.cmaComboCache,
        requestCoalescer: window.cmaRequestCoalescer,
        notification: window.cmaNotification
    };
}
