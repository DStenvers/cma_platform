<?php
/**
 * Migration: Add openInNewWindow to extra buttons with [domein] URLs
 * Version: 9.1.0
 *
 * Extra buttons with [domein] in their URL navigate to the front-end site
 * and should open in a new browser tab instead of the popup overlay.
 */

namespace CmaMigrations;

class Migration_9_1_0_extra_button_open_in_new_window
{
    /**
     * Run the migration
     */
    public static function up(): array
    {
        $results = [
            'success' => true,
            'filesProcessed' => 0,
            'buttonsUpdated' => 0,
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

            if (!isset($data['extraButtons']) || !is_array($data['extraButtons'])) {
                continue;
            }

            $fileChanges = [];
            $modified = false;

            foreach ($data['extraButtons'] as $i => &$btn) {
                if (!isset($btn['openInNewWindow']) && stripos($btn['url'] ?? '', '[domein]') !== false) {
                    $btn['openInNewWindow'] = true;
                    $modified = true;
                    $results['buttonsUpdated']++;
                    $title = $btn['title'] ?? "button[$i]";
                    $fileChanges[] = "extraButtons[$title]: openInNewWindow = true";
                }
            }
            unset($btn);

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
        return 'Voegt openInNewWindow=true toe aan extra buttons met [domein] in de URL';
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running openInNewWindow migration...\n\n";

    $result = Migration_9_1_0_extra_button_open_in_new_window::up();

    echo "Bestanden verwerkt: {$result['filesProcessed']}\n";
    echo "Buttons bijgewerkt: {$result['buttonsUpdated']}\n";

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
