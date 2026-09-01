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
    // De schrijfwijze van het bestand
    // ------------------------------------------------------------------

    public function testEenBestandMetGeescapeteStrepenBlijftZo(): void
    {
        $vlaggen = M::schrijfvlaggen('{"url":"..\\/..\\/cma"}');
        $this->assertEquals(0, $vlaggen & JSON_UNESCAPED_SLASHES, 'anders herschrijft de migratie het hele bestand');
    }

    public function testEenBestandMetKaleStrepenBlijftOokZo(): void
    {
        $vlaggen = M::schrijfvlaggen('{"url":"../../cma"}');
        $this->assertTrue(($vlaggen & JSON_UNESCAPED_SLASHES) !== 0);
    }

    public function testDeStijlkeuzeVerandertAlleenDeStrepen(): void
    {
        foreach (['{"a":"b/c"}', '{"a":"b\\/c"}'] as $origineel) {
            $vlaggen = M::schrijfvlaggen($origineel);
            $this->assertTrue(($vlaggen & JSON_PRETTY_PRINT) !== 0, 'inspringen blijft');
            $this->assertTrue(($vlaggen & JSON_UNESCAPED_UNICODE) !== 0, 'accenten blijven leesbaar');
        }
    }

    public function testDeInspringingVanHetBestandWordtHerkend(): void
    {
        $achtSpaties = "{\n        \"a\": 1\n}";
        $vierSpaties = "{\n    \"a\": 1\n}";
        $tabs        = "{\n\t\"a\": 1\n}";

        $this->assertEquals(8, strlen(M::inspringing($achtSpaties)));
        $this->assertEquals(4, strlen(M::inspringing($vierSpaties)));
        $this->assertEquals("\t", M::inspringing($tabs));
        $this->assertEquals('    ', M::inspringing('{}'), 'niets te zien: vier spaties');
    }

    public function testHetOpnieuwGecodeerdeJsonKrijgtDeOudeInspringingTerug(): void
    {
        // json_encode springt altijd met vier spaties in; 113 van de 130 definities
        // gebruiken er acht. Zonder deze stap verandert elke regel van het bestand.
        $origineel = "{\n        \"a\": 1,\n        \"b\": {\n                \"c\": 2\n        }\n}";
        $json = json_encode(['a' => 1, 'b' => ['c' => 2]], JSON_PRETTY_PRINT);

        $this->assertEquals($origineel, M::herindenteer($json, $origineel));
    }

    public function testEenBestandDatAlVierSpatiesGebruiktBlijftOngemoeid(): void
    {
        $json = json_encode(['a' => 1], JSON_PRETTY_PRINT);
        $this->assertEquals($json, M::herindenteer($json, "{\n    \"a\": 1\n}"));
    }

    public function testDeInhoudVanTekstenVerandertNietDoorHetHerindenteren(): void
    {
        // Een SQL-query met eigen inspringing staat in een string en mag niet meebewegen.
        $def = ['listQuery' => "SELECT 1\n    FROM t", 'n' => 1];
        $origineel = "{\n        \"n\": 1\n}";
        $uit = M::herindenteer(json_encode($def, JSON_PRETTY_PRINT), $origineel);

        $terug = json_decode($uit, true);
        $this->assertEquals("SELECT 1\n    FROM t", $terug['listQuery'], 'de query zelf blijft letterlijk');
    }
}
