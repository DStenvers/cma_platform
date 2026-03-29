<?php
/**
 * Migration: Remove labelColumnWidth from form definitions
 *
 * Label column width is now calculated dynamically in JavaScript (form-controller.js)
 * based on field captions, so the stored property is no longer needed.
 */

$cmaDir = __DIR__ . '/../assets/forms/definitions';
$appDir = __DIR__ . '/../../assets/forms';

$cmaFiles = glob($cmaDir . '/*.json') ?: [];
$appFiles = glob($appDir . '/*.json') ?: [];
$files = array_merge($cmaFiles, $appFiles);

$removed = 0;
$skipped = 0;

echo "=== Migration: Remove labelColumnWidth from form definitions ===\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $json = file_get_contents($file);
    $definition = json_decode($json, true);

    if (!$definition) {
        echo "  [ERROR] Could not parse: $filename\n";
        continue;
    }

    if (!isset($definition['labelColumnWidth'])) {
        $skipped++;
        continue;
    }

    $oldWidth = $definition['labelColumnWidth'];
    unset($definition['labelColumnWidth']);

    $newJson = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = preg_replace('/^(  +)/m', '$1$1', $newJson);
    file_put_contents($file, $newJson . "\n");

    echo "  [REMOVED] $filename (was {$oldWidth}px)\n";
    $removed++;
}

echo "\n=== Summary ===\n";
echo "Removed: $removed\n";
echo "Already clean: $skipped\n";
echo "Total: " . count($files) . "\n";
