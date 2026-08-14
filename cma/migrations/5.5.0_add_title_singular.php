<?php
/**
 * Migration: Add titleSingular field to form definitions
 *
 * Converts Dutch plural titles to singular:
 * - "Opleidingen" -> "Opleiding" (remove 'en')
 * - "Gebruikers" -> "Gebruiker" (remove 's')
 * - "Groepen" -> "Groep" (remove 'en')
 *
 * The titleSingular is used for action labels like "Opleiding toevoegen" or "Opleiding details"
 */

use App\Library\Str;

$definitionsDir = __DIR__ . '/../assets/forms/definitions';

$results = [
    'processed' => 0,
    'updated' => 0,
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

    // Skip if already has titleSingular
    if (isset($definition['titleSingular'])) {
        continue;
    }

    // Skip if no title
    if (!isset($definition['title']) || empty($definition['title'])) {
        continue;
    }

    $title = $definition['title'];
    $singular = Str::dutchSingular($title);

    // Only add if different from title
    if ($singular !== $title) {
        // Insert titleSingular right after title
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

// Output results
echo "=== Add titleSingular Migration Results ===\n\n";
echo "Files processed: {$results['processed']}\n";
echo "Files updated: {$results['updated']}\n";

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
