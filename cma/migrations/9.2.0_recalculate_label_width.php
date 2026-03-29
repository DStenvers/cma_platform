<?php
/**
 * Migration: Recalculate labelColumnWidth including postCaption/hint text
 *
 * The original 6.8.0 migration only considered caption length.
 * Now that postcaptions are displayed below the caption in the label column,
 * the width needs to account for the longest of caption OR postcaption text.
 *
 * Uses 2.5x the original character width to give enough room for postcaptions
 * (which render in a smaller font but still need horizontal space).
 *
 * Calculation: max(longestCaption, longestPostCaption) * charWidth + padding
 * Clamped between 130px (minimum) and 400px (maximum)
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

// Character width estimates (px per character)
// Caption uses normal UI font (~13px), postcaption uses smaller font (~11px)
$captionCharWidth = 8;        // wider estimate for caption text
$postCaptionCharWidth = 6.5;  // postcaption renders smaller
// Padding (left + right + required indicator + buffer)
$padding = 24;
// Minimum and maximum width
$minWidth = 130;
$maxWidth = 400;

$updated = 0;
$skipped = 0;
$noFields = 0;

echo "=== Migration: Recalculate labelColumnWidth (including postCaption) ===\n\n";
echo "Scanning " . count($files) . " form definitions...\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $json = file_get_contents($file);
    $definition = json_decode($json, true);

    if (!$definition) {
        echo "  [ERROR] Could not parse: $filename\n";
        continue;
    }

    // Get the longest caption and postcaption from all fields
    $longestCaption = '';
    $longestCaptionLen = 0;
    $longestPostCaption = '';
    $longestPostCaptionLen = 0;

    if (empty($definition['fields'])) {
        echo "  [NO FIELDS] $filename - no fields found\n";
        $noFields++;
        continue;
    }

    foreach ($definition['fields'] as $field) {
        // Skip groupseparator fields - they span full width
        $fieldType = $field['type'] ?? '';
        if ($fieldType === 'groupseparator') {
            continue;
        }

        // Measure caption
        $caption = $field['caption'] ?? '';
        $lines = preg_split('/<br\s*\/?>/i', $caption);
        foreach ($lines as $line) {
            $cleanLine = strip_tags($line);
            $cleanLine = html_entity_decode($cleanLine, ENT_QUOTES, 'UTF-8');
            $cleanLine = trim($cleanLine);
            $visualLength = function_exists('mb_strlen') ? mb_strlen($cleanLine) : strlen($cleanLine);
            if ($visualLength > $longestCaptionLen) {
                $longestCaptionLen = $visualLength;
                $longestCaption = $cleanLine;
            }
        }

        // Measure postcaption (hint field)
        // Skip postcaption for fields where it renders INLINE (after input, not in label column):
        // - Checkboxes, dates, times (compact controls)
        // - Small text fields (maxLength <= 20)
        // - Numeric fields
        $postCaption = $field['postCaption'] ?? $field['hint'] ?? '';
        $isCompactControl = in_array($fieldType, ['checkbox', 'date', 'time']);
        $fieldMaxLength = (int)($field['maxLength'] ?? 0);
        $isSmallField = $fieldMaxLength > 0 && $fieldMaxLength <= 20;
        $fieldDataType = strtolower($field['dataType'] ?? '');
        $numericTypes = ['int', 'integer', 'bigint', 'smallint', 'tinyint',
            'decimal', 'numeric', 'float', 'real', 'money', 'number',
            '2', '3', '4', '5', '6', '14', '20', '131'];
        $isNumericField = in_array($fieldDataType, $numericTypes);
        // Wide controls always render postcaption in the label column, not inline
        $isWideControl = in_array($fieldType, ['combobox', 'combo', 'dropdown', 'userlist', 'xmlstore', 'memo', 'checklist', 'sortlist']);
        $postCaptionIsInline = !$isWideControl && ($isCompactControl || $isSmallField || $isNumericField);

        // Skip format hints that are suppressed by FormRenderer
        if (strtolower(trim($postCaption)) === 'hh:mm') {
            continue;
        }
        // Strip parentheses like FormRenderer does
        if ($postCaption !== '' && str_starts_with($postCaption, '(') && str_ends_with($postCaption, ')')) {
            $postCaption = substr($postCaption, 1, -1);
        }
        // Only count postcaption for label width if it renders in the label column (not inline)
        if ($postCaption !== '' && !$postCaptionIsInline) {
            $pcLines = preg_split('/<br\s*\/?>/i', $postCaption);
            foreach ($pcLines as $pcLine) {
                $cleanPC = strip_tags($pcLine);
                $cleanPC = html_entity_decode($cleanPC, ENT_QUOTES, 'UTF-8');
                $cleanPC = trim($cleanPC);
                $pcLen = function_exists('mb_strlen') ? mb_strlen($cleanPC) : strlen($cleanPC);
                if ($pcLen > $longestPostCaptionLen) {
                    $longestPostCaptionLen = $pcLen;
                    $longestPostCaption = $cleanPC;
                }
            }
        }
    }

    if ($longestCaptionLen === 0 && $longestPostCaptionLen === 0) {
        echo "  [NO FIELDS] $filename - no fields with captions found\n";
        $noFields++;
        continue;
    }

    // Calculate width needed for caption and postcaption separately
    $captionWidth = ($longestCaptionLen * $captionCharWidth) + $padding;
    $postCaptionWidth = ($longestPostCaptionLen * $postCaptionCharWidth) + $padding;

    // Use the larger of the two
    $calculatedWidth = max($captionWidth, $postCaptionWidth);
    $optimalWidth = (int) round(max($minWidth, min($maxWidth, $calculatedWidth)));

    $oldWidth = $definition['labelColumnWidth'] ?? null;

    // Update or add labelColumnWidth
    $definition['labelColumnWidth'] = $optimalWidth;

    // Write back with pretty formatting
    $newJson = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // Fix indentation to 4 spaces
    $newJson = preg_replace('/^(  +)/m', '$1$1', $newJson);

    file_put_contents($file, $newJson . "\n");

    $oldStr = $oldWidth !== null ? "{$oldWidth}px" : "none (default 150)";
    $reason = $postCaptionWidth > $captionWidth
        ? "postCaption: \"$longestPostCaption\" ({$longestPostCaptionLen} chars)"
        : "caption: \"$longestCaption\" ({$longestCaptionLen} chars)";
    echo "  [UPDATED] $filename - {$oldStr} -> {$optimalWidth}px (widest: $reason)\n";
    $updated++;
}

echo "\n=== Summary ===\n";
echo "Updated: $updated\n";
echo "No fields: $noFields\n";
echo "Total: " . count($files) . "\n";
