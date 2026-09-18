<?php
/**
 * FormDataProvider::comboIdAndText() — an option's id and text come from the
 * columns the definition names, not from column positions. Regression: a
 * combo query that put a status column first ("Actief" for sorting) produced
 * options whose id was "Actief" and whose text was the id.
 *
 *   php cma/tests/TestRunner.php ComboIdAndTextTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/FormDataProvider.php';

use Cma\FormDataProvider;

class ComboIdAndTextTest extends TestCase
{
    public function testNamedColumnsWinOverPosition(): void
    {
        $row = ['Actief' => 'Actief', 'ID' => 102, 'descr' => 'KP KBT 2026'];
        $this->assertSame(['102', 'KP KBT 2026'], FormDataProvider::comboIdAndText($row, 'ID', 'Descr'));
    }

    public function testLookupIsCaseInsensitive(): void
    {
        $row = ['id' => 7, 'NAAM' => 'Zeven'];
        $this->assertSame(['7', 'Zeven'], FormDataProvider::comboIdAndText($row, 'ID', 'naam'));
    }

    public function testFallsBackToTheFirstTwoColumnsWhenNamesAreAbsent(): void
    {
        $row = ['x' => 3, 'y' => 'Drie', 0 => 3, 1 => 'Drie'];
        $this->assertSame(['3', 'Drie'], FormDataProvider::comboIdAndText($row, 'ID', 'Naam'));
    }

    public function testSingleColumnUsesItForBoth(): void
    {
        $this->assertSame(['NL', 'NL'], FormDataProvider::comboIdAndText(['code' => 'NL'], 'ID', 'Naam'));
    }

    public function testRowWithoutIdIsSkipped(): void
    {
        $this->assertSame([null, ''], FormDataProvider::comboIdAndText(['ID' => null, 'Naam' => 'x'], 'ID', 'Naam'));
    }
}
