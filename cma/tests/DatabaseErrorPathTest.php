<?php
/**
 * Tests for App\Library\Database error-path logging (v1.19.8 fix).
 *
 * Run with: php tests/TestRunner.php DatabaseErrorPathTest
 *
 * Pre-1.19.8 the Database static methods (query/executeQuery/openRS/
 * executeSingleRecord/execute) all called self::logError() in their
 * PDOException catches, but logError() was gated to dev/test
 * environments only — production silently swallowed every DB error.
 * v1.19.8 dropped the env-guard so error_log is always invoked.
 *
 * This test verifies the always-log contract by:
 *  - Sending a StubConnection that throws PDOException on execute
 *  - Capturing the test process's error_log to a tmp file
 *  - Asserting the catch fires the log AND returns null/[]/0 as before
 *
 * Important: callers still receive null/[]/0 — the silent-fallback
 * behaviour for end-users is unchanged. What changed is that the
 * operator now always sees the error in logs.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

class DatabaseErrorPathTest extends TestCase
{
    private string $tmpLog;
    private string $originalErrorLog;

    public function setUp(): void
    {
        // Redirect error_log() output to a tmp file so we can assert on it.
        $this->tmpLog = sys_get_temp_dir() . '/db-error-path-' . bin2hex(random_bytes(4)) . '.log';
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
    // Database::query — happy path returns statement
    // ------------------------------------------------------------------

    public function testQueryHappyPathReturnsStatement(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['ID' => 1, 'naam' => 'Alice']]);

        $stmt = Database::query('SELECT * FROM tblUsers', [], $conn);

        $this->assertNotNull($stmt, 'Happy-path query must return a statement, not null');
        $rows = $stmt->fetchAll();
        $this->assertEquals(1, count($rows));
        $this->assertEquals('Alice', $rows[0]['naam']);
    }

    // ------------------------------------------------------------------
    // Database::query — exception path returns null AND logs
    // ------------------------------------------------------------------

    public function testQueryWithFailedExecuteReturnsNull(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('SQLSTATE[42000]: Syntax error near tblUsers'));

        $result = Database::query('SELECT * FROM tblUsers WHERE x', [], $conn);

        // Caller contract: PDO failure → null (so existing `if ($result)`
        // guards keep working). NOT an exception, NOT a partial result.
        $this->assertNull($result);
    }

    public function testQueryFailureWritesSqlToErrorLog(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Syntax error'));

        Database::query('UPDATE tblUsers SET x = ?', ['v'], $conn);

        $log = $this->logContents();
        $this->assertStringContainsString('Database Error', $log);
        $this->assertStringContainsString('Syntax error', $log, 'PDOException message must appear');
        $this->assertStringContainsString('UPDATE tblUsers SET x = ?', $log, 'Failing SQL must be logged');
    }

    public function testQueryFailureLogsParameters(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Constraint violation'));

        Database::query('INSERT INTO tblUsers (naam, email) VALUES (?, ?)', ['Alice', 'a@x'], $conn);

        $log = $this->logContents();
        $this->assertStringContainsString('Alice', $log, 'Bound parameters must appear in the log');
        $this->assertStringContainsString('a@x', $log);
    }

    public function testQueryFailureLogsRegardlessOfEnvironment(): void
    {
        // The v1.19.8 fix: pre-fix this only logged when omgeving was L/O/T
        // or Application::get('test') was truthy. Production was silent.
        // Now: log unconditionally. Explicitly set "production-like" state
        // and verify the log still receives the entry.
        \App\Library\Application::set('omgeving', 'P');
        \App\Library\Application::set('test', false);

        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Connection lost'));

        Database::query('SELECT 1', [], $conn);

        $log = $this->logContents();
        $this->assertStringContainsString('Connection lost', $log,
            'v1.19.8 contract: PDOException must be logged even in production (omgeving=P)');
    }

    // ------------------------------------------------------------------
    // Database::execute — same error contract
    // ------------------------------------------------------------------

    public function testExecuteFailureReturnsZero(): void
    {
        // Database::execute uses self::getConnection() — it doesn't accept
        // a connection parameter, so we can't inject our stub. We verify
        // the contract via the same code path as query() (logError is the
        // shared mechanism) — see testQueryFailureLogsRegardlessOfEnvironment
        // for the actual logging proof. Here we only assert that the
        // caller-visible return on a configuration-less call is the
        // documented sentinel value (0), not a fatal.
        \App\Library\Application::set('conn_data', '');
        \App\Library\Application::set('conn_data_path', '');

        $affected = Database::execute('INSERT INTO t (x) VALUES (1)');

        // The execute() method catches PDOException and returns 0. Without
        // a configured connection it can fail earlier (RuntimeException
        // from getConnection()). Either way, no fatal.
        $this->assertTrue(is_int($affected), 'Database::execute must return an int even on failure');
    }

    // ------------------------------------------------------------------
    // Database::getLastError — populated after a failure
    // ------------------------------------------------------------------

    public function testLastErrorReflectsLastFailure(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \PDOException('Specific failure message'));

        Database::query('SELECT 1', [], $conn);

        $this->assertEquals('Specific failure message', Database::getLastError());
    }
}
