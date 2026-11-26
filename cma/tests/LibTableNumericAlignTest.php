<?php
/**
 * Getalkolommen van lib-table staan rechts uitgelijnd, kop en cellen (library/webcomponents/lib-table.js, _applyNumericAlignment,
 * en de klasse lt-num in lib-table.css). Statische controle op de bron en op de gebouwde .min-bestanden.
 *
 * Run with: php tests/TestRunner.php LibTableNumericAlignTest
 */

require_once __DIR__ . '/TestRunner.php';

class LibTableNumericAlignTest extends TestCase
{
    private function lees(string $pad): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/library/webcomponents/' . $pad);
    }

    public function testJsMarkeertKopEnCellenVanGetalkolommen(): void
    {
        $js = $this->lees('lib-table.js');
        $this->assertStringContainsString('_applyNumericAlignment()', $js);
        $this->assertStringContainsString("th.classList.toggle('lt-num', numeric)", $js);
        $this->assertStringContainsString("cells.forEach(td => td.classList.toggle('lt-num', numeric))", $js);
        // Na het opbouwen van de filters en na het herindelen van de kolommen, want de koppen worden dan opnieuw opgebouwd.
        $this->assertTrue(substr_count($js, 'this._applyNumericAlignment();') >= 2);
    }

    public function testCssLijntGetalkolommenRechtsUit(): void
    {
        $css = $this->lees('lib-table.css');
        $this->assertStringContainsString('lib-table th.lt-num', $css);
        $this->assertStringContainsString('lib-table td.lt-num', $css);
        $this->assertTrue((bool) preg_match('/th\.lt-num,[^{]*td\.lt-num[^{]*\{\s*text-align:\s*right/s', $css));
    }

    public function testKopInhoudStaatRechtsBijGetalkolommen(): void
    {
        $css = $this->lees('lib-table.css');
        $this->assertTrue((bool) preg_match('/lib-table th\.lt-num \.th-header-wrapper\s*\{\s*justify-content:\s*flex-end/', $css));
        $this->assertStringContainsString('lt-num .th-header-wrapper', $this->lees('lib-table.min.css'));
    }

    public function testMinBestandenZijnBijgewerkt(): void
    {
        $this->assertStringContainsString('lt-num', $this->lees('lib-table.min.js'));
        $this->assertStringContainsString('lt-num', $this->lees('lib-table.min.css'));
    }

    public function testGetalHerkenning(): void
    {
        // Dezelfde regex als in het JS.
        $re = '/^[-+\x{2212}]?\s*(?:[\x{20ac}$\x{00a3}]\s*)?(?:\d{1,3}(?:\.\d{3})+|\d+)(?:,\d+)?\s*%?$/u';
        foreach (['12', '1.234', '1.234,56', '-5', '€ 12,50', '45%', '0'] as $ok) {
            $this->assertTrue((bool) preg_match($re, $ok), $ok);
        }
        foreach (['12-11-2026', '09:30', '1012 AB', 'abc', '12 november 2026', '1,2,3'] as $nee) {
            $this->assertFalse((bool) preg_match($re, $nee), $nee);
        }
    }
}
