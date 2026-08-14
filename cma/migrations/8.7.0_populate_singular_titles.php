<?php
/**
 * Migration: Populate missing titleSingular fields in form definitions
 *
 * Scans all form definitions and adds titleSingular where missing.
 * Uses Dutch plural-to-singular conversion rules.
 *
 * Target directories:
 * - /site/assets/forms/ (site-specific forms)
 * - /site/cma/assets/forms/definitions/ (CMA core forms)
 */

use App\Library\Str;

$siteFormsDir = realpath(__DIR__ . '/../../assets/forms');
$cmaFormsDir = __DIR__ . '/../assets/forms/definitions';

$results = [
    'processed' => 0,
    'updated' => 0,
    'skipped' => 0,
    'errors' => [],
    'details' => [],
];

/**
 * Convert Dutch plural to singular
 * Rules:
 * - Words ending in 'en' -> remove 'en' (Opleidingen -> Opleiding)
 * - Words ending in 's' -> remove 's' (Gebruikers -> Gebruiker)
 * - Special cases handled manually
 */

/**
 * Process forms in a directory
 */
function processDirectory(string $dir, array &$results): void
{
    if (!is_dir($dir)) {
        $results['errors'][] = "Directory not found: $dir";
        return;
    }

    $files = glob($dir . '/*.json');

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
            $results['skipped']++;
            continue;
        }

        $definition = json_decode($content, true);
        if ($definition === null) {
            $results['errors'][] = "Invalid JSON in: $filename";
            continue;
        }

        // Skip if already has titleSingular with a value
        if (!empty($definition['titleSingular'])) {
            $results['skipped']++;
            continue;
        }

        // Skip if no title
        if (!isset($definition['title']) || empty($definition['title'])) {
            $results['skipped']++;
            continue;
        }

        $title = $definition['title'];
        $singular = Str::dutchSingular($title);

        // Add titleSingular
        // Insert right after title for consistency
        $newDefinition = [];
        foreach ($definition as $key => $value) {
            $newDefinition[$key] = $value;
            if ($key === 'title') {
                $newDefinition['titleSingular'] = $singular;
            }
        }

        // Write back with pretty printing
        $newContent = json_encode($newDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($newContent === false) {
            $results['errors'][] = "Failed to encode: $filename";
            continue;
        }

        if (file_put_contents($file, $newContent . "\n") === false) {
            $results['errors'][] = "Failed to write: $filename";
            continue;
        }

        $results['updated']++;
        $results['details'][$filename] = "$title -> $singular";
    }
}

// Process both directories
echo "Processing site forms: $siteFormsDir\n";
processDirectory($siteFormsDir, $results);

echo "Processing CMA forms: $cmaFormsDir\n";
processDirectory($cmaFormsDir, $results);

// Output results
echo "\n=== Populate titleSingular Migration Results ===\n\n";
echo "Files processed: {$results['processed']}\n";
echo "Files updated: {$results['updated']}\n";
echo "Files skipped (already have titleSingular): {$results['skipped']}\n";

if (!empty($results['errors'])) {
    echo "\nErrors:\n";
    foreach ($results['errors'] as $error) {
        echo "  - $error\n";
    }
}

if (!empty($results['details'])) {
    echo "\nConversions:\n";
    foreach ($results['details'] as $file => $conversion) {
        echo "  $file: $conversion\n";
    }
}

echo "\nMigration complete.\n";
