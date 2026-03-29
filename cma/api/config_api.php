<?php
/**
 * Config API - JSON Configuration Management
 *
 * Provides API endpoints for reading and managing JSON configuration files.
 * Used by config maintenance forms.
 *
 * Endpoints:
 * - GET ?action=list&config=<name>         - List all items from config
 * - GET ?action=get&config=<name>&id=<id>  - Get single item by ID
 * - POST ?action=save                      - Save/update item (via config_post.php)
 * - POST ?action=delete                    - Delete item
 */

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use App\Library\Response;
use App\Library\Request;
use Cma\ConfigLoader;
use Cma\SecurityHelper;
use Cma\Services\Logger;

// Security check - require admin access
if (!SecurityHelper::isAdmin()) {
    Response::json(['error' => 'Unauthorized'], 403);
    exit;
}

$action = Request::query('action', 'list');
// Support both 'config' and 'type' parameters for backwards compatibility
$configName = Request::query('config', '') ?: Request::query('type', '');

// Validate config name
$allowedConfigs = ['databases', 'modules', 'menu', 'control-types', 'reports', 'data-sources', 'app'];
if (!in_array($configName, $allowedConfigs)) {
    Response::json(['error' => 'Invalid config name'], 400);
    exit;
}

switch ($action) {
    case 'list':
        handleList($configName);
        break;

    case 'get':
        $id = Request::query('id', '');
        handleGet($configName, $id);
        break;

    case 'schema':
        handleSchema($configName);
        break;

    default:
        Response::json(['error' => 'Unknown action'], 400);
}

/**
 * List all items from a config file
 */
function handleList(string $configName): void
{
    try {
        $data = ConfigLoader::load($configName);

        // Get the array key based on config type
        $arrayKey = getArrayKey($configName);

        if ($arrayKey === null) {
            // Single object config (like app.json)
            Response::json(['data' => [$data], 'total' => 1]);
        } else {
            $items = $data[$arrayKey] ?? [];
            Response::json(['data' => $items, 'total' => count($items)]);
        }
    } catch (\RuntimeException $e) {
        Logger::error('API config_api list error', [
            'message' => $e->getMessage(),
            'configName' => $configName,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        Response::json(['error' => $e->getMessage()], 500);
    }
}

/**
 * Get a single item by ID
 */
function handleGet(string $configName, $id): void
{
    try {
        $data = ConfigLoader::load($configName);
        $arrayKey = getArrayKey($configName);

        if ($arrayKey === null) {
            // Single object config
            Response::json(['data' => $data]);
            return;
        }

        $items = $data[$arrayKey] ?? [];

        // Find item by ID
        foreach ($items as $item) {
            if (strval($item['id'] ?? '') === strval($id)) {
                Response::json(['data' => $item]);
                return;
            }
        }

        Response::json(['error' => 'Item not found'], 404);
    } catch (\RuntimeException $e) {
        Logger::error('API config_api get error', [
            'message' => $e->getMessage(),
            'configName' => $configName,
            'id' => $id,
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        Response::json(['error' => $e->getMessage()], 500);
    }
}

/**
 * Get JSON schema for a config
 */
function handleSchema(string $configName): void
{
    $schemaPath = __DIR__ . '/../config/schema/' . $configName . '.schema.json';

    if (!file_exists($schemaPath)) {
        Response::json(['error' => 'Schema not found'], 404);
        return;
    }

    $schema = json_decode(file_get_contents($schemaPath), true);
    Response::json(['schema' => $schema]);
}

/**
 * Get the array key for a config type
 */
function getArrayKey(string $configName): ?string
{
    $mapping = [
        'databases' => 'databases',
        'modules' => 'modules',
        'menu' => 'menus',
        'control-types' => 'controlTypes',
        'reports' => 'reports',
        'data-sources' => 'dataSources',
        'app' => null,  // Single object, no array
    ];

    return $mapping[$configName] ?? null;
}
