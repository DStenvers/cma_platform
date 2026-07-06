<?php
/**
 * Unit tests for App\Library\Cache — FILE backend + pure logic only.
 *
 * Run with: php tests/TestRunner.php CacheTest
 *
 * The class supports redis / apcu / file backends, auto-selected in init().
 * We force the FILE backend by:
 *   - Application::set('cma_caching', true)      (caching enabled)
 *   - Application::set('cache_backend', 'file')  (skip redis/apcu auto-detect)
 *   - Application::set('cache_directory', <tmp>) (throwaway dir, never a real one)
 *
 * init() memoises the chosen backend in the private static $backend, so between
 * tests we reset ALL relevant static properties via reflection (resetStatics())
 * to force a clean lazy re-init against the fresh temp dir. No redis/apcu
 * extension is required or touched — this suite exercises only the file paths
 * and pure helpers.
 */

require_once __DIR__ . '/TestRunner.php';

use App\Library\Cache;

class CacheTest extends TestCase
{
    private string $tmpDir;
    /** @var mixed */
    private $savedApplication;

    public function setUp(): void
    {
        // Save whatever global config existed so we can restore it verbatim.
        $this->savedApplication = $GLOBALS['Application'] ?? null;

        $this->tmpDir = sys_get_temp_dir() . '/cache-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0755, true);

        // Fresh, minimal config forcing the file backend into our temp dir.
        $GLOBALS['Application'] = [];
        \App\Library\Application::set('cma_caching', true);
        \App\Library\Application::set('cache_backend', 'file');
        \App\Library\Application::set('cache_directory', $this->tmpDir);
        \App\Library\Application::set('cache_log_enabled', false);

        $this->resetStatics();
    }

    public function tearDown(): void
    {
        $this->resetStatics();

        // Restore global config exactly as it was.
        if ($this->savedApplication === null) {
            unset($GLOBALS['Application']);
        } else {
            $GLOBALS['Application'] = $this->savedApplication;
        }

        $this->rmrf($this->tmpDir);
    }

    /**
     * Reset Cache's private static state so init() re-runs cleanly each test.
     */
    private function resetStatics(): void
    {
        $reset = [
            'backend' => null,
            'redis' => null,
            'cacheDir' => null,
            'enabled' => true,
            'hits' => 0,
            'misses' => 0,
            'logEnabled' => null,
            'invalidationFile' => null,
            'invalidationCache' => [],
            'lastInvalidationCheck' => 0,
        ];
        foreach ($reset as $prop => $value) {
            $p = new \ReflectionProperty(Cache::class, $prop);
            $p->setAccessible(true);
            $p->setValue(null, $value);
        }
    }

    private function rmrf(string $path): void
    {
        if (!file_exists($path)) return;
        if (is_file($path) || is_link($path)) { @unlink($path); return; }
        foreach ((array)scandir($path) as $e) {
            if ($e === '.' || $e === '..') continue;
            $this->rmrf($path . '/' . $e);
        }
        @rmdir($path);
    }

    /** Compute the on-disk path Cache uses for a key (mirrors getCacheFilepath). */
    private function fileForKey(string $key): string
    {
        $hash = md5($key);
        return $this->tmpDir . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    // ---------------------------------------------------------------------
    // Backend selection
    // ---------------------------------------------------------------------

    public function testBackendIsFile(): void
    {
        $this->assertEquals('file', Cache::getBackend(), 'forced backend should be file');
    }

    public function testCachingIsEnabled(): void
    {
        $this->assertTrue(Cache::isEnabled());
    }

    // ---------------------------------------------------------------------
    // set / get round-trips for every value shape
    // ---------------------------------------------------------------------

    public function testSetGetString(): void
    {
        $this->assertTrue(Cache::set('k_str', 'hello world'));
        $this->assertEquals('hello world', Cache::get('k_str'));
    }

    public function testSetGetEmptyString(): void
    {
        Cache::set('k_empty', '');
        // '' !== null default, so it is a genuine stored value.
        $this->assertEquals('', Cache::get('k_empty', 'FALLBACK'));
    }

    public function testSetGetInt(): void
    {
        Cache::set('k_int', 42);
        $this->assertSame(42, Cache::get('k_int'));
    }

    public function testSetGetZeroInt(): void
    {
        Cache::set('k_zero', 0);
        $this->assertSame(0, Cache::get('k_zero', -1));
    }

    public function testSetGetFloat(): void
    {
        Cache::set('k_float', 3.14159);
        $this->assertSame(3.14159, Cache::get('k_float'));
    }

    public function testSetGetBooleanTrue(): void
    {
        Cache::set('k_true', true);
        $this->assertSame(true, Cache::get('k_true'));
    }

    public function testSetGetBooleanFalse(): void
    {
        // false must survive the getFile() `?? $default` (it does; only null trips it).
        Cache::set('k_false', false);
        $this->assertSame(false, Cache::get('k_false', 'FALLBACK'));
    }

    public function testSetGetArray(): void
    {
        $arr = ['a' => 1, 'b' => 2, 'c' => 3];
        Cache::set('k_arr', $arr);
        $this->assertEquals($arr, Cache::get('k_arr'));
    }

    public function testSetGetNestedArray(): void
    {
        $nested = [
            'level1' => [
                'level2' => [
                    'level3' => ['deep' => 'value', 'nums' => [1, 2, 3]],
                ],
                'sibling' => 'x',
            ],
            'top' => 99,
        ];
        Cache::set('k_nested', $nested);
        $this->assertEquals($nested, Cache::get('k_nested'));
    }

    public function testSetGetUnicode(): void
    {
        $unicode = 'café ☕ 日本語 — Ærøskøbing № 5';
        Cache::set('k_uni', $unicode);
        $this->assertEquals($unicode, Cache::get('k_uni'));
    }

    // ---------------------------------------------------------------------
    // misses / defaults
    // ---------------------------------------------------------------------

    public function testGetMissingReturnsNullByDefault(): void
    {
        $this->assertNull(Cache::get('does_not_exist'));
    }

    public function testGetMissingReturnsProvidedDefault(): void
    {
        $this->assertEquals('DEFAULT', Cache::get('does_not_exist', 'DEFAULT'));
    }

    // ---------------------------------------------------------------------
    // overwrite
    // ---------------------------------------------------------------------

    public function testOverwriteExistingKey(): void
    {
        Cache::set('k_over', 'first');
        $this->assertEquals('first', Cache::get('k_over'));
        Cache::set('k_over', 'second');
        $this->assertEquals('second', Cache::get('k_over'));
    }

    // ---------------------------------------------------------------------
    // delete
    // ---------------------------------------------------------------------

    public function testDeleteRemovesKey(): void
    {
        Cache::set('k_del', 'value');
        $this->assertTrue(Cache::delete('k_del'));
        $this->assertNull(Cache::get('k_del'));
    }

    public function testDeleteMissingKeyReturnsTrue(): void
    {
        // File backend treats a non-existent file as already-deleted (returns true).
        $this->assertTrue(Cache::delete('never_existed'));
    }

    // ---------------------------------------------------------------------
    // has
    // ---------------------------------------------------------------------

    public function testHasTrueForStoredKey(): void
    {
        Cache::set('k_has', 'value');
        $this->assertTrue(Cache::has('k_has'));
    }

    public function testHasFalseForMissingKey(): void
    {
        $this->assertFalse(Cache::has('k_absent'));
    }

    public function testHasFalseAfterDelete(): void
    {
        Cache::set('k_hd', 'value');
        Cache::delete('k_hd');
        $this->assertFalse(Cache::has('k_hd'));
    }

    // ---------------------------------------------------------------------
    // clear
    // ---------------------------------------------------------------------

    public function testClearEmptiesCache(): void
    {
        Cache::set('k1', 'a');
        Cache::set('k2', 'b');
        Cache::set('k3', 'c');
        $this->assertTrue(Cache::clear());
        $this->assertNull(Cache::get('k1'));
        $this->assertNull(Cache::get('k2'));
        $this->assertNull(Cache::get('k3'));
    }

    // ---------------------------------------------------------------------
    // TTL / expiry
    // ---------------------------------------------------------------------

    public function testLongTtlPersistsWithinTest(): void
    {
        Cache::set('k_long', 'still-here', 3600);
        $this->assertEquals('still-here', Cache::get('k_long'));
        $this->assertTrue(Cache::has('k_long'));
    }

    public function testNullTtlUsesDefaultAndPersists(): void
    {
        Cache::set('k_defttl', 'default-ttl', null);
        $this->assertEquals('default-ttl', Cache::get('k_defttl'));
    }

    public function testShortTtlExpires(): void
    {
        Cache::set('k_ttl', 'temporary', 1);
        // Present immediately.
        $this->assertEquals('temporary', Cache::get('k_ttl'));
        // Wait past expiry (expires = time()+1; getFile drops when expires < time()).
        sleep(2);
        $this->assertNull(Cache::get('k_ttl'), 'value should be gone after TTL');
        $this->assertFalse(Cache::has('k_ttl'), 'has() should be false after TTL');
    }

    // ---------------------------------------------------------------------
    // disabled caching
    // ---------------------------------------------------------------------

    public function testDisabledCacheReturnsDefaultOnGet(): void
    {
        Cache::set('k_en', 'value');
        Cache::setEnabled(false);
        $this->assertFalse(Cache::isEnabled());
        $this->assertEquals('MISS', Cache::get('k_en', 'MISS'));
    }

    public function testDisabledCacheSetReturnsFalse(): void
    {
        Cache::setEnabled(false);
        $this->assertFalse(Cache::set('k_dis', 'value'));
    }

    // ---------------------------------------------------------------------
    // stats
    // ---------------------------------------------------------------------

    public function testStatsCountHitsAndMisses(): void
    {
        Cache::resetStats();
        Cache::set('k_stat', 'v');
        Cache::get('k_stat');        // hit
        Cache::get('missing_stat');  // miss
        $stats = Cache::getStats();
        $this->assertEquals(1, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(2, $stats['total']);
    }

    // ---------------------------------------------------------------------
    // error handling — corrupt / unwritable
    // ---------------------------------------------------------------------

    public function testCorruptCacheFileReturnsDefaultAndDoesNotFatal(): void
    {
        Cache::set('k_corrupt', 'good-value');
        // Overwrite the on-disk file with unserializable garbage.
        $file = $this->fileForKey('k_corrupt');
        $this->assertTrue(file_exists($file), 'sanity: cache file should exist');
        file_put_contents($file, 'this is not valid serialized php {{{');

        $this->assertEquals('FALLBACK', Cache::get('k_corrupt', 'FALLBACK'));
        // has() must also cope with the corrupt file (unserialize false -> false).
        $this->assertFalse(Cache::has('k_corrupt'));
    }

    public function testSetToUnwritablePathFailsSafe(): void
    {
        // Force setFile()'s fopen('w') to fail by planting a DIRECTORY where the
        // cache file for this key is expected. fopen() on a dir returns false,
        // and setFile() returns false rather than throwing.
        $file = $this->fileForKey('k_block');
        mkdir(dirname($file), 0755, true);
        mkdir($file, 0755, true); // the file path is now a directory

        $this->assertFalse(Cache::set('k_block', 'value'), 'set should fail safe, not throw');
    }

    // ---------------------------------------------------------------------
    // raw file helpers (saveFile / loadFile / clearFile / clearAllFiles)
    // ---------------------------------------------------------------------

    public function testSaveAndLoadFileRoundTrip(): void
    {
        $this->assertTrue(Cache::saveFile('report1', "line-a\nline-b"));
        $this->assertEquals("line-a\nline-b", Cache::loadFile('report1'));
    }

    public function testLoadFileMissingReturnsNull(): void
    {
        $this->assertNull(Cache::loadFile('no_such_report'));
    }

    public function testLoadFileStripsBom(): void
    {
        // saveFile stores raw content; write a BOM-prefixed file directly.
        Cache::saveFile('bomfile', "\xEF\xBB\xBF" . 'content');
        $this->assertEquals('content', Cache::loadFile('bomfile'));
    }

    public function testClearFileRemovesIt(): void
    {
        Cache::saveFile('to_clear', 'data');
        $this->assertTrue(Cache::clearFile('to_clear'));
        $this->assertNull(Cache::loadFile('to_clear'));
    }

    public function testClearFileMissingReturnsTrue(): void
    {
        $this->assertTrue(Cache::clearFile('absent_file'));
    }

    public function testClearAllFilesRemovesRootCacheFiles(): void
    {
        Cache::saveFile('af1', 'a');
        Cache::saveFile('af2', 'b');
        $this->assertTrue(Cache::clearAllFiles());
        $this->assertNull(Cache::loadFile('af1'));
        $this->assertNull(Cache::loadFile('af2'));
    }

    // ---------------------------------------------------------------------
    // cross-instance invalidation (file-based, single process)
    // ---------------------------------------------------------------------

    public function testSetGetWithInvalidationRoundTrip(): void
    {
        $this->assertTrue(Cache::setWithInvalidation('iv_k', 'payload', 'forms'));
        $this->assertEquals('payload', Cache::getWithInvalidation('iv_k', 'forms', 'DEF'));
    }

    public function testInvalidateGroupExpiresEarlierEntries(): void
    {
        Cache::setWithInvalidation('iv_stale', 'old', 'forms');
        usleep(2000); // ensure invalidation timestamp is strictly later than createdAt
        $this->assertTrue(Cache::invalidateGroup('forms'));
        // Entry created before the invalidation is now considered stale.
        $this->assertEquals('DEF', Cache::getWithInvalidation('iv_stale', 'forms', 'DEF'));
    }

    public function testInvalidateGroupDoesNotAffectOtherGroups(): void
    {
        Cache::setWithInvalidation('iv_other', 'keep', 'menu');
        usleep(2000);
        Cache::invalidateGroup('forms'); // different group
        $this->assertEquals('keep', Cache::getWithInvalidation('iv_other', 'menu', 'DEF'));
    }

    public function testInvalidationSignalFileIsWritten(): void
    {
        Cache::invalidateGroup('security');
        $signalFile = $this->tmpDir . '/cache_invalidation.json';
        $this->assertTrue(file_exists($signalFile), 'invalidation signal file should be written');
        $data = json_decode(file_get_contents($signalFile), true);
        $this->assertArrayHasKey('security', $data);
    }

    // ---------------------------------------------------------------------
    // Documented quirk: storing NULL is lossy (getFile uses `?? $default`).
    // Not asserting this is "correct" — pinning current behaviour so a future
    // change is noticed. See report / suspected-bug note.
    // ---------------------------------------------------------------------

    public function testStoringNullIsLossyReturnsDefault(): void
    {
        Cache::set('k_null', null);
        // has() reports true (file exists, not expired)...
        $this->assertTrue(Cache::has('k_null'));
        // ...but get() cannot distinguish a stored null from a miss.
        $this->assertEquals('DEFAULT', Cache::get('k_null', 'DEFAULT'));
        $this->assertNull(Cache::get('k_null'));
    }
}
