<?php
/**
 * SqliteDialectTest — de platform-SQL draait ook op SQLite.
 *
 * Run with: php cma/tests/TestRunner.php SqliteDialectTest
 *
 * Waarom deze test bestaat:
 * SQL::processSQL() kende maar twee smaken — SQL Server, of anders Access. SQLite
 * viel dus in de Access-tak, en die tak vertaalt actief de verkeerde kant op:
 * `DELETE FROM` werd `DELETE * FROM` (syntaxfout) en `= True` werd `= -1` (geen
 * fout, gewoon nul rijen). Schema-SQL ging helemaal ongefilterd naar de driver,
 * waardoor een verse installatie al bij migratie 1.0.1 stopte:
 *
 *   ✗ Fout: SQLSTATE[HY000]: General error: 1 near "AUTOINCREMENT": syntax error
 *
 * De vertaling hoort in Database/SQL te zitten en niet in elke migratie, dus de
 * meeste tests hieronder draaien een écht SQLite-geheugendatabase: wat hier langs
 * komt is wat de driver accepteert, niet wat wij denken dat hij accepteert.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;
use App\Library\SQL;

class SqliteDialectTest extends TestCase
{
    private function sqlite(): \PDO
    {
        $conn = new \PDO('sqlite::memory:');
        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $conn;
    }

    private function odbc(): StubConnection
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        return $conn;
    }

    // ---- Het dialect herkennen ------------------------------------------------

    public function testDialectHerkentSqliteAanDriverEnDsn(): void
    {
        $this->assertEquals('sqlite', SQL::dialect($this->sqlite()));
        $this->assertEquals('sqlite', SQL::dialect('sqlite:/site/db/data.sqlite'));
        $this->assertEquals('access', SQL::dialect($this->odbc()));
        $this->assertEquals('sqlserver', SQL::dialect('Provider=SQLOLEDB;Initial Catalog=cma'));
    }

    // ---- Schema (DDL) ---------------------------------------------------------

    /** De migratie die het meldde: tblCMAMonitoring uit 1.0.1, ongewijzigd. */
    public function testMonitoringTabelWordtOpSqliteAangemaakt(): void
    {
        $conn = $this->sqlite();
        Database::executeDdl($conn, "CREATE TABLE tblCMAMonitoring (
            ID AUTOINCREMENT PRIMARY KEY,
            datestamp DATETIME,
            Username VARCHAR(78),
            Formname VARCHAR(78),
            Formid INTEGER,
            RecordID LONG,
            Actie VARCHAR(78),
            Notificatie MEMO
        )");

        $this->assertTrue(Database::tableExistsPDO($conn, 'tblCMAMonitoring'));

        // AUTOINCREMENT moet ook echt tellen — een kolom die alleen INTEGER heet
        // laat de ID's op NULL staan en dat merk je pas als je gaat koppelen.
        $conn->exec("INSERT INTO tblCMAMonitoring (Actie) VALUES ('een')");
        $conn->exec("INSERT INTO tblCMAMonitoring (Actie) VALUES ('twee')");
        $this->assertEquals(
            [1, 2],
            array_map('intval', $conn->query('SELECT ID FROM tblCMAMonitoring ORDER BY ID')->fetchAll(\PDO::FETCH_COLUMN))
        );
    }

    public function testAccessTypenamenWordenSqliteTypen(): void
    {
        $conn = $this->sqlite();
        Database::executeDdl($conn, "CREATE TABLE [api_call_log] (
            [id]            AUTOINCREMENT PRIMARY KEY,
            [endpoint]      MEMO,
            [latency_ms]    LONG,
            [ok]            YESNO NOT NULL,
            [prijs]         CURRENCY,
            [called_from]   VARCHAR(60)
        )");

        $types = [];
        foreach ($conn->query('PRAGMA table_info([api_call_log])')->fetchAll(\PDO::FETCH_ASSOC) as $col) {
            $types[$col['name']] = strtoupper($col['type']);
        }
        $this->assertEquals('INTEGER', $types['id']);
        $this->assertEquals('TEXT', $types['endpoint'], 'MEMO hoort TEXT te worden, niet NUMERIC-affiniteit');
        $this->assertEquals('INTEGER', $types['latency_ms']);
        $this->assertEquals('INTEGER', $types['ok']);
        $this->assertEquals('REAL', $types['prijs']);
        $this->assertEquals('VARCHAR(60)', $types['called_from'], 'VARCHAR(n) betekent overal hetzelfde');
    }

    /** Een kolom die zelf memo of long heet houdt zijn naam. */
    public function testKolomnaamDieOpEenTypenaamLijktBlijftStaan(): void
    {
        $sql = SQL::processDdl('sqlite:x', 'CREATE TABLE t (memo MEMO, long LONG)');
        $this->assertStringContainsString('memo TEXT', $sql);
        $this->assertStringContainsString('long INTEGER', $sql);
    }

    public function testAccessKrijgtZijnEigenDdlOngewijzigdTerug(): void
    {
        $ddl = 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, Notitie MEMO)';
        $this->assertEquals($ddl, SQL::processDdl($this->odbc(), $ddl));
    }

    public function testSqlServerKrijgtEenIdentityKolom(): void
    {
        $sql = SQL::processDdl('Provider=SQLOLEDB;Initial Catalog=cma',
            'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, Notitie MEMO)');
        $this->assertStringContainsString('INT IDENTITY(1,1) PRIMARY KEY', $sql);
        $this->assertStringContainsString('NVARCHAR(MAX)', $sql);
    }

    public function testKolomToevoegenEnIndexDroppenOpSqlite(): void
    {
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY)');

        $res = Database::addColumnPDO($conn, 't', 'Notificatie', 'MEMO');
        $this->assertTrue($res['success'], $res['error'] ?? '');
        $this->assertTrue(Database::columnExistsPDO($conn, 't', 'Notificatie'));
        $this->assertFalse(Database::columnExistsPDO($conn, 't', 'BestaatNiet'));

        $this->assertTrue(Database::addIndexPDO($conn, 't', ['Notificatie'], 'ix_t_not')['success']);
        // SQLite kent geen "DROP INDEX ... ON <tabel>" — dat is een syntaxfout.
        $this->assertTrue(Database::dropIndexPDO($conn, 't', 'ix_t_not')['success']);
        $this->assertTrue(Database::dropIndexPDO($conn, 't', 'ix_t_not')['success'], 'nogmaals droppen is een no-op');
    }

    // ---- Queries --------------------------------------------------------------

    public function testTopWordtLimit(): void
    {
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, naam VARCHAR(20))');
        $conn->exec("INSERT INTO t (naam) VALUES ('een')");
        $conn->exec("INSERT INTO t (naam) VALUES ('twee')");

        $rijen = Database::fetchAll('SELECT TOP 1 naam FROM t ORDER BY ID', [], $conn);
        $this->assertEquals([['naam' => 'een']], $rijen);
    }

    public function testDeleteBehoudtZijnVorm(): void
    {
        // De Access-tak maakt hier `DELETE * FROM` van; SQLite weigert dat.
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY)');
        $conn->exec('INSERT INTO t DEFAULT VALUES');

        Database::query('DELETE FROM t WHERE ID = ?', [1], $conn);
        $this->assertEquals(0, (int) $conn->query('SELECT COUNT(*) FROM t')->fetchColumn());
    }

    public function testAccessBooleansMatchenOpSqlite(): void
    {
        // Access schrijft True weg als -1, SQLite als 1: `= True` en `= -1` moeten
        // hier allebei op 1 uitkomen, anders levert de query stil nul rijen op.
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, actief YESNO)');
        $conn->exec('INSERT INTO t (actief) VALUES (1)');
        $conn->exec('INSERT INTO t (actief) VALUES (0)');

        $this->assertCount(1, Database::fetchAll('SELECT ID FROM t WHERE actief = True', [], $conn));
        $this->assertCount(1, Database::fetchAll('SELECT ID FROM t WHERE actief = -1', [], $conn));
        $this->assertCount(1, Database::fetchAll('SELECT ID FROM t WHERE actief = False', [], $conn));
    }

    public function testAccessDatumliteralWordtEenIsoString(): void
    {
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, datum VARCHAR(20))');
        $conn->exec("INSERT INTO t (datum) VALUES ('2026-08-13')");

        $this->assertCount(1, Database::fetchAll('SELECT ID FROM t WHERE datum = #2026-08-13#', [], $conn));
    }

    public function testFunctienamenWordenVertaald(): void
    {
        $conn = $this->sqlite();
        $rij = Database::fetchAll("SELECT ucase('ab') AS a, lcase('CD') AS b, mid('abcdef',2,3) AS c, len('abcd') AS d, nz(NULL,'x') AS e", [], $conn);
        $this->assertEquals([['a' => 'AB', 'b' => 'cd', 'c' => 'bcd', 'd' => 4, 'e' => 'x']], $rij);
    }

    public function testPostStringEscapesLandenOpSqlite(): void
    {
        // postString() bouwt "' & chr(39) & '" voor een apostrof — Access-syntax die
        // op SQLite een bitwise AND zou zijn. De vertaling maakt er || en char() van.
        $conn = $this->sqlite();
        $waarde = SQL::postString("O'Brien", 'sqlite::memory:');
        $rij = Database::fetchAll('SELECT ' . $waarde . ' AS naam', [], $conn);
        $this->assertEquals("O'Brien", $rij[0]['naam']);
    }

    public function testStringliteralsBlijvenOngemoeid(): void
    {
        // Elke vertaling hierboven is syntaxwerk en mag de gegevens niet raken.
        $conn = $this->sqlite();
        $rij = Database::fetchAll("SELECT 'Jansen & Zn' AS a, '<sort:naam>' AS b, 'delete from x' AS c", [], $conn);
        $this->assertEquals('Jansen & Zn', $rij[0]['a']);
        $this->assertEquals('<sort:naam>', $rij[0]['b']);
        $this->assertEquals('delete from x', $rij[0]['c']);
    }

    public function testGuidVergelijkingBlijftEenGelijkteken(): void
    {
        // De LIKE-omweg is een Access-ODBC-pleister; op SQLite is het een tabelscan.
        $access = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=/site/db/data.mdb';
        $this->assertStringContainsString('=', SQL::guidEquals('guid', '{ABC}', 'sqlite:/x'));
        $this->assertStringContainsString('LIKE', SQL::guidEquals('guid', '{ABC}', $access));
    }

    // ---- De afspraak met de migraties ----------------------------------------

    /**
     * Schema-SQL in een migratie hoort via Database::executeDdl() te gaan. Een
     * rechtstreekse PDO::exec() draagt zijn eigen dialect mee, en dat is precies
     * hoe migratie 1.0.1 SQLite een AUTOINCREMENT-kolom aanbood.
     */
    public function testGeenMigratieVoertSchemaSqlRechtstreeksUit(): void
    {
        $overtreders = [];
        foreach (glob(__DIR__ . '/../migrations/*.php') as $bestand) {
            $inhoud = (string) file_get_contents($bestand);
            if (preg_match('/->exec\(\s*["\']?\s*(CREATE|ALTER|DROP)\s/i', $inhoud)
                || preg_match('/->exec\(\$(sql|createSql|ddl)\b/i', $inhoud)) {
                $overtreders[] = basename($bestand);
            }
        }
        $this->assertEquals([], $overtreders,
            'deze migraties voeren schema-SQL rechtstreeks uit: ' . implode(', ', $overtreders));
    }
}
