<?php
/**
 * DatabaseFieldExistsTest — fieldExists()/tableExists()/addField() op Access-ODBC.
 *
 *   php cma/tests/TestRunner.php DatabaseFieldExistsTest
 *
 * De ODBC-driver geeft bij een SELECT rowCount() = -1: hoeveel rijen er zijn weet hij
 * pas na het ophalen. Een "bestaat de kolom"-probe die op rowCount() >= 0 leunt zegt
 * daar dus altijd nee, ook voor een kolom die er gewoon is. Of de kolom bestaat blijkt
 * uit het wel of niet slagen van de query zelf. De stub hieronder gedraagt zich als die
 * driver: rowCount() -1, en een ontbrekende kolom geeft een fout.
 *
 * De meegegeven PDO-verbinding is de verbinding waarop gekeken wordt (ASP-geconverteerde
 * aanroepers geven hun open $conn mee); zonder verbinding geldt de standaard 'data'.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

/** Statement zoals PDO-ODBC hem geeft: rowCount() is -1 bij een SELECT. */
class OdbcRowCountStatement extends StubStatement
{
    public function rowCount(): int
    {
        return -1;
    }
}

/** StubConnection met driver 'odbc' die OdbcRowCountStatement-en teruggeeft. */
class OdbcRowCountConnection extends StubConnection
{
    public static function make(): self
    {
        $conn = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $conn->setDriverName('odbc');
        return $conn;
    }

    public function prepare(string $query, array $options = []): \PDOStatement|false
    {
        return new OdbcRowCountStatement($this, $query);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $this->throwIfQueued();
        $this->recordStatement($query, []);
        return new OdbcRowCountStatement($this, $query);
    }
}

class DatabaseFieldExistsTest extends TestCase
{
    public function testBestaandeKolomOpOdbcIsTrue(): void
    {
        $conn = OdbcRowCountConnection::make();
        $conn->enqueueResult([]);   // geen rijen, wel geldig: de kolom is er
        $this->assertTrue(Database::fieldExists($conn, 'tblToetsen', 'Inleverdatum'));
    }

    public function testOntbrekendeKolomOpOdbcIsFalse(): void
    {
        $conn = OdbcRowCountConnection::make();
        $conn->enqueueException(new \PDOException('Te weinig parameters. Het verwachte aantal is: 1.'));
        $this->assertFalse(Database::fieldExists($conn, 'tblToetsen', 'BestaatNiet'));
    }

    public function testBestaandeTabelOpOdbcIsTrue(): void
    {
        $conn = OdbcRowCountConnection::make();
        $conn->enqueueResult([]);
        $this->assertTrue(Database::tableExists($conn, 'tblToetsen'));
    }

    public function testOntbrekendeTabelOpOdbcIsFalse(): void
    {
        $conn = OdbcRowCountConnection::make();
        $conn->enqueueException(new \PDOException('De Microsoft Access-database-engine kan de invoertabel of query tblWeg niet vinden.'));
        $this->assertFalse(Database::tableExists($conn, 'tblWeg'));
    }

    public function testProbeDraaitOpDeMeegegevenVerbinding(): void
    {
        $conn = OdbcRowCountConnection::make();
        $conn->enqueueResult([]);
        Database::fieldExists($conn, 'tblToetsen', 'Inleverdatum');
        $calls = $conn->getCalls();
        $this->assertSame(1, count($calls), 'precies één probe op de meegegeven verbinding');
        $this->assertStringContainsString('[Inleverdatum]', $calls[0]['sql']);
        $this->assertStringContainsString('[tblToetsen]', $calls[0]['sql']);
    }

    public function testAddFieldOpOdbcVoegtDeKolomToe(): void
    {
        $conn = OdbcRowCountConnection::make();
        // columnExists-probe vooraf: kolom ontbreekt nog
        $conn->enqueueException(new \PDOException('Te weinig parameters. Het verwachte aantal is: 1.'));
        $this->assertTrue(Database::addField($conn, 'tblToetsen', 'Opmerking', 'varchar', 50));
        $sqls = array_map(fn($c) => $c['sql'], $conn->getCalls());
        $alter = array_values(array_filter($sqls, fn($s) => stripos($s, 'ALTER TABLE') !== false));
        $this->assertSame(1, count($alter), implode(' | ', $sqls));
        $this->assertStringContainsString('[tblToetsen]', $alter[0]);
        $this->assertStringContainsString('[Opmerking]', $alter[0]);
        $this->assertStringContainsString('(50)', $alter[0]);
    }

    public function testAddFieldOpOdbcMislukteDdlIsFalse(): void
    {
        $conn = OdbcRowCountConnection::make();
        $conn->enqueueException(new \PDOException('Te weinig parameters. Het verwachte aantal is: 1.'));
        $conn->enqueueException(new \PDOException('De instructie ALTER TABLE bevat een syntaxisfout.'));
        $this->assertFalse(Database::addField($conn, 'tblToetsen', 'Opmerking', 'varchar', 50));
    }
}
