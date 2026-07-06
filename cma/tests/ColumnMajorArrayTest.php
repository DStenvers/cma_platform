<?php
/**
 * Tests for App\Library\ColumnMajorArray
 *
 * Run with: php tests/TestRunner.php ColumnMajorArrayTest
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\ColumnMajorArray;

class ColumnMajorArrayTest extends TestCase
{
    private function sampleData(): array
    {
        return [
            ['name' => 'John', 'age' => 30, 'city' => 'Amsterdam'],
            ['name' => 'Jane', 'age' => 25, 'city' => 'Rotterdam'],
            ['name' => 'Bob',  'age' => 35, 'city' => 'Utrecht'],
        ];
    }

    // ========================================================================
    // Basic column-major access
    // ========================================================================

    public function testAccessByColumnName(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertEquals('John', $arr['name'][0]);
        $this->assertEquals('Jane', $arr['name'][1]);
        $this->assertEquals('Bob',  $arr['name'][2]);
    }

    public function testAccessByColumnAndRow(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertEquals(30, $arr['age'][0]);
        $this->assertEquals(25, $arr['age'][1]);
        $this->assertEquals(35, $arr['age'][2]);
    }

    public function testAccessByNumericColumnIndex(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        // Column 0 = 'name', Column 1 = 'age'
        $this->assertEquals('John', $arr[0][0]);
        $this->assertEquals(30, $arr[1][0]);
    }

    // ========================================================================
    // Safe access (no errors for missing keys)
    // ========================================================================

    public function testSafeAccessMissingColumn(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertNull($arr['missing'][0]);
    }

    public function testSafeAccessMissingRow(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertNull($arr['name'][999]);
    }

    // ========================================================================
    // Empty array
    // ========================================================================

    public function testEmptyArray(): void
    {
        $arr = new ColumnMajorArray([]);
        $this->assertEquals(0, $arr->getRowCount());
        $this->assertEquals([], $arr->getColumnNames());
    }

    // ========================================================================
    // Metadata methods
    // ========================================================================

    public function testGetColumnNames(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertEquals(['name', 'age', 'city'], $arr->getColumnNames());
    }

    public function testGetRowCount(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertEquals(3, $arr->getRowCount());
    }

    public function testGetRow(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $row = $arr->getRow(1);
        $this->assertEquals('Jane', $row['name']);
        $this->assertEquals(25, $row['age']);
        $this->assertEquals('Rotterdam', $row['city']);
    }

    public function testGetRowOutOfBounds(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertNull($arr->getRow(999));
    }

    // ========================================================================
    // Column exists check
    // ========================================================================

    public function testOffsetExists(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertTrue(isset($arr['name']));
        $this->assertFalse(isset($arr['missing']));
    }

    public function testOffsetExistsNumeric(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertTrue(isset($arr[0]));
        $this->assertTrue(isset($arr[2]));
        $this->assertFalse(isset($arr[99]));
    }

    // ========================================================================
    // isArray compatibility
    // ========================================================================

    public function testIsArrayCompatible(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        // Should be instanceof ArrayObject
        $this->assertTrue($arr instanceof \ArrayObject);
    }

    // ========================================================================
    // count() override — returns ROW count, not column count (previously uncovered)
    // ========================================================================

    public function testCountReturnsRowCount(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        // 3 rows / 3 columns — count() must return rows (VBScript UBound semantics)
        $this->assertEquals(3, count($arr));
    }

    public function testCountEmpty(): void
    {
        $arr = new ColumnMajorArray([]);
        $this->assertEquals(0, count($arr));
    }

    public function testCountSingleRow(): void
    {
        $arr = new ColumnMajorArray([['a' => 1, 'b' => 2, 'c' => 3]]);
        // 1 row, 3 columns → count is 1
        $this->assertEquals(1, count($arr));
    }

    // ========================================================================
    // numeric column index — second row and out-of-range
    // ========================================================================

    public function testNumericColumnSecondRow(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        // Column 2 = 'city'
        $this->assertEquals('Rotterdam', $arr[2][1]);
    }

    public function testNumericColumnOutOfRangeReturnsEmptySafeArray(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        // Missing numeric column falls through to name lookup (99 not a name) → empty
        $this->assertNull($arr[99][0]);
    }

    // ========================================================================
    // getRow — first and last rows, partial data
    // ========================================================================

    public function testGetRowFirst(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $row = $arr->getRow(0);
        $this->assertEquals('John', $row['name']);
        $this->assertEquals('Amsterdam', $row['city']);
    }

    public function testGetRowEmptyArray(): void
    {
        $arr = new ColumnMajorArray([]);
        $this->assertNull($arr->getRow(0));
    }

    // ========================================================================
    // Ragged rows — columns are derived from the FIRST row only
    // ========================================================================

    public function testColumnsDerivedFromFirstRow(): void
    {
        // Second row has an extra 'extra' key that the first row lacks;
        // array_column only extracts columns known from the first row.
        $arr = new ColumnMajorArray([
            ['name' => 'A'],
            ['name' => 'B', 'extra' => 'X'],
        ]);
        $this->assertEquals(['name'], $arr->getColumnNames());
        $this->assertNull($arr['extra'][1]);
    }

    // ========================================================================
    // Non-array first row → treated as empty
    // ========================================================================

    public function testScalarFirstRowYieldsEmpty(): void
    {
        $arr = new ColumnMajorArray(['scalar', 'values']);
        $this->assertEquals(0, $arr->getRowCount());
        $this->assertEquals([], $arr->getColumnNames());
    }

    // ========================================================================
    // Values are preserved by reference position (row order stable)
    // ========================================================================

    public function testRowOrderStable(): void
    {
        $arr = new ColumnMajorArray($this->sampleData());
        $this->assertEquals(['John', 'Jane', 'Bob'], iterator_to_array($arr['name']));
    }
}
