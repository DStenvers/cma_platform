<?php
/**
 * Migration 9.20.0: Rename data/reports.json -> data/cma_reports.json
 *
 * The reports config is renamed to a cma_-prefixed name so all CMA-owned site configs
 * share a clear prefix (cf. 9.16.0 cma_menu, 9.17.0 cma_tools, 9.19.0 cma_branding).
 * ReportsService and ConfigLoader (load('reports')) prefer data/cma_reports.json and
 * fall back to the legacy data/reports.json until this runs, so it is safe to apply at
 * any time. Idempotent: no-op if already renamed.
 */

// $dataDir may be pre-set by a test harness; otherwise derive it from the migration
// location (site root when the runner defines MIGRATION_RUNNING, else the cma/ parent).
if (!isset($dataDir)) {
    $basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
    $dataDir  = $basePath . '/data';
}
$old = $dataDir . '/reports.json';
$new = $dataDir . '/cma_reports.json';

if (file_exists($new)) {
    echo "  Overgeslagen: data/cma_reports.json bestaat al\n";
    // Legacy file lingering next to the new one -> remove it to avoid confusion.
    if (file_exists($old)) {
        @unlink($old);
        echo "  Opgeruimd: oude data/reports.json verwijderd\n";
    }
} elseif (file_exists($old)) {
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    rename($old, $new);
    echo "  Hernoemd: data/reports.json -> data/cma_reports.json\n";
} else {
    echo "  Overgeslagen: geen data/reports.json aanwezig (niets te hernoemen)\n";
}
