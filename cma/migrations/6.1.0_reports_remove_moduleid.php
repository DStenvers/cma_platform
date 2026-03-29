<?php
/**
 * Migration: Remove moduleId from reports.json
 *
 * The moduleId field is legacy data from the database export (fkModule).
 * It is NOT functionally used - ReportsService.php uses the 'module' string field
 * for grouping reports. This migration removes the unused moduleId field.
 */

$result = ['success' => false, 'message' => '', 'removed' => 0];

try {
    // Load reports.json from CMA config
    $reportsPath = dirname(__DIR__, 2) . '/data/reports.json';
    if (!file_exists($reportsPath)) {
        throw new Exception("reports.json niet gevonden: $reportsPath");
    }

    $reportsJson = file_get_contents($reportsPath);
    $reports = json_decode($reportsJson, true);
    if ($reports === null) {
        throw new Exception('Kan reports.json niet parsen: ' . json_last_error_msg());
    }

    // Make sure reports array exists
    if (!isset($reports['reports']) || !is_array($reports['reports'])) {
        throw new Exception('reports.json heeft geen geldige "reports" array');
    }

    // Remove moduleId from each report
    $removed = 0;
    foreach (array_keys($reports['reports']) as $key) {
        if (isset($reports['reports'][$key]['moduleId'])) {
            unset($reports['reports'][$key]['moduleId']);
            $removed++;
        }
    }

    // Save updated reports.json
    $newJson = json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($newJson === false) {
        throw new Exception('Kan reports.json niet encoderen: ' . json_last_error_msg());
    }

    $bytesWritten = file_put_contents($reportsPath, $newJson);
    if ($bytesWritten === false) {
        throw new Exception('Kan reports.json niet opslaan');
    }

    $result['success'] = true;
    $result['message'] = "moduleId verwijderd uit $removed rapporten";
    $result['removed'] = $removed;

    echo "moduleId verwijderd uit $removed rapporten\n";
    echo "Bestand opgeslagen: $bytesWritten bytes\n";

} catch (Exception $e) {
    $result['message'] = 'Fout: ' . $e->getMessage();
    echo "ERROR: " . $e->getMessage() . "\n";
}

return $result;
