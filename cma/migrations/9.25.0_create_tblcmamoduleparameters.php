<?php
/**
 * Migration 9.25.0: Create tblCMAModuleParameters
 *
 * Per-module settings (module, key, value, type, caption) as the classic CMA
 * kept in tblModuleParameters (mod_maint.asp) and templates read through
 * lib_XMLSnippets. Edited with the moduleparameters form; read in site code with
 * Cma\Services\ModuleParameters::get('module', 'key').
 *
 * Idempotent: skips when the table exists. A legacy tblModuleParameters is
 * copied over once, so existing values survive the rename.
 */
use App\Library\Database;

$basePath = defined('MIGRATION_RUNNING') ? dirname(__DIR__) : __DIR__;
if (strpos($basePath, 'migrations') !== false) {
    $basePath = dirname($basePath);
}
require_once $basePath . '/bootstrap.inc';

try {
    $conn = Database::getConnection('data');
    if ($conn === null) {
        echo "✗ Kan geen verbinding maken met database 'data'\n";
        if (defined('MIGRATION_RUNNING')) return false;
        exit(1);
    }
    if (Database::tableExistsPDO($conn, 'tblCMAModuleParameters')) {
        echo "✓ tblCMAModuleParameters bestaat al\n";
        if (defined('MIGRATION_RUNNING')) return true;
        exit(0);
    }
    // Schema mirrors cma/assets/forms/definitions/moduleparameters.json
    Database::executeDdl($conn, "CREATE TABLE tblCMAModuleParameters (
        ID AUTOINCREMENT PRIMARY KEY,
        ModuleNaam VARCHAR(50),
        Name VARCHAR(50),
        Caption VARCHAR(100),
        ParamType VARCHAR(10),
        Waarde MEMO,
        PostCaption VARCHAR(100),
        Sortorder INTEGER
    )");
    try {
        Database::executeDdl($conn, "CREATE INDEX tblCMAModuleParameters_mod ON tblCMAModuleParameters (ModuleNaam, Name)");
    } catch (\Exception $e) {
        echo "  (index niet aangemaakt: " . $e->getMessage() . ")\n";
    }
    echo "✓ tblCMAModuleParameters aangemaakt\n";

    if (Database::tableExistsPDO($conn, 'tblModuleParameters')) {
        $n = $conn->exec("INSERT INTO tblCMAModuleParameters (ModuleNaam, Name, Caption, ParamType, Waarde, PostCaption, Sortorder)
            SELECT ModuleNaam, Name, Caption, ParamType, Waarde, PostCaption, Sortorder FROM tblModuleParameters");
        echo "✓ " . (int)$n . " parameter(s) overgenomen uit tblModuleParameters\n";
    }
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);
} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
