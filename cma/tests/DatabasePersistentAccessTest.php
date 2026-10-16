<?php
/**
 * Database::getConnection() opent de gedeelde verbinding NIET persistent voor MS Access.
 *
 * De Access-driver (Jet/ACE) houdt per verbinding een leescache vast. Een persistente
 * PDO leeft door in het php-cgi-proces, dus een schrijfactie uit proces A bleef voor
 * proces B lang onzichtbaar (lokaal >30 s; een geüpload IOP verscheen pas na opnieuw
 * inloggen). Instelling db_persistent (DB_PERSISTENT): auto (standaard) = uit voor
 * Access, aan voor de rest; on = altijd; off = nooit. Binnen één request blijft de pool
 * per naam één handle geven.
 *
 * De verbindingstests draaien op pdo_sqlite (Windows-PHP). De Access-proef draait alleen
 * met pdo_odbc en CMA_TEST_ACCESS_MDB=<pad naar een .mdb> (alleen lezen).
 *
 * Run met: php cma/tests/TestRunner.php DatabasePersistentAccessTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Database;
use App\Library\Settings;

class DatabasePersistentAccessTest extends TestCase
{
    private const ACCESS = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=C:\\data\\db\\pdodomain.mdb';
    private const VELDEN = ['namedConnections', 'namedConnectionDsns', 'openedDsns', 'connData', 'connRep'];

    private array $bewaard = [];
    private string $bestand = '';

    public function setUp(): void
    {
        foreach (self::VELDEN as $veld) {
            $p = new \ReflectionProperty(Database::class, $veld);
            $p->setAccessible(true);
            $this->bewaard[$veld] = $p->getValue();
            $p->setValue(null, is_array($this->bewaard[$veld]) ? [] : null);
        }
        $this->zetModus(null);
    }

    public function tearDown(): void
    {
        foreach (self::VELDEN as $veld) {
            $p = new \ReflectionProperty(Database::class, $veld);
            $p->setAccessible(true);
            $p->setValue(null, $this->bewaard[$veld]);
        }
        $this->zetModus(null);
        if ($this->bestand !== '' && is_file($this->bestand)) {
            @unlink($this->bestand);
        }
    }

    private function zetModus(?string $modus): void
    {
        if ($modus === null) {
            unset($_ENV['DB_PERSISTENT']);
            putenv('DB_PERSISTENT');
        } else {
            $_ENV['DB_PERSISTENT'] = $modus;
        }
        Settings::reset();
    }

    public function testInstellingBestaatMetAutoAlsStandaard(): void
    {
        $this->assertSame('auto', Settings::get('db_persistent'));
        $def = Settings::definitions()['db_persistent'];
        $this->assertSame(['auto', 'on', 'off'], array_keys($def['options']));
        $this->zetModus('onzin');
        $this->assertSame('auto', Settings::get('db_persistent'), 'onbekende waarde valt terug op auto');
    }

    public function testHerkentAccessDsns(): void
    {
        $this->assertTrue(Database::isAccessDsn(self::ACCESS));
        $this->assertTrue(Database::isAccessDsn('odbc:Driver={Microsoft Access Driver (*.mdb)};Dbq=c:/x/y.mdb'));
        $this->assertTrue(Database::isAccessDsn('ODBC:Dbq=D:\\sites\\data.ACCDB;'));
        $this->assertTrue(Database::isAccessDsn('odbc:Provider=Microsoft.ACE.OLEDB.12.0;Data Source=x'));
        $this->assertFalse(Database::isAccessDsn('mysql:host=localhost;dbname=cma'));
        $this->assertFalse(Database::isAccessDsn('sqlsrv:Server=.;Database=cma'));
        $this->assertFalse(Database::isAccessDsn('sqlite:C:\\tmp\\x.mdb'), 'alleen ODBC is Access');
        $this->assertFalse(Database::isAccessDsn('odbc:Driver={ODBC Driver 18 for SQL Server};Server=.'));
        $this->assertFalse(Database::isAccessDsn('odbc:MijnSysteemDsn'), 'een benoemde DSN is niet te herkennen');
    }

    public function testKeuzePerDriverEnModus(): void
    {
        $mysql = 'mysql:host=localhost;dbname=cma';
        $sqlsrv = 'sqlsrv:Server=.;Database=cma';

        // auto: Access uit, de rest aan.
        $this->assertFalse(Database::usePersistentConnection(self::ACCESS), 'auto: Access niet persistent');
        $this->assertTrue(Database::usePersistentConnection($mysql), 'auto: mysql persistent');
        $this->assertTrue(Database::usePersistentConnection($sqlsrv), 'auto: sqlsrv persistent');

        $this->zetModus('on');
        $this->assertTrue(Database::usePersistentConnection(self::ACCESS), 'on: ook Access');
        $this->assertTrue(Database::usePersistentConnection($mysql));

        $this->zetModus('off');
        $this->assertFalse(Database::usePersistentConnection(self::ACCESS));
        $this->assertFalse(Database::usePersistentConnection($mysql), 'off: ook mysql niet');
    }

    private function sqlite(): void
    {
        $this->requireExtensions('pdo_sqlite');
        $this->bestand = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cma_persist_' . getmypid() . '.sqlite';
        @unlink($this->bestand);
        Database::initConnections(['data' => 'sqlite:' . $this->bestand]);
    }

    public function testPoolGeeftBinnenEenRequestEenHandleOokZonderPersistentie(): void
    {
        $this->zetModus('off');
        $this->sqlite();
        $a = Database::getConnection('data');
        $this->assertFalse((bool)$a->getAttribute(\PDO::ATTR_PERSISTENT), 'off: niet persistent');
        $this->assertSame($a, Database::getConnection('data'), 'dezelfde handle bij de tweede aanroep');
        $this->assertSame($a, Database::getConnection('main'), 'alias: dezelfde handle');
        $this->assertSame(\PDO::ERRMODE_EXCEPTION, $a->getAttribute(\PDO::ATTR_ERRMODE));
    }

    public function testAutoLaatAndereDriversPersistent(): void
    {
        $this->sqlite();
        $a = Database::getConnection('data');
        $this->assertTrue((bool)$a->getAttribute(\PDO::ATTR_PERSISTENT), 'auto: sqlite blijft persistent');
    }

    public function testEchteAccessVerbindingIsNietPersistent(): void
    {
        $this->requireExtensions('pdo_odbc');
        $mdb = (string)getenv('CMA_TEST_ACCESS_MDB');
        if ($mdb === '' || !is_file($mdb)) {
            $this->markTestSkipped('zet CMA_TEST_ACCESS_MDB op een .mdb om tegen Access te proeven');
        }
        Database::initConnections(['data' => 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=' . $mdb]);
        $a = Database::getConnection('data');
        $this->assertFalse((bool)$a->getAttribute(\PDO::ATTR_PERSISTENT), 'Access: niet persistent');
        $this->assertSame($a, Database::getConnection('data'), 'binnen het request dezelfde handle');
        $this->assertSame('odbc', $a->getAttribute(\PDO::ATTR_DRIVER_NAME));
    }
}
