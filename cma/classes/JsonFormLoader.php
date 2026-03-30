<?php

namespace Cma;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\Server;

/**
 * JSON Form Definition Loader
 *
 * Loads form definitions from JSON files instead of tblForms database.
 * This provides:
 * - Version-controllable form definitions
 * - Easy editing without database access
 * - Independence from cache clearing
 * - Consistent structure for all forms
 * - Persistent caching (1 week default) for performance
 *
 * JSON files are stored in:
 * - Internal (CMA system) forms: /cma/assets/forms/definitions/
 * - App/External forms: /site/assets/forms/ (outside /cma for safe updates)
 *
 * File naming convention:
 * - Main forms: {formName}.json (e.g., users.json, groups.json)
 * - Subforms: {mainFormName}_{subformName}.json (e.g., users_notifications.json)
 */
class JsonFormLoader
{
    /**
     * Directory where internal (CMA system) JSON form definitions are stored
     */
    private const INTERNAL_DEFINITIONS_DIR = __DIR__ . '/../assets/forms/definitions';

    /**
     * Directory where app-specific JSON form definitions are stored
     * Located outside /cma so updating /cma doesn't overwrite app forms
     */
    private const APP_DEFINITIONS_DIR = __DIR__ . '/../../assets/forms';

    /**
     * Directory where external (user-defined) JSON form definitions are stored
     * Same as APP_DEFINITIONS_DIR - outside /cma for safe updates
     */
    private const EXTERNAL_DEFINITIONS_DIR = __DIR__ . '/../../assets/forms';

    /**
     * List of internal form names that stay in the CMA directory
     * These are system forms that should not be overwritten by user forms
     */
    private const INTERNAL_FORMS = [
        'users',
        'groups',
        '_menus',
        '_menu_items',
        'cmamonitoring',
        'contentblocks',
        'marketingurl',
        'formdefinitions',
    ];

    /**
     * @deprecated Use INTERNAL_DEFINITIONS_DIR instead
     */
    private const DEFINITIONS_DIR = self::INTERNAL_DEFINITIONS_DIR;

    /**
     * Directory for cached parsed form definitions
     * Uses site root cache: /site/cache/cma/forms/
     */
    private const CACHE_DIR = __DIR__ . '/../../cache/cma/forms';

    /**
     * Default cache TTL in seconds (1 week)
     */
    private const CACHE_TTL = 604800; // 7 * 24 * 60 * 60

    /**
     * Cache of loaded form definitions (memory cache for current request)
     */
    private static array $cache = [];

    /**
     * Whether file caching is enabled
     */
    private static bool $fileCacheEnabled = true;

    /**
     * Cache of filemtime results to avoid redundant stat calls
     */
    private static array $mtimeCache = [];

    /**
     * Cache of directory file listings (memory cache for current request)
     * Avoids repeated glob() calls for the same directories
     */
    private static array $dirListCache = [];

    /**
     * Get list of JSON files in a directory (with caching)
     */
    private static function getJsonFilesInDir(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        // Return from cache if already loaded
        if (isset(self::$dirListCache[$dir])) {
            return self::$dirListCache[$dir];
        }

        // Load and cache
        $files = glob($dir . '/*.json');
        self::$dirListCache[$dir] = $files ?: [];
        return self::$dirListCache[$dir];
    }

    /**
     * Get the cache file path for a form
     *
     * @param string $formName Form name
     * @param string $type Cache type ('raw' or 'legacy')
     * @return string Cache file path
     */
    private static function getCacheFilePath(string $formName, string $type = 'raw'): string
    {
        // Normalize to lowercase for consistent cache keys
        $formName = strtolower($formName);
        return self::CACHE_DIR . '/' . $formName . '.' . $type . '.cache';
    }

    /**
     * Ensure cache directory exists
     */
    private static function ensureCacheDirectoryExists(): bool
    {
        if (!is_dir(self::CACHE_DIR)) {
            return @mkdir(self::CACHE_DIR, 0755, true);
        }
        return true;
    }

    /**
     * Check if file cache is valid (exists, not expired, source not modified)
     *
     * @param string $formName Form name
     * @param string $type Cache type
     * @return bool
     */
    /**
     * Get cached filemtime result to avoid redundant stat calls
     */
    private static function getFileMtime(string $path): int|false
    {
        if (!isset(self::$mtimeCache[$path])) {
            self::$mtimeCache[$path] = @filemtime($path);
        }
        return self::$mtimeCache[$path];
    }

    private static function isFileCacheValid(string $formName, string $type = 'raw'): bool
    {
        if (!self::$fileCacheEnabled) {
            return false;
        }

        $cacheFile = self::getCacheFilePath($formName, $type);
        if (!file_exists($cacheFile)) {
            return false;
        }

        $cacheTime = self::getFileMtime($cacheFile);
        if ($cacheTime === false) {
            return false;
        }
        $now = time();

        // Check TTL expiration
        if (($now - $cacheTime) > self::CACHE_TTL) {
            return false;
        }

        // Check if source file is newer than cache
        $sourceFile = self::getFilePath($formName);
        if (file_exists($sourceFile)) {
            $sourceTime = self::getFileMtime($sourceFile);
            if ($sourceTime !== false && $sourceTime > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Read from file cache
     *
     * @param string $formName Form name
     * @param string $type Cache type
     * @return array|null Cached data or null if not found/invalid
     */
    private static function readFileCache(string $formName, string $type = 'raw'): ?array
    {
        if (!self::isFileCacheValid($formName, $type)) {
            return null;
        }

        $cacheFile = self::getCacheFilePath($formName, $type);
        $content = @file_get_contents($cacheFile);
        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);
        return Arr::isArray($data) ? $data : null;
    }

    /**
     * Write to file cache
     *
     * @param string $formName Form name
     * @param array $data Data to cache
     * @param string $type Cache type
     * @return bool Success
     */
    private static function writeFileCache(string $formName, array $data, string $type = 'raw'): bool
    {
        if (!self::$fileCacheEnabled) {
            return false;
        }

        if (!self::ensureCacheDirectoryExists()) {
            return false;
        }

        $cacheFile = self::getCacheFilePath($formName, $type);
        $content = serialize($data);

        return @file_put_contents($cacheFile, $content) !== false;
    }

    /**
     * Clear cache for a specific form or all forms
     *
     * @param string|null $formName Form name, or null to clear all
     * @return int Number of cache files deleted
     */
    public static function clearCache(?string $formName = null): int
    {
        $count = 0;

        if ($formName !== null) {
            // Normalize form name to lowercase
            $formName = self::normalizeFormName($formName);

            // Clear specific form cache
            foreach (['raw', 'legacy'] as $type) {
                $cacheFile = self::getCacheFilePath($formName, $type);
                if (file_exists($cacheFile) && @unlink($cacheFile)) {
                    $count++;
                }
            }
            // Clear from memory cache
            unset(self::$cache[$formName]);
            unset(self::$cache['raw_' . $formName]);
            // Clear from APCu cache
            if (function_exists('apcu_delete')) {
                @apcu_delete('jfl_' . $formName . '_raw');
                @apcu_delete('jfl_' . $formName . '_legacy');
            }
        } else {
            // Clear all cache files
            if (is_dir(self::CACHE_DIR)) {
                $files = glob(self::CACHE_DIR . '/*.cache');
                foreach ($files as $file) {
                    if (@unlink($file)) {
                        $count++;
                    }
                }
            }
            // Clear memory cache
            self::$cache = [];
            // Clear all APCu entries for form loader
            if (function_exists('apcu_clear_cache')) {
                // APCu doesn't support prefix deletion, but clearCache(null)
                // is only called from tools_clearcache.php which already calls apcu_clear_cache()
            }
        }

        return $count;
    }

    /**
     * Enable or disable file caching
     *
     * @param bool $enabled
     */
    public static function setFileCacheEnabled(bool $enabled): void
    {
        self::$fileCacheEnabled = $enabled;
    }

    /**
     * Control type mappings (name to ID)
     */
    public const CONTROL_TYPES = [
        'combobox' => 2,
        'combo' => 2,     // Alias for combobox
        'dropdown' => 2,  // Alias for combobox with static options
        'textbox' => 3,
        'checkbox' => 5,
        'memo' => 6,
        'checklist' => 8,
        'image' => 9,
        'url' => 10,
        'file' => 11,
        'label' => 12,
        'sortlist' => 13,
        'directory' => 14,
        'groupseparator' => 15,
        'heading' => 15,      // Alias for groupseparator
        'separator' => 15,    // Alias for groupseparator
        'userlist' => 16,
        'email' => 17,
        'xmlstore' => 18,
        'htmlstrip' => 19,
        'thumbnail' => 20,
        'time' => 21,
        'password' => 22,
        'ignorefield' => 23,  // Field hidden from UI but stored in database
        'date' => 24,         // Date field (rendered as lib-datepicker)
        // New control types for system forms
        'radio' => 100,           // Radio button group (alias)
        'radiogroup' => 100,      // Radio button group (for access rights)
        'checklistinline' => 101, // Inline checklist with labels
        'checklisttree' => 102,   // Tree-structured checklist (forms/subforms)
        'tip' => 103,             // Info tip/tooltip block
        'hidden' => 104,          // Hidden field
        'readonly' => 105,        // Read-only display field
        'custom' => 199,          // Custom rendered control (uses 'renderer' property)
    ];

    /**
     * Normalize form name to lowercase for case-insensitive lookups
     *
     * @param string $formName Form name
     * @return string Normalized (lowercase) form name
     */
    private static function normalizeFormName(string $formName): string
    {
        return strtolower(str_replace(' ', '_', $formName));
    }

    /**
     * Check if a JSON form definition exists
     *
     * @param string $formName Form name (without .json extension)
     * @return bool
     */
    public static function exists(string $formName): bool
    {
        $formName = self::normalizeFormName($formName);
        $path = self::getFilePath($formName);
        return file_exists($path);
    }

    /**
     * Load raw JSON form definition (without legacy conversion)
     *
     * Uses 3-tier caching:
     * 1. Memory cache (current request)
     * 2. File cache (persisted, 1 week TTL)
     * 3. JSON source file (parsed and cached)
     *
     * @param string $formName Form name (without .json extension)
     * @return array|null Raw JSON form definition, or null if not found
     */
    public static function loadRaw(string $formName): ?array
    {
        // Normalize form name to lowercase for case-insensitive lookups
        $formName = self::normalizeFormName($formName);

        // Tier 1: Memory cache (current request)
        $cacheKey = 'raw_' . $formName;
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Tier 2: APCu cache (shared memory across requests)
        $apcuKey = 'jfl_' . $formName . '_raw';
        if (function_exists('apcu_fetch')) {
            $apcuCached = apcu_fetch($apcuKey);
            if ($apcuCached !== false) {
                self::$cache[$cacheKey] = $apcuCached;
                return $apcuCached;
            }
        }

        // Tier 3: File cache (persisted)
        $cached = self::readFileCache($formName, 'raw');
        if ($cached !== null) {
            self::$cache[$cacheKey] = $cached;
            // Promote to APCu for faster access next request
            if (function_exists('apcu_store')) {
                apcu_store($apcuKey, $cached, 3600);
            }
            return $cached;
        }

        // Tier 4: Parse JSON source file
        $path = self::getFilePath($formName);
        if (!file_exists($path)) {
            self::$cache[$cacheKey] = null;
            return null;
        }

        $json = file_get_contents($path);
        $data = json_decode($json, true);

        if ($data === null) {
            \Cma\Services\Logger::warning("JsonFormLoader: Failed to parse form JSON", [
                'formName' => $formName,
                'jsonError' => json_last_error_msg()
            ]);
            self::$cache[$cacheKey] = null;
            return null;
        }

        // Write to file cache for future requests
        self::writeFileCache($formName, $data, 'raw');

        // Store in APCu cache (1-hour TTL)
        if (function_exists('apcu_store')) {
            apcu_store($apcuKey, $data, 3600);
        }

        // Store in memory cache
        self::$cache[$cacheKey] = $data;
        return $data;
    }

    /**
     * Load a form definition by name (legacy format)
     *
     * Uses 4-tier caching:
     * 1. Memory cache (current request)
     * 2. APCu cache (shared memory, 1 hour TTL)
     * 3. File cache (persisted, 1 week TTL)
     * 4. JSON source file (parsed, converted, and cached)
     *
     * @param string $formName Form name (without .json extension)
     * @return array|null Form definition array compatible with GetFormDef() format, or null if not found
     */
    public static function load(string $formName): ?array
    {
        // Normalize form name to lowercase for case-insensitive lookups
        $formName = self::normalizeFormName($formName);

        // Tier 1: Memory cache (current request)
        if (isset(self::$cache[$formName])) {
            return self::$cache[$formName];
        }

        // Tier 2: APCu cache (shared memory across requests)
        $apcuKey = 'jfl_' . $formName . '_legacy';
        if (function_exists('apcu_fetch')) {
            $apcuCached = apcu_fetch($apcuKey);
            if ($apcuCached !== false) {
                self::$cache[$formName] = $apcuCached;
                return $apcuCached;
            }
        }

        // Tier 3: File cache (persisted legacy format)
        $cached = self::readFileCache($formName, 'legacy');
        if ($cached !== null) {
            self::$cache[$formName] = $cached;
            if (function_exists('apcu_store')) {
                apcu_store($apcuKey, $cached, 3600);
            }
            return $cached;
        }

        // Tier 4: Load raw and convert to legacy format
        $data = self::loadRaw($formName);
        if ($data === null) {
            return null;
        }

        // Convert to legacy format (Q_* indexed array)
        $legacyFormat = self::convertToLegacyFormat($data);

        // Write to file cache for future requests
        self::writeFileCache($formName, $legacyFormat, 'legacy');

        // Store in APCu cache (1-hour TTL)
        if (function_exists('apcu_store')) {
            apcu_store($apcuKey, $legacyFormat, 3600);
        }

        // Store in memory cache
        self::$cache[$formName] = $legacyFormat;
        return $legacyFormat;
    }

    /**
     * Load raw form definition by name (alias for loadRaw)
     *
     * @param string $formName Form name (without .json extension)
     * @return array|null Raw JSON form definition, or null if not found
     */
    public static function loadFormDefinition(string $formName): ?array
    {
        return self::loadRaw($formName);
    }

    /**
     * Get form definition by source form ID (legacy database ID)
     *
     * Searches through all JSON form definitions to find one with matching sourceFormId.
     *
     * @param int $sourceId Original database form ID
     * @return array|null Raw JSON form definition, or null if not found
     */
    public static function getFormDefBySourceId(int $sourceId): ?array
    {
        // Check memory cache for sourceId mappings
        static $sourceIdCache = [];

        if (isset($sourceIdCache[$sourceId])) {
            return $sourceIdCache[$sourceId] !== false ? self::loadRaw($sourceIdCache[$sourceId]) : null;
        }

        // Iterate through all form files in both directories
        $directories = [self::INTERNAL_DEFINITIONS_DIR, self::APP_DEFINITIONS_DIR, self::EXTERNAL_DEFINITIONS_DIR];

        foreach ($directories as $dir) {
            $files = self::getJsonFilesInDir($dir);
            foreach ($files as $file) {
                $formName = basename($file, '.json');
                $def = self::loadRaw($formName);

                if ($def !== null && isset($def['sourceFormId']) && (int)$def['sourceFormId'] === $sourceId) {
                    $sourceIdCache[$sourceId] = $formName;
                    return $def;
                }
            }
        }

        $sourceIdCache[$sourceId] = false;
        return null;
    }

    /**
     * Get form name by source form ID (legacy database ID)
     *
     * @param int $sourceId Original database form ID
     * @return string|null JSON form name, or null if not found
     */
    public static function getFormNameBySourceId(int $sourceId): ?string
    {
        // Use getFormDefBySourceId which caches the mapping
        $def = self::getFormDefBySourceId($sourceId);
        if ($def !== null) {
            return $def['name'] ?? null;
        }
        return null;
    }

    /**
     * Get all available JSON form names
     *
     * @return array List of form names (without .json extension)
     */
    public static function listForms(): array
    {
        $forms = [];
        $directories = [self::INTERNAL_DEFINITIONS_DIR, self::APP_DEFINITIONS_DIR, self::EXTERNAL_DEFINITIONS_DIR];

        foreach ($directories as $dir) {
            $files = self::getJsonFilesInDir($dir);
            foreach ($files as $file) {
                $name = basename($file, '.json');
                // Skip subforms (they contain underscores and their parent exists)
                if (strpos($name, '_') === false && !in_array($name, $forms, true)) {
                    $forms[] = $name;
                }
            }
        }

        return $forms;
    }

    /**
     * Get subforms for a main form
     *
     * @param string $mainFormName Main form name
     * @return array List of subform names
     */
    public static function getSubforms(string $mainFormName): array
    {
        $subforms = [];

        // Determine which directory to search based on form type
        if (self::isInternalForm($mainFormName)) {
            $directories = [self::INTERNAL_DEFINITIONS_DIR];
        } else {
            // Check external first, then app, then internal for backward compatibility
            $directories = [self::EXTERNAL_DEFINITIONS_DIR, self::APP_DEFINITIONS_DIR, self::INTERNAL_DEFINITIONS_DIR];
        }

        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $pattern = $dir . '/' . $mainFormName . '_*.json';
            $files = glob($pattern);

            foreach ($files as $file) {
                $name = basename($file, '.json');
                // Extract subform name (remove mainFormName_ prefix)
                $subformName = substr($name, strlen($mainFormName) + 1);
                if (!in_array($subformName, $subforms, true)) {
                    $subforms[] = $subformName;
                }
            }
        }

        return $subforms;
    }

    /**
     * Get a map of sourceFormId -> securityByUser for all forms
     * Used by JsonFormRenderer to replace tblForms queries
     *
     * @return array Map of [sourceFormId => bool securityByUser]
     */
    public static function getFormSecurityByUserMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        $directories = [self::INTERNAL_DEFINITIONS_DIR, self::APP_DEFINITIONS_DIR, self::EXTERNAL_DEFINITIONS_DIR];

        foreach ($directories as $dir) {
            $files = self::getJsonFilesInDir($dir);
            foreach ($files as $file) {
                $formName = basename($file, '.json');
                $def = self::loadRaw($formName);
                if ($def !== null && isset($def['sourceFormId'])) {
                    $cache[(int)$def['sourceFormId']] = (bool)($def['securityByUser'] ?? false);
                }
            }
        }

        return $cache;
    }

    /**
     * Get a map of sourceFormId -> subforms array for all forms
     * Used by JsonFormRenderer to replace tblSubForms queries
     *
     * @return array Map of [parentFormId => [[formId, formName, securityByUser], ...]]
     */
    public static function getSubformsMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        $directories = [self::INTERNAL_DEFINITIONS_DIR, self::APP_DEFINITIONS_DIR, self::EXTERNAL_DEFINITIONS_DIR];

        foreach ($directories as $dir) {
            $files = self::getJsonFilesInDir($dir);
            foreach ($files as $file) {
                $formName = basename($file, '.json');
                $def = self::loadRaw($formName);
                if ($def === null || !isset($def['sourceFormId'])) {
                    continue;
                }

                $parentFormId = (int)$def['sourceFormId'];
                $subformsList = $def['subforms'] ?? [];

                if (!empty($subformsList)) {
                    $cache[$parentFormId] = [];
                    foreach ($subformsList as $sub) {
                        // Skip disabled subforms
                        if (!empty($sub['_disabled'])) {
                            continue;
                        }

                        // Load subform definition to get its securityByUser
                        $subformName = $sub['form'] ?? $sub['name'] ?? '';
                        $subDef = $subformName ? self::loadRaw($subformName) : null;

                        $cache[$parentFormId][] = [
                            'formId' => $sub['sourceFormId'] ?? 0,
                            'formName' => $sub['title'] ?? $sub['name'] ?? '',
                            'hasSecurityByUser' => $subDef ? (bool)($subDef['securityByUser'] ?? false) : false,
                        ];
                    }
                }
            }
        }

        return $cache;
    }

    /**
     * Get parent form ID for a subform
     * Returns null if the form is not a subform (i.e., it's a main form)
     *
     * @param int $subformId The subform's sourceFormId
     * @return int|null Parent form ID or null if not a subform
     */
    public static function getParentFormId(int $subformId): ?int
    {
        static $cache = null;
        if ($cache === null) {
            $cache = [];
            // Build reverse mapping from subforms map
            $subformsMap = self::getSubformsMap();
            foreach ($subformsMap as $parentFormId => $subforms) {
                foreach ($subforms as $sub) {
                    $subId = (int)($sub['formId'] ?? 0);
                    if ($subId > 0) {
                        $cache[$subId] = $parentFormId;
                    }
                }
            }
        }
        return $cache[$subformId] ?? null;
    }

    /**
     * Check if a form is a subform (has a parent form)
     *
     * @param int $formId The form's sourceFormId
     * @return bool True if the form is a subform
     */
    public static function isSubform(int $formId): bool
    {
        return self::getParentFormId($formId) !== null;
    }

    /**
     * Get sourceFormId from form name
     *
     * @param string $formName Form name (without .json extension)
     * @return int|null sourceFormId or null if not found
     */
    public static function getFormIdByName(string $formName): ?int
    {
        // normalizeFormName is called in loadRaw(), but explicit here for clarity
        $def = self::loadRaw($formName);
        if ($def !== null && isset($def['sourceFormId'])) {
            return (int)$def['sourceFormId'];
        }
        return null;
    }

    /**
     * Get parent form ID for a subform by form name
     * Returns null if the form is not a subform
     *
     * @param string $formName Form name (without .json extension)
     * @return int|null Parent form ID or null if not a subform
     */
    public static function getParentFormIdByName(string $formName): ?int
    {
        $formId = self::getFormIdByName($formName);
        if ($formId === null) {
            return null;
        }
        return self::getParentFormId($formId);
    }

    /**
     * Get table-to-form mapping from JSON definitions
     * Used to find which form handles a specific database table
     *
     * @return array Map of [tableName (uppercase) => formId]
     */
    public static function getTableToFormMap(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $cache = [];
        $directories = [self::INTERNAL_DEFINITIONS_DIR, self::APP_DEFINITIONS_DIR, self::EXTERNAL_DEFINITIONS_DIR];

        foreach ($directories as $dir) {
            $files = self::getJsonFilesInDir($dir);
            foreach ($files as $file) {
                $formName = basename($file, '.json');
                $def = self::loadRaw($formName);
                if ($def === null || !isset($def['sourceFormId']) || !isset($def['table'])) {
                    continue;
                }

                // Only include forms that allow adding new records
                $allowAdd = $def['allowAdd'] ?? true;
                if ($allowAdd) {
                    $tableName = strtoupper($def['table']);
                    // Store first match (forms are processed in file order)
                    if (!isset($cache[$tableName])) {
                        $cache[$tableName] = (int)$def['sourceFormId'];
                    }
                }
            }
        }

        return $cache;
    }

    /**
     * Get filter field for a form by sourceFormId
     *
     * @param int $sourceFormId Form ID
     * @return string|null Filter field name or null
     */
    public static function getFilterFieldByFormId(int $sourceFormId): ?string
    {
        $def = self::getFormDefBySourceId($sourceFormId);
        if ($def === null) {
            return null;
        }

        $filterField = $def['filter']['field'] ?? $def['filterIdName'] ?? '';
        return $filterField !== '' ? $filterField : null;
    }

    /**
     * Convert JSON format to legacy Q_* indexed array format
     *
     * This allows JSON-defined forms to work with existing code that uses
     * the GetFormDef() array structure.
     *
     * @param array $data JSON form definition
     * @return array Legacy format array
     */
    private static function convertToLegacyFormat(array $data): array
    {
        $legacy = [];

        // Form-level properties (single values stored as arrays for consistency)
        $legacy[Q_FKDATABASE] = [$data['database'] ?? ''];
        $legacy[Q_FRMIDFLD] = [$data['idField'] ?? 'ID'];
        $legacy[Q_AFTERPOSTURL] = [$data['afterPostUrl'] ?? ''];
        $legacy[Q_SQLTABLENAME] = [$data['table'] ?? ''];
        $legacy[Q_MENUNEW] = [$data['allowAdd'] ?? true];
        $legacy[Q_MENUDELETE] = [$data['allowDelete'] ?? true];
        $legacy[Q_MENUCOPY] = [$data['allowCopy'] ?? false];
        $legacy[Q_PREVIEWURL] = [$data['previewUrl'] ?? ''];
        $legacy[Q_FORMNAME] = [$data['name'] ?? $data['title'] ?? ''];
        $legacy[Q_SECBYUSER] = [$data['securityByUser'] ?? false];
        $legacy[Q_STORELASTMOD] = [$data['storeLastModified'] ?? false];
        $legacy[Q_CACHE_PREFIX] = [$data['cachePrefix'] ?? ''];
        $legacy[Q_ONLOADJS] = [$data['onLoadJs'] ?? ''];
        $legacy[Q_FILTERIDNAME] = [$data['filterIdName'] ?? ''];
        $legacy[Q_PARENTFORM] = [$data['parentForm'] ?? ''];
        $legacy[Q_QUICKFIELDS] = [$data['quickSearchFields'] ?? ''];
        $legacy[Q_NAMEQUERY] = [$data['listQuery'] ?? ''];

        // Group fields for tree view (up to 3 levels)
        $groupFields = $data['groupFields'] ?? [];
        $legacy[Q_GROUP1FIELD] = [$groupFields[0] ?? ''];
        $legacy[Q_GROUP2FIELD] = [$groupFields[1] ?? ''];
        $legacy[Q_GROUP3FIELD] = [$groupFields[2] ?? ''];

        // Detail field for tree display
        $legacy[Q_DETAILFIELD] = [$data['detailField'] ?? ''];

        // Extra buttons (up to 5)
        // Support both extraButtons array format AND root-level extraIconURL/extraIconTitle properties
        for ($i = 1; $i <= 5; $i++) {
            $btn = $data['extraButtons'][$i - 1] ?? [];
            $suffix = $i == 1 ? '' : (string)$i;

            // Check for root-level properties (extraIconURL, extraIcon2URL, etc.)
            $rootUrlKey = 'extraIcon' . $suffix . 'URL';
            $rootResKey = 'extraIcon' . $suffix . 'Resource';
            $rootTitleKey = 'extraIcon' . $suffix . 'Title';

            // Use root-level properties if set, otherwise fall back to extraButtons array
            $url = $data[$rootUrlKey] ?? $btn['url'] ?? '';
            $res = $data[$rootResKey] ?? $btn['icon'] ?? '';
            $title = $data[$rootTitleKey] ?? $btn['title'] ?? '';

            $legacy[constant('Q_EXTRAICON' . ($i == 1 ? '' : $i) . 'URL')] = [$url];
            $legacy[constant('Q_EXTRAICON' . ($i == 1 ? '' : $i) . 'RES')] = [$res];
            $legacy[constant('Q_EXTRAICON' . ($i == 1 ? '' : $i) . 'TITLE')] = [$title];
        }

        // Filter field
        $filter = $data['filter'] ?? [];
        $legacy[Q_FILTERFIELDNAME] = [$filter['field'] ?? ''];
        $legacy[Q_FILTERDESCR] = [$filter['description'] ?? ''];

        // Initialize field arrays
        $fieldArrays = [
            Q_CONTROLID, Q_FIELDNAME, Q_CONTROLTYPEID, Q_ISREQUIRED, Q_CAPTION,
            Q_POSTCAPTION, Q_BASEFIELDNAME, Q_CTRLIDFIELD, Q_FOREIGNIDFIELD,
            Q_SOURCETABLE, Q_SQLLIST, Q_HEIGHT, Q_HTMLTAGS, Q_IMGPATH,
            Q_IMGWIDTHFLD, Q_IMGHEIGHTFLD, Q_IMGRESIZETYPE, Q_IMGRESIZEHEIGHT,
            Q_IMGRESIZEWIDTH, Q_FILERANDOM, Q_CHKLISTWIDTH, Q_PASSONTOPOST,
            Q_XMLSNIPPET, Q_DIRFILENAME, Q_DIRTEMPLATE, Q_DATABASEID,
            Q_NOSPAMJS, Q_NEWCHANGABLEONLY, Q_FLDREADONLY, Q_FLDLIMITEDHTML,
            Q_FLDMAXCHARS, Q_KEEPWITHNEXT, Q_SCHEMA_DATE_PREC, Q_SCHEMA_DEFAULT,
            Q_SCHEMA_CHAR_MAXL, Q_SCHEMA_NUM_PREC, Q_SCHEMA_DATATYPE,
            Q_ACTIE, Q_BEHEER, Q_RENDERER, Q_RENDEROPTIONS
        ];

        foreach ($fieldArrays as $idx) {
            $legacy[$idx] = [];
        }

        // Process fields
        $fields = $data['fields'] ?? [];
        foreach ($fields as $i => $field) {
            // Resolve control type from name to ID
            $controlType = $field['type'] ?? 'textbox';
            if (is_string($controlType)) {
                $controlType = self::CONTROL_TYPES[strtolower($controlType)] ?? 3;
            }

            $legacy[Q_CONTROLID][$i] = $field['id'] ?? '';
            $legacy[Q_FIELDNAME][$i] = $field['name'] ?? '';
            $legacy[Q_CONTROLTYPEID][$i] = $controlType;
            $legacy[Q_ISREQUIRED][$i] = $field['required'] ?? false;
            $legacy[Q_CAPTION][$i] = $field['caption'] ?? $field['label'] ?? '';
            $legacy[Q_POSTCAPTION][$i] = $field['postCaption'] ?? $field['hint'] ?? '';
            $legacy[Q_BASEFIELDNAME][$i] = $field['baseField'] ?? '';
            $legacy[Q_CTRLIDFIELD][$i] = $field['idField'] ?? '';
            $legacy[Q_FOREIGNIDFIELD][$i] = $field['displayField'] ?? '';
            $legacy[Q_SOURCETABLE][$i] = $field['sourceTable'] ?? '';
            $legacy[Q_SQLLIST][$i] = $field['sql'] ?? '';
            $legacy[Q_HEIGHT][$i] = $field['height'] ?? 1;
            $legacy[Q_HTMLTAGS][$i] = $field['allowHtml'] ?? $field['html'] ?? false;
            $legacy[Q_IMGPATH][$i] = $field['path'] ?? '';
            $legacy[Q_IMGWIDTHFLD][$i] = $field['widthField'] ?? '';
            $legacy[Q_IMGHEIGHTFLD][$i] = $field['heightField'] ?? '';
            $legacy[Q_IMGRESIZETYPE][$i] = $field['resizeType'] ?? 0;
            $legacy[Q_IMGRESIZEHEIGHT][$i] = $field['resizeHeight'] ?? 0;
            $legacy[Q_IMGRESIZEWIDTH][$i] = $field['resizeWidth'] ?? 0;
            $legacy[Q_FILERANDOM][$i] = $field['randomName'] ?? false;
            $legacy[Q_CHKLISTWIDTH][$i] = $field['width'] ?? 200;
            $legacy[Q_PASSONTOPOST][$i] = $field['passToPost'] ?? false;
            $legacy[Q_XMLSNIPPET][$i] = $field['xmlSnippet'] ?? '';
            $legacy[Q_DIRFILENAME][$i] = $field['dirFileName'] ?? '';
            $legacy[Q_DIRTEMPLATE][$i] = $field['dirTemplate'] ?? '';
            $legacy[Q_DATABASEID][$i] = $field['database'] ?? '';
            $legacy[Q_NOSPAMJS][$i] = $field['noSpamJs'] ?? false;
            $legacy[Q_NEWCHANGABLEONLY][$i] = $field['newOnly'] ?? $field['editableOnNewOnly'] ?? false;
            $legacy[Q_FLDREADONLY][$i] = $field['readOnly'] ?? $field['readonly'] ?? false;
            $legacy[Q_FLDLIMITEDHTML][$i] = $field['limitedHtml'] ?? false;
            $legacy[Q_FLDMAXCHARS][$i] = $field['maxChars'] ?? 0;
            $legacy[Q_KEEPWITHNEXT][$i] = $field['combineWithNext'] ?? false;
            $legacy[Q_SCHEMA_DATE_PREC][$i] = $field['dateFormat'] ?? '';
            $legacy[Q_SCHEMA_DEFAULT][$i] = $field['defaultValue'] ?? $field['default'] ?? '';
            $legacy[Q_SCHEMA_CHAR_MAXL][$i] = $field['maxLength'] ?? 0;
            $legacy[Q_SCHEMA_NUM_PREC][$i] = $field['numericPrecision'] ?? '';

            // Use explicit dataType from JSON definition
            $legacy[Q_SCHEMA_DATATYPE][$i] = $field['dataType'] ?? '';
            $legacy[Q_ACTIE][$i] = $field['action'] ?? '';
            $legacy[Q_BEHEER][$i] = $field['adminOnly'] ?? false;
            $legacy[Q_RENDERER][$i] = $field['renderer'] ?? '';
            $legacy[Q_RENDEROPTIONS][$i] = !empty($field['options']) ? json_encode($field['options']) : '';

            // Extended properties for new control types (stored in extra array)
            if (!isset($legacy['_extended'])) {
                $legacy['_extended'] = [];
            }
            $legacy['_extended'][$i] = [
                'options' => $field['options'] ?? [],
                'renderer' => $field['renderer'] ?? '',
                'validation' => $field['validation'] ?? [],
                'dependencies' => $field['dependencies'] ?? [],
                'css' => $field['css'] ?? '',
                'class' => $field['class'] ?? '',
            ];
        }

        // Store original JSON data for extended access
        $legacy['_json'] = $data;

        return $legacy;
    }

    /**
     * Check if a form is an internal (CMA system) form
     *
     * @param string $formName Form name (should already be normalized to lowercase)
     * @return bool True if internal form
     */
    private static function isInternalForm(string $formName): bool
    {
        // Normalize to ensure case-insensitive matching
        $formName = strtolower($formName);

        // Direct match
        if (in_array($formName, self::INTERNAL_FORMS, true)) {
            return true;
        }

        // Check if it's a subform of an internal form (e.g., users_notifications)
        foreach (self::INTERNAL_FORMS as $internalForm) {
            if (strpos($formName, $internalForm . '_') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get file path for a form definition
     *
     * Internal forms are loaded from /cma/assets/forms/definitions/
     * App-specific forms are loaded from /cma/assets/forms/app/
     * External (user-defined) forms are loaded from /site/config/forms/
     * Falls back to internal path for backward compatibility
     *
     * @param string $formName Form name
     * @return string Full file path to the JSON definition
     */
    public static function getFilePath(string $formName): string
    {
        // Normalize form name to lowercase for case-insensitive lookups
        $formName = self::normalizeFormName($formName);

        // Internal forms always use internal path
        if (self::isInternalForm($formName)) {
            return self::INTERNAL_DEFINITIONS_DIR . '/' . $formName . '.json';
        }

        // External forms: check external path first
        $externalPath = self::EXTERNAL_DEFINITIONS_DIR . '/' . $formName . '.json';
        if (file_exists($externalPath)) {
            return $externalPath;
        }

        // Check app-specific forms
        $appPath = self::APP_DEFINITIONS_DIR . '/' . $formName . '.json';
        if (file_exists($appPath)) {
            return $appPath;
        }

        // Fallback to internal path for backward compatibility during migration
        return self::INTERNAL_DEFINITIONS_DIR . '/' . $formName . '.json';
    }

    /**
     * Create the definitions directory if it doesn't exist
     *
     * @param string|null $formName Form name to determine which directory
     * @return bool Success
     */
    public static function ensureDirectoryExists(?string $formName = null): bool
    {
        // Determine directory based on form type
        if ($formName !== null && !self::isInternalForm($formName)) {
            $dir = self::EXTERNAL_DEFINITIONS_DIR;
        } else {
            $dir = self::INTERNAL_DEFINITIONS_DIR;
        }

        if (!is_dir($dir)) {
            return @mkdir($dir, 0755, true);
        }
        return true;
    }

    /**
     * Save a form definition to JSON
     *
     * Internal forms are saved to /cma/assets/forms/definitions/
     * External (user-defined) forms are saved to /site/config/forms/
     *
     * @param string $formName Form name
     * @param array $data Form definition data
     * @return bool Success
     */
    public static function save(string $formName, array $data): bool
    {
        self::ensureDirectoryExists($formName);

        // Determine target path
        if (self::isInternalForm($formName)) {
            $path = self::INTERNAL_DEFINITIONS_DIR . '/' . $formName . '.json';
        } else {
            $path = self::EXTERNAL_DEFINITIONS_DIR . '/' . $formName . '.json';
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            \Cma\Services\Logger::warning("JsonFormLoader: Failed to encode form", [
                'formName' => $formName,
                'jsonError' => json_last_error_msg()
            ]);
            return false;
        }

        $result = file_put_contents($path, $json);

        // Clear caches
        unset(self::$cache[$formName]);
        unset(self::$cache['raw_' . $formName]);
        self::clearCache($formName);

        return $result !== false;
    }

    /**
     * Export a database form to JSON format
     *
     * @param int $formId Form ID from tblForms
     * @param string|null $formName Custom name (auto-generated if null)
     * @return array|null JSON-compatible form definition, or null on error
     */
    public static function exportFromDatabase(int $formId, ?string $formName = null): ?array
    {
        $arrRep = \GetFormDef($formId);
        if (!Arr::isArray($arrRep) && !($arrRep instanceof \ArrayAccess)) {
            return null;
        }

        // Generate a safe form name (lowercase, underscores only)
        $safeName = $formName ?? self::generateFormName($arrRep[Q_FORMNAME][0] ?? 'form_' . $formId);
        $originalTitle = $arrRep[Q_FORMNAME][0] ?? '';

        // Build JSON structure with schema reference
        $json = [
            '$schema' => '../schema/form-definition.schema.json',
            'name' => $safeName,
            'title' => $originalTitle,
            'table' => $arrRep[Q_SQLTABLENAME][0] ?? '',
            'database' => $arrRep[Q_FKDATABASE][0] ?? '',
            'idField' => $arrRep[Q_FRMIDFLD][0] ?? 'ID',
            'allowAdd' => (bool)($arrRep[Q_MENUNEW][0] ?? true),
            'allowDelete' => (bool)($arrRep[Q_MENUDELETE][0] ?? true),
            'allowCopy' => (bool)($arrRep[Q_MENUCOPY][0] ?? false),
            'securityByUser' => (bool)($arrRep[Q_SECBYUSER][0] ?? false),
            'storeLastModified' => (bool)($arrRep[Q_STORELASTMOD][0] ?? false),
            'previewUrl' => $arrRep[Q_PREVIEWURL][0] ?? '',
            'afterPostUrl' => $arrRep[Q_AFTERPOSTURL][0] ?? '',
            'onLoadJs' => $arrRep[Q_ONLOADJS][0] ?? '',
            'filterIdName' => $arrRep[Q_FILTERIDNAME][0] ?? '',
            'quickSearchFields' => $arrRep[Q_QUICKFIELDS][0] ?? '',
            'listQuery' => $arrRep[Q_NAMEQUERY][0] ?? '',
            'sourceFormId' => $formId, // Keep reference to original
        ];

        // Filter
        if (!empty($arrRep[Q_FILTERFIELDNAME][0])) {
            $json['filter'] = [
                'field' => $arrRep[Q_FILTERFIELDNAME][0],
                'description' => $arrRep[Q_FILTERDESCR][0] ?? '',
            ];
        }

        // Extra buttons
        $extraButtons = [];
        for ($i = 1; $i <= 5; $i++) {
            $urlKey = constant('Q_EXTRAICON' . ($i == 1 ? '' : $i) . 'URL');
            $resKey = constant('Q_EXTRAICON' . ($i == 1 ? '' : $i) . 'RES');
            $titleKey = constant('Q_EXTRAICON' . ($i == 1 ? '' : $i) . 'TITLE');

            $url = $arrRep[$urlKey][0] ?? '';
            $icon = $arrRep[$resKey][0] ?? '';
            $title = $arrRep[$titleKey][0] ?? '';

            if ($url || $icon || $title) {
                $extraButtons[] = [
                    'url' => $url,
                    'icon' => $icon,
                    'title' => $title,
                ];
            }
        }
        if (!empty($extraButtons)) {
            $json['extraButtons'] = $extraButtons;
        }

        // Fields
        $json['fields'] = [];
        $fieldNames = $arrRep[Q_FIELDNAME];
        $fieldCount = (Arr::isArray($fieldNames) || $fieldNames instanceof \Countable) ? count($fieldNames) : 0;

        for ($i = 0; $i < $fieldCount; $i++) {
            $controlTypeId = (int)($arrRep[Q_CONTROLTYPEID][$i] ?? 3);
            $controlTypeName = array_search($controlTypeId, self::CONTROL_TYPES);
            if ($controlTypeName === false) {
                $controlTypeName = 'textbox';
            }

            $fieldName = $arrRep[Q_FIELDNAME][$i] ?? '';
            $caption = $arrRep[Q_CAPTION][$i] ?? '';

            // For group separators and other display-only controls, generate a name from caption or index
            if (empty($fieldName)) {
                if ($controlTypeId == 15) { // groupseparator
                    $fieldName = '_group_' . $i;
                } elseif ($controlTypeId == 12) { // label
                    $fieldName = '_label_' . $i;
                } elseif ($controlTypeId == 103) { // tip
                    $fieldName = '_tip_' . $i;
                } else {
                    // Skip fields with no name that aren't special types
                    continue;
                }
            }

            $field = [
                'name' => $fieldName,
                'type' => $controlTypeName,
                'caption' => $caption,
            ];

            // Only add non-default values
            if ($arrRep[Q_ISREQUIRED][$i] ?? false) {
                $field['required'] = true;
            }
            if ($arrRep[Q_FLDREADONLY][$i] ?? false) {
                $field['readonly'] = true;
            }
            if ($arrRep[Q_BEHEER][$i] ?? false) {
                $field['adminOnly'] = true;
            }
            if (!empty($arrRep[Q_POSTCAPTION][$i])) {
                $field['hint'] = $arrRep[Q_POSTCAPTION][$i];
            }
            if (!empty($arrRep[Q_ACTIE][$i])) {
                $field['action'] = $arrRep[Q_ACTIE][$i];
            }

            // Control-specific properties
            switch ($controlTypeId) {
                case 2: // combobox
                case 16: // userlist
                case 18: // xmlstore
                    if (!empty($arrRep[Q_SOURCETABLE][$i])) {
                        $field['sourceTable'] = $arrRep[Q_SOURCETABLE][$i];
                    }
                    if (!empty($arrRep[Q_CTRLIDFIELD][$i])) {
                        $field['idField'] = $arrRep[Q_CTRLIDFIELD][$i];
                    }
                    if (!empty($arrRep[Q_FOREIGNIDFIELD][$i])) {
                        $field['displayField'] = $arrRep[Q_FOREIGNIDFIELD][$i];
                    }
                    if (!empty($arrRep[Q_SQLLIST][$i])) {
                        $field['sql'] = $arrRep[Q_SQLLIST][$i];
                    }
                    if (!empty($arrRep[Q_DATABASEID][$i])) {
                        $field['database'] = $arrRep[Q_DATABASEID][$i];
                    }
                    break;

                case 3: // textbox
                case 17: // email
                    $maxLen = (int)($arrRep[Q_SCHEMA_CHAR_MAXL][$i] ?? 0);
                    if ($maxLen > 0) {
                        $field['maxLength'] = $maxLen;
                    }
                    if (!empty($arrRep[Q_SCHEMA_DATE_PREC][$i])) {
                        $field['dateFormat'] = $arrRep[Q_SCHEMA_DATE_PREC][$i];
                    }
                    break;

                case 6: // memo
                    $height = (int)($arrRep[Q_HEIGHT][$i] ?? 3);
                    if ($height != 3) {
                        $field['height'] = $height;
                    }
                    if ($arrRep[Q_HTMLTAGS][$i] ?? false) {
                        $field['allowHtml'] = true;
                    }
                    if ($arrRep[Q_FLDLIMITEDHTML][$i] ?? false) {
                        $field['limitedHtml'] = true;
                    }
                    if ((int)($arrRep[Q_FLDMAXCHARS][$i] ?? 0) > 0) {
                        $field['maxChars'] = (int)$arrRep[Q_FLDMAXCHARS][$i];
                    }
                    break;

                case 8: // checklist
                    if (!empty($arrRep[Q_SQLLIST][$i])) {
                        $field['sql'] = $arrRep[Q_SQLLIST][$i];
                    }
                    $width = (int)($arrRep[Q_CHKLISTWIDTH][$i] ?? 200);
                    if ($width != 200) {
                        $field['width'] = $width;
                    }
                    break;

                case 9: // image
                case 11: // file
                    if (!empty($arrRep[Q_IMGPATH][$i])) {
                        $field['path'] = $arrRep[Q_IMGPATH][$i];
                    }
                    if ($arrRep[Q_FILERANDOM][$i] ?? false) {
                        $field['randomName'] = true;
                    }
                    if ($controlTypeId == 9) {
                        $resizeType = (int)($arrRep[Q_IMGRESIZETYPE][$i] ?? 0);
                        if ($resizeType > 0) {
                            $field['resizeType'] = $resizeType;
                            $field['resizeWidth'] = (int)($arrRep[Q_IMGRESIZEWIDTH][$i] ?? 0);
                            $field['resizeHeight'] = (int)($arrRep[Q_IMGRESIZEHEIGHT][$i] ?? 0);
                        }
                        if (!empty($arrRep[Q_IMGWIDTHFLD][$i])) {
                            $field['widthField'] = $arrRep[Q_IMGWIDTHFLD][$i];
                        }
                        if (!empty($arrRep[Q_IMGHEIGHTFLD][$i])) {
                            $field['heightField'] = $arrRep[Q_IMGHEIGHTFLD][$i];
                        }
                    }
                    break;

                case 15: // groupseparator
                    // Caption is the group title
                    break;
            }

            // Combine with next
            if ($arrRep[Q_KEEPWITHNEXT][$i] ?? false) {
                $field['combineWithNext'] = true;
            }

            $json['fields'][] = $field;
        }

        // Get subforms
        $subforms = \SubFormGetArray($formId);
        if (Arr::isArray($subforms) || $subforms instanceof \ArrayAccess) {
            $json['subforms'] = [];
            $subformCount = count($subforms[SUBFORM_ID] ?? []);
            for ($i = 0; $i < $subformCount; $i++) {
                $subformId = $subforms[SUBFORM_ID][$i];
                $subformName = $subforms[SUBFORM_NAME][$i] ?? 'subform_' . $subformId;
                $json['subforms'][] = [
                    'form' => self::generateFormName($subformName),
                    'title' => $subformName,
                    'sourceFormId' => $subformId,
                    // 'adminOnly' removed in 5.4.0 - feature no longer used
                ];
            }
        }

        return $json;
    }

    /**
     * Generate a safe form name from a display name
     */
    public static function generateFormName(string $displayName): string
    {
        // Lowercase
        $name = strtolower($displayName);
        // Replace spaces and special chars with underscores
        $name = preg_replace('/[^a-z0-9]+/', '_', $name);
        // Remove leading/trailing underscores
        $name = trim($name, '_');
        // Limit length
        if (strlen($name) > 50) {
            $name = substr($name, 0, 50);
        }
        return $name ?: 'form';
    }
}
