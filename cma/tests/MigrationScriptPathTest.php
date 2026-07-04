<?php
/**
 * Regression test for MigrationService script-path resolution.
 *
 * A SITE-OWNED migration (registered via `migration_sources_extra`) carries
 * its runPhp/runSqlScript file next to its own manifest, OUTSIDE the
 * platform-synced cma/ tree. resolveScriptPath() must therefore:
 *   1. pass an absolute path through verbatim,
 *   2. prefer a path that exists relative to the source manifest's directory,
 *   3. otherwise fall back to cma/-relative (where bundled platform scripts
 *      live — this keeps the platform's own migrations working).
 *
 * Run with: php tests/TestRunner.php MigrationScriptPathTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/MigrationService.php';

use Cma\Services\MigrationService;

class MigrationScriptPathTest extends TestCase
{
    private function resolve(string $scriptPath, ?string $sourceDir): string
    {
        $svc = new MigrationService();
        $ref = new \ReflectionMethod($svc, 'resolveScriptPath');
        $ref->setAccessible(true);
        return $ref->invoke($svc, $scriptPath, $sourceDir);
    }

    public function testAbsolutePosixPathPassesThrough(): void
    {
        $this->assertEquals('/var/site/migrations/x.php', $this->resolve('/var/site/migrations/x.php', '/anything'));
    }

    public function testAbsoluteWindowsPathPassesThrough(): void
    {
        $this->assertEquals('C:\\site\\x.php', $this->resolve('C:\\site\\x.php', 'D:\\anything'));
    }

    public function testSiteOwnedScriptRelativeToManifestDirWins(): void
    {
        // A real file next to a "manifest" — the site-owned case.
        $dir = sys_get_temp_dir() . '/cma_migtest_' . getmypid();
        @mkdir($dir, 0777, true);
        $file = $dir . '/fix_beeld.php';
        file_put_contents($file, "<?php return true;");

        $resolved = $this->resolve('fix_beeld.php', $dir);
        $this->assertEquals($file, $resolved);

        @unlink($file);
        @rmdir($dir);
    }

    public function testFallsBackToCmaRelativeWhenNotNextToManifest(): void
    {
        // sourceDir given, but the file isn't there -> platform (cma/) fallback.
        $resolved = $this->resolve('migrations/1.0.1_create_cmamonitoring.php', '/no/such/dir');
        // Fallback shape is "<service>/../../migrations/..." i.e. cma-relative.
        $this->assertStringContainsString('/../../migrations/1.0.1_create_cmamonitoring.php', $resolved);
        // And it points at a file that really exists (the bundled platform script).
        $this->assertTrue(file_exists($resolved), 'platform fallback path should resolve to a real file');
    }

    public function testNullSourceDirUsesCmaRelative(): void
    {
        $resolved = $this->resolve('migrations/0.0.1_create_databases_json.php', null);
        $this->assertStringContainsString('/../../migrations/0.0.1_create_databases_json.php', $resolved);
        $this->assertTrue(file_exists($resolved));
    }
}
