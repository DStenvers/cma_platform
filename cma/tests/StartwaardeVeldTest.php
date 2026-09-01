<?php
/**
 * StartwaardeVeldTest — de startwaarde van een nieuw record moet het scherm halen.
 *
 * WAAROM DEZE TEST ER IS. Het schakelaartje "Login actief?" van tblLogins hoort bij
 * een nieuw record op Aan te staan. Het stond op Uit. De oorzaak zat niet in de
 * schakelaar en niet in de client, maar in het stuk ertussen: FormTemplate zette
 * config['defaultValue'] alléén in de radiogroup-tak van zijn switch. Voor elk ander
 * besturingselement werd Q_SCHEMA_DEFAULT — netjes gevuld door de JSON-loader uit
 * "defaultValue" — zonder één melding weggegooid. Het formulier kon dus keurig een
 * startwaarde declareren die nergens aankwam.
 *
 * Wat hier wordt vastgelegd:
 *   1. FormRenderer maakt van elke schrijfwijze van "aan" een eenduidige 'True'
 *      (JSON schrijft true, Access levert -1, oudere definities "checked").
 *   2. Niets ingevuld blijft Uit — een schakelaar zonder startwaarde staat af.
 *   3. De keten van velddefinitie → data-default is heel, voor de schakelaar én
 *      voor de gewone invoervelden waar dezelfde regel geldt.
 *
 * Let op: data-default is een startwaarde voor een NIEUW record. De client zet hem in
 * applyDefaultValues(), die alleen bij "nieuw" draait; een bestaand record met een
 * andere (of lege) waarde blijft ongemoeid.
 *
 *   php cma/tests/TestRunner.php StartwaardeVeldTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/FormControlHelper.php';
require_once dirname(__DIR__) . '/classes/FormRenderer.php';

use Cma\FormRenderer;

class StartwaardeVeldTest extends TestCase
{
    // ------------------------------------------------------------------
    // De schakelaar
    // ------------------------------------------------------------------

    public function testSchakelaarStaatAanBijEenBooleanTrue(): void
    {
        // Zo staat het in JSON: "defaultValue": true
        $out = FormRenderer::renderCheckBox('Actief', ['defaultValue' => true]);
        $this->assertStringContainsString('data-default="True"', $out);
    }

    public function testSchakelaarStaatUitBijEenBooleanFalse(): void
    {
        $out = FormRenderer::renderCheckBox('Actief', ['defaultValue' => false]);
        $this->assertStringContainsString('data-default="False"', $out);
    }

    public function testSchakelaarHerkentDeSchrijfwijzenVanAan(): void
    {
        foreach (['True', 'true', '1', '-1', 'checked', 'Ja', 'yes', 'aan', ' True '] as $waarde) {
            $out = FormRenderer::renderCheckBox('Actief', ['defaultValue' => $waarde]);
            $this->assertStringContainsString(
                'data-default="True"',
                $out,
                'startwaarde ' . var_export($waarde, true) . ' hoort Aan te betekenen'
            );
        }
    }

    public function testSchakelaarHerkentDeSchrijfwijzenVanUit(): void
    {
        foreach (['False', 'false', '0', 'Nee', 'no', 'uit', ''] as $waarde) {
            $out = FormRenderer::renderCheckBox('Actief', ['defaultValue' => $waarde]);
            $this->assertStringContainsString(
                'data-default="False"',
                $out,
                'startwaarde ' . var_export($waarde, true) . ' hoort Uit te betekenen'
            );
        }
    }

    public function testSchakelaarZonderStartwaardeStaatUit(): void
    {
        $out = FormRenderer::renderCheckBox('Actief', []);
        $this->assertStringContainsString('data-default="False"', $out);
    }

    public function testOnzinInDeStartwaardeMaaktDeSchakelaarNietAan(): void
    {
        $out = FormRenderer::renderCheckBox('Actief', ['defaultValue' => 'misschien']);
        $this->assertStringContainsString('data-default="False"', $out);
    }

    // ------------------------------------------------------------------
    // De keten: FormTemplate geeft Q_SCHEMA_DEFAULT door voor ELK type
    // ------------------------------------------------------------------

    public function testFormTemplateZetDeStartwaardeInDeBasisconfiguratie(): void
    {
        // De regel stond alleen in de radiogroup-tak; nu hoort hij bij de velden die
        // elk besturingselement krijgt. Zonder database te raken: lees de bron.
        $bron = file_get_contents(dirname(__DIR__) . '/classes/FormTemplate.php');
        $start = strpos($bron, 'private function buildControlConfig');
        $this->assertTrue($start !== false, 'buildControlConfig moet bestaan');

        $switch = strpos($bron, 'switch ($controlType)', $start);
        $this->assertTrue($switch !== false, 'de switch op besturingselement moet erop volgen');

        $basis = substr($bron, $start, $switch - $start);
        $this->assertStringContainsString(
            "'defaultValue' => \$this->arrRep[\\Q_SCHEMA_DEFAULT][\$index] ?? ''",
            $basis,
            'de startwaarde hoort in de basisconfiguratie, vóór de switch, zodat elk type hem krijgt'
        );
    }

    public function testDeStartwaardeStaatNietMeerAlleenInDeRadiogroupTak(): void
    {
        $bron = file_get_contents(dirname(__DIR__) . '/classes/FormTemplate.php');
        $this->assertEquals(
            1,
            substr_count($bron, "Q_SCHEMA_DEFAULT][\$index] ?? ''"),
            'één plek is genoeg; een tweede kopie gaat op termijn afwijken'
        );
    }

    // ------------------------------------------------------------------
    // Dezelfde startwaarde bij de andere besturingselementen
    // ------------------------------------------------------------------

    public function testTekstveldNeemtDeStartwaardeOver(): void
    {
        $out = FormRenderer::renderTextBox('functie', ['defaultValue' => 'Psycholoog']);
        $this->assertStringContainsString('data-default="Psycholoog"', $out);
    }

    public function testRadiogroepNeemtDeStartwaardeOver(): void
    {
        $out = FormRenderer::renderRadioGroup('soort', [
            'defaultValue' => 'f',
            'options' => [['value' => 'm', 'text' => 'Man'], ['value' => 'f', 'text' => 'Vrouw']],
        ]);
        $this->assertStringContainsString('data-default="f"', $out);
    }
}
