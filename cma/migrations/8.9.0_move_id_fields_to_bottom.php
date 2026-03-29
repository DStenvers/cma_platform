<?php
/**
 * Migration: Formulieren: ID's naar onderen verplaatsen
 * Version: 8.9.0
 *
 * Verplaatst velden met de naam PromptusID, CartaID of STBID naar het einde
 * van de fields array, zodat ze onderaan het formulier verschijnen.
 */

namespace CmaMigrations;

class Migration_8_9_0_move_id_fields_to_bottom
{
    /**
     * Field names to move to the bottom
     */
    private static $fieldNames = ['PromptusID', 'CartaID', 'STBID'];

    /**
     * Run the migration
     */
    public static function up(): array
    {
        $results = [
            'success' => true,
            'filesProcessed' => 0,
            'fieldsMoved' => 0,
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

            // Skip schema files and internal files
            if (strpos($filename, 'schema') !== false || strpos($filename, '_') === 0) {
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

            if (!isset($data['fields']) || !is_array($data['fields'])) {
                continue;
            }

            $fieldsToMove = [];
            $remainingFields = [];
            $fileChanges = [];

            foreach ($data['fields'] as $field) {
                $name = $field['name'] ?? '';
                if (in_array($name, self::$fieldNames, true)) {
                    $fieldsToMove[] = $field;
                    $fileChanges[] = "$name naar onderkant verplaatst";
                } else {
                    $remainingFields[] = $field;
                }
            }

            if (empty($fieldsToMove)) {
                continue;
            }

            // Move matched fields to the end
            $data['fields'] = array_merge($remainingFields, $fieldsToMove);

            $results['filesProcessed']++;
            $results['fieldsMoved'] += count($fieldsToMove);

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

    public static function down(): array
    {
        return [
            'success' => false,
            'message' => 'Rollback niet ondersteund - velden stonden al op verschillende posities.',
        ];
    }

    public static function getDescription(): string
    {
        return 'Formulieren: ID\'s naar onderen verplaatsen';
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running migration: Formulieren: ID's naar onderen verplaatsen...\n\n";

    $result = Migration_8_9_0_move_id_fields_to_bottom::up();

    echo "Bestanden verwerkt: {$result['filesProcessed']}\n";
    echo "Velden verplaatst: {$result['fieldsMoved']}\n";

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
