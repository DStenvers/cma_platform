<?php

namespace Cma;

use App\Library\Arr;
use App\Library\Cache;

require_once __DIR__ . '/FormField.php';
require_once __DIR__ . '/CmaRepository.php';

/**
 * Form Definition Class
 *
 * Provides named access to CMA form definition data.
 * Wraps the column-major array returned by Cache::retrieveFromFile()
 * with readable property names instead of numeric constants.
 */
class FormDefinition
{
    /** @var array|\ArrayObject|null */
    private $data = null;
    private int $rowCount = 0;

    /** @var array|null JSON-specific data (from _json key or raw JSON) */
    private ?array $jsonData = null;

    /** @var string|null Form identifier (name for JSON forms, ID for database forms) */
    private ?string $formIdentifier = null;

    /** @var bool Whether this is a JSON-defined form */
    private bool $isJsonForm = false;

    /**
     * Column name mapping (property name => database column name)
     * Values are the actual column names returned by GetFormDef SQL query
     */
    private const COLUMNS = [
        'fkDatabase' => 'fkDatabase',
        'formIdField' => 'FormIDField',
        'afterPostUrl' => 'AfterPostUrl',
        'sqlTableName' => 'SqlTable',
        'menuNew' => 'MenuNew',
        'menuDelete' => 'MenuDelete',
        'previewUrl' => 'previewUrl',
        'formName' => 'FormName',
        'securityByUser' => 'blnSecurityByUser',
        'storeLastModified' => 'blnStoreLastModified',
        'cachePrefix' => 'Cache_Prefix',
        'controlId' => 'ControlID',
        'fieldName' => 'FieldName',
        'controlTypeId' => 'ControlTypeID',
        'isRequired' => 'IsRequired',
        'caption' => 'Caption',
        'postCaption' => 'PostCaption',
        'baseFieldName' => 'BaseFieldname',
        'ctrlIdField' => 'IDField',
        'foreignIdField' => 'ForeignIDField',
        'sourceTable' => 'SourceTable',
        'sqlList' => 'SqlList',
        'height' => 'Height',
        'htmlTags' => 'TagsAllowed',
        'imgPath' => 'ImgPath',
        'imgWidthField' => 'ImgWidthField',
        'imgHeightField' => 'ImgHeightField',
        'imgResizeType' => 'ImgResizeType',
        'imgResizeHeight' => 'ImgResizeHeight',
        'imgResizeWidth' => 'ImgResizeWidth',
        'fileRandom' => 'blnFileRandomName',
        'checkListWidth' => 'CheckListWidth',
        'passOnToPost' => 'blnPassOnToPostUrl',
        'xmlSnippet' => 'XMLSnippet',
        'dirFilename' => 'dirFilename',
        'dirTemplate' => 'dirTemplate',
        'databaseId' => 'ControlDatabaseID',
        'extraIconUrl' => 'extraIconURL',
        'extraIconRes' => 'extraIconResource',
        'extraIconTitle' => 'extraIconTitle',
        'noSpamJs' => 'blnNoSpamJS',
        'filterFieldName' => 'FilterFieldName',
        'filterDescription' => 'FilterCaption',
        'newChangableOnly' => 'blnNewChangableOnly',
        'parentForm' => 'fkParentForm',
        'extraIcon2Url' => 'extraIcon2URL',
        'extraIcon2Res' => 'extraIcon2Resource',
        'extraIcon2Title' => 'extraIcon2Title',
        'extraIcon3Url' => 'extraIcon3URL',
        'extraIcon3Res' => 'extraIcon3Resource',
        'extraIcon3Title' => 'extraIcon3Title',
        'extraIcon4Url' => 'extraIcon4URL',
        'extraIcon4Res' => 'extraIcon4Resource',
        'extraIcon4Title' => 'extraIcon4Title',
        'extraIcon5Url' => 'extraIcon5URL',
        'extraIcon5Res' => 'extraIcon5Resource',
        'extraIcon5Title' => 'extraIcon5Title',
        'onLoadJs' => 'onloadJS',
        'filterIdName' => 'filterIDName',
        'fieldReadOnly' => 'blnReadOnly',
        'fieldLimitedHtml' => 'blnLimitedHTML',
        'fieldMaxChars' => 'intMaxChars',
        'quickFields' => 'quickfilterfields',
        'menuCopy' => 'MenuCopy',
        'keepWithNext' => 'bCombineWithNext',
        'schemaDatePrecision' => 'schema_date_prec',
        'schemaDefault' => 'schema_default',
        'schemaCharMaxLength' => 'schema_char_maxl',
        'schemaNumPrecision' => 'schema_num_prec',
        'schemaDataType' => 'schema_datatype',
        'action' => 'actie',
        'formAction' => 'FormActie',
        'isBeheer' => 'isBeheer',
        'group1Field' => 'Group1Field',
        'group2Field' => 'Group2Field',
        'group3Field' => 'Group3Field',
        'detailField' => 'DetailField',
        'nameQuery' => 'NameQuery',
        'recurseField' => 'recurseField',
    ];

    /**
     * Create FormDefinition from form ID
     *
     * @param int|string $formId Form ID
     * @param \PDO $connection Repository connection
     * @return self
     */
    public static function load($formId, \PDO $connection): self
    {
        $instance = new self();
        $instance->data = \GetFormDef($formId);

        if (Arr::isArray($instance->data) && isset($instance->data[0])) {
            $instance->rowCount = count($instance->data[0]);
        }

        return $instance;
    }

    /**
     * Create FormDefinition from existing array data
     *
     * @param array|ArrayObject|null $data Raw form definition array or ColumnMajorArray
     * @return self
     */
    public static function fromArray($data): self
    {
        $instance = new self();
        $instance->data = $data;

        if (Arr::isArray($data)) {
            // Check for Q_FIELDNAME array first (from JSON loader or cache)
            // This is the primary way to count fields in legacy format
            if (isset($data[Q_FIELDNAME]) && Arr::isArray($data[Q_FIELDNAME])) {
                $instance->rowCount = count($data[Q_FIELDNAME]);
            } elseif ($data instanceof \ArrayObject) {
                // For ColumnMajorArray, count() returns row count
                $instance->rowCount = count($data);
            } elseif (isset($data[0])) {
                // For regular arrays, check first column
                $instance->rowCount = count($data[0]);
            }

            // Check for JSON data
            if (isset($data['_json'])) {
                $instance->jsonData = $data['_json'];
                $instance->isJsonForm = true;
            }
        }

        return $instance;
    }

    /**
     * Unified form loader - loads form by ID (int) or name (string)
     *
     * This is the preferred way to load form definitions as it handles
     * both database forms (by ID) and JSON forms (by name) transparently.
     *
     * @param int|string $formIdOrName Form ID (int) or JSON form name (string)
     * @return self
     */
    public static function loadForm(int|string $formIdOrName): self
    {
        $instance = new self();

        // Determine if this is a JSON form (string name) or database form (int ID)
        if (is_string($formIdOrName) && !is_numeric($formIdOrName)) {
            // JSON form - load by name
            $instance->formIdentifier = $formIdOrName;
            $instance->isJsonForm = true;

            $data = JsonFormLoader::load($formIdOrName);
            if ($data !== null) {
                $instance->data = $data;
                $instance->jsonData = $data['_json'] ?? null;

                // Count fields
                if (isset($data[Q_FIELDNAME]) && Arr::isArray($data[Q_FIELDNAME])) {
                    $instance->rowCount = count($data[Q_FIELDNAME]);
                }
            }
        } else {
            // Database form - load by ID
            $formId = (int)$formIdOrName;
            $instance->formIdentifier = (string)$formId;
            $instance->isJsonForm = false;

            $instance->data = \GetFormDef($formId);

            if (Arr::isArray($instance->data)) {
                // Check for Q_FIELDNAME array (from JSON loader or cache)
                if (isset($instance->data[Q_FIELDNAME]) && Arr::isArray($instance->data[Q_FIELDNAME])) {
                    $instance->rowCount = count($instance->data[Q_FIELDNAME]);
                } elseif ($instance->data instanceof \ArrayObject) {
                    $instance->rowCount = count($instance->data);
                } elseif (isset($instance->data[0])) {
                    $instance->rowCount = count($instance->data[0]);
                }

                // Also capture JSON data if present (from JSON loader)
                if (isset($instance->data['_json'])) {
                    $instance->jsonData = $instance->data['_json'];
                }
            }
        }

        return $instance;
    }

    /**
     * Check if form definition is valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return Arr::isArray($this->data);
    }

    /**
     * Get number of rows (controls) in the form
     *
     * @return int
     */
    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    /**
     * Get raw array data (for backward compatibility)
     *
     * @return array|null
     */
    public function getRawData(): ?array
    {
        return $this->data;
    }

    /**
     * Get a value by column name and row index
     *
     * @param string $column Column name (e.g., 'filterIdName')
     * @param int $row Row index (default: 0)
     * @return mixed Value or null if not found
     */
    public function get(string $column, int $row = 0): mixed
    {
        if (!isset(self::COLUMNS[$column])) {
            return null;
        }

        $colKey = self::COLUMNS[$column];
        return $this->data[$colKey][$row] ?? null;
    }

    /**
     * Check if a column value exists and is not empty
     *
     * @param string $column Column name
     * @param int $row Row index (default: 0)
     * @return bool
     */
    public function has(string $column, int $row = 0): bool
    {
        $value = $this->get($column, $row);
        return $value !== null && $value !== '';
    }

    /**
     * Get a value by Q_ constant (which are now column name strings)
     *
     * @param int|string $colKey Column key - Q_ constant (string column name) or legacy numeric index
     * @param int $row Row index (default: 0)
     * @return mixed
     */
    public function getByIndex(int|string $colKey, int $row = 0): mixed
    {
        return $this->data[$colKey][$row] ?? null;
    }

    /**
     * Get a value by actual database column name (case-insensitive)
     *
     * This bypasses the COLUMNS index mapping and accesses data directly
     * by the column name returned from the database.
     *
     * @param string $dbColumnName Database column name (e.g., 'extraIconURL')
     * @param int $row Row index (default: 0)
     * @return mixed Value or null if not found
     */
    public function getByDbColumn(string $dbColumnName, int $row = 0): mixed
    {
        if ($this->data === null) {
            return null;
        }

        $dbColumnNameLower = strtolower(trim($dbColumnName));

        // For ColumnMajorArray objects - use getColumnNames for reliable access
        if (is_object($this->data) && method_exists($this->data, 'getColumnNames')) {
            $columnNames = $this->data->getColumnNames();

            // Find column with case-insensitive match
            foreach ($columnNames as $colName) {
                if (strtolower(trim((string)($colName ?? ''))) === $dbColumnNameLower) {
                    // Access column data - ColumnMajorArray returns SafeColumnArray
                    $colData = $this->data[$colName];
                    // SafeColumnArray's offsetGet handles missing rows
                    return $colData[$row];
                }
            }

            return null;
        }

        // For regular arrays - try case-insensitive match on array keys
        if (Arr::isArray($this->data)) {
            foreach (array_keys((array)$this->data) as $key) {
                if (is_string($key) && strtolower(trim($key)) === $dbColumnNameLower) {
                    return $this->data[$key][$row] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Get a value using FormField enum (type-safe access)
     *
     * Usage:
     *   $formDef->field(FormField::FieldName, $row)
     *   $formDef->field(FormField::ControlTypeId, $row)
     *
     * @param FormField $field The field enum case
     * @param int $row Row index (default: 0)
     * @return mixed Value or null if not found
     */
    public function field(FormField $field, int $row = 0): mixed
    {
        return $this->data[$field->value][$row] ?? null;
    }

    /**
     * Check if a field value exists and is not empty (using enum)
     *
     * @param FormField $field The field enum case
     * @param int $row Row index (default: 0)
     * @return bool
     */
    public function hasField(FormField $field, int $row = 0): bool
    {
        $value = $this->field($field, $row);
        return $value !== null && $value !== '';
    }

    // =========================================================================
    // Convenience getters for common fields (form-level, typically row 0)
    // =========================================================================

    public function getFormName(): ?string
    {
        return $this->get('formName', 0);
    }

    public function getFormIdField(): ?string
    {
        return $this->get('formIdField', 0);
    }

    public function getSqlTableName(): ?string
    {
        return $this->get('sqlTableName', 0);
    }

    public function getAfterPostUrl(): ?string
    {
        return $this->get('afterPostUrl', 0);
    }

    public function getPreviewUrl(): ?string
    {
        return $this->get('previewUrl', 0);
    }

    public function getCachePrefix(): ?string
    {
        return $this->get('cachePrefix', 0);
    }

    public function getFilterIdName(): ?string
    {
        return $this->get('filterIdName', 0);
    }

    public function getFilterFieldName(): ?string
    {
        // JSON forms store filter in filter.field
        if ($this->jsonData !== null && !empty($this->jsonData['filter']['field'])) {
            return $this->jsonData['filter']['field'];
        }
        return $this->get('filterFieldName', 0);
    }

    public function getFilterCaption(): ?string
    {
        return $this->getByDbColumn('FilterCaption', 0);
    }

    public function getFilterDescription(): ?string
    {
        // JSON forms store filter description in filter.description
        if ($this->jsonData !== null && !empty($this->jsonData['filter']['description'])) {
            return $this->jsonData['filter']['description'];
        }
        return $this->get('filterDescription', 0);
    }

    public function getQuickFields(): ?string
    {
        return $this->get('quickFields', 0);
    }

    public function getOnLoadJs(): ?string
    {
        return $this->get('onLoadJs', 0);
    }

    public function getDatabaseId(): ?int
    {
        $val = $this->get('fkDatabase', 0);
        return $val !== null ? (int)$val : null;
    }

    /**
     * Get connection string for this form's database
     *
     * @return string Connection string
     */
    public function getConnectionString(): string
    {
        $databaseId = $this->getDatabaseId() ?? 0;
        return CmaRepository::getConnectionStringById($databaseId);
    }

    public function getParentFormId(): ?int
    {
        $val = $this->get('parentForm', 0);
        return $val !== null && $val !== '' ? (int)$val : null;
    }

    public function hasMenuNew(): bool
    {
        return (bool)$this->get('menuNew', 0);
    }

    public function hasMenuDelete(): bool
    {
        return (bool)$this->get('menuDelete', 0);
    }

    public function hasMenuCopy(): bool
    {
        return (bool)$this->get('menuCopy', 0);
    }

    public function hasSecurityByUser(): bool
    {
        return (bool)$this->get('securityByUser', 0);
    }

    public function getSecurityByUser(): bool
    {
        return (bool)$this->get('securityByUser', 0);
    }

    public function hasStoreLastModified(): bool
    {
        return (bool)$this->get('storeLastModified', 0);
    }

    public function hasNoSpamJs(): bool
    {
        return (bool)$this->get('noSpamJs', 0);
    }

    // =========================================================================
    // Tree/list-related getters (form-level)
    // These use getByDbColumn for reliable access by database column name
    // =========================================================================

    public function getGroup1Field(): ?string
    {
        return $this->getByDbColumn('Group1Field', 0);
    }

    public function getGroup2Field(): ?string
    {
        return $this->getByDbColumn('Group2Field', 0);
    }

    public function getGroup3Field(): ?string
    {
        return $this->getByDbColumn('Group3Field', 0);
    }

    /**
     * Check if form has grouping configured (for tree view folders)
     * Checks both database forms (Group1Field) and JSON forms (groupFields)
     *
     * @return bool
     */
    public function hasGrouping(): bool
    {
        // Check database form Group1Field
        $group1Field = $this->getGroup1Field();
        if ($group1Field !== null && $group1Field !== '') {
            return true;
        }

        // Check JSON form groupFields
        $jsonData = $this->getJsonData();
        if ($jsonData !== null) {
            $groupFields = $jsonData['groupFields'] ?? [];
            if (!empty($groupFields)) {
                return true;
            }
        }

        return false;
    }

    public function getDetailField(): ?string
    {
        return $this->getByDbColumn('DetailField', 0);
    }

    public function getNameQuery(): ?string
    {
        return $this->getByDbColumn('NameQuery', 0);
    }

    public function getRecurseField(): ?string
    {
        return $this->getByDbColumn('recurseField', 0);
    }

    // =========================================================================
    // Control/field-level getters (per row)
    // =========================================================================

    public function getControlId(int $row): ?int
    {
        $val = $this->get('controlId', $row);
        return $val !== null ? (int)$val : null;
    }

    public function getFieldName(int $row): ?string
    {
        return $this->get('fieldName', $row);
    }

    public function getControlTypeId(int $row): ?int
    {
        $val = $this->get('controlTypeId', $row);
        return $val !== null ? (int)$val : null;
    }

    public function getCaption(int $row): ?string
    {
        return $this->get('caption', $row);
    }

    public function getPostCaption(int $row): ?string
    {
        return $this->get('postCaption', $row);
    }

    public function isRequired(int $row): bool
    {
        return (bool)$this->get('isRequired', $row);
    }

    public function isReadOnly(int $row): bool
    {
        return (bool)$this->get('fieldReadOnly', $row);
    }

    public function isInlineEdit(int $row): bool
    {
        return (bool)$this->get('inlineEdit', $row);
    }

    public function getHeight(int $row): ?int
    {
        $val = $this->get('height', $row);
        return $val !== null && $val !== '' ? (int)$val : null;
    }

    public function getSourceTable(int $row): ?string
    {
        return $this->get('sourceTable', $row);
    }

    public function getSqlList(int $row): ?string
    {
        return $this->get('sqlList', $row);
    }

    public function getImgPath(int $row): ?string
    {
        return $this->get('imgPath', $row);
    }

    public function getXmlSnippet(int $row): ?string
    {
        return $this->get('xmlSnippet', $row);
    }

    public function getAction(int $row): ?string
    {
        return $this->get('action', $row);
    }

    public function isBeheer(int $row): bool
    {
        return (bool)$this->get('isBeheer', $row);
    }

    /**
     * Get schema data type for a field
     *
     * @param int $row Row index
     * @return ?string Data type from schema (e.g., 'datetime', 'nvarchar', 'int')
     */
    public function getSchemaDataType(int $row): ?string
    {
        return $this->get('schemaDataType', $row);
    }

    /**
     * Check if a field is a date/datetime type
     *
     * @param int $row Row index
     * @return bool True if field is a date or datetime type
     */
    public function isDateField(int $row): bool
    {
        $schemaType = $this->getSchemaDataType($row) ?? '';

        // Check string type names (SQL Server, etc.)
        $schemaTypeLower = strtolower($schemaType);
        if (in_array($schemaTypeLower, ['date', 'datetime', 'datetime2', 'smalldatetime', 'datetimeoffset'])) {
            return true;
        }

        // Check ADO type codes: 7=Date, 133=DBDate, 135=DBTimeStamp
        if (is_numeric($schemaType)) {
            $adoType = (int)$schemaType;
            if (in_array($adoType, [7, 133, 135])) {
                return true;
            }
        }

        // Explicit "text" dataType means NOT a date
        if ($schemaTypeLower === 'text') {
            return false;
        }

        return false;
    }

    /**
     * Check if a field is a numeric type
     *
     * @param int $row Row index
     * @return bool True if field is a numeric type (int, float, decimal, etc.)
     */
    public function isNumericField(int $row): bool
    {
        $schemaType = strtolower($this->getSchemaDataType($row) ?? '');
        return in_array($schemaType, [
            'int', 'integer', 'bigint', 'smallint', 'tinyint',
            'float', 'real', 'double', 'double precision',
            'decimal', 'numeric', 'money', 'smallmoney',
            'number', 'currency'
        ]);
    }

    // =========================================================================
    // Extra icons (form-level)
    // =========================================================================

    /**
     * Get extra icon configuration
     *
     * Uses actual database column names for reliable access.
     * DB columns: extraIconURL, extraIconResource, extraIconTitle
     *             extraIcon2URL, extraIcon2Resource, extraIcon2Title, etc.
     *
     * @param int $iconNumber Icon number (1-5)
     * @return array{url: ?string, resource: ?string, title: ?string}
     */
    public function getExtraIcon(int $iconNumber): array
    {
        // Use actual database column names (case-insensitive access)
        // Icon 1 has no number suffix, icons 2-5 have number suffix
        if ($iconNumber === 1) {
            return [
                'url' => $this->getByDbColumn('extraIconURL', 0),
                'resource' => $this->getByDbColumn('extraIconResource', 0),
                'title' => $this->getByDbColumn('extraIconTitle', 0),
            ];
        }
        return [
            'url' => $this->getByDbColumn("extraIcon{$iconNumber}URL", 0),
            'resource' => $this->getByDbColumn("extraIcon{$iconNumber}Resource", 0),
            'title' => $this->getByDbColumn("extraIcon{$iconNumber}Title", 0),
        ];
    }

    // =========================================================================
    // JSON form support and unified accessors
    // =========================================================================

    /**
     * Check if this is a JSON-defined form
     *
     * @return bool
     */
    public function isJsonForm(): bool
    {
        return $this->isJsonForm;
    }

    /**
     * Get the form identifier (name for JSON forms, ID for database forms)
     *
     * @return string|null
     */
    public function getFormIdentifier(): ?string
    {
        return $this->formIdentifier;
    }

    /**
     * Get raw JSON data for JSON forms
     *
     * @return array|null
     */
    public function getJsonData(): ?array
    {
        return $this->jsonData;
    }

    /**
     * Get list query SQL
     *
     * For JSON forms: uses listQuery from JSON definition
     * For database forms: uses NameQuery from form definition
     *
     * @return string|null
     */
    public function getListQuery(): ?string
    {
        // JSON forms have explicit listQuery
        if ($this->jsonData !== null && isset($this->jsonData['listQuery'])) {
            return $this->jsonData['listQuery'];
        }

        // Database forms use NameQuery
        return $this->getNameQuery();
    }

    /**
     * Get list columns configuration
     *
     * For JSON forms: returns explicit listColumns array
     * For database forms: builds columns from form fields
     *
     * @return array Array of column definitions [{field, title, width?, type?}, ...]
     */
    public function getListColumns(): array
    {
        // JSON forms have explicit listColumns
        if ($this->jsonData !== null && !empty($this->jsonData['listColumns'])) {
            return $this->jsonData['listColumns'];
        }

        // Database forms: build from form fields
        $columns = [];
        $skipTypes = [
            FormControlHelper::TYPE_GROUPSEPARATOR,
            FormControlHelper::TYPE_LABEL,
            FormControlHelper::TYPE_CHECKLIST,
            FormControlHelper::TYPE_SORTLIST,
            FormControlHelper::TYPE_IMAGE,
            FormControlHelper::TYPE_FILE,
            FormControlHelper::TYPE_THUMBNAIL,
            FormControlHelper::TYPE_DIRECTORY,
            FormControlHelper::TYPE_XMLSTORE,
            FormControlHelper::TYPE_MEMO,
        ];

        $idField = strtolower($this->getFormIdField() ?? 'ID');

        for ($i = 0; $i < $this->rowCount && count($columns) < 10; $i++) {
            $fieldName = $this->getFieldName($i);
            $controlType = $this->getControlTypeId($i);
            $caption = $this->getCaption($i);

            if (empty($fieldName)) continue;
            if (strtolower($fieldName) === $idField) continue;
            if (in_array($controlType, $skipTypes)) continue;

            $column = [
                'field' => $fieldName,
                'title' => $caption ?: $fieldName,
            ];

            // Add type for special column types
            if ($controlType == FormControlHelper::TYPE_CHECKBOX) {
                $column['type'] = 'boolean';
            } elseif ($this->isDateField($i)) {
                $column['type'] = 'date';
            } elseif ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
                $column['type'] = 'lookup';
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Get list limit (max records to load)
     *
     * For JSON forms: uses 'listLimit' field if set
     * For database forms: returns null (use default)
     *
     * @return int|null Custom limit or null for default
     */
    public function getListLimit(): ?int
    {
        if ($this->jsonData !== null && isset($this->jsonData['listLimit'])) {
            return (int)$this->jsonData['listLimit'];
        }
        return null;
    }

    /**
     * Get the database identifier for connection resolution
     *
     * For JSON forms: uses 'database' field from JSON
     * For database forms: uses fkDatabase
     *
     * @return string|int|null Database identifier
     */
    public function getDatabaseIdentifier(): string|int|null
    {
        // JSON forms use string database identifier
        if ($this->jsonData !== null && isset($this->jsonData['database'])) {
            return $this->jsonData['database'];
        }

        // Database forms use numeric fkDatabase
        return $this->getDatabaseId();
    }

    /**
     * Get the display title for the form
     *
     * @return string
     */
    public function getTitle(): string
    {
        // JSON forms have explicit title
        if ($this->jsonData !== null && !empty($this->jsonData['title'])) {
            return $this->jsonData['title'];
        }

        // Database forms use FormName
        $name = $this->getFormName();
        return $name ? str_replace('_', ' ', $name) : '';
    }

    /**
     * Get the singular form of the title
     * Used for action labels like "Opleiding toevoegen" instead of "Opleidingen toevoegen"
     *
     * @return string
     */
    public function getTitleSingular(): string
    {
        // JSON forms may have explicit titleSingular
        if ($this->jsonData !== null && !empty($this->jsonData['titleSingular'])) {
            return $this->jsonData['titleSingular'];
        }

        // Auto-derive singular from plural Dutch title
        $title = $this->getTitle();
        return self::dutchSingular($title);
    }

    /**
     * Auto-derive singular from Dutch plural title.
     * Common Dutch plural patterns:
     * - "en" suffix: Opleidingen → Opleiding, Docenten → Docent
     * - doubled consonant + "en": Blokken → Blok, Stichtingen → Stichting
     * - "s" suffix: Deelnemers → Deelnemer, Competenties → Competentie
     */
    public static function dutchSingular(string $plural): string
    {
        $len = mb_strlen($plural);
        if ($len <= 3) return $plural;

        // Try "-en" suffix first (most common Dutch plural)
        if (mb_substr($plural, -2) === 'en' && $len > 4) {
            $stem = mb_substr($plural, 0, -2);
            $stemLen = mb_strlen($stem);

            // Check for doubled consonant before "en" (e.g., "Blokken" → "Blok")
            if ($stemLen >= 2) {
                $last = mb_substr($stem, -1);
                $secondLast = mb_substr($stem, -2, 1);
                if ($last === $secondLast && preg_match('/[bcdfgklmnprstvwz]/i', $last)) {
                    return mb_substr($stem, 0, -1);
                }
            }

            return $stem;
        }

        // Try "-s" suffix (e.g., "Deelnemers" → "Deelnemer")
        if (mb_substr($plural, -1) === 's' && $len > 3) {
            return mb_substr($plural, 0, -1);
        }

        return $plural;
    }

    /**
     * Check if adding new records is allowed
     *
     * @return bool
     */
    public function allowAdd(): bool
    {
        // JSON forms
        if ($this->jsonData !== null) {
            return $this->jsonData['allowAdd'] ?? true;
        }

        // Database forms
        return $this->hasMenuNew();
    }

    /**
     * Check if editing existing records is allowed
     *
     * @return bool
     */
    public function allowEdit(): bool
    {
        // JSON forms
        if ($this->jsonData !== null) {
            return $this->jsonData['allowEdit'] ?? true;
        }

        // Database forms - always allow edit if user has access
        // (traditional forms don't have a separate allowEdit setting)
        return true;
    }

    /**
     * Check if deleting records is allowed
     *
     * @return bool
     */
    public function allowDelete(): bool
    {
        // JSON forms
        if ($this->jsonData !== null) {
            return $this->jsonData['allowDelete'] ?? true;
        }

        // Database forms
        return $this->hasMenuDelete();
    }

    /**
     * Check if copying records is allowed
     *
     * @return bool
     */
    public function allowCopy(): bool
    {
        // JSON forms
        if ($this->jsonData !== null) {
            return $this->jsonData['allowCopy'] ?? false;
        }

        // Database forms
        return $this->hasMenuCopy();
    }

    /**
     * Get the order direction for list sorting
     *
     * @return string 'ASC' or 'DESC'
     */
    public function getOrderDirection(): string
    {
        if ($this->jsonData !== null && isset($this->jsonData['orderDirection'])) {
            $dir = strtoupper($this->jsonData['orderDirection']);
            return $dir === 'DESC' ? 'DESC' : 'ASC';
        }
        return 'ASC';
    }

    /**
     * Get the order field name
     *
     * @return string|null The field name to order by
     */
    public function getOrderField(): ?string
    {
        if ($this->jsonData !== null && isset($this->jsonData['orderField'])) {
            return $this->jsonData['orderField'];
        }
        return null;
    }

    /**
     * Get the active field name for tree view indicators
     *
     * Returns the field name that indicates active/inactive status.
     * Used by tree views to show green (active) or red (inactive) icons.
     *
     * @return string|null The field name or null if not set
     */
    public function getActiveField(): ?string
    {
        if ($this->jsonData !== null && isset($this->jsonData['activeField'])) {
            return $this->jsonData['activeField'];
        }
        return null;
    }

    /**
     * Open database connection for this form
     *
     * Handles both JSON forms (string database identifiers) and
     * database forms (numeric fkDatabase IDs).
     *
     * @return mixed Database connection or null on failure
     */
    public function openConnection()
    {
        global $conn, $connrep;

        $dbId = $this->getDatabaseIdentifier();

        if ($this->isJsonForm && is_string($dbId)) {
            // JSON form with string database identifier
            switch (strtolower($dbId)) {
                case 'users':
                    return \App\Library\Database::getConnection('users');
                case 'rep':
                case 'repository':
                    return $connrep;
                case 'data':
                case '':
                default:
                    return $conn;
            }
        }

        // Database form with numeric ID
        $connString = CmaRepository::getConnectionStringById((int)($dbId ?? 0));
        \OpenConnection($connString);
        return $conn;
    }

    /**
     * Get column type name for a field (for filtering/display)
     *
     * @param string $fieldName Field name
     * @return string Type name: 'text', 'boolean', 'date', 'lookup', 'number'
     */
    public function getColumnType(string $fieldName): string
    {
        // Check JSON listColumns for explicit type
        if ($this->jsonData !== null && !empty($this->jsonData['listColumns'])) {
            foreach ($this->jsonData['listColumns'] as $col) {
                if (($col['field'] ?? '') === $fieldName) {
                    return $col['type'] ?? 'text';
                }
            }
        }

        // Find field in form definition
        for ($i = 0; $i < $this->rowCount; $i++) {
            if ($this->getFieldName($i) === $fieldName) {
                $controlType = $this->getControlTypeId($i);

                if ($controlType == FormControlHelper::TYPE_CHECKBOX) {
                    return 'boolean';
                } elseif ($this->isDateField($i)) {
                    return 'date';
                } elseif ($controlType == FormControlHelper::TYPE_COMBOBOX || $controlType == FormControlHelper::TYPE_USERLIST) {
                    return 'lookup';
                }

                // Check if numeric based on schema
                $schemaType = $this->getSchemaDataType($i);
                if (in_array(strtolower($schemaType ?? ''), ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'decimal', 'numeric', 'float', 'real', 'money'])) {
                    return 'number';
                }

                break;
            }
        }

        return 'text';
    }

    /**
     * Get lookup configuration for a field (for combo/dropdown fields)
     *
     * @param string $fieldName Field name
     * @return array|null Lookup config or null if not a lookup field
     */
    public function getFieldLookup(string $fieldName): ?array
    {
        // Check JSON listColumns for inline lookup
        if ($this->jsonData !== null && !empty($this->jsonData['listColumns'])) {
            foreach ($this->jsonData['listColumns'] as $col) {
                if (($col['field'] ?? '') === $fieldName && isset($col['lookup'])) {
                    return $col['lookup'];
                }
            }
        }

        return null;
    }

    /**
     * Find field row index by field name
     *
     * @param string $fieldName Field name to find
     * @return int|null Row index or null if not found
     */
    public function findFieldRow(string $fieldName): ?int
    {
        for ($i = 0; $i < $this->rowCount; $i++) {
            if ($this->getFieldName($i) === $fieldName) {
                return $i;
            }
        }
        return null;
    }
}
