<?php

namespace Cma\Services;

use App\Library\Application;
use App\Library\Cookie;
use App\Library\Database;
use App\Library\Server;
use App\Library\SQL;
use Cma\FormDefinition;
use Cma\JsonFormLoader;
use Cma\ListMode;
use Cma\SecurityHelper;

/**
 * Tree Service
 *
 * Handles tree/hierarchical list display for forms.
 * Extracted from ListService for better maintainability.
 */
class TreeService extends BaseFormService
{
    /**
     * Get tree HTML for list panel (matches list.php GetTree() output exactly)
     *
     * @param int $formId Form ID
     * @param int|null $activeId Currently active record ID
     * @param array $options Options: search, filters
     * @return array ['success' => bool, 'html' => string, 'count' => int]
     */
    public static function getTreeHtml(int $formId, ?int $activeId = null, array $options = []): array
    {
        $startTime = microtime(true);
        $timing = [];
        PerformanceLogger::startTimer('getTreeHtml');

        try {
            // Use consolidated helper - checks access and loads form in one call
            $formResult = self::requireForm($formId);
            if (isset($formResult['error'])) {
                return $formResult;
            }
            $formDef = $formResult['formDef'];
            $arrRep = $formResult['arrRep'];
            $rights = $formResult['accessLevel'];

            // OPTIMIZATION: Use FormDefinition getters instead of redundant SQL query
            $formName = $formDef->getFormName() ?? '';
            $idField = $formDef->getFormIdField() ?? 'ID';
            $listSql = $formDef->getNameQuery() ?? '';
            $group1Field = $formDef->getGroup1Field() ?? '';
            $group2Field = $formDef->getGroup2Field() ?? '';
            $group3Field = $formDef->getGroup3Field() ?? '';
            $detailField = $formDef->getDetailField() ?? '';
            $blnSecurityByUser = $formDef->getSecurityByUser();
            $filterFieldName = $formDef->getFilterFieldName() ?? '';
            $activeField = $formDef->getActiveField();
            $connectionString = $formDef->getConnectionString();

            // Check for NameQuery
            if (empty($listSql)) {
                return self::error('Geen lijst query (NameQuery) beschikbaar voor formulier ' . $formId);
            }

            // Check user-level security
            $blnCheckUser = false;
            if ($blnSecurityByUser) {
                $blnCheckUser = $rights == SecurityHelper::ACCESS_CHANGE_OWN_DATA;
            }

            // Open data connection
            $conn = Database::getConnection($connectionString);

            // Get search/filter options
            $search = $options['search'] ?? '';
            $filters = $options['filters'] ?? [];

            // Determine if filtering is required
            $filterMode = ListMode::FILTER_NONE;
            $filterValue = '';

            // Check if form has forced filtering via FilterFieldName
            if ($filterFieldName !== '' && $filterFieldName !== null && $filterFieldName !== '0' && $search === '') {
                $filterValue = $filters[$filterFieldName] ?? '';
                if ($filterValue === '') {
                    $filterMode = ListMode::FILTER_REPOSITORY_FORCED;
                }
            }

            // If filtering is required but not provided, return empty with filter info
            if ($filterMode === ListMode::FILTER_REPOSITORY_FORCED && $search === '' && $filterValue === '') {
                $displayFormName = str_replace('_', ' ', $formName);
                $filterCaption = $formDef->getFilterCaption() ?? '';

                if ($filterMode === ListMode::FILTER_REPOSITORY_FORCED && $filterCaption !== '') {
                    $cleanCaption = preg_replace('/^selecteer\s+de\s+/i', '', $filterCaption);
                    $cleanCaption = ucfirst($cleanCaption);
                    $message = 'Selecteer een ' . lcfirst($cleanCaption);
                } elseif ($filterMode === ListMode::FILTER_REPOSITORY_FORCED && $filterFieldName !== '') {
                    $message = 'Selecteer ' . $filterFieldName;
                } else {
                    $message = 'Teveel gegevens om allemaal te tonen, gebruik zoeken om gegevens te filteren';
                }

                return [
                    'success' => true,
                    'html' => '<div id="simpletree"><div class="titel">' . $displayFormName . '</div><div class="filter-required">' . htmlspecialchars($message) . '</div></div>',
                    'count' => 0,
                    'filterMode' => $filterMode,
                    'filterFieldName' => $filterFieldName,
                    'requiresFilter' => true,
                ];
            }

            // Apply search filter if provided
            if ($search !== '') {
                if ($detailField !== '') {
                    $listSql = SQL::addWhere($listSql, "[$detailField] LIKE " . SQL::postString('%' . $search . '%'));
                }
            }

            // Apply field-specific filters from search panel
            if (!empty($filters)) {
                $listSql = ListServiceHelper::applySearchFilters($listSql, $filters, $arrRep);
            }

            // Apply TOP limit: use form-specific limit or default (800 records max)
            $formLimit = $formDef->getListLimit() ?? 800;
            $listSql = SQL::addTop($listSql, $formLimit, $conn);

            $timing['prepare'] = round((microtime(true) - $startTime) * 1000, 1);
            $queryStart = microtime(true);

            $rs = Database::openRS($listSql, $conn);
            if ($rs === null) {
                return ListServiceHelper::queryError($listSql);
            }

            $timing['query'] = round((microtime(true) - $queryStart) * 1000, 1);
            $timing['conn_status'] = Database::getLastConnectionStatus();
            $renderStart = microtime(true);

            // Generate tree HTML using array and implode for performance
            $htmlParts = [];
            $count = 0;
            $bSimpleTree = ($group1Field === '');
            $formClass = strtolower(str_replace(' ', '_', $formName ?? ''));
            $displayFormName = str_replace('_', ' ', $formName);

            if ($rs->EOF) {
                $htmlParts[] = '<div id="simpletree"><div class=titel>' . $displayFormName . '</div><div class="no-data">Geen gegevens om weer te geven</div></div>';
            } else {
                if ($bSimpleTree) {
                    $htmlParts[] = '<div id="simpletree"><div class=titel>' . $displayFormName . '</div>';
                }

                // For grouped trees, collect flat items then assemble into tree
                $flatItems = [];

                $prevLevel1 = '';
                $prevLevel2 = '';
                $prevLevel3 = '';

                // Cache lowercase field names before loop
                $idFieldLower = strtolower($idField ?? '');
                $detailFieldLower = (!empty($detailField)) ? strtolower($detailField) : '';
                $group1FieldLower = (!empty($group1Field)) ? strtolower($group1Field) : '';
                $group2FieldLower = (!empty($group2Field)) ? strtolower($group2Field) : '';
                $group3FieldLower = (!empty($group3Field)) ? strtolower($group3Field) : '';
                $activeFieldLower = (!empty($activeField)) ? strtolower($activeField) : '';

                while (!$rs->EOF) {
                    $row = $rs->fields;
                    $rowLower = array_change_key_case($row, CASE_LOWER);
                    $rowLower = \App\Library\Str::toUtf8($rowLower);

                    $recordId = $rowLower[$idFieldLower] ?? '';
                    $detail = ($detailFieldLower !== '') ? ($rowLower[$detailFieldLower] ?? '') : '';
                    $group1 = ($group1FieldLower !== '') ? ($rowLower[$group1FieldLower] ?? '') : '';
                    $group2 = ($group2FieldLower !== '') ? ($rowLower[$group2FieldLower] ?? '') : '';
                    $group3 = ($group3FieldLower !== '') ? ($rowLower[$group3FieldLower] ?? '') : '';

                    $activeValue = ($activeFieldLower !== '') ? ($rowLower[$activeFieldLower] ?? null) : null;
                    $statusClass = '';
                    if ($activeValue !== null) {
                        $statusClass = ($activeValue == 1 || $activeValue === '1' || $activeValue === true) ? 'green' : 'red';
                    }

                    $blnShowIt = true;
                    if ($blnCheckUser) {
                        $blnShowIt = ($rowLower['userid'] ?? '') == Cookie::get(SecurityHelper::COOKIE_USERID, '');
                    }

                    if ($blnShowIt) {
                        $display = '';
                        if ($detailField === '') {
                            foreach ($row as $key => $value) {
                                if (!is_int($key) && strtolower($key) !== $idFieldLower) {
                                    $display .= ',' . $value;
                                }
                            }
                            $display = \App\Library\Date::fixValue(str_replace("\r", ' ', substr($display, 1)));
                        } else {
                            if ($detail !== null && $detail !== '') {
                                $detailStr = is_string($detail) ? $detail : (string)$detail;
                                $display = \App\Library\Date::fixValue(str_replace("\r", ' ', $detailStr));
                            }
                        }

                        if ($bSimpleTree) {
                            $activeClass = ($activeId !== null && $activeId == $recordId) ? ' active' : '';
                            $statusAttr = ($statusClass !== '') ? ' ' . $statusClass : '';
                            $htmlParts[] = '<a href="javascript:void(0)" class="' . $formClass . $activeClass . $statusAttr . '" target="R" data-id="' . htmlspecialchars($recordId) . '">' . htmlspecialchars($display) . '</a>';
                        } else {
                            // Collect flat item with group keys for later tree assembly
                            $item = [
                                'label' => $display,
                                'id' => $recordId,
                                'g1' => $group1,
                                'g2' => $group2,
                                'g3' => $group3,
                            ];
                            if ($statusClass !== '') {
                                $item['active'] = ($statusClass === 'green') ? 1 : 0;
                            }
                            $flatItems[] = $item;
                        }

                        $count++;
                    }

                    $rs->MoveNext();
                }

                if ($bSimpleTree) {
                    $htmlParts[] = '</div>';
                }
            }

            $html = implode('', $htmlParts);

            $timing['render'] = round((microtime(true) - $renderStart) * 1000, 1);
            $timing['total'] = round((microtime(true) - $startTime) * 1000, 1);
            $timing['rows'] = $count;

            PerformanceLogger::endTimer('getTreeHtml', [
                'formId' => $formId,
                'count' => $count,
                'hasGrouping' => !empty($group1Field),
            ]);

            $result = [
                'success' => true,
                'html' => $html,
                'count' => $count,
                'hasGrouping' => !empty($group1Field),
                'sql' => $listSql,
                '_timing' => $timing,
            ];

            // For grouped trees, assemble JSON tree from flat items
            if (!$bSimpleTree && !empty($flatItems)) {
                $treeData = self::buildTreeFromFlat($flatItems, $group1Field, $group2Field, $group3Field);
                if (!empty($treeData)) {
                    $result['treeData'] = $treeData;
                    $result['treeTitle'] = $displayFormName;
                    $result['html'] = '';
                }
                // Sanity: if tree building failed but we have items, log and keep html empty
                // so the client knows something went wrong
                if (empty($treeData) && $count > 0) {
                    error_log("TreeService: buildTreeFromFlat returned empty for {$count} items (formId={$formId}, group1={$group1Field})");
                }
            }

            return $result;
        } catch (\Exception $e) {
            PerformanceLogger::endTimer('getTreeHtml', ['error' => $e->getMessage()]);
            return self::error($e->getMessage());
        }
    }

    /**
     * Get tree HTML for JSON form
     *
     * @param string $formName JSON form name
     * @param string|int|null $activeId Active record ID (can be GUID or integer)
     * @param array $options Options
     * @return array ['success' => bool, 'html' => string, 'count' => int]
     */
    public static function getJsonFormTreeHtml(string $formName, string|int|null $activeId = null, array $options = []): array
    {
        try {
            // Load JSON form definition
            $formDef = JsonFormLoader::load($formName);
            if ($formDef === null) {
                return self::error("Formulier '$formName' niet gevonden");
            }

            $jsonData = $formDef['_json'] ?? [];
            $tableName = $jsonData['table'] ?? '';
            $idField = $jsonData['idField'] ?? 'ID';
            $database = $jsonData['database'] ?? '';
            $listQuery = $jsonData['listQuery'] ?? '';
            $listColumns = $jsonData['listColumns'] ?? [];
            $title = $jsonData['title'] ?? $formName;
            $groupFields = $jsonData['groupFields'] ?? [];
            $detailField = $jsonData['detailField'] ?? '';

            // Check access rights using sourceFormId if available
            $sourceFormId = $jsonData['sourceFormId'] ?? null;
            if ($sourceFormId) {
                $accessError = self::checkFormAccess($sourceFormId);
                if ($accessError !== null) {
                    return $accessError;
                }
            } elseif (!SecurityHelper::isAdmin()) {
                return self::error('Geen toegang tot dit formulier');
            }

            // Check if this is a JSON config form
            if ($database === 'json') {
                return self::getJsonConfigTreeHtml($formName, $activeId, $options, $jsonData);
            }

            if (empty($tableName) && empty($listQuery)) {
                return self::error('Formulier heeft geen tabel gedefinieerd');
            }

            // Check if filtering is required
            // Note: filterIdName is for passing filter TO subforms, not requiring filter on THIS form
            $filterFieldName = $jsonData['filter']['field'] ?? '';
            $filterDescription = $jsonData['filter']['description'] ?? '';
            $search = $options['search'] ?? '';
            $filters = $options['filters'] ?? [];

            if ($filterFieldName !== '' && $search === '') {
                $filterValue = $filters[$filterFieldName] ?? '';
                if ($filterValue === '') {
                    $displayFormName = $jsonData['title'] ?? ucfirst(str_replace('_', ' ', $formName));
                    $message = $filterDescription !== '' ? $filterDescription : 'Selecteer een ' . lcfirst(str_replace('fk', '', $filterFieldName));

                    return [
                        'success' => true,
                        'html' => '<div id="simpletree"><div class="titel">' . Server::htmlEncode($displayFormName) . '</div><div class="filter-required">' . Server::htmlEncode($message) . '</div></div>',
                        'count' => 0,
                        'filterMode' => ListMode::FILTER_REPOSITORY_FORCED,
                        'filterFieldName' => $filterFieldName,
                        'requiresFilter' => true,
                    ];
                }
            }

            // Open database connection
            $conn = ListServiceHelper::getJsonFormConnection($database);
            if ($conn === null) {
                return self::error('Database connectie mislukt');
            }

            // Build query
            if (!empty($listQuery)) {
                $sql = $listQuery;
            } else {
                $columns = self::buildJsonColumnList($listColumns, $idField, $detailField, $groupFields);
                $sql = "SELECT $columns FROM [$tableName]";
            }

            // Apply search filter
            if ($search !== '' && !empty($listColumns)) {
                $searchConditions = [];
                foreach ($listColumns as $col) {
                    $fieldName = $col['field'] ?? '';
                    if ($fieldName) {
                        $searchConditions[] = "[$fieldName] LIKE " . SQL::postString('%' . $search . '%');
                    }
                }
                if (!empty($searchConditions)) {
                    if (stripos($sql, ' WHERE ') !== false) {
                        $sql .= ' AND (' . implode(' OR ', $searchConditions) . ')';
                    } else {
                        $sql .= ' WHERE (' . implode(' OR ', $searchConditions) . ')';
                    }
                }
            }

            // Apply field-specific filters
            if (!empty($filters)) {
                $rawFormDef = JsonFormLoader::loadRaw($formName);
                if ($rawFormDef) {
                    $sql = ListServiceHelper::applyJsonFormFilters($sql, $filters, $rawFormDef);
                }
            }

            // Execute query
            $rs = Database::openRS($sql, $conn);
            if ($rs === null) {
                return self::error('Query uitvoering mislukt: ' . Database::getLastError() . "\n" . $sql);
            }

            // Determine display field
            $displayFieldName = $detailField ?: '';
            if (empty($displayFieldName) && !empty($listColumns)) {
                $excludeFields = array_map('strtolower', array_filter(array_merge([$idField], $groupFields ?? [])));
                foreach ($listColumns as $col) {
                    $field = $col['field'] ?? '';
                    if ($field && !in_array(strtolower($field), $excludeFields, true)) {
                        $displayFieldName = $field;
                        break;
                    }
                }
            }

            // Build tree
            $formClass = strtolower(str_replace(' ', '_', $formName ?? ''));
            $htmlParts = [];
            $bSimpleTree = empty($groupFields);

            if ($bSimpleTree) {
                $htmlParts[] = '<div id="simpletree"><div class="titel">' . Server::htmlEncode($title) . '</div>';
            }

            // For grouped trees, collect flat items then assemble into tree
            $flatItems = [];

            $count = 0;
            $prevLevel1 = '';
            $prevLevel2 = '';
            $prevLevel3 = '';
            $group1Field = $groupFields[0] ?? '';
            $group2Field = $groupFields[1] ?? '';
            $group3Field = $groupFields[2] ?? '';

            $fieldMapBuilt = false;
            $fieldMap = [];

            while (!$rs->EOF) {
                $fields = \App\Library\Str::toUtf8($rs->fetchAssoc());

                if (!$fieldMapBuilt) {
                    foreach ($fields as $key => $value) {
                        if (!is_int($key)) {
                            $fieldMap[strtolower($key)] = $key;
                        }
                    }
                    $fieldMapBuilt = true;
                }

                $getField = function($fieldName) use ($fields, $fieldMap) {
                    if (!$fieldName) return '';
                    if (isset($fields[$fieldName])) return $fields[$fieldName];
                    $lower = strtolower($fieldName);
                    if (isset($fieldMap[$lower]) && isset($fields[$fieldMap[$lower]])) {
                        return $fields[$fieldMap[$lower]];
                    }
                    return '';
                };

                $recordId = $getField($idField) ?: ($fields[$idField] ?? '');

                $display = '';
                if ($displayFieldName) {
                    $display = $getField($displayFieldName);
                }
                if ($display === '' && $detailField) {
                    $display = $getField($detailField);
                }
                if ($display === '') {
                    foreach ($fields as $key => $value) {
                        if (!is_int($key) && strtolower($key) !== strtolower($idField)) {
                            $display = $value;
                            break;
                        }
                    }
                }

                $group1 = $group1Field ? $getField($group1Field) : '';
                $group2 = $group2Field ? $getField($group2Field) : '';
                $group3 = $group3Field ? $getField($group3Field) : '';

                $display = (string)$display;

                if ($bSimpleTree) {
                    $activeClass = ($activeId !== null && $recordId == $activeId) ? ' active' : '';
                    $htmlParts[] = '<a href="javascript:void(0)" class="' . $formClass . $activeClass . '" target="R" data-id="' . htmlspecialchars($recordId) . '">' . htmlspecialchars($display) . '</a>';
                } else {
                    // Collect flat item with group keys for later tree assembly
                    $flatItems[] = [
                        'label' => $display,
                        'id' => $recordId,
                        'g1' => $group1,
                        'g2' => $group2,
                        'g3' => $group3,
                    ];
                }

                $count++;
                $rs->MoveNext();
            }

            if ($bSimpleTree) {
                if ($count === 0) {
                    $htmlParts[] = '<div class="no-data">Geen gegevens gevonden</div>';
                }
                $htmlParts[] = '</div>';
            }

            $html = implode('', $htmlParts);
            $hasFullAccess = SecurityHelper::isAdmin();

            $result = [
                'success' => true,
                'html' => $html,
                'count' => $count,
                'displayMode' => ListMode::DISPLAY_TREE,
                'hasGrouping' => !empty($group1Field),
                'permissions' => [
                    'canAdd' => ($jsonData['allowAdd'] ?? true) && $hasFullAccess,
                    'canEdit' => ($jsonData['allowEdit'] ?? true) && $hasFullAccess,
                    'canCopy' => ($jsonData['allowCopy'] ?? false) && $hasFullAccess,
                    'canDelete' => ($jsonData['allowDelete'] ?? true) && $hasFullAccess,
                ],
            ];

            // For grouped trees, assemble JSON tree from flat items
            if (!$bSimpleTree && !empty($flatItems)) {
                $treeData = self::buildTreeFromFlat($flatItems, $group1Field, $group2Field, $group3Field);
                if (!empty($treeData)) {
                    $result['treeData'] = $treeData;
                    $result['treeTitle'] = $title;
                    $result['html'] = '';
                }
                if (empty($treeData) && $count > 0) {
                    error_log("TreeService: buildTreeFromFlat returned empty for {$count} items (form={$formName}, group1={$group1Field})");
                }
            }

            return $result;

        } catch (\Exception $e) {
            return self::error($e->getMessage());
        }
    }

    /**
     * Build explicit column list from JSON form listColumns definition
     */
    /**
     * Build a nested tree structure from flat items with group keys.
     * Each item has g1/g2/g3 keys for up to 3 levels of grouping.
     * Returns array suitable for cma-tree JSON data attribute.
     */
    private static function buildTreeFromFlat(array $flatItems, string $group1Field, string $group2Field, string $group3Field): array
    {
        $tree = [];
        $folders1 = [];
        $folders2 = [];
        $folders3 = [];

        foreach ($flatItems as $fi) {
            $node = [
                'type' => 'item',
                'label' => $fi['label'],
                'id' => $fi['id'],
                'href' => 'javascript:void(0)',
                'target' => 'R',
            ];
            if (isset($fi['active'])) {
                $node['active'] = $fi['active'];
            }

            // Cast to string — group values can be 0, false, null from database
            $g1 = (string)($fi['g1'] ?? '');
            $g2 = (string)($fi['g2'] ?? '');
            $g3 = (string)($fi['g3'] ?? '');

            if ($group1Field === '' || $g1 === '') {
                // No grouping or empty group — add to root (possibly in [leeg] folder)
                if ($group1Field !== '') {
                    if (!isset($folders1['[leeg]'])) {
                        $folders1['[leeg]'] = count($tree);
                        $tree[] = ['type' => 'folder', 'label' => '[leeg]', 'children' => []];
                    }
                    $tree[$folders1['[leeg]']]['children'][] = $node;
                } else {
                    $tree[] = $node;
                }
                continue;
            }

            // Level 1 folder
            if (!isset($folders1[$g1])) {
                $folders1[$g1] = count($tree);
                $tree[] = ['type' => 'folder', 'label' => \App\Library\Date::fixValue($g1), 'children' => []];
            }
            $f1Idx = $folders1[$g1];

            if ($group2Field === '' || $g2 === '') {
                $tree[$f1Idx]['children'][] = $node;
                continue;
            }

            // Level 2 folder
            $g2Key = $g1 . '|' . $g2;
            if (!isset($folders2[$g2Key])) {
                $folders2[$g2Key] = count($tree[$f1Idx]['children']);
                $tree[$f1Idx]['children'][] = ['type' => 'folder', 'label' => \App\Library\Date::fixValue($g2), 'children' => []];
            }
            $f2Idx = $folders2[$g2Key];

            if ($group3Field === '' || $g3 === '') {
                $tree[$f1Idx]['children'][$f2Idx]['children'][] = $node;
                continue;
            }

            // Level 3 folder
            $g3Key = $g2Key . '|' . $g3;
            if (!isset($folders3[$g3Key])) {
                $folders3[$g3Key] = count($tree[$f1Idx]['children'][$f2Idx]['children']);
                $tree[$f1Idx]['children'][$f2Idx]['children'][] = ['type' => 'folder', 'label' => \App\Library\Date::fixValue($g3), 'children' => []];
            }
            $f3Idx = $folders3[$g3Key];
            $tree[$f1Idx]['children'][$f2Idx]['children'][$f3Idx]['children'][] = $node;
        }

        return $tree;
    }

    private static function buildJsonColumnList(array $listColumns, string $idField, string $detailField = '', array $groupFields = []): string
    {
        $columns = [];
        $addedFields = [];

        $columns[] = "[$idField]";
        $addedFields[strtolower($idField)] = true;

        if ($detailField !== '' && !isset($addedFields[strtolower($detailField)])) {
            $columns[] = "[$detailField]";
            $addedFields[strtolower($detailField)] = true;
        }

        foreach ($groupFields as $groupField) {
            if ($groupField !== '' && !isset($addedFields[strtolower($groupField)])) {
                $columns[] = "[$groupField]";
                $addedFields[strtolower($groupField)] = true;
            }
        }

        foreach ($listColumns as $col) {
            $fieldName = $col['field'] ?? '';
            if ($fieldName && !isset($addedFields[strtolower($fieldName)])) {
                $columns[] = "[$fieldName]";
                $addedFields[strtolower($fieldName)] = true;
            }
        }

        if (count($columns) <= 1) {
            return '*';
        }

        return implode(', ', $columns);
    }

    /**
     * Get tree HTML for JSON config form (data stored in JSON files)
     */
    private static function getJsonConfigTreeHtml(string $formName, ?int $activeId, array $options, array $jsonData): array
    {
        // Pass filters from options to ConfigFormService
        $filters = $options['filters'] ?? [];
        $result = ConfigFormService::getListData($formName, $filters);
        if (!$result['success']) {
            return $result;
        }

        $items = $result['data'] ?? [];
        $title = $jsonData['title'] ?? $formName;
        $idField = $jsonData['idField'] ?? 'id';
        $listColumns = $jsonData['listColumns'] ?? [];

        $displayField = '';
        if (!empty($listColumns)) {
            foreach ($listColumns as $col) {
                $field = $col['field'] ?? '';
                if ($field && strtolower($field) !== strtolower($idField)) {
                    $displayField = $field;
                    break;
                }
            }
        }

        $search = strtolower($options['search'] ?? '');
        if ($search !== '') {
            $items = array_filter($items, function($item) use ($search, $idField) {
                // Search in parent form fields
                foreach ($item as $key => $value) {
                    if ($key === '_subforms') continue;
                    if (strtolower($key) !== strtolower($idField) && is_string($value)) {
                        if (stripos($value, $search) !== false) {
                            return true;
                        }
                    }
                }
                // Also match if any subform name/title matches
                foreach ($item['_subforms'] ?? [] as $sub) {
                    if (stripos($sub['form'] ?? '', $search) !== false ||
                        stripos($sub['title'] ?? '', $search) !== false) {
                        return true;
                    }
                }
                return false;
            });
        }

        $formClass = strtolower(str_replace(' ', '_', $formName));
        $html = '<div id="simpletree"><div class="titel">' . Server::htmlEncode($title) . '</div>';

        $count = 0;
        foreach ($items as $item) {
            $recordId = $item[$idField] ?? $item['id'] ?? '';
            $display = '';
            if ($displayField && isset($item[$displayField])) {
                $display = $item[$displayField];
            } else {
                foreach ($item as $key => $value) {
                    if (strtolower($key) !== strtolower($idField) && strtolower($key) !== 'id' && is_scalar($value)) {
                        $display = $value;
                        break;
                    }
                }
            }

            $display = Server::htmlEncode((string)$display);
            $activeClass = ($activeId !== null && $recordId == $activeId) ? ' active' : '';

            // Check if item has subforms (parent form with children)
            $subforms = $item['_subforms'] ?? [];
            if (!empty($subforms)) {
                // Render as collapsible folder with subforms
                $html .= '<details class="tree-folder" open>';
                $html .= '<summary>';
                $html .= '<a href="javascript:void(0)" class="' . $formClass . $activeClass . '" target="R" data-id="' . htmlspecialchars($recordId) . '">' . $display . '</a>';
                $html .= '</summary>';
                $html .= '<div class="tree-folder-children">';
                foreach ($subforms as $sub) {
                    $subId = $sub['form'] ?? '';
                    $subDisplay = Server::htmlEncode($sub['title'] ?? $subId);
                    $subActiveClass = ($activeId !== null && $subId == $activeId) ? ' active' : '';
                    $html .= '<a href="javascript:void(0)" class="' . $formClass . ' tree-subform' . $subActiveClass . '" target="R" data-id="' . htmlspecialchars($subId) . '">' . $subDisplay . '</a>';
                    $count++;
                }
                $html .= '</div></details>';
            } else {
                $html .= '<a href="javascript:void(0)" class="' . $formClass . $activeClass . '" target="R" data-id="' . htmlspecialchars($recordId) . '">' . $display . '</a>';
            }
            $count++;
        }

        if ($count === 0) {
            $html .= '<div class="no-data">Geen gegevens gevonden</div>';
        }

        $html .= '</div>';
        $hasFullAccess = SecurityHelper::isAdmin();

        // Build field definitions for field chooser (same logic as JsonFormService table view)
        $fieldDefs = [];
        $skipTypes = ['groupseparator', 'label', 'hidden', 'password', 'image', 'file', 'checklist', 'sortlist', 'checklisttree', 'checklistinline', 'custom', 'tip', 'ignorefield'];
        $idField = $jsonData['idField'] ?? 'id';
        foreach ($jsonData['fields'] ?? [] as $field) {
            $fieldName = $field['name'] ?? '';
            $fieldType = $field['type'] ?? 'textbox';
            if (empty($fieldName)) continue;
            if (in_array($fieldType, $skipTypes)) continue;
            if (strtolower($fieldName) === strtolower($idField)) continue;
            if (str_starts_with($fieldName, '_')) continue;

            $fieldDefs[] = [
                'name' => $fieldName,
                'caption' => $field['caption'] ?? $fieldName,
                'type' => $fieldType,
            ];
        }

        return [
            'success' => true,
            'html' => $html,
            'count' => $count,
            'displayMode' => ListMode::DISPLAY_TREE,
            'fields' => $fieldDefs,
            'permissions' => [
                'canAdd' => ($jsonData['allowAdd'] ?? true) && $hasFullAccess,
                'canEdit' => ($jsonData['allowEdit'] ?? true) && $hasFullAccess,
                'canCopy' => ($jsonData['allowCopy'] ?? false) && $hasFullAccess,
                'canDelete' => ($jsonData['allowDelete'] ?? true) && $hasFullAccess,
            ],
        ];
    }
}
