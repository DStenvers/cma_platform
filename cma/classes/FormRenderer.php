<?php

namespace Cma;

use App\Library\Application;
use App\Library\Server;

/**
 * CMA Form Renderer
 *
 * Renders form controls for static templates.
 * Controls are rendered without values - JavaScript populates them via AJAX.
 *
 * Each control has:
 * - data-field="fieldName" for JavaScript binding
 * - data-type="controlType" for type-specific handling
 * - data-required="true/false" for validation
 * - data-readonly="true/false" for edit state
 */
class FormRenderer
{
    /**
     * Control type constants (mirrors FormControlHelper)
     */
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
    public const TYPE_IGNOREFIELD = 23;
    public const TYPE_DATE = 24;
    public const TYPE_RADIOGROUP = 100;

    /**
     * Control type names for debugging
     */
    private const TYPE_NAMES = [
        self::TYPE_COMBOBOX => 'combobox',
        self::TYPE_TEXTBOX => 'textbox',
        self::TYPE_CHECKBOX => 'checkbox',
        self::TYPE_MEMO => 'memo',
        self::TYPE_CHECKLIST => 'checklist',
        self::TYPE_IMAGE => 'image',
        self::TYPE_URL => 'url',
        self::TYPE_FILE => 'file',
        self::TYPE_LABEL => 'label',
        self::TYPE_SORTLIST => 'sortlist',
        self::TYPE_DIRECTORY => 'directory',
        self::TYPE_GROUPSEPARATOR => 'groupseparator',
        self::TYPE_USERLIST => 'userlist',
        self::TYPE_EMAIL => 'email',
        self::TYPE_XMLSTORE => 'xmlstore',
        self::TYPE_HTMLSTRIP => 'htmlstrip',
        self::TYPE_THUMBNAIL => 'thumbnail',
        self::TYPE_TIME => 'time',
        self::TYPE_PASSWORD => 'password',
        self::TYPE_IGNOREFIELD => 'ignorefield',
        self::TYPE_DATE => 'date',
        self::TYPE_RADIOGROUP => 'radiogroup',
    ];

    /**
     * Render a control based on its type
     *
     * @param int $controlType Control type constant
     * @param string $name Field name
     * @param array $config Control configuration
     * @return string HTML output
     */
    public static function renderControl(int $controlType, string $name, array $config): string
    {
        $typeName = self::TYPE_NAMES[$controlType] ?? 'unknown';

        return match ($controlType) {
            self::TYPE_COMBOBOX,
            self::TYPE_USERLIST,
            self::TYPE_XMLSTORE => self::renderComboBox($name, $config),

            self::TYPE_TEXTBOX,
            self::TYPE_DIRECTORY,
            self::TYPE_EMAIL => self::renderTextBox($name, $config),

            self::TYPE_PASSWORD => self::renderPasswordBox($name, $config),

            self::TYPE_DATE => self::renderDateBox($name, $config),

            self::TYPE_TIME => self::renderTimeBox($name, $config),

            self::TYPE_CHECKBOX => self::renderCheckBox($name, $config),

            self::TYPE_MEMO => self::renderMemo($name, $config),

            self::TYPE_CHECKLIST => self::renderChecklist($name, $config),

            self::TYPE_IMAGE => self::renderImage($name, $config),

            self::TYPE_FILE => self::renderFile($name, $config),

            self::TYPE_URL => self::renderUrl($name, $config),

            self::TYPE_LABEL => self::renderLabel($name, $config),

            self::TYPE_SORTLIST => self::renderSortlist($name, $config),

            self::TYPE_GROUPSEPARATOR => self::renderGroupSeparator($name, $config),

            self::TYPE_RADIOGROUP => self::renderRadioGroup($name, $config),

            // Hidden/automatic fields - not rendered in form, processed on save
            self::TYPE_HTMLSTRIP,
            self::TYPE_THUMBNAIL => '',

            default => self::renderUnknown($name, $controlType, $config),
        };
    }

    /**
     * Render a text input
     */
    public static function renderTextBox(string $name, array $config): string
    {
        $maxLength = $config['maxLength'] ?? 50;
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $validationType = $config['validationType'] ?? '';
        $isDate = $config['isDate'] ?? false;
        $isDateTime = $config['isDateTime'] ?? false;
        $caption = $config['caption'] ?? '';
        $postCaption = $config['postCaption'] ?? '';
        $defaultValue = $config['defaultValue'] ?? '';

        // DateTime fields get date picker + separate time input
        if ($isDateTime) {
            $requiredAttr = $required ? ' required' : '';

            // For readonly datetime fields, show plain text date + readonly timepicker
            if ($readonly) {
                return sprintf(
                    '<span class="datetime-group">' .
                    '<input type="text" name="%s" class="datefield" data-type="datetime" data-caption="%s" data-is-datetime="true" readonly>' .
                    '<lib-timepicker name="%s_time" data-type="time" data-for-field="%s" readonly></lib-timepicker>' .
                    '</span>',
                    self::escape($name),
                    self::escape($caption),
                    self::escape($name),
                    self::escape($name)
                );
            }

            // Date field (lib-datepicker) + time field (lib-timepicker) side by side
            return sprintf(
                '<span class="datetime-group">' .
                '<lib-datepicker name="%s" format="dd-mm-yyyy" locale="nl" data-type="datetime" data-caption="%s" data-is-datetime="true"%s></lib-datepicker>' .
                '<lib-timepicker name="%s_time" data-type="time" data-for-field="%s"></lib-timepicker>' .
                '</span>',
                self::escape($name),
                self::escape($caption),
                $requiredAttr,
                self::escape($name),
                self::escape($name)
            );
        }

        // Date-only fields get date picker with calendar button
        if ($isDate) {
            $requiredAttr = $required ? ' required' : '';

            // For readonly date fields, show plain text field instead of datepicker
            if ($readonly) {
                return sprintf(
                    '<input type="text" name="%s" class="datefield" data-type="date" data-caption="%s" readonly>',
                    self::escape($name),
                    self::escape($caption)
                );
            }

            // Use lib-datepicker web component
            return sprintf(
                '<lib-datepicker name="%s" format="dd-mm-yyyy" locale="nl" data-type="date" data-caption="%s"%s></lib-datepicker>',
                self::escape($name),
                self::escape($caption),
                $requiredAttr
            );
        }

        $dataAttrs = self::buildDataAttributes($name, 'textbox', $required, $readonly, [
            'maxlength' => $maxLength,
            'validation' => $validationType,
            'is-date' => 'false',
            'default' => $defaultValue,
        ], $newChangableOnly, $caption);

        // Large text fields become textareas
        if ($maxLength > 128) {
            $rows = max(round($maxLength / 80, 0), 1);
            $height = $rows * 22;

            return sprintf(
                '<textarea name="%s" %s style="width:100%%;height:%dpx" maxlength="%d" rows="%d"></textarea>',
                self::escape($name),
                $dataAttrs,
                $height,
                $maxLength,
                $rows
            );
        }

        // Regular text input
        $autocomplete = FormControlHelper::getAutocompleteAttribute($name);
        $size = min($maxLength, 70);

        // Number fields: limit to 8 chars and 80px width
        $style = '';
        if ($validationType === 'number') {
            $maxLength = 8;
            $size = 8;
            $style = ' style="width:80px"';
        }

        return sprintf(
            '<input type="text" name="%s" %s maxlength="%d" size="%d" data-validation-type="%s"%s%s>',
            self::escape($name),
            $dataAttrs,
            $maxLength,
            $size,
            self::escape($validationType),
            $autocomplete !== '' ? ' ' . $autocomplete : '',
            $style
        );
    }

    /**
     * Render a password input
     */
    public static function renderPasswordBox(string $name, array $config): string
    {
        $maxLength = $config['maxLength'] ?? 50;
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $caption = $config['caption'] ?? '';
        $newChangableOnly = $config['newChangableOnly'] ?? false;

        $dataAttrs = self::buildDataAttributes($name, 'password', $required, $readonly, [
            'maxlength' => $maxLength,
        ], $newChangableOnly, $caption);

        $autocomplete = FormControlHelper::getAutocompleteAttribute($name, true);
        $size = min($maxLength, 40);

        $html = sprintf(
            '<input type="password" name="%s" %s maxlength="%d" size="%d"%s>',
            self::escape($name),
            $dataAttrs,
            $maxLength,
            $size,
            $autocomplete !== '' ? ' ' . $autocomplete : ''
        );

        // Password visibility toggle (opt-in via showPasswordToggle in form definition)
        if (!empty($config['showPasswordToggle'])) {
            $html .= sprintf(
                '<span class="pwd_view" title="Toon wachtwoord" data-toggle-field="%s"></span>',
                self::escape($name)
            );
        }

        return $html;
    }

    /**
     * Render a date input
     */
    public static function renderDateBox(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $caption = $config['caption'] ?? '';
        $isDateTime = $config['isDateTime'] ?? false;

        // DateTime fields get date + time side by side
        if ($isDateTime) {
            $requiredAttr = $required ? ' required' : '';

            if ($readonly) {
                return sprintf(
                    '<span class="datetime-group">' .
                    '<input type="text" name="%s" class="datefield" data-type="datetime" data-caption="%s" data-is-datetime="true" readonly>' .
                    '<lib-timepicker name="%s_time" data-type="time" data-for-field="%s" readonly></lib-timepicker>' .
                    '</span>',
                    self::escape($name),
                    self::escape($caption),
                    self::escape($name),
                    self::escape($name)
                );
            }

            return sprintf(
                '<span class="datetime-group">' .
                '<lib-datepicker name="%s" format="dd-mm-yyyy" locale="nl" data-type="datetime" data-caption="%s" data-is-datetime="true"%s></lib-datepicker>' .
                '<lib-timepicker name="%s_time" data-type="time" data-for-field="%s"></lib-timepicker>' .
                '</span>',
                self::escape($name),
                self::escape($caption),
                $requiredAttr,
                self::escape($name),
                self::escape($name)
            );
        }

        $dataAttrs = self::buildDataAttributes($name, 'date', $required, $readonly, [], $newChangableOnly, $caption);

        return sprintf(
            '<lib-datepicker name="%s" %s></lib-datepicker>',
            self::escape($name),
            $dataAttrs
        );
    }

    /**
     * Render a time input
     */
    public static function renderTimeBox(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'time', $required, $readonly, [], $newChangableOnly, $caption);

        return sprintf(
            '<lib-timepicker name="%s" %s></lib-timepicker>',
            self::escape($name),
            $dataAttrs
        );
    }

    /**
     * Render a combo box (select/dropdown)
     */
    public static function renderComboBox(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $height = $config['height'] ?? 1;
        $isDynamic = $config['isDynamic'] ?? false;
        $ajaxUrl = $config['ajaxUrl'] ?? '';
        $sourceTable = $config['sourceTable'] ?? '';
        $currentFormId = $config['currentFormId'] ?? null;
        $caption = $config['caption'] ?? '';

        $filterByField = $config['filterByField'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'combobox', $required, $readonly, [
            'source-table' => $sourceTable,
            'dynamic' => $isDynamic ? 'true' : 'false',
            'filter-by-field' => $filterByField,
        ], $newChangableOnly, $caption);

        // Dynamic combos use lib-combo with AJAX
        if ($isDynamic && $ajaxUrl !== '') {
            $html = sprintf(
                '<lib-combo id="%s_id" name="%s" %s ajax-url="%s" ajax-id="id" ajax-text="text" min-search="3"%s></lib-combo>',
                self::escape($name),
                self::escape($name),
                $dataAttrs,
                self::escape($ajaxUrl),
                $required ? '' : ' placeholder=" "'
            );

            // Add "new" button if applicable (same logic as static combos below)
            $addButton = '';
            if (!$readonly && !empty($sourceTable)) {
                $addFormId = CmaRepository::getFormIdBySourceTable($sourceTable, $currentFormId);
                if ($addFormId !== null) {
                    $addFormName = JsonFormLoader::getFormNameBySourceId($addFormId);
                    if ($addFormName !== null) {
                        $addButton = sprintf(
                            '<a href="javascript:void(0)" class="btn-add-related btn-icon" data-field="%s" data-form-name="%s" title="Nieuw toevoegen">' .
                            '+</a>',
                            self::escape($name),
                            self::escape($addFormName)
                        );
                    }
                }
            }

            if ($addButton) {
                return '<span class="input-group">' . $html . $addButton . '</span>';
            }
            return $html;
        }

        // Static combos render as lib-combo with option children
        $html = sprintf(
            '<lib-combo id="%s_id" name="%s" %s>',
            self::escape($name),
            self::escape($name),
            $dataAttrs
        );

        // Empty option if not required
        if (!$required) {
            $html .= '<option value=""></option>';
        }

        // Inline options (for static dropdowns defined in JSON)
        $options = $config['options'] ?? [];
        if (!empty($options)) {
            foreach ($options as $opt) {
                $optValue = $opt['value'] ?? '';
                $optText = $opt['text'] ?? $opt['label'] ?? $optValue;
                $html .= sprintf(
                    '<option value="%s">%s</option>',
                    self::escape($optValue),
                    self::escape($optText)
                );
            }
        }

        $html .= '</lib-combo>';

        // Add "new" button if a form exists that can edit records in the source table
        $addButton = '';
        if (!$readonly && !empty($sourceTable)) {
            $addFormId = CmaRepository::getFormIdBySourceTable($sourceTable, $currentFormId);
            if ($addFormId !== null) {
                // Get form name from form ID - needed for the form.php?form=xxx URL
                $addFormName = JsonFormLoader::getFormNameBySourceId($addFormId);
                if ($addFormName !== null) {
                    $addButton = sprintf(
                        '<a href="javascript:void(0)" class="btn-add-related btn-icon" data-field="%s" data-form-name="%s" title="Nieuw toevoegen">' .
                        '+</a>',
                        self::escape($name),
                        self::escape($addFormName)
                    );
                }
            }
        }

        // Use flexbox wrapper only if we have an add button
        if ($addButton) {
            return '<span class="input-group">' . $html . $addButton . '</span>';
        }
        return $html;
    }

    /**
     * Render a checkbox (iOS-style toggle)
     * Uses lib-switch web component
     */
    public static function renderCheckBox(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $yesNo = $config['yesNo'] ?? true;
        $defaultValue = $config['defaultValue'] ?? 'False';
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'checkbox', $required, $readonly, [
            'default' => $defaultValue,
        ], $newChangableOnly, $caption);

        $onText = $yesNo ? 'Ja' : 'Aan';
        $offText = $yesNo ? 'Nee' : 'Uit';

        return sprintf(
            '<lib-switch name="%s" %s%s value="True" labels="%s:%s"></lib-switch>',
            self::escape($name),
            $dataAttrs,
            $readonly ? ' disabled' : '',
            self::escape($onText),
            self::escape($offText)
        );
    }

    /**
     * Render a radio button group
     */
    public static function renderRadioGroup(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $options = $config['options'] ?? [];
        $defaultValue = $config['defaultValue'] ?? '';
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'radiogroup', $required, $readonly, [
            'default' => $defaultValue,
        ], $newChangableOnly, $caption);

        $html = sprintf('<div class="radiocontrolgroup" %s>', $dataAttrs);

        foreach ($options as $option) {
            // Cast value to string to handle both string and integer values from JSON
            $value = (string)($option['value'] ?? '');
            $text = (string)($option['text'] ?? $option['label'] ?? $value);
            $id = self::escape($name . '_' . $value);
            $disabledAttr = $readonly ? ' disabled' : '';

            $html .= sprintf(
                '<label><input type="radio" id="%s" name="%s" value="%s"%s> %s</label>',
                $id,
                self::escape($name),
                self::escape($value),
                $disabledAttr,
                self::escape($text)
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Check if content blocks are available
     * Content blocks JSON is in /site/assets/contentblocks/ (shared with front-end)
     */
    public static function hasContentBlocks(): bool
    {
        static $hasBlocks = null;
        if ($hasBlocks === null) {
            // Check in site/assets (parent of cma directory)
            $blocksFile = __DIR__ . '/../../assets/contentblocks/contentblocks.json';
            $hasBlocks = file_exists($blocksFile);
        }
        return $hasBlocks;
    }

    /**
     * Render a memo/textarea (with optional HTML editor)
     * When allowHtml=true, limitedHtml=false, and contentblocks.json exists,
     * wraps the textarea in a blockedit container for structured content editing.
     */
    public static function renderMemo(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $height = $config['height'] ?? 3;
        $allowHtml = $config['allowHtml'] ?? false;
        $limitedHtml = $config['limitedHtml'] ?? false;
        $maxChars = $config['maxChars'] ?? 0;
        $noSpamJs = $config['noSpamJs'] ?? false;
        $caption = $config['caption'] ?? '';
        $dataType = $config['dataType'] ?? '';
        $defaultValue = $config['defaultValue'] ?? '';

        // Check if this field should use content blocks
        // Conditions: HTML allowed, not limited, not readonly, contentblocks.json exists,
        // and field doesn't explicitly disable it (useContentBlocks: false)
        $explicitDisable = isset($config['useContentBlocks']) && $config['useContentBlocks'] === false;
        $useContentBlocks = !$explicitDisable && !$readonly && $allowHtml && !$limitedHtml && self::hasContentBlocks();

        // Readonly HTML memo: render as a div so content displays directly (no CKEditor needed)
        if ($readonly && $allowHtml) {
            $rowHeight = $height * 18;
            return sprintf(
                '<div name="%s" data-field="%s" data-type="memo" data-allow-html="true" data-readonly="true" data-caption="%s" class="memo-readonly-html" style="min-height:%dpx"></div>',
                self::escape($name),
                self::escape($name),
                self::escape($caption),
                $rowHeight
            );
        }

        $dataAttrs = self::buildDataAttributes($name, 'memo', $required, $readonly, [
            'allow-html' => $allowHtml ? 'true' : 'false',
            'limited-html' => $limitedHtml ? 'true' : 'false',
            'max-chars' => $maxChars,
            'no-spam-js' => $noSpamJs ? 'true' : 'false',
            'type' => $dataType ?: null,
            'default' => $defaultValue,
            'use-blockedit' => $useContentBlocks ? 'true' : 'false',
        ], $newChangableOnly, $caption);

        $rowHeight = $height * 18;

        // Add class for JSON fields (monospace font)
        $class = $dataType === 'json' ? ' class="json-field"' : '';

        // Note: id attribute is required for CKEditor.replace() to find the textarea
        // CKEditor.replace(fieldname) looks for an element by ID, not by name
        $textarea = sprintf(
            '<textarea id="%s" name="%s" %s%s style="width:100%%;height:%dpx" rows="%d"></textarea>',
            self::escape($name),
            self::escape($name),
            $dataAttrs,
            $class,
            $rowHeight,
            $height
        );

        // Wrap in blockedit container if using content blocks
        // The blockedit.js script will initialize this field for structured editing
        if ($useContentBlocks) {
            $html = sprintf(
                '<div class="blockedit" data-field="%s">%s</div>',
                self::escape($name),
                $textarea
            );
        } else {
            $html = $textarea;
        }

        // HTML editor initialization is handled by form-controller.js initHtmlEditors()
        // which looks for textareas with data-allow-html="true" attribute

        return $html;
    }

    /**
     * Render a checklist (multi-select)
     */
    public static function renderChecklist(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $controlId = $config['controlId'] ?? $name;
        $width = $config['width'] ?? 200;
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'checklist', $required, $readonly, [
            'control-id' => $controlId,
            'width' => $width,
        ], $newChangableOnly, $caption);

        // Hidden fields for checklist state
        $html = sprintf('<input type="hidden" name="chklstinfo_%s" data-checklist-info="%s">', $controlId, $controlId);
        $html .= sprintf('<input type="hidden" name="chklstall_%s" data-checklist-all="%s">', $controlId, $controlId);

        // Multi-select dropdown
        $html .= sprintf(
            '<lib-combo name="chklst_%s" %s multiple%s></lib-combo>',
            $controlId,
            $dataAttrs,
            $readonly ? ' disabled' : ''
        );

        return $html;
    }

    /**
     * Render an image upload control
     */
    public static function renderImage(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $imagePath = $config['imagePath'] ?? '';
        // Ensure path starts with / so URLs resolve from site root, not relative to /cma/
        if ($imagePath !== '' && $imagePath[0] !== '/' && !str_starts_with($imagePath, 'http')) {
            $imagePath = '/' . $imagePath;
        }
        $resizeType = $config['resizeType'] ?? 0;
        $resizeWidth = $config['resizeWidth'] ?? 0;
        $resizeHeight = $config['resizeHeight'] ?? 0;
        $randomName = $config['randomName'] ?? false;
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'image', $required, $readonly, [
            'path' => $imagePath,
            'resize-type' => $resizeType,
            'resize-width' => $resizeWidth,
            'resize-height' => $resizeHeight,
            'random-name' => $randomName ? 'true' : 'false',
        ], $newChangableOnly, $caption);

        // Hidden input for value
        $html = sprintf(
            '<input type="text" style="height:0px;width:0px;display:none" name="%s" %s readonly>',
            self::escape($name),
            $dataAttrs
        );

        // Hidden fields for image metadata
        $html .= sprintf('<input type="hidden" name="%s_resizetype" value="%d">', self::escape($name), $resizeType);
        $html .= sprintf('<input type="hidden" name="%s_resizeheight" value="%d">', self::escape($name), $resizeHeight);
        $html .= sprintf('<input type="hidden" name="%s_resizewidth" value="%d">', self::escape($name), $resizeWidth);
        $html .= sprintf('<input type="hidden" name="%s_random" value="%s">', self::escape($name), $randomName ? 'Y' : 'N');
        $html .= sprintf('<input type="hidden" name="%s_width" data-image-width="%s">', self::escape($name), self::escape($name));
        $html .= sprintf('<input type="hidden" name="%s_height" data-image-height="%s">', self::escape($name), self::escape($name));
        $html .= sprintf('<input type="hidden" name="%s_path" value="%s">', self::escape($name), self::escape($imagePath));

        // Preview image (always visible, disabled state when no image via CSS/JS)
        $html .= sprintf(
            '<a class="image-preview-btn disabled" data-preview-field="%s" title="Afbeelding bekijken">
                <img name="%s_preview" src="" data-image-preview="%s" style="display:none">
            </a>',
            self::escape($name),
            self::escape($name),
            self::escape($name)
        );

        // Control buttons
        $html .= '<span class="image-controls" data-image-controls="' . self::escape($name) . '">';

        // Crop button removed - file browser has built-in image editor with crop functionality

        $html .= sprintf(
            '<a class="image-select btn-icon" data-select-field="%s" data-path="%s" title="Afbeelding selecteren">
                <span class="lnr lnr-file-image"></span>
            </a>',
            self::escape($name),
            self::escape($imagePath)
        );

        if (!$required) {
            $html .= sprintf(
                '<a class="image-clear btn-icon disabled" data-clear-field="%s" title="Afbeelding verwijderen">
                    <span class="lnr lnr-cross-circle"></span>
                </a>',
                self::escape($name)
            );
        }

        $html .= '</span>';

        return $html;
    }

    /**
     * Render a file upload control
     * Styled like input + icon buttons (similar to date selector)
     */
    public static function renderFile(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $filePath = $config['filePath'] ?? '';
        // Ensure path starts with / so URLs resolve from site root, not relative to /cma/
        if ($filePath !== '' && $filePath[0] !== '/' && !str_starts_with($filePath, 'http')) {
            $filePath = '/' . $filePath;
        }
        $randomName = $config['randomName'] ?? false;
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'file', $required, $readonly, [
            'path' => $filePath,
            'random-name' => $randomName ? 'true' : 'false',
        ], $newChangableOnly, $caption);

        // Hidden input for value
        $html = sprintf(
            '<input type="hidden" name="%s" %s>',
            self::escape($name),
            $dataAttrs
        );

        // Hidden fields for file metadata
        $html .= sprintf('<input type="hidden" name="%s_random" value="%s">', self::escape($name), $randomName ? 'Y' : 'N');
        $html .= sprintf('<input type="hidden" name="%s_path" value="%s">', self::escape($name), self::escape($filePath));

        // File input group (styled like dateselect/input-group)
        $html .= '<span class="file-input-group">';

        // Filename display with clear button inside (like Select2)
        $html .= sprintf('<span class="file-display-input" data-file-name="%s">', self::escape($name));

        // Clear button inside display input (× icon) - only if not required and not readonly
        if (!$readonly && !$required) {
            $html .= sprintf(
                '<span class="combo-clear file-clear-btn disabled" data-clear-field="%s" title="Invoer leegmaken">&times;</span>',
                self::escape($name)
            );
        }
        $html .= '</span>';

        // View button (eye icon) - disabled when no file
        $html .= sprintf(
            '<a class="file-view-btn disabled" data-file-link="%s" data-path="%s" target="_blank" title="Bekijken">
                <span class="lnr lnr-eye"></span>
            </a>',
            self::escape($name),
            self::escape($filePath)
        );

        if (!$readonly) {
            // Select button (select icon)
            $html .= sprintf(
                '<a class="file-select-btn" data-select-field="%s" data-path="%s" title="Bestand selecteren">
                    <span class="lnr lnr-select"></span>
                </a>',
                self::escape($name),
                self::escape($filePath)
            );
        }

        $html .= '</span>';

        return $html;
    }

    /**
     * Render a URL input (styled like file-input-group with integrated icon)
     */
    public static function renderUrl(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $maxLength = $config['maxLength'] ?? 255;
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'url', $required, $readonly, [
            'maxlength' => $maxLength,
        ], $newChangableOnly, $caption);

        $html = '<div class="url-input-group">';

        // URL input field
        $html .= sprintf(
            '<input type="url" name="%s" class="url-display-input" %s maxlength="%d" placeholder="https://...">',
            self::escape($name),
            $dataAttrs,
            $maxLength
        );

        // Preview button (disabled initially, enabled via JS when URL has value)
        $html .= sprintf(
            '<a class="url-preview-btn disabled" data-url-link="%s" target="_blank" title="Open URL">
                <span class="lnr lnr-eye"></span>
            </a>',
            self::escape($name)
        );

        $html .= '</div>';

        return $html;
    }

    /**
     * Render a read-only label
     */
    public static function renderLabel(string $name, array $config): string
    {
        // Pass through dataType for boolean formatting (dataType 11 = Access Yes/No)
        $extra = [];
        if (!empty($config['dataType'])) {
            $extra['datatype'] = $config['dataType'];
        }
        // Also check if the original type was checkbox/boolean
        if (!empty($config['originalType']) && in_array($config['originalType'], ['checkbox', 'boolean'])) {
            $extra['original-type'] = $config['originalType'];
        }

        $dataAttrs = self::buildDataAttributes($name, 'label', false, true, $extra);

        return sprintf(
            '<div class="label" %s data-label="%s"></div>',
            $dataAttrs,
            self::escape($name)
        );
    }

    /**
     * Render a sortlist control
     */
    public static function renderSortlist(string $name, array $config): string
    {
        $required = $config['required'] ?? false;
        $readonly = $config['readonly'] ?? false;
        $newChangableOnly = $config['newChangableOnly'] ?? false;
        $controlId = $config['controlId'] ?? $name;
        $caption = $config['caption'] ?? '';

        $dataAttrs = self::buildDataAttributes($name, 'sortlist', $required, $readonly, [
            'control-id' => $controlId,
        ], $newChangableOnly, $caption);

        return sprintf(
            '<div class="sortlist-container" %s data-sortlist="%s">
                <ul class="sortlist" data-sortlist-items="%s"></ul>
                <input type="hidden" name="%s" data-sortlist-value="%s">
            </div>',
            $dataAttrs,
            self::escape($name),
            self::escape($name),
            self::escape($name),
            self::escape($name)
        );
    }

    /**
     * Render a group separator
     * Creates a clickable header that can collapse/expand the fields below it
     */
    public static function renderGroupSeparator(string $name, array $config): string
    {
        $caption = $config['caption'] ?? '';
        $groupId = $config['groupId'] ?? 0;
        $collapsed = $config['collapsed'] ?? false;
        $formId = $config['formId'] ?? 0;

        // Use cma-groupbox web component for collapsible sections
        // State is stored in localStorage automatically
        // Note: No whitespace around cma-groupbox to avoid creating visual gaps
        return sprintf(
            '<tr class="groupbox-row"><td colspan="99"><cma-groupbox id="group_%d" group-id="%d" form-id="%d" caption="%s"%s></cma-groupbox></td></tr>',
            $groupId,
            $groupId,
            $formId,
            htmlspecialchars($caption, ENT_QUOTES),
            $collapsed ? ' collapsed' : ''
        );
    }

    /**
     * Render an unknown control type (fallback)
     */
    public static function renderUnknown(string $name, int $controlType, array $config): string
    {
        return sprintf(
            '<!-- Unknown control type %d for field "%s" -->
            <input type="text" name="%s" data-field="%s" data-type="unknown-%d" disabled>',
            $controlType,
            self::escape($name),
            self::escape($name),
            self::escape($name),
            $controlType
        );
    }

    /**
     * Build a form row with caption and control
     *
     * @param string $name Field name
     * @param string $caption Caption text
     * @param string $controlHtml Rendered control HTML
     * @param array $options Row options (required, beheer, postCaption, combineWithNext, groupId, controlType, maxLength)
     * @return string Complete row HTML
     */
    public static function renderFormRow(
        string $name,
        string $caption,
        string $controlHtml,
        array $options = []
    ): string {
        $required = $options['required'] ?? false;
        $beheer = $options['beheer'] ?? false;
        $actie = $options['actie'] ?? '';
        $postCaption = $options['postCaption'] ?? '';
        $combineWithNext = $options['combineWithNext'] ?? false;
        $groupId = $options['groupId'] ?? 0;
        $groupRow = $options['groupRow'] ?? 0;
        $controlType = $options['controlType'] ?? 0;
        $maxLength = $options['maxLength'] ?? 0;

        // Skip postcaption if it's just a format hint like "hh:mm"
        if (strtolower($postCaption) === 'hh:mm') {
            $postCaption = '';
        }

        // Remove leading ( and trailing ) from postcaption
        if ($postCaption !== '' && str_starts_with($postCaption, '(') && str_ends_with($postCaption, ')')) {
            $postCaption = substr($postCaption, 1, -1);
        }

        // Determine where to place postcaption:
        // - After input for small fields (<=20 chars) or checkbox/radiobutton
        // - Below caption for larger fields
        $dataType = $options['dataType'] ?? '';
        $postCaptionAfterInput = false;
        if ($postCaption !== '') {
            $isSmallField = $maxLength > 0 && $maxLength <= 20;
            $isCompactControl = in_array($controlType, [
                self::TYPE_CHECKBOX, self::TYPE_DATE, self::TYPE_TIME
            ]);
            // Numeric fields: check string dataType names and ADO numeric type codes
            $dataTypeLower = strtolower((string)$dataType);
            $numericNames = ['int', 'integer', 'bigint', 'smallint', 'tinyint',
                'decimal', 'numeric', 'float', 'real', 'money', 'number'];
            // ADO type codes: 2=SmallInt, 3=Integer, 4=Single, 5=Double, 6=Currency,
            // 14=Decimal, 20=BigInt, 131=Numeric
            $numericAdoCodes = ['2', '3', '4', '5', '6', '14', '20', '131'];
            $isNumeric = in_array($dataTypeLower, $numericNames)
                || in_array((string)$dataType, $numericAdoCodes);
            // Comboboxes are wide controls even if they have a numeric FK dataType
            $isWideControl = in_array($controlType, [
                self::TYPE_COMBOBOX, self::TYPE_USERLIST, self::TYPE_XMLSTORE,
                self::TYPE_MEMO, self::TYPE_CHECKLIST, self::TYPE_SORTLIST
            ]);
            $postCaptionAfterInput = !$isWideControl && ($isSmallField || $isCompactControl || $isNumeric);
        }

        $html = '';

        // Row start with group support and field name for JS targeting
        $html .= '<tr data-field-row="' . self::escape($name) . '"';
        if ($groupId > 0) {
            $html .= sprintf(' id="_g%d_%d" data-group-row="%d"', $groupId, $groupRow, $groupId);
        }
        $html .= '>';

        // Caption cell
        $html .= '<td class="c1' . ($groupId > 0 ? '_g' : '') . '">';
        $html .= self::escapeWithBreaks($caption);
        // Post caption below label (for larger fields)
        if ($postCaption !== '' && !$postCaptionAfterInput) {
            $html .= '<div class="postcaption">' . self::escapeWithBreaks($postCaption) . '</div>';
        }
        $html .= '</td>';

        // Control cell
        $html .= '<td class="c2' . ($groupId > 0 ? '_g' : '') . '">';
        $html .= $controlHtml;
        // Post caption after input (for small fields/checkboxes)
        if ($postCaption !== '' && $postCaptionAfterInput) {
            $html .= '<span class="postcaption-inline">' . self::escapeWithBreaks($postCaption) . '</span>';
        }
        $html .= '</td>';

        // Row end
        $html .= '</tr>';

        return $html;
    }

    /**
     * Build data attributes string
     */
    private static function buildDataAttributes(
        string $name,
        string $type,
        bool $required,
        bool $readonly,
        array $extra = [],
        bool $newChangableOnly = false,
        string $label = ''
    ): string {
        $attrs = [
            'data-field' => $name,
            'data-type' => $type,
            'data-required' => $required ? 'true' : 'false',
            'data-readonly' => $readonly ? 'true' : 'false',
        ];

        // Add field label for validation messages
        if ($label !== '') {
            $attrs['data-label'] = $label;
        }

        // Add newChangableOnly flag - field is readonly except when adding new records
        if ($newChangableOnly) {
            $attrs['data-new-changable-only'] = 'true';
        }

        foreach ($extra as $key => $value) {
            if ($value !== '' && $value !== null) {
                $attrs['data-' . $key] = (string) $value;
            }
        }

        $parts = [];
        foreach ($attrs as $key => $value) {
            $parts[] = $key . '="' . self::escape($value) . '"';
        }

        // Add native required attribute for HTML5 validation and CSS :valid/:invalid pseudo-classes
        if ($required) {
            $parts[] = 'required';
        }

        // Add native readonly attribute for per-field readonly fields
        // This ensures the field is readonly from initial render, not relying on JS timing
        if ($readonly) {
            $parts[] = 'readonly';
        }

        return implode(' ', $parts);
    }

    /**
     * HTML escape helper
     */
    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Escape HTML but allow safe tags like <br>
     * Used for captions and hints that may contain line breaks.
     *
     * Supports:
     * - <br> tags for line breaks
     * - &lt; and &gt; entities in source to display < and > characters
     *
     * @param string $value Text to escape
     * @return string Escaped text with allowed tags
     */
    private static function escapeWithBreaks(string $value): string
    {
        // First escape everything
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // OPTIMIZATION: Use str_ireplace instead of regex for common tags/entities
        // This avoids regex compilation overhead on every call
        return str_ireplace(
            [
                // Allow <br> tags for line breaks (after escaping they become &lt;br&gt;)
                '&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;',
                // Support HTML entities in source: &lt; becomes &amp;lt; after escape,
                // convert back to &lt; so browser displays <
                '&amp;lt;', '&amp;gt;',
            ],
            [
                '<br>', '<br>', '<br>',
                '&lt;', '&gt;',
            ],
            $escaped
        );
    }
}
