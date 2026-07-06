<?php
/**
 * Tests for Cma\FormDataProvider::buildEditChangelog (added in v1.20.1).
 *
 * Run with: php tests/TestRunner.php FormDataProviderChangelogTest
 *
 * Covers the server-side fallback path that fires when the client-side
 * JS that normally builds _changelog erred out (POST size truncation,
 * JS exception, blocked field collection). The method takes three
 * arrays in, returns an HTML-table string out — no DB, no filesystem,
 * pure-data test as outlined in the docs `testing` topic.
 *
 * Coverage:
 * - No changes → empty string (don't pollute Notificatie)
 * - Single & multiple field changes render the right table rows
 * - Unchanged fields are skipped (normalized equality)
 * - Internal/virtual field names (`_x`, `bar__label`) are skipped
 * - Undeclared fields (not in formDef) are skipped
 * - Case-insensitive old↔new column matching (DB returns may differ)
 * - Boolean rendering: Ja/Nee instead of 1/0
 * - Empty/null rendering: "(leeg)" placeholder
 * - HTML escaping of values
 * - Long values truncated at 500 chars
 * - Array values JSON-encoded
 */

require_once __DIR__ . '/TestRunner.php';
// Autoload is wired by TestRunner's CLI entry point; the test file should
// not re-require vendor/autoload.php directly (the path differs between
// consumer-site and platform-repo layouts).
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';

use Cma\FormDataProvider;

class FormDataProviderChangelogTest extends TestCase
{
    // Reflection handle to the private static method under test. Reused
    // across all test methods so we set it up once in setUp.
    private \ReflectionMethod $build;

    public function setUp(): void
    {
        $ref = new \ReflectionClass(FormDataProvider::class);
        $this->build = $ref->getMethod('buildEditChangelog');
        $this->build->setAccessible(true);
    }

    /**
     * Shorthand to invoke the private static and get the rendered HTML.
     */
    private function build(array $formDef, array $oldFields, array $newData): string
    {
        return (string)$this->build->invoke(null, $formDef, $oldFields, $newData);
    }

    /**
     * Minimal form definition shape that buildEditChangelog reads from.
     * Real form defs have much more, but the method only needs `_json.fields`.
     */
    private function formDef(array $fields): array
    {
        return ['_json' => ['fields' => $fields]];
    }

    // ------------------------------------------------------------------
    // No changes → empty output
    // ------------------------------------------------------------------

    public function testReturnsEmptyWhenNoFieldsDefined(): void
    {
        $html = $this->build($this->formDef([]), ['foo' => 'a'], ['foo' => 'b']);
        // No field declarations → no rows to consider → no table.
        $this->assertEquals('', $html);
    }

    public function testReturnsEmptyWhenValuesAreEqual(): void
    {
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'Alice'], ['naam' => 'Alice']);
        $this->assertEquals('', $html);
    }

    public function testReturnsEmptyWhenOnlyWhitespaceDiffers(): void
    {
        // normalizeChangelogValue trim()s strings — leading/trailing whitespace
        // shouldn't fire a "change" entry. Catches the case where SaveValue() in
        // form-controller.js sometimes wraps values with spaces.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'Alice'], ['naam' => '  Alice  ']);
        $this->assertEquals('', $html);
    }

    // ------------------------------------------------------------------
    // Single-field changes render the diff
    // ------------------------------------------------------------------

    public function testSingleFieldChangeProducesOneRow(): void
    {
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'Alice'], ['naam' => 'Bob']);
        $this->assertNotEquals('', $html, 'Expected non-empty output for changed field');
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('>Naam<', $html, 'Field label must appear in the table');
        $this->assertStringContainsString('>Alice<', $html, 'Old value must appear in the table');
        $this->assertStringContainsString('>Bob<', $html, 'New value must appear in the table');
        // Three columns means three <th> elements.
        $this->assertEquals(3, substr_count($html, '<th'), 'Expected three header columns');
    }

    public function testMultipleFieldChangesProduceMultipleRows(): void
    {
        $formDef = $this->formDef([
            ['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text'],
            ['name' => 'email', 'label' => 'E-mail', 'dataType' => 'text'],
            ['name' => 'rol', 'label' => 'Rol', 'dataType' => 'text'],
        ]);
        $html = $this->build($formDef,
            ['naam' => 'A', 'email' => 'a@x', 'rol' => 'editor'],
            ['naam' => 'B', 'email' => 'b@x', 'rol' => 'editor']
        );
        // Header row + 2 changed-data rows = 3 <tr> total. (rol unchanged)
        $this->assertEquals(3, substr_count($html, '<tr'));
        $this->assertStringNotContainsString('>Rol<', $html, 'Unchanged field must not render');
    }

    // ------------------------------------------------------------------
    // Field-name filtering
    // ------------------------------------------------------------------

    public function testInternalFieldsAreSkipped(): void
    {
        // Fields starting with _ or ending with __label are virtual/internal.
        $formDef = $this->formDef([
            ['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text'],
            ['name' => '_internal', 'label' => 'Internal', 'dataType' => 'text'],
            ['name' => 'naam__label', 'label' => 'Display Label', 'dataType' => 'text'],
        ]);
        $html = $this->build($formDef,
            ['naam' => 'Alice', '_internal' => 'X', 'naam__label' => 'Alice (legacy)'],
            ['naam' => 'Bob', '_internal' => 'Y', 'naam__label' => 'Bob (new)']
        );
        // Only naam should render — internal + label fields stripped.
        $this->assertEquals(2, substr_count($html, '<tr'), 'Expected one header + one data row');
        $this->assertStringNotContainsString('>Internal<', $html);
        $this->assertStringNotContainsString('>Display Label<', $html);
    }

    public function testUndeclaredFieldsAreSkipped(): void
    {
        // Field not in formDef.fields → should never appear in changelog.
        // This is how we filter out request artefacts like `action`, `formID`.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef,
            ['naam' => 'Alice'],
            ['naam' => 'Bob', 'random_post_field' => 'leaks_in']
        );
        $this->assertStringNotContainsString('random_post_field', $html);
    }

    public function testCaseInsensitiveOldNewMatching(): void
    {
        // DB drivers sometimes return column names in a different case than
        // the form declared (Access often UPPERCASEs, SQL Server preserves).
        // The diff must compare by case-insensitive name lookup.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['NAAM' => 'Alice'], ['naam' => 'Bob']);
        $this->assertStringContainsString('>Alice<', $html, 'Old value must be matched case-insensitively');
        $this->assertStringContainsString('>Bob<', $html);
    }

    // ------------------------------------------------------------------
    // Value rendering
    // ------------------------------------------------------------------

    public function testBooleanFieldRendersJaNee(): void
    {
        $formDef = $this->formDef([['name' => 'actief', 'label' => 'Actief', 'dataType' => 'boolean']]);
        $html = $this->build($formDef, ['actief' => 0], ['actief' => 1]);
        $this->assertStringContainsString('>Nee<', $html, 'Old boolean value should render as Nee');
        $this->assertStringContainsString('>Ja<', $html, 'New boolean value should render as Ja');
    }

    public function testBooleanByControlTypeCheckbox(): void
    {
        // dataType blank, controlType=checkbox → treated as boolean.
        $formDef = $this->formDef([['name' => 'optie', 'label' => 'Optie', 'controlType' => 'checkbox']]);
        $html = $this->build($formDef, ['optie' => '0'], ['optie' => '1']);
        $this->assertStringContainsString('>Nee<', $html);
        $this->assertStringContainsString('>Ja<', $html);
    }

    public function testBooleanEqualityNormalised(): void
    {
        // 0 ↔ '0' ↔ false should all be equal under boolean normalization.
        // No row should render for these comparisons.
        $formDef = $this->formDef([['name' => 'actief', 'label' => 'Actief', 'dataType' => 'boolean']]);
        $this->assertEquals('', $this->build($formDef, ['actief' => 0], ['actief' => '0']));
        $this->assertEquals('', $this->build($formDef, ['actief' => 1], ['actief' => true]));
    }

    public function testEmptyValueRendersAsPlaceholder(): void
    {
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'Alice'], ['naam' => '']);
        // Empty new value → "(leeg)" placeholder span instead of blank cell.
        $this->assertStringContainsString('(leeg)', $html);
    }

    public function testHtmlIsEscapedInValues(): void
    {
        // A user-entered value containing < or > or & must be encoded so it
        // doesn't break the rendered table when shown in the monitoring tool.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'plain'], ['naam' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html, 'Raw script tag must not pass through');
        $this->assertStringContainsString('&lt;script&gt;', $html, 'Escaped form should appear');
    }

    public function testLongValueIsTruncatedAt500Chars(): void
    {
        $formDef = $this->formDef([['name' => 'opmerking', 'label' => 'Opmerking', 'dataType' => 'text']]);
        $longValue = str_repeat('a', 1000);
        $html = $this->build($formDef, ['opmerking' => 'short'], ['opmerking' => $longValue]);
        $this->assertStringContainsString('...', $html, 'Truncation ellipsis must appear');
        // The full 1000-char value must not be in the output.
        $this->assertStringNotContainsString(str_repeat('a', 600), $html, 'Full long value must not appear');
    }

    public function testArrayValueRendersAsJson(): void
    {
        // Multi-select fields arrive as arrays; rendering as JSON keeps the
        // audit trail readable instead of dumping a PHP-style print_r.
        $formDef = $this->formDef([['name' => 'rollen', 'label' => 'Rollen', 'dataType' => 'text']]);
        $html = $this->build($formDef,
            ['rollen' => ['editor']],
            ['rollen' => ['editor', 'admin']]
        );
        $this->assertStringContainsString('admin', $html);
        $this->assertStringContainsString('editor', $html);
        // JSON-style output, not PHP's Array().
        $this->assertStringNotContainsString('Array', $html);
    }

    // ------------------------------------------------------------------
    // Regression-guard: defensive against malformed input
    // ------------------------------------------------------------------

    public function testOldFieldsCanBeEmpty(): void
    {
        // Caller (saveJsonFormRecord) sets oldFields = [] when the pre-update
        // SELECT failed. The method should not throw on empty oldFields; it
        // just renders all fields as "(leeg) → new value" diffs.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, [], ['naam' => 'Alice']);
        $this->assertStringContainsString('(leeg)', $html);
        $this->assertStringContainsString('>Alice<', $html);
    }

    public function testNewDataCanBeEmpty(): void
    {
        // No new values to iterate → no rows → empty output.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'Alice'], []);
        $this->assertEquals('', $html);
    }

    // ------------------------------------------------------------------
    // Additional coverage: label fallback, null-old rendering, array
    // equality, 500-char truncation boundary.
    // ------------------------------------------------------------------

    public function testLabelDefaultsToFieldNameWhenNoLabel(): void
    {
        // A declared field with no explicit label uses its own name.
        $formDef = $this->formDef([['name' => 'voornaam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['voornaam' => 'Alice'], ['voornaam' => 'Bob']);
        $this->assertStringContainsString('>voornaam<', $html);
    }

    public function testNullOldValueRendersLeegPlaceholder(): void
    {
        // Old value NULL (e.g. column was empty) → "(leeg)" in the Oud column,
        // new value shown normally.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => null], ['naam' => 'Bob']);
        $this->assertStringContainsString('(leeg)', $html);
        $this->assertStringContainsString('>Bob<', $html);
    }

    public function testEqualArraysProduceNoRow(): void
    {
        // Same multi-select value on both sides → normalised JSON equal → no change.
        $formDef = $this->formDef([['name' => 'rollen', 'label' => 'Rollen', 'dataType' => 'text']]);
        $html = $this->build($formDef,
            ['rollen' => ['editor', 'admin']],
            ['rollen' => ['editor', 'admin']]
        );
        $this->assertEquals('', $html);
    }

    public function testValueOfExactly500CharsIsNotTruncated(): void
    {
        // Boundary: strlen > 500 truncates; exactly 500 must pass through whole.
        $formDef = $this->formDef([['name' => 'opmerking', 'label' => 'Opmerking', 'dataType' => 'text']]);
        $exact = str_repeat('a', 500);
        $html = $this->build($formDef, ['opmerking' => 'short'], ['opmerking' => $exact]);
        $this->assertStringContainsString($exact, $html, '500-char value must render intact');
        $this->assertStringNotContainsString($exact . '...', $html, 'no ellipsis at exactly 500');
    }
}
