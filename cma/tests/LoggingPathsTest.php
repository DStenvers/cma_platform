<?php
/**
 * Guards the .logs/ consolidation: every log writer/reader resolves under the
 * site-root .logs/<type>/ — no more data/logs, cache/perf_logs or cma/logs. This is
 * a source-level guard (no class loading / filesystem side effects) so it stays green
 * in the bare TestRunner harness and fails loudly if a path regresses.
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
}
