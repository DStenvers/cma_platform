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
}
