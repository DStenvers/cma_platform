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
    $existed = Database::tableExistsPDO($conn, 'tblCMAModuleParameters');
    if ($existed) {
        echo "✓ tblCMAModuleParameters bestaat al\n";
    } else {
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
    }

    // Take over a legacy tblModuleParameters once (only into an empty table). Its
    // columns vary per site, so copy row by row with whatever columns it has.
    $count = (int) Database::getFieldValue($conn, 'SELECT COUNT(*) AS n FROM tblCMAModuleParameters', 'n');
    if ($count === 0 && Database::tableExistsPDO($conn, 'tblModuleParameters')) {
        $rs = Database::openRS('SELECT * FROM tblModuleParameters', $conn);
        $copied = 0;
        $cols = ['ModuleNaam', 'Name', 'Caption', 'ParamType', 'Waarde', 'PostCaption', 'Sortorder'];
        while ($rs && !$rs->EOF) {
            $row = [];
            foreach ((array) $rs->fetchAssoc() as $k => $v) {
                if (!is_int($k)) {
                    $row[strtolower((string) $k)] = $v;
                }
            }
            $names = [];
            $values = [];
            foreach ($cols as $col) {
                if (array_key_exists(strtolower($col), $row) && $row[strtolower($col)] !== null) {
                    $names[] = '[' . $col . ']';
                    $values[] = $col === 'Sortorder' ? \App\Library\SQL::postNumber($row[strtolower($col)]) : \App\Library\SQL::postString((string) $row[strtolower($col)]);
                }
            }
            if ($names !== []) {
                $conn->exec('INSERT INTO tblCMAModuleParameters (' . implode(', ', $names) . ') VALUES (' . implode(', ', $values) . ')');
                $copied++;
            }
            $rs->MoveNext();
        }
        echo "✓ $copied parameter(s) overgenomen uit tblModuleParameters\n";
    }
    if (defined('MIGRATION_RUNNING')) return true;
    exit(0);
} catch (\Exception $e) {
    echo "✗ Fout: " . $e->getMessage() . "\n";
    if (defined('MIGRATION_RUNNING')) return false;
    exit(1);
}
