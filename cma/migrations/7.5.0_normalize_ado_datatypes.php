<?php
/**
 * Migration: Normalize ADO dataType codes to meaningful string types
 * Version: 7.5.0
 *
 * This migration converts numeric ADO type codes in JSON form definitions
 * to human-readable string type names. This eliminates the need for
 * runtime conversion of ADO codes.
 *
 * ADO Type Code Reference:
 * - 2 = adSmallInt → "smallint"
 * - 3 = adInteger → "integer"
 * - 4 = adSingle → "float"
 * - 5 = adDouble → "double"
 * - 6 = adCurrency → "money"
 * - 7 = adDate → "date"
 * - 11 = adBoolean → "boolean"
 * - 72 = adGUID → "guid"
 * - 129 = adChar → "char"
 * - 130 = adWChar → "text"
 * - 131 = adNumeric → "decimal"
 * - 133 = adDBDate → "date"
 * - 135 = adDBTimeStamp → "datetime"
 * - 200 = adVarChar → "varchar"
 * - 201 = adLongVarChar → "text"
 * - 202 = adVarWChar → "text"
 * - 203 = adLongVarWChar → "memo"
 */

namespace CmaMigrations;

class Migration_7_5_0_normalize_ado_datatypes
{
    /**
     * ADO type code to string type mapping
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
     * Run the migration
     *
     * @return array Migration result with status and details
     */
    public static function up(): array
    {
        $results = [
            'success' => true,
            'filesProcessed' => 0,
            'fieldsConverted' => 0,
            'errors' => [],
            'conversions' => [],
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

            // Read JSON file
            $content = file_get_contents($file);
            if ($content === false) {
                $results['errors'][] = "Failed to read: $filename";
                continue;
            }

            $data = json_decode($content, true);
            if ($data === null) {
                $results['errors'][] = "Invalid JSON in: $filename";
                continue;
            }

            // Track conversions for this file
            $fileConversions = [];
            $modified = false;

            // Process fields array
            if (isset($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $i => $field) {
                    if (isset($field['dataType']) && is_string($field['dataType'])) {
                        $oldType = $field['dataType'];

                        // Check if it's a numeric ADO code
                        if (isset(self::$adoTypeMap[$oldType])) {
                            $newType = self::$adoTypeMap[$oldType];
                            $data['fields'][$i]['dataType'] = $newType;
                            $modified = true;
                            $results['fieldsConverted']++;

                            $fieldName = $field['name'] ?? "field[$i]";
                            $fileConversions[] = "$fieldName: $oldType → $newType";
                        }
                    }
                }
            }

            // Save if modified
            if ($modified) {
                $results['filesProcessed']++;

                // Pretty print JSON with consistent formatting
                $newContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Ensure consistent indentation (use tabs like existing files)
                $newContent = preg_replace('/^(  +)/m', str_repeat("\t", 1), $newContent);

                if (file_put_contents($file, $newContent) === false) {
                    $results['errors'][] = "Failed to write: $filename";
                    $results['success'] = false;
                } else {
                    $results['conversions'][$filename] = $fileConversions;
                }
            }
        }
    }

    /**
     * Rollback is not supported for this migration
     * The original ADO codes are not recoverable without a backup
     */
    public static function down(): array
    {
        return [
            'success' => false,
            'message' => 'Rollback not supported. Restore from backup if needed.',
        ];
    }

    /**
     * Get migration description
     */
    public static function getDescription(): string
    {
        return 'Convert numeric ADO dataType codes to meaningful string types in JSON form definitions';
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running ADO dataType normalization migration...\n\n";

    $result = Migration_7_5_0_normalize_ado_datatypes::up();

    echo "Files processed: {$result['filesProcessed']}\n";
    echo "Fields converted: {$result['fieldsConverted']}\n";

    if (!empty($result['conversions'])) {
        echo "\nConversions by file:\n";
        foreach ($result['conversions'] as $file => $conversions) {
            echo "\n  $file:\n";
            foreach ($conversions as $conv) {
                echo "    - $conv\n";
            }
        }
    }

    if (!empty($result['errors'])) {
        echo "\nErrors:\n";
        foreach ($result['errors'] as $error) {
            echo "  - $error\n";
        }
    }

    echo "\n" . ($result['success'] ? "Migration completed successfully." : "Migration completed with errors.") . "\n";
}
