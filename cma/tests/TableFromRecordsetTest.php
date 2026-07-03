<?php
/**
 * Regression test for Table::fromRecordset().
 *
 * RecordSet::$fields returns a CaseArray (extends ArrayObject) for
 * case-insensitive column access. fromRecordset() previously gated each row on
 * is_array($row), which is false for an object, so EVERY row was silently
 * dropped and any SELECT in the SQL tool (tools_query.php) rendered "0 records".
 * These tests lock in that a CaseArray row is rendered, not discarded.
 *
 * Run with: php tests/TestRunner.php TableFromRecordsetTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Table;
use App\Library\RecordSet;

class TableFromRecordsetTest extends TestCase
{
    /**
     * arrayMode=true buffers an iterator of rows; $rs->fields then yields a
     * CaseArray per row — the exact shape tools_query.php feeds fromRecordset().
     */
    private function makeRecordset(array $rows): RecordSet
    {
        return new RecordSet(new \ArrayIterator($rows), false, true);
    }

    public function testRendersCaseArrayRows(): void
    {
        $rs = $this->makeRecordset([
            ['Id' => 1, 'Naam' => 'Alpha'],
            ['Id' => 2, 'Naam' => 'Beta'],
        ]);
        $html = Table::fromRecordset($rs, ['id' => 'resultaat']);

        $this->assertStringContainsString('Alpha', $html);
        $this->assertStringContainsString('Beta', $html);
        // Header keeps the original column casing (not lowercased by CaseArray).
        $this->assertStringContainsString('Naam', $html);
    }

    public function testSingleRowNotDropped(): void
    {
        $rs = $this->makeRecordset([['cnt' => 1796]]);
        $html = Table::fromRecordset($rs);
        $this->assertStringContainsString('1796', $html);
    }

    public function testEmptyResultStillRendersTable(): void
    {
        $rs = $this->makeRecordset([]);
        $html = Table::fromRecordset($rs);
        // No fatal, and a table element is emitted even with zero rows.
        $this->assertStringContainsString('<TABLE', strtoupper($html));
    }
}
