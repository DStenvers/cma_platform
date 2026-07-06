<?php
/**
 * Tests for Cma\Services\JsonFormService::getTableHtml — the LIST/TABLE
 * rendering path a user sees on every form list. This is a top source of
 * real bugs: wrong columns, empty-result crashes, record-count undercount,
 * unescaped cell values.
 *
 * Run with: php tests/TestRunner.php ListTableRenderTest
 *
 * Integration-style: TestHarness short-circuits auth, DB-connection lookup
 * and form-def loading so the REAL production code in getTableHtml runs
 * without a CMA or a database. A StubConnection feeds query results.
 *
 * IMPORTANT — query order getTableHtml consumes (initial load, no listQuery):
 *   1) COUNT(*) query  (JsonFormService.php ~line 372)
 *   2) data-rows query (JsonFormService.php ~line 386)
 * Each Database::openRS() does prepare()+execute(), which pops ONE result
 * off the StubConnection queue. So enqueue the COUNT result FIRST, then the
 * data rows. A COUNT(*) always returns exactly one row holding the total,
 * so the count fixture is [['cnt' => N]] even when zero data rows follow.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/TableService.php';
require_once dirname(__DIR__) . '/classes/Services/JsonFormService.php';
// ListMode (used when building the success response) is declared in FormDataProvider.php.
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';

use App\Library\Application;
use Cma\Services\JsonFormService;

class ListTableRenderTest extends TestCase
{
    private StubConnection $conn;

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();

        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
    }

    public function tearDown(): void
    {
        TestHarness::reset();
        $_COOKIE = [];
    }

    /**
     * Build + inject a minimal JSON form definition. Shape matches what
     * JsonFormLoader produces (see cma/assets/forms/definitions/*.json):
     * a top-level '_json' with table/idField/database/fields. loadRaw()
     * returns the '_json' payload (TestHarness wires the 'raw_' cache key).
     */
    private function injectForm(string $name, array $fields, array $extra = []): void
    {
        $json = array_merge([
            'name'     => $name,
            'title'    => 'Test List',
            'table'    => 'tblTest',
            'idField'  => 'ID',
            'database' => 'data',
            'fields'   => $fields,
        ], $extra);
        TestHarness::injectFormDef($name, ['_json' => $json]);
    }

    /**
     * Run a callable with warnings/notices promoted to exceptions so a test
     * can assert "no Undefined key / Undefined array key warning was emitted".
     * Restores the previous handler afterwards.
     */
    private function withStrictErrors(callable $fn)
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            // Respect the @-operator / error_reporting level.
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            return $fn();
        } finally {
            restore_error_handler();
        }
    }

    // ------------------------------------------------------------------
    // 1. Happy path — N columns, M rows
    // ------------------------------------------------------------------

    public function testHappyPathRendersHeaderPerColumnAndRowPerRecord(): void
    {
        $this->injectForm('list_happy', [
            ['name' => 'naam',   'type' => 'textbox', 'caption' => 'Naam'],
            ['name' => 'plaats', 'type' => 'textbox', 'caption' => 'Plaats'],
        ]);

        $this->conn->enqueueResult([['cnt' => 3]]);           // COUNT(*)
        $this->conn->enqueueResult([                           // data rows
            ['ID' => 1, 'naam' => 'Alice', 'plaats' => 'Amsterdam'],
            ['ID' => 2, 'naam' => 'Bob',   'plaats' => 'Rotterdam'],
            ['ID' => 3, 'naam' => 'Carol', 'plaats' => 'Utrecht'],
        ]);

        $result = JsonFormService::getTableHtml('list_happy', null, []);

        $this->assertTrue($result['success'] ?? false, 'Happy path must succeed: ' . ($result['error'] ?? ''));
        $html = $result['html'];

        // A real <table> with the listtable class wrapped in <lib-table>.
        $this->assertStringContainsString('<lib-table>', $html);
        $this->assertStringContainsString('class="listtable', $html);

        // One header cell per visible column, in definition order.
        $this->assertStringContainsString('<th data-field="naam"', $html);
        $this->assertStringContainsString('<th data-field="plaats"', $html);

        // One row per record.
        $this->assertEquals(3, substr_count($html, 'class="listrow'), 'One <tr class="listrow"> per record');
        $this->assertEquals(3, $result['count'], 'count must equal number of rendered rows');

        // Cell values present.
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('Utrecht', $html);
    }

    // ------------------------------------------------------------------
    // 2. Empty result set — valid empty table, no crash/warning
    // ------------------------------------------------------------------

    public function testEmptyResultSetRendersValidEmptyTable(): void
    {
        $this->injectForm('list_empty', [
            ['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam'],
        ]);

        // COUNT(*) always returns a single row — here the total is 0.
        $this->conn->enqueueResult([['cnt' => 0]]);
        $this->conn->enqueueResult([]); // zero data rows

        $result = $this->withStrictErrors(fn() => JsonFormService::getTableHtml('list_empty', null, []));

        $this->assertTrue($result['success'] ?? false, 'Empty result must still be a success, not a crash');
        $this->assertEquals(0, $result['count'], 'count must be 0 for an empty result set');

        $html = $result['html'];
        // Still a structurally valid table.
        $this->assertStringContainsString('<lib-table>', $html);
        $this->assertStringContainsString('class="listtable', $html);
        $this->assertStringContainsString('</table></lib-table>', $html);
        // And the friendly empty-state message.
        $this->assertStringContainsString('Geen gegevens gevonden', $html);
        // No rendered data rows.
        $this->assertEquals(0, substr_count($html, 'class="listrow'));
    }

    // ------------------------------------------------------------------
    // 3. Record-count correctness (pager-undercount bug class)
    // ------------------------------------------------------------------

    public function testGrandTotalComesFromCountQueryNotRenderedRows(): void
    {
        // Under paging the data query returns only a page of rows while the
        // COUNT(*) reports the grand total. Assert the count-query value is
        // surfaced independently of how many data rows come back.
        $this->injectForm('list_paged', [
            ['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam'],
        ]);

        $this->conn->enqueueResult([['cnt' => 100]]);   // grand total from COUNT(*)
        $this->conn->enqueueResult([                     // only 3 rows on this page
            ['ID' => 1, 'naam' => 'Alice'],
            ['ID' => 2, 'naam' => 'Bob'],
            ['ID' => 3, 'naam' => 'Carol'],
        ]);

        $result = JsonFormService::getTableHtml('list_paged', null, []);

        $this->assertTrue($result['success'] ?? false);
        // The grand total lives in 'totalCount' and reflects the COUNT query,
        // NOT count($rows). This is the field a pager must read to avoid the
        // "records 1-3 van 100" undercount.
        $this->assertEquals(100, $result['totalCount'], "totalCount must come from the COUNT(*) query, independent of rendered rows");
        // 'count' is the number of rows in THIS page/response (rendered rows).
        $this->assertEquals(3, $result['count'], "count is the rendered-rows-on-this-page value");
    }

    // ------------------------------------------------------------------
    // 4. Memo / long-text columns excluded from the table
    // ------------------------------------------------------------------

    public function testMemoColumnIsExcludedFromTable(): void
    {
        // Access ODBC can't render a memo alongside other columns, so the
        // default-column builder must drop memo fields entirely.
        $this->injectForm('list_memo', [
            ['name' => 'naam',    'type' => 'textbox', 'caption' => 'Naam'],
            ['name' => 'notitie', 'type' => 'memo',    'caption' => 'Notitie'],
        ]);

        $this->conn->enqueueResult([['cnt' => 1]]);
        $this->conn->enqueueResult([['ID' => 1, 'naam' => 'Alice']]);

        $result = JsonFormService::getTableHtml('list_memo', null, []);
        $html = $result['html'];

        $this->assertStringContainsString('<th data-field="naam"', $html, 'Normal text column must render');
        $this->assertStringNotContainsString('data-field="notitie"', $html, 'Memo column must NOT appear as a table column');
    }

    // ------------------------------------------------------------------
    // 5. Cell escaping + null/empty values
    // ------------------------------------------------------------------

    public function testCellValueIsHtmlEscaped(): void
    {
        $this->injectForm('list_escape', [
            ['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam'],
        ]);

        $this->conn->enqueueResult([['cnt' => 1]]);
        $this->conn->enqueueResult([['ID' => 1, 'naam' => 'a<b> & "c"']]);

        $result = JsonFormService::getTableHtml('list_escape', null, []);
        $html = $result['html'];

        // The dangerous characters from the value must be entity-encoded.
        $this->assertStringContainsString('a&lt;b&gt;', $html, '< and > in a cell value must be escaped');
        $this->assertStringContainsString('&amp;', $html, '& in a cell value must be escaped');
        // The raw, unescaped injected markup must NOT survive into the output.
        $this->assertStringNotContainsString('a<b>', $html, 'Raw unescaped markup from the cell value must not leak through');
    }

    public function testNullAndEmptyValuesRenderAsEmptyCellsWithoutWarning(): void
    {
        $this->injectForm('list_null', [
            ['name' => 'naam',   'type' => 'textbox', 'caption' => 'Naam'],
            ['name' => 'plaats', 'type' => 'textbox', 'caption' => 'Plaats'],
        ]);

        $this->conn->enqueueResult([['cnt' => 2]]);
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => null, 'plaats' => ''],   // NULL + empty string
            ['ID' => 2, 'naam' => 'Bob', 'plaats' => 'Utrecht'],
        ]);

        // Under strict errors: an "Undefined array key" warning from a missing
        // value would be promoted to an exception and fail this test.
        $result = $this->withStrictErrors(fn() => JsonFormService::getTableHtml('list_null', null, []));

        $this->assertTrue($result['success'] ?? false);
        $this->assertEquals(2, $result['count']);
        // 'plaats' is the 2nd column (no row-menu prefix), so an empty value
        // renders as a truly empty <td> — proving NULL/'' become empty cells,
        // not a fatal and not an "Undefined array key" warning (strict handler
        // above would have thrown otherwise). ('naam' is column 1 and carries
        // the row-menu trigger span, so it can't be asserted empty here.)
        $this->assertStringContainsString('<td data-field="plaats" data-type="text"></td>', $result['html'],
            'An empty value must render as an empty cell');
    }

    // ------------------------------------------------------------------
    // 6. User-selected columns (field chooser) — subset + order
    // ------------------------------------------------------------------

    public function testUserSelectedColumnsRenderInGivenSubsetAndOrder(): void
    {
        // Definition order is naam, plaats, land. The user selects a reordered
        // subset [plaats, naam]; land must be dropped and order preserved.
        $this->injectForm('list_cols', [
            ['name' => 'naam',   'type' => 'textbox', 'caption' => 'Naam'],
            ['name' => 'plaats', 'type' => 'textbox', 'caption' => 'Plaats'],
            ['name' => 'land',   'type' => 'textbox', 'caption' => 'Land'],
        ]);

        $this->conn->enqueueResult([['cnt' => 1]]);
        $this->conn->enqueueResult([['ID' => 1, 'naam' => 'Alice', 'plaats' => 'Amsterdam', 'land' => 'NL']]);

        $result = JsonFormService::getTableHtml('list_cols', null, ['columns' => ['plaats', 'naam']]);
        $html = $result['html'];

        $this->assertStringContainsString('<th data-field="plaats"', $html);
        $this->assertStringContainsString('<th data-field="naam"', $html);
        $this->assertStringNotContainsString('data-field="land"', $html, 'Unselected column must not render');

        // Order preserved: plaats header must appear before naam header.
        $posPlaats = strpos($html, '<th data-field="plaats"');
        $posNaam   = strpos($html, '<th data-field="naam"');
        $this->assertTrue($posPlaats !== false && $posNaam !== false && $posPlaats < $posNaam,
            'Selected columns must render in the user-specified order (plaats before naam)');
    }

    // ------------------------------------------------------------------
    // 7. Required-filter path (too-many-records guard)
    // ------------------------------------------------------------------

    public function testRequiredFilterShortCircuitsBeforeQuerying(): void
    {
        // A form with filter.field set must NOT load all records when no
        // filter value is supplied: it returns a requiresFilter response and
        // touches the DB zero times.
        $this->injectForm('list_filter', [
            ['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam'],
        ], [
            'filter' => ['field' => 'fkOpleiding', 'description' => 'Selecteer eerst een opleiding'],
        ]);

        $result = JsonFormService::getTableHtml('list_filter', null, []);

        $this->assertTrue($result['success'] ?? false, 'Filter-required is a valid (non-error) state');
        $this->assertTrue($result['requiresFilter'] ?? false, 'requiresFilter must be true');
        $this->assertEquals('fkOpleiding', $result['filterFieldName'] ?? null);
        $this->assertEquals(0, $result['count']);
        $this->assertStringContainsString('filter-required-table', $result['html']);
        $this->assertStringContainsString('Selecteer eerst een opleiding', $result['html']);
        $this->assertEquals(0, count($this->conn->getCalls()), 'No DB query when a required filter is missing');
    }
}
