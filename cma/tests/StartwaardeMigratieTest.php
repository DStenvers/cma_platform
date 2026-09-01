<?php
/**
 * StartwaardeMigratieTest — welke startwaarden uit repository.mdb mee terugkomen.
 *
 * WAAROM DEZE TEST ER IS. tblControls.schema_default bevat Access-uitdrukkingen, geen
 * waarden. Naast True en 14 staan er dingen als GenGUID(), Now() en Date()+60 in. Wie
 * die klakkeloos overneemt zet letterlijk de tekst "Now()" als startwaarde in een
 * datumveld — een fout die pas opvalt als iemand een record opslaat. De grens tussen
 * "dit is een waarde" en "dit is een uitdrukking" is dus het hele punt van de migratie,
 * en die grens wordt hier vastgelegd.
 *
 * De echte cijfers uit repository.mdb (430 velden met een startwaarde): 73 vinkjes die
 * aan hoorden te staan, ~91 andere losse waarden, 71 uitdrukkingen, en de rest betekent
 * "uit" of "leeg" — al het gedrag zonder startwaarde.
 *
 *   php cma/tests/TestRunner.php StartwaardeMigratieTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/Services/StartwaardeMigratie.php';

use Cma\Services\StartwaardeMigratie as M;

class StartwaardeMigratieTest extends TestCase
{
    // ------------------------------------------------------------------
    // Wat wél een waarde is
    // ------------------------------------------------------------------

    public function testWaarInAlleSchrijfwijzenWordtEenAangevinkteSchakelaar(): void
    {
        foreach (['True', 'true', 'TRUE', '-1', 'Ja', 'yes'] as $ruw) {
            $u = M::beoordeel($ruw, true);
            $this->assertEquals(M::OVERNEMEN, $u['besluit'], $ruw . ' hoort overgenomen te worden');
            $this->assertTrue($u['waarde'] === true, $ruw . ' hoort true te worden');
        }
    }

    public function testEenGetalOpEenGewoonVeldBlijftEenGetal(): void
    {
        $u = M::beoordeel('14', false);
        $this->assertEquals(M::OVERNEMEN, $u['besluit']);
        $this->assertTrue($u['waarde'] === 14, 'een geheel getal blijft een geheel getal');

        $u = M::beoordeel('1.5', false);
        $this->assertTrue($u['waarde'] === 1.5);
    }

    public function testEenGetalOpEenSchakelaarIsAanOfUit(): void
    {
        // In de repository staat op vinkjes soms 1 of 0 in plaats van True/False.
        $aan = M::beoordeel('1', true);
        $this->assertEquals(M::OVERNEMEN, $aan['besluit']);
        $this->assertTrue($aan['waarde'] === true);

        $uit = M::beoordeel('0', true);
        $this->assertEquals(M::OVERSLAAN, $uit['besluit'], 'uit is al het gedrag zonder startwaarde');
    }

    public function testAanhalingstekensHorenBijDeUitdrukkingNietBijDeTekst(): void
    {
        $u = M::beoordeel('"studiegids"', false);
        $this->assertEquals(M::OVERNEMEN, $u['besluit']);
        $this->assertEquals('studiegids', $u['waarde'], 'de aanhalingstekens gaan eraf');

        $u = M::beoordeel('"(030) 230 84 00"', false);
        $this->assertEquals('(030) 230 84 00', $u['waarde']);
    }

    public function testEenDatumliteraalWordtOvergenomen(): void
    {
        $u = M::beoordeel('#01-01-2099#', false);
        $this->assertEquals(M::OVERNEMEN, $u['besluit']);
        $this->assertEquals('01-01-2099', $u['waarde']);
    }

    // ------------------------------------------------------------------
    // Wat géén waarde is
    // ------------------------------------------------------------------

    public function testUitdrukkingenBlijvenLiggen(): void
    {
        foreach (['GenGUID()', 'Now()', 'now()', 'Date()', 'DATE()+60', '=Date()'] as $ruw) {
            $u = M::beoordeel($ruw, false);
            $this->assertEquals(
                M::OVERSLAAN,
                $u['besluit'],
                $ruw . ' wordt door de database ingevuld, niet door het scherm'
            );
            $this->assertEquals('uitdrukking', $u['soort']);
        }
    }

    public function testOnwaarLevertGeenStartwaardeOp(): void
    {
        foreach (['False', 'false', 'FALSE', '0', 'Nee'] as $ruw) {
            $u = M::beoordeel($ruw, true);
            $this->assertEquals(M::OVERSLAAN, $u['besluit'], $ruw . ' voegt niets toe');
        }
    }

    public function testEenLegeTekstliteraalLevertGeenStartwaardeOp(): void
    {
        foreach (['""', "''"] as $ruw) {
            $u = M::beoordeel($ruw, false);
            $this->assertEquals(M::OVERSLAAN, $u['besluit'], $ruw . ' — een leeg veld is al leeg');
        }
    }

    public function testNietsIngevuldLevertNiets(): void
    {
        foreach (['', '   ', null] as $ruw) {
            $u = M::beoordeel($ruw, false);
            $this->assertEquals(M::OVERSLAAN, $u['besluit']);
        }
    }

    // ------------------------------------------------------------------
    // Toepassen op een definitie
    // ------------------------------------------------------------------

    public function testDeStartwaardeKomtAchterDeCaptionTeStaan(): void
    {
        $def = ['fields' => [
            ['name' => 'actief', 'type' => 'checkbox', 'caption' => 'Login actief?', 'dataType' => 'boolean'],
        ]];
        $uit = M::toepassen($def, ['actief' => true]);
        $veld = $uit['definitie']['fields'][0];

        $this->assertTrue($veld['defaultValue'] === true);
        $this->assertEquals(
            ['name', 'type', 'caption', 'defaultValue', 'dataType'],
            array_keys($veld),
            'achter de caption leest het prettigst en houdt de volgorde van de rest heel'
        );
        $this->assertEquals(['actief' => true], $uit['gezet']);
    }

    public function testEenVeldZonderCaptionKrijgtDeStartwaardeAchteraan(): void
    {
        $def = ['fields' => [['name' => 'aantal', 'type' => 'textbox']]];
        $uit = M::toepassen($def, ['aantal' => 14]);
        $this->assertEquals(['name', 'type', 'defaultValue'], array_keys($uit['definitie']['fields'][0]));
    }

    public function testEenDefinitieDieHetZelfAlVastlegtWint(): void
    {
        $def = ['fields' => [
            ['name' => 'actief', 'type' => 'checkbox', 'caption' => 'Actief?', 'defaultValue' => false],
        ]];
        $uit = M::toepassen($def, ['actief' => true]);

        $this->assertTrue($uit['definitie']['fields'][0]['defaultValue'] === false, 'de bestaande keuze blijft staan');
        $this->assertEquals([], $uit['gezet']);
        $this->assertTrue(isset($uit['overgeslagen']['actief']));
    }

    public function testEenVeldDatNietInDeDefinitieStaatWordtNietToegevoegd(): void
    {
        $def = ['fields' => [['name' => 'actief', 'type' => 'checkbox', 'caption' => 'Actief?']]];
        $uit = M::toepassen($def, ['bestaatniet' => true]);

        $this->assertEquals(1, count($uit['definitie']['fields']), 'er komt geen veld bij');
        $this->assertEquals([], $uit['gezet']);
    }

    public function testDeVeldnaamWordtHoofdletterongevoeligVergeleken(): void
    {
        // De repository schrijft "Actief", de JSON "actief".
        $def = ['fields' => [['name' => 'actief', 'type' => 'checkbox', 'caption' => 'Actief?']]];
        $uit = M::toepassen($def, [strtolower('Actief') => true]);
        $this->assertTrue($uit['definitie']['fields'][0]['defaultValue'] === true);
    }

    public function testEenDefinitieZonderVeldenGaatNietStuk(): void
    {
        $uit = M::toepassen(['table' => 'tblIets'], ['actief' => true]);
        $this->assertEquals([], $uit['gezet']);
        $this->assertEquals([], $uit['definitie']['fields']);
    }

    public function testEenWaarOnwaarLandtAlleenOpEenVinkje(): void
    {
        // Komt echt voor: een Ja/Nee-kolom die bij de omzetting een tekstvak werd.
        // Daar "1" in zetten geeft de gebruiker een 1 in het invoervak te zien.
        $def = ['fields' => [
            ['name' => 'emailMessageNotificaties', 'type' => 'textbox', 'caption' => 'Notificaties', 'maxLength' => 1],
        ]];
        $uit = M::toepassen($def, ['emailmessagenotificaties' => true]);

        $this->assertTrue(!isset($uit['definitie']['fields'][0]['defaultValue']));
        $this->assertEquals([], $uit['gezet']);
        $this->assertTrue(isset($uit['overgeslagen']['emailmessagenotificaties']), 'met vermelding, want het veld zelf klopt niet');
    }

    public function testEenTekstOfGetalMagWelOpEenTekstvak(): void
    {
        $def = ['fields' => [['name' => 'mv', 'type' => 'textbox', 'caption' => 'M/V']]];
        $uit = M::toepassen($def, ['mv' => 'V']);
        $this->assertEquals('V', $uit['definitie']['fields'][0]['defaultValue']);
    }

    // ------------------------------------------------------------------
    // De startwaarde als losse regel in de bestandstekst
    // ------------------------------------------------------------------

    private function definitieTekst(): string
    {
        // Acht spaties inspringen en geescapete strepen: zoals de echte bestanden.
        return <<<JSON
{
        "name": "logins",
        "table": "tblLogins",
        "listColumns": [
                {
                        "name": "actief"
                }
        ],
        "fields": [
                {
                        "name": "actief",
                        "type": "checkbox",
                        "caption": "Login actief?",
                        "dataType": "boolean"
                },
                {
                        "name": "roepnaam",
                        "type": "textbox"
                }
        ]
}
JSON;
    }

    public function testDeStartwaardeKomtAchterDeCaptionInDeTekst(): void
    {
        $uit = M::invoegen($this->definitieTekst(), [], ['actief' => true]);
        $regels = explode("\n", $uit['tekst']);

        $i = null;
        foreach ($regels as $n => $regel) {
            if (strpos($regel, '"defaultValue"') !== false) { $i = $n; break; }
        }
        $this->assertTrue($i !== null, 'de regel moet er staan');
        $this->assertStringContainsString('"caption": "Login actief?"', $regels[$i - 1]);
        $this->assertEquals('                        "defaultValue": true,', $regels[$i], 'zelfde inspringing als de buren');
    }

    public function testAlleenDeToegevoegdeRegelVerandert(): void
    {
        $origineel = $this->definitieTekst();
        $uit = M::invoegen($origineel, [], ['actief' => true]);

        $oud = explode("\n", $origineel);
        $nieuw = array_values(array_filter(explode("\n", $uit['tekst']), fn($r) => strpos($r, '"defaultValue"') === false));

        $this->assertEquals($oud, $nieuw, 'haal de nieuwe regel weg en het bestand is weer letterlijk het oude');
    }

    public function testDeUitkomstIsNogSteedsLeesbareJson(): void
    {
        $uit = M::invoegen($this->definitieTekst(), [], ['actief' => true, 'roepnaam' => 'Jan']);
        $terug = json_decode($uit['tekst'], true);

        $this->assertTrue($terug !== null, 'leesbaar gebleven');
        $this->assertTrue($terug['fields'][0]['defaultValue'] === true);
        $this->assertEquals('Jan', $terug['fields'][1]['defaultValue']);
    }

    public function testEenVeldZonderCaptionKrijgtDeRegelAchterZijnNaam(): void
    {
        $uit = M::invoegen($this->definitieTekst(), [], ['roepnaam' => 'Jan']);
        $terug = json_decode($uit['tekst'], true);

        $this->assertEquals('Jan', $terug['fields'][1]['defaultValue']);
        $this->assertEquals('textbox', $terug['fields'][1]['type'], 'de rest van het veld blijft heel');
    }

    public function testEenAnkerregelZonderKommaKrijgtErEen(): void
    {
        // "type": "textbox" is de laatste regel van het blok; de nieuwe regel komt
        // erachter en mag dan zelf geen komma hebben.
        $uit = M::invoegen($this->definitieTekst(), [], ['roepnaam' => 'Jan']);
        $this->assertTrue(json_decode($uit['tekst'], true) !== null, 'anders staat er een komma te veel of te weinig');
        $this->assertStringContainsString('"name": "roepnaam",', $uit['tekst']);
    }

    public function testEenNaamBuitenDeVeldenlijstWordtGenegeerd(): void
    {
        // "actief" staat ook in listColumns; daar hoort geen startwaarde.
        $uit = M::invoegen($this->definitieTekst(), [], ['actief' => true]);
        $this->assertEquals(1, substr_count($uit['tekst'], '"defaultValue"'));

        $terug = json_decode($uit['tekst'], true);
        $this->assertTrue(!isset($terug['listColumns'][0]['defaultValue']));
    }

    public function testEenVeldDatAlEenStartwaardeHeeftKrijgtErGeenTweede(): void
    {
        $eerst = M::invoegen($this->definitieTekst(), [], ['actief' => true]);
        $nogmaals = M::invoegen($eerst['tekst'], [], ['actief' => false]);

        $this->assertEquals(1, substr_count($nogmaals['tekst'], '"defaultValue"'), 'de migratie mag twee keer draaien');
        $this->assertEquals(['actief'], $nogmaals['mislukt']);
        $this->assertTrue(json_decode($nogmaals['tekst'], true)['fields'][0]['defaultValue'] === true);
    }

    public function testEenVeldDatNietInDeTekstStaatWordtGemeld(): void
    {
        $uit = M::invoegen($this->definitieTekst(), [], ['bestaatniet' => true]);
        $this->assertEquals([], $uit['gezet']);
        $this->assertEquals(['bestaatniet'], $uit['mislukt']);
        $this->assertEquals($this->definitieTekst(), $uit['tekst'], 'en het bestand blijft ongemoeid');
    }

    public function testEenDefinitieZonderVeldenlijstBlijftOngemoeid(): void
    {
        $uit = M::invoegen('{"name":"x"}', [], ['actief' => true]);
        $this->assertEquals('{"name":"x"}', $uit['tekst']);
        $this->assertEquals(['actief'], $uit['mislukt']);
    }
}
