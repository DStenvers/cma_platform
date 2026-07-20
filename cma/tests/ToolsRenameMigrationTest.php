<?php
/**
 * Tests for migration 9.17.0 (data/tools.json -> data/cma_tools.json).
 *
 * The migration accepts a pre-set $dataDir so it can run against a temp sandbox
 * instead of the real site data/ dir. Run with: php tests/TestRunner.php ToolsRenameMigrationTest
 */

require_once __DIR__ . '/TestRunner.php';

class ToolsRenameMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__ . '/../migrations/9.17.0_rename_tools_to_cma_tools.php';

    private function makeSandbox(): string
    {
        $dir = sys_get_temp_dir() . '/toolsrename_' . uniqid('', true) . '/data';
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

    private function runMigration(string $dataDir): void
    {
        include self::MIGRATION; // uses the $dataDir in this scope
    }

    public function testRenamesLegacyToolsToCmaTools(): void
    {
        $dataDir = $this->makeSandbox();
        file_put_contents($dataDir . '/tools.json', '{"groups":["x"]}');

        $this->runMigration($dataDir);

        $this->assertTrue(file_exists($dataDir . '/cma_tools.json'), 'cma_tools.json should exist after rename');
        $this->assertFalse(file_exists($dataDir . '/tools.json'), 'legacy tools.json should be gone');
        $this->assertEquals('{"groups":["x"]}', file_get_contents($dataDir . '/cma_tools.json'), 'contents preserved');

        $this->rmtree(dirname($dataDir));
    }

    public function testIdempotentWhenAlreadyRenamed(): void
    {
        $dataDir = $this->makeSandbox();
        file_put_contents($dataDir . '/cma_tools.json', '{"groups":["y"]}');

        $this->runMigration($dataDir);

        $this->assertTrue(file_exists($dataDir . '/cma_tools.json'), 'existing cma_tools.json untouched');
        $this->assertEquals('{"groups":["y"]}', file_get_contents($dataDir . '/cma_tools.json'), 'contents untouched');

        $this->rmtree(dirname($dataDir));
    }

    public function testCleansUpLingeringLegacyWhenNewAlreadyExists(): void
    {
        $dataDir = $this->makeSandbox();
        file_put_contents($dataDir . '/cma_tools.json', '{"groups":["new"]}');
        file_put_contents($dataDir . '/tools.json', '{"groups":["stale"]}');

        $this->runMigration($dataDir);

        $this->assertTrue(file_exists($dataDir . '/cma_tools.json'), 'new file kept');
        $this->assertFalse(file_exists($dataDir . '/tools.json'), 'stale legacy removed to avoid confusion');
        $this->assertEquals('{"groups":["new"]}', file_get_contents($dataDir . '/cma_tools.json'), 'new contents win');

        $this->rmtree(dirname($dataDir));
    }

    public function testNoopWhenNeitherPresent(): void
    {
        $dataDir = $this->makeSandbox();

        $this->runMigration($dataDir);

        $this->assertFalse(file_exists($dataDir . '/cma_tools.json'), 'no file created out of thin air');
        $this->assertFalse(file_exists($dataDir . '/tools.json'));

        $this->rmtree(dirname($dataDir));
    }
}
