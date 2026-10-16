<?php
/**
 * Een vinkjeskolom waarvan de lijstquery TEKST teruggeeft, toont die tekst als vinkje.
 *
 * Een listQuery kan een ja/nee-kolom als tekstexpressie opleveren
 * (IIf(..., 'Ja', 'Nee')). De lijstcellen beoordeelden "aan" elk met een eigen
 * lijstje (true, 1, -1, '1', '-1', 'true') waar 'Ja' en 'Yes' niet in stonden, zodat
 * een 'Ja' als "Nee" (schakelaar uit) in de lijst kwam. Alle lijstcellen
 * (JsonFormService, SubformService, TableService) gebruiken nu
 * ListServiceHelper::toBool, met één waarheidstabel.
 *
 * Daarnaast: form_api action=subforms&indices=0 gaf "indices parameter is verplicht",
 * omdat array_filter() de index 0 (het eerste subformulier) weggooide.
 * ListServiceHelper::parseIndexList houdt 0 vast.
 *
 * Run met: php cma/tests/TestRunner.php ListBooleanTekstwaardeTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/StubConnection.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/FormControlHelper.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/Services/FieldType.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/TableService.php';
require_once dirname(__DIR__) . '/classes/Services/JsonFormService.php';

use Cma\Services\JsonFormService;
use Cma\Services\ListServiceHelper;
use Cma\Services\TableService;

class ListBooleanTekstwaardeTest extends TestCase
{
    private StubConnection $conn;

    private const AAN = [true, 1, -1, '1', '-1', 'True', 'true', 'Ja', 'ja', ' Ja ', 'Yes', 'yes'];
    private const UIT = [false, 0, '0', null, '', 'False', 'false', 'Nee', 'nee', 'No', 'no'];

    public function setUp(): void
    {
        TestHarness::loginAsAdmin();
        TestHarness::silenceLogger();
        $this->conn = StubConnection::create();
        TestHarness::injectConnection('data', $this->conn);
        TestHarness::injectFormDef('bool_tekst', ['_json' => [
            'name'     => 'bool_tekst',
            'title'    => 'Booleans',
            'table'    => 'tblBool',
            'idField'  => 'ID',
            'database' => 'data',
            'fields'   => [
                ['name' => 'naam',   'type' => 'textbox',  'caption' => 'Naam'],
                ['name' => 'actief', 'type' => 'checkbox', 'caption' => 'Actief'],
            ],
        ]]);
    }

    public function tearDown(): void
    {
        TestHarness::reset();
        $_COOKIE = [];
    }

    public function testToBoolWaarheidstabel(): void
    {
        foreach (self::AAN as $w) {
            $this->assertTrue(ListServiceHelper::toBool($w), 'aan: ' . var_export($w, true));
        }
        foreach (self::UIT as $w) {
            $this->assertFalse(ListServiceHelper::toBool($w), 'uit: ' . var_export($w, true));
        }
    }

    public function testLijstToontTekstJaAlsAangevinkt(): void
    {
        $this->conn->enqueueResult([['cnt' => 2]]);
        $this->conn->enqueueResult([
            ['ID' => 1, 'naam' => 'A', 'actief' => 'Ja'],
            ['ID' => 2, 'naam' => 'B', 'actief' => 'Nee'],
        ]);
        $result = JsonFormService::getTableHtml('bool_tekst', null, ['columns' => ['naam', 'actief']]);
        $this->assertTrue($result['success'] ?? false, $result['error'] ?? '');
        $html = $result['html'];
        $this->assertMatchesRegularExpression(
            '~<tr[^>]*data-id="1".*?data-field="actief" data-type="boolean" data-value="1"><lib-switch data-field="actief" checked>~s',
            $html, 'Ja moet aangevinkt staan'
        );
        $this->assertMatchesRegularExpression(
            '~<tr[^>]*data-id="2".*?data-field="actief" data-type="boolean" data-value="0"><lib-switch data-field="actief">~s',
            $html, 'Nee moet uit staan'
        );
    }

    public function testRijToontElkeWaarheidswaardeGoed(): void
    {
        foreach ([[self::AAN, '1'], [self::UIT, '0']] as [$waarden, $verwacht]) {
            foreach ($waarden as $w) {
                $this->conn->enqueueResult([['ID' => 4, 'actief' => $w]]);
                $html = JsonFormService::getRowHtml('bool_tekst', '4', 2, ['actief'])['html'] ?? '';
                $this->assertStringContainsString('data-value="' . $verwacht . '"', $html, var_export($w, true));
            }
        }
    }

    public function testTabelweergaveToontTekstJaAlsJa(): void
    {
        $m = new ReflectionMethod(TableService::class, 'renderTableCell');
        $m->setAccessible(true);
        $cel = fn($w, int $rechten) => $m->invoke(null, 'actief', $w, \Cma\FormControlHelper::TYPE_CHECKBOX, [], [], $rechten);
        $this->assertStringContainsString('data-value="1"', $cel('Ja', \Cma\SecurityHelper::ACCESS_FULL));
        $this->assertStringContainsString('data-value="0"', $cel('Nee', \Cma\SecurityHelper::ACCESS_FULL));
        $this->assertStringContainsString('>Ja</td>', $cel('Yes', 0));
        $this->assertStringContainsString('>Nee</td>', $cel('No', 0));
    }

    public function testSubformIndexNulIsGeldig(): void
    {
        $this->assertSame([0], ListServiceHelper::parseIndexList('0'));
        $this->assertSame([0, 2, 3], ListServiceHelper::parseIndexList('0,2, 3'));
        $this->assertSame([1], ListServiceHelper::parseIndexList('1,1'));
        $this->assertSame([], ListServiceHelper::parseIndexList(''));
        $this->assertSame([4], ListServiceHelper::parseIndexList(',x,-1,4,'));
    }

    public function testFormApiGebruiktDeIndexParser(): void
    {
        $bron = file_get_contents(dirname(__DIR__) . '/form_api.php');
        $this->assertStringContainsString('ListServiceHelper::parseIndexList(', $bron);
        $this->assertStringNotContainsString("array_filter(array_map('intval', explode(',', \$indicesParam)))", $bron);
    }
}
