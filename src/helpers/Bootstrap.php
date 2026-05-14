<?php
/**
 * Bootstrap - Initializes the application environment
 *
 * Refactored from the monolithic _bootstrap.php into a reusable class.
 * Each project calls Bootstrap::init() with project-specific configuration.
 *
 * @package App\Library
 */

namespace App\Library;

class Bootstrap
{
    /** @var string Project root directory */
    private static string $rootDir = '';

    /** @var array Configuration options */
    private static array $config = [];

    /** @var array Timing measurements */
    private static array $timing = [];

    /** @var float Start time */
    private static float $startTime = 0;

    /**
     * Initialize the application.
     *
     * @param string $rootDir Project root directory (where _bootstrap.php lives)
     * @param array $config Configuration options:
     *   - app_config:    Path to app.php (project config)
     *   - global_asa:    Path to global.asa.php (secrets/init)
     *   - session_dir:   Path to sessions directory
     *   - constants_file: Path to _bootstrap_constants.inc
     *   - log_dir:       Path to logs directory
     *   - db_dir:        Path to database directory
     *   - env_file:      Specific .env file to load (null = auto-detect)
     */
    public static function init(string $rootDir, array $config = []): void
    {
        self::$startTime = $GLOBALS['_bootstrap_start'] ?? microtime(true);
        $GLOBALS['_bootstrap_start'] = self::$startTime;
        $GLOBALS['_bootstrap_timing'] = [];

        self::$rootDir = $rootDir;
        self::$config = array_merge([
            'app_config'     => $rootDir . '/app.php',
            'global_asa'     => $rootDir . '/global.asa.php',
            'session_dir'    => $rootDir . '/sessions',
            'constants_file' => $rootDir . '/_bootstrap_constants.inc',
            'log_dir'        => $rootDir . '/logs',
            'db_dir'         => $rootDir . '/db',
            'env_file'       => null, // auto-detect
        ], $config);

        self::initEncoding();
        self::recordTiming('init');

        self::initSession();
        self::recordTiming('session');

        self::loadConstants();

        self::detectAndLoadEnv();
        self::recordTiming('env_detect');

        self::configureErrorDisplay();

        self::sqliteEmergencyRecovery();

        self::loadDotenv();
        self::recordTiming('autoload');

        self::initApplication();
        self::recordTiming('app_init');

        self::registerErrorHandler();
        self::loadSpecificHelpers();
        self::defineAdoConstants();
        self::recordTiming('error_handler');

        // Helpers are loaded via Composer autoload, but we still need aliases
        self::createClassAliases();
        self::recordTiming('helpers');

        self::loadLegacyClasses();
        self::recordTiming('aliases');

        self::loadLegacyLibFiles();
        self::recordTiming('lib_inc');

        self::initGlobalVariables();

        self::loadFieldTypes();
        self::recordTiming('fldtypes');

        self::loadCmaRepository();
        self::recordTiming('cma_repo');

        self::loadCmaConnect();
        self::recordTiming('cma_connect');

        self::initDatabaseConnections();
        self::recordTiming('db_init');

        self::initSessionData();

        self::checkMigrations();

        self::recordTiming('total');
    }

    /**
     * Get the platform package version from Composer.
     */
    public static function getPlatformVersion(): string
    {
        // Try reading from Composer's installed.json
        $installedFile = self::$rootDir . '/vendor/composer/installed.json';
        if (file_exists($installedFile)) {
            $data = json_decode(file_get_contents($installedFile), true);
            $packages = $data['packages'] ?? $data; // Composer 2 vs 1
            foreach ($packages as $package) {
                if (($package['name'] ?? '') === 'stenversonline/platform') {
                    return $package['version'] ?? 'dev';
                }
            }
        }

        // Fallback: try InstalledVersions (Composer 2 runtime API)
        if (class_exists('Composer\InstalledVersions')) {
            try {
                return \Composer\InstalledVersions::getPrettyVersion('stenversonline/platform') ?? 'dev';
            } catch (\Exception $e) {
                // Package not installed via Composer
            }
        }

        return 'dev';
    }

    /**
     * Get the project root directory.
     */
    public static function getRootDir(): string
    {
        return self::$rootDir;
    }

    /**
     * Get a config value.
     */
    public static function getConfig(string $key, $default = null)
    {
        return self::$config[$key] ?? $default;
    }

    // =========================================================================
    // Private initialization methods
    // =========================================================================

    private static function initEncoding(): void
    {
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
            mb_http_output('UTF-8');
        }
    }

    private static function initSession(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $sessionDir = self::$config['session_dir'];

        // Cache session directory check in APCu
        $sessionDirChecked = false;
        if (function_exists('apcu_fetch') && apcu_fetch('session_dir_ok') === true) {
            session_save_path($sessionDir);
            $sessionDirChecked = true;
        }

        if (!$sessionDirChecked && !is_dir($sessionDir)) {
            if (!@mkdir($sessionDir, 0755, true)) {
                self::bootstrapError("Failed to create session directory: $sessionDir", 'BOOTSTRAP_SESSION_DIR_CREATE');
            } else {
                session_save_path($sessionDir);
                if (function_exists('apcu_store')) {
                    apcu_store('session_dir_ok', true, 3600);
                }
            }
        } elseif (!$sessionDirChecked) {
            if (!is_writable($sessionDir)) {
                self::bootstrapError("Session directory not writable: $sessionDir", 'BOOTSTRAP_SESSION_DIR_PERMS');
                @chmod($sessionDir, 0777);
            } else {
                session_save_path($sessionDir);
                if (function_exists('apcu_store')) {
                    apcu_store('session_dir_ok', true, 3600);
                }
            }
        }

        session_set_cookie_params([
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_cache_limiter('');

        try {
            session_start();
        } catch (\Exception $e) {
            self::bootstrapError("Failed to start session: " . $e->getMessage(), 'BOOTSTRAP_SESSION_START');
        }
    }

    private static function loadConstants(): void
    {
        $file = self::$config['constants_file'];
        if ($file && file_exists($file)) {
            require_once $file;
        }
    }

    private static function detectAndLoadEnv(): void
    {
        $envFile = self::$config['env_file'];
        if ($envFile) {
            // Explicit env file specified
            $GLOBALS['_env_file'] = $envFile;
            return;
        }

        // Auto-detect environment
        $appEnv = $_ENV['APP_ENVIRONMENT'] ?? $_SERVER['APP_ENVIRONMENT'] ?? null;

        $envFileMap = [
            'L' => '.env.local',
            'O' => '.env.development',
            'T' => '.env.test',
            'A' => '.env.acceptance',
            'P' => '.env.production'
        ];

        $envFile = '.env'; // Default fallback
        if ($appEnv && isset($envFileMap[$appEnv])) {
            $envFile = $envFileMap[$appEnv];
        } else {
            foreach (['L', 'O', 'T', 'A', 'P'] as $envCode) {
                if (file_exists(self::$rootDir . '/' . $envFileMap[$envCode])) {
                    $envFile = $envFileMap[$envCode];
                    $appEnv = $envCode;
                    break;
                }
            }
        }

        $GLOBALS['_env_file'] = $envFile;
        $GLOBALS['_app_env'] = $appEnv;
    }

    private static function configureErrorDisplay(): void
    {
        $appEnv = $GLOBALS['_app_env'] ?? null;
        if ($appEnv !== 'P') {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }
    }

    private static function sqliteEmergencyRecovery(): void
    {
        $dbDir = self::$config['db_dir'];
        $flagFile = $dbDir . '/sqlite_emergency_recovery.flag';

        if (!file_exists($flagFile)) {
            return;
        }

        $dbPath = $dbDir . '/cmausers.sqlite';
        $log = [];

        $flagContent = @file_get_contents($flagFile);
        $log[] = 'Emergency recovery triggered at ' . date('Y-m-d H:i:s');
        $log[] = 'Flag content: ' . ($flagContent ?: '(empty)');

        foreach (['-wal', '-shm'] as $suffix) {
            $file = $dbPath . $suffix;
            if (file_exists($file)) {
                $log[] = (@unlink($file) ? 'Deleted: ' : 'Failed to delete: ') . $file;
            }
        }

        @file_put_contents($dbDir . '/sqlite_recovery.log', implode("\n", $log) . "\n\n", FILE_APPEND);
        @unlink($flagFile);

        $GLOBALS['_sqlite_recovery_performed'] = true;
    }

    private static function loadDotenv(): void
    {
        $envFile = $GLOBALS['_env_file'] ?? '.env';

        // Composer autoloader is already loaded by the project's _bootstrap.php
        if (class_exists('Dotenv\Dotenv')) {
            $dotenv = \Dotenv\Dotenv::createImmutable(self::$rootDir, $envFile);
            $dotenv->safeLoad();
            return;
        }

        // Fallback: manual .env parsing
        $envPath = self::$rootDir . '/' . $envFile;
        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            if (strpos($line, '=') === false) continue;

            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (preg_match('/^([\'"])(.*?)\\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }

    private static function initApplication(): void
    {
        if (isset($GLOBALS['Application'])) {
            return;
        }

        $GLOBALS['Application'] = [];

        // Load project-specific config
        $appConfig = self::$config['app_config'];
        if ($appConfig && file_exists($appConfig)) {
            require_once $appConfig;
        }

        $environment = $_ENV['APP_ENVIRONMENT'] ?? $GLOBALS['Application']['omgeving'] ?? 'P';
        $skipFlag = in_array(strtoupper($environment), ['O', 'L']);

        $appStarted = false;

        if (!$skipFlag) {
            $fileCacheExists = file_exists(self::$rootDir . '/.app_started');

            if (function_exists('apcu_fetch')) {
                $appStarted = apcu_fetch('application_started');

                if ($appStarted && !$fileCacheExists) {
                    if (class_exists('\\App\\Library\\Cache')) {
                        \App\Library\Cache::clear();
                    } else {
                        apcu_clear_cache();
                    }
                    $appStarted = false;
                }
            } elseif ($fileCacheExists) {
                $appStarted = true;
            }
        }

        if (!$appStarted) {
            try {
                $globalAsa = self::$config['global_asa'];
                if ($globalAsa && file_exists($globalAsa)) {
                    require_once $globalAsa;
                }

                if (function_exists('Application_OnStart')) {
                    Application_OnStart();
                }

                if (!$skipFlag) {
                    if (function_exists('apcu_store')) {
                        apcu_store('application_started', true, 0);
                    } else {
                        touch(self::$rootDir . '/.app_started');
                    }
                }
            } catch (\Exception $e) {
                error_log('Application_OnStart failed: ' . $e->getMessage());

                if (function_exists('apcu_delete')) {
                    apcu_delete('application_started');
                }
                $flagFile = self::$rootDir . '/.app_started';
                if (file_exists($flagFile)) {
                    @unlink($flagFile);
                }

                throw $e;
            }
        }
    }

    private static function registerErrorHandler(): void
    {
        if (!class_exists('\App\Library\ErrorHandler')) {
            return;
        }

        \App\Library\ErrorHandler::register([
            'error_log_file' => self::$config['log_dir'] . '/php_errors.log'
        ]);
    }

    private static function loadSpecificHelpers(): void
    {
        // RecordSet and FormControls need explicit early loading for legacy compatibility
        // With Composer autoload, classes are loaded on first use,
        // but some legacy code expects them to be available immediately
        if (class_exists('\App\Library\RecordSet')) {
            // Trigger autoload
        }
        if (class_exists('\App\Library\FormControls')) {
            // Trigger autoload
        }
    }

    private static function defineAdoConstants(): void
    {
        if (defined('adOpenForwardOnly')) {
            return;
        }

        define('adOpenForwardOnly', 0);
        define('adOpenKeyset', 1);
        define('adOpenDynamic', 2);
        define('adOpenStatic', 3);
        define('adLockReadOnly', 1);
        define('adLockPessimistic', 2);
        define('adLockOptimistic', 3);
        define('adLockBatchOptimistic', 4);
    }

    private static function createClassAliases(): void
    {
        $aliases = [
            'LibUpload', 'Arr', 'Str', 'SQL', 'Error', 'ErrorHandler',
            'Date', 'Hilight', 'HttpClient', 'Cookie'
        ];

        foreach ($aliases as $alias) {
            $fqcn = "\\App\\Library\\$alias";
            if (class_exists($fqcn) && !class_exists($alias, false)) {
                class_alias($fqcn, $alias);
            }
        }
    }

    private static function loadLegacyClasses(): void
    {
        // Legacy converted classes (class_tabs.inc, class_table.inc, etc.)
        // These live in library/web/ in the platform, but are copied to library/ in the project
        $classesDir = self::$rootDir . '/library/classes';
        if (!is_dir($classesDir)) {
            return;
        }

        $classFiles = function_exists('apcu_fetch') ? apcu_fetch('bootstrap_class_files') : false;
        if ($classFiles === false) {
            $classFiles = glob($classesDir . '/class_*.inc');
            if (function_exists('apcu_store')) {
                apcu_store('bootstrap_class_files', $classFiles, 3600);
            }
        }

        if ($classFiles) {
            foreach ($classFiles as $classFile) {
                require_once $classFile;
            }
        }
    }

    private static function loadLegacyLibFiles(): void
    {
        $libraryDir = self::$rootDir . '/library';
        if (!is_dir($libraryDir)) {
            return;
        }

        $legacyFiles = function_exists('apcu_fetch') ? apcu_fetch('bootstrap_legacy_lib_files') : false;
        if ($legacyFiles === false) {
            $legacyFiles = glob($libraryDir . '/lib_*.inc');
            if (function_exists('apcu_store')) {
                apcu_store('bootstrap_legacy_lib_files', $legacyFiles, 3600);
            }
        }

        if ($legacyFiles) {
            foreach ($legacyFiles as $file) {
                require_once $file;
            }
        }
    }

    private static function initGlobalVariables(): void
    {
        // Common loop counters from VBScript conversion
        $GLOBALS['t'] = null;
        $GLOBALS['Err'] = 0;

        // SQL escaping control
        $GLOBALS['libSQL_KeepOffAmp'] = false;
        $GLOBALS['libSQL_KeepOffQuotes'] = false;
    }

    private static function loadFieldTypes(): void
    {
        $file = self::$rootDir . '/library/cma_fldtypes.inc';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    private static function loadCmaRepository(): void
    {
        $file = self::$rootDir . '/cma/classes/CmaRepository.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    private static function loadCmaConnect(): void
    {
        $file = self::$rootDir . '/library/cma_connect.inc';
        if (file_exists($file)) {
            require_once $file;
        }
    }

    private static function initDatabaseConnections(): void
    {
        if (!class_exists('\App\Library\Database')) {
            return;
        }

        \App\Library\Database::initConnections([
            'data' => \App\Library\Application::get('conn_data', ''),
            'rep' => \App\Library\Application::get('conn_rep', ''),
            'users' => \App\Library\Application::get('conn_users', '')
        ]);
    }

    private static function initSessionData(): void
    {
        if (!isset($_SESSION['_session_initialized'])) {
            $_SESSION['_session_initialized'] = true;
            $_SESSION['_session_start_time'] = time();
        }
    }

    private static function checkMigrations(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';

        if (isset($_SESSION['_migration_check_done']) ||
            strpos($requestUri, '/cma/') === false ||
            strpos($requestUri, '/cma/login.php') !== false ||
            strpos($requestUri, 'tools_migrations.php') !== false ||
            strpos($requestUri, '_api.php') !== false ||
            isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return;
        }

        $_SESSION['_migration_check_done'] = true;

        $migrationsFile = self::$rootDir . '/cma/migrations/migrations.json';
        if (!file_exists($migrationsFile)) {
            return;
        }

        $migrationsData = json_decode(file_get_contents($migrationsFile), true);
        $targetVersion = $migrationsData['targetVersion'] ?? '0.0.0';

        try {
            $repConn = \App\Library\Application::get('conn_rep', '');
            if ($repConn && class_exists('\App\Library\Database')) {
                $pdo = new \PDO($repConn);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->query("SELECT version FROM _cma_version ORDER BY applied_at DESC");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                $currentVersion = $row ? $row['version'] : '0.0.0';

                if (version_compare($currentVersion, $targetVersion, '<')) {
                    $_SESSION['_migration_needed'] = true;
                    $_SESSION['_migration_current'] = $currentVersion;
                    $_SESSION['_migration_target'] = $targetVersion;
                }
            }
        } catch (\Exception $e) {
            $_SESSION['_migration_needed'] = true;
            $_SESSION['_migration_current'] = '0.0.0';
            $_SESSION['_migration_target'] = $targetVersion;
        }

        // Show migration warning on CMA pages
        if (!empty($_SESSION['_migration_needed']) &&
            strpos($requestUri, '/cma/') !== false &&
            strpos($requestUri, '/cma/login.php') === false &&
            strpos($requestUri, 'tools_migrations.php') === false) {

            $GLOBALS['_pending_migrations'] = [
                [
                    'current' => $_SESSION['_migration_current'] ?? '0.0.0',
                    'target' => $_SESSION['_migration_target'] ?? '?'
                ]
            ];
        }
    }

    private static function bootstrapError(string $message, string $code): void
    {
        if (class_exists('\App\Library\ErrorHandler')) {
            \App\Library\ErrorHandler::handleBootstrapError($message, $code);
        } else {
            trigger_error($message, E_USER_WARNING);
        }
    }

    private static function recordTiming(string $key): void
    {
        $GLOBALS['_bootstrap_timing'][$key] = round((microtime(true) - self::$startTime) * 1000, 1);
    }
}
