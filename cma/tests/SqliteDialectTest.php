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

    /** Tabel met twee rijen: 'een' en 'twee'. */
    private function tweeRijen(): \PDO
    {
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, naam VARCHAR(20))');
        $conn->exec("INSERT INTO t (naam) VALUES ('een')");
        $conn->exec("INSERT INTO t (naam) VALUES ('twee')");
        return $conn;
    }

    public function testTopWordtLimit(): void
    {
        $rijen = Database::fetchAll('SELECT TOP 1 naam FROM t ORDER BY ID', [], $this->tweeRijen());
        $this->assertEquals([['naam' => 'een']], $rijen);
    }

    /**
     * LIMIT hoort aan het einde van het HELE statement.
     *
     * De vertaling van TOP liep eerst mee in de pas die de SQL bij elke
     * stringliteral opknipt, dus belandde `LIMIT n` aan het einde van het stuk
     * vóór de eerste literal in plaats van aan het einde van de query:
     *
     *   SELECT naam FROM t WHERE soort = LIMIT 5'fout' ORDER BY ID
     *
     * Daarmee was élke TOP-query met ook maar één literal erin stuk. Dat bleef
     * onopgemerkt omdat de test hierboven geen enkele literal bevat.
     */
    public function testTopMetEenLiteralInDeQuery(): void
    {
        $rijen = Database::fetchAll("SELECT TOP 1 naam FROM t WHERE naam <> 'twee' ORDER BY ID", [], $this->tweeRijen());
        $this->assertEquals([['naam' => 'een']], $rijen);
    }

    public function testTopNaastEenDatumfunctie(): void
    {
        // De vorm uit de logreader: TOP samen met DateAdd, dat zelf een literal
        // aanmaakt (' days'). Precies het geval dat in productie omviel.
        $conn = $this->sqlite();
        Database::executeDdl($conn, 'CREATE TABLE fouten (ID AUTOINCREMENT PRIMARY KEY, melding MEMO, datestamp DATETIME)');
        $conn->exec("INSERT INTO fouten (melding, datestamp) VALUES ('vers', datetime('now','localtime'))");
        $conn->exec("INSERT INTO fouten (melding, datestamp) VALUES ('oud', '2020-01-01 00:00:00')");

        $rijen = Database::fetchAll(
            "SELECT TOP 500 ID, melding FROM fouten WHERE datestamp >= DateAdd('d', -7, Now()) ORDER BY datestamp DESC",
            [], $conn);
        $this->assertEquals([['ID' => 1, 'melding' => 'vers']], $rijen);
    }

    public function testDistinctTopMetLiteral(): void
    {
        $rijen = Database::fetchAll("SELECT DISTINCT TOP 2 naam FROM t WHERE naam <> 'x' ORDER BY ID", [], $this->tweeRijen());
        $this->assertCount(2, $rijen);
    }

    public function testEenLimitInEenLiteralTeltNietMee(): void
    {
        // Anders zou de tekst 'LIMIT 9' in een zoekterm de echte begrenzing wegnemen.
        $conn = $this->tweeRijen();
        $vertaald = SQL::processSQL($conn, "SELECT TOP 1 naam FROM t WHERE naam = 'met LIMIT 9 erin'");
        $this->assertStringContainsString("'met LIMIT 9 erin' LIMIT 1", $vertaald);
        $this->assertEquals([], $conn->query($vertaald)->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function testEenEchteLimitBlijftStaan(): void
    {
        $vertaald = SQL::processSQL($this->tweeRijen(), 'SELECT TOP 5 naam FROM t ORDER BY ID LIMIT 1');
        $this->assertEquals(1, preg_match_all('/\bLIMIT\b/i', $vertaald), 'er hoort er maar één te staan');
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

    // ---- De drie Access-datumfuncties ----------------------------------------

    /** Draai $sql vertaald op een SQLite-geheugendatabase en geef de eerste kolom. */
    private function eersteWaarde(string $sql)
    {
        $conn = $this->sqlite();
        $conn->exec('CREATE TABLE t (d TEXT)');
        $conn->exec("INSERT INTO t VALUES ('2026-03-09 14:05:07')");
        return $conn->query(SQL::processSQL($conn, $sql))->fetchColumn();
    }

    public function testDateAddPerInterval(): void
    {
        $this->assertEquals('2026-02-07 00:00:00', $this->eersteWaarde("SELECT DateAdd('d', -30, #2026-03-09#)"));
        $this->assertEquals('2026-05-09 14:05:07', $this->eersteWaarde("SELECT DateAdd('m', 2, d) FROM t"));
        $this->assertEquals('2027-03-09 14:05:07', $this->eersteWaarde("SELECT DateAdd('yyyy', 1, d) FROM t"));
        // ww telt weken, q kwartalen — allebei een veelvoud van een SQLite-eenheid.
        $this->assertEquals('2026-03-16 14:05:07', $this->eersteWaarde("SELECT DateAdd('ww', 1, d) FROM t"));
        $this->assertEquals('2026-06-09 14:05:07', $this->eersteWaarde("SELECT DateAdd('q', 1, d) FROM t"));
        $this->assertEquals('2026-03-09 16:05:07', $this->eersteWaarde("SELECT DateAdd('h', 2, d) FROM t"));
        // 'n' is minuten en 'm' maanden — omgekeerd aan wat strftime doet.
        $this->assertEquals('2026-03-09 14:35:07', $this->eersteWaarde("SELECT DateAdd('n', 30, d) FROM t"));
    }

    public function testDateAddNeemtEenExpressieAlsAantal(): void
    {
        // Het aantal hoeft geen constante te zijn; SQLite krijgt zijn modifier als
        // een string die met || opgebouwd wordt.
        $this->assertEquals('2026-03-11 14:05:07',
            $this->eersteWaarde("SELECT DateAdd('d', (SELECT 2), d) FROM t"));
    }

    public function testDateDiffTeltGrenzenZoalsAccess(): void
    {
        $this->assertEquals(67, (int) $this->eersteWaarde("SELECT DateDiff('d','2026-01-01','2026-03-09')"));
        // Access telt maandgrenzen, geen hele maanden: 31 jan -> 1 mrt is 2.
        $this->assertEquals(2, (int) $this->eersteWaarde("SELECT DateDiff('m','2026-01-31','2026-03-01')"));
        $this->assertEquals(6, (int) $this->eersteWaarde("SELECT DateDiff('yyyy','2020-12-31','2026-01-01')"));
        // En uurgrenzen, geen verstreken uren: 09:50 -> 10:10 is 1, niet 0.
        $this->assertEquals(1, (int) $this->eersteWaarde("SELECT DateDiff('h','2026-01-01 09:50','2026-01-01 10:10')"));
        $this->assertEquals(20, (int) $this->eersteWaarde("SELECT DateDiff('n','2026-01-01 09:50','2026-01-01 10:10')"));
        $this->assertEquals(-5, (int) $this->eersteWaarde("SELECT DateDiff('d','2026-03-09','2026-03-04')"));
    }

    public function testFormatDatums(): void
    {
        $this->assertEquals('09 mrt 2026', $this->eersteWaarde("SELECT Format([d],'dd mmm yyyy') FROM t"));
        $this->assertEquals('2026-03-09', $this->eersteWaarde("SELECT Format(d,'yyyy-mm-dd') FROM t"));
        $this->assertEquals('maandag 9 maart 2026', $this->eersteWaarde("SELECT Format(d,'dddd d mmmm yyyy') FROM t"));
        $this->assertEquals('09-03-2026', $this->eersteWaarde("SELECT Format(d,'Short Date') FROM t"));
        $this->assertEquals('14:05:07', $this->eersteWaarde("SELECT Format(d,'hh:nn:ss') FROM t"));
        // Enkelvoudige tokens zijn zonder voorloopnul.
        $this->assertEquals('9-3-2026', $this->eersteWaarde("SELECT Format(d,'d-m-yyyy') FROM t"));
    }

    /**
     * De valkuil van het hele formaat: in Access is `m` maand, BEHALVE direct na een
     * uur-token. 'dd mmm yyyy HH:mm' — verreweg het meest gebruikte formaat in de
     * rapportqueries — staat of valt daarmee; zonder die regel leest de tijd als maand.
     */
    public function testMinuutVersusMaandInEenFormaat(): void
    {
        $this->assertEquals('09 mrt 2026 14:05', $this->eersteWaarde("SELECT format( d, \"dd mmm yyyy HH:mm\") FROM t"));
        $this->assertEquals('14:05:07', $this->eersteWaarde("SELECT Format(d,'hh:mm:ss') FROM t"));
        // Zónder uur ervoor blijft mm gewoon de maand.
        $this->assertEquals('03', $this->eersteWaarde("SELECT Format(d,'mm') FROM t"));
    }

    public function testFormatGetallen(): void
    {
        $this->assertEquals('1234.57', $this->eersteWaarde("SELECT Format(1234.5678,'0.00')"));
        $this->assertEquals(1235, (int) $this->eersteWaarde("SELECT Format(1234.5678,'0')"));
    }

    public function testGenesteEnGeciteerdeAanroepen(): void
    {
        $this->assertEquals('10-03-2026', $this->eersteWaarde("SELECT Format(DateAdd('d',1,d),'dd-mm-yyyy') FROM t"));
        // Een functienaam in een stringliteral is gegevens, geen aanroep.
        $this->assertEquals('Format(x)', $this->eersteWaarde("SELECT 'Format(x)'"));
        // Een komma binnen het formaat splitst de argumenten niet.
        $this->assertEquals('09, mrt', $this->eersteWaarde("SELECT Format(d,'dd, mmm') FROM t"));
    }

    /**
     * Een onvertaalbaar formaat moet de query stoppen, niet stilletjes iets teruggeven.
     * SQLite heeft namelijk een eigen format() — een alias van printf sinds 3.38 — dus
     * een onaangeraakte Access-aanroep geeft geen fout maar bij elke rij letterlijk de
     * formaatstring terug.
     */
    public function testOnvertaalbaarFormaatStoptDeQuery(): void
    {
        $conn = $this->sqlite();
        $vertaald = SQL::processSQL($conn, "SELECT Format('2026-03-09','q')");
        $this->assertStringContainsString('access_format_niet_vertaald', $vertaald);
        $this->assertStringContainsString("'q'", $vertaald, 'de melding hoort het formaat te noemen');

        $mislukt = false;
        try {
            $conn->query($vertaald);
        } catch (\Throwable $e) {
            $mislukt = str_contains($e->getMessage(), 'access_format_niet_vertaald');
        }
        $this->assertTrue($mislukt, 'een onvertaalbaar formaat hoort luid te falen');
    }

    /** SQLite's eigen format(formaat, …) heeft de formaatstring vóóraan en blijft. */
    public function testSqliteEigenFormatBlijftWerken(): void
    {
        $this->assertEquals('42', $this->eersteWaarde("SELECT format('%d', 42)"));
        $this->assertEquals('3.1', $this->eersteWaarde("SELECT printf('%.1f', 3.14159)"));
    }

    /** Een onbekend interval blijft staan; "no such function: dateadd" is duidelijk genoeg. */
    public function testOnbekendIntervalBlijftStaan(): void
    {
        $conn = $this->sqlite();
        $this->assertStringContainsString('DateAdd', SQL::processSQL($conn, "SELECT DateAdd('zz', 1, d) FROM t"));
    }

    public function testAccessKrijgtZijnDatumfunctiesOngewijzigd(): void
    {
        $sql = "SELECT Format([Datum],'dd mmm yyyy'), DateAdd('d',-30,Now()) FROM t";
        $this->assertStringContainsString('Format(', SQL::processSQL($this->odbc(), $sql));
        $this->assertStringContainsString('DateAdd(', SQL::processSQL($this->odbc(), $sql));
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
