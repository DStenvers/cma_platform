<?php

namespace App\Library;

/**
 * Table Helper Class
 *
 * Provides utilities for rendering HTML tables from various data sources
 */
class Table
{
    /**
     * Display a RecordSet as an HTML table
     *
     * @param RecordSet $rs The recordset to display
     * @param array $options Optional display options:
     *                       - 'id' => string: Table ID attribute
     *                       - 'class' => string: Table CSS class(es)
     *                       - 'cellspacing' => int: Cell spacing
     *                       - 'cellpadding' => int: Cell padding
     *                       - 'headerClass' => string: CSS class for header row
     *                       - 'evenClass' => string: CSS class for even rows
     *                       - 'oddClass' => string: CSS class for odd rows
     * @return string HTML table markup
     */
    public static function fromRecordset(RecordSet $rs, array $options = []): string
    {
        // Default options
        $id = $options['id'] ?? 'resultaat';
        $class = $options['class'] ?? 'filtering';
        $cellspacing = $options['cellspacing'] ?? 2;
        $cellpadding = $options['cellpadding'] ?? 4;
        $headerClass = $options['headerClass'] ?? '';
        $evenClass = $options['evenClass'] ?? 'even';
        $oddClass = $options['oddClass'] ?? 'odd';

        $html = '<TABLE CELLSPACING="' . $cellspacing . '" CELLPADDING="' . $cellpadding . '"';
        if ($id) {
            $html .= ' id="' . htmlspecialchars($id) . '"';
        }
        if ($class) {
            $html .= ' class="' . htmlspecialchars($class) . '"';
        }
        $html .= '>';

        // First, collect all rows into an array so we can get column names
        // and iterate multiple times if needed
        $rows = [];
        $columnNames = [];
        $useNumericKeys = false;

        $isDebug = Application::get('omgeving', 'P') !== 'P';

        // Debug: Check initial EOF state
        if ($isDebug) {
            $debugInfo = '<!-- Table Debug: EOF=' . ($rs->EOF ? 'true' : 'false') . ' -->';
        }

        $loopCount = 0;
        while (!$rs->EOF) {
            $loopCount++;
            $row = $rs->fields;  // Use fields property for current row data

            // Debug first row
            if ($isDebug && $loopCount === 1) {
                $debugInfo .= PHP_EOL . '<!-- First row type: ' . gettype($row) . ' -->';
                if (is_array($row)) {
                    $debugInfo .= PHP_EOL . '<!-- First row keys: ' . implode(', ', array_map(function($k) {
                        return '(' . gettype($k) . ')' . $k;
                    }, array_keys($row))) . ' -->';
                    $debugInfo .= PHP_EOL . '<!-- First row empty: ' . (empty($row) ? 'yes' : 'no') . ' -->';
                }
            }

            if (is_array($row) && !empty($row)) {
                // Get column names from first row
                if (empty($columnNames)) {
                    foreach ($row as $key => $value) {
                        // Check for string keys (column names)
                        if (is_string($key)) {
                            $columnNames[] = $key;
                        }
                    }
                    // If no string keys found, use numeric indices
                    if (empty($columnNames)) {
                        $useNumericKeys = true;
                        $columnNames = array_keys($row);
                    }
                }
                $rows[] = $row;
            }
            $rs->MoveNext();
        }

        if ($isDebug) {
            $debugInfo .= '<!-- Loop count: ' . $loopCount . ', Rows collected: ' . count($rows) . ', Columns: ' . count($columnNames) . ' -->';
            $html .= $debugInfo;
        }

        // Output header row
        $html .= '<thead><TR valign="top"';
        if ($headerClass) {
            $html .= ' class="' . htmlspecialchars($headerClass) . '"';
        }
        $html .= '>';
        foreach ($columnNames as $idx => $colName) {
            $displayName = $useNumericKeys ? 'Column ' . ($idx + 1) : $colName;
            $html .= '<TH>' . htmlspecialchars((string)$displayName) . '</TH>';
        }
        $html .= '</TR></thead>';

        // Output data rows
        $html .= '<tbody>';
        $rowCount = 0;
        foreach ($rows as $row) {
            $rowCount++;
            $rowClass = ($rowCount % 2 == 0) ? $evenClass : $oddClass;
            $html .= '<TR valign="top"';
            if ($rowClass) {
                $html .= ' class="' . htmlspecialchars($rowClass) . '"';
            }
            $html .= '>';

            foreach ($columnNames as $colName) {
                $value = $row[$colName] ?? null;
                if (is_null($value)) {
                    $html .= '<TD>&nbsp;</TD>';
                } else {
                    $html .= '<TD>' . htmlspecialchars((string)$value) . '</TD>';
                }
            }

            $html .= '</TR>';
        }
        $html .= '</tbody></TABLE>';
        $html .= '<br>' . $rowCount . ' record' . ($rowCount != 1 ? 's' : '') . '<br>&nbsp;';

        return $html;
    }

    /**
     * Display an array as an HTML table
     *
     * @param array $data Array of associative arrays (rows)
     * @param array $options Optional display options (same as fromRecordset)
     * @return string HTML table markup
     */
    public static function fromArray(array $data, array $options = []): string
    {
        // Default options
        $id = $options['id'] ?? 'resultaat';
        $class = $options['class'] ?? 'filtering';
        $cellspacing = $options['cellspacing'] ?? 2;
        $cellpadding = $options['cellpadding'] ?? 4;
        $headerClass = $options['headerClass'] ?? '';
        $evenClass = $options['evenClass'] ?? 'even';
        $oddClass = $options['oddClass'] ?? 'odd';

        $html = '<TABLE CELLSPACING="' . $cellspacing . '" CELLPADDING="' . $cellpadding . '"';
        if ($id) {
            $html .= ' id="' . htmlspecialchars($id) . '"';
        }
        if ($class) {
            $html .= ' class="' . htmlspecialchars($class) . '"';
        }
        $html .= '>';

        // Get column names from first row
        $columnNames = [];
        if (!empty($data) && is_array($data[0])) {
            foreach ($data[0] as $key => $value) {
                if (is_string($key)) {
                    $columnNames[] = $key;
                }
            }
        }

        // Output header row
        $html .= '<thead><TR valign="top"';
        if ($headerClass) {
            $html .= ' class="' . htmlspecialchars($headerClass) . '"';
        }
        $html .= '>';
        foreach ($columnNames as $colName) {
            $html .= '<TH>' . htmlspecialchars($colName) . '</TH>';
        }
        $html .= '</TR></thead>';

        // Output data rows
        $html .= '<tbody>';
        $rowCount = 0;
        foreach ($data as $row) {
            $rowCount++;
            $rowClass = ($rowCount % 2 == 0) ? $evenClass : $oddClass;
            $html .= '<TR valign="top"';
            if ($rowClass) {
                $html .= ' class="' . htmlspecialchars($rowClass) . '"';
            }
            $html .= '>';

            if (is_array($row)) {
                foreach ($columnNames as $colName) {
                    $value = $row[$colName] ?? null;
                    if (is_null($value)) {
                        $html .= '<TD>&nbsp;</TD>';
                    } else {
                        $html .= '<TD>' . htmlspecialchars((string)$value) . '</TD>';
                    }
                }
            }

            $html .= '</TR>';
        }
        $html .= '</tbody></TABLE>';
        $html .= '<br>' . $rowCount . ' record' . ($rowCount != 1 ? 's' : '') . '<br>&nbsp;';

        return $html;
    }
}
