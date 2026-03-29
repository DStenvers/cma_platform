<?php
/**
 * Migration: Add activeField to form definitions
 *
 * Scans all JSON form definitions for fields that indicate active/inactive status
 * and adds the activeField property to the form definition.
 *
 * Candidate field names (in priority order):
 * - actief (Dutch for "active")
 * - active (English)
 * - online_indic (Online indicator)
 * - is_actief (Dutch boolean pattern)
 * - is_active (English boolean pattern)
 * - enabled
 * - visible
 * - zichtbaar (Dutch for "visible")
 */

// No bootstrap needed - only JSON file operations

// Active field candidates in priority order
$activeFieldCandidates = [
    'actief',
    'active',
    'online_indic',
    'is_actief',
    'is_active',
    'enabled',
    'visible',
    'zichtbaar'
];

$definitionsDir = __DIR__ . '/../assets/forms/definitions';
$files = glob($definitionsDir . '/*.json');

$updated = 0;
$skipped = 0;
$notFound = 0;

echo "=== Migration: Add activeField to form definitions ===\n\n";
echo "Scanning " . count($files) . " form definitions...\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $json = file_get_contents($file);
    $definition = json_decode($json, true);

    if (!$definition) {
        echo "  [ERROR] Could not parse: $filename\n";
        continue;
    }

    // Skip if activeField is already set
    if (!empty($definition['activeField'])) {
        echo "  [SKIP] $filename - activeField already set: {$definition['activeField']}\n";
        $skipped++;
        continue;
    }

    // Get all field names from the definition
    $fieldNames = [];
    if (!empty($definition['fields'])) {
        foreach ($definition['fields'] as $field) {
            if (!empty($field['name'])) {
                $fieldNames[] = strtolower($field['name']);
            }
        }
    }

    // Also check listQuery for field names (SELECT fields)
    if (!empty($definition['listQuery'])) {
        // Extract field names from SELECT clause
        if (preg_match('/SELECT\s+(.+?)\s+FROM/is', $definition['listQuery'], $matches)) {
            $selectPart = $matches[1];
            // Split by comma, handling aliases
            $selectFields = preg_split('/,\s*/', $selectPart);
            foreach ($selectFields as $selectField) {
                $selectField = trim($selectField);
                // Handle "field AS alias" - use alias
                if (preg_match('/\s+AS\s+(\w+)/i', $selectField, $aliasMatch)) {
                    $fieldNames[] = strtolower($aliasMatch[1]);
                }
                // Handle simple field names
                elseif (preg_match('/^(\w+)$/', $selectField, $simpleMatch)) {
                    $fieldNames[] = strtolower($simpleMatch[1]);
                }
                // Handle table.field
                elseif (preg_match('/\.(\w+)$/', $selectField, $tableFieldMatch)) {
                    $fieldNames[] = strtolower($tableFieldMatch[1]);
                }
            }
        }
    }

    // Find first matching active field candidate
    $foundField = null;
    foreach ($activeFieldCandidates as $candidate) {
        if (in_array(strtolower($candidate), $fieldNames)) {
            // Find the actual field name with correct casing from fields array
            if (!empty($definition['fields'])) {
                foreach ($definition['fields'] as $field) {
                    if (!empty($field['name']) && strtolower($field['name']) === strtolower($candidate)) {
                        $foundField = $field['name'];
                        break;
                    }
                }
            }
            // If not found in fields, use the candidate as-is
            if (!$foundField) {
                $foundField = $candidate;
            }
            break;
        }
    }

    if ($foundField) {
        // Add activeField to the definition
        // Insert after 'name' or at the beginning
        $newDefinition = [];
        $inserted = false;

        foreach ($definition as $key => $value) {
            $newDefinition[$key] = $value;
            // Insert after 'table' or 'idField' for logical ordering
            if (($key === 'table' || $key === 'idField') && !$inserted) {
                // Check if next key is not already activeField
                $keys = array_keys($definition);
                $currentIndex = array_search($key, $keys);
                $nextKey = $keys[$currentIndex + 1] ?? null;
                if ($nextKey !== 'activeField') {
                    $newDefinition['activeField'] = $foundField;
                    $inserted = true;
                }
            }
        }

        // If not inserted yet, add it
        if (!$inserted) {
            $newDefinition['activeField'] = $foundField;
        }

        // Write back with pretty formatting
        $newJson = json_encode($newDefinition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // Fix indentation to 4 spaces
        $newJson = preg_replace('/^(  +)/m', '$1$1', $newJson);

        file_put_contents($file, $newJson);
        echo "  [UPDATED] $filename - added activeField: $foundField\n";
        $updated++;
    } else {
        echo "  [NO MATCH] $filename - no active field candidate found\n";
        $notFound++;
    }
}

echo "\n=== Summary ===\n";
echo "Updated: $updated\n";
echo "Skipped (already set): $skipped\n";
echo "No match found: $notFound\n";
echo "Total: " . count($files) . "\n";
