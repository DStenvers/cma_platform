<?php
/**
 * Regression test for Cma\Services\OptionsService::findFieldIndex.
 *
 * Run with: php tests/TestRunner.php OptionsServiceFieldIndexTest
 *
 * Guards the "empty comboboxes when a second form/tab is open" bug. An earlier
 * findFieldIndex memoised its field→index map in a process-static array keyed by
 * md5(first 3 field names). Under FastCGI that static outlives the request, so two
 * DIFFERENT forms whose opening columns matched (ID, Naam, Datum, … — very common)
 * collided on one cached map: the second form's own fields resolved to -1 and its
 * combos came back empty. findFieldIndex is now a pure scan holding no state; these
 * tests lock that in by resolving fields on two forms that share their first three
 * column names but differ afterwards.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/Services/BaseFormService.php';
require_once dirname(__DIR__) . '/classes/Services/OptionsService.php';

use Cma\Services\OptionsService;

class OptionsServiceFieldIndexTest extends TestCase
{
    /** Build a column-major form definition from an ordered list of field names. */
    private function formDef(array $fieldNames): array
    {
        return [Q_FIELDNAME => $fieldNames];
    }

    public function testFindsFieldInSingleForm(): void
    {
        $form = $this->formDef(['ID', 'Naam', 'Datum', 'Status']);
        $this->assertSame(0, OptionsService::findFieldIndex($form, 'ID'));
        $this->assertSame(3, OptionsService::findFieldIndex($form, 'Status'));
    }

    public function testMissingFieldReturnsMinusOne(): void
    {
        $form = $this->formDef(['ID', 'Naam', 'Datum']);
        $this->assertSame(-1, OptionsService::findFieldIndex($form, 'DoesNotExist'));
    }

    public function testMatchIsCaseInsensitive(): void
    {
        $form = $this->formDef(['ID', 'Naam', 'Datum', 'fkStatus']);
        $this->assertSame(3, OptionsService::findFieldIndex($form, 'FKSTATUS'));
    }

    /**
     * The core regression: two forms sharing their first three column names must
     * each resolve their OWN distinct later fields — no cross-form collision.
     */
    public function testTwoFormsSharingOpeningColumnsDoNotCollide(): void
    {
        $formA = $this->formDef(['ID', 'Naam', 'Datum', 'fkOpleiding']);
        $formB = $this->formDef(['ID', 'Naam', 'Datum', 'fkDocent']);

        // Resolve A's own field first, then B's — order must not matter.
        $this->assertSame(3, OptionsService::findFieldIndex($formA, 'fkOpleiding'));
        $this->assertSame(3, OptionsService::findFieldIndex($formB, 'fkDocent'));

        // The buggy static map would have made each form see the OTHER's fields.
        $this->assertSame(-1, OptionsService::findFieldIndex($formA, 'fkDocent'));
        $this->assertSame(-1, OptionsService::findFieldIndex($formB, 'fkOpleiding'));
    }

    /**
     * Same first-three names but a shared field at a DIFFERENT index in each form:
     * every lookup must reflect the form actually passed in, not a cached sibling.
     */
    public function testSharedFieldAtDifferentIndexResolvesPerForm(): void
    {
        $formA = $this->formDef(['ID', 'Naam', 'Datum', 'Notitie', 'fkStatus']);
        $formB = $this->formDef(['ID', 'Naam', 'Datum', 'fkStatus']);

        $this->assertSame(4, OptionsService::findFieldIndex($formA, 'fkStatus'));
        $this->assertSame(3, OptionsService::findFieldIndex($formB, 'fkStatus'));
    }
}
