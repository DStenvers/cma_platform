<?php

namespace Cma;

use App\Library\Application;
use App\Library\Cache;
use App\Library\Database;
use App\Library\Profiler;
use App\Library\Request;
use App\Library\SQL;
use App\Library\Server;
use PDO;

/**
 * CMA Form Control Helper
 *
 * Provides form control rendering functionality (inputs, combos, checkboxes, etc.)
 */
class FormControlHelper
{
    public const FULL_LOAD_CHK_FIELD = '__fully_loaded';
    public const DYNAMIC_LIST_ITEMS = 50;

    // Image resize type constants
    public const IMG_NO_RESIZE = 0;
    public const IMG_MAXIMUM = 1;
    public const IMG_FIXED = 2;

    // Field type constants
    public const TYPE_COMBOBOX = 2;
    public const TYPE_TEXTBOX = 3;
    public const TYPE_CHECKBOX = 5;
    public const TYPE_MEMO = 6;
    public const TYPE_CHECKLIST = 8;
    public const TYPE_IMAGE = 9;
    public const TYPE_URL = 10;
    public const TYPE_FILE = 11;
    public const TYPE_LABEL = 12;
    public const TYPE_SORTLIST = 13;
    public const TYPE_DIRECTORY = 14;
    public const TYPE_GROUPSEPARATOR = 15;
    public const TYPE_USERLIST = 16;
    public const TYPE_EMAIL = 17;
    public const TYPE_XMLSTORE = 18;
    public const TYPE_HTMLSTRIP = 19;
    public const TYPE_THUMBNAIL = 20;
    public const TYPE_TIME = 21;
    public const TYPE_PASSWORD = 22;

    /**
     * Initialize Select2 dropdowns
     * Note: Uses jQuery explicitly (not $ shorthand) for Select2 initialization
     */
    public static function initSelect2JS(): void
    {
        echo <<<'JS'
        jQuery('.select2').each(function() {
            var $el = jQuery(this);
            var settings = {
                minimumResultsForSearch: 20,
                allowClear: true
            };

            // Check if required
            var required = $el.attr('data-required');
            if (required && (required.toLowerCase() === 'j' || required.toLowerCase() === 'y')) {
                settings.allowClear = false;
            }

            // Check for AJAX mode
            if ($el.attr('data-ajax')) {
                if ($el.attr('data-default_id')) {
                    settings.initSelection = function(element, callback) {
                        callback({
                            id: element.attr('data-default_id'),
                            text: element.attr('data-default_value')
                        });
                    };
                }
                settings.minimumInputLength = 2;
                settings.ajax = {
                    dataType: 'json',
                    quietMillis: 150,
                    url: $el.attr('data-ajax'),
                    data: function(term, page) { return { q: term }; },
                    results: function(data, page) { return { results: data.data }; }
                };
            }

            $el.select2(settings);

            // Handle readonly
            var readonly = $el.attr('data-readonly');
            if (readonly && readonly.toLowerCase() === 'y') {
                $el.select2('readonly', true);
            }
        });
JS;
    }

    /**
     * Write a repository-based combo box
     */
    public static function comboBox(array|\ArrayAccess $arrRep, string $formId, int $recordIndex, string $fieldName, string $defaultValue, string $recordId): void
    {
        global $Myconn, $connrep;

        $column = function($name, $row = 0) use ($arrRep) {
            return $arrRep[$name][$row] ?? null;
        };

        switch ($column(\Q_CONTROLTYPEID, $recordIndex)) {
            case self::TYPE_USERLIST:
                self::internalComboBox($fieldName, $formId, 'users', '', 'SELECT ID, userFullName from tblUsers order by userFullName', 'userFullName', 'ID', 'tblUsers', $defaultValue, $column(\Q_HEIGHT, $recordIndex), $column(\Q_ISREQUIRED, $recordIndex), $column(\Q_FLDREADONLY, $recordIndex));
                break;

            case self::TYPE_XMLSTORE:
                // Get XMLStore options from JSON config
                $xmlStoreNames = ConfigLoader::getSelectableDataSourceNames();
                self::internalComboBoxFromArray($fieldName, $formId, $xmlStoreNames, $defaultValue, $column(\Q_HEIGHT, $recordIndex), $column(\Q_ISREQUIRED, $recordIndex), $column(\Q_FLDREADONLY, $recordIndex));
                break;

            case self::TYPE_COMBOBOX:
                $comboSQL = '';
                if ($column(\Q_SQLLIST, $recordIndex) === null) {
                    $comboSQL = 'select ' . $column(\Q_FOREIGNIDFIELD, $recordIndex) . ',' . $column(\Q_CTRLIDFIELD, $recordIndex) . ' from ' . $column(\Q_SOURCETABLE, $recordIndex) . ' order by ' . $column(\Q_FOREIGNIDFIELD, $recordIndex);
                } else {
                    $comboSQL = str_ireplace('[ID]', '[ProdID]', $column(\Q_SQLLIST, $recordIndex));
                    if ($recordId !== '') {
                        $comboSQL = str_ireplace('[ProdID]', $recordId, $comboSQL);
                    } else {
                        $comboSQL = str_ireplace('=[ProdID]', ' is null', $comboSQL);
                    }
                }

                if ($column(\Q_DATABASEID, $recordIndex)) {
                    $Myconn = Database::getConnection(CmaRepository::getResolvedConnectionString((int)$column(\Q_DATABASEID, $recordIndex)));
                    $hasIdPlaceholder = stripos($column(\Q_SQLLIST, $recordIndex) ?? '', '[ID]') !== false;
                    self::internalComboBox($fieldName, $formId, $Myconn, ($hasIdPlaceholder ? $recordId : ''), $comboSQL, $column(\Q_FOREIGNIDFIELD, $recordIndex), $column(\Q_CTRLIDFIELD, $recordIndex), $column(\Q_SOURCETABLE, $recordIndex), $defaultValue, $column(\Q_HEIGHT, $recordIndex), $column(\Q_ISREQUIRED, $recordIndex), $column(\Q_FLDREADONLY, $recordIndex));
                    $Myconn = null;
                } else {
                    global $conn;
                    $hasIdPlaceholder = stripos($column(\Q_SQLLIST, $recordIndex) ?? '', '[ID]') !== false;
                    self::internalComboBox($fieldName, $formId, $conn, ($hasIdPlaceholder ? $recordId : ''), $comboSQL, $column(\Q_FOREIGNIDFIELD, $recordIndex), $column(\Q_CTRLIDFIELD, $recordIndex), $column(\Q_SOURCETABLE, $recordIndex), $defaultValue, $column(\Q_HEIGHT, $recordIndex), $column(\Q_ISREQUIRED, $recordIndex), $column(\Q_FLDREADONLY, $recordIndex));
                }
                break;
        }
    }

    /**
     * Internal combo box rendering with caching
     */
    public static function internalComboBox(string $name, string $formId, $conn, string $extraCacheId, string $sql, ?string $displayField, ?string $idField, ?string $tableName, ?string $currentValue, ?int $height, $required, $readonly): void
    {
        // Convert to proper booleans - database may return '0', 'False', null, etc.
        $required = ($required === true || $required === 1 || $required === '1' ||
                     strtolower($required ?? '') === 'true' || strtolower($required ?? '') === 'yes' ||
                     $required === -1 || $required === '-1');
        $readonly = ($readonly === true || $readonly === 1 || $readonly === '1' ||
                     strtolower($readonly ?? '') === 'true' || strtolower($readonly ?? '') === 'yes' ||
                     $readonly === -1 || $readonly === '-1');

        $debugCombo = (Request::query('debug', '') === 'combo');
        if ($debugCombo) {
            echo "\n<!-- COMBO DEBUG: $name | table=$tableName | display=$displayField | id=$idField | val=$currentValue | req=" . ($required ? 'Y' : 'N') . " | ro=" . ($readonly ? 'Y' : 'N') . " -->\n";
        }

        $strPreviousGroup = "";
        $strGroupName = "";
        $strDetailName = "";
        $completeCombo = '';
        $bDynamic = true;
        $dynamicUrl = '';
        $recordCount = 0;
        $defaultValue = '';
        $listContent = '';

        $cacheId = 'CMA_combo_' . $formId . '_' . $name . '_' . $extraCacheId;
        $bCached = false;

        $bGrouping = stripos($sql, '|') !== false && Application::get('company', '') !== 'RINO Groep';
        $displayField = strval($displayField);
        $idField = strval($idField);
        $dynamicUrl = Request::currentDomain() . Application::get('base_path', '') . 'details_getdata.php?formid=' . $formId . '&controlname=' . $name;

        if ($tableName !== null && $tableName !== '') {
            $nRecords = Database::getTableRecordCount($conn, $tableName);
            $bDynamic = $nRecords > self::DYNAMIC_LIST_ITEMS;
        } else {
            $bDynamic = false;
            $nRecords = -1;
        }

        $completeCombo = Cache::get($cacheId) ?? '';
        if ($completeCombo !== '') {
            $bCached = true;
        }

        if ($debugCombo) {
            echo "<!-- COMBO: nRecords=$nRecords | bDynamic=" . ($bDynamic ? 'Y' : 'N') . " | bCached=" . ($bCached ? 'Y' : 'N') . " -->\n";
            echo "<!-- COMBO SQL: " . htmlspecialchars(substr($sql, 0, 200)) . "... -->\n";
        }

        $rsCombo = null;

        if (!$bDynamic && !$bCached) {
            $listSQL = str_ireplace('select ', 'select top ' . (self::DYNAMIC_LIST_ITEMS + 1) . ' ', $sql);
            $listSQL = str_ireplace('select top ' . (self::DYNAMIC_LIST_ITEMS + 1) . ' DISTINCT ', 'select DISTINCT top ' . (self::DYNAMIC_LIST_ITEMS + 1) . ' ', $listSQL);
            $rsCombo = Database::openRS($listSQL, $conn, adOpenStatic);
            if ($rsCombo === null) {
                throw new \Exception('Database query failed: ' . Database::getLastError());
            }
            // Note: Recordset is already positioned at first row after construction - no need to call fetch()

            $originalScript = strtolower(Request::getOriginalScript());
            if (stripos($originalScript, 'details.php') !== false || stripos($originalScript, 'list.php') !== false) {
                if (!$rsCombo->EOF) {
                    while (!$rsCombo->EOF && !($recordCount > self::DYNAMIC_LIST_ITEMS && ($currentValue === '' || $defaultValue !== ''))) {
                        $recordCount++;
                        if ($currentValue !== '') {
                            if (($rsCombo->fields[$idField] ?? '') == $currentValue . '') {
                                $defaultValue = $rsCombo->fields[$displayField] ?? '';
                                if ($bGrouping && stripos($defaultValue, '|') !== false) {
                                    $defaultValue = substr($defaultValue, stripos($defaultValue, '|') + 1);
                                }
                            }
                        }
                        $rsCombo->MoveNext();
                    }
                    $bDynamic = $recordCount > self::DYNAMIC_LIST_ITEMS;
                    if ($bDynamic) {
                        $dynamicUrl = Request::currentDomain() . Application::get('base_path', '') . 'cma/details_getdata.php?formid=' . $formId . '&controlname=' . $name;
                    }
                }
            }
        }

        // Get default value
        if ($currentValue !== '') {
            $sqlDefault = SQL::addWhere($sql, ($tableName !== '' ? $tableName . '.' : '') . $idField . '=' . (is_numeric($currentValue) ? $currentValue : SQL::postString($currentValue)));
            $defaultValue = Database::getFieldValue($conn, $sqlDefault, $displayField);
            if ($bGrouping && stripos($defaultValue, '|') !== false) {
                $defaultValue = substr($defaultValue, stripos($defaultValue, '|') + 1);
            }
        }

        if ($bDynamic && !$bCached) {
            echo '<INPUT id="' . $name . '_id"' . (!$required ? ' placeholder="&nbsp;"' : '') . ($readonly ? ' data-readonly="y" readonly="readonly"' : '') . ' value="' . $currentValue . '" name="' . $name . '"' . ($currentValue === '' ? '' : ' data-default_value="' . $defaultValue . '" data-default_id="' . $currentValue . '"') . ' data-ajax="' . $dynamicUrl . '"' . ($required ? ' data-required="J"' : '') . ' class="select2">';
        } else {
            if (!$bCached) {
                // Re-query the database since the first loop consumed the recordset
                $listSQL = str_ireplace('select ', 'select top ' . (self::DYNAMIC_LIST_ITEMS + 1) . ' ', $sql);
                $listSQL = str_ireplace('select top ' . (self::DYNAMIC_LIST_ITEMS + 1) . ' DISTINCT ', 'select DISTINCT top ' . (self::DYNAMIC_LIST_ITEMS + 1) . ' ', $listSQL);
                $rsCombo = Database::openRS($listSQL, $conn, adOpenStatic);
                // Note: Recordset is already positioned at first row after construction - no need to call fetch()

                $completeCombo .= '<SELECT id="' . $name . '_id" name="' . $name . '"' . ($readonly ? ' data-readonly="y" readonly="readonly"' : '') . ($required ? ' data-required="J"' : '') . ' size="' . $height . '" class="select2">';
                if (!$required) {
                    $completeCombo .= '<OPTION value=""' . ($currentValue === "" ? ' selected' : '') . '></OPTION>';
                }
                if ($rsCombo !== null && !$rsCombo->EOF) {
                    $strPreviousGroup = '';
                    while (!$rsCombo->EOF) {
                        $sGroup = '';
                        $blnSkip = false;
                        if (!$blnSkip) {
                            $displayValue = ($rsCombo->Fields[$displayField] ?? '') . '';
                            $pipePos = stripos($displayValue, '|');
                            if ($pipePos !== false) {
                                $strGroupName = trim(substr($displayValue, 0, $pipePos));
                                $strDetailName = substr($displayValue, $pipePos + 1);
                                if ($strPreviousGroup !== $strGroupName) {
                                    if ($strPreviousGroup !== '') {
                                        $sGroup .= '</OPTGROUP>';
                                    }
                                    $sGroup .= '<OPTGROUP label="' . $strGroupName . '">';
                                    $strPreviousGroup = $strGroupName;
                                }
                            } else {
                                $strGroupName = '';
                                $strDetailName = $displayValue;
                                if ($strPreviousGroup !== '') {
                                    $sGroup .= '</OPTGROUP>';
                                    $strPreviousGroup = '';
                                }
                            }
                            $completeCombo .= $sGroup . '<OPTION value="' . Server::htmlEncode($rsCombo->Fields[$idField] ?? '') . '">' . str_replace('<br>', ', ', $strDetailName) . '</OPTION>';
                        }
                        $rsCombo->MoveNext();
                    }
                    if ($strPreviousGroup !== '') {
                        $completeCombo .= '</OPTGROUP>';
                    }
                }
                $completeCombo .= '</SELECT>';
                Cache::set($cacheId, $completeCombo);
            }
            if ($currentValue !== '') {
                $completeCombo = str_replace('value="' . Server::htmlEncode($currentValue) . '">', 'value="' . Server::htmlEncode($currentValue) . '" selected>', $completeCombo);
            }
            if ($debugCombo) {
                echo "<!-- COMBO OUTPUT len=" . strlen($completeCombo) . " -->\n";
            }
            echo $completeCombo . PHP_EOL;
        }
        Profiler::tussenstand('WriteRepCombo : Na ophalen data (naam: ' . $name . ', dynamisch: ' . $bDynamic . ', nRecords:' . ($nRecords ?? 0) . ')');
    }

    /**
     * Internal combo box rendering from array of values (for JSON-based data like XMLStore)
     *
     * @param string $name Field name
     * @param string $formId Form ID
     * @param array $values Array of string values (used as both id and display text)
     * @param string|null $currentValue Current selected value
     * @param int|null $height Height (unused, for compatibility)
     * @param mixed $required Is field required
     * @param mixed $readonly Is field readonly
     */
    public static function internalComboBoxFromArray(string $name, string $formId, array $values, ?string $currentValue, ?int $height, $required, $readonly): void
    {
        // Convert to proper booleans
        $required = ($required === true || $required === 1 || $required === '1' ||
                     strtolower($required ?? '') === 'true' || strtolower($required ?? '') === 'yes' ||
                     $required === -1 || $required === '-1');
        $readonly = ($readonly === true || $readonly === 1 || $readonly === '1' ||
                     strtolower($readonly ?? '') === 'true' || strtolower($readonly ?? '') === 'yes' ||
                     $readonly === -1 || $readonly === '-1');

        $recordCount = count($values);
        $bDynamic = $recordCount > self::DYNAMIC_LIST_ITEMS;
        $dynamicUrl = Request::currentDomain() . Application::get('base_path', '') . 'details_getdata.php?formid=' . $formId . '&controlname=' . $name;

        // Build select element
        echo '<SELECT ' . ($required ? 'required="required" ' : '') . 'name="' . $name . '" id="' . $name . '" class="form-control select2" ';
        echo 'data-field="' . Server::htmlEncode($name) . '" ';
        if ($bDynamic) {
            echo 'data-ajax-url="' . $dynamicUrl . '" data-ajax="true" ';
        }
        if ($readonly) {
            echo 'disabled="disabled" ';
        }
        echo '>';

        // Empty option
        echo '<option value=""></option>';

        // Options
        foreach ($values as $value) {
            $selected = ($currentValue !== null && $currentValue === $value) ? ' selected' : '';
            echo '<option value="' . Server::htmlEncode($value) . '"' . $selected . '>' . Server::htmlEncode($value) . '</option>';
        }

        echo '</SELECT>';
    }

    /**
     * Render an edit tip box
     */
    public static function editTip(string $title, string $content, string $formId, bool $withinGroup): void
    {
        $id = 0;
        echo '<div id="edit_tip' . ($withinGroup ? '_group' : '_free') . '">';
        echo '<div id="group_' . $id . '" class="groupbox group_open" onclick="grp_flip(' . $id . ',' . $formId . ')">';
        echo '<span class="groupbox-title" id="_gtitle_' . $id . '">' . ($title === '' ? 'Tip' : $title) . '</span>';
        echo '<span class="groupbox-chevron"></span></div>';
        echo '<div id="_g' . $id . '_1" class="groupbox-content" data-last-id="_g' . $id . '_last">' . $content . '</div></div>';
    }

    /**
     * Render a multi-select checklist
     */
    public static function checklist(string $caption, string|int $id, string $sql, $conn, bool $clearAll, int $width, bool $readonly): string
    {
        $i = 0;
        $info = '';
        $currentValue = '';

        $rs = Database::openRS($sql, $conn, adOpenForwardOnly);
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }
        $row = $rs->fetch(PDO::FETCH_ASSOC);
        $recordCount = $rs->RecordCount;

        echo '<INPUT type="hidden" name="chklstinfo_' . $id . '" value="' . $recordCount . '">';
        echo '<SELECT name="chklst_' . $id . '" class="select2 select2-original"' . ($readonly ? ' readonly="readonly"' : '') . ' multiple>' . PHP_EOL;

        while ($row !== false) {
            $info = ($info !== '' ? $info . ',' : '') . $row['ID'];
            // Check for both 'Selected' and 'selected' (case-insensitive lookup)
            $selectedValue = $row['Selected'] ?? $row['selected'] ?? null;
            $isSelected = ($selectedValue !== null && $selectedValue != 0);
            if ($isSelected && !$clearAll) {
                $currentValue = ($currentValue !== '' ? $currentValue . ', ' : '') . $row['ID'];
            }
            echo '<OPTION value="' . $row['ID'] . '"' . ($isSelected && !$clearAll ? ' selected' : '') . '>' . ($row['DisplayName'] ?? '') . '</option>' . PHP_EOL;
            $i++;
            $row = $rs->fetch(PDO::FETCH_ASSOC);
        }

        echo '</SELECT>' . PHP_EOL;
        echo '<INPUT type="hidden" name="chklstall_' . $id . '" value="' . $info . '">';

        return $currentValue;
    }

    /**
     * Determine autocomplete attribute value based on field name
     */
    public static function getAutocompleteAttribute(string $name, bool $isPassword = false, bool $isNewPassword = false): string
    {
        $nameLower = strtolower($name);

        // Password fields
        if ($isPassword) {
            if ($isNewPassword || strpos($nameLower, 'new') !== false || strpos($nameLower, 'nieuw') !== false) {
                return 'autocomplete="new-password"';
            }
            return 'autocomplete="current-password"';
        }

        // Username fields - only match explicit username/login fields, not generic 'name' fields
        if ($nameLower === 'username' ||
            $nameLower === 'loginname' ||
            $nameLower === 'login_name' ||
            $nameLower === 'user_name' ||
            $nameLower === 'gebruikersnaam' ||
            $nameLower === 'login' ||
            strpos($nameLower, 'username') !== false ||
            strpos($nameLower, 'loginname') !== false) {
            return 'autocomplete="username"';
        }

        // Email fields
        if (strpos($nameLower, 'email') !== false || strpos($nameLower, 'mail') !== false) {
            return 'autocomplete="email"';
        }

        // Phone fields
        if (strpos($nameLower, 'phone') !== false ||
            strpos($nameLower, 'tel') !== false ||
            strpos($nameLower, 'mobiel') !== false ||
            strpos($nameLower, 'mobile') !== false) {
            return 'autocomplete="tel"';
        }

        // Address fields
        if (strpos($nameLower, 'street') !== false || strpos($nameLower, 'straat') !== false || strpos($nameLower, 'adres') !== false) {
            return 'autocomplete="street-address"';
        }
        if (strpos($nameLower, 'city') !== false || strpos($nameLower, 'plaats') !== false || strpos($nameLower, 'woonplaats') !== false) {
            return 'autocomplete="address-level2"';
        }
        if (strpos($nameLower, 'postcode') !== false || strpos($nameLower, 'postal') !== false || strpos($nameLower, 'zip') !== false) {
            return 'autocomplete="postal-code"';
        }
        if (strpos($nameLower, 'country') !== false || strpos($nameLower, 'land') !== false) {
            return 'autocomplete="country-name"';
        }

        // Name fields
        if (strpos($nameLower, 'firstname') !== false || strpos($nameLower, 'voornaam') !== false) {
            return 'autocomplete="given-name"';
        }
        if (strpos($nameLower, 'lastname') !== false || strpos($nameLower, 'achternaam') !== false) {
            return 'autocomplete="family-name"';
        }
        if (strpos($nameLower, 'fullname') !== false) {
            return 'autocomplete="name"';
        }

        // Organization
        if (strpos($nameLower, 'company') !== false || strpos($nameLower, 'bedrijf') !== false || strpos($nameLower, 'organization') !== false) {
            return 'autocomplete="organization"';
        }

        return '';
    }

    /**
     * Render a text input or textarea
     */
    public static function textBox(string $name, int $maxLength, ?string $value, bool $isDate, string $validationType, bool $readonly, bool $password): void
    {
        $autocomplete = self::getAutocompleteAttribute($name, $password);

        if ($isDate && !$readonly) {
            if ($value !== '' && $value !== null && strtotime($value) !== false) {
                $value = date("d-m-Y", strtotime($value));
            }
            echo '<script>DatePicker("' . $name . '","' . ($value ?? '') . '");</script>';
        } else {
            if ($maxLength > 128) {
                echo '<TEXTAREA onkeyup="return lib_form_check_maxlength(this)"' . ($readonly ? ' readonly="readonly"' : '') . ' name=' . $name . ' style="width:100%;height:' . max(round($maxLength / 80, 0), 1) * 22 . 'px" maxlength=' . $maxLength . '>';
                if ($value !== '') {
                    echo Server::htmlEncode($value . '');
                }
                echo '</TEXTAREA>';
            } else {
                echo '<INPUT type="' . ($password ? 'password' : 'text') . '" name=' . $name . ($readonly ? ' readonly="readonly"' : '');
                if ($value !== '') {
                    echo ' value="' . Server::htmlEncode($value . '') . '"';
                }
                echo ' maxlength="' . $maxLength . '" size="' . ($maxLength >= 70 ? 70 : $maxLength) . '" data-validation-type="' . $validationType . '"' . ($autocomplete !== '' ? ' ' . $autocomplete : '') . '>';
                if (SecurityHelper::isAdmin() && $password) {
                    echo '<span class="pwd_view" title="Toon wachtwoord" onclick="ShowPwd(this, \'' . $name . '\')">&nbsp;</span>';
                }
            }
        }
        echo PHP_EOL;
    }

    /**
     * Render an iOS-style toggle checkbox
     */
    public static function checkbox(string $name, string $value, bool $checked, bool $readonly, bool $yesNo): void
    {
        echo '<span class="' . ($yesNo ? 'jn ' : '') . 'small switch' . ($readonly ? ' disabled' : '') . ($checked ? ' checked' : '') . '"><small></small>';
        echo '<input type="checkbox" value="' . $value . '"' . ($checked ? ' data-default="checked"' : '') . ($checked ? ' checked' : '') . ' id="' . $name . '_id" name="' . $name . '">';
        echo '<span class="switch-text"><span class="on">' . ($yesNo ? 'Ja' : 'Aan') . ' </span><span class="off">' . ($yesNo ? 'Nee' : 'Uit') . ' </span></span>';
        echo '</span>';
    }

    /**
     * Render a textarea with display label
     */
    public static function textarea(string $name, int $rows, ?string $value, string $display, bool $readonly): void
    {
        echo '<TEXTAREA' . ($readonly ? ' readonly="readonly"' : '') . ' name="' . $name . '" style="width:100%;' . ($display !== '' ? '' : 'display:none') . 'height:' . ($rows * 18) . 'px" rows=' . $rows . '>';
        echo Server::htmlEncode($value ?? '');
        echo '</TEXTAREA>';
        // Only show display message if it's not a CSS style property (contains ':')
        if ($display !== '' && strpos($display, ':') === false) {
            echo '<br><small>' . $display . '</small>';
        }
        echo PHP_EOL;
    }
}
