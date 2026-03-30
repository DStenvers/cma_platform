<?php
/**
 * Export Forms to JSON - Migration Script
 *
 * Exports all form definitions from tblForms/tblControls to JSON files.
 * Can be run standalone or as a migration script.
 *
 * Features:
 * - Queries database directly (not via GetFormDef)
 * - Generates all visible forms as JSON definitions with full metadata
 * - Handles subforms automatically
 * - Includes version info for future migrations
 * - Captures dateFormat, listQuery, and all control properties
 */

// Debug: Show all errors
ini_set('display_errors', 1);
error_reporting(E_ALL);

use App\Library\Database;
use App\Library\Response;
use App\Library\Server;
use Cma\SecurityHelper;
use Cma\Services\Logger;

// Wrap entire script in try-catch to prevent 500 errors
try {

// Fix path when running as migration
$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

// Check if running as migration (CLI, internal call, or ?migration=1) or standalone
$isMigration = defined('MIGRATION_RUNNING') || php_sapi_name() === 'cli' || \App\Library\Request::hasQuery('migration');

// Force regeneration - only when explicitly requested
// IMPORTANT: Do NOT force regenerate when MIGRATION_RUNNING is defined
// This preserves manual customizations to JSON form files (listQuery, etc.)
$forceRegenerate = \App\Library\Request::hasQuery('force') || \App\Library\Request::hasPost('force') || defined('FORCE_REGENERATE');

if (!$isMigration) {
    // Standalone mode - check permissions
    if (!SecurityHelper::isDeveloper()) {
        http_response_code(403);
        echo "Toegang geweigerd - alleen developers";
        exit(1);
    }
    Response::noCache();
}

/**
 * List of internal form names that stay in the CMA directory
 * These are system forms that should not be overwritten by user forms
 */
if (!defined('INTERNAL_FORMS')) {
    define('INTERNAL_FORMS', [
        'users',
        'groups',
        '_menus',
        '_menu_items',
        'cmamonitoring',
        'contentblocks',
        'marketingurl',
    ]);
}

// Export all forms
$result = exportAllFormsToJson($forceRegenerate);

if ($isMigration) {
    // Return result for migration service
    if ($result['success']) {
        echo "✓ " . $result['message'] . "\n";
        if (!empty($result['details'])) {
            foreach ($result['details'] as $detail) {
                echo "  - " . $detail . "\n";
            }
        }
        if (defined('MIGRATION_RUNNING')) {
            return true;
        }
        exit(0);
    } else {
        echo "✗ " . $result['message'] . "\n";
        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $error) {
                echo "  - " . $error . "\n";
            }
        }
        if (defined('MIGRATION_RUNNING')) {
            return false;
        }
        exit(1);
    }
} else {
    // Standalone mode - show HTML result
    cma_html_header('Formulieren exporteren');
    echo '<body class="contentbody tools">';
    echo '<div id="c">';

    if ($result['success']) {
        $detailsHtml = '';
        if (!empty($result['details'])) {
            $detailsHtml = '<br><strong>Details:</strong><ul>';
            foreach ($result['details'] as $detail) {
                $detailsHtml .= '<li>' . htmlspecialchars($detail) . '</li>';
            }
            $detailsHtml .= '</ul>';
        }
        echo '<lib-message type="success"><strong>Formulieren succesvol geexporteerd!</strong><br>' . htmlspecialchars($result['message']) . $detailsHtml . '</lib-message>';
    } else {
        $errorsHtml = '';
        if (!empty($result['errors'])) {
            $errorsHtml = '<ul>';
            foreach ($result['errors'] as $error) {
                $errorsHtml .= '<li>' . htmlspecialchars($error) . '</li>';
            }
            $errorsHtml .= '</ul>';
        }
        echo '<lib-message type="error"><strong>Export mislukt!</strong><br>' . htmlspecialchars($result['message']) . $errorsHtml . '</lib-message>';
    }

    echo '</div></body></html>';
}

} catch (\Throwable $e) {
    // Catch any errors/exceptions to prevent 500 response
    $isMigration = defined('MIGRATION_RUNNING') || php_sapi_name() === 'cli' || \App\Library\Request::hasQuery('migration');
    $errorMsg = $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
    if ($isMigration) {
        echo '✗ Fout: ' . $errorMsg . "\n";
        exit(1);
    } else {
        echo '<lib-message type="error"><strong>Fout:</strong> ' . htmlspecialchars($errorMsg) . '</lib-message>';
    }
}

/**
 * Export all forms from database to JSON files
 *
 * @param bool $force Force regeneration by deleting existing files first
 */
function exportAllFormsToJson(bool $force = false): array
{
    global $connrep;

    $details = [];
    $errors = [];
    $generated = 0;
    $updated = 0;
    $subformsGenerated = 0;
    $renamed = 0;

    // Current schema version for forms
    $schemaVersion = '1.0.0';

    // Directory paths
    $cmaDefinitionsDir = dirname(__DIR__) . '/assets/forms/definitions';
    $siteFormsDir = dirname(__DIR__, 2) . '/assets/forms';

    try {
        // Ensure database connection (important when running standalone)
        if ($connrep === null) {
            $connrep = Database::getConnection('rep');
            if ($connrep === null) {
                return ['success' => false, 'message' => 'Kan geen verbinding maken met repository database: ' . Database::getLastError()];
            }
        }

        // Ensure definitions directories exist
        if (!is_dir($cmaDefinitionsDir)) {
            mkdir($cmaDefinitionsDir, 0755, true);
        }
        if (!is_dir($siteFormsDir)) {
            mkdir($siteFormsDir, 0755, true);
        }

        // Never delete any existing JSON files - only overwrite forms that exist in the database
        // Manual JSON-only forms (users.json, groups.json, marketingurl.json, etc.) are never touched

        // Get all visible main forms (no parent)
        $sql = "SELECT f.ID, f.FormName, f.fkParentForm, m.Name as ModuleName
                FROM tblForms f
                LEFT JOIN tblModules m ON f.fkModule = m.ID
                WHERE f.Visible = True AND (f.fkParentForm IS NULL OR f.fkParentForm = 0)
                ORDER BY m.Name, f.FormName";
        $rs = Database::openRS($sql, $connrep);

        if ($rs === null) {
            return ['success' => false, 'message' => 'Kan tblForms niet lezen: ' . Database::getLastError()];
        }

        // First pass: collect all forms and generate unique names
        $formNames = [];
        $forms = [];

        while (!$rs->EOF) {
            $id = (int)$rs->fields['ID'];
            $name = $rs->fields['FormName'];
            $module = $rs->fields['ModuleName'] ?? '';

            // Generate unique name
            $baseName = generateSafeFormName($name);
            $finalName = $baseName;
            $counter = 1;
            while (in_array($finalName, array_values($formNames))) {
                $finalName = $baseName . '_' . $counter;
                $counter++;
            }

            $formNames[$id] = $finalName;
            $forms[] = [
                'id' => $id,
                'name' => $name,
                'generatedName' => $finalName,
                'module' => $module,
            ];

            $rs->MoveNext();
        }

        // Second pass: export each form
        foreach ($forms as $form) {
            $formId = $form['id'];
            $jsonName = $form['generatedName'];

            // Determine correct directory (CMA internal or site)
            $isInternalForm = in_array($jsonName, INTERNAL_FORMS);
            $targetDir = $isInternalForm ? $cmaDefinitionsDir : $siteFormsDir;
            $wrongDir = $isInternalForm ? $siteFormsDir : $cmaDefinitionsDir;
            $jsonPath = $targetDir . '/' . $jsonName . '.json';
            $wrongPath = $wrongDir . '/' . $jsonName . '.json';

            // Rename wrongly placed file to old_*.json if it exists
            if (file_exists($wrongPath)) {
                $oldPath = $wrongDir . '/old_' . $jsonName . '.json';
                if (rename($wrongPath, $oldPath)) {
                    $renamed++;
                    $details[] = "Hernoemd: {$jsonName}.json -> old_{$jsonName}.json (" . ($isInternalForm ? 'site' : 'cma') . ")";
                }
            }

            // Export form from database
            $jsonData = exportFormFromDatabase($formId, $jsonName, $schemaVersion);

            if ($jsonData === null) {
                $errors[] = "Kon formulier '{$form['name']}' (ID: $formId) niet laden";
                continue;
            }

            // Check if file exists - SKIP if it does (preserve customizations)
            // Only overwrite when force=true is explicitly set
            $isUpdate = false;
            if (file_exists($jsonPath)) {
                if (!$force) {
                    // File exists, skip to preserve manual customizations
                    continue;
                }
                $isUpdate = true;
            }

            // Handle subforms - update references and export them
            if (isset($jsonData['subforms']) && !empty($jsonData['subforms'])) {
                foreach ($jsonData['subforms'] as &$subform) {
                    $subformId = (int)$subform['sourceFormId'];
                    $subformTitle = $subform['title'] ?? 'subform';

                    // Generate subform filename
                    $subformJsonName = $jsonName . '_' . generateSafeFormName($subformTitle);

                    // Subforms go to same directory as parent form
                    $subformPath = $targetDir . '/' . $subformJsonName . '.json';
                    $subformWrongPath = $wrongDir . '/' . $subformJsonName . '.json';

                    // Rename wrongly placed subform file
                    if (file_exists($subformWrongPath)) {
                        $subformOldPath = $wrongDir . '/old_' . $subformJsonName . '.json';
                        if (rename($subformWrongPath, $subformOldPath)) {
                            $renamed++;
                        }
                    }

                    // Add file reference
                    $subform['form'] = $subformJsonName;

                    // Export subform only if it doesn't exist (preserve customizations)
                    // Only overwrite when force=true is explicitly set
                    $exportSubform = $force || !file_exists($subformPath);

                    if ($exportSubform) {
                        $subformData = exportFormFromDatabase($subformId, $subformJsonName, $schemaVersion);
                        if ($subformData) {
                            $subformData['parentForm'] = $jsonName;
                            if (saveJsonForm($subformPath, $subformData)) {
                                $subformsGenerated++;
                            }
                        }
                    }
                }
                unset($subform);
            }

            // Save main form
            if (saveJsonForm($jsonPath, $jsonData)) {
                if ($isUpdate) {
                    $updated++;
                    $details[] = "{$form['name']} -> {$jsonName}.json (" . ($isInternalForm ? 'cma' : 'site') . ", bijgewerkt)";
                } else {
                    $generated++;
                    $subformCount = count($jsonData['subforms'] ?? []);
                    $details[] = "{$form['name']} -> {$jsonName}.json (" . ($isInternalForm ? 'cma' : 'site') . ")" . ($subformCount > 0 ? " (+{$subformCount} subformulieren)" : "");
                }
            } else {
                $errors[] = "Kon '{$form['name']}' niet opslaan als {$jsonName}.json";
            }
        }

        $totalForms = count($forms);

        $renamedMsg = $renamed > 0 ? ", {$renamed} hernoemd naar old_*" : "";
        return [
            'success' => empty($errors),
            'message' => "Geexporteerd: {$generated} nieuwe, {$updated} bijgewerkt, {$subformsGenerated} subformulieren{$renamedMsg}",
            'details' => $details,
            'errors' => $errors,
            'stats' => [
                'total' => $totalForms,
                'generated' => $generated,
                'updated' => $updated,
                'subforms' => $subformsGenerated,
                'renamed' => $renamed,
                'errors' => count($errors),
            ]
        ];

    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Fout: ' . $e->getMessage(), 'errors' => [$e->getMessage()]];
    }
}

/**
 * Export a single form from database to JSON format
 * Queries database directly - does not use GetFormDef
 */
function exportFormFromDatabase(int $formId, string $formName, string $version): ?array
{
    global $connrep;

    // Ensure connection exists
    if ($connrep === null) {
        $connrep = Database::getConnection('rep');
        if ($connrep === null) {
            Logger::error('exportFormFromDatabase: Cannot connect to repository database');
            return null;
        }
    }

    // Query form definition directly from database
    // Split into separate queries to avoid Access JOIN issues
    $formSql = "SELECT * FROM tblForms WHERE ID = $formId";
    $formRs = Database::openRS($formSql, $connrep);
    if ($formRs === null || $formRs->EOF) {
        return null;
    }
    $formData = $formRs->fields;

    // Get SQL statement info if SqlID exists
    $sqlId = (int)($formData['SqlID'] ?? 0);
    if ($sqlId > 0) {
        $sqlStmtRs = Database::openRS("SELECT * FROM tblSqlStatements WHERE ID = $sqlId", $connrep);
        if ($sqlStmtRs !== null && !$sqlStmtRs->EOF) {
            // Access returns field names with varying case - check multiple variations
            $fields = $sqlStmtRs->fields;
            $formData['SqlTable'] = $fields['Table'] ?? $fields['table'] ?? $fields['TABLE'] ?? '';
            $formData['SqlStatement'] = $fields['SqlStatement'] ?? $fields['sqlstatement'] ?? $fields['SQLSTATEMENT'] ?? '';
        }
    }

    // Get module info if fkModule exists
    $moduleId = (int)($formData['fkModule'] ?? 0);
    if ($moduleId > 0) {
        $moduleRs = Database::openRS("SELECT fkDatabase, Cache_Prefix FROM tblModules WHERE ID = $moduleId", $connrep);
        if ($moduleRs !== null && !$moduleRs->EOF) {
            $formData['fkDatabase'] = $moduleRs->fields['fkDatabase'] ?? '';
            $formData['Cache_Prefix'] = $moduleRs->fields['Cache_Prefix'] ?? '';
        }
    }

    // Query controls for this form
    $controlSql = "SELECT * FROM tblControls
                   WHERE FormID = $formId AND Enabled = True
                   ORDER BY ExecutionOrder";
    $controlRs = Database::openRS($controlSql, $connrep);

    $controls = [];
    if ($controlRs !== null) {
        while (!$controlRs->EOF) {
            $controls[] = $controlRs->fields;
            $controlRs->MoveNext();
        }
    }

    // Build JSON structure
    $json = [
        '$schema' => '../schema/form-definition.schema.json',
        'version' => $version,
        'name' => $formName,
        'title' => $formData['FormName'] ?? '',
        'table' => $formData['SqlTable'] ?? '',
        'database' => (string)($formData['fkDatabase'] ?? ''),
        'idField' => $formData['IDField'] ?? 'ID',
        'listQuery' => $formData['NameQuery'] ?? '',
        'allowAdd' => (bool)($formData['MenuNew'] ?? true),
        'allowDelete' => (bool)($formData['MenuDelete'] ?? true),
        'allowCopy' => (bool)($formData['MenuCopy'] ?? false),
        'securityByUser' => (bool)($formData['blnSecurityByUser'] ?? false),
        'storeLastModified' => (bool)($formData['blnStoreLastModified'] ?? false),
        'previewUrl' => $formData['previewUrl'] ?? '',
        'afterPostUrl' => $formData['AfterPostUrl'] ?? '',
        'onLoadJs' => $formData['onloadJS'] ?? '',
        'filterIdName' => $formData['filterIDName'] ?? '',
        'quickSearchFields' => $formData['quickfilterfields'] ?? '',
        'sourceFormId' => $formId,
    ];

    // Filter
    if (!empty($formData['FilterFieldName'])) {
        $json['filter'] = [
            'field' => $formData['FilterFieldName'],
            'description' => $formData['FilterCaption'] ?? '',
        ];
    }

    // Group fields
    if (!empty($formData['Group1Field'])) {
        $json['groupFields'] = array_filter([
            $formData['Group1Field'] ?? '',
            $formData['Group2Field'] ?? '',
            $formData['Group3Field'] ?? '',
        ]);
    }

    // Detail field
    if (!empty($formData['DetailField'])) {
        $json['detailField'] = $formData['DetailField'];
    }

    // Recurse field (for tree structures)
    if (!empty($formData['recurseField'])) {
        $json['recurseField'] = $formData['recurseField'];
    }

    // Extra buttons
    $extraButtons = [];
    for ($i = 1; $i <= 5; $i++) {
        $suffix = $i == 1 ? '' : (string)$i;
        $url = $formData["extraIcon{$suffix}URL"] ?? '';
        $icon = $formData["extraIcon{$suffix}Resource"] ?? '';
        $title = $formData["extraIcon{$suffix}Title"] ?? '';

        if ($url || $icon || $title) {
            $extraButtons[] = array_filter([
                'url' => $url,
                'icon' => $icon,
                'title' => $title,
            ]);
        }
    }
    if (!empty($extraButtons)) {
        $json['extraButtons'] = $extraButtons;
    }

    // Fields/Controls
    $json['fields'] = [];
    // Control type mapping - matches FormRenderer constants
    $controlTypeMap = [
        2 => 'combobox',
        3 => 'textbox',
        5 => 'checkbox',
        6 => 'memo',
        8 => 'checklist',
        9 => 'image',
        10 => 'url',
        11 => 'file',
        12 => 'label',
        13 => 'sortlist',
        14 => 'directory',
        15 => 'groupseparator',
        16 => 'userlist',
        17 => 'email',
        18 => 'xmlstore',
        19 => 'htmlstrip',
        20 => 'thumbnail',
        21 => 'time',
        22 => 'password',
        103 => 'tip',
    ];

    foreach ($controls as $i => $ctrl) {
        $controlTypeId = (int)($ctrl['ControlTypeID'] ?? 3);
        $controlTypeName = $controlTypeMap[$controlTypeId] ?? 'textbox';

        $fieldName = $ctrl['FieldName'] ?? '';
        $caption = $ctrl['Caption'] ?? '';

        // For group separators and other display-only controls, generate a name
        if (empty($fieldName)) {
            if ($controlTypeId == 15) {
                $fieldName = '_group_' . $i;
            } elseif ($controlTypeId == 12) {
                $fieldName = '_label_' . $i;
            } elseif ($controlTypeId == 103) {
                $fieldName = '_tip_' . $i;
            } else {
                continue;
            }
        }

        $field = [
            'name' => $fieldName,
            'type' => $controlTypeName,
            'caption' => $caption,
        ];

        // Common properties
        if ($ctrl['IsRequired'] ?? false) {
            $field['required'] = true;
        }
        if ($ctrl['blnReadOnly'] ?? false) {
            $field['readonly'] = true;
        }
        if ($ctrl['isBeheer'] ?? false) {
            $field['adminOnly'] = true;
        }
        if (!empty($ctrl['PostCaption'])) {
            $field['hint'] = $ctrl['PostCaption'];
        }
        if (!empty($ctrl['actie'])) {
            $field['action'] = $ctrl['actie'];
        }
        if ($ctrl['bCombineWithNext'] ?? false) {
            $field['combineWithNext'] = true;
        }
        if ($ctrl['blnNewChangableOnly'] ?? false) {
            $field['newOnly'] = true;
        }

        // Data type from database schema - determines rendering (date picker, number validation, etc.)
        $schemaDataType = strtolower($ctrl['schema_datatype'] ?? '');
        if (!empty($schemaDataType)) {
            $field['dataType'] = $schemaDataType;
        }

        // Date format - legacy field, also indicates date type
        $datePrec = $ctrl['schema_date_prec'] ?? '';
        if (!empty($datePrec)) {
            $field['dateFormat'] = $datePrec;
            // If dataType not set but dateFormat exists, it's a date field
            if (empty($field['dataType'])) {
                $field['dataType'] = 'datetime';
            }
        }

        // Numeric precision - for number fields
        $numPrec = $ctrl['schema_num_prec'] ?? '';
        if (!empty($numPrec)) {
            $field['numericPrecision'] = $numPrec;
        }

        // Control-specific properties
        switch ($controlTypeId) {
            case 2: // combobox
            case 16: // userlist
            case 18: // xmlstore
                if (!empty($ctrl['SourceTable'])) {
                    $field['sourceTable'] = $ctrl['SourceTable'];
                }
                if (!empty($ctrl['IDField'])) {
                    $field['idField'] = $ctrl['IDField'];
                }
                if (!empty($ctrl['ForeignIDField'])) {
                    $field['displayField'] = $ctrl['ForeignIDField'];
                }
                if (!empty($ctrl['SqlList'])) {
                    $field['sql'] = $ctrl['SqlList'];
                }
                if (!empty($ctrl['fkDatabase'])) {
                    $field['database'] = (string)$ctrl['fkDatabase'];
                }
                break;

            case 3: // textbox
            case 17: // email
                $maxLen = (int)($ctrl['schema_char_maxl'] ?? 0);
                if ($maxLen > 0) {
                    $field['maxLength'] = $maxLen;
                }
                break;

            case 6: // memo
                $height = (int)($ctrl['Height'] ?? 3);
                if ($height != 3) {
                    $field['height'] = $height;
                }
                if ($ctrl['TagsAllowed'] ?? false) {
                    $field['allowHtml'] = true;
                }
                if ($ctrl['blnLimitedHTML'] ?? false) {
                    $field['limitedHtml'] = true;
                }
                if ((int)($ctrl['intMaxChars'] ?? 0) > 0) {
                    $field['maxChars'] = (int)$ctrl['intMaxChars'];
                }
                break;

            case 8: // checklist
                if (!empty($ctrl['SqlList'])) {
                    $field['sql'] = $ctrl['SqlList'];
                }
                $width = (int)($ctrl['CheckListWidth'] ?? 200);
                if ($width != 200) {
                    $field['width'] = $width;
                }
                break;

            case 9: // image
            case 11: // file
                if (!empty($ctrl['ImgPath'])) {
                    $field['path'] = $ctrl['ImgPath'];
                }
                if ($ctrl['blnFileRandomName'] ?? false) {
                    $field['randomName'] = true;
                }
                if ($controlTypeId == 9) {
                    $resizeType = (int)($ctrl['ImgResizeType'] ?? 0);
                    if ($resizeType > 0) {
                        $field['resizeType'] = $resizeType;
                        $field['resizeWidth'] = (int)($ctrl['ImgResizeWidth'] ?? 0);
                        $field['resizeHeight'] = (int)($ctrl['ImgResizeHeight'] ?? 0);
                    }
                    if (!empty($ctrl['ImgWidthField'])) {
                        $field['widthField'] = $ctrl['ImgWidthField'];
                    }
                    if (!empty($ctrl['ImgHeightField'])) {
                        $field['heightField'] = $ctrl['ImgHeightField'];
                    }
                }
                break;

            case 19: // htmlstrip - auto-generated from base field
                if (!empty($ctrl['BaseFieldName'])) {
                    $field['baseField'] = $ctrl['BaseFieldName'];
                }
                break;

            case 20: // thumbnail - auto-generated from base image field
                if (!empty($ctrl['BaseFieldName'])) {
                    $field['baseField'] = $ctrl['BaseFieldName'];
                }
                if (!empty($ctrl['ImgPath'])) {
                    $field['path'] = $ctrl['ImgPath'];
                }
                $resizeWidth = (int)($ctrl['ImgResizeWidth'] ?? 0);
                $resizeHeight = (int)($ctrl['ImgResizeHeight'] ?? 0);
                if ($resizeWidth > 0) {
                    $field['resizeWidth'] = $resizeWidth;
                }
                if ($resizeHeight > 0) {
                    $field['resizeHeight'] = $resizeHeight;
                }
                break;

            case 103: // tip
                if (!empty($ctrl['XMLSnippet'])) {
                    $field['content'] = $ctrl['XMLSnippet'];
                }
                break;
        }

        $json['fields'][] = $field;
    }

    // If listQuery is just "SELECT ID FROM table", generate a better one with display columns
    $json['listQuery'] = improveListQuery($json['listQuery'], $json['table'], $json['idField'], $json['fields']);

    // Get subforms
    $subformSql = "SELECT ID, FormName, blnSecurityByUser, MenuNew, isBeheer, SubFormSortorder
                   FROM tblForms
                   WHERE fkParentForm = $formId AND Visible = True
                   ORDER BY SubFormSortorder, FormName";
    $subformRs = Database::openRS($subformSql, $connrep);

    // Parent table for determining FK fields in subforms
    $parentTable = $json['table'] ?? '';

    if ($subformRs !== null) {
        $json['subforms'] = [];
        while (!$subformRs->EOF) {
            $subformId = (int)$subformRs->fields['ID'];
            $sortOrder = (int)($subformRs->fields['SubFormSortorder'] ?? 0);
            $subformEntry = [
                'title' => $subformRs->fields['FormName'],
                'sourceFormId' => $subformId,
                'order' => $sortOrder,
            ];

            // Try to find parentField - the FK field in subform that references parent table
            if (!empty($parentTable)) {
                $parentField = detectSubformParentField($subformId, $parentTable);
                if (!empty($parentField)) {
                    $subformEntry['parentField'] = $parentField;
                }
            }

            $json['subforms'][] = $subformEntry;
            $subformRs->MoveNext();
        }
    }

    return $json;
}

/**
 * Detect the parent field in a subform that references the parent table
 *
 * Searches for a FK field using multiple strategies:
 * 1. Query ALL controls (not just comboboxes) for FK patterns
 * 2. Check the SQL statement (NameQuery) for FK patterns
 * 3. Query the actual data table schema for FK columns
 *
 * @param int $subformId The subform's form ID
 * @param string $parentTable The parent form's table name
 * @return string|null The parent field name or null if not found
 */
function detectSubformParentField(int $subformId, string $parentTable): ?string
{
    global $connrep;

    if (empty($parentTable)) {
        return null;
    }

    // Build variations of parent table name to search for
    $baseName = preg_replace('/^tbl/i', '', $parentTable);
    $variations = [
        strtolower('fk' . $baseName),                              // fkAgenda
        strtolower('fk' . rtrim($baseName, 's')),                  // fkDocenten -> fkDocent
        strtolower('fk' . preg_replace('/en$/i', '', $baseName)),  // fkDocenten -> fkDoct
        strtolower('fk' . preg_replace('/(en|s)$/i', '', $baseName)), // Combined
    ];

    // Also handle compound names like tblOpleidingenBlokken
    if (preg_match('/^(.+?)([A-Z][a-z]+)$/u', $baseName, $matches)) {
        $lastWord = preg_replace('/(en|ken|s)$/i', '', $matches[2]);
        $firstWord = preg_replace('/(en|s)$/i', '', $matches[1]);
        $variations[] = strtolower('fk' . $firstWord . $lastWord);
        $variations[] = strtolower('fk' . $lastWord);
    }
    $variations = array_unique($variations);

    // Method 1: Query ALL controls for the subform (not just comboboxes)
    $sql = "SELECT FieldName, SourceTable FROM tblControls
            WHERE FormID = $subformId AND Enabled = True";
    $rs = Database::openRS($sql, $connrep);

    $foundField = null;

    if ($rs !== null) {
        while (!$rs->EOF) {
            $fieldName = $rs->fields['FieldName'] ?? '';
            $sourceTable = $rs->fields['SourceTable'] ?? '';

            // Priority 1: Check if sourceTable matches parent table exactly
            if (!empty($sourceTable) && strcasecmp($sourceTable, $parentTable) === 0) {
                return $fieldName;
            }

            // Priority 2: Check if field name matches FK naming convention
            if (!empty($fieldName)) {
                $lowerFieldName = strtolower($fieldName);
                foreach ($variations as $variation) {
                    if ($lowerFieldName === $variation) {
                        $foundField = $fieldName;
                        // Don't return yet - sourceTable match has higher priority
                    }
                }
            }

            $rs->MoveNext();
        }
    }

    // Return if found in controls
    if ($foundField !== null) {
        return $foundField;
    }

    // Method 2: Check the NameQuery SQL for FK patterns
    $formSql = "SELECT SqlID FROM tblForms WHERE ID = $subformId";
    $formRs = Database::openRS($formSql, $connrep);

    if ($formRs !== null && !$formRs->EOF) {
        $sqlId = (int)($formRs->fields['SqlID'] ?? 0);
        if ($sqlId > 0) {
            $stmtSql = "SELECT SqlStatement FROM tblSqlStatements WHERE ID = $sqlId";
            $stmtRs = Database::openRS($stmtSql, $connrep);
            if ($stmtRs !== null && !$stmtRs->EOF) {
                $nameQuery = $stmtRs->fields['SqlStatement'] ?? '';
                foreach ($variations as $variation) {
                    if (preg_match('/\b(' . preg_quote($variation, '/') . ')\b/i', $nameQuery, $match)) {
                        return $match[1];
                    }
                }
            }
        }
    }

    // Method 3: Query the actual data table schema for FK columns
    $dbSql = "SELECT m.fkDatabase, s.Table as TableName
              FROM tblForms f
              INNER JOIN tblModules m ON f.fkModule = m.ID
              LEFT JOIN tblSqlStatements s ON f.SqlID = s.ID
              WHERE f.ID = $subformId";
    $dbRs = Database::openRS($dbSql, $connrep);

    if ($dbRs !== null && !$dbRs->EOF) {
        $databaseId = $dbRs->fields['fkDatabase'] ?? '';
        $tableName = $dbRs->fields['TableName'] ?? '';

        if (!empty($databaseId) && !empty($tableName)) {
            try {
                $conn = Database::getConnection((string)$databaseId);
                if ($conn !== null) {
                    $columns = Database::getSchema($conn, 4, [null, null, $tableName]);
                    if ($columns !== null) {
                        $columnMap = [];
                        while (!$columns->EOF) {
                            $colName = $columns->fields['COLUMN_NAME'] ?? '';
                            if (!empty($colName)) {
                                $columnMap[strtolower($colName)] = $colName;
                            }
                            $columns->MoveNext();
                        }
                        foreach ($variations as $variation) {
                            if (isset($columnMap[$variation])) {
                                return $columnMap[$variation];
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                // Silently fail - detection not possible
            }
        }
    }

    return null;
}

/**
 * Generate a safe form name from a display name
 */
function generateSafeFormName(?string $displayName): string
{
    $name = strtolower($displayName ?? 'form');
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    $name = trim($name, '_');
    return substr($name, 0, 64) ?: 'form';
}

/**
 * Save JSON form to file with pretty printing
 * NEVER overwrites existing files with meaningful content (table + fields defined)
 */
function saveJsonForm(string $path, array $data): bool
{
    // Never overwrite existing form definitions - they may have been hand-tuned
    if (file_exists($path)) {
        $existing = json_decode(file_get_contents($path), true);
        if (is_array($existing) && (!empty($existing['table']) || !empty($existing['fields']))) {
            echo "  ⊘ Overgeslagen (bestaand bestand met tabel/velden): " . basename($path) . "\n";
            return true;
        }
    }

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json) !== false;
}

/**
 * Improve a listQuery that only selects ID to include display columns
 *
 * When the database's NameQuery only has "SELECT ID FROM table ORDER BY ID",
 * this function builds a proper SELECT with displayable fields from the form.
 *
 * @param string $listQuery The original listQuery from database
 * @param string $table The form's table name
 * @param string $idField The ID field name (usually 'ID')
 * @param array $fields The form's field definitions
 * @return string The improved listQuery or original if already has columns
 */
function improveListQuery(string $listQuery, string $table, string $idField, array $fields): string
{
    // Skip if empty or already has multiple columns
    if (empty($listQuery) || empty($table)) {
        return $listQuery;
    }

    // Check if query is just "SELECT ID FROM table" (with optional ORDER BY)
    $pattern = '/^\s*SELECT\s+' . preg_quote($idField, '/') . '\s+FROM\s+\[?' . preg_quote($table, '/') . '\]?\s*(ORDER\s+BY\s+.*)?\s*$/i';
    if (!preg_match($pattern, $listQuery)) {
        // Query already has columns or is more complex, keep as-is
        return $listQuery;
    }

    // Collect displayable fields (skip system fields, FK fields for now)
    $displayFields = [];
    $comboboxJoins = [];
    $maxFields = 6;

    foreach ($fields as $field) {
        if (count($displayFields) >= $maxFields) {
            break;
        }

        $fieldName = $field['name'] ?? '';
        $fieldType = $field['type'] ?? 'textbox';

        // Skip generated/system fields
        if (strpos($fieldName, '_group') === 0 || strpos($fieldName, '_label') === 0 || strpos($fieldName, '_tip') === 0) {
            continue;
        }

        // Skip memo/large text fields (not suitable for list display)
        if ($fieldType === 'memo') {
            continue;
        }

        // Skip image/file fields
        if (in_array($fieldType, ['image', 'file', 'thumbnail', 'directory'])) {
            continue;
        }

        // For combobox, try to JOIN to get display value
        if (in_array($fieldType, ['combobox', 'userlist'])) {
            $sourceTable = $field['sourceTable'] ?? '';
            $displayField = $field['displayField'] ?? '';
            $sourceIdField = $field['idField'] ?? 'ID';

            if (!empty($sourceTable) && !empty($displayField)) {
                // Generate alias for joined table (use abbreviated name)
                $alias = 'cb_' . count($comboboxJoins);
                $comboboxJoins[] = [
                    'sourceTable' => $sourceTable,
                    'alias' => $alias,
                    'joinField' => $fieldName,
                    'sourceIdField' => $sourceIdField,
                    'displayField' => $displayField,
                    'caption' => $field['caption'] ?? $fieldName,
                ];
                $displayFields[] = "{$alias}.{$displayField} AS {$fieldName}_naam";
            } else {
                // No join info, include raw FK field
                $displayFields[] = "{$table}.{$fieldName}";
            }
            continue;
        }

        // Regular field
        $displayFields[] = "{$table}.{$fieldName}";
    }

    // If no display fields found, return original
    if (empty($displayFields)) {
        return $listQuery;
    }

    // Build improved query
    $select = "SELECT {$table}.{$idField}";
    $select .= ", " . implode(", ", $displayFields);
    $from = " FROM {$table}";

    // Add LEFT JOINs for combobox lookups
    foreach ($comboboxJoins as $join) {
        $from .= " LEFT JOIN {$join['sourceTable']} AS {$join['alias']} ON {$table}.{$join['joinField']} = {$join['alias']}.{$join['sourceIdField']}";
    }

    // Try to find a good ORDER BY field (prefer date fields, then any text field)
    $orderByField = $idField;
    $orderByDir = 'ASC';
    foreach ($fields as $field) {
        $dataType = $field['dataType'] ?? '';
        if (in_array($dataType, ['date', 'datetime'])) {
            $orderByField = $field['name'];
            $orderByDir = 'DESC'; // Dates usually sorted newest first
            break;
        }
    }

    $order = " ORDER BY {$orderByField} {$orderByDir}";

    return $select . $from . $order;
}
