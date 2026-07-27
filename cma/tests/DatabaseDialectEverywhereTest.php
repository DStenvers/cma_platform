<?php
/**
 * DatabaseDialectEverywhereTest — élke query-ingang vertaalt het dialect, niet alleen openRS().
 *
 * Run with: php cma/tests/TestRunner.php DatabaseDialectEverywhereTest
 *
 * Waarom deze test bestaat:
 * openRS() haalde zijn SQL altijd door SQL::processSQL(); getFieldValue(), getIds(),
 * query(), execute() en de safe*-wrappers prepareerden de string ongewijzigd. Op
 * Access/ODBC is dat geen detail: een Replication-ID (GUID) kolom matcht daar NOOIT met
 * `=`, dus processSQL() herschrijft `guid = '<guid>'` naar `guid LIKE '%<guid>%'`.
 * Zonder die herschrijving gaf dezelfde query via openRS() een rij en via
 * getFieldValue() NUL rijen — stil, zonder fout.
 *
 * In mijnRINO liep daar een compleet scherm op stuk: eval_resultaten.php zocht het
 * evaluatiedocument op met getFieldValue(... where guid=...) en kreeg niets, waardoor de
 * hele docent-evaluatie leeg bleef terwijl de gegevens er gewoon waren.
 *
 * Omgezette ASP-code wisselt deze helpers per regel af, dus de vertaling hoort overal te
 * gebeuren of nergens.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';

use App\Library\Database;

class DatabaseDialectEverywhereTest extends TestCase
{
    private const GUID = 'DFF2849F-4F23-4E0B-BB4D-D4C2DACCAB02';

    /** Een stub die zich als Access-via-ODBC voordoet. */
    private function odbc(): StubConnection
    {
        $conn = StubConnection::create();
        $conn->setDriverName('odbc');
        return $conn;
    }

    private function sqlMet(StubConnection $conn): string
    {
        $call = $conn->getLastCall();
        return $call === null ? '' : (string) $call['sql'];
    }

    public function testGetFieldValueHerschrijftGuidVergelijking(): void
    {
        $conn = $this->odbc();
        $conn->enqueueResult([['fkEvalDocument' => '2']]);
        $waarde = Database::getFieldValue($conn,
            "SELECT fkEvalDocument from tblEvalDeelname where guid='" . self::GUID . "'", 'fkEvalDocument');

        $this->assertStringContainsString("guid LIKE '%" . self::GUID . "%'", $this->sqlMet($conn),
            'getFieldValue() prepareerde de ruwe SQL; op Access matcht een GUID-kolom niet met =');
        $this->assertEquals('2', $waarde);
    }

    public function testGetIdsHerschrijftGuidVergelijking(): void
    {
        $conn = $this->odbc();
        $conn->enqueueResult([['ID' => '10975'], ['ID' => '10976']]);
        $ids = Database::getIds($conn, "SELECT ID from tblEvalDeelname where guid='" . self::GUID . "'");

        $this->assertStringContainsString('LIKE', $this->sqlMet($conn));
        $this->assertEquals('10975,10976', $ids);
    }

    public function testQueryEnExecuteHerschrijvenOok(): void
    {
        // Een UPDATE met een GUID in de WHERE raakt anders NUL rijen — even stil.
        $conn = $this->odbc();
        $conn->enqueueResult([]);
        Database::execute("UPDATE tblEvalDeelname set datum_ingevuld=date() where guid='" . self::GUID . "'");
        // execute() gebruikt de pool-verbinding, niet deze stub: hier alleen query() met
        // een expliciete verbinding controleren.
        $conn->reset();
        $conn->enqueueResult([['x' => 1]]);
        Database::query("SELECT x from tbl where guid='" . self::GUID . "'", [], $conn);
        $this->assertStringContainsString('LIKE', $this->sqlMet($conn),
            'query() met een expliciete verbinding vertaalt het dialect niet');
    }

    public function testSafeHelpersHerschrijvenOok(): void
    {
        $conn = $this->odbc();
        $conn->enqueueResult([['naam' => 'Corgé']]);
        Database::safeQuery("SELECT naam from tblDocenten where guid='" . self::GUID . "'", [], '', $conn);
        $this->assertStringContainsString('LIKE', $this->sqlMet($conn));
    }

    public function testAndereDriversBlijvenOngemoeid(): void
    {
        // SQLite/MySQL vergelijken een GUID-kolom gewoon met '='; LIKE zou daar een
        // volledige tabelscan opleveren.
        $conn = StubConnection::create();           // driver = sqlite
        $conn->enqueueResult([['ID' => '1']]);
        $sql = "SELECT ID from tblEvalDeelname where guid='" . self::GUID . "'";
        Database::getFieldValue($conn, $sql, 'ID');
        // processSQL() normaliseert buiten Access hooguit de witruimte; de vergelijking
        // zelf hoort een '=' te blijven.
        $this->assertStringNotContainsString('LIKE', $this->sqlMet($conn),
            'buiten Access hoort een GUID gewoon met = vergeleken te worden');
        $this->assertStringContainsString('guid = ', $this->sqlMet($conn));
    }
}
