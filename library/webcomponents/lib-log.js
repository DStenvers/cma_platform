/**
 * LibLog - Unified Logging System
 *
 * A centralized logging system that:
 * - Intercepts console.* calls and routes through LibLog
 * - Sends logs to server for database storage
 * - Respects debug mode (CMA_DEBUG) for console output
 * - Works across library.js and CMA contexts
 *
 * Usage:
 *   LibLog.info('User logged in', { userId: 123 });
 *   LibLog.warning('Slow query detected', { ms: 500 });
 *   LibLog.error('Failed to save', { error: err.message });
 *
 * Configuration:
 *   window.LIBLOG_CONFIG = {
 *     apiEndpoint: '/cma/api/log.php',  // Server endpoint
 *     sendToServer: true,                // Enable server logging
 *     batchSize: 10,                     // Batch size before flush
 *     flushInterval: 5000,               // Auto-flush interval (ms)
 *     interceptConsole: true,            // Replace console.* methods
 *   };
 */
(function(global) {
    'use strict';

    // Read user console logging preference from cookie
    function getCookie(name) {
        var cookies = document.cookie.split(';');
        for (var i = 0; i < cookies.length; i++) {
            var cookie = cookies[i].trim();
            if (cookie.indexOf(name + '=') === 0) {
                return cookie.substring(name.length + 1);
            }
        }
        return null;
    }

    // Console logging preference from cookie (set in CMA preferences)
    var consoleLoggingEnabled = (function() {
        var cookieVal = getCookie('cma_debug_mode');
        if (cookieVal !== null) {
            return cookieVal === 'J';
        }
        // Default: use environment-based detection
        return global.CMA_DEBUG || false;
    })();

    // Default configuration
    var config = {
        apiEndpoint: '/cma/api/log.php',
        sendToServer: true,
        batchSize: 10,
        flushInterval: 5000,
        interceptConsole: true,
        minLevelForServer: 'error', // Only send errors to server by default (performance)
        debugMode: consoleLoggingEnabled // Respect user preference
    };

    // Merge user config if available
    if (global.LIBLOG_CONFIG) {
        for (var key in global.LIBLOG_CONFIG) {
            if (global.LIBLOG_CONFIG.hasOwnProperty(key)) {
                config[key] = global.LIBLOG_CONFIG[key];
            }
        }
    }

    // Expose console logging setting for other scripts
    global.CMA_CONSOLE_LOGGING = consoleLoggingEnabled;

    // Log levels with priorities (lower = more severe)
    var LEVELS = {
        error: { priority: 0, consoleMethod: 'error', color: '#dc3545' },
        warning: { priority: 1, consoleMethod: 'warn', color: '#f59e0b' },
        info: { priority: 2, consoleMethod: 'info', color: '#077ab2' },
        debug: { priority: 3, consoleMethod: 'log', color: '#6c757d' }
    };

    // Level priority lookup for filtering
    var levelPriority = {
        error: 0,
        warning: 1,
        info: 2,
        debug: 3
    };

    // Store original console methods before interception
    var originalConsole = {
        log: console.log.bind(console),
        info: console.info.bind(console),
        warn: console.warn.bind(console),
        error: console.error.bind(console),
        debug: console.debug ? console.debug.bind(console) : console.log.bind(console)
    };

    // Log buffer for batching
    var logBuffer = [];
    var flushTimer = null;
    var isFlushing = false;

    /**
     * Generate unique request ID for correlation
     */
    function generateRequestId() {
        return 'r' + Math.random().toString(36).substr(2, 8);
    }

    var requestId = generateRequestId();

    /**
     * Get current timestamp in ISO format with milliseconds
     */
    function getTimestamp() {
        var now = new Date();
        return now.toISOString();
    }

    /**
     * Get source info from stack trace
     */
    function getSource() {
        try {
            var stack = new Error().stack;
            if (!stack) return 'unknown';

            var lines = stack.split('\n');
            // Find first line that's not from lib-log.js
            for (var i = 2; i < lines.length; i++) {
                var line = lines[i];
                if (line.indexOf('lib-log.js') === -1 &&
                    line.indexOf('LibLog') === -1) {
                    // Extract file:line from stack
                    var match = line.match(/(?:at\s+)?(?:.*?\s+\()?(.+?):(\d+)(?::\d+)?\)?$/);
                    if (match) {
                        var file = match[1].split('/').pop();
                        return file + ':' + match[2];
                    }
                }
            }
        } catch (e) {
            // Ignore errors in stack trace parsing
        }
        return 'unknown';
    }

    /**
     * Convert arguments to a loggable object
     */
    function argsToData(args) {
        if (args.length === 0) return null;
        if (args.length === 1) {
            return typeof args[0] === 'object' ? args[0] : { message: String(args[0]) };
        }

        // Multiple args - first is message, rest is context
        var result = { message: String(args[0]) };
        if (args.length === 2 && typeof args[1] === 'object') {
            // Second arg is context object
            for (var key in args[1]) {
                if (args[1].hasOwnProperty(key)) {
                    result[key] = args[1][key];
                }
            }
        } else {
            // Multiple values - store as array
            result.args = Array.prototype.slice.call(args, 1);
        }
        return result;
    }

    /**
     * Should this level be sent to server?
     * Re-reads debug mode cookie to catch preference changes without page refresh.
     */
    function shouldSendToServer(level) {
        if (!config.sendToServer) return false;

        // Re-read debug mode from cookie to catch preference changes
        // (Preferences may have been saved since page load)
        var currentDebugMode = (function() {
            var cookieVal = getCookie('cma_debug_mode');
            if (cookieVal !== null) {
                return cookieVal === 'J';
            }
            return config.debugMode;
        })();

        // When debug mode is OFF, only send errors to server (not debug/info/warning)
        // This ensures turning off debug mode actually stops most server logging
        if (!currentDebugMode && level !== 'error') {
            return false;
        }

        var minPriority = levelPriority[config.minLevelForServer] || 2;
        var levelPri = levelPriority[level] || 3;
        return levelPri <= minPriority;
    }

    /**
     * Core logging function
     */
    function log(level, args) {
        var levelConfig = LEVELS[level] || LEVELS.info;

        // Output to console (respects debug mode for non-errors)
        if (level === 'error' || config.debugMode) {
            // Use styled output in browsers that support it
            if (typeof window !== 'undefined' && originalConsole[levelConfig.consoleMethod]) {
                originalConsole[levelConfig.consoleMethod].apply(console, args);
            }
        }

        // Build log entry
        var entry = {
            timestamp: getTimestamp(),
            level: level,
            source: getSource(),
            requestId: requestId,
            data: argsToData(args)
        };

        // Notify error handler for errors (always) and warnings (only when console logging enabled)
        // Errors always show in panel - important for debugging production issues
        // Warnings only show when user has enabled console logging
        var showInPanel = level === 'error' || (level === 'warning' && config.debugMode);
        if (showInPanel && typeof window !== 'undefined' && window.CmaErrorHandler) {
            var message = args.length > 0 ? String(args[0]) : 'Unknown error';
            if (args.length > 1 && typeof args[1] === 'object') {
                // Append context info to message
                try {
                    message += ' ' + JSON.stringify(args[1]);
                } catch (e) {
                    // Ignore stringify errors
                }
            }
            window.CmaErrorHandler.report(level === 'error' ? 'manual' : 'warning', message, {
                source: entry.source,
                level: level
            });
        }

        // Add to buffer for server
        if (shouldSendToServer(level)) {
            logBuffer.push(entry);

            // Auto-flush if buffer is full
            if (logBuffer.length >= config.batchSize) {
                flush();
            } else {
                // Start flush timer if not running
                scheduleFlush();
            }
        }

        return entry;
    }

    /**
     * Schedule auto-flush
     */
    function scheduleFlush() {
        if (flushTimer || !config.sendToServer) return;

        flushTimer = setTimeout(function() {
            flushTimer = null;
            flush();
        }, config.flushInterval);
    }

    /**
     * Flush log buffer to server
     */
    function flush() {
        if (isFlushing || logBuffer.length === 0) return;

        // Get current buffer and clear it
        var entries = logBuffer.slice();
        logBuffer = [];
        isFlushing = true;

        // Send to server
        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.apiEndpoint + '?type=debug', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                isFlushing = false;
                if (xhr.status !== 200) {
                    // Re-add entries on failure (but limit to prevent infinite growth)
                    if (logBuffer.length < 100) {
                        logBuffer = entries.concat(logBuffer);
                    }
                }
            }
        };

        xhr.send(JSON.stringify({
            source: 'LibLog',
            entries: entries
        }));
    }

    /**
     * Intercept console methods
     */
    function interceptConsole() {
        if (!config.interceptConsole) return;

        console.log = function() {
            log('debug', arguments);
        };

        console.info = function() {
            log('info', arguments);
        };

        console.warn = function() {
            log('warning', arguments);
        };

        console.error = function() {
            log('error', arguments);
        };

        if (console.debug) {
            console.debug = function() {
                log('debug', arguments);
            };
        }
    }

    /**
     * LibLog public API
     */
    var LibLog = {
        // Version
        version: '1.0.0',

        // Log levels
        LEVELS: LEVELS,

        // Logging methods
        error: function() { return log('error', arguments); },
        warning: function() { return log('warning', arguments); },
        warn: function() { return log('warning', arguments); }, // Alias
        info: function() { return log('info', arguments); },
        debug: function() { return log('debug', arguments); },
        log: function() { return log('debug', arguments); }, // Alias

        // Force flush
        flush: flush,

        // Get current request ID
        getRequestId: function() { return requestId; },

        // Reconfigure
        configure: function(newConfig) {
            for (var key in newConfig) {
                if (newConfig.hasOwnProperty(key)) {
                    config[key] = newConfig[key];
                }
            }
            // Update debug mode
            config.debugMode = global.CMA_DEBUG || config.debugMode;
        },

        // Access original console (for when you really need it)
        console: originalConsole,

        // Check if debug mode is enabled
        isDebug: function() {
            return config.debugMode;
        },

        // Alias for cmaLog compatibility
        isEnabled: function() {
            return config.debugMode;
        },

        // Enable/disable debug mode at runtime
        setDebug: function(enabled) {
            config.debugMode = !!enabled;
            global.CMA_DEBUG = config.debugMode;
            global.CMA_CONSOLE_LOGGING = config.debugMode;
        },

        // Re-read debug mode from cookie (call after preferences are saved)
        refreshFromCookie: function() {
            var cookieVal = getCookie('cma_debug_mode');
            if (cookieVal !== null) {
                config.debugMode = cookieVal === 'J';
                global.CMA_DEBUG = config.debugMode;
                global.CMA_CONSOLE_LOGGING = config.debugMode;
            }
            return config.debugMode;
        },

        // Get current config (for debugging)
        getConfig: function() {
            return {
                debugMode: config.debugMode,
                sendToServer: config.sendToServer,
                minLevelForServer: config.minLevelForServer,
                interceptConsole: config.interceptConsole
            };
        }
    };

    // Intercept console if configured
    interceptConsole();

    // Flush on page unload
    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', function() {
            if (logBuffer.length > 0) {
                // Use sendBeacon for reliable delivery
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(
                        config.apiEndpoint + '?type=debug',
                        JSON.stringify({ source: 'LibLog', entries: logBuffer })
                    );
                } else {
                    // Synchronous fallback
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', config.apiEndpoint + '?type=debug', false);
                    xhr.setRequestHeader('Content-Type', 'application/json');
                    xhr.send(JSON.stringify({ source: 'LibLog', entries: logBuffer }));
                }
            }
        });
    }

    // Export
    global.LibLog = LibLog;

    // Also provide as window.libLog for backward compatibility with library.js
    global.libLog = LibLog;

    // Also provide as window.cmaLog for CMA compatibility
    global.cmaLog = LibLog;

})(typeof window !== 'undefined' ? window : global);
