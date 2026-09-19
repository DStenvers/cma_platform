<?php
/**
 * JsonFormService::defaultListColumns — the table view's default columns:
 * the first LIST_DEFAULT_COLUMNS fields in detail order, without the types
 * that make no column and without the required filter field.
 *
 *   php cma/tests/TestRunner.php DefaultListColumnsTest
 */
require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/FieldType.php';
require_once __DIR__ . '/../classes/Services/BaseFormService.php';
require_once __DIR__ . '/../classes/Services/JsonFormService.php';

use App\Library\Settings;
use Cma\Services\JsonFormService;

class DefaultListColumnsTest extends TestCase
{
    public function tearDown(): void
    {
        unset($_ENV['LIST_DEFAULT_COLUMNS']);
        Settings::reset();
    }

    private function toetsen(): array
    {
        $f = fn(string $n, string $t = 'textbox', array $extra = []) => array_merge(['name' => $n, 'type' => $t, 'caption' => ucfirst($n)], $extra);
        return [
            'idField' => 'ID',
            'filter' => ['field' => 'fkOpleiding', 'required' => true],
            'fields' => [
                $f('fkOpleiding', 'combobox'),
                $f('naam'), $f('_group_datums', 'groupseparator'), $f('plaatsingsdatum', 'date'), $f('inleverdatum', 'date'),
                $f('omschrijving', 'memo'), $f('fkDocent', 'combobox'), $f('fkDocent2', 'combobox'), $f('fkDocent3', 'combobox'), $f('fkDocent4', 'combobox'),
                $f('verplicht', 'checkbox'), $f('geheim', 'checkbox', ['skipInTableView' => true]), $f('foto', 'image', ['showInTableView' => true]), $f('extra'),
            ],
        ];
    }

    private function names(array $cols): array
    {
        return array_column($cols, 'field');
    }

    public function testSixInDetailOrderSkippingWhatIsNoColumn(): void
    {
        $cols = JsonFormService::defaultListColumns($this->toetsen(), 'ID', 6);
        $this->assertSame(['naam', 'plaatsingsdatum', 'inleverdatum', 'fkDocent', 'fkDocent2', 'fkDocent3'], $this->names($cols),
            'filter field, group separator and memo are skipped; the cap counts real columns');
    }

    public function testTheCapIsTheOnlyThingThatHidesDocent4(): void
    {
        $cols = JsonFormService::defaultListColumns($this->toetsen(), 'ID', 999);
        $this->assertSame(['naam', 'plaatsingsdatum', 'inleverdatum', 'fkDocent', 'fkDocent2', 'fkDocent3', 'fkDocent4', 'verplicht', 'foto', 'extra'], $this->names($cols),
            'skipInTableView hides, showInTableView opts an image back in');
    }

    public function testTheSettingDefaultsToSix(): void
    {
        $this->assertSame(6, Settings::get('list_default_columns'));
        $_ENV['LIST_DEFAULT_COLUMNS'] = '3';
        Settings::reset();
        $this->assertSame(3, Settings::get('list_default_columns'));
    }
}
