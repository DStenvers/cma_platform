<?php

namespace App\Library;

use ArrayObject;

/**
 * ColumnMajorArray - Wrapper for column-major array access
 *
 * Converts row-major database results to column-major format matching
 * VBScript/ASP GetRows() behavior where data is accessed as arr(column, row)
 *
 * Database Result (row-major):
 * [
 *   0 => ['name' => 'John', 'age' => 30],
 *   1 => ['name' => 'Jane', 'age' => 25],
 * ]
 *
 * Column-Major Access:
 * $arr['name'][0] => 'John'
 * $arr['name'][1] => 'Jane'
 * $arr['age'][0] => 30
 * $arr['age'][1] => 25
 *
 * Safe Access (returns null for missing keys):
 * $arr['missing'][0] => null  (no error)
 * $arr['name'][999] => null   (no error)
 */
class ColumnMajorArray extends ArrayObject
{
    /**
     * @var array Column names in order for numeric access
     */
    private array $columnOrder = [];

    /**
     * Constructor
     *
     * @param array $rowMajorData Database result in row-major format
     */
    public function __construct(array $rowMajorData = [])
    {
        // Transpose row-major to column-major
        $columnMajorData = $this->transposeToColumnMajor($rowMajorData);

        // Store column order for numeric index access
        $this->columnOrder = array_keys($columnMajorData);

        // Initialize ArrayObject with column-major data
        parent::__construct($columnMajorData, ArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * Transpose row-major array to column-major
     *
     * @param array $rows Row-major data from database
     * @return array Column-major data
     */
    private function transposeToColumnMajor(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $columns = [];

        // Get column names from first row
        $firstRow = reset($rows);
        if (!is_array($firstRow)) {
            return [];
        }

        $columnNames = array_keys($firstRow);

        // Transpose: for each column, collect all row values using array_column (much faster)
        foreach ($columnNames as $columnName) {
            $columns[$columnName] = array_column($rows, $columnName);
        }

        // Legacy nested loop (replaced by array_column above)
        if (false) foreach ($columnNames as $columnName) {
            $columns[$columnName] = [];
            foreach ($rows as $rowIndex => $row) {
                $columns[$columnName][$rowIndex] = $row[$columnName] ?? null;
            }
        }

        return $columns;
    }

    /**
     * Safe array access - implements offsetGet with null default
     * Supports both column name and numeric index access
     *
     * @param mixed $key Column name or numeric index
     * @return SafeColumnArray|null Array of column values or null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        // If numeric, convert to column name
        if (is_int($key) && isset($this->columnOrder[$key])) {
            $key = $this->columnOrder[$key];
        }

        if ($this->offsetExists($key)) {
            $columnData = parent::offsetGet($key);
            // Wrap column array in SafeColumnArray for safe row access
            return new SafeColumnArray($columnData);
        }

        // Return empty SafeColumnArray for missing columns
        return new SafeColumnArray([]);
    }

    /**
     * Check if column exists
     * Supports both column name and numeric index access
     *
     * @param mixed $key Column name or numeric index
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key): bool
    {
        // If numeric, convert to column name
        if (is_int($key) && isset($this->columnOrder[$key])) {
            $key = $this->columnOrder[$key];
        }

        return parent::offsetExists($key);
    }

    /**
     * Get all column names
     *
     * @return array
     */
    public function getColumnNames(): array
    {
        return array_keys($this->getArrayCopy());
    }

    /**
     * Get number of rows
     *
     * @return int
     */
    public function getRowCount(): int
    {
        $columns = $this->getArrayCopy();
        if (empty($columns)) {
            return 0;
        }

        // Get count from first column
        $firstColumn = reset($columns);
        return is_array($firstColumn) ? count($firstColumn) : 0;
    }

    /**
     * Override count() to return row count instead of column count
     *
     * This matches VBScript behavior where UBound(arr) returns the row count
     * when arr is a 2D array from GetRows()
     *
     * @return int Number of rows
     */
    #[\ReturnTypeWillChange]
    public function count(): int
    {
        return $this->getRowCount();
    }

    /**
     * Get row at specific index (converts back to associative array)
     *
     * @param int $rowIndex
     * @return array|null
     */
    public function getRow(int $rowIndex): ?array
    {
        $columns = $this->getArrayCopy();
        if (empty($columns)) {
            return null;
        }

        $row = [];
        foreach ($columns as $columnName => $columnValues) {
            if (isset($columnValues[$rowIndex])) {
                $row[$columnName] = $columnValues[$rowIndex];
            }
        }

        return empty($row) ? null : $row;
    }
}

/**
 * SafeColumnArray - Wrapper for safe row access within a column
 *
 * Provides safe array access with null default for missing row indices
 */
class SafeColumnArray extends ArrayObject
{
    /**
     * Constructor
     *
     * @param array $columnData Column values
     */
    public function __construct(array $columnData = [])
    {
        parent::__construct($columnData);
    }

    /**
     * Safe array access - returns null for missing indices
     *
     * @param mixed $key Row index
     * @return mixed|null
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($key)
    {
        if ($this->offsetExists($key)) {
            return parent::offsetGet($key);
        }

        return null; // Safe default
    }

    /**
     * Check if row index exists
     *
     * @param mixed $key Row index
     * @return bool
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($key): bool
    {
        return parent::offsetExists($key);
    }
}
