<?php
/**
 * System Settings Service
 *
 * Manages system-wide configuration settings that can be changed
 * by admins. Settings are persisted to the environment-specific .env file.
 */

namespace Cma\Services;

class SystemSettings
{
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
     * Get the environment-specific .env file path
     */
    private static function getEnvFile(): string
    {
        if (self::$envFile === null) {
            // Go up from /cma/classes/Services to /site
            $siteRoot = dirname(__DIR__, 3);

            // Determine which env file based on APP_ENVIRONMENT
            $appEnv = $_ENV['APP_ENVIRONMENT'] ?? \App\Library\Request::server('APP_ENVIRONMENT', null);

            if ($appEnv && isset(self::ENV_FILE_MAP[$appEnv])) {
                self::$envFileName = self::ENV_FILE_MAP[$appEnv];
            } else {
                // Auto-detect by checking which .env file exists
                foreach (self::ENV_FILE_MAP as $code => $fileName) {
                    if (file_exists($siteRoot . '/' . $fileName)) {
                        self::$envFileName = $fileName;
                        break;
                    }
                }
                // Fallback to .env if none found
                if (self::$envFileName === null) {
                    self::$envFileName = '.env';
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
     * Check if performance logging is enabled
     */
    public static function isPerfLogEnabled(): bool
    {
        $envValue = getenv('PERF_LOG_ENABLED') ?: ($_ENV['PERF_LOG_ENABLED'] ?? null);
        if ($envValue !== null) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }
        return true; // Default to true
    }

    /**
     * Check if cache logging is enabled
     */
    public static function isCacheLogEnabled(): bool
    {
        $envValue = getenv('CACHE_LOG_ENABLED') ?: ($_ENV['CACHE_LOG_ENABLED'] ?? null);
        if ($envValue !== null) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }
        return true; // Default to true
    }

    /**
     * Check if debug logging is enabled
     */
    public static function isDebugLogEnabled(): bool
    {
        $envValue = getenv('DEBUG_LOG_ENABLED') ?: ($_ENV['DEBUG_LOG_ENABLED'] ?? null);
        if ($envValue !== null) {
            return filter_var($envValue, FILTER_VALIDATE_BOOLEAN);
        }
        return true; // Default to true
    }

    /**
     * Get all system settings
     */
    public static function getAll(): array
    {
        return [
            'perf_log_enabled' => self::isPerfLogEnabled(),
            'cache_log_enabled' => self::isCacheLogEnabled(),
            'debug_log_enabled' => self::isDebugLogEnabled(),
        ];
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

        if (!file_exists($envFile)) {
            return false;
        }

        $content = file_get_contents($envFile);
        if ($content === false) {
            return false;
        }

        $lines = explode("\n", $content);
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

        $newContent = implode("\n", $newLines);

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
     * Save multiple settings
     *
     * @param array $settings Key-value pairs of settings to save
     * @return bool Success
     */
    public static function saveSettings(array $settings): bool
    {
        $success = true;

        // Map our setting names to env variable names
        $mapping = [
            'perf_log_enabled' => 'PERF_LOG_ENABLED',
            'cache_log_enabled' => 'CACHE_LOG_ENABLED',
            'debug_log_enabled' => 'DEBUG_LOG_ENABLED',
        ];

        foreach ($settings as $key => $value) {
            if (isset($mapping[$key])) {
                // Convert boolean to string
                $strValue = $value ? 'true' : 'false';
                if (!self::updateEnvSetting($mapping[$key], $strValue)) {
                    $success = false;
                }
            }
        }

        return $success;
    }
}
