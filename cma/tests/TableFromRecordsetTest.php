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

    // ------------------------------------------------------------------
    // Additional coverage: HTML escaping, null cells, record-count footer,
    // id/class options.
    // ------------------------------------------------------------------

    public function testCellValuesAreHtmlEscaped(): void
    {
        // A value containing markup must not break the rendered table.
        $rs = $this->makeRecordset([['Naam' => '<b>x</b> & y']]);
        $html = Table::fromRecordset($rs);
        $this->assertStringNotContainsString('<b>x</b>', $html, 'raw markup must not pass through');
        $this->assertStringContainsString('&lt;b&gt;', $html);
        $this->assertStringContainsString('&amp;', $html);
    }

    public function testNullCellRendersNbsp(): void
    {
        // A NULL column value renders as a non-breaking space, not the literal
        // string "null" or a collapsed cell.
        $rs = $this->makeRecordset([['Naam' => null]]);
        $html = Table::fromRecordset($rs);
        $this->assertStringContainsString('&nbsp;', $html);
        $this->assertStringNotContainsString('>null<', $html);
    }

    public function testRecordCountFooterPluralisation(): void
    {
        // Footer reads "1 record" (singular) for one row …
        $one = Table::fromRecordset($this->makeRecordset([['n' => 1]]));
        $this->assertStringContainsString('1 record<', $one);
        // … and "N records" (plural) for several.
        $three = Table::fromRecordset($this->makeRecordset([['n' => 1], ['n' => 2], ['n' => 3]]));
        $this->assertStringContainsString('3 records', $three);
    }

    public function testIdAndClassOptionsAreRendered(): void
    {
        $rs = $this->makeRecordset([['n' => 1]]);
        $html = Table::fromRecordset($rs, ['id' => 'myresult', 'class' => 'grid striped']);
        $this->assertStringContainsString('id="myresult"', $html);
        $this->assertStringContainsString('class="grid striped"', $html);
    }
}
