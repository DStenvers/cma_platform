/**
 * API Error Handler - processes fetch responses and displays detailed errors
 *
 * Handles:
 * - HTTP error status codes (4xx, 5xx)
 * - Non-JSON responses (shows raw text)
 * - JSON error responses with debug info
 * - Network errors
 *
 * @module cma-api-error
 */

// Use cmaLog if available, otherwise fallback to console
const log = window.cmaLog || { log: () => {}, warn: console.warn, error: console.error };

const cmaApiError = {
    /**
     * Extract PHP error from HTML comment marker
     * Delegates to shared cmaErrorParser utility
     * @param {string} html - HTML response text
     * @returns {object|null} - Parsed error info or null
     */
    extractPhpError(html) {
        // Use shared utility from cma-utils.js
        return window.cmaErrorParser ? window.cmaErrorParser.extract(html) : null;
    },

    /**
     * Process a fetch response and return JSON data or throw detailed error
     * @param {Response} response - Fetch API response
     * @param {string} context - Description of what was being done (for error messages)
     * @returns {Promise<object>} - Parsed JSON data
     * @throws {Error} - Detailed error with debug info if available
     */
    async handleResponse(response, context = 'API call') {
        // Check if response is OK
        if (!response.ok) {
            // Try to parse error response
            const contentType = response.headers.get('content-type') || '';

            if (contentType.includes('application/json')) {
                // JSON error response (from our improved ErrorHandler)
                try {
                    const errorData = await response.json();
                    const error = new Error(errorData.error || `HTTP ${response.status}`);
                    error.statusCode = response.status;
                    error.errorType = errorData.errorType;
                    error.debug = errorData.debug;
                    throw error;
                } catch (parseError) {
                    if (parseError.statusCode) throw parseError; // Re-throw our error
                    throw new Error(`${context}: HTTP ${response.status} ${response.statusText}`);
                }
            } else {
                // Non-JSON response (HTML error or compile error)
                let responseText = '';
                try {
                    responseText = await response.text();
                } catch (e) {
                    log.error('[cmaApiError] Failed to read response body:', e.message);
                    responseText = '[Response body unreadable]';
                }

                // Debug: log raw response for troubleshooting
                log.error('[cmaApiError] Non-JSON response received. Status:', response.status);
                log.error('[cmaApiError] Response text (first 1000 chars):', responseText.substring(0, 1000));

                // Try to extract PHP error from HTML comment
                const phpError = this.extractPhpError(responseText);
                if (phpError) {
                    log.error('[cmaApiError] Extracted PHP error:', phpError);
                    const error = new Error(phpError.message);
                    error.statusCode = response.status;
                    error.errorType = phpError.type;
                    error.debug = {
                        file: phpError.file,
                        line: phpError.line
                    };
                    throw error;
                }

                log.error('[cmaApiError] Could not extract PHP error from response');
                const error = new Error(`${context}: HTTP ${response.status} ${response.statusText}`);
                error.statusCode = response.status;
                error.responseText = responseText;
                throw error;
            }
        }

        // Response OK - parse JSON
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            // Server returned non-JSON (might be HTML error page)
            let responseText = '';
            try {
                responseText = await response.text();
            } catch (e) {
                log.error('[cmaApiError] Failed to read non-JSON response body:', e.message);
                responseText = '[Response body unreadable]';
            }

            // Try to extract PHP error from HTML comment
            const phpError = this.extractPhpError(responseText);
            if (phpError) {
                const error = new Error(phpError.message);
                error.statusCode = 200; // Response was OK but content was wrong
                error.errorType = phpError.type;
                error.debug = {
                    file: phpError.file,
                    line: phpError.line
                };
                throw error;
            }

            const error = new Error(`${context}: Server returned non-JSON response`);
            error.responseText = responseText;
            throw error;
        }

        return response.json();
    },

    /**
     * Format an error for display (includes debug info in dev mode)
     * @param {Error} error - Error object (may have debug property)
     * @returns {string} - Formatted error message
     */
    formatError(error) {
        let message = error.message || 'Onbekende fout';

        // In debug mode, add detailed info
        const isDebug = typeof CMA_DEBUG !== 'undefined' && CMA_DEBUG;
        if (isDebug && error.debug) {
            const debug = error.debug;
            const details = [];

            if (debug.file) {
                const shortFile = debug.file.split(/[\/\\]/).slice(-3).join('/');
                details.push(`Bestand: ${shortFile}:${debug.line || '?'}`);
            }

            if (debug.diagnostics) {
                const diag = debug.diagnostics;
                if (diag.likelyCauses && diag.likelyCauses.length > 0) {
                    details.push('Mogelijke oorzaak: ' + diag.likelyCauses[0]);
                }
            }

            if (details.length > 0) {
                message += '\n\n' + details.join('\n');
            }
        }

        return message;
    },

    /**
     * Show an API error in the UI and console
     * @param {Error} error - Error object
     * @param {string} context - Context description
     */
    showError(error, context = '') {
        const formattedMessage = this.formatError(error);

        // Log detailed error to console
        log.error(`[API Error] ${context}:`, error.message);
        if (error.debug) {
            log.error('Debug info:', error.debug);
        }
        if (error.responseText) {
            log.error('Response text (first 500 chars):', error.responseText.substring(0, 500));
        }

        // Report to dev error panel
        if (typeof CmaErrorHandler !== 'undefined') {
            CmaErrorHandler.reportServerError('API', `${context}: ${error.message}`, {
                url: window.location.href,
                debug: error.debug || null,
                statusCode: error.statusCode,
                errorType: error.errorType
            });
        }

        // Build details string for admins/developers
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
            if (error.responseText) {
                detailParts.push('Response (first 500 chars):\n' + error.responseText.substring(0, 500));
            }
            details = detailParts.join('\n');
        }

        // Show user-facing error via cmaNotification or libMessage
        if (typeof cmaNotification !== 'undefined') {
            cmaNotification.show(formattedMessage, 'error');
        } else if (typeof libMessage !== 'undefined') {
            const options = { type: 'error', closable: true };
            if (details) options.details = details;
            libMessage.create(formattedMessage, options);
        } else {
            alert(formattedMessage);
        }

        return formattedMessage;
    }
};

// Attach to window for backward compatibility
if (typeof window !== 'undefined') {
    window.cmaApiError = cmaApiError;
}

export default cmaApiError;
