<?php
/**
 * lib-table sorteert een datumkolom op de datum zelf (library/webcomponents/lib-table.js: _isDateSortColumn, _dateSortKey, _sort).
 * Statische controle op de bron en de gebouwde .min.js; het gedrag (24 november 2026, 3-1-2027, 2026-02-01 14:30, lege cellen
 * achteraan, tekstkolommen ongemoeid) is in Chromium nagelopen.
 *
 * Run with: php tests/TestRunner.php LibTableDateSortTest
 */

require_once __DIR__ . '/TestRunner.php';

class LibTableDateSortTest extends TestCase
{
    private function lees(string $bestand): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/library/webcomponents/' . $bestand);
    }

    public function testSorteerkolommenWordenHerkend(): void
    {
        $js = $this->lees('lib-table.js');
        $this->assertStringContainsString('_isDateSortColumn(column)', $js);
        $this->assertStringContainsString("type === 'date' || type === 'datetime'", $js);
        $this->assertStringContainsString('if (type) return false;', $js);
        $this->assertStringContainsString('this._dateSortKey(waarde) === null', $js);
    }

    public function testSorterenGebeurtOpDeDatum(): void
    {
        $js = $this->lees('lib-table.js');
        $this->assertStringContainsString('if (this._isDateSortColumn(column)) {', $js);
        $this->assertStringContainsString('a.k === null ? 1 : -1', $js);   // zonder datum altijd achteraan
        $this->assertStringContainsString('LibTable._maanden = {', $js);
        $this->assertStringContainsString("isDateColumn || this._isDateSortColumn(column) ? ['Oud - Nieuw', 'Nieuw - Oud']", $js);
    }

    public function testMinBestandIsBijgewerkt(): void
    {
        $this->assertStringContainsString('_maanden', $this->lees('lib-table.min.js'));
    }

    public function testDatumsSchrijfwijzenHerkenning(): void
    {
        // Dezelfde patronen als _dateSortKey (dag-maand-jaar, jaar-maand-dag, dag maandnaam jaar).
        $tijd = '(?:[ t,]+(?:om\s+)?(\d{1,2})[:.](\d{2})(?::(\d{2}))?(?:\s*u(?:ur)?)?)?';
        $nlnum = '#^(?:[a-z]+\.?\s+)?(\d{1,2})[-/.](\d{1,2})[-/.](\d{4}|\d{2})' . $tijd . '$#';
        $iso = '#^(\d{4})-(\d{2})-(\d{2})' . $tijd . '$#';
        $naam = '#^(?:[a-z]+\.?\s+)?(\d{1,2})\s+([a-z]+)\.?\s+(\d{4})' . $tijd . '$#';
        foreach ([[$nlnum, '24-11-2026'], [$nlnum, '3-1-27'], [$nlnum, '24-11-2026 14:30'], [$iso, '2026-11-24'], [$iso, '2026-02-01 14:30'],
            [$naam, '24 november 2026'], [$naam, 'wo 4 november 2026'], [$naam, '1 jan. 2026']] as [$re, $tekst]) {
            $this->assertTrue((bool) preg_match($re, $tekst), $tekst);
        }
        foreach (['12', '2026', 'tekst', '12-11', '09:30', '1.234,56'] as $tekst) {
            $this->assertFalse((bool) preg_match($nlnum, $tekst) || (bool) preg_match($iso, $tekst) || (bool) preg_match($naam, $tekst), $tekst);
        }
    }
}
