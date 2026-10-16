<?php
/**
 * JsonFormLoaderApcuBronTest — een formulierdefinitie uit APCu telt alleen zolang de
 * JSON-bron niet gewijzigd is.
 *
 *   php cma/tests/TestRunner.php JsonFormLoaderApcuBronTest
 *
 * load() en loadRaw() bewaren een definitie een uur in APCu. De bestandscache vergelijkt
 * al de mtime van de bron; zonder diezelfde vergelijking bij APCu bleef een wijziging in
 * assets/forms/<naam>.json tot een uur onzichtbaar. De APCu-waarde onthoudt daarom de
 * mtime van de bron waaruit hij gemaakt is, en een andere mtime is een misser.
 *
 * Vereist APCu met apc.enable_cli=1 (anders overgeslagen). De bestandscache staat in
 * deze test uit, zodat alleen de APCu-laag wordt getoetst.
 */

require_once __DIR__ . '/TestRunner.php';
require_once dirname(__DIR__) . '/classes/form_constants.php';
require_once dirname(__DIR__) . '/classes/JsonFormLoader.php';

use Cma\JsonFormLoader;

class JsonFormLoaderApcuBronTest extends TestCase
{
    private const FORM = 'zz_apcu_bron_test';

    private string $dir = '';
    private bool $dirAangemaakt = false;
    private bool $assetsAangemaakt = false;

    public function setUp(): void
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            return;
        }
        $root = dirname(__DIR__, 2);
        $this->assetsAangemaakt = !is_dir($root . '/assets');
        $this->dir = $root . '/assets/forms';
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
            $this->dirAangemaakt = true;
        }
        JsonFormLoader::setFileCacheEnabled(false);
        $this->vergeetGeheugen();
        apcu_delete('jfl_' . self::FORM . '_raw');
        apcu_delete('jfl_' . self::FORM . '_legacy');
    }

    public function tearDown(): void
    {
        if ($this->dir === '') {
            return;
        }
        @unlink($this->dir . '/' . self::FORM . '.json');
        if ($this->dirAangemaakt) {
            @rmdir($this->dir);
        }
        if ($this->assetsAangemaakt) {
            @rmdir(dirname($this->dir));
        }
        apcu_delete('jfl_' . self::FORM . '_raw');
        apcu_delete('jfl_' . self::FORM . '_legacy');
        JsonFormLoader::setFileCacheEnabled(true);
        $this->vergeetGeheugen();
    }

    /** Een nieuw verzoek: geheugencache en gememoriseerde mtimes zijn leeg, APCu niet. */
    private function vergeetGeheugen(): void
    {
        $ref = new \ReflectionClass(JsonFormLoader::class);
        foreach (['cache', 'mtimeCache', 'dirListCache'] as $naam) {
            $prop = $ref->getProperty($naam);
            $prop->setAccessible(true);
            $prop->setValue(null, []);
        }
    }

    private function schrijf(string $titel, int $mtime): void
    {
        $pad = $this->dir . '/' . self::FORM . '.json';
        file_put_contents($pad, json_encode([
            'name' => self::FORM,
            'title' => $titel,
            'table' => 'tblZz',
            'fields' => [['name' => 'naam', 'type' => 'textbox', 'label' => $titel]],
        ]));
        touch($pad, $mtime);
        clearstatcache(true, $pad);
    }

    private function vereisApcu(): void
    {
        if ($this->dir === '') {
            $this->markTestSkipped('APCu niet beschikbaar in de CLI (apc.enable_cli)');
        }
    }

    public function testLoadRawZietGewijzigdeBronOndanksApcu(): void
    {
        $this->vereisApcu();
        $this->schrijf('Oud', time() - 100);
        $this->assertSame('Oud', JsonFormLoader::loadRaw(self::FORM)['title'] ?? null);

        $this->vergeetGeheugen();
        $this->schrijf('Nieuw', time());
        $this->assertSame('Nieuw', JsonFormLoader::loadRaw(self::FORM)['title'] ?? null);
    }

    public function testLoadZietGewijzigdeBronOndanksApcu(): void
    {
        $this->vereisApcu();
        $this->schrijf('Oud', time() - 100);
        $this->assertSame('Oud', JsonFormLoader::load(self::FORM)['Caption'][0] ?? null);

        $this->vergeetGeheugen();
        $this->schrijf('Nieuw', time());
        $this->assertSame('Nieuw', JsonFormLoader::load(self::FORM)['Caption'][0] ?? null);
    }

    public function testOngewijzigdeBronKomtUitApcu(): void
    {
        $this->vereisApcu();
        $this->schrijf('Oud', time() - 100);
        JsonFormLoader::load(self::FORM);

        // Inhoud veranderen maar de mtime gelijk houden: dan hoort APCu te antwoorden.
        $this->vergeetGeheugen();
        $this->schrijf('Nieuw', time() - 100);
        $this->assertSame('Oud', JsonFormLoader::load(self::FORM)['Caption'][0] ?? null);
    }
}
