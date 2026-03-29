<?php
/**
 * Migration: Convert editForm FormID to form name in reports.json
 *
 * This migration updates reports.json to use form names instead of FormIDs
 * in the editForm field. This aligns with the JSON-based form system where
 * forms are identified by name (e.g., "opleidingen") rather than numeric ID.
 *
 * The mapping is obtained from menu.json which has both form and formId for each item.
 */

use Cma\Services\Logger;
use Cma\Services\MenuService;

require_once dirname(__DIR__) . '/bootstrap.inc';

$result = ['success' => false, 'message' => '', 'updated' => 0, 'skipped' => 0];

try {
    // Load reports.json
    $reportsPath = dirname(__DIR__, 2) . '/data/reports.json';
    if (!file_exists($reportsPath)) {
        // Try external path
        $reportsPath = dirname(__DIR__, 2) . '/data/reports.json';
    }

    if (!file_exists($reportsPath)) {
        throw new Exception('reports.json niet gevonden');
    }

    $reportsJson = file_get_contents($reportsPath);
    $reports = json_decode($reportsJson, true);
    if ($reports === null) {
        throw new Exception('Kan reports.json niet parsen: ' . json_last_error_msg());
    }

    // Build FormID to form name mapping from menu.json
    $formIdToNameMap = [];
    $menuItems = MenuService::getAllItems();
    foreach ($menuItems as $item) {
        if (!empty($item['formId']) && !empty($item['form'])) {
            $formIdToNameMap[(string)$item['formId']] = $item['form'];
        }
    }

    // Also check form definitions for sourceFormId mapping
    $definitionsDir = dirname(__DIR__) . '/assets/forms/definitions/';
    if (is_dir($definitionsDir)) {
        $files = glob($definitionsDir . '*.json');
        foreach ($files as $file) {
            $formJson = file_get_contents($file);
            $formDef = json_decode($formJson, true);
            if ($formDef && !empty($formDef['sourceFormId']) && !empty($formDef['name'])) {
                $formIdToNameMap[(string)$formDef['sourceFormId']] = $formDef['name'];
            }
        }
    }

    // Update each report's editForm field
    $updated = 0;
    $skipped = 0;

    // Make sure reports array exists
    if (!isset($reports['reports']) || !is_array($reports['reports'])) {
        throw new Exception('reports.json heeft geen geldige "reports" array');
    }

    // Use array keys to modify in place (foreach with &$report doesn't work with ?? operator)
    foreach (array_keys($reports['reports']) as $key) {
        $report = &$reports['reports'][$key];

        if (!isset($report['editForm'])) {
            continue;
        }

        $editForm = $report['editForm'];

        // Skip if already a form name (not numeric)
        if (!is_numeric($editForm)) {
            $skipped++;
            continue;
        }

        // Look up form name from FormID
        $formId = (string)$editForm;
        if (isset($formIdToNameMap[$formId])) {
            $report['editForm'] = $formIdToNameMap[$formId];
            $updated++;
        } else {
            // FormID not found in mapping - keep as is but log warning
            Logger::warning('Migration: FormID niet gevonden in menu/form definities', [
                'formId' => $formId,
                'reportId' => $report['id'] ?? 'onbekend'
            ]);
            $skipped++;
        }
        unset($report);
    }

    // Save updated reports.json
    $newJson = json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($newJson === false) {
        throw new Exception('Kan reports.json niet encoderen: ' . json_last_error_msg());
    }

    if (file_put_contents($reportsPath, $newJson) === false) {
        throw new Exception('Kan reports.json niet opslaan');
    }

    $result['success'] = true;
    $result['message'] = "editForm FormIDs geconverteerd naar form namen: $updated bijgewerkt, $skipped overgeslagen";
    $result['updated'] = $updated;
    $result['skipped'] = $skipped;

} catch (Exception $e) {
    $result['message'] = 'Fout: ' . $e->getMessage();
}

// Output for HTTP requests, return for direct inclusion
if (php_sapi_name() !== 'cli' && \App\Library\Request::hasQuery('migration')) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

return $result;
