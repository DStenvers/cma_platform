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
    /**
     * Platform version — the SINGLE runtime source of truth, hardcoded here
     * and bumped together with composer.json's "version" on every release.
     *
     * Why hardcoded instead of reading Composer metadata: sites that track the
     * branch (`"stenversonline/platform": "dev-main"`) make Composer report
     * "dev-main" in installed.json / InstalledVersions, and the per-site path
     * to the vendored composer.json wasn't always resolvable — so the profile
     * menu showed "vdev-main" instead of a real number. A class constant is
     * read straight from the running code: it can't degrade to "dev-main", it
     * needs no file I/O or path resolution, and it always reflects the version
     * of the code that is actually installed on the site.
     */
    public const VERSION = '1.26.28';

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

        // Bootstrap phase: errors ON so any failure before .env is read is
        // visible (never a silent white-screen during boot).
        self::configureErrorDisplay(true);

        self::sqliteEmergencyRecovery();

        self::loadDotenv();
        self::recordTiming('autoload');

        // .env is loaded now — re-apply with the REAL APP_ENVIRONMENT: stays
        // verbose for non-prod / FORCE_DEBUG, switches off for production runtime.
        self::configureErrorDisplay(false);

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
     * Get the platform package version.
     *
     * Returns the hardcoded self::VERSION constant — see that constant for why
     * we no longer read Composer metadata (it degraded to "dev-main" on
     * branch-tracking sites). Kept as a method so existing callers don't change.
     */
    public static function getPlatformVersion(): string
    {
        // Hardcoded constant — see self::VERSION for why we no longer read
        // Composer's installed.json / composer.json (the old method degraded to
        // "dev-main" on branch-tracking sites).
        return self::VERSION;
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
        // Legacy per-environment files — kept only as a fallback so boxes
        // that haven't been migrated to a single .env keep working.
        $envFileMap = [
            'L' => '.env.local',
            'O' => '.env.development',
            'T' => '.env.test',
            'A' => '.env.acceptance',
            'P' => '.env.production',
        ];

        $envFile = self::$config['env_file'];
        if (!$envFile) {
            // SINGLE-FILE MODEL (preferred): one .env per machine holds
            // everything, including APP_ENVIRONMENT. APP_ENVIRONMENT is just a
            // variable INSIDE the file now — it no longer selects which file
            // to load. This removes the "which .env.<x> holds my secret?"
            // confusion (the app + deploy.php now read the same .env).
            if (file_exists(self::$rootDir . '/.env')) {
                $envFile = '.env';
            } else {
                // No single .env — fall back to the legacy environment files.
                $appEnv = $_ENV['APP_ENVIRONMENT'] ?? $_SERVER['APP_ENVIRONMENT'] ?? null;
                if ($appEnv && isset($envFileMap[$appEnv])) {
                    $envFile = $envFileMap[$appEnv];
                    if (!file_exists(self::$rootDir . '/' . $envFile)) {
                        self::bootstrapError(
                            "Neither .env nor $envFile (APP_ENVIRONMENT=$appEnv) exists on the site root. Create a .env (copy from .env.example).",
                            'BOOTSTRAP_ENV_FILE_MISSING'
                        );
                    }
                } else {
                    $envFile = '.env';
                    foreach ($envFileMap as $candidate) {
                        if (file_exists(self::$rootDir . '/' . $candidate)) {
                            $envFile = $candidate;
                            break;
                        }
                    }
                }
            }
        }

        // ASSUME PROD until .env is actually read. We deliberately do NOT peek
        // into the file for APP_ENVIRONMENT — error display stays OFF (fail-safe)
        // through the dotenv load, and configureErrorDisplay() is called again
        // AFTER loadDotenv() to switch on verbose errors once we positively know
        // the environment is non-prod (it now lives inside the loaded .env).
        $GLOBALS['_env_file'] = $envFile;
        $GLOBALS['_app_env'] = strtoupper((string)($_ENV['APP_ENVIRONMENT'] ?? $_SERVER['APP_ENVIRONMENT'] ?? 'P'));
    }

    /**
     * @param bool $bootstrapPhase True on the pre-dotenv call, when .env hasn't
     *   been read yet so the real APP_ENVIRONMENT is unknown. We turn errors ON
     *   then so a failure during early boot (constants / env detect / dotenv
     *   load) is never a silent white-screen — that's the worst case to debug.
     */
    private static function configureErrorDisplay(bool $bootstrapPhase = false): void
    {
        $appEnv = strtoupper((string)($_ENV['APP_ENVIRONMENT'] ?? $_SERVER['APP_ENVIRONMENT'] ?? 'P'));
        $GLOBALS['_app_env'] = $appEnv;

        $forceDebug = (string)($_ENV['FORCE_DEBUG'] ?? getenv('FORCE_DEBUG') ?: '') === '1';

        // Errors ON (verbose) whenever we need to SEE them: during the bootstrap
        // phase (env not loaded yet), in any non-prod environment, or when
        // FORCE_DEBUG=1 forces it on production too.
        if ($bootstrapPhase || $appEnv !== 'P' || $forceDebug) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            ini_set('html_errors', '1');
            return;
        }

        // Production runtime (env now known, not forced): don't leak to the page.
        // Errors still go to the log; flip on per-request with FORCE_DEBUG=1.
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
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
        // .env is a flat KEY=VALUE file — parsed by our own EnvFile reader
        // (no vlucas/phpdotenv dependency). Immutable: existing OS/env vars win.
        $envFile = $GLOBALS['_env_file'] ?? '.env';
        EnvFile::loadInto(self::$rootDir . '/' . $envFile);
    }

    private static function initApplication(): void
    {
        if (isset($GLOBALS['Application'])) {
            return;
        }

        $GLOBALS['Application'] = [];

        // Backward-compat for legacy per-environment .env files. The single-.env
        // model (v1.23+) reads APP_ENVIRONMENT only from INSIDE the loaded file,
        // but legacy sites select their environment by FILENAME (.env.production,
        // .env.acceptance, …) and may not carry an explicit APP_ENVIRONMENT line.
        // Without this, app.php's `$_ENV['APP_ENVIRONMENT'] ?? 'O'` defaults a
        // production box to 'O' (development) → global.asa.php then wires up the
        // LOCAL SQLite databases (cmausers.sqlite) instead of the real ones →
        // "no such table: tblUsers" and a dead login. Derive APP_ENVIRONMENT from
        // the loaded filename when it wasn't set by the file itself or the OS env.
        if (!isset($_ENV['APP_ENVIRONMENT']) && !isset($_SERVER['APP_ENVIRONMENT'])) {
            $envByFile = [
                '.env.local'       => 'L',
                '.env.development' => 'O',
                '.env.test'        => 'T',
                '.env.acceptance'  => 'A',
                '.env.production'  => 'P',
            ];
            $loadedEnvFile = $GLOBALS['_env_file'] ?? '';
            if (isset($envByFile[$loadedEnvFile])) {
                $_ENV['APP_ENVIRONMENT']     = $envByFile[$loadedEnvFile];
                $_SERVER['APP_ENVIRONMENT']  = $envByFile[$loadedEnvFile];
                $GLOBALS['_app_env']         = $envByFile[$loadedEnvFile];
            }
        }

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
        // ErrorHandler is the whole reason errors get caught & logged. If the
        // class is missing (typically stale vendor/ after a partial composer
        // update), failing silently leaves the entire request running
        // unprotected — invisible 500s, no logs, no email alerts. Die loud.
        if (!class_exists('\App\Library\ErrorHandler')) {
            self::bootstrapError(
                'App\\Library\\ErrorHandler class not found — vendor/ is out of sync. Run composer update stenversonline/platform.',
                'BOOTSTRAP_NO_ERROR_HANDLER'
            );
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

        // Single source of truth: build the logical data/rep/users connections
        // from databases.json (per-site data/ preferred, platform default as
        // fallback). Tolerant of the legacy entry names so a site keeps working
        // before the rename migration canonicalises them.
        $logical = [
            'users' => 'users', 'cmausers' => 'users',
            'data' => 'data', 'database' => 'data', 'main' => 'data',
            'rep' => 'rep', 'repository' => 'rep',
        ];
        $dsns = ['data' => '', 'rep' => '', 'users' => ''];
        foreach (self::loadDatabasesConfig() as $entry) {
            $name = strtolower(trim((string)($entry['name'] ?? '')));
            $key = $logical[$name] ?? null;
            if ($key === null || $dsns[$key] !== '') {
                continue; // unknown name, or already filled (first match wins)
            }
            $dsns[$key] = \App\Library\Database::dsnFromConfigEntry($entry, self::$rootDir);
        }

        // Back-compat: honour the legacy conn_* Application globals (still set in
        // app.php by many consumers, e.g. rec/karaat) for any logical connection
        // databases.json didn't define — BEFORE the Access file default below.
        // databases.json always wins where present; this just stops a conn_*-only
        // site (e.g. one configured for SQL Server in app.php with no
        // data/databases.json) from silently falling through to a non-existent
        // Access main.mdb.
        foreach (['data', 'rep', 'users'] as $key) {
            if ($dsns[$key] === '') {
                $legacy = (string)\App\Library\Application::get('conn_' . $key, '');
                if ($legacy !== '') {
                    $dsns[$key] = $legacy;
                }
            }
        }

        // Access is the default DB format: data/users that databases.json
        // didn't define fall back to the conventional Access file under /db. We
        // NEVER silently fall back to a local SQLite file (that just creates an
        // empty DB and a confusing "no such table" error). 'rep' is deliberately
        // omitted — it's the deprecated repository DB; leaving it empty lets
        // getConnection() fall back to 'data' instead of forcing an Access
        // repository.mdb that often can't be opened (volatile Ace DSN error).
        $accessDefaults = ['data' => 'main.mdb', 'users' => 'CMAUsers.mdb'];
        foreach ($accessDefaults as $key => $file) {
            if ($dsns[$key] === '') {
                $path = self::$rootDir . DIRECTORY_SEPARATOR . 'db' . DIRECTORY_SEPARATOR . $file;
                $dsns[$key] = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $path;
            }
        }

        \App\Library\Database::initConnections($dsns);
    }

    /**
     * Load the databases.json "databases" array — per-site data/databases.json
     * preferred, falling back to the platform default cma/config/databases.json.
     *
     * Public so backup/restore and other tooling resolve the EXACT same config
     * the live connections do, instead of hardcoding cma/config/ (which can hold
     * stale/empty entries that diverge from the per-site data/databases.json).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function loadDatabasesConfig(): array
    {
        $candidates = [
            self::$rootDir . '/data/databases.json',
            self::$rootDir . '/cma/config/databases.json',
        ];
        foreach ($candidates as $file) {
            if (!is_file($file)) {
                continue;
            }
            $data = json_decode((string) @file_get_contents($file), true);
            if (is_array($data) && !empty($data['databases']) && is_array($data['databases'])) {
                $GLOBALS['_db_config_source'] = $file;
                return $data['databases'];
            }
        }
        return [];
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

        // Applied version is tracked in migrations.json (schemaVersion) since
        // migrations moved to JSON. We no longer open the deprecated 'rep'
        // (repository.mdb) database at runtime to read a _cma_version table —
        // rep is migration-only, and opening it on every CMA page caused the
        // Access "volatile Ace DSN" crash (SQLSTATE HY000 / 63).
        $currentVersion = $migrationsData['schemaVersion'] ?? '0.0.0';

        if (version_compare($currentVersion, $targetVersion, '<')) {
            $_SESSION['_migration_needed'] = true;
            $_SESSION['_migration_current'] = $currentVersion;
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
