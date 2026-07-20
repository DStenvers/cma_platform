<?php
/**
 * Migration 9.17.0: Rename data/tools.json -> data/cma_tools.json
 *
 * The site tools-launcher config is renamed to a cma_-prefixed name so all
 * CMA-owned site configs share a clear prefix (cma_menu.json, cma_tools.json).
 * tools_catalog.inc prefers data/cma_tools.json and falls back to the legacy
 * data/tools.json until this migration runs, so the rename is safe to apply at
 * any time. Idempotent: no-op if already renamed.
 */

// $dataDir may be pre-set by a test harness; otherwise derive it from the migration
// location (site root when the runner defines MIGRATION_RUNNING, else the cma/ parent).
if (!isset($dataDir)) {
    $basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
    $dataDir  = $basePath . '/data';
}
$old = $dataDir . '/tools.json';
$new = $dataDir . '/cma_tools.json';

if (file_exists($new)) {
    echo "  Overgeslagen: data/cma_tools.json bestaat al\n";
    // Legacy file lingering next to the new one -> remove it to avoid confusion.
    if (file_exists($old)) {
        @unlink($old);
        echo "  Opgeruimd: oude data/tools.json verwijderd\n";
    }
} elseif (file_exists($old)) {
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    rename($old, $new);
    echo "  Hernoemd: data/tools.json -> data/cma_tools.json\n";
} else {
    echo "  Overgeslagen: geen data/tools.json aanwezig (niets te hernoemen)\n";
}
