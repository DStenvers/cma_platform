<?php
/**
 * Een CMA-sessie vraagt een CMAG-cookie die bij de gebruiker hoort.
 *
 * Gevonden 16-10-2026 vanuit de site mijnRINO: met alleen de cookie CMAU=1 (of CMAU=1 met een
 * verzonnen CMAG) kreeg je het volledige CMA-dashboard als gebruiker 1. isLoggedIn() keek
 * alleen of CMAU een bestaande gebruiker was, en vulde een ontbrekende CMAG zelfs aan.
 * Nu beslist guidKlopt(): CMAG moet gelijk zijn aan tblUsers.userGUID.
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/SecurityHelper.php';

use Cma\SecurityHelper;

class CmaSessieGuidTest extends TestCase
{
    private const GUID = '36a8cd5a-5184-42aa-b9a2-f7eed05458be';

    public function testJuisteGuidGeeftSessie(): void
    {
        $this->assertTrue(SecurityHelper::guidKlopt(self::GUID, self::GUID));
        $this->assertTrue(SecurityHelper::guidKlopt('{' . strtoupper(self::GUID) . '}', self::GUID),
            'accolades en hoofdletters maken niet uit');
    }

    public function testVerkeerdeOfOntbrekendeGuidGeeftGeenSessie(): void
    {
        $this->assertFalse(SecurityHelper::guidKlopt('', self::GUID), 'alleen CMAU mag geen sessie zijn');
        $this->assertFalse(SecurityHelper::guidKlopt('00000000-0000-0000-0000-000000000000', self::GUID));
    }

    public function testLegeGuidInDeDatabaseGeeftGeenSessie(): void
    {
        $this->assertFalse(SecurityHelper::guidKlopt('', ''), 'lege GUID aan beide kanten is geen bewijs');
        $this->assertFalse(SecurityHelper::guidKlopt('iets', ''));
    }

    public function testZonderGuidKolomBlijftHetOudeGedrag(): void
    {
        // Gekopieerde gebruikersdatabase zonder de migratiekolom: login.php kan de GUID niet
        // opslaan, dus vergelijken kan niet - anders kon daar niemand meer in.
        $this->assertTrue(SecurityHelper::guidKlopt('', null));
    }

    public function testIsLoggedInVultCmagNietMeerZelfAan(): void
    {
        $src = file_get_contents(__DIR__ . '/../classes/SecurityHelper.php');
        $start = strpos($src, 'public static function isLoggedIn()');
        $eind = strpos($src, 'public static function guidKlopt(');
        $this->assertTrue($start !== false && $eind !== false && $eind > $start, 'isLoggedIn/guidKlopt gevonden');
        $body = substr($src, $start, $eind - $start);
        $this->assertFalse(strpos($body, 'Cookie::set(self::COOKIE_USERGUID') !== false,
            'isLoggedIn() mag CMAG niet zelf zetten: dan geldt CMAU alleen weer als sessie');
        $this->assertTrue(strpos($body, 'self::guidKlopt(') !== false, 'isLoggedIn() toetst CMAG via guidKlopt()');
    }
}
