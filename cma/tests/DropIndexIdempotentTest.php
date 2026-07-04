<?php
/**
 * Regression test for Database::dropIndexPDO() idempotency on Access ODBC.
 *
 * Migration 5.8.0 drops the tblCMAMonitoring_formid index before dropping the
 * Formid column. On a legacy DB already at the target schema the index is gone,
 * and Access ODBC reports that as:
 *
 *   -1404 No such index 'tblCMAMonitoring_formid' on table 'tblCMAMonitoring'.
 *
 * The phrase "No such index" contains neither "not found" nor "does not exist",
 * so before the fix dropIndexPDO returned success=false and the whole migration
 * chain aborted at 5.8.0. These tests lock in that a missing index is a no-op.
 *
 * Run with: php tests/TestRunner.php DropIndexIdempotentTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

class DropIndexIdempotentTest extends TestCase
{
    private function odbcConnThatThrows(string $message): StubConnection
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        $conn->enqueueException(new \PDOException($message));
        return $conn;
    }

    public function testAccessNoSuchIndexIsTreatedAsAlreadyDropped(): void
    {
        $conn = $this->odbcConnThatThrows(
            "No such index 'tblCMAMonitoring_formid' on table 'tblCMAMonitoring'."
        );
        $res = Database::dropIndexPDO($conn, 'tblCMAMonitoring', 'tblCMAMonitoring_formid');
        $this->assertTrue($res['success'], 'missing index must be idempotent success');
    }

    public function testCouldNotFindObjectIsTreatedAsAlreadyDropped(): void
    {
        $conn = $this->odbcConnThatThrows("The database engine could not find the object 'idx_foo'.");
        $res = Database::dropIndexPDO($conn, 'tblFoo', 'idx_foo');
        $this->assertTrue($res['success']);
    }

    public function testExistingWordingsStillPass(): void
    {
        foreach (['index not found', 'index does not exist', 'index bestaat niet'] as $msg) {
            $conn = $this->odbcConnThatThrows($msg);
            $res = Database::dropIndexPDO($conn, 'tblFoo', 'idx_foo');
            $this->assertTrue($res['success'], "should stay idempotent for: $msg");
        }
    }

    public function testUnrelatedErrorStillFails(): void
    {
        // A genuine error (not "index absent") must NOT be swallowed.
        $conn = $this->odbcConnThatThrows('Disk I/O error: the file is locked by another process.');
        $res = Database::dropIndexPDO($conn, 'tblFoo', 'idx_foo');
        $this->assertFalse($res['success'], 'a real failure must propagate');
    }
}
