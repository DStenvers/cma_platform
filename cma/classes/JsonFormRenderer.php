<?php

namespace Cma;

use App\Library\Database;
use App\Library\Server;
use Cma\Services\MenuService;
use Cma\Services\ReportsService;

/**
 * Custom Field Renderers for JSON Form Definitions
 *
 * Handles special field types that require custom rendering logic:
 * - form_notifications: Tree of forms for notification subscriptions
 * - data_notifications: Data alert subscriptions
 * - group_menu_rights: Menu/Form access rights matrix
 * - group_report_rights: Report access rights checklist
 */
class JsonFormRenderer
{
    /**
     * Render a custom field based on the renderer name
     *
     * @param string $renderer Renderer name from JSON definition
     * @param string $fieldName Field name
     * @param array $config Field configuration
     * @param mixed $value Current value
     * @param int|string|null $recordId Record ID (for relationship queries)
     * @return string HTML output
     */
    public static function render(string $renderer, string $fieldName, array $config, $value = null, $recordId = null): string
    {
        switch ($renderer) {
            case 'form_notifications':
                return self::renderFormNotifications($fieldName, $config, $recordId);
            case 'data_notifications':
                return self::renderDataNotifications($fieldName, $config, $recordId);
            case 'group_menu_rights':
                return self::renderGroupMenuRights($fieldName, $config, $recordId);
            case 'group_report_rights':
                return self::renderGroupReportRights($fieldName, $config, $recordId);
            case 'security_groups':
                return self::renderSecurityGroups($fieldName, $config, $recordId);
            case 'group_members':
                return self::renderGroupMembers($fieldName, $config, $recordId);
            default:
                return '<div class="small">Unknown renderer: ' . Server::htmlEncode($renderer) . '</div>';
        }
    }

    /**
     * Render form notifications tree (for user form)
     * Shows a tree of forms grouped by menu that the user can subscribe to
     */
    private static function renderFormNotifications(string $fieldName, array $config, $recordId): string
    {
        // Get users database connection (tblNotifications is in users db)
        $usersConn = Database::getConnection('users');

        // Get user's current notification subscriptions
        $subscribed = [];
        if ($recordId && $usersConn) {
            $sql = "SELECT fkFormID FROM tblNotifications WHERE fkUserID = " . (int)$recordId;
            $rs = Database::openRS($sql, $usersConn);
            if ($rs) {
                while (!$rs->EOF) {
                    $subscribed[$rs->fields['fkFormID']] = true;
                    $rs->MoveNext();
                }
            }
        }

        // Get all visible forms grouped by menu from MenuService
        $menus = MenuService::getMenus();

        if (empty($menus)) {
            return '<div class="small">Kan formulieren niet laden</div>';
        }

        $html = '<div class="checklist-tree" data-field="' . Server::htmlEncode($fieldName) . '">';

        foreach ($menus as $menu) {
            $menuName = $menu['name'] ?? '';
            $items = $menu['items'] ?? [];

            // Filter to items with formId only
            $formItems = array_filter($items, fn($item) => !empty($item['formId']));

            if (empty($formItems)) {
                continue;
            }

            $html .= '<div class="checklist-group">';
            // Capitalize first letter only
            $displayName = ucfirst(strtolower($menuName));
            $html .= '<div class="checklist-group-header">' . Server::htmlEncode($displayName) . '</div>';

            foreach ($formItems as $item) {
                $formId = $item['formId'];
                $formName = $item['form'] ?? $item['name'] ?? '';

                $checked = isset($subscribed[$formId]) ? ' checked' : '';
                $html .= '<label class="checklist-item">';
                $html .= '<input type="checkbox" name="' . Server::htmlEncode($fieldName) . '[]" value="' . $formId . '"' . $checked . '>';
                $html .= ' ' . Server::htmlEncode($formName);
                $html .= '</label>';
            }

            $html .= '</div>'; // Close group
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render data notifications (for user form)
     * Shows data sources that can trigger alerts
     */
    private static function renderDataNotifications(string $fieldName, array $config, $recordId): string
    {
        $connrep = Database::getRepConnection();

        if ($recordId === null || $recordId === '') {
            return '<div class="small">Opslaan om data meldingen te configureren</div>';
        }

        // Get data notification sources
        $sql = "SELECT ID, SourceName, SourceDescription FROM tblDataNotificationSources ORDER BY SourceName";
        $rs = Database::openRS($sql, $connrep);

        if ($rs === null || $rs->EOF) {
            return '<div class="small">Geen data bronnen geconfigureerd</div>';
        }

        // Get user's current subscriptions
        $subscribed = [];
        $subSql = "SELECT fkSourceID FROM tblDataNotificationSubscriptions WHERE fkUserID = " . (int)$recordId;
        $subRs = Database::openRS($subSql, $connrep);
        if ($subRs) {
            while (!$subRs->EOF) {
                $subscribed[$subRs->fields['fkSourceID']] = true;
                $subRs->MoveNext();
            }
        }

        $html = '<div class="checklist" data-field="' . Server::htmlEncode($fieldName) . '">';

        while (!$rs->EOF) {
            $sourceId = $rs->fields['ID'];
            $sourceName = $rs->fields['SourceName'];
            $sourceDesc = $rs->fields['SourceDescription'] ?? '';

            $checked = isset($subscribed[$sourceId]) ? ' checked' : '';
            $html .= '<label class="checklist-item">';
            $html .= '<input type="checkbox" name="' . Server::htmlEncode($fieldName) . '[]" value="' . $sourceId . '"' . $checked . '>';
            $html .= ' ' . Server::htmlEncode($sourceName);
            if ($sourceDesc) {
                $html .= '<span class="checklist-hint">' . Server::htmlEncode($sourceDesc) . '</span>';
            }
            $html .= '</label>';

            $rs->MoveNext();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render group menu rights matrix (for groups form)
     * Shows a matrix of menus/forms with access level radio buttons and button checkboxes.
     * Includes subforms with proper hierarchy and indentation.
     */
    private static function renderGroupMenuRights(string $fieldName, array $config, $recordId): string
    {
        // tblGroupRights is in users database
        $usersConn = Database::getConnection('users');

        $options = $config['options'] ?? [];

        // Access level columns (use proper security constants)
        $columns = $options['columns'] ?? [
            ['value' => 0, 'label' => 'Geen'],
            ['value' => 10, 'label' => 'Lezen'],
            ['value' => 30, 'label' => 'Volledig'],
        ];

        // Button columns (5 custom buttons per form)
        $showButtons = $options['includeExtraButtons'] ?? true;
        $buttonLabels = ['Knop 1', 'Knop 2', 'Knop 3', 'Knop 4', 'Knop 5'];

        // Get current rights for this group (menu type = 10, form type = 30)
        $rights = [];
        $buttonRights = [];
        if ($recordId && $usersConn) {
            $sql = "SELECT secObjectID, secObjectType, secAccessType, " .
                "secButton1, secButton2, secButton3, secButton4, secButton5 " .
                "FROM tblGroupRights WHERE fkGroup = " . (int)$recordId .
                " AND secObjectType IN (10, 30)"; // 10 = menu, 30 = form
            $rs = Database::openRS($sql, $usersConn);
            if ($rs) {
                while (!$rs->EOF) {
                    $objId = $rs->fields['secObjectID'];
                    $objType = $rs->fields['secObjectType'];
                    $key = $objType . '_' . $objId;
                    $rights[$key] = (int)$rs->fields['secAccessType'];
                    $buttonRights[$key] = [
                        (bool)$rs->fields['secButton1'],
                        (bool)$rs->fields['secButton2'],
                        (bool)$rs->fields['secButton3'],
                        (bool)$rs->fields['secButton4'],
                        (bool)$rs->fields['secButton5'],
                    ];
                    $rs->MoveNext();
                }
            }
        }

        // Get all menu items with forms from MenuService
        $menus = MenuService::getMenus();
        $menuGroups = []; // Group by main menu

        // Get securityByUser map from JSON definitions (no more tblForms query)
        $formSecurityByUser = JsonFormLoader::getFormSecurityByUserMap();

        foreach ($menus as $menu) {
            $mainMenuName = $menu['name'] ?? '';
            $mainMenuId = $menu['id'] ?? 0;
            $menuItems = [];

            foreach ($menu['items'] ?? [] as $item) {
                if (!isset($item['visible']) || $item['visible'] !== false) {
                    $formId = $item['formId'] ?? null;
                    $itemName = $item['name'] ?? '';
                    $legacyFormName = $item['form'] ?? '';  // Legacy form display name
                    $jsonFormName = $item['formName'] ?? ''; // JSON form identifier

                    // Build display name priority:
                    // 1. Item name from menu config
                    // 2. Legacy form display name
                    // 3. JSON form name (converted to readable: "rooster" → "Rooster")
                    // 4. Fallback to parent menu name in brackets
                    $displayName = $itemName ?: $legacyFormName;
                    if (empty($displayName) && !empty($jsonFormName)) {
                        // Convert form identifier to readable name: underscores to spaces, capitalize first letter
                        $displayName = ucfirst(str_replace('_', ' ', $jsonFormName));
                    }
                    if (empty($displayName)) {
                        $displayName = "[$mainMenuName]";
                    }

                    // Get button labels from form definition's extraButtons
                    $buttonLabels = [];
                    $formDef = null;

                    // Try JSON definition first
                    if (!empty($jsonFormName)) {
                        $formDef = JsonFormLoader::loadFormDefinition($jsonFormName);
                    }

                    // Fall back to database if no JSON definition and we have a formId
                    if (!$formDef && $formId) {
                        $formDef = JsonFormLoader::exportFromDatabase($formId);
                    }

                    if ($formDef && !empty($formDef['extraButtons'])) {
                        foreach ($formDef['extraButtons'] as $idx => $btn) {
                            $buttonLabels[$idx] = $btn['title'] ?? '';
                        }
                    }

                    $menuItems[] = [
                        'menuId' => $item['id'] ?? 0,
                        'formId' => $formId,
                        'menuName' => $itemName,
                        'formName' => $legacyFormName ?: $jsonFormName, // Store whichever form name is available
                        'jsonFormName' => $jsonFormName, // Keep JSON form name separately for debug
                        'displayName' => $displayName,
                        'hasSecurityByUser' => $formId ? ($formSecurityByUser[$formId] ?? false) : false,
                        'buttonLabels' => $buttonLabels, // Button labels from form definition
                    ];
                }
            }

            if (!empty($menuItems)) {
                $menuGroups[] = [
                    'mainMenuName' => $mainMenuName,
                    'mainMenuId' => $mainMenuId,
                    'items' => $menuItems,
                ];
            }
        }

        // Get subforms from JSON definitions (no more tblSubForms query)
        $subforms = [];
        if ($options['includeSubforms'] ?? true) {
            $subforms = JsonFormLoader::getSubformsMap();
        }

        if (empty($menuGroups)) {
            return '<div class="small">Kan menu items niet laden</div>';
        }

        // Track which button columns have content (for hiding empty columns)
        // Start with all false and set to true when we find buttons
        $usedButtonColumns = [false, false, false, false, false];
        $defaultButtonLabels = ['Knop 1', 'Knop 2', 'Knop 3', 'Knop 4', 'Knop 5'];

        // Scan all menu items for button labels to determine which columns are used
        foreach ($menuGroups as $menuGroup) {
            foreach ($menuGroup['items'] as $item) {
                $buttonLabels = $item['buttonLabels'] ?? [];
                foreach ($buttonLabels as $idx => $label) {
                    if ($idx < 5 && !empty($label)) {
                        $usedButtonColumns[$idx] = true;
                        // Update default label if we have a real label
                        $defaultButtonLabels[$idx] = $label;
                    }
                }
            }
        }

        // Calculate number of used button columns
        $usedButtonCount = array_sum($usedButtonColumns);
        $totalCols = 1 + count($columns) + ($showButtons ? $usedButtonCount : 0);

        // Build button column visibility classes
        $hiddenButtonCols = [];
        for ($i = 0; $i < 5; $i++) {
            if (!$usedButtonColumns[$i]) {
                $hiddenButtonCols[] = 'hide-btn-' . $i;
            }
        }

        // Build HTML table
        $html = '<div class="rights-matrix-container">';
        $html .= '<table class="rights-matrix' . (!empty($hiddenButtonCols) ? ' ' . implode(' ', $hiddenButtonCols) : '') . '">';
        $html .= '<thead><tr><th class="label-col">Menu / Formulier</th>';
        foreach ($columns as $col) {
            $html .= '<th class="access-col">' . Server::htmlEncode($col['label']) . '</th>';
        }
        if ($showButtons && $usedButtonCount > 0) {
            $html .= '<th class="button-col" colspan="' . $usedButtonCount . '">Extra knoppen</th>';
        }
        $html .= '</tr></thead><tbody>';

        // Render grouped menu items with section headers
        foreach ($menuGroups as $groupIdx => $group) {
            // Section header row with bulk-set radio buttons
            $groupId = 'group_' . $groupIdx;
            $html .= '<tr class="section-header" data-group-id="' . $groupId . '">';
            $html .= '<td class="label-col"><strong>' . Server::htmlEncode($group['mainMenuName']) . '</strong></td>';
            // Add radio buttons for bulk selection
            foreach ($columns as $col) {
                $html .= '<td class="access-col">';
                $html .= '<input type="radio" name="' . $fieldName . '_header_' . $groupId . '" ';
                $html .= 'class="header-radio" data-group="' . $groupId . '" data-value="' . $col['value'] . '" ';
                $html .= 'title="Zet alle items in deze groep op \'' . Server::htmlEncode($col['label']) . '\'">';
                $html .= '</td>';
            }
            // Empty cells for button columns
            if ($showButtons && $usedButtonCount > 0) {
                for ($i = 0; $i < $usedButtonCount; $i++) {
                    $html .= '<td class="button-col"></td>';
                }
            }
            $html .= '</tr>';

            // Render items in this group
            foreach ($group['items'] as $menu) {
                $html .= self::renderRightsRow(
                    $fieldName,
                    'menu',
                    $menu['menuId'],
                    $menu['displayName'],
                    0, // indent level
                    $menu['hasSecurityByUser'],
                    $columns,
                    $rights,
                    $buttonRights,
                    $showButtons,
                    $menu['buttonLabels'],
                    $menu,  // Pass full menu item for debug comment
                    $usedButtonColumns  // Which button columns are used across all forms
                );

                // Render subforms if this menu item has a form
                if ($menu['formId'] && isset($subforms[$menu['formId']])) {
                    $html .= self::renderSubformRights(
                        $fieldName,
                        $menu['formId'],
                        $subforms,
                        1, // starting indent
                        $columns,
                        $rights,
                        $buttonRights,
                        $showButtons,
                        $menu['buttonLabels'],  // Pass button labels for subforms to inherit
                        $usedButtonColumns,  // Which button columns are used
                        3,  // maxDepth
                        'menu_' . $menu['menuId']  // Parent is the menu row
                    );
                }
            }
        }

        $html .= '</tbody></table>';
        $html .= '</div>';

        // Add data attribute to trigger JS initialization
        // Note: Script is loaded via form-controller.js after AJAX load
        $html .= '<div class="rights-matrix-init" data-initialized="false"></div>';

        return $html;
    }

    /**
     * Render a single rights row
     *
     * @param array $menuItem Full menu item data for analysis (contains: menuId, formId, menuName, formName, displayName, hasSecurityByUser, buttonLabels)
     * @param array $usedButtonColumns Which button columns are used across all forms (indices 0-4)
     */
    private static function renderRightsRow(
        string $fieldName,
        string $type,
        $objectId,
        string $label,
        int $indent,
        bool $hasSecurityByUser,
        array $columns,
        array $rights,
        array $buttonRights,
        bool $showButtons,
        array $buttonLabels = [],
        array $menuItem = [],  // Full menu item for debugging
        array $usedButtonColumns = [true, true, true, true, true],  // Default to all visible
        ?string $parentRowId = null  // Parent row ID for subform dependency tracking
    ): string {
        $typeId = $type === 'menu' ? 10 : 30;
        $key = $typeId . '_' . $objectId;
        $currentRight = $rights[$key] ?? 0;
        $currentButtons = $buttonRights[$key] ?? [false, false, false, false, false];

        $displayName = ucfirst(strtolower($label));
        $rowClass = $indent > 0 ? 'subform-row indent-' . $indent : '';
        $parentAttr = $parentRowId ? ' data-parent="' . $parentRowId . '"' : '';

        $html = '<tr class="' . $rowClass . '" data-row-id="' . $type . '_' . $objectId . '"' . $parentAttr . '>';

        // Label column with indentation
        $html .= '<td class="label-col">';
        $html .= '<div class="row-label" style="padding-left: ' . ($indent * 20) . 'px;">';
        if ($indent > 0) {
            $html .= '<span class="indent-marker">└</span> ';
        }
        $html .= '<span class="' . ($indent > 0 ? 'subform-text' : '') . '">';
        $html .= Server::htmlEncode($displayName);
        $html .= '</span>';

        // Debug comment with all available name fields for analysis
        if (!empty($menuItem)) {
            $debugInfo = [];
            if (!empty($menuItem['menuName'])) $debugInfo[] = 'menuName=' . $menuItem['menuName'];
            if (!empty($menuItem['formName'])) $debugInfo[] = 'formName=' . $menuItem['formName'];
            if (!empty($menuItem['jsonFormName'])) $debugInfo[] = 'jsonFormName=' . $menuItem['jsonFormName'];
            if (!empty($menuItem['displayName'])) $debugInfo[] = 'displayName=' . $menuItem['displayName'];
            if (isset($menuItem['menuId'])) $debugInfo[] = 'menuId=' . $menuItem['menuId'];
            if (isset($menuItem['formId'])) $debugInfo[] = 'formId=' . $menuItem['formId'];
            if (!empty($menuItem['buttonLabels'])) $debugInfo[] = 'buttonLabels=' . json_encode($menuItem['buttonLabels']);
            $html .= '<!-- ' . implode(' | ', $debugInfo) . ' -->';
        }

        $html .= '</div></td>';

        // Access level radio buttons
        foreach ($columns as $col) {
            $isConditional = ($col['conditional'] ?? false) && !$hasSecurityByUser;
            $inputName = $fieldName . '_' . $type . '_' . $objectId;
            $checked = ($currentRight == $col['value']) ? ' checked' : '';
            $disabled = $isConditional ? ' disabled' : '';

            $html .= '<td class="access-col' . ($isConditional ? ' not-applicable' : '') . '">';
            if (!$isConditional) {
                $html .= '<input type="radio" name="' . Server::htmlEncode($inputName) . '" ' .
                    'value="' . $col['value'] . '"' . $checked . $disabled . '>';
            }
            $html .= '</td>';
        }

        // Button checkboxes - only render columns that are used across all forms
        if ($showButtons) {
            for ($i = 0; $i < 5; $i++) {
                // Skip button columns that are not used by any form
                if (!($usedButtonColumns[$i] ?? false)) {
                    continue;
                }

                $btnLabel = $buttonLabels[$i] ?? '';
                $btnName = $fieldName . '_' . $type . '_' . $objectId . '_btn' . ($i + 1);
                $checked = $currentButtons[$i] ? ' checked' : '';
                $disabled = ($currentRight == 0) ? ' disabled' : '';

                if (!empty(trim($btnLabel))) {
                    $html .= '<td class="button-col" title="' . Server::htmlEncode($btnLabel) . '">';
                    $html .= '<label class="button-checkbox">';
                    $html .= '<input type="checkbox" name="' . Server::htmlEncode($btnName) . '" ' .
                        'value="1"' . $checked . $disabled . '>';
                    $html .= ' <span class="button-label">' . Server::htmlEncode($btnLabel) . '</span>';
                    $html .= '</label>';
                    $html .= '</td>';
                } else {
                    // Empty cell for this row but column is used by other forms
                    $html .= '<td class="button-col empty"></td>';
                }
            }
        }

        $html .= '</tr>';
        return $html;
    }

    /**
     * Recursively render subform rights
     */
    private static function renderSubformRights(
        string $fieldName,
        $parentFormId,
        array $subforms,
        int $indent,
        array $columns,
        array $rights,
        array $buttonRights,
        bool $showButtons,
        array $parentButtonLabels = [],
        array $usedButtonColumns = [true, true, true, true, true],
        int $maxDepth = 3,
        ?string $parentRowId = null  // Parent row ID for dependency tracking
    ): string {
        if ($indent > $maxDepth || !isset($subforms[$parentFormId])) {
            return '';
        }

        // Build parent row ID if not provided (first level subforms)
        $effectiveParentRowId = $parentRowId ?? 'form_' . $parentFormId;

        $html = '';
        foreach ($subforms[$parentFormId] as $sub) {
            // Subforms only show their own button labels, not parent's
            $subButtonLabels = $sub['buttonLabels'] ?? [];
            $currentRowId = 'form_' . $sub['formId'];

            $html .= self::renderRightsRow(
                $fieldName,
                'form',
                $sub['formId'],
                $sub['formName'],
                $indent,
                $sub['hasSecurityByUser'],
                $columns,
                $rights,
                $buttonRights,
                $showButtons,
                $subButtonLabels,
                $sub,  // Pass full subform item for debug comment
                $usedButtonColumns,  // Pass used button columns
                $effectiveParentRowId  // Pass parent row ID for dependency tracking
            );

            // Recursively render nested subforms
            if (isset($subforms[$sub['formId']])) {
                $html .= self::renderSubformRights(
                    $fieldName,
                    $sub['formId'],
                    $subforms,
                    $indent + 1,
                    $columns,
                    $rights,
                    $buttonRights,
                    $showButtons,
                    $subButtonLabels,
                    $usedButtonColumns,
                    $maxDepth,
                    $currentRowId  // Pass current row as parent for nested subforms
                );
            }
        }

        return $html;
    }

    /**
     * Render group report rights checklist (for groups form)
     * Uses ReportsService to load reports from JSON config
     */
    private static function renderGroupReportRights(string $fieldName, array $config, $recordId): string
    {
        // Get reports grouped by module from JSON config
        $reportsByModule = ReportsService::getGroupedByModule(true);

        if (empty($reportsByModule)) {
            return '<div class="small">Geen rapporten gevonden</div>';
        }

        // Get current report rights for this group (tblGroupRights is in users db)
        $rights = [];
        if ($recordId) {
            $usersConn = Database::getConnection('users');
            $rightsSql = "SELECT secObjectID FROM tblGroupRights " .
                "WHERE fkGroup = " . (int)$recordId . " AND secObjectType = 20 AND secAccessType > 0"; // 20 = report
            $rightsRs = $usersConn ? Database::openRS($rightsSql, $usersConn) : null;
            if ($rightsRs) {
                while (!$rightsRs->EOF) {
                    $rights[$rightsRs->fields['secObjectID']] = true;
                    $rightsRs->MoveNext();
                }
            }
        }

        $html = '<div class="checklist-inline" data-field="' . Server::htmlEncode($fieldName) . '">';

        foreach ($reportsByModule as $moduleName => $reports) {
            $html .= '<div class="checklist-group">';
            // Capitalize first letter only
            $displayName = ucfirst(strtolower($moduleName));
            $html .= '<div class="checklist-group-header">' . Server::htmlEncode($displayName) . '</div>';

            foreach ($reports as $report) {
                $reportId = $report['id'];
                $reportTitle = $report['title'] ?? '';

                // Use individual field names: group_report_rights_report_123
                // This matches the pattern expected by saveGroupRights()
                $inputName = $fieldName . '_report_' . $reportId;
                $checked = isset($rights[$reportId]) ? ' checked' : '';
                $html .= '<label class="checklist-item-inline">';
                $html .= '<input type="checkbox" name="' . Server::htmlEncode($inputName) . '" value="1"' . $checked . '>';
                $html .= ' ' . Server::htmlEncode($reportTitle);
                $html .= '</label>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render security groups checklist (for users form)
     * Shows a list of security groups that the user can be a member of.
     * Uses tblGroups and tblGroupMembers in the users database.
     */
    private static function renderSecurityGroups(string $fieldName, array $config, $recordId): string
    {
        // Get users database connection
        $usersConn = Database::getConnection('users');

        if (!$usersConn) {
            return '<div class="small">Database verbinding niet beschikbaar</div>';
        }

        // Get all groups except "Everyone" (ID = 0)
        $sql = "SELECT ID, grpName FROM tblGroups WHERE ID <> 0 ORDER BY grpName";
        $rs = Database::openRS($sql, $usersConn);

        if ($rs === null || $rs->EOF) {
            return '<div class="small">Geen groepen gevonden</div>';
        }

        // Get user's current group memberships
        $memberOf = [];
        if ($recordId) {
            $memberSql = "SELECT fkGroup FROM tblGroupMembers WHERE fkUser = " . (int)$recordId;
            $memberRs = Database::openRS($memberSql, $usersConn);
            if ($memberRs) {
                while (!$memberRs->EOF) {
                    $memberOf[$memberRs->fields['fkGroup']] = true;
                    $memberRs->MoveNext();
                }
            }
        }

        $html = '<div class="checklist-inline security-groups" data-field="' . Server::htmlEncode($fieldName) . '">';

        while (!$rs->EOF) {
            $groupId = $rs->fields['ID'];
            $groupName = $rs->fields['grpName'];

            $checked = isset($memberOf[$groupId]) ? ' checked' : '';
            $html .= '<label class="checklist-item-inline">';
            $html .= '<input type="checkbox" name="' . Server::htmlEncode($fieldName) . '[]" value="' . $groupId . '"' . $checked . '>';
            $html .= ' ' . Server::htmlEncode($groupName);
            $html .= '</label>';

            $rs->MoveNext();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render group members checklist (for groups form)
     * Shows a list of users that can be members of this group.
     * Uses tblUsers and tblGroupMembers in the users database.
     */
    private static function renderGroupMembers(string $fieldName, array $config, $recordId): string
    {
        // Get users database connection
        $usersConn = Database::getConnection('users');

        if (!$usersConn) {
            return '<div class="small">Database verbinding niet beschikbaar</div>';
        }

        // Get all users ordered by name
        $sql = "SELECT ID, userFullName, userLogin FROM tblUsers ORDER BY userFullName";
        $rs = Database::openRS($sql, $usersConn);

        if ($rs === null || $rs->EOF) {
            return '<div class="small">Geen gebruikers gevonden</div>';
        }

        // Get current group members
        $members = [];
        if ($recordId) {
            $memberSql = "SELECT fkUser FROM tblGroupMembers WHERE fkGroup = " . (int)$recordId;
            $memberRs = Database::openRS($memberSql, $usersConn);
            if ($memberRs) {
                while (!$memberRs->EOF) {
                    $members[$memberRs->fields['fkUser']] = true;
                    $memberRs->MoveNext();
                }
            }
        }

        $html = '<div class="checklist-inline group-members" data-field="' . Server::htmlEncode($fieldName) . '">';

        while (!$rs->EOF) {
            $userId = $rs->fields['ID'];
            $userName = $rs->fields['userFullName'];
            $userLogin = $rs->fields['userLogin'];

            // Show full name, fall back to login if full name is empty
            $displayName = !empty($userName) ? $userName : $userLogin;

            $checked = isset($members[$userId]) ? ' checked' : '';
            $html .= '<label class="checklist-item-inline">';
            $html .= '<input type="checkbox" name="' . Server::htmlEncode($fieldName) . '[]" value="' . $userId . '"' . $checked . '>';
            $html .= ' ' . Server::htmlEncode($displayName);
            $html .= '</label>';

            $rs->MoveNext();
        }

        $html .= '</div>';

        return $html;
    }
}
