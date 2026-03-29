<?php

namespace Cma\Services;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Cache;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Server;
use Cma\CmaRepository;
use Cma\FormDefinition;
use Cma\SecurityHelper;

/**
 * Base class for CMA Form Services
 *
 * Provides common functionality for form data operations:
 * - Form definition loading
 * - Database connection management
 * - Security checks
 * - Caching (both request-level and persistent cross-request)
 */
abstract class BaseFormService
{
    /**
     * Cache group name for form definitions
     */
    public const CACHE_GROUP_FORMDEFS = 'formdefs';

    /**
     * Cache TTL for form definitions (1 hour)
     */
    protected const FORMDEF_CACHE_TTL = 3600;

    /**
     * Cache for form definitions within request (memory cache)
     * @var array<int, array|\ArrayAccess>
     */
    protected static array $formDefCache = [];

    /**
     * Cache for security rights within request (avoids duplicate DB queries)
     * @var array<string, int> "formId_userId" => accessLevel
     */
    protected static array $accessLevelCache = [];

    /**
     * Get form definition array
     *
     * Uses a two-level cache strategy:
     * 1. Request-level memory cache (fastest, no serialization)
     * 2. Persistent cache with cross-instance invalidation support
     *
     * @param int $formId Form ID
     * @return array|\ArrayAccess|null Form definition or null if not found
     */
    protected static function getFormDef(int $formId): array|\ArrayAccess|null
    {
        // Level 1: Request-level memory cache (fastest)
        if (isset(self::$formDefCache[$formId])) {
            return self::$formDefCache[$formId];
        }

        // Level 2: Persistent cache with invalidation support
        $cacheKey = 'formdef_' . $formId;
        $cached = Cache::getWithInvalidation($cacheKey, self::CACHE_GROUP_FORMDEFS);
        if ($cached !== null) {
            // Store in memory cache for subsequent calls in this request
            self::$formDefCache[$formId] = $cached;
            return $cached;
        }

        // Cache miss: Load from database
        $arrRep = \GetFormDef($formId);
        if (!Arr::isArray($arrRep) && !($arrRep instanceof \ArrayAccess)) {
            return null;
        }

        // Store in persistent cache with invalidation support
        // Note: ColumnMajorArray implements ArrayAccess but serializes properly
        Cache::setWithInvalidation($cacheKey, $arrRep, self::CACHE_GROUP_FORMDEFS, self::FORMDEF_CACHE_TTL);

        // Store in memory cache
        self::$formDefCache[$formId] = $arrRep;
        return $arrRep;
    }

    /**
     * Clear form definition cache for a specific form
     *
     * Call this after modifying a form definition (e.g., in form designer).
     *
     * @param int $formId Form ID to clear
     */
    public static function clearFormDefCache(int $formId): void
    {
        // Clear memory cache
        unset(self::$formDefCache[$formId]);

        // Clear persistent cache
        $cacheKey = 'formdef_' . $formId;
        Cache::delete($cacheKey);
    }

    /**
     * Clear all form definition caches
     *
     * Call this to invalidate all cached form definitions across all PHP instances.
     */
    public static function clearAllFormDefCache(): void
    {
        // Clear memory cache
        self::$formDefCache = [];

        // Invalidate the entire formdefs group across all instances
        Cache::invalidateGroup(self::CACHE_GROUP_FORMDEFS);
    }

    /**
     * Get database connection for a form
     *
     * @param int $formId Form ID
     * @return mixed Database connection
     */
    protected static function getConnection(int $formId)
    {
        $arrRep = self::getFormDef($formId);
        if (!$arrRep) {
            return null;
        }

        $databaseId = $arrRep[\Q_FKDATABASE][0] ?? '';
        return CmaRepository::openConnectionById($databaseId);
    }

    /**
     * Get the current user ID
     *
     * @return int User ID or 0 if not logged in
     */
    protected static function getUserId(): int
    {
        return (int) Cookie::get(SecurityHelper::COOKIE_USERID, '0');
    }

    /**
     * Check if current user has access to form (with request-level caching)
     *
     * @param int $formId Form ID
     * @return int Access level (ACCESS_NONE, ACCESS_READ, etc.)
     */
    protected static function getAccessLevel(int $formId): int
    {
        $userId = self::getUserId();
        $cacheKey = "{$formId}_{$userId}";

        // Check request-level cache first
        if (isset(self::$accessLevelCache[$cacheKey])) {
            return self::$accessLevelCache[$cacheKey];
        }

        // Query and cache
        $accessLevel = SecurityHelper::checkFormRights($userId, $formId);
        self::$accessLevelCache[$cacheKey] = $accessLevel;

        return $accessLevel;
    }

    /**
     * Check if user can read records
     *
     * @param int $formId Form ID
     * @return bool
     */
    protected static function canRead(int $formId): bool
    {
        return self::getAccessLevel($formId) >= SecurityHelper::ACCESS_READ;
    }

    /**
     * Check if user can write/modify records
     *
     * @param int $formId Form ID
     * @return bool
     */
    protected static function canWrite(int $formId): bool
    {
        return self::getAccessLevel($formId) >= SecurityHelper::ACCESS_FULL;
    }

    /**
     * Build error response
     *
     * @param string $message Error message
     * @return array
     */
    protected static function error(string $message): array
    {
        return ['success' => false, 'error' => $message];
    }

    /**
     * Build success response
     *
     * @param array $data Additional data to include
     * @return array
     */
    protected static function success(array $data = []): array
    {
        return array_merge(['success' => true], $data);
    }

    /**
     * Escape value for HTML output
     *
     * @param mixed $value Value to escape
     * @return string
     */
    protected static function escape($value): string
    {
        return Server::htmlEncode((string) $value);
    }

    /**
     * Check form access and return error if no access
     *
     * Consolidates the common pattern:
     *   $userId = (int)Cookie::get(SecurityHelper::COOKIE_USERID, '0');
     *   $rights = SecurityHelper::checkFormRights($userId, $formId);
     *   if ($rights == SecurityHelper::ACCESS_NONE) {
     *       return self::error('Geen toegang tot dit formulier');
     *   }
     *
     * @param int $formId Form ID
     * @param int $requiredLevel Minimum access level required (default: ACCESS_READ)
     * @param string|null $errorMessage Custom error message (default: 'Geen toegang tot dit formulier')
     * @return array|null Error array if access denied, null if access granted
     */
    protected static function checkFormAccess(int $formId, int $requiredLevel = SecurityHelper::ACCESS_READ, ?string $errorMessage = null): ?array
    {
        $accessLevel = self::getAccessLevel($formId);

        if ($accessLevel < $requiredLevel) {
            return self::error($errorMessage ?? 'Geen toegang tot dit formulier');
        }

        return null; // Access granted
    }

    /**
     * Load and validate form definition
     *
     * Consolidates the common pattern:
     *   $arrRep = \GetFormDef($formId);
     *   $formDef = FormDefinition::fromArray($arrRep);
     *   if (!$formDef->isValid()) {
     *       return self::error('Formulier niet gevonden');
     *   }
     *
     * @param int $formId Form ID
     * @param string|null $errorMessage Custom error message (default: 'Formulier niet gevonden')
     * @return array ['formDef' => FormDefinition, 'arrRep' => array] on success, or error array on failure
     */
    protected static function loadAndValidateForm(int $formId, ?string $errorMessage = null): array
    {
        $arrRep = self::getFormDef($formId);

        if (!$arrRep) {
            return self::error($errorMessage ?? 'Formulier niet gevonden');
        }

        $formDef = FormDefinition::fromArray($arrRep);

        if (!$formDef->isValid()) {
            return self::error($errorMessage ?? 'Formulier niet gevonden');
        }

        return [
            'formDef' => $formDef,
            'arrRep' => $arrRep
        ];
    }

    /**
     * Check access and load form in one call
     *
     * Combines checkFormAccess() and loadAndValidateForm() for the most common pattern.
     *
     * @param int $formId Form ID
     * @param int $requiredLevel Minimum access level required (default: ACCESS_READ)
     * @return array ['formDef' => FormDefinition, 'arrRep' => array, 'accessLevel' => int] on success, or error array on failure
     */
    protected static function requireForm(int $formId, int $requiredLevel = SecurityHelper::ACCESS_READ): array
    {
        // Check access first
        $accessError = self::checkFormAccess($formId, $requiredLevel);
        if ($accessError !== null) {
            return $accessError;
        }

        // Load and validate form
        $result = self::loadAndValidateForm($formId);
        if (isset($result['error'])) {
            return $result;
        }

        // Add access level to result for convenience
        $result['accessLevel'] = self::getAccessLevel($formId);

        return $result;
    }

    /**
     * Open database connection for a form and return global $conn
     *
     * Consolidates the common pattern:
     *   CmaRepository::openConnectionById($formDef->getDatabaseId());
     *   global $conn;
     *
     * @param FormDefinition $formDef Form definition
     * @return mixed Database connection
     */
    protected static function openFormConnection(FormDefinition $formDef)
    {
        CmaRepository::openConnectionById($formDef->getDatabaseId());
        global $conn;
        return $conn;
    }
}
