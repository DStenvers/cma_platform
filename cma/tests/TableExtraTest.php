<?php
/**
 * Unit tests for App\Library\Table::fromArray() (and option handling).
 *
 * Table::fromRecordset() is covered separately by TableFromRecordsetTest;
 * this suite focuses on fromArray(), which renders an HTML table from an
 * array of associative rows.
 *
 * Covers: empty array, single/multiple rows, header derivation from the first
 * row, missing/extra keys across rows, HTML-escaping of headers and cell
 * values, null and numeric values, and the id/class/row-class options.
 *
 * Run with: php tests/TestRunner.php TableExtraTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Table;

class TableExtraTest extends TestCase
{
    // --- empty / structural ---------------------------------------------

    public function testEmptyArray(): void
    {
        $html = Table::fromArray([]);
        $this->assertStringContainsString('<TABLE', $html);
        $this->assertStringContainsString('<thead>', $html);
        $this->assertStringContainsString('<tbody></tbody>', $html);
        // No data cells and a zero record count.
        $this->assertStringNotContainsString('<TD>', $html);
        $this->assertStringContainsString('0 records', $html);
    }

    public function testSingleRow(): void
    {
        $html = Table::fromArray([['Naam' => 'Alpha', 'Id' => 1]]);
        // Header cells come from the first row's string keys.
        $this->assertStringContainsString('<TH>Naam</TH>', $html);
        $this->assertStringContainsString('<TH>Id</TH>', $html);
        // Data cells.
        $this->assertStringContainsString('<TD>Alpha</TD>', $html);
        $this->assertStringContainsString('<TD>1</TD>', $html);
        // Singular record label.
        $this->assertStringContainsString('1 record<br>', $html);
    }

    public function testMultipleRowsAndAlternatingClasses(): void
    {
        $html = Table::fromArray([
            ['Naam' => 'Alpha'],
            ['Naam' => 'Beta'],
        ]);
        $this->assertStringContainsString('<TD>Alpha</TD>', $html);
        $this->assertStringContainsString('<TD>Beta</TD>', $html);
        // Row 1 = odd, row 2 = even.
        $this->assertStringContainsString('class="odd"', $html);
        $this->assertStringContainsString('class="even"', $html);
        $this->assertStringContainsString('2 records', $html);
    }

    // --- escaping --------------------------------------------------------

    public function testCellValueIsHtmlEscaped(): void
    {
        $html = Table::fromArray([['x' => '<script>alert(1)</script>']]);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function testHeaderNameIsHtmlEscaped(): void
    {
        $html = Table::fromArray([['a<b>' => 'v']]);
        $this->assertStringContainsString('<TH>a&lt;b&gt;</TH>', $html);
        $this->assertStringNotContainsString('<TH>a<b></TH>', $html);
    }

    public function testAmpersandAndQuotesEscaped(): void
    {
        $html = Table::fromArray([['c' => 'Tom & "Jerry"']]);
        $this->assertStringContainsString('Tom &amp; &quot;Jerry&quot;', $html);
    }

    // --- null / numeric --------------------------------------------------

    public function testNullValueRendersNbsp(): void
    {
        $html = Table::fromArray([['a' => null]]);
        $this->assertStringContainsString('<TD>&nbsp;</TD>', $html);
    }

    public function testNumericValuesRendered(): void
    {
        $html = Table::fromArray([['n' => 42, 'f' => 3.5, 'zero' => 0]]);
        $this->assertStringContainsString('<TD>42</TD>', $html);
        $this->assertStringContainsString('<TD>3.5</TD>', $html);
        // Numeric 0 is not null, so it renders as "0", not &nbsp;.
        $this->assertStringContainsString('<TD>0</TD>', $html);
    }

    // --- missing / extra keys across rows -------------------------------

    public function testMissingKeyInLaterRowRendersNbsp(): void
    {
        // Columns are fixed from the first row: [a, b]. Second row lacks 'b'.
        $html = Table::fromArray([
            ['a' => 1, 'b' => 2],
            ['a' => 3],
        ]);
        $this->assertStringContainsString('<TD>3</TD>', $html);
        // The missing 'b' cell of row 2 falls back to &nbsp;.
        $this->assertStringContainsString('<TD>&nbsp;</TD>', $html);
        $this->assertStringContainsString('2 records', $html);
    }

    public function testExtraKeyInLaterRowIsIgnored(): void
    {
        // Header columns come only from the first row, so 'extra' is never shown.
        $html = Table::fromArray([
            ['a' => 1],
            ['a' => 2, 'extra' => 'HIDDEN'],
        ]);
        $this->assertStringNotContainsString('HIDDEN', $html);
        $this->assertStringNotContainsString('<TH>extra</TH>', $html);
    }

    // --- options ---------------------------------------------------------

    public function testDefaultIdAndClass(): void
    {
        $html = Table::fromArray([['a' => 1]]);
        $this->assertStringContainsString('id="resultaat"', $html);
        $this->assertStringContainsString('class="filtering"', $html);
    }

    public function testCustomIdAndClass(): void
    {
        $html = Table::fromArray([['a' => 1]], ['id' => 'myid', 'class' => 'myclass']);
        $this->assertStringContainsString('id="myid"', $html);
        $this->assertStringContainsString('class="myclass"', $html);
    }

    public function testCustomCellSpacingAndPadding(): void
    {
        $html = Table::fromArray([['a' => 1]], ['cellspacing' => 9, 'cellpadding' => 7]);
        $this->assertStringContainsString('CELLSPACING="9"', $html);
        $this->assertStringContainsString('CELLPADDING="7"', $html);
    }

    public function testCustomRowAndHeaderClasses(): void
    {
        $html = Table::fromArray(
            [['a' => 1], ['a' => 2]],
            ['headerClass' => 'hdr', 'oddClass' => 'o', 'evenClass' => 'e']
        );
        $this->assertStringContainsString('class="hdr"', $html);
        $this->assertStringContainsString('class="o"', $html);
        $this->assertStringContainsString('class="e"', $html);
    }

    // --- limitation note -------------------------------------------------

    public function testNumericKeyedFirstRowProducesNoColumns(): void
    {
        // fromArray only derives columns from *string* keys of the first row.
        // A list-of-lists first row (numeric keys) yields no header columns
        // and no data cells (unlike fromRecordset, which falls back to numeric).
        $html = Table::fromArray([['Alpha', 'Beta']]);
        $this->assertStringNotContainsString('<TH>', $html);
        $this->assertStringNotContainsString('<TD>', $html);
        // Row is still counted.
        $this->assertStringContainsString('1 record<br>', $html);
    }
}
