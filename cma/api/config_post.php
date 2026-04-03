<?php
/**
 * Config Post Handler - Save JSON Configuration
 *
 * Handles POST requests from config maintenance forms.
 * Saves changes to JSON configuration files.
 *
 * Expected POST data:
 * - _config_file: Config file name (without .json)
 * - _config_array_key: Key for array in config (or null for single object)
 * - _action: 'save' or 'delete'
 * - id: Record ID (for updates/deletes)
 * - ...other fields from form
 */

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use App\Library\Arr;
use App\Library\Request;
use App\Library\Response;
use Cma\ConfigLoader;
use Cma\SecurityHelper;
use Cma\Services\Logger;

// Security check - require admin access
if (!SecurityHelper::isAdmin()) {
    Response::jsonResponse(['error' => 'Unauthorized', 'success' => false], 403);
    exit;
}

// Get config parameters
$configFile = Request::post('_config_file', '');
$configArrayKey = Request::post('_config_array_key', '');
$action = Request::post('_action', 'save');
$recordId = Request::post('id', '');

// Validate config file name
$allowedConfigs = ['databases', 'modules', 'menu', 'control-types', 'reports', 'data-sources', 'app'];
if (!in_array($configFile, $allowedConfigs)) {
    Response::jsonResponse(['error' => 'Invalid config file', 'success' => false], 400);
    exit;
}

try {
    // Load current config
    $config = ConfigLoader::load($configFile);

    switch ($action) {
        case 'save':
            $result = handleSave($config, $configFile, $configArrayKey, $recordId);
            break;

        case 'delete':
            $result = handleDelete($config, $configFile, $configArrayKey, $recordId);
            break;

        default:
            Response::jsonResponse(['error' => 'Unknown action', 'success' => false], 400);
            exit;
    }

    Response::jsonResponse($result);

} catch (\Exception $e) {
    Logger::error('API config_post error', [
        'message' => $e->getMessage(),
        'configFile' => $configFile ?? null,
        'action' => $action ?? null,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    Response::jsonResponse([
        'error' => $e->getMessage(),
        'success' => false
    ], 500);
}

/**
 * Handle save/update operation
 */
function handleSave(array $config, string $configFile, string $configArrayKey, $recordId): array
{
    // Build record data from POST
    $record = buildRecordFromPost();

    if (empty($configArrayKey) || $configArrayKey === 'null') {
        // Single object config (like app.json)
        $config = mergeNestedData($config, $record);
    } else {
        // Array-based config
        $items = $config[$configArrayKey] ?? [];

        if ($recordId === '' || $recordId === null || $recordId === 'new') {
            // New record - generate ID
            $maxId = 0;
            foreach ($items as $item) {
                $maxId = max($maxId, (int)($item['id'] ?? 0));
            }
            $record['id'] = $maxId + 1;
            $items[] = $record;
        } else {
            // Update existing record
            $found = false;
            foreach ($items as $idx => $item) {
                if (strval($item['id'] ?? '') === strval($recordId)) {
                    // Preserve ID, merge other fields
                    $record['id'] = (int)$recordId;
                    $items[$idx] = $record;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return ['error' => 'Record not found', 'success' => false];
            }
        }

        $config[$configArrayKey] = $items;
    }

    // Save config
    if (ConfigLoader::save($configFile, $config)) {
        return [
            'success' => true,
            'message' => 'Configuration saved',
            'id' => $record['id'] ?? $recordId
        ];
    } else {
        return ['error' => 'Failed to save configuration', 'success' => false];
    }
}

/**
 * Handle delete operation
 */
function handleDelete(array $config, string $configFile, string $configArrayKey, $recordId): array
{
    if (empty($configArrayKey) || $configArrayKey === 'null') {
        return ['error' => 'Cannot delete single object config', 'success' => false];
    }

    $items = $config[$configArrayKey] ?? [];
    $newItems = [];
    $found = false;

    foreach ($items as $item) {
        if (strval($item['id'] ?? '') === strval($recordId)) {
            $found = true;
            continue; // Skip this item (delete it)
        }
        $newItems[] = $item;
    }

    if (!$found) {
        return ['error' => 'Record not found', 'success' => false];
    }

    $config[$configArrayKey] = $newItems;

    if (ConfigLoader::save($configFile, $config)) {
        return ['success' => true, 'message' => 'Record deleted'];
    } else {
        return ['error' => 'Failed to save configuration', 'success' => false];
    }
}

/**
 * Build a record from POST data
 * Filters out internal fields (starting with _)
 */
function buildRecordFromPost(): array
{
    $record = [];
    $skipFields = ['_config_file', '_config_array_key', '_action', '_method'];

    foreach (Request::postAll() as $key => $value) {
        // Skip internal fields
        if (str_starts_with($key, '_') || in_array($key, $skipFields)) {
            continue;
        }

        // Handle nested fields (e.g., "company.logo" -> $record['company']['logo'])
        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $ref = &$record;
            foreach ($parts as $i => $part) {
                if ($i === count($parts) - 1) {
                    $ref[$part] = convertValue($value);
                } else {
                    if (!isset($ref[$part])) {
                        $ref[$part] = [];
                    }
                    $ref = &$ref[$part];
                }
            }
        } else {
            $record[$key] = convertValue($value);
        }
    }

    return $record;
}

/**
 * Convert POST value to appropriate type
 */
function convertValue($value)
{
    // Boolean conversion
    if ($value === 'true' || $value === '1' || $value === 'on') {
        return true;
    }
    if ($value === 'false' || $value === '0' || $value === 'off') {
        return false;
    }

    // Numeric conversion
    if (is_numeric($value)) {
        if (strpos($value, '.') !== false) {
            return (float)$value;
        }
        return (int)$value;
    }

    // Null handling
    if ($value === '' || $value === null) {
        return null;
    }

    return $value;
}

/**
 * Merge nested data into config (for single object configs)
 */
function mergeNestedData(array $config, array $record): array
{
    foreach ($record as $key => $value) {
        if (Arr::isArray($value) && isset($config[$key]) && Arr::isArray($config[$key])) {
            $config[$key] = mergeNestedData($config[$key], $value);
        } else {
            $config[$key] = $value;
        }
    }
    return $config;
}
