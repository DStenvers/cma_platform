<?php
/**
 * Migration: Replace .asp references with .php in form definition URLs
 * Version: 8.8.0
 *
 * The site has been converted from ASP to PHP. This migration updates
 * all URL fields in JSON form definitions:
 * - extraButtons[].url
 * - afterPostUrl
 * - previewUrl
 */

namespace CmaMigrations;

class Migration_8_8_0_replace_asp_with_php
{
    /** URL properties at the root level of a form definition */
    private static $urlProperties = ['afterPostUrl', 'previewUrl'];

    /**
     * Run the migration
     */
    public static function up(): array
    {
        $results = [
            'success' => true,
            'filesProcessed' => 0,
            'urlsReplaced' => 0,
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

            // Replace .asp in root-level URL properties (afterPostUrl, previewUrl)
            foreach (self::$urlProperties as $prop) {
                if (isset($data[$prop]) && is_string($data[$prop]) && stripos($data[$prop], '.asp') !== false) {
                    $old = $data[$prop];
                    $data[$prop] = self::replaceAsp($old);
                    if ($data[$prop] !== $old) {
                        $modified = true;
                        $results['urlsReplaced']++;
                        $fileChanges[] = "$prop: $old → {$data[$prop]}";
                    }
                }
            }

            // Replace .asp in extraButtons[].url
            if (isset($data['extraButtons']) && is_array($data['extraButtons'])) {
                foreach ($data['extraButtons'] as $i => $btn) {
                    if (isset($btn['url']) && is_string($btn['url']) && stripos($btn['url'], '.asp') !== false) {
                        $old = $btn['url'];
                        $data['extraButtons'][$i]['url'] = self::replaceAsp($old);
                        if ($data['extraButtons'][$i]['url'] !== $old) {
                            $modified = true;
                            $results['urlsReplaced']++;
                            $title = $btn['title'] ?? "button[$i]";
                            $fileChanges[] = "extraButtons[$title]: $old → {$data['extraButtons'][$i]['url']}";
                        }
                    }
                }
            }

            // Replace .asp in legacy root-level extraIconURL properties (1-5)
            $legacyProps = ['extraIconURL', 'extraIcon2URL', 'extraIcon3URL', 'extraIcon4URL', 'extraIcon5URL'];
            foreach ($legacyProps as $prop) {
                if (isset($data[$prop]) && is_string($data[$prop]) && stripos($data[$prop], '.asp') !== false) {
                    $old = $data[$prop];
                    $data[$prop] = self::replaceAsp($old);
                    if ($data[$prop] !== $old) {
                        $modified = true;
                        $results['urlsReplaced']++;
                        $fileChanges[] = "$prop: $old → {$data[$prop]}";
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

    /**
     * Replace .asp extension with .php in a URL string.
     * Handles .asp at end of string, before query string (?), and escaped slashes.
     */
    private static function replaceAsp(string $url): string
    {
        // Replace .asp before query string, hash, quote, end of string, or escaped sequences
        return preg_replace('/\.asp\b/i', '.php', $url);
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
        return 'Vervang .asp referenties door .php in formulier URL-velden (extraButtons, afterPostUrl, previewUrl)';
    }
}

// Allow running directly from command line
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    echo "Running ASP to PHP URL migration...\n\n";

    $result = Migration_8_8_0_replace_asp_with_php::up();

    echo "Bestanden verwerkt: {$result['filesProcessed']}\n";
    echo "URLs vervangen: {$result['urlsReplaced']}\n";

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
