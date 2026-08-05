<?php
/**
 * FormConfigPerFormulierTest.php — de configuratie hoort bij het formulier.
 *
 * main.js bewaart hele pagina's: cacheCurrentPage() haalt de wrapper mét
 * .form-layout en de controller uit de DOM en zet ze later terug. Intussen heb
 * je een ánder formulier bezocht, dus window.CMA.formConfig hoort op dat moment
 * bij dát formulier.
 *
 * Wat er misging: verifyIdentity() vergeleek this.config met die globale en nam
 * bij verschil de globale over. Een teruggezette pagina kreeg zo de rechten en
 * knoppen van het laatst bezochte formulier — accessLevel, canAdd, canDelete,
 * canCopy, filterIdName, formName — en de methode schreef ook nog terug in dat
 * globale object. Een tripwire die de drift veroorzaakte die hij moest vangen.
 * Dezelfde fout stond bij de editor-instellingen, waar extraPlugins bepaalt of
 * de literatuur-plugin meekomt (dat verschilt per klant).
 *
 * Dit is een broncontrole, geen gedragstest: de controller heeft een browser
 * nodig. Welke bron hij leest is wél vast te leggen, en dat is hier het punt.
 *
 *   php tests/TestRunner.php FormConfigPerFormulierTest
 */

require_once __DIR__ . '/TestRunner.php';

class FormConfigPerFormulierTest extends TestCase
{
    private function bron(): string
    {
        $pad = __DIR__ . '/../assets/js/form-controller.js';
        $this->assertTrue(is_file($pad), 'form-controller.js niet gevonden');
        return (string) file_get_contents($pad);
    }

    /**
     * Commentaar eruit, zodat een uitleg over de oude situatie geen valse
     * treffer geeft. Strings blijven staan: een leesactie in een string is
     * alsnog een leesactie.
     */
    private function bronZonderCommentaar(): string
    {
        $bron = $this->bron();
        $bron = preg_replace('#/\*.*?\*/#s', '', $bron);
        $bron = preg_replace('#^\s*//.*$#m', '', (string) $bron);
        return (string) $bron;
    }

    private function methode(string $naam): string
    {
        $bron = $this->bronZonderCommentaar();
        $start = strpos($bron, $naam . '(');
        $this->assertTrue($start !== false, "methode {$naam}() niet gevonden");
        // Ruim genomen: het blok tot de volgende methode op klasseniveau.
        $eind = strpos($bron, "\n    }", (int) $start);
        $this->assertTrue($eind !== false, "einde van {$naam}() niet gevonden");
        return substr($bron, (int) $start, (int) $eind - (int) $start);
    }

    public function testVerifyIdentityLeestDeGlobaleConfigNiet(): void
    {
        $body = $this->methode('verifyIdentity');
        $this->assertFalse(
            strpos($body, 'formConfig') !== false,
            'verifyIdentity() raakt window.CMA.formConfig weer aan; de config van een '
            . 'teruggezette pagina wordt dan overschreven met die van het laatst bezochte formulier'
        );
    }

    public function testVerifyIdentitySchrijftNietInDeGlobaleConfig(): void
    {
        $body = $this->methode('verifyIdentity');
        $this->assertFalse(
            preg_match('/\bcfg\s*\.\s*\w+\s*=/', $body) === 1,
            'verifyIdentity() schrijft in het globale config-object van een ander formulier'
        );
    }

    public function testEditorInstellingenKomenVanHetFormulierZelf(): void
    {
        $bron = $this->bronZonderCommentaar();
        $this->assertTrue(
            strpos($bron, 'this.config?.editorConfig') !== false,
            'de editor-instellingen worden niet meer uit this.config gelezen'
        );
        $this->assertFalse(
            strpos($bron, 'CMA.formConfig.editorConfig') !== false,
            'de editor-instellingen komen weer uit de globale config; een teruggezette '
            . 'pagina krijgt dan de instellingen van het laatst bezochte formulier '
            . '(waaronder extraPlugins, dat per klant verschilt)'
        );
    }

    public function testDeControllerLeestDeGlobaleConfigNergensAlsToestand(): void
    {
        $bron = $this->bronZonderCommentaar();
        // De enige toegestane vermelding is de tekst van een foutmelding die de
        // beheerder vertelt waar hij moet kijken.
        $regels = [];
        foreach (explode("\n", $bron) as $nr => $regel) {
            if (strpos($regel, 'CMA.formConfig') === false) {
                continue;
            }
            if (strpos($regel, 'cmaLog.error') !== false) {
                continue; // foutmelding, geen leesactie
            }
            $regels[] = ($nr + 1) . ': ' . trim($regel);
        }
        $this->assertEquals(
            [],
            $regels,
            'de controller leest window.CMA.formConfig weer; die hoort bij het venster '
            . 'en niet bij dit formulier'
        );
    }
}
