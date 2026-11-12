<?php
/**
 * De boom bij een DISTINCT-lijstquery: Access negeert DISTINCT als er ook TOP en ORDER BY in staan (gemeten op deelnemers: 1001 rijen
 * waarvan 409 verschillende id's, elke naam 3-5 keer in de boom terwijl de tabelweergave klopte). TreeService begrenst zo'n query dus niet
 * met SELECT DISTINCT TOP n, maar laat bij het lezen rijen weg die gelijk zijn aan een eerdere.
 *
 *   php cma/tests/TestRunner.php TreeDistinctTest
 */
require_once __DIR__ . '/TestRunner.php';

require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/TreeService.php';

use Cma\Services\TreeService;

class TreeDistinctTest extends TestCase
{
    public function testHerkentEenDistinctQuery(): void
    {
        $this->assertTrue(TreeService::isDistinctQuery('SELECT DISTINCT a, b FROM t'), 'SELECT DISTINCT');
        $this->assertTrue(TreeService::isDistinctQuery("  select\n  distinct a FROM t"), 'kleine letters en witruimte');
        $this->assertTrue(TreeService::isDistinctQuery('SELECT DISTINCT TOP 5 a FROM t'), 'DISTINCT TOP');
        $this->assertFalse(TreeService::isDistinctQuery('SELECT a FROM t'), 'gewone query');
        $this->assertFalse(TreeService::isDistinctQuery('SELECT DISTINCTROW a FROM t'), 'DISTINCTROW is een ander sleutelwoord');
        $this->assertFalse(TreeService::isDistinctQuery('SELECT a, (SELECT DISTINCT b FROM u) FROM t'), 'een DISTINCT in een subquery telt niet');
    }

    public function testRijSleutelNegeertNummeriekeKolommenEnVerschiltPerWaarde(): void
    {
        $a = ['ID' => '1', 'Naam' => 'Anna', 0 => '1', 1 => 'Anna'];
        $b = ['ID' => '1', 'Naam' => 'Anna'];
        $c = ['ID' => '2', 'Naam' => 'Anna'];
        $this->assertEquals(TreeService::distinctRowKey($a), TreeService::distinctRowKey($b), 'dezelfde waarden = dezelfde rij, ook met numerieke dubbelen');
        $this->assertTrue(TreeService::distinctRowKey($b) !== TreeService::distinctRowKey($c), 'een ander id = een andere rij');
        $this->assertTrue(TreeService::distinctRowKey(['Naam' => "Zoë"]) !== '', 'ook niet-ASCII geeft een sleutel');
    }
}
