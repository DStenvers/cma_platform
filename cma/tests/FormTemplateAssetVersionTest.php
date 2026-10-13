<?php
/**
 * FormTemplateAssetVersionTest — het gecachte formuliersjabloon bevat de
 * bundle-URL's (v=cma_asset_version()). Een sjabloon dat een deploy overleeft
 * (APCu niet geleegd, of een bestand van vóór de release) laat de browser dus
 * de JS van de vorige release laden. De APCu-sleutel draagt daarom de
 * assetversie, en een sjabloonbestand ouder dan bootstrap.inc telt niet.
 *
 *   php cma/tests/TestRunner.php FormTemplateAssetVersionTest
 */

require_once __DIR__ . '/TestRunner.php';

class FormTemplateAssetVersionTest extends TestCase
{
    private function bron(): string
    {
        return file_get_contents(dirname(__DIR__) . '/classes/FormTemplate.php');
    }

    public function testApcuSleutelBevatDeAssetversie(): void
    {
        $bron = $this->bron();
        $start = strpos($bron, 'private static function jsonCacheKey(');
        $this->assertTrue($start !== false, 'jsonCacheKey niet gevonden');
        $this->assertStringContainsString('cma_asset_version()', substr($bron, $start, 300));
        // Alle plekken die de sleutel bouwen gebruiken de helper
        $this->assertEquals(1, substr_count($bron, "self::CACHE_PREFIX . 'json_'"));
    }

    public function testSjabloonbestandOuderDanBootstrapTeltNiet(): void
    {
        $this->assertStringContainsString("filemtime(\$filePath) >= (int) @filemtime(__DIR__ . '/../bootstrap.inc')", $this->bron());
    }
}
