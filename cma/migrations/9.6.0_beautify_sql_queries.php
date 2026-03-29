<?php
/**
 * Migration: Beautify SQL queries in form definitions
 *
 * Formats all listQuery values in form definition JSON files
 * for better readability. Uses the same formatting rules as
 * SqlUtils.formatSql() in library/sql-utils.js.
 */

/**
 * Format/beautify a SQL query string (PHP equivalent of SqlUtils.formatSql)
 */
function formatSql(string $sql): string {
    if (empty(trim($sql))) return $sql;

    // Normalize whitespace (including \r\n)
    $formatted = preg_replace('/\s+/', ' ', $sql);
    $formatted = trim($formatted);

    // Add newlines before major keywords
    $majorKeywords = ['SELECT', 'FROM', 'WHERE', 'ORDER BY', 'GROUP BY', 'HAVING', 'UNION'];
    foreach ($majorKeywords as $kw) {
        $formatted = preg_replace('/\s+(' . $kw . ')\b/i', "\n$1", $formatted);
    }

    // Indent AND/OR
    $formatted = preg_replace('/\s+(AND|OR)\s+/i', "\n  $1 ", $formatted);

    // Indent JOINs
    $formatted = preg_replace('/\s+(INNER JOIN|LEFT JOIN|RIGHT JOIN|FULL JOIN)\s+/i', "\n  $1 ", $formatted);

    // Handle ON clauses
    $formatted = preg_replace('/\s+ON\s+/i', "\n    ON ", $formatted);

    // Clean up multiple newlines
    $formatted = preg_replace('/\n{3,}/', "\n\n", $formatted);

    return trim($formatted);
}

$cmaDir = __DIR__ . '/../assets/forms/definitions';
$appDir = __DIR__ . '/../../assets/forms';

$cmaFiles = glob($cmaDir . '/*.json') ?: [];
$appFiles = glob($appDir . '/*.json') ?: [];
$files = array_merge($cmaFiles, $appFiles);

$updated = 0;
$skipped = 0;
$errors = 0;

echo "=== Migration: Beautify SQL queries in form definitions ===\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    $json = file_get_contents($file);
    $definition = json_decode($json, true);

    if (!$definition) {
        echo "  [ERROR] Could not parse: $filename\n";
        $errors++;
        continue;
    }

    $listQuery = $definition['listQuery'] ?? '';
    if (empty($listQuery)) {
        $skipped++;
        continue;
    }

    $formatted = formatSql($listQuery);

    // Skip if nothing changed
    if ($formatted === $listQuery) {
        $skipped++;
        continue;
    }

    $definition['listQuery'] = $formatted;

    $newJson = json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $newJson = preg_replace('/^(  +)/m', '$1$1', $newJson);
    file_put_contents($file, $newJson . "\n");

    echo "  [UPDATED] $filename\n";
    $updated++;
}

echo "\n=== Summary ===\n";
echo "Updated: $updated\n";
echo "Skipped (no change or no query): $skipped\n";
echo "Errors: $errors\n";
echo "Total: " . count($files) . "\n";
