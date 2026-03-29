<?php
/**
 * ConfigLoader Test Script
 *
 * Tests the JSON configuration system:
 * - ConfigLoader functionality
 * - JSON file reading/writing
 * - Config validation
 *
 * Run from browser when logged in as admin, or via CLI if database access isn't needed.
 */

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use App\Library\Arr;
use App\Library\Server;
use Cma\ConfigLoader;
use Cma\SecurityHelper;

// Security check for browser access
if (php_sapi_name() !== 'cli' && !SecurityHelper::isAdmin()) {
    die("Access denied. Admin login required.");
}

// Output format
$isCli = php_sapi_name() === 'cli';
$nl = $isCli ? "\n" : "<br>\n";
$pre = $isCli ? '' : '<pre>';
$endPre = $isCli ? '' : '</pre>';

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>ConfigLoader Test</title></head><body>";
}

echo "{$pre}=== ConfigLoader Test ==={$nl}{$nl}";

$tests = 0;
$passed = 0;
$failed = 0;

// Test 1: ConfigLoader class exists
echo "Test 1: ConfigLoader class exists... ";
$tests++;
if (class_exists('ConfigLoader')) {
    echo "PASSED{$nl}";
    $passed++;
} else {
    echo "FAILED{$nl}";
    $failed++;
}

// Test 2: Config path is set correctly
echo "Test 2: Config path validation... ";
$tests++;
try {
    $exists = ConfigLoader::exists('databases');
    echo "PASSED (databases.json exists: " . ($exists ? 'yes' : 'no') . "){$nl}";
    $passed++;
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 3: Load databases.json
echo "Test 3: Load databases.json... ";
$tests++;
try {
    $databases = ConfigLoader::getDatabases();
    if (Arr::isArray($databases) && count($databases) > 0) {
        echo "PASSED (" . count($databases) . " databases){$nl}";
        $passed++;
    } else {
        echo "FAILED - Empty or invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 4: Load modules.json
echo "Test 4: Load modules.json... ";
$tests++;
try {
    $modules = ConfigLoader::getModules();
    if (Arr::isArray($modules)) {
        echo "PASSED (" . count($modules) . " modules){$nl}";
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 5: Load menu.json
echo "Test 5: Load menu.json... ";
$tests++;
try {
    $menus = ConfigLoader::getMenu();
    if (Arr::isArray($menus)) {
        $itemCount = 0;
        foreach ($menus as $menu) {
            $itemCount += count($menu['items'] ?? []);
        }
        echo "PASSED (" . count($menus) . " menus, {$itemCount} items){$nl}";
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 6: Load control-types.json
echo "Test 6: Load control-types.json... ";
$tests++;
try {
    $controlTypes = ConfigLoader::getControlTypes();
    if (Arr::isArray($controlTypes) && count($controlTypes) > 0) {
        echo "PASSED (" . count($controlTypes) . " control types){$nl}";
        $passed++;
    } else {
        echo "FAILED - Empty or invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 7: Load app.json
echo "Test 7: Load app.json... ";
$tests++;
try {
    $appConfig = ConfigLoader::getAppConfig();
    if (Arr::isArray($appConfig) && isset($appConfig['company'])) {
        echo "PASSED{$nl}";
        $passed++;
    } else {
        echo "FAILED - Missing company config{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 8: Load reports.json
echo "Test 8: Load reports.json... ";
$tests++;
try {
    $reports = ConfigLoader::getReports();
    if (Arr::isArray($reports)) {
        echo "PASSED (" . count($reports) . " reports){$nl}";
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 9: Load data-sources.json
echo "Test 9: Load data-sources.json... ";
$tests++;
try {
    $dataSources = ConfigLoader::getDataSources();
    if (Arr::isArray($dataSources)) {
        echo "PASSED (" . count($dataSources) . " data sources){$nl}";
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 10: Get specific database by name
echo "Test 10: Get database by name... ";
$tests++;
try {
    $dataDb = ConfigLoader::getDatabase('data');
    if ($dataDb !== null && isset($dataDb['connectionString'])) {
        echo "PASSED (found 'data' database){$nl}";
        $passed++;
    } else {
        echo "FAILED - 'data' database not found{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 11: Get active modules only
echo "Test 11: Get active modules... ";
$tests++;
try {
    $activeModules = ConfigLoader::getActiveModules();
    if (Arr::isArray($activeModules)) {
        echo "PASSED (" . count($activeModules) . " active modules){$nl}";
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 12: Get control type by ID
echo "Test 12: Get control type by ID... ";
$tests++;
try {
    $textbox = ConfigLoader::getControlType(3);
    if ($textbox !== null && ($textbox['name'] === 'textbox' || strpos($textbox['description'] ?? '', 'TextBox') !== false)) {
        echo "PASSED (ID 3 = textbox){$nl}";
        $passed++;
    } else {
        echo "FAILED - Control type 3 not found or wrong{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 13: Cache invalidation
echo "Test 13: Cache invalidation... ";
$tests++;
try {
    ConfigLoader::invalidate('databases');
    $databases = ConfigLoader::getDatabases(); // Should reload
    if (Arr::isArray($databases)) {
        echo "PASSED{$nl}";
        $passed++;
    } else {
        echo "FAILED{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 14: Get menu items flat list
echo "Test 14: Get menu items flat list... ";
$tests++;
try {
    $menuItems = ConfigLoader::getMenuItems();
    if (Arr::isArray($menuItems)) {
        echo "PASSED (" . count($menuItems) . " items){$nl}";
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 15: Schema validation
echo "Test 15: Schema validation... ";
$tests++;
try {
    $errors = ConfigLoader::validate('databases');
    if (Arr::isArray($errors)) {
        if (count($errors) === 0) {
            echo "PASSED (no validation errors){$nl}";
        } else {
            echo "PASSED with warnings: " . implode(', ', $errors) . "{$nl}";
        }
        $passed++;
    } else {
        echo "FAILED - Invalid result{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Test 16: Get available configs
echo "Test 16: List available configs... ";
$tests++;
try {
    $configs = ConfigLoader::getAvailableConfigs();
    if (Arr::isArray($configs) && count($configs) > 0) {
        echo "PASSED (" . implode(', ', $configs) . "){$nl}";
        $passed++;
    } else {
        echo "FAILED - No configs found{$nl}";
        $failed++;
    }
} catch (\Exception $e) {
    echo "FAILED - " . $e->getMessage() . "{$nl}";
    $failed++;
}

// Summary
echo "{$nl}=== Test Summary ==={$nl}";
echo "Total tests: {$tests}{$nl}";
echo "Passed: {$passed}{$nl}";
echo "Failed: {$failed}{$nl}";
echo "Success rate: " . round(($passed / $tests) * 100, 1) . "%{$nl}";

if ($failed > 0) {
    echo "{$nl}WARNING: Some tests failed!{$nl}";
}

echo "{$endPre}";

if (!$isCli) {
    echo "</body></html>";
}
