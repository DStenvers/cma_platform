<?php
/**
 * Het cache-log staat op één plek (<site>/.logs/cache/cache.log) en het dashboard zoekt het daar ook; de schakelaar uit de CMA geldt.
 *
 *   php cma/tests/TestRunner.php CacheLogLocatieTest
 */
require_once __DIR__ . '/TestRunner.php';

class CacheLogLocatieTest extends TestCase
{
    public function testCacheLogLivesUnderLogsCache(): int
    {
        $pad = str_replace('\\', '/', \App\Library\Cache::logFile());
        $this->assertTrue(substr($pad, -strlen('/.logs/cache/cache.log')) === '/.logs/cache/cache.log', 'cache log is <root>/.logs/cache/cache.log: ' . $pad);
        return 0;
    }

    public function testDashboardLooksWhereTheCacheWrites(): int
    {
        $src = (string) file_get_contents(dirname(__DIR__) . '/api/dashboard_stats.php');
        $this->assertTrue(substr_count($src, '\\App\\Library\\Cache::logFile()') >= 2, 'the dashboard asks Cache::logFile() for the cache log (status and recent misses)');
        $this->assertTrue(strpos($src, "'/cache/cache.log'") === false && strpos($src, "'/cache/debug.log'") === false, 'no more cache/cache.log or cache/debug.log');
        $this->assertTrue(strpos($src, "/.logs/debug/debug_' . date('Y-m-d')") !== false, 'the debug log is .logs/debug/debug_<date>.log, where cma/api/log.php writes it');
        return 0;
    }

    public function testCacheLoggingFollowsTheCmaSwitch(): int
    {
        $src = (string) file_get_contents(dirname(__DIR__, 2) . '/src/helpers/Cache.php');
        $this->assertTrue(strpos($src, 'SystemSettings::isCacheLogEnabled()') !== false, 'Cache logs when the CMA switch (system settings) is on');
        return 0;
    }
}
