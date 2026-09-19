<?php
/**
 * DirectoryUrlServiceTest — een "directory"-veld maakt een record bereikbaar als
 * /<waarde>/; de dirTemplate van het veld wordt met [ID]/[veldnaam] ingevuld.
 *
 *   php cma/tests/TestRunner.php DirectoryUrlServiceTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/DirectoryUrlService.php';

use Cma\Services\DirectoryUrlService;

class DirectoryUrlServiceTest extends TestCase
{
    public function testPlaceholdersWordenIngevuld(): void
    {
        $out = DirectoryUrlService::fillTemplate('<h1>[Titel]</h1><a href="/opleiding.php?id=[ID]">meer</a> [onbekend]', ['ID' => 12, 'Titel' => 'A & B', 'Dir' => 'AANMELDEN']);
        $this->assertSame('<h1>A &amp; B</h1><a href="/opleiding.php?id=12">meer</a> [onbekend]', $out);
    }

    public function testLegeTemplateGeeftLeeg(): void
    {
        $this->assertSame('', DirectoryUrlService::fillTemplate('', ['ID' => 1]));
    }
}
