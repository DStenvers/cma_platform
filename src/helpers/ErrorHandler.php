<?php
namespace App\Library;

class ErrorHandler
{
    /** @var array Error handler configuration */
    protected static $config = [
        'debug' => false,
        'environment' => 'production',
        'display_errors' => false,
        'log_errors' => true,
        'error_log_file' => null,
        'error_view' => null,
        'error_handling_level' => E_ALL,
        'app_name' => 'PHP Application',
        'app_url' => '',
        'exclude_paths' => ['vendor/']
    ];

    /** @var array Colors for terminal output */
    private static $colors = [
        'reset' => "\033[0m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'magenta' => "\033[35m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'bold' => "\033[1m"
    ];

    /** @var array HTTP status codes */
    private static $statusCodes = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    ];

    /**
     * Generate HTML comment with error summary for automated testing tools
     *
     * Format: <!-- [PHP_ERROR] Type: ... | Message: ... | File: ... | Line: ... [/PHP_ERROR] -->
     *
     * @param \Throwable $exception The exception to summarize
     * @return string HTML comment containing error details
     */
    protected static function generateErrorComment(\Throwable $exception): string
    {
        $type = get_class($exception);
        $message = str_replace(['--', "\n", "\r"], ['- -', ' ', ' '], $exception->getMessage());
        $file = $exception->getFile();
        $line = $exception->getLine();

        return "<!-- [PHP_ERROR] Type: {$type} | Message: {$message} | File: {$file} | Line: {$line} [/PHP_ERROR] -->\n";
    }

    /**
     * Set verbose/debug mode for error display
     *
     * @param bool $enabled True to enable verbose output, false to disable
     * @return void
     */
    public static function setVerbose(bool $enabled): void
    {
        self::$config['debug'] = $enabled;
        self::$config['display_errors'] = $enabled;
        ini_set('display_errors', $enabled ? '1' : '0');
    }

    /**
     * Register the error handler
     *
     * @param array $config Configuration options
     * @return void
     */
    public static function register(array $config = []): void
    {
        // Auto-detect environment and app name from $GLOBALS['Application'] if available
        if (isset($GLOBALS['Application'])) {
            $environment = $GLOBALS['Application']['Omgeving'] ?? $GLOBALS['Application']['omgeving'] ?? 'P';

            // Set debug mode for Local (L/O) or Test (T) environments
            $config['debug'] = $config['debug'] ?? in_array(strtoupper($environment), ['L', 'O', 'T']);
            $config['display_errors'] = $config['display_errors'] ?? in_array(strtoupper($environment), ['L', 'O', 'T']);
            $config['environment'] = strtoupper($environment);

            // Use app name from Application config
            $config['app_name'] = $config['app_name'] ??
                ($GLOBALS['Application']['appname_simple'] ?? $GLOBALS['Application']['appname'] ?? 'PHP Application');

            // On production, exclude E_DEPRECATED to avoid deprecated warnings breaking pages
            if (!($config['debug'] ?? false)) {
                $config['error_handling_level'] = $config['error_handling_level'] ?? (E_ALL & ~E_DEPRECATED);
            }
        }

        // Merge custom configuration with defaults
        self::$config = array_merge(self::$config, $config);

        // Start output buffering to help prevent headers being sent prematurely
        if (!self::$config['debug'] && PHP_SAPI !== 'cli' && !ob_get_level()) {
            ob_start();
        }

        // Create error log directory if it doesn't exist
        if (self::$config['log_errors'] && self::$config['error_log_file']) {
            $logDir = dirname(self::$config['error_log_file']);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
        }

        // Set PHP error reporting level
        error_reporting(self::$config['error_handling_level']);

        // Set PHP display_errors directive
        ini_set('display_errors', self::$config['display_errors'] ? '1' : '0');

        // Set PHP error_log directive if custom log file is specified
        if (self::$config['log_errors'] && self::$config['error_log_file']) {
            ini_set('error_log', self::$config['error_log_file']);
        }

        // Register error handler
        set_error_handler([self::class, 'handleError'], self::$config['error_handling_level']);

        // Register exception handler
        set_exception_handler([self::class, 'handleException']);

        // Register shutdown function to catch fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    /**
     * Handle PHP errors
     *
     * @param int $level Error level
     * @param string $message Error message
     * @param string $file File where the error occurred
     * @param int $line Line number where the error occurred
     * @param array $context Error context
     * @return bool Whether the error was handled
     */
    public static function handleError(int $level, string $message, string $file, int $line, array $context = []): bool
    {
        // Check if the error should be reported based on error_reporting
        if (!(error_reporting() & $level)) {
            return false;
        }

        // Convert error to exception (except for silenced errors with @ operator)
        if (error_reporting() !== 0) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        }

        return true;
    }

    /**
     * Handle exceptions
     *
     * @param \Throwable $exception The exception to handle
     * @return void
     */
    public static function handleException(\Throwable $exception): void
    {
        try {
            // Basic error details for fallback
            $errorType = get_class($exception);
            $errorMessage = $exception->getMessage();
            $errorFile = $exception->getFile();
            $errorLine = $exception->getLine();
            $errorTrace = $exception->getTraceAsString();
            
            // Register a fallback error handler that will execute if something goes wrong
            register_shutdown_function(function() use ($errorType, $errorMessage, $errorFile, $errorLine, $errorTrace) {
                $lastError = error_get_last();
                // Only show fallback if there was a fatal error after our handler started
                if ($lastError && in_array($lastError['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
                    // Clear any output buffers
                    while (ob_get_level()) {
                        ob_end_clean();
                    }
                    
                    // Simple HTML output with error information
                    echo '<!DOCTYPE html>
                    <html>
                    <head>
                        <title>Critical Error</title>
                        <style>
                            body { font-family: sans-serif; padding: 0; margin: 0; background: #f8f8f8; }
                            .error-container { background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                            .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 15px; }
                            .error-message { margin-bottom: 20px; }
                            .error-detail { background: #f5f5f5; padding: 15px; border-left: 4px solid #e74c3c; margin-bottom: 20px; overflow-x: auto; }
                            .error-trace { font-family: monospace; white-space: pre-wrap; background: #f0f0f0; padding: 10px; font-size: 12px; overflow-x: auto; }
                            .error-meta { color: #777; font-size: 12px; margin-top: 20px; }
                            .error-meta strong { color: #555; }
                            .handler-error { background: #fff3f3; border-left: 4px solid #ff5757; margin-top: 30px; padding: 15px; }
                        </style>
    <link rel="stylesheet" href="/library/css/errorhandler.css">

                    </head>
                    <body>
                        <div class="error-container">
                            <div class="error-title">Error Handler Failure</div>
                            <p class="error-message">The error handler encountered a problem while processing the original error.</p>
                            
                            <div class="handler-error">
                                <strong>Error Handler Failed:</strong> ' . htmlspecialchars($lastError['message']) . '<br>
                                <strong>In file:</strong> ' . htmlspecialchars($lastError['file']) . '<br>
                                <strong>On line:</strong> ' . $lastError['line'] . '
                            </div>
                            
                            <h3>Original Error:</h3>
                            <div class="error-detail">
                                <strong>' . htmlspecialchars($errorType) . ':</strong> ' . htmlspecialchars($errorMessage) . '<br>
                                <strong>In file:</strong> ' . htmlspecialchars($errorFile) . '<br>
                                <strong>On line:</strong> ' . $errorLine . '
                            </div>
                            
                            <h3>Stack Trace:</h3>
                            <div class="error-trace">' . htmlspecialchars($errorTrace) . '</div>
                            
                            <div class="error-meta">
                                <strong>Time:</strong> ' . date('Y-m-d H:i:s') . ' |
                                <strong>PHP Version:</strong> ' . PHP_VERSION . ' |
                                <strong>Server:</strong> ' . PHP_SAPI . '
                            </div>
                        </div>
                    </body>
                    </html>';
                    exit(1);
                }
            });
            
            // Log the exception if enabled
            if (self::$config['log_errors']) {
                self::logException($exception);
            }
    
            // Do not display errors if in production mode and display_errors is disabled
            if (self::$config['environment'] === 'production' && !self::$config['display_errors']) {
                self::renderProductionError($exception);
                return;
            }
    
            // Otherwise, display a detailed error page
            self::renderDetailedError($exception);
            
        } catch (\Throwable $internalException) {
            // If our error handler itself throws an exception, show a simple error page
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Error Handler Failed</title>
                <style>
                    body { font-family: sans-serif; padding: 0; margin: 0; background: #f8f8f8; }
                    .error-container { background: white; border: 1px solid #ddd; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    .error-title { color: #e74c3c; font-size: 24px; margin-bottom: 15px; }
                    .error-message { margin-bottom: 20px; }
                    .error-detail { background: #f5f5f5; padding: 15px; border-left: 4px solid #e74c3c; margin-bottom: 20px; overflow-x: auto; }
                    .error-trace { font-family: monospace; white-space: pre-wrap; background: #f0f0f0; padding: 10px; font-size: 12px; overflow-x: auto; }
                    .error-meta { color: #777; font-size: 12px; margin-top: 20px; }
                    .error-meta strong { color: #555; }
                    .handler-error { background: #fff3f3; border-left: 4px solid #ff5757; margin-top: 30px; padding: 15px; }
                </style>
    <link rel="stylesheet" href="/library/css/errorhandler.css">

            </head>
            <body>
                <div class="error-container">
                    <div class="error-title">Error Handler Failed</div>
                    <p class="error-message">The error handler encountered a problem while processing an error.</p>
                    
                    <div class="handler-error">
                        <strong>Error in Error Handler:</strong> ' . htmlspecialchars($internalException->getMessage()) . '<br>
                        <strong>In file:</strong> ' . htmlspecialchars($internalException->getFile()) . '<br>
                        <strong>On line:</strong> ' . $internalException->getLine() . '
                    </div>
                    
                    <h3>Original Error:</h3>
                    <div class="error-detail">
                        <strong>' . htmlspecialchars(get_class($exception)) . ':</strong> ' . htmlspecialchars($exception->getMessage()) . '<br>
                        <strong>In file:</strong> ' . htmlspecialchars($exception->getFile()) . '<br>
                        <strong>On line:</strong> ' . $exception->getLine() . '
                    </div>
                    
                    <h3>Error Handler Stack Trace:</h3>
                    <div class="error-trace">' . htmlspecialchars($internalException->getTraceAsString()) . '</div>
                    
                    <h3>Original Error Stack Trace:</h3>
                    <div class="error-trace">' . htmlspecialchars($exception->getTraceAsString()) . '</div>
                    
                    <div class="error-meta">
                        <strong>Time:</strong> ' . date('Y-m-d H:i:s') . ' |
                        <strong>PHP Version:</strong> ' . PHP_VERSION . ' |
                        <strong>Server:</strong> ' . PHP_SAPI . '
                    </div>
                </div>
            </body>
            </html>';
            
            // Log the internal error
            error_log("Error Handler Failed: " . $internalException->getMessage() . 
                " in " . $internalException->getFile() . " on line " . $internalException->getLine() . 
                "\nOriginal error: " . $exception->getMessage());
            
            exit(1);
        }
    }

    /**
     * Handle script shutdown and catch fatal errors
     *
     * @return void
     */
    public static function handleShutdown(): void
    {
        // Get last error
        $error = error_get_last();

        // Check if a fatal error occurred
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            try {
                // Clear any output buffering
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
    
                // Convert to exception and handle
                $exception = new \ErrorException(
                    $error['message'],
                    0,
                    $error['type'],
                    $error['file'],
                    $error['line']
                );
    
                self::handleException($exception);
            } catch (\Throwable $internalException) {
                // If even our fallback fails, display the simplest possible error message
                echo '<!DOCTYPE html>
                <html>
                <head>
                    <title>Critical Error</title>
                    <style>
                        body { font-family: sans-serif; background: #f8f8f8; padding: 0; margin: 0; }
                        .error-box { background: white; border: 2px solid #e74c3c; padding: 20px; border-radius: 5px; max-width: 800px; margin: 0 auto; }
                        h1 { color: #e74c3c; }
                        .code { font-family: monospace; background: #f0f0f0; padding: 10px; overflow-x: auto; }
                    </style>
    <link rel="stylesheet" href="/library/css/errorhandler.css">

                </head>
                <body>
                    <div class="error-box">
                        <h1>Fatal Error</h1>
                        <p>A critical error occurred that could not be handled by the error handler:</p>
                        <div class="code">
                            <strong>' . htmlspecialchars($error['message']) . '</strong><br>
                            in file: ' . htmlspecialchars($error['file']) . '<br>
                            on line: ' . $error['line'] . '
                        </div>
                        <p>The error handler itself also failed with: ' . htmlspecialchars($internalException->getMessage()) . '</p>
                        <p><small>Time: ' . date('Y-m-d H:i:s') . ' | PHP: ' . PHP_VERSION . '</small></p>
                    </div>
                </body>
                </html>';
                
                // Log the double-failure
                error_log("[CRITICAL] Both the application and error handler failed. Application Error: {$error['message']} in {$error['file']} on line {$error['line']}. Error Handler Error: {$internalException->getMessage()} in {$internalException->getFile()} on line {$internalException->getLine()}");
                
                exit(1);
            }
        }
    }

    /**
     * Log an exception to the error log
     *
     * @param \Throwable $exception The exception to log
     * @return void
     */
    protected static function logException(\Throwable $exception): void
    {
        $message = self::formatExceptionForLog($exception);

        // Log to custom file if specified, otherwise use error_log()
        if (self::$config['error_log_file']) {
            $logFile = self::$config['error_log_file'];
            error_log($message . PHP_EOL, 3, $logFile);
        } else {
            error_log($message);
        }
    }

    /**
     * Format exception for logging
     *
     * @param \Throwable $exception The exception to format
     * @return string Formatted exception message
     */
    protected static function formatExceptionForLog(\Throwable $exception): string
    {
        $now = date('Y-m-d H:i:s');
        $message = "[{$now}] " . get_class($exception) . ": {$exception->getMessage()}";
        $message .= " in {$exception->getFile()}:{$exception->getLine()}";

        // Check if this is a web request or CLI
        if (PHP_SAPI !== 'cli') {
            $message .= " (URL: {$_SERVER['REQUEST_URI']}";
            if (!empty($_SERVER['HTTP_REFERER'])) {
                $message .= ", Referer: {$_SERVER['HTTP_REFERER']}";
            }
            $message .= ")";
        }

        // Add trace
        $message .= "\nStack trace:\n" . $exception->getTraceAsString();

        // Add previous exceptions if any
        $previous = $exception->getPrevious();
        if ($previous) {
            $message .= "\n\nCaused by: " . self::formatExceptionForLog($previous);
        }

        return $message;
    }

    /**
     * Render a production-friendly error page
     *
     * @param \Throwable $exception The exception that was thrown
     * @return void
     */
    protected static function renderProductionError(\Throwable $exception): void
    {
        $statusCode = 500;

        // Determine HTTP status code based on exception type
        if ($exception instanceof \HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        // Set HTTP response code
        self::setHttpResponseCode($statusCode);

        // Check if running in CLI
        if (PHP_SAPI === 'cli') {
            echo self::$colors['red'] . "Error: " . self::$colors['reset'] .
                 $exception->getMessage() . PHP_EOL;
            exit(1);
        }

        // Check if we have a custom error view
        if (self::$config['error_view'] && file_exists(self::$config['error_view'])) {
            // Output HTML comment for automated testing tools
            echo self::generateErrorComment($exception);

            // Extract variables for the view
            $statusCode = $statusCode;
            $statusText = self::$statusCodes[$statusCode] ?? 'Server Error';
            $appName = self::$config['app_name'];

            // Include the error view
            include self::$config['error_view'];
        } else {
            // Fallback to a simple HTML error page
            $statusText = self::$statusCodes[$statusCode] ?? 'Server Error';

            // Output HTML comment for automated testing tools
            echo self::generateErrorComment($exception);

            echo '<!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>' . $statusCode . ' - ' . htmlspecialchars($statusText) . '</title>
                <style>
                    body {
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        line-height: 1.6;
                        color: #333;
                        background-color: #f5f5f5;
                        margin: 0;
                        padding: 0;
                    }
                    .container {
                        max-width: 600px;
                        margin: 100px auto;
                        padding: 30px;
                        background-color: #fff;
                        border-radius: 5px;
                        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                        text-align: center;
                    }
                    h1 {
                        font-size: 36px;
                        margin: 0 0 20px;
                        color: #d9534f;
                    }
                    p {
                        margin: 0 0 15px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 10px 20px;
                        background-color: #007bff;
                        color: #fff;
                        text-decoration: none;
                        border-radius: 3px;
                        margin-top: 20px;
                    }
                </style>
    <link rel="stylesheet" href="/library/css/errorhandler.css">

            </head>
            <body>
                <div class="container">
                    <h1>' . $statusCode . ' - ' . htmlspecialchars($statusText) . '</h1>
                    <p>Sorry, something went wrong on our server.</p>
                    <p>Please try again later or contact the site administrator if the problem persists.</p>
                    <a href="' . htmlspecialchars(self::$config['app_url']) . '" class="btn">Go Home</a>
                </div>
            </body>
            </html>';
        }
    }

    /**
     * Get database/PDO diagnostics for common errors
     *
     * @param \Throwable $exception The exception that was thrown
     * @return array|null Diagnostic information array or null if not applicable
     */
    protected static function getDatabaseDiagnostics(\Throwable $exception): ?array
    {
        $message = $exception->getMessage();
        $diagnostics = null;

        // PDO Driver not found
        if (stripos($message, 'could not find driver') !== false) {
            // Analyze PHP environment
            $phpIniPath = php_ini_loaded_file();
            $extensionDir = ini_get('extension_dir');
            $loadedExtensions = get_loaded_extensions();
            $pdoDrivers = class_exists('PDO') ? \PDO::getAvailableDrivers() : [];

            // Detect which driver is needed
            $neededDriver = 'unknown';
            $dsn = isset($GLOBALS['Application']['conn_data']) ? $GLOBALS['Application']['conn_data'] : '';
            if (preg_match('/^(\w+):/', $dsn, $matches)) {
                $neededDriver = $matches[1];
            } elseif (isset($GLOBALS['Application']['pdo_driver'])) {
                $neededDriver = $GLOBALS['Application']['pdo_driver'];
            }

            // Build specific solution based on detected environment
            $solutions = [];
            $likelyCauses = [];

            // Check what's actually missing
            $pdoInstalled = in_array('PDO', $loadedExtensions);
            $driverInstalled = in_array('pdo_' . $neededDriver, $loadedExtensions);

            if (!$pdoInstalled) {
                $likelyCauses[] = 'PDO extension is not loaded';
                $solutions[] = [
                    'title' => 'Enable PDO Extension',
                    'steps' => [
                        "1. Edit php.ini: {$phpIniPath}",
                        '2. Find and uncomment: extension=pdo',
                        '3. Restart your web server'
                    ]
                ];
            }

            if (!$driverInstalled) {
                $likelyCauses[] = "PDO driver 'pdo_{$neededDriver}' is not loaded";

                // Check if DLL/SO file exists
                $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
                $extFile = $isWindows ? "php_pdo_{$neededDriver}.dll" : "pdo_{$neededDriver}.so";
                $extPath = $extensionDir . DIRECTORY_SEPARATOR . $extFile;
                $fileExists = file_exists($extPath);

                if ($fileExists) {
                    $likelyCauses[] = "Extension file exists ({$extFile}) but is not enabled in php.ini";
                    $solutions[] = [
                        'title' => "✅ File Found - Just Enable It!",
                        'steps' => [
                            "1. Edit php.ini: {$phpIniPath}",
                            "2. Find and uncomment (remove ;): extension=pdo_{$neededDriver}",
                            "   OR add this line if it doesn't exist: extension=pdo_{$neededDriver}",
                            '3. Save php.ini',
                            '4. Restart your web server',
                            "5. Verify: Run 'php -m | grep pdo_{$neededDriver}'"
                        ]
                    ];
                } else {
                    $likelyCauses[] = "Extension file does NOT exist: {$extPath}";

                    // Provide specific installation instructions based on driver type
                    if ($neededDriver === 'odbc') {
                        $solutions[] = [
                            'title' => '📦 Install PDO_ODBC Extension',
                            'steps' => $isWindows ? [
                                '1. Your PHP version: ' . PHP_VERSION,
                                "2. Extension directory: {$extensionDir}",
                                '3. PDO_ODBC is usually included with PHP - check if extension_dir path is correct',
                                '4. Download PHP from windows.php.net if file is missing',
                                '5. Extract php_pdo_odbc.dll to extension directory',
                                "6. Edit php.ini ({$phpIniPath})",
                                '7. Add: extension=pdo_odbc',
                                '8. Restart web server'
                            ] : [
                                '1. Ubuntu/Debian: sudo apt-get install php-odbc php-pdo',
                                '2. CentOS/RHEL: sudo yum install php-odbc php-pdo',
                                '3. Restart web server: sudo systemctl restart apache2 (or php-fpm)',
                                "4. Verify: php -m | grep pdo_odbc"
                            ]
                        ];

                        // Add MS Access specific instructions
                        if ($isWindows && stripos($dsn, 'Access Driver') !== false) {
                            $solutions[] = [
                                'title' => '🗄️  Install Microsoft Access ODBC Driver',
                                'steps' => [
                                    '1. Download "Microsoft Access Database Engine 2016 Redistributable"',
                                    '2. URL: https://www.microsoft.com/en-us/download/details.aspx?id=54920',
                                    '3. Choose 32-bit or 64-bit to match your PHP version',
                                    '4. Run: php -i | findstr "Architecture" to check PHP bitness',
                                    '5. Install the downloaded file',
                                    '6. Restart web server after installation'
                                ]
                            ];
                        }
                    } elseif ($neededDriver === 'sqlsrv') {
                        $solutions[] = [
                            'title' => '📦 Install PDO_SQLSRV Extension (SQL Server)',
                            'steps' => $isWindows ? [
                                '1. Download Microsoft Drivers for PHP for SQL Server',
                                '2. URL: https://docs.microsoft.com/en-us/sql/connect/php/download-drivers-php-sql-server',
                                "3. Extract the .dll matching your PHP version (e.g., php_pdo_sqlsrv_" . PHP_MAJOR_VERSION . PHP_MINOR_VERSION . "_ts.dll)",
                                "4. Copy to: {$extensionDir}",
                                "5. Edit php.ini ({$phpIniPath})",
                                '6. Add: extension=pdo_sqlsrv',
                                '7. Restart web server'
                            ] : [
                                '1. Ubuntu/Debian: sudo apt-get install php-sqlsrv php-pdo-sqlsrv',
                                '2. CentOS/RHEL: sudo yum install php-sqlsrv php-pdo-sqlsrv',
                                '3. May need Microsoft ODBC Driver: https://docs.microsoft.com/en-us/sql/connect/odbc/linux-mac/installing-the-microsoft-odbc-driver-for-sql-server',
                                '4. Restart web server'
                            ]
                        ];
                    } elseif ($neededDriver === 'mysql') {
                        $solutions[] = [
                            'title' => '📦 Install PDO_MySQL Extension',
                            'steps' => $isWindows ? [
                                "1. Extension file missing: {$extPath}",
                                '2. PDO_MySQL is usually included with PHP',
                                '3. Re-download PHP from windows.php.net',
                                '4. Extract php_pdo_mysql.dll to extension directory',
                                "5. Edit php.ini ({$phpIniPath})",
                                '6. Add: extension=pdo_mysql',
                                '7. Restart web server'
                            ] : [
                                '1. Ubuntu/Debian: sudo apt-get install php-mysql',
                                '2. CentOS/RHEL: sudo yum install php-mysqlnd',
                                '3. Restart web server',
                                "4. Verify: php -m | grep pdo_mysql"
                            ]
                        ];
                    }
                }
            }

            // Build the diagnostics array
            $diagnostics = [
                'title' => "PDO Driver '{$neededDriver}' Not Found",
                'problem' => "PHP cannot find the '{$neededDriver}' driver for PDO database connections.",
                'likely_causes' => $likelyCauses,
                'solutions' => $solutions,
                'verification' => [
                    "Run: php -m | grep pdo_{$neededDriver}",
                    'Should show: pdo_' . $neededDriver,
                    'Or visit phpinfo() and search for "PDO drivers"'
                ],
                'additional_info' => [
                    'PHP Version: ' . PHP_VERSION . ' (' . (PHP_ZTS ? 'Thread Safe' : 'Non-Thread Safe') . ')',
                    'PHP INI: ' . ($phpIniPath ?: 'Not found'),
                    'Extension Directory: ' . $extensionDir,
                    'Currently Loaded PDO Drivers: ' . (empty($pdoDrivers) ? 'None' : implode(', ', $pdoDrivers)),
                    'Operating System: ' . PHP_OS . ' (' . php_uname('r') . ')',
                    'Connection DSN: ' . $dsn
                ]
            ];
        }

        // Connection refused / Could not connect
        if (stripos($message, 'connection refused') !== false || stripos($message, 'could not connect') !== false) {
            $diagnostics = [
                'title' => 'Database Connection Refused',
                'problem' => 'Unable to establish a connection to the database server.',
                'likely_causes' => [
                    'Database server is not running',
                    'Incorrect host or port in connection string',
                    'Firewall blocking the connection',
                    'Database server not accepting remote connections'
                ],
                'solutions' => [
                    [
                        'title' => 'Verify Database Server',
                        'steps' => [
                            '1. Check if database service is running',
                            '2. Verify connection string in .env or Application config',
                            '3. Test connection with database client (SSMS, MySQL Workbench, etc.)',
                            '4. Check firewall rules allow connections on database port'
                        ]
                    ]
                ],
                'additional_info' => [
                    'Connection string location: Check .env file or $GLOBALS[\'Application\'][\'conn_data\']',
                    'Common ports: SQL Server=1433, MySQL=3306, PostgreSQL=5432'
                ]
            ];
        }

        // Access denied / Authentication failed
        if (stripos($message, 'access denied') !== false || stripos($message, 'authentication failed') !== false) {
            $diagnostics = [
                'title' => 'Database Authentication Failed',
                'problem' => 'Invalid database username or password.',
                'likely_causes' => [
                    'Incorrect username or password in connection string',
                    'User does not have permission to connect',
                    'User account is locked or expired'
                ],
                'solutions' => [
                    [
                        'title' => 'Fix Authentication',
                        'steps' => [
                            '1. Verify username and password in .env file',
                            '2. Test credentials with database management tool',
                            '3. Check user has appropriate permissions',
                            '4. Ensure user is allowed to connect from this host'
                        ]
                    ]
                ],
                'additional_info' => [
                    'Check: $GLOBALS[\'Application\'][\'conn_data\'] for connection string',
                    'Verify credentials in .env file match database users'
                ]
            ];
        }

        return $diagnostics;
    }

    /**
     * Render a detailed error page for development
     *
     * @param \Throwable $exception The exception that was thrown
     * @return void
     */
    protected static function renderDetailedError(\Throwable $exception): void
    {
        $statusCode = 500;

        // Determine HTTP status code based on exception type
        if ($exception instanceof \HttpException) {
            $statusCode = $exception->getStatusCode();
        }

        // Set HTTP response code
        self::setHttpResponseCode($statusCode);

        // Check if running in CLI
        if (PHP_SAPI === 'cli') {
            self::renderCliError($exception);
            exit(1);
        }

        // Determine if we're running locally for VS Code integration
        $isLocalEnvironment = self::isLocalEnvironment();

        // Render HTML error page
        self::renderHtmlError($exception, $isLocalEnvironment);
    }
    
    /**
     * Check if the application is running on a local environment
     * 
     * @return bool True if running locally
     */
    protected static function isLocalEnvironment(): bool
    {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $localHosts = [
            'localhost',
            '127.0.0.1',
            '::1'  // IPv6 localhost
        ];
        
        return in_array($serverName, $localHosts) || 
               in_array($remoteAddr, $localHosts) ||
               strpos($serverName, '.local') !== false ||
               strpos($serverName, '.test') !== false;
    }

    /**
     * Render a detailed error for CLI
     *
     * @param \Throwable $exception The exception that was thrown
     * @return void
     */
    protected static function renderCliError(\Throwable $exception): void
    {
        $c = self::$colors;
        $type = get_class($exception);

        echo "{$c['bold']}{$c['red']}Error:{$c['reset']} {$c['bold']}" .
             $exception->getMessage() . "{$c['reset']}\n\n";

        echo "{$c['bold']}Type:{$c['reset']} $type\n";
        echo "{$c['bold']}File:{$c['reset']} {$exception->getFile()}\n";
        echo "{$c['bold']}Line:{$c['reset']} {$exception->getLine()}\n\n";

        // Show database diagnostics if applicable
        $diagnostics = self::getDatabaseDiagnostics($exception);
        if ($diagnostics) {
            echo "{$c['bold']}{$c['yellow']}═══ DATABASE DIAGNOSTICS ═══{$c['reset']}\n\n";
            echo "{$c['bold']}{$c['cyan']}{$diagnostics['title']}{$c['reset']}\n";
            echo "{$diagnostics['problem']}\n\n";

            if (!empty($diagnostics['likely_causes'])) {
                echo "{$c['bold']}Likely Causes:{$c['reset']}\n";
                foreach ($diagnostics['likely_causes'] as $cause) {
                    echo "  • {$cause}\n";
                }
                echo "\n";
            }

            if (!empty($diagnostics['solutions'])) {
                echo "{$c['bold']}Solutions:{$c['reset']}\n";
                foreach ($diagnostics['solutions'] as $solution) {
                    echo "{$c['bold']}{$c['green']}{$solution['title']}:{$c['reset']}\n";
                    foreach ($solution['steps'] as $step) {
                        echo "  {$step}\n";
                    }
                    echo "\n";
                }
            }

            if (!empty($diagnostics['verification'])) {
                echo "{$c['bold']}Verification:{$c['reset']}\n";
                foreach ($diagnostics['verification'] as $verify) {
                    echo "  • {$verify}\n";
                }
                echo "\n";
            }

            if (!empty($diagnostics['additional_info'])) {
                echo "{$c['bold']}Additional Info:{$c['reset']}\n";
                foreach ($diagnostics['additional_info'] as $info) {
                    echo "  {$info}\n";
                }
                echo "\n";
            }

            echo "{$c['yellow']}═══════════════════════════════{$c['reset']}\n\n";
        }

        echo "{$c['bold']}Stack trace:{$c['reset']}\n";

        $frames = self::getTraceFrames($exception);
        foreach ($frames as $index => $frame) {
            $frameNumber = str_pad($index + 1, 2, ' ', STR_PAD_LEFT);
            $file = $frame['file'] ?? '';
            $line = $frame['line'] ?? '';
            $class = $frame['class'] ?? '';
            $function = $frame['function'] ?? '';
            $type = $frame['type'] ?? '';

            echo "{$c['magenta']}#{$frameNumber}{$c['reset']} ";

            if ($file && $line) {
                echo "{$file}:{$line}: ";
            } else {
                echo "{$c['yellow']}[internal function]:{$c['reset']} ";
            }

            if ($class) {
                echo "{$c['cyan']}{$class}{$c['reset']}";
            }

            if ($type) {
                echo "{$c['yellow']}{$type}{$c['reset']}";
            }

            if ($function) {
                echo "{$c['green']}{$function}(){$c['reset']}";
            }

            echo "\n";
        }

        // Show previous exception if any
        $previous = $exception->getPrevious();
        if ($previous) {
            echo "\n{$c['bold']}{$c['red']}Previous exception:{$c['reset']}\n\n";
            self::renderCliError($previous);
        }
    }

    /**
     * Render a detailed HTML error page
     *
     * @param \Throwable $exception The exception that was thrown
     * @param bool $isLocalEnvironment Whether the application is running locally
     * @return void
     */
    protected static function renderHtmlError(\Throwable $exception, bool $isLocalEnvironment = false): void
    {
        $frames = self::getTraceFrames($exception);
        $type = get_class($exception);
        $code = $exception->getCode();
        $message = $exception->getMessage();
        $file = $exception->getFile();
        $line = $exception->getLine();

        $fileContents = [];
        if (file_exists($file)) {
            $fileContents = self::getFileLines($file, $line, 10);
        }

        // Generate CSS class for severity
        $severityClass = 'error'; // Default
        if ($exception instanceof \ErrorException) {
            switch ($exception->getSeverity()) {
                case E_WARNING:
                case E_USER_WARNING:
                    $severityClass = 'warning';
                    break;
                case E_NOTICE:
                case E_USER_NOTICE:
                    $severityClass = 'notice';
                    break;
            }
        }

        // Output HTML comment for automated testing tools
        echo self::generateErrorComment($exception);

        // Begin output
        echo '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Error: ' . htmlspecialchars($message) . '</title>
            <script src="/assets/js/error-handler.js"></script>
            <style>
                :root {
                    --red: #d9534f;
                    --yellow: #f0ad4e;
                    --blue: #5bc0de;
                    --green: #5cb85c;
                    --gray: #f5f5f5;
                    --dark-gray: #444;
                    --border-color: #ddd;
                }

                * {
                    box-sizing: border-box;
                }

                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    background-color: #f5f5f5;
                    margin: 0;
                    padding: 0;
                }

                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                }

                header {
                    background-color: var(--red);
                    color: white;
                    padding: 20px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }

                header.warning {
                    background-color: var(--yellow);
                }

                header.notice {
                    background-color: var(--blue);
                }

                h1, h2, h3 {
                    margin-top: 0;
                }

                .exception-message {
                    font-size: 18px;
                    font-weight: normal;
                    margin-bottom: 0;
                }

                .exception-type {
                    font-size: 14px;
                    opacity: 0.8;
                }

                .card {
                    background-color: white;
                    border-radius: 4px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    margin-bottom: 20px;
                    overflow: hidden;
                }

                .card-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    background-color: var(--gray);
                    padding: 15px 20px;
                    border-bottom: 1px solid var(--border-color);
                    font-weight: bold;
                }

                .card-body {
                    padding: 20px;
                }

                .error-details {
                    background-color: var(--dark-gray);
                    color: white;
                    padding: 15px 20px;
                    border-radius: 4px;
                    margin-bottom: 20px;
                    font-family: Monaco, Consolas, "Courier New", monospace;
                    overflow-x: auto;
                }

                .error-details .file-path {
                    opacity: 0.8;
                    font-size: 14px;
                }

                pre {
                    background-color: var(--gray);
                    padding: 15px;
                    border-radius: 4px;
                    overflow-x: auto;
                    margin: 0;
                    font-family: Monaco, Consolas, "Courier New", monospace;
                }

                code {
                    font-family: Monaco, Consolas, "Courier New", monospace;
                }

                .line-numbers {
                    float: left;
                    text-align: right;
                    padding-right: 20px;
                    width: 50px;
                    color: #999;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    -ms-user-select: none;
                    user-select: none;
                }

                .error-line {
                    background-color: rgba(255, 0, 0, 0.1);
                    width: 100%;
                    display: inline-block;
                }

                .code-context {
                    display: flex;
                }

                .stack-trace {
                    list-style-type: none;
                    padding: 0;
                    margin: 0;
                }

                .stack-frame {
                    padding: 10px 15px;
                    border-bottom: 1px solid var(--border-color);
                }

                .stack-frame:last-child {
                    border-bottom: none;
                }

                .stack-frame.vendor {
                    opacity: 0.7;
                }

                .stack-frame-number {
                    background-color: var(--gray);
                    color: var(--dark-gray);
                    border-radius: 4px;
                    padding: 2px 6px;
                    margin-right: 10px;
                    font-size: 12px;
                }

                .stack-frame-file {
                    font-family: Monaco, Consolas, "Courier New", monospace;
                    font-size: 14px;
                    color: var(--dark-gray);
                }

                .stack-frame-line {
                    background-color: var(--red);
                    color: white;
                    border-radius: 4px;
                    padding: 2px 6px;
                    font-size: 12px;
                    margin-left: 10px;
                }

                .stack-frame-function {
                    font-family: Monaco, Consolas, "Courier New", monospace;
                    font-size: 14px;
                    margin-top: 5px;
                    color: var(--dark-gray);
                }

                .stack-frame-class {
                    color: var(--blue);
                }

                .stack-frame-separator {
                    color: #999;
                }

                .stack-frame-function-name {
                    color: var(--green);
                }

                .tab-buttons {
                    display: flex;
                    border-bottom: 1px solid var(--border-color);
                    background-color: var(--gray);
                }

                .tab-button {
                    padding: 10px 20px;
                    background-color: transparent;
                    border: none;
                    border-bottom: 2px solid transparent;
                    cursor: pointer;
                    font-size: 14px;
                    outline: none;
                    color: #555 !important;
                }

                .tab-button:hover {
                    color: #333 !important;
                    background-color: rgba(0, 0, 0, 0.05);
                }

                .tab-button.active {
                    border-bottom-color: var(--blue);
                    font-weight: bold;
                    color: #333 !important;
                }

                .tab-content {
                    display: none;
                    padding: 20px;
                }

                .tab-content.active {
                    display: block;
                }

                .request-data {
                    width: 100%;
                    border-collapse: collapse;
                }

                .request-data th, .request-data td {
                    text-align: left;
                    padding: 8px;
                    border-bottom: 1px solid var(--border-color);
                }

                .request-data th {
                    background-color: var(--gray);
                }

                @media (max-width: 768px) {
                    body {
                        padding: 0;
                    }

                    .card-header, .card-body {
                        padding: 10px;
                    }

                    .error-details {
                        padding: 10px;
                    }

                    pre {
                        padding: 10px;
                    }

                    .stack-frame {
                        padding: 10px;
                    }

                    .tab-button {
                        padding: 8px 15px;
                    }

                    .tab-content {
                        padding: 10px;
                    }
                }

                .button-group {
                    display: flex;
                    gap: 10px;
                }
                
                .vscode-button {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    background-color: #007ACC;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    padding: 5px 10px;
                    font-size: 12px;
                    cursor: pointer;
                    text-decoration: none;
                    transition: background-color 0.2s;
                }
                
                .vscode-button:hover {
                    background-color: #005A9E;
                    text-decoration: none;
                    color: white;
                }

                .copy-button {
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    background-color: #6c757d;
                    color: white;
                    border: none;
                    border-radius: 4px;
                    padding: 5px 10px;
                    font-size: 12px;
                    cursor: pointer;
                    text-decoration: none;
                }

                .copy-button:hover {
                    background-color: #5a6268;
                }

                /* PHP.ini comment lines - display as block so they can be fully hidden */
                .ini-comment {
                    display: block;
                    color: #6a737d;
                }

                .ini-comment.hidden {
                    display: none !important;
                }
            </style>
    <link rel="stylesheet" href="/library/css/errorhandler.css">

        </head>
        <body>
            <div class="container">
                <header class="' . $severityClass . '">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start">
                        <div>
                            <h1>' . htmlspecialchars($type) . '</h1>
                            <div class="exception-message">' . str_replace("\n", '<br>', $message) . '</div>
                            <div class="exception-type">
                                in ' . htmlspecialchars($file) . ' on line ' . $line .
                                ($code ? ' (code: ' . $code . ')' : '') . '
                            </div>
                        </div>
                        <button class="copy-button" onclick="var h=this.closest(\'header\');var t=h.querySelector(\'h1\').textContent+\' in \'+h.querySelector(\'.exception-type\').textContent.trim()+\'\\n\'+h.querySelector(\'.exception-message\').textContent;navigator.clipboard.writeText(t).then(function(){this.textContent=\'Gekopieerd!\';setTimeout(function(){this.innerHTML=\'&#128203; Kopieer\'}.bind(this),2000)}.bind(this))" title="Kopieer foutmelding naar klembord">&#128203; Kopieer</button>
                    </div>
                </header>';

        // Show database diagnostics if applicable
        $diagnostics = self::getDatabaseDiagnostics($exception);
        if ($diagnostics) {
            echo '
                <div class="card eh-diagnostics-card">
                    <div class="card-header eh-diagnostics-header">
                        <span class="eh-diagnostics-title">⚠️ ' . htmlspecialchars($diagnostics['title']) . '</span>
                    </div>
                    <div class="card-body">
                        <p class="eh-diagnostics-problem">' . htmlspecialchars($diagnostics['problem']) . '</p>';

            if (!empty($diagnostics['likely_causes'])) {
                echo '
                        <h4 class="eh-diagnostics-section-title">Likely Causes:</h4>
                        <ul class="eh-diagnostics-list">';
                foreach ($diagnostics['likely_causes'] as $cause) {
                    echo '<li>' . htmlspecialchars($cause) . '</li>';
                }
                echo '</ul>';
            }

            if (!empty($diagnostics['solutions'])) {
                echo '<h4 class="eh-diagnostics-section-title">Solutions:</h4>';
                foreach ($diagnostics['solutions'] as $solution) {
                    echo '
                        <div class="eh-solution-box">
                            <strong class="eh-solution-title">' . htmlspecialchars($solution['title']) . '</strong>
                            <ol class="eh-solution-steps">';
                    foreach ($solution['steps'] as $step) {
                        echo '<li>' . htmlspecialchars($step) . '</li>';
                    }
                    echo '</ol>
                        </div>';
                }
            }

            if (!empty($diagnostics['verification'])) {
                echo '
                        <h4 class="eh-diagnostics-section-title">Verification:</h4>
                        <ul class="eh-diagnostics-list">';
                foreach ($diagnostics['verification'] as $verify) {
                    echo '<li><code class="eh-verification-code">' . htmlspecialchars($verify) . '</code></li>';
                }
                echo '</ul>';
            }

            if (!empty($diagnostics['additional_info'])) {
                echo '
                        <h4 class="eh-diagnostics-section-title">Additional Info:</h4>
                        <ul class="eh-additional-info">';
                foreach ($diagnostics['additional_info'] as $info) {
                    echo '<li class="eh-additional-info-item">' . htmlspecialchars($info) . '</li>';
                }
                echo '</ul>';
            }

            echo '
                    </div>
                </div>';
        }

        // Show SQL Query card if there's a last SQL from Database class
        $lastSQL = '';
        if (class_exists('\\App\\Library\\Database')) {
            $lastSQL = \App\Library\Database::getLastSQL();
        }
        if (!empty($lastSQL)) {
            echo '
                <div class="card">
                    <div class="card-header">
                        <span>SQL Query</span>
                        <button onclick="copySQL()" class="copy-button" title="Copy SQL to clipboard">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                            </svg>
                            Copy
                        </button>
                    </div>
                    <div class="card-body">
                        <pre id="sql-query" style="white-space: pre-wrap; word-wrap: break-word; background: #f8f9fa; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; overflow-x: auto; max-height: 400px; overflow-y: auto;">' . htmlspecialchars($lastSQL) . '</pre>
                    </div>
                </div>
                <script>
                function copySQL() {
                    var sql = document.getElementById("sql-query").innerText;
                    navigator.clipboard.writeText(sql).then(function() {
                        var btn = document.querySelector(".copy-button");
                        var originalText = btn.innerHTML;
                        btn.innerHTML = "Copied!";
                        setTimeout(function() { btn.innerHTML = originalText; }, 2000);
                    });
                }
                </script>';
        }

        echo '
                <div class="card">
                    <div class="card-header">
                        Source Code
                        <div class="button-group">
                            ' . ($isLocalEnvironment ? '
                            <a href="vscode://file/' . str_replace('+', '%20', urlencode($file)) . ':' . $line . '" class="vscode-button">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 18l6-6-6-6"></path>
                                    <path d="M8 6l-6 6 6 6"></path>
                                </svg>
                                Open in VS Code
                            </a>' : '') . '
                            <button onclick="askClaudeHelp();" class="claude-button eh-claude-button">
                                <svg width="16" height="16" viewBox="0 0 50 50" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M37.5 9.5C36 3.625 29.5009 0 22.2502 0C12.875 0 5 7.625 5 17.0007C5 17.25 5 17.5 5.02376 17.75C1.64581 19 1.96227e-06 22.5 1.96227e-06 25.75C1.96227e-06 31.0004 4.00002 35 9.25015 35L37.5 34.9998C44.125 34.9998 50 29.125 50 22.5C50 15.8752 44.125 10 37.5 10" />
                                    <path d="M28.9098 20.5442C28.7391 19.6768 28.4293 18.7731 28.0012 17.8859C26.5211 15.0813 23.7089 13.3134 20.7032 13.3134C20.4632 13.3134 20.242 13.3452 20.0049 13.3708L19.994 13.3723C19.7675 13.3979 19.5434 13.4235 19.3183 13.4651C18.6344 13.5947 18.0053 13.8026 17.3949 14.0681C15.9272 14.7136 14.6822 15.8066 13.7848 17.2323C12.9033 18.633 12.42 20.2746 12.42 21.9913C12.42 22.7154 12.5039 23.4347 12.6705 24.1445C12.8442 24.889 13.1218 25.6161 13.4973 26.3103C14.3035 27.7965 15.5791 29.0129 17.1234 29.7646C18.1977 30.2872 19.3507 30.5551 20.5273 30.5551C20.586 30.5551 20.644 30.5507 20.7028 30.5462L20.7032 30.5461H20.7039L20.7064 30.5459C20.7659 30.5413 20.8252 30.5368 20.8847 30.5297C20.8872 30.5288 20.8906 30.5288 20.8932 30.5288C21.008 30.5159 21.112 30.5005 21.2187 30.4825L21.2201 30.482C21.3233 30.4647 21.4265 30.4423 21.5282 30.4175L21.5296 30.4171C22.1172 30.2911 22.6767 30.0929 23.2104 29.813C25.4462 28.7423 27.0928 26.5636 27.6747 23.8794C27.9039 22.7918 27.908 21.6573 27.6788 20.5432L28.9091 20.5434L28.9098 20.5442ZM25.4358 23.3588L25.4349 23.3574C24.7918 25.1485 23.251 26.5321 21.409 27.0785H21.408C21.0022 27.2068 20.5769 27.2712 20.1504 27.2712C19.9542 27.2712 19.7567 27.251 19.5558 27.2117C18.7354 27.0597 17.9693 26.6879 17.3393 26.116C16.6998 25.5458 16.2216 24.8046 15.9628 23.9809C15.8577 23.6424 15.7902 23.2969 15.758 22.9462C15.7387 22.7352 15.7295 22.5244 15.7295 22.3109C15.7295 20.772 16.4127 19.3572 17.5641 18.4402C18.4053 17.7783 19.4334 17.413 20.5041 17.413C22.6088 17.413 24.4676 18.6829 25.2158 20.5743C25.4238 21.1022 25.5382 21.6541 25.5561 22.2211C25.5732 22.6037 25.5371 22.9914 25.4358 23.3588Z" fill="white" />
                                </svg>
                                Ask Claude
                            </button>
                        </div>
                    </div>
                     
                    <div class="card-body">
                        <pre id="source-code"><code>';
            echo '<div class="code-context">';
            echo '<div class="line-numbers">';
            foreach ($fileContents as $lineNumber => $content) {
                echo $lineNumber . "\n";
            }
            echo '</div>';
            echo '<div class="code-content">';
            foreach ($fileContents as $lineNumber => $content) {
                if ($lineNumber === $line) {
                    echo '<div class="error-line">' . htmlspecialchars($content) . '</div><br>';
                } else {
                    echo htmlspecialchars($content);
                }
            }
            echo '</div>';
            echo '</div>';
            echo '</code></pre>
                    </div>
                </div>
           ';

        // Show tabs for request, server, environment, ODBC sources and logs
        if (PHP_SAPI !== 'cli') {
            echo '<div class="card-header">
                        Debug information </div>
                  <div class="card">
                    <div class="tab-buttons">
                        <button class="tab-button active" onclick="showTab(\'stack\');" data-tab="stack">Call Stack</button>
                        <button class="tab-button" onclick="showTab(\'request\');" data-tab="request">Request</button>
                        <button class="tab-button" onclick="showTab(\'server\');" data-tab="server">Server</button>
                        <button class="tab-button" onclick="showTab(\'cookies\');" data-tab="cookies">Cookies</button>
                        <button class="tab-button" onclick="showTab(\'session\');" data-tab="session">Session</button>
                        <button class="tab-button" onclick="showTab(\'odbc\');" data-tab="odbc">ODBC Sources</button>
                        <button class="tab-button" onclick="showTab(\'phpini\');" data-tab="phpini">PHP.ini</button>
                        <button class="tab-button" onclick="showTab(\'phpinfo\');" data-tab="phpinfo">PHP Info</button>';
                        
            // Add logs tab if in development mode and log file exists
            if (self::$config['environment'] === 'development' && 
                self::$config['error_log_file'] && 
                file_exists(self::$config['error_log_file'])) {
                echo '<button class="tab-button" onclick="showTab(\'logs\');" data-tab="logs">Log Entries</button>';
            }
                        
            echo '</div>

                    <div class="tab-content" id="stack-tab" class="eh-tab-content-visible">
                        <table class="request-data">
                            <tr>
                                <th class="eh-stack-frame-number">#</th>
                                <th>Call</th>
                                <th>File</th>
                                <th class="eh-stack-line-number">Line</th>
                            </tr>';
                        
                        // Get stack trace frames
                        $frames = self::getTraceFrames($exception);
                        
                        // Display each frame
                        foreach ($frames as $index => $frame) {
                            $file = isset($frame["file"]) ? htmlspecialchars($frame["file"]) : "[internal function]";
                            $line = isset($frame["line"]) ? $frame["line"] : "";
                            
                            // Format function call
                            $call = "";
                            if (isset($frame["class"])) {
                                $call .= '<span class="eh-stack-call-class">' . htmlspecialchars($frame["class"]) . '</span>';
                                $call .= '<span class="eh-stack-call-type">' . htmlspecialchars($frame["type"]) . '</span>';
                            }
                            
                            if (isset($frame["function"])) {
                                $call .= '<span class="eh-stack-call-function">' . htmlspecialchars($frame["function"]) . '</span>';
                                $call .= '(';
                                
                                // Add function arguments if available
                                if (isset($frame["args"]) && is_array($frame["args"])) {
                                    $args = [];
                                    foreach ($frame["args"] as $arg) {
                                        if (is_scalar($arg)) {
                                            // Format scalar arguments
                                            if (is_string($arg)) {
                                                if (strlen($arg) > 50) {
                                                    $args[] = '"' . htmlspecialchars(substr($arg, 0, 47)) . '..."';
                                                } else {
                                                    $args[] = '"' . htmlspecialchars($arg) . '"';
                                                }
                                            } elseif (is_bool($arg)) {
                                                $args[] = $arg ? 'true' : 'false';
                                            } elseif (is_null($arg)) {
                                                $args[] = 'null';
                                            } else {
                                                $args[] = htmlspecialchars((string)$arg);
                                            }
                                        } elseif (is_array($arg)) {
                                            $args[] = 'Array(' . count($arg) . ')';
                                        } elseif (is_object($arg)) {
                                            $args[] = get_class($arg) . ' Object';
                                        } elseif (is_resource($arg)) {
                                            $args[] = get_resource_type($arg) . ' Resource';
                                        } else {
                                            $args[] = 'unknown';
                                        }
                                    }
                                    
                                    $call .= implode(', ', $args);
                                }
                                
                                $call .= ')';
                            }
                            
                            // Determine if this is vendor code
                            $isVendor = strpos($file, '/vendor/') !== false;
                            $rowClass = $isVendor ? 'class="eh-stack-vendor-row"' : '';
                            
                            // Open file in VS Code if local environment
                            $vsCodeLink = '';
                            if (self::isLocalEnvironment() && $file !== "[internal function]") {
                                $encodedPath = str_replace('+', '%20', urlencode($file));
                                $vsCodeLink = '<a href="vscode://file/' . $encodedPath . ':' . $line . '" 
                                           class="eh-vscode-link" 
                                           title="Open in VS Code">
                                            <span class="eh-vscode-icon">&#9998;</span>
                                        </a>';
                            }
                            
                            echo '<tr ' . $rowClass . '>
                                  <td class="eh-stack-frame-number">' . $index . '</td>
                                  <td>' . $call . '</td>
                                  <td>' . $file . $vsCodeLink . '</td>
                                  <td class="eh-stack-line-number">' . $line . '</td>
                                  </tr>';
                        }
                        
                        echo '</table>
                    </div>

                    <div class="tab-content" id="request-tab" class="eh-tab-content-hidden">
                        <table class="request-data">
                            <tr>
                                <th>Parameter</th>
                                <th>Value</th>
                            </tr>';

            // Show GET parameters
            foreach ($_GET as $key => $value) {
                $formattedValue = is_array($value) ? json_encode($value) : $value;
                echo '<tr>
                        <td><strong>$_GET[\'' . htmlspecialchars($key) . '\']</strong></td>
                        <td>' . htmlspecialchars($formattedValue) . '</td>
                      </tr>';
            }

            // Show POST parameters
            foreach ($_POST as $key => $value) {
                $formattedValue = is_array($value) ? json_encode($value) : $value;
                echo '<tr>
                        <td><strong>$_POST[\'' . htmlspecialchars($key) . '\']</strong></td>
                        <td>' . htmlspecialchars($formattedValue) . '</td>
                      </tr>';
            }

            echo '</table>
                    </div>

                    <div class="tab-content" id="server-tab" class="eh-tab-content-hidden">
                        <table class="request-data">
                            <tr>
                                <th>Parameter</th>
                                <th>Value</th>
                            </tr>';

            // Show SERVER variables
            foreach ($_SERVER as $key => $value) {
                if (!is_array($value) && !is_object($value)) {
                    echo '<tr>
                            <td><strong>$_SERVER[\'' . htmlspecialchars($key) . '\']</strong></td>
                            <td>' . htmlspecialchars($value) . '</td>
                          </tr>';
                }
            }

            echo '</table>
                    </div>

                    <div class="tab-content" id="cookies-tab" class="eh-tab-content-hidden">
                        <table class="request-data">
                            <tr>
                                <th>Parameter</th>
                                <th>Value</th>
                            </tr>';

            // Show COOKIE variables
            if (!empty($_COOKIE)) {
                foreach ($_COOKIE as $key => $value) {
                    $formattedValue = is_array($value) ? json_encode($value) : $value;
                    echo '<tr>
                            <td><strong>$_COOKIE[\'' . htmlspecialchars($key) . '\']</strong></td>
                            <td>' . htmlspecialchars($formattedValue) . '</td>
                          </tr>';
                }
            } else {
                echo '<tr><td colspan="2">No cookies found</td></tr>';
            }

            echo '</table>
                    </div>

                    <div class="tab-content" id="session-tab" class="eh-tab-content-hidden">
                        <table class="request-data">
                            <tr>
                                <th>Parameter</th>
                                <th>Value</th>
                            </tr>';

            // Show SESSION variables
            if (isset($_SESSION) && !empty($_SESSION)) {
                foreach ($_SESSION as $key => $value) {
                    $formattedValue = is_array($value) ? json_encode($value) : (is_object($value) ? get_class($value) : $value);
                    echo '<tr>
                            <td><strong>$_SESSION[\'' . htmlspecialchars($key) . '\']</strong></td>
                            <td>' . htmlspecialchars($formattedValue) . '</td>
                          </tr>';
                }
            } else {
                echo '<tr><td colspan="2">No session data found or session not started</td></tr>';
            }

            echo '</table>
                    </div>
                    
                    <div class="tab-content" id="odbc-tab" class="eh-tab-content-hidden">
                        <table class="request-data">
                            <tr>
                                <th>ODBC Driver / Source</th>
                                <th>Type</th>
                                <th>Version</th>
                                <th>File</th>
                            </tr>';
                            
                            // Get ODBC sources and drivers
                            $odbcSources = [];
                            $odbcDrivers = [];
                            
                            // PHP 8.4+ requires an actual connection for odbc_data_source
                            if (function_exists('odbc_data_source') && version_compare(PHP_VERSION, '8.4.0', '<')) {
                                // Only try to get ODBC sources in PHP versions before 8.4
                                try {
                                    // 2 = SQL_FETCH_ALL
                                    $odbcSources = odbc_data_source(null, 2);
                                } catch (\Throwable $e) {
                                    // Silently fail
                                }
                            }
                            
                            if (function_exists('odbc_drivers')) {
                                $odbcDrivers = odbc_drivers();
                            }
                            
                            // Show ODBC sources
                            if (!empty($odbcSources)) {
                                foreach ($odbcSources as $name => $type) {
                                    echo '<tr>
                                            <td><strong>' . htmlspecialchars($name) . '</strong></td>
                                            <td>' . htmlspecialchars($type) . '</td>
                                            <td>-</td>
                                            <td>-</td>
                                          </tr>';
                                }
                            }
                            
                            // Show ODBC drivers
                            if (!empty($odbcDrivers)) {
                                foreach ($odbcDrivers as $driver) {
                                    $driverInfo = '-';
                                    $driverFile = '-';
                                    $driverVersion = '-';
                                    
                                    // Get additional driver info
                                    if (function_exists('phpinfo') && preg_match('/([^\[]*)\s?(\[(.*)\])?/', $driver, $matches)) {
                                        $driverName = trim($matches[1]);
                                        $driverInfo = isset($matches[3]) ? $matches[3] : '';
                                        
                                        // Try to extract version from driver info
                                        if (preg_match('/version\s*[=:]\s*([0-9.]+)/i', $driverInfo, $vMatches)) {
                                            $driverVersion = $vMatches[1];
                                        }
                                        
                                        // Windows-specific: try to get driver DLL file
                                        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                                            if (preg_match('/dll\s*[=:]\s*([^\s,;]+)/i', $driverInfo, $fMatches)) {
                                                $driverFile = $fMatches[1];
                                            }
                                        }
                                    }
                                    
                                    echo '<tr>
                                            <td><strong>' . htmlspecialchars($driver) . '</strong></td>
                                            <td>Driver</td>
                                            <td>' . htmlspecialchars($driverVersion) . '</td>
                                            <td>' . htmlspecialchars($driverFile) . '</td>
                                          </tr>';
                                }
                            }
                            
                            if (empty($odbcSources) && empty($odbcDrivers)) {
                                echo '<tr><td colspan="4">No ODBC sources or drivers found</td></tr>';
                            }
                            
                            echo '</table>
                                  <div class="eh-info-box">
                                    <strong>PHP ODBC Functions:</strong>
                                    <ul>';
                                    
                            $odbcFunctions = [
                                'odbc_connect' => 'Connect to ODBC data source',
                                'odbc_data_source' => 'Return information about available DSNs',
                                'odbc_drivers' => 'List available ODBC drivers',
                                'odbc_exec' => 'Execute SQL statement',
                                'PDO::__construct' => 'PDO constructor with ODBC support',
                            ];
                            
                            $pdoAvailable = class_exists('\\PDO');
                            $pdoOdbcAvailable = false;
                            
                            if ($pdoAvailable) {
                                $pdoDrivers = \PDO::getAvailableDrivers();
                                $pdoOdbcAvailable = in_array('odbc', $pdoDrivers);
                            }
                            
                            foreach ($odbcFunctions as $function => $description) {
                                $available = function_exists($function) || 
                                            (strpos($function, '::') !== false && 
                                             class_exists(explode('::', $function)[0]));
                                
                                $status = $available ? 
                                    '<span class="eh-status-available">Available</span>' : 
                                    '<span class="eh-status-unavailable">Not Available</span>';
                                
                                echo '<li><code>' . htmlspecialchars($function) . '</code> - ' . 
                                     htmlspecialchars($description) . ' (' . $status . ')</li>';
                            }
                            
                            echo '</ul>';
                                    
                            // Display PDO drivers and connection examples
                            if ($pdoAvailable) {
                                echo '<strong>PDO Drivers Available:</strong><br>';
                                echo '<code>' . implode(', ', \PDO::getAvailableDrivers()) . '</code><br><br>';
                            }
                            
                            // Show PDO ODBC connection examples if available
                            if ($pdoOdbcAvailable) {
                                echo '<strong>PDO ODBC Connection Examples:</strong>
                                <pre><code>// DSN-less connection to MS Access
$dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};DBQ=C:/path/to/database.accdb";
$pdo = new PDO($dsn, "", "");

// Connection using ODBC DSN
$dsn = "odbc:DSN=YourDSNName";
$pdo = new PDO($dsn, "username", "password");

// DSN-less connection to SQL Server
$dsn = "odbc:Driver={SQL Server};Server=ServerName;Database=DatabaseName";
$pdo = new PDO($dsn, "username", "password");</code></pre>';
                            } elseif (!$pdoOdbcAvailable && $pdoAvailable) {
                                echo '<div class="eh-warning"><strong>Warning:</strong> PDO is available, but the ODBC driver is not enabled. Enable the PDO_ODBC extension in php.ini to use PDO with ODBC.</div>';
                            } else {
                                echo '<div class="eh-warning"><strong>Warning:</strong> PDO is not available. Enable the PDO and PDO_ODBC extensions in php.ini.</div>';
                            }
                                    
                            echo '
                                </div>
                    </div>';
                    
                    // PHP.ini tab content
                    echo '<div class="tab-content" id="phpini-tab">
                            <div class="eh-section-header">
                                <div>
                                    <strong>PHP.ini File:</strong> ';
                    
                    // Get loaded php.ini file
                    $iniFile = php_ini_loaded_file();
                    $iniScanDir = php_ini_scanned_files();
                    
                    if ($iniFile) {
                        echo htmlspecialchars($iniFile);
                    } else {
                        echo '<span class="eh-warning">No php.ini file loaded</span>';
                    }
                    
                    echo '</div>
                                <div class="button-group eh-button-group">';

                                    // Add checkbox to hide comments
                                    echo '<label class="eh-checkbox-label">
                                        <input type="checkbox" id="hideCommentsCheckbox" checked onchange="toggleComments()" class="eh-checkbox">
                                        <span class="eh-checkbox-text">Hide comments</span>
                                    </label>';

                                    // Add VS Code button if ini file exists and running locally
                                    if ($isLocalEnvironment && $iniFile && file_exists($iniFile)) {
                                        // Make sure spaces in paths are properly encoded
                                        $encodedPath = str_replace('+', '%20', urlencode($iniFile));
                                        echo '<a href="vscode://file/' . $encodedPath . '" class="vscode-button eh-vscode-button">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M16 18l6-6-6-6"></path>
                                                <path d="M8 6l-6 6 6 6"></path>
                                            </svg>
                                            Open in VS Code
                                        </a>';
                                    }

                                echo '</div>
                            </div>';
                    
                    // Show additional ini scan dir if available
                    if ($iniScanDir) {
                        echo '<div class="eh-info-box-border">
                                <strong>Additional .ini files scan directory:</strong> ' . str_replace("\n", '<br>', htmlspecialchars($iniScanDir)) . '
                              </div>';
                    }
                    
                    // Display php.ini content
                    echo '<pre id="php-ini-content" class="eh-phpini-content">';
                    
                    // Try to load php.ini content
                    if ($iniFile && file_exists($iniFile)) {
                        try {
                            // Check if we can actually read the file
                            if (!is_readable($iniFile)) {
                                echo '<div style="color: #d9534f; margin-bottom: 10px;">
                                    <strong>Warning:</strong> php.ini file exists but is not readable. This may be due to file permissions.
                                    <ul>
                                        <li>File: ' . htmlspecialchars($iniFile) . '</li>
                                        <li>Permissions: ' . substr(sprintf('%o', fileperms($iniFile)), -4) . '</li>
                                        <li>Owner: ' . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($iniFile))['name'] : fileowner($iniFile)) . '</li>
                                    </ul>
                                    Using alternative method to display PHP configuration...
                                </div>';
                                
                                // Alternative method - use ini_get_all() to show current values
                                $iniValues = ini_get_all();
                                ksort($iniValues);
                                
                                foreach ($iniValues as $directive => $info) {
                                    echo '<span class="eh-ini-directive">' . htmlspecialchars($directive) . '</span>=<span class="eh-ini-value">' . htmlspecialchars($info['local_value']) . '</span>' . "\n";
                                }
                            } else {
                                // Normal file reading
                                $iniContent = file_get_contents($iniFile);
                                
                                // Check if we got content
                                if ($iniContent === false) {
                                    throw new \Exception("file_get_contents returned false");
                                }
                                
                                $lines = explode("\n", $iniContent);
                                
                                foreach ($lines as $line) {
                                    $line = htmlspecialchars($line);
                                    $trimmedLine = trim($line);

                                    // Colorize comments (add class for toggling)
                                    if (strpos($trimmedLine, ';') === 0) {
                                        echo '<span class="ini-comment eh-ini-comment">' . $line . "\n" . '</span>';
                                    }
                                    // Skip empty lines when comments are hidden
                                    elseif ($trimmedLine === '') {
                                        echo '<span class="ini-comment" class="eh-tab-content-hidden">' . "\n" . '</span>';
                                    }
                                    // Colorize section headers
                                    elseif (preg_match('/^\[.*\]/', $trimmedLine)) {
                                        echo '<span class="eh-ini-section">' . $line . '</span>' . "\n";
                                    }
                                    // Colorize directives
                                    elseif (preg_match('/^[a-z0-9_\-\.]+\s*=/', $trimmedLine)) {
                                        // Split into directive name and value
                                        $parts = explode('=', $line, 2);
                                        if (count($parts) === 2) {
                                            $directive = $parts[0];
                                            $value = $parts[1];

                                            echo '<span class="eh-ini-directive">' . $directive . '</span>=<span class="eh-ini-value">' . $value . '</span>' . "\n";
                                        } else {
                                            echo $line . "\n";
                                        }
                                    } else {
                                        echo $line . "\n";
                                    }
                                }
                            }
                        } catch (\Throwable $e) {
                            echo '<div style="color: #d9534f; margin-bottom: 10px;">
                                <strong>Error reading php.ini file:</strong> ' . htmlspecialchars($e->getMessage()) . '
                            </div>';
                            
                            // Fallback to showing active PHP settings
                            echo '<div style="margin-top: 10px;">Showing current PHP settings instead:</div>';
                            $iniValues = ini_get_all();
                            ksort($iniValues);
                            
                            foreach ($iniValues as $directive => $info) {
                                echo '<span class="eh-ini-directive">' . htmlspecialchars($directive) . '</span>=<span class="eh-ini-value">' . htmlspecialchars($info['local_value']) . '</span>' . "\n";
                            }
                        }
                    } else {
                        echo "<div style=\"color: #d9534f;\">php.ini file not found at " . htmlspecialchars($iniFile ?: 'unknown location') . ".</div>";
                        
                        // Show current PHP settings instead
                        echo '<div style="margin-top: 10px;">Showing current PHP settings instead:</div>';
                        $iniValues = ini_get_all();
                        ksort($iniValues);
                        
                        foreach ($iniValues as $directive => $info) {
                            echo '<span class="eh-ini-directive">' . htmlspecialchars($directive) . '</span>=<span class="eh-ini-value">' . htmlspecialchars($info['local_value']) . '</span>' . "\n";
                        }
                    }
                    
                    echo '</pre>';
                    
                    // Important directives section
                    echo '<div class="eh-info-box-border">
                            <strong>Important Directives:</strong>
                            <table class="request-data" style="margin-top: 10px;">
                                <tr>
                                    <th style="width: 30%;">Directive</th>
                                    <th>Value</th>
                                </tr>';
                    
                    $importantDirectives = [
                        'memory_limit' => 'Memory limit for PHP scripts',
                        'upload_max_filesize' => 'Maximum allowed upload file size',
                        'post_max_size' => 'Maximum POST data size',
                        'max_execution_time' => 'Maximum script execution time',
                        'display_errors' => 'Display errors on screen',
                        'error_reporting' => 'Error reporting level',
                        'date.timezone' => 'Default timezone',
                        'disable_functions' => 'Disabled functions',
                        'extension_dir' => 'Extension directory',
                        'include_path' => 'Include path for PHP files',
                        'pdo.dsn.*' => 'PDO DSN configuration',
                        'session.save_path' => 'Session save path',
                        'session.gc_maxlifetime' => 'Session lifetime'
                    ];
                    
                    foreach ($importantDirectives as $directive => $description) {
                        $value = ini_get($directive);
                        // Handle wildcard directive patterns
                        if (strpos($directive, '*') !== false) {
                            $pattern = str_replace('*', '.*', $directive);
                            $allIniSettings = ini_get_all();
                            $matchingSettings = [];
                            
                            foreach ($allIniSettings as $settingName => $settingData) {
                                if (preg_match('/^' . $pattern . '$/i', $settingName)) {
                                    $matchingSettings[$settingName] = $settingData['local_value'];
                                }
                            }
                            
                            // Display matching settings
                            if (!empty($matchingSettings)) {
                                foreach ($matchingSettings as $settingName => $settingValue) {
                                    echo '<tr>
                                            <td><strong>' . htmlspecialchars($settingName) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>
                                            <td>' . htmlspecialchars($settingValue ?: '(not set)') . '</td>
                                          </tr>';
                                }
                            } else {
                                echo '<tr>
                                        <td><strong>' . htmlspecialchars($directive) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>
                                        <td><em>No matching settings found</em></td>
                                      </tr>';
                            }
                        } else {
                            echo '<tr>
                                    <td><strong>' . htmlspecialchars($directive) . '</strong><br><small>' . htmlspecialchars($description) . '</small></td>
                                    <td>' . htmlspecialchars($value ?: '(not set)') . '</td>
                                  </tr>';
                        }
                    }
                    
                    echo '</table>
                          </div>
                          
                          <div class="eh-info-box-border">
                            <strong>Loaded Extensions:</strong><br>
                            <div style="margin-top: 5px; line-height: 1.8;">';
                    
                    $extensions = get_loaded_extensions();
                    sort($extensions);
                    
                    foreach ($extensions as $extension) {
                        echo '<span class="eh-extension-badge">' . 
                             htmlspecialchars($extension) . '</span>';
                    }
                    
                    echo '</div>
                          </div>
                        </div>';
                    
                    // PHP Info tab content with custom styling
                    echo '<div class="tab-content" id="phpinfo-tab">
                            <div class="eh-phpinfo-header">
                                <strong>PHP Version:</strong> ' . htmlspecialchars(PHP_VERSION) . '
                                <span class="eh-phpinfo-header-item"><strong>Zend Engine Version:</strong> ' . htmlspecialchars(zend_version()) . '</span>
                                <span class="eh-phpinfo-header-item"><strong>Server API:</strong> ' . htmlspecialchars(PHP_SAPI) . '</span>
                            </div>
                            <div id="phpinfo-content" class="eh-phpinfo-content">';
                    
                    // Capture phpinfo output
                    ob_start();
                    phpinfo(INFO_GENERAL | INFO_CONFIGURATION | INFO_MODULES | INFO_ENVIRONMENT | INFO_VARIABLES);
                    $phpinfo = ob_get_clean();
                    
                    // Extract the body content
                    if (preg_match('/<body[^>]*>(.*)<\/body>/s', $phpinfo, $matches)) {
                        $phpinfoBody = $matches[1];
                        
                        // Clean up and apply our own styling
                        $phpinfoBody = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $phpinfoBody);
                        $phpinfoBody = preg_replace('/<img[^>]*>/s', '', $phpinfoBody);
                        
                        // Replace phpinfo tables with our styled tables
                        $phpinfoBody = str_replace('<table', '<table class="request-data"', $phpinfoBody);
                        
                        // Replace section headers
                        $phpinfoBody = preg_replace('/<h2([^>]*)>(.*?)<\/h2>/s', '<h3 style="background-color: #f5f5f5; padding: 10px; margin-top: 20px; border-radius: 4px;">$2</h3>', $phpinfoBody);
                        
                        // Output the cleaned content
                        echo $phpinfoBody;
                    } else {
                        echo '<div class="eh-warning"><strong>Error:</strong> Unable to parse phpinfo() output.</div>';
                    }
                    
                    echo '</div>
                        </div>';
                
                    // Add diagnostics tab group specifically for tests
                    echo '<div class="card eh-diagnostics-card-mt">
                            <div class="card-header">
                                <strong>Diagnostics & Tests</strong>
                            </div>
                            <div class="tab-buttons">
                                <button class="tab-button active" onclick="showTab(\'env-file\');" data-tab="env-file">Environment File</button>
                                <button class="tab-button" onclick="showTab(\'db-test\');" data-tab="db-test">Database Tests</button>
                                <button class="tab-button" onclick="showTab(\'filesystem\');" data-tab="filesystem">File System Status</button>
                            </div>
                            

                            <div class="tab-content" id="env-file-tab" class="eh-tab-content-visible">
                                <div class="eh-section-header">
                                    <div>';
                    
                    // Locate .env* files (supporting multiple environments)
                    $envFiles = [];
                    $searchDirs = [
                        realpath(__DIR__ . '/../..'),  // project root
                        realpath(__DIR__ . '/..'),     // app directory
                    ];

                    foreach ($searchDirs as $dir) {
                        if ($dir && is_dir($dir)) {
                            $files = glob($dir . '/.env*');
                            if ($files !== false && is_array($files)) {
                                foreach ($files as $file) {
                                    // Skip .example files
                                    if (is_file($file) && !preg_match('/\.example$/i', $file)) {
                                        $envFiles[] = $file;
                                    }
                                }
                            }
                        }
                    }

                    // Remove duplicates
                    $envFiles = array_unique($envFiles);

                    if (!empty($envFiles)) {
                        echo '<strong>Environment Files:</strong> ' . count($envFiles) . ' file(s) found';
                        echo '<div style="margin-top: 5px;">';
                        foreach ($envFiles as $file) {
                            echo '<div style="margin-left: 10px;">- ' . htmlspecialchars(basename($file)) . '</div>';
                        }
                        echo '</div>';
                    } else {
                        echo '<strong>Environment Files:</strong> <span class="eh-warning">No .env files found</span>';
                    }

                    echo '</div>
                                </div>';

                    // Show redacted .env file content for all found files
                    if (!empty($envFiles)) {
                        foreach ($envFiles as $envFile) {
                            if (file_exists($envFile) && is_readable($envFile)) {
                                echo '<div style="margin-bottom: 15px;">
                                        <div style="padding: 5px 10px; background-color: #2d2d2d; color: #569CD6; font-weight: bold; border-radius: 3px 3px 0 0;">
                                            ' . htmlspecialchars(basename($envFile)) . '
                                        </div>
                                        <pre style="margin: 0; max-height: 300px; overflow: auto; background-color: #1e1e1e; color: #d4d4d4; padding: 10px; border-radius: 0 0 3px 3px;">';

                                $redactedContent = self::getRedactedEnvContent($envFile);
                                $lines = explode("\n", $redactedContent);

                                foreach ($lines as $line) {
                                    $line = htmlspecialchars($line);

                                    // Colorize comments
                                    if (strpos(trim($line), '#') === 0 || strpos(trim($line), '//') === 0) {
                                        echo '<span style="color: #6A9955;">' . $line . '</span>';
                                    }
                                    // Colorize key-value pairs
                                    elseif (strpos($line, '=') !== false) {
                                        list($key, $value) = explode('=', $line, 2);
                                        echo '<span class="eh-ini-directive">' . $key . '</span>=<span style="color: ' .
                                            (strpos($value, '*****') !== false ? '#FF7B72' : '#CE9178') .
                                            ';">' . $value . '</span>';
                                    } else {
                                        echo $line;
                                    }

                                    echo "\n";
                                }

                                echo '</pre>
                                      </div>';
                            }
                        }

                        echo '<div class="eh-info-box-border">
                                <span style="color:var(--blue);"><strong>Note:</strong> Sensitive information (passwords, tokens, etc.) has been automatically redacted.</span>
                              </div>';
                    } else {
                        echo '<pre style="margin: 0; max-height: 400px; overflow: auto; background-color: #1e1e1e; color: #d4d4d4; padding: 10px;">
                                <div class="eh-warning">No .env files found or files are not readable.</div>
                              </pre>';
                    }

                    echo '</div>
                            
                            <!-- Database Tests Tab -->
                            <div class="tab-content" id="db-test-tab" class="eh-tab-content-hidden">
                                <div style="padding: 10px; background-color: #f5f5f5; border-radius: 4px 4px 0 0;">
                                    <strong>Database Connection Tests</strong>
                                </div>
                                <div style="padding: 15px;">';
                    
                    // Get database connections from Config class if available
                    $dbConnections = [];
                    if (class_exists('\\App\\Library\\Config')) {
                        // Try to get all database connections from config
                        try {
                            $config = \App\Library\Config::all();
                            if (isset($config['database']['connections']) && is_array($config['database']['connections'])) {
                                $dbConnections = $config['database']['connections'];
                            }
                        } catch (\Exception $e) {
                            // Config class might throw exception if not initialized
                        }
                    }

                    // Fallback: Check for legacy $GLOBALS['Application'] connections
                    if (empty($dbConnections)) {
                        try {
                            if (isset($GLOBALS['Application']) && is_array($GLOBALS['Application'])) {
                                if (isset($GLOBALS['Application']['conn_data']) && !empty($GLOBALS['Application']['conn_data'])) {
                                    $dbConnections[] = [
                                        'name' => 'conn_data (Legacy)',
                                        'driver' => 'access',
                                        'connection_string' => $GLOBALS['Application']['conn_data'],
                                        'username' => '',
                                        'password' => ''
                                    ];
                                }
                                if (isset($GLOBALS['Application']['conn_rep']) && !empty($GLOBALS['Application']['conn_rep'])) {
                                    $dbConnections[] = [
                                        'name' => 'conn_rep (Legacy)',
                                        'driver' => 'access',
                                        'connection_string' => $GLOBALS['Application']['conn_rep'],
                                        'username' => '',
                                        'password' => ''
                                    ];
                                }
                            }
                        } catch (\Throwable $e) {
                            // Silently ignore if $GLOBALS['Application'] is not accessible
                        }
                    }
                    
                    if (!empty($dbConnections)) {
                        echo '<table class="request-data">
                                <tr>
                                    <th>Connection Name</th>
                                    <th>Driver</th>
                                    <th>Status</th>
                                    <th>Details</th>
                                </tr>';
                        
                        foreach ($dbConnections as $connection) {
                            if (!isset($connection['name']) || !isset($connection['driver'])) {
                                continue;
                            }
                            
                            $name = $connection['name'];
                            $driver = $connection['driver'];
                            $status = 'Not Tested';
                            $details = '';
                            
                            // Test connection (safely - avoiding exceptions)
                            try {
                                switch ($driver) {
                                    case 'mysql':
                                        $dsn = "mysql:host=" . ($connection['host'] ?? 'localhost') . 
                                               ";dbname=" . ($connection['database'] ?? '');
                                        $status = self::testDatabaseConnection(
                                            $dsn, 
                                            $connection['username'] ?? '', 
                                            $connection['password'] ?? ''
                                        );
                                        break;
                                        
                                    case 'sqlsrv':
                                        $dsn = "sqlsrv:Server=" . ($connection['host'] ?? 'localhost') . 
                                               ";Database=" . ($connection['database'] ?? '');
                                        $status = self::testDatabaseConnection(
                                            $dsn, 
                                            $connection['username'] ?? '', 
                                            $connection['password'] ?? ''
                                        );
                                        break;
                                        
                                    case 'access':
                                        if (isset($connection['connection_string'])) {
                                            $dsn = $connection['connection_string'];
                                            $status = self::testDatabaseConnection(
                                                $dsn, 
                                                $connection['username'] ?? '', 
                                                $connection['password'] ?? ''
                                            );
                                        } elseif (isset($connection['database_path'])) {
                                            $dsn = "odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};DBQ=" . 
                                                  $connection['database_path'];
                                            $status = self::testDatabaseConnection(
                                                $dsn, 
                                                $connection['username'] ?? '', 
                                                $connection['password'] ?? ''
                                            );
                                        } else {
                                            $status = ['success' => false, 'message' => 'Missing database path or connection string'];
                                        }
                                        break;
                                        
                                    default:
                                        $status = ['success' => false, 'message' => 'Unsupported database driver'];
                                }
                            } catch (\Exception $e) {
                                $status = ['success' => false, 'message' => 'Error testing connection: ' . $e->getMessage()];
                            }
                            
                            $statusClass = ($status['success'] ?? false) ? 'green' : 'red';
                            $statusText = ($status['success'] ?? false) ? 'Connected' : 'Failed';
                            $details = $status['message'] ?? '';
                            
                            echo '<tr>
                                    <td><strong>' . htmlspecialchars($name) . '</strong></td>
                                    <td>' . htmlspecialchars($driver) . '</td>
                                    <td style="color: ' . $statusClass . ';">' . $statusText . '</td>
                                    <td>' . htmlspecialchars($details) . '</td>
                                  </tr>';
                        }
                        
                        echo '</table>';
                    } else {
                        echo '<div class="alert" style="background-color: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">
                                No database connections found in configuration.
                              </div>';
                    }
                    
                    echo '</div>
                            </div>
                            
                            <!-- File System Status Tab -->
                            <div class="tab-content" id="filesystem-tab" class="eh-tab-content-hidden">
                                <div style="padding: 10px; background-color: #f5f5f5; border-radius: 4px 4px 0 0;">
                                    <strong>File System Status</strong>
                                </div>
                                <div style="padding: 15px;">';
                    
                    // System information first
                    echo '<div style="margin-bottom: 20px;">
                            <h4>System Information</h4>
                            <table class="request-data">
                                <tr>
                                    <th>Item</th>
                                    <th>Value</th>
                                </tr>
                                <tr>
                                    <td>Server OS</td>
                                    <td>' . htmlspecialchars(PHP_OS) . '</td>
                                </tr>
                                <tr>
                                    <td>Current Working Directory</td>
                                    <td>' . htmlspecialchars(getcwd()) . '</td>
                                </tr>';

                    // Only show PHP User on Unix/Linux systems where it's meaningful
                    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                        $userInfo = @posix_getpwuid(posix_geteuid());
                        if ($userInfo && isset($userInfo['name'])) {
                            echo '<tr>
                                    <td>PHP User</td>
                                    <td>' . htmlspecialchars($userInfo['name']) . '</td>
                                  </tr>';
                        }
                    } elseif (function_exists('get_current_user')) {
                        $currentUser = get_current_user();
                        if ($currentUser && $currentUser !== 'Unknown') {
                            echo '<tr>
                                    <td>PHP Process Owner</td>
                                    <td>' . htmlspecialchars($currentUser) . '</td>
                                  </tr>';
                        }
                    }

                    echo '<tr>
                                    <td>Disk Free Space</td>
                                    <td>' . htmlspecialchars(self::formatFileSize(disk_free_space('.'))) . ' / ' .
                                        htmlspecialchars(self::formatFileSize(disk_total_space('.'))) . '</td>
                                </tr>
                            </table>
                          </div>';
                    
                    // Check important directories
                    $importantDirs = [
                        '.' => 'Root Directory',
                        './app' => 'App Directory',
                        './public' => 'Public Directory',
                        './uploads' => 'Uploads Directory',
                        './logs' => 'Logs Directory',
                        './cache' => 'Cache Directory',
                        './storage' => 'Storage Directory',
                        './vendor' => 'Vendor Directory'
                    ];
                    
                    echo '<div>
                            <h4>Directory Permissions</h4>
                            <table class="request-data">
                                <tr>
                                    <th>Directory</th>
                                    <th>Read</th>
                                    <th>Write</th>
                                    <th>Execute</th>
                                    <th>Permissions</th>
                                </tr>';

                    foreach ($importantDirs as $dir => $label) {
                        $path = realpath($dir);
                        $exists = $path && is_dir($path);

                        // Skip non-existing directories
                        if (!$exists) {
                            continue;
                        }

                        $readable = is_readable($path);
                        $writable = is_writable($path);
                        $executable = is_executable($path);
                        $perms = substr(sprintf('%o', fileperms($path)), -4);

                        echo '<tr>
                                <td><strong>' . htmlspecialchars($label) . '</strong><br><small>' . htmlspecialchars($path) . '</small></td>
                                <td>' . self::renderPermissionStatus($readable) . '</td>
                                <td>' . self::renderPermissionStatus($writable) . '</td>
                                <td>' . self::renderPermissionStatus($executable) . '</td>
                                <td>' . $perms . '</td>
                              </tr>';
                    }

                    echo '</table>
                          </div>';
                    
                    echo '</div>
                            </div>
                        </div>';
                    
                    // Add logs tab content if in development mode
                    if (self::$config['environment'] === 'development' && 
                        self::$config['error_log_file'] && 
                        file_exists(self::$config['error_log_file'])) {
                        
                        $logEntries = self::getRecentLogEntries(self::$config['error_log_file'], 100);
                        
                        if (!empty($logEntries)) {
                            echo '<div class="tab-content" id="logs-tab" class="eh-tab-content-hidden">
                                    <div class="eh-section-header">
                                        <div>
                                            <strong>Log File:</strong> ' . htmlspecialchars(self::$config['error_log_file']) . '
                                            <span class="eh-phpinfo-header-item"><strong>Size:</strong> ' . 
                                            htmlspecialchars(self::formatFileSize(filesize(self::$config['error_log_file']))) . '</span>
                                        </div>
                                        <div class="button-group">
                                        </div>
                                    </div>
                                    <pre id="log-entries" style="margin: 0; max-height: 400px; overflow: auto; background-color: #1e1e1e; color: #d4d4d4; padding: 10px;">';
                                    
                            foreach ($logEntries as $entry) {
                                // Color code the log entries based on their content
                                $line = htmlspecialchars($entry);
                                
                                // Apply styling to ERROR, WARNING, NOTICE, etc.
                                $coloredLine = preg_replace(
                                    '/\[(ERROR|FATAL|CRITICAL|ALERT|EMERGENCY)\]/i',
                                    '<span style="color: #ff5f5f; font-weight: bold;">[$1]</span>',
                                    $line
                                );
                                $coloredLine = preg_replace(
                                    '/\[(WARNING|WARN)\]/i',
                                    '<span style="color: #ffdf5f; font-weight: bold;">[$1]</span>',
                                    $coloredLine
                                );
                                $coloredLine = preg_replace(
                                    '/\[(INFO|NOTICE)\]/i',
                                    '<span style="color: #5fa9ff; font-weight: bold;">[$1]</span>',
                                    $coloredLine
                                );
                                $coloredLine = preg_replace(
                                    '/\[(DEBUG)\]/i',
                                    '<span style="color: #5fdfa7; font-weight: bold;">[$1]</span>',
                                    $coloredLine
                                );
                                
                                // Color timestamps
                                $coloredLine = preg_replace(
                                    '/(\[\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:[\+-]\d{2}:\d{2})?\])/i',
                                    '<span style="color: #5fa9ff;">$1</span>',
                                    $coloredLine
                                );
                                
                                // Color stack traces
                                $coloredLine = preg_replace(
                                    '/(Stack trace:|#\d+)/i',
                                    '<span style="color: #ffdf5f; font-weight: bold;">$1</span>',
                                    $coloredLine
                                );
                                
                                echo $coloredLine . "\n";
                            }
                            
                            echo '</pre>
                                </div>';
                        }
                    }
                    
                echo '</div>
                </div>';
        }

        // Show previous exception if any
        $previous = $exception->getPrevious();
        if ($previous) {
            echo '<div class="card">
                    <div class="card-header">Previous Exception</div>
                    <div class="card-body">
                        <div class="error-details">
                            <strong>' . get_class($previous) . ':</strong> ' . htmlspecialchars($previous->getMessage()) . '
                            <div class="file-path">
                                in ' . htmlspecialchars($previous->getFile()) . ' on line ' . $previous->getLine() . '
                            </div>
                        </div>
                    </div>
                </div>';
        }

        echo '</div>
        <script>
            function showTab(tabName) {
                // Hide all tab contents
                var tabContents = document.getElementsByClassName("tab-content");
                for (var i = 0; i < tabContents.length; i++) {
                    tabContents[i].style.display = "none";
                }
                
                // Remove active class from all buttons
                var tabButtons = document.getElementsByClassName("tab-button");
                for (var i = 0; i < tabButtons.length; i++) {
                    tabButtons[i].className = tabButtons[i].className.replace(" active", "");
                }
                
                // Show the selected tab
                var selectedTab = document.getElementById(tabName + "-tab");
                if (selectedTab) {
                    selectedTab.style.display = "block";
                }
                
                // Make button active
                for (var i = 0; i < tabButtons.length; i++) {
                    if (tabButtons[i].getAttribute("data-tab") === tabName) {
                        tabButtons[i].className += " active";
                    }
                }
                
                return false;
            }
            
            // Toggle comments visibility in php.ini display
            function toggleComments() {
                var checkbox = document.getElementById("hideCommentsCheckbox");
                var comments = document.getElementsByClassName("ini-comment");

                for (var i = 0; i < comments.length; i++) {
                    if (checkbox.checked) {
                        comments[i].classList.add("hidden");
                    } else {
                        comments[i].classList.remove("hidden");
                    }
                }
            }

            // Apply initial state (comments hidden by default)
            document.addEventListener("DOMContentLoaded", function() {
                toggleComments();
            });

            // Simple function to ask Claude for help
            function askClaudeHelp() {
                var errorType = document.querySelector("header h1").innerText || "Unknown Error";
                var errorMessage = document.querySelector(".exception-message").innerText || "No message available";
                var errorLocation = document.querySelector(".exception-type").innerText || "Unknown location";
                var sourceCode = document.querySelector(".code-content").innerHTML || "No code available";
                
                var prompt = "I need help debugging the following PHP error:\\n\\n";
                prompt += "Error Type: " + errorType + "\\n";
                prompt += "Error Message: " + errorMessage + "\\n";
                prompt += "Location: " + errorLocation + "\\n\\n";
                prompt += "Code Context:\\n```php\\n" + sourceCode + "\\n```\\n\\n";
                prompt += "Please explain what is causing this error and how to fix it. Thank you!";
                
                window.open("https://claude.ai/new?q=" + encodeURIComponent(prompt), "_blank");
                return false;
            }
        </script>
    </body>
</html>';
    }

    /**
     * Get formatted trace frames from an exception
     *
     * @param \Throwable $exception The exception
     * @return array Formatted stack trace frames
     */
    protected static function getTraceFrames(\Throwable $exception): array
    {
        $trace = $exception->getTrace();

        // Add the exception's file and line as the first frame
        array_unshift($trace, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        return $trace;
    }

    /**
     * Get lines of code from a file
     *
     * @param string $file File path
     * @param int $lineNumber Line number to focus on
     * @param int $context Number of lines before and after the focus line
     * @return array Array of lines
     */
    protected static function getFileLines(string $file, int $lineNumber, int $context = 5): array
    {
        $lines = [];

        if (file_exists($file) && is_readable($file)) {
            $fileContent = file($file);
            $start = max(0, $lineNumber - $context - 1);
            $end = min(count($fileContent), $lineNumber + $context);

            for ($i = $start; $i < $end; $i++) {
                $lines[$i + 1] = isset($fileContent[$i]) ? $fileContent[$i] : '';
            }
        }

        return $lines;
    }

    /**
     * Set HTTP response code safely
     * 
     * @param int $statusCode The HTTP status code to set
     * @return bool Whether the status code was successfully set
     */
    protected static function setHttpResponseCode(int $statusCode): bool
    {
        // Check if headers have already been sent
        if (headers_sent($file, $line)) {
            // Log the issue but don't try to set the status code
            if (self::$config['log_errors']) {
                error_log("Warning: Could not set HTTP status code to {$statusCode} - headers already sent in {$file} on line {$line}");
            }
            return false;
        }
        
        // Set the status code
        http_response_code($statusCode);
        return true;
    }
    
    /**
     * Format a file size in bytes to a human-readable format
     * 
     * @param int $bytes File size in bytes
     * @param int $precision Number of decimal places to include
     * @return string Formatted file size with units
     */
    protected static function formatFileSize(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    /**
     * Get redacted content of the .env file
     * Automatically masks sensitive information like passwords and keys
     * 
     * @param string $envFilePath Path to the .env file
     * @return string Redacted content of the .env file
     */
    protected static function getRedactedEnvContent(string $envFilePath): string
    {
        if (!file_exists($envFilePath) || !is_readable($envFilePath)) {
            return "Unable to read .env file";
        }
        
        $content = file_get_contents($envFilePath);
        $lines = explode("\n", $content);
        $redactedLines = [];
        
        // Pattern to identify sensitive keys
        $sensitivePatterns = [
            '/password/i', '/secret/i', '/key/i', '/token/i',
            '/auth/i', '/credential/i', '/pwd/i', '/apikey/i',
            '/client_id/i', '/username/i'
        ];
        
        foreach ($lines as $line) {
            // Skip empty lines and comments
            if (empty(trim($line)) || strpos(trim($line), '#') === 0) {
                $redactedLines[] = $line;
                continue;
            }
            
            // Check if the line contains a key-value pair
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Check if the key contains any sensitive patterns
                $isSensitive = false;
                foreach ($sensitivePatterns as $pattern) {
                    if (preg_match($pattern, $key)) {
                        $isSensitive = true;
                        break;
                    }
                }
                
                // Redact sensitive values
                if ($isSensitive && !empty($value)) {
                    // Keep first character if longer than 3 chars
                    if (strlen($value) > 3) {
                        $redactedLines[] = $key . '=' . substr($value, 0, 1) . '*****';
                    } else {
                        $redactedLines[] = $key . '=*****';
                    }
                } else {
                    $redactedLines[] = $line;
                }
            } else {
                $redactedLines[] = $line;
            }
        }
        
        return implode("\n", $redactedLines);
    }
    
    /**
     * Test a database connection without exposing credentials
     * 
     * @param string $dsn DSN connection string
     * @param string $username Database username
     * @param string $password Database password
     * @return array Result with success status and message
     */
    protected static function testDatabaseConnection(string $dsn, string $username, string $password): array
    {
        // Safety measure: truncate any overly long DSN for displaying in errors
        $displayDsn = strlen($dsn) > 50 ? substr($dsn, 0, 47) . '...' : $dsn;
        // Mask credentials from the displayed DSN
        $displayDsn = preg_replace('/password=[^;]*/i', 'password=*****', $displayDsn);
        
        try {
            // Set a short timeout for connection
            $options = [
                \PDO::ATTR_TIMEOUT => 5, // 5 seconds timeout
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
            ];
            
            // Check if PDO class exists
            if (!class_exists('\\PDO')) {
                return [
                    'success' => false,
                    'message' => 'PDO extension not available'
                ];
            }
            
            // Attempt connection
            $pdo = new \PDO($dsn, $username, $password, $options);
            
            // If we get here, the connection succeeded
            return [
                'success' => true,
                'message' => 'Successfully connected'
            ];
        } catch (\PDOException $e) {
            // Strip credentials from error message
            $errorMessage = $e->getMessage();
            $errorMessage = preg_replace('/password=[^;]*/i', 'password=*****', $errorMessage);
            
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $errorMessage
            ];
        } catch (\Exception $e) {
            // Handle any other exceptions
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Render a visual indicator for permission status
     * 
     * @param bool $status Permission status
     * @return string HTML for the status indicator
     */
    protected static function renderPermissionStatus(bool $status): string
    {
        if ($status) {
            return '<span style="color: green;">✓</span>';
        } else {
            return '<span style="color: red;">✗</span>';
        }
    }
    
    /**
     * Get the most recent log entries from a log file
     * 
     * @param string $logFile Path to the log file
     * @param int $numLines Number of lines to retrieve
     * @return array An array of log entries, most recent first
     */
    protected static function getRecentLogEntries(string $logFile, int $numLines = 100): array
    {
        // If log file doesn't exist or isn't readable, return empty array
        if (!file_exists($logFile) || !is_readable($logFile)) {
            return [];
        }
        
        // Use efficient approach to read the last N lines without loading the whole file
        $lines = [];
        $fp = fopen($logFile, 'r');
        
        // Get the file size
        fseek($fp, 0, SEEK_END);
        $fileSize = ftell($fp);
        
        // No lines if the file is empty
        if ($fileSize === 0) {
            fclose($fp);
            return [];
        }
        
        // Average line length - adjust based on your log format
        $avgLineLength = 300; 
        
        // Calculate the approximate position to start reading
        // Read extra lines to ensure we get at least the number requested
        $seekPosition = max(0, $fileSize - ($numLines * $avgLineLength * 2));
        
        // Seek to the position
        fseek($fp, $seekPosition);
        
        // If we're not at the beginning of the file, discard the first line
        // because it's likely to be a partial line
        if ($seekPosition > 0) {
            fgets($fp);
        }
        
        // Read all remaining lines
        $allLines = [];
        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false && !empty(trim($line))) {
                $allLines[] = rtrim($line);
            }
        }
        
        fclose($fp);
        
        // Take only the requested number of lines, most recent first
        $lines = array_slice(array_reverse($allLines), 0, $numLines);

        return $lines;
    }

    /**
     * Handle bootstrap errors with user-friendly messaging (Change #414)
     *
     * Called from _bootstrap.php when critical initialization fails
     * Provides clear error messages for common bootstrap issues
     *
     * @param string $message Error message
     * @param string $errorCode Error code for identification
     * @return void
     */
    public static function handleBootstrapError(string $message, string $errorCode): void
    {
        // Detect if we're in debug mode
        $debug = false;
        if (isset($GLOBALS['Application']['omgeving'])) {
            $env = strtoupper($GLOBALS['Application']['omgeving']);
            $debug = in_array($env, ['L', 'O', 'T']); // Local, Development, Test
        }

        // Build user-friendly error page
        $title = 'Application Bootstrap Error';
        $heading = 'Configuration Error';

        // Map error codes to user-friendly descriptions
        $descriptions = [
            'BOOTSTRAP_SESSION_DIR_CREATE' => [
                'title' => 'Session Directory Creation Failed',
                'description' => 'The application could not create a directory to store session data.',
                'solution' => 'Ensure the web server has write permissions to the application directory.'
            ],
            'BOOTSTRAP_SESSION_DIR_PERMS' => [
                'title' => 'Session Directory Not Writable',
                'description' => 'The session directory exists but the web server cannot write to it.',
                'solution' => 'Run: <code>chmod 755 sessions/</code> (or 777 if needed) in the application root directory.'
            ],
            'BOOTSTRAP_SESSION_START' => [
                'title' => 'Session Start Failed',
                'description' => 'The application could not initialize the session system.',
                'solution' => 'Check PHP session configuration and server permissions.'
            ]
        ];

        $errorInfo = $descriptions[$errorCode] ?? [
            'title' => 'Bootstrap Error',
            'description' => 'An error occurred during application initialization.',
            'solution' => 'Contact system administrator.'
        ];

        // HTML output with styling
        echo '<!DOCTYPE html>';
        echo '<html lang="en">';
        echo '<head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>' . htmlspecialchars($title) . '</title>';
        echo '<style>';
        echo 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 0; padding: 0; background: #f5f5f5; }';
        echo '.error-container { max-width: 800px; margin: 40px auto; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }';
        echo '.error-header { background: #dc3545; color: white; padding: 20px 30px; border-radius: 8px 8px 0 0; }';
        echo '.error-header h1 { margin: 0; font-size: 24px; }';
        echo '.error-body { padding: 30px; }';
        echo '.error-title { color: #dc3545; font-size: 20px; margin-bottom: 15px; }';
        echo '.error-message { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 20px; }';
        echo '.error-solution { background: #d1ecf1; border-left: 4px solid #0c5460; padding: 15px; margin-bottom: 20px; }';
        echo '.error-solution strong { color: #0c5460; }';
        echo '.technical-details { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; border-radius: 4px; font-family: monospace; font-size: 13px; }';
        echo '.code { background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-family: monospace; }';
        echo '</style>
    <link rel="stylesheet" href="/library/css/errorhandler.css">
';
        echo '</head>';
        echo '<body>';
        echo '<div class="error-container">';
        echo '<div class="error-header" style="display:flex;justify-content:space-between;align-items:center">';
        echo '<h1 style="margin:0">⚠️ ' . htmlspecialchars($errorInfo['title']) . '</h1>';
        echo '<button onclick="var t=document.querySelector(\'.error-message\').textContent+\'\\n\'+document.querySelector(\'.error-solution\').textContent;navigator.clipboard.writeText(t).then(function(){this.textContent=\'Gekopieerd!\';setTimeout(function(){this.innerHTML=\'&#128203; Kopieer\'}.bind(this),2000)}.bind(this))" style="background:#fff3;color:white;border:1px solid #fff6;border-radius:4px;padding:5px 10px;cursor:pointer;font-size:12px" title="Kopieer foutmelding">&#128203; Kopieer</button>';
        echo '</div>';
        echo '<div class="error-body">';
        echo '<div class="error-message">';
        echo '<strong>What happened:</strong><br>';
        echo htmlspecialchars($errorInfo['description']);
        echo '</div>';
        echo '<div class="error-solution">';
        echo '<strong>How to fix:</strong><br>';
        echo $errorInfo['solution']; // Allow HTML for <code> tags
        echo '</div>';

        if ($debug) {
            echo '<div class="technical-details">';
            echo '<strong>Technical Details (Debug Mode):</strong><br>';
            echo 'Error Code: ' . htmlspecialchars($errorCode) . '<br>';
            echo 'Message: ' . htmlspecialchars($message) . '<br>';
            echo 'PHP Version: ' . PHP_VERSION . '<br>';
            echo 'Server: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . '<br>';
            echo 'Document Root: ' . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . '<br>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
        echo '</body>';
        echo '</html>';

        // Log the error
        error_log("Bootstrap Error [$errorCode]: $message");

        // Exit to prevent further execution
        exit(1);
    }
}