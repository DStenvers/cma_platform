<?php
/**
 * Tests for App\Library\Database::dsnFromConfigEntry — building a PDO DSN from
 * a databases.json entry (the single source of truth for DB connections).
 *
 * Run with: php cma/tests/TestRunner.php DatabasesJsonDsnTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Database;

class DatabasesJsonDsnTest extends TestCase
{
    private string $root = '/var/www/site';

    public function testAccessOleDbBracketPathToOdbc(): void
    {
        $dsn = Database::dsnFromConfigEntry([
            'name' => 'users',
            'type' => 'access',
            'connectionString' => 'Provider=Microsoft.Jet.OLEDB.4.0;Data Source=[db/CMAUsers.mdb]',
        ], $this->root);
        $this->assertSame(
            'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=/var/www/site/db/CMAUsers.mdb',
            $dsn
        );
    }

    public function testBarePathPlaceholderIsSiteRoot(): void
    {
        $dsn = Database::dsnFromConfigEntry([
            'connectionString' => 'Provider=Microsoft.Jet.OLEDB.4.0;Data Source=[path]db/x.mdb',
        ], $this->root);
        $this->assertSame(
            'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=/var/www/site/db/x.mdb',
            $dsn
        );
    }

    public function testSqlitePdoDsnBracketResolved(): void
    {
        $dsn = Database::dsnFromConfigEntry([
            'name' => 'users',
            'type' => 'sqlite',
            'connectionString' => 'sqlite:[db/cmausers.sqlite]',
        ], $this->root);
        $this->assertSame('sqlite:/var/www/site/db/cmausers.sqlite', $dsn);
    }

    public function testAlreadyOdbcDsnPassThrough(): void
    {
        $in = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=/abs/x.mdb';
        $this->assertSame($in, Database::dsnFromConfigEntry(['connectionString' => $in], $this->root));
    }

    public function testEnvSecretPlaceholder(): void
    {
        $_ENV['TEST_DB_DSN_XYZ'] = 'mysql:host=db;dbname=app';
        $dsn = Database::dsnFromConfigEntry([
            'connectionString' => '[env:TEST_DB_DSN_XYZ]',
        ], $this->root);
        unset($_ENV['TEST_DB_DSN_XYZ']);
        $this->assertSame('mysql:host=db;dbname=app', $dsn);
    }

    public function testEmptyConnectionStringReturnsEmpty(): void
    {
        $this->assertSame('', Database::dsnFromConfigEntry(['name' => 'users'], $this->root));
    }

    // ------------------------------------------------------------------
    // Database::isSQLServer — connection-string dialect detection.
    // Pure when passed an explicit string (null would read site config).
    // ------------------------------------------------------------------

    public function testIsSqlServerDetectsInitialCatalog(): void
    {
        $this->assertTrue(Database::isSQLServer('Initial Catalog=mydb;Data Source=srv'));
    }

    public function testIsSqlServerDetectsNativeClientAndOleDb(): void
    {
        $this->assertTrue(Database::isSQLServer('Provider=SQLOLEDB;Server=x'));
        $this->assertTrue(Database::isSQLServer('Provider=SQLNCLI11;Server=x'));
        $this->assertTrue(Database::isSQLServer('Provider=MSOLEDBSQL;Server=x'));
    }

    public function testIsSqlServerFalseForAccessDriver(): void
    {
        $this->assertFalse(
            Database::isSQLServer('Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=/x.mdb')
        );
    }

    public function testIsSqlServerFalseForSqliteDsn(): void
    {
        $this->assertFalse(Database::isSQLServer('sqlite:/var/db/app.sqlite'));
    }

    // ------------------------------------------------------------------
    // Database::isODBC — true only when the string *starts* with DSN=
    // ------------------------------------------------------------------

    public function testIsOdbcTrueOnlyWhenDsnPrefix(): void
    {
        $this->assertTrue(Database::isODBC('DSN=mydb;UID=sa'));
        // "DSN=" not at position 0 must NOT count as ODBC.
        $this->assertFalse(Database::isODBC('Server=x;DSN=mydb'));
        $this->assertFalse(Database::isODBC('sqlite:/var/db/app.sqlite'));
    }

    // ------------------------------------------------------------------
    // Database::probeDsn — the pre-save connection check for databases.json
    // ------------------------------------------------------------------

    public function testProbeReportsAnEmptyConnectionString(): void
    {
        $this->assertSame('geen connectionString', Database::probeDsn('  '));
    }

    public function testProbeNamesTheMissingFileInsteadOfTheDriverError(): void
    {
        $missing = sys_get_temp_dir() . '/definitely-not-here-' . bin2hex(random_bytes(4)) . '.mdb';
        $reason = Database::probeDsn('odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $missing);
        $this->assertStringContainsString('bestand bestaat niet', (string)$reason);
        $this->assertStringContainsString(basename($missing), (string)$reason);
    }

    public function testProbeAcceptsAnOpenableConnection(): void
    {
        if (!in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            return; // no sqlite driver here; nothing to assert
        }
        $this->assertNull(Database::probeDsn('sqlite::memory:'));
    }

    public function testProbeReportsADriverThatDoesNotExist(): void
    {
        $reason = Database::probeDsn('nosuchdriver:host=localhost');
        $this->assertNotNull($reason);
    }
}
