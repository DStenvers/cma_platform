<?php
/**
 * Tests for App\Library\FormControls
 *
 * Run with: php tests/TestRunner.php FormControlsTest
 *
 * Notes:
 * - All controls are exercised with $echo = false so the produced HTML string
 *   is returned and can be asserted on directly.
 * - Some methods (combo standard path, getOptions, checkList) pull
 *   their options from the database via Database::openRS(). No live DB is
 *   available in the test harness, so those methods are tested only for their
 *   no-DB behaviour (structural shell, empty result, or thrown exception). The
 *   option-rendering loop of those methods is therefore NOT covered here — it
 *   requires a configured connection that we deliberately don't wire up.
 * - FormControls references the bare constant `adOpenForwardOnly`, which is
 *   normally defined by Bootstrap. setUp() defines it (value 0, matching
 *   library/config/constants.php) so the DB-backed methods don't fatal on an
 *   undefined constant.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\FormControls;

class FormControlsTest extends TestCase
{
    public function setUp(): void
    {
        // Bootstrap normally defines this; the harness doesn't load Bootstrap.
        if (!defined('adOpenForwardOnly')) {
            define('adOpenForwardOnly', 0);
        }
    }

    /**
     * Run a callable while swallowing anything it echoes (the DB layer prints an
     * error block to stdout when no connection is configured). Returns the
     * callable's return value.
     */
    private function quiet(callable $fn)
    {
        ob_start();
        try {
            return $fn();
        } finally {
            ob_end_clean();
        }
    }

    // ========================================================================
    // combo — dynamic (AJAX) path — no DB required
    // ========================================================================

    public function testComboDynamicRendersInput(): void
    {
        $out = FormControls::combo('city', null, '', 'name', 'id', 'X', [
            'dynamic' => true, 'dynamicUrl' => '/api/cities',
        ], false);
        $this->assertStringContainsString('<input', $out);
        $this->assertStringNotContainsString('<select', $out);
    }

    public function testComboDynamicAttributes(): void
    {
        $out = FormControls::combo('city', null, '', 'name', 'id', 'Utrecht', [
            'dynamic' => true, 'dynamicUrl' => '/api/cities', 'id' => 'city_ctl',
        ], false);
        $this->assertStringContainsString('id="city_ctl"', $out);
        $this->assertStringContainsString('name="city"', $out);
        $this->assertStringContainsString('value="Utrecht"', $out);
        $this->assertStringContainsString('data-ajax="/api/cities"', $out);
        $this->assertStringContainsString('class="select2"', $out);
    }

    public function testComboDynamicRequired(): void
    {
        $out = FormControls::combo('c', null, '', 'n', 'i', '', [
            'dynamic' => true, 'dynamicUrl' => '/u', 'required' => true,
        ], false);
        $this->assertStringContainsString('data-required="J"', $out);
    }

    public function testComboDynamicReadonly(): void
    {
        $out = FormControls::combo('c', null, '', 'n', 'i', '', [
            'dynamic' => true, 'dynamicUrl' => '/u', 'readonly' => true,
        ], false);
        $this->assertStringContainsString('data-readonly="y"', $out);
        $this->assertStringContainsString('readonly="readonly"', $out);
    }

    public function testComboDynamicPlaceholderOnlyWhenNotRequired(): void
    {
        $with = FormControls::combo('c', null, '', 'n', 'i', '', [
            'dynamic' => true, 'dynamicUrl' => '/u', 'placeholder' => 'Kies...',
        ], false);
        $this->assertStringContainsString('placeholder="Kies..."', $with);

        $withReq = FormControls::combo('c', null, '', 'n', 'i', '', [
            'dynamic' => true, 'dynamicUrl' => '/u', 'placeholder' => 'Kies...', 'required' => true,
        ], false);
        $this->assertStringNotContainsString('placeholder=', $withReq);
    }

    public function testComboDynamicEscapesValueAndUrl(): void
    {
        $out = FormControls::combo('c', null, '', 'n', 'i', '"><script>alert(1)</script>', [
            'dynamic' => true, 'dynamicUrl' => '/u?x="&y=1',
        ], false);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&amp;y=1', $out);
    }

    // ========================================================================
    // combo — standard <select> path (no DB → shell only)
    // ========================================================================

    public function testComboStandardRendersSelectShell(): void
    {
        $out = $this->quiet(fn() => FormControls::combo('city', null, 'SELECT 1', 'name', 'id', '', [], false));
        $this->assertStringContainsString('<select', $out);
        $this->assertStringContainsString('id="city_id"', $out);
        $this->assertStringContainsString('name="city"', $out);
        $this->assertStringContainsString('size="1"', $out);
        $this->assertStringContainsString('</select>', $out);
    }

    public function testComboStandardEmptyOptionForOptional(): void
    {
        $out = $this->quiet(fn() => FormControls::combo('city', null, 'SELECT 1', 'name', 'id', '', ['placeholder' => 'Kies'], false));
        // non-required → empty option present and selected (current value is '')
        $this->assertStringContainsString('<option value="" selected>Kies</option>', $out);
    }

    public function testComboStandardRequiredOmitsEmptyOption(): void
    {
        $out = $this->quiet(fn() => FormControls::combo('city', null, 'SELECT 1', 'name', 'id', '', ['required' => true], false));
        $this->assertStringContainsString('data-required="J"', $out);
        $this->assertStringNotContainsString('<option value="" selected>', $out);
    }

    public function testComboStandardReadonlyAndCustomClass(): void
    {
        $out = $this->quiet(fn() => FormControls::combo('city', null, 'SELECT 1', 'name', 'id', '', [
            'readonly' => true, 'class' => 'mycombo', 'height' => 4,
        ], false));
        $this->assertStringContainsString('readonly="readonly"', $out);
        $this->assertStringContainsString('class="mycombo"', $out);
        $this->assertStringContainsString('size="4"', $out);
    }

    public function testComboStandardEscapesName(): void
    {
        $out = $this->quiet(fn() => FormControls::combo('a"b', null, 'SELECT 1', 'name', 'id', '', [], false));
        $this->assertStringContainsString('name="a&quot;b"', $out);
        $this->assertStringNotContainsString('name="a"b"', $out);
    }

    // ========================================================================
    // text
    // ========================================================================

    public function testTextBasic(): void
    {
        $out = FormControls::text('email', 'x@y.nl', [], false);
        $this->assertStringContainsString('<input type="text"', $out);
        $this->assertStringContainsString('id="email"', $out);
        $this->assertStringContainsString('name="email"', $out);
        $this->assertStringContainsString('value="x@y.nl"', $out);
    }

    public function testTextDefaultIdEqualsName(): void
    {
        $out = FormControls::text('field1', '', [], false);
        $this->assertStringContainsString('id="field1"', $out);
    }

    public function testTextCustomIdAndType(): void
    {
        $out = FormControls::text('mail', '', ['id' => 'mail_ctl', 'type' => 'email'], false);
        $this->assertStringContainsString('type="email"', $out);
        $this->assertStringContainsString('id="mail_ctl"', $out);
    }

    public function testTextMaxlengthRequiredReadonly(): void
    {
        $out = FormControls::text('f', 'v', ['maxlength' => 50, 'required' => true, 'readonly' => true], false);
        $this->assertStringContainsString('maxlength="50"', $out);
        $this->assertStringContainsString(' required', $out);
        $this->assertStringContainsString(' readonly', $out);
    }

    public function testTextMaxlengthCoercedToInt(): void
    {
        $out = FormControls::text('f', 'v', ['maxlength' => '50; DROP'], false);
        $this->assertStringContainsString('maxlength="50"', $out);
        $this->assertStringNotContainsString('DROP', $out);
    }

    public function testTextPlaceholderAndValidation(): void
    {
        $out = FormControls::text('f', '', ['placeholder' => 'Naam', 'validation' => 'number', 'class' => 'wide'], false);
        $this->assertStringContainsString('placeholder="Naam"', $out);
        $this->assertStringContainsString('data-validation="number"', $out);
        $this->assertStringContainsString('class="wide"', $out);
    }

    public function testTextEscapesValueXss(): void
    {
        $out = FormControls::text('f', '"><script>alert(1)</script>', [], false);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
        $this->assertStringContainsString('&quot;&gt;', $out);
    }

    public function testTextEscapesAmpersandAndApostrophe(): void
    {
        $out = FormControls::text('f', "Ben & Jerry's", [], false);
        $this->assertStringContainsString('&amp;', $out);
        $this->assertStringNotContainsString("Jerry's", $out);
    }

    // ========================================================================
    // textarea
    // ========================================================================

    public function testTextareaBasic(): void
    {
        $out = FormControls::textarea('bio', 'hello', [], false);
        $this->assertStringContainsString('<textarea', $out);
        $this->assertStringContainsString('name="bio"', $out);
        $this->assertStringContainsString('id="bio"', $out);
        $this->assertStringContainsString('rows="5"', $out);
        $this->assertStringContainsString('>hello</textarea>', $out);
    }

    public function testTextareaRowsColsMaxlength(): void
    {
        $out = FormControls::textarea('bio', '', ['rows' => 8, 'cols' => 40, 'maxlength' => 200], false);
        $this->assertStringContainsString('rows="8"', $out);
        $this->assertStringContainsString('cols="40"', $out);
        $this->assertStringContainsString('maxlength="200"', $out);
    }

    public function testTextareaRequiredReadonlyClass(): void
    {
        $out = FormControls::textarea('bio', '', ['required' => true, 'readonly' => true, 'class' => 'big'], false);
        $this->assertStringContainsString(' required', $out);
        $this->assertStringContainsString(' readonly', $out);
        $this->assertStringContainsString('class="big"', $out);
    }

    public function testTextareaEscapesValue(): void
    {
        $out = FormControls::textarea('bio', '</textarea><script>alert(1)</script>', [], false);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;/textarea&gt;', $out);
    }

    // ========================================================================
    // checkbox
    // ========================================================================

    public function testCheckboxUnchecked(): void
    {
        $out = FormControls::checkbox('active', false, [], false);
        $this->assertStringContainsString('type="checkbox"', $out);
        $this->assertStringContainsString('name="active"', $out);
        $this->assertStringContainsString('value="1"', $out);
        $this->assertStringNotContainsString(' checked', $out);
    }

    public function testCheckboxChecked(): void
    {
        $out = FormControls::checkbox('active', true, [], false);
        $this->assertStringContainsString(' checked', $out);
    }

    public function testCheckboxCustomValue(): void
    {
        $out = FormControls::checkbox('active', true, ['value' => 'Y'], false);
        $this->assertStringContainsString('value="Y"', $out);
    }

    public function testCheckboxLabelWraps(): void
    {
        $out = FormControls::checkbox('active', false, ['label' => 'Actief'], false);
        $this->assertMatchesRegularExpression('/^<label>.*Actief<\/label>$/s', $out);
    }

    public function testCheckboxLabelEscaped(): void
    {
        $out = FormControls::checkbox('active', false, ['label' => '<b>x</b>'], false);
        $this->assertStringNotContainsString('<b>x</b>', $out);
        $this->assertStringContainsString('&lt;b&gt;', $out);
    }

    public function testCheckboxReadonlyOnclickGuard(): void
    {
        $out = FormControls::checkbox('active', true, ['readonly' => true], false);
        $this->assertStringContainsString('onclick="return false;"', $out);
    }

    public function testCheckboxRequiredAndClass(): void
    {
        $out = FormControls::checkbox('active', false, ['required' => true, 'class' => 'cb'], false);
        $this->assertStringContainsString(' required', $out);
        $this->assertStringContainsString('class="cb"', $out);
    }

    // ========================================================================
    // hidden
    // ========================================================================

    public function testHiddenBasic(): void
    {
        $out = FormControls::hidden('token', 'abc123', [], false);
        $this->assertStringContainsString('type="hidden"', $out);
        $this->assertStringContainsString('name="token"', $out);
        $this->assertStringContainsString('id="token"', $out);
        $this->assertStringContainsString('value="abc123"', $out);
    }

    public function testHiddenCustomId(): void
    {
        $out = FormControls::hidden('token', '', ['id' => 'tok_ctl'], false);
        $this->assertStringContainsString('id="tok_ctl"', $out);
    }

    public function testHiddenEscapesValue(): void
    {
        $out = FormControls::hidden('token', '"><script>alert(1)</script>', [], false);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    // ========================================================================
    // date
    // ========================================================================

    public function testDateInlineStructure(): void
    {
        $out = FormControls::date('birth', '', [], false);
        $this->assertStringContainsString('class="dateselect"', $out);
        $this->assertStringContainsString('id="birth_id"', $out);
        $this->assertStringContainsString('name="birth"', $out);
        $this->assertStringContainsString('class="datefield"', $out);
        $this->assertStringContainsString('data-validation-type="datum"', $out);
        $this->assertStringContainsString('maxlength="10"', $out);
    }

    public function testDateFormatsIsoValue(): void
    {
        $out = FormControls::date('birth', '2024-03-15', [], false);
        $this->assertStringContainsString('value="15-03-2024"', $out);
    }

    public function testDateEmptyValueStaysEmpty(): void
    {
        $out = FormControls::date('birth', '', [], false);
        $this->assertStringContainsString('value=""', $out);
    }

    public function testDateReadonlyHasNoCalendarArrow(): void
    {
        $out = FormControls::date('birth', '2024-03-15', ['readonly' => true], false);
        $this->assertStringContainsString('readonly="readonly"', $out);
        $this->assertStringNotContainsString('cal_arrow', $out);
        $this->assertStringNotContainsString('show_calendar', $out);
    }

    public function testDateEditableHasCalendarArrow(): void
    {
        $out = FormControls::date('birth', '2024-03-15', [], false);
        $this->assertStringContainsString('cal_arrow', $out);
        $this->assertStringContainsString('show_calendar', $out);
    }

    public function testDateUseScriptPath(): void
    {
        $out = FormControls::date('birth', '2024-03-15', ['useScript' => true], false);
        $this->assertStringContainsString('<script>DatePicker("birth","15-03-2024");</script>', $out);
    }

    // --- XSS neutralisation locks (values/names in JS contexts) ---

    public function testDateScriptValueXssNeutralised(): void
    {
        // A hostile value must not break out of the JS string. It is JS-hex-
        // escaped (json_encode HEX flags), so the raw payload never appears.
        $out = FormControls::date('fld', '"};alert(1)//', ['useScript' => true], false);
        $this->assertStringNotContainsString('"};alert(1)', $out);
        $this->assertStringContainsString('"', $out); // the quote is escaped
    }

    public function testDateScriptNameBreakoutNeutralised(): void
    {
        // A </script> in the field name must be hex-escaped, not close the block.
        $out = FormControls::date('n</script><script>alert(2)//', 'x', ['useScript' => true], false);
        $this->assertStringNotContainsString('</script><script>alert(2)', $out);
        $this->assertStringContainsString('<', $out);
    }

    public function testDateOnclickNameXssNeutralised(): void
    {
        // The inline (non-script) path puts the name in show_calendar('...').
        $out = FormControls::date('a")+alert(3)+("', 'x', ['useScript' => false], false);
        $this->assertStringNotContainsString('show_calendar("a")+alert(3)', $out);
    }

    // ========================================================================
    // time (delegates to text)
    // ========================================================================

    public function testTimeUsesTextInput(): void
    {
        $out = FormControls::time('start', '09:30', [], false);
        $this->assertStringContainsString('<input type="text"', $out);
        $this->assertStringContainsString('name="start"', $out);
        $this->assertStringContainsString('value="09:30"', $out);
    }

    public function testTimeDefaultValidationAndMaxlength(): void
    {
        $out = FormControls::time('start', '', [], false);
        $this->assertStringContainsString('data-validation="time"', $out);
        $this->assertStringContainsString('maxlength="5"', $out);
    }

    // ========================================================================
    // textBox (CMA-specific)
    // ========================================================================

    public function testTextBoxShortInput(): void
    {
        $out = FormControls::textBox('naam', 30, 'Jan', false, '', false, false, false);
        $this->assertStringContainsString('<input', $out);
        $this->assertStringContainsString('type="text"', $out);
        $this->assertStringContainsString('name="naam"', $out);
        $this->assertStringContainsString('value="Jan"', $out);
        $this->assertStringContainsString('maxlength="30"', $out);
        $this->assertStringContainsString('size="30"', $out);
    }

    public function testTextBoxSizeCappedAt70(): void
    {
        // 100 stays in the <input> branch (<=128) but exceeds the 70 cap.
        $out = FormControls::textBox('naam', 100, 'x', false, '', false, false, false);
        $this->assertStringContainsString('size="70"', $out);
    }

    public function testTextBoxLongBecomesTextarea(): void
    {
        $out = FormControls::textBox('omschrijving', 500, 'lang verhaal', false, '', false, false, false);
        $this->assertStringContainsString('<textarea', $out);
        $this->assertStringContainsString('maxlength="500"', $out);
        $this->assertStringContainsString('>lang verhaal</textarea>', $out);
    }

    public function testTextBoxPassword(): void
    {
        $out = FormControls::textBox('pwd', 30, '', false, '', false, true, false);
        $this->assertStringContainsString('type="password"', $out);
    }

    public function testTextBoxReadonly(): void
    {
        $out = FormControls::textBox('naam', 30, 'x', false, '', true, false, false);
        $this->assertStringContainsString('readonly="readonly"', $out);
    }

    public function testTextBoxValidationType(): void
    {
        $out = FormControls::textBox('bedrag', 30, '', false, 'number', false, false, false);
        $this->assertStringContainsString('data-validation-type="number"', $out);
    }

    public function testTextBoxShortEscapesValue(): void
    {
        $out = FormControls::textBox('naam', 30, '"><script>alert(1)</script>', false, '', false, false, false);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    public function testTextBoxLongEscapesValue(): void
    {
        $out = FormControls::textBox('omschrijving', 500, '</textarea><script>alert(1)</script>', false, '', false, false, false);
        $this->assertStringNotContainsString('<script>alert(1)', $out);
        $this->assertStringContainsString('&lt;/textarea&gt;', $out);
    }

    // ========================================================================
    // switchBox (CMA iOS-style switch)
    // ========================================================================

    public function testSwitchBoxStructure(): void
    {
        $out = FormControls::switchBox('flag', '1', false, false, false, false);
        $this->assertStringContainsString('class="small switch"', $out);
        $this->assertStringContainsString('type="checkbox"', $out);
        $this->assertStringContainsString('id="flag_id"', $out);
        $this->assertStringContainsString('name="flag"', $out);
        $this->assertStringContainsString('value="1"', $out);
    }

    public function testSwitchBoxChecked(): void
    {
        $out = FormControls::switchBox('flag', '1', true, false, false, false);
        $this->assertStringContainsString('switch checked', $out);
        $this->assertStringContainsString('checked data-default="checked"', $out);
    }

    public function testSwitchBoxUnchecked(): void
    {
        $out = FormControls::switchBox('flag', '1', false, false, false, false);
        $this->assertStringNotContainsString('data-default="checked"', $out);
        $this->assertStringNotContainsString('switch checked', $out);
    }

    public function testSwitchBoxReadonlyDisabled(): void
    {
        $out = FormControls::switchBox('flag', '1', false, true, false, false);
        $this->assertStringContainsString('disabled', $out);
    }

    public function testSwitchBoxYesNo(): void
    {
        $out = FormControls::switchBox('flag', '1', false, false, true, false);
        $this->assertStringContainsString('jn ', $out);
        $this->assertStringContainsString('>Ja ', $out);
        $this->assertStringContainsString('>Nee ', $out);
    }

    public function testSwitchBoxAanUitDefault(): void
    {
        $out = FormControls::switchBox('flag', '1', false, false, false, false);
        $this->assertStringContainsString('>Aan ', $out);
        $this->assertStringContainsString('>Uit ', $out);
    }

    public function testSwitchBoxEscapesValue(): void
    {
        $out = FormControls::switchBox('flag', '"><script>x</script>', false, false, false, false);
        $this->assertStringNotContainsString('<script>x</script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    // ========================================================================
    // url
    // ========================================================================

    public function testUrlBasic(): void
    {
        $out = FormControls::url('website', 'http://x.nl', 255, false, false);
        $this->assertStringContainsString('<input type="text"', $out);
        $this->assertStringContainsString('class="url"', $out);
        $this->assertStringContainsString('data-validation-type="url"', $out);
        $this->assertStringContainsString('name="website"', $out);
        $this->assertStringContainsString('value="http://x.nl"', $out);
        $this->assertStringContainsString('maxlength="255"', $out);
    }

    public function testUrlReadonly(): void
    {
        $out = FormControls::url('website', '', 100, true, false);
        $this->assertStringContainsString('readonly="readonly"', $out);
        $this->assertStringContainsString('maxlength="100"', $out);
    }

    public function testUrlEscapesValue(): void
    {
        $out = FormControls::url('website', '"><script>x</script>', 255, false, false);
        $this->assertStringNotContainsString('<script>x</script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    // ========================================================================
    // file
    // ========================================================================

    public function testFileBasic(): void
    {
        $out = FormControls::file('upload', 'photo.jpg', 255, '', false);
        $this->assertStringContainsString('class="file"', $out);
        $this->assertStringContainsString('name="upload"', $out);
        $this->assertStringContainsString('value="photo.jpg"', $out);
        $this->assertStringContainsString('readonly', $out);
    }

    public function testFileRandomValueHiddenField(): void
    {
        $out = FormControls::file('upload', 'photo.jpg', 255, 'r4nd0m', false);
        $this->assertStringContainsString('name="upload_random"', $out);
        $this->assertStringContainsString('value="r4nd0m"', $out);
    }

    public function testFileNoRandomFieldWhenEmpty(): void
    {
        $out = FormControls::file('upload', 'photo.jpg', 255, '', false);
        $this->assertStringNotContainsString('upload_random', $out);
    }

    public function testFileEscapesValue(): void
    {
        $out = FormControls::file('upload', '"><script>x</script>', 255, '', false);
        $this->assertStringNotContainsString('<script>x</script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    // ========================================================================
    // label
    // ========================================================================

    public function testLabelStructure(): void
    {
        $out = FormControls::label('status', 'Actief', false);
        $this->assertStringContainsString('class="form-label"', $out);
        $this->assertStringContainsString('id="status_display"', $out);
        $this->assertStringContainsString('Actief', $out);
        $this->assertStringContainsString('type="hidden"', $out);
        $this->assertStringContainsString('name="status"', $out);
    }

    public function testLabelEscapesValue(): void
    {
        $out = FormControls::label('status', '<script>x</script>', false);
        $this->assertStringNotContainsString('<script>x</script>', $out);
        $this->assertStringContainsString('&lt;script&gt;', $out);
    }

    // ========================================================================
    // getOptions / checkList — DB-backed, no-DB behaviour only
    // ========================================================================

    public function testGetOptionsReturnsArrayWithoutDb(): void
    {
        $result = $this->quiet(fn() => FormControls::getOptions(null, 'SELECT 1', 'id', 'name'));
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testCheckListThrowsWhenQueryFails(): void
    {
        $threw = false;
        ob_start();
        try {
            FormControls::checkList('Cap', 5, 'SELECT 1', '', false, 0, false, false);
        } catch (\Exception $e) {
            $threw = true;
        } finally {
            ob_end_clean();
        }
        $this->assertTrue($threw, 'checkList should throw when the query cannot run');
    }

    // ========================================================================
    // echo path (default $echo = true) writes to output and returns null
    // ========================================================================

    public function testEchoPathWritesToOutput(): void
    {
        ob_start();
        $ret = FormControls::text('f', 'v', [], true);
        $captured = ob_get_clean();
        $this->assertNull($ret);
        $this->assertStringContainsString('name="f"', $captured);
        $this->assertStringContainsString('value="v"', $captured);
    }

    public function testReturnPathDoesNotEcho(): void
    {
        ob_start();
        $ret = FormControls::text('f', 'v', [], false);
        $captured = ob_get_clean();
        $this->assertEmpty($captured);
        $this->assertNotNull($ret);
        $this->assertStringContainsString('name="f"', $ret);
    }
}
