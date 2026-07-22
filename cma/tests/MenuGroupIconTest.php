<?php
/**
 * Tests for menuGroupIcon() (cma/menurep.inc) — the group-name -> Linearicons
 * mapping that puts an icon on each CMA menu block. Shared by the header
 * dropdowns (main.php) and the dashboard menu cards (dashboard.php).
 *
 * Regression: the dashboard menu cards had lost their icons; they now resolve
 * one through this shared helper, so the mapping + case-insensitive lookup +
 * generic fallback are worth pinning down.
 *
 * Run with: php cma/tests/TestRunner.php MenuGroupIconTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../menurep.inc';

class MenuGroupIconTest extends TestCase
{
    public function testKnownGroupsMapToTheirIcon(): void
    {
        $this->assertSame('lnr-graduation-hat', menuGroupIcon('Opleidingen'));
        $this->assertSame('lnr-users2', menuGroupIcon('Personen'));
        $this->assertSame('lnr-cog', menuGroupIcon('Systeem'));
        $this->assertSame('lnr-inbox', menuGroupIcon('Inventarisatie'));
    }

    // Regression: these two real group names contain a space/word that did not
    // match the underscore keys, so they fell back to the generic lnr-menu.
    public function testMultiWordGroupNamesResolveNotFallback(): void
    {
        $this->assertSame('lnr-file-check', menuGroupIcon('Toetsen en opdrachten'));
        $this->assertSame('lnr-book', menuGroupIcon('RINO info'));
        // These must NOT be the generic fallback.
        $this->assertTrue(menuGroupIcon('Toetsen en opdrachten') !== 'lnr-menu');
        $this->assertTrue(menuGroupIcon('RINO info') !== 'lnr-menu');
    }

    public function testLookupIsCaseInsensitive(): void
    {
        $this->assertSame('lnr-cog', menuGroupIcon('SYSTEEM'));
        $this->assertSame('lnr-graduation-hat', menuGroupIcon('opleidingen'));
    }

    public function testUnknownGroupFallsBackToGenericMenuIcon(): void
    {
        $this->assertSame('lnr-menu', menuGroupIcon('Onbekende Groep'));
        $this->assertSame('lnr-menu', menuGroupIcon(''));
    }
}
