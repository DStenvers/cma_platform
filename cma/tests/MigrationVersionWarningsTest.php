<?php
/**
 * Regression test for MigrationService::detectVersionIssues() / getWarnings().
 *
 * Site-specific migrations must live in the reserved 0.x.x range; 1.0.0+ is
 * the platform's space. The service reports two advisories:
 *   - a site source using a version >= 1.0.0, and
 *   - the same version string defined by more than one source.
 *
 * Both are surfaced so a collision (which makes rerun-by-version ambiguous)
 * can't hide. The platform manifest really defines 1.0.0, so a test source
 * that also uses 1.0.0 triggers both advisories at once.
 *
 * Run with: php tests/TestRunner.php MigrationVersionWarningsTest
 */

require_once __DIR__ . '/TestRunner.php';
require_once __DIR__ . '/../classes/Services/MigrationService.php';

use Cma\Services\MigrationService;
use App\Library\Application;

class MigrationVersionWarningsTest extends TestCase
{
    private string $tmpDir = '';

    private function makeSource(array $migrations): array
    {
        $this->tmpDir = sys_get_temp_dir() . '/cma_migwarn_' . getmypid() . '_' . count($migrations);
        @mkdir($this->tmpDir, 0777, true);
        $file = $this->tmpDir . '/site_migrations.json';
        file_put_contents($file, json_encode(['migrations' => $migrations]));
        return ['name' => 'testsite', 'file' => $file, 'trackingDb' => 'data'];
    }

    private function warningsFor(array $migrations): array
    {
        $src = $this->makeSource($migrations);
        $prev = Application::get('migration_sources_extra', null);
        Application::set('migration_sources_extra', [$src]);
        try {
            $svc = new MigrationService();
            return $svc->getWarnings();
        } finally {
            Application::set('migration_sources_extra', $prev ?? []);
            @unlink($src['file']);
            @rmdir($this->tmpDir);
        }
    }

    public function testSiteVersionAtOrAbove_1_0_0_isFlagged(): void
    {
        $warnings = $this->warningsFor([
            ['version' => '1.0.0', 'description' => 'x', 'changes' => []],
        ]);
        $joined = implode("\n", $warnings);
        $this->assertStringContainsString('gereserveerd voor het platform', $joined);
    }

    public function testCrossSourceCollisionIsFlagged(): void
    {
        // 1.0.0 also exists in the platform manifest -> collision.
        $warnings = $this->warningsFor([
            ['version' => '1.0.0', 'description' => 'x', 'changes' => []],
        ]);
        $joined = implode("\n", $warnings);
        $this->assertStringContainsString('meerdere bronnen', $joined);
        $this->assertStringContainsString('platform', $joined);
        $this->assertStringContainsString('testsite', $joined);
    }

    public function testReservedRangeVersionIsClean(): void
    {
        // 0.1.0 is in the reserved site range and not used by the platform.
        $warnings = $this->warningsFor([
            ['version' => '0.1.0', 'description' => 'x', 'changes' => []],
        ]);
        $this->assertEquals([], $warnings, 'a 0.x.x site version must produce no advisories');
    }

    // ------------------------------------------------------------------
    // Additional coverage: platform-only cleanliness, high-version without
    // a cross-source collision, empty version strings ignored.
    // ------------------------------------------------------------------

    public function testPlatformOnlyProducesNoWarnings(): void
    {
        // With no extra sources, only the bundled platform manifest loads. It
        // must be internally consistent: no duplicate versions, and platform
        // owning 1.0.0+ is legitimate — so zero advisories.
        $prev = Application::get('migration_sources_extra', null);
        Application::set('migration_sources_extra', []);
        try {
            $svc = new MigrationService();
            $this->assertEquals([], $svc->getWarnings(), 'the platform manifest alone must be advisory-free');
        } finally {
            Application::set('migration_sources_extra', $prev ?? []);
        }
    }

    public function testHighSiteVersionWithoutCollisionOnlyFlagsReservedRange(): void
    {
        // 2.5.0 is >= 1.0.0 (reserved-range advisory) but not used by the
        // platform, so there is NO cross-source collision advisory.
        $warnings = $this->warningsFor([
            ['version' => '2.5.0', 'description' => 'x', 'changes' => []],
        ]);
        $joined = implode("\n", $warnings);
        $this->assertStringContainsString('gereserveerd voor het platform', $joined);
        $this->assertStringNotContainsString('meerdere bronnen', $joined);
        $this->assertEquals(1, count($warnings), 'exactly one advisory (reserved-range) expected');
    }

    public function testEmptyVersionStringIsIgnored(): void
    {
        // A migration with a blank version must not trip either advisory
        // (it's skipped entirely in detectVersionIssues).
        $warnings = $this->warningsFor([
            ['version' => '', 'description' => 'x', 'changes' => []],
        ]);
        $this->assertEquals([], $warnings);
    }
}
