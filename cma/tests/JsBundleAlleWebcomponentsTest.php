<?php
/**
 * JsBundleAlleWebcomponentsTest.php — elk library-component zit in de CMA-bundel.
 *
 * Gemeld: "lib-sheet does not work". In de storybook viel de demo-knop om met
 * "document.getElementById(...).open is not a function": customElements.get('lib-sheet')
 * was leeg, want lib-sheet.js zat niet in cma_js_bundle() — net als lib-field,
 * lib-arrowsteps en lib-fileuploader. Het commentaar boven de lijst zegt "ALL must be
 * included, no exceptions"; deze test houdt dat ook echt zo.
 *
 * Eén bewuste uitzondering: lib-log.js is geen custom element maar een logger die bij het
 * laden console.log/warn/error overneemt. Die hoort niet op elke pagina, en
 * documentation.php laadt hem los met zijn configuratie ervóór.
 *
 * Wie een component in de bundel zet, moet ook een guard tegen dubbele registratie hebben:
 * een pagina die hetzelfde bestand nog eens los bijlaadt (imageupload_crop.php doet dat met
 * lib-fileuploader) zou anders omvallen op "has already been used with this registry".
 *
 *   php tests/TestRunner.php JsBundleAlleWebcomponentsTest
 */

require_once __DIR__ . '/TestRunner.php';

class JsBundleAlleWebcomponentsTest extends TestCase
{
    private const UITZONDERINGEN = ['lib-log.js'];

    private function bundelBron(): string
    {
        $bron = (string) file_get_contents(__DIR__ . '/../bootstrap.inc');
        $start = strpos($bron, 'function cma_js_bundle()');
        $this->assertTrue($start !== false, 'cma_js_bundle() staat niet meer in bootstrap.inc');
        $eind = strpos($bron, 'function cma_form_js_bundle()', (int) $start);
        return substr($bron, (int) $start, $eind !== false ? $eind - (int) $start : null);
    }

    /** @return string[] bestandsnamen, zonder .min */
    private function componenten(): array
    {
        $uit = [];
        foreach (glob(__DIR__ . '/../../library/webcomponents/lib-*.js') ?: [] as $pad) {
            $naam = basename($pad);
            if (substr($naam, -7) === '.min.js') { continue; }
            $uit[] = $naam;
        }
        return $uit;
    }

    public function testElkLibraryComponentZitInDeBundel(): void
    {
        $bundel = $this->bundelBron();
        $namen = $this->componenten();
        $this->assertTrue(count($namen) > 15, 'library/webcomponents/ is niet gevonden of leeg');
        foreach ($namen as $naam) {
            if (in_array($naam, self::UITZONDERINGEN, true)) { continue; }
            $this->assertStringContainsString(
                "'../library/webcomponents/$naam'",
                $bundel,
                "$naam staat niet in cma_js_bundle(): customElements.get() blijft leeg en elke demo of pagina die het gebruikt valt om"
            );
        }
    }

    public function testDeUitzonderingBlijftEenLoggerEnGeenElement(): void
    {
        $src = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/lib-log.js');
        $this->assertStringNotContainsString('customElements.define', $src,
            'lib-log.js definieert nu een custom element; dan hoort het wél in de bundel');
        $this->assertStringNotContainsString("'../library/webcomponents/lib-log.js'", $this->bundelBron(),
            'lib-log.js neemt console.log/warn/error over bij het laden en hoort daarom niet in de bundel');
    }

    public function testElkGebundeldComponentHeeftEenGuardTegenDubbeleRegistratie(): void
    {
        foreach ($this->componenten() as $naam) {
            if (in_array($naam, self::UITZONDERINGEN, true)) { continue; }
            $src = (string) file_get_contents(__DIR__ . '/../../library/webcomponents/' . $naam);
            if (strpos($src, 'customElements.define') === false) { continue; }
            $tag = substr($naam, 0, -3);
            $this->assertStringContainsString("customElements.get('$tag')", $src,
                "$naam registreert zonder guard; een pagina die het bestand ook los laadt valt dan om");
        }
    }
}
