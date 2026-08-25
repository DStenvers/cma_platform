<?php
/**
 * Tests for App\Library\RecordSet — the PDOStatement wrapper that emulates
 * classic ASP ADO cursors (EOF, MoveNext, Fields, RecordCount, …).
 *
 * Run with: php tests/TestRunner.php RecordSetTest
 *
 * Backend: the pdo_sqlite extension is NOT available in this WSL/CI env
 * (checked via extension_loaded('pdo_sqlite') === false), so these tests
 * feed rows through the project's StubConnection / StubStatement double
 * instead of a real database. StubStatement implements the PDOStatement
 * surface RecordSet actually touches: fetch(FETCH_ASSOC), fetchAll(),
 * closeCursor(). Rows round-trip verbatim, so data-type fidelity
 * (int/string/null/unicode) is preserved through the cursor.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\RecordSet;
use App\Library\CaseArray;

class RecordSetTest extends TestCase
{
    /**
     * Build a forward-only RecordSet over the given rows using the stub.
     * Mirrors the production path: $conn->query() → new RecordSet($stmt).
     */
    private function forwardRs(array $rows): RecordSet
    {
        $conn = StubConnection::create();
        $conn->enqueueResult($rows);
        $stmt = $conn->query('SELECT * FROM t');
        return new RecordSet($stmt, false);
    }

    /**
     * Build a scrollable RecordSet (all rows cached; supports MoveFirst/
     * MoveLast/MovePrevious/RecordCount).
     */
    private function scrollableRs(array $rows): RecordSet
    {
        $conn = StubConnection::create();
        $conn->enqueueResult($rows);
        $stmt = $conn->query('SELECT * FROM t');
        return new RecordSet($stmt, true);
    }

    /** Sample multi-row fixture. */
    private function sampleRows(): array
    {
        return [
            ['ID' => 1, 'naam' => 'Alice'],
            ['ID' => 2, 'naam' => 'Bob'],
            ['ID' => 3, 'naam' => 'Charlie'],
        ];
    }

    // ---- Iteration ------------------------------------------------------

    public function testForwardIterationVisitsAllRowsInOrder(): void
    {
        $rs = $this->forwardRs($this->sampleRows());

        $seen = [];
        while (!$rs->EOF) {
            $seen[] = $rs->Fields['naam'];
            $rs->MoveNext();
        }

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $seen);
        $this->assertTrue($rs->EOF, 'EOF must be true after visiting every row');
    }

    public function testScrollableIterationVisitsAllRowsInOrder(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());

        $seen = [];
        while (!$rs->EOF) {
            $seen[] = $rs->Fields['naam'];
            $rs->MoveNext();
        }

        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $seen);
        $this->assertTrue($rs->EOF);
    }

    public function testFirstRowIsLoadedImmediatelyAfterConstruct(): void
    {
        // ADO compatibility: $rs->Fields must work right after openRS()
        // without an explicit MoveNext().
        $rs = $this->forwardRs($this->sampleRows());
        $this->assertFalse($rs->EOF);
        $this->assertEquals(1, $rs->Fields['ID']);
        $this->assertEquals('Alice', $rs->Fields['naam']);
    }

    // ---- EOF semantics --------------------------------------------------

    public function testEofIsFalseAtStartOfNonEmptyForwardSet(): void
    {
        $rs = $this->forwardRs([['ID' => 1]]);
        $this->assertFalse($rs->EOF);
    }

    public function testEofTrueImmediatelyOnEmptyForwardSet(): void
    {
        $rs = $this->forwardRs([]);
        $this->assertTrue($rs->EOF);
        $this->assertEquals([], $rs->Fields, 'Fields on empty set is an empty array');
    }

    public function testEofTrueImmediatelyOnEmptyScrollableSet(): void
    {
        $rs = $this->scrollableRs([]);
        $this->assertTrue($rs->EOF);
        $this->assertEquals(0, $rs->RecordCount);
    }

    public function testEofMethodFormMatchesProperty(): void
    {
        // ADO-style code (and the ASP->PHP converter) sometimes writes $rs->EOF()
        // as a method; the explicit EOF() method must return the same as $rs->EOF.
        $rs = $this->forwardRs([['ID' => 1]]);
        $this->assertFalse($rs->EOF());
        $this->assertSame($rs->EOF, $rs->EOF());
        $rs->MoveNext();
        $this->assertTrue($rs->EOF());
        $this->assertSame($rs->EOF, $rs->EOF());

        // Empty set is at EOF immediately, method form.
        $this->assertTrue($this->forwardRs([])->EOF());
    }

    public function testEofBecomesTrueAfterMovingPastLastRow(): void
    {
        $rs = $this->forwardRs([['ID' => 1]]);
        $this->assertFalse($rs->EOF);
        $rs->MoveNext();
        $this->assertTrue($rs->EOF);
    }

    public function testMoveNextPastEndIsSafeAndStaysEof(): void
    {
        $rs = $this->forwardRs([['ID' => 1]]);
        $rs->MoveNext(); // now EOF
        // Repeated MoveNext past the end must not fatal and must stay EOF.
        $rs->MoveNext();
        $rs->MoveNext();
        $this->assertTrue($rs->EOF);
    }

    public function testScrollableMoveNextPastEndIsSafe(): void
    {
        $rs = $this->scrollableRs([['ID' => 1]]);
        $rs->MoveNext(); // EOF
        $rs->MoveNext(); // past end
        $this->assertTrue($rs->EOF);
    }

    // ---- isEOF() / __call EOF() ----------------------------------------

    public function testIsEofMethodMatchesEofProperty(): void
    {
        $rs = $this->forwardRs([['ID' => 1]]);
        $this->assertFalse($rs->isEOF());
        $rs->MoveNext();
        $this->assertTrue($rs->isEOF());
    }

    public function testEofCallableViaMagicCall(): void
    {
        // __call maps $rs->eof() / $rs->EOF() to the property.
        $rs = $this->forwardRs([['ID' => 1]]);
        $this->assertFalse($rs->eof());
        $this->assertFalse($rs->EOF());
        $rs->MoveNext();
        $this->assertTrue($rs->eof());
    }

    public function testUnknownMethodThrows(): void
    {
        $rs = $this->forwardRs([['ID' => 1]]);
        $threw = false;
        try {
            $rs->NoSuchMethod();
        } catch (\BadMethodCallException $e) {
            $threw = true;
            $this->assertStringContainsString('NoSuchMethod', $e->getMessage());
        }
        $this->assertTrue($threw, 'Unknown method must throw BadMethodCallException');
    }

    // ---- Field access ---------------------------------------------------

    public function testFieldsArrayAccessReturnsCurrentRowValue(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $this->assertEquals('Alice', $rs->Fields['naam']);
        $rs->MoveNext();
        $this->assertEquals('Bob', $rs->Fields['naam']);
    }

    public function testFieldsIsCaseInsensitive(): void
    {
        $rs = $this->forwardRs([['Naam' => 'Alice', 'ID' => 7]]);
        // CaseArray provides case-insensitive key access (Access ODBC
        // returns column names in varying case).
        $this->assertEquals('Alice', $rs->Fields['naam']);
        $this->assertEquals('Alice', $rs->Fields['NAAM']);
        $this->assertEquals('Alice', $rs->Fields['Naam']);
        $this->assertEquals(7, $rs->Fields['id']);
    }

    public function testDirectPropertyFieldAccess(): void
    {
        $rs = $this->forwardRs([['naam' => 'Alice', 'ID' => 7]]);
        // $rs->naam — direct magic property access (case-sensitive).
        $this->assertEquals('Alice', $rs->naam);
        $this->assertEquals(7, $rs->ID);
    }

    public function testDirectPropertyFieldAccessIsCaseSensitive(): void
    {
        $rs = $this->forwardRs([['naam' => 'Alice']]);
        // Direct property access uses the exact key; a case mismatch
        // returns null (unlike Fields[] / offsetGet which are insensitive).
        $this->assertEquals('Alice', $rs->naam);
        $this->assertNull($rs->Naam, 'Direct property access is case-sensitive');
    }

    public function testArrayAccessOnRecordSetIsCaseInsensitive(): void
    {
        $rs = $this->forwardRs([['Naam' => 'Alice']]);
        // $rs['col'] via ArrayAccess — case-insensitive fallback.
        $this->assertEquals('Alice', $rs['Naam']);
        $this->assertEquals('Alice', $rs['naam']);
        $this->assertEquals('Alice', $rs['NAAM']);
    }

    public function testMissingColumnReturnsNullNotFatal(): void
    {
        $rs = $this->forwardRs([['naam' => 'Alice']]);
        $this->assertNull($rs->Fields['does_not_exist']);
        $this->assertNull($rs['does_not_exist']);
        $this->assertNull($rs->does_not_exist);
    }

    public function testOffsetExistsReflectsCurrentRow(): void
    {
        $rs = $this->forwardRs([['naam' => 'Alice']]);
        $this->assertTrue(isset($rs['naam']));
        $this->assertFalse(isset($rs['missing']));
    }

    public function testOffsetSetAndUnsetAreNoOpsReadOnly(): void
    {
        $rs = $this->forwardRs([['naam' => 'Alice']]);
        $rs['naam'] = 'Mallory'; // must be ignored (read-only)
        unset($rs['naam']);       // must be ignored
        $this->assertEquals('Alice', $rs['naam']);
    }

    // ---- Move* on scrollable cursors -----------------------------------

    public function testMoveFirstReturnsToFirstRow(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());
        $rs->MoveNext();
        $rs->MoveNext();
        $this->assertEquals('Charlie', $rs->Fields['naam']);
        $rs->MoveFirst();
        $this->assertEquals('Alice', $rs->Fields['naam']);
        $this->assertFalse($rs->EOF);
    }

    public function testMoveLastGoesToLastRow(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());
        $rs->MoveLast();
        $this->assertEquals('Charlie', $rs->Fields['naam']);
        $this->assertFalse($rs->EOF);
    }

    public function testMovePreviousStepsBack(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());
        $rs->MoveLast(); // Charlie (pos 2)
        $rs->MovePrevious();
        $this->assertEquals('Bob', $rs->Fields['naam']);
        $rs->MovePrevious();
        $this->assertEquals('Alice', $rs->Fields['naam']);
    }

    public function testMovePreviousFromFirstRowSetsEof(): void
    {
        // Documented quirk: MovePrevious at position 0 sets EOF true.
        $rs = $this->scrollableRs($this->sampleRows());
        $this->assertEquals('Alice', $rs->Fields['naam']);
        $rs->MovePrevious();
        $this->assertTrue($rs->EOF);
    }

    public function testMoveFirstThrowsOnForwardOnlyCursor(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $threw = false;
        try {
            $rs->MoveFirst();
        } catch (\Exception $e) {
            $threw = true;
            $this->assertStringContainsString('scrollable', $e->getMessage());
        }
        $this->assertTrue($threw, 'MoveFirst must throw on a forward-only cursor');
    }

    public function testMoveLastThrowsOnForwardOnlyCursor(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $threw = false;
        try {
            $rs->MoveLast();
        } catch (\Exception $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'MoveLast must throw on a forward-only cursor');
    }

    public function testMovePreviousThrowsOnForwardOnlyCursor(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $threw = false;
        try {
            $rs->MovePrevious();
        } catch (\Exception $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'MovePrevious must throw on a forward-only cursor');
    }

    // ---- RecordCount ----------------------------------------------------

    public function testRecordCountOnScrollableCursor(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());
        $this->assertEquals(3, $rs->RecordCount);
    }

    public function testRecordCountIsMinusOneOnForwardOnlyCursor(): void
    {
        // Forward-only cursors cannot know the count up front.
        $rs = $this->forwardRs($this->sampleRows());
        $this->assertEquals(-1, $rs->RecordCount);
    }

    // ---- fetchAll / GetRows --------------------------------------------

    public function testFetchAllReturnsAllRowsForwardOnly(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $rows = $rs->fetchAll();
        $this->assertCount(3, $rows);
        $this->assertEquals('Alice', $rows[0]['naam']);
        $this->assertEquals('Charlie', $rows[2]['naam']);
    }

    public function testFetchAllReturnsAllRowsScrollable(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());
        $rows = $rs->fetchAll();
        $this->assertCount(3, $rows);
        $this->assertEquals('Bob', $rows[1]['naam']);
    }

    public function testFetchAllOnEmptyForwardSet(): void
    {
        $rs = $this->forwardRs([]);
        $this->assertEquals([], $rs->fetchAll());
    }

    public function testGetRowsReturnsAdoShape(): void
    {
        // ADO indexeert (veld, rij); GetRows() geeft daarom een ColumnMajorArray
        // terug, net als Cache::retrieve(). fetchAll() blijft row-major.
        $rs = $this->forwardRs($this->sampleRows());
        $rows = $rs->GetRows();
        $this->assertEquals('Alice', $rows['naam'][0]);
        $this->assertEquals('Bob', $rows['naam'][1]);
        $this->assertCount(3, $rows['naam']);
    }

    public function testFetchAllStaysRowMajor(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $rows = $rs->fetchAll();
        $this->assertCount(3, $rows);
        $this->assertEquals('Alice', $rows[0]['naam']);
    }

    // ---- fetch() / fetchAssoc() ----------------------------------------

    public function testFetchReturnsCurrentRowThenAdvances(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $r0 = $rs->fetch();
        $this->assertEquals('Alice', $r0['naam']);
        $r1 = $rs->fetch();
        $this->assertEquals('Bob', $r1['naam']);
        $r2 = $rs->fetch();
        $this->assertEquals('Charlie', $r2['naam']);
        $this->assertFalse($rs->fetch(), 'fetch() past end returns false');
    }

    public function testFetchAssocReturnsCurrentRow(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $this->assertEquals(['ID' => 1, 'naam' => 'Alice'], $rs->fetchAssoc());
    }

    // ---- Iteration via IteratorAggregate --------------------------------

    public function testForeachOverRecordSetIteratesCurrentRowFields(): void
    {
        $rs = $this->forwardRs([['ID' => 1, 'naam' => 'Alice']]);
        $collected = [];
        foreach ($rs as $key => $value) {
            $collected[$key] = $value;
        }
        $this->assertEquals(['ID' => 1, 'naam' => 'Alice'], $collected);
    }

    public function testForeachOverFieldsSnapshot(): void
    {
        $rs = $this->forwardRs([['ID' => 1, 'naam' => 'Alice']]);
        $collected = [];
        foreach ($rs->Fields as $key => $value) {
            $collected[$key] = $value;
        }
        $this->assertEquals(['ID' => 1, 'naam' => 'Alice'], $collected);
    }

    // ---- Fields snapshot semantics -------------------------------------

    public function testFieldsSnapshotCapturesRowBeforeMoveNext(): void
    {
        // $row = $rs->Fields; is a snapshot copy, so it must retain the
        // value even after the cursor advances.
        $rs = $this->forwardRs($this->sampleRows());
        $snapshot = $rs->Fields;
        $rs->MoveNext();
        $this->assertEquals('Alice', $snapshot['naam'], 'Snapshot must not follow the cursor');
        $this->assertEquals('Bob', $rs->Fields['naam']);
    }

    // ---- Close ----------------------------------------------------------

    public function testCloseIsSafeAndSetsEof(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $rs->Close();
        $this->assertTrue($rs->EOF);
        $this->assertEquals([], $rs->Fields, 'Fields is empty after Close');
    }

    public function testCloseTwiceIsSafe(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $rs->Close();
        $rs->Close(); // second close must not fatal
        $this->assertTrue($rs->EOF);
    }

    public function testFieldAccessAfterCloseReturnsNull(): void
    {
        $rs = $this->forwardRs($this->sampleRows());
        $rs->Close();
        $this->assertNull($rs->naam);
        $this->assertNull($rs['naam']);
    }

    // ---- Data-type fidelity --------------------------------------------

    public function testDataTypeFidelityThroughCursor(): void
    {
        $rows = [[
            'int_col'     => 42,
            'zero_col'    => 0,
            'str_col'     => 'hello',
            'empty_col'   => '',
            'null_col'    => null,
            'unicode_col' => 'héllo wörld ☂ 日本語',
        ]];
        $rs = $this->forwardRs($rows);

        $this->assertSame(42, $rs->Fields['int_col']);
        $this->assertSame(0, $rs->Fields['zero_col']);
        $this->assertSame('hello', $rs->Fields['str_col']);
        $this->assertSame('', $rs->Fields['empty_col']);
        $this->assertNull($rs->Fields['null_col']);
        $this->assertSame('héllo wörld ☂ 日本語', $rs->Fields['unicode_col']);
    }

    public function testNullFieldValueViaArrayAccess(): void
    {
        $rs = $this->forwardRs([['naam' => null]]);
        // Column present but NULL — must resolve to null, not fatal.
        $this->assertNull($rs['naam']);
        $this->assertNull($rs->Fields['naam']);
    }

    // ---- Array mode (ODBC native) --------------------------------------

    public function testArrayModeIteration(): void
    {
        // arrayMode wraps an ArrayIterator of pre-fetched rows (ODBC path).
        $it = new \ArrayIterator($this->sampleRows());
        $rs = new RecordSet($it, false, true);

        $seen = [];
        while (!$rs->EOF) {
            $seen[] = $rs->Fields['naam'];
            $rs->MoveNext();
        }
        $this->assertEquals(['Alice', 'Bob', 'Charlie'], $seen);
    }

    public function testArrayModeEmptyIsEof(): void
    {
        $it = new \ArrayIterator([]);
        $rs = new RecordSet($it, false, true);
        $this->assertTrue($rs->EOF);
    }

    public function testArrayModeCloseIsSafeWithoutCloseCursor(): void
    {
        // ArrayIterator has no closeCursor(); Close() must guard for that.
        $it = new \ArrayIterator($this->sampleRows());
        $rs = new RecordSet($it, false, true);
        $rs->Close();
        $this->assertTrue($rs->EOF);
    }

    // ---- CaseArray unit -------------------------------------------------

    public function testCaseArrayCaseInsensitiveAccess(): void
    {
        $ca = new CaseArray(['Naam' => 'Alice', 'ID' => 5]);
        $this->assertEquals('Alice', $ca['naam']);
        $this->assertEquals('Alice', $ca['NAAM']);
        $this->assertEquals('Alice', $ca['Naam']);
        $this->assertEquals(5, $ca['id']);
    }

    public function testCaseArrayOffsetExists(): void
    {
        $ca = new CaseArray(['Naam' => 'Alice']);
        $this->assertTrue(isset($ca['naam']));
        $this->assertTrue(isset($ca['Naam']));
        $this->assertFalse(isset($ca['other']));
    }

    public function testCaseArrayIsIterable(): void
    {
        $ca = new CaseArray(['a' => 1, 'b' => 2]);
        $collected = [];
        foreach ($ca as $k => $v) {
            $collected[$k] = $v;
        }
        $this->assertEquals(['a' => 1, 'b' => 2], $collected);
    }

    /**
     * Een numerieke index is "de n-de kolom". ADO stond dat toe en een paar
     * plekken gebruiken $rs->fields[0] als laatste redmiddel; de rijen zelf
     * dragen alleen naamsleutels (ASSOC — élke waarde één keer in geheugen,
     * wat op een grote scrollbare set het verschil is met een memory-fatal),
     * dus de vertaling gebeurt hier.
     */
    public function testCaseArrayNumericIndexIsNthColumn(): void
    {
        $ca = new CaseArray(['Naam' => 'Alice', 'Prijs' => 450000]);
        $this->assertEquals('Alice', $ca[0]);
        $this->assertEquals(450000, $ca[1]);
        $this->assertNull($ca[2]);
        $this->assertTrue(isset($ca[1]));
        $this->assertFalse(isset($ca[5]));
    }

    /**
     * Een scrollbare set draagt zijn rijen als ASSOC: geen numerieke
     * dubbelgangers. Fields() geeft dus élke kolom precies één keer — met
     * FETCH_BOTH kwam elke kolom er twee keer uit, en telde het geheugen
     * voor de hele set dubbel.
     */
    public function testScrollableRowsCarryEachColumnOnce(): void
    {
        $rs = $this->scrollableRs($this->sampleRows());
        $fields = $rs->Fields();
        $names = array_map(static fn($f) => $f->name(), $fields);
        $this->assertEquals(count(array_unique($names)), count($names));
        foreach ($names as $name) {
            $this->assertFalse(is_int($name) || ctype_digit((string) $name),
                'rij bevat numerieke sleutel: ' . var_export($name, true));
        }
        // En de numerieke toegang loopt via de kolomvolgorde, niet via de rij.
        $eerste = array_values($rs->fetchAll()[0])[0];
        $this->assertEquals($eerste, $rs->fields[0]);
    }

    // ---- lib_openRS helper ---------------------------------------------

    public function testLibOpenRsBuildsForwardOnlyRecordSet(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult($this->sampleRows());

        $rs = null;
        $ok = \App\Library\lib_openRS($rs, 'SELECT * FROM t', $conn);

        $this->assertTrue($ok);
        $this->assertNotNull($rs);
        $this->assertFalse($rs->EOF);
        $this->assertEquals('Alice', $rs->Fields['naam']);
        $this->assertEquals(-1, $rs->RecordCount, 'Default cursor is forward-only');
    }

    public function testLibOpenRsScrollableCursor(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult($this->sampleRows());

        $rs = null;
        \App\Library\lib_openRS($rs, 'SELECT * FROM t', $conn, \PDO::CURSOR_SCROLL);

        $this->assertEquals(3, $rs->RecordCount, 'Scroll cursor exposes RecordCount');
        $rs->MoveLast();
        $this->assertEquals('Charlie', $rs->Fields['naam']);
    }

    public function testRecordCountMethodForm(): void
    {
        // The ASP->PHP converter sometimes emits $rs->RecordCount() (method form)
        // for what ADO exposes as a property; __call must handle it like __get.
        $conn = StubConnection::create();
        $conn->enqueueResult($this->sampleRows());
        $rs = null;
        \App\Library\lib_openRS($rs, 'SELECT * FROM t', $conn, \PDO::CURSOR_SCROLL);
        $this->assertEquals(3, $rs->RecordCount(), 'Scrollable: method form returns the count');

        // Forward-only cursor -> -1 (unknown), method form, no BadMethodCallException.
        $conn2 = StubConnection::create();
        $conn2->enqueueResult($this->sampleRows());
        $rs2 = null;
        \App\Library\lib_openRS($rs2, 'SELECT * FROM t', $conn2);
        $this->assertEquals(-1, $rs2->RecordCount());
    }

    // ---- Fields() method form (converted VBScript Field collections) -----

    public function testFieldsMethodReturnsFieldObjectsForCurrentRow(): void
    {
        // Converted VBScript iterates `for each fld in rs.Fields` — emitted as
        // foreach ($rs->Fields() as $fld) with name()/value()/string context.
        // The constructor already positions on the first row (ADO semantics).
        $rs = $this->forwardRs($this->sampleRows());
        $fields = $rs->Fields();
        $this->assertEquals(2, count($fields));
        $this->assertEquals('ID', $fields[0]->name());
        $this->assertEquals(1, $fields[0]->value());
        $this->assertEquals('naam', $fields[1]->name());
        $this->assertEquals('Alice', $fields[1]->value());
        // Case-insensitive property form + string context
        $this->assertEquals('naam', $fields[1]->Name);
        $this->assertEquals('Alice', $fields[1]->Value);
        $this->assertEquals('Alice', (string)$fields[1]);
    }

    public function testFieldsMethodEmptyAfterEofAndStringifiesNull(): void
    {
        $rs = $this->forwardRs([['a' => null]]);
        $fields = $rs->Fields();
        $this->assertEquals('', (string)$fields[0], 'null value stringifies to empty string');
        $rs->MoveNext();
        $this->assertTrue($rs->EOF);
        $this->assertEquals([], $rs->Fields(), 'Past EOF -> empty collection');
    }

    public function testFieldsPropertyFormStillReturnsCaseArraySnapshot(): void
    {
        // The property form ($rs->Fields['col']) must keep its CaseArray
        // snapshot behaviour, untouched by the new Fields() method.
        $rs = $this->forwardRs($this->sampleRows());
        $snapshot = $rs->Fields;
        $this->assertTrue($snapshot instanceof CaseArray);
        $this->assertEquals('Alice', $snapshot['NAAM'], 'case-insensitive key access');
    }
}
