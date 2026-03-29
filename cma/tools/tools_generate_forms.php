<?php
/**
 * Generate missing JSON form definitions from repository.mdb
 *
 * This script reads the original form definitions from repository.mdb
 * and generates JSON files for forms that don't exist yet.
 */
require_once dirname(__DIR__) . '/bootstrap.inc';

use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\SecurityHelper;

Response::noCache();

// Check access
if (!SecurityHelper::isAdmin()) {
    http_response_code(403);
    die("Toegang geweigerd - alleen admins");
}

// Control type mapping (from tblControlTypes)
$controlTypeMap = [
    1 => 'textbox',      // Textbox
    2 => 'combobox',     // Combobox
    3 => 'textbox',      // Textbox (date/number)
    4 => 'checklist',    // Checklist
    5 => 'checkbox',     // Checkbox
    6 => 'memo',         // Memo/textarea
    7 => 'textbox',      // Textbox variant
    8 => 'radiobutton',  // Radio button
    9 => 'image',        // Image
    10 => 'textbox',     // Textbox variant
    11 => 'file',        // File upload
    12 => 'label',       // Label (readonly)
    13 => 'textbox',     // Textbox variant
    14 => 'time',        // Time field
    15 => 'groupseparator', // Group separator
    16 => 'email',       // Email field
    17 => 'textbox',     // Textbox variant
    18 => 'password',    // Password field
];

// ADO data type mapping to readable type names
$dataTypeMap = [
    2 => 'integer',     // adSmallInt
    3 => 'integer',     // adInteger
    4 => 'float',       // adSingle
    5 => 'float',       // adDouble
    6 => 'currency',    // adCurrency
    7 => 'date',        // adDate
    11 => 'boolean',    // adBoolean
    17 => 'integer',    // adUnsignedTinyInt
    72 => 'guid',       // adGUID
    128 => 'binary',    // adBinary
    129 => 'string',    // adChar
    130 => 'string',    // adWChar (text)
    131 => 'decimal',   // adNumeric
    200 => 'string',    // adVarChar
    201 => 'memo',      // adLongVarChar (memo)
    202 => 'string',    // adVarWChar
    203 => 'memo',      // adLongVarWChar (memo)
];

// Get connections
$repConn = Database::getConnection('rep');
$dataConn = Database::getConnection('data');

if (!$repConn) {
    die("Kan geen verbinding maken met repository database");
}

// Get list of missing form IDs from the request or find them
// Maps formId => parentFormName for proper naming
$missingFormIds = [];
if (Request::hasQuery('formids')) {
    // Manual mode - no parent form name available
    foreach (array_map('intval', explode(',', Request::query('formids', ''))) as $id) {
        $missingFormIds[$id] = null;
    }
} else {
    // Find all sourceFormIds referenced in subforms that don't have JSON files
    $formsDir = dirname(__DIR__, 2) . '/assets/forms';
    $existingForms = [];

    // Build map of existing sourceFormIds => formName
    foreach (glob($formsDir . '/*.json') as $file) {
        if (strpos($file, '.schema.json') !== false) continue;
        $data = json_decode(file_get_contents($file), true);
        if ($data && isset($data['sourceFormId'])) {
            $existingForms[$data['sourceFormId']] = [
                'file' => basename($file),
                'title' => $data['title'] ?? $data['name'] ?? ''
            ];
        }
    }

    // Find referenced but missing forms, tracking their parent
    foreach (glob($formsDir . '/*.json') as $file) {
        if (strpos($file, '.schema.json') !== false) continue;
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['subforms'])) continue;

        $parentTitle = $data['title'] ?? $data['name'] ?? '';

        foreach ($data['subforms'] as $subform) {
            if (isset($subform['sourceFormId']) && !isset($existingForms[$subform['sourceFormId']])) {
                // Store parent form title for proper naming
                $missingFormIds[$subform['sourceFormId']] = $parentTitle;
            }
        }
    }
}

/**
 * Get form definition from repository
 */
function getFormDefinition($formId, $repConn) {
    $sql = "SELECT * FROM tblForms WHERE ID = $formId";
    $rs = Database::openRS($sql, $repConn);
    if (!$rs || $rs->EOF) return null;
    return $rs->fields;
}

/**
 * Get SQL statement details
 */
function getSqlStatement($sqlId, $repConn) {
    if (!$sqlId) return null;
    $sql = "SELECT * FROM tblSqlStatements WHERE ID = $sqlId";
    $rs = Database::openRS($sql, $repConn);
    if (!$rs || $rs->EOF) return null;
    return $rs->fields;
}

/**
 * Get form controls/fields
 */
function getFormControls($formId, $repConn) {
    $sql = "SELECT * FROM tblControls WHERE FormID = $formId ORDER BY ExecutionOrder";
    $rs = Database::openRS($sql, $repConn);
    $controls = [];
    if ($rs) {
        while (!$rs->EOF) {
            $controls[] = $rs->fields;
            $rs->MoveNext();
        }
    }
    return $controls;
}

/**
 * Get subforms for a form
 */
function getSubforms($formId, $repConn) {
    $sql = "SELECT * FROM tblForms WHERE fkParentForm = $formId ORDER BY SubFormSortorder, FormName";
    $rs = Database::openRS($sql, $repConn);
    $subforms = [];
    if ($rs) {
        while (!$rs->EOF) {
            $subforms[] = $rs->fields;
            $rs->MoveNext();
        }
    }
    return $subforms;
}

/**
 * Convert form name to JSON filename
 */
function formNameToFilename($formName, $parentFormName = null) {
    // Clean and convert to lowercase with underscores
    $name = strtolower(trim($formName));
    $name = preg_replace('/[^a-z0-9]+/', '_', $name);
    $name = trim($name, '_');

    // If this is a subform, prefix with parent name
    if ($parentFormName) {
        $parent = strtolower(trim($parentFormName));
        $parent = preg_replace('/[^a-z0-9]+/', '_', $parent);
        $parent = trim($parent, '_');
        $name = $parent . '_' . $name;
    }

    return $name;
}

/**
 * Build field definition from control
 */
function buildFieldDefinition($control, $controlTypeMap, $dataTypeMap, $repConn) {
    global $controlTypeMap, $dataTypeMap;

    $fieldName = $control['FieldName'] ?? '';
    $controlType = $control['ControlTypeID'] ?? 1;
    $caption = $control['Caption'] ?? $fieldName;

    // Skip empty field names (group separators have empty names)
    $type = $controlTypeMap[$controlType] ?? 'textbox';

    $field = [];

    // Handle group separators
    if ($type === 'groupseparator') {
        static $groupCounter = 0;
        $field['name'] = '_group_' . ($groupCounter++);
        $field['type'] = 'groupseparator';
        $field['caption'] = $caption;
        return $field;
    }

    // Skip if no field name
    if (empty($fieldName)) {
        return null;
    }

    $field['name'] = $fieldName;
    $field['type'] = $type;
    $field['caption'] = $caption;

    // Required
    if (!empty($control['IsRequired'])) {
        $field['required'] = true;
    }

    // Readonly
    if (isset($control['Enabled']) && $control['Enabled'] === false) {
        $field['readonly'] = true;
    }
    if (!empty($control['blnReadOnly'])) {
        $field['readonly'] = true;
    }

    // New only (changeable only on new records)
    if (!empty($control['blnNewChangableOnly'])) {
        $field['newOnly'] = true;
    }

    // Data type
    $schemaDataType = $control['schema_datatype'] ?? null;
    if ($schemaDataType && isset($dataTypeMap[$schemaDataType])) {
        $field['dataType'] = $dataTypeMap[$schemaDataType];
    }

    // Numeric precision
    if (!empty($control['schema_num_prec'])) {
        $field['numericPrecision'] = (string)$control['schema_num_prec'];
    }

    // Max length for text fields
    if (!empty($control['schema_char_maxl']) && $type === 'textbox') {
        $field['maxLength'] = (int)$control['schema_char_maxl'];
    }

    // Post caption (hint)
    if (!empty($control['PostCaption'])) {
        $field['hint'] = $control['PostCaption'];
    }

    // Combobox settings
    if ($type === 'combobox') {
        if (!empty($control['SourceTable'])) {
            $field['sourceTable'] = $control['SourceTable'];
        }
        if (!empty($control['IDField'])) {
            $field['idField'] = $control['IDField'];
        }
        if (!empty($control['ForeignIDField'])) {
            $field['displayField'] = $control['ForeignIDField'];
        }
        if (!empty($control['SqlList'])) {
            $field['sql'] = $control['SqlList'];
        }
    }

    // Memo/textarea height
    if ($type === 'memo') {
        $height = $control['Height'] ?? 4;
        $field['height'] = max(1, (int)$height);

        // HTML settings
        if (!empty($control['TagsAllowed'])) {
            $field['allowHtml'] = true;
        }
        if (!empty($control['blnLimitedHTML'])) {
            $field['limitedHtml'] = true;
        }
    }

    // File upload settings
    if ($type === 'file') {
        if (!empty($control['ImgPath'])) {
            $field['path'] = $control['ImgPath'];
        }
        if (!empty($control['blnFileRandomName'])) {
            $field['randomName'] = true;
        }
    }

    // Action field (vervalt, beheer, etc.)
    if (!empty($control['actie'])) {
        $field['action'] = $control['actie'];
    }

    return $field;
}

/**
 * Generate JSON form definition
 */
function generateFormJson($formId, $repConn, $controlTypeMap, $dataTypeMap, $parentFormName = null) {
    $form = getFormDefinition($formId, $repConn);
    if (!$form) return null;

    $sqlStmt = getSqlStatement($form['SqlID'], $repConn);
    $controls = getFormControls($formId, $repConn);
    $subforms = getSubforms($formId, $repConn);

    $formName = $form['FormName'] ?? 'unknown';
    $tableName = $sqlStmt['Table'] ?? '';
    $jsonName = formNameToFilename($formName, $parentFormName);

    // Build the JSON structure
    $json = [
        '$schema' => '../cma/config/schema/form-definition.schema.json',
        'version' => '1.0.0',
        'name' => $jsonName,
        'title' => $formName,
        'table' => $tableName,
        'database' => '6',
        'idField' => $form['IDField'] ?? 'ID',
    ];

    // List query from SQL statement
    if ($sqlStmt && !empty($sqlStmt['SQL'])) {
        $json['listQuery'] = $sqlStmt['SQL'];
    } else {
        // Generate a basic list query
        $json['listQuery'] = "SELECT ID FROM $tableName ORDER BY ID";
    }

    // Form settings
    $json['allowAdd'] = !empty($form['MenuNew']);
    $json['allowDelete'] = !empty($form['MenuDelete']);
    $json['allowCopy'] = !empty($form['menuCopy']);
    $json['securityByUser'] = !empty($form['blnSecurityByUser']);
    $json['labelColumnWidth'] = 200;
    $json['storeLastModified'] = !empty($form['blnStoreLastModified']);
    $json['previewUrl'] = $form['previewUrl'] ?? '';
    $json['afterPostUrl'] = $form['AfterPostUrl'] ?? '';
    $json['onLoadJs'] = $form['onloadJS'] ?? '';
    $json['filterIdName'] = $form['filterIDName'] ?? '';
    $json['quickSearchFields'] = $form['quickfilterfields'] ?? '';
    $json['sourceFormId'] = (int)$formId;

    // Group fields
    $groupFields = [];
    if (!empty($form['Group1Field'])) $groupFields[] = $form['Group1Field'];
    if (!empty($form['Group2Field'])) $groupFields[] = $form['Group2Field'];
    if (!empty($form['Group3Field'])) $groupFields[] = $form['Group3Field'];
    if (!empty($groupFields)) {
        $json['groupFields'] = $groupFields;
    }

    // Detail field
    if (!empty($form['DetailField'])) {
        $json['detailField'] = $form['DetailField'];
    }

    // Build fields array
    $fields = [];
    foreach ($controls as $control) {
        $field = buildFieldDefinition($control, $controlTypeMap, $dataTypeMap, $repConn);
        if ($field) {
            $fields[] = $field;
        }
    }
    $json['fields'] = $fields;

    // Build subforms array
    $subformDefs = [];
    foreach ($subforms as $subform) {
        $subformDef = [
            'title' => $subform['FormName'] ?? 'Subform',
            'sourceFormId' => (int)$subform['ID'],
        ];

        // Parent field
        if (!empty($subform['ParentField'])) {
            // Clean up parent field (remove table prefix if present)
            $parentField = $subform['ParentField'];
            if (strpos($parentField, '.') !== false) {
                $parts = explode('.', $parentField);
                $parentField = end($parts);
            }
            $subformDef['parentField'] = $parentField;
        }

        // Generate form name for subform
        $subformDef['form'] = formNameToFilename($subform['FormName'], $formName);

        $subformDefs[] = $subformDef;
    }
    $json['subforms'] = $subformDefs;

    // Parent form reference (if this is a subform)
    if (!empty($form['fkParentForm'])) {
        $parentForm = getFormDefinition($form['fkParentForm'], $repConn);
        if ($parentForm) {
            $json['parentForm'] = formNameToFilename($parentForm['FormName']);
        }
    }

    return [
        'filename' => $jsonName . '.json',
        'json' => $json,
        'formName' => $formName,
        'tableName' => $tableName,
        'parentFormName' => $parentFormName,
    ];
}

// Process mode
$mode = Request::query('mode', 'preview');
$formsDir = dirname(__DIR__, 2) . '/assets/forms';

$results = [];
$errors = [];

foreach ($missingFormIds as $formId => $parentFormName) {
    try {
        $result = generateFormJson($formId, $repConn, $controlTypeMap, $dataTypeMap, $parentFormName);
        if ($result) {
            $results[] = $result;

            // Write file if in generate mode
            if ($mode === 'generate') {
                $filepath = $formsDir . '/' . $result['filename'];
                $jsonContent = json_encode($result['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                // Use tabs for indentation to match existing files
                $jsonContent = preg_replace('/^(  +)/m', str_repeat("\t", 1), $jsonContent);
                $jsonContent = preg_replace_callback('/^(\t+)/m', function($m) {
                    return str_repeat("\t", (int)(strlen($m[1]) / 4) + 1);
                }, $jsonContent);
                // Simpler approach - just use 1 tab per 4 spaces
                $jsonContent = str_replace('    ', "\t", $jsonContent);

                if (file_put_contents($filepath, $jsonContent)) {
                    $result['written'] = true;
                } else {
                    $result['written'] = false;
                    $errors[] = "Kon bestand niet schrijven: " . $result['filename'];
                }
            }
        } else {
            $errors[] = "Form ID $formId niet gevonden in repository";
        }
    } catch (Exception $e) {
        $errors[] = "Fout bij form ID $formId: " . $e->getMessage();
    }
}

// ============================================
// RENAME MODE: Fix existing subform filenames
// ============================================
$renameResults = [];
if ($mode === 'rename-preview' || $mode === 'rename-execute') {
    $formsDir = dirname(__DIR__, 2) . '/assets/forms';

    // Scan all forms and their subforms
    foreach (glob($formsDir . '/*.json') as $file) {
        if (strpos($file, '.schema.json') !== false) continue;

        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['subforms']) || empty($data['subforms'])) continue;

        $parentName = $data['name'] ?? pathinfo($file, PATHINFO_FILENAME);
        $parentTitle = $data['title'] ?? $parentName;
        $parentUpdated = false;

        foreach ($data['subforms'] as $idx => $subform) {
            $subformFile = $subform['form'] ?? '';
            if (empty($subformFile)) continue;

            // Check if file exists
            $subformPath = $formsDir . '/' . $subformFile . '.json';
            if (!file_exists($subformPath)) continue;

            // Check if already has parent prefix
            $expectedPrefix = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $parentName)) . '_';
            if (strpos($subformFile, $expectedPrefix) === 0) continue;

            // Calculate new name with parent prefix
            $newSubformName = $expectedPrefix . $subformFile;
            $newSubformPath = $formsDir . '/' . $newSubformName . '.json';

            // Skip if new name already exists (collision)
            if (file_exists($newSubformPath) && $newSubformPath !== $subformPath) {
                $renameResults[] = [
                    'parent' => $parentTitle,
                    'oldName' => $subformFile,
                    'newName' => $newSubformName,
                    'status' => 'collision',
                    'error' => 'Bestand bestaat al'
                ];
                continue;
            }

            $renameResults[] = [
                'parent' => $parentTitle,
                'oldName' => $subformFile,
                'newName' => $newSubformName,
                'oldPath' => $subformPath,
                'newPath' => $newSubformPath,
                'parentFile' => $file,
                'subformIndex' => $idx,
                'status' => 'pending'
            ];

            // Execute rename if in execute mode
            if ($mode === 'rename-execute') {
                // 1. Read subform JSON and update its name field
                $subformData = json_decode(file_get_contents($subformPath), true);
                if ($subformData) {
                    $subformData['name'] = $newSubformName;
                    $jsonContent = json_encode($subformData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $jsonContent = str_replace('    ', "\t", $jsonContent);

                    // 2. Write to new file
                    if (file_put_contents($newSubformPath, $jsonContent)) {
                        // 3. Delete old file (only if different path)
                        if ($subformPath !== $newSubformPath) {
                            unlink($subformPath);
                        }

                        // 4. Update parent's subform reference
                        $data['subforms'][$idx]['form'] = $newSubformName;
                        $parentUpdated = true;

                        $renameResults[count($renameResults) - 1]['status'] = 'success';
                    } else {
                        $renameResults[count($renameResults) - 1]['status'] = 'error';
                        $renameResults[count($renameResults) - 1]['error'] = 'Kon bestand niet schrijven';
                    }
                }
            }
        }

        // Save updated parent form if any subforms were renamed
        if ($parentUpdated && $mode === 'rename-execute') {
            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $jsonContent = str_replace('    ', "\t", $jsonContent);
            file_put_contents($file, $jsonContent);
        }
    }
}

// Output HTML
cma_html_header('Genereer form definities');
?>
<body class="contentbody tools">
<div id="c">
    <h2>Genereer form definities uit repository.mdb</h2>

    <?php if ($mode === 'preview'): ?>
    <p>Dit script genereert JSON form definities voor ontbrekende formulieren.</p>
    <p><strong>Modus:</strong> Preview (geen bestanden worden geschreven)</p>
    <p><a href="?mode=generate" class="btn btn-primary" onclick="event.preventDefault(); var href=this.href; libConfirm('Weet je zeker dat je <?= count($results) ?> form definities wilt genereren?').then(function(ok){if(ok){window.location.href=href}})">Genereer bestanden</a></p>
    <?php else: ?>
    <p><strong>Modus:</strong> Genereren</p>
    <lib-message type="success"><?= count($results) ?> form definities gegenereerd!</lib-message>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <h3 style="color:red">Fouten</h3>
    <ul>
        <?php foreach ($errors as $error): ?>
        <li><?= htmlspecialchars($error) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <h3>Te genereren forms (<?= count($results) ?>)</h3>
    <table class="datatable">
        <thead>
            <tr>
                <th>Form ID</th>
                <th>Naam</th>
                <th>Parent form</th>
                <th>Tabel</th>
                <th>Bestand</th>
                <th>Velden</th>
                <th>Subforms</th>
                <?php if ($mode === 'generate'): ?>
                <th>Status</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $result): ?>
            <tr>
                <td><?= $result['json']['sourceFormId'] ?></td>
                <td><?= htmlspecialchars($result['formName']) ?></td>
                <td><?= htmlspecialchars($result['parentFormName'] ?? '-') ?></td>
                <td><?= htmlspecialchars($result['tableName']) ?></td>
                <td><?= htmlspecialchars($result['filename']) ?></td>
                <td><?= count($result['json']['fields']) ?></td>
                <td><?= count($result['json']['subforms']) ?></td>
                <?php if ($mode === 'generate'): ?>
                <td><?= ($result['written'] ?? false) ? '<span style="color:green">✓</span>' : '<span style="color:red">✗</span>' ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($mode === 'preview' && !empty($results)): ?>
    <h3>Preview eerste form definitie</h3>
    <details open>
        <summary><?= htmlspecialchars($results[0]['filename']) ?></summary>
        <pre style="background:#f5f5f5;padding:10px;overflow:auto;max-height:400px"><?= htmlspecialchars(json_encode($results[0]['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </details>
    <?php endif; ?>

    <hr style="margin:30px 0">

    <h2>Hernoem subforms met parent prefix</h2>
    <p>Dit hernoemt bestaande subform bestanden zodat ze de parent form naam als prefix hebben (bijv. <code>login.json</code> → <code>docenten_login.json</code>).</p>

    <?php if ($mode === 'rename-preview' || $mode === 'rename-execute'): ?>
        <?php if ($mode === 'rename-execute'): ?>
        <lib-message type="success"><?= count(array_filter($renameResults, fn($r) => $r['status'] === 'success')) ?> bestanden hernoemd!</lib-message>
        <?php endif; ?>

        <?php if (!empty($renameResults)): ?>
        <h3>Te hernoemen subforms (<?= count($renameResults) ?>)</h3>
        <table class="datatable">
            <thead>
                <tr>
                    <th>Parent form</th>
                    <th>Oude naam</th>
                    <th>Nieuwe naam</th>
                    <?php if ($mode === 'rename-execute'): ?>
                    <th>Status</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($renameResults as $r): ?>
                <tr>
                    <td><?= htmlspecialchars($r['parent']) ?></td>
                    <td><?= htmlspecialchars($r['oldName']) ?></td>
                    <td><?= htmlspecialchars($r['newName']) ?></td>
                    <?php if ($mode === 'rename-execute'): ?>
                    <td>
                        <?php if ($r['status'] === 'success'): ?>
                            <span style="color:green">✓</span>
                        <?php elseif ($r['status'] === 'collision'): ?>
                            <span style="color:orange">⚠ <?= htmlspecialchars($r['error'] ?? '') ?></span>
                        <?php else: ?>
                            <span style="color:red">✗ <?= htmlspecialchars($r['error'] ?? '') ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($mode === 'rename-preview' && !empty($renameResults)): ?>
        <p style="margin-top:20px">
            <a href="?mode=rename-execute" class="btn btn-primary" onclick="event.preventDefault(); var href=this.href; libConfirm('Weet je zeker dat je <?= count($renameResults) ?> bestanden wilt hernoemen?').then(function(ok){if(ok){window.location.href=href}})">Hernoem bestanden</a>
        </p>
        <?php endif; ?>

        <?php else: ?>
        <lib-message type="info">Geen subforms gevonden die hernoemd moeten worden. Alle subforms hebben al de juiste parent prefix.</lib-message>
        <?php endif; ?>

    <?php else: ?>
    <p><a href="?mode=rename-preview" class="btn">Preview hernoemen</a></p>
    <?php endif; ?>

    <p style="margin-top:20px">
        <a href="../tools.php" class="btn">Terug naar Tools</a>
        <a href="tools_validate_parentfields.php" class="btn">Valideer parentFields</a>
    </p>
</div>
</body>
</html>
<?php
