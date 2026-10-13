<?php
/**
 * The platform version shown in the CMA follows composer.json after a deploy.
 * A value cached in the session (no APCu) or in an APCu that was not flushed
 * must not survive a change of composer.json: the cache key carries its mtime.
 * Runs in a child process, because getPlatformVersion() memoizes per request.
 *
 *   php tests/TestRunner.php PlatformVersionCacheTest
 */
require_once __DIR__ . '/TestRunner.php';

class PlatformVersionCacheTest extends TestCase
{
    public function testStaleCachedVersionUnderOldKeyIsIgnored(): void
    {
        $root = realpath(__DIR__ . '/../..');
        $expected = json_decode((string) file_get_contents($root . '/composer.json'), true)['version'];
        $script = '<?php
            require ' . var_export($root . '/src/helpers/Bootstrap.php', true) . ';
            $_SESSION = ["cma_platform_version" => "0.0.1", "cma_platform_version_1" => "0.0.2"];
            if (function_exists("apcu_store")) { apcu_store("cma_platform_version", "0.0.1"); }
            echo \App\Library\Bootstrap::getPlatformVersion();
        ';
        $file = tempnam(sys_get_temp_dir(), 'pvc') . '.php';
        file_put_contents($file, $script);
        $out = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1');
        @unlink($file);
        $this->assertEquals($expected, trim((string) $out), 'version read from composer.json, not from the stale cache');
    }
}
