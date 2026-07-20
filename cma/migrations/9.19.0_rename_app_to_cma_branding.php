<?php
/**
 * Migration 9.19.0: Rename data/app.json -> data/cma_branding.json
 *
 * The site branding/app config is renamed to a cma_-prefixed name so all CMA-owned
 * site configs share a clear prefix (cf. 9.16.0 cma_menu, 9.17.0 cma_tools).
 * ConfigLoader (load('app')), bootstrap.inc's cma_get_app_logo and tools_maintenance
 * prefer data/cma_branding.json and fall back to the legacy data/app.json until this
 * runs, so it is safe to apply at any time. Idempotent: no-op if already renamed.
 */

// $dataDir may be pre-set by a test harness; otherwise derive it from the migration
// location (site root when the runner defines MIGRATION_RUNNING, else the cma/ parent).
if (!isset($dataDir)) {
    $basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
    $dataDir  = $basePath . '/data';
}
$old = $dataDir . '/app.json';
$new = $dataDir . '/cma_branding.json';

if (file_exists($new)) {
    echo "  Overgeslagen: data/cma_branding.json bestaat al\n";
    // Legacy file lingering next to the new one -> remove it to avoid confusion.
    if (file_exists($old)) {
        @unlink($old);
        echo "  Opgeruimd: oude data/app.json verwijderd\n";
    }
} elseif (file_exists($old)) {
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    rename($old, $new);
    echo "  Hernoemd: data/app.json -> data/cma_branding.json\n";
} else {
    echo "  Overgeslagen: geen data/app.json aanwezig (niets te hernoemen)\n";
}
