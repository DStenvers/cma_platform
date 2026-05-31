<?php
/**
 * Tests for the StubConnection PDO-shaped test double.
 *
 * Run with: php tests/TestRunner.php StubConnectionTest
 *
 * This file IS the harness verification: before service-tests (RecordService,
 * FormDataProvider DB paths) rely on StubConnection, we prove the stub's
 * queue mechanics and call-recording work as documented. If these tests
 * are green the stub is safe to build on; if they fail every dependent
 * test will fail mysteriously.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

class StubConnectionTest extends TestCase
{
    public function testPreparedExecuteReturnsQueuedRows(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['ID' => 1, 'naam' => 'Alice'], ['ID' => 2, 'naam' => 'Bob']]);

        $stmt = $conn->prepare('SELECT * FROM tblUsers WHERE x = ?');
        $stmt->execute([42]);
        $rows = $stmt->fetchAll();

        $this->assertEquals(2, count($rows));
        $this->assertEquals('Alice', $rows[0]['naam']);
        $this->assertEquals('Bob', $rows[1]['naam']);
    }

    public function testRecordsSqlAndParams(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([]);
        $stmt = $conn->prepare('UPDATE tblUsers SET x = ? WHERE ID = ?');
        $stmt->execute(['new-value', 7]);

        $calls = $conn->getCalls();
        $this->assertEquals(1, count($calls));
        $this->assertEquals('prepare', $calls[0]['type']);
        $this->assertEquals('UPDATE tblUsers SET x = ? WHERE ID = ?', $calls[0]['sql']);
        $this->assertEquals(['new-value', 7], $calls[0]['params']);
    }

    public function testQueueIsFifoAcrossMultipleCalls(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['n' => 1]]);
        $conn->enqueueResult([['n' => 2]]);

        $a = $conn->prepare('SELECT 1'); $a->execute(); $rowsA = $a->fetchAll();
        $b = $conn->prepare('SELECT 2'); $b->execute(); $rowsB = $b->fetchAll();

        $this->assertEquals(1, $rowsA[0]['n'], 'First result should come first (FIFO)');
        $this->assertEquals(2, $rowsB[0]['n']);
    }

    public function testFetchReturnsFalseWhenExhausted(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['a' => 1]]);

        $stmt = $conn->prepare('SELECT a FROM t');
        $stmt->execute();
        $this->assertEquals(['a' => 1], $stmt->fetch());
        $this->assertFalse($stmt->fetch(), 'Past-end fetch must return false (matches PDO)');
    }

    public function testExecConsumesFromExecQueue(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueExec(3);
        $conn->enqueueExec(0);

        $this->assertEquals(3, $conn->exec("DELETE FROM t WHERE a = 1"));
        $this->assertEquals(0, $conn->exec("DELETE FROM t WHERE a = 99"));
    }

    public function testExecRecordsSql(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueExec(1);
        $conn->exec("INSERT INTO tblUsers (naam) VALUES ('Alice')");

        $last = $conn->getLastCall();
        $this->assertNotNull($last);
        $this->assertEquals('exec', $last['type']);
        $this->assertStringContainsString('INSERT INTO tblUsers', $last['sql']);
    }

    public function testLastInsertIdComesFromQueue(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueInsertId('42');
        $this->assertEquals('42', $conn->lastInsertId());
    }

    public function testLastInsertIdDefaultsToOneWhenQueueEmpty(): void
    {
        // Production code calls lastInsertId() right after INSERT; if a
        // test forgets to enqueue we return '1' so the caller doesn't
        // see false (which would surface as a "save failed" later).
        $conn = StubConnection::create();
        $this->assertEquals('1', $conn->lastInsertId());
    }

    public function testDriverNameDefaultsToSqliteAndIsOverridable(): void
    {
        $conn = StubConnection::create();
        $this->assertEquals('sqlite', $conn->getAttribute(\PDO::ATTR_DRIVER_NAME));

        $conn->setDriverName('odbc');
        $this->assertEquals('odbc', $conn->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }

    public function testQueryRecordsAndReturnsResults(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['cnt' => 5]]);

        $stmt = $conn->query('SELECT COUNT(*) AS cnt FROM t');
        $row = $stmt->fetch();

        $this->assertEquals(5, $row['cnt']);
        $last = $conn->getLastCall();
        $this->assertEquals('query', $last['type']);
    }

    public function testRowCountMatchesResultSize(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['x' => 1], ['x' => 2], ['x' => 3]]);
        $stmt = $conn->prepare('SELECT *');
        $stmt->execute();
        $this->assertEquals(3, $stmt->rowCount());
    }

    public function testResetClearsEverything(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['n' => 1]]);
        $conn->enqueueExec(7);
        $conn->enqueueInsertId('99');
        $stmt = $conn->prepare('SELECT 1'); $stmt->execute();

        $conn->reset();

        $this->assertEquals([], $conn->getCalls());
        $this->assertEquals('1', $conn->lastInsertId(), 'InsertId queue should be cleared');
        $this->assertEquals(1, $conn->exec('X'), 'Exec queue should be cleared (default 1)');
    }
}
