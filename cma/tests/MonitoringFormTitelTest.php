<?php
/**
 * MonitoringFormTitelTest.php — de naam van een formulier in de logregels.
 *
 * tblCMAMonitoring bewaart twee dingen naast elkaar: de handle waarmee het
 * formulier wordt aangeroepen (Form, bijv. "_menus") en de titel zoals die bij
 * het schrijven gold (Formname, "Menu's"). Het dashboard toonde de handle. Wie
 * net het menu had aangepast, kreeg "_menus" te zien in "Recente activiteit" —
 * een naam die nergens in de interface voorkomt.
 *
 * Deze test legt de keuzevolgorde vast: opgeslagen titel, anders de titel uit
 * de definitie, anders de handle. De functie komt uit de échte bron, zodat een
 * test hier iets zegt over de code die wordt uitgeleverd; de definitie-cache
 * wordt hier gestubd zodat de regels zonder bestanden op schijf te toetsen zijn.
 *
 *   php tests/TestRunner.php MonitoringFormTitelTest
 */

require_once __DIR__ . '/TestRunner.php';

// Stub voor de definitie-cache die monitoringFormTitle raadpleegt. Staat vóór
// de eval, zodat de uit de bron geknipte functie deze versie vindt.
if (!function_exists('loadFormDefinitionCache')) {
    function loadFormDefinitionCache(): array
    {
        return [
            ['_menus' => ['title' => "Menu's"], 'groups' => ['title' => 'Groepen']],
            ["menu's" => '_menus', 'groepen' => 'groups'],
        ];
    }
}

class MonitoringFormTitelTest extends TestCase
{
    /** Knip monitoringFormTitle uit dashboard_stats.php en definieer hem hier. */
    private function laadFunctie(): void
    {
        if (function_exists('monitoringFormTitle')) {
            return;
        }
        $pad = __DIR__ . '/../api/dashboard_stats.php';
        $this->assertTrue(is_file($pad), 'dashboard_stats.php niet gevonden');
        $bron = (string) file_get_contents($pad);

        $start = strpos($bron, 'function monitoringFormTitle(');
        $this->assertTrue($start !== false, 'monitoringFormTitle staat niet meer in dashboard_stats.php');

        $diepte = 0;
        $eind = null;
        for ($i = (int) strpos($bron, '{', $start); $i < strlen($bron); $i++) {
            if ($bron[$i] === '{') {
                $diepte++;
            } elseif ($bron[$i] === '}') {
                $diepte--;
                if ($diepte === 0) {
                    $eind = $i;
                    break;
                }
            }
        }
        $this->assertTrue($eind !== null, 'einde van monitoringFormTitle niet gevonden');

        eval(substr($bron, $start, $eind - $start + 1));
    }

    public function testDeOpgeslagenTitelWint(): void
    {
        $this->laadFunctie();
        $this->assertEquals("Menu's", monitoringFormTitle('_menus', "Menu's"));
        $this->assertEquals('Groepen', monitoringFormTitle('groups', 'Groepen'));
    }

    public function testZonderOpgeslagenTitelKomtDeTitelUitDeDefinitie(): void
    {
        $this->laadFunctie();
        $this->assertEquals("Menu's", monitoringFormTitle('_menus', ''));
        $this->assertEquals('Groepen', monitoringFormTitle('groups', ''));
    }

    public function testEenHandleAlsOpgeslagenTitelLevertAlsnogDeDefinitieTitel(): void
    {
        $this->laadFunctie();
        // Oude regels bewaarden soms de handle in beide kolommen
        $this->assertEquals("Menu's", monitoringFormTitle('_menus', '_menus'));
    }

    public function testEenOnbekendFormulierHoudtZijnHandle(): void
    {
        $this->laadFunctie();
        $this->assertEquals('verdwenen_form', monitoringFormTitle('verdwenen_form', ''));
    }

    public function testZonderIetsBruikbaarsEenStreepje(): void
    {
        $this->laadFunctie();
        $this->assertEquals('-', monitoringFormTitle('', ''));
    }

    /**
     * De interne formulieren moeten in de definitie-cache passen: staat er geen
     * title in _menus.json, dan valt het dashboard alsnog terug op de handle.
     */
    public function testDeInterneMenuFormulierenHebbenEenTitel(): void
    {
        foreach (['_menus' => "Menu's", '_menu_items' => 'Menu-items'] as $naam => $verwacht) {
            $pad = __DIR__ . '/../assets/forms/definitions/' . $naam . '.json';
            $this->assertTrue(is_file($pad), "$naam.json niet gevonden");
            $def = json_decode((string) file_get_contents($pad), true);
            $this->assertEquals($verwacht, $def['title'] ?? '', "title ontbreekt of wijkt af in $naam.json");
        }
    }

    /** De cache mag interne formulieren niet overslaan — dan blijft "_menus" staan. */
    public function testDeDefinitieCacheSlaatOnderstreepFormulierenNietOver(): void
    {
        $bron = (string) file_get_contents(__DIR__ . '/../api/dashboard_stats.php');
        $start = strpos($bron, 'function loadFormDefinitionCache(');
        $this->assertTrue($start !== false, 'loadFormDefinitionCache niet gevonden');
        $blok = substr($bron, $start, 2000);
        $this->assertTrue(
            !str_contains($blok, "str_starts_with(\$formName, '_')"),
            'interne formulieren worden weer overgeslagen; dan toont het dashboard "_menus"'
        );
    }
}
