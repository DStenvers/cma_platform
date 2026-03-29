<?php

namespace Cma;

use App\Library\Arr;

/**
 * ConfigLoader - Central configuration loading from JSON files
 *
 * Replaces repository database queries with JSON file reads.
 * Provides caching and validation for all configuration data.
 */
class ConfigLoader
{
    /** @var array In-memory cache for loaded configs */
    private static array $cache = [];

    /** @var string Base path to config directory */
    private static ?string $configPath = null;

    /** @var bool Whether to use JSON configs (feature flag) */
    private static bool $enabled = true;

    /**
     * Get the config directory path
     */
    private static function getConfigPath(): string
    {
        if (self::$configPath === null) {
            self::$configPath = __DIR__ . '/../config/';
        }
        return self::$configPath;
    }

    /**
     * Resolve the full file path for a config name
     * Handles absolute paths (starting with /) relative to site root
     */
    /** @var array Config name aliases for relocated files */
    private static array $aliases = [
        'data-sources' => '/assets/datastores/data-sources',
    ];

    private static function resolveFilePath(string $name): string
    {
        // Resolve aliases (e.g. 'data-sources' -> '/assets/datastores/data-sources')
        $name = self::$aliases[$name] ?? $name;

        if (str_starts_with($name, '/')) {
            $siteRoot = dirname(__DIR__, 2); // Go up from /cma/classes to /site
            return $siteRoot . $name . '.json';
        }

        // Customer data configs: prefer /site/data/ (survives CMA updates)
        $siteDataPath = dirname(__DIR__, 2) . '/data/' . $name . '.json';
        if (file_exists($siteDataPath)) {
            return $siteDataPath;
        }

        // Application configs (migrations, control-types, schema): /cma/config/
        return self::getConfigPath() . $name . '.json';
    }

    /**
     * Set the config directory path (for testing)
     */
    public static function setConfigPath(string $path): void
    {
        self::$configPath = rtrim($path, '/') . '/';
        self::$cache = []; // Clear cache when path changes
    }

    /**
     * Enable or disable JSON config loading
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if JSON config loading is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Load a configuration file
     *
     * @param string $name Config name (without .json extension)
     * @return array Parsed JSON data
     * @throws RuntimeException If file not found or invalid JSON
     */
    public static function load(string $name): array
    {
        if (!isset(self::$cache[$name])) {
            $file = self::resolveFilePath($name);

            if (!file_exists($file)) {
                throw new \RuntimeException("Config file not found: {$name}.json");
            }

            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException("Invalid JSON in {$name}.json: " . json_last_error_msg());
            }

            self::$cache[$name] = $data;
        }

        return self::$cache[$name];
    }

    /**
     * Get the resolved file path for a config name (public for tools)
     */
    public static function getFilePath(string $name): string
    {
        return self::resolveFilePath($name);
    }

    /**
     * Check if a config file exists
     */
    public static function exists(string $name): bool
    {
        return file_exists(self::resolveFilePath($name));
    }

    /**
     * Save a configuration file
     *
     * @param string $name Config name
     * @param array $data Data to save
     * @return bool Success
     */
    public static function save(string $name, array $data): bool
    {
        $file = self::resolveFilePath($name);

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        // Add newline at end of file
        $json .= "\n";

        $result = file_put_contents($file, $json, LOCK_EX);

        if ($result !== false) {
            // Update cache
            self::$cache[$name] = $data;

            // Clear dependent service caches
            if ($name === 'menu') {
                \Cma\Services\MenuService::clearCache();
            }

            return true;
        }

        return false;
    }

    /**
     * Invalidate cached config
     *
     * @param string|null $name Specific config name, or null for all
     */
    public static function invalidate(?string $name = null): void
    {
        if ($name !== null) {
            unset(self::$cache[$name]);
        } else {
            self::$cache = [];
        }
    }

    // =========================================================================
    // Database Configuration
    // =========================================================================

    /**
     * Get all database configurations
     */
    public static function getDatabases(): array
    {
        try {
            return self::load('databases')['databases'] ?? [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get a specific database configuration by name or ID
     *
     * @param string|int $nameOrId Database name (e.g., 'data') or numeric ID
     */
    public static function getDatabase($nameOrId): ?array
    {
        foreach (self::getDatabases() as $db) {
            if ($db['name'] === $nameOrId || (string)$db['id'] === (string)$nameOrId) {
                return $db;
            }
        }
        return null;
    }

    /**
     * Get connection string for a database
     */
    public static function getConnectionString($nameOrId): ?string
    {
        $db = self::getDatabase($nameOrId);
        if ($db === null) {
            return null;
        }

        $connString = $db['connectionString'] ?? '';

        // Replace [path] placeholder with actual path
        $basePath = \App\Library\Application::get('base_path', '');
        $connString = str_replace('[path]', $basePath, $connString);

        return $connString;
    }

    // =========================================================================
    // Module Configuration
    // =========================================================================

    /**
     * Get all module configurations
     */
    public static function getModules(): array
    {
        try {
            return self::load('modules')['modules'] ?? [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get active modules only
     */
    public static function getActiveModules(): array
    {
        return array_filter(self::getModules(), function($m) {
            return ($m['active'] ?? true) === true;
        });
    }

    /**
     * Get a specific module by name or ID
     */
    public static function getModule($nameOrId): ?array
    {
        foreach (self::getModules() as $module) {
            if ($module['name'] === $nameOrId || (string)$module['id'] === (string)$nameOrId) {
                return $module;
            }
        }
        return null;
    }

    /**
     * Get modules for a specific database
     */
    public static function getModulesForDatabase($databaseId): array
    {
        return array_filter(self::getActiveModules(), function($m) use ($databaseId) {
            $dbName = $m['database'] ?? '';
            $db = self::getDatabase($dbName);
            return $db !== null && (string)$db['id'] === (string)$databaseId;
        });
    }

    // =========================================================================
    // Menu Configuration
    // =========================================================================

    /**
     * Get the complete menu structure
     */
    public static function getMenu(): array
    {
        try {
            return self::load('menu')['menus'] ?? [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get a flat list of all menu items
     */
    public static function getMenuItems(): array
    {
        $items = [];
        foreach (self::getMenu() as $menu) {
            foreach ($menu['items'] ?? [] as $item) {
                $item['menuName'] = $menu['name'];
                $item['menuId'] = $menu['id'];
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Get menu item by ID
     */
    public static function getMenuItem(int $itemId): ?array
    {
        foreach (self::getMenuItems() as $item) {
            if (($item['id'] ?? 0) === $itemId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Get menu item ID for a form
     */
    public static function getMenuItemIdForForm(string $formName): ?int
    {
        foreach (self::getMenuItems() as $item) {
            if (($item['form'] ?? '') === $formName) {
                return $item['id'] ?? null;
            }
        }
        return null;
    }

    // =========================================================================
    // Control Types Configuration
    // =========================================================================

    /**
     * Get all control type definitions
     */
    public static function getControlTypes(): array
    {
        try {
            return self::load('control-types')['controlTypes'] ?? [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get control type by ID
     */
    public static function getControlType(int $id): ?array
    {
        foreach (self::getControlTypes() as $type) {
            if (($type['id'] ?? 0) === $id) {
                return $type;
            }
        }
        return null;
    }

    /**
     * Get control type by name
     */
    public static function getControlTypeByName(string $name): ?array
    {
        foreach (self::getControlTypes() as $type) {
            if (($type['name'] ?? '') === $name) {
                return $type;
            }
        }
        return null;
    }

    // =========================================================================
    // Reports Configuration
    // =========================================================================

    /**
     * Get all report definitions
     */
    public static function getReports(): array
    {
        try {
            return self::load('reports')['reports'] ?? [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get visible reports only
     */
    public static function getVisibleReports(): array
    {
        return array_filter(self::getReports(), function($r) {
            return ($r['visible'] ?? true) === true;
        });
    }

    /**
     * Get reports grouped by module
     */
    public static function getReportsByModule(): array
    {
        $grouped = [];
        foreach (self::getVisibleReports() as $report) {
            $module = $report['module'] ?? 'Other';
            if (!isset($grouped[$module])) {
                $grouped[$module] = [];
            }
            $grouped[$module][] = $report;
        }
        return $grouped;
    }

    // =========================================================================
    // Data Sources Configuration (XMLStore)
    // =========================================================================

    /**
     * Get all data source definitions
     */
    public static function getDataSources(): array
    {
        try {
            return self::load('data-sources')['dataSources'] ?? [];
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get selectable data sources only
     */
    public static function getSelectableDataSources(): array
    {
        return array_filter(self::getDataSources(), function($ds) {
            return ($ds['selectable'] ?? true) === true;
        });
    }

    /**
     * Get selectable data source names sorted alphabetically
     * @return array List of data source names
     */
    public static function getSelectableDataSourceNames(): array
    {
        $dataSources = self::getSelectableDataSources();
        $names = array_map(fn($ds) => $ds['name'] ?? '', $dataSources);
        sort($names, SORT_STRING | SORT_FLAG_CASE);
        return $names;
    }

    /**
     * Get data source by name
     */
    public static function getDataSource(string $name): ?array
    {
        foreach (self::getDataSources() as $source) {
            if (($source['name'] ?? '') === $name) {
                return $source;
            }
        }
        return null;
    }

    // =========================================================================
    // Application Configuration
    // =========================================================================

    /**
     * Get application configuration
     */
    public static function getAppConfig(): array
    {
        try {
            return self::load('app');
        } catch (\RuntimeException $e) {
            return [];
        }
    }

    /**
     * Get company configuration (branding)
     */
    public static function getCompanyConfig(): array
    {
        return self::getAppConfig()['company'] ?? [];
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Get all available config files
     */
    public static function getAvailableConfigs(): array
    {
        $configs = [];
        $path = self::getConfigPath();

        if (is_dir($path)) {
            foreach (glob($path . '*.json') as $file) {
                $name = basename($file, '.json');
                $configs[] = $name;
            }
        }

        return $configs;
    }

    /**
     * Validate a config against its schema
     *
     * @param string $name Config name
     * @return array Array of validation errors (empty if valid)
     */
    public static function validate(string $name): array
    {
        $errors = [];
        $schemaFile = self::getConfigPath() . 'schema/' . $name . '.schema.json';

        if (!file_exists($schemaFile)) {
            // No schema to validate against
            return [];
        }

        try {
            $data = self::load($name);
            $schema = json_decode(file_get_contents($schemaFile), true);

            // Basic validation - check required properties
            if (isset($schema['required']) && Arr::isArray($schema['required'])) {
                foreach ($schema['required'] as $prop) {
                    if (!isset($data[$prop])) {
                        $errors[] = "Missing required property: {$prop}";
                    }
                }
            }

        } catch (\RuntimeException $e) {
            $errors[] = $e->getMessage();
        }

        return $errors;
    }

    /**
     * Export all configs as a single array (for backup/migration)
     */
    public static function exportAll(): array
    {
        $export = [];
        foreach (self::getAvailableConfigs() as $name) {
            try {
                $export[$name] = self::load($name);
            } catch (\RuntimeException $e) {
                $export[$name] = ['_error' => $e->getMessage()];
            }
        }
        return $export;
    }
}
