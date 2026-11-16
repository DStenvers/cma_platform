<?php
/**
 * Het icoon lnr-checkmark-circle is donkergroen (darkgreen), in de CMA-stijl en in de bibliotheek-iconen.
 *
 *   php cma/tests/TestRunner.php CheckmarkCircleKleurTest
 */
require_once __DIR__ . '/TestRunner.php';

class CheckmarkCircleKleurTest extends TestCase
{
    public function testCheckmarkCircleIsDarkgreen(): int
    {
        foreach (['/assets/css/style.css', '/../library/css/linearicons.css'] as $bestand) {
            $css = (string) file_get_contents(dirname(__DIR__) . $bestand);
            $this->assertTrue((bool) preg_match('~\.lnr-checkmark-circle::before\s*\{[^}]*color:\s*darkgreen~', $css), basename($bestand) . ': .lnr-checkmark-circle::before is darkgreen');
        }
        return 0;
    }
}
