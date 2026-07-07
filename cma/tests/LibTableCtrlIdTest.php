<?php
/**
 * Regression test for library/classes/class_table.inc — the file-scope
 * $Table_CtrlID counter.
 *
 * Run with: php tests/TestRunner.php LibTableCtrlIdTest
 *
 * $Table_CtrlID is a file-scope counter bumped once per LibTable instance so the
 * generated per-table JS names (row_act<n>/sorttable<n>) stay unique on a page.
 * The ASP->PHP converter dropped the `global $Table_CtrlID;` declarations in
 * Render()/__construct(), so the constructor warned "Undefined variable
 * $Table_CtrlID" and the increment never reached the shared value. This pins the
 * fix: constructing bumps the global, no warning.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/../library/classes/class_table.inc';

class LibTableCtrlIdTest extends TestCase
{
    public function testConstructIncrementsSharedCounterWithoutWarning(): void
    {
        $GLOBALS['Table_CtrlID'] = 0;

        $warnings = [];
        set_error_handler(function ($n, $s) use (&$warnings) {
            if (stripos($s, 'Table_CtrlID') !== false) {
                $warnings[] = $s;
            }
            return true;
        });
        try {
            new \LibTable();
            new \LibTable();
        } finally {
            restore_error_handler();
        }

        $this->assertEmpty($warnings, 'no "Undefined variable $Table_CtrlID" warning');
        $this->assertEquals(2, $GLOBALS['Table_CtrlID'], 'each construct bumps the shared counter');
    }
}
