<?php
/**
 * Tests for the singular form-title derivation used in popup/detail titles
 * ("Order wijzigen" instead of "Orders wijzigen").
 *
 * Run with: php tests/TestRunner.php FormTitleSingularTest
 *
 * Note: subformTitleSingular() with a non-empty JSON form name goes through
 * JsonFormLoader::loadRaw(), which needs a configured forms directory — the
 * harness doesn't wire that up, so only the no-form-name fallback path is
 * covered here.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';
require_once dirname(__DIR__) . '/classes/FormDefinition.php';

use Cma\FormDefinition;

class FormTitleSingularTest extends TestCase
{
    public function testEnSuffix(): void
    {
        $this->assertEquals('Opleiding', FormDefinition::dutchSingular('Opleidingen'));
        $this->assertEquals('Docent', FormDefinition::dutchSingular('Docenten'));
    }

    public function testDoubledConsonantEnSuffix(): void
    {
        $this->assertEquals('Blok', FormDefinition::dutchSingular('Blokken'));
    }

    public function testSSuffix(): void
    {
        $this->assertEquals('Order', FormDefinition::dutchSingular('Orders'));
        $this->assertEquals('Deelnemer', FormDefinition::dutchSingular('Deelnemers'));
    }

    public function testShortAndNonPluralTitlesUnchanged(): void
    {
        $this->assertEquals('Log', FormDefinition::dutchSingular('Log'));
        $this->assertEquals('Agenda', FormDefinition::dutchSingular('Agenda'));
        $this->assertEquals('', FormDefinition::dutchSingular(''));
    }

    public function testSubformSingularFallsBackToDerivedName(): void
    {
        // No JSON form name -> derive from the (plural) display name
        $this->assertEquals('Order', FormDefinition::subformTitleSingular('', 'Orders'));
    }
}
