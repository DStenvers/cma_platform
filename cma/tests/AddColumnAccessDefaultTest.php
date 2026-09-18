<?php
/**
 * AddColumnAccessDefaultTest — ALTER TABLE ... ADD COLUMN via de Access-ODBC-driver
 * kent géén DEFAULT-clausule (ook niet voor YESNO: "De instructie ALTER TABLE bevat
 * een syntaxisfout", gezien bij migratie 9.24.0 op CMAusers.mdb). De standaardwaarde
 * hoort dan via een aparte UPDATE te gaan; een nieuwe YESNO-kolom is al False.
 *
 *   php cma/tests/TestRunner.php AddColumnAccessDefaultTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

class AddColumnAccessDefaultTest extends TestCase
{
    private function odbc(): StubConnection
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        // columnExists probes with SELECT TOP 1 [col]: let that fail = column ontbreekt
        $conn->enqueueException(new \PDOException('Te weinig parameters. Het verwachte aantal is: 1.'));
        return $conn;
    }

    private function sqls(StubConnection $conn): array
    {
        return array_map(fn($c) => $c['sql'], $conn->getCalls());
    }

    public function testYesNoFalseDefaultGeenDefaultClausuleEnGeenUpdate(): void
    {
        $conn = $this->odbc();
        $r = Database::addColumnPDO($conn, 'tblGroups', 'isBeheer', 'YESNO', '0');
        $this->assertTrue($r['success'], $r['error'] ?? '');
        $sqls = $this->sqls($conn);
        $this->assertSame(1, count($sqls), implode(' | ', $sqls));
        $this->assertStringNotContainsString('DEFAULT', $sqls[0]);
        $this->assertStringContainsString('ADD COLUMN [isBeheer] YESNO', $sqls[0]);
    }

    public function testYesNoTrueDefaultZetAlleRijenViaUpdate(): void
    {
        $conn = $this->odbc();
        $r = Database::addColumnPDO($conn, 'tblForms', 'menuCopy', 'YESNO', 'TRUE');
        $this->assertTrue($r['success'], $r['error'] ?? '');
        $sqls = $this->sqls($conn);
        $this->assertStringNotContainsString('DEFAULT', $sqls[0]);
        $this->assertSame('UPDATE [tblForms] SET [menuCopy] = True', $sqls[1] ?? '');
    }

    public function testTekstDefaultViaUpdateOpLegeRijen(): void
    {
        $conn = $this->odbc();
        Database::addColumnPDO($conn, 'tblX', 'kleur', 'TEXT(20)', 'rood');
        $sqls = $this->sqls($conn);
        $this->assertStringNotContainsString('DEFAULT', $sqls[0]);
        $this->assertSame("UPDATE [tblX] SET [kleur] = 'rood' WHERE [kleur] IS NULL", $sqls[1] ?? '');
    }
}
