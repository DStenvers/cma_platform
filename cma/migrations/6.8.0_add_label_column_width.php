<?php
/**
 * Migration: Calculate and add labelColumnWidth to form definitions
 *
 * Scans all JSON form definitions, calculates the optimal label column width
 * based on the longest field caption, and adds the labelColumnWidth property.
 *
 * Calculation: (longestCaptionLength * 8) + 16 for padding
 * Clamped between 110px (minimum) and 360px (maximum)
 */

// SKIPPED: labelColumnWidth is now calculated dynamically in JS (form-controller.js)
echo "SKIPPED: labelColumnWidth is calculated dynamically in JS\n";
return;

// Scan both CMA internal forms and app forms
$cmaDir = __DIR__ . '/../assets/forms/definitions';
$appDir = __DIR__ . '/../../assets/forms';

$cmaFiles = glob($cmaDir . '/*.json') ?: [];
$appFiles = glob($appDir . '/*.json') ?: [];
$files = array_merge($cmaFiles, $appFiles);

// Character width estimate (px per character for typical UI fonts)
$charWidth = 6.5;
// Padding (left + right + buffer for required indicator)
$padding = 10;
// Minimum width
$minWidth = 110;
// Maximum width
$maxWidth = 360;

$updated = 0;
$skipped = 0;
$noFields = 0;

echo "=== Migration: Add labelColumnWidth to form definitions ===\n\n";
echo "Scanning " . count($files) . " form definitions...\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $json = file_get_contents($file);
    $definition = json_decode($json, true);

    if (!$definition) {
        echo "  [ERROR] Could not parse: $filename\n";
        continue;
    }

    // Skip if labelColumnWidth is already set
    if (!empty($definition['labelColumnWidth'])) {
        echo "  [SKIP] $filename - labelColumnWidth already set: {$definition['labelColumnWidth']}px\n";
        $skipped++;
        continue;
    }

    // Get all field captions from the definition
    $longestCaption = '';
    $longestLength = 0;

    if (!empty($definition['fields'])) {
        foreach ($definition['fields'] as $field) {
            // Skip groupseparator fields - they span full width
            $fieldType = $field['type'] ?? '';
            if ($fieldType === 'groupseparator') {
                continue;
            }

            $caption = $field['caption'] ?? '';

            // Handle multi-line captions: split on <br> and find longest line
            // Also handle <br/>, <br /> variations
            $lines = preg_split('/<br\s*\/?>/i', $caption);

            foreach ($lines as $line) {
                // Strip any remaining HTML tags and entities
                $cleanLine = strip_tags($line);
                $cleanLine = html_entity_decode($cleanLine, ENT_QUOTES, 'UTF-8');
                $cleanLine = trim($cleanLine);

                // Calculate visual length
                $visualLength = function_exists('mb_strlen') ? mb_strlen($cleanLine) : strlen($cleanLine);

                if ($visualLength > $longestLength) {
                    $longestLength = $visualLength;
                    $longestCaption = $cleanLine;
                }
            }
        }
    }

    if ($longestLength === 0) {
        echo "  [NO FIELDS] $filename - no fields with captions found\n";
        $noFields++;
        continue;
    }

    // Calculate optimal width and round to integer
    $calculatedWidth = ($longestLength * $charWidth) + $padding;
    // Clamp to min/max bounds and round
    $optimalWidth = (int) round(max($minWidth, min($maxWidth, $calculatedWidth)));

    // Add labelColumnWidth to the definition
    // Insert after 'filterIdName' or 'quickSearchFields' for logical ordering
    $newDefinition = [];
    $inserted = false;
    $insertAfter = ['filterIdName', 'quickSearchFields', 'storeLastModified', 'securityByUser'];

    foreach ($definition as $key => $value) {
        $newDefinition[$key] = $value;
        // Insert after specific keys for logical ordering
        if (in_array($key, $insertAfter) && !$inserted) {
            $newDefinition['labelColumnWidth'] = $optimalWidth;
            $inserted = true;
        }
    }

    // If not inserted yet (keys not found), add before 'fields'
    if (!$inserted) {
        $finalDefinition = [];
        foreach ($newDefinition as $key => $value) {
            if ($key === 'fields' && !$inserted) {
                $finalDefinition['labelColumnWidth'] = $optimalWidth;
                $inserted = true;
            }
            $finalDefinition[$key] = $value;
        }
        $newDefinition = $finalDefinition;
    }

    // If still not inserted, add at the end
    if (!$inserted) {
        $newDefinition['labelColumnWidth'] = $optimalWidth;
    }

    // Write back with pretty formatting
    $newJson = json_encode($newDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Fix indentation to 4 spaces
    $newJson = preg_replace('/^(  +)/m', '$1$1', $newJson);

    file_put_contents($file, $newJson . "\n");
    echo "  [UPDATED] $filename - labelColumnWidth: {$optimalWidth}px (longest: \"$longestCaption\" = {$longestLength} chars)\n";
    $updated++;
}

echo "\n=== Summary ===\n";
echo "Updated: $updated\n";
echo "Skipped (already set): $skipped\n";
echo "No fields: $noFields\n";
echo "Total: " . count($files) . "\n";
