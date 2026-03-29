<?php
/**
 * Tests for App\Library\ColumnMajorArray
 *
 * Run with: php tests/TestRunner.php ColumnMajorArrayTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

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
}
