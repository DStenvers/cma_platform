<?php
/**
 * Tests voor lib_FormValRequired() en lib_FormPassOn(): beide lopen over de NAMEN van
 * de geposte velden.
 *
 * Draaien met: php cma/tests/TestRunner.php FormPostVeldnamenTest
 *
 * In VBScript geeft "for each fld in request.form" de veldnamen. De omzetting maakte daar
 * "foreach (Request::postAll() as $fld)" van, en dat geeft in PHP de WAARDEN. Gevolg:
 * lib_FormValRequired() zocht naar een veld dat "Jan" heette in plaats van naar
 * "required-Naam", vond dus nooit een leeg verplicht veld en liet alles door.
 * lib_FormPassOn() zette de waarden als veldnaam in de verborgen velden, zodat de vorige
 * stap niet meereisde; daarbij las "!find(...) > -1" als "(!find(...)) > -1" — altijd
 * waar — zodat de overslaanlijst niets oversloeg.
 */

require_once __DIR__ . '/TestRunner.php';

class FormPostVeldnamenTest extends TestCase
{
    private array $postVoor = [];

    public function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/library/lib_html.inc';
        require_once dirname(__DIR__, 2) . '/library/lib_form.inc';
        $this->postVoor = $_POST;
    }

    public function tearDown(): void
    {
        $_POST = $this->postVoor;
    }

    public function testLeegRequiredVeldWordtGemeld(): void
    {
        $_POST = ['required-Naam' => '', 'Opmerking' => 'iets'];
        $fout = lib_FormValRequired();
        $this->assertStringContainsString('Naam', $fout);
        $this->assertStringNotContainsString('Opmerking', $fout);
    }

    public function testIngevuldRequiredVeldIsGoed(): void
    {
        $_POST = ['required-Naam' => 'Jan', 'Opmerking' => ''];
        $this->assertSame('', lib_FormValRequired());
    }

    public function testVeldUitDeRequiredLijstWordtGemeld(): void
    {
        // Het veld "required" noemt de verplichte velden; de label-variant geeft de tekst.
        $_POST = ['required' => 'Email;Naam', 'Email' => ' ', 'Email__label' => 'E-mailadres', 'Naam' => 'Jan'];
        $fout = lib_FormValRequired();
        $this->assertStringContainsString('E-mailadres', $fout);
        $this->assertStringNotContainsString('Naam', $fout);
    }

    public function testEenWaardeDieOpEenVeldnaamLijktTeltNiet(): void
    {
        // Met de oude lus werd de WAARDE "required-x" als verplicht veld gezien.
        $_POST = ['Opmerking' => 'required-x'];
        $this->assertSame('', lib_FormValRequired());
    }

    public function testPassOnGeeftVeldnaamEnWaardeDoor(): void
    {
        $_POST = ['zaal' => '3', 'leeg' => '', 'step' => '2'];
        ob_start();
        lib_FormPassOn('step');
        $html = ob_get_clean();
        $this->assertStringContainsString('<input type=hidden name="zaal" value="3">', $html);
        // De overslaanlijst slaat over, en lege waarden reizen niet mee.
        $this->assertStringNotContainsString('name="step"', $html);
        $this->assertStringNotContainsString('name="leeg"', $html);
    }

    public function testPassOnZonderOverslaanlijstGeeftAllesDoor(): void
    {
        $_POST = ['a' => '1', 'b' => '2'];
        ob_start();
        lib_FormPassOn('');
        $html = ob_get_clean();
        $this->assertStringContainsString('name="a" value="1"', $html);
        $this->assertStringContainsString('name="b" value="2"', $html);
    }
}
