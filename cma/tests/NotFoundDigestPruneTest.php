<?php
/**
 * NotFoundDigest::prune() — the 404 logs and their digest markers older than
 * NOTFOUND_LOG_RETENTION_DAYS are removed, by the date in the filename.
 *
 *   php cma/tests/TestRunner.php NotFoundDigestPruneTest
 */
require_once __DIR__ . '/TestRunner.php';

use App\Library\NotFoundDigest;

class NotFoundDigestPruneTest extends TestCase
{
    private string $dir;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/nf-prune-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    public function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testOldLogsAndMarkersGoRecentOnesStay(): void
    {
        $now = strtotime('2026-09-17 12:00:00');
        $files = [
            '404_2026-09-16.log' => true,   // yesterday: stays
            '404_2026-07-01.log' => false,  // 78 days: goes at 60
            'digest_2026-07-01.sent' => false,
            'digest_2026-09-10.sent' => true,
            '404_garbage.log' => true,      // no date in the name: never touched
        ];
        foreach ($files as $name => $_) {
            touch($this->dir . '/' . $name, $now - 200 * 86400); // mtime is deliberately old for all: it must not count
        }
        $removed = NotFoundDigest::prune($this->dir, 60, $now);
        $this->assertEquals(2, $removed);
        foreach ($files as $name => $stays) {
            $this->assertEquals($stays, is_file($this->dir . '/' . $name), $name);
        }
    }
}
