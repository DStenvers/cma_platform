<?php

namespace Cma;

use App\Library\Arr;
use App\Library\Database;
use App\Library\SQL;

/**
 * Query Builder
 *
 * Constructs SQL queries from report definitions with proper escaping
 * and parameter substitution.
 */
class QueryBuilder
{
    /**
     * @var array Report definition
     */
    private array $definition = [];

    /**
     * @var array Runtime parameters
     */
    private array $parameters = [];

    /**
     * @var \PDO|null Database connection
     */
    private $connection = null;

    /**
     * @var array Errors encountered during building
     */
    private array $errors = [];

    /**
     * Filter operators by type category
     */
    public const FILTER_OPERATORS = [
        'text' => [
            '=' => '= ?',
            '<>' => '<> ?',
            'contains' => 'LIKE ?',
            'starts' => 'LIKE ?',
            'ends' => 'LIKE ?',
            'empty' => 'IS NULL OR ? = \'\'',
            'notempty' => 'IS NOT NULL AND ? <> \'\''
        ],
        'number' => [
            '=' => '= ?',
            '<>' => '<> ?',
            '<' => '< ?',
            '>' => '> ?',
            '<=' => '<= ?',
            '>=' => '>= ?',
            'between' => 'BETWEEN ? AND ?'
        ],
        'date' => [
            '=' => '= ?',
            '<>' => '<> ?',
            'before' => '< ?',
            'after' => '> ?',
            'between' => 'BETWEEN ? AND ?',
            'today' => '= ?',
            'thisweek' => 'BETWEEN ? AND ?',
            'thismonth' => 'BETWEEN ? AND ?'
        ],
        'boolean' => [
            'yes' => '= ?',
            'no' => '= ?'
        ]
    ];

    /**
     * Create a new QueryBuilder
     *
     * @param array $definition Report definition
     * @param \PDO|string|null $connection Database connection
     */
    public function __construct(array $definition = [], $connection = null)
    {
        $this->definition = $definition;
        $this->connection = $connection;
    }

    /**
     * Set the report definition
     *
     * @param array $definition Report definition
     * @return self
     */
    public function setDefinition(array $definition): self
    {
        $this->definition = $definition;
        return $this;
    }

    /**
     * Set runtime parameters
     *
     * @param array $parameters Associative array of parameter values
     * @return self
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set database connection
     *
     * @param \PDO|string $connection Database connection
     * @return self
     */
    public function setConnection($connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Build the SELECT clause
     * Returns SELECT * if all fields are visible and have no custom aliases
     * Supports DISTINCT option from definition
     *
     * @return string
     */
    public function buildSelect(): string
    {
        $fields = $this->definition['fields'] ?? [];
        $distinct = !empty($this->definition['distinct']);

        $keyword = $distinct ? 'SELECT DISTINCT' : 'SELECT';

        // Only use SELECT * when no fields are specified
        // If specific fields are provided, always use them explicitly
        if (empty($fields)) {
            return $keyword . ' *';
        }

        $selectParts = [];
        foreach ($fields as $field) {
            if (!($field['visible'] ?? true)) {
                continue;
            }

            $tableName = trim($field['table'] ?? '');
            $fieldName = trim($field['field'] ?? '');
            $alias = trim($field['alias'] ?? '');

            if (empty($fieldName)) {
                continue;
            }

            // Build qualified field name
            $qualifiedName = '';
            if (!empty($tableName)) {
                $qualifiedName = $this->quoteIdentifier($tableName) . '.';
            }
            $qualifiedName .= $this->quoteIdentifier($fieldName);

            // Add alias if different from field name
            if (!empty($alias) && $alias !== $fieldName) {
                $qualifiedName .= ' AS ' . $this->quoteIdentifier($alias);
            }

            $selectParts[] = $qualifiedName;
        }

        if (empty($selectParts)) {
            return $keyword . ' *';
        }

        return $keyword . ' ' . implode(', ', $selectParts);
    }

    /**
     * Build the FROM clause with JOINs
     * MS Access requires nested parentheses around multiple JOINs
     *
     * @return string
     */
    public function buildFrom(): string
    {
        $tables = $this->definition['tables'] ?? [];

        if (empty($tables)) {
            // No tables selected yet - not an error, just return empty
            return '';
        }

        // Collect base table and all joins
        $baseTable = null;
        $allJoins = [];

        foreach ($tables as $table) {
            $tableName = $table['name'] ?? '';
            $joins = $table['joins'] ?? [];

            if (empty($tableName)) {
                continue;
            }

            // First table becomes the base
            if ($baseTable === null) {
                $baseTable = $tableName;
            }

            // Collect all joins
            foreach ($joins as $join) {
                $joinTable = $join['table'] ?? '';
                $joinType = strtoupper($join['type'] ?? 'INNER');
                $joinOn = $join['on'] ?? '';

                if (empty($joinTable) || empty($joinOn)) {
                    continue;
                }

                // Validate join type
                if (!in_array($joinType, ['INNER', 'LEFT', 'RIGHT', 'FULL'])) {
                    $joinType = 'INNER';
                }

                $allJoins[] = [
                    'table' => $joinTable,
                    'type' => $joinType,
                    'on' => $joinOn
                ];
            }
        }

        if ($baseTable === null) {
            return '';
        }

        // No joins - simple FROM clause
        if (empty($allJoins)) {
            return 'FROM ' . $this->quoteIdentifier($baseTable);
        }

        // Build set of tables that are in the FROM clause
        $tablesInFrom = [strtolower($baseTable)];
        foreach ($allJoins as $join) {
            $tablesInFrom[] = strtolower($join['table']);
        }

        // Validate that ON clauses only reference tables in the FROM
        foreach ($allJoins as $join) {
            $referencedTables = $this->extractTablesFromOnClause($join['on']);
            foreach ($referencedTables as $refTable) {
                if (!in_array(strtolower($refTable), $tablesInFrom)) {
                    $this->errors[] = "Table '{$refTable}' is referenced in JOIN ON clause but is not in the FROM clause. " .
                                     "Make sure all tables used in relationships are properly connected.";
                }
            }
        }

        // MS Access requires parentheses around multiple JOINs
        // Pattern: FROM (((table1 INNER JOIN table2 ON cond) INNER JOIN table3 ON cond) ...)
        $joinCount = count($allJoins);
        $openParens = str_repeat('(', $joinCount);

        $result = 'FROM ' . $openParens . $this->quoteIdentifier($baseTable);

        foreach ($allJoins as $index => $join) {
            $joinPart = "\n" . $join['type'] . ' JOIN ' . $this->quoteIdentifier($join['table']);
            $joinPart .= ' ON ' . $this->sanitizeJoinCondition($join['on']) . ')';
            $result .= $joinPart;
        }

        return $result;
    }

    /**
     * Extract table names from an ON clause
     *
     * @param string $onClause The ON clause (e.g., "[tblA].[field] = [tblB].[field]")
     * @return array List of table names found
     */
    private function extractTablesFromOnClause(string $onClause): array
    {
        $tables = [];
        // Match [tableName].[fieldName] patterns
        if (preg_match_all('/\[(\w+)\]\.\[(\w+)\]/', $onClause, $matches)) {
            foreach ($matches[1] as $tableName) {
                if (!in_array($tableName, $tables)) {
                    $tables[] = $tableName;
                }
            }
        }
        return $tables;
    }

    /**
     * Build the WHERE clause
     * Tries new conditions array first, falls back to legacy field-based filters
     *
     * @return string
     */
    public function buildWhere(): string
    {
        // Try new conditions array first
        $conditions = $this->definition['conditions'] ?? [];

        if (!empty($conditions)) {
            return $this->buildWhereFromConditions($conditions);
        }

        // Fallback to legacy field-based filters
        return $this->buildWhereFromFields();
    }

    /**
     * Build WHERE clause from new conditions array format
     * Supports multiple conditions per field, custom ordering, and AND/OR logic
     *
     * @param array $conditions Array of condition objects
     * @return string
     */
    private function buildWhereFromConditions(array $conditions): string
    {
        $parts = [];

        foreach ($conditions as $index => $cond) {
            $operator = $cond['operator'] ?? '';
            if (empty($operator)) {
                continue;
            }

            $value = $this->substituteParameter($cond['value'] ?? '');
            $value2 = $this->substituteParameter($cond['value2'] ?? '');

            // Skip empty values (except for empty/notempty/today/thisweek/thismonth/yes/no operators)
            $noValueOperators = ['empty', 'notempty', 'today', 'thisweek', 'thismonth', 'yes', 'no'];
            if ($value === '' && !in_array($operator, $noValueOperators)) {
                continue;
            }

            // Build qualified field name
            $tableName = trim($cond['table'] ?? '');
            $fieldName = trim($cond['field'] ?? '');

            if (empty($fieldName)) {
                continue;
            }

            $qualifiedName = '';
            if (!empty($tableName)) {
                $qualifiedName = $this->quoteIdentifier($tableName) . '.';
            }
            $qualifiedName .= $this->quoteIdentifier($fieldName);

            // Get type category from condition or default to text
            $typeCategory = $cond['typeCategory'] ?? 'text';

            $conditionSql = $this->buildCondition($qualifiedName, $operator, $value, $value2, $typeCategory);
            if (empty($conditionSql)) {
                continue;
            }

            // Add brackets (prefix/suffix)
            $prefix = $cond['prefix'] ?? '';
            $suffix = $cond['suffix'] ?? '';
            $conditionSql = $prefix . $conditionSql . $suffix;

            // Add logic (AND/OR) for conditions after first
            if (!empty($parts)) {
                $logic = strtoupper($cond['logic'] ?? 'AND');
                if (!in_array($logic, ['AND', 'OR'])) {
                    $logic = 'AND';
                }
                $conditionSql = $logic . ' ' . $conditionSql;
            }

            $parts[] = $conditionSql;
        }

        if (empty($parts)) {
            return '';
        }

        return 'WHERE ' . implode("\n", $parts);
    }

    /**
     * Build WHERE clause from legacy field-based filters
     *
     * @return string
     */
    private function buildWhereFromFields(): string
    {
        $fields = $this->definition['fields'] ?? [];
        $conditions = [];

        foreach ($fields as $field) {
            $filter = $field['filter'] ?? null;
            if (empty($filter)) {
                continue;
            }

            $operator = $filter['operator'] ?? '';
            $value = $filter['value'] ?? '';
            $value2 = $filter['value2'] ?? '';

            if (empty($operator)) {
                continue;
            }

            // Substitute parameters
            $value = $this->substituteParameter($value);
            $value2 = $this->substituteParameter($value2);

            // Skip if parameter is empty and not set
            if ($value === '' && !in_array($operator, ['empty', 'notempty'])) {
                continue;
            }

            $tableName = trim($field['table'] ?? '');
            $fieldName = trim($field['field'] ?? '');

            // Build qualified field name
            $qualifiedName = '';
            if (!empty($tableName)) {
                $qualifiedName = $this->quoteIdentifier($tableName) . '.';
            }
            $qualifiedName .= $this->quoteIdentifier($fieldName);

            // Get field type category for proper quoting
            $dataType = $field['dataType'] ?? 0;
            $typeCategory = SchemaHelper::categorizeType((int)$dataType);

            // Build condition with brackets and logic
            $condition = $this->buildCondition($qualifiedName, $operator, $value, $value2, $typeCategory);
            if (!empty($condition)) {
                // Add brackets from filter
                $prefix = $filter['prefix'] ?? '';
                $suffix = $filter['suffix'] ?? '';
                $condition = $prefix . $condition . $suffix;

                // Add logic for conditions after first
                if (!empty($conditions)) {
                    $logic = strtoupper($filter['logic'] ?? 'AND');
                    if (!in_array($logic, ['AND', 'OR'])) {
                        $logic = 'AND';
                    }
                    $condition = $logic . ' ' . $condition;
                }

                $conditions[] = $condition;
            }
        }

        if (empty($conditions)) {
            return '';
        }

        return 'WHERE ' . implode("\n", $conditions);
    }

    /**
     * Build a single WHERE condition
     *
     * @param string $field Qualified field name
     * @param string $operator Operator
     * @param string $value First value
     * @param string $value2 Second value (for BETWEEN)
     * @param string $typeCategory Type category (text, number, date, boolean)
     * @return string
     */
    private function buildCondition(string $field, string $operator, string $value, string $value2, string $typeCategory): string
    {
        switch ($operator) {
            case '=':
            case '<>':
            case '<':
            case '>':
            case '<=':
            case '>=':
                return $field . ' ' . $operator . ' ' . $this->quoteValue($value, $typeCategory);

            case 'contains':
                return $field . ' LIKE ' . $this->quoteValue('%' . $value . '%', 'text');

            case 'starts':
                return $field . ' LIKE ' . $this->quoteValue($value . '%', 'text');

            case 'ends':
                return $field . ' LIKE ' . $this->quoteValue('%' . $value, 'text');

            case 'empty':
                return '(' . $field . ' IS NULL OR ' . $field . ' = \'\')';

            case 'notempty':
                return '(' . $field . ' IS NOT NULL AND ' . $field . ' <> \'\')';

            case 'between':
                return $field . ' BETWEEN ' . $this->quoteValue($value, $typeCategory) .
                       ' AND ' . $this->quoteValue($value2, $typeCategory);

            case 'before':
                return $field . ' < ' . $this->quoteValue($value, $typeCategory);

            case 'after':
                return $field . ' > ' . $this->quoteValue($value, $typeCategory);

            case 'today':
                $today = date('Y-m-d');
                return $field . ' >= ' . $this->quoteValue($today . ' 00:00:00', 'date') .
                       ' AND ' . $field . ' < ' . $this->quoteValue($today . ' 23:59:59', 'date');

            case 'thisweek':
                $monday = date('Y-m-d', strtotime('monday this week'));
                $sunday = date('Y-m-d', strtotime('sunday this week'));
                return $field . ' BETWEEN ' . $this->quoteValue($monday, 'date') .
                       ' AND ' . $this->quoteValue($sunday . ' 23:59:59', 'date');

            case 'thismonth':
                $firstDay = date('Y-m-01');
                $lastDay = date('Y-m-t');
                return $field . ' BETWEEN ' . $this->quoteValue($firstDay, 'date') .
                       ' AND ' . $this->quoteValue($lastDay . ' 23:59:59', 'date');

            case 'yes':
                return $field . ' = 1';

            case 'no':
                return $field . ' = 0';

            default:
                return '';
        }
    }

    /**
     * Build the ORDER BY clause
     *
     * @return string
     */
    public function buildOrderBy(): string
    {
        $sorting = $this->definition['sorting'] ?? [];

        if (empty($sorting)) {
            return '';
        }

        $orderParts = [];
        foreach ($sorting as $sort) {
            $tableName = trim($sort['table'] ?? '');
            $field = trim($sort['field'] ?? '');
            $direction = strtoupper($sort['direction'] ?? 'ASC');

            if (empty($field)) {
                continue;
            }

            if (!in_array($direction, ['ASC', 'DESC'])) {
                $direction = 'ASC';
            }

            // Build qualified field name
            $qualifiedName = '';
            if (!empty($tableName)) {
                $qualifiedName = $this->quoteIdentifier($tableName) . '.';
            }
            $qualifiedName .= $this->quoteIdentifier($field);

            $orderParts[] = $qualifiedName . ' ' . $direction;
        }

        if (empty($orderParts)) {
            return '';
        }

        return 'ORDER BY ' . implode(', ', $orderParts);
    }

    /**
     * Build the GROUP BY clause
     * Automatically includes fields from conditions to prevent MS Access errors
     *
     * @return string
     */
    public function buildGroupBy(): string
    {
        $grouping = $this->definition['grouping'] ?? [];

        if (empty($grouping)) {
            return '';
        }

        $groupParts = [];
        $groupedFields = []; // Track which fields are already grouped (lowercase key)

        foreach ($grouping as $group) {
            $tableName = is_string($group) ? '' : trim($group['table'] ?? '');
            $field = is_string($group) ? trim($group) : trim($group['field'] ?? '');

            if (empty($field)) {
                continue;
            }

            // Build qualified field name
            $qualifiedName = '';
            if (!empty($tableName)) {
                $qualifiedName = $this->quoteIdentifier($tableName) . '.';
            }
            $qualifiedName .= $this->quoteIdentifier($field);

            $groupParts[] = $qualifiedName;
            $groupedFields[strtolower($tableName . '.' . $field)] = true;
        }

        // Auto-include fields from conditions that aren't already grouped
        $conditions = $this->definition['conditions'] ?? [];
        foreach ($conditions as $cond) {
            $tableName = trim($cond['table'] ?? '');
            $fieldName = trim($cond['field'] ?? '');

            if (empty($fieldName)) {
                continue;
            }

            $key = strtolower($tableName . '.' . $fieldName);
            if (!isset($groupedFields[$key])) {
                $qualifiedName = '';
                if (!empty($tableName)) {
                    $qualifiedName = $this->quoteIdentifier($tableName) . '.';
                }
                $qualifiedName .= $this->quoteIdentifier($fieldName);

                $groupParts[] = $qualifiedName;
                $groupedFields[$key] = true;
            }
        }

        // Auto-include visible fields from SELECT that aren't already grouped
        $fields = $this->definition['fields'] ?? [];
        foreach ($fields as $field) {
            if (!($field['visible'] ?? true)) {
                continue;
            }

            $tableName = trim($field['table'] ?? '');
            $fieldName = trim($field['field'] ?? '');

            if (empty($fieldName)) {
                continue;
            }

            $key = strtolower($tableName . '.' . $fieldName);
            if (!isset($groupedFields[$key])) {
                $qualifiedName = '';
                if (!empty($tableName)) {
                    $qualifiedName = $this->quoteIdentifier($tableName) . '.';
                }
                $qualifiedName .= $this->quoteIdentifier($fieldName);

                $groupParts[] = $qualifiedName;
                $groupedFields[$key] = true;
            }
        }

        if (empty($groupParts)) {
            return '';
        }

        return 'GROUP BY ' . implode(', ', $groupParts);
    }

    /**
     * Build the complete SQL query
     *
     * @param int|null $limit Optional row limit (overrides topN from definition for preview)
     * @return string
     */
    public function toSql(?int $limit = null): string
    {
        // If SQL mode, return the raw SQL directly
        if (($this->definition['mode'] ?? '') === 'sql' && !empty($this->definition['rawSql'])) {
            $sql = $this->definition['rawSql'];

            // Add TOP limit if not present and limit is specified
            if ($limit !== null && $limit > 0 && !preg_match('/\bTOP\s+\d+\b/i', $sql)) {
                $sql = preg_replace('/^SELECT\s+/i', 'SELECT TOP ' . $limit . ' ', $sql);
            }

            return $sql;
        }

        $parts = [];

        $select = $this->buildSelect();

        // Determine effective limit: explicit $limit parameter takes precedence, else use topN from definition
        $effectiveLimit = $limit ?? ($this->definition['topN'] ?? null);

        if ($effectiveLimit !== null && $effectiveLimit > 0) {
            // Insert TOP after DISTINCT if present, otherwise after SELECT
            // MS Access requires: SELECT DISTINCT TOP N field1, field2 FROM ...
            if (preg_match('/^SELECT DISTINCT\s+/i', $select)) {
                $select = preg_replace('/^SELECT DISTINCT\s+/i', 'SELECT DISTINCT TOP ' . $effectiveLimit . ' ', $select);
            } else {
                $select = preg_replace('/^SELECT\s+/i', 'SELECT TOP ' . $effectiveLimit . ' ', $select);
            }
        }
        $parts[] = $select;

        $from = $this->buildFrom();
        if (!empty($from)) {
            $parts[] = $from;
        }

        $where = $this->buildWhere();
        if (!empty($where)) {
            $parts[] = $where;
        }

        $groupBy = $this->buildGroupBy();
        if (!empty($groupBy)) {
            $parts[] = $groupBy;
        }

        $orderBy = $this->buildOrderBy();
        if (!empty($orderBy)) {
            $parts[] = $orderBy;
        }

        return implode("\n", $parts);
    }

    /**
     * Execute query and return preview data
     *
     * @param int $limit Maximum rows to return (0 = no limit)
     * @return array ['success' => bool, 'data' => array, 'columns' => array, 'error' => string|null]
     */
    public function executePreview(int $limit = 0): array
    {
        if ($this->connection === null) {
            return [
                'success' => false,
                'data' => [],
                'columns' => [],
                'error' => 'No database connection'
            ];
        }

        try {
            // Generate SQL without TOP - let lib-table handle virtualization
            $sql = $this->toSql();

            if (empty($sql) || !empty($this->errors)) {
                return [
                    'success' => false,
                    'data' => [],
                    'columns' => [],
                    'error' => implode(', ', $this->errors)
                ];
            }

            // Get PDO connection
            $pdo = $this->connection;
            if (is_string($pdo)) {
                $pdo = Database::getConnection($pdo);
            }

            $stmt = $pdo->query($sql);
            $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Get column info from first row or query metadata
            $columns = [];
            if (!empty($data)) {
                $columns = array_keys($data[0]);
            }

            return [
                'success' => true,
                'data' => $data,
                'columns' => $columns,
                'sql' => $sql,
                'rowCount' => count($data),
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'columns' => [],
                'sql' => $this->toSql($limit),
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute a count query to get the total number of rows
     *
     * @return array ['success' => bool, 'count' => int, 'error' => string|null]
     */
    public function executeCount(): array
    {
        if ($this->connection === null) {
            return [
                'success' => false,
                'count' => 0,
                'error' => 'No database connection'
            ];
        }

        try {
            // Generate the base SQL and wrap it in a COUNT query
            $sql = $this->toSql();

            if (empty($sql) || !empty($this->errors)) {
                return [
                    'success' => false,
                    'count' => 0,
                    'error' => implode(', ', $this->errors)
                ];
            }

            // Wrap query in COUNT
            $countSql = "SELECT COUNT(*) as cnt FROM ({$sql}) AS count_query";

            // Get PDO connection
            $pdo = $this->connection;
            if (is_string($pdo)) {
                $pdo = Database::getConnection($pdo);
            }

            $stmt = $pdo->query($countSql);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $count = (int)($row['cnt'] ?? 0);

            return [
                'success' => true,
                'count' => $count,
                'error' => null
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'count' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get any errors encountered during building
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Clear errors
     *
     * @return self
     */
    public function clearErrors(): self
    {
        $this->errors = [];
        return $this;
    }

    /**
     * Quote a database identifier (table/column name)
     *
     * @param string $identifier
     * @return string
     */
    private function quoteIdentifier(string $identifier): string
    {
        // Remove any existing brackets
        $cleaned = str_replace(['[', ']'], '', $identifier);

        // Handle qualified names (table.column)
        if (strpos($cleaned, '.') !== false) {
            $parts = explode('.', $cleaned);
            return '[' . implode('].[', $parts) . ']';
        }

        return '[' . $cleaned . ']';
    }

    /**
     * Quote a value based on type category
     *
     * @param string $value
     * @param string $typeCategory
     * @return string
     */
    private function quoteValue(string $value, string $typeCategory): string
    {
        switch ($typeCategory) {
            case 'number':
                // Validate and return numeric value
                if (is_numeric($value)) {
                    return str_replace(',', '.', $value);
                }
                return '0';

            case 'boolean':
                return ($value === '1' || strtolower($value) === 'true' || strtolower($value) === 'yes') ? '1' : '0';

            case 'date':
                // Use SQL helper for proper date formatting
                return SQL::postDateStr($value);

            case 'text':
            default:
                // Escape string value
                return SQL::postString($value);
        }
    }

    /**
     * Substitute parameter reference with actual value
     *
     * @param string $value Value that may contain parameter reference (@param)
     * @return string
     */
    private function substituteParameter(string $value): string
    {
        // Check if value is a parameter reference
        if (strpos($value, '@') === 0) {
            $paramName = substr($value, 1);
            if (isset($this->parameters[$paramName])) {
                return (string)$this->parameters[$paramName];
            }
            // Also check with @ prefix
            if (isset($this->parameters[$value])) {
                return (string)$this->parameters[$value];
            }
            return '';
        }

        return $value;
    }

    /**
     * Sanitize a JOIN condition to prevent SQL injection
     * Only allows basic comparison operators and identifiers
     *
     * @param string $condition
     * @return string
     */
    private function sanitizeJoinCondition(string $condition): string
    {
        // Parse and rebuild the condition to ensure safety
        // Allow: identifiers, =, AND, OR, parentheses
        // Pattern matches: table.column = table.column patterns

        // First validate that it only contains safe characters
        if (!preg_match('/^[\w\.\[\]\s=<>]+(?:\s+(?:AND|OR)\s+[\w\.\[\]\s=<>]+)*$/i', $condition)) {
            $this->errors[] = 'Invalid join condition: ' . $condition;
            return '1=0'; // Safe fallback
        }

        return $condition;
    }

    /**
     * Get available filter operators for a type category
     *
     * @param string $typeCategory
     * @return array
     */
    public static function getOperatorsForType(string $typeCategory): array
    {
        $operators = [
            'text' => [
                ['value' => '=', 'label' => 'is gelijk aan'],
                ['value' => '<>', 'label' => 'is niet gelijk aan'],
                ['value' => 'contains', 'label' => 'bevat'],
                ['value' => 'starts', 'label' => 'begint met'],
                ['value' => 'ends', 'label' => 'eindigt met'],
                ['value' => 'empty', 'label' => 'is leeg'],
                ['value' => 'notempty', 'label' => 'is niet leeg']
            ],
            'number' => [
                ['value' => '=', 'label' => 'is gelijk aan'],
                ['value' => '<>', 'label' => 'is niet gelijk aan'],
                ['value' => '<', 'label' => 'kleiner dan'],
                ['value' => '>', 'label' => 'groter dan'],
                ['value' => '<=', 'label' => 'kleiner of gelijk aan'],
                ['value' => '>=', 'label' => 'groter of gelijk aan'],
                ['value' => 'between', 'label' => 'tussen']
            ],
            'date' => [
                ['value' => '=', 'label' => 'is gelijk aan'],
                ['value' => '<>', 'label' => 'is niet gelijk aan'],
                ['value' => 'before', 'label' => 'voor'],
                ['value' => 'after', 'label' => 'na'],
                ['value' => 'between', 'label' => 'tussen'],
                ['value' => 'today', 'label' => 'vandaag'],
                ['value' => 'thisweek', 'label' => 'deze week'],
                ['value' => 'thismonth', 'label' => 'deze maand']
            ],
            'boolean' => [
                ['value' => 'yes', 'label' => 'ja'],
                ['value' => 'no', 'label' => 'nee'],
                ['value' => 'empty', 'label' => 'is leeg'],
                ['value' => 'notempty', 'label' => 'is niet leeg']
            ]
        ];

        return $operators[$typeCategory] ?? $operators['text'];
    }
}
