<?php
/**
 * DerivedColumnsTest — kolommen die bij opslaan uit andere velden gevuld worden,
 * zoals detailsRep_post.asp deed: breedte/hoogte van een afbeelding, een _tn-
 * thumbnail en de platte tekst van een HTML-veld (htmlstrip).
 *
 *   php cma/tests/TestRunner.php DerivedColumnsTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/TestHarness.php';
require_once dirname(__DIR__) . '/classes/Services/ListServiceHelper.php';
require_once dirname(__DIR__) . '/classes/Services/Logger.php';
require_once dirname(__DIR__) . '/classes/FormDataProvider.php';

use Cma\FormDataProvider;

class DerivedColumnsTest extends TestCase
{
    public function testHtmlstripGeeftPlatteTekst(): void
    {
        $defs = [
            ['name' => 'Tekst', 'type' => 'memo'],
            ['name' => 'TekstPlat', 'type' => 'htmlstrip', 'baseField' => 'Tekst', 'maxLength' => 200],
        ];
        $out = FormDataProvider::derivedColumns($defs, ['Tekst' => '<p>Hallo <b>wereld</b>&nbsp;&amp; co</p><p>Regel&nbsp;2</p>']);
        $this->assertSame("Hallo wereld & co\nRegel 2", $out['TekstPlat']);
    }

    public function testBreedteEnHoogteUitDePostOfGewist(): void
    {
        $defs = [['name' => 'Foto', 'type' => 'image', 'path' => 'uploads/', 'widthField' => 'FotoBreedte', 'heightField' => 'FotoHoogte']];
        $out = FormDataProvider::derivedColumns($defs, ['Foto' => 'a.jpg', 'Foto_width' => '640', 'Foto_height' => '480']);
        $this->assertSame(['FotoBreedte' => 640, 'FotoHoogte' => 480], $out);
        $leeg = FormDataProvider::derivedColumns($defs, ['Foto' => '']);
        $this->assertSame(['FotoBreedte' => '', 'FotoHoogte' => ''], $leeg);
    }

    public function testThumbnailWordtGemaaktEnBenoemd(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            $this->assertTrue(true); // geen GD in deze PHP
            return;
        }
        $root = rtrim(\App\Library\Server::mapPath(\App\Library\Application::get('base_path', '/')), '/\\');
        $dir = $root . '/.cache/cma/test_tn';
        @mkdir($dir, 0755, true);
        $img = imagecreatetruecolor(400, 300);
        imagepng($img, $dir . '/foto.png');
        imagedestroy($img);
        @unlink($dir . '/foto_tn.png');

        $defs = [
            ['name' => 'Foto', 'type' => 'image', 'path' => '.cache/cma/test_tn/'],
            ['name' => 'FotoKlein', 'type' => 'thumbnail', 'baseField' => 'Foto', 'path' => '.cache/cma/test_tn/', 'resizeWidth' => 100, 'resizeHeight' => 75],
        ];
        $out = FormDataProvider::derivedColumns($defs, ['Foto' => 'foto.png']);
        $this->assertSame('foto_tn.png', $out['FotoKlein']);
        $this->assertTrue(is_file($dir . '/foto_tn.png'), 'thumbnail staat naast het origineel');
        $size = getimagesize($dir . '/foto_tn.png');
        $this->assertTrue($size !== false && $size[0] <= 100 && $size[1] <= 75, 'thumbnail is verkleind');
        @unlink($dir . '/foto_tn.png');
        @unlink($dir . '/foto.png');
        @rmdir($dir);
    }
}
