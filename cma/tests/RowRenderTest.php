<?php
/**
 * Tests for Cma\Services\JsonFormService::getRowHtml — renders one
 * record's <tr> after an inline save/edit so the list can swap the
 * single row in place.
 *
 * Run with: php tests/TestRunner.php RowRenderTest
 *
 * Integration-style: TestHarness short-circuits auth, the DB connection
 * lookup and form-def loading. getRowHtml runs a single
 *   SELECT * FROM [table] WHERE [idField] = <id>
 * so each test enqueues exactly one result row on the StubConnection;
 * the stub returns it regardless of the SQL text. We pass the columns
 * explicitly (as the inline-edit refresh does) so the render path is
 * deterministic and doesn't depend on stored column preferences.
 *
 * The core guarantee: a record's field values actually populate the row
 * (testRowPopulatesFieldValues) and NULL/empty values render safely with
 * no warning or fatal (testNullAndEmptyValuesRenderSafely).
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php'; // defines Cma\ListMode
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/JsonFormService.php';

use Cma\Services\JsonFormService;

class RowRenderTest extends TestCase
{
    private StubConnection $conn;

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();

        // A products table with a spread of field types so each column
        // renderer (text / number / boolean / memo) is exercised.
        $def = ['_json' => [
            'name'     => 'row_form',
            'title'    => 'Producten',
            'table'    => 'tblProducts',
            'idField'  => 'ID',
            'database' => 'data',
            'fields'   => [
                ['name' => 'naam',         'type' => 'textbox',  'caption' => 'Naam'],
                ['name' => 'prijs',        'type' => 'number',   'caption' => 'Prijs', 'dataType' => '5'],
                ['name' => 'actief',       'type' => 'checkbox', 'caption' => 'Actief'],
                ['name' => 'omschrijving', 'type' => 'memo',     'caption' => 'Omschrijving'],
            ],
        ]];
        TestHarness::injectFormDef('row_form', $def);

        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
    }

    public function tearDown(): void
    {
        TestHarness::reset();
    }

    // ------------------------------------------------------------------
    // 1. Field values populate the rendered row
    // ------------------------------------------------------------------

    public function testRowPopulatesFieldValues(): void
    {
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Appel', 'prijs' => '1.50'],
        ]);

        $result = JsonFormService::getRowHtml('row_form', '1', 2, ['naam', 'prijs']);

        $this->assertTrue($result['success'] ?? false, 'row render must succeed: ' . ($result['error'] ?? ''));
        $html = $result['html'] ?? '';
        $this->assertStringContainsString('<tr class="listrow" data-id="1"', $html, 'row is keyed by record id');
        $this->assertStringContainsString('data-field="naam"', $html, 'naam column cell is present');
        $this->assertStringContainsString('Appel', $html, 'the naam value populates the cell');
        $this->assertStringContainsString('data-field="prijs"', $html, 'prijs column cell is present');
        // number formatter strips trailing-zero decimals: 1.50 -> 1.5
        $this->assertStringContainsString('>1.5<', $html, 'number value is formatted and populated');

        // displayText drives the tree-node label after a save; first column here.
        $this->assertEquals('Appel', $result['displayText'] ?? '', 'displayText is the first column value');
    }

    public function testRecordNotFoundIsReported(): void
    {
        // openRS returns an empty recordset (EOF) → "Record niet gevonden".
        $this->conn->enqueueResult([]);

        $result = JsonFormService::getRowHtml('row_form', '999', 2, ['naam']);

        $this->assertFalse($result['success'] ?? true, 'a missing record must fail cleanly, not fatal');
        $this->assertStringContainsString('niet gevonden', strtolower($result['error'] ?? ''));
    }

    // ------------------------------------------------------------------
    // 2. NULL / empty values render safely
    // ------------------------------------------------------------------

    public function testNullAndEmptyValuesRenderSafely(): void
    {
        // naam NULL, prijs empty-string — must not emit warnings or fatal.
        $this->conn->enqueueResult([
            ['ID' => 2, 'naam' => null, 'prijs' => ''],
        ]);

        $result = JsonFormService::getRowHtml('row_form', '2', 2, ['naam', 'prijs']);

        $this->assertTrue($result['success'] ?? false, 'null/empty values must render without error: ' . ($result['error'] ?? ''));
        $html = $result['html'] ?? '';
        // Cells still emitted, just empty — the table structure stays intact.
        $this->assertStringContainsString('data-field="naam"', $html, 'null value still emits its cell');
        $this->assertStringContainsString('data-field="prijs"', $html, 'empty value still emits its cell');
        $this->assertStringContainsString('<td data-field="naam" data-type="text">', $html, 'null text cell renders as an empty text td');
        // displayText degrades to '' rather than "null".
        $this->assertEquals('', $result['displayText'] ?? 'x', 'null first-column value yields an empty displayText');
    }

    // ------------------------------------------------------------------
    // 3. displayMode variations — DB-backed rows are mode-independent
    // ------------------------------------------------------------------

    public function testDisplayModeDoesNotChangeDbRowHtml(): void
    {
        // For table-backed forms getRowHtml always emits a <tr>; the
        // displayMode arg (1=tree, 2=table) only branches for JSON-config
        // forms. Assert the two modes produce identical row HTML so a
        // future change that couples them to displayMode is caught.
        $this->conn->enqueueResult([['ID' => 3, 'naam' => 'Peer']]);
        $tree = JsonFormService::getRowHtml('row_form', '3', 1, ['naam']);

        $this->conn->enqueueResult([['ID' => 3, 'naam' => 'Peer']]);
        $table = JsonFormService::getRowHtml('row_form', '3', 2, ['naam']);

        $this->assertTrue($tree['success'] ?? false);
        $this->assertTrue($table['success'] ?? false);
        $this->assertEquals($table['html'] ?? '', $tree['html'] ?? '', 'DB row HTML is identical for tree and table display modes');
    }

    // ------------------------------------------------------------------
    // 4. Type-specific rendering: boolean and memo
    // ------------------------------------------------------------------

    public function testBooleanFieldRendersAsSwitchForAdmin(): void
    {
        $this->conn->enqueueResult([
            ['ID' => 4, 'actief' => 1],
        ]);

        $result = JsonFormService::getRowHtml('row_form', '4', 2, ['actief']);

        $this->assertTrue($result['success'] ?? false, 'boolean render must succeed');
        $html = $result['html'] ?? '';
        $this->assertStringContainsString('data-type="boolean"', $html, 'boolean column is typed');
        // admin gets an interactive switch, pre-checked for a truthy value.
        $this->assertStringContainsString('<lib-switch data-field="actief" checked>', $html, 'truthy boolean renders a checked lib-switch');
    }

    public function testMemoFieldRendersItsValue(): void
    {
        $this->conn->enqueueResult([
            ['ID' => 5, 'omschrijving' => 'Een lange omschrijving'],
        ]);

        $result = JsonFormService::getRowHtml('row_form', '5', 2, ['omschrijving']);

        $this->assertTrue($result['success'] ?? false, 'memo render must succeed');
        $html = $result['html'] ?? '';
        $this->assertStringContainsString('data-field="omschrijving"', $html, 'memo column cell is present');
        $this->assertStringContainsString('Een lange omschrijving', $html, 'memo value populates the cell');
    }

    public function testHtmlSpecialCharsAreEscaped(): void
    {
        // A value with markup must be HTML-escaped so it can't break the row
        // or inject into the list DOM.
        $this->conn->enqueueResult([
            ['ID' => 6, 'naam' => '<b>x</b> & "q"'],
        ]);

        $result = JsonFormService::getRowHtml('row_form', '6', 2, ['naam']);

        $this->assertTrue($result['success'] ?? false);
        $html = $result['html'] ?? '';
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html, 'angle brackets are escaped');
        $this->assertStringContainsString('&amp;', $html, 'ampersand is escaped');
        $this->assertStringNotContainsString('<b>x</b>', $html, 'raw markup must not survive into the row');
    }

    // ------------------------------------------------------------------
    // Guard: missing form
    // ------------------------------------------------------------------

    public function testMissingFormIsRejected(): void
    {
        $result = JsonFormService::getRowHtml('does_not_exist', '1', 2, ['naam']);
        $this->assertFalse($result['success'] ?? true, 'unknown form must fail cleanly');
        $this->assertNotEmpty($result['error'] ?? '', 'a failure message must be present');
    }
}
