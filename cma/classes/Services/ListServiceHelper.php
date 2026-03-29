<?php

namespace Cma\Services;

use App\Library\Application;
use App\Library\Arr;
use App\Library\Database;
use App\Library\SQL;
use Cma\FormControlHelper;
use Cma\FormDefinition;

/**
 * List Service Helper
 *
 * Contains shared helper methods used by TreeService, TableService,
 * SubformService, and JsonFormService.
 */
class ListServiceHelper
{
    /**
     * Get list SQL for a form
     */
    public static function getListSql(int $formId, FormDefinition $formDef, array $options): ?string
    {
        // Get list SQL from JSON definition first
        $listSql = $formDef->getListQuery();
        if ($listSql !== '' && $listSql !== null) {
            return $listSql;
        }

        // Build default list SQL as fallback
        $tableName = $formDef->getSqlTableName();
        $idField = $formDef->getFormIdField();

        if ($tableName === '' || $idField === '') {
            return null;
        }

        // Build efficient SELECT with only needed columns
        $columns = [$idField];
        $listColumns = $formDef->getListColumns();
        foreach ($listColumns as $col) {
            $fieldName = $col['field'] ?? '';
            if ($fieldName !== '' && !in_array($fieldName, $columns)) {
                $columns[] = $fieldName;
            }
        }

        // Fallback to SELECT * if no columns found
        if (count($columns) <= 1) {
            return "SELECT * FROM $tableName";
        }

        $columnList = implode(', ', array_map(fn($c) => "[$c]", $columns));
        return "SELECT $columnList FROM $tableName";
    }

    /**
     * Apply search filter to SQL using quickSearchFields from form definition
     */
    public static function applySearchFilter(string $sql, string $search, FormDefinition $formDef): string
    {
        $quickFields = $formDef->getQuickFields();
        if (empty($quickFields)) {
            return $sql;
        }

        $searchEscaped = SQL::postString('%' . $search . '%');
        $fields = array_filter(array_map('trim', explode(',', $quickFields)));
        if (empty($fields)) {
            return $sql;
        }

        $conditions = [];
        foreach ($fields as $fieldName) {
            $conditions[] = "[$fieldName] LIKE $searchEscaped";
        }

        return SQL::addWhere($sql, '(' . implode(' OR ', $conditions) . ')');
    }

    /**
     * Apply field-specific search filters to SQL query
     *
     * @param string $sql Original SQL query
     * @param array $filters Field filters from search panel
     * @param array|\ArrayAccess $arrRep Form definition array
     * @return string Modified SQL query with WHERE clauses
     */
    public static function applySearchFilters(string $sql, array $filters, array|\ArrayAccess $arrRep): string
    {
        if (empty($filters)) {
            return $sql;
        }

        // Build field lookup map once - O(n) instead of O(n*m)
        $fieldLookup = [];
        $fieldNames = $arrRep[\Q_FIELDNAME] ?? [];
        $baseFieldNames = $arrRep[\Q_BASEFIELDNAME] ?? [];
        $controlTypes = $arrRep[\Q_CONTROLTYPEID] ?? [];

        foreach ($fieldNames as $i => $name) {
            $nameLower = strtolower((string)($name ?? ''));
            $baseNameLower = strtolower((string)($baseFieldNames[$i] ?? ''));
            if ($nameLower !== '') {
                $fieldLookup[$nameLower] = $i;
            }
            if ($baseNameLower !== '' && !isset($fieldLookup[$baseNameLower])) {
                $fieldLookup[$baseNameLower] = $i;
            }
        }

        foreach ($filters as $columnName => $value) {
            if ($value === '' || $value === null || $columnName === '') {
                continue;
            }

            // O(1) lookup for control type
            $controlType = 0;
            $fieldIndex = null;
            $columnNameLower = strtolower((string)$columnName);
            if (isset($fieldLookup[$columnNameLower])) {
                $fieldIndex = $fieldLookup[$columnNameLower];
                $controlType = (int)($controlTypes[$fieldIndex] ?? 0);
            } else {
                // Skip filters for columns not defined in the form fields
                continue;
            }

            // Range filter (date or number)
            if (Arr::isArray($value) && (isset($value['from']) || isset($value['to']))) {
                $from = $value['from'] ?? '';
                $to = $value['to'] ?? '';

                // Handle date range filters first (try to parse as date)
                if ($from !== '') {
                    $fromDate = self::parseSearchDate($from);
                    if ($fromDate) {
                        $sql = SQL::addWhere($sql, "[$columnName] >= " . SQL::postDateTime($fromDate));
                    } elseif (is_numeric($from)) {
                        $sql = SQL::addWhere($sql, "[$columnName] >= " . floatval($from));
                    }
                }
                if ($to !== '') {
                    $toDate = self::parseSearchDate($to);
                    if ($toDate) {
                        $sql = SQL::addWhere($sql, "[$columnName] <= " . SQL::postDateTime($toDate));
                    } elseif (is_numeric($to)) {
                        $sql = SQL::addWhere($sql, "[$columnName] <= " . floatval($to));
                    }
                }
            }
            // Boolean filter
            elseif ($controlType === FormControlHelper::TYPE_CHECKBOX) {
                $boolVal = ($value === '1' || $value === 1 || $value === true) ? 1 : 0;
                $sql = SQL::addWhere($sql, "[$columnName] = $boolVal");
            }
            // Combobox/select filter - use exact match
            elseif (in_array($controlType, [
                FormControlHelper::TYPE_COMBOBOX,
                FormControlHelper::TYPE_USERLIST,
                FormControlHelper::TYPE_XMLSTORE
            ])) {
                if (is_numeric($value)) {
                    $sql = SQL::addWhere($sql, "[$columnName] = " . (int)$value);
                } else {
                    $sql = SQL::addWhere($sql, "[$columnName] = " . SQL::postString($value));
                }
            }
            // Text filter - use LIKE
            else {
                if (Arr::isArray($value)) {
                    continue;
                }
                $sql = SQL::addWhere($sql, "[$columnName] LIKE " . SQL::postString('%' . $value . '%'));
            }
        }

        return $sql;
    }

    /**
     * Apply field-specific filters for JSON forms
     *
     * @param string $sql Original SQL query
     * @param array $filters Field filters
     * @param array $rawFormDef Raw JSON form definition
     * @return string Modified SQL query with WHERE clauses
     */
    public static function applyJsonFormFilters(string $sql, array $filters, array $rawFormDef): string
    {
        if (empty($filters)) {
            return $sql;
        }

        // Build field type lookup from JSON definition
        $fieldTypes = [];
        foreach ($rawFormDef['fields'] ?? [] as $field) {
            $name = $field['name'] ?? '';
            if ($name !== '') {
                $fieldTypes[strtolower($name)] = $field['type'] ?? 'textbox';
            }
        }

        foreach ($filters as $columnName => $value) {
            if ($value === '' || $value === null || $columnName === '') {
                continue;
            }

            $fieldType = $fieldTypes[strtolower($columnName)] ?? null;

            // Skip filters for columns not defined in the form fields
            // This prevents SQL errors when a filter references a column that doesn't exist in the table
            // (e.g. filterIdName "fkOpleiding" applied to tblOpleidingen where fkOpleiding is not a column)
            if ($fieldType === null) {
                continue;
            }

            // Range filter
            if (Arr::isArray($value) && (isset($value['from']) || isset($value['to']))) {
                $from = $value['from'] ?? '';
                $to = $value['to'] ?? '';

                $isDateField = in_array($fieldType, ['date', 'datetime', 'time']);

                if ($isDateField) {
                    if ($from !== '') {
                        $fromDate = self::parseSearchDate($from);
                        if ($fromDate) {
                            $sql = SQL::addWhere($sql, "[$columnName] >= " . SQL::postDateTime($fromDate));
                        }
                    }
                    if ($to !== '') {
                        $toDate = self::parseSearchDate($to);
                        if ($toDate) {
                            $sql = SQL::addWhere($sql, "[$columnName] <= " . SQL::postDateTime($toDate . ' 23:59:59'));
                        }
                    }
                } else {
                    if ($from !== '' && is_numeric($from)) {
                        $sql = SQL::addWhere($sql, "[$columnName] >= " . floatval($from));
                    }
                    if ($to !== '' && is_numeric($to)) {
                        $sql = SQL::addWhere($sql, "[$columnName] <= " . floatval($to));
                    }
                }
            }
            // Boolean filter
            elseif ($fieldType === 'checkbox') {
                $boolVal = ($value === '1' || $value === 1 || $value === true) ? 1 : 0;
                $sql = SQL::addWhere($sql, "[$columnName] = $boolVal");
            }
            // Combobox/select filter
            elseif (in_array($fieldType, ['combobox', 'userlist', 'select'])) {
                if (is_numeric($value)) {
                    $sql = SQL::addWhere($sql, "[$columnName] = " . (int)$value);
                } else {
                    $sql = SQL::addWhere($sql, "[$columnName] = " . SQL::postString($value));
                }
            }
            // Text filter
            else {
                if (Arr::isArray($value)) {
                    continue;
                }
                $sql = SQL::addWhere($sql, "[$columnName] LIKE " . SQL::postString('%' . $value . '%'));
            }
        }

        return $sql;
    }

    /**
     * Parse search date from various formats to yyyy-mm-dd
     * Accepts: dd-mm-yyyy, yyyy-mm-dd (ISO format from lib-datepicker)
     */
    public static function parseSearchDate(string $dateStr): ?string
    {
        // Try ISO format first (yyyy-mm-dd) - this is what lib-datepicker uses
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $dateStr, $matches)) {
            $year = (int)$matches[1];
            $month = (int)$matches[2];
            $day = (int)$matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }

        // Try Dutch format (dd-mm-yyyy)
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})$/', $dateStr, $matches)) {
            $day = (int)$matches[1];
            $month = (int)$matches[2];
            $year = (int)$matches[3];

            if (checkdate($month, $day, $year)) {
                return sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        }
        return null;
    }

    /**
     * Convert control type ID to field type name
     */
    public static function getFieldTypeName(int $controlType): string
    {
        switch ($controlType) {
            case FormControlHelper::TYPE_CHECKBOX:
                return 'checkbox';
            case FormControlHelper::TYPE_COMBOBOX:
            case FormControlHelper::TYPE_USERLIST:
                return 'combobox';
            case FormControlHelper::TYPE_MEMO:
                return 'memo';
            case FormControlHelper::TYPE_EMAIL:
                return 'email';
            case FormControlHelper::TYPE_URL:
                return 'url';
            default:
                return 'textbox';
        }
    }

    /**
     * Get database connection for JSON form
     */
    public static function getJsonFormConnection(string $database)
    {
        switch (strtolower($database)) {
            case 'users':
                return Database::getConnection('users');
            case 'rep':
            case 'repository':
                return Database::getRepConnection();
            case 'json':
                return null;
            case 'data':
            case '':
                return Database::getConnection('data');
        }

        if (is_numeric($database)) {
            $connString = \Cma\CmaRepository::getConnectionStringById((int)$database);
            return \Cma\CmaRepository::resolveConnectionString($connString);
        }

        return Database::getConnection($database);
    }

    /**
     * Convert database value to boolean
     */
    public static function toBool($value): bool
    {
        if ($value === null || $value === '' || $value === 0 || $value === '0') {
            return false;
        }
        if ($value === true || $value === 1 || $value === -1) {
            return true;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return $lower === 'true' || $lower === 'yes' || $lower === 'ja' || $lower === '-1' || $lower === '1';
        }
        return (bool)$value;
    }

    /**
     * Create query error response with SQL included on local/test environments
     */
    public static function queryError(string $sql): array
    {
        $message = 'Query mislukt: ' . Database::getLastError();

        if (Application::get('local', '') || Application::get('test', '')) {
            $message .= ' | SQL: ' . $sql;
        }

        return ['success' => false, 'error' => $message];
    }

    /**
     * Build column list SQL for JSON forms
     */
    public static function buildJsonColumnList(array $listColumns, string $idField, string $detailField = '', array $groupFields = []): string
    {
        $columns = [$idField];

        // Add group fields
        foreach ($groupFields as $gf) {
            if (!empty($gf) && !in_array($gf, $columns)) {
                $columns[] = $gf;
            }
        }

        // Add detail field
        if ($detailField !== '' && !in_array($detailField, $columns)) {
            $columns[] = $detailField;
        }

        // Add list columns
        foreach ($listColumns as $col) {
            $colName = Arr::isArray($col) ? ($col['name'] ?? $col['field'] ?? '') : $col;
            if (!empty($colName) && !in_array($colName, $columns)) {
                $columns[] = $colName;
            }
        }

        // Wrap column names in brackets
        return implode(', ', array_map(fn($c) => "[$c]", $columns));
    }

    /**
     * Convert control type to field type string
     */
    public static function controlTypeToFieldType(int $controlType): string
    {
        switch ($controlType) {
            case FormControlHelper::TYPE_CHECKBOX:
                return 'boolean';
            case FormControlHelper::TYPE_COMBOBOX:
            case FormControlHelper::TYPE_USERLIST:
                return 'select';
            case FormControlHelper::TYPE_MEMO:
                return 'textarea';
            default:
                return 'text';
        }
    }

    /**
     * Get control type name for debugging
     */
    public static function getControlTypeName(int $controlType): string
    {
        $names = [
            FormControlHelper::TYPE_TEXTBOX => 'textbox',
            FormControlHelper::TYPE_CHECKBOX => 'checkbox',
            FormControlHelper::TYPE_COMBOBOX => 'combobox',
            FormControlHelper::TYPE_USERLIST => 'userlist',
            FormControlHelper::TYPE_MEMO => 'memo',
            FormControlHelper::TYPE_EMAIL => 'email',
            FormControlHelper::TYPE_URL => 'url',
            FormControlHelper::TYPE_GROUPSEPARATOR => 'separator',
            FormControlHelper::TYPE_LABEL => 'label',
        ];

        return $names[$controlType] ?? 'unknown(' . $controlType . ')';
    }
}
