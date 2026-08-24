<?php
/**
 * PreferencesToolbarTest.php — the toolbar of the preferences page.
 *
 * The page saves every change on the spot, so there is no save button. What the
 * toolbar shows is the SPINNER, and only while a save is in flight. The standing
 * line "Wijzigingen worden meteen opgeslagen" is gone: it said the same thing on
 * every visit, and with no save button there is nothing to mistake it for.
 *
 * Guarded here and not only in Cypress because Cypress needs a running CMA; this
 * runs on the source and so also holds in CI.
 *
 *   php tests/TestRunner.php PreferencesToolbarTest
 */

require_once __DIR__ . '/TestRunner.php';

class PreferencesToolbarTest extends TestCase
{
    private function src(): string
    {
        return (string) file_get_contents(__DIR__ . '/../preferences.php');
    }

    public function testStandingAutosaveTextIsGone(): void
    {
        $s = $this->src();
        $this->assertFalse(
            str_contains($s, 'Wijzigingen worden meteen opgeslagen'),
            'the standing autosave line must not come back'
        );
        $this->assertFalse(
            str_contains($s, 'Changes are saved immediately'),
            'nor its English counterpart'
        );
        $this->assertFalse(
            str_contains($s, 'cma-page__autosave-text'),
            'the class of the removed line must not linger in the markup'
        );
    }

    public function testTheStatusItselfStays(): void
    {
        // Removing the text must not take the status container or the spinner with
        // it: setPrefsSaving() toggles the spinner by id, so it has to be there.
        $s = $this->src();
        $this->assertTrue(str_contains($s, 'id="autosaveStatus"'), 'the status container stays');
        $this->assertTrue(str_contains($s, 'id="autosaveSpinner"'), 'the spinner stays');
        $this->assertTrue(str_contains($s, "getElementById('autosaveSpinner')"), 'and the script still drives it');
    }

    public function testStylesheetHasNoOrphanRuleLeft(): void
    {
        $css = (string) file_get_contents(__DIR__ . '/../assets/css/style.css');
        $this->assertFalse(
            str_contains($css, '.cma-page__autosave-text'),
            'the rule for the removed element must go with it'
        );
        $this->assertTrue(
            str_contains($css, '.cma-page__autosave-spinner'),
            'the spinner rules stay'
        );
    }
}
