<?php
/**
 * Tests for Cma\JsonFormLoader::validateDefinitionData — the form-definition
 * guardrail that blocks a form on screen when its JSON is missing crucial
 * information instead of letting it silently half-work (a checklist whose
 * relation columns are absent saves nothing; an image field with no path has
 * nowhere to store to).
 *
 * Run with: php tests/TestRunner.php JsonFormValidationTest
 *
 * Uses the pure validateDefinitionData() entry point so no cache, filesystem
 * or DB is needed. Path-existence (image/file with a configured path) is left
 * to integration since it needs DOCUMENT_ROOT; the cases here cover structure,
 * field names, checklist relations and empty upload paths.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';

use Cma\JsonFormLoader;

class JsonFormValidationTest extends TestCase
{
    private function validate(array $data): array
    {
        return JsonFormLoader::validateDefinitionData($data, 'testform');
    }

    public function testEmptyDefinitionIsRejected(): void
    {
        $this->assertEquals(1, count(JsonFormLoader::validateDefinitionData([], 'testform')));
        $this->assertEquals(1, count(JsonFormLoader::validateDefinitionData(null, 'testform')));
    }

    public function testNoFieldsAndNoTableIsRejected(): void
    {
        $problems = $this->validate(['name' => 'testform']);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString("Geen velden", $problems[0]);
    }

    public function testTableWithoutFieldsIsAllowed(): void
    {
        // Fields are auto-generated from the table schema, so this is valid.
        $this->assertEquals(0, count($this->validate(['table' => 'tblFoo', 'fields' => []])));
    }

    public function testFieldWithoutNameIsRejected(): void
    {
        $problems = $this->validate(['fields' => [['type' => 'textbox']]]);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString("geen 'name'", $problems[0]);
    }

    public function testPresentationalFieldNeedsNoName(): void
    {
        $this->assertEquals(0, count($this->validate(['fields' => [['type' => 'groupseparator']]])));
        $this->assertEquals(0, count($this->validate(['fields' => [['type' => 'label']]])));
    }

    public function testChecklistMissingRelationColumnsIsRejected(): void
    {
        // Missing all three relation columns → three problems.
        $problems = $this->validate(['fields' => [['name' => 'cats', 'type' => 'checklist']]]);
        $this->assertEquals(3, count($problems));
    }

    public function testChecklistWithRelationColumnsIsValid(): void
    {
        $problems = $this->validate(['fields' => [[
            'name' => 'cats',
            'type' => 'checklist',
            'sourceTable' => 'tblProductCategory',
            'idField' => 'ProductID',
            'displayField' => 'CategoryID',
        ]]]);
        $this->assertEquals(0, count($problems));
    }

    public function testNumericChecklistTypeIsRecognised(): void
    {
        // Type may be the legacy numeric control-type id (8 = checklist).
        $problems = $this->validate(['fields' => [['name' => 'cats', 'type' => 8]]]);
        $this->assertEquals(3, count($problems));
    }

    public function testImageFieldWithoutPathIsRejected(): void
    {
        $problems = $this->validate(['fields' => [['name' => 'photo', 'type' => 'image']]]);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString("geen pad", $problems[0]);
    }

    public function testValidSimpleFormPasses(): void
    {
        $problems = $this->validate(['name' => 'testform', 'table' => 'tblFoo', 'fields' => [
            ['name' => 'title', 'type' => 'textbox'],
            ['type' => 'groupseparator'],
            ['name' => 'body', 'type' => 'memo'],
        ]]);
        $this->assertEquals(0, count($problems));
    }

    // ------------------------------------------------------------------
    // Additional coverage: non-array fields, file fields, path aliases,
    // URL paths, tip presentational, multiple accumulated problems.
    // ------------------------------------------------------------------

    public function testNonArrayFieldIsRejected(): void
    {
        // A scalar where a field object is expected → "is geen object".
        $problems = $this->validate(['fields' => ['not-an-object']]);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString('is geen object', $problems[0]);
    }

    public function testTipFieldNeedsNoName(): void
    {
        // 103 = tip — presentational, so no 'name' is required.
        $this->assertEquals(0, count($this->validate(['fields' => [['type' => 'tip']]])));
        $this->assertEquals(0, count($this->validate(['fields' => [['type' => 103]]])));
    }

    public function testFileFieldWithoutPathIsRejected(): void
    {
        $problems = $this->validate(['fields' => [['name' => 'attachment', 'type' => 'file']]]);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString('geen pad', $problems[0]);
    }

    public function testNumericImageTypeWithoutPathIsRejected(): void
    {
        // 9 = image via the legacy numeric control-type id.
        $problems = $this->validate(['fields' => [['name' => 'photo', 'type' => 9]]]);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString('geen pad', $problems[0]);
    }

    public function testImageFieldWithUrlPathPasses(): void
    {
        // An http(s) path is served elsewhere; the directory check is skipped,
        // so a configured URL path is accepted with no problems.
        $problems = $this->validate(['fields' => [[
            'name' => 'photo', 'type' => 'image', 'path' => 'https://cdn.example/imgs',
        ]]]);
        $this->assertEquals(0, count($problems));
    }

    public function testImageLegacyImagePathAliasIsAccepted(): void
    {
        // The legacy 'imagePath' key is honoured as a fallback for 'path'.
        $problems = $this->validate(['fields' => [[
            'name' => 'photo', 'type' => 'image', 'imagePath' => 'https://cdn.example/imgs',
        ]]]);
        $this->assertEquals(0, count($problems));
    }

    public function testFileLegacyFilePathAliasIsAccepted(): void
    {
        $problems = $this->validate(['fields' => [[
            'name' => 'doc', 'type' => 'file', 'filePath' => 'https://cdn.example/files',
        ]]]);
        $this->assertEquals(0, count($problems));
    }

    public function testMultipleProblemsAccumulateAcrossFields(): void
    {
        // Two distinct fields each with their own problem → two entries.
        $problems = $this->validate(['fields' => [
            ['type' => 'textbox'],                 // missing name → 1 problem
            ['name' => 'photo', 'type' => 'image'], // missing path → 1 problem
        ]]);
        $this->assertEquals(2, count($problems));
    }

    public function testUnknownStringTypeDefaultsToTextboxAndNeedsName(): void
    {
        // An unrecognised type string falls back to control-type 3 (textbox),
        // which is not presentational, so a missing name is still flagged.
        $problems = $this->validate(['fields' => [['type' => 'totally-unknown-type']]]);
        $this->assertEquals(1, count($problems));
        $this->assertStringContainsString("geen 'name'", $problems[0]);
    }
}
