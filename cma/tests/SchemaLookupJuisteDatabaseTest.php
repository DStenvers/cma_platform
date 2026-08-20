<?php
/**
 * Een schema-uitvraag moet over DEZELFDE database gaan als de query die erop volgt.
 *
 * Wat er misging (gemeten op test-mijn.rino.nl, 17x op één dag):
 *
 *   SchemaHelper::getNativeOdbc() las de verbindingsnaam met `is_string($connection)`.
 *   Aanroepers geven vaak een PDO-object door — cma/login.php deed dat met de
 *   `users`-verbinding — en dan was die naam null, waarna de methode terugviel op
 *   de DSN van 'data'. odbc_columns() las dus de kolommen van tblUsers in
 *   pdodomain.mdb, terwijl de query er even later via
 *   Database::getFieldValue('users', ...) op CMAusers.mdb werd losgelaten.
 *
 *   In login.php koos hasColumn('userLevel') daarmee over de VERKEERDE tabel. Op een
 *   server waar de data-kopie een oude tblUsers zonder userLevel had, viel de code in
 *   de legacy-tak en vroeg naar userAdministrator — een kolom die in CMAusers.mdb
 *   allang door userLevel was vervangen. Resultaat: een stacktrace per inlogpoging.
 *
 *   De bestaande PDO-terugval in getColumns() ving dit NIET af: die springt alleen bij
 *   een lege kolomlijst bij, en de verkeerde database leverde keurig kolommen.
 *
 * Run met: php cma/tests/TestRunner.php SchemaLookupJuisteDatabaseTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/../classes/SchemaHelper.php';

use App\Library\Database;
use Cma\SchemaHelper;

class SchemaLookupJuisteDatabaseTest extends TestCase
{
    // Windows-paden met backslashes: op Windows normaliseert Database elke ODBC-DSN
    // daarnaartoe (de Access-driver eist het), dus een fixture met slashes komt daar
    // anders terug dan hij erin ging en de test zou alleen op Linux slagen.
    private const DSN_DATA  = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=C:\site\db\pdodomain.mdb';
    private const DSN_USERS = 'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=C:\site\db\CMAusers.mdb';

    /** @var \PDO */
    private $usersPdo;

    public function setUp(): void
    {
        Database::initConnections([
            'data'  => self::DSN_DATA,
            'users' => self::DSN_USERS,
        ]);

        // Doe alsof 'users' al geopend is: zet een stub-PDO in de pool, zoals
        // getConnection('users') dat zou doen. Zo hoeft de test geen echte
        // Access-driver te hebben.
        $this->usersPdo = StubConnection::create();
        $this->setPool(['users' => $this->usersPdo]);
    }

    public function tearDown(): void
    {
        $this->setPool([]);
    }

    // ---- Database::nameForConnection() ----------------------------------

    public function testPdoUitDePoolWordtHerkendAlsZijnEigenVerbinding(): void
    {
        $this->assertSame('users', Database::nameForConnection($this->usersPdo));
    }

    public function testOnbekendePdoLevertNullEnDusGeenGok(): void
    {
        $this->assertNull(
            Database::nameForConnection(StubConnection::create()),
            'een verbinding buiten de pool mag geen naam krijgen — anders wordt er gegokt'
        );
    }

    public function testNaamStringWordtGenormaliseerdEnAliasOpgelost(): void
    {
        $this->assertSame('users', Database::nameForConnection('USERS'));
        $this->assertSame('users', Database::nameForConnection('cmausers'));
        $this->assertSame('data', Database::nameForConnection('main'));
        $this->assertNull(Database::nameForConnection(''));
    }

    // ---- Database::dsnForConnection() ------------------------------------

    /**
     * DE REGRESSIE: het PDO-object van `users` mag nooit de data-DSN opleveren.
     */
    public function testPdoVanUsersLevertDeUsersDsnEnNietDieVanData(): void
    {
        $dsn = Database::dsnForConnection($this->usersPdo);

        $this->assertSame(self::DSN_USERS, $dsn);
        $this->assertStringNotContainsString(
            'pdodomain.mdb',
            $dsn,
            'het schema werd uit de data-database gelezen terwijl de query op users draait'
        );
    }

    public function testNaamlozeVerbindingLevertGeenDsnZodatDePdoWegLoopt(): void
    {
        $this->assertSame(
            '',
            Database::dsnForConnection(StubConnection::create()),
            'zonder naam hoort er geen native handle te komen; de PDO-weg zit per definitie goed'
        );
    }

    public function testVerbindingsnaamBlijftGewoonWerken(): void
    {
        $this->assertSame(self::DSN_DATA, Database::dsnForConnection('data'));
        $this->assertSame(self::DSN_USERS, Database::dsnForConnection('users'));
        $this->assertSame(self::DSN_USERS, Database::dsnForConnection('cmausers'));
    }

    public function testOnbekendeNaamLevertGeenDsn(): void
    {
        $this->assertSame('', Database::dsnForConnection('bestaatniet'));
        $this->assertSame('', Database::dsnForConnection(''));
        $this->assertSame('', Database::dsnForConnection(null));
    }

    /**
     * De CMA-tools (dbsummary, query, formwiz) geven geen naam door maar een rauwe
     * connectiestring uit CmaRepository::getResolvedConnectionString(). Die viel
     * langs dezelfde weg terug op 'data': elke andere database dan de standaard
     * kreeg daar het schema van pdodomain.mdb te zien.
     */
    public function testRauweOleDbStringWijstNaarZijnEigenBestand(): void
    {
        $dsn = Database::dsnForConnection(
            'Provider=Microsoft.Jet.OLEDB.4.0;Locale Identifier=1043;Data Source=D:\\site\\db\\archief.mdb'
        );

        $this->assertSame(
            'odbc:Driver={Microsoft Access Driver (*.mdb, *.accdb)};Dbq=D:/site/db/archief.mdb',
            $dsn
        );
    }

    public function testBestaandePdoDsnGaatOngewijzigdDoor(): void
    {
        $this->assertSame(self::DSN_DATA, Database::dsnForConnection(self::DSN_DATA));
        $this->assertSame('sqlite:/site/db/x.sqlite', Database::dsnForConnection('sqlite:/site/db/x.sqlite'));
    }

    /**
     * Een kaal Windows-pad ziet er met /^\w+:/ uit als een DSN. Dat mag niet als
     * connectiestring doorgaan, want odbc_connect() maakt er dan iets onzinnigs van.
     */
    public function testKaalWindowsPadIsGeenDsn(): void
    {
        $this->assertSame('', Database::dsnForConnection('C:\\site\\db\\pdodomain.mdb'));
    }

    /**
     * getNativeOdbc() mag die lege uitkomst niet zelf weer opvullen met 'data'.
     * Broncontrole, want een echte handle vraagt een Access-driver.
     */
    public function testGetNativeOdbcHeeftGeenTerugvalOpData(): void
    {
        $bron = file_get_contents(__DIR__ . '/../classes/SchemaHelper.php');
        $begin = strpos($bron, 'private static function getNativeOdbc');
        $this->assertTrue($begin !== false, 'getNativeOdbc() is verdwenen');
        $body = substr($bron, $begin, 800);

        $this->assertStringContainsString('Database::dsnForConnection($connection)', $body);
        $this->assertStringNotContainsString(
            "getDsn('data')",
            $body,
            'de gok op de data-database is terug'
        );
    }

    // ---- cma/login.php: de adminbootstrap --------------------------------

    /**
     * Broncontrole, want de bootstrap zit midden in main() met een POST eromheen.
     * Het gedrag dat hier misging is puur besturingslogica: "geen userLevel" mocht
     * niet langer automatisch "dan legacy" betekenen.
     */
    public function testLoginVraagtNaarBeideAdminkolommenVoorHijEenKiest(): void
    {
        $bron = file_get_contents(__DIR__ . '/../login.php');

        $this->assertStringContainsString(
            "SchemaHelper::hasColumn('users', 'tblUsers', 'userAdministrator')",
            $bron,
            'de legacy-tak wordt weer gekozen zonder te vragen of userAdministrator bestaat'
        );
        $this->assertStringContainsString(
            "SchemaHelper::hasColumn('users', 'tblUsers', 'userLevel')",
            $bron,
            'de schema-uitvraag moet de verbindingsNAAM meekrijgen, niet een PDO-object'
        );
        $this->assertStringContainsString(
            'if ($heeftUserLevel || $heeftLegacyVlag) {',
            $bron,
            'zonder herkenbare adminkolom hoort de bootstrap over te slaan, niet te gokken'
        );
    }

    /**
     * De schema-uitvraag en de query moeten dezelfde database noemen: de INSERT en
     * de count gaan expliciet naar 'users', dus de kolomvraag ook.
     */
    public function testLoginGeeftGeenPdoObjectMeerAanSchemaHelper(): void
    {
        $bron = file_get_contents(__DIR__ . '/../login.php');

        $this->assertStringNotContainsString(
            'SchemaHelper::hasColumn($usersConn',
            $bron,
            'met een PDO-object kan SchemaHelper niet zien om welke database het gaat'
        );
    }

    // ---- helpers ---------------------------------------------------------

    /** Pool van open verbindingen in Database vervangen (private static). */
    private function setPool(array $connections): void
    {
        $prop = new \ReflectionProperty(Database::class, 'namedConnections');
        $prop->setAccessible(true);
        $prop->setValue(null, $connections);
    }
}
