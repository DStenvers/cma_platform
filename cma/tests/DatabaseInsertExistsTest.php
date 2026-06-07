<?php
/**
 * Tests for App\Library\Database::{getLastInsertId, recordExists}.
 *
 * - getLastInsertId is ODBC/Access-aware: the odbc driver round-trips
 *   SELECT @@IDENTITY (PDO::lastInsertId is unreliable on Access); other
 *   drivers use PDO::lastInsertId(). Must run on the passed connection.
 * - recordExists collapses the `SELECT TOP 1 … fetchColumn() !== false`
 *   exists-probe boilerplate.
 *
 * Run with: php cma/tests/TestRunner.php DatabaseInsertExistsTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

class DatabaseInsertExistsTest extends TestCase
{
    public function testGetLastInsertIdOdbcUsesIdentitySelect(): void
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        $conn->enqueueResult([['' => 42]]); // SELECT @@IDENTITY → 42
        $this->assertSame(42, Database::getLastInsertId($conn));
    }

    public function testGetLastInsertIdNonOdbcUsesLastInsertId(): void
    {
        $conn = StubConnection::create(); // default driver 'sqlite'
        $conn->enqueueInsertId('77');
        $this->assertSame(77, Database::getLastInsertId($conn));
    }

    public function testGetLastInsertIdReturnsZeroOnException(): void
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        $conn->enqueueException(new \RuntimeException('boom'));
        $this->assertSame(0, Database::getLastInsertId($conn));
    }

    public function testRecordExistsTrueWhenRowReturned(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([['1' => 1]]);
        $this->assertTrue(Database::recordExists('SELECT TOP 1 1 FROM x WHERE id = ?', [5], '', $conn));
    }

    public function testRecordExistsFalseWhenNoRow(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueResult([]);
        $this->assertFalse(Database::recordExists('SELECT TOP 1 1 FROM x WHERE id = ?', [9], '', $conn));
    }

    public function testRecordExistsFalseOnException(): void
    {
        $conn = StubConnection::create();
        $conn->enqueueException(new \RuntimeException('boom'));
        $this->assertFalse(Database::recordExists('SELECT TOP 1 1 FROM x', [], '', $conn));
    }
}
