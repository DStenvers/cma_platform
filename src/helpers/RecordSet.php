<?php

namespace App\Library;

use PDO;
use PDOException;

/**
 * RecordSet - PDO Wrapper for ADO Recordset Compatibility
 *
 * This class wraps a PDOStatement to emulate ADO recordset behavior,
 * allowing converted ASP code to work with minimal changes.
 *
 * Usage:
 *   $rs = null;
 *   lib_openRS($rs, "SELECT * FROM users", $conn, PDO::CURSOR_FWDONLY);
 *
 *   while (!$rs->EOF) {
 *       echo $rs->Fields['name'];
 *       $rs->MoveNext();
 *   }
 *   $rs->Close();
 */

class RecordSet implements \ArrayAccess, \IteratorAggregate {
    /**
     * @var PDOStatement The underlying PDO statement
     */
    private $stmt;

    /**
     * @var array|false The current row data
     */
    private $current_row = null;

    /**
     * @var bool End of file flag
     */
    private $eof = true;

    /**
     * @var int Current row position (0-based)
     */
    private $position = -1;

    /**
     * @var array All rows (only loaded if needed for scrolling)
     */
    private $all_rows = null;

    /**
     * @var bool Whether this is a scrollable cursor
     */
    private $scrollable = false;

    /**
     * @var bool Whether data is from an array (ODBC mode)
     */
    private $arrayMode = false;

    /**
     * Constructor
     *
     * @param PDOStatement|ArrayIterator $stmt The PDO statement or ArrayIterator to wrap
     * @param bool $scrollable Whether this is a scrollable cursor
     * @param bool $arrayMode Whether data comes from an array (ODBC native mode)
     */
    public function __construct($stmt, $scrollable = false, $arrayMode = false) {
        $this->stmt = $stmt;
        $this->scrollable = $scrollable;
        $this->arrayMode = $arrayMode;

        // If array mode (ODBC), data is already in the ArrayIterator
        if ($arrayMode) {
            // Convert ArrayIterator to array for easy access
            $this->all_rows = iterator_to_array($stmt);
            $this->eof = (count($this->all_rows) == 0);
            if (!$this->eof) {
                $this->position = 0;
                $this->current_row = $this->all_rows[0];
            }
        } elseif ($scrollable) {
            // If scrollable, fetch all rows immediately
            // Use FETCH_BOTH for ADO compatibility (numeric and associative keys)
            $this->all_rows = $this->stmt->fetchAll(PDO::FETCH_BOTH);
            $this->eof = (count($this->all_rows) == 0);
            if (!$this->eof) {
                $this->position = 0;
                $this->current_row = $this->all_rows[0];
            }
        } else {
            // For forward-only, load first row
            $this->MoveNext();
        }
    }

    /**
     * Move to the next record
     *
     * This is the primary method for iterating through results
     */
    public function MoveNext() {
        if ($this->arrayMode || $this->scrollable) {
            // Array mode (ODBC) or scrollable cursor - move through cached rows
            $this->position++;
            if ($this->all_rows !== null && $this->position < count($this->all_rows)) {
                $this->current_row = $this->all_rows[$this->position];
                $this->eof = false;
            } else {
                $this->current_row = false;
                $this->eof = true;
            }
        } else {
            // Forward-only cursor - fetch next row from PDO
            $this->current_row = $this->stmt->fetch(PDO::FETCH_ASSOC);
            $this->eof = ($this->current_row === false);
            if (!$this->eof) {
                $this->position++;
            }
        }
    }

    /**
     * Move to the first record (scrollable cursors only)
     */
    public function MoveFirst() {
        if (!$this->scrollable) {
            throw new \Exception("MoveFirst() requires a scrollable cursor");
        }
        if (count($this->all_rows) > 0) {
            $this->position = 0;
            $this->current_row = $this->all_rows[0];
            $this->eof = false;
        }
    }

    /**
     * Move to the last record (scrollable cursors only)
     */
    public function MoveLast() {
        if (!$this->scrollable) {
            throw new \Exception("MoveLast() requires a scrollable cursor");
        }
        $count = count($this->all_rows);
        if ($count > 0) {
            $this->position = $count - 1;
            $this->current_row = $this->all_rows[$this->position];
            $this->eof = false;
        }
    }

    /**
     * Move to the previous record (scrollable cursors only)
     */
    public function MovePrevious() {
        if (!$this->scrollable) {
            throw new \Exception("MovePrevious() requires a scrollable cursor");
        }
        if ($this->position > 0) {
            $this->position--;
            $this->current_row = $this->all_rows[$this->position];
            $this->eof = false;
        } else {
            $this->eof = true;
        }
    }

    /**
     * Close the recordset and free resources
     */
    public function Close() {
        if ($this->stmt) {
            // Only call closeCursor on PDOStatement, not on ArrayIterator
            if (method_exists($this->stmt, 'closeCursor')) {
                $this->stmt->closeCursor();
            }
            $this->stmt = null;
        }
        $this->current_row = null;
        $this->all_rows = null;
        $this->eof = true;
    }

    /**
     * Magic getter for ADO recordset properties
     *
     * @param string $name Property name
     * @return mixed Property value
     */
    public function __get($name) {
        switch (strtoupper($name)) {
            case 'EOF':
                return $this->eof;

            case 'FIELDS':
                // Return self so $rs->Fields[$name] uses ArrayAccess (handles missing keys gracefully)
                return $this;

            case 'RECORDCOUNT':
                // Only available for scrollable cursors
                if ($this->scrollable) {
                    return count($this->all_rows);
                }
                return -1; // Unknown for forward-only cursors

            default:
                // For direct field access: $rs->fieldname
                if ($this->current_row && isset($this->current_row[$name])) {
                    return $this->current_row[$name];
                }
                return null;
        }
    }

    /**
     * Magic method call for compatibility with eof() and EOF() method calls
     *
     * Handles cases where code calls $rs->eof() or $rs->EOF() as a method
     * instead of accessing $rs->EOF as a property.
     *
     * @param string $name Method name
     * @param array $arguments Method arguments
     * @return mixed Method result
     * @throws \BadMethodCallException If method is not supported
     */
    public function __call($name, $arguments) {
        // Handle eof() and EOF() method calls as property access
        if (strtoupper($name) === 'EOF') {
            return $this->eof;
        }
        throw new \BadMethodCallException("Method {$name} does not exist on RecordSet");
    }

    /**
     * Check if recordset is at end of file
     *
     * @return bool True if at EOF
     */
    public function isEOF() {
        return $this->eof;
    }

    /**
     * Get all remaining rows as array (PDO-friendly method)
     *
     * @return array All rows
     */
    public function fetchAll() {
        if ($this->scrollable) {
            return $this->all_rows;
        } else {
            // For forward-only, return remaining rows
            $rows = array();
            while (!$this->eof) {
                $rows[] = $this->current_row;
                $this->MoveNext();
            }
            return $rows;
        }
    }

    /**
     * Get all remaining rows as 2D array (ADO compatibility)
     *
     * ADO method: rs.GetRows() returns all remaining rows
     * Alias for fetchAll()
     *
     * @return array 2D array of remaining rows
     */
    public function GetRows() {
        return $this->fetchAll();
    }

    /**
     * Get current row as associative array (PDO-friendly method)
     *
     * @return array|false Current row or false if EOF
     */
    public function fetchAssoc() {
        return $this->current_row;
    }

    /**
     * IteratorAggregate: allow foreach iteration over current row fields.
     * Since $rs->fields returns $this for ArrayAccess chaining,
     * this makes foreach($rs->fields as $key => $value) work correctly.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->current_row ?: []);
    }

    /**
     * Fetch the next row from the result set (PDO-compatible method)
     *
     * This method is for PDO compatibility when code uses $rs->fetch(PDO::FETCH_ASSOC)
     *
     * @param int $fetchMode The fetch mode (default: PDO::FETCH_ASSOC)
     * @return array|false Current row or false if EOF
     */
    public function fetch($fetchMode = PDO::FETCH_ASSOC) {
        if ($this->eof) {
            return false;
        }
        $row = $this->current_row;
        $this->MoveNext();
        return $row;
    }

    // ArrayAccess implementation - allows $rs['fieldname'] syntax
    public function offsetExists($offset): bool {
        return $this->current_row && (isset($this->current_row[$offset]) || array_key_exists($offset, $this->current_row));
    }

    public function offsetGet($offset): mixed {
        if ($this->current_row) {
            // Try exact match first
            if (isset($this->current_row[$offset]) || array_key_exists($offset, $this->current_row)) {
                return $this->current_row[$offset];
            }
            // Case-insensitive fallback (Access ODBC returns field names in original case)
            if (is_string($offset)) {
                $lower = strtolower($offset);
                foreach ($this->current_row as $key => $value) {
                    if (strtolower($key) === $lower) {
                        return $value;
                    }
                }
            }
        }
        return null;
    }

    public function offsetSet($offset, $value): void {
        // RecordSet is read-only
    }

    public function offsetUnset($offset): void {
        // RecordSet is read-only
    }
}

/**
 * Open a recordset using PDO
 *
 * This function maintains compatibility with the ASP lib_openRS API
 * while using PDO underneath.
 *
 * @param RecordSet &$rs Reference to recordset variable (will be set)
 * @param string $sql SQL query to execute
 * @param PDO $conn PDO connection object
 * @param int $cursorType Cursor type (PDO::CURSOR_FWDONLY or PDO::CURSOR_SCROLL)
 * @return bool Success status
 */
function lib_openRS(&$rs, $sql, $conn, $cursorType = PDO::CURSOR_FWDONLY) {
    try {
        // Convert ADO cursor types to PDO if needed
        $scrollable = ($cursorType !== PDO::CURSOR_FWDONLY && $cursorType !== 0 && $cursorType !== 3);

        // Execute query
        $stmt = $conn->query($sql);

        if ($stmt === false) {
            throw new \Exception("Query failed: " . implode(", ", $conn->errorInfo()));
        }

        // Wrap in RecordSet object
        $rs = new RecordSet($stmt, $scrollable);

        return true;

    } catch (\PDOException $e) {
        // Log error
        error_log("lib_openRS failed: " . $e->getMessage());
        error_log("SQL: " . $sql);

        // Re-throw for error handling
        throw $e;
    } catch (\Exception $e) {
        error_log("lib_openRS failed: " . $e->getMessage());
        throw $e;
    }
}
