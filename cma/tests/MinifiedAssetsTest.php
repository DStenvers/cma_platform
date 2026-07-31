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
