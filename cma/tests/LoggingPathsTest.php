<?php
/**
 * Guards the dot-dir consolidation: every log writer/reader resolves under the
 * site-root .logs/<type>/ (no more data/logs, cache/perf_logs or cma/logs), the CMA
 * caches live under .cache/cma/ (not cache/cma/), and the Installer provisions the
 * dot-prefixed writable dirs. Source-level guard (no class loading / filesystem side
 * effects) so it stays green in the bare TestRunner harness and fails loudly on a
 * path regression.
 *
 * Run with: php tests/TestRunner.php LoggingPathsTest
 */

require_once __DIR__ . '/TestRunner.php';

class LoggingPathsTest extends TestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(__DIR__ . '/../' . $rel);
    }

    public function testPerformanceLoggerUsesDotLogsPerf(): void
    {
        $s = $this->src('classes/Services/PerformanceLogger.php');
        $this->assertStringContainsString("/.logs/perf", $s, 'PerformanceLogger must write under .logs/perf');
        $this->assertStringNotContainsString("/data/logs", $s, 'PerformanceLogger must not use data/logs');
    }

    public function testAppLoggerUsesDotLogsApp(): void
    {
        $s = $this->src('classes/Services/Logger.php');
        $this->assertStringContainsString("/.logs/app", $s, 'App Logger must write under .logs/app');
        $this->assertStringNotContainsString("/data/logs", $s, 'App Logger must not use data/logs');
    }

    public function testApiLogWritesDebugAndPerfUnderDotLogs(): void
    {
        $s = $this->src('api/log.php');
        $this->assertStringContainsString("/.logs", $s);
        $this->assertStringContainsString("/debug/debug_", $s, 'debug logs under .logs/debug');
        $this->assertStringContainsString("/perf/perf_", $s, 'perf logs under .logs/perf');
        $this->assertStringNotContainsString("/data/logs", $s);
    }

    public function testLogreaderReadsFromDotLogsOnly(): void
    {
        $s = $this->src('tools/logreader.php');
        foreach (['/perf/perf_', '/debug/debug_', '/404/404_', '/cache/cache.log', '/deploy/deploy.log', '/access/unauthorized_access.log'] as $needle) {
            $this->assertStringContainsString($needle, $s, "logreader must read $needle");
        }
        // The old, mismatched read locations must be gone.
        $this->assertStringNotContainsString("cache/perf_logs", $s);
        $this->assertStringNotContainsString("cmaLogsDir . '/debug_", $s);
    }

    public function testError404WritesUnderDotLogs404(): void
    {
        $s = $this->src('404.php');
        $this->assertStringContainsString("/.logs/404", $s, '404 log under .logs/404');
    }

    public function testPreferencesReadsDotLogsPerf(): void
    {
        $s = $this->src('preferences.php');
        $this->assertStringContainsString("/.logs/perf", $s, 'preferences must read perf logs from .logs/perf');
        $this->assertStringContainsString("/perf_", $s, 'preferences must use the perf_ filename prefix');
        $this->assertStringNotContainsString("__DIR__ . '/logs'", $s, 'preferences must not read cma/logs');
    }

    // ── #7: the deploy recovery-hatch templates write under .logs/deploy ─────────
    public function testDeployTemplatesUseDotLogsDeploy(): void
    {
        foreach (['../templates/deploy.php.template', '../templates/deploy_status.php.template'] as $rel) {
            $s = $this->src($rel);
            $this->assertStringContainsString("/.logs/deploy/deploy.log", $s, "$rel must use .logs/deploy/deploy.log");
            $this->assertStringNotContainsString("/logs/deploy.log", $s, "$rel must not use the old /logs/deploy.log");
            $this->assertStringNotContainsString("__DIR__ . '/logs'", $s, "$rel must not create /logs");
        }
    }

    // ── #7: the bootstrap scaffold log_dir points at .logs ───────────────────────
    public function testBootstrapTemplateLogDirIsDotLogs(): void
    {
        $s = $this->src('../templates/_bootstrap.php.template');
        $this->assertStringContainsString("'log_dir'        => __DIR__ . '/.logs'", $s, 'bootstrap log_dir must be .logs');
    }

    // ── #4 + #7: Installer provisions .cache/.logs (keeps sessions), not cache/logs ─
    public function testInstallerProvisionsDotDirs(): void
    {
        $s = $this->src('../src/Installer.php');
        $this->assertStringContainsString("['sessions', '.cache', '.logs']", $s, 'Installer must create sessions/.cache/.logs');
        $this->assertStringContainsString(".cache/cma/minify", $s, 'Installer clears .cache/cma/minify');
        $this->assertStringContainsString(".cache/cma/forms", $s, 'Installer clears .cache/cma/forms');
    }

    // ── #4: the CMA caches (minify + form defs) live under .cache/cma ─────────────
    public function testCmaCachesUseDotCache(): void
    {
        foreach (['minify.php', 'api/report-schema.php', 'classes/FormTemplate.php', 'classes/JsonFormLoader.php', 'tools/tools_clearcache.php'] as $rel) {
            $s = $this->src($rel);
            $this->assertStringContainsString(".cache/cma", $s, "$rel must use .cache/cma");
            // no bare cache/cma (a '/cache/cma' not preceded by a dot)
            $this->assertSame(0, preg_match('#[^.]cache/cma#', $s), "$rel must not use bare cache/cma");
        }
    }
}
