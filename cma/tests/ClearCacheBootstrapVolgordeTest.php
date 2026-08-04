<?php
/**
 * ClearCacheBootstrapVolgordeTest.php — de bootstrap vóór de eerste uitvoer.
 *
 * De cache-tool schrijft bewust vroeg een "even geduld"-melding weg en sluit
 * daarvoor élke output-buffer (ob_end_flush + flush). Vanaf dat moment zijn de
 * headers verstuurd. Stond het laden van de bootstrap daarná, dan kwam het
 * sessieblok in _bootstrap.php aan de beurt met de headers al weg:
 *
 *   Warning: session_save_path(): Session save path cannot be changed after
 *   headers have already been sent in ..._bootstrap.php on line 172
 *
 * plus dezelfde melding voor session_set_cookie_params, session_cache_limiter en
 * session_start. En erger dan de melding: de sessie van dat verzoek belandde op
 * het standaardpad in plaats van de sessiemap van de site, en de tool telde en
 * ruimde daardoor de verkeerde map op.
 *
 * Dit is een broncontrole, geen gedragstest: de tool heeft een draaiende site
 * nodig. De vololgorde is wél vast te leggen, en die is hier het hele punt.
 *
 *   php tests/TestRunner.php ClearCacheBootstrapVolgordeTest
 */

require_once __DIR__ . '/TestRunner.php';

class ClearCacheBootstrapVolgordeTest extends TestCase
{
    private function bron(): string
    {
        $pad = __DIR__ . '/../tools/tools_clearcache.php';
        $this->assertTrue(is_file($pad), 'tools_clearcache.php niet gevonden');
        return (string) file_get_contents($pad);
    }

    /** Positie van de eerste uitvoer: de echo van de bezig-melding. */
    private function positieEersteUitvoer(string $bron): int
    {
        $pos = strpos($bron, "echo '<link rel=\"stylesheet\"");
        $this->assertTrue($pos !== false, 'de bezig-melding is verdwenen — pas deze test aan');
        return (int) $pos;
    }

    private function positieBootstrap(string $bron): int
    {
        $pos = strpos($bron, "require_once __DIR__ . '/../bootstrap.inc';");
        $this->assertTrue($pos !== false, 'de tool laadt de bootstrap niet meer');
        return (int) $pos;
    }

    public function testBootstrapWordtGeladenVoorDeEersteUitvoer(): void
    {
        $bron = $this->bron();
        $this->assertTrue(
            $this->positieBootstrap($bron) < $this->positieEersteUitvoer($bron),
            'bootstrap.inc moet vóór de eerste echo staan, anders draait het sessieblok '
            . 'met de headers al verstuurd (vier waarschuwingen bij elke cache-leging)'
        );
    }

    public function testBootstrapWordtGeladenVoorDeBuffersWordenGesloten(): void
    {
        $bron = $this->bron();
        $posFlush = strpos($bron, 'while (ob_get_level() > 0)');
        $this->assertTrue($posFlush !== false, 'de buffer-flush is verdwenen — pas deze test aan');
        $this->assertTrue(
            $this->positieBootstrap($bron) < (int) $posFlush,
            'de buffers worden gesloten vóórdat de bootstrap geladen is; daarna kan de '
            . 'sessie niet meer ingesteld worden'
        );
    }

    public function testDeBootstrapWordtMaarOpEenPlekGeladen(): void
    {
        $bron = $this->bron();
        $this->assertEquals(
            1,
            substr_count($bron, "require_once __DIR__ . '/../bootstrap.inc';"),
            'twee plekken die de bootstrap laden — dan is niet meer te zien welke de eerste is'
        );
    }

    public function testSessiemapWordtPasNaDeBootstrapBepaald(): void
    {
        $bron = $this->bron();
        // ini_get('session.save_path') geeft vóór de bootstrap het standaardpad terug
        // (Windows TEMP), niet de sessiemap van de site. De laatste toewijzing moet
        // dus ná het laden van de bootstrap staan.
        $laatste = strrpos($bron, "\$_sessionDir = ini_get('session.save_path')");
        $this->assertTrue($laatste !== false, 'de sessiemap wordt niet meer bepaald');
        $this->assertTrue(
            (int) $laatste > $this->positieBootstrap($bron),
            'de sessiemap wordt bepaald vóór de bootstrap — dan telt en ruimt de tool de '
            . 'verkeerde map op'
        );
    }
}
