<?php

namespace App\Library;

/**
 * FormControls - Centralized form control rendering
 *
 * This class provides a unified API for rendering form controls,
 * preparing for future migration to web components.
 *
 * Future web component migration:
 * - Each render method can be updated to output custom elements
 * - Data attributes already follow web component patterns (data-*)
 * - The class structure allows easy addition of shadow DOM support
 *
 * Usage:
 *   FormControls::combo($name, $options, $selected, ['required' => true]);
 *   FormControls::text($name, $value, ['maxlength' => 100]);
 *   FormControls::checkbox($name, $checked, ['label' => 'Enable']);
 */
class FormControls
{
    /**
     * Render a select/combobox control
     *
     * @param string $name Field name
     * @param mixed $connection Database connection (PDO, resource, or connection string)
     * @param string $sql SQL query for options
     * @param string $displayField Field name for display text
     * @param string $idField Field name for option value
     * @param string $currentValue Currently selected value
     * @param array $options Additional options:
     *   - height: int (default 1)
     *   - required: bool (default false)
     *   - readonly: bool (default false)
     *   - class: string (additional CSS classes)
     *   - id: string (element ID, defaults to $name . '_id')
     *   - dynamic: bool (use AJAX loading)
     *   - dynamicUrl: string (AJAX endpoint URL)
     *   - grouping: bool (enable optgroup by | separator)
     *   - placeholder: string (empty option text)
     * @param bool $echo Whether to echo output (true) or return it (false)
     * @return string|null HTML output if $echo is false
     */
    public static function combo(
        string $name,
        $connection,
        string $sql,
        string $displayField,
        string $idField,
        string $currentValue = '',
        array $options = [],
        bool $echo = true
    ): ?string {
        // Default options
        $height = $options['height'] ?? 1;
        $required = $options['required'] ?? false;
        $readonly = $options['readonly'] ?? false;
        $class = $options['class'] ?? 'select2';
        $id = $options['id'] ?? $name . '_id';
        $dynamic = $options['dynamic'] ?? false;
        $dynamicUrl = $options['dynamicUrl'] ?? '';
        $placeholder = $options['placeholder'] ?? '';

        $output = '';

        if ($dynamic && $dynamicUrl) {
            // Dynamic/AJAX-loaded select
            $output .= '<input';
            $output .= ' id="' . Server::htmlEncode($id) . '"';
            $output .= ' name="' . Server::htmlEncode($name) . '"';
            $output .= ' value="' . Server::htmlEncode($currentValue) . '"';
            $output .= ' data-ajax="' . Server::htmlEncode($dynamicUrl) . '"';
            if ($required) {
                $output .= ' data-required="J"';
            }
            if ($readonly) {
                $output .= ' data-readonly="y" readonly="readonly"';
            }
            if (!$required && $placeholder) {
                $output .= ' placeholder="' . Server::htmlEncode($placeholder) . '"';
            }
            $output .= ' class="' . Server::htmlEncode($class) . '"';
            $output .= '>';
        } else {
            // Standard select with options from database
            $output .= '<select';
            $output .= ' id="' . Server::htmlEncode($id) . '"';
            $output .= ' name="' . Server::htmlEncode($name) . '"';
            $output .= ' size="' . intval($height) . '"';
            if ($required) {
                $output .= ' data-required="J"';
            }
            if ($readonly) {
                $output .= ' data-readonly="y" readonly="readonly"';
            }
            if ($class) {
                $output .= ' class="' . Server::htmlEncode($class) . '"';
            }
            $output .= '>' . PHP_EOL;

            // Empty option for non-required fields
            if (!$required) {
                $selected = ($currentValue === '') ? ' selected' : '';
                $output .= '<option value=""' . $selected . '>' . Server::htmlEncode($placeholder) . '</option>' . PHP_EOL;
            }

            // Fetch options from database
            $rs = Database::openRS($sql, $connection, adOpenForwardOnly);
            if ($rs !== null) {
                $previousGroup = '';

                while (!$rs->EOF) {
                    $displayValue = ($rs->Fields[$displayField] ?? '') . '';
                    $idValue = $rs->Fields[$idField] ?? '';

                    // Check for grouping (pipe separator)
                    $pipePos = stripos($displayValue, '|');
                    if ($pipePos !== false) {
                        $groupName = trim(substr($displayValue, 0, $pipePos));
                        $detailName = substr($displayValue, $pipePos + 1);

                        if ($previousGroup !== $groupName) {
                            if ($previousGroup !== '') {
                                $output .= '</optgroup>' . PHP_EOL;
                            }
                            $output .= '<optgroup label="' . Server::htmlEncode($groupName) . '">' . PHP_EOL;
                            $previousGroup = $groupName;
                        }
                    } else {
                        $detailName = $displayValue;
                        if ($previousGroup !== '') {
                            $output .= '</optgroup>' . PHP_EOL;
                            $previousGroup = '';
                        }
                    }

                    $selected = (strval($idValue) === strval($currentValue)) ? ' selected' : '';
                    $output .= '<option value="' . Server::htmlEncode($idValue) . '"' . $selected . '>';
                    $output .= Server::htmlEncode(str_replace('<br>', ', ', $detailName));
                    $output .= '</option>' . PHP_EOL;

                    $rs->MoveNext();
                }

                if ($previousGroup !== '') {
                    $output .= '</optgroup>' . PHP_EOL;
                }
            }

            $output .= '</select>';
        }

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a text input control
     *
     * @param string $name Field name
     * @param string $value Current value
     * @param array $options Additional options:
     *   - id: string (element ID)
     *   - maxlength: int
     *   - required: bool
     *   - readonly: bool
     *   - class: string
     *   - placeholder: string
     *   - type: string (text, email, tel, etc.)
     *   - validation: string (datum, time, number, postalcode, etc.)
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function text(
        string $name,
        string $value = '',
        array $options = [],
        bool $echo = true
    ): ?string {
        $id = $options['id'] ?? $name;
        $type = $options['type'] ?? 'text';
        $maxlength = $options['maxlength'] ?? null;
        $required = $options['required'] ?? false;
        $readonly = $options['readonly'] ?? false;
        $class = $options['class'] ?? '';
        $placeholder = $options['placeholder'] ?? '';
        $validation = $options['validation'] ?? '';

        $output = '<input type="' . Server::htmlEncode($type) . '"';
        $output .= ' id="' . Server::htmlEncode($id) . '"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' value="' . Server::htmlEncode($value) . '"';

        if ($maxlength !== null) {
            $output .= ' maxlength="' . intval($maxlength) . '"';
        }
        if ($required) {
            $output .= ' required';
        }
        if ($readonly) {
            $output .= ' readonly';
        }
        if ($class) {
            $output .= ' class="' . Server::htmlEncode($class) . '"';
        }
        if ($placeholder) {
            $output .= ' placeholder="' . Server::htmlEncode($placeholder) . '"';
        }
        if ($validation) {
            $output .= ' data-validation="' . Server::htmlEncode($validation) . '"';
        }

        $output .= '>';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a textarea control
     *
     * @param string $name Field name
     * @param string $value Current value
     * @param array $options Additional options:
     *   - id: string
     *   - rows: int (default 5)
     *   - cols: int
     *   - required: bool
     *   - readonly: bool
     *   - class: string
     *   - maxlength: int
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function textarea(
        string $name,
        string $value = '',
        array $options = [],
        bool $echo = true
    ): ?string {
        $id = $options['id'] ?? $name;
        $rows = $options['rows'] ?? 5;
        $cols = $options['cols'] ?? null;
        $required = $options['required'] ?? false;
        $readonly = $options['readonly'] ?? false;
        $class = $options['class'] ?? '';
        $maxlength = $options['maxlength'] ?? null;

        $output = '<textarea';
        $output .= ' id="' . Server::htmlEncode($id) . '"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' rows="' . intval($rows) . '"';

        if ($cols !== null) {
            $output .= ' cols="' . intval($cols) . '"';
        }
        if ($maxlength !== null) {
            $output .= ' maxlength="' . intval($maxlength) . '"';
        }
        if ($required) {
            $output .= ' required';
        }
        if ($readonly) {
            $output .= ' readonly';
        }
        if ($class) {
            $output .= ' class="' . Server::htmlEncode($class) . '"';
        }

        $output .= '>' . Server::htmlEncode($value) . '</textarea>';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a checkbox control
     *
     * @param string $name Field name
     * @param bool $checked Whether checkbox is checked
     * @param array $options Additional options:
     *   - id: string
     *   - label: string (label text)
     *   - value: string (value when checked, default "1")
     *   - required: bool
     *   - readonly: bool
     *   - class: string
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function checkbox(
        string $name,
        bool $checked = false,
        array $options = [],
        bool $echo = true
    ): ?string {
        $id = $options['id'] ?? $name;
        $label = $options['label'] ?? '';
        $value = $options['value'] ?? '1';
        $required = $options['required'] ?? false;
        $readonly = $options['readonly'] ?? false;
        $class = $options['class'] ?? '';

        $output = '<input type="checkbox"';
        $output .= ' id="' . Server::htmlEncode($id) . '"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' value="' . Server::htmlEncode($value) . '"';

        if ($checked) {
            $output .= ' checked';
        }
        if ($required) {
            $output .= ' required';
        }
        if ($readonly) {
            $output .= ' onclick="return false;"'; // Prevent change when readonly
        }
        if ($class) {
            $output .= ' class="' . Server::htmlEncode($class) . '"';
        }

        $output .= '>';

        if ($label) {
            $output = '<label>' . $output . ' ' . Server::htmlEncode($label) . '</label>';
        }

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a hidden input control
     *
     * @param string $name Field name
     * @param string $value Value
     * @param array $options Additional options:
     *   - id: string
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function hidden(
        string $name,
        string $value = '',
        array $options = [],
        bool $echo = true
    ): ?string {
        $id = $options['id'] ?? $name;

        $output = '<input type="hidden"';
        $output .= ' id="' . Server::htmlEncode($id) . '"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' value="' . Server::htmlEncode($value) . '"';
        $output .= '>';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a date input control with calendar picker
     *
     * Integrates with datepicker.js - outputs the same HTML structure as the
     * JavaScript DatePicker() function for consistency.
     *
     * @param string $name Field name
     * @param string $value Current value (d-m-Y or Y-m-d format)
     * @param array $options Additional options:
     *   - readonly: bool (default false)
     *   - useScript: bool (default false) - use JS DatePicker() instead of inline HTML
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function date(
        string $name,
        string $value = '',
        array $options = [],
        bool $echo = true
    ): ?string {
        $readonly = $options['readonly'] ?? false;
        $useScript = $options['useScript'] ?? false;

        // Format date for display (d-m-Y format)
        if ($value !== '' && strtotime($value) !== false) {
            $value = date('d-m-Y', strtotime($value));
        }

        if ($useScript && !$readonly) {
            // Use JavaScript DatePicker() function (requires datepicker.js)
            $output = '<script>DatePicker("' . $name . '","' . Server::htmlEncode($value) . '");</script>';
        } else {
            // Inline HTML matching datepicker.js structure
            $output = '<table cellpadding="0" cellspacing="0" class="dateselect"><tr>';
            $output .= '<td class="dateinput">';
            $output .= '<input type="text"';
            $output .= ' id="' . Server::htmlEncode($name) . '_id"';
            $output .= ' name="' . Server::htmlEncode($name) . '"';
            $output .= ' value="' . Server::htmlEncode($value) . '"';
            $output .= ' class="datefield"';
            $output .= ' data-validation-type="datum"';
            $output .= ' maxlength="10"';
            if ($readonly) {
                $output .= ' readonly="readonly"';
            }
            $output .= '>';
            $output .= '</td>';
            if (!$readonly) {
                $output .= '<td onclick="show_calendar(\'' . $name . '\')" class="cal_arrow">';
                $output .= '<div class="cal_arrow"></div>';
                $output .= '</td>';
            }
            $output .= '</tr></table>';
        }

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a time input control
     *
     * @param string $name Field name
     * @param string $value Current value (H:i format)
     * @param array $options Additional options
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function time(
        string $name,
        string $value = '',
        array $options = [],
        bool $echo = true
    ): ?string {
        $options['type'] = 'text';
        $options['validation'] = $options['validation'] ?? 'time';
        $options['maxlength'] = $options['maxlength'] ?? 5;

        return self::text($name, $value, $options, $echo);
    }

    /**
     * Build options array from database query
     *
     * @param mixed $connection Database connection
     * @param string $sql SQL query
     * @param string $idField Field name for value
     * @param string $displayField Field name for display text
     * @return array Array of [value => label] pairs
     */
    public static function getOptions($connection, string $sql, string $idField, string $displayField): array
    {
        $options = [];

        $rs = Database::openRS($sql, $connection, adOpenForwardOnly);
        if ($rs !== null) {
            while (!$rs->EOF) {
                $id = $rs->Fields[$idField] ?? '';
                $display = $rs->Fields[$displayField] ?? '';
                $options[$id] = $display;
                $rs->MoveNext();
            }
        }

        return $options;
    }

    // =========================================================================
    // CMA-specific controls
    // =========================================================================

    /**
     * Render a CMA textbox control (auto-expands to textarea for long fields)
     *
     * @param string $name Field name
     * @param int $maxLength Maximum character length
     * @param string $value Current value
     * @param bool $isDate Whether this is a date field
     * @param string $validationType Validation type (datum, time, number, email, etc.)
     * @param bool $readonly Whether field is read-only
     * @param bool $isPassword Whether this is a password field
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function textBox(
        string $name,
        int $maxLength,
        string $value = '',
        bool $isDate = false,
        string $validationType = '',
        bool $readonly = false,
        bool $isPassword = false,
        bool $echo = true
    ): ?string {
        $output = '';

        if ($isDate && !$readonly) {
            // Format date for display
            if (strtotime($value) !== false) {
                $value = date('d-m-Y', strtotime($value));
            }
            $output .= '<script>DatePicker("' . $name . '","' . $value . '");</script>';
        } else {
            if ($maxLength > 128) {
                // Use textarea for long text
                $rows = max(round($maxLength / 80, 0), 1);
                $height = $rows * 22;
                $output .= '<textarea';
                $output .= ' name="' . Server::htmlEncode($name) . '"';
                $output .= ' style="width:100%;height:' . $height . 'px"';
                $output .= ' maxlength="' . $maxLength . '"';
                $output .= ' onkeyup="return lib_form_check_maxlength(this)"';
                if ($readonly) {
                    $output .= ' readonly="readonly"';
                }
                $output .= '>';
                $output .= Server::htmlEncode($value);
                $output .= '</textarea>';
            } else {
                // Standard input
                $output .= '<input';
                $output .= ' type="' . ($isPassword ? 'password' : 'text') . '"';
                $output .= ' name="' . Server::htmlEncode($name) . '"';
                if ($value !== '') {
                    $output .= ' value="' . Server::htmlEncode($value) . '"';
                }
                $output .= ' maxlength="' . $maxLength . '"';
                $output .= ' size="' . min($maxLength, 70) . '"';
                if ($validationType) {
                    $output .= ' data-validation-type="' . Server::htmlEncode($validationType) . '"';
                }
                if ($readonly) {
                    $output .= ' readonly="readonly"';
                }
                $output .= '>';

                // Show password toggle for admins
                if ($isPassword && function_exists('UserIsAdmin') && UserIsAdmin()) {
                    $output .= '<span class="pwd_view" title="Toon wachtwoord" onclick="ShowPwd(this, \'' . $name . '\')">&nbsp;</span>';
                }
            }
        }
        $output .= PHP_EOL;

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a CMA iOS-style switch checkbox
     *
     * @param string $name Field name
     * @param string $value Value when checked
     * @param bool $checked Whether checkbox is checked
     * @param bool $readonly Whether field is read-only
     * @param bool $yesNo Whether to show Ja/Nee instead of Aan/Uit
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function switchBox(
        string $name,
        string $value = '1',
        bool $checked = false,
        bool $readonly = false,
        bool $yesNo = false,
        bool $echo = true
    ): ?string {
        $classes = ($yesNo ? 'jn ' : '') . 'small switch';
        if ($readonly) {
            $classes .= ' disabled';
        }
        if ($checked) {
            $classes .= ' checked';
        }

        $output = '<span class="' . $classes . '">';
        $output .= '<small></small>';
        $output .= '<input type="checkbox"';
        $output .= ' id="' . Server::htmlEncode($name) . '_id"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' value="' . Server::htmlEncode($value) . '"';
        if ($checked) {
            $output .= ' checked data-default="checked"';
        }
        $output .= '>';
        $output .= '<span class="switch-text">';
        $output .= '<span class="on">' . ($yesNo ? 'Ja' : 'Aan') . ' </span>';
        $output .= '<span class="off">' . ($yesNo ? 'Nee' : 'Uit') . ' </span>';
        $output .= '</span>';
        $output .= '</span>';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a CMA checklist (multi-select with checkboxes)
     *
     * @param string $caption Field caption
     * @param int $id Control ID
     * @param string $sql SQL query for options (must return ID, DisplayName, selected columns)
     * @param mixed $connection Database connection
     * @param bool $clearAll Whether to clear all selections
     * @param int $width Control width
     * @param bool $readonly Whether field is read-only
     * @param bool $echo Whether to echo output
     * @return string Current selected values (comma-separated)
     */
    public static function checkList(
        string $caption,
        int $id,
        string $sql,
        $connection,
        bool $clearAll = false,
        int $width = 0,
        bool $readonly = false,
        bool $echo = true
    ): string {
        $output = '';
        $info = '';
        $currentValue = '';

        $rs = Database::openRS($sql, ($connection === 'users' ? $connection : ''), adOpenForwardOnly);
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }

        $recordCount = $rs->RecordCount;
        $output .= '<input type="hidden" name="chklstinfo_' . $id . '" value="' . $recordCount . '">';
        $output .= '<select name="chklst_' . $id . '" class="select2 select2-original"';
        if ($readonly) {
            $output .= ' readonly="readonly"';
        }
        $output .= ' multiple>' . PHP_EOL;

        while (!$rs->EOF) {
            $rowId = $rs->Fields['ID'] ?? '';
            $displayName = $rs->Fields['DisplayName'] ?? '';
            $selected = ($rs->Fields['selected'] ?? 0) != 0;

            $info .= ($info !== '' ? ',' : '') . $rowId;

            if ($selected && !$clearAll) {
                $currentValue .= ($currentValue !== '' ? ', ' : '') . $rowId;
            }

            $output .= '<option value="' . Server::htmlEncode($rowId) . '"';
            if ($selected && !$clearAll) {
                $output .= ' selected';
            }
            $output .= '>' . Server::htmlEncode($displayName) . '</option>' . PHP_EOL;

            $rs->MoveNext();
        }

        $output .= '</select>' . PHP_EOL;
        $output .= '<input type="hidden" name="chklstall_' . $id . '" value="' . Server::htmlEncode($info) . '">';

        if ($echo) {
            echo $output;
        }

        return $currentValue;
    }

    /**
     * Render a CMA sortable list box
     *
     * @param string $caption Field caption
     * @param int $id Control ID
     * @param string $sql SQL query for options
     * @param mixed $connection Database connection
     * @param int $height List height (number of visible rows)
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function sortList(
        string $caption,
        int $id,
        string $sql,
        $connection,
        int $height = 5,
        bool $echo = true
    ): ?string {
        $output = '';
        $strName = 'srtlst_' . $id;
        $strValues = '';
        $strOrder = '';

        $rs = Database::openRS($sql, $connection, adOpenForwardOnly);
        if ($rs === null) {
            throw new \Exception('Database query failed: ' . Database::getLastError());
        }

        if (!$rs->EOF) {
            $output .= '<table cellpadding="0" style="width:100%" class="sortlistbox"><tr><td style="width:100%">';

            // Toolbar buttons - these need to be echoed separately as they use global functions
            // For now, just include placeholders that the caller can replace
            $output .= '<div class="sortlist-toolbar" data-field="' . Server::htmlEncode($strName) . '">';
            $output .= '<a onclick="lb_sortup(lib_form_findfield(\'' . $strName . '\'))" class="btn-sortup" title="Verplaats omhoog"><span class="lnr lnr-moveup"></span></a>';
            $output .= '<a onclick="lb_sortdown(lib_form_findfield(\'' . $strName . '\'))" class="btn-sortdown" title="Verplaats omlaag"><span class="lnr lnr-movedown"></span></a>';
            $output .= '<a onclick="lb_sort(lib_form_findfield(\'' . $strName . '\'),false)" class="btn-sortaz" title="Sorteer A-Z"><span class="lnr lnr-sortaz"></span></a>';
            $output .= '<a onclick="lb_sort(lib_form_findfield(\'' . $strName . '\'),true)" class="btn-sortza" title="Sorteer Z-A"><span class="lnr lnr-sortza"></span></a>';
            $output .= '<span class="sortlist-hint">Ctrl+Pijltjes verplaatst</span>';
            $output .= '</div>';

            $output .= '</td></tr><tr><td style="width:100%">';
            $output .= '<select name="' . Server::htmlEncode($strName) . '" size="' . $height . '" style="width:100%;border:0px" onkeydown="return lb_key(event)">';

            while (!$rs->EOF) {
                $rowId = $rs->Fields['ID'] ?? '';
                $displayName = $rs->Fields['DisplayName'] ?? '';
                $sortOrder = $rs->Fields['SortOrder'] ?? '';

                if ($strValues !== '') {
                    $strValues .= ',';
                }
                $strValues .= $rowId;

                if ($strOrder !== '') {
                    $strOrder .= ',';
                }
                $strOrder .= $sortOrder;

                $output .= '<option value="' . Server::htmlEncode($rowId) . '">' . Server::htmlEncode($displayName) . '</option>' . PHP_EOL;

                $rs->MoveNext();
            }

            $output .= '</select>';
            $output .= '</td></tr></table>';

            $output .= '<input type="hidden" name="' . Server::htmlEncode($strName) . '__label" value="' . Server::htmlEncode($caption) . '">';
            $output .= '<input type="hidden" name="' . Server::htmlEncode($strName) . '_info" value="' . Server::htmlEncode($strValues) . '">';
            $output .= '<input type="hidden" name="' . Server::htmlEncode($strName) . '_info_order" value="' . Server::htmlEncode($strOrder) . '">';
        }

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a URL input control
     *
     * @param string $name Field name
     * @param string $value Current URL value
     * @param int $maxLength Maximum length
     * @param bool $readonly Whether field is read-only
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function url(
        string $name,
        string $value = '',
        int $maxLength = 255,
        bool $readonly = false,
        bool $echo = true
    ): ?string {
        $output = '<input type="text"';
        $output .= ' class="url"';
        $output .= ' data-validation-type="url"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' value="' . Server::htmlEncode($value) . '"';
        $output .= ' maxlength="' . $maxLength . '"';
        if ($readonly) {
            $output .= ' readonly="readonly"';
        }
        $output .= '>';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a file input control
     *
     * @param string $name Field name
     * @param string $value Current file value
     * @param int $maxLength Maximum length
     * @param string $randomValue Random value for file naming
     * @param bool $readonly Always true for file display
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function file(
        string $name,
        string $value = '',
        int $maxLength = 255,
        string $randomValue = '',
        bool $echo = true
    ): ?string {
        $output = '';

        if ($randomValue !== '') {
            $output .= '<input type="hidden" name="' . Server::htmlEncode($name) . '_random" value="' . Server::htmlEncode($randomValue) . '">';
        }

        $output .= '<input type="text"';
        $output .= ' class="file"';
        $output .= ' name="' . Server::htmlEncode($name) . '"';
        $output .= ' value="' . Server::htmlEncode($value) . '"';
        $output .= ' maxlength="' . $maxLength . '"';
        $output .= ' readonly>';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }

    /**
     * Render a label (read-only display field)
     *
     * @param string $name Field name
     * @param string $value Value to display
     * @param bool $echo Whether to echo output
     * @return string|null HTML output if $echo is false
     */
    public static function label(
        string $name,
        string $value = '',
        bool $echo = true
    ): ?string {
        $output = '<span class="form-label" id="' . Server::htmlEncode($name) . '_display">';
        $output .= Server::htmlEncode($value);
        $output .= '</span>';
        $output .= '<input type="hidden" name="' . Server::htmlEncode($name) . '" value="' . Server::htmlEncode($value) . '">';

        if ($echo) {
            echo $output;
            return null;
        }
        return $output;
    }
}
