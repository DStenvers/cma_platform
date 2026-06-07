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
}
