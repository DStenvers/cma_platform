<?php
/**
 * Migration: Fix date/time field types in JSON form definitions
 * Version: 8.5.0
 *
 * This migration changes the field "type" from "textbox" to "date" for fields
 * that have a date/time dataType. This ensures date pickers are rendered correctly.
 *
 * Affected dataTypes:
 * - "7", "date" → type changes to "date"
 * - "133" → type changes to "date"
 * - "135", "datetime" → type changes to "date"
 * - Time fields (dataType "time") → type changes to "time"
 */

namespace CmaMigrations;

class Migration_8_5_0_fix_date_field_types
{
    /**
     * DataTypes that indicate a date or time field
     * Maps dataType → new field type
     */
    private static $dateTypeMap = [
        // Numeric ADO codes
        '7'   => 'date',       // adDate
        '133' => 'date',       // adDBDate
        '135' => 'date',       // adDBTimeStamp (datetime)
        // String type names (from normalized forms)
        'date' => 'date',
        'datetime' => 'date',
        'time' => 'time',
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
            'fieldsFixed' => 0,
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

            // Track changes for this file
            $fileChanges = [];
            $modified = false;

            // Process fields array
            if (isset($data['fields']) && is_array($data['fields'])) {
                foreach ($data['fields'] as $i => $field) {
                    // Only process textbox fields
                    $fieldType = $field['type'] ?? '';
                    if ($fieldType !== 'textbox') {
                        continue;
                    }

                    // Check if dataType indicates a date field
                    $dataType = $field['dataType'] ?? '';
                    if ($dataType === '') {
                        continue;
                    }

                    // Normalize dataType to string for lookup
                    $dataTypeKey = strtolower((string)$dataType);

                    if (isset(self::$dateTypeMap[$dataTypeKey])) {
                        $newType = self::$dateTypeMap[$dataTypeKey];
                        $data['fields'][$i]['type'] = $newType;
                        $modified = true;
                        $results['fieldsFixed']++;

                        $fieldName = $field['name'] ?? "field[$i]";
                        $fileChanges[] = "$fieldName: type textbox → $newType (dataType: $dataType)";
                    }
                }
            }

            // Save if modified
            if ($modified) {
                $results['filesProcessed']++;

                // Pretty print JSON with 4-space indentation
                $newContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                // Ensure 4-space indentation (json_encode uses 4 spaces by default)

                if (file_put_contents($file, $newContent) === false) {
                    $results['errors'][] = "Failed to write: $filename";
                    $results['success'] = false;
                } else {
                    $results['changes'][$filename] = $fileChanges;
                }
            }
        }
    }

    /**
     * Rollback - change date types back to textbox
     * Note: This is a best-effort rollback and may not be perfect
     */
    public static function down(): array
    {
        return [
            'success' => false,
            'message' => 'Rollback not supported. The original field types cannot be reliably determined.',
        ];
    }

    /**
     * Get migration description
     */
    public static function getDescription(): string
    {
        return 'Change field type from "textbox" to "date" for fields with date/time dataType';
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running date field type fix migration...\n\n";

    $result = Migration_8_5_0_fix_date_field_types::up();

    echo "Files processed: {$result['filesProcessed']}\n";
    echo "Fields fixed: {$result['fieldsFixed']}\n";

    if (!empty($result['changes'])) {
        echo "\nChanges by file:\n";
        foreach ($result['changes'] as $file => $changes) {
            echo "\n  $file:\n";
            foreach ($changes as $change) {
                echo "    - $change\n";
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
