<?php

namespace Cma\Services;

use App\Library\Arr;
use App\Library\Database;
use App\Library\Request;
use Cma\ConfigLoader;
use Cma\JsonFormLoader;

/**
 * ConfigFormService - Handles forms backed by JSON configuration files
 *
 * This service provides CRUD operations for forms that use `database: "json"`
 * and store their data in ConfigLoader-managed JSON files.
 *
 * Form definition properties used:
 * - configFile: Name of config file (e.g., "databases", "modules")
 * - configArrayKey: Key in config for array of items (e.g., "databases")
 * - singleRecord: If true, form edits the entire config object (e.g., app.json)
 */
class ConfigFormService
{
    /**
     * Check if a form uses JSON config as its data source
     */
    public static function isJsonConfigForm(array $formDef): bool
    {
        $jsonData = $formDef['_json'] ?? [];
        return ($jsonData['database'] ?? '') === 'json';
    }

    /**
     * Check if form uses directory mode (individual JSON files per record)
     */
    private static function isDirectoryMode(array $formDef): bool
    {
        return !empty($formDef['configDir']);
    }

    /**
     * Resolve directory path from configDir property
     * Leading '/' means relative to site root, otherwise relative to /data/
     */
    private static function resolveDirectoryPath(string $configDir): string
    {
        if (str_starts_with($configDir, '/')) {
            $siteRoot = dirname(__DIR__, 3); // /cma/classes/Services -> /site
            return $siteRoot . $configDir;
        }
        return dirname(__DIR__, 2) . '/config/' . $configDir;
    }

    /**
     * Get list data for a JSON config form
     *
     * @param string $formName Form name
     * @param array $filters Optional filters to apply (field => value or field => ['from' => x, 'to' => y])
     * @return array Result with 'success', 'data', 'total' keys
     */
    public static function getListData(string $formName, array $filters = []): array
    {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            return self::error("Formulier '$formName' niet gevonden");
        }

        // Directory mode: each record is an individual JSON file
        if (self::isDirectoryMode($formDef)) {
            return self::getListDataFromDirectory($formName, $formDef, $filters);
        }

        $configFile = $formDef['configFile'] ?? '';
        $configArrayKey = $formDef['configArrayKey'] ?? '';

        if (empty($configFile)) {
            return self::error("Form missing configFile property");
        }

        try {
            $config = ConfigLoader::load($configFile);

            if (empty($configArrayKey) || ($formDef['singleRecord'] ?? false)) {
                // Single record form (like app.json)
                return [
                    'success' => true,
                    'data' => [$config],
                    'total' => 1
                ];
            }

            // Handle nested array paths like "menus[].items"
            if (preg_match('/^(\w+)\[\]\.(\w+)$/', $configArrayKey, $matches)) {
                $parentKey = $matches[1]; // e.g., "menus"
                $nestedKey = $matches[2]; // e.g., "items"
                $parentItems = $config[$parentKey] ?? [];

                // Collect all nested items from all parents
                $items = [];
                foreach ($parentItems as $parentItem) {
                    $parentId = $parentItem['id'] ?? null;
                    $parentName = $parentItem['name'] ?? '';
                    $nestedItems = $parentItem[$nestedKey] ?? [];
                    foreach ($nestedItems as $item) {
                        // Add parent reference for context
                        $item['menuId'] = $parentId;
                        $item['_parentName'] = $parentName;
                        $items[] = $item;
                    }
                }

                // Add computed fields
                $items = self::addComputedFields($items, $formName);

                // Apply filters
                if (!empty($filters)) {
                    $items = self::applyFilters($items, $filters);
                }

                return [
                    'success' => true,
                    'data' => $items,
                    'total' => count($items)
                ];
            }

            $items = $config[$configArrayKey] ?? [];

            // Add computed fields (like item counts for menus)
            $items = self::addComputedFields($items, $formName);

            // Apply filters
            if (!empty($filters)) {
                $items = self::applyFilters($items, $filters);
            }

            return [
                'success' => true,
                'data' => $items,
                'total' => count($items)
            ];
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get a single record from JSON config
     *
     * @param string $formName Form name
     * @param mixed $id Record ID
     * @return array Result with 'success' and 'data' keys
     */
    public static function getRecord(string $formName, $id): array
    {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            return self::error("Formulier '$formName' niet gevonden");
        }

        // Directory mode: load individual file by name
        if (self::isDirectoryMode($formDef)) {
            return self::getRecordFromDirectory($formDef, $id);
        }

        $configFile = $formDef['configFile'] ?? '';
        $configArrayKey = $formDef['configArrayKey'] ?? '';

        if (empty($configFile)) {
            return self::error("Form missing configFile property");
        }

        try {
            $config = ConfigLoader::load($configFile);

            if (empty($configArrayKey) || ($formDef['singleRecord'] ?? false)) {
                // Single record form - return flattened config
                return [
                    'success' => true,
                    'data' => self::flattenForForm($config)
                ];
            }

            // Handle nested array paths like "menus[].items"
            if (preg_match('/^(\w+)\[\]\.(\w+)$/', $configArrayKey, $matches)) {
                $parentKey = $matches[1]; // e.g., "menus"
                $nestedKey = $matches[2]; // e.g., "items"
                $parentItems = $config[$parentKey] ?? [];

                // Search through all parent items' nested arrays
                foreach ($parentItems as $parentItem) {
                    $parentId = $parentItem['id'] ?? null;
                    $parentName = $parentItem['name'] ?? '';
                    $nestedItems = $parentItem[$nestedKey] ?? [];
                    foreach ($nestedItems as $item) {
                        if (strval($item['id'] ?? '') === strval($id)) {
                            // Add parent reference for context (same as getList does)
                            $item['menuId'] = $parentId;
                            $item['_parentName'] = $parentName;
                            return [
                                'success' => true,
                                'data' => self::prepareRecordForDisplay($item, $formDef)
                            ];
                        }
                    }
                }

                return self::error("Record met ID '$id' niet gevonden");
            }

            $items = $config[$configArrayKey] ?? [];

            // Find by ID
            foreach ($items as $item) {
                if (strval($item['id'] ?? '') === strval($id)) {
                    return [
                        'success' => true,
                        'data' => self::prepareRecordForDisplay($item, $formDef)
                    ];
                }
            }

            return self::error("Record met ID '$id' niet gevonden");
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get a single record from JSON config by filter criteria
     *
     * @param string $formName Form name
     * @param array $filter Associative array of field => value pairs to match
     * @return array Result with 'success' and 'data' keys
     */
    public static function getRecordByFilter(string $formName, array $filter): array
    {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            return self::error("Formulier '$formName' niet gevonden");
        }

        $configFile = $formDef['configFile'] ?? '';
        $configArrayKey = $formDef['configArrayKey'] ?? '';

        if (empty($configFile)) {
            return self::error("Form missing configFile property");
        }

        try {
            $config = ConfigLoader::load($configFile);

            if (empty($configArrayKey) || ($formDef['singleRecord'] ?? false)) {
                // Single record form - check if filter matches config
                $matches = true;
                foreach ($filter as $field => $value) {
                    if (!isset($config[$field]) || strval($config[$field]) !== strval($value)) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return [
                        'success' => true,
                        'data' => self::flattenForForm($config)
                    ];
                }
                return self::error("Record met opgegeven filter niet gevonden");
            }

            $items = $config[$configArrayKey] ?? [];

            // Find by filter criteria - all criteria must match
            foreach ($items as $item) {
                $matches = true;
                foreach ($filter as $field => $value) {
                    // Support case-insensitive matching for strings
                    $itemValue = $item[$field] ?? null;
                    if ($itemValue === null || strcasecmp(strval($itemValue), strval($value)) !== 0) {
                        $matches = false;
                        break;
                    }
                }
                if ($matches) {
                    return [
                        'success' => true,
                        'data' => self::prepareRecordForDisplay($item, $formDef)
                    ];
                }
            }

            $filterDesc = json_encode($filter, JSON_UNESCAPED_UNICODE);
            return self::error("Record met filter $filterDesc niet gevonden");
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Save a record to JSON config
     *
     * @param string $formName Form name
     * @param array $data Record data
     * @return array Result with 'success' and 'id' keys
     */
    public static function saveRecord(string $formName, array $data): array
    {
        \Cma\Services\Logger::debug("ConfigFormService::saveRecord", [
            'formName' => $formName,
            'data' => $data,
        ]);

        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            return self::error("Formulier '$formName' niet gevonden");
        }

        // Directory mode: merge-save individual file
        if (self::isDirectoryMode($formDef)) {
            return self::saveRecordToDirectory($formDef, $data);
        }

        $configFile = $formDef['configFile'] ?? '';
        $configArrayKey = $formDef['configArrayKey'] ?? '';
        $idField = $formDef['idField'] ?? 'id';

        if (empty($configFile)) {
            return self::error("Form missing configFile property");
        }

        // Prevent recursive contentblock references in contentblocks form
        // Contentblocks are stored as <!--BLOCK{...}--> markers
        if ($formName === 'contentblocks' && !empty($data['html'])) {
            if (preg_match('/<!--BLOCK\{/i', $data['html'])) {
                return self::error("HTML template mag geen contentblocks bevatten (zou recursie veroorzaken)");
            }
        }

        try {
            $config = ConfigLoader::load($configFile);

            if (empty($configArrayKey) || ($formDef['singleRecord'] ?? false)) {
                // Single record form - merge data into config
                $config = self::unflattenFromForm($config, $data);
                if (ConfigLoader::save($configFile, $config)) {
                    return ['success' => true, 'id' => 'single'];
                }
                return self::error("Opslaan mislukt");
            }

            // Handle nested array paths like "menus[].items"
            if (preg_match('/^(\w+)\[\]\.(\w+)$/', $configArrayKey, $matches)) {
                $parentKey = $matches[1]; // e.g., "menus"
                $nestedKey = $matches[2]; // e.g., "items"
                $parentItems = $config[$parentKey] ?? [];

                $id = $data[$idField] ?? '';
                $isNew = empty($id) || $id === 'new';

                // For readOnly ID fields, check if record actually exists
                // If ID is provided but doesn't exist, treat as new record
                if (!$isNew) {
                    $idFieldDef = null;
                    foreach ($formDef['fields'] ?? [] as $field) {
                        if (($field['name'] ?? '') === $idField) {
                            $idFieldDef = $field;
                            break;
                        }
                    }
                    $isIdReadOnly = ($idFieldDef['readOnly'] ?? false) === true;

                    if ($isIdReadOnly) {
                        $recordExists = false;
                        foreach ($parentItems as $parentItem) {
                            foreach ($parentItem[$nestedKey] ?? [] as $item) {
                                if (strval($item['id'] ?? '') === strval($id)) {
                                    $recordExists = true;
                                    break 2;
                                }
                            }
                        }
                        if (!$recordExists) {
                            // ID field is readOnly and record doesn't exist - treat as new
                            $isNew = true;
                        }
                    }
                }

                // For nested items, we need to know which parent they belong to
                // This is typically stored in a field like "menuId"
                $parentIdField = $parentKey . 'Id'; // e.g., "menusId" or check for "menuId"
                $parentId = $data[$parentIdField] ?? $data['menuId'] ?? null;

                if ($isNew) {
                    // For new items, we need the parent ID
                    if ($parentId === null) {
                        return self::error("Parent ID is verplicht voor dit type formulier");
                    }

                    // Generate new ID across ALL nested items
                    $maxId = 0;
                    foreach ($parentItems as $parentItem) {
                        foreach ($parentItem[$nestedKey] ?? [] as $item) {
                            $maxId = max($maxId, (int)($item['id'] ?? 0));
                        }
                    }
                    $data['id'] = $maxId + 1;

                    // Find the parent and add the item
                    $found = false;
                    foreach ($parentItems as $parentIdx => $parentItem) {
                        if (strval($parentItem['id'] ?? '') === strval($parentId)) {
                            $cleanedData = self::cleanRecordData($data, $formDef);
                            // Remove the parent reference field from the stored data
                            unset($cleanedData[$parentIdField]);
                            unset($cleanedData['menuId']);

                            $config[$parentKey][$parentIdx][$nestedKey][] = $cleanedData;
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        return self::error("Parent met ID '$parentId' niet gevonden");
                    }
                } else {
                    // Update existing - search through all parents
                    $found = false;
                    \Cma\Services\Logger::debug("ConfigFormService: searching for item", [
                        'id' => $id,
                        'parentKey' => $parentKey,
                        'nestedKey' => $nestedKey,
                        'parentCount' => count($parentItems),
                    ]);
                    foreach ($parentItems as $parentIdx => $parentItem) {
                        $nestedItems = $parentItem[$nestedKey] ?? [];
                        foreach ($nestedItems as $itemIdx => $item) {
                            if (strval($item['id'] ?? '') === strval($id)) {
                                $data['id'] = $id; // Preserve original ID
                                $cleanedData = self::cleanRecordData($data, $formDef);
                                // Remove the parent reference field from the stored data
                                unset($cleanedData[$parentIdField]);
                                unset($cleanedData['menuId']);

                                \Cma\Services\Logger::debug("ConfigFormService: found item, merging", [
                                    'parentIdx' => $parentIdx,
                                    'itemIdx' => $itemIdx,
                                    'originalItem' => $item,
                                    'cleanedData' => $cleanedData,
                                ]);

                                // Merge with existing item (supports partial updates like switch toggles)
                                $config[$parentKey][$parentIdx][$nestedKey][$itemIdx] = array_merge($item, $cleanedData);
                                $found = true;
                                break 2;
                            }
                        }
                    }

                    if (!$found) {
                        \Cma\Services\Logger::debug("ConfigFormService: item not found", ['id' => $id]);
                        return self::error("Record niet gevonden");
                    }
                }

                $saveResult = ConfigLoader::save($configFile, $config);
                \Cma\Services\Logger::debug("ConfigFormService: save result", [
                    'configFile' => $configFile,
                    'saveResult' => $saveResult,
                ]);
                if ($saveResult) {
                    return ['success' => true, 'id' => $data['id']];
                }

                return self::error("Opslaan mislukt");
            }

            // Simple path - direct array access
            $items = $config[$configArrayKey] ?? [];
            $id = $data[$idField] ?? '';
            $isNew = empty($id) || $id === 'new';

            // For readOnly ID fields, check if record actually exists
            // If ID is provided but doesn't exist, treat as new record
            if (!$isNew) {
                $idFieldDef = null;
                foreach ($formDef['fields'] ?? [] as $field) {
                    if (($field['name'] ?? '') === $idField) {
                        $idFieldDef = $field;
                        break;
                    }
                }
                $isIdReadOnly = ($idFieldDef['readOnly'] ?? false) === true;

                if ($isIdReadOnly) {
                    $recordExists = false;
                    foreach ($items as $item) {
                        if (strval($item['id'] ?? '') === strval($id)) {
                            $recordExists = true;
                            break;
                        }
                    }
                    if (!$recordExists) {
                        // ID field is readOnly and record doesn't exist - treat as new
                        $isNew = true;
                    }
                }
            }

            if ($isNew) {
                // Generate new ID - check if IDs are numeric or string-based
                $firstItem = $items[0] ?? null;
                $firstId = $firstItem['id'] ?? null;
                $isNumericId = $firstId === null || is_numeric($firstId);

                if ($isNumericId) {
                    // Numeric IDs - find max and increment
                    $maxId = 0;
                    foreach ($items as $item) {
                        $maxId = max($maxId, (int)($item['id'] ?? 0));
                    }
                    $data['id'] = $maxId + 1;
                } else {
                    // String IDs - require ID to be provided in data
                    if (empty($data['id']) || $data['id'] === 'new') {
                        return self::error("ID is verplicht voor dit formulier");
                    }
                    // Verify ID doesn't already exist
                    foreach ($items as $item) {
                        if (strval($item['id'] ?? '') === strval($data['id'])) {
                            return self::error("ID '{$data['id']}' bestaat al");
                        }
                    }
                }
                $items[] = self::cleanRecordData($data, $formDef);
            } else {
                // Update existing - preserve ID as-is (string or int)
                $found = false;
                foreach ($items as $idx => $item) {
                    if (strval($item['id'] ?? '') === strval($id)) {
                        $data['id'] = $id; // Preserve original ID type
                        // Merge with existing item (supports partial updates like switch toggles)
                        $items[$idx] = array_merge($item, self::cleanRecordData($data, $formDef));
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    return self::error("Record niet gevonden");
                }
            }

            $config[$configArrayKey] = $items;

            if (ConfigLoader::save($configFile, $config)) {
                return ['success' => true, 'id' => $data['id']];
            }

            return self::error("Opslaan mislukt");
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Get subform list data for a JSON config form
     *
     * This handles nested data like menus[].items where items are inside each menu.
     *
     * @param string $parentFormName Parent form name
     * @param mixed $parentId Parent record ID
     * @param int $subformIndex Index of subform in parent's subforms array
     * @return array Result with 'success', 'data', 'total' keys
     */
    public static function getSubformListData(string $parentFormName, $parentId, int $subformIndex = 0): array
    {
        // Load parent form definition
        $parentFormDef = \Cma\JsonFormLoader::load($parentFormName);
        if (!$parentFormDef) {
            return self::error("Formulier '$parentFormName' niet gevonden");
        }

        // Check if parent form is a JSON config form
        if (!self::isJsonConfigForm($parentFormDef)) {
            return self::error("Formulier is geen JSON configuratie formulier");
        }

        // Access JSON data from the _json property (legacy format stores original JSON there)
        $jsonData = $parentFormDef['_json'] ?? $parentFormDef;

        // Get subform definition
        $subforms = $jsonData['subforms'] ?? [];
        if (!isset($subforms[$subformIndex])) {
            return self::error("Subformulier index $subformIndex niet gevonden");
        }

        $subformDef = $subforms[$subformIndex];
        $subformName = $subformDef['formName'] ?? $subformDef['name'] ?? '';

        // Load subform form definition
        $subformFormDef = \Cma\JsonFormLoader::load($subformName);
        if (!$subformFormDef) {
            return self::error("Subformulier '$subformName' niet gevonden");
        }

        $configFile = $jsonData['configFile'] ?? '';
        $configArrayKey = $jsonData['configArrayKey'] ?? '';
        $linkField = $subformDef['linkField'] ?? '';
        $parentIdField = $subformDef['parentIdField'] ?? 'id';

        if (empty($configFile)) {
            return self::error("Form missing configFile property");
        }

        try {
            $config = ConfigLoader::load($configFile);
            $parentItems = $config[$configArrayKey] ?? [];

            // Find the parent record
            $parentRecord = null;
            foreach ($parentItems as $item) {
                if (strval($item[$parentIdField] ?? '') === strval($parentId)) {
                    $parentRecord = $item;
                    break;
                }
            }

            if ($parentRecord === null) {
                return self::error("Parent record met ID '$parentId' niet gevonden");
            }

            // Get nested items array name from subform's configArrayKey
            // e.g., "menus[].items" -> extract "items"
            $subformConfigKey = $subformFormDef['configArrayKey'] ?? '';
            $nestedKey = 'items'; // Default

            if (preg_match('/\[\]\.(\w+)$/', $subformConfigKey, $matches)) {
                $nestedKey = $matches[1];
            }

            $items = $parentRecord[$nestedKey] ?? [];

            // Add parent ID reference to each item for context
            foreach ($items as &$item) {
                if (!empty($linkField) && !isset($item[$linkField])) {
                    $item[$linkField] = $parentId;
                }
            }
            unset($item);

            // Get subform's JSON data to check allowAdd
            $subformJsonData = $subformFormDef['_json'] ?? $subformFormDef;
            $hasFullAccess = \Cma\SecurityHelper::isAdmin();

            // Build columns from subform fields
            $columns = [];
            $columnInfo = [];
            $subformFields = $subformJsonData['fields'] ?? [];
            $idField = $subformJsonData['idField'] ?? 'id';
            $skipTypes = ['groupseparator', 'label', 'hidden', 'password', 'image', 'file', 'checklist', 'sortlist', 'memo', 'ignorefield'];
            $colCount = 0;
            foreach ($subformFields as $field) {
                $fieldName = $field['name'] ?? '';
                $fieldType = $field['type'] ?? 'textbox';
                if (empty($fieldName)) continue;
                if (in_array($fieldType, $skipTypes)) continue;
                if (strtolower($fieldName) === strtolower($idField)) continue;
                if (strtolower($fieldName) === strtolower($linkField)) continue;
                $columns[] = $fieldName;
                $columnInfo[$fieldName] = [
                    'type' => $fieldType,
                    'caption' => $field['caption'] ?? $fieldName,
                ];
                $colCount++;
            }

            // Generate HTML table for consistent rendering
            $tableId = 'subformTable_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $subformName);
            $html = '<lib-table><table class="listtable subform-table filtering sorttable" id="' . htmlspecialchars($tableId) . '" data-subform-id="' . htmlspecialchars($subformName) . '" data-json-form="' . htmlspecialchars($subformName) . '" data-name="' . htmlspecialchars($subformName) . '" cellspacing="0" cellpadding="0">';

            // Header row
            $html .= '<thead><tr class="listheader">';
            foreach ($columns as $col) {
                $info = $columnInfo[$col] ?? [];
                $caption = $info['caption'] ?? $col;
                $type = $info['type'] ?? 'textbox';
                $dataType = ($type === 'checkbox') ? 'boolean' : 'text';
                $html .= '<th data-field="' . htmlspecialchars($col) . '" data-type="' . $dataType . '">' . htmlspecialchars($caption) . '</th>';
            }
            $html .= '</tr></thead>';

            // Data rows
            $html .= '<tbody>';
            foreach ($items as $item) {
                $recordId = $item['id'] ?? '';
                $html .= '<tr class="listrow" data-id="' . htmlspecialchars($recordId) . '">';

                // Menu trigger goes inside the first data cell
                $menuTrigger = '<span class="row-menu-trigger" data-id="' . htmlspecialchars($recordId) . '">&#8942;</span>';
                $isFirstCol = true;

                foreach ($columns as $col) {
                    $value = $item[$col] ?? '';
                    $info = $columnInfo[$col] ?? [];
                    $type = $info['type'] ?? 'textbox';
                    $prefix = $isFirstCol ? $menuTrigger : '';
                    $isFirstCol = false;

                    // Format boolean values
                    if ($type === 'checkbox') {
                        $boolVal = $value === true || $value === 'true' || $value === 1 || $value === '1';
                        // Render as lib-switch for interactive toggle
                        $switchHtml = '<lib-switch data-field="' . htmlspecialchars($col) . '"' .
                            ($boolVal ? ' checked' : '') . '></lib-switch>';
                        $html .= '<td data-field="' . htmlspecialchars($col) . '" data-type="boolean" data-value="' . ($boolVal ? '1' : '0') . '">' . $prefix . $switchHtml . '</td>';
                    } else {
                        $displayValue = is_string($value) ? $value : strval($value);
                        if (strlen($displayValue) > 50) {
                            $displayValue = substr($displayValue, 0, 47) . '...';
                        }
                        $html .= '<td data-field="' . htmlspecialchars($col) . '">' . $prefix . htmlspecialchars($displayValue) . '</td>';
                    }
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table></lib-table>';

            if (count($items) === 0) {
                $canAdd = ($subformJsonData['allowAdd'] ?? true) && $hasFullAccess;
                $message = $canAdd
                    ? 'Geen gegevens, klik op \'Toevoegen\' om een nieuw record aan te maken'
                    : 'Geen gegevens';
                $html = '<div class="no-data">' . $message . '</div>';
            }

            return [
                'success' => true,
                'html' => $html, // Return HTML for consistent rendering
                'items' => $items,
                'data' => $items, // Also include as 'data' for compatibility
                'columns' => $columns,
                'total' => count($items),
                'count' => count($items),
                'formName' => $subformName,
                'subformName' => $subformName, // Alias for frontend
                'subformId' => $subformName, // Use name as ID for JSON forms
                'parentFormName' => $parentFormName,
                'parentId' => $parentId,
                'parentField' => $linkField, // linkField maps to parentField for frontend
                'canAdd' => ($subformJsonData['allowAdd'] ?? true) && $hasFullAccess,
                'canEdit' => ($subformJsonData['allowEdit'] ?? true) && $hasFullAccess,
                'canDelete' => ($subformJsonData['allowDelete'] ?? true) && $hasFullAccess,
            ];
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Delete a record from JSON config
     *
     * @param string $formName Form name
     * @param mixed $id Record ID
     * @return array Result with 'success' key
     */
    public static function deleteRecord(string $formName, $id): array
    {
        $formDef = JsonFormLoader::loadRaw($formName);
        if ($formDef === null) {
            return self::error("Formulier '$formName' niet gevonden");
        }

        // Directory mode: deletion not allowed for form definitions
        if (self::isDirectoryMode($formDef)) {
            return self::error("Formulierdefinities mogen niet worden verwijderd");
        }

        $configFile = $formDef['configFile'] ?? '';
        $configArrayKey = $formDef['configArrayKey'] ?? '';

        if (empty($configFile) || empty($configArrayKey)) {
            return self::error("Cannot delete from this form type");
        }

        try {
            $config = ConfigLoader::load($configFile);

            // Handle nested array paths like "menus[].items"
            if (preg_match('/^(\w+)\[\]\.(\w+)$/', $configArrayKey, $matches)) {
                $parentKey = $matches[1]; // e.g., "menus"
                $nestedKey = $matches[2]; // e.g., "items"
                $parentItems = $config[$parentKey] ?? [];
                $found = false;

                // Search through all parent items' nested arrays
                foreach ($parentItems as $parentIdx => $parentItem) {
                    $nestedItems = $parentItem[$nestedKey] ?? [];
                    $newNestedItems = [];

                    foreach ($nestedItems as $item) {
                        if (strval($item['id'] ?? '') === strval($id)) {
                            $found = true;
                            continue; // Skip this item (delete it)
                        }
                        $newNestedItems[] = $item;
                    }

                    if ($found) {
                        // Update the nested items in the parent
                        $config[$parentKey][$parentIdx][$nestedKey] = $newNestedItems;
                        break;
                    }
                }

                if (!$found) {
                    return self::error("Record niet gevonden");
                }

                if (ConfigLoader::save($configFile, $config)) {
                    return ['success' => true];
                }

                return self::error("Verwijderen mislukt");
            }

            // Simple path - direct array access
            $items = $config[$configArrayKey] ?? [];
            $newItems = [];
            $found = false;

            foreach ($items as $item) {
                if (strval($item['id'] ?? '') === strval($id)) {
                    $found = true;
                    continue;
                }
                $newItems[] = $item;
            }

            if (!$found) {
                return self::error("Record niet gevonden");
            }

            $config[$configArrayKey] = $newItems;

            if (ConfigLoader::save($configFile, $config)) {
                return ['success' => true];
            }

            return self::error("Verwijderen mislukt");
        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    // ==================== Directory Mode Methods ====================

    /**
     * Get list data from a directory of individual JSON files
     */
    private static function getListDataFromDirectory(string $formName, array $formDef, array $filters = []): array
    {
        $configDir = $formDef['configDir'] ?? '';
        $dirPath = self::resolveDirectoryPath($configDir);

        if (!is_dir($dirPath)) {
            return self::error("Directory '$configDir' niet gevonden");
        }

        $fields = $formDef['fields'] ?? [];
        $idField = $formDef['idField'] ?? 'name';

        // Get list of field names to extract (skip group separators etc.)
        $fieldNames = [];
        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            if (!empty($name) && !str_starts_with($name, '_')) {
                $fieldNames[] = $name;
            }
        }

        // Scan directory for JSON files
        $files = glob($dirPath . '/*.json');

        // Build set of known subform names and parent→subform mapping
        $subformNames = [];
        $parentSubforms = []; // parentName => [['form' => subName, 'title' => subTitle, 'order' => n], ...]
        foreach ($files as $file) {
            $parentName = basename($file, '.json');
            $parentData = JsonFormLoader::loadRaw($parentName);
            if ($parentData === null) continue;
            foreach ($parentData['subforms'] ?? [] as $sub) {
                $subName = $sub['form'] ?? $sub['name'] ?? '';
                if ($subName !== '') {
                    $subformNames[$subName] = true;
                    $parentSubforms[$parentName][] = [
                        'form' => $subName,
                        'title' => $sub['title'] ?? $subName,
                        'order' => $sub['order'] ?? 99,
                    ];
                }
            }
            // Sort subforms by order
            if (isset($parentSubforms[$parentName])) {
                usort($parentSubforms[$parentName], fn($a, $b) => $a['order'] <=> $b['order']);
            }
        }

        $items = [];

        foreach ($files as $file) {
            $name = basename($file, '.json');

            // Skip internal CMA forms (users, groups, etc.)
            if (in_array($name, ['users', 'groups', '_menus', '_menu_items', 'cmamonitoring', 'contentblocks', 'marketingurl', 'formdefinitions'], true)) {
                continue;
            }

            // Skip forms that are referenced as subforms of other forms
            if (isset($subformNames[$name])) {
                continue;
            }

            // Load form data via JsonFormLoader cache
            $data = JsonFormLoader::loadRaw($name);
            if ($data === null) {
                continue;
            }

            // Extract only defined fields
            $item = [$idField => $name];
            foreach ($fieldNames as $fn) {
                if ($fn === $idField && $idField === 'name') {
                    $item[$fn] = $name;
                } elseif (isset($data[$fn])) {
                    $value = $data[$fn];
                    // Convert arrays/objects to string for list display
                    if (Arr::isArray($value)) {
                        $item[$fn] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } else {
                        $item[$fn] = $value;
                    }
                } else {
                    $item[$fn] = null;
                }
            }

            // Attach subform info for tree view rendering
            if (isset($parentSubforms[$name])) {
                $item['_subforms'] = $parentSubforms[$name];
            }

            $items[] = $item;
        }

        // Apply filters
        if (!empty($filters)) {
            $items = self::applyFilters($items, $filters);
        }

        // Sort by orderField if specified
        $orderField = $formDef['orderField'] ?? $idField;
        usort($items, function($a, $b) use ($orderField) {
            return strnatcasecmp(
                (string)($a[$orderField] ?? ''),
                (string)($b[$orderField] ?? '')
            );
        });

        return [
            'success' => true,
            'data' => $items,
            'total' => count($items)
        ];
    }

    /**
     * Get a single record from a directory of individual JSON files
     */
    private static function getRecordFromDirectory(array $formDef, $id): array
    {
        if (empty($id)) {
            return self::error("Record ID is verplicht");
        }

        // Load via JsonFormLoader (uses 3-tier cache)
        $data = JsonFormLoader::loadRaw((string)$id);
        if ($data === null) {
            return self::error("Formulierdefinitie '$id' niet gevonden");
        }

        // Add name from filename
        $data['name'] = (string)$id;

        // Prepare for display (convert JSON fields to strings)
        $data = self::prepareRecordForDisplay($data, $formDef);

        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Save a record to a directory of individual JSON files (merge-save)
     */
    private static function saveRecordToDirectory(array $formDef, array $data): array
    {
        $idField = $formDef['idField'] ?? 'name';
        $id = $data[$idField] ?? '';

        if (empty($id)) {
            return self::error("ID is verplicht");
        }

        // Load existing file content
        $existing = JsonFormLoader::loadRaw((string)$id);
        if ($existing === null) {
            return self::error("Formulierdefinitie '$id' niet gevonden");
        }

        // Clean form data - only keep fields defined in form
        $cleanedData = self::cleanRecordData($data, $formDef);

        // Remove synthetic 'id' added by cleanRecordData (not part of file content)
        if ($idField !== 'id') {
            unset($cleanedData['id']);
        }

        // Merge form data on top of existing (preserves $schema, version, etc.)
        foreach ($cleanedData as $key => $value) {
            $existing[$key] = $value;
        }

        // Save via JsonFormLoader
        if (JsonFormLoader::save((string)$id, $existing)) {
            return ['success' => true, 'id' => $id];
        }

        return self::error("Opslaan mislukt");
    }

    /**
     * Add computed fields to list items
     */
    private static function addComputedFields(array $items, string $formName): array
    {
        // Add item count for menu lists
        if ($formName === 'config_menu') {
            foreach ($items as &$item) {
                $item['_itemCount'] = count($item['items'] ?? []);
            }
        }

        return $items;
    }

    /**
     * Flatten nested object for form display
     * e.g., company.logo becomes a top-level field
     */
    private static function flattenForForm(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? "{$prefix}.{$key}" : $key;
            if (Arr::isArray($value) && !isset($value[0])) {
                // Nested object - recurse
                $result = array_merge($result, self::flattenForForm($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Unflatten form data back to nested object
     */
    private static function unflattenFromForm(array $original, array $formData): array
    {
        foreach ($formData as $key => $value) {
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $ref = &$original;
                foreach ($parts as $i => $part) {
                    if ($i === count($parts) - 1) {
                        $ref[$part] = self::convertValue($value);
                    } else {
                        if (!isset($ref[$part])) {
                            $ref[$part] = [];
                        }
                        $ref = &$ref[$part];
                    }
                }
            } else {
                $original[$key] = self::convertValue($value);
            }
        }
        return $original;
    }

    /**
     * Prepare record data for display (convert JSON objects to strings)
     */
    private static function prepareRecordForDisplay(array $data, array $formDef): array
    {
        $fields = $formDef['fields'] ?? [];

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $dataType = $field['dataType'] ?? '';

            if ($dataType === 'json' && isset($data[$name])) {
                // Convert array/object to JSON string for display in textarea
                if (Arr::isArray($data[$name]) || is_object($data[$name])) {
                    $data[$name] = json_encode($data[$name], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
            }
        }

        return $data;
    }

    /**
     * Clean record data, keeping only fields defined in the form
     */
    private static function cleanRecordData(array $data, array $formDef): array
    {
        $result = [];
        $fields = $formDef['fields'] ?? [];

        // Always keep id (preserve string IDs like "C47")
        if (isset($data['id'])) {
            $result['id'] = $data['id'];
        }

        foreach ($fields as $field) {
            $name = $field['name'] ?? '';
            $type = $field['type'] ?? 'textbox';
            if (empty($name) || str_starts_with($name, '_')) {
                continue;
            }
            if (isset($data[$name])) {
                $result[$name] = self::convertValue($data[$name], $type, $field['dataType'] ?? '');
            } elseif ($type === 'checkbox') {
                // Unchecked checkboxes are not sent by the browser
                $result[$name] = false;
            }
        }

        return $result;
    }

    /**
     * Convert value to appropriate type based on field type and dataType
     */
    private static function convertValue($value, string $type = '', string $dataType = ''): mixed
    {
        // JSON dataType - parse string to object/array
        if ($dataType === 'json') {
            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
            // Already an array/object or empty
            if (Arr::isArray($value)) {
                return $value;
            }
            return $value === '' ? [] : $value;
        }

        // Boolean conversion ONLY for checkbox/boolean fields
        // This prevents converting "1" to true for number fields like 'order'
        if ($type === 'checkbox' || $type === 'switch' || $type === 'boolean' || $dataType === 'boolean') {
            $lowerValue = is_string($value) ? strtolower($value) : $value;
            if ($lowerValue === 'true' || $value === '1' || $lowerValue === 'on' || $value === true || $value === 1) {
                return true;
            }
            if ($lowerValue === 'false' || $value === '0' || $lowerValue === 'off' || $value === false || $value === 0) {
                return false;
            }
        }

        // Number types - convert numeric strings to actual numbers
        if ($dataType === 'number' && is_numeric($value)) {
            return strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
        }

        // Null
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }

    /**
     * Get options from a JSON config file for combo fields
     *
     * @param string $configFile Config file name (e.g., "menu")
     * @param string $configArrayKey Key for the items array (e.g., "menus")
     * @param string $valueField Field to use as value (e.g., "id")
     * @param string $labelField Field to use as label (e.g., "name")
     * @return array Options array with 'id' and 'text' keys
     */
    public static function getOptionsFromConfig(string $configFile, string $configArrayKey, string $valueField = 'id', string $labelField = 'name'): array
    {
        try {
            $config = ConfigLoader::load($configFile);
            if ($config === null) {
                return [];
            }

            $items = $config[$configArrayKey] ?? [];
            $options = [];

            foreach ($items as $item) {
                $value = $item[$valueField] ?? '';
                $label = $item[$labelField] ?? $value;
                if ($value !== '') {
                    $options[] = [
                        'id' => $value,
                        'text' => $label,
                    ];
                }
            }

            return $options;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Create error response
     */
    /**
     * Apply filters to an array of items
     *
     * @param array $items Items to filter
     * @param array $filters Filters (field => value or field => ['from' => x, 'to' => y])
     * @return array Filtered items
     */
    private static function applyFilters(array $items, array $filters): array
    {
        if (empty($filters)) {
            return $items;
        }

        return array_filter($items, function($item) use ($filters) {
            foreach ($filters as $field => $filterValue) {
                $itemValue = $item[$field] ?? null;

                // Range filter (from/to)
                if (Arr::isArray($filterValue)) {
                    $from = $filterValue['from'] ?? '';
                    $to = $filterValue['to'] ?? '';

                    if ($from !== '' && $itemValue !== null) {
                        if (is_numeric($from) && is_numeric($itemValue)) {
                            if ((float)$itemValue < (float)$from) {
                                return false;
                            }
                        } elseif (strcasecmp((string)$itemValue, (string)$from) < 0) {
                            return false;
                        }
                    }

                    if ($to !== '' && $itemValue !== null) {
                        if (is_numeric($to) && is_numeric($itemValue)) {
                            if ((float)$itemValue > (float)$to) {
                                return false;
                            }
                        } elseif (strcasecmp((string)$itemValue, (string)$to) > 0) {
                            return false;
                        }
                    }
                }
                // Exact/contains filter
                else {
                    if ($filterValue === '' || $filterValue === null) {
                        continue; // Skip empty filters
                    }

                    if ($itemValue === null) {
                        return false;
                    }

                    // For string values, use case-insensitive contains
                    if (is_string($itemValue) && is_string($filterValue)) {
                        if (stripos($itemValue, $filterValue) === false) {
                            return false;
                        }
                    }
                    // For other types, exact match
                    elseif ((string)$itemValue !== (string)$filterValue) {
                        return false;
                    }
                }
            }
            return true;
        });
    }

    private static function error(string $message): array
    {
        return [
            'success' => false,
            'error' => $message
        ];
    }
}
