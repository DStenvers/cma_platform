<?php
/**
 * JsonFormLoaderAlleFormulierenTest — listAllForms() laat de deelformulieren wél zien.
 *
 * Draaien met: php cma/tests/TestRunner.php JsonFormLoaderAlleFormulierenTest
 *
 * listForms() slaat elke naam met een underscore over, want zo heet een deelformulier
 * (rooster_docenten) en in een formulierkeuze hoort het niet thuis. De db-sync-tool gebruikte
 * diezelfde lijst om formuliervelden naast de echte kolommen te leggen, en controleerde
 * daardoor alleen de hoofdformulieren: op mijnRINO 49 van de 134 definities. Een deelformulier
 * schrijft net zo goed naar een tabel.
 *
 * getSubforms() volstond niet als aanvulling: dat vindt alleen kinderen van een HOOFDformulier
 * en mist dus contactpersonen_inventarisatie_login, waar het middenstuk zelf al een
 * deelformulier is. Vandaar listAllForms(), die gewoon alle definities opsomt.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';

use Cma\JsonFormLoader;

class JsonFormLoaderAlleFormulierenTest extends TestCase
{
    public function testAllesBevatMinstensDeHoofdformulieren(): void
    {
        $alles = JsonFormLoader::listAllForms();
        $hoofd = JsonFormLoader::listForms();
        $this->assertTrue(count($alles) >= count($hoofd),
            'listAllForms() geeft er nooit minder dan listForms()');
        foreach ($hoofd as $naam) {
            $this->assertTrue(in_array($naam, $alles, true), "hoofdformulier $naam ontbreekt");
        }
    }

    public function testDeelformulierenZittenErInEnInListFormsNiet(): void
    {
        $alles = JsonFormLoader::listAllForms();
        $hoofd = JsonFormLoader::listForms();
        $deel = array_values(array_diff($alles, $hoofd));
        // In deze installatie zijn er deelformulieren; zonder is de test zinloos.
        $this->assertTrue(count($deel) > 0, 'er zijn deelformulieren gevonden');
        foreach ($deel as $naam) {
            $this->assertTrue(strpos($naam, '_') !== false,
                "wat listForms() weglaat heeft een underscore ($naam)");
            $this->assertFalse(in_array($naam, $hoofd, true),
                "$naam hoort niet in listForms()");
        }
    }

    public function testGeenDubbeleNamen(): void
    {
        $alles = JsonFormLoader::listAllForms();
        $this->assertSame(count($alles), count(array_unique($alles)),
            'elke naam komt één keer voor, ook als hij in twee mappen staat');
    }
}
