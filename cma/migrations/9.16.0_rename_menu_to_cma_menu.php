<?php
/**
 * Migration 9.16.0: Rename data/menu.json -> data/cma_menu.json
 *
 * The site menu config is renamed to a cma_-prefixed name so all CMA-owned site
 * configs share a clear prefix. MenuService prefers data/cma_menu.json and falls
 * back to the legacy data/menu.json until this migration runs, so the rename is
 * safe to apply at any time. Idempotent: no-op if already renamed.
 */

// $dataDir may be pre-set by a test harness; otherwise derive it from the migration
// location (site root when the runner defines MIGRATION_RUNNING, else the cma/ parent).
if (!isset($dataDir)) {
    $basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
    $dataDir  = $basePath . '/data';
}
$old      = $dataDir . '/menu.json';
$new      = $dataDir . '/cma_menu.json';

if (file_exists($new)) {
    echo "  Overgeslagen: data/cma_menu.json bestaat al\n";
    // Legacy file lingering next to the new one -> remove it to avoid confusion.
    if (file_exists($old)) {
        @unlink($old);
        echo "  Opgeruimd: oude data/menu.json verwijderd\n";
    }
} elseif (file_exists($old)) {
    if (!is_dir($dataDir)) {
        @mkdir($dataDir, 0755, true);
    }
    rename($old, $new);
    echo "  Hernoemd: data/menu.json -> data/cma_menu.json\n";
} else {
    echo "  Overgeslagen: geen data/menu.json aanwezig (niets te hernoemen)\n";
}
