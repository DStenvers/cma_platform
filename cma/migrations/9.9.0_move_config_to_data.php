<?php
/**
 * Migration 9.9.0: Reorganize directory structure
 *
 * - app/css/errorhandler.css -> library/css/errorhandler.css
 * - config/constants.php -> library/constants.php
 * - config/global_functions.php -> deleted (empty placeholder)
 * - cma/config/ site-specific configs (app, databases, menu, reports) -> data/
 * - cma/config/ generic configs (control-types, schema/, migrations) -> cma/
 * - cma/data/reports/ -> data/reports/
 * - Cleans up app/, config/, cma/config/, cma/data/
 */

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__, 2) : dirname(__DIR__);
$configDir = $basePath . '/cma/config';
$dataDir = $basePath . '/data';
$cmaDir = $basePath . '/cma';
$libraryDir = $basePath . '/library';

$moved = 0;
$skipped = 0;

// --- Move app/css/errorhandler.css -> library/css/errorhandler.css ---
$ehSrc = $basePath . '/app/css/errorhandler.css';
$ehDest = $libraryDir . '/css/errorhandler.css';
if (file_exists($ehSrc)) {
    if (file_exists($ehDest)) {
        unlink($ehSrc);
        echo "  Overgeslagen: errorhandler.css (bestaat al in library/css/)\n";
        $skipped++;
    } else {
        if (!is_dir($libraryDir . '/css')) mkdir($libraryDir . '/css', 0755, true);
        rename($ehSrc, $ehDest);
        echo "  Verplaatst: app/css/errorhandler.css -> library/css/\n";
        $moved++;
    }
    // Clean up empty app/ directories
    @rmdir($basePath . '/app/css');
    @rmdir($basePath . '/app/library');
    @rmdir($basePath . '/app');
}

// --- Move config/constants.php -> library/constants.php ---
$constSrc = $basePath . '/config/constants.php';
$constDest = $libraryDir . '/constants.php';
if (file_exists($constSrc)) {
    if (file_exists($constDest)) {
        unlink($constSrc);
        echo "  Overgeslagen: constants.php (bestaat al in library/)\n";
        $skipped++;
    } else {
        rename($constSrc, $constDest);
        echo "  Verplaatst: config/constants.php -> library/\n";
        $moved++;
    }
}

// --- Delete config/global_functions.php (empty placeholder) ---
$gfSrc = $basePath . '/config/global_functions.php';
if (file_exists($gfSrc)) {
    unlink($gfSrc);
    echo "  Verwijderd: config/global_functions.php (lege placeholder)\n";
}

// Clean up empty config/ directory
@rmdir($basePath . '/config');

// --- Move cma/config/ contents ---
if (!is_dir($configDir)) {
    echo "✓ cma/config/ bestaat niet meer - migratie al uitgevoerd\n";
    echo "✓ Migratie voltooid: {$moved} verplaatst, {$skipped} overgeslagen\n";
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);
}

// Ensure data/ directory exists
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
    echo "  Aangemaakt: data/\n";
}

// Site-specific JSON files -> data/
$siteFiles = ['app.json', 'databases.json', 'menu.json', 'reports.json'];
foreach ($siteFiles as $filename) {
    $src = $configDir . '/' . $filename;
    $dest = $dataDir . '/' . $filename;
    if (!file_exists($src)) continue;

    if (file_exists($dest)) {
        echo "  Overgeslagen: {$filename} (bestaat al in data/)\n";
        unlink($src);
        $skipped++;
    } else {
        rename($src, $dest);
        echo "  Verplaatst: {$filename} -> data/\n";
        $moved++;
    }
}

// Generic JSON files -> cma/
$genericFiles = ['control-types.json', 'migrations.json'];
foreach ($genericFiles as $filename) {
    $src = $configDir . '/' . $filename;
    if (!file_exists($src)) continue;

    // migrations.json goes to cma/migrations/
    if ($filename === 'migrations.json') {
        $dest = $cmaDir . '/migrations/' . $filename;
    } else {
        $dest = $cmaDir . '/' . $filename;
    }

    if (file_exists($dest)) {
        echo "  Overgeslagen: {$filename} (bestaat al)\n";
        unlink($src);
        $skipped++;
    } else {
        rename($src, $dest);
        echo "  Verplaatst: {$filename} -> cma/\n";
        $moved++;
    }
}

// Move schema/ -> cma/schema/
if (is_dir($configDir . '/schema')) {
    if (!is_dir($cmaDir . '/schema')) {
        rename($configDir . '/schema', $cmaDir . '/schema');
        echo "  Verplaatst: schema/ -> cma/schema/\n";
        $moved++;
    } else {
        // Merge: copy any missing schema files
        $schemaFiles = glob($configDir . '/schema/*.json');
        foreach ($schemaFiles as $file) {
            $filename = basename($file);
            $dest = $cmaDir . '/schema/' . $filename;
            if (!file_exists($dest)) {
                rename($file, $dest);
                echo "  Verplaatst: schema/{$filename}\n";
                $moved++;
            } else {
                unlink($file);
            }
        }
        @rmdir($configDir . '/schema');
    }
}

// Move reports/ output data -> data/reports/
if (is_dir($configDir . '/../data/reports')) {
    $reportsSource = $configDir . '/../data/reports';
    if (!is_dir($dataDir . '/reports')) {
        rename($reportsSource, $dataDir . '/reports');
        echo "  Verplaatst: reports/ -> data/reports/\n";
        $moved++;
    }
}

// Clean up empty cma/config/ directory
$remaining = glob($configDir . '/*');
if (empty($remaining)) {
    rmdir($configDir);
    echo "  Verwijderd: cma/config/\n";
} else {
    echo "  Let op: cma/config/ bevat nog bestanden: " . implode(', ', array_map('basename', $remaining)) . "\n";
}

// Clean up cma/data/ if now empty
$cmaDataDir = $basePath . '/cma/data';
if (is_dir($cmaDataDir)) {
    $remaining = glob($cmaDataDir . '/*');
    $remaining = array_filter($remaining, function($f) { return basename($f) !== '.gitkeep'; });
    if (empty($remaining)) {
        @unlink($cmaDataDir . '/.gitkeep');
        @rmdir($cmaDataDir);
        echo "  Verwijderd: cma/data/\n";
    }
}

echo "✓ Migratie voltooid: {$moved} verplaatst, {$skipped} overgeslagen\n";
if (defined('MIGRATION_RUNNING')) return true;
exit(0);
