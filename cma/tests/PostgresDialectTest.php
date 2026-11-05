<?php
/**
 * PostgresDialectTest — de platform-SQL (Access-dialect) wordt voor PostgreSQL vertaald.
 *
 * Run with: php cma/tests/TestRunner.php PostgresDialectTest
 *
 * Anders dan SqliteDialectTest draait deze test geen echte database: er is hier geen
 * pdo_pgsql en geen server. Wat bewezen wordt is dus de VERTALING (de tekst die naar
 * de driver gaat), niet dat PostgreSQL die tekst accepteert. Een nieuwe vertaalregel
 * hoort daarom ook eenmalig tegen een echte server geprobeerd te worden.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\SQL;

class PostgresDialectTest extends TestCase
{
    private function pg(string $sql): string
    {
        $conn = StubConnection::create();
        $conn->setDriverName('pgsql');
        return SQL::processSQL($conn, $sql);
    }

    private function ddl(string $sql): string
    {
        $conn = StubConnection::create();
        $conn->setDriverName('pgsql');
        return SQL::processDdl($conn, $sql);
    }

    // ---- Het dialect herkennen ------------------------------------------------

    public function testDialectHerkentPostgresAanDriverEnDsn(): void
    {
        $conn = StubConnection::create();
        $conn->setDriverName('pgsql');
        $this->assertEquals('postgres', SQL::dialect($conn));
        $this->assertEquals('postgres', SQL::dialect('pgsql:host=localhost;dbname=cma'));
        $this->assertEquals('postgres', SQL::dialect('postgresql://localhost/cma'));
    }

    // ---- Identifiers ----------------------------------------------------------

    public function testHaakjesWordenGequoteEnKleineLetters(): void
    {
        $this->assertEquals(
            'SELECT "naam", "tblklant"."id" FROM "tblklant" WHERE "user" = 1',
            $this->pg('SELECT [Naam], [tblKlant].[ID] FROM [tblKlant] WHERE [User] = 1')
        );
    }

    public function testHaakjesInEenLiteralBlijvenStaan(): void
    {
        $this->assertEquals("SELECT 1 FROM t WHERE x = '[Naam]'", $this->pg("SELECT 1 FROM t WHERE x = '[Naam]'"));
    }

    public function testDubbeleAanhalingstekensZijnTekstEnGeenIdentifier(): void
    {
        $this->assertEquals("SELECT 1 FROM t WHERE soort = 'KBA'", $this->pg('SELECT 1 FROM t WHERE soort = "KBA"'));
    }

    // ---- Basis ----------------------------------------------------------------

    public function testTopWordtLimitAanHetEindeVanHetStatement(): void
    {
        $this->assertEquals("SELECT x FROM t WHERE soort = 'fout' LIMIT 5", $this->pg("SELECT TOP 5 x FROM t WHERE soort = 'fout'"));
        $this->assertEquals('SELECT DISTINCT x FROM t LIMIT 3', $this->pg('SELECT DISTINCT TOP 3 x FROM t'));
    }

    public function testDeleteSterEnBooleans(): void
    {
        $this->assertEquals('DELETE FROM t WHERE actief = TRUE', $this->pg('DELETE * FROM t WHERE actief = True'));
        $this->assertEquals('SELECT 1 FROM t WHERE a = TRUE AND b = FALSE AND c <> TRUE', $this->pg('SELECT 1 FROM t WHERE a = -1 AND b = False AND c <> -1'));
    }

    public function testVergelijkingMetMinEenAlsGetalBlijftEenGetal(): void
    {
        $this->assertEquals('SELECT 1 FROM t WHERE x >= -1', $this->pg('SELECT 1 FROM t WHERE x >= -1'));
    }

    public function testLikeIsHoofdletterongevoeligZoalsAccess(): void
    {
        $this->assertEquals("SELECT 1 FROM t WHERE a ILIKE '%x%' AND b NOT ILIKE 'y'", $this->pg("SELECT 1 FROM t WHERE a LIKE '%x%' AND b NOT LIKE 'y'"));
        $this->assertEquals("SELECT 1 FROM t WHERE a ILIKE 'x'", $this->pg("SELECT 1 FROM t WHERE a ILIKE 'x'"));
    }

    public function testStringsBlijvenOnaangeroerd(): void
    {
        $sql = "SELECT 1 FROM t WHERE n = 'Jansen & Zn LIKE True now() [x]'";
        $this->assertEquals($sql, $this->pg($sql));
    }

    public function testConcatenatieEnPostStringEscaping(): void
    {
        $this->assertEquals("SELECT a || ' ' || b FROM t", $this->pg("SELECT a & ' ' & b FROM t"));
        // SQL::postString(): O'Brien -> 'O' & chr(39) & 'Brien'
        $this->assertEquals("INSERT INTO t (n) VALUES ('O' || chr(39) || 'Brien')", $this->pg("INSERT INTO t (n) VALUES (" . SQL::postString("O'Brien") . ")"));
    }

    public function testHuidigeDatumEnTijd(): void
    {
        $this->assertEquals('SELECT CURRENT_DATE, LOCALTIMESTAMP, LOCALTIMESTAMP', $this->pg('SELECT Date(), Now(), GetDate()'));
    }

    public function testDatumliteralsWordenIsoStrings(): void
    {
        $this->assertEquals("SELECT 1 FROM t WHERE d >= '2026-08-13'", $this->pg('SELECT 1 FROM t WHERE d >= #2026-08-13#'));
        $this->assertEquals("SELECT 1 FROM t WHERE d >= '2026-08-13 09:30:00'", $this->pg('SELECT 1 FROM t WHERE d >= #2026-08-13 09:30#'));
    }

    public function testLaatsteIdentity(): void
    {
        $this->assertEquals('SELECT lastval() AS NewID', $this->pg('SELECT @@IDENTITY AS NewID'));
    }

    // ---- Functies -------------------------------------------------------------

    public function testStringEnNumeriekeFuncties(): void
    {
        $this->assertEquals('SELECT upper(a), lower(b), length(c), substr(d, 2, 3) FROM t', $this->pg('SELECT ucase(a), lcase(b), len(c), mid(d, 2, 3) FROM t'));
        $this->assertEquals('SELECT a % 2 FROM t', $this->pg('SELECT a MOD 2 FROM t'));
        $this->assertEquals('SELECT SUM(a) FROM t', $this->pg('SELECT DSum(a) FROM t'));
    }

    public function testIifWordtCaseOokGenest(): void
    {
        $this->assertEquals(
            "SELECT (CASE WHEN a = 1 THEN 'x' ELSE (CASE WHEN a = 2 THEN 'y' ELSE 'z' END) END) FROM t",
            $this->pg("SELECT IIf(a = 1, 'x', IIf(a = 2, 'y', 'z')) FROM t")
        );
    }

    public function testIifKomtMetKommaInEenLiteralUit(): void
    {
        $this->assertEquals("SELECT (CASE WHEN a THEN 'x, y' ELSE 'z' END) FROM t", $this->pg("SELECT IIf(a, 'x, y', 'z') FROM t"));
    }

    public function testNzIsNullEnInt(): void
    {
        $this->assertEquals('SELECT COALESCE(a, 0), (b IS NULL), FLOOR(c) FROM t', $this->pg('SELECT Nz(a, 0), IsNull(b), Int(c) FROM t'));
    }

    public function testNzMetEenArgumentBlijftStaanZodatHetHardFaalt(): void
    {
        $this->assertEquals('SELECT Nz(a) FROM t', $this->pg('SELECT Nz(a) FROM t'));
    }

    public function testInStr(): void
    {
        $this->assertEquals("SELECT strpos(a, 'x') FROM t", $this->pg("SELECT InStr(a, 'x') FROM t"));
        $this->assertStringContainsString("strpos(substr(a, 3), 'x')", $this->pg("SELECT InStr(3, a, 'x') FROM t"));
    }

    public function testDatumdelen(): void
    {
        $this->assertEquals('SELECT CAST(EXTRACT(YEAR FROM CAST(d AS timestamp)) AS integer) FROM t', $this->pg('SELECT Year(d) FROM t'));
        $this->assertEquals('SELECT (CAST(EXTRACT(DOW FROM CAST(d AS timestamp)) AS integer) + 1) FROM t', $this->pg('SELECT Weekday(d) FROM t'));
    }

    public function testConversiefuncties(): void
    {
        $this->assertEquals('SELECT CAST(a AS integer), CAST(b AS text) FROM t', $this->pg('SELECT CLng(a), CStr(b) FROM t'));
    }

    // ---- DateAdd / DateDiff / Format ------------------------------------------

    public function testDateAdd(): void
    {
        $this->assertEquals(
            "SELECT 1 FROM t WHERE d >= (CAST(LOCALTIMESTAMP AS timestamp) + (-30) * INTERVAL '1 day')",
            $this->pg("SELECT 1 FROM t WHERE d >= DateAdd('d', -30, Now())")
        );
        $this->assertStringContainsString("((2) * 3) * INTERVAL '1 month'", str_replace('(CAST(x AS timestamp) + ', '', $this->pg("SELECT DateAdd('q', 2, x)")));
        // m = maanden, n = minuten.
        $this->assertStringContainsString("INTERVAL '1 month'", $this->pg("SELECT DateAdd('m', 1, x)"));
        $this->assertStringContainsString("INTERVAL '1 minute'", $this->pg("SELECT DateAdd('n', 1, x)"));
    }

    public function testOnbekendIntervalBlijftStaan(): void
    {
        $this->assertEquals("SELECT DateAdd('zz', 1, x)", $this->pg("SELECT DateAdd('zz', 1, x)"));
    }

    public function testDateDiffTeltGrenzenEnGeenVerstrekenTijd(): void
    {
        $this->assertEquals('SELECT (CAST(b AS date) - CAST(a AS date))', $this->pg("SELECT DateDiff('d', a, b)"));
        $uur = $this->pg("SELECT DateDiff('h', a, b)");
        $this->assertStringContainsString("date_trunc('hour', CAST(a AS timestamp))", $uur);
        $this->assertStringContainsString('/ 3600 AS integer', $uur);
        $maand = $this->pg("SELECT DateDiff('m', a, b)");
        $this->assertStringContainsString('* 12', $maand);
    }

    public function testFormatDatum(): void
    {
        $this->assertEquals(
            "SELECT to_char(CAST(d AS timestamp), 'DD\"-\"MM\"-\"YYYY') FROM t",
            $this->pg("SELECT Format(d, 'dd-mm-yyyy') FROM t")
        );
    }

    public function testFormatMinuutVersusMaand(): void
    {
        $f = $this->pg("SELECT Format(d, 'dd-mm-yyyy HH:mm')");
        $this->assertStringContainsString('HH24"', $f . '"');
        $this->assertStringContainsString('"-"MM"-"', $f);
        $this->assertStringContainsString('HH24":"MI', $f);
    }

    public function testFormatMaandnaamIsNederlands(): void
    {
        $f = $this->pg("SELECT Format(d, 'dd mmm yyyy')");
        $this->assertStringContainsString("WHEN 3 THEN 'mrt'", $f);
        $this->assertStringContainsString('EXTRACT(MONTH FROM CAST(d AS timestamp))', $f);
    }

    public function testFormatGetallen(): void
    {
        $this->assertEquals("SELECT to_char(x, 'FM999999999990.00')", $this->pg("SELECT Format(x, '0.00')"));
        $this->assertEquals('SELECT CAST(ROUND(x) AS integer)', $this->pg("SELECT Format(x, '0')"));
    }

    public function testOnvertaalbaarFormaatStoptDeQuery(): void
    {
        $this->assertEquals("SELECT access_format_niet_vertaald(d, 'AM/PM')", $this->pg("SELECT Format(d, 'AM/PM')"));
    }

    public function testEigenFormatVanPostgresBlijftWerken(): void
    {
        $sql = "SELECT format('%s-%s', a, b)";
        $this->assertEquals($sql, $this->pg($sql));
    }

    // ---- Schema (DDL) ---------------------------------------------------------

    public function testCreateTable(): void
    {
        $ddl = $this->ddl("CREATE TABLE [tblMonitor] (
            ID AUTOINCREMENT PRIMARY KEY,
            datestamp DATETIME,
            Naam VARCHAR(78),
            Actief YESNO DEFAULT -1,
            Blokkade YESNO DEFAULT 0,
            Bedrag CURRENCY,
            Gewicht DOUBLE,
            RecordID LONG,
            Guid GUID,
            Notitie MEMO
        )");
        $this->assertStringContainsString('"tblmonitor"', $ddl);
        $this->assertStringContainsString('ID INTEGER GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY', $ddl);
        $this->assertStringContainsString('datestamp TIMESTAMP', $ddl);
        $this->assertStringContainsString('Actief BOOLEAN DEFAULT TRUE', $ddl);
        $this->assertStringContainsString('Blokkade BOOLEAN DEFAULT FALSE', $ddl);
        $this->assertStringContainsString('Bedrag NUMERIC(19,4)', $ddl);
        $this->assertStringContainsString('Gewicht DOUBLE PRECISION', $ddl);
        $this->assertStringContainsString('RecordID INTEGER', $ddl);
        $this->assertStringContainsString('Guid UUID', $ddl);
        $this->assertStringContainsString('Notitie TEXT', $ddl);
    }

    public function testKolomDieMemoHeetBehoudtZijnNaam(): void
    {
        $this->assertStringContainsString('memo TEXT', $this->ddl('CREATE TABLE t (memo MEMO)'));
    }

    public function testAlterTable(): void
    {
        $this->assertEquals('ALTER TABLE "t" ADD COLUMN "x" TEXT', $this->ddl('ALTER TABLE [t] ADD COLUMN [x] MEMO'));
        $this->assertEquals('ALTER TABLE "t" ALTER COLUMN "x" TYPE VARCHAR(30)', $this->ddl('ALTER TABLE [t] ALTER COLUMN [x] VARCHAR(30) WITH COMPRESSION'));
        $this->assertEquals('ALTER TABLE t ALTER COLUMN x SET DEFAULT 1', $this->ddl('ALTER TABLE t ALTER COLUMN x SET DEFAULT 1'));
    }

    public function testDropIndexZonderTabel(): void
    {
        $this->assertEquals('DROP INDEX "ix_a"', $this->ddl('DROP INDEX [ix_a] ON [tblA]'));
    }

    public function testAccessDdlBlijftOngewijzigd(): void
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        $sql = 'CREATE TABLE t (ID AUTOINCREMENT PRIMARY KEY, n MEMO)';
        $this->assertEquals($sql, SQL::processDdl($conn, $sql));
    }
}
