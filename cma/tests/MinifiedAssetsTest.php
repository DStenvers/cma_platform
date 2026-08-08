<?php
/**
 * Guards that the platform never releases a CSS/JS file without a current .min sibling.
 *
 * Run with: php cma/tests/TestRunner.php MinifiedAssetsTest
 *
 * The serving layer (minify.php, rino_serve_minified_head) falls back to the full source
 * whenever the .min is missing or older. That is the safe choice, and a silent one: a
 * stylesheet that quietly stopped being minified looks exactly like one that never was.
 * Consumer sites cannot repair this themselves — terser and lightningcss live in
 * cma/node_modules, which is a dev dependency and not part of the Composer package — so a
 * stale .min shipped in a release stays stale on every site until the next release.
 *
 * Hence a gate here rather than a check at install time: this must fail before the tag.
 * Fix by running: cd cma && npm install && npm run build:minify
 *
 * Vendor trees (select2, jcrop, fineuploader) are deliberately absent: their .min files
 * are shipped by the vendor and are not ours to rebuild. The list below must stay in step
 * with JS_DIRS/CSS_DIRS in cma/tools/build-minify.sh.
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
     * Een CSS-bestand dat niet te verkleinen is, mag het bouwscript niet stilletjes doden.
     *
     * build-minify.sh draait onder `set -e`. De CSS-lus riep de node-wrapper KAAL aan, met
     * 2>/dev/null erachter: brak die af (lightningcss aanwezig maar zonder zijn native
     * binding — een optionele dependency die bij een install op een ander platform wordt
     * overgeslagen), dan stierf het hele script op die eerste regel. Geen melding, geen
     * samenvatting, exit 3, en elke .min.css bleef achter zoals hij was. Op de site waar dat
     * gebeurde bleven de bundels maanden verouderd zonder dat iemand het zag: de
     * serveerlaag valt bij een oude .min netjes terug op de bron.
     *
     * De JS-lus deed het al goed (`if "$TERSER" …; then`). Deze test bewaakt dat de CSS-lus
     * dat ook doet, en dat de reden van de mislukking in beeld komt.
     */
    public function testCssLusOverleeftEenMislukteMinify(): void
    {
        $script = $this->platformRoot() . '/cma/tools/build-minify.sh';
        $this->assertTrue(is_file($script), 'cma/tools/build-minify.sh is weg');
        $sh = (string) file_get_contents($script);

        $this->assertTrue(strpos($sh, 'set -e') !== false,
            'set -e is weg — dan meet deze test iets anders dan waarvoor hij bedoeld is');

        // De aanroep van de CSS-wrapper moet in een voorwaarde staan, niet kaal.
        $this->assertTrue(
            (bool) preg_match('/if\s*!\s*css_fout=\$\("\$NODE_BIN"\s*"\$CSS_WRAPPER"/', $sh),
            'de CSS-wrapper wordt kaal aangeroepen; onder set -e neemt één mislukt bestand '
            . 'het hele bouwproces mee vóórdat de ERROR-tak iets kan melden'
        );
        // En de reden moet zichtbaar zijn: 2>/dev/null gooit precies de regel weg die
        // vertelt wat er moet gebeuren ("cd cma && npm install").
        $this->assertTrue(strpos($sh, '"$CSS_WRAPPER" "$cssfile" "$minfile.tmp" 2>/dev/null') === false,
            'de fout van de CSS-wrapper gaat naar /dev/null — dan staat er alleen "ERROR: x.css"');
        $this->assertTrue(strpos($sh, 'ERROR: $basename${css_fout') !== false,
            'de ERROR-regel toont de reden van de mislukking niet');
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
