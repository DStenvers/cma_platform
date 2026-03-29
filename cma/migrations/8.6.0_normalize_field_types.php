<?php
/**
 * Migration: Normalize field types and dataTypes in JSON form definitions
 * Version: 8.6.0
 *
 * Combines the work of migrations 7.5.0 and 8.5.0 which may not have
 * executed due to version tracking. This migration:
 *
 * 1. Converts numeric ADO dataType codes to string names
 *    (e.g. "7" → "date", "130" → "text", "3" → "integer")
 *
 * 2. Changes field type from "textbox" to "date" or "time" for fields
 *    with date/time dataTypes, so table headers can read the type directly.
 */

namespace CmaMigrations;

class Migration_8_6_0_normalize_field_types
{
    /**
     * ADO type code → string type mapping
     */
    private static $adoTypeMap = [
        '2'   => 'smallint',    // adSmallInt
        '3'   => 'integer',     // adInteger
        '4'   => 'float',       // adSingle
        '5'   => 'double',      // adDouble
        '6'   => 'money',       // adCurrency
        '7'   => 'date',        // adDate
        '11'  => 'boolean',     // adBoolean
        '72'  => 'guid',        // adGUID
        '129' => 'char',        // adChar
        '130' => 'text',        // adWChar (nvarchar)
        '131' => 'decimal',     // adNumeric
        '133' => 'date',        // adDBDate
        '135' => 'datetime',    // adDBTimeStamp
        '200' => 'varchar',     // adVarChar
        '201' => 'text',        // adLongVarChar
        '202' => 'text',        // adVarWChar
        '203' => 'memo',        // adLongVarWChar
    ];

    /**
     * dataType values that indicate a date field → new field type
     */
    private static $dateFieldTypes = [
        'date'     => 'date',
        'datetime' => 'date',
        'time'     => 'time',
    ];

    /**
     * Run the migration
     */
    public static function up(): array
    {
        $results = [
            'success' => true,
            'filesProcessed' => 0,
            'dataTypesNormalized' => 0,
            'fieldTypesFixed' => 0,
            'errors' => [],
            'changes' => [],
        ];

        // Process JSON forms in site/assets/forms/
        $siteFormsDir = dirname(__DIR__, 2) . '/assets/forms';
        if (is_dir($siteFormsDir)) {
            self::processDirectory($siteFormsDir, $results);
        }

        // Process JSON forms in cma/assets/forms/definitions/
        $cmaFormsDir = dirname(__DIR__) . '/assets/forms/definitions';
        if (is_dir($cmaFormsDir)) {
            self::processDirectory($cmaFormsDir, $results);
        }

        return $results;
    }

    /**
     * Process all JSON files in a directory
     */
    private static function processDirectory(string $dir, array &$results): void
    {
        $files = glob($dir . '/*.json');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $filename = basename($file);

            // Skip schema files
            if (strpos($filename, 'schema') !== false) {
                continue;
            }

            $content = file_get_contents($file);
            if ($content === false) {
                $results['errors'][] = "Kan niet lezen: $filename";
                continue;
            }

            $data = json_decode($content, true);
            if ($data === null) {
                $results['errors'][] = "Ongeldige JSON in: $filename";
                continue;
            }

            $fileChanges = [];
            $modified = false;

            if (isset($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $i => $field) {
                    $fieldName = $field['name'] ?? "field[$i]";
                    $dataType = (string)($field['dataType'] ?? '');

                    // Step 1: Normalize ADO numeric codes to string names
                    if ($dataType !== '' && isset(self::$adoTypeMap[$dataType])) {
                        $newDataType = self::$adoTypeMap[$dataType];
                        $data['fields'][$i]['dataType'] = $newDataType;
                        $modified = true;
                        $results['dataTypesNormalized']++;
                        $fileChanges[] = "$fieldName: dataType $dataType → $newDataType";

                        // Use the normalized value for step 2
                        $dataType = $newDataType;
                    }

                    // Step 2: Change field type from textbox to date/time
                    $fieldType = $data['fields'][$i]['type'] ?? '';
                    if ($fieldType === 'textbox' && isset(self::$dateFieldTypes[strtolower($dataType)])) {
                        $newFieldType = self::$dateFieldTypes[strtolower($dataType)];
                        $data['fields'][$i]['type'] = $newFieldType;
                        $modified = true;
                        $results['fieldTypesFixed']++;
                        $fileChanges[] = "$fieldName: type textbox → $newFieldType";
                    }
                }
            }

            if ($modified) {
                $results['filesProcessed']++;

                // Detect original indentation
                $indent = '    '; // default 4 spaces
                if (preg_match('/^(\s+)"/m', $content, $m)) {
                    $indent = $m[1];
                }

                $newContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Match original indentation if it was tabs
                if (str_contains($indent, "\t")) {
                    $newContent = preg_replace_callback('/^( +)/m', function ($m) {
                        return str_repeat("\t", (int)(strlen($m[1]) / 4));
                    }, $newContent);
                }

                if (file_put_contents($file, $newContent) === false) {
                    $results['errors'][] = "Kan niet schrijven: $filename";
                    $results['success'] = false;
                } else {
                    $results['changes'][$filename] = $fileChanges;
                }
            }
        }
    }

    public static function down(): array
    {
        return [
            'success' => false,
            'message' => 'Rollback niet ondersteund.',
        ];
    }

    public static function getDescription(): string
    {
        return 'Normaliseer ADO dataType codes en wijzig field type van textbox naar date/time';
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running field type normalization migration...\n\n";

    $result = Migration_8_6_0_normalize_field_types::up();

    echo "Bestanden verwerkt: {$result['filesProcessed']}\n";
    echo "DataTypes genormaliseerd: {$result['dataTypesNormalized']}\n";
    echo "Veldtypes aangepast: {$result['fieldTypesFixed']}\n";

    if (!empty($result['changes'])) {
        echo "\nWijzigingen per bestand:\n";
        foreach ($result['changes'] as $file => $changes) {
            echo "\n  $file:\n";
            foreach ($changes as $change) {
                echo "    - $change\n";
            }
        }
    }

    if (!empty($result['errors'])) {
        echo "\nFouten:\n";
        foreach ($result['errors'] as $error) {
            echo "  - $error\n";
        }
    }

    echo "\n" . ($result['success'] ? "Migratie succesvol." : "Migratie met fouten.") . "\n";
}
