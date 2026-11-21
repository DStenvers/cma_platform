<?php
/**
 * Icons drawn with <i class="lnr lnr-...">: an <i> is italic by default and the ::before pseudo-element inherits that, so the icon-font glyphs (for example the up/down
 * chevrons of the lib-table field chooser) came out slanted. The base rule .lnr::before in cma/assets/css/style.css therefore sets font-style: normal.
 *
 *   php cma/tests/TestRunner.php LnrIconItalicTest
 */
require_once __DIR__ . '/TestRunner.php';

class LnrIconItalicTest extends TestCase
{
    public function testBaseRuleResetsFontStyle(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/style.css');
        $this->assertTrue(preg_match('/\.lnr::before\s*\{[^}]*font-style:\s*normal/', $css) === 1, '.lnr::before sets font-style: normal');
    }

    public function testMinifiedStylesheetCarriesIt(): void
    {
        $min = (string) file_get_contents(__DIR__ . '/../assets/css/style.min.css');
        $this->assertTrue(preg_match('/\.lnr:+before\s*\{[^}]*font-style:\s*normal/', $min) === 1, 'style.min.css has the same rule');
    }
}
