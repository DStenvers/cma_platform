<?php
/**
 * Regression test for library/classes/class_table.inc — LibTable::Render() row/cell output.
 *
 * Run with: php tests/TestRunner.php LibTableRenderTest
 *
 * Two ASP->PHP conversion bugs collapsed every consumer that renders a data table
 * with ShowIDField=false (e.g. all the front-end rapportage_* reports) to just a
 * header bar plus two pagination arrows:
 *
 *   1. Cell condition (2x, header + body): the converter mangled a De Morgan
 *      negation into `ShowIDField && field == IDField && ...`, so a <TD>/<TH> was
 *      emitted ONLY when ShowIDField was true AND the column WAS the id column.
 *      With ShowIDField=false no cells rendered at all -> empty rows.
 *   2. Missing MoveNext: the converter dropped `RS.MoveNext` from the end of the
 *      Do-While body, so the loop re-rendered the first row until it hit
 *      RowsPerPage (99999 identical rows + spurious "next page" nav arrows).
 *
 * This pins both fixes: a 2-row recordset with the id column hidden renders exactly
 * 2 rows, each with the non-id data cells, no duplicate rows, no nav footer.
 */

require_once __DIR__ . '/TestRunner.php';
if (!defined('ADDATE')) {
    define('ADDATE', 7); // adDate — matches the ADO constant used by Render()
}
require_once dirname(__DIR__) . '/../library/lib_date.inc';
require_once dirname(__DIR__) . '/../library/classes/class_table.inc';

use App\Library\RecordSet;

class LibTableRenderTest extends TestCase
{
    private function render(bool $showId): string
    {
        $rows = [
            ['ID' => 1, 'Opleiding' => 'GZ', 'Deelnemer' => 'Jan Jansen'],
            ['ID' => 2, 'Opleiding' => 'KP', 'Deelnemer' => 'Piet Puk'],
        ];
        $t = new \LibTable();
        $t->Recordset   = new RecordSet(new \ArrayIterator($rows), false, true);
        $t->IDField     = 'ID';
        $t->ShowIDField = $showId;
        $t->ShowCaptions = true;
        $t->RowsPerPage = 99999;
        ob_start();
        $t->Render();
        return ob_get_clean();
    }

    public function testRendersOneRowPerRecordWithCellsWhenIdHidden(): void
    {
        $html = $this->render(false);

        // MoveNext fix: exactly one <TR> per data row (not RowsPerPage duplicates).
        $this->assertEquals(2, substr_count($html, '<TR ID='), 'one row per record, not RowsPerPage duplicates');

        // Cell fix: the non-id columns render as cells, for BOTH rows.
        $this->assertStringContainsString('Jan Jansen', $html, 'first row data cell rendered');
        $this->assertStringContainsString('Piet Puk', $html, 'second row data cell rendered (proves the cursor advanced)');

        // ShowIDField=false: the id column is hidden (its 'Id' caption is not rendered).
        $this->assertStringNotContainsString('>Id</TH>', $html, 'the ID column caption is hidden when ShowIDField is false');

        // No spurious pagination footer / nav arrows (the loop terminated at EOF).
        $this->assertStringNotContainsString('nav_fwd', $html, 'no bogus next-page nav arrow');
        $this->assertStringNotContainsString('<TFOOT', $html, 'no pagination footer for a 2-row set');
    }

    public function testAutoHeadersAndRawHtmlCell(): void
    {
        $rows = [
            ['ID' => 1, 'Opleiding' => 'GZ', 'Status' => '<html><status class="approved">Actief</status>'],
            ['ID' => 2, 'Opleiding' => 'KP', 'Status' => 'Open'],
        ];
        $t = new \LibTable();
        $t->Recordset   = new RecordSet(new \ArrayIterator($rows), false, true);
        $t->IDField     = 'ID';
        $t->ShowIDField = false;
        $t->ShowCaptions = true;
        $t->RowsPerPage = 99999;
        ob_start();
        $t->Render();
        $html = ob_get_clean();

        // Headers auto-generate from field names when no explicit captions are set
        // (empty TableFieldCaptions [] must fall through to the field-name fallback,
        // which also requires `use App\Library\Str`).
        $this->assertStringContainsString('>Opleiding</TH>', $html, 'auto header from field name');
        $this->assertStringContainsString('>Status</TH>', $html);
        $this->assertStringNotContainsString('>Id</TH>', $html, 'ID column header hidden');

        // A cell value prefixed with the <html> marker renders its inner HTML raw.
        $this->assertStringContainsString('<status class="approved">Actief</status>', $html, '<html> cell rendered raw');
        $this->assertStringNotContainsString('&lt;status', $html, 'raw HTML cell not encoded');
    }

    public function testIdColumnShownWhenShowIdFieldTrue(): void
    {
        $html = $this->render(true);
        // With ShowIDField=true the id column renders too — still exactly 2 rows.
        $this->assertEquals(2, substr_count($html, '<TR ID='), 'still one row per record');
        $this->assertStringContainsString('Jan Jansen', $html);
        $this->assertStringContainsString('Piet Puk', $html);
    }
}
