<?php
/**
 * De knop "maximaliseren" van lib_window (div.lib_window_max::after in library/library.css) heeft geen eigen doorzichtigheid: geen opacity:.75.
 *
 *   php cma/tests/TestRunner.php WindowMaxOpacityTest
 */
require_once __DIR__ . '/TestRunner.php';

class WindowMaxOpacityTest extends TestCase
{
    private function regel(string $bestand): string
    {
        $css = (string) file_get_contents(__DIR__ . '/../../library/' . $bestand);
        preg_match('/div\.lib_window_caption div\.lib_window_max:+after\s*\{[^}]*\}/', $css, $m);
        return $m[0] ?? '';
    }

    public function testBron(): void
    {
        $r = $this->regel('library.css');
        $this->assertTrue($r !== '', 'regel gevonden');
        $this->assertTrue(!preg_match('/opacity:\s*\.75/', $r), 'geen opacity:.75');
    }

    public function testMinified(): void
    {
        $r = $this->regel('library.min.css');
        $this->assertTrue($r !== '', 'regel gevonden in library.min.css');
        $this->assertTrue(!preg_match('/opacity:\s*(\.75|0\.75)/', $r), 'geen opacity:.75 in de min');
    }
}
