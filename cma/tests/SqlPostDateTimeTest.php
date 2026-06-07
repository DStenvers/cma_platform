<?php
/**
 * Tests for App\Library\SQL::postDateTime — ISO-8601 with seconds.
 *
 * Was: Access branch emitted ambiguous US `#n/j/Y G:i#` (m/d order, no
 * seconds), forcing consumers to inline `#" . date('Y-m-d H:i:s') . "#`.
 * Now both branches emit unambiguous ISO with seconds.
 *
 * Run with: php cma/tests/TestRunner.php SqlPostDateTimeTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\SQL;

class SqlPostDateTimeTest extends TestCase
{
    private string $access = 'Driver={Microsoft Access Driver (*.mdb, *.accdb)}';

    public function testAccessIsoWithSeconds(): void
    {
        $this->assertSame('#2026-06-07 14:30:45#', SQL::postDateTime('2026-06-07 14:30:45', $this->access));
    }

    public function testAccessZeroPadsAndKeepsSeconds(): void
    {
        // 5 Jan, 09:05:03 — must stay ISO (no US m/d), zero-padded, seconds kept.
        $this->assertSame('#2026-01-05 09:05:03#', SQL::postDateTime('2026-01-05 09:05:03', $this->access));
    }

    public function testSqlServerCastsIsoWithSeconds(): void
    {
        $this->assertSame(
            "CAST('2026-06-07 14:30:45' AS DATETIME)",
            SQL::postDateTime('2026-06-07 14:30:45', 'Initial Catalog=mydb')
        );
    }

    public function testNullValueReturnsNull(): void
    {
        $this->assertSame('NULL', SQL::postDateTime(null, $this->access));
    }

    public function testUnparseableValueReturnsNull(): void
    {
        $this->assertSame('NULL', SQL::postDateTime('not a date', $this->access));
    }
}
