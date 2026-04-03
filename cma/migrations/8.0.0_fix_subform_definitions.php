<?php
/**
 * Migration: Fix form definitions
 *
 * 1. Normalize database IDs to names (e.g., "6" -> "data")
 * 2. Add missing parentField to subforms by querying database table schema
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

$stats = ['processed' => 0, 'updated' => 0, 'dbNormalized' => 0, 'parentFieldsAdded' => 0];
$changes = [];

foreach ($files as $file) {
    $filename = basename($file);
    $stats['processed']++;

    $content = file_get_contents($file);
    if (!$content || trim($content) === '') continue;

    $def = json_decode($content, true);
    if (!$def) continue;

    $modified = false;
    $fileChanges = [];

    // 1. Normalize database ID to name
    if (isset($def['database']) && isset($dbMap[$def['database']])) {
        $oldDb = $def['database'];
        $def['database'] = $dbMap[$oldDb];
        $fileChanges[] = "database: '$oldDb' -> '{$def['database']}'";
        $stats['dbNormalized']++;
        $modified = true;
    }

    // 2. Add missing parentField to subforms
    if (!empty($def['subforms']) && !empty($def['table'])) {
        $parentTable = $def['table'];

        foreach ($def['subforms'] as $i => &$subform) {
            // Skip if already has parentField
            if (!empty($subform['parentField'])) continue;

            $subformName = $subform['form'] ?? '';
            if (empty($subformName)) continue;

            // Load subform definition to get its table
            $subformPath = null;
            if (file_exists($cmaDir . '/' . $subformName . '.json')) {
                $subformPath = $cmaDir . '/' . $subformName . '.json';
            } elseif (file_exists($siteDir . '/' . $subformName . '.json')) {
                $subformPath = $siteDir . '/' . $subformName . '.json';
            }

            if (!$subformPath) continue;

            $subformContent = file_get_contents($subformPath);
            $subformDef = $subformContent ? json_decode($subformContent, true) : null;
            if (!$subformDef) continue;

            $subformTable = $subformDef['table'] ?? '';
            $subformDb = $subformDef['database'] ?? '';
            if (empty($subformTable) || empty($subformDb)) continue;

            // Normalize subform db for lookup
            $lookupDb = $dbMap[$subformDb] ?? $subformDb;

            // Find FK field in subform table that references parent
            $parentField = findParentFieldInTable($lookupDb, $subformTable, $parentTable);

            if ($parentField) {
                $subform['parentField'] = $parentField;
                $title = $subform['title'] ?? $subformName;
                $fileChanges[] = "$title: parentField='$parentField'";
                $stats['parentFieldsAdded']++;
                $modified = true;
            }
        }
        unset($subform);
    }

    if ($modified) {
        $newContent = json_encode($def, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($file, $newContent . "\n")) {
            $stats['updated']++;
            $changes[$filename] = $fileChanges;
        }
    }
}

// Output
echo "=== Fix Form Definitions ===\n";
echo "Processed: {$stats['processed']}, Updated: {$stats['updated']}\n";
echo "Database IDs normalized: {$stats['dbNormalized']}\n";
echo "ParentFields added: {$stats['parentFieldsAdded']}\n";

if (!empty($changes)) {
    echo "\nChanges:\n";
    foreach ($changes as $file => $fileChanges) {
        echo "  $file:\n";
        foreach ($fileChanges as $c) {
            echo "    - $c\n";
        }
    }
}

return ['success' => true, 'message' => "Updated {$stats['updated']} files"];

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
        $baseName = preg_replace('/^tbl/i', '', $parentTable);
        $variations = [
            strtolower('fk' . $baseName),
            strtolower('fk' . rtrim($baseName, 's')),
            strtolower('fk' . preg_replace('/en$/i', '', $baseName)),
        ];

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
