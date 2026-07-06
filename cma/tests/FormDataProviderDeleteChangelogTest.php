<?php
/**
 * Tests for Cma\FormDataProvider::buildDeleteChangelog (pre-existing).
 *
 * Run with: php tests/TestRunner.php FormDataProviderDeleteChangelogTest
 *
 * Counterpart to FormDataProviderChangelogTest (which covers the Edit
 * changelog from v1.20.1). buildDeleteChangelog is called when a record
 * is deleted: the entire record gets snapshotted as a two-column
 * "Veld / Verwijderde waarde" HTML table that lands in the
 * tblCMAMonitoring.Notificatie column for audit.
 *
 * Pure-data test — no DB, no filesystem, no bootstrap. Method invoked
 * via reflection because the production method is private static.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';

use Cma\FormDataProvider;

class FormDataProviderDeleteChangelogTest extends TestCase
{
    private \ReflectionMethod $build;

    public function setUp(): void
    {
        $ref = new \ReflectionClass(FormDataProvider::class);
        $this->build = $ref->getMethod('buildDeleteChangelog');
        $this->build->setAccessible(true);
    }

    private function build(array $formDef, array $fields): string
    {
        return (string)$this->build->invoke(null, $formDef, $fields);
    }

    private function formDef(array $fields): array
    {
        return ['_json' => ['fields' => $fields]];
    }

    // ------------------------------------------------------------------
    // Structure & header
    // ------------------------------------------------------------------

    public function testEmptyFieldsRendersHeaderOnly(): void
    {
        // No fields → just the header row inside an empty table. We do not
        // suppress the table entirely (different from Edit changelog) —
        // delete audit always wants to record "something was deleted".
        $html = $this->build($this->formDef([]), []);
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('Veld', $html);
        $this->assertStringContainsString('Verwijderde waarde', $html);
    }

    public function testHeaderHasTwoColumns(): void
    {
        // The delete table is two columns, not three (no old↔new diff).
        $html = $this->build($this->formDef([]), []);
        $this->assertEquals(2, substr_count($html, '<th'));
    }

    public function testHeaderUsesDeleteColor(): void
    {
        // Header uses the dark-red `#8B0000` background — that's the
        // operator-facing signal that "this is a delete record". The
        // Edit changelog uses `#1a4d72` (blue). If both turn the same
        // colour, audit readers can't tell the row type at a glance.
        $html = $this->build($this->formDef([]), []);
        $this->assertStringContainsString('#8B0000', $html);
        $this->assertStringNotContainsString('#1a4d72', $html);
    }

    // ------------------------------------------------------------------
    // Field rendering
    // ------------------------------------------------------------------

    public function testSingleFieldRendersOneDataRow(): void
    {
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => 'Alice']);
        // Header row + 1 data row.
        $this->assertEquals(2, substr_count($html, '<tr'));
        $this->assertStringContainsString('>Naam<', $html);
        $this->assertStringContainsString('>Alice<', $html);
    }

    public function testMultipleFieldsRenderInRowOrder(): void
    {
        $formDef = $this->formDef([
            ['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text'],
            ['name' => 'email', 'label' => 'E-mail', 'dataType' => 'text'],
        ]);
        $html = $this->build($formDef, ['naam' => 'Alice', 'email' => 'a@x']);
        // Header + 2 data rows.
        $this->assertEquals(3, substr_count($html, '<tr'));
    }

    public function testInternalFieldsAreSkipped(): void
    {
        // Same filter rule as buildEditChangelog — _x and bar__label out.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, [
            'naam' => 'Alice',
            '_internal' => 'X',
            'naam__label' => 'Alice (display)',
        ]);
        $this->assertStringContainsString('>Alice<', $html);
        $this->assertStringNotContainsString('_internal', $html);
        $this->assertStringNotContainsString('display', $html);
    }

    public function testFieldWithoutFormDefStillRendersWithRawNameAsLabel(): void
    {
        // Unlike Edit changelog (which filters to declared fields), the
        // Delete changelog renders EVERY non-internal field — even ones
        // without a definition. Operator wants the complete snapshot, so
        // a column the form doesn't know about should still appear with
        // the raw column name as its label.
        $formDef = $this->formDef([]);
        $html = $this->build($formDef, ['undeclared_col' => 'value']);
        $this->assertStringContainsString('undeclared_col', $html);
        $this->assertStringContainsString('value', $html);
    }

    // ------------------------------------------------------------------
    // Value rendering
    // ------------------------------------------------------------------

    public function testBooleanFieldRendersJaNee(): void
    {
        $formDef = $this->formDef([['name' => 'actief', 'label' => 'Actief', 'dataType' => 'boolean']]);
        $this->assertStringContainsString('Ja', $this->build($formDef, ['actief' => 1]));
        $this->assertStringContainsString('Nee', $this->build($formDef, ['actief' => 0]));
        $this->assertStringContainsString('Ja', $this->build($formDef, ['actief' => true]));
        $this->assertStringContainsString('Nee', $this->build($formDef, ['actief' => false]));
    }

    public function testBooleanViaControlTypeSwitch(): void
    {
        $formDef = $this->formDef([['name' => 'optie', 'label' => 'Optie', 'controlType' => 'switch']]);
        $this->assertStringContainsString('Ja', $this->build($formDef, ['optie' => '1']));
    }

    public function testEmptyValueRendersAsPlaceholder(): void
    {
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $this->assertStringContainsString('(leeg)', $this->build($formDef, ['naam' => '']));
        $this->assertStringContainsString('(leeg)', $this->build($formDef, ['naam' => null]));
    }

    public function testHtmlIsEscaped(): void
    {
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['naam' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testLongValueIsTruncated(): void
    {
        $formDef = $this->formDef([['name' => 'opmerking', 'label' => 'Opmerking', 'dataType' => 'text']]);
        $longValue = str_repeat('a', 1000);
        $html = $this->build($formDef, ['opmerking' => $longValue]);
        $this->assertStringContainsString('...', $html);
        $this->assertStringNotContainsString(str_repeat('a', 600), $html);
    }

    public function testArrayValueRendersAsJson(): void
    {
        $formDef = $this->formDef([['name' => 'rollen', 'label' => 'Rollen', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['rollen' => ['editor', 'admin']]);
        $this->assertStringContainsString('editor', $html);
        $this->assertStringContainsString('admin', $html);
        $this->assertStringNotContainsString('Array', $html);
    }

    public function testLabelDefaultsToFieldName(): void
    {
        // When the field def has no `label`, the field name itself is used.
        $formDef = $this->formDef([['name' => 'voornaam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['voornaam' => 'Alice']);
        $this->assertStringContainsString('voornaam', $html);
    }

    // ------------------------------------------------------------------
    // Additional coverage: string boolean representations, truncation
    // boundary, case-insensitive label/type lookup.
    // ------------------------------------------------------------------

    public function testBooleanStringRepresentationsRenderJaNee(): void
    {
        // DB drivers hand back booleans as '1'/'0' strings; with a boolean
        // dataType those must still map to Ja/Nee, not the raw digit.
        $formDef = $this->formDef([['name' => 'actief', 'label' => 'Actief', 'dataType' => 'boolean']]);
        $this->assertStringContainsString('Ja', $this->build($formDef, ['actief' => '1']));
        $this->assertStringContainsString('Nee', $this->build($formDef, ['actief' => '0']));
    }

    public function testValueOfExactly500CharsIsNotTruncated(): void
    {
        // Boundary mirror of the edit changelog: strlen > 500 truncates,
        // exactly 500 renders whole with no ellipsis.
        $formDef = $this->formDef([['name' => 'opmerking', 'label' => 'Opmerking', 'dataType' => 'text']]);
        $exact = str_repeat('a', 500);
        $html = $this->build($formDef, ['opmerking' => $exact]);
        $this->assertStringContainsString($exact, $html);
        $this->assertStringNotContainsString($exact . '...', $html);
    }

    public function testCaseInsensitiveLabelLookup(): void
    {
        // The record column can arrive UPPERCASED (Access) while the form
        // declared it lowercase; the label lookup must still resolve.
        $formDef = $this->formDef([['name' => 'naam', 'label' => 'Naam', 'dataType' => 'text']]);
        $html = $this->build($formDef, ['NAAM' => 'Alice']);
        $this->assertStringContainsString('>Naam<', $html, 'label must resolve case-insensitively');
        $this->assertStringContainsString('>Alice<', $html);
    }
}
