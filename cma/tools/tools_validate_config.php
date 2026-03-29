<?php
/**
 * Config Validation Script
 *
 * Validates all JSON configuration files against their schemas.
 * Reports any structural issues or missing required fields.
 */

require_once __DIR__ . '/../bootstrap.inc';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use Cma\SecurityHelper;

// Security check for browser access
if (php_sapi_name() !== 'cli' && !SecurityHelper::isAdmin()) {
    die("Access denied. Admin login required.");
}

$isCli = php_sapi_name() === 'cli';
$nl = $isCli ? "\n" : "<br>\n";

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Config Validation</title></head><body><pre>";
}

echo "=== JSON Configuration Validator ==={$nl}{$nl}";

$configPath = __DIR__ . '/config/';
$schemaPath = $configPath . 'schema/';

$totalErrors = 0;
$totalWarnings = 0;

// Config files to validate
$configs = [
    'databases' => ['required' => ['databases'], 'itemFields' => ['id', 'name', 'title', 'connectionString', 'type']],
    'modules' => ['required' => ['modules'], 'itemFields' => ['id', 'name', 'database']],
    'menu' => ['required' => ['menus'], 'itemFields' => ['id', 'name']],
    'control-types' => ['required' => ['controlTypes'], 'itemFields' => ['id', 'name']],
    'app' => ['required' => ['company', 'settings'], 'itemFields' => null],
    'reports' => ['required' => ['reports'], 'itemFields' => ['id', 'title']],
    'data-sources' => ['required' => ['dataSources'], 'itemFields' => ['id', 'name', 'query']],
];

foreach ($configs as $name => $rules) {
    echo "Validating {$name}.json...{$nl}";

    $file = \Cma\ConfigLoader::getFilePath($name);
    $errors = [];
    $warnings = [];

    // Check file exists
    if (!file_exists($file)) {
        echo "  ERROR: File not found{$nl}";
        $totalErrors++;
        continue;
    }

    // Check JSON validity
    $content = file_get_contents($file);
    $data = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "  ERROR: Invalid JSON - " . json_last_error_msg() . "{$nl}";
        $totalErrors++;
        continue;
    }

    // Check required top-level keys
    foreach ($rules['required'] as $key) {
        if (!isset($data[$key])) {
            $errors[] = "Missing required key: {$key}";
        }
    }

    // Check item fields for array configs
    if ($rules['itemFields'] !== null) {
        $arrayKey = $rules['required'][0];
        $items = $data[$arrayKey] ?? [];

        foreach ($items as $idx => $item) {
            foreach ($rules['itemFields'] as $field) {
                if (!isset($item[$field])) {
                    $warnings[] = "Item {$idx}: missing field '{$field}'";
                }
            }

            // Check for duplicate IDs
            if (isset($item['id'])) {
                $duplicates = array_filter($items, fn($i) => ($i['id'] ?? null) === $item['id']);
                if (count($duplicates) > 1 && !in_array("Duplicate ID: {$item['id']}", $errors)) {
                    $errors[] = "Duplicate ID: {$item['id']}";
                }
            }
        }

        echo "  Items: " . count($items) . "{$nl}";
    }

    // Check schema reference
    if (isset($data['$schema'])) {
        $schemaRef = $data['$schema'];
        $schemaFile = $configPath . ltrim($schemaRef, './');
        if (!file_exists($schemaFile)) {
            $warnings[] = "Schema file not found: {$schemaRef}";
        }
    } else {
        $warnings[] = "No \$schema reference";
    }

    // Report errors
    if (count($errors) > 0) {
        foreach ($errors as $error) {
            echo "  ERROR: {$error}{$nl}";
            $totalErrors++;
        }
    }

    // Report warnings
    if (count($warnings) > 0) {
        foreach ($warnings as $warning) {
            echo "  WARNING: {$warning}{$nl}";
            $totalWarnings++;
        }
    }

    if (count($errors) === 0 && count($warnings) === 0) {
        echo "  OK{$nl}";
    }

    echo "{$nl}";
}

// Check schema files exist
echo "Checking schema files...{$nl}";
$schemaFiles = ['databases', 'modules', 'menu', 'control-types', 'app', 'reports', 'data-sources'];
foreach ($schemaFiles as $schema) {
    $file = $schemaPath . $schema . '.schema.json';
    if (file_exists($file)) {
        // Validate schema JSON
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  ERROR: {$schema}.schema.json - Invalid JSON{$nl}";
            $totalErrors++;
        } else {
            echo "  OK: {$schema}.schema.json{$nl}";
        }
    } else {
        echo "  MISSING: {$schema}.schema.json{$nl}";
        $totalWarnings++;
    }
}

// Summary
echo "{$nl}=== Summary ==={$nl}";
echo "Errors: {$totalErrors}{$nl}";
echo "Warnings: {$totalWarnings}{$nl}";

if ($totalErrors > 0) {
    echo "{$nl}FAILED: Please fix the errors above.{$nl}";
} elseif ($totalWarnings > 0) {
    echo "{$nl}PASSED with warnings.{$nl}";
} else {
    echo "{$nl}ALL VALIDATIONS PASSED!{$nl}";
}

if (!$isCli) {
    echo "</pre></body></html>";
}
