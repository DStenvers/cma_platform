<?php
/**
 * Diagnostic script to check form definitions
 *
 * This script checks:
 * 1. JSON form definitions exist and are valid
 * 2. sourceFormId mapping works correctly
 * 3. table field is populated
 * 4. GetFormDef() returns proper data
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/bootstrap.inc';

use Cma\JsonFormLoader;

echo "<pre style='font-family: monospace;'>";
echo "=== CMA Form Definition Diagnostic ===\n\n";

// 1. Check JSON definitions directory
$defsDir = dirname(__DIR__) . '/assets/forms/definitions';
echo "1. Definitions directory: $defsDir\n";
echo "   Exists: " . (is_dir($defsDir) ? 'YES' : 'NO') . "\n\n";

// 2. Count JSON files
$jsonFiles = glob($defsDir . '/*.json');
echo "2. JSON files found: " . count($jsonFiles) . "\n\n";

// 3. Check formId to name mapping
echo "3. Testing loadFormIdToNameMapping():\n";
$mapping = loadFormIdToNameMapping();
echo "   Mapped forms: " . count($mapping) . "\n\n";

// 4. Check a few specific forms
$testFormIds = [68, 61, 51, 58];  // opleidingen, deelnemers, users, groups
echo "4. Testing specific FormIDs:\n";

foreach ($testFormIds as $formId) {
    echo "\n   FormID: $formId\n";

    // Check mapping
    $formName = $mapping[$formId] ?? null;
    echo "   -> JSON name: " . ($formName ?? '(not found)') . "\n";

    if ($formName) {
        // Load raw JSON
        $rawJson = JsonFormLoader::loadRaw($formName);
        if ($rawJson) {
            echo "   -> table: " . ($rawJson['table'] ?? '(empty)') . "\n";
            echo "   -> database: " . ($rawJson['database'] ?? '(empty)') . "\n";
            echo "   -> title: " . ($rawJson['title'] ?? '(empty)') . "\n";
        } else {
            echo "   -> ERROR: Could not load raw JSON\n";
        }

        // Load via GetFormDef (legacy format)
        $formDef = GetFormDef($formId);
        if ($formDef) {
            echo "   -> GetFormDef _json.table: " . ($formDef['_json']['table'] ?? '(empty)') . "\n";
            echo "   -> GetFormDef Q_SQLTABLENAME: " . ($formDef[Q_SQLTABLENAME][0] ?? '(empty)') . "\n";
        } else {
            echo "   -> ERROR: GetFormDef returned null\n";
        }
    }
}

// 5. Check for empty table fields
echo "\n\n5. Checking for forms with empty table field:\n";
$emptyTableForms = [];
foreach ($jsonFiles as $file) {
    $content = @file_get_contents($file);
    if ($content === false) continue;

    $json = json_decode($content, true);
    if ($json === null) {
        echo "   WARNING: Invalid JSON: " . basename($file) . "\n";
        continue;
    }

    $table = $json['table'] ?? '';
    if (empty($table)) {
        $emptyTableForms[] = basename($file, '.json');
    }
}

if (empty($emptyTableForms)) {
    echo "   All forms have table defined.\n";
} else {
    echo "   Forms with empty table (" . count($emptyTableForms) . "):\n";
    foreach ($emptyTableForms as $name) {
        echo "   - $name\n";
    }
}

// 6. Check for 0-byte files
echo "\n6. Checking for empty/invalid JSON files:\n";
$emptyFiles = [];
foreach ($jsonFiles as $file) {
    if (filesize($file) < 10) {
        $emptyFiles[] = basename($file);
    }
}
if (empty($emptyFiles)) {
    echo "   All JSON files have content.\n";
} else {
    echo "   Empty files (" . count($emptyFiles) . "):\n";
    foreach ($emptyFiles as $name) {
        echo "   - $name (0 bytes)\n";
    }
}

echo "\n=== Diagnostic complete ===\n";
echo "</pre>";
