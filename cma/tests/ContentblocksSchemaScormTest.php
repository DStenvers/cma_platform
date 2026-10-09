<?php
/**
 * ContentblocksSchemaScormTest — het contentblok-schema kent de veldtypes die
 * blockedit.js rendert, waaronder "switch" (met options/default) en "scorm".
 * Een site-blok als S01 (SCORM-module) moet dus zonder schemafout op te slaan zijn.
 *
 *   php cma/tests/TestRunner.php ContentblocksSchemaScormTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use App\Library\JsonSchema;

class ContentblocksSchemaScormTest extends TestCase
{
    private function schema(): array
    {
        return json_decode((string)file_get_contents(dirname(__DIR__) . '/config/schema/contentblocks.schema.json'), true);
    }

    private function blok(array $variables): array
    {
        return ['templates' => [[
            'id' => 'S01',
            'title' => 'SCORM',
            'html' => '<div class="cb cb--scorm mr-scorm-embed" data-scorm-id="[scorm_id]" data-voortgang="[scorm_voortgang]" data-titel="[scorm_titel]"></div>',
            'variables' => $variables,
        ]]];
    }

    public function testScormBlokIsGeldig(): void
    {
        $errors = JsonSchema::validate($this->blok([
            'scorm_id' => ['description' => 'SCORM-pakket', 'type' => 'scorm', 'required' => true],
            'scorm_titel' => ['description' => 'Titel (boven de module)', 'type' => 'text'],
            'scorm_voortgang' => ['description' => 'Voortgang bijhouden', 'type' => 'switch', 'options' => ['Nee', 'Ja'], 'default' => 'Nee'],
        ]), $this->schema());
        $this->assertSame([], $errors);
    }

    public function testOnbekendTypeBlijftFout(): void
    {
        $errors = JsonSchema::validate($this->blok([
            'x' => ['description' => 'x', 'type' => 'bestaatniet'],
        ]), $this->schema());
        $this->assertNotEmpty($errors);
    }
}
