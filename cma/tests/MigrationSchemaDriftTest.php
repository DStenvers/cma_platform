<?php
/**
 * A migration that is recorded as applied becomes pending again when a column it
 * added no longer exists (schema drift: e.g. the users database was restored from
 * a backup taken before the migration). missingColumns() only judges tables that
 * exist and only addColumn changes.
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/MigrationService.php';

use Cma\Services\MigrationService;

class MigrationSchemaDriftTest extends TestCase
{
    private function missing(array $migration): array
    {
        $svc = new MigrationService();
        $m = new \ReflectionMethod($svc, 'missingColumns');
        $m->setAccessible(true);
        return $m->invoke($svc, $migration);
    }

    public function testMigratieZonderKolommenGeeftNooitDrift(): void
    {
        $this->assertEquals([], $this->missing(['version' => '1.0.0', 'changes' => [
            ['type' => 'runSql', 'database' => 'data', 'sql' => 'SELECT 1'],
        ]]));
    }

    public function testOnbereikbareDatabaseIsGeenDrift(): void
    {
        $this->assertEquals([], $this->missing(['version' => '1.0.0', 'changes' => [
            ['type' => 'addColumn', 'database' => 'bestaat_niet_' . getmypid(), 'table' => 't', 'column' => 'c', 'dataType' => 'INT'],
        ]]));
    }

    public function testOntbrekendeTabelIsGeenDrift(): void
    {
        $this->assertEquals([], $this->missing(['version' => '1.0.0', 'changes' => [
            ['type' => 'addColumn', 'database' => 'data', 'table' => 'tbl_bestaat_niet_' . getmypid(), 'column' => 'c', 'dataType' => 'INT'],
        ]]));
    }

    /** Put an in-memory SQLite database in the pool under a private name. */
    private function sqliteAs(string $name): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE tblUsers (ID INTEGER PRIMARY KEY, prefTheme TEXT)');
        $prop = new \ReflectionProperty(\App\Library\Database::class, 'namedConnections');
        $prop->setAccessible(true);
        $pool = $prop->getValue();
        $pool[$name] = $pdo;
        $prop->setValue(null, $pool);
        return $pdo;
    }

    public function testAlleenDeOntbrekendeKolomTeltAlsDrift(): void
    {
        $db = 'driftdb' . getmypid();
        $this->sqliteAs($db);
        $res = $this->missing(['version' => '6.5.0', 'changes' => [
            ['type' => 'addColumn', 'database' => $db, 'table' => 'tblUsers', 'column' => 'prefTheme', 'dataType' => 'VARCHAR(10)'],
            ['type' => 'addColumn', 'database' => $db, 'table' => 'tblUsers', 'column' => 'prefMenuStyle', 'dataType' => 'VARCHAR(10)'],
        ]]);
        $this->assertEquals(["$db.tblUsers.prefMenuStyle"], $res);
    }
}
