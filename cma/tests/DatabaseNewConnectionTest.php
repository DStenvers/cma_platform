<?php
/**
 * Database::newConnection() geeft een eigen, NIET-persistente verbinding voor een
 * transactie.
 *
 * getConnection() deelt per naam één persistente PDO. De Access-ODBC-driver weigert
 * beginTransaction() op die handle zolang er nog een recordset op openstaat:
 * "SQLSTATE[HY011] Attribute cannot be set now" (AUTOCOMMIT = OFF). Een eigen
 * verbinding heeft geen andere open statements. Deze test pint vast dat
 * newConnection() dezelfde DSN gebruikt als de logische naam, niet persistent is,
 * de pool niet raakt, en elke aanroep een nieuwe handle geeft. De standaard
 * (getConnection) blijft persistent.
 *
 * De verbindingstests draaien op pdo_sqlite (aanwezig in de Windows-PHP, niet in de
 * WSL-PHP: daar worden ze overgeslagen).
 *
 * Run met: php cma/tests/TestRunner.php DatabaseNewConnectionTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Database;

class DatabaseNewConnectionTest extends TestCase
{
    private string $bestand = '';
    private array $bewaard = [];

    private const VELDEN = ['namedConnections', 'namedConnectionDsns', 'openedDsns', 'connData', 'connRep'];

    public function setUp(): void
    {
        foreach (self::VELDEN as $veld) {
            $p = new \ReflectionProperty(Database::class, $veld);
            $p->setAccessible(true);
            $this->bewaard[$veld] = $p->getValue();
            $p->setValue(null, is_array($this->bewaard[$veld]) ? [] : null);
        }
    }

    public function tearDown(): void
    {
        foreach (self::VELDEN as $veld) {
            $p = new \ReflectionProperty(Database::class, $veld);
            $p->setAccessible(true);
            $p->setValue(null, $this->bewaard[$veld]);
        }
        if ($this->bestand !== '' && is_file($this->bestand)) {
            @unlink($this->bestand);
        }
    }

    private function sqlite(): string
    {
        $this->requireExtensions('pdo_sqlite');
        $this->bestand = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cma_newconn_' . getmypid() . '.sqlite';
        @unlink($this->bestand);
        $dsn = 'sqlite:' . $this->bestand;
        Database::initConnections(['data' => $dsn]);
        return $dsn;
    }

    public function testOnbekendeNaamGooitEenDuidelijkeFout(): void
    {
        try {
            Database::newConnection('users');
            throw new \RuntimeException('newConnection op een niet-geconfigureerde naam moet gooien');
        } catch (\PDOException $e) {
            $this->assertStringContainsString("'users' not configured", $e->getMessage());
        }
    }

    public function testGebruiktDeDsnVanDeLogischeNaamEnRepValtTerugOpData(): void
    {
        // Een DSN die niet te openen is: de fout noemt welke DSN er geprobeerd werd.
        Database::initConnections(['data' => 'nietbestaand:x=1']);
        try {
            Database::newConnection('rep');
            throw new \RuntimeException('verbinden met een onbekende driver moet gooien');
        } catch (\PDOException $e) {
            $this->assertStringContainsString("Database connection 'rep' failed", $e->getMessage());
            $this->assertStringContainsString('DSN: nietbestaand:x=1', $e->getMessage());
        }
        $pool = new \ReflectionProperty(Database::class, 'namedConnections');
        $pool->setAccessible(true);
        $this->assertSame([], $pool->getValue(), 'newConnection zet niets in de pool');
    }

    public function testNieuweVerbindingIsEigenEnNietPersistent(): void
    {
        $this->sqlite();
        $gedeeld = Database::getConnection('data');
        $a = Database::newConnection('data');
        $b = Database::newConnection();

        $this->assertTrue($a instanceof \PDO);
        $this->assertFalse($a === $gedeeld, 'niet de gedeelde handle');
        $this->assertFalse($a === $b, 'elke aanroep een nieuwe handle');
        $this->assertFalse((bool)$a->getAttribute(\PDO::ATTR_PERSISTENT), 'niet persistent');
        $this->assertTrue((bool)$gedeeld->getAttribute(\PDO::ATTR_PERSISTENT), 'de standaard blijft persistent');
        $this->assertSame($gedeeld, Database::getConnection('data'), 'de pool is ongemoeid');

        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $a->getAttribute(\PDO::ATTR_ERRMODE));
        $this->assertSame(\PDO::FETCH_ASSOC, $a->getAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE));
    }

    public function testTransactieOpEigenVerbindingIsZichtbaarVoorDeGedeelde(): void
    {
        $this->sqlite();
        $gedeeld = Database::getConnection('data');
        $gedeeld->exec('CREATE TABLE t (id INTEGER)');

        $tx = Database::newConnection('data');
        $this->assertTrue($tx->beginTransaction());
        $tx->exec('INSERT INTO t (id) VALUES (7)');
        $this->assertTrue($tx->commit());

        $this->assertSame('7', (string)$gedeeld->query('SELECT id FROM t')->fetchColumn());
    }
}
