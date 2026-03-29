<?php
/**
 * Migration: Add accessLevel to menu.json
 *
 * Adds accessLevel property to menu items and menus.
 * Default value is 'user', Systeem menu gets 'admin'.
 *
 * Access levels:
 * - 'user': All logged-in users (default)
 * - 'admin': Administrators only
 * - 'developer': Developers only
 */

require_once __DIR__ . '/../bootstrap.inc';

echo "<h2>Migration: Add accessLevel to menu.json</h2>\n";

$menuFile = dirname(__DIR__, 2) . '/data/menu.json';

if (!file_exists($menuFile)) {
    echo "<p style='color:red'>Error: menu.json not found at {$menuFile}</p>\n";
    exit(1);
}

$json = file_get_contents($menuFile);
$data = json_decode($json, true);

if ($data === null) {
    echo "<p style='color:red'>Error: Invalid JSON in menu.json</p>\n";
    exit(1);
}

$modified = 0;

// Process each menu
foreach ($data['menus'] as &$menu) {
    // Add accessLevel to menu if not present
    if (!isset($menu['accessLevel'])) {
        // Systeem menu gets admin access, others get user (default)
        if (strtolower($menu['name'] ?? '') === 'systeem') {
            $menu['accessLevel'] = 'admin';
            $modified++;
            echo "<p>Added accessLevel='admin' to menu: {$menu['name']}</p>\n";
        }
        // Other menus don't need explicit accessLevel (defaults to 'user')
    }

    // Process menu items
    if (isset($menu['items']) && is_array($menu['items'])) {
        foreach ($menu['items'] as &$item) {
            // Items inherit from menu unless they have their own accessLevel
            // No need to add default 'user' - it's implicit
        }
    }
}
unset($menu);

if ($modified > 0) {
    // Save the updated menu
    $newJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson .= "\n";

    if (file_put_contents($menuFile, $newJson) !== false) {
        echo "<p style='color:green'>Successfully updated menu.json with {$modified} changes</p>\n";

        // Clear menu cache
        \Cma\Services\MenuService::clearCache();
        echo "<p>Menu cache cleared</p>\n";
    } else {
        echo "<p style='color:red'>Error: Failed to write menu.json</p>\n";
        exit(1);
    }
} else {
    echo "<p>No changes needed - accessLevel already configured</p>\n";
}

echo "<h3>Summary</h3>\n";
echo "<p>Access level support has been added to menu.json.</p>\n";
echo "<p>Supported levels: 'user' (default), 'admin', 'developer'</p>\n";
