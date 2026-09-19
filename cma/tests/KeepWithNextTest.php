<?php
/**
 * KeepWithNextTest — combineWithNext ("keep with next") zet twee velden op één rij.
 *
 * Stond hard op false ("te veel randgevallen met het sluiten van de rij"). De rij
 * van een combinerend veld wordt nu vastgehouden en pas uitgeschreven zodra een
 * niet-combinerend veld volgt; overgeslagen velden, een groepscheiding en het
 * einde van de velden schrijven hem óók uit, zodat er nooit een open <tr> blijft.
 *
 *   php cma/tests/TestRunner.php KeepWithNextTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';
require_once dirname(__DIR__) . '/classes/SecurityHelper.php';
require_once dirname(__DIR__) . '/classes/FormControlHelper.php';
require_once dirname(__DIR__) . '/classes/FormRenderer.php';
require_once dirname(__DIR__) . '/classes/FormTemplate.php';

class KeepWithNextTest extends TestCase
{
    private function render(array $fields): string
    {
        $json = ['name' => 'kwn', 'title' => 'KWN', 'table' => 'tblKwn', 'idField' => 'ID', 'database' => 'data', 'fields' => $fields];
        $conv = new \ReflectionMethod(\Cma\JsonFormLoader::class, 'convertToLegacyFormat');
        $conv->setAccessible(true);
        $legacy = $conv->invoke(null, $json);
        $legacy['_json'] = $json;

        $tpl = (new \ReflectionClass(\Cma\FormTemplate::class))->newInstanceWithoutConstructor();
        foreach (['arrRep' => $legacy, 'formDef' => \Cma\FormDefinition::fromArray($legacy), 'accessLevel' => \Cma\SecurityHelper::ACCESS_FULL, 'sourceFormId' => 0, 'jsonFormName' => 'kwn', 'arrSubForms' => null] as $prop => $value) {
            $p = new \ReflectionProperty(\Cma\FormTemplate::class, $prop);
            $p->setAccessible(true);
            $p->setValue($tpl, $value);
        }
        $m = new \ReflectionMethod(\Cma\FormTemplate::class, 'generateFormFields');
        $m->setAccessible(true);
        return $m->invoke($tpl);
    }

    public function testTweeVeldenOpEenRij(): void
    {
        $html = $this->render([
            ['name' => 'datum', 'type' => 'textbox', 'caption' => 'Datum', 'combineWithNext' => true],
            ['name' => 'tijd', 'type' => 'textbox', 'caption' => 'Tijd'],
            ['name' => 'naam', 'type' => 'textbox', 'caption' => 'Naam'],
        ]);
        $this->assertSame(2, substr_count($html, '<tr data-field-row='), $html);
        $this->assertStringContainsString('<div class="next_col" data-field-col="tijd"><span>Tijd</span>', $html);
        $this->assertSame(substr_count($html, '<tr'), substr_count($html, '</tr>'), 'elke rij is gesloten');
    }

    public function testLaatsteVeldMetCombineWithNextKrijgtTochEenRij(): void
    {
        $html = $this->render([
            ['name' => 'a', 'type' => 'textbox', 'caption' => 'A'],
            ['name' => 'b', 'type' => 'textbox', 'caption' => 'B', 'combineWithNext' => true],
        ]);
        $this->assertSame(2, substr_count($html, '<tr data-field-row='));
        $this->assertStringContainsString('data-field-row="b"', $html);
    }

    public function testGroepscheidingSluitDeVastgehoudenRij(): void
    {
        $html = $this->render([
            ['name' => 'a', 'type' => 'textbox', 'caption' => 'A', 'combineWithNext' => true],
            ['name' => 'g', 'type' => 'groupseparator', 'caption' => 'Groep'],
            ['name' => 'b', 'type' => 'textbox', 'caption' => 'B'],
        ]);
        $posA = strpos($html, 'data-field-row="a"');
        $posG = strpos($html, '<cma-groupbox');
        $this->assertTrue($posA !== false && $posG !== false && $posA < $posG, 'rij a staat vóór de groepscheiding');
        $this->assertStringNotContainsString('next_col', $html);
    }
}
