<?php
/**
 * Tests for Cma\FormRenderer — the PURE (no-DB) control renderer.
 *
 * Run with: php tests/TestRunner.php FormRendererControlsTest
 *
 * FormRenderer turns a field definition (control-type int + name + config
 * array) into the HTML control the user edits. It is deliberately stateless
 * and value-free: per its class docblock, "Controls are rendered without
 * values - JavaScript populates them via AJAX." That means:
 *
 *   - config['value'] is NEVER read. Current-value population (input value=,
 *     selected option, checked state, textarea body) does NOT happen in PHP —
 *     it is a client-side concern. These tests LOCK that: they prove the right
 *     control shell is emitted for the right type, and where a "current value"
 *     analog DOES exist server-side (config['defaultValue'] → data-default /
 *     the radio value= attribute, and static config['options']) they assert it.
 *
 *   - Option lists for combobox/radiogroup can be provided statically in
 *     config['options'] (tested here, no DB). Checklist options and DB-backed
 *     dynamic combo option loops are fetched client-side / via Database and are
 *     NOT reachable here — the checklist renderer only emits the shell +
 *     data-control-id and the client loads the items. Those loops are noted,
 *     not covered.
 *
 * Everything is exercised through both the per-type entry points
 * (renderTextBox, renderComboBox, …) and the renderControl() dispatcher with
 * the matching control-type integer constant.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/FormControlHelper.php';
require_once dirname(__DIR__) . '/classes/FormRenderer.php';

use Cma\FormRenderer;

class FormRendererControlsTest extends TestCase
{
    // ========================================================================
    // TEXTBOX (TYPE_TEXTBOX = 3)
    // ========================================================================

    public function testTextBoxEmitsTextInputWithName(): void
    {
        $out = FormRenderer::renderTextBox('email', []);
        $this->assertStringContainsString('<input type="text"', $out);
        $this->assertStringContainsString('name="email"', $out);
        $this->assertStringContainsString('data-field="email"', $out);
        $this->assertStringContainsString('data-type="textbox"', $out);
    }

    public function testTextBoxMaxLengthEmittedForPositiveLimit(): void
    {
        $out = FormRenderer::renderTextBox('naam', ['maxLength' => 30]);
        $this->assertStringContainsString('maxlength="30"', $out);
        $this->assertStringContainsString('data-maxlength="30"', $out);
    }

    public function testTextBoxNoMaxLengthAttributeForZero(): void
    {
        // maxLength 0 means "no limit" — a hard maxlength="0" would block all
        // typing. The renderer must omit it entirely.
        $out = FormRenderer::renderTextBox('naam', ['maxLength' => 0]);
        $this->assertStringNotContainsString('maxlength="0"', $out);
        $this->assertStringNotContainsString('data-maxlength="0"', $out);
    }

    public function testTextBoxRequiredEmitsNativeAndDataAttr(): void
    {
        $out = FormRenderer::renderTextBox('naam', ['required' => true]);
        $this->assertStringContainsString('data-required="true"', $out);
        $this->assertMatchesRegularExpression('/\srequired(\s|>)/', $out);
    }

    public function testTextBoxReadonlyEmitsNativeAndDataAttr(): void
    {
        $out = FormRenderer::renderTextBox('naam', ['readonly' => true]);
        $this->assertStringContainsString('data-readonly="true"', $out);
        $this->assertMatchesRegularExpression('/\sreadonly(\s|>)/', $out);
    }

    public function testTextBoxDefaultValueReflectedInDataDefault(): void
    {
        // The only server-side "current value" analog for a textbox is the
        // configured default, surfaced as data-default for the client.
        $out = FormRenderer::renderTextBox('naam', ['defaultValue' => 'Jan']);
        $this->assertStringContainsString('data-default="Jan"', $out);
    }

    public function testTextBoxLongMaxLengthBecomesTextarea(): void
    {
        $out = FormRenderer::renderTextBox('omschrijving', ['maxLength' => 500]);
        $this->assertStringContainsString('<textarea', $out);
        $this->assertStringContainsString('maxlength="500"', $out);
    }

    public function testTextBoxNumberValidationConstrainsWidth(): void
    {
        $out = FormRenderer::renderTextBox('bedrag', ['validationType' => 'number']);
        $this->assertStringContainsString('data-validation-type="number"', $out);
        $this->assertStringContainsString('style="width:80px"', $out);
        $this->assertStringContainsString('maxlength="8"', $out);
    }

    public function testTextBoxEscapesNameInAttributeContext(): void
    {
        $out = FormRenderer::renderTextBox('a"b', []);
        $this->assertStringContainsString('name="a&quot;b"', $out);
        $this->assertStringNotContainsString('name="a"b"', $out);
    }

    public function testTextBoxEscapesDefaultValueXss(): void
    {
        $out = FormRenderer::renderTextBox('f', ['defaultValue' => '"><script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testTextBoxEmptyConfigDoesNotFatal(): void
    {
        $out = FormRenderer::renderTextBox('f', []);
        $this->assertStringContainsString('<input type="text"', $out);
        $this->assertStringContainsString('name="f"', $out);
    }

    public function testTextBoxViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_TEXTBOX, 'email', []);
        $this->assertStringContainsString('<input type="text"', $out);
        $this->assertStringContainsString('name="email"', $out);
    }

    public function testTextBoxIsDateProducesDatepicker(): void
    {
        $out = FormRenderer::renderTextBox('dob', ['isDate' => true]);
        $this->assertStringContainsString('<lib-datepicker', $out);
        $this->assertStringContainsString('name="dob"', $out);
    }

    // ========================================================================
    // PASSWORD (TYPE_PASSWORD = 22)
    // ========================================================================

    public function testPasswordEmitsPasswordInput(): void
    {
        $out = FormRenderer::renderPasswordBox('pwd', []);
        $this->assertStringContainsString('<input type="password"', $out);
        $this->assertStringContainsString('name="pwd"', $out);
        $this->assertStringContainsString('data-type="password"', $out);
    }

    public function testPasswordAutocompleteAttribute(): void
    {
        $out = FormRenderer::renderPasswordBox('pwd', []);
        $this->assertStringContainsString('autocomplete="current-password"', $out);
    }

    public function testPasswordToggleOptIn(): void
    {
        $out = FormRenderer::renderPasswordBox('pwd', ['showPasswordToggle' => true]);
        $this->assertStringContainsString('pwd_view', $out);
        $this->assertStringContainsString('data-toggle-field="pwd"', $out);
    }

    public function testPasswordNoToggleByDefault(): void
    {
        $out = FormRenderer::renderPasswordBox('pwd', []);
        $this->assertStringNotContainsString('pwd_view', $out);
    }

    public function testPasswordViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_PASSWORD, 'pwd', []);
        $this->assertStringContainsString('<input type="password"', $out);
    }

    // ========================================================================
    // DATE (TYPE_DATE = 24)
    // ========================================================================

    public function testDateEmitsDatepicker(): void
    {
        $out = FormRenderer::renderDateBox('birth', []);
        $this->assertStringContainsString('<lib-datepicker', $out);
        $this->assertStringContainsString('name="birth"', $out);
        $this->assertStringContainsString('data-type="date"', $out);
    }

    public function testDateRequiredAttribute(): void
    {
        $out = FormRenderer::renderDateBox('birth', ['required' => true]);
        $this->assertStringContainsString('data-required="true"', $out);
    }

    public function testDateTimeGroupHasDatepickerAndTimepicker(): void
    {
        $out = FormRenderer::renderDateBox('start', ['isDateTime' => true]);
        $this->assertStringContainsString('datetime-group', $out);
        $this->assertStringContainsString('<lib-datepicker', $out);
        $this->assertStringContainsString('<lib-timepicker', $out);
        $this->assertStringContainsString('name="start_time"', $out);
    }

    public function testDateTimeReadonlyUsesPlainInput(): void
    {
        $out = FormRenderer::renderDateBox('start', ['isDateTime' => true, 'readonly' => true]);
        $this->assertStringContainsString('class="datefield"', $out);
        $this->assertStringContainsString('readonly', $out);
    }

    public function testDateEscapesNameInDatetimeGroup(): void
    {
        $out = FormRenderer::renderDateBox('a"b', ['isDateTime' => true]);
        $this->assertStringContainsString('name="a&quot;b"', $out);
        $this->assertStringNotContainsString('name="a"b"', $out);
    }

    public function testDateViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_DATE, 'birth', []);
        $this->assertStringContainsString('<lib-datepicker', $out);
    }

    // ========================================================================
    // TIME (TYPE_TIME = 21)
    // ========================================================================

    public function testTimeEmitsTimepicker(): void
    {
        $out = FormRenderer::renderTimeBox('start', []);
        $this->assertStringContainsString('<lib-timepicker', $out);
        $this->assertStringContainsString('name="start"', $out);
        $this->assertStringContainsString('data-type="time"', $out);
    }

    public function testTimeViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_TIME, 'start', []);
        $this->assertStringContainsString('<lib-timepicker', $out);
        $this->assertStringContainsString('name="start"', $out);
    }

    // ========================================================================
    // COMBOBOX (TYPE_COMBOBOX = 2) — static option path (no DB)
    // ========================================================================

    public function testComboEmitsLibComboWithNameAndId(): void
    {
        $out = FormRenderer::renderComboBox('city', []);
        $this->assertStringContainsString('<lib-combo', $out);
        $this->assertStringContainsString('name="city"', $out);
        $this->assertStringContainsString('id="city_id"', $out);
        $this->assertStringContainsString('</lib-combo>', $out);
    }

    public function testComboOptionalGetsEmptyOption(): void
    {
        $out = FormRenderer::renderComboBox('city', []);
        $this->assertStringContainsString('<option value=""></option>', $out);
    }

    public function testComboRequiredOmitsEmptyOption(): void
    {
        $out = FormRenderer::renderComboBox('city', ['required' => true]);
        $this->assertStringNotContainsString('<option value=""></option>', $out);
        $this->assertStringContainsString('data-required="true"', $out);
    }

    public function testComboStaticOptionsAllAppear(): void
    {
        $out = FormRenderer::renderComboBox('city', ['options' => [
            ['value' => '1', 'text' => 'Utrecht'],
            ['value' => '2', 'text' => 'Amsterdam'],
            ['value' => '3', 'label' => 'Rotterdam'],
        ]]);
        $this->assertStringContainsString('<option value="1">Utrecht</option>', $out);
        $this->assertStringContainsString('<option value="2">Amsterdam</option>', $out);
        // 'label' is accepted as a text fallback
        $this->assertStringContainsString('<option value="3">Rotterdam</option>', $out);
    }

    public function testComboOptionTextEscaped(): void
    {
        $out = FormRenderer::renderComboBox('city', ['options' => [
            ['value' => 'x', 'text' => '<script>alert(1)</script>'],
        ]]);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testComboEmptyOptionsStillRendersShell(): void
    {
        // A "current value" not present in any option is a client concern;
        // server-side the shell must still render without any options gracefully.
        $out = FormRenderer::renderComboBox('city', ['options' => []]);
        $this->assertStringContainsString('<lib-combo', $out);
        $this->assertStringContainsString('</lib-combo>', $out);
    }

    public function testComboDynamicUsesAjaxUrl(): void
    {
        $out = FormRenderer::renderComboBox('city', [
            'isDynamic' => true, 'ajaxUrl' => '/api/cities', 'minSearch' => 0,
        ]);
        $this->assertStringContainsString('ajax-url="/api/cities"', $out);
        $this->assertStringContainsString('min-search="0"', $out);
    }

    public function testComboEscapesName(): void
    {
        $out = FormRenderer::renderComboBox('a"b', []);
        $this->assertStringContainsString('name="a&quot;b"', $out);
        $this->assertStringNotContainsString('name="a"b"', $out);
    }

    public function testComboViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_COMBOBOX, 'city', []);
        $this->assertStringContainsString('<lib-combo', $out);
        $this->assertStringContainsString('name="city"', $out);
    }

    // ========================================================================
    // CHECKBOX (TYPE_CHECKBOX = 5)
    // ========================================================================

    public function testCheckBoxEmitsSwitch(): void
    {
        $out = FormRenderer::renderCheckBox('active', []);
        $this->assertStringContainsString('<lib-switch', $out);
        $this->assertStringContainsString('name="active"', $out);
        $this->assertStringContainsString('value="True"', $out);
        $this->assertStringContainsString('data-type="checkbox"', $out);
    }

    public function testCheckBoxDefaultLabelsJaNee(): void
    {
        $out = FormRenderer::renderCheckBox('active', []);
        $this->assertStringContainsString('labels="Ja:Nee"', $out);
    }

    public function testCheckBoxAanUitWhenNotYesNo(): void
    {
        $out = FormRenderer::renderCheckBox('active', ['yesNo' => false]);
        $this->assertStringContainsString('labels="Aan:Uit"', $out);
    }

    public function testCheckBoxReadonlyDisabled(): void
    {
        $out = FormRenderer::renderCheckBox('active', ['readonly' => true]);
        $this->assertStringContainsString('disabled', $out);
        $this->assertStringContainsString('data-readonly="true"', $out);
    }

    public function testCheckBoxDefaultValueReflected(): void
    {
        $out = FormRenderer::renderCheckBox('active', ['defaultValue' => 'True']);
        $this->assertStringContainsString('data-default="True"', $out);
    }

    public function testCheckBoxViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_CHECKBOX, 'active', []);
        $this->assertStringContainsString('<lib-switch', $out);
    }

    // ========================================================================
    // RADIOGROUP (TYPE_RADIOGROUP = 100)
    // ========================================================================

    public function testRadioGroupEmitsComponentWithOptions(): void
    {
        $out = FormRenderer::renderRadioGroup('gender', ['options' => [
            ['value' => 'm', 'text' => 'Man'],
            ['value' => 'f', 'text' => 'Vrouw'],
        ]]);
        $this->assertStringContainsString('<lib-radio-group', $out);
        $this->assertStringContainsString('name="gender"', $out);
        // options attribute is "value:label|value:label"
        $this->assertStringContainsString('m:Man', $out);
        $this->assertStringContainsString('f:Vrouw', $out);
    }

    public function testRadioGroupDefaultValueSelected(): void
    {
        $out = FormRenderer::renderRadioGroup('gender', [
            'defaultValue' => 'f',
            'options' => [['value' => 'm', 'text' => 'Man'], ['value' => 'f', 'text' => 'Vrouw']],
        ]);
        $this->assertStringContainsString('value="f"', $out);
        $this->assertStringContainsString('data-default="f"', $out);
    }

    public function testRadioGroupValueNotInOptionsStillRenders(): void
    {
        // A default that matches no option must not break rendering.
        $out = FormRenderer::renderRadioGroup('gender', [
            'defaultValue' => 'zzz',
            'options' => [['value' => 'm', 'text' => 'Man']],
        ]);
        $this->assertStringContainsString('<lib-radio-group', $out);
        $this->assertStringContainsString('value="zzz"', $out);
    }

    public function testRadioGroupReadonly(): void
    {
        $out = FormRenderer::renderRadioGroup('gender', ['readonly' => true, 'options' => []]);
        $this->assertStringContainsString('readonly', $out);
    }

    public function testRadioGroupEncodesPipeAndColonInOptions(): void
    {
        $out = FormRenderer::renderRadioGroup('opt', ['options' => [
            ['value' => 'a|b', 'text' => 'c:d'],
        ]]);
        // Raw bar/colon in payload would break the value:label|... parser, so
        // they are percent-encoded before assembly.
        $this->assertStringContainsString('a%7Cb', $out);
        $this->assertStringContainsString('c%3Ad', $out);
    }

    public function testRadioGroupViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_RADIOGROUP, 'gender', ['options' => []]);
        $this->assertStringContainsString('<lib-radio-group', $out);
    }

    // ========================================================================
    // MEMO (TYPE_MEMO = 6)
    // ========================================================================

    public function testMemoEmitsTextarea(): void
    {
        $out = FormRenderer::renderMemo('body', []);
        $this->assertStringContainsString('<textarea', $out);
        $this->assertStringContainsString('name="body"', $out);
        $this->assertStringContainsString('id="body"', $out);
        $this->assertStringContainsString('data-type="memo"', $out);
    }

    public function testMemoAllowHtmlAttribute(): void
    {
        $out = FormRenderer::renderMemo('body', ['allowHtml' => true]);
        $this->assertStringContainsString('data-allow-html="true"', $out);
    }

    public function testMemoReadonlyHtmlBecomesDiv(): void
    {
        // A readonly HTML memo renders as a display div, not an editor textarea.
        $out = FormRenderer::renderMemo('body', ['allowHtml' => true, 'readonly' => true]);
        $this->assertStringContainsString('memo-readonly-html', $out);
        $this->assertStringNotContainsString('<textarea', $out);
    }

    public function testMemoJsonDataTypeGetsClass(): void
    {
        $out = FormRenderer::renderMemo('config', ['dataType' => 'json']);
        $this->assertStringContainsString('class="json-field"', $out);
    }

    public function testMemoDefaultValueReflected(): void
    {
        $out = FormRenderer::renderMemo('body', ['defaultValue' => 'hallo']);
        $this->assertStringContainsString('data-default="hallo"', $out);
    }

    public function testMemoViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_MEMO, 'body', []);
        $this->assertStringContainsString('<textarea', $out);
    }

    // ========================================================================
    // CHECKLIST (TYPE_CHECKLIST = 8) — shell only, options are client-loaded
    // ========================================================================

    public function testChecklistEmitsMultiCombo(): void
    {
        $out = FormRenderer::renderChecklist('tags', []);
        $this->assertStringContainsString('<lib-combo name="chklst_tags"', $out);
        $this->assertStringContainsString('multiple', $out);
        $this->assertStringContainsString('data-type="checklist"', $out);
    }

    public function testChecklistEmitsHiddenStateFields(): void
    {
        $out = FormRenderer::renderChecklist('tags', []);
        $this->assertStringContainsString('name="chklstinfo_tags"', $out);
        $this->assertStringContainsString('name="chklstall_tags"', $out);
    }

    public function testChecklistControlIdFallsBackToName(): void
    {
        // JSON forms deliver an empty controlId; it must fall back to the field
        // name, else the client posts an empty controlId and no options load.
        $out = FormRenderer::renderChecklist('tags', ['controlId' => '']);
        $this->assertStringContainsString('chklst_tags', $out);
        $this->assertStringContainsString('data-control-id="tags"', $out);
    }

    public function testChecklistReadonlyDisabled(): void
    {
        $out = FormRenderer::renderChecklist('tags', ['readonly' => true]);
        $this->assertStringContainsString('disabled', $out);
    }

    public function testChecklistViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_CHECKLIST, 'tags', []);
        $this->assertStringContainsString('chklst_tags', $out);
    }

    // ========================================================================
    // IMAGE (TYPE_IMAGE = 9)
    // ========================================================================

    public function testImageEmitsValueInputAndMetadata(): void
    {
        $out = FormRenderer::renderImage('photo', []);
        $this->assertStringContainsString('name="photo"', $out);
        $this->assertStringContainsString('data-type="image"', $out);
        $this->assertStringContainsString('name="photo_path"', $out);
        $this->assertStringContainsString('name="photo_resizetype"', $out);
    }

    public function testImageHasSelectButton(): void
    {
        $out = FormRenderer::renderImage('photo', []);
        $this->assertStringContainsString('image-select', $out);
        $this->assertStringContainsString('data-select-field="photo"', $out);
    }

    public function testImageClearButtonWhenOptional(): void
    {
        $out = FormRenderer::renderImage('photo', []);
        $this->assertStringContainsString('image-clear', $out);
    }

    public function testImageNoClearButtonWhenRequired(): void
    {
        $out = FormRenderer::renderImage('photo', ['required' => true]);
        $this->assertStringNotContainsString('image-clear', $out);
    }

    public function testImageHasEditButtonWhenEditable(): void
    {
        $out = FormRenderer::renderImage('photo', []);
        // Edit-in-place icon, starts disabled (no image yet) and targets the field.
        $this->assertStringContainsString('image-edit', $out);
        $this->assertStringContainsString('data-edit-field="photo"', $out);
        $this->assertStringContainsString('image-edit btn-icon disabled', $out);
    }

    public function testImageNoEditButtonWhenReadonly(): void
    {
        // A read-only image field can't be modified, so no edit-in-place icon.
        $out = FormRenderer::renderImage('photo', ['readonly' => true]);
        $this->assertStringNotContainsString('data-edit-field', $out);
    }

    // ========================================================================
    // Video control type (parallels image; selects .mp4 via the file browser)
    // ========================================================================

    public function testVideoEmitsValueSelectAndPath(): void
    {
        $out = FormRenderer::renderVideo('clip', ['imagePath' => 'videos/']);
        $this->assertStringContainsString('name="clip"', $out);
        $this->assertStringContainsString('data-type="video"', $out);
        $this->assertStringContainsString('name="clip_path"', $out);
        // Reuses the image select hook so the existing JS delegation drives it.
        $this->assertStringContainsString('data-select-field="clip"', $out);
        // Path is normalised with a leading slash.
        $this->assertStringContainsString('/videos/', $out);
    }

    public function testVideoHasViewLinkAndClearWhenOptional(): void
    {
        $out = FormRenderer::renderVideo('clip', []);
        $this->assertStringContainsString('data-video-view="clip"', $out);
        $this->assertStringContainsString('data-clear-field="clip"', $out);
    }

    public function testVideoNoClearWhenRequired(): void
    {
        $out = FormRenderer::renderVideo('clip', ['required' => true]);
        $this->assertStringNotContainsString('data-clear-field', $out);
    }

    public function testVideoViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_VIDEO, 'clip', ['imagePath' => 'videos/']);
        $this->assertStringContainsString('data-type="video"', $out);
        $this->assertStringContainsString('data-select-field="clip"', $out);
    }

    public function testVideoTypeConstant(): void
    {
        $this->assertSame(25, FormRenderer::TYPE_VIDEO);
    }

    public function testImageViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_IMAGE, 'photo', []);
        $this->assertStringContainsString('data-type="image"', $out);
    }

    // ========================================================================
    // FILE (TYPE_FILE = 11)
    // ========================================================================

    public function testFileEmitsHiddenValueAndPath(): void
    {
        $out = FormRenderer::renderFile('doc', []);
        $this->assertStringContainsString('name="doc"', $out);
        $this->assertStringContainsString('data-type="file"', $out);
        $this->assertStringContainsString('name="doc_path"', $out);
    }

    public function testFileHasSelectButtonWhenEditable(): void
    {
        $out = FormRenderer::renderFile('doc', []);
        $this->assertStringContainsString('file-select-btn', $out);
        $this->assertStringContainsString('data-select-field="doc"', $out);
    }

    public function testFileNoSelectButtonWhenReadonly(): void
    {
        $out = FormRenderer::renderFile('doc', ['readonly' => true]);
        $this->assertStringNotContainsString('file-select-btn', $out);
    }

    public function testFileClearButtonWhenOptionalEditable(): void
    {
        $out = FormRenderer::renderFile('doc', []);
        $this->assertStringContainsString('file-clear-btn', $out);
    }

    public function testFileNoClearButtonWhenRequired(): void
    {
        $out = FormRenderer::renderFile('doc', ['required' => true]);
        $this->assertStringNotContainsString('file-clear-btn', $out);
    }

    public function testFileViaRenderControl(): void
    {
        $out = FormRenderer::renderControl(FormRenderer::TYPE_FILE, 'doc', []);
        $this->assertStringContainsString('data-type="file"', $out);
    }

    // ========================================================================
    // EDGE CASES / real failure modes
    // ========================================================================

    public function testUnknownControlTypeReturnsSafeFallback(): void
    {
        // An unrecognised control-type id must NOT fatal; it returns a disabled
        // placeholder input and a diagnostic comment.
        $out = FormRenderer::renderControl(9999, 'mystery', []);
        $this->assertStringContainsString('Unknown control type 9999', $out);
        $this->assertStringContainsString('name="mystery"', $out);
        $this->assertStringContainsString('disabled', $out);
    }

    public function testUnknownControlTypeEscapesName(): void
    {
        $out = FormRenderer::renderControl(9999, 'a"b', []);
        $this->assertStringContainsString('name="a&quot;b"', $out);
        $this->assertStringNotContainsString('name="a"b"', $out);
    }

    public function testHiddenTypesRenderEmptyString(): void
    {
        // HTMLSTRIP and THUMBNAIL are processed on save, never rendered.
        $this->assertEquals('', FormRenderer::renderControl(FormRenderer::TYPE_HTMLSTRIP, 'f', []));
        $this->assertEquals('', FormRenderer::renderControl(FormRenderer::TYPE_THUMBNAIL, 'f', []));
    }

    public function testEmptyConfigNeverFatalsAcrossTypes(): void
    {
        // Drive every mapped control type through renderControl with an empty
        // config; none may throw and each must return a string.
        $types = [
            FormRenderer::TYPE_COMBOBOX, FormRenderer::TYPE_TEXTBOX, FormRenderer::TYPE_CHECKBOX,
            FormRenderer::TYPE_MEMO, FormRenderer::TYPE_CHECKLIST, FormRenderer::TYPE_IMAGE,
            FormRenderer::TYPE_URL, FormRenderer::TYPE_FILE, FormRenderer::TYPE_LABEL,
            FormRenderer::TYPE_SORTLIST, FormRenderer::TYPE_GROUPSEPARATOR, FormRenderer::TYPE_USERLIST,
            FormRenderer::TYPE_EMAIL, FormRenderer::TYPE_TIME, FormRenderer::TYPE_PASSWORD,
            FormRenderer::TYPE_DATE, FormRenderer::TYPE_RADIOGROUP,
        ];
        foreach ($types as $t) {
            $out = FormRenderer::renderControl($t, 'f', []);
            $this->assertIsString($out, "control type {$t} must return a string for empty config");
        }
    }

    public function testNullDefaultValueDoesNotFatal(): void
    {
        // Explicit null in the config must be tolerated (?? treats it as absent).
        $out = FormRenderer::renderTextBox('f', ['defaultValue' => null, 'caption' => null]);
        $this->assertStringContainsString('name="f"', $out);
    }

    public function testValueKeyIsNotReflectedByDesign(): void
    {
        // LOCK: FormRenderer never emits config['value']. Current-value
        // population is a client-side (AJAX) concern. If a future change starts
        // echoing config['value'] server-side, this test flags it so the
        // contract is revisited deliberately.
        $out = FormRenderer::renderTextBox('f', ['value' => 'SERVER_SIDE_VALUE']);
        $this->assertStringNotContainsString('SERVER_SIDE_VALUE', $out);
    }

    public function testCaptionBecomesDataLabel(): void
    {
        $out = FormRenderer::renderTextBox('f', ['caption' => 'Naam']);
        $this->assertStringContainsString('data-label="Naam"', $out);
    }
}
