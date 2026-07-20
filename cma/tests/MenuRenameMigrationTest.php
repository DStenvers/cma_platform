<?php
/**
 * Tests for migration 9.16.0 (data/menu.json -> data/cma_menu.json).
 *
 * The migration accepts a pre-set $dataDir so it can run against a temp sandbox
 * instead of the real site data/ dir. Run with: php tests/TestRunner.php MenuRenameMigrationTest
 */

require_once __DIR__ . '/TestRunner.php';

class MenuRenameMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__ . '/../migrations/9.16.0_rename_menu_to_cma_menu.php';

    private function makeSandbox(): string
    {
        $dir = sys_get_temp_dir() . '/menurename_' . uniqid('', true) . '/data';
        mkdir($dir, 0777, true);
        return $dir;
    }

    private function rmtree(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            is_dir($f) ? $this->rmtree($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    /** Runs the migration against a controlled $dataDir. */
    private function runMigration(string $dataDir): void
    {
        include self::MIGRATION; // uses the $dataDir in this scope
    }

    public function testRenamesLegacyMenuToCmaMenu(): void
    {
        $dataDir = $this->makeSandbox();
        file_put_contents($dataDir . '/menu.json', '{"menu":["x"]}');

        $this->runMigration($dataDir);

        $this->assertTrue(file_exists($dataDir . '/cma_menu.json'), 'cma_menu.json should exist after rename');
        $this->assertFalse(file_exists($dataDir . '/menu.json'), 'legacy menu.json should be gone');
        $this->assertEquals('{"menu":["x"]}', file_get_contents($dataDir . '/cma_menu.json'), 'contents preserved');

        $this->rmtree(dirname($dataDir));
    }

    public function testIdempotentWhenAlreadyRenamed(): void
    {
        $dataDir = $this->makeSandbox();
        file_put_contents($dataDir . '/cma_menu.json', '{"menu":["y"]}');

        $this->runMigration($dataDir); // no legacy file — must be a no-op

        $this->assertTrue(file_exists($dataDir . '/cma_menu.json'), 'existing cma_menu.json untouched');
        $this->assertEquals('{"menu":["y"]}', file_get_contents($dataDir . '/cma_menu.json'), 'contents untouched');

        $this->rmtree(dirname($dataDir));
    }

    public function testCleansUpLingeringLegacyWhenNewAlreadyExists(): void
    {
        $dataDir = $this->makeSandbox();
        file_put_contents($dataDir . '/cma_menu.json', '{"menu":["new"]}');
        file_put_contents($dataDir . '/menu.json', '{"menu":["stale"]}');

        $this->runMigration($dataDir);

        $this->assertTrue(file_exists($dataDir . '/cma_menu.json'), 'new file kept');
        $this->assertFalse(file_exists($dataDir . '/menu.json'), 'stale legacy removed to avoid confusion');
        $this->assertEquals('{"menu":["new"]}', file_get_contents($dataDir . '/cma_menu.json'), 'new contents win');

        $this->rmtree(dirname($dataDir));
    }

    public function testNoopWhenNeitherPresent(): void
    {
        $dataDir = $this->makeSandbox();

        $this->runMigration($dataDir); // nothing to do

        $this->assertFalse(file_exists($dataDir . '/cma_menu.json'), 'no file created out of thin air');
        $this->assertFalse(file_exists($dataDir . '/menu.json'));

        $this->rmtree(dirname($dataDir));
    }
}
