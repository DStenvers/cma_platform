<?php
/**
 * Database Sync Tool
 * Compares form field definitions with actual database columns.
 * Uses Database::getSchema() for Access/ODBC support.
 */
use App\Library\Database;
use App\Library\Request;
use App\Library\Response;
use Cma\CmaRepository;
use Cma\JsonFormLoader;
use Cma\SchemaHelper;
use Cma\ToolbarHelper;
use Cma\Services\MigrationService;

require_once __DIR__ . '/../bootstrap.inc';
Response::noCache();

// Handle form submission
$action = Request::post('action', '');
$messages = [];

if ($action === 'sync') {
    $updates = Request::post('updates', []);
    if (!empty($updates)) {
        // Group actions by form and type
        $formActions = [];
        $dbDropColumns = [];

        foreach ($updates as $formName => $fields) {
            foreach ($fields as $fieldData) {
                $fieldName = $fieldData['name'] ?? '';
                $updateType = $fieldData['type'] ?? '';
                if (!$fieldName) continue;

                if ($updateType === 'dropColumn') {
                    // Database column drop - group by database/table
                    $dbName = $fieldData['database'] ?? 'data';
                    $tableName = $fieldData['table'] ?? '';
                    if ($tableName) {
                        $key = $dbName . '.' . $tableName;
                        if (!isset($dbDropColumns[$key])) {
                            $dbDropColumns[$key] = [
                                'database' => $dbName,
                                'table' => $tableName,
                                'columns' => []
                            ];
                        }
                        $dbDropColumns[$key]['columns'][] = $fieldName;
                    }
                } else {
                    // Form definition changes
                    if (!isset($formActions[$formName])) {
                        $formActions[$formName] = [];
                    }
                    $formActions[$formName][] = $fieldData;
                }
            }
        }

        // Process database column drops first
        foreach ($dbDropColumns as $key => $dropInfo) {
            $conn = Database::getNamedConnection($dropInfo['database']);
            if ($conn === null) {
                $messages[] = ['type' => 'error', 'text' => "Kan geen verbinding maken met database '{$dropInfo['database']}'"];
                continue;
            }

            foreach ($dropInfo['columns'] as $columnName) {
                $result = Database::dropColumnPDO($conn, $dropInfo['table'], $columnName);
                if ($result['success']) {
                    $msg = $result['message'] ?? 'Kolom verwijderd';
                    $messages[] = ['type' => 'success', 'text' => "Kolom '$columnName' verwijderd uit {$dropInfo['table']}: $msg"];
                } else {
                    $messages[] = ['type' => 'error', 'text' => "Fout bij verwijderen kolom '$columnName': " . ($result['error'] ?? 'onbekende fout')];
                }
            }
        }

        // Process form definition changes
        foreach ($formActions as $formName => $fields) {
            $formDef = JsonFormLoader::loadRaw($formName);
            if (!$formDef) {
                $messages[] = ['type' => 'error', 'text' => "Formulier '$formName' niet gevonden"];
                continue;
            }

            $modified = false;
            $addedFields = [];
            $updatedFields = [];
            $deletedFields = [];

            foreach ($fields as $fieldData) {
                $fieldName = $fieldData['name'] ?? '';
                $updateType = $fieldData['type'] ?? '';

                if (!$fieldName) continue;

                if ($updateType === 'add') {
                    // Add new field
                    $newField = [
                        'name' => $fieldName,
                        'type' => $fieldData['fieldType'] ?? 'textbox',
                        'caption' => $fieldData['caption'] ?? ucfirst($fieldName)
                    ];
                    if (!empty($fieldData['maxLength'])) {
                        $newField['maxLength'] = (int)$fieldData['maxLength'];
                    }
                    if (isset($fieldData['defaultValue']) && $fieldData['defaultValue'] !== '') {
                        $newField['defaultValue'] = $fieldData['defaultValue'];
                    }
                    $formDef['fields'][] = $newField;
                    $addedFields[] = $fieldName;
                    $modified = true;
                } elseif ($updateType === 'update') {
                    // Update existing field
                    foreach ($formDef['fields'] as &$field) {
                        if (strtolower($field['name'] ?? '') === strtolower($fieldName)) {
                            if (!empty($fieldData['maxLength'])) {
                                $field['maxLength'] = (int)$fieldData['maxLength'];
                            }
                            if (isset($fieldData['defaultValue'])) {
                                if ($fieldData['defaultValue'] === '') {
                                    unset($field['defaultValue']);
                                } else {
                                    $field['defaultValue'] = $fieldData['defaultValue'];
                                }
                            }
                            $updatedFields[] = $fieldName;
                            $modified = true;
                            break;
                        }
                    }
                    unset($field);
                } elseif ($updateType === 'delete') {
                    // Delete field from form definition
                    $formDef['fields'] = array_values(array_filter($formDef['fields'], function($field) use ($fieldName) {
                        return strtolower($field['name'] ?? '') !== strtolower($fieldName);
                    }));
                    $deletedFields[] = $fieldName;
                    $modified = true;
                }
            }

            if ($modified) {
                if (JsonFormLoader::save($formName, $formDef)) {
                    $msg = "Formulier '$formName' bijgewerkt: ";
                    $parts = [];
                    if (count($addedFields) > 0) {
                        $parts[] = count($addedFields) . ' veld(en) toegevoegd';
                    }
                    if (count($updatedFields) > 0) {
                        $parts[] = count($updatedFields) . ' veld(en) gewijzigd';
                    }
                    if (count($deletedFields) > 0) {
                        $parts[] = count($deletedFields) . ' veld(en) verwijderd uit formulier';
                    }
                    $messages[] = ['type' => 'success', 'text' => $msg . implode(', ', $parts)];
                } else {
                    $messages[] = ['type' => 'error', 'text' => "Fout bij opslaan van '$formName'"];
                }
            }
        }
    }
}

cma_html_header('CMA - Database Sync', '', false);
ToolbarHelper::writeJS();
echo '</HEAD><BODY class="contentbody tools tool-db-sync">';
ToolbarHelper::report('Database veld synchronisatie', false, false, false, false, 'Synchroniseer JSON formulier-velden met database kolommen');
echo '<div id="c" class="tools">';

// Show messages
foreach ($messages as $msg) {
    $type = $msg['type'] === 'success' ? 'success' : 'error';
    echo '<lib-message type="' . $type . '">' . htmlspecialchars($msg['text']) . '</lib-message>';
}

// Add styles
echo '<style>
    .sync-mismatch { background-color: #fff3cd !important; }
    .sync-cell-mismatch { color: #856404; font-weight: bold; }
    .action-description { font-size: var(--font-size-sm); line-height: 1.6; }
    .mismatch-detail { display: block; margin: 2px 0; }
    .mismatch-detail .old-value { color: #dc3545; text-decoration: line-through; }
    .mismatch-detail .new-value { color: #28a745; font-weight: bold; }
    .sync-ok { background-color: #d4edda; }
    .sync-missing { background-color: #f8d7da; }
    .sync-orphan { background-color: #f5c6cb; }
    .form-section { margin-bottom: 20px; border: 1px solid #ddd; border-radius: 5px; }
    .form-section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 15px;
        cursor: pointer;
        user-select: none;
        background: #f8f9fa;
        border-radius: 5px 5px 0 0;
    }
    .form-section.collapsed .form-section-header { border-radius: 5px; }
    .form-section-header:hover { background: #f0f0f0; }
    .form-section h3 { margin: 0; font-size: var(--font-size-md); flex: 1; }
    .form-section-chevron {
        font-size: var(--font-size-lg);
        transition: transform 0.2s ease;
        color: #666;
    }
    .form-section.collapsed .form-section-chevron { transform: rotate(-90deg); }
    .form-section-content { padding: 15px; border-top: 1px solid #ddd; }
    .form-section.collapsed .form-section-content { display: none; }
    .form-section.has-issues { border-color: #ffc107; }
    .form-section.has-issues .form-section-header { background: #fff8e6; }
    .form-section.has-errors { border-color: #dc3545; }
    .form-section.has-errors .form-section-header { background: #fff5f5; }
    .summary-box { padding: 10px; background: #f5f5f5; border: 1px solid #ccc; margin-top: 10px; font-size: var(--font-size-sm); border-radius: 4px; }
    .sync-table { width: 100%; font-size: var(--font-size-xs); border-collapse: collapse; }
    .sync-table th, .sync-table td { padding: 4px 6px; border: 1px solid #ddd; }
    .sync-table th { background: #f5f5f5; text-align: left; }
    .select-all { font-size: var(--font-size-xs); cursor: pointer; color: #007bff; }
    .action-radios { display: flex; gap: 8px; align-items: center; }
    .action-radios label { display: flex; align-items: center; gap: 3px; cursor: pointer; font-size: var(--font-size-2xs); white-space: nowrap; }
    .action-radios input[type="radio"] { margin: 0; cursor: pointer; }
    .action-add { color: #28a745; }
    .action-dropdb { color: #dc3545; }
</style>';

// Get all forms
$forms = JsonFormLoader::listForms();
$formsWithTables = [];

foreach ($forms as $formName) {
    $formDef = JsonFormLoader::loadRaw($formName);
    if ($formDef && !empty($formDef['table'])) {
        $formsWithTables[$formName] = [
            'title' => $formDef['title'] ?? $formName,
            'table' => $formDef['table'],
            'database' => $formDef['database'] ?? 'data',
            'fields' => $formDef['fields'] ?? []
        ];
    }
}

// Sort by title
uasort($formsWithTables, fn($a, $b) => strcasecmp($a['title'], $b['title']));

// Global statistics
$totalForms = count($formsWithTables);
$formsOk = 0;
$formsWithIssues = 0;
$formsSkipped = 0;
$totalMissingFields = 0;
$totalOrphanedFields = 0;
$totalLengthMismatches = 0;
$totalDefaultMismatches = 0;

echo '<p>Totaal ' . $totalForms . ' formulieren met tabeldefinities gevonden.</p>';

// Start form
echo '<form method="POST" action="tools_db_sync.php" id="syncForm">';
echo '<input type="hidden" name="action" value="sync">';

// Cache connections
$connectionCache = [];
$formIndex = 0;

foreach ($formsWithTables as $formName => $formInfo) {
    $tableName = $formInfo['table'];
    $databaseName = $formInfo['database'];

    // Try to get connection
    if (!isset($connectionCache[$databaseName])) {
        try {
            $conn = Database::getNamedConnection($databaseName);
            $connectionCache[$databaseName] = $conn ?: false;
        } catch (\Exception $e) {
            $connectionCache[$databaseName] = false;
        }
    }

    $conn = $connectionCache[$databaseName];

    if ($conn === false) {
        $formsSkipped++;
        continue;
    }

    // Get database columns via centralized SchemaHelper (include hidden columns for full comparison)
    $dbColumns = [];
    try {
        $columns = SchemaHelper::getColumns($databaseName, $tableName, true);
        foreach ($columns as $col) {
            $dbColumns[strtolower($col['name'])] = [
                'name' => $col['name'],
                'type' => $col['dataType'],
                'length' => $col['length'],
                'nullable' => $col['nullable'],
                'default' => $col['default'],
                'ordinal' => $col['ordinal']
            ];
        }
    } catch (\Exception $e) {
        $formsSkipped++;
        continue;
    }

    if (empty($dbColumns)) {
        $formsSkipped++;
        continue;
    }

    // Get form fields (skip fields without a name or internal fields or custom fields)
    $formFields = [];
    foreach ($formInfo['fields'] as $field) {
        $fieldName = $field['name'] ?? null;
        // Skip fields without a name (labels, separators, groups, etc.)
        if ($fieldName === null || $fieldName === '' || trim($fieldName) === '') {
            continue;
        }
        // Skip internal/display-only fields (starting with _)
        if (substr($fieldName, 0, 1) === '_') {
            continue;
        }
        
        if ( $field['type'] === 'custom' ) {
            continue;
        }
        $formFields[strtolower($fieldName)] = [
            'name' => $fieldName,
            'type' => $field['type'] ?? 'textbox',
            'caption' => $field['caption'] ?? $fieldName,
            'maxLength' => $field['maxLength'] ?? null,
            'defaultValue' => $field['defaultValue'] ?? null,
            'dataType' => $field['dataType'] ?? null
        ];
    }

    // Compare
    $inDbNotInForm = [];
    $inFormNotInDb = [];
    $inBoth = [];
    $lengthMismatches = [];
    $defaultMismatches = [];

    foreach ($dbColumns as $key => $col) {
        if (isset($formFields[$key])) {
            $inBoth[$key] = ['db' => $col, 'form' => $formFields[$key]];

            $dbLen = $col['length'];
            $formLen = $formFields[$key]['maxLength'];
            if ($dbLen && $formLen && (int)$dbLen !== (int)$formLen) {
                $lengthMismatches[$key] = ['db' => $dbLen, 'form' => $formLen];
            } elseif ($dbLen && !$formLen && $dbLen < 1000) {
                $lengthMismatches[$key] = ['db' => $dbLen, 'form' => null];
            }

            $dbDefault = cleanDefaultValue($col['default']);
            $formDefault = $formFields[$key]['defaultValue'] !== null ? (string)$formFields[$key]['defaultValue'] : null;
            if ($dbDefault !== null && $formDefault === null) {
                $defaultMismatches[$key] = ['db' => $dbDefault, 'form' => null];
            } elseif ($dbDefault !== null && $formDefault !== null && $dbDefault !== $formDefault) {
                $defaultMismatches[$key] = ['db' => $dbDefault, 'form' => $formDefault];
            }
        } else {
            if (!in_array(strtolower($col['name']), ['id', 'lastmodified', 'lastmodifiedby', 'createdby', 'createdate'])) {
                $inDbNotInForm[$key] = $col;
            }
        }
    }

    foreach ($formFields as $key => $field) {
        if (!isset($dbColumns[$key])) {
            $inFormNotInDb[$key] = $field;
        }
    }

    // Update stats
    $totalMissingFields += count($inDbNotInForm);
    $totalOrphanedFields += count($inFormNotInDb);
    $totalLengthMismatches += count($lengthMismatches);
    $totalDefaultMismatches += count($defaultMismatches);

    $hasIssues = count($inDbNotInForm) > 0 || count($lengthMismatches) > 0 || count($defaultMismatches) > 0;
    $hasErrors = count($inFormNotInDb) > 0;

    if ($hasErrors) {
        $formsWithIssues++;
    } elseif ($hasIssues) {
        $formsWithIssues++;
    } else {
        $formsOk++;
    }

    if (!$hasIssues && !$hasErrors) {
        continue;
    }

    $sectionClass = 'form-section';
    if ($hasErrors) $sectionClass .= ' has-errors';
    elseif ($hasIssues) $sectionClass .= ' has-issues';

    $formIndex++;
    $safeFormName = htmlspecialchars($formName);
    $detailId = 'details_' . $formIndex;

    echo '<div class="' . $sectionClass . ' collapsed" id="section_' . $formIndex . '">';

    // Quick summary for header
    $issues = [];
    if (count($inDbNotInForm) > 0) $issues[] = count($inDbNotInForm) . ' ontbrekend';
    if (count($inFormNotInDb) > 0) $issues[] = '<span style="color:red;">' . count($inFormNotInDb) . ' orphan</span>';
    if (count($lengthMismatches) > 0) $issues[] = count($lengthMismatches) . ' lengte';
    if (count($defaultMismatches) > 0) $issues[] = count($defaultMismatches) . ' default';
    $issuesSummary = implode(' | ', $issues);

    echo '<div class="form-section-header" onclick="toggleSection(\'section_' . $formIndex . '\')">';
    echo '<h3>' . htmlspecialchars($formInfo['title']) . ' <small style="color:#666; font-weight:normal;">(' . htmlspecialchars($tableName) . ' @ ' . htmlspecialchars($databaseName) . ')</small>';
    echo ' <span style="margin-left:15px; font-size:var(--font-size-sm); font-weight:normal;">' . $issuesSummary . '</span></h3>';
    echo '<span class="form-section-chevron lnr lnr-chevron-down"></span>';
    echo '</div>';

    echo '<div class="form-section-content">';

    // Table
    echo '<table class="sync-table">';
    echo '<thead><tr>';
    echo '<th style="width:30px;"><input type="checkbox" onclick="toggleAll(this, \'' . $safeFormName . '\')" title="Selecteer alles"></th>';
    echo '<th>Actie</th>';
    echo '<th>Veldnaam</th>';
    echo '<th>DB Type</th>';
    echo '<th>DB Len</th>';
    echo '<th>Form Len</th>';
    echo '<th>DB Default</th>';
    echo '<th>Form Default</th>';
    echo '</tr></thead><tbody>';

    $fieldIndex = 0;

    // Mismatched fields (update)
    foreach ($inBoth as $key => $data) {
        $hasLenMismatch = isset($lengthMismatches[$key]);
        $hasDefMismatch = isset($defaultMismatches[$key]);
        if (!$hasLenMismatch && !$hasDefMismatch) continue;

        $dbLen = $data['db']['length'];
        $formLen = $data['form']['maxLength'];
        $dbDefault = cleanDefaultValue($data['db']['default']);
        $formDefault = $data['form']['defaultValue'];

        $inputName = "updates[{$formName}][{$fieldIndex}]";

        echo '<tr class="sync-mismatch">';
        echo '<td><input type="checkbox" name="' . $inputName . '[name]" value="' . htmlspecialchars($data['db']['name']) . '" data-form="' . $safeFormName . '">';
        echo '<input type="hidden" name="' . $inputName . '[type]" value="update" disabled>';
        if ($hasLenMismatch) {
            echo '<input type="hidden" name="' . $inputName . '[maxLength]" value="' . (int)$dbLen . '" disabled>';
        }
        if ($hasDefMismatch) {
            echo '<input type="hidden" name="' . $inputName . '[defaultValue]" value="' . htmlspecialchars($dbDefault ?? '') . '" disabled>';
        }
        echo '</td>';

        // Build verbose action description
        $actionParts = [];
        if ($hasLenMismatch) {
            $formLenDisplay = $formLen ?: '(leeg)';
            $dbLenDisplay = $dbLen ?: '(leeg)';
            $actionParts[] = '<span class="mismatch-detail" title="Lengte aanpassen">Lengte: <span class="old-value">' . $formLenDisplay . '</span> → <span class="new-value">' . $dbLenDisplay . '</span></span>';
        }
        if ($hasDefMismatch) {
            $formDefDisplay = ($formDefault !== null && $formDefault !== '') ? "'" . htmlspecialchars($formDefault) . "'" : '(leeg)';
            $dbDefDisplay = ($dbDefault !== null && $dbDefault !== '') ? "'" . htmlspecialchars($dbDefault) . "'" : '(leeg)';
            $actionParts[] = '<span class="mismatch-detail" title="Standaardwaarde aanpassen">Default: <span class="old-value">' . $formDefDisplay . '</span> → <span class="new-value">' . $dbDefDisplay . '</span></span>';
        }
        echo '<td class="action-description">' . implode('<br>', $actionParts) . '</td>';

        echo '<td>' . htmlspecialchars($data['db']['name']) . '</td>';
        echo '<td>' . htmlspecialchars($data['db']['type']) . '</td>';
        echo '<td>' . ($dbLen ?: '-') . '</td>';
        echo '<td style="' . ($hasLenMismatch ? 'color:#856404;font-weight:bold;' : '') . '">' . ($formLen ?: '-') . '</td>';
        echo '<td>' . htmlspecialchars($dbDefault ?? '-') . '</td>';
        echo '<td style="' . ($hasDefMismatch ? 'color:#856404;font-weight:bold;' : '') . '">' . htmlspecialchars($formDefault ?? '-') . '</td>';
        echo '</tr>';
        $fieldIndex++;
    }

    // Missing fields (in DB, not in form - can add to form OR delete from DB)
    foreach ($inDbNotInForm as $key => $col) {
        $dbDefault = cleanDefaultValue($col['default']);
        $fieldType = mapDbTypeToFieldType($col['type']);
        $caption = ucfirst(preg_replace('/([a-z])([A-Z])/', '$1 $2', $col['name']));

        $inputName = "updates[{$formName}][{$fieldIndex}]";
        $radioName = "action_{$formName}_{$fieldIndex}";

        echo '<tr class="sync-missing">';
        // Radio buttons for action selection
        echo '<td colspan="2" class="action-radios">';
        // Hidden field for the actual field name - always present but only submitted when an action is selected
        echo '<input type="hidden" name="' . $inputName . '[name]" value="' . htmlspecialchars($col['name']) . '" disabled class="field-name-input">';
        // Hidden fields for add action data
        echo '<input type="hidden" name="' . $inputName . '[type]" value="" disabled class="action-type-input">';
        echo '<input type="hidden" name="' . $inputName . '[fieldType]" value="' . $fieldType . '" disabled class="add-data">';
        echo '<input type="hidden" name="' . $inputName . '[caption]" value="' . htmlspecialchars($caption) . '" disabled class="add-data">';
        if ($col['length'] && $col['length'] < 1000) {
            echo '<input type="hidden" name="' . $inputName . '[maxLength]" value="' . (int)$col['length'] . '" disabled class="add-data">';
        }
        if ($dbDefault !== null) {
            echo '<input type="hidden" name="' . $inputName . '[defaultValue]" value="' . htmlspecialchars($dbDefault) . '" disabled class="add-data">';
        }
        // Hidden fields for dropColumn action data
        echo '<input type="hidden" name="' . $inputName . '[database]" value="' . htmlspecialchars($databaseName) . '" disabled class="drop-data">';
        echo '<input type="hidden" name="' . $inputName . '[table]" value="' . htmlspecialchars($tableName) . '" disabled class="drop-data">';

        // Radio buttons
        echo '<label class="action-add"><input type="radio" name="' . $radioName . '" value="add" data-form="' . $safeFormName . '" data-field-index="' . $fieldIndex . '" onchange="handleActionRadio(this)"> + form</label>';
        echo '<label class="action-dropdb"><input type="radio" name="' . $radioName . '" value="dropColumn" data-form="' . $safeFormName . '" data-field-index="' . $fieldIndex . '" data-drop-db="1" onchange="handleActionRadio(this)"> - DB</label>';
        echo '</td>';
        echo '<td><strong>' . htmlspecialchars($col['name']) . '</strong></td>';
        echo '<td>' . htmlspecialchars($col['type']) . '</td>';
        echo '<td>' . ($col['length'] ?: '-') . '</td>';
        echo '<td>-</td>';
        echo '<td>' . htmlspecialchars($dbDefault ?? '-') . '</td>';
        echo '<td>-</td>';
        echo '</tr>';
        $fieldIndex++;
    }

    // Orphaned fields (in form, not in DB - can be deleted from form)
    foreach ($inFormNotInDb as $key => $field) {
        $inputName = "updates[{$formName}][{$fieldIndex}]";

        echo '<tr class="sync-orphan">';
        echo '<td><input type="checkbox" name="' . $inputName . '[name]" value="' . htmlspecialchars($field['name']) . '" data-form="' . $safeFormName . '" data-delete="1">';
        echo '<input type="hidden" name="' . $inputName . '[type]" value="delete" disabled>';
        echo '</td>';
        echo '<td><strong>- uit form</strong></td>';
        echo '<td><strong>' . htmlspecialchars($field['name']) . '</strong></td>';
        echo '<td><em>(' . htmlspecialchars($field['type']) . ')</em></td>';
        echo '<td>-</td>';
        echo '<td>' . ($field['maxLength'] ?: '-') . '</td>';
        echo '<td>-</td>';
        echo '<td>' . htmlspecialchars($field['defaultValue'] ?? '-') . '</td>';
        echo '</tr>';
        $fieldIndex++;
    }

    echo '</tbody></table>';
    echo '</div>'; // form-section-content
    echo '</div>'; // form-section
}

// Submit button
echo '<div style="margin-top:20px; padding:15px; background:#f8f9fa; border:1px solid #ddd; border-radius:4px;">';
echo '<button type="submit" class="btn btn-primary" id="btnSync" disabled>Geselecteerde velden synchroniseren</button>';
echo ' <span id="selectedCount" style="margin-left:10px; color:#666;">0 velden geselecteerd</span>';
echo '</div>';

// Global summary
echo '<div class="summary-box" style="margin-top:20px; font-size:var(--font-size-md);">';
echo '<strong>Totaal overzicht:</strong><br>';
echo 'Formulieren OK: <span style="color:green;">' . $formsOk . '</span> | ';
echo 'Met problemen: <span style="color:orange;">' . $formsWithIssues . '</span> | ';
echo 'Overgeslagen: <span style="color:gray;">' . $formsSkipped . '</span><br>';
echo 'Ontbrekende velden: ' . $totalMissingFields . ' | ';
$orphanColor = $totalOrphanedFields > 0 ? 'color:red;' : '';
echo 'Orphaned: <span style="' . $orphanColor . '">' . $totalOrphanedFields . '</span> | ';
echo 'Lengte: ' . $totalLengthMismatches . ' | ';
echo 'Default: ' . $totalDefaultMismatches;
echo '</div>';

echo '</form>';

// JavaScript
echo '<script>
function toggleSection(sectionId) {
    var section = document.getElementById(sectionId);
    if (section) {
        section.classList.toggle("collapsed");
    }
}

function toggleAll(masterCheckbox, formName) {
    var checkboxes = document.querySelectorAll("input[type=checkbox][data-form=\"" + formName + "\"]");
    checkboxes.forEach(function(cb) {
        cb.checked = masterCheckbox.checked;
        toggleHiddenFields(cb);
    });
    updateSelectedCount();
}

function toggleHiddenFields(checkbox) {
    var row = checkbox.closest("tr");
    var hiddenInputs = row.querySelectorAll("input[type=hidden]");
    hiddenInputs.forEach(function(input) {
        input.disabled = !checkbox.checked;
    });
}

function handleActionRadio(radio) {
    var row = radio.closest("tr");
    var actionType = radio.value;
    var isSelected = radio.checked;

    // Get all hidden inputs in this row
    var fieldNameInput = row.querySelector(".field-name-input");
    var actionTypeInput = row.querySelector(".action-type-input");
    var addDataInputs = row.querySelectorAll(".add-data");
    var dropDataInputs = row.querySelectorAll(".drop-data");

    if (isSelected) {
        // Enable the field name and action type
        fieldNameInput.disabled = false;
        actionTypeInput.disabled = false;
        actionTypeInput.value = actionType;

        // Enable appropriate hidden fields based on action
        if (actionType === "add") {
            addDataInputs.forEach(function(input) { input.disabled = false; });
            dropDataInputs.forEach(function(input) { input.disabled = true; });
        } else if (actionType === "dropColumn") {
            addDataInputs.forEach(function(input) { input.disabled = true; });
            dropDataInputs.forEach(function(input) { input.disabled = false; });
        }
    } else {
        // Disable all hidden inputs
        fieldNameInput.disabled = true;
        actionTypeInput.disabled = true;
        actionTypeInput.value = "";
        addDataInputs.forEach(function(input) { input.disabled = true; });
        dropDataInputs.forEach(function(input) { input.disabled = true; });
    }

    updateSelectedCount();
}

function updateSelectedCount() {
    // Count checkboxes (for update/delete form actions)
    var checkedCheckboxes = document.querySelectorAll("input[type=checkbox][data-form]:checked").length;

    // Count selected radio buttons (for add/dropColumn actions)
    var selectedRadios = document.querySelectorAll("input[type=radio][data-form]:checked").length;

    var total = checkedCheckboxes + selectedRadios;

    // Count deletions (form definition)
    var deleteFormCount = document.querySelectorAll("input[type=checkbox][data-form][data-delete]:checked").length;

    // Count DB drops
    var dropDbCount = document.querySelectorAll("input[type=radio][data-drop-db]:checked").length;

    var text = total + " velden geselecteerd";
    var warnings = [];
    if (deleteFormCount > 0) {
        warnings.push(deleteFormCount + " uit form");
    }
    if (dropDbCount > 0) {
        warnings.push(dropDbCount + " uit DB");
    }
    if (warnings.length > 0) {
        text += " (" + warnings.join(", ") + " te verwijderen)";
    }

    document.getElementById("selectedCount").textContent = text;
    document.getElementById("btnSync").disabled = total === 0;
}

function confirmSubmit(callback) {
    var deleteFormCount = document.querySelectorAll("input[type=checkbox][data-form][data-delete]:checked").length;
    var dropDbCount = document.querySelectorAll("input[type=radio][data-drop-db]:checked").length;

    if (deleteFormCount > 0 || dropDbCount > 0) {
        var msg = "Let op:\n\n";
        if (deleteFormCount > 0) {
            msg += "• " + deleteFormCount + " veld(en) worden verwijderd uit formulierdefinitie(s)\n";
        }
        if (dropDbCount > 0) {
            msg += "• " + dropDbCount + " kolom(men) worden PERMANENT verwijderd uit de database\n";
        }
        msg += "\nWeet je zeker dat je door wilt gaan?";

        // Use libConfirm if available, otherwise fall back to native confirm
        if (typeof libConfirm === "function") {
            libConfirm(msg, {
                title: "Bevestig synchronisatie",
                confirmText: "Ja, doorgaan",
                cancelText: "Annuleren",
                onConfirm: function() {
                    callback(true);
                },
                onCancel: function() {
                    callback(false);
                }
            });
        } else {
            libConfirm(msg).then(function(ok) {
                callback(ok);
            });
        }
        return; // async path
    }
    callback(true);
}

// Add event listeners for checkboxes
document.querySelectorAll("input[type=checkbox][data-form]").forEach(function(cb) {
    cb.addEventListener("change", function() {
        toggleHiddenFields(this);
        updateSelectedCount();
    });
});

document.getElementById("syncForm").addEventListener("submit", function(e) {
    e.preventDefault();
    var form = this;
    confirmSubmit(function(confirmed) {
        if (confirmed) {
            form.submit();
        }
    });
});
</script>';

echo '</div></body></html>';

/**
 * Map database data type to form field type
 */
function mapDbTypeToFieldType(string $dbType): string
{
    $dbType = strtolower($dbType);
    if (strpos($dbType, 'char') !== false || strpos($dbType, 'text') !== false || $dbType === 'string') return 'textbox';
    if (in_array($dbType, ['int', 'integer', 'smallint', 'tinyint', 'bigint', 'long', 'short'])) return 'textbox';
    if (in_array($dbType, ['decimal', 'numeric', 'float', 'double', 'real', 'currency', 'money'])) return 'textbox';
    if (in_array($dbType, ['bit', 'boolean', 'yesno', 'bool'])) return 'checkbox';
    if (strpos($dbType, 'date') !== false || strpos($dbType, 'time') !== false) return 'date';
    if (in_array($dbType, ['memo', 'longtext', 'ntext', 'longchar'])) return 'textarea';
    if (strpos($dbType, 'binary') !== false || strpos($dbType, 'image') !== false || strpos($dbType, 'blob') !== false) return 'file';
    return 'textbox';
}

/**
 * Clean database default value for display/comparison
 */
function cleanDefaultValue($value): ?string
{
    if ($value === null || $value === '') return null;
    $value = (string)$value;
    while (preg_match('/^\((.+)\)$/', $value, $matches)) $value = $matches[1];
    if (preg_match("/^N'(.*)'\$/", $value, $matches)) $value = $matches[1];
    if (preg_match("/^'(.*)'\$/", $value, $matches)) $value = $matches[1];
    $valueLower = strtolower($value);
    if ($valueLower === 'getdate()' || $valueLower === 'now()' || $valueLower === 'date()') return 'NOW';
    if ($valueLower === 'newid()' || $valueLower === 'uuid()') return 'UUID';
    if ($value === '0' || $valueLower === 'false' || $valueLower === 'no') return '0';
    if ($value === '1' || $valueLower === 'true' || $valueLower === 'yes' || $value === '-1') return '1';
    if (trim($value) === '') return null;
    return $value;
}
