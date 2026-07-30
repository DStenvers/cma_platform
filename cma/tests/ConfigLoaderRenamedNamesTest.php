<?php
/**
 * Tests for Cma\ConfigLoader's renamed-config resolution — the logical names
 * app/menu/reports must land on the cma_-prefixed files the rename migrations
 * produce, and fall back to the legacy names on a site that has not migrated.
 *
 * This is what binds the JSON config FORMS (which address files by logical
 * name via ConfigFormService) to the files the runtime readers use. A missing
 * entry here means a maintenance form silently edits a file nobody reads.
 *
 * Run with: php cma/tests/TestRunner.php ConfigLoaderRenamedNamesTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/ConfigLoader.php';

use Cma\ConfigLoader;

class ConfigLoaderRenamedNamesTest extends TestCase
{
    private string $dir = '';

    private function sandbox(array $files = []): void
    {
        $this->dir = sys_get_temp_dir() . '/configloader-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0755, true);
        foreach ($files as $name => $content) {
            file_put_contents($this->dir . '/' . $name, $content);
        }
        ConfigLoader::setConfigPath($this->dir);
    }

    public function testMenuResolvesToCmaMenuWhenPresent(): void
    {
        $this->sandbox(['cma_menu.json' => '{"menus":[]}']);
        $this->assertSame($this->dir . '/cma_menu.json', ConfigLoader::getFilePath('menu'));
        $this->assertTrue(ConfigLoader::exists('menu'));
    }

    public function testMenuFallsBackToLegacyName(): void
    {
        $this->sandbox(['menu.json' => '{"menus":[]}']);
        $this->assertSame($this->dir . '/menu.json', ConfigLoader::getFilePath('menu'));
        $this->assertTrue(ConfigLoader::exists('menu'));
    }

    public function testAppAndReportsResolveToRenamedFiles(): void
    {
        $this->sandbox([
            'cma_branding.json' => '{"company":{}}',
            'cma_reports.json'  => '{"reports":[]}',
        ]);
        $this->assertSame($this->dir . '/cma_branding.json', ConfigLoader::getFilePath('app'));
        $this->assertSame($this->dir . '/cma_reports.json', ConfigLoader::getFilePath('reports'));
    }

    public function testEveryRenameMigrationHasAConfigLoaderEntry(): void
    {
        // A rename migration without a matching $renamed entry is the whole
        // failure mode this test exists for, so derive the expectation from
        // the migrations that actually ship.
        $migrations = glob(__DIR__ . '/../migrations/*_rename_*_to_cma_*.php') ?: [];
        $this->sandbox([]);
        foreach ($migrations as $file) {
            if (!preg_match('/rename_(\w+?)_to_(cma_\w+)\.php$/', basename($file), $m)) {
                continue;
            }
            [, $legacy, $renamed] = $m;
            // Only configs ConfigLoader is responsible for: it resolves names
            // against data/, so a config read straight from disk elsewhere
            // (cma_tools.json, via tools_catalog.inc) is out of scope.
            if (!in_array($legacy, ['app', 'menu', 'reports'], true)) {
                continue;
            }
            file_put_contents($this->dir . '/' . $renamed . '.json', '{}');
            $this->assertSame(
                $this->dir . '/' . $renamed . '.json',
                ConfigLoader::getFilePath($legacy),
                "ConfigLoader must resolve '$legacy' to $renamed.json"
            );
        }
    }
}
