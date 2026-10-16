<?php
/**
 * ExtraButtonRightsTest — de extra knoppen in de formuliertoolbar volgen de knoprechten
 * zoals de klassieke CMA (toolbar.inc ToolbarWriteExtraButtons + security.inc
 * CheckFormButtonRights/CheckRightsforUser):
 *   - knop N (extraIconURL = 1, extraIcon2URL..extraIcon5URL = 2..5) staat alleen in de
 *     toolbar als CheckFormButtonRights(gebruiker, formulier, N) > 0; beheerders zien alles;
 *   - CheckRightsforUser geeft max(secAccessType, secButtonN ? 30 : 0) over de rijen met
 *     secAccessType > 0. Wie het formulier mag zien, ziet de knop dus ook zonder secButtonN;
 *     zonder rij voor het menu-item/subformulier geen knop;
 *   - het knopnummer is het "slot" van een extraButton, anders de positie + 1.
 * Voorheen negeerde FormTemplate de knoprechten helemaal en telde alleen de positie.
 *
 *   php cma/tests/TestRunner.php ExtraButtonRightsTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormTemplate.php';

class ExtraButtonRightsTest extends TestCase
{
    /** Kolom-georiënteerde rechtenrijen zoals groupRightsSql() ze geeft. */
    private function rijen(array $rijen): array
    {
        $kol = array_fill(0, 8, []);
        foreach ($rijen as $r) {
            // [objectId, toegang, [b1..b5], beheer]
            $kol[0][] = $r[0];
            $kol[1][] = $r[1];
            for ($i = 0; $i < 5; $i++) {
                $kol[2 + $i][] = $r[2][$i] ?? 0;
            }
            $kol[7][] = $r[3] ?? 0;
        }
        return $kol;
    }

    private function knop(array $rijen, int $object, int $slot): int
    {
        [$toegang] = \Cma\SecurityHelper::accessFromRightsRows($this->rijen($rijen), $object, $slot);
        return (int)$toegang;
    }

    // ---- CheckRightsforUser-formule -------------------------------------------------

    public function testZonderRijVoorHetObjectGeenKnop(): void
    {
        $this->assertEquals(0, $this->knop([[74, 30, [-1, -1, -1, -1, -1]]], 83, 1));
        $this->assertEquals(0, $this->knop([], 83, 1));
    }

    public function testMetSecButtonNVolledig(): void
    {
        // ASP 68 Opleidingen, groep 2: lezen-plus-knoppen 2 en 3
        $rijen = [[83, 10, [0, -1, -1, 0, 0]]];
        $this->assertEquals(30, $this->knop($rijen, 83, 2));
        $this->assertEquals(30, $this->knop($rijen, 83, 3));
    }

    public function testZonderSecButtonNGeldtDeToegangZoalsInDeAsp(): void
    {
        // Rij met toegang maar zonder knoprecht: de ASP gaf max(toegang, 0) = toegang
        // terug, en "if 10 then" is waar: de knop stond er dus wel.
        $rijen = [[83, 10, [0, 0, 0, 0, 0]]];
        $this->assertEquals(10, $this->knop($rijen, 83, 1));
        $this->assertTrue(\Cma\SecurityHelper::extraButtonMask(fn ($s) => $this->knop($rijen, 83, $s)) === 31);
    }

    public function testZonderKnopnummerAlleenDeToegang(): void
    {
        [$toegang] = \Cma\SecurityHelper::accessFromRightsRows($this->rijen([[83, 10, [-1, -1, -1, -1, -1]]]), 83, -1);
        $this->assertEquals(10, (int)$toegang);
    }

    public function testKnopkolomHoortBijHetKnopnummer(): void
    {
        // Alleen knop 4 aan: knop 4 = 30, de rest = de toegang (20)
        $rijen = [[83, 20, [0, 0, 0, -1, 0]]];
        $this->assertEquals(30, $this->knop($rijen, 83, 4));
        $this->assertEquals(20, $this->knop($rijen, 83, 5));
    }

    // ---- masker ---------------------------------------------------------------------

    public function testMaskerBitPerKnopnummer(): void
    {
        $rechten = [1 => 30, 2 => 0, 3 => 10, 4 => 0, 5 => 40];
        $mask = \Cma\SecurityHelper::extraButtonMask(fn (int $s) => $rechten[$s]);
        $this->assertEquals(0b10101, $mask);
        $this->assertEquals(0, \Cma\SecurityHelper::extraButtonMask(fn (int $s) => 0));
    }

    public function testBeheerderZietAlleKnoppen(): void
    {
        $this->alsGebruiker(1);
        try {
            $this->assertEquals(\Cma\SecurityHelper::EXTRA_BUTTONS_ALL, \Cma\SecurityHelper::currentUserExtraButtonMask('bestaat_niet'));
            // Ook checkFormButtonRights zelf (de ASP-beheerderbypass), zonder databank
            $this->assertEquals(\Cma\SecurityHelper::ACCESS_FULL, \Cma\SecurityHelper::checkFormButtonRights(5, 999999, 3));
        } finally {
            \Cma\SecurityHelper::clearUserCache();
        }
    }

    public function testNietIngelogdGeenKnoppen(): void
    {
        $this->alsGebruiker(null);
        try {
            $this->assertEquals(0, \Cma\SecurityHelper::currentUserExtraButtonMask('bestaat_niet'));
        } finally {
            \Cma\SecurityHelper::clearUserCache();
        }
    }

    /** Ingelogd als gebruiker met dit niveau (null = niet ingelogd), zonder databank. */
    private function alsGebruiker(?int $niveau): void
    {
        $r = new \ReflectionClass(\Cma\SecurityHelper::class);
        $r->getProperty('isValidatedCache')->setValue(null, $niveau !== null);
        $r->getProperty('currentUserCache')->setValue(null, $niveau !== null ? ['ID' => 5, 'userLevel' => $niveau] : null);
    }

    // ---- slot vs positie ------------------------------------------------------------

    public function testZonderSlotGeldtDePositie(): void
    {
        $perSlot = \Cma\JsonFormLoader::extraButtonsBySlot([['title' => 'A'], ['title' => 'B']]);
        $this->assertEquals([1, 2], array_keys($perSlot));
        $this->assertEquals('B', $perSlot[2]['title']);
    }

    public function testSlotWintVanDePositie(): void
    {
        // logins.json: "Profiel wijzigen" vooraan, maar ASP-knoppen 1 en 2 houden hun nummer
        $perSlot = \Cma\JsonFormLoader::extraButtonsBySlot([
            ['title' => 'Profiel wijzigen', 'slot' => 3],
            ['title' => 'Login als', 'slot' => 1],
            ['title' => 'Maak en Haak', 'slot' => 2],
        ]);
        $this->assertEquals([1, 2, 3], array_keys($perSlot));
        $this->assertEquals('Login als', $perSlot[1]['title']);
        $this->assertEquals('Profiel wijzigen', $perSlot[3]['title']);
    }

    public function testOngeldigOfDubbelSlotWordtGemeld(): void
    {
        $this->assertCount(0, \Cma\JsonFormLoader::extraButtonSlotProblems([['title' => 'A', 'slot' => 2], ['title' => 'B', 'slot' => 3]]));
        $this->assertCount(1, \Cma\JsonFormLoader::extraButtonSlotProblems([['title' => 'A', 'slot' => 6]]));
        // positie 2 (impliciet slot 2) botst met een expliciet slot 2
        $problemen = \Cma\JsonFormLoader::extraButtonSlotProblems([['title' => 'A', 'slot' => 2], ['title' => 'B']]);
        $this->assertCount(1, $problemen);
        $this->assertStringContainsString("'B'", $problemen[0]);
        // en validateDefinitionData blokkeert zo'n definitie
        $def = ['table' => 'tblX', 'fields' => [['name' => 'x']], 'extraButtons' => [['title' => 'A', 'slot' => 2], ['title' => 'B', 'slot' => 2]]];
        $this->assertCount(1, \Cma\JsonFormLoader::validateDefinitionData($def, 'x'));
    }

    // ---- het sjabloon ---------------------------------------------------------------

    /** De extra knoppen zoals FormTemplate ze rendert, voor deze definitie en dit masker. */
    private function toolbar(array $extraButtons, int $mask): string
    {
        $conv = new \ReflectionMethod(\Cma\JsonFormLoader::class, 'convertToLegacyFormat');
        $arrRep = $conv->invoke(null, ['title' => 'T', 'table' => 'tblT', 'fields' => [], 'extraButtons' => $extraButtons]);
        $r = new \ReflectionClass(\Cma\FormTemplate::class);
        $t = $r->newInstanceWithoutConstructor();
        $r->getProperty('arrRep')->setValue($t, $arrRep);
        $r->getProperty('extraButtonMask')->setValue($t, $mask);
        $m = $r->getMethod('generateExtraButtons');
        return (string)$m->invoke($t);
    }

    private function knoppen(): array
    {
        return [
            ['title' => 'Profiel wijzigen', 'url' => '/p?e=[Email]', 'icon' => 'lnr lnr-user', 'slot' => 3, 'openInNewWindow' => true],
            ['title' => 'Login als', 'url' => '/i?a=[GUID]', 'icon' => 'lnr lnr-lock', 'slot' => 1],
            ['title' => 'Maak en Haak', 'url' => '/sync', 'icon' => 'lnr lnr-sync', 'slot' => 2],
        ];
    }

    public function testSjabloonToontAlleenKnoppenUitHetMasker(): void
    {
        $alles = $this->toolbar($this->knoppen(), 31);
        foreach (['Profiel wijzigen', 'Login als', 'Maak en Haak'] as $titel) {
            $this->assertStringContainsString('title="' . $titel . '"', $alles);
        }
        // Alleen knop 1 en 3
        $deel = $this->toolbar($this->knoppen(), 0b101);
        $this->assertStringContainsString('title="Login als"', $deel);
        $this->assertStringContainsString('title="Profiel wijzigen"', $deel);
        $this->assertStringNotContainsString('Maak en Haak', $deel);
        $this->assertEquals('', $this->toolbar($this->knoppen(), 0));
    }

    public function testSjabloonGebruiktHetSlotEnNietDePositie(): void
    {
        // Masker 0b001: alleen knop 1 = "Login als", ook al staat die op positie 2
        $html = $this->toolbar($this->knoppen(), 0b001);
        $this->assertStringContainsString('title="Login als"', $html);
        $this->assertStringContainsString('data-extra-index="1"', $html);
        $this->assertStringNotContainsString('Profiel wijzigen', $html);
        // openInNewWindow hoort bij de knop zelf (slot 3), niet bij positie 3
        $html3 = $this->toolbar($this->knoppen(), 0b100);
        $this->assertStringContainsString('data-open-new-window="true"', $html3);
        $this->assertStringContainsString('data-extra-index="3"', $html3);
        $this->assertStringNotContainsString('data-open-new-window', $this->toolbar($this->knoppen(), 0b001));
    }

    public function testSjabloonZonderSlotOngewijzigd(): void
    {
        $html = $this->toolbar([['title' => 'A', 'url' => '/a'], ['title' => 'B', 'url' => '/b']], 0b10);
        $this->assertStringContainsString('title="B"', $html);
        $this->assertStringNotContainsString('title="A"', $html);
    }

    public function testMaskerZitInCachesleutelEnBestandsnaam(): void
    {
        $bron = file_get_contents(dirname(__DIR__) . '/classes/FormTemplate.php');
        $this->assertStringContainsString("'_b' . \$extraButtonMask . '_' . cma_asset_version()", $bron);
        $this->assertStringContainsString("'_b' . \$extraButtonMask . '.html'", $bron);
        $form = file_get_contents(dirname(__DIR__) . '/form.php');
        $this->assertStringContainsString('FormTemplate::getForJsonForm($formName, $accessLevel, $extraButtonMask)', $form);
        $this->assertStringContainsString("\$accessLevel . '_' . \$extraButtonMask", $form);
    }
}
