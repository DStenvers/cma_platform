<?php

namespace Cma\Services;

use App\Library\Cache;
use Cma\ConfigLoader;
use Cma\SecurityHelper;

/**
 * Menu Service
 *
 * Loads menu configuration from /site/config/menu.json (external)
 * Stores menu configuration in /site/data/menu.json
 * Replaces database-based menu from tblMenu and tblMenuItems
 *
 * Access Levels:
 * - "user" (default): All logged-in users
 * - "admin": Requires admin privileges
 * - "developer": Requires developer privileges
 */
class MenuService
{
    private static ?array $menuData = null;

    /**
     * Access level values (higher = more privileges)
     */
    private const ACCESS_LEVELS = [
        'user' => 0,
        'admin' => 1,
        'developer' => 2,
    ];

    /**
     * Config path (in /site/data/, outside CMA so updates don't overwrite it)
     */
    private const CONFIG_PATH = __DIR__ . '/../../../data/menu.json';

    /**
     * Get the active config path (external preferred, fallback to internal)
     */
    private static function getConfigPath(): string
    {
        return self::CONFIG_PATH;
    }

    /**
     * Get the save path (always use external for new saves)
     */
    private static function getSavePath(): string
    {
        $dir = dirname(self::CONFIG_PATH);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return self::CONFIG_PATH;
    }

    /**
     * Haal applicatie configuratie op (logo, kleuren, etc.)
     * Reads from config/app.json
     *
     * @return array
     */
    public static function getApplicationConfig(): array
    {
        $appConfig = ConfigLoader::getAppConfig();
        $company = $appConfig['company'] ?? [];

        return [
            'logo' => $company['logo'] ?? '',
            'logoWidth' => $company['logoWidth'] ?? 200,
            'logoHeight' => $company['logoHeight'] ?? 50,
            'url' => $company['url'] ?? '../',
            'backgroundColor' => $company['backgroundColor'] ?? '#3F096E'
        ];
    }

    /**
     * Haal specifieke applicatie waarde op
     *
     * @param string $key Sleutel (logo, logoWidth, logoHeight, url, backgroundColor)
     * @param mixed $default Standaardwaarde
     * @return mixed
     */
    public static function getApplicationValue(string $key, $default = null)
    {
        $config = self::getApplicationConfig();
        return $config[$key] ?? $default;
    }

    /**
     * Get all menus with their items
     *
     * @param bool $filterByAccess If true, filter by current user's access level
     * @return array
     */
    public static function getMenus(bool $filterByAccess = false): array
    {
        self::loadMenuData();
        $menus = self::$menuData['menus'] ?? [];

        if ($filterByAccess) {
            $menus = self::filterMenusByAccessLevel($menus);
        }

        return $menus;
    }

    /**
     * Get current user's access level value
     *
     * @return int 0=user, 1=admin, 2=developer
     */
    private static function getCurrentAccessLevel(): int
    {
        if (SecurityHelper::isDeveloper()) {
            return self::ACCESS_LEVELS['developer'];
        }
        if (SecurityHelper::isAdmin()) {
            return self::ACCESS_LEVELS['admin'];
        }
        return self::ACCESS_LEVELS['user'];
    }

    /**
     * Filter menus based on user's access level
     *
     * @param array $menus
     * @return array Filtered menus
     */
    private static function filterMenusByAccessLevel(array $menus): array
    {
        $userLevel = self::getCurrentAccessLevel();
        $filteredMenus = [];

        foreach ($menus as $menu) {
            // Check menu-level access
            $menuLevel = self::ACCESS_LEVELS[$menu['accessLevel'] ?? 'user'] ?? 0;
            if ($menuLevel > $userLevel) {
                continue; // User doesn't have access to this menu
            }

            // Filter items within the menu
            $filteredItems = [];
            foreach ($menu['items'] ?? [] as $item) {
                $itemLevel = self::ACCESS_LEVELS[$item['accessLevel'] ?? 'user'] ?? 0;
                if ($itemLevel <= $userLevel) {
                    $filteredItems[] = $item;
                }
            }

            // Only include menu if it has visible items
            if (!empty($filteredItems)) {
                $menu['items'] = $filteredItems;
                $filteredMenus[] = $menu;
            }
        }

        return $filteredMenus;
    }

    /**
     * Get a specific menu by ID
     *
     * @param int $menuId
     * @return array|null
     */
    public static function getMenu(int $menuId): ?array
    {
        self::loadMenuData();
        foreach (self::$menuData['menus'] ?? [] as $menu) {
            if (($menu['id'] ?? 0) === $menuId) {
                return $menu;
            }
        }
        return null;
    }

    /**
     * Get menu item by ID
     *
     * @param int $itemId
     * @return array|null
     */
    public static function getMenuItem(int $itemId): ?array
    {
        self::loadMenuData();
        foreach (self::$menuData['menus'] ?? [] as $menu) {
            foreach ($menu['items'] ?? [] as $item) {
                if (($item['id'] ?? 0) === $itemId) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Form access types (can be set per menu item)
     */
    private const FORM_ACCESS = [
        'none' => SecurityHelper::ACCESS_NONE,
        'readonly' => SecurityHelper::ACCESS_READ,
        'full' => SecurityHelper::ACCESS_FULL,
    ];

    /**
     * Get access level for a form (centralized access check)
     *
     * Logic:
     * 1. Admin/Developer: Always full access (ACCESS_FULL_BEHEER)
     * 2. Normal user: Check group-based rights from tblGroupRights
     * 3. If no rights found: No access (ACCESS_NONE)
     *
     * @param string $formName Form name to check
     * @return int SecurityHelper access constant (ACCESS_NONE, ACCESS_READ, ACCESS_FULL, ACCESS_FULL_BEHEER)
     */
    public static function getFormAccessLevel(string $formName): int
    {
        // DEBUG: Log all access check details
        $debugLog = function($msg) use ($formName) {
            error_log("[MenuService::getFormAccessLevel] form=$formName: $msg");
        };

        // Must be logged in
        $isLoggedIn = SecurityHelper::isLoggedIn();
        $debugLog("isLoggedIn=" . ($isLoggedIn ? 'true' : 'false'));

        if (!$isLoggedIn) {
            $debugLog("DENIED: Not logged in");
            return SecurityHelper::ACCESS_NONE;
        }

        // Check cookies directly for debugging
        $cmau = \App\Library\Cookie::get(SecurityHelper::COOKIE_USERID, '');
        $debugLog("Cookies: CMAU=$cmau");

        // Admins and developers always have full access
        $isAdmin = SecurityHelper::isAdmin();
        $isDeveloper = SecurityHelper::isDeveloper();
        $userLevel = SecurityHelper::getUserLevel();
        $debugLog("isAdmin=$isAdmin, isDeveloper=$isDeveloper, userLevel=$userLevel");

        if ($isAdmin) {
            $debugLog("GRANTED: User is admin, returning ACCESS_FULL_BEHEER (40)");
            return SecurityHelper::ACCESS_FULL_BEHEER;
        }

        // Normal user: check group-based rights
        $formId = \Cma\JsonFormLoader::getFormIdByName($formName);
        $debugLog("formId from JsonFormLoader=" . ($formId ?? 'null'));

        if ($formId === null) {
            // Form has no sourceFormId - cannot check group rights
            $debugLog("DENIED: Form has no sourceFormId");
            return SecurityHelper::ACCESS_NONE;
        }

        $userId = (int)(\App\Library\Cookie::get(SecurityHelper::COOKIE_USERID, 0));
        $debugLog("userId from cookie=$userId");

        if ($userId <= 0) {
            $debugLog("DENIED: Invalid userId");
            return SecurityHelper::ACCESS_NONE;
        }

        // Check group-based access via SecurityHelper
        // This checks tblGroupRights for the user's group memberships
        $accessLevel = SecurityHelper::checkFormRights($userId, $formId);
        $debugLog("checkFormRights returned=$accessLevel");

        // Return access level (defaults to NONE if no rights found)
        $debugLog("FINAL: Returning accessLevel=$accessLevel");
        return $accessLevel;
    }

    /**
     * Check if current user has access to a form (convenience method)
     *
     * @param string $formName Form name to check
     * @return bool True if user has at least readonly access
     */
    public static function hasAccessToForm(string $formName): bool
    {
        return self::getFormAccessLevel($formName) > SecurityHelper::ACCESS_NONE;
    }

    /**
     * Find menu item by form name (JSON filename without .json)
     *
     * @param string $formName
     * @return array|null
     */
    public static function findMenuItemByFormName(string $formName): ?array
    {
        self::loadMenuData();
        foreach (self::$menuData['menus'] ?? [] as $menu) {
            foreach ($menu['items'] ?? [] as $item) {
                // Check 'form' property (used in menu.json)
                if (($item['form'] ?? '') === $formName) {
                    return $item;
                }
                // Also check 'formName' for backward compatibility
                if (($item['formName'] ?? '') === $formName) {
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Check if a form name exists in any menu item
     *
     * @param string $formName
     * @return bool
     */
    public static function formExistsInMenu(string $formName): bool
    {
        return self::findMenuItemByFormName($formName) !== null;
    }

    /**
     * Get menu item ID for a form name (for security checks)
     *
     * @param string $formName
     * @return int|null
     */
    public static function getMenuItemIdForForm(string $formName): ?int
    {
        $item = self::findMenuItemByFormName($formName);
        return $item ? ($item['id'] ?? null) : null;
    }

    /**
     * Get all menu items (flat list)
     *
     * @return array
     */
    public static function getAllItems(): array
    {
        self::loadMenuData();
        $items = [];
        foreach (self::$menuData['menus'] ?? [] as $menu) {
            foreach ($menu['items'] ?? [] as $item) {
                $item['menuId'] = $menu['id'] ?? 0;
                $item['menuName'] = $menu['name'] ?? '';
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Get menu data for rendering
     *
     * @return array Array of rows with mName, formName, miID, iName, Href
     */
    public static function getMenuDataForRendering(): array
    {
        self::loadMenuData();
        $rows = [];

        foreach (self::$menuData['menus'] ?? [] as $menu) {
            foreach ($menu['items'] ?? [] as $item) {
                $rows[] = [
                    'mName' => $menu['name'] ?? '',
                    'formName' => $item['formName'] ?? null,
                    'miID' => $item['id'] ?? 0,
                    'iName' => $item['name'] ?? '',
                    'Href' => $item['href'] ?? ''
                ];
            }
        }

        return $rows;
    }

    /**
     * Load menu data from JSON file
     */
    private static function loadMenuData(): void
    {
        if (self::$menuData !== null) {
            return;
        }

        // Try cache first
        $cacheKey = 'menu_config';
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            self::$menuData = $cached;
            return;
        }

        // Load from file (external preferred, fallback to internal)
        $configPath = self::getConfigPath();
        if (!file_exists($configPath)) {
            self::$menuData = ['menus' => []];
            return;
        }

        $json = file_get_contents($configPath);
        if ($json === false) {
            self::$menuData = ['menus' => []];
            return;
        }

        $data = json_decode($json, true);
        if ($data === null) {
            self::$menuData = ['menus' => []];
            return;
        }

        self::$menuData = $data;

        // Cache for 1 hour
        Cache::set($cacheKey, self::$menuData, 3600);
    }

    /**
     * Clear cached menu data (call after menu.json is updated)
     */
    public static function clearCache(): void
    {
        self::$menuData = null;
        Cache::delete('menu_config');
    }

    /**
     * Save menu data to JSON file
     *
     * Always saves to external path (/site/config/menu.json)
     * Note: Application config is stored in app.json, not menu.json
     *
     * @param array $menus
     * @return bool
     */
    public static function saveMenus(array $menus): bool
    {
        // Load existing data to preserve version
        self::loadMenuData();

        $data = [
            '$schema' => 'schema/menu.schema.json',
            'version' => self::$menuData['version'] ?? '1.0.0',
            'menus' => $menus
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $savePath = self::getSavePath();
        $result = file_put_contents($savePath, $json);
        if ($result !== false) {
            self::clearCache();
            return true;
        }

        return false;
    }

    /**
     * Save full menu data to JSON file
     * Note: Application config is stored in app.json, not menu.json
     *
     * @param array $data Full menu data including 'menus'
     * @return bool
     */
    public static function saveFullData(array $data): bool
    {
        $fullData = [
            '$schema' => 'schema/menu.schema.json',
            'version' => $data['version'] ?? '1.0.0',
            'menus' => $data['menus'] ?? []
        ];

        $json = json_encode($fullData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $savePath = self::getSavePath();
        $result = file_put_contents($savePath, $json);
        if ($result !== false) {
            self::clearCache();
            return true;
        }

        return false;
    }
}
