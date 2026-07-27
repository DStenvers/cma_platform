<?php
/**
 * Tests for App\Library\Database::{safeQuery, safeScalar, safeExec}
 * — the null-conn-guard + try/catch + tagged-error_log + typed-
 * default wrappers.
 *
 * Run with: php tests/TestRunner.php DatabaseSafeHelpersTest
 *
 * Contract under test (mirrors the boilerplate the helpers replace):
 *  - Happy path: returns rows / scalar / true respectively.
 *  - PDOException on prepare/execute: returns the typed default
 *    (empty array / $default / false) AND writes one error_log
 *    line prefixed with [$context].
 *  - Empty $context: same return shape, NO log line.
 *  - safeScalar with no rows: returns $default (not false).
 *
 * The helpers were designed to collapse the ~100 sites in
 * mijntoprecepten that hand-rolled the pattern. This test pins
 * the contract so future refactors of the boilerplate replacement
 * can't silently break the consumers.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

class DatabaseSafeHelpersTest extends TestCase
{
    private string $tmpLog;
    private string $originalErrorLog;

    public function setUp(): void
    {
        $this->tmpLog = sys_get_temp_dir() . '/db-safe-helpers-' . bin2hex(random_bytes(4)) . '.log';
        touch($this->tmpLog);
        $this->originalErrorLog = (string)ini_get('error_log');
        ini_set('error_log', $this->tmpLog);
    }

    public function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog);
        if (file_exists($this->tmpLog)) {
            @unlink($this->tmpLog);
        }
    }

    private function logContents(): string
    {
        clearstatcache(true, $this->tmpLog);
        return file_exists($this->tmpLog) ? (string)file_get_contents($this->tmpLog) : '';
    }

    // ------------------------------------------------------------------
    // safeQuery
    // ------------------------------------------------------------------

    public function testSafeQueryHappyPathReturnsRows(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([
            ['ID' => 1, 'naam' => 'Alice'],
            ['ID' => 2, 'naam' => 'Bob'],
        ]);

        $rows = Database::safeQuery('SELECT * FROM tblUsers', [], 'TestCase::happy', $conn);

        $this->assertEquals(2, count($rows));
        $this->assertEquals('Alice', $rows[0]['naam']);
        $this->assertEquals('Bob',   $rows[1]['naam']);
    }

    public function testSafeQueryOnExceptionReturnsEmptyArray(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('SQLSTATE[42000]: Syntax error'));

        $rows = Database::safeQuery('SELECT * FROM nope', [], 'TestCase::dies', $conn);

        // Caller contract: failure → []. No partial result, no nulls.
        $this->assertEquals([], $rows);
    }

    public function testSafeQueryFailureWritesTaggedErrorLog(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Constraint violation'));

        Database::safeQuery('SELECT 1', [], 'Recipes::popular', $conn);

        $log = $this->logContents();
        $this->assertStringContainsString('[Recipes::popular]', $log,
            'safeQuery must prefix the log line with the $context tag so existing grep patterns still match');
        $this->assertStringContainsString('Constraint violation', $log,
            'PDOException message must reach the log');
    }

    public function testSafeQueryWithEmptyContextSuppressesLog(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Intentional probe — should not surface'));

        $rows = Database::safeQuery('SELECT 1', [], '', $conn);

        // Test code intentionally probing the error path doesn't want
        // its noise in the log. Same return shape, just no logging.
        $this->assertEquals([], $rows);
        $this->assertEquals('', $this->logContents(),
            'Empty $context must suppress the log line for opt-out test probes');
    }

    // ------------------------------------------------------------------
    // safeScalar
    // ------------------------------------------------------------------

    public function testSafeScalarHappyPathReturnsFirstColumn(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['cnt' => 42]]);

        $count = Database::safeScalar(
            'SELECT COUNT(*) AS cnt FROM tblUsers',
            [],
            0,
            'TestCase::count',
            $conn
        );

        $this->assertEquals(42, $count);
    }

    public function testSafeScalarOnExceptionReturnsDefault(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Bang'));

        $result = Database::safeScalar('SELECT 1', [], -1, 'TestCase::bang', $conn);

        $this->assertEquals(-1, $result,
            'PDOException must yield the supplied $default, not false');
    }

    public function testSafeScalarWithNoRowsReturnsDefault(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([]);   // valid query, zero rows

        $result = Database::safeScalar(
            'SELECT [id] FROM [recipes] WHERE [id] = 999999',
            [],
            null,
            'TestCase::empty',
            $conn
        );

        // fetchColumn() returns false on empty result-set; the wrapper
        // collapses that to $default so callers don't have to disambiguate
        // "no row" from "row with value false".
        $this->assertNull($result,
            'No-rows result must return $default, NOT false');
    }

    // ------------------------------------------------------------------
    // safeExec
    // ------------------------------------------------------------------

    public function testSafeExecHappyPathReturnsTrue(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([]);   // execute() return — stub returns true on a queued result

        $ok = Database::safeExec(
            'INSERT INTO [audit_log] ([msg]) VALUES (?)',
            ['hello'],
            'TestCase::insert',
            $conn
        );

        $this->assertTrue($ok);
    }

    public function testSafeExecOnExceptionReturnsFalse(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('UNIQUE constraint failed'));

        $ok = Database::safeExec(
            'INSERT INTO [users] ([email]) VALUES (?)',
            ['duplicate@example.com'],
            'Users::create',
            $conn
        );

        $this->assertEquals(false, $ok,
            'PDOException must yield false (the wrapper swallows the throw)');
    }

    public function testSafeExecFailureWritesTaggedErrorLog(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('FK violation'));

        Database::safeExec('DELETE FROM [recipes]', [], 'Recipes::deleteAll', $conn);

        $log = $this->logContents();
        $this->assertStringContainsString('[Recipes::deleteAll]', $log);
        $this->assertStringContainsString('FK violation', $log);
    }
}
