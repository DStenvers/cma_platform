<?php
/**
 * Migration: Auto-detect and add filterByField to cascading combo fields
 * Version: 9.3.0
 *
 * Generic approach: for each combo field with a sourceTable, check if any other
 * field on the same form exists as a column in that source table. If so, the combo
 * should be filtered by that field (cascading combo).
 *
 * Example: form has fkOpleiding + fkOpleidingsBlok (sourceTable=tblOpleidingenBlokken).
 * If tblOpleidingenBlokken has a column "fkOpleiding", then fkOpleidingsBlok gets
 * filterByField="fkOpleiding".
 */

namespace CmaMigrations;

use Cma\SchemaHelper;
use App\Library\Database;

class Migration_9_3_0_combo_filter_by_field
{
    /** @var array Cache of table columns: tableName => [columnName => true] */
    private static array $columnCache = [];

    /**
     * Run the migration
     */
    public static function up(): array
    {
        $results = [
            'success' => true,
            'filesProcessed' => 0,
            'fieldsUpdated' => 0,
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
            self::processFile($file, $results);
        }
    }

    /**
     * Get columns of a table (cached, case-insensitive)
     */
    private static function getTableColumns(string $tableName, string $database = ''): array
    {
        $cacheKey = strtolower($database . '.' . $tableName);
        if (isset(self::$columnCache[$cacheKey])) {
            return self::$columnCache[$cacheKey];
        }

        $columns = [];
        try {
            $conn = !empty($database)
                ? Database::getConnection($database)
                : Database::getConnection('data');

            if ($conn) {
                $dbColumns = SchemaHelper::getColumns($conn, $tableName);
                foreach ($dbColumns as $col) {
                    $colName = $col['name'] ?? $col['COLUMN_NAME'] ?? '';
                    if ($colName !== '') {
                        $columns[strtolower($colName)] = $colName;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Table might not exist or DB not available — skip silently
        }

        self::$columnCache[$cacheKey] = $columns;
        return $columns;
    }

    /**
     * Process a single JSON form file
     */
    private static function processFile(string $filePath, array &$results): void
    {
        $json = file_get_contents($filePath);
        if ($json === false) {
            return;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['fields'])) {
            return;
        }

        $results['filesProcessed']++;
        $modified = false;
        $formDatabase = $data['database'] ?? '';

        // Collect all field names on this form (potential parent fields)
        $formFieldNames = [];
        foreach ($data['fields'] as $field) {
            $name = $field['name'] ?? '';
            if ($name !== '') {
                $formFieldNames[strtolower($name)] = $name;
            }
        }

        // For each combo with a sourceTable, check if any other form field
        // exists as a column in the source table
        foreach ($data['fields'] as &$field) {
            if (($field['type'] ?? '') !== 'combobox') {
                continue;
            }
            if (!empty($field['filterByField'])) {
                continue; // Already set
            }

            $sourceTable = $field['sourceTable'] ?? '';
            if ($sourceTable === '') {
                continue;
            }

            $fieldDatabase = $field['database'] ?? $formDatabase;
            $columns = self::getTableColumns($sourceTable, $fieldDatabase);
            if (empty($columns)) {
                continue;
            }

            $fieldNameLower = strtolower($field['name'] ?? '');

            // Look for form fields that exist as columns in the source table
            // but are NOT the combo field itself
            foreach ($formFieldNames as $lcFormField => $originalFormField) {
                if ($lcFormField === $fieldNameLower) {
                    continue; // Skip self
                }
                if (isset($columns[$lcFormField])) {
                    // This form field exists as a column in the combo's source table
                    // → it's a cascading filter candidate
                    $field['filterByField'] = $originalFormField;
                    $modified = true;
                    $results['fieldsUpdated']++;
                    $results['changes'][] = basename($filePath) . ': ' .
                        ($field['name'] ?? '?') . ' → filterByField=' . $originalFormField .
                        ' (column found in ' . $sourceTable . ')';
                    break; // Only one filterByField per combo
                }
            }
        }
        unset($field);

        if ($modified) {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded !== false) {
                file_put_contents($filePath, $encoded);
            }
        }
    }
}
