<?php
/**
 * FormSavePipelineTest — primary-function coverage for the record SAVE
 * pipeline: Cma\FormDataProvider::saveJsonFormRecord and the INSERT/UPDATE
 * SQL it builds.
 *
 * Run with: php tests/TestRunner.php FormSavePipelineTest
 *
 * These are CORE-FUNCTION regression tests, not security tests. They pin the
 * behaviours whose failure silently corrupts or drops data:
 *   - decimals must be emitted as bare period-decimal SQL literals (the ×100
 *     Access/Jet locale data-corruption bug),
 *   - empty/null values must become SQL NULL (never '0' / ''),
 *   - virtual __label + undeclared form fields must be filtered out of columns,
 *   - date fields must be formatted, not quoted as a raw string,
 *   - new vs existing records pick INSERT vs UPDATE with the right PK WHERE,
 *   - verification failures / DB exceptions surface as structured success=false.
 *
 * Like SaveJsonFormRecordTest, it uses TestHarness + StubConnection to run the
 * real production code without a CMA or database, then asserts on the exact SQL
 * (and bound params) the code executed. Where a behaviour is already covered by
 * SaveJsonFormRecordTest (SELECT*-before-UPDATE ordering, UPDATE-path cnt=0,
 * read-back PDOException) this file extends rather than duplicates it.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/RecordService.php';

use App\Library\Application;
use Cma\FormDataProvider;

class FormSavePipelineTest extends TestCase
{
    private StubConnection $conn;

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();

        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);

        // Monitoring off — keeps the recorded call log focused on the
        // INSERT/UPDATE + verification shape.
        Application::set('cma_monitoring', '');
    }

    public function tearDown(): void
    {
        TestHarness::reset();
        Application::set('cma_monitoring', '');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Inject a form def (production `type`-keyed field shape). */
    private function injectForm(string $name, string $table, array $fields): void
    {
        TestHarness::injectFormDef($name, ['_json' => [
            'name'     => $name,
            'title'    => ucfirst($name),
            'table'    => $table,
            'idField'  => 'ID',
            'database' => 'data',
            'fields'   => $fields,
        ]]);
    }

    /** Queue the DB reads a happy-path INSERT performs (INSERT, lastInsertId, verify COUNT). */
    private function queueInsertHappyPath(string $newId = '42'): void
    {
        $this->conn->enqueueResult([]);                 // INSERT (recordStatement)
        $this->conn->enqueueInsertId($newId);           // PDO lastInsertId (non-empty -> skips @@IDENTITY)
        $this->conn->enqueueResult([['cnt' => 1]]);     // verification SELECT COUNT
    }

    /** Queue the DB reads a happy-path UPDATE performs (pre-read SELECT*, UPDATE, verify COUNT). */
    private function queueUpdateHappyPath(array $oldRow = ['ID' => '1', 'naam' => 'Alice']): void
    {
        $this->conn->enqueueResult([$oldRow]);          // pre-update SELECT *
        $this->conn->enqueueResult([]);                 // UPDATE
        $this->conn->enqueueResult([['cnt' => 1]]);     // verification SELECT COUNT
    }

    /** The recorded INSERT call (['type','sql','params']). */
    private function firstInsert(): array
    {
        $ins = array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'INSERT'));
        $this->assertNotEquals(0, count($ins), 'An INSERT statement must be issued');
        return reset($ins);
    }

    /** The recorded UPDATE call (['type','sql','params']). */
    private function firstUpdate(): array
    {
        $upd = array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'UPDATE'));
        $this->assertNotEquals(0, count($upd), 'An UPDATE statement must be issued');
        return reset($upd);
    }

    // ------------------------------------------------------------------
    // 1. Decimal / Dutch-locale — the ×100 data-corruption bug.
    //    Decimals MUST be inlined as a bare, unquoted, period-decimal
    //    literal — never bound (params stay empty), never quoted, never
    //    comma-form, never ×100.
    // ------------------------------------------------------------------

    public function testDecimalTenPointFiveIsBareLiteralOnUpdate(): void
    {
        $this->injectForm('dec_upd', 'tblDec', [
            ['name' => 'gewicht', 'type' => 'textbox', 'numericPrecision' => 4],
        ]);
        $this->queueUpdateHappyPath(['ID' => '1', 'gewicht' => '9.0']);

        FormDataProvider::saveJsonFormRecord('dec_upd', '1', ['gewicht' => '10.5']);

        $upd = $this->firstUpdate();
        $this->assertStringContainsString('[gewicht] = 10.5', $upd['sql'], 'Decimal must be inlined as a bare literal in the SET clause');
        $this->assertStringNotContainsString("'10.5'", $upd['sql'], "Decimal must NOT be quoted (Access locale would mangle '10.5' -> 105)");
        $this->assertStringNotContainsString('1050', $upd['sql'], 'Decimal must NOT be scaled x100');
        $this->assertStringNotContainsString('10,5', $upd['sql'], 'Decimal must NOT keep the comma form');
        $this->assertEquals([], $upd['params'], 'Decimal must be INLINED, not bound as a PDO param (binding lets the driver locale re-parse it)');
    }

    public function testAssortedDecimalsAndIntegerInlinedOnInsert(): void
    {
        // Three numeric fields exercised in one INSERT: a large decimal, a
        // negative decimal, and a plain integer declared numeric.
        $this->injectForm('dec_ins', 'tblDec', [
            ['name' => 'prijs',  'type' => 'textbox', 'numericPrecision' => 8],
            ['name' => 'delta',  'type' => 'textbox', 'numericPrecision' => 4],
            ['name' => 'aantal', 'type' => 'textbox', 'numericPrecision' => 4],
        ]);
        $this->queueInsertHappyPath('7');

        FormDataProvider::saveJsonFormRecord('dec_ins', null, [
            'prijs'  => '1234.56',
            'delta'  => '-3.14',
            'aantal' => '42',
        ]);

        $ins = $this->firstInsert();
        // Bare literals present…
        $this->assertStringContainsString('1234.56', $ins['sql'], 'Large decimal inlined bare');
        $this->assertStringContainsString('-3.14', $ins['sql'], 'Negative decimal inlined bare');
        $this->assertStringContainsString('42', $ins['sql'], 'Integer inlined bare');
        // …and none of the corrupting forms.
        $this->assertStringNotContainsString("'1234.56'", $ins['sql'], 'Large decimal must not be quoted');
        $this->assertStringNotContainsString("'-3.14'", $ins['sql'], 'Negative decimal must not be quoted');
        $this->assertStringNotContainsString('123456', $ins['sql'], 'Large decimal must not be scaled x100');
        $this->assertStringNotContainsString('314', $ins['sql'], 'Negative decimal must not be scaled x100');
        $this->assertEquals([], $ins['params'], 'No numeric field may be bound — all inlined');
    }

    public function testCommaDecimalNormalisesToDotLiteral(): void
    {
        // A field with a '.'/',' still inlines even WITHOUT a numericPrecision
        // hint (the strpos('.') branch), and the Dutch comma form normalises.
        $this->injectForm('dec_comma', 'tblDec', [
            ['name' => 'gewicht', 'type' => 'textbox'],
        ]);
        $this->queueInsertHappyPath('8');

        FormDataProvider::saveJsonFormRecord('dec_comma', null, ['gewicht' => '2,41']);

        $ins = $this->firstInsert();
        $this->assertStringContainsString('2.41', $ins['sql'], 'Comma decimal must normalise to a bare 2.41 literal');
        $this->assertStringNotContainsString('2,41', $ins['sql'], 'Comma form must be normalised away');
        $this->assertStringNotContainsString("'2.41'", $ins['sql'], 'Must not be quoted');
    }

    // ------------------------------------------------------------------
    // 2. NULL vs empty — empty string and explicit null both become SQL
    //    NULL, never '0' or ''.
    // ------------------------------------------------------------------

    public function testEmptyAndNullBecomeSqlNull(): void
    {
        $this->injectForm('null_form', 'tblTest', [
            ['name' => 'naam',      'type' => 'textbox'],
            ['name' => 'opmerking', 'type' => 'textbox'],
        ]);
        $this->queueUpdateHappyPath(['ID' => '1', 'naam' => 'x', 'opmerking' => 'y']);

        FormDataProvider::saveJsonFormRecord('null_form', '1', [
            'naam'      => '',    // empty string
            'opmerking' => null,  // explicit null
        ]);

        $upd = $this->firstUpdate();
        $this->assertStringContainsString('[naam] = NULL', $upd['sql'], 'Empty string must become SQL NULL');
        $this->assertStringContainsString('[opmerking] = NULL', $upd['sql'], 'Explicit null must become SQL NULL');
        $this->assertStringNotContainsString("[naam] = ''", $upd['sql'], "Empty must not become an empty quoted string");
        $this->assertStringNotContainsString("[naam] = '0'", $upd['sql'], "Empty must not become '0'");
        $this->assertStringNotContainsString('[naam] = 0', $upd['sql'], 'Empty must not become bare 0');
    }

    // ------------------------------------------------------------------
    // 3. Field filtering — __label virtual fields and fields not declared
    //    in the definition must never become columns.
    // ------------------------------------------------------------------

    public function testUndeclaredAndLabelFieldsAreSkipped(): void
    {
        $this->injectForm('filt_form', 'tblTest', [
            ['name' => 'naam', 'type' => 'textbox'],
        ]);
        $this->queueInsertHappyPath('5');

        FormDataProvider::saveJsonFormRecord('filt_form', null, [
            'naam'             => 'Alice',
            'naam__label'      => 'display only',  // virtual label
            'actie'            => 'save',          // control field, not a column
            'required'         => '1',             // control field
            'user_groups[]'    => ['a', 'b'],      // array control field
        ]);

        $ins = $this->firstInsert();
        $this->assertStringContainsString('[naam]', $ins['sql'], 'Declared field must be a column');
        $this->assertStringContainsString('Alice', $ins['sql'], 'Declared value must be present');
        $this->assertStringNotContainsString('__label', $ins['sql'], 'Virtual __label fields must be skipped');
        $this->assertStringNotContainsString('[actie]', $ins['sql'], "Undeclared 'actie' must be skipped");
        $this->assertStringNotContainsString('[required]', $ins['sql'], "Undeclared 'required' must be skipped");
        $this->assertStringNotContainsString('user_groups', $ins['sql'], "Undeclared 'user_groups[]' must be skipped");
    }

    // ------------------------------------------------------------------
    // 4. Date fields — formatted for SQL, not quoted as a raw dd-mm-yyyy
    //    string. On SQLite the driver-aware helper emits a quoted ISO date.
    // ------------------------------------------------------------------

    public function testDateFieldIsFormattedToIso(): void
    {
        $this->injectForm('date_form', 'tblTest', [
            ['name' => 'DATESTAMP', 'type' => 'date'],
        ]);
        $this->queueUpdateHappyPath(['ID' => '1', 'DATESTAMP' => '2000-01-01']);

        // dd-mm-yyyy is what the JSON date control posts.
        FormDataProvider::saveJsonFormRecord('date_form', '1', ['DATESTAMP' => '15-06-2026']);

        $upd = $this->firstUpdate();
        $this->assertStringContainsString("[DATESTAMP] = '2026-06-15'", $upd['sql'], 'Date must be reformatted to ISO for SQL (SQLite path)');
        $this->assertStringNotContainsString('15-06-2026', $upd['sql'], 'Raw dd-mm-yyyy string must not be emitted');
    }

    // ------------------------------------------------------------------
    // 5. INSERT vs UPDATE selection + PK WHERE.
    // ------------------------------------------------------------------

    public function testNewRecordBuildsInsert(): void
    {
        $this->injectForm('mode_form', 'tblTest', [
            ['name' => 'naam', 'type' => 'textbox'],
        ]);
        $this->queueInsertHappyPath('99');

        $result = FormDataProvider::saveJsonFormRecord('mode_form', null, ['naam' => 'Bob']);

        $ins = $this->firstInsert();
        $this->assertStringContainsString('INSERT INTO [tblTest]', $ins['sql'], 'New record (no id) must build an INSERT on the form table');
        $this->assertTrue(str_starts_with($ins['sql'], 'INSERT'), 'Statement must be an INSERT');
        $this->assertTrue($result['isNew'] ?? false, 'isNew must be true for a new record');
        // And no UPDATE / no pre-read SELECT * fires for an insert.
        foreach ($this->conn->getCalls() as $c) {
            $this->assertFalse(str_starts_with($c['sql'], 'UPDATE'), 'Insert path must not issue an UPDATE');
        }
    }

    public function testExistingRecordBuildsUpdateWithPkWhere(): void
    {
        $this->injectForm('mode_form2', 'tblTest', [
            ['name' => 'naam', 'type' => 'textbox'],
        ]);
        $this->queueUpdateHappyPath();

        $result = FormDataProvider::saveJsonFormRecord('mode_form2', '1', ['naam' => 'Bob']);

        $upd = $this->firstUpdate();
        $this->assertStringContainsString('UPDATE [tblTest] SET', $upd['sql'], 'Existing id must build an UPDATE on the form table');
        $this->assertTrue(str_starts_with($upd['sql'], 'UPDATE'), 'Statement must be an UPDATE');
        $this->assertStringContainsString('WHERE [ID] = 1', $upd['sql'], 'UPDATE must scope to the PK with the record id');
        $this->assertFalse($result['isNew'] ?? true, 'isNew must be false for an existing record');
    }

    // ------------------------------------------------------------------
    // 6. Changelog pre-read — extends SaveJsonFormRecordTest: the pre-update
    //    SELECT * must not only fire before the UPDATE but scope to the SAME
    //    record via the PK, so the old-vs-new changelog diff is meaningful.
    // ------------------------------------------------------------------

    public function testPreUpdateSelectTargetsRecordByPkBeforeUpdate(): void
    {
        $this->injectForm('cl_form', 'tblTest', [
            ['name' => 'naam', 'type' => 'textbox'],
        ]);
        $this->queueUpdateHappyPath(['ID' => '1', 'naam' => 'Alice']);

        FormDataProvider::saveJsonFormRecord('cl_form', '1', ['naam' => 'Bob']);

        $selectIdx = null;
        $updateIdx = null;
        foreach ($this->conn->getCalls() as $idx => $call) {
            if ($selectIdx === null && str_contains($call['sql'], 'SELECT *') && str_contains($call['sql'], 'tblTest')) {
                $selectIdx = $idx;
                $this->assertStringContainsString('WHERE [ID] = 1', $call['sql'], 'Pre-update SELECT * must scope to the edited record by PK');
            }
            if ($updateIdx === null && str_starts_with($call['sql'], 'UPDATE')) {
                $updateIdx = $idx;
            }
        }
        $this->assertNotNull($selectIdx, 'Pre-update SELECT * must fire');
        $this->assertNotNull($updateIdx, 'UPDATE must fire');
        $this->assertTrue($selectIdx < $updateIdx, 'SELECT * must precede the UPDATE');
    }

    // ------------------------------------------------------------------
    // 7. Error paths — extends SaveJsonFormRecordTest (which covers the
    //    UPDATE-path cnt=0 and a read-back PDOException). Here: the INSERT
    //    path must ALSO fail structurally when post-save verification COUNT
    //    comes back 0 (row didn't persist), never a false "success".
    // ------------------------------------------------------------------

    public function testInsertVerificationCountZeroSurfacesFailure(): void
    {
        $this->injectForm('verf_form', 'tblTest', [
            ['name' => 'naam', 'type' => 'textbox'],
        ]);
        $this->conn->enqueueResult([]);                 // INSERT
        $this->conn->enqueueInsertId('9');              // PDO lastInsertId
        $this->conn->enqueueResult([['cnt' => 0]]);     // verification COUNT = 0 -> not persisted

        $result = FormDataProvider::saveJsonFormRecord('verf_form', null, ['naam' => 'Alice']);

        $this->assertFalse($result['success'] ?? true, 'INSERT cnt=0 verification must fail the save');
        $this->assertStringContainsString('verificatie', strtolower($result['error'] ?? ''), 'Failure must be the verification error, not a fatal');
    }
}
