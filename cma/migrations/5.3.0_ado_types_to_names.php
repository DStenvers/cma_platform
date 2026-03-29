<?php
/**
 * Migration: Convert ADO type codes to readable dataType names
 *
 * This migration transforms numeric ADO type codes in JSON form definitions
 * to readable string names for easier detection and maintenance.
 *
 * ADO Type Codes:
 * - 7   (adDate)         → "date"
 * - 133 (adDBDate)       → "date"
 * - 134 (adDBTime)       → "time"
 * - 135 (adDBTimeStamp)  → "datetime"
 * - 130 (adWChar/nvarchar) → "text"
 * - 200 (adVarChar)      → "text"
 * - 201 (adLongVarChar)  → "text"
 * - 202 (adVarWChar)     → "text"
 * - 203 (adLongVarWChar) → "memo"
 * - 3   (adInteger)      → "integer"
 * - 2   (adSmallInt)     → "integer"
 * - 16  (adTinyInt)      → "integer"
 * - 20  (adBigInt)       → "integer"
 * - 11  (adBoolean)      → "boolean"
 * - 4   (adSingle)       → "float"
 * - 5   (adDouble)       → "float"
 * - 6   (adCurrency)     → "currency"
 * - 14  (adDecimal)      → "decimal"
 * - 131 (adNumeric)      → "decimal"
 * - 72  (adGUID)         → "guid"
 * - 128 (adBinary)       → "binary"
 * - 204 (adVarBinary)    → "binary"
 * - 205 (adLongVarBinary)→ "binary"
 */

$definitionsDir = __DIR__ . '/../assets/forms/definitions';

// ADO type code to readable name mapping
$adoTypeMap = [
    // Date/Time types
    '7'   => 'date',        // adDate
    '133' => 'date',        // adDBDate
    '134' => 'time',        // adDBTime
    '135' => 'datetime',    // adDBTimeStamp

    // Text types
    '130' => 'text',        // adWChar (nvarchar)
    '200' => 'text',        // adVarChar
    '201' => 'text',        // adLongVarChar
    '202' => 'text',        // adVarWChar
    '203' => 'memo',        // adLongVarWChar (ntext)

    // Integer types
    '2'   => 'integer',     // adSmallInt
    '3'   => 'integer',     // adInteger
    '16'  => 'integer',     // adTinyInt
    '20'  => 'integer',     // adBigInt
    '17'  => 'integer',     // adUnsignedTinyInt
    '18'  => 'integer',     // adUnsignedSmallInt
    '19'  => 'integer',     // adUnsignedInt
    '21'  => 'integer',     // adUnsignedBigInt

    // Boolean
    '11'  => 'boolean',     // adBoolean

    // Floating point
    '4'   => 'float',       // adSingle
    '5'   => 'float',       // adDouble

    // Decimal/Currency
    '6'   => 'currency',    // adCurrency
    '14'  => 'decimal',     // adDecimal
    '131' => 'decimal',     // adNumeric

    // GUID
    '72'  => 'guid',        // adGUID

    // Binary
    '128' => 'binary',      // adBinary
    '204' => 'binary',      // adVarBinary
    '205' => 'binary',      // adLongVarBinary
];

$results = [
    'processed' => 0,
    'updated' => 0,
    'fieldsConverted' => 0,
    'errors' => [],
    'details' => [],
];

// Get all JSON files
$files = glob($definitionsDir . '/*.json');

foreach ($files as $file) {
    $filename = basename($file);
    $results['processed']++;

    $content = file_get_contents($file);
    if ($content === false) {
        $results['errors'][] = "Failed to read: $filename";
        continue;
    }

    // Skip empty files
    if (trim($content) === '') {
        continue;
    }

    $definition = json_decode($content, true);
    if ($definition === null) {
        $results['errors'][] = "Invalid JSON in: $filename";
        continue;
    }

    $modified = false;
    $fieldsChanged = [];

    // Process main fields
    if (isset($definition['fields']) && is_array($definition['fields'])) {
        foreach ($definition['fields'] as $i => $field) {
            if (isset($field['dataType'])) {
                $dataType = (string)$field['dataType'];
                if (isset($adoTypeMap[$dataType])) {
                    $newType = $adoTypeMap[$dataType];
                    $definition['fields'][$i]['dataType'] = $newType;
                    $fieldsChanged[] = ($field['name'] ?? "field[$i]") . ": $dataType → $newType";
                    $modified = true;
                    $results['fieldsConverted']++;
                }
            }
        }
    }

    // Process subform fields
    if (isset($definition['subforms']) && is_array($definition['subforms'])) {
        foreach ($definition['subforms'] as $si => $subform) {
            if (isset($subform['fields']) && is_array($subform['fields'])) {
                foreach ($subform['fields'] as $fi => $field) {
                    if (isset($field['dataType'])) {
                        $dataType = (string)$field['dataType'];
                        if (isset($adoTypeMap[$dataType])) {
                            $newType = $adoTypeMap[$dataType];
                            $definition['subforms'][$si]['fields'][$fi]['dataType'] = $newType;
                            $subformName = $subform['name'] ?? "subform[$si]";
                            $fieldName = $field['name'] ?? "field[$fi]";
                            $fieldsChanged[] = "$subformName.$fieldName: $dataType → $newType";
                            $modified = true;
                            $results['fieldsConverted']++;
                        }
                    }
                }
            }
        }
    }

    if ($modified) {
        // Write back with pretty printing
        $newContent = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($newContent === false) {
            $results['errors'][] = "Failed to encode: $filename";
            continue;
        }

        // Ensure consistent formatting (4-space indent)
        $newContent = preg_replace_callback('/^( +)/m', function($matches) {
            return str_repeat('    ', strlen($matches[1]) / 4);
        }, $newContent);

        if (file_put_contents($file, $newContent . "\n") === false) {
            $results['errors'][] = "Failed to write: $filename";
            continue;
        }

        $results['updated']++;
        $results['details'][$filename] = $fieldsChanged;
    }
}

// Output results
echo "=== ADO Type Migration Results ===\n\n";
echo "Files processed: {$results['processed']}\n";
echo "Files updated: {$results['updated']}\n";
echo "Fields converted: {$results['fieldsConverted']}\n";

if (!empty($results['errors'])) {
    echo "\nErrors:\n";
    foreach ($results['errors'] as $error) {
        echo "  - $error\n";
    }
}

if (!empty($results['details'])) {
    echo "\nDetails:\n";
    foreach ($results['details'] as $file => $changes) {
        echo "\n$file:\n";
        foreach ($changes as $change) {
            echo "  - $change\n";
        }
    }
}

echo "\nMigration complete.\n";
