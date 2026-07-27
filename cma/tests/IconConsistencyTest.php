<?php
/**
 * IconConsistencyTest.php — één icoonnaam, één glyph.
 *
 * De CMA en de front-end laden verschillende stylesheets: de CMA krijgt zijn
 * `content:`-regels uit cma/assets/css/*.css, de front-end uit
 * library/css/linearicons.css. Niets dwingt af dat beide dezelfde codepoint
 * kiezen, en dat liep uiteen: lnr-trash was \e681 in de CMA en \e680 op de
 * front-end — twee verschillende prullenbakken in hetzelfde product, wat niemand
 * opmerkt tot je de schermen naast elkaar legt.
 *
 *   php tests/TestRunner.php IconConsistencyTest
 */

require_once __DIR__ . '/TestRunner.php';

class IconConsistencyTest extends TestCase
{
    /** Stylesheets die een .lnr-<naam>::before { content } mogen definiëren. */
    private const SHEETS = [
        '/library/css/linearicons.css',
        '/library/library.css',
        '/library/css/lib-components.css',
        '/cma/assets/css/style.css',
        '/cma/assets/css/form.css',
        '/cma/assets/css/main.css',
        '/cma/assets/css/inline-edit.css',
    ];

    /** @return array<string, array<string, string>> naam => [codepoint => bestand] */
    private function iconDefinitions(): array
    {
        $root = dirname(__DIR__, 2);
        // Zelfde patroon als de icoon-font build (tools/build-icon-font.py), incl.
        // ruimte voor `content : "\e6b2"` zoals style.css het schrijft.
        $rx = '/\.lnr-([a-z0-9-]+)\s*::?before\s*\{[^}]*?content\s*:\s*"\\\\([0-9a-fA-F]{3,6})"/i';

        $found = [];
        foreach (self::SHEETS as $relative) {
            $path = $root . $relative;
            if (!is_file($path)) {
                continue;
            }
            if (preg_match_all($rx, (string) file_get_contents($path), $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $name = strtolower($match[1]);
                    $code = strtolower($match[2]);
                    $found[$name][$code] = ltrim($relative, '/');
                }
            }
        }
        return $found;
    }

    /**
     * Namen die vandaag nog uiteenlopen tussen CMA en front-end. Dit is schuld,
     * geen ontwerp: dezelfde klasse toont een ander plaatje afhankelijk van waar
     * je kijkt. De lijst staat hier zodat NIEUWE afwijkingen wél falen; een
     * regel weghalen is precies de handeling die er hoort te gebeuren zodra
     * iemand kiest welke glyph wint.
     */
    private const KNOWN_DIVERGENCE = ['sun', 'eye', 'delete', 'refresh'];

    public function testEveryIconNameResolvesToOneGlyph(): void
    {
        $conflicts = [];
        foreach ($this->iconDefinitions() as $name => $codes) {
            if (count($codes) > 1 && !in_array($name, self::KNOWN_DIVERGENCE, true)) {
                $parts = [];
                foreach ($codes as $code => $sheet) {
                    $parts[] = '\\' . $code . ' (' . $sheet . ')';
                }
                $conflicts[] = 'lnr-' . $name . ': ' . implode(' vs ', $parts);
            }
        }
        $this->assertEquals(
            [],
            $conflicts,
            "Dezelfde icoonnaam wijst naar verschillende glyphs:\n  " . implode("\n  ", $conflicts)
        );
    }

    /** De schuldlijst mag alleen krimpen, nooit stilletjes groeien. */
    public function testKnownDivergenceListIsStillAccurate(): void
    {
        $definitions = $this->iconDefinitions();
        $stale = [];
        foreach (self::KNOWN_DIVERGENCE as $name) {
            if (count($definitions[$name] ?? []) <= 1) {
                $stale[] = 'lnr-' . $name;
            }
        }
        $this->assertEquals([], $stale,
            'deze namen lopen niet meer uiteen — haal ze uit KNOWN_DIVERGENCE: ' . implode(', ', $stale));
    }

    public function testTrashUsesTheChosenGlyph(): void
    {
        // Bewuste keuze: \e681 leest duidelijker als prullenbak dan \e680.
        $definitions = $this->iconDefinitions();
        $this->assertTrue(isset($definitions['trash']), 'lnr-trash moet ergens gedefinieerd zijn');
        $this->assertEquals(['e681'], array_keys($definitions['trash']), 'lnr-trash hoort \\e681 te zijn');
    }

    public function testDefinedIconsExistInTheShippedFont(): void
    {
        $font = dirname(__DIR__, 2) . '/library/fonts/Linearicons.ttf';
        if (!is_file($font)) {
            $this->markTestSkipped('Linearicons.ttf niet gevonden');
        }
        $available = $this->fontCodepoints($font);
        if ($available === []) {
            $this->markTestSkipped('kon de cmap van het font niet lezen');
        }

        // Alleen de namen die de CMA/front-end echt in markup gebruiken: de
        // referentielijst dekt 1002 iconen en het meegeleverde font is bewust
        // een subset daarvan.
        $missing = [];
        foreach ($this->usedIconNames() as $name) {
            $definitions = $this->iconDefinitions()[$name] ?? null;
            if ($definitions === null) {
                continue; // geen content-regel: aparte zorg, niet deze test
            }
            $code = hexdec((string) array_key_first($definitions));
            if (!isset($available[$code])) {
                $missing[] = 'lnr-' . $name . ' (\\' . dechex($code) . ')';
            }
        }
        $this->assertEquals([], $missing, 'gebruikte iconen ontbreken in het subset-font: ' . implode(', ', $missing));
    }

    /** Icoonnamen die daadwerkelijk in markup/JS gebruikt worden. */
    private function usedIconNames(): array
    {
        $root = dirname(__DIR__, 2);
        $names = [];
        foreach (['/cma/webcomponents', '/library/webcomponents', '/library/assets/js'] as $dir) {
            $path = $root . $dir;
            if (!is_dir($path)) {
                continue;
            }
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
                if (!$file->isFile() || !str_ends_with($file->getFilename(), '.js') || str_contains($file->getFilename(), '.min.')) {
                    continue;
                }
                if (preg_match_all('/lnr-([a-z0-9]+(?:-[a-z0-9]+)*)/', (string) file_get_contents($file->getPathname()), $m)) {
                    $names = array_merge($names, $m[1]);
                }
            }
        }
        return array_values(array_unique($names));
    }

    /** @return array<int,true> codepoints in de cmap van het font */
    private function fontCodepoints(string $path): array
    {
        $data = (string) file_get_contents($path);
        $numTables = unpack('n', substr($data, 4, 2))[1];
        $cmapOffset = null;
        for ($i = 0; $i < $numTables; $i++) {
            $rec = 12 + 16 * $i;
            if (substr($data, $rec, 4) === 'cmap') {
                $cmapOffset = unpack('N', substr($data, $rec + 8, 4))[1];
            }
        }
        if ($cmapOffset === null) {
            return [];
        }
        $subtables = unpack('n', substr($data, $cmapOffset + 2, 2))[1];
        $best = null;
        for ($i = 0; $i < $subtables; $i++) {
            $p = $cmapOffset + 4 + 8 * $i;
            $platform = unpack('n', substr($data, $p, 2))[1];
            $encoding = unpack('n', substr($data, $p + 2, 2))[1];
            $offset = unpack('N', substr($data, $p + 4, 4))[1];
            if (($platform === 3 && in_array($encoding, [1, 10], true)) || ($platform === 0)) {
                $best = $cmapOffset + $offset;
            }
        }
        if ($best === null || unpack('n', substr($data, $best, 2))[1] !== 4) {
            return [];
        }
        $segX2 = unpack('n', substr($data, $best + 6, 2))[1];
        $segs = (int) ($segX2 / 2);
        $ends = array_values(unpack('n*', substr($data, $best + 14, $segX2)));
        $starts = array_values(unpack('n*', substr($data, $best + 16 + $segX2, $segX2)));

        $codepoints = [];
        foreach ($starts as $i => $start) {
            $end = $ends[$i];
            if ($end === 0xFFFF) {
                continue;
            }
            for ($c = $start; $c <= $end; $c++) {
                $codepoints[$c] = true;
            }
        }
        return $codepoints;
    }
}
