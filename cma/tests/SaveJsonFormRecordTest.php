<?php
/**
 * Tests for Cma\FormDataProvider::saveJsonFormRecord — the v1.20.1
 * server-side changelog path in particular, proven end-to-end.
 *
 * Run with: php tests/TestRunner.php SaveJsonFormRecordTest
 *
 * Integration-style: uses TestHarness to short-circuit auth, DB
 * connection lookup and form-def loading so production code runs
 * without a real CMA or database. The connection is a StubConnection
 * which records every SQL + params the production code executes;
 * tests then assert the right shape of calls came through.
 *
 * The single most important assertion: the pre-update SELECT * that
 * v1.20.1 added MUST fire before the UPDATE — without it the server-
 * side changelog fallback has no oldFields and the Notificatie column
 * silently degrades back to the pre-1.20.1 empty state.
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

use App\Library\Application;
use Cma\FormDataProvider;

class SaveJsonFormRecordTest extends TestCase
{
    private StubConnection $conn;

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();

        // Minimal valid form definition: one editable text field on a
        // table called tblTest with ID as PK. The shape matches what
        // JsonFormLoader produces — see `cma/assets/forms/definitions/`
        // for real examples.
        $formDef = [
            '_json' => [
                'name'     => 'test_form',
                'title'    => 'Test Form',
                'table'    => 'tblTest',
                'idField'  => 'ID',
                'database' => 'data',
                'fields'   => [
                    ['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text', 'controlType' => 'text'],
                ],
            ],
        ];
        TestHarness::injectFormDef('test_form', $formDef);

        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);

        // Monitoring off by default — keeps the test focused on the
        // SELECT*+UPDATE shape. Per-test we can turn it on to assert
        // the changelog flowing through.
        Application::set('cma_monitoring', '');
    }

    public function tearDown(): void
    {
        TestHarness::reset();
        Application::set('cma_monitoring', '');
    }

    // ------------------------------------------------------------------
    // The v1.20.1 contract: SELECT * before UPDATE
    // ------------------------------------------------------------------

    public function testEditFetchesOldRecordBeforeUpdate(): void
    {
        // Queue: SELECT * row, UPDATE rowCount, verification SELECT cnt=1.
        $this->conn->enqueueResult([['ID' => '1', 'naam' => 'Alice']]); // pre-update SELECT *
        $this->conn->enqueueResult([]);                                  // UPDATE
        $this->conn->enqueueResult([['cnt' => 1]]);                       // verification SELECT COUNT

        $result = FormDataProvider::saveJsonFormRecord('test_form', '1', ['naam' => 'Bob']);

        $this->assertTrue($result['success'] ?? false, 'Save must succeed: ' . ($result['error'] ?? '(no error msg)'));

        $calls = $this->conn->getCalls();
        // Find the SELECT * that v1.20.1 added.
        $foundPreUpdateSelect = false;
        foreach ($calls as $call) {
            if (str_contains($call['sql'], 'SELECT *') && str_contains($call['sql'], 'tblTest')) {
                $foundPreUpdateSelect = true;
                break;
            }
        }
        $this->assertTrue($foundPreUpdateSelect, 'v1.20.1 contract: pre-update SELECT * must fire so oldFields can populate the changelog fallback');
    }

    public function testEditExecutesUpdateWithNewValue(): void
    {
        $this->conn->enqueueResult([['ID' => '1', 'naam' => 'Alice']]); // pre-update SELECT *
        $this->conn->enqueueResult([]);                                  // UPDATE
        $this->conn->enqueueResult([['cnt' => 1]]);                       // verification SELECT COUNT

        FormDataProvider::saveJsonFormRecord('test_form', '1', ['naam' => 'Bob']);

        $updates = array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'UPDATE'));
        $this->assertNotEquals(0, count($updates), 'An UPDATE statement must be issued');

        $updateSql = reset($updates)['sql'];
        $this->assertStringContainsString('tblTest', $updateSql);
        $this->assertStringContainsString('[naam]', $updateSql, 'Updated column must be bracketed');
        $this->assertStringContainsString('Bob', $updateSql, 'New value must appear in the UPDATE');
    }

    public function testPreUpdateSelectComesBeforeUpdate(): void
    {
        // Ordering matters — if the UPDATE went first, oldFields would
        // already reflect the new state and the changelog diff would
        // show no changes. Catch any future refactor that swaps order.
        $this->conn->enqueueResult([['ID' => '1', 'naam' => 'Alice']]);
        $this->conn->enqueueResult([]);
        $this->conn->enqueueResult([['cnt' => 1]]);

        FormDataProvider::saveJsonFormRecord('test_form', '1', ['naam' => 'Bob']);

        $selectIdx = null;
        $updateIdx = null;
        foreach ($this->conn->getCalls() as $idx => $call) {
            if ($selectIdx === null && str_contains($call['sql'], 'SELECT *') && str_contains($call['sql'], 'tblTest')) {
                $selectIdx = $idx;
            }
            if ($updateIdx === null && str_starts_with($call['sql'], 'UPDATE')) {
                $updateIdx = $idx;
            }
        }
        $this->assertNotNull($selectIdx, 'Pre-update SELECT * must be in the call log');
        $this->assertNotNull($updateIdx, 'UPDATE must be in the call log');
        $this->assertTrue($selectIdx < $updateIdx, 'SELECT * must precede UPDATE');
    }

    // ------------------------------------------------------------------
    // Insert path — no pre-update SELECT, no changelog fallback
    // ------------------------------------------------------------------

    public function testInsertSkipsPreUpdateSelect(): void
    {
        // For new records there's no old state to fetch. Pre-1.20.1
        // behaviour was the same — verify v1.20.1 didn't accidentally
        // start running a SELECT for new-record inserts.
        $this->conn->enqueueResult([]);                            // INSERT
        $this->conn->enqueueInsertId('42');                        // PDO lastInsertId
        $this->conn->enqueueResult([['NewID' => '42']]);           // @@IDENTITY/last_insert_rowid fallback
        $this->conn->enqueueResult([['cnt' => 1]]);                // verification SELECT COUNT

        $result = FormDataProvider::saveJsonFormRecord('test_form', null, ['naam' => 'Alice']);

        $this->assertTrue($result['success'] ?? false, 'Insert must succeed: ' . ($result['error'] ?? ''));
        $this->assertTrue($result['isNew'] ?? false, 'isNew must be true');

        // No SELECT * for the test table should appear (only the
        // verification COUNT SELECT and @@IDENTITY result).
        foreach ($this->conn->getCalls() as $call) {
            $this->assertFalse(
                str_contains($call['sql'], 'SELECT *') && str_contains($call['sql'], 'tblTest'),
                'Insert path must NOT run a pre-update SELECT * (no old record to fetch)'
            );
        }
    }

    // ------------------------------------------------------------------
    // Decimal binding — locale-safe save (regression guard)
    // ------------------------------------------------------------------

    public function testDecimalValueIsBareLiteralNotQuotedOrBound(): void
    {
        // A decimal must be written as an UNQUOTED period-decimal SQL literal
        // (… = 2.41). Quoting it ('2.41') OR binding it (PDO sends the float to
        // Access ODBC as the string "2.41") both let the Jet/ACE locale (LCID
        // 1043) re-parse '.' as a thousands separator -> 241. A bare literal is
        // parsed by the query engine, locale-independent. Must hold even when the
        // field carries NO numeric dataType/numericPrecision.
        $formDef = ['_json' => [
            'name' => 'dec_form', 'title' => 'Dec', 'table' => 'tblDec',
            'idField' => 'ID', 'database' => 'data',
            'fields' => [
                ['name' => 'gewicht', 'label' => 'Gewicht', 'type' => 'textbox'],
            ],
        ]];
        TestHarness::injectFormDef('dec_form', $formDef);

        $this->conn->enqueueResult([]);                 // INSERT
        $this->conn->enqueueInsertId('7');              // PDO lastInsertId
        $this->conn->enqueueResult([['NewID' => '7']]); // @@IDENTITY fallback
        $this->conn->enqueueResult([['cnt' => 1]]);     // verification COUNT

        // The stub's post-insert verification SELECT is unrelated to binding
        // (and currently can't be satisfied by the stub), so assert directly on
        // the captured INSERT call rather than on $result['success'].
        FormDataProvider::saveJsonFormRecord('dec_form', null, ['gewicht' => '2.41']);

        $inserts = array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'INSERT'));
        $this->assertNotEquals(0, count($inserts), 'An INSERT must be issued');
        $insert = reset($inserts);

        $this->assertStringContainsString('2.41', $insert['sql'], 'Decimal must appear as a bare numeric literal in the SQL');
        $this->assertStringNotContainsString("'2.41'", $insert['sql'], "Decimal must NOT be a quoted string (Access locale would mangle '2.41' -> 241)");
    }

    public function testCommaDecimalNormalisesToDotLiteral(): void
    {
        // The Dutch comma form ("2,41") must normalise + bind to 2.41 too.
        $formDef = ['_json' => [
            'name' => 'dec_form2', 'title' => 'Dec', 'table' => 'tblDec',
            'idField' => 'ID', 'database' => 'data',
            'fields' => [['name' => 'gewicht', 'label' => 'Gewicht', 'type' => 'textbox']],
        ]];
        TestHarness::injectFormDef('dec_form2', $formDef);

        $this->conn->enqueueResult([]);
        $this->conn->enqueueInsertId('8');
        $this->conn->enqueueResult([['NewID' => '8']]);
        $this->conn->enqueueResult([['cnt' => 1]]);

        FormDataProvider::saveJsonFormRecord('dec_form2', null, ['gewicht' => '2,41']);

        $inserts = array_filter($this->conn->getCalls(), fn($c) => str_starts_with($c['sql'], 'INSERT'));
        $this->assertNotEquals(0, count($inserts), 'An INSERT must be issued');
        $insert = reset($inserts);
        $this->assertStringContainsString('2.41', $insert['sql'], 'Comma decimal must normalise to a bare 2.41 literal');
        $this->assertStringNotContainsString('2,41', $insert['sql'], 'Comma form must be normalised to a dot decimal');
        $this->assertStringNotContainsString("'2.41'", $insert['sql'], 'Must not be a quoted string');
    }

    // ------------------------------------------------------------------
    // Auth guard
    // ------------------------------------------------------------------

    public function testNonAdminIsRejected(): void
    {
        // Demote to USER level — saveJsonFormRecord opens with isAdmin
        // check and must reject without touching the DB.
        TestHarness::reset();
        TestHarness::loginAs(0); // LEVEL_USER
        TestHarness::silenceLogger();
        TestHarness::injectConnection('data', $this->conn);

        $result = FormDataProvider::saveJsonFormRecord('test_form', '1', ['naam' => 'Bob']);

        $this->assertFalse($result['success'] ?? true, 'Non-admin must be rejected');
        $this->assertStringContainsString('toegang', strtolower($result['error'] ?? ''));
        $this->assertEquals(0, count($this->conn->getCalls()), 'No DB calls when auth fails');
    }

    public function testMissingFormDefIsRejected(): void
    {
        TestHarness::reset();
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();
        TestHarness::injectConnection('data', $this->conn);
        // Deliberately don't inject the form def.

        $result = FormDataProvider::saveJsonFormRecord('nonexistent_form', '1', ['naam' => 'Bob']);

        $this->assertFalse($result['success'] ?? true);
        $this->assertEquals(0, count($this->conn->getCalls()), 'No DB calls when form def missing');
    }
}
