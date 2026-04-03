<?php
/**
 * Migration: Check subform parentField configuration
 *
 * Scans all form definitions and reports subforms that are missing parentField.
 * Also tries to detect the correct field and offers fixes.
 */

use App\Library\Database;

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

// Database ID to name mapping
$dbMap = [
    '4' => 'rep',
    '5' => 'users',
    '6' => 'data',
];

$cmaDir = $basePath . '/assets/forms/definitions';
$siteDir = dirname($basePath) . '/assets/forms';

$cmaFiles = glob($cmaDir . '/*.json') ?: [];
$siteFiles = glob($siteDir . '/*.json') ?: [];
$files = array_merge($cmaFiles, $siteFiles);

$issues = [];
$fixed = 0;

echo "=== Subform ParentField Check ===\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $formName = str_replace('.json', '', $filename);

    $content = file_get_contents($file);
    if (!$content || trim($content) === '') continue;

    $def = json_decode($content, true);
    if (!$def) continue;

    // Skip if no subforms
    if (empty($def['subforms'])) continue;

    $parentTable = $def['table'] ?? '';
    if (empty($parentTable)) continue;

    $modified = false;

    foreach ($def['subforms'] as $i => &$subform) {
        $subformName = $subform['form'] ?? '';
        $subformTitle = $subform['title'] ?? $subformName;

        // Check if parentField is set
        if (!empty($subform['parentField'])) {
            continue; // Already configured
        }

        // Load subform definition
        $subformPath = null;
        if (file_exists($cmaDir . '/' . $subformName . '.json')) {
            $subformPath = $cmaDir . '/' . $subformName . '.json';
        } elseif (file_exists($siteDir . '/' . $subformName . '.json')) {
            $subformPath = $siteDir . '/' . $subformName . '.json';
        }

        if (!$subformPath) {
            $issues[] = [
                'parent' => $formName,
                'subform' => $subformName,
                'title' => $subformTitle,
                'error' => "Subform definition not found: {$subformName}.json"
            ];
            continue;
        }

        $subformContent = file_get_contents($subformPath);
        $subformDef = $subformContent ? json_decode($subformContent, true) : null;
        if (!$subformDef) {
            $issues[] = [
                'parent' => $formName,
                'subform' => $subformName,
                'title' => $subformTitle,
                'error' => "Could not parse subform JSON"
            ];
            continue;
        }

        $subformTable = $subformDef['table'] ?? '';
        $subformDb = $subformDef['database'] ?? '';
        if (empty($subformTable)) {
            $issues[] = [
                'parent' => $formName,
                'subform' => $subformName,
                'title' => $subformTitle,
                'error' => "Subform has no table defined"
            ];
            continue;
        }

        // Normalize db for lookup
        $lookupDb = $dbMap[$subformDb] ?? $subformDb;

        // Try to find FK field
        $parentField = findParentFieldInTable($lookupDb, $subformTable, $parentTable);

        if ($parentField) {
            // Auto-fix: add the parentField
            $subform['parentField'] = $parentField;
            $modified = true;
            $fixed++;
            echo "  FIXED: {$formName} -> {$subformTitle}: parentField='{$parentField}'\n";
        } else {
            // Report missing with diagnostic info
            $columns = getTableColumns($lookupDb, $subformTable);
            $issues[] = [
                'parent' => $formName,
                'subform' => $subformName,
                'title' => $subformTitle,
                'parentTable' => $parentTable,
                'subformTable' => $subformTable,
                'database' => $lookupDb,
                'columns' => $columns,
                'error' => "No FK field found for parent table '{$parentTable}'"
            ];
        }
    }
    unset($subform);

    // Save if modified
    if ($modified) {
        $newContent = json_encode($def, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($file, $newContent . "\n");
    }
}

// Report
echo "\n=== Results ===\n";
echo "Fixed: {$fixed}\n";
echo "Issues remaining: " . count($issues) . "\n";

if (!empty($issues)) {
    echo "\n=== Issues (need manual fix) ===\n\n";
    foreach ($issues as $issue) {
        echo "Parent form: {$issue['parent']}\n";
        echo "Subform: {$issue['title']} ({$issue['subform']})\n";
        echo "Error: {$issue['error']}\n";
        if (!empty($issue['columns'])) {
            echo "Available columns in {$issue['subformTable']}:\n";
            $fkCandidates = [];
            foreach ($issue['columns'] as $col) {
                if (stripos($col, 'fk') === 0 || stripos($col, '_id') !== false) {
                    $fkCandidates[] = $col;
                }
            }
            if (!empty($fkCandidates)) {
                echo "  FK candidates: " . implode(', ', $fkCandidates) . "\n";
            } else {
                echo "  All columns: " . implode(', ', array_slice($issue['columns'], 0, 15));
                if (count($issue['columns']) > 15) echo " ... (" . count($issue['columns']) . " total)";
                echo "\n";
            }
        }
        echo "\n";
    }
}

return ['success' => true, 'message' => "Fixed {$fixed}, " . count($issues) . " issues remaining"];

/**
 * Find FK field in table that references parent table
 */
function findParentFieldInTable(string $dbId, string $table, string $parentTable): ?string
{
    try {
        $conn = Database::getConnection($dbId);
        if (!$conn) return null;

        // Get columns from table schema
        $columns = Database::getTableSchema($conn, $table);
        if (!$columns || $columns->EOF) return null;

        // Build FK name variations from parent table
        // tblDeelnemers -> fkDeelnemer, fkDeelnemers, Deelnemers_ID, etc.
        $baseName = preg_replace('/^tbl/i', '', $parentTable);
        $variations = [
            strtolower('fk' . $baseName),               // fkDeelnemers
            strtolower('fk' . rtrim($baseName, 's')),   // fkDeelnemer
            strtolower('fk' . preg_replace('/en$/i', '', $baseName)), // fkDeelnem (Dutch plural -en)
            strtolower($baseName . '_id'),              // Deelnemers_ID
            strtolower($baseName . 'id'),               // DeelnemersID
            strtolower('fk_' . $baseName),              // fk_Deelnemers
        ];

        // Also try ID of parent
        $variations[] = strtolower('fk' . $parentTable);
        $variations[] = strtolower($parentTable . '_id');

        // Check each column
        while (!$columns->EOF) {
            $colName = $columns->fields['COLUMN_NAME'] ?? '';
            $lowerCol = strtolower($colName);

            foreach ($variations as $v) {
                if ($lowerCol === $v) {
                    return $colName;
                }
            }
            $columns->MoveNext();
        }
    } catch (\Exception $e) {
        // Silent fail
    }

    return null;
}

/**
 * Get all column names from a table
 */
function getTableColumns(string $dbId, string $table): array
{
    $cols = [];
    try {
        $conn = Database::getConnection($dbId);
        if (!$conn) return $cols;

        $columns = Database::getTableSchema($conn, $table);
        if (!$columns || $columns->EOF) return $cols;

        while (!$columns->EOF) {
            $cols[] = $columns->fields['COLUMN_NAME'] ?? '';
            $columns->MoveNext();
        }
    } catch (\Exception $e) {
        // Silent fail
    }
    return $cols;
}
