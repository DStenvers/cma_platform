<?php
/**
 * Tests voor lib_FormValidDate() — de datumcontrole van een geposte formulierwaarde.
 *
 * Draaien met: php cma/tests/TestRunner.php FormValidDateTest
 *
 * Wat hier wordt vastgelegd: de controle keurt precies dát af wat SQL::postDateStr() later
 * niet zou kunnen opslaan. Hij eiste eerst letterlijk dd/mm/jjjj — tien tekens met een schuine
 * streep op plek 3 en 6 — terwijl de datumkiezer van de site dd-mm-jjjj toont en jjjj-mm-dd
 * post. Wie op de kalender klikte kreeg daardoor "Ongeldige datum opgegeven" over een datum
 * die hij niet eens zelf had getypt (gemeld op het toets-plaatsen scherm van mijnRINO).
 */

require_once __DIR__ . '/TestRunner.php';

class FormValidDateTest extends TestCase
{
    public function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/library/lib_form.inc';
    }

    public function testAlleSchrijfwijzenVanDeSiteWordenGoedgekeurd(): void
    {
        // Wat de datumkiezer post (jjjj-mm-dd) en wat hij toont (dd-mm-jjjj).
        $this->assertSame('', lib_FormValidDate('2026-06-07'));
        $this->assertSame('', lib_FormValidDate('07-06-2026'));
        // En de oude, met de hand getypte vorm blijft gewoon geldig.
        $this->assertSame('', lib_FormValidDate('07/06/2026'));
        $this->assertSame('', lib_FormValidDate('7-6-2026'));
    }

    public function testOnzinWordtNogSteedsAfgekeurd(): void
    {
        $this->assertTrue('' !== lib_FormValidDate('geen datum'));
        $this->assertTrue('' !== lib_FormValidDate('31-02-2026'));   // bestaat niet
        $this->assertTrue('' !== lib_FormValidDate('07-06'));        // te weinig delen
    }

    public function testLeegVeldBlijftEenFout(): void
    {
        // Aanroepers gebruiken deze functie ook om een niet-ingevuld verplicht datumveld te
        // betrappen; dat gedrag verandert niet.
        $this->assertTrue('' !== lib_FormValidDate(''));
    }

    public function testNullBlijftDoorgelaten(): void
    {
        // "veld niet meegestuurd" is iets anders dan "veld leeg gelaten".
        $this->assertSame('', lib_FormValidDate(null));
    }

    public function testDeMeldingNoemtDeVormDieDeSiteZelfToont(): void
    {
        $this->assertStringContainsString('dd-mm-jjjj', lib_FormValidDate('geen datum'));
    }
}
