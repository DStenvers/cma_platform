<?php
/**
 * System Settings Service
 *
 * The site-wide settings an administrator changes from the CMA
 * (Beheerstools → Systeeminstellingen). Every setting is one variable in the
 * site's .env file; this class is the registry (which variables, which type,
 * which default), the reader for the CMA side, and the writer.
 *
 * Code that runs outside the CMA (ErrorHandler, NotFoundDigest, Database)
 * reads the same variables straight through EnvFile::value()/flag() with the
 * same defaults — keep the two in step when adding a setting.
 */

namespace Cma\Services;

use App\Library\EnvFile;

class SystemSettings
{
    /**
     * Registry of settings the admin UI exposes.
     *
     *   env     the .env variable
     *   type    bool  — written as true/false
     *           flag  — written as 1/0 (readers compare against the string '1')
     *           email — one or more addresses, comma-separated
     *           int   — bounded by min/max
     *   default the value when the variable is absent
     */
    public const DEFINITIONS = [
        // Meldingen
        'error_mail_enabled'       => ['env' => 'ERROR_MAIL_ENABLED',       'type' => 'bool',  'default' => false],
        'error_mail_to'            => ['env' => 'ERROR_MAIL_TO',            'type' => 'email', 'default' => ''],
        'notfound_mail_enabled'    => ['env' => 'NOTFOUND_MAIL_ENABLED',    'type' => 'bool',  'default' => false],
        'notfound_mail_to'         => ['env' => 'NOTFOUND_MAIL_TO',         'type' => 'email', 'default' => ''],
        'deploy_alert_email'       => ['env' => 'DEPLOY_ALERT_EMAIL',       'type' => 'email', 'default' => ''],
        // Logging
        'perf_log_enabled'         => ['env' => 'PERF_LOG_ENABLED',         'type' => 'bool',  'default' => true],
        'cache_log_enabled'        => ['env' => 'CACHE_LOG_ENABLED',        'type' => 'bool',  'default' => true],
        'debug_log_enabled'        => ['env' => 'DEBUG_LOG_ENABLED',        'type' => 'bool',  'default' => true],
        'email_log_enabled'        => ['env' => 'EMAIL_LOG_ENABLED',        'type' => 'bool',  'default' => true],
        'sql_log_enabled'          => ['env' => 'SQL_LOG_ENABLED',          'type' => 'bool',  'default' => false],
        'error_log_retention_days' => ['env' => 'ERROR_LOG_RETENTION_DAYS', 'type' => 'int',   'default' => 7, 'min' => 1, 'max' => 365],
        // Foutweergave
        'force_debug'              => ['env' => 'FORCE_DEBUG',              'type' => 'flag',  'default' => false],
        'cma_debug'                => ['env' => 'CMA_DEBUG',                'type' => 'flag',  'default' => false],
    ];

    private static ?string $envFile = null;
    private static ?string $envFileName = null;

    // Map environment codes to .env file names
    private const ENV_FILE_MAP = [
        'L' => '.env.local',
        'O' => '.env.development',
        'T' => '.env.test',
        'A' => '.env.acceptance',
        'P' => '.env.production'
    ];

    /**
     * Get the .env file path — MUST be the same file the bootstrap loaded,
     * otherwise settings are written to a file the app never reads (the
     * "toggle stays on" bug). Mirrors Bootstrap::detectAndLoadEnv(): the
     * single-file model (.env) is preferred; the per-environment files are
     * only a fallback for boxes that haven't been migrated.
     */
    private static function getEnvFile(): string
    {
        if (self::$envFile === null) {
            // Go up from /cma/classes/Services to /site
            $siteRoot = dirname(__DIR__, 3);

            // 1) The file Bootstrap actually loaded (set in detectAndLoadEnv).
            $loaded = $GLOBALS['_env_file'] ?? null;
            if (is_string($loaded) && $loaded !== '' && file_exists($siteRoot . '/' . $loaded)) {
                self::$envFileName = $loaded;
            } elseif (file_exists($siteRoot . '/.env')) {
                // 2) Single-file model.
                self::$envFileName = '.env';
            } else {
                // 3) Legacy per-environment fallback: APP_ENVIRONMENT mapping,
                //    then existence scan.
                $appEnv = $_ENV['APP_ENVIRONMENT'] ?? \App\Library\Request::server('APP_ENVIRONMENT', null);
                if ($appEnv && isset(self::ENV_FILE_MAP[$appEnv]) && file_exists($siteRoot . '/' . self::ENV_FILE_MAP[$appEnv])) {
                    self::$envFileName = self::ENV_FILE_MAP[$appEnv];
                } else {
                    foreach (self::ENV_FILE_MAP as $code => $fileName) {
                        if (file_exists($siteRoot . '/' . $fileName)) {
                            self::$envFileName = $fileName;
                            break;
                        }
                    }
                    if (self::$envFileName === null) {
                        self::$envFileName = '.env';
                    }
                }
            }

            self::$envFile = $siteRoot . '/' . self::$envFileName;
        }
        return self::$envFile;
    }

    /**
     * Get the .env file name (for display purposes)
     */
    public static function getEnvFileName(): string
    {
        // Ensure envFile is initialized
        self::getEnvFile();
        return self::$envFileName ?? '.env';
    }

    /**
     * Current value of one setting, typed per its definition.
     *
     * @return bool|int|string
     */
    public static function get(string $key)
    {
        $def = self::DEFINITIONS[$key] ?? null;
        if ($def === null) {
            throw new \InvalidArgumentException("Unknown system setting: $key");
        }
        $raw = EnvFile::value($def['env']);
        if ($raw === null || trim($raw) === '') {
            return $def['default'];
        }
        switch ($def['type']) {
            case 'bool':
                return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
            case 'flag':
                return trim($raw) === '1';
            case 'int':
                return max($def['min'], min($def['max'], (int) $raw));
            default:
                return trim($raw);
        }
    }

    /** Check if performance logging is enabled */
    public static function isPerfLogEnabled(): bool
    {
        return (bool) self::get('perf_log_enabled');
    }

    /** Check if cache logging is enabled */
    public static function isCacheLogEnabled(): bool
    {
        return (bool) self::get('cache_log_enabled');
    }

    /** Check if debug logging is enabled */
    public static function isDebugLogEnabled(): bool
    {
        return (bool) self::get('debug_log_enabled');
    }

    /**
     * All settings, keyed by setting name.
     *
     * @return array<string,bool|int|string>
     */
    public static function getAll(): array
    {
        $out = [];
        foreach (array_keys(self::DEFINITIONS) as $key) {
            $out[$key] = self::get($key);
        }
        return $out;
    }

    /**
     * Validate raw form input against the registry. Pure: no I/O.
     *
     * Bool/flag input is 'J'/'N' (or a PHP bool); email input is a string of
     * comma-separated addresses; int input is a numeric string. Unknown keys
     * are ignored. Returns the .env strings to write and the validation
     * errors (Dutch, keyed by setting) — an entry with an error is not in
     * $values.
     *
     * @param  array<string,mixed> $input
     * @return array{values: array<string,string>, errors: array<string,string>}
     */
    public static function normalize(array $input): array
    {
        $values = [];
        $errors = [];
        foreach ($input as $key => $raw) {
            $def = self::DEFINITIONS[$key] ?? null;
            if ($def === null) {
                continue;
            }
            switch ($def['type']) {
                case 'bool':
                    $values[$key] = self::truthy($raw) ? 'true' : 'false';
                    break;
                case 'flag':
                    $values[$key] = self::truthy($raw) ? '1' : '0';
                    break;
                case 'int':
                    $s = trim((string) $raw);
                    if ($s === '' || !preg_match('/^\d+$/', $s)) {
                        $errors[$key] = 'Vul een geheel getal in.';
                    } elseif ((int) $s < $def['min'] || (int) $s > $def['max']) {
                        $errors[$key] = 'Vul een getal tussen ' . $def['min'] . ' en ' . $def['max'] . ' in.';
                    } else {
                        $values[$key] = (string) (int) $s;
                    }
                    break;
                case 'email':
                    $addresses = array_values(array_filter(array_map('trim', explode(',', (string) $raw)), 'strlen'));
                    $bad = array_filter($addresses, static fn ($a) => filter_var($a, FILTER_VALIDATE_EMAIL) === false);
                    if ($bad !== []) {
                        $errors[$key] = 'Ongeldig e-mailadres: ' . implode(', ', $bad);
                    } else {
                        $values[$key] = implode(',', $addresses);
                    }
                    break;
            }
        }
        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * Validate and persist form input. Returns the validation errors; when
     * there are any, nothing is written. A write failure is reported under
     * the pseudo-key '_file'.
     *
     * @param  array<string,mixed> $input
     * @return array<string,string>
     */
    public static function save(array $input): array
    {
        $n = self::normalize($input);
        if ($n['errors'] !== []) {
            return $n['errors'];
        }
        foreach ($n['values'] as $key => $value) {
            if (!self::updateEnvSetting(self::DEFINITIONS[$key]['env'], $value)) {
                return ['_file' => 'Kon ' . self::getEnvFileName() . ' niet schrijven.'];
            }
        }
        return [];
    }

    private static function truthy($raw): bool
    {
        if (is_bool($raw)) {
            return $raw;
        }
        $s = strtoupper(trim((string) $raw));
        return $s === 'J' || $s === '1' || $s === 'TRUE' || $s === 'ON';
    }

    /**
     * Update a setting in the environment-specific .env file
     *
     * @param string $key The env variable name (e.g., 'PERF_LOG_ENABLED')
     * @param string $value The new value
     * @return bool Success
     */
    public static function updateEnvSetting(string $key, string $value): bool
    {
        $envFile = self::getEnvFile();

        // A site may not have an .env yet (fresh install, or env supplied by the
        // web server). An admin toggling a system setting must not silently fail
        // because of that — create the file, writing just this key. getEnvFile()
        // already resolves the correct target name (the single-file '.env' by
        // default), so we only need to seed empty content when it's absent.
        if (file_exists($envFile)) {
            $content = file_get_contents($envFile);
            if ($content === false) {
                return false;
            }
        } else {
            $content = '';
        }

        $newContent = self::applyEnvContent($content, $key, $value);

        // Write back to file
        if (file_put_contents($envFile, $newContent) === false) {
            return false;
        }

        // Update the runtime environment
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;

        return true;
    }

    /**
     * Pure .env transform: return $content with $key set to $value — replacing
     * the existing assignment in place, or inserting a new one (after any other
     * logging keys, else at the end). No file I/O, so it is unit-testable and
     * carries the create-from-empty behaviour that fixes the "no .env yet →
     * saving system settings fails" bug.
     *
     * @param string $content Current file contents ('' for a file to be created)
     */
    public static function applyEnvContent(string $content, string $key, string $value): string
    {
        // explode('') yields [''] — a phantom blank line that would head a
        // freshly-created .env; start from an empty set instead.
        $lines = ($content === '') ? [] : explode("\n", $content);
        $found = false;
        $newLines = [];

        foreach ($lines as $line) {
            // Check if this line sets our key
            if (preg_match('/^' . preg_quote($key, '/') . '\s*=/', $line)) {
                // Replace the value
                $newLines[] = $key . '=' . $value;
                $found = true;
            } else {
                $newLines[] = $line;
            }
        }

        // If key wasn't found, add it before the last empty section
        if (!$found) {
            // Find a good place to insert (after other logging settings or at end)
            $inserted = false;
            for ($i = count($newLines) - 1; $i >= 0; $i--) {
                if (preg_match('/^(PERF_LOG|CACHE_LOG|DEBUG_LOG|PROFILER)/', $newLines[$i])) {
                    array_splice($newLines, $i + 1, 0, [$key . '=' . $value]);
                    $inserted = true;
                    break;
                }
            }
            if (!$inserted) {
                // Add at the end (before final empty lines)
                $newLines[] = $key . '=' . $value;
            }
        }

        return implode("\n", $newLines);
    }
}
