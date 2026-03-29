<?php
/**
 * SQL Parser
 *
 * Parses SQL SELECT queries and extracts structure (tables, fields, joins, sorting, etc.)
 * Used by both the report-query API and unit tests.
 *
 * @package Cma
 */

namespace Cma;

class SqlParser
{
    /**
     * Parse a SQL SELECT query and extract its structure
     *
     * @param string $sql SQL query to parse
     * @return array|null Parsed structure or null if unparseable
     */
    public static function parse(string $sql): ?array
    {
        // Remove comments BEFORE normalizing whitespace
        // Line comments: -- until end of line
        $sql = preg_replace('/--[^\r\n]*/', '', $sql);
        // Block comments: /* ... */
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        // Normalize whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // Check for unsupported constructs
        $unsupported = ['UNION', 'INTERSECT', 'EXCEPT', 'WITH', 'INTO'];
        foreach ($unsupported as $keyword) {
            if (preg_match('/\b' . $keyword . '\b/i', $sql)) {
                return null; // Cannot parse complex queries
            }
        }

        $result = [
            'tables' => [],
            'joins' => [],
            'fields' => [],
            'sorting' => [],
            'grouping' => [],
            'distinct' => false,
            'topN' => null,
            'limit' => null,
            'offset' => null
        ];

        // Extract DISTINCT and DISTINCTROW
        if (preg_match('/^SELECT\s+DISTINCTROW\b/i', $sql)) {
            $result['distinct'] = 'DISTINCTROW';
        } elseif (preg_match('/^SELECT\s+DISTINCT\b/i', $sql)) {
            $result['distinct'] = true;
        }

        // Extract TOP N
        if (preg_match('/^SELECT\s+(?:DISTINCTROW\s+|DISTINCT\s+)?TOP\s+(\d+)\b/i', $sql, $topMatch)) {
            $result['topN'] = (int)$topMatch[1];
        }

        // Extract LIMIT/OFFSET
        if (preg_match('/\bLIMIT\s+(\d+)(?:\s+OFFSET\s+(\d+))?\s*$/i', $sql, $limitMatch)) {
            $result['limit'] = (int)$limitMatch[1];
            if (isset($limitMatch[2])) {
                $result['offset'] = (int)$limitMatch[2];
            }
        }

        // Extract tables from FROM clause
        $tables = self::extractTables($sql);
        if ($tables === null) {
            return null;
        }
        $result['tables'] = $tables;

        // Extract JOIN conditions from FROM clause
        $joins = self::extractJoins($sql, $tables);
        $result['joins'] = $joins;

        // Extract fields from SELECT clause
        $fields = self::extractFields($sql, $tables);
        if ($fields === null) {
            return null;
        }
        $result['fields'] = $fields;

        // Extract sorting from ORDER BY clause
        $sorting = self::extractSorting($sql);
        $result['sorting'] = $sorting ?? [];

        // Extract grouping from GROUP BY clause
        $grouping = self::extractGrouping($sql);

        // If GROUP BY is present, all non-aggregated SELECT fields must be in GROUP BY
        if (!empty($grouping) && !empty($fields)) {
            $grouping = [];
            foreach ($fields as $field) {
                $isAggregate = !empty($field['expression']) &&
                    preg_match('/\b(COUNT|SUM|AVG|MIN|MAX|FIRST|LAST)\s*\(/i', $field['field'] ?? '');

                if (!$isAggregate && !empty($field['table']) && !empty($field['field'])) {
                    $grouping[] = [
                        'table' => $field['table'],
                        'field' => $field['field']
                    ];
                }
            }
        }
        $result['grouping'] = $grouping ?? [];

        return $result;
    }

    /**
     * Extract table names from FROM and JOIN clauses
     * Handles MS Access nested JOIN syntax: FROM (([table1] JOIN [table2] ON ...) JOIN [table3] ON ...)
     */
    public static function extractTables(string $sql): ?array
    {
        $tables = [];

        // First try to match simple FROM clause (FROM [table] or FROM table)
        if (preg_match('/\bFROM\s+(\[[\w]+\]|\w+)(?:\s+(?:AS\s+)?(\w+))?/i', $sql, $fromMatch)) {
            $firstChar = $fromMatch[1][0] ?? '';
            if ($firstChar !== '(') {
                $tableName = trim($fromMatch[1], '[]');
                $tables[] = $tableName;
            }
        }

        // Check for nested parentheses pattern (MS Access multiple JOINs)
        if (preg_match('/\bFROM\s+\(/i', $sql)) {
            if (preg_match_all('/\[(\w+)\](?=\s*(?:INNER|LEFT|RIGHT|FULL|OUTER|CROSS)?\s*JOIN|\s*ON|\s*\))/i', $sql, $bracketMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($bracketMatches[1] as $match) {
                    $tableName = $match[0];
                    $offset = $match[1];

                    if ($offset > 1) {
                        $charBeforeBracket = $sql[$offset - 2];
                        if ($charBeforeBracket === '.') {
                            continue;
                        }
                    }

                    if (!in_array($tableName, $tables)) {
                        $tables[] = $tableName;
                    }
                }
            }
        }

        // Also match regular JOIN clauses (for non-nested syntax)
        if (preg_match_all('/\b(?:LEFT|RIGHT|INNER|FULL|OUTER|CROSS)?\s*JOIN\s+(\[?[\w]+\]?)(?:\s+(?:AS\s+)?(\w+))?/i', $sql, $joinMatches, PREG_SET_ORDER)) {
            foreach ($joinMatches as $match) {
                $joinTable = trim($match[1], '[]');
                if (!in_array($joinTable, $tables)) {
                    $tables[] = $joinTable;
                }
            }
        }

        // If still no tables found, try to extract all bracketed table names from FROM onwards
        if (empty($tables)) {
            if (preg_match('/\bFROM\s+(.+?)(?:\s+WHERE\b|\s+ORDER\s+BY\b|\s+GROUP\s+BY\b|$)/is', $sql, $fromSection)) {
                $fromPart = $fromSection[1];
                if (preg_match_all('/\[(\w+)\]/i', $fromPart, $allBrackets)) {
                    foreach ($allBrackets[1] as $name) {
                        if (!in_array($name, $tables)) {
                            $tables[] = $name;
                        }
                    }
                    $tables = self::filterToTableNames($fromPart, $tables);
                }
            }
        }

        return empty($tables) ? null : $tables;
    }

    /**
     * Extract JOIN conditions from the FROM clause
     * Returns array of joins with table, type, and ON condition
     */
    public static function extractJoins(string $sql, array $tables): array
    {
        $joins = [];

        // Get the FROM clause section
        if (!preg_match('/\bFROM\s+(.+?)(?:\s+WHERE\b|\s+ORDER\s+BY\b|\s+GROUP\s+BY\b|\s+HAVING\b|$)/is', $sql, $fromSection)) {
            return [];
        }

        $fromPart = $fromSection[1];

        // Pattern to match JOIN clauses with ON conditions
        // Captures multi-condition ON clauses (with AND/OR)
        $pattern = '/\b(INNER|LEFT\s+OUTER|RIGHT\s+OUTER|FULL\s+OUTER|FULL|LEFT|RIGHT|OUTER|CROSS)?\s*JOIN\s+\[?(\w+)\]?(?:\s+(?:AS\s+)?\w+)?\s+ON\s+(.+?)(?=\s*(?:INNER|LEFT|RIGHT|FULL|OUTER|CROSS)?\s*JOIN\b|\s*\)|\s*$)/is';

        if (preg_match_all($pattern, $fromPart, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $joinType = strtoupper(trim($match[1] ?? ''));
                // Normalize join types - preserve specificity
                if (empty($joinType)) {
                    $joinType = 'INNER';
                } elseif ($joinType === 'LEFT OUTER') {
                    $joinType = 'LEFT OUTER';
                } elseif ($joinType === 'RIGHT OUTER') {
                    $joinType = 'RIGHT OUTER';
                } elseif ($joinType === 'FULL OUTER') {
                    $joinType = 'FULL OUTER';
                } elseif ($joinType === 'FULL') {
                    $joinType = 'FULL OUTER';
                } elseif ($joinType === 'LEFT') {
                    $joinType = 'LEFT';
                } elseif ($joinType === 'RIGHT') {
                    $joinType = 'RIGHT';
                } elseif ($joinType === 'OUTER') {
                    $joinType = 'FULL OUTER';
                } elseif ($joinType === 'CROSS') {
                    $joinType = 'CROSS';
                } elseif ($joinType === 'INNER') {
                    $joinType = 'INNER';
                }

                $joinTable = $match[2];
                $onCondition = trim($match[3]);

                // Clean up trailing parentheses from ON condition
                $onCondition = rtrim($onCondition, ')');
                $onCondition = trim($onCondition);

                // Only add if the table is in our tables list
                $tableFound = false;
                foreach ($tables as $t) {
                    if (strcasecmp($t, $joinTable) === 0) {
                        $tableFound = true;
                        break;
                    }
                }

                if ($tableFound) {
                    $joins[] = [
                        'table' => $joinTable,
                        'type' => $joinType,
                        'on' => $onCondition
                    ];
                }
            }
        }

        return $joins;
    }

    /**
     * Extract fields from SELECT clause
     */
    public static function extractFields(string $sql, array $tables): ?array
    {
        if (!preg_match('/SELECT\s+(.*?)\s+FROM\b/is', $sql, $selectMatch)) {
            return null;
        }

        $selectPart = trim($selectMatch[1]);

        // Handle SELECT *
        if ($selectPart === '*') {
            return [];
        }

        // Handle TOP N, DISTINCT, DISTINCTROW prefixes
        $selectPart = preg_replace('/^(?:DISTINCTROW|DISTINCT)\s+/i', '', $selectPart);
        $selectPart = preg_replace('/^TOP\s+\d+\s*/i', '', $selectPart);

        // Split by commas (but not inside parentheses)
        $fields = [];
        $depth = 0;
        $current = '';

        for ($i = 0; $i < strlen($selectPart); $i++) {
            $char = $selectPart[$i];
            if ($char === '(') {
                $depth++;
                $current .= $char;
            } elseif ($char === ')') {
                $depth--;
                $current .= $char;
            } elseif ($char === ',' && $depth === 0) {
                $fields[] = trim($current);
                $current = '';
            } else {
                $current .= $char;
            }
        }
        if (!empty(trim($current))) {
            $fields[] = trim($current);
        }

        // Parse each field
        $parsedFields = [];
        foreach ($fields as $field) {
            $parsed = self::parseFieldExpression($field, $tables);
            if ($parsed !== null) {
                $parsedFields[] = $parsed;
            }
        }

        return $parsedFields;
    }

    /**
     * Parse a single field expression
     */
    public static function parseFieldExpression(string $expr, array $tables): ?array
    {
        $expr = trim($expr);

        // Check for alias (AS alias or just alias)
        $alias = null;

        // Pattern for quoted/bracketed aliases (may contain spaces)
        if (preg_match('/\bAS\s+("[^"]+"|\'[^\']+\'|\[[^\]]+\])$/i', $expr, $aliasMatch)) {
            $alias = trim($aliasMatch[1], '"\'[]');
            $alias = self::sanitizeAlias($alias);
            $expr = preg_replace('/\bAS\s+("[^"]+"|\'[^\']+\'|\[[^\]]+\])$/i', '', $expr);
            $expr = trim($expr);
        }
        // Pattern for simple word aliases (no spaces)
        elseif (preg_match('/\bAS\s+([\w]+)$/i', $expr, $aliasMatch)) {
            $alias = $aliasMatch[1];
            $expr = preg_replace('/\bAS\s+[\w]+$/i', '', $expr);
            $expr = trim($expr);
        } elseif (preg_match('/\s+([\w]+)$/i', $expr, $aliasMatch)) {
            $potentialAlias = $aliasMatch[1];
            if (!preg_match('/^[\w\.]+$/', trim($expr))) {
                $alias = $potentialAlias;
                $expr = preg_replace('/\s+[\w]+$/i', '', $expr);
                $expr = trim($expr);
            }
        }

        // Check for table.field pattern
        if (preg_match('/^(\[?[\w]+\]?)\.(\[?[\w]+\]?)$/', $expr, $fieldMatch)) {
            $tableName = trim($fieldMatch[1], '[]');
            $fieldName = trim($fieldMatch[2], '[]');

            return [
                'table' => $tableName,
                'field' => $fieldName,
                'alias' => $alias ?? $fieldName,
                'visible' => true
            ];
        }

        // Simple field name (no table prefix)
        if (preg_match('/^\[?([\w]+)\]?$/', $expr, $simpleMatch)) {
            $fieldName = $simpleMatch[1];
            $tableName = !empty($tables) ? $tables[0] : '';

            return [
                'table' => $tableName,
                'field' => $fieldName,
                'alias' => $alias ?? $fieldName,
                'visible' => true
            ];
        }

        // Complex expression (function, calculation, IIf, Switch, etc.)
        if (!empty($alias)) {
            return [
                'table' => '',
                'field' => $expr,
                'alias' => $alias,
                'expression' => true,
                'visible' => true
            ];
        }

        // Expression without alias - generate alias from expression
        // This handles cases like IIf(...) or Switch(...) without explicit AS
        if (preg_match('/\b(IIf|Switch|COUNT|SUM|AVG|MIN|MAX|FIRST|LAST)\s*\(/i', $expr)) {
            return [
                'table' => '',
                'field' => $expr,
                'alias' => 'Expr' . crc32($expr),
                'expression' => true,
                'visible' => true
            ];
        }

        return null;
    }

    /**
     * Extract sorting from ORDER BY clause
     */
    public static function extractSorting(string $sql): ?array
    {
        if (!preg_match('/\bORDER\s+BY\s+(.*?)(?:$|\bLIMIT\b|\bOFFSET\b)/is', $sql, $orderMatch)) {
            return [];
        }

        $orderPart = trim($orderMatch[1]);
        $sortItems = [];

        $parts = preg_split('/,(?![^()]*\))/', $orderPart);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            $direction = 'asc';
            if (preg_match('/\bDESC\s*$/i', $part)) {
                $direction = 'desc';
                $part = preg_replace('/\bDESC\s*$/i', '', $part);
            } elseif (preg_match('/\bASC\s*$/i', $part)) {
                $part = preg_replace('/\bASC\s*$/i', '', $part);
            }

            $part = trim($part);

            if (preg_match('/^(\[?[\w]+\]?)\.(\[?[\w]+\]?)$/', $part, $fieldMatch)) {
                $sortItems[] = [
                    'table' => trim($fieldMatch[1], '[]'),
                    'field' => trim($fieldMatch[2], '[]'),
                    'direction' => $direction
                ];
            } elseif (preg_match('/^\[?([\w]+)\]?$/', $part, $simpleMatch)) {
                $sortItems[] = [
                    'table' => '',
                    'field' => $simpleMatch[1],
                    'direction' => $direction
                ];
            }
        }

        return $sortItems;
    }

    /**
     * Extract grouping from GROUP BY clause
     */
    public static function extractGrouping(string $sql): ?array
    {
        if (!preg_match('/\bGROUP\s+BY\s+(.*?)(?:$|\bHAVING\b|\bORDER\s+BY\b|\bLIMIT\b)/is', $sql, $groupMatch)) {
            return [];
        }

        $groupPart = trim($groupMatch[1]);
        $groupItems = [];

        $parts = preg_split('/,(?![^()]*\))/', $groupPart);

        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            if (preg_match('/^(\[?[\w]+\]?)\.(\[?[\w]+\]?)$/', $part, $fieldMatch)) {
                $groupItems[] = [
                    'table' => trim($fieldMatch[1], '[]'),
                    'field' => trim($fieldMatch[2], '[]')
                ];
            } elseif (preg_match('/^\[?([\w]+)\]?$/', $part, $simpleMatch)) {
                $groupItems[] = [
                    'table' => '',
                    'field' => $simpleMatch[1]
                ];
            }
        }

        return $groupItems;
    }

    /**
     * Filter bracketed names to only include actual table names
     */
    public static function filterToTableNames(string $fromPart, array $candidates): array
    {
        $tables = [];

        foreach ($candidates as $name) {
            $tablePattern = '/(?:\bFROM\s+\(*\s*\[' . preg_quote($name, '/') . '\]|' .
                            '\bJOIN\s+\[' . preg_quote($name, '/') . '\]|' .
                            '\[' . preg_quote($name, '/') . '\]\s*(?:INNER|LEFT|RIGHT|FULL|OUTER|CROSS)?\s*JOIN|' .
                            '\[' . preg_quote($name, '/') . '\]\s*ON\b)/i';

            if (preg_match($tablePattern, $fromPart)) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    /**
     * Sanitize an alias - removes spaces and invalid SQL identifier characters
     *
     * @param string $alias The input alias
     * @return string The sanitized alias
     */
    public static function sanitizeAlias(string $alias): string
    {
        if (empty($alias)) return '';
        $alias = preg_replace('/\s+/', '_', $alias);
        $alias = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        return $alias;
    }
}
