<?php
/**
 * OwnDataFilterTest — "Alleen eigen records" (toegangsniveau 20) op JSON-lijsten.
 *
 * De oude list.asp liet bij blnSecurityByUser + constSecAccess_Change_Own_Data
 * alleen rijen zien waarvan `userid` gelijk is aan de ingelogde CMA-gebruiker.
 * ListServiceHelper::applyOwnDataFilter doet dat nu voor de JSON-boom en de
 * tabelweergave door een WHERE-voorwaarde toe te voegen.
 *
 *   php cma/tests/TestRunner.php OwnDataFilterTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';

use Cma\SecurityHelper;
use Cma\Services\ListServiceHelper;

class OwnDataFilterTest extends TestCase
{
    public function setUp(): void
    {
        TestHarness::reset();
        TestHarness::loginAs(0); // LEVEL_USER, ID 1
    }

    private function setFormRights(string $formName, int $level): void
    {
        $prop = new \ReflectionProperty(SecurityHelper::class, 'formRightsCache');
        $prop->setAccessible(true);
        $cache = $prop->getValue();
        $cache['1_name_' . $formName] = $level;
        $prop->setValue(null, $cache);
    }

    public function testEigenRecordsVoegtUseridVoorwaardeToe(): void
    {
        $this->setFormRights('eigen_form', SecurityHelper::ACCESS_CHANGE_OWN_DATA);
        $sql = ListServiceHelper::applyOwnDataFilter('SELECT ID, naam, userid FROM [tblX]', ['securityByUser' => true], 'eigen_form');
        $this->assertStringContainsString('WHERE [userid] = 1', $sql);
    }

    public function testEigenRecordsRespecteertBestaandeWhereEnOrderBy(): void
    {
        $this->setFormRights('eigen_form', SecurityHelper::ACCESS_CHANGE_OWN_DATA);
        $sql = ListServiceHelper::applyOwnDataFilter(
            'SELECT ID, naam, userid FROM [tblX] WHERE actief = True ORDER BY naam',
            ['securityByUser' => true],
            'eigen_form'
        );
        $this->assertStringContainsString('[userid] = 1', $sql);
        $this->assertTrue(stripos($sql, '[userid] = 1') < stripos($sql, 'ORDER BY'), 'voorwaarde hoort vóór ORDER BY: ' . $sql);
        $this->assertStringContainsString('actief = True', $sql);
    }

    public function testVolledigeRechtenZienAlles(): void
    {
        $this->setFormRights('eigen_form', SecurityHelper::ACCESS_FULL);
        $sql = ListServiceHelper::applyOwnDataFilter('SELECT ID FROM [tblX]', ['securityByUser' => true], 'eigen_form');
        $this->assertSame('SELECT ID FROM [tblX]', $sql);
    }

    public function testZonderSecurityByUserGeenFilter(): void
    {
        $this->setFormRights('gewoon_form', SecurityHelper::ACCESS_CHANGE_OWN_DATA);
        $sql = ListServiceHelper::applyOwnDataFilter('SELECT ID FROM [tblX]', [], 'gewoon_form');
        $this->assertSame('SELECT ID FROM [tblX]', $sql);
    }

    public function testRechtenmatrixBiedtKolomEigenAlleenBijSecurityByUser(): void
    {
        $bron = file_get_contents(dirname(__DIR__) . '/classes/JsonFormRenderer.php');
        $this->assertStringContainsString("['value' => 20, 'label' => 'Eigen', 'conditional' => true]", $bron);
    }
}
