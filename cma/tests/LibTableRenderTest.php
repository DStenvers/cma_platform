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

    public function testSortMarkerBecomesDataSortAndIsHidden(): void
    {
        $rows = [
            ['ID' => 1, 'Deelnemer' => '<sort:Hagens>Jan Hagens'],
            ['ID' => 2, 'Deelnemer' => '<sort:Abma>Ties Abma'],
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

        // <sort:KEY> becomes the cell's data-sort (honoured by <lib-table>) ...
        $this->assertStringContainsString('data-sort="Hagens"', $html, 'sort key emitted as data-sort');
        // ... and the marker itself is not displayed ...
        $this->assertStringNotContainsString('sort:Hagens', $html, 'sort marker not shown');
        // ... while the remaining display text is kept.
        $this->assertStringContainsString('Jan Hagens', $html, 'display text kept after stripping the marker');
    }

    public function testIdColumnShownWhenShowIdFieldTrue(): void
    {
        $html = $this->render(true);
        // With ShowIDField=true the id column renders too — still exactly 2 rows.
        $this->assertEquals(2, substr_count($html, '<TR ID='), 'still one row per record');
        $this->assertStringContainsString('Jan Jansen', $html);
        $this->assertStringContainsString('Piet Puk', $html);
    }

    /**
     * RowClassField: rapporten die hun eigen <tr> schreven markeerden uitzonderingen met
     * een klasse ("kanniet", "voorlopig"). Zonder deze eigenschap konden ze niet naar
     * LibTable over — dan zou de markering wegvallen of moest de tabel handwerk blijven.
     */
    public function testRowClassFieldZetKlasseOpDeRijEnToontGeenKolom(): void
    {
        $rows = [
            ['ID' => 1, 'Deelnemer' => 'Jan',  'rijklasse' => 'kanniet'],
            ['ID' => 2, 'Deelnemer' => 'Ties', 'rijklasse' => ''],
        ];
        $t = new \LibTable();
        $t->Recordset      = new RecordSet(new \ArrayIterator($rows), false, true);
        $t->IDField        = 'ID';
        $t->ShowIDField    = false;
        $t->RowClassField  = 'rijklasse';
        $t->RowsPerPage    = 99999;
        ob_start();
        $t->Render();
        $html = ob_get_clean();

        $this->assertStringContainsString('kanniet', $html, 'de rijklasse staat niet op de rij');
        $this->assertStringNotContainsString('<TH nowrap  data-filter', $html, 'onverwacht filterattribuut');
        // Het veld is stuurinformatie, geen kolom: het hoort niet in de kop te staan.
        $this->assertStringNotContainsString('Rijklasse', $html, 'het klasse-veld werd als kolom getoond');
        // ... en de tweede rij (lege waarde) krijgt geen extra klasse.
        $this->assertEquals(1, substr_count($html, 'kanniet'), 'de klasse lekte naar een andere rij');
    }

    /**
     * FieldFilters: 'N' zet de filterkeuzelijst per kolom uit, zoals de handgeschreven
     * `<th data-filter=N>` in de rapporten deed voor icoon- en actiekolommen.
     */
    public function testFieldFiltersZetDataFilterOpDeKop(): void
    {
        $rows = [['ID' => 1, 'Actie' => 'x', 'Naam' => 'Jan']];
        $t = new \LibTable();
        $t->Recordset     = new RecordSet(new \ArrayIterator($rows), false, true);
        $t->IDField       = 'ID';
        $t->ShowIDField   = false;
        $t->FieldCaptions = ['Actie', 'Naam'];
        $t->FieldFilters  = [0 => 'N'];
        $t->RowsPerPage   = 99999;
        ob_start();
        $t->Render();
        $html = ob_get_clean();

        $this->assertStringContainsString('data-filter="N"', $html, 'data-filter ontbreekt op de eerste kop');
        $this->assertEquals(1, substr_count($html, 'data-filter'), 'alleen de opgegeven kolom hoort een filter-attribuut te krijgen');
    }
}
