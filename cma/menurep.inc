<?php

require_once __DIR__ . '/classes/ConfigLoader.php';
require_once __DIR__ . '/classes/Services/MenuService.php';

define("MENU_MENUNAME", 0);
define("MENU_FORMNAME", 1);  // JSON form name (filename without .json)
define("MENU_MENUITEMID", 2);
define("MENU_MENUITEMNAME", 3);
define("MENU_MENUITEMHREF", 4);

define("MENU_CACHE_ID_BASE", 'CMA_Menudef');

/**
 * Get menu cache ID with user context for debugging
 * Includes user ID to prevent caching issues between logged-in/logged-out states
 * Also includes access level to ensure proper caching per role
 */
function getMenuCacheId(): string {
    $userId = \Cma\SecurityHelper::getCurrentUserId();
    $level = \Cma\SecurityHelper::isDeveloper() ? 'dev' : (\Cma\SecurityHelper::isAdmin() ? 'admin' : 'user');
    return MENU_CACHE_ID_BASE . '_' . ($userId !== '' ? $userId : 'guest') . '_' . $level;
}

// Keep old constant for backward compatibility
define("MENU_CACHE_ID", MENU_CACHE_ID_BASE);

/**
 * Load menu data from JSON config
 * Returns array with menu structure
 * Filters menus based on current user's access level
 * @return array
 */
function loadMenuData(): array {
    // Load from MenuService with access level filtering
    $menus = \Cma\Services\MenuService::getMenus(true);

    if (empty($menus)) {
        return [
            MENU_MENUNAME => [],
            MENU_FORMNAME => [],
            MENU_MENUITEMID => [],
            MENU_MENUITEMNAME => [],
            MENU_MENUITEMHREF => [],
        ];
    }

    // Convert JSON structure to flat array format
    $result = [
        MENU_MENUNAME => [],
        MENU_FORMNAME => [],
        MENU_MENUITEMID => [],
        MENU_MENUITEMNAME => [],
        MENU_MENUITEMHREF => [],
    ];

    // Sort menus by order
    usort($menus, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

    foreach ($menus as $menu) {
        $menuName = $menu['name'] ?? '';
        $items = $menu['items'] ?? [];

        // Sort items by order
        usort($items, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        foreach ($items as $item) {
            // Skip invisible items
            if (isset($item['visible']) && $item['visible'] === false) {
                continue;
            }

            $result[MENU_MENUNAME][] = $menuName;
            $result[MENU_FORMNAME][] = $item['form'] ?? $item['formName'] ?? '';
            $result[MENU_MENUITEMID][] = $item['id'] ?? 0;
            $result[MENU_MENUITEMNAME][] = $item['name'] ?? '';
            $result[MENU_MENUITEMHREF][] = $item['href'] ?? '';
        }
    }

    return $result;
}
