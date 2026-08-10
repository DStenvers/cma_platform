<?php
/**
 * Guards that the platform never releases a CSS/JS file without a current .min sibling.
 *
 * Run with: php cma/tests/TestRunner.php MinifiedAssetsTest
 *
 * The serving layer (minify.php, rino_serve_minified_head) falls back to the full source
 * whenever the .min is missing or older. That is the safe choice, and a silent one: a
 * stylesheet that quietly stopped being minified looks exactly like one that never was.
 * A stale .min shipped in a release stays stale on every site until someone runs the
 * build there, and nobody watches for that — so the gate belongs here, before the tag,
 * not at install time. Fix by running: cd cma && npm install && npm run build:minify
 *
 * Vendor trees (select2, jcrop, fineuploader) are deliberately absent: their .min files
 * are shipped by the vendor and are not ours to rebuild. The list below must stay in step
 * with JS_DIRS/CSS_DIRS in cma/tools/build-minify.js.
 */

require_once __DIR__ . '/TestRunner.php';

class MinifiedAssetsTest extends TestCase
{
    /** Directories the build covers, relative to the platform root. */
    private const DIRS = [
        'library',
        'library/css',
        'library/webcomponents',
        'library/assets/js',
        'cma/assets/css',
        'cma/assets/js',
        'cma/webcomponents',
    ];

    private function platformRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /** @return array<string,string> relatief pad => reden */
    private function zonderActueleMin(): array
    {
        $root = $this->platformRoot();
        $gevonden = [];

        foreach (self::DIRS as $dir) {
            $pad = $root . '/' . $dir;
            if (!is_dir($pad)) {
                continue;
            }
            foreach ((array) glob($pad . '/*.{css,js}', GLOB_BRACE) as $src) {
                if (preg_match('/\.min\.(css|js)$/i', $src)) {
                    continue;
                }
                $min = preg_replace('/\.(css|js)$/i', '.min.$1', $src);
                $rel = $dir . '/' . basename($src);
                if (!is_file($min)) {
                    $gevonden[$rel] = 'ontbreekt';
                } elseif (filemtime($min) < filemtime($src)) {
                    $gevonden[$rel] = 'ouder dan de bron';
                }
            }
        }

        return $gevonden;
    }

    public function testElkeBronHeeftEenActueleMin(): void
    {
        $gevonden = $this->zonderActueleMin();

        $uitleg = '';
        foreach ($gevonden as $rel => $reden) {
            $uitleg .= "\n    - $rel ($reden)";
        }

        $this->assertEquals(
            [],
            $gevonden,
            'Deze bestanden gaan onverkleind de deur uit; bouw ze met "cd cma && npm run build:minify":' . $uitleg
        );
    }

    /**
     * Een bestand dat niet te verkleinen is, mag de rest van de build niet meenemen.
     *
     * Een minifier faalt op één bestand om redenen die niets met de andere te maken
     * hebben — lightningcss dat wel in node_modules staat maar zonder zijn native
     * binding (een optionele dependency die bij een install op een ander platform wordt
     * overgeslagen), of een bron met een syntaxfout. Breekt de build daar af, dan blijft
     * elke .min erachter staan zoals hij was, en dat is onzichtbaar: de serveerlaag valt
     * bij een oude .min netjes terug op de bron. Het bouwscript moet dus per bestand
     * falen, met de reden erbij, en doorlopen.
     */
    public function testEenMisluktBestandStoptDeBuildNiet(): void
    {
        $script = $this->platformRoot() . '/cma/tools/build-minify.js';
        $this->assertTrue(is_file($script), 'cma/tools/build-minify.js is weg');
        $js = (string) file_get_contents($script);

        // Beide minifiers staan in een try/catch, niet kaal.
        foreach (['terser.minify', 'lightningcss.transform'] as $call) {
            $this->assertTrue(
                (bool) preg_match('/try\s*\{[^}]*' . preg_quote($call, '/') . '/s', $js),
                "$call wordt buiten een try/catch aangeroepen; dan neemt één mislukt "
                . 'bestand de hele build mee'
            );
        }
        // De reden komt in beeld — zonder e.message staat er alleen "ERROR: x.css".
        $this->assertTrue(
            substr_count($js, "' — ' + e.message") >= 2,
            'de ERROR-regel toont de reden van de mislukking niet'
        );
        // En de lus gaat verder in plaats van te stoppen.
        $this->assertTrue(
            substr_count($js, 'stats.errors++') >= 2 && substr_count($js, 'continue;') >= 2,
            'na een fout stopt de build in plaats van door te lopen met de rest'
        );
        // Het aantal fouten is de exitcode, zodat een buildstap erop kan reageren.
        $this->assertTrue(strpos($js, 'process.exit(stats.errors)') !== false,
            'de exitcode telt de fouten niet, dus een kapotte build lijkt geslaagd');
    }

    public function testDeGecontroleerdeMappenBestaan(): void
    {
        // Een hernoemde map zou de controle hierboven stilzwijgend leegmaken.
        foreach (self::DIRS as $dir) {
            $this->assertTrue(
                is_dir($this->platformRoot() . '/' . $dir),
                "map $dir bestaat nog (anders controleert deze test niets meer)"
            );
        }
    }
}
